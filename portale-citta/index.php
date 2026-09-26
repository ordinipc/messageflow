<?php
/**
 * Portale Città — front controller pubblico.
 * Tutte le richieste passano da qui tramite .htaccess.
 */

define( 'PC_AVVIO', true );
require_once __DIR__ . '/inc/core.php';

if ( ! db_configurato() ) {
	header( 'Content-Type: text/html; charset=UTF-8' );
	exit( '<h1>Portale non ancora installato</h1><p>Apri <a href="install.php">install.php</a> per completare l\'installazione.</p>' );
}

try {
	db();
} catch ( PDOException $ex ) {
	http_response_code( 500 );
	exit( '<h1>Database non raggiungibile</h1><p>Controlla i dati in config.php.</p>' );
}

if ( ! db_installato() ) {
	exit( '<h1>Tabelle mancanti</h1><p>Apri <a href="install.php">install.php</a> per crearle.</p>' );
}

/** Percorso richiesto, senza la cartella di installazione. */
function percorso_richiesto() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
	$uri = parse_url( $uri, PHP_URL_PATH );
	$uri = null === $uri ? '/' : $uri;
	$base = rtrim( dirname( (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) ), '/\\' );
	if ( '' !== $base && 0 === strpos( $uri, $base ) ) {
		$uri = substr( $uri, strlen( $base ) );
	}
	$uri = trim( rawurldecode( $uri ), '/' );
	// Ripiego senza riscrittura: index.php?percorso=trapani/apertura-porte
	if ( '' === $uri && isset( $_GET['percorso'] ) ) {
		$uri = trim( (string) $_GET['percorso'], '/' );
	}
	return $uri;
}

$percorso = percorso_richiesto();

/* --- robots.txt ---------------------------------------------------------- */
if ( 'robots.txt' === $percorso ) {
	header( 'Content-Type: text/plain; charset=UTF-8' );
	echo robots_txt();
	exit;
}

/* --- llms.txt: l'indice per gli assistenti IA ---------------------------- */
if ( 'llms.txt' === $percorso ) {
	header( 'Content-Type: text/plain; charset=UTF-8' );
	if ( '0' === (string) impostazione( 'ia_consenti', '1' ) ) {
		http_response_code( 404 );
		exit;
	}
	echo llms_txt();
	exit;
}

/* --- sitemap ------------------------------------------------------------- */
if ( 'sitemap.xml' === $percorso ) {
	header( 'Content-Type: application/xml; charset=UTF-8' );
	echo sitemap_indice();
	exit;
}
if ( preg_match( '#^sitemap-([a-z0-9-]+)\.xml$#', $percorso, $m ) ) {
	$citta = citta_per_slug( $m[1] );
	// Una città in bozza, o senza nemmeno una pagina pubblicata, non ha
	// una sitemap: qui c'è 404, non un elenco vuoto. Un elenco vuoto
	// risponde 200, e Google se lo segna come sitemap buona che non
	// porta niente — resta lì per sempre a dire che qualcosa non va.
	if ( ! $citta || 'pubblicata' !== $citta['stato'] || empty( sitemap_voci( $citta['id'] ) ) ) {
		http_response_code( 404 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		exit( "Questa città non ha una sitemap: o è in bozza, o non ha pagine pubblicate.\n" );
	}
	header( 'Content-Type: application/xml; charset=UTF-8' );
	echo sitemap_xml( $citta['id'] );
	exit;
}

/* --- home del portale: elenco delle città -------------------------------- */
if ( '' === $percorso ) {
	// Anche la home si può incorporare: è l'elenco delle città.
	include __DIR__ . ( isset( $_GET['incorpora'] ) ? '/tema/incorpora-home.php' : '/tema/elenco-citta.php' );
	exit;
}

/* --- /{citta}/ e /{citta}/{pagina}/ -------------------------------------- */
$parti = explode( '/', $percorso );
$citta = citta_per_slug( $parti[0] );

if ( ! $citta ) {
	http_response_code( 404 );
	include __DIR__ . '/tema/404.php';
	exit;
}

// Chi è collegato all'amministrazione può vedere le bozze in anteprima.
// La sessione si avvia solo se il cookie esiste già: le pagine pubbliche restano cacheabili.
$anteprima = isset( $_COOKIE['portalecitta'] ) ? connesso() : false;

if ( 'pubblicata' !== $citta['stato'] && ! $anteprima ) {
	http_response_code( 404 );
	include __DIR__ . '/tema/404.php';
	exit;
}

if ( count( $parti ) > 1 && '' !== $parti[1] ) {
	$pagina = pagina_per_slug( $citta['id'], $parti[1] );
} else {
	$pagina = pagina_home( $citta['id'] );
}

// Terzo livello: /{citta}/{blog}/{articolo}/
// e quarto: /{citta}/{blog}/categoria/{categoria}/
$articolo  = null;
$categoria = null;
if ( $pagina && 'blog' === $pagina['tipo'] && count( $parti ) > 2 && '' !== $parti[2] ) {
	if ( 'categoria' === $parti[2] ) {
		// Il pezzo fisso "categoria" tiene separati i due spazi di nomi: una
		// categoria e un articolo possono chiamarsi allo stesso modo.
		$categoria = ( count( $parti ) > 3 && '' !== $parti[3] ) ? categoria_per_slug( $parti[3] ) : null;
		// Una categoria che in questa città non ha nemmeno un articolo
		// pubblicato non è una pagina: sarebbe un indirizzo vuoto in indice.
		$vuota = $categoria && 0 === articoli_conta_categoria( $citta['id'], $categoria['id'], true );
		if ( ! $categoria || ( $vuota && ! $anteprima ) ) {
			http_response_code( 404 );
			include __DIR__ . '/tema/404.php';
			exit;
		}
	} else {
		$articolo = articolo_per_slug( $citta['id'], $parti[2] );
		if ( ! $articolo ) {
			http_response_code( 404 );
			include __DIR__ . '/tema/404.php';
			exit;
		}
		if ( 'pubblicato' !== $articolo['stato'] && ! $anteprima ) {
			http_response_code( 404 );
			include __DIR__ . '/tema/404.php';
			exit;
		}
	}
}

if ( ! $pagina ) {
	http_response_code( 404 );
	include __DIR__ . '/tema/404.php';
	exit;
}

if ( 'pubblicata' !== $pagina['stato'] && ! $anteprima ) {
	http_response_code( 404 );
	include __DIR__ . '/tema/404.php';
	exit;
}

if ( $categoria ) {
	include __DIR__ . '/tema/categoria.php';
	exit;
}

if ( $articolo ) {
	include __DIR__ . '/tema/articolo.php';
	exit;
}

// Richiesta di incorporamento: risponde in JSON, senza intestazione né piè
// di pagina. La usa lo shortcode di WordPress.
if ( isset( $_GET['incorpora'] ) ) {
	include __DIR__ . '/tema/incorpora.php';
	exit;
}

include __DIR__ . '/tema/pagina.php';
