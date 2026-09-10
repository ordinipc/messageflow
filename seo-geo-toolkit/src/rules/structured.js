'use strict';
const { SEV, docIssue, siteIssue } = require('./_helpers');

module.exports = [
  {
    id: 'SCH-01', category: 'structured', severity: SEV.critical, auto: true,
    title: 'Nessun dato strutturato personalizzato (JSON-LD) nei contenuti',
    why: 'Senza schema Google non ottiene entità esplicite: niente rich result, niente knowledge panel e i motori generativi faticano ad attribuire le informazioni al brand.',
    fix: 'Il plugin inietta Organization/ProfessionalService, WebSite+SearchAction, BreadcrumbList, Article, FAQPage, Service e Person.',
    check: (s) => s.published.filter((d) => !d.hasJsonLd).map((d) => docIssue(d, 'nessun JSON-LD nel contenuto')),
  },
  {
    id: 'SCH-02', category: 'structured', severity: SEV.high, auto: true,
    title: 'FAQPage assente su pagine che contengono domande',
    why: 'Le pagine con domande nei titoli sono candidate naturali a FAQ rich result e a essere citate come risposta diretta dagli assistenti AI.',
    fix: 'Il plugin genera FAQPage automaticamente dalle coppie domanda/risposta trovate negli H2-H3.',
    check: (s) => s.published.filter((d) => !d.hasJsonLd && d.headings.some((h) => h.text.includes('?')))
      .map((d) => docIssue(d, `${d.headings.filter((h) => h.text.includes('?')).length} domande nei titoli, nessun FAQPage`)),
  },
  {
    id: 'SCH-03', category: 'structured', severity: SEV.high, auto: true,
    title: 'BreadcrumbList assente',
    why: 'I breadcrumb migliorano la comprensione della gerarchia del sito e sostituiscono l’URL nello snippet, aumentando il CTR.',
    fix: 'Breadcrumb + JSON-LD BreadcrumbList generati dal plugin.',
    check: (s) => s.published.filter((d) => !/BreadcrumbList/i.test(d.content)).map((d) => docIssue(d, 'nessun BreadcrumbList')),
  },
  {
    id: 'SCH-04', category: 'structured', severity: SEV.high, auto: true,
    title: 'Nessuno schema Service sulle pagine servizio',
    why: 'Le pagine servizio senza schema Service/Offer non comunicano cosa vendi, dove e a chi: informazione chiave sia per il local pack sia per le risposte AI.',
    fix: 'Schema Service con areaServed, provider e offerta generato per ogni pagina servizio.',
    check: (s) => s.pages.filter((d) => /servizi|realizzazione|gestione|produzione|sviluppo|marketing|design|naming|stampe/i.test(d.slug))
      .filter((d) => !/"@type"\s*:\s*"Service"/i.test(d.content))
      .map((d) => docIssue(d, 'schema Service assente')),
  },
  {
    id: 'SCH-05', category: 'structured', severity: SEV.medium, auto: true,
    title: 'Open Graph e Twitter Card non verificabili nell’export',
    why: 'Senza og:title/og:description/og:image le condivisioni social perdono anteprima e CTR, e alcuni crawler AI usano proprio l’Open Graph come riassunto.',
    fix: 'Il plugin stampa Open Graph e Twitter Card completi con fallback su immagine brandizzata.',
    check: (s) => {
      const withOg = s.published.filter((d) => d.meta.rank_math_facebook_title || d.meta._yoast_wpseo_opengraph_title).length;
      return withOg === 0 ? [siteIssue(`nessuno dei ${s.published.length} contenuti ha Open Graph personalizzato`)] : [];
    },
  },
];
