<?php
/**
 * Bootstrap DB + path song per manutenzione_songs.
 * Usato da delete_orphan.php (e riusabile).
 */

function manutenzione_load_config()
{
    $candidates = array(
        __DIR__ . '/config.local.php',
        __DIR__ . '/../inc/config.php',
        '/var/www/vhosts/yourradio.org/httpdocs/tools/inc/config.php',
        __DIR__ . '/../../inc/config.php',
        __DIR__ . '/../../api/config.php',
        '/var/www/vhosts/yourradio.org/httpdocs/api/config.php',
        '/var/www/vhosts/yourradio.org/httpdocs/inc/config.php',
    );

    foreach ($candidates as $cfg) {
        if (is_file($cfg)) {
            require_once $cfg;
            break;
        }
    }

    if (!defined('DB_HOST') && isset($GLOBALS['db_host'])) {
        define('DB_HOST', $GLOBALS['db_host']);
    }
    if (!defined('DB_NAME') && isset($GLOBALS['db_name'])) {
        define('DB_NAME', $GLOBALS['db_name']);
    }
    if (!defined('DB_USER') && isset($GLOBALS['db_user'])) {
        define('DB_USER', $GLOBALS['db_user']);
    }
    if (!defined('DB_PASS') && isset($GLOBALS['db_pass'])) {
        define('DB_PASS', $GLOBALS['db_pass']);
    }
    // variabili locali dal config legacy
    if (!defined('DB_HOST') && isset($db_host)) {
        define('DB_HOST', $db_host);
    }
    if (!defined('DB_NAME') && isset($db_name)) {
        define('DB_NAME', $db_name);
    }
    if (!defined('DB_USER') && isset($db_user)) {
        define('DB_USER', $db_user);
    }
    if (!defined('DB_PASS') && isset($db_pass)) {
        define('DB_PASS', $db_pass);
    }
}

function manutenzione_song_path()
{
    if (defined('SONG_PATH') && SONG_PATH) {
        return rtrim(SONG_PATH, '/');
    }
    return '/var/www/vhosts/yourradio.org/httpdocs/player/song';
}

/**
 * Nome file download: "artista - titolo.mp3" (caratteri pericolosi rimossi).
 */
function manutenzione_download_filename($artista, $titolo, $fallbackId)
{
    $a = trim((string) $artista);
    $t = trim((string) $titolo);
    if ($a === '' && $t === '') {
        return $fallbackId . '.mp3';
    }
    if ($a === '') {
        $name = $t;
    } elseif ($t === '') {
        $name = $a;
    } else {
        $name = $a . ' - ' . $t;
    }
    // caratteri non validi nei filename
    $name = preg_replace('/[\\\\\\/:*?"<>|]+/', '_', $name);
    $name = preg_replace('/\\s+/', ' ', $name);
    $name = trim($name, " .");
    if ($name === '' || $name === '-') {
        return $fallbackId . '.mp3';
    }
    // limita lunghezza
    if (function_exists('mb_substr')) {
        $name = mb_substr($name, 0, 180, 'UTF-8');
    } else {
        $name = substr($name, 0, 180);
    }
    return $name . '.mp3';
}

