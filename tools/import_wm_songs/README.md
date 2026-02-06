# Import Songs da Watermelon (`tools/import_wm_songs`)

Questa cartella contiene gli strumenti per importare le **songs** da file CSV esportati da **Watermelon** nel database di YourRadio.

## Struttura

- `import.php`  
  Pagina web di import (da aprire via browser) che:
  - elenca i file CSV disponibili in `csv/`
  - permette di scegliere il **format** di destinazione
  - esegue l’import riga per riga chiamando le API di YourRadio (`https://yourradio.org/api`)
  - scrive un log dettagliato in `log.txt`.

- `csv/`  
  Cartella che contiene i file CSV esportati da Watermelon, ad esempio:
  - `Au_Mai_12-25-metadata.csv`
  - `KFC_7-25-metadata.csv`
  - `ODStore.csv`
  - `testODStore.csv`
  - altri CSV da importare.

- `filenew/`  
  Cartella di appoggio per i file audio MP3 nuovi/da rinominare (es. `42004.mp3`).  
  Viene usata dallo script per verifiche/copie durante l’import.

> Nota: `import.php` si aspetta anche una cartella `file/` (fratello di `filenew/`) per contare i file MP3 già presenti.

## Come funziona `import.php`

### 1. Caricamento pagina

- Include `inc/config.php` e `inc/database.php` per avere le configurazioni dell’admin.
- Mostra un’interfaccia HTML per:
  - scegliere il CSV da `csv/`
  - scegliere l’ID del **format** di destinazione
  - avviare l’import.

### 2. Azioni AJAX supportate

- `?action=list_csv`  
  Restituisce JSON con la lista dei file CSV presenti in `csv/`.

- `?action=count_files`  
  Restituisce il numero di file MP3:
  - nella cartella `file/`
  - nella cartella `filenew/`.

- `?action=execute_import` (POST)  
  Parametri richiesti:
  - `csv_file` – nome del file CSV da importare (presente in `csv/`)
  - `id_format` – ID format da associare alle songs create

  Durante l’import:
  - abilita logging in `log.txt`
  - aumenta `max_execution_time` e `set_time_limit` per evitare timeout
  - usa funzioni helper (`callApi`) per chiamare le API REST di YourRadio (`https://yourradio.org/api/...`) invece di accedere direttamente al DB
  - scrive nel log le operazioni principali e gli eventuali errori.

### 3. Log

- `log.txt` (creato nella stessa cartella di `import.php`):
  - contiene timestamp + messaggi di avanzamento/import
  - utile per debug se l’import si interrompe o dà errori.

## Utilizzo in locale (con proxy)

Quando lavori in locale e devi parlare con `https://yourradio.org/api`, ricorda di usare il **proxy** (`api-proxy.php`) nel codice JS/PHP che chiama `import.php` o le API, per evitare problemi di CORS (come nel resto dell’admin).

In ambiente server (su `yourradio.org`), `import.php` può chiamare direttamente `https://yourradio.org/api` senza proxy.

