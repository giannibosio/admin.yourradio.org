<?php
/**
 * Manutenzione songs: elenca song in DB SENZA file audio in player/song/.
 *
 * Controlla TUTTE le righe della tabella songs (solo SELECT).
 * Solo lettura: NON modifica DB né file.
 *
 * Browser: https://yourradio.org/tools/manutenzione_songs/check_missing_files.php
 * Export:  ?export=csv  |  ?export=pdf
 */

require_once __DIR__ . '/_bootstrap.php';
manutenzione_load_config();

$isCli = (php_sapi_name() === 'cli');
$wantTxt = false;
$export = '';
$songPath = manutenzione_song_path();
$adminSongsUrl = 'https://admin.yourradio.org/songs.php';

if (!$isCli && isset($_GET['export'])) {
    $export = strtolower(trim((string) $_GET['export']));
}

if ($isCli && isset($argv) && is_array($argv)) {
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--txt') {
            $wantTxt = true;
        } elseif (strpos($arg, '--song-path=') === 0) {
            $songPath = rtrim(substr($arg, strlen('--song-path=')), '/');
        } elseif (strpos($arg, '--export=') === 0) {
            $export = strtolower(substr($arg, strlen('--export=')));
        } elseif ($arg === '--help' || $arg === '-h') {
            echo "Uso: php check_missing_files.php [--txt] [--export=csv|pdf] [--song-path=DIR]\n";
            exit(0);
        }
    }
}

function fail_msg($msg, $isCli)
{
    if ($isCli) {
        fwrite(STDERR, $msg . "\n");
        exit(2);
    }
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo $msg;
    exit;
}

function diritti_label($code)
{
    $map = array(
        '0' => 'SIAE',
        '1' => 'CREATIVE C',
        '2' => 'SOUNDREEF',
        '3' => 'WATERMELON',
        '4' => 'YOURRADIO',
    );
    $k = (string) $code;
    return isset($map[$k]) ? $map[$k] : ('?' . $k);
}

if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    fail_msg('ERRORE: config DB mancante.', $isCli);
}
if (!is_dir($songPath)) {
    fail_msg('ERRORE: cartella song non trovata: ' . $songPath, $isCli);
}

$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    fail_msg('ERRORE MySQL: ' . $mysqli->connect_error, $isCli);
}
$mysqli->set_charset('utf8');

$sql = "SELECT sg_id, sg_file, sg_titolo, sg_artista, sg_attivo, sg_diritti
        FROM songs
        ORDER BY sg_id ASC";

$result = $mysqli->query($sql);
if (!$result) {
    $err = $mysqli->error;
    $mysqli->close();
    fail_msg('ERRORE query: ' . $err, $isCli);
}

$checked = 0;
$missing = array();

while ($row = $result->fetch_assoc()) {
    $checked++;
    $sgFile = trim((string) $row['sg_file']);
    if ($sgFile === '' || $sgFile === '0') {
        $row['reason'] = 'sg_file vuoto';
        $row['diritti_label'] = diritti_label(isset($row['sg_diritti']) ? $row['sg_diritti'] : '');
        $row['formats_label'] = '';
        $missing[] = $row;
        continue;
    }
    $path = $songPath . '/' . $sgFile . '.mp3';
    if (!is_file($path)) {
        $row['reason'] = 'file assente';
        $row['diritti_label'] = diritti_label(isset($row['sg_diritti']) ? $row['sg_diritti'] : '');
        $row['formats_label'] = '';
        $missing[] = $row;
    }
}
$result->free();

// Format abbinati (solo per le song senza file) — ancora sola lettura
if (count($missing) > 0) {
    $ids = array();
    foreach ($missing as $m) {
        $ids[] = (int) $m['sg_id'];
    }
    $ids = array_values(array_unique($ids));
    $formatsBySong = array();
    // chunk per evitare query troppo lunghe
    $chunkSize = 500;
    for ($i = 0; $i < count($ids); $i += $chunkSize) {
        $chunk = array_slice($ids, $i, $chunkSize);
        $inList = implode(',', $chunk);
        $fq = "SELECT sf.id_song, GROUP_CONCAT(DISTINCT f.frmt_nome ORDER BY f.frmt_nome SEPARATOR ', ') AS formats_label
               FROM song_format sf
               INNER JOIN `format` f ON f.frmt_id = sf.id_format
               WHERE sf.id_song IN ($inList)
               GROUP BY sf.id_song";
        $fres = $mysqli->query($fq);
        if ($fres) {
            while ($fr = $fres->fetch_assoc()) {
                $formatsBySong[(int) $fr['id_song']] = (string) $fr['formats_label'];
            }
            $fres->free();
        }
    }
    for ($j = 0; $j < count($missing); $j++) {
        $sid = (int) $missing[$j]['sg_id'];
        $missing[$j]['formats_label'] = isset($formatsBySong[$sid]) ? $formatsBySong[$sid] : '—';
    }
}

$mysqli->close();

$disabledCount = 0;
$activeCount = 0;
foreach ($missing as $_r) {
    if ((string) $_r['sg_attivo'] === '1') {
        $activeCount++;
    } else {
        $disabledCount++;
    }
}

// --- Export CSV (Excel) ---
if ($export === 'csv' || $export === 'excel' || $export === 'xlsx') {
    $filename = 'song_senza_file_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo "\xEF\xBB\xBF"; // BOM UTF-8 per Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, array('sg_id', 'sg_file', 'attivo', 'diritti', 'formats', 'artista', 'titolo', 'motivo'), ';');
    foreach ($missing as $r) {
        fputcsv($out, array(
            $r['sg_id'],
            $r['sg_file'],
            ((string) $r['sg_attivo'] === '1') ? 'SI' : 'NO',
            isset($r['diritti_label']) ? $r['diritti_label'] : diritti_label($r['sg_diritti']),
            isset($r['formats_label']) ? $r['formats_label'] : '',
            $r['sg_artista'],
            $r['sg_titolo'],
            $r['reason'],
        ), ';');
    }
    fclose($out);
    exit(0);
}

// --- Export PDF (pagina stampabile → Salva come PDF / Stampa) ---
if ($export === 'pdf') {
    $when = htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><title>Song senza file</title>
<style>
  body{font-family:Helvetica,Arial,sans-serif;font-size:11px;color:#111;margin:16px}
  h1{font-size:16px;margin:0 0 8px}
  table{border-collapse:collapse;width:100%}
  th,td{border:1px solid #ccc;padding:4px 6px;text-align:left}
  th{background:#eee}
  @media print{ .noprint{display:none} }
</style></head><body>
<div class="noprint" style="margin-bottom:12px">
  <button onclick="window.print()">Stampa / Salva come PDF</button>
  <a href="?" style="margin-left:12px">← Torna all&apos;elenco</a>
</div>
<h1>Song in DB senza file audio</h1>
<p>Generato: ' . $when . ' — Totale: ' . count($missing) . ' / controllate ' . (int) $checked . '</p>
<table><thead><tr>
<th>sg_id</th><th>sg_file</th><th>Attiva</th><th>Diritti</th><th>Format</th><th>Artista</th><th>Titolo</th><th>Motivo</th>
</tr></thead><tbody>';
    foreach ($missing as $r) {
        echo '<tr>';
        echo '<td>' . (int) $r['sg_id'] . '</td>';
        echo '<td>' . htmlspecialchars((string) $r['sg_file'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . (((string) $r['sg_attivo'] === '1') ? 'SI' : 'NO') . '</td>';
        echo '<td>' . htmlspecialchars($r['diritti_label'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars(isset($r['formats_label']) ? $r['formats_label'] : '', ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $r['sg_artista'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $r['sg_titolo'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $r['reason'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>
<script>window.addEventListener("load",function(){ setTimeout(function(){ window.print(); }, 300); });</script>
</body></html>';
    exit(0);
}

if ($wantTxt && $isCli) {
    $reportPath = __DIR__ . '/missing_files_' . date('Ymd_His') . '.txt';
    $lines = array();
    $lines[] = '=== Song in DB senza file audio ===';
    $lines[] = 'Data: ' . date('Y-m-d H:i:s');
    $lines[] = 'Song path: ' . $songPath;
    $lines[] = 'Song controllate: ' . $checked;
    $lines[] = 'Senza file: ' . count($missing);
    $lines[] = '';
    $lines[] = "# sg_id\tsg_file\tsg_attivo\tdiritti\tformats\tsg_artista\tsg_titolo\treason";
    foreach ($missing as $r) {
        $lines[] = $r['sg_id'] . "\t" . $r['sg_file'] . "\t" . $r['sg_attivo'] . "\t"
            . $r['diritti_label'] . "\t"
            . str_replace(array("\t", "\n", "\r"), ' ', isset($r['formats_label']) ? $r['formats_label'] : '') . "\t"
            . str_replace(array("\t", "\n", "\r"), ' ', $r['sg_artista']) . "\t"
            . str_replace(array("\t", "\n", "\r"), ' ', $r['sg_titolo']) . "\t"
            . $r['reason'];
    }
    file_put_contents($reportPath, implode("\n", $lines) . "\n");
    echo "Report TXT: $reportPath\n";
    echo "Controllate: $checked — Senza file: " . count($missing) . "\n";
    exit(0);
}

$when = htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8');
$songPathEsc = htmlspecialchars($songPath, ENT_QUOTES, 'UTF-8');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Song senza file audio</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  :root {
    --bg: #f4f6f8;
    --card: #ffffff;
    --text: #1a1d21;
    --muted: #6b7280;
    --line: #e5e7eb;
    --blue: #1d4ed8;
    --blue-bg: #eff6ff;
    --green: #15803d;
    --green-bg: #ecfdf5;
    --red: #b91c1c;
    --red-bg: #fef2f2;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.45;
  }
  .wrap { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }
  .card {
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 1.25rem 1.4rem;
    box-shadow: 0 1px 2px rgba(0,0,0,.04);
  }
  h1 { font-size: 1.35rem; margin: 0 0 .35rem; font-weight: 650; }
  .sub { color: var(--muted); font-size: .92rem; margin: 0 0 1rem; }
  .stats { display: flex; flex-wrap: wrap; gap: .75rem; margin: 0 0 1rem; }
  .stat {
    background: var(--blue-bg);
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: .45rem .75rem;
    font-size: .9rem;
  }
  .stat strong { font-size: 1.05rem; }
  .toolbar {
    display: flex; flex-wrap: wrap; gap: .75rem 1.25rem;
    align-items: center; margin: 0 0 1rem;
  }
  .switch {
    display: inline-flex; align-items: center; gap: .5rem;
    cursor: pointer; user-select: none; font-size: .92rem;
  }
  .switch input { position: absolute; opacity: 0; width: 0; height: 0; }
  .slider {
    position: relative; width: 40px; height: 22px;
    background: #d1d5db; border-radius: 22px; transition: background .15s;
    flex-shrink: 0;
  }
  .slider::after {
    content: ""; position: absolute; top: 2px; left: 2px;
    width: 18px; height: 18px; background: #fff; border-radius: 50%;
    transition: transform .15s; box-shadow: 0 1px 2px rgba(0,0,0,.2);
  }
  .switch input:checked + .slider.red { background: var(--red); }
  .switch input:checked + .slider.green { background: var(--green); }
  .switch input:checked + .slider::after { transform: translateX(18px); }
  .exports { display: flex; gap: .5rem; margin-left: auto; }
  .btn {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .4rem .7rem; border-radius: 6px; border: 1px solid var(--line);
    background: #fff; color: var(--text); text-decoration: none;
    font-size: .88rem; cursor: pointer;
  }
  .btn:hover { background: #f9fafb; }
  .btn.primary { background: var(--blue); border-color: var(--blue); color: #fff; }
  .btn.primary:hover { filter: brightness(1.05); }
  table { border-collapse: collapse; width: 100%; background: #fff; }
  th, td {
    padding: .55rem .6rem; border-bottom: 1px solid var(--line);
    text-align: left; font-size: .88rem; vertical-align: middle;
  }
  th { color: var(--muted); font-weight: 600; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; }
  tr:hover td { background: #fafafa; }
  tr.is-hidden { display: none; }
  .id-link {
    display: inline-block; min-width: 3.2rem; text-align: center;
    padding: .2rem .45rem; border-radius: 5px;
    background: var(--blue-bg); color: var(--blue); text-decoration: none;
    font-weight: 600; border: 1px solid #bfdbfe;
  }
  .id-link:hover { background: #dbeafe; }
  .file { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8rem; color: #374151; }
  .formats { font-size: .8rem; color: #374151; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .badge {
    display: inline-block; padding: .12rem .45rem; border-radius: 999px;
    font-size: .75rem; font-weight: 600;
  }
  .badge.on { background: var(--green-bg); color: var(--green); }
  .badge.off { background: var(--red-bg); color: var(--red); }
  .badge.dir { background: #f3f4f6; color: #374151; }
  .muted { color: var(--muted); }
  .note { font-size: .82rem; color: var(--muted); margin-top: .75rem; }
  .empty { padding: 1rem 0; color: var(--muted); }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Song in DB senza file audio</h1>
    <p class="sub">Generato: <?php echo $when; ?> · Cartella: <code><?php echo $songPathEsc; ?></code> · <a href="?">aggiorna</a></p>

    <div class="stats">
      <div class="stat">Controllate: <strong><?php echo (int) $checked; ?></strong></div>
      <div class="stat">Senza file: <strong id="total-all"><?php echo count($missing); ?></strong></div>
      <div class="stat">Visibili: <strong id="total-visible"><?php echo count($missing); ?></strong></div>
      <div class="stat">Attive in elenco: <strong><?php echo (int) $activeCount; ?></strong></div>
      <div class="stat">Disabilitate in elenco: <strong><?php echo (int) $disabledCount; ?></strong></div>
    </div>

<?php if (count($missing) === 0): ?>
    <p class="empty">Nessuna song senza file: tutte le righe in DB hanno l&apos;mp3 corrispondente (o l&apos;elenco è vuoto).</p>
<?php else: ?>
    <div class="toolbar">
      <label class="switch" title="Mostra solo song attive (sg_attivo=1)">
        <input type="checkbox" id="filter-active">
        <span class="slider green"></span>
        <span>Solo ATTIVE</span>
      </label>
      <label class="switch" title="Mostra solo song disabilitate (sg_attivo≠1)">
        <input type="checkbox" id="filter-disabled">
        <span class="slider red"></span>
        <span>Solo DISABILITATE</span>
      </label>
      <div class="exports">
        <a class="btn" href="?export=csv"><i class="bi bi-file-earmark-excel"></i> Excel (CSV)</a>
        <a class="btn primary" href="?export=pdf" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
      </div>
    </div>

    <table id="list">
      <thead>
        <tr>
          <th>ID</th>
          <th>File</th>
          <th>Stato</th>
          <th>Diritti</th>
          <th>Format</th>
          <th>Artista — Titolo</th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($missing as $r):
    $sgId = (int) $r['sg_id'];
    $sgFile = htmlspecialchars(trim((string) $r['sg_file']), ENT_QUOTES, 'UTF-8');
    $titolo = htmlspecialchars((string) $r['sg_titolo'], ENT_QUOTES, 'UTF-8');
    $artista = htmlspecialchars((string) $r['sg_artista'], ENT_QUOTES, 'UTF-8');
    $isAttivo = ((string) $r['sg_attivo'] === '1');
    $diritti = htmlspecialchars($r['diritti_label'], ENT_QUOTES, 'UTF-8');
    $formats = htmlspecialchars(isset($r['formats_label']) ? $r['formats_label'] : '—', ENT_QUOTES, 'UTF-8');
    $reason = htmlspecialchars((string) $r['reason'], ENT_QUOTES, 'UTF-8');
    $scheda = htmlspecialchars($adminSongsUrl . '?id=' . $sgId, ENT_QUOTES, 'UTF-8');
?>
        <tr data-attivo="<?php echo $isAttivo ? '1' : '0'; ?>">
          <td>
            <a class="id-link" href="<?php echo $scheda; ?>" target="_blank" rel="noopener"
               title="Apri in admin (devi essere già loggato su admin.yourradio.org)"><?php echo $sgId; ?></a>
          </td>
          <td class="file" title="<?php echo $reason; ?>"><?php echo ($sgFile !== '' && $sgFile !== '0') ? ($sgFile . '.mp3') : '—'; ?></td>
          <td><?php echo $isAttivo ? '<span class="badge on">ATTIVA</span>' : '<span class="badge off">OFF</span>'; ?></td>
          <td><span class="badge dir"><?php echo $diritti; ?></span></td>
          <td class="formats" title="<?php echo $formats; ?>"><?php echo $formats; ?></td>
          <td><?php echo $artista; ?> — <?php echo $titolo; ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    <p class="note">
      I filtri ATTIVE / DISABILITATE sono solo visuali (la scansione è già su tutte le song).<br>
      Il link ID apre <code>admin.yourradio.org/songs.php?id=…</code> in una nuova scheda:
      <strong>devi essere già autenticato</strong> sull&apos;admin, altrimenti vedi la login senza layout completo.
    </p>
<?php endif; ?>
  </div>
</div>
<script>
(function () {
  var list = document.getElementById("list");
  var filterActive = document.getElementById("filter-active");
  var filterDisabled = document.getElementById("filter-disabled");
  var totalVisible = document.getElementById("total-visible");
  if (!list) return;

  function applyFilter(e) {
    if (e && e.target === filterActive && filterActive.checked && filterDisabled) {
      filterDisabled.checked = false;
    }
    if (e && e.target === filterDisabled && filterDisabled.checked && filterActive) {
      filterActive.checked = false;
    }
    var onlyActive = filterActive && filterActive.checked;
    var onlyDisabled = filterDisabled && filterDisabled.checked;
    var rows = list.querySelectorAll("tbody tr");
    var visible = 0;
    for (var i = 0; i < rows.length; i++) {
      var attivo = rows[i].getAttribute("data-attivo");
      var hide = false;
      if (onlyActive) hide = (attivo !== "1");
      if (onlyDisabled) hide = (attivo === "1");
      if (hide) rows[i].classList.add("is-hidden");
      else { rows[i].classList.remove("is-hidden"); visible++; }
    }
    if (totalVisible) totalVisible.textContent = String(visible);
  }

  if (filterActive) filterActive.addEventListener("change", applyFilter);
  if (filterDisabled) filterDisabled.addEventListener("change", applyFilter);
})();
</script>
</body>
</html>
<?php
exit(0);
