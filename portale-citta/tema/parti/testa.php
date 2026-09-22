<?php
/**
 * <head> condiviso.
 * Variabili attese: $titolo, $descrizione, $canonico, $immagine,
 * $indicizza, $schemi (array di nodi JSON-LD), $css_extra, $citta (facoltativa).
 */

defined( 'PC_AVVIO' ) || exit;

$imp          = impostazioni();
$titolo       = isset( $titolo ) ? $titolo : $imp['brand'];
$descrizione  = isset( $descrizione ) ? $descrizione : '';
$canonico     = isset( $canonico ) ? $canonico : base_url() . '/';
$immagine     = isset( $immagine ) ? $immagine : url_media( $imp['logo'] );
$indicizza    = isset( $indicizza ) ? (bool) $indicizza : true;
$schemi       = isset( $schemi ) ? (array) $schemi : array();
$css_extra    = isset( $css_extra ) ? $css_extra : '';
$js_extra     = isset( $js_extra ) ? $js_extra : '';
?>
<!DOCTYPE html>
<html lang="<?php echo e( str_replace( '_', '-', $imp['lingua'] ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?php echo e( $titolo ); ?></title>
<?php if ( ! vuoto( $descrizione ) ) : ?>
<meta name="description" content="<?php echo e( $descrizione ); ?>">
<?php endif; ?>
<link rel="canonical" href="<?php echo e( $canonico ); ?>">
<meta name="robots" content="<?php echo $indicizza ? 'index, follow, max-image-preview:large, max-snippet:-1' : 'noindex, nofollow'; ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?php echo e( $imp['brand'] ); ?>">
<meta property="og:title" content="<?php echo e( $titolo ); ?>">
<meta property="og:description" content="<?php echo e( $descrizione ); ?>">
<meta property="og:url" content="<?php echo e( $canonico ); ?>">
<meta property="og:locale" content="<?php echo e( str_replace( '-', '_', $imp['lingua'] ) ); ?>">
<?php if ( ! vuoto( $immagine ) ) : ?>
<meta property="og:image" content="<?php echo e( $immagine ); ?>">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>

<?php if ( ! empty( $citta ) ) : ?>
	<?php if ( ! vuoto( $citta['lat'] ) && ! vuoto( $citta['lng'] ) ) : ?>
<meta name="geo.position" content="<?php echo e( $citta['lat'] . ';' . $citta['lng'] ); ?>">
<meta name="ICBM" content="<?php echo e( $citta['lat'] . ', ' . $citta['lng'] ); ?>">
	<?php endif; ?>
<meta name="geo.placename" content="<?php echo e( $citta['nome'] ); ?>">
	<?php if ( ! vuoto( $citta['provincia'] ) ) : ?>
<meta name="geo.region" content="<?php echo e( $imp['nazione'] . '-' . $citta['provincia'] ); ?>">
	<?php endif; ?>
<?php endif; ?>

<?php if ( ! vuoto( $imp['favicon'] ) ) : ?>
<link rel="icon" href="<?php echo e( url_media( $imp['favicon'] ) ); ?>">
<?php endif; ?>

<link rel="stylesheet" href="<?php echo e( base_url() ); ?>/tema/style.css?v=<?php echo e( versione_asset( 'style.css' ) ); ?>">
<style><?php echo css_variabili(); ?></style>
<?php if ( ! vuoto( $imp['css_globale'] ) ) : ?>
<style><?php echo $imp['css_globale']; // CSS inserito dall'amministratore. ?></style>
<?php endif; ?>
<?php if ( ! vuoto( $css_extra ) ) : ?>
<style><?php echo $css_extra; // CSS della città o della pagina. ?></style>
<?php endif; ?>

<!-- Il contenuto si nasconde solo se il JavaScript è attivo: senza, resta visibile. -->
<script>
document.documentElement.classList.add('glp-js');
setTimeout(function(){document.documentElement.classList.add('glp-reveal-fallback');},2500);
</script>

<?php
foreach ( $schemi as $nodo ) {
	if ( ! empty( $nodo ) ) {
		echo json_ld( $nodo ) . "\n";
	}
}
?>
<?php if ( ! vuoto( $imp['ga_id'] ) ) : ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo e( $imp['ga_id'] ); ?>"></script>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('js',new Date());gtag('config','<?php echo e( $imp['ga_id'] ); ?>');
</script>
<?php endif; ?>
</head>
<body class="<?php echo e( classi_corpo( isset( $classi_pagina ) ? $classi_pagina : '' ) ); ?>">
