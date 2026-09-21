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
	// Chi aggiorna il portale trova le tabelle vecchie: qui si allineano.
	db_installa();
	db_aggiorna();
	// La vecchia password unica diventa il primo utente.
	utenti_migra();
} catch ( PDOException $ex ) {
	exit( '<h1>Database non raggiungibile</h1><p>' . e( $ex->getMessage() ) . '</p>' );
}

$pagine_admin = array(
	'login'           => 'Accesso',
	'esci'            => 'Esci',
	'pannello'        => 'Pannello',
	'home'            => 'Home del portale',
	'citta'           => 'Città',
	'citta-modifica'  => 'Modifica città',
	'pagine'          => 'Pagine',
	'menu'            => 'Menu',
	'pagina-modifica' => 'Modifica pagina',
	'servizi'         => 'Tipi di servizio',
	'articoli'         => 'Articoli',
	'articolo-modifica'=> 'Modifica articolo',
	'importa'          => 'Importa',
	'media'           => 'Immagini',
	'seo'             => 'SEO e sitemap',
	'impostazioni'    => 'Impostazioni',
	'utenti'          => 'Utenti',
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

// Impostazioni e utenti restano in mano agli amministratori.
$solo_amministratori = array( 'impostazioni', 'utenti' );

if ( 'login' !== $schermata ) {
	if ( in_array( $schermata, $solo_amministratori, true ) ) {
		richiedi_amministratore();
	} else {
		richiedi_accesso();
	}
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
