'use strict';
const { SEV, docIssue, siteIssue } = require('./_helpers');

module.exports = [
  {
    id: 'TAX-01', category: 'taxonomy', severity: SEV.high, auto: false,
    title: 'Categoria sovraccarica: quasi tutti gli articoli nella stessa',
    why: 'Una categoria che raccoglie l’80% degli articoli non crea alcun cluster tematico: l’archivio è inutile per l’utente e non aiuta Google a capire i topic del sito.',
    fix: 'Ricategorizzazione automatica proposta dal fixer in base alle keyword del testo.',
    check: (s) => {
      const counts = new Map();
      s.posts.forEach((d) => d.categories.forEach((c) => counts.set(c.name, (counts.get(c.name) || 0) + 1)));
      const total = s.posts.length;
      return [...counts.entries()].filter(([, n]) => n / total > 0.5)
        .map(([name, n]) => siteIssue(`categoria "${name}": ${n} articoli su ${total} (${Math.round((n / total) * 100)}%)`, { value: name }));
    },
  },
  {
    id: 'TAX-02', category: 'taxonomy', severity: SEV.medium, auto: false,
    title: 'Nessun tag utilizzato',
    why: 'Senza tag mancano le pagine di archivio tematiche che possono intercettare query di coda lunga e collegare articoli correlati.',
    fix: 'Set di tag proposto dal fixer a partire dalle entità ricorrenti nei testi (max 5 per articolo).',
    check: (s) => (s.tags.length === 0 ? [siteIssue('0 tag definiti sul sito')] : []),
  },
  {
    id: 'TAX-03', category: 'taxonomy', severity: SEV.medium, auto: false,
    title: 'Categorie fuori tema rispetto al contenuto',
    why: 'Articoli su social media o video classificati come "E-commerce" mandano segnali contraddittori sull’argomento della pagina e degli archivi.',
    fix: 'Il fixer propone per ogni articolo la categoria coerente con le keyword dominanti.',
    check: (s) => {
      const map = {
        'E-commerce': /e-?commerce|shop online|negozio online|carrello|vendita online/i,
        Marketing: /marketing|social|campagn|advertis|ads|brand|content/i,
        'Web development': /sito web|siti web|sviluppo|wordpress|landing|hosting/i,
        'Cyber Security': /sicurezza|cyber|attacco|malware|phishing|backup/i,
        Software: /software|gestionale|crm|app|automazione/i,
      };
      const out = [];
      s.posts.forEach((d) => {
        const cat = (d.categories[0] || {}).name;
        if (!cat || !map[cat]) return;
        const hay = `${d.title} ${d.text.slice(0, 1500)}`;
        if (!map[cat].test(hay)) {
          const better = Object.entries(map).find(([, re]) => re.test(hay));
          out.push(docIssue(d, `categoria "${cat}" non coerente${better ? `, suggerita "${better[0]}"` : ''}`, { suggested: better ? better[0] : '' }));
        }
      });
      return out;
    },
  },
];
