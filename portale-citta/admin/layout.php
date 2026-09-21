<?php
/** Struttura comune dell'amministrazione. */
defined( 'PC_AVVIO' ) || exit;
$imp  = impostazioni();
$msg  = avviso();
$voci = array(
	'pannello'     => array( 'Pannello', '▦' ),
	'citta'        => array( 'Città', '◉' ),
	'pagine'       => array( 'Pagine', '▤' ),
	'menu'         => array( 'Menu', '≡' ),
	'servizi'      => array( 'Tipi di servizio', '⚙' ),
	'articoli'     => array( 'Articoli', '✎' ),
	'media'        => array( 'Immagini', '▣' ),
	'seo'          => array( 'SEO e sitemap', '↗' ),
	'impostazioni' => array( 'Impostazioni', '☰' ),
	'utenti'       => array( 'Utenti', '☺' ),
);
// Le due schermate di sistema non si mostrano a chi non può aprirle.
if ( ! puo_amministrare() ) {
	unset( $voci['impostazioni'], $voci['utenti'] );
}
$io = utente_corrente();
$attiva = $schermata;
if ( 'citta-modifica' === $attiva ) { $attiva = 'citta'; }
if ( 'pagina-modifica' === $attiva ) { $attiva = 'pagine'; }
if ( 'articolo-modifica' === $attiva || 'importa' === $attiva ) { $attiva = 'articoli'; }
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo e( $titolo_schermata ); ?> — <?php echo e( $imp['brand'] ); ?></title>
<link rel="stylesheet" href="admin/admin.css?v=<?php echo e( is_file( __DIR__ . '/admin.css' ) ? filemtime( __DIR__ . '/admin.css' ) : PC_VERSIONE ); ?>">
</head>
<body class="pc-admin" data-token="<?php echo e( token() ); ?>">

<header class="pc-top">
	<a class="pc-top__logo" href="admin.php"><?php echo e( $imp['brand'] ); ?><span>portale città</span></a>
	<div class="pc-top__azioni">
		<?php if ( $io ) : ?>
			<span class="pc-top__chi"><?php echo e( vuoto( $io['etichetta'] ) ? $io['nome'] : $io['etichetta'] ); ?></span>
		<?php endif; ?>
		<a class="pc-btn pc-btn--ghost" href="<?php echo e( base_url() ); ?>/" target="_blank" rel="noopener">Vedi il sito ↗</a>
		<a class="pc-btn pc-btn--ghost" href="admin.php?p=esci">Esci</a>
	</div>
</header>

<div class="pc-corpo">
	<nav class="pc-menu">
		<?php foreach ( $voci as $chiave => $v ) : ?>
			<a class="pc-menu__voce<?php echo $attiva === $chiave ? ' is-attiva' : ''; ?>" href="admin.php?p=<?php echo e( $chiave ); ?>">
				<span class="pc-menu__icona" aria-hidden="true"><?php echo $v[1]; ?></span>
				<?php echo e( $v[0] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<main class="pc-contenuto">
		<?php if ( $msg ) : ?>
			<div class="pc-avviso pc-avviso--<?php echo e( 'ok' === $msg['tipo'] ? 'ok' : 'errore' ); ?>"><?php echo e( $msg['testo'] ); ?></div>
		<?php endif; ?>
		<?php echo $contenuto; // Generato dalle schermate. ?>
	</main>
</div>

<script src="admin/admin.js?v=<?php echo e( is_file( __DIR__ . '/admin.js' ) ? filemtime( __DIR__ . '/admin.js' ) : PC_VERSIONE ); ?>"></script>
</body>
</html>
