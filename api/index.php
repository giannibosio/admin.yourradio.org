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
    error_log("API INDEX: Inizializzazione database...");
    DB::init();
    error_log("API INDEX: Database inizializzato con successo");
} catch (Exception $e) {
    error_log("API INDEX ERROR: " . $e->getMessage());
    error_log("API INDEX ERROR: DB_HOST=" . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED'));
    error_log("API INDEX ERROR: DB_NAME=" . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED'));
    sendErrorResponse("Database connection failed: " . $e->getMessage(), 500, 'DB_ERROR');
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
} elseif ($endpoint === 'players' && isset($segments[1]) && isset($segments[2]) && $segments[2] === 'password') {
    // /api/players/{id}/password
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
            // Gestione filtri nella sessione
            if ($action === 'filters') {
                // Avvia la sessione se non è già avviata
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                if ($requestMethod === 'GET') {
                    // Recupera i filtri dalla sessione
                    $filters = isset($_SESSION['songs_filters']) ? $_SESSION['songs_filters'] : array();
                    error_log("GET /api/songs/filters - Filtri recuperati: " . print_r($filters, true));
                    sendSuccessResponse($filters);
                } elseif ($requestMethod === 'POST' || $requestMethod === 'PUT') {
                    // Salva i filtri nella sessione
                    error_log("POST/PUT /api/songs/filters - Dati ricevuti: " . print_r($requestData, true));
                    $_SESSION['songs_filters'] = $requestData;
                    error_log("POST/PUT /api/songs/filters - Filtri salvati in sessione: " . print_r($_SESSION['songs_filters'], true));
                    sendSuccessResponse($requestData, "Filtri salvati con successo");
                } elseif ($requestMethod === 'DELETE') {
                    // Rimuovi i filtri dalla sessione
                    unset($_SESSION['songs_filters']);
                    sendSuccessResponse(array(), "Filtri rimossi con successo");
                } else {
                    sendErrorResponse("Method not allowed", 405);
                }
            } else {
                handleSongsRequest($requestMethod, $action, $id, $requestData);
            }
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
            
        case 'auth':
            require_once __DIR__ . '/endpoints/auth.php';
            handleAuthRequest($requestMethod, $action, $id, $requestData);
            break;
            
        case 'subgruppi':
            require_once __DIR__ . '/endpoints/gruppi.php';
            // Gestione endpoint /api/subgruppi/{id}/players
            if (isset($segments[1]) && is_numeric($segments[1]) && isset($segments[2]) && $segments[2] === 'players') {
                $subgruppoId = $segments[1];
                $players = Gruppi::selectAllPlayersSottoGruppoById($subgruppoId);
                $result = [];
                foreach ($players as $p) {
                    $status = ($p['pl_active'] == 1) ? "ON" : "OFF";
                    $result[] = [
                        'id' => (int)$p['pl_id'],
                        'nome' => strtoupper($p['pl_nome']),
                        'attivo' => $status,
                        'ultimo_accesso' => substr($p['pl_player_ultimaDataEstesa'], 0, 10)
                    ];
                }
                sendSuccessResponse($result);
            } else {
                sendErrorResponse("Endpoint non valido. Usa /api/subgruppi/{id}/players", 400);
            }
            break;
            
        case 'formats':
            if ($requestMethod === 'GET') {
                try {
                    // Se c'è un parametro 'all', restituisce tutti i format, altrimenti solo quelli attivi
                    if (isset($_GET['all']) && $_GET['all'] == '1') {
                        $formats = Songs::selectAllFormatsAll();
                    } else {
                        $formats = Songs::selectAllFormats();
                    }
                    
                    if ($formats === false || $formats === null) {
                        sendErrorResponse("Errore nel recupero dei format", 500);
                    } else {
                        sendSuccessResponse($formats);
                    }
                } catch (Exception $e) {
                    sendErrorResponse("Errore: " . $e->getMessage(), 500);
                }
            } elseif ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
                // Aggiorna format
                try {
                    if ($id === null) {
                        sendErrorResponse("ID format richiesto", 400);
                    }
                    
                    // Debug: log dei dati ricevuti
                    error_log("PUT /api/formats/" . $id . " - Dati ricevuti: " . print_r($data, true));
                    
                    // Se i dati non sono un array, prova a decodificarli manualmente
                    if (!is_array($data)) {
                        $rawInput = file_get_contents('php://input');
                        if (!empty($rawInput)) {
                            $decoded = json_decode($rawInput, true);
                            if (is_array($decoded)) {
                                $data = $decoded;
                            } else {
                                sendErrorResponse("Dati non validi. Atteso JSON object.", 400);
                            }
                        } else {
                            sendErrorResponse("Nessun dato ricevuto", 400);
                        }
                    }
                    
                    $result = Songs::updateFormat($id, $data);
                    if ($result) {
                        sendSuccessResponse(['id' => $id], "Format aggiornato con successo");
                    } else {
                        sendErrorResponse("Errore nell'aggiornamento del format", 500);
                    }
                } catch (Exception $e) {
                    error_log("Errore in PUT /api/formats: " . $e->getMessage());
                    sendErrorResponse("Errore: " . $e->getMessage(), 500);
                }
            } elseif ($requestMethod === 'DELETE') {
                // Cancella format
                try {
                    if ($id === null) {
                        sendErrorResponse("ID format richiesto", 400);
                    }
                    
                    $result = Songs::deleteFormat($id);
                    if ($result) {
                        sendSuccessResponse(['id' => $id], "Format cancellato con successo");
                    } else {
                        sendErrorResponse("Errore nella cancellazione del format", 500);
                    }
                } catch (Exception $e) {
                    error_log("Errore in DELETE /api/formats: " . $e->getMessage());
                    sendErrorResponse("Errore: " . $e->getMessage(), 500);
                }
            } elseif ($requestMethod === 'POST') {
                // Crea nuovo format
                try {
                    // Debug: log dei dati ricevuti
                    error_log("POST /api/formats - Content-Type: " . (isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : 'non impostato'));
                    error_log("POST /api/formats - Raw input: " . file_get_contents('php://input'));
                    error_log("POST /api/formats - Dati parsati: " . print_r($data, true));
                    error_log("POST /api/formats - Tipo dati: " . gettype($data));
                    
                    // Se i dati non sono un array, prova a decodificarli manualmente
                    if (!is_array($data)) {
                        $rawInput = file_get_contents('php://input');
                        if (!empty($rawInput)) {
                            $decoded = json_decode($rawInput, true);
                            if (is_array($decoded)) {
                                $data = $decoded;
                                error_log("POST /api/formats - Dati decodificati manualmente: " . print_r($data, true));
                            } else {
                                error_log("POST /api/formats - Impossibile decodificare JSON. Errore: " . json_last_error_msg());
                                sendErrorResponse("Dati non validi. Atteso JSON object. Errore: " . json_last_error_msg(), 400);
                            }
                        } else {
                            // Se non ci sono dati raw, prova $_POST
                            if (!empty($_POST)) {
                                $data = $_POST;
                                error_log("POST /api/formats - Usati dati da \$_POST: " . print_r($data, true));
                            } else {
                                sendErrorResponse("Nessun dato ricevuto", 400);
                            }
                        }
                    }
                    
                    // Verifica che i dati siano un array
                    if (!is_array($data)) {
                        sendErrorResponse("Dati non validi. Atteso JSON object. Tipo ricevuto: " . gettype($data), 400);
                    }
                    
                    // Verifica i campi richiesti
                    if (!isset($data['frmt_nome']) || empty(trim($data['frmt_nome']))) {
                        sendErrorResponse("Il campo 'frmt_nome' è obbligatorio", 400);
                    }
                    if (!isset($data['frmt_descrizione']) || empty(trim($data['frmt_descrizione']))) {
                        sendErrorResponse("Il campo 'frmt_descrizione' è obbligatorio", 400);
                    }
                    
                    $newId = Songs::createFormat($data);
                    if ($newId) {
                        sendSuccessResponse(['id' => $newId], "Format creato con successo", 201);
                    } else {
                        sendErrorResponse("Errore nella creazione del format", 500);
                    }
                } catch (Exception $e) {
                    error_log("Errore in POST /api/formats: " . $e->getMessage());
                    sendErrorResponse("Errore: " . $e->getMessage(), 500);
                }
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

