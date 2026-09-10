'use strict';
const T = require('../core/text');

/**
 * Piano operativo per contenuto: cosa scrivere, quanto, con quale struttura.
 * Le scalette sono costruite sull'intento di ricerca rilevato dal triage.
 */

const SCALETTE = {
  transazionale: (kw, citta) => [
    `Che cos'è ${kw} e a chi serve`,
    `Quanto costa ${kw} a ${citta}: fasce di prezzo e cosa incide`,
    'Cosa comprende il servizio, passo per passo',
    'Tempi di realizzazione e come si svolge il lavoro',
    'Errori da evitare nella scelta del fornitore',
    'Caso reale: problema, intervento, risultato misurato',
    'Domande frequenti',
  ],
  commerciale: (kw) => [
    `Come scegliere ${kw}: i criteri che contano davvero`,
    'Confronto fra le soluzioni disponibili (tabella)',
    'Vantaggi e limiti di ciascuna opzione',
    'Quanto budget serve e come si ripaga',
    'Come valutare i risultati dopo tre mesi',
    'Domande frequenti',
  ],
  informazionale: (kw) => [
    `${T.truncate(kw, 50)}: definizione in due righe`,
    'Perché è importante adesso (con dati aggiornati)',
    'Come funziona: spiegazione passo per passo',
    'Esempi pratici applicati a una PMI',
    'Errori più comuni e come evitarli',
    'Strumenti e risorse consigliate',
    'Domande frequenti',
  ],
  navigazionale: (kw) => [
    `Chi siamo e cosa facciamo`,
    `Perché scegliere ${kw}`,
    'I nostri lavori',
    'Domande frequenti',
  ],
};

function faqPerIntento(kw, citta, intento) {
  const base = [
    `Quanto costa ${kw}?`,
    `Quanto tempo serve per ${kw.toLowerCase().startsWith('come') ? kw : `ottenere risultati con ${kw}`}?`,
    `${kw} conviene a una piccola impresa?`,
    `Come si misurano i risultati di ${kw}?`,
  ];
  if (intento === 'transazionale') base.unshift(`Chi si occupa di ${kw} a ${citta}?`);
  return base.slice(0, 5);
}

function buildContentPlan(site, cfg, triage, linkPlan) {
  const citta = cfg.seo.cittaPrincipale;
  const byId = new Map(site.published.map((d) => [d.id, d]));
  const linkPerDoc = new Map();
  linkPlan.plan.forEach((l) => {
    if (!linkPerDoc.has(l.da)) linkPerDoc.set(l.da, []);
    linkPerDoc.get(l.da).push(l);
  });

  const schede = triage.articoli
    .filter((a) => ['riscrivere', 'accorpare'].includes(a.categoria))
    .map((a) => {
      const doc = byId.get(a.id);
      const kw = a.focusKeyword || doc.title;
      const intento = a.intento;
      const scaletta = (SCALETTE[intento] || SCALETTE.informazionale)(kw, citta);
      const target = a.parole < 500 ? 1200 : Math.max(1000, Math.round((a.parole * 1.8) / 100) * 100);
      const links = (linkPerDoc.get(doc.path) || []).slice(0, 4);

      return {
        id: a.id,
        titolo: a.titolo,
        url: a.url,
        categoria: a.categoria,
        intento,
        paroleAttuali: a.parole,
        paroleTarget: Math.min(1800, target),
        focusKeyword: kw,
        inBreve: `Rispondi alla domanda principale in 40-60 parole, subito sotto l'H1, iniziando con "${T.truncate(kw, 40)} è…" o con il dato più utile.`,
        scalettaH2: scaletta,
        faq: faqPerIntento(kw, citta, intento),
        datiDaInserire: [
          'almeno 2 numeri verificabili con fonte (Istat, Osservatori Politecnico di Milano, report Google/Meta)',
          'una tabella con costi, tempi o requisiti',
          'un esempio concreto di cliente o settore locale',
        ],
        immagini: doc.images.length === 0 ? 'aggiungere almeno 1 immagine originale con alt descrittivo + immagine in evidenza' : 'verificare alt e peso delle immagini presenti',
        linkInterniDaInserire: links.map((l) => ({ anchor: l.anchor, url: site.byPath.get(l.a) ? site.byPath.get(l.a).link : l.a })),
        note: a.motivo,
        azione: a.azione,
      };
    });

  // Calendario: prima le pagine servizio, poi gli articoli a maggior potenziale.
  const priorita = schede
    .map((s) => ({ s, score: (s.intento === 'transazionale' ? 3 : s.intento === 'commerciale' ? 2 : 1) * 100 - s.paroleAttuali / 10 }))
    .sort((a, b) => b.score - a.score)
    .map((x) => x.s);

  const settimane = [];
  const inizio = new Date();
  for (let w = 0; w < 12; w++) {
    const data = new Date(inizio.getTime() + w * 7 * 864e5);
    settimane.push({
      settimana: w + 1,
      dal: data.toISOString().slice(0, 10),
      lavori: priorita.slice(w * 4, w * 4 + 4).map((s) => ({ titolo: s.titolo, url: s.url, azione: s.azione, paroleTarget: s.paroleTarget })),
    });
  }

  // Contenuti nuovi da produrre per coprire i buchi di keyword.
  const nuoviContenuti = [
    { titolo: `Quanto costa un sito web a ${citta}? Prezzi reali e cosa incide`, intento: 'transazionale', motivo: 'query ad alto volume e alta intenzione, oggi non coperta' },
    { titolo: `Web agency a ${citta}: come scegliere quella giusta (guida 2026)`, intento: 'commerciale', motivo: 'pagina pilastro che raccoglie la cannibalizzazione attuale' },
    { titolo: `Case study: come abbiamo aumentato i contatti di un'azienda di ${citta}`, intento: 'commerciale', motivo: 'prova di esperienza reale, oggi assente (E-E-A-T)' },
    { titolo: 'Quanto costa gestire i social media per una PMI: fasce di prezzo', intento: 'transazionale', motivo: 'domanda frequente, formato citabile dai motori generativi' },
    { titolo: `Realizzazione e-commerce a ${citta}: tempi, costi e piattaforme a confronto`, intento: 'commerciale', motivo: 'tabella comparativa, formato preferito dalle risposte AI' },
    ...(cfg.azienda.areaServita || []).filter((c) => c !== citta && c !== 'Sicilia').slice(0, 4)
      .map((comune) => ({ titolo: `Realizzazione siti web a ${comune}: servizi per le imprese locali`, intento: 'transazionale', motivo: `copertura della coda lunga geografica su ${comune}` })),
  ];

  return { schede, calendario: settimane, nuoviContenuti };
}

module.exports = { buildContentPlan };
