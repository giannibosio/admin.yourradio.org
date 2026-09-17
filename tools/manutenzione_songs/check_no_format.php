<?php
/**
 * Manutenzione songs: elenca song in DB senza alcun format (nessuna riga in song_format).
 *
 * Browser:
 *   https://yourradio.org/tools/manutenzione_songs/check_no_format.php
 *
 * CLI (stesso output HTML, oppure --txt per report file):
 *   php check_no_format.php
 *   php check_no_format.php --txt
 *
 * Solo lettura su DB: non modifica nulla.
 */

require_once __DIR__ . '/_bootstrap.php';
manutenzione_load_config();

$isCli = (php_sapi_name() === 'cli');
$wantTxt = false;
$baseUrl = 'https://yourradio.org/player/song';
$adminUrl = 'https://admin.yourradio.org/song-scheda.php';

if ($isCli && isset($argv) && is_array($argv)) {
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--txt') {
            $wantTxt = true;
        } elseif (strpos($arg, '--base-url=') === 0) {
            $baseUrl = rtrim(substr($arg, strlen('--base-url=')), '/');
        } elseif (strpos($arg, '--admin-url=') === 0) {
            $adminUrl = rtrim(substr($arg, strlen('--admin-url=')), '/');
        } elseif ($arg === '--help' || $arg === '-h') {
            echo "Uso: php check_no_format.php [--txt] [--base-url=URL] [--admin-url=URL]\n";
            echo "Web:  apri check_no_format.php nel browser\n";
            exit(0);
        }
    }
}

if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    $msg = "ERRORE: config DB mancante.";
    if ($isCli) {
        fwrite(STDERR, $msg . "\n");
        exit(2);
    }
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo $msg;
    exit;
}

$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    $msg = "ERRORE MySQL: " . $mysqli->connect_error;
    if ($isCli) {
        fwrite(STDERR, $msg . "\n");
        exit(2);
    }
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo $msg;
    exit;
}
$mysqli->set_charset('utf8');

$sql = "SELECT s.sg_id, s.sg_file, s.sg_titolo, s.sg_artista, s.sg_attivo
        FROM songs s
        LEFT JOIN song_format sf ON sf.id_song = s.sg_id
        WHERE sf.id_song IS NULL
        ORDER BY s.sg_id ASC";

$result = $mysqli->query($sql);
if (!$result) {
    $msg = "ERRORE query: " . $mysqli->error;
    $mysqli->close();
    if ($isCli) {
        fwrite(STDERR, $msg . "\n");
        exit(2);
    }
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo $msg;
    exit;
}

$rows = array();
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$result->free();
$mysqli->close();

if ($wantTxt && $isCli) {
    $reportPath = __DIR__ . '/no_format_' . date('Ymd_His') . '.txt';
    $lines = array();
    $lines[] = '=== Song senza format ===';
    $lines[] = 'Data: ' . date('Y-m-d H:i:s');
    $lines[] = 'Totale: ' . count($rows);
    $lines[] = '';
    $lines[] = "# sg_id\tsg_file\tsg_attivo\tsg_artista\tsg_titolo";
    foreach ($rows as $r) {
        $lines[] = $r['sg_id'] . "\t" . $r['sg_file'] . "\t" . $r['sg_attivo'] . "\t"
            . str_replace(array("\t", "\n", "\r"), ' ', $r['sg_artista']) . "\t"
            . str_replace(array("\t", "\n", "\r"), ' ', $r['sg_titolo']);
    }
    file_put_contents($reportPath, implode("\n", $lines) . "\n");
    echo "Report TXT: $reportPath\n";
    echo "Totale: " . count($rows) . "\n";
    exit(0);
}

$when = htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Song senza format</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { font-family: sans-serif; margin: 1.5rem; line-height: 1.4; color: #222; }
  h1 { font-size: 1.25rem; }
  table {
    border-collapse: collapse;
    table-layout: fixed;
    width: 700px;
  }
  col.c-id { width: 70px; }
  col.c-play { width: 120px; }
  col.c-dl { width: 50px; }
  col.c-meta { width: 460px; }
  th, td {
    padding: 2px 4px;
    border-bottom: 1px solid #e0e0e0;
    vertical-align: middle;
    font-size: 12px;
    text-align: left;
  }
  th { color: #666; font-weight: normal; }
  button.play, a.edit, a.dl {
    box-sizing: border-box;
    display: inline-block;
    width: 100%;
    font: inherit;
    font-size: 12px;
    line-height: 1.2;
    padding: 2px 6px;
    margin: 0;
    border: 1px solid #2e7d32;
    background: #e8f5e9;
    border-radius: 2px;
    text-align: center;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #1b5e20;
  }
  button.play.focused, button.play:focus {
    outline: 2px solid #1b5e20;
    outline-offset: 1px;
    background: #c8e6c9;
  }
  button.play:disabled,
  a.dl.disabled {
    opacity: 0.4; cursor: default; border-color: #999; background: #eee; color: #777;
    pointer-events: none;
  }
  a.edit { border-color: #1565c0; background: #e3f2fd; color: #0d47a1; width: auto; padding: 2px 8px; }
  a.dl { border-color: #1565c0; background: #e3f2fd; color: #0d47a1; }
  a.dl i { font-size: 14px; }
  .meta { color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .muted { color: #888; }
  .filters { margin: 0.75rem 0 1rem; display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
  .switch {
    display: inline-flex; align-items: center; gap: 0.5rem;
    cursor: pointer; user-select: none; font-size: 0.95rem;
  }
  .switch input { position: absolute; opacity: 0; width: 0; height: 0; }
  .slider {
    position: relative; width: 40px; height: 22px;
    background: #ccc; border-radius: 22px; transition: background 0.15s;
    flex-shrink: 0;
  }
  .slider::after {
    content: ""; position: absolute; top: 2px; left: 2px;
    width: 18px; height: 18px; background: #fff; border-radius: 50%;
    transition: transform 0.15s;
  }
  .switch input:checked + .slider { background: #c62828; }
  .switch input:checked + .slider::after { transform: translateX(18px); }
  .switch input:focus + .slider { outline: 2px solid #999; outline-offset: 2px; }
  tr.is-hidden { display: none; }
</style>
</head>
<body>
<h1>Song senza format</h1>
<p>Generato: <?php echo $when; ?> — totale: <strong id="total-all"><?php echo count($rows); ?></strong>
 — visibili: <strong id="total-visible"><?php echo count($rows); ?></strong>
 — <a href="?">aggiorna</a></p>
<?php
$disabledCount = 0;
foreach ($rows as $_r) {
    if ((string) $_r['sg_attivo'] !== '1') {
        $disabledCount++;
    }
}
?>
<?php if (count($rows) === 0): ?>
<p>Nessuna song senza format.</p>
<?php else: ?>
<div class="filters">
  <label class="switch" title="Mostra solo song con sg_attivo diverso da 1">
    <input type="checkbox" id="filter-disabled">
    <span class="slider"></span>
    <span>Solo DISABILITATE <span class="muted">(<?php echo (int) $disabledCount; ?>)</span></span>
  </label>
</div>
<table id="list">
<colgroup>
  <col class="c-id">
  <col class="c-play">
  <col class="c-dl">
  <col class="c-meta">
</colgroup>
<thead><tr><th>sg_id</th><th>file</th><th></th><th>artista — titolo</th></tr></thead>
<tbody>
<?php foreach ($rows as $r):
    $sgId = (int) $r['sg_id'];
    $sgFile = trim((string) $r['sg_file']);
    $titoloRaw = (string) $r['sg_titolo'];
    $artistaRaw = (string) $r['sg_artista'];
    $titolo = htmlspecialchars($titoloRaw, ENT_QUOTES, 'UTF-8');
    $artista = htmlspecialchars($artistaRaw, ENT_QUOTES, 'UTF-8');
    $isAttivo = ((string) $r['sg_attivo'] === '1');
    $attivo = $isAttivo ? '' : ' <span class="muted">(non attiva)</span>';
    $dlName = manutenzione_download_filename($artistaRaw, $titoloRaw, $sgFile !== '' ? $sgFile : (string) $sgId);
    $dlNameEsc = htmlspecialchars($dlName, ENT_QUOTES, 'UTF-8');
?>
<tr data-attivo="<?php echo $isAttivo ? '1' : '0'; ?>">
  <td><a class="edit" href="<?php echo htmlspecialchars($adminUrl . '?id=' . $sgId, ENT_QUOTES, 'UTF-8'); ?>" target="scheda" title="Apri scheda"><?php echo $sgId; ?></a></td>
  <td>
<?php if ($sgFile !== '' && $sgFile !== '0'):
    $fileName = $sgFile . '.mp3';
    $url = $baseUrl . '/' . $fileName;
    $urlEsc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $fileEsc = htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8');
?>
    <button type="button" class="play" data-url="<?php echo $urlEsc; ?>"><?php echo $fileEsc; ?></button>
  </td>
  <td>
    <a class="dl" href="download_as.php?id=<?php echo $sgId; ?>" title="Scarica come: <?php echo $dlNameEsc; ?>" aria-label="Scarica"><i class="bi bi-download"></i></a>
<?php else: ?>
    <button type="button" class="play" disabled>—</button>
  </td>
  <td>
    <a class="dl disabled" href="#" title="Nessun file" aria-label="Scarica non disponibile"><i class="bi bi-download"></i></a>
<?php endif; ?>
  </td>
  <td class="meta"><?php echo $artista; ?> — <?php echo $titolo . $attivo; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
<script>
(function () {
  var list = document.getElementById("list");
  var filter = document.getElementById("filter-disabled");
  var totalVisible = document.getElementById("total-visible");

  function applyFilter() {
    if (!list) return;
    var onlyDisabled = filter && filter.checked;
    var rows = list.querySelectorAll("tbody tr");
    var visible = 0;
    for (var i = 0; i < rows.length; i++) {
      var attivo = rows[i].getAttribute("data-attivo");
      var hide = onlyDisabled && attivo === "1";
      if (hide) {
        rows[i].classList.add("is-hidden");
      } else {
        rows[i].classList.remove("is-hidden");
        visible++;
      }
    }
    if (totalVisible) totalVisible.textContent = String(visible);
  }

  if (filter) {
    filter.addEventListener("change", applyFilter);
  }

  if (!list) return;
  list.addEventListener("click", function (ev) {
    var btn = ev.target;
    if (btn && btn.closest) {
      var wrap = btn.closest("button.play");
      if (wrap) btn = wrap;
    }
    if (!btn || !btn.classList || !btn.classList.contains("play")) return;
    if (btn.disabled) return;
    var url = btn.getAttribute("data-url");
    if (!url) return;
    var prev = list.querySelectorAll("button.play.focused");
    for (var i = 0; i < prev.length; i++) prev[i].classList.remove("focused");
    btn.classList.add("focused");
    btn.focus();
    window.open(url, "player");
  });
})();
</script>
</body>
</html>
<?php
if ($isCli) {
    // in CLI l'HTML è già stato stampato su stdout
}
exit(0);
