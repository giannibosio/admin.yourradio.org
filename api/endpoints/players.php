<?php
/**
 * Endpoint per gestione Players
 * GET    /api/players              - Lista tutti i players
 * GET    /api/players/{id}         - Dettaglio player
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
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        default:
            sendErrorResponse("Method not allowed", 405);
    }
}

