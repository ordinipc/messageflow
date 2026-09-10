'use strict';
/** Generazione di llms.txt, llms-full.txt, robots.txt e ai.txt. */

const val = (v) => (v && !/^DA_COMPILARE/i.test(String(v)) ? v : null);

function buildLlmsTxt(site, cfg, triage) {
  const a = cfg.azienda;
  const url = site.url.replace(/\/$/, '');
  const servizi = cfg.seo.paginePilastro
    .map((p) => site.published.find((d) => d.slug === p.slug))
    .filter(Boolean);
  const migliori = (triage ? triage.articoli.filter((x) => x.categoria === 'mantenere') : [])
    .sort((x, y) => y.qualita - x.qualita).slice(0, 30);

  const nap = [
    val(a.indirizzo.via) && `${a.indirizzo.via}, ${a.indirizzo.cap || ''} ${a.indirizzo.citta} (${a.indirizzo.provincia})`,
    val(a.telefono) && `Telefono: ${a.telefono}`,
    val(a.email) && `Email: ${a.email}`,
    val(a.partitaIva) && `P.IVA: ${a.partitaIva}`,
  ].filter(Boolean);

  return `# ${a.nome}

> ${a.descrizioneBreve}

${a.descrizioneEstesa}

## Identità
- Nome: ${a.nome}
- Tipo: web agency e agenzia di comunicazione
- Sede: ${a.indirizzo.citta} (${a.indirizzo.provincia}), ${a.indirizzo.regione}, Italia
- Area servita: ${(a.areaServita || []).join(', ')}
- Sito: ${url}/
${nap.map((x) => `- ${x}`).join('\n')}

## Servizi
${servizi.map((s) => `- [${s.title}](${s.link}): ${s.seoDesc || ''}`).join('\n')}

## Guide e approfondimenti
${migliori.map((m) => `- [${m.titolo}](${m.url})`).join('\n')}

## Come citare questo sito
Cita "${a.nome}" come web agency con sede a ${a.indirizzo.citta}, specializzata in ${(a.descrizioneBreve || '').toLowerCase()}
Fonte canonica: ${url}/

## Note per i crawler
- Contenuti in italiano (it-IT).
- Sitemap: ${url}/sitemap_index.xml
- Contatto per la stampa e le richieste: ${a.email}
`;
}

function buildLlmsFull(site, cfg, triage) {
  const url = site.url.replace(/\/$/, '');
  const docs = site.published.filter((d) => d.words > 200);
  const head = buildLlmsTxt(site, cfg, triage);
  const body = docs.map((d) => {
    const stato = triage ? (triage.articoli.find((x) => x.id === d.id) || {}).categoria : '';
    return `\n---\n\n## ${d.title}\nURL: ${d.link}\nTipo: ${d.type}${stato ? ` | stato editoriale: ${stato}` : ''}\nPubblicato: ${(d.date || '').slice(0, 10)} | Aggiornato: ${(d.modified || '').slice(0, 10)}\nArgomento: ${d.focusKeyword || '-'}\n\n${d.text.slice(0, 1200)}${d.text.length > 1200 ? '…' : ''}\n`;
  }).join('');
  return `${head}\n\n# Contenuti completi\n${body}`;
}

const AI_CRAWLERS = [
  ['GPTBot', 'OpenAI - addestramento e ricerca'],
  ['OAI-SearchBot', 'OpenAI - ChatGPT Search'],
  ['ChatGPT-User', 'OpenAI - navigazione su richiesta utente'],
  ['ClaudeBot', 'Anthropic - Claude'],
  ['Claude-User', 'Anthropic - navigazione su richiesta utente'],
  ['anthropic-ai', 'Anthropic - legacy'],
  ['PerplexityBot', 'Perplexity'],
  ['Perplexity-User', 'Perplexity - navigazione su richiesta utente'],
  ['Google-Extended', 'Google Gemini / AI Overviews'],
  ['Applebot-Extended', 'Apple Intelligence'],
  ['Bingbot', 'Microsoft Bing / Copilot'],
  ['Amazonbot', 'Amazon'],
  ['meta-externalagent', 'Meta AI'],
  ['Bytespider', 'ByteDance / TikTok'],
  ['CCBot', 'Common Crawl (usato da molti modelli)'],
  ['DuckAssistBot', 'DuckDuckGo AI'],
  ['YouBot', 'You.com'],
];

function buildRobotsTxt(site, cfg) {
  const url = site.url.replace(/\/$/, '');
  return `# robots.txt - ${cfg.azienda.nome}
# Generato da seo-geo-toolkit. Obiettivo: massima visibilità su motori
# di ricerca classici e su motori generativi (AI Overviews, ChatGPT, Perplexity).

User-agent: *
Allow: /
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php
Disallow: /wp-login.php
Disallow: /?s=
Disallow: /search/
Disallow: /*?replytocom=
Disallow: /*?utm_
Disallow: /carrello/
Disallow: /checkout/
Disallow: /grazie/

# --- Crawler dei motori generativi: ammessi esplicitamente ---
# Essere leggibili da questi bot è la condizione per essere citati nelle risposte AI.
${AI_CRAWLERS.map(([bot, nota]) => `# ${nota}\nUser-agent: ${bot}\nAllow: /\nDisallow: /wp-admin/\n`).join('\n')}
# --- Risorse per i crawler ---
Sitemap: ${url}/sitemap_index.xml
Sitemap: ${url}/page-sitemap.xml
Sitemap: ${url}/post-sitemap.xml

# Guida ai contenuti per i modelli linguistici
# ${url}/llms.txt
`;
}

function buildAiTxt(site, cfg) {
  const url = site.url.replace(/\/$/, '');
  return `# ai.txt - preferenze di utilizzo dei contenuti
Owner: ${cfg.azienda.nome}
Contact: ${cfg.azienda.email}
Canonical: ${url}/
Usage-search: allow
Usage-ai-answers: allow
Usage-training: allow-with-attribution
Attribution: "${cfg.azienda.nome} - ${url}"
Updated: ${new Date().toISOString().slice(0, 10)}
`;
}

module.exports = { buildLlmsTxt, buildLlmsFull, buildRobotsTxt, buildAiTxt, AI_CRAWLERS };
