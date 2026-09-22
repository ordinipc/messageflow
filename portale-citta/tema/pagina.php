<?php
/**
 * Modello di una pagina città.
 * Variabili attese da index.php: $citta, $pagina.
 */

defined( 'PC_AVVIO' ) || exit;
require_once __DIR__ . '/funzioni-tema.php';
require_once __DIR__ . '/sezioni.php';

$imp      = impostazioni();
$telefono = contatto( $citta, 'telefono' );
$whatsapp = contatto( $citta, 'whatsapp' );
$servizi  = servizi_citta( $citta );
$altre    = altre_citta( $citta['id'] );
$menu     = menu_citta( $citta, $pagina['id'] );

// Il modulo di contatto, se questa pagina lo mostra, va elaborato prima
// di stampare qualsiasi cosa: potrebbe dover reindirizzare o rispondere.
$esito_modulo = pagina_mostra( $pagina, 'modulo' ) ? modulo_gestisci( $citta, $pagina ) : null;

// Dati per la testa del documento.
$titolo      = seo_titolo( $citta, $pagina );
$descrizione = seo_descrizione( $citta, $pagina );
$canonico    = seo_canonico( $citta, $pagina );
$immagine    = vuoto( $pagina['immagine'] ) ? url_media( $imp['logo'] ) : url_media( $pagina['immagine'] );
$indicizza   = seo_indicizzabile( $citta, $pagina );
$css_extra   = $citta['css'] . "\n" . $pagina['css'];
$js_extra    = $citta['js'] . "\n" . $pagina['js'];
$schemi      = array(
	schema_attivita( $citta ),
	schema_servizio( $citta, $pagina ),
	// Le FAQ nei dati strutturati solo se sono anche sulla pagina: dichiarare
	// a Google domande che il visitatore non vede è un modo per prendersi
	// una penalizzazione.
	schema_faq( pagina_mostra( $pagina, 'faq' ) ? $pagina['faq'] : array() ),
	schema_elenco_servizi( $citta, $pagina, $servizi ),
	schema_breadcrumb( $citta, $pagina ),
);

// Pulsante principale.
$cta_url   = '';
$cta_testo = '';
if ( ! vuoto( $telefono ) ) {
	$cta_url   = 'tel:' . tel( $telefono );
	$cta_testo = 'Chiama ' . $telefono;
}

include __DIR__ . '/parti/testa.php';
include __DIR__ . '/parti/barra.php';
?>

<main class="glp-main">

<?php if ( 'pubblicata' !== $pagina['stato'] || 'pubblicata' !== $citta['stato'] ) : ?>
	<div class="glp-main__inner">
		<p style="background:#b00;color:#fff;padding:10px 14px;border-radius:6px;font:600 13px/1.4 system-ui,sans-serif;margin:0 0 16px">
			Anteprima: questa pagina è in bozza e non è visibile al pubblico né a Google.
		</p>
	</div>
<?php endif; ?>

<!-- INTESTAZIONE — sta fuori dal contenitore: nello stile "vetrina" va da bordo a bordo -->
<?php
// L'immagine della pagina fa da sfondo dell'intestazione: prima finiva
// solo in og:image e non si vedeva da nessuna parte.
$sfondo = vuoto( $pagina['immagine'] ) ? '' : url_media( $pagina['immagine'] );
?>
<div class="glp-hero<?php echo '' === $sfondo ? '' : ' glp-hero--image'; ?>"
	<?php echo '' === $sfondo ? '' : 'style="background-image:url(' . e( $sfondo ) . ')"'; ?>>
	<div class="glp-hero__inner">

		<div class="glp-hero__top glp-reveal">
			<p class="glp-hero__eyebrow"><?php echo e( trim( $imp['brand'] . ' · ' . $citta['nome'], ' ·' ) ); ?></p>
			<?php if ( ! vuoto( $telefono ) ) : ?>
				<div class="glp-hero__status">
					<span class="glp-hero__status-dot" aria-hidden="true"></span>
					Interveniamo a <?php echo e( $citta['nome'] ); ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="glp-hero__main<?php echo empty( $servizi ) ? ' glp-hero__main--solo' : ''; ?>">
			<div class="glp-hero__content">
				<h1 class="glp-hero__title glp-reveal"><?php echo titolo_hero( $citta, $pagina ); // HTML controllato. ?></h1>

				<?php
				$intro = vuoto( $pagina['intro'] ) ? $citta['intro'] : $pagina['intro'];
				if ( ! vuoto( $intro ) ) :
					?>
					<p class="glp-hero__text glp-reveal"><?php echo e( $intro ); ?></p>
				<?php endif; ?>

				<div class="glp-hero__cta glp-reveal">
					<?php if ( ! vuoto( $cta_url ) ) : ?>
						<a class="glp-btn glp-btn--tel" href="<?php echo e( $cta_url ); ?>"><?php echo e( $cta_testo ); ?></a>
					<?php endif; ?>
					<?php if ( ! vuoto( $whatsapp ) ) : ?>
						<a class="glp-btn glp-btn--ghost" rel="nofollow noopener" target="_blank"
							href="<?php echo e( url_whatsapp( $whatsapp, 'Salve, vi scrivo da ' . $citta['nome'] . ': avrei bisogno di ' . mb_strtolower( $pagina['titolo'] ) . '.' ) ); ?>">
							Scrivici su WhatsApp
						</a>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! empty( $servizi ) ) : ?>
				<nav class="glp-hero__index glp-reveal" aria-label="Servizi a <?php echo e( $citta['nome'] ); ?>">
					<div class="glp-hero__index-title" data-count="<?php echo e( sprintf( '%02d', count( $servizi ) ) ); ?>">
						Servizi a <?php echo e( $citta['nome'] ); ?>
					</div>
					<?php foreach ( $servizi as $i => $s ) : ?>
						<a class="glp-hero__service" href="<?php echo e_url( $s['url'] ); ?>">
							<span class="glp-hero__service-number"><?php echo e( sprintf( '%02d', $i + 1 ) ); ?></span>
							<span class="glp-hero__service-name"><?php echo e( $s['nome'] ); ?></span>
							<span class="glp-hero__service-arrow" aria-hidden="true">→</span>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>

		<div class="glp-hero__bottom glp-reveal">
			<span><?php echo e( trim( $citta['nome'] . ' · ' . $citta['provincia'], ' ·' ) ); ?></span>
			<div class="glp-hero__bottom-line" aria-hidden="true"></div>
			<span><?php echo e( $imp['brand'] ); ?></span>
		</div>
	</div>
	<div class="glp-hero__corner" aria-hidden="true"></div>
</div>

<div class="glp-main__inner">
<div class="glp-sections">
<?php
/**
 * Le sezioni escono nell'ordine deciso nella scheda "Sezioni" della pagina.
 * Ognuna sa dire da sola se ha qualcosa da mostrare: se il contenuto manca
 * restituisce stringa vuota e non lascia un buco.
 */
$contesto = contesto_pagina( $citta, $pagina, $esito_modulo );
echo stampa_sezioni( $pagina, $contesto );
?>
</div><!-- .glp-sections -->
</div><!-- .glp-main__inner -->
</main>

<?php include __DIR__ . '/parti/piede.php'; ?>
