<?php
/** Amministrazione — front controller. */

define( 'PC_AVVIO', true );
// La versione che questo file si aspetta di trovare dentro inc/. Sta qui
// e non lì apposta: serve proprio a scoprire quando i due non combaciano.
define( 'PC_VERSIONE_ATTESA', '1.2.0' );
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
	// Le migrazioni: la vecchia password unica che diventa il primo
	// utente, le FAQ che arrivano sulle pagine principali già create.
	//
	// Si chiamano solo se ci sono davvero. Un caricamento a metà —
	// admin.php nuovo e la cartella inc/ ancora vecchia — chiudeva fuori
	// dal pannello con un errore fatale, e il pannello è proprio il
	// posto da cui si rimedia. Meglio entrare con un avviso.
	$pc_da_ricaricare = array();
	// La versione sta in inc/core.php: se è indietro, la cartella inc/ è
	// vecchia anche quando le funzioni qui sotto ci sono tutte.
	if ( version_compare( PC_VERSIONE, PC_VERSIONE_ATTESA, '<' ) ) {
		$pc_da_ricaricare[] = 'inc/core.php';
	}
	foreach ( array( 'utenti_migra', 'sezioni_migra_faq' ) as $pc_passo ) {
		if ( function_exists( $pc_passo ) ) {
			$pc_passo();
		} else {
			$pc_da_ricaricare[] = $pc_passo;
		}
	}
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
	'categorie'        => 'Categorie',
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
