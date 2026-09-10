'use strict';
const { SEV, docIssue, siteIssue } = require('./_helpers');

module.exports = [
  {
    id: 'LNK-01', category: 'links', severity: SEV.critical, auto: true,
    title: 'Pagine orfane: nessun link interno in entrata',
    why: 'Una pagina che nessun’altra linka riceve pochissimo PageRank interno e viene scansionata di rado: è la causa numero uno di articoli mai posizionati.',
    fix: 'Il fixer genera una mappa di link interni (keyword → URL) applicata automaticamente dal plugin.',
    check: (s) => s.published.filter((d) => s.inboundCount(d.path) === 0 && d.slug !== 'home')
      .map((d) => docIssue(d, '0 link interni in entrata')),
  },
  {
    id: 'LNK-02', category: 'links', severity: SEV.high, auto: true,
    title: 'Nessun link interno in uscita',
    why: 'Senza link in uscita l’articolo è un vicolo cieco: non distribuisce autorità e non guida l’utente verso le pagine servizio che convertono.',
    fix: 'Inserimento automatico di 3-5 link contestuali per articolo verso pagine pilastro e correlati.',
    check: (s) => s.published.filter((d) => d.internalLinks.length === 0 && d.words > 150)
      .map((d) => docIssue(d, '0 link interni in uscita')),
  },
  {
    id: 'LNK-03', category: 'links', severity: SEV.medium, auto: false,
    title: 'Anchor text generico o non descrittivo',
    why: 'Anchor come "clicca qui" o "leggi di più" non trasmettono rilevanza semantica al link.',
    fix: 'Sostituire con anchor che descrivono la pagina di destinazione.',
    check: (s) => {
      const bad = /^(clicca qui|qui|leggi di più|leggi di piu|scopri di più|scopri di piu|link|questo link|continua|vai)$/i;
      const out = [];
      s.published.forEach((d) => {
        const n = d.links.filter((l) => bad.test((l.anchor || '').trim())).length;
        if (n) out.push(docIssue(d, `${n} anchor generici`));
      });
      return out;
    },
  },
  {
    id: 'LNK-04', category: 'links', severity: SEV.high, auto: true,
    title: 'Link esterni in dofollow che disperdono autorità',
    why: 'Centinaia di link dofollow verso profili social e aggregatori trasferiscono fuori il valore del dominio senza ritorno SEO.',
    fix: 'Il plugin applica rel="nofollow sponsored" ai domini configurati e target _blank sicuro.',
    check: (s) => {
      const counts = new Map();
      s.published.forEach((d) => {
        d.externalLinks.forEach((l) => {
          if (/nofollow|ugc|sponsored/i.test(l.rel)) return;
          const host = (l.href.match(/^https?:\/\/([^/]+)/i) || [])[1] || '';
          if (!host) return;
          counts.set(host, (counts.get(host) || 0) + 1);
        });
      });
      return [...counts.entries()].sort((a, b) => b[1] - a[1])
        .map(([host, n]) => siteIssue(`${n} link dofollow verso ${host}`, { value: host, count: n }));
    },
  },
  {
    id: 'LNK-05', category: 'links', severity: SEV.medium, auto: false,
    title: 'Link esterni senza rel di sicurezza su target _blank',
    why: 'target="_blank" senza rel="noopener" espone a reverse tabnabbing e viene segnalato dagli audit di sicurezza.',
    fix: 'Aggiunta automatica di rel="noopener noreferrer" dal plugin.',
    check: (s) => {
      const out = [];
      s.published.forEach((d) => {
        const n = d.links.filter((l) => l.target === '_blank' && !/noopener/i.test(l.rel)).length;
        if (n) out.push(docIssue(d, `${n} link _blank senza noopener`));
      });
      return out;
    },
  },
  {
    id: 'LNK-06', category: 'links', severity: SEV.high, auto: false,
    title: 'Il blog non è raggiungibile dal menu principale',
    why: 'Se gli articoli non sono linkati dalla navigazione, il crawler li scopre solo dalla sitemap: profondità di click alta e scansione rara.',
    fix: 'Aggiungere al menu una voce Blog/Risorse e sezioni "articoli correlati" nelle pagine servizio.',
    check: (s) => {
      const hasBlog = s.menuItems.some((m) => /blog|news|risorse|magazine|articoli/i.test(m.title || '') || /blog|news/i.test(m.url || ''));
      return hasBlog ? [] : [siteIssue(`menu con ${s.menuItems.length} voci, nessuna porta al blog (${s.posts.length} articoli non navigabili)`)];
    },
  },
  {
    id: 'LNK-07', category: 'links', severity: SEV.high, auto: false,
    title: 'Menu costruito su ancore della home invece che su pagine reali',
    why: 'Voci come /#contatti non creano URL indicizzabili: il sito perde pagine posizionabili per query come "contatti" o "chi siamo".',
    fix: 'Creare pagine autonome (/chi-siamo/, /contatti/) e collegarle nel menu.',
    check: (s) => s.menuItems.filter((m) => /^\/?#/.test(m.url || '') || (m.url || '').includes('/#'))
      .map((m) => siteIssue(`voce di menu "${m.title}" punta a ${m.url}`, { value: m.url })),
  },
];
