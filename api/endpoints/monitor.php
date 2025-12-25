<?php
/**
 * Endpoint per Monitoraggio Players
 * GET    /api/monitor              - Lista players monitorati (opzionale: gruppo)
 * GET    /api/monitor/player/{id}  - Dettaglio player monitorato
 * GET    /api/monitor/ping/{id}    - Lista ping di un player
 */

function handleMonitorRequest($method, $action, $id, $data) {
    switch ($method) {
        case 'GET':
            if ($action === '' && $id === null) {
                // Lista players monitorati
                $gruppo = isset($_GET['gruppo']) && $_GET['gruppo'] != '' ? $_GET['gruppo'] : 'tutti';
                $players = Monitor::selectPlayers($gruppo);
                
                // Calcola orari
                $ultimaOra = time() - (60 * 60);
                $ultimaOraIeri = time() - ((60 * 60) * 24);
                
                $result = [];
                foreach ($players as $p) {
                    // Calcola status
                    $status = 1;
                    if ($p['pl_player_ultimaData'] < $ultimaOra) {
                        $status = 2;
                    }
                    if ($p['pl_player_ultimaData'] < $ultimaOraIeri) {
                        $status = 3;
                    }
                    
                    // Calcola SD
                    $sd_status = 0;
                    $mem = $p['pl_mem_size'] . "-" . $p['pl_mem_percent'] . "%";
                    if ((int)$p['pl_mem_percent'] == 0) {
                        $sd_status = 0;
                        $mem = "ND";
                    }
                    if ((int)$p['pl_mem_percent'] > 0) {
                        $sd_status = 1;
                    }
                    if ((int)$p['pl_mem_percent'] > 70) {
                        $sd_status = 2;
                    }
                    if ((int)$p['pl_mem_percent'] > 90) {
                        $sd_status = 3;
                    }
                    
                    if (substr(strtoupper($p['pl_player_pc']), 0, 4) == "RSPI") {
                        $ip = $p['pl_player_ip'];
                        $type = "RASPI";
                    } else {
                        $mem = "SD";
                        $sd_status = 4;
                        $ip = strtoupper($p['pl_player_pc']);
                        $type = "PC";
                    }
                    
                    $ping = substr($p['pl_player_ultimaDataEstesa'], 2, -3);
                    
                    $result[] = [
                        'player_id' => (int)$p['pl_id'],
                        'gruppo' => strtoupper($p['gr_nome']),
                        'nome' => strtoupper($p['pl_nome']),
                        'ping' => $ping,
                        'ip' => $ip,
                        'sd' => $mem,
                        'sd_status' => $sd_status,
                        'status' => $status,
                        'type' => $type
                    ];
                }
                sendSuccessResponse($result);
            } elseif ($action === 'player' && $id !== null) {
                // Dettaglio player monitorato
                $player = Monitor::selectPlayerByID($id);
                if (empty($player)) {
                    sendErrorResponse("Player non trovato", 404);
                }
                sendSuccessResponse($player[0]);
            } elseif ($action === 'ping' && $id !== null) {
                // Lista ping di un player
                $pings = Monitor::selectPingByPlayerID($id);
                $result = [];
                foreach ($pings as $p) {
                    $status = isset($p['ping_status']) ? $p['ping_status'] : '';
                    $result[] = [
                        'TimeStamp' => isset($p['ping_timestamp']) ? $p['ping_timestamp'] : '',
                        'Giorno' => substr(isset($p['ping_timestamp']) ? $p['ping_timestamp'] : '', 0, 10),
                        'PcName' => isset($p['ping_pc_name']) ? $p['ping_pc_name'] : '',
                        'IpExternal' => isset($p['ping_IP_player']) ? $p['ping_IP_player'] : '',
                        'Note' => isset($p['ping_note']) ? $p['ping_note'] : '',
                        'Status' => $status
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

