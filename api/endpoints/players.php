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
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        case 'PUT':
        case 'POST':
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
                        'pl_ds_attivo' => 'pl_ds_attivo',
                        'pl_ds_audio' => 'pl_ds_audio',
                        'pl_ds_videospot' => 'pl_ds_videospot',
                        'pl_ds_videoclip_on' => 'pl_ds_videoclip_on',
                        'pl_ds_oroscopo_on' => 'pl_ds_oroscopo_on',
                        'pl_ds_news_on' => 'pl_ds_news_on',
                        'pl_ds_meteo_on' => 'pl_ds_meteo_on',
                        'pl_ds_adv_on' => 'pl_ds_adv_on',
                        'pl_ds_campagna_id' => 'pl_ds_campagna_id'
                    ];

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
                        'pl_client_edit_rubriche' => 'pl_client_edit_rubriche',
                        'pl_ds_attivo' => 'pl_ds_attivo',
                        'pl_ds_audio' => 'pl_ds_audio',
                        'pl_ds_videospot' => 'pl_ds_videospot',
                        'pl_ds_videoclip_on' => 'pl_ds_videoclip_on',
                        'pl_ds_oroscopo_on' => 'pl_ds_oroscopo_on',
                        'pl_ds_news_on' => 'pl_ds_news_on',
                        'pl_ds_meteo_on' => 'pl_ds_meteo_on',
                        'pl_ds_adv_on' => 'pl_ds_adv_on'
                    ];
                    foreach($checkboxFields as $formField => $dbField) {
                        if(!isset($data[$formField])) {
                            $updateFields[] = "`".$dbField."` = 0";
                        }
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

