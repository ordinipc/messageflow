<?php
/**
 * Una singola bozza, pronta da leggere e da incollare in WordPress.
 *
 * @package SeoGeoAudit
 * @var array  $bozza     Riga della bozza con i dati dell articolo originale.
 * @var string $contenuto File HTML ricostruito.
 * @var int    $audit_id  Audit di riferimento.
 */

use SeoGeo\Html;

$faq       = json_decode( (string) $bozza['faq'], true ) ?: array();
$verifiche = json_decode( (string) $bozza['da_verificare'], true ) ?: array();
$accorpata = false !== stripos( (string) $bozza['note'], 'accorpa' );

?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <a href="?p=bozze&amp;id=<?php echo (int) $audit_id; ?>">Riscrittura assistita</a> › Bozza</p>
	<h1><?php echo e( $bozza['titolo'] ); ?></h1>
	<p class="guida">
		<?php if ( $accorpata ) : ?>
			Articolo unificato: fonde più contenuti che competevano per la stessa ricerca.
		<?php else : ?>
			Riscrittura di <a href="<?php echo e( $bozza['url_originale'] ); ?>" target="_blank" rel="noopener"><?php echo e( $bozza['titolo_originale'] ); ?></a>.
		<?php endif; ?>
		Da <?php echo num( $bozza['parole_originali'] ); ?> a <?php echo num( $bozza['parole'] ); ?> parole · generata con <?php echo e( $bozza['modello'] ); ?> il <?php echo e( substr( (string) $bozza['creato_il'], 0, 16 ) ); ?>.
	</p>
</section>

<div class="azioni" style="margin-bottom:18px">
	<a class="bottone" href="?p=bozza&amp;b=<?php echo (int) $bozza['id']; ?>&amp;scarica=1">Scarica il file HTML</a>
	<a class="bottone chiaro" href="<?php echo e( $bozza['url_originale'] ); ?>" target="_blank" rel="noopener">Apri l'articolo attuale</a>
	<a class="bottone chiaro" href="?p=collega&amp;id=<?php echo (int) $audit_id; ?>">Invia le bozze al sito</a>
</div>

<?php if ( $verifiche ) : ?>
	<section class="scheda">
		<h2>Dati da inserire prima di pubblicare</h2>
		<p class="guida">Il modello ha l'istruzione di non inventare mai numeri, prezzi o risultati: dove servivano ha lasciato un segnaposto. Questi sono i punti da completare con dati reali.</p>
		<ul>
			<?php foreach ( $verifiche as $v ) : ?>
				<li><?php echo e( $v ); ?></li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<section class="scheda">
	<h2>Meta</h2>
	<div class="campo-confronto">
		<span class="etichetta">Title SEO</span>
		<div class="dopo" style="grid-column: span 2">
			<?php echo e( $bozza['meta_title'] ); ?>
			<span class="conta"><?php echo mb_strlen( (string) $bozza['meta_title'] ); ?> caratteri<?php echo mb_strlen( (string) $bozza['meta_title'] ) > 60 ? ' — oltre il limite di 60' : ''; ?></span>
		</div>
	</div>
	<div class="campo-confronto">
		<span class="etichetta">Meta description</span>
		<div class="dopo" style="grid-column: span 2">
			<?php echo e( $bozza['meta_description'] ); ?>
			<span class="conta"><?php echo mb_strlen( (string) $bozza['meta_description'] ); ?> caratteri</span>
		</div>
	</div>
</section>

<?php if ( $bozza['in_breve'] ) : ?>
	<section class="scheda">
		<h2>Blocco "In breve"</h2>
		<p class="guida">Va in cima all'articolo: è il testo che i motori generativi citano come risposta.</p>
		<div class="dopo"><?php echo e( $bozza['in_breve'] ); ?></div>
	</section>
<?php endif; ?>

<section class="scheda">
	<h2>Testo dell'articolo</h2>
	<div class="testo-bozza"><?php echo Html::sanifica( (string) $bozza['corpo_html'] ); ?></div>
</section>

<?php if ( $faq ) : ?>
	<section class="scheda">
		<h2>Domande frequenti</h2>
		<?php foreach ( $faq as $f ) : ?>
			<h3><?php echo e( $f['domanda'] ?? '' ); ?></h3>
			<p><?php echo e( $f['risposta'] ?? '' ); ?></p>
		<?php endforeach; ?>
	</section>
<?php endif; ?>

<?php if ( $bozza['note'] ) : ?>
	<section class="scheda">
		<h2>Cosa è cambiato</h2>
		<p><?php echo e( $bozza['note'] ); ?></p>
	</section>
<?php endif; ?>

<section class="scheda">
	<h2>HTML da incollare in WordPress</h2>
	<p class="guida">Copia e incolla nell'editor in modalità <strong>Codice</strong>. In alternativa usa <em>Invia le bozze al sito</em>: crea l'articolo in stato Bozza senza toccare quello pubblicato.</p>
	<textarea id="html-bozza" rows="10" readonly style="width:100%;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px;padding:12px;border:1px solid var(--linea);border-radius:4px;background:var(--superficie);color:var(--inchiostro)"><?php echo e( $contenuto ); ?></textarea>
	<p style="margin-top:10px"><button class="bottone chiaro" type="button" id="copia">Copia negli appunti</button></p>
</section>

<script>
document.getElementById('copia').addEventListener('click', function () {
	var campo = document.getElementById('html-bozza');
	campo.select();
	campo.setSelectionRange(0, campo.value.length);

	try {
		document.execCommand('copy');
		this.textContent = 'Copiato';
		var bottone = this;
		setTimeout(function () { bottone.textContent = 'Copia negli appunti'; }, 2000);
	} catch (e) {
		this.textContent = 'Copia con Ctrl+C';
	}
});
</script>
