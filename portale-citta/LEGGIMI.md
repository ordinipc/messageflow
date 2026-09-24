# Portale Città

Un portale PHP autonomo per creare e gestire pagine geolocalizzate: una città,
tante pagine, indirizzi del tipo `/trapani/duplicazione-chiavi-auto/`.

Non è un plugin: gira da solo, con il suo database e la sua amministrazione.

---

## Cosa fa

| | |
|---|---|
| **Città illimitate** | Ognuna con slug, provincia, CAP, coordinate, contatti, **denominazione sociale**, **partita IVA**, **social**, orari, zone servite |
| **Pagine per città** | Principale, servizi, chi siamo, contatti, FAQ… Ogni città ha le sue |
| **Menu nell'intestazione** | Costruito dalle pagine della città, sempre in alto, con ordine e visibilità per pagina |
| **Sitemap** | Indice `/sitemap.xml` + una sitemap per città, aggiornate da sole |
| **robots.txt** | Generato, con l'indirizzo della sitemap e i crawler IA nominati uno per uno |
| **Editor di testo** | Barra con grassetto, corsivo, sottotitoli, elenchi e collegamenti |
| **Icone nelle card** | Sedici segni disegnati dentro il portale, uno per tipo di servizio |
| **Tag per pagina** | Le pastiglie sotto il testo che riassumono di cosa parla |
| **SEO per pagina** | Title, description, canonical, Open Graph, meta geo, punteggio 0-100 |
| **Dati strutturati** | LocalBusiness (con social, fascia di prezzo, mappa), Service, FAQPage, BreadcrumbList, ItemList, BlogPosting |
| **GEO** | `/llms.txt`, interruttore per gli assistenti IA, riepilogo di cosa vedono |
| **Controllo duplicati** | Confronta i testi fra città e segnala quelli troppo simili |
| **Libreria immagini** | Caricamento multiplo, ridimensionamento automatico a 1800 px |
| **Assistente Gemini** | Propone introduzione, testo, descrizione e FAQ dai dati della città |
| **Blog per città** | Articoli con elenco paginato, schema BlogPosting, importazione da WordPress |
| **Modulo di contatto** | Con recapiti completi, anti-spam senza captcha |
| **Home del portale** | La pagina d'ingresso e il piè di pagina si scrivono dal pannello |
| **Due stili grafici** | Vetrina (intestazione a tutta larghezza) o Classico, con larghezza, altezza del logo e corpo del menu regolabili |
| **Shortcode per WordPress** | Ogni pagina (o singola sezione) si incolla dentro WordPress |
| **Sezioni per pagina** | Ogni pagina sceglie cosa mostrare, **in che ordine** e **in quale colonna**: non escono tutte uguali |
| **Sezioni a card** | Ogni sezione si disegna a riquadri o a elenco, con immagini di anteprima ed effetti dinamici |
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

### «Indirizzo del portale» non è un permalink

In **Impostazioni → Generale** c'è *Indirizzo del portale*. Quel campo dice
**dove i file stanno**, non dove vorresti che stessero: togliere `/zone` da lì
non sposta niente e non accorcia gli indirizzi. Manda solo canonical, sitemap,
menu e ogni collegamento interno su indirizzi dove non c'è nulla — e quelle
pagine, nella radice, le risponde WordPress con un 404.

Il portale se ne accorge: se l'indirizzo scritto non combacia con la cartella
da cui sta davvero girando, un avviso rosso compare sotto il campo e nella
**Diagnostica** del Pannello, con scritto qual è la cartella vera. Il confronto
è solo sulla cartella, non sul dominio: fra `www` e senza `www`, o dietro a un
proxy, l'host può legittimamente essere diverso.

### Nascondere la cartella dagli indirizzi

Altro discorso è **non farla vedere**, tenendo i file dove sono. Si può, e il
portale ti prepara tutto: **SEO e sitemap → «Togliere /zone dagli indirizzi»**.

Come funziona: alcune righe di riscrittura nel `.htaccess` della radice dicono
ad Apache che `/trapani/` va servito dai file dentro `/zone/`. I file non si
spostano, si sposta solo l'indirizzo che si vede.

I tre passi, nell'ordine — e l'ordine conta:

1. **Copia il blocco** che trovi già scritto in quella schermata, nel
   `.htaccess` della radice, **prima** di `# BEGIN WordPress`. Sotto, WordPress
   se le prenderebbe tutte lui.
2. **Premi «Prova gli indirizzi corti»**: il portale chiede al server, città per
   città, se rispondono. Solo quando sono tutte ✓ si va avanti.
3. **In Impostazioni** togli `/zone` dall'indirizzo del portale e spunta
   *Nascondi la cartella*. Quella spunta serve anche a far tacere l'avviso di
   prima, che altrimenti scatterebbe proprio adesso.

Il pulsante di prova serve anche dopo: **ogni città nuova va aggiunta alle
regole**. Se te ne dimentichi, quella città risponde 404 e la prova te lo dice
con il suo nome.

**Tre cose da sapere prima di farlo.**

- Una pagina di WordPress che si chiama come una città diventa irraggiungibile:
  vince il portale.
- Se un plugin SEO gestisce già `/sitemap.xml`, togli quella riga dal blocco e
  lascia la sitemap del portale dentro la cartella.
- La pagina d'ingresso del portale, quella con l'elenco delle città,
  all'indirizzo corto è la home di WordPress. Per mostrare l'elenco dentro
  WordPress c'è lo shortcode `[portale_citta_zone]`.

### L'alternativa: il sottodominio

| Come | Cosa comporta |
|---|---|
| **Riscrittura** (sopra) | Gli indirizzi diventano `chiaviitalia.it/trapani/`. I file restano in `/zone/`. Niente da spostare |
| **Sottodominio** — `zone.chiaviitalia.it` | Sposti i file lì e cambi l'indirizzo del portale. Più pulito da gestire, ma un sottodominio non eredita l'autorità del dominio principale come fa una sottocartella |

In tutti e due i casi, se le pagine sono già indicizzate servono i **redirect
301** dai vecchi indirizzi ai nuovi, o perdi il lavoro fatto con Google.

---

## Installazione

1. Carica il contenuto sul tuo hosting, dentro la cartella che hai scelto.
2. Dai il permesso di scrittura (755) alle cartelle `dati/` e `media/`.
3. Apri `install.php` nel browser e segui i tre passi:
   - **Database** — MySQL (consigliato) oppure SQLite se non puoi creare un database
   - **Il tuo sito** — nome dell'attività, indirizzo del portale, nome utente,
     email e password
   - **Fine**
4. **Cancella `install.php` dal server.** L'amministrazione te lo ricorda.
5. Se il portale sta in una sottocartella, apri il `robots.txt` del **sito
   principale** e aggiungi la riga che trovi in SEO e sitemap. Google legge
   `robots.txt` solo dalla radice del dominio: quello dentro la sottocartella
   viene ignorato.

L'amministrazione è su `tuosito.it/admin.php`.

### Dove porta il logo

Il logo in alto riporta al **sito principale**, su ogni pagina del portale:
home, città, pagine, articoli e anche la pagina di errore. L'indirizzo si
scrive in Impostazioni → Sito → *Indirizzo del sito principale*, e lo chiede
anche l'installazione.

Se lo lasci vuoto il portale lo ricava da sé: quando sta in una sottocartella
(`tuosito.it/zone`) il logo porta alla **radice del dominio**, cioè
`tuosito.it/`, non alla cartella del portale. Con il portale installato sulla
radice i due coincidono.

### Chi entra nel pannello

Gli accessi si gestiscono da **Utenti**. Ogni persona ha il suo nome e la sua
password, così dal pannello si vede sempre chi è collegato e quando è entrato
l'ultima volta.

Ci sono due ruoli:

| Ruolo | Cosa può fare |
|---|---|
| **Amministratore** | tutto, comprese le Impostazioni e la gestione degli Utenti |
| **Redattore** | città, pagine, menu, articoli, immagini, SEO — ma non Impostazioni né Utenti |

Un utente si può **sospendere** invece di eliminarlo: l'account resta ma non
entra più, e se in quel momento era collegato viene rimandato al login.

Il portale non si lascia chiudere fuori: l'ultimo amministratore attivo non si
può declassare, sospendere o eliminare, e nessuno può eliminare sé stesso.

La schermata di accesso ha sempre due campi: **nome utente o email** e
password. Finché c'è un solo utente il primo non è obbligatorio e non fa da
filtro — non c'è nessuno da distinguere, decide la password. Serve anche a
non farsi rifiutare una password giusta quando il gestore di password del
browser riempie il campo da sé con un indirizzo che non corrisponde.

Dal secondo utente in poi il campo diventa obbligatorio e deve combaciare:
va bene il nome utente oppure l'email della persona.

> **Aggiorni da una versione precedente?** La password che usavi continua a
> funzionare: diventa l'utente `admin`. Il primo accesso al pannello fa tutto
> da solo, non devi toccare niente.

### Se il server usa nginx

`.htaccess` non funziona. Chiedi all'hosting due regole:

```nginx
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ ^/(dati|inc)/ { deny all; }
```

Per **nascondere la cartella** su nginx il blocco `.htaccess` non serve: chiedi
all'hosting l'equivalente, con l'elenco delle città che trovi nella schermata
SEO e sitemap.

```nginx
location ~ ^/(tema|media)/ { root /percorso/della/radice/zone; }
location ~ ^/(trapani|marsala)(/|$) { try_files $uri /zone/index.php; }
```

### Provare in locale

```bash
php -S localhost:8000 router-dev.php
```

---

## Come si lavora

**1. Tipi di servizio** → definisci una volta sola il catalogo
("Duplicazione chiavi auto", "Apertura porte"…).

⚠️ **Un tipo di servizio non è una pagina: è un modello.** Finché non generi
la pagina, quel servizio non compare da nessuna parte sul sito — né nel menu,
né nell'elenco dei servizi. Nella colonna "Pagine collegate" leggi quante
città hanno già la pagina di quel servizio.

Le pagine si creano in due momenti:
- alla creazione della città, spuntando i servizi
- **dopo**, da Pagine → riquadro *"Servizi del catalogo senza una pagina"*, che
  compare da solo quando un tipo di servizio non ha ancora la sua pagina lì

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

**6. Articoli** → scrivili a mano oppure importali da un'esportazione WordPress.

---

## Il blog

Ogni città può avere il suo blog: una pagina di tipo **Blog** elenca gli
articoli di quella città, dodici per volta, e ogni articolo vive a
`/citta/blog/titolo-articolo/` con il suo schema `BlogPosting`.

### Importare da WordPress

In WordPress: Strumenti → Esporta → Articoli. Poi qui, in **Importa**:

1. Carichi il file (`.xml` o `.zip`). Se supera il limite di caricamento del
   server, lo metti via FTP in `dati/import/` e compare da solo nell'elenco
2. **Analizza il file**: ti dice quanti articoli contiene e come verrebbero
   smistati fra le tue città
3. Scegli le città, quanti per volta, e se pubblicarli subito o lasciarli in bozza

Lo smistamento legge il titolo e lo confronta con i nomi delle tue città: un
articolo su Marsala va a Marsala, **se quella città esiste nel portale**. Vince
il nome che compare più avanti nel titolo, perché la geolocalizzazione sta di
solito in fondo. Quelli che non nominano nessuna città vanno nella riserva.

### Se vuoi una città sola

Va benissimo: imposti la **città di riserva** e importi. Tutti gli articoli
finiscono lì, compresi quelli che nominano altri comuni. Non devi fare
nient'altro.

L'analisi elenca comunque, in un blocco richiudibile, le **località nominate
nei titoli** che non sono ancora città del portale. È solo un'informazione:
niente è spuntato e niente viene creato se non lo chiedi tu.

### Se invece vuoi una città per comune

Nello stesso blocco spunti le località che meritano pagine proprie e le crei
in un colpo solo (nascono in bozza). Poi rianalizzi il file: gli articoli si
smistano da soli.

Su un'esportazione reale di 1750 articoli con la sola Trapani: 1567 riconosciuti
e 183 alla riserva. Creando le 16 località nominate da almeno tre articoli:
1731 riconosciuti e 19 alla riserva, divisi fra 17 città.

Il file si legge in streaming: un'esportazione da 40 MB con 1750 articoli
si importa in pochi secondi senza superare i 6 MB di memoria.

Il testo viene ripulito: via commenti di blocco, shortcode, script, iframe e
attributi. I paragrafi vengono ricostruiti anche quando WordPress non li aveva
salvati come `<p>`.

### Dopo l'importazione

- **Riscrivi i link interni** — i link fra articoli puntano ancora al sito di
  origine: questo li fa puntare alle pagine del portale
- **SEO e sitemap** prepara le regole di redirect 301 dai vecchi indirizzi ai nuovi

⚠️ **Un articolo importato resta pubblicato anche sul sito di origine.** Lo
stesso testo a due indirizzi dello stesso dominio si fa concorrenza da solo.
Delle due l'una: o lo tieni dov'è e non lo pubblichi qui, o lo pubblichi qui e
reindirizzi il vecchio indirizzo.

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

## Farsi trovare dagli assistenti IA (GEO)

La SEO serve a finire nei dieci link blu. Il **GEO** serve a finire dentro la
risposta che ChatGPT, Gemini, Claude o Perplexity scrivono a chi chiede
«dove duplico una chiave a Trapani». Sono due lavori diversi e il portale li
fa tutti e due, dalla schermata **SEO e sitemap** → riquadro *Farsi trovare
dagli assistenti IA*.

**1. Dati strutturati più ricchi.** Ogni città pubblica ora un
`LocalBusiness` con dentro anche la descrizione, i profili social
(`sameAs`), la fascia di prezzo (`priceRange`, calcolata dai prezzi delle
pagine: se il tuo servizio più basso costa 15 € e il più alto 180 €, esce
`€15-€180`), il collegamento alla mappa e il sito principale come azienda
madre. Sono i campi che gli assistenti leggono per dire *quanto costa* e
*chi sei* senza inventarselo.

**2. Le FAQ.** Restano la cosa che conta di più: una domanda scritta come la
farebbe un cliente, con la risposta sotto, è esattamente il pezzo che un
assistente cita. Le FAQ generano lo schema `FAQPage` (servono almeno due
domande) e ora la sezione FAQ è prevista anche sulla **pagina principale**
della città e sulle pagine servizio. Il riquadro in SEO e sitemap ti dice
su quante pagine le hai già scritte. Le pagine principali create **prima**
di questo aggiornamento se la prendono da sole, la prima volta che apri il
pannello: la sezione viene aggiunta in fondo agli orari senza spostare
nient'altro di quello che avevi ordinato, e resta invisibile finché quella
pagina non ha domande scritte.

**3. `/llms.txt`.** Un indice in testo semplice, all'indirizzo
`iltuosito.it/zone/llms.txt`, che elenca il brand, i recapiti e — città per
città — tutte le pagine pubblicate con titolo, indirizzo e descrizione. È
una convenzione giovane: non tutti gli assistenti la leggono, ma costa zero
e chi la legge trova tutto in ordine.

**4. I crawler IA nel `robots.txt`.** GPTBot, ClaudeBot, PerplexityBot,
Google-Extended, Applebot-Extended e gli altri sono nominati uno per uno e
autorizzati (pannello e cartelle di sistema restano off-limits anche per
loro). Se un giorno **non** vuoi più che i tuoi testi finiscano negli
assistenti, togli la spunta *Consenti agli assistenti IA di leggere il sito*
in **Impostazioni → Indicizzazione**: il `robots.txt` diventa `Disallow: /`
per quei bot soli — Google e Bing continuano a indicizzarti normalmente — e
`/llms.txt` risponde 404.

> Se hai tempo per una cosa sola, scrivi le FAQ. `llms.txt` è una
> convenzione giovane; i dati strutturati e le FAQ li leggono già tutti.

---

## La home del portale e il piè di pagina

Sono i due pezzi che non appartengono a nessuna città, e si scrivono da
**Home del portale** nel menu.

**Il nome in alto a sinistra del piè di pagina è il logo**, lo stesso di
Impostazioni → Logo e icona, rimpicciolito. Se non hai caricato un logo resta
il nome scritto, o il piede aprirebbe con un buco.

**Pagina principale** — è quella che si apre sull'indirizzo del portale
(`tuosito.it/zone/`) e serve a chi arriva senza sapere ancora quale città gli
interessa. In barra non c'è un menu — non appartiene a nessuna città — e il
marchio sta al centro. Si cambiano soprattitolo, titolo, testo di presentazione, immagine
di sfondo, il testo sopra e sotto l'elenco, un blocco di HTML libero, e titolo
e descrizione per Google.

Nel titolo, quello che scrivi **fra asterischi** esce in giallo:

```
Un fabbro *a Trapani*   →   Un fabbro a Trapani
```

L'elenco delle città si costruisce da solo con le città pubblicate.

Anche qui c'è l'assistente: propone il titolo (con gli asterischi già al
posto giusto), la riga sotto, i due testi intorno all'elenco, titolo e
descrizione per Google, e disegna l'immagine di sfondo. Non conosce una
città sola: gli si passa l'elenco delle zone pubblicate e il catalogo dei
servizi.

**Piè di pagina** — è lo stesso su tutte le pagine del portale. Si cambiano la
riga di presentazione sotto il nome, i titoli delle due colonne, il testo del
copyright e i tuoi collegamenti, uno per riga:

```
Preventivi | https://www.tuosito.it/preventivi/
Blog | https://www.tuosito.it/blog/
```

Senza la barra verticale la riga vale sia come etichetta sia come indirizzo.

Nella **riga di presentazione** e nel **testo del copyright** valgono due
scorciatoie:

| Scrivi | Esce |
|---|---|
| `{anno}` | l'anno corrente, che a gennaio cambia da solo |
| `[testo](indirizzo)` | quel pezzo, e solo quello, diventa cliccabile |

Per esempio:

```
Copyright © {anno} - [Realizzato da Max Digital Innovation](https://maxdigitalinnovation.it/)
```

esce come «Copyright © 2026 - Realizzato da Max Digital Innovation», dove solo
le ultime parole sono un collegamento. Un indirizzo che comincia per `http`
si apre in una scheda nuova. Il resto della riga passa da `htmlspecialchars`:
un tag scritto lì dentro resta testo, e un `javascript:` non diventa un
collegamento — la riga si vede com'è stata scritta, così ci si accorge
dell'errore.

Due tolleranze, perché questa riga si scrive a mano una volta sola e
sbagliarla è facile: lo **spazio fra `]` e `(`** non rompe niente, e un
indirizzo scritto **senza `https://`** (`maxdigitalinnovation.it`) viene
completato da solo, invece di diventare una sottopagina del portale che non
esiste. Sotto il campo, nel pannello, c'è l'**anteprima** della riga come
esce sul sito: se lì il pezzo non è sottolineato, cliccabile non è.

Sito principale, privacy e cookie si mettono nelle impostazioni e compaiono
lì da soli; indirizzo, telefono, email e P. IVA vengono dalla città che si sta
guardando.

---

## Scrivere il testo

Il campo del testo di approfondimento ha una barra sopra: **G** (grassetto),
**C** (corsivo), Sottotitolo, Paragrafo, elenco puntato, elenco numerato,
Collegamento, Pulisci. Si scrive vedendo il risultato, come su WordPress.

Tre cose da sapere:

- **Il sottotitolo è un `<h3>`.** L'`<h1>` è il titolo della pagina e l'`<h2>`
  lo mette la sezione, quindi qui si riparte da lì: la gerarchia resta giusta
  per Google senza doverci pensare.
- **Incollando da un altro sito entra solo il testo**, non l'impaginazione
  altrui. Poi lo formatti con la barra.
- **Quello che si salva è HTML ripulito**: restano i tag del testo,
  spariscono script, stili, moduli e ogni attributo tranne `href` sui
  collegamenti. È lo stesso filtro degli articoli importati da WordPress.

I testi scritti prima che l'editor esistesse continuano a funzionare: erano
testo semplice, e testo semplice restano finché non li tocchi.

**L'assistente scrive già formattato**: sottotitoli ogni due o tre paragrafi,
grassetto sulle tre o quattro cose che il cliente cerca davvero (il servizio,
la città, il prezzo, il tempo), elenchi scritti come elenchi. E più corto di
prima — 300-380 parole spezzate invece di un muro unico, perché un muro non
lo legge nessuno e non lo cita nemmeno un assistente IA.

---

## Tag, icone, dati legali e social

**I tag** sono le pastiglie sotto il testo: quattro o cinque parole che dicono
di cosa parla la pagina a chi la guarda senza leggerla tutta. Si scrivono in
**Pagine → Modifica → riquadro Tag**, una per riga. Non sono collegamenti e
non portano da nessuna parte: servono a farsi capire in un colpo d'occhio. Il
pulsante **✦ Proponi i tag** li fa scrivere all'assistente, che pesca dal
testo della pagina; quelli troppo lunghi per stare in una pastiglia li scarta
da solo.

Una precisazione, perché è una domanda che torna sempre: **i tag non servono
a Google.** Il `meta keywords` non conta più da vent'anni. Servono a chi
legge, e indirettamente agli assistenti IA, che dalle pastiglie capiscono in
una riga di cosa tratta la pagina.

**Le immagini nelle card** sono l'immagine di anteprima di ogni pagina
servizio — la stessa della scheda SEO, quella che si vede quando il
collegamento viene condiviso. Escono come fascia in cima alla card, ritagliate
in 16:9, con il numero e l'icona in sovrimpressione. Le pagine che non ce
l'hanno escono come prima, senza riquadro vuoto: per non avere card a metà
conviene dare l'immagine a tutte o a nessuna. Si spengono da
**Impostazioni → Aspetto → «Mostra l'immagine di anteprima nelle card»**.

**Le icone** stanno sul *tipo di servizio*, non sulla singola pagina: così
tutte le città che offrono quel servizio mostrano lo stesso segno. Si
scelgono in **Tipi di servizio**, vedendole: sedici disegni (chiave, chiave
auto, telecomando, lucchetto, serratura, porta, casa, auto, moto, cancello,
cassaforte, attrezzi, sicurezza, orari, telefono, mappa). Sono disegnati
dentro il portale, quindi non c'è niente da scaricare da altri siti e
prendono da soli il colore dell'accento.

**La denominazione sociale e la partita IVA** si mettono in due posti:

| Dove | Quando |
|---|---|
| **Impostazioni → Contatti** | Una sola società per tutto il portale |
| **Città → Contatti e orari** | Quella città ha una società diversa |

La denominazione sociale è il nome con cui la società è registrata — quello
che sta accanto alla partita IVA nel piè di pagina — e non va confuso con il
nome commerciale, che è già nel logo. Esce sotto il logo, sopra l'indirizzo,
e nei dati strutturati va nel campo `legalName`, separato da `name`: Google
li tiene distinti, e tenerli distinti evita che il nome legale scacci quello
commerciale dai risultati.

Quella della città vince su quella generale. Se la lasci vuota vale la
generale, quindi non devi riscriverla su ognuna. Esce nel piè di pagina,
nella sezione dei recapiti completi, nei dati strutturati (`vatID`) e, se è
diversa da quella generale, anche dentro `llms.txt`.

**I social** funzionano allo stesso modo, ma **rete per rete**:

| Dove | Quando |
|---|---|
| **Home del portale → Piè di pagina → Social** | I profili del marchio, validi ovunque |
| **Città → Contatti → Social di questa città** | Quella città ha i suoi |

Il confronto non è «o tutti della città o tutti generali»: una città con la
sua pagina Facebook ma senza un suo Instagram mostra **il Facebook della
città accanto all'Instagram del marchio**. Il segnaposto di ogni campo ti
dice cosa erediteresti lasciandolo vuoto. Svuotare un campo già compilato
riporta a quello generale, non cancella niente.

Anche qui i profili finiscono nel campo `sameAs` dei dati strutturati, e
quelli sono quelli della città che si sta guardando. La pagina d'ingresso del
portale non appartiene a nessuna città, quindi mostra sempre i generali.

---

## Ogni pagina mostra cose diverse

Molte sezioni (orari, recensioni, zone, team, mappa) vengono dai dati della
**città**: se le mostrassero tutte le pagine, ogni pagina della stessa città
sarebbe identica alle altre, e Google ne indicizzerebbe una sola.

Per questo ogni pagina ha la scheda **Sezioni** nel suo editor, con due
colonne: a sinistra **Nella pagina**, nell'ordine in cui si leggono scorrendo;
a destra **Non mostrate**.

- `↑` `↓` spostano una sezione su e giù
- `×` la toglie dalla pagina, `+` la rimette
- dall'alto in basso = dall'inizio alla fine della pagina

Si sposta anche il **testo di approfondimento**: se su Contatti vuoi il modulo
per primo e il testo sotto, basta portare *Modulo di contatto* in cima.

Una sezione senza contenuto non lascia un buco: semplicemente non esce.

Le proposte di partenza:

| Tipo di pagina | Sezioni proposte |
|---|---|
| Principale | perché, zone, recensioni, dove siamo, orari, CTA, servizi, altre città |
| Servizio | cosa comprende, processo, prezzi, FAQ, perché, CTA, altri servizi |
| Elenco servizi | perché, CTA, altre città |
| Contatti | recapiti, modulo, dove siamo, orari |
| Chi siamo | team, perché, CTA |
| Blog | CTA |

Sono proposte, non regole: togli, aggiungi e riordina come vuoi. Le pagine
già scritte non si muovono da sole — restano come sono finché non sei tu a
spostare qualcosa.

---

## Il modulo di contatto

Si attiva dalla scheda **Sezioni** di una pagina, portando *Modulo di contatto*
fra quelle mostrate (e di solito anche *Recapiti completi*).

Le richieste arrivano via email all'indirizzo della città; se non c'è, a quello
delle impostazioni. L'editor ti dice a quale indirizzo andranno, e ti avvisa se
non ne hai messo nessuno.

Contro lo spam, senza captcha e senza far perdere tempo a chi scrive davvero:

- un campo esca invisibile, che compilano solo i robot
- una firma sul momento in cui il modulo è stato aperto
- un tempo minimo di compilazione e uno massimo
- gli a capo nei campi brevi, tipici dei tentativi di iniezione, fanno scartare l'invio

Niente sessioni: le pagine pubbliche restano cacheabili.

---

## Il menu della città

Sta **dentro l'intestazione**, in alto, accanto a logo e numero di telefono.
L'intestazione resta attaccata al bordo superiore mentre si scorre, quindi il
menu è sempre a un clic.

### La schermata Menu

In **Menu** gestisci la barra di una città in un posto solo:

- **Come si vedrà** — l'anteprima della barra, che si aggiorna mentre lavori
- **Nel menu** — le voci in ordine, con ↑ ↓ per spostarle e × per toglierle
- **Fuori dal menu** — le pagine che esistono ma non compaiono, con + per aggiungerle
- **Salva il menu** — scrive l'ordine su tutte le pagine in un colpo

Dall'alto in basso corrisponde a da sinistra a destra nella barra. La pagina
principale non compare: è già il pulsante con il nome della città.

Se le voci diventano tante, un avviso ti dice che la barra andrà a capo. Non è
un errore — i collegamenti restano tutti visibili, ed è meglio così per chi
indicizza — ma se preferisci una riga sola sai cosa togliere.

**Usa questo menu anche altrove** copia la struttura su altre città,
abbinando le pagine per indirizzo. Le pagine che in quelle città non esistono
vengono saltate.

Senza JavaScript i pulsanti funzionano lo stesso: ogni clic salva e ricarica.

Le stesse impostazioni restano anche nell'editor della singola pagina
(scheda SEO → Posizione nel menu), se preferisci lavorare da lì.

**Le pagine servizio nascono fuori dal menu**, perché con sei o sette servizi la
barra va a capo e diventa difficile da leggere. Restano raggiungibili
dall'indice nell'intestazione e dalla pagina "Elenco servizi".

Se hai pagine servizio create prima e vuoi toglierle, in **Pagine** compare un
pulsante *Togli i servizi dal menu* quando ce ne sono almeno tre. Oppure le
selezioni e usi i comandi *Metti nel menu* / *Togli dal menu*.

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

## Portare una pagina dentro WordPress

Ogni pagina del portale ha il suo **shortcode**, pronto da copiare nella
colonna destra dell'editor:

```
[portale_citta citta="trapani" pagina="contatti"]
```

Nella tendina sotto scegli una **singola sezione** e lo shortcode cambia da
solo:

```
[portale_citta citta="trapani" pagina="contatti" sezione="modulo"]
```

**Serve solo l'elenco delle zone?** È lo shortcode della home del portale,
che trovi in *Home del portale* → colonna destra:

```
[portale_citta]                    → tutta la pagina /zone/
[portale_citta sezione="citta"]    → solo la griglia delle zone
```

Perché funzioni serve il plugin **Portale Città — shortcode**
(`wordpress-plugin/portale-citta/`), da installare in WordPress e da puntare
all'indirizzo del portale in *Impostazioni → Portale Città*.

**Meglio una sezione che la pagina intera.** Incorporando una pagina intera lo
stesso testo esiste su due indirizzi e Google ne sceglie uno solo, scartando
l'altro. Le sezioni singole invece no: il modulo di contatto di Trapani dentro
la pagina "Contatti" di WordPress, le card dei servizi dentro la home, i
recapiti dove servono.

Come funziona sotto: il portale risponde in JSON su
`/{citta}/{pagina}/?incorpora=1[&sezione=chiave]` — e su `/?incorpora=1` per
la home — con il contenuto già disegnato, l'indirizzo del foglio di stile e
l'elenco delle sezioni disponibili. Le bozze non si incorporano: rispondono
404 come al pubblico.

---

## Stile grafico e larghezza

Impostazioni → Aspetto → **Impianto della pagina**.

| Stile | Com'è fatto |
|---|---|
| **Vetrina** (predefinito) | Intestazione da bordo a bordo con l'immagine dietro, titolo maiuscolo e pulsanti al centro, i servizi come pastiglie, il telefono in barra con l'etichetta ("Assistenza 24h") e la cornetta |
| **Classico** | L'intestazione è una scheda arrotondata dentro al contenuto, testo a sinistra, servizi in colonna di fianco al titolo |

Si cambia idea quando si vuole: è una tendina, non una riscrittura.

**Altezza del logo in barra** — da 24 a 140 px, 56 di partenza. Sullo schermo
del telefono si rimpicciolisce da solo (fino a 40), ma un logo già più piccolo
resta com'è. Un'immagine non viene mai stirata oltre la sua misura vera: se la
vuoi grande, caricala grande.

**Testo del menu** — da 10 a 22 px, 12,5 di partenza. Alzandolo si muovono
insieme anche il pulsante con il nome della città e il menu del telefono, così
la barra resta in proporzione invece di sfilacciarsi. L'anteprima in
**Menu → Come si vedrà** usa la stessa misura: quello che vedi lì è quello
che esce.

> Attenzione a esagerare: con molte voci, una misura alta manda la barra a
> capo su due righe. Non è un errore — i collegamenti restano tutti — ma la
> schermata Menu te lo dice, e da lì puoi togliere qualche voce.

**Larghezza del contenuto** — 1240, 1440 (consigliata), 1600 o 1800 px. È la
misura che usano *insieme* intestazione, contenuto e piè di pagina: sono
sempre allineati fra loro, qualunque valore scegli.

> Se il contenuto ti sembra stretto su un monitor grande non è disallineato:
> è il contenitore che finisce prima. Allargalo qui.

**Social nel piè di pagina** — Home del portale → Piè di pagina → Social.
Compaiono solo quelli con un indirizzo scritto. Le icone sono disegnate dentro
il portale: non si scarica niente da server di altri.

---

## Due colonne sugli schermi larghi

Nella scheda **Sezioni** ogni sezione ha tre bottoncini: **Intera**,
**Sinistra**, **Destra**.

- **Intera** è il riposo: la sezione prende tutta la larghezza.
- **Due sezioni vicine**, una messa a Sinistra e una a Destra, **escono
  affiancate**. Sotto i 1100px — tablet e telefono — tornano una sotto
  l'altra da sole.

**Il lato lo decide il bottoncino, non l'ordine.** Trascinare una sezione
sopra l'altra cambia l'ordine di lettura, non il lato: se vuoi il modulo a
sinistra, marcalo Sinistra. Prima era l'ordine a decidere, e per le pagine
mai salvate dalla scheda Sezioni lo è ancora.

Perché «vicine»: una coppia si forma solo fra due sezioni che si toccano
nell'ordine. Se ne infili una a tutta larghezza in mezzo, la coppia si apre e
le due tornano larghe. E se una delle due non ha niente da mostrare — un
modulo di fianco al vuoto — la coppia non si fa: meglio una sezione larga che
mezza pagina bianca.

Le sezioni si spostano **trascinandole**, oppure con le frecce ↑ ↓ per chi
preferisce (o per chi non può trascinare: funzionano anche da tastiera, e
senza JavaScript).

**Le pagine fatte prima** di questa scheda tengono l'impaginazione che
avevano: testo e modulo affiancati da soli. Appena salvi la pagina dalla
scheda Sezioni, comanda quello che hai scelto lì.

Quando invece il testo è da solo sulla pagina — le pagine servizio, per
esempio — si dispone su **due colonne** invece di lasciare mezza pagina
bianca. Stessa cosa per il modulo quando sta da solo: campi a sinistra,
messaggio e pulsante a destra.

Non c'è niente da impostare, e le soglie sono scritte in `em`: se il
visitatore ingrandisce il testo le colonne diventano una da sole, prima che
si riducano a strisce. Un testo corto resta comunque in una colonna.

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
├── index.php          front controller pubblico (città, pagine, sitemap, robots, llms.txt)
├── admin.php          front controller dell'amministrazione
├── install.php        installazione guidata (da cancellare dopo)
├── config.php         dati del database (creato dall'installazione)
├── router-dev.php     solo per le prove in locale
├── inc/
│   ├── core.php       funzioni di base
│   ├── db.php         connessione e creazione delle tabelle
│   ├── archivio.php   lettura/scrittura di città, pagine, servizi
│   ├── auth.php       accesso, ruoli e token anti-CSRF
│   ├── utenti.php     gli utenti del pannello
│   ├── media.php      immagini
│   ├── seo.php        titoli, descrizioni, punteggio, sitemap, robots, llms.txt
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
| `pc_articoli` | Articoli del blog, legati a una città |
| `pc_media` | Immagini caricate |

I campi ripetibili (orari, FAQ, recensioni, processo, numeri, team) sono
salvati in JSON dentro la loro colonna.

---

## Aggiornare

Sovrascrivi tutto **tranne** `config.php`, `dati/` e `media/`.

Al primo accesso all'amministrazione le tabelle mancanti vengono create e le
colonne nuove aggiunte a quelle esistenti: non devi toccare il database.

**Caricali tutti.** Se `admin.php` è nuovo e la cartella `inc/` è rimasta
quella vecchia, il pannello se ne accorge da solo: entra lo stesso e mette
in cima un avviso rosso che dice cosa ricaricare. Gli aggiornamenti al
database, però, non sono passati — quindi ricarica e riapri la pagina. I
dati non vengono toccati in nessun caso.

---

## Sicurezza

- Password con `password_hash`, sessione con cookie HttpOnly e SameSite
- Utenti separati con due ruoli: le Impostazioni e la gestione degli Utenti
  sono chiuse ai redattori sia nel menu sia scrivendo l'indirizzo a mano
- L'utente della sessione si rilegge a ogni richiesta: sospenderlo o
  eliminarlo chiude subito le sessioni già aperte
- Il nome utente sbagliato e la password sbagliata impiegano lo stesso tempo,
  così non si scopre quali nomi esistono
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
