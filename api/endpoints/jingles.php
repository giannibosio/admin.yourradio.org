<?php
/**
 * Endpoint per gestione Jingles
 * GET    /api/jingles              - Lista jingles (richiede gruppo_id)
 * GET    /api/jingles/{id}         - Dettaglio jingle
 * POST   /api/jingles              - Crea nuovo jingle
 * POST   /api/jingles/{id}/upload  - Carica file audio per un jingle
 * PUT    /api/jingles/{id}         - Aggiorna jingle
 */

function handleJinglesRequest($method, $action, $id, $data) {
    switch ($method) {
        case 'GET':
            if ($id === null && $action === '') {
                // Lista jingles per gruppo
                if (!isset($_GET['gruppo_id'])) {
                    sendErrorResponse("gruppo_id richiesto", 400);
                }
                $jingles = Jingles::selectAllJinglesByGruppoId($_GET['gruppo_id']);
                $result = [];
                foreach ($jingles as $j) {
                    $status = ($j['jingle_attivo'] == 0) ? "Non attivo" : "On-Air";
                    
                    if ($j['jingle_programmato'] == 1) {
                        $dal = unixTimeFromDate($j['jingle_dal'], 0);
                        $al = unixTimeFromDate($j['jingle_al'], 1);
                        $now = time();
                        $status = ($dal <= $now && $al >= $now) ? "Programmazione : dal ".$j['jingle_dal']." al ".$j['jingle_al'] : "Scaduto";
                        $status = ($dal >= $now && $al >= $now) ? "Programmato dal ".$j['jingle_dal'] : $status;
                    }
                    
                    $result[] = [
                        'id' => (int)$j['jingle_id'],
                        'nome' => strtoupper($j['jingle_nome']),
                        'attivo' => (int)$j['jingle_attivo'],
                        'programmato' => (int)$j['jingle_programmato'],
                        'dal' => isset($j['jingle_dal']) ? $j['jingle_dal'] : null,
                        'al' => isset($j['jingle_al']) ? $j['jingle_al'] : null,
                        'status' => $status
                    ];
                }
                sendSuccessResponse($result);
            } elseif ($id !== null && $action === '') {
                // Dettaglio jingle
                $jingle = Jingles::selectJingleById($id);
                if (empty($jingle)) {
                    sendErrorResponse("Jingle non trovato", 404);
                }
                $j = $jingle[0];
                sendSuccessResponse([
                    'jingle_id' => (int)$j['jingle_id'],
                    'jingle_nome' => $j['jingle_nome'],
                    'jingle_attivo' => (int)$j['jingle_attivo'],
                    'jingle_programmato' => isset($j['jingle_programmato']) ? (int)$j['jingle_programmato'] : 0,
                    'jingle_dal' => isset($j['jingle_dal']) ? $j['jingle_dal'] : null,
                    'jingle_al' => isset($j['jingle_al']) ? $j['jingle_al'] : null,
                    'jingle_gr_id' => isset($j['jingle_gr_id']) ? (int)$j['jingle_gr_id'] : null,
                    'jingle_file' => isset($j['jingle_file']) ? $j['jingle_file'] : '',
                    'gr_nome' => isset($j['gr_nome']) ? $j['gr_nome'] : ''
                ]);
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
            
        case 'POST':
            // Upload file jingle
            if ($id !== null && $action === 'upload') {
                // Carica file audio
                if (!isset($_FILES['file'])) {
                    sendErrorResponse("Nessun file ricevuto", 400);
                }
                
                if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    $errorMessages = array(
                        UPLOAD_ERR_INI_SIZE => 'Il file supera la dimensione massima consentita',
                        UPLOAD_ERR_FORM_SIZE => 'Il file supera la dimensione massima del form',
                        UPLOAD_ERR_PARTIAL => 'Il file è stato caricato solo parzialmente',
                        UPLOAD_ERR_NO_FILE => 'Nessun file è stato caricato',
                        UPLOAD_ERR_NO_TMP_DIR => 'Directory temporanea mancante',
                        UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file su disco',
                        UPLOAD_ERR_EXTENSION => 'Un\'estensione PHP ha bloccato il caricamento del file'
                    );
                    $errorMsg = isset($errorMessages[$_FILES['file']['error']]) 
                        ? $errorMessages[$_FILES['file']['error']] 
                        : 'Errore sconosciuto nel caricamento: ' . $_FILES['file']['error'];
                    sendErrorResponse($errorMsg, 400);
                }
                
                $file = $_FILES['file'];
                $filename = $file['name'];
                $tmpPath = $file['tmp_name'];
                
                // Verifica che il file temporaneo esista
                if (!file_exists($tmpPath)) {
                    sendErrorResponse("File temporaneo non trovato: " . $tmpPath, 400);
                }
                
                // Verifica che il jingle esista e recupera il nome del gruppo
                $jingle = Jingles::selectJingleById($id);
                if (empty($jingle)) {
                    sendErrorResponse("Jingle non trovato", 404);
                }
                $j = $jingle[0];
                $gruppoNome = isset($j['gr_nome']) ? $j['gr_nome'] : '';
                
                if (empty($gruppoNome)) {
                    sendErrorResponse("Nome gruppo non trovato per il jingle", 400);
                }
                
                // Genera un nome file basato sull'ID del jingle (formato 4 cifre)
                $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if ($fileExtension !== 'mp3') {
                    sendErrorResponse("Il file deve essere in formato MP3 (ricevuto: " . $fileExtension . ")", 400);
                }
                
                $jingleIdFormatted = str_pad($id, 4, '0', STR_PAD_LEFT);
                $newFilename = $jingleIdFormatted . '.mp3';
                
                // Percorso di destinazione: /player/[nome del gruppo minuscolo]/jingle/[nomefile]
                $gruppoNomeLower = strtolower(preg_replace('/\s+/', '', $gruppoNome));
                $remotePath = $_SERVER['DOCUMENT_ROOT'] . '/player/' . $gruppoNomeLower . '/jingle/' . $newFilename;
                $remoteDir = dirname($remotePath);
                
                // Crea la directory se non esiste
                if (!is_dir($remoteDir)) {
                    if (!mkdir($remoteDir, 0755, true)) {
                        sendErrorResponse("Impossibile creare la directory di destinazione: " . $remoteDir, 500);
                    }
                }
                
                // Verifica che la directory sia scrivibile
                if (!is_writable($remoteDir)) {
                    sendErrorResponse("La directory di destinazione non è scrivibile: " . $remoteDir, 500);
                }
                
                // Sposta il file caricato nella directory di destinazione
                if (!move_uploaded_file($tmpPath, $remotePath)) {
                    $lastError = error_get_last();
                    $errorMsg = "Errore nel salvataggio del file sul server";
                    if ($lastError) {
                        $errorMsg .= ": " . $lastError['message'];
                    }
                    sendErrorResponse($errorMsg . " (da: " . $tmpPath . " a: " . $remotePath . ")", 500);
                }
                
                // Verifica che il file sia stato spostato correttamente
                if (!file_exists($remotePath)) {
                    sendErrorResponse("Il file non è stato salvato correttamente: " . $remotePath, 500);
                }
                
                // Aggiorna il database con il nome del file
                try {
                    $updateQuery = "UPDATE `jingle` SET `jingle_file` = :filename WHERE `jingle_id` = :id";
                    $updateSt = DB::$db->prepare($updateQuery);
                    $result = $updateSt->execute(array(
                        ':filename' => $newFilename,
                        ':id' => $id
                    ));
                    
                    if (!$result) {
                        $errorInfo = $updateSt->errorInfo();
                        sendErrorResponse("Errore nell'aggiornamento del database: " . $errorInfo[2], 500);
                    }
                } catch (Exception $e) {
                    sendErrorResponse("Eccezione durante l'aggiornamento del database: " . $e->getMessage(), 500);
                }
                
                sendSuccessResponse(array(
                    'filename' => $newFilename,
                    'path' => '/player/' . $gruppoNomeLower . '/jingle/' . $newFilename,
                    'size' => $file['size']
                ), "File caricato con successo");
            } elseif ($id === null && $action === '') {
                // Crea nuovo jingle (codice esistente)
                try {
                    if (!isset($data['jingle_nome']) || empty($data['jingle_nome'])) {
                        sendErrorResponse("jingle_nome richiesto", 400);
                    }
                    
                    if (!isset($data['jingle_gr_id']) || empty($data['jingle_gr_id'])) {
                        sendErrorResponse("jingle_gr_id richiesto", 400);
                    }
                    
                    $query = "INSERT INTO `jingle` (`jingle_nome`, `jingle_attivo`, `jingle_gr_id`, `jingle_file`) 
                              VALUES (:jingle_nome, :jingle_attivo, :jingle_gr_id, :jingle_file)";
                    $st = DB::$db->prepare($query);
                    $st->execute([
                        ':jingle_nome' => $data['jingle_nome'],
                        ':jingle_attivo' => isset($data['jingle_attivo']) ? intval($data['jingle_attivo']) : 0,
                        ':jingle_gr_id' => intval($data['jingle_gr_id']),
                        ':jingle_file' => isset($data['jingle_file']) ? $data['jingle_file'] : ''
                    ]);
                    
                    $newJingleId = DB::$db->lastInsertId();
                    $jingle = Jingles::selectJingleById($newJingleId);
                    
                    if (empty($jingle)) {
                        sendErrorResponse("Errore nel recupero del jingle creato", 500);
                    }
                    
                    $j = $jingle[0];
                    sendSuccessResponse([
                        'jingle_id' => (int)$j['jingle_id'],
                        'jingle_nome' => $j['jingle_nome'],
                        'jingle_attivo' => (int)$j['jingle_attivo'],
                        'jingle_gr_id' => (int)$j['jingle_gr_id'],
                        'jingle_file' => isset($j['jingle_file']) ? $j['jingle_file'] : '',
                        'gr_nome' => isset($j['gr_nome']) ? $j['gr_nome'] : ''
                    ], "Jingle creato con successo");
                } catch (Exception $e) {
                    error_log("Errore nella creazione jingle: " . $e->getMessage());
                    sendErrorResponse("Errore nella creazione del jingle: " . $e->getMessage(), 500);
                }
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        case 'PUT':
            // Aggiorna jingle
            if ($id !== null && $action === '') {
                try {
                    $jingleId = intval($id);
                    
                    // Verifica che il jingle esista
                    $jingle = Jingles::selectJingleById($jingleId);
                    if (empty($jingle)) {
                        sendErrorResponse("Jingle non trovato", 404);
                    }
                    
                    $updateFields = [];
                    $params = [':jingle_id' => $jingleId];
                    
                    if (isset($data['jingle_nome'])) {
                        $updateFields[] = "`jingle_nome` = :jingle_nome";
                        $params[':jingle_nome'] = $data['jingle_nome'];
                    }
                    
                    if (isset($data['jingle_attivo'])) {
                        $updateFields[] = "`jingle_attivo` = :jingle_attivo";
                        $params[':jingle_attivo'] = intval($data['jingle_attivo']);
                    }
                    
                    if (isset($data['jingle_file'])) {
                        $updateFields[] = "`jingle_file` = :jingle_file";
                        $params[':jingle_file'] = $data['jingle_file'];
                    }
                    
                    if (empty($updateFields)) {
                        sendErrorResponse("Nessun campo da aggiornare", 400);
                    }
                    
                    $query = "UPDATE `jingle` SET " . implode(", ", $updateFields) . " WHERE `jingle_id` = :jingle_id";
                    $st = DB::$db->prepare($query);
                    $st->execute($params);
                    
                    // Recupera il jingle aggiornato
                    $jingle = Jingles::selectJingleById($jingleId);
                    $j = $jingle[0];
                    
                    sendSuccessResponse([
                        'jingle_id' => (int)$j['jingle_id'],
                        'jingle_nome' => $j['jingle_nome'],
                        'jingle_attivo' => (int)$j['jingle_attivo'],
                        'jingle_gr_id' => (int)$j['jingle_gr_id'],
                        'jingle_file' => isset($j['jingle_file']) ? $j['jingle_file'] : '',
                        'gr_nome' => isset($j['gr_nome']) ? $j['gr_nome'] : ''
                    ], "Jingle aggiornato con successo");
                } catch (Exception $e) {
                    error_log("Errore nell'aggiornamento jingle: " . $e->getMessage());
                    sendErrorResponse("Errore nell'aggiornamento del jingle: " . $e->getMessage(), 500);
                }
            } else {
                sendErrorResponse("ID jingle richiesto", 400);
            }
            break;
            
        default:
            sendErrorResponse("Method not allowed", 405);
    }
}

