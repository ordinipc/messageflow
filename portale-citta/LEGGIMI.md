# Portale Città

Un portale PHP autonomo per creare e gestire pagine geolocalizzate: una città,
tante pagine, indirizzi del tipo `/trapani/duplicazione-chiavi-auto/`.

Non è un plugin: gira da solo, con il suo database e la sua amministrazione.

---

## Cosa fa

| | |
|---|---|
| **Città illimitate** | Ognuna con slug, provincia, CAP, coordinate, contatti, orari, zone servite |
| **Pagine per città** | Principale, servizi, chi siamo, contatti, FAQ… Ogni città ha le sue |
| **Menu automatico** | Costruito dalle pagine della città, con ordine e visibilità per pagina |
| **Sitemap** | Indice `/sitemap.xml` + una sitemap per città, aggiornate da sole |
| **robots.txt** | Generato, con l'indirizzo della sitemap |
| **SEO per pagina** | Title, description, canonical, Open Graph, meta geo, punteggio 0-100 |
| **Dati strutturati** | LocalBusiness, Service, FAQPage, BreadcrumbList |
| **Controllo duplicati** | Confronta i testi fra città e segnala quelli troppo simili |
| **Libreria immagini** | Caricamento multiplo, ridimensionamento automatico a 1800 px |
| **Assistente Gemini** | Propone introduzione, testo, descrizione e FAQ dai dati della città |
| **CSS/HTML/JS** | Tre livelli: globale, per città, per pagina |
| **Bozze** | Le pagine non pubblicate sono 404 per tutti, tranne per chi è collegato |

---

## Installazione

1. Carica la cartella `portale-citta` sul tuo hosting.
   Può stare nella radice o in una sottocartella (per esempio `/citta/`).
2. Dai il permesso di scrittura (755) alle cartelle `dati/` e `media/`.
3. Apri `install.php` nel browser e segui i tre passi:
   - **Database** — MySQL (consigliato) oppure SQLite se non puoi creare un database
   - **Il tuo sito** — nome dell'attività, indirizzo del portale, password
   - **Fine**
4. **Cancella `install.php` dal server.** L'amministrazione te lo ricorda.

L'amministrazione è su `tuosito.it/admin.php`.

### Se il server usa nginx

`.htaccess` non funziona. Chiedi all'hosting due regole:

```nginx
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ ^/(dati|inc)/ { deny all; }
```

### Provare in locale

```bash
php -S localhost:8000 router-dev.php
```

---

## Come si lavora

**1. Tipi di servizio** → definisci una volta sola il catalogo
("Duplicazione chiavi auto", "Apertura porte"…). Non sono pagine: sono modelli.

**2. Nuova città** → compili i dati e spunti le pagine da creare.
Il portale genera tutto in bozza, vuoto.

**3. Pagine** → apri ogni pagina e scrivi il testo. La colonna di destra mostra
il punteggio SEO e l'anteprima del risultato su Google.

**4. Pubblica** → una pagina è online solo se sia la pagina sia la città sono
pubblicate.

**5. SEO e sitemap** → prendi l'indirizzo della sitemap e mettilo in Search Console.

---

## La regola che decide tutto

Il portale può creare venti città in dieci minuti. Se i testi sono gli stessi
con il nome cambiato, Google ne indicizza una e scarta diciannove.

Devono essere **diversi per città**:

- il testo di approfondimento della pagina
- le FAQ
- i punti di "perché sceglierci"
- le recensioni

Possono restare uguali: cosa comprende il servizio, i passi del processo,
la fascia di prezzo.

La schermata **SEO e sitemap** ti dice quando due testi sono troppo simili.
Sopra l'88% di somiglianza hai un problema.

---

## L'assistente Gemini

Con una chiave API (Impostazioni → Assistente) compaiono i pulsanti
"✦ Scrivi con l'assistente" nell'editor.

Riceve i dati che hai inserito — città, quartieri, servizio, prezzi, punti di
forza — e propone il testo. **Va sempre riletto**: il modello non conosce la tua
attività e inventa dettagli. Pubblicare testo generato e non rivisto è il modo
più rapido per farsi ignorare.

---

## Struttura dei file

```
portale-citta/
├── index.php          front controller pubblico (città, pagine, sitemap, robots)
├── admin.php          front controller dell'amministrazione
├── install.php        installazione guidata (da cancellare dopo)
├── config.php         dati del database (creato dall'installazione)
├── router-dev.php     solo per le prove in locale
├── inc/
│   ├── core.php       funzioni di base
│   ├── db.php         connessione e creazione delle tabelle
│   ├── archivio.php   lettura/scrittura di città, pagine, servizi
│   ├── auth.php       accesso e token anti-CSRF
│   ├── media.php      immagini
│   ├── seo.php        titoli, descrizioni, punteggio, sitemap, robots
│   ├── schema.php     dati strutturati JSON-LD
│   └── ai.php         assistente Gemini
├── admin/             schermate dell'amministrazione + admin.css/js
├── tema/              modelli pubblici, style.css, script.js
├── dati/              database SQLite (se usato) — non accessibile dal web
└── media/             immagini caricate
```

---

## Tabelle del database

Prefisso predefinito `pc_`, si cambia in fase di installazione.

| Tabella | Contenuto |
|---|---|
| `pc_impostazioni` | Coppie chiave/valore |
| `pc_servizi` | Catalogo dei tipi di servizio |
| `pc_citta` | Una riga per città |
| `pc_pagine` | Una riga per pagina, legata a una città |
| `pc_media` | Immagini caricate |

I campi ripetibili (orari, FAQ, recensioni, processo, numeri, team) sono
salvati in JSON dentro la loro colonna.

---

## Aggiornare

Sovrascrivi tutto **tranne** `config.php`, `dati/` e `media/`.
Le tabelle mancanti vengono create da sole al primo accesso.

---

## Sicurezza

- Password con `password_hash`, sessione con cookie HttpOnly e SameSite
- Token anti-CSRF su ogni azione che modifica dati
- Ogni valore in uscita passa da `htmlspecialchars`; i dati strutturati usano
  i flag `JSON_HEX_*`, così nessun testo può chiudere il tag `<script>`
- Gli indirizzi `javascript:` e `data:` vengono scartati
- I file caricati sono controllati con `finfo`, non con l'estensione
- In `media/` l'esecuzione di PHP è bloccata
- `config.php`, `inc/` e `dati/` non sono accessibili dal web
- Con SQLite il file del database prende un nome casuale, così non è
  indovinabile anche se l'hosting ignora `.htaccess`

---

## Requisiti

PHP 7.4 o superiore, con `pdo_mysql` (o `pdo_sqlite`), `mbstring`, `json`.
Facoltativi: `gd` per ridimensionare le immagini, `curl` per l'assistente.
