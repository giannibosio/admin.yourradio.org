<?php
/**
 * Endpoint per gestione Gruppi
 * GET    /api/gruppi              - Lista tutti i gruppi
 * GET    /api/gruppi/{id}         - Dettaglio gruppo
 * GET    /api/gruppi/{id}/players - Lista players del gruppo
 * GET    /api/gruppi/{id}/subgruppi - Lista sottogruppi
 * POST   /api/gruppi              - Crea nuovo gruppo
 * PUT    /api/gruppi/{id}         - Aggiorna gruppo
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
            // Crea nuovo gruppo
            validateRequired($data, ['nome']);
            $nome = sanitizeInput($data['nome']);
            $newId = Gruppi::createGruppo($nome);
            sendSuccessResponse(['id' => $newId], "Gruppo creato con successo");
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
            
        default:
            sendErrorResponse("Method not allowed", 405);
    }
}

