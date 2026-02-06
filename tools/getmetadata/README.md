# Tools getmetadata – Estrazione metadati e import song

Questa cartella contiene due strumenti che lavorano in sequenza:

1. **extract_metadata.php** – legge i metadati ID3 dai file MP3 in `files/` e genera `metadata.json`.
2. **importBymeta.php** – legge `metadata.json`, copia i file in `newfiles/` e crea/aggiorna le song sul DB (yourradio.org) via API.

---

## 1. extract_metadata.php

### Cosa fa

Estrae i metadati (ID3v1 e ID3v2) da tutti i file MP3 nella cartella `tools/getmetadata/files` e scrive un file JSON (`metadata.json`) con titolo, durata, autore, anno, album, genere, ecc. per ogni traccia.

### Funzionamento a step

1. **Configurazione**
   - Imposta la cartella sorgente: `tools/getmetadata/files`.
   - Imposta il file di output: `tools/getmetadata/metadata.json`.
   - Estensioni ammesse: `mp3`.

2. **Caricamento getID3**
   - Cerca la libreria getID3: prima in `vendor/` (Composer), poi in `tools/getmetadata/getid3/getid3.php`.
   - Se non trova getID3, termina con errore (CLI o messaggio in output).

3. **Scansione della cartella `files/`**
   - Elenca tutti i file con estensione `.mp3` (ignora `.`, `..`, e eventuali file con nomi particolari).
   - Ordina i nomi in ordine alfabetico.

4. **Per ogni file MP3**
   - Chiama `getID3->analyze($path)`.
   - Se ci sono errori getID3, li registra in `error` e in un array `errors` globale.
   - Estrae i tag (prima ID3v2, poi ID3v1 come fallback): `title`, `artist`/`band`, `album`, `comment`, `genre`, `year`, `track_number`/`track`.
   - Calcola `durata` (MM:SS) e `durata_secondi` da `playtime_seconds`.
   - Compila un oggetto per la traccia: `filename`, `filesize`, `titolo`, `durata`, `durata_secondi`, `autori`, `anno_registrazione`, `album`, `commento`, `track`, `genre`, `error`.
   - Aggiunge l’oggetto all’array delle tracce.

5. **Scrittura output**
   - Costruisce l’oggetto finale: `generated` (timestamp), `folder` (path di `files/`), `count`, `errors`, `tracks`.
   - Codifica in JSON (pretty print, UTF-8) e scrive in `metadata.json`.
   - In CLI: stampa "OK: N file elaborati, output in ..." e eventuali errori su STDERR.
   - In ambiente web: invia header JSON e restituisce lo stesso JSON.

### Istruzioni per l’uso

- **Prerequisito**: libreria getID3 (vedi sotto).
- **Cartella**: metti i file MP3 in `tools/getmetadata/files/`.
- **Da terminale** (consigliato):
  ```bash
  cd tools/getmetadata
  php extract_metadata.php
  ```
- **Da browser**: apri `http://.../tools/getmetadata/extract_metadata.php`; in uscita riceverai il JSON (e il file `metadata.json` verrà comunque scritto sul server).

### Libreria getID3

- **Opzione A (Composer)**  
  Dalla root del progetto: `composer install`.

- **Opzione B (manuale)**  
  1. Scarica getID3: https://github.com/JamesHeinrich/getID3/archive/refs/heads/master.zip  
  2. Estrai e copia la cartella **getid3** (quella che contiene `getid3.php`) in `tools/getmetadata/`.  
  3. Verifica che esista: `tools/getmetadata/getid3/getid3.php`.

### API locale (senza DB)

Per avere un endpoint JSON che fa la stessa cosa (legge `files/`, aggiorna `metadata.json`, restituisce JSON):

- **URL**: `http://localhost:8000/api/local-metadata.php` (o `.../admin/api/local-metadata.php` se admin è in sottocartella).
- Non usa il database; legge solo i file in `tools/getmetadata/files` e aggiorna `metadata.json`.

---

## 2. importBymeta.php

### Cosa fa

Legge `metadata.json`, per ogni traccia verifica se esiste già una song con lo stesso `sg_filename_origin` sul DB (yourradio.org). Se non esiste: copia il file da `files/` a `newfiles/` con nome numerico (`sg_file.mp3`), crea la song e la relazione con un format. Se esiste: aggiunge solo la relazione format (se manca), senza duplicare la song.

### Variabili in cima al file (istruzioni)

Imposta queste variabili all’inizio dello script:

| Variabile     | Significato                                      | Esempio |
|---------------|---------------------------------------------------|--------|
| `$newFormat`  | ID del format da abbinare (tabella `song_format`) | `65`   |
| `$limitTracks`| `0` = tutti i track del JSON; `N` = solo i primi N | `0` o `5` |
| `$diritti`    | Valore del campo `sg_diritti` per le nuove song   | `3`    |

### Funzionamento a step

1. **Configurazione e timeout**
   - `set_time_limit(0)` e `ini_set('max_execution_time', 0)` per evitare timeout con molti track.

2. **Recupero prossimi ID**
   - Chiamata API `GET /api/songs/maxids` su yourradio.org.
   - Ottiene `next_sg_id` e `next_sg_file` da usare per le nuove song.
   - Se l’API fallisce, usa default (1, 1) e segnala l’errore in pagina.

3. **Caricamento track**
   - Legge `tools/getmetadata/metadata.json`.
   - Se `$limitTracks > 0`: prende solo i primi `$limitTracks` track; altrimenti tutti.

4. **Creazione cartella**
   - Crea `tools/getmetadata/newfiles/` se non esiste.

5. **Per ogni track** (in ordine):

   - **Filename vuoto** → salta (status `skip`), motivo: "filename vuoto".
   - **File assente in `files/`** → salta, motivo: "file non presente in /files/".
   - **Verifica esistenza song (anti-duplicati)**  
     Chiamata `GET /api/songs/byfilenameorigin?filename=...` (yourradio.org).
     - Se la chiamata **fallisce** (rete, 404, 500): **non** crea la song; salta la riga e logga "verifica esistenza fallita, riga saltata per evitare duplicati".
     - Se la risposta non contiene `data.exists`: salta e logga "risposta API incompleta".
   - **Se esiste già una song con quel `sg_filename_origin`** (`exists === true`):
     - Non copia il file e non crea una nuova song.
     - Chiamata `GET /api/songs/{sg_id}/format?id_format={$newFormat}` per verificare se il format è già abbinato.
     - Se il format **c’è già**: salta (status `existing_skip`), log: "song e format già presenti".
     - Se il format **manca**: `POST /api/songs/{sg_id}/format` con `id_format`; status `existing_format_added` o `db_error` in caso di errore.
   - **Se la song non esiste** (`exists === false`):
     - Assegna `sg_id` = contatore e `sg_file` = contatore.
     - Copia `files/{filename}` → `newfiles/{sg_file}.mp3` (solo in locale).
     - Se la copia fallisce: registra errore, incrementa comunque i contatori, non chiama l’API.
     - Se la copia va a buon fine: `POST /api/songs` con payload: `sg_id`, `sg_file`, `sg_filesize`, `sg_titolo`, `sg_artista`, `sg_anno`, `sg_filename_origin`, `sg_diritti`, `formats` = `[$newFormat]`. Il server crea la riga in `songs` e l’abbinamento in `song_format`.
     - Incrementa i contatori per la prossima song.

6. **Output**
   - Pagina HTML con riepilogo: query max ids, numero di track elaborati, per ogni track un box con stato (skip, existing_skip, existing_format_added, ready, copy_failed, db_error) e eventuale log/testo delle query mostrate a titolo informativo.

### Istruzioni per l’uso

1. **Prima esecuzione**: aver generato `metadata.json` con **extract_metadata.php** (e avere i file MP3 in `files/`).
2. **Configurare** in cima a `importBymeta.php`: `$newFormat`, `$limitTracks` (0 = tutti), `$diritti` (es. 3).
3. **Server yourradio.org**: deve esporre le API usate dallo script:
   - `GET /api/songs/maxids`
   - `GET /api/songs/byfilenameorigin?filename=...`
   - `GET /api/songs/{id}/format?id_format=...`
   - `POST /api/songs` (creazione song con eventuale `formats`)
   - `POST /api/songs/{id}/format` (aggiunta relazione format)
4. **Aprire in browser**: `http://.../tools/getmetadata/importBymeta.php`.  
   Il flusso parte al caricamento della pagina: vengono elaborati i track, mostrati i risultati e i log per riga.

### Cartelle

- **files/** – File MP3 sorgente (stessi nomi indicati in `metadata.json`).
- **newfiles/** – File copiati con nome numerico `{sg_file}.mp3` (solo locale; il DB su yourradio.org non riceve i file, solo i dati).

### Evitare duplicati

- Se la verifica `byfilenameorigin` non è disponibile o fallisce, lo script **non** crea la song e salta la riga, scrivendo nel log il motivo.
- Una nuova song viene creata solo quando l’API risponde esplicitamente `exists: false` per quel `filename`.

---

## Ordine consigliato

1. Mettere i MP3 in `tools/getmetadata/files/`.
2. Eseguire **extract_metadata.php** (CLI o browser) per generare/aggiornare `metadata.json`.
3. Configurare **importBymeta.php** (`$newFormat`, `$limitTracks`, `$diritti`) e aprire la pagina per l’import.
