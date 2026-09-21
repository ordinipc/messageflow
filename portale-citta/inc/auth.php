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

/**
 * L'utente della sessione, riletto dal database a ogni richiesta.
 *
 * Rileggerlo costa una query e serve: se nel frattempo l'account è stato
 * sospeso o cancellato, la sessione aperta deve cadere subito.
 */
function utente_corrente() {
	if ( array_key_exists( 'pc_utente_letto', $GLOBALS ) ) {
		return $GLOBALS['pc_utente_letto'];
	}
	avvia_sessione();
	$u = empty( $_SESSION['pc_utente'] ) ? null : utente( (string) $_SESSION['pc_utente'] );
	if ( $u && 'attivo' !== $u['stato'] ) {
		$u = null;
	}
	$GLOBALS['pc_utente_letto'] = $u;
	return $u;
}

/** Dimentica l'utente in cache: da chiamare quando la sessione cambia. */
function scorda_utente() {
	unset( $GLOBALS['pc_utente_letto'] );
}

function connesso() {
	return null !== utente_corrente();
}

function ruolo_corrente() {
	$u = utente_corrente();
	return $u ? (string) $u['ruolo'] : '';
}

/** True se chi è collegato può toccare impostazioni e utenti. */
function puo_amministrare() {
	return 'amministratore' === ruolo_corrente();
}

/**
 * Entra nel pannello.
 *
 * Il nome si può lasciare vuoto quando esiste un solo utente attivo:
 * finché il portale ha un padrone solo, chiedergli anche il nome è
 * un ostacolo senza guadagno.
 */
function accedi( $password, $nome = '' ) {
	$nome = trim( (string) $nome );
	if ( '' === $nome ) {
		$attivi = db_righe( 'SELECT * FROM ' . db_tab( 'utenti' ) . " WHERE stato = 'attivo'" );
		$u      = ( 1 === count( $attivi ) ) ? $attivi[0] : null;
	} else {
		$u = utente_per_accesso( $nome );
	}
	if ( ! $u || 'attivo' !== $u['stato'] || vuoto( $u['password_hash'] ) ) {
		// Si verifica comunque un hash finto: senza, il tempo di risposta
		// direbbe da solo quali nomi esistono.
		password_verify( (string) $password, '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv' );
		return false;
	}
	if ( ! password_verify( (string) $password, (string) $u['password_hash'] ) ) {
		return false;
	}
	if ( password_needs_rehash( (string) $u['password_hash'], PASSWORD_DEFAULT ) ) {
		db_esegui(
			'UPDATE ' . db_tab( 'utenti' ) . ' SET password_hash = ? WHERE id = ?',
			array( password_hash( (string) $password, PASSWORD_DEFAULT ), $u['id'] )
		);
	}
	db_esegui(
		'UPDATE ' . db_tab( 'utenti' ) . ' SET ultimo_accesso = ? WHERE id = ?',
		array( date( 'Y-m-d H:i' ), $u['id'] )
	);

	avvia_sessione();
	session_regenerate_id( true );
	scorda_utente();
	$_SESSION['pc_connesso'] = true;
	$_SESSION['pc_utente']   = $u['id'];
	$_SESSION['pc_accesso']  = time();
	return true;
}

function esci() {
	avvia_sessione();
	$_SESSION = array();
	scorda_utente();
	if ( PHP_SESSION_ACTIVE === session_status() ) {
		session_destroy();
	}
}

function richiedi_accesso() {
	if ( ! connesso() ) {
		vai_a( 'admin.php?p=login' );
	}
}

/** Blocca le schermate riservate a chi amministra. */
function richiedi_amministratore() {
	richiedi_accesso();
	if ( ! puo_amministrare() ) {
		http_response_code( 403 );
		exit( 'Questa sezione è riservata agli amministratori.' );
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
