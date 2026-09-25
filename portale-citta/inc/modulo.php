<?php
/**
 * Modulo di contatto delle pagine pubbliche.
 *
 * Niente sessione: le pagine pubbliche devono restare cacheabili. Contro
 * lo spam bastano tre cose che non danno fastidio a chi scrive davvero:
 * un campo esca invisibile, un tempo minimo di compilazione e una firma.
 */

defined( 'PC_AVVIO' ) || exit;

/** Chiave segreta per firmare i moduli; nasce da sola al primo uso. */
function modulo_segreto() {
	$s = impostazione( 'modulo_segreto', '' );
	if ( vuoto( $s ) ) {
		$s = bin2hex( random_bytes( 16 ) );
		impostazioni_salva( array( 'modulo_segreto' => $s ) );
	}
	return $s;
}

/** Firma del momento in cui il modulo è stato mostrato. */
function modulo_firma( $quando ) {
	return hash_hmac( 'sha256', (string) $quando, modulo_segreto() );
}

/**
 * L'indirizzo con cui il portale spedisce.
 *
 * Deve essere del dominio del sito: i provider rifiutano — o mandano in
 * posta indesiderata — una email che dice di venire da un dominio che
 * non è quello da cui parte davvero.
 */
function modulo_mittente() {
	$mittente = impostazione( 'modulo_mittente', '' );
	if ( ! vuoto( $mittente ) && filter_var( $mittente, FILTER_VALIDATE_EMAIL ) ) {
		return $mittente;
	}
	$host = preg_replace( '/^www\./', '', (string) parse_url( base_url(), PHP_URL_HOST ) );
	return 'noreply@' . ( '' === $host ? 'localhost' : $host );
}

/**
 * Manda una email di prova al destinatario di una città.
 *
 * Serve a rispondere alla domanda che non ha altra risposta: il modulo
 * funziona davvero su questo server? mail() dice solo se ha consegnato
 * al sistema di posta, non se l'email arriverà — ma un falso qui vuol
 * dire che non parte proprio, ed è già metà del problema.
 *
 * @return array( 'ok' => bool, 'messaggio' => string )
 */
function modulo_prova( $citta ) {
	$a = modulo_destinatario( $citta );
	if ( '' === $a ) {
		return array( 'ok' => false, 'messaggio' => 'Nessun indirizzo a cui mandarla: scrivi un\'email qui sotto o nella scheda della città.' );
	}
	if ( ! function_exists( 'mail' ) ) {
		return array( 'ok' => false, 'messaggio' => 'Questo server non ha la funzione mail() di PHP: il modulo non può spedire. Chiedi all\'hosting.' );
	}

	$corpo = "Questa è una prova mandata dal pannello del portale.\n\n"
		. "Se la stai leggendo, il modulo di contatto riesce a spedire.\n\n"
		. 'Destinatario: ' . $a . "\n"
		. 'Mittente:     ' . modulo_mittente() . "\n"
		. 'Quando:       ' . date( 'd/m/Y H:i' ) . "\n";

	$intestazioni = array(
		'From: ' . mb_encode_mimeheader( impostazione( 'brand', 'Sito' ) ) . ' <' . modulo_mittente() . '>',
		'Content-Type: text/plain; charset=UTF-8',
		'MIME-Version: 1.0',
	);

	$ok = @mail( $a, mb_encode_mimeheader( 'Prova del modulo di contatto' ), $corpo, implode( "\r\n", $intestazioni ) );

	if ( ! $ok ) {
		return array(
			'ok'        => false,
			'messaggio' => 'Il server ha rifiutato l\'invio a ' . $a . '. Di solito manca il servizio di posta, '
				. 'o il mittente non è del dominio del sito.',
		);
	}
	return array(
		'ok'        => true,
		'messaggio' => 'Prova consegnata al sistema di posta per ' . $a . '. Controlla la casella, '
			. 'e guarda anche nella posta indesiderata: se è finita lì, il mittente è da sistemare.',
	);
}

/** Destinatario delle richieste di una città. */
function modulo_destinatario( $citta ) {
	foreach ( array( $citta['email'], impostazione( 'modulo_email', '' ), impostazione( 'email', '' ) ) as $e ) {
		if ( ! vuoto( $e ) && filter_var( $e, FILTER_VALIDATE_EMAIL ) ) {
			return $e;
		}
	}
	return '';
}

/**
 * Elabora l'invio, se c'è.
 * Ritorna null, oppure array( 'ok' => bool, 'messaggio' => string, 'valori' => array ).
 */
function modulo_gestisci( $citta, $pagina ) {
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['pc_modulo'] ) ) {
		return null;
	}

	$valori = array(
		'nome'      => trim( (string) ( $_POST['nome'] ?? '' ) ),
		'telefono'  => trim( (string) ( $_POST['telefono'] ?? '' ) ),
		'email'     => trim( (string) ( $_POST['email'] ?? '' ) ),
		'messaggio' => trim( (string) ( $_POST['messaggio'] ?? '' ) ),
		'servizio'  => trim( (string) ( $_POST['servizio'] ?? '' ) ),
	);

	$no = function ( $m ) use ( $valori ) {
		return array( 'ok' => false, 'messaggio' => $m, 'valori' => $valori );
	};

	// Campo esca: lo compilano solo i robot.
	if ( '' !== trim( (string) ( $_POST['indirizzo2'] ?? '' ) ) ) {
		return $no( 'Invio non riuscito. Riprova, oppure chiamaci.' );
	}

	// Firma e tempo: il modulo deve essere stato aperto davvero.
	$quando = (int) ( $_POST['aperto'] ?? 0 );
	$firma  = (string) ( $_POST['firma'] ?? '' );
	if ( $quando <= 0 || ! hash_equals( modulo_firma( $quando ), $firma ) ) {
		return $no( 'La pagina è rimasta aperta troppo a lungo. Ricaricala e riprova.' );
	}
	$passati = time() - $quando;
	if ( $passati < 3 ) {
		return $no( 'Invio troppo rapido: ricontrolla i dati e riprova.' );
	}
	if ( $passati > 7200 ) {
		return $no( 'La pagina è rimasta aperta troppo a lungo. Ricaricala e riprova.' );
	}

	if ( vuoto( $valori['nome'] ) ) {
		return $no( 'Scrivi il tuo nome.' );
	}
	if ( vuoto( $valori['telefono'] ) && vuoto( $valori['email'] ) ) {
		return $no( 'Lascia almeno un recapito: telefono o email.' );
	}
	if ( ! vuoto( $valori['email'] ) && ! filter_var( $valori['email'], FILTER_VALIDATE_EMAIL ) ) {
		return $no( 'L\'indirizzo email non sembra valido.' );
	}
	if ( mb_strlen( $valori['messaggio'] ) < 10 ) {
		return $no( 'Scrivi due righe su cosa ti serve.' );
	}
	// Gli a capo nei campi brevi sono il segno di un tentativo di iniezione.
	foreach ( array( 'nome', 'telefono', 'email' ) as $campo ) {
		if ( preg_match( '/[\r\n]/', $valori[ $campo ] ) ) {
			return $no( 'Invio non riuscito. Riprova, oppure chiamaci.' );
		}
	}

	$a = modulo_destinatario( $citta );
	if ( '' === $a ) {
		return $no( 'Il modulo non è configurato. Chiamaci pure al telefono.' );
	}

	$oggetto = 'Richiesta da ' . $citta['nome'] . ' — ' . $valori['nome'];
	$corpo   = "Nuova richiesta dal sito.\n\n"
		. "Città:     " . $citta['nome'] . "\n"
		. "Pagina:    " . $pagina['titolo'] . "\n"
		. "Indirizzo: " . url_pagina( $citta, $pagina ) . "\n\n"
		. "Nome:      " . $valori['nome'] . "\n"
		. "Telefono:  " . ( vuoto( $valori['telefono'] ) ? '—' : $valori['telefono'] ) . "\n"
		. "Email:     " . ( vuoto( $valori['email'] ) ? '—' : $valori['email'] ) . "\n"
		. ( vuoto( $valori['servizio'] ) ? '' : "Servizio:  " . $valori['servizio'] . "\n" )
		. "\nMessaggio:\n" . $valori['messaggio'] . "\n";

	$mittente = modulo_mittente();

	$intestazioni = array(
		'From: ' . mb_encode_mimeheader( impostazione( 'brand', 'Sito' ) ) . ' <' . $mittente . '>',
		'Content-Type: text/plain; charset=UTF-8',
		'MIME-Version: 1.0',
	);
	if ( ! vuoto( $valori['email'] ) ) {
		$intestazioni[] = 'Reply-To: ' . $valori['email'];
	}

	$inviata = @mail( $a, mb_encode_mimeheader( $oggetto ), $corpo, implode( "\r\n", $intestazioni ) );

	if ( ! $inviata ) {
		return $no( 'Non è stato possibile inviare il messaggio. Chiamaci al telefono, ti rispondiamo subito.' );
	}

	return array(
		'ok'        => true,
		'messaggio' => 'Messaggio inviato. Ti richiamiamo il prima possibile.',
		'valori'    => array(),
	);
}
