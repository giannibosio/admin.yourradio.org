<?php
session_start();
include_once('load.php');

if (!isset($_SESSION['userID'])) {
    header('Location: auth-login.php');
    exit;
}

$userId = (int)$_SESSION['userID'];
$monitorUrl = defined('MONITOR_URL') ? MONITOR_URL : 'https://monitor.yourradio.org';
$apiUrl = (defined('MONITOR_API_URL') ? MONITOR_API_URL : 'https://yourradio.org/monitor/api') . '/auth/remember_token';

$token = null;

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['user_id' => $userId]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
curl_close($ch);

if ($response !== false) {
    $data = json_decode($response, true);
    if (is_array($data) && !empty($data['success']) && !empty($data['data']['token'])) {
        $token = $data['data']['token'];
    }
}

if ($token) {
    header('Location: ' . rtrim($monitorUrl, '/') . '/sso.php?token=' . urlencode($token));
    exit;
}

header('Location: ' . rtrim($monitorUrl, '/') . '/');
exit;
