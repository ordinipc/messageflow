'use strict';
const { SEV, docIssue, siteIssue, T } = require('./_helpers');

module.exports = [
  {
    id: 'CNT-01', category: 'content', severity: SEV.critical, auto: false,
    title: 'Contenuto molto scarno (thin content, < 300 parole)',
    why: 'Sotto le 300 parole la pagina raramente soddisfa un intento di ricerca informazionale: Google la considera di scarso valore e spesso non la indicizza affatto.',
    fix: 'Il piano di riscrittura indica per ogni pagina la scaletta da sviluppare fino a 900-1.500 parole utili.',
    check: (s) => s.published.filter((d) => d.words < 300 && !/privacy|cookie|termini|grazie/i.test(d.slug))
      .map((d) => docIssue(d, `${d.words} parole`)),
  },
  {
    id: 'CNT-02', category: 'content', severity: SEV.high, auto: false,
    title: 'Contenuto sotto la soglia competitiva (< 600 parole)',
    why: 'Per query commerciali le pagine in prima pagina superano quasi sempre le 900 parole: con meno testo mancano le entità e le domande che i motori cercano.',
    fix: 'Espansione guidata: sezioni H2 aggiuntive, FAQ, caso studio, dati locali.',
    check: (s) => s.published.filter((d) => d.words >= 300 && d.words < 600)
      .map((d) => docIssue(d, `${d.words} parole`)),
  },
  {
    id: 'CNT-03', category: 'content', severity: SEV.critical, auto: false,
    title: 'Contenuti quasi duplicati fra loro',
    why: 'Testi sovrapposti generano cannibalizzazione e segnalano contenuto generato in serie: Google ne indicizza uno solo e svaluta il resto del sito.',
    fix: 'Unire in un unico articolo approfondito e impostare 301 dalle versioni ridondanti.',
    check: (s) => {
      const docs = s.published.filter((d) => d.words > 120);
      const shings = docs.map((d) => ({ d, sh: T.shingles(d.text) }));
      const out = [];
      for (let i = 0; i < shings.length; i++) {
        for (let j = i + 1; j < shings.length; j++) {
          const sim = T.jaccard(shings[i].sh, shings[j].sh);
          if (sim >= 0.28) {
            out.push(docIssue(shings[i].d, `similarità ${Math.round(sim * 100)}% con /${shings[j].d.slug}/`, { duplicateOf: shings[j].path || shings[j].d.path, similarity: sim }));
          }
        }
      }
      return out;
    },
  },
  {
    id: 'CNT-04', category: 'content', severity: SEV.high, auto: false,
    title: 'Contenuti non aggiornati da oltre 12 mesi',
    why: 'La freschezza è un fattore di ranking per query in evoluzione (marketing, social, AI) e i motori generativi preferiscono citare fonti recenti.',
    fix: 'Piano di refresh: aggiornare dati, anno nel titolo, esempi e data di modifica.',
    check: (s) => {
      const now = Date.now();
      return s.published.filter((d) => {
        const t = Date.parse((d.modified || d.date || '').replace(' ', 'T') + 'Z');
        return t && (now - t) > 365 * 864e5;
      }).map((d) => {
        const t = Date.parse((d.modified || d.date).replace(' ', 'T') + 'Z');
        return docIssue(d, `ultimo aggiornamento ${(d.modified || d.date).slice(0, 10)} (${Math.round((now - t) / 864e5)} giorni fa)`);
      });
    },
  },
  {
    id: 'CNT-05', category: 'content', severity: SEV.medium, auto: false,
    title: 'Buco di pubblicazione: frequenza non costante',
    why: 'Lunghi periodi senza pubblicazioni riducono la frequenza di scansione di Googlebot e la percezione di sito attivo.',
    fix: 'Calendario editoriale generato con cadenza settimanale e cluster tematici.',
    check: (s) => {
      const months = new Map();
      s.posts.forEach((d) => { const m = (d.date || '').slice(0, 7); if (m) months.set(m, (months.get(m) || 0) + 1); });
      const keys = [...months.keys()].sort();
      if (keys.length < 2) return [];
      const gaps = [];
      for (let i = 1; i < keys.length; i++) {
        const a = new Date(keys[i - 1] + '-01'), b = new Date(keys[i] + '-01');
        const diff = (b.getFullYear() - a.getFullYear()) * 12 + (b.getMonth() - a.getMonth());
        if (diff > 2) gaps.push(siteIssue(`nessuna pubblicazione tra ${keys[i - 1]} e ${keys[i]} (${diff - 1} mesi di silenzio)`));
      }
      return gaps;
    },
  },
  {
    id: 'CNT-06', category: 'content', severity: SEV.medium, auto: false,
    title: 'Leggibilità bassa (indice Gulpease < 50)',
    why: 'Testi difficili aumentano la frequenza di rimbalzo e riducono il tempo di permanenza, due segnali comportamentali correlati al posizionamento.',
    fix: 'Frasi sotto le 25 parole, paragrafi da 2-3 frasi, elenchi puntati.',
    check: (s) => s.published.filter((d) => d.gulpease !== null && d.gulpease < 50 && d.words > 200)
      .map((d) => docIssue(d, `Gulpease ${d.gulpease}/100`)),
  },
  {
    id: 'CNT-07', category: 'content', severity: SEV.low, auto: true,
    title: 'CSS inline dentro il contenuto dell’articolo',
    why: 'Il CSS finito nel campo contenuto viene indicizzato come testo, abbassa il rapporto testo/codice e può comparire negli snippet.',
    fix: 'Spostamento in file di stile o in Elementor Custom CSS (il fixer estrae i blocchi trovati).',
    check: (s) => s.published.filter((d) => d.hasInlineStyle)
      .map((d) => docIssue(d, 'blocco <style> nel contenuto')),
  },
  {
    id: 'CNT-08', category: 'content', severity: SEV.medium, auto: false,
    title: 'Nessun elemento visuale o strutturato (liste, tabelle, immagini)',
    why: 'Blocchi di testo continuo non producono featured snippet e sono difficili da estrarre per le risposte generative.',
    fix: 'Aggiungere almeno un elenco puntato, una tabella comparativa o un’immagine con didascalia per articolo.',
    check: (s) => s.published.filter((d) => d.words > 300 && d.lists === 0 && d.tables === 0 && d.images.length === 0)
      .map((d) => docIssue(d, 'nessuna lista, tabella o immagine')),
  },
];
