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
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode(file_get_contents('php://input'), true);
            return isset($data) ? $data : [];
        }
        return $_POST;
    } elseif ($method === 'PUT' || $method === 'PATCH') {
        $data = json_decode(file_get_contents('php://input'), true);
        return isset($data) ? $data : [];
    } elseif ($method === 'DELETE') {
        return $_GET;
    }
    
    return [];
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

