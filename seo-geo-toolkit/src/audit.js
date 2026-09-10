'use strict';
const { RULES, CATEGORIES } = require('./rules');

const SEVERITY_WEIGHT = { critical: 30, high: 16, medium: 7, low: 2 };
const SEVERITY_ORDER = { critical: 0, high: 1, medium: 2, low: 3 };

/** Esegue tutte le regole sul sito e calcola i punteggi per categoria. */
function runAudit(site) {
  const results = [];
  for (const rule of RULES) {
    let issues = [];
    try {
      issues = rule.check(site) || [];
    } catch (err) {
      issues = [{ ref: '(errore)', detail: `regola non eseguita: ${err.message}`, type: 'error' }];
    }
    if (!issues.length) continue;
    const affected = new Set(issues.map((i) => i.ref)).size;
    const share = issues[0] && issues[0].type === 'site'
      ? 1
      : Math.min(1, affected / Math.max(1, site.published.length));
    results.push({
      id: rule.id,
      category: rule.category,
      severity: rule.severity,
      title: rule.title,
      why: rule.why,
      fix: rule.fix,
      auto: Boolean(rule.auto),
      count: issues.length,
      affected,
      share,
      penalty: SEVERITY_WEIGHT[rule.severity] * (0.35 + 0.65 * share),
      issues,
    });
  }

  results.sort((a, b) => SEVERITY_ORDER[a.severity] - SEVERITY_ORDER[b.severity] || b.penalty - a.penalty);

  const scores = {};
  for (const key of Object.keys(CATEGORIES)) {
    const penalty = results.filter((r) => r.category === key).reduce((a, r) => a + r.penalty, 0);
    scores[key] = { ...CATEGORIES[key], key, score: Math.max(0, Math.round(100 - penalty)), findings: results.filter((r) => r.category === key).length };
  }

  const totalWeight = Object.values(CATEGORIES).reduce((a, c) => a + c.weight, 0);
  const global = Math.round(
    Object.entries(scores).reduce((a, [k, v]) => a + v.score * CATEGORIES[k].weight, 0) / totalWeight,
  );

  const bySeverity = { critical: 0, high: 0, medium: 0, low: 0 };
  results.forEach((r) => { bySeverity[r.severity] += 1; });

  return {
    generatedAt: new Date().toISOString(),
    site: {
      name: site.meta.title,
      url: site.url,
      host: site.host,
      language: site.meta.language,
      posts: site.posts.length,
      pages: site.pages.length,
      attachments: site.attachments.length,
      categories: site.categories.length,
      tags: site.tags.length,
      authors: site.authors.length,
    },
    global,
    scores,
    bySeverity,
    totalIssues: results.reduce((a, r) => a + r.count, 0),
    findings: results,
  };
}

module.exports = { runAudit, SEVERITY_WEIGHT };
