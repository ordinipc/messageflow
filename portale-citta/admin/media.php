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

if ( isset( $_GET['azione'] ) && 'elimina' === $_GET['azione'] ) {
	verifica_token();
	media_elimina( (string) ( $_GET['id'] ?? '' ) );
	avviso( 'Immagine eliminata. Controlla che non fosse usata in qualche pagina.' );
	vai_a( 'admin.php?p=media' );
}

$lista = media_tutti();
$tok   = '&token=' . rawurlencode( token() );
?>

<div class="pc-titolo">
	<div>
		<h1>Immagini</h1>
		<p>Le immagini oltre 1800 px di larghezza vengono ridotte automaticamente.</p>
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

<?php if ( empty( $lista ) ) : ?>
	<div class="pc-scheda pc-vuoto">
		<h3>Nessuna immagine</h3>
		<p>Carica il logo e le foto dei lavori: le foto reali sono un segnale di autenticità.</p>
	</div>
<?php else : ?>
	<div class="pc-media">
		<?php foreach ( $lista as $m ) : ?>
			<div class="pc-media__voce">
				<img src="<?php echo e( url_media( $m['file'] ) ); ?>" alt="<?php echo e( $m['alt'] ); ?>" loading="lazy">
				<div class="pc-media__info">
					<strong><?php echo e( $m['file'] ); ?></strong>
					<?php echo (int) $m['larghezza']; ?>×<?php echo (int) $m['altezza']; ?> · <?php echo e( media_peso( $m['peso'] ) ); ?>
				</div>
				<div class="pc-media__azioni">
					<a class="pc-btn pc-btn--rosso pc-btn--piccolo" href="admin.php?p=media&azione=elimina&id=<?php echo e( $m['id'] ) . $tok; ?>"
						data-conferma="Eliminare <?php echo e( $m['file'] ); ?>?">Elimina</a>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
