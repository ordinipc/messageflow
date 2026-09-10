'use strict';
const { SEV, docIssue, siteIssue, isQuestion } = require('./_helpers');

/**
 * GEO - Generative Engine Optimization: essere citati dentro le risposte di
 * AI Overviews, ChatGPT Search, Perplexity, Copilot, Gemini.
 * Le regole misurano quanto il contenuto è estraibile, attribuibile e citabile.
 */
module.exports = [
  {
    id: 'GEO-01', category: 'generative', severity: SEV.critical, auto: true,
    title: 'File llms.txt assente',
    why: 'llms.txt è lo standard emergente con cui si dichiara ai modelli quali contenuti del sito sono autorevoli e come vanno citati. Senza, il crawler AI deve indovinare la struttura del sito.',
    fix: 'Generazione di llms.txt e llms-full.txt con mappa dei servizi, delle guide e dei dati aziendali.',
    check: () => [siteIssue('nessun llms.txt dichiarato (non presente nell’export, da verificare in root)')],
  },
  {
    id: 'GEO-02', category: 'generative', severity: SEV.critical, auto: true,
    title: 'robots.txt non regola i crawler AI (GPTBot, ClaudeBot, PerplexityBot)',
    why: 'Se i crawler dei motori generativi non sono esplicitamente ammessi (o sono bloccati da regole generiche) il sito non può comparire nelle risposte AI, che oggi intercettano una quota crescente di ricerche informazionali.',
    fix: 'robots.txt generato con allow espliciti per GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended, più sitemap e llms.txt.',
    check: () => [siteIssue('robots.txt da rigenerare con direttive esplicite per i crawler AI')],
  },
  {
    id: 'GEO-03', category: 'generative', severity: SEV.high, auto: true,
    title: 'Nessuna risposta diretta in apertura (answer-first)',
    why: 'I modelli generativi estraggono la risposta dai primi 40-60 parole dopo il titolo. Se l’articolo apre con un’introduzione narrativa, non c’è nulla da citare.',
    fix: 'Blocco "In breve" di 40-60 parole in cima a ogni articolo, generato nel piano di riscrittura.',
    check: (s) => s.published.filter((d) => d.words > 250).filter((d) => {
      const first = (d.firstParagraph || '').split(/\s+/).length;
      const hasSummary = /in breve|in sintesi|risposta rapida|tl;dr|punti chiave/i.test(d.text.slice(0, 600));
      return !hasSummary && first > 0;
    }).map((d) => docIssue(d, 'nessun blocco di sintesi iniziale citabile')),
  },
  {
    id: 'GEO-04', category: 'generative', severity: SEV.high, auto: true,
    title: 'Nessuna sezione FAQ esplicita',
    why: 'Le coppie domanda/risposta brevi sono il formato più citato dai motori generativi e alimentano i rich result FAQ.',
    fix: 'Blocco FAQ (3-5 domande reali dalla ricerca) + schema FAQPage generati per ogni pagina servizio e articolo pilastro.',
    check: (s) => s.published.filter((d) => d.words > 250 && !d.headings.some((h) => isQuestion(h.text)))
      .map((d) => docIssue(d, 'nessuna domanda fra i titoli di sezione')),
  },
  {
    id: 'GEO-05', category: 'generative', severity: SEV.high, auto: false,
    title: 'Contenuto senza dati, numeri o fonti citabili',
    why: 'Gli studi sulla citazione nei motori generativi mostrano che statistiche, citazioni e fonti aumentano molto la probabilità di essere ripresi in risposta.',
    fix: 'Inserire almeno 2 dati verificabili con fonte (Istat, Osservatori PoliMi, Google/Meta) per articolo pilastro.',
    check: (s) => s.published.filter((d) => d.words > 300)
      .filter((d) => (d.text.match(/\b\d{1,3}(?:[.,]\d+)?\s?%|\b\d{4}\b|\b\d+\s?(?:milioni|miliardi|mila)\b/gi) || []).length < 2)
      .map((d) => docIssue(d, 'nessun dato numerico o percentuale nel testo')),
  },
  {
    id: 'GEO-06', category: 'generative', severity: SEV.high, auto: true,
    title: 'Autore non attribuito a una persona reale',
    why: 'I motori generativi valutano l’attribuibilità: un contenuto firmato da una persona con biografia e profili verificabili è più affidabile di uno firmato da un login generico.',
    fix: 'Schema Person + author box; rinominare gli utenti WordPress con nome e cognome reali.',
    check: (s) => {
      const bad = s.authors.filter((a) => !a.firstName || !a.lastName || /^[a-z0-9._%-]+@|^\w+\d+$|digital$/i.test(a.displayName || ''));
      return bad.map((a) => siteIssue(`autore "${a.displayName || a.login}" senza nome e cognome reali`, { value: a.login }));
    },
  },
  {
    id: 'GEO-07', category: 'generative', severity: SEV.medium, auto: true,
    title: 'Nessun markup speakable per risposte vocali',
    why: 'Lo schema speakable indica quali frammenti sono adatti alla lettura vocale e alla sintesi: aiuta assistenti vocali e riassunti AI.',
    fix: 'speakable aggiunto allo schema Article dal plugin.',
    check: (s) => s.published.filter((d) => !/speakable/i.test(d.content)).map((d) => docIssue(d, 'speakable assente')),
  },
  {
    id: 'GEO-08', category: 'generative', severity: SEV.high, auto: false,
    title: 'Entità del brand non definita in modo coerente',
    why: 'Perché un modello citi "Max Digital Innovation" come agenzia di Palermo deve trovare la stessa descrizione ripetuta in modo coerente su sito, schema e profili esterni (sameAs).',
    fix: 'Definire una descrizione canonica dell’entità e replicarla identica in Organization, footer, llms.txt, Google Business e profili social.',
    check: (s) => {
      const has = s.published.some((d) => /"@type"\s*:\s*"Organization"/i.test(d.content));
      const sameAs = s.published.some((d) => /sameAs/i.test(d.content));
      const out = [];
      if (!has) out.push(siteIssue('nessuno schema Organization che definisca l’entità aziendale'));
      if (!sameAs) out.push(siteIssue('nessun sameAs verso profili social/directory: l’entità non è collegabile'));
      return out;
    },
  },
  {
    id: 'GEO-09', category: 'generative', severity: SEV.medium, auto: false,
    title: 'Contenuti senza aggiornamento datato visibile',
    why: 'I motori generativi privilegiano fonti recenti e con data esplicita: senza "Aggiornato al" il contenuto viene considerato potenzialmente obsoleto.',
    fix: 'Mostrare data di pubblicazione e ultimo aggiornamento, allineate a dateModified nello schema.',
    check: (s) => s.published.filter((d) => d.date && d.modified && d.date.slice(0, 10) === d.modified.slice(0, 10) && d.words > 300)
      .map((d) => docIssue(d, `mai aggiornato dalla pubblicazione (${d.date.slice(0, 10)})`)),
  },
  {
    id: 'GEO-10', category: 'generative', severity: SEV.medium, auto: true,
    title: 'Nessun blocco di dati sintetici (tabella, elenco definitorio)',
    why: 'Tabelle e liste con coppie chiave/valore sono la struttura che i modelli estraggono con più affidabilità (prezzi, tempi, requisiti, confronti).',
    fix: 'Aggiungere per ogni servizio una tabella "cosa include / tempi / a chi serve".',
    check: (s) => s.published.filter((d) => d.words > 400 && d.tables === 0)
      .map((d) => docIssue(d, 'nessuna tabella di dati strutturati')),
  },
  {
    id: 'GEO-11', category: 'generative', severity: SEV.medium, auto: false,
    title: 'Titoli non allineati al linguaggio delle query conversazionali',
    why: 'Le ricerche nei motori generativi sono frasi lunghe e in forma di domanda: i titoli devono rispecchiare quel linguaggio per intercettarle.',
    fix: 'Riformulare i titoli di sezione come domande reali ("Quanto costa un sito web a Palermo?").',
    check: (s) => {
      const q = s.published.filter((d) => d.isQuestionTitle).length;
      const share = Math.round((q / Math.max(1, s.published.length)) * 100);
      return share < 25 ? [siteIssue(`solo ${q} contenuti su ${s.published.length} (${share}%) hanno un titolo in forma di domanda`)] : [];
    },
  },
];
