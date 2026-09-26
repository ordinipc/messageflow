<?php
/**
 * Archivio di una categoria, dentro una città.
 * Variabili attese da index.php: $citta, $pagina (la pagina blog), $categoria.
 */

defined( 'PC_AVVIO' ) || exit;
require_once __DIR__ . '/funzioni-tema.php';
require_once __DIR__ . '/sezioni.php';

$imp      = impostazioni();
$telefono = contatto( $citta, 'telefono' );
$whatsapp = contatto( $citta, 'whatsapp' );
$menu     = menu_citta( $citta, $pagina['id'] );
$altre    = altre_citta( $citta['id'] );
$anteprima_admin = connesso();

$per_pagina    = 12;
$totale        = articoli_conta_categoria( $citta['id'], $categoria['id'], ! $anteprima_admin );
$pagine_totali = max( 1, (int) ceil( $totale / $per_pagina ) );
$corrente      = isset( $_GET['pag'] ) ? max( 1, min( $pagine_totali, (int) $_GET['pag'] ) ) : 1;
$articoli      = articoli_di_categoria( $citta['id'], $categoria['id'], ! $anteprima_admin, $per_pagina, ( $corrente - 1 ) * $per_pagina );

// Il nome della città nel titolo: è quello che si cerca davvero, e senza
// di lui due città avrebbero lo stesso titolo su due indirizzi diversi.
$nome_locale = titolo_con_citta( $categoria['nome'], $citta['nome'] );
$titolo      = vuoto( $categoria['seo_titolo'] )
	? $nome_locale . ' | ' . $imp['brand']
	: titolo_con_citta( $categoria['seo_titolo'], $citta['nome'] );
$descrizione = vuoto( $categoria['seo_desc'] )
	? ( vuoto( $categoria['descrizione'] )
		? $totale . ' articoli su ' . $categoria['nome'] . ' a ' . $citta['nome'] . '.'
		: mb_substr( trim( preg_replace( '/\s+/u', ' ', strip_tags( $categoria['descrizione'] ) ) ), 0, 155 ) )
	: $categoria['seo_desc'];
$canonico  = url_categoria( $citta, $categoria, $pagina );
$immagine  = vuoto( $categoria['immagine'] ) ? url_media( $imp['logo'] ) : url_media( $categoria['immagine'] );
// Una pagina 2, 3… non va indicizzata come la prima: è lo stesso archivio.
$indicizza = '1' === (string) $imp['indicizza'] && 'pubblicata' === $citta['stato']
	&& 'pubblicata' === $pagina['stato'] && $totale > 0 && 1 === $corrente;
$css_extra = $citta['css'];
$js_extra  = $citta['js'];

$elementi = array();
foreach ( $articoli as $i => $a ) {
	$elementi[] = array(
		'@type'    => 'ListItem',
		'position' => ( $corrente - 1 ) * $per_pagina + $i + 1,
		'url'      => url_articolo( $citta, $a, $pagina ),
		'name'     => $a['titolo'],
	);
}

$schemi = array(
	schema_attivita( $citta ),
	array(
		'@context'      => 'https://schema.org',
		'@type'         => 'CollectionPage',
		'name'          => $nome_locale,
		'description'   => $descrizione,
		'url'           => $canonico,
		'inLanguage'    => $imp['lingua'],
		'isPartOf'      => array( '@type' => 'Blog', 'name' => $pagina['titolo'], 'url' => url_pagina( $citta, $pagina ) ),
		'dateModified'  => vuoto( $categoria['aggiornata'] ) ? oggi() : $categoria['aggiornata'],
		'publisher'     => array( '@id' => url_citta( $citta ) . '#attivita' ),
		'about'         => array( '@type' => 'City', 'name' => $citta['nome'] ),
		'mainEntity'    => array(
			'@type'           => 'ItemList',
			'numberOfItems'   => count( $elementi ),
			'itemListElement' => $elementi,
		),
	),
	array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => array(
			array( '@type' => 'ListItem', 'position' => 1, 'name' => $imp['brand'], 'item' => base_url() . '/' ),
			array( '@type' => 'ListItem', 'position' => 2, 'name' => $citta['nome'], 'item' => url_citta( $citta ) ),
			array( '@type' => 'ListItem', 'position' => 3, 'name' => $pagina['titolo'], 'item' => url_pagina( $citta, $pagina ) ),
			array( '@type' => 'ListItem', 'position' => 4, 'name' => $categoria['nome'], 'item' => $canonico ),
		),
	),
);

include __DIR__ . '/parti/testa.php';
include __DIR__ . '/parti/barra.php';
?>

<main class="glp-main">
<div class="glp-main__inner">

<?php if ( 0 === $totale && $anteprima_admin ) : ?>
	<p style="background:#b00;color:#fff;padding:10px 14px;border-radius:6px;font:600 13px/1.4 system-ui,sans-serif;margin:0 0 16px">
		Anteprima: in <?php echo e( $citta['nome'] ); ?> questa categoria non ha nessun articolo pubblicato,
		quindi al pubblico questo indirizzo risponde «pagina non trovata».
	</p>
<?php endif; ?>

<div class="glp-sections">

	<p class="glp-breadcrumbs glp-reveal">
		<a href="<?php echo e( url_citta( $citta ) ); ?>"><?php echo e( $citta['nome'] ); ?></a>
		<span aria-hidden="true">›</span>
		<a href="<?php echo e( url_pagina( $citta, $pagina ) ); ?>"><?php echo e( $pagina['titolo'] ); ?></a>
	</p>

	<header class="glp-section glp-reveal">
		<p class="glp-section__label">Categoria · <?php echo e( $citta['nome'] ); ?></p>
		<h1 class="glp-section__title"><?php echo e( $nome_locale ); ?></h1>
		<?php if ( ! vuoto( $categoria['descrizione'] ) ) : ?>
			<div class="glp-prosa"><?php echo blocco_prosa( $categoria['descrizione'] ); ?></div>
		<?php endif; ?>
		<p class="glp-areas__label">
			<?php echo (int) $totale; ?> articol<?php echo 1 === $totale ? 'o' : 'i'; ?>
			<?php echo $pagine_totali > 1 ? ' · pagina ' . (int) $corrente . ' di ' . (int) $pagine_totali : ''; ?>
		</p>
	</header>

	<?php if ( ! empty( $articoli ) ) : ?>
		<section class="glp-section glp-reveal">
			<ul class="glp-boxes glp-boxes--larghe">
			<?php foreach ( $articoli as $i => $a ) : ?>
				<?php echo box_apri( 'link', $i ); ?>
					<a href="<?php echo e( url_articolo( $citta, $a, $pagina ) ); ?>">
						<span class="glp-box__titolo"><?php echo e( $a['titolo'] ); ?></span>
						<?php if ( ! vuoto( $a['estratto'] ) ) : ?>
							<span class="glp-box__testo"><?php echo e( mb_substr( $a['estratto'], 0, 140 ) ); ?></span>
						<?php endif; ?>
						<?php if ( ! vuoto( $a['data'] ) && '1' === (string) impostazione( 'mostra_data', '1' ) ) : ?>
							<span class="glp-box__nota"><?php echo e( data_italiana( $a['data'] ) ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
			</ul>

			<?php if ( $pagine_totali > 1 ) : ?>
				<nav class="glp-paginazione" aria-label="Pagine della categoria">
					<?php if ( $corrente > 1 ) : ?>
						<a class="glp-btn glp-btn--ghost" href="<?php echo e( $canonico . ( $corrente - 1 > 1 ? '?pag=' . ( $corrente - 1 ) : '' ) ); ?>">← Più recenti</a>
					<?php endif; ?>
					<span class="glp-paginazione__stato"><?php echo (int) $corrente; ?> / <?php echo (int) $pagine_totali; ?></span>
					<?php if ( $corrente < $pagine_totali ) : ?>
						<a class="glp-btn glp-btn--ghost" href="<?php echo e( $canonico . '?pag=' . ( $corrente + 1 ) ); ?>">Più vecchi →</a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php
	// Le altre categorie della città: collegamenti interni veri, non un menu.
	$altre_cat = array();
	foreach ( categorie_di_citta( $citta['id'], ! $anteprima_admin ) as $c ) {
		if ( $c['id'] !== $categoria['id'] ) {
			$altre_cat[] = $c;
		}
	}
	if ( ! empty( $altre_cat ) ) {
		echo sezione_apri( 'altre-categorie', 'Altri argomenti a ' . $citta['nome'], 'Categorie' );
		echo '<ul class="glp-tags glp-tags--pagina">';
		foreach ( $altre_cat as $c ) {
			echo '<li><a href="' . e( url_categoria( $citta, $c, $pagina ) ) . '">'
				. e( $c['nome'] ) . ' (' . (int) $c['quanti'] . ')</a></li>';
		}
		echo '</ul></section>';
	}

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
	?>

</div>
</div>
</main>

<?php include __DIR__ . '/parti/piede.php'; ?>
