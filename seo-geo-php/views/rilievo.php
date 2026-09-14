<?php
/**
 * Dettaglio di un singolo rilievo.
 *
 * @package SeoGeoAudit
 * @var array $rilievo    Riga rilievo.
 * @var array $occorrenze Occorrenze.
 * @var array $audit      Audit di riferimento.
 */

$gravita = array(
	'critical' => array( 'Critico', 'grave' ),
	'high'     => array( 'Alto', 'alto' ),
	'medium'   => array( 'Medio', 'medio' ),
	'low'      => array( 'Basso', 'basso' ),
);

?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>"><?php echo e( $audit['sito_nome'] ); ?></a> › <?php echo e( $rilievo['regola'] ); ?></p>
	<h1><?php echo e( $rilievo['titolo'] ); ?></h1>
	<p>
		<span class="tag <?php echo $gravita[ $rilievo['gravita'] ][1]; ?>"><?php echo $gravita[ $rilievo['gravita'] ][0]; ?></span>
		<span class="tag basso"><?php echo e( $rilievo['area'] ); ?></span>
		<?php echo $rilievo['automatico'] ? '<span class="tag ok">correzione automatica</span>' : '<span class="tag basso">intervento manuale</span>'; ?>
	</p>
</section>

<section class="scheda">
	<h2>Perché conta</h2>
	<p><?php echo e( $rilievo['perche'] ); ?></p>
	<h2>Come si risolve</h2>
	<p><?php echo e( $rilievo['soluzione'] ); ?></p>
</section>

<?php
$chiuse  = count( array_filter( $occorrenze, static fn( $o ) => ! empty( $o['applicato'] ) ) );
$restano = (int) $rilievo['occorrenze'] - $chiuse;
?>

<section class="scheda">
	<h2><?php echo num( max( 0, $restano ) ); ?> occorrenze da sistemare</h2>
	<?php if ( $chiuse ) : ?>
		<p class="guida">
			Altre <?php echo num( $chiuse ); ?> sono già state sistemate dal gestionale dopo la lettura
			del sito: restano in elenco, segnate, finché il sito non viene riletto.
		</p>
	<?php endif; ?>
	<?php if ( count( $occorrenze ) < (int) $rilievo['occorrenze'] ) : ?>
		<p class="guida">Sono mostrate le prime <?php echo count( $occorrenze ); ?>: l elenco completo è in <code>problemi.csv</code>.</p>
	<?php endif; ?>
	<div class="tabellabox">
		<table>
			<thead><tr><th>URL / elemento</th><th>Dettaglio</th><th>Stato</th></tr></thead>
			<tbody>
			<?php foreach ( $occorrenze as $o ) : ?>
				<tr>
					<td class="mono"><?php echo e( $o['riferimento'] ); ?></td>
					<td><?php echo e( $o['dettaglio'] ); ?></td>
					<td>
						<?php if ( ! empty( $o['applicato'] ) ) : ?>
							<span class="tag ok">sistemato</span>
						<?php else : ?>
							<span class="tag basso">da fare</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
