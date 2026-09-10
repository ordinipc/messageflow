'use strict';
const T = require('../core/text');

/**
 * Triage editoriale: classifica ogni articolo pubblicato in
 * ELIMINARE / ACCORPARE / RISCRIVERE / MANTENERE.
 * Le decisioni si basano su qualità misurata, similarità reale fra testi,
 * sovrapposizione con le pagine servizio e cannibalizzazione di keyword.
 */

const INTENT = [
  { key: 'transazionale', re: /\b(prezzo|prezzi|costo|costi|quanto costa|preventivo|agenzia|servizi|assistenza|contatt|acquist|vendita|noleggi)\b/i },
  { key: 'commerciale', re: /\b(migliori|miglior|come scegliere|scegliere|confronto|alternativ|vale la pena|conviene|recension|classifica|top \d+)\b/i },
  { key: 'informazionale', re: /\b(come|cosa|perch|quando|dove|guida|strategie|consigli|errori|significa|funziona|tutorial|esempi|tendenze|trend)\b/i },
];

function classifyIntent(doc) {
  const hay = `${doc.title} ${doc.focusKeyword}`;
  const hit = INTENT.find((i) => i.re.test(hay));
  if (/\bmax digital\b/i.test(hay)) return 'navigazionale';
  return hit ? hit.key : 'informazionale';
}

/** Punteggio di qualità 0-100: lunghezza, struttura, media, link, freschezza, meta. */
function qualityScore(doc, site, maxSim) {
  let s = 0;
  const w = doc.words;
  if (w >= 1300) s += 34; else if (w >= 900) s += 28; else if (w >= 600) s += 18; else if (w >= 350) s += 9;

  const h2 = doc.h2.length;
  if (h2 >= 5) s += 14; else if (h2 >= 3) s += 10; else if (h2 >= 1) s += 5;
  if (doc.lists > 0) s += 4;
  if (doc.tables > 0) s += 4;
  if (doc.images.length > 0) s += 5;
  if (doc.thumbnailId) s += 3;

  s += Math.round(16 * (1 - Math.min(1, maxSim / 0.5)));            // unicità

  const inb = site.inboundCount(doc.path);
  s += Math.min(6, inb * 2) + Math.min(4, doc.internalLinks.length * 2);

  const ageDays = (Date.now() - Date.parse(`${(doc.modified || doc.date || '').replace(' ', 'T')}Z`)) / 864e5;
  if (ageDays < 180) s += 6; else if (ageDays < 365) s += 3;

  if (doc.seoScore !== null) s += Math.round(Math.min(1, doc.seoScore / 100) * 5);
  if (doc.gulpease !== null && doc.gulpease >= 50) s += 3;

  return Math.max(0, Math.min(100, s));
}

function buildTriage(site, cfg) {
  const posts = site.posts.slice();
  const servicePages = site.pages.filter((p) => cfg.seo.paginePilastro.some((x) => x.slug === p.slug));

  const shPost = new Map(posts.map((p) => [p.id, T.shingles(p.text)]));
  const shService = new Map(servicePages.map((p) => [p.id, T.shingles(p.text)]));

  // 1) Similarità articolo-articolo
  const sim = new Map(posts.map((p) => [p.id, []]));
  for (let i = 0; i < posts.length; i++) {
    for (let j = i + 1; j < posts.length; j++) {
      const v = T.jaccard(shPost.get(posts[i].id), shPost.get(posts[j].id));
      if (v >= 0.18) {
        sim.get(posts[i].id).push({ id: posts[j].id, v });
        sim.get(posts[j].id).push({ id: posts[i].id, v });
      }
    }
  }

  // 2) Sovrapposizione con le pagine servizio
  const overlapService = new Map();
  posts.forEach((p) => {
    let best = { slug: null, v: 0 };
    servicePages.forEach((sp) => {
      const v = T.jaccard(shPost.get(p.id), shService.get(sp.id));
      if (v > best.v) best = { slug: sp.slug, title: sp.title, url: sp.link, v };
    });
    overlapService.set(p.id, best);
  });

  // 3) Cannibalizzazione: stessa focus keyword normalizzata
  const normKw = (k) => (k || '').toLowerCase().replace(/\b(a|di|in|per|la|il|le|i|lo|gli|un|una|e)\b/g, ' ').replace(/[^a-zà-ù0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();
  const kwGroups = new Map();
  posts.forEach((p) => {
    const k = normKw(p.focusKeyword);
    if (!k) return;
    if (!kwGroups.has(k)) kwGroups.set(k, []);
    kwGroups.get(k).push(p);
  });
  const servizioKw = new Map();
  servicePages.forEach((sp) => servizioKw.set(normKw(sp.focusKeyword || sp.title), sp));

  const byId = new Map(posts.map((p) => [p.id, p]));
  const record = new Map();
  posts.forEach((p) => {
    const maxSim = (sim.get(p.id)[0] || { v: 0 }).v && Math.max(...sim.get(p.id).map((x) => x.v));
    const q = qualityScore(p, site, maxSim || 0);
    record.set(p.id, {
      doc: p,
      intento: classifyIntent(p),
      qualita: q,
      simili: sim.get(p.id).sort((a, b) => b.v - a.v).slice(0, 5)
        .map((x) => ({ v: Number(x.v.toFixed(2)), title: byId.get(x.id).title, url: byId.get(x.id).link, id: x.id })),
      maxSim: Number((maxSim || 0).toFixed(2)),
      servizio: overlapService.get(p.id),
      kwGroup: normKw(p.focusKeyword),
      cannibalizzaServizio: servizioKw.has(normKw(p.focusKeyword)) ? servizioKw.get(normKw(p.focusKeyword)) : null,
      categoria: null,
      motivo: [],
      azione: '',
      target301: null,
    });
  });

  /** Il "vincitore" di un gruppo: più qualità, più parole, più link in entrata. */
  const pickWinner = (docs) => docs.slice().sort((a, b) => {
    const ra = record.get(a.id), rb = record.get(b.id);
    return (rb.qualita - ra.qualita) || (b.words - a.words) || (site.inboundCount(b.path) - site.inboundCount(a.path));
  })[0];

  // --- Cluster di near-duplicate (soglia alta): uno resta, gli altri si eliminano ---
  const visited = new Set();
  const clustersDuplicati = [];
  posts.forEach((p) => {
    if (visited.has(p.id)) return;
    const stack = [p.id], group = [];
    while (stack.length) {
      const id = stack.pop();
      if (visited.has(id)) continue;
      visited.add(id);
      group.push(byId.get(id));
      sim.get(id).filter((x) => x.v >= 0.55).forEach((x) => { if (!visited.has(x.id)) stack.push(x.id); });
    }
    if (group.length > 1) clustersDuplicati.push(group);
  });

  clustersDuplicati.forEach((group) => {
    const winner = pickWinner(group);
    group.forEach((d) => {
      const r = record.get(d.id);
      if (d.id === winner.id) {
        r.categoria = 'riscrivere';
        r.motivo.push(`versione migliore di un gruppo di ${group.length} articoli quasi identici: assorbe i contenuti degli altri`);
      } else {
        r.categoria = 'eliminare';
        r.motivo.push(`duplicato al ${Math.round(r.maxSim * 100)}% di "${winner.title}"`);
        r.target301 = { title: winner.title, url: winner.link };
      }
    });
  });

  // --- Cluster di sovrapposizione media: da accorpare ---
  const visited2 = new Set();
  const clustersAccorpa = [];
  posts.forEach((p) => {
    if (record.get(p.id).categoria || visited2.has(p.id)) return;
    const stack = [p.id], group = [];
    while (stack.length) {
      const id = stack.pop();
      if (visited2.has(id) || record.get(id).categoria) continue;
      visited2.add(id);
      group.push(byId.get(id));
      sim.get(id).filter((x) => x.v >= 0.30).forEach((x) => { if (!visited2.has(x.id)) stack.push(x.id); });
    }
    if (group.length > 1) clustersAccorpa.push(group);
  });

  // --- Gruppi di cannibalizzazione sulla stessa keyword ---
  for (const [k, docs] of kwGroups) {
    if (!k || docs.length < 2) continue;
    const liberi = docs.filter((d) => !record.get(d.id).categoria);
    if (liberi.length > 1) clustersAccorpa.push(liberi);
  }

  clustersAccorpa.forEach((group) => {
    const winner = pickWinner(group);
    group.forEach((d) => {
      const r = record.get(d.id);
      if (r.categoria) return;
      if (d.id === winner.id) {
        r.categoria = 'accorpare';
        r.motivo.push(`articolo principale del gruppo: accoglie i contenuti degli altri ${group.length - 1}`);
        r.azione = 'mantenere e ampliare';
        r.gruppo = group.map((g) => ({ title: g.title, url: g.link, id: g.id }));
      } else {
        r.categoria = 'accorpare';
        const comune = Math.round((record.get(d.id).maxSim || 0) * 100);
        r.motivo.push(comune >= 30
          ? `sovrapposto a "${winner.title}": ${comune}% di testo in comune`
          : `stessa focus keyword di "${winner.title}" ("${d.focusKeyword}"): le due pagine competono per la stessa query`);
        r.azione = 'unire nel principale + 301';
        r.target301 = { title: winner.title, url: winner.link };
      }
    });
  });

  // --- Cannibalizzazione diretta di una pagina servizio ---
  posts.forEach((p) => {
    const r = record.get(p.id);
    if (r.categoria) return;
    const sp = r.cannibalizzaServizio;
    const ov = r.servizio;
    if (sp && p.words < 700) {
      r.categoria = 'eliminare';
      r.motivo.push(`stessa keyword della pagina servizio "${sp.title}" con contenuto più debole: toglie forza alla pagina che deve posizionarsi`);
      r.target301 = { title: sp.title, url: sp.link };
    } else if (ov && ov.v >= 0.30) {
      r.categoria = 'accorpare';
      r.azione = 'confluire nella pagina servizio + 301';
      r.motivo.push(`${Math.round(ov.v * 100)}% di testo in comune con la pagina servizio "${ov.title}"`);
      r.target301 = { title: ov.title, url: ov.url };
    }
  });

  // --- Contenuti inutilmente scarni ---
  posts.forEach((p) => {
    const r = record.get(p.id);
    if (r.categoria) return;
    if (p.words < 300 && r.qualita < 30) {
      r.categoria = 'eliminare';
      r.motivo.push(`solo ${p.words} parole e nessun segnale di valore: non può soddisfare nessun intento di ricerca`);
      const t = r.servizio && r.servizio.v > 0.12 ? r.servizio : null;
      r.target301 = t ? { title: t.title, url: t.url } : null;
    }
  });

  // --- Resto: riscrivere o mantenere in base alla qualità ---
  const SOGLIA_MANTIENI = 58;
  posts.forEach((p) => {
    const r = record.get(p.id);
    if (r.categoria) return;
    // Sotto le 500 parole un articolo non regge il confronto con la prima pagina,
    // per quanto sia scritto bene: va comunque ampliato.
    const promosso = p.words >= 500
      && (r.qualita >= SOGLIA_MANTIENI || (p.words >= 900 && r.qualita >= 52));
    if (promosso) {
      r.categoria = 'mantenere';
      r.motivo.push(`qualità ${r.qualita}/100: lunghezza, struttura e unicità adeguate`);
      r.azione = 'ottimizzare meta e link interni';
    } else {
      r.categoria = 'riscrivere';
      const perche = [];
      if (p.words < 900) perche.push(`solo ${p.words} parole`);
      if (p.h2.length < 3) perche.push(`${p.h2.length} sottotitoli H2`);
      if (!p.images.length) perche.push('nessuna immagine');
      if (!p.internalLinks.length) perche.push('nessun link interno');
      if (p.tables === 0) perche.push('nessun dato in tabella');
      r.motivo.push(`qualità ${r.qualita}/100 — ${perche.join(', ')}`);
      r.azione = 'espandere a 1.000-1.400 parole con struttura, FAQ e dati';
    }
  });

  // Azioni e note 301 finali
  posts.forEach((p) => {
    const r = record.get(p.id);
    if (r.categoria === 'eliminare') {
      const inb = site.inboundCount(p.path);
      r.azione = r.target301
        ? `301 verso ${r.target301.url} poi cestinare`
        : (inb > 0 ? '301 verso la categoria di riferimento poi cestinare' : 'cestinare e restituire 410 (nessun link in entrata da preservare)');
      r.redirect = Boolean(r.target301) || inb > 0;
    }
    if (r.categoria === 'riscrivere' && !r.azione) r.azione = 'riscrittura sostanziale';
  });

  const out = posts.map((p) => {
    const r = record.get(p.id);
    return {
      id: p.id,
      titolo: p.title,
      url: p.link,
      slug: p.slug,
      categoria: r.categoria,
      parole: p.words,
      qualita: r.qualita,
      intento: r.intento,
      h2: p.h2.length,
      immagini: p.images.length,
      linkInterniIn: site.inboundCount(p.path),
      linkInterniOut: p.internalLinks.length,
      focusKeyword: p.focusKeyword,
      dataPubblicazione: (p.date || '').slice(0, 10),
      ultimaModifica: (p.modified || p.date || '').slice(0, 10),
      similaritaMax: r.maxSim,
      articoliSimili: r.simili,
      sovrapposizioneServizio: r.servizio && r.servizio.slug ? { pagina: r.servizio.title, url: r.servizio.url, percentuale: Math.round(r.servizio.v * 100) } : null,
      cannibalizzaPaginaServizio: r.cannibalizzaServizio ? { pagina: r.cannibalizzaServizio.title, url: r.cannibalizzaServizio.link } : null,
      motivo: r.motivo.join('; '),
      azione: r.azione,
      redirect301: r.target301 || null,
      gruppoAccorpamento: r.gruppo || null,
    };
  });

  const conteggi = out.reduce((a, x) => { a[x.categoria] = (a[x.categoria] || 0) + 1; return a; }, {});
  return { articoli: out, conteggi, totale: out.length };
}

module.exports = { buildTriage, classifyIntent, qualityScore };
