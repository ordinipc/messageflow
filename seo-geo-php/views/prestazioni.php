<?php
/**
 * Rendimento reale in Google e cose da fare che ne derivano.
 *
 * @package SeoGeoAudit
 * @var array      $cfg         Configurazione.
 * @var bool       $configurato Collegamento a Search Console configurato.
 * @var string     $account     Indirizzo dell account di servizio.
 * @var array|null $ultima      Ultima rilevazione.
 * @var array|null $precedente  Rilevazione prima di quella.
 * @var array[]    $storico     Rilevazioni recenti.
 * @var array[]    $segnali     Cose da fare.
 * @var array[]    $per_tipo    Segnali raggruppati.
 * @var array[]    $pagine_top  Pagine con più impression.
 * @var array[]    $query_top   Ricerche con più impression.
 * @var string     $messaggio   Esito dell ultimo aggiornamento.
 * @var string     $errore      Errore dell ultimo aggiornamento.
 */

$colori = array(
	'quasi_prima_pagina'   => 'alto',
	'titolo_che_non_rende' => 'medio',
	'cannibalizzazione'    => 'grave',
	'in_calo'              => 'grave',
	'mai_mostrata'         => 'basso',
);

/**
 * Variazione fra due numeri, con il segno.
 *
 * @param int|float $ora    Valore attuale.
 * @param int|float $prima  Valore precedente.
 * @param bool      $meglio Se true, scendere è un miglioramento (la posizione).
 * @return string
 */
function delta( $ora, $prima, $meglio = false ) {
	$d = round( (float) $ora - (float) $prima, 1 );

	if ( abs( $d ) < 0.05 ) {
		return '<span class="sotto">invariato</span>';
	}

	$bene   = $meglio ? $d < 0 : $d > 0;
	$segno  = $d > 0 ? '+' : '';
	$numero = number_format( $d, ( $d == (int) $d ) ? 0 : 1, ',', '.' );

	return '<span class="tag ' . ( $bene ? 'ok' : 'grave' ) . '">' . $segno . $numero . '</span>';
}

?>
<section class="intestazione">
	<h1>Rendimento in Google</h1>
	<p class="guida">
		Qui non ci sono previsioni: sono i dati di Search Console, cioè quello che succede davvero
		quando qualcuno cerca. Quando questi numeri e il punteggio dell audit dicono cose diverse,
		hanno ragione questi.
	</p>
</section>

<?php if ( $messaggio ) : ?>
	<p class="avviso ok-bg"><?php echo e( $messaggio ); ?></p>
<?php endif; ?>

<?php if ( $errore ) : ?>
	<p class="avviso grave"><?php echo e( $errore ); ?></p>
<?php endif; ?>

<?php if ( ! $configurato ) : ?>
	<section class="scheda">
		<h2>Search Console non è ancora collegata</h2>
		<p class="guida">
			Serve una chiave di account di servizio di Google Cloud, da incollare nelle Impostazioni.
			Sono cinque minuti e non costa niente: le istruzioni passo passo sono lì.
		</p>
		<p><a class="bottone" href="?p=impostazioni#search-console">Vai alle impostazioni</a></p>
	</section>
<?php else : ?>

	<section class="scheda">
		<h2>Aggiorna i dati</h2>
		<p class="guida">
			Scarica gli ultimi <?php echo (int) ( $cfg['google']['giorni'] ?? 28 ); ?> giorni di ricerche e ricalcola le priorità.
			Su molte pagine può richiedere un minuto.
			<?php if ( $account ) : ?>
				Account usato: <code><?php echo e( $account ); ?></code>.
			<?php endif; ?>
		</p>
		<form method="post" action="?p=aggiorna-google">
			<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
			<button class="bottone" type="submit">Aggiorna da Search Console</button>
		</form>
	</section>

	<?php if ( ! $ultima ) : ?>
		<p class="nota">Nessuna rilevazione ancora: premi il pulsante qui sopra per la prima.</p>
	<?php else : ?>

		<div class="riquadri">
			<div class="riquadro">
				<span class="etichetta">Clic</span>
				<strong><?php echo num( $ultima['clic'] ); ?></strong>
				<span class="sotto"><?php echo $precedente ? delta( $ultima['clic'], $precedente['clic'] ) . ' sul periodo prima' : 'prima rilevazione'; ?></span>
			</div>
			<div class="riquadro">
				<span class="etichetta">Impression</span>
				<strong><?php echo num( $ultima['impression'] ); ?></strong>
				<span class="sotto"><?php echo $precedente ? delta( $ultima['impression'], $precedente['impression'] ) . ' sul periodo prima' : 'quante volte sei comparso'; ?></span>
			</div>
			<div class="riquadro">
				<span class="etichetta">Posizione media</span>
				<strong><?php echo number_format( (float) $ultima['posizione_media'], 1, ',', '.' ); ?></strong>
				<span class="sotto"><?php echo $precedente ? delta( $ultima['posizione_media'], $precedente['posizione_media'], true ) . ' (scendere è meglio)' : 'più è bassa, meglio è'; ?></span>
			</div>
			<div class="riquadro">
				<span class="etichetta">Pagine che ricevono visite</span>
				<strong><?php echo num( $ultima['pagine'] ); ?></strong>
				<span class="sotto"><?php echo num( $ultima['query'] ); ?> ricerche diverse</span>
			</div>
		</div>

		<p class="nota">
			Periodo: dal <?php echo e( $ultima['periodo_da'] ); ?> al <?php echo e( $ultima['periodo_a'] ); ?>
			(<?php echo (int) $ultima['giorni']; ?> giorni) · proprietà <code><?php echo e( $ultima['proprieta'] ); ?></code>
			· rilevato il <?php echo e( $ultima['creato_il'] ); ?>
		</p>

		<?php if ( $per_tipo ) : ?>
			<div class="pillole">
				<?php foreach ( $per_tipo as $gruppo ) : ?>
					<span class="pillola <?php echo e( $colori[ $gruppo['tipo'] ] ?? '' ); ?>">
						<?php echo e( $gruppo['titolo'] ); ?> <b><?php echo num( $gruppo['quanti'] ); ?></b>
					</span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<section class="scheda">
			<h2>Da fare, in ordine di convenienza</h2>
			<p class="guida">
				L ordine non è il mio: è quello dei numeri. In cima c è dove si guadagna di più con meno lavoro.
			</p>
		</section>

		<div class="tabellabox">
			<table>
				<thead>
					<tr>
						<th>Cosa</th>
						<th>Pagina</th>
						<th class="num">Pos.</th>
						<th class="num">Impr.</th>
						<th class="num">Clic</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( array_slice( $segnali, 0, 100 ) as $segnale ) : ?>
					<tr>
						<td>
							<span class="tag <?php echo e( $colori[ $segnale['tipo'] ] ?? 'basso' ); ?>"><?php echo e( $segnale['titolo'] ); ?></span>
							<div class="sotto"><?php echo e( $segnale['spiegazione'] ); ?></div>
						</td>
						<td>
							<a href="<?php echo e( $segnale['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( ltrim( (string) parse_url( $segnale['url'], PHP_URL_PATH ), '/' ) ?: '/' ); ?></a>
						</td>
						<td class="num"><?php echo $segnale['posizione'] > 0 ? number_format( (float) $segnale['posizione'], 1, ',', '' ) : '—'; ?></td>
						<td class="num"><?php echo num( $segnale['impression'] ); ?></td>
						<td class="num"><?php echo num( $segnale['clic'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $segnali ) : ?>
					<tr><td colspan="5">Nessun segnale: o i dati sono troppo pochi per dire qualcosa, o non c è niente di urgente.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

		<section class="scheda">
			<h2>Le ricerche che ti portano più gente</h2>
			<div class="tabellabox">
				<table>
					<thead><tr><th>Ricerca</th><th class="num">Impr.</th><th class="num">Clic</th><th class="num">CTR</th><th class="num">Pos.</th></tr></thead>
					<tbody>
					<?php foreach ( array_slice( $query_top, 0, 25 ) as $riga ) : ?>
						<tr>
							<td><?php echo e( $riga['query'] ); ?></td>
							<td class="num"><?php echo num( $riga['impression'] ); ?></td>
							<td class="num"><?php echo num( $riga['clic'] ); ?></td>
							<td class="num"><?php echo number_format( (float) $riga['ctr'], 1, ',', '' ); ?>%</td>
							<td class="num"><?php echo number_format( (float) $riga['posizione'], 1, ',', '' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>

		<?php if ( count( $storico ) > 1 ) : ?>
			<section class="scheda">
				<h2>Come sta andando nel tempo</h2>
				<div class="tabellabox">
					<table>
						<thead><tr><th>Rilevazione</th><th>Periodo</th><th class="num">Clic</th><th class="num">Impr.</th><th class="num">Pos. media</th></tr></thead>
						<tbody>
						<?php foreach ( $storico as $riga ) : ?>
							<tr>
								<td><?php echo e( $riga['creato_il'] ); ?></td>
								<td class="sotto"><?php echo e( $riga['periodo_da'] ); ?> → <?php echo e( $riga['periodo_a'] ); ?></td>
								<td class="num"><?php echo num( $riga['clic'] ); ?></td>
								<td class="num"><?php echo num( $riga['impression'] ); ?></td>
								<td class="num"><?php echo number_format( (float) $riga['posizione_media'], 1, ',', '' ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

	<?php endif; ?>
<?php endif; ?>
