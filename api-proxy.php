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
if (isset($_FILES) && !empty($_FILES)) {
    error_log("PROXY: File rilevati in richiesta: " . print_r(array_keys($_FILES), true));
}

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
// Disabilita la compressione automatica - vogliamo ricevere dati non compressi
curl_setopt($ch, CURLOPT_ENCODING, '');

// Passa il metodo HTTP
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
}

// Passa i dati se presenti
if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
    // Verifica se è un upload di file (multipart/form-data)
    $contentType = '';
    $allHeaders = getallheaders();
    if ($allHeaders) {
        foreach ($allHeaders as $name => $value) {
            if (strtolower($name) === 'content-type') {
                $contentType = $value;
                break;
            }
        }
    }
    
    // Se è multipart/form-data, inoltriamo direttamente php://input senza processarlo
    // Questo è necessario perché quando la richiesta arriva via AJAX, i file
    // potrebbero non essere disponibili in $_FILES
    if (strpos($contentType, 'multipart/form-data') !== false) {
        error_log("PROXY: Rilevato multipart/form-data. Content-Type: " . $contentType);
        error_log("PROXY: $_FILES presente: " . (isset($_FILES) && !empty($_FILES) ? 'yes' : 'no'));
        
        // Leggi direttamente da php://input e inoltra così com'è
        $data = file_get_contents('php://input');
        if ($data) {
            error_log("PROXY: Inoltro multipart direttamente da php://input, size: " . strlen($data));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            // Il Content-Type verrà mantenuto dagli header originali
        } else {
            error_log("PROXY WARNING: Multipart rilevato ma nessun dato disponibile in php://input");
        }
    } else if (isset($_FILES) && !empty($_FILES)) {
        // Se abbiamo file in $_FILES ma non è multipart (caso raro), usa CURLFile
        error_log("PROXY: File trovati in $_FILES ma non multipart");
        $postData = [];
        
        foreach ($_FILES as $key => $file) {
            if (isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
                error_log("PROXY: File trovato - Key: $key, Name: " . $file['name'] . ", Size: " . $file['size']);
                $postData[$key] = new CURLFile(
                    $file['tmp_name'],
                    $file['type'],
                    $file['name']
                );
            }
        }
        
        if (isset($_POST) && !empty($_POST)) {
            foreach ($_POST as $key => $value) {
                if (!isset($postData[$key])) {
                    $postData[$key] = $value;
                }
            }
        }
        
        if (!empty($postData)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            error_log("PROXY: Dati POST inviati con " . count($postData) . " campi");
        }
    } else {
        // Gestione dati JSON o altri formati
        $data = file_get_contents('php://input');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            // Verifica il Content-Type dall'header
            $contentType = $contentType ?: 'application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: ' . $contentType));
        }
    }
}

// Passa gli header
$headers = [];
$isMultipart = false;
$allHeaders = getallheaders();
if ($allHeaders) {
    foreach ($allHeaders as $name => $value) {
        $nameLower = strtolower($name);
        if ($nameLower === 'content-type' && strpos($value, 'multipart/form-data') !== false) {
            $isMultipart = true;
        }
        if ($nameLower !== 'host' && $nameLower !== 'connection') {
            // Per multipart, manteniamo Content-Type e Content-Length originali
            // perché stiamo inoltrando php://input direttamente
            if ($isMultipart && $nameLower === 'content-length') {
                // Manteniamo Content-Length per multipart
                $headers[] = "$name: $value";
            } else if (!$isMultipart || $nameLower !== 'content-length') {
                // Per non-multipart, escludiamo Content-Length (curl lo calcola)
                // Per multipart, includiamo tutto tranne host e connection
                $headers[] = "$name: $value";
            }
        }
    }
}
if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    error_log("PROXY: Headers passati: " . count($headers) . ($isMultipart ? " (multipart)" : ""));
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

// Controlla se la risposta è compressa (gzip) e decomprimi se necessario
// Verifica se inizia con i byte magic di gzip (0x1f 0x8b)
if (strlen($response) > 2 && ord($response[0]) === 0x1f && ord($response[1]) === 0x8b) {
    error_log("PROXY: Risposta compressa rilevata, decomprimo...");
    $decompressed = @gzdecode($response);
    if ($decompressed !== false) {
        $response = $decompressed;
        error_log("PROXY: Risposta decompressa con successo");
    } else {
        error_log("PROXY WARNING: Impossibile decomprimere la risposta, invio originale");
    }
}

error_log("PROXY SUCCESS: " . $url . " | HTTP " . $httpCode . " | Size: " . strlen($response) . " bytes");

http_response_code($httpCode);
if ($contentType) {
    header('Content-Type: ' . $contentType);
}
echo $response;

