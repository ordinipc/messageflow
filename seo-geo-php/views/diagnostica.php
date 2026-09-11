<?php
/**
 * Pacchetto diagnostico da mandare a chi sviluppa.
 *
 * @package SeoGeoAudit
 * @var string[] $esclusi Cosa non viene incluso.
 * @var string[] $meta    Meta incluse.
 * @var bool     $pronto  Collegamento al sito configurato.
 * @var string   $errore  Errore dell ultimo tentativo.
 */

?>
<section class="intestazione">
	<h1>Pacchetto diagnostico</h1>
	<p class="guida">
		Serve a far riprodurre un problema a chi sviluppa, con la forma vera dei tuoi contenuti,
		senza mandare in giro un dump del database. Legge il sito e basta: non modifica niente.
	</p>
</section>

<?php if ( $errore ) : ?>
	<p class="avviso grave"><?php echo e( $errore ); ?></p>
<?php endif; ?>

<section class="scheda">
	<h2>Cosa contiene</h2>
	<ul class="guida">
		<li>Titolo, indirizzo, slug, date, stato e <strong>testo</strong> di articoli e pagine</li>
		<li>Categorie, tag e voci di menu</li>
		<li>
			Le meta SEO:
			<?php foreach ( array_slice( $meta, 0, 6 ) as $i => $chiave ) : ?>
				<?php echo $i ? ', ' : ''; ?><code><?php echo e( $chiave ); ?></code>
			<?php endforeach; ?>
			e poche altre
		</li>
		<li>Gli allegati: indirizzo, testo alternativo e peso — <strong>non</strong> i file</li>
		<li>Versione di WordPress, del plugin, e se c è Rank Math o Yoast</li>
	</ul>

	<h2>Cosa non contiene</h2>
	<ul class="guida">
		<?php foreach ( $esclusi as $voce ) : ?>
			<li><?php echo e( $voce ); ?></li>
		<?php endforeach; ?>
	</ul>

	<p class="nota">
		Il file viene composto al momento e scaricato: sul server non resta niente.
		Il testo degli articoli c è tutto, perché è quello che serve a riprodurre i problemi
		di lunghezza, di formato e di duplicazione — se per te è un problema, dimmelo e ne
		preparo una versione senza.
	</p>

	<?php if ( $pronto ) : ?>
		<form method="post" action="?p=scarica-diagnostica">
			<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
			<button class="bottone" type="submit">Genera e scarica</button>
		</form>
		<p class="nota">Su qualche centinaio di contenuti richiede da mezzo minuto a due minuti.</p>
	<?php else : ?>
		<p class="avviso grave">
			Il collegamento al sito non è configurato: serve per leggere i contenuti attuali.
			Vai in <a href="?p=impostazioni">Impostazioni</a>.
		</p>
	<?php endif; ?>
</section>
