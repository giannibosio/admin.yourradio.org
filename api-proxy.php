<?php
/**
 * Proxy per chiamate API da localhost
 * Gestisce CORS e reindirizza le richieste a https://yourradio.org/api
 * SEMPRE punta a https://yourradio.org - MAI localhost
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$url = isset($_GET['url']) ? $_GET['url'] : '';

// Log della richiesta proxy
error_log("PROXY REQUEST: " . date('Y-m-d H:i:s') . " | URL: " . $url . " | Method: " . $_SERVER['REQUEST_METHOD']);

if (empty($url)) {
    error_log("PROXY ERROR: URL mancante");
    http_response_code(400);
    echo json_encode(['error' => 'URL mancante']);
    exit;
}

// Verifica che l'URL sia per yourradio.org - MAI localhost
if (strpos($url, 'yourradio.org') === false) {
    error_log("PROXY ERROR: URL non consentito (deve essere yourradio.org): " . $url);
    http_response_code(403);
    echo json_encode(['error' => 'URL non consentito - deve essere yourradio.org']);
    exit;
}

// Verifica che NON sia localhost
if (strpos($url, 'localhost') !== false || strpos($url, '127.0.0.1') !== false) {
    error_log("PROXY ERROR: URL contiene localhost (NON PERMESSO): " . $url);
    http_response_code(403);
    echo json_encode(['error' => 'URL non consentito - localhost non permesso']);
    exit;
}

// Prepara la richiesta
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// Passa il metodo HTTP
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
}

// Passa i dati se presenti
if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
    $data = file_get_contents('php://input');
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    }
}

// Passa gli header
$headers = [];
foreach (getallheaders() as $name => $value) {
    if (strtolower($name) !== 'host' && strtolower($name) !== 'connection') {
        $headers[] = "$name: $value";
    }
}
if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

$startTime = microtime(true);
$response = curl_exec($ch);
$endTime = microtime(true);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);
$curlInfo = curl_getinfo($ch);
curl_close($ch);

$responseTime = round(($endTime - $startTime) * 1000, 2);

// Log della risposta
error_log("PROXY RESPONSE: " . date('Y-m-d H:i:s') . " | URL: " . $url . " | HTTP Code: " . $httpCode . " | Response Time: " . $responseTime . "ms | Error: " . ($error ?: 'none'));

if ($error) {
    error_log("PROXY ERROR: " . $error . " | URL: " . $url);
    http_response_code(500);
    echo json_encode(['error' => 'Errore proxy: ' . $error, 'url' => $url]);
    exit;
}

error_log("PROXY SUCCESS: " . $url . " | HTTP " . $httpCode . " | Size: " . strlen($response) . " bytes");

http_response_code($httpCode);
if ($contentType) {
    header('Content-Type: ' . $contentType);
}
echo $response;

