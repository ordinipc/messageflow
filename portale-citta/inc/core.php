<?php
/**
 * Portale Città — nucleo.
 * Funzioni di base condivise da front-end e amministrazione.
 */

if ( ! defined( 'PC_AVVIO' ) ) {
	define( 'PC_AVVIO', true );
}

define( 'PC_VERSIONE', '1.0.0' );
define( 'PC_RADICE', dirname( __DIR__ ) );
define( 'PC_DATI', PC_RADICE . '/dati' );
define( 'PC_MEDIA', PC_RADICE . '/media' );

mb_internal_encoding( 'UTF-8' );

/**
 * Errori del database: mostra un messaggio leggibile invece di una pagina bianca.
 * In amministrazione si vede il dettaglio, sul sito pubblico no.
 */
set_exception_handler( function ( $errore ) {
	$amministrazione = false !== strpos( (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ), 'admin' );
	if ( ! headers_sent() ) {
		http_response_code( 500 );
		header( 'Content-Type: text/html; charset=UTF-8' );
	}
	echo '<div style="font:15px/1.5 system-ui,sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #f3c8c5;background:#fdeceb;border-radius:8px">';
	echo '<h1 style="font-size:18px;margin:0 0 8px">Qualcosa non ha funzionato</h1>';
	if ( $amministrazione ) {
		echo '<p style="margin:0 0 10px">' . htmlspecialchars( $errore->getMessage(), ENT_QUOTES, 'UTF-8' ) . '</p>';
		echo '<p style="margin:0;font-size:13px;color:#8c1d18">' . htmlspecialchars( basename( $errore->getFile() ) . ':' . $errore->getLine(), ENT_QUOTES, 'UTF-8' ) . '</p>';
	} else {
		echo '<p style="margin:0">La pagina non è al momento disponibile. Riprova fra poco.</p>';
	}
	echo '</div>';
	exit;
} );

require_once __DIR__ . '/archivio.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/ai.php';

/** Escape HTML. */
function e( $testo ) {
	return htmlspecialchars( (string) $testo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/** Escape per attributo href/src già validato. */
function e_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	if ( preg_match( '#^(javascript|data|vbscript):#i', $url ) ) {
		return '';
	}
	return e( $url );
}

/** Primo carattere maiuscolo rispettando UTF-8. */
function maiuscola( $testo ) {
	$testo = (string) $testo;
	if ( '' === $testo ) {
		return '';
	}
	return mb_strtoupper( mb_substr( $testo, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $testo, 1, null, 'UTF-8' );
}

/** Trasforma un testo in slug URL. */
function slugifica( $testo ) {
	$testo = mb_strtolower( (string) $testo, 'UTF-8' );
	$mappa = array(
		'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
		'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
		'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
		'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
		'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
		'ç' => 'c', 'ñ' => 'n', 'ß' => 'ss',
	);
	$testo = strtr( $testo, $mappa );
	$testo = preg_replace( '/[^a-z0-9]+/', '-', $testo );
	$testo = trim( (string) $testo, '-' );
	return $testo;
}

/** Valore da array con notazione a punti: conf( $a, 'seo.titolo', '' ). */
function pesca( $array, $percorso, $default = '' ) {
	$parti = explode( '.', (string) $percorso );
	$corrente = $array;
	foreach ( $parti as $parte ) {
		if ( is_array( $corrente ) && array_key_exists( $parte, $corrente ) ) {
			$corrente = $corrente[ $parte ];
		} else {
			return $default;
		}
	}
	if ( null === $corrente || '' === $corrente ) {
		return $default;
	}
	return $corrente;
}

/** True se il valore è vuoto (stringa vuota, array vuoto, null). */
function vuoto( $valore ) {
	if ( is_array( $valore ) ) {
		return 0 === count( array_filter( $valore, function ( $v ) { return ! vuoto( $v ); } ) );
	}
	return null === $valore || '' === trim( (string) $valore );
}

/** Numero di telefono ripulito per tel:. */
function tel( $numero ) {
	return preg_replace( '/[^0-9+]/', '', (string) $numero );
}

/** Testo libero → paragrafi HTML. */
function paragrafi( $testo ) {
	$testo = trim( (string) $testo );
	if ( '' === $testo ) {
		return '';
	}
	$blocchi = preg_split( "/\n\s*\n/", $testo );
	$html    = '';
	foreach ( $blocchi as $blocco ) {
		$blocco = trim( $blocco );
		if ( '' === $blocco ) {
			continue;
		}
		$html .= '<p>' . nl2br( e( $blocco ) ) . '</p>';
	}
	return $html;
}

/** Una riga per elemento → array. */
function righe( $testo ) {
	$testo = (string) $testo;
	$out   = array();
	foreach ( preg_split( "/\r\n|\r|\n/", $testo ) as $riga ) {
		$riga = trim( $riga );
		if ( '' !== $riga ) {
			$out[] = $riga;
		}
	}
	return $out;
}

/** URL base del sito, senza slash finale. */
function base_url() {
	$imp = impostazioni();
	$url = trim( (string) pesca( $imp, 'sito_url', '' ), '/' );
	if ( '' !== $url ) {
		return $url;
	}
	$schema = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ? 'https' : 'http';
	$host   = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
	$dir    = rtrim( dirname( (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) ), '/\\' );
	return $schema . '://' . $host . $dir;
}

/** URL pubblico di una città. */
function url_citta( $citta ) {
	return base_url() . '/' . $citta['slug'] . '/';
}

/** URL pubblico di una pagina. */
function url_pagina( $citta, $pagina ) {
	if ( 'home' === $pagina['tipo'] ) {
		return url_citta( $citta );
	}
	return base_url() . '/' . $citta['slug'] . '/' . $pagina['slug'] . '/';
}

/** URL di un file in media/. */
function url_media( $file ) {
	if ( vuoto( $file ) ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $file ) ) {
		return $file;
	}
	return base_url() . '/media/' . ltrim( $file, '/' );
}

/** Reindirizza e termina. */
function vai_a( $url ) {
	header( 'Location: ' . $url );
	exit;
}

/** Identificatore univoco breve. */
function nuovo_id() {
	return substr( bin2hex( random_bytes( 8 ) ), 0, 12 );
}

/** Data odierna ISO. */
function oggi() {
	return date( 'Y-m-d' );
}

/** Messaggio flash in sessione. */
function avviso( $testo = null, $tipo = 'ok' ) {
	avvia_sessione();
	if ( null !== $testo ) {
		$_SESSION['pc_avviso'] = array( 'testo' => $testo, 'tipo' => $tipo );
		return null;
	}
	if ( empty( $_SESSION['pc_avviso'] ) ) {
		return null;
	}
	$a = $_SESSION['pc_avviso'];
	unset( $_SESSION['pc_avviso'] );
	return $a;
}

/** Stampa checked se la condizione è vera. */
function checked_pc( $condizione ) {
	echo $condizione ? 'checked' : '';
}

/** Stampa selected se i due valori coincidono. */
function selected_pc( $a, $b ) {
	echo (string) $a === (string) $b ? 'selected' : '';
}
