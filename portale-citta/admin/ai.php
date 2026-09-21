<?php
/** Endpoint dell'assistente: risponde in JSON alle richieste dell'editor. */
defined( 'PC_AVVIO' ) || exit;

header( 'Content-Type: application/json; charset=UTF-8' );

function ai_risposta( $dati ) {
	echo json_encode( $dati, JSON_UNESCAPED_UNICODE );
	exit;
}

if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
	ai_risposta( array( 'ok' => false, 'errore' => 'Richiesta non valida.' ) );
}

$inviato = (string) ( $_POST['token'] ?? '' );
if ( ! hash_equals( token(), $inviato ) ) {
	ai_risposta( array( 'ok' => false, 'errore' => 'Sessione scaduta: ricarica la pagina.' ) );
}

if ( ! ai_attiva() ) {
	ai_risposta( array( 'ok' => false, 'errore' => 'Chiave Gemini non impostata.' ) );
}

$citta = citta_per_id( (string) ( $_POST['citta'] ?? '' ) );
if ( ! $citta ) {
	ai_risposta( array( 'ok' => false, 'errore' => 'Città non trovata.' ) );
}

$pagina = pagina_per_id( (string) ( $_POST['pagina'] ?? '' ) );
if ( ! $pagina ) {
	$pagina             = pagina_predefinita();
	$pagina['citta_id'] = $citta['id'];
}

// Il modulo non ancora salvato ha la precedenza: l'assistente vede ciò che l'utente sta scrivendo.
$modulo = (array) ( $_POST['modulo'] ?? array() );
foreach ( array( 'titolo', 'intro', 'corpo', 'inclusi', 'prezzo_da', 'prezzo_a', 'h1' ) as $campo ) {
	if ( isset( $modulo[ $campo ] ) && '' !== trim( (string) $modulo[ $campo ] ) ) {
		$pagina[ $campo ] = trim( (string) $modulo[ $campo ] );
	}
}

$compito = (string) ( $_POST['compito'] ?? '' );

switch ( $compito ) {
	case 'intro':
		$esito = ai_intro( $citta, $pagina );
		break;
	case 'corpo':
		$esito = ai_corpo( $citta, $pagina );
		break;
	case 'descrizione':
		$esito = ai_descrizione( $citta, $pagina );
		break;
	case 'faq':
		$esito = ai_faq( $citta, $pagina );
		ai_risposta( array(
			'ok'     => (bool) $esito['ok'],
			'voci'   => isset( $esito['voci'] ) ? $esito['voci'] : array(),
			'errore' => $esito['errore'],
		) );
		break;
	default:
		ai_risposta( array( 'ok' => false, 'errore' => 'Richiesta sconosciuta.' ) );
}

ai_risposta( array(
	'ok'     => (bool) $esito['ok'],
	'testo'  => isset( $esito['testo'] ) ? $esito['testo'] : '',
	'errore' => $esito['errore'],
) );
