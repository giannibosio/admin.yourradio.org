<?php
/**
 * Endpoint per gestione Songs
 * GET    /api/songs              - Lista tutte le songs (con filtri)
 * GET    /api/songs/{id}         - Dettaglio song
 * POST   /api/songs              - Crea nuova song
 * PUT    /api/songs/{id}         - Aggiorna song
 * DELETE /api/songs/{id}         - Elimina song
 * POST   /api/songs/{id}/upload  - Carica file audio per una song
 */

function handleSongsRequest($method, $action, $id, $data) {
    switch ($method) {
        case 'GET':
            if ($id === null && $action === 'maxids') {
                // Recupera i valori massimi di sg_id e sg_file
                try {
                    $queryMaxSgId = "SELECT sg_id FROM songs WHERE sg_id IS NOT NULL ORDER BY sg_id DESC LIMIT 1";
                    $queryMaxSgFile = "SELECT sg_file FROM songs WHERE sg_file IS NOT NULL ORDER BY sg_file DESC LIMIT 1";
                    
                    $st1 = Songs::$db->prepare($queryMaxSgId);
                    $st1->execute();
                    $result1 = $st1->fetch(PDO::FETCH_ASSOC);
                    
                    $st2 = Songs::$db->prepare($queryMaxSgFile);
                    $st2->execute();
                    $result2 = $st2->fetch(PDO::FETCH_ASSOC);
                    
                    $maxSgId = isset($result1['sg_id']) && $result1['sg_id'] !== null ? (int)$result1['sg_id'] : 0;
                    $maxSgFile = isset($result2['sg_file']) && $result2['sg_file'] !== null ? (int)$result2['sg_file'] : 0;
                    
                    sendSuccessResponse([
                        'max_sg_id' => $maxSgId,
                        'max_sg_file' => $maxSgFile,
                        'next_sg_id' => $maxSgId + 1,
                        'next_sg_file' => $maxSgFile + 1
                    ]);
                } catch (Exception $e) {
                    sendErrorResponse("Errore nel recupero dei valori massimi: " . $e->getMessage(), 500);
                }
            } elseif ($id === null && $action === 'byfilename') {
                // Recupera song per sg_filename_wm
                if (!isset($_GET['filename'])) {
                    sendErrorResponse("Parametro filename richiesto", 400);
                }
                try {
                    $filename = $_GET['filename'];
                    $query = "SELECT * FROM songs WHERE sg_filename_wm = :filename LIMIT 1";
                    $st = Songs::$db->prepare($query);
                    $st->execute([':filename' => $filename]);
                    $song = $st->fetch(PDO::FETCH_ASSOC);
                    
                    if ($song) {
                        sendSuccessResponse([
                            'sg_id' => (int)$song['sg_id'],
                            'sg_file' => isset($song['sg_file']) ? (int)$song['sg_file'] : null,
                            'sg_filename_wm' => $song['sg_filename_wm'],
                            'exists' => true,
                            'song' => $song
                        ]);
                    } else {
                        sendSuccessResponse([
                            'sg_id' => null,
                            'sg_file' => null,
                            'sg_filename_wm' => $filename,
                            'exists' => false
                        ]);
                    }
                } catch (Exception $e) {
                    sendErrorResponse("Errore nel recupero della song: " . $e->getMessage(), 500);
                }
            } elseif ($id !== null && $action === 'format') {
                // Verifica se esiste song_format per id_song e id_format
                if (!isset($_GET['id_format'])) {
                    sendErrorResponse("Parametro id_format richiesto", 400);
                }
                try {
                    $idSong = (int)$id;
                    $idFormat = (int)$_GET['id_format'];
                    $query = "SELECT id_song FROM song_format WHERE id_song = :id_song AND id_format = :id_format LIMIT 1";
                    $st = Songs::$db->prepare($query);
                    $st->execute([
                        ':id_song' => $idSong,
                        ':id_format' => $idFormat
                    ]);
                    $format = $st->fetch(PDO::FETCH_ASSOC);
                    
                    sendSuccessResponse([
                        'id_song' => $idSong,
                        'id_format' => $idFormat,
                        'exists' => !empty($format)
                    ]);
                } catch (Exception $e) {
                    sendErrorResponse("Errore nel recupero del format: " . $e->getMessage(), 500);
                }
            } elseif ($id === null && $action === '') {
                // Lista songs con filtri
                $filter = [];
                
                // Applica filtri dalla query string
                if (isset($_GET['attivo'])) $filter['attivo'] = (int)$_GET['attivo'];
                // Format può essere un singolo valore o una lista separata da virgole
                if (isset($_GET['format']) && $_GET['format'] !== '') {
                    $formatValue = $_GET['format'];
                    // Rimuovi spazi
                    $formatValue = trim($formatValue);
                    // Debug: verifica se contiene virgole
                    if (strpos($formatValue, ',') !== false) {
                        // Lista di format separati da virgola
                        $formatArray = explode(',', $formatValue);
                        $formatIds = array();
                        foreach ($formatArray as $f) {
                            $f = trim($f);
                            if ($f !== '' && $f !== '0') {
                                $fid = (int)$f;
                                if ($fid > 0) {
                                    $formatIds[] = $fid;
                                }
                            }
                        }
                        if (count($formatIds) > 0) {
                            $filter['formats'] = $formatIds;
                        }
                    } else {
                        // Singolo format
                        $formatInt = (int)$formatValue;
                        if ($formatInt > 0) {
                            $filter['format'] = $formatInt;
                        }
                    }
                }
                if (isset($_GET['nazionalita'])) $filter['nazionalita'] = (int)$_GET['nazionalita'];
                if (isset($_GET['strategia'])) $filter['strategia'] = (int)$_GET['strategia'];
                if (isset($_GET['sex'])) $filter['sex'] = sanitizeInput($_GET['sex']);
                if (isset($_GET['umore'])) $filter['umore'] = sanitizeInput($_GET['umore']);
                if (isset($_GET['ritmo'])) $filter['ritmo'] = (int)$_GET['ritmo'];
                if (isset($_GET['energia'])) $filter['energia'] = (int)$_GET['energia'];
                if (isset($_GET['anno'])) $filter['anno'] = (int)$_GET['anno'];
                if (isset($_GET['periodo'])) $filter['periodo'] = (int)$_GET['periodo'];
                if (isset($_GET['genere'])) $filter['genere'] = (int)$_GET['genere'];
                // Gestisci diritti: aggiungi al filtro solo se è un valore numerico valido (non "*" o vuoto)
                // Nota: il valore 0 (Siae) è valido e deve essere incluso nel filtro
                if (isset($_GET['diritti']) && $_GET['diritti'] !== '*' && $_GET['diritti'] !== '' && $_GET['diritti'] !== null) {
                    $dirittiVal = trim($_GET['diritti']);
                    if ($dirittiVal !== '*' && $dirittiVal !== '') {
                        // Converti a intero (0, 1, 3 sono valori validi)
                        $dirittiInt = (int)$dirittiVal;
                        // Verifica che sia un numero valido (0, 1, o 3)
                        if ($dirittiInt === 0 || $dirittiInt === 1 || $dirittiInt === 3) {
                            $filter['diritti'] = $dirittiInt;
                        }
                    }
                }
                // Se diritti non è impostato o è "*", non aggiungere al filtro (mostra tutti)
                
                $songs = Songs::selectAll($filter);
                $result = [];
                foreach ($songs as $s) {
                    if ($s['sg_artista'] != '') {
                        $result[] = [
                            'id' => (int)$s['sg_id'],
                            'artista' => $s['sg_artista'],
                            'titolo' => $s['sg_titolo'],
                            'anno' => $s['sg_anno'] ? (int)$s['sg_anno'] : null,
                            'attivo' => (int)$s['sg_attivo']
                        ];
                    }
                }
                sendSuccessResponse($result);
            } elseif ($id !== null && $action === '') {
                // Dettaglio song
                $song = Songs::selectSongById($id);
                if (empty($song)) {
                    sendErrorResponse("Song non trovata", 404);
                }
                sendSuccessResponse($song[0]);
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        case 'POST':
            if ($id === null && $action === '') {
                // Crea nuova song
                $newId = Songs::createSong($data);
                if ($newId) {
                    $song = Songs::selectSongById($newId);
                    sendSuccessResponse($song[0], "Song creata con successo", 201);
                } else {
                    sendErrorResponse("Errore nella creazione della song", 500);
                }
            } elseif ($id !== null && $action === 'format') {
                // Aggiungi song_format
                if (!isset($data['id_format'])) {
                    sendErrorResponse("Parametro id_format richiesto", 400);
                }
                try {
                    $idSong = (int)$id;
                    $idFormat = (int)$data['id_format'];
                    
                    // Verifica se esiste già
                    $checkQuery = "SELECT id_song FROM song_format WHERE id_song = :id_song AND id_format = :id_format LIMIT 1";
                    $checkSt = Songs::$db->prepare($checkQuery);
                    $checkSt->execute([
                        ':id_song' => $idSong,
                        ':id_format' => $idFormat
                    ]);
                    $existing = $checkSt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing) {
                        sendSuccessResponse([
                            'id_song' => $idSong,
                            'id_format' => $idFormat,
                            'already_exists' => true
                        ], "Abbinamento già esistente");
                    } else {
                        // Inserisci
                        $insertQuery = "INSERT INTO `song_format` (`id_song`, `id_format`) VALUES (:id_song, :id_format)";
                        $insertSt = Songs::$db->prepare($insertQuery);
                        $insertSt->execute([
                            ':id_song' => $idSong,
                            ':id_format' => $idFormat
                        ]);
                        
                        sendSuccessResponse([
                            'id_song' => $idSong,
                            'id_format' => $idFormat,
                            'already_exists' => false
                        ], "Abbinamento creato con successo", 201);
                    }
                } catch (Exception $e) {
                    sendErrorResponse("Errore nell'inserimento del format: " . $e->getMessage(), 500);
                }
            } elseif ($id !== null && $action === 'upload') {
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
                
                // Genera un nome file basato sull'ID della song
                $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if ($fileExtension !== 'mp3') {
                    sendErrorResponse("Il file deve essere in formato MP3 (ricevuto: " . $fileExtension . ")", 400);
                }
                
                $newFilename = $id . '.mp3';
                
                // Percorso di destinazione sul server remoto
                // Il percorso assoluto sul server esterno: /player/song/ (non /player/song/test/)
                $remotePath = $_SERVER['DOCUMENT_ROOT'] . '/player/song/' . $newFilename;
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
                    // Prima verifica se esiste già una song (diversa da questa) con lo stesso sg_file
                    $checkQuery = "SELECT `sg_id` FROM `songs` WHERE `sg_file` = :filename AND `sg_id` != :id";
                    $checkSt = Songs::$db->prepare($checkQuery);
                    $checkSt->execute(array(
                        ':filename' => $id,
                        ':id' => $id
                    ));
                    $existingSong = $checkSt->fetch();
                    
                    // Se esiste già una song con questo sg_file, imposta il suo sg_file a NULL
                    if ($existingSong) {
                        $clearQuery = "UPDATE `songs` SET `sg_file` = NULL, `sg_filesize` = 0 WHERE `sg_id` = :other_id";
                        $clearSt = Songs::$db->prepare($clearQuery);
                        $clearSt->execute(array(':other_id' => $existingSong['sg_id']));
                    }
                    
                    // Ora aggiorna questa song con il nuovo sg_file
                    $updateQuery = "UPDATE `songs` SET `sg_file` = :filename, `sg_filesize` = :filesize WHERE `sg_id` = :id";
                    $updateSt = Songs::$db->prepare($updateQuery);
                    $result = $updateSt->execute(array(
                        ':filename' => $id, // Salva solo l'ID, l'estensione è .mp3
                        ':filesize' => $file['size'],
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
                    'path' => '/player/song/' . $newFilename,
                    'size' => $file['size']
                ), "File caricato con successo");
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        case 'PUT':
        case 'PATCH':
            // Aggiorna song
            if ($id === null) {
                sendErrorResponse("ID song richiesto", 400);
            }
            $data['sg_id'] = $id;
            $result = Songs::updateSongById($data);
            if ($result) {
                sendSuccessResponse(['id' => $id], "Song aggiornata con successo");
            } else {
                sendErrorResponse("Errore nell'aggiornamento della song", 500);
            }
            break;
            
        case 'DELETE':
            // Elimina song
            if ($id === null) {
                sendErrorResponse("ID song richiesto", 400);
            }
            
            error_log("[API DELETE SONG] Richiesta cancellazione song ID: " . $id);
            
            $result = Songs::deleteById($id);
            if ($result) {
                error_log("[API DELETE SONG] Song ID " . $id . " eliminata con successo");
                sendSuccessResponse(['id' => $id], "Song eliminata con successo");
            } else {
                error_log("[API DELETE SONG] ERRORE durante l'eliminazione della song ID: " . $id);
                sendErrorResponse("Errore nell'eliminazione della song", 500);
            }
            break;
            
        default:
            sendErrorResponse("Method not allowed", 405);
    }
}

