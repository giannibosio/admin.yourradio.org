<?php
/**
 * Endpoint per gestione Songs
 * GET    /api/songs              - Lista tutte le songs (con filtri)
 * GET    /api/songs/{id}         - Dettaglio song
 * POST   /api/songs              - Crea nuova song
 * PUT    /api/songs/{id}         - Aggiorna song
 * DELETE /api/songs/{id}         - Elimina song
 * POST   /api/songs/{id}/upload  - Carica file audio per una song
 */

/**
 * Azzera i metadati ID3 del file MP3 e scrive solo titolo, autore, anno, album.
 * Rimuove ID3v2 (in testa) e ID3v1 (in coda), poi aggiunge un unico tag ID3v1.1.
 * @param string $tmpPath Percorso del file MP3 (viene sovrascritto)
 * @param string $title Titolo (max 30 caratteri)
 * @param string $artist Autore (max 30 caratteri)
 * @param string|int $year Anno (max 4 caratteri)
 * @param string $album Album (default "YourRadio", max 30 caratteri)
 * @return bool true se ok
 */
function rewriteMp3Metadata($tmpPath, $title, $artist, $year, $album = 'YourRadio') {
    $data = file_get_contents($tmpPath);
    if ($data === false || strlen($data) < 10) {
        return false;
    }
    // Rimuovi ID3v2 in testa (header 10 byte, size in byte 6-9 synchsafe)
    if (substr($data, 0, 3) === 'ID3') {
        $size = (ord($data[6]) & 0x7F) << 21 | (ord($data[7]) & 0x7F) << 14
            | (ord($data[8]) & 0x7F) << 7 | (ord($data[9]) & 0x7F);
        $data = substr($data, 10 + $size);
    }
    // Rimuovi ID3v1 in coda (128 byte che iniziano con TAG)
    if (strlen($data) >= 128 && substr($data, -128, 3) === 'TAG') {
        $data = substr($data, 0, -128);
    }
    // ID3v1 usa ISO-8859-1 (Latin1); tronca a 30 byte per title/artist/album
    $enc = 'ISO-8859-1';
    $title  = (string)$title;
    $artist = (string)$artist;
    $album  = (string)$album;
    $year   = substr((string)$year, 0, 4);
    if (function_exists('mb_convert_encoding')) {
        $title  = mb_convert_encoding($title, $enc, 'UTF-8');
        $artist = mb_convert_encoding($artist, $enc, 'UTF-8');
        $album  = mb_convert_encoding($album, $enc, 'UTF-8');
    }
    $title  = substr($title, 0, 30);
    $artist = substr($artist, 0, 30);
    $album  = substr($album, 0, 30);
    // ID3v1.1: 128 byte total. TAG(3) + title(30) + artist(30) + album(30) + year(4) + comment(30) + genre(1)
    // Comment block = 30 bytes: 28 comment + \0 + track (v1.1); then 1 byte genre
    $tag = 'TAG';
    $tag .= str_pad($title, 30, "\0");
    $tag .= str_pad($artist, 30, "\0");
    $tag .= str_pad($album, 30, "\0");
    $tag .= str_pad($year, 4, "\0");
    $tag .= str_repeat("\0", 28) . "\0" . chr(0); // comment 30 bytes (28 + null + track)
    $tag .= chr(255); // genre
    if (strlen($tag) !== 128) {
        return false;
    }
    $data .= $tag;
    return file_put_contents($tmpPath, $data, LOCK_EX) !== false;
}

function handleSongsRequest($method, $action, $id, $data) {
    switch ($method) {
        case 'GET':
            if ($id === null && $action === 'maxids') {
                // Recupera i valori massimi di sg_id e sg_file
                try {
                    $queryMaxSgId = "SELECT sg_id FROM songs WHERE sg_id IS NOT NULL ORDER BY sg_id DESC LIMIT 1";
                    $queryMaxSgFile = "SELECT sg_file FROM songs WHERE sg_file IS NOT NULL ORDER BY sg_file DESC LIMIT 1";
                    
                    $st1 = Songs::$db->prepare($queryMaxSgId);
                    $st1->execute();
                    $result1 = $st1->fetch(PDO::FETCH_ASSOC);
                    
                    $st2 = Songs::$db->prepare($queryMaxSgFile);
                    $st2->execute();
                    $result2 = $st2->fetch(PDO::FETCH_ASSOC);
                    
                    $maxSgId = isset($result1['sg_id']) && $result1['sg_id'] !== null ? (int)$result1['sg_id'] : 0;
                    $maxSgFile = isset($result2['sg_file']) && $result2['sg_file'] !== null ? (int)$result2['sg_file'] : 0;
                    
                    sendSuccessResponse([
                        'max_sg_id' => $maxSgId,
                        'max_sg_file' => $maxSgFile,
                        'next_sg_id' => $maxSgId + 1,
                        'next_sg_file' => $maxSgFile + 1
                    ]);
                } catch (Exception $e) {
                    sendErrorResponse("Errore nel recupero dei valori massimi: " . $e->getMessage(), 500);
                }
            } elseif ($id === null && $action === 'byfilename') {
                // Recupera song per sg_filename_wm
                if (!isset($_GET['filename'])) {
                    sendErrorResponse("Parametro filename richiesto", 400);
                }
                try {
                    $filename = $_GET['filename'];
                    $query = "SELECT * FROM songs WHERE sg_filename_wm = :filename LIMIT 1";
                    $st = Songs::$db->prepare($query);
                    $st->execute([':filename' => $filename]);
                    $song = $st->fetch(PDO::FETCH_ASSOC);
                    
                    if ($song) {
                        sendSuccessResponse([
                            'sg_id' => (int)$song['sg_id'],
                            'sg_file' => isset($song['sg_file']) ? (int)$song['sg_file'] : null,
                            'sg_filename_wm' => $song['sg_filename_wm'],
                            'exists' => true,
                            'song' => $song
                        ]);
                    } else {
                        sendSuccessResponse([
                            'sg_id' => null,
                            'sg_file' => null,
                            'sg_filename_wm' => $filename,
                            'exists' => false
                        ]);
                    }
                } catch (Exception $e) {
                    sendErrorResponse("Errore nel recupero della song: " . $e->getMessage(), 500);
                }
            } elseif ($id === null && $action === 'byfilenameorigin') {
                // Recupera song per sg_filename_origin
                if (!isset($_GET['filename'])) {
                    sendErrorResponse("Parametro filename richiesto", 400);
                }
                try {
                    $filename = $_GET['filename'];
                    $query = "SELECT * FROM songs WHERE sg_filename_origin = :filename LIMIT 1";
                    $st = Songs::$db->prepare($query);
                    $st->execute([':filename' => $filename]);
                    $song = $st->fetch(PDO::FETCH_ASSOC);
                    
                    if ($song) {
                        sendSuccessResponse([
                            'sg_id' => (int)$song['sg_id'],
                            'sg_file' => isset($song['sg_file']) ? (int)$song['sg_file'] : null,
                            'sg_filename_origin' => isset($song['sg_filename_origin']) ? $song['sg_filename_origin'] : null,
                            'exists' => true,
                            'song' => $song
                        ]);
                    } else {
                        sendSuccessResponse([
                            'sg_id' => null,
                            'sg_file' => null,
                            'sg_filename_origin' => $filename,
                            'exists' => false
                        ]);
                    }
                } catch (Exception $e) {
                    sendErrorResponse("Errore nel recupero della song: " . $e->getMessage(), 500);
                }
            } elseif ($id !== null && $action === 'format') {
                // Verifica se esiste song_format per id_song e id_format
                if (!isset($_GET['id_format'])) {
                    sendErrorResponse("Parametro id_format richiesto", 400);
                }
                try {
                    $idSong = (int)$id;
                    $idFormat = (int)$_GET['id_format'];
                    $query = "SELECT id_song FROM song_format WHERE id_song = :id_song AND id_format = :id_format LIMIT 1";
                    $st = Songs::$db->prepare($query);
                    $st->execute([
                        ':id_song' => $idSong,
                        ':id_format' => $idFormat
                    ]);
                    $format = $st->fetch(PDO::FETCH_ASSOC);
                    
                    sendSuccessResponse([
                        'id_song' => $idSong,
                        'id_format' => $idFormat,
                        'exists' => !empty($format)
                    ]);
                } catch (Exception $e) {
                    sendErrorResponse("Errore nel recupero del format: " . $e->getMessage(), 500);
                }
            } elseif ($id === null && $action === '') {
                // Lista songs con filtri
                $filter = [];
                
                // Applica filtri dalla query string
                if (isset($_GET['attivo'])) $filter['attivo'] = (int)$_GET['attivo'];
                // Format può essere un singolo valore o una lista separata da virgole
                if (isset($_GET['format']) && $_GET['format'] !== '') {
                    $formatValue = $_GET['format'];
                    // Rimuovi spazi
                    $formatValue = trim($formatValue);
                    // Debug: verifica se contiene virgole
                    if (strpos($formatValue, ',') !== false) {
                        // Lista di format separati da virgola
                        $formatArray = explode(',', $formatValue);
                        $formatIds = array();
                        foreach ($formatArray as $f) {
                            $f = trim($f);
                            if ($f !== '' && $f !== '0') {
                                $fid = (int)$f;
                                if ($fid > 0) {
                                    $formatIds[] = $fid;
                                }
                            }
                        }
                        if (count($formatIds) > 0) {
                            $filter['formats'] = $formatIds;
                        }
                    } else {
                        // Singolo format
                        $formatInt = (int)$formatValue;
                        if ($formatInt > 0) {
                            $filter['format'] = $formatInt;
                        }
                    }
                }
                if (isset($_GET['nazionalita'])) $filter['nazionalita'] = (int)$_GET['nazionalita'];
                if (isset($_GET['strategia'])) $filter['strategia'] = (int)$_GET['strategia'];
                if (isset($_GET['sex'])) $filter['sex'] = sanitizeInput($_GET['sex']);
                if (isset($_GET['umore'])) $filter['umore'] = sanitizeInput($_GET['umore']);
                if (isset($_GET['ritmo'])) $filter['ritmo'] = (int)$_GET['ritmo'];
                if (isset($_GET['energia'])) $filter['energia'] = (int)$_GET['energia'];
                // Anno: range DAL - AL. Se AL è 0 (TUTTI) e DAL è valorizzato = DAL fino ad anno attuale
                $annoDal = isset($_GET['anno_dal']) ? (int)$_GET['anno_dal'] : 0;
                $annoAl  = isset($_GET['anno_al'])  ? (int)$_GET['anno_al']  : 0;
                if ($annoDal > 0) {
                    if ($annoAl === 0) {
                        $annoAl = (int)date('Y');
                    }
                    if ($annoDal > $annoAl) {
                        sendErrorResponse("Anno DAL deve essere minore o uguale ad Anno AL", 400);
                    }
                    $filter['anno_dal'] = $annoDal;
                    $filter['anno_al']  = $annoAl;
                }
                if (isset($_GET['periodo'])) $filter['periodo'] = (int)$_GET['periodo'];
                if (isset($_GET['genere'])) $filter['genere'] = (int)$_GET['genere'];
                // Gestisci diritti: aggiungi al filtro solo se è un valore numerico valido (non "*" o vuoto)
                // Nota: il valore 0 (Siae) è valido e deve essere incluso nel filtro
                if (isset($_GET['diritti']) && $_GET['diritti'] !== '*' && $_GET['diritti'] !== '' && $_GET['diritti'] !== null) {
                    $dirittiVal = trim($_GET['diritti']);
                    if ($dirittiVal !== '*' && $dirittiVal !== '') {
                        // Converti a intero (0, 1, 3 sono valori validi)
                        $dirittiInt = (int)$dirittiVal;
                        // Verifica che sia un numero valido (0, 1, o 3)
                        if ($dirittiInt === 0 || $dirittiInt === 1 || $dirittiInt === 3) {
                            $filter['diritti'] = $dirittiInt;
                        }
                    }
                }
                // Se diritti non è impostato o è "*", non aggiungere al filtro (mostra tutti)
                
                $songs = Songs::selectAll($filter);
                $result = [];
                foreach ($songs as $s) {
                    if ($s['sg_artista'] != '') {
                        $result[] = [
                            'id' => (int)$s['sg_id'],
                            'artista' => $s['sg_artista'],
                            'titolo' => $s['sg_titolo'],
                            'anno' => $s['sg_anno'] ? (int)$s['sg_anno'] : null,
                            'attivo' => (int)$s['sg_attivo']
                        ];
                    }
                }
                sendSuccessResponse($result);
            } elseif ($id !== null && $action === '') {
                // Dettaglio song
                $song = Songs::selectSongById($id);
                if (empty($song)) {
                    sendErrorResponse("Song non trovata", 404);
                }
                sendSuccessResponse($song[0]);
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        case 'POST':
            if ($id === null && $action === 'bulk-format') {
                // Abbina più song a un format: per ogni song verifica se esiste già la relazione in song_format, altrimenti la crea
                if (!isset($data['song_ids']) || !is_array($data['song_ids']) || !isset($data['id_format'])) {
                    sendErrorResponse("Parametri song_ids (array) e id_format richiesti", 400);
                }
                $songIds = array_map('intval', $data['song_ids']);
                $songIds = array_filter($songIds, function($v) { return $v > 0; });
                $idFormat = (int)$data['id_format'];
                if ($idFormat <= 0) {
                    sendErrorResponse("id_format non valido", 400);
                }
                $created = 0;
                $alreadyExisted = 0;
                $checkSt = Songs::$db->prepare("SELECT id_song FROM song_format WHERE id_song = :id_song AND id_format = :id_format LIMIT 1");
                $insertSt = Songs::$db->prepare("INSERT INTO song_format (id_song, id_format) VALUES (:id_song, :id_format)");
                foreach ($songIds as $idSong) {
                    $checkSt->execute([':id_song' => $idSong, ':id_format' => $idFormat]);
                    if ($checkSt->fetch(PDO::FETCH_ASSOC)) {
                        $alreadyExisted++;
                    } else {
                        $insertSt->execute([':id_song' => $idSong, ':id_format' => $idFormat]);
                        $created++;
                    }
                }
                sendSuccessResponse([
                    'created' => $created,
                    'already_existed' => $alreadyExisted,
                    'total' => count($songIds)
                ], "Abbinamento completato");
            } elseif ($id === null && $action === 'new-with-file') {
                // Nuova song da file: prossimo sg_file e prossimo sg_id dalla tabella songs, poi upload e INSERT
                if (!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
                    sendErrorResponse("Nessun file ricevuto", 400);
                }
                if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    $errMsg = array(
                        UPLOAD_ERR_INI_SIZE => 'File troppo grande',
                        UPLOAD_ERR_FORM_SIZE => 'File troppo grande',
                        UPLOAD_ERR_PARTIAL => 'Upload parziale',
                        UPLOAD_ERR_NO_FILE => 'Nessun file',
                        UPLOAD_ERR_NO_TMP_DIR => 'Directory temp mancante',
                        UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere',
                        UPLOAD_ERR_EXTENSION => 'Upload bloccato'
                    );
                    sendErrorResponse(isset($errMsg[$_FILES['file']['error']]) ? $errMsg[$_FILES['file']['error']] : 'Errore upload', 400);
                }
                $tmpPath = $_FILES['file']['tmp_name'];
                if (!file_exists($tmpPath)) {
                    sendErrorResponse("File temporaneo non trovato", 400);
                }
                $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'mp3') {
                    sendErrorResponse("Il file deve essere MP3", 400);
                }
                try {
                    $stFile = Songs::$db->query("SELECT `sg_file`, `sg_id` FROM `songs` ORDER BY `sg_file` DESC LIMIT 1");
                    $rowFile = $stFile ? $stFile->fetch(PDO::FETCH_ASSOC) : false;
                    $nextFile = ($rowFile && isset($rowFile['sg_file']) && $rowFile['sg_file'] !== null) ? (int)$rowFile['sg_file'] + 1 : 1;
                    $stId = Songs::$db->query("SELECT `sg_id` FROM `songs` ORDER BY `sg_id` DESC LIMIT 1");
                    $rowId = $stId ? $stId->fetch(PDO::FETCH_ASSOC) : false;
                    $nextSgId = ($rowId && isset($rowId['sg_id']) && $rowId['sg_id'] !== null) ? (int)$rowId['sg_id'] + 1 : 1;
                } catch (Exception $e) {
                    sendErrorResponse("Errore nel recupero prossimi ID: " . $e->getMessage(), 500);
                }
                $remoteDir = (defined('SONG_PATH') && SONG_PATH !== '/path/to/player/song/')
                    ? rtrim(SONG_PATH, '/')
                    : $_SERVER['DOCUMENT_ROOT'] . '/player/song';
                $newFilename = $nextFile . '.mp3';
                $remotePath = $remoteDir . '/' . $newFilename;
                if (!is_dir($remoteDir)) {
                    if (!mkdir($remoteDir, 0755, true)) {
                        sendErrorResponse("Impossibile creare la directory", 500);
                    }
                }
                if (!is_writable($remoteDir)) {
                    sendErrorResponse("Directory non scrivibile", 500);
                }
                $sgTitolo = isset($_POST['sg_titolo']) ? trim((string)$_POST['sg_titolo']) : '';
                $sgArtista = isset($_POST['sg_artista']) ? trim((string)$_POST['sg_artista']) : '';
                if (!rewriteMp3Metadata($tmpPath, $sgTitolo, $sgArtista, '', 'YourRadio')) {
                    sendErrorResponse("Impossibile riscrivere metadati MP3", 500);
                }
                if (!move_uploaded_file($tmpPath, $remotePath)) {
                    sendErrorResponse("Errore nel salvataggio del file", 500);
                }
                $fileSize = file_exists($remotePath) ? filesize($remotePath) : 0;
                $emptySong = array(
                    'sg_id' => $nextSgId,
                    'sg_file' => (string)$nextFile,
                    'sg_filesize' => $fileSize,
                    'sg_attivo' => 0,
                    'sg_titolo' => $sgTitolo,
                    'sg_artista' => $sgArtista,
                    'sg_anno' => 0,
                    'sg_artista2' => '',
                    'sg_artista3' => '',
                    'sg_diritti' => 0,
                    'sg_autori' => '',
                    'sg_casaDiscografica' => '',
                    'sg_etichetta' => '',
                    'sg_umoreId' => 0,
                    'sg_nazione' => '',
                    'formats' => array()
                );
                $newId = Songs::createSong($emptySong);
                if (!$newId) {
                    sendErrorResponse("Errore nella creazione della song", 500);
                }
                $song = Songs::selectSongById($newId);
                sendSuccessResponse($song[0], "Song creata con file", 201);
            } elseif ($id === null && $action === '') {
                // Crea nuova song
                $newId = Songs::createSong($data);
                if ($newId) {
                    $song = Songs::selectSongById($newId);
                    sendSuccessResponse($song[0], "Song creata con successo", 201);
                } else {
                    sendErrorResponse("Errore nella creazione della song", 500);
                }
            } elseif ($id !== null && $action === 'format') {
                // Aggiungi song_format
                if (!isset($data['id_format'])) {
                    sendErrorResponse("Parametro id_format richiesto", 400);
                }
                try {
                    $idSong = (int)$id;
                    $idFormat = (int)$data['id_format'];
                    
                    // Verifica se esiste già
                    $checkQuery = "SELECT id_song FROM song_format WHERE id_song = :id_song AND id_format = :id_format LIMIT 1";
                    $checkSt = Songs::$db->prepare($checkQuery);
                    $checkSt->execute([
                        ':id_song' => $idSong,
                        ':id_format' => $idFormat
                    ]);
                    $existing = $checkSt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing) {
                        sendSuccessResponse([
                            'id_song' => $idSong,
                            'id_format' => $idFormat,
                            'already_exists' => true
                        ], "Abbinamento già esistente");
                    } else {
                        // Inserisci
                        $insertQuery = "INSERT INTO `song_format` (`id_song`, `id_format`) VALUES (:id_song, :id_format)";
                        $insertSt = Songs::$db->prepare($insertQuery);
                        $insertSt->execute([
                            ':id_song' => $idSong,
                            ':id_format' => $idFormat
                        ]);
                        
                        sendSuccessResponse([
                            'id_song' => $idSong,
                            'id_format' => $idFormat,
                            'already_exists' => false
                        ], "Abbinamento creato con successo", 201);
                    }
                } catch (Exception $e) {
                    sendErrorResponse("Errore nell'inserimento del format: " . $e->getMessage(), 500);
                }
            } elseif ($id !== null && $action === 'upload') {
                // Carica file audio
                if (!isset($_FILES['file'])) {
                    sendErrorResponse("Nessun file ricevuto", 400);
                }
                
                if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    $errorMessages = array(
                        UPLOAD_ERR_INI_SIZE => 'Il file supera la dimensione massima consentita',
                        UPLOAD_ERR_FORM_SIZE => 'Il file supera la dimensione massima del form',
                        UPLOAD_ERR_PARTIAL => 'Il file è stato caricato solo parzialmente',
                        UPLOAD_ERR_NO_FILE => 'Nessun file è stato caricato',
                        UPLOAD_ERR_NO_TMP_DIR => 'Directory temporanea mancante',
                        UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file su disco',
                        UPLOAD_ERR_EXTENSION => 'Un\'estensione PHP ha bloccato il caricamento del file'
                    );
                    $errorMsg = isset($errorMessages[$_FILES['file']['error']]) 
                        ? $errorMessages[$_FILES['file']['error']] 
                        : 'Errore sconosciuto nel caricamento: ' . $_FILES['file']['error'];
                    sendErrorResponse($errorMsg, 400);
                }
                
                $file = $_FILES['file'];
                $filename = $file['name'];
                $tmpPath = $file['tmp_name'];
                
                // Verifica che il file temporaneo esista
                if (!file_exists($tmpPath)) {
                    sendErrorResponse("File temporaneo non trovato: " . $tmpPath, 400);
                }
                
                // Nome file: SOLO sg_file della scheda. Upload da song-scheda sovrascrive il file già presente con lo stesso nome.
                $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if ($fileExtension !== 'mp3') {
                    sendErrorResponse("Il file deve essere in formato MP3 (ricevuto: " . $fileExtension . ")", 400);
                }
                
                $songRow = Songs::selectSongById($id);
                $songData = !empty($songRow) ? $songRow[0] : array();
                $sgFileVal = isset($songData['sg_file']) ? $songData['sg_file'] : null;
                if ($sgFileVal === null || $sgFileVal === '') {
                    sendErrorResponse("La scheda non ha un nome file (sg_file) impostato. Impossibile caricare.", 400);
                }
                $sgFile = (string)$sgFileVal;
                $newFilename = $sgFile . '.mp3';
                
                // Percorso di destinazione sul server remoto
                $remotePath = $_SERVER['DOCUMENT_ROOT'] . '/player/song/' . $newFilename;
                $remoteDir = dirname($remotePath);
                
                // Crea la directory se non esiste
                if (!is_dir($remoteDir)) {
                    if (!mkdir($remoteDir, 0755, true)) {
                        sendErrorResponse("Impossibile creare la directory di destinazione: " . $remoteDir, 500);
                    }
                }
                
                // Verifica che la directory sia scrivibile
                if (!is_writable($remoteDir)) {
                    sendErrorResponse("La directory di destinazione non è scrivibile: " . $remoteDir, 500);
                }
                $metaTitle  = isset($songData['sg_titolo'])  ? trim((string)$songData['sg_titolo'])  : '';
                $metaArtist = isset($songData['sg_artista']) ? trim((string)$songData['sg_artista']) : '';
                $metaYear   = isset($songData['sg_anno'])    ? trim((string)$songData['sg_anno'])    : '';
                
                // Azzera metadati esistenti e scrivi solo: titolo, autore, anno, album = YourRadio
                if (!rewriteMp3Metadata($tmpPath, $metaTitle, $metaArtist, $metaYear, 'YourRadio')) {
                    sendErrorResponse("Impossibile riscrivere i metadati del file MP3", 500);
                }
                
                // Sposta il file caricato nella directory di destinazione
                if (!move_uploaded_file($tmpPath, $remotePath)) {
                    $lastError = error_get_last();
                    $errorMsg = "Errore nel salvataggio del file sul server";
                    if ($lastError) {
                        $errorMsg .= ": " . $lastError['message'];
                    }
                    sendErrorResponse($errorMsg . " (da: " . $tmpPath . " a: " . $remotePath . ")", 500);
                }
                
                // Verifica che il file sia stato spostato correttamente
                if (!file_exists($remotePath)) {
                    sendErrorResponse("Il file non è stato salvato correttamente: " . $remotePath, 500);
                }
                
                // Calcola il filesize del file salvato sul server
                $fileSize = filesize($remotePath);
                if ($fileSize === false) {
                    sendErrorResponse("Impossibile calcolare la dimensione del file salvato: " . $remotePath, 500);
                }
                
                // Aggiorna il database con il nome del file (sg_file) e il filesize
                try {
                    // Prima verifica se esiste già una song (diversa da questa) con lo stesso sg_file
                    $checkQuery = "SELECT `sg_id` FROM `songs` WHERE `sg_file` = :filename AND `sg_id` != :id";
                    $checkSt = Songs::$db->prepare($checkQuery);
                    $checkSt->execute(array(
                        ':filename' => $sgFile,
                        ':id' => $id
                    ));
                    $existingSong = $checkSt->fetch();
                    
                    // Se esiste già una song con questo sg_file, imposta il suo sg_file a NULL
                    if ($existingSong) {
                        $clearQuery = "UPDATE `songs` SET `sg_file` = NULL, `sg_filesize` = 0 WHERE `sg_id` = :other_id";
                        $clearSt = Songs::$db->prepare($clearQuery);
                        $clearSt->execute(array(':other_id' => $existingSong['sg_id']));
                    }
                    
                    // Ora aggiorna questa song con sg_file (nome file senza estensione) e sg_filesize
                    $updateQuery = "UPDATE `songs` SET `sg_file` = :filename, `sg_filesize` = :filesize WHERE `sg_id` = :id";
                    $updateSt = Songs::$db->prepare($updateQuery);
                    $result = $updateSt->execute(array(
                        ':filename' => $sgFile,
                        ':filesize' => $fileSize,
                        ':id' => $id
                    ));
                    
                    if (!$result) {
                        $errorInfo = $updateSt->errorInfo();
                        sendErrorResponse("Errore nell'aggiornamento del database: " . $errorInfo[2], 500);
                    }
                } catch (Exception $e) {
                    sendErrorResponse("Eccezione durante l'aggiornamento del database: " . $e->getMessage(), 500);
                }
                
                sendSuccessResponse(array(
                    'filename' => $newFilename,
                    'path' => '/player/song/' . $newFilename,
                    'size' => $fileSize
                ), "File caricato con successo");
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        case 'PUT':
        case 'PATCH':
            // Aggiorna song
            if ($id === null) {
                sendErrorResponse("ID song richiesto", 400);
            }
            $data['sg_id'] = $id;
            $result = Songs::updateSongById($data);
            if ($result) {
                sendSuccessResponse(['id' => $id], "Song aggiornata con successo");
            } else {
                sendErrorResponse("Errore nell'aggiornamento della song", 500);
            }
            break;
            
        case 'DELETE':
            // Elimina song
            if ($id === null) {
                sendErrorResponse("ID song richiesto", 400);
            }
            
            error_log("[API DELETE SONG] Richiesta cancellazione song ID: " . $id);
            
            $result = Songs::deleteById($id);
            if ($result) {
                error_log("[API DELETE SONG] Song ID " . $id . " eliminata con successo");
                sendSuccessResponse(['id' => $id], "Song eliminata con successo");
            } else {
                error_log("[API DELETE SONG] ERRORE durante l'eliminazione della song ID: " . $id);
                sendErrorResponse("Errore nell'eliminazione della song", 500);
            }
            break;
            
        default:
            sendErrorResponse("Method not allowed", 405);
    }
}

