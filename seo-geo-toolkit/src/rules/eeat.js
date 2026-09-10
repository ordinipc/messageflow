'use strict';
const { SEV, docIssue, siteIssue } = require('./_helpers');

module.exports = [
  {
    id: 'EAT-01', category: 'eeat', severity: SEV.high, auto: true,
    title: 'Nessuna biografia autore collegata ai contenuti',
    why: 'Per i temi YMYL e per i servizi professionali Google valuta chi scrive: senza author box e schema Person manca il segnale di competenza.',
    fix: 'Author box + schema Person con ruolo, esperienza e profili verificabili generati dal plugin.',
    check: (s) => {
      const has = s.published.some((d) => /"@type"\s*:\s*"Person"/i.test(d.content));
      return has ? [] : [siteIssue('nessuno schema Person / author box rilevato')];
    },
  },
  {
    id: 'EAT-02', category: 'eeat', severity: SEV.high, auto: false,
    title: 'Nessun caso studio o portfolio verificabile',
    why: 'L’esperienza dimostrata (la prima E di E-E-A-T) per un’agenzia si prova con lavori reali, risultati misurati e clienti citabili.',
    fix: 'Pagina Portfolio con 5-8 casi studio (problema, intervento, risultato numerico) e schema CreativeWork.',
    check: (s) => {
      const has = s.pages.some((p) => /portfolio|case-stud|casi-studio|lavori|progetti/i.test(p.slug));
      return has ? [] : [siteIssue('nessuna pagina portfolio o casi studio pubblicata')];
    },
  },
  {
    id: 'EAT-03', category: 'eeat', severity: SEV.medium, auto: false,
    title: 'Contenuti con tratti di generazione automatica non revisionata',
    why: 'Testi molto uniformi per lunghezza e struttura, senza dati, esempi o firme, rientrano nei contenuti "scalati" che le linee guida antispam di Google penalizzano.',
    fix: 'Revisione umana: aggiungere esperienza diretta, dati propri, esempi di clienti reali, foto originali.',
    check: (s) => {
      const posts = s.posts.filter((d) => d.words > 100);
      if (posts.length < 20) return [];
      const suspicious = posts.filter((d) => d.images.length === 0 && d.internalLinks.length === 0 && d.words < 900 && d.tables === 0);
      const share = Math.round((suspicious.length / posts.length) * 100);
      return share > 40 ? [siteIssue(`${suspicious.length} articoli su ${posts.length} (${share}%) senza immagini, link interni, tabelle né dati: profilo tipico di contenuto prodotto in serie`)] : [];
    },
  },
  {
    id: 'EAT-04', category: 'eeat', severity: SEV.medium, auto: false,
    title: 'Commenti chiusi ovunque: nessun segnale di interazione',
    why: 'Interazione e contenuti generati dagli utenti sono segnali di sito vivo; non sono decisivi ma contribuiscono alla percezione di attività.',
    fix: 'Valutare l’apertura dei commenti moderati sugli articoli guida.',
    check: (s) => {
      const open = s.posts.filter((d) => d.commentStatus === 'open').length;
      return open === 0 ? [siteIssue(`commenti chiusi su tutti i ${s.posts.length} articoli`)] : [];
    },
  },
];
