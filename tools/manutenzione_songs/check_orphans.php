#!/usr/bin/env php
<?php
/**
 * Manutenzione songs: trova file in player/song/ senza corrispondenza in DB (sg_file).
 *
 * Esempio: 42386.mp3 è valido se esiste una riga songs con sg_file = 42386.
 * I file sul disco il cui nome (senza .mp3) NON è in sg_file vengono elencati
 * come orfani (candidati all'eliminazione).
 *
 * Uso (CLI sul server Register):
 *   php check_orphans.php
 *
 * Genera orphans.html con PLAY / ELIMINA.
 * ELIMINA chiama delete_orphan.php (cancella solo quel file, dopo conferma URL).
 * Lo scan CLI non elimina nulla da solo.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Solo da CLI.\n";
    exit(1);
}

$songPath = '/var/www/vhosts/yourradio.org/httpdocs/player/song';
$reportPath = __DIR__ . '/orphans_' . date('Ymd_His') . '.txt';
$htmlPath = __DIR__ . '/orphans.html';
$htmlLatest = __DIR__ . '/orphans.html';
$baseUrl = 'https://yourradio.org/player/song';
$verbose = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--verbose' || $arg === '-v') {
        $verbose = true;
    } elseif (strpos($arg, '--song-path=') === 0) {
        $songPath = substr($arg, strlen('--song-path='));
    } elseif (strpos($arg, '--report=') === 0) {
        $reportPath = substr($arg, strlen('--report='));
    } elseif (strpos($arg, '--html=') === 0) {
        $htmlPath = substr($arg, strlen('--html='));
        $htmlLatest = $htmlPath;
    } elseif (strpos($arg, '--base-url=') === 0) {
        $baseUrl = rtrim(substr($arg, strlen('--base-url=')), '/');
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Uso: php check_orphans.php [--song-path=DIR] [--report=FILE] [--html=FILE] [--base-url=URL] [--verbose]\n";
        exit(0);
    }
}

$configCandidates = array(
    // preferito: config locale nella stessa cartella dello script
    __DIR__ . '/config.local.php',
    // sul Register: config sotto tools/inc
    __DIR__ . '/../inc/config.php',
    '/var/www/vhosts/yourradio.org/httpdocs/tools/inc/config.php',
    // relative all'admin / tools
    __DIR__ . '/../../inc/config.php',
    __DIR__ . '/../../api/config.php',
    // path tipici sul server centrale yourradio.org
    '/var/www/vhosts/yourradio.org/httpdocs/api/config.php',
    '/var/www/vhosts/yourradio.org/httpdocs/inc/config.php',
    '/var/www/yourradio/api/config.php',
    '/var/www/vhosts/admin.yourradio.org/httpdocs/inc/config.php',
);

$configLoaded = false;
$tried = array();
foreach ($configCandidates as $cfg) {
    $tried[] = $cfg;
    if (is_file($cfg)) {
        require_once $cfg;
        $configLoaded = true;
        echo "Config: $cfg\n";
        break;
    }
}

// Compat: config legacy Register usa $db_host / $db_user / ... invece di define()
if (!defined('DB_HOST') && isset($db_host)) {
    define('DB_HOST', $db_host);
}
if (!defined('DB_NAME') && isset($db_name)) {
    define('DB_NAME', $db_name);
}
if (!defined('DB_USER') && isset($db_user)) {
    define('DB_USER', $db_user);
}
if (!defined('DB_PASS') && isset($db_pass)) {
    define('DB_PASS', $db_pass);
}

if (!$configLoaded || !defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    fwrite(STDERR, "ERRORE: impossibile caricare DB config.\n");
    fwrite(STDERR, "Path provati:\n  - " . implode("\n  - ", $tried) . "\n");
    fwrite(STDERR, "Crea " . __DIR__ . "/config.local.php (vedi config.local.php.example)\n");
    exit(2);
}

if (!is_dir($songPath)) {
    fwrite(STDERR, "ERRORE: cartella song non trovata: $songPath\n");
    exit(2);
}

echo "Song path: $songPath\n";
echo "Carico sg_file dal DB...\n";

$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "ERRORE MySQL: " . $mysqli->connect_error . "\n");
    exit(2);
}
$mysqli->set_charset('utf8');

$sgFiles = array();
$sql = "SELECT sg_id, sg_file FROM songs WHERE sg_file IS NOT NULL AND sg_file <> '' AND sg_file <> '0'";
$result = $mysqli->query($sql);
if (!$result) {
    fwrite(STDERR, "ERRORE query: " . $mysqli->error . "\n");
    exit(2);
}

while ($row = $result->fetch_assoc()) {
    $key = (string) $row['sg_file'];
    // mappa sg_file -> sg_id (se duplicati, tiene l'ultimo)
    $sgFiles[$key] = (int) $row['sg_id'];
}
$result->free();
$mysqli->close();

echo "Song in DB con sg_file: " . count($sgFiles) . "\n";
echo "Scansione file su disco...\n";

$dh = opendir($songPath);
if ($dh === false) {
    fwrite(STDERR, "ERRORE: impossibile aprire $songPath\n");
    exit(2);
}

$onDisk = 0;
$matched = 0;
$orphans = array();
$skipped = 0;

while (($name = readdir($dh)) !== false) {
    if ($name === '.' || $name === '..') {
        continue;
    }
    $full = $songPath . '/' . $name;
    if (!is_file($full)) {
        continue;
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'mp3') {
        $skipped++;
        if ($verbose) {
            echo "SKIP (non mp3): $name\n";
        }
        continue;
    }

    $onDisk++;
    $base = pathinfo($name, PATHINFO_FILENAME); // es. 42386

    if (isset($sgFiles[$base])) {
        $matched++;
        if ($verbose) {
            echo "OK  $name  ->  sg_id=" . $sgFiles[$base] . " sg_file=$base\n";
        }
        continue;
    }

    $size = filesize($full);
    $orphans[] = array(
        'file' => $name,
        'sg_file_candidate' => $base,
        'size' => $size,
        'path' => $full,
    );
    echo "ORFANO  $name  (nessuna song con sg_file=$base)\n";
}
closedir($dh);

$report = array();
$report[] = '=== Manutenzione songs: file orfani (su disco, non in DB) ===';
$report[] = 'Data: ' . date('Y-m-d H:i:s');
$report[] = 'Song path: ' . $songPath;
$report[] = 'Song DB (con sg_file): ' . count($sgFiles);
$report[] = 'File .mp3 su disco: ' . $onDisk;
$report[] = 'Match OK: ' . $matched;
$report[] = 'Orfani: ' . count($orphans);
$report[] = 'Skip non-mp3: ' . $skipped;
$report[] = '';
$report[] = '# file\tsize_bytes\tsg_file_mancante\turl';

foreach ($orphans as $o) {
    $url = $baseUrl . '/' . rawurlencode($o['file']);
    $report[] = $o['file'] . "\t" . $o['size'] . "\t" . $o['sg_file_candidate'] . "\t" . $url;
}

$reportBody = implode("\n", $report) . "\n";
if (file_put_contents($reportPath, $reportBody) === false) {
    fwrite(STDERR, "WARN: impossibile scrivere report in $reportPath\n");
} else {
    echo "\nReport TXT: $reportPath\n";
}

// Pagina HTML: PLAY + ELIMINA (conferma = incolla link intero)
$when = htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8');
$html = '<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Song orfane</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { font-family: sans-serif; margin: 1.5rem; line-height: 1.4; color: #222; }
  h1 { font-size: 1.25rem; }
  table {
    border-collapse: collapse;
    table-layout: fixed;
    width: 280px;
  }
  col.c-play { width: 140px; }
  col.c-dl { width: 70px; }
  col.c-del { width: 70px; }
  td {
    padding: 2px 4px;
    border-bottom: 1px solid #e0e0e0;
    vertical-align: middle;
  }
  button, a.dl {
    box-sizing: border-box;
    display: block;
    width: 100%;
    font: inherit;
    font-size: 12px;
    line-height: 1.2;
    padding: 2px 6px;
    margin: 0;
    border: 1px solid #999;
    background: #f5f5f5;
    border-radius: 2px;
    text-align: center;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: inherit;
  }
  a.dl { border-color: #1565c0; background: #e3f2fd; color: #0d47a1; }
  a.dl i, button.del i { font-size: 14px; line-height: 1; }
  button.play { background: #e8f5e9; border-color: #2e7d32; }
  button.play.focused,
  button.play:focus {
    outline: 2px solid #1b5e20;
    outline-offset: 1px;
    background: #c8e6c9;
    box-shadow: inset 0 0 0 1px #1b5e20;
  }
  button.del { background: #ffebee; border-color: #c62828; color: #b71c1c; }
  button:disabled { opacity: 0.5; cursor: default; }
  .gone { opacity: 0.45; text-decoration: line-through; }
  .msg { margin-top: 1rem; color: #555; }
</style>
</head>
<body>
<h1>Song orfane</h1>
<p>Generato: ' . $when . ' — totale: <span id="total">' . count($orphans) . '</span></p>
';

if (count($orphans) === 0) {
    $html .= '<p>Nessun file orfano.</p>';
} else {
    $html .= '<table id="list">
<colgroup>
  <col class="c-play">
  <col class="c-dl">
  <col class="c-del">
</colgroup>
';
    foreach ($orphans as $o) {
        $id = $o['sg_file_candidate'];
        $fileName = $id . '.mp3';
        $url = $baseUrl . '/' . $fileName;
        $urlEsc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $idEsc = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $fileEsc = htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8');
        $html .= '<tr data-id="' . $idEsc . '">';
        $html .= '<td><button type="button" class="play" data-url="' . $urlEsc . '">' . $fileEsc . '</button></td>';
        $html .= '<td><a class="dl" href="' . $urlEsc . '" download="' . $fileEsc . '" title="Scarica" aria-label="Scarica"><i class="bi bi-download"></i></a></td>';
        $html .= '<td><button type="button" class="del" data-url="' . $urlEsc . '" disabled title="Elimina temporaneamente disabilitato" aria-label="Elimina"><i class="bi bi-trash"></i></button></td>';
        $html .= "</tr>\n";
    }
    $html .= "</table>\n";
}

$html .= '<p class="msg" id="msg"></p>
<script>
(function () {
  var list = document.getElementById("list");
  var msg = document.getElementById("msg");
  var totalEl = document.getElementById("total");
  if (!list) return;

  list.addEventListener("click", function (ev) {
    var btn = ev.target;
    if (btn && btn.closest) {
      var wrap = btn.closest("button.play, button.del");
      if (wrap) btn = wrap;
    }
    if (!btn || !btn.getAttribute) return;
    var url = btn.getAttribute("data-url");
    if (!url) return;

    if (btn.classList.contains("play")) {
      var prev = list.querySelectorAll("button.play.focused");
      for (var i = 0; i < prev.length; i++) {
        prev[i].classList.remove("focused");
      }
      btn.classList.add("focused");
      btn.focus();
      window.open(url, "player");
      return;
    }

    if (!btn.classList.contains("del")) return;
    return; // ELIMINA disabilitato per ora

    var typed = window.prompt(
      "ATTENZIONE: stai per cancellare QUESTO file dal server.\\n\\n" +
      "Per confermare, incolla qui sotto il link INTERO:\\n\\n" + url
    );
    if (typed === null) return;
    typed = typed.trim();
    if (typed !== url) {
      alert("Link non corrispondente. Cancellazione annullata.");
      return;
    }
    if (!window.confirm("Confermi definitivamente la cancellazione di:\\n" + url + " ?")) {
      return;
    }

    btn.disabled = true;
    msg.textContent = "Eliminazione in corso...";

    fetch("delete_orphan.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ confirm_url: typed })
    })
    .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
    .then(function (res) {
      if (!res.body || !res.body.ok) {
        var err = (res.body && res.body.error) ? res.body.error : ("HTTP " + res.status);
        msg.textContent = "Errore: " + err;
        btn.disabled = false;
        return;
      }
      var row = btn.closest("tr");
      if (row) {
        row.classList.add("gone");
        row.querySelectorAll("button").forEach(function (b) { b.disabled = true; });
      }
      if (totalEl) {
        var n = parseInt(totalEl.textContent, 10) || 0;
        totalEl.textContent = Math.max(0, n - 1);
      }
      msg.textContent = "Eliminato: " + url;
    })
    .catch(function (e) {
      msg.textContent = "Errore di rete: " + e;
      btn.disabled = false;
    });
  });
})();
</script>
</body>
</html>
';

if (file_put_contents($htmlPath, $html) === false) {
    fwrite(STDERR, "WARN: impossibile scrivere HTML in $htmlPath\n");
} else {
    echo "Report HTML: $htmlPath\n";
    echo "Apri: https://yourradio.org/tools/manutenzione_songs/orphans.html\n";
}

echo "\n=== RIEPILOGO ===\n";
echo "File .mp3 su disco : $onDisk\n";
echo "Match OK           : $matched\n";
echo "Orfani (da valutare): " . count($orphans) . "\n";
echo "Skip non-mp3       : $skipped\n";

exit(0);
