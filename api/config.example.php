<?php
/**
 * File di esempio per la configurazione API YourRadio
 * Copiare questo file come config.php e aggiornare i valori
 */

/// DATABASE
define('DB_ENGINE', "mysql");
define('DB_HOST', "localhost");
define('DB_NAME', "myradio");
define('DB_USER', "mymusic");
define('DB_PASS', "your_password_here");

/// SITE
define('SITE_TITLE', "YourRadio");
define('SITE_DESCRIPTION', "Radio Instore");

/// PATHS
// IMPORTANTE: Aggiornare questi path con i percorsi corretti sul server centrale
define('PLAYER_PATH', "/var/www/yourradio/player/");
define('SONG_PATH', "/var/www/yourradio/player/song/");

/// API CONFIG
define('API_VERSION', "1.0");
define('API_BASE_URL', "https://yourradio.org/api");

/// CORS
// Aggiungere qui i domini autorizzati a fare richieste alle API
// PHP 5.6 compatibility: use global variable instead of define() with array
$GLOBALS['ALLOWED_ORIGINS'] = array(
    'https://admin.yourradio.org',
    'http://localhost:3000',
    'http://localhost:8080'
);

/// ERROR HANDLING
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disabilitare in produzione
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

