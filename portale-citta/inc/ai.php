<?php
/**
 * Assistente di scrittura con Google Gemini.
 * La chiave si imposta in Amministrazione → Impostazioni.
 */

defined( 'PC_AVVIO' ) || exit;

function ai_attiva() {
	return ! vuoto( impostazione( 'gemini_key', '' ) );
}

/**
 * Chiama Gemini e restituisce il testo generato.
 * Ritorna array( 'ok' => bool, 'testo' => string, 'errore' => string ).
 */
function ai_chiedi( $istruzione, $schema = null ) {
	$chiave = impostazione( 'gemini_key', '' );
	if ( vuoto( $chiave ) ) {
		return array( 'ok' => false, 'testo' => '', 'errore' => 'Chiave Gemini non impostata.' );
	}
	if ( ! function_exists( 'curl_init' ) ) {
		return array( 'ok' => false, 'testo' => '', 'errore' => 'Estensione cURL non disponibile sul server.' );
	}

	$modello = impostazione( 'gemini_modello', 'gemini-2.5-flash' );
	$url     = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $modello ) . ':generateContent';

	$corpo = array(
		'contents'         => array(
			array( 'parts' => array( array( 'text' => $istruzione ) ) ),
		),
		'generationConfig' => array(
			'temperature'     => 0.8,
			'maxOutputTokens' => 2048,
		),
	);
	if ( is_array( $schema ) ) {
		$corpo['generationConfig']['responseMimeType'] = 'application/json';
		$corpo['generationConfig']['responseSchema']   = $schema;
	}

	$ch = curl_init( $url );
	curl_setopt_array( $ch, array(
		CURLOPT_POST           => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 60,
		CURLOPT_HTTPHEADER     => array(
			'Content-Type: application/json',
			'x-goog-api-key: ' . $chiave,
		),
		CURLOPT_POSTFIELDS     => json_encode( $corpo, JSON_UNESCAPED_UNICODE ),
	) );
	$risposta = curl_exec( $ch );
	$stato    = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$errore   = curl_error( $ch );
	curl_close( $ch );

	if ( false === $risposta ) {
		return array( 'ok' => false, 'testo' => '', 'errore' => 'Connessione fallita: ' . $errore );
	}
	$dati = json_decode( (string) $risposta, true );
	if ( 200 !== $stato ) {
		$messaggio = pesca( is_array( $dati ) ? $dati : array(), 'error.message', 'Errore HTTP ' . $stato );
		return array( 'ok' => false, 'testo' => '', 'errore' => $messaggio );
	}
	$testo = pesca( is_array( $dati ) ? $dati : array(), 'candidates.0.content.parts.0.text', '' );
	if ( vuoto( $testo ) ) {
		return array( 'ok' => false, 'testo' => '', 'errore' => 'Risposta vuota dal modello.' );
	}
	return array( 'ok' => true, 'testo' => ai_ripulisci( $testo ), 'errore' => '' );
}

/** Toglie il markdown che il modello aggiunge di sua iniziativa. */
function ai_ripulisci( $testo ) {
	$testo = trim( (string) $testo );
	$testo = preg_replace( '/^```[a-z]*\s*/i', '', $testo );
	$testo = preg_replace( '/```\s*$/', '', $testo );
	// Prima grassetto e corsivo, poi i marcatori di riga: l'ordine conta.
	$testo = preg_replace( '/\*\*(.+?)\*\*/su', '$1', $testo );
	$testo = preg_replace( '/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/su', '$1', $testo );
	$testo = preg_replace( '/^#{1,6}\s*/m', '', $testo );
	$testo = preg_replace( '/^\s*[-*]\s+/m', '', $testo );
	return trim( $testo );
}

/** Contesto testuale della città, da passare al modello. */
function ai_contesto( $citta, $pagina = null ) {
	$imp   = impostazioni();
	$parti = array();
	$parti[] = 'Attività: ' . $imp['brand'];
	$parti[] = 'Città: ' . $citta['nome'] . ( vuoto( $citta['provincia'] ) ? '' : ' (' . $citta['provincia'] . ')' );
	if ( ! vuoto( $citta['indirizzo'] ) ) {
		$parti[] = 'Indirizzo: ' . $citta['indirizzo'] . ' ' . $citta['cap'];
	}
	if ( ! vuoto( $citta['zone'] ) ) {
		$parti[] = 'Quartieri serviti: ' . implode( ', ', righe( $citta['zone'] ) );
	}
	if ( ! vuoto( $citta['comuni'] ) ) {
		$parti[] = 'Comuni limitrofi: ' . implode( ', ', righe( $citta['comuni'] ) );
	}
	if ( ! vuoto( $citta['perche'] ) ) {
		$parti[] = 'Punti di forza: ' . implode( '; ', righe( $citta['perche'] ) );
	}
	if ( $pagina ) {
		$parti[] = 'Servizio della pagina: ' . $pagina['titolo'];
		if ( ! vuoto( $pagina['inclusi'] ) ) {
			$parti[] = 'Cosa comprende: ' . implode( '; ', righe( $pagina['inclusi'] ) );
		}
		if ( ! vuoto( $pagina['prezzo_da'] ) ) {
			$parti[] = 'Prezzo: da ' . $pagina['prezzo_da'] . ' €' . ( vuoto( $pagina['prezzo_a'] ) ? '' : ' a ' . $pagina['prezzo_a'] . ' €' );
		}
	}
	return implode( "\n", $parti );
}

/** Regole comuni a ogni richiesta. */
function ai_regole() {
	return "Regole:\n"
		. "- Scrivi in italiano, in seconda persona plurale o impersonale, tono professionale e concreto.\n"
		. "- Niente superlativi pubblicitari, niente promesse non verificabili, niente emoji.\n"
		. "- Cita la città in modo naturale, senza ripeterla in ogni frase.\n"
		. "- Non inventare dati: usa solo le informazioni fornite.\n"
		. "- Restituisci solo il testo richiesto, senza titoli né formattazione markdown.";
}

/** Genera il testo di approfondimento. */
function ai_corpo( $citta, $pagina ) {
	$istruzione = "Scrivi il testo di approfondimento per una pagina di servizio locale.\n\n"
		. ai_contesto( $citta, $pagina ) . "\n\n"
		. "Lunghezza: 300-400 parole, divise in 3 o 4 paragrafi separati da una riga vuota.\n"
		. "Spiega quando serve il servizio, come si svolge, cosa deve sapere il cliente e cosa lo distingue in questa città.\n\n"
		. ai_regole();
	return ai_chiedi( $istruzione );
}

/** Genera la meta description. */
function ai_descrizione( $citta, $pagina ) {
	$istruzione = "Scrivi la meta description per Google di questa pagina.\n\n"
		. ai_contesto( $citta, $pagina ) . "\n\n"
		. "Massimo 155 caratteri, una sola frase, deve contenere il nome della città e invitare al contatto.\n\n"
		. ai_regole();
	return ai_chiedi( $istruzione );
}

/** Genera un elenco di FAQ. */
function ai_faq( $citta, $pagina, $quante = 6 ) {
	$schema = array(
		'type'  => 'ARRAY',
		'items' => array(
			'type'       => 'OBJECT',
			'properties' => array(
				'domanda'  => array( 'type' => 'STRING' ),
				'risposta' => array( 'type' => 'STRING' ),
			),
			'required'   => array( 'domanda', 'risposta' ),
		),
	);
	$istruzione = "Genera {$quante} domande frequenti con risposta per questa pagina di servizio locale.\n\n"
		. ai_contesto( $citta, $pagina ) . "\n\n"
		. "Le domande devono essere quelle che una persona digiterebbe davvero su Google.\n"
		. "Ogni risposta: 2-4 frasi, concreta, senza rimandare genericamente al contatto.\n\n"
		. ai_regole();

	$esito = ai_chiedi( $istruzione, $schema );
	if ( ! $esito['ok'] ) {
		return $esito;
	}
	$voci = json_decode( $esito['testo'], true );
	if ( ! is_array( $voci ) ) {
		return array( 'ok' => false, 'voci' => array(), 'errore' => 'Risposta non interpretabile.' );
	}
	$pulite = array();
	foreach ( $voci as $v ) {
		if ( empty( $v['domanda'] ) || empty( $v['risposta'] ) ) {
			continue;
		}
		$pulite[] = array(
			'domanda'  => trim( (string) $v['domanda'] ),
			'risposta' => trim( (string) $v['risposta'] ),
		);
	}
	return array( 'ok' => true, 'voci' => $pulite, 'errore' => '' );
}

/** Genera l'introduzione breve. */
function ai_intro( $citta, $pagina ) {
	$istruzione = "Scrivi l'introduzione che compare sotto il titolo principale della pagina.\n\n"
		. ai_contesto( $citta, $pagina ) . "\n\n"
		. "Una o due frasi, massimo 40 parole, che dicano subito cosa fate e dove.\n\n"
		. ai_regole();
	return ai_chiedi( $istruzione );
}
