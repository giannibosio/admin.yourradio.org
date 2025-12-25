<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once('inc/config.php');
include_once('inc/database.php');

include_once('inc/functions.php');
include_once('inc/ajax.php');



?>