<?php
/**
 * Confronto prima/dopo delle meta, prima di applicarle.
 *
 * @package SeoGeoAudit
 * @var array  $audit    Riga audit.
 * @var array  $righe    Confronto per contenuto.
 * @var string $sorgente 'sito' se letto dal plugin, 'export' se dal database.
 * @var string $quando   Data dell anteprima.
 * @var string $solo     'modificati' oppure 'tutti'.
 */

$conteggi  = array();
$modificate = array();

foreach ( $righe as $r ) {
	$cambia = array();

	foreach ( $r['campi'] as $nome => $valori ) {
		if ( trim( (string) $valori[0] ) !== trim( (string) $valori[1] ) ) {
			$cambia[ $nome ]      = $valori;
			$conteggi[ $nome ]    = ( $conteggi[ $nome ] ?? 0 ) + 1;
		}
	}

	if ( $cambia ) {
		$r['cambia']  = $cambia;
		$modificate[] = $r;
	}
}

$elenco = ( 'tutti' === $solo ) ? $righe : $modificate;

?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>"><?php echo e( $audit['sito_nome'] ); ?></a> › <a href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>">Applica sul sito</a> › Anteprima</p>
	<h1>Anteprima delle modifiche</h1>
	<p class="guida">
		<?php if ( 'sito' === $sorgente ) : ?>
			Confronto letto dal sito il <?php echo e( substr( $quando, 0, 16 ) ); ?>: a sinistra quello che c'è ora su WordPress, a destra quello che verrà scritto. <strong>Il sito non è stato modificato.</strong>
		<?php else : ?>
			Confronto calcolato dall'export: a sinistra i valori al momento dell'analisi, a destra quelli ottimizzati. Per leggere lo stato attuale del sito lancia l'anteprima da <a href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>">Applica sul sito</a>.
		<?php endif; ?>
	</p>
</section>

<div class="riquadri">
	<div class="riquadro">
		<span class="etichetta">Contenuti che cambiano</span>
		<strong><?php echo num( count( $modificate ) ); ?></strong>
		<span class="sotto">su <?php echo num( count( $righe ) ); ?> analizzati</span>
	</div>
	<?php foreach ( array( 'Title SEO', 'Meta description', 'Estratto' ) as $campo ) : ?>
		<div class="riquadro">
			<span class="etichetta"><?php echo e( $campo ); ?></span>
			<strong><?php echo num( $conteggi[ $campo ] ?? 0 ); ?></strong>
			<span class="sotto">modifiche</span>
		</div>
	<?php endforeach; ?>
</div>

<div class="pillole">
	<a class="pillola <?php echo 'modificati' === $solo ? 'attiva' : ''; ?>" href="?p=anteprima&amp;id=<?php echo (int) $audit['id']; ?>">Solo quelli che cambiano <b><?php echo num( count( $modificate ) ); ?></b></a>
	<a class="pillola <?php echo 'tutti' === $solo ? 'attiva' : ''; ?>" href="?p=anteprima&amp;id=<?php echo (int) $audit['id']; ?>&amp;tutti=1">Tutti <b><?php echo num( count( $righe ) ); ?></b></a>
	<a class="pillola" href="?p=download&amp;id=<?php echo (int) $audit['id']; ?>&amp;f=meta-ottimizzate.csv">Scarica il confronto in CSV</a>
</div>

<input type="search" id="cerca" class="cerca" placeholder="Cerca un contenuto…" aria-label="Cerca fra i contenuti">

<div id="elenco">
<?php foreach ( $elenco as $r ) : ?>
	<?php $cambia = $r['cambia'] ?? array(); ?>
	<section class="scheda confronto">
		<h2>
			<?php if ( ! empty( $r['url'] ) ) : ?>
				<a href="<?php echo e( $r['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $r['titolo'] ); ?></a>
			<?php else : ?>
				<?php echo e( $r['titolo'] ); ?>
			<?php endif; ?>
			<span class="sotto">ID <?php echo e( $r['id'] ); ?></span>
		</h2>

		<?php if ( empty( $cambia ) ) : ?>
			<p class="sotto">Nessuna modifica: i valori attuali sono già corretti.</p>
		<?php else : ?>
			<?php foreach ( $cambia as $nome => $valori ) : ?>
				<div class="campo-confronto">
					<span class="etichetta"><?php echo e( $nome ); ?></span>
					<div class="prima">
						<?php if ( '' === trim( (string) $valori[0] ) ) : ?>
							<em>assente</em>
						<?php else : ?>
							<?php echo e( $valori[0] ); ?>
							<span class="conta"><?php echo mb_strlen( $valori[0] ); ?> car.</span>
						<?php endif; ?>
					</div>
					<div class="dopo">
						<?php echo e( $valori[1] ); ?>
						<span class="conta"><?php echo mb_strlen( $valori[1] ); ?> car.</span>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>
<?php endforeach; ?>
</div>

<?php if ( empty( $elenco ) ) : ?>
	<div class="vuoto">Nessun contenuto da mostrare.</div>
<?php endif; ?>

<section class="scheda">
	<h2>Se il confronto ti convince</h2>
	<p class="guida">Torna a <a href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>">Applica sul sito</a> e premi <strong>Applica le meta</strong>. I valori attuali vengono conservati: se qualcosa non ti piace, <em>Annulla e ripristina</em> li rimette com'erano.</p>
</section>

<script>
document.getElementById('cerca').addEventListener('input', function (e) {
	var q = e.target.value.toLowerCase();
	document.querySelectorAll('#elenco .confronto').forEach(function (s) {
		s.style.display = s.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
	});
});
</script>
