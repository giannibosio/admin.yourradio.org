<?php
/**
 * Scarica un mp3 da player/song/ forzando il nome "artista - titolo.mp3".
 *
 * Uso:
 *   download_as.php?id=25039
 *   download_as.php?file=28824
 */

require_once __DIR__ . '/_bootstrap.php';
manutenzione_load_config();

$sgId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$sgFile = isset($_GET['file']) ? trim((string) $_GET['file']) : '';

if ($sgId <= 0 && ($sgFile === '' || !preg_match('/^[0-9]+$/', $sgFile))) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Parametro id o file mancante/non valido.";
    exit;
}

if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Config DB mancante.";
    exit;
}

$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Connessione DB fallita.";
    exit;
}
$mysqli->set_charset('utf8');

if ($sgId > 0) {
    $stmt = $mysqli->prepare('SELECT sg_id, sg_file, sg_titolo, sg_artista FROM songs WHERE sg_id = ? LIMIT 1');
    $stmt->bind_param('i', $sgId);
} else {
    $stmt = $mysqli->prepare('SELECT sg_id, sg_file, sg_titolo, sg_artista FROM songs WHERE sg_file = ? LIMIT 1');
    $stmt->bind_param('s', $sgFile);
}
$stmt->execute();
$stmt->bind_result($outId, $outFile, $outTitolo, $outArtista);
$row = null;
if ($stmt->fetch()) {
    $row = array(
        'sg_id' => $outId,
        'sg_file' => $outFile,
        'sg_titolo' => $outTitolo,
        'sg_artista' => $outArtista,
    );
}
$stmt->close();
$mysqli->close();

if (!$row) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Song non trovata.";
    exit;
}

$fileId = trim((string) $row['sg_file']);
if ($fileId === '' || $fileId === '0' || !preg_match('/^[0-9]+$/', $fileId)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "sg_file assente.";
    exit;
}

$songDir = manutenzione_song_path();
$full = $songDir . '/' . $fileId . '.mp3';
$realDir = realpath($songDir);
$realFile = realpath($full);

if ($realDir === false || $realFile === false || strpos($realFile, $realDir . DIRECTORY_SEPARATOR) !== 0 || !is_file($realFile)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "File non trovato sul disco.";
    exit;
}

$dlName = manutenzione_download_filename($row['sg_artista'], $row['sg_titolo'], $fileId);
// Content-Disposition filename (ASCII fallback) + filename* UTF-8
$ascii = preg_replace('/[^\x20-\x7E]/', '_', $dlName);
if ($ascii === '' || $ascii === '.mp3') {
    $ascii = $fileId . '.mp3';
}
$utf8star = "UTF-8''" . rawurlencode($dlName);

header('Content-Type: audio/mpeg');
header('Content-Length: ' . filesize($realFile));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $ascii) . '"; filename*=' . $utf8star);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0');

readfile($realFile);
exit;
