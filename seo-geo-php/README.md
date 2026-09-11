# SEO & GEO Audit — applicazione PHP con database

Analizza l'esportazione WordPress (WXR) di un sito, individua i problemi **SEO** e **GEO**
— sia *geografico/locale* sia *Generative Engine Optimization*, cioè la visibilità dentro
le risposte di AI Overviews, ChatGPT, Perplexity e Copilot — salva tutto su database e
genera i file di correzione, incluso un **plugin WordPress pronto da installare**.

PHP 7.4+ con PDO. Nessuna dipendenza, nessun Composer: si carica via FTP e funziona.

---

## Installazione

### In locale (la via più rapida)

```bash
php -d upload_max_filesize=64M -d post_max_size=64M -S localhost:8000 -t public
```

Poi apri <http://localhost:8000>.

### Su hosting (via FTP)

1. Carica l'intera cartella nello spazio web, per esempio in `/seo/`.
2. Dai permessi di scrittura a `storage/` (775).
3. Apri **`https://tuodominio.it/seo/`**.

Non c'è nessuna procedura di installazione da lanciare: il database e le tabelle
si creano da soli alla prima apertura.

L'applicazione vera vive in `public/`; nella radice ci sono un `.htaccess` che la
serve direttamente e un `index.php` che reindirizza quando le riscritture Apache
non sono attive. Se l'hosting non usa Apache (Nginx, LiteSpeed senza `.htaccess`)
apri direttamente **`https://tuodominio.it/seo/public/`**: funziona in ogni caso.

**Prima di tutto:** apri `https://tuodominio.it/seo/verifica.php` (oppure
`/seo/public/verifica.php`). È una pagina di sola diagnosi che controlla versione
di PHP, estensioni, permessi di `storage/` e limiti di caricamento, e ti dice
l'indirizzo esatto da usare.

Per far puntare un dominio o un sottodominio direttamente all'app, imposta come
document root la cartella `public/`: è la configurazione più pulita perché lascia
codice e dati fuori dalla portata del web.

### Impostazioni

Dalla voce **Impostazioni** in alto si configurano dal browser chiave API, dati aziendali,
autore, parametri SEO e collegamento a WordPress. Quello che salvi finisce in
`storage/impostazioni.json` (permessi 600, cartella non raggiungibile dal web) e si
sovrappone a `config.php`: non serve più modificare file via FTP, e un aggiornamento del
programma non cancella i tuoi dati.

### Database

**Non serve creare nessun database.** SQLite è l'impostazione predefinita: il file nasce
da solo in `storage/audit.sqlite` alla prima apertura, senza password, senza phpMyAdmin,
senza importare niente. Il file `mysql/schema.sql` riguarda solo chi *preferisce* usare un
MySQL già esistente — in tutti gli altri casi si ignora.

Per usare MySQL apri `config.php`:

```php
'database' => array(
	'driver' => 'mysql',
	'mysql'  => array( 'host' => '127.0.0.1', 'nome' => 'seo_geo_audit', 'utente' => '...', 'password' => '...' ),
),
```

Le tabelle vengono create automaticamente; solo se l'utente del database non ha il
permesso di creare tabelle, importa a mano `mysql/schema.sql`.

### Limiti di caricamento

Un export WordPress con qualche centinaio di articoli pesa 5-20 MB. Nel `php.ini`
(o `.user.ini`) servono almeno:

```
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 256M
max_execution_time = 300
```

---

## Uso

### Interfaccia web

1. **Nuova analisi** → carica il file XML (WordPress → Strumenti → Esporta → Tutti i contenuti).
2. L'app esegue i 65 controlli, classifica ogni articolo e genera i file: 2-4 secondi
   per un sito da 300 articoli.
3. Dalla scheda dell'audit scarichi il plugin e tutti i CSV.

### Riga di comando

```bash
php cli/audit.php export.xml                 # analizza, salva su database, genera i file
php cli/audit.php export.xml /percorso/out   # con cartella di destinazione personalizzata
```

---

## Cosa controlla

65 regole in 10 aree, ciascuna con gravità, spiegazione dell'impatto e soluzione.
Ogni regola dichiara se la correzione è automatica o manuale.

| Area | Esempi di controllo |
|---|---|
| Tecnico e indicizzazione | plugin SEO in conflitto, meta robots, canonical, pagine di servizio indicizzate, cestino |
| On-page | lunghezza di title e description, keyword nel title, gerarchia H1-H6, slug, cannibalizzazione |
| Contenuti | thin content, quasi-duplicati (Jaccard su shingle di 5 parole), freschezza, leggibilità Gulpease |
| Link e architettura | pagine orfane, link in uscita, anchor generici, dispersione di autorità, menu |
| Immagini | alt mancanti, peso, formati, immagine in evidenza, dimensioni esplicite |
| Dati strutturati | JSON-LD assente, FAQPage, BreadcrumbList, Service, Open Graph |
| SEO locale | NAP, LocalBusiness, comuni della provincia, recensioni, cannibalizzazione locale |
| GEO (motori generativi) | llms.txt, crawler AI in robots.txt, risposta in apertura, FAQ, dati citabili, entità e `sameAs`, speakable |
| E-E-A-T | biografia autore, casi studio, tratti di contenuto prodotto in serie |
| Tassonomie | categoria sovraccarica, tag assenti, categorie fuori tema |

## Triage editoriale

Ogni articolo pubblicato finisce in una di quattro categorie — **eliminare, accorpare,
riscrivere, mantenere** — combinando qualità misurata (lunghezza, struttura, media, link,
freschezza), similarità reale fra i testi, sovrapposizione con le pagine servizio e
competizione sulle stesse focus keyword. Per i contenuti da rimuovere viene indicata la
destinazione del redirect 301.

## Cosa genera

```
storage/export/audit-<n>/
├── mdi-seo-geo-booster.zip      plugin WordPress con i dati di questo sito
├── problemi.csv                 elenco completo dei rilievi
├── triage.csv                   classificazione di ogni articolo
├── meta-ottimizzate.csv         title, description, excerpt e slug riscritti
├── rank-math-bulk.csv           formato dell'editor massivo di Rank Math
├── redirect-301.csv             redirect da impostare
├── redirect.htaccess            gli stessi redirect per Apache
├── piano-link-interni.csv       link interni proposti con anchor text
├── immagini-alt.csv             alt suggeriti, peso, formati da convertire
├── file-root/                   llms.txt, llms-full.txt, robots.txt, ai.txt
├── schema/                      JSON-LD per ogni URL
└── plugin-data/                 gli stessi dati in JSON
```

## Il plugin generato

`mdi-seo-geo-booster` applica le correzioni sul sito live senza modificare i contenuti
nel database:

- title, meta description ed excerpt ottimizzati (via i filtri di Rank Math se presente,
  altrimenti stampati direttamente);
- meta robots con `max-image-preview:large`, `noindex` sulle pagine legali;
- un unico grafo JSON-LD per pagina: Organization/ProfessionalService, WebSite, Person,
  BreadcrumbList, BlogPosting con `speakable`, Service, FAQPage estratto dagli H2/H3;
- link interni automatici da mappa keyword → URL, senza toccare titoli e link esistenti,
  e **solo negli articoli**: il testo delle pagine servizio non viene alterato (interruttore
  in Impostazioni per includerle);
- blocco "Approfondimenti correlati" per recuperare le pagine orfane;
- `rel="nofollow sponsored noopener"` sui domini che disperdono autorità;
- alt, `loading`, `decoding` e `fetchpriority` sulle immagini;
- endpoint `/llms.txt`, `/llms-full.txt`, `/ai.txt` e direttive robots per i crawler AI;
- shortcode `[mdi_nap]`, `[mdi_faq]`, `[mdi_breadcrumb]`, `[mdi_in_breve]`;
- avvisi in bacheca su conflitti fra plugin SEO e dati aziendali mancanti.

**I dati aziendali non stanno nel plugin.** Si compilano una volta sola in
*Impostazioni* e il gestionale li spedisce al sito a ogni salvataggio: da quel momento
schema LocalBusiness, footer NAP e llms.txt usano quei valori, senza reinstallare niente.
Il file `data/config.json` dentro lo zip è solo la fotografia del momento in cui il plugin
è stato generato e viene scavalcato da quanto arriva dal gestionale.

La pagina SEO &amp; GEO in bacheca mostra da dove arrivano i dati in uso e quali campi
restano vuoti. Il plugin ignora i campi non compilati, quindi non stampa mai dati finti.

---

## Riscrittura assistita con Gemini (modulo AI)

Il resto del programma è deterministico: regole, soglie, algoritmi. Questo modulo è
l'unica parte che chiama un modello, e serve per la sola cosa che un algoritmo non può
fare — scrivere i 220 articoli che l'audit segnala come troppo deboli.

### Come funziona

Le bozze non partono da un prompt generico: partono dalle schede già prodotte
dall'audit. Per ogni articolo il modulo passa al modello l'intento di ricerca rilevato,
la scaletta H2 corrispondente, la lunghezza obiettivo, i link interni da inserire con il
loro anchor e il testo attuale come base di contenuto. Il modello esegue una strategia
già decisa, non la inventa.

### La regola sui dati

Le istruzioni di sistema vietano di inventare numeri, percentuali, prezzi, nomi di
clienti, premi e risultati. Dove servirebbe un dato reale il modello scrive
`[DA VERIFICARE: descrizione del dato]` e prosegue. Ogni bozza arriva con l'elenco dei
dati da inserire prima di pubblicare. È il vincolo più importante del modulo: un numero
inventato su un sito aziendale è un problema legale, non un difetto di stile.

### Nulla viene pubblicato

Le bozze restano nel database e come file HTML in `storage/export/audit-<n>/bozze/`.
Non esiste una funzione che le mandi su WordPress. Pubblicare in massa testo generato
senza revisione è esattamente ciò che le linee guida antispam di Google chiamano abuso
di contenuti scalati — e il sito analizzato mostra già quel profilo.

### Configurazione

Crea una chiave su <https://aistudio.google.com/apikey>, poi in `config.php`:

```php
'ai' => array(
	'provider'           => 'gemini',
	'chiave'             => 'la-tua-chiave',
	'modello'            => 'gemini-2.5-flash',
	'articoli_per_volta' => 5,
	'prezzo_per_milione' => array( 'input' => 0.10, 'output' => 0.40 ),
),
```

Meglio ancora: lascia `chiave` vuota ed esporta la variabile d'ambiente
`GEMINI_API_KEY`, così la chiave non finisce nei backup del sito. I prezzi servono solo
alla stima mostrata prima di lanciare: aggiornali con quelli del tuo piano.

### Uso

Dall'interfaccia web: nella scheda dell'audit, **Riscrittura assistita**. Si procede a
lotti (3-5 articoli per volta su hosting condiviso) per non superare il tempo massimo di
esecuzione: ogni articolo richiede 10-30 secondi.

Da riga di comando, senza limiti di tempo:

```bash
php cli/riscrivi.php 1 --stima                    # quanto costerebbe, senza chiamare l'API
php cli/riscrivi.php 1 --limite=5                 # genera 5 bozze
php cli/riscrivi.php 1 --categoria=accorpare      # solo una categoria del triage
php cli/riscrivi.php 1 --limite=50 --rigenera     # rifà anche quelle già fatte
```

La coda è ordinata per resa: prima gli articoli con intento transazionale e commerciale,
poi gli informativi, dai più corti ai più lunghi.

### Cosa il modulo non fa

Non genera backlink, non crea recensioni, non aumenta l'autorità del dominio e non
garantisce posizionamenti. Riscrive testi: è una parte del lavoro, non tutto il lavoro.

---

## Il punteggio si aggiorna da solo?

No, ed è una scelta: il punteggio è la fotografia del sito nel momento in cui è stato
analizzato, e resta lì per poterlo confrontare con le fotografie successive.

Per rifare la foto non serve più riesportare l'XML da WordPress: il plugin (dalla versione
1.2.0) espone contenuti, meta, immagini, menu e autori, e il gestionale li rilegge da lì.

```bash
php cli/audit.php --dal-sito
```

Dall'interfaccia: **Rileggi il sito e ricalcola**, in home o nella scheda dell'audit.
La nuova analisi si affianca alle precedenti — non le sostituisce — e la scheda mostra la
variazione rispetto a quella prima (`+12 rispetto all'analisi del 2026-09-11`).

Dalla bacheca di WordPress: **SEO & GEO → Analizza adesso** (plugin 1.3.0). Il pulsante
compare da solo appena si salvano le Impostazioni del gestionale, perché insieme ai dati
aziendali viene spedito anche l'indirizzo da chiamare. Premendolo, WordPress interroga il
gestionale e mostra il punteggio aggiornato con la variazione.

Da un pulsante tuo, ovunque: **Impostazioni → Avviare l'analisi da un pulsante** mostra un
indirizzo con token che risponde in JSON.

```
GET https://tuo-gestionale/index.php?p=api-analizza&token=…

{"ok":true,"audit":7,"punteggio":52,"variazione":18,"problemi":2140,
 "articoli":311,"pagine":15,"scheda":"…/index.php?p=audit&id=7"}
```

Il token vale come una password, va usato solo su `https` e si può rigenerare dalla stessa
scheda. Un'analisi ogni due minuti al massimo: le richieste più ravvicinate ricevono `429`,
così un doppio clic non fa partire due letture insieme. L'analisi legge e basta: non tocca
il sito.

Il flusso resta comunque comandato dal gestionale: il plugin non spedisce niente di sua
iniziativa, risponde quando gli viene chiesto. Un sito che chiama da solo un server esterno
a ogni modifica è un rischio che non vale il vantaggio; se vuoi l'aggiornamento periodico,
un cron con `php cli/audit.php --dal-sito` una volta a settimana fa lo stesso lavoro senza
aprire niente.

---

## Perché questa pagina non si vede

Menù **Controlla una pagina**: si incolla un indirizzo e il programma interroga tre fonti —
il sito (stato, categorie, robots, canonica, parole, se una bozza è in attesa, se le meta sono
state toccate), Google tramite l'ispezione URL di Search Console, e quello che l'analisi stessa
ha detto e fatto su quel contenuto. Legge soltanto, non modifica niente.

Riconosce e spiega: contenuto nel cestino, bozza non pubblicata, data futura, contenuto
privato o protetto da password, `noindex`, canonica che punta altrove, contenuto svuotato da
una riscrittura finita male, doppione che Google ha deduplicato in favore di un gemello,
pagina vista ma non indicizzata. Ogni causa dice **cosa fare**, non solo cosa non va.

Serve a non passare un pomeriggio a indovinare: nove volte su dieci la risposta è una riga
sola, e viene da chi la sa davvero.

## Search Console: i dati veri

Finché il programma legge solo il sito, il punteggio è una previsione: dice cosa è sbagliato
secondo le linee guida. Collegando Search Console entrano i fatti — per quali ricerche il sito
compare, in che posizione, quante volte viene cliccato — e il programma smette di lavorare a
occhio.

**Cosa ricava dai numeri** (menù *Rendimento*):

| Segnale | Come lo riconosce | Cosa propone |
|---|---|---|
| A un passo dalla prima pagina | posizione fra 8 e 20 con impression sopra la soglia | riscrittura e link interni, prima di tutto il resto |
| Ti vedono ma non ti cliccano | già in prima pagina, ma CTR sotto metà di quello atteso per quella posizione | rigenerare title e description |
| Due pagine competono sulla stessa ricerca | la stessa query porta impression a più pagine, senza che una prevalga | accorpare, tenendo la più forte |
| Pagina in calo | posizione peggiorata di 3 o più rispetto al periodo precedente | verificare cosa è cambiato |
| Google non la mostra mai | zero impression su un contenuto pubblicato da almeno due settimane | controllare l indicizzazione |

La cannibalizzazione qui è **misurata**, non ipotizzata: il triage editoriale la deduce leggendo
i testi, questa la vede accadere nelle ricerche.

Con i dati collegati cambia anche l ordine del pilota automatico: le riscritture partono dagli
articoli su cui Google dice che c è più da guadagnare, non dall ordine editoriale.

**Come si collega** (cinque minuti, gratis): Impostazioni → Google Search Console. Le istruzioni
passo passo sono lì dentro; in sintesi serve un account di servizio di Google Cloud con la
Search Console API attiva, e quell account va aggiunto fra gli utenti della proprietà in
Search Console. La chiave resta sul tuo server, dà accesso in sola lettura, e non può modificare
né il sito né l account Google.

La chiave si può consegnare in tre modi, in ordine di preferenza:

1. **caricando il file JSON** scaricato da Google Cloud (Impostazioni → Search Console);
2. **incollandone il contenuto** nel campo di testo;
3. **mettendola per FTP** in `storage/google.json`.

Il terzo modo esiste per una ragione concreta: su parecchi hosting condivisi il firewall
applicativo blocca le richieste che contengono una chiave privata, e un salvataggio dal browser
non arriverebbe mai al server. Se succede, il file per FTP aggira il problema senza discussioni.
La chiave viene comunque verificata prima di essere accettata: un ID client OAuth al posto di un
account di servizio, per dire, viene riconosciuto e spiegato.

Da riga di comando, per il cron:

```bash
php cli/prestazioni.php        # ultimi 28 giorni
php cli/prestazioni.php 90     # ultimi 90
```

Una volta al giorno è più che sufficiente: Google consolida i dati con due o tre giorni di
ritardo, e infatti il periodo analizzato si ferma a tre giorni fa.

Se sull'hosting non c'è il cron di sistema, c'è un indirizzo con token che fa lo stesso lavoro
e si può chiamare da un servizio esterno (cron-job.org, EasyCron, il cron del pannello):

```
GET https://tuo-gestionale/index.php?p=api-prestazioni&token=…

{"ok":true,"clic":312,"impression":8140,
 "variazione":{"clic":28,"impression":640},
 "da_fare":47,"per_tipo":{"quasi_prima_pagina":12,…}}
```

Lo trovi già scritto in Impostazioni → *Avviare l'analisi da un pulsante*. Una chiamata ogni
dieci minuti al massimo; più spesso riceve `429` e non consuma la quota di Google.

### Dalle indicazioni alle modifiche

I segnali dicono cosa succede in Google; il collegamento con il sito dice a quale contenuto
corrispondono. Uniti, diventano modifiche che il pilota sa eseguire:

| Segnale | Compito | Cosa cambia sul sito |
|---|---|---|
| Ti vedono ma non ti cliccano | `meta_mirata` | title e description riscritti **usando la ricerca vera come parola chiave**, inviati subito |
| A un passo dalla prima pagina | `bozza` | riscrittura del contenuto, caricata come bozza |
| Due pagine competono | `accorpa` | fusione delle pagine in una sola, come bozza |

*Pagina in calo* e *Google non la mostra mai* restano senza azione automatica: vogliono un
occhio umano, e far riscrivere all'AI un problema che non ha capito peggiora le cose.

**Le pagine restano fuori**, come nel resto del programma: sono poche e scritte a mano, e qui
si cambierebbero title e description senza vederle prima. Il programma dice quante ne ha
lasciate fuori e le include solo se lo si chiede con la casella apposita. Se sono già state
toccate, *Collegamento → Annulla → Ripristina solo le pagine* le riporta indietro senza
disfare il lavoro fatto sugli articoli.

Un contenuto riceve un compito solo per giro, anche quando i segnali che lo riguardano sono
due. Nella pagina Rendimento la colonna *Contenuto sul sito* dice quale articolo o pagina è
stato riconosciuto: se resta vuota, il programma non tocca niente e lo dichiara.

Il pulsante **Prepara le modifiche e vai al pilota** scrive la coda e porta al pilota, che
aspetta un avvio esplicito: queste operazioni costano e finiscono sul sito, non partono per
il solo fatto che si è aperta una pagina. La coda nata dai dati di Google non viene
ricostruita dall'audit — sarebbe cancellata.

### Cosa confronta, e quando

Il confronto è automatico, e avviene su due piani diversi:

- **dentro una singola lettura**: si scaricano il periodo richiesto *e* quello precedente, così
  "Pagina in calo" e le variazioni su clic, impression e posizione media esistono già dalla
  prima volta che si preme il pulsante;
- **fra una lettura e l'altra**: ogni rilevazione resta nel database, e la tabella
  *Come sta andando nel tempo* mette in fila tutte quelle fatte finora.

Quello che non avviene da solo è la **chiamata**: senza un cron, i dati si aggiornano quando
qualcuno preme il pulsante. Con il cron, non ci pensa più nessuno.

### llms.txt e ai.txt si aggiornano da soli

Fino alla versione 1.3.0 del plugin erano file statici, fotografati al momento
dell'ultima analisi: dopo qualche riscrittura raccontavano un sito che non esisteva
più, e si aggiornavano solo reinstallando lo zip.

Dalla 1.4.0 il plugin li compone dai contenuti pubblicati in quel momento, con una
cache di un'ora che viene buttata a ogni pubblicazione, modifica o cestinata. Il file
dichiara la propria data (`Aggiornato: 2026-09-11`) e la risposta porta
l'intestazione `X-Mdi-Origine: dinamico`.

Se mancano i dati aziendali il plugin non pubblica una mappa monca: ripiega sul file
statico dentro lo zip (`X-Mdi-Origine: statico`). Anche l'indirizzo della sitemap non
viene più dato per scontato: `/sitemap_index.xml` se sul sito c'è Rank Math o Yoast,
`/wp-sitemap.xml` altrimenti — in `llms.txt` e in `robots.txt`.

### La sitemap

Non la genera questo programma e non deve: la fa WordPress, o il plugin SEO installato,
e si aggiorna da sola a ogni pubblicazione. Va inviata **una volta** in Search Console,
poi Google la rilegge per conto suo. La pagina Rendimento mostra quando l'ha fatto
l'ultima volta, con errori e avvisi dichiarati da Google.

### Quello che non fa

Non esiste un modo per cui un programma legga gli aggiornamenti dell algoritmo di Google e si
riscriva da solo: le linee guida cambiano dentro documenti scritti per le persone, e
l algoritmo non è documentato. Le 65 regole di questo programma sono la traduzione delle linee
guida a una certa data, fatta da una persona. L Indexing API di Google, che qualche strumento
usa per promettere indicizzazioni immediate, è ammessa solo per offerte di lavoro e diretta
video: usarla per articoli normali è fuori scopo.

---

### Quando il modello si ferma a metà

Sui modelli 2.5 il ragionamento consuma lo stesso budget della risposta: con uno spazio
stretto, un articolo lungo arriva tagliato a metà e il JSON risulta incompleto. Non è un
errore di formato, ed è inutile riprovare identici.

Il programma se ne accorge dal `finishReason` della risposta e **riprova una volta sola con
il doppio dello spazio**. Se non basta nemmeno allora, lo dice con i numeri in mano — quanto
spazio è stato usato, quanto se n'è andato in ragionamento — invece di dare la colpa al
formato. Il valore si alza da Impostazioni → *Spazio massimo per risposta*.

## Pilota automatico

Prima di avviarlo si sceglie **cosa** fargli fare, voce per voce, con i numeri davanti:

| Gruppo | Costo |
|---|---|
| Dati aziendali al sito | nessuno |
| Meta degli articoli | nessuno |
| Redirect 301 e categorie | nessuno |
| Accorpamento degli articoli che si cannibalizzano | una richiesta AI per gruppo |
| Riscrittura degli articoli | una richiesta AI per articolo |
| Immagini in evidenza mancanti | una richiesta a pagamento per immagine |

Le prime tre sono spuntate di partenza, le altre no: si scelgono apposta. Spuntando solo le
immagini si mettono in coda **solo** quelle — prima la casella delle immagini si aggiungeva
al piano intero, e chi voleva 289 immagini si ritrovava 522 operazioni, riscritture comprese.

Un comando solo, e il programma esegue in sequenza tutto ciò che non richiede una
decisione umana: meta, redirect, categorie, accorpamenti degli articoli che si
cannibalizzano, riscritture, immagini. Lavora a giri brevi per stare dentro i limiti di
tempo degli hosting condivisi e riprende da dove si era fermato.

**Dal browser:** scheda dell'audit → *Pilota automatico* → scegli le opzioni → Avvia.
La pagina mostra avanzamento e registro in tempo reale e va avanti da sola; si può mettere
in pausa o annullare ciò che resta in coda.

**Da riga di comando**, senza limiti di tempo e senza browser:

```bash
php cli/pilota.php 1 --avvia                 # prepara la coda e la esegue (solo articoli)
php cli/pilota.php 1 --avvia --pagine        # tocca anche le meta delle pagine
php cli/pilota.php 1 --avvia --immagini      # incluse le immagini in evidenza
php cli/pilota.php 1                         # riprende una coda già avviata
php cli/pilota.php 1 --minuti=4              # lavora 4 minuti e si ferma (per il cron)
php cli/pilota.php 1 --stato                 # solo il riepilogo
```

Con un cron ogni cinque minuti (`php /percorso/cli/pilota.php 1 --minuti=4`) la coda si
svuota da sola nell'arco di qualche ora.

### Articoli e pagine sono trattati separatamente

Le pagine servizio sono poche, scritte a mano e di solito già a posto: il pilota **non le
tocca** se non glielo si chiede, e nell'interfaccia hanno una sezione propria con il numero
di modifiche effettivamente proposte. Gli articoli sono centinaia e generati in serie: lì
l'intervento automatico ha senso.

Cosa tocca una pagina, in concreto:

| Operazione | Pagine |
|---|---|
| Riscritture, accorpamenti, immagini generate | mai: riguardano solo gli articoli |
| Ricategorizzazione, redirect, cestino | mai: riguardano solo gli articoli |
| Meta (title, description, estratto) | solo dal pulsante dedicato, mai dal pilota senza spunta |
| Link interni automatici nel testo | no, salvo interruttore in Impostazioni |
| Dati strutturati, meta robots, alt, Open Graph | sì, e conviene: aggiungono informazioni senza cambiare una parola di quello che hai scritto |

### Le due opzioni che cambiano la natura del lavoro

Sono spente di proposito: con esse il programma non si limita più a preparare, decide.

- **Pubblica le riscritture negli articoli originali.** Il testo nuovo sostituisce quello
  vecchio mantenendo URL, data e storia; WordPress conserva una revisione, quindi si torna
  indietro dall'editor. Senza questa opzione le riscritture restano bozze da rileggere.
  Va detto chiaramente: pubblicare testo generato senza rileggerlo lascia in pagina i
  segnaposto `[DA VERIFICARE]` ed è ciò che le linee guida antispam di Google chiamano
  abuso di contenuti scalati.
- **Sposta nel cestino i contenuti da eliminare.** Solo dopo i redirect, e solo nel
  cestino: da WordPress si recuperano.

### Quando si ferma da solo

Se un errore riguarda la configurazione — chiave API rifiutata, quota esaurita, token
sbagliato, plugin non raggiungibile — il pilota interrompe il giro invece di ripetere lo
stesso errore su centinaia di operazioni. Le restanti tornano in coda: si risolve il
problema e si riavvia.

### Cosa resta comunque a una persona

Compilare i dati aziendali, rileggere le bozze sostituendo i segnaposto con dati reali,
creare le pagine Chi siamo e Contatti, curare la scheda Google Business e le recensioni.

---

## Applicare le correzioni sul sito (collegamento con il plugin)

Il gestionale non si limita a produrre file: parla direttamente con il plugin installato
su WordPress e scrive lì.

### Come si collega

Serve il plugin **versione 1.1.0 o successiva**: le versioni precedenti non sanno ricevere
i dati aziendali né pubblicare le riscritture. La versione installata è scritta nella
pagina SEO &amp; GEO della bacheca e nel riquadro "WordPress" di *Applica sul sito*.

1. Su WordPress apri **SEO &amp; GEO** nel menu: nel riquadro *Collegamento con il gestionale*
   trovi indirizzo del sito e token (il token si genera con un clic).
2. Incollali in **Impostazioni → Collegamento al sito WordPress**.
3. Apri la scheda dell'audit → **Applica sul sito**: se il collegamento funziona vedi nome
   del sito, versione di WordPress, numero di contenuti e quali plugin SEO sono attivi.

Il canale è una API REST del plugin (`/wp-json/mdi-seo/v1/…`) autenticata con il token in
un'intestazione `X-MDI-Token`. Il token vale come una password: si può rigenerare in
qualsiasi momento dalla bacheca.

### Cosa si può applicare

| Operazione | Cosa fa | Reversibile |
|---|---|---|
| Meta degli articoli | Scrive title, description, focus keyword ed estratto sui campi di Rank Math | Sì: i valori precedenti restano da parte, un pulsante li ripristina |
| Meta delle pagine | Operazione separata: le pagine servizio sono poche e curate a mano, e di solito non hanno bisogno di niente | Sì, come sopra |
| Anteprima meta | Mostra il confronto prima/dopo senza scrivere nulla | Non modifica niente |
| Bozze | Crea articoli in stato **Bozza** collegati agli originali | I contenuti pubblicati non vengono mai toccati |
| Redirect 301 | Attiva la tabella dei redirect (slug accorciati, articoli eliminati o accorpati) | Sì, si riapplica una tabella vuota |
| Categorie | Riassegna le categorie agli articoli classificati fuori tema | Manualmente |
| Immagini | Carica l'immagine in libreria e la imposta come immagine in evidenza | Manualmente |

Le riscritture non sovrascrivono mai il testo pubblicato: diventano bozze nuove, che una
persona confronta con l'originale e pubblica quando è soddisfatta.

---

## Cannibalizzazione: fondere gli articoli che competono fra loro

Quando più articoli puntano alla stessa ricerca si tolgono forza a vicenda e nessuno si
posiziona. Il triage li raggruppa, il modulo AI li fonde.

```bash
php cli/riscrivi.php 1 --accorpa --stima     # mostra i gruppi, senza chiamare l'API
php cli/riscrivi.php 1 --accorpa --limite=3  # fonde tre gruppi
```

Il modello riceve **tutti i testi del gruppo** e ne produce uno solo: tiene quello che ha
valore in ciascuno, elimina le ripetizioni, organizza per sezioni tematiche e segnala con
un segnaposto i punti dove le fonti si contraddicono. Gli articoli assorbiti vanno poi
reindirizzati con un 301 sul principale — i redirect sono già calcolati e si attivano dalla
pagina *Applica sul sito*.

---

## Immagini in evidenza mancanti

```bash
php cli/riscrivi.php 1 --immagini --stima          # quanti articoli ne sono privi
php cli/riscrivi.php 1 --immagini --limite=5       # genera e salva in storage/
php cli/riscrivi.php 1 --immagini --limite=5 --invia   # e le carica sul sito
```

Le immagini sono orizzontali, senza testo, senza logo e senza volti riconoscibili (i modelli
rendono male il testo dentro le immagini, e un titolo storto su un'immagine in evidenza si
nota subito). Vengono salvate in `storage/export/audit-<n>/immagini/` e, con `--invia` o con
la casella corrispondente nell'interfaccia, caricate in libreria media e impostate come
immagine in evidenza con un alt descrittivo.

Due avvertenze oneste: la generazione di immagini **richiede un progetto Google con
fatturazione attiva** (sul piano gratuito l'API risponde con un errore di quota), e per una
web agency le foto dei lavori veri valgono più di qualsiasi immagine generata. Questo serve
a coprire l'archivio storico, non a sostituire il portfolio.

---

## Struttura del progetto

```
index.php                   reindirizza a public/ (per le installazioni in sottocartella)
.htaccess                   serve public/ come radice quando Apache lo consente
config.php                  dati aziendali, database, soglie SEO
mysql/schema.sql            schema MySQL, serve solo se si sceglie MySQL al posto di SQLite
cli/audit.php               interfaccia a riga di comando
cli/riscrivi.php            generazione delle bozze con Gemini
cli/pilota.php              pilota automatico (anche da cron)
cli/prestazioni.php         aggiornamento dei dati di Search Console
public/index.php            front controller web
public/verifica.php         diagnosi dei requisiti del server
public/assets/app.css       interfaccia
views/                      layout e pagine
src/
├── Autoload.php            autoload PSR-4 senza Composer
├── Db.php                  PDO, creazione schema, inserimenti massivi
├── WxrParser.php           lettura in streaming dell'export (XMLReader)
├── Site.php                modello del sito e grafo dei link interni
├── Text.php                metriche italiane: Gulpease, slug, shingle, Jaccard
├── Html.php                estrazione di titoli, immagini, link, paragrafi
├── Rules/                  le 65 regole, un file per area
├── Audit.php               esecuzione delle regole, punteggi, salvataggio
├── Triage.php              classificazione editoriale
├── Ai/                     client Gemini, prompt, bozze, accorpamenti, immagini
├── Bridge/                 client verso le API REST del plugin
├── Google/                 account di servizio e client di Search Console
├── Search/                 rendimento reale e segnali che ne derivano
├── Coda.php                coda delle operazioni del pilota automatico
├── Impostazioni.php        configurazione modificabile dal browser
├── Fix/                    meta, link interni, dati strutturati, llms.txt
└── Export.php              CSV, file per i crawler, assemblaggio del plugin
plugin-wordpress/           sorgenti del plugin generato (incluse le rotte REST)
storage/                    database, upload e file generati (in sola scrittura)
test/                       collaudo, senza dipendenze esterne
```

## Collaudo

```bash
php test/app.php      # gestionale: dati letti dal sito, ordine di lavoro
php test/google.php   # Search Console: firma, lettura, segnali
php test/plugin.php   # plugin: le classi vere sopra un WordPress simulato
```

Il secondo non è una finta rete: esegue il codice del plugin con le funzioni di
WordPress sostituite da versioni in memoria, così gli errori di logica vengono
a galla. Ogni errore trovato in produzione diventa una verifica in questi file.

## Indirizzi cambiati

Quando un contenuto viene rinominato e cambia slug, il vecchio indirizzo comincia a dare 404:
i link che arrivano da fuori si perdono e la posizione guadagnata in Google riparte da zero.
Non lo fa il programma — lo fa chi modifica un articolo in WordPress — ma nessuno se ne accorge
finché non è tardi.

Il gestionale conserva l'indirizzo di ogni contenuto a ogni analisi. Confrontando le ultime due
per **identificativo di WordPress** (l'unica cosa che non cambia quando si rinomina), trova i
contenuti spostati e li mostra in *Collegamento*, con il pulsante che crea i 301 dal vecchio
indirizzo al nuovo. Il plugin li applica solo sulle pagine che danno 404, quindi non
interferisce con niente di quello che già funziona.

## Mandare i dati a chi sviluppa

Menù **Diagnostica**. Un dump del database di WordPress pesa centinaia di megabyte e contiene
email, password, IP di chi ha commentato e chiavi API: non è una cosa da spedire. Questo
pulsante legge il sito e compone un file con la sola forma dei contenuti — titolo, slug, date,
stato, testo, categorie, tag, meta SEO, allegati (indirizzi, non file), versioni di WordPress e
del plugin.

Restano fuori: utenti, email, password, commenti, indirizzi IP, chiavi API, impostazioni del
sito, revisioni e i blocchi di Elementor (di quelli resta solo il fatto che ci sono).

Il file è compresso, si scarica e sul server non resta niente. Un sito di trecento articoli sta
in pochi megabyte contro le centinaia di un dump completo.

Le meta che arrivano come array restano array: è la forma vera del dato, ed è esattamente
quella da cui sono nati i problemi più difficili da trovare.

## Il file XML come backup

L'export WXR di WordPress (Strumenti → Esporta → Tutti i contenuti) contiene testo, title,
description, parole chiave, categorie e tag di ogni contenuto: come *archivio* dei dati va
benissimo, ed è quello da cui nasce la prima analisi.

Quello che **non** contiene: i file delle immagini (solo i loro indirizzi), il tema, i plugin
e tutte le impostazioni del sito.

E soprattutto: **non è un ripristino**. L'importatore di WordPress salta i contenuti che
esistono già invece di sovrascriverli, quindi reimportare l'export non annulla niente — al
massimo crea doppioni. Per tornare indietro servono altre strade:

1. *Collegamento → Annulla* — il plugin conserva i valori precedenti di ogni contenuto toccato;
2. *Collegamento → Riporta indietro da un analisi archiviata* — ogni analisi conserva title,
   description e parola chiave come erano quel giorno, e la prima viene dall'export XML, cioè
   da prima di qualsiasi modifica. Si sceglie da quale analisi ripescare e si rimettono sul sito;
3. il backup del database dell'hosting, che è l'unico ripristino completo.

## Cosa si può disfare, e cosa no

| Operazione | Si torna indietro? | Come |
|---|---|---|
| Meta (title, description, estratto) | sì | il plugin conserva i valori precedenti · *Collegamento → Annulla* |
| Categorie riassegnate | sì, dalla 1.6.0 | copia salvata prima di sostituire · stesso pulsante |
| Riscritture | sì | restano bozze separate finché non le pubblichi tu |
| Riscrittura pubblicata | sì | WordPress conserva la revisione precedente |
| Contenuti cestinati | sì | cestino di WordPress, finché non lo si svuota |
| Immagini generate | sì | si eliminano dalla libreria media, ma la spesa resta |
| Dati aziendali, llms.txt, schema | non serve | sono ricalcolati e si sovrascrivono da soli |

Il plugin, disattivato, smette di applicare tutto quello che fa a runtime — schema, robots,
llms.txt, link automatici — senza lasciare tracce nel contenuto.

## Sicurezza

- `storage/` e `src/` contengono un `.htaccess` che ne blocca l'accesso diretto su Apache.
  Su Nginx aggiungi una regola equivalente, oppure tieni fuori dalla document root tutto
  tranne `public/`.
- I download passano da un controllo che impedisce di uscire dalla cartella dell'audit.
- I moduli usano un token di sessione anti-CSRF.
- L'app è pensata per uso interno: se la esponi in rete, proteggila con autenticazione
  HTTP di base.
