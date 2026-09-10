'use strict';
const T = require('../core/text');

const CAPS = (s) => String(s || '').replace(/^\s*([a-zà-ù])/, (m, c) => c.toUpperCase());

/** Benefici usati per completare i title corti, scelti in base al tema della pagina. */
const BENEFIT = [
  [/e-?commerce|negozio online|shop/i, 'Vendi di più online'],
  [/sito web|siti web|landing/i, 'Sito veloce e che converte'],
  [/social|instagram|facebook|tiktok|reels/i, 'Strategia social che funziona'],
  [/video|reel|spot|riprese/i, 'Video che fanno crescere il brand'],
  [/seo|posizionamento|google/i, 'Più visibilità su Google'],
  [/crm|gestionale|software|automazione/i, 'Processi automatizzati'],
  [/grafic|logo|brand|naming|design/i, 'Identità di marca riconoscibile'],
  [/sicurezza|cyber|malware|phishing/i, 'Proteggi i tuoi dati'],
  [/stamp|pannell|insegn/i, 'Stampa professionale su misura'],
  [/marketing|campagn|ads|advertis/i, 'Campagne che portano clienti'],
];

function benefitFor(text) {
  const hit = BENEFIT.find(([re]) => re.test(text));
  return hit ? hit[1] : 'Guida pratica per le PMI';
}

/** Normalizza i nomi propri che nelle keyword arrivano in minuscolo. */
const PROPER = ['Instagram','Facebook','TikTok','YouTube','LinkedIn','WordPress','Google','WhatsApp','SEO','SEM','CRM','AI','E-commerce','Shopify','WooCommerce','Meta Ads','Google Ads','Palermo','Sicilia','Reels','Telegram','Pinterest','Twitch','Threads'];
function properCase(str) {
  let out = String(str || '');
  for (const w of PROPER) {
    out = out.replace(new RegExp(`\\b${w.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&')}\\b`, 'ig'), w);
  }
  return out;
}

/** Title entro `max` caratteri, con la focus keyword nei primi caratteri. */
function buildTitle(doc, cfg) {
  const max = cfg.seo.titleMax;
  const brand = cfg.seo.brandSuffix;
  const kw = properCase(CAPS(doc.focusKeyword || '').trim());
  const current = (doc.seoTitle || doc.title).trim();
  const isLegal = /privacy|cookie|termini|condizioni|note-legali/i.test(doc.slug);

  // Title già valido e con la keyword: si tiene, non si tocca cio che funziona.
  if (current.length <= max && current.length >= 30
      && (!kw || current.toLowerCase().includes(kw.toLowerCase()))) {
    return { title: current, changed: false };
  }

  // Pagine legali: nessun claim di marketing, solo etichetta + brand.
  if (isLegal) {
    const t = T.truncate(`${doc.title} | ${brand}`, max);
    return { title: t, changed: t !== current };
  }

  const benefit = benefitFor(`${doc.title} ${doc.focusKeyword} ${doc.text.slice(0, 400)}`);
  const rest = kw
    ? properCase(doc.title).replace(new RegExp(kw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'ig'), '')
        .replace(/^[\s:,\-–|]+/, '').replace(/\s{2,}/g, ' ').trim()
    : properCase(doc.title);

  // Il resto del titolo si riusa solo se la keyword ne e' il prefisso: altrimenti
  // togliendola dal mezzo resterebbe un frammento senza senso ("? Una guida per").
  const kwIsPrefix = kw && properCase(doc.title).toLowerCase().startsWith(kw.toLowerCase());
  const restIsClean = rest && rest.length > 12 && /^[A-Za-zÀ-ù0-9]/.test(rest) && rest.split(/\s+/).length >= 3;

  const candidates = [];
  if (kw) {
    if (kwIsPrefix && restIsClean) candidates.push(`${kw}: ${T.truncate(rest, max - kw.length - 2)}`);
    candidates.push(`${kw}: ${benefit}`);
    candidates.push(`${kw} | ${brand}`);
    candidates.push(kw);
  }
  candidates.push(T.truncate(properCase(doc.title), max));

  // Evita ripetizioni tipo "Privacy Policy: Privacy Policy".
  const dedup = (str) => {
    const parts = String(str).split(/\s*[:|]\s*/).map((x) => x.trim()).filter(Boolean);
    const kept = [];
    for (const part of parts) {
      const k = part.toLowerCase();
      if (kept.some((v) => v.toLowerCase().includes(k) || k.includes(v.toLowerCase()))) continue;
      kept.push(part);
    }
    return kept.join(': ');
  };

  let best = candidates.map(dedup).find((c) => c.length >= 30 && c.length <= max)
    || candidates.map((c) => T.truncate(dedup(c), max)).find((c) => c.length >= 25)
    || T.truncate(dedup(current), max);

  // Se resta spazio si aggancia il brand: rafforza il riconoscimento dell'entità.
  if (best.length + brand.length + 3 <= max && !best.toLowerCase().includes(brand.toLowerCase())) best = `${best} | ${brand}`;
  return { title: best.trim(), changed: best.trim() !== current };
}

/** Meta description 140-158 caratteri, costruita su frasi complete. */
function buildDescription(doc, cfg) {
  const { descMin, descMax, cittaPrincipale } = cfg.seo;
  const current = (doc.seoDesc || '').trim();
  // Una description "nella lunghezza giusta" ma tagliata a meta ("...visibilita, engagement e")
  // resta inutile: si considera valida solo se chiude una frase compiuta.
  const benFormata = /[.!?]$/.test(current) && T.polishClause(current).length >= current.length - 1;
  if (current.length >= descMin && current.length <= descMax && benFormata) {
    return { description: current, changed: false };
  }

  const kw = properCase(CAPS((doc.focusKeyword || '').trim()));
  // Si riparte dalla description esistente quando c'e' (conserva il lavoro fatto),
  // altrimenti dal primo paragrafo del contenuto.
  const source = [current, doc.firstParagraph, doc.excerpt, doc.text].find((x) => x && x.length > 40) || doc.title;

  // Si accumulano frasi intere: mai tagli a metà concetto.
  const parts = properCase(source.replace(/\s+/g, ' ')).split(/(?<=[.!?])\s+/);
  let body = '';
  for (const part of parts) {
    const next = body ? `${body} ${part}` : part;
    if (next.length > descMax - 2) break;
    body = next;
  }
  if (!body) {
    body = T.polishClause(T.truncate(properCase(source), descMax - 26));
  }
  body = body.trim();
  if (!/[.!?]$/.test(body)) body = `${T.polishClause(body)}.`;

  // La keyword deve comparire: se manca si antepone come attacco.
  if (kw && !body.toLowerCase().includes(kw.toLowerCase())) {
    const prefix = `${kw}: `;
    const room = descMax - prefix.length - 1;
    let shortened = body;
    if (shortened.length > room) {
      shortened = `${T.polishClause(T.truncate(shortened, room))}.`;
    }
    body = prefix + shortened;
  }

  // Riempimento fino alla soglia minima con una call to action pertinente.
  const ctas = [
    ` Scopri come lavoriamo a ${cittaPrincipale}.`,
    ' Richiedi una consulenza gratuita.',
    ' Parla con i nostri esperti.',
    ' Contattaci per un preventivo.',
    ` ${cfg.azienda.nome}, web agency a ${cittaPrincipale}.`,
  ];
  let out = body;
  for (const cta of ctas) {
    if (out.length >= descMin) break;
    if ((out + cta).length <= descMax) out += cta;
  }
  let finale = T.truncate(out, descMax).trim();
  if (!/[.!?]$/.test(finale)) finale = `${T.polishClause(finale)}.`;
  return { description: finale, changed: finale !== current };
}

/** Estratto di 25-35 parole, usato come fallback ovunque serva un riassunto. */
function buildExcerpt(doc) {
  if (doc.excerpt && doc.excerpt.split(/\s+/).length >= 20) return { excerpt: doc.excerpt, changed: false };
  const src = (doc.firstParagraph || doc.text).replace(/\s+/g, ' ');
  const w = src.split(' ');
  let out = w.slice(0, 34).join(' ');
  if (w.length > 34) out = `${T.polishClause(out)}…`;
  return { excerpt: out, changed: true };
}

function buildSlug(doc, cfg, taken) {
  const current = doc.slug;
  if (current.length <= cfg.seo.slugMax) return { slug: current, changed: false };
  let s = T.shortSlug(doc.focusKeyword || doc.title, 7, cfg.seo.slugMax);
  if (!s) s = T.truncate(current, cfg.seo.slugMax).replace(/-[^-]*$/, '');
  let candidate = s, n = 2;
  while (taken.has(candidate) && candidate !== current) candidate = `${s}-${n++}`;
  taken.add(candidate);
  return { slug: candidate, changed: candidate !== current };
}

/** Genera il piano meta completo per tutti i contenuti pubblicati. */
function buildMetaPlan(site, cfg) {
  const taken = new Set(site.published.map((d) => d.slug));
  return site.published.map((doc) => {
    const t = buildTitle(doc, cfg);
    const d = buildDescription(doc, cfg);
    const e = buildExcerpt(doc);
    const s = buildSlug(doc, cfg, taken);
    return {
      id: doc.id,
      type: doc.type,
      path: doc.path,
      url: doc.link,
      titoloAttuale: doc.seoTitle,
      titoloNuovo: t.title,
      titoloCambiato: t.changed,
      descrizioneAttuale: doc.seoDesc,
      descrizioneNuova: d.description,
      descrizioneCambiata: d.changed,
      excerptNuovo: e.excerpt,
      excerptCambiato: e.changed,
      slugAttuale: doc.slug,
      slugNuovo: s.slug,
      slugCambiato: s.changed,
      focusKeyword: doc.focusKeyword,
      parole: doc.words,
    };
  });
}

module.exports = { buildMetaPlan, properCase, buildTitle, buildDescription, buildExcerpt, buildSlug };
