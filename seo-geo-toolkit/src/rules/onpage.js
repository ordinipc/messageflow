'use strict';
const { SEV, docIssue, T } = require('./_helpers');

const TITLE_MIN = 30, TITLE_MAX = 60, DESC_MIN = 120, DESC_MAX = 158;

module.exports = [
  {
    id: 'ONP-01', category: 'onpage', severity: SEV.high, auto: true,
    title: 'Title SEO troppo lungo (viene troncato in SERP)',
    why: 'Oltre ~60 caratteri Google tronca il title con "…": il messaggio e la keyword finale si perdono e il CTR cala.',
    fix: 'Riscrittura automatica entro 60 caratteri mantenendo focus keyword a inizio titolo + brand.',
    check: (s) => s.published.filter((d) => d.seoTitle.length > TITLE_MAX)
      .map((d) => docIssue(d, `${d.seoTitle.length} caratteri`, { value: d.seoTitle })),
  },
  {
    id: 'ONP-02', category: 'onpage', severity: SEV.medium, auto: true,
    title: 'Title SEO troppo corto (spazio SERP sprecato)',
    why: 'Sotto i 30 caratteri si perde spazio utile per keyword secondarie e qualificatori geografici.',
    fix: 'Estensione automatica con keyword + località + brand.',
    check: (s) => s.published.filter((d) => d.seoTitle && d.seoTitle.length < TITLE_MIN)
      .map((d) => docIssue(d, `${d.seoTitle.length} caratteri`, { value: d.seoTitle })),
  },
  {
    id: 'ONP-03', category: 'onpage', severity: SEV.high, auto: true,
    title: 'Meta description fuori lunghezza ottimale',
    why: 'Sopra ~158 caratteri viene troncata, sotto 120 non sfrutta lo snippet: in entrambi i casi si perde CTR.',
    fix: 'Riscrittura automatica a 140-158 caratteri con keyword, beneficio e call to action.',
    check: (s) => s.published.filter((d) => d.seoDesc && (d.seoDesc.length > DESC_MAX || d.seoDesc.length < DESC_MIN))
      .map((d) => docIssue(d, `${d.seoDesc.length} caratteri`, { value: d.seoDesc })),
  },
  {
    id: 'ONP-04', category: 'onpage', severity: SEV.critical, auto: true,
    title: 'Meta description mancante',
    why: 'Senza description Google genera uno snippet arbitrario dal testo, spesso incoerente con l’intento di ricerca.',
    fix: 'Generazione automatica dal primo paragrafo + focus keyword.',
    check: (s) => s.published.filter((d) => !d.seoDesc).map((d) => docIssue(d, 'assente')),
  },
  {
    id: 'ONP-05', category: 'onpage', severity: SEV.high, auto: true,
    title: 'Focus keyword assente dal title SEO',
    why: 'La keyword nel title è ancora uno dei segnali on-page più forti per il ranking e per la rilevanza percepita.',
    fix: 'Il title rigenerato inserisce la focus keyword nei primi 30 caratteri.',
    check: (s) => s.published.filter((d) => d.focusKeyword && d.seoTitle
      && !d.seoTitle.toLowerCase().includes(d.focusKeyword.toLowerCase()))
      .map((d) => docIssue(d, `keyword "${d.focusKeyword}" non presente`, { value: d.seoTitle })),
  },
  {
    id: 'ONP-06', category: 'onpage', severity: SEV.high, auto: false,
    title: 'Focus keyword duplicata su più pagine (cannibalizzazione)',
    why: 'Più URL che competono per la stessa query si tolgono forza a vicenda: Google non capisce quale posizionare.',
    fix: 'Consolidare in una pagina pilastro e differenziare le altre su varianti long-tail; redirect 301 dove il contenuto è ridondante.',
    check: (s) => {
      const map = new Map();
      s.published.forEach((d) => {
        const k = (d.focusKeyword || '').toLowerCase().trim();
        if (!k) return;
        if (!map.has(k)) map.set(k, []);
        map.get(k).push(d);
      });
      const out = [];
      for (const [k, docs] of map) {
        if (docs.length < 2) continue;
        docs.forEach((d) => out.push(docIssue(d, `keyword "${k}" condivisa con altre ${docs.length - 1} pagine`, { group: k, competitors: docs.map((x) => x.path) })));
      }
      return out;
    },
  },
  {
    id: 'ONP-07', category: 'onpage', severity: SEV.medium, auto: true,
    title: 'Slug URL troppo lungo',
    why: 'URL lunghi sono meno cliccabili, si troncano in SERP e diluiscono il peso delle keyword.',
    fix: 'Slug accorciato a max 60 caratteri senza stopword + redirect 301 generato automaticamente.',
    check: (s) => s.published.filter((d) => d.slug.length > 60)
      .map((d) => docIssue(d, `${d.slug.length} caratteri`, { value: d.slug, suggested: T.shortSlug(d.focusKeyword || d.title) })),
  },
  {
    id: 'ONP-08', category: 'onpage', severity: SEV.medium, auto: false,
    title: 'H1 non rilevabile nel contenuto',
    why: 'Se il tema non stampa un H1 unico, il documento perde il principale segnale di argomento. Va verificato sul front-end.',
    fix: 'Verificare il template; nelle pagine Elementor impostare il titolo principale come H1 (uno solo per pagina).',
    check: (s) => s.published.filter((d) => d.h1.length === 0 && d.words > 100)
      .map((d) => docIssue(d, 'nessun tag H1 nel contenuto salvato')),
  },
  {
    id: 'ONP-09', category: 'onpage', severity: SEV.high, auto: false,
    title: 'H1 multipli nella stessa pagina',
    why: 'Più H1 confondono la gerarchia semantica e diluiscono il topic principale.',
    fix: 'Lasciare un solo H1 e declassare gli altri a H2.',
    check: (s) => s.published.filter((d) => d.h1.length > 1)
      .map((d) => docIssue(d, `${d.h1.length} tag H1`, { value: d.h1.map((h) => h.text).join(' | ') })),
  },
  {
    id: 'ONP-10', category: 'onpage', severity: SEV.high, auto: true,
    title: 'Nessun H2: contenuto senza struttura',
    why: 'Senza sottotitoli il testo non è scansionabile, non genera featured snippet e i motori generativi non trovano blocchi citabili.',
    fix: 'Il piano di ristrutturazione genera una scaletta H2/H3 per ogni articolo (incluse domande frequenti).',
    check: (s) => s.published.filter((d) => d.h2.length === 0 && d.words > 250)
      .map((d) => docIssue(d, `0 H2 su ${d.words} parole`)),
  },
  {
    id: 'ONP-11', category: 'onpage', severity: SEV.low, auto: false,
    title: 'Gerarchia dei titoli saltata (es. H2 -> H4)',
    why: 'Salti di livello rompono l’outline semantico usato da crawler e screen reader.',
    fix: 'Riordinare i livelli in sequenza.',
    check: (s) => {
      const out = [];
      s.published.forEach((d) => {
        let prev = 0, bad = 0;
        d.headings.forEach((h) => { if (prev && h.level > prev + 1) bad++; prev = h.level; });
        if (bad) out.push(docIssue(d, `${bad} salti di livello`));
      });
      return out;
    },
  },
  {
    id: 'ONP-12', category: 'onpage', severity: SEV.medium, auto: true,
    title: 'Riassunto (excerpt) mancante',
    why: 'L’excerpt alimenta archivi, feed RSS, anteprime social e fallback della meta description.',
    fix: 'Generazione automatica di un estratto di 25-35 parole con la focus keyword.',
    check: (s) => s.published.filter((d) => !d.excerpt).map((d) => docIssue(d, 'assente')),
  },
];
