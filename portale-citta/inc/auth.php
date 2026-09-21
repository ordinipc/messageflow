<?php
/** Accesso all'area di amministrazione. */

defined( 'PC_AVVIO' ) || exit;

function avvia_sessione() {
	if ( PHP_SESSION_ACTIVE !== session_status() ) {
		session_set_cookie_params( array(
			'lifetime' => 0,
			'path'     => '/',
			'httponly' => true,
			'samesite' => 'Lax',
			'secure'   => ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ),
		) );
		session_name( 'portalecitta' );
		session_start();
	}
}

function connesso() {
	avvia_sessione();
	return ! empty( $_SESSION['pc_connesso'] );
}

function accedi( $password ) {
	$hash = impostazione( 'password_hash', '' );
	if ( '' === $hash ) {
		return false;
	}
	if ( ! password_verify( (string) $password, $hash ) ) {
		return false;
	}
	avvia_sessione();
	session_regenerate_id( true );
	$_SESSION['pc_connesso'] = true;
	$_SESSION['pc_accesso']  = time();
	return true;
}

function esci() {
	avvia_sessione();
	$_SESSION = array();
	session_destroy();
}

function richiedi_accesso() {
	if ( ! connesso() ) {
		vai_a( 'admin.php?p=login' );
	}
}

/** Token anti-CSRF della sessione. */
function token() {
	avvia_sessione();
	if ( empty( $_SESSION['pc_token'] ) ) {
		$_SESSION['pc_token'] = bin2hex( random_bytes( 16 ) );
	}
	return $_SESSION['pc_token'];
}

function campo_token() {
	return '<input type="hidden" name="token" value="' . e( token() ) . '">';
}

/** Blocca la richiesta se il token non combacia. */
function verifica_token() {
	$inviato = isset( $_POST['token'] ) ? (string) $_POST['token'] : ( isset( $_GET['token'] ) ? (string) $_GET['token'] : '' );
	if ( ! hash_equals( token(), $inviato ) ) {
		http_response_code( 403 );
		exit( 'Sessione scaduta. Torna indietro e riprova.' );
	}
}
