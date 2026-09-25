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

<?php /* Nascondere la sottocartella: le regole si generano qui, si
	incollano a mano nella radice, e poi si prova che funzionino. Il
	portale quel file non può né leggerlo né scriverlo, quindi l'unica
	verifica onesta è chiedere al server. */ ?>
<?php $cartella = trim( cartella_reale(), '/' ); ?>
<?php if ( '' !== $cartella ) : ?>
	<?php $nascosta = '1' === (string) impostazione( 'cartella_nascosta', '0' ); ?>
	<div class="pc-scheda">
		<h2>Togliere <code>/<?php echo e( $cartella ); ?></code> dagli indirizzi</h2>
		<p class="pc-scheda__nota">
			I file restano dove sono: è il server a girare le richieste. Gli indirizzi
			diventano <code><?php echo e( preg_replace( '#/' . preg_quote( $cartella, '#' ) . '$#', '', base_url() ) ); ?>/trapani/</code>
			invece di <code>…/<?php echo e( $cartella ); ?>/trapani/</code>.
		</p>

		<p class="pc-scheda__nota"><strong>1.</strong> Copia queste righe nel file <code>.htaccess</code> della
			radice del dominio, <strong>prima</strong> del blocco <code># BEGIN WordPress</code>.</p>
		<textarea class="pc-codice" readonly rows="16" onclick="this.select()"><?php echo e( regole_htaccess() ); ?></textarea>

		<p class="pc-scheda__nota" style="margin-top:14px"><strong>2.</strong> Torna qui e premi il pulsante: il portale
			prova gli indirizzi corti uno per uno e ti dice se il server li ha presi.</p>
		<p><a class="pc-btn pc-btn--ghost" href="admin.php?p=seo&prova=1#prova">Prova gli indirizzi corti</a></p>

		<?php if ( isset( $_GET['prova'] ) ) : ?>
			<?php $esiti = prova_indirizzi_nascosti(); ?>
			<table class="pc-tabella" id="prova">
				<tbody>
					<?php foreach ( $esiti as $slug => $e ) : ?>
						<tr>
							<td><strong><?php echo e( $e['nome'] ); ?></strong><br><span class="pc-nota"><code><?php echo e( $e['url'] ); ?></code></span></td>
							<td>
								<?php if ( $e['ok'] ) : ?>
									<span style="color:var(--pc-ok);font-weight:700">✓ risponde</span>
								<?php else : ?>
									<span style="color:#b3261e;font-weight:700">✗ <?php echo 0 === $e['stato'] ? 'non raggiungibile' : $e['stato']; ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $esiti ) ) : ?>
						<tr><td colspan="2">Nessuna città pubblicata da provare<?php echo function_exists( 'curl_init' ) ? '' : ', oppure cURL non è disponibile su questo server'; ?>.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
			<p class="pc-nota">
				Un <code>404</code> vuol dire che quella città non è nelle regole: se l'hai
				aggiunta dopo aver copiato il blocco, ricopialo da qui sopra.
				<br>
				<strong>«Non raggiungibile» su tutte</strong> di solito non vuol dire che le
				regole siano sbagliate: certi server non lasciano che un sito chiami se
				stesso. Apri uno di quegli indirizzi nel browser: se si vede la pagina,
				va tutto bene.
			</p>
		<?php endif; ?>

		<p class="pc-scheda__nota" style="margin-top:14px"><strong>3.</strong> Solo quando rispondono tutte:
			in <a href="admin.php?p=impostazioni">Impostazioni</a> togli <code>/<?php echo e( $cartella ); ?></code>
			dall'indirizzo del portale e spunta «Nascondi la cartella».
			<?php if ( $nascosta ) : ?>
				<strong style="color:var(--pc-ok)">Fatto: la casella è spuntata.</strong>
			<?php endif; ?>
		</p>

		<p class="pc-nota" style="margin-top:14px">
			<strong>Tre cose da sapere prima.</strong>
			Una pagina di WordPress che si chiama come una città diventa irraggiungibile:
			vince il portale. Se un plugin SEO gestisce già <code>/sitemap.xml</code>, togli
			quella riga dal blocco. E la pagina d'ingresso del portale, quella con l'elenco
			delle città, all'indirizzo corto è la home di WordPress: per mostrare l'elenco
			dentro WordPress usa lo shortcode <code>[portale_citta_zone]</code>.
		</p>

		<p class="pc-nota">
			Se le pagine sono già indicizzate, i vecchi indirizzi vanno reindirizzati ai
			nuovi con un 301, o il lavoro fatto con Google riparte da zero.
		</p>
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
			<?php /* Le città senza sitemap si elencano lo stesso, con il
				motivo: sparire e basta fa cercare l'indirizzo a mano, e
				poi mandarlo a Search Console, che lo segna in errore. */ ?>
			<?php $senza = array(); ?>
			<?php foreach ( $citta as $c ) : ?>
				<?php
				if ( 'pubblicata' !== $c['stato'] ) {
					$senza[] = array( $c['nome'], 'è in bozza' );
					continue;
				}
				if ( empty( sitemap_voci( $c['id'] ) ) ) {
					$senza[] = array( $c['nome'], 'non ha ancora pagine pubblicate' );
					continue;
				}
				?>
				<tr>
					<td><?php echo e( $c['nome'] ); ?></td>
					<td><a href="<?php echo e( base_url() ); ?>/sitemap-<?php echo e( $c['slug'] ); ?>.xml" target="_blank" rel="noopener"><code>/sitemap-<?php echo e( $c['slug'] ); ?>.xml</code></a></td>
				</tr>
			<?php endforeach; ?>
			<?php foreach ( $senza as $s ) : ?>
				<tr>
					<td><?php echo e( $s[0] ); ?></td>
					<td><span class="pc-nota">Nessuna sitemap: <?php echo e( $s[1] ); ?>. L'indirizzo risponde 404, non mandarlo a Search Console.</span></td>
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
