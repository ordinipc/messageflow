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

## Non si riscrive niente due volte

I dati della città si compilano **una volta sola**, sulla pagina città. Tutte le sue pagine e
i suoi servizi li ereditano: **29 campi**, fra cui telefono, indirizzo, orari, zone servite,
comuni limitrofi, coordinate, mappa, recensioni, referente, qualifiche, certificazioni,
partita IVA, anni di attività e tempo di intervento.

Aprendo una pagina servizio vedi quei campi vuoti, ma **vuoti significa "usa il valore della
città"**: sotto ogni campo compare in grigio il valore ereditato, e in alto a destra un
riquadro verde dice quante risposte arrivano dalla città e quali.

Su una pagina servizio servono solo le risposte sue: nome del servizio, prezzo, cosa comprende,
processo, garanzia, FAQ e i testi SEO. Il punteggio di qualità conta anche l'eredità: un
servizio con due soli campi compilati sotto una città completa parte già sopra il 50.

Se cambi il telefono sulla pagina città, cambia ovunque.

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

Le landing e le pagine generate usano lo **stesso linguaggio visivo dell'hero Servizi**:
giallo `#ffd400` su nero, angoli a 5 px, micro-etichette maiuscole con barra gialla, linee
sottili, numerazioni `01` e frecce, punto di stato pulsante, riga tecnica con punto scanner e
angolo decorativo.

**Landing locali → Impostazioni → Aspetto grafico**

| Impostazione | Cosa fa |
|---|---|
| Stile del plugin | togli la spunta per ereditare solo lo stile del tema |
| Intestazione della pagina | con titolo H1, senza titolo, oppure nessuna |
| Colori | accento, sfondo scuro, testo, sfondo chiaro |
| Arrotondamento angoli | 5 px di serie, come i pulsanti del sito |
| Larghezza delle bande | intestazione e CTA a tutto schermo |

Il **font non viene forzato**: i titoli ereditano quello del tema, così le pagine restano
coerenti col resto del sito. I colori dei testi sopra l'accento e sopra lo sfondo scuro sono
calcolati dal contrasto (WCAG): cambiando il giallo con un colore scuro le scritte diventano
bianche da sole.

### L'intestazione

Riproduce la struttura dell'hero Servizi, riempita con i dati del questionario:

- **occhiello** `Chiavi Italia · Trapani`
- **stato** con punto pulsante: orario 24/24, tempo di intervento o anni di attività
- **titolo** con la città evidenziata in giallo
- **pulsanti** preventivo/telefono e WhatsApp
- **indice dei servizi**: sulla pagina città i suoi servizi, su una pagina servizio gli altri
  servizi della stessa città, numerati con il totale in alto a destra
- **riga tecnica** in basso e angolo decorativo

### Le animazioni non possono far sparire il contenuto

La comparsa progressiva parte da `opacity:0`, quindi un JavaScript che non gira lascerebbe la
pagina vuota. Per questo il contenuto viene nascosto **solo dopo** che uno script
nell'intestazione conferma che JavaScript è attivo, e una rete di sicurezza lo mostra comunque
dopo 2,5 secondi se lo script principale non parte (cache aggressive, errori di altri plugin).
Senza JavaScript la pagina è visibile e completa.

Con `prefers-reduced-motion` attivo le animazioni sono disattivate e tutto è visibile subito.

## Le pagine di ogni città

**Landing locali → Pagine del sito**

Ogni città può avere il suo corredo di pagine, create **dentro la città**:

```
/trapani/chi-siamo/            /marsala/chi-siamo/
/trapani/servizi/              /marsala/servizi/
/trapani/contatti/             /marsala/contatti/
/trapani/zone-servite/         …
/trapani/prezzi/
/trapani/recensioni/
/trapani/domande-frequenti/
```

Scegli quali pagine e per quali città: vengono create **in bozza** sotto ciascuna. Le pagine
già esistenti non vengono toccate né duplicate.

### Restano allineate da sole

Il testo di apertura è tuo da scrivere; i blocchi di dati sono shortcode che leggono la
**scheda della città**:

```
[glp_sezione tipo="dove" da="citta"]
[glp_sezione tipo="orari" da="citta"]
[glp_sezione tipo="recensioni" da="citta"]
```

Se cambi telefono, orari o zone sulla pagina città, cambiano su tutte le sue pagine senza
rigenerare niente. Tipi disponibili: `intro`, `approfondimento`, `servizi`, `incluso`,
`perche`, `processo`, `prezzi`, `zone`, `orari`, `recensioni`, `team`, `dove`, `faq`, `cta`,
`correlate`.

### Attenzione ai contenuti fotocopia

Sette pagine per città moltiplicate per venti città fanno 140 pagine che nascono quasi
identiche: sono i tuoi dati locali a renderle diverse. Finché il punteggio di qualità resta
sotto la soglia restano **noindex** ed escono dalla sitemap. Non è un intralcio, è la
protezione: decine di "Chi siamo" uguali tranne il nome del comune sono esattamente ciò che
Google declassa.

Meglio partire da poche città complete che da tutte le pagine per tutte le città.

---

## Diagnostica

**Landing locali → Diagnostica**

Dice lo stato reale del sito invece di lasciartelo indovinare: permalink, città senza
contenuti, servizi senza città, tipi non collegati a una pagina, stato dei due menu, regole
degli indirizzi.

Il controllo più utile è **"Servizi collegati a una città"**. Una pagina di primo livello
dovrebbe essere una città; se ha un tipo di servizio assegnato, quasi certamente è un servizio
a cui manca la città genitore — e risponde su `/duplicazione-chiavi-auto/` invece che su
`/trapani/duplicazione-chiavi-auto/`. È il motivo più frequente per cui una città resta vuota,
il menu non compare e le schede contano zero.

Dalla stessa schermata le sposti sotto la città giusta, senza aprirle una per una.

---

## I tipi di servizio e le schede

**Landing locali → Tipi di servizio**

Ogni tipo ha due usi:

1. **Collega la stessa pagina servizio fra città diverse** (la sezione "Lo stesso servizio in
   altre città" in fondo alle pagine locali).
2. **Punta alla pagina del sito che descrive quel servizio** — per esempio
   `/duplicazione-chiavi-auto/`. Si sceglie dal campo *Pagina del servizio*.

Da lì si costruiscono due elenchi a schede:

| Shortcode | Dove metterlo | Cosa mostra |
|---|---|---|
| `[glp_servizi_cards]` | sulla pagina **Servizi** | una scheda per tipo: nome, descrizione, in quante città è attivo, link alla pagina del servizio |
| `[glp_servizio_citta]` | sulla pagina **di un servizio** | le città in cui quel servizio è pubblicato |

`[glp_servizio_citta]` riconosce da solo di quale servizio si tratta, se la pagina è quella
collegata al tipo. Altrimenti si indica: `[glp_servizio_citta tipo="duplicazione-chiavi-auto"]`.

Attributi utili: `colonne="4"`, `descrizione="no"`, `citta="no"`.

La **descrizione** del tipo è quella che compare nelle schede, e l'**immagine o icona** è
facoltativa. Un tipo senza pagina collegata appare nella griglia ma non è cliccabile, ed è il
modo per accorgersene.

---

## Il menu della città

Compare sulla pagina città e su tutte le sue pagine e servizi, e si costruisce da solo con
quello che pubblichi: prima le pagine della città nell'ordine in cui sono definite, poi i
servizi. La voce corrente è marcata, e le pagine della città perdono il " a Trapani" dal
titolo per restare leggibili.

```
● TRAPANI │ Chi siamo  Contatti  Prezzi  Recensioni  Duplicazione chiavi  Apertura porte
```

**Landing locali → Impostazioni → Menu della città**

| Impostazione | Cosa fa |
|---|---|
| Posizione | sotto l'intestazione (predefinito), sopra, oppure nascosto |
| Cosa elencare | pagine e servizi, solo le pagine, solo i servizi |
| Comportamento | resta agganciato in alto durante lo scorrimento |

Puoi anche metterlo dove vuoi con lo shortcode `[glp_menu_citta]`.

### Il menu in alto del tema

Sulle pagine di una città il menu dell'intestazione può mostrare anche la navigazione di quella
zona: **Menu in alto del tema** → *Aggiungi la città come voce a tendina* (oppure
*Sostituiscilo*, che però toglie Home, Servizi e Blog da quelle pagine).

Se il tema ha più menu (principale, mobile, piè di pagina), scegli la posizione giusta in
**In quale menu**, altrimenti le voci compaiono ovunque.

### Perché il menu non si vede

Si costruisce con quello che è **pubblicato** sotto la città. Se Trapani non ha ancora né
pagine né servizi pubblicati, non c'è niente da elencare e il menu non compare — così come non
compare il pannello "Servizi a Trapani" nell'intestazione. Pubblica almeno un servizio o una
pagina della città e compaiono entrambi.

Su desktop le voci vanno a capo, così non se ne nasconde nessuna; sul telefono diventano una
riga sola che scorre, con il nome della città fermo a sinistra.

---

## Le pagine legali (una per tutto il sito)

Privacy, cookie, termini, note legali e mappa del sito **non** vanno duplicate per città: il
trattamento dei dati e le condizioni di servizio sono unici. Restano pagine WordPress normali,
in **Pagine**, e si creano dalla seconda sezione della stessa schermata.

Privacy, cookie e termini sono **tracce** con segnaposto `[DA COMPLETARE]`: struttura e dati
aziendali, non testo legale conforme. Vanno completate con i dati reali del trattamento e
verificate da chi se ne occupa per te.

---

## Codice personalizzato (HTML, CSS, JavaScript)

Due livelli:

- **Tutte le landing**: Impostazioni → *Codice personalizzato*
- **Una sola pagina**: sezione 9 del questionario — HTML prima/dopo le sezioni, CSS, JavaScript

| Campo | Dove viene stampato |
|---|---|
| HTML prima / dopo | nel contenuto, attorno alle sezioni generate (accetta gli shortcode) |
| CSS | in `<style>` nell'intestazione della pagina |
| JavaScript | in `<script>` a fine pagina |

**Sicurezza.** I campi sono visibili e salvabili solo da chi ha il permesso `unfiltered_html`
(gli amministratori). Un redattore non li vede e, salvando la pagina, non li cancella. Le
sequenze `</style>` e `</script>` vengono neutralizzate. Il JavaScript gira dentro un
`try/catch`: un errore finisce nella console invece di bloccare gli altri script del sito.

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
