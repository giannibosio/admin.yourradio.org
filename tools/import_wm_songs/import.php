<?php
/**
 * Pagina web per importare canzoni da CSV Watermelon
 */

require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/database.php';

$pageTitle = "Import Songs Watermelon";

// Gestione richieste AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_GET['action'] === 'list_csv') {
        // Lista i file CSV disponibili
        $csvDir = __DIR__ . '/csv/';
        $csvFiles = [];
        if (is_dir($csvDir)) {
            $files = scandir($csvDir);
            if ($files !== false) {
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && is_file($csvDir . $file)) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if ($ext === 'csv') {
                            $csvFiles[] = $file;
                        }
                    }
                }
            }
        }
        echo json_encode(['success' => true, 'files' => $csvFiles, 'debug' => ['dir' => $csvDir, 'exists' => is_dir($csvDir)]]);
        exit;
    }
    
    if ($_GET['action'] === 'count_files') {
        // Conta i file nella cartella /file
        $fileDir = __DIR__ . '/file/';
        $fileCount = 0;
        if (is_dir($fileDir)) {
            $files = scandir($fileDir);
            if ($files !== false) {
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && is_file($fileDir . $file)) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if ($ext === 'mp3') {
                            $fileCount++;
                        }
                    }
                }
            }
        }
        
        // Conta i file nella cartella /filenew
        $fileNewDir = __DIR__ . '/filenew/';
        $filenewCount = 0;
        if (is_dir($fileNewDir)) {
            $files = scandir($fileNewDir);
            if ($files !== false) {
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && is_file($fileNewDir . $file)) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if ($ext === 'mp3') {
                            $filenewCount++;
                        }
                    }
                }
            }
        }
        
        echo json_encode(['success' => true, 'count' => $fileCount, 'filenew_count' => $filenewCount]);
        exit;
    }
    
    if ($_GET['action'] === 'execute_import' && isset($_POST['csv_file']) && isset($_POST['id_format'])) {
        try {
            // Abilita la visualizzazione degli errori per debug
            error_reporting(E_ALL);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
            // Aumenta i timeout per evitare errori 504
            set_time_limit(600); // 10 minuti
            ini_set('max_execution_time', 600);
            ini_set('default_socket_timeout', 120);
            
            // Disabilita il buffering dell'output per evitare timeout
            if (ob_get_level()) {
                ob_end_clean();
            }
            // Invia header per mantenere la connessione aperta
            header('X-Accel-Buffering: no');
            ignore_user_abort(false);
            
            // File di log
            $logFile = __DIR__ . '/log.txt';
            
            // Funzione per scrivere nel log
            function writeLog($message, $logFile) {
                $timestamp = date('Y-m-d H:i:s');
                $logMessage = "[$timestamp] $message" . PHP_EOL;
                file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
            }
            
            // Inizia il log
            writeLog("=== INIZIO IMPORT ===", $logFile);
            writeLog("CSV File: " . basename($_POST['csv_file']), $logFile);
            writeLog("Format ID: " . $idFormat, $logFile);
            
            // Funzione per inviare heartbeat (mantiene la connessione viva)
            function sendHeartbeat() {
                // Invia uno spazio per mantenere la connessione aperta
                echo " ";
                if (ob_get_level()) {
                    @ob_flush();
                }
                @flush();
            }
            
            // Funzione per inviare output di progresso (mantiene la connessione viva)
            function sendProgress($message = '', $logFile = null) {
                sendHeartbeat();
                if ($message && $logFile) {
                    writeLog($message, $logFile);
                }
            }
            
            // Esegue l'import
            $csvFile = __DIR__ . '/csv/' . basename($_POST['csv_file']);
            $fileDir = __DIR__ . '/file/';
            $fileNewDir = __DIR__ . '/filenew/';
            $idFormat = (int)$_POST['id_format'];
            
            // Verifica che il file CSV esista
            if (!file_exists($csvFile)) {
                echo json_encode(['success' => false, 'message' => 'File CSV non trovato: ' . $csvFile]);
                exit;
            }
            
            // Usa le API invece della connessione diretta al database
            $apiBaseUrl = "https://yourradio.org/api";
        
        // Funzione helper per chiamate API
        function callApi($url, $method = 'GET', $data = null) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Aumentato a 120 secondi
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // Timeout per la connessione
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            
            if ($method === 'POST' || $method === 'PUT') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $curlInfo = curl_getinfo($ch);
            curl_close($ch);
            
            if ($error) {
                error_log("Errore cURL: $error (URL: $url, Method: $method)");
                return ['success' => false, 'error' => $error, 'http_code' => 0, 'curl_info' => $curlInfo];
            }
            
            if ($httpCode === 0 || $response === false) {
                error_log("Risposta vuota o errore HTTP (URL: $url, HTTP Code: $httpCode)");
                return ['success' => false, 'error' => 'Risposta vuota dal server', 'http_code' => $httpCode, 'curl_info' => $curlInfo];
            }
            
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Errore parsing JSON: " . json_last_error_msg() . " (URL: $url, Response: " . substr($response, 0, 200) . ")");
                return ['success' => false, 'error' => 'Errore parsing JSON: ' . json_last_error_msg(), 'http_code' => $httpCode, 'raw_response' => $response];
            }
            
            return [
                'success' => ($httpCode >= 200 && $httpCode < 300) && isset($result['success']) && $result['success'],
                'data' => isset($result['data']) ? $result['data'] : null,
                'message' => isset($result['message']) ? $result['message'] : null,
                'http_code' => $httpCode,
                'raw_response' => $result,
                'curl_info' => $curlInfo
            ];
        }
        
        // Recupera i valori massimi dall'API
        $maxIdsResponse = callApi($apiBaseUrl . "/songs/maxids");
        $httpCode = $maxIdsResponse['http_code'];
        $response = json_encode($maxIdsResponse['raw_response']);
        
        if (!$maxIdsResponse['success']) {
            echo json_encode(['success' => false, 'message' => 'Errore nel recupero dei valori massimi: ' . ($maxIdsResponse['message'] ?? 'Errore sconosciuto')]);
            exit;
        }
        
        $sgIdCounter = isset($maxIdsResponse['data']['next_sg_id']) ? (int)$maxIdsResponse['data']['next_sg_id'] : 1;
        $sgFileCounter = isset($maxIdsResponse['data']['next_sg_file']) ? (int)$maxIdsResponse['data']['next_sg_file'] : 1;
        
        // Crea la cartella filenew se non esiste
        if (!is_dir($fileNewDir)) {
            mkdir($fileNewDir, 0755, true);
        }
        
        // Apri il file CSV
        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            echo json_encode(['success' => false, 'message' => 'Impossibile aprire il file CSV']);
            exit;
        }
        
        // Leggi l'header
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if ($header === false) {
            fclose($handle);
            echo json_encode(['success' => false, 'message' => 'Impossibile leggere l\'header del CSV']);
            exit;
        }
        
        // Trova gli indici delle colonne
        $filenameIndex = array_search('filename', $header);
        $trackTitleIndex = array_search('track_title', $header);
        $artistIndex = array_search('artist', $header);
        
        if ($filenameIndex === false || $trackTitleIndex === false || $artistIndex === false) {
            fclose($handle);
            echo json_encode(['success' => false, 'message' => 'Colonne mancanti nel CSV']);
            exit;
        }
        
        $results = [];
        $rowCount = 0;
        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        $formatAddedCount = 0;
        
        $debugInfo = [];
        
        // Leggi ogni riga del CSV
        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rowCount++;
            
            // Invia heartbeat ogni 5 righe per mantenere la connessione viva
            if ($rowCount % 5 === 0) {
                sendHeartbeat();
            }
            
            $filename = trim($data[$filenameIndex] ?? '');
            $trackTitle = trim($data[$trackTitleIndex] ?? '');
            $artist = trim($data[$artistIndex] ?? '');
            
            writeLog("--- RIGA $rowCount ---", $logFile);
            writeLog("Filename CSV: $filename", $logFile);
            writeLog("Track Title: $trackTitle", $logFile);
            writeLog("Artist: $artist", $logFile);
            
            if (empty($filename) && empty($trackTitle) && empty($artist)) {
                writeLog("Riga vuota, saltata", $logFile);
                continue;
            }
            
            $sourceFile = $fileDir . $filename;
            if (!file_exists($sourceFile)) {
                writeLog("ERRORE: File non presente in /file: $filename", $logFile);
                $results[] = [
                    'row' => $rowCount,
                    'status' => 'error',
                    'type' => 'file_not_present',
                    'filename' => $filename,
                    'track_title' => $trackTitle,
                    'artist' => $artist,
                    'message' => "File not present: $filename"
                ];
                $errorCount++;
                continue;
            }
            
            $filesize = filesize($sourceFile);
            if ($filesize === false) {
                writeLog("ERRORE: Impossibile ottenere la dimensione del file: $filename", $logFile);
                $results[] = [
                    'row' => $rowCount,
                    'status' => 'error',
                    'type' => 'filesize_error',
                    'filename' => $filename,
                    'message' => 'Impossibile ottenere la dimensione del file'
                ];
                $errorCount++;
                continue;
            }
            
            writeLog("File size: $filesize bytes", $logFile);
            
            // Debug info per questa riga
            $rowDebug = [
                'row' => $rowCount,
                'filename' => $filename,
                'track_title' => $trackTitle,
                'artist' => $artist
            ];
            
            // Cerca se esiste già una song con questo filename nel campo sg_filename_wm
            $existingSong = null;
            $existingSgId = null;
            $existingSgFile = null;
            
            try {
                writeLog("Verifica song nel DB per filename: $filename", $logFile);
                $apiResponse = callApi($apiBaseUrl . "/songs/byfilename?filename=" . urlencode($filename));
                $rowDebug['api_call_song_by_filename'] = true;
                $rowDebug['api_response_song'] = $apiResponse;
                
                if ($apiResponse['success'] && isset($apiResponse['data']['exists']) && $apiResponse['data']['exists']) {
                    $existingSong = $apiResponse['data']['song'];
                    $existingSgId = (int)$apiResponse['data']['sg_id'];
                    $existingSgFile = isset($apiResponse['data']['sg_file']) ? (int)$apiResponse['data']['sg_file'] : null;
                    $rowDebug['song_found_in_db'] = true;
                    $rowDebug['existing_sg_id'] = $existingSgId;
                    $rowDebug['existing_sg_file'] = $existingSgFile;
                    writeLog("Song trovata nel DB: sg_id=$existingSgId, sg_file=$existingSgFile", $logFile);
                } else {
                    $rowDebug['song_found_in_db'] = false;
                    writeLog("Song NON trovata nel DB", $logFile);
                }
            } catch (Exception $e) {
                error_log("Errore verifica song per filename (riga $rowCount): " . $e->getMessage());
                writeLog("ERRORE verifica song: " . $e->getMessage(), $logFile);
                $rowDebug['error'] = $e->getMessage();
                $results[] = [
                    'row' => $rowCount,
                    'status' => 'error',
                    'type' => 'api_error',
                    'filename' => $filename,
                    'track_title' => $trackTitle,
                    'artist' => $artist,
                    'message' => 'Errore nella verifica song: ' . $e->getMessage()
                ];
                $errorCount++;
                $debugInfo['rows'][] = $rowDebug;
                continue;
            }
            
            // Se la song NON esiste nel DB, procedi con la creazione
            if (!$existingSong) {
                writeLog("AZIONE: Creazione nuova song", $logFile);
                // Genera il sg_file e sg_id per questa nuova song
                $sgId = $sgIdCounter;
                $sgFile = $sgFileCounter;
                $destFileName = $sgFile . '.mp3';
                $destFile = $fileNewDir . $destFileName;
                
                writeLog("Assegnato sg_id: $sgId, sg_file: $sgFile", $logFile);
                
                $rowDebug['action'] = 'new_song_creation';
                $rowDebug['sg_id'] = $sgId;
                $rowDebug['sg_file'] = $sgFile;
                $rowDebug['dest_file_name'] = $destFileName;
                
                // Copia il file audio nella folder filenew rinominandolo con sg_file.mp3
                writeLog("Copia file: $filename -> $destFileName", $logFile);
                $copySuccess = false;
                try {
                    $copySuccess = copy($sourceFile, $destFile);
                    $rowDebug['copy_success'] = $copySuccess;
                    if ($copySuccess) {
                        writeLog("File copiato con successo", $logFile);
                    } else {
                        writeLog("ERRORE: Copia file fallita", $logFile);
                    }
                } catch (Exception $e) {
                    error_log("Errore copia file (riga $rowCount): " . $e->getMessage());
                    writeLog("ERRORE copia file: " . $e->getMessage(), $logFile);
                    $rowDebug['copy_error'] = $e->getMessage();
                }
                
                if (!$copySuccess) {
                    $results[] = [
                        'row' => $rowCount,
                        'status' => 'error',
                        'type' => 'copy_failed',
                        'filename' => $filename,
                        'track_title' => $trackTitle,
                        'artist' => $artist,
                        'message' => 'Errore nella copia del file'
                    ];
                    $errorCount++;
                    $debugInfo['rows'][] = $rowDebug;
                    // Incrementa i counter anche in caso di errore
                    $sgIdCounter++;
                    $sgFileCounter++;
                    continue;
                }
                
                // Crea il record in songs tramite API
                $songData = [
                    'sg_id' => $sgId,
                    'sg_file' => $sgFile,
                    'sg_filesize' => $filesize,
                    'sg_titolo' => $trackTitle,
                    'sg_artista' => $artist,
                    'sg_filename_wm' => $filename,
                    'sg_diritti' => 3,
                    'formats' => [$idFormat] // Includi il format nella creazione
                ];
                
                $sgTitoloEscaped = addslashes($trackTitle);
                $sgArtistaEscaped = addslashes($artist);
                $sgFilenameWmEscaped = addslashes($filename);
                
                $querySongs = "INSERT INTO `songs` (`sg_id`, `sg_file`, `sg_filesize`, `sg_titolo`, `sg_artista`, `sg_filename_wm`, `sg_diritti`) " .
                             "VALUES ($sgId, $sgFile, $filesize, '$sgTitoloEscaped', '$sgArtistaEscaped', '$sgFilenameWmEscaped', 3);";
                $querySongFormat = "INSERT INTO `song_format` (`id_song`, `id_format`) VALUES ($sgId, $idFormat);";
                
                writeLog("Inserimento song nel DB: sg_id=$sgId, sg_file=$sgFile", $logFile);
                $songApiResponse = callApi($apiBaseUrl . "/songs", 'POST', $songData);
                $rowDebug['api_call_song_insert'] = true;
                $rowDebug['api_response_song_insert'] = $songApiResponse;
                
                $songInsertSuccess = false;
                $formatInsertSuccess = false;
                $songInsertError = null;
                $formatInsertError = null;
                
                if ($songApiResponse['success']) {
                    $songInsertSuccess = true;
                    // Il format viene gestito automaticamente da createSong se formats è incluso
                    $formatInsertSuccess = true;
                    writeLog("Song inserita con successo nel DB", $logFile);
                    writeLog("Format $idFormat abbinato automaticamente", $logFile);
                } else {
                    $songInsertError = isset($songApiResponse['raw_response']['message']) ? $songApiResponse['raw_response']['message'] : 'Errore API';
                    error_log("Errore inserimento songs via API (riga $rowCount, sg_id=$sgId): " . $songInsertError);
                    writeLog("ERRORE inserimento song: $songInsertError", $logFile);
                }
                
                $results[] = [
                    'row' => $rowCount,
                    'status' => ($songInsertSuccess && $formatInsertSuccess) ? 'success' : 'error',
                    'type' => 'new_song_created',
                    'filename' => $filename,
                    'track_title' => $trackTitle,
                    'artist' => $artist,
                    'filesize' => $filesize,
                    'sg_id' => $sgId,
                    'sg_file' => $sgFile,
                    'query_songs' => $querySongs,
                    'query_song_format' => $querySongFormat,
                    'message' => ($songInsertSuccess && $formatInsertSuccess) ? 
                        "File copiato: $filename -> $destFileName" : 
                        ('Errore inserimento: ' . ($songInsertError ?? $formatInsertError)),
                    'query_executed' => $songInsertSuccess && $formatInsertSuccess,
                    'query_error' => $songInsertError ?? $formatInsertError
                ];
                
                if ($songInsertSuccess && $formatInsertSuccess) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
                
                $rowDebug['song_insert_success'] = $songInsertSuccess;
                $rowDebug['format_insert_success'] = $formatInsertSuccess;
                $rowDebug['counters_before'] = ['sg_id' => $sgIdCounter, 'sg_file' => $sgFileCounter];
                // Incrementa i counter per la prossima song
                $sgIdCounter++;
                $sgFileCounter++;
                $rowDebug['counters_after'] = ['sg_id' => $sgIdCounter, 'sg_file' => $sgFileCounter];
                $debugInfo['rows'][] = $rowDebug;
                continue;
            }
            
            // Se la song ESISTE già nel DB, verifica se è già abbinata al format
            if ($existingSong) {
                writeLog("AZIONE: Song esistente, verifica format", $logFile);
                $rowDebug['action'] = 'existing_song_check_format';
                
                // Verifica se esiste già l'abbinamento con il format tramite API
                writeLog("Verifica abbinamento format $idFormat per song sg_id=$existingSgId", $logFile);
                $formatApiResponse = callApi($apiBaseUrl . "/songs/" . $existingSgId . "/format?id_format=" . $idFormat);
                $rowDebug['api_call_format_check'] = true;
                $rowDebug['api_response_format'] = $formatApiResponse;
                
                $existingFormat = null;
                if ($formatApiResponse['success'] && isset($formatApiResponse['data']['exists']) && $formatApiResponse['data']['exists']) {
                    $existingFormat = ['id_song' => $existingSgId];
                }
                
                $rowDebug['format_exists'] = !empty($existingFormat);
                
                if ($existingFormat) {
                    // Già abbinata al format - non fare nulla
                    writeLog("Format $idFormat già abbinato, riga saltata", $logFile);
                    $results[] = [
                        'row' => $rowCount,
                        'status' => 'skipped',
                        'type' => 'already_processed',
                        'filename' => $filename,
                        'track_title' => $trackTitle,
                        'artist' => $artist,
                        'sg_id' => $existingSgId,
                        'sg_file' => $existingSgFile,
                        'query_songs' => null,
                        'query_song_format' => null,
                        'message' => 'Song già registrata nel DB e già abbinata al format'
                    ];
                    $skippedCount++;
                    $rowDebug['action'] = 'skipped_already_processed';
                    $debugInfo['rows'][] = $rowDebug;
                    continue;
                } else {
                    // Manca l'abbinamento, aggiungi solo song_format tramite API
                    writeLog("Format $idFormat mancante, aggiunta abbinamento", $logFile);
                    $querySongFormat = "INSERT INTO `song_format` (`id_song`, `id_format`) VALUES ($existingSgId, $idFormat);";
                    
                    writeLog("Inserimento song_format: id_song=$existingSgId, id_format=$idFormat", $logFile);
                    $formatApiInsertResponse = callApi($apiBaseUrl . "/songs/" . $existingSgId . "/format", 'POST', ['id_format' => $idFormat]);
                    $rowDebug['api_call_format_insert'] = true;
                    $rowDebug['api_response_format_insert'] = $formatApiInsertResponse;
                    
                    $queryExecuted = false;
                    $queryError = null;
                    
                    if ($formatApiInsertResponse['success']) {
                        $queryExecuted = true;
                        writeLog("Format inserito con successo", $logFile);
                    } else {
                        // Estrai il messaggio di errore più dettagliato
                        if (isset($formatApiInsertResponse['raw_response']['message'])) {
                            $queryError = $formatApiInsertResponse['raw_response']['message'];
                        } elseif (isset($formatApiInsertResponse['message'])) {
                            $queryError = $formatApiInsertResponse['message'];
                        } elseif (isset($formatApiInsertResponse['error'])) {
                            $queryError = $formatApiInsertResponse['error'];
                        } else {
                            $queryError = 'Errore API (HTTP ' . $formatApiInsertResponse['http_code'] . ')';
                        }
                        error_log("Errore inserimento song_format via API (riga $rowCount, sg_id=$existingSgId, format=$idFormat): " . $queryError);
                        error_log("Risposta completa API: " . json_encode($formatApiInsertResponse));
                        writeLog("ERRORE inserimento format: $queryError", $logFile);
                    }
                    
                    $results[] = [
                        'row' => $rowCount,
                        'status' => $queryExecuted ? 'format_added' : 'error',
                        'type' => 'format_added_only',
                        'filename' => $filename,
                        'track_title' => $trackTitle,
                        'artist' => $artist,
                        'sg_id' => $existingSgId,
                        'sg_file' => $existingSgFile,
                        'query_songs' => null,
                        'query_song_format' => $querySongFormat,
                        'message' => $queryExecuted ? 'Song già registrata nel DB, aggiunto abbinamento format' : ('Errore inserimento format: ' . $queryError),
                        'query_executed' => $queryExecuted,
                        'query_error' => $queryError
                    ];
                    
                    if ($queryExecuted) {
                        $formatAddedCount++;
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                    
                    $rowDebug['action'] = $queryExecuted ? 'format_added_only' : 'format_added_error';
                    $rowDebug['query_executed'] = $queryExecuted;
                    $rowDebug['query_error'] = $queryError;
                    $debugInfo['rows'][] = $rowDebug;
                    continue;
                }
            }
        }
        
        fclose($handle);
        
        writeLog("=== FINE IMPORT ===", $logFile);
        writeLog("Totale righe processate: $rowCount", $logFile);
        writeLog("Successi: $successCount", $logFile);
        writeLog("Errori: $errorCount", $logFile);
        writeLog("Saltate: $skippedCount", $logFile);
        writeLog("Format aggiunti: $formatAddedCount", $logFile);
        
        // Pulisci eventuali output di heartbeat prima di inviare JSON
        if (ob_get_level()) {
            ob_clean();
        }
        
        echo json_encode([
                'success' => true,
                'row_count' => $rowCount,
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'skipped_count' => $skippedCount,
                'format_added_count' => $formatAddedCount,
                'results' => $results,
                'debug' => $debugInfo
            ]);
            exit;
        } catch (Exception $e) {
            error_log("Errore in execute_import: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'message' => 'Errore durante l\'import: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            exit;
        } catch (Error $e) {
            error_log("Errore fatale in execute_import: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'message' => 'Errore fatale durante l\'import: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            exit;
        }
    }
}

// Head personalizzato con percorsi corretti
$pageReq=substr($_SERVER['REQUEST_URI'],strrpos($_SERVER['REQUEST_URI'],"/")+1);
?>
<!doctype html>
<html lang="it">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="<?=SITE_DESCRIPTION?>">
    <link rel="icon" href="../../assets/images/favicon.ico">
    <title><?=isset($pageTitle) && $pageTitle != '' ? $pageTitle . ' - ' . SITE_TITLE : SITE_TITLE?></title>
    <!-- Simple bar CSS -->
    <link rel="stylesheet" href="../../css/simplebar.css">
    <!-- Fonts CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Overpass:ital,wght@0,100;0,200;0,300;0,400;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <!-- Icons CSS -->
    <link rel="stylesheet" href="../../css/feather.css">
    <!-- Date Range Picker CSS -->
    <link rel="stylesheet" href="../../css/daterangepicker.css">
    <!-- App CSS -->
    <link rel="stylesheet" href="../../css/app-light.css" id="lightTheme" disabled>
    <link rel="stylesheet" href="../../css/app-dark.css" id="darkTheme">

    <script src="../../js/yourradio.js"></script>
    <script>
      (function() {
        if (!document.querySelector('#modeSwitcher')) {
          var switcher = document.createElement('a');
          switcher.id = 'modeSwitcher';
          switcher.className = 'nav-link text-muted my-2';
          switcher.href = '#';
          switcher.setAttribute('data-mode', 'dark');
          switcher.style.display = 'none';
          if (document.body) {
            document.body.appendChild(switcher);
          } else {
            var checkBody = setInterval(function() {
              if (document.body) {
                document.body.appendChild(switcher);
                clearInterval(checkBody);
              }
            }, 10);
            setTimeout(function() {
              clearInterval(checkBody);
              if (document.body && !document.querySelector('#modeSwitcher')) {
                document.body.appendChild(switcher);
              }
            }, 1000);
          }
        }
      })();
    </script>
  </head>

<body class="horizontal dark">
  <div class="wrapper">
    <!-- Header personalizzato -->
    <header class="bg-dark text-center py-4 mb-4" style="background-color: #000 !important;">
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-12">
            <a href="../../index.php" class="d-inline-block mb-3">
              <img src="../../assets/images/logo-yourradio-maxi.png" alt="YourRadio" height="60">
            </a>
            <a href="../../index.php" class="btn btn-outline-light">
              <span class="fe fe-log-out"></span> ESCI
            </a>
          </div>
        </div>
      </div>
    </header>
    
    <main role="main" class="main-content">
      <div class="container-fluid">
        <div class="row justify-content-center">
          <div class="col-12">
            <div class="row align-items-center mb-2">
              <div class="col">
                <h2 class="h2 text-white text-center page-title">TOOLS</h2>
              </div>
            </div>
            
            <div class="card shadow mb-4">
              <div class="card-header">
                <h4 class="card-title">Import Songs Watermelon</h4>
              </div>
              <div class="card-body">
                  <form id="importForm">
                    <div class="row mb-4">
                      <div class="col-md-6">
                        <label for="csvSelect" class="form-label"><strong>Seleziona File CSV:</strong></label>
                        <select id="csvSelect" class="form-control" required>
                          <option value="">Caricamento...</option>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label for="formatSelect" class="form-label"><strong>Seleziona Format ID:</strong></label>
                        <select id="formatSelect" class="form-control" required>
                          <option value="49">Aumasi format Watermelon (ID: 49)</option>
                          <option value="53" selected>ODS format watermelon top (ID: 53)</option>
                          <option value="52">KFC format watermelon young (ID: 52)</option>
                        </select>
                      </div>
                    </div>
                    
                    <div class="alert alert-info mb-4">
                      <h5 class="text-dark">Informazioni Import</h5>
                      <div class="row">
                        <div class="col-md-6">
                          <p><strong class="text-dark">File CSV:</strong> <span id="selectedCsvFile" class="text-dark">-</span></p>
                          <p><strong class="text-dark">Format ID:</strong> <span id="selectedFormatId" class="text-dark">-</span></p>
                          <p><strong class="text-dark">Valore massimo sg_id trovato:</strong> <span id="maxSgId" class="text-dark">-</span></p>
                          <p><strong class="text-dark">Valore massimo sg_file trovato:</strong> <span id="maxSgFile" class="text-dark">-</span></p>
                          <p><strong class="text-dark">Primo sg_id da utilizzare:</strong> <span id="nextSgId" class="text-dark">-</span></p>
                          <p><strong class="text-dark">Primo sg_file da utilizzare:</strong> <span id="nextSgFile" class="text-dark">-</span></p>
                        </div>
                        <div class="col-md-6">
                          <p><strong class="text-dark">Totale file songs disponibili in /file:</strong> <span id="fileCount" class="text-dark">-</span></p>
                          <p><strong class="text-dark">Totale file disponibili in /filenew:</strong> <span id="filenewCount" class="text-dark">-</span></p>
                        </div>
                      </div>
                    </div>
                    
                    <div class="text-center">
                      <button type="button" class="btn btn-primary btn-lg" id="executeBtn">
                        <span class="fe fe-play"></span> AVVIA
                      </button>
                    </div>
                  </form>
                  
                  <div id="resultsPanel" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <h5 class="mb-0">Risultati Import</h5>
                      <button type="button" class="btn btn-secondary" id="exportTxtBtn">
                        <span class="fe fe-download"></span> Esporta TXT
                      </button>
                    </div>
                    <div id="resultsSummary" class="alert alert-light mb-4"></div>
                    <div id="resultsContent" style="max-height: 600px; overflow-y: auto;"></div>
                  </div>
                  
                  <div id="loadingSpinner" class="text-center" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                      <span class="sr-only">Caricamento...</span>
                    </div>
                    <p class="mt-2">Elaborazione in corso...</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Modale conferma esecuzione -->
  <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Conferma Esecuzione</h5>
          <button type="button" class="close" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p>Sei sicuro di voler eseguire l'import?</p>
          <p class="text-muted"><small>File CSV: <strong id="confirmCsvFile">-</strong></p>
          <p class="text-muted"><small>Format ID: <strong id="confirmFormatId">-</strong></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
          <button type="button" class="btn btn-primary" id="confirmExecuteBtn">Conferma</button>
        </div>
      </div>
    </div>
  </div>

  <script src="../../js/jquery.min.js"></script>
  <script src="../../js/popper.min.js"></script>
  <script src="../../js/moment.min.js"></script>
  <script src="../../js/bootstrap.min.js"></script>
  <script src="../../js/simplebar.min.js"></script>
  <script src="../../js/tinycolor-min.js"></script>
  <script src="../../js/config.js"></script>
  <script src="../../js/jquery.stickOnScroll.js"></script>
  <script src="../../js/apps.js"></script>
  <script>
    let selectedCsvFile = '';
    let idFormat = 53; // Default format ID
    
    // Inizializza quando il documento è pronto
    $(document).ready(function() {
      
      // Inizializza Format ID
      idFormat = parseInt($('#formatSelect').val());
      $('#selectedFormatId').text(idFormat);
      
      // Carica CSV all'avvio
      loadCsvFiles();
      
      // Aggiorna format ID quando cambia la selezione
      $('#formatSelect').on('change', function() {
        idFormat = parseInt($(this).val());
        $('#selectedFormatId').text(idFormat);
        if (selectedCsvFile) {
          loadImportInfo();
        }
      });
      
      // Aggiorna dati quando cambia CSV
      $('#csvSelect').on('change', function() {
        selectedCsvFile = $(this).val();
        $('#selectedCsvFile').text(selectedCsvFile || '-');
        if (selectedCsvFile) {
          loadImportInfo();
        } else {
          // Reset valori se nessun CSV selezionato
          $('#maxSgId').text('-');
          $('#maxSgFile').text('-');
          $('#nextSgId').text('-');
          $('#nextSgFile').text('-');
          $('#fileCount').text('-');
          $('#filenewCount').text('-');
        }
      });
    });
    
    // Carica lista CSV
    function loadCsvFiles() {
      console.log('Caricamento lista CSV...');
      const currentUrl = window.location.href.split('?')[0];
      $.ajax({
        url: currentUrl + '?action=list_csv',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
          console.log('Risposta CSV:', response);
          const select = $('#csvSelect');
          select.empty();
          
          if (response.success && response.files && response.files.length > 0) {
            select.append('<option value="">-- Seleziona un file CSV --</option>');
            response.files.forEach(function(file) {
              select.append('<option value="' + file + '">' + file + '</option>');
            });
            console.log('CSV caricati:', response.files.length, response.files);
          } else {
            select.append('<option value="">Nessun file CSV trovato</option>');
            console.log('Nessun file CSV trovato. Debug:', response.debug);
            if (response.debug) {
              console.log('Directory:', response.debug.dir, 'Esiste:', response.debug.exists);
            }
          }
        },
        error: function(xhr, status, error) {
          console.error('Errore nel caricamento CSV:', error, xhr);
          const select = $('#csvSelect');
          select.empty();
          select.append('<option value="">Errore nel caricamento: ' + error + '</option>');
        }
      });
    }
    
    // Carica informazioni import
    function loadImportInfo() {
      if (!selectedCsvFile) {
        selectedCsvFile = $('#csvSelect').val();
      }
      if (!selectedCsvFile) {
        return;
      }
      
      // Aggiorna valori visibili
      $('#selectedCsvFile').text(selectedCsvFile);
      $('#selectedFormatId').text(idFormat);
      
      $('#loadingSpinner').show();
      
      // Recupera valori massimi
      $.ajax({
        url: 'https://yourradio.org/api/songs/maxids',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
          console.log('Risposta API maxids:', response);
          if (response.success && response.data) {
            $('#maxSgId').text(response.data.max_sg_id || 0);
            $('#maxSgFile').text(response.data.max_sg_file || 0);
            $('#nextSgId').text(response.data.next_sg_id || 1);
            $('#nextSgFile').text(response.data.next_sg_file || 1);
          } else {
            console.error('Risposta API non valida:', response);
            $('#maxSgId').text('Errore');
            $('#maxSgFile').text('Errore');
            $('#nextSgId').text('Errore');
            $('#nextSgFile').text('Errore');
          }
          
          // Conta file
          const currentUrl = window.location.href.split('?')[0];
          $.ajax({
            url: currentUrl + '?action=count_files',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
              console.log('Risposta count_files:', response);
              if (response.success) {
                $('#fileCount').text(response.count || 0);
                $('#filenewCount').text(response.filenew_count || 0);
              } else {
                $('#fileCount').text('Errore');
                $('#filenewCount').text('Errore');
              }
              $('#loadingSpinner').hide();
            },
            error: function(xhr, status, error) {
              console.error('Errore nel conteggio file:', error, xhr);
              $('#fileCount').text('Errore');
              $('#filenewCount').text('Errore');
              $('#loadingSpinner').hide();
            }
          });
        },
        error: function(xhr, status, error) {
          console.error('Errore nel recupero dei valori massimi:', error, xhr);
          $('#maxSgId').text('Errore');
          $('#maxSgFile').text('Errore');
          $('#nextSgId').text('Errore');
          $('#nextSgFile').text('Errore');
          $('#loadingSpinner').hide();
        }
      });
    }
    
    // Conferma esecuzione
    $('#executeBtn').on('click', function() {
      selectedCsvFile = $('#csvSelect').val();
      idFormat = parseInt($('#formatSelect').val());
      
      if (!selectedCsvFile) {
        alert('Seleziona un file CSV');
        return;
      }
      
      $('#confirmCsvFile').text(selectedCsvFile);
      $('#confirmFormatId').text(idFormat);
      $('#confirmModal').modal('show');
    });
    
    $('#confirmExecuteBtn').on('click', function() {
      $('#confirmModal').modal('hide');
      executeImport();
    });
    
    // Esegue import
    function executeImport() {
      $('#importForm').hide();
      $('#resultsPanel').hide();
      $('#loadingSpinner').show();
      
      const formData = new FormData();
      formData.append('csv_file', selectedCsvFile);
      formData.append('id_format', idFormat);
      
      $.ajax({
        url: '?action=execute_import',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
          $('#loadingSpinner').hide();
          
          if (response.success) {
            displayResults(response);
          } else {
            alert('Errore: ' + (response.message || 'Errore sconosciuto'));
          }
        },
        error: function(xhr, status, error) {
          $('#loadingSpinner').hide();
          alert('Errore nella richiesta: ' + error);
        }
      });
    }
    
    // Variabile globale per salvare i risultati
    let currentResults = null;
    
    // Esporta risultati in TXT
    $('#exportTxtBtn').on('click', function() {
      if (!currentResults) {
        alert('Nessun risultato da esportare');
        return;
      }
      
      let txtContent = '=== IMPORT SONGS WATERMELON ===\n\n';
      txtContent += 'Data: ' + new Date().toLocaleString('it-IT') + '\n';
      txtContent += 'File CSV: ' + selectedCsvFile + '\n';
      txtContent += 'Format ID: ' + idFormat + '\n\n';
      
      txtContent += '=== RIEPILOGO ===\n';
      txtContent += 'Righe processate: ' + currentResults.row_count + '\n';
      txtContent += 'Operazioni riuscite: ' + currentResults.success_count + '\n';
      if (currentResults.skipped_count > 0) {
        txtContent += 'Già processate (saltate): ' + currentResults.skipped_count + '\n';
      }
      if (currentResults.format_added_count > 0) {
        txtContent += 'Format aggiunti a song esistenti: ' + currentResults.format_added_count + '\n';
      }
      txtContent += 'Errori: ' + currentResults.error_count + '\n\n';
      
      txtContent += 'NOTA: Le query sono state eseguite sul database.\n\n';
      txtContent += '='.repeat(80) + '\n\n';
      
      currentResults.results.forEach(function(result) {
        txtContent += '--- RIGA ' + result.row + ' ---\n';
        
        if (result.status === 'error' && result.type === 'file_not_present') {
          txtContent += '**File not present**\n';
          txtContent += 'Filename CSV: ' + result.filename + '\n';
          txtContent += 'Track Title: ' + result.track_title + '\n';
          txtContent += 'Artist: ' + result.artist + '\n';
        } else if (result.status === 'skipped' && result.type === 'already_processed') {
          txtContent += '**Song già processata e abbinata al format**\n';
          txtContent += 'Filename CSV: ' + result.filename + '\n';
          txtContent += 'Track Title: ' + result.track_title + '\n';
          txtContent += 'Artist: ' + result.artist + '\n';
          txtContent += 'sg_id: ' + result.sg_id + '\n';
          txtContent += 'sg_file: ' + result.sg_file + '\n';
        } else if (result.status === 'format_added' && result.type === 'format_added_only') {
          txtContent += '**Song esistente, aggiunto solo abbinamento format**\n';
          txtContent += 'Filename CSV: ' + result.filename + '\n';
          txtContent += 'Track Title: ' + result.track_title + '\n';
          txtContent += 'Artist: ' + result.artist + '\n';
          txtContent += 'File Size: ' + result.filesize + ' bytes\n';
          txtContent += 'sg_id: ' + result.sg_id + '\n';
          txtContent += 'sg_file: ' + result.sg_file + '\n';
          txtContent += 'Query Song Format:\n' + result.query_song_format + '\n';
        } else if (result.status === 'success') {
          txtContent += 'Filename CSV: ' + result.filename + '\n';
          txtContent += 'Track Title: ' + result.track_title + '\n';
          txtContent += 'Artist: ' + result.artist + '\n';
          txtContent += 'File Size: ' + result.filesize + ' bytes\n';
          txtContent += 'sg_id: ' + result.sg_id + '\n';
          txtContent += 'sg_file: ' + result.sg_file + '\n';
          txtContent += 'Query Songs:\n' + result.query_songs + '\n';
          txtContent += 'Query Song Format:\n' + result.query_song_format + '\n';
          txtContent += 'File copiato: ' + result.filename + ' -> ' + result.new_filename + '\n';
        } else {
          txtContent += 'Errore: ' + (result.message || 'Errore sconosciuto') + '\n';
        }
        
        txtContent += '\n' + '-'.repeat(80) + '\n\n';
      });
      
      // Crea e scarica il file
      const blob = new Blob([txtContent], { type: 'text/plain;charset=utf-8' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'import_songs_' + new Date().toISOString().split('T')[0] + '_' + Date.now() + '.txt';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    });
    
    // Mostra risultati
    function displayResults(response) {
      currentResults = response; // Salva i risultati per l'esportazione
      const summary = $('#resultsSummary');
      summary.removeClass('alert-info alert-success alert-danger');
      
      if (response.error_count === 0) {
        summary.addClass('alert-success');
        summary.html('<h5 class="text-dark">Import completato con successo!</h5>');
      } else {
        summary.addClass('alert-warning');
        summary.html('<h5 class="text-dark">Import completato con alcuni errori</h5>');
      }
      
      summary.append('<p class="text-dark">Righe processate: <strong class="text-dark">' + response.row_count + '</strong></p>');
      summary.append('<p class="text-dark">Operazioni riuscite: <strong class="text-dark">' + response.success_count + '</strong></p>');
      if (response.skipped_count > 0) {
        summary.append('<p class="text-dark">Già processate (saltate): <strong class="text-dark">' + response.skipped_count + '</strong></p>');
      }
      if (response.format_added_count > 0) {
        summary.append('<p class="text-dark">Format aggiunti a song esistenti: <strong class="text-dark">' + response.format_added_count + '</strong></p>');
      }
      summary.append('<p class="text-dark">Errori: <strong class="text-dark">' + response.error_count + '</strong></p>');
      summary.append('<p class="text-dark mt-3"><small><strong class="text-dark">NOTA:</strong> Le query sono state eseguite sul database.</small></p>');
      
      // Aggiungi informazioni di debug se presenti
      if (response.debug) {
        const debugDiv = $('<div class="mt-3 p-3 bg-light border rounded"></div>');
        debugDiv.html('<h6 class="text-dark">🔍 Informazioni di Debug</h6>');
        debugDiv.append('<p class="text-dark"><strong>Cartella /filenew:</strong> ' + (response.debug.file_new_dir || 'N/A') + '</p>');
        debugDiv.append('<p class="text-dark"><strong>File trovati in /filenew (tutti):</strong> ' + (response.debug.all_files_count || 0) + '</p>');
        if (response.debug.all_files_found && response.debug.all_files_found.length > 0) {
          const filesList = $('<div class="mt-2"></div>');
          filesList.html('<strong class="text-dark">Lista completa file trovati:</strong>');
          const filesUl = $('<ul class="list-unstyled mt-2 mb-2" style="max-height: 200px; overflow-y: auto;"></ul>');
          response.debug.all_files_found.forEach(function(file) {
            filesUl.append('<li class="text-dark"><code>' + file + '</code></li>');
          });
          filesList.append(filesUl);
          debugDiv.append(filesList);
        }
        debugDiv.append('<p class="text-dark"><strong>File numerici trovati (sg_file):</strong> ' + response.debug.existing_files_count + '</p>');
        if (response.debug.existing_files_sample && response.debug.existing_files_sample.length > 0) {
          debugDiv.append('<p class="text-dark"><strong>Esempi di sg_file esistenti:</strong> ' + response.debug.existing_files_sample.join(', ') + '</p>');
        }
        if (response.debug.rows && response.debug.rows.length > 0) {
          debugDiv.append('<p class="text-dark"><strong>Dettagli per riga:</strong></p>');
          const debugTable = $('<table class="table table-sm table-bordered mt-2"></table>');
          debugTable.append('<thead><tr><th>Riga</th><th>Filename</th><th>sg_file</th><th>File Esiste</th><th>DB Connesso</th><th>Song Trovata</th><th>Format Esiste</th><th>Azione</th></tr></thead>');
          const tbody = $('<tbody></tbody>');
          response.debug.rows.forEach(function(row) {
            const tr = $('<tr></tr>');
            tr.append('<td>' + row.row + '</td>');
            tr.append('<td>' + (row.filename || '') + '</td>');
            tr.append('<td>' + (row.sg_file_to_check || row.final_sg_file || '') + '</td>');
            tr.append('<td>' + (row.file_exists ? '✅' : '❌') + '</td>');
            tr.append('<td>' + (row.db_connected ? '✅' : '❌') + '</td>');
            tr.append('<td>' + (row.song_found_in_db ? '✅' : '❌') + '</td>');
            tr.append('<td>' + (row.format_exists ? '✅' : '❌') + '</td>');
            tr.append('<td><small>' + (row.action || '') + '</small></td>');
            tbody.append(tr);
          });
          debugTable.append(tbody);
          debugDiv.append(debugTable);
        }
        summary.append(debugDiv);
      }
      
      const content = $('#resultsContent');
      content.empty();
      
      response.results.forEach(function(result) {
        const div = $('<div class="mb-3 p-3 border rounded"></div>');
        
        if (result.status === 'error' && result.type === 'file_not_present') {
          div.addClass('border-danger');
          div.html('<h6>--- RIGA ' + result.row + ' ---</h6>' +
                   '<p class="text-danger" style="margin: 0;"><strong>**File not present**</strong></p>' +
                   '<p style="margin: 0;">Filename CSV: ' + result.filename + '</p>' +
                   '<p style="margin: 0;">Track Title: ' + result.track_title + '</p>' +
                   '<p style="margin: 0;">Artist: ' + result.artist + '</p>');
        } else if (result.status === 'skipped' && result.type === 'already_processed') {
          div.addClass('border-info');
          div.html('<h6>--- RIGA ' + result.row + ' ---</h6>' +
                   '<p class="text-info" style="margin: 0;"><strong>Song già processata e abbinata al format</strong></p>' +
                   '<p style="margin: 0;">Filename CSV: ' + result.filename + '</p>' +
                   '<p style="margin: 0;">Track Title: ' + result.track_title + '</p>' +
                   '<p style="margin: 0;">Artist: ' + result.artist + '</p>' +
                   '<p style="margin: 0;">sg_id: ' + result.sg_id + '</p>' +
                   '<p style="margin: 0;">sg_file: ' + result.sg_file + '</p>');
        } else if (result.status === 'skipped' && result.type === 'file_exists_no_song_in_db') {
          div.addClass('border-warning');
          div.html('<h6>--- RIGA ' + result.row + ' ---</h6>' +
                   '<p class="text-warning" style="margin: 0;"><strong>File già presente ma song non trovata nel DB</strong></p>' +
                   '<p style="margin: 0;">Filename CSV: ' + result.filename + '</p>' +
                   '<p style="margin: 0;">Track Title: ' + result.track_title + '</p>' +
                   '<p style="margin: 0;">Artist: ' + result.artist + '</p>' +
                   '<p style="margin: 0;">sg_file: ' + result.sg_file + '</p>' +
                   '<p style="margin: 0;">Riga saltata (file non copiato, song non inserita)</p>');
        } else if (result.status === 'format_added' && result.type === 'format_added_only') {
          div.addClass('border-warning');
          div.html('<h6>--- RIGA ' + result.row + ' ---</h6>' +
                   '<p class="text-warning" style="margin: 0;"><strong>Song esistente, aggiunto solo abbinamento format</strong></p>' +
                   '<p style="margin: 0;">Filename CSV: ' + result.filename + '</p>' +
                   '<p style="margin: 0;">Track Title: ' + result.track_title + '</p>' +
                   '<p style="margin: 0;">Artist: ' + result.artist + '</p>' +
                   '<p style="margin: 0;">File Size: ' + result.filesize + ' bytes</p>' +
                   '<p style="margin: 0;">sg_id: ' + result.sg_id + '</p>' +
                   '<p style="margin: 0;">sg_file: ' + result.sg_file + '</p>' +
                   '<p style="margin: 0;"><strong>Query Song Format:</strong><br><code>' + result.query_song_format + '</code></p>');
        } else if (result.status === 'success') {
          div.addClass('border-success');
          div.html('<h6>--- RIGA ' + result.row + ' ---</h6>' +
                   '<p style="margin: 0;">Filename CSV: ' + result.filename + '</p>' +
                   '<p style="margin: 0;">Track Title: ' + result.track_title + '</p>' +
                   '<p style="margin: 0;">Artist: ' + result.artist + '</p>' +
                   '<p style="margin: 0;">File Size: ' + result.filesize + ' bytes</p>' +
                   '<p style="margin: 0;">sg_id: ' + result.sg_id + '</p>' +
                   '<p style="margin: 0;">sg_file: ' + result.sg_file + '</p>' +
                   '<p style="margin: 0;"><strong>Query Songs:</strong><br><code>' + result.query_songs + '</code></p>' +
                   '<p style="margin: 0;"><strong>Query Song Format:</strong><br><code>' + result.query_song_format + '</code></p>' +
                   '<p class="text-success" style="margin: 0;">File copiato: ' + result.filename + ' -> ' + result.new_filename + '</p>');
        } else if (result.status === 'error' && result.type === 'db_error') {
          div.addClass('border-danger');
          let errorHtml = '<h6>--- RIGA ' + result.row + ' ---</h6>' +
                   '<p class="text-danger" style="margin: 0;"><strong>Errore inserimento database</strong></p>' +
                   '<p style="margin: 0;">Filename CSV: ' + result.filename + '</p>' +
                   '<p style="margin: 0;">Track Title: ' + result.track_title + '</p>' +
                   '<p style="margin: 0;">Artist: ' + result.artist + '</p>';
          if (result.song_insert_error) {
            errorHtml += '<p style="margin: 0;" class="text-danger">Errore inserimento song: ' + result.song_insert_error + '</p>';
          }
          if (result.format_insert_error) {
            errorHtml += '<p style="margin: 0;" class="text-danger">Errore inserimento format: ' + result.format_insert_error + '</p>';
          }
          if (result.message) {
            errorHtml += '<p style="margin: 0;" class="text-danger">' + result.message + '</p>';
          }
          errorHtml += '<p style="margin: 0;">DB Connesso: ' + (result.db_connected ? 'Sì' : 'No') + '</p>';
          div.html(errorHtml);
        } else {
          div.addClass('border-warning');
          div.html('<h6>--- RIGA ' + result.row + ' ---</h6>' +
                   '<p class="text-warning" style="margin: 0;">Errore: ' + (result.message || 'Errore sconosciuto') + '</p>');
        }
        
        content.append(div);
      });
      
      $('#resultsPanel').show();
    }
  </script>
</body>
</html>
