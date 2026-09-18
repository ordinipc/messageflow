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

		<?php
		// Su quale cliente si sta lavorando, sempre sotto agli occhi: con
		// piu clienti aperti in schede diverse, non saperlo vuol dire
		// mandare le correzioni sul sito sbagliato.
		$clienti_tutti = \SeoGeo\Siti::elenco();
		?>
		<?php if ( count( $clienti_tutti ) > 1 ) : ?>
			<form method="get" action="" class="scelta-sito" style="margin:0">
				<input type="hidden" name="p" value="<?php echo e( (string) ( $_GET['p'] ?? 'home' ) ); ?>">
				<select name="sito" onchange="this.form.submit()"
					style="padding:6px;border:1px solid var(--linea);border-radius:4px;max-width:220px">
					<?php foreach ( $clienti_tutti as $uno ) : ?>
						<option value="<?php echo e( $uno['slug'] ); ?>" <?php echo ( $uno['slug'] === ( $GLOBALS['sito_slug'] ?? '' ) ) ? 'selected' : ''; ?>>
							<?php echo e( $uno['nome'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<noscript><button type="submit">vai</button></noscript>
			</form>
		<?php endif; ?>
		<nav>
			<a href="?p=clienti">Clienti</a>
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
