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

### Database

SQLite è l'impostazione predefinita e non richiede nulla: il file nasce da solo in
`storage/audit.sqlite`. Per usare MySQL apri `config.php`:

```php
'database' => array(
	'driver' => 'mysql',
	'mysql'  => array( 'host' => '127.0.0.1', 'nome' => 'seo_geo_audit', 'utente' => '...', 'password' => '...' ),
),
```

Le tabelle vengono create automaticamente; in alternativa importa `schema.sql`.

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
- link interni automatici da mappa keyword → URL, senza toccare titoli e link esistenti;
- blocco "Approfondimenti correlati" per recuperare le pagine orfane;
- `rel="nofollow sponsored noopener"` sui domini che disperdono autorità;
- alt, `loading`, `decoding` e `fetchpriority` sulle immagini;
- endpoint `/llms.txt`, `/llms-full.txt`, `/ai.txt` e direttive robots per i crawler AI;
- shortcode `[mdi_nap]`, `[mdi_faq]`, `[mdi_breadcrumb]`, `[mdi_in_breve]`;
- avvisi in bacheca su conflitti fra plugin SEO e dati aziendali mancanti.

**Prima di installarlo** compila i campi `DA_COMPILARE` in `config.php` (telefono,
indirizzo, CAP, partita IVA, coordinate, scheda Google Business, autore) e rigenera
l'analisi: quei dati alimentano lo schema LocalBusiness, il footer NAP e llms.txt.
Il plugin ignora i segnaposto non compilati, quindi non stampa mai dati finti.

---

## Struttura del progetto

```
index.php                   reindirizza a public/ (per le installazioni in sottocartella)
.htaccess                   serve public/ come radice quando Apache lo consente
config.php                  dati aziendali, database, soglie SEO
schema.sql                  schema MySQL (opzionale)
cli/audit.php               interfaccia a riga di comando
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
├── Fix/                    meta, link interni, dati strutturati, llms.txt
└── Export.php              CSV, file per i crawler, assemblaggio del plugin
plugin-wordpress/           sorgenti del plugin generato
storage/                    database, upload e file generati (in sola scrittura)
```

## Sicurezza

- `storage/` e `src/` contengono un `.htaccess` che ne blocca l'accesso diretto su Apache.
  Su Nginx aggiungi una regola equivalente, oppure tieni fuori dalla document root tutto
  tranne `public/`.
- I download passano da un controllo che impedisce di uscire dalla cartella dell'audit.
- I moduli usano un token di sessione anti-CSRF.
- L'app è pensata per uso interno: se la esponi in rete, proteggila con autenticazione
  HTTP di base.
