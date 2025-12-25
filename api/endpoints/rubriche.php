<?php
/**
 * Endpoint per gestione Rubriche
 * GET    /api/rubriche              - Lista rubriche (richiede gruppo_id)
 */

function handleRubricheRequest($method, $action, $id, $data) {
    switch ($method) {
        case 'GET':
            if ($id === null && $action === '') {
                // Lista rubriche per gruppo
                if (!isset($_GET['gruppo_id'])) {
                    sendErrorResponse("gruppo_id richiesto", 400);
                }
                $rubriche = Rubriche::selectAllRubricheByGruppoId($_GET['gruppo_id']);
                $result = [];
                foreach ($rubriche as $r) {
                    $div = (substr($r['rub_path'], 0, 1) != "/" && substr($_SERVER['DOCUMENT_ROOT'], -1) != "/") ? "/" : "";
                    $dir_rubrica = $_SERVER['DOCUMENT_ROOT'] . $div . $r['rub_path'];
                    $totfile = countFilesInDirectory($dir_rubrica);
                    
                    $status = "--";
                    $status = ($r['rg_id'] && $totfile > 0) ? "Attivo" : $status;
                    $status = ($r['rg_id'] && $totfile == 0) ? "MANCANO FILE !!!" : $status;
                    
                    $result[] = [
                        'id' => (int)$r['rub_id'],
                        'nome' => strtoupper($r['rub_titolo']),
                        'files' => $totfile,
                        'status' => $status
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

