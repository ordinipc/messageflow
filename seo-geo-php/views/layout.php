<?php
/**
 * Layout comune a tutte le pagine.
 *
 * @package SeoGeoAudit
 * @var string $titolo    Titolo della pagina.
 * @var string $contenuto HTML della vista.
 */

?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo e( $titolo ); ?> — SEO &amp; GEO Audit</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="testa">
	<div class="contenitore">
		<a class="marchio" href="?p=home">SEO &amp; GEO <span>Audit</span></a>
		<nav>
			<a href="?p=home">Audit archiviati</a>
			<a href="?p=prestazioni">Rendimento</a>
			<a href="?p=indice">Che cosa dice Google</a>
			<a href="?p=controlla">Controlla una pagina</a>
			<a href="?p=impostazioni">Impostazioni</a>
			<a href="?p=diagnostica">Diagnostica</a>
			<a href="verifica.php">Requisiti</a>
			<a class="bottone" href="?p=nuovo">Nuova analisi</a>
		</nav>
	</div>
</header>

<main class="contenitore">
	<?php if ( ! empty( $piu_recente['id'] ) ) : ?>
		<p class="avviso">
			<strong>Stai guardando un'analisi vecchia.</strong>
			Del <?php echo e( substr( (string) ( $audit['creato_il'] ?? '' ), 0, 16 ) ); ?>; ce n'è una più
			recente, del <?php echo e( substr( (string) $piu_recente['creato_il'], 0, 16 ) ); ?>.
			Quello che fai qui lavora sui dati di allora.
			<a href="?p=audit&amp;id=<?php echo (int) $piu_recente['id']; ?>">Vai all'analisi più recente</a>.
		</p>
	<?php endif; ?>
	<?php echo $contenuto; ?>
</main>

<footer class="piede">
	<div class="contenitore">
		Analisi SEO e GEO di un sito WordPress a partire dall esportazione WXR ·
		database <?php echo e( $GLOBALS['cfg']['database']['driver'] ?? 'sqlite' ); ?> ·
		gestionale <strong>v<?php echo e( \SeoGeo\Versione::NUMERO ); ?></strong>
	</div>
</footer>
</body>
</html>
