<?php
/**
 * Utenti dell'amministrazione.
 *
 * Fino alla versione precedente il portale aveva una sola password,
 * conservata fra le impostazioni. Qui diventa un elenco di persone, così
 * chi entra si riconosce dal nome e chi comanda decide fin dove può
 * arrivare ciascuno.
 */

defined( 'PC_AVVIO' ) || exit;

/** I due ruoli previsti, con la descrizione mostrata nel pannello. */
function ruoli() {
	return array(
		'amministratore' => array(
			'nome'  => 'Amministratore',
			'nota'  => 'Può fare tutto, comprese le impostazioni e la gestione degli utenti.',
		),
		'redattore'      => array(
			'nome'  => 'Redattore',
			'nota'  => 'Scrive città, pagine, articoli e immagini. Non tocca le impostazioni né gli utenti.',
		),
	);
}

function ruolo_valido( $ruolo ) {
	return isset( ruoli()[ (string) $ruolo ] ) ? (string) $ruolo : 'redattore';
}

/** Riga vuota con i valori di partenza. */
function utente_predefinito() {
	return array(
		'id'             => '',
		'nome'           => '',
		'etichetta'      => '',
		'email'          => '',
		'password_hash'  => '',
		'ruolo'          => 'redattore',
		'stato'          => 'attivo',
		'creato'         => date( 'Y-m-d H:i' ),
		'ultimo_accesso' => '',
	);
}

/**
 * Normalizza il nome di accesso: minuscolo, senza spazi né accenti.
 * Deve restare digitabile al volo da chi entra di fretta.
 */
function utente_nome_pulito( $nome ) {
	$nome = strtolower( trim( (string) $nome ) );
	$nome = strtr( $nome, array( 'à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u' ) );
	$nome = preg_replace( '/[^a-z0-9._-]+/', '', $nome );
	return (string) $nome;
}

function utenti_tutti() {
	return db_righe( 'SELECT * FROM ' . db_tab( 'utenti' ) . ' ORDER BY ruolo ASC, nome ASC' );
}

function utenti_conta( $solo_attivi = false ) {
	$sql = 'SELECT COUNT(*) FROM ' . db_tab( 'utenti' );
	if ( $solo_attivi ) {
		$sql .= " WHERE stato = 'attivo'";
	}
	return (int) db_valore( $sql, array(), 0 );
}

/** Quanti amministratori attivi restano: serve a non chiudersi fuori. */
function amministratori_attivi() {
	return (int) db_valore(
		'SELECT COUNT(*) FROM ' . db_tab( 'utenti' ) . " WHERE ruolo = 'amministratore' AND stato = 'attivo'",
		array(),
		0
	);
}

function utente( $id ) {
	if ( vuoto( $id ) ) {
		return null;
	}
	return db_riga( 'SELECT * FROM ' . db_tab( 'utenti' ) . ' WHERE id = ?', array( (string) $id ) );
}

function utente_per_nome( $nome ) {
	$nome = utente_nome_pulito( $nome );
	if ( '' === $nome ) {
		return null;
	}
	return db_riga( 'SELECT * FROM ' . db_tab( 'utenti' ) . ' WHERE nome = ?', array( $nome ) );
}

/**
 * Trova l'utente da quello che ha scritto nel campo: il nome, oppure
 * l'email. Chi entra due volte l'anno il nome se lo dimentica, l'email no.
 */
function utente_per_accesso( $scritto ) {
	$scritto = trim( (string) $scritto );
	if ( '' === $scritto ) {
		return null;
	}
	$u = utente_per_nome( $scritto );
	if ( $u ) {
		return $u;
	}
	if ( false === strpos( $scritto, '@' ) ) {
		return null;
	}
	return db_riga(
		'SELECT * FROM ' . db_tab( 'utenti' ) . ' WHERE LOWER(email) = ?',
		array( strtolower( $scritto ) )
	);
}

/** True se il nome è libero (escluso l'utente che si sta modificando). */
function utente_nome_libero( $nome, $escludi = '' ) {
	$altro = utente_per_nome( $nome );
	return ! $altro || $altro['id'] === (string) $escludi;
}

function utente_salva( $dati ) {
	$u = array_merge( utente_predefinito(), $dati );
	$u['id']    = vuoto( $u['id'] ) ? nuovo_id() : (string) $u['id'];
	$u['nome']  = utente_nome_pulito( $u['nome'] );
	$u['ruolo'] = ruolo_valido( $u['ruolo'] );
	$u['stato'] = 'sospeso' === $u['stato'] ? 'sospeso' : 'attivo';
	if ( vuoto( $u['etichetta'] ) ) {
		$u['etichetta'] = maiuscola( $u['nome'] );
	}
	db_salva( 'utenti', $u );
	return $u;
}

function utente_elimina( $id ) {
	return db_elimina( 'utenti', 'id = ?', array( (string) $id ) );
}

/** Cambia la password di un utente. Ritorna un messaggio d'errore o ''. */
function utente_password( $id, $password ) {
	$password = (string) $password;
	if ( mb_strlen( $password ) < 8 ) {
		return 'La password deve avere almeno 8 caratteri.';
	}
	$u = utente( $id );
	if ( ! $u ) {
		return 'Utente non trovato.';
	}
	db_esegui(
		'UPDATE ' . db_tab( 'utenti' ) . ' SET password_hash = ? WHERE id = ?',
		array( password_hash( $password, PASSWORD_DEFAULT ), (string) $id )
	);
	return '';
}

/**
 * Porta la vecchia password unica dentro la tabella utenti.
 *
 * Chi aggiorna il portale non deve accorgersi di nulla: la password che
 * usava continua a funzionare, con il nome «admin».
 *
 * @return string Nome dell'utente creato, '' se non c'era nulla da fare.
 */
function utenti_migra() {
	if ( utenti_conta() > 0 ) {
		return '';
	}
	$hash = (string) impostazione( 'password_hash', '' );
	if ( '' === $hash ) {
		return '';
	}
	$u = utente_salva( array(
		'nome'          => 'admin',
		'etichetta'     => 'Amministratore',
		'email'         => (string) impostazione( 'email', '' ),
		'password_hash' => $hash,
		'ruolo'         => 'amministratore',
		'stato'         => 'attivo',
	) );
	// Una sola verità: la copia fra le impostazioni non serve più e non
	// deve restare in giro a fare da chiave di scorta.
	impostazioni_salva( array( 'password_hash' => '' ) );
	return $u['nome'];
}
