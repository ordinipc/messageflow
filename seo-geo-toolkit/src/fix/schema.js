'use strict';
/**
 * Generatore di dati strutturati JSON-LD.
 * Un solo grafo @graph per pagina: entità collegate da @id, come raccomandato
 * da Google per evitare schema duplicati e non collegati.
 */

const DA_COMPILARE = /^DA_COMPILARE/i;
const val = (v) => (v && !DA_COMPILARE.test(String(v)) ? v : null);

function organizationNode(cfg, siteUrl) {
  const a = cfg.azienda;
  const sameAs = Object.values(a.profili || {}).map(val).filter(Boolean);
  const node = {
    '@type': ['Organization', 'ProfessionalService'],
    '@id': `${siteUrl}/#organization`,
    name: a.nome,
    legalName: val(a.nomeLegale) || a.nome,
    url: `${siteUrl}/`,
    description: a.descrizioneBreve,
    email: val(a.email),
    telephone: val(a.telefono) || val(a.cellulare),
    vatID: val(a.partitaIva),
    foundingDate: val(a.fondazione),
    priceRange: a.fasciaPrezzo,
    logo: { '@type': 'ImageObject', '@id': `${siteUrl}/#logo`, url: a.logo },
    image: { '@id': `${siteUrl}/#logo` },
    areaServed: (a.areaServita || []).map((x) => ({ '@type': 'City', name: x })),
    knowsAbout: ['Realizzazione siti web', 'E-commerce', 'SEO locale', 'Social media marketing',
      'Produzione video', 'Graphic design', 'Naming', 'CRM e gestionali', 'Stampa digitale'],
    sameAs: sameAs.length ? sameAs : undefined,
  };
  const ind = a.indirizzo || {};
  if (val(ind.via) || val(ind.cap)) {
    node.address = {
      '@type': 'PostalAddress',
      streetAddress: val(ind.via),
      addressLocality: ind.citta,
      addressRegion: ind.provincia,
      postalCode: val(ind.cap),
      addressCountry: ind.nazione,
    };
  }
  if (val(ind.latitudine) && val(ind.longitudine)) {
    node.geo = { '@type': 'GeoCoordinates', latitude: ind.latitudine, longitude: ind.longitudine };
  }
  if (a.orari && a.orari.length) {
    node.openingHoursSpecification = a.orari.map((o) => ({
      '@type': 'OpeningHoursSpecification', dayOfWeek: o.giorni, opens: o.apre, closes: o.chiude,
    }));
  }
  return node;
}

function websiteNode(cfg, siteUrl) {
  return {
    '@type': 'WebSite',
    '@id': `${siteUrl}/#website`,
    url: `${siteUrl}/`,
    name: cfg.azienda.nome,
    description: cfg.azienda.descrizioneBreve,
    inLanguage: 'it-IT',
    publisher: { '@id': `${siteUrl}/#organization` },
    potentialAction: {
      '@type': 'SearchAction',
      target: { '@type': 'EntryPoint', urlTemplate: `${siteUrl}/?s={search_term_string}` },
      'query-input': 'required name=search_term_string',
    },
  };
}

function personNode(cfg, siteUrl, autore) {
  const nome = [val(autore.nome), val(autore.cognome)].filter(Boolean).join(' ') || cfg.azienda.nome;
  return {
    '@type': 'Person',
    '@id': `${siteUrl}/#/schema/person/${encodeURIComponent(nome.toLowerCase().replace(/\s+/g, '-'))}`,
    name: nome,
    jobTitle: val(autore.ruolo),
    description: val(autore.bio),
    worksFor: { '@id': `${siteUrl}/#organization` },
    sameAs: [val(autore.linkedin)].filter(Boolean),
  };
}

function breadcrumbNode(siteUrl, doc) {
  const items = [{ name: 'Home', item: `${siteUrl}/` }];
  if (doc.type === 'post' && doc.categories[0]) {
    items.push({ name: doc.categories[0].name, item: `${siteUrl}/category/${doc.categories[0].slug}/` });
  }
  items.push({ name: doc.title, item: doc.link });
  return {
    '@type': 'BreadcrumbList',
    '@id': `${doc.link}#breadcrumb`,
    itemListElement: items.map((it, i) => ({ '@type': 'ListItem', position: i + 1, name: it.name, item: it.item })),
  };
}

/** Coppie domanda/risposta estratte dai titoli di sezione interrogativi. */
function extractFaq(doc) {
  const out = [];
  const hs = doc.headings.filter((h) => h.level >= 2 && h.text.includes('?'));
  for (const h of hs) {
    const after = doc.content.slice(h.index + h.raw.length);
    const p = (after.match(/<p[^>]*>([\s\S]*?)<\/p>/i) || [])[1];
    if (!p) continue;
    const risposta = p.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    if (risposta.split(' ').length < 8) continue;
    out.push({ domanda: h.text.trim(), risposta: risposta.slice(0, 600) });
  }
  return out;
}

function faqNode(doc, faq) {
  return {
    '@type': 'FAQPage',
    '@id': `${doc.link}#faq`,
    mainEntity: faq.map((f) => ({
      '@type': 'Question',
      name: f.domanda,
      acceptedAnswer: { '@type': 'Answer', text: f.risposta },
    })),
  };
}

function articleNode(cfg, siteUrl, doc, autore) {
  const nome = [val(autore.nome), val(autore.cognome)].filter(Boolean).join(' ') || cfg.azienda.nome;
  return {
    '@type': 'BlogPosting',
    '@id': `${doc.link}#article`,
    headline: doc.seoTitle || doc.title,
    description: doc.seoDesc,
    inLanguage: 'it-IT',
    datePublished: (doc.date || '').replace(' ', 'T') + '+02:00',
    dateModified: (doc.modified || doc.date || '').replace(' ', 'T') + '+02:00',
    author: { '@id': `${siteUrl}/#/schema/person/${encodeURIComponent(nome.toLowerCase().replace(/\s+/g, '-'))}` },
    publisher: { '@id': `${siteUrl}/#organization` },
    mainEntityOfPage: { '@id': doc.link },
    articleSection: (doc.categories[0] || {}).name,
    keywords: [doc.focusKeyword].filter(Boolean),
    wordCount: doc.words,
    // speakable: indica ad assistenti vocali e riassunti AI cosa leggere.
    speakable: { '@type': 'SpeakableSpecification', cssSelector: ['h1', '.entry-summary', '.mdi-in-breve'] },
  };
}

function serviceNode(cfg, siteUrl, doc) {
  return {
    '@type': 'Service',
    '@id': `${doc.link}#service`,
    name: doc.title,
    serviceType: doc.focusKeyword || doc.title,
    description: doc.seoDesc,
    provider: { '@id': `${siteUrl}/#organization` },
    areaServed: (cfg.azienda.areaServita || []).map((x) => ({ '@type': 'City', name: x })),
    audience: { '@type': 'BusinessAudience', name: 'PMI e professionisti' },
    offers: {
      '@type': 'Offer',
      availability: 'https://schema.org/InStock',
      priceCurrency: 'EUR',
      url: doc.link,
    },
  };
}

/** Rimuove chiavi nulle/vuote: uno schema con campi vuoti genera warning. */
function clean(obj) {
  if (Array.isArray(obj)) return obj.map(clean).filter((x) => x !== undefined && x !== null);
  if (obj && typeof obj === 'object') {
    const out = {};
    for (const [k, v] of Object.entries(obj)) {
      const c = clean(v);
      if (c === undefined || c === null || (Array.isArray(c) && !c.length)) continue;
      out[k] = c;
    }
    return out;
  }
  return obj;
}

function buildSchemaFor(site, cfg, doc) {
  const siteUrl = site.url.replace(/\/$/, '');
  const autore = (cfg.autori || [])[0] || {};
  const graph = [organizationNode(cfg, siteUrl), websiteNode(cfg, siteUrl), personNode(cfg, siteUrl, autore), breadcrumbNode(siteUrl, doc)];

  const isService = /servizi|realizzazione|gestione|produzione|sviluppo|marketing|design|naming|stampe/i.test(doc.slug) && doc.type === 'page';
  if (isService) graph.push(serviceNode(cfg, siteUrl, doc));
  if (doc.type === 'post') graph.push(articleNode(cfg, siteUrl, doc, autore));

  const faq = extractFaq(doc);
  if (faq.length >= 2) graph.push(faqNode(doc, faq));

  return clean({ '@context': 'https://schema.org', '@graph': graph });
}

function buildSchemaPlan(site, cfg) {
  const out = site.published.map((doc) => ({
    path: doc.path,
    url: doc.link,
    tipi: [],
    schema: buildSchemaFor(site, cfg, doc),
  }));
  out.forEach((o) => { o.tipi = o.schema['@graph'].map((n) => (Array.isArray(n['@type']) ? n['@type'].join('+') : n['@type'])); });
  return out;
}

module.exports = { buildSchemaPlan, buildSchemaFor, extractFaq, organizationNode, websiteNode, clean };
