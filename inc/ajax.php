<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once('config.php');
include_once('database.php');
include_once('functions.php');


if(isset($_GET['job']) && $_GET['job']=='lista-rubriche-by-gruppo'){

    DB::init();
    $res=Rubriche::selectAllRubricheByGruppoId($_GET["id"] ?? '');
    $json_file='{"data":[';
    $json_loop = '';
    foreach ($res as &$r) {
        $div=(substr($r['rub_path'],0,1)!="/" && substr($_SERVER['DOCUMENT_ROOT'],-1)!="/")? "/":"";
        
        $dir_rubrica=$_SERVER['DOCUMENT_ROOT'].$div.$r['rub_path'];
        $totfile=0;
        if(is_dir($dir_rubrica) ){
            $d = dir($dir_rubrica); 
            while (($file = $d->read()) !== false){ 
                if($file != '.' && $file != '..' && $file != 'index.php' && $file != 'UploadHandler.php' ){
                    $totfile++;
                } 
            } 
            $d->close(); 
        }

        $status="--";
        $status=($r['rg_id'] && $totfile>0)?"Attivo":$status;
        $status=($r['rg_id'] && $totfile==0)?"MANCANO FILE !!!":$status;
        
        $json_loop.= '{
            "id":"'.$r['rub_id'].'",
            "nome":"'.strtoupper($r['rub_titolo']).'",
            "files":"'.$totfile.'",
            "status":"'.$status.'"
        },';
    }
    echo $json_file.=substr($json_loop,0,-1).']}';

}




if(isset($_GET['job']) && $_GET['job']=='lista-spot-loc-by-gruppo'){

    DB::init();
    $res=Spot::selectAllSpotLocByGruppoId($_GET["id"] ?? '');
    $json_file='{"data":[';
    $json_loop = '';
    foreach ($res as &$r) {
        $status=($r['spot_loc_attivo']==0)?"Non attivo":"Attivo";
        
        $json_loop.= '{
            "id":"'.$r['spot_loc_id'].'",
            "nome":"'.strtoupper($r['spot_loc_nome']).'",
            "attivo":"'.$r['spot_loc_attivo'].'",
            "status":"'.$status.'"
        },';
    }
    echo $json_file.=substr($json_loop,0,-1).']}';

}


if(isset($_GET['job']) && $_GET['job']=='lista-spot-net-by-gruppo'){

    DB::init();
    $jingles=Spot::selectAllSpotNetByGruppoId($_GET["id"] ?? '');
    $json_file='{"data":[';
    $json_loop = '';
    foreach ($jingles as &$g) {
        $status=($g['spot_attivo']==0)?"Disabilitato":"On-Air";
        
        if($g['spot_attivo']!=0){
            $dal = unixTimeFromDate($g['spot_dal'],0);
            $al = unixTimeFromDate($g['spot_al'],1);
            $now = time();
            $status =($dal<=$now && $al >= $now)? "Dal ".$g['spot_dal']." al ".$g['spot_al']:"Scaduto";
            $status =($dal>=$now && $al >= $now)? "Dal ".$g['spot_dal']:$status;
            //echo strtoupper($g['jingle_nome'])." -> ".$status." --- programmato dal ".$g['jingle_dal']." .".$dal.' al '.$g['jingle_al']." .".$al." - now:".$now."<br><br>";
        }
        $json_loop.= '{
            "id":"'.$g['spot_id'].'",
            "nome":"'.strtoupper($g['spot_nome']).'",
            "attivo":"'.$g['spot_attivo'].'",
            "dal":"'.$g['spot_dal'].'",
            "al":"'.$g['spot_al'].'",
            "status":"'.$status.'"
        },';
    }
    echo $json_file.=substr($json_loop,0,-1).']}';

}


if(isset($_GET['job']) && $_GET['job']=='update-song'){
    DB::init();
    $res=Songs::updateSongById($_GET);
    echo "song aggiornata";
}

if(isset($_GET['job']) && $_GET['job']=='lista-songs'){
    DB::init();
    $gruppi=Songs::selectAll($_GET);
    $json_file='{"data":[';
    $json_loop = '';
    foreach ($gruppi as &$g) {
        if($g['sg_artista']!=''){
            $json_loop.= '{
            "id":"'.$g['sg_id'].'",
            "artista":"'.$g['sg_artista'].'",
            "titolo":"'.$g['sg_titolo'].'",
            "anno":"'.$g['sg_anno'].'",
            "attivo":"'.$g['sg_attivo'].'"
        },';
        }
        
    }
    echo $json_file.=substr($json_loop,0,-1).']}';

}

if(isset($_POST['job']) && $_POST['job']=='deleteSong'){
    DB::init();
    $res=Songs::deleteById($_POST['id'] ?? '');
    $resDelFile = "Song ". ($_POST['id'] ?? '')." cancellata da db\n";
    $resDelFile .= Utils::deleteSongFile();
    echo $resDelFile;
}

if(isset($_GET['job']) && $_GET['job']=='uploadFile'){ 
    $fileName = $_FILES["file"]["name"] ?? ''; // The file name
    $fileTmpLoc = $_FILES["file"]["tmp_name"] ?? ''; // File in the PHP tmp folder
    $fileType = $_FILES["file"]["type"] ?? ''; // The type of file it is
    $fileSize = $_FILES["file"]["size"] ?? 0; // File size in bytes
    $fileErrorMsg = $_FILES["file"]["error"] ?? 0; // 0 for false... and 1 for true
    if (!$fileTmpLoc) { // if file not chosen
        echo "ERROR: file not chosen";
        exit();
    }
    if(move_uploaded_file($fileTmpLoc, $_SESSION['songs']['totalPathFile'] ?? '')){ 
        DB::init();
        Songs::uploadDataFile($_SESSION['songs']['schedaId'] ?? '',$_SESSION['mediaplayer']['file'] ?? '',$fileSize);
        echo ($_SESSION['songs']['totalPathFile'] ?? '')." (filesize: ".$fileSize.") - upload completo!";
    } else {
        echo "ERROR on move_uploaded_file";
    }

}


if(isset($_GET['job']) && $_GET['job']=='lista-jingles-by-gruppo'){

    DB::init();
    $jingles=Jingles::selectAllJinglesByGruppoId($_GET["id"] ?? '');
    $json_file='{"data":[';
    $json_loop = '';
    foreach ($jingles as &$g) {
        $status=($g['jingle_attivo']==0)?"Non attivo":"On-Air";
        
        if ($g['jingle_programmato']==1){
            $dal = unixTimeFromDate($g['jingle_dal'],0);
            $al = unixTimeFromDate($g['jingle_al'],1);
            $now = time();
            $status =($dal<=$now && $al >= $now)? "Programmazione : dal ".$g['jingle_dal']." al ".$g['jingle_al']:"Scaduto";
            $status =($dal>=$now && $al >= $now)? "Programmato dal ".$g['jingle_dal']:$status;
            //echo strtoupper($g['jingle_nome'])." -> ".$status." --- programmato dal ".$g['jingle_dal']." .".$dal.' al '.$g['jingle_al']." .".$al." - now:".$now."<br><br>";
        }
        
        
        $json_loop.= '{
            "id":"'.$g['jingle_id'].'",
            "nome":"'.strtoupper($g['jingle_nome']).'",
            "attivo":"'.$g['jingle_attivo'].'",
            "programmato":"'.$g['jingle_programmato'].'",
            "dal":"'.$g['jingle_dal'].'",
            "al":"'.$g['jingle_al'].'",
            "status":"'.$status.'"
        },';
    }
    echo $json_file.=substr($json_loop,0,-1).']}';

}



if(isset($_GET['job']) && $_GET['job']=='lista-players-by-gruppo'){

    DB::init();
    $players=Gruppi::selectAllPlayersGruppoById($_GET["id"] ?? '');
    $json_file='{"data":[';
    $json_loop = '';
    foreach ($players as &$g) {
        if($g['pl_active']==1){$status="ON";}else{$status="OFF";}
        $json_loop.= '{
            "id":"'.$g['pl_id'].'",
            "nome":"'.strtoupper($g['pl_nome']).'",
            "attivo":"'.$status.'",
            "ultimo_accesso":"'.substr($g['pl_player_ultimaDataEstesa'],0,10).'"
        },';
    }
    echo $json_file.=substr($json_loop,0,-1).']}';

}

if(isset($_GET['job']) && $_GET['job']=='lista-gruppi'){

    DB::init();
    $gruppi=Gruppi::selectAll();
    $json_file='{"data":[';
    $json_loop = '';
    foreach ($gruppi as &$g) {
        $json_loop.= '{
            "id":"'.strtoupper($g['gr_id']).'",
            "nome":"'.strtoupper($g['gr_nome']).'",
            "players":"'.$g['tot_player'].'",
            "attivo":"'.$g['gr_active'].'"
        },';
    }
    echo $json_file.=substr($json_loop,0,-1).']}';

}

if(isset($_GET['job']) && $_GET['job']=='lista-utenti'){

    DB::init();
    $users=Utenti::selectUtenti();
    $json_file='{"data":[';
    $json_loop = '';

    
    $gruppo="ADMIN";

    foreach ($users as &$u) {
        $gruppo='ADMIN';
        if($u['gr_nome']!=''){$gruppo=$u['gr_nome'];}
        if($u['active']==1){$attivo="attivo";}else{$attivo="disattivato";}
        $json_loop.= '{
            "id":"'.strtoupper($u['id']).'",
            "nome":"'.strtoupper($u['nome']).'",
            "gruppo":"'.strtoupper($gruppo).'",
            "mail":"'.strtolower($u['mail']).'",
            "ultimo_accesso":"'.$u['ultimoAccesso'].'",
            "data_creazione":"'.$u['dataCreazione'].'",
            "ruolo":"'.$u['permesso'].'",
            "attivo":"'.$u['active'].'"
        },';
    }
    echo $json_file.=substr($json_loop,0,-1).']}';

}

if(isset($_GET['job']) && $_GET['job']=='chngpsw'){

    if(!isset($_GET['idprofile']) ||!isset($_GET['newpass'])){return;}
    DB::init();
    $res=Utenti::changePassword($_GET['idprofile'] ?? '',md5($_GET['newpass'] ?? ''));
    echo md5($_GET['newpass'] ?? '');
}

if(isset($_GET['job']) && $_GET['job']=='monitor'){
	if(!isset($_GET['gruppo']) || $_GET['gruppo']==''){$gruppo='tutti';}else{$gruppo=$_GET['gruppo'];}
	DB::init();
	$players=Monitor::selectPlayers($gruppo);

	// calcola orari
    $ultimaOra=time()-(60*60);
    $ultimaOraIeri=time()-((60*60)*24);
    $json_file='{"data":[';
    $json_loop = '';

    foreach ($players as &$p) {
    	/// calcola status
    	if($p['pl_player_ultimaData']<$ultimaOra){$status=2;}else{$status=1;}
    	if($p['pl_player_ultimaData']<$ultimaOraIeri){$status=3;}
		/// calcola SD
    	$sd_status=0;
    	$mem= $p['pl_mem_size']."-".$p['pl_mem_percent']."%";
    	if((int)$p['pl_mem_percent']==0){$sd_status=0;$mem="ND";}
    	if((int)$p['pl_mem_percent']>0){$sd_status=1;}
    	if((int)$p['pl_mem_percent']>70){$sd_status=2;}
    	if((int)$p['pl_mem_percent']>90){$sd_status=3;}

    	if(substr(strtoupper($p['pl_player_pc']),0,4)=="RSPI"){
    		$ip=$p['pl_player_ip'];
    		$type="RASPI";
    	}else{
    		$mem="SD";$sd_status=4;
    		$ip=strtoupper($p['pl_player_pc']);
    		$type="PC";
    	}
    	$ping=substr($p['pl_player_ultimaDataEstesa'],2,-3);
    	$json_loop.= '{
    		"player_id":"'.strtoupper($p['pl_id']).'",
    		"gruppo":"'.strtoupper($p['gr_nome']).'",
    		"nome":"'.strtoupper($p['pl_nome']).'",
    		"ping":"'.$ping.'",
    		"ip":"'.$ip.'",
    		"sd":"'.$mem.'",
    		"sd_status":'.$sd_status.',
    		"status":'.$status.',
    		"type":"'.$type.'"
    	},';
    }
    echo $json_file.=substr($json_loop,0,-1).']}';
}

if(isset($_GET['job']) && $_GET['job']=='ping'){
	DB::init();
	$pings=Monitor::selectPingByPlayerID($_GET['id'] ?? '');
	$json_loop = '';
	foreach ($pings as &$p) {
		$status = $p['ping_status'] ?? '';
		$json_loop.= '{
    		"TimeStamp":"'.($p['ping_timestamp'] ?? '').'",
    		"Giorno":"'.substr($p['ping_timestamp'] ?? '',0,10).'",
    		"PcName":"'.($p['ping_pc_name'] ?? '').'",
    		"IpExternal":"'.($p['ping_IP_player'] ?? '').'",
    		"Note":"'.($p['ping_note'] ?? '').'",
    		"Status":"'.$status.'"
    	},';
	}
	echo '{"data":['.substr($json_loop,0,-1).']}';
}
