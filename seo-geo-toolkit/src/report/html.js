'use strict';

const SEV = {
  critical: { label: 'Critico', color: '#b42318', bg: '#fef3f2' },
  high: { label: 'Alto', color: '#b54708', bg: '#fffaeb' },
  medium: { label: 'Medio', color: '#854a0e', bg: '#fefbe8' },
  low: { label: 'Basso', color: '#475467', bg: '#f8fafc' },
};

const CAT_TRIAGE = {
  eliminare: { label: 'Da eliminare', color: '#b42318' },
  accorpare: { label: 'Da accorpare', color: '#b54708' },
  riscrivere: { label: 'Da riscrivere', color: '#1570ef' },
  mantenere: { label: 'Da mantenere', color: '#067647' },
};

const esc = (s) => String(s === null || s === undefined ? '' : s)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

function anelloPunteggio(score) {
  const colore = score >= 70 ? '#067647' : score >= 45 ? '#b54708' : '#b42318';
  const gradi = Math.round((score / 100) * 360);
  return `<div class="ring" style="--deg:${gradi}deg;--col:${colore}"><span>${score}</span><small>/100</small></div>`;
}

function buildHtmlReport({ audit, triage, linkPlan, metaPlan, contentPlan, cfg }) {
  const catRows = Object.values(audit.scores).sort((a, b) => a.score - b.score).map((c) => `
    <tr>
      <td>${c.icon} ${esc(c.label)}</td>
      <td class="num">${c.findings}</td>
      <td><div class="bar"><i style="width:${c.score}%;background:${c.score >= 70 ? '#067647' : c.score >= 45 ? '#b54708' : '#b42318'}"></i></div></td>
      <td class="num"><strong>${c.score}</strong></td>
    </tr>`).join('');

  const findingCards = audit.findings.map((f) => `
    <details class="finding" data-sev="${f.severity}" data-cat="${f.category}">
      <summary>
        <span class="pill" style="color:${SEV[f.severity].color};background:${SEV[f.severity].bg}">${SEV[f.severity].label}</span>
        <span class="fid">${f.id}</span>
        <span class="ftitle">${esc(f.title)}</span>
        <span class="fcount">${f.count}</span>
      </summary>
      <div class="fbody">
        <p><strong>Perché conta.</strong> ${esc(f.why)}</p>
        <p><strong>Come si risolve.</strong> ${esc(f.fix)} ${f.auto ? '<span class="auto">risolto dal toolkit</span>' : '<span class="manual">intervento manuale</span>'}</p>
        <table class="mini">
          <thead><tr><th>URL / elemento</th><th>Dettaglio</th></tr></thead>
          <tbody>
            ${f.issues.slice(0, 15).map((i) => `<tr><td class="mono">${esc(i.ref)}</td><td>${esc(i.detail)}</td></tr>`).join('')}
            ${f.issues.length > 15 ? `<tr><td colspan="2" class="more">… altri ${f.issues.length - 15} casi (elenco completo in audit.json)</td></tr>` : ''}
          </tbody>
        </table>
      </div>
    </details>`).join('');

  const triageRows = triage.articoli
    .sort((a, b) => ['eliminare', 'accorpare', 'riscrivere', 'mantenere'].indexOf(a.categoria) - ['eliminare', 'accorpare', 'riscrivere', 'mantenere'].indexOf(b.categoria) || a.qualita - b.qualita)
    .map((a) => `
      <tr data-cat="${a.categoria}">
        <td><span class="tag" style="color:${CAT_TRIAGE[a.categoria].color}">${CAT_TRIAGE[a.categoria].label}</span></td>
        <td><a href="${esc(a.url)}" target="_blank" rel="noopener">${esc(a.titolo)}</a><div class="sub">${esc(a.motivo)}</div></td>
        <td class="num">${a.parole}</td>
        <td class="num">${a.qualita}</td>
        <td>${esc(a.intento)}</td>
        <td>${esc(a.azione)}</td>
      </tr>`).join('');

  const conteggi = Object.entries(CAT_TRIAGE).map(([k, v]) => `
    <button class="chip" data-filter="${k}" style="--c:${v.color}">${v.label} <b>${triage.conteggi[k] || 0}</b></button>`).join('');

  return `<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Audit SEO &amp; GEO — ${esc(audit.site.name)}</title>
<style>
  :root{--bg:#f6f7f9;--card:#fff;--ink:#101828;--muted:#667085;--line:#e4e7ec;--accent:#1570ef}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
  .wrap{max-width:1180px;margin:0 auto;padding:24px 16px 80px}
  header.top{background:linear-gradient(135deg,#0b1220,#1d2939);color:#fff;padding:36px 0;margin-bottom:24px}
  header.top .wrap{padding-bottom:0}
  h1{font-size:26px;margin:0 0 6px}
  .lede{color:#98a2b3;margin:0}
  .grid{display:grid;gap:16px}
  .cards{grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin:24px 0}
  .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:18px}
  .card h3{margin:0 0 4px;font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
  .card .big{font-size:30px;font-weight:700}
  .ring{--deg:0deg;--col:#1570ef;width:120px;height:120px;border-radius:50%;display:grid;place-content:center;text-align:center;
    background:conic-gradient(var(--col) var(--deg),#e4e7ec 0);position:relative}
  .ring::before{content:"";position:absolute;inset:10px;background:var(--card);border-radius:50%}
  .ring span,.ring small{position:relative;z-index:1}
  .ring span{font-size:34px;font-weight:700;line-height:1}
  .ring small{color:var(--muted)}
  section{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:22px;margin-bottom:20px}
  section h2{margin:0 0 14px;font-size:19px}
  table{width:100%;border-collapse:collapse;font-size:14px}
  th,td{text-align:left;padding:9px 10px;border-bottom:1px solid var(--line);vertical-align:top}
  th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
  td.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
  .bar{background:#eef0f3;border-radius:99px;height:9px;min-width:120px}
  .bar i{display:block;height:9px;border-radius:99px}
  .finding{border:1px solid var(--line);border-radius:10px;margin-bottom:8px;background:#fff}
  .finding summary{cursor:pointer;padding:12px 14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .pill{font-size:11px;font-weight:700;padding:3px 9px;border-radius:99px;text-transform:uppercase;letter-spacing:.04em}
  .fid{font:12px ui-monospace,SFMono-Regular,Menlo,monospace;color:var(--muted)}
  .ftitle{flex:1;min-width:220px;font-weight:600}
  .fcount{background:#eef2f6;border-radius:99px;padding:2px 10px;font-size:12px;font-weight:700;color:#344054}
  .fbody{padding:0 14px 16px;border-top:1px solid var(--line)}
  .auto{background:#ecfdf3;color:#067647;font-size:12px;padding:2px 8px;border-radius:99px;font-weight:600}
  .manual{background:#fef3f2;color:#b42318;font-size:12px;padding:2px 8px;border-radius:99px;font-weight:600}
  .mini{margin-top:10px;background:#fbfcfd;border:1px solid var(--line);border-radius:8px}
  .mono{font:12px ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all}
  .more{color:var(--muted);font-style:italic}
  .chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
  .chip{border:1px solid var(--line);background:#fff;border-radius:99px;padding:7px 14px;cursor:pointer;font-size:13px;color:var(--c)}
  .chip.on{background:var(--c);color:#fff;border-color:var(--c)}
  .tag{font-size:12px;font-weight:700;white-space:nowrap}
  .sub{color:var(--muted);font-size:12px;margin-top:3px}
  a{color:var(--accent);text-decoration:none}
  a:hover{text-decoration:underline}
  .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
  input[type=search]{flex:1;min-width:200px;padding:9px 12px;border:1px solid var(--line);border-radius:8px;font-size:14px}
  .note{background:#fffaeb;border:1px solid #fedf89;border-radius:10px;padding:14px;font-size:14px}
  @media(max-width:640px){.ring{width:96px;height:96px}.ring span{font-size:26px}}
</style>
</head>
<body>
<header class="top"><div class="wrap">
  <h1>Audit SEO &amp; GEO — ${esc(audit.site.name)}</h1>
  <p class="lede">${esc(audit.site.url)} · analisi del ${audit.generatedAt.slice(0, 10)} · ${audit.site.posts} articoli, ${audit.site.pages} pagine, ${audit.site.attachments} file media</p>
</div></header>

<div class="wrap">

<div class="grid cards">
  <div class="card" style="display:flex;gap:18px;align-items:center">
    ${anelloPunteggio(audit.global)}
    <div><h3>Punteggio complessivo</h3><div>${audit.bySeverity.critical} problemi critici<br>${audit.bySeverity.high} di livello alto</div></div>
  </div>
  <div class="card"><h3>Problemi rilevati</h3><div class="big">${audit.totalIssues}</div><div class="sub">su ${audit.findings.length} controlli non superati</div></div>
  <div class="card"><h3>Articoli da rivedere</h3><div class="big">${(triage.conteggi.eliminare || 0) + (triage.conteggi.accorpare || 0) + (triage.conteggi.riscrivere || 0)}</div><div class="sub">su ${triage.totale} pubblicati</div></div>
  <div class="card"><h3>Link interni proposti</h3><div class="big">${linkPlan.statistiche.linkProposti}</div><div class="sub">pagine orfane: da ${linkPlan.statistiche.orfaniPrima} a ${linkPlan.statistiche.orfaniDopo}</div></div>
</div>

<section>
  <h2>Punteggio per area</h2>
  <table><thead><tr><th>Area</th><th class="num">Rilievi</th><th>Livello</th><th class="num">Punteggio</th></tr></thead><tbody>${catRows}</tbody></table>
</section>

<section>
  <h2>Problemi rilevati (${audit.findings.length})</h2>
  <div class="toolbar">
    <input type="search" id="q" placeholder="Cerca fra i problemi…">
  </div>
  <div id="findings">${findingCards}</div>
</section>

<section>
  <h2>Triage editoriale — ${triage.totale} articoli</h2>
  <div class="chips">${conteggi}<button class="chip on" data-filter="all" style="--c:#344054">Tutti <b>${triage.totale}</b></button></div>
  <div class="toolbar"><input type="search" id="qt" placeholder="Cerca un articolo…"></div>
  <table id="triage"><thead><tr><th>Categoria</th><th>Articolo</th><th class="num">Parole</th><th class="num">Qualità</th><th>Intento</th><th>Azione</th></tr></thead><tbody>${triageRows}</tbody></table>
</section>

<section>
  <h2>Cosa correggere e in che ordine</h2>
  <ol>
    <li><strong>Disattivare uno dei due plugin SEO</strong> (Rank Math e Yoast sono entrambi attivi): elimina title, canonical e schema duplicati.</li>
    <li><strong>Compilare i dati aziendali</strong> in <code>config.json</code> (telefono, indirizzo, P.IVA, scheda Google Business): senza questi il posizionamento locale non parte.</li>
    <li><strong>Installare il plugin generato</strong>: applica meta, dati strutturati, link interni, alt immagini, llms.txt e direttive per i crawler AI.</li>
    <li><strong>Pubblicare le pagine Chi siamo e Contatti</strong> con i template generati e collegarle nel menu.</li>
    <li><strong>Eseguire il triage editoriale</strong>: eliminare, accorpare e riscrivere secondo la tabella qui sopra.</li>
    <li><strong>Impostare i redirect 301</strong> dal file redirect-301.csv prima di cestinare qualunque contenuto.</li>
  </ol>
  <p class="note"><strong>Nota.</strong> ${esc(cfg.azienda.nome)} non espone oggi telefono, indirizzo e partita IVA in nessuna pagina: sono i dati che Google usa per collegare il sito alla scheda locale. Finché restano assenti, il capitolo "SEO locale" resta il freno principale.</p>
</section>

</div>
<script>
  document.getElementById('q').addEventListener('input', (e) => {
    const v = e.target.value.toLowerCase();
    document.querySelectorAll('#findings .finding').forEach((el) => {
      el.style.display = el.textContent.toLowerCase().includes(v) ? '' : 'none';
    });
  });
  const righe = () => Array.from(document.querySelectorAll('#triage tbody tr'));
  let filtro = 'all';
  const applica = () => {
    const v = document.getElementById('qt').value.toLowerCase();
    righe().forEach((tr) => {
      const okCat = filtro === 'all' || tr.dataset.cat === filtro;
      const okTxt = tr.textContent.toLowerCase().includes(v);
      tr.style.display = okCat && okTxt ? '' : 'none';
    });
  };
  document.querySelectorAll('.chip').forEach((b) => b.addEventListener('click', () => {
    document.querySelectorAll('.chip').forEach((x) => x.classList.remove('on'));
    b.classList.add('on');
    filtro = b.dataset.filter;
    applica();
  }));
  document.getElementById('qt').addEventListener('input', applica);
</script>
</body>
</html>`;
}

module.exports = { buildHtmlReport };
