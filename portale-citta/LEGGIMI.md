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
| **Menu nell'intestazione** | Costruito dalle pagine della città, sempre in alto, con ordine e visibilità per pagina |
| **Sitemap** | Indice `/sitemap.xml` + una sitemap per città, aggiornate da sole |
| **robots.txt** | Generato, con l'indirizzo della sitemap |
| **SEO per pagina** | Title, description, canonical, Open Graph, meta geo, punteggio 0-100 |
| **Dati strutturati** | LocalBusiness, Service, FAQPage, BreadcrumbList |
| **Controllo duplicati** | Confronta i testi fra città e segnala quelli troppo simili |
| **Libreria immagini** | Caricamento multiplo, ridimensionamento automatico a 1800 px |
| **Assistente Gemini** | Propone introduzione, testo, descrizione e FAQ dai dati della città |
| **Sezioni a card** | Ogni sezione si disegna a riquadri o a elenco, con effetti dinamici |
| **CSS/HTML/JS** | Tre livelli: globale, per città, per pagina |
| **Bozze** | Le pagine non pubblicate sono 404 per tutti, tranne per chi è collegato |

---

## Dove metterlo e come chiamare la cartella

Il nome della cartella entra in **ogni** indirizzo:

```
chiaviitalia.it/NOMECARTELLA/trapani/duplicazione-chiavi-auto/
```

Cambiarlo dopo la pubblicazione significa reindirizzare tutte le pagine, quindi
si sceglie una volta sola.

**Regole:** minuscolo, senza accenti, senza spazi, senza underscore. Una parola
sola, corta. Con il trattino se servono due parole (`zone-servite`).

**Nomi da evitare:** `admin`, `wp-admin`, `wp-content`, `wp-includes`, `blog`,
`shop`, `feed`, `page`, `category`, `tag`, `author` — sono riservati o già usati
da WordPress. Ed evita qualsiasi nome che coincida con lo slug di una pagina
WordPress esistente: la cartella fisica vince e quella pagina diventa
irraggiungibile.

**Se nella radice c'è WordPress** non serve toccare niente: il suo `.htaccess`
lascia passare le cartelle reali (`RewriteCond %{REQUEST_FILENAME} !-d`), quindi
una cartella fisica non viene intercettata.

---

## Installazione

1. Carica il contenuto sul tuo hosting, dentro la cartella che hai scelto.
2. Dai il permesso di scrittura (755) alle cartelle `dati/` e `media/`.
3. Apri `install.php` nel browser e segui i tre passi:
   - **Database** — MySQL (consigliato) oppure SQLite se non puoi creare un database
   - **Il tuo sito** — nome dell'attività, indirizzo del portale, password
   - **Fine**
4. **Cancella `install.php` dal server.** L'amministrazione te lo ricorda.
5. Se il portale sta in una sottocartella, apri il `robots.txt` del **sito
   principale** e aggiungi la riga che trovi in SEO e sitemap. Google legge
   `robots.txt` solo dalla radice del dominio: quello dentro la sottocartella
   viene ignorato.

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

**2b. I quattro tipi di pagina** — la scelta più importante dell'editor:

| Tipo | A cosa serve |
|---|---|
| **Principale** | È l'indirizzo `/citta/`. Una sola per città |
| **Servizio** | Una pagina per **un** servizio. Genera lo schema `Service` |
| **Elenco servizi** | Mostra da sola **tutte** le pagine servizio della città, come schede con descrizione e prezzo. Genera lo schema `ItemList` |
| **Pagina fissa** | Chi siamo, contatti, recensioni… |

Il campo **Tipo di servizio collegato** non c'entra con l'elenco: serve solo a
legare fra loro la *stessa* pagina in città diverse (la "Duplicazione chiavi
auto" di Trapani con quella di Marsala), per ritrovarle insieme. Su una pagina
di tipo Elenco servizi va lasciato su "nessuno".

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

## Il menu della città

Sta **dentro l'intestazione**, in alto, accanto a logo e numero di telefono.
L'intestazione resta attaccata al bordo superiore mentre si scorre, quindi il
menu è sempre a un clic.

Si costruisce da solo dalle pagine della città. Per ogni pagina decidi se
mostrarla e in che ordine (editor della pagina → SEO → Posizione nel menu).

Si adatta da solo allo spazio, misurando le voci:

| Situazione | Come si presenta |
|---|---|
| Le voci ci stanno in riga | Una riga sola accanto a logo e telefono |
| Non ci stanno, schermo ≥ 900 px | Seconda riga dentro l'intestazione, **tutte le voci visibili** |
| Schermo < 900 px | Pulsante a tre righe, pannello a discesa con tutte le voci |
| JavaScript disattivato | Menu sempre aperto e impilato, nessun pulsante inerte |

La seconda riga è preferita al pulsante su schermo largo per un motivo
concreto: i collegamenti visibili nel codice sono collegamenti interni che
Google segue e pesa. Nasconderli dietro un pulsante quando c'è spazio è
sprecarli.

Il pannello si chiude con Esc, con un tocco fuori, e si richiude da solo
tornando a schermo largo.

---

## Sezioni a riquadri ed effetti

Impostazioni → Aspetto → **Sezioni ed effetti**.

**Stile delle sezioni**

- **Riquadri (card)** — cosa comprende, perché sceglierci, numeri, processo,
  zone, recensioni, team, orari, FAQ, altri servizi e altre città diventano
  griglie di riquadri che si adattano da sole alla larghezza
- **Elenco** — righe sottili, più compatto, come prima

**Effetti dinamici** (interruttore separato)

- comparsa a scalare quando la sezione entra nello schermo
- sollevamento e riga d'accento al passaggio del mouse
- alone che segue il cursore dentro il riquadro
- numeri che salgono da zero fino al valore, una volta sola

Gli effetti si spengono da soli in tre casi: se il visitatore ha chiesto meno
animazioni nelle impostazioni del suo sistema, se il JavaScript è disattivato,
e ovviamente se togli la spunta. In tutti e tre i casi la pagina resta completa
e leggibile: niente contenuti nascosti in attesa di un'animazione che non parte.

Non servono al posizionamento. Servono a chi legge.

---

## L'assistente Gemini

Con una chiave API (Impostazioni → Assistente) compaiono i pulsanti ✦ nell'editor:

| Pulsante | Dove | Cosa fa |
|---|---|---|
| Scrivi con l'assistente | Testi | Introduzione e testo di approfondimento |
| Scrivi la descrizione | SEO | La meta description, entro 155 caratteri |
| **Scrivi il titolo** | SEO | Il tag title, 45-60 caratteri, con la città dentro |
| Proponi 6 FAQ | FAQ | Domande e risposte, pronte per lo schema FAQPage |
| **Genera immagine** | SEO | Crea l'immagine di anteprima e la salva nella libreria |

Servono due modelli diversi: uno per il testo, uno per le immagini (di solito
ha "image" nel nome). Il pulsante "Carica i modelli disponibili" li divide da
solo fra i due campi.

**L'immagine generata è decorativa.** Va bene come anteprima per la
condivisione social; non spacciarla per una foto del tuo lavoro o della tua
sede. Per un'attività locale una foto vera vale molto di più, ed è uno dei
segnali che Google guarda.

Il prompt dell'immagine chiede esplicitamente niente testo, niente volti
riconoscibili, niente marchi e niente luoghi reali: un'immagine generica, non
una finta foto della tua città.

### Se il modello risponde male

Capita che un modello consegni i propri appunti invece del risultato — per
esempio il conteggio dei caratteri al posto del titolo. Il portale se ne
accorge e **non scrive niente nel campo**: te lo dice e riprovi.

I testi brevi vengono anche accorciati da soli se il modello esagera, tagliando
sull'ultimo spazio utile e togliendo eventuali preposizioni rimaste appese.
Il limite lo impone il codice, non l'istruzione al modello: chiedergli di
contare i caratteri è proprio ciò che lo fa sbagliare.

Riceve i dati che hai inserito — città, quartieri, servizio, prezzi, punti di
forza — e propone il testo. **Va sempre riletto**: il modello non conosce la tua
attività e inventa dettagli. Pubblicare testo generato e non rivisto è il modo
più rapido per farsi ignorare.

### Quando Google ritira un modello

Succede, e senza preavviso: un giorno l'assistente risponde *"questo modello non
è più disponibile"*. Per questo il campo **Modello** è libero, non un elenco
chiuso scritto nel codice.

Premi **Verifica la chiave e carica i modelli**: il portale chiede a Google quali
modelli accetta la *tua* chiave e riempie i suggerimenti. Se quello impostato non
è più valido lo sostituisce con il più recente, poi salvi.

Gli errori dell'API arrivano tradotti, con il rimedio: chiave non valida, chiave
senza permessi, limite di richieste superato, modello ritirato (con il nome del
sostituto che Google stesso indica).

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
├── tema/
│   ├── pagina.php     modello della pagina città
│   ├── sezioni.php    costruttori delle sezioni (riquadri o elenco)
│   ├── parti/         testa, barra, piè di pagina
│   ├── style.css      stile pubblico
│   └── script.js      comparsa, FAQ, alone del cursore, numeri
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
