'use strict';
const { SEV, docIssue, siteIssue, mentionsCity } = require('./_helpers');

/** GEO nel senso di SEO geografico / locale: il sito lavora su Palermo e provincia. */
module.exports = [
  {
    id: 'LOC-01', category: 'local', severity: SEV.critical, auto: true,
    title: 'NAP incompleto: numero di telefono assente dal sito',
    why: 'Nome, Indirizzo, Telefono (NAP) coerenti sono il segnale fondante del local SEO: senza telefono non c’è allineamento con il profilo Google Business e il local pack resta irraggiungibile.',
    fix: 'Il plugin stampa il NAP nel footer e nello schema LocalBusiness; da compilare in config.json.',
    check: (s) => {
      const text = s.published.map((d) => d.text).join(' ');
      const phones = text.match(/(?:\+39[\s.]?)?(?:0\d{1,3}[\s.\-/]?\d{5,8}|3\d{2}[\s.\-]?\d{3}[\s.\-]?\d{3,4})/g) || [];
      return phones.length ? [] : [siteIssue('nessun numero di telefono trovato in tutto il contenuto pubblicato')];
    },
  },
  {
    id: 'LOC-02', category: 'local', severity: SEV.critical, auto: true,
    title: 'Indirizzo fisico e dati aziendali (P.IVA) assenti',
    why: 'Indirizzo e partita IVA sono richiesti dalla normativa italiana per i siti aziendali e sono segnali di fiducia usati da Google per la valutazione E-E-A-T e per il local ranking.',
    fix: 'Footer + pagina Contatti + schema PostalAddress generati dal fixer.',
    check: (s) => {
      const text = s.published.map((d) => d.text).join(' ');
      const out = [];
      if (!/(?:P\.?\s?IVA|partita iva)[:\s]*\d{11}/i.test(text)) out.push(siteIssue('partita IVA non presente nel sito'));
      if (!/\b(via|viale|piazza|corso|largo)\s+[A-ZÀ-Ù][\w'àèéìòù]+/i.test(text)) out.push(siteIssue('nessun indirizzo postale rilevato'));
      return out;
    },
  },
  {
    id: 'LOC-03', category: 'local', severity: SEV.critical, auto: true,
    title: 'Schema LocalBusiness assente',
    why: 'Senza LocalBusiness/ProfessionalService Google non ha una entità aziendale da associare a orari, area servita e recensioni: è il prerequisito tecnico del local pack.',
    fix: 'Schema ProfessionalService completo (geo, openingHours, areaServed, sameAs) generato dal plugin.',
    check: (s) => {
      const has = s.published.some((d) => /LocalBusiness|ProfessionalService/i.test(d.content));
      return has ? [] : [siteIssue('nessuno schema LocalBusiness/ProfessionalService in tutto il sito')];
    },
  },
  {
    id: 'LOC-04', category: 'local', severity: SEV.high, auto: false,
    title: 'Nessun link al profilo Google Business',
    why: 'Il collegamento fra sito e scheda Google Business rafforza la corrispondenza dell’entità e alimenta le recensioni mostrate in SERP.',
    fix: 'Aggiungere link alla scheda e sameAs nello schema; richiedere recensioni ai clienti.',
    check: (s) => {
      const has = s.published.some((d) => /g\.page|google\.com\/maps|maps\.app\.goo\.gl|business\.google/i.test(d.content));
      return has ? [] : [siteIssue('nessun riferimento alla scheda Google Business trovato')];
    },
  },
  {
    id: 'LOC-05', category: 'local', severity: SEV.high, auto: false,
    title: 'Cannibalizzazione fra le landing locali',
    why: 'Più pagine ottimizzate su varianti quasi identiche della stessa query locale ("web agency a palermo", "agenzia di marketing a palermo") si contendono lo stesso posizionamento.',
    fix: 'Una pagina pilastro "web agency Palermo" + pagine servizio differenziate per intento, collegate da link interni.',
    check: (s) => {
      const map = new Map();
      s.published.forEach((d) => {
        const k = (d.focusKeyword || '').toLowerCase();
        if (!k || !mentionsCity(k)) return;
        const norm = k.replace(/\b(a|di|in|per|la|il)\b/g, ' ').replace(/\s+/g, ' ').trim();
        if (!map.has(norm)) map.set(norm, []);
        map.get(norm).push(d);
      });
      const out = [];
      for (const [k, docs] of map) if (docs.length > 1) docs.forEach((d) => out.push(docIssue(d, `keyword locale "${k}" condivisa con altre ${docs.length - 1} pagine`)));
      return out;
    },
  },
  {
    id: 'LOC-06', category: 'local', severity: SEV.medium, auto: false,
    title: 'Nessuna copertura dei comuni della provincia',
    why: 'Le ricerche locali si frammentano per comune (Monreale, Bagheria, Carini, Cefalù...): senza pagine o sezioni dedicate si perde tutta la coda lunga geografica.',
    fix: 'Creare pagine "servizio + comune" con contenuto realmente differenziato (casi, riferimenti locali), mai duplicate.',
    check: (s) => {
      const comuni = ['monreale', 'bagheria', 'carini', 'cefalù', 'cefalu', 'termini imerese', 'partinico', 'misilmeri'];
      const text = s.published.map((d) => d.text.toLowerCase()).join(' ');
      const found = comuni.filter((c) => text.includes(c));
      return found.length >= 3 ? [] : [siteIssue(`solo ${found.length} comuni della provincia citati (${found.join(', ') || 'nessuno'})`)];
    },
  },
  {
    id: 'LOC-07', category: 'local', severity: SEV.medium, auto: true,
    title: 'Landing locali senza segnali geografici nel testo',
    why: 'Una pagina che punta a una keyword locale deve contenere riferimenti reali al territorio: quartieri, comuni, indirizzo, area servita.',
    fix: 'Blocco "Dove operiamo" con area servita e riferimenti locali aggiunto dal fixer.',
    check: (s) => s.published.filter((d) => mentionsCity(d.focusKeyword) && (d.text.toLowerCase().match(/palermo/g) || []).length < 3)
      .map((d) => docIssue(d, `keyword locale ma solo ${(d.text.toLowerCase().match(/palermo/g) || []).length} menzioni della città nel testo`)),
  },
  {
    id: 'LOC-08', category: 'local', severity: SEV.high, auto: false,
    title: 'Nessuna recensione o testimonianza strutturata',
    why: 'Le recensioni sono un fattore di ranking locale e alimentano le stelline in SERP; sono anche uno dei contenuti più citati dalle risposte AI su fornitori di servizi.',
    fix: 'Raccogliere recensioni Google, pubblicare testimonianze con schema Review/AggregateRating (solo se reali e verificabili).',
    check: (s) => {
      const has = s.published.some((d) => /"@type"\s*:\s*"(Review|AggregateRating)"/i.test(d.content));
      return has ? [] : [siteIssue('nessuna recensione strutturata sul sito')];
    },
  },
];
