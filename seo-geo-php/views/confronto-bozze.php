<?php
/**
 * Vecchio e nuovo affiancati, con il pulsante che sovrascrive.
 *
 * @package SeoGeoAudit
 * @var array  $audit  Riga audit.
 * @var array  $righe  Bozze con il contenuto attuale del sito.
 * @var bool   $pronto Collegamento a WordPress configurato.
 * @var string $esito  Messaggio.
 * @var string $errore Errore.
 */

$da_inviare = array_values( array_filter( $righe, static fn( $r ) => empty( $r['inviata_il'] ) ) );
?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>"><?php echo e( $audit['sito_nome'] ); ?></a> › <a href="?p=bozze&amp;id=<?php echo (int) $audit['id']; ?>">Riscrittura</a> › Vecchio e nuovo</p>
	<h1>Vecchio e nuovo</h1>
	<p class="guida">
		A sinistra quello che c'è adesso sul sito, a destra il testo migliorato.
		«Sovrascrivi» scrive <strong>sull'articolo che esiste già</strong>: non crea un doppione e l'indirizzo
		non cambia. Il testo precedente resta da parte e si rimette con «Annulla tutto e ripristina»
		in <a href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>">Applica sul sito</a>.
	</p>
</section>

<?php if ( $esito ) : ?><p class="avviso ok-bg"><?php echo e( $esito ); ?></p><?php endif; ?>
<?php if ( $errore ) : ?><p class="avviso grave"><?php echo e( $errore ); ?></p><?php endif; ?>

<?php if ( ! $pronto ) : ?>
	<p class="avviso grave">Il collegamento a WordPress non è configurato: senza, non si può scrivere sul sito.</p>
<?php endif; ?>

<?php if ( ! $righe ) : ?>
	<section class="scheda">
		<p class="guida">Nessuna bozza pronta. Vai a <a href="?p=bozze&amp;id=<?php echo (int) $audit['id']; ?>">Riscrittura assistita</a> per generarle.</p>
	</section>
<?php else : ?>

<section class="scheda">
	<p class="guida">
		<strong><?php echo num( count( $da_inviare ) ); ?></strong> da inviare ·
		<?php echo num( count( $righe ) - count( $da_inviare ) ); ?> già online
	</p>
	<?php if ( $pronto && $da_inviare ) : ?>
		<div class="azioni">
			<button class="bottone" type="button" id="invia-tutte">Sovrascrivi tutte le <?php echo (int) count( $da_inviare ); ?></button>
		</div>
		<div id="tutte-corso" hidden>
			<p><span id="tutte-spia" class="spia"></span> <strong id="tutte-titolo">Sto scrivendo sul sito…</strong></p>
			<div class="barra" style="height:10px;margin-bottom:12px"><i id="tutte-barra" class="ok" style="width:1%;height:10px"></i></div>
			<p class="nota"><span id="tutte-fatte">0</span> di <?php echo (int) count( $da_inviare ); ?> · <span id="tutte-errori">0</span> non riuscite</p>
			<button type="button" class="bottone chiaro" id="tutte-stop">Ferma</button>
		</div>
	<?php endif; ?>
</section>

<?php foreach ( $righe as $riga ) : ?>
	<section class="scheda confronto" data-bozza="<?php echo (int) $riga['id']; ?>">
		<h2><?php echo e( $riga['titolo_vecchio'] ); ?></h2>
		<p class="nota">
			<a href="<?php echo e( $riga['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $riga['url'] ); ?></a>
			<?php if ( ! empty( $riga['inviata_il'] ) ) : ?>
				· <strong>già online</strong> dal <?php echo e( substr( $riga['inviata_il'], 0, 16 ) ); ?>
			<?php endif; ?>
		</p>

		<div class="due-colonne">
			<div>
				<span class="etichetta">Adesso sul sito · <?php echo num( $riga['parole_vecchie'] ); ?> parole</span>
				<p class="nota"><strong>Title:</strong> <?php echo e( $riga['seo_title'] ?: '(nessuno)' ); ?></p>
				<div class="testo-bozza testo-vecchio"><?php echo nl2br( e( mb_substr( (string) $riga['testo_vecchio'], 0, 2500 ) ) ); ?></div>
			</div>
			<div>
				<span class="etichetta">Dopo · <?php echo num( str_word_count( strip_tags( (string) $riga['corpo_html'] ) ) ); ?> parole</span>
				<p class="nota"><strong>Title:</strong> <?php echo e( $riga['meta_title'] ?: '(nessuno)' ); ?></p>
				<div class="testo-bozza"><?php echo $riga['corpo_html']; ?></div>
			</div>
		</div>

		<div class="azioni">
			<a class="bottone chiaro" href="?p=bozza&amp;b=<?php echo (int) $riga['id']; ?>">Apri la bozza intera</a>
			<?php if ( $pronto ) : ?>
				<button class="bottone invia-una" type="button" data-bozza="<?php echo (int) $riga['id']; ?>">
					<?php echo empty( $riga['inviata_il'] ) ? 'Sovrascrivi questo articolo' : 'Riscrivi di nuovo'; ?>
				</button>
			<?php endif; ?>
			<span class="nota esito-una"></span>
		</div>
	</section>
<?php endforeach; ?>

<script>
(function () {
	var token = <?php echo json_encode( token() ); ?>;
	var idAudit = <?php echo (int) $audit['id']; ?>;

	if (!window.fetch) { return; }

	function invia(idBozza) {
		return fetch('?p=api-sovrascrivi&id=' + idAudit + '&bozza=' + idBozza + '&token=' + encodeURIComponent(token))
			.then(function (r) { return r.json(); });
	}

	Array.prototype.forEach.call(document.querySelectorAll('.invia-una'), function (bottone) {
		bottone.addEventListener('click', function () {
			var scheda = bottone.closest('.confronto');
			var esito = scheda.querySelector('.esito-una');

			if (!confirm('Scrivere questo testo sull articolo pubblicato? Il testo attuale resta da parte e si può rimettere.')) {
				return;
			}

			bottone.disabled = true;
			esito.textContent = 'Sto scrivendo…';

			invia(bottone.dataset.bozza)
				.then(function (d) {
					if (d.errore) { esito.textContent = 'Errore: ' + d.errore; bottone.disabled = false; return; }

					esito.textContent = 'Scritto sul sito.';
					bottone.textContent = 'Riscrivi di nuovo';
					bottone.disabled = false;
				})
				.catch(function (e) { esito.textContent = 'Errore: ' + e.message; bottone.disabled = false; });
		});
	});

	var tutte = document.getElementById('invia-tutte');

	if (!tutte) { return; }

	tutte.addEventListener('click', function () {
		var code = Array.prototype.map.call(
			document.querySelectorAll('.confronto'),
			function (s) { return s.dataset.bozza; }
		).filter(function (id) {
			var b = document.querySelector('.invia-una[data-bozza="' + id + '"]');
			return b && 'Sovrascrivi questo articolo' === b.textContent.trim();
		});

		if (!code.length || !confirm('Scrivere ' + code.length + ' testi sugli articoli pubblicati? Si può annullare.')) {
			return;
		}

		tutte.hidden = true;
		document.getElementById('tutte-corso').hidden = false;

		var fatte = 0;
		var errori = 0;
		var fermato = false;

		document.getElementById('tutte-stop').addEventListener('click', function () {
			fermato = true;
			document.getElementById('tutte-titolo').textContent = 'Mi fermo dopo questo…';
			document.getElementById('tutte-spia').className = 'spia fermo';
		});

		function prossima() {
			if (fermato || !code.length) {
				document.getElementById('tutte-spia').className = 'spia fermo';
				document.getElementById('tutte-titolo').textContent = fermato
					? 'Fermata: ' + code.length + ' non inviate'
					: 'Fatto: tutte scritte sul sito';
				document.getElementById('tutte-stop').textContent = 'Ricarica la pagina';
				document.getElementById('tutte-stop').onclick = function () { location.reload(); };
				return;
			}

			var idBozza = code.shift();

			invia(idBozza)
				.then(function (d) {
					if (d.errore) { errori++; } else { fatte++; }

					document.getElementById('tutte-fatte').textContent = fatte;
					document.getElementById('tutte-errori').textContent = errori;
					document.getElementById('tutte-barra').style.width =
						Math.max(1, Math.round((fatte / (fatte + errori + code.length)) * 100)) + '%';

					prossima();
				})
				.catch(function () { errori++; prossima(); });
		}

		prossima();
	});
})();
</script>

<?php endif; ?>
