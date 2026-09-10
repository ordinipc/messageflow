'use strict';
/** Template HTML pronti per le pagine mancanti e per il blocco NAP. */

const val = (v) => (v && !/^DA_COMPILARE/i.test(String(v)) ? v : null);
const ph = (v, fallback) => (val(v) ? v : `<mark>${fallback}</mark>`);

function paginaContatti(cfg) {
  const a = cfg.azienda;
  const ind = a.indirizzo;
  return `<!-- Pagina: Contatti — slug consigliato: /contatti/ -->
<h1>Contatti — ${a.nome}, web agency a ${ind.citta}</h1>

<div class="mdi-in-breve"><p><strong>In breve:</strong> ${a.nome} è una web agency con sede a ${ind.citta}. Rispondiamo entro un giorno lavorativo a richieste di preventivo per siti web, e-commerce, social media, video e stampa. Chiama ${ph(a.telefono, 'INSERIRE TELEFONO')} o scrivi a ${a.email}.</p></div>

<h2>Dove siamo</h2>
<p><strong>${a.nome}</strong><br>
${ph(ind.via, 'INSERIRE VIA E NUMERO CIVICO')}<br>
${ph(ind.cap, 'CAP')} ${ind.citta} (${ind.provincia}) — ${ind.regione}, Italia</p>

<h2>Come raggiungerci</h2>
<p>Siamo a ${ind.citta} e seguiamo clienti in tutta la provincia: ${(a.areaServita || []).join(', ')}.</p>
<!-- Inserire qui l'iframe della mappa Google con l'indirizzo esatto -->

<h2>Parla con noi</h2>
<ul>
  <li>Telefono: <a href="tel:${(val(a.telefono) || '').replace(/[^0-9+]/g, '')}">${ph(a.telefono, 'INSERIRE TELEFONO')}</a></li>
  <li>Cellulare / WhatsApp: <a href="https://wa.me/${val(a.whatsapp) || ''}" rel="nofollow noopener">${ph(a.cellulare, 'INSERIRE CELLULARE')}</a></li>
  <li>Email: <a href="mailto:${a.email}">${a.email}</a></li>
  <li>PEC: ${ph(a.pec, 'INSERIRE PEC (opzionale)')}</li>
</ul>

<h2>Orari</h2>
<p>${(a.orari || []).map((o) => `Da lunedì a venerdì, ${o.apre}–${o.chiude}`).join('<br>')}</p>

<h2>Dati aziendali</h2>
<p>Ragione sociale: ${ph(a.nomeLegale, 'INSERIRE RAGIONE SOCIALE')}<br>
Partita IVA: ${ph(a.partitaIva, 'INSERIRE P.IVA')}</p>

<h2>Domande frequenti</h2>
<h3>Quanto costa un sito web a ${ind.citta}?</h3>
<p>Il costo dipende da numero di pagine, funzionalità e contenuti da produrre. <!-- Inserire una forbice di prezzo reale: è la domanda più cercata e più citata dalle risposte AI. --></p>
<h3>In quanto tempo consegnate un sito?</h3>
<p><!-- Inserire i tempi medi reali, es. 4-6 settimane. --></p>
<h3>Lavorate solo con aziende di ${ind.citta}?</h3>
<p>No: la sede è a ${ind.citta} e seguiamo clienti in tutta la Sicilia e da remoto nel resto d'Italia.</p>

<!-- Modulo di contatto: usare il form Contact Form 7 già presente ("Modulo Max") -->
`;
}

function paginaChiSiamo(cfg) {
  const a = cfg.azienda;
  const autore = (cfg.autori || [])[0] || {};
  return `<!-- Pagina: Chi siamo — slug consigliato: /chi-siamo/ -->
<h1>Chi siamo — ${a.nome}, la web agency di ${a.indirizzo.citta}</h1>

<div class="mdi-in-breve"><p><strong>In breve:</strong> ${a.descrizioneEstesa}</p></div>

<h2>La nostra storia</h2>
<p>Nata nel ${ph(a.fondazione, 'INSERIRE ANNO DI FONDAZIONE')}, ${a.nome} <!-- Raccontare come e perché è nata l'agenzia: è la parte che Google e i modelli AI usano per valutare l'esperienza reale. --></p>

<h2>Come lavoriamo</h2>
<ol>
  <li><strong>Analisi</strong> — studio del mercato, dei concorrenti e delle ricerche del pubblico.</li>
  <li><strong>Strategia</strong> — obiettivi misurabili e canali scelti in base al budget.</li>
  <li><strong>Produzione</strong> — sito, contenuti, video e campagne.</li>
  <li><strong>Misurazione</strong> — report mensile con dati di traffico, contatti e vendite.</li>
</ol>

<h2>Il team</h2>
<h3>${ph(autore.nome, 'NOME')} ${ph(autore.cognome, 'COGNOME')} — ${ph(autore.ruolo, 'RUOLO')}</h3>
<p>${ph(autore.bio, 'INSERIRE BIOGRAFIA: anni di esperienza, specializzazione, risultati')}</p>
<!-- Aggiungere una scheda per ogni persona del team, con foto reale. -->

<h2>Numeri</h2>
<table>
  <tr><th>Anni di attività</th><td><!-- dato reale --></td></tr>
  <tr><th>Progetti realizzati</th><td><!-- dato reale --></td></tr>
  <tr><th>Clienti attivi</th><td><!-- dato reale --></td></tr>
  <tr><th>Settori seguiti</th><td><!-- es. ristorazione, sanità, retail --></td></tr>
</table>

<h2>Dove operiamo</h2>
<p>Sede a ${a.indirizzo.citta}. Seguiamo aziende in ${(a.areaServita || []).join(', ')}.</p>

<h2>Domande frequenti</h2>
<h3>Che tipo di aziende seguite?</h3>
<p>PMI e professionisti che vogliono una presenza digitale che porti contatti misurabili.</p>
<h3>Perché scegliere un'agenzia locale?</h3>
<p>Conoscenza diretta del mercato siciliano, incontri in sede e tempi di risposta brevi.</p>
`;
}

function bloccoFooterNap(cfg) {
  const a = cfg.azienda;
  const ind = a.indirizzo;
  return `<!-- Blocco footer: NAP coerente su tutte le pagine. Usare lo shortcode [mdi_nap] oppure questo HTML. -->
<address class="mdi-nap">
  <strong>${a.nome}</strong><br>
  ${ph(ind.via, 'INSERIRE VIA')}, ${ph(ind.cap, 'CAP')} ${ind.citta} (${ind.provincia})<br>
  Tel. <a href="tel:${(val(a.telefono) || '').replace(/[^0-9+]/g, '')}">${ph(a.telefono, 'INSERIRE TELEFONO')}</a><br>
  <a href="mailto:${a.email}">${a.email}</a><br>
  P.IVA ${ph(a.partitaIva, 'INSERIRE P.IVA')}
</address>
`;
}

function buildPages(cfg) {
  return {
    'contatti.html': paginaContatti(cfg),
    'chi-siamo.html': paginaChiSiamo(cfg),
    'footer-nap.html': bloccoFooterNap(cfg),
  };
}

module.exports = { buildPages };
