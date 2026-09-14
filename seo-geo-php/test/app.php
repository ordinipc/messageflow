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

verifica(
	'i tre lotti usano lo stesso ciclo',
	3 === substr_count( $sorgenteBozze, 'class="scheda a-lotti"' )
);

verifica(
	'ognuno dichiara che cosa lavora e quanti ne restano',
	3 === substr_count( $sorgenteBozze, 'data-tipo=' ) && 3 === substr_count( $sorgenteBozze, 'data-restanti=' )
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

echo "\n" . ( $errori ? "✖ $errori verifiche fallite\n\n" : "✔ tutte le verifiche superate\n\n" );

exit( $errori ? 1 : 0 );
