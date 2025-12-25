<?php
/**
 * Endpoint per Dashboard/Graph
 * GET    /api/dashboard/graph  - Dati per il grafico della dashboard
 */

function handleDashboardRequest($method, $action, $id, $data) {
    switch ($method) {
        case 'GET':
            if ($action === 'graph') {
                // Usa la STESSA query della pagina monitor.php
                $players = Monitor::selectPlayers('tutti');
                
                // Calcola orari (identici a monitor.php)
                $ultimaOra = time() - (60 * 60);
                $ultimaOraIeri = time() - ((60 * 60) * 24);
                
                // Variabili per il grafico
                $listaTotGruppi = '';
                $ultimoGruppoNome = '';
                $totGruppoOn = 0;
                $totGruppoOff = 0;
                $totGruppoOfs = 0;
                $listaTotGruppoOn = '';
                $listaTotGruppoOff = '';
                $listaTotGruppoOfs = '';
                $listaGruppi = '';
                $totStatusOn = 0;
                $totStatusOff = 0;
                $totStatusOfs = 0;
                $totPlayers = 0;
                
                foreach ($players as $p) {
                    $totPlayers++;
                    
                    // Logica ESATTA come in monitor.php (inc/ajax.php riga 279-280)
                    /// calcola status
                    if($p['pl_player_ultimaData']<$ultimaOra){$status=2;}else{$status=1;}
                    if($p['pl_player_ultimaData']<$ultimaOraIeri){$status=3;}
                    
                // Gestione cambio gruppo
                if($p['gr_nome']!=$ultimoGruppoNome && $ultimoGruppoNome!=''){
                    // Cambio gruppo: salva i valori del gruppo precedente
                    $listaTotGruppoOn.=$totGruppoOn.",";
                    $listaTotGruppoOff.=$totGruppoOff.",";
                    $listaTotGruppoOfs.=$totGruppoOfs.",";
                    $totGruppoOn=0;
                    $totGruppoOff=0;
                    $totGruppoOfs=0;
                }
                
                if($p['gr_nome']!=$ultimoGruppoNome){
                    // Nuovo gruppo: aggiungi alla lista gruppi
                    $ultimoGruppoNome=$p['gr_nome'];
                    $listaGruppi.="'".$ultimoGruppoNome."',";
                }
                    
                    // Incrementa contatori
                    switch ($status) {
                        case 1:
                            $totStatusOn++;
                            $totGruppoOn++;
                            break;
                        case 2:
                            $totStatusOff++;
                            $totGruppoOff++;
                            break;
                        case 3:
                            $totStatusOfs++;
                            $totGruppoOfs++;
                            break;
                    }
                }
                
                // Aggiungi l'ultimo gruppo
                $listaTotGruppoOn.=$totGruppoOn.",";
                $listaTotGruppoOff.=$totGruppoOff.",";
                $listaTotGruppoOfs.=$totGruppoOfs.",";
                
                // Prepara i dati per il grafico
                $gruppiArray = explode(',', substr($listaGruppi, 0, -1));
                $gruppiClean = array_map(function($g) {
                    return trim(str_replace(array("'", '"'), '', $g));
                }, $gruppiArray);
                
                $onArray = explode(',', substr($listaTotGruppoOn, 0, -1));
                $offArray = explode(',', substr($listaTotGruppoOff, 0, -1));
                $ofsArray = explode(',', substr($listaTotGruppoOfs, 0, -1));
                
                // Converti in numeri
                $onArray = array_map('intval', $onArray);
                $offArray = array_map('intval', $offArray);
                $ofsArray = array_map('intval', $ofsArray);
                
                $result = array(
                    'gruppi' => $gruppiClean,
                    'on' => $onArray,
                    'off' => $offArray,
                    'ofs' => $ofsArray,
                    'percentages' => array(
                        'on' => $totPlayers > 0 ? round(($totStatusOn*100)/$totPlayers) : 0,
                        'off' => $totPlayers > 0 ? round(($totStatusOff*100)/$totPlayers) : 0,
                        'ofs' => $totPlayers > 0 ? round(($totStatusOfs*100)/$totPlayers) : 0
                    ),
                    'totals' => array(
                        'on' => $totStatusOn,
                        'off' => $totStatusOff,
                        'ofs' => $totStatusOfs,
                        'total' => $totPlayers
                    )
                );
                
                sendSuccessResponse($result);
            } else {
                sendErrorResponse("Action non valida. Usa: /api/dashboard/graph", 400);
            }
            break;
            
        default:
            sendErrorResponse("Method not allowed", 405);
    }
}

