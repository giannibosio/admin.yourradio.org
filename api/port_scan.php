<?php
/* 
port_scan_player.php
https://yourradio.org/xml/port_scan.php?playerId=5482
*/
date_default_timezone_set('UTC');

/* ===== CONFIG DATABASE ===== */
$db_host = "localhost";
$db_name = "myradio";
$db_user = "mymusic";
$db_pass = "jago22422";
/* =========================== */

$ports = array(4080, 4022);
$timeout = 3; // secondi

// Recupera playerId
$playerId = isset($_GET['playerId']) ? $_GET['playerId'] : '';
if ($playerId === '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => 'Parametro playerId mancante'), JSON_PRETTY_PRINT);
    exit;
}

// Connessione DB
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => 'Connessione DB fallita', 'errno' => $mysqli->connect_errno, 'error' => $mysqli->connect_error), JSON_PRETTY_PRINT);
    exit;
}

// Recupera player
$stmt = $mysqli->prepare("SELECT * FROM players WHERE pl_id = ?");
$stmt->bind_param('i', $playerId);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows == 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => 'Player non trovato', 'playerId' => $playerId), JSON_PRETTY_PRINT);
    $stmt->close();
    $mysqli->close();
    exit;
}

$player = $result->fetch_assoc();
$stmt->close();

if($player['pl_player_network']!=2){
    $timestamp = date('Y-m-d H:i:s');
    $response = array(
        'playerId' => $playerId,
        'pl_nome' => $player['pl_nome'],
        'pl_player_ip' => $player['pl_player_ip'],
        'timestamp' => $timestamp,
        'network' => $player['pl_player_network'],
        'results' => null
    );

    // Output JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}


$ip = $player['pl_player_ip'];
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => 'IP player non valido', 'playerId' => $playerId, 'ip' => $ip), JSON_PRETTY_PRINT);
    $mysqli->close();
    exit;
}

// Test porte
$results = array();
foreach ($ports as $port) {
    $status = 'CLOSED';
    $errNo = 0;
    $errStr = '';
    $fp = @fsockopen($ip, $port, $errNo, $errStr, $timeout);
    if ($fp) {
        $status = 'OPEN';
        fclose($fp);
    }
    $results[$port] = $status;

}


// Aggiorna il record del player con lo stato delle porte
$updateStmt = $mysqli->prepare("UPDATE players SET port_80 = ?, port_22 = ? WHERE pl_id = ?");
$updateStmt->bind_param(
    'ssi',
    $results[4080],
    $results[4022],
    $playerId
);
$updateStmt->execute();
$updateStmt->close();

// Preparazione JSON di ritorno
$timestamp = date('Y-m-d H:i:s');
$response = array(
    'playerId' => $playerId,
    'pl_nome' => $player['pl_nome'],
    'pl_player_ip' => $ip,
    'timestamp' => $timestamp,
    'results' => $results
);

// Output JSON
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_PRETTY_PRINT);

$mysqli->close();
exit;
?>