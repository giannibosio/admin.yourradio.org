<?php
/**
 * Endpoint per generare i metadati delle song dai file audio locali.
 *
 * GET /api/metadata
 *    - Legge tutti i file MP3 in tools/getmetadata/files
 *    - Usa getID3 per estrarre titolo, durata, autore, anno, ecc.
 *    - Scrive tools/getmetadata/metadata.json
 *    - Restituisce lo stesso JSON in risposta
 */

function handleMetadataRequest($method, $action, $id, $data) {
    if ($method !== 'GET') {
        sendErrorResponse("Method not allowed", 405);
    }

    $baseDir   = realpath(__DIR__ . '/../../tools/getmetadata'); // tools/getmetadata
    $musicDir  = $baseDir ? $baseDir . '/files' : null;
    $outputFile = $baseDir ? $baseDir . '/metadata.json' : null;

    if (!$baseDir || !$musicDir || !$outputFile) {
        sendErrorResponse("Percorsi tools/getmetadata non disponibili", 500);
    }

    // Carica getID3: prima Composer, poi cartella locale getid3
    $getID3 = null;
    $projectRoot = dirname(__DIR__, 1); // api/..
    if (file_exists($projectRoot . '/vendor/autoload.php')) {
        require_once $projectRoot . '/vendor/autoload.php';
        $getID3 = new getID3();
    } elseif (file_exists($baseDir . '/getid3/getid3.php')) {
        require_once $baseDir . '/getid3/getid3.php';
        $getID3 = new getID3();
    }

    if (!$getID3) {
        sendErrorResponse("Libreria getID3 non trovata. Installa con Composer o copia getid3 in tools/getmetadata/.", 500);
    }

    // Helper locali (nomi unici per evitare collisioni)
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
        sendErrorResponse("La cartella file audio non esiste: " . $musicDir, 500);
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
            'filename'          => $filename,
            'filesize'          => filesize($path),
            'titolo'            => '',
            'durata'            => null,
            'durata_secondi'    => null,
            'autori'            => '',
            'anno_registrazione'=> null,
            //'album'             => '',
            //'commento'          => '',
            //'track'             => null,
            //'genre'             => '',
            //'error'             => null
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
            $entry['album']     = $getTagValue($info, 'album');
            $entry['commento']  = $getTagValue($info, 'comment');
            $entry['genre']     = $getTagValue($info, 'genre');

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

    // Salva anche su file per riuso da strumenti interni
    @file_put_contents($outputFile, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    sendSuccessResponse($output);
}

