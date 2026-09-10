'use strict';
const T = require('../core/text');

/**
 * Piano di link interni.
 * Obiettivi: (1) nessuna pagina orfana, (2) ogni articolo linka le pagine
 * pilastro pertinenti, (3) anchor text descrittivi e non ripetitivi.
 */

function termsOf(doc) {
  const set = new Set();
  T.words(`${doc.title} ${doc.focusKeyword} ${doc.focusKeyword}`).forEach((w) => { if (w.length > 3 && !T.STOPWORDS_IT.has(w)) set.add(w); });
  T.topTerms(doc.text, 15).forEach((t) => set.add(t.term));
  return set;
}

function overlap(a, b) {
  let n = 0;
  for (const x of a) if (b.has(x)) n++;
  return n / Math.sqrt(Math.max(1, a.size) * Math.max(1, b.size));
}

function buildLinkPlan(site, cfg) {
  const perDoc = cfg.seo.linkInterniPerArticolo || 4;
  const pillarSlugs = new Set(cfg.seo.paginePilastro.map((p) => p.slug));

  // Le pagine legali non partecipano: non devono ne' ricevere ne' distribuire link editoriali.
  const legale = (d) => /privacy|cookie|termini|condizioni|note-legali/i.test(d.slug);
  const docs = site.published.filter((d) => d.words > 80 && !legale(d));
  const index = new Map(docs.map((d) => [d.path, { doc: d, terms: termsOf(d) }]));

  const isPillar = (d) => pillarSlugs.has(d.slug) || d.type === 'page';

  const plan = [];        // { from, to, anchor, motivo }
  const inboundNew = new Map(docs.map((d) => [d.path, 0]));

  for (const source of docs) {
    const src = index.get(source.path);
    const already = new Set(source.internalLinks.map((l) => l.href.replace(/^https?:\/\/[^/]+/, '').replace(/\/$/, '')));

    const scored = docs
      .filter((t) => t.path !== source.path && !already.has(t.path))
      .map((t) => {
        const sim = overlap(src.terms, index.get(t.path).terms);
        const bonus = isPillar(t) ? 0.22 : 0;                       // le pagine servizio vanno spinte
        const orphanBoost = site.inboundCount(t.path) === 0 ? 0.12 : 0; // e le orfane vanno recuperate
        const cat = t.categories[0] && source.categories[0] && t.categories[0].slug === source.categories[0].slug ? 0.05 : 0;
        return { t, score: sim + bonus + orphanBoost + cat };
      })
      .filter((x) => x.score > 0.12)
      .sort((a, b) => b.score - a.score);

    const chosen = [];
    for (const cand of scored) {
      if (chosen.length >= perDoc) break;
      // Massimo una pagina pilastro ogni due link: il resto sono correlati.
      const pillars = chosen.filter((c) => isPillar(c.t)).length;
      if (isPillar(cand.t) && pillars >= Math.ceil(perDoc / 2)) continue;
      chosen.push(cand);
    }

    chosen.forEach((c) => {
      inboundNew.set(c.t.path, (inboundNew.get(c.t.path) || 0) + 1);
      plan.push({
        da: source.path,
        daTitolo: source.title,
        a: c.t.path,
        aTitolo: c.t.title,
        anchor: c.t.focusKeyword ? c.t.focusKeyword : T.truncate(c.t.title, 60),
        punteggio: Number(c.score.toFixed(3)),
        motivo: isPillar(c.t) ? 'pagina servizio pertinente' : 'articolo correlato',
      });
    });
  }

  // Recupero esplicito delle orfane rimaste senza link in entrata.
  const orphans = docs.filter((d) => site.inboundCount(d.path) === 0 && (inboundNew.get(d.path) || 0) < 2);
  for (const orphan of orphans) {
    const ot = index.get(orphan.path).terms;
    const sources = docs
      .filter((d) => d.path !== orphan.path)
      .map((d) => ({ d, score: overlap(ot, index.get(d.path).terms) }))
      .sort((a, b) => b.score - a.score)
      .slice(0, 3 - (inboundNew.get(orphan.path) || 0));
    sources.forEach(({ d, score }) => {
      if (plan.some((p) => p.da === d.path && p.a === orphan.path)) return;
      inboundNew.set(orphan.path, (inboundNew.get(orphan.path) || 0) + 1);
      plan.push({
        da: d.path, daTitolo: d.title, a: orphan.path, aTitolo: orphan.title,
        anchor: orphan.focusKeyword || T.truncate(orphan.title, 60),
        punteggio: Number(score.toFixed(3)), motivo: 'recupero pagina orfana',
      });
    });
  }

  // Mappa keyword -> URL usata dal plugin per i link automatici nel testo.
  const keywordMap = {};
  cfg.seo.paginePilastro.forEach((p) => {
    const doc = site.published.find((d) => d.slug === p.slug);
    if (doc) keywordMap[p.keyword.toLowerCase()] = doc.link;
  });
  site.published.forEach((d) => {
    const k = (d.focusKeyword || '').toLowerCase().trim();
    if (k && k.length > 8 && !keywordMap[k]) keywordMap[k] = d.link;
  });

  const residuiOrfani = docs.filter((d) => site.inboundCount(d.path) === 0 && (inboundNew.get(d.path) || 0) === 0);

  return {
    plan,
    keywordMap,
    statistiche: {
      linkProposti: plan.length,
      documenti: docs.length,
      orfaniPrima: docs.filter((d) => site.inboundCount(d.path) === 0).length,
      orfaniDopo: residuiOrfani.length,
      mediaLinkPerDocumento: Number((plan.length / Math.max(1, docs.length)).toFixed(2)),
    },
  };
}

module.exports = { buildLinkPlan };
