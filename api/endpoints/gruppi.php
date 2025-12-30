<?php
/**
 * Endpoint per gestione Gruppi
 * GET    /api/gruppi              - Lista tutti i gruppi
 * GET    /api/gruppi/{id}         - Dettaglio gruppo
 * GET    /api/gruppi/{id}/players - Lista players del gruppo
 * GET    /api/gruppi/{id}/subgruppi - Lista sottogruppi
 * POST   /api/gruppi              - Crea nuovo gruppo
 * POST   /api/gruppi/{id}/logo    - Carica logo del gruppo
 * PUT    /api/gruppi/{id}         - Aggiorna gruppo
 * DELETE /api/gruppi/{id}         - Elimina gruppo
 */

function handleGruppiRequest($method, $action, $id, $data) {
    switch ($method) {
        case 'GET':
            if ($id === null && $action === '') {
                // Lista tutti i gruppi
                $gruppi = Gruppi::selectAll();
                $result = [];
                foreach ($gruppi as $g) {
                    $result[] = [
                        'id' => (int)$g['gr_id'],
                        'nome' => strtoupper($g['gr_nome']),
                        'players' => (int)$g['tot_player'],
                        'attivo' => (int)$g['gr_active']
                    ];
                }
                sendSuccessResponse($result);
            } elseif ($id !== null && $action === '') {
                // Dettaglio gruppo
                $gruppo = Gruppi::selectGruppoById($id);
                if (empty($gruppo)) {
                    sendErrorResponse("Gruppo non trovato", 404);
                }
                $g = $gruppo[0];
                sendSuccessResponse([
                    'id' => (int)$g['gr_id'],
                    'nome' => strtoupper($g['gr_nome']),
                    'attivo' => (int)$g['gr_active'],
                    'data_creazione' => $g['gr_dataCreazione'],
                    'rss_id' => $g['rss_id'] ? (int)$g['rss_id'] : null,
                    'rss_nome' => isset($g['rss_nome']) ? $g['rss_nome'] : null,
                    'tot_player' => (int)$g['tot_player']
                ]);
            } elseif ($id !== null && $action === 'players') {
                // Lista players del gruppo
                $players = Gruppi::selectAllPlayersGruppoById($id);
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
            } elseif ($id !== null && $action === 'subgruppi') {
                // Lista sottogruppi
                $subgruppi = Gruppi::selectSubGruppoById($id);
                $result = [];
                foreach ($subgruppi as $sg) {
                    $totPlayers = Gruppi::selectTotPlayersSottoGruppoById($sg['sgr_id']);
                    $result[] = [
                        'id' => (int)$sg['sgr_id'],
                        'nome' => strtoupper($sg['sgr_nome']),
                        'tot_player' => (int)$totPlayers[0]['tot_player']
                    ];
                }
                sendSuccessResponse($result);
            } elseif ($id !== null && $action === 'campagne') {
                // Lista campagne del gruppo
                $campagne = Gruppi::selectCampagneByIdGroup($id);
                sendSuccessResponse($campagne);
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        case 'POST':
            if ($id !== null && $action === 'logo') {
                // Carica logo del gruppo
                if (!isset($_FILES['logo_file'])) {
                    sendErrorResponse("Nessun file ricevuto", 400);
                }
                
                if ($_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
                    $errorMessages = array(
                        UPLOAD_ERR_INI_SIZE => 'Il file supera la dimensione massima consentita',
                        UPLOAD_ERR_FORM_SIZE => 'Il file supera la dimensione massima del form',
                        UPLOAD_ERR_PARTIAL => 'Il file è stato caricato solo parzialmente',
                        UPLOAD_ERR_NO_FILE => 'Nessun file è stato caricato',
                        UPLOAD_ERR_NO_TMP_DIR => 'Directory temporanea mancante',
                        UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file su disco',
                        UPLOAD_ERR_EXTENSION => 'Un\'estensione PHP ha bloccato il caricamento del file'
                    );
                    $errorMsg = isset($errorMessages[$_FILES['logo_file']['error']]) 
                        ? $errorMessages[$_FILES['logo_file']['error']] 
                        : 'Errore sconosciuto nel caricamento: ' . $_FILES['logo_file']['error'];
                    sendErrorResponse($errorMsg, 400);
                }
                
                // Recupera i dati del gruppo per ottenere il nome
                $gruppo = Gruppi::selectGruppoById($id);
                if (empty($gruppo)) {
                    sendErrorResponse("Gruppo non trovato", 404);
                }
                $gruppoNome = strtolower(trim($gruppo[0]['gr_nome']));
                
                $file = $_FILES['logo_file'];
                $filename = $file['name'];
                $tmpPath = $file['tmp_name'];
                
                // Verifica che il file temporaneo esista
                if (!file_exists($tmpPath)) {
                    sendErrorResponse("File temporaneo non trovato: " . $tmpPath, 400);
                }
                
                // Verifica il tipo di file
                $allowedTypes = array('image/png', 'image/jpeg', 'image/jpg', 'image/gif');
                $fileType = $file['type'];
                if (!in_array($fileType, $allowedTypes)) {
                    sendErrorResponse("Tipo di file non supportato. Usa PNG, JPG o GIF.", 400);
                }
                
                // Percorsi di destinazione sul server remoto
                $basePath = "/var/www/vhosts/yourradio.org/httpdocs/player/" . $gruppoNome;
                $logoPath = $basePath . "/images/logo_gruppo.png";
                $logoThumbPath = $basePath . "/images/thumbnail/logo_gruppo.png";
                
                // Verifica che le cartelle esistano
                if (!is_dir($basePath . "/images")) {
                    if (!mkdir($basePath . "/images", 0755, true)) {
                        sendErrorResponse("Impossibile creare la directory images", 500);
                    }
                }
                if (!is_dir($basePath . "/images/thumbnail")) {
                    if (!mkdir($basePath . "/images/thumbnail", 0755, true)) {
                        sendErrorResponse("Impossibile creare la directory thumbnail", 500);
                    }
                }
                
                // Sposta il file caricato nella directory images
                if (!move_uploaded_file($tmpPath, $logoPath)) {
                    $lastError = error_get_last();
                    $errorMsg = "Errore nel salvataggio del file sul server";
                    if ($lastError) {
                        $errorMsg .= ": " . $lastError['message'];
                    }
                    sendErrorResponse($errorMsg, 500);
                }
                
                // Copia il file anche nella cartella thumbnail
                if (!copy($logoPath, $logoThumbPath)) {
                    error_log("Avviso: Impossibile copiare logo in thumbnail: " . $logoThumbPath);
                    // Non blocchiamo se la copia fallisce, il file principale è stato salvato
                }
                
                sendSuccessResponse(['message' => 'Logo caricato con successo'], "Logo caricato con successo");
            } elseif ($id === null && $action === '') {
                // Crea nuovo gruppo
                validateRequired($data, ['nome']);
                $nome = strtoupper(trim(sanitizeInput($data['nome'])));
                $newId = Gruppi::createGruppo($nome);
                
                // Crea la struttura di cartelle sul server esterno
                if (!createGroupFolderStructure($nome)) {
                    error_log("Errore nella creazione della struttura cartelle per gruppo '{$nome}' (ID: {$newId})");
                    // Non blocchiamo la creazione del gruppo se la cartella fallisce, ma logghiamo l'errore
                }
                
                sendSuccessResponse(['id' => $newId], "Gruppo creato con successo");
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        case 'PUT':
        case 'PATCH':
            // Aggiorna gruppo
            if ($id === null) {
                sendErrorResponse("ID gruppo richiesto", 400);
            }
            validateRequired($data, ['nome', 'active']);
            $gruppoData = [
                'groupId' => $id,
                'nome' => sanitizeInput($data['nome']),
                'active' => (int)$data['active'],
                'rss_id' => isset($data['rss_id']) ? (int)$data['rss_id'] : null
            ];
            Gruppi::updateGruppo($gruppoData);
            sendSuccessResponse(['id' => $id], "Gruppo aggiornato con successo");
            break;
            
        case 'DELETE':
            // Elimina gruppo
            if ($id === null) {
                sendErrorResponse("ID gruppo richiesto", 400);
            }
            
            // Recupera i dati del gruppo per ottenere il nome
            $gruppo = Gruppi::selectGruppoById($id);
            if (empty($gruppo)) {
                sendErrorResponse("Gruppo non trovato", 404);
            }
            $gruppoNome = strtolower(trim($gruppo[0]['gr_nome']));
            
            // Cancella il gruppo dal database (cancella anche sottogruppi, players, ecc.)
            if (!Gruppi::deleteGruppoById($id)) {
                sendErrorResponse("Errore nella cancellazione del gruppo dal database", 500);
            }
            
            // Cancella la cartella del gruppo sul server
            $basePath = "/var/www/vhosts/yourradio.org/httpdocs/player/" . $gruppoNome;
            if (is_dir($basePath)) {
                if (!deleteDirectory($basePath)) {
                    error_log("Avviso: Impossibile cancellare completamente la directory: {$basePath}");
                    // Non blocchiamo se la cartella non viene cancellata, il gruppo è già stato eliminato dal DB
                }
            }
            
            sendSuccessResponse(['id' => $id], "Gruppo eliminato con successo");
            break;
            
        default:
            sendErrorResponse("Method not allowed", 405);
    }
}

