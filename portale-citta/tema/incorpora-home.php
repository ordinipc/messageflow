<?php
/**
 * Incorporamento della home del portale: l'elenco delle città.
 *
 * Risponde su /?incorpora=1, con &sezione=citta per prendere solo la
 * griglia delle città senza i testi intorno.
 */

defined( 'PC_AVVIO' ) || exit;
require_once __DIR__ . '/funzioni-tema.php';
require_once __DIR__ . '/sezioni.php';

header( 'Content-Type: application/json; charset=UTF-8' );
header( 'Access-Control-Allow-Origin: *' );
header( 'Cache-Control: public, max-age=300' );

$imp     = impostazioni();
$elenco  = array_keys( sezioni_home() );
$chiesta = trim( (string) ( $_GET['sezione'] ?? '' ) );

if ( '' !== $chiesta && ! in_array( $chiesta, $elenco, true ) ) {
	http_response_code( 404 );
	echo json_encode( array(
		'ok'      => false,
		'errore'  => 'La home del portale non ha la sezione «' . $chiesta . '».',
		'sezioni' => $elenco,
	), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	exit;
}

$html = '';
foreach ( '' === $chiesta ? $elenco : array( $chiesta ) as $chiave ) {
	$html .= rendi_sezione_home( $chiave );
}

echo json_encode( array(
	'ok'       => true,
	'titolo'   => $imp['home_elenco_titolo'],
	'h1'       => trim( str_replace( '*', '', (string) $imp['home_titolo'] ) ),
	'intro'    => $imp['home_intro'],
	'url'      => base_url() . '/',
	'citta'    => '',
	'sezione'  => $chiesta,
	'sezioni'  => $elenco,
	'css'      => base_url() . '/tema/style.css?v=' . versione_asset( 'style.css' ),
	'js'       => base_url() . '/tema/script.js?v=' . versione_asset( 'script.js' ),
	'variabili'=> css_variabili(),
	'classi'   => classi_corpo(),
	'html'     => '<div class="glp-sections">' . $html . '</div>',
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
