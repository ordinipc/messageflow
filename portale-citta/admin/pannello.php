<?php
/** Pannello: riepilogo e problemi da sistemare. */
defined( 'PC_AVVIO' ) || exit;

$stat  = statistiche();
$citta = citta_tutte();
$imp   = impostazioni();

/* Diagnostica: cosa manca per essere indicizzati. */
$problemi = array();

if ( 0 === $stat['citta'] ) {
	$problemi[] = array( 'Nessuna città', 'Crea la prima città per iniziare.', 'admin.php?p=citta-modifica' );
}
foreach ( $citta as $c ) {
	$pagine = pagine_di_citta( $c['id'] );
	if ( empty( $pagine ) ) {
		$problemi[] = array(
			$c['nome'] . ': nessuna pagina',
			'La città non ha pagine: l\'indirizzo /' . $c['slug'] . '/ restituisce 404.',
			'admin.php?p=pagine&citta=' . $c['id'],
		);
		continue;
	}
	if ( ! pagina_home( $c['id'] ) ) {
		$problemi[] = array(
			$c['nome'] . ': manca la pagina principale',
			'Serve una pagina di tipo "Principale" per l\'indirizzo /' . $c['slug'] . '/.',
			'admin.php?p=pagine&citta=' . $c['id'],
		);
	}
	$bozze = 0;
	foreach ( $pagine as $p ) {
		if ( 'pubblicata' !== $p['stato'] ) {
			$bozze++;
		}
	}
	if ( $bozze > 0 && 'pubblicata' === $c['stato'] ) {
		$problemi[] = array(
			$c['nome'] . ': ' . $bozze . ' ' . ( 1 === $bozze ? 'pagina in bozza' : 'pagine in bozza' ),
			'Le bozze non sono visibili al pubblico e non vanno nella sitemap.',
			'admin.php?p=pagine&citta=' . $c['id'],
		);
	}
	if ( vuoto( $c['telefono'] ) && vuoto( $imp['telefono'] ) ) {
		$problemi[] = array( $c['nome'] . ': manca il telefono', 'Senza telefono lo schema LocalBusiness è incompleto.', 'admin.php?p=citta-modifica&id=' . $c['id'] );
	}
	if ( vuoto( $c['lat'] ) || vuoto( $c['lng'] ) ) {
		$problemi[] = array( $c['nome'] . ': mancano le coordinate', 'Latitudine e longitudine servono al geotagging.', 'admin.php?p=citta-modifica&id=' . $c['id'] );
	}
}
if ( vuoto( $imp['sito_url'] ) ) {
	$problemi[] = array( 'Indirizzo del portale non impostato', 'Canonical e sitemap useranno un indirizzo indovinato dal server.', 'admin.php?p=impostazioni' );
}
if ( '1' !== (string) $imp['indicizza'] ) {
	$problemi[] = array( 'Indicizzazione disattivata', 'robots.txt blocca tutti i motori di ricerca.', 'admin.php?p=impostazioni' );
}
?>

<div class="pc-titolo">
	<div>
		<h1>Pannello</h1>
		<p>Stato del portale e cose da sistemare.</p>
	</div>
	<div class="pc-titolo__azioni">
		<a class="pc-btn" href="admin.php?p=citta-modifica">+ Nuova città</a>
	</div>
</div>

<div class="pc-numeri">
	<div class="pc-numero"><strong><?php echo (int) $stat['citta_pubblicate']; ?>/<?php echo (int) $stat['citta']; ?></strong><span>Città pubblicate</span></div>
	<div class="pc-numero"><strong><?php echo (int) $stat['pagine_pubblicate']; ?>/<?php echo (int) $stat['pagine']; ?></strong><span>Pagine pubblicate</span></div>
	<div class="pc-numero"><strong><?php echo (int) $stat['servizi']; ?></strong><span>Tipi di servizio</span></div>
	<div class="pc-numero"><strong><?php echo (int) $stat['media']; ?></strong><span>Immagini</span></div>
</div>

<div class="pc-scheda">
	<h2>Diagnostica</h2>
	<p class="pc-scheda__nota">Controlli automatici su ciò che impedisce a Google di indicizzare le pagine.</p>
	<?php if ( empty( $problemi ) ) : ?>
		<p style="color:var(--pc-ok);font-weight:600;margin:0">✓ Nessun problema rilevato.</p>
	<?php else : ?>
		<ul class="pc-controlli">
			<?php foreach ( $problemi as $p ) : ?>
				<li class="is-ko">
					<b>!</b>
					<div>
						<strong><?php echo e( $p[0] ); ?></strong>
						<span><?php echo e( $p[1] ); ?> — <a href="<?php echo e( $p[2] ); ?>">sistema</a></span>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>

<?php if ( ! empty( $citta ) ) : ?>
<div class="pc-scheda">
	<h2>Le tue città</h2>
	<p class="pc-scheda__nota">Punteggio SEO medio delle pagine pubblicate di ogni città.</p>
	<table class="pc-tabella">
		<thead>
			<tr><th>Città</th><th>Indirizzo</th><th>Pagine</th><th>SEO</th><th>Stato</th><th></th></tr>
		</thead>
		<tbody>
		<?php foreach ( $citta as $c ) : ?>
			<?php
			$pagine    = pagine_di_citta( $c['id'] );
			$punteggi  = array();
			foreach ( $pagine as $p ) {
				$a          = seo_analisi( $c, $p );
				$punteggi[] = $a['punteggio'];
			}
			$media  = empty( $punteggi ) ? 0 : (int) round( array_sum( $punteggi ) / count( $punteggi ) );
			$classe = $media >= 80 ? 'is-alto' : ( $media >= 55 ? 'is-medio' : 'is-basso' );
			?>
			<tr>
				<td><strong><?php echo e( $c['nome'] ); ?></strong></td>
				<td><code>/<?php echo e( $c['slug'] ); ?>/</code></td>
				<td><?php echo count( $pagine ); ?></td>
				<td><?php if ( ! empty( $pagine ) ) : ?><span class="pc-stato pc-stato--<?php echo $media >= 80 ? 'pubblicata' : 'bozza'; ?>"><?php echo $media; ?>/100</span><?php else : ?>—<?php endif; ?></td>
				<td><span class="pc-stato pc-stato--<?php echo e( $c['stato'] ); ?>"><?php echo e( $c['stato'] ); ?></span></td>
				<td class="pc-tabella__azioni">
					<a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=pagine&citta=<?php echo e( $c['id'] ); ?>">Pagine</a>
					<a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=citta-modifica&id=<?php echo e( $c['id'] ); ?>">Modifica</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>
