<?php
/**
 * Collaudo del gestionale sui punti che in produzione si sono rotti.
 *
 * Uso: php test/app.php
 *
 * @package SeoGeoAudit
 */

require_once __DIR__ . '/../src/Autoload.php';

use SeoGeo\Site;
use SeoGeo\Sync\Sito as SitoRemoto;

$errori = 0;

/**
 * Verifica un asserto e stampa l esito.
 *
 * @param string $descrizione Cosa si sta verificando.
 * @param bool   $condizione  Esito.
 * @param string $dettaglio   Informazione aggiuntiva in caso di errore.
 * @return void
 */
function verifica( $descrizione, $condizione, $dettaglio = '' ) {
	global $errori;

	if ( $condizione ) {
		echo "  ✔ $descrizione\n";
		return;
	}

	$errori++;
	echo "  ✖ $descrizione" . ( $dettaglio ? " — $dettaglio" : '' ) . "\n";
}

/**
 * Esegue una funzione e restituisce il messaggio dell eccezione.
 *
 * @param callable $azione Codice da eseguire.
 * @return string Vuoto se non ha sollevato nulla.
 */
function errore_di( callable $azione ) {
	try {
		$azione();
	} catch ( Throwable $e ) {
		return $e->getMessage();
	}

	return '';
}

/**
 * Costruisce un sito minimo con un solo contenuto.
 *
 * @param array $meta Meta del contenuto.
 * @return Site
 */
function sito_con_meta( array $meta ) {
	return new Site(
		array(
			'sito'      => array(
				'titolo'  => 'Prova',
				'link'    => 'https://esempio.it',
				'baseUrl' => 'https://esempio.it',
				'autori'  => array(),
			),
			'categorie' => array(),
			'tag'       => array(),
			'items'     => array(
				array(
					'wp_id'      => '1',
					'tipo'       => 'post',
					'stato'      => 'publish',
					'titolo'     => 'Articolo di prova',
					'slug'       => 'articolo-di-prova',
					'link'       => 'https://esempio.it/articolo-di-prova/',
					'data'       => '2026-01-01 10:00:00',
					'modificato' => '2026-02-01 10:00:00',
					'autore'     => 'Redazione',
					'contenuto'  => '<h2>Sezione</h2><p>Testo di prova.</p>',
					'estratto'   => '',
					'categorie'  => array(),
					'tag'        => array(),
					'commenti'   => 'closed',
					'genitore'   => '0',
					'meta'       => $meta,
				),
			),
		)
	);
}

echo "\n▶ Collaudo del gestionale\n\n";

// --- Meta che WordPress restituisce come array ------------------------------
// In produzione questo faceva cadere l analisi con
// "preg_match(): Argument #2 ($subject) must be of type string, array given".
echo "Meta lette dal sito\n";

try {
	$doc    = sito_con_meta( array( 'rank_math_robots' => array( 'noindex', 'nofollow' ) ) )->articoli[0];
	$caduta = '';
} catch ( Throwable $e ) {
	$doc    = array( 'noindex' => null, 'robots' => null );
	$caduta = $e->getMessage();
}

verifica( 'un robots come array non fa cadere l analisi', '' === $caduta, $caduta );
verifica( 'il noindex viene riconosciuto lo stesso', true === $doc['noindex'] );
verifica( 'robots diventa testo', 'noindex,nofollow' === $doc['robots'], var_export( $doc['robots'], true ) );

$doc = sito_con_meta( array( 'rank_math_robots' => 'a:2:{i:0;s:5:"index";i:1;s:6:"follow";}' ) )->articoli[0];
verifica( 'la stringa serializzata dell export XML resta valida', false === $doc['noindex'] );

$doc = sito_con_meta( array( 'rank_math_robots' => array( 'index', 'follow' ) ) )->articoli[0];
verifica( 'index,follow non viene scambiato per noindex', false === $doc['noindex'] );

$doc = sito_con_meta(
	array(
		'rank_math_title'         => array( 'per sbaglio un array' ),
		'rank_math_description'   => array( 'anche qui' ),
		'rank_math_focus_keyword' => array( 'siti web', 'palermo' ),
		'rank_math_canonical_url' => array( 'https://esempio.it/' ),
		'_thumbnail_id'           => array( '42' ),
	)
)->articoli[0];

verifica( 'nessun campo SEO resta un array', is_string( $doc['seo_title'] ) && is_string( $doc['seo_desc'] ) && is_string( $doc['focus'] ) && is_string( $doc['canonical'] ) && is_string( $doc['thumbnail'] ) );
verifica( 'la focus keyword prende la prima voce', 'siti web' === $doc['focus'], $doc['focus'] );

$doc = sito_con_meta( array() )->articoli[0];
verifica( 'senza meta i campi restano vuoti, non nulli', '' === $doc['seo_title'] && '' === $doc['robots'] && false === $doc['noindex'] );

// --- Normalizzazione a monte, nel lettore del sito --------------------------
echo "\nNormalizzazione delle meta in arrivo\n";

$metodo = new ReflectionMethod( SitoRemoto::class, 'meta' );
$metodo->setAccessible( true );

$pulite = $metodo->invoke(
	null,
	array(
		'array'   => array( 'index', 'follow' ),
		'vero'    => true,
		'falso'   => false,
		'numero'  => 12,
		'oggetto' => new stdClass(),
		'testo'   => 'invariato',
	)
);

verifica( 'un array diventa un elenco separato da virgole', 'index,follow' === $pulite['array'] );
verifica( 'un booleano vero diventa 1', '1' === $pulite['vero'] );
verifica( 'un booleano falso diventa vuoto', '' === $pulite['falso'] );
verifica( 'un numero diventa testo', '12' === $pulite['numero'] );
verifica( 'un oggetto viene scartato', '' === $pulite['oggetto'] );
verifica( 'il testo resta com era', 'invariato' === $pulite['testo'] );
verifica( 'tutti i valori sono stringhe', array() === array_filter( $pulite, static fn( $v ) => ! is_string( $v ) ) );

// --- Ordine di lavoro del pilota automatico --------------------------------
// Con Search Console collegata l ordine non è più quello editoriale: davanti
// vanno gli articoli su cui Google dice che c è da guadagnare.
echo "\nOrdine di lavoro del pilota automatico\n";

$ordina = new ReflectionMethod( \SeoGeo\Coda::class, 'ordinaPerPriorita' );
$ordina->setAccessible( true );

$candidati = array(
	array( 'doc_id' => 1, 'titolo' => 'Primo in ordine editoriale', 'url' => 'https://esempio.it/uno/' ),
	array( 'doc_id' => 2, 'titolo' => 'Senza dati', 'url' => 'https://esempio.it/due/' ),
	array( 'doc_id' => 3, 'titolo' => 'A un passo dalla prima pagina', 'url' => 'https://www.esempio.it/tre' ),
	array( 'doc_id' => 4, 'titolo' => 'Segnale debole', 'url' => 'https://esempio.it/quattro/' ),
);

$priorita = array(
	'esempio.it/tre'      => array( 'priorita' => 96, 'titolo' => 'A un passo', 'impression' => 500 ),
	'esempio.it/quattro'  => array( 'priorita' => 60, 'titolo' => 'Mai mostrata', 'impression' => 0 ),
);

$ordinati = $ordina->invoke( null, $candidati, $priorita );
$ids      = array_column( $ordinati, 'doc_id' );

verifica( 'davanti va l articolo con il segnale più forte', 3 === $ids[0], implode( ',', $ids ) );
verifica( 'poi quello con il segnale debole', 4 === $ids[1], implode( ',', $ids ) );
verifica( 'gli articoli senza dati restano nel loro ordine editoriale', array( 1, 2 ) === array_slice( $ids, 2 ), implode( ',', $ids ) );
verifica( 'nessun articolo viene perso per strada', 4 === count( $ordinati ) );

$senzaDati = $ordina->invoke( null, $candidati, array() );
verifica( 'senza segnali l ordine non cambia', array( 1, 2, 3, 4 ) === array_column( $senzaDati, 'doc_id' ) );

// www e barra finale non devono impedire l aggancio fra articolo e segnale.
verifica( 'l indirizzo si confronta senza www né barra finale', 'esempio.it/tre' === \SeoGeo\Search\Prestazioni::chiaveUrl( 'https://www.Esempio.it/tre/' ) );

// La chiave del sito deve essere la stessa quando si salva e quando si rilegge.
$cfgProva = array( 'azienda' => array(), 'wordpress' => array( 'url' => 'https://sito.it/' ), 'google' => array( 'proprieta' => 'sc-domain:sito.it' ) );
verifica( 'la chiave del sito viene dal sito, non dalla proprietà', 'https://sito.it' === \SeoGeo\Search\Prestazioni::chiaveSito( $cfgProva ) );
verifica(
	'senza sito configurato si ripiega sulla proprietà',
	'sc-domain:sito.it' === \SeoGeo\Search\Prestazioni::chiaveSito( array( 'google' => array( 'proprieta' => 'sc-domain:sito.it' ) ) )
);

// --- Da dove arriva la chiave di Google ------------------------------------
// Su parecchi hosting il firewall blocca i moduli che contengono una chiave
// privata: deve esserci una strada che non passa dal browser.
echo "\nChiave di Google\n";

$fileChiave = \SeoGeo\Impostazioni::fileChiaveGoogle();
$esisteva   = is_file( $fileChiave );
$copia      = $esisteva ? file_get_contents( $fileChiave ) : null;

@unlink( $fileChiave );

verifica(
	'senza niente da nessuna parte la chiave è vuota',
	'' === \SeoGeo\Impostazioni::chiaveGoogle( array( 'google' => array( 'chiave_json' => '' ) ) )
);

file_put_contents( $fileChiave, '{"type":"service_account","da":"file"}' );

verifica(
	'in mancanza d altro si legge storage/google.json',
	false !== strpos( \SeoGeo\Impostazioni::chiaveGoogle( array( 'google' => array( 'chiave_json' => '' ) ) ), '"da":"file"' )
);

verifica(
	'la chiave salvata dalle impostazioni ha la precedenza sul file',
	false !== strpos(
		\SeoGeo\Impostazioni::chiaveGoogle( array( 'google' => array( 'chiave_json' => '{"da":"impostazioni"}' ) ) ),
		'impostazioni'
	)
);

verifica(
	'con la sola chiave nel file il collegamento risulta configurato',
	\SeoGeo\Search\Prestazioni::configurata(
		array( 'google' => array( 'chiave_json' => '', 'proprieta' => 'sc-domain:sito.it' ) )
	)
);

verifica(
	'senza proprietà non basta la chiave',
	! \SeoGeo\Search\Prestazioni::configurata( array( 'google' => array( 'chiave_json' => '', 'proprieta' => '' ) ) )
);

@unlink( $fileChiave );

if ( $esisteva ) {
	file_put_contents( $fileChiave, $copia );
}

// --- Dalle indicazioni di Google alle modifiche sul contenuto --------------
// Un segnale dice "questa pagina è a un passo": serve sapere quale contenuto
// del sito è, altrimenti non si può toccare niente.
echo "\nDa Google al contenuto giusto\n";

$fileDb = sys_get_temp_dir() . '/prova-azioni-' . getmypid() . '.sqlite';
@unlink( $fileDb );
$db = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileDb ) );

$auditId = $db->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50 )
);

foreach ( array(
	array( 'wp_id' => '10', 'titolo' => 'Guida ai siti web', 'percorso' => '/blog/guida/', 'url' => 'https://esempio.it/blog/guida/', 'tipo' => 'post' ),
	array( 'wp_id' => '11', 'titolo' => 'Servizi', 'percorso' => '/servizi/', 'url' => 'https://esempio.it/servizi/', 'tipo' => 'page' ),
) as $documento ) {
	$db->insert( 'documento', $documento + array( 'audit_id' => $auditId, 'stato' => 'publish' ) );
}

$segnali = \SeoGeo\Search\Azioni::abbina(
	$db,
	$auditId,
	array(
		array( 'tipo' => 'titolo_che_non_rende', 'url' => 'https://esempio.it/servizi/', 'query' => 'agenzia web palermo', 'impression' => 800, 'posizione' => 3.4, 'priorita' => 90 ),
		array( 'tipo' => 'quasi_prima_pagina', 'url' => 'https://www.esempio.it/blog/guida', 'query' => 'siti web palermo', 'impression' => 500, 'posizione' => 12.8, 'priorita' => 80 ),
		array( 'tipo' => 'in_calo', 'url' => 'https://esempio.it/blog/guida/', 'query' => '', 'impression' => 500, 'posizione' => 12.8, 'priorita' => 75 ),
		array( 'tipo' => 'mai_mostrata', 'url' => 'https://esempio.it/pagina-sconosciuta/', 'query' => '', 'impression' => 0, 'posizione' => 0, 'priorita' => 60 ),
	)
);

verifica( 'il contenuto viene riconosciuto dall indirizzo', 'Servizi' === $segnali[0]['titolo_sito'] );
verifica( 'e ne porta l identificativo di WordPress', '11' === $segnali[0]['wp_id'] );
verifica( 'www e barra finale non impediscono il riconoscimento', 'Guida ai siti web' === $segnali[1]['titolo_sito'] );
verifica( 'un indirizzo che non è sul sito resta senza contenuto', 0 === $segnali[3]['documento_id'] );

$piano = \SeoGeo\Search\Azioni::piano( $segnali );

$tutto = \SeoGeo\Search\Azioni::piano( $segnali, array( 'pagine' => true ) );

verifica( 'dal segnale sul titolo esce un compito sulle meta', 'meta_mirata' === $tutto[0]['compito'] );
verifica( 'e si porta dietro la ricerca vera', 'agenzia web palermo' === $tutto[0]['query'] );
verifica( 'da "a un passo" esce una riscrittura', 'bozza' === $tutto[1]['compito'] );
verifica( 'i segnali senza azione automatica restano fuori', 1 === count( $piano ), count( $piano ) . ' compiti' );

// Le pagine servizio sono scritte a mano: restano fuori se non le si chiede.
// Qui il segnale sulle meta riguarda la pagina "Servizi".
verifica( 'la pagina resta fuori dal piano automatico', 'bozza' === $piano[0]['compito'] || 'page' !== $segnali[0]['tipo_sito'] );
verifica( 'nessun compito tocca una pagina', array() === array_filter( $piano, static fn( $v ) => 'Servizi' === $v['titolo_sito'] ) );
verifica( 'ma il programma dice quante ne ha lasciate fuori', 1 === \SeoGeo\Search\Azioni::pagineEscluse( $segnali ) );

$con_pagine = \SeoGeo\Search\Azioni::piano( $segnali, array( 'pagine' => true ) );
verifica( 'chiedendolo espressamente le pagine rientrano', 2 === count( $con_pagine ), count( $con_pagine ) . ' compiti' );
verifica( 'e fra queste c è la pagina Servizi', array() !== array_filter( $con_pagine, static fn( $v ) => 'Servizi' === $v['titolo_sito'] ) );

// Stesso contenuto, due segnali: un compito solo, o si lavorerebbe due volte
// sopra sé stessi nello stesso giro.
$doppio = \SeoGeo\Search\Azioni::piano(
	\SeoGeo\Search\Azioni::abbina(
		$db,
		$auditId,
		array(
			array( 'tipo' => 'quasi_prima_pagina', 'url' => 'https://esempio.it/blog/guida/', 'query' => 'a', 'impression' => 500, 'posizione' => 12.0, 'priorita' => 90 ),
			array( 'tipo' => 'cannibalizzazione', 'url' => 'https://esempio.it/blog/guida/', 'query' => 'b', 'impression' => 400, 'posizione' => 14.0, 'priorita' => 75 ),
		)
	)
);

verifica( 'due segnali sullo stesso contenuto danno un compito solo', 1 === count( $doppio ) );
verifica( 'e vince quello arrivato prima, cioè il più importante', 'bozza' === $doppio[0]['compito'] );

// Si mette in coda il piano completo: copre tutti e due i tipi di compito.
$esito = \SeoGeo\Search\Azioni::inCoda( $db, $auditId, $tutto );
$coda  = $db->all( 'SELECT * FROM coda WHERE audit_id = ? ORDER BY ordine', array( $auditId ) );

verifica( 'il piano finisce nella coda del pilota', 2 === (int) $esito['totale'] && 2 === count( $coda ) );
verifica( 'la ricerca viene conservata nel compito', 'agenzia web palermo' === $coda[0]['dettaglio'] );
verifica( 'il compito punta al documento, non all indirizzo', (string) $segnali[0]['documento_id'] === $coda[0]['riferimento'] );
verifica( 'l etichetta dice cosa si sta per fare e su cosa', false !== strpos( $coda[0]['etichetta'], 'Servizi' ) );

\SeoGeo\Search\Azioni::inCoda( $db, $auditId, $tutto );
verifica( 'ripreparare il piano non accumula compiti vecchi', 2 === count( $db->all( 'SELECT id FROM coda WHERE audit_id = ?', array( $auditId ) ) ) );

@unlink( $fileDb );

// --- Qualità di quello che finisce sul sito --------------------------------
// Queste meta ora vengono pubblicate senza passare da nessuno: quello che
// prima era brutto, adesso è brutto in pagina.
echo "\nQualità delle meta pubblicate\n";

$cfgMeta = require __DIR__ . '/../config.php';

$scarno = array(
	'titolo' => 'Servizi', 'slug' => 'servizi', 'focus' => 'agenzia web palermo',
	'testo'  => 'Servizi.', 'seo_title' => '', 'seo_desc' => '', 'primo_paragrafo' => '', 'estratto' => '',
);

list( $title ) = \SeoGeo\Fix\Meta::title( $scarno, $cfgMeta );
verifica( 'su una pagina senza testo il title non inventa una promessa', false === stripos( $title, 'Guida pratica per le PMI' ), $title );
verifica( 'ma la parola chiave c è comunque', false !== stripos( $title, 'agenzia web palermo' ), $title );

list( $descrizione ) = \SeoGeo\Fix\Meta::description( $scarno, $cfgMeta );
$inviti = 0;

foreach ( array( 'Scopri come lavoriamo', 'Richiedi una consulenza', 'Parla con i nostri esperti', 'Contattaci per un preventivo' ) as $invito ) {
	$inviti += false !== stripos( $descrizione, $invito ) ? 1 : 0;
}

verifica( 'la description non impila più inviti all azione', $inviti <= 1, $descrizione );

$pieno = array(
	'titolo' => 'Realizzazione siti web a Palermo', 'slug' => 'siti', 'focus' => 'agenzia web palermo',
	'testo'  => str_repeat( 'Realizziamo siti web su misura per le aziende siciliane, curando grafica contenuti e velocità. ', 4 ),
	'seo_title' => '', 'seo_desc' => '', 'primo_paragrafo' => '', 'estratto' => '',
);

list( $titoloPieno ) = \SeoGeo\Fix\Meta::title( $pieno, $cfgMeta );
list( $descPiena )   = \SeoGeo\Fix\Meta::description( $pieno, $cfgMeta );

verifica( 'con del testo vero il title resta pieno di senso', mb_strlen( $titoloPieno ) >= 30 && mb_strlen( $titoloPieno ) <= $cfgMeta['seo']['titleMax'], $titoloPieno );
verifica( 'e la description arriva alla lunghezza utile con il contenuto', mb_strlen( $descPiena ) >= 140, mb_strlen( $descPiena ) . ' caratteri' );

// --- Risposte del modello tagliate a metà ----------------------------------
// In produzione arrivava "Risposta non in formato JSON: { "titolo": ..." con
// dentro del JSON perfettamente valido: era solo finito lo spazio.
echo "\nRisposte del modello\n";

$porta   = 8873;
$mock    = proc_open(
	sprintf( 'php -S 127.0.0.1:%d %s', $porta, escapeshellarg( __DIR__ . '/mock/gemini.php' ) ),
	array( 1 => array( 'file', '/dev/null', 'w' ), 2 => array( 'file', '/dev/null', 'w' ) ),
	$tubi
);

for ( $i = 0; $i < 50; $i++ ) {
	$prova = @fsockopen( '127.0.0.1', $porta, $n, $m, 0.1 );

	if ( $prova ) {
		fclose( $prova );
		break;
	}

	usleep( 100000 );
}

$base = 'http://127.0.0.1:' . $porta . '/gemini.php?modo=%s&m=';

$cliente = static function ( $modo, $tetto = 8192 ) use ( $base ) {
	return new \SeoGeo\Ai\Gemini(
		array(
			'chiave'    => 'prova',
			'endpoint'  => sprintf( $base, $modo ),
			'modello'   => 'gemini-2.5-flash',
			'max_token' => $tetto,
			'tentativi' => 1,
		)
	);
};

$dati = $cliente( 'completo' )->generaJson( 'istruzioni', 'richiesta' );
verifica( 'una risposta intera viene letta', isset( $dati['corpo_html'] ) );

// Il caso vero: prima risposta tagliata, seconda intera col budget raddoppiato.
$dati = $cliente( 'tronca' )->generaJson( 'istruzioni', 'richiesta' );
verifica( 'una risposta tagliata fa riprovare con più spazio, e la seconda riesce', isset( $dati['corpo_html'] ) );

$messaggio = errore_di( static fn() => $cliente( 'sempre-tronca' )->generaJson( 'istruzioni', 'richiesta' ) );
verifica( 'se lo spazio non basta mai lo dice chiaramente', false !== stripos( $messaggio, 'esaurito lo spazio' ), $messaggio );
verifica( 'e non dà la colpa al formato', false === stripos( $messaggio, 'non in formato JSON' ), $messaggio );
verifica( 'e dice quanto è stato speso a ragionare', false !== stripos( $messaggio, 'ragionamento' ), $messaggio );
verifica( 'e indica dove alzare il valore', false !== stripos( $messaggio, 'max_token' ), $messaggio );

// Una risposta che JSON non è resta un errore di formato, senza riprovare.
$messaggio = errore_di( static fn() => $cliente( 'non-json' )->generaJson( 'istruzioni', 'richiesta' ) );
verifica( 'una risposta che non è JSON resta un errore di formato', false !== stripos( $messaggio, 'non in formato JSON' ), $messaggio );
verifica( 'e mostra cosa ha risposto il modello', false !== stripos( $messaggio, 'Mi dispiace' ), $messaggio );

// Il segnalino di troncatura non deve finire dentro una risposta JSON.
$testo = $cliente( 'sempre-tronca' )->genera( 'istruzioni', 'richiesta', array( 'json' => true ) );
verifica( 'in modalità JSON non viene aggiunta nessuna nota al testo', false === strpos( $testo, 'TESTO TRONCATO' ) );

$testo = $cliente( 'sempre-tronca' )->genera( 'istruzioni', 'richiesta' );
verifica( 'in modalità testo la nota resta, ed è utile', false !== strpos( $testo, 'TESTO TRONCATO' ) );

if ( is_resource( $mock ) ) {
	proc_terminate( $mock );
	proc_close( $mock );
}

// --- Scelta di cosa far fare al pilota -------------------------------------
// "Genera le immagini mancanti" non deve trascinarsi dietro duecento
// riscritture: erano finite in coda 522 operazioni al posto di 289 immagini.
echo "\nScelta delle operazioni del pilota\n";

$fileDb2 = sys_get_temp_dir() . '/prova-coda-' . getmypid() . '.sqlite';


@unlink( $fileDb2 );
$db2 = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileDb2 ) );

$auditId2 = $db2->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50 )
);

for ( $i = 1; $i <= 3; $i++ ) {
	$doc = $db2->insert(
		'documento',
		array( 'audit_id' => $auditId2, 'wp_id' => (string) $i, 'titolo' => 'Articolo ' . $i, 'percorso' => '/a' . $i . '/', 'tipo' => 'post', 'stato' => 'publish', 'ha_thumbnail' => 0 )
	);

	$db2->insert( 'meta_piano', array( 'audit_id' => $auditId2, 'documento_id' => $doc, 'title_nuovo' => 'T', 'description_nuova' => 'D' ) );
	$db2->insert( 'triage', array( 'audit_id' => $auditId2, 'documento_id' => $doc, 'categoria' => 'riscrivere', 'qualita' => 40, 'redirect_a' => '' ) );
}

// Chiave finta ma presente: senza, il pilota salta tutto quello che passa
// dall AI e la verifica misurerebbe la configurazione di chi la esegue invece
// del comportamento del codice.
$cfgProva = require __DIR__ . '/../config.php';
$cfgProva['ai']['chiave'] = 'chiave-di-prova';

$conteggi = static function ( $db, $id ) {
	$fuori = array();

	foreach ( $db->all( 'SELECT tipo, COUNT(*) n FROM coda WHERE audit_id = ? GROUP BY tipo', array( $id ) ) as $riga ) {
		$fuori[ $riga['tipo'] ] = (int) $riga['n'];
	}

	return $fuori;
};

\SeoGeo\Coda::prepara( $db2, $auditId2, $cfgProva, array( 'includi' => array( 'immagine' ), 'immagini' => true ) );
$solo_immagini = $conteggi( $db2, $auditId2 );

verifica( 'chiedendo le sole immagini si mettono in coda solo quelle', array( 'immagine' ) === array_keys( $solo_immagini ), implode( ', ', array_keys( $solo_immagini ) ) );
verifica( 'e sono tutte quelle che mancano', 3 === ( $solo_immagini['immagine'] ?? 0 ) );

\SeoGeo\Coda::prepara( $db2, $auditId2, $cfgProva, array( 'includi' => array( 'meta' ) ) );
$solo_meta = $conteggi( $db2, $auditId2 );

verifica( 'chiedendo le sole meta non parte nessuna riscrittura', ! isset( $solo_meta['bozza'] ) && ! isset( $solo_meta['immagine'] ) );
verifica( 'e nemmeno i dati aziendali o i redirect', ! isset( $solo_meta['config'] ) && ! isset( $solo_meta['redirect'] ) );

\SeoGeo\Coda::prepara( $db2, $auditId2, $cfgProva, array( 'includi' => array( 'config', 'struttura' ) ) );
$senza_ai = $conteggi( $db2, $auditId2 );

verifica( 'le operazioni gratuite si possono fare da sole', isset( $senza_ai['config'], $senza_ai['redirect'], $senza_ai['categorie'] ) );
verifica( 'senza toccare niente che costi', ! isset( $senza_ai['bozza'] ) && ! isset( $senza_ai['immagine'] ) && ! isset( $senza_ai['meta'] ) );

// Senza indicazioni si comporta come sempre: le chiamate esistenti non cambiano.
\SeoGeo\Coda::prepara( $db2, $auditId2, $cfgProva, array() );
$tutto_come_prima = $conteggi( $db2, $auditId2 );

verifica( 'senza scelta esplicita si fa tutto come prima', isset( $tutto_come_prima['config'], $tutto_come_prima['meta'], $tutto_come_prima['redirect'] ) );
verifica( 'ma le immagini restano fuori se non richieste', ! isset( $tutto_come_prima['immagine'] ) );

// I blocchi delle meta si sono dimezzati: 40 per volta invece di 80.
for ( $i = 4; $i <= 60; $i++ ) {
	$doc = $db2->insert(
		'documento',
		array( 'audit_id' => $auditId2, 'wp_id' => (string) $i, 'titolo' => 'Articolo ' . $i, 'percorso' => '/a' . $i . '/', 'tipo' => 'post', 'stato' => 'publish', 'ha_thumbnail' => 1 )
	);

	$db2->insert( 'meta_piano', array( 'audit_id' => $auditId2, 'documento_id' => $doc, 'title_nuovo' => 'T', 'description_nuova' => 'D' ) );
}

\SeoGeo\Coda::prepara( $db2, $auditId2, $cfgProva, array( 'includi' => array( 'meta' ) ) );
verifica( 'sessanta articoli diventano due blocchi da quaranta', 2 === ( $conteggi( $db2, $auditId2 )['meta'] ?? 0 ) );

// La stima del costo, prima di premere il pulsante.
$stima = \SeoGeo\Coda::stima( $db2, $auditId2, $cfgProva + array( 'ai' => array( 'prezzo_per_milione' => array( 'input' => 0.10, 'output' => 0.40 ) ) ) );

verifica( 'la stima conta gli articoli da riscrivere', 3 === $stima['bozza']['quanti'], $stima['bozza']['quanti'] . '' );
verifica( 'e stima i token in ingresso e in uscita', $stima['bozza']['token_in'] > 0 && $stima['bozza']['token_out'] > 0 );
verifica( 'il costo cresce con i token', $stima['bozza']['costo'] > 0 );
verifica( 'le immagini si contano ma non si prezzano a token', null === $stima['immagine']['costo'] && $stima['immagine']['quanti'] > 0 );

$a_zero = \SeoGeo\Coda::stima( $db2, $auditId2, array( 'ai' => array( 'prezzo_per_milione' => array( 'input' => 0, 'output' => 0 ) ) ) );
verifica( 'con i prezzi a zero il costo è zero, non un numero inventato', 0.0 === $a_zero['bozza']['costo'] );

@unlink( $fileDb2 );

// --- Perché una pagina non si vede -----------------------------------------
echo "\nDiagnosi di una pagina\n";

$cause = new ReflectionMethod( \SeoGeo\Diagnosi::class, 'cause' );
$cause->setAccessible( true );

$diagnosi = static function ( $sito, $google = null ) use ( $cause ) {
	return $cause->invoke( null, array( 'url' => 'https://esempio.it/pagina/', 'sito' => $sito, 'google' => $google, 'nostro' => null ) );
};

$sano = array( 'stato' => 'publish', 'parole' => 800, 'robots' => '', 'canonica' => '', 'slug' => 'pagina', 'password' => false );

verifica( 'una pagina sana non genera allarmi', array() === $diagnosi( $sano ) );

$trovate = static fn( $c, $pezzo ) => array() !== array_filter( $c, static fn( $x ) => false !== stripos( $x['titolo'], $pezzo ) );

verifica( 'il cestino viene riconosciuto', $trovate( $diagnosi( array( 'stato' => 'trash' ) + $sano ), 'cestino' ) );
verifica( 'una bozza non pubblicata viene riconosciuta', $trovate( $diagnosi( array( 'stato' => 'draft' ) + $sano ), 'bozza' ) );
verifica( 'una data futura viene riconosciuta', $trovate( $diagnosi( array( 'stato' => 'future' ) + $sano ), 'programmato' ) );
verifica( 'un contenuto privato viene riconosciuto', $trovate( $diagnosi( array( 'stato' => 'private' ) + $sano ), 'privato' ) );
verifica( 'la password viene riconosciuta', $trovate( $diagnosi( array( 'password' => true ) + $sano ), 'password' ) );
verifica( 'il noindex viene riconosciuto', $trovate( $diagnosi( array( 'robots' => 'noindex,nofollow' ) + $sano ), 'noindex' ) );
verifica( 'un contenuto svuotato viene riconosciuto', $trovate( $diagnosi( array( 'parole' => 12 ) + $sano ), 'quasi vuoto' ) );
verifica(
	'una canonica che punta altrove viene riconosciuta',
	$trovate( $diagnosi( array( 'canonica' => 'https://esempio.it/un-altra/' ) + $sano ), 'un altra pagina' )
);
verifica(
	'una canonica che punta a sé stessa non è un problema',
	! $trovate( $diagnosi( array( 'canonica' => 'https://esempio.it/pagina/' ) + $sano ), 'un altra pagina' )
);

// Il caso vero: due articoli gemelli, Google ne mostra uno solo.
$doppione = $diagnosi(
	$sano,
	array(
		'stato'           => 'NEUTRAL',
		'copertura'       => 'Duplicata: Google ha scelto una pagina canonica diversa da quella specificata dall utente',
		'canonica_google' => 'https://esempio.it/articolo-gemello/',
	)
);

verifica( 'il doppione secondo Google viene riconosciuto', $trovate( $doppione, 'doppione' ) );
verifica( 'e dice quale pagina Google ha scelto al suo posto', false !== strpos( $doppione[0]['spiegazione'], 'articolo-gemello' ) );
verifica( 'e propone di accorpare', false !== stripos( $doppione[0]['rimedio'], 'accorpa' ) );

$non_indicizzata = $diagnosi( $sano, array( 'stato' => 'NEUTRAL', 'copertura' => 'Scansionata, attualmente non indicizzata', 'canonica_google' => 'https://esempio.it/pagina/' ) );
verifica( 'la pagina vista ma non indicizzata viene riconosciuta', $trovate( $non_indicizzata, 'non l ha indicizzata' ) );

$indicizzata = $diagnosi( $sano, array( 'stato' => 'PASS', 'copertura' => 'Inviata e indicizzata', 'canonica_google' => 'https://esempio.it/pagina/' ) );
verifica( 'una pagina indicizzata non genera allarmi', array() === $indicizzata );

// Più problemi insieme: si elencano tutti, dal sito prima e da Google poi.
$molti = $diagnosi(
	array( 'stato' => 'draft', 'parole' => 5, 'robots' => 'noindex', 'canonica' => '', 'slug' => 'pagina', 'password' => true ),
	array( 'stato' => 'NEUTRAL', 'copertura' => 'Scansionata, attualmente non indicizzata', 'canonica_google' => 'https://esempio.it/pagina/' )
);

verifica( 'i problemi vengono elencati tutti, non solo il primo', count( $molti ) >= 4, count( $molti ) . ' cause' );
verifica( 'ogni causa dice anche cosa fare', array() === array_filter( $molti, static fn( $c ) => '' === trim( $c['rimedio'] ) ) );

// --- Pacchetto diagnostico --------------------------------------------------
// Deve contenere la forma vera dei contenuti e nessun dato di persone: è un
// file che l utente manda a qualcun altro.
echo "\nPacchetto diagnostico\n";

$sitoProva = sito_con_meta(
	array(
		'rank_math_title'       => 'Titolo SEO',
		'rank_math_robots'      => array( 'noindex', 'nofollow' ),
		'_elementor_data'       => str_repeat( '{"blocco":"enorme"}', 500 ),
		'_qualche_plugin_segreto' => 'chiave-che-non-deve-uscire',
	)
);

$pacchetto = \SeoGeo\Diagnostica::pacchetto( $sitoProva, array( 'wordpress' => '6.7.1', 'plugin' => '1.6.0', 'rank_math' => true ) );
$testo     = json_encode( $pacchetto );

verifica( 'il pacchetto contiene i contenuti', 1 === count( $pacchetto['contenuti'] ) );
verifica( 'con il testo vero dell articolo', false !== strpos( $pacchetto['contenuti'][0]['contenuto'], 'Testo di prova' ) );
verifica( 'e con le meta SEO che servono alle regole', 'Titolo SEO' === ( $pacchetto['contenuti'][0]['meta']['rank_math_title'] ?? '' ) );
verifica(
	'le meta array restano array, che è la forma che ha rotto le cose',
	array( 'noindex', 'nofollow' ) === ( $pacchetto['contenuti'][0]['meta']['rank_math_robots'] ?? null )
);
verifica( 'di Elementor resta solo il fatto che c è', '1' === ( $pacchetto['contenuti'][0]['meta']['_elementor_data'] ?? '' ) );
verifica( 'le meta di altri plugin non escono', false === strpos( $testo, 'chiave-che-non-deve-uscire' ) );
verifica( 'il pacchetto dice con cosa convive il plugin', '6.7.1' === $pacchetto['ambiente']['wordpress'] );

// Il controllo che conta davvero.
verifica( 'nel pacchetto non finisce nessun indirizzo email', 0 === preg_match( '/[\w.+-]+@[\w-]+\.[\w.]{2,}/', $testo ) );
verifica( 'né la parola password', false === stripos( $testo, 'password' ) );
verifica( 'e non ci sono autori con i loro dati', ! isset( $pacchetto['autori'] ) && false === stripos( $testo, 'autori' ) );

verifica( 'l elenco di cosa resta fuori è mostrato all utente', count( \SeoGeo\Diagnostica::esclusi() ) >= 5 );

// --- Indirizzi cambiati -----------------------------------------------------
// Un contenuto rinominato lascia indietro il vecchio indirizzo, che da quel
// momento dà 404. Nessuno se ne accorgeva.
echo "\nIndirizzi cambiati\n";

$fileDb3 = sys_get_temp_dir() . '/prova-redir-' . getmypid() . '.sqlite';
@unlink( $fileDb3 );
$db3 = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileDb3 ) );

$sito = 'https://esempio.it';
$a1   = $db3->insert( 'audit', array( 'sito_nome' => 'P', 'sito_url' => $sito, 'creato_il' => '2026-09-01 10:00:00', 'punteggio' => 40 ) );
$a2   = $db3->insert( 'audit', array( 'sito_nome' => 'P', 'sito_url' => $sito, 'creato_il' => '2026-09-11 10:00:00', 'punteggio' => 50 ) );

// Stesso contenuto, indirizzo diverso.
$db3->insert( 'documento', array( 'audit_id' => $a1, 'wp_id' => '7388', 'titolo' => 'Video a Palermo', 'percorso' => '/perche-ogni-attivita-video-2', 'tipo' => 'post', 'stato' => 'publish' ) );
$db3->insert( 'documento', array( 'audit_id' => $a2, 'wp_id' => '7388', 'titolo' => 'Video a Palermo', 'percorso' => '/perche-le-attivita-video', 'tipo' => 'post', 'stato' => 'publish' ) );

// Contenuto rimasto fermo.
$db3->insert( 'documento', array( 'audit_id' => $a1, 'wp_id' => '99', 'titolo' => 'Fermo', 'percorso' => '/fermo', 'tipo' => 'post', 'stato' => 'publish' ) );
$db3->insert( 'documento', array( 'audit_id' => $a2, 'wp_id' => '99', 'titolo' => 'Fermo', 'percorso' => '/fermo', 'tipo' => 'post', 'stato' => 'publish' ) );

// Contenuto nuovo, che prima non c era: non è un cambio di indirizzo.
$db3->insert( 'documento', array( 'audit_id' => $a2, 'wp_id' => '500', 'titolo' => 'Nuovo', 'percorso' => '/nuovo', 'tipo' => 'post', 'stato' => 'publish' ) );

$cambiati = \SeoGeo\Redirezioni::cambiati( $db3, $sito );

verifica( 'trova il contenuto che ha cambiato indirizzo', 1 === count( $cambiati ), count( $cambiati ) . ' trovati' );
verifica( 'con il vecchio indirizzo giusto', '/perche-ogni-attivita-video-2' === $cambiati[0]['da'] );
verifica( 'e con il nuovo', '/perche-le-attivita-video' === $cambiati[0]['a'] );
verifica( 'il confronto è per identificativo, non per indirizzo', '7388' === $cambiati[0]['wp_id'] );
verifica( 'un contenuto nuovo non viene scambiato per uno spostato', array() === array_filter( $cambiati, static fn( $c ) => '500' === $c['wp_id'] ) );

// La barra finale non è un cambio di indirizzo.
$a3 = $db3->insert( 'audit', array( 'sito_nome' => 'P', 'sito_url' => 'https://altro.it', 'creato_il' => '2026-09-01 10:00:00', 'punteggio' => 40 ) );
$a4 = $db3->insert( 'audit', array( 'sito_nome' => 'P', 'sito_url' => 'https://altro.it', 'creato_il' => '2026-09-11 10:00:00', 'punteggio' => 40 ) );
$db3->insert( 'documento', array( 'audit_id' => $a3, 'wp_id' => '1', 'titolo' => 'X', 'percorso' => '/pagina/', 'tipo' => 'post', 'stato' => 'publish' ) );
$db3->insert( 'documento', array( 'audit_id' => $a4, 'wp_id' => '1', 'titolo' => 'X', 'percorso' => '/pagina', 'tipo' => 'post', 'stato' => 'publish' ) );

verifica( 'la barra finale non conta come cambio', array() === \SeoGeo\Redirezioni::cambiati( $db3, 'https://altro.it' ) );

// Con una sola analisi non c è niente da confrontare.
$a5 = $db3->insert( 'audit', array( 'sito_nome' => 'P', 'sito_url' => 'https://solo.it', 'creato_il' => '2026-09-11 10:00:00', 'punteggio' => 40 ) );
verifica( 'con una sola analisi non inventa niente', array() === \SeoGeo\Redirezioni::cambiati( $db3, 'https://solo.it' ) );

// Il caso che conta davvero: il contenuto è stato rinominato tre analisi fa e
// da allora l indirizzo nuovo si ripete. Confrontando solo le ultime due non
// risulterebbe niente, ma il vecchio indirizzo è morto lo stesso.
$sito2 = 'https://vecchio.it';
$b1 = $db3->insert( 'audit', array( 'sito_nome' => 'P', 'sito_url' => $sito2, 'creato_il' => '2026-09-01 10:00:00', 'punteggio' => 40 ) );
$b2 = $db3->insert( 'audit', array( 'sito_nome' => 'P', 'sito_url' => $sito2, 'creato_il' => '2026-09-05 10:00:00', 'punteggio' => 40 ) );
$b3 = $db3->insert( 'audit', array( 'sito_nome' => 'P', 'sito_url' => $sito2, 'creato_il' => '2026-09-11 10:00:00', 'punteggio' => 40 ) );

$db3->insert( 'documento', array( 'audit_id' => $b1, 'wp_id' => '7388', 'titolo' => 'Video', 'percorso' => '/vecchio-indirizzo-2', 'tipo' => 'post', 'stato' => 'publish' ) );
$db3->insert( 'documento', array( 'audit_id' => $b2, 'wp_id' => '7388', 'titolo' => 'Video', 'percorso' => '/nuovo-indirizzo', 'tipo' => 'post', 'stato' => 'publish' ) );
$db3->insert( 'documento', array( 'audit_id' => $b3, 'wp_id' => '7388', 'titolo' => 'Video', 'percorso' => '/nuovo-indirizzo', 'tipo' => 'post', 'stato' => 'publish' ) );

$vecchi = \SeoGeo\Redirezioni::cambiati( $db3, $sito2 );

verifica( 'un indirizzo cambiato prima dell ultima analisi viene ancora trovato', 1 === count( $vecchi ), count( $vecchi ) . ' trovati' );
verifica( 'e punta al primo indirizzo mai registrato', '/vecchio-indirizzo-2' === ( $vecchi[0]['da'] ?? '' ) );
verifica( 'verso quello di adesso', '/nuovo-indirizzo' === ( $vecchi[0]['a'] ?? '' ) );

// Quando non trova niente deve dire perché, altrimenti chi guarda non sa se
// il programma ha controllato o no.
$spiegato = \SeoGeo\Redirezioni::confronto( $db3, 'https://solo.it' );
verifica( 'con una sola analisi lo dice', false !== stripos( $spiegato['motivo'], 'seconda analisi' ), $spiegato['motivo'] );

$nessuno = \SeoGeo\Redirezioni::confronto( $db3, 'https://altro.it' );
verifica( 'senza cambi dice che non è cambiato niente', false !== stripos( $nessuno['motivo'], 'Nessun indirizzo' ), $nessuno['motivo'] );
verifica( 'e dice su quanti contenuti ha guardato', $nessuno['confrontati'] > 0 );
verifica( 'e quali due analisi ha messo a confronto', ! empty( $nessuno['prima'] ) && ! empty( $nessuno['ultima'] ) );

// Il dominio con e senza barra finale è lo stesso sito.
$con_barra = \SeoGeo\Redirezioni::confronto( $db3, 'https://vecchio.it/' );
verifica( 'la barra finale nell indirizzo del sito non fa fallire il confronto', 1 === count( $con_barra['cambiati'] ) );

$con_www = \SeoGeo\Redirezioni::confronto( $db3, 'https://www.vecchio.it' );
verifica( 'e nemmeno il www', 1 === count( $con_www['cambiati'] ) );

@unlink( $fileDb3 );

// --- Title tagliati a metà --------------------------------------------------
// Sul sito vero erano quattordici, e li aveva scritti questo programma.
echo "\nTitle tagliati a metà\n";

$cfgT = require __DIR__ . '/../config.php';

$monchi = array(
	'Web agency a Palermo: La Guida Completa per Far Crescere la tua attività commerciale',
	'Digital Agency a Palermo: La Soluzione Completa per la Tua azienda che cresce',
	'Piano di Marketing: Guida Completa per il Successo del Tuo progetto aziendale',
);

$tagliati = 0;

foreach ( $monchi as $titolo ) {
	list( $title ) = \SeoGeo\Fix\Meta::title(
		array( 'titolo' => $titolo, 'slug' => 'x', 'testo' => str_repeat( 'testo di prova ', 60 ), 'seo_title' => '', 'seo_desc' => '', 'primo_paragrafo' => '', 'estratto' => '', 'focus' => '' ),
		$cfgT
	);

	if ( preg_match( '/\b(per|la|il|del|della|tuo|tua|i|le|un|una|di|da|con)\s*$/i', $title ) ) {
		$tagliati++;
	}

	if ( mb_strlen( $title ) > $cfgT['seo']['titleMax'] ) {
		$tagliati++;
	}
}

verifica( 'nessun title esce monco o troppo lungo', 0 === $tagliati, $tagliati . ' difettosi' );

// Il difetto vero era a monte: un title della lunghezza giusta ma tagliato
// veniva accettato così com era, e restava in pagina.
list( $rigenerato, $cambiato ) = \SeoGeo\Fix\Meta::title(
	array(
		'titolo'    => 'Digital Agency a Palermo: la soluzione completa per la tua azienda',
		'slug'      => 'digital-agency-palermo',
		'testo'     => str_repeat( 'agenzia digitale a Palermo che segue le aziende ', 40 ),
		'seo_title' => 'Digital Agency a Palermo: La Soluzione Completa per la Tua',
		'seo_desc'  => '', 'primo_paragrafo' => '', 'estratto' => '',
		'focus'     => 'digital agency palermo',
	),
	$cfgT
);

verifica( 'un title già in pagina ma tagliato viene rifatto', $cambiato );
verifica( 'e quello rifatto non è più monco', false === stripos( $rigenerato, 'per la Tua' ), $rigenerato );

// La parola chiave dev essere dentro al title, altrimenti viene rifatto per
// un motivo diverso: qui si sta verificando solo il taglio.
$buono = 'Agenzia web Palermo: siti che portano clienti veri';

list( $intatto, $toccato ) = \SeoGeo\Fix\Meta::title(
	array(
		'titolo'    => $buono,
		'slug'      => 'agenzia-web-palermo',
		'testo'     => str_repeat( 'realizziamo siti web a Palermo per le aziende ', 40 ),
		'seo_title' => $buono,
		'seo_desc'  => '', 'primo_paragrafo' => '', 'estratto' => '',
		'focus'     => 'agenzia web palermo',
	),
	$cfgT
);

verifica( 'un title già buono resta com è', ! $toccato && $buono === $intatto, $intatto );

verifica(
	'un possessivo finale viene tolto come una preposizione',
	'Marketing a Palermo: Strategie per' !== \SeoGeo\Text::polishClause( 'Marketing a Palermo: Strategie per la Tua' )
		&& false === stripos( \SeoGeo\Text::polishClause( 'Marketing a Palermo: Strategie per la Tua' ), 'per la Tua' )
);

verifica(
	'un titolo ben formato non viene toccato',
	'Agenzia web a Palermo: siti che portano clienti' === \SeoGeo\Text::polishClause( 'Agenzia web a Palermo: siti che portano clienti' )
);

echo "\n" . ( $errori ? "✖ $errori verifiche fallite\n\n" : "✔ tutte le verifiche superate\n\n" );

exit( $errori ? 1 : 0 );
