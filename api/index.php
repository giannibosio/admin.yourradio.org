<?php
/**
 * API Router principale per YourRadio
 * Base URL: https://yourradio.org/api
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';

// Gestione CORS
handleCors();

// Inizializza database
try {
    DB::init();
} catch (Exception $e) {
    sendErrorResponse("Database connection failed", 500, 'DB_ERROR');
}

// Ottieni il path della richiesta
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Rimuovi query string e base path
$path = parse_url($requestUri, PHP_URL_PATH);
$basePath = '/api';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Rimuovi slash iniziale
$path = ltrim($path, '/');

// Dividi il path in segmenti
$segments = array_filter(explode('/', $path), function($s) { return $s !== ''; });
$segments = array_values($segments); // Re-index array

$endpoint = isset($segments[0]) ? $segments[0] : '';
$id = null;
$action = '';

// Gestione routing per endpoint con struttura speciale
if ($endpoint === 'monitor' && isset($segments[1])) {
    // /api/monitor/player/{id} o /api/monitor/ping/{id}
    $action = $segments[1];
    $id = isset($segments[2]) ? $segments[2] : null;
} elseif ($endpoint === 'spot' && isset($segments[1])) {
    // /api/spot/net o /api/spot/loc
    $action = $segments[1];
    $id = null;
} elseif ($endpoint === 'utenti' && isset($segments[1]) && isset($segments[2]) && $segments[2] === 'password') {
    // /api/utenti/{id}/password
    $id = $segments[1];
    $action = 'password';
} elseif (isset($segments[1])) {
    // Endpoint standard: /api/{endpoint}/{id} o /api/{endpoint}/{id}/{action}
    if (is_numeric($segments[1])) {
        $id = $segments[1];
        $action = isset($segments[2]) ? $segments[2] : '';
    } else {
        // Se il secondo segmento non è numerico, potrebbe essere un'action senza ID
        // Es: /api/gruppi/active (se esistesse)
        $action = $segments[1];
        $id = isset($segments[2]) && is_numeric($segments[2]) ? $segments[2] : null;
    }
}

// Ottieni i dati della richiesta
$requestData = getRequestData();

// Routing
try {
    switch ($endpoint) {
        case 'gruppi':
            require_once __DIR__ . '/endpoints/gruppi.php';
            handleGruppiRequest($requestMethod, $action, $id, $requestData);
            break;
            
        case 'songs':
            require_once __DIR__ . '/endpoints/songs.php';
            handleSongsRequest($requestMethod, $action, $id, $requestData);
            break;
            
        case 'players':
            require_once __DIR__ . '/endpoints/players.php';
            handlePlayersRequest($requestMethod, $action, $id, $requestData);
            break;
            
        case 'jingles':
            require_once __DIR__ . '/endpoints/jingles.php';
            handleJinglesRequest($requestMethod, $action, $id, $requestData);
            break;
            
        case 'spot':
            require_once __DIR__ . '/endpoints/spot.php';
            handleSpotRequest($requestMethod, $action, $id, $requestData);
            break;
            
        case 'rubriche':
            require_once __DIR__ . '/endpoints/rubriche.php';
            handleRubricheRequest($requestMethod, $action, $id, $requestData);
            break;
            
        case 'utenti':
            require_once __DIR__ . '/endpoints/utenti.php';
            handleUtentiRequest($requestMethod, $action, $id, $requestData);
            break;
            
        case 'monitor':
            require_once __DIR__ . '/endpoints/monitor.php';
            handleMonitorRequest($requestMethod, $action, $id, $requestData);
            break;
            
        case 'dashboard':
            require_once __DIR__ . '/endpoints/dashboard.php';
            handleDashboardRequest($requestMethod, $action, $id, $requestData);
            break;
            
        case 'formats':
            if ($requestMethod === 'GET') {
                // Se c'è un parametro 'all', restituisce tutti i format, altrimenti solo quelli attivi
                if (isset($_GET['all']) && $_GET['all'] == '1') {
                    $formats = Songs::selectAllFormatsAll();
                } else {
                    $formats = Songs::selectAllFormats();
                }
                sendSuccessResponse($formats);
            } else {
                sendErrorResponse("Method not allowed", 405);
            }
            break;
            
        case '':
        case 'index':
            sendSuccessResponse([
                'api' => 'YourRadio API',
                'version' => API_VERSION,
                'endpoints' => [
                    'gruppi' => '/api/gruppi',
                    'songs' => '/api/songs',
                    'players' => '/api/players',
                    'jingles' => '/api/jingles',
                    'spot' => '/api/spot',
                    'rubriche' => '/api/rubriche',
                    'utenti' => '/api/utenti',
                    'monitor' => '/api/monitor',
                    'dashboard' => '/api/dashboard',
                    'formats' => '/api/formats'
                ]
            ]);
            break;
            
        default:
            sendErrorResponse("Endpoint not found: /$endpoint", 404);
    }
} catch (Exception $e) {
    sendErrorResponse($e->getMessage(), 500, 'INTERNAL_ERROR');
}

