<?php
/** Libreria immagini. */
defined( 'PC_AVVIO' ) || exit;

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_FILES['file'] ) ) {
	verifica_token();
	$caricati = 0;
	$errori   = array();
	// Più file in un colpo solo.
	$n = is_array( $_FILES['file']['name'] ) ? count( $_FILES['file']['name'] ) : 0;
	for ( $i = 0; $i < $n; $i++ ) {
		if ( UPLOAD_ERR_NO_FILE === (int) $_FILES['file']['error'][ $i ] ) {
			continue;
		}
		$uno = array(
			'name'     => $_FILES['file']['name'][ $i ],
			'type'     => $_FILES['file']['type'][ $i ],
			'tmp_name' => $_FILES['file']['tmp_name'][ $i ],
			'error'    => $_FILES['file']['error'][ $i ],
			'size'     => $_FILES['file']['size'][ $i ],
		);
		$esito = media_carica( $uno );
		if ( $esito['ok'] ) {
			$caricati++;
		} else {
			$errori[] = $uno['name'] . ': ' . $esito['messaggio'];
		}
	}
	if ( ! empty( $errori ) ) {
		avviso( implode( ' · ', $errori ), 'errore' );
	} else {
		avviso( $caricati . ( 1 === $caricati ? ' immagine caricata.' : ' immagini caricate.' ) );
	}
	vai_a( 'admin.php?p=media' );
}

/* --- Alleggerimento delle immagini già in libreria ----------------------- */
$esito_peso = null;
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['alleggerisci'] ) ) {
	verifica_token();

	// Impostazioni prese al volo dal riquadro, se le ha toccate.
	impostazioni_salva( array(
		'media_larghezza' => (string) max( 600, min( 4000, (int) ( $_POST['media_larghezza'] ?? 1600 ) ) ),
		'media_qualita'   => (string) max( 50, min( 95, (int) ( $_POST['media_qualita'] ?? 82 ) ) ),
	) );

	// A lotti: su hosting lento cinquanta immagini in un colpo superano il
	// tempo massimo di esecuzione, e non si saprebbe nemmeno a che punto
	// si è fermato.
	$per_giro = 15;
	$tutte    = media_tutti();
	$da       = max( 0, (int) ( $_POST['da'] ?? 0 ) );
	$prima    = (int) ( $_POST['prima'] ?? 0 );
	$dopo     = (int) ( $_POST['dopo'] ?? 0 );
	$fatte    = (int) ( $_POST['fatte'] ?? 0 );

	$lotto = array_slice( $tutte, $da, $per_giro );
	foreach ( $lotto as $m ) {
		$percorso = PC_MEDIA . '/' . basename( (string) $m['file'] );
		if ( ! is_file( $percorso ) ) {
			continue;
		}
		$peso_prima = (int) filesize( $percorso );
		$r          = media_alleggerisci( $percorso );
		media_mini_crea( $m['file'] );

		$prima += $peso_prima;
		$dopo  += (int) filesize( $percorso );
		if ( $r['fatto'] ) {
			$fatte++;
			db_esegui(
				'UPDATE ' . db_tab( 'media' ) . ' SET peso = ?, larghezza = ?, altezza = ? WHERE id = ?',
				array( (int) filesize( $percorso ), $r['larghezza'], $r['altezza'], $m['id'] )
			);
		}
	}

	$esito_peso = array(
		'da'       => $da + count( $lotto ),
		'totale'   => count( $tutte ),
		'prima'    => $prima,
		'dopo'     => $dopo,
		'fatte'    => $fatte,
		'finito'   => ( $da + count( $lotto ) ) >= count( $tutte ),
	);

	if ( $esito_peso['finito'] ) {
		$risparmio = $prima - $dopo;
		avviso(
			$risparmio > 0
				? 'Alleggerite ' . $fatte . ' immagini su ' . count( $tutte ) . ': da '
					. media_peso( $prima ) . ' a ' . media_peso( $dopo ) . ', risparmiati '
					. media_peso( $risparmio ) . ' (' . round( 100 * $risparmio / max( 1, $prima ) ) . '%).'
				: 'Le immagini erano già leggere: non c\'era niente da togliere.',
			$risparmio > 0 ? 'ok' : 'errore'
		);
		vai_a( 'admin.php?p=media' );
	}
}

if ( isset( $_GET['azione'] ) && 'elimina' === $_GET['azione'] ) {
	verifica_token();
	media_elimina( (string) ( $_GET['id'] ?? '' ) );
	avviso( 'Immagine eliminata. Controlla che non fosse usata in qualche pagina.' );
	vai_a( 'admin.php?p=media' );
}

$lista = media_tutti();
$tok   = '&token=' . rawurlencode( token() );

$peso_totale = 0;
$pesanti     = 0;
foreach ( $lista as $m ) {
	$peso_totale += (int) $m['peso'];
	// Sopra i 250 KB una foto da sito è pesante: è il peso di una pagina.
	if ( (int) $m['peso'] > 250 * 1024 ) {
		$pesanti++;
	}
}
?>

<div class="pc-titolo">
	<div>
		<h1>Immagini</h1>
		<p>Ogni immagine nuova viene rimpicciolita a <?php echo (int) media_larghezza_max(); ?> px e ricompressa: pesa fra il 70% e l'80% in meno.</p>
	</div>
</div>

<div class="pc-scheda">
	<h2>Carica</h2>
	<form method="post" enctype="multipart/form-data">
		<?php echo campo_token(); ?>
		<label>JPG, PNG, WebP, GIF o SVG — massimo 8 MB per file
			<input type="file" name="file[]" accept="image/*" multiple required>
		</label>
		<button class="pc-btn" type="submit">Carica</button>
	</form>
</div>

<?php if ( ! empty( $lista ) ) : ?>
<div class="pc-scheda">
	<h2>Peso</h2>
	<p class="pc-scheda__nota">
		<?php echo count( $lista ); ?> immagini, <strong><?php echo e( media_peso( $peso_totale ) ); ?></strong> in tutto.
		<?php if ( $pesanti > 0 ) : ?>
			Di queste <strong><?php echo (int) $pesanti; ?> superano i 250 KB</strong>: sono il peso di
			una pagina intera ciascuna, e le pagine che le mostrano si aprono lente sul telefono.
		<?php else : ?>
			Nessuna supera i 250 KB.
		<?php endif; ?>
	</p>
	<p class="pc-scheda__nota">
		Alleggerire vuol dire riscriverle più strette e meno compresse del necessario.
		<strong>Il nome del file non cambia</strong>, quindi nessuna pagina perde la sua immagine.
		Si crea anche una copia piccola per le card, dove la foto si vede larga poche centinaia di pixel.
		È un'operazione che non si può annullare: le immagini originali vengono sostituite.
	</p>

	<?php if ( $esito_peso && ! $esito_peso['finito'] ) : ?>
		<div class="pc-avviso">
			Fatte <?php echo (int) $esito_peso['da']; ?> su <?php echo (int) $esito_peso['totale']; ?>.
			Finora <?php echo e( media_peso( $esito_peso['prima'] ) ); ?> sono diventati
			<?php echo e( media_peso( $esito_peso['dopo'] ) ); ?>.
			<strong>Premi di nuovo per continuare.</strong>
		</div>
	<?php endif; ?>

	<form method="post">
		<?php echo campo_token(); ?>
		<?php if ( $esito_peso && ! $esito_peso['finito'] ) : ?>
			<input type="hidden" name="da" value="<?php echo (int) $esito_peso['da']; ?>">
			<input type="hidden" name="prima" value="<?php echo (int) $esito_peso['prima']; ?>">
			<input type="hidden" name="dopo" value="<?php echo (int) $esito_peso['dopo']; ?>">
			<input type="hidden" name="fatte" value="<?php echo (int) $esito_peso['fatte']; ?>">
		<?php endif; ?>
		<div class="pc-riga pc-riga--2">
			<label>Larghezza massima
				<input type="number" name="media_larghezza" value="<?php echo (int) media_larghezza_max(); ?>" min="600" max="4000" step="100">
				<small>Oltre questa misura l'immagine viene rimpicciolita. 1600 px basta per qualsiasi schermo.</small>
			</label>
			<label>Qualità
				<input type="number" name="media_qualita" value="<?php echo (int) media_qualita(); ?>" min="50" max="95">
				<small>82 è il punto in cui non si vede la differenza. Sotto 70 si inizia a notare.</small>
			</label>
		</div>
		<button class="pc-btn" type="submit" name="alleggerisci" value="1"
			data-conferma="Riscrivere le immagini più leggere? Gli originali vengono sostituiti e non si torna indietro.">
			<?php echo $esito_peso && ! $esito_peso['finito'] ? 'Continua' : 'Alleggerisci le immagini'; ?>
		</button>
	</form>
</div>
<?php endif; ?>

<?php if ( empty( $lista ) ) : ?>
	<div class="pc-scheda pc-vuoto">
		<h3>Nessuna immagine</h3>
		<p>Carica il logo e le foto dei lavori: le foto reali sono un segnale di autenticità.</p>
	</div>
<?php else : ?>
	<div class="pc-media">
		<?php foreach ( $lista as $m ) : ?>
			<div class="pc-media__voce">
				<?php $anteprima = media_mini( $m['file'] ); ?>
				<img src="<?php echo e( url_media( '' === $anteprima ? $m['file'] : $anteprima ) ); ?>" alt="<?php echo e( $m['alt'] ); ?>" loading="lazy">
				<div class="pc-media__info">
					<strong><?php echo e( $m['file'] ); ?></strong>
					<?php echo (int) $m['larghezza']; ?>×<?php echo (int) $m['altezza']; ?> ·
					<?php if ( (int) $m['peso'] > 250 * 1024 ) : ?>
						<strong style="color:#b3261e"><?php echo e( media_peso( $m['peso'] ) ); ?></strong>
					<?php else : ?>
						<?php echo e( media_peso( $m['peso'] ) ); ?>
					<?php endif; ?>
				</div>
				<div class="pc-media__azioni">
					<a class="pc-btn pc-btn--rosso pc-btn--piccolo" href="admin.php?p=media&azione=elimina&id=<?php echo e( $m['id'] ) . $tok; ?>"
						data-conferma="Eliminare <?php echo e( $m['file'] ); ?>?">Elimina</a>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
