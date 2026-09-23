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
			<?php if ( '0' !== (string) impostazione( 'ia_consenti', '1' ) ) : ?>
			<tr>
				<td><strong>llms.txt</strong><br><span class="pc-nota">L'indice scritto per gli assistenti IA.</span></td>
				<td><a href="<?php echo e( base_url() ); ?>/llms.txt" target="_blank" rel="noopener"><code><?php echo e( base_url() ); ?>/llms.txt</code></a></td>
			</tr>
			<?php endif; ?>
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

<div class="pc-scheda">
	<h2>Farsi trovare dagli assistenti IA</h2>
	<p class="pc-scheda__nota">
		ChatGPT, Gemini, Perplexity e Google con le risposte generate non «posizionano»
		pagine: leggono e citano. Quello che pesa non è più solo la parola chiave nel
		titolo, ma quanto sono estraibili i fatti — recapiti, zone, orari, prezzi, e
		soprattutto <strong>domande con risposta</strong>.
	</p>

	<table class="pc-tabella">
		<tbody>
			<tr>
				<td><strong>Dati strutturati</strong><br><span class="pc-nota">LocalBusiness con indirizzo, orari, zone servite, coordinate, prezzi e profili social.</span></td>
				<td>✓ su ogni pagina</td>
			</tr>
			<tr>
				<td><strong>FAQ sulle pagine</strong><br><span class="pc-nota">Domanda e risposta visibili, più lo schema FAQPage. È quello che gli assistenti citano.</span></td>
				<td>
					<?php
					$con_faq = 0;
					$pubbliche = 0;
					foreach ( pagine_tutte() as $pg ) {
						if ( 'pubblicata' !== $pg['stato'] ) { continue; }
						$pubbliche++;
						if ( ! empty( $pg['faq'] ) && pagina_mostra( $pg, 'faq' ) ) { $con_faq++; }
					}
					echo (int) $con_faq . ' su ' . (int) $pubbliche . ' pagine';
					?>
					<?php if ( $con_faq < $pubbliche ) : ?>
						<br><span class="pc-nota">Aprendo una pagina, scheda FAQ → «✦ Proponi 6 FAQ».</span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong>llms.txt</strong><br><span class="pc-nota">Indice in testo semplice con attività, contatti, città e pagine.</span></td>
				<td><?php echo '0' !== (string) impostazione( 'ia_consenti', '1' ) ? '✓ attivo' : '✗ spento'; ?></td>
			</tr>
			<tr>
				<td><strong>robots.txt</strong><br><span class="pc-nota">GPTBot, ClaudeBot, PerplexityBot, Google-Extended e gli altri, nominati uno per uno.</span></td>
				<td><?php echo '0' !== (string) impostazione( 'ia_consenti', '1' ) ? '✓ ammessi' : '✗ bloccati'; ?></td>
			</tr>
		</tbody>
	</table>

	<p class="pc-nota" style="margin-top:14px">
		Si accende e si spegne da <a href="admin.php?p=impostazioni">Impostazioni → SEO</a>.
		<strong>Una cosa detta con onestà:</strong> <code>llms.txt</code> è una convenzione
		giovane e non tutti gli assistenti la seguono. I dati strutturati e le FAQ, invece,
		li leggono già tutti — se hai tempo per una cosa sola, scrivi le FAQ.
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

<?php
/* Articoli importati: elenco dei redirect dal vecchio indirizzo al nuovo. */
$redirect = array();
foreach ( db_righe( 'SELECT citta_id, slug, origine, stato FROM ' . db_tab( 'articoli' ) . " WHERE origine <> '' ORDER BY origine ASC" ) as $r ) {
	$c = citta_per_id( $r['citta_id'] );
	$b = $c ? pagina_blog( $c['id'] ) : null;
	if ( ! $c || ! $b || 'pubblicato' !== $r['stato'] ) {
		continue;
	}
	$vecchio = parse_url( $r['origine'], PHP_URL_PATH );
	$redirect[] = array(
		'da' => null === $vecchio ? $r['origine'] : $vecchio,
		'a'  => url_articolo( $c, array( 'slug' => $r['slug'] ), $b ),
	);
}
?>

<?php if ( ! empty( $redirect ) ) : ?>
<div class="pc-scheda">
	<h2>Redirect degli articoli importati</h2>
	<p class="pc-scheda__nota">
		<?php echo count( $redirect ); ?> articoli importati sono ancora pubblicati anche all'indirizzo
		di origine. Lo stesso testo a due indirizzi dello stesso dominio si fa concorrenza da solo:
		o togli quelli vecchi, o li reindirizzi qui.
	</p>

	<label>Regole per <code>.htaccess</code> del sito di origine
		<textarea class="pc-codice" readonly style="min-height:200px" onclick="this.select()"><?php
		echo e( "# Articoli spostati nel portale città — generato il " . oggi() . "\n" );
		echo e( "<IfModule mod_rewrite.c>\n" );
		echo e( "\tRewriteEngine On\n" );
		foreach ( $redirect as $r ) {
			echo e( "\tRedirect 301 " . $r['da'] . " " . $r['a'] . "\n" );
		}
		echo e( "</IfModule>\n" );
		?></textarea>
		<small>
			Clicca dentro per selezionare tutto. Vanno incollate <strong>sopra</strong> il blocco di
			WordPress, non dentro. Con tanti articoli conviene un plugin di redirect o un file separato.
		</small>
	</label>

	<p class="pc-nota">
		<strong>Prima di farlo:</strong> un redirect è permanente e Google ci mette settimane a
		riassorbirlo. Se gli articoli posizionano già bene dove sono, valuta se lasciarli lì e
		non pubblicarli qui.
	</p>
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
