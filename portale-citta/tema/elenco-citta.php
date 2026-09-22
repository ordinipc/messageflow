<?php
/** Home del portale: elenco di tutte le città pubblicate. */

defined( 'PC_AVVIO' ) || exit;
require_once __DIR__ . '/funzioni-tema.php';
require_once __DIR__ . '/sezioni.php';

$imp   = impostazioni();
$lista = citta_tutte( true );
$citta = null;

$nomi        = array_column( $lista, 'nome' );
$titolo      = vuoto( $imp['home_seo_titolo'] )
	? $imp['brand'] . ' — tutte le città in cui operiamo'
	: $imp['home_seo_titolo'];
$descrizione = vuoto( $imp['home_seo_desc'] )
	? 'Scegli la tua città: ' . implode( ', ', array_slice( $nomi, 0, 12 ) ) . '.'
	: $imp['home_seo_desc'];
$canonico    = base_url() . '/';
$immagine    = url_media( vuoto( $imp['home_immagine'] ) ? $imp['logo'] : $imp['home_immagine'] );
$indicizza   = '1' === (string) $imp['indicizza'];
$schemi      = array(
	array(
		'@context' => 'https://schema.org',
		'@type'    => 'CollectionPage',
		'name'     => $titolo,
		'url'      => $canonico,
	),
);

$sfondo = vuoto( $imp['home_immagine'] ) ? '' : url_media( $imp['home_immagine'] );

include __DIR__ . '/parti/testa.php';
include __DIR__ . '/parti/barra.php';
?>

<main class="glp-main">

	<div class="glp-hero<?php echo '' === $sfondo ? '' : ' glp-hero--image'; ?>"
		<?php echo '' === $sfondo ? '' : 'style="background-image:url(' . e( $sfondo ) . ')"'; ?>>
		<div class="glp-hero__inner">
			<div class="glp-hero__top glp-reveal">
				<p class="glp-hero__eyebrow"><?php echo e( vuoto( $imp['home_soprattitolo'] ) ? $imp['brand'] : $imp['home_soprattitolo'] ); ?></p>
			</div>
			<div class="glp-hero__main glp-hero__main--solo">
				<div class="glp-hero__content">
					<h1 class="glp-hero__title glp-reveal"><?php echo titolo_evidenziato( $imp['home_titolo'] ); // HTML controllato. ?></h1>
					<?php if ( ! vuoto( $imp['home_intro'] ) ) : ?>
						<p class="glp-hero__text glp-reveal"><?php echo e( $imp['home_intro'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<div class="glp-hero__corner" aria-hidden="true"></div>
	</div>

	<div class="glp-main__inner">
	<div class="glp-sections">
		<?php
		// Le stesse funzioni che risponderanno allo shortcode: una sola
		// versione di questa pagina, non due che si allontanano.
		foreach ( array_keys( sezioni_home() ) as $chiave ) {
			echo rendi_sezione_home( $chiave );
		}
		?>
	</div>

</div>
</main>

<?php
$altre = array();
include __DIR__ . '/parti/piede.php';
