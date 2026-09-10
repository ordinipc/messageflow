# Audit SEO & GEO — Max Digital Innovation

Sito analizzato: https://maxdigitalinnovation.it  
Data analisi: 2026-09-10  
Contenuti esaminati: 311 articoli, 15 pagine, 95 file media

## Punteggio complessivo: 34/100

```
███████░░░░░░░░░░░░░ 34/100
```

Problemi rilevati: **5974** su **65** controlli non superati — 8 critici, 30 di livello alto, 23 medi, 4 bassi.

## Punteggio per area

| Area | Punteggio | Rilievi |
|---|---|---|
| 🤖 GEO — Generative Engine Optimization | 0/100 | 11 |
| 🔗 Link interni e architettura | 13/100 | 5 |
| ⚙️ Tecnico e indicizzazione | 26/100 | 8 |
| 📝 On-page (title, meta, URL) | 28/100 | 11 |
| 🧩 Dati strutturati | 37/100 | 4 |
| 📍 SEO locale (GEO geografico) | 38/100 | 5 |
| 📄 Qualità dei contenuti | 45/100 | 8 |
| 🏅 E-E-A-T e affidabilità | 54/100 | 4 |
| 🖼️ Immagini e performance | 66/100 | 6 |
| 🗂️ Tassonomie e archivi | 71/100 | 3 |

## Problemi in dettaglio

### 🔴 CRITICO — TEC-01: Due plugin SEO attivi contemporaneamente (Rank Math + Yoast)

**Estensione:** 1 occorrenze  
**Perché conta:** Entrambi stampano title, meta description, canonical, Open Graph e schema: si generano tag duplicati e in conflitto e Google può scegliere quello sbagliato. È anche doppio carico sul server.  
**Come si risolve:** Scegliere Rank Math (i dati sono più completi), migrare i dati residui di Yoast, disinstallare Yoast e ripulire il postmeta _yoast_*. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | Rank Math su 326 contenuti e Yoast su 278 contenuti: entrambi installati |

### 🔴 CRITICO — LOC-02: Indirizzo fisico e dati aziendali (P.IVA) assenti

**Estensione:** 1 occorrenze  
**Perché conta:** Indirizzo e partita IVA sono richiesti dalla normativa italiana per i siti aziendali e sono segnali di fiducia usati da Google per la valutazione E-E-A-T e per il local ranking.  
**Come si risolve:** Footer + pagina Contatti + schema PostalAddress generati dal fixer. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | partita IVA non presente nel sito |

### 🔴 CRITICO — GEO-01: File llms.txt assente

**Estensione:** 1 occorrenze  
**Perché conta:** llms.txt è lo standard emergente con cui si dichiara ai modelli quali contenuti del sito sono autorevoli e come vanno citati. Senza, il crawler AI deve indovinare la struttura del sito.  
**Come si risolve:** Generazione di llms.txt e llms-full.txt con mappa dei servizi, delle guide e dei dati aziendali. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | nessun llms.txt dichiarato (non presente nell’export, da verificare in root) |

### 🔴 CRITICO — GEO-02: robots.txt non regola i crawler AI (GPTBot, ClaudeBot, PerplexityBot)

**Estensione:** 1 occorrenze  
**Perché conta:** Se i crawler dei motori generativi non sono esplicitamente ammessi (o sono bloccati da regole generiche) il sito non può comparire nelle risposte AI, che oggi intercettano una quota crescente di ricerche informazionali.  
**Come si risolve:** robots.txt generato con allow espliciti per GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended, più sitemap e llms.txt. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | robots.txt da rigenerare con direttive esplicite per i crawler AI |

### 🔴 CRITICO — SCH-01: Nessun dato strutturato personalizzato (JSON-LD) nei contenuti

**Estensione:** 326 occorrenze su 325 URL  
**Perché conta:** Senza schema Google non ottiene entità esplicite: niente rich result, niente knowledge panel e i motori generativi faticano ad attribuire le informazioni al brand.  
**Come si risolve:** Il plugin inietta Organization/ProfessionalService, WebSite+SearchAction, BreadcrumbList, Article, FAQPage, Service e Person. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | nessun JSON-LD nel contenuto |
| / | nessun JSON-LD nel contenuto |
| /digital-strategies | nessun JSON-LD nel contenuto |
| /cookie-policy | nessun JSON-LD nel contenuto |
| /servizi-digitali-palermo | nessun JSON-LD nel contenuto |
| /realizzazione-siti-web-a-palermo | nessun JSON-LD nel contenuto |
| /gestione-social-media-palermo | nessun JSON-LD nel contenuto |
| /digital-marketing-palermo | nessun JSON-LD nel contenuto |
| … | altri 318 casi nel file audit.json |

### 🔴 CRITICO — LNK-01: Pagine orfane: nessun link interno in entrata

**Estensione:** 223 occorrenze su 222 URL  
**Perché conta:** Una pagina che nessun’altra linka riceve pochissimo PageRank interno e viene scansionata di rado: è la causa numero uno di articoli mai posizionati.  
**Come si risolve:** Il fixer genera una mappa di link interni (keyword → URL) applicata automaticamente dal plugin. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | 0 link interni in entrata |
| /cookie-policy | 0 link interni in entrata |
| /lavora-con-noi | 0 link interni in entrata |
| /innovazione-digitale-pmi-competitive | 0 link interni in entrata |
| /innovazione-digitale-palermo | 0 link interni in entrata |
| /cybersecurity-per-aziende | 0 link interni in entrata |
| /strategia-marketing-digitale | 0 link interni in entrata |
| /campagne-ai-driven-marketing-automation | 0 link interni in entrata |
| … | altri 215 casi nel file audit.json |

### 🔴 CRITICO — CNT-01: Contenuto molto scarno (thin content, < 300 parole)

**Estensione:** 3 occorrenze su 3 URL  
**Perché conta:** Sotto le 300 parole la pagina raramente soddisfa un intento di ricerca informazionale: Google la considera di scarso valore e spesso non la indicizza affatto.  
**Come si risolve:** Il piano di riscrittura indica per ogni pagina la scaletta da sviluppare fino a 900-1.500 parole utili. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /lavora-con-noi | 299 parole |
| /il-laboratorio-creativo-di-una-web-agency-dove-nascono-le-idee | 298 parole |
| /tiktok-e-instagram-reels-perche-ogni-impresa-a-palermo-deve-sfruttarli-ora | 262 parole |

### 🔴 CRITICO — CNT-03: Contenuti quasi duplicati fra loro

**Estensione:** 4 occorrenze su 3 URL  
**Perché conta:** Testi sovrapposti generano cannibalizzazione e segnalano contenuto generato in serie: Google ne indicizza uno solo e svaluta il resto del sito.  
**Come si risolve:** Unire in un unico articolo approfondito e impostare 301 dalle versioni ridondanti. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /i-segreti-dei-video-virali-tecniche-e-strumenti-da-conoscere | similarità 90% con /come-trasformare-unidea-in-un-video-virale-di-successo/ |
| /i-segreti-dei-video-virali-tecniche-e-strumenti-da-conoscere | similarità 90% con /scopri-come-un-semplice-video-ha-raggiunto-1-milione-di-visualizzazioni-in-24-ore/ |
| /come-trasformare-unidea-in-un-video-virale-di-successo | similarità 93% con /scopri-come-un-semplice-video-ha-raggiunto-1-milione-di-visualizzazioni-in-24-ore/ |
| /3-trucchi-per-rendere-i-tuoi-reels-irresistibili | similarità 87% con /scopri-strategie-efficaci-per-creare-reels/ |

### 🟠 ALTO — LNK-04: Link esterni in dofollow che disperdono autorità

**Estensione:** 23 occorrenze  
**Perché conta:** Centinaia di link dofollow verso profili social e aggregatori trasferiscono fuori il valore del dominio senza ritorno SEO.  
**Come si risolve:** Il plugin applica rel="nofollow sponsored" ai domini configurati e target _blank sicuro. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | 120 link dofollow verso linktr.ee |
| (sito) | 70 link dofollow verso www.youtube.com |
| (sito) | 25 link dofollow verso www.instagram.com |
| (sito) | 18 link dofollow verso youtube.com |
| (sito) | 17 link dofollow verso www.google.com |
| (sito) | 11 link dofollow verso wa.me |
| (sito) | 2 link dofollow verso www.verycontent.it |
| (sito) | 2 link dofollow verso wildflowermood.com |
| … | altri 15 casi nel file audit.json |

### 🟠 ALTO — LNK-06: Il blog non è raggiungibile dal menu principale

**Estensione:** 1 occorrenze  
**Perché conta:** Se gli articoli non sono linkati dalla navigazione, il crawler li scopre solo dalla sitemap: profondità di click alta e scansione rara.  
**Come si risolve:** Aggiungere al menu una voce Blog/Risorse e sezioni "articoli correlati" nelle pagine servizio. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | menu con 7 voci, nessuna porta al blog (311 articoli non navigabili) |

### 🟠 ALTO — LNK-07: Menu costruito su ancore della home invece che su pagine reali

**Estensione:** 4 occorrenze  
**Perché conta:** Voci come /#contatti non creano URL indicizzabili: il sito perde pagine posizionabili per query come "contatti" o "chi siamo".  
**Come si risolve:** Creare pagine autonome (/chi-siamo/, /contatti/) e collegarle nel menu. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | voce di menu "Expertise" punta a /#cosa-facciamo |
| (sito) | voce di menu "Brand Growth" punta a /#metodo |
| (sito) | voce di menu "About" punta a /#about |
| (sito) | voce di menu "Contatti" punta a /#contatti |

### 🟠 ALTO — LOC-08: Nessuna recensione o testimonianza strutturata

**Estensione:** 1 occorrenze  
**Perché conta:** Le recensioni sono un fattore di ranking locale e alimentano le stelline in SERP; sono anche uno dei contenuti più citati dalle risposte AI su fornitori di servizi.  
**Come si risolve:** Raccogliere recensioni Google, pubblicare testimonianze con schema Review/AggregateRating (solo se reali e verificabili). _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | nessuna recensione strutturata sul sito |

### 🟠 ALTO — GEO-06: Autore non attribuito a una persona reale

**Estensione:** 2 occorrenze  
**Perché conta:** I motori generativi valutano l’attribuibilità: un contenuto firmato da una persona con biografia e profili verificabili è più affidabile di uno firmato da un login generico.  
**Come si risolve:** Schema Person + author box; rinominare gli utenti WordPress con nome e cognome reali. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | autore "Max Digital" senza nome e cognome reali |
| (sito) | autore "Maxdigital26" senza nome e cognome reali |

### 🟠 ALTO — GEO-08: Entità del brand non definita in modo coerente

**Estensione:** 2 occorrenze  
**Perché conta:** Perché un modello citi "Max Digital Innovation" come agenzia di Palermo deve trovare la stessa descrizione ripetuta in modo coerente su sito, schema e profili esterni (sameAs).  
**Come si risolve:** Definire una descrizione canonica dell’entità e replicarla identica in Organization, footer, llms.txt, Google Business e profili social. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | nessuno schema Organization che definisca l’entità aziendale |
| (sito) | nessun sameAs verso profili social/directory: l’entità non è collegabile |

### 🟠 ALTO — EAT-01: Nessuna biografia autore collegata ai contenuti

**Estensione:** 1 occorrenze  
**Perché conta:** Per i temi YMYL e per i servizi professionali Google valuta chi scrive: senza author box e schema Person manca il segnale di competenza.  
**Come si risolve:** Author box + schema Person con ruolo, esperienza e profili verificabili generati dal plugin. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | nessuno schema Person / author box rilevato |

### 🟠 ALTO — EAT-02: Nessun caso studio o portfolio verificabile

**Estensione:** 1 occorrenze  
**Perché conta:** L’esperienza dimostrata (la prima E di E-E-A-T) per un’agenzia si prova con lavori reali, risultati misurati e clienti citabili.  
**Come si risolve:** Pagina Portfolio con 5-8 casi studio (problema, intervento, risultato numerico) e schema CreativeWork. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | nessuna pagina portfolio o casi studio pubblicata |

### 🟠 ALTO — TAX-01: Categoria sovraccarica: quasi tutti gli articoli nella stessa

**Estensione:** 1 occorrenze  
**Perché conta:** Una categoria che raccoglie l’80% degli articoli non crea alcun cluster tematico: l’archivio è inutile per l’utente e non aiuta Google a capire i topic del sito.  
**Come si risolve:** Ricategorizzazione automatica proposta dal fixer in base alle keyword del testo. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | categoria "E-commerce": 258 articoli su 311 (83%) |

### 🟠 ALTO — TEC-03: max-image-preview:large assente

**Estensione:** 326 occorrenze su 325 URL  
**Perché conta:** Senza questa direttiva Google mostra miniature piccole e la pagina non è eleggibile per Google Discover.  
**Come si risolve:** Aggiunta automatica nella meta robots dal plugin. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | direttiva assente |
| / | direttiva assente |
| /digital-strategies | direttiva assente |
| /cookie-policy | direttiva assente |
| /servizi-digitali-palermo | direttiva assente |
| /realizzazione-siti-web-a-palermo | direttiva assente |
| /gestione-social-media-palermo | direttiva assente |
| /digital-marketing-palermo | direttiva assente |
| … | altri 318 casi nel file audit.json |

### 🟠 ALTO — SCH-03: BreadcrumbList assente

**Estensione:** 325 occorrenze su 324 URL  
**Perché conta:** I breadcrumb migliorano la comprensione della gerarchia del sito e sostituiscono l’URL nello snippet, aumentando il CTR.  
**Come si risolve:** Breadcrumb + JSON-LD BreadcrumbList generati dal plugin. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | nessun BreadcrumbList |
| / | nessun BreadcrumbList |
| /digital-strategies | nessun BreadcrumbList |
| /cookie-policy | nessun BreadcrumbList |
| /servizi-digitali-palermo | nessun BreadcrumbList |
| /realizzazione-siti-web-a-palermo | nessun BreadcrumbList |
| /digital-marketing-palermo | nessun BreadcrumbList |
| /produzione-video-palermo | nessun BreadcrumbList |
| … | altri 317 casi nel file audit.json |

### 🟠 ALTO — GEO-03: Nessuna risposta diretta in apertura (answer-first)

**Estensione:** 324 occorrenze su 323 URL  
**Perché conta:** I modelli generativi estraggono la risposta dai primi 40-60 parole dopo il titolo. Se l’articolo apre con un’introduzione narrativa, non c’è nulla da citare.  
**Come si risolve:** Blocco "In breve" di 40-60 parole in cima a ogni articolo, generato nel piano di riscrittura. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | nessun blocco di sintesi iniziale citabile |
| / | nessun blocco di sintesi iniziale citabile |
| /digital-strategies | nessun blocco di sintesi iniziale citabile |
| /servizi-digitali-palermo | nessun blocco di sintesi iniziale citabile |
| /realizzazione-siti-web-a-palermo | nessun blocco di sintesi iniziale citabile |
| /gestione-social-media-palermo | nessun blocco di sintesi iniziale citabile |
| /digital-marketing-palermo | nessun blocco di sintesi iniziale citabile |
| /produzione-video-palermo | nessun blocco di sintesi iniziale citabile |
| … | altri 316 casi nel file audit.json |

### 🟠 ALTO — GEO-05: Contenuto senza dati, numeri o fonti citabili

**Estensione:** 299 occorrenze su 299 URL  
**Perché conta:** Gli studi sulla citazione nei motori generativi mostrano che statistiche, citazioni e fonti aumentano molto la probabilità di essere ripresi in risposta.  
**Come si risolve:** Inserire almeno 2 dati verificabili con fonte (Istat, Osservatori PoliMi, Google/Meta) per articolo pilastro. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /servizi-digitali-palermo | nessun dato numerico o percentuale nel testo |
| /realizzazione-siti-web-a-palermo | nessun dato numerico o percentuale nel testo |
| /digital-marketing-palermo | nessun dato numerico o percentuale nel testo |
| /produzione-video-palermo | nessun dato numerico o percentuale nel testo |
| /realizzazione-e-commerce-palermo | nessun dato numerico o percentuale nel testo |
| /graphic-design-palermo | nessun dato numerico o percentuale nel testo |
| /sviluppo-crm-gestionali-palermo | nessun dato numerico o percentuale nel testo |
| /naming-palermo | nessun dato numerico o percentuale nel testo |
| … | altri 291 casi nel file audit.json |

### 🟠 ALTO — LNK-02: Nessun link interno in uscita

**Estensione:** 292 occorrenze su 291 URL  
**Perché conta:** Senza link in uscita l’articolo è un vicolo cieco: non distribuisce autorità e non guida l’utente verso le pagine servizio che convertono.  
**Come si risolve:** Inserimento automatico di 3-5 link contestuali per articolo verso pagine pilastro e correlati. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | 0 link interni in uscita |
| /realizzazione-siti-web-a-palermo | 0 link interni in uscita |
| /digital-marketing-palermo | 0 link interni in uscita |
| /produzione-video-palermo | 0 link interni in uscita |
| /realizzazione-e-commerce-palermo | 0 link interni in uscita |
| /graphic-design-palermo | 0 link interni in uscita |
| /sviluppo-crm-gestionali-palermo | 0 link interni in uscita |
| /naming-palermo | 0 link interni in uscita |
| … | altri 284 casi nel file audit.json |

### 🟠 ALTO — IMG-05: Articoli senza immagine in evidenza

**Estensione:** 284 occorrenze su 283 URL  
**Perché conta:** Senza featured image mancano og:image e twitter:image: le condivisioni social sono senza anteprima e Google Discover esclude la pagina.  
**Come si risolve:** Il plugin imposta un’immagine di fallback brandizzata e genera og:image dinamico. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | nessuna immagine in evidenza |
| / | nessuna immagine in evidenza |
| /digital-strategies | nessuna immagine in evidenza |
| /cookie-policy | nessuna immagine in evidenza |
| /servizi-digitali-palermo | nessuna immagine in evidenza |
| /realizzazione-siti-web-a-palermo | nessuna immagine in evidenza |
| /gestione-social-media-palermo | nessuna immagine in evidenza |
| /digital-marketing-palermo | nessuna immagine in evidenza |
| … | altri 276 casi nel file audit.json |

### 🟠 ALTO — ONP-10: Nessun H2: contenuto senza struttura

**Estensione:** 214 occorrenze su 214 URL  
**Perché conta:** Senza sottotitoli il testo non è scansionabile, non genera featured snippet e i motori generativi non trovano blocchi citabili.  
**Come si risolve:** Il piano di ristrutturazione genera una scaletta H2/H3 per ogni articolo (incluse domande frequenti). _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /mi-serve-davvero-un-sito-web-aziendale | 0 H2 su 332 parole |
| /quali-sono-i-servizi-offerti-da-unagenzia-di-comunicazione-a-palermo | 0 H2 su 443 parole |
| /le-commerce-in-italia-sta-cambiando-con-lintelligenza-artificiale | 0 H2 su 379 parole |
| /il-marketing-per-attivita-locali-i-vantaggi-di-affidarsi-a-una-web-agency | 0 H2 su 487 parole |
| /i-social-media-gli-alleati-per-il-tuo-e-commerce | 0 H2 su 445 parole |
| /marketing-online-per-la-tua-concessionaria | 0 H2 su 509 parole |
| /marketing-online-per-il-tuo-negozio-di-abbigliamento | 0 H2 su 460 parole |
| /web-marketing-per-la-tua-parruccheria | 0 H2 su 473 parole |
| … | altri 206 casi nel file audit.json |

### 🟠 ALTO — SCH-02: FAQPage assente su pagine che contengono domande

**Estensione:** 189 occorrenze su 189 URL  
**Perché conta:** Le pagine con domande nei titoli sono candidate naturali a FAQ rich result e a essere citate come risposta diretta dagli assistenti AI.  
**Come si risolve:** Il plugin genera FAQPage automaticamente dalle coppie domanda/risposta trovate negli H2-H3. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| / | 11 domande nei titoli, nessun FAQPage |
| /digital-strategies | 6 domande nei titoli, nessun FAQPage |
| /gestione-social-media-palermo | 1 domande nei titoli, nessun FAQPage |
| /digital-marketing-palermo | 1 domande nei titoli, nessun FAQPage |
| /produzione-video-palermo | 8 domande nei titoli, nessun FAQPage |
| /realizzazione-e-commerce-palermo | 1 domande nei titoli, nessun FAQPage |
| /graphic-design-palermo | 1 domande nei titoli, nessun FAQPage |
| /sviluppo-crm-gestionali-palermo | 1 domande nei titoli, nessun FAQPage |
| … | altri 181 casi nel file audit.json |

### 🟠 ALTO — CNT-02: Contenuto sotto la soglia competitiva (< 600 parole)

**Estensione:** 161 occorrenze su 161 URL  
**Perché conta:** Per query commerciali le pagine in prima pagina superano quasi sempre le 900 parole: con meno testo mancano le entità e le domande che i motori cercano.  
**Come si risolve:** Espansione guidata: sezioni H2 aggiuntive, FAQ, caso studio, dati locali. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /innovazione-digitale-pmi-competitive | 348 parole |
| /innovazione-digitale-palermo | 424 parole |
| /cybersecurity-per-aziende | 393 parole |
| /strategia-marketing-digitale | 328 parole |
| /campagne-ai-driven-marketing-automation | 306 parole |
| /gestione-social-media-presenza-online | 338 parole |
| /design-responsivo-sito-web-successo-online | 497 parole |
| /perche-un-sito-web-professionale-e-la-chiave-del-successo-per-ogni-impresa | 479 parole |
| … | altri 153 casi nel file audit.json |

### 🟠 ALTO — ONP-05: Focus keyword assente dal title SEO

**Estensione:** 111 occorrenze su 111 URL  
**Perché conta:** La keyword nel title è ancora uno dei segnali on-page più forti per il ranking e per la rilevanza percepita.  
**Come si risolve:** Il title rigenerato inserisce la focus keyword nei primi 30 caratteri. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | keyword "Privacy Policy Max Digital Innovation" non presente |
| /come-creare-una-strategia-di-comunicazione-digitale-efficace-per-piccole-imprese | keyword "strategia comunicazione digitale piccole imprese" non presente |
| /cookie-cosa-sono-e-a-che-servono | keyword "cookie cosa sono" non presente |
| /e-commerce-a-palermo | keyword "e-commerce Palermo" non presente |
| /advertising-a-palermo | keyword "Advertising Palermo" non presente |
| /5-consigli-preziosi-per-pubblicizzare-la-propria-azienda-a-palermo-su-google | keyword "pubblicizzare azienda Google Palermo" non presente |
| /ecco-perche-un-sito-web-a-palermo-e-cosi-essenziale-per-la-tua-attivita | keyword "sito web essenziale attività" non presente |
| /gestione-social-media-a-palermo-come-la-nostra-agenzia-di-comunicazione-cura-i-tuoi-social-media | keyword "gestione social media Palermo" non presente |
| … | altri 103 casi nel file audit.json |

### 🟠 ALTO — GEO-04: Nessuna sezione FAQ esplicita

**Estensione:** 92 occorrenze su 92 URL  
**Perché conta:** Le coppie domanda/risposta brevi sono il formato più citato dai motori generativi e alimentano i rich result FAQ.  
**Come si risolve:** Blocco FAQ (3-5 domande reali dalla ricerca) + schema FAQPage generati per ogni pagina servizio e articolo pilastro. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | nessuna domanda fra i titoli di sezione |
| /servizi-digitali-palermo | nessuna domanda fra i titoli di sezione |
| /innovazione-digitale-pmi-competitive | nessuna domanda fra i titoli di sezione |
| /campagne-ai-driven-marketing-automation | nessuna domanda fra i titoli di sezione |
| /come-creare-una-strategia-di-comunicazione-digitale-efficace-per-piccole-imprese | nessuna domanda fra i titoli di sezione |
| /tipologie-di-siti-web-la-guida-definitiva-per-imprenditori | nessuna domanda fra i titoli di sezione |
| /advertising-a-palermo | nessuna domanda fra i titoli di sezione |
| /hai-bisogno-di-pubblicizzare-il-tuo-ristorante-a-palermo-ecco-come-possiamo-aiutarti | nessuna domanda fra i titoli di sezione |
| … | altri 84 casi nel file audit.json |

### 🟠 ALTO — ONP-01: Title SEO troppo lungo (viene troncato in SERP)

**Estensione:** 89 occorrenze su 89 URL  
**Perché conta:** Oltre ~60 caratteri Google tronca il title con "…": il messaggio e la keyword finale si perdono e il CTR cala.  
**Come si risolve:** Riscrittura automatica entro 60 caratteri mantenendo focus keyword a inizio titolo + brand. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /cookie-policy | 62 caratteri |
| /innovazione-digitale-pmi-competitive | 64 caratteri |
| /strategia-marketing-digitale | 65 caratteri |
| /campagne-ai-driven-marketing-automation | 61 caratteri |
| /gestione-social-media-presenza-online | 65 caratteri |
| /come-creare-una-strategia-di-comunicazione-digitale-efficace-per-piccole-imprese | 64 caratteri |
| /reputazione_online_cose_perche_conta_e_come_verificarla | 64 caratteri |
| /branded-content-come-raccontare-il-tuo-brand-con-max-digital-innovation | 65 caratteri |
| … | altri 81 casi nel file audit.json |

### 🟠 ALTO — ONP-03: Meta description fuori lunghezza ottimale

**Estensione:** 88 occorrenze su 88 URL  
**Perché conta:** Sopra ~158 caratteri viene troncata, sotto 120 non sfrutta lo snippet: in entrambi i casi si perde CTR.  
**Come si risolve:** Riscrittura automatica a 140-158 caratteri con keyword, beneficio e call to action. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /come-creare-una-strategia-di-comunicazione-digitale-efficace-per-piccole-imprese | 162 caratteri |
| /reputazione_online_cose_perche_conta_e_come_verificarla | 161 caratteri |
| /come-creare-una-landing-page-efficace | 163 caratteri |
| /cookie-cosa-sono-e-a-che-servono | 162 caratteri |
| /scopri-come-aumentare-posizionamento-seo-per-il-tuo-sito-web-su-google | 99 caratteri |
| /tipologie-di-siti-web-la-guida-definitiva-per-imprenditori | 88 caratteri |
| /advertising-a-palermo | 119 caratteri |
| /5-consigli-preziosi-per-pubblicizzare-la-propria-azienda-a-palermo-su-google | 162 caratteri |
| … | altri 80 casi nel file audit.json |

### 🟠 ALTO — TEC-02: Direttiva robots non impostata esplicitamente

**Estensione:** 48 occorrenze su 48 URL  
**Perché conta:** Senza direttiva esplicita il comportamento dipende dalle impostazioni globali del plugin: pagine di servizio possono finire indicizzate e diluire la qualità del sito.  
**Come si risolve:** Il plugin imposta index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1 sui contenuti utili e noindex su pagine di servizio. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | nessun meta robots salvato |
| / | nessun meta robots salvato |
| /digital-strategies | nessun meta robots salvato |
| /cookie-policy | nessun meta robots salvato |
| /servizi-digitali-palermo | nessun meta robots salvato |
| /realizzazione-siti-web-a-palermo | nessun meta robots salvato |
| /gestione-social-media-palermo | nessun meta robots salvato |
| /digital-marketing-palermo | nessun meta robots salvato |
| … | altri 40 casi nel file audit.json |

### 🟠 ALTO — IMG-03: Immagini pesanti (> 200 KB) che rallentano il caricamento

**Estensione:** 39 occorrenze su 38 URL  
**Perché conta:** Il peso delle immagini è la causa principale di LCP lento; i Core Web Vitals influenzano ranking e conversioni.  
**Come si risolve:** Conversione in WebP/AVIF e compressione; il plugin abilita lazy loading e dimensioni esplicite. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| maxdigitalinnovation.it_-1.gif | 51945 KB |
| 126882.jpg | 855 KB |
| studio.webp | 336 KB |
| Foto-6-2.png | 313 KB |
| Foto-7-2.png | 260 KB |
| Foto-8-2.png | 349 KB |
| Foto-9-2.png | 241 KB |
| Foto-10-1.png | 337 KB |
| … | altri 31 casi nel file audit.json |

### 🟠 ALTO — ONP-06: Focus keyword duplicata su più pagine (cannibalizzazione)

**Estensione:** 23 occorrenze su 23 URL  
**Perché conta:** Più URL che competono per la stessa query si tolgono forza a vicenda: Google non capisce quale posizionare.  
**Come si risolve:** Consolidare in una pagina pilastro e differenziare le altre su varianti long-tail; redirect 301 dove il contenuto è ridondante. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /gestione-social-media-palermo | keyword "gestione social media" condivisa con altre 1 pagine |
| /gestione-social-media-presenza-online | keyword "gestione social media" condivisa con altre 1 pagine |
| /produzione-video-palermo | keyword "produzione video palermo" condivisa con altre 2 pagine |
| /produzione-video-a-palermo-come-scegliere-lagenzia-giusta | keyword "produzione video palermo" condivisa con altre 2 pagine |
| /produzione-video-palermo-perche-oggi-e-indispensabile-per-ogni-azienda | keyword "produzione video palermo" condivisa con altre 2 pagine |
| /realizzazione-e-commerce-palermo | keyword "realizzazione e-commerce palermo" condivisa con altre 1 pagine |
| /hai-bisogno-di-uno-store-online-per-la-tua-attivita-offriamo-realizzazione-e-commerce-a-palermo | keyword "realizzazione e-commerce palermo" condivisa con altre 1 pagine |
| /gestione-social-media-a-palermo-come-la-nostra-agenzia-di-comunicazione-cura-i-tuoi-social-media | keyword "gestione social media palermo" condivisa con altre 1 pagine |
| … | altri 15 casi nel file audit.json |

### 🟠 ALTO — LOC-05: Cannibalizzazione fra le landing locali

**Estensione:** 22 occorrenze su 22 URL  
**Perché conta:** Più pagine ottimizzate su varianti quasi identiche della stessa query locale ("web agency a palermo", "agenzia di marketing a palermo") si contendono lo stesso posizionamento.  
**Come si risolve:** Una pagina pilastro "web agency Palermo" + pagine servizio differenziate per intento, collegate da link interni. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /produzione-video-palermo | keyword locale "produzione video palermo" condivisa con altre 2 pagine |
| /produzione-video-a-palermo-come-scegliere-lagenzia-giusta | keyword locale "produzione video palermo" condivisa con altre 2 pagine |
| /produzione-video-palermo-perche-oggi-e-indispensabile-per-ogni-azienda | keyword locale "produzione video palermo" condivisa con altre 2 pagine |
| /realizzazione-e-commerce-palermo | keyword locale "realizzazione e-commerce palermo" condivisa con altre 1 pagine |
| /hai-bisogno-di-uno-store-online-per-la-tua-attivita-offriamo-realizzazione-e-commerce-a-palermo | keyword locale "realizzazione e-commerce palermo" condivisa con altre 1 pagine |
| /gestione-social-media-a-palermo-come-la-nostra-agenzia-di-comunicazione-cura-i-tuoi-social-media | keyword locale "gestione social media palermo" condivisa con altre 1 pagine |
| /gestione-social-media-per-la-tua-attivita | keyword locale "gestione social media palermo" condivisa con altre 1 pagine |
| /web-agency-a-palermo-il-design-che-parte-dal-brand | keyword locale "web agency palermo" condivisa con altre 2 pagine |
| … | altri 14 casi nel file audit.json |

### 🟠 ALTO — SCH-04: Nessuno schema Service sulle pagine servizio

**Estensione:** 10 occorrenze su 10 URL  
**Perché conta:** Le pagine servizio senza schema Service/Offer non comunicano cosa vendi, dove e a chi: informazione chiave sia per il local pack sia per le risposte AI.  
**Come si risolve:** Schema Service con areaServed, provider e offerta generato per ogni pagina servizio. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /servizi-digitali-palermo | schema Service assente |
| /realizzazione-siti-web-a-palermo | schema Service assente |
| /gestione-social-media-palermo | schema Service assente |
| /digital-marketing-palermo | schema Service assente |
| /produzione-video-palermo | schema Service assente |
| /realizzazione-e-commerce-palermo | schema Service assente |
| /graphic-design-palermo | schema Service assente |
| /sviluppo-crm-gestionali-palermo | schema Service assente |
| … | altri 2 casi nel file audit.json |

### 🟠 ALTO — CNT-04: Contenuti non aggiornati da oltre 12 mesi

**Estensione:** 7 occorrenze su 7 URL  
**Perché conta:** La freschezza è un fattore di ranking per query in evoluzione (marketing, social, AI) e i motori generativi preferiscono citare fonti recenti.  
**Come si risolve:** Piano di refresh: aggiornare dati, anno nel titolo, esempi e data di modifica. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /digital-agency-a-palermo-la-soluzione-completa-per-la-tua-presenza-online | ultimo aggiornamento 2024-11-11 (668 giorni fa) |
| /come-migliorare-la-tua-brand-reputation-con-le-attivita-di-digital-marketing | ultimo aggiornamento 2024-11-11 (668 giorni fa) |
| /come-realizzare-un-sito-web-efficace-le-migliori-alternative-per-creare-un-percorso-di-successo-online | ultimo aggiornamento 2024-11-12 (667 giorni fa) |
| /integrazione-di-piattaforme-tiktok-e-commerce-un-nuovo-orizzonte-per-i-commercianti-a-palermo | ultimo aggiornamento 2024-12-06 (643 giorni fa) |
| /quando-la-web-agency-incontra-lintelligenza-artificiale | ultimo aggiornamento 2024-12-11 (638 giorni fa) |
| /i-siti-web-del-futuro-come-saranno-nel-2050 | ultimo aggiornamento 2024-12-11 (638 giorni fa) |
| /servizi-alla-persona-promuovere-online-estetiste-massaggiatori-e-parrucchieri | ultimo aggiornamento 2025-04-02 (526 giorni fa) |

### 🟠 ALTO — ONP-09: H1 multipli nella stessa pagina

**Estensione:** 5 occorrenze su 5 URL  
**Perché conta:** Più H1 confondono la gerarchia semantica e diluiscono il topic principale.  
**Come si risolve:** Lasciare un solo H1 e declassare gli altri a H2. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /scopri-come-creare-video-virali | 4 tag H1 |
| /contenuti-che-spopolano-sui-social | 4 tag H1 |
| /trend-alert-i-video-che-stanno-conquistando-tiktok | 10 tag H1 |
| /come-ottimizzare-i-tuoi-video-per-i-motori-di-ricerca-nel-2025 | 4 tag H1 |
| /seo-per-contenuti-video-guida-pratica-per-aumentare-le-visualizzazioni | 3 tag H1 |

### 🟠 ALTO — IMG-01: Immagini senza attributo alt

**Estensione:** 1 occorrenze  
**Perché conta:** L’alt è il testo che Google usa per capire l’immagine: senza, si perde traffico da Google Immagini e la pagina non è accessibile.  
**Come si risolve:** Generazione automatica dell’alt da titolo pagina + focus keyword, applicata dal plugin a runtime. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| / | 27 immagini su 43 senza alt |

### 🟡 MEDIO — TEC-07: Pagine chiave assenti (Chi siamo, Contatti)

**Estensione:** 2 occorrenze  
**Perché conta:** Sono le pagine che Google usa per valutare l’affidabilità (E-E-A-T) e le uniche a intercettare le ricerche di brand navigazionali. Qui esistono solo come ancore della home o nel cestino.  
**Come si risolve:** Il fixer genera i template HTML pronti per /chi-siamo/ e /contatti/ con dati aziendali e schema. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | pagina "Chi siamo" (/chi-siamo/) non presente fra le pagine pubblicate |
| (sito) | pagina "Contatti" (/contatti/) non presente fra le pagine pubblicate |

### 🟡 MEDIO — CNT-05: Buco di pubblicazione: frequenza non costante

**Estensione:** 1 occorrenze  
**Perché conta:** Lunghi periodi senza pubblicazioni riducono la frequenza di scansione di Googlebot e la percezione di sito attivo.  
**Come si risolve:** Calendario editoriale generato con cadenza settimanale e cluster tematici. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | nessuna pubblicazione tra 2025-07 e 2026-07 (11 mesi di silenzio) |

### 🟡 MEDIO — LOC-06: Nessuna copertura dei comuni della provincia

**Estensione:** 1 occorrenze  
**Perché conta:** Le ricerche locali si frammentano per comune (Monreale, Bagheria, Carini, Cefalù...): senza pagine o sezioni dedicate si perde tutta la coda lunga geografica.  
**Come si risolve:** Creare pagine "servizio + comune" con contenuto realmente differenziato (casi, riferimenti locali), mai duplicate. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | solo 0 comuni della provincia citati (nessuno) |

### 🟡 MEDIO — GEO-11: Titoli non allineati al linguaggio delle query conversazionali

**Estensione:** 1 occorrenze  
**Perché conta:** Le ricerche nei motori generativi sono frasi lunghe e in forma di domanda: i titoli devono rispecchiare quel linguaggio per intercettarle.  
**Come si risolve:** Riformulare i titoli di sezione come domande reali ("Quanto costa un sito web a Palermo?"). _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | solo 54 contenuti su 326 (17%) hanno un titolo in forma di domanda |

### 🟡 MEDIO — EAT-03: Contenuti con tratti di generazione automatica non revisionata

**Estensione:** 1 occorrenze  
**Perché conta:** Testi molto uniformi per lunghezza e struttura, senza dati, esempi o firme, rientrano nei contenuti "scalati" che le linee guida antispam di Google penalizzano.  
**Come si risolve:** Revisione umana: aggiungere esperienza diretta, dati propri, esempi di clienti reali, foto originali. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | 241 articoli su 311 (77%) senza immagini, link interni, tabelle né dati: profilo tipico di contenuto prodotto in serie |

### 🟡 MEDIO — EAT-04: Commenti chiusi ovunque: nessun segnale di interazione

**Estensione:** 1 occorrenze  
**Perché conta:** Interazione e contenuti generati dagli utenti sono segnali di sito vivo; non sono decisivi ma contribuiscono alla percezione di attività.  
**Come si risolve:** Valutare l’apertura dei commenti moderati sugli articoli guida. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | commenti chiusi su tutti i 311 articoli |

### 🟡 MEDIO — TAX-02: Nessun tag utilizzato

**Estensione:** 1 occorrenze  
**Perché conta:** Senza tag mancano le pagine di archivio tematiche che possono intercettare query di coda lunga e collegare articoli correlati.  
**Come si risolve:** Set di tag proposto dal fixer a partire dalle entità ricorrenti nei testi (max 5 per articolo). _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | 0 tag definiti sul sito |

### 🟡 MEDIO — TEC-04: Canonical non dichiarato esplicitamente

**Estensione:** 326 occorrenze su 325 URL  
**Perché conta:** Il canonical esplicito protegge da duplicazioni generate da parametri UTM, paginazione e varianti con/senza slash.  
**Come si risolve:** Il plugin stampa un canonical assoluto e autoreferenziale su ogni URL. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | canonical non impostato |
| / | canonical non impostato |
| /digital-strategies | canonical non impostato |
| /cookie-policy | canonical non impostato |
| /servizi-digitali-palermo | canonical non impostato |
| /realizzazione-siti-web-a-palermo | canonical non impostato |
| /gestione-social-media-palermo | canonical non impostato |
| /digital-marketing-palermo | canonical non impostato |
| … | altri 318 casi nel file audit.json |

### 🟡 MEDIO — ONP-12: Riassunto (excerpt) mancante

**Estensione:** 326 occorrenze su 325 URL  
**Perché conta:** L’excerpt alimenta archivi, feed RSS, anteprime social e fallback della meta description.  
**Come si risolve:** Generazione automatica di un estratto di 25-35 parole con la focus keyword. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | assente |
| / | assente |
| /digital-strategies | assente |
| /cookie-policy | assente |
| /servizi-digitali-palermo | assente |
| /realizzazione-siti-web-a-palermo | assente |
| /gestione-social-media-palermo | assente |
| /digital-marketing-palermo | assente |
| … | altri 318 casi nel file audit.json |

### 🟡 MEDIO — GEO-07: Nessun markup speakable per risposte vocali

**Estensione:** 326 occorrenze su 325 URL  
**Perché conta:** Lo schema speakable indica quali frammenti sono adatti alla lettura vocale e alla sintesi: aiuta assistenti vocali e riassunti AI.  
**Come si risolve:** speakable aggiunto allo schema Article dal plugin. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | speakable assente |
| / | speakable assente |
| /digital-strategies | speakable assente |
| /cookie-policy | speakable assente |
| /servizi-digitali-palermo | speakable assente |
| /realizzazione-siti-web-a-palermo | speakable assente |
| /gestione-social-media-palermo | speakable assente |
| /digital-marketing-palermo | speakable assente |
| … | altri 318 casi nel file audit.json |

### 🟡 MEDIO — ONP-08: H1 non rilevabile nel contenuto

**Estensione:** 289 occorrenze su 288 URL  
**Perché conta:** Se il tema non stampa un H1 unico, il documento perde il principale segnale di argomento. Va verificato sul front-end.  
**Come si risolve:** Verificare il template; nelle pagine Elementor impostare il titolo principale come H1 (uno solo per pagina). _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | nessun tag H1 nel contenuto salvato |
| /digital-strategies | nessun tag H1 nel contenuto salvato |
| /servizi-digitali-palermo | nessun tag H1 nel contenuto salvato |
| /innovazione-digitale-pmi-competitive | nessun tag H1 nel contenuto salvato |
| /innovazione-digitale-palermo | nessun tag H1 nel contenuto salvato |
| /cybersecurity-per-aziende | nessun tag H1 nel contenuto salvato |
| /strategia-marketing-digitale | nessun tag H1 nel contenuto salvato |
| /campagne-ai-driven-marketing-automation | nessun tag H1 nel contenuto salvato |
| … | altri 281 casi nel file audit.json |

### 🟡 MEDIO — GEO-10: Nessun blocco di dati sintetici (tabella, elenco definitorio)

**Estensione:** 245 occorrenze su 245 URL  
**Perché conta:** Tabelle e liste con coppie chiave/valore sono la struttura che i modelli estraggono con più affidabilità (prezzi, tempi, requisiti, confronti).  
**Come si risolve:** Aggiungere per ogni servizio una tabella "cosa include / tempi / a chi serve". _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | nessuna tabella di dati strutturati |
| / | nessuna tabella di dati strutturati |
| /digital-strategies | nessuna tabella di dati strutturati |
| /servizi-digitali-palermo | nessuna tabella di dati strutturati |
| /realizzazione-siti-web-a-palermo | nessuna tabella di dati strutturati |
| /gestione-social-media-palermo | nessuna tabella di dati strutturati |
| /digital-marketing-palermo | nessuna tabella di dati strutturati |
| /produzione-video-palermo | nessuna tabella di dati strutturati |
| … | altri 237 casi nel file audit.json |

### 🟡 MEDIO — TAX-03: Categorie fuori tema rispetto al contenuto

**Estensione:** 237 occorrenze su 237 URL  
**Perché conta:** Articoli su social media o video classificati come "E-commerce" mandano segnali contraddittori sull’argomento della pagina e degli archivi.  
**Come si risolve:** Il fixer propone per ogni articolo la categoria coerente con le keyword dominanti. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /campagne-ai-driven-marketing-automation | categoria "Web development" non coerente, suggerita "Marketing" |
| /come-creare-una-strategia-di-comunicazione-digitale-efficace-per-piccole-imprese | categoria "E-commerce" non coerente, suggerita "Marketing" |
| /branded-content-come-raccontare-il-tuo-brand-con-max-digital-innovation | categoria "E-commerce" non coerente, suggerita "Marketing" |
| /piano-di-marketing-cose-e-come-crearlo-con-successo | categoria "E-commerce" non coerente, suggerita "Marketing" |
| /cookie-cosa-sono-e-a-che-servono | categoria "E-commerce" non coerente, suggerita "Web development" |
| /privacy-policy | categoria "E-commerce" non coerente, suggerita "Web development" |
| /scopri-come-aumentare-posizionamento-seo-per-il-tuo-sito-web-su-google | categoria "Marketing" non coerente, suggerita "Web development" |
| /tipologie-di-siti-web-la-guida-definitiva-per-imprenditori | categoria "Software" non coerente, suggerita "E-commerce" |
| … | altri 229 casi nel file audit.json |

### 🟡 MEDIO — CNT-06: Leggibilità bassa (indice Gulpease < 50)

**Estensione:** 201 occorrenze su 200 URL  
**Perché conta:** Testi difficili aumentano la frequenza di rimbalzo e riducono il tempo di permanenza, due segnali comportamentali correlati al posizionamento.  
**Come si risolve:** Frasi sotto le 25 parole, paragrafi da 2-3 frasi, elenchi puntati. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | Gulpease 39/100 |
| / | Gulpease 49/100 |
| /servizi-digitali-palermo | Gulpease 41/100 |
| /realizzazione-siti-web-a-palermo | Gulpease 44/100 |
| /gestione-social-media-palermo | Gulpease 42/100 |
| /digital-marketing-palermo | Gulpease 45/100 |
| /produzione-video-palermo | Gulpease 43/100 |
| /realizzazione-e-commerce-palermo | Gulpease 42/100 |
| … | altri 193 casi nel file audit.json |

### 🟡 MEDIO — ONP-07: Slug URL troppo lungo

**Estensione:** 193 occorrenze su 193 URL  
**Perché conta:** URL lunghi sono meno cliccabili, si troncano in SERP e diluiscono il peso delle keyword.  
**Come si risolve:** Slug accorciato a max 60 caratteri senza stopword + redirect 301 generato automaticamente. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /perche-un-sito-web-professionale-e-la-chiave-del-successo-per-ogni-impresa | 74 caratteri |
| /come-creare-una-strategia-di-comunicazione-digitale-efficace-per-piccole-imprese | 80 caratteri |
| /branded-content-come-raccontare-il-tuo-brand-con-max-digital-innovation | 71 caratteri |
| /scopri-come-aumentare-posizionamento-seo-per-il-tuo-sito-web-su-google | 70 caratteri |
| /tiktok-ads-manager-guida-completa-su-come-gestire-le-tue-campagne-su-tiktok | 75 caratteri |
| /dem-marketing-come-usarlo-nel-2024-per-massimizzare-i-risultati | 63 caratteri |
| /5-consigli-preziosi-per-pubblicizzare-la-propria-azienda-a-palermo-su-google | 76 caratteri |
| /realizzazione-siti-web-per-bb-a-palermo-hotel-motel-e-strutture-ricettive-tutto-quello-che-ce-da-sapere | 103 caratteri |
| … | altri 185 casi nel file audit.json |

### 🟡 MEDIO — CNT-08: Nessun elemento visuale o strutturato (liste, tabelle, immagini)

**Estensione:** 100 occorrenze su 100 URL  
**Perché conta:** Blocchi di testo continuo non producono featured snippet e sono difficili da estrarre per le risposte generative.  
**Come si risolve:** Aggiungere almeno un elenco puntato, una tabella comparativa o un’immagine con didascalia per articolo. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /realizzazione-siti-web-a-palermo | nessuna lista, tabella o immagine |
| /digital-marketing-palermo | nessuna lista, tabella o immagine |
| /produzione-video-palermo | nessuna lista, tabella o immagine |
| /realizzazione-e-commerce-palermo | nessuna lista, tabella o immagine |
| /graphic-design-palermo | nessuna lista, tabella o immagine |
| /sviluppo-crm-gestionali-palermo | nessuna lista, tabella o immagine |
| /naming-palermo | nessuna lista, tabella o immagine |
| /stampe-digitali-palermo | nessuna lista, tabella o immagine |
| … | altri 92 casi nel file audit.json |

### 🟡 MEDIO — IMG-02: Allegati in libreria media senza testo alternativo

**Estensione:** 64 occorrenze su 63 URL  
**Perché conta:** L’alt impostato in libreria viene ereditato ovunque l’immagine sia inserita: compilarlo una volta risolve decine di pagine.  
**Come si risolve:** File CSV con alt suggerito per ogni allegato + importer del plugin. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| Logo_Bianco_01.svg | alt assente |
| menu-b.svg | alt assente |
| close-b.svg | alt assente |
| close.svg | alt assente |
| menu.svg | alt assente |
| nupi.webp | alt assente |
| riccio.webp | alt assente |
| stoliva.webp | alt assente |
| … | altri 56 casi nel file audit.json |

### 🟡 MEDIO — IMG-04: Formati immagine non moderni (JPG/PNG invece di WebP)

**Estensione:** 63 occorrenze su 62 URL  
**Perché conta:** WebP pesa il 25-35% in meno a parità di qualità: impatto diretto su LCP e crawl budget.  
**Come si risolve:** Conversione batch in WebP mantenendo i vecchi file come fallback. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| bg-e1786541817316.png | formato png |
| Max-Doigital-Logo.png | formato png |
| cropped-Max-Doigital-Logo.png | formato png |
| testlogomax.png | formato png |
| 126882.jpg | formato jpg |
| Harmont__and__Blaine-logo.png | formato png |
| logo.png | formato png |
| logo_2_small.png | formato png |
| … | altri 55 casi nel file audit.json |

### 🟡 MEDIO — LOC-07: Landing locali senza segnali geografici nel testo

**Estensione:** 7 occorrenze su 7 URL  
**Perché conta:** Una pagina che punta a una keyword locale deve contenere riferimenti reali al territorio: quartieri, comuni, indirizzo, area servita.  
**Come si risolve:** Blocco "Dove operiamo" con area servita e riferimenti locali aggiunto dal fixer. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /realizzazione-e-commerce-palermo | keyword locale ma solo 2 menzioni della città nel testo |
| /web-agency-a-palermo-per-il-settore-immobiliare-la-chiave-per-il-successo-online | keyword locale ma solo 2 menzioni della città nel testo |
| /come-lanciare-prodotti-tipici-siciliani-online-vendita | keyword locale ma solo 2 menzioni della città nel testo |
| /creazione-video-virali-sicilia | keyword locale ma solo 0 menzioni della città nel testo |
| /produzione-video-virali-sicilia | keyword locale ma solo 0 menzioni della città nel testo |
| /realizzazione-video-sicilia | keyword locale ma solo 0 menzioni della città nel testo |
| /realizzazione-video-virali-trapani | keyword locale ma solo 0 menzioni della città nel testo |

### 🟡 MEDIO — GEO-09: Contenuti senza aggiornamento datato visibile

**Estensione:** 7 occorrenze su 7 URL  
**Perché conta:** I motori generativi privilegiano fonti recenti e con data esplicita: senza "Aggiornato al" il contenuto viene considerato potenzialmente obsoleto.  
**Come si risolve:** Mostrare data di pubblicazione e ultimo aggiornamento, allineate a dateModified nello schema. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /digital-agency-a-palermo-la-soluzione-completa-per-la-tua-presenza-online | mai aggiornato dalla pubblicazione (2024-11-11) |
| /come-migliorare-la-tua-brand-reputation-con-le-attivita-di-digital-marketing | mai aggiornato dalla pubblicazione (2024-11-11) |
| /come-realizzare-un-sito-web-efficace-le-migliori-alternative-per-creare-un-percorso-di-successo-online | mai aggiornato dalla pubblicazione (2024-11-12) |
| /integrazione-di-piattaforme-tiktok-e-commerce-un-nuovo-orizzonte-per-i-commercianti-a-palermo | mai aggiornato dalla pubblicazione (2024-12-06) |
| /quando-la-web-agency-incontra-lintelligenza-artificiale | mai aggiornato dalla pubblicazione (2024-12-11) |
| /i-siti-web-del-futuro-come-saranno-nel-2050 | mai aggiornato dalla pubblicazione (2024-12-11) |
| /servizi-alla-persona-promuovere-online-estetiste-massaggiatori-e-parrucchieri | mai aggiornato dalla pubblicazione (2025-04-02) |

### 🟡 MEDIO — TEC-05: Pagine di servizio indicizzabili (privacy, cookie, grazie)

**Estensione:** 6 occorrenze su 5 URL  
**Perché conta:** Pagine legali o di ringraziamento indicizzate abbassano la qualità media del sito e sprecano crawl budget.  
**Come si risolve:** Impostare noindex,follow su queste pagine (incluso nel plugin). _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | indicizzabile, dovrebbe essere noindex |
| /cookie-policy | indicizzabile, dovrebbe essere noindex |
| /cookie-cosa-sono-e-a-che-servono | indicizzabile, dovrebbe essere noindex |
| /privacy-policy | indicizzabile, dovrebbe essere noindex |
| /ottimizzare-la-comunicazione-grazie-allutilizzo-di-call-to-action-efficaci-per-il-tuo-sito-web-a-palermo | indicizzabile, dovrebbe essere noindex |
| /privacy-policy-sito-web-cose-e-come-scriverla-correttamente | indicizzabile, dovrebbe essere noindex |

### 🟡 MEDIO — TEC-06: Contenuti nel cestino mai eliminati definitivamente

**Estensione:** 4 occorrenze su 4 URL  
**Perché conta:** Le pagine in cestino restano nel database, gonfiano le query e in caso di ripristino accidentale creano URL duplicati.  
**Come si risolve:** Svuotare il cestino dopo aver verificato che non servano redirect 301 dai vecchi URL. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /?page_id=2 | in cestino dal 2026-08-12 |
| /?page_id=7 | in cestino dal 2026-08-12 |
| /?page_id=50 | in cestino dal 2026-08-13 |
| /?page_id=52 | in cestino dal 2026-08-13 |

### 🟡 MEDIO — ONP-02: Title SEO troppo corto (spazio SERP sprecato)

**Estensione:** 4 occorrenze su 4 URL  
**Perché conta:** Sotto i 30 caratteri si perde spazio utile per keyword secondarie e qualificatori geografici.  
**Come si risolve:** Estensione automatica con keyword + località + brand. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /digital-agency-a-palermo-la-soluzione-completa-per-la-tua-presenza-online | 24 caratteri |
| /come-realizzare-un-sito-web-efficace-le-migliori-alternative-per-creare-un-percorso-di-successo-online | 28 caratteri |
| /integrazione-di-piattaforme-tiktok-e-commerce-un-nuovo-orizzonte-per-i-commercianti-a-palermo | 27 caratteri |
| /i-siti-web-del-futuro-come-saranno-nel-2050 | 19 caratteri |

### ⚪ BASSO — TEC-08: Percentuale alta di pagine costruite con page builder

**Estensione:** 1 occorrenze  
**Perché conta:** Elementor genera markup annidato e CSS pesante: impatta LCP e INP, due Core Web Vitals usati come segnali di ranking.  
**Come si risolve:** Attivare CSS/JS ottimizzati di Elementor, disattivare i widget inutilizzati, usare cache e critical CSS. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| (sito) | 269 contenuti su 326 (83%) generati con Elementor |

### ⚪ BASSO — CNT-07: CSS inline dentro il contenuto dell’articolo

**Estensione:** 14 occorrenze su 14 URL  
**Perché conta:** Il CSS finito nel campo contenuto viene indicizzato come testo, abbassa il rapporto testo/codice e può comparire negli snippet.  
**Come si risolve:** Spostamento in file di stile o in Elementor Custom CSS (il fixer estrae i blocchi trovati). _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| /privacy-policy | blocco <style> nel contenuto |
| / | blocco <style> nel contenuto |
| /cookie-policy | blocco <style> nel contenuto |
| /servizi-digitali-palermo | blocco <style> nel contenuto |
| /realizzazione-siti-web-a-palermo | blocco <style> nel contenuto |
| /gestione-social-media-palermo | blocco <style> nel contenuto |
| /digital-marketing-palermo | blocco <style> nel contenuto |
| /produzione-video-palermo | blocco <style> nel contenuto |
| … | altri 6 casi nel file audit.json |

### ⚪ BASSO — ONP-11: Gerarchia dei titoli saltata (es. H2 -> H4)

**Estensione:** 6 occorrenze su 6 URL  
**Perché conta:** Salti di livello rompono l’outline semantico usato da crawler e screen reader.  
**Come si risolve:** Riordinare i livelli in sequenza. _(intervento manuale)_

| URL / elemento | Dettaglio |
|---|---|
| /come-creare-una-landing-page-efficace | 1 salti di livello |
| /cookie-cosa-sono-e-a-che-servono | 2 salti di livello |
| /web-agency-per-e-commerce-a-palermo-come-incrementare-le-vendite-online | 1 salti di livello |
| /web-agency-a-palermo-per-eventi-massimizza-la-partecipazione-al-tuo-evento | 1 salti di livello |
| /web-agency-locale-a-palermo-la-tua-guida-per-conquistare-il-pubblico-della-citta | 1 salti di livello |
| /pubblicita-digitale-a-palermo-come-aumentare-la-visibilita-della-tua-attivita | 1 salti di livello |

### ⚪ BASSO — IMG-06: Immagini senza width/height espliciti

**Estensione:** 2 occorrenze su 2 URL  
**Perché conta:** Senza dimensioni il browser non riserva lo spazio e si genera Cumulative Layout Shift (CLS), penalizzato dai Core Web Vitals.  
**Come si risolve:** Il plugin aggiunge width/height e loading="lazy" alle immagini del contenuto. _(automatizzato dal toolkit)_

| URL / elemento | Dettaglio |
|---|---|
| / | 42 immagini senza dimensioni |
| /gestione-social-media-palermo | 18 immagini senza dimensioni |
