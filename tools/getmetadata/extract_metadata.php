<?php
/**
 * Estrae i metadati (ID3v1 + ID3v2) dai file audio nella folder tools/getmetadata/files
 * e genera un file JSON (metadata.json) con titolo, durata, autore, anno, ecc.
 *
 * Richiede la libreria getID3 (legge ID3v2 e calcola la durata):
 *   - Composer: dalla root del progetto esegui "composer install"
 *   - Oppure: scarica getID3 da https://github.com/JamesHeinrich/getID3/archive/refs/heads/master.zip,
 *     estrai e copia la cartella \"getid3\" (quella interna) in tools/getmetadata/
 *     così che esista tools/getmetadata/getid3/getid3.php
 *
 * Esecuzione:
 *   php extract_metadata.php
 *   oppure da browser: .../tools/getmetadata/extract_metadata.php
 */

// In ambiente web attivo un buffer di output, così posso pulire eventuali warning
// o output indesiderato prima di inviare il JSON.
if (php_sapi_name() !== 'cli') {
    ob_start();
}

date_default_timezone_set('Europe/Rome');

$baseDir  = __DIR__;                    // tools/getmetadata
$musicDir = $baseDir . '/files';        // cartella che contiene i file musicali
$outputFile = $baseDir . DIRECTORY_SEPARATOR . 'metadata.json';

$extensions = array('mp3');

// Carica getID3: prima Composer, poi cartella locale getid3
$getID3 = null;
$projectRoot = dirname(__DIR__, 2);
if (file_exists($projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
    require_once $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    $getID3 = new getID3();
} elseif (file_exists($baseDir . DIRECTORY_SEPARATOR . 'getid3' . DIRECTORY_SEPARATOR . 'getid3.php')) {
    require_once $baseDir . DIRECTORY_SEPARATOR . 'getid3' . DIRECTORY_SEPARATOR . 'getid3.php';
    $getID3 = new getID3();
}

if (!$getID3) {
    $msg = 'Libreria getID3 non trovata. Installa con Composer (composer install dalla root) '
         . 'oppure copia la cartella getid3 in tools/getmetadata/. Vedi intestazione di questo file.';
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, $msg . "\n");
        exit(1);
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit(1);
}

/**
 * Estrae il primo valore di un tag da $info['tags'], provando id3v2 poi id3v1.
 * I tag getID3 sono spesso array, es. ['title'] => ['I\'m Old Fashioned'].
 */
function getTagValue($info, $key) {
    $tags = isset($info['tags']) ? $info['tags'] : array();
    foreach (array('id3v2', 'id3v1') as $format) {
        if (!isset($tags[$format][$key]) || !is_array($tags[$format][$key])) {
            continue;
        }
        $val = $tags[$format][$key][0];
        if ($val !== null && $val !== '') {
            return is_string($val) ? trim($val) : $val;
        }
    }
    return '';
}

/**
 * Formatta secondi in MM:SS (es. 272 -> "4:32").
 */
function formatDuration($seconds) {
    if ($seconds === null || $seconds === '' || !is_numeric($seconds)) {
        return null;
    }
    $s = (int) round((float) $seconds);
    $m = (int) floor($s / 60);
    $s = $s % 60;
    return sprintf('%d:%02d', $m, $s);
}

$items = array();
$errors = array();

if (!is_dir($musicDir)) {
    $errors[] = 'La cartella ' . $musicDir . ' non esiste (attesa tools/getmetadata/files).';
} else {
    $files = array();
    $di = new DirectoryIterator($musicDir);
    foreach ($di as $file) {
        if ($file->isDot() || $file->isDir()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $extensions, true)) {
            continue;
        }
        $base = $file->getFilename();
        if ($base === 'extract_metadata.php' || $base === 'metadata.json') {
            continue;
        }
        $files[] = $file->getPathname();
    }
    sort($files);

    foreach ($files as $path) {
        $filename = basename($path);
        $entry = array(
            'filename' => $filename,
            'filesize' => filesize($path),
            'titolo' => '',
            'durata' => null,
            'durata_secondi' => null,
            'autori' => '',
            'anno_registrazione' => null,
            'album' => '',
            'commento' => '',
            'track' => null,
            'genre' => '',
            'error' => null
        );

        try {
            $info = $getID3->analyze($path);

            if (isset($info['error']) && !empty($info['error'])) {
                $entry['error'] = is_array($info['error']) ? implode('; ', $info['error']) : $info['error'];
                $errors[] = $filename . ': ' . $entry['error'];
                $items[] = $entry;
                continue;
            }

            $entry['titolo']       = getTagValue($info, 'title');
            $entry['autori']       = getTagValue($info, 'artist');
            if ($entry['autori'] === '') {
                $entry['autori'] = getTagValue($info, 'band');
            }
            $entry['album']        = getTagValue($info, 'album');
            $entry['commento']    = getTagValue($info, 'comment');
            $entry['genre']       = getTagValue($info, 'genre');

            $year = getTagValue($info, 'year');
            if ($year !== '') {
                $entry['anno_registrazione'] = (int) $year;
            }
            $track = getTagValue($info, 'track_number');
            if ($track === '') {
                $track = getTagValue($info, 'track');
            }
            if ($track !== '') {
                $entry['track'] = (int) $track;
            }

            if (isset($info['playtime_seconds'])) {
                $entry['durata_secondi'] = round((float) $info['playtime_seconds'], 1);
                $entry['durata'] = formatDuration($entry['durata_secondi']);
            }
            if ($entry['durata'] === null && isset($info['playtime_string']) && $info['playtime_string'] !== '') {
                $entry['durata'] = trim($info['playtime_string']);
            }
        } catch (Exception $e) {
            $entry['error'] = $e->getMessage();
            $errors[] = $filename . ': ' . $e->getMessage();
        }

        $items[] = $entry;
    }
}

$output = array(
    'generated' => date('Y-m-d H:i:s'),
    'folder' => $musicDir,
    'count' => count($items),
    'errors' => $errors,
    'tracks' => $items
);

$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if ($json === false) {
    $errorMsg = 'Errore generazione JSON: ' . json_last_error_msg();
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, $errorMsg . "\n");
        exit(1);
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo $errorMsg;
    exit(1);
}

$written = @file_put_contents($outputFile, $json);

if ($written === false) {
    $errorMsg = 'Impossibile scrivere il file ' . $outputFile;
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, $errorMsg . "\n");
        exit(1);
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo $errorMsg;
    exit(1);
}

if (php_sapi_name() === 'cli') {
    echo "OK: " . count($items) . " file elaborati, output in " . $outputFile . "\n";
    if (!empty($errors)) {
        foreach ($errors as $err) {
            fwrite(STDERR, "  - " . $err . "\n");
        }
    }
    exit(0);
}

// In ambiente web, pulisco qualsiasi output generato (warning, notice, ecc.)
// e restituisco solo JSON valido.
if (php_sapi_name() !== 'cli') {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo $json;
}
