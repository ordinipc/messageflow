# Come applicare le correzioni

Punteggio di partenza: **34/100** — 5974 problemi rilevati.

## Ordine di esecuzione

### 1. Prima di toccare qualsiasi cosa
- Fai un backup completo (database + file).
- Verifica che Google Search Console sia collegata e che la sitemap sia inviata.

### 2. Risolvi il conflitto fra plugin SEO (10 minuti)
Rank Math e Yoast sono entrambi attivi e stampano gli stessi tag due volte.
Mantieni **Rank Math** (i suoi dati sono più completi), disattiva e disinstalla Yoast.

### 3. Compila i dati aziendali (15 minuti)
Apri `config.json` e sostituisci ogni `DA_COMPILARE`: telefono, indirizzo, CAP, P.IVA,
coordinate, scheda Google Business, nome e biografia dell'autore.
Poi rigenera i file: `node bin/seo-geo.js all --input <export.xml>`.
Senza questi dati lo schema locale resta incompleto e il capitolo SEO locale non migliora.

### 4. Installa il plugin (5 minuti)
1. Comprimi la cartella `plugin-wordpress/mdi-seo-geo-booster` in uno zip.
2. WordPress → Plugin → Aggiungi nuovo → Carica plugin → Attiva.
3. Vai su Impostazioni → Permalink e premi Salva (rigenera le regole per /llms.txt).
4. Controlla la nuova voce di menu "SEO & GEO".

Il plugin applica automaticamente: 326 title e description ottimizzate,
dati strutturati JSON-LD su tutte le pagine, 315 keyword
mappate per i link interni, alt e lazy loading sulle immagini, nofollow sui link social,
endpoint /llms.txt e /ai.txt, direttive robots per i crawler AI.

### 5. Carica i file di root
Copia `file-root/robots.txt` nella radice del sito (o lascia fare al plugin, che integra le
direttive tramite il filtro `robots_txt`).

### 6. Crea le pagine mancanti
Usa i template in `pagine-da-creare/`: **/chi-siamo/** e **/contatti/**.
Aggiungile al menu principale insieme a una voce **Blog** — oggi 311 articoli
non sono raggiungibili dalla navigazione.

### 7. Applica il triage editoriale
- Da eliminare: **5** articoli
- Da accorpare: **19**
- Da riscrivere: **220**
- Da mantenere: **67**

Imposta prima i **209 redirect 301** del file `redirect-301.csv`
(plugin Redirection o `redirect.htaccess`), poi cestina.

### 8. Segui il calendario
`piano-contenuti.md` contiene 12 settimane di lavoro già ordinate per priorità
e 239 schede di riscrittura con scaletta e FAQ.

## Verifiche dopo la pubblicazione
1. Rich Results Test su una pagina servizio e su un articolo con FAQ.
2. `maxdigitalinnovation.it/llms.txt` deve rispondere 200.
3. Search Console → Controllo URL → Richiedi indicizzazione sulle 10 pagine servizio.
4. Ripeti l'audit dopo 30 giorni: `node bin/seo-geo.js audit --input <nuovo-export.xml>`.
