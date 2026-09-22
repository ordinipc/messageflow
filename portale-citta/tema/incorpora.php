<?php
/**
 * Incorporamento: restituisce il contenuto di una pagina in JSON, così
 * WordPress (o qualunque altro sito) può stamparlo dentro una sua pagina.
 *
 * Risponde su /{citta}/{pagina}/?incorpora=1, con &sezione=chiave per
 * prendere una sola sezione invece di tutte.
 *
 * Variabili attese da index.php: $citta, $pagina.
 */

defined( 'PC_AVVIO' ) || exit;
require_once __DIR__ . '/funzioni-tema.php';
require_once __DIR__ . '/sezioni.php';

header( 'Content-Type: application/json; charset=UTF-8' );
// Il contenuto è già pubblico: chiunque può leggerlo anche da un'altra pagina.
header( 'Access-Control-Allow-Origin: *' );
header( 'Cache-Control: public, max-age=300' );

$chiesta = trim( (string) ( $_GET['sezione'] ?? '' ) );
$elenco  = pagina_sezioni( $pagina );

if ( '' !== $chiesta && ! in_array( $chiesta, $elenco, true ) ) {
	http_response_code( 404 );
	echo json_encode( array(
		'ok'      => false,
		'errore'  => 'La pagina non mostra la sezione «' . $chiesta . '».',
		'sezioni' => $elenco,
	), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	exit;
}

// Il modulo di contatto invia al portale, non a chi incorpora: l'esito si
// vede là. Qui si disegna sempre vuoto.
$contesto = contesto_pagina( $citta, $pagina, null );
$html     = '';
foreach ( '' === $chiesta ? $elenco : array( $chiesta ) as $chiave ) {
	$html .= rendi_sezione( $chiave, $contesto );
}

$imp = impostazioni();

echo json_encode( array(
	'ok'       => true,
	'titolo'   => $pagina['titolo'],
	'h1'       => seo_h1( $citta, $pagina ),
	'intro'    => vuoto( $pagina['intro'] ) ? $citta['intro'] : $pagina['intro'],
	'url'      => url_pagina( $citta, $pagina ),
	'citta'    => $citta['nome'],
	'sezione'  => $chiesta,
	'sezioni'  => $elenco,
	'css'      => base_url() . '/tema/style.css?v=' . versione_asset( 'style.css' ),
	'js'       => base_url() . '/tema/script.js?v=' . versione_asset( 'script.js' ),
	'variabili'=> css_variabili(),
	'classi'   => classi_corpo(),
	'html'     => '<div class="glp-sections">' . $html . '</div>',
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
