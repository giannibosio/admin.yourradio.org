# Estrazione metadati da file MP3 (tools/ewsong)

Lo script `extract_metadata.php` legge i metadati ID3 (ID3v1 e ID3v2) dai file MP3 nella stessa cartella e genera `metadata.json` con:

- **titolo** – Titolo del brano  
- **durata** – Formato MM:SS (es. 4:32)  
- **durata_secondi** – Durata in secondi  
- **autori** – Artista / autore  
- **anno_registrazione** – Anno  
- **album**, **commento**, **track**, **genre**

## Libreria getID3

Lo script usa la libreria **getID3** per leggere i tag ID3v2 e calcolare la durata.

### Installazione su server

**Opzione A – Composer** (dalla root del progetto):

```bash
composer install
```

**Opzione B – Installazione manuale**

1. Scarica getID3: https://github.com/JamesHeinrich/getID3/archive/refs/heads/master.zip  
2. Estrai l’archivio.  
3. Copia la cartella **getid3** (quella interna, che contiene `getid3.php`) in `tools/ewsong/`.  
   Risultato: `tools/ewsong/getid3/getid3.php` deve esistere.

## Esecuzione

- Da terminale: `php extract_metadata.php`  
- Da browser: apri `.../tools/ewsong/extract_metadata.php`  
  (viene creato/aggiornato `metadata.json` e in risposta vedi il JSON).
