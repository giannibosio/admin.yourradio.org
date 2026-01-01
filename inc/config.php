<?php

/// DATABASE
define('DB_ENGINE', "mysql");
// Rileva automaticamente l'host del database in base al server
// Se siamo su admin.yourradio.org, il database potrebbe essere su localhost (stesso server)
// oppure su yourradio.org (server remoto)
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'admin.yourradio.org') !== false) {
    // Siamo su admin.yourradio.org - prova prima localhost (stesso server)
    // Se non funziona, potrebbe essere necessario configurare MySQL per accettare connessioni remote
    define('DB_HOST', "localhost");
} else {
    // Sviluppo locale
    define('DB_HOST', "localhost");
}
define('DB_NAME', "myradio");
define('DB_USER', "mymusic");
define('DB_PASS', "jago22422");

/// SITE
define('SITE_TITLE', "YourRadio");
define('SITE_DESCRIPTION', "Radio Instore");

define('PLAYER_PATH', "./player/");
define('SONG_PATH', $_SERVER['DOCUMENT_ROOT']."/player/song/");

class Select {
	public static function getOptions($sel)
	{
		switch ($sel){
			case 'sg_diritti':
				return array(
		          "0" => "SIAE",
		          "1" => "CREATIVE C",
		          "3" => "WATERMELON"
	        	); 
	        	break;
			case 'sg_umoreId':
				return array(
		          "0" => "Normale",
		          "1" => "Allegro",
		          "2" => "Allegrissimo",
		          "3" => "Aggressivo",
		          "4" => "Triste",
		          "5" => "Malinconico"
	        	); 
	        	break;
	        case 'sg_nazione':
				return array(
		          "0" => "",
		          "Italiano" => "Italiana",
		          "Straniero" => "Straniera"
	        	); 
	        	break;
	        case 'sg_sex':
				return array(
		          "0" => "",
		          "Maschile" => "Maschile",
		          "Femminile" => "Femminile",
		          "Strumentale" => "Strumentale"
	        	); 
	        	break;
	        case 'sg_energia':
				return array(
		          "0" => "",
		          "1" => "Energia 1",
		          "2" => "Energia 2",
		          "3" => "Energia 3",
		          "4" => "Energia 4",
		          "5" => "Energia 5",
		          "6" => "Energia 6"
	        	); 
	        	break;
	        case 'sg_ritmoId':
				return array(
		          "0" => "",
		          "1" => "Molto Lento",
		          "2" => "Lento",
		          "3" => "Moderato",
		          "4" => "Veloce",
		          "5" => "Molto Veloce"
	        	); 
	        	break;
	        case 'sg_periodoId':
				return array(
		          "0" => "Sempre",
		          "1" => "Estate",
		          "2" => "Natale"
	        	); 
	        	break;
	        case 'sg_strategia':
				return array(
		          "0" => "",
		          "2" => "Stra.2",
		          "3" => "Stra.3",
		          "4" => "Stra.4",
		          "5" => "Stra.5",
		          "1" => "Speciale..."
	        	); 
	        	break;
	        case 'sg_genereId':
				return array(
		          "0"  => "",
		          "2"  => "Disco",
		          "3"  => "Pop",
		          "4"  => "Rock",
		          "32" => "Jazz",
		          "13" => "Urban",
		          "37" => "Hip Hop",
		          "48" => "Lounge",
		          "54" => "World",
		          "59" => "Deep",
		          "52" => "Classica Str.",
		          "53" => "Classica Str",
		          "55" => "Bimbi",
		          "66" => "Country"
	        	); 
	        	break;
		}
    	   
	}
}
