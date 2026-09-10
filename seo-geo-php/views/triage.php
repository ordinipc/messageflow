<?php
/**
 * Tabella del triage editoriale.
 *
 * @package SeoGeoAudit
 * @var array  $audit    Riga audit.
 * @var array  $articoli Articoli classificati.
 * @var array  $conteggi Conteggi per categoria.
 * @var string $filtro   Categoria filtrata.
 */

$classi = array(
	'eliminare'  => 'grave',
	'accorpare'  => 'alto',
	'riscrivere' => 'medio',
	'mantenere'  => 'ok',
);

$perCategoria = array();
foreach ( $conteggi as $c ) {
	$perCategoria[ $c['categoria'] ] = (int) $c['n'];
}

?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>"><?php echo e( $audit['sito_nome'] ); ?></a> › Triage</p>
	<h1>Triage editoriale</h1>
	<p class="guida"><?php echo num( array_sum( $perCategoria ) ); ?> articoli pubblicati, classificati per qualità, sovrapposizione e intento di ricerca.</p>
</section>

<div class="pillole">
	<a class="pillola <?php echo '' === $filtro ? 'attiva' : ''; ?>" href="?p=triage&amp;id=<?php echo (int) $audit['id']; ?>">Tutti <b><?php echo num( array_sum( $perCategoria ) ); ?></b></a>
	<?php foreach ( $classi as $cat => $classe ) : ?>
		<a class="pillola <?php echo $classe; ?> <?php echo $filtro === $cat ? 'attiva' : ''; ?>" href="?p=triage&amp;id=<?php echo (int) $audit['id']; ?>&amp;c=<?php echo $cat; ?>">
			<?php echo ucfirst( $cat ); ?> <b><?php echo num( $perCategoria[ $cat ] ?? 0 ); ?></b>
		</a>
	<?php endforeach; ?>
</div>

<input type="search" id="cerca" class="cerca" placeholder="Cerca un articolo, una keyword, un azione…" aria-label="Cerca fra gli articoli">

<div class="tabellabox">
	<table id="tabella-triage">
		<thead>
			<tr><th>Stato</th><th>Articolo</th><th class="num">Parole</th><th class="num">Qualità</th><th>Intento</th><th>Azione</th></tr>
		</thead>
		<tbody>
		<?php foreach ( $articoli as $a ) : ?>
			<tr>
				<td><span class="tag <?php echo $classi[ $a['categoria'] ]; ?>"><?php echo ucfirst( e( $a['categoria'] ) ); ?></span></td>
				<td>
					<a href="<?php echo e( $a['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $a['titolo'] ); ?></a>
					<div class="sotto"><?php echo e( $a['motivo'] ); ?><?php echo $a['redirect_a'] ? ' → 301 verso ' . e( $a['redirect_a'] ) : ''; ?></div>
				</td>
				<td class="num"><?php echo num( $a['parole'] ); ?></td>
				<td class="num"><span class="voto <?php echo $a['qualita'] >= 58 ? 'ok' : ( $a['qualita'] >= 45 ? 'medio' : 'grave' ); ?>"><?php echo (int) $a['qualita']; ?></span></td>
				<td><?php echo e( $a['intento'] ); ?></td>
				<td class="stretta"><?php echo e( $a['azione'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>

<script>
document.getElementById('cerca').addEventListener('input', function (e) {
	var q = e.target.value.toLowerCase();
	document.querySelectorAll('#tabella-triage tbody tr').forEach(function (tr) {
		tr.style.display = tr.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
	});
});
</script>
