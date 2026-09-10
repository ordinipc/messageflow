#!/usr/bin/env node
'use strict';
/**
 * seo-geo-toolkit — audit e correzione SEO/GEO di un sito WordPress
 * a partire dal file di esportazione WXR.
 *
 * Uso:
 *   node bin/seo-geo.js all --input export.xml [--config config.json] [--out output]
 *   node bin/seo-geo.js audit --input export.xml
 *   node bin/seo-geo.js triage --input export.xml
 *   node bin/seo-geo.js fix --input export.xml
 */

const fs = require('fs');
const path = require('path');

const { parseFile } = require('../src/parser/wxr');
const { buildSite } = require('../src/core/site');
const { runAudit } = require('../src/audit');
const { buildTriage } = require('../src/analysis/triage');
const { buildMetaPlan } = require('../src/fix/meta');
const { buildLinkPlan } = require('../src/fix/internal-links');
const { buildSchemaPlan } = require('../src/fix/schema');
const { buildLlmsTxt, buildLlmsFull, buildRobotsTxt, buildAiTxt } = require('../src/fix/llms');
const { buildPlugin } = require('../src/fix/plugin');
const { buildPages } = require('../src/fix/pages');
const { buildContentPlan } = require('../src/fix/content-plan');
const { toCsv } = require('../src/report/csv');
const { reportAudit, reportTriage } = require('../src/report/markdown');
const { buildHtmlReport } = require('../src/report/html');

function parseArgs(argv) {
  const out = { cmd: argv[2] || 'all', input: '', config: path.join(__dirname, '..', 'config.json'), out: path.join(__dirname, '..', 'output') };
  for (let i = 3; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--input' || a === '-i') out.input = argv[++i];
    else if (a === '--config' || a === '-c') out.config = argv[++i];
    else if (a === '--out' || a === '-o') out.out = argv[++i];
    else if (!out.input) out.input = a;
  }
  return out;
}

const write = (file, content) => {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, content);
  return file;
};

const fmt = (n) => new Intl.NumberFormat('it-IT').format(n);

function main() {
  const args = parseArgs(process.argv);

  if (!args.input || !fs.existsSync(args.input)) {
    console.error('Errore: file di esportazione WordPress non trovato.\n');
    console.error('Uso: node bin/seo-geo.js all --input <export.xml> [--config config.json] [--out output]');
    process.exit(1);
  }

  const cfg = JSON.parse(fs.readFileSync(args.config, 'utf8'));
  const t0 = Date.now();

  console.log(`\n▶ Lettura di ${path.basename(args.input)}…`);
  const site = buildSite(parseFile(args.input));
  console.log(`  ${fmt(site.posts.length)} articoli · ${fmt(site.pages.length)} pagine · ${fmt(site.attachments.length)} media · dominio ${site.host}`);

  const scritti = [];

  // ---------- AUDIT ----------
  console.log('\n▶ Audit SEO e GEO…');
  const audit = runAudit(site);
  console.log(`  punteggio ${audit.global}/100 — ${fmt(audit.totalIssues)} problemi su ${audit.findings.length} controlli non superati`);
  Object.values(audit.scores).sort((a, b) => a.score - b.score).slice(0, 4)
    .forEach((c) => console.log(`    ${String(c.score).padStart(3)}/100  ${c.label}`));

  scritti.push(write(path.join(args.out, 'audit.json'), JSON.stringify(audit, null, 1)));
  scritti.push(write(path.join(args.out, 'audit.md'), reportAudit(audit)));
  scritti.push(write(path.join(args.out, 'problemi.csv'), toCsv(
    audit.findings.flatMap((f) => f.issues.map((i) => ({ ...i, id: f.id, sev: f.severity, titolo: f.title, categoria: f.category, auto: f.auto ? 'sì' : 'no' }))),
    [
      { label: 'ID regola', key: 'id' }, { label: 'Gravità', key: 'sev' }, { label: 'Area', key: 'categoria' },
      { label: 'Problema', key: 'titolo' }, { label: 'URL / elemento', key: 'ref' }, { label: 'Dettaglio', key: 'detail' },
      { label: 'Risolto dal toolkit', key: 'auto' },
    ],
  )));

  if (args.cmd === 'audit') return report(scritti, t0);

  // ---------- TRIAGE ----------
  console.log('\n▶ Triage editoriale degli articoli…');
  const triage = buildTriage(site, cfg);
  Object.entries(triage.conteggi).forEach(([k, v]) => console.log(`  ${String(v).padStart(4)}  ${k}`));

  scritti.push(write(path.join(args.out, 'triage.json'), JSON.stringify(triage, null, 1)));
  scritti.push(write(path.join(args.out, 'triage.md'), reportTriage(triage, site)));
  scritti.push(write(path.join(args.out, 'triage.csv'), toCsv(triage.articoli, [
    { label: 'Categoria', key: 'categoria' }, { label: 'Titolo', key: 'titolo' }, { label: 'URL', key: 'url' },
    { label: 'Parole', key: 'parole' }, { label: 'Qualità', key: 'qualita' }, { label: 'Intento', key: 'intento' },
    { label: 'H2', key: 'h2' }, { label: 'Immagini', key: 'immagini' },
    { label: 'Link in entrata', key: 'linkInterniIn' }, { label: 'Link in uscita', key: 'linkInterniOut' },
    { label: 'Focus keyword', key: 'focusKeyword' }, { label: 'Pubblicato', key: 'dataPubblicazione' },
    { label: 'Similarità max', key: 'similaritaMax' },
    { label: 'Sovrapposizione con pagina servizio', value: (r) => (r.sovrapposizioneServizio ? `${r.sovrapposizioneServizio.pagina} (${r.sovrapposizioneServizio.percentuale}%)` : '') },
    { label: 'Motivo', key: 'motivo' }, { label: 'Azione', key: 'azione' },
    { label: 'Redirect 301 verso', value: (r) => (r.redirect301 ? r.redirect301.url : '') },
  ])));

  if (args.cmd === 'triage') return report(scritti, t0);

  // ---------- FIX ----------
  console.log('\n▶ Generazione delle correzioni…');

  const metaPlan = buildMetaPlan(site, cfg);
  console.log(`  meta: ${metaPlan.filter((m) => m.titoloCambiato).length} title, ${metaPlan.filter((m) => m.descrizioneCambiata).length} description, ${metaPlan.filter((m) => m.slugCambiato).length} slug, ${metaPlan.filter((m) => m.excerptCambiato).length} excerpt`);

  const linkPlan = buildLinkPlan(site, cfg);
  console.log(`  link interni: ${fmt(linkPlan.statistiche.linkProposti)} proposti — pagine orfane da ${linkPlan.statistiche.orfaniPrima} a ${linkPlan.statistiche.orfaniDopo}`);

  const schemaPlan = buildSchemaPlan(site, cfg);
  console.log(`  dati strutturati: ${schemaPlan.length} grafi JSON-LD (${schemaPlan.filter((s) => s.tipi.includes('FAQPage')).length} con FAQPage)`);

  const contentPlan = buildContentPlan(site, cfg, triage, linkPlan);
  console.log(`  piano contenuti: ${contentPlan.schede.length} schede di riscrittura + ${contentPlan.nuoviContenuti.length} contenuti nuovi`);

  const llms = {
    llmsTxt: buildLlmsTxt(site, cfg, triage),
    llmsFull: buildLlmsFull(site, cfg, triage),
    robotsTxt: buildRobotsTxt(site, cfg),
    aiTxt: buildAiTxt(site, cfg),
  };

  // File per i motori di ricerca e generativi
  scritti.push(write(path.join(args.out, 'file-root', 'llms.txt'), llms.llmsTxt));
  scritti.push(write(path.join(args.out, 'file-root', 'llms-full.txt'), llms.llmsFull));
  scritti.push(write(path.join(args.out, 'file-root', 'robots.txt'), llms.robotsTxt));
  scritti.push(write(path.join(args.out, 'file-root', 'ai.txt'), llms.aiTxt));

  // Meta pronte per l'import massivo
  scritti.push(write(path.join(args.out, 'meta-ottimizzate.csv'), toCsv(metaPlan, [
    { label: 'ID', key: 'id' }, { label: 'Tipo', key: 'type' }, { label: 'URL', key: 'url' },
    { label: 'Title attuale', key: 'titoloAttuale' }, { label: 'Title nuovo', key: 'titoloNuovo' },
    { label: 'Lunghezza title', value: (r) => r.titoloNuovo.length },
    { label: 'Description attuale', key: 'descrizioneAttuale' }, { label: 'Description nuova', key: 'descrizioneNuova' },
    { label: 'Lunghezza description', value: (r) => r.descrizioneNuova.length },
    { label: 'Excerpt nuovo', key: 'excerptNuovo' },
    { label: 'Slug attuale', key: 'slugAttuale' }, { label: 'Slug nuovo', key: 'slugNuovo' },
    { label: 'Focus keyword', key: 'focusKeyword' },
  ])));

  // Formato accettato dall'editor massivo di Rank Math
  scritti.push(write(path.join(args.out, 'rank-math-bulk.csv'), toCsv(metaPlan, [
    { label: 'id', key: 'id' },
    { label: 'rank_math_title', key: 'titoloNuovo' },
    { label: 'rank_math_description', key: 'descrizioneNuova' },
    { label: 'rank_math_focus_keyword', key: 'focusKeyword' },
  ], ',')));

  // Redirect per gli slug accorciati e per i contenuti da eliminare/accorpare
  const redirects = [
    ...metaPlan.filter((m) => m.slugCambiato).map((m) => ({ da: m.path, a: `/${m.slugNuovo}/`, motivo: 'slug accorciato' })),
    ...triage.articoli.filter((a) => a.redirect301).map((a) => ({
      da: new URL(a.url).pathname, a: new URL(a.redirect301.url).pathname,
      motivo: a.categoria === 'eliminare' ? 'contenuto eliminato' : 'contenuto accorpato',
    })),
  ];
  scritti.push(write(path.join(args.out, 'redirect-301.csv'), toCsv(redirects, [
    { label: 'URL di partenza', key: 'da' }, { label: 'URL di destinazione', key: 'a' }, { label: 'Motivo', key: 'motivo' },
  ])));
  scritti.push(write(path.join(args.out, 'redirect.htaccess'),
    `# Redirect 301 generati da seo-geo-toolkit — ${new Date().toISOString().slice(0, 10)}\n`
    + '# Inserire PRIMA delle regole di WordPress in .htaccess.\n'
    + '<IfModule mod_rewrite.c>\nRewriteEngine On\n'
    + redirects.map((r) => `Redirect 301 ${r.da.replace(/\/$/, '')}/ ${r.a}`).join('\n')
    + '\n</IfModule>\n'));

  // Piano dei link interni
  scritti.push(write(path.join(args.out, 'piano-link-interni.csv'), toCsv(linkPlan.plan, [
    { label: 'Pagina di partenza', key: 'da' }, { label: 'Titolo partenza', key: 'daTitolo' },
    { label: 'Pagina di destinazione', key: 'a' }, { label: 'Titolo destinazione', key: 'aTitolo' },
    { label: 'Anchor text', key: 'anchor' }, { label: 'Motivo', key: 'motivo' }, { label: 'Punteggio', key: 'punteggio' },
  ])));

  // Alt text per la libreria media
  const altRows = site.attachments.filter((a) => /jpe?g|png|webp|gif|svg/.test(a.ext)).map((a) => {
    const parent = site.published.find((d) => d.id === a.parent);
    const base = a.alt || (parent ? `${parent.title}` : a.title.replace(/[-_]+/g, ' '));
    return {
      file: a.file, url: a.url, altAttuale: a.alt,
      altSuggerito: a.alt || `${base} | ${cfg.seo.brandSuffix} ${cfg.seo.cittaPrincipale}`.replace(/\s+/g, ' ').trim(),
      peso: a.filesize ? `${Math.round(a.filesize / 1024)} KB` : '',
      formato: a.ext,
      daConvertire: ['jpg', 'jpeg', 'png'].includes(a.ext) ? 'sì (WebP)' : 'no',
      daComprimere: a.filesize > 200 * 1024 ? 'sì' : 'no',
    };
  });
  scritti.push(write(path.join(args.out, 'immagini-alt.csv'), toCsv(altRows, [
    { label: 'File', key: 'file' }, { label: 'URL', key: 'url' }, { label: 'Alt attuale', key: 'altAttuale' },
    { label: 'Alt suggerito', key: 'altSuggerito' }, { label: 'Peso', key: 'peso' }, { label: 'Formato', key: 'formato' },
    { label: 'Convertire in WebP', key: 'daConvertire' }, { label: 'Comprimere', key: 'daComprimere' },
  ])));

  // Dati strutturati
  scritti.push(write(path.join(args.out, 'schema', 'tutti-gli-schema.json'), JSON.stringify(schemaPlan, null, 1)));
  const esempio = schemaPlan.find((s) => s.tipi.includes('Service')) || schemaPlan[0];
  scritti.push(write(path.join(args.out, 'schema', 'esempio-pagina-servizio.json'), JSON.stringify(esempio.schema, null, 2)));

  // Piano editoriale
  const md = ['# Piano di riscrittura e calendario editoriale', ''];
  md.push(`Schede operative: ${contentPlan.schede.length}. Ogni scheda indica lunghezza obiettivo, scaletta, FAQ e link interni da inserire.`, '');
  md.push('## Calendario — 12 settimane', '');
  contentPlan.calendario.forEach((s) => {
    if (!s.lavori.length) return;
    md.push(`### Settimana ${s.settimana} (dal ${s.dal})`, '');
    s.lavori.forEach((l) => md.push(`- **${l.titolo}** → ${l.azione} (obiettivo ${l.paroleTarget} parole)  \n  ${l.url}`));
    md.push('');
  });
  md.push('## Contenuti nuovi da produrre', '');
  contentPlan.nuoviContenuti.forEach((n) => md.push(`- **${n.titolo}** — intento ${n.intento}. ${n.motivo}`));
  md.push('', '## Schede di riscrittura', '');
  contentPlan.schede.forEach((s) => {
    md.push(`### ${s.titolo}`, '');
    md.push(`${s.url}  `);
    md.push(`Categoria: ${s.categoria} · intento: ${s.intento} · da ${s.paroleAttuali} a ${s.paroleTarget} parole  `);
    md.push(`Focus keyword: ${s.focusKeyword}  `);
    md.push('', `**Blocco "In breve".** ${s.inBreve}`, '');
    md.push('**Scaletta H2**', '');
    s.scalettaH2.forEach((h) => md.push(`- ${h}`));
    md.push('', '**FAQ da inserire**', '');
    s.faq.forEach((f) => md.push(`- ${f}`));
    md.push('', '**Da aggiungere**', '');
    s.datiDaInserire.forEach((d) => md.push(`- ${d}`));
    md.push(`- immagini: ${s.immagini}`);
    if (s.linkInterniDaInserire.length) {
      md.push('', '**Link interni da inserire**', '');
      s.linkInterniDaInserire.forEach((l) => md.push(`- [${l.anchor}](${l.url})`));
    }
    md.push('');
  });
  scritti.push(write(path.join(args.out, 'piano-contenuti.md'), md.join('\n')));
  scritti.push(write(path.join(args.out, 'piano-contenuti.csv'), toCsv(contentPlan.schede, [
    { label: 'Titolo', key: 'titolo' }, { label: 'URL', key: 'url' }, { label: 'Categoria', key: 'categoria' },
    { label: 'Intento', key: 'intento' }, { label: 'Parole attuali', key: 'paroleAttuali' }, { label: 'Parole obiettivo', key: 'paroleTarget' },
    { label: 'Focus keyword', key: 'focusKeyword' },
    { label: 'Scaletta H2', value: (r) => r.scalettaH2.join(' | ') },
    { label: 'FAQ', value: (r) => r.faq.join(' | ') },
    { label: 'Azione', key: 'azione' },
  ])));

  // Pagine mancanti
  const pagine = buildPages(cfg);
  Object.entries(pagine).forEach(([nome, html]) => scritti.push(write(path.join(args.out, 'pagine-da-creare', nome), html)));

  // Plugin WordPress
  const plugin = buildPlugin({ site, cfg, outDir: args.out, metaPlan, linkPlan, triage, llms });
  console.log(`  plugin WordPress: ${plugin.contenutiOttimizzati} contenuti, ${plugin.keywordMappate} keyword, ${plugin.articoliConCorrelati} blocchi correlati`);
  scritti.push(plugin.file);

  // Report HTML
  scritti.push(write(path.join(args.out, 'report.html'), buildHtmlReport({ audit, triage, linkPlan, metaPlan, contentPlan, cfg })));

  // Istruzioni
  scritti.push(write(path.join(args.out, 'LEGGIMI.md'), istruzioni({ audit, triage, linkPlan, metaPlan, redirects, cfg })));

  report(scritti, t0);
}

function istruzioni({ audit, triage, linkPlan, metaPlan, redirects, cfg }) {
  return `# Come applicare le correzioni

Punteggio di partenza: **${audit.global}/100** — ${audit.totalIssues} problemi rilevati.

## Ordine di esecuzione

### 1. Prima di toccare qualsiasi cosa
- Fai un backup completo (database + file).
- Verifica che Google Search Console sia collegata e che la sitemap sia inviata.

### 2. Risolvi il conflitto fra plugin SEO (10 minuti)
Rank Math e Yoast sono entrambi attivi e stampano gli stessi tag due volte.
Mantieni **Rank Math** (i suoi dati sono più completi), disattiva e disinstalla Yoast.

### 3. Compila i dati aziendali (15 minuti)
Apri \`config.json\` e sostituisci ogni \`DA_COMPILARE\`: telefono, indirizzo, CAP, P.IVA,
coordinate, scheda Google Business, nome e biografia dell'autore.
Poi rigenera i file: \`node bin/seo-geo.js all --input <export.xml>\`.
Senza questi dati lo schema locale resta incompleto e il capitolo SEO locale non migliora.

### 4. Installa il plugin (5 minuti)
1. Comprimi la cartella \`plugin-wordpress/mdi-seo-geo-booster\` in uno zip.
2. WordPress → Plugin → Aggiungi nuovo → Carica plugin → Attiva.
3. Vai su Impostazioni → Permalink e premi Salva (rigenera le regole per /llms.txt).
4. Controlla la nuova voce di menu "SEO & GEO".

Il plugin applica automaticamente: ${metaPlan.length} title e description ottimizzate,
dati strutturati JSON-LD su tutte le pagine, ${Object.keys(linkPlan.keywordMap).length} keyword
mappate per i link interni, alt e lazy loading sulle immagini, nofollow sui link social,
endpoint /llms.txt e /ai.txt, direttive robots per i crawler AI.

### 5. Carica i file di root
Copia \`file-root/robots.txt\` nella radice del sito (o lascia fare al plugin, che integra le
direttive tramite il filtro \`robots_txt\`).

### 6. Crea le pagine mancanti
Usa i template in \`pagine-da-creare/\`: **/chi-siamo/** e **/contatti/**.
Aggiungile al menu principale insieme a una voce **Blog** — oggi ${triage.totale} articoli
non sono raggiungibili dalla navigazione.

### 7. Applica il triage editoriale
- Da eliminare: **${triage.conteggi.eliminare || 0}** articoli
- Da accorpare: **${triage.conteggi.accorpare || 0}**
- Da riscrivere: **${triage.conteggi.riscrivere || 0}**
- Da mantenere: **${triage.conteggi.mantenere || 0}**

Imposta prima i **${redirects.length} redirect 301** del file \`redirect-301.csv\`
(plugin Redirection o \`redirect.htaccess\`), poi cestina.

### 8. Segui il calendario
\`piano-contenuti.md\` contiene 12 settimane di lavoro già ordinate per priorità
e ${(triage.conteggi.riscrivere || 0) + (triage.conteggi.accorpare || 0)} schede di riscrittura con scaletta e FAQ.

## Verifiche dopo la pubblicazione
1. Rich Results Test su una pagina servizio e su un articolo con FAQ.
2. \`${cfg.azienda.nome.toLowerCase().replace(/\s+/g, '')}.it/llms.txt\` deve rispondere 200.
3. Search Console → Controllo URL → Richiedi indicizzazione sulle 10 pagine servizio.
4. Ripeti l'audit dopo 30 giorni: \`node bin/seo-geo.js audit --input <nuovo-export.xml>\`.
`;
}

function report(scritti, t0) {
  console.log(`\n✔ Completato in ${((Date.now() - t0) / 1000).toFixed(1)}s — ${scritti.length} file generati:`);
  scritti.forEach((f) => console.log(`  ${path.relative(process.cwd(), f)}`));
  console.log('');
}

main();
