<?php
/**
 * Vecchio e nuovo affiancati, con il pulsante che sovrascrive.
 *
 * @package SeoGeoAudit
 * @var array  $audit  Riga audit.
 * @var array  $righe  Bozze con il contenuto attuale del sito.
 * @var bool   $pronto Collegamento a WordPress configurato.
 * @var array  $costruttori wp_id => costruttore visuale che disegna il contenuto.
 * @var array  $strutture   wp_id => che cosa c e dentro alla struttura di Elementor.
 * @var string $filtro      Vista scelta: '', 'da-inviare', 'online', 'a-mano',
 *                          'da-completare', 'da-ripulire'.
 * @var string $esito  Messaggio.
 * @var string $errore Errore.
 */

// Un contenuto disegnato da un costruttore visuale non si puo sovrascrivere:
// il testo che si vede non sta in post_content, quindi scriverci dentro
// riesce senza errori e non cambia niente. Vanno esclusi qui, non scoperti
// dopo aver guardato l articolo convinti che fosse cambiato.
$con_costruttore = static function ( $riga ) use ( $costruttori ) {
	return (string) ( $costruttori[ (string) $riga['wp_id'] ] ?? '' );
};

// Con Elementor si scrive dentro al suo blocco di testo, ma solo quando ce
// n e uno solo: con due non si puo sapere quale sia l articolo e quale una
// didascalia, e indovinare vorrebbe dire cancellare qualcosa che serviva.
$scrivibile = static function ( $riga ) use ( $costruttori, $strutture ) {
	$costruttore = (string) ( $costruttori[ (string) $riga['wp_id'] ] ?? '' );

	if ( '' === $costruttore ) {
		return true;
	}

	if ( 'Elementor' !== $costruttore ) {
		return false;
	}

	return ! empty( $strutture[ (string) $riga['wp_id'] ]['scrivibile'] );
};

$perche_no = static function ( $riga ) use ( $costruttori, $strutture ) {
	$costruttore = (string) ( $costruttori[ (string) $riga['wp_id'] ] ?? '' );

	if ( 'Elementor' !== $costruttore ) {
		return 'è costruito con ' . $costruttore . ': il testo va incollato a mano nel costruttore';
	}

	$dentro = $strutture[ (string) $riga['wp_id'] ] ?? array();

	return 'la struttura di Elementor non è leggibile: ' . ( $dentro['errore'] ?: 'non si apre' );
};

// Che cosa succedera a questo contenuto, detto prima di premere.
$cosa_fara = static function ( $riga ) use ( $costruttori, $strutture ) {
	if ( '' === (string) ( $costruttori[ (string) $riga['wp_id'] ] ?? '' ) ) {
		return '';
	}

	$dentro = $strutture[ (string) $riga['wp_id'] ] ?? array();

	if ( ! empty( $dentro['post_content'] ) ) {
		return 'Elementor mostra il contenuto di WordPress: si scrive lì';
	}

	if ( empty( $dentro['blocchi'] ) ) {
		return 'in Elementor non c\'è un blocco di testo: ne verrà aggiunto uno in fondo, il resto della pagina non si tocca';
	}

	if ( 1 === (int) $dentro['blocchi'] ) {
		return 'scrive dentro all\'unico blocco di testo di Elementor';
	}

	return 'in Elementor ci sono ' . (int) $dentro['blocchi'] . ' blocchi di testo: il testo nuovo va nel più lungo, gli altri non si toccano — se uno conteneva un pezzo del vecchio articolo, quel pezzo resta in pagina sotto al nuovo';
};

// Il testo che parte davvero e questo: ripulito dai dati strutturati che il
// modello aveva scritto nel corpo. Tutto quello che si decide qui sotto va
// deciso su questo, non sul testo grezzo in archivio, altrimenti la pagina
// dice una cosa e il pulsante ne fa un altra.
$ripulito = static function ( $riga ) {
	static $fatti = array();

	$chiave = (int) $riga['id'];

	if ( ! isset( $fatti[ $chiave ] ) ) {
		$fatti[ $chiave ] = \SeoGeo\Html::senzaDatiStrutturati( (string) $riga['corpo_html'] );
	}

	return $fatti[ $chiave ];
};

// Quali bozze hanno ancora un «[DA VERIFICARE: ...]» dentro. Non ferma
// niente: si mandano online come le altre, e chi pubblica decide. Serve a
// dirlo prima, e a offrire i due pulsanti che lo chiudono.
//
// Si guarda il testo ripulito: i segnaposto che stavano dentro al JSON-LD
// sparivano insieme a quello, e contarli lo stesso avrebbe segnalato bozze
// che erano gia a posto.
$mancanti = static function ( $riga ) use ( $ripulito ) {
	return \SeoGeo\Ai\Verifiche::restano( array( 'corpo_html' => $ripulito( $riga ) ) + $riga );
};

// Gli articoli gia online il cui testo conteneva i dati strutturati: sul sito
// ce l hanno ancora, perche sono partiti prima che si ripulisse. Si ritrovano
// da qui e si rimandano, senza cercarli a mano in mezzo a duecento.
$da_ripulire = array_values(
	array_filter(
		$righe,
		static fn( $r ) => ! empty( $r['inviata_il'] ) && $ripulito( $r ) !== (string) $r['corpo_html']
	)
);

$da_completare = array_values(
	array_filter(
		$righe,
		static fn( $r ) => empty( $r['inviata_il'] ) && $scrivibile( $r ) && $mancanti( $r )
	)
);

$da_inviare = array_values(
	array_filter(
		$righe,
		static fn( $r ) => empty( $r['inviata_il'] ) && $scrivibile( $r )
	)
);

$bloccate = array_values( array_filter( $righe, static fn( $r ) => ! $scrivibile( $r ) ) );
?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>"><?php echo e( $audit['sito_nome'] ); ?></a> › <a href="?p=bozze&amp;id=<?php echo (int) $audit['id']; ?>">Riscrittura</a> › Vecchio e nuovo</p>
	<h1>Vecchio e nuovo</h1>
	<p class="guida">
		A sinistra quello che c'è adesso sul sito, a destra il testo migliorato.
		«Sovrascrivi» scrive <strong>sull'articolo che esiste già</strong>: non crea un doppione e l'indirizzo
		non cambia. Il testo precedente resta da parte e si rimette con «Annulla tutto e ripristina»
		in <a href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>">Applica sul sito</a>.
	</p>
</section>

<?php if ( $esito ) : ?><p class="avviso ok-bg"><?php echo e( $esito ); ?></p><?php endif; ?>
<?php if ( $errore ) : ?><p class="avviso grave"><?php echo e( $errore ); ?></p><?php endif; ?>

<?php if ( ! $pronto ) : ?>
	<p class="avviso grave">Il collegamento a WordPress non è configurato: senza, non si può scrivere sul sito.</p>
<?php endif; ?>

<?php if ( $da_ripulire ) : ?>
	<section class="scheda">
		<h2>⚠︎ <?php echo 1 === count( $da_ripulire ) ? 'Un articolo online ha' : num( count( $da_ripulire ) ) . ' articoli online hanno' ?> i dati strutturati stampati nel testo</h2>
		<p class="guida">
			Sono partiti prima della correzione: nel corpo hanno il blocco <code>{ "@context": …}</code>
			che si legge in pagina. Il testo qui in archivio adesso è ripulito, quindi basta
			<strong>rimandarli</strong>: stesso articolo, stesso indirizzo, senza il blocco.
			In alternativa «Rimetti il testo di prima» rimette l'articolo com'era prima della riscrittura.
		</p>
		<ul class="guida">
			<?php foreach ( array_slice( $da_ripulire, 0, 5 ) as $riga ) : ?>
				<li><a href="#bozza-<?php echo (int) $riga['id']; ?>"><?php echo e( $riga['titolo_vecchio'] ); ?></a></li>
			<?php endforeach; ?>
			<?php if ( count( $da_ripulire ) > 5 ) : ?>
				<li>e altri <?php echo num( count( $da_ripulire ) - 5 ); ?></li>
			<?php endif; ?>
		</ul>
		<?php if ( $pronto ) : ?>
			<div class="azioni">
				<a class="bottone" href="?p=confronto-bozze&amp;id=<?php echo (int) $audit['id']; ?>&amp;filtro=da-ripulire">Vedili e rimandali</a>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( ! $righe ) : ?>
	<section class="scheda">
		<p class="guida">Nessuna bozza pronta. Vai a <a href="?p=bozze&amp;id=<?php echo (int) $audit['id']; ?>">Riscrittura assistita</a> per generarle.</p>
	</section>
<?php else : ?>

<section class="scheda">
	<p class="guida">
		<strong><?php echo num( count( $da_inviare ) ); ?></strong> da inviare ·
		<?php echo num( count( array_filter( $righe, static fn( $r ) => ! empty( $r['inviata_il'] ) ) ) ); ?> già online
		<?php if ( $bloccate ) : ?>
			· <strong><?php echo num( count( $bloccate ) ); ?> non sovrascrivibili</strong>
		<?php endif; ?>
		<?php if ( $da_completare ) : ?>
			· <strong><?php echo num( count( $da_completare ) ); ?> con dati da verificare</strong>
		<?php endif; ?>
		<?php
		$accorpamenti = array_filter( $righe, static fn( $r ) => 0 === strpos( (string) ( $r['note'] ?? '' ), 'Accorpa ' ) );
		?>
		<?php if ( $accorpamenti ) : ?>
			· <strong><?php echo num( count( $accorpamenti ) ); ?> uniscono più articoli</strong>
		<?php endif; ?>
	</p>
	<?php if ( $accorpamenti ) : ?>
		<p class="nota">
			<?php echo num( count( $accorpamenti ) ); ?> di queste bozze nascono da un accorpamento: uniscono
			due o più articoli che si contendevano la stessa ricerca. Mandarle online non chiude il lavoro —
			gli articoli assorbiti restano pubblicati. Dopo l'invio servono, <strong>in quest'ordine</strong>:
            i <a href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>#redirect">redirect 301</a>, e poi
			il cestino per gli assorbiti.
		</p>
	<?php endif; ?>
	<?php if ( $da_completare ) : ?>
		<p class="nota">
			<?php echo num( count( $da_completare ) ); ?> bozze hanno ancora dei segnaposto
			<code>[DA VERIFICARE: …]</code> nel testo. <strong>Si mandano online lo stesso</strong>: sono
			dentro a «Sovrascrivi tutte» come le altre, e i segnaposto si leggeranno in pagina.
			Se preferisci evitarlo, in
			<a href="?p=bozze&amp;id=<?php echo (int) $audit['id']; ?>#verifiche">Riscrittura assistita</a>
			ci sono due pulsanti che li chiudono senza farti scrivere niente: «Cercali su Google e compila»
			e «Gira le frasi senza il dato mancante».
		</p>
	<?php endif; ?>
	<?php if ( $bloccate ) : ?>
		<?php
		// Il conto da solo non dice niente: "17 non sovrascrivibili" fa
		// chiedere perche. Il motivo c e gia sotto a ogni contenuto, ma
		// scorrere 227 righe per contarli a mano non e un modo di saperlo.
		$motivi = array();

		foreach ( $bloccate as $riga ) {
			$costruttore = (string) ( $costruttori[ (string) $riga['wp_id'] ] ?? '' );
			$dentro      = $strutture[ (string) $riga['wp_id'] ] ?? array();

			$chiave = 'Elementor' !== $costruttore
				? 'costruiti con ' . ( $costruttore ?: 'un altro costruttore' )
				: 'con la struttura di Elementor illeggibile';

			$motivi[ $chiave ] = ( $motivi[ $chiave ] ?? 0 ) + 1;
		}

		arsort( $motivi );
		?>
		<p class="nota">
			<strong><?php echo num( count( $bloccate ) ); ?> vanno fatti a mano:</strong>
			<?php
			$pezzi = array();

			foreach ( $motivi as $chiave => $quanti ) {
				$pezzi[] = num( $quanti ) . ' ' . $chiave;
			}

			echo e( implode( ', ', $pezzi ) );
			?>.
		</p>
		<p class="nota">
			Restano fuori solo i contenuti la cui struttura non si riesce ad aprire, e quelli
			costruiti con un costruttore diverso da Elementor. Per quelli: apri la bozza,
			copia il testo e incollalo dentro al costruttore.
		</p>
	<?php endif; ?>

	<?php
	// Con duecento contenuti, sapere che diciassette non si possono fare non
	// serve a niente se poi bisogna cercarli a mano in mezzo agli altri.
	$gia_online = array_values( array_filter( $righe, static fn( $r ) => ! empty( $r['inviata_il'] ) ) );

	$viste = array(
		''           => array( 'Tutti', count( $righe ) ),
		'da-inviare' => array( 'Da inviare', count( $da_inviare ) ),
		'online'     => array( 'Già online', count( $gia_online ) ),
		'a-mano'     => array( 'Da fare a mano', count( $bloccate ) ),
		'da-completare' => array( 'Con dati da verificare', count( $da_completare ) ),
		'da-ripulire'   => array( 'Da ripulire sul sito', count( $da_ripulire ) ),
	);
	?>
	<p class="filtri">
		<?php foreach ( $viste as $chiave => $vista ) : ?>
			<?php if ( ! $vista[1] && '' !== $chiave ) : continue; endif; ?>
			<?php if ( $chiave === $filtro ) : ?>
				<strong><?php echo e( $vista[0] ); ?> <?php echo num( $vista[1] ); ?></strong>
			<?php else : ?>
				<a href="?p=confronto-bozze&amp;id=<?php echo (int) $audit['id']; ?><?php echo $chiave ? '&amp;filtro=' . e( $chiave ) : ''; ?>"><?php echo e( $vista[0] ); ?> <?php echo num( $vista[1] ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</p>
	<?php
	$elenco = $righe;

	if ( 'da-inviare' === $filtro ) {
		$elenco = $da_inviare;
	} elseif ( 'online' === $filtro ) {
		$elenco = $gia_online;
	} elseif ( 'a-mano' === $filtro ) {
		$elenco = $bloccate;
	} elseif ( 'da-completare' === $filtro ) {
		$elenco = $da_completare;
	} elseif ( 'da-ripulire' === $filtro ) {
		$elenco = $da_ripulire;
	}

	// Il pulsante in blocco lavora sulla vista che si sta guardando. Prima
	// mandava sempre e solo le bozze mai inviate: nella vista «da ripulire»,
	// dove sono tutte gia online, non avrebbe fatto niente.
	$in_lotto = array_values( array_filter( $elenco, static fn( $r ) => $scrivibile( $r ) ) );

	// Quante di quelle in lotto non sono mai state mandate: se sono zero, il
	// pulsante non sta per «applicare il lavoro», sta per rifare da capo una
	// cosa gia fatta. Diceva «Sovrascrivi tutte le 100» accanto a «0 da
	// inviare · 100 gia online», e chi legge preme.
	$nuove_in_lotto = count( array_filter( $in_lotto, static fn( $r ) => empty( $r['inviata_il'] ) ) );

	$etichetta_lotto = 'da-ripulire' === $filtro
		? 'Rimanda ' . ( 1 === count( $in_lotto ) ? 'l\'articolo da ripulire' : 'i ' . count( $in_lotto ) . ' articoli da ripulire' )
		: ( 0 === $nuove_in_lotto
			? 'Riscrivi di nuovo ' . ( 1 === count( $in_lotto ) ? 'l\'articolo già online' : 'le ' . count( $in_lotto ) . ' già online' )
			: 'Sovrascrivi ' . ( 1 === count( $in_lotto ) ? 'l\'articolo' : 'tutte le ' . count( $in_lotto ) ) );
	?>
	<?php if ( $pronto && $in_lotto ) : ?>
		<?php if ( 0 === $nuove_in_lotto && 'da-ripulire' !== $filtro ) : ?>
			<p class="avviso">
				Qui non c'è niente di nuovo da mandare: queste riscritture <strong>sono già sul sito</strong>.
				Premere il pulsante le riscrive identiche sopra sé stesse — non fa danni, ma non cambia
				niente e aggiorna la data di modifica di
				<?php echo num( count( $in_lotto ) ); ?> articoli.
				Quello che serve adesso è
				<a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>">rileggere il sito</a>
				per vedere l'effetto di quello che hai già applicato.
			</p>
		<?php endif; ?>
		<div class="azioni">
			<button class="bottone<?php echo 0 === $nuove_in_lotto && 'da-ripulire' !== $filtro ? ' chiaro' : ''; ?>" type="button" id="invia-tutte"
				data-gia-online="<?php echo 0 === $nuove_in_lotto && 'da-ripulire' !== $filtro ? '1' : ''; ?>"
				data-quante="<?php echo (int) count( $in_lotto ); ?>"><?php echo e( $etichetta_lotto ); ?></button>
		</div>
		<div id="tutte-corso" hidden>
			<p><span id="tutte-spia" class="spia"></span> <strong id="tutte-titolo">Sto scrivendo sul sito…</strong></p>
			<div class="barra" style="height:10px;margin-bottom:12px"><i id="tutte-barra" class="ok" style="width:1%;height:10px"></i></div>
			<p class="nota"><span id="tutte-fatte">0</span> di <?php echo (int) count( $in_lotto ); ?> · <span id="tutte-errori">0</span> non riuscite</p>
			<button type="button" class="bottone chiaro" id="tutte-stop">Ferma</button>
		</div>
	<?php endif; ?>
</section>

<section class="scheda">
	<label class="etichetta" for="cerca-bozza">Cerca un articolo per titolo o indirizzo</label>
	<input type="search" id="cerca-bozza" placeholder="es. web agency palermo" autocomplete="off" style="width:100%;max-width:420px">
	<p class="nota" id="cerca-esito" hidden></p>
</section>

<?php if ( ! $elenco ) : ?>
	<section class="scheda"><p class="guida">Nessun contenuto in questa vista.</p></section>
<?php endif; ?>

<?php foreach ( $elenco as $riga ) : ?>
	<section class="scheda confronto" id="bozza-<?php echo (int) $riga['id']; ?>" data-bozza="<?php echo (int) $riga['id']; ?>" data-cerca="<?php echo e( mb_strtolower( $riga['titolo_vecchio'] . ' ' . $riga['url'] ) ); ?>">
		<h2><?php echo e( $riga['titolo_vecchio'] ); ?></h2>
		<?php if ( ! empty( $riga['inviata_il'] ) && $ripulito( $riga ) !== (string) $riga['corpo_html'] ) : ?>
			<p class="avviso grave">
				Sul sito questo articolo ha i dati strutturati stampati nel testo. «Riscrivi di nuovo»
				manda la versione ripulita allo stesso indirizzo.
			</p>
		<?php endif; ?>
		<p class="nota">
			<a href="<?php echo e( $riga['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $riga['url'] ); ?></a>
			<?php if ( ! empty( $riga['inviata_il'] ) ) : ?>
				· <strong>già online</strong> dal <?php echo e( substr( $riga['inviata_il'], 0, 16 ) ); ?>
			<?php endif; ?>
		</p>

		<div class="due-colonne">
			<div>
				<span class="etichetta">Adesso sul sito · <?php echo num( $riga['parole_vecchie'] ); ?> parole</span>
				<p class="nota"><strong>Title:</strong> <?php echo e( $riga['seo_title'] ?: '(nessuno)' ); ?></p>
				<div class="testo-bozza testo-vecchio"><?php echo nl2br( e( mb_substr( (string) $riga['testo_vecchio'], 0, 2500 ) ) ); ?></div>
			</div>
			<div>
				<span class="etichetta">Dopo · <?php echo num( str_word_count( strip_tags( (string) $riga['corpo_html'] ) ) ); ?> parole</span>
				<p class="nota"><strong>Title:</strong> <?php echo e( $riga['meta_title'] ?: '(nessuno)' ); ?></p>
				<div class="testo-bozza"><?php echo \SeoGeo\Html::senzaDatiStrutturati( \SeoGeo\Html::sanifica( (string) $riga['corpo_html'] ) ); ?></div>
			</div>
		</div>

		<?php
		// Una bozza che nasce da un accorpamento non finisce quando la si
		// manda online: gli articoli assorbiti restano pubblicati, e finche
		// non si reindirizzano il contenuto e doppio. Va detto qui, dove si
		// preme, non in un altra pagina.
		$accorpa = 0 === strpos( (string) ( $riga['note'] ?? '' ), 'Accorpa ' );
		?>
		<?php if ( $accorpa ) : ?>
			<p class="avviso">
				<strong>Questa unisce più articoli.</strong>
				<?php echo e( explode( '. ', (string) $riga['note'] )[0] ); ?>.
				Mandarla online <strong>non basta</strong>: gli articoli assorbiti restano pubblicati e il
				contenuto resta doppio. Dopo averla inviata, in
				<a href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>#redirect">Applica sul sito</a>
				attiva i redirect 301 e <strong>poi</strong> cestina gli assorbiti — in quest'ordine, se no
				chi arriva da Google trova pagina non trovata.
			</p>
		<?php endif; ?>

		<?php $buchi = $mancanti( $riga ); ?>
		<?php if ( $buchi ) : ?>
			<p class="avviso grave">
				<strong>Dati da verificare, <?php echo num( count( $buchi ) ); ?>:</strong>
				<?php echo e( implode( ' · ', array_slice( $buchi, 0, 4 ) ) ); ?><?php echo count( $buchi ) > 4 ? ' · e altri ' . num( count( $buchi ) - 4 ) : ''; ?>.
				Se lo mandi così, in pagina si leggono. «Gira prima le frasi col buco» le riscrive senza il dato.
			</p>
		<?php endif; ?>

		<div class="azioni">
			<a class="bottone chiaro" href="?p=bozza&amp;b=<?php echo (int) $riga['id']; ?>">Apri la bozza intera</a>
			<?php if ( $pronto && $scrivibile( $riga ) ) : ?>
				<button class="bottone invia-una" type="button" data-bozza="<?php echo (int) $riga['id']; ?>">
					<?php echo empty( $riga['inviata_il'] ) ? 'Sovrascrivi questo articolo' : 'Riscrivi di nuovo'; ?>
				</button>
				<?php if ( $buchi ) : ?>
					<a class="bottone chiaro" href="?p=bozze&amp;id=<?php echo (int) $audit['id']; ?>#senza-dato">Gira prima le frasi col buco</a>
				<?php endif; ?>
				<?php if ( '' !== $cosa_fara( $riga ) ) : ?>
					<span class="nota"><?php echo e( $cosa_fara( $riga ) ); ?></span>
				<?php endif; ?>
			<?php elseif ( $pronto ) : ?>
				<span class="nota"><strong>Da fare a mano:</strong> <?php echo e( $perche_no( $riga ) ); ?>.</span>
			<?php endif; ?>
			<?php if ( $pronto && ! empty( $riga['inviata_il'] ) ) : ?>
				<button class="bottone chiaro ripristina-una" type="button" data-bozza="<?php echo (int) $riga['id']; ?>">Rimetti il testo di prima</button>
			<?php endif; ?>
			<span class="nota esito-una"></span>
		</div>
	</section>
<?php endforeach; ?>

<script>
(function () {
	var token = <?php echo json_encode( token() ); ?>;
	var idAudit = <?php echo (int) $audit['id']; ?>;

	if (!window.fetch) { return; }

	function invia(idBozza) {
		return fetch('?p=api-sovrascrivi&id=' + idAudit + '&bozza=' + idBozza + '&token=' + encodeURIComponent(token))
			.then(function (r) { return r.json(); });
	}

	Array.prototype.forEach.call(document.querySelectorAll('.invia-una'), function (bottone) {
		bottone.addEventListener('click', function () {
			var scheda = bottone.closest('.confronto');
			var esito = scheda.querySelector('.esito-una');

			if (!confirm('Scrivere questo testo sull articolo pubblicato? Il testo attuale resta da parte e si può rimettere.')) {
				return;
			}

			bottone.disabled = true;
			esito.textContent = 'Sto scrivendo…';

			invia(bottone.dataset.bozza)
				.then(function (d) {
					if (d.errore) { esito.textContent = 'Errore: ' + d.errore; bottone.disabled = false; return; }

					esito.textContent = 'Scritto sul sito.';
					bottone.textContent = 'Riscrivi di nuovo';
					bottone.disabled = false;
				})
				.catch(function (e) { esito.textContent = 'Errore: ' + e.message; bottone.disabled = false; });
		});
	});

	Array.prototype.forEach.call(document.querySelectorAll('.ripristina-una'), function (bottone) {
		bottone.addEventListener('click', function () {
			var scheda = bottone.closest('.confronto');
			var esito = scheda.querySelector('.esito-una');

			if (!confirm('Rimettere sull articolo il testo che c era prima della sovrascrittura?')) {
				return;
			}

			bottone.disabled = true;
			esito.textContent = 'Sto rimettendo il testo di prima…';

			fetch('?p=api-ripristina&id=' + idAudit + '&bozza=' + bottone.dataset.bozza + '&token=' + encodeURIComponent(token))
				.then(function (r) { return r.json(); })
				.then(function (d) {
					if (d.errore) { esito.textContent = 'Errore: ' + d.errore; bottone.disabled = false; return; }

					esito.textContent = 'Rimesso il testo di prima.';
					bottone.hidden = true;
				})
				.catch(function (e) { esito.textContent = 'Errore: ' + e.message; bottone.disabled = false; });
		});
	});

	var cerca = document.getElementById('cerca-bozza');

	if (cerca) {
		var esitoCerca = document.getElementById('cerca-esito');
		var schede = document.querySelectorAll('.confronto');

		cerca.addEventListener('input', function () {
			var q = cerca.value.trim().toLowerCase();
			var visti = 0;

			Array.prototype.forEach.call(schede, function (s) {
				var dentro = !q || -1 !== (s.dataset.cerca || '').indexOf(q);
				s.hidden = !dentro;
				if (dentro) { visti++; }
			});

			esitoCerca.hidden = !q;
			esitoCerca.textContent = visti + (1 === visti ? ' articolo trovato' : ' articoli trovati') + ' su ' + schede.length + '.';
		});
	}

	var tutte = document.getElementById('invia-tutte');

	if (!tutte) { return; }

	tutte.addEventListener('click', function () {
		// Rifare una cosa gia fatta non deve partire per sbaglio.
		if (tutte.dataset.giaOnline
			&& !confirm('Queste ' + tutte.dataset.quante + ' riscritture sono già sul sito. Riscriverle identiche non cambia niente e aggiorna la data di modifica di tutti questi articoli. Procedere lo stesso?')) {
			return;
		}

		// Si prende quello che si vede: la vista scelta, meno quello che la
		// ricerca ha nascosto. Prima si guardava la scritta sul pulsante, e
		// nella vista «da ripulire» - dove dicono tutti "Riscrivi di nuovo" -
		// il lotto restava vuoto.
		var code = Array.prototype.map.call(
			document.querySelectorAll('.confronto:not([hidden])'),
			function (s) { return s.dataset.bozza; }
		).filter(function (id) {
			return !!document.querySelector('.invia-una[data-bozza="' + id + '"]');
		});

		if (!code.length || !confirm('Scrivere ' + code.length + ' testi sugli articoli pubblicati? Si può annullare.')) {
			return;
		}

		tutte.hidden = true;
		document.getElementById('tutte-corso').hidden = false;

		var fatte = 0;
		var errori = 0;
		var fermato = false;

		document.getElementById('tutte-stop').addEventListener('click', function () {
			fermato = true;
			document.getElementById('tutte-titolo').textContent = 'Mi fermo dopo questo…';
			document.getElementById('tutte-spia').className = 'spia fermo';
		});

		function prossima() {
			if (fermato || !code.length) {
				document.getElementById('tutte-spia').className = 'spia fermo';
				document.getElementById('tutte-titolo').textContent = fermato
					? 'Fermata: ' + code.length + ' non inviate'
					: 'Fatto: tutte scritte sul sito';
				document.getElementById('tutte-stop').textContent = 'Ricarica la pagina';
				document.getElementById('tutte-stop').onclick = function () { location.reload(); };
				return;
			}

			var idBozza = code.shift();

			invia(idBozza)
				.then(function (d) {
					if (d.errore) { errori++; } else { fatte++; }

					document.getElementById('tutte-fatte').textContent = fatte;
					document.getElementById('tutte-errori').textContent = errori;
					document.getElementById('tutte-barra').style.width =
						Math.max(1, Math.round((fatte / (fatte + errori + code.length)) * 100)) + '%';

					prossima();
				})
				.catch(function () { errori++; prossima(); });
		}

		prossima();
	});
})();
</script>

<?php endif; ?>
