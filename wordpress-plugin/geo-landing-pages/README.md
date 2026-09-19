# Geo Landing Pages — landing page per città con SEO locale

Plugin WordPress che crea pagine locali su URL del tipo:

```
https://chiaviitalia.it/trapani/                          ← hub della città
https://chiaviitalia.it/trapani/duplicazione-chiavi/      ← landing servizio + città
https://chiaviitalia.it/trapani/apertura-porte/
https://chiaviitalia.it/palermo/pronto-intervento-fabbro/
```

Il contenuto non viene inventato: il plugin ti fa **le domande che Google si aspetta di
trovare risposte** in una pagina locale, e con le tue risposte costruisce le sezioni della
pagina, le FAQ e i dati strutturati.

---

## Installazione

1. Comprimi in zip la cartella `geo-landing-pages` (oppure lancia `./build.sh`).
2. WordPress → **Plugin → Aggiungi nuovo → Carica plugin** → seleziona lo zip → **Attiva**.
3. Vai in **Impostazioni → Permalink** e premi **Salva** una volta (rigenera le regole URL).

Requisiti: WordPress 5.9+, PHP 7.4+, permalink diversi da "Semplice".

---

## Primo avvio (5 minuti)

### 1. Impostazioni generali
**Landing locali → Impostazioni**

| Campo | Cosa metterci |
|---|---|
| Prefisso URL | vuoto per avere `/trapani/`; scrivi `citta` per avere `/citta/trapani/` |
| Nome attività, telefono, WhatsApp, email, P. IVA | i dati identici a quelli del profilo Google Business |
| Servizio principale | es. `servizi per chiavi e serrature` (usato nelle pagine città) |
| Tipo di attività (schema.org) | per un fabbro: **Locksmith** |
| Punteggio minimo | soglia sotto la quale una pagina resta `noindex` (default 60) |

### 2. Crea la prima città
**Landing locali → Aggiungi pagina**

- Titolo: `Trapani` → lo slug diventa `trapani` → URL `/trapani/`
- Lascia **Genitore** vuoto (in *Attributi pagina*): è una pagina città
- Compila il questionario, in particolare telefono, indirizzo, orari, zone servite
- Salva: quei dati verranno **ereditati da tutti i servizi di quella città**

### 3. Crea il primo servizio
**Landing locali → Aggiungi pagina**

- Titolo: `Duplicazione chiavi`
- **Attributi pagina → Genitore: Trapani** → URL `/trapani/duplicazione-chiavi/`
- Rispondi alle domande specifiche del servizio: prezzi, cosa comprende, processo, FAQ
- I campi lasciati vuoti mostrano in grigio il valore ereditato dalla città

### Quando la bozza diventa pubblica

Finché la pagina è in **bozza** il link è quello di anteprima di WordPress
(`?post_type=glp_landing&p=123`), così l'anteprima funziona. Appena la **pubblichi**
l'indirizzo diventa `/trapani/`, e i servizi figli `/trapani/duplicazione-chiavi/`. Le regole
degli URL vengono ricostruite a ogni salvataggio, quindi non serve fare altro.

**Attenzione a un caso**: se esiste già una pagina del sito con lo stesso slug (per esempio
una pagina `/servizi/` e una landing chiamata "Servizi"), la landing la copre. Il plugin te lo
segnala in rosso nel riquadro laterale prima che accada.

### 4. Molte città insieme
**Landing locali → Crea città in blocco**

```
Trapani|TP|Sicilia
Marsala|TP|Sicilia
Alcamo|TP|Sicilia
```

Crea le pagine **in bozza**. Pubblicale solo dopo aver risposto al questionario.

---

## Il questionario: perché queste domande

Le 8 sezioni corrispondono ai segnali che Google valuta su una pagina locale.

| Sezione | A cosa serve |
|---|---|
| 1. Servizio e luogo | rende la pagina univoca: città, provincia, keyword da intercettare |
| 2. Dati di contatto (NAP) | nome-indirizzo-telefono devono coincidere con il profilo Google Business: è il primo fattore di ranking locale |
| 3. Prova di presenza locale | quartieri, tempi di intervento, numeri reali, recensioni e approfondimento: è ciò che distingue una pagina vera da una doorway page |
| 4. E-E-A-T | chi risponde del servizio: nome, qualifiche, iscrizioni |
| 5. Offerta e prezzi | il prezzo è la prima domanda di ogni ricerca locale |
| 6. FAQ | le domande del riquadro "Le persone chiedono anche", con dati strutturati `FAQPage` |
| 7. SEO | title, description, indicizzazione, link interni tra città |
| 8. Conversione | telefono, WhatsApp, modulo: senza azione il traffico non serve |

### Il pulsante "Precarica le domande tipiche"
Nella sezione FAQ inserisce le 10 domande ricorrenti delle ricerche locali già adattate alla
città (`Quanto costa {servizio} a {citta}?`, `Intervenite di notte?`, …). **Le risposte le
scrivi tu**: sono la parte che rende il contenuto originale.

---

## Il punteggio di completezza (protezione anti-penalizzazione)

Ogni pagina ha un punteggio 0-100 nel riquadro laterale, con l'elenco delle risposte mancanti.

**Sotto la soglia la pagina resta automaticamente `noindex` ed esce dalla sitemap.**

Non è una limitazione, è la protezione principale del plugin: decine di pagine città
identiche tranne il nome del comune sono esattamente ciò che Google classifica come
[doorway page](https://developers.google.com/search/docs/essentials/spam-policies#doorways),
e la penalizzazione colpisce l'intero sito, non solo quelle pagine.

Per forzare l'indicizzazione: sezione 7 → **Indicizzazione → Indicizza sempre**.

---

## Cosa genera in automatico

**Nel contenuto:** introduzione, elenco servizi della città, perché sceglierci, numeri in
evidenza, processo passo-passo, prezzi, zone servite, recensioni, chi se ne occupa, mappa e
indicazioni, FAQ a fisarmonica, call to action, link alle città correlate. Le sezioni senza
risposte non vengono stampate.

**Nell'`<head>`:** `<title>`, meta description, canonical, Open Graph, Twitter Card, meta geo,
`robots`, e JSON-LD con `LocalBusiness` (indirizzo, coordinate, orari, aree servite,
recensioni reali), `Service` con `offers`, `FAQPage` e `BreadcrumbList`.

Se hai **Yoast, Rank Math, SEOPress o AIOSEO**, il plugin non stampa tag doppi: passa i valori
al plugin SEO attivo.

---

## Segnaposto nei modelli

Utilizzabili in title, description, H1, introduzione e nelle FAQ:

`{citta}` `{provincia}` `{regione}` `{cap}` `{servizio}` `{servizio_default}` `{brand}`
`{telefono}` `{indirizzo}` `{prezzo_da}` `{anni}` `{anni_frase}` `{interventi_frase}`
`{tempo_intervento}` `{perche_primo}` `{sep}` `{anno}`

Se un segnaposto resta vuoto, il plugin ripulisce il testo (niente `a  | ` o parentesi vuote).

---

## Aspetto grafico

Le landing usano uno stile proprio allineato al brand: intestazione scura come la home,
accenti colorati, pulsanti a pillola, schede, FAQ a fisarmonica e riquadro finale di contatto.

**Landing locali → Impostazioni → Aspetto grafico**

| Impostazione | Cosa fa |
|---|---|
| Stile del plugin | togli la spunta per ereditare solo lo stile del tema |
| Intestazione della pagina | blocco scuro con occhiello, titolo, testo e pulsanti |
| Colori | accento, sfondo scuro, testo, sfondo chiaro |
| Arrotondamento angoli | da 0 (squadrato) a 40 px |
| Larghezza delle bande | intestazione e CTA a tutto schermo |

I colori dei testi sopra l'accento e sopra lo sfondo scuro sono calcolati in automatico
(formula di contrasto WCAG): se cambi il giallo con un colore scuro, le scritte diventano
bianche da sole.

### Se vedi il titolo due volte

Alcuni temi stampano già il titolo della pagina. In quel caso imposta **Intestazione della
pagina → Intestazione grafica senza titolo**: resta il blocco scuro con testo e pulsanti, ma
l'H1 lo gestisce il tema. È importante per la SEO che l'H1 sia uno solo.

### Se compare una barra di scorrimento orizzontale

Togli la spunta da **Larghezza delle bande**: alcuni temi a larghezza fissa non gradiscono le
sezioni a tutto schermo.

Il font non viene forzato: i titoli ereditano quello del tema, così le pagine restano coerenti
col resto del sito anche se cambi tema.

---

## Le altre pagine del sito

**Landing locali → Pagine del sito**

Elenca le pagine che un sito di servizi locali dovrebbe avere, dice **perché** servono e crea
quelle mancanti **in bozza**, già con lo stile delle landing e i dati aziendali inseriti.

| Pagina | Perché |
|---|---|
| Chi siamo | è il segnale più diretto su chi c'è dietro al sito (E-E-A-T) |
| I nostri servizi | raccoglie i servizi e distribuisce link verso le città |
| Dove operiamo | l'indice delle città: senza, le landing restano isolate |
| Contatti | recapiti verificabili, coerenti con Google Business |
| Domande frequenti | intercetta le ricerche in forma di domanda |
| Recensioni | prova di affidabilità (solo recensioni reali) |
| Note legali e dati aziendali | in Italia P. IVA e sede vanno indicate sul sito |
| Privacy Policy | obbligatoria col GDPR se raccogli dati |
| Cookie Policy | richiesta se usi cookie non tecnici |
| Termini e condizioni | cosa comprende il servizio, tempi, garanzie |
| Mappa del sito | indice leggibile, utile quando le città sono molte |

Le pagine già esistenti sul sito vengono riconosciute dallo slug e **non** vengono toccate.

### Le pagine legali sono tracce, non testi conformi

Privacy, cookie e termini vengono generate come **scaletta con segnaposto `[DA COMPLETARE]`**:
struttura dei capitoli e dati aziendali, non il testo legale. Una privacy policy richiede i
dati reali del trattamento (finalità, basi giuridiche, conservazione, destinatari) e va
verificata da chi se ne occupa per te. Pubblicarne una incompleta è un problema legale, non
un dettaglio SEO.

---

## Codice personalizzato (HTML, CSS, JavaScript)

Due livelli:

- **Tutte le landing**: Impostazioni → *Codice personalizzato*
- **Una sola pagina**: sezione 9 del questionario — HTML prima/dopo le sezioni, CSS, JavaScript

Dove finisce il codice:

| Campo | Dove viene stampato |
|---|---|
| HTML prima / dopo | nel contenuto, attorno alle sezioni generate (accetta gli shortcode) |
| CSS | in `<style>` nell'intestazione della pagina |
| JavaScript | in `<script>` a fine pagina |

**Sicurezza.** I campi sono visibili e salvabili solo da chi ha il permesso `unfiltered_html`
(gli amministratori). Un redattore non li vede e, salvando la pagina, non li cancella. Le
sequenze `</style>` e `</script>` vengono neutralizzate, così il codice non può chiudere in
anticipo il proprio blocco.

**Il JavaScript gira dentro un `try/catch`**: un errore nel tuo codice viene scritto nella
console del browser invece di bloccare gli altri script del sito. Restano due cose che il
plugin non può evitare: script pesanti peggiorano i tempi di caricamento che Google misura, e
codice di terze parti può installare cookie che vanno dichiarati nella cookie policy.

---

## Assistente AI (Google Gemini)

Gemini scrive i testi **partendo dalle risposte che hai inserito**. Non è un generatore di
contenuti a comando: senza risposte nel questionario non ha materiale e produce banalità.

### Attivazione

1. Crea una chiave gratuita su [Google AI Studio](https://aistudio.google.com/apikey).
2. **Landing locali → Impostazioni → Assistente AI**: spunta l'attivazione e incolla la chiave.
3. Premi **Verifica chiave e carica i modelli** e scegli il modello (i `flash` costano meno e
   bastano per questi testi).

**Modo più sicuro di conservare la chiave** — in `wp-config.php`:

```php
define( 'GLP_GEMINI_API_KEY', 'la-tua-chiave' );
```

Così la chiave non finisce nel database e non è leggibile dagli altri amministratori. Se la
costante è presente, il campo nelle impostazioni viene ignorato.

### Cosa può scrivere

| Pulsante | Campo | Cosa fa |
|---|---|---|
| Genera il title | SEO title | massimo 60 caratteri, servizio e città in testa |
| Genera la meta description | Meta description | massimo 155 caratteri con un motivo concreto e una CTA |
| Riscrivi i motivi | Perché sceglierci | riformula i tuoi motivi, non ne aggiunge di nuovi |
| Riscrivi cosa comprende | Servizi inclusi | riscrive l'elenco in linguaggio da cliente |
| Scrivi i passaggi | Processo | 3-5 passaggi dal contatto alla conclusione |
| Scrivi le risposte alle FAQ | FAQ | risponde alle tue domande; se sono meno di 5 ne aggiunge |
| Scrivi l'approfondimento | Approfondimento | 2-4 paragrafi di testo specifico per quella città |
| Riscrivi le indicazioni | Come raggiungerci | riscrive usando solo i riferimenti che hai dato |

I pulsanti leggono anche le risposte **non ancora salvate**: puoi compilare e generare senza
passare dal salvataggio.

### I limiti, detti chiaramente

Il prompt vieta di inventare prezzi, telefoni, indirizzi, orari, tempi di intervento, anni di
attività, numeri di clienti, certificazioni, garanzie e recensioni. Questo **riduce molto** le
invenzioni, ma non è una garanzia: un modello linguistico può sempre sbagliare.

Per questo ogni campo generato resta **marcato in viola** e compare nel riquadro laterale
sotto *"Testi scritti dall'assistente da rileggere"*, con un pulsante *"l'ho riletto"*. Il
segno non sparisce da solo.

Tre cose da verificare sempre, prima di pubblicare:

1. **I numeri** — prezzi, tempi, anni, quantità: che siano quelli che hai scritto tu.
2. **Le promesse** — garanzie e impegni che l'azienda deve poter mantenere.
3. **I riferimenti locali** — vie, quartieri, mezzi: che esistano davvero e siano giusti.

Per contenuti che riguardano sicurezza, salute o denaro, Google valuta con particolare
severità (criteri YMYL): un dato sbagliato lì costa più di una pagina non scritta.

### Costi e privacy

Le chiamate vanno all'API di Google e **consumano il credito della tua chiave**. Ogni
generazione invia a Google le risposte del questionario di quella pagina: sono dati aziendali
pubblici (servizi, zone, orari), ma se inserisci nomi di clienti nelle recensioni, quelli
partono insieme al resto. Il piano gratuito di Google AI Studio può usare i dati inviati per
migliorare i servizi: per non farlo, serve un piano a pagamento. Verifica le condizioni
correnti prima di inviare dati sensibili.

---

## Shortcode

| Shortcode | Effetto |
|---|---|
| `[glp_citta colonne="4" titolo="Dove operiamo"]` | elenco di tutte le città |
| `[glp_servizi]` | servizi della città corrente (o `citta="123"`) |
| `[glp_faq]` | solo il blocco FAQ |
| `[glp_breadcrumbs]` | briciole di pane |
| `[glp_sezioni]` | tutte le sezioni generate |

---

## Dopo la pubblicazione

1. **Search Console** → Controllo URL → Richiedi indicizzazione della prima città.
2. Verifica i dati strutturati con il [Rich Results Test](https://search.google.com/test/rich-results).
3. Controlla che la sitemap (`/wp-sitemap.xml`) contenga solo le pagine complete.
4. Collega ogni landing dal profilo Google Business della zona, se ne hai uno.
5. Pubblica **poche città complete** prima di aggiungerne altre: 10 pagine vere posizionano
   più di 200 pagine vuote.

---

## Per sviluppatori

```php
add_filter( 'glp_questionnaire_groups', function ( $groups ) { /* aggiungi domande */ return $groups; } );
add_filter( 'glp_faq_suggestions',      function ( $faq ) { /* domande del tuo settore */ return $faq; } );
add_filter( 'glp_tokens',               function ( $tokens, $post_id ) { return $tokens; }, 10, 2 );
add_filter( 'glp_sections_html',        function ( $html, $post_id ) { return $html; }, 10, 2 );
add_filter( 'glp_hero_html',            function ( $html, $post_id ) { return $html; }, 10, 2 );
add_filter( 'glp_schema_graph',         function ( $data, $post_id ) { return $data; }, 10, 2 );
add_filter( 'glp_ai_tasks',             function ( $tasks ) { /* attività dell'assistente */ return $tasks; } );
add_filter( 'glp_ai_system_rules',      function ( $rules, $post_id ) { return $rules; }, 10, 2 );
```

Override del template nel tema: `/wp-content/themes/<tema>/geo-landing-pages/single-glp_landing.php`
