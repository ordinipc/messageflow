<?php
/**
 * Dashboard di un audit.
 *
 * @package SeoGeoAudit
 * @var array $confronto Esito del confronto degli indirizzi.
 * @var array $audit    Riga audit.
 * @var array $aree     Punteggi per area.
 * @var array $rilievi  Rilievi ancora aperti, ordinati per gravità.
 * @var array $sistemati Rilievi che il lavoro fatto ha già chiuso.
 * @var array $conteggi Conteggi del triage.
 */

$sistemati = $sistemati ?? array();
$allineato = $allineato ?? array();
$allinea   = $allinea ?? array();

// I problemi che restano davvero: le occorrenze della fotografia meno quelle
// chiuse da quando e stata scattata.
$problemiAperti = 0;
$problemiChiusi = 0;

foreach ( array_merge( $rilievi, $sistemati ) as $r ) {
	$problemiAperti += (int) $r['occorrenze'];
	$problemiChiusi += (int) ( $r['chiuse'] ?? 0 );
}

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

// Dove si risolve ogni problema. Senza questo la colonna «Correzione» dice
// «automatica» e basta, che lascia chi legge con l elenco in mano e nessun
// posto dove andare.
$rimedi    = \SeoGeo\Rimedi::mappa( $audit['id'] );
$riassunto = \SeoGeo\Rimedi::riassunto( $rilievi, $audit['id'] );
$perArea   = \SeoGeo\Rimedi::perArea( $rilievi, $audit['id'] );

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

<?php if ( ! empty( $nuovo ) ) : ?>
	<p class="avviso ok-bg">Analisi aggiornata leggendo direttamente dal sito.</p>
<?php endif; ?>

<div class="riquadri">
	<div class="riquadro grande">
		<span class="etichetta">Punteggio complessivo</span>
		<span class="voto-grande <?php echo $audit['punteggio'] >= 70 ? 'ok' : ( $audit['punteggio'] >= 45 ? 'medio' : 'grave' ); ?>"><?php echo (int) $audit['punteggio']; ?><small>/100</small></span>
		<?php if ( ! empty( $precedente ) ) : ?>
			<?php $delta = (int) $audit['punteggio'] - (int) $precedente['punteggio']; ?>
			<span class="sotto">
				<?php if ( 0 === $delta ) : ?>
					invariato rispetto all'analisi del <?php echo e( substr( $precedente['creato_il'], 0, 10 ) ); ?>
				<?php else : ?>
					<strong style="color:<?php echo $delta > 0 ? 'var(--ok)' : 'var(--grave)'; ?>"><?php echo $delta > 0 ? '+' . $delta : $delta; ?></strong>
					rispetto all'analisi del <?php echo e( substr( $precedente['creato_il'], 0, 10 ) ); ?> (era <?php echo (int) $precedente['punteggio']; ?>)
				<?php endif; ?>
			</span>
		<?php endif; ?>
	</div>
	<div class="riquadro">
		<span class="etichetta">Problemi rilevati</span>
		<strong><?php echo num( $problemiAperti ); ?></strong>
		<span class="sotto">
			<?php if ( $problemiChiusi ) : ?>
				<?php echo num( $problemiChiusi ); ?> già sistemati da quando il sito è stato letto ·
			<?php endif; ?>
			su <?php echo count( $rilievi ); ?> controlli non superati ·
			<?php echo (int) $riassunto['plugin']; ?> li chiude il plugin,
			<?php echo (int) $riassunto['azione']; ?> un pulsante,
			<?php echo (int) $riassunto['manuale']; ?> a mano
		</span>
	</div>
	<div class="riquadro">
		<span class="etichetta">Articoli da rivedere</span>
		<strong><?php echo num( $daRivedere ); ?></strong>
		<span class="sotto">
			<?php echo num( $perCategoria['eliminare'] ?? 0 ); ?> da eliminare,
			<?php echo num( $perCategoria['accorpare'] ?? 0 ); ?> da accorpare,
			<?php echo num( $perCategoria['riscrivere'] ?? 0 ); ?> da riscrivere
		</span>
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
		<?php $rim = $perArea[ $a['chiave'] ] ?? array(); ?>
		<div class="area">
			<div class="nome">
				<?php echo e( $a['etichetta'] ); ?>
				<small><?php echo (int) $a['rilievi']; ?> controlli non superati · peso <?php echo (int) $a['peso']; ?>%</small>
				<small>
					<?php if ( ! empty( $rim['dove'] ) ) : ?>
						<a href="<?php echo e( $rim['dove'] ); ?>">Correggi da «<?php echo e( $rim['etichetta'] ); ?>»</a>
						<?php if ( ! empty( $rim['plugin'] ) ) : ?>
							· altri <?php echo (int) $rim['plugin']; ?> li chiude il plugin
						<?php endif; ?>
					<?php elseif ( ! empty( $rim['plugin'] ) ) : ?>
						<a href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>">Li chiude il plugin: <?php echo (int) $rim['plugin']; ?> su <?php echo (int) $a['rilievi']; ?></a>
					<?php elseif ( (int) $a['rilievi'] > 0 ) : ?>
						nessuna correzione automatica: si sistema a mano
					<?php endif; ?>
				</small>
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
	<p class="guida">
		Il sito è stato letto per intero il <?php echo e( substr( $audit['creato_il'], 0, 16 ) ); ?>.
		I numeri qui sotto non sono fermi a quel momento: a ogni apertura di questa pagina il
		gestionale richiede al sito quello che si può sapere in fretta — che cosa stampa il plugin
		su ogni pagina, quali redirect sono attivi, quali articoli hanno l'immagine in evidenza — e
		toglie dal conto quello che risulta già fatto, insieme a tutto ciò che ha scritto lui stesso
		sul sito. I controlli chiusi del tutto finiscono più in basso, in «Già sistemati».
	</p>
	<?php if ( ! empty( $allineato ) ) : ?>
		<p class="avviso ok-bg">
			Appena controllato sul sito:
			<?php echo num( array_sum( $allineato ) ); ?> segnalazioni erano già risolte e sono state
			tolte dal conto (<?php echo e( implode( ', ', array_keys( $allineato ) ) ); ?>).
		</p>
	<?php elseif ( ! empty( $allinea['quando'] ) ) : ?>
		<p class="guida">
			Ultimo controllo sul sito: <?php echo e( substr( (string) $allinea['quando'], 0, 16 ) ); ?> —
			niente di nuovo da togliere.
		</p>
	<?php endif; ?>
	<p class="guida">
		Quello che resta è la lettura completa: le cose che costano minuti da verificare — la
		lunghezza dei testi, le meta di ogni articolo, il peso di ogni file — si aggiornano
		rileggendo il sito.
	</p>
	<form method="post" action="?p=risincronizza">
		<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
		<button class="bottone chiaro piccolo" type="submit">Rileggi il sito e ricalcola questi numeri</button>
	</form>
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
					<td class="num">
						<?php echo num( $r['occorrenze'] ); ?>
						<?php if ( ! empty( $r['chiuse'] ) ) : ?>
							<div class="sotto"><?php echo num( $r['chiuse'] ); ?> sistemate</div>
						<?php endif; ?>
					</td>
					<?php $rim = $rimedi[ $r['regola'] ] ?? array(); ?>
					<td class="rimedio">
						<?php if ( 'azione' === ( $rim['come'] ?? '' ) ) : ?>
							<a class="bottone chiaro piccolo" href="<?php echo e( $rim['dove'] ); ?>"><?php echo e( $rim['etichetta'] ); ?></a>
							<div class="sotto"><?php echo e( $rim['spiega'] ); ?></div>
						<?php elseif ( 'plugin' === ( $rim['come'] ?? '' ) ) : ?>
							<span class="tag ok">lo fa il plugin</span>
							<div class="sotto"><?php echo e( $rim['spiega'] ); ?></div>
						<?php else : ?>
							<span class="tag basso">a mano</span>
							<div class="sotto"><?php echo e( $r['soluzione'] ); ?></div>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>

<?php if ( $sistemati ) : ?>
<section class="scheda">
	<h2>Già sistemati</h2>
	<p class="guida">
		Questi controlli erano fuori posto quando il sito è stato letto, ma da allora il lavoro è
		stato fatto e scritto sul sito. Restano qui, invece di sparire, perché un problema risolto e
		un problema dimenticato non devono avere lo stesso aspetto. Alla prossima rilettura del sito
		spariranno da soli.
	</p>
	<div class="tabellabox">
		<table>
			<thead>
				<tr><th>Regola</th><th>Problema</th><th class="num">Sistemate</th></tr>
			</thead>
			<tbody>
			<?php foreach ( $sistemati as $r ) : ?>
				<tr>
					<td class="mono"><?php echo e( $r['regola'] ); ?></td>
					<td>
						<a href="?p=rilievo&amp;id=<?php echo (int) $r['id']; ?>"><?php echo e( $r['titolo'] ); ?></a>
					</td>
					<td class="num"><?php echo num( $r['chiuse'] ); ?> su <?php echo num( $r['occorrenze_iniziali'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
<?php endif; ?>

<form class="scheda" method="post" action="?p=risincronizza">
	<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
	<h2>Il punteggio è aggiornato?</h2>
	<p class="guida">
		No: è la fotografia del momento in cui il sito è stato analizzato
		(<?php echo e( substr( $audit['creato_il'], 0, 16 ) ); ?>). Le correzioni applicate dopo non lo spostano da sole.
		Rileggi il sito per ricalcolarlo e confrontarlo con questo.
	</p>
	<button class="bottone chiaro" type="submit">Rileggi il sito e ricalcola</button>
</form>

<?php $cambiati = $confronto['cambiati']; ?>

<?php if ( $cambiati ) : ?>
	<section class="scheda">
		<h2>⚠︎ <?php echo 1 === count( $cambiati ) ? 'Un contenuto ha' : num( count( $cambiati ) ) . ' contenuti hanno'; ?> cambiato indirizzo</h2>
		<p class="guida">
			Il vecchio indirizzo da quel momento dà <strong>pagina non trovata</strong>: i link che arrivano
			da fuori si perdono e la posizione guadagnata in Google riparte da zero. Si sistema con un
			redirect 301, che è gratis e richiede un clic.
		</p>
		<ul class="guida">
			<?php foreach ( array_slice( $cambiati, 0, 5 ) as $riga ) : ?>
				<li><code><?php echo e( $riga['da'] ); ?></code> → <code><?php echo e( $riga['a'] ); ?></code></li>
			<?php endforeach; ?>
			<?php if ( count( $cambiati ) > 5 ) : ?>
				<li>e altri <?php echo num( count( $cambiati ) - 5 ); ?></li>
			<?php endif; ?>
		</ul>
		<p><a class="bottone" href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>#indirizzi">Sistemali adesso</a></p>
	</section>
<?php elseif ( ! empty( $confronto['motivo'] ) ) : ?>
	<p class="nota">
		<strong>Indirizzi:</strong> <?php echo e( $confronto['motivo'] ); ?>
		<?php if ( ! empty( $confronto['prima'] ) && ! empty( $confronto['ultima'] ) ) : ?>
			(confronto fra l analisi del <?php echo e( substr( (string) $confronto['prima']['creato_il'], 0, 16 ) ); ?>
			e quella del <?php echo e( substr( (string) $confronto['ultima']['creato_il'], 0, 16 ) ); ?>,
			su <?php echo num( $confronto['confrontati'] ); ?> contenuti in comune)
		<?php endif; ?>
	</p>
<?php endif; ?>

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
	<h2>Due articoli si vedono diversi e non si capisce perché</h2>
	<p class="guida">Legge dal sito che cosa c'è dentro a due articoli e dice che cosa è diverso davvero: chi ha scritto il testo, com'è fatta la struttura di Elementor, e come sono impostati nel tema — che è quello che decide l'aspetto dell'intestazione.</p>
	<p><a class="bottone chiaro" href="?p=confronta-contenuti&amp;id=<?php echo (int) $audit['id']; ?>">Confronta due articoli</a></p>
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
		<span class="sotto">
			<?php $versione = \SeoGeo\Export::versionePlugin(); ?>
			<?php echo $versione ? 'versione ' . e( $versione ) . ' · ' : ''; ?><?php echo num( filesize( $zip ) / 1024 ); ?> KB
		</span>
	</p>
	<?php
	// Lo zip in archivio e di quando si e fatta l analisi: se nel frattempo
	// il gestionale e stato aggiornato, viene ricostruito al momento del
	// download. Lo si dice, perche altrimenti il peso mostrato qui sopra
	// non corrisponde a quello che si scarica.
	$in_archivio = \SeoGeo\Export::versioneNelloZip( $zip );
	?>
	<?php if ( $versione && $in_archivio && $versione !== $in_archivio ) : ?>
		<p class="nota">In archivio c'è la <?php echo e( $in_archivio ); ?>, ma il gestionale ha la <strong><?php echo e( $versione ); ?></strong>: il file viene rifatto al momento del download, quindi scarichi la <?php echo e( $versione ); ?>.</p>
	<?php endif; ?>
</section>
<?php endif; ?>

<section class="scheda">
	<h2>File di correzione</h2>
	<p class="guida">Generati durante l analisi e pronti da applicare sul sito.</p>
	<p class="nota">
		Sono copie da portare fuori: servono se vuoi importarli in Rank Math, caricarli sull hosting o
		tenerli da parte. <strong>Non è da qui che si correggono i problemi.</strong> Sul sito scrive il
		<a href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>">collegamento</a> — che manda le stesse
		cose direttamente a WordPress — oppure il
		<a href="?p=pilota&amp;id=<?php echo (int) $audit['id']; ?>">pilota automatico</a>. Scaricare un file
		non cambia niente sul sito, e nemmeno rifare l analisi.
	</p>
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
