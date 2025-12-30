<?php
/**
 * Script per importare canzoni da CSV Watermelon
 * Legge il file CSV e genera query INSERT per la tabella songs
 * Copia i file dalla cartella file/ a filenew/ rinominandoli
 */

// Percorsi
$csvFile = __DIR__ . '/csv/watermelonODStore.csv';
$fileDir = __DIR__ . '/file/';
$fileNewDir = __DIR__ . '/filenew/';

// Crea la cartella filenew se non esiste
if (!is_dir($fileNewDir)) {
    mkdir($fileNewDir, 0755, true);
    echo "Cartella 'filenew' creata.\n\n";
}

// Contatori per gli ID incrementali
$sgIdCounter = 24645;
$sgFileCounter = 42003;

// Verifica che il file CSV esista
if (!file_exists($csvFile)) {
    die("Errore: File CSV non trovato: $csvFile\n");
}

// Apri il file CSV
$handle = fopen($csvFile, 'r');
if ($handle === false) {
    die("Errore: Impossibile aprire il file CSV: $csvFile\n");
}

// Leggi l'header (prima riga)
$header = fgetcsv($handle, 0, ',', '"', '\\');
if ($header === false) {
    die("Errore: Impossibile leggere l'header del CSV\n");
}

// Trova gli indici delle colonne necessarie
$filenameIndex = array_search('filename', $header);
$trackTitleIndex = array_search('track_title', $header);
$artistIndex = array_search('artist', $header);

if ($filenameIndex === false || $trackTitleIndex === false || $artistIndex === false) {
    die("Errore: Colonne mancanti nel CSV. Richieste: filename, track_title, artist\n");
}

echo "=== IMPORT WATERMELON SONGS ===\n\n";
echo "Header CSV: " . implode(', ', $header) . "\n\n";

$rowCount = 0;
$successCount = 0;
$errorCount = 0;

// Leggi ogni riga del CSV
while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
    $rowCount++;
    
    // Estrai i dati
    $filename = trim($data[$filenameIndex] ?? '');
    $trackTitle = trim($data[$trackTitleIndex] ?? '');
    $artist = trim($data[$artistIndex] ?? '');
    
    // Salta righe vuote
    if (empty($filename) && empty($trackTitle) && empty($artist)) {
        continue;
    }
    
    // Verifica che il file esista nella cartella file/
    $sourceFile = $fileDir . $filename;
    if (!file_exists($sourceFile)) {
        echo "[RIGA $rowCount] ERRORE: File non trovato: $filename\n";
        $errorCount++;
        continue;
    }
    
    // Ottieni la dimensione del file
    $filesize = filesize($sourceFile);
    if ($filesize === false) {
        echo "[RIGA $rowCount] ERRORE: Impossibile ottenere la dimensione del file: $filename\n";
        $errorCount++;
        continue;
    }
    
    // Genera i valori incrementali
    $sgId = $sgIdCounter++;
    $sgFile = $sgFileCounter++;
    
    // Prepara i valori per la query (escape per SQL)
    $sgTitoloEscaped = addslashes($trackTitle);
    $sgArtistaEscaped = addslashes($artist);
    
    // Genera la query INSERT
    $query = "INSERT INTO `songs` (`sg_id`, `sg_file`, `sg_filesize`, `sg_titolo`, `sg_artista`) " .
             "VALUES ($sgId, $sgFile, $filesize, '$sgTitoloEscaped', '$sgArtistaEscaped');";
    
    // Stampa la query
    echo "--- RIGA $rowCount ---\n";
    echo "Filename CSV: $filename\n";
    echo "Track Title: $trackTitle\n";
    echo "Artist: $artist\n";
    echo "File Size: $filesize bytes\n";
    echo "sg_id: $sgId\n";
    echo "sg_file: $sgFile\n";
    echo "Query:\n$query\n";
    
    // Copia il file dalla cartella file/ a filenew/ rinominandolo
    $destFile = $fileNewDir . $sgFile . '.mp3';
    if (copy($sourceFile, $destFile)) {
        echo "File copiato: $filename -> " . basename($destFile) . "\n";
        $successCount++;
    } else {
        echo "ERRORE: Impossibile copiare il file: $filename\n";
        $errorCount++;
    }
    
    echo "\n";
}

// Chiudi il file
fclose($handle);

// Riepilogo
echo "\n=== RIEPILOGO ===\n";
echo "Righe processate: $rowCount\n";
echo "Operazioni riuscite: $successCount\n";
echo "Errori: $errorCount\n";
echo "\nNOTA: Le query NON sono state eseguite. Sono state solo generate e stampate.\n";
?>

