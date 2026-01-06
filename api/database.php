<?php
/**
 * Database Classes per API YourRadio
 */

class DB
{
    public static $db;
   
    public static function init()
    {
        try {
            // Le API sono installate su yourradio.org, quindi il database è locale
            // DB_HOST dovrebbe essere "localhost" quando le API sono sul server
            $dbHost = DB_HOST;
            $db_config = DB_ENGINE . ":host=".$dbHost . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            
            error_log("DB INIT: Tentativo connessione a " . $dbHost . " | Database: " . DB_NAME);
            
            $pdo = new PDO($db_config, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$db = $pdo;
            
            error_log("DB INIT: Connessione riuscita a " . $dbHost);
        } catch (PDOException $e) {
            error_log("DB INIT ERROR: " . $e->getMessage() . " | Host: " . DB_HOST . " | DB: " . DB_NAME);
            error_log("DB INIT ERROR CODE: " . $e->getCode());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
}

class Player extends DB
{
    public static function selectPlayerByID($id){
        $query = "SELECT * FROM players JOIN gruppi ON(pl_idGruppo=gr_id) WHERE pl_id=:id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }
}

class Jingles extends DB
{
    public static function selectAllJinglesByGruppoId($id){
        $query = "SELECT * FROM jingle WHERE jingle_gr_id=:id ORDER BY jingle_attivo DESC";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }
    
    public static function selectJingleById($id){
        $query = "SELECT j.*, g.gr_nome FROM jingle j LEFT JOIN gruppi g ON j.jingle_gr_id = g.gr_id WHERE j.jingle_id=:id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }
}

class Rubriche extends DB
{
    public static function selectAllRubricheByGruppoId($id){
        $query = "SELECT * FROM `rubriche` LEFT JOIN `rubricagruppo` ON (rg_rub_id=rub_id AND rg_gr_id=:id) WHERE `rub_categoria` LIKE '%speciali%' ORDER BY 'rub_titolo'";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }
}

class Spot extends DB
{
    public static function selectAllSpotNetByGruppoId($id){
        $query = "SELECT * FROM `spot` WHERE `spot_gr_id` = :id ORDER BY spot_al DESC";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }

    public static function selectAllSpotLocByGruppoId($id){
        $query = "SELECT * FROM `spot_loc` WHERE `spot_loc_gr_id` = :id ORDER BY spot_loc_nome ASC";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }
}

class Songs extends DB
{
    public static function updateSongById($song)
    {
        $sett = [];
        $songId = isset($song['sg_id']) ? (int)$song['sg_id'] : 0;
        $params = [':sg_id' => $songId];
        $formatIds = null;
        
        // Estrai i format se presenti (anche se è un array vuoto, aggiorna le relazioni)
        if (isset($song['formats'])) {
            $formatIds = $song['formats'];
            unset($song['formats']);
        }
        
        foreach($song as $key => $value){
            if($key != 'job' && $key != 'formAction' && $key != 'sg_id' && $key != 'formats'){
                // Gestisci sg_file: se è vuoto, '0' o non presente, non includerlo (per evitare violazioni di vincolo UNIQUE)
                if ($key === 'sg_file' && ($value === '' || $value === '0' || $value === 0)) {
                    continue; // Salta questo campo
                }
                
                // Gestisci sg_filesize: se non presente o 0, non includerlo
                if ($key === 'sg_filesize' && ($value === '' || $value === '0' || $value === 0)) {
                    continue; // Salta questo campo
                }
                
                $sett[] = "`".$key."` = :".$key;
                $params[':'.$key] = $value;
            }
        }
        
        if(count($sett) > 0) {
            $query = "UPDATE `songs` SET ".implode(', ', $sett)." WHERE `songs`.`sg_id` = :sg_id";
            $st = self::$db->prepare($query);
            $st->execute($params);
        }
        
        // Aggiorna sempre i format (anche se è un array vuoto, elimina tutte le relazioni)
        // Usa $songId invece di $song['sg_id'] per sicurezza
        if ($formatIds !== null) {
            self::updateSongFormats($songId, $formatIds);
        }
        
        return true;
    }
    
    public static function createSong($song)
    {
        $formatIds = null;
        
        // Estrai i format se presenti
        if (isset($song['formats'])) {
            $formatIds = $song['formats'];
            unset($song['formats']);
        }
        
        // Prepara i campi per l'INSERT
        $fields = array();
        $values = array();
        $params = array();
        
        // Campi obbligatori con valori di default
        $defaultFields = array(
            'sg_attivo' => 1,
            'sg_titolo' => '',
            'sg_artista' => '',
            'sg_anno' => 0,
            'sg_artista2' => '',
            'sg_artista3' => '',
            'sg_diritti' => 0,
            'sg_autori' => '',
            'sg_casaDiscografica' => '',
            'sg_etichetta' => '',
            'sg_umoreId' => 0,
            'sg_nazione' => ''
            // Non includere sg_file e sg_filesize nei default - verranno aggiunti solo se presenti nei dati
        );
        
        // Unisci i valori di default con i dati forniti
        $songData = array_merge($defaultFields, $song);
        
        // Rimuovi campi che non devono essere inseriti
        unset($songData['job']);
        unset($songData['formAction']);
        // Mantieni sg_id se viene passato esplicitamente (per import)
        // unset($songData['sg_id']); // Commentato per permettere l'inserimento con sg_id specifico
        unset($songData['formats']);
        
        // Gestisci sg_file: se è vuoto, '0' o non presente, non includerlo (per evitare violazioni di vincolo UNIQUE)
        if (isset($songData['sg_file']) && ($songData['sg_file'] === '' || $songData['sg_file'] === '0' || $songData['sg_file'] === 0)) {
            unset($songData['sg_file']);
        }
        
        // Gestisci sg_filesize: se non presente o 0, non includerlo
        if (isset($songData['sg_filesize']) && ($songData['sg_filesize'] === '' || $songData['sg_filesize'] === '0' || $songData['sg_filesize'] === 0)) {
            unset($songData['sg_filesize']);
        }
        
        // Costruisci la query
        foreach($songData as $key => $value) {
            $fields[] = "`".$key."`";
            $values[] = ":".$key;
            $params[':'.$key] = $value;
        }
        
        $query = "INSERT INTO `songs` (".implode(', ', $fields).") VALUES (".implode(', ', $values).")";
        $st = self::$db->prepare($query);
        $st->execute($params);
        
        // Ottieni l'ID della song appena creata
        // Se sg_id è stato specificato, usalo, altrimenti usa lastInsertId
        $newId = isset($songData['sg_id']) ? (int)$songData['sg_id'] : self::$db->lastInsertId();
        
        // Aggiungi i format se forniti
        if ($formatIds !== null && $newId > 0) {
            self::updateSongFormats($newId, $formatIds);
        }
        
        return $newId;
    }
    
    public static function uploadDataFile($sg_id, $sg_file, $sg_filesize)
    {
        $query = "UPDATE `songs` SET `sg_filesize` = :filesize WHERE `songs`.`sg_id` = :id";
        $st = self::$db->prepare($query);
        $st->execute([
            ':filesize' => $sg_filesize,
            ':id' => $sg_id
        ]);
        return true;
    }

    public static function selectSongById($id)
    {
        // Query con JOIN per recuperare i format associati
        $query = "SELECT s.* 
                  FROM songs s 
                  LEFT JOIN song_format sf ON sf.id_song = s.sg_id 
                  WHERE s.sg_id=:id 
                  GROUP BY s.sg_id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        $song = $st->fetchAll();
        
        // Recupera i format associati separatamente
        if (!empty($song)) {
            $queryFormats = "SELECT sf.id_format 
                            FROM song_format sf 
                            WHERE sf.id_song = :id";
            $stFormats = self::$db->prepare($queryFormats);
            $stFormats->execute([':id' => $id]);
            $formats = $stFormats->fetchAll();
            
            // Aggiungi i format come array alla song
            $song[0]['formats'] = array();
            foreach ($formats as $format) {
                $song[0]['formats'][] = (int)$format['id_format'];
            }
        }
        
        return $song;
    }

    public static function deleteById($id)
    {
        $query = "DELETE FROM `songs` WHERE `songs`.`sg_id` = :id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return true;
    }

    public static function selectAll($filter)
    {
        $where = ['sg_id > 0'];
        $params = [];

        if(isset($filter['attivo']) && $filter['attivo'] == 1){
            $where[] = "sg_attivo = 1";
        }
        if(isset($filter['attivo']) && $filter['attivo'] == 2){
            $where[] = "sg_attivo = 0";
        }
        // Usa JOIN con song_format per filtrare i format (non usa più frmt_binario)
        $joinFormat = '';
        if(isset($filter['formats']) && is_array($filter['formats']) && count($filter['formats']) > 0){
            // Filtra per più format - mostra tutte le songs che appartengono a QUALSIASI format selezionato
            $formatIds = array();
            foreach($filter['formats'] as $f) {
                $fid = (int)$f;
                if($fid > 0) {
                    $formatIds[] = $fid;
                }
            }
            if(count($formatIds) > 0) {
                // Usa JOIN per filtrare le songs che hanno almeno uno dei format selezionati
                // DISTINCT evita duplicati se una song ha più format associati
                $joinFormat = ' JOIN song_format sf ON sf.id_song = s.sg_id';
                $placeholders = array();
                foreach($formatIds as $idx => $fid) {
                    $key = ':format'.$idx;
                    $placeholders[] = $key;
                    $params[$key] = $fid;
                }
                // IN restituisce tutte le songs che appartengono a QUALSIASI format selezionato (OR logico)
                $where[] = 'sf.id_format IN ('.implode(',', $placeholders).')';
            }
        } elseif(isset($filter['format']) && $filter['format'] > 0){
            // Singolo format (compatibilità con vecchio sistema)
            $joinFormat = ' JOIN song_format sf ON sf.id_song = s.sg_id';
            $where[] = 'sf.id_format = :format';
            $params[':format'] = $filter['format'];
        }
        if(isset($filter['nazionalita']) && $filter['nazionalita'] == 1){
            $where[] = "sg_nazione = 'Italiano'";
        }
        if(isset($filter['nazionalita']) && $filter['nazionalita'] == 2){
            $where[] = "sg_nazione = 'Straniero'";
        }
        if(isset($filter['strategia']) && $filter['strategia'] > 0){
            $where[] = "sg_strategia = :strategia";
            $params[':strategia'] = $filter['strategia'];
        }
        if(isset($filter['sex']) && $filter['sex'] != ''){
            $where[] = "sg_sex = :sex";
            $params[':sex'] = $filter['sex'];
        }
        if(isset($filter['umore']) && $filter['umore'] != ''){
            $where[] = "sg_umoreId = :umore";
            $params[':umore'] = $filter['umore'];
        }
        if(isset($filter['ritmo']) && $filter['ritmo'] > 0){
            $where[] = "sg_ritmoId = :ritmo";
            $params[':ritmo'] = $filter['ritmo'];
        }
        if(isset($filter['energia']) && $filter['energia'] > 0){
            $where[] = "sg_energia = :energia";
            $params[':energia'] = $filter['energia'];
        }
        if(isset($filter['anno']) && $filter['anno'] > 0){
            $where[] = "sg_anno = :anno";
            $params[':anno'] = $filter['anno'];
        }
        if(isset($filter['periodo']) && $filter['periodo'] > 0){
            $where[] = "sg_periodoId = :periodo";
            $params[':periodo'] = $filter['periodo'];
        }
        if(isset($filter['genere']) && $filter['genere'] > 0){
            $where[] = "sg_genereId = :genere";
            $params[':genere'] = $filter['genere'];
        }
        // Gestisci diritti: se è impostato (anche se è 0), applica il filtro
        // Se non è impostato o è "*", non filtrare (mostra tutti)
        if(isset($filter['diritti'])){
            $where[] = "sg_diritti = :diritti";
            $params[':diritti'] = (int)$filter['diritti'];
        }

        // Usa DISTINCT per evitare duplicati quando una song ha più format associati
        $query = "SELECT DISTINCT s.`sg_id`, s.`sg_artista`, s.`sg_titolo`, s.`sg_anno`, s.`sg_attivo` 
                  FROM `songs` s".$joinFormat." 
                  WHERE ".implode(' AND ', $where)." 
                  ORDER BY s.sg_artista";
        $st = self::$db->prepare($query);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function selectAllFormats()
    {
        $query = "SELECT * FROM `format` WHERE `frmt_active` = '1' ORDER BY `frmt_nome`";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st->fetchAll();
    }
    
    public static function selectAllFormatsAll()
    {
        try {
            // Prima recupera tutti i format con tutti i campi necessari
            $query = "SELECT * FROM `format` ORDER BY `frmt_nome`";
            $st = self::$db->prepare($query);
            if (!$st) {
                $errorInfo = self::$db->errorInfo();
                error_log("Errore preparazione query selectAllFormatsAll: " . $errorInfo[2]);
                return array();
            }
            
            $result = $st->execute();
            if (!$result) {
                $errorInfo = $st->errorInfo();
                error_log("Errore esecuzione query selectAllFormatsAll: " . $errorInfo[2]);
                return array();
            }
            
            $formats = $st->fetchAll();
            
            // Per ogni format, conta le song associate
            if (is_array($formats)) {
                foreach ($formats as $key => $format) {
                    $formatId = $format['frmt_id'];
                    
                    // Conta le song associate a questo format (totali, attive e non attive)
                    $countQuery = "SELECT 
                        COUNT(DISTINCT sf.id_song) as count_total,
                        COUNT(DISTINCT CASE WHEN s.sg_attivo = 1 THEN sf.id_song END) as count_active,
                        COUNT(DISTINCT CASE WHEN s.sg_attivo = 0 THEN sf.id_song END) as count_inactive
                    FROM `song_format` sf
                    LEFT JOIN `songs` s ON sf.id_song = s.sg_id
                    WHERE sf.id_format = :format_id";
                    $countSt = self::$db->prepare($countQuery);
                    if ($countSt) {
                        $countSt->execute(array(':format_id' => $formatId));
                        $countResult = $countSt->fetch();
                        $formats[$key]['songs_count'] = isset($countResult['count_total']) ? (int)$countResult['count_total'] : 0;
                        $formats[$key]['songs_active'] = isset($countResult['count_active']) ? (int)$countResult['count_active'] : 0;
                        $formats[$key]['songs_inactive'] = isset($countResult['count_inactive']) ? (int)$countResult['count_inactive'] : 0;
                    } else {
                        $formats[$key]['songs_count'] = 0;
                        $formats[$key]['songs_active'] = 0;
                        $formats[$key]['songs_inactive'] = 0;
                    }
                    
                    // Assicurati che i campi siano sempre presenti
                    $formats[$key]['frmt_descrizione'] = isset($format['frmt_descrizione']) ? $format['frmt_descrizione'] : '';
                    $formats[$key]['frmt_permettiRipetizioneArtista'] = isset($format['frmt_permettiRipetizioneArtista']) ? $format['frmt_permettiRipetizioneArtista'] : 0;
                }
            } else {
                $formats = array();
            }
            
            return $formats;
        } catch (Exception $e) {
            error_log("Eccezione in selectAllFormatsAll: " . $e->getMessage());
            return array();
        }
    }
    
    public static function getSongFormats($songId)
    {
        $query = "SELECT id_format FROM song_format WHERE id_song = :song_id";
        $st = self::$db->prepare($query);
        $st->execute([':song_id' => $songId]);
        $formats = $st->fetchAll();
        $result = array();
        foreach ($formats as $format) {
            $result[] = (int)$format['id_format'];
        }
        return $result;
    }
    
    public static function updateSongFormats($songId, $formatIds)
    {
        // Elimina tutti i format esistenti per questa song
        $queryDelete = "DELETE FROM song_format WHERE id_song = :song_id";
        $stDelete = self::$db->prepare($queryDelete);
        $stDelete->execute([':song_id' => $songId]);
        
        // Inserisce i nuovi format (anche se l'array è vuoto, le relazioni sono già state eliminate)
        if (is_array($formatIds) && count($formatIds) > 0) {
            $queryInsert = "INSERT INTO song_format (id_song, id_format) VALUES (:song_id, :format_id)";
            $stInsert = self::$db->prepare($queryInsert);
            foreach ($formatIds as $formatId) {
                $formatIdInt = (int)$formatId;
                if ($formatIdInt > 0) {
                    $stInsert->execute([
                        ':song_id' => $songId,
                        ':format_id' => $formatIdInt
                    ]);
                }
            }
        }
        return true;
    }
    
    public static function createFormat($formatData)
    {
        try {
            // Pulisci e valida il nome (solo maiuscolo, lettere, numeri, spazi, max 16 caratteri)
            $nome = isset($formatData['frmt_nome']) ? strtoupper(trim($formatData['frmt_nome'])) : '';
            $nome = preg_replace('/[^A-Z0-9\s]/', '', $nome); // Rimuovi caratteri non validi
            
            if (empty($nome)) {
                throw new Exception("Il nome del format è obbligatorio e deve contenere solo lettere, numeri e spazi");
            }
            
            // Limita a 16 caratteri
            if (strlen($nome) > 16) {
                $nome = substr($nome, 0, 16);
            }
            
            // Valida la descrizione (max 25 caratteri)
            $descrizione = isset($formatData['frmt_descrizione']) ? trim($formatData['frmt_descrizione']) : '';
            if (empty($descrizione)) {
                throw new Exception("La descrizione è obbligatoria");
            }
            if (strlen($descrizione) > 25) {
                $descrizione = substr($descrizione, 0, 25);
            }
            
            // Genera l'MD5 del nome
            $nomeCript = md5($nome);
            
            // Inserisci nel database
            $query = "INSERT INTO `format` SET 
                      `frmt_nome` = :nome,
                      `frmt_descrizione` = :descrizione,
                      `frmt_nome_cript` = :nome_cript,
                      `frmt_active` = 0";
            
            $st = self::$db->prepare($query);
            $result = $st->execute(array(
                ':nome' => $nome,
                ':descrizione' => $descrizione,
                ':nome_cript' => $nomeCript
            ));
            
            if (!$result) {
                $errorInfo = $st->errorInfo();
                throw new Exception("Errore database: " . $errorInfo[2]);
            }
            
            $newId = self::$db->lastInsertId();
            return $newId;
        } catch (Exception $e) {
            error_log("Errore in createFormat: " . $e->getMessage());
            throw $e;
        }
    }
    
    public static function updateFormat($formatId, $formatData)
    {
        try {
            $formatId = (int)$formatId;
            if ($formatId <= 0) {
                throw new Exception("ID format non valido");
            }
            
            // Valida la descrizione (max 25 caratteri)
            $descrizione = isset($formatData['frmt_descrizione']) ? trim($formatData['frmt_descrizione']) : '';
            if (empty($descrizione)) {
                throw new Exception("La descrizione è obbligatoria");
            }
            if (strlen($descrizione) > 25) {
                $descrizione = substr($descrizione, 0, 25);
            }
            
            // Prepara i campi da aggiornare
            $fields = array();
            $params = array(':id' => $formatId);
            
            $fields[] = "`frmt_descrizione` = :descrizione";
            $params[':descrizione'] = $descrizione;
            
            if (isset($formatData['frmt_active'])) {
                $fields[] = "`frmt_active` = :active";
                $params[':active'] = (int)$formatData['frmt_active'];
            }
            
            if (isset($formatData['frmt_permettiRipetizioneArtista'])) {
                $fields[] = "`frmt_permettiRipetizioneArtista` = :permetti_ripetizione";
                $params[':permetti_ripetizione'] = (int)$formatData['frmt_permettiRipetizioneArtista'];
            }
            
            if (empty($fields)) {
                throw new Exception("Nessun campo da aggiornare");
            }
            
            // Aggiorna nel database
            $query = "UPDATE `format` SET " . implode(', ', $fields) . " WHERE `frmt_id` = :id";
            
            $st = self::$db->prepare($query);
            $result = $st->execute($params);
            
            if (!$result) {
                $errorInfo = $st->errorInfo();
                throw new Exception("Errore database: " . $errorInfo[2]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Errore in updateFormat: " . $e->getMessage());
            throw $e;
        }
    }
    
    public static function deleteFormat($formatId)
    {
        try {
            $formatId = (int)$formatId;
            if ($formatId <= 0) {
                throw new Exception("ID format non valido");
            }
            
            // Elimina le relazioni con le songs
            $queryDeleteRelations = "DELETE FROM `song_format` WHERE `id_format` = :id";
            $stDelete = self::$db->prepare($queryDeleteRelations);
            $stDelete->execute(array(':id' => $formatId));
            
            // Elimina il format
            $query = "DELETE FROM `format` WHERE `frmt_id` = :id";
            $st = self::$db->prepare($query);
            $result = $st->execute(array(':id' => $formatId));
            
            if (!$result) {
                $errorInfo = $st->errorInfo();
                throw new Exception("Errore database: " . $errorInfo[2]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Errore in deleteFormat: " . $e->getMessage());
            throw $e;
        }
    }
}

class Gruppi extends DB
{
    public static function selectCampagneByIdGroup($id)
    {
        $query = "SELECT * FROM `ds_campagne` WHERE `ds_camp_gruppo_id` = :id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }

    public static function selectAllActive()
    {
        $query = "SELECT `gr_id`, `gr_nome` FROM `gruppi` WHERE `gr_active` = '1'";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st->fetchAll();
    }

    public static function selectAllRss()
    {
        $query = "SELECT `rss_id`, `rss_nome` FROM `rss` WHERE `rss_active` = '1'";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st->fetchAll();
    }

    public static function selectAll()
    {
        $query = "SELECT gr.gr_id, gr.gr_active, gr.gr_nome, COUNT(pl.pl_id) as tot_player 
                  FROM players AS pl 
                  RIGHT JOIN gruppi AS gr ON(pl.pl_idGruppo=gr.gr_id) 
                  WHERE gr.gr_id>0 AND gr.gr_nome<>'' 
                  GROUP BY gr.gr_nome 
                  ORDER BY gr.gr_nome ASC";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st->fetchAll();
    }

    public static function selectGruppoById($id)
    {
        $query = "SELECT gr.gr_id, gr.gr_active, gr.gr_nome, gr.gr_dataCreazione, rss.rss_id, rss.rss_nome, COUNT(pl.pl_id) as tot_player 
                  FROM players AS pl 
                  LEFT JOIN gruppi AS gr ON(pl.pl_idGruppo=gr.gr_id) 
                  LEFT JOIN rss as rss ON(gr.gr_ds_rss=rss.rss_id)
                  WHERE gr.gr_id=:id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }

    public static function getCheckRelatedSubGruppoByIdPlayer($idPlayer, $idSubGruppo)
    {
        $query = "SELECT COUNT(plsgr_sgr_id) as checked FROM `player_subgruppo` WHERE `plsgr_pl_id` = :player_id AND `plsgr_sgr_id` = :subgruppo_id";
        error_log("QUERY CHECK RELATION: " . $query);
        error_log("PARAMS CHECK: player_id = " . $idPlayer . ", subgruppo_id = " . $idSubGruppo);
        $st = self::$db->prepare($query);
        $st->execute([
            ':player_id' => $idPlayer,
            ':subgruppo_id' => $idSubGruppo
        ]);
        $result = $st->fetchAll();
        error_log("RESULT CHECK RELATION: " . json_encode($result));
        return $result;
    }

    public static function selectSubGruppoByIdPlayer($id)
    {
        $query = "SELECT sgr.sgr_id, sgr.sgr_nome FROM sub_gruppi AS sgr 
                  JOIN gruppi AS gr ON(sgr.sgr_gr_id=gr.gr_id) 
                  JOIN players AS pl ON(pl.pl_idGruppo=gr.gr_id)
                  WHERE pl.pl_id = :id";
        error_log("QUERY SELECT SUBGRUPPI BY PLAYER: " . $query);
        error_log("PARAMS SELECT: id = " . $id);
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        $result = $st->fetchAll();
        error_log("RESULT SELECT SUBGRUPPI: " . json_encode($result));
        return $result;
    }

    public static function selectSubGruppoById($id)
    {
        $query = "SELECT sgr.sgr_id, sgr.sgr_nome FROM sub_gruppi AS sgr 
                  JOIN gruppi AS gr ON(sgr.sgr_gr_id=gr.gr_id) 
                  WHERE gr.gr_id = :id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }

    public static function selectTotPlayersSottoGruppoById($id)
    {
        $query = "SELECT COUNT(plsgr.plsgr_pl_id) as tot_player FROM player_subgruppo AS plsgr WHERE plsgr.plsgr_sgr_id = :id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }

    public static function selectAllPlayersSottoGruppoById($id)
    {
        $query = "SELECT pl.pl_id, pl.pl_active, pl.pl_nome, pl.pl_player_ultimaDataEstesa 
                  FROM players pl 
                  JOIN player_subgruppo plsgr ON pl.pl_id = plsgr.plsgr_pl_id 
                  WHERE plsgr.plsgr_sgr_id = :id 
                  ORDER BY pl.pl_nome";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }

    public static function selectAllNetworks()
    {
        $query = "SELECT * FROM `networks` ORDER BY `id` ASC";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st->fetchAll();
    }
    
    public static function updatePlayerSubgruppi($playerId, $subgruppiIds)
    {
        try {
            error_log("=== UPDATE PLAYER SUBGRUPPI ===");
            error_log("Player ID: " . $playerId);
            error_log("Subgruppi IDs ricevuti: " . json_encode($subgruppiIds));
            
            // Prima elimina tutte le relazioni esistenti per questo player
            $deleteQuery = "DELETE FROM `player_subgruppo` WHERE `plsgr_pl_id` = :player_id";
            error_log("QUERY DELETE: " . $deleteQuery);
            error_log("PARAMS DELETE: player_id = " . $playerId);
            $deleteSt = self::$db->prepare($deleteQuery);
            $deleteResult = $deleteSt->execute([':player_id' => $playerId]);
            $rowsDeleted = $deleteSt->rowCount();
            error_log("DELETE RESULT: rows deleted = " . $rowsDeleted);
            
            // Poi inserisci le nuove relazioni
            if (!empty($subgruppiIds) && is_array($subgruppiIds)) {
                $insertQuery = "INSERT INTO `player_subgruppo` (`plsgr_pl_id`, `plsgr_sgr_id`) VALUES (:player_id, :subgruppo_id)";
                error_log("QUERY INSERT: " . $insertQuery);
                $insertSt = self::$db->prepare($insertQuery);
                
                $insertedCount = 0;
                foreach ($subgruppiIds as $sgrId) {
                    $sgrId = intval($sgrId);
                    if ($sgrId > 0) {
                        $insertParams = [
                            ':player_id' => $playerId,
                            ':subgruppo_id' => $sgrId
                        ];
                        error_log("INSERT PARAMS: player_id = " . $playerId . ", subgruppo_id = " . $sgrId);
                        $insertResult = $insertSt->execute($insertParams);
                        if ($insertResult) {
                            $insertedCount++;
                            error_log("INSERT SUCCESS per subgruppo " . $sgrId);
                        } else {
                            $errorInfo = $insertSt->errorInfo();
                            error_log("INSERT ERROR per subgruppo " . $sgrId . ": " . json_encode($errorInfo));
                        }
                    }
                }
                error_log("INSERT RESULT: " . $insertedCount . " relazioni inserite");
            } else {
                error_log("Nessun subgruppo da inserire (array vuoto o non valido)");
            }
            
            // Verifica finale: leggi le relazioni salvate
            $verifyQuery = "SELECT `plsgr_pl_id`, `plsgr_sgr_id` FROM `player_subgruppo` WHERE `plsgr_pl_id` = :player_id";
            $verifySt = self::$db->prepare($verifyQuery);
            $verifySt->execute([':player_id' => $playerId]);
            $savedRelations = $verifySt->fetchAll();
            error_log("VERIFICA FINALE - Relazioni salvate nel DB: " . json_encode($savedRelations));
            error_log("=== FINE UPDATE PLAYER SUBGRUPPI ===");
            
            return true;
        } catch (Exception $e) {
            error_log("Errore in updatePlayerSubgruppi: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public static function selectAllPlayersGruppoById($id)
    {
        $query = "SELECT pl_id, pl_active, pl_nome, pl_player_ultimaDataEstesa FROM players WHERE pl_idGruppo = :id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }

    public static function updateGruppo($gruppo)
    {
        $st = self::$db->prepare(
            "UPDATE `gruppi` set
            gr_active = :active,
            gr_nome = :nome,
            gr_ds_rss = :rss
            WHERE gr_id = :id"
        );
        $st->execute([
            ':active' => $gruppo['active'],
            ':nome' => $gruppo['nome'],
            ':rss' => $gruppo['rss_id'],
            ':id' => $gruppo['groupId']
        ]);
        return $gruppo['groupId'];
    }

    public static function createGruppo($nome)
    {
        // Assicura che il nome sia maiuscolo
        $nome = strtoupper(trim($nome));
        $st = self::$db->prepare(
            "INSERT INTO `gruppi` set 
            gr_active = '0',
            gr_nome = :nome,
            gr_dataCreazione = :dataCreazione,
            gr_jingle_canzone = '0',
            gr_sel_top40 = '0',
            gr_format_ok = '1',
            gr_format = '0',
            gr_format_new = '0',
            gr_format_def_new = '0',
            gr_format_def = '0',
            gr_format_fascia1 = '0',
            gr_format_fascia2 = '0',
            gr_format_fascia3 = '0',
            gr_format_fascia4 = '0',
            gr_format_fascia5 = '0',
            gr_idformat_def = '0',
            gr_idformat_fascia1 = '0',
            gr_idformat_fascia2 = '0',
            gr_idformat_fascia3 = '0',
            gr_idformat_fascia4 = '0',
            gr_idformat_fascia5 = '0',
            gr_rotator_idformat = '0',
            gr_rotator_format = '16777216',
            gr_rotator_song = '4',
            gr_time = '0',
            gr_orariPreferiti = '111111',
            gr_fascePreferite = '1111111111111',
            gr_ds_active = '1',
            gr_ds_template = '1',
            gr_ds_time = '1',
            gr_ds_campagna_id = '0',
            gr_ds_logo = NULL,
            gr_ds_sottologo = NULL,
            gr_ds_sfondo = NULL,
            gr_ds_rss = NULL,
            gr_server_file = 'http://www.yourradio.org',
            gr_nat_port = '',
            gr_abilitaPassaggi = '0'"
        );
        $st->execute([
            ':nome' => $nome,
            ':dataCreazione' => date("Y-m-d H:i:s")
        ]);
        $query = "SELECT * FROM gruppi ORDER BY gr_id DESC LIMIT 1";
        $st = self::$db->prepare($query);
        $st->execute();
        $res = $st->fetch();
        return $res['gr_id'];
    }

    public static function deleteGruppoById($id)
    {
        // Prima cancella i sottogruppi e i collegamenti ai player
        $query = "DELETE FROM `player_subgruppo` WHERE `plsgr_sgr_id` IN (SELECT `sgr_id` FROM `sub_gruppi` WHERE `sgr_gr_id` = :id)";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        
        $query = "DELETE FROM `sub_gruppi` WHERE `sgr_gr_id` = :id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        
        // Cancella i players del gruppo
        $query = "DELETE FROM `players` WHERE `pl_idGruppo` = :id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        
        // Cancella il gruppo
        $query = "DELETE FROM `gruppi` WHERE `gr_id` = :id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        
        return true;
    }
}

class Login extends DB
{
    public static function selectByLogin($userLogin)
    {
        $st = self::$db->prepare(
            "SELECT * FROM user 
            WHERE login = :login AND password = :password"
        );
        $st->execute([
            ':login' => strval($userLogin['login']),
            ':password' => strval(md5($userLogin['password']))
        ]);
        return $st->fetchAll();
    }

    public static function addLastLoginById($id)
    {
        $query = "UPDATE `user` set `user`.`ultimoAccesso` = :ultimoAccesso WHERE `user`.`id` = :id";
        $st = self::$db->prepare($query);
        $st->execute([
            ':ultimoAccesso' => date("Y-m-d H:i:s"),
            ':id' => $id
        ]);
        return true;
    }
}

class Utenti extends DB
{
    public static function selectUtenti()
    {
        $query = "SELECT id, active, nome, mail, rete_id, ultimoAccesso, dataCreazione, gr_nome, gr_id FROM user LEFT JOIN gruppi ON(rete_id=gr_id) ORDER BY active DESC, rete_id ASC";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st->fetchAll();
    }
    
    public static function selectUtenteById($id)
    {
        $query = "SELECT * FROM user LEFT JOIN gruppi ON (rete_id=gr_id) WHERE id=:id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }

    public static function changePassword($id, $newPass)
    {
        $query = "UPDATE `user` set `user`.`password` = :pass WHERE `user`.`id` = :id";
        $st = self::$db->prepare($query);
        $st->execute([
            ':pass' => $newPass,
            ':id' => $id
        ]);
        return true;
    }
    
    public static function updateUtente($id, $utente)
    {
        // Verifica se l'utente esiste
        $existing = self::selectUtenteById($id);
        if (empty($existing)) {
            return false;
        }
        
        // Prepara i dati per l'update
        $updateFields = array(
            'active' => isset($utente['active']) ? (int)$utente['active'] : 1,
            'nome' => isset($utente['nome']) ? $utente['nome'] : '',
            'indirizzo' => isset($utente['indirizzo']) ? $utente['indirizzo'] : null,
            'citta' => isset($utente['citta']) ? $utente['citta'] : '',
            'cap' => isset($utente['cap']) ? $utente['cap'] : null,
            'pro' => isset($utente['pro']) ? $utente['pro'] : null,
            'tel' => isset($utente['tel']) ? $utente['tel'] : null,
            'mail' => isset($utente['mail']) ? $utente['mail'] : '',
            'login' => isset($utente['login']) ? $utente['login'] : '',
            'permesso' => isset($utente['permesso']) ? (int)$utente['permesso'] : 1,
            'rete_id' => isset($utente['rete_id']) ? (int)$utente['rete_id'] : 0,
            'contractor' => isset($utente['contractor']) ? (int)$utente['contractor'] : 0,
            'role' => isset($utente['role']) ? (int)$utente['role'] : 0,
            'require_2fa' => isset($utente['require_2fa']) ? (int)$utente['require_2fa'] : 0,
            'gruppi_monitor' => isset($utente['gruppi_monitor']) ? $utente['gruppi_monitor'] : null
        );
        
        // Se la password è fornita, aggiornala
        if (isset($utente['password']) && !empty($utente['password'])) {
            // Se la password è in chiaro (password_plain flag), hashala in MD5
            if (isset($utente['password_plain']) && $utente['password_plain']) {
                $updateFields['password'] = md5($utente['password']);
            } else {
                // Altrimenti usa la password già hashata
                $updateFields['password'] = $utente['password'];
            }
        }
        
        $query = "UPDATE `user` SET 
            active = :active,
            nome = :nome,
            indirizzo = :indirizzo,
            citta = :citta,
            cap = :cap,
            pro = :pro,
            tel = :tel,
            mail = :mail,
            login = :login,
            permesso = :permesso,
            rete_id = :rete_id,
            contractor = :contractor,
            role = :role,
            require_2fa = :require_2fa,
            gruppi_monitor = :gruppi_monitor";
        
        if (isset($updateFields['password'])) {
            $query .= ", password = :password";
        }
        
        $query .= " WHERE id = :id";
        
        $params = array(
            ':active' => $updateFields['active'],
            ':nome' => $updateFields['nome'],
            ':indirizzo' => $updateFields['indirizzo'],
            ':citta' => $updateFields['citta'],
            ':cap' => $updateFields['cap'],
            ':pro' => $updateFields['pro'],
            ':tel' => $updateFields['tel'],
            ':mail' => $updateFields['mail'],
            ':login' => $updateFields['login'],
            ':permesso' => $updateFields['permesso'],
            ':rete_id' => $updateFields['rete_id'],
            ':contractor' => $updateFields['contractor'],
            ':role' => $updateFields['role'],
            ':require_2fa' => $updateFields['require_2fa'],
            ':gruppi_monitor' => $updateFields['gruppi_monitor'],
            ':id' => $id
        );
        
        if (isset($updateFields['password'])) {
            $params[':password'] = $updateFields['password'];
        }
        
        $st = self::$db->prepare($query);
        $result = $st->execute($params);
        if ($result) {
            return $id;
        } else {
            return false;
        }
    }
    
    public static function createUtente($utente)
    {
        // Usa sempre la data corrente per la creazione, ignora qualsiasi valore inviato dal client
        // Rimuovi dataCreazione dai dati inviati dal client per sicurezza
        if (isset($utente['dataCreazione'])) {
            unset($utente['dataCreazione']);
        }
        
        // Imposta il timezone se non è già impostato
        if (function_exists('date_default_timezone_get')) {
            $currentTimezone = date_default_timezone_get();
            if (!$currentTimezone || $currentTimezone == 'UTC') {
                date_default_timezone_set('Europe/Rome');
            }
        }
        
        // Genera la data corrente nel formato MySQL datetime
        $dataCreazione = date("Y-m-d H:i:s");
        
        // Verifica che la data sia valida (non può essere 0000-00-00 00:00:00)
        if (!$dataCreazione || $dataCreazione == '0000-00-00 00:00:00' || strpos($dataCreazione, '0000') === 0) {
            // Se la data non è valida, riprova
            $dataCreazione = date("Y-m-d H:i:s");
        }
        
        $query = "INSERT INTO `user` SET 
            active = :active,
            nome = :nome,
            indirizzo = :indirizzo,
            citta = :citta,
            cap = :cap,
            pro = :pro,
            tel = :tel,
            mail = :mail,
            login = :login,
            permesso = :permesso,
            rete_id = :rete_id,
            contractor = :contractor,
            role = :role,
            require_2fa = :require_2fa,
            gruppi_monitor = :gruppi_monitor,
            dataCreazione = :dataCreazione";
        
        if (isset($utente['password']) && !empty($utente['password'])) {
            $query .= ", password = :password";
        }
        
        $params = array(
            ':active' => isset($utente['active']) ? (int)$utente['active'] : 1,
            ':nome' => isset($utente['nome']) ? $utente['nome'] : '',
            ':indirizzo' => isset($utente['indirizzo']) ? $utente['indirizzo'] : null,
            ':citta' => isset($utente['citta']) ? $utente['citta'] : '',
            ':cap' => isset($utente['cap']) ? $utente['cap'] : null,
            ':pro' => isset($utente['pro']) ? $utente['pro'] : null,
            ':tel' => isset($utente['tel']) ? $utente['tel'] : null,
            ':mail' => isset($utente['mail']) ? $utente['mail'] : '',
            ':login' => isset($utente['login']) ? $utente['login'] : '',
            ':permesso' => isset($utente['permesso']) ? (int)$utente['permesso'] : 1,
            ':rete_id' => isset($utente['rete_id']) ? (int)$utente['rete_id'] : 0,
            ':contractor' => isset($utente['contractor']) ? (int)$utente['contractor'] : 0,
            ':role' => isset($utente['role']) ? (int)$utente['role'] : 0,
            ':require_2fa' => isset($utente['require_2fa']) ? (int)$utente['require_2fa'] : 0,
            ':gruppi_monitor' => isset($utente['gruppi_monitor']) ? $utente['gruppi_monitor'] : null,
            ':dataCreazione' => $dataCreazione
        );
        
        if (isset($utente['password']) && !empty($utente['password'])) {
            // Se la password è in chiaro (password_plain flag), hashala in MD5
            if (isset($utente['password_plain']) && $utente['password_plain']) {
                $params[':password'] = md5($utente['password']);
            } else {
                // Altrimenti usa la password già hashata
                $params[':password'] = $utente['password'];
            }
        }
        
        $st = self::$db->prepare($query);
        $st->execute($params);
        
        // Restituisci l'ID dell'utente appena creato
        $query = "SELECT * FROM user ORDER BY id DESC LIMIT 1";
        $st = self::$db->prepare($query);
        $st->execute();
        $res = $st->fetch();
        return $res['id'];
    }
    
    public static function deleteUtente($id)
    {
        $query = "DELETE FROM `user` WHERE `id` = :id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return true;
    }
}

class Monitor extends DB
{
    public static function selectPlayers($gr_nome)
    {
        $whereGruppo = '';
        $params = [];
        if($gr_nome != 'tutti'){
            $whereGruppo = " AND gr_nome = :gruppo";
            $params[':gruppo'] = $gr_nome;
        }
        $query = "SELECT pl_idGruppo, pl_id, pl_status, pl_nome, pl_player_ultimaData, pl_player_ultimaDataEstesa, pl_player_pc, pl_player_ip, pl_player_ver, gr_id, gr_nome, pl_mem_size, pl_mem_percent 
                  FROM players JOIN gruppi ON(pl_idGruppo=gr_id) 
                  WHERE pl_monitor=1".$whereGruppo." 
                  ORDER BY gr_nome, pl_player_ultimaData ASC";
        $st = self::$db->prepare($query);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function selectPlayerByID($id)
    {
        $query = "SELECT * FROM players JOIN gruppi ON(pl_idGruppo=gr_id) WHERE pl_id=:id";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    }

    public static function selectPingByPlayerID($player_id)
    {
        $query = "SELECT * FROM ping WHERE ping_ID_player=:id ORDER BY ping_ID DESC";
        $st = self::$db->prepare($query);
        $st->execute([':id' => $player_id]);
        return $st->fetchAll();
    }

    public static function selectAllGroups()
    {
        $query = "SELECT gr_nome FROM gruppi WHERE gr_active=1 ORDER BY gr_nome ASC";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st->fetchAll();
    }

    public static function selectAllPlayersByStatusOrderByGroups()
    {
        $query = "SELECT * FROM players JOIN gruppi ON(pl_idGruppo=gr_id) WHERE pl_monitor=1 AND gr_active=1 ORDER BY gr_nome, pl_player_ultimaData ASC";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st->fetchAll();
    }
}

