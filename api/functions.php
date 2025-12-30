<?php
/**
 * Utility Functions per API YourRadio
 */

function unixTimeFromDate($data, $time = 0){
    /// trasforma la data YYYY-MM-DD in unixtime
    $d = explode("-", $data);
    $h = '0';
    $m = '0';
    $s = '0';
    if($time == 1){
        $h = '23';
        $m = '59';
    }
    return mktime($h, $m, $s, $d[1], $d[2], $d[0]);
}

function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function sendErrorResponse($message, $statusCode = 400, $code = null) {
    $response = [
        'success' => false,
        'error' => [
            'message' => $message
        ]
    ];
    if ($code !== null) {
        $response['error']['code'] = $code;
    }
    sendJsonResponse($response, $statusCode);
}

function sendSuccessResponse($data, $message = null) {
    $response = [
        'success' => true,
        'data' => $data
    ];
    if ($message !== null) {
        $response['message'] = $message;
    }
    sendJsonResponse($response);
}

function handleCors() {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    
    // PHP 5.6 compatibility: use global variable
    $allowedOrigins = isset($GLOBALS['ALLOWED_ORIGINS']) ? $GLOBALS['ALLOWED_ORIGINS'] : array();
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 3600");
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateRequired($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        sendErrorResponse("Missing required fields: " . implode(', ', $missing), 400);
    }
}

function getRequestData() {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        return $_GET;
    } elseif ($method === 'POST') {
        // Controlla Content-Type in entrambi i modi (per compatibilità PHP 5.6)
        $contentType = '';
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $contentType = $_SERVER['CONTENT_TYPE'];
        } elseif (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
            $contentType = $_SERVER['HTTP_CONTENT_TYPE'];
        }
        
        if (strpos($contentType, 'application/json') !== false) {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            
            // Se json_decode fallisce, logga l'errore
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg() . " - Input: " . substr($rawInput, 0, 500));
            }
            
            return isset($data) && is_array($data) ? $data : array();
        }
        return $_POST;
    } elseif ($method === 'PUT' || $method === 'PATCH') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        // Se json_decode fallisce, logga l'errore
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg() . " - Input: " . substr($rawInput, 0, 500));
        }
        
        return isset($data) && is_array($data) ? $data : array();
    } elseif ($method === 'DELETE') {
        return $_GET;
    }
    
    return array();
}

function countFilesInDirectory($dir) {
    $totfile = 0;
    if (is_dir($dir)) {
        $d = dir($dir);
        while (($file = $d->read()) !== false) {
            if ($file != '.' && $file != '..' && $file != 'index.php' && $file != 'UploadHandler.php') {
                $totfile++;
            }
        }
        $d->close();
    }
    return $totfile;
}

/**
 * Cancella ricorsivamente una directory e tutti i suoi contenuti
 * @param string $dir Path della directory da cancellare
 * @return bool True se la cancellazione è riuscita, False altrimenti
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return true; // La directory non esiste, consideriamo il successo
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            if (!deleteDirectory($path)) {
                return false;
            }
        } else {
            if (!unlink($path)) {
                error_log("Errore: Impossibile cancellare il file: {$path}");
                return false;
            }
        }
    }
    
    if (!rmdir($dir)) {
        error_log("Errore: Impossibile cancellare la directory: {$dir}");
        return false;
    }
    
    return true;
}

/**
 * Crea la struttura di cartelle per un nuovo gruppo
 * @param string $groupName Nome del gruppo (verrà convertito in minuscolo)
 * @return bool True se la creazione è riuscita, False altrimenti
 */
function createGroupFolderStructure($groupName) {
    $folderName = strtolower(trim($groupName));
    $basePath = "/var/www/vhosts/yourradio.org/httpdocs/player/" . $folderName;
    
    // Crea la cartella principale se non esiste
    if (!is_dir($basePath)) {
        if (!mkdir($basePath, 0755, true)) {
            error_log("Errore: Impossibile creare la directory principale: {$basePath}");
            return false;
        }
    }
    
    // Crea le sottocartelle richieste
    $subfolders = array(
        'images/thumbnail',
        'jingle',
        'spot/loc',
        'xml'
    );
    
    foreach ($subfolders as $subfolder) {
        $fullPath = $basePath . '/' . $subfolder;
        if (!is_dir($fullPath)) {
            if (!mkdir($fullPath, 0755, true)) {
                error_log("Errore: Impossibile creare la sottodirectory: {$fullPath}");
                return false;
            }
        }
    }
    
    return true;
}

