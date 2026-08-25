<?php
/**
 * Pagina web per importare canzoni da metadata.json (getmetadata)
 * Ispirato a import_wm_songs/import.php.
 * DB su server esterno yourradio.org: le query (INSERT song + song_format) vengono eseguite via API.
 * La copia dei file (files -> newfiles) avviene solo in locale.
 */

require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/database.php';

// Evita timeout su import con molti track (0 = nessun limite)
set_time_limit(0);
ini_set('max_execution_time', 0);

$pageTitle = "Import Songs by Metadata";

// Format da abbinare a ogni nuova song (tabella song_format)
$newFormat = '81'; //COVER=3. AI POP=81

// Fornitore: se "watermelon" usa sg_filename_wm (senza estensione), altrimenti sg_filename_origin
$fornitore = '';

// Limite track da metadata.json: 0 = tutti i tracks; N = solo i primi N (es. 5 per test)
$limitTracks =1 ;

// Valore per il campo sg_diritti nelle nuove song (0 = Siae, 1 = Creative, 2 = Soundreef, 3 = Watermelon, 4 = YourRadio)
$diritti = 4;

// Percorsi cartelle (stesso stile di import_wm_songs)
$filesDir = __DIR__ . '/files/';
$newfilesDir = __DIR__ . '/newfiles/';
$metadataPath = __DIR__ . '/metadata.json';

// Query che in apertura servono per ottenere ultimo sg_id e ultimo sg_file (solo mostrate, non eseguite)
$queryMaxSgId = "SELECT `sg_id` FROM `songs` ORDER BY `sg_id` DESC LIMIT 1";
$queryMaxSgFile = "SELECT `sg_file` FROM `songs` ORDER BY `sg_file` DESC LIMIT 1";

// Recupero valori massimi via API (solo lettura; non tocchiamo il DB con INSERT/UPDATE)
$apiBaseUrl = "https://yourradio.org/api";
$nextSgId = 1;
$nextSgFile = 1;
$maxSgId = 0;
$maxSgFile = 0;
$apiError = null;

function callApi($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        return array('success' => false, 'error' => $error, 'data' => null);
    }
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array('success' => false, 'error' => 'JSON parse error', 'data' => null);
    }
    $ok = ($httpCode >= 200 && $httpCode < 300) && isset($result['success']) && $result['success'];
    $errMsg = null;
    if (!$ok) {
        if (isset($result['error']['message'])) {
            $errMsg = $result['error']['message'];
        } elseif (isset($result['message'])) {
            $errMsg = $result['message'];
        }
    }
    return array(
        'success' => $ok,
        'data' => isset($result['data']) ? $result['data'] : null,
        'error' => $errMsg
    );
}

$maxIdsResponse = callApi($apiBaseUrl . '/songs/maxids');
if ($maxIdsResponse['success'] && $maxIdsResponse['data']) {
    $maxSgId = isset($maxIdsResponse['data']['max_sg_id']) ? (int)$maxIdsResponse['data']['max_sg_id'] : 0;
    $maxSgFile = isset($maxIdsResponse['data']['max_sg_file']) ? (int)$maxIdsResponse['data']['max_sg_file'] : 0;
    $nextSgId = isset($maxIdsResponse['data']['next_sg_id']) ? (int)$maxIdsResponse['data']['next_sg_id'] : ($maxSgId + 1);
    $nextSgFile = isset($maxIdsResponse['data']['next_sg_file']) ? (int)$maxIdsResponse['data']['next_sg_file'] : ($maxSgFile + 1);
} else {
    $apiError = isset($maxIdsResponse['error']) ? $maxIdsResponse['error'] : 'Errore recupero max ids';
}

// Carica metadata.json e applica eventuale limite track
$planned = array();
$tracks = array();
if (is_file($metadataPath)) {
    $json = file_get_contents($metadataPath);
    $meta = json_decode($json, true);
    if (isset($meta['tracks']) && is_array($meta['tracks'])) {
        $tracks = ($limitTracks > 0) ? array_slice($meta['tracks'], 0, $limitTracks) : $meta['tracks'];
    }
}

$sgIdCounter = $nextSgId;
$sgFileCounter = $nextSgFile;
$logMessages = array();
$hasNewFormat = !($newFormat === null || $newFormat === '');
$isWatermelon = (strtolower(trim((string)$fornitore)) === 'watermelon');

// Crea cartella newfiles se non esiste
if (!is_dir($newfilesDir)) {
    mkdir($newfilesDir, 0755, true);
}

foreach ($tracks as $idx => $track) {
    $filename = isset($track['filename']) ? trim($track['filename']) : '';
    if ($filename === '') {
        $planned[] = array(
            'index' => $idx + 1,
            'status' => 'skip',
            'reason' => 'filename vuoto',
            'filename' => '',
            'query_songs' => null,
            'query_song_format' => null,
            'log' => array()
        );
        continue;
    }
    $sourceFile = $filesDir . $filename;
    if (!is_file($sourceFile)) {
        $planned[] = array(
            'index' => $idx + 1,
            'status' => 'skip',
            'reason' => 'file non presente in /files/',
            'filename' => $filename,
            'query_songs' => null,
            'query_song_format' => null,
            'log' => array()
        );
        continue;
    }
    $titolo = isset($track['titolo']) ? $track['titolo'] : '';
    $autori = isset($track['autori']) ? $track['autori'] : '';
    $anno = isset($track['anno_registrazione']) ? (int)$track['anno_registrazione'] : 0;
    $filesize = isset($track['filesize']) ? (int)$track['filesize'] : 0;
    $sgTitoloEsc = addslashes($titolo);
    $sgArtistaEsc = addslashes($autori);
    $filenameNoExt = pathinfo($filename, PATHINFO_FILENAME);
    $filenameForDb = $isWatermelon ? $filenameNoExt : $filename;
    $filenameField = $isWatermelon ? 'sg_filename_wm' : 'sg_filename_origin';
    $sgFilenameEsc = addslashes($filenameForDb);
    $rowLog = array();

    // 1) Verifica se esiste già una song con lo stesso filename nel campo previsto dal fornitore.
    //    - watermelon: GET /api/songs/byfilename?filename=<nome_senza_estensione>
    //    - default:    GET /api/songs/byfilenameorigin?filename=<nome_originale>
    $byFilenameUrl = $isWatermelon
        ? ($apiBaseUrl . '/songs/byfilename?filename=' . urlencode($filenameForDb))
        : ($apiBaseUrl . '/songs/byfilenameorigin?filename=' . urlencode($filenameForDb));
    $byFilenameResponse = callApi($byFilenameUrl);
    $existingSong = null;
    $existingSgId = null;

    if (!$byFilenameResponse['success']) {
        // Verifica fallita (endpoint assente, 404, 500, rete): NON creare, evita duplicati
        $err = isset($byFilenameResponse['error']) ? $byFilenameResponse['error'] : 'Risposta non valida';
        $msg = "[Riga " . ($idx + 1) . "] Verifica esistenza fallita (" . ($isWatermelon ? 'byfilename' : 'byfilenameorigin') . "): $err. Riga saltata per evitare duplicati.";
        $rowLog[] = $msg;
        error_log("importBymeta: " . $msg);
        $planned[] = array(
            'index' => $idx + 1,
            'status' => 'skip',
            'reason' => 'verifica esistenza API fallita (evita duplicati)',
            'filename' => $filename,
            'log' => $rowLog,
            'query_songs' => null,
            'query_song_format' => null
        );
        continue;
    }

    if (!isset($byFilenameResponse['data']) || !array_key_exists('exists', $byFilenameResponse['data'])) {
        $msg = "[Riga " . ($idx + 1) . "] Risposta API verifica esistenza senza 'data.exists'. Riga saltata per evitare duplicati.";
        $rowLog[] = $msg;
        error_log("importBymeta: " . $msg);
        $planned[] = array(
            'index' => $idx + 1,
            'status' => 'skip',
            'reason' => 'risposta API incompleta (evita duplicati)',
            'filename' => $filename,
            'log' => $rowLog,
            'query_songs' => null,
            'query_song_format' => null
        );
        continue;
    }

    if ($byFilenameResponse['data']['exists']) {
        $existingSong = $byFilenameResponse['data'];
        $existingSgId = (int)$byFilenameResponse['data']['sg_id'];
    }

    if ($existingSong && $existingSgId) {
        // Song già presente: non creare, non copiare; aggiungi solo relazione format se manca
        $msg = "[Riga " . ($idx + 1) . "] " . $filenameField . " già presente: \"$filenameForDb\" (sg_id=$existingSgId). Song non aggiunta.";
        $rowLog[] = $msg;
        error_log("importBymeta: " . $msg);

        if (!$hasNewFormat) {
            $msg2 = "[Riga " . ($idx + 1) . "] newFormat vuoto/null: nessuna relazione format da creare.";
            $rowLog[] = $msg2;
            error_log("importBymeta: " . $msg2);
            $planned[] = array(
                'index' => $idx + 1,
                'status' => 'existing_skip',
                'reason' => 'song già presente (nessun format richiesto)',
                'filename' => $filename,
                'sg_id' => $existingSgId,
                'titolo' => $titolo,
                'autori' => $autori,
                'log' => $rowLog,
                'query_songs' => null,
                'query_song_format' => null
            );
            continue;
        }

        // Verifica se la relazione format esiste già
        $formatCheckResponse = callApi($apiBaseUrl . '/songs/' . $existingSgId . '/format?id_format=' . $newFormat);
        $formatExists = ($formatCheckResponse['success'] && isset($formatCheckResponse['data']['exists']) && $formatCheckResponse['data']['exists']);

        if ($formatExists) {
            $msg2 = "[Riga " . ($idx + 1) . "] Relazione format id_format=$newFormat per sg_id=$existingSgId già presente. Non aggiunta.";
            $rowLog[] = $msg2;
            error_log("importBymeta: " . $msg2);
            $planned[] = array(
                'index' => $idx + 1,
                'status' => 'existing_skip',
                'reason' => 'song e format già presenti',
                'filename' => $filename,
                'sg_id' => $existingSgId,
                'titolo' => $titolo,
                'autori' => $autori,
                'log' => $rowLog,
                'query_songs' => null,
                'query_song_format' => null
            );
        } else {
            // Aggiungi solo la relazione format
            $formatAddResponse = callApi($apiBaseUrl . '/songs/' . $existingSgId . '/format', 'POST', array('id_format' => $newFormat));
            if ($formatAddResponse['success']) {
                $msg2 = "[Riga " . ($idx + 1) . "] Aggiunta relazione format id_format=$newFormat per song esistente sg_id=$existingSgId.";
                $rowLog[] = $msg2;
                error_log("importBymeta: " . $msg2);
                $planned[] = array(
                    'index' => $idx + 1,
                    'status' => 'existing_format_added',
                    'filename' => $filename,
                    'sg_id' => $existingSgId,
                    'titolo' => $titolo,
                    'autori' => $autori,
                    'log' => $rowLog,
                    'query_songs' => null,
                    'query_song_format' => "INSERT INTO `song_format` (`id_song`, `id_format`) VALUES ($existingSgId, $newFormat);"
                );
            } else {
                $err = isset($formatAddResponse['error']) ? $formatAddResponse['error'] : 'Errore API';
                $rowLog[] = "[Riga " . ($idx + 1) . "] Errore aggiunta format: " . $err;
                $planned[] = array(
                    'index' => $idx + 1,
                    'status' => 'db_error',
                    'filename' => $filename,
                    'song_insert_error' => $err,
                    'sg_id' => $existingSgId,
                    'log' => $rowLog,
                    'query_songs' => null,
                    'query_song_format' => null
                );
            }
        }
        continue;
    }

    // Song non esiste: copia file, crea song e (opzionalmente) relazione format
    $sgId = $sgIdCounter;
    $sgFile = $sgFileCounter;
    $destFileName = $sgFile . '.mp3';
    $destFile = $newfilesDir . $destFileName;
    $copyOk = @copy($sourceFile, $destFile);

    // se è un inserimento di YourRadio (diritti=4), impostiamo sg_autori a "Dennis Lirani", altrimenti lasciamo vuoto
    if ($diritti==4){
        $sgAutoriEsc="Dennis Lirani";
    }else{
        $sgAutoriEsc="";
    }

    $querySongs = "INSERT INTO `songs` (`sg_id`, `sg_file`, `sg_filesize`, `sg_titolo`, `sg_artista`, `sg_autori`, `sg_anno`, `$filenameField`, `sg_diritti`) " .
        "VALUES ($sgId, $sgFile, $filesize, '$sgTitoloEsc', '$sgArtistaEsc', '$sgAutoriEsc', $anno, '$sgFilenameEsc', $diritti);";
    $querySongFormat = $hasNewFormat ? "INSERT INTO `song_format` (`id_song`, `id_format`) VALUES ($sgId, $newFormat);" : null;

    $songInsertOk = false;
    $songInsertError = null;
    if ($copyOk) {
        $songData = array(
            'sg_id' => $sgId,
            'sg_file' => $sgFile,
            'sg_filesize' => $filesize,
            'sg_titolo' => $titolo,
            'sg_artista' => $autori,
            'sg_autori' => $sgAutoriEsc,
            'sg_anno' => $anno,
            $filenameField => (string) $filenameForDb,
            'sg_diritti' => (int) $diritti
        );
        if ($hasNewFormat) {
            $songData['formats'] = array($newFormat);
        }
        $createResponse = callApi($apiBaseUrl . '/songs', 'POST', $songData);
        $songInsertOk = $createResponse['success'];
        if (!$songInsertOk) {
            $songInsertError = isset($createResponse['error']) ? $createResponse['error'] : 'Errore API';
        }
    }

    $planned[] = array(
        'index' => $idx + 1,
        'status' => !$copyOk ? 'copy_failed' : ($songInsertOk ? 'ready' : 'db_error'),
        'filename' => $filename,
        'dest_filename' => $destFileName,
        'copy_ok' => $copyOk,
        'song_insert_ok' => $songInsertOk,
        'song_insert_error' => $songInsertError,
        'titolo' => $titolo,
        'autori' => $autori,
        'anno_registrazione' => $anno,
        'sg_id' => $sgId,
        'sg_file' => $sgFile,
        'query_songs' => $querySongs,
        'query_song_format' => $querySongFormat,
        'log' => $rowLog
    );

    $sgIdCounter++;
    $sgFileCounter++;
}

$pageReq = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '/') + 1);
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="../../assets/images/favicon.ico">
    <title><?= isset($pageTitle) && $pageTitle != '' ? htmlspecialchars($pageTitle) . ' - ' . SITE_TITLE : SITE_TITLE ?></title>
    <link rel="stylesheet" href="../../css/simplebar.css">
    <link href="https://fonts.googleapis.com/css2?family=Overpass:ital,wght@0,100;0,200;0,300;0,400;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/feather.css">
    <link rel="stylesheet" href="../../css/app-light.css" id="lightTheme" disabled>
    <link rel="stylesheet" href="../../css/app-dark.css" id="darkTheme">
</head>
<body class="horizontal dark">
<div class="wrapper">
    <header class="bg-dark text-center py-4 mb-4" style="background-color: #000 !important;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12">
                    <a href="../../index.php" class="d-inline-block mb-3">
                        <img src="../../assets/images/logo-yourradio-maxi.png" alt="YourRadio" height="60">
                    </a>
                    <a href="../../index.php" class="btn btn-outline-light"><span class="fe fe-log-out"></span> ESCI</a>
                </div>
            </div>
        </div>
    </header>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12">
                    <h2 class="h2 text-white text-center page-title">TOOLS</h2>
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h4 class="card-title">Import Songs by Metadata</h4>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Fornitore: <strong><?= htmlspecialchars($fornitore !== '' ? $fornitore : 'default') ?></strong> (campo filename DB: <strong><?= $isWatermelon ? 'sg_filename_wm' : 'sg_filename_origin' ?></strong>). Format ID abbinamento: <strong><?= $hasNewFormat ? htmlspecialchars((string)$newFormat) : 'nessuno' ?></strong>. Diritti (sg_diritti): <strong><?= (int)$diritti ?></strong>. Le INSERT su <strong>songs</strong> e <strong>song_format</strong> vengono eseguite sul DB del server esterno (yourradio.org) via API. La copia file resta in locale (files → newfiles).</p>

                            <div class="alert alert-info mb-4">
                                <h5 class="text-dark">Query usate in apertura (solo lettura, non eseguite da questo script)</h5>
                                <p class="mb-1"><strong>Ultimo sg_id:</strong></p>
                                <pre class="bg-dark text-light p-2 rounded mb-3"><?= htmlspecialchars($queryMaxSgId) ?></pre>
                                <p class="mb-1"><strong>Ultimo sg_file:</strong></p>
                                <pre class="bg-dark text-light p-2 rounded"><?= htmlspecialchars($queryMaxSgFile) ?></pre>
                                <?php if ($apiError): ?>
                                    <p class="text-warning mb-0">API maxids: <?= htmlspecialchars($apiError) ?> — usati valori di default (next_sg_id=1, next_sg_file=1).</p>
                                <?php else: ?>
                                    <p class="text-dark mb-0">Valori da API: max_sg_id=<?= (int)$maxSgId ?>, max_sg_file=<?= (int)$maxSgFile ?> → prossimi: sg_id=<?= (int)$nextSgId ?>, sg_file=<?= (int)$nextSgFile ?>.</p>
                                <?php endif; ?>
                            </div>

                            <h5 class="text-dark"><?= $limitTracks > 0 ? 'Prime ' . (int)$limitTracks . ' track' : 'Tutti i track' ?> da metadata.json (<?= count($tracks) ?> elaborati)</h5>
                            <div id="resultsContent" class="mb-4">
                                <?php foreach ($planned as $item): ?>
                                    <?php
                                    $borderClass = 'border-success';
                                    if ($item['status'] === 'skip' || $item['status'] === 'existing_skip') $borderClass = 'border-warning';
                                    elseif ($item['status'] === 'existing_format_added') $borderClass = 'border-info';
                                    elseif ($item['status'] === 'copy_failed' || $item['status'] === 'db_error') $borderClass = 'border-danger';
                                    ?>
                                    <div class="mb-4 p-3 border rounded <?= $borderClass ?>">
                                        <h6>Track #<?= (int)$item['index'] ?></h6>
                                        <?php if (!empty($item['log'])): ?>
                                            <div class="mb-2 p-2 bg-light rounded small">
                                                <strong>Log:</strong>
                                                <?php foreach ($item['log'] as $logLine): ?>
                                                    <div><?= htmlspecialchars($logLine) ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($item['status'] === 'skip'): ?>
                                            <p class="text-warning mb-1">Saltata: <?= htmlspecialchars($item['reason']) ?></p>
                                            <?php if (!empty($item['filename'])): ?>
                                                <p class="mb-0 small">filename: <?= htmlspecialchars($item['filename']) ?></p>
                                            <?php endif; ?>
                                        <?php elseif (isset($item['status']) && $item['status'] === 'existing_skip'): ?>
                                            <p class="text-warning mb-1">Song già presente (sg_filename_origin). Relazione format già presente. Nessuna modifica.</p>
                                            <p class="mb-1"><strong>File:</strong> <?= htmlspecialchars($item['filename']) ?> | <strong>sg_id:</strong> <?= (int)$item['sg_id'] ?></p>
                                            <p class="mb-0"><strong>Titolo:</strong> <?= htmlspecialchars($item['titolo']) ?> | <strong>Autori:</strong> <?= htmlspecialchars($item['autori']) ?></p>
                                        <?php elseif (isset($item['status']) && $item['status'] === 'existing_format_added'): ?>
                                            <p class="text-info mb-1">Song già presente. Aggiunta solo relazione format (id_format=<?= htmlspecialchars((string)$newFormat) ?>).</p>
                                            <p class="mb-1"><strong>File:</strong> <?= htmlspecialchars($item['filename']) ?> | <strong>sg_id:</strong> <?= (int)$item['sg_id'] ?></p>
                                            <p class="mb-1"><strong>INSERT song_format (eseguito):</strong></p>
                                            <pre class="bg-dark text-light p-2 rounded small"><?= htmlspecialchars($item['query_song_format']) ?></pre>
                                        <?php elseif (isset($item['status']) && $item['status'] === 'copy_failed'): ?>
                                            <p class="mb-1"><strong>File:</strong> <?= htmlspecialchars($item['filename']) ?></p>
                                            <p class="text-danger mb-1">Copia file fallita: files/<?= htmlspecialchars($item['filename']) ?> → newfiles/<?= htmlspecialchars($item['dest_filename']) ?></p>
                                            <p class="mb-1"><strong>sg_id:</strong> <?= (int)$item['sg_id'] ?> | <strong>sg_file:</strong> <?= (int)$item['sg_file'] ?></p>
                                            <p class="mb-1"><strong>INSERT songs (non eseguito):</strong></p>
                                            <pre class="bg-dark text-light p-2 rounded small"><?= htmlspecialchars($item['query_songs']) ?></pre>
                                            <p class="mb-1"><strong>INSERT song_format (non eseguito):</strong></p>
                                            <pre class="bg-dark text-light p-2 rounded small"><?= htmlspecialchars($item['query_song_format']) ?></pre>
                                        <?php elseif (isset($item['status']) && $item['status'] === 'db_error'): ?>
                                            <p class="mb-1"><strong>File:</strong> <?= htmlspecialchars($item['filename']) ?></p>
                                            <?php if (!empty($item['dest_filename'])): ?>
                                                <p class="text-success mb-1">Copia file: files/<?= htmlspecialchars($item['filename']) ?> → newfiles/<?= htmlspecialchars($item['dest_filename']) ?></p>
                                            <?php endif; ?>
                                            <p class="text-danger mb-1">Errore DB (API): <?= htmlspecialchars(isset($item['song_insert_error']) ? $item['song_insert_error'] : '') ?></p>
                                            <?php if (isset($item['sg_id'])): ?>
                                                <p class="mb-1"><strong>sg_id:</strong> <?= (int)$item['sg_id'] ?><?= isset($item['sg_file']) ? ' | <strong>sg_file:</strong> ' . (int)$item['sg_file'] : '' ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($item['query_songs'])): ?>
                                                <p class="mb-1"><strong>INSERT songs (tentato):</strong></p>
                                                <pre class="bg-dark text-light p-2 rounded small"><?= htmlspecialchars($item['query_songs']) ?></pre>
                                            <?php endif; ?>
                                            <?php if (!empty($item['query_song_format'])): ?>
                                                <p class="mb-1"><strong>INSERT song_format (tentato):</strong></p>
                                                <pre class="bg-dark text-light p-2 rounded small"><?= htmlspecialchars($item['query_song_format']) ?></pre>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p class="mb-1"><strong>File:</strong> <?= htmlspecialchars($item['filename']) ?></p>
                                            <p class="mb-1 text-success"><strong>Copia file:</strong> files/<?= htmlspecialchars($item['filename']) ?> → newfiles/<?= htmlspecialchars($item['dest_filename']) ?></p>
                                            <p class="mb-1 text-success"><strong>DB:</strong> song e format inseriti via API (server esterno)</p>
                                            <p class="mb-1"><strong>Titolo:</strong> <?= htmlspecialchars($item['titolo']) ?> | <strong>Autori:</strong> <?= htmlspecialchars($item['autori']) ?> | <strong>Anno:</strong> <?= (int)$item['anno_registrazione'] ?></p>
                                            <p class="mb-1"><strong>sg_id:</strong> <?= (int)$item['sg_id'] ?> | <strong>sg_file:</strong> <?= (int)$item['sg_file'] ?></p>
                                            <p class="mb-1"><strong>INSERT songs (eseguito):</strong></p>
                                            <pre class="bg-dark text-light p-2 rounded small"><?= htmlspecialchars($item['query_songs']) ?></pre>
                                            <p class="mb-1"><strong>INSERT song_format (eseguito):</strong></p>
                                            <pre class="bg-dark text-light p-2 rounded small"><?= htmlspecialchars($item['query_song_format']) ?></pre>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../../js/jquery.min.js"></script>
<script src="../../js/bootstrap.min.js"></script>
</body>
</html>
