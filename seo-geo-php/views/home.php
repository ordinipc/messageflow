<?php
/**
 * Elenco degli audit archiviati.
 *
 * @package SeoGeoAudit
 * @var array $audit Audit salvati.
 */

?>
<section class="intestazione">
	<h1>Audit archiviati</h1>
	<p class="guida">Ogni analisi resta nel database con punteggi, rilievi, triage editoriale e file di correzione pronti da scaricare.</p>
</section>

<?php if ( empty( $audit ) ) : ?>
	<div class="vuoto">
		<p>Nessuna analisi presente.</p>
		<p><a class="bottone" href="?p=nuovo">Carica un export WordPress</a></p>
	</div>
<?php else : ?>
	<div class="tabellabox">
		<table>
			<thead>
				<tr>
					<th>Sito</th>
					<th>Data</th>
					<th class="num">Punteggio</th>
					<th class="num">Problemi</th>
					<th class="num">Contenuti</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $audit as $a ) : ?>
				<tr>
					<td>
						<a href="?p=audit&amp;id=<?php echo (int) $a['id']; ?>"><strong><?php echo e( $a['sito_nome'] ); ?></strong></a>
						<div class="sotto"><?php echo e( $a['sito_url'] ); ?></div>
					</td>
					<td><?php echo e( substr( $a['creato_il'], 0, 16 ) ); ?></td>
					<td class="num">
						<span class="voto <?php echo $a['punteggio'] >= 70 ? 'ok' : ( $a['punteggio'] >= 45 ? 'medio' : 'grave' ); ?>">
							<?php echo (int) $a['punteggio']; ?>
						</span>
					</td>
					<td class="num"><?php echo num( $a['problemi_totali'] ); ?></td>
					<td class="num"><?php echo num( $a['articoli'] ); ?> art. · <?php echo num( $a['pagine'] ); ?> pag.</td>
					<td class="num">
						<form method="post" action="?p=elimina" onsubmit="return confirm('Eliminare definitivamente questo audit?')">
							<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
							<input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
							<button class="collegamento" type="submit">elimina</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
