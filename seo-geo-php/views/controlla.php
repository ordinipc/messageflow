<?php
/**
 * Perché una pagina non si vede.
 *
 * @package SeoGeoAudit
 * @var string     $url     Indirizzo controllato.
 * @var array|null $esito   Risultato della diagnosi.
 * @var bool       $google  Search Console collegata.
 */

$classi = array( 'grave' => 'grave', 'alto' => 'alto', 'medio' => 'medio' );

?>
<section class="intestazione">
	<h1>Perché questa pagina non si vede</h1>
	<p class="guida">
		Incolla l indirizzo di un contenuto: il programma chiede al sito com è messo adesso, chiede a
		Google se la conosce, e mette insieme quello che sa dall analisi. Non tocca niente: legge e basta.
	</p>
</section>

<form class="scheda" method="post" action="?p=controlla">
	<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
	<div class="campo">
		<label for="url">Indirizzo da controllare</label>
		<input id="url" type="url" name="url" required placeholder="https://tuosito.it/un-articolo/" value="<?php echo e( $url ); ?>">
		<small><?php echo $google ? 'Search Console è collegata: la risposta comprende anche il parere di Google.' : 'Search Console non è collegata: la risposta arriverà solo dal sito.'; ?></small>
	</div>
	<button class="bottone" type="submit">Controlla</button>
</form>

<?php if ( $esito ) : ?>

	<?php if ( $esito['cause'] ) : ?>
		<section class="scheda">
			<h2>Cosa ho trovato</h2>
			<?php foreach ( $esito['cause'] as $causa ) : ?>
				<div class="causa">
					<span class="tag <?php echo e( $classi[ $causa['gravita'] ] ?? 'basso' ); ?>"><?php echo e( $causa['titolo'] ); ?></span>
					<p class="guida"><?php echo e( $causa['spiegazione'] ); ?></p>
					<p class="nota"><strong>Cosa fare:</strong> <?php echo e( $causa['rimedio'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</section>
	<?php else : ?>
		<p class="avviso ok-bg">
			Nessun problema riconosciuto: il contenuto risulta pubblicato e indicizzabile.
			Se non si vede lo stesso, guarda la cache del sito e il tema.
		</p>
	<?php endif; ?>

	<section class="scheda">
		<h2>Quello che dice il sito</h2>
		<?php if ( $esito['sito_err'] ) : ?>
			<p class="avviso grave"><?php echo e( $esito['sito_err'] ); ?></p>
		<?php elseif ( $esito['sito'] ) : ?>
			<?php $s = $esito['sito']; ?>
			<table class="widefat">
				<tbody>
					<tr><td>Titolo</td><td><strong><?php echo e( $s['titolo'] ?? '' ); ?></strong></td></tr>
					<tr><td>Tipo</td><td><?php echo 'page' === ( $s['tipo'] ?? '' ) ? 'pagina' : 'articolo'; ?> #<?php echo e( $s['wp_id'] ?? '' ); ?></td></tr>
					<tr><td>Stato</td><td><?php echo e( $s['stato'] ?? '' ); ?></td></tr>
					<tr><td>Parole</td><td><?php echo num( $s['parole'] ?? 0 ); ?></td></tr>
					<tr><td>Categorie</td><td><?php echo e( implode( ', ', (array) ( $s['categorie'] ?? array() ) ) ) ?: '—'; ?></td></tr>
					<tr><td>Direttive robots</td><td><?php echo e( $s['robots'] ?? '' ) ?: 'nessuna'; ?></td></tr>
					<tr><td>Canonica impostata</td><td><?php echo e( $s['canonica'] ?? '' ) ?: '—'; ?></td></tr>
					<tr><td>Meta toccate dal programma</td><td><?php echo ! empty( $s['meta_toccate'] ) ? 'sì, e si possono annullare' : 'no'; ?></td></tr>
					<tr><td>Bozza riscritta in attesa</td><td><?php echo ! empty( $s['bozza_pronta'] ) ? 'sì' : 'no'; ?></td></tr>
				</tbody>
			</table>
		<?php endif; ?>
	</section>

	<section class="scheda">
		<h2>Quello che dice Google</h2>
		<?php if ( ! $google ) : ?>
			<p class="nota">Search Console non è collegata: collegala dalle Impostazioni per avere anche questa metà.</p>
		<?php elseif ( $esito['google_err'] ) : ?>
			<p class="avviso grave"><?php echo e( $esito['google_err'] ); ?></p>
		<?php elseif ( $esito['google'] ) : ?>
			<?php $g = $esito['google']; ?>
			<?php $verdetto = \SeoGeo\Diagnosi::spiegaGoogle( $g ); ?>
			<p class="guida">
				<strong><?php echo e( $verdetto['titolo'] ); ?>.</strong>
				<?php echo e( $verdetto['spiega'] ); ?>
				<?php if ( '' !== $verdetto['fare'] ) : ?>
					<br><strong>Che cosa fare:</strong> <?php echo e( $verdetto['fare'] ); ?>
				<?php endif; ?>
			</p>
			<p class="nota">
				Google non dà un punteggio alle pagine, e nessuno può leggerne uno: questo è il giudizio
				che si può sapere, e viene da Google, non dal gestionale. Il punteggio dell audit e quello
				di Rank Math sono liste di controllo nostre: una pagina può farle tutte e restare fuori
				dall indice lo stesso.
			</p>
			<table class="widefat">
				<tbody>
					<tr><td>Esito</td><td><strong><?php echo e( $g['stato'] ?? '' ); ?></strong></td></tr>
					<tr><td>Copertura</td><td><?php echo e( $g['copertura'] ?? '' ) ?: '—'; ?></td></tr>
					<tr><td>Ultima scansione</td><td><?php echo e( substr( (string) ( $g['ultima_scansione'] ?? '' ), 0, 10 ) ) ?: 'mai'; ?></td></tr>
					<tr><td>Originale scelta da Google</td><td><?php echo e( $g['canonica_google'] ?? '' ) ?: '—'; ?></td></tr>
					<tr><td>robots.txt</td><td><?php echo e( $g['robots'] ?? '' ) ?: '—'; ?></td></tr>
				</tbody>
			</table>
		<?php endif; ?>
	</section>

	<?php if ( $esito['nostro'] ) : ?>
		<section class="scheda">
			<h2>Quello che ne dice l analisi</h2>
			<?php $n = $esito['nostro']; ?>
			<table class="widefat">
				<tbody>
					<tr><td>Classificazione</td><td><?php echo e( $n['triage']['categoria'] ?? 'non classificato' ); ?><?php echo ! empty( $n['triage']['motivo'] ) ? ' — ' . e( $n['triage']['motivo'] ) : ''; ?></td></tr>
					<tr><td>Redirect previsto</td><td><?php echo e( $n['triage']['redirect_a'] ?? '' ) ?: 'nessuno'; ?></td></tr>
					<tr><td>Bozza generata</td><td><?php echo ! empty( $n['bozza'] ) ? e( $n['bozza']['stato'] ) . ', ' . num( $n['bozza']['parole'] ) . ' parole, il ' . e( $n['bozza']['creato_il'] ) : 'nessuna'; ?></td></tr>
				</tbody>
			</table>

			<?php if ( $n['coda'] ) : ?>
				<h2>Operazioni fatte su questo contenuto</h2>
				<div class="tabellabox">
					<table>
						<thead><tr><th>Operazione</th><th>Esito</th><th>Risultato</th></tr></thead>
						<tbody>
						<?php foreach ( $n['coda'] as $riga ) : ?>
							<tr>
								<td><?php echo e( $riga['tipo'] ); ?></td>
								<td><?php echo e( $riga['stato'] ); ?></td>
								<td class="sotto"><?php echo e( $riga['messaggio'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>
	<?php else : ?>
		<p class="nota">Questo indirizzo non risulta fra i contenuti dell ultima analisi: se è nuovo, rifai l analisi leggendo dal sito.</p>
	<?php endif; ?>

<?php endif; ?>
