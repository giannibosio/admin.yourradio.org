<?php
/**
 * Script di test per verificare la connessione alle API
 * Esegui questo file per vedere se le API su yourradio.org rispondono
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Connessione API</h1>";
echo "<p>Server: <strong>https://yourradio.org</strong></p>";
echo "<hr>";

// Includi la funzione callApi
include_once('inc/functions.php');

// Test 1: Chiamata semplice
echo "<h2>Test 1: Chiamata API base</h2>";
$test1 = callApi("gruppi");
echo "<pre>";
echo "Success: " . (isset($test1['success']) && $test1['success'] ? 'YES' : 'NO') . "\n";
if(isset($test1['error'])) {
    echo "Error: " . json_encode($test1['error'], JSON_PRETTY_PRINT) . "\n";
}
if(isset($test1['httpCode'])) {
    echo "HTTP Code: " . $test1['httpCode'] . "\n";
}
if(isset($test1['url'])) {
    echo "URL chiamato: " . $test1['url'] . "\n";
}
echo "</pre>";

// Test 2: Chiamata a players
echo "<h2>Test 2: Chiamata API players (ID 1)</h2>";
$test2 = callApi("players/1");
echo "<pre>";
echo "Success: " . (isset($test2['success']) && $test2['success'] ? 'YES' : 'NO') . "\n";
if(isset($test2['error'])) {
    echo "Error: " . json_encode($test2['error'], JSON_PRETTY_PRINT) . "\n";
}
if(isset($test2['httpCode'])) {
    echo "HTTP Code: " . $test2['httpCode'] . "\n";
}
if(isset($test2['url'])) {
    echo "URL chiamato: " . $test2['url'] . "\n";
}
echo "</pre>";

// Test 3: Test connessione diretta
echo "<h2>Test 3: Test connessione diretta con curl</h2>";
$ch = curl_init("https://yourradio.org/api/gruppi");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<pre>";
echo "HTTP Code: " . $httpCode . "\n";
echo "Error: " . ($error ?: 'none') . "\n";
echo "Response length: " . strlen($response) . " bytes\n";
echo "Response preview: " . substr($response, 0, 200) . "\n";
echo "</pre>";

echo "<hr>";
echo "<p><strong>Controlla i log PHP per dettagli completi delle chiamate API</strong></p>";
echo "<p>I log mostrano:</p>";
echo "<ul>";
echo "<li>URL completo chiamato (sempre https://yourradio.org/api/...)</li>";
echo "<li>Metodo HTTP</li>";
echo "<li>Codice di risposta</li>";
echo "<li>Tempo di risposta</li>";
echo "<li>Eventuali errori</li>";
echo "</ul>";

