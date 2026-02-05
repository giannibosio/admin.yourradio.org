<?php
/**
 * API locale per estrarre i metadati dai file audio in tools/getmetadata/files.
 *
 * NOTE IMPORTANTI:
 * - NON usa il database.
 * - Pensata SOLO per uso in locale (http://localhost:8000/admin/api/local-metadata.php).
 * - Legge i file MP3 in tools/getmetadata/files
 * - Usa la libreria getID3 in tools/getmetadata/getid3 oppure vendor/autoload.php
 * - Restituisce JSON direttamente (Content-Type: application/json).
 */

// CORS molto permissivo solo per sviluppo locale
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

date_default_timezone_set('Europe/Rome');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Percorsi base
$baseDir   = realpath(__DIR__ . '/../tools/getmetadata'); // tools/getmetadata
$musicDir  = $baseDir ? $baseDir . '/files' : null;
$outputFile = $baseDir ? $baseDir . '/metadata.json' : null;

if (!$baseDir || !$musicDir || !$outputFile) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'Percorsi tools/getmetadata non disponibili'
    ]);
    exit;
}

// Carica getID3: prima Composer (se presente), poi cartella locale getid3
$getID3 = null;
$projectRoot = realpath(__DIR__ . '/..'); // cartella admin.yourradio.org
if ($projectRoot && file_exists($projectRoot . '/vendor/autoload.php')) {
    require_once $projectRoot . '/vendor/autoload.php';
    $getID3 = new getID3();
} elseif (file_exists($baseDir . '/getid3/getid3.php')) {
    require_once $baseDir . '/getid3/getid3.php';
    $getID3 = new getID3();
}

if (!$getID3) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'Libreria getID3 non trovata. Copia la cartella getid3 in tools/getmetadata/ o usa Composer.'
    ]);
    exit;
}

// Helper per leggere i tag (id3v2 poi id3v1)
$getTagValue = function($info, $key) {
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
};

// Helper per formattare la durata
$formatDuration = function($seconds) {
    if ($seconds === null || $seconds === '' || !is_numeric($seconds)) {
        return null;
    }
    $s = (int) round((float) $seconds);
    $m = (int) floor($s / 60);
    $s = $s % 60;
    return sprintf('%d:%02d', $m, $s);
};

$items  = array();
$errors = array();

if (!is_dir($musicDir)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'La cartella file audio non esiste: ' . $musicDir
    ]);
    exit;
}

$extensions = array('mp3');
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
    $files[] = $file->getPathname();
}
sort($files);

foreach ($files as $path) {
    $filename = basename($path);
    $entry = array(
        'filename'           => $filename,
        'filesize'           => filesize($path),
        'titolo'             => '',
        'durata'             => null,
        'durata_secondi'     => null,
        'autori'             => '',
        'anno_registrazione' => null,
        'album'              => '',
        'commento'           => '',
        'track'              => null,
        'genre'              => '',
        'error'              => null
    );

    try {
        $info = $getID3->analyze($path);

        if (isset($info['error']) && !empty($info['error'])) {
            $entry['error'] = is_array($info['error']) ? implode('; ', $info['error']) : $info['error'];
            $errors[] = $filename . ': ' . $entry['error'];
            $items[] = $entry;
            continue;
        }

        $entry['titolo'] = $getTagValue($info, 'title');
        $entry['autori'] = $getTagValue($info, 'artist');
        if ($entry['autori'] === '') {
            $entry['autori'] = $getTagValue($info, 'band');
        }
        $entry['album']    = $getTagValue($info, 'album');
        $entry['commento'] = $getTagValue($info, 'comment');
        $entry['genre']    = $getTagValue($info, 'genre');

        $year = $getTagValue($info, 'year');
        if ($year !== '') {
            $entry['anno_registrazione'] = (int) $year;
        }

        $track = $getTagValue($info, 'track_number');
        if ($track === '') {
            $track = $getTagValue($info, 'track');
        }
        if ($track !== '') {
            $entry['track'] = (int) $track;
        }

        if (isset($info['playtime_seconds'])) {
            $entry['durata_secondi'] = round((float) $info['playtime_seconds'], 1);
            $entry['durata'] = $formatDuration($entry['durata_secondi']);
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

$output = array(
    'generated' => date('Y-m-d H:i:s'),
    'folder'    => $musicDir,
    'count'     => count($items),
    'errors'    => $errors,
    'tracks'    => $items
);

// Salva anche su file locale (riutilizzabile da script/tools)
@file_put_contents($outputFile, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data'    => $output
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

