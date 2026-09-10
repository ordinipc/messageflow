'use strict';
const { SEV, docIssue, siteIssue } = require('./_helpers');

module.exports = [
  {
    id: 'TEC-01', category: 'technical', severity: SEV.critical, auto: false,
    title: 'Due plugin SEO attivi contemporaneamente (Rank Math + Yoast)',
    why: 'Entrambi stampano title, meta description, canonical, Open Graph e schema: si generano tag duplicati e in conflitto e Google può scegliere quello sbagliato. È anche doppio carico sul server.',
    fix: 'Scegliere Rank Math (i dati sono più completi), migrare i dati residui di Yoast, disinstallare Yoast e ripulire il postmeta _yoast_*.',
    check: (s) => {
      const rm = s.published.filter((d) => d.meta.rank_math_title).length;
      const yo = s.published.filter((d) => d.meta._yoast_wpseo_title || d.meta._yoast_wpseo_metadesc).length;
      if (rm && yo) return [siteIssue(`Rank Math su ${rm} contenuti e Yoast su ${yo} contenuti: entrambi installati`)];
      return [];
    },
  },
  {
    id: 'TEC-02', category: 'technical', severity: SEV.high, auto: true,
    title: 'Direttiva robots non impostata esplicitamente',
    why: 'Senza direttiva esplicita il comportamento dipende dalle impostazioni globali del plugin: pagine di servizio possono finire indicizzate e diluire la qualità del sito.',
    fix: 'Il plugin imposta index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1 sui contenuti utili e noindex su pagine di servizio.',
    check: (s) => s.published.filter((d) => !d.robots).map((d) => docIssue(d, 'nessun meta robots salvato')),
  },
  {
    id: 'TEC-03', category: 'technical', severity: SEV.high, auto: true,
    title: 'max-image-preview:large assente',
    why: 'Senza questa direttiva Google mostra miniature piccole e la pagina non è eleggibile per Google Discover.',
    fix: 'Aggiunta automatica nella meta robots dal plugin.',
    check: (s) => s.published.filter((d) => !/max-image-preview/.test(d.robots)).map((d) => docIssue(d, 'direttiva assente')),
  },
  {
    id: 'TEC-04', category: 'technical', severity: SEV.medium, auto: true,
    title: 'Canonical non dichiarato esplicitamente',
    why: 'Il canonical esplicito protegge da duplicazioni generate da parametri UTM, paginazione e varianti con/senza slash.',
    fix: 'Il plugin stampa un canonical assoluto e autoreferenziale su ogni URL.',
    check: (s) => s.published.filter((d) => !d.canonical).map((d) => docIssue(d, 'canonical non impostato')),
  },
  {
    id: 'TEC-05', category: 'technical', severity: SEV.medium, auto: false,
    title: 'Pagine di servizio indicizzabili (privacy, cookie, grazie)',
    why: 'Pagine legali o di ringraziamento indicizzate abbassano la qualità media del sito e sprecano crawl budget.',
    fix: 'Impostare noindex,follow su queste pagine (incluso nel plugin).',
    check: (s) => s.published.filter((d) => /privacy|cookie|grazie|thank|termini|condizioni/i.test(d.slug) && !d.noindex)
      .map((d) => docIssue(d, 'indicizzabile, dovrebbe essere noindex')),
  },
  {
    id: 'TEC-06', category: 'technical', severity: SEV.medium, auto: false,
    title: 'Contenuti nel cestino mai eliminati definitivamente',
    why: 'Le pagine in cestino restano nel database, gonfiano le query e in caso di ripristino accidentale creano URL duplicati.',
    fix: 'Svuotare il cestino dopo aver verificato che non servano redirect 301 dai vecchi URL.',
    check: (s) => s.trashed.map((d) => docIssue(d, `in cestino dal ${(d.modified || '').slice(0, 10)}`)),
  },
  {
    id: 'TEC-07', category: 'technical', severity: SEV.medium, auto: false,
    title: 'Pagine chiave assenti (Chi siamo, Contatti)',
    why: 'Sono le pagine che Google usa per valutare l’affidabilità (E-E-A-T) e le uniche a intercettare le ricerche di brand navigazionali. Qui esistono solo come ancore della home o nel cestino.',
    fix: 'Il fixer genera i template HTML pronti per /chi-siamo/ e /contatti/ con dati aziendali e schema.',
    check: (s) => {
      const need = [
        { slug: 'chi-siamo', label: 'Chi siamo', re: /chi-siamo|about|azienda|team/i },
        { slug: 'contatti', label: 'Contatti', re: /contatt|contact/i },
      ];
      return need.filter((n) => !s.pages.some((p) => n.re.test(p.slug)))
        .map((n) => siteIssue(`pagina "${n.label}" (/${n.slug}/) non presente fra le pagine pubblicate`, { value: n.slug }));
    },
  },
  {
    id: 'TEC-08', category: 'technical', severity: SEV.low, auto: false,
    title: 'Percentuale alta di pagine costruite con page builder',
    why: 'Elementor genera markup annidato e CSS pesante: impatta LCP e INP, due Core Web Vitals usati come segnali di ranking.',
    fix: 'Attivare CSS/JS ottimizzati di Elementor, disattivare i widget inutilizzati, usare cache e critical CSS.',
    check: (s) => {
      const n = s.published.filter((d) => d.isElementor).length;
      const p = Math.round((n / Math.max(1, s.published.length)) * 100);
      return p > 50 ? [siteIssue(`${n} contenuti su ${s.published.length} (${p}%) generati con Elementor`)] : [];
    },
  },
];
