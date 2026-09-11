<?php
/**
 * Pilota automatico: prepara la coda e la consuma da solo.
 *
 * @package SeoGeoAudit
 * @var array $audit      Riga audit.
 * @var array $cfg        Configurazione.
 * @var bool  $ai_pronto  Chiave Gemini presente.
 * @var bool  $wp_pronto  Collegamento al sito configurato.
 * @var array $stato      Stato della coda.
 * @var bool  $attivo     Deve partire subito.
 * @var array $ultime     Ultime operazioni eseguite.
 * @var array $previsione Quantità previste per ogni tipo.
 */

$in_corso = $stato['attesa'] > 0;

?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>"><?php echo e( $audit['sito_nome'] ); ?></a> › Pilota automatico</p>
	<h1>Pilota automatico</h1>
	<p class="guida">Esegue da solo tutte le correzioni che si possono applicare senza decisioni umane, una dopo l'altra. Lascia questa pagina aperta: va avanti da sé e riprende da dove si era fermata se la chiudi.</p>
</section>

<?php if ( ! $wp_pronto || ! $ai_pronto ) : ?>
	<section class="scheda">
		<h2>Prima di partire</h2>
		<ul>
			<?php if ( ! $wp_pronto ) : ?>
				<li><strong>Collegamento a WordPress mancante.</strong> Senza, il pilota non può scrivere sul sito: indirizzo e token in <a href="?p=impostazioni">Impostazioni</a>, il token si genera nel plugin.</li>
			<?php endif; ?>
			<?php if ( ! $ai_pronto ) : ?>
				<li><strong>Chiave Gemini mancante.</strong> Senza, il pilota esegue comunque meta, redirect e categorie, ma salta riscritture e immagini.</li>
			<?php endif; ?>
		</ul>
	</section>
<?php endif; ?>

<div class="riquadri">
	<div class="riquadro">
		<span class="etichetta">Avanzamento</span>
		<strong id="percentuale"><?php echo (int) $stato['percentuale']; ?>%</strong>
		<span class="sotto"><span id="completate"><?php echo num( $stato['completate'] ); ?></span> di <span id="totale"><?php echo num( $stato['totale'] ); ?></span> operazioni</span>
	</div>
	<div class="riquadro">
		<span class="etichetta">Eseguite</span>
		<strong id="fatte"><?php echo num( $stato['fatto'] ); ?></strong>
		<span class="sotto">senza errori</span>
	</div>
	<div class="riquadro">
		<span class="etichetta">Errori</span>
		<strong id="errori"><?php echo num( $stato['errore'] ); ?></strong>
		<span class="sotto">da rivedere nel registro</span>
	</div>
	<div class="riquadro">
		<span class="etichetta">In coda</span>
		<strong id="attesa"><?php echo num( $stato['attesa'] ); ?></strong>
		<span class="sotto" id="situazione"><?php echo $in_corso ? 'in attesa di essere eseguite' : 'coda vuota'; ?></span>
	</div>
</div>

<div class="barra" style="height:10px;margin-bottom:20px"><i id="avanzamento" class="ok" style="width:<?php echo max( 1, (int) $stato['percentuale'] ); ?>%;height:10px"></i></div>

<?php if ( $in_corso ) : ?>
	<section class="scheda">
		<h2 id="titolo-stato">Esecuzione in corso</h2>
		<p class="guida" id="spiegazione">Il pilota lavora a giri brevi per non superare il tempo massimo del server. Tieni questa scheda aperta.</p>
		<div class="azioni">
			<button class="bottone" type="button" id="pausa">Metti in pausa</button>
			<form method="post" action="?p=pilota-ferma" onsubmit="return confirm('Annullare le operazioni non ancora eseguite? Quelle già fatte restano.')">
				<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
				<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">
				<button class="bottone chiaro" type="submit">Annulla quelle in coda</button>
			</form>
		</div>
	</section>
<?php else : ?>
	<form class="scheda" method="post" action="?p=pilota-avvia">
		<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
		<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">

		<h2>Cosa farà</h2>
		<ul>
			<li><strong><?php echo num( $previsione['meta'] ); ?></strong> <strong>articoli</strong> con title, meta description ed estratto ottimizzati</li>
			<li><strong><?php echo num( $previsione['redirect'] ); ?></strong> redirect 301 per i contenuti rimossi o accorpati</li>
			<li><strong><?php echo num( $previsione['categorie'] ); ?></strong> articoli ricategorizzati</li>
			<li><strong><?php echo num( $previsione['gruppi'] ); ?></strong> gruppi di articoli che si cannibalizzano, fusi in uno solo</li>
			<li><strong><?php echo num( $previsione['riscritture'] ); ?></strong> articoli riscritti e caricati sul sito come bozze</li>
		</ul>

		<h2>Opzioni</h2>

		<label class="scelta">
			<input type="checkbox" name="pagine" value="1">
			<span>
				<strong>Tocca anche le <?php echo num( $previsione['meta_pagine'] ); ?> pagine</strong>
				<small>
					Le pagine servizio sono poche e scritte a mano: il programma propone modifiche solo su
					<?php echo num( $previsione['cambi_pagine'] ); ?> di esse. Sono escluse di default —
					se le hai curate tu, non c'è motivo di farle riscrivere a un algoritmo.
				</small>
			</span>
		</label>

		<label class="scelta">
			<input type="checkbox" name="immagini" value="1">
			<span>
				<strong>Genera le <?php echo num( $previsione['immagini'] ); ?> immagini in evidenza mancanti</strong>
				<small>Richiede un progetto Google con fatturazione attiva. Aggiunge tempo e costo.</small>
			</span>
		</label>

		<label class="scelta">
			<input type="checkbox" name="pubblica" value="1">
			<span>
				<strong>Pubblica le riscritture dentro gli articoli originali</strong>
				<small>
					Senza questa opzione le riscritture restano bozze e le pubblichi tu dopo averle lette.
					Con l'opzione attiva il testo nuovo sostituisce quello vecchio mantenendo URL e data;
					WordPress conserva una revisione, quindi si torna indietro dall'editor.
					<strong>Da sapere:</strong> pubblicare testo generato senza rileggerlo lascia in pagina i
					segnaposto <code>[DA VERIFICARE]</code> ed è ciò che le linee guida antispam di Google
					chiamano abuso di contenuti scalati. La scelta è tua.
				</small>
			</span>
		</label>

		<label class="scelta">
			<input type="checkbox" name="cestina" value="1">
			<span>
				<strong>Sposta nel cestino i <?php echo num( $previsione['cestino'] ); ?> contenuti da eliminare</strong>
				<small>Solo dopo i redirect, e solo nel cestino: da WordPress si recuperano.</small>
			</span>
		</label>

		<p style="margin-top:18px"><button class="bottone" type="submit">Avvia il pilota</button></p>
	</form>
<?php endif; ?>

<section class="scheda">
	<h2>Registro</h2>
	<div class="tabellabox">
		<table>
			<thead><tr><th>Esito</th><th>Operazione</th><th>Risultato</th></tr></thead>
			<tbody id="registro">
			<?php foreach ( $ultime as $r ) : ?>
				<tr>
					<td><span class="tag <?php echo 'fatto' === $r['stato'] ? 'ok' : ( 'errore' === $r['stato'] ? 'grave' : 'basso' ); ?>"><?php echo e( $r['stato'] ); ?></span></td>
					<td><?php echo e( $r['etichetta'] ); ?></td>
					<td class="stretta"><?php echo e( $r['messaggio'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( empty( $ultime ) ) : ?>
				<tr><td colspan="3" class="vuoto">Nessuna operazione eseguita finora.</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</section>

<section class="scheda">
	<h2>Se preferisci lasciarlo lavorare senza browser</h2>
	<p class="guida">Da riga di comando il pilota non ha limiti di tempo e non richiede una scheda aperta:</p>
	<pre class="mono">php cli/pilota.php <?php echo (int) $audit['id']; ?> --avvia --immagini
php cli/pilota.php <?php echo (int) $audit['id']; ?>          # riprende una coda già avviata</pre>
	<p class="nota">Con un cron ogni cinque minuti (<code>php /percorso/cli/pilota.php <?php echo (int) $audit['id']; ?> --minuti=4</code>) la coda si svuota da sola nell'arco di qualche ora.</p>
</section>

<script>
(function () {
	var attivo = <?php echo $attivo || $in_corso ? 'true' : 'false'; ?>;
	var token = <?php echo json_encode( token() ); ?>;
	var idAudit = <?php echo (int) $audit['id']; ?>;
	var inPausa = false;

	var pausa = document.getElementById('pausa');

	if (pausa) {
		pausa.addEventListener('click', function () {
			inPausa = !inPausa;
			this.textContent = inPausa ? 'Riprendi' : 'Metti in pausa';
			document.getElementById('titolo-stato').textContent = inPausa ? 'In pausa' : 'Esecuzione in corso';
			if (!inPausa) { giro(); }
		});
	}

	function aggiorna(stato) {
		document.getElementById('percentuale').textContent = stato.percentuale + '%';
		document.getElementById('completate').textContent = stato.completate.toLocaleString('it-IT');
		document.getElementById('totale').textContent = stato.totale.toLocaleString('it-IT');
		document.getElementById('fatte').textContent = stato.fatto.toLocaleString('it-IT');
		document.getElementById('errori').textContent = stato.errore.toLocaleString('it-IT');
		document.getElementById('attesa').textContent = stato.attesa.toLocaleString('it-IT');
		document.getElementById('avanzamento').style.width = Math.max(1, stato.percentuale) + '%';
	}

	function scrivi(operazioni) {
		var corpo = document.getElementById('registro');

		operazioni.slice().reverse().forEach(function (o) {
			var tr = document.createElement('tr');
			var classe = o.stato === 'fatto' ? 'ok' : (o.stato === 'errore' ? 'grave' : 'basso');
			tr.innerHTML = '<td><span class="tag ' + classe + '">' + o.stato + '</span></td>'
				+ '<td></td><td class="stretta"></td>';
			tr.children[1].textContent = o.etichetta;
			tr.children[2].textContent = o.messaggio;
			corpo.insertBefore(tr, corpo.firstChild);
		});

		while (corpo.children.length > 60) { corpo.removeChild(corpo.lastChild); }
	}

	function giro() {
		if (!attivo || inPausa) { return; }

		fetch('?p=pilota-esegui&id=' + idAudit + '&token=' + encodeURIComponent(token))
			.then(function (r) { return r.json(); })
			.then(function (dati) {
				if (dati.errore) {
					document.getElementById('spiegazione').textContent = dati.errore;
					attivo = false;
					return;
				}

				aggiorna(dati.stato);
				scrivi(dati.eseguite || []);

				if (dati.stato.attesa > 0) {
					setTimeout(giro, 800);
				} else {
					attivo = false;
					document.getElementById('titolo-stato').textContent = 'Completato';
					document.getElementById('spiegazione').textContent = 'Tutte le operazioni sono state eseguite. Ricarica la pagina per il riepilogo.';
					document.getElementById('situazione').textContent = 'coda vuota';
				}
			})
			.catch(function () {
				// Una richiesta persa non ferma il lavoro: si riprova più lentamente.
				setTimeout(giro, 4000);
			});
	}

	giro();
}());
</script>
