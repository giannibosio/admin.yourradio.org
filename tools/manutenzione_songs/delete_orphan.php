<?php
/**
 * Cancella UN solo file orfano da player/song/.
 *
 * POST JSON:
 *   { "confirm_url": "https://yourradio.org/player/song/28824.mp3" }
 *
 * Sicurezza:
 * - solo POST
 * - URL deve essere esattamente https://yourradio.org/player/song/[ID].mp3
 * - ID solo numerico
 * - file deve stare dentro la cartella song (no path traversal)
 * - sg_file NON deve esistere in DB (deve essere ancora orfano)
 * - cancella solo quel file
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'error' => 'Solo POST'));
    exit;
}

require_once __DIR__ . '/_bootstrap.php';
manutenzione_load_config();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$confirmUrl = isset($data['confirm_url']) ? trim($data['confirm_url']) : '';
if ($confirmUrl === '') {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => 'confirm_url mancante'));
    exit;
}

// Accetta solo link canonico
if (!preg_match('#^https://yourradio\.org/player/song/([0-9]+)\.mp3$#', $confirmUrl, $m)) {
    http_response_code(400);
    echo json_encode(array(
        'ok' => false,
        'error' => 'URL non valido. Atteso: https://yourradio.org/player/song/[ID].mp3',
    ));
    exit;
}

$id = $m[1];
$filename = $id . '.mp3';
$songDir = manutenzione_song_path();
$full = $songDir . '/' . $filename;

// Path traversal / fuori cartella
$realDir = realpath($songDir);
$realFile = realpath($full);
if ($realDir === false || !is_dir($realDir)) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'Cartella song non trovata'));
    exit;
}
if ($realFile === false || strpos($realFile, $realDir . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => 'File non trovato o path non consentito'));
    exit;
}
if (!is_file($realFile)) {
    http_response_code(404);
    echo json_encode(array('ok' => false, 'error' => 'File già assente'));
    exit;
}

// Deve essere ancora orfano: se è in DB, NON cancellare
if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'Config DB mancante'));
    exit;
}

$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'Connessione DB fallita'));
    exit;
}
$mysqli->set_charset('utf8');

$stmt = $mysqli->prepare('SELECT sg_id FROM songs WHERE sg_file = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'Prepare fallita'));
    $mysqli->close();
    exit;
}
$stmt->bind_param('s', $id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    $mysqli->close();
    http_response_code(409);
    echo json_encode(array(
        'ok' => false,
        'error' => 'Il file corrisponde a una song in DB (sg_file=' . $id . '). Cancellazione rifiutata.',
    ));
    exit;
}
$stmt->close();
$mysqli->close();

if (!@unlink($realFile)) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'unlink fallito'));
    exit;
}

$logLine = date('Y-m-d H:i:s') . "\tDELETED\t" . $confirmUrl . "\t" . $realFile . "\n";
@file_put_contents(__DIR__ . '/delete_log.txt', $logLine, FILE_APPEND);

echo json_encode(array(
    'ok' => true,
    'deleted' => $filename,
    'url' => $confirmUrl,
));
