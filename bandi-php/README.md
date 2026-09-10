# 🎓 Aggregatore bandi docenti — versione PHP + MySQL

Rileva automaticamente **bandi, concorsi e avvisi per professori e insegnanti**
pubblicati da scuole, università, USR e ministeri italiani: li filtra (esclude ATA,
appalti e forniture), ne estrae scadenza, ente e classe di concorso, e ti segnala
solo le novità.

Scritto in **PHP puro**: nessun Composer, nessuna libreria esterna, nessun Node.
Funziona sugli hosting condivisi italiani (Aruba, Register.it, Netsons, Keliweb, cPanel/Plesk).

---

## Requisiti

| Requisito | Note |
|---|---|
| PHP 7.4 o superiore | va bene anche PHP 8.x |
| Estensione `mbstring` | presente praticamente ovunque |
| `cURL` **oppure** `allow_url_fopen` | serve per scaricare le pagine |
| MySQL | **facoltativo**: senza database i bandi vanno su file JSON |
| Un cron | facoltativo: in alternativa si lancia da browser o con cron-job.org |

---

## Installazione (10 minuti)

### 1. Carica i file
Via FTP, copia la cartella `bandi-php/` nel tuo spazio web, per esempio in
`/public_html/bandi/` (così sarà raggiungibile su `https://tuo-dominio.it/bandi/`).

### 2. Permessi
Imposta i permessi **755** (o 775 se necessario) sulle cartelle `cache/` e `data/`:
devono essere scrivibili dal server.

### 3. Configurazione
Rinomina `config.example.php` in **`config.php`** e aprilo con un editor di testo:

```php
'db' => [
    'host' => 'localhost',        // spesso 'localhost'; Aruba usa un host dedicato
    'nome' => 'Sql123456_1',      // nome del database creato dal pannello
    'utente' => 'Sql123456',
    'password' => 'la_tua_password',
],
'token' => 'una-stringa-lunga-e-casuale',   // obbligatorio
'email_a' => 'tu@dominio.it',               // per ricevere il digest
```

> Se **non** vuoi usare MySQL, lascia `'nome' => ''`: i bandi verranno salvati in
> `data/bandi.json`. Funziona benissimo fino a qualche decina di migliaia di bandi.

### 4. Installazione guidata
Apri nel browser:

```
https://tuo-dominio.it/bandi/install.php?token=IL_TUO_TOKEN
```

La pagina verifica i requisiti dell'hosting, crea le tabelle MySQL e ti dà i link
per i passi successivi. **Quando hai finito, cancella `install.php` dal server.**

### 5. Verifica le fonti e fai la prima raccolta

```
https://tuo-dominio.it/bandi/cron.php?token=IL_TUO_TOKEN&azione=doctor   ← controlla le fonti
https://tuo-dominio.it/bandi/cron.php?token=IL_TUO_TOKEN                 ← raccoglie i bandi
https://tuo-dominio.it/bandi/index.php                                   ← l'elenco pubblico
```

---

## Aggiornamento automatico

### Cron del pannello (cPanel → "Cron Jobs", Plesk → "Attività pianificate")
Ogni giorno alle 7:00:

```
0 7 * * *  /usr/bin/php /home/TUO_UTENTE/public_html/bandi/cron.php
```

Il percorso di PHP cambia da hosting a hosting (`/usr/bin/php`, `/usr/local/bin/php`,
`/opt/php82/bin/php`): lo trovi nella documentazione del tuo provider.

### Se l'hosting non ha il cron
Usa un servizio gratuito come cron-job.org puntandolo a:

```
https://tuo-dominio.it/bandi/cron.php?token=IL_TUO_TOKEN
```

> Sugli hosting condivisi `max_execution_time` è spesso 30 secondi. Per questo
> `config.php` ha `'budget_secondi' => 20`: la raccolta si ferma prima del taglio e
> le fonti rimaste vengono elaborate alla chiamata successiva. Con poche fonti non
> te ne accorgerai nemmeno; con molte, imposta il cron ogni ora invece che ogni giorno.

---

## Le fonti

Le fonti predefinite sono in `sources.json`: Gazzetta Ufficiale (4ª Serie Speciale –
Concorsi ed esami), portale bandi del MUR, inPA, notizie del MIM, alcuni USR, EURAXESS.

> **Importante:** gli URL dei portali della PA cambiano spesso e alcune pagine caricano
> i contenuti via JavaScript (il programma legge solo l'HTML servito). Ogni fonte ha
> `"verificata": false` finché non l'hai controllata **dal tuo hosting** con
> `cron.php?token=...&azione=doctor`: il comando dice, per ogni fonte, quante voci ha
> estratto e quante sono risultate pertinenti. Le fonti in errore si correggono
> nell'URL o si disattivano con `"attiva": false`.

### Aggiungere la tua scuola o il tuo ateneo
Rinomina `sources.local.example.json` in `sources.local.json` e inserisci le tue fonti:

```json
{
  "sources": [
    { "id": "ic-verdi", "nome": "IC Verdi - Albo", "tipo": "html",
      "url": "https://www.icverdi.edu.it/albo-online/", "livello": "scuola" }
  ]
}
```

Tipi supportati: `rss` (RSS/Atom), `html` (link + testo circostante), `json` (API con
`jsonPath`, `urlTemplate` e mappatura `campi`). Una fonte locale con lo stesso `id` di
una predefinita la sostituisce: è così che si disattiva una fonte di serie.

---

## Pagine e API

| Indirizzo | Cosa fa |
|---|---|
| `index.php` | Elenco pubblico con filtri (livello, tipologia, scadenza, classe, ricerca) |
| `api.php` | JSON: `?livello=scuola&entro=30&classe=A-28&cerca=matematica&limite=100` |
| `api.php?azione=statistiche` | Conteggi per livello, categoria e regione |
| `api.php?azione=fonti` | Fonti configurate e loro stato |
| `cron.php?token=…` | Avvia la raccolta |
| `cron.php?token=…&azione=doctor` | Verifica le fonti |
| `install.php?token=…` | Installazione guidata (**da cancellare dopo l'uso**) |

`index.php` e `api.php` sono in **sola lettura** e non richiedono token: sono pensati
per essere pubblici. Tutto ciò che scrive richiede il token.

---

## Sicurezza

- Il file `.htaccess` incluso blocca l'accesso diretto a `config.php`, `lib/`, `data/`,
  `cache/` e `tests/`. **Se il tuo hosting usa nginx** (raro sui piani condivisi PHP)
  chiedi all'assistenza di negare l'accesso a quelle cartelle, oppure sposta `data/` e
  `cache/` fuori dalla cartella pubblica cambiando i percorsi in `config.php`.
- Cancella `install.php` dopo l'installazione.
- Il `token` va trattato come una password: chi lo conosce può avviare la raccolta.
- `config.php` non va mai condiviso: contiene la password del database.

---

## Uso corretto e limiti

- Il programma legge **solo pagine pubbliche**, rispetta `robots.txt` e il `Crawl-delay`,
  usa le richieste condizionali (ETag) e attende fra una richiesta e l'altra.
- Il riconoscimento è automatico e **può sbagliare in entrambe le direzioni**. Prima di
  presentare domanda apri sempre il bando originale sul sito dell'ente: fa fede solo il
  testo ufficiale (e, per i concorsi statali, la Gazzetta Ufficiale).
- Le scadenze sono dedotte dal testo: se non viene rilevata, il campo resta vuoto —
  non viene mai inventata.
- I PDF non vengono letti (solo il titolo del link).

---

## Test

```bash
php tests/run.php
```

39 test offline: parser RSS/Atom, estrazione link, date italiane, robots.txt,
classificatore, archivio (MySQL/SQL e JSON), filtri, digest, più un portale di prova
locale che verifica la raccolta completa end-to-end.

Puoi lanciarli anche sull'hosting via SSH, se disponibile: sono la verifica più rapida
che l'ambiente PHP sia adatto.

---

## Struttura

```
bandi-php/
├── index.php                  elenco pubblico
├── api.php                    API JSON in sola lettura
├── cron.php                   raccolta + verifica fonti
├── install.php                installazione guidata (da cancellare dopo)
├── config.example.php         → rinomina in config.php
├── sources.json               fonti predefinite
├── sources.local.example.json → rinomina in sources.local.json per le tue fonti
├── lib/                       Http, Robots, Feed, Html, Dates, Classifier, Store, Collector, Notifier
├── data/                      archivio JSON (se non usi MySQL)
├── cache/                     cache delle richieste
└── tests/                     test suite
```
