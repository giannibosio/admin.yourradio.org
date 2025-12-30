<?php

class DB
{
    public static $db;
   
    public static function init()
    {
        $db_config = DB_ENGINE . ":host=".DB_HOST . ";dbname=" . DB_NAME;
        $pdo = new PDO($db_config, DB_USER, DB_PASS);
        self::$db = $pdo;
    }
}

class VendorMapper extends DB
{
    public static function add($vendor)
    {
        $st = self::$db->prepare(
            "insert into vendors set
            first_name = :first_name,
            last_name = :last_name"
        );
        $st->execute(array(
            ':first_name' => $vendor->first_name,
            ':last_name' => $vendor->last_name
        ));
    }
}


class Player extends DB
{
    public static function selectPlayerByID($id){
        $query ="SELECT * FROM players JOIN gruppi ON(pl_idGruppo=gr_id) WHERE pl_id=".$id." ";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }


}

class Jingles extends DB
{
    public static function selectAllJinglesByGruppoId($id){
        $query ="SELECT * FROM jingle WHERE jingle_gr_id=".$id." ORDER BY jingle_attivo DESC ";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }


}

class Rubriche extends DB
{
    public static function selectAllRubricheByGruppoId($id){
        $query ="SELECT *  FROM `rubriche` LEFT JOIN `rubricagruppo` ON (rg_rub_id=rub_id AND rg_gr_id='".$id."') WHERE `rub_categoria` LIKE '%speciali%' ORDER BY 'rub_titolo' ";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

}


class Spot extends DB
{
    public static function selectAllSpotNetByGruppoId($id){
        $query ="SELECT *  FROM `spot` WHERE `spot_gr_id` = ".$id." ORDER BY spot_al DESC";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectAllSpotLocByGruppoId($id){
        $query ="SELECT *  FROM `spot_loc` WHERE `spot_loc_gr_id` = ".$id." ORDER BY spot_loc_nome ASC";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
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
        $st->execute(array(
            ':login' => strval($userLogin['login']),
            ':password' => strval(md5($userLogin['password']))
        ));
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function addLastLoginById($id)
    {
        
        $query ="UPDATE `user` set `user`.`ultimoAccesso` =  '".date("Y-m-d H:i:s")."' WHERE `user`.`id` = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

}

class Songs extends DB
{

    public static function updateSongById($song)
    {
        $sett = '';
        foreach($song as $key=>$value){
            if($key!='job' && $key!='formAction' && $key!='sg_id'){
               $sett.= "`".$key."` = '".$value."', "; 
            }
            
        }
        if(strlen($sett) > 0) {
            $sett=substr($sett,0,-2);
        }
        $query ="UPDATE `songs` SET ".$sett." WHERE `songs`.`sg_id` =".($song['sg_id'] ?? '');      
        $st = self::$db->prepare($query);
        $st->execute();
        //return $st -> fetchAll ( );
        //$st -> closeCursor ( ) ;
        return $query;
    }

    
    public static function uploadDataFile($sg_id,$sg_file,$sg_filesize)
    {
        $query ="UPDATE `songs` SET `sg_filesize` = '".$sg_filesize."' WHERE `songs`.`sg_id` =".$sg_id;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectSongById($id)
    {
        // Verifica che l'ID sia valido (numero intero)
        $idInt = (int)$id;
        if ($idInt <= 0) {
            return array();
        }
        $query ="SELECT * FROM songs WHERE sg_id=:id";
        $st = self::$db->prepare($query);
        $st->execute(array(':id' => $idInt));
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function deleteById($id)
    {
        $query ="DELETE FROM `songs` WHERE `songs`.`sg_id` = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectAll($filter)
    {
        
        $where ='WHERE sg_id > 0';

        if(isset($filter['attivo']) && $filter['attivo']==1){
            $where.=" AND sg_attivo = 1";
        }
        if(isset($filter['attivo']) && $filter['attivo']==2){
            $where.=" AND sg_attivo = 0";
        }
        if(isset($filter['format']) && $filter['format']>0){
            $where.=' AND sg_format & '.$filter['format'];
        }
        if(isset($filter['nazionalita']) && $filter['nazionalita']==1){
            $where.=" AND sg_nazione = 'Italiano'";
        }
        if(isset($filter['nazionalita']) && $filter['nazionalita']==2){
            $where.=" AND sg_nazione = 'Straniero'";
        }
        if(isset($filter['strategia']) && $filter['strategia']>0){
            $where.=" AND sg_strategia = ".$filter['strategia'];
        }
        if(isset($filter['sex']) && $filter['sex']!=''){
            $where.=" AND sg_sex = '".$filter['sex']."'";
        }
        if(isset($filter['umore']) && $filter['umore']!=''){
            $where.=" AND sg_umoreId = '".$filter['umore']."'";
        }
        if(isset($filter['ritmo']) && $filter['ritmo']>0){
            $where.=" AND sg_ritmoId = '".$filter['ritmo']."'";
        }
        if(isset($filter['energia']) && $filter['energia']>0){
            $where.=" AND sg_energia = '".$filter['energia']."'";
        }
        if(isset($filter['anno']) && $filter['anno']>0){
            $where.=" AND sg_anno = '".$filter['anno']."'";
        }
        if(isset($filter['periodo']) && $filter['periodo']>0){
            $where.=" AND sg_periodoId = '".$filter['periodo']."'";
        }
        if(isset($filter['genere']) && $filter['genere']>0){
            $where.=" AND sg_genereId = '".$filter['genere']."'";
        }
        if(isset($filter['diritti']) && $filter['diritti']>0){
            $where.=" AND sg_diritti = ".$filter['diritti'];
        }else{
            $where.=" AND sg_diritti = 0";
        }

        $query ="SELECT `sg_id`, `sg_artista`, `sg_titolo`, `sg_anno`, `sg_attivo`  FROM `songs` ".$where." ORDER BY sg_artista" ;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectAllFormats()
    {
        // Query esatta come richiesto: SELECT * FROM `format` WHERE `frmt_active` = '1' ORDER BY `frmt_nome`
        $query = "SELECT * FROM `format` WHERE `frmt_active` = '1' ORDER BY `frmt_nome`";
        $st = self::$db->prepare($query);
        $st->execute();
        $result = $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
        return $result;
    }

}
class Gruppi extends DB
{


    public static function selectCampagneByIdGroup($id)
    {
        $query ="SELECT * FROM `ds_campagne` WHERE `ds_camp_gruppo_id` = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectAllActive()
    {
        $query ="SELECT `gr_id`, `gr_nome` FROM `gruppi` WHERE `gr_active` = '1'";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectAllRss()
    {
        $query ="SELECT `rss_id`, `rss_nome` FROM `rss` WHERE `rss_active` = '1'";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectAll()
    {
        $query ="SELECT gr.gr_id, gr.gr_active, gr.gr_nome, COUNT(pl.pl_id) as tot_player FROM players AS pl RIGHT JOIN gruppi AS gr ON(pl.pl_idGruppo=gr.gr_id) WHERE gr.gr_id>0 AND gr.gr_nome<>'' GROUP BY gr.gr_id, gr.gr_active, gr.gr_nome ORDER BY gr.gr_nome ASC";
        /// result: gr_id, gr_active, gr_nome, tot_player
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;

    }

    public static function selectGruppoById($id)
    {
    $query ="SELECT gr.gr_id, gr.gr_active, gr.gr_nome, gr.gr_dataCreazione, rss.rss_id, rss.rss_nome, COUNT(pl.pl_id) as tot_player FROM players AS pl 
    LEFT JOIN gruppi AS gr ON(pl.pl_idGruppo=gr.gr_id) 
    LEFT JOIN rss as rss ON(gr.gr_ds_rss=rss.rss_id)
    WHERE gr.gr_id=".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function getCheckRelatedSubGruppoByIdPlayer($idPlayer,$idSubGruppo){

        $query = "SELECT COUNT(plsgr_sgr_id) as checked FROM `player_subgruppo` WHERE `plsgr_pl_id` = ".$idPlayer." AND `plsgr_sgr_id` = ".$idSubGruppo;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;

    }

    public static function selectSubGruppoByIdPlayer($id)
    {
        $query ="SELECT sgr.sgr_id , sgr.sgr_nome FROM sub_gruppi AS sgr 
        JOIN gruppi AS gr ON(sgr.sgr_gr_id=gr.gr_id) 
        JOIN players AS pl ON(pl.pl_idGruppo=gr.gr_id)
        WHERE pl.pl_id = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectSubGruppoById($id)
    {
        $query ="SELECT sgr.sgr_id , sgr.sgr_nome FROM sub_gruppi AS sgr 
        JOIN gruppi AS gr ON(sgr.sgr_gr_id=gr.gr_id) 
        WHERE gr.gr_id = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectTotPlayersSottoGruppoById($id)
    {
        $query ="SELECT COUNT(plsgr.plsgr_pl_id) as tot_player FROM player_subgruppo AS plsgr  
        WHERE plsgr.plsgr_sgr_id = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectAllPlayersGruppoById($id)
    {
        $query ="SELECT pl_id, pl_active, pl_nome, pl_player_ultimaDataEstesa FROM players WHERE pl_idGruppo = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function deleteSottoGruppoById($id)
    {
        $query ="DELETE FROM `sub_gruppi` WHERE `sub_gruppi`.`sgr_id` = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        $query ="DELETE FROM `player_subgruppo` WHERE `player_subgruppo`.`plsgr_sgr_id` = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function deleteGruppoById($id)
    {
        $query ="DELETE FROM `sub_gruppi` LEFT JOIN `player_subgruppo` ON (`player_subgruppo`.`plsgr_sgr_id`=`sub_gruppi`.`sgr_id`) WHERE `sub_gruppi`.`sgr_gr_id` = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        $query ="DELETE FROM `players` WHERE `players`.`pl_idGruppo` = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        $query ="DELETE FROM `gruppi` WHERE `gruppi`.`gr_id` = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function addSottoGruppoByName($idGroup,$name)
    {
        $query ="INSERT INTO `sub_gruppi` set sgr_gr_id  = ".$idGroup.", sgr_nome = '".strtoupper($name)."', srg_data_creazione = '".date("Y-m-d H:i:s")."'";
        $st = self::$db->prepare($query);
        $st->execute();
        $st -> closeCursor ( ) ;
        return $st -> fetchAll ( );
    }

    public static function updateGruppo($gruppo)
    {
            ///aggiorno il gruppo
        $st = self::$db->prepare(
            "UPDATE `gruppi` set
            gr_active = :active,
            gr_nome = :nome,
            gr_ds_rss = :rss
            WHERE gr_id = :id"
        );
        $st->execute(array(
            ':active' => $gruppo['active'],
            ':nome' => $gruppo['nome'],
            ':rss' => $gruppo['rss_id'],
            ':id' => $gruppo['groupId']
        ));
        $result=$st -> fetchAll ();
        $st -> closeCursor ( ) ;
        return $gruppo['groupId'];
    }

    public static function createGruppo($nome)
    {
        /// crea nuovo gruppo col nome (MAIUSCOLO)
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
        $st->execute(array(
            ':nome' => $nome,
            ':dataCreazione' => date("Y-m-d H:i:s")
        ));
        $res=$st -> fetchAll ();
        $query="SELECT * FROM gruppi ORDER BY gr_id DESC LIMIT 1";
        $st = self::$db->prepare($query);
        $st->execute();
        $res=$st -> fetch();
        $st -> closeCursor ( ) ;
        return $res['gr_id'];
    }
    

} 

class Utenti extends DB
{
    public static function selectUtenti()
    {
        $query ="SELECT id, active, nome, mail, rete_id, ultimoAccesso, dataCreazione, gr_nome, gr_id  FROM user LEFT JOIN gruppi ON(rete_id=gr_id) ORDER BY active DESC, rete_id ASC";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }
    public static function selectUtenteById($id)
    {
        $query ="SELECT * FROM user LEFT JOIN gruppi ON (rete_id=gr_id) WHERE id=".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function deleteUtente($id)
    {
        $query ="DELETE FROM `user` WHERE `user`.`id` = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        $st -> closeCursor ( ) ;
        return;
    }

    public static function changePassword($id,$newPass)
    {
        $query ="UPDATE `user` set `user`.`password` =  '".$newPass."' WHERE `user`.`id` = ".$id;
        $st = self::$db->prepare($query);
        $st->execute();
        $st -> closeCursor ( ) ;
        return;
    }

    public static function updateUtente($utente)
    {
        //print_r($utente);
        ///verifico se questo utente è già nel DB
        $query ="SELECT * FROM `user` WHERE `login`='".$utente['login']."'";
        $st = self::$db->prepare($query);
        $st->execute();
        if($st->rowCount()>0){
            $res=$st -> fetch ( );
            $type="upgrade";
            /// upgrade scheda esistente
            $st = self::$db->prepare(
                "UPDATE `user` set
                active   = :active,
                nome = :nome,
                indirizzo = :indirizzo,
                citta = :citta,
                cap = :cap,
                pro = :pro,
                tel = :tel,
                mail = :mail,
                login = :login,
                permesso = :permesso,
                rete_id = :rete_id
                WHERE id = :id"
            );
            $st->execute(array(
                ':active' => $utente['active'],
                ':nome' => $utente['nome'],
                ':indirizzo' => $utente['indirizzo'],
                ':citta' => $utente['citta'],
                ':cap' => $utente['cap'],
                ':pro' => $utente['pro'],
                ':tel' => $utente['tel'],
                ':mail' => $utente['mail'],
                ':login' => $utente['login'],
                ':permesso' => $utente['permesso'],
                ':rete_id' => $utente['rete_id'],
                ':id' => $res['id']
            ));
            $result=$st -> fetchAll ();
            $st -> closeCursor ( ) ;
            return $res['id'];
        }else{
        /// crea nuova scheda  
            $st = self::$db->prepare(
                "INSERT INTO `user` set
                active   = :active,
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
                dataCreazione = :dataCreazione"
            );
            $st->execute(array(
                ':active' => $utente['active'],
                ':nome' => $utente['nome'],
                ':indirizzo' => $utente['indirizzo'],
                ':citta' => $utente['citta'],
                ':cap' => $utente['cap'],
                ':pro' => $utente['pro'],
                ':tel' => $utente['tel'],
                ':mail' => $utente['mail'],
                ':login' => $utente['login'],
                ':permesso' => $utente['permesso'],
                ':rete_id' => $utente['rete_id'],
                ':dataCreazione' => date("Y-m-d H:i:s")
            ));
            $res=$st -> fetchAll ();
            $query="SELECT * FROM user ORDER BY id DESC LIMIT 1";
            $st = self::$db->prepare($query);
            $st->execute();
            $res=$st -> fetch();
            $st -> closeCursor ( ) ;
            return $res['id'];
        }
    }
}

class Monitor extends DB
{
    public static function selectPlayers($gr_nome)
    {
        $whereGruppo='';
        if($gr_nome!='tutti'){
            $whereGruppo=" AND gr_nome = '".$gr_nome."'";
        }
        $query ="SELECT pl_idGruppo, pl_id,pl_status, pl_nome,pl_player_ultimaData,pl_player_ultimaDataEstesa,pl_player_pc,pl_player_ip,pl_player_ver,gr_id,gr_nome,pl_mem_size,pl_mem_percent FROM players JOIN gruppi ON(pl_idGruppo=gr_id) WHERE pl_monitor=1".$whereGruppo." ORDER BY gr_nome,pl_player_ultimaData ASC";

        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectPlayerByID($id)
    {
        $query ="SELECT * FROM players JOIN gruppi ON(pl_idGruppo=gr_id) WHERE pl_id=".$id." ";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectPingByPlayerID($player_id)
    {
        $query ="SELECT * FROM ping WHERE ping_ID_player=".$player_id." ORDER BY ping_ID DESC";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectAllGroups()
    {
        
        $query ="SELECT gr_nome FROM gruppi WHERE gr_active=1 ORDER BY gr_nome ASC";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }

    public static function selectAllPlayersByStatusOrderByGroups()
    {
        
        $query ="SELECT * FROM players JOIN gruppi ON(pl_idGruppo=gr_id) WHERE pl_monitor=1 AND gr_active=1  ORDER BY gr_nome,pl_player_ultimaData ASC";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st -> fetchAll ( );
        $st -> closeCursor ( ) ;
    }



}

class Contractor extends DB
{
    public static function selectAll()
    {
        $query = "SELECT id, name FROM contractor ORDER BY id ASC";
        $st = self::$db->prepare($query);
        $st->execute();
        return $st->fetchAll();
        $st->closeCursor();
    }
}
