<?php
/** SEO: sitemap, robots, controllo duplicati, indirizzi da inviare a Google. */
defined( 'PC_AVVIO' ) || exit;

$imp   = impostazioni();
$citta = citta_tutte();
$voci  = sitemap_voci();

/* Pagine con testo troppo simile fra loro. */
$duplicati = array();
$corpi     = array();
foreach ( pagine_tutte() as $p ) {
	$testo = preg_replace( '/\s+/u', ' ', mb_strtolower( strip_tags( (string) $p['corpo'] ) ) );
	if ( mb_strlen( $testo ) < 40 ) {
		continue;
	}
	$corpi[ $p['id'] ] = array( 'testo' => mb_substr( $testo, 0, 1200 ), 'pagina' => $p );
}
$chiavi = array_keys( $corpi );
for ( $i = 0; $i < count( $chiavi ); $i++ ) {
	for ( $j = $i + 1; $j < count( $chiavi ); $j++ ) {
		similar_text( $corpi[ $chiavi[ $i ] ]['testo'], $corpi[ $chiavi[ $j ] ]['testo'], $percentuale );
		if ( $percentuale > 88 ) {
			$duplicati[] = array( $corpi[ $chiavi[ $i ] ]['pagina'], $corpi[ $chiavi[ $j ] ]['pagina'], round( $percentuale ) );
		}
	}
}

/* Pagine senza testo. */
$vuote = array();
foreach ( pagine_tutte() as $p ) {
	if ( vuoto( $p['corpo'] ) ) {
		$c = citta_per_id( $p['citta_id'] );
		if ( $c ) {
			$vuote[] = array( $c, $p );
		}
	}
}
?>

<div class="pc-titolo">
	<div>
		<h1>SEO e sitemap</h1>
		<p>Cosa vede Google del tuo portale.</p>
	</div>
</div>

<div class="pc-numeri">
	<div class="pc-numero"><strong><?php echo count( $voci ); ?></strong><span>URL nella sitemap</span></div>
	<div class="pc-numero"><strong><?php echo count( $duplicati ); ?></strong><span>Coppie di testi simili</span></div>
	<div class="pc-numero"><strong><?php echo count( $vuote ); ?></strong><span>Pagine senza testo</span></div>
</div>

<?php if ( '' !== sottocartella() ) : ?>
<div class="pc-avviso pc-avviso--errore">
	<strong>Il portale sta nella sottocartella <code><?php echo e( sottocartella() ); ?>/</code>.</strong><br>
	Google legge <code>robots.txt</code> soltanto nella radice del dominio: quello qui sotto
	(<code><?php echo e( sottocartella() ); ?>/robots.txt</code>) viene ignorato.
	Apri il <code>robots.txt</code> del sito principale e aggiungi questa riga:
	<pre style="user-select:all">Sitemap: <?php echo e( base_url() ); ?>/sitemap.xml</pre>
	Su WordPress: Yoast → Strumenti → Modifica file, oppure Rank Math → Impostazioni generali → Modifica robots.txt.
	La sitemap resta comunque da aggiungere a mano in Search Console, ed è la strada che conta di più.
</div>
<?php endif; ?>

<div class="pc-scheda">
	<h2>Indirizzi da dare a Google</h2>
	<p class="pc-scheda__nota">Aggiungi la sitemap in Search Console: Indicizzazione → Sitemap.</p>
	<table class="pc-tabella">
		<tbody>
			<tr>
				<td><strong>Indice delle sitemap</strong><br><span class="pc-nota">Una sitemap per città, aggiornata da sola.</span></td>
				<td><a href="<?php echo e( base_url() ); ?>/sitemap.xml" target="_blank" rel="noopener"><code><?php echo e( base_url() ); ?>/sitemap.xml</code></a></td>
			</tr>
			<tr>
				<td><strong>robots.txt</strong></td>
				<td><a href="<?php echo e( base_url() ); ?>/robots.txt" target="_blank" rel="noopener"><code><?php echo e( base_url() ); ?>/robots.txt</code></a></td>
			</tr>
			<?php foreach ( $citta as $c ) : ?>
				<?php if ( 'pubblicata' !== $c['stato'] ) { continue; } ?>
				<tr>
					<td><?php echo e( $c['nome'] ); ?></td>
					<td><a href="<?php echo e( base_url() ); ?>/sitemap-<?php echo e( $c['slug'] ); ?>.xml" target="_blank" rel="noopener"><code>/sitemap-<?php echo e( $c['slug'] ); ?>.xml</code></a></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="pc-nota" style="margin-top:14px">
		<strong>Consiglio.</strong> In Search Console crea una proprietà "prefisso URL" per ogni città
		(<code><?php echo e( base_url() ); ?>/trapani/</code>): così vedi posizioni e clic città per città,
		invece di un unico totale che non dice niente.
	</p>
</div>

<?php if ( ! empty( $duplicati ) ) : ?>
<div class="pc-scheda">
	<h2>Testi troppo simili</h2>
	<p class="pc-scheda__nota">Google sceglie una pagina sola e scarta le altre. Riscrivi questi testi.</p>
	<table class="pc-tabella">
		<thead><tr><th>Pagina</th><th>Pagina simile</th><th>Somiglianza</th></tr></thead>
		<tbody>
		<?php foreach ( array_slice( $duplicati, 0, 40 ) as $d ) : ?>
			<?php
			$c1 = citta_per_id( $d[0]['citta_id'] );
			$c2 = citta_per_id( $d[1]['citta_id'] );
			?>
			<tr>
				<td><a href="admin.php?p=pagina-modifica&id=<?php echo e( $d[0]['id'] ); ?>"><?php echo e( ( $c1 ? $c1['nome'] . ' · ' : '' ) . $d[0]['titolo'] ); ?></a></td>
				<td><a href="admin.php?p=pagina-modifica&id=<?php echo e( $d[1]['id'] ); ?>"><?php echo e( ( $c2 ? $c2['nome'] . ' · ' : '' ) . $d[1]['titolo'] ); ?></a></td>
				<td><span class="pc-stato pc-stato--bozza"><?php echo (int) $d[2]; ?>%</span></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

<?php if ( ! empty( $vuote ) ) : ?>
<div class="pc-scheda">
	<h2>Pagine senza testo</h2>
	<p class="pc-scheda__nota">Una pagina senza contenuto non si posiziona e trascina in basso tutto il sito.</p>
	<table class="pc-tabella">
		<thead><tr><th>Città</th><th>Pagina</th><th>Stato</th><th></th></tr></thead>
		<tbody>
		<?php foreach ( array_slice( $vuote, 0, 60 ) as $v ) : ?>
			<tr>
				<td><?php echo e( $v[0]['nome'] ); ?></td>
				<td><?php echo e( $v[1]['titolo'] ); ?></td>
				<td><span class="pc-stato pc-stato--<?php echo e( $v[1]['stato'] ); ?>"><?php echo e( $v[1]['stato'] ); ?></span></td>
				<td class="pc-tabella__azioni"><a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=pagina-modifica&id=<?php echo e( $v[1]['id'] ); ?>">Scrivi</a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

<div class="pc-scheda">
	<h2>Tutte le URL pubblicate</h2>
	<p class="pc-scheda__nota"><?php echo count( $voci ); ?> indirizzi. Sono esattamente quelli che finiscono nella sitemap.</p>
	<?php if ( empty( $voci ) ) : ?>
		<p class="pc-nota">Nessuna pagina pubblicata: la sitemap è vuota e Google non ha niente da leggere.</p>
	<?php else : ?>
		<table class="pc-tabella">
			<thead><tr><th>URL</th><th>Ultima modifica</th></tr></thead>
			<tbody>
			<?php foreach ( $voci as $v ) : ?>
				<tr>
					<td><a href="<?php echo e( $v['url'] ); ?>" target="_blank" rel="noopener"><code><?php echo e( str_replace( base_url(), '', $v['url'] ) ); ?></code></a></td>
					<td class="pc-nota"><?php echo e( $v['modifica'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
