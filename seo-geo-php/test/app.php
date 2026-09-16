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
 * Regole che l audit dichiara correggibili in automatico ma per cui nessuno
 * sa dire dove si clicchi.
 *
 * @param array $rimedi Mappa dei rimedi.
 * @return string[]
 */
function regole_auto_senza_rimedio( array $rimedi ) {
	$fuori = array();

	foreach ( glob( __DIR__ . '/../src/Rules/*.php' ) as $file ) {
		$sorgente = file_get_contents( $file );

		preg_match_all(
			"/'id'\s*=>\s*'([A-Z]{3}-[0-9]+)'(.*?)(?='id'\s*=>\s*'[A-Z]{3}-|\z)/s",
			$sorgente,
			$trovate,
			PREG_SET_ORDER
		);

		foreach ( $trovate as $regola ) {
			if ( preg_match( "/'auto'\s*=>\s*true/", $regola[2] ) && ! isset( $rimedi[ $regola[1] ] ) ) {
				$fuori[] = $regola[1];
			}
		}
	}

	return $fuori;
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

// Il caso trovato in produzione: 3 articoli su 43 falliti con "Risposta non
// in formato JSON: { "titolo": ... "meta_description": "Cerca un'ag", cioe un
// JSON valido tagliato a meta senza MAX_TOKENS. Gemini aveva spezzato la
// risposta su piu parti e ne leggevamo solo la prima.
// Se torna a leggere una parte sola qui esplode: si raccoglie l errore,
// cosi la suite dice quali verifiche non passano invece di morire.
try {
	$aPezzi = $cliente( 'a-pezzi' )->generaJson( 'istruzioni', 'richiesta' );
} catch ( Throwable $e ) {
	$aPezzi = array( 'errore' => $e->getMessage() );
}

verifica( 'una risposta spezzata su piu parti viene ricomposta', isset( $aPezzi['corpo_html'] ) );
verifica( 'e arriva intera fino all ultimo campo', 'x' === ( $aPezzi['note'] ?? '' ), var_export( $aPezzi['note'] ?? null, true ) );
verifica(
	'il ragionamento non finisce dentro alla risposta',
	false === strpos( (string) ( $aPezzi['titolo'] ?? '' ), 'ragionando' ),
	(string) ( $aPezzi['titolo'] ?? '' )
);

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

echo "\nQuali contenuti vengono spediti al sito\n";

// Il pulsante "Applica" e il pilota automatico devono toccare esattamente gli
// stessi contenuti. Finche il pulsante non filtrava, "prova su 5" prendeva i
// cinque articoli piu lunghi - quasi mai fra quelli da correggere - e il sito
// rispondeva "5 aggiornati" riscrivendo cinque volte gli stessi valori.

$fileSped = sys_get_temp_dir() . '/seo-spedizione-' . getmypid() . '.sqlite';
@unlink( $fileSped );
$dbS = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileSped ) );

$auditS = $dbS->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50 )
);

// Dieci articoli. I due da correggere sono i piu corti, cosi l ordinamento
// per lunghezza (quello vero del pulsante) non li mette per primi.
foreach ( range( 1, 10 ) as $i ) {
	$cambia = in_array( $i, array( 4, 9 ), true );

	$doc = $dbS->insert(
		'documento',
		array(
			'audit_id'     => $auditS,
			'wp_id'        => (string) ( 100 + $i ),
			'titolo'       => 'Articolo ' . $i,
			'percorso'     => '/a' . $i . '/',
			'tipo'         => 'post',
			'stato'        => 'publish',
			'parole'       => $cambia ? 100 : 5000,
			'seo_title'    => $cambia ? 'Titolo tagliato a' : 'Titolo gia a posto ' . $i,
			'seo_description' => 'Descrizione ' . $i,
		)
	);

	$dbS->insert(
		'meta_piano',
		array(
			'audit_id'          => $auditS,
			'documento_id'      => $doc,
			'title_nuovo'       => $cambia ? 'Titolo rifatto per bene ' . $i : 'Titolo gia a posto ' . $i,
			'description_nuova' => 'Descrizione ' . $i,
		)
	);
}

verifica(
	'il conto dice quanti articoli cambiano davvero',
	2 === \SeoGeo\Coda::metaDaCambiare( $dbS, $auditS, 'post' ),
	(string) \SeoGeo\Coda::metaDaCambiare( $dbS, $auditS, 'post' )
);

// La query del pulsante, identica a quella di public/index.php.
$queryPulsante = static function ( $limite ) use ( $dbS, $auditS ) {
	return $dbS->all(
		'SELECT d.wp_id AS id, m.title_nuovo AS title
		 FROM meta_piano m JOIN documento d ON d.id = m.documento_id
		 WHERE m.audit_id = ? AND d.tipo = ?' . \SeoGeo\Coda::soloDaCambiare()
		 . ' ORDER BY d.tipo DESC, d.parole DESC' . ( $limite ? ' LIMIT ' . (int) $limite : '' ),
		array( $auditS, 'post' )
	);
};

$tutti = $queryPulsante( 0 );

verifica(
	'"applica a tutti" spedisce solo quelli da correggere, non tutti e dieci',
	2 === count( $tutti ),
	count( $tutti ) . ' spediti'
);

$idsSpediti = array_map( static fn( $r ) => (int) $r['id'], $tutti );
sort( $idsSpediti );

verifica(
	'e sono proprio quei due',
	array( 104, 109 ) === $idsSpediti,
	implode( ', ', $idsSpediti )
);

$cinque = $queryPulsante( 5 );

verifica(
	'"prova su 5" pesca fra quelli da correggere, non fra gli articoli piu lunghi',
	count( $cinque ) > 0 && count( $cinque ) <= 2,
	count( $cinque ) . ' spediti'
);

$idsCinque = array_map( static fn( $r ) => (int) $r['id'], $cinque );

verifica(
	'e nessun articolo gia a posto finisce nella prova',
	array() === array_diff( $idsCinque, array( 104, 109 ) ),
	implode( ', ', $idsCinque )
);

// Il numero scritto nella pagina e quello che viene spedito devono venire
// dallo stesso conto: altrimenti si legge 18 e se ne aggiornano 311.
verifica(
	'il numero mostrato e il numero spedito coincidono',
	\SeoGeo\Coda::metaDaCambiare( $dbS, $auditS, 'post' ) === count( $tutti )
);

verifica(
	'le pagine restano fuori dal conto degli articoli',
	0 === \SeoGeo\Coda::metaDaCambiare( $dbS, $auditS, 'page' )
);

@unlink( $fileSped );

echo "\nImmagini generate: peso e formato\n";

// Il modello restituisce PNG da qualche megabyte. Caricati com erano
// risolvevano IMG-05 ma facevano scattare IMG-03 (oltre 200 KB) e IMG-04
// (formato non moderno): sul sito vero l audit e passato da 42 a 304
// immagini pesanti dopo 262 immagini generate.

$fotoFinta = static function ( $larghezza, $altezza, $grana ) {
	mt_srand( 7 );
	$im = imagecreatetruecolor( $larghezza, $altezza );

	for ( $x = 0; $x < $larghezza; $x++ ) {
		for ( $y = 0; $y < $altezza; $y++ ) {
			$b = (int) ( 128 + 100 * sin( $x / 70 ) * cos( $y / 90 ) + mt_rand( -$grana, $grana ) );
			$b = max( 0, min( 255, $b ) );
			imagesetpixel( $im, $x, $y, imagecolorallocate( $im, $b, (int) ( $b * 0.85 ), (int) ( $b * 0.7 ) ) );
		}
	}

	ob_start();
	imagepng( $im );
	$png = (string) ob_get_clean();
	imagedestroy( $im );

	return $png;
};

if ( ! function_exists( 'imagewebp' ) ) {
	echo "  · GD senza WebP su questa macchina: verifiche sul peso saltate\n";
} else {
	$cfgImg = require __DIR__ . '/../config.php';

	foreach ( array(
		array( 'una fotografia normale', 1536, 1024, 8 ),
		array( 'una fotografia granulosa', 1536, 1024, 28 ),
		array( 'una immagine quadrata grande', 2048, 2048, 30 ),
	) as $caso ) {
		list( $nome, $w, $h, $g ) = $caso;

		$png = $fotoFinta( $w, $h, $g );
		list( $mimeImg, $uscita ) = \SeoGeo\Ai\Immagini::ottimizza( $png, 'image/png', $cfgImg );

		verifica(
			"$nome resta sotto i 200 KB (IMG-03)",
			strlen( $uscita ) < 204800,
			round( strlen( $uscita ) / 1024 ) . ' KB, partiva da ' . round( strlen( $png ) / 1024 ) . ' KB'
		);

		verifica(
			"$nome esce in WebP (IMG-04)",
			'image/webp' === $mimeImg,
			$mimeImg
		);

		$dimensioni = getimagesizefromstring( $uscita );

		verifica(
			"$nome non supera il lato lungo richiesto",
			max( $dimensioni[0], $dimensioni[1] ) <= (int) $cfgImg['ai']['immagine_lato_max'],
			$dimensioni[0] . 'x' . $dimensioni[1]
		);
	}

	// Meglio un immagine pesante che nessuna immagine: se il risultato non
	// migliora niente si tiene quello che c era.
	$minuscola = $fotoFinta( 40, 40, 4 );
	list( $mimeMin, $uscitaMin ) = \SeoGeo\Ai\Immagini::ottimizza( $minuscola, 'image/png', $cfgImg );

	verifica(
		'una immagine che non si puo migliorare torna com era',
		strlen( $uscitaMin ) <= strlen( $minuscola )
	);

	verifica(
		'e comunque non torna mai vuota',
		strlen( $uscitaMin ) > 0
	);
}

// Il controllo "gia generata" cercava solo il .png: passando al WebP avrebbe
// rigenerato tutto da capo, pagando una seconda volta lo stesso lavoro.
$cartellaImg = sys_get_temp_dir() . '/seo-immagini-' . getmypid();
@mkdir( $cartellaImg, 0775, true );

$fileImg = sys_get_temp_dir() . '/seo-img-' . getmypid() . '.sqlite';
@unlink( $fileImg );
$dbI = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileImg ) );

$auditI = $dbI->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50 )
);

foreach ( array( 'gia-fatta-webp', 'gia-fatta-png', 'da-fare' ) as $slug ) {
	$dbI->insert(
		'documento',
		array(
			'audit_id' => $auditI, 'wp_id' => (string) crc32( $slug ), 'titolo' => $slug, 'slug' => $slug,
			'percorso' => '/' . $slug . '/', 'url' => 'https://esempio.it/' . $slug . '/',
			'tipo' => 'post', 'stato' => 'publish', 'ha_thumbnail' => 0, 'parole' => 600,
		)
	);
}

file_put_contents( $cartellaImg . '/gia-fatta-webp.webp', 'x' );
file_put_contents( $cartellaImg . '/gia-fatta-png.png', 'x' );

$restanti = \SeoGeo\Ai\Immagini::candidati( $dbI, $auditI, array( 'cartella' => $cartellaImg ) );
$slugRestanti = array_map( static fn( $r ) => $r['slug'], $restanti );

verifica(
	'una immagine gia generata in WebP non viene rifatta',
	! in_array( 'gia-fatta-webp', $slugRestanti, true ),
	implode( ', ', $slugRestanti )
);

verifica(
	'ne quella gia generata in PNG',
	! in_array( 'gia-fatta-png', $slugRestanti, true )
);

verifica(
	'ma quella che manca resta da fare',
	array( 'da-fare' ) === $slugRestanti,
	implode( ', ', $slugRestanti )
);

array_map( 'unlink', glob( $cartellaImg . '/*' ) );
@rmdir( $cartellaImg );
@unlink( $fileImg );

echo "\nVersione del plugin da scaricare\n";

// Lo zip del plugin veniva costruito una volta sola, quando si faceva
// l analisi. Aggiornando il gestionale restava in archivio quello vecchio e
// il pulsante continuava a servirlo: si installava una versione diversa da
// quella nel gestionale, senza che niente lo dicesse.

$versioneOra = \SeoGeo\Export::versionePlugin();

verifica(
	'la versione del plugin installato si legge',
	(bool) preg_match( '/^\d+\.\d+/', $versioneOra ),
	$versioneOra
);

$sorgentePlugin = __DIR__ . '/../plugin-wordpress/mdi-seo-geo-booster/mdi-seo-geo-booster.php';

verifica(
	'e coincide con quella scritta nel file del plugin',
	false !== strpos( (string) file_get_contents( $sorgentePlugin ), 'Version:           ' . $versioneOra )
);

if ( ! class_exists( '\ZipArchive' ) ) {
	echo "  · ZipArchive assente su questa macchina: verifica sullo zip saltata\n";
} else {
	$cartellaZip = sys_get_temp_dir() . '/seo-plugin-' . getmypid();
	@mkdir( $cartellaZip . '/plugin-data', 0775, true );

	$esitoZip = \SeoGeo\Export::plugin( array( 'cartella' => $cartellaZip ), $cartellaZip . '/plugin-data' );

	verifica( 'lo zip del plugin viene creato', ! empty( $esitoZip['zip'] ) && is_file( (string) $esitoZip['zip'] ) );

	verifica(
		'e dentro c e la stessa versione del gestionale',
		$versioneOra === \SeoGeo\Export::versioneNelloZip( (string) $esitoZip['zip'] ),
		\SeoGeo\Export::versioneNelloZip( (string) $esitoZip['zip'] )
	);

	// La verifica che conta: uno zip vecchio deve essere riconosciuto come
	// tale, perche e da li che parte la ricostruzione al download.
	verifica(
		'uno zip che non esiste non spaccia una versione',
		'' === \SeoGeo\Export::versioneNelloZip( $cartellaZip . '/mai-esistito.zip' )
	);

	$archivio = new ZipArchive();
	$archivio->open( (string) $esitoZip['zip'] );
	$dentro = (string) $archivio->getFromName( 'mdi-seo-geo-booster/includes/class-mdi-api.php' );
	$archivio->close();

	foreach ( array( 'immagini-pesanti', 'comprimi-immagine', 'ripristina-immagine' ) as $rotta ) {
		verifica( "lo zip contiene la rotta $rotta", false !== strpos( $dentro, "/$rotta" ) );
	}

	$rimuovi = static function ( $cartella ) use ( &$rimuovi ) {
		foreach ( (array) glob( $cartella . '/*' ) as $voce ) {
			is_dir( $voce ) ? $rimuovi( $voce ) : unlink( $voce );
		}

		@rmdir( $cartella );
	};

	$rimuovi( $cartellaZip );
}

echo "\nCompressione che va avanti da sola\n";

// Il lavoro lungo non puo stare in una richiesta sola: gli hosting
// condivisi la chiudono. Ma nemmeno si puo chiedere a una persona di
// premere venti volte lo stesso pulsante. La soluzione e il browser che
// richiama a giri corti, e queste verifiche guardano che il ciclo finisca
// e soprattutto che non possa girare a vuoto per sempre.

$sorgenteIndice = (string) file_get_contents( __DIR__ . '/../public/index.php' );

verifica(
	'esiste la rotta che fa un giro di compressione',
	false !== strpos( $sorgenteIndice, "'api-comprimi' === \$pagina" )
);

verifica(
	'e chiede il token di sessione',
	(bool) preg_match( "/'api-comprimi'.{0,400}hash_equals\( token\(\)/s", $sorgenteIndice )
);

// Un giro che non conclude niente deve chiudere il ciclo: se un errore si
// ripetesse, il browser continuerebbe a chiamare all infinito.
verifica(
	'un giro che non fa avanzare niente ferma il ciclo',
	false !== strpos( $sorgenteIndice, "0 === (int) \$esito['compresse'] + (int) \$esito['gia_fatte'] + (int) \$esito['invariate']" )
);

// Sul sito vero il ciclo si e fermato con 172 immagini ancora da fare
// perche un blocco intero aveva trovato solo immagini gia fatte, e quelle
// contavano come nulla. Sono avanzamento: senza questo, un archivio
// lavorato a meta blocca tutto il resto.
verifica(
	'le immagini gia fatte contano come avanzamento',
	false !== strpos( $sorgenteIndice, "'gia_fatte'   => (int) \$esito['gia_fatte']" )
);

$sorgenteCompressione = (string) file_get_contents( __DIR__ . '/../src/Media/Compressione.php' );

verifica(
	'una gia fatta non finisce fra i guasti',
	false !== strpos( $sorgenteCompressione, "'gia_fatta' === ( \$esito['motivo'] ?? '' )" )
);

verifica(
	'e nemmeno con un plugin vecchio che risponde ancora con un errore',
	false !== strpos( $sorgenteCompressione, "stripos( \$e->getMessage(), 'gia stata ricompressa' )" )
);

verifica(
	'e un errore chiude il ciclo invece di farlo ripartire',
	(bool) preg_match( "/catch \( Throwable \\\$e \) \{.{0,200}'finito' => true/s", $sorgenteIndice )
);

$sorgenteVista = (string) file_get_contents( __DIR__ . '/../views/collega.php' );

verifica(
	'senza JavaScript il pulsante resta un modulo normale',
	false !== strpos( $sorgenteVista, 'if (!modulo || !corso) { return; }' )
);

verifica(
	'si puo fermare a meta',
	false !== strpos( $sorgenteVista, "compressione-stop" ) && false !== strpos( $sorgenteVista, 'fermato = true' )
);

// Una barra di avanzamento puo restare ferma quaranta secondi mentre il
// blocco e in corso, e sembra bloccata. Serve qualcosa che si muova ogni
// secondo, altrimenti non si distingue il lavoro in corso da un guasto.
verifica(
	'c e un cronometro che sale ogni secondo',
	false !== strpos( $sorgenteVista, 'setInterval(battito, 1000)' )
);

verifica(
	'e una spia che si spegne quando si ferma',
	false !== strpos( $sorgenteVista, 'function spegni(classe, testo)' )
		&& false !== strpos( $sorgenteVista, "spegni('guasto'" )
		&& (bool) preg_match( "/spegni\(.*'fermo'/", $sorgenteVista )
		&& false !== strpos( $sorgenteVista, 'clearInterval(orologio)' )
);

verifica(
	'la spia esiste anche nel foglio di stile',
	false !== strpos( (string) file_get_contents( __DIR__ . '/../public/assets/app.css' ), '.spia' )
);

echo "\nLotti di riscrittura che vanno avanti da soli\n";

$sorgenteBozze = (string) file_get_contents( __DIR__ . '/../views/bozze.php' );

verifica(
	'esiste la rotta che fa un giro di generazione',
	false !== strpos( $sorgenteIndice, "'api-bozze' === \$pagina" )
);

verifica(
	'e chiede il token di sessione',
	(bool) preg_match( "/'api-bozze'.{0,400}hash_equals\( token\(\)/s", $sorgenteIndice )
);

// Qui il ciclo costa soldi a ogni giro: se non sapesse fermarsi
// continuerebbe a chiamare il modello a vuoto, a pagamento.
verifica(
	'un giro che non conclude niente ferma il ciclo',
	false !== strpos( $sorgenteIndice, "\$dopo >= \$prima_di && empty( \$esito['interrotto'] )" )
);

// Il difetto trovato sul sito vero: con il modo "migliora" il prompt e piu
// grande e ogni articolo piu lento, cosi un giro finiva il tempo prima di
// completare anche un solo contenuto. Veniva letto come "bloccato" e il
// ciclo si fermava scrivendo "Fermata" con 187 bozze ancora da fare.
verifica(
	'ma un giro finito per tempo non e bloccato',
	false !== strpos( $sorgenteIndice, "empty( \$esito['interrotto'] ) )" )
);

// Ogni pulsante che lavora a lotti deve passare dallo stesso ciclo: e quello
// che tiene il cronometro, riprova e sa dire quanti ne restano.
$quantiLotti = substr_count( $sorgenteBozze, 'class="scheda a-lotti"' ) + substr_count( $sorgenteBozze, 'class="a-lotti ' );

verifica( 'i lotti usano tutti lo stesso ciclo', $quantiLotti >= 4, (string) $quantiLotti );

verifica(
	'ognuno dichiara che cosa lavora e quanti ne restano',
	$quantiLotti === substr_count( $sorgenteBozze, 'data-tipo=' ) && $quantiLotti === substr_count( $sorgenteBozze, 'data-restanti=' ),
	$quantiLotti . ' moduli, ' . substr_count( $sorgenteBozze, 'data-tipo=' ) . ' data-tipo'
);

// Il pulsante che avvia deve stare dentro al riquadro della regola: stava in
// una scheda piu in basso, e chi arrivava dalla tabella dei problemi leggeva
// l elenco dei contenuti e concludeva che non c era modo di partire.
verifica(
	'il pulsante per avviare sta nel riquadro della regola',
	false !== strpos( $sorgenteBozze, 'Genera le bozze per <?php echo e( $regola ); ?>' ),
	'il pulsante non e nel riquadro'
);

verifica(
	'e se manca la chiave si dice perche il pulsante non c e',
	false !== strpos( $sorgenteBozze, "Il pulsante per avviare non c'è perché manca la chiave" ),
	'manca la spiegazione'
);

verifica(
	'c e il cronometro anche qui',
	false !== strpos( $sorgenteBozze, 'setInterval(battito, 1000)' )
);

verifica(
	'senza fetch i moduli restano quelli di prima',
	false !== strpos( $sorgenteBozze, 'if (!moduli.length || !window.fetch) { return; }' )
);

// I gruppi gia fusi non devono ripresentarsi: senza questo, premere il
// pulsante una seconda volta rifaceva i primi della lista e si pagava due
// volte lo stesso lavoro, senza mai arrivare in fondo.
$fileGruppi = sys_get_temp_dir() . '/seo-gruppi-' . getmypid() . '.sqlite';
@unlink( $fileGruppi );
$dbG = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileGruppi ) );

$auditG = $dbG->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50 )
);

$vincitore = $dbG->insert(
	'documento',
	array( 'audit_id' => $auditG, 'wp_id' => '1', 'titolo' => 'Principale', 'slug' => 'principale', 'percorso' => '/principale/', 'url' => 'https://esempio.it/principale/', 'tipo' => 'post', 'stato' => 'publish', 'parole' => 900, 'testo' => 'Testo principale.' )
);

foreach ( array( 2, 3 ) as $n ) {
	$assorbito = $dbG->insert(
		'documento',
		array( 'audit_id' => $auditG, 'wp_id' => (string) $n, 'titolo' => 'Doppione ' . $n, 'slug' => 'doppione-' . $n, 'percorso' => '/doppione-' . $n . '/', 'url' => 'https://esempio.it/doppione-' . $n . '/', 'tipo' => 'post', 'stato' => 'publish', 'parole' => 400, 'testo' => 'Testo doppione.' )
	);

	$dbG->insert( 'triage', array( 'audit_id' => $auditG, 'documento_id' => $assorbito, 'categoria' => 'accorpare', 'qualita' => 30, 'redirect_a' => 'https://esempio.it/principale/' ) );
}

verifica( 'il gruppo da fondere viene trovato', 1 === count( \SeoGeo\Ai\Rewriter::gruppi( $dbG, $auditG ) ) );

$dbG->insert(
	'bozza',
	array( 'audit_id' => $auditG, 'documento_id' => $vincitore, 'stato' => 'ok', 'titolo' => 'Fuso', 'corpo_html' => '<p>x</p>', 'faq' => '[]', 'da_verificare' => '[]', 'meta_title' => 'T', 'meta_description' => 'D', 'modello' => 'prova', 'creato_il' => date( 'Y-m-d H:i:s' ) )
);

verifica(
	'una volta fuso non si ripresenta',
	0 === count( \SeoGeo\Ai\Rewriter::gruppi( $dbG, $auditG ) )
);

verifica(
	'ma si puo rifare chiedendolo espressamente',
	1 === count( \SeoGeo\Ai\Rewriter::gruppi( $dbG, $auditG, array( 'rigenera' => 1 ) ) )
);

@unlink( $fileGruppi );

echo "\nDati cercati su Google per i segnaposto\n";

// Si riusa il finto servizio gia avviato piu sopra, passando l indirizzo
// nella configurazione come fanno le altre verifiche: la variabile d
// ambiente GEMINI_ENDPOINT ha la precedenza su tutto e scavalcherebbe i
// finti servizi delle prove vicine.
$cfgCerca = require __DIR__ . '/../config.php';
$cfgCerca['ai']['chiave'] = 'chiave-di-prova';

$cercatore = static function ( $modo ) use ( $base ) {
	return new \SeoGeo\Ai\Gemini(
		array( 'chiave' => 'prova', 'endpoint' => sprintf( $base, $modo ), 'modello' => 'gemini-2.5-flash', 'tentativi' => 1 )
	);
};

{
	$trovato = \SeoGeo\Ai\Verifiche::cercaValore( $cercatore( 'completo' ), 'costo minimo entry level', $cfgCerca );

	verifica( 'la ricerca restituisce un valore', '' !== $trovato['valore'], $trovato['valore'] . ' ' . ( $trovato['motivo'] ?? '' ) );
	verifica( 'con le fonti da cui viene', count( $trovato['fonti'] ) >= 1, (string) count( $trovato['fonti'] ) );
	verifica( 'senza ripetere due volte la stessa fonte', 2 === count( $trovato['fonti'] ), (string) count( $trovato['fonti'] ) );
	verifica( 'e ogni fonte ha un indirizzo', '' !== ( $trovato['fonti'][0]['url'] ?? '' ), (string) ( $trovato['fonti'][0]['url'] ?? '' ) );

	// La regola che conta: un dato di mercato non puo comparire come se
	// fosse il listino dell agenzia.
	verifica( 'un dato di mercato viene riconosciuto come tale', 'mercato' === ( $trovato['tipo'] ?? '' ), (string) ( $trovato['tipo'] ?? '' ) );
	verifica(
		'e in pagina si scrive che e una media, non il nostro prezzo',
		false !== stripos( \SeoGeo\Ai\Verifiche::comeScriverlo( $trovato ), 'non il nostro listino' ),
		\SeoGeo\Ai\Verifiche::comeScriverlo( $trovato )
	);

	// Senza fonti non si scrive niente: e la sola garanzia che il numero
	// non sia inventato.
	$senzaFonti = \SeoGeo\Ai\Verifiche::cercaValore( $cercatore( 'senza-fonti' ), 'costo minimo entry level', $cfgCerca );
	verifica( 'senza fonti il valore non viene usato', '' === $senzaFonti['valore'], $senzaFonti['valore'] );
	verifica( 'e viene detto perche', false !== stripos( $senzaFonti['motivo'], 'verificabil' ), $senzaFonti['motivo'] );

	$nonTrovato = \SeoGeo\Ai\Verifiche::cercaValore( $cercatore( 'non-trovato' ), 'quanti clienti abbiamo', $cfgCerca );
	verifica( 'quando le fonti non sanno, il buco resta', '' === $nonTrovato['valore'] );
	verifica( 'e lo dice', false !== stripos( $nonTrovato['motivo'], 'fonti' ), $nonTrovato['motivo'] );
}

// Raggruppamento e sostituzione: non dipendono dal modello.
$fileVer = sys_get_temp_dir() . '/seo-verifiche-' . getmypid() . '.sqlite';
@unlink( $fileVer );
$dbV = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileVer ) );

$auditV = $dbV->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50 )
);

foreach ( array( 1, 2, 3 ) as $n ) {
	$doc = $dbV->insert(
		'documento',
		array( 'audit_id' => $auditV, 'wp_id' => (string) $n, 'titolo' => 'Articolo ' . $n, 'slug' => 'a' . $n, 'percorso' => '/a' . $n . '/', 'url' => 'https://esempio.it/a' . $n . '/', 'tipo' => 'post', 'stato' => 'publish', 'parole' => 700 )
	);

	$dbV->insert(
		'bozza',
		array(
			'audit_id' => $auditV, 'documento_id' => $doc, 'stato' => 'ok', 'modello' => 'prova',
			'titolo' => 'Bozza ' . $n,
			// Scritte in modi diversi apposta: devono contare come una sola.
			'corpo_html' => '<p>Si parte da [DA VERIFICARE: costo minimo entry level] fino a [DA VERIFICARE:  Costo Minimo Entry Level ].</p>',
			'in_breve' => 'Da [DA VERIFICARE: numero prodotti standard] prodotti.',
			'meta_description' => 'Prezzi da [DA VERIFICARE: costo minimo entry level].',
			'faq' => '[]', 'da_verificare' => '[]', 'creato_il' => date( 'Y-m-d H:i:s' ),
		)
	);
}

$gruppiSegnaposto = \SeoGeo\Ai\Verifiche::segnaposto( $dbV, $auditV );

verifica( 'i segnaposto distinti sono due, non nove', 2 === count( $gruppiSegnaposto ), (string) count( $gruppiSegnaposto ) );

$chiaviSegnaposto = array_keys( $gruppiSegnaposto );

verifica( 'maiuscole e spazi non creano un segnaposto diverso', in_array( 'costo minimo entry level', $chiaviSegnaposto, true ), implode( ' | ', $chiaviSegnaposto ) );
verifica( 'il piu frequente viene per primo', 'costo minimo entry level' === $chiaviSegnaposto[0], $chiaviSegnaposto[0] );
verifica( 'e si sa quante volte compare', 9 === $gruppiSegnaposto['costo minimo entry level']['quante'], (string) $gruppiSegnaposto['costo minimo entry level']['quante'] );
verifica( 'e in quante bozze', 3 === count( $gruppiSegnaposto['costo minimo entry level']['bozze'] ) );

// Si compila solo quello risolto: l altro resta buco, come deve.
$esitoApplica = \SeoGeo\Ai\Verifiche::applica(
	$dbV,
	$auditV,
	array( 'costo minimo entry level' => array( 'valore' => 'da 1.500 a 4.000 euro', 'tipo' => 'mercato' ) )
);

verifica( 'le bozze toccate sono tre', 3 === $esitoApplica['bozze'], (string) $esitoApplica['bozze'] );
verifica( 'i segnaposto chiusi sono nove', 9 === $esitoApplica['segnaposto'], (string) $esitoApplica['segnaposto'] );

$dopoApplica = $dbV->one( "SELECT corpo_html, in_breve FROM bozza WHERE audit_id = ?", array( $auditV ) );

verifica( 'nel testo compare il valore trovato', false !== strpos( $dopoApplica['corpo_html'], '1.500' ), $dopoApplica['corpo_html'] );
verifica( 'con l avvertenza che e una media di mercato', false !== stripos( $dopoApplica['corpo_html'], 'non il nostro listino' ) );
verifica( 'e il segnaposto non risolto resta al suo posto', false !== strpos( $dopoApplica['in_breve'], '[DA VERIFICARE' ), $dopoApplica['in_breve'] );

$restano = \SeoGeo\Ai\Verifiche::segnaposto( $dbV, $auditV );

verifica( 'dopo la compilazione ne resta uno solo da risolvere', 1 === count( $restano ), implode( ' | ', array_keys( $restano ) ) );

@unlink( $fileVer );

echo "\nMigliorare invece di riscrivere da capo\n";

$fileMig = sys_get_temp_dir() . '/seo-migliora-' . getmypid() . '.sqlite';
@unlink( $fileMig );
$dbM = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileMig ) );

$auditM = $dbM->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50 )
);

$docM = array(
	'titolo'   => 'Come scegliere un e-commerce',
	'url'      => 'https://esempio.it/ecommerce/',
	'percorso' => '/ecommerce/',
	'focus'    => 'e-commerce palermo',
	'intento'  => 'commerciale',
	'parole'   => 900,
	'testo'    => 'Testo originale dell articolo, con un esempio vero e una cifra gia verificata.',
);

$rilievoM = $dbM->insert(
	'rilievo',
	array( 'audit_id' => $auditM, 'regola' => 'STR-02', 'area' => 'struttura', 'gravita' => 'alto', 'titolo' => 'Manca il blocco di sintesi iniziale', 'perche' => 'x', 'soluzione' => 'y', 'automatico' => 1, 'occorrenze' => 1 )
);
$dbM->insert( 'occorrenza', array( 'rilievo_id' => $rilievoM, 'riferimento' => 'https://esempio.it/ecommerce/', 'dettaglio' => 'nessun paragrafo di risposta nei primi 60 termini' ) );

$rilievoBasso = $dbM->insert(
	'rilievo',
	array( 'audit_id' => $auditM, 'regola' => 'CNT-09', 'area' => 'content', 'gravita' => 'basso', 'titolo' => 'Titoletti che ripetono il titolo', 'perche' => 'x', 'soluzione' => 'y', 'automatico' => 1, 'occorrenze' => 1 )
);
$dbM->insert( 'occorrenza', array( 'rilievo_id' => $rilievoBasso, 'riferimento' => '/ecommerce/', 'dettaglio' => '3 titoletti ripetono il titolo' ) );

// Un rilievo che si risolve nel <head> e non nel testo: dirlo a chi scrive
// l articolo non serve, e in produzione ha fatto danni — il modello, davanti
// a «manca lo schema Article», ha scritto il JSON-LD dentro all articolo e
// quello e finito in pagina come testo.
$rilievoSchema = $dbM->insert(
	'rilievo',
	array( 'audit_id' => $auditM, 'regola' => 'SCH-01', 'area' => 'structured', 'gravita' => 'alto', 'titolo' => 'Manca lo schema Article', 'perche' => 'x', 'soluzione' => 'y', 'automatico' => 1, 'occorrenze' => 1 )
);
$dbM->insert( 'occorrenza', array( 'rilievo_id' => $rilievoSchema, 'riferimento' => 'https://esempio.it/ecommerce/', 'dettaglio' => 'nessun JSON-LD Article' ) );

$problemiM = \SeoGeo\Ai\Rewriter::problemi( $dbM, $auditM, $docM );

verifica( 'i problemi della pagina vengono ritrovati', 2 === count( $problemiM ), (string) count( $problemiM ) );
verifica( 'per indirizzo completo e per percorso', 'STR-02' === $problemiM[0]['regola'] && 'CNT-09' === $problemiM[1]['regola'] );
verifica(
	'i problemi che risolve il plugin non arrivano a chi scrive il testo',
	! in_array( 'SCH-01', array_column( $problemiM, 'regola' ), true ),
	implode( ',', array_column( $problemiM, 'regola' ) )
);
verifica( 'e i piu gravi vengono per primi', 'alto' === $problemiM[0]['gravita'], $problemiM[0]['gravita'] );

$problemiAltrove = \SeoGeo\Ai\Rewriter::problemi( $dbM, $auditM, array( 'url' => 'https://esempio.it/altra/', 'percorso' => '/altra/' ) );
verifica( 'e quelli di un altra pagina non si mescolano', 0 === count( $problemiAltrove ) );

$cfgMig = require __DIR__ . '/../config.php';

$promptMigliora = \SeoGeo\Ai\Prompt::miglioramento( $docM, $docM, array(), $problemiM, $cfgMig );

verifica( 'il prompt dice di non riscrivere da capo', false !== stripos( $promptMigliora, 'NON riscriverlo da capo' ) );
verifica( 'e passa i problemi veri trovati sulla pagina', false !== strpos( $promptMigliora, 'STR-02' ) && false !== strpos( $promptMigliora, 'nessun paragrafo di risposta' ) );
verifica( 'vieta di cancellare quello che va bene', false !== stripos( $promptMigliora, 'Non cancellare sezioni che non hanno problemi' ) );

// La regola che protegge dai danni: cifre e nomi gia scritti dall autore
// non si toccano, perche il modello non li puo verificare.
verifica( 'e di cambiare cifre e nomi gia presenti', false !== stripos( $promptMigliora, 'Non cambiare affermazioni, cifre, nomi o date' ) );
verifica( 'il testo originale ci arriva intero', false !== strpos( $promptMigliora, 'una cifra gia verificata' ) );
verifica( 'e segue lo stesso schema per intento di ricerca', false !== stripos( $promptMigliora, 'SCALETTA DI RIFERIMENTO' ) );

$promptRiscrivi = \SeoGeo\Ai\Prompt::articolo( $docM, $docM, array(), $cfgMig );

verifica( 'il modo riscrittura resta disponibile e diverso', false !== stripos( $promptRiscrivi, 'Riscrivi questo articolo' ) && false === stripos( $promptRiscrivi, 'NON riscriverlo da capo' ) );

verifica(
	'il valore predefinito e migliorare, non rifare',
	! empty( $cfgMig['ai']['migliora_invece_di_riscrivere'] )
);

// La vista delle bozze usa gia $verifiche dentro al ciclo: passando la
// sezione con lo stesso nome veniva sovrascritta e non compariva mai.
$vistaBozze = (string) file_get_contents( __DIR__ . '/../views/bozze.php' );

verifica(
	'la sezione dei dati da verificare non usa un nome gia occupato',
	false !== strpos( $vistaBozze, '$segnaposto_aperti' )
		&& false === strpos( $vistaBozze, 'empty( $verifiche )' )
);

verifica(
	'e il gestionale gliela passa con quel nome',
	false !== strpos( $sorgenteIndice, "'segnaposto_aperti' => Verifiche::segnaposto" )
);

verifica(
	'la rotta che cerca i dati esiste e chiede il token',
	false !== strpos( $sorgenteIndice, "'api-verifiche' === \$pagina" )
		&& (bool) preg_match( "/'api-verifiche'.{0,400}hash_equals\( token\(\)/s", $sorgenteIndice )
);

// Anche qui il ciclo costa: se un giro non risolve niente, ritentare le
// stesse etichette spenderebbe soldi per lo stesso risultato.
verifica(
	'un giro che non risolve niente ferma il ciclo',
	false !== strpos( $sorgenteIndice, "0 === \$restano || 0 === count( \$risolti )" )
);

echo "\nFermarsi per tempo non e un guasto\n";

// Sullo schermo compariva in rosso "Saltate: tempo massimo raggiunto", e un
// giro andato benissimo sembrava rotto. Fermarsi al tetto di tempo e il
// funzionamento normale su hosting condiviso.
$sorgenteRiscrittura = (string) file_get_contents( __DIR__ . '/../src/Ai/Rewriter.php' );
$sorgenteImmagini    = (string) file_get_contents( __DIR__ . '/../src/Ai/Immagini.php' );

verifica(
	'il tetto di tempo non finisce piu fra gli errori',
	false === strpos( $sorgenteRiscrittura, "\$errori[] = 'tempo massimo raggiunto" )
);

verifica(
	'ma viene comunque riferito a chi chiama',
	2 === substr_count( $sorgenteRiscrittura, "'interrotto' => \$interrotto" )
		&& false !== strpos( $sorgenteImmagini, "'interrotto' => \$interrotto" )
);

verifica(
	'e la pagina lo scrive come lavoro in corso',
	false !== strpos( $sorgenteIndice, "'per_tempo' => ! empty( \$esito['interrotto'] )" )
		&& false !== strpos( $sorgenteBozze, 'il blocco si è chiuso al limite di tempo, continuo' )
);

verifica(
	'gli errori veri restano separati',
	false !== strpos( $sorgenteBozze, "'Non riuscite: '" )
);

// Con duecento contenuti la differenza fra dieci minuti e due ore cambia
// quello che uno decide di fare: la stima si misura sul ritmo vero.
verifica(
	'e dice quanto manca, misurato sul ritmo vero',
	false !== strpos( $sorgenteBozze, 'function stima()' )
		&& false !== strpos( $sorgenteBozze, '(Date.now() - avvio) / fatte' )
);

@unlink( $fileMig );

echo "\nChe versione ho installato\n";

// Senza un numero visibile, "hai gia aggiornato?" si puo solo indovinare -
// e indovinare male fa rifare lavoro gia fatto, o peggio fa credere fatto
// un aggiornamento che non c e.
verifica(
	'il gestionale dichiara una versione',
	(bool) preg_match( '/^\d+\.\d+\.\d+$/', \SeoGeo\Versione::NUMERO ),
	\SeoGeo\Versione::NUMERO
);

verifica(
	'e la stessa del plugin, cosi si confrontano',
	\SeoGeo\Versione::NUMERO === \SeoGeo\Export::versionePlugin(),
	\SeoGeo\Versione::NUMERO . ' contro ' . \SeoGeo\Export::versionePlugin()
);

verifica(
	'e si vede in fondo a ogni pagina',
	false !== strpos( (string) file_get_contents( __DIR__ . '/../views/layout.php' ), 'Versione::NUMERO' )
);

echo "\nNote del modello e stato delle bozze\n";

// Nell elenco delle bozze compariva la parola "Array" al posto delle note.
// Nel modo "migliora" si chiede un elenco di cosa e stato cambiato, e il
// modello risponde con un elenco vero: salvato con un cast a stringa
// diventava "Array".
verifica(
	'un elenco di note diventa una riga leggibile',
	'Aggiunta la sintesi · Sistemati i titoli' === \SeoGeo\Ai\Rewriter::note( array( 'Aggiunta la sintesi', 'Sistemati i titoli' ) ),
	\SeoGeo\Ai\Rewriter::note( array( 'Aggiunta la sintesi', 'Sistemati i titoli' ) )
);

verifica(
	'e non compare mai la parola Array',
	false === strpos( \SeoGeo\Ai\Rewriter::note( array( 'una', array( 'annidata', 'dentro' ) ) ), 'Array' ),
	\SeoGeo\Ai\Rewriter::note( array( 'una', array( 'annidata', 'dentro' ) ) )
);

verifica( 'una nota normale resta com era', 'Ho sistemato i titoli.' === \SeoGeo\Ai\Rewriter::note( 'Ho sistemato i titoli.' ) );
verifica( 'le voci vuote non lasciano separatori a vuoto', 'sola' === \SeoGeo\Ai\Rewriter::note( array( '', 'sola', '  ' ) ) );

$sorgenteRiscritturaNote = (string) file_get_contents( __DIR__ . '/../src/Ai/Rewriter.php' );

verifica(
	'le note passano sempre da li, in tutte e due le strade',
	2 === substr_count( $sorgenteRiscritturaNote, "self::note( \$dati['note'] ?? '' )" )
);

// Una riga di errore che resta accanto a una bozza riuscita racconta una
// cosa che non e piu vera.
verifica(
	'una bozza riuscita toglie l errore di prima',
	2 === substr_count( $sorgenteRiscritturaNote, "DELETE FROM bozza WHERE audit_id = ? AND documento_id = ? AND stato <> 'ok'" )
);

$vistaBozzeStato = (string) file_get_contents( __DIR__ . '/../views/bozze.php' );

verifica(
	'l elenco distingue le bozze gia scritte sul sito',
	false !== strpos( $vistaBozzeStato, "\$online = 'ok' === \$b['stato'] && ! empty( \$b['inviata_il'] )" )
		&& false !== strpos( $vistaBozzeStato, 'scritta sul sito' )
);

echo "\nTesto leggibile e titoletti riempiti di parole chiave\n";

// Nel confronto vecchio/nuovo il testo del sito arrivava tutto su una riga:
// il titoletto incollato al paragrafo faceva sembrare rotto anche un
// articolo scritto bene.
$htmlProva = '<h2>Perche i video contano</h2><p>Primo paragrafo.</p><h2>Come si gira</h2><p>Secondo paragrafo.</p>';

verifica(
	'i blocchi restano separati',
	3 === substr_count( \SeoGeo\Html::testo( $htmlProva ), "\n\n" ),
	str_replace( "\n", '\\n', \SeoGeo\Html::testo( $htmlProva ) )
);

verifica(
	'il titoletto non si incolla al paragrafo',
	false === strpos( \SeoGeo\Html::testo( $htmlProva ), 'contano Primo' )
);

verifica( 'e il testo c e tutto', false !== strpos( \SeoGeo\Html::testo( $htmlProva ), 'Secondo paragrafo' ) );
verifica( 'le parole si contano tutte', 11 === \SeoGeo\Text::wordCount( \SeoGeo\Html::testo( $htmlProva ) ), (string) \SeoGeo\Text::wordCount( \SeoGeo\Html::testo( $htmlProva ) ) );

// La regola nuova: il titolo intero ripetuto dentro ai titoletti. Sui dati
// veri del sito sono 28 articoli su 311, i peggiori con 7 titoletti uguali.
$regole = array();

foreach ( \SeoGeo\Rules\Content::rules() as $r ) {
	$regole[ $r['id'] ] = $r;
}

verifica( 'la regola esiste', isset( $regole['CNT-09'] ) );

// Sito vero, non finto: cosi la prova esercita anche l estrazione dei
// titoletti dall HTML, che e la parte che potrebbe sbagliare.
$sitoConTitoletti = static function ( $titolo, $html ) {
	return new Site(
		array(
			'sito'      => array( 'titolo' => 'Prova', 'link' => 'https://esempio.it', 'baseUrl' => 'https://esempio.it', 'autori' => array() ),
			'categorie' => array(),
			'tag'       => array(),
			'items'     => array(
				array(
					'wp_id' => '1', 'tipo' => 'post', 'stato' => 'publish', 'titolo' => $titolo,
					'slug' => 'a', 'link' => 'https://esempio.it/a/', 'data' => '2026-01-01 10:00:00',
					'modificato' => '2026-02-01 10:00:00', 'autore' => 'Redazione',
					'contenuto' => $html, 'estratto' => '', 'categorie' => array(), 'tag' => array(),
					'commenti' => 'closed', 'genitore' => '0', 'meta' => array(),
				),
			),
		)
	);
};

$titoloLungo = 'Produzione video Palermo: perche oggi e indispensabile per ogni azienda';

$riempito = $sitoConTitoletti(
	$titoloLungo,
	'<h2>Cos e la ' . $titoloLungo . '</h2><p>Uno.</p>'
	. '<h2>I vantaggi della ' . $titoloLungo . '</h2><p>Due.</p>'
	. '<h2>Come si gira un video</h2><p>Tre.</p>'
);

$trovati = call_user_func( $regole['CNT-09']['check'], $riempito );

verifica( 'segnala l articolo con i titoletti riempiti', 1 === count( $trovati ), (string) count( $trovati ) );
verifica( 'e dice quanti sono', false !== strpos( (string) ( $trovati[0]['dettaglio'] ?? '' ), '2 titoletti' ), (string) ( $trovati[0]['dettaglio'] ?? '' ) );

$unoSolo = $sitoConTitoletti(
	$titoloLungo,
	'<h2>Cos e la ' . $titoloLungo . '</h2><p>Uno.</p><h2>Come si gira un video</h2><p>Due.</p>'
);

verifica( 'un titoletto solo non basta a segnalare', 0 === count( call_user_func( $regole['CNT-09']['check'], $unoSolo ) ) );

// Un titolo corto puo ricomparire senza che sia riempimento.
$corto = $sitoConTitoletti( 'Video aziendali', '<h2>Video aziendali oggi</h2><p>Uno.</p><h2>Perche i Video aziendali</h2><p>Due.</p>' );

verifica( 'un titolo corto non fa scattare la regola', 0 === count( call_user_func( $regole['CNT-09']['check'], $corto ) ) );

// L H1 e il titolo dell articolo: non deve contare contro se stesso.
$conH1 = $sitoConTitoletti(
	$titoloLungo,
	'<h1>' . $titoloLungo . '</h1><h2>Cos e la ' . $titoloLungo . '</h2><p>Uno.</p>'
	. '<h2>I vantaggi della ' . $titoloLungo . '</h2><p>Due.</p>'
);

$conH1Trovati = call_user_func( $regole['CNT-09']['check'], $conH1 );

// "17 non sovrascrivibili" da solo fa chiedere perche: il motivo c e gia
// sotto a ogni contenuto, ma scorrere duecento righe per contarli a mano
// non e un modo di saperlo.
$vistaConfronto = (string) file_get_contents( __DIR__ . '/../views/confronto-bozze.php' );

verifica(
	'il riepilogo dice perche non si possono sovrascrivere',
	false !== strpos( $vistaConfronto, 'vanno fatti a mano:' )
		&& false !== strpos( $vistaConfronto, 'con la struttura di Elementor illeggibile' )
);

// Non basta poterlo fare: prima di premere si deve sapere che cosa
// succedera, e adesso che gli altri blocchi non si svuotano piu va detto
// che un pezzo di testo vecchio puo restare in pagina sotto al nuovo.
verifica(
	'e dice in anticipo che cosa fara su ogni contenuto',
	false !== strpos( $vistaConfronto, '$cosa_fara' )
		&& false !== strpos( $vistaConfronto, 'ne verrà aggiunto uno in fondo' )
		&& false !== strpos( $vistaConfronto, 'gli altri non si toccano' )
);

// Sapere che diciassette non si possono fare non serve se poi vanno
// cercati a mano in mezzo a duecento.
verifica(
	'si possono vedere solo quelli da fare a mano',
	false !== strpos( $vistaConfronto, "'a-mano'" )
		&& false !== strpos( $vistaConfronto, "'a-mano' === \$filtro" )
		&& false !== strpos( $vistaConfronto, 'class="filtri"' )
);

verifica(
	'le viste sono quattro e contano quante righe hanno',
	false !== strpos( $vistaConfronto, "'Da inviare', count( \$da_inviare )" )
		&& false !== strpos( $vistaConfronto, "'Già online', count( \$gia_online )" )
		&& false !== strpos( $vistaConfronto, "'Da fare a mano', count( \$bloccate )" )
);

verifica(
	'il gestionale accetta solo le viste previste',
	false !== strpos( $sorgenteIndice, "array( 'da-inviare', 'online', 'a-mano', 'da-completare', 'da-ripulire' ), true )" )
);

verifica(
	'e distingue anche gli altri costruttori e le strutture illeggibili',
	false !== strpos( $vistaConfronto, "'costruiti con '" )
		&& false !== strpos( $vistaConfronto, 'con la struttura di Elementor illeggibile' )
);

verifica(
	'l H1 non viene contato',
	false !== strpos( (string) ( $conH1Trovati[0]['dettaglio'] ?? '' ), '2 titoletti' ),
	(string) ( $conH1Trovati[0]['dettaglio'] ?? '' )
);

// --- Dove si risolve ogni problema ----------------------------------------
//
// In produzione la tabella dei problemi diceva «Correzione: automatica» e
// non offriva niente da premere: l elenco in mano e nessun posto dove
// andare. Queste verifiche tengono insieme le tre cose che devono
// combaciare — la regola dell audit, il rimedio e la pagina che lo esegue.

$rimedi = \SeoGeo\Rimedi::mappa( 7 );

verifica(
	'ogni regola che l audit dichiara automatica sa dove si risolve',
	array() === ( $senzaRimedio = regole_auto_senza_rimedio( $rimedi ) ),
	implode( ', ', $senzaRimedio )
);

$destinazioni = array();
foreach ( $rimedi as $regola => $r ) {
	if ( 'azione' === $r['come'] ) {
		$destinazioni[ $r['dove'] ][] = $regola;
	}
}

$ancoreMancanti = array();
foreach ( $destinazioni as $dove => $regole ) {
	$pezzi  = explode( '#', $dove );
	$pagina = array();
	parse_str( ltrim( $pezzi[0], '?' ), $pagina );
	$vista  = __DIR__ . '/../views/' . ( $pagina['p'] ?? '' ) . '.php';

	if ( ! is_file( $vista ) ) {
		$ancoreMancanti[] = $dove . ' (vista assente)';
		continue;
	}

	if ( isset( $pezzi[1] ) && false === strpos( file_get_contents( $vista ), 'id="' . $pezzi[1] . '"' ) ) {
		$ancoreMancanti[] = $dove . ' (ancora assente)';
	}
}

verifica(
	'ogni pulsante di correzione porta a una sezione che esiste davvero',
	array() === $ancoreMancanti,
	implode( ', ', $ancoreMancanti )
);

$vistaAudit = file_get_contents( __DIR__ . '/../views/audit.php' );

verifica(
	'la colonna «Correzione» non si limita più a dire «automatica»',
	false === strpos( $vistaAudit, ">automatica</span>" )
		&& false !== strpos( $vistaAudit, '\'azione\' === ( $rim[\'come\'] ?? \'\' )' )
);

verifica(
	'il punteggio per area dice dove si corregge',
	false !== strpos( $vistaAudit, 'Correggi da' )
		&& false !== strpos( $vistaAudit, '$perArea[ $a[' )
);

verifica(
	'i file di correzione dicono che non applicano niente da soli',
	false !== strpos( $vistaAudit, 'Non è da qui che si correggono i problemi' )
);

$finti = array(
	array( 'regola' => 'SCH-01', 'area' => 'structured', 'occorrenze' => 30 ),
	array( 'regola' => 'ONP-01', 'area' => 'onpage', 'occorrenze' => 12 ),
	array( 'regola' => 'ONP-10', 'area' => 'onpage', 'occorrenze' => 90 ),
	array( 'regola' => 'TEC-08', 'area' => 'technical', 'occorrenze' => 4 ),
);

$conti = \SeoGeo\Rimedi::riassunto( $finti, 7 );

verifica(
	'il riassunto separa plugin, pulsante e lavoro a mano',
	1 === $conti['plugin'] && 2 === $conti['azione'] && 1 === $conti['manuale'],
	json_encode( $conti )
);

$aree = \SeoGeo\Rimedi::perArea( $finti, 7 );

verifica(
	'per ogni area si propone il rimedio che tocca più contenuti',
	false !== strpos( (string) $aree['onpage']['dove'], 'bozze' ),
	(string) ( $aree['onpage']['dove'] ?? '' )
);

verifica(
	'un area senza rimedi guidati non inventa un collegamento',
	'' === $aree['technical']['dove'] && 1 === $aree['technical']['manuale']
);

// --- Lo schema non deve finire dentro all articolo ------------------------
//
// In produzione un articolo e andato online con il JSON-LD stampato in mezzo
// al testo, segnaposto compresi. WordPress toglie il tag <script> ma tiene
// quello che c e dentro: in pagina resta un muro di graffe.

echo "\nLo schema resta fuori dal testo\n";

$conSchema = '<p>Testo prima.</p>' . "\n\n"
	. '{ "@context": "https://schema.org", "@graph": [ { "@type": "Article", "headline": "Web Agency { a }", '
	. '"image": { "@type": "ImageObject", "url": "[DA VERIFICARE: URL dell immagine principale]" } } ] }' . "\n\n"
	. '<p>Testo dopo.</p>';

$ripulito = \SeoGeo\Html::senzaDatiStrutturati( $conSchema );

verifica( 'il JSON-LD nudo sparisce dal corpo', false === strpos( $ripulito, '@context' ), $ripulito );
verifica( 'il testo prima e dopo resta', false !== strpos( $ripulito, 'Testo prima' ) && false !== strpos( $ripulito, 'Testo dopo' ) );

verifica(
	'e sparisce anche quando e dentro a uno <script>',
	false === strpos(
		\SeoGeo\Html::senzaDatiStrutturati( '<p>a</p><script type="application/ld+json">{"@context":"x"}</script><p>b</p>' ),
		'@context'
	)
);

verifica(
	'un oggetto qualsiasi nel testo non si tocca',
	false !== strpos( \SeoGeo\Html::senzaDatiStrutturati( '<p>Scrivi {"chiave": 1} nel file.</p>' ), '"chiave"' )
);

verifica(
	'graffe non bilanciate non mangiano il resto della pagina',
	false !== strpos(
		\SeoGeo\Html::senzaDatiStrutturati( '{ "@context": "x", "a": [ <p>coda</p>' ),
		'coda'
	)
);

$indiceSorgente = file_get_contents( __DIR__ . '/../public/index.php' );

verifica(
	'si ripulisce anche al momento di inviare, non solo quando si genera',
	false !== strpos( $indiceSorgente, 'Html::senzaDatiStrutturati( (string) $riga[' )
);

verifica(
	'il prompt vieta di scrivere dati strutturati nel corpo',
	false !== strpos( \SeoGeo\Ai\Prompt::miglioramento( $docM, $docM, array(), $problemiM, $cfgMig ), 'Non scrivere dati strutturati' )
);

// --- Segnaposto: non si pubblica un articolo con dentro [DA VERIFICARE] ---

$conBuchi = array(
	'corpo_html'       => '<p>Costa [DA VERIFICARE: prezzo medio] e dura [DA VERIFICARE: tempi].</p>',
	'in_breve'         => '',
	'meta_description' => '',
	'titolo'           => '',
	'faq'              => '',
);

$buchi = \SeoGeo\Ai\Verifiche::restano( $conBuchi );

verifica( 'i segnaposto rimasti si contano', 2 === count( $buchi ), implode( ' | ', $buchi ) );
verifica( 'e si dice quali sono', in_array( 'prezzo medio', $buchi, true ), implode( ' | ', $buchi ) );

verifica(
	'una bozza senza segnaposto non viene fermata',
	array() === \SeoGeo\Ai\Verifiche::restano( array( 'corpo_html' => '<p>Tutto compilato.</p>' ) )
);

verifica(
	'anche i segnaposto nelle domande frequenti fermano l invio',
	array() !== \SeoGeo\Ai\Verifiche::restano( array( 'faq' => '[{"risposta":"[DA VERIFICARE: orari]"}]' ) )
);

// Chi pubblica decide: i segnaposto rimasti non fermano l invio. Restano
// segnalati, e i due pulsanti che li chiudono restano dove sono.
verifica(
	'i segnaposto non bloccano piu l invio',
	false === strpos( $indiceSorgente, 'Mancano ancora dei dati da verificare' )
);

$vistaConfronto2 = file_get_contents( __DIR__ . '/../views/confronto-bozze.php' );

verifica(
	'e quelle bozze sono dentro a «Sovrascrivi tutte» come le altre',
	false === strpos( $vistaConfronto2, "&& ! \$mancanti( \$r )" )
);

verifica(
	'ma in pagina si vede quali ne hanno ancora',
	false !== strpos( $vistaConfronto2, '$da_completare' )
		&& false !== strpos( $vistaConfronto2, 'Dati da verificare,' )
);

verifica(
	'e il pulsante per sovrascrivere c e su tutte',
	false !== strpos( $vistaConfronto2, 'if ( $pronto && $scrivibile( $riga ) ) : ?>' )
);

verifica(
	'si puo rimettere il testo di prima su un articolo solo',
	false !== strpos( $indiceSorgente, "'api-ripristina' === \$pagina" )
		&& false !== strpos( $vistaConfronto2, 'ripristina-una' )
);

// --- I segnaposto dentro allo schema non contano ---------------------------
//
// La bozza rotta aveva i suoi [DA VERIFICARE] dentro al JSON-LD. Ripulito il
// corpo, spariscono insieme a quello: contarli lo stesso teneva fuori
// dall invio una bozza che era gia a posto, e in pagina il conto scendeva da
// 17 a 16 senza che si capisse perche.

$bozzaConSchema = array(
	'corpo_html' => '<p>Testo buono.</p>'
		. '{ "@context": "https://schema.org", "@type": "Article", "datePublished": "[DA VERIFICARE: data di pubblicazione]" }'
		. '<p>Altro testo buono.</p>',
);

verifica(
	'sul testo grezzo i segnaposto dello schema si vedono ancora',
	array() !== \SeoGeo\Ai\Verifiche::restano( $bozzaConSchema )
);

verifica(
	'ma su quello che parte davvero non ci sono piu',
	array() === \SeoGeo\Ai\Verifiche::restano(
		array( 'corpo_html' => \SeoGeo\Html::senzaDatiStrutturati( $bozzaConSchema['corpo_html'] ) )
	)
);

$vistaConfronto3 = file_get_contents( __DIR__ . '/../views/confronto-bozze.php' );

verifica(
	'e la pagina decide sul testo ripulito, non su quello in archivio',
	false !== strpos( $vistaConfronto3, "Verifiche::restano( array( 'corpo_html' => \$ripulito( \$riga ) )" )
);

verifica(
	'gli articoli gia online con lo schema nel testo si ritrovano',
	false !== strpos( $vistaConfronto3, '$da_ripulire' )
		&& false !== strpos( $vistaConfronto3, "'da-ripulire'   => array( 'Da ripulire sul sito'" )
);

verifica(
	'e il pulsante in blocco li rimanda davvero',
	false !== strpos( $vistaConfronto3, '.confronto:not([hidden])' )
		&& false === strpos( $vistaConfronto3, "'Sovrascrivi questo articolo' === b.textContent" )
);

verifica(
	'si puo cercare un articolo per titolo o indirizzo',
	false !== strpos( $vistaConfronto3, 'cerca-bozza' )
		&& false !== strpos( $vistaConfronto3, 'data-cerca=' )
);

// --- L ultimo passo: girare le frasi rimaste col buco ----------------------
//
// Dopo la ricerca online qualche segnaposto resta sempre: ci sono dati che
// nessuna fonte pubblica ha. Senza questo passo quelle bozze restavano
// bloccate per sempre e l unica via d uscita era compilarle a mano — che e
// esattamente quello che non si voleva fare.

echo "\nFrasi girate quando il dato non si trova\n";

$testoConBuco = '<p>Un sito parte da [DA VERIFICARE: prezzo minimo] euro. Il lavoro dura poco.</p>'
	. '<p>Seguiamo [DA VERIFICARE: numero clienti] clienti.</p>';

$frasiBuco = \SeoGeo\Ai\Verifiche::frasiCon( $testoConBuco );

verifica( 'si isolano le frasi col buco, non i paragrafi interi', 2 === count( $frasiBuco ), implode( ' || ', $frasiBuco ) );
verifica(
	'la frase accanto resta fuori',
	false === strpos( $frasiBuco[0], 'Il lavoro dura poco' ),
	$frasiBuco[0]
);
verifica( 'e i tag non vengono inghiottiti', false === strpos( $frasiBuco[0], '<p>' ), $frasiBuco[0] );

$girate = \SeoGeo\Ai\Verifiche::riscriviFrasi( $cercatore( 'frasi' ), $frasiBuco, $cfgCerca );

verifica( 'il modello rimanda indietro una frase per ognuna', 2 === count( $girate ), (string) count( $girate ) );
verifica(
	'e nessuna ha ancora il segnaposto dentro',
	0 === count( array_filter( $girate, static fn( $f ) => false !== strpos( $f, 'DA VERIFICARE' ) ) )
);

// Il modello che non fa il lavoro e rimanda indietro il segnaposto: quella
// frase non si scrive, o il buco finisce in pagina lo stesso.
$pigre = \SeoGeo\Ai\Verifiche::riscriviFrasi( $cercatore( 'frasi-pigre' ), array( 'Costa [DA VERIFICARE: prezzo medio].' ), $cfgCerca );

verifica( 'una frase che torna col buco viene scartata', array() === $pigre, json_encode( $pigre ) );

// Applicazione sulla bozza vera.
$docB = $dbV->insert(
	'documento',
	array( 'audit_id' => $auditV, 'wp_id' => '9', 'titolo' => 'Articolo 9', 'slug' => 'a9', 'percorso' => '/a9/', 'url' => 'https://esempio.it/a9/', 'tipo' => 'post', 'stato' => 'publish', 'parole' => 700 )
);

$bozzaB = $dbV->insert(
	'bozza',
	array(
		'audit_id' => $auditV, 'documento_id' => $docB, 'stato' => 'ok', 'modello' => 'prova',
		'titolo' => 'Bozza bloccata',
		'corpo_html' => $testoConBuco,
		'in_breve' => 'Sintesi senza buchi.',
		'meta_description' => 'Description senza buchi.',
		'faq' => '[]', 'da_verificare' => '[]', 'creato_il' => date( 'Y-m-d H:i:s' ),
	)
);

$aperteB = \SeoGeo\Ai\Verifiche::bozzeAperte( $dbV, $auditV );

verifica( 'le bozze ancora bloccate si elencano', ! empty( $aperteB ), (string) count( $aperteB ) );

$laMia = array_values( array_filter( $aperteB, static fn( $b ) => (int) $b['id'] === (int) $bozzaB ) );

verifica( 'compresa quella appena inserita', 1 === count( $laMia ) );

$sistemate = \SeoGeo\Ai\Verifiche::applicaFrasi( $dbV, $laMia[0], $girate );

verifica( 'le frasi girate entrano nella bozza', $sistemate >= 1, (string) $sistemate );

$dopoB = $dbV->one( 'SELECT * FROM bozza WHERE id = ?', array( $bozzaB ) );

verifica( 'e nella bozza non resta nessun buco', array() === \SeoGeo\Ai\Verifiche::restano( $dopoB ), $dopoB['corpo_html'] );
verifica( 'la frase senza buco non e stata toccata', false !== strpos( (string) $dopoB['corpo_html'], 'Il lavoro dura poco' ), $dopoB['corpo_html'] );
verifica( 'i tag del paragrafo sono ancora al loro posto', false !== strpos( (string) $dopoB['corpo_html'], '</p>' ), $dopoB['corpo_html'] );
verifica(
	'e quella bozza non risulta piu bloccata',
	0 === count( array_filter( \SeoGeo\Ai\Verifiche::bozzeAperte( $dbV, $auditV ), static fn( $b ) => (int) $b['id'] === (int) $bozzaB ) )
);

verifica(
	'il gestionale ha il giro che le sistema in blocco',
	false !== strpos( $indiceSorgente, "'api-senza-dato' === \$pagina" )
		&& false !== strpos( $indiceSorgente, 'Verifiche::riscriviFrasi(' )
);

verifica(
	'e la pagina delle bozze lo offre con il conto davanti',
	false !== strpos( file_get_contents( __DIR__ . '/../views/bozze.php' ), 'Gira le frasi senza il dato mancante' )
		&& false !== strpos( $indiceSorgente, "'bozze_bloccate'" )
);

// --- Il conto delle immagini deve scendere quando le carichi ---------------
//
// In produzione la pagina diceva «8 mancanti» anche dopo averle caricate
// tutte e otto: il numero legge il database dell analisi, e caricare
// un immagine non lo cambiava. L unico modo di aggiornarlo era rifare
// l analisi intera.

echo "\nImmagini in evidenza: il conto si aggiorna\n";

$fileImg = sys_get_temp_dir() . '/seo-immagini-' . getmypid() . '.sqlite';
@unlink( $fileImg );
$dbI = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileImg ) );

$auditI = $dbI->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50 )
);

foreach ( array( 401, 402, 403 ) as $n ) {
	$dbI->insert(
		'documento',
		array(
			'audit_id' => $auditI, 'wp_id' => (string) $n, 'titolo' => 'Articolo ' . $n, 'slug' => 'i' . $n,
			'percorso' => '/i' . $n . '/', 'url' => 'https://esempio.it/i' . $n . '/', 'tipo' => 'post',
			'stato' => 'publish', 'parole' => 700, 'ha_thumbnail' => 0,
		)
	);
}

$quanteMancano = static function () use ( $dbI, $auditI ) {
	return (int) $dbI->one(
		"SELECT COUNT(*) n FROM documento WHERE audit_id = ? AND ha_thumbnail = 0 AND tipo = 'post'",
		array( $auditI )
	)['n'];
};

verifica( 'si parte da tre senza immagine', 3 === $quanteMancano(), (string) $quanteMancano() );

// Un sito che dice: due ce l hanno, uno no.
$sitoFinto = new class() extends \SeoGeo\Bridge\WordPress {
	/** @var int Quante volte e stata chiesta la rotta breve. */
	public $chiamate = 0;

	public function __construct() {}

	public function pronto() { return true; }

	public function miniature( array $ids ) {
		$this->chiamate++;

		return array( 'miniature' => array( '401' => true, '402' => true, '403' => false ) );
	}
};

$esitoRic = \SeoGeo\Ai\Immagini::ricontrolla( $dbI, $sitoFinto, $auditI );

verifica( 'si chiede al sito solo per quelli che risultano senza', 3 === $esitoRic['controllati'], (string) $esitoRic['controllati'] );
verifica( 'e chi ce l ha viene segnato', 2 === $esitoRic['sistemati'], (string) $esitoRic['sistemati'] );
verifica( 'il conto in pagina scende', 1 === $quanteMancano(), (string) $quanteMancano() );
verifica( 'e dice quanti ne restano', 1 === $esitoRic['restano'], (string) $esitoRic['restano'] );

// Rifarlo non cambia niente e non richiede di nuovo quelli gia sistemati.
$sitoFinto->chiamate = 0;
$diNuovo = \SeoGeo\Ai\Immagini::ricontrolla( $dbI, $sitoFinto, $auditI );

verifica( 'rifarlo chiede solo di quello rimasto', 1 === $diNuovo['controllati'], (string) $diNuovo['controllati'] );

// Plugin vecchio, rotta assente: si ripiega sui contenuti, che riportano
// _thumbnail_id da sempre.
$dbV2 = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileImg ) );

$sitoVecchio = new class() extends \SeoGeo\Bridge\WordPress {
	public function __construct() {}

	public function pronto() { return true; }

	public function miniature( array $ids ) {
		throw new \RuntimeException( 'rest_no_route' );
	}

	public function contenuti( $offset = 0, $limite = 40 ) {
		if ( $offset > 0 ) { return array( 'contenuti' => array() ); }

		return array(
			'contenuti' => array(
				array( 'wp_id' => '403', 'meta' => array( '_thumbnail_id' => '9912' ) ),
			),
		);
	}
};

$conVecchio = \SeoGeo\Ai\Immagini::ricontrolla( $dbV2, $sitoVecchio, $auditI );

verifica( 'col plugin vecchio funziona lo stesso', 1 === $conVecchio['sistemati'], (string) $conVecchio['sistemati'] );
verifica( 'e il conto arriva a zero', 0 === $quanteMancano(), (string) $quanteMancano() );

@unlink( $fileImg );

// La prova che conta: generare e caricare deve far scendere il conto da
// solo, senza che nessuno debba premere «ricontrolla».
$fileGen = sys_get_temp_dir() . '/seo-immagini-gen-' . getmypid() . '.sqlite';
@unlink( $fileGen );
$dbG2 = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileGen ) );

$auditG2 = $dbG2->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50 )
);

$dbG2->insert(
	'documento',
	array(
		'audit_id' => $auditG2, 'wp_id' => '501', 'titolo' => 'Senza immagine', 'slug' => 'senza-immagine',
		'percorso' => '/senza-immagine/', 'url' => 'https://esempio.it/senza-immagine/', 'tipo' => 'post',
		'stato' => 'publish', 'parole' => 700, 'ha_thumbnail' => 0,
	)
);

$geminiFinto = new class( array( 'chiave' => 'prova' ) ) extends \SeoGeo\Ai\Gemini {
	public function generaImmagine( $descrizione, array $opzioni = array() ) {
		$tela = imagecreatetruecolor( 40, 24 );
		ob_start();
		imagepng( $tela );
		$binario = (string) ob_get_clean();
		imagedestroy( $tela );

		return array( 'image/png', $binario );
	}
};

$sitoCarica = new class() extends \SeoGeo\Bridge\WordPress {
	/** @var int[] Chi ha ricevuto l immagine. */
	public $ricevuti = array();

	public function __construct() {}

	public function pronto() { return true; }

	public function inviaImmagine( $id, $binario, $mime, $nome, $alt, $inEvidenza = true ) {
		$this->ricevuti[] = (int) $id;

		return array( 'ok' => true );
	}
};

$cartellaImg = sys_get_temp_dir() . '/seo-img-' . getmypid();

$esitoGen = \SeoGeo\Ai\Immagini::esegui(
	$dbG2,
	$geminiFinto,
	$auditG2,
	$cfgCerca,
	array( 'cartella' => $cartellaImg, 'invia' => true ),
	$sitoCarica
);

verifica( 'l immagine viene generata e caricata', 1 === (int) $esitoGen['inviate'], json_encode( $esitoGen['errori'] ) );

$restaSenza = (int) $dbG2->one(
	"SELECT COUNT(*) n FROM documento WHERE audit_id = ? AND ha_thumbnail = 0 AND tipo = 'post'",
	array( $auditG2 )
)['n'];

verifica( 'e il conto scende da solo, senza ricontrollare niente', 0 === $restaSenza, (string) $restaSenza );

array_map( 'unlink', glob( $cartellaImg . '/*' ) ?: array() );
@rmdir( $cartellaImg );
@unlink( $fileGen );

verifica(
	'il gestionale offre di richiederlo al sito',
	false !== strpos( $indiceSorgente, "'ricontrolla-immagini' === \$pagina" )
		&& false !== strpos( file_get_contents( __DIR__ . '/../views/bozze.php' ), 'Chiedilo al sito' )
);

// --- Rimettere a posto duecento articoli ----------------------------------
//
// «Annulla tutto e ripristina» mandava tutti gli id in una richiesta sola.
// Con duecento articoli l hosting la chiude a meta e non si sa nemmeno
// quanti ne sono tornati indietro.

echo "\nRipristino a blocchi\n";

verifica(
	'il ripristino va a blocchi, non tutto in una richiesta',
	false !== strpos( $indiceSorgente, "'api-annulla' === \$pagina" )
);

$vistaCollega = file_get_contents( __DIR__ . '/../views/collega.php' );

verifica(
	'e la pagina dice a che punto e',
	false !== strpos( $vistaCollega, 'annulla-barra' )
		&& false !== strpos( $vistaCollega, 'annulla-battito' )
		&& false !== strpos( $vistaCollega, 'annulla-stop' )
);

verifica(
	'si puo fermare e quello che e fatto resta fatto',
	false !== strpos( $vistaCollega, 'quello che è già' )
);

verifica(
	'le pagine restano fuori: non vengono mai sovrascritte',
	false !== strpos( $indiceSorgente, "SELECT wp_id FROM documento WHERE audit_id = ? AND tipo = 'post' ORDER BY id" )
);

verifica(
	'e la sezione non promette piu solo le meta',
	false !== strpos( $vistaCollega, "<strong>il testo dell'articolo</strong>" )
);

// --- Confrontare due articoli senza guardare i pixel ----------------------
//
// Mezza giornata passata a confrontare screenshot per capire se una pagina
// fosse cambiata o no. Dalle immagini non si capisce: la differenza fra due
// articoli si legge dal database del sito.

echo "\nConfronto fra due contenuti\n";

// Se gli articoli cambiano aspetto tutti insieme, la causa non e dentro a
// nessuno di loro: e nel CSS che il sito genera una volta per tutti. Quello
// si guarda sempre, anche senza indicare nessun articolo.
verifica(
	'lo stato del CSS del sito si guarda sempre',
	false !== strpos( $indiceSorgente, '$ponte->diagnosiSito()' )
		&& false !== strpos( file_get_contents( __DIR__ . '/../src/Bridge/WordPress.php' ), 'diagnosiSito' )
);

$vistaConfronta = file_get_contents( __DIR__ . '/../views/confronta-contenuti.php' );

verifica(
	'e quando manca il file dei colori globali lo dice in chiaro',
	false !== strpos( $vistaConfronta, "Il file dei colori globali non c'è" )
		&& false !== strpos( $vistaConfronta, 'La cartella non è scrivibile' )
);

verifica(
	'e dice anche dove si sistema',
	false !== strpos( $vistaConfronta, 'deve essere scrivibile' )
);

verifica(
	'quando invece e a posto lo dice, cosi non si cerca li',
	false !== strpos( $vistaConfronta, 'non è per un file mancante' )
);

verifica(
	'il gestionale sa chiedere che cosa c e dentro a un contenuto',
	false !== strpos( $indiceSorgente, "'confronta-contenuti' === \$pagina" )
		&& false !== strpos( file_get_contents( __DIR__ . '/../src/Bridge/WordPress.php' ), 'diagnosiContenuto' )
);

verifica(
	'si puo indicare l articolo con l indirizzo o con il numero',
	false !== strpos( $indiceSorgente, "ctype_digit( \$cercato )" )
		&& false !== strpos( $indiceSorgente, 'parse_url( $cercato, PHP_URL_PATH )' )
);

verifica(
	'e la pagina mette in evidenza solo quello che e diverso',
	false !== strpos( $vistaConfronta, '$diverse' )
		&& false !== strpos( $vistaConfronta, 'diverso</span>' )
);

verifica(
	'titolo e data non vengono contati come differenze: cambiano sempre',
	false !== strpos( $vistaConfronta, "\$ovvie = array( 'id', 'titolo', 'modificato', 'revisioni' );" )
);

verifica(
	'e se non c e nessuna differenza lo dice, invece di lasciare una tabella muta',
	false !== strpos( $vistaConfronta, 'sono impostati allo stesso modo' )
);

verifica(
	'la pagina e raggiungibile dall audit',
	false !== strpos( file_get_contents( __DIR__ . '/../views/audit.php' ), 'Confronta due articoli' )
);

// --- Rimandare un articolo deve anche toglierlo dall elenco ---------------
//
// L elenco «da ripulire» guarda il testo in archivio. Il testo si ripuliva
// solo al momento di inviarlo, e in archivio restava quello sporco: dopo
// aver rimandato tutti e trenta gli articoli, l elenco ne contava ancora
// trenta.

echo "\nL elenco «da ripulire» si svuota\n";

verifica(
	'il testo ripulito viene scritto anche in archivio, non solo inviato',
	false !== strpos( $indiceSorgente, "UPDATE bozza SET inviata_il = ?, corpo_html = ? WHERE id = ?" )
);

// La condizione dell elenco: una bozza ci finisce finche il suo testo in
// archivio contiene i dati strutturati.
$sporca  = '<p>Testo.</p>{ "@context": "https://schema.org", "@type": "Article" }<p>Altro.</p>';
$pulita  = \SeoGeo\Html::senzaDatiStrutturati( $sporca );

verifica( 'una bozza sporca risulta da ripulire', $pulita !== $sporca );
verifica( 'una bozza gia ripulita non ci rientra', $pulita === \SeoGeo\Html::senzaDatiStrutturati( $pulita ) );

// --- Quello che sta nella testata non si cerca nel testo -------------------
//
// SCH-01 diceva «nessun JSON-LD nel contenuto» su tutti e 328 i contenuti,
// come rilievo CRITICO, e leggeva il testo degli articoli. I dati
// strutturati stanno nel <head>, li stampa il plugin al momento di servire
// la pagina: nel testo non ci sono mai, quindi quel rilievo non si poteva
// chiudere in nessun modo. E il modello, a cui quel rilievo veniva passato,
// ha provato a «risolverlo» scrivendo il JSON-LD dentro all articolo.

echo "\nI rilievi sulla testata\n";

/**
 * Sito minimo, con o senza la dichiarazione di cosa stampa il plugin.
 *
 * @param array $stampa Dichiarazione del plugin.
 * @return \SeoGeo\Site
 */
function sito_che_stampa( array $stampa ) {
	return new \SeoGeo\Site(
		array(
			'sito' => array(
				'titolo' => 'Prova', 'link' => 'https://esempio.it', 'baseUrl' => 'https://esempio.it',
				'descrizione' => '', 'lingua' => 'it-IT', 'autori' => array(),
				'stampa' => $stampa,
			),
			'items' => array(
				array(
					'wp_id' => '1', 'tipo' => 'post', 'stato' => 'publish', 'titolo' => 'Articolo',
					'slug' => 'articolo', 'link' => 'https://esempio.it/articolo/', 'data' => '2026-01-01',
					'modificato' => '2026-01-01', 'autore' => 'admin', 'categorie' => array(), 'tag' => array(),
					'estratto' => '', 'contenuto' => '<p>Testo senza nessuno schema dentro.</p>',
					'meta' => array(), 'commenti' => 0,
				),
			),
			'categorie' => array(), 'tag' => array(),
		)
	);
}

$regoleTutte = array();

foreach ( \SeoGeo\Audit::regole() as $r ) {
	$regoleTutte[ $r['id'] ] = $r;
}

$senza = sito_che_stampa( array() );
$con   = sito_che_stampa(
	array(
		'jsonld' => true, 'canonical' => true, 'robots' => true, 'opengraph' => true,
		'local' => true, 'autore' => true,
		// Queste il plugin le fa mentre serve la pagina: nel testo salvato
		// su WordPress non ci sono, e cercarle li non finisce mai.
		'link_interni' => true, 'nofollow' => true, 'alt' => true, 'dimensioni' => true,
		'llms' => true, 'robots_txt' => true,
	)
);

verifica(
	'senza plugin il rilievo sullo schema resta, perche del sito non si sa niente',
	array() !== $regoleTutte['SCH-01']['check']( $senza )
);

verifica(
	'ma se il plugin dichiara di stamparlo, non si segnala piu',
	array() === $regoleTutte['SCH-01']['check']( $con ),
	json_encode( $regoleTutte['SCH-01']['check']( $con ) )
);

foreach ( array( 'SCH-02', 'SCH-03', 'SCH-04', 'SCH-05', 'GEO-07', 'LOC-03', 'TEC-02', 'TEC-03', 'TEC-04', 'GEO-01', 'GEO-02', 'LNK-01', 'LNK-02', 'LNK-04', 'IMG-01', 'IMG-02', 'IMG-06' ) as $id ) {
	verifica(
		'lo stesso vale per ' . $id,
		array() === $regoleTutte[ $id ]['check']( $con ),
		json_encode( $regoleTutte[ $id ]['check']( $con ) )
	);
}

// Le regole sul testo non c entrano: quelle devono continuare a funzionare.
verifica(
	'le regole sul testo non vengono zittite dal plugin',
	array() !== $regoleTutte['CNT-01']['check']( $con ) || array() !== $regoleTutte['ONP-04']['check']( $con ),
	'nessuna delle due segnala piu niente'
);

// Dichiarare una cosa che il plugin non fa e peggio che non dichiararla:
// si zittisce un rilievo vero.
$sorgenteMedia = file_get_contents( __DIR__ . '/../plugin-wordpress/mdi-seo-geo-booster/includes/class-mdi-media.php' );

verifica(
	'il plugin mette davvero width e height, visto che lo dichiara',
	false !== strpos( $sorgenteMedia, 'misure_da_src' )
		&& false !== strpos( $sorgenteMedia, 'attachment_url_to_postid' )
);

verifica(
	'e non si dichiara il CSS inline, che il plugin non ripulisce',
	false === strpos( file_get_contents( __DIR__ . '/../plugin-wordpress/mdi-seo-geo-booster/includes/class-mdi-api.php' ), "'css_inline'" )
);

verifica(
	'infatti CNT-07 non risulta chiuso dal plugin',
	'plugin' !== ( \SeoGeo\Rimedi::per( 'CNT-07', 1 )['come'] ?? '' ),
	(string) ( \SeoGeo\Rimedi::per( 'CNT-07', 1 )['come'] ?? 'nessun rimedio' )
);

verifica(
	'il plugin dichiara che cosa stampa',
	false !== strpos( file_get_contents( __DIR__ . '/../plugin-wordpress/mdi-seo-geo-booster/includes/class-mdi-api.php' ), 'public static function cosa_stampa()' )
);

verifica(
	'e la dichiarazione arriva fino al sito analizzato',
	false !== strpos( file_get_contents( __DIR__ . '/../src/Sync/Sito.php' ), "'stampa'" )
		&& false !== strpos( file_get_contents( __DIR__ . '/../src/Site.php' ), 'public $stampa' )
);

// --- Il pulsante deve portare con se il problema ---------------------------
//
// Cliccando «Riscrittura assistita» da un problema preciso si finiva nella
// pagina giusta senza sapere su che cosa lavorare, e la generazione
// ricominciava da capo su tutto l archivio.

echo "\nDal problema alla correzione\n";

verifica(
	'il rimedio porta con se la regola',
	false !== strpos( (string) ( \SeoGeo\Rimedi::per( 'GEO-04', 7 )['dove'] ?? '' ), 'regola=GEO-04' ),
	(string) ( \SeoGeo\Rimedi::per( 'GEO-04', 7 )['dove'] ?? '' )
);

verifica(
	'e la pagina dice su che cosa si sta lavorando',
	false !== strpos( file_get_contents( __DIR__ . '/../views/bozze.php' ), 'Stai correggendo' )
		&& false !== strpos( file_get_contents( __DIR__ . '/../views/bozze.php' ), '$da_regola' )
);

verifica(
	'la generazione riceve la regola dal modulo',
	false !== strpos( $indiceSorgente, "\$opzioni['regola'] = \$_POST['regola'];" )
);

// La selezione per regola, sul database vero.
$fileReg = sys_get_temp_dir() . '/seo-regola-' . getmypid() . '.sqlite';
@unlink( $fileReg );
$dbR = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileReg ) );

$auditR = $dbR->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50 )
);

$rilievoR = $dbR->insert(
	'rilievo',
	array( 'audit_id' => $auditR, 'regola' => 'GEO-04', 'area' => 'generative', 'gravita' => 'high', 'titolo' => 'Nessuna sezione FAQ', 'perche' => 'x', 'soluzione' => 'y', 'automatico' => 1, 'occorrenze' => 1 )
);

foreach ( array( 'con-problema', 'senza-problema' ) as $n => $slug ) {
	$docR = $dbR->insert(
		'documento',
		array(
			'audit_id' => $auditR, 'wp_id' => (string) ( 600 + $n ), 'titolo' => $slug, 'slug' => $slug,
			'percorso' => '/' . $slug . '/', 'url' => 'https://esempio.it/' . $slug . '/',
			'tipo' => 'post', 'stato' => 'publish', 'parole' => 700,
		)
	);

	// Categoria «mantenere»: un articolo buono a cui mancano comunque le FAQ.
	$dbR->insert( 'triage', array( 'audit_id' => $auditR, 'documento_id' => $docR, 'categoria' => 'mantenere', 'intento' => 'informazionale' ) );

	if ( 'con-problema' === $slug ) {
		$dbR->insert( 'occorrenza', array( 'rilievo_id' => $rilievoR, 'riferimento' => 'https://esempio.it/' . $slug . '/', 'dettaglio' => 'nessuna FAQ' ) );
	}
}

$perRegola = \SeoGeo\Ai\Rewriter::candidati( $dbR, $auditR, array( 'regola' => 'GEO-04', 'rigenera' => 1 ) );

verifica( 'si seleziona solo chi ha quel problema', 1 === count( $perRegola ), (string) count( $perRegola ) );
verifica( 'ed e quello giusto', 'con-problema' === ( $perRegola[0]['slug'] ?? '' ), (string) ( $perRegola[0]['slug'] ?? '' ) );

// Senza regola valgono le categorie di sempre: «mantenere» resta fuori.
$senzaRegola = \SeoGeo\Ai\Rewriter::candidati( $dbR, $auditR, array( 'rigenera' => 1 ) );

verifica( 'senza regola gli articoli da mantenere restano fuori', 0 === count( $senzaRegola ), (string) count( $senzaRegola ) );

@unlink( $fileReg );

// --- I conteggi dei pulsanti scendono quando si applica --------------------

verifica(
	'le occorrenze applicate non si contano piu',
	false !== strpos( $indiceSorgente, "COALESCE( o.applicato, 0 ) = 0" )
		&& false !== strpos( $indiceSorgente, "UPDATE occorrenza SET applicato = 1" )
);

verifica(
	'e la colonna viene creata da sola sugli archivi gia esistenti',
	false !== strpos( file_get_contents( __DIR__ . '/../src/Db.php' ), "aggiungiColonna( 'occorrenza', 'applicato', 'INT' )" )
);

// --- Sitemap: dirlo a Google ----------------------------------------------
//
// Dopo aver cambiato duecento contenuti la sitemap sul sito e gia
// aggiornata, ma Google la ripassa quando gli pare.

echo "\nSitemap\n";

// Finto sito che risponde solo a uno degli indirizzi soliti.
// La porta si deriva dal processo: due esecuzioni ravvicinate non si
// contendono la stessa, e il collaudo non dipende da chi e passato prima.
$portaSm  = 20000 + ( getmypid() % 20000 );
$radiceSm = sys_get_temp_dir() . '/seo-sitemap-' . getmypid();
@mkdir( $radiceSm );

file_put_contents(
	$radiceSm . '/sitemap_index.xml',
	'<?xml version="1.0"?><sitemapindex><sitemap><loc>https://esempio.it/a.xml</loc></sitemap>'
	. '<sitemap><loc>https://esempio.it/b.xml</loc></sitemap></sitemapindex>'
);

// Una pagina 404 che risponde 200 con HTML: non e una sitemap, e mandarla
// a Google farebbe solo comparire un errore nella sua console.
file_put_contents( $radiceSm . '/sitemap.xml', '<!doctype html><html><body>Pagina non trovata</body></html>' );

$serverSm = proc_open(
	sprintf( 'php -S 127.0.0.1:%d -t %s', $portaSm, escapeshellarg( $radiceSm ) ),
	array( 1 => array( 'file', '/dev/null', 'w' ), 2 => array( 'file', '/dev/null', 'w' ) ),
	$tubiSm
);

// Si aspetta che risponda davvero, invece di sperare in una pausa fissa:
// su una macchina carica mezzo secondo non basta, e il collaudo fallirebbe
// per un motivo che non c entra niente con quello che sta misurando.
$prontoSm = false;

for ( $tentativo = 0; $tentativo < 60; $tentativo++ ) {
	$prova = @fsockopen( '127.0.0.1', $portaSm, $e1, $e2, 0.2 );

	if ( $prova ) {
		fclose( $prova );
		$prontoSm = true;
		break;
	}

	usleep( 100000 );
}

verifica( 'il finto sito risponde', $prontoSm );

$trovate = $prontoSm ? \SeoGeo\Search\Sitemap::trova( 'http://127.0.0.1:' . $portaSm ) : array();

verifica( 'si trova la sitemap vera', 1 === count( $trovate ), json_encode( array_column( $trovate, 'url' ) ) );
verifica( 'ed e quella giusta', false !== strpos( (string) ( $trovate[0]['url'] ?? '' ), 'sitemap_index.xml' ), (string) ( $trovate[0]['url'] ?? '' ) );
verifica( 'con quante voci contiene', 2 === (int) ( $trovate[0]['url_dentro'] ?? 0 ), (string) ( $trovate[0]['url_dentro'] ?? 0 ) );
verifica( 'una pagina HTML che risponde 200 non viene scambiata per una sitemap', 1 === count( $trovate ) );

if ( is_resource( $serverSm ) ) {
	proc_terminate( $serverSm );
	proc_close( $serverSm );
}

array_map( 'unlink', glob( $radiceSm . '/*' ) ?: array() );
@rmdir( $radiceSm );

// Un sito che non risponde non deve far esplodere la pagina.
verifica( 'un sito irraggiungibile non rompe niente', array() === \SeoGeo\Search\Sitemap::trova( 'http://127.0.0.1:9' ) );

$sorgenteConsole = file_get_contents( __DIR__ . '/../src/Google/SearchConsole.php' );

verifica(
	'l invio usa PUT, come vuole l API di Google',
	false !== strpos( $sorgenteConsole, "'PUT'," )
		&& false !== strpos( $sorgenteConsole, 'CURLOPT_CUSTOMREQUEST' )
);

verifica(
	'e chiede l ambito di scrittura, non quello di sola lettura',
	false !== strpos( $sorgenteConsole, "'https://www.googleapis.com/auth/webmasters'" )
);

verifica(
	'il gestionale ha il pulsante e passa dal client gia esistente',
	false !== strpos( $indiceSorgente, "'invia-sitemap' === \$pagina" )
		&& false !== strpos( $indiceSorgente, "Prestazioni::client( \$cfg )->inviaSitemap(" )
);

verifica(
	'un indirizzo non valido viene rifiutato prima di chiamare Google',
	false !== strpos( $indiceSorgente, 'FILTER_VALIDATE_URL' )
);

// Non si promette quello che non si puo fare.
$vistaPrest = file_get_contents( __DIR__ . '/../views/prestazioni.php' );

verifica(
	'la pagina dice chiaro che non forza l indicizzazione',
	false !== strpos( $vistaPrest, 'non</strong> fa: forzare l\'indicizzazione' )
);

verifica(
	'e avverte che serve il permesso di scrittura',
	false !== strpos( $vistaPrest, 'autorizzazione completa' )
);

// --- Perche quelle immagini sono ancora li --------------------------------
//
// La pagina diceva «9 ancora da ricomprimere» e basta. Premendo, si
// riottenevano 9: il motivo compariva per un attimo durante il giro e
// spariva al ricaricamento. Nove che non si spiegano sembrano un programma
// rotto.

echo "\nImmagini che resistono\n";

$fileFalliti = \SeoGeo\Media\Compressione::fileFalliti();
$backupFalliti = is_file( $fileFalliti ) ? file_get_contents( $fileFalliti ) : null;
@unlink( $fileFalliti );

\SeoGeo\Media\Compressione::segnaFalliti(
	array(
		'gigante.png: ricompressa ma resta sopra la soglia',
		'strana.gif: formato non gestito da GD',
	)
);

$falliti = \SeoGeo\Media\Compressione::falliti();

verifica( 'i motivi restano scritti', 2 === count( $falliti ), json_encode( $falliti ) );
verifica( 'e si leggono per file', 'formato non gestito da GD' === ( $falliti['strana.gif'] ?? '' ), (string) ( $falliti['strana.gif'] ?? '' ) );

// Quella che poi riesce non deve restare nell elenco: un errore vecchio che
// resta scritto e peggio di nessun errore.
\SeoGeo\Media\Compressione::segnaFalliti( array(), array( 'strana.gif' ) );
$dopoFalliti = \SeoGeo\Media\Compressione::falliti();

verifica( 'chi poi riesce sparisce dall elenco', ! isset( $dopoFalliti['strana.gif'] ), json_encode( $dopoFalliti ) );
verifica( 'e chi resiste ancora ci resta', isset( $dopoFalliti['gigante.png'] ) );

@unlink( $fileFalliti );

if ( null !== $backupFalliti ) {
	file_put_contents( $fileFalliti, $backupFalliti );
}

$sorgenteCompr = file_get_contents( __DIR__ . '/../src/Media/Compressione.php' );

verifica(
	'«invariata» viene contata come resistenza, non come successo',
	false !== strpos( $sorgenteCompr, 'resta sopra la soglia' )
		&& false !== strpos( $sorgenteCompr, 'self::segnaFalliti( $errori, $riuscite )' )
);

$vistaCollega2 = file_get_contents( __DIR__ . '/../views/collega.php' );

verifica(
	'la pagina mostra il motivo, e non solo il numero',
	false !== strpos( $vistaCollega2, 'hanno già resistito a un tentativo' )
		&& false !== strpos( $vistaCollega2, 'Compressione::falliti()' )
);

verifica(
	'i motivi dei blocchi si accumulano invece di sovrascriversi',
	false !== strpos( $vistaCollega2, 'var saltate = [];' )
		&& false === strpos( $vistaCollega2, "'Saltate: ' + d.errori.join" )
);

// --- Arrivare da un problema e trovarci un pulsante -----------------------
//
// Si arrivava con «Stai correggendo LOC-05: riguarda 19 contenuti» e sotto
// nessun pulsante: la condizione guardava il conto dell archivio intero, che
// era a zero perche tutte le bozze erano gia state fatte.

echo "\nDal problema al pulsante\n";

$vistaBozze = file_get_contents( __DIR__ . '/../views/bozze.php' );

verifica(
	'il pulsante compare in base ai contenuti della regola, non dell archivio',
	false !== strpos( $vistaBozze, "\$pronto && ! \$daFondere && count( \$da_regola ) > 0" ),
	'la condizione guarda ancora l archivio'
);

// E il riquadro generico non deve comparire quando si sta lavorando su una
// regola: due moduli identici uno sopra l altro erano solo confusione.
verifica(
	'il lotto sull archivio intero sparisce quando si lavora su una regola',
	false !== strpos( $vistaBozze, "\$pronto && empty( \$regola ) && \$stima['articoli'] > 0" ),
	'il modulo generico compare comunque'
);

verifica(
	'e anche la stima guarda la stessa cosa',
	false !== strpos( $indiceSorgente, "Rewriter::candidati( \$db, \$id, array( 'regola' => \$regola_scelta ) )" ),
	'l elenco e la generazione contano cose diverse'
);

// Il difetto: il pulsante «Genera le bozze per GEO-10» chiamava la rotta a
// lotti senza dire da che problema si era partiti. Il server lavorava sull
// archivio intero, dove il triage non aveva segnalato nessuno di quei 244
// articoli, e rispondeva «nessun articolo da riscrivere».
verifica(
	'il lotto porta con se la regola fino al server',
	false !== strpos( $vistaBozze, "'&regola=' + encodeURIComponent(regola.value)" ),
	'la richiesta non manda la regola'
);

verifica(
	'e il server la legge',
	false !== strpos( $indiceSorgente, "\$regola_lotto = preg_match(" ),
	'la rotta a lotti non legge la regola'
);

// Leggerla e inutile se poi non arriva alla generazione: e il passaggio che
// mancava, e toglierlo non faceva fallire niente.
verifica(
	'e la passa davvero alla generazione',
	false !== strpos( $indiceSorgente, "\$opzioni['regola'] = \$regola_lotto;" ),
	'la regola letta non arriva alla generazione'
);

verifica(
	'anche il conto di quante ne restano guarda la stessa regola',
	false !== strpos( $indiceSorgente, "'' !== \$regola_lotto ? array( 'regola' => \$regola_lotto ) : array()" ),
	'la barra misura un lavoro diverso'
);

// I problemi che si risolvono fondendo due pagine non vanno mandati alla
// riscrittura di un articolo per volta: sarebbe lo strumento sbagliato.
verifica(
	'i problemi da accorpare portano al posto giusto',
	false !== strpos( $vistaBozze, "array( 'LOC-05', 'ONP-06', 'CNT-03' )" )
		&& false !== strpos( $vistaBozze, 'vanno <strong>fuse in una</strong>' )
);

verifica(
	'e la sezione dei gruppi e raggiungibile',
	false !== strpos( $vistaBozze, 'id="gruppi"' )
		&& false !== strpos( $vistaBozze, 'href="#gruppi"' )
);

verifica(
	'con quei problemi non si offre la generazione singola',
	false !== strpos( $vistaBozze, '$pronto && ! $daFondere &&' )
);

// --- Una bozza che unisce piu articoli non finisce quando parte ------------
//
// Gli articoli assorbiti restano pubblicati: finche non si reindirizzano, il
// contenuto e doppio. Va detto dove si preme, non in un altra pagina.

echo "\nAccorpamenti: il lavoro dopo l invio\n";

$vistaConfronto4 = file_get_contents( __DIR__ . '/../views/confronto-bozze.php' );

verifica(
	'le bozze che accorpano si riconoscono',
	false !== strpos( $vistaConfronto4, "0 === strpos( (string) ( \$riga['note'] ?? '' ), 'Accorpa ' )" )
);

verifica(
	'e la pagina avvisa che mandarle online non basta',
	false !== strpos( $vistaConfronto4, 'Mandarla online <strong>non basta</strong>' )
		&& false !== strpos( $vistaConfronto4, 'cestina gli assorbiti' )
);

verifica(
	'dicendo anche in che ordine, che e la parte che rompe le cose',
	false !== strpos( $vistaConfronto4, "in quest'ordine" )
);

verifica(
	'e si contano in cima, non si scoprono a meta strada',
	false !== strpos( $vistaConfronto4, 'uniscono più articoli' )
);

verifica(
	'la nota della bozza arriva fino alla pagina',
	false !== strpos( $indiceSorgente, 'b.meta_description, b.faq, b.note,' )
);

// --- Il pilota automatico deve arrivare in fondo --------------------------
//
// «applica_bozza» chiamava applicaBozza(), che su WordPress cerca una bozza
// creata da inviaBozza(). Da quando le riscritture non passano piu da una
// bozza di WordPress - creava un doppione in bacheca da applicare a mano -
// quella bozza non esiste: il pilota falliva su OGNI riscrittura con
// «nessuna bozza collegata a questo articolo», e chi premeva il pulsante si
// ritrovava una fila di errori senza capire perche.

echo "\nPilota automatico: pubblicazione\n";

$sorgenteCoda = file_get_contents( __DIR__ . '/../src/Coda.php' );

verifica(
	'il pilota scrive sull articolo che esiste, come il pulsante',
	false !== strpos( $sorgenteCoda, '$ponte->sovrascrivi(' )
		&& false === strpos( $sorgenteCoda, '$ponte->applicaBozza(' )
);

verifica(
	'prende la bozza dall archivio del gestionale',
	false !== strpos( $sorgenteCoda, "WHERE b.audit_id = ? AND b.documento_id = ? AND b.stato = 'ok'" )
);

verifica(
	'ripulisce il testo prima di mandarlo, come fa il pulsante',
	false !== strpos( $sorgenteCoda, 'Html::senzaDatiStrutturati( (string) $riga[' )
);

verifica(
	'e segna la bozza come inviata, cosi non si ripresenta',
	false !== strpos( $sorgenteCoda, "UPDATE bozza SET inviata_il = ?, corpo_html = ? WHERE id = ?" )
);

// L ordine delle operazioni: i redirect prima di cestinare, se no chi arriva
// da Google trova pagina non trovata.
$posRedirect = strpos( $sorgenteCoda, "\$aggiungi( 'redirect'," );
$posAccorpa  = strpos( $sorgenteCoda, "'accorpa',\n" );
$posCestina  = strpos( $sorgenteCoda, "\$aggiungi( 'cestina'," );

verifica(
	'i redirect vengono messi in coda prima del cestino',
	$posRedirect > 0 && $posCestina > 0 && $posRedirect < $posCestina,
	$posRedirect . ' < ' . $posCestina
);

verifica(
	'e la pubblicazione prima del cestino',
	false !== strpos( $sorgenteCoda, "\$aggiungi( 'applica_bozza'," )
		&& strpos( $sorgenteCoda, "\$aggiungi( 'applica_bozza'," ) < $posCestina
);

// --- Cestinare gli assorbiti: il pulsante che mancava ---------------------
//
// Dopo un accorpamento gli articoli assorbiti restano pubblicati, e il loro
// testo e anche dentro al principale: contenuto doppio. Il modo di toglierli
// esisteva solo dentro al pilota automatico, come casella. In «Applica sul
// sito» - dove si fanno i redirect, cioe il passo prima - non c era niente.

echo "\nCestinare gli assorbiti\n";

$vistaCollega3 = file_get_contents( __DIR__ . '/../views/collega.php' );

verifica(
	'il pulsante esiste nella pagina dove si fanno i redirect',
	false !== strpos( $vistaCollega3, 'Cestina i contenuti assorbiti' )
		&& false !== strpos( $vistaCollega3, "'cestina'," )
);

verifica(
	'e dice che vanno nel cestino, non cancellati',
	false !== strpos( $vistaCollega3, 'da lì si recuperano' ) || false !== strpos( $vistaCollega3, 'si recuperano' )
);

// La rete che conta: cestinare senza redirect da pagina non trovata a chi
// arriva da Google.
verifica(
	'la pagina confronta i redirect attivi sul sito con quelli previsti',
	false !== strpos( $vistaCollega3, "\$redirect_attivi = (int) ( \$stato['redirect'] ?? 0 )" )
		&& false !== strpos( $vistaCollega3, 'Prima però servono i redirect' )
);

verifica(
	'e il gestionale si rifiuta comunque, non si fida solo dell avviso',
	false !== strpos( $indiceSorgente, "case 'cestina':" )
		&& false !== strpos( $indiceSorgente, 'cestinare adesso darebbe pagina non trovata' )
);

verifica(
	'si cestinano gli assorbiti, cioe quelli che hanno un redirect',
	false !== strpos( $indiceSorgente, "WHERE t.audit_id = ? AND t.redirect_a <> '' AND d.wp_id <> ''" )
);

// --- «Ha cambiato indirizzo» deve smettere dopo che l hai sistemato -------
//
// L avviso nasce dal confronto fra due analisi archiviate. Attivare il
// redirect non cambia ne l una ne l altra: restava li per sempre, e premendo
// «Sistemali adesso» si riotteneva lo stesso avviso.

echo "\nIndirizzi cambiati: l avviso si spegne\n";

$fileRed = sys_get_temp_dir() . '/seo-redirect-' . getmypid() . '.sqlite';
@unlink( $fileRed );
$dbRed = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileRed ) );

foreach ( array( '2026-01-01 10:00:00', '2026-02-01 10:00:00' ) as $quando ) {
	$aRed = $dbRed->insert(
		'audit',
		array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => $quando, 'punteggio' => 50 )
	);

	// Lo stesso contenuto, con il percorso cambiato fra le due analisi.
	$dbRed->insert(
		'documento',
		array(
			'audit_id' => $aRed, 'wp_id' => '700', 'titolo' => 'Articolo',
			'slug' => 'nuovo', 'tipo' => 'post', 'stato' => 'publish', 'parole' => 500,
			'percorso' => '2026-01-01 10:00:00' === $quando ? '/vecchio-indirizzo/' : '/nuovo-indirizzo/',
			'url'      => 'https://esempio.it/' . ( '2026-01-01 10:00:00' === $quando ? 'vecchio-indirizzo' : 'nuovo-indirizzo' ) . '/',
		)
	);
}

$senzaPonte = \SeoGeo\Redirezioni::confronto( $dbRed, 'https://esempio.it' );

verifica( 'un indirizzo cambiato viene segnalato', 1 === count( $senzaPonte['cambiati'] ), json_encode( $senzaPonte['cambiati'] ) );

// Un sito che dichiara quel redirect gia attivo.
$sitoConRedirect = new class() extends \SeoGeo\Bridge\WordPress {
	public function __construct() {}

	public function pronto() { return true; }

	public function redirectAttivi() {
		return array( 'percorsi' => array( '/vecchio-indirizzo/' ) );
	}
};

$conPonte = \SeoGeo\Redirezioni::confronto( $dbRed, 'https://esempio.it', $sitoConRedirect );

verifica( 'ma se sul sito e gia attivo non si segnala piu', 0 === count( $conPonte['cambiati'] ), json_encode( $conPonte['cambiati'] ) );

// Un sito che non risponde non deve nascondere un indirizzo rotto.
$sitoMuto = new class() extends \SeoGeo\Bridge\WordPress {
	public function __construct() {}

	public function pronto() { return true; }

	public function redirectAttivi() {
		throw new \RuntimeException( 'sito irraggiungibile' );
	}
};

$conMuto = \SeoGeo\Redirezioni::confronto( $dbRed, 'https://esempio.it', $sitoMuto );

verifica(
	'se il sito non risponde l avviso resta, invece di sparire per sbaglio',
	1 === count( $conMuto['cambiati'] ),
	json_encode( $conMuto['cambiati'] )
);

@unlink( $fileRed );

verifica(
	'il plugin sa dire quali redirect sono attivi',
	false !== strpos( file_get_contents( __DIR__ . '/../plugin-wordpress/mdi-seo-geo-booster/includes/class-mdi-api.php' ), 'public static function redirect_attivi()' )
);

verifica(
	'e le pagine lo chiedono al sito',
	2 === substr_count( $indiceSorgente, "Redirezioni::confronto( \$db, \$audit['sito_url'], " ),
	(string) substr_count( $indiceSorgente, "Redirezioni::confronto( \$db, \$audit['sito_url'], " )
);


// ---------------------------------------------------------------------------
// I conti scendono quando il lavoro viene fatto
//
// Il difetto che questo blocco sorveglia: il gestionale scriveva title e
// description sul sito, il sito rispondeva "fatto", e la tabella dei problemi
// continuava a segnare gli stessi numeri perche leggeva la fotografia scattata
// prima. Chi guardava vedeva errori che non esistevano piu, e gli stessi
// articoli venivano riproposti all infinito.

echo "\nIl lavoro fatto si vede sui conti\n";

$fileApp = sys_get_temp_dir() . '/prova-applicato-' . getmypid() . '.sqlite';
@unlink( $fileApp );
$dbApp = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileApp ) );

$auditApp = $dbApp->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50, 'problemi_totali' => 3 )
);

foreach ( array(
	array( 'wp_id' => '10', 'titolo' => 'Uno', 'percorso' => '/uno/', 'url' => 'https://esempio.it/uno/', 'seo_title' => 'Titolo vecchio lunghissimo che viene troncato in SERP', 'seo_description' => 'vecchia' ),
	array( 'wp_id' => '11', 'titolo' => 'Due', 'percorso' => '/due/', 'url' => 'https://esempio.it/due/', 'seo_title' => 'Anche questo titolo e decisamente troppo lungo per la SERP', 'seo_description' => 'vecchia' ),
	array( 'wp_id' => '12', 'titolo' => 'Tre', 'percorso' => '/tre/', 'url' => 'https://esempio.it/tre/', 'seo_title' => 'E pure il terzo titolo sfora la lunghezza massima consentita', 'seo_description' => 'vecchia' ),
) as $documento ) {
	$dbApp->insert( 'documento', $documento + array( 'audit_id' => $auditApp, 'tipo' => 'post', 'stato' => 'publish' ) );
}

$rilievoApp = $dbApp->insert(
	'rilievo',
	array(
		'audit_id'   => $auditApp,
		'regola'     => 'ONP-01',
		'area'       => 'onpage',
		'gravita'    => 'high',
		'titolo'     => 'Title SEO troppo lungo',
		'perche'     => '',
		'soluzione'  => '',
		'automatico' => 1,
		'occorrenze' => 3,
	)
);

foreach ( array( '/uno/', '/due/', '/tre/' ) as $percorso ) {
	$dbApp->insert( 'occorrenza', array( 'rilievo_id' => $rilievoApp, 'riferimento' => $percorso, 'dettaglio' => '' ) );
}

$primaApp = \SeoGeo\Applicato::residui( $dbApp, $auditApp );

verifica( 'prima di toccare niente restano tutte e tre le occorrenze', 3 === $primaApp['ONP-01']['aperte'], json_encode( $primaApp ) );

// Si spediscono le meta di due contenuti: e il momento in cui prima il conto
// restava fermo.
\SeoGeo\Applicato::meta(
	$dbApp,
	$auditApp,
	array(
		array( 'id' => '10', 'title' => 'Titolo corto e giusto', 'description' => 'nuova' ),
		array( 'id' => '11', 'title' => 'Anche questo corto', 'description' => 'nuova' ),
	)
);

$dopoApp = \SeoGeo\Applicato::residui( $dbApp, $auditApp );

verifica( 'dopo averne scritte due sul sito ne resta una sola', 1 === $dopoApp['ONP-01']['aperte'], json_encode( $dopoApp ) );

// La copia locale deve dire quello che il sito dice adesso: e quella che
// "solo quelle da cambiare" legge per decidere chi rispedire.
$rimasto = $dbApp->one( 'SELECT seo_title FROM documento WHERE audit_id = ? AND wp_id = ?', array( $auditApp, '10' ) );

verifica( 'e la copia locale del contenuto e allineata al sito', 'Titolo corto e giusto' === $rimasto['seo_title'], (string) $rimasto['seo_title'] );

// Rispedire le stesse meta non deve far scendere il conto sotto il vero.
\SeoGeo\Applicato::meta( $dbApp, $auditApp, array( array( 'id' => '10', 'title' => 'Titolo corto e giusto', 'description' => 'nuova' ) ) );

$ancoraApp = \SeoGeo\Applicato::residui( $dbApp, $auditApp );

verifica( 'rifarlo due volte non conta due volte', 1 === $ancoraApp['ONP-01']['aperte'], json_encode( $ancoraApp ) );

// Un ripristino rimette le meta vecchie: i problemi tornano, quindi non si
// chiude niente.
$rilieviApp = $dbApp->all( 'SELECT * FROM rilievo WHERE audit_id = ?', array( $auditApp ) );
$separati   = \SeoGeo\Applicato::separa( $rilieviApp, $ancoraApp );

verifica( 'il rilievo con un occorrenza aperta resta fra i problemi', 1 === count( $separati['aperti'] ), json_encode( $separati ) );
verifica( 'e porta con se quante ne sono state sistemate', 2 === (int) $separati['aperti'][0]['chiuse'], json_encode( $separati['aperti'][0] ) );

// Chiuso l ultimo, il rilievo si sposta fra i sistemati invece di sparire.
\SeoGeo\Applicato::meta( $dbApp, $auditApp, array( array( 'id' => '12', 'title' => 'Corto anche lui', 'description' => 'nuova' ) ) );

$finiti  = \SeoGeo\Applicato::separa( $rilieviApp, \SeoGeo\Applicato::residui( $dbApp, $auditApp ) );

verifica( 'chiuse tutte, il rilievo esce dai problemi', 0 === count( $finiti['aperti'] ), json_encode( $finiti['aperti'] ) );
verifica( 'ma non sparisce: finisce fra i gia sistemati', 1 === count( $finiti['chiusi'] ), json_encode( $finiti['chiusi'] ) );
verifica( 'e ricorda quante erano in partenza', 3 === (int) $finiti['chiusi'][0]['occorrenze_iniziali'], json_encode( $finiti['chiusi'][0] ) );

// Un rilievo senza occorrenze salvate non si puo fingere risolto.
$soloSito = $dbApp->insert(
	'rilievo',
	array( 'audit_id' => $auditApp, 'regola' => 'GEO-01', 'area' => 'generative', 'gravita' => 'high', 'titolo' => 'llms.txt assente', 'perche' => '', 'soluzione' => '', 'automatico' => 1, 'occorrenze' => 1 )
);

$conSito = \SeoGeo\Applicato::separa(
	$dbApp->all( 'SELECT * FROM rilievo WHERE audit_id = ?', array( $auditApp ) ),
	\SeoGeo\Applicato::residui( $dbApp, $auditApp )
);

verifica(
	'un rilievo senza occorrenze salvate resta fra i problemi',
	1 === count( array_filter( $conSito['aperti'], static fn( $r ) => 'GEO-01' === $r['regola'] ) ),
	json_encode( $conSito['aperti'] )
);

// E la riscrittura assistita non deve riproporre chi e gia stato sistemato.
verifica(
	'la riscrittura assistita salta le occorrenze gia chiuse',
	false !== strpos( file_get_contents( __DIR__ . '/../src/Ai/Rewriter.php' ), 'COALESCE( o.applicato, 0 ) = 0' ),
	'il filtro non c e'
);

// ---------------------------------------------------------------------------
// «Non c e niente da fare» deve dire di chi sta parlando
//
// La tabella dell audit segnava 2 contenuti con CNT-01, si premeva
// «Riscrittura assistita» e la pagina rispondeva che non c era niente. Due
// numeri diversi per la stessa cosa, e nessun modo di sapere quale fosse
// quello giusto.

echo "\nQuando la coda e vuota si dice perche\n";

$rilievoCnt = $dbApp->insert(
	'rilievo',
	array( 'audit_id' => $auditApp, 'regola' => 'CNT-01', 'area' => 'content', 'gravita' => 'critical', 'titolo' => 'Contenuto molto scarno', 'perche' => '', 'soluzione' => '', 'automatico' => 0, 'occorrenze' => 4 )
);

// Una pagina: esclusa per scelta, la riscrittura non tocca le pagine.
$dbApp->insert( 'documento', array( 'audit_id' => $auditApp, 'wp_id' => '20', 'tipo' => 'page', 'stato' => 'publish', 'titolo' => 'Chi siamo', 'percorso' => '/chi-siamo/', 'url' => 'https://esempio.it/chi-siamo/', 'parole' => 120 ) );
$dbApp->insert( 'occorrenza', array( 'rilievo_id' => $rilievoCnt, 'riferimento' => '/chi-siamo/', 'dettaglio' => '120 parole' ) );

// Un articolo mai classificato dal triage.
$dbApp->insert( 'documento', array( 'audit_id' => $auditApp, 'wp_id' => '21', 'tipo' => 'post', 'stato' => 'publish', 'titolo' => 'Nota breve', 'percorso' => '/nota/', 'url' => 'https://esempio.it/nota/', 'parole' => 90 ) );
$dbApp->insert( 'occorrenza', array( 'rilievo_id' => $rilievoCnt, 'riferimento' => '/nota/', 'dettaglio' => '90 parole' ) );

// Un articolo gia sistemato dal gestionale.
$dbApp->insert( 'occorrenza', array( 'rilievo_id' => $rilievoCnt, 'riferimento' => '/uno/', 'dettaglio' => '80 parole', 'applicato' => 1 ) );

// Un riferimento che non corrisponde piu a nessun contenuto.
$dbApp->insert( 'occorrenza', array( 'rilievo_id' => $rilievoCnt, 'riferimento' => '/sparito/', 'dettaglio' => '10 parole' ) );

$perche = \SeoGeo\Ai\Rewriter::esclusi( $dbApp, $auditApp, 'CNT-01' );

$motivoDi = static function ( $riferimento ) use ( $perche ) {
	foreach ( $perche as $riga ) {
		if ( $riferimento === $riga['riferimento'] ) {
			return $riga['motivo'];
		}
	}

	return '';
};

verifica( 'si elencano tutte e quattro le occorrenze', 4 === count( $perche ), json_encode( $perche ) );
verifica( 'di una pagina si dice che e una pagina', false !== strpos( $motivoDi( '/chi-siamo/' ), 'è una pagina' ), $motivoDi( '/chi-siamo/' ) );
verifica( 'di un articolo mai classificato si dice quello', false !== strpos( $motivoDi( '/nota/' ), 'triage' ), $motivoDi( '/nota/' ) );
verifica( 'di uno gia sistemato si dice che e sistemato', false !== strpos( $motivoDi( '/uno/' ), 'già sistemato' ), $motivoDi( '/uno/' ) );
verifica( 'di un riferimento sparito si dice che non c e piu', false !== strpos( $motivoDi( '/sparito/' ), 'non risulta' ), $motivoDi( '/sparito/' ) );

// Il titolo serve a riconoscerlo: un percorso da solo non basta.
verifica(
	'ogni riga porta il titolo del contenuto',
	'Chi siamo' === ( array_values( array_filter( $perche, static fn( $r ) => '/chi-siamo/' === $r['riferimento'] ) )[0]['titolo'] ?? '' ),
	json_encode( $perche )
);

@unlink( $fileApp );

// ---------------------------------------------------------------------------
// I numeri si aggiornano da soli chiedendo al sito
//
// Rileggere tutto costa minuti e non si puo fare a ogni caricamento. Ma
// alcune domande costano una richiesta sola e rispondono per tutto il sito:
// quelle si fanno a ogni apertura della pagina.

echo "\nL analisi si allinea da sola a quello che il sito ha adesso\n";

$fileAll = sys_get_temp_dir() . '/prova-allinea-' . getmypid() . '.sqlite';
@unlink( $fileAll );
$dbAll = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileAll ) );

$auditAll = $dbAll->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 40 )
);

$dbAll->insert( 'documento', array( 'audit_id' => $auditAll, 'wp_id' => '30', 'tipo' => 'post', 'stato' => 'publish', 'titolo' => 'Con foto', 'percorso' => '/con-foto/', 'url' => 'https://esempio.it/con-foto/', 'ha_thumbnail' => 0 ) );
$dbAll->insert( 'documento', array( 'audit_id' => $auditAll, 'wp_id' => '31', 'tipo' => 'post', 'stato' => 'publish', 'titolo' => 'Senza foto', 'percorso' => '/senza-foto/', 'url' => 'https://esempio.it/senza-foto/', 'ha_thumbnail' => 0 ) );

$mettiRilievo = static function ( $regola, array $riferimenti ) use ( $dbAll, $auditAll ) {
	$rid = $dbAll->insert(
		'rilievo',
		array( 'audit_id' => $auditAll, 'regola' => $regola, 'area' => 'x', 'gravita' => 'high', 'titolo' => $regola, 'perche' => '', 'soluzione' => '', 'automatico' => 1, 'occorrenze' => count( $riferimenti ) )
	);

	foreach ( $riferimenti as $r ) {
		$dbAll->insert( 'occorrenza', array( 'rilievo_id' => $rid, 'riferimento' => $r, 'dettaglio' => '' ) );
	}
};

$mettiRilievo( 'SCH-01', array( '/con-foto/', '/senza-foto/' ) );
$mettiRilievo( 'ONP-07', array( '/vecchio-indirizzo/', '/mai-spostato/' ) );
$mettiRilievo( 'IMG-05', array( '/con-foto/', '/senza-foto/' ) );
$mettiRilievo( 'CNT-01', array( '/con-foto/', '/senza-foto/' ) );
$mettiRilievo( 'ONP-01', array( '/con-foto/', '/senza-foto/' ) );
$mettiRilievo( 'ONP-03', array( '/con-foto/', '/senza-foto/' ) );
$mettiRilievo( 'ONP-09', array( '/con-foto/', '/senza-foto/' ) );

// Un sito che risponde: stampa lo schema, ha un redirect attivo, e uno solo
// dei due articoli ha l immagine in evidenza.
$sitoVivo = new class() extends \SeoGeo\Bridge\WordPress {
	public function __construct() {}

	public function pronto() { return true; }

	public function conteggi() {
		return array( 'sito' => array( 'stampa' => array( 'jsonld' => true, 'alt' => false ) ) );
	}

	public function redirectAttivi() {
		return array( 'percorsi' => array( '/vecchio-indirizzo/' ) );
	}

	public function miniature( array $ids ) {
		return array( 'miniature' => array( '30' => true, '31' => false ) );
	}

	public function misure( array $ids ) {
		return array(
			'misure' => array(
				// Titolo rientrato nei 60, description ancora fuori misura,
				// immagine in evidenza caricata.
				'30' => array( 'titolo_lungh' => 55, 'descr_lungh' => 200, 'chiave_titolo' => true, 'ha_chiave' => true, 'estratto' => true, 'thumbnail' => true, 'parole' => 900, 'h1_testo' => 0, 'h1_tema' => true ),
				// Titolo ancora troppo lungo, e l articolo resta corto.
				// Un H1 scritto nel testo piu quello del tema: due in pagina.
				'31' => array( 'titolo_lungh' => 88, 'descr_lungh' => 140, 'chiave_titolo' => false, 'ha_chiave' => true, 'estratto' => false, 'thumbnail' => false, 'parole' => 120, 'h1_testo' => 1, 'h1_tema' => true ),
			),
		);
	}
};

$cfgAll = array(
	'azienda' => array( 'telefono' => '091 1234567', 'partitaIva' => 'DA_COMPILARE', 'indirizzo' => array( 'via' => 'DA_COMPILARE' ) ),
	'autori'  => array( array( 'nome' => 'DA_COMPILARE', 'ruolo' => 'DA_COMPILARE' ) ),
);

$mettiRilievo( 'LOC-01', array( '(sito)' ) );
$mettiRilievo( 'LOC-02', array( '(sito)' ) );

$esitoAll = \SeoGeo\Allinea::esegui( $dbAll, $sitoVivo, $auditAll, $cfgAll );

$aperte = static function ( $regola ) use ( $dbAll, $auditAll ) {
	return (int) $dbAll->one(
		'SELECT COUNT(*) n FROM occorrenza o JOIN rilievo r ON r.id = o.rilievo_id
		 WHERE r.audit_id = ? AND r.regola = ? AND COALESCE( o.applicato, 0 ) = 0',
		array( $auditAll, $regola )
	)['n'];
};

verifica( 'quello che il plugin stampa si chiude da solo', 0 === $aperte( 'SCH-01' ), (string) $aperte( 'SCH-01' ) );
verifica( 'il title rientrato nei 60 si chiude, quello ancora lungo no', 1 === $aperte( 'ONP-01' ), (string) $aperte( 'ONP-01' ) );
verifica( 'la description fuori misura resta aperta, quella a posto si chiude', 1 === $aperte( 'ONP-03' ), (string) $aperte( 'ONP-03' ) );
verifica( 'l articolo arrivato a 900 parole si chiude, quello a 120 no', 1 === $aperte( 'CNT-01' ), (string) $aperte( 'CNT-01' ) );
verifica( 'un solo H1 in pagina chiude il doppione, due lo lasciano', 1 === $aperte( 'ONP-09' ), (string) $aperte( 'ONP-09' ) );

// Un plugin non aggiornato non manda il conto degli H1. Li non si sa niente,
// e non sapere non e una risoluzione: scritta senza pensarci, la condizione
// «al massimo uno» avrebbe chiuso il rilievo proprio in quel caso.
$fileVecchio = sys_get_temp_dir() . '/prova-vecchio-' . getmypid() . '.sqlite';
@unlink( $fileVecchio );
$dbVecchio = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileVecchio ) );

$auditVecchio = $dbVecchio->insert( 'audit', array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 40 ) );
$dbVecchio->insert( 'documento', array( 'audit_id' => $auditVecchio, 'wp_id' => '60', 'tipo' => 'post', 'stato' => 'publish', 'titolo' => 'Uno', 'percorso' => '/uno/', 'url' => 'https://esempio.it/uno/' ) );
$ridVecchio = $dbVecchio->insert( 'rilievo', array( 'audit_id' => $auditVecchio, 'regola' => 'ONP-09', 'area' => 'onpage', 'gravita' => 'high', 'titolo' => 'x', 'perche' => '', 'soluzione' => '', 'automatico' => 0, 'occorrenze' => 1 ) );
$dbVecchio->insert( 'occorrenza', array( 'rilievo_id' => $ridVecchio, 'riferimento' => '/uno/', 'dettaglio' => '4 tag H1' ) );

$pluginVecchio = new class() extends \SeoGeo\Bridge\WordPress {
	public function __construct() {}

	public function pronto() { return true; }

	public function conteggi() { return array( 'sito' => array( 'stampa' => array() ) ); }

	public function redirectAttivi() { return array( 'percorsi' => array() ); }

	public function miniature( array $ids ) { return array( 'miniature' => array() ); }

	public function misure( array $ids ) {
		// Le misure di prima dell aggiornamento: niente conto degli H1.
		return array( 'misure' => array( '60' => array( 'titolo_lungh' => 40, 'descr_lungh' => 140, 'chiave_titolo' => true, 'ha_chiave' => true, 'estratto' => true, 'thumbnail' => true, 'parole' => 900 ) ) );
	}
};

\SeoGeo\Allinea::esegui( $dbVecchio, $pluginVecchio, $auditVecchio, array() );

verifica(
	'con un plugin vecchio il doppione non si chiude per sbaglio',
	1 === (int) $dbVecchio->one(
		"SELECT COUNT(*) n FROM occorrenza o JOIN rilievo r ON r.id = o.rilievo_id
		 WHERE r.audit_id = ? AND r.regola = 'ONP-09' AND COALESCE( o.applicato, 0 ) = 0",
		array( $auditVecchio )
	)['n']
);

@unlink( \SeoGeo\Allinea::segno( $auditVecchio ) );
@unlink( $fileVecchio );
verifica( 'il redirect attivo si chiude, quello mai fatto no', 1 === $aperte( 'ONP-07' ), (string) $aperte( 'ONP-07' ) );
verifica( 'si chiude solo l articolo che ha davvero la foto', 1 === $aperte( 'IMG-05' ), (string) $aperte( 'IMG-05' ) );
verifica( 'e sul sito viene segnato che la foto c e', 1 === (int) $dbAll->one( 'SELECT ha_thumbnail FROM documento WHERE wp_id = ?', array( '30' ) )['ha_thumbnail'] );
verifica( 'il telefono compilato chiude il rilievo sul telefono', 0 === $aperte( 'LOC-01' ), (string) $aperte( 'LOC-01' ) );
verifica( 'la partita IVA non compilata lo lascia aperto', 1 === $aperte( 'LOC-02' ), (string) $aperte( 'LOC-02' ) );
verifica( 'e si dice che cosa e stato chiuso', isset( $esitoAll['SCH-01'], $esitoAll['ONP-07'], $esitoAll['IMG-05'] ), json_encode( $esitoAll ) );

// Un sito che non risponde non deve far sparire niente: meglio un numero
// alto di un numero inventato.
@unlink( $fileAll );
$dbMuto = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileAll ) );

$auditMuto = $dbMuto->insert( 'audit', array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 40 ) );
$ridMuto   = $dbMuto->insert( 'rilievo', array( 'audit_id' => $auditMuto, 'regola' => 'SCH-01', 'area' => 'x', 'gravita' => 'high', 'titolo' => 'x', 'perche' => '', 'soluzione' => '', 'automatico' => 1, 'occorrenze' => 2 ) );
$dbMuto->insert( 'occorrenza', array( 'rilievo_id' => $ridMuto, 'riferimento' => '/uno/', 'dettaglio' => '' ) );
$dbMuto->insert( 'occorrenza', array( 'rilievo_id' => $ridMuto, 'riferimento' => '/due/', 'dettaglio' => '' ) );

// Serve anche una regola di quelle che si rileggono chiedendo le misure al
// sito, con un documento collegato: senza, la parte che interroga il sito
// non verrebbe nemmeno raggiunta e il collaudo non proverebbe niente.
$dbMuto->insert( 'documento', array( 'audit_id' => $auditMuto, 'wp_id' => '40', 'tipo' => 'post', 'stato' => 'publish', 'titolo' => 'Uno', 'percorso' => '/uno/', 'url' => 'https://esempio.it/uno/' ) );
$ridMisure = $dbMuto->insert( 'rilievo', array( 'audit_id' => $auditMuto, 'regola' => 'ONP-01', 'area' => 'onpage', 'gravita' => 'high', 'titolo' => 'x', 'perche' => '', 'soluzione' => '', 'automatico' => 1, 'occorrenze' => 1 ) );
$dbMuto->insert( 'occorrenza', array( 'rilievo_id' => $ridMisure, 'riferimento' => '/uno/', 'dettaglio' => '' ) );

$sitoRotto = new class() extends \SeoGeo\Bridge\WordPress {
	public function __construct() {}

	public function pronto() { return true; }

	public function conteggi() { throw new \RuntimeException( 'sito irraggiungibile' ); }

	public function redirectAttivi() { throw new \RuntimeException( 'sito irraggiungibile' ); }

	public function miniature( array $ids ) { throw new \RuntimeException( 'sito irraggiungibile' ); }

	public function misure( array $ids ) { throw new \RuntimeException( 'sito irraggiungibile' ); }
};

$esitoMuto = \SeoGeo\Allinea::esegui( $dbMuto, $sitoRotto, $auditMuto, array() );

verifica(
	'se il sito non risponde non si chiude niente',
	3 === (int) $dbMuto->one(
		'SELECT COUNT(*) n FROM occorrenza o JOIN rilievo r ON r.id = o.rilievo_id
		 WHERE r.audit_id = ? AND COALESCE( o.applicato, 0 ) = 0',
		array( $auditMuto )
	)['n'],
	json_encode( $esitoMuto )
);

// E non si richiede al sito a ogni ricarica della pagina.
verifica( 'appena fatto, non si rifa subito', false === \SeoGeo\Allinea::scaduto( $auditMuto ) );

verifica(
	'il plugin sa rispondere con le misure di un contenuto',
	false !== strpos( file_get_contents( __DIR__ . '/../plugin-wordpress/mdi-seo-geo-booster/includes/class-mdi-api.php' ), "public static function misure(" ),
	'la rotta non c e'
);

verifica(
	'e la rotta e registrata',
	false !== strpos( file_get_contents( __DIR__ . '/../plugin-wordpress/mdi-seo-geo-booster/includes/class-mdi-api.php' ), "'/misure'" ),
	'la rotta non e registrata'
);

@unlink( \SeoGeo\Allinea::segno( $auditAll ) );
@unlink( \SeoGeo\Allinea::segno( $auditMuto ) );
@unlink( $fileAll );

// ---------------------------------------------------------------------------
// Premere «Genera» deve generare
//
// Il caso vero: «Stai correggendo GEO-10, riguarda 244 contenuti», si preme e
// la risposta e «0 fatte, 0 da fare: nessun articolo da riscrivere». I 244
// esistono davvero, ma sono tutti classificati «mantenere» dal triage - che e
// giusto, sono articoli buoni a cui manca solo la tabella - e la generazione
// guardava solo «riscrivere» e «accorpare», che erano a zero.

echo "\nPremere Genera su un problema preciso genera davvero\n";

$fileGen = sys_get_temp_dir() . '/prova-genera-' . getmypid() . '.sqlite';
@unlink( $fileGen );
$dbGen = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileGen ) );

$auditGen = $dbGen->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50 )
);

$ridGen = $dbGen->insert(
	'rilievo',
	array( 'audit_id' => $auditGen, 'regola' => 'GEO-10', 'area' => 'generative', 'gravita' => 'high', 'titolo' => 'Nessun blocco di dati sintetici', 'perche' => '', 'soluzione' => '', 'automatico' => 1, 'occorrenze' => 3 )
);

// Tre articoli buoni: il triage li tiene, ma a tutti manca la tabella.
foreach ( array( '/uno/', '/due/', '/tre/' ) as $i => $percorso ) {
	$doc = $dbGen->insert(
		'documento',
		array( 'audit_id' => $auditGen, 'wp_id' => (string) ( 50 + $i ), 'tipo' => 'post', 'stato' => 'publish', 'titolo' => 'Articolo ' . $i, 'percorso' => $percorso, 'url' => 'https://esempio.it' . $percorso, 'parole' => 800, 'testo' => 'testo' )
	);

	$dbGen->insert( 'triage', array( 'audit_id' => $auditGen, 'documento_id' => $doc, 'categoria' => 'mantenere', 'qualita' => 80, 'intento' => 'informazionale', 'motivo' => '', 'azione' => '', 'redirect_a' => '' ) );
	$dbGen->insert( 'occorrenza', array( 'rilievo_id' => $ridGen, 'riferimento' => $percorso, 'dettaglio' => 'nessuna tabella' ) );
}

$senzaRegola = \SeoGeo\Ai\Rewriter::candidati( $dbGen, $auditGen, array() );
$conRegola   = \SeoGeo\Ai\Rewriter::candidati( $dbGen, $auditGen, array( 'regola' => 'GEO-10' ) );

verifica( 'sull archivio intero il triage non ne segnala nessuno', 0 === count( $senzaRegola ), (string) count( $senzaRegola ) );
verifica( 'ma partendo dal problema si lavora su tutti e tre', 3 === count( $conRegola ), (string) count( $conRegola ) );

// Il numero mostrato nel riquadro deve essere quello che verra generato: con
// una bozza gia pronta, due.
$dbGen->insert(
	'bozza',
	array( 'audit_id' => $auditGen, 'documento_id' => $conRegola[0]['doc_id'], 'wp_id' => '50', 'stato' => 'ok', 'modello' => 'x', 'titolo' => 'x', 'corpo_html' => 'x', 'creato_il' => date( 'Y-m-d H:i:s' ) )
);

$dopoBozza = \SeoGeo\Ai\Rewriter::candidati( $dbGen, $auditGen, array( 'regola' => 'GEO-10' ) );

verifica( 'chi ha gia una bozza non viene ricontato', 2 === count( $dopoBozza ), (string) count( $dopoBozza ) );

// E deve comparire fra gli esclusi, col motivo, invece di sparire e basta.
$percheGen = \SeoGeo\Ai\Rewriter::esclusi( $dbGen, $auditGen, 'GEO-10' );

// Chi e gia in coda non e escluso: contarlo fra gli esclusi mostrava lo
// stesso contenuto due volte e gonfiava il numero degli «altri».
$inCodaOra   = array_column( \SeoGeo\Ai\Rewriter::candidati( $dbGen, $auditGen, array( 'regola' => 'GEO-10' ) ), 'doc_id' );
$soloEsclusi = \SeoGeo\Ai\Rewriter::esclusi( $dbGen, $auditGen, 'GEO-10', $inCodaOra );

verifica(
	'chi e in coda non compare fra gli esclusi',
	count( $soloEsclusi ) === count( $percheGen ) - count( $inCodaOra ),
	count( $soloEsclusi ) . ' esclusi, ' . count( $percheGen ) . ' occorrenze, ' . count( $inCodaOra ) . ' in coda'
);

verifica(
	'e chi resta e proprio quello con la bozza gia pronta',
	1 === count( $soloEsclusi ) && false !== strpos( $soloEsclusi[0]['motivo'], 'riscrittura pronta' ),
	json_encode( $soloEsclusi )
);
$conBozza  = array_values( array_filter( $percheGen, static fn( $r ) => false !== strpos( $r['motivo'], 'riscrittura pronta' ) ) );

verifica( 'e di lui si dice che la bozza ce l ha gia', 1 === count( $conBozza ), json_encode( $percheGen ) );

@unlink( $fileGen );

// ---------------------------------------------------------------------------
// L H1 lo stampa il tema, non il testo salvato
//
// «H1 non rilevabile nel contenuto: 279 articoli» su un sito di 295. Nei temi
// WordPress l H1 e il titolo dell articolo e lo stampa il tema al momento di
// servire la pagina: nel testo salvato non c e, e non ci deve essere. Il
// rilievo colpiva quasi tutto l archivio e non si poteva chiudere in nessun
// modo, perche non c era niente di rotto.

echo "\nL H1 si chiede al sito, non al testo salvato\n";

$testoLungo = '<h2>Sezione</h2><p>' . str_repeat( 'parola ', 150 ) . '</p>';

$sitoH1 = static function ( $stampa, $contenuto = '' ) use ( $testoLungo ) {
	$contenuto = '' !== $contenuto ? $contenuto : $testoLungo;

	return new Site(
		array(
			'sito'      => array(
				'titolo'  => 'Prova',
				'link'    => 'https://esempio.it',
				'baseUrl' => 'https://esempio.it',
				'autori'  => array(),
				'stampa'  => $stampa,
			),
			'categorie' => array(),
			'tag'       => array(),
			'items'     => array(
				array(
					'wp_id' => '1', 'tipo' => 'post', 'stato' => 'publish',
					'titolo' => 'Articolo', 'slug' => 'articolo',
					'link' => 'https://esempio.it/articolo/',
					'data' => '2026-01-01 10:00:00', 'modificato' => '2026-02-01 10:00:00',
					'autore' => 'Redazione', 'contenuto' => $contenuto, 'estratto' => '',
					'categorie' => array(), 'tag' => array(), 'commenti' => 'closed',
					'genitore' => '0', 'meta' => array(),
				),
			),
		)
	);
};

$regolaH1 = static function ( $id ) {
	foreach ( \SeoGeo\Rules\OnPage::rules() as $regola ) {
		if ( $id === $regola['id'] ) {
			return $regola;
		}
	}

	return null;
};

$onp08 = $regolaH1( 'ONP-08' );
$onp09 = $regolaH1( 'ONP-09' );

// Sito che non dice niente: si guarda il testo, come prima.
$muto = $sitoH1( array() );

verifica(
	'se il sito non dice niente il rilievo resta',
	1 === count( $onp08['check']( $muto ) ),
	json_encode( $onp08['check']( $muto ) )
);

// Sito che dichiara di stampare l H1: non c e niente da segnalare.
$conH1 = $sitoH1( array( 'h1' => true ) );

verifica(
	'se il tema stampa l H1 il rilievo sparisce',
	0 === count( $onp08['check']( $conH1 ) ),
	json_encode( $onp08['check']( $conH1 ) )
);

// E un H1 scritto dentro al testo, col titolo gia stampato dal tema, fa due
// H1 in pagina: quello si segnala.
$doppio = $sitoH1( array( 'h1' => true ), '<h1>Titolo ripetuto</h1><p>' . str_repeat( 'parola ', 150 ) . '</p>' );

verifica(
	'ma un H1 nel testo, col titolo del tema, fa un doppione vero',
	1 === count( $onp09['check']( $doppio ) ),
	json_encode( $onp09['check']( $doppio ) )
);

// Senza titolo stampato dal tema, un H1 solo nel testo e corretto.
$unoSolo = $sitoH1( array(), '<h1>Titolo</h1><p>' . str_repeat( 'parola ', 150 ) . '</p>' );

verifica(
	'mentre senza tema un H1 solo va benissimo',
	0 === count( $onp09['check']( $unoSolo ) ),
	json_encode( $onp09['check']( $unoSolo ) )
);

verifica(
	'e il plugin guarda la pagina vera per rispondere',
	false !== strpos( file_get_contents( __DIR__ . '/../plugin-wordpress/mdi-seo-geo-booster/includes/class-mdi-api.php' ), 'private static function quanti_h1_mette_il_tema()' ),
	'il controllo non c e'
);

verifica(
	'se non riesce a leggerla non dice di si',
	false !== strpos( file_get_contents( __DIR__ . '/../plugin-wordpress/mdi-seo-geo-booster/includes/class-mdi-api.php' ), "set_transient( 'mdi_h1_dal_tema', '0', HOUR_IN_SECONDS )" ),
	'un sito illeggibile chiuderebbe il rilievo per sbaglio'
);

// ---------------------------------------------------------------------------
// Revisione di tutte le regole: nel testo salvato o stampato dal sito?
//
// Tre volte in due giorni e saltato fuori lo stesso difetto: una regola che
// cerca dentro al testo salvato su WordPress una cosa che il sito produce al
// momento di servire la pagina. Non si trova mai, il rilievo non si chiude
// mai, e chi guarda smette di credere ai numeri.
//
// Qui si passa in rassegna ogni regola che cerca markup nel contenuto e si
// verifica che sia protetta: se il sito dichiara di stamparla, non si segnala.

echo "\nNessuna regola cerca nel testo salvato quello che stampa il sito\n";

$sorgentiRegole = array();

foreach ( glob( __DIR__ . '/../src/Rules/*.php' ) as $fileRegola ) {
	$sorgentiRegole[ basename( $fileRegola ) ] = file_get_contents( $fileRegola );
}

// Ogni regola che fruga in $d['contenuto'] cerca markup: quel markup o sta
// davvero nel testo (uno <style> incollato dentro) oppure lo stampa il sito.
// Nel secondo caso ci vuole la guardia.
$cercanoNelMarkup = array(
	'EAT-01' => 'autore',
	'GEO-07' => 'jsonld',
	'GEO-08' => 'jsonld',
	'LOC-03' => 'local',
	'LOC-04' => 'local',
	'SCH-01' => 'jsonld',
	'SCH-02' => 'jsonld',
	'SCH-03' => 'jsonld',
	'SCH-04' => 'jsonld',
	'SCH-05' => 'opengraph',
	'TEC-02' => 'robots',
	'TEC-03' => 'robots',
	'TEC-04' => 'canonical',
	'ONP-08' => 'h1',
);

$tutteLeRegole = implode( "\n", $sorgentiRegole );

foreach ( $cercanoNelMarkup as $regola => $cosa ) {
	// Si isola il pezzo di sorgente della regola: dal suo identificativo
	// fino a quello successivo.
	$da = strpos( $tutteLeRegole, "'id' => '" . $regola . "'" );

	if ( false === $da ) {
		verifica( "la regola $regola esiste ancora", false, 'non trovata nel sorgente' );
		continue;
	}

	$prossimo = strpos( $tutteLeRegole, "'id' => '", $da + 20 );
	$pezzo    = substr( $tutteLeRegole, $da, false === $prossimo ? null : $prossimo - $da );

	verifica(
		"$regola non segnala quello che il sito stampa ($cosa)",
		false !== strpos( $pezzo, "loFaIlSito( \$s, '" . $cosa . "' )" ),
		"manca la guardia su '$cosa'"
	);
}

// E ogni regola protetta deve anche chiudersi da sola sulle analisi gia
// fatte: senza, resta segnata finche non si rilegge tutto il sito.
$mappaAllinea = \SeoGeo\Allinea::DAL_PLUGIN;

foreach ( $cercanoNelMarkup as $regola => $cosa ) {
	verifica(
		"$regola si chiude da sola anche sulle analisi vecchie",
		isset( $mappaAllinea[ $regola ] ) && $cosa === $mappaAllinea[ $regola ],
		isset( $mappaAllinea[ $regola ] ) ? 'e collegata a ' . $mappaAllinea[ $regola ] : 'non e in elenco'
	);
}

// ---------------------------------------------------------------------------
// «Correggi tutto» deve voler dire tutto
//
// Il pilota prendeva solo gli articoli che il triage segna «da riscrivere» o
// «da accorpare». Su un archivio curato quelli sono pochi - sul sito vero 104
// su 295 - e i restanti, classificati «da mantenere» perche sono articoli
// buoni, restavano fuori anche con tre o quattro rilievi aperti ciascuno.

echo "\nCorreggi tutto prende chi ha un problema, non chi e in una categoria\n";

$fileTutto = sys_get_temp_dir() . '/prova-tutto-' . getmypid() . '.sqlite';
@unlink( $fileTutto );
$dbTutto = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileTutto ) );

$auditTutto = $dbTutto->insert(
	'audit',
	array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50 )
);

$ridTutto = $dbTutto->insert(
	'rilievo',
	array( 'audit_id' => $auditTutto, 'regola' => 'GEO-04', 'area' => 'generative', 'gravita' => 'high', 'titolo' => 'Nessuna FAQ', 'perche' => '', 'soluzione' => '', 'automatico' => 1, 'occorrenze' => 3 )
);

// Tre articoli: uno solo e segnato dal triage come da riscrivere, gli altri
// due sono «da mantenere» ma hanno lo stesso problema aperto.
foreach ( array(
	array( '/uno/', 'riscrivere' ),
	array( '/due/', 'mantenere' ),
	array( '/tre/', 'mantenere' ),
) as $i => $coppia ) {
	list( $percorso, $categoria ) = $coppia;

	$doc = $dbTutto->insert(
		'documento',
		array( 'audit_id' => $auditTutto, 'wp_id' => (string) ( 70 + $i ), 'tipo' => 'post', 'stato' => 'publish', 'titolo' => 'Articolo ' . $i, 'percorso' => $percorso, 'url' => 'https://esempio.it' . $percorso, 'parole' => 700, 'testo' => 'testo' )
	);

	$dbTutto->insert( 'triage', array( 'audit_id' => $auditTutto, 'documento_id' => $doc, 'categoria' => $categoria, 'qualita' => 70, 'intento' => 'informazionale', 'motivo' => '', 'azione' => '', 'redirect_a' => '' ) );
	$dbTutto->insert( 'occorrenza', array( 'rilievo_id' => $ridTutto, 'riferimento' => $percorso, 'dettaglio' => '' ) );
}

$soloTriage = \SeoGeo\Ai\Rewriter::candidati( $dbTutto, $auditTutto, array() );
$conProblemi = \SeoGeo\Ai\Rewriter::daCorreggere( $dbTutto, $auditTutto );

verifica( 'il criterio vecchio ne prende uno solo', 1 === count( $soloTriage ), (string) count( $soloTriage ) );
verifica( 'quello nuovo prende tutti e tre', 3 === count( $conProblemi ), (string) count( $conProblemi ) );

// Il difetto che ha prodotto 103 errori: il pilota metteva in coda articoli
// classificati «da mantenere», e chi li doveva riscrivere li riscartava per
// categoria. Chiedere un contenuto preciso vuol dire che la scelta e gia
// stata fatta: non si rifiltra.
$mantenere = null;

foreach ( $conProblemi as $riga ) {
	if ( '/due/' === $riga['percorso'] ) {
		$mantenere = (int) $riga['doc_id'];
	}
}

verifica(
	'un contenuto chiesto per identificativo non viene scartato per categoria',
	1 === count( \SeoGeo\Ai\Rewriter::candidati( $dbTutto, $auditTutto, array( 'solo_documento' => $mantenere ) ) ),
	'il documento «da mantenere» sparisce quando lo si chiede per id'
);

// Chi ha piu problemi aperti viene prima: e li che il giro rende di piu.
$ridSecondo = $dbTutto->insert(
	'rilievo',
	array( 'audit_id' => $auditTutto, 'regola' => 'GEO-10', 'area' => 'generative', 'gravita' => 'medium', 'titolo' => 'Nessuna tabella', 'perche' => '', 'soluzione' => '', 'automatico' => 1, 'occorrenze' => 1 )
);
$dbTutto->insert( 'occorrenza', array( 'rilievo_id' => $ridSecondo, 'riferimento' => '/tre/', 'dettaglio' => '' ) );

$ordinati = \SeoGeo\Ai\Rewriter::daCorreggere( $dbTutto, $auditTutto );

verifica( 'e mette per primo quello messo peggio', '/tre/' === $ordinati[0]['percorso'], json_encode( array_column( $ordinati, 'percorso' ) ) );

// Chi ha gia una bozza pronta non si rifa.
$dbTutto->insert(
	'bozza',
	array( 'audit_id' => $auditTutto, 'documento_id' => $ordinati[0]['doc_id'], 'wp_id' => '72', 'stato' => 'ok', 'modello' => 'x', 'titolo' => 'x', 'corpo_html' => 'x', 'creato_il' => date( 'Y-m-d H:i:s' ) )
);

verifica( 'chi ha gia la bozza non torna in coda', 2 === count( \SeoGeo\Ai\Rewriter::daCorreggere( $dbTutto, $auditTutto ) ) );

// Le occorrenze gia chiuse non rimettono in coda nessuno.
\SeoGeo\Applicato::chiudi( $dbTutto, $auditTutto, array( 'GEO-04' ), array( '/due/' ) );

verifica( 'e chi e gia stato sistemato nemmeno', 1 === count( \SeoGeo\Ai\Rewriter::daCorreggere( $dbTutto, $auditTutto ) ) );

// Le pagine restano fuori: e una scelta, non una dimenticanza.
$docPagina = $dbTutto->insert(
	'documento',
	array( 'audit_id' => $auditTutto, 'wp_id' => '99', 'tipo' => 'page', 'stato' => 'publish', 'titolo' => 'Servizi', 'percorso' => '/servizi/', 'url' => 'https://esempio.it/servizi/', 'parole' => 700, 'testo' => 'testo' )
);
$dbTutto->insert( 'triage', array( 'audit_id' => $auditTutto, 'documento_id' => $docPagina, 'categoria' => 'mantenere', 'qualita' => 70, 'intento' => '', 'motivo' => '', 'azione' => '', 'redirect_a' => '' ) );
$dbTutto->insert( 'occorrenza', array( 'rilievo_id' => $ridTutto, 'riferimento' => '/servizi/', 'dettaglio' => '' ) );

verifica(
	'le pagine restano fuori anche da «tutto»',
	0 === count( array_filter( \SeoGeo\Ai\Rewriter::daCorreggere( $dbTutto, $auditTutto ), static fn( $r ) => '/servizi/' === $r['percorso'] ) )
);

// Il pulsante deve esistere e passare l opzione al pilota.
$vistaAudit = file_get_contents( __DIR__ . '/../views/audit.php' );

// Il confronto con l analisi precedente: senza, «prima 1.290 adesso 1.910»
// resta senza risposta e sembra che il sito sia peggiorato.
$auditPrima = $dbTutto->insert( 'audit', array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => '2026-09-01 10:00:00', 'punteggio' => 60 ) );
$dbTutto->insert( 'rilievo', array( 'audit_id' => $auditPrima, 'regola' => 'GEO-04', 'area' => 'x', 'gravita' => 'high', 'titolo' => 'Nessuna FAQ', 'perche' => '', 'soluzione' => '', 'automatico' => 1, 'occorrenze' => 3 ) );
$dbTutto->insert( 'rilievo', array( 'audit_id' => $auditPrima, 'regola' => 'ONP-01', 'area' => 'x', 'gravita' => 'high', 'titolo' => 'Title lungo', 'perche' => '', 'soluzione' => '', 'automatico' => 1, 'occorrenze' => 50 ) );

$differenze = \SeoGeo\Applicato::confronto( $dbTutto, $auditTutto, $auditPrima );

$rigaDi = static function ( $regola ) use ( $differenze ) {
	foreach ( $differenze as $riga ) {
		if ( $regola === $riga['regola'] ) {
			return $riga;
		}
	}

	return null;
};

verifica( 'il confronto mostra le regole comparse adesso', null !== $rigaDi( 'GEO-10' ) && 1 === $rigaDi( 'GEO-10' )['adesso'] && 0 === $rigaDi( 'GEO-10' )['prima'], json_encode( $differenze ) );
verifica( 'e quelle sparite', null !== $rigaDi( 'ONP-01' ) && 0 === $rigaDi( 'ONP-01' )['adesso'] && -50 === $rigaDi( 'ONP-01' )['differenza'], json_encode( $rigaDi( 'ONP-01' ) ) );
verifica( 'chi non e cambiato non compare', null === $rigaDi( 'GEO-04' ), json_encode( $rigaDi( 'GEO-04' ) ) );
verifica( 'e il cambiamento piu grosso viene per primo', 'ONP-01' === $differenze[0]['regola'], json_encode( array_column( $differenze, 'regola' ) ) );

verifica( 'il pulsante unico c e', false !== strpos( $vistaAudit, 'Correggi tutto e pubblica' ) );
verifica( 'e chiede al pilota di lavorare su tutto l archivio', false !== strpos( $vistaAudit, "name=\"tutto_larchivio\"" ) );
verifica( 'e il pilota legge quell opzione', false !== strpos( $indiceSorgente, "'tutto_larchivio' => ! empty( \$_POST['tutto_larchivio'] )" ) );
// Il numero e il costo devono uscire dallo stesso elenco: due conti fatti da
// due parti diverse finiscono per non tornare, ed e successo - «164 articoli»
// accanto a «0,00 €», perche si leggeva una chiave che quella funzione non
// restituisce e un «?? 0» copriva il buco.
verifica(
	'il costo esce dallo stesso elenco del numero',
	false !== strpos( $vistaAudit, "\$costoStima           = \SeoGeo\Ai\Rewriter::stima( \$articoliDaCorreggere" ),
	'numero e costo vengono da due conti diversi'
);

// E le due funzioni che si chiamano «stima» devono almeno rispondere con le
// stesse chiavi, cosi leggerne una al posto dell altra non da zero in
// silenzio.
$chiaviRewriter = array_keys( \SeoGeo\Ai\Rewriter::stima( array(), array( 'prezzo_per_milione' => array( 'input' => 1, 'output' => 2 ) ) ) );
$chiaviCoda     = array_keys( \SeoGeo\Coda::stima( $dbTutto, $auditTutto, array( 'ai' => array( 'prezzo_per_milione' => array( 'input' => 1, 'output' => 2 ) ) ) ) );

verifica(
	'le due stime rispondono con le stesse chiavi principali',
	array() === array_diff( array( 'articoli', 'costo_stimato' ), $chiaviRewriter )
		&& array() === array_diff( array( 'articoli', 'costo_stimato' ), $chiaviCoda ),
	json_encode( array( 'rewriter' => $chiaviRewriter, 'coda' => $chiaviCoda ) )
);

@unlink( $fileTutto );

// ---------------------------------------------------------------------------
// «Due plugin SEO attivi» va detto solo se e vero
//
// La regola guardava i postmeta: se c erano sia rank_math_title sia
// _yoast_wpseo_title concludeva che i due plugin erano entrambi attivi. Ma un
// Yoast disinstallato lascia i suoi postmeta nel database per sempre. Il
// gestionale segnalava un conflitto critico a chi aveva un plugin solo.

echo "\nIl conflitto fra plugin SEO si chiede al sito\n";

$sitoSeo = static function ( $attivi, $metaContenuto ) {
	return new Site(
		array(
			'sito'      => array(
				'titolo'     => 'Prova',
				'link'       => 'https://esempio.it',
				'baseUrl'    => 'https://esempio.it',
				'autori'     => array(),
				'seo_attivi' => $attivi,
			),
			'categorie' => array(),
			'tag'       => array(),
			'items'     => array(
				array(
					'wp_id' => '1', 'tipo' => 'post', 'stato' => 'publish',
					'titolo' => 'Articolo', 'slug' => 'articolo',
					'link' => 'https://esempio.it/articolo/',
					'data' => '2026-01-01 10:00:00', 'modificato' => '2026-02-01 10:00:00',
					'autore' => 'Redazione', 'contenuto' => '<p>testo</p>', 'estratto' => '',
					'categorie' => array(), 'tag' => array(), 'commenti' => 'closed',
					'genitore' => '0', 'meta' => $metaContenuto,
				),
			),
		)
	);
};

$regolaTec = static function ( $id ) {
	foreach ( \SeoGeo\Rules\Technical::rules() as $regola ) {
		if ( $id === $regola['id'] ) {
			return $regola;
		}
	}

	return null;
};

$tec01 = $regolaTec( 'TEC-01' );
$tec09 = $regolaTec( 'TEC-09' );

// Il caso vero: solo Rank Math attivo, ma i postmeta di Yoast sono rimasti.
$conResidui = array( 'rank_math_title' => 'Titolo', '_yoast_wpseo_title' => 'Vecchio titolo' );
$soloRank   = $sitoSeo( array( 'Rank Math' ), $conResidui );

verifica(
	'con un plugin solo attivo non si segnala nessun conflitto',
	0 === count( $tec01['check']( $soloRank ) ),
	json_encode( $tec01['check']( $soloRank ) )
);

verifica(
	'ma i dati rimasti si segnalano, come cosa minore',
	1 === count( $tec09['check']( $soloRank ) ) && false !== strpos( $tec09['check']( $soloRank )[0]['dettaglio'], 'Yoast' ),
	json_encode( $tec09['check']( $soloRank ) )
);

// Due davvero attivi: quello si.
$dueVeri = $sitoSeo( array( 'Rank Math', 'Yoast SEO' ), $conResidui );

verifica(
	'due plugin davvero attivi restano un problema critico',
	1 === count( $tec01['check']( $dueVeri ) ),
	json_encode( $tec01['check']( $dueVeri ) )
);

verifica(
	'e in quel caso non si parla di residui',
	0 === count( $tec09['check']( $dueVeri ) ),
	json_encode( $tec09['check']( $dueVeri ) )
);

// Quando il sito non lo dichiara - export, o plugin non aggiornato - non si
// puo dire ne una cosa ne l altra con certezza: si segnala, ma dicendo che e
// da verificare, e non si parla di residui.
$nonDichiara = $sitoSeo( array(), $conResidui );

verifica(
	'se il sito non lo dichiara si segnala come da verificare',
	1 === count( $tec01['check']( $nonDichiara ) )
		&& false !== strpos( $tec01['check']( $nonDichiara )[0]['dettaglio'], 'da verificare' ),
	json_encode( $tec01['check']( $nonDichiara ) )
);

verifica(
	'e non si accusa nessuno di aver lasciato residui',
	0 === count( $tec09['check']( $nonDichiara ) ),
	json_encode( $tec09['check']( $nonDichiara ) )
);

// Un sito pulito non deve produrre niente.
$pulito = $sitoSeo( array( 'Rank Math' ), array( 'rank_math_title' => 'Titolo' ) );

verifica( 'un sito con un plugin solo e senza residui non segnala niente', 0 === count( $tec01['check']( $pulito ) ) + count( $tec09['check']( $pulito ) ) );

// «Le riscritture sono andate sul sito o no?» e la domanda che si fa chi
// guarda il pilota: deve avere una risposta in cifre, non un registro da
// scorrere.
$fileCoda = sys_get_temp_dir() . '/prova-coda-' . getmypid() . '.sqlite';
@unlink( $fileCoda );
$dbCoda = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileCoda ) );

$auditCoda = $dbCoda->insert( 'audit', array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => date( 'Y-m-d H:i:s' ), 'punteggio' => 50 ) );

foreach ( array(
	array( 'applica_bozza', 'fatto' ),
	array( 'applica_bozza', 'fatto' ),
	array( 'applica_bozza', 'saltato' ),
	array( 'applica_bozza', 'attesa' ),
	array( 'bozza', 'fatto' ),
) as $i => $riga ) {
	$dbCoda->insert(
		'coda',
		array( 'audit_id' => $auditCoda, 'ordine' => $i, 'tipo' => $riga[0], 'riferimento' => (string) $i, 'etichetta' => 'x', 'stato' => $riga[1], 'messaggio' => '', 'creato_il' => date( 'Y-m-d H:i:s' ), 'eseguito_il' => '' )
	);
}

$statoCoda = \SeoGeo\Coda::stato( $dbCoda, $auditCoda );

verifica( 'si dice quante riscritture sono davvero online', 2 === $statoCoda['pubblicate']['fatte'], json_encode( $statoCoda['pubblicate'] ) );
verifica( 'e quante non ce l hanno fatta', 1 === $statoCoda['pubblicate']['non_fatte'], json_encode( $statoCoda['pubblicate'] ) );
verifica( 'quelle ancora in coda non si contano da nessuna delle due parti', 3 === $statoCoda['pubblicate']['fatte'] + $statoCoda['pubblicate']['non_fatte'], json_encode( $statoCoda['pubblicate'] ) );

@unlink( $fileCoda );

verifica(
	'e il plugin sa dire quali plugin SEO sono caricati',
	false !== strpos( file_get_contents( __DIR__ . '/../plugin-wordpress/mdi-seo-geo-booster/includes/class-mdi-api.php' ), 'public static function plugin_seo_attivi()' )
);

// ---------------------------------------------------------------------------
// Il gestionale non deve scrivere meta che le sue stesse regole bocciano
//
// Il pilota ha scritto le meta di tutto l archivio e al giro dopo le
// description fuori misura erano passate da 47 a 103. Non era il sito a
// peggiorare: era il generatore che, sugli articoli con poco testo, produceva
// description di un centinaio di caratteri - sotto la soglia della regola
// ONP-03, che quindi segnalava come sbagliato il lavoro appena fatto.

echo "\nLe meta scritte dal gestionale superano le regole del gestionale\n";

$cfgMeta = require __DIR__ . '/../config.php';

// Casi difficili di proposito: titoli cortissimi, testo quasi assente,
// titoli lunghissimi, caratteri accentati.
$casiMeta = array(
	array( 'SEO e SEM', '<p>x</p>' ),
	array( 'Blog', '' ),
	array( 'Siti web', '<p></p>' ),
	array( 'Contatti', '<p>Scrivici.</p>' ),
	array( 'Perché la comunicazione è così importante per un’agenzia?', '<p>Poco testo.</p>' ),
	array( 'Marketing per Podologi Palermo: La Tua Strategia Digitale', '<p>Poco testo qui dentro.</p>' ),
	array( 'Digitale e Tradizione: Come Promuovere un’Attività Storica con Nuove Strategie Digitali Molto Efficaci', '<p>' . str_repeat( 'Frase di prova sul marketing digitale a Palermo. ', 30 ) . '</p>' ),
	array( 'Quanto costa farsi gestire un profilo Instagram?', '<p>' . str_repeat( 'Una frase. ', 3 ) . '</p>' ),
);

$fuoriMisura = array();

foreach ( $casiMeta as $n => $caso ) {
	$sitoMeta = new Site(
		array(
			'sito'      => array( 'titolo' => 'Prova', 'link' => 'https://esempio.it', 'baseUrl' => 'https://esempio.it', 'autori' => array() ),
			'categorie' => array(),
			'tag'       => array(),
			'items'     => array(
				array(
					'wp_id' => (string) ( $n + 1 ), 'tipo' => 'post', 'stato' => 'publish',
					'titolo' => $caso[0], 'slug' => 'articolo-' . $n,
					'link' => 'https://esempio.it/articolo-' . $n . '/',
					'data' => '2026-01-01 10:00:00', 'modificato' => '2026-02-01 10:00:00',
					'autore' => 'Redazione', 'contenuto' => $caso[1], 'estratto' => '',
					'categorie' => array(), 'tag' => array(), 'commenti' => 'closed',
					'genitore' => '0', 'meta' => array(),
				),
			),
		)
	);

	$pianoMeta = \SeoGeo\Fix\Meta::piano( $sitoMeta, $cfgMeta );
	$riga      = $pianoMeta[0];

	$lt = mb_strlen( (string) $riga['title_nuovo'] );
	$ld = mb_strlen( (string) $riga['description_nuova'] );

	// Le stesse soglie delle regole ONP-01, ONP-02, ONP-03 e ONP-04.
	if ( $lt > 60 ) {
		$fuoriMisura[] = $caso[0] . ': title di ' . $lt . ' caratteri (ONP-01)';
	}

	if ( $lt > 0 && $lt < 30 ) {
		$fuoriMisura[] = $caso[0] . ': title di ' . $lt . ' caratteri (ONP-02)';
	}

	if ( 0 === $ld ) {
		$fuoriMisura[] = $caso[0] . ': nessuna description (ONP-04)';
	} elseif ( $ld > 158 || $ld < 120 ) {
		$fuoriMisura[] = $caso[0] . ': description di ' . $ld . ' caratteri (ONP-03)';
	}
}

verifica(
	'nessuna delle meta generate viola una regola dell audit',
	0 === count( $fuoriMisura ),
	implode( ' | ', $fuoriMisura )
);

// ---------------------------------------------------------------------------
// Un pulsante non deve invitare a rifare una cosa gia fatta
//
// «0 da inviare · 100 gia online», e sotto un pulsante verde che dice
// «Sovrascrivi tutte le 100». Chi legge preme, e riscrive cento articoli
// identici a se stessi aggiornando cento date di modifica per niente.

echo "\nIl pulsante in blocco dice se c e davvero qualcosa da mandare\n";

$vistaConfronto = file_get_contents( __DIR__ . '/../views/confronto-bozze.php' );

verifica(
	'si conta quante bozze del lotto non sono mai state mandate',
	false !== strpos( $vistaConfronto, '$nuove_in_lotto = count( array_filter( $in_lotto' ),
	'il conteggio non c e'
);

verifica(
	'e quando sono zero il pulsante cambia parole',
	false !== strpos( $vistaConfronto, "'Riscrivi di nuovo '" ),
	'il pulsante dice ancora «Sovrascrivi»'
);

verifica(
	'il pulsante smette di essere quello principale',
	false !== strpos( $vistaConfronto, "0 === \$nuove_in_lotto && 'da-ripulire' !== \$filtro ? ' chiaro' : ''" ),
	'resta verde come se fosse la cosa da fare'
);

verifica(
	'e si dice che cosa succede davvero premendolo',
	false !== strpos( $vistaConfronto, 'aggiorna la data di modifica di' ),
	'non si spiega la conseguenza'
);

verifica(
	'chiedendo conferma prima di rifare il lavoro',
	false !== strpos( $vistaConfronto, 'tutte.dataset.giaOnline' ),
	'parte senza chiedere niente'
);

// La vista «da ripulire» e il caso opposto: li sono tutte gia online per
// costruzione, e rimandarle e proprio il lavoro da fare.
verifica(
	'ma nella vista «da ripulire» rimandarle resta la cosa giusta',
	false !== strpos( $vistaConfronto, "'da-ripulire' === \$filtro" ),
	'la vista da ripulire non e distinta'
);

// ---------------------------------------------------------------------------
// Le riscritture seguono il sito, non l analisi in cui sono nate
//
// «Correggo, poi rileggo, e li ritrovo tutti: sembra che non salvi». Non era
// un problema di salvataggio: ogni rilettura crea un analisi nuova, e le
// riscritture restavano attaccate a quella vecchia. Sparivano dalla vista, la
// riscrittura assistita ripartiva da zero e si ripagava Gemini per rifare un
// lavoro gia fatto.

echo "\nLe riscritture passano da un analisi alla successiva\n";

$fileCont = sys_get_temp_dir() . '/prova-continuita-' . getmypid() . '.sqlite';
@unlink( $fileCont );
$dbCont = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileCont ) );

$auditVecchio = $dbCont->insert( 'audit', array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => '2026-09-15 10:00:00', 'punteggio' => 50 ) );
$auditNuovo   = $dbCont->insert( 'audit', array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => '2026-09-16 10:00:00', 'punteggio' => 50 ) );
$auditAltro   = $dbCont->insert( 'audit', array( 'sito_nome' => 'Altro', 'sito_url' => 'https://altrosito.it', 'creato_il' => '2026-09-16 11:00:00', 'punteggio' => 50 ) );

// Gli stessi due articoli, letti due volte: il numero di riga cambia, l
// identificativo WordPress no.
$docVecchio = array();
$docNuovo   = array();

foreach ( array( '80', '81' ) as $wp ) {
	$docVecchio[ $wp ] = $dbCont->insert( 'documento', array( 'audit_id' => $auditVecchio, 'wp_id' => $wp, 'tipo' => 'post', 'stato' => 'publish', 'titolo' => 'Articolo ' . $wp, 'percorso' => '/a-' . $wp . '/', 'url' => 'https://esempio.it/a-' . $wp . '/' ) );
	$docNuovo[ $wp ]   = $dbCont->insert( 'documento', array( 'audit_id' => $auditNuovo, 'wp_id' => $wp, 'tipo' => 'post', 'stato' => 'publish', 'titolo' => 'Articolo ' . $wp, 'percorso' => '/a-' . $wp . '/', 'url' => 'https://esempio.it/a-' . $wp . '/' ) );
}

$dbCont->insert( 'bozza', array( 'audit_id' => $auditVecchio, 'documento_id' => $docVecchio['80'], 'wp_id' => '80', 'stato' => 'ok', 'modello' => 'gemini', 'titolo' => 'Titolo riscritto', 'corpo_html' => '<p>Testo nuovo</p>', 'parole' => 900, 'token_in' => 1000, 'token_out' => 2000, 'creato_il' => '2026-09-15 11:00:00', 'inviata_il' => '2026-09-15 12:00:00' ) );
$dbCont->insert( 'bozza', array( 'audit_id' => $auditVecchio, 'documento_id' => $docVecchio['81'], 'wp_id' => '81', 'stato' => 'ok', 'modello' => 'gemini', 'titolo' => 'Altro titolo', 'corpo_html' => '<p>Altro testo</p>', 'parole' => 800, 'creato_il' => '2026-09-15 11:05:00' ) );
// Una fallita non si porta avanti: non c e niente da salvare.
$dbCont->insert( 'bozza', array( 'audit_id' => $auditVecchio, 'documento_id' => $docVecchio['81'], 'wp_id' => '81', 'stato' => 'errore', 'modello' => 'gemini', 'titolo' => '', 'corpo_html' => '', 'errore' => 'il modello non ha risposto', 'creato_il' => '2026-09-15 11:06:00' ) );

$portate = \SeoGeo\Continuita::riportaBozze( $dbCont, $auditNuovo, $auditVecchio );

$bozzeNuove = $dbCont->all( 'SELECT * FROM bozza WHERE audit_id = ? ORDER BY documento_id', array( $auditNuovo ) );

verifica( 'le riscritture riuscite passano alla nuova analisi', 2 === $portate && 2 === count( $bozzeNuove ), $portate . ' portate, ' . count( $bozzeNuove ) . ' presenti' );
verifica( 'si riattaccano al contenuto giusto', $docNuovo['80'] === (int) $bozzeNuove[0]['documento_id'], json_encode( array_column( $bozzeNuove, 'documento_id' ) ) );
verifica( 'col testo intatto', '<p>Testo nuovo</p>' === $bozzeNuove[0]['corpo_html'], (string) $bozzeNuove[0]['corpo_html'] );
verifica( 'e chi era gia online resta segnato come tale', '2026-09-15 12:00:00' === $bozzeNuove[0]['inviata_il'], (string) $bozzeNuove[0]['inviata_il'] );
verifica( 'quella fallita non si porta avanti', 0 === count( array_filter( $bozzeNuove, static fn( $b ) => 'ok' !== $b['stato'] ) ) );

// Rifarlo non deve creare doppioni.
\SeoGeo\Continuita::riportaBozze( $dbCont, $auditNuovo, $auditVecchio );

verifica( 'ripetere l operazione non fa doppioni', 2 === (int) $dbCont->one( 'SELECT COUNT(*) n FROM bozza WHERE audit_id = ?', array( $auditNuovo ) )['n'] );

// Un altro sito non c entra niente.
verifica( 'le analisi di un altro sito non si mescolano', 0 === \SeoGeo\Continuita::precedente( $dbCont, $auditAltro, 'https://altrosito.it' ) );
verifica( 'mentre dello stesso sito si trova quella prima', $auditVecchio === \SeoGeo\Continuita::precedente( $dbCont, $auditNuovo, 'https://esempio.it' ) );

// Un articolo cancellato dal sito non deve far fallire niente.
$dbCont->run( 'DELETE FROM documento WHERE audit_id = ? AND wp_id = ?', array( $auditNuovo, '81' ) );
$dbCont->run( 'DELETE FROM bozza WHERE audit_id = ?', array( $auditNuovo ) );

verifica( 'un contenuto sparito dal sito si salta senza rompere niente', 1 === \SeoGeo\Continuita::riportaBozze( $dbCont, $auditNuovo, $auditVecchio ) );

// Chi guarda un analisi vecchia deve saperlo: si puo restare per ore sul
// pilota di un analisi superata e lavorare su dati di ieri.
$dbCont2 = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileCont ) );

verifica(
	'di un analisi vecchia si sa che ce n e una piu recente',
	$auditNuovo === (int) ( \SeoGeo\Continuita::piuRecente( $dbCont2, $auditVecchio, 'https://esempio.it' )['id'] ?? 0 )
);

verifica(
	'e della piu recente non si dice niente',
	array() === \SeoGeo\Continuita::piuRecente( $dbCont2, $auditNuovo, 'https://esempio.it' )
);

verifica(
	'l avviso compare su tutte le pagine, non solo su una',
	false !== strpos( file_get_contents( __DIR__ . '/../views/layout.php' ), "Stai guardando un'analisi vecchia" ),
	'l avviso non e nel contorno comune'
);

// E il salvataggio di un analisi lo fa da solo, da qualunque strada arrivi.
verifica(
	'il salvataggio dell analisi lo fa da solo',
	false !== strpos( file_get_contents( __DIR__ . '/../src/Audit.php' ), 'Continuita::riportaBozze( $db, $auditId, $precedente )' ),
	'chi salva un analisi puo dimenticarsene'
);

@unlink( $fileCont );

// ---------------------------------------------------------------------------
// La riscrittura deve chiudere i problemi per cui e nata
//
// «Li correggo e riappaiono sempre». La bozza veniva salvata qualunque cosa
// contenesse: a un articolo segnalato «sotto le 600 parole» il modello poteva
// rispondere con 300, e il rilievo era ancora li alla rilettura dopo. Ogni
// giro costava token e non chiudeva niente.

echo "\nLa riscrittura si controlla da sola\n";

use SeoGeo\Ai\Chiusura;

$articoloProva = array(
	'wp_id'    => '77',
	'titolo'   => 'Come scegliere un fornitore',
	'slug'     => 'come-scegliere-un-fornitore',
	'url'      => 'https://esempio.it/come-scegliere-un-fornitore/',
	'percorso' => '/come-scegliere-un-fornitore/',
	'focus'    => 'scegliere un fornitore',
);

// Quello che una riscrittura non puo chiudere da sola non deve nemmeno
// entrare nel controllo: si ritenterebbe all infinito qualcosa che qui non si
// risolve.
verifica(
	'le regole che la riscrittura non puo chiudere restano fuori',
	array( 'CNT-02', 'ONP-10' ) === Chiusura::verificabili( array( 'CNT-02', 'IMG-05', 'CNT-03', 'GEO-11', 'ONP-10' ) ),
	json_encode( Chiusura::verificabili( array( 'CNT-02', 'IMG-05', 'CNT-03', 'GEO-11', 'ONP-10' ) ) )
);

$bozzaScarsa = array(
	'titolo'     => 'Come scegliere un fornitore',
	'in_breve'   => '',
	'corpo_html' => '<p>' . str_repeat( 'Una frase breve di prova. ', 60 ) . '</p>',
	'faq'        => array(),
);

$aperteScarsa = Chiusura::controlla( $bozzaScarsa, $articoloProva, array( 'CNT-02', 'ONP-10', 'GEO-03', 'GEO-04', 'GEO-10' ) );
$regoleAperte = array_column( $aperteScarsa, 'regola' );

verifica( 'una bozza corta lascia aperto il rilievo sulla lunghezza', in_array( 'CNT-02', $regoleAperte, true ), json_encode( $regoleAperte ) );
verifica( 'senza H2 resta aperto anche quello sulla struttura', in_array( 'ONP-10', $regoleAperte, true ), json_encode( $regoleAperte ) );
verifica( 'e senza sintesi iniziale quello sulla risposta diretta', in_array( 'GEO-03', $regoleAperte, true ), json_encode( $regoleAperte ) );

// Il dettaglio serve al secondo tentativo: «l articolo e corto» non si puo
// verificare, «ha 300 parole» si.
verifica(
	'di ogni punto aperto si dice la misura, non un giudizio',
	'' !== (string) $aperteScarsa[0]['dettaglio'],
	json_encode( $aperteScarsa[0] )
);

verifica(
	'e l istruzione per il secondo tentativo la riporta',
	false !== strpos( Chiusura::istruzioni( $aperteScarsa ), '600' ),
	Chiusura::istruzioni( $aperteScarsa )
);

// Le domande frequenti non stanno nel corpo: stanno in un campo a parte e
// diventano H2 e H3 solo quando il plugin scrive l articolo. Misurando il
// solo corpo_html, GEO-04 risultava aperto su ogni bozza che le domande le
// aveva davvero.
$bozzaBuona = array(
	'titolo'     => 'Come scegliere un fornitore',
	'in_breve'   => 'In breve: guarda referenze, tempi e assistenza prima del prezzo, e chiedi sempre due preventivi confrontabili sullo stesso perimetro di lavoro.',
	'corpo_html' => '<h2>Le referenze</h2><p>' . str_repeat( 'Chiedi i lavori gia fatti. ', 90 )
		. '</p><h2>I tempi</h2><ul><li>Consegna</li><li>Assistenza</li></ul><p>' . str_repeat( 'Metti per iscritto le scadenze. ', 90 )
		. '</p><table><tr><td>Voce</td><td>Tempo</td></tr></table>',
	'faq'        => array( array( 'domanda' => 'Quanto tempo serve?', 'risposta' => 'Dipende dal perimetro.' ) ),
);

$aperteBuona = Chiusura::controlla( $bozzaBuona, $articoloProva, array( 'CNT-02', 'ONP-10', 'GEO-03', 'GEO-04', 'GEO-10' ) );

verifica( 'una bozza completa non lascia aperto niente', array() === $aperteBuona, json_encode( $aperteBuona ) );

// Le domande stanno nel campo faq: cercarle nel solo corpo le perderebbe.
verifica(
	'le domande frequenti contano anche se stanno nel campo a parte',
	false !== strpos( Chiusura::testoCompleto( $bozzaBuona ), 'Quanto tempo serve?' )
);

// L H1 lo stampa gia il tema col titolo: scriverne uno nel corpo ne fa due in
// pagina. Quando non si sa che cosa stampa il sito si prende la lettura piu
// severa, perche una bozza senza H1 va bene in tutti e due i casi.
$bozzaConH1 = $bozzaBuona;
$bozzaConH1['corpo_html'] = '<h1>Come scegliere un fornitore</h1>' . $bozzaBuona['corpo_html'];

verifica(
	'un H1 scritto nel corpo non passa',
	array( 'ONP-09' ) === array_column( Chiusura::controlla( $bozzaConH1, $articoloProva, array( 'ONP-09' ) ), 'regola' )
);

verifica(
	'mentre senza H1 nel corpo va bene',
	array() === Chiusura::controlla( $bozzaBuona, $articoloProva, array( 'ONP-09' ) )
);

// E il giro intero: il modello risponde male, glielo si dice, risponde bene.
$fileChi = sys_get_temp_dir() . '/prova-chiusura-' . getmypid() . '.sqlite';
@unlink( $fileChi );
$dbChi = new \SeoGeo\Db( array( 'driver' => 'sqlite', 'sqlite' => $fileChi ) );

$auditChi = $dbChi->insert( 'audit', array( 'sito_nome' => 'Prova', 'sito_url' => 'https://esempio.it', 'creato_il' => '2026-09-16 10:00:00', 'punteggio' => 50 ) );
$docChi   = $dbChi->insert(
	'documento',
	array(
		'audit_id' => $auditChi, 'wp_id' => '77', 'tipo' => 'post', 'stato' => 'publish',
		'titolo' => $articoloProva['titolo'], 'slug' => $articoloProva['slug'],
		'percorso' => $articoloProva['percorso'], 'url' => $articoloProva['url'],
		'parole' => 300, 'testo' => 'Testo di partenza.', 'focus_keyword' => $articoloProva['focus'],
	)
);

$rilChi = $dbChi->insert( 'rilievo', array( 'audit_id' => $auditChi, 'regola' => 'CNT-02', 'titolo' => 'Contenuto sotto la soglia competitiva', 'gravita' => 'alto', 'area' => 'content', 'occorrenze' => 1 ) );
$dbChi->insert( 'occorrenza', array( 'rilievo_id' => $rilChi, 'riferimento' => $articoloProva['percorso'], 'dettaglio' => '300 parole' ) );
$dbChi->insert( 'triage', array( 'audit_id' => $auditChi, 'documento_id' => $docChi, 'categoria' => 'mantenere', 'intento' => 'informativo', 'qualita' => 70 ) );

// Quattrocento parole: chiude «meno di 300» ma non «meno di 600».
$corpoCorto = '<p>' . str_repeat( 'Una frase breve di prova. ', 80 ) . '</p>';
$corpoLungo = '<h2>Referenze</h2><p>' . str_repeat( 'Chiedi i lavori gia fatti prima di firmare. ', 120 )
	. '</p><ul><li>Referenze</li><li>Tempi</li></ul><table><tr><td>Voce</td><td>Tempo</td></tr></table>';
$sintesi    = 'In breve: guarda referenze, tempi e assistenza prima del prezzo, e chiedi due preventivi confrontabili sullo stesso perimetro di lavoro prima di firmare.';
$domande    = array( array( 'domanda' => 'Quanto tempo serve?', 'risposta' => 'Dipende dal perimetro.' ) );

$geminiTestardo = new class( array( 'chiave' => 'prova' ) ) extends \SeoGeo\Ai\Gemini {
	/** @var string[] Le richieste ricevute, per guardare che cosa e stato detto. */
	public $richieste = array();

	/** @var string[] Le risposte da dare, in ordine. */
	public $risposte = array();

	public function generaJson( $istruzioni, $richiesta, array $opzioni = array() ) {
		$this->richieste[] = (string) $richiesta;

		return array_shift( $this->risposte ) ?: array( 'corpo_html' => '<p>vuoto</p>' );
	}
};

$geminiTestardo->risposte = array(
	array( 'titolo' => 'Come scegliere un fornitore', 'corpo_html' => $corpoCorto, 'in_breve' => '', 'faq' => array() ),
	array( 'titolo' => 'Come scegliere un fornitore', 'corpo_html' => $corpoLungo, 'in_breve' => $sintesi, 'faq' => $domande ),
);

$cfgChi = require __DIR__ . '/../config.php';

$esitoChi = \SeoGeo\Ai\Rewriter::esegui(
	$dbChi,
	$geminiTestardo,
	$auditChi,
	$cfgChi,
	array( 'migliora' => true, 'solo_documento' => $docChi, 'cartella' => sys_get_temp_dir() . '/bozze-chi-' . getmypid() )
);

$bozzaChi = $dbChi->one( "SELECT * FROM bozza WHERE audit_id = ? AND stato = 'ok'", array( $auditChi ) );

verifica( 'la bozza viene generata', 1 === (int) $esitoChi['generate'], json_encode( $esitoChi['errori'] ) );
verifica( 'il primo tentativo corto non viene salvato', $bozzaChi && false !== strpos( (string) $bozzaChi['corpo_html'], 'prima di firmare' ), substr( (string) ( $bozzaChi['corpo_html'] ?? '' ), 0, 80 ) );
verifica( 'sono serviti due tentativi', 2 === (int) ( $bozzaChi['tentativi'] ?? 0 ), (string) ( $bozzaChi['tentativi'] ?? '' ) );
verifica( 'e non resta niente di aperto', '' === trim( (string) ( $bozzaChi['rimaste'] ?? '' ) ), (string) ( $bozzaChi['rimaste'] ?? '' ) );
verifica( 'al secondo giro il modello ha ricevuto la misura che gli mancava', 2 === count( $geminiTestardo->richieste ) && false !== strpos( $geminiTestardo->richieste[1], 'NON HA CHIUSO' ), (string) count( $geminiTestardo->richieste ) );

// Se nemmeno il secondo tentativo basta, la bozza si tiene - qualcosa di
// meglio e meglio di niente - ma si scrive che cosa non ha chiuso, invece di
// lasciarlo scoprire dalla rilettura di domani.
$dbChi->run( 'DELETE FROM bozza WHERE audit_id = ?', array( $auditChi ) );
$geminiTestardo->richieste = array();
$geminiTestardo->risposte  = array(
	array( 'titolo' => 'x', 'corpo_html' => $corpoCorto, 'in_breve' => '', 'faq' => array() ),
	array( 'titolo' => 'x', 'corpo_html' => $corpoCorto, 'in_breve' => '', 'faq' => array() ),
);

// Una riscrittura che scendesse sotto le 300 parole chiuderebbe «meno di 600»
// e aprirebbe un problema critico al suo posto: e cosi che il totale saliva
// mentre si correggeva. Il controllo guarda il testo prodotto per intero, non
// i soli rilievi da cui si era partiti.
verifica(
	'una riscrittura non puo chiudere un problema aprendone uno piu grave',
	in_array( 'CNT-01', array_column( Chiusura::controlla( array( 'corpo_html' => '<p>' . str_repeat( 'Poche parole. ', 30 ) . '</p>' ), $articoloProva, Chiusura::VERIFICABILI ), 'regola' ), true )
);

\SeoGeo\Ai\Rewriter::esegui(
	$dbChi,
	$geminiTestardo,
	$auditChi,
	$cfgChi,
	array( 'migliora' => true, 'solo_documento' => $docChi, 'cartella' => sys_get_temp_dir() . '/bozze-chi-' . getmypid() )
);

$bozzaTestarda = $dbChi->one( "SELECT * FROM bozza WHERE audit_id = ? AND stato = 'ok'", array( $auditChi ) );

verifica( 'quando non ce la fa la bozza si tiene lo stesso', ! empty( $bozzaTestarda ) );
verifica( 'ma resta scritto che cosa non ha chiuso', false !== strpos( (string) ( $bozzaTestarda['rimaste'] ?? '' ), 'CNT-02' ), (string) ( $bozzaTestarda['rimaste'] ?? '' ) );
verifica( 'e non si ritenta una terza volta a spese di chi paga i token', 2 === count( $geminiTestardo->richieste ), (string) count( $geminiTestardo->richieste ) );

@unlink( $fileChi );

// I controlli che una riscrittura sa fare non devono restare segnati «a mano»:
// insieme facevano 201 segnalazioni che il pulsante non toccava mai.
$mappaRim = \SeoGeo\Rimedi::mappa( 1 );

foreach ( array( 'CNT-06', 'CNT-08', 'ONP-09', 'ONP-11' ) as $regolaRim ) {
	verifica(
		"$regolaRim si corregge da un pulsante, non a mano",
		'azione' === ( $mappaRim[ $regolaRim ]['come'] ?? 'manuale' )
	);
}

verifica(
	'e il rel di sicurezza lo mette il plugin, non una persona',
	'plugin' === ( $mappaRim['LNK-05']['come'] ?? '' )
);

// Il pulsante «correggi tutto» deve dire quante segnalazioni chiude davvero:
// prometterle tutte e chiuderne un terzo e come non averle chiuse.
$rilieviFinti = array(
	array( 'regola' => 'CNT-02', 'titolo' => 'Corti', 'occorrenze' => 84 ),
	array( 'regola' => 'GEO-05', 'titolo' => 'Senza dati citabili', 'occorrenze' => 283 ),
	array( 'regola' => 'SCH-01', 'titolo' => 'Schema', 'occorrenze' => 10 ),
);

$conteggioRim = \SeoGeo\Rimedi::occorrenze( $rilieviFinti, 1 );

verifica( 'si contano le segnalazioni, non i controlli', 377 === $conteggioRim['totale'], json_encode( $conteggioRim ) );
verifica( 'quelle che chiude il pulsante', 84 === $conteggioRim['azione'], json_encode( $conteggioRim ) );
verifica( 'quelle che chiude il plugin', 10 === $conteggioRim['plugin'], json_encode( $conteggioRim ) );
verifica( 'e quelle che restano a una persona', 283 === $conteggioRim['manuale'], json_encode( $conteggioRim ) );
verifica(
	'elencate dalla piu pesante',
	'GEO-05' === ( \SeoGeo\Rimedi::aMano( $rilieviFinti, 1 )[0]['regola'] ?? '' )
);

verifica(
	'e la pagina dell audit lo dice prima di premere',
	false !== strpos( file_get_contents( __DIR__ . '/../views/audit.php' ), 'Le chiude questo pulsante' )
);

// ---------------------------------------------------------------------------
// La spinta: i link interni costruiti sui dati di Google
//
// Su «max digital innovation» Google alternava trentatre pagine dello stesso
// sito: la home era in posizione 1,2 con 928 impression e 51 clic, cioe un
// decimo di quello che una prima posizione di marca dovrebbe rendere.
// Trentatre pagine sulla stessa ricerca non fanno trentatre volte la forza.

echo "\nLa spinta con i link interni\n";

use SeoGeo\Search\Spinta;

$righeGsc = array(
	// Una ricerca contesa: la home vince, le altre due le passano forza.
	array( 'query' => 'max digital innovation', 'url' => 'https://esempio.it/', 'clic' => 51, 'impression' => 928, 'posizione' => 1.2 ),
	array( 'query' => 'max digital innovation', 'url' => 'https://esempio.it/chi-siamo/', 'clic' => 0, 'impression' => 88, 'posizione' => 8.4 ),
	array( 'query' => 'max digital innovation', 'url' => 'https://esempio.it/servizi/', 'clic' => 0, 'impression' => 60, 'posizione' => 11.0 ),
	// Una sola pagina, ma a un passo dalla prima pagina: si puo spingere.
	array( 'query' => 'agenzia comunicazione strategica', 'url' => 'https://esempio.it/agenzia/', 'clic' => 0, 'impression' => 28, 'posizione' => 10.1 ),
	// Una sola pagina gia prima: non c e niente da guadagnare.
	array( 'query' => 'nome proprio esatto srl', 'url' => 'https://esempio.it/', 'clic' => 20, 'impression' => 40, 'posizione' => 1.1 ),
	// Troppo poche impression per dire qualcosa.
	array( 'query' => 'una ricerca rarissima', 'url' => 'https://esempio.it/x/', 'clic' => 0, 'impression' => 2, 'posizione' => 14.0 ),
	// Una parola sola: metterla come testo di un link la sparge ovunque.
	array( 'query' => 'marketing', 'url' => 'https://esempio.it/y/', 'clic' => 0, 'impression' => 500, 'posizione' => 12.0 ),
);

$pianoSpinta = Spinta::calcola( $righeGsc );
$ricerche    = array_column( $pianoSpinta['gruppi'], 'query' );

verifica( 'la ricerca contesa entra nel piano', in_array( 'max digital innovation', $ricerche, true ), json_encode( $ricerche ) );
verifica( 'vince la pagina con piu clic', 'https://esempio.it/' === ( $pianoSpinta['mappa']['max digital innovation'] ?? '' ), json_encode( $pianoSpinta['mappa'] ) );
verifica( 'e le altre due risultano quelle che cedono', 2 === $pianoSpinta['conteggi']['pagine_che_cedono'], json_encode( $pianoSpinta['conteggi'] ) );
verifica( 'anche una pagina sola a un passo dalla prima pagina si spinge', in_array( 'agenzia comunicazione strategica', $ricerche, true ), json_encode( $ricerche ) );
verifica( 'chi e gia primo e non ha concorrenti si lascia stare', ! in_array( 'nome proprio esatto srl', $ricerche, true ), json_encode( $ricerche ) );
verifica( 'le ricerche con due impression non dicono niente', ! in_array( 'una ricerca rarissima', $ricerche, true ), json_encode( $ricerche ) );
verifica( 'e una parola sola non diventa il testo di un link', ! in_array( 'marketing', $ricerche, true ), json_encode( $ricerche ) );

verifica( 'una ricerca di una parola sola non e un ancora valida', ! Spinta::ancoraValida( 'marketing' ) );
verifica( 'nemmeno una troppo corta', ! Spinta::ancoraValida( 'seo srl' ) );
verifica( 'nemmeno un indirizzo', ! Spinta::ancoraValida( 'https://esempio.it/pagina' ) );
verifica( 'mentre una frase vera si', Spinta::ancoraValida( 'agenzia di comunicazione a palermo' ) );

// Prima le contese: se si deve tagliare, si taglia da quelle che contano meno.
verifica( 'in cima ci sono le ricerche contese', ! empty( $pianoSpinta['gruppi'][0]['contesa'] ), json_encode( $pianoSpinta['gruppi'][0] ?? array() ) );

$strettoSpinta = Spinta::calcola( $righeGsc, array( 'max_ancore' => 1 ) );
verifica( 'e il tetto sulle ancore si rispetta', 1 === count( $strettoSpinta['mappa'] ), json_encode( $strettoSpinta['mappa'] ) );

// Mandare la spinta al sito non deve cancellare le impostazioni: salva()
// riscrive il file intero, e li dentro c e anche la chiave di Gemini.
verifica(
	'attivando la spinta le impostazioni non si perdono',
	false !== strpos( file_get_contents( __DIR__ . '/../public/index.php' ), '$salvate = Impostazioni::salvate();' ),
	'salva() riscrive tutto il file: senza rileggerlo si perde la chiave API'
);

verifica(
	'e il piano arriva al sito prima del numero che lo accende',
	strpos( file_get_contents( __DIR__ . '/../public/index.php' ), "inviaDati( 'internal-links'" )
		< strpos( file_get_contents( __DIR__ . '/../public/index.php' ), 'inviaConfigurazione( $conSpinta )' ),
	'per qualche secondo il sito avrebbe i link accesi sulla mappa vecchia'
);

// Dopo aver premuto, la scheda deve dire che cosa e successo: lasciarla
// identica lascia chi guarda a chiedersi se il pulsante abbia fatto qualcosa.
$vistaPrestazioni = file_get_contents( __DIR__ . '/../views/prestazioni.php' );

verifica( 'a spinta attiva la scheda lo dice', false !== strpos( $vistaPrestazioni, 'La spinta è attiva sul sito' ) );
verifica( 'e il pulsante cambia parole', false !== strpos( $vistaPrestazioni, 'Aggiorna la spinta sul sito' ) );
verifica(
	'ma lo stato lo chiede al sito, non se lo tiene per conto suo',
	false !== strpos( file_get_contents( __DIR__ . '/../public/index.php' ), "\$ponte_spinta->stato()['spinta']" ),
	'un segno tenuto a parte dice «attiva» anche dopo che qualcuno ha spento da WordPress'
);
verifica(
	'e se il sito non risponde non si inventa ne acceso ne spento',
	false !== strpos( file_get_contents( __DIR__ . '/../public/index.php' ), '$spinta_sul_sito = array();' )
);

echo "\n" . ( $errori ? "✖ $errori verifiche fallite\n\n" : "✔ tutte le verifiche superate\n\n" );

exit( $errori ? 1 : 0 );
