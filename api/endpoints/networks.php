<?php
/**
 * Endpoint per gestione Networks
 * GET    /api/networks              - Lista tutte le networks
 */

function handleNetworksRequest($method, $action, $id, $data) {
    switch ($method) {
        case 'GET':
            if ($id === null && $action === '') {
                // Lista tutte le networks
                $networks = Gruppi::selectAllNetworks();
                $result = [];
                foreach ($networks as $n) {
                    $result[] = [
                        'id' => (int)$n['id'],
                        'name' => $n['name']
                    ];
                }
                sendSuccessResponse($result);
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        default:
            sendErrorResponse("Method not allowed", 405);
    }
}

