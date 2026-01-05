<?php

/**
 * Funzione helper per chiamare le API su yourradio.org
 * SEMPRE usa https://yourradio.org/api - MAI localhost per il database
 */
function callApi($endpoint, $method = 'GET', $data = null) {
    // SEMPRE usa https://yourradio.org/api - MAI localhost
    $apiUrl = "https://yourradio.org/api/" . ltrim($endpoint, '/');
    
    // Log dettagliato della chiamata
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint' => $endpoint,
        'full_url' => $apiUrl,
        'method' => $method,
        'server' => 'https://yourradio.org',
        'has_data' => $data !== null
    ];
    error_log("API CALL: " . json_encode($logData, JSON_PRETTY_PRINT));
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($data !== null) {
        $jsonData = is_array($data) ? json_encode($data) : $data;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        error_log("API CALL DATA: " . substr($jsonData, 0, 500)); // Log primi 500 caratteri
    }
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $curlInfo = curl_getinfo($ch);
    curl_close($ch);
    
    $responseLog = [
        'http_code' => $httpCode,
        'response_time' => round(($endTime - $startTime) * 1000, 2) . 'ms',
        'response_size' => strlen($response),
        'curl_error' => $error ?: 'none',
        'effective_url' => $curlInfo['url'] ?? $apiUrl
    ];
    error_log("API RESPONSE: " . json_encode($responseLog, JSON_PRETTY_PRINT));
    
    if ($error) {
        error_log("API CALL ERROR: " . $error . " | URL: " . $apiUrl);
        error_log("API CALL ERROR DETAILS: curl_errno potrebbe indicare problemi di connessione, DNS, SSL");
        return ['success' => false, 'error' => ['message' => 'Errore di connessione: ' . $error], 'url' => $apiUrl, 'curl_error' => $error];
    }
    
    // Se non c'è risposta o HTTP code non è 200
    if (empty($response) || $httpCode !== 200) {
        error_log("API CALL FAILED: HTTP " . $httpCode . " | URL: " . $apiUrl);
        error_log("API CALL FAILED RESPONSE: " . substr($response, 0, 500));
        error_log("API CALL FAILED CURL INFO: " . json_encode($curlInfo));
        return ['success' => false, 'error' => ['message' => 'Errore HTTP ' . $httpCode . ($response ? ': ' . substr($response, 0, 100) : '')], 'httpCode' => $httpCode, 'url' => $apiUrl, 'response' => substr($response, 0, 200)];
    }
    
    $result = json_decode($response, true);
    if ($result === null) {
        error_log("API CALL JSON DECODE ERROR: " . json_last_error_msg() . " | Response: " . substr($response, 0, 200));
        return ['success' => false, 'error' => ['message' => 'Risposta JSON non valida: ' . json_last_error_msg()], 'httpCode' => $httpCode, 'url' => $apiUrl];
    }
    
    if (isset($result['success'])) {
        error_log("API CALL SUCCESS: " . ($result['success'] ? 'YES' : 'NO') . " | URL: " . $apiUrl);
        if(!$result['success'] && isset($result['error'])) {
            error_log("API CALL ERROR IN RESPONSE: " . json_encode($result['error']));
        }
    } else {
        error_log("API CALL WARNING: Risposta senza campo 'success' | URL: " . $apiUrl);
    }
    
    return $result ?: ['success' => false, 'error' => ['message' => 'Risposta non valida'], 'httpCode' => $httpCode, 'url' => $apiUrl];
}

class Utils 
{
    
    public static function createSelectSongsFilters()
    {
        
        DB::init();
    	$res=Songs::selectAllFormats();

    	$sel = '
    	<select id="filter_format" name="filter_format" class="form-control">
    	<option value="0">All</option>
    	';
    	foreach ($res as &$r) {
        	$sel.= '<option value="'.$r['frmt_id'].'">'.$r['frmt_nome'].'</option>';
    	}
    	return $sel.='</select>';
	}

    public static function deleteSongFile()
    {
        $file=$_SESSION['songs']['totalPathFile'];
        if(file_exists($file)){
            $dd=unlink($file);
            if($dd){
                $res = "(eliminato ".$file.")";
            }else{
                $res = "(ERRORE nell'eliminare ".$file.")";
            }
        }else{
            $res = "(file ".$file." non esiste!)";
        }
        return $res;
    }

	public static function createAllSongsFilters()
    {
    	$sel = '
    	<div class="cardx card-body shadow mb-6 box-filter">
    	<h4>Filtri</h4><hr>
    	';
    	
    	// FILTRI PRINCIPALI IN ALTO: Format, Abilitate, Diritti
    	
    	//Format - Multiselect (verrà popolata via JavaScript dall'API)
    	$sel .= '
    	<label class="songFilter_select">Format</label>
    	<select id="f_format" name="f_format[]" class="form-control songFilter_select" multiple size="5">
    	<!-- I format verranno caricati dinamicamente dall\'API -->
    	</select>';
    	$sel.='<small class="form-text text-muted">Clicca per selezionare/deselezionare. Tieni premuto Ctrl (o Cmd su Mac) per selezionare più format</small>';

    	//Active
    	$sel .= '
    	<label class="songFilter_select">Abilitate</label>
    	<select id="f_abilitate" name="f_abilitate" class="form-control songFilter_select">
    	<option value="0">Tutte</option>
    	<option value="1">Abilitate</option>
    	<option value="2">Non Abilitate</option>
    	</select>
    	';
    	
    	
    	
    	// ACCORDION PER GLI ALTRI FILTRI
    	$sel .= '
    	<hr>
    	<div class="accordion" id="accordionAltriFiltri">
    		<div class="card">
    			<div class="card-header" id="headingAltriFiltri">
    				<h5 class="mb-0">
    					<button class="btn btn-primary btn-sm" type="button" data-toggle="collapse" data-target="#collapseAltriFiltri" aria-expanded="false" aria-controls="collapseAltriFiltri">
    						Altri filtri
    					</button>
    				</h5>
    			</div>
    			<div id="collapseAltriFiltri" class="collapse" aria-labelledby="headingAltriFiltri" data-parent="#accordionAltriFiltri">
    				<div class="card-body">
    	';
    	
    	//Nazionalità
    	$sel .= '
    	<label class="songFilter_select">Nazionalità</label>
    	<select id="f_nazionalita" name="f_nazionalita" class="form-control songFilter_select">
    		<option value="0">Tutte</option>
    		<option value="1">Italiana</option>
    		<option value="2">Straniera</option>
    	</select>
    	';

    	//Strategia
    	$sel .= '
    	<label class="songFilter_select">Strategia</label>
    	<select id="f_strategia" name="f_strategia" class="form-control songFilter_select">
    		<option value="0">Tutte</option>
    		<option value="2">Stra.2</option>
    		<option value="3">Stra.3</option>
    		<option value="4">Stra.4</option>
    		<option value="5">Stra.5</option>
    		<option value="1">Speciale...</option>
    	</select>
    	';

    	//Sesso
    	$sel .= '
    	<label class="songFilter_select">Sesso</label>
    	<select id="f_sex" name="f_sex" class="form-control songFilter_select">
    		<option value="">Tutti</option>
    		<option value="Maschile">Maschile</option>
    		<option value="Femminile">Femminile</option>
    		<option value="Strumentale">Strumentale</option>
    	</select>
    	';

    	//Umore
    	$sel .= '
    	<label class="songFilter_select">Umore</label>
    	<select id="f_umore" name="f_umore" class="form-control songFilter_select">
    		<option value="" selected="">Tutti</option>
    		<option value="0">Normale</option>
    		<option value="1">Allegro</option>
    		<option value="2">Allegrissimo</option>
    		<option value="3">Aggressivo</option>
    		<option value="4">Triste</option>
    		<option value="5">Malinconico</option>
    	</select>
    	';

    	//Ritmo
    	$sel .= '
    	<label class="songFilter_select">Ritmo</label>
    	<select id="f_ritmo" name="f_ritmo" class="form-control songFilter_select">
	    	<option value="0" selected="">Tutti</option>
	    	<option value="1">Molto Lento</option>
	    	<option value="2">Lento</option>
	    	<option value="3">Moderato</option>
	    	<option value="4">Veloce</option>
	    	<option value="5">Molto Veloce</option>
    	</select>
    	';

    	//Energia
    	$sel .= '
    	<label class="songFilter_select">Energia</label>
    	<select id="f_energia" name="f_energia" class="form-control songFilter_select">
	    	<option value="0" selected="">Tutte</option>
	    	<option value="1">Energia 1</option>
	    	<option value="2">Energia 2</option>
	    	<option value="3">Energia 3</option>
	    	<option value="4">Energia 4</option>
	    	<option value="5">Energia 5</option>
	    </select>
    	';

    	//Anno
    	$sel .= '
    	<label class="songFilter_select">Anno</label>
    	<select id="f_anno" name="f_anno" class="form-control songFilter_select">
	    	<option value="0" selected="0">Tutti</option>';
		    for($a=date('Y');$a>1940;$a=$a-1){
		    	$sel.='<option value="'.$a.'">'.$a.'</option>';
		    }
	    $sel.='</select>';

	    //Periodo
    	$sel .= '
    	<label class="songFilter_select">Periodo</label>
    	<select id="f_periodo" name="f_periodo" class="form-control songFilter_select">
	    	<option value="0" selected="">Tutti</option>
	    	<option value="1">Estate</option>
	    	<option value="2">Natale</option>
	    </select>
    	';

    	//Genere
    	$sel .= '
    	<label class="songFilter_select">Genere</label>
    	<select id="f_genere" name="f_genere" class="form-control songFilter_select">
	    	<option value="0" selected="">Tutti</option>
	    	<option value="2">Disco</option>
	    	<option value="3">Pop</option>
	    	<option value="4">Rock</option>
	    	<option value="32">Jazz</option>
	    	<option value="13">Urban</option>
	    	<option value="37">Hip Hop</option>
	    	<option value="48">Lounge</option>
	    	<option value="54">World</option>
	    	<option value="59">Deep</option>
	    	<option value="52">Classica Str.</option>
	    	<option value="53">Classica Str.</option>
	    	<option value="55">Bimbi</option>
	    	<option value="66">Country</option>
	    </select>
    	';

    	//Diritti
    	$sel .= '
    	<label class="songFilter_select">Diritti</label>
    	<select id="f_diritti" name="f_diritti" class="form-control songFilter_select">
	    	<option value="*" selected="">Tutti</option>
	    	<option value="0">Siae</option>
	    	<option value="1">Creative C</option>
	    	<option value="3">WaterMelon</option>
	    </select>
    	';
    	
		// CHIUSURA ACCORDION
    	$sel .= '
    				</div>
    			</div>
    		</div>
    	</div>
    	';

		$sel .= '
		<hr>
		<button type="button"  style="width:100%" id="songFilter_reset" class="btn btn-outline-primary" ><span class="fe fe-refresh-cw fe-16"></span> Reset Filtri</button>
		<br><br>
		<button style="width:100%" type="button" id="nuovaSongBtn" class="btn btn-outline-primary" ><span class="fe fe-plus fe-16"></span> Nuova Song</button>

		</div>';
    	return $sel;
	}

}

function unixTimeFromDate($data,$time=0){
    /// trasforma la data YYYY-MM-DD in unixtime
    $d=explode("-", $data);
    $h='0';$m='0';$s='0';
    if($time==1){
        $h='23';$m='59';
    }
    return mktime($h, $m, $s, $d[1], $d[2], $d[0]);
}

function createSelectForSchedaSong($name,$nameField,$selected,$options){
    $opts = '';
    foreach ($options as $key =>$value) {
        if($selected==$key){$sel = "selected";}else{$sel = "";}
        $opts.= '<option value="'.$key.'" '.$sel.'>'.strtoupper($value).'</option>';
    }
    $res = '
        <label for="rete_id">'.$name.'</label>
        <select class="form-control" name="'.$nameField.'" id="'.$nameField.'" >
        '.$opts.'
        </select>
        ';
    return $res;

}

function createSelectForSchedaSong2($name,$nameField,$selected){
    $optt=Select::getOptions($nameField);
    $opts = '';
    foreach ($optt as $key =>$value) {
        if($selected==$key){$sel = "selected";}else{$sel = "";}
        $opts.= '<option value="'.$key.'" '.$sel.'>'.strtoupper($value).'</option>';
    }
    $res = '
        <label for="rete_id" style="text-transform: capitalize;">'.$name.'</label>
        <select class="form-control" name="'.$nameField.'" id="'.$nameField.'" >
        '.$opts.'
        </select>
        ';
    return $res;

}

function buildCheckSubGroupByIdPlayer($id){
    $check = '';
    
    // Usa l'API invece del database locale
    $apiResponse = callApi("players/" . intval($id) . "/subgruppi");
    
    if($apiResponse && isset($apiResponse['success']) && $apiResponse['success'] && isset($apiResponse['data'])) {
        $res = $apiResponse['data'];
        foreach($res as $sg){
            $checked = (isset($sg['checked']) && $sg['checked'] == 1) ? " checked " : "";
            $check.='
    <div class="custom-control custom-checkbox">
        <input type="checkbox" class="custom-control-input" id="subgruppo_'.$sg['sgr_id'].'" name="subgruppo_'.$sg['sgr_id'].'" value="1" '.$checked.'>
        <label class="custom-control-label" for="subgruppo_'.$sg['sgr_id'].'">'.$sg['sgr_nome'].'-'.$sg['sgr_id'].'</label>
    </div>';
        }
    } else {
        // Fallback: prova a usare il database locale se disponibile (solo per compatibilità)
        if(class_exists('Gruppi') && method_exists('Gruppi', 'selectSubGruppoByIdPlayer')) {
            try {
                if(!isset(DB::$db) || DB::$db === null) {
                    DB::init();
                }
                $res = Gruppi::selectSubGruppoByIdPlayer($id);
                foreach($res as $sg){
                    $getCheck = Gruppi::getCheckRelatedSubGruppoByIdPlayer($id, $sg['sgr_id']);
                    if(!empty($getCheck) && isset($getCheck[0]['checked']) && $getCheck[0]['checked']==1){$checked=" checked ";}else{$checked="";}
                    $check.='
    <div class="custom-control custom-checkbox">
        <input type="checkbox" class="custom-control-input" id="subgruppo_'.$sg['sgr_id'].'" name="subgruppo_'.$sg['sgr_id'].'" value="1" '.$checked.'>
        <label class="custom-control-label" for="subgruppo_'.$sg['sgr_id'].'">'.$sg['sgr_nome'].'-'.$sg['sgr_id'].'</label>
    </div>';
                }
            } catch(Exception $e) {
                error_log("Errore in buildCheckSubGroupByIdPlayer: " . $e->getMessage());
            }
        }
    }

    return $check;

}


function buildCheckByDbField($label,$nameField,$valueField,$customId = null){

    $checked=($valueField==1 ? "checked" : "");
    // Genera un ID unico combinando nameField e label normalizzato
    if($customId === null) {
        $idSuffix = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $label));
        $fieldId = $nameField . '_' . $idSuffix;
    } else {
        $fieldId = $customId;
    }
    $check='
    <div class="custom-control custom-checkbox">
        <input type="checkbox" class="custom-control-input" id="'.$fieldId.'" name="'.$nameField.'" value="1" '.$checked.'>
        <label class="custom-control-label" for="'.$fieldId.'">'.$label.'</label>
    </div>

    ';
    return $check;
}


function verifyGroupFolder($id,$name){

	$path=PLAYER_PATH.$name."/";
	//verify player
	$placeholder_logo="./assets/images/pc_logo_200.png";
	$gruppo_logo=$path."/images/logo_gruppo.png";
	$gruppo_logo_thumb=$path."/images/thumbnail/logo_gruppo.png";
	if(!is_dir($path)){
		$res=mkdir( $path ,0777);
	}
	//verify subfolder
	$folders=array('ds/','ds/adv/','ds/campagne/','ds/item/','ds/videospot/','images/','images/thumbnail/','jingle/','spot/','spot/loc/','xml/','playlist/');
	foreach($folders as $f){
		if(!is_dir($path.$f)){
			$res=mkdir( $path.$f ,0777);
		}
	}
	//copy logo placeholder
	if(!file_exists($gruppo_logo)){
		copy($placeholder_logo, $gruppo_logo);
	}
	if(!file_exists($gruppo_logo_thumb)){
		copy($placeholder_logo, $gruppo_logo_thumb);
	}


}

function deleteGroupFolder($id,$name) {
	$path=PLAYER_PATH.$name."/";
	$folders=array('ds/adv/','ds/campagne/','ds/item/','ds/videospot/','ds/','images/thumbnail/','images/','jingle/','spot/loc/','spot/','xml/');
	foreach($folders as $f){
	    $files = glob($path.$f.'*');
	    foreach ($files as $file) {
	        if(is_dir($file)){
	        	rmdir($file."/");
	        }else{
	        	unlink($file);
	        }
	    }
		rmdir($path.$f);
	}
    rmdir($path);
}

function createGroupFolderOnServer($groupName) {
	// Crea la cartella sul server esterno (stesso flusso dell'upload delle songs)
	// Path: /var/www/vhosts/yourradio.org/httpdocs/player/[nome del gruppo minuscolo]
	$folderName = strtolower(trim($groupName));
	if(empty($folderName)) {
		return false;
	}
	
	$remotePath = "/var/www/vhosts/yourradio.org/httpdocs/player/" . $folderName;
	
	// Crea la directory direttamente (stesso metodo usato per le songs nell'API)
	// Verifica se la directory padre esiste o è accessibile
	$parentDir = dirname($remotePath);
	
	// Prova a creare la cartella direttamente (stesso approccio delle songs)
	if (!is_dir($remotePath)) {
		// Crea la directory se non esiste (stesso metodo dell'API songs)
		if (!mkdir($remotePath, 0755, true)) {
			error_log("Errore nella creazione della cartella per gruppo '{$groupName}': {$remotePath}");
			// Non blocchiamo la creazione del gruppo nel DB anche se la cartella non viene creata
			return false;
		}
	}
	
	return true;
}


function getUserByLogin($login,$pass){

	try {
	    $pdo = new PDO(DB_ENGINE . ":host=".DB_HOST .";dbname=" . DB_NAME, DB_USER, DB_PASS);
	        
	    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	} catch (PDOException $e) {
    	exit("Impossibile connettersi al database: " . $e->getMessage());
	}

	$query = "
	  SELECT *
	  FROM user
	  WHERE login = '".$login."' AND password = '".$pass."'
	  ";

  	$check = $pdo->prepare($query);
	//$check->bindParam(':login', $login, PDO::PARAM_STR);
  	$check->execute();
  	$user = $check->fetchAll(PDO::FETCH_ASSOC);

	return $user;

}

function dateByNow(){
	$date = weekdayByNumber(date('N')).", ".date('d')." ".monthByNumber(date('n'))." ".date('Y');
	return $date;
}


function weekdayByNumber($numberDay){
	switch ($numberDay) {
		case 0:
			return "Domenica";
			break;
		case 1:
			return "Lunedì";
			break;
		case 2:
			return "Martedì";
			break;
		case 3:
			return "Mercoledì";
			break;
		case 4:
			return "Giovedì";
			break;
		case 5:
			return "Venerdì";
			break;
		case 6:
			return "Sabato";
			break;
		case 7:
			return "Domenica";
			break;
	}
}

function monthByNumber($numberMonth){
	switch ($numberMonth) {
		case 1:
			return "Gennaio";
			break;
		case 2:
			return "Febbraio";
			break;
		case 3:
			return "Marzo";
			break;
		case 4:
			return "Aprile";
			break;
		case 5:
			return "Maggio";
			break;
		case 6:
			return "Giugno";
			break;
		case 7:
			return "Luglio";
			break;
		case 8:
			return "Agosto";
			break;
		case 9:
			return "Settembre";
			break;
		case 10:
			return "Ottobre";
			break;
		case 11:
			return "Novembre";
			break;
		case 12:
			return "Dicembre";
			break;
	}
}



function getAllGroupForGraph(){
	DB::init();
    $gruppi=Monitor::selectAllGroups();
    $list = '';
    foreach ($gruppi as &$g) {
        if($g['gr_nome']!=''){
            $list.="'".strtoupper($g['gr_nome'])."',";
        }
        
    }
    return substr($list,0,-1);
}

function getAllPlayersByStatusForGraph($status){
	DB::init();
    $players=Monitor::selectAllPlayersByStatusOrderByGroups();

    //print_r($players);
    if($status=='PERC'){$statusSearch=5;}
	if($status=='GRUPPI'){$statusSearch=5;}
    if($status=='ON'){$statusSearch=1;}
    if($status=='OFF'){$statusSearch=2;}
    if($status=='OFS'){$statusSearch=3;}

    $ultimaOra=time()-(60*60); // 1 ora fa
    $ultimaOraIeri=time()-((60*60)*24); // 24 ore fa (un giorno)
    
    // Debug temporaneo
    $debugStats = ['on' => 0, 'off' => 0, 'ofs' => 0, 'null' => 0, 'sample_data' => []];
    $listaTotGruppi='';
    $ultimoGruppoNome='';
    $listaTotGruppi='';
    $totGruppo=0;
    $totStatusOn=0;
    $totStatusOff=0;
    $totStatusOfs=0;
    $totPlayers=0;
    $totGruppoOn=0;
    $totGruppoOff=0;
    $totGruppoOfs=0;
    $listaTotGruppoOn='';
    $listaTotGruppoOff='';
    $listaTotGruppoOfs='';
    $listaGruppi='';

    foreach ($players as &$p) {
    	$totPlayers++;
    	// Logica identica a monitor.php (inc/ajax.php riga 279-280)
    	// Usa esattamente la stessa logica senza controlli aggiuntivi
    	$ultimaData = isset($p['pl_player_ultimaData']) ? $p['pl_player_ultimaData'] : 0;
    	
    	// Logica esatta come in inc/ajax.php riga 279-280
    	if($ultimaData < $ultimaOra){
    		$status = 2; // OFF (rosso) - ultimo ping più vecchio di 1 ora
    	} else {
    		$status = 1; // ON (verde) - ultimo ping recente
    	}
    	if($ultimaData < $ultimaOraIeri){
    		$status = 3; // OUT OF SERVICE (grigio/arancione) - ultimo ping più vecchio di 24 ore
    	}
    	
    	// Debug temporaneo
    	if($status == 1) $debugStats['on']++;
    	if($status == 2) $debugStats['off']++;
    	if($status == 3) $debugStats['ofs']++;
    	if($ultimaData == 0 || $ultimaData === null) $debugStats['null']++;
    	if(count($debugStats['sample_data']) < 5){
    		$diffOra = $ultimaOra - $ultimaData;
    		$diffGiorno = $ultimaOraIeri - $ultimaData;
    		$debugStats['sample_data'][] = [
    			'nome' => $p['pl_nome'],
    			'ultimaData' => $ultimaData,
    			'ultimaData_formatted' => $ultimaData > 0 ? date('Y-m-d H:i:s', $ultimaData) : 'NULL/0',
    			'ultimaOra' => $ultimaOra,
    			'ultimaOraIeri' => $ultimaOraIeri,
    			'time_now' => time(),
    			'diff_ora_sec' => $diffOra,
    			'diff_giorno_sec' => $diffGiorno,
    			'status' => $status,
    			'check_off' => ($ultimaData < $ultimaOra) ? 'YES' : 'NO',
    			'check_ofs' => ($ultimaData < $ultimaOraIeri) ? 'YES' : 'NO'
    		];
    	}

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
    $listaTotGruppoOn.=$totGruppoOn.",";
    $listaTotGruppoOff.=$totGruppoOff.",";
    $listaTotGruppoOfs.=$totGruppoOfs.",";

    	$totRes = array(
    		"PERC-ON"=>round(($totStatusOn*100)/$totPlayers),
    		"PERC-OFF"=>round(($totStatusOff*100)/$totPlayers),
    		"PERC-OFS"=>round(($totStatusOfs*100)/$totPlayers),
    		"GRUPPI"=>strtoupper(substr($listaGruppi,0,-1)),
    		"ON"=>substr($listaTotGruppoOn,0,-1),
    		"OFF"=>substr($listaTotGruppoOff,0,-1),
    		"OFS"=>substr($listaTotGruppoOfs,0,-1),
    		"DEBUG"=>$debugStats // Debug temporaneo
    	);
    	return $totRes;
	    
}


?>