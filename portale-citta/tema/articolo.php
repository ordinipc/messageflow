<?php
/**
 * Un articolo del blog.
 * Variabili attese da index.php: $citta, $pagina (la pagina blog), $articolo.
 */

defined( 'PC_AVVIO' ) || exit;
require_once __DIR__ . '/funzioni-tema.php';
require_once __DIR__ . '/sezioni.php';

$imp      = impostazioni();
$telefono = contatto( $citta, 'telefono' );
$whatsapp = contatto( $citta, 'whatsapp' );
$menu     = menu_citta( $citta, $pagina['id'] );
$altre    = altre_citta( $citta['id'] );

$titolo      = vuoto( $articolo['seo_titolo'] ) ? $articolo['titolo'] . ' | ' . $imp['brand'] : $articolo['seo_titolo'];
$descrizione = vuoto( $articolo['seo_desc'] ) ? $articolo['estratto'] : $articolo['seo_desc'];
$canonico    = url_articolo( $citta, $articolo, $pagina );
$categoria   = categoria_per_id( $articolo['categoria'] );
$immagine    = vuoto( $articolo['immagine'] ) ? url_media( $imp['logo'] ) : url_media( $articolo['immagine'] );
$indicizza   = '1' === (string) $imp['indicizza'] && 'pubblicato' === $articolo['stato'] && 'pubblicata' === $citta['stato'];
$css_extra   = $citta['css'];
$js_extra    = $citta['js'];

$schemi = array(
	schema_attivita( $citta ),
	array_merge(
		array(
			'@context'         => 'https://schema.org',
			'@type'            => 'BlogPosting',
			'headline'         => mb_substr( $articolo['titolo'], 0, 110 ),
			'description'      => $descrizione,
			'url'              => $canonico,
			'datePublished'    => $articolo['data'],
			'dateModified'     => vuoto( $articolo['aggiornata'] ) ? $articolo['data'] : $articolo['aggiornata'],
			'inLanguage'       => $imp['lingua'],
			'author'           => array( '@type' => 'Organization', 'name' => $imp['brand'] ),
			'publisher'        => array( '@id' => url_citta( $citta ) . '#attivita' ),
			'mainEntityOfPage' => $canonico,
			'about'            => array( '@type' => 'City', 'name' => $citta['nome'] ),
		),
		// Categoria e tag solo se ci sono: una chiave vuota nei dati
		// strutturati è peggio della chiave che manca.
		$categoria ? array( 'articleSection' => $categoria['nome'] ) : array(),
		vuoto( $articolo['tag'] ) ? array() : array( 'keywords' => implode( ', ', righe( (string) $articolo['tag'] ) ) )
	),
	array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		// array_merge e non «+»: con le chiavi numeriche «+» tiene quelle
		// di sinistra e butta via l'aggiunta, senza dire niente.
		'itemListElement' => array_merge(
			array(
				array( '@type' => 'ListItem', 'position' => 1, 'name' => $imp['brand'], 'item' => base_url() . '/' ),
				array( '@type' => 'ListItem', 'position' => 2, 'name' => $citta['nome'], 'item' => url_citta( $citta ) ),
				array( '@type' => 'ListItem', 'position' => 3, 'name' => $pagina['titolo'], 'item' => url_pagina( $citta, $pagina ) ),
			),
			// La categoria è un anello vero della catena: se c'è, ci va.
			$categoria
				? array( array( '@type' => 'ListItem', 'position' => 4, 'name' => $categoria['nome'], 'item' => url_categoria( $citta, $categoria, $pagina ) ) )
				: array(),
			array( array( '@type' => 'ListItem', 'position' => $categoria ? 5 : 4, 'name' => $articolo['titolo'], 'item' => $canonico ) )
		),
	),
);

include __DIR__ . '/parti/testa.php';
include __DIR__ . '/parti/barra.php';
?>

<main class="glp-main">
<div class="glp-main__inner">

<?php if ( 'pubblicato' !== $articolo['stato'] ) : ?>
	<p style="background:#b00;color:#fff;padding:10px 14px;border-radius:6px;font:600 13px/1.4 system-ui,sans-serif;margin:0 0 16px">
		Anteprima: questo articolo è in bozza e non è visibile al pubblico né a Google.
	</p>
<?php endif; ?>

<article class="glp-sections glp-articolo">

	<p class="glp-breadcrumbs glp-reveal">
		<a href="<?php echo e( url_citta( $citta ) ); ?>"><?php echo e( $citta['nome'] ); ?></a>
		<span aria-hidden="true">›</span>
		<a href="<?php echo e( url_pagina( $citta, $pagina ) ); ?>"><?php echo e( $pagina['titolo'] ); ?></a>
		<?php if ( $categoria ) : ?>
			<span aria-hidden="true">›</span>
			<a href="<?php echo e( url_categoria( $citta, $categoria, $pagina ) ); ?>"><?php echo e( $categoria['nome'] ); ?></a>
		<?php endif; ?>
	</p>

	<header class="glp-section glp-reveal">
		<p class="glp-section__label">
			<?php echo e( $citta['nome'] ); ?>
			<?php if ( ! vuoto( $articolo['data'] ) ) : ?>
				· <time datetime="<?php echo e( $articolo['data'] ); ?>"><?php echo e( data_italiana( $articolo['data'] ) ); ?></time>
			<?php endif; ?>
		</p>
		<h1 class="glp-section__title"><?php echo e( $articolo['titolo'] ); ?></h1>
		<?php if ( ! vuoto( $articolo['immagine'] ) ) : ?>
			<p class="glp-articolo__foto"><img src="<?php echo e( url_media( $articolo['immagine'] ) ); ?>" alt="<?php echo e( $articolo['titolo'] ); ?>" loading="lazy"></p>
		<?php endif; ?>
	</header>

	<div class="glp-section glp-articolo__corpo glp-reveal">
		<?php echo $articolo['corpo']; // Ripulito in fase di importazione. ?>
		<?php echo blocco_tag( $articolo['tag'] ); ?>
		<?php if ( $categoria ) : ?>
			<p class="glp-articolo__categoria">
				Categoria:
				<a href="<?php echo e( url_categoria( $citta, $categoria, $pagina ) ); ?>"><?php echo e( $categoria['nome'] ); ?></a>
			</p>
		<?php endif; ?>
	</div>

	<?php
	if ( ! vuoto( $telefono ) || ! vuoto( $whatsapp ) ) {
		echo sezione_apri( 'cta', 'Ti serve un intervento a ' . $citta['nome'] . '?', 'Contatti' );
		echo '<div class="glp-cta">';
		if ( ! vuoto( $telefono ) ) {
			echo '<a class="glp-btn glp-btn--tel" href="tel:' . e( tel( $telefono ) ) . '">Chiama ' . e( $telefono ) . '</a>';
		}
		if ( ! vuoto( $whatsapp ) ) {
			echo '<a class="glp-btn glp-btn--ghost" rel="nofollow noopener" target="_blank" href="'
				. e( url_whatsapp( $whatsapp ) ) . '">Scrivici su WhatsApp</a>';
		}
		echo '</div></section>';
	}

	// Altri articoli della stessa città.
	$vicini = array();
	foreach ( articoli_di_citta( $citta['id'], ! connesso(), 7 ) as $a ) {
		if ( $a['id'] === $articolo['id'] ) {
			continue;
		}
		$vicini[] = $a;
	}
	$vicini = array_slice( $vicini, 0, 6 );

	if ( ! empty( $vicini ) ) {
		echo sezione_apri( 'altri-articoli', 'Altri articoli su ' . $citta['nome'], 'Dal blog' );
		echo '<ul class="glp-boxes glp-boxes--larghe">';
		foreach ( $vicini as $i => $a ) {
			echo box_apri( 'link', $i )
				. '<a href="' . e( url_articolo( $citta, $a, $pagina ) ) . '">'
				. '<span class="glp-box__titolo">' . e( $a['titolo'] ) . '</span>';
			if ( ! vuoto( $a['estratto'] ) ) {
				echo '<span class="glp-box__testo">' . e( mb_substr( $a['estratto'], 0, 110 ) ) . '</span>';
			}
			echo '</a></li>';
		}
		echo '</ul></section>';
	}
	?>

</article>
</div>
</main>

<?php include __DIR__ . '/parti/piede.php'; ?>
