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

/* --- sitemap ------------------------------------------------------------- */
if ( 'sitemap.xml' === $percorso ) {
	header( 'Content-Type: application/xml; charset=UTF-8' );
	echo sitemap_indice();
	exit;
}
if ( preg_match( '#^sitemap-([a-z0-9-]+)\.xml$#', $percorso, $m ) ) {
	$citta = citta_per_slug( $m[1] );
	if ( ! $citta ) {
		http_response_code( 404 );
		exit;
	}
	header( 'Content-Type: application/xml; charset=UTF-8' );
	echo sitemap_xml( $citta['id'] );
	exit;
}

/* --- home del portale: elenco delle città -------------------------------- */
if ( '' === $percorso ) {
	include __DIR__ . '/tema/elenco-citta.php';
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
$articolo = null;
if ( $pagina && 'blog' === $pagina['tipo'] && count( $parti ) > 2 && '' !== $parti[2] ) {
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
