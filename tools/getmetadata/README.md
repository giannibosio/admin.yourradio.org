# Estrazione metadati da file MP3 (tools/getmetadata)

Lo script `extract_metadata.php` + le API locali leggono i metadati ID3 (ID3v1 e ID3v2) dai file MP3 in `tools/getmetadata/files` e generano `metadata.json` con:

- **titolo** – Titolo del brano  
- **durata** – Formato MM:SS (es. 4:32)  
- **durata_secondi** – Durata in secondi  
- **autori** – Artista / autore  
- **anno_registrazione** – Anno  
- **album**, **commento**, **track**, **genre**

## Libreria getID3

Lo script usa la libreria **getID3** per leggere i tag ID3v2 e calcolare la durata.

### Installazione (solo locale / sviluppo)

**Opzione A – Composer** (dalla root del progetto):

```bash
composer install
```

**Opzione B – Installazione manuale**

1. Scarica getID3: https://github.com/JamesHeinrich/getID3/archive/refs/heads/master.zip  
2. Estrai l’archivio.  
3. Copia la cartella **getid3** (quella interna, che contiene `getid3.php`) in `tools/getmetadata/`.  
   Risultato: `tools/getmetadata/getid3/getid3.php` deve esistere.

## Esecuzione (script locale)

- Da terminale (dentro `tools/getmetadata`):  
  `php extract_metadata.php`  
  (viene creato/aggiornato `metadata.json` leggendo i file in `files/`).

> Nota: in ambiente web, a causa del layout dell’admin, `extract_metadata.php` può essere inglobato nell’HTML.  
> Per questo è preferibile usare l’API locale qui sotto.

## API locale senza DB

Per avere un endpoint JSON pulito **solo in locale** e **senza usare il database** è disponibile:

- `api/local-metadata.php`

URL tipico in locale:

- Se il document root è `admin.yourradio.org`:  
  `http://localhost:8000/api/local-metadata.php`
- Se `admin` è una sottocartella:  
  `http://localhost:8000/admin/api/local-metadata.php`

Questa API:

- legge i file MP3 in `tools/getmetadata/files`
- usa `getID3` per estrarre i metadati
- aggiorna `tools/getmetadata/metadata.json`
- restituisce un JSON del tipo:

```json
{
  "success": true,
  "data": {
    "generated": "YYYY-MM-DD HH:MM:SS",
    "folder": ".../tools/getmetadata/files",
    "count": 3,
    "errors": [],
    "tracks": [ ... ]
  }
}
```
