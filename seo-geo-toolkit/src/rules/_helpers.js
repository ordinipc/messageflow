'use strict';
const T = require('../core/text');

const SEV = { critical: 'critical', high: 'high', medium: 'medium', low: 'low' };

/** Un problema riferito a un singolo documento. */
function docIssue(doc, detail, extra = {}) {
  return { ref: doc.path || doc.slug, title: doc.title, type: doc.type, detail, ...extra };
}

/** Un problema a livello di sito (nessun documento specifico). */
function siteIssue(detail, extra = {}) {
  return { ref: '(sito)', title: '', type: 'site', detail, ...extra };
}

function pct(part, total) {
  return total ? Math.round((part / total) * 100) : 0;
}

const CITY_TERMS = ['palermo', 'sicilia', 'siciliana', 'siciliano', 'monreale', 'bagheria', 'cefalù', 'trapani', 'catania'];

function mentionsCity(text) {
  const t = String(text || '').toLowerCase();
  return CITY_TERMS.some((c) => t.includes(c));
}

const QUESTION_STARTERS = ['come', 'perché', 'perche', 'quanto', 'quali', 'quale', 'cosa', 'che cos', 'quando', 'dove', 'chi', 'conviene', 'meglio'];

function isQuestion(text) {
  const t = String(text || '').trim().toLowerCase();
  return t.endsWith('?') || QUESTION_STARTERS.some((q) => t.startsWith(q));
}

module.exports = { SEV, docIssue, siteIssue, pct, mentionsCity, isQuestion, CITY_TERMS, QUESTION_STARTERS, T };
