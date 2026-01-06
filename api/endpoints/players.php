<?php
/**
 * Endpoint per gestione Players
 * GET    /api/players              - Lista tutti i players
 * GET    /api/players/{id}         - Dettaglio player
 * PUT    /api/players/{id}         - Aggiorna player
 * POST   /api/players/{id}         - Aggiorna player
 */

function handlePlayersRequest($method, $action, $id, $data) {
    switch ($method) {
        case 'GET':
            if ($id === null && $action === '') {
                // Lista tutti i players (richiede gruppo_id come parametro)
                if (!isset($_GET['gruppo_id'])) {
                    sendErrorResponse("gruppo_id richiesto", 400);
                }
                $players = Gruppi::selectAllPlayersGruppoById($_GET['gruppo_id']);
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
            } elseif ($id !== null && $action === '') {
                // Dettaglio player
                $player = Player::selectPlayerByID($id);
                if (empty($player)) {
                    sendErrorResponse("Player non trovato", 404);
                }
                sendSuccessResponse($player[0]);
            } elseif ($id !== null && $action === 'subgruppi') {
                // Lista subgruppi del player con stato checked
                $player = Player::selectPlayerByID($id);
                if (empty($player)) {
                    sendErrorResponse("Player non trovato", 404);
                }
                $subgruppi = Gruppi::selectSubGruppoByIdPlayer($id);
                $result = [];
                foreach ($subgruppi as $sg) {
                    $getCheck = Gruppi::getCheckRelatedSubGruppoByIdPlayer($id, $sg['sgr_id']);
                    $checked = (!empty($getCheck) && isset($getCheck[0]['checked']) && $getCheck[0]['checked'] == 1) ? 1 : 0;
                    $result[] = [
                        'sgr_id' => (int)$sg['sgr_id'],
                        'sgr_nome' => $sg['sgr_nome'],
                        'checked' => $checked
                    ];
                }
                sendSuccessResponse($result);
            } elseif ($id !== null && $action === 'password') {
                // Cambia password del player (in chiaro)
                if (!isset($data['newpass']) || empty($data['newpass'])) {
                    sendErrorResponse("Password richiesta", 400);
                }
                $playerId = intval($id);
                $newPassword = $data['newpass']; // Password in chiaro (pl_keyword)
                // Calcola pl_keyword_md5 = MD5(pl_keyword + "_" + pl_id)
                $plKeywordMd5 = md5($newPassword . "_" . $playerId);
                
                $query = "UPDATE `players` SET `pl_keyword` = :pl_keyword, `pl_keyword_md5` = :pl_keyword_md5 WHERE `pl_id` = :pl_id";
                $st = DB::$db->prepare($query);
                $result = $st->execute([
                    ':pl_keyword' => $newPassword,
                    ':pl_keyword_md5' => $plKeywordMd5,
                    ':pl_id' => $playerId
                ]);
                
                if (!$result) {
                    sendErrorResponse("Errore nell'aggiornamento della password", 500);
                }
                
                // Recupera il player aggiornato
                $player = Player::selectPlayerByID($playerId);
                if (empty($player)) {
                    sendErrorResponse("Player non trovato", 404);
                }
                sendSuccessResponse(['pl_keyword_md5' => $plKeywordMd5, 'pl_keyword' => $newPassword], "Password cambiata con successo");
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        case 'POST':
            if ($id === null && $action === '') {
                // Crea nuovo player
                try {
                    error_log("CREATE PLAYER: Data ricevuta: " . json_encode($data));
                    
                    // Verifica campi obbligatori
                    if (!isset($data['pl_nome']) || empty($data['pl_nome'])) {
                        sendErrorResponse("Nome player richiesto", 400);
                    }
                    if (!isset($data['pl_keyword_md5']) || empty($data['pl_keyword_md5'])) {
                        sendErrorResponse("Password richiesta", 400);
                    }
                    
                    // Prepara i campi per l'inserimento
                    $insertFields = [];
                    $insertValues = [];
                    $params = [];
                    
                    // Campi obbligatori e opzionali
                    $fieldMapping = [
                        'pl_active' => 'pl_active',
                        'pl_nome' => 'pl_nome',
                        'pl_idGruppo' => 'pl_idGruppo',
                        'pl_riferimento' => 'pl_riferimento',
                        'pl_mail' => 'pl_mail',
                        'tel' => 'pl_telefono',
                        'pl_indirizzo' => 'pl_indirizzo',
                        'pl_citta' => 'pl_citta',
                        'pl_pro' => 'pl_pro',
                        'pl_cap' => 'pl_cap',
                        'pl_note' => 'pl_note',
                        'pl_keyword' => 'pl_keyword',
                        'pl_dataCreazione' => 'pl_dataCreazione'
                    ];
                    
                    // pl_keyword_md5 non viene salvato direttamente, ma calcolato dopo
                    $plKeywordForInsert = null;
                    foreach($fieldMapping as $formField => $dbField) {
                        if(isset($data[$formField])) {
                            // Salva pl_keyword temporaneamente per calcolare pl_keyword_md5 dopo
                            if($formField === 'pl_keyword') {
                                $plKeywordForInsert = $data[$formField];
                            }
                            $insertFields[] = "`".$dbField."`";
                            $insertValues[] = ":".$dbField;
                            $params[':'.$dbField] = $data[$formField];
                        }
                    }
                    
                    // Se pl_keyword_md5 viene passato ma pl_keyword no, usa pl_keyword_md5 come pl_keyword
                    if($plKeywordForInsert === null && isset($data['pl_keyword_md5']) && !empty($data['pl_keyword_md5'])) {
                        $plKeywordForInsert = $data['pl_keyword_md5'];
                        $insertFields[] = "`pl_keyword`";
                        $insertValues[] = ":pl_keyword";
                        $params[':pl_keyword'] = $plKeywordForInsert;
                    }
                    
                    // Valori di default per campi non specificati
                    if(!isset($data['pl_active'])) {
                        $insertFields[] = "`pl_active`";
                        $insertValues[] = ":pl_active";
                        $params[':pl_active'] = 0;
                    }
                    if(!isset($data['pl_idGruppo'])) {
                        $insertFields[] = "`pl_idGruppo`";
                        $insertValues[] = ":pl_idGruppo";
                        $params[':pl_idGruppo'] = 0;
                    }
                    
                    if(empty($insertFields)) {
                        sendErrorResponse("Nessun campo da inserire", 400);
                    }
                    
                    $query = "INSERT INTO `players` (" . implode(", ", $insertFields) . ") VALUES (" . implode(", ", $insertValues) . ")";
                    error_log("CREATE PLAYER QUERY: " . $query);
                    error_log("CREATE PLAYER PARAMS: " . json_encode($params));
                    
                    $st = DB::$db->prepare($query);
                    $result = $st->execute($params);
                    
                    if (!$result) {
                        $errorInfo = $st->errorInfo();
                        error_log("CREATE PLAYER ERROR: " . json_encode($errorInfo));
                        $errorMsg = isset($errorInfo[2]) ? $errorInfo[2] : 'Errore sconosciuto';
                        sendErrorResponse("Errore nella creazione del player: " . $errorMsg, 500);
                    }
                    
                    $newPlayerId = DB::$db->lastInsertId();
                    error_log("CREATE PLAYER SUCCESS: Nuovo player creato con ID " . $newPlayerId);
                    
                    // Calcola pl_keyword_md5 = MD5(pl_keyword + "_" + pl_id) se pl_keyword è presente
                    if($plKeywordForInsert !== null && !empty($plKeywordForInsert)) {
                        // Calcola pl_keyword_md5 come MD5(pl_keyword + "_" + pl_id)
                        $plKeywordMd5 = md5($plKeywordForInsert . "_" . $newPlayerId);
                        $updateKeywordQuery = "UPDATE `players` SET `pl_keyword_md5` = :pl_keyword_md5 WHERE `pl_id` = :pl_id";
                        $stKeyword = DB::$db->prepare($updateKeywordQuery);
                        $stKeyword->execute([
                            ':pl_keyword_md5' => $plKeywordMd5,
                            ':pl_id' => $newPlayerId
                        ]);
                        error_log("CREATE PLAYER: pl_keyword salvato: " . $plKeywordForInsert . ", pl_keyword_md5 calcolato: " . $plKeywordMd5);
                    }
                    
                    // Recupera il player appena creato
                    $querySelect = "SELECT p.*, g.* FROM players p LEFT JOIN gruppi g ON(p.pl_idGruppo=g.gr_id) WHERE p.pl_id=:id";
                    $stSelect = DB::$db->prepare($querySelect);
                    $stSelect->execute([':id' => $newPlayerId]);
                    $player = $stSelect->fetchAll();
                    
                    if (empty($player)) {
                        error_log("CREATE PLAYER ERROR: Player non trovato dopo INSERT - ID: " . $newPlayerId);
                        sendErrorResponse("Player non trovato dopo la creazione", 404);
                    }
                    sendSuccessResponse($player[0], "Player creato con successo");
                } catch (Exception $e) {
                    error_log("Errore nella creazione player: " . $e->getMessage());
                    sendErrorResponse("Errore nella creazione del player: " . $e->getMessage(), 500);
                }
            } elseif ($id !== null && $action === '') {
                // Aggiorna player esistente (POST come alternativa a PUT)
                // Esegui la stessa logica del PUT
                try {
                    $playerId = intval($id);
                    error_log("UPDATE PLAYER (via POST): ID=" . $playerId . " | Data ricevuta: " . json_encode($data));
                    $updateFields = [];
                    $params = [':pl_id' => $playerId];

                    // Mappa i campi del form ai campi del database
                    $fieldMapping = [
                        'pl_active' => 'pl_active',
                        'pl_nome' => 'pl_nome',
                        'pl_idGruppo' => 'pl_idGruppo',
                        'pl_riferimento' => 'pl_riferimento',
                        'pl_mail' => 'pl_mail',
                        'tel' => 'pl_telefono',
                        'pl_indirizzo' => 'pl_indirizzo',
                        'pl_citta' => 'pl_citta',
                        'pl_pro' => 'pl_pro',
                        'pl_cap' => 'pl_cap',
                        'pl_note' => 'pl_note',
                        'pl_time' => 'pl_time',
                        'pl_player_freeaccess' => 'pl_player_freeaccess',
                        'pl_monitor' => 'pl_monitor',
                        'pl_test' => 'pl_test',
                        'pl_sendmail' => 'pl_sendmail',
                        'pl_client_edit_selector' => 'pl_client_edit_selector',
                        'pl_client_edit_spot' => 'pl_client_edit_selector',
                        'pl_client_edit_rubriche' => 'pl_client_edit_rubriche',
                        'pl_client_ora_on_ora' => 'pl_client_ora_on_ora',
                        'pl_client_ora_on_min' => 'pl_client_ora_on_min',
                        'pl_oraOnCalcolata' => 'pl_oraOnCalcolata',
                        'pl_client_ora_off_ora' => 'pl_client_ora_off_ora',
                        'pl_client_ora_off_min' => 'pl_client_ora_off_min',
                        'pl_oraOffCalcolata' => 'pl_oraOffCalcolata',
                        'pl_keyword' => 'pl_keyword',
                        'pl_dataCreazione' => 'pl_dataCreazione'
                    ];
                    
                    // Se pl_keyword viene modificato, calcola pl_keyword_md5 = MD5(pl_keyword + "_" + pl_id)
                    $plKeywordToUpdate = null;
                    if(isset($data['pl_keyword']) && !empty($data['pl_keyword'])) {
                        $plKeywordToUpdate = $data['pl_keyword'];
                    } elseif(isset($data['pl_keyword_md5']) && !empty($data['pl_keyword_md5'])) {
                        // Retrocompatibilità: se viene passato pl_keyword_md5, usalo come pl_keyword
                        $plKeywordToUpdate = $data['pl_keyword_md5'];
                    }

                    foreach($fieldMapping as $formField => $dbField) {
                        // Gestisci esplicitamente pl_idGruppo - deve essere sempre incluso anche se 0
                        if($formField === 'pl_idGruppo') {
                            $updateFields[] = "`".$dbField."` = :".$dbField;
                            // Usa array_key_exists invece di isset per gestire correttamente il valore 0
                            $plIdGruppoValue = array_key_exists($formField, $data) ? intval($data[$formField]) : 0;
                            $params[':'.$dbField] = $plIdGruppoValue;
                            error_log("UPDATE PLAYER: pl_idGruppo trovato nei dati, valore: " . $plIdGruppoValue . " (da data: " . (isset($data[$formField]) ? $data[$formField] : 'non presente') . ")");
                        } elseif(isset($data[$formField])) {
                            $updateFields[] = "`".$dbField."` = :".$dbField;
                            $params[':'.$dbField] = $data[$formField];
                        }
                    }

                    // Gestisci i checkbox che potrebbero non essere inviati se non selezionati
                    $checkboxFields = [
                        'pl_active' => 'pl_active',
                        'pl_time' => 'pl_time',
                        'pl_player_freeaccess' => 'pl_player_freeaccess',
                        'pl_monitor' => 'pl_monitor',
                        'pl_test' => 'pl_test',
                        'pl_sendmail' => 'pl_sendmail',
                        'pl_client_edit_selector' => 'pl_client_edit_selector',
                        'pl_client_edit_spot' => 'pl_client_edit_selector',
                        'pl_client_edit_rubriche' => 'pl_client_edit_rubriche'
                    ];
                    foreach($checkboxFields as $formField => $dbField) {
                        if(!isset($data[$formField])) {
                            $updateFields[] = "`".$dbField."` = 0";
                        }
                    }

                    // Se pl_keyword viene modificato, aggiungi anche pl_keyword_md5 calcolato
                    if($plKeywordToUpdate !== null) {
                        $plKeywordMd5 = md5($plKeywordToUpdate . "_" . $playerId);
                        $updateFields[] = "`pl_keyword` = :pl_keyword";
                        $updateFields[] = "`pl_keyword_md5` = :pl_keyword_md5";
                        $params[':pl_keyword'] = $plKeywordToUpdate;
                        $params[':pl_keyword_md5'] = $plKeywordMd5;
                    }
                    
                    if(empty($updateFields)) {
                        sendErrorResponse("Nessun campo da aggiornare", 400);
                    }

                    $query = "UPDATE `players` SET " . implode(", ", $updateFields) . " WHERE `pl_id` = :pl_id";
                    error_log("UPDATE PLAYER QUERY: " . $query);
                    error_log("UPDATE PLAYER PARAMS: " . json_encode($params));
                    $st = DB::$db->prepare($query);
                    $result = $st->execute($params);
                    $rowsAffected = $st->rowCount();
                    error_log("UPDATE PLAYER RESULT: rows affected = " . $rowsAffected);

                    // Verifica se l'UPDATE ha funzionato
                    if ($rowsAffected === 0) {
                        error_log("UPDATE PLAYER WARNING: Nessuna riga aggiornata per player ID " . $playerId);
                    }

                    // Recupera il player aggiornato - usa LEFT JOIN per gestire pl_idGruppo = 0
                    $querySelect = "SELECT p.*, g.* FROM players p LEFT JOIN gruppi g ON(p.pl_idGruppo=g.gr_id) WHERE p.pl_id=:id";
                    $stSelect = DB::$db->prepare($querySelect);
                    $stSelect->execute([':id' => $playerId]);
                    $player = $stSelect->fetchAll();
                    
                    if (empty($player)) {
                        error_log("UPDATE PLAYER ERROR: Player non trovato dopo UPDATE - ID: " . $playerId);
                        sendErrorResponse("Player non trovato dopo l'aggiornamento", 404);
                    }
                    sendSuccessResponse($player[0], "Player aggiornato con successo");
                } catch (Exception $e) {
                    error_log("Errore nell'aggiornamento player: " . $e->getMessage());
                    sendErrorResponse("Errore nell'aggiornamento del player: " . $e->getMessage(), 500);
                }
            } else {
                sendErrorResponse("ID player richiesto per l'aggiornamento", 400);
            }
            break;
            
        case 'PUT':
            if ($id !== null && $action === '') {
                try {
                    $playerId = intval($id);
                    error_log("UPDATE PLAYER: ID=" . $playerId . " | Data ricevuta: " . json_encode($data));
                    $updateFields = [];
                    $params = [':pl_id' => $playerId];

                    // Mappa i campi del form ai campi del database
                    $fieldMapping = [
                        'pl_active' => 'pl_active',
                        'pl_nome' => 'pl_nome',
                        'pl_idGruppo' => 'pl_idGruppo',
                        'pl_riferimento' => 'pl_riferimento',
                        'pl_mail' => 'pl_mail',
                        'tel' => 'pl_telefono',
                        'pl_indirizzo' => 'pl_indirizzo',
                        'pl_citta' => 'pl_citta',
                        'pl_pro' => 'pl_pro',
                        'pl_cap' => 'pl_cap',
                        'pl_note' => 'pl_note',
                        'pl_time' => 'pl_time',
                        'pl_player_freeaccess' => 'pl_player_freeaccess',
                        'pl_monitor' => 'pl_monitor',
                        'pl_test' => 'pl_test',
                        'pl_sendmail' => 'pl_sendmail',
                        'pl_client_edit_selector' => 'pl_client_edit_selector',
                        'pl_client_edit_spot' => 'pl_client_edit_selector',
                        'pl_client_edit_rubriche' => 'pl_client_edit_rubriche',
                        'pl_client_ora_on_ora' => 'pl_client_ora_on_ora',
                        'pl_client_ora_on_min' => 'pl_client_ora_on_min',
                        'pl_oraOnCalcolata' => 'pl_oraOnCalcolata',
                        'pl_client_ora_off_ora' => 'pl_client_ora_off_ora',
                        'pl_client_ora_off_min' => 'pl_client_ora_off_min',
                        'pl_oraOffCalcolata' => 'pl_oraOffCalcolata',
                        'pl_keyword' => 'pl_keyword',
                        'pl_dataCreazione' => 'pl_dataCreazione'
                    ];
                    
                    // Se pl_keyword viene modificato, calcola pl_keyword_md5 = MD5(pl_keyword + "_" + pl_id)
                    $plKeywordToUpdate = null;
                    if(isset($data['pl_keyword']) && !empty($data['pl_keyword'])) {
                        $plKeywordToUpdate = $data['pl_keyword'];
                    } elseif(isset($data['pl_keyword_md5']) && !empty($data['pl_keyword_md5'])) {
                        // Retrocompatibilità: se viene passato pl_keyword_md5, usalo come pl_keyword
                        $plKeywordToUpdate = $data['pl_keyword_md5'];
                    }

                    foreach($fieldMapping as $formField => $dbField) {
                        // Gestisci esplicitamente pl_idGruppo - deve essere sempre incluso anche se 0
                        if($formField === 'pl_idGruppo') {
                            $updateFields[] = "`".$dbField."` = :".$dbField;
                            // Usa array_key_exists invece di isset per gestire correttamente il valore 0
                            $plIdGruppoValue = array_key_exists($formField, $data) ? intval($data[$formField]) : 0;
                            $params[':'.$dbField] = $plIdGruppoValue;
                            error_log("UPDATE PLAYER: pl_idGruppo trovato nei dati, valore: " . $plIdGruppoValue . " (da data: " . (isset($data[$formField]) ? $data[$formField] : 'non presente') . ")");
                        } elseif(isset($data[$formField])) {
                            $updateFields[] = "`".$dbField."` = :".$dbField;
                            $params[':'.$dbField] = $data[$formField];
                        }
                    }

                    // Gestisci i checkbox che potrebbero non essere inviati se non selezionati
                    $checkboxFields = [
                        'pl_active' => 'pl_active',
                        'pl_time' => 'pl_time',
                        'pl_player_freeaccess' => 'pl_player_freeaccess',
                        'pl_monitor' => 'pl_monitor',
                        'pl_test' => 'pl_test',
                        'pl_sendmail' => 'pl_sendmail',
                        'pl_client_edit_selector' => 'pl_client_edit_selector',
                        'pl_client_edit_spot' => 'pl_client_edit_selector',
                        'pl_client_edit_rubriche' => 'pl_client_edit_rubriche'
                    ];
                    foreach($checkboxFields as $formField => $dbField) {
                        if(!isset($data[$formField])) {
                            $updateFields[] = "`".$dbField."` = 0";
                        }
                    }

                    // Se pl_keyword viene modificato, aggiungi anche pl_keyword_md5 calcolato
                    if($plKeywordToUpdate !== null) {
                        $plKeywordMd5 = md5($plKeywordToUpdate . "_" . $playerId);
                        $updateFields[] = "`pl_keyword` = :pl_keyword";
                        $updateFields[] = "`pl_keyword_md5` = :pl_keyword_md5";
                        $params[':pl_keyword'] = $plKeywordToUpdate;
                        $params[':pl_keyword_md5'] = $plKeywordMd5;
                    }
                    
                    if(empty($updateFields)) {
                        sendErrorResponse("Nessun campo da aggiornare", 400);
                    }

                    $query = "UPDATE `players` SET " . implode(", ", $updateFields) . " WHERE `pl_id` = :pl_id";
                    error_log("UPDATE PLAYER QUERY: " . $query);
                    error_log("UPDATE PLAYER PARAMS: " . json_encode($params));
                    $st = DB::$db->prepare($query);
                    $result = $st->execute($params);
                    $rowsAffected = $st->rowCount();
                    error_log("UPDATE PLAYER RESULT: rows affected = " . $rowsAffected);

                    // Verifica se l'UPDATE ha funzionato
                    if ($rowsAffected === 0) {
                        error_log("UPDATE PLAYER WARNING: Nessuna riga aggiornata per player ID " . $playerId);
                    }

                    // Recupera il player aggiornato - usa LEFT JOIN per gestire pl_idGruppo = 0
                    $querySelect = "SELECT p.*, g.* FROM players p LEFT JOIN gruppi g ON(p.pl_idGruppo=g.gr_id) WHERE p.pl_id=:id";
                    $stSelect = DB::$db->prepare($querySelect);
                    $stSelect->execute([':id' => $playerId]);
                    $player = $stSelect->fetchAll();
                    
                    if (empty($player)) {
                        error_log("UPDATE PLAYER ERROR: Player non trovato dopo UPDATE - ID: " . $playerId);
                        sendErrorResponse("Player non trovato dopo l'aggiornamento", 404);
                    }
                    sendSuccessResponse($player[0], "Player aggiornato con successo");
                } catch (Exception $e) {
                    error_log("Errore nell'aggiornamento player: " . $e->getMessage());
                    sendErrorResponse("Errore nell'aggiornamento del player: " . $e->getMessage(), 500);
                }
            } else {
                sendErrorResponse("ID player richiesto", 400);
            }
            break;
            
        default:
            sendErrorResponse("Method not allowed", 405);
    }
}

