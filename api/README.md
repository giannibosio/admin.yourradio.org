# YourRadio API

API RESTful per l'applicazione YourRadio Admin. Queste API devono essere installate sul server centrale dove risiede il database e i file MP3.

## Base URL

```
https://yourradio.org/api
```

## Installazione

1. Copiare l'intera cartella `api` sul server centrale
2. Configurare `config.php` con le credenziali del database corrette
3. Aggiornare i path `PLAYER_PATH` e `SONG_PATH` in `config.php`
4. Configurare il web server per puntare alla cartella `api`
5. Assicurarsi che la cartella `logs` esista e sia scrivibile (per gli error log)

## Autenticazione

Attualmente le API non richiedono autenticazione. Si consiglia di implementare un sistema di autenticazione (API Key, JWT, ecc.) per la produzione.

## Endpoint Disponibili

### Gruppi

#### Lista tutti i gruppi
```
GET /api/gruppi
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nome": "BURGER KING",
      "players": 5,
      "attivo": 1
    }
  ]
}
```

#### Dettaglio gruppo
```
GET /api/gruppi/{id}
```

#### Lista players del gruppo
```
GET /api/gruppi/{id}/players
```

#### Lista sottogruppi
```
GET /api/gruppi/{id}/subgruppi
```

#### Lista campagne del gruppo
```
GET /api/gruppi/{id}/campagne
```

#### Crea nuovo gruppo
```
POST /api/gruppi
Content-Type: application/json

{
  "nome": "Nuovo Gruppo"
}
```

#### Aggiorna gruppo
```
PUT /api/gruppi/{id}
Content-Type: application/json

{
  "nome": "Gruppo Aggiornato",
  "active": 1,
  "rss_id": 2
}
```

### Songs

#### Lista songs (con filtri opzionali)
```
GET /api/songs?attivo=1&format=2&nazionalita=1&strategia=3&sex=Maschile&umore=1&ritmo=3&energia=4&anno=2020&periodo=1&genere=4&diritti=0
```

**Parametri query disponibili:**
- `attivo`: 1 (attive), 2 (non attive)
- `format`: ID formato (binario)
- `nazionalita`: 1 (Italiana), 2 (Straniera)
- `strategia`: ID strategia
- `sex`: Maschile, Femminile, Strumentale
- `umore`: ID umore
- `ritmo`: ID ritmo
- `energia`: ID energia
- `anno`: Anno
- `periodo`: ID periodo
- `genere`: ID genere
- `diritti`: 0 (SIAE), 1 (Creative)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "artista": "Artista",
      "titolo": "Titolo",
      "anno": 2020,
      "attivo": 1
    }
  ]
}
```

#### Dettaglio song
```
GET /api/songs/{id}
```

#### Aggiorna song
```
PUT /api/songs/{id}
Content-Type: application/json

{
  "sg_titolo": "Nuovo Titolo",
  "sg_artista": "Nuovo Artista",
  "sg_attivo": 1
}
```

#### Elimina song
```
DELETE /api/songs/{id}
```

### Players

#### Lista players per gruppo
```
GET /api/players?gruppo_id=1
```

#### Dettaglio player
```
GET /api/players/{id}
```

### Jingles

#### Lista jingles per gruppo
```
GET /api/jingles?gruppo_id=1
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nome": "JINGLE 1",
      "attivo": 1,
      "programmato": 1,
      "dal": "2024-01-01",
      "al": "2024-12-31",
      "status": "Programmazione : dal 2024-01-01 al 2024-12-31"
    }
  ]
}
```

### Spot

#### Lista spot network per gruppo
```
GET /api/spot/net?gruppo_id=1
```

#### Lista spot locali per gruppo
```
GET /api/spot/loc?gruppo_id=1
```

### Rubriche

#### Lista rubriche per gruppo
```
GET /api/rubriche?gruppo_id=1
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nome": "RUBRICA SPECIALE",
      "files": 5,
      "status": "Attivo"
    }
  ]
}
```

### Utenti

#### Lista tutti gli utenti
```
GET /api/utenti
```

#### Dettaglio utente
```
GET /api/utenti/{id}
```

#### Cambia password utente
```
PUT /api/utenti/{id}/password
Content-Type: application/json

{
  "newpass": "nuovapassword"
}
```

### Monitor

#### Lista players monitorati
```
GET /api/monitor
GET /api/monitor?gruppo=BURGER KING
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "player_id": 1,
      "gruppo": "BURGER KING",
      "nome": "PLAYER 1",
      "ping": "2024-01-15 10:30:00",
      "ip": "192.168.1.100",
      "sd": "32GB-45%",
      "sd_status": 1,
      "status": 1,
      "type": "RASPI"
    }
  ]
}
```

**Status codes:**
- `1`: Online (ultimo ping < 1 ora fa)
- `2`: Offline (ultimo ping > 1 ora fa)
- `3`: Offline da molto tempo (ultimo ping > 24 ore fa)

**SD Status codes:**
- `0`: Non disponibile
- `1`: Normale (< 70%)
- `2`: Attenzione (70-90%)
- `3`: Critico (> 90%)
- `4`: PC (non applicabile)

#### Dettaglio player monitorato
```
GET /api/monitor/player/{id}
```

#### Lista ping di un player
```
GET /api/monitor/ping/{id}
```

### Formats

#### Lista tutti i formati disponibili
```
GET /api/formats
```

## Formato Risposta Standard

### Successo
```json
{
  "success": true,
  "data": [...],
  "message": "Operazione completata con successo" // opzionale
}
```

### Errore
```json
{
  "success": false,
  "error": {
    "message": "Messaggio di errore",
    "code": "ERROR_CODE" // opzionale
  }
}
```

## Codici di Stato HTTP

- `200`: Successo
- `400`: Richiesta non valida
- `404`: Risorsa non trovata
- `405`: Metodo non consentito
- `500`: Errore interno del server

## CORS

Le API supportano CORS per i seguenti domini (configurabili in `config.php`):
- `https://admin.yourradio.org`
- `http://localhost:3000`
- `http://localhost:8080`

## Note

- Tutti i timestamp sono in formato ISO 8601 o formato MySQL standard
- I nomi vengono restituiti in maiuscolo per coerenza con il sistema esistente
- Le date vengono formattate secondo le convenzioni del sistema originale

## Esempi di Utilizzo

### Fetch in JavaScript

```javascript
// Lista gruppi
fetch('https://yourradio.org/api/gruppi')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log(data.data);
    }
  });

// Lista songs con filtri
fetch('https://yourradio.org/api/songs?attivo=1&nazionalita=1')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log(data.data);
    }
  });

// Aggiorna song
fetch('https://yourradio.org/api/songs/123', {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    sg_titolo: 'Nuovo Titolo',
    sg_attivo: 1
  })
})
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('Song aggiornata');
    }
  });
```

## Supporto

Per problemi o domande, contattare il team di sviluppo YourRadio.

