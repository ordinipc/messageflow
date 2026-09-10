'use strict';
/** Verifica di base: parser, regole, fix e report su un WXR minimo. */
const assert = require('assert');
const { parseString } = require('../src/parser/wxr');
const { buildSite } = require('../src/core/site');
const { runAudit } = require('../src/audit');
const { buildTriage } = require('../src/analysis/triage');
const { buildMetaPlan } = require('../src/fix/meta');
const { buildLinkPlan } = require('../src/fix/internal-links');
const { buildSchemaFor } = require('../src/fix/schema');
const T = require('../src/core/text');
const cfg = require('../config.json');

const XML = `<?xml version="1.0"?><rss version="2.0"
 xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
 xmlns:content="http://purl.org/rss/1.0/modules/content/"
 xmlns:dc="http://purl.org/dc/elements/1.1/"
 xmlns:wp="http://wordpress.org/export/1.2/"><channel>
<title>Sito Prova</title><link>https://esempio.it</link><language>it-IT</language>
<wp:base_site_url>https://esempio.it</wp:base_site_url>
<wp:author><wp:author_id>1</wp:author_id><wp:author_login><![CDATA[admin]]></wp:author_login><wp:author_email><![CDATA[a@b.it]]></wp:author_email><wp:author_display_name><![CDATA[admin]]></wp:author_display_name><wp:author_first_name><![CDATA[]]></wp:author_first_name><wp:author_last_name><![CDATA[]]></wp:author_last_name></wp:author>
<item><title><![CDATA[Come scegliere una web agency]]></title>
<link>https://esempio.it/come-scegliere-web-agency/</link>
<content:encoded><![CDATA[<h2>Che cosa fa una web agency?</h2><p>Una web agency progetta siti, cura la comunicazione e segue le campagne pubblicitarie per le imprese che vogliono crescere online in modo misurabile e continuo nel tempo.</p><img src="/a.jpg">]]></content:encoded>
<excerpt:encoded><![CDATA[]]></excerpt:encoded>
<wp:post_id>10</wp:post_id><wp:post_date><![CDATA[2024-01-02 10:00:00]]></wp:post_date>
<wp:post_date_gmt><![CDATA[2024-01-02 09:00:00]]></wp:post_date_gmt>
<wp:post_modified_gmt><![CDATA[2024-01-02 09:00:00]]></wp:post_modified_gmt>
<wp:post_name><![CDATA[come-scegliere-web-agency]]></wp:post_name><wp:status><![CDATA[publish]]></wp:status>
<wp:post_parent>0</wp:post_parent><wp:post_type><![CDATA[post]]></wp:post_type>
<category domain="category" nicename="marketing"><![CDATA[Marketing]]></category>
<wp:postmeta><wp:meta_key><![CDATA[rank_math_focus_keyword]]></wp:meta_key><wp:meta_value><![CDATA[web agency palermo]]></wp:meta_value></wp:postmeta>
<wp:postmeta><wp:meta_key><![CDATA[rank_math_title]]></wp:meta_key><wp:meta_value><![CDATA[Un titolo davvero molto lungo che supera abbondantemente i sessanta caratteri consentiti]]></wp:meta_value></wp:postmeta>
</item></channel></rss>`;

const parsed = parseString(XML);
assert.strictEqual(parsed.items.length, 1, 'un item atteso');
assert.strictEqual(parsed.site.title, 'Sito Prova');

const site = buildSite(parsed);
assert.strictEqual(site.posts.length, 1);
assert.strictEqual(site.posts[0].h2.length, 1, 'un H2 atteso');
assert.strictEqual(site.posts[0].images.length, 1);
assert.strictEqual(site.posts[0].images[0].hasAlt, false, 'alt mancante da rilevare');

const audit = runAudit(site);
assert.ok(audit.global >= 0 && audit.global <= 100, 'punteggio nell intervallo');
assert.ok(audit.findings.some((f) => f.id === 'ONP-01'), 'title troppo lungo rilevato');
assert.ok(audit.findings.some((f) => f.id === 'IMG-01'), 'immagine senza alt rilevata');

const meta = buildMetaPlan(site, cfg);
assert.ok(meta[0].titoloNuovo.length <= cfg.seo.titleMax, 'title accorciato entro il limite');
assert.ok(/[.!?]$/.test(meta[0].descrizioneNuova), 'description chiusa da punteggiatura');
assert.ok(meta[0].excerptNuovo.length > 0, 'excerpt generato');

const triage = buildTriage(site, cfg);
assert.strictEqual(triage.totale, 1);
assert.ok(['eliminare', 'accorpare', 'riscrivere', 'mantenere'].includes(triage.articoli[0].categoria));

const link = buildLinkPlan(site, cfg);
assert.ok(typeof link.statistiche.linkProposti === 'number');

const schema = buildSchemaFor(site, cfg, site.posts[0]);
const tipi = schema['@graph'].map((n) => (Array.isArray(n['@type']) ? n['@type'][0] : n['@type']));
assert.ok(tipi.includes('Organization') && tipi.includes('BlogPosting'), 'grafo con Organization e BlogPosting');

assert.strictEqual(T.shortSlug('Perché un sito web professionale è la chiave del successo'), 'sito-web-professionale-chiave-successo');
assert.strictEqual(T.polishClause('visibilità, engagement e'), 'visibilità, engagement');
// Gulpease richiede almeno 30 parole: sotto quella soglia restituisce null.
const testo = 'Questa è una frase breve. Questa seconda frase è un poco più lunga della prima e serve a superare la soglia minima di parole richiesta dal calcolo dell indice. La terza frase chiude il paragrafo.';
const g = T.gulpease(testo);
assert.ok(g !== null && g > 0 && g <= 100, 'indice Gulpease calcolato');
assert.strictEqual(T.gulpease('Testo troppo breve.'), null, 'sotto le 30 parole nessun indice');

console.log('✔ smoke test superato');
