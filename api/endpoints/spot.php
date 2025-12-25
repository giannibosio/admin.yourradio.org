<?php
/**
 * Endpoint per gestione Spot
 * GET    /api/spot/net              - Lista spot network (richiede gruppo_id)
 * GET    /api/spot/loc              - Lista spot locali (richiede gruppo_id)
 */

function handleSpotRequest($method, $action, $id, $data) {
    switch ($method) {
        case 'GET':
            if ($action === 'net') {
                // Lista spot network per gruppo
                if (!isset($_GET['gruppo_id'])) {
                    sendErrorResponse("gruppo_id richiesto", 400);
                }
                $spots = Spot::selectAllSpotNetByGruppoId($_GET['gruppo_id']);
                $result = [];
                foreach ($spots as $s) {
                    $status = ($s['spot_attivo'] == 0) ? "Disabilitato" : "On-Air";
                    
                    if ($s['spot_attivo'] != 0) {
                        $dal = unixTimeFromDate($s['spot_dal'], 0);
                        $al = unixTimeFromDate($s['spot_al'], 1);
                        $now = time();
                        $status = ($dal <= $now && $al >= $now) ? "Dal ".$s['spot_dal']." al ".$s['spot_al'] : "Scaduto";
                        $status = ($dal >= $now && $al >= $now) ? "Dal ".$s['spot_dal'] : $status;
                    }
                    
                    $result[] = [
                        'id' => (int)$s['spot_id'],
                        'nome' => strtoupper($s['spot_nome']),
                        'attivo' => (int)$s['spot_attivo'],
                        'dal' => isset($s['spot_dal']) ? $s['spot_dal'] : null,
                        'al' => isset($s['spot_al']) ? $s['spot_al'] : null,
                        'status' => $status
                    ];
                }
                sendSuccessResponse($result);
            } elseif ($action === 'loc') {
                // Lista spot locali per gruppo
                if (!isset($_GET['gruppo_id'])) {
                    sendErrorResponse("gruppo_id richiesto", 400);
                }
                $spots = Spot::selectAllSpotLocByGruppoId($_GET['gruppo_id']);
                $result = [];
                foreach ($spots as $s) {
                    $status = ($s['spot_loc_attivo'] == 0) ? "Non attivo" : "Attivo";
                    
                    $result[] = [
                        'id' => (int)$s['spot_loc_id'],
                        'nome' => strtoupper($s['spot_loc_nome']),
                        'attivo' => (int)$s['spot_loc_attivo'],
                        'status' => $status
                    ];
                }
                sendSuccessResponse($result);
            } else {
                sendErrorResponse("Action non valida. Usa 'net' o 'loc'", 400);
            }
            break;
            
        default:
            sendErrorResponse("Method not allowed", 405);
    }
}

