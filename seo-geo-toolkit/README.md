# seo-geo-toolkit

Analizza l'esportazione WordPress (WXR) di un sito, individua i problemi **SEO** e **GEO**
— sia *geografico/locale* sia *Generative Engine Optimization*, la visibilità dentro le
risposte di AI Overviews, ChatGPT, Perplexity e Copilot — e genera tutto il materiale
per risolverli: plugin WordPress, meta ottimizzate, redirect, dati strutturati,
piano dei link interni e calendario editoriale.

Nessuna dipendenza esterna: solo Node.js ≥ 18.

## Uso

```bash
node bin/seo-geo.js all --input export.xml            # audit + triage + correzioni
node bin/seo-geo.js audit --input export.xml          # solo diagnosi
node bin/seo-geo.js triage --input export.xml         # solo classificazione articoli
node bin/seo-geo.js all -i export.xml -c config.json -o output
```

Prima di generare i fix definitivi va compilato `config.json`: ogni campo `DA_COMPILARE`
(telefono, indirizzo, P.IVA, coordinate, scheda Google Business, autore) alimenta lo
schema LocalBusiness, il footer NAP e la pagina Contatti.

## Cosa controlla

65 regole raggruppate in 10 aree, ciascuna con gravità, spiegazione dell'impatto e
soluzione. Ogni regola dichiara se è risolta automaticamente dal toolkit.

| Area | Esempi di controllo |
|---|---|
| Tecnico e indicizzazione | plugin SEO in conflitto, direttive robots, canonical, pagine di servizio indicizzate |
| On-page | lunghezza title e description, keyword nel title, gerarchia H1-H6, slug, cannibalizzazione |
| Contenuti | thin content, near-duplicate (Jaccard su shingle), freschezza, leggibilità Gulpease |
| Link e architettura | pagine orfane, link in uscita, anchor generici, dispersione di autorità, menu |
| Immagini | alt mancanti, peso, formati, immagine in evidenza, dimensioni esplicite |
| Dati strutturati | JSON-LD assente, FAQPage, BreadcrumbList, Service, Open Graph |
| SEO locale | NAP, LocalBusiness, copertura dei comuni, recensioni, cannibalizzazione locale |
| GEO (motori generativi) | llms.txt, crawler AI in robots.txt, blocco answer-first, FAQ, dati citabili, entità e `sameAs`, speakable |
| E-E-A-T | biografia autore, casi studio, tratti di contenuto prodotto in serie |
| Tassonomie | categoria sovraccarica, tag assenti, categorie fuori tema |

## Triage editoriale

Ogni articolo pubblicato viene classificato in **eliminare / accorpare / riscrivere / mantenere**
combinando qualità misurata (lunghezza, struttura, media, link, freschezza), similarità reale
fra i testi, sovrapposizione con le pagine servizio e cannibalizzazione delle focus keyword.
Per i contenuti da rimuovere viene indicata la destinazione del redirect 301.

## Cosa genera

```
output/
├── report.html                  dashboard navigabile con filtri
├── audit.md · audit.json · problemi.csv
├── triage.md · triage.csv · triage.json
├── meta-ottimizzate.csv         title, description, excerpt e slug riscritti
├── rank-math-bulk.csv           formato dell'editor massivo di Rank Math
├── redirect-301.csv · redirect.htaccess
├── piano-link-interni.csv       link interni proposti con anchor text
├── immagini-alt.csv             alt suggeriti, peso, formati da convertire
├── piano-contenuti.md/.csv      schede di riscrittura + calendario 12 settimane
├── schema/                      grafi JSON-LD per ogni URL
├── file-root/                   llms.txt, llms-full.txt, robots.txt, ai.txt
├── pagine-da-creare/            template HTML per Chi siamo, Contatti, footer NAP
└── plugin-wordpress/            plugin che applica le correzioni a runtime
```

## Il plugin generato

`mdi-seo-geo-booster` applica sul sito live, senza modificare i contenuti nel database:

- title, meta description ed excerpt ottimizzati (via i filtri di Rank Math se presente);
- meta robots con `max-image-preview:large`, `noindex` sulle pagine legali;
- grafo JSON-LD unico per pagina: Organization/ProfessionalService, WebSite, Person,
  BreadcrumbList, BlogPosting con `speakable`, Service, FAQPage estratto dagli H2/H3;
- link interni automatici da mappa keyword → URL, senza toccare titoli e link esistenti;
- blocco "Approfondimenti correlati" per recuperare le pagine orfane;
- `rel="nofollow sponsored noopener"` sui domini che disperdono autorità;
- alt, `loading`, `decoding` e `fetchpriority` sulle immagini;
- endpoint `/llms.txt`, `/llms-full.txt`, `/ai.txt` e direttive robots per i crawler AI;
- shortcode `[mdi_nap]`, `[mdi_faq]`, `[mdi_breadcrumb]`, `[mdi_in_breve]`;
- avvisi in bacheca su conflitti fra plugin SEO e dati aziendali mancanti.

## Struttura del codice

```
bin/seo-geo.js            interfaccia a riga di comando
src/parser/wxr.js         parser dell'export WordPress
src/core/                 modello del sito, estrazione HTML, metriche testuali italiane
src/rules/                le 65 regole, una per file tematico
src/audit.js              esecuzione delle regole e punteggi per area
src/analysis/triage.js    classificazione editoriale degli articoli
src/fix/                  generatori: meta, link interni, schema, llms, plugin, pagine, piano
src/report/               report HTML, Markdown, CSV
templates/plugin/         sorgenti PHP del plugin WordPress
```
