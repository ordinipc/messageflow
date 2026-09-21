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
	schema_faq( $pagina['faq'] ),
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
<div class="glp-main__inner">

<?php if ( 'pubblicata' !== $pagina['stato'] || 'pubblicata' !== $citta['stato'] ) : ?>
	<p style="background:#b00;color:#fff;padding:10px 14px;border-radius:6px;font:600 13px/1.4 system-ui,sans-serif;margin:0 0 16px">
		Anteprima: questa pagina è in bozza e non è visibile al pubblico né a Google.
	</p>
<?php endif; ?>

<!-- INTESTAZIONE -->
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

<div class="glp-sections">
<?php
/* --- Testo principale --------------------------------------------------- */
if ( ! vuoto( $pagina['corpo'] ) ) {
	echo sezione_apri( 'approfondimento', seo_h1( $citta, $pagina ) . ': cosa sapere', 'Approfondimento' );
	echo paragrafi( $pagina['corpo'] );
	echo '</section>';
}

/* --- HTML libero della pagina ------------------------------------------- */
if ( ! vuoto( $pagina['html'] ) ) {
	echo '<section class="glp-section glp-section--libero glp-reveal">' . $pagina['html'] . '</section>';
}

/* --- Elenco dei servizi: è il corpo della pagina, non una coda --------- */
if ( 'servizi' === $pagina['tipo'] ) {
	echo sezione_elenco_servizi( $citta, $servizi );
}

/* --- Blog: idem, l'elenco degli articoli è il contenuto --------------- */
if ( 'blog' === $pagina['tipo'] ) {
	echo sezione_blog( $citta, $pagina );
}

/* --- Sezioni a riquadri o a elenco (tema/sezioni.php) -------------------- */
if ( pagina_mostra( $pagina, 'inclusi' ) )    { echo sezione_inclusi( $pagina ); }
if ( pagina_mostra( $pagina, 'perche' ) )     { echo sezione_perche( $citta ); }
if ( pagina_mostra( $pagina, 'processo' ) )   { echo sezione_processo( $pagina ); }

/* --- Prezzi -------------------------------------------------------------- */
if ( pagina_mostra( $pagina, 'prezzi' ) && ! vuoto( $pagina['prezzo_da'] ) ) {
	echo sezione_apri( 'prezzi', 'Quanto costa', 'Prezzi' );
	$valuta = '€';
	$testo  = vuoto( $pagina['prezzo_a'] )
		? 'a partire da ' . $pagina['prezzo_da'] . ' ' . $valuta
		: 'da ' . $pagina['prezzo_da'] . ' ' . $valuta . ' a ' . $pagina['prezzo_a'] . ' ' . $valuta;
	echo '<p class="glp-price">' . e( $testo ) . '</p>';
	if ( ! vuoto( $pagina['prezzo_note'] ) ) {
		echo paragrafi( $pagina['prezzo_note'] );
	}
	echo '</section>';
}

if ( pagina_mostra( $pagina, 'zone' ) )       { echo sezione_zone( $citta ); }
if ( pagina_mostra( $pagina, 'recensioni' ) ) { echo sezione_recensioni( $citta ); }
if ( pagina_mostra( $pagina, 'team' ) )       { echo sezione_team( $citta ); }

/* --- Dove siamo ---------------------------------------------------------- */
if ( pagina_mostra( $pagina, 'dove' ) && ( ! vuoto( $citta['indirizzo'] ) || ! vuoto( $citta['mappa'] ) || ! vuoto( $citta['raggiungerci'] ) ) ) {
	echo sezione_apri( 'dove', 'Dove siamo e come raggiungerci', 'Sede' );
	if ( ! vuoto( $citta['indirizzo'] ) ) {
		$completo = $citta['indirizzo'] . ', ' . trim( $citta['cap'] . ' ' . $citta['nome'] );
		if ( ! vuoto( $citta['provincia'] ) ) {
			$completo .= ' (' . $citta['provincia'] . ')';
		}
		echo '<p class="glp-address">' . e( $completo ) . '</p>';
	}
	if ( ! vuoto( $citta['raggiungerci'] ) ) {
		echo paragrafi( $citta['raggiungerci'] );
	}
	if ( ! vuoto( $citta['mappa'] ) ) {
		echo '<div class="glp-map"><iframe src="' . e_url( $citta['mappa'] ) . '" loading="lazy" title="Mappa della sede a ' . e( $citta['nome'] ) . '" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>';
	}
	echo '</section>';
}


if ( pagina_mostra( $pagina, 'orari' ) )      { echo sezione_orari( $citta ); }

/* --- FAQ ----------------------------------------------------------------- */
$faq = pagina_mostra( $pagina, 'faq' ) ? (array) $pagina['faq'] : array();
if ( ! empty( $faq ) ) {
	echo sezione_apri( 'faq', 'Domande frequenti', 'FAQ' );
	echo '<div class="glp-faq">';
	$i = 0;
	foreach ( $faq as $riga ) {
		$d = isset( $riga['domanda'] ) ? $riga['domanda'] : '';
		if ( vuoto( $d ) ) {
			continue;
		}
		echo '<details class="glp-faq__item"' . ( 0 === $i ? ' open' : '' ) . '>';
		echo '<summary class="glp-faq__q">' . e( $d ) . '</summary>';
		echo '<div class="glp-faq__a">' . paragrafi( isset( $riga['risposta'] ) ? $riga['risposta'] : '' ) . '</div>';
		echo '</details>';
		$i++;
	}
	echo '</div></section>';
}

/* --- Recapiti e modulo di contatto ---------------------------------------- */
if ( pagina_mostra( $pagina, 'recapiti' ) ) {
	echo sezione_recapiti( $citta );
}
if ( pagina_mostra( $pagina, 'modulo' ) ) {
	echo sezione_modulo( $citta, $pagina, $servizi, $esito_modulo );
}

/* --- Chiamata all'azione -------------------------------------------------- */
if ( pagina_mostra( $pagina, 'cta' ) && ( ! vuoto( $telefono ) || ! vuoto( $whatsapp ) ) ) {
	echo sezione_apri( 'cta', 'Richiedi un intervento a ' . $citta['nome'], 'Contatti' );
	echo '<div class="glp-cta">';
	if ( ! vuoto( $cta_url ) ) {
		echo '<a class="glp-btn" href="' . e( $cta_url ) . '">' . e( $cta_testo ) . '</a>';
	}
	if ( ! vuoto( $whatsapp ) ) {
		echo '<a class="glp-btn glp-btn--ghost" rel="nofollow noopener" target="_blank" href="' . e( url_whatsapp( $whatsapp ) ) . '">Scrivici su WhatsApp</a>';
	}
	echo '</div></section>';
}

// Su una pagina che già elenca i servizi non si ripete l'elenco in fondo.
if ( 'servizi' !== $pagina['tipo'] && pagina_mostra( $pagina, 'servizi' ) ) {
	echo sezione_servizi( $citta, $servizi, url_pagina( $citta, $pagina ) );
}
if ( pagina_mostra( $pagina, 'correlate' ) ) {
	echo sezione_correlate( $altre );
}
?>
</div><!-- .glp-sections -->
</div><!-- .glp-main__inner -->
</main>

<?php include __DIR__ . '/parti/piede.php'; ?>
