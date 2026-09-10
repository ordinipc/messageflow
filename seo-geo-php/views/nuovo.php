<?php
/**
 * Caricamento di un nuovo export.
 *
 * @package SeoGeoAudit
 * @var string|null $errore Messaggio di errore.
 */

?>
<section class="intestazione">
	<h1>Nuova analisi</h1>
	<p class="guida">Carica il file XML prodotto da WordPress in <em>Strumenti → Esporta → Tutti i contenuti</em>. L analisi esegue 65 controlli, classifica ogni articolo e genera i file di correzione.</p>
</section>

<?php if ( ! empty( $errore ) ) : ?>
	<p class="avviso grave"><?php echo e( $errore ); ?></p>
<?php endif; ?>

<form class="scheda" method="post" action="?p=analizza" enctype="multipart/form-data">
	<input type="hidden" name="token" value="<?php echo e( token() ); ?>">

	<label for="export">File di esportazione WordPress (.xml)</label>
	<input id="export" type="file" name="export" accept=".xml,text/xml" required>

	<p class="nota">
		Limite di caricamento del server: <?php echo e( ini_get( 'upload_max_filesize' ) ); ?>
		(post_max_size <?php echo e( ini_get( 'post_max_size' ) ); ?>).
		Un export con qualche centinaio di articoli pesa in genere fra 5 e 20 MB.
	</p>

	<button class="bottone" type="submit">Analizza</button>
</form>

<section class="scheda">
	<h2>Cosa viene analizzato</h2>
	<ul class="elenco-due">
		<li><strong>Tecnico</strong> — plugin SEO in conflitto, robots, canonical, pagine di servizio indicizzate</li>
		<li><strong>On-page</strong> — lunghezza di title e description, keyword, gerarchia dei titoli, slug</li>
		<li><strong>Contenuti</strong> — thin content, quasi-duplicati, freschezza, leggibilità Gulpease</li>
		<li><strong>Link</strong> — pagine orfane, link in uscita, anchor, dispersione di autorità</li>
		<li><strong>Immagini</strong> — alt, peso, formati, immagine in evidenza</li>
		<li><strong>Dati strutturati</strong> — JSON-LD, FAQ, breadcrumb, Service, Open Graph</li>
		<li><strong>SEO locale</strong> — NAP, LocalBusiness, copertura dei comuni, recensioni</li>
		<li><strong>GEO</strong> — llms.txt, crawler AI, risposta in apertura, dati citabili, entità</li>
		<li><strong>E-E-A-T</strong> — autore, casi studio, tratti di contenuto prodotto in serie</li>
		<li><strong>Tassonomie</strong> — categorie sovraccariche, tag, coerenza tematica</li>
	</ul>
</section>
