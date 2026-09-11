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

echo "\n" . ( $errori ? "✖ $errori verifiche fallite\n\n" : "✔ tutte le verifiche superate\n\n" );

exit( $errori ? 1 : 0 );
