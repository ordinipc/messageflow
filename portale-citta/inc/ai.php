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
 * Indirizzo di base dell'API.
 *
 * La variabile d'ambiente PC_AI_BASE esiste solo per poter collaudare
 * l'assistente contro un finto endpoint, senza consumare la quota vera.
 * In produzione non va impostata: il valore giusto è quello di riserva.
 */
function ai_base() {
	$prova = getenv( 'PC_AI_BASE' );
	return ( is_string( $prova ) && '' !== $prova ) ? $prova : 'https://generativelanguage.googleapis.com/v1beta';
}

/**
 * Chiede a Google quali modelli accetta questa chiave.
 *
 * Meglio di un elenco scritto nel codice: Google ritira i modelli senza
 * preavviso e un elenco fisso invecchia. Qui si vede sempre la verità.
 *
 * @return array( 'ok' => bool, 'modelli' => array, 'errore' => string )
 */
function ai_modelli() {
	$chiave = impostazione( 'gemini_key', '' );
	if ( vuoto( $chiave ) ) {
		return array( 'ok' => false, 'modelli' => array(), 'errore' => 'Chiave Gemini non impostata.' );
	}
	if ( ! function_exists( 'curl_init' ) ) {
		return array( 'ok' => false, 'modelli' => array(), 'errore' => 'Estensione cURL non disponibile sul server.' );
	}

	$ch = curl_init( ai_base() . '/models?pageSize=200' );
	curl_setopt_array( $ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 25,
		CURLOPT_HTTPHEADER     => array( 'x-goog-api-key: ' . $chiave ),
	) );
	$risposta = curl_exec( $ch );
	$stato    = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$errcurl  = curl_error( $ch );
	curl_close( $ch );

	if ( false === $risposta ) {
		return array( 'ok' => false, 'modelli' => array(), 'errore' => 'Connessione fallita: ' . $errcurl );
	}
	$dati = json_decode( (string) $risposta, true );
	if ( 200 !== $stato ) {
		$messaggio = pesca( is_array( $dati ) ? $dati : array(), 'error.message', 'Errore HTTP ' . $stato );
		return array( 'ok' => false, 'modelli' => array(), 'errore' => ai_traduci( $messaggio, $stato ) );
	}

	$modelli = array();
	foreach ( (array) pesca( is_array( $dati ) ? $dati : array(), 'models', array() ) as $m ) {
		$metodi = isset( $m['supportedGenerationMethods'] ) ? (array) $m['supportedGenerationMethods'] : array();
		if ( ! in_array( 'generateContent', $metodi, true ) ) {
			continue;
		}
		$nome = isset( $m['name'] ) ? (string) $m['name'] : '';
		// Arriva come "models/gemini-3.6-flash": teniamo solo l'ultima parte.
		$nome = preg_replace( '#^models/#', '', $nome );
		if ( '' === $nome ) {
			continue;
		}
		$modelli[] = array(
			'nome'      => $nome,
			'etichetta' => isset( $m['displayName'] ) ? (string) $m['displayName'] : $nome,
		);
	}

	usort( $modelli, function ( $a, $b ) {
		return strcmp( $b['nome'], $a['nome'] );
	} );

	return array( 'ok' => true, 'modelli' => $modelli, 'errore' => '' );
}

/**
 * Traduce in italiano gli errori più frequenti dell'API, con il rimedio.
 * Il testo originale di Google resta in coda: serve per cercare aiuto.
 */
function ai_traduci( $messaggio, $stato = 0 ) {
	$m = (string) $messaggio;

	if ( false !== stripos( $m, 'no longer available' ) || false !== stripos( $m, 'is not found' ) || false !== stripos( $m, 'not supported' ) ) {
		$rimedio = 'Il modello impostato non esiste più.';
		// Google di solito nomina il sostituto nel messaggio: l'ultimo
		// "models/…" citato è quello consigliato, non quello ritirato.
		if ( preg_match_all( '#models/([a-z0-9.\-]+)#i', $m, $citati ) && count( $citati[1] ) > 1 ) {
			$rimedio .= ' Google suggerisce "' . end( $citati[1] ) . '".';
		}
		return $rimedio . ' Vai in Impostazioni → Assistente e premi "Carica i modelli disponibili". — ' . $m;
	}
	if ( 400 === $stato && false !== stripos( $m, 'API key not valid' ) ) {
		return 'Chiave non valida: ricontrolla di averla copiata per intero da Google AI Studio. — ' . $m;
	}
	if ( 403 === $stato ) {
		return 'Chiave rifiutata: potrebbe non avere accesso all\'API Generative Language, o essere limitata a certi indirizzi IP. — ' . $m;
	}
	if ( 429 === $stato ) {
		return 'Hai superato il limite di richieste. Aspetta qualche minuto e riprova. — ' . $m;
	}
	if ( $stato >= 500 ) {
		return 'Google ha risposto con un errore temporaneo. Riprova fra poco. — ' . $m;
	}
	return $m;
}

/**
 * Chiama Gemini e restituisce il testo generato.
 * Ritorna array( 'ok' => bool, 'testo' => string, 'errore' => string ).
 */
function ai_chiedi( $istruzione, $schema = null, $massimo = 8192 ) {
	$chiave = impostazione( 'gemini_key', '' );
	if ( vuoto( $chiave ) ) {
		return array( 'ok' => false, 'testo' => '', 'errore' => 'Chiave Gemini non impostata.' );
	}
	if ( ! function_exists( 'curl_init' ) ) {
		return array( 'ok' => false, 'testo' => '', 'errore' => 'Estensione cURL non disponibile sul server.' );
	}

	$modello = impostazione( 'gemini_modello', 'gemini-3.6-flash' );
	$url     = ai_base() . '/models/' . rawurlencode( $modello ) . ':generateContent';

	$corpo = array(
		'contents'         => array(
			array( 'parts' => array( array( 'text' => $istruzione ) ) ),
		),
		'generationConfig' => array(
			'temperature'     => 0.8,
			// I modelli recenti "ragionano" prima di rispondere, e quei token
			// consumano lo stesso budget del testo: stretto qui significa
			// risposte tagliate a metà frase.
			'maxOutputTokens' => max( 1024, (int) $massimo ),
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
		return array( 'ok' => false, 'testo' => '', 'errore' => ai_traduci( $messaggio, $stato ) );
	}
	$dati   = is_array( $dati ) ? $dati : array();
	$testo  = pesca( $dati, 'candidates.0.content.parts.0.text', '' );
	$motivo = (string) pesca( $dati, 'candidates.0.finishReason', '' );

	if ( 'MAX_TOKENS' === $motivo ) {
		return array(
			'ok'     => false,
			'testo'  => '',
			'motivo' => $motivo,
			'errore' => 'La risposta è stata tagliata prima della fine: il modello ha esaurito lo spazio. '
				. 'Riprova; se capita di nuovo, in Impostazioni → Assistente scegli un modello "flash", '
				. 'che ragiona meno e lascia più spazio al testo.',
		);
	}
	if ( 'SAFETY' === $motivo || 'RECITATION' === $motivo || 'PROHIBITED_CONTENT' === $motivo ) {
		return array(
			'ok'     => false,
			'testo'  => '',
			'motivo' => $motivo,
			'errore' => 'Il modello si è fermato da solo (motivo: ' . $motivo . '). Riformula la richiesta.',
		);
	}
	if ( vuoto( $testo ) ) {
		return array(
			'ok'     => false,
			'testo'  => '',
			'motivo' => $motivo,
			'errore' => 'Risposta vuota dal modello' . ( '' === $motivo ? '' : ' (motivo: ' . $motivo . ')' ) . '.',
		);
	}
	return array( 'ok' => true, 'testo' => ai_ripulisci( $testo ), 'motivo' => $motivo, 'errore' => '' );
}

/**
 * Chiede un singolo testo facendolo restituire dentro un campo JSON.
 *
 * Serve a impedire che il modello "pensi ad alta voce": senza una forma
 * imposta capita che consegni i propri appunti invece del risultato.
 *
 * @param string $istruzione Istruzione completa.
 * @param string $campo      Nome del campo da leggere.
 * @param int    $max        Taglio di sicurezza in caratteri, 0 per nessuno.
 */
function ai_chiedi_testo( $istruzione, $campo = 'testo', $max = 0, $massimo_token = 8192 ) {
	$schema = array(
		'type'       => 'OBJECT',
		'properties' => array( $campo => array( 'type' => 'STRING' ) ),
		'required'   => array( $campo ),
	);

	$esito = ai_chiedi( $istruzione, $schema, $massimo_token );
	if ( ! $esito['ok'] ) {
		return $esito;
	}

	$grezzo = trim( $esito['testo'] );
	$dati   = json_decode( $grezzo, true );
	$testo  = ( is_array( $dati ) && isset( $dati[ $campo ] ) ) ? (string) $dati[ $campo ] : '';

	if ( '' === trim( $testo ) ) {
		// Il JSON non si è chiuso: quasi sempre vuol dire risposta tagliata.
		// Non si può consegnare il grezzo, finirebbe nel campo con le graffe.
		if ( ai_sembra_json( $grezzo ) ) {
			return array(
				'ok'     => false,
				'testo'  => '',
				'errore' => 'La risposta del modello è arrivata incompleta e non si può usare. '
					. 'Riprova: di solito al secondo tentativo va.',
			);
		}
		// Nessuna forma JSON: è testo semplice, si tiene.
		$testo = $grezzo;
	}

	$testo = ai_ripulisci( $testo );
	$testo = trim( $testo, " \t\n\r\0\x0B\"'«»" );

	if ( ai_testo_sospetto( $testo ) ) {
		return array(
			'ok'     => false,
			'testo'  => '',
			'errore' => 'Il modello ha risposto con qualcosa che non è un testo utilizzabile. Riprova: capita, ed è quasi sempre una volta sola.',
		);
	}

	if ( $max > 0 ) {
		$testo = ai_taglia( $testo, $max );
	}

	return array( 'ok' => true, 'testo' => $testo, 'errore' => '' );
}

/** True se il testo ha la forma di un oggetto JSON, anche spezzato. */
function ai_sembra_json( $testo ) {
	$testo = ltrim( (string) $testo );
	if ( '' === $testo ) {
		return false;
	}
	if ( '{' === $testo[0] || '[' === $testo[0] ) {
		return true;
	}
	// Una coppia "chiave": "valore" all'inizio basta a riconoscerlo.
	return 1 === preg_match( '/^\s*"[a-z_]+"\s*:/i', $testo );
}

/**
 * Riconosce le risposte degenerate.
 *
 * Il caso visto dal vivo: il modello conta i caratteri per rispettare un
 * limite e consegna il conteggio ("30:n 31:i 32: 33:|") invece della frase.
 */
function ai_testo_sospetto( $testo ) {
	$testo = trim( (string) $testo );
	if ( '' === $testo ) {
		return true;
	}
	// Graffe e coppie chiave-valore: è la risposta grezza, non il testo.
	if ( ai_sembra_json( $testo ) ) {
		return true;
	}
	// Tre o più gruppi "numero:carattere" sono appunti, non prosa.
	if ( preg_match_all( '/\b\d{1,3}\s*:\s*\S?/u', $testo ) >= 3 ) {
		return true;
	}
	// Una risposta fatta quasi solo di cifre e due punti.
	$lettere = preg_match_all( '/\p{L}/u', $testo );
	if ( $lettere > 0 && preg_match_all( '/[\d:]/u', $testo ) > $lettere ) {
		return true;
	}
	return false;
}

/** Taglia a una lunghezza massima senza spezzare le parole. */
function ai_taglia( $testo, $max ) {
	$testo = trim( (string) $testo );
	if ( mb_strlen( $testo ) <= $max ) {
		return $testo;
	}
	$corto  = mb_substr( $testo, 0, $max );
	$spazio = mb_strrpos( $corto, ' ' );
	if ( false !== $spazio && $spazio > $max * 0.6 ) {
		$corto = mb_substr( $corto, 0, $spazio );
	}
	$corto = rtrim( $corto, " ,;:-–—|" );

	// Un titolo non finisce con una preposizione o un articolo appesi.
	$appese = array(
		'a', 'e', 'o', 'di', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra',
		'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una',
		'del', 'dello', 'della', 'dei', 'degli', 'delle',
		'al', 'allo', 'alla', 'ai', 'agli', 'alle',
		'dal', 'dallo', 'dalla', 'dai', 'dagli', 'dalle',
		'nel', 'nello', 'nella', 'nei', 'negli', 'nelle',
		'sul', 'sullo', 'sulla', 'sui', 'sugli', 'sulle',
		'col', 'coi',
	);
	while ( true ) {
		$spazio = mb_strrpos( $corto, ' ' );
		if ( false === $spazio ) {
			break;
		}
		$ultima = mb_strtolower( mb_substr( $corto, $spazio + 1 ) );
		if ( ! in_array( $ultima, $appese, true ) ) {
			break;
		}
		$corto = rtrim( mb_substr( $corto, 0, $spazio ), " ,;:-–—|" );
	}

	return $corto;
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

/* ---------------------------------------------------------------------------
 * Immagini
 * ------------------------------------------------------------------------- */

/** Il modello scelto per le immagini. */
function ai_modello_immagini() {
	return impostazione( 'gemini_modello_immagini', 'gemini-3.1-flash-image' );
}

/** True se il nome del modello lascia pensare che sappia disegnare. */
function ai_modello_e_immagini( $nome ) {
	return false !== stripos( (string) $nome, 'image' );
}

/**
 * Genera un'immagine e la salva nella libreria.
 *
 * @param array  $citta      Città.
 * @param array  $pagina     Pagina.
 * @param string $richiesta  Descrizione libera; se vuota se ne costruisce una.
 * @return array( 'ok' => bool, 'file' => string, 'alt' => string, 'errore' => string )
 */
function ai_immagine( $citta, $pagina, $richiesta = '' ) {
	$chiave = impostazione( 'gemini_key', '' );
	if ( vuoto( $chiave ) ) {
		return array( 'ok' => false, 'file' => '', 'alt' => '', 'errore' => 'Chiave Gemini non impostata.' );
	}
	if ( ! function_exists( 'curl_init' ) ) {
		return array( 'ok' => false, 'file' => '', 'alt' => '', 'errore' => 'Estensione cURL non disponibile sul server.' );
	}

	$soggetto = vuoto( $richiesta ) ? ai_soggetto_immagine( $citta, $pagina ) : trim( $richiesta );
	$imp      = impostazioni();

	$istruzione = "Crea un'immagine fotografica orizzontale, formato 16:9, per l'anteprima di una pagina web.\n\n"
		. "Soggetto: " . $soggetto . "\n\n"
		. "Stile: fotografia professionale, luce naturale, messa a fuoco sul soggetto, sfondo sobrio.\n"
		. "Tonalità coerenti con il giallo " . $imp['colore_accento'] . " e il nero, senza esagerare.\n\n"
		. "Da evitare in modo assoluto:\n"
		. "- qualsiasi testo, scritta, logo, insegna o filigrana nell'immagine\n"
		. "- volti riconoscibili di persone\n"
		. "- marchi, loghi di automobili o insegne commerciali esistenti\n"
		. "- luoghi reali riconoscibili: deve essere una scena generica, non " . $citta['nome'] . "\n"
		. "- numeri di targa, documenti o dati leggibili";

	$url = ai_base() . '/models/' . rawurlencode( ai_modello_immagini() ) . ':generateContent';

	$corpo = array(
		'contents'         => array(
			array( 'parts' => array( array( 'text' => $istruzione ) ) ),
		),
		'generationConfig' => array(
			'responseModalities' => array( 'IMAGE' ),
		),
	);

	$ch = curl_init( $url );
	curl_setopt_array( $ch, array(
		CURLOPT_POST           => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 120,
		CURLOPT_HTTPHEADER     => array(
			'Content-Type: application/json',
			'x-goog-api-key: ' . $chiave,
		),
		CURLOPT_POSTFIELDS     => json_encode( $corpo, JSON_UNESCAPED_UNICODE ),
	) );
	$risposta = curl_exec( $ch );
	$stato    = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$errcurl  = curl_error( $ch );
	curl_close( $ch );

	if ( false === $risposta ) {
		return array( 'ok' => false, 'file' => '', 'alt' => '', 'errore' => 'Connessione fallita: ' . $errcurl );
	}
	$dati = json_decode( (string) $risposta, true );
	if ( 200 !== $stato ) {
		$messaggio = pesca( is_array( $dati ) ? $dati : array(), 'error.message', 'Errore HTTP ' . $stato );
		return array( 'ok' => false, 'file' => '', 'alt' => '', 'errore' => ai_traduci( $messaggio, $stato ) );
	}

	$immagine = ai_estrai_immagine( is_array( $dati ) ? $dati : array() );
	if ( null === $immagine ) {
		$motivo = pesca( is_array( $dati ) ? $dati : array(), 'candidates.0.finishReason', '' );
		$extra  = vuoto( $motivo ) ? '' : ' (motivo: ' . $motivo . ')';
		return array(
			'ok'     => false,
			'file'   => '',
			'alt'    => '',
			'errore' => 'Il modello non ha restituito nessuna immagine' . $extra
				. '. Controlla che "' . ai_modello_immagini() . '" sia un modello capace di generarle.',
		);
	}

	$alt  = ai_alt_immagine( $citta, $pagina );
	$base = $pagina['slug'] . '-' . $citta['slug'];

	$salvata = media_salva_dati( $immagine['dati'], $immagine['mime'], $base, $alt );
	if ( ! $salvata['ok'] ) {
		return array( 'ok' => false, 'file' => '', 'alt' => '', 'errore' => $salvata['messaggio'] );
	}

	return array( 'ok' => true, 'file' => $salvata['file'], 'alt' => $alt, 'errore' => '' );
}

/** Pesca i byte dell'immagine dalla risposta, qualunque forma abbia. */
function ai_estrai_immagine( $dati ) {
	$parti = pesca( $dati, 'candidates.0.content.parts', array() );
	foreach ( (array) $parti as $parte ) {
		// L'API usa inlineData, alcune librerie inline_data: accettiamo entrambe.
		$blocco = null;
		if ( isset( $parte['inlineData'] ) && is_array( $parte['inlineData'] ) ) {
			$blocco = $parte['inlineData'];
		} elseif ( isset( $parte['inline_data'] ) && is_array( $parte['inline_data'] ) ) {
			$blocco = $parte['inline_data'];
		}
		if ( null === $blocco || empty( $blocco['data'] ) ) {
			continue;
		}
		$binario = base64_decode( (string) $blocco['data'], true );
		if ( false === $binario || '' === $binario ) {
			continue;
		}
		$mime = isset( $blocco['mimeType'] ) ? $blocco['mimeType'] : ( isset( $blocco['mime_type'] ) ? $blocco['mime_type'] : 'image/png' );
		return array( 'dati' => $binario, 'mime' => $mime );
	}
	return null;
}

/** Soggetto predefinito dell'immagine, dedotto dalla pagina. */
function ai_soggetto_immagine( $citta, $pagina ) {
	$titolo = trim( (string) $pagina['titolo'] );
	if ( 'home' === $pagina['tipo'] || '' === $titolo ) {
		return 'il mestiere di ' . impostazione( 'brand', 'questa attività' ) . ', attrezzi e banco di lavoro ordinati';
	}
	return 'una scena che rappresenta il servizio "' . $titolo . '": attrezzi, mani al lavoro, dettaglio ravvicinato';
}

/** Testo alternativo dell'immagine. */
function ai_alt_immagine( $citta, $pagina ) {
	if ( 'home' === $pagina['tipo'] ) {
		return impostazione( 'brand', '' ) . ' a ' . $citta['nome'];
	}
	return $pagina['titolo'] . ' a ' . $citta['nome'];
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
	// Il testo di approfondimento è lungo: serve spazio per il ragionamento
	// del modello e per le quattrocento parole richieste.
	return ai_chiedi_testo( $istruzione, 'testo', 0, 16384 );
}

/** Genera la meta description. */
function ai_descrizione( $citta, $pagina ) {
	$istruzione = "Scrivi la meta description per Google di questa pagina.\n\n"
		. ai_contesto( $citta, $pagina ) . "\n\n"
		. "Una frase sola, breve, che contenga il nome della città e inviti al contatto.\n"
		. "Tienila corta: deve stare in una riga di risultato di Google.\n\n"
		. ai_regole();
	return ai_chiedi_testo( $istruzione, 'descrizione', 158 );
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

/** Genera il titolo per Google. */
function ai_titolo( $citta, $pagina ) {
	$brand = impostazione( 'brand', '' );

	// Nessuna richiesta di contare i caratteri: il modello proverebbe a
	// farlo davvero e a volte consegna il conteggio al posto del titolo.
	// La misura la impone il codice, qui sotto.
	$istruzione = "Scrivi il tag title di questa pagina, la riga blu che compare nei risultati di Google.\n\n"
		. ai_contesto( $citta, $pagina ) . "\n\n"
		. "Come deve essere:\n"
		. "- breve, una riga sola, molto meno di una frase intera\n"
		. "- deve iniziare dal servizio e contenere il nome della città\n"
		. "- niente virgolette, niente punto finale, nessun marchio inventato\n"
		. ( vuoto( $brand ) ? '' : "- non aggiungere \"" . $brand . "\": lo mette il portale se ci sta\n" )
		. "\n" . ai_regole();

	$esito = ai_chiedi_testo( $istruzione, 'titolo' );
	if ( ! $esito['ok'] ) {
		return $esito;
	}

	// Il nome dell'attività si aggiunge solo se il risultato resta corto.
	$titolo = $esito['testo'];
	if ( ! vuoto( $brand ) && false === mb_stripos( $titolo, $brand ) ) {
		$completo = $titolo . ' | ' . $brand;
		if ( mb_strlen( $completo ) <= 60 ) {
			$titolo = $completo;
		}
	}

	$esito['testo'] = ai_taglia( $titolo, 60 );
	return $esito;
}

/** Genera l'introduzione breve. */
function ai_intro( $citta, $pagina ) {
	$istruzione = "Scrivi l'introduzione che compare sotto il titolo principale della pagina.\n\n"
		. ai_contesto( $citta, $pagina ) . "\n\n"
		. "Una o due frasi che dicano subito cosa fate e dove. Breve.\n\n"
		. ai_regole();
	return ai_chiedi_testo( $istruzione, 'introduzione', 220 );
}
