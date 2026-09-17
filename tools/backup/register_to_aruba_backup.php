<?php
/**
 * Wrapper PHP per avviare il backup Register -> Aruba.
 *
 * Uso consigliato: cron con lo script bash direttamente.
 * Questo file serve per lancio manuale da CLI o da endpoint protetto.
 *
 * CLI:
 *   php register_to_aruba_backup.php
 *
 * Non esporre via web senza autenticazione forte.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Solo da CLI.\n";
    exit(1);
}

$script = getenv('YOURRADIO_BACKUP_SCRIPT') ?: '/usr/local/bin/yourradio-backup';
$config = getenv('YOURRADIO_BACKUP_CONFIG') ?: '/etc/yourradio/backup.env';

if (!is_file($script) || !is_executable($script)) {
    fwrite(STDERR, "Script backup non trovato o non eseguibile: {$script}\n");
    exit(2);
}

if (!is_file($config)) {
    fwrite(STDERR, "Config mancante: {$config}\n");
    exit(2);
}

$cmd = escapeshellarg($script);
$envPrefix = 'YOURRADIO_BACKUP_CONFIG=' . escapeshellarg($config);

echo "Avvio backup YourRadio...\n";
echo "Script: {$script}\n";
echo "Config: {$config}\n\n";

passthru("{$envPrefix} {$cmd}", $exitCode);
exit((int) $exitCode);
