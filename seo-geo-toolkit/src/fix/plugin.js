'use strict';
const fs = require('fs');
const path = require('path');

/** Copia ricorsiva dei template PHP. */
function copyDir(src, dest) {
  fs.mkdirSync(dest, { recursive: true });
  for (const entry of fs.readdirSync(src, { withFileTypes: true })) {
    const s = path.join(src, entry.name);
    const d = path.join(dest, entry.name);
    if (entry.isDirectory()) copyDir(s, d);
    else fs.copyFileSync(s, d);
  }
}

/**
 * Assembla il plugin WordPress: codice dai template + dati calcolati dall'audit.
 */
function buildPlugin(ctx) {
  const { site, cfg, outDir, metaPlan, linkPlan, triage, llms } = ctx;
  const pluginDir = path.join(outDir, 'plugin-wordpress', 'mdi-seo-geo-booster');
  const templates = path.join(__dirname, '..', '..', 'templates', 'plugin');

  copyDir(templates, pluginDir);
  const dataDir = path.join(pluginDir, 'data');
  fs.mkdirSync(dataDir, { recursive: true });

  // 1. Meta ottimizzate per post ID.
  const legale = (slug) => /privacy|cookie|termini|condizioni|grazie|thank/i.test(slug);
  const metaMap = metaPlan.map((m) => ({
    id: Number(m.id),
    slug: m.slugAttuale,
    title: m.titoloNuovo,
    description: m.descrizioneNuova,
    excerpt: m.excerptNuovo,
    noindex: legale(m.slugAttuale),
  }));
  fs.writeFileSync(path.join(dataDir, 'meta-map.json'), JSON.stringify(metaMap, null, 1));

  // 2. Mappa keyword -> URL per i link interni automatici.
  fs.writeFileSync(path.join(dataDir, 'internal-links.json'), JSON.stringify(linkPlan.keywordMap, null, 1));

  // 3. Correlati per articolo (recupera le pagine orfane).
  const related = {};
  const byPath = new Map(site.published.map((d) => [d.path, d]));
  linkPlan.plan.forEach((l) => {
    const from = byPath.get(l.da);
    const to = byPath.get(l.a);
    if (!from || !to) return;
    if (!related[from.id]) related[from.id] = [];
    if (related[from.id].length >= 5) return;
    related[from.id].push({ titolo: to.title, url: to.link });
  });
  fs.writeFileSync(path.join(dataDir, 'related.json'), JSON.stringify(related, null, 1));

  // 4. Configurazione aziendale (il plugin ignora i segnaposto non compilati).
  fs.writeFileSync(path.join(dataDir, 'config.json'), JSON.stringify(cfg, null, 1));

  // 5. File per i motori generativi, serviti dagli endpoint del plugin.
  fs.writeFileSync(path.join(dataDir, 'llms.txt'), llms.llmsTxt);
  fs.writeFileSync(path.join(dataDir, 'llms-full.txt'), llms.llmsFull);
  fs.writeFileSync(path.join(dataDir, 'ai.txt'), llms.aiTxt);

  // 6. Stato editoriale, utile come riferimento dentro l'installazione.
  if (triage) {
    fs.writeFileSync(path.join(dataDir, 'triage.json'), JSON.stringify(triage.conteggi, null, 1));
  }

  return {
    dir: pluginDir,
    file: path.join(pluginDir, 'mdi-seo-geo-booster.php'),
    contenutiOttimizzati: metaMap.length,
    keywordMappate: Object.keys(linkPlan.keywordMap).length,
    articoliConCorrelati: Object.keys(related).length,
  };
}

module.exports = { buildPlugin };
