<?php
/**
 * Endpoint per gestione Jingles
 * GET    /api/jingles              - Lista jingles (richiede gruppo_id)
 * GET    /api/jingles/{id}         - Dettaglio jingle
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
                // Dettaglio jingle (se necessario implementare selectJingleById)
                sendErrorResponse("Endpoint non ancora implementato", 501);
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        default:
            sendErrorResponse("Method not allowed", 405);
    }
}

