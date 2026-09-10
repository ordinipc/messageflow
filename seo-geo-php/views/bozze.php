<?php
/**
 * Riscrittura assistita: stima, generazione a lotti ed elenco delle bozze.
 *
 * @package SeoGeoAudit
 * @var array $audit  Riga audit.
 * @var array $cfg    Configurazione.
 * @var bool  $pronto Chiave API presente.
 * @var array $stima  Stima di token e costo.
 * @var array $bozze  Bozze già generate.
 */

$riuscite = array_filter( $bozze, static fn( $b ) => 'ok' === $b['stato'] );
$errate   = array_filter( $bozze, static fn( $b ) => 'ok' !== $b['stato'] );

?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>"><?php echo e( $audit['sito_nome'] ); ?></a> › Riscrittura</p>
	<h1>Riscrittura assistita</h1>
	<p class="guida">Le bozze partono dalle schede dell'audit: scaletta per intento di ricerca, lunghezza obiettivo, link interni da inserire. <strong>Nulla viene pubblicato</strong>: i testi restano qui in attesa di revisione.</p>
</section>

<?php if ( $errore ) : ?><p class="avviso grave"><?php echo e( $errore ); ?></p><?php endif; ?>

<?php if ( $fatte || $errori ) : ?>
	<?php
	$etichette = array( 'accorpa' => 'accorpamenti', 'immagini' => 'immagini', 'articoli' => 'bozze' );
	$cosa      = $etichette[ $tipo ] ?? 'bozze';
	?>
	<p class="avviso <?php echo $errori ? 'grave' : 'ok-bg'; ?>">
		Ultimo lotto: <?php echo (int) $fatte; ?> <?php echo e( $cosa ); ?><?php echo $errori ? ', ' . (int) $errori . ' errori' : ''; ?>.
	</p>
<?php endif; ?>

<?php if ( 'chiave' === $esito || ! $pronto ) : ?>
	<section class="scheda">
		<h2>Manca la chiave API</h2>
		<p>Il modulo usa <strong>Google Gemini</strong> (modello <code><?php echo e( $cfg['ai']['modello'] ); ?></code>).</p>
		<ol>
			<li>Crea una chiave gratuita su <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</a>.</li>
			<li>Aprila in <code>config.php</code> e incollala in <code>'ai' =&gt; array( 'chiave' =&gt; '...' )</code>.</li>
			<li>In alternativa impostala come variabile d'ambiente <code>GEMINI_API_KEY</code>: non finisce nei backup del sito.</li>
		</ol>
		<p class="nota">Finché la chiave manca, il resto dell'applicazione funziona normalmente: la riscrittura è un modulo separato.</p>
	</section>
<?php endif; ?>

<div class="riquadri">
	<div class="riquadro">
		<span class="etichetta">Articoli in coda</span>
		<strong><?php echo num( $stima['articoli'] ); ?></strong>
		<span class="sotto">da riscrivere o accorpare</span>
	</div>
	<div class="riquadro">
		<span class="etichetta">Bozze pronte</span>
		<strong><?php echo num( count( $riuscite ) ); ?></strong>
		<span class="sotto"><?php echo count( $errate ) ? num( count( $errate ) ) . ' con errori' : 'nessun errore'; ?></span>
	</div>
	<div class="riquadro">
		<span class="etichetta">Costo stimato totale</span>
		<strong><?php echo number_format( $stima['costo_stimato'], 2, ',', '.' ); ?> €</strong>
		<span class="sotto"><?php echo num( $stima['token_in'] + $stima['token_out'] ); ?> token · prezzi da config.php</span>
	</div>
	<div class="riquadro">
		<span class="etichetta">Modello</span>
		<strong style="font-size:17px"><?php echo e( $cfg['ai']['modello'] ); ?></strong>
		<span class="sotto">Google Gemini</span>
	</div>
</div>

<?php if ( $pronto && $stima['articoli'] > 0 ) : ?>
<form class="scheda" method="post" action="?p=genera">
	<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
	<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">

	<h2>Genera un lotto</h2>
	<p class="guida">Si procede a lotti per non superare il tempo massimo di esecuzione del server. Ogni articolo richiede 10-30 secondi.</p>

	<label for="quante">Quanti articoli in questo lotto</label>
	<input id="quante" type="number" name="quante" value="<?php echo (int) $cfg['ai']['articoli_per_volta']; ?>" min="1" max="25" style="width:110px;padding:8px;border:1px solid var(--linea);border-radius:4px">

	<p class="nota">Su hosting condiviso conviene restare su 3-5 per volta. Da riga di comando non c'è limite: <code>php cli/riscrivi.php <?php echo (int) $audit['id']; ?> --limite=50</code></p>

	<button class="bottone" type="submit">Genera bozze</button>
</form>
<?php endif; ?>

<?php if ( $pronto && $gruppi > 0 ) : ?>
<form class="scheda" method="post" action="?p=genera">
	<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
	<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">
	<input type="hidden" name="tipo" value="accorpa">

	<h2>Cannibalizzazione: <?php echo num( $gruppi ); ?> gruppi da fondere</h2>
	<p class="guida">
		Questi articoli competono fra loro per la stessa ricerca e si tolgono forza a vicenda.
		Il modello riceve tutti i testi del gruppo e ne produce uno solo, più completo di ciascuno:
		tiene quello che ha valore, elimina le ripetizioni e segnala le contraddizioni fra le fonti.
		Gli articoli assorbiti vanno poi reindirizzati con un 301 sul principale — i redirect sono già
		calcolati in <a href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>">Applica sul sito</a>.
	</p>

	<label for="quante_gruppi">Quanti gruppi in questo lotto</label>
	<input id="quante_gruppi" type="number" name="quante" value="3" min="1" max="10" style="width:110px;padding:8px;border:1px solid var(--linea);border-radius:4px">
	<p class="nota">Un accorpamento richiede più tempo di una riscrittura: il modello legge fino a quattro articoli interi.</p>

	<button class="bottone" type="submit">Fondi i gruppi</button>
</form>
<?php endif; ?>

<?php if ( ! empty( $immagini['ignoti'] ) ) : ?>
	<section class="scheda">
		<h2>Immagini in evidenza: dato non disponibile</h2>
		<p class="guida">
			Questa analisi è stata fatta con una versione precedente del programma, che non
			registrava quali articoli hanno già un'immagine in evidenza: <?php echo num( $immagini['ignoti'] ); ?> contenuti
			risultano senza informazione. Rilancia l'analisi caricando di nuovo l'export
			(<a href="?p=nuovo">Nuova analisi</a>): il conteggio diventa esatto e non paghi
			immagini per articoli che ce l'hanno già.
		</p>
	</section>
<?php endif; ?>

<?php if ( $pronto && $immagini['mancanti'] > 0 ) : ?>
<form class="scheda" method="post" action="?p=genera">
	<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
	<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">
	<input type="hidden" name="tipo" value="immagini">

	<h2>Immagini in evidenza: <?php echo num( $immagini['mancanti'] ); ?> mancanti</h2>
	<p class="guida">
		Senza immagine in evidenza mancano og:image e twitter:image: le condivisioni social non hanno
		anteprima e Google Discover esclude la pagina. Le immagini vengono generate in formato
		orizzontale, senza testo e senza logo, e salvate in <code>storage/export/audit-<?php echo (int) $audit['id']; ?>/immagini/</code>.
		<?php if ( $immagini['generate'] ) : ?>
			Finora ne sono state generate <strong><?php echo num( $immagini['generate'] ); ?></strong>.
		<?php endif; ?>
	</p>

	<label for="quante_img">Quante immagini in questo lotto</label>
	<input id="quante_img" type="number" name="quante" value="3" min="1" max="10" style="width:110px;padding:8px;border:1px solid var(--linea);border-radius:4px">

	<?php if ( $wp_pronto ) : ?>
		<label style="display:flex;gap:8px;align-items:center;margin:10px 0;font-weight:400">
			<input type="checkbox" name="invia" value="1" checked>
			Caricale subito sul sito e impostale come immagine in evidenza
		</label>
	<?php endif; ?>

	<p class="nota">
		La generazione di immagini richiede un progetto Google con fatturazione attiva: sul piano gratuito
		l'API risponde con un errore di quota. Un avvertimento onesto: per una web agency le foto dei
		lavori veri valgono più di qualsiasi immagine generata — questo serve a coprire l'archivio storico,
		non a sostituire il portfolio.
	</p>

	<button class="bottone" type="submit">Genera immagini</button>
</form>
<?php endif; ?>

<?php if ( $bozze ) : ?>
<section class="scheda">
	<h2>Bozze generate</h2>
	<div class="tabellabox">
		<table>
			<thead><tr><th>Stato</th><th>Articolo</th><th class="num">Parole</th><th>Da verificare</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $bozze as $b ) : ?>
				<?php $verifiche = json_decode( (string) $b['da_verificare'], true ) ?: array(); ?>
				<tr>
					<td><span class="tag <?php echo 'ok' === $b['stato'] ? 'ok' : 'grave'; ?>"><?php echo 'ok' === $b['stato'] ? 'pronta' : 'errore'; ?></span></td>
					<td>
						<strong><?php echo e( $b['titolo'] ); ?></strong>
						<div class="sotto">
							<?php if ( 'ok' === $b['stato'] ) : ?>
								da <?php echo num( $b['parole_originali'] ); ?> a <?php echo num( $b['parole'] ); ?> parole · <?php echo e( $b['note'] ); ?>
							<?php else : ?>
								<?php echo e( $b['errore'] ); ?>
							<?php endif; ?>
						</div>
					</td>
					<td class="num"><?php echo 'ok' === $b['stato'] ? num( $b['parole'] ) : '—'; ?></td>
					<td class="stretta">
						<?php if ( $verifiche ) : ?>
							<?php echo count( $verifiche ); ?> dati reali da inserire
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td class="num">
						<?php if ( 'ok' === $b['stato'] ) : ?>
							<a href="?p=bozza&amp;b=<?php echo (int) $b['id']; ?>">apri</a> ·
							<a href="?p=bozza&amp;b=<?php echo (int) $b['id']; ?>&amp;scarica=1">scarica</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
<?php endif; ?>

<section class="scheda">
	<h2>Prima di pubblicare</h2>
	<ol>
		<li>Sostituisci ogni <code>[DA VERIFICARE: ...]</code> con dati reali: prezzi, tempi, risultati. Il modello ha l'istruzione di non inventarli mai.</li>
		<li>Aggiungi almeno un'esperienza diretta o un caso vostro: è quello che distingue il testo da mille altri simili.</li>
		<li>Rileggi e taglia. Una bozza da 1.200 parole di solito ne regge 900 di buone.</li>
		<li>Inserisci un'immagine originale con alt descrittivo e imposta l'immagine in evidenza.</li>
	</ol>
	<p class="nota">Pubblicare in massa testo generato senza revisione è ciò che le linee guida antispam di Google chiamano abuso di contenuti scalati: il modulo è costruito per evitarlo, non per aggirare il problema.</p>
</section>
