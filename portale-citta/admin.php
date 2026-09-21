<?php
/** Amministrazione — front controller. */

define( 'PC_AVVIO', true );
require_once __DIR__ . '/inc/core.php';

if ( ! db_configurato() ) {
	vai_a( 'install.php' );
}
try {
	db();
	if ( ! db_installato() ) {
		vai_a( 'install.php' );
	}
} catch ( PDOException $ex ) {
	exit( '<h1>Database non raggiungibile</h1><p>' . e( $ex->getMessage() ) . '</p>' );
}

$pagine_admin = array(
	'login'           => 'Accesso',
	'esci'            => 'Esci',
	'pannello'        => 'Pannello',
	'citta'           => 'Città',
	'citta-modifica'  => 'Modifica città',
	'pagine'          => 'Pagine',
	'pagina-modifica' => 'Modifica pagina',
	'servizi'         => 'Tipi di servizio',
	'media'           => 'Immagini',
	'seo'             => 'SEO e sitemap',
	'impostazioni'    => 'Impostazioni',
	'ai'              => 'Assistente',
);

$schermata = isset( $_GET['p'] ) ? (string) $_GET['p'] : 'pannello';
if ( ! isset( $pagine_admin[ $schermata ] ) ) {
	$schermata = 'pannello';
}

if ( 'esci' === $schermata ) {
	esci();
	vai_a( 'admin.php?p=login' );
}

if ( 'login' !== $schermata ) {
	richiedi_accesso();
}

$file = __DIR__ . '/admin/' . $schermata . '.php';
if ( ! is_file( $file ) ) {
	http_response_code( 404 );
	exit( 'Schermata non trovata.' );
}

// Le schermate che rispondono in JSON gestiscono da sole l'output.
if ( 'ai' === $schermata ) {
	include $file;
	exit;
}

$titolo_schermata = $pagine_admin[ $schermata ];

if ( 'login' === $schermata ) {
	include $file;
	exit;
}

ob_start();
include $file;
$contenuto = ob_get_clean();
include __DIR__ . '/admin/layout.php';
