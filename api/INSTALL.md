# Guida all'Installazione API YourRadio

## Requisiti

- PHP 7.0 o superiore
- MySQL/MariaDB
- Apache con mod_rewrite abilitato (o Nginx configurato)
- Estensioni PHP: PDO, PDO_MySQL, JSON

## Installazione

### 1. Copia dei File

Copiare l'intera cartella `api` sul server centrale dove risiede il database e i file MP3.

```bash
scp -r api/ user@server-central:/var/www/yourradio/
```

### 2. Configurazione

1. Copiare il file di esempio della configurazione:
```bash
cd /var/www/yourradio/api
cp config.example.php config.php
```

2. Modificare `config.php` con le credenziali corrette:
   - Credenziali database
   - Path corretti per `PLAYER_PATH` e `SONG_PATH`
   - Domini autorizzati per CORS

### 3. Permessi

Assicurarsi che la cartella `logs` esista e sia scrivibile:

```bash
mkdir -p logs
chmod 755 logs
chown www-data:www-data logs  # o l'utente del web server
```

### 4. Configurazione Web Server

#### Apache

Assicurarsi che mod_rewrite sia abilitato:

```bash
a2enmod rewrite
systemctl restart apache2
```

Il file `.htaccess` è già incluso e dovrebbe funzionare automaticamente.

#### Nginx

Aggiungere questa configurazione al virtual host:

```nginx
location /api {
    root /var/www/yourradio;
    index index.php;
    
    try_files $uri $uri/ /api/index.php?$query_string;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. Test

Testare l'installazione visitando:

```
https://yourradio.org/api
```

Dovresti vedere una risposta JSON con la lista degli endpoint disponibili.

## Verifica Funzionamento

### Test con cURL

```bash
# Test endpoint principale
curl https://yourradio.org/api

# Test lista gruppi
curl https://yourradio.org/api/gruppi

# Test lista songs
curl https://yourradio.org/api/songs?attivo=1
```

### Test con Browser

Aprire nel browser:
- `https://yourradio.org/api` - Dovrebbe mostrare la lista degli endpoint
- `https://yourradio.org/api/gruppi` - Dovrebbe restituire JSON con i gruppi

## Troubleshooting

### Errore 500 - Database Connection Failed

- Verificare le credenziali in `config.php`
- Verificare che il database sia accessibile dal server
- Controllare i log in `logs/error.log`

### Errore 404 - Not Found

- Verificare che mod_rewrite sia abilitato (Apache)
- Verificare la configurazione del virtual host
- Controllare che il file `.htaccess` sia presente

### CORS Errors

- Verificare che il dominio sia aggiunto in `ALLOWED_ORIGINS` in `config.php`
- Verificare che i header CORS siano configurati correttamente

### Permission Denied

- Verificare i permessi della cartella `logs`
- Verificare i permessi dei file PHP (644)
- Verificare che il web server abbia accesso ai file

## Sicurezza

⚠️ **IMPORTANTE**: Prima di mettere in produzione:

1. **Disabilitare display_errors** in `config.php`:
   ```php
   ini_set('display_errors', 0);
   ```

2. **Implementare autenticazione**: Le API attualmente non richiedono autenticazione. Si consiglia di implementare:
   - API Key authentication
   - JWT tokens
   - OAuth2

3. **Limitare accesso IP**: Configurare il firewall per limitare l'accesso alle API solo da IP autorizzati

4. **HTTPS**: Assicurarsi che tutte le comunicazioni avvengano via HTTPS

5. **Rate Limiting**: Implementare rate limiting per prevenire abusi

6. **Validazione Input**: Tutti gli input vengono già sanitizzati, ma verificare sempre i dati ricevuti

## Supporto

Per problemi durante l'installazione, consultare:
- I log in `logs/error.log`
- La documentazione completa in `README.md`
- Il team di sviluppo YourRadio

