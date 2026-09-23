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

$compito = (string) ( $_POST['compito'] ?? '' );

// L'elenco dei modelli non ha bisogno né di città né di pagina.
if ( 'modelli' === $compito ) {
	$esito = ai_modelli();
	ai_risposta( array(
		'ok'      => (bool) $esito['ok'],
		'modelli' => $esito['modelli'],
		'errore'  => $esito['errore'],
	) );
}

if ( ! ai_attiva() ) {
	ai_risposta( array( 'ok' => false, 'errore' => 'Chiave Gemini non impostata.' ) );
}

// La home del portale e il piè di pagina non appartengono a nessuna città:
// si rispondono prima di andare a cercarla.
$senza_citta = array(
	'home_titolo'     => 'ai_home_titolo',
	'home_intro'      => 'ai_home_intro',
	'home_testo'      => 'ai_home_testo',
	'home_sotto'      => 'ai_home_sotto',
	'home_seo_titolo' => 'ai_home_seo_titolo',
	'home_seo_desc'   => 'ai_home_seo_desc',
	'piede_testo'     => 'ai_piede_testo',
);

if ( isset( $senza_citta[ $compito ] ) ) {
	$esito = call_user_func( $senza_citta[ $compito ] );
	ai_risposta( array(
		'ok'     => (bool) $esito['ok'],
		'testo'  => isset( $esito['testo'] ) ? $esito['testo'] : '',
		'errore' => $esito['errore'],
	) );
}

if ( 'home_immagine' === $compito ) {
	$esito = ai_immagine_portale( (string) ( $_POST['richiesta'] ?? '' ) );
	ai_risposta( array(
		'ok'     => (bool) $esito['ok'],
		'file'   => $esito['file'],
		'alt'    => $esito['alt'],
		'url'    => '' === $esito['file'] ? '' : url_media( $esito['file'] ),
		'errore' => $esito['errore'],
	) );
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
foreach ( array( 'titolo', 'intro', 'corpo', 'inclusi', 'prezzo_da', 'prezzo_a', 'h1', 'tag' ) as $campo ) {
	if ( isset( $modulo[ $campo ] ) && '' !== trim( (string) $modulo[ $campo ] ) ) {
		$pagina[ $campo ] = trim( (string) $modulo[ $campo ] );
	}
}

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
	case 'tag':
		$esito = ai_tag( $citta, $pagina );
		break;
	case 'titolo':
		$esito = ai_titolo( $citta, $pagina );
		break;
	case 'immagine':
		$esito = ai_immagine( $citta, $pagina, (string) ( $_POST['richiesta'] ?? '' ) );
		ai_risposta( array(
			'ok'     => (bool) $esito['ok'],
			'file'   => $esito['file'],
			'alt'    => $esito['alt'],
			'url'    => '' === $esito['file'] ? '' : url_media( $esito['file'] ),
			'errore' => $esito['errore'],
		) );
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
