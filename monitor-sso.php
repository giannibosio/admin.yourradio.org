<?php
/**
 * Bridge SSO: utente già loggato su admin → apre monitor.yourradio.org senza login.
 */
session_start();
include_once('load.php');

if (!isset($_SESSION['userID'])) {
    header('Location: auth-login.php');
    exit;
}

$userId = (int)$_SESSION['userID'];
$monitorUrl = defined('MONITOR_URL') ? rtrim(MONITOR_URL, '/') : 'https://monitor.yourradio.org';
$apiUrl = (defined('MONITOR_API_URL') ? rtrim(MONITOR_API_URL, '/') : 'https://yourradio.org/monitor/api')
    . '/auth/remember_token';

$token = null;
$errorDetail = '';

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['user_id' => $userId]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    $errorDetail = 'curl: ' . $curlErr;
} else {
    $data = json_decode($response, true);
    if (is_array($data) && !empty($data['success']) && !empty($data['data']['token'])) {
        $token = $data['data']['token'];
    } else {
        $errorDetail = 'http=' . $httpCode . ' body=' . substr((string)$response, 0, 300);
    }
}

if ($token) {
    header('Location: ' . $monitorUrl . '/sso.php?token=' . urlencode($token));
    exit;
}

// Se la creazione token fallisce, non aprire la login "muta":
// resta su admin con messaggio chiaro (meglio di un redirect silenzioso alla login monitor).
header('Content-Type: text/html; charset=utf-8');
http_response_code(502);
echo '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><title>SSO Monitor</title></head><body style="font-family:sans-serif;padding:2rem;background:#111;color:#eee;">';
echo '<h2>Impossibile aprire Monitor senza login</h2>';
echo '<p>Non è stato possibile creare il token SSO per l\'utente ID ' . htmlspecialchars((string)$userId) . '.</p>';
if ($errorDetail !== '') {
    echo '<p style="color:#aaa;font-size:0.9em;">Dettaglio: ' . htmlspecialchars($errorDetail) . '</p>';
}
echo '<p><a href="' . htmlspecialchars($monitorUrl) . '/" style="color:#6cf;">Apri monitor.yourradio.org (con login)</a></p>';
echo '<p><a href="javascript:history.back()" style="color:#6cf;">Torna indietro</a></p>';
echo '</body></html>';
exit;
