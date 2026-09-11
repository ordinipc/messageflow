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
			<a href="?p=controlla">Controlla una pagina</a>
			<a href="?p=impostazioni">Impostazioni</a>
			<a href="verifica.php">Requisiti</a>
			<a class="bottone" href="?p=nuovo">Nuova analisi</a>
		</nav>
	</div>
</header>

<main class="contenitore">
	<?php echo $contenuto; ?>
</main>

<footer class="piede">
	<div class="contenitore">
		Analisi SEO e GEO di un sito WordPress a partire dall esportazione WXR ·
		database <?php echo e( $GLOBALS['cfg']['database']['driver'] ?? 'sqlite' ); ?>
	</div>
</footer>
</body>
</html>
