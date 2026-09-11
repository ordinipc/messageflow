<?php
/**
 * Dashboard di un audit.
 *
 * @package SeoGeoAudit
 * @var array $audit    Riga audit.
 * @var array $aree     Punteggi per area.
 * @var array $rilievi  Rilievi ordinati per gravità.
 * @var array $conteggi Conteggi del triage.
 */

$gravita = array(
	'critical' => array( 'Critico', 'grave' ),
	'high'     => array( 'Alto', 'alto' ),
	'medium'   => array( 'Medio', 'medio' ),
	'low'      => array( 'Basso', 'basso' ),
);

$perCategoria = array();
foreach ( $conteggi as $c ) {
	$perCategoria[ $c['categoria'] ] = (int) $c['n'];
}

$daRivedere = ( $perCategoria['eliminare'] ?? 0 ) + ( $perCategoria['accorpare'] ?? 0 ) + ( $perCategoria['riscrivere'] ?? 0 );

$scaricabili = array(
	array( '', 'problemi.csv', 'Elenco completo dei problemi' ),
	array( '', 'triage.csv', 'Classificazione degli articoli' ),
	array( '', 'meta-ottimizzate.csv', 'Title, description, excerpt e slug riscritti' ),
	array( '', 'rank-math-bulk.csv', 'Import massivo per Rank Math' ),
	array( '', 'redirect-301.csv', 'Redirect da impostare' ),
	array( '', 'redirect.htaccess', 'Stessi redirect in formato Apache' ),
	array( '', 'piano-link-interni.csv', 'Link interni proposti' ),
	array( '', 'immagini-alt.csv', 'Alt e ottimizzazione immagini' ),
	array( 'file-root', 'llms.txt', 'Guida ai contenuti per i modelli AI' ),
	array( 'file-root', 'llms-full.txt', 'Versione con i testi completi' ),
	array( 'file-root', 'robots.txt', 'Con le direttive per i crawler AI' ),
	array( 'file-root', 'ai.txt', 'Preferenze di utilizzo dei contenuti' ),
	array( 'schema', 'tutti-gli-schema.json', 'JSON-LD per ogni URL' ),
	array( 'plugin-data', 'meta-map.json', 'Dati per il plugin WordPress' ),
	array( 'plugin-data', 'internal-links.json', 'Mappa keyword → URL per il plugin' ),
	array( 'plugin-data', 'related.json', 'Articoli correlati per il plugin' ),
);

?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <?php echo e( $audit['sito_nome'] ); ?></p>
	<h1><?php echo e( $audit['sito_nome'] ); ?></h1>
	<p class="guida"><?php echo e( $audit['sito_url'] ); ?> · analisi del <?php echo e( substr( $audit['creato_il'], 0, 16 ) ); ?> · <?php echo num( $audit['articoli'] ); ?> articoli, <?php echo num( $audit['pagine'] ); ?> pagine, <?php echo num( $audit['media'] ); ?> file media</p>
</section>

<div class="riquadri">
	<div class="riquadro grande">
		<span class="etichetta">Punteggio complessivo</span>
		<span class="voto-grande <?php echo $audit['punteggio'] >= 70 ? 'ok' : ( $audit['punteggio'] >= 45 ? 'medio' : 'grave' ); ?>"><?php echo (int) $audit['punteggio']; ?><small>/100</small></span>
	</div>
	<div class="riquadro">
		<span class="etichetta">Problemi rilevati</span>
		<strong><?php echo num( $audit['problemi_totali'] ); ?></strong>
		<span class="sotto">su <?php echo count( $rilievi ); ?> controlli non superati</span>
	</div>
	<div class="riquadro">
		<span class="etichetta">Articoli da rivedere</span>
		<strong><?php echo num( $daRivedere ); ?></strong>
		<span class="sotto">su <?php echo num( $audit['articoli'] ); ?> pubblicati</span>
	</div>
	<div class="riquadro">
		<span class="etichetta">Triage editoriale</span>
		<strong><?php echo num( $perCategoria['mantenere'] ?? 0 ); ?></strong>
		<span class="sotto">articoli già validi</span>
	</div>
</div>

<section class="scheda">
	<h2>Punteggio per area</h2>
	<div class="aree">
	<?php foreach ( $aree as $a ) : ?>
		<?php $classe = $a['punteggio'] >= 70 ? 'ok' : ( $a['punteggio'] >= 45 ? 'medio' : 'grave' ); ?>
		<div class="area">
			<div class="nome">
				<?php echo e( $a['etichetta'] ); ?>
				<small><?php echo (int) $a['rilievi']; ?> controlli non superati · peso <?php echo (int) $a['peso']; ?>%</small>
			</div>
			<div class="barra"><i class="<?php echo $classe; ?>" style="width:<?php echo max( 2, (int) $a['punteggio'] ); ?>%"></i></div>
			<div class="valore <?php echo $classe; ?>"><?php echo (int) $a['punteggio']; ?></div>
		</div>
	<?php endforeach; ?>
	</div>
</section>

<section class="scheda">
	<h2>Triage dei contenuti</h2>
	<p class="guida">Classificazione di ogni articolo pubblicato in base a qualità misurata, sovrapposizione fra i testi e competizione con le pagine servizio.</p>
	<div class="pillole">
		<?php foreach ( array( 'eliminare' => 'grave', 'accorpare' => 'alto', 'riscrivere' => 'medio', 'mantenere' => 'ok' ) as $cat => $classe ) : ?>
			<a class="pillola <?php echo $classe; ?>" href="?p=triage&amp;id=<?php echo (int) $audit['id']; ?>&amp;c=<?php echo $cat; ?>">
				<?php echo ucfirst( $cat ); ?> <b><?php echo num( $perCategoria[ $cat ] ?? 0 ); ?></b>
			</a>
		<?php endforeach; ?>
		<a class="pillola" href="?p=triage&amp;id=<?php echo (int) $audit['id']; ?>">Tutti <b><?php echo num( array_sum( $perCategoria ) ); ?></b></a>
	</div>
</section>

<section class="scheda">
	<h2>Problemi rilevati</h2>
	<div class="tabellabox">
		<table>
			<thead>
				<tr><th>Gravità</th><th>Regola</th><th>Problema</th><th class="num">Occorrenze</th><th>Correzione</th></tr>
			</thead>
			<tbody>
			<?php foreach ( $rilievi as $r ) : ?>
				<tr>
					<td><span class="tag <?php echo $gravita[ $r['gravita'] ][1]; ?>"><?php echo $gravita[ $r['gravita'] ][0]; ?></span></td>
					<td class="mono"><?php echo e( $r['regola'] ); ?></td>
					<td>
						<a href="?p=rilievo&amp;id=<?php echo (int) $r['id']; ?>"><?php echo e( $r['titolo'] ); ?></a>
						<div class="sotto"><?php echo e( $r['perche'] ); ?></div>
					</td>
					<td class="num"><?php echo num( $r['occorrenze'] ); ?></td>
					<td><?php echo $r['automatico'] ? '<span class="tag ok">automatica</span>' : '<span class="tag basso">manuale</span>'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>

<section class="scheda">
	<h2>Pilota automatico</h2>
	<p class="guida">Esegue da solo, una dopo l'altra, tutte le correzioni che non richiedono una decisione umana: meta, redirect, categorie, accorpamenti e riscritture. Un pulsante, poi lascia fare.</p>
	<p><a class="bottone" href="?p=pilota&amp;id=<?php echo (int) $audit['id']; ?>">Apri il pilota automatico</a></p>
</section>

<section class="scheda">
	<h2>Applica sul sito (operazione per operazione)</h2>
	<p class="guida">Se il plugin è installato e collegato, il gestionale scrive meta, bozze, redirect e categorie direttamente su WordPress.</p>
	<p><a class="bottone" href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>">Apri il collegamento</a></p>
</section>

<section class="scheda">
	<h2>Riscrittura assistita</h2>
	<p class="guida">Genera le bozze dei contenuti da riscrivere partendo dalle schede dell'audit, con Google Gemini. Le bozze restano in attesa di revisione: nulla viene pubblicato.</p>
	<p><a class="bottone" href="?p=bozze&amp;id=<?php echo (int) $audit['id']; ?>">Apri la riscrittura</a></p>
</section>

<?php $zip = __DIR__ . '/../storage/export/audit-' . (int) $audit['id'] . '/mdi-seo-geo-booster.zip'; ?>
<?php if ( is_file( $zip ) ) : ?>
<section class="scheda">
	<h2>Plugin WordPress pronto</h2>
	<p class="guida">Contiene già le meta ottimizzate, la mappa dei link interni, gli articoli correlati e i file per i crawler AI di questo sito. Si installa da <em>Plugin → Aggiungi nuovo → Carica plugin</em>.</p>
	<p>
		<a class="bottone" href="?p=download&amp;id=<?php echo (int) $audit['id']; ?>&amp;f=mdi-seo-geo-booster.zip">Scarica mdi-seo-geo-booster.zip</a>
		<span class="sotto"><?php echo num( filesize( $zip ) / 1024 ); ?> KB</span>
	</p>
</section>
<?php endif; ?>

<section class="scheda">
	<h2>File di correzione</h2>
	<p class="guida">Generati durante l analisi e pronti da applicare sul sito.</p>
	<ul class="elenco-file">
	<?php foreach ( $scaricabili as $f ) : ?>
		<?php $percorso = __DIR__ . '/../storage/export/audit-' . (int) $audit['id'] . '/' . ( $f[0] ? $f[0] . '/' : '' ) . $f[1]; ?>
		<?php if ( is_file( $percorso ) ) : ?>
			<li>
				<a href="?p=download&amp;id=<?php echo (int) $audit['id']; ?>&amp;d=<?php echo e( $f[0] ); ?>&amp;f=<?php echo e( $f[1] ); ?>"><?php echo e( $f[1] ); ?></a>
				<span class="sotto"><?php echo e( $f[2] ); ?> · <?php echo num( filesize( $percorso ) / 1024 ); ?> KB</span>
			</li>
		<?php endif; ?>
	<?php endforeach; ?>
	</ul>
</section>
