'use strict';
/** Versione della dashboard pensata per la pubblicazione come pagina web. */

const esc = (s) => String(s === null || s === undefined ? '' : s)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

function buildArtifact({ audit, triage, linkPlan, metaPlan }) {
  // Si imbarcano solo i campi che la pagina mostra davvero.
  const dati = {
    sito: audit.site,
    generato: audit.generatedAt.slice(0, 10),
    globale: audit.global,
    gravita: audit.bySeverity,
    problemi: audit.totalIssues,
    aree: Object.values(audit.scores).map((c) => ({ label: c.label, icon: c.icon, score: c.score, findings: c.findings, key: c.key })),
    rilievi: audit.findings.map((f) => ({
      id: f.id, sev: f.severity, cat: f.category, titolo: f.title, perche: f.why, come: f.fix,
      auto: f.auto, n: f.count,
      esempi: f.issues.slice(0, 6).map((i) => ({ ref: i.ref, det: i.detail })),
    })),
    articoli: triage.articoli.map((a) => ({
      t: a.titolo, u: a.url, c: a.categoria, w: a.parole, q: a.qualita, i: a.intento,
      m: a.motivo, az: a.azione, r: a.redirect301 ? a.redirect301.url : '',
    })),
    conteggi: triage.conteggi,
    link: linkPlan.statistiche,
    meta: {
      title: metaPlan.filter((m) => m.titoloCambiato).length,
      desc: metaPlan.filter((m) => m.descrizioneCambiata).length,
      slug: metaPlan.filter((m) => m.slugCambiato).length,
    },
  };

  return `<title>Audit SEO Max Digital Innovation</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Source+Sans+3:ital,wght@0,400;0,600;1,400&display=swap">
<style>
  :root{
    --ink:#111a1d; --ink-2:#3d4c50; --ink-3:#6b7c80;
    --paper:#f2f5f4; --surface:#ffffff; --line:#dbe3e1; --line-2:#eef2f1;
    --accent:#0b6e6e; --accent-soft:#e2efec;
    --crit:#a4262c; --crit-bg:#fbeceb;
    --high:#a6600d; --high-bg:#fdf3e3;
    --med:#7a6a12; --med-bg:#faf7e4;
    --low:#5a6a6d; --low-bg:#eef2f1;
    --ok:#1c6b4d;
    --font-d:"Bricolage Grotesque","Trebuchet MS",system-ui,sans-serif;
    --font-b:"Source Sans 3","Segoe UI",system-ui,sans-serif;
    --font-m:ui-monospace,SFMono-Regular,"SF Mono",Menlo,Consolas,monospace;
  }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]){
      --ink:#e8efee; --ink-2:#a9bcba; --ink-3:#7d908e;
      --paper:#0d1516; --surface:#131f20; --line:#26383a; --line-2:#1b2a2b;
      --accent:#5fd0c4; --accent-soft:#16302f;
      --crit:#ff8f88; --crit-bg:#33191a;
      --high:#e8b169; --high-bg:#2f2413;
      --med:#d6cd7a; --med-bg:#2a2814;
      --low:#9fb0b2; --low-bg:#1b2a2b;
      --ok:#63c69c;
    }
  }
  :root[data-theme="dark"]{
    --ink:#e8efee; --ink-2:#a9bcba; --ink-3:#7d908e;
    --paper:#0d1516; --surface:#131f20; --line:#26383a; --line-2:#1b2a2b;
    --accent:#5fd0c4; --accent-soft:#16302f;
    --crit:#ff8f88; --crit-bg:#33191a;
    --high:#e8b169; --high-bg:#2f2413;
    --med:#d6cd7a; --med-bg:#2a2814;
    --low:#9fb0b2; --low-bg:#1b2a2b;
    --ok:#63c69c;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--paper);color:var(--ink);font-family:var(--font-b);font-size:16px;line-height:1.55;-webkit-font-smoothing:antialiased}
  .wrap{max-width:1120px;margin:0 auto;padding-inline:20px;padding-block:0 72px}
  a{color:var(--accent)}
  h1,h2,h3{font-family:var(--font-d);text-wrap:balance;margin:0}
  .eyebrow{font-family:var(--font-m);font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3)}

  header{border-bottom:1px solid var(--line);background:var(--surface)}
  header .wrap{padding-block:34px 28px;display:flex;flex-wrap:wrap;gap:28px;align-items:flex-end;justify-content:space-between}
  h1{font-size:clamp(26px,4vw,38px);font-weight:800;letter-spacing:-.02em;line-height:1.1}
  .sottotitolo{color:var(--ink-2);margin-top:8px;max-width:52ch}
  .punteggio{display:flex;align-items:baseline;gap:6px;font-family:var(--font-d);font-weight:800;letter-spacing:-.03em}
  .punteggio b{font-size:60px;line-height:1;color:var(--crit)}
  .punteggio span{font-size:20px;color:var(--ink-3)}

  .sommario{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:1px;background:var(--line);border:1px solid var(--line);border-radius:4px;overflow:hidden;margin-top:28px}
  .sommario div{background:var(--surface);padding:16px 18px}
  .sommario b{display:block;font-family:var(--font-d);font-size:27px;font-weight:700;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
  .sommario small{color:var(--ink-3);font-size:13px}

  section{margin-top:44px}
  section > h2{font-size:21px;font-weight:700;letter-spacing:-.01em;margin-bottom:4px}
  .intro{color:var(--ink-2);margin:0 0 18px;max-width:70ch}

  .aree{display:grid;gap:10px}
  .area{display:grid;grid-template-columns:minmax(0,1fr) 88px 44px;gap:14px;align-items:center;padding:11px 14px;background:var(--surface);border:1px solid var(--line);border-radius:4px}
  .area .nome{font-weight:600;min-width:0}
  .area .nome small{display:block;color:var(--ink-3);font-weight:400;font-size:12.5px}
  .track{height:6px;background:var(--line-2);border-radius:99px;overflow:hidden}
  .track i{display:block;height:6px;border-radius:99px}
  .area .val{font-family:var(--font-d);font-weight:700;text-align:right;font-variant-numeric:tabular-nums}

  .filtri{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
  .chip{font-family:var(--font-b);font-size:13.5px;border:1px solid var(--line);background:var(--surface);color:var(--ink-2);border-radius:99px;padding:6px 14px;cursor:pointer}
  .chip b{font-variant-numeric:tabular-nums}
  .chip[aria-pressed="true"]{background:var(--ink);color:var(--paper);border-color:var(--ink)}
  input[type="search"]{width:100%;font-family:var(--font-b);font-size:15px;padding:10px 14px;border:1px solid var(--line);border-radius:4px;background:var(--surface);color:var(--ink);margin-bottom:14px}
  input[type="search"]:focus-visible,.chip:focus-visible,summary:focus-visible{outline:2px solid var(--accent);outline-offset:2px}

  details.rilievo{background:var(--surface);border:1px solid var(--line);border-left:3px solid var(--sev,var(--line));border-radius:4px;margin-bottom:7px}
  details.rilievo summary{cursor:pointer;padding:12px 15px;display:grid;grid-template-columns:auto auto minmax(0,1fr) auto;gap:11px;align-items:center;list-style:none}
  details.rilievo summary::-webkit-details-marker{display:none}
  .sev{font-family:var(--font-m);font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;padding:3px 8px;border-radius:3px;white-space:nowrap}
  .rid{font-family:var(--font-m);font-size:12px;color:var(--ink-3)}
  .rtit{font-weight:600;min-width:0}
  .rnum{font-family:var(--font-d);font-weight:700;font-variant-numeric:tabular-nums;color:var(--ink-2);white-space:nowrap}
  .rbody{padding:0 15px 16px;display:grid;gap:9px;border-top:1px solid var(--line-2);margin-top:2px;padding-top:13px}
  .rbody p{margin:0;max-width:78ch}
  .rbody .k{font-family:var(--font-m);font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--ink-3);display:block}
  .badge{display:inline-block;font-size:12px;font-weight:600;padding:2px 9px;border-radius:99px}
  .badge.auto{background:var(--accent-soft);color:var(--accent)}
  .badge.man{background:var(--crit-bg);color:var(--crit)}
  .esempi{font-family:var(--font-m);font-size:12.5px;color:var(--ink-2);display:grid;gap:3px;background:var(--low-bg);padding:10px 12px;border-radius:4px;overflow-x:auto}
  .esempi span{white-space:nowrap}
  .esempi em{color:var(--ink-3);font-style:normal}

  .tabellabox{overflow-x:auto;border:1px solid var(--line);border-radius:4px;background:var(--surface)}
  table{width:100%;border-collapse:collapse;font-size:14.5px;min-width:640px}
  th{text-align:left;font-family:var(--font-m);font-size:10.5px;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-3);font-weight:400;padding:11px 14px;border-bottom:1px solid var(--line);position:sticky;top:0;background:var(--surface)}
  td{padding:11px 14px;border-bottom:1px solid var(--line-2);vertical-align:top}
  tr:last-child td{border-bottom:none}
  td.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
  .stato{font-family:var(--font-m);font-size:11px;letter-spacing:.06em;text-transform:uppercase;white-space:nowrap}
  .motivo{color:var(--ink-3);font-size:13px;margin-top:3px;max-width:62ch}
  .azione{font-size:13px;color:var(--ink-2);max-width:34ch}
  .vuoto{padding:26px;text-align:center;color:var(--ink-3)}

  ol.passi{counter-reset:p;list-style:none;padding:0;margin:0;display:grid;gap:12px}
  ol.passi li{counter-increment:p;background:var(--surface);border:1px solid var(--line);border-radius:4px;padding:14px 16px 14px 52px;position:relative}
  ol.passi li::before{content:counter(p);position:absolute;left:16px;top:13px;font-family:var(--font-d);font-weight:700;color:var(--accent);font-size:19px}
  ol.passi b{display:block;margin-bottom:2px}
  ol.passi span{color:var(--ink-2);font-size:14.5px}

  footer{margin-top:52px;padding-top:20px;border-top:1px solid var(--line);color:var(--ink-3);font-size:13.5px}
  @media (max-width:640px){
    details.rilievo summary{grid-template-columns:auto minmax(0,1fr);row-gap:6px}
    .rid{grid-column:1}
    .rnum{justify-self:end}
    .area{grid-template-columns:minmax(0,1fr) 56px}
    .area .track{display:none}
  }
  @media (prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important}}
</style>

<header>
  <div class="wrap">
    <div>
      <p class="eyebrow">Audit tecnico · <span id="data"></span></p>
      <h1>Max Digital Innovation</h1>
      <p class="sottotitolo">Diagnosi SEO e GEO di maxdigitalinnovation.it: 65 controlli su 311 articoli, 15 pagine e 95 file media, con la classificazione editoriale di ogni contenuto pubblicato.</p>
    </div>
    <div>
      <p class="eyebrow">Punteggio complessivo</p>
      <p class="punteggio"><b id="globale">–</b><span>/100</span></p>
    </div>
  </div>
</header>

<div class="wrap">
  <div class="sommario" id="sommario"></div>

  <section>
    <h2>Dove il sito perde terreno</h2>
    <p class="intro">Ogni area vale un peso diverso sul punteggio finale. Le due in fondo alla lista sono quelle che oggi bloccano l'indicizzazione: nessun dato strutturato per i motori generativi e 218 pagine che nessun'altra pagina del sito collega.</p>
    <div class="aree" id="aree"></div>
  </section>

  <section>
    <h2>Problemi rilevati</h2>
    <p class="intro">65 controlli non superati, ordinati per gravità. Per ciascuno: quanto è esteso, perché incide sul posizionamento e se il toolkit lo risolve da solo.</p>
    <div class="filtri" id="filtriSev"></div>
    <input type="search" id="cercaRilievi" placeholder="Cerca fra i problemi…" aria-label="Cerca fra i problemi">
    <div id="rilievi"></div>
  </section>

  <section>
    <h2>Triage dei 311 articoli</h2>
    <p class="intro">Classificazione di ogni articolo pubblicato in base a qualità misurata, sovrapposizione reale fra i testi e competizione sulle stesse keyword delle pagine servizio.</p>
    <div class="filtri" id="filtriCat"></div>
    <input type="search" id="cercaArticoli" placeholder="Cerca un articolo, una keyword, un'azione…" aria-label="Cerca un articolo">
    <div class="tabellabox">
      <table>
        <thead><tr><th>Stato</th><th>Articolo</th><th class="num">Parole</th><th class="num">Qualità</th><th>Intento</th><th>Azione</th></tr></thead>
        <tbody id="tabella"></tbody>
      </table>
    </div>
    <p class="intro" id="contatore" style="margin-top:10px"></p>
  </section>

  <section>
    <h2>Ordine di esecuzione</h2>
    <p class="intro">I primi due passi valgono più di tutto il resto: finché due plugin SEO scrivono gli stessi tag e mancano telefono, indirizzo e partita IVA, ogni altra ottimizzazione lavora su fondamenta instabili.</p>
    <ol class="passi">
      <li><b>Disattivare Yoast, tenere Rank Math</b><span>Sono attivi entrambi e stampano due volte title, canonical, Open Graph e dati strutturati.</span></li>
      <li><b>Compilare i dati aziendali</b><span>Telefono, indirizzo, CAP, partita IVA, coordinate e scheda Google Business: oggi non compaiono in nessuna pagina del sito.</span></li>
      <li><b>Installare il plugin generato</b><span>Applica meta ottimizzate, JSON-LD, link interni automatici, alt immagini, llms.txt e direttive per i crawler AI.</span></li>
      <li><b>Pubblicare Chi siamo e Contatti</b><span>Oggi esistono solo come ancore della home; la versione in cestino non è indicizzabile.</span></li>
      <li><b>Aprire il blog dal menu</b><span>311 articoli non sono raggiungibili dalla navigazione: il crawler li trova solo dalla sitemap.</span></li>
      <li><b>Eseguire il triage con i redirect 301</b><span>Prima i reindirizzamenti, poi il cestino: mai il contrario.</span></li>
    </ol>
  </section>

  <footer>Generato da seo-geo-toolkit sull'esportazione WordPress del <span id="data2"></span>. Il dettaglio completo dei 5.974 rilievi e i file di correzione sono nella cartella <code>output/</code> del progetto.</footer>
</div>

<script>
const D = ${JSON.stringify(dati)};

const SEV = {
  critical:{l:'Critico',c:'var(--crit)',b:'var(--crit-bg)'},
  high:{l:'Alto',c:'var(--high)',b:'var(--high-bg)'},
  medium:{l:'Medio',c:'var(--med)',b:'var(--med-bg)'},
  low:{l:'Basso',c:'var(--low)',b:'var(--low-bg)'}
};
const CAT = {
  eliminare:{l:'Eliminare',c:'var(--crit)'},
  accorpare:{l:'Accorpare',c:'var(--high)'},
  riscrivere:{l:'Riscrivere',c:'var(--accent)'},
  mantenere:{l:'Mantenere',c:'var(--ok)'}
};
const nf = new Intl.NumberFormat('it-IT');
const escape = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

document.getElementById('data').textContent = D.generato;
document.getElementById('data2').textContent = D.generato;
document.getElementById('globale').textContent = D.globale;

document.getElementById('sommario').innerHTML = [
  ['Problemi rilevati', nf.format(D.problemi), D.gravita.critical + ' critici · ' + D.gravita.high + ' alti'],
  ['Articoli da rivedere', nf.format(D.conteggi.eliminare + D.conteggi.accorpare + D.conteggi.riscrivere), 'su ' + nf.format(D.sito.posts) + ' pubblicati'],
  ['Pagine orfane', nf.format(D.link.orfaniPrima), 'nessun link interno in entrata'],
  ['Meta riscritte', nf.format(D.meta.title + D.meta.desc), D.meta.title + ' title · ' + D.meta.desc + ' description'],
  ['Link interni proposti', nf.format(D.link.linkProposti), 'orfane dopo l\\'intervento: ' + D.link.orfaniDopo]
].map(([k,v,s]) => '<div><small>' + k + '</small><b>' + v + '</b><small>' + s + '</small></div>').join('');

const colore = (s) => s >= 70 ? 'var(--ok)' : s >= 45 ? 'var(--high)' : 'var(--crit)';
document.getElementById('aree').innerHTML = D.aree.slice().sort((a,b) => a.score - b.score).map((a) =>
  '<div class="area"><div class="nome">' + a.icon + ' ' + escape(a.label) +
  '<small>' + a.findings + ' controlli non superati</small></div>' +
  '<div class="track"><i style="width:' + Math.max(2, a.score) + '%;background:' + colore(a.score) + '"></i></div>' +
  '<div class="val" style="color:' + colore(a.score) + '">' + a.score + '</div></div>').join('');

let filtroSev = 'all', filtroCat = 'all';

const contaSev = D.rilievi.reduce((a,r) => (a[r.sev] = (a[r.sev]||0)+1, a), {});
document.getElementById('filtriSev').innerHTML =
  '<button class="chip" data-sev="all" aria-pressed="true">Tutti <b>' + D.rilievi.length + '</b></button>' +
  Object.entries(SEV).map(([k,v]) => contaSev[k] ? '<button class="chip" data-sev="' + k + '" aria-pressed="false">' + v.l + ' <b>' + contaSev[k] + '</b></button>' : '').join('');

function renderRilievi() {
  const q = document.getElementById('cercaRilievi').value.toLowerCase();
  const el = D.rilievi.filter((r) => (filtroSev === 'all' || r.sev === filtroSev)
    && (r.titolo + ' ' + r.perche + ' ' + r.come + ' ' + r.id).toLowerCase().includes(q));
  document.getElementById('rilievi').innerHTML = el.length ? el.map((r) =>
    '<details class="rilievo" style="--sev:' + SEV[r.sev].c + '">' +
      '<summary>' +
        '<span class="sev" style="color:' + SEV[r.sev].c + ';background:' + SEV[r.sev].b + '">' + SEV[r.sev].l + '</span>' +
        '<span class="rid">' + r.id + '</span>' +
        '<span class="rtit">' + escape(r.titolo) + '</span>' +
        '<span class="rnum">' + nf.format(r.n) + '</span>' +
      '</summary>' +
      '<div class="rbody">' +
        '<p><span class="k">Perché conta</span>' + escape(r.perche) + '</p>' +
        '<p><span class="k">Come si risolve</span>' + escape(r.come) + ' ' +
          (r.auto ? '<span class="badge auto">risolto dal toolkit</span>' : '<span class="badge man">intervento manuale</span>') + '</p>' +
        (r.esempi.length ? '<div><span class="k">Esempi</span><div class="esempi">' +
          r.esempi.map((e) => '<span>' + escape(e.ref) + ' <em>— ' + escape(e.det) + '</em></span>').join('') +
          (r.n > r.esempi.length ? '<span><em>… altri ' + nf.format(r.n - r.esempi.length) + ' casi</em></span>' : '') +
        '</div></div>' : '') +
      '</div>' +
    '</details>').join('') : '<p class="vuoto">Nessun problema corrisponde alla ricerca.</p>';
}

document.getElementById('filtriSev').addEventListener('click', (e) => {
  const b = e.target.closest('button'); if (!b) return;
  document.querySelectorAll('#filtriSev .chip').forEach((c) => c.setAttribute('aria-pressed', String(c === b)));
  filtroSev = b.dataset.sev; renderRilievi();
});
document.getElementById('cercaRilievi').addEventListener('input', renderRilievi);

const ordine = ['eliminare','accorpare','riscrivere','mantenere'];
document.getElementById('filtriCat').innerHTML =
  '<button class="chip" data-cat="all" aria-pressed="true">Tutti <b>' + D.articoli.length + '</b></button>' +
  ordine.map((k) => '<button class="chip" data-cat="' + k + '" aria-pressed="false">' + CAT[k].l + ' <b>' + (D.conteggi[k]||0) + '</b></button>').join('');

function renderArticoli() {
  const q = document.getElementById('cercaArticoli').value.toLowerCase();
  const el = D.articoli
    .filter((a) => (filtroCat === 'all' || a.c === filtroCat) && (a.t + ' ' + a.m + ' ' + a.az + ' ' + a.i).toLowerCase().includes(q))
    .sort((a,b) => ordine.indexOf(a.c) - ordine.indexOf(b.c) || a.q - b.q);
  document.getElementById('tabella').innerHTML = el.length ? el.map((a) =>
    '<tr>' +
      '<td><span class="stato" style="color:' + CAT[a.c].c + '">' + CAT[a.c].l + '</span></td>' +
      '<td><a href="' + escape(a.u) + '" target="_blank" rel="noopener">' + escape(a.t) + '</a>' +
        '<div class="motivo">' + escape(a.m) + (a.r ? ' → 301 verso ' + escape(a.r.replace(/^https?:\\/\\/[^/]+/, '')) : '') + '</div></td>' +
      '<td class="num">' + nf.format(a.w) + '</td>' +
      '<td class="num" style="color:' + (a.q >= 58 ? 'var(--ok)' : a.q >= 45 ? 'var(--high)' : 'var(--crit)') + '">' + a.q + '</td>' +
      '<td>' + escape(a.i) + '</td>' +
      '<td class="azione">' + escape(a.az) + '</td>' +
    '</tr>').join('') : '<tr><td colspan="6" class="vuoto">Nessun articolo corrisponde alla ricerca.</td></tr>';
  document.getElementById('contatore').textContent = el.length + ' articoli mostrati su ' + D.articoli.length + '.';
}

document.getElementById('filtriCat').addEventListener('click', (e) => {
  const b = e.target.closest('button'); if (!b) return;
  document.querySelectorAll('#filtriCat .chip').forEach((c) => c.setAttribute('aria-pressed', String(c === b)));
  filtroCat = b.dataset.cat; renderArticoli();
});
document.getElementById('cercaArticoli').addEventListener('input', renderArticoli);

renderRilievi();
renderArticoli();
</script>`;
}

module.exports = { buildArtifact };
