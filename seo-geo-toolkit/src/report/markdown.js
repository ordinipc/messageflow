'use strict';

const SEV_LABEL = { critical: '🔴 CRITICO', high: '🟠 ALTO', medium: '🟡 MEDIO', low: '⚪ BASSO' };

function barra(score) {
  const pieni = Math.round(score / 5);
  return `${'█'.repeat(pieni)}${'░'.repeat(20 - pieni)} ${score}/100`;
}

function reportAudit(audit) {
  const L = [];
  L.push(`# Audit SEO & GEO — ${audit.site.name}`);
  L.push('');
  L.push(`Sito analizzato: ${audit.site.url}  `);
  L.push(`Data analisi: ${audit.generatedAt.slice(0, 10)}  `);
  L.push(`Contenuti esaminati: ${audit.site.posts} articoli, ${audit.site.pages} pagine, ${audit.site.attachments} file media`);
  L.push('');
  L.push(`## Punteggio complessivo: ${audit.global}/100`);
  L.push('');
  L.push('```');
  L.push(barra(audit.global));
  L.push('```');
  L.push('');
  L.push(`Problemi rilevati: **${audit.totalIssues}** su **${audit.findings.length}** controlli non superati — ${audit.bySeverity.critical} critici, ${audit.bySeverity.high} di livello alto, ${audit.bySeverity.medium} medi, ${audit.bySeverity.low} bassi.`);
  L.push('');
  L.push('## Punteggio per area');
  L.push('');
  L.push('| Area | Punteggio | Rilievi |');
  L.push('|---|---|---|');
  Object.values(audit.scores).sort((a, b) => a.score - b.score).forEach((c) => {
    L.push(`| ${c.icon} ${c.label} | ${c.score}/100 | ${c.findings} |`);
  });
  L.push('');
  L.push('## Problemi in dettaglio');
  L.push('');
  for (const f of audit.findings) {
    L.push(`### ${SEV_LABEL[f.severity]} — ${f.id}: ${f.title}`);
    L.push('');
    L.push(`**Estensione:** ${f.count} occorrenze${f.affected > 1 ? ` su ${f.affected} URL` : ''}  `);
    L.push(`**Perché conta:** ${f.why}  `);
    L.push(`**Come si risolve:** ${f.fix} ${f.auto ? '_(automatizzato dal toolkit)_' : '_(intervento manuale)_'}`);
    L.push('');
    const esempi = f.issues.slice(0, 8);
    if (esempi.length) {
      L.push('| URL / elemento | Dettaglio |');
      L.push('|---|---|');
      esempi.forEach((i) => L.push(`| ${String(i.ref).replace(/\|/g, '/')} | ${String(i.detail).replace(/\|/g, '/')} |`));
      if (f.issues.length > esempi.length) L.push(`| … | altri ${f.issues.length - esempi.length} casi nel file audit.json |`);
      L.push('');
    }
  }
  return L.join('\n');
}

function reportTriage(triage, site) {
  const C = triage.conteggi;
  const gruppi = [
    ['eliminare', '1. Da eliminare', 'Contenuti duplicati, sovrapposti alle pagine servizio o troppo scarni per posizionarsi. Per ognuno è indicato se serve un redirect 301 prima del cestino.'],
    ['accorpare', '2. Da accorpare', 'Contenuti validi ma sovrapposti fra loro o in competizione sulla stessa keyword: si uniscono nell’articolo indicato come principale e si reindirizzano gli altri.'],
    ['riscrivere', '3. Da riscrivere o aggiornare', 'Argomento valido, contenuto sotto la soglia competitiva: vanno ampliati, strutturati e arricchiti di dati.'],
    ['mantenere', '4. Da mantenere', 'Articoli con lunghezza, struttura e unicità adeguate: serve solo ottimizzazione di meta e link interni.'],
  ];

  const L = [];
  L.push(`# Triage editoriale — ${triage.totale} articoli pubblicati`);
  L.push('');
  L.push('| Categoria | Articoli | Quota |');
  L.push('|---|---|---|');
  gruppi.forEach(([k, label]) => {
    const n = C[k] || 0;
    L.push(`| ${label} | **${n}** | ${Math.round((n / triage.totale) * 100)}% |`);
  });
  L.push(`| **Totale** | **${triage.totale}** | 100% |`);
  L.push('');

  for (const [key, label, intro] of gruppi) {
    const items = triage.articoli.filter((a) => a.categoria === key)
      .sort((a, b) => a.qualita - b.qualita);
    L.push(`## ${label} — ${items.length} articoli`);
    L.push('');
    L.push(intro);
    L.push('');
    items.forEach((a, i) => {
      L.push(`${i + 1}. **${a.titolo}**  `);
      L.push(`   ${a.url}  `);
      L.push(`   ${a.parole} parole · qualità ${a.qualita}/100 · intento ${a.intento} · pubblicato ${a.dataPubblicazione}  `);
      L.push(`   Motivo: ${a.motivo}  `);
      L.push(`   Azione: ${a.azione}${a.redirect301 ? `  \n   Redirect 301 → ${a.redirect301.url}` : ''}`);
      L.push('');
    });
  }
  return L.join('\n');
}

module.exports = { reportAudit, reportTriage };
