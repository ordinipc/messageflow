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
| 3. Prova di presenza locale | quartieri, tempi di intervento, numeri reali, recensioni: è ciò che distingue una pagina vera da una doorway page |
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
add_filter( 'glp_schema_graph',         function ( $data, $post_id ) { return $data; }, 10, 2 );
```

Override del template nel tema: `/wp-content/themes/<tema>/geo-landing-pages/single-glp_landing.php`
