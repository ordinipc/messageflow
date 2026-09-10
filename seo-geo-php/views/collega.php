<?php
/**
 * Applicazione delle correzioni sul sito WordPress.
 *
 * @package SeoGeoAudit
 * @var array  $audit        Riga audit.
 * @var bool   $pronto       Collegamento configurato.
 * @var array  $stato        Risposta del sito.
 * @var string $errore_stato Errore della prova di collegamento.
 * @var array  $conteggi     Quantità per ogni operazione.
 */

/**
 * Pulsante di un operazione.
 *
 * @param int    $id         Audit.
 * @param string $azione     Azione.
 * @param string $etichetta  Testo del pulsante.
 * @param string $conferma   Domanda di conferma.
 * @param string $classe     Classe del pulsante.
 * @return void
 */
function azione( $id, $azione, $etichetta, $conferma = '', $classe = 'bottone' ) {
	?>
	<form method="post" action="?p=applica" <?php echo $conferma ? 'onsubmit="return confirm(' . "'" . e( $conferma ) . "'" . ')"' : ''; ?>>
		<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
		<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<input type="hidden" name="azione" value="<?php echo e( $azione ); ?>">
		<button class="<?php echo e( $classe ); ?>" type="submit"><?php echo e( $etichetta ); ?></button>
	</form>
	<?php
}

?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>"><?php echo e( $audit['sito_nome'] ); ?></a> › Applica sul sito</p>
	<h1>Applica le correzioni sul sito</h1>
	<p class="guida">Il gestionale parla con il plugin installato su WordPress e scrive direttamente lì: niente copia e incolla. Le meta si possono annullare, le bozze non toccano mai i contenuti pubblicati.</p>
</section>

<?php if ( $esito ) : ?><p class="avviso ok-bg"><?php echo e( $esito ); ?></p><?php endif; ?>
<?php if ( $errore ) : ?><p class="avviso grave"><?php echo e( $errore ); ?></p><?php endif; ?>

<?php if ( ! $pronto ) : ?>
	<section class="scheda">
		<h2>Collegamento da configurare</h2>
		<ol>
			<li>Sul sito WordPress apri <strong>SEO &amp; GEO</strong> nel menu di sinistra.</li>
			<li>Nel riquadro <em>Collegamento con il gestionale</em> copia indirizzo e token.</li>
			<li>Incollali qui, in <a href="?p=impostazioni">Impostazioni</a>.</li>
		</ol>
		<p class="nota">Se non vedi il menu SEO &amp; GEO, il plugin non è attivo: installa lo zip generato dalla scheda dell'audit.</p>
	</section>
<?php elseif ( $errore_stato ) : ?>
	<section class="scheda">
		<h2>Il sito non risponde</h2>
		<p class="avviso grave"><?php echo e( $errore_stato ); ?></p>
		<p>Controlla che il plugin sia attivo, che i permalink non siano impostati su "Semplice" e che l'indirizzo in Impostazioni sia esatto (con <code>https://</code>).</p>
	</section>
<?php else : ?>
	<div class="riquadri">
		<div class="riquadro">
			<span class="etichetta">Sito collegato</span>
			<strong style="font-size:18px"><?php echo e( $stato['sito'] ); ?></strong>
			<span class="sotto"><?php echo e( $stato['url'] ); ?></span>
		</div>
		<div class="riquadro">
			<span class="etichetta">WordPress</span>
			<strong style="font-size:18px"><?php echo e( $stato['wordpress'] ); ?></strong>
			<span class="sotto">plugin <?php echo e( $stato['plugin'] ); ?></span>
		</div>
		<div class="riquadro">
			<span class="etichetta">Contenuti sul sito</span>
			<strong><?php echo num( $stato['articoli'] ); ?></strong>
			<span class="sotto">articoli · <?php echo num( $stato['pagine'] ); ?> pagine</span>
		</div>
		<div class="riquadro">
			<span class="etichetta">Plugin SEO</span>
			<strong style="font-size:18px"><?php echo $stato['rank_math'] ? 'Rank Math' : 'nessuno'; ?></strong>
			<span class="sotto"><?php echo $stato['yoast'] ? '⚠ anche Yoast attivo' : 'nessun conflitto'; ?></span>
		</div>
	</div>

	<section class="scheda">
		<h2>Meta ottimizzate</h2>
		<p class="guida">Scrive title, meta description, focus keyword ed estratto sui campi di Rank Math per <?php echo num( $conteggi['meta'] ); ?> contenuti. Prima di scrivere, il plugin mette da parte i valori attuali: l'operazione si può annullare.</p>
		<div class="azioni">
			<?php azione( $audit['id'], 'meta_anteprima', 'Anteprima (non scrive nulla)', '', 'bottone chiaro' ); ?>
			<?php azione( $audit['id'], 'meta', 'Applica le meta', 'Applicare le meta ottimizzate su ' . $conteggi['meta'] . ' contenuti? I valori attuali verranno conservati per poterli ripristinare.' ); ?>
			<?php azione( $audit['id'], 'annulla', 'Annulla e ripristina', 'Ripristinare le meta precedenti su tutti i contenuti?', 'bottone chiaro' ); ?>
		</div>
	</section>

	<section class="scheda">
		<h2>Bozze riscritte</h2>
		<p class="guida">
			<?php if ( $conteggi['bozze'] ) : ?>
				Crea sul sito <?php echo num( min( 25, $conteggi['bozze'] ) ); ?> articoli in stato <strong>Bozza</strong>, collegati agli originali. Nessun contenuto pubblicato viene modificato: confronti, correggi i segnaposto e pubblichi tu.
			<?php else : ?>
				Nessuna bozza ancora generata. Vai a <a href="?p=bozze&amp;id=<?php echo (int) $audit['id']; ?>">Riscrittura assistita</a>.
			<?php endif; ?>
		</p>
		<?php if ( $conteggi['bozze'] ) : ?>
			<div class="azioni"><?php azione( $audit['id'], 'bozze', 'Invia le bozze al sito' ); ?></div>
		<?php endif; ?>
	</section>

	<section class="scheda">
		<h2>Redirect 301</h2>
		<p class="guida">Attiva <?php echo num( $conteggi['redirect'] ); ?> redirect sul sito: slug accorciati e articoli da eliminare o accorpare. Vanno impostati <strong>prima</strong> di cestinare qualsiasi contenuto.</p>
		<div class="azioni"><?php azione( $audit['id'], 'redirect', 'Attiva i redirect' ); ?></div>
	</section>

	<section class="scheda">
		<h2>Categorie</h2>
		<p class="guida">Riassegna le categorie ai <?php echo num( $conteggi['categorie'] ); ?> articoli che l'audit segnala come classificati fuori tema.</p>
		<div class="azioni"><?php azione( $audit['id'], 'categorie', 'Ricategorizza', 'Riassegnare le categorie a ' . $conteggi['categorie'] . ' articoli?' ); ?></div>
	</section>
<?php endif; ?>
