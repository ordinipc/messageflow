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
	// Qui si toglie solo il recinto di codice attorno al JSON. La
	// formattazione non si tocca: chi ha chiesto un testo formattato la
	// vuole, e chi ha chiesto testo semplice la toglie più avanti. Prima
	// spariva qui per tutti, e il grassetto non arrivava mai.
	return array( 'ok' => true, 'testo' => ai_togli_recinto( $testo ), 'motivo' => $motivo, 'errore' => '' );
}

/** Toglie il recinto ```…``` con cui certi modelli incartano la risposta. */
function ai_togli_recinto( $testo ) {
	$testo = trim( (string) $testo );
	$testo = preg_replace( '/^```[a-z]*\s*/i', '', $testo );
	$testo = preg_replace( '/```\s*$/', '', $testo );
	return trim( $testo );
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
function ai_chiedi_testo( $istruzione, $campo = 'testo', $max = 0, $massimo_token = 8192, $formato = 'semplice' ) {
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

	if ( 'html' === $formato ) {
		$testo = ai_ripulisci_html( $testo );
	} else {
		$testo = ai_ripulisci( $testo );
		$testo = trim( $testo, " \t\n\r\0\x0B\"'«»" );
	}

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

/**
 * Ripulitura per i campi che accettano formattazione.
 *
 * Differenza con ai_ripulisci(): lì i marcatori markdown si buttano via,
 * qui si traducono. Al modello si chiede HTML, ma ogni tanto risponde in
 * markdown lo stesso — e buttare via gli asterischi butterebbe via anche
 * il grassetto che ci avevamo chiesto di mettere.
 *
 * Alla fine passa dal filtro del server: quello che torna è già pronto
 * da salvare.
 */
function ai_ripulisci_html( $testo ) {
	$testo = trim( (string) $testo );
	$testo = preg_replace( '/^```[a-z]*\s*/i', '', $testo );
	$testo = preg_replace( '/```\s*$/', '', $testo );

	// Titoli markdown. Si va tutti su h3: h1 è il titolo della pagina e
	// h2 lo mette la sezione, quindi qui sotto si riparte da lì.
	$testo = preg_replace( '/^\s*#{1,6}\s*(.+?)\s*$/m', '<h3>$1</h3>', $testo );

	// Righe di elenco consecutive → un solo <ul>.
	$testo = preg_replace_callback(
		'/(?:^[ \t]*[-*+][ \t]+.+(?:\n|$))+/m',
		function ( $pezzi ) {
			$voci = '';
			foreach ( preg_split( "/\n/", trim( $pezzi[0] ) ) as $riga ) {
				$riga = preg_replace( '/^[ \t]*[-*+][ \t]+/', '', $riga );
				if ( '' !== trim( $riga ) ) {
					$voci .= '<li>' . trim( $riga ) . '</li>';
				}
			}
			return '' === $voci ? '' : '<ul>' . $voci . '</ul>' . "\n";
		},
		$testo
	);

	// Grassetto e corsivo, in quest'ordine: ** prima di *.
	$testo = preg_replace( '/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $testo );
	$testo = preg_replace( '/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/su', '<em>$1</em>', $testo );

	return corpo_pulisci( $testo );
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

	return ai_disegna_immagine(
		$soggetto,
		ai_alt_immagine( $citta, $pagina ),
		$pagina['slug'] . '-' . $citta['slug'],
		$citta['nome']
	);
}

/**
 * Disegna un'immagine e la salva in archivio.
 *
 * Sta separata da ai_immagine() perché la home del portale non appartiene
 * a nessuna città: le serve lo stesso disegno, non lo stesso contesto.
 *
 * @param string $soggetto    Cosa deve mostrare.
 * @param string $alt         Testo alternativo.
 * @param string $base        Base del nome del file.
 * @param string $evita_luogo Luogo reale da non riprodurre, se c'è.
 */
function ai_disegna_immagine( $soggetto, $alt, $base, $evita_luogo = '' ) {
	$chiave = impostazione( 'gemini_key', '' );
	if ( vuoto( $chiave ) ) {
		return array( 'ok' => false, 'file' => '', 'alt' => '', 'errore' => 'Chiave Gemini non impostata.' );
	}
	if ( ! function_exists( 'curl_init' ) ) {
		return array( 'ok' => false, 'file' => '', 'alt' => '', 'errore' => 'Estensione cURL non disponibile sul server.' );
	}

	$imp = impostazioni();

	$istruzione = "Crea un'immagine fotografica orizzontale, formato 16:9, per l'anteprima di una pagina web.\n\n"
		. "Soggetto: " . $soggetto . "\n\n"
		. "Stile: fotografia professionale, luce naturale, messa a fuoco sul soggetto, sfondo sobrio.\n"
		. "Tonalità coerenti con il giallo " . $imp['colore_accento'] . " e il nero, senza esagerare.\n\n"
		. "Da evitare in modo assoluto:\n"
		. "- qualsiasi testo, scritta, logo, insegna o filigrana nell'immagine\n"
		. "- volti riconoscibili di persone\n"
		. "- marchi, loghi di automobili o insegne commerciali esistenti\n"
		. "- luoghi reali riconoscibili: deve essere una scena generica"
		. ( vuoto( $evita_luogo ) ? '' : ', non ' . $evita_luogo ) . "\n"
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
/* ---------------------------------------------------------------------------
 * Migliorare invece di riscrivere
 *
 * Un testo già scritto contiene cose che il modello non sa: prezzi veri,
 * tempi veri, il modo in cui l'attività parla di sé. Buttarlo e ripartire da
 * zero è una perdita secca, e chi ha scritto quelle righe se ne accorge.
 * Quando il campo è già pieno, quindi, l'assistente non scrive: corregge.
 * ------------------------------------------------------------------------- */

/**
 * Il testo già presente in un campo, se è abbastanza da valere qualcosa.
 *
 * Tre parole non sono un testo da migliorare: sono un inizio buttato lì, e
 * su quello conviene lasciar scrivere da capo.
 */
function ai_esistente( $testo ) {
	$nudo = trim( preg_replace( '/\s+/u', ' ', strip_tags( (string) $testo ) ) );
	return mb_strlen( $nudo ) >= 40 ? trim( (string) $testo ) : '';
}

/**
 * Il blocco di istruzione che trasforma «scrivi» in «migliora».
 * Torna '' se non c'è niente da migliorare: allora vale l'istruzione normale.
 */
function ai_istruzione_migliora( $testo ) {
	$esistente = ai_esistente( $testo );
	if ( '' === $esistente ) {
		return '';
	}
	return "ATTENZIONE: questo testo esiste già. Non riscriverlo da capo, miglioralo.\n"
		. "- I fatti, i numeri, i prezzi, i tempi e i nomi propri che ci sono dentro sono veri "
		. "e tu non li sai: tienili come sono. Non aggiungerne di nuovi.\n"
		. "- Tieni l'ordine degli argomenti e le frasi che già funzionano. Si riconosce che è lo stesso testo.\n"
		. "- Intervieni dove serve: frasi contorte, ripetizioni, paragrafi troppo lunghi, "
		. "passaggi che danno per scontato quello che il cliente non sa, formattazione mancante.\n"
		. "- Puoi aggiungere quello che manca davvero, ma non allungare per allungare.\n"
		. "- Se è già buono, restituiscilo quasi identico: va bene anche cambiare poco.\n\n"
		. "Testo attuale, da migliorare:\n---\n" . $esistente . "\n---";
}

/* ---------------------------------------------------------------------------
 * Il richiamo alla pagina del servizio
 *
 * Un articolo del blog che spiega un problema e finisce lì è mezzo lavoro:
 * chi ha letto vuole sapere dove si risolve. Il richiamo in fondo porta alla
 * pagina del servizio della stessa città — un collegamento interno vero, che
 * serve al lettore e che Google legge come struttura del sito.
 * ------------------------------------------------------------------------- */

/** Le pagine di servizio pubblicate di una città, con il loro indirizzo. */
function ai_pagine_servizio( $citta, $escludi_id = '' ) {
	$fuori = array();
	foreach ( pagine_di_citta( $citta['id'], true ) as $p ) {
		if ( ! in_array( $p['tipo'], array( 'servizio', 'servizi' ), true ) ) {
			continue;
		}
		if ( '' !== $escludi_id && $p['id'] === $escludi_id ) {
			continue;
		}
		$fuori[] = array(
			'id'     => $p['id'],
			'titolo' => $p['titolo'],
			'url'    => url_pagina( $citta, $p ),
		);
	}
	return $fuori;
}

/** L'elenco delle pagine di servizio, da mettere nel contesto del modello. */
function ai_elenco_servizi( $pagine ) {
	if ( empty( $pagine ) ) {
		return '';
	}
	$righe = array();
	foreach ( $pagine as $p ) {
		$righe[] = '- ' . $p['titolo'] . ' → ' . $p['url'];
	}
	return "Pagine di servizio di questa città (sono gli unici indirizzi che puoi usare):\n"
		. implode( "\n", $righe );
}

/** L'istruzione che chiede il richiamo finale. */
function ai_regole_richiamo( $pagine, $obbligatorio = true ) {
	if ( empty( $pagine ) ) {
		return '';
	}
	return "Chiusura" . ( $obbligatorio ? ' (obbligatoria)' : ' (solo se ci sta bene)' ) . ":\n"
		. "- " . ( $obbligatorio ? 'Chiudi' : 'Puoi chiudere' ) . " con un <h3> e un paragrafo che rimanda "
		. "alla pagina di servizio più vicina all'argomento, fra quelle elencate sopra.\n"
		. "- Il collegamento si scrive <a href=\"INDIRIZZO\">testo</a>, con l'indirizzo copiato "
		. "esatto dall'elenco. Non inventare indirizzi: quelli che non sono nell'elenco vengono tolti.\n"
		. "- Un collegamento solo, dentro una frase che dice cosa si trova di là. "
		. "Non «clicca qui», non «scopri di più».";
}

/**
 * Quale pagina di servizio c'entra di più con un testo.
 *
 * Confronto grezzo di parole in comune, ed è giusto così: serve a scegliere
 * fra cinque pagine, non a capire la lingua.
 */
function ai_servizio_piu_vicino( $pagine, $testo ) {
	if ( empty( $pagine ) ) {
		return null;
	}
	$scarta = array( 'della', 'delle', 'degli', 'nella', 'nelle', 'sono', 'come', 'cosa',
		'quando', 'dove', 'perche', 'perché', 'tutti', 'tutte', 'questo', 'questa', 'anche',
		'servizio', 'servizi', 'pagina', 'casa', 'auto' );

	$parole = function ( $t ) use ( $scarta ) {
		$t    = mb_strtolower( strip_tags( (string) $t ) );
		$out  = array();
		foreach ( preg_split( '/[^\p{L}]+/u', $t, -1, PREG_SPLIT_NO_EMPTY ) as $w ) {
			if ( mb_strlen( $w ) >= 5 && ! in_array( $w, $scarta, true ) ) {
				$out[ $w ] = true;
			}
		}
		return $out;
	};

	$cerca   = $parole( $testo );
	$scelta  = $pagine[0];
	$massimo = -1;
	foreach ( $pagine as $p ) {
		$quante = count( array_intersect_key( $parole( $p['titolo'] ), $cerca ) );
		if ( $quante > $massimo ) {
			$massimo = $quante;
			$scelta  = $p;
		}
	}
	return $scelta;
}

/**
 * Tiene solo i collegamenti che puntano davvero da qualche parte, e mette
 * il richiamo se il modello non l'ha messo.
 *
 * Un modello che inventa un indirizzo non lo dice: scrive un link che sembra
 * giusto e porta a una pagina che non esiste. Qui gli indirizzi ammessi sono
 * quelli dell'elenco, e gli altri diventano testo normale.
 */
function ai_richiamo_applica( $html, $citta, $pagine, $riferimento = '', $obbligatorio = true ) {
	$html = (string) $html;
	if ( empty( $pagine ) ) {
		return $html;
	}

	$ammessi = array();
	foreach ( $pagine as $p ) {
		$ammessi[ rtrim( $p['url'], '/' ) ] = true;
	}

	$trovato = false;
	$html    = preg_replace_callback(
		'#<a\b[^>]*href\s*=\s*["\']([^"\']*)["\'][^>]*>(.*?)</a>#is',
		function ( $m ) use ( $ammessi, &$trovato ) {
			if ( isset( $ammessi[ rtrim( trim( $m[1] ), '/' ) ] ) ) {
				$trovato = true;
				return $m[0];
			}
			// Indirizzo inventato: resta il testo, sparisce il collegamento.
			return $m[2];
		},
		$html
	);

	if ( $trovato || ! $obbligatorio ) {
		return $html;
	}

	// Il modello non l'ha messo: lo mettiamo noi, che almeno l'indirizzo è giusto.
	$scelta = ai_servizio_piu_vicino( $pagine, '' === $riferimento ? $html : $riferimento );
	if ( ! $scelta ) {
		return $html;
	}
	$nome = titolo_con_citta( $scelta['titolo'], $citta['nome'] );
	return rtrim( $html ) . "\n"
		. '<h3>Ti serve ' . e( mb_strtolower( $scelta['titolo'] ) ) . ' a ' . e( $citta['nome'] ) . '?</h3>' . "\n"
		. '<p>Nella pagina <a href="' . e( $scelta['url'] ) . '">' . e( $nome ) . '</a> trovi '
		. 'cosa comprende il servizio, come si svolge e quanto costa.</p>';
}

function ai_regole() {
	return "Regole:\n"
		. "- Scrivi in italiano, in seconda persona plurale o impersonale, tono professionale e concreto.\n"
		. "- Niente superlativi pubblicitari, niente promesse non verificabili, niente emoji.\n"
		. "- Cita la città in modo naturale, senza ripeterla in ogni frase.\n"
		. "- Non inventare dati: usa solo le informazioni fornite.\n"
		. "- Restituisci solo il testo richiesto, senza titoli né formattazione markdown.";
}

/**
 * Regole in più per i campi che escono formattati.
 *
 * Il muro di testo non lo legge nessuno, e nemmeno lo cita un assistente
 * IA: quello che serve è un testo spezzato, con i punti che contano in
 * evidenza e gli elenchi scritti come elenchi.
 */
function ai_regole_formato() {
	return "Formato della risposta:\n"
		. "- Rispondi in HTML, senza <html>, <head> o <body>.\n"
		. "- Usa solo questi tag: <p>, <h3>, <strong>, <ul>, <ol>, <li>. Nient'altro.\n"
		. "- Spezza il testo con un <h3> ogni due o tre paragrafi: il sottotitolo dice cosa si trova sotto, non è un titolo generico.\n"
		. "- Paragrafi corti, due o tre frasi. Mai un blocco di dieci righe.\n"
		. "- Metti in <strong> le tre o quattro cose che il cliente cerca davvero: il servizio, la città, il prezzo, il tempo di attesa. Parole o brevi gruppi di parole, mai frasi intere.\n"
		. "- Quando elenchi cose — cosa serve portare, cosa comprende, i passaggi — usa <ul> con <li>, non un elenco dentro il paragrafo.";
}

/** Genera il testo di approfondimento. */
function ai_corpo( $citta, $pagina ) {
	// Una pagina di servizio non rimanda a sé stessa: le altre pagine della
	// città sì, quando c'entrano, ma senza forzare la chiusura.
	$servizi = ai_pagine_servizio( $citta, $pagina['id'] );
	$migliora = ai_istruzione_migliora( $pagina['corpo'] );

	$istruzione = ( '' === $migliora
			? "Scrivi il testo di approfondimento per una pagina di servizio locale.\n\n"
			: "Migliora il testo di approfondimento di una pagina di servizio locale.\n\n" )
		. ai_contesto( $citta, $pagina ) . "\n\n"
		. ( '' === $migliora
			? "Lunghezza: 300-380 parole in tutto, divise in 3 o 4 blocchi, ognuno con il suo <h3>.\n"
				. "Spiega quando serve il servizio, come si svolge, cosa deve sapere il cliente e cosa lo distingue in questa città.\n\n"
			: $migliora . "\n\n" )
		. ( '' === ai_elenco_servizi( $servizi ) ? '' : ai_elenco_servizi( $servizi ) . "\n\n" )
		. ai_regole() . "\n\n"
		. ai_regole_formato()
		. ( '' === ai_regole_richiamo( $servizi, false ) ? '' : "\n\n" . ai_regole_richiamo( $servizi, false ) );

	// Il testo di approfondimento è lungo: serve spazio per il ragionamento
	// del modello e per le quattrocento parole richieste.
	$esito = ai_chiedi_testo( $istruzione, 'testo', 0, 16384, 'html' );
	if ( $esito['ok'] ) {
		$esito['testo'] = ai_richiamo_applica( $esito['testo'], $citta, $servizi, $pagina['titolo'], false );
	}
	return $esito;
}

/**
 * Genera i tag della pagina.
 *
 * Sono le pastiglie sotto il testo: quattro o cinque parole che dicono
 * di cosa parla la pagina. Si chiedono dentro un JSON con la forma
 * giusta, così non tornano come una frase da spezzare a indovinare.
 */
function ai_tag( $citta, $pagina, $quanti = 5 ) {
	$schema = array(
		'type'  => 'ARRAY',
		'items' => array( 'type' => 'STRING' ),
	);

	$istruzione = "Elenca {$quanti} tag per questa pagina di servizio locale.\n\n"
		. ai_contesto( $citta, $pagina ) . "\n\n"
		. "I tag sono le parole con cui un cliente cerca questo servizio: cose concrete, "
		. "non concetti. «Transponder», «Smart Key», «Cilindri europei» vanno bene; "
		. "«Qualità», «Professionalità», «Affidabilità» no.\n"
		. "Una o due parole ciascuno, con la maiuscola iniziale, senza punteggiatura.\n"
		. "Non mettere il nome della città: è già nel titolo e in mezza pagina.\n\n"
		. ai_regole();

	$esito = ai_chiedi( $istruzione, $schema );
	if ( ! $esito['ok'] ) {
		return array( 'ok' => false, 'testo' => '', 'errore' => $esito['errore'] );
	}

	$voci = json_decode( $esito['testo'], true );
	if ( ! is_array( $voci ) ) {
		return array( 'ok' => false, 'testo' => '', 'errore' => 'Risposta non interpretabile.' );
	}

	$pulite = array();
	foreach ( $voci as $v ) {
		// Un tag lungo una riga non è un tag: è una frase, e nella
		// pastiglia andrebbe a capo tre volte.
		$v = trim( ai_ripulisci( (string) $v ), " \t\n\r.,;:•-–—" );
		if ( '' === $v || mb_strlen( $v ) > 28 ) {
			continue;
		}
		$pulite[] = maiuscola( $v );
	}
	$pulite = array_slice( array_unique( $pulite ), 0, $quanti + 1 );

	if ( empty( $pulite ) ) {
		return array( 'ok' => false, 'testo' => '', 'errore' => 'Il modello non ha proposto tag utilizzabili. Riprova.' );
	}
	return array( 'ok' => true, 'testo' => implode( "\n", $pulite ), 'errore' => '' );
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
		// Le FAQ vanno in campi di testo semplice: qui i marcatori si
		// tolgono, o in pagina si leggerebbero gli asterischi.
		$pulite[] = array(
			'domanda'  => ai_ripulisci( (string) $v['domanda'] ),
			'risposta' => ai_ripulisci( (string) $v['risposta'] ),
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
	$migliora   = ai_istruzione_migliora( $pagina['intro'] );
	$istruzione = ( '' === $migliora
			? "Scrivi l'introduzione che compare sotto il titolo principale della pagina.\n\n"
			: "Migliora l'introduzione che compare sotto il titolo principale della pagina.\n\n" )
		. ai_contesto( $citta, $pagina ) . "\n\n"
		. ( '' === $migliora ? "Una o due frasi che dicano subito cosa fate e dove. Breve.\n\n" : $migliora . "\n\n" )
		. ai_regole();
	return ai_chiedi_testo( $istruzione, 'introduzione', 220 );
}

/* ---------------------------------------------------------------------------
 * Home del portale
 *
 * Questa pagina non appartiene a nessuna città: il contesto non è una
 * scheda comunale ma l'insieme delle zone in cui si lavora.
 * ------------------------------------------------------------------------- */

/** Contesto del portale nel suo insieme, da passare al modello. */
function ai_contesto_portale() {
	$imp   = impostazioni();
	$parti = array();

	$parti[] = 'Attività: ' . $imp['brand'];

	$nomi = array();
	foreach ( citta_tutte( true ) as $c ) {
		$nomi[] = $c['nome'] . ( vuoto( $c['provincia'] ) ? '' : ' (' . $c['provincia'] . ')' );
	}
	$parti[] = empty( $nomi )
		? 'Città coperte: nessuna ancora pubblicata.'
		: 'Città coperte (' . count( $nomi ) . '): ' . implode( ', ', $nomi );

	$servizi = array();
	foreach ( servizi() as $s ) {
		$servizi[] = $s['nome'];
	}
	if ( ! empty( $servizi ) ) {
		$parti[] = 'Servizi offerti: ' . implode( ', ', $servizi );
	}
	if ( ! vuoto( $imp['telefono'] ) ) {
		$parti[] = 'Telefono: ' . $imp['telefono'];
	}
	if ( ! vuoto( $imp['piva'] ) ) {
		$parti[] = 'Partita IVA: ' . $imp['piva'];
	}

	return "Contesto:\n- " . implode( "\n- ", $parti );
}

/** Regole per i testi del portale: qui non c'è una città da citare. */
function ai_regole_portale() {
	return "Regole:\n"
		. "- Scrivi in italiano, tono professionale e concreto.\n"
		. "- Niente superlativi pubblicitari, niente promesse non verificabili, niente emoji.\n"
		. "- Non inventare città, servizi o dati: usa solo quelli elencati qui sopra.\n"
		. "- Parla dell'insieme delle zone, non di una città sola.\n"
		. "- Restituisci solo il testo richiesto, senza titoli né formattazione markdown.";
}

/**
 * Titolo dell'intestazione, con la parte da evidenziare fra asterischi.
 *
 * È la sola convenzione che il modello deve rispettare, quindi gliela si
 * spiega con un esempio e si ricontrolla al ritorno.
 */
function ai_home_titolo() {
	$istruzione = "Scrivi il titolo grande della pagina d'ingresso di un portale di zone.\n\n"
		. ai_contesto_portale() . "\n\n"
		. "Da due a quattro parole in tutto. Dice dove si lavora, non cosa si fa.\n"
		. "Metti fra asterischi la parola o le due parole da far risaltare in giallo.\n"
		. "Esempi della forma richiesta: \"Dove *operiamo*\" oppure \"Le nostre *zone*\".\n\n"
		. ai_regole_portale();

	$esito = ai_chiedi_testo( $istruzione, 'titolo', 60 );
	if ( ! $esito['ok'] ) {
		return $esito;
	}

	// Senza asterischi il titolo esce tutto bianco: si evidenzia l'ultima
	// parola, che è quella su cui cade l'accento nella forma richiesta.
	if ( false === strpos( $esito['testo'], '*' ) ) {
		$parole = preg_split( '/\s+/u', trim( $esito['testo'] ) );
		$ultima = array_pop( $parole );
		if ( null !== $ultima && '' !== $ultima ) {
			$esito['testo'] = trim( implode( ' ', $parole ) . ' *' . $ultima . '*' );
		}
	}
	return $esito;
}

/** Riga sotto il titolo della home del portale. */
function ai_home_intro() {
	$istruzione = "Scrivi la riga che compare sotto il titolo nella pagina d'ingresso di un portale di zone.\n\n"
		. ai_contesto_portale() . "\n\n"
		. "Una frase sola: dice a chi arriva di scegliere la città e cosa ci troverà.\n\n"
		. ai_regole_portale();
	return ai_chiedi_testo( $istruzione, 'introduzione', 200 );
}

/** Testo sopra l'elenco delle città. */
function ai_home_testo() {
	$istruzione = "Scrivi il testo di presentazione della pagina d'ingresso di un portale di zone.\n\n"
		. ai_contesto_portale() . "\n\n"
		. "Lunghezza: 140-200 parole, in due o tre blocchi con il loro <h3>.\n"
		. "Racconta di cosa si occupa l'attività e come lavora nelle zone che copre.\n"
		. "Non elencare le città una per una: sotto c'è già il loro elenco.\n\n"
		. ai_regole_portale() . "\n\n"
		. ai_regole_formato();
	return ai_chiedi_testo( $istruzione, 'testo', 0, 8192, 'html' );
}

/** Testo sotto l'elenco delle città. */
function ai_home_sotto() {
	$istruzione = "Scrivi due righe da mettere sotto l'elenco delle città di un portale di zone.\n\n"
		. ai_contesto_portale() . "\n\n"
		. "Servono a chi non trova la propria città: invitale a chiamare lo stesso.\n\n"
		. ai_regole_portale();
	return ai_chiedi_testo( $istruzione, 'testo', 240 );
}

/** Titolo per Google della home del portale. */
function ai_home_seo_titolo() {
	$istruzione = "Scrivi il tag title della pagina d'ingresso di un portale di zone.\n\n"
		. ai_contesto_portale() . "\n\n"
		. "Fra 45 e 60 caratteri. Deve contenere il nome dell'attività e l'idea delle zone coperte.\n\n"
		. ai_regole_portale();
	return ai_chiedi_testo( $istruzione, 'titolo', 65 );
}

/** Descrizione per Google della home del portale. */
function ai_home_seo_desc() {
	$istruzione = "Scrivi la meta description della pagina d'ingresso di un portale di zone.\n\n"
		. ai_contesto_portale() . "\n\n"
		. "Fra 120 e 155 caratteri. Dice cosa si fa e dove, e invita a scegliere la città.\n\n"
		. ai_regole_portale();
	return ai_chiedi_testo( $istruzione, 'descrizione', 158 );
}

/** Riga di presentazione nel piè di pagina. */
function ai_piede_testo() {
	$istruzione = "Scrivi la riga di presentazione che sta nel piè di pagina, sotto il nome dell'attività.\n\n"
		. ai_contesto_portale() . "\n\n"
		. "Una frase breve, al massimo dodici parole. Dice il mestiere, non slogan.\n\n"
		. ai_regole_portale();
	return ai_chiedi_testo( $istruzione, 'testo', 120 );
}

/** Soggetto proposto per l'immagine della home del portale. */
function ai_soggetto_portale() {
	$servizi = array();
	foreach ( servizi() as $s ) {
		$servizi[] = mb_strtolower( $s['nome'] );
	}
	$mestiere = empty( $servizi ) ? 'il mestiere' : implode( ', ', array_slice( $servizi, 0, 3 ) );
	return 'il mestiere di ' . impostazione( 'brand', 'questa attività' ) . ' (' . $mestiere . '): '
		. 'attrezzi e banco di lavoro ordinati, vista ampia';
}

/** Immagine di sfondo per la home del portale. */
function ai_immagine_portale( $richiesta = '' ) {
	$soggetto = vuoto( $richiesta ) ? ai_soggetto_portale() : trim( $richiesta );
	return ai_disegna_immagine( $soggetto, impostazione( 'brand', '' ), 'home-portale', '' );
}

/* ---------------------------------------------------------------------------
 * Articoli del blog
 *
 * Un articolo non è una pagina di servizio: risponde a una domanda, e la
 * città è il posto dove la domanda se la fanno, non l'argomento. Per questo
 * ha istruzioni sue e non riusa quelle delle pagine.
 * ------------------------------------------------------------------------- */

/** Contesto per l'assistente quando lavora su un articolo. */
function ai_contesto_articolo( $citta, $articolo, $con_corpo = true ) {
	$parti   = array();
	$parti[] = ai_contesto( $citta );

	$dentro = array();
	if ( ! vuoto( $articolo['titolo'] ) ) {
		$dentro[] = "Titolo dell'articolo: " . $articolo['titolo'];
	}
	$categoria = categoria_per_id( $articolo['categoria'] ?? '' );
	if ( $categoria ) {
		$dentro[] = 'Categoria: ' . $categoria['nome']
			. ( vuoto( $categoria['descrizione'] ) ? '' : ' — ' . mb_substr( strip_tags( $categoria['descrizione'] ), 0, 160 ) );
	}
	if ( ! vuoto( $articolo['estratto'] ) ) {
		$dentro[] = 'Di cosa parla: ' . $articolo['estratto'];
	}
	// Quando il compito è migliorare il testo, quel testo arriva già per
	// intero più avanti: ripeterne l'inizio qui confonderebbe e basta.
	if ( $con_corpo && ! vuoto( $articolo['corpo'] ) ) {
		// Solo l'inizio: serve a capire il taglio, non a rileggersi tutto.
		$testo    = trim( preg_replace( '/\s+/u', ' ', strip_tags( (string) $articolo['corpo'] ) ) );
		$dentro[] = 'Testo già scritto (inizio): ' . mb_substr( $testo, 0, 600 );
	}
	if ( ! empty( $dentro ) ) {
		$parti[] = implode( "\n", $dentro );
	}
	return implode( "\n", $parti );
}

/** Regole comuni agli articoli. */
function ai_regole_articolo() {
	return ai_regole() . "\n"
		. "Questo è un articolo di blog, non una pagina di vendita: risponde a una domanda "
		. "concreta e aiuta chi legge anche se poi non chiama nessuno.\n"
		. "Nomina la città dove serve, non a ogni paragrafo.";
}

/** Titolo dell'articolo. */
function ai_articolo_titolo( $citta, $articolo ) {
	$spunto = vuoto( $articolo['titolo'] ) ? '' : "Titolo attuale, da migliorare: " . $articolo['titolo'] . "\n";
	$istruzione = "Scrivi il titolo di un articolo di blog per un'attività locale.\n\n"
		. ai_contesto_articolo( $citta, $articolo ) . "\n\n"
		. $spunto
		. "Massimo 70 caratteri. Deve dire di cosa parla e nominare " . $citta['nome'] . ".\n"
		. "Una domanda va bene se è la domanda che fa il cliente.\n"
		. "Niente due punti decorativi, niente «guida completa», niente «tutto quello che».\n\n"
		. ai_regole_articolo();
	return ai_chiedi_testo( $istruzione, 'titolo', 72 );
}

/** Estratto dell'articolo: le due righe che si leggono nell'elenco. */
function ai_articolo_estratto( $citta, $articolo ) {
	$migliora   = ai_istruzione_migliora( $articolo['estratto'] );
	$istruzione = ( '' === $migliora ? 'Scrivi' : 'Migliora' )
		. " l'estratto di questo articolo: le due righe che si leggono nell'elenco del blog.\n\n"
		. ai_contesto_articolo( $citta, $articolo ) . "\n\n"
		. ( '' === $migliora ? '' : $migliora . "\n\n" )
		. "Fra 140 e 200 caratteri. Dice cosa si impara leggendolo, non «in questo articolo vedremo».\n\n"
		. ai_regole_articolo();
	return ai_chiedi_testo( $istruzione, 'estratto', 205 );
}

/** Corpo dell'articolo, già formattato in HTML. */
function ai_articolo_corpo( $citta, $articolo ) {
	// Un articolo del blog esiste per portare qualcuno alla pagina del
	// servizio: il richiamo in fondo non è un di più, è il motivo.
	$servizi  = ai_pagine_servizio( $citta );
	$migliora = ai_istruzione_migliora( $articolo['corpo'] );

	$istruzione = ( '' === $migliora
			? "Scrivi il testo di un articolo di blog per un'attività locale.\n\n"
			: "Migliora il testo di un articolo di blog per un'attività locale.\n\n" )
		. ai_contesto_articolo( $citta, $articolo, '' === $migliora ) . "\n\n"
		. ( '' === $migliora
			? "Lunghezza: 500-650 parole, divise in 4 o 5 blocchi, ognuno con il suo <h3>.\n"
				. "Il primo blocco risponde subito alla domanda del titolo: chi legge non deve "
				. "scorrere per sapere la risposta.\n"
				. "Poi: quando succede, cosa si può fare da sé, quando serve un tecnico, "
				. "cosa aspettarsi in termini di tempi e di costi.\n"
				. "Se ci sono cifre o tempi, dilli come intervalli e di' che dipendono dal caso: "
				. "non inventare prezzi precisi.\n"
			: $migliora . "\n\n" )
		. ( '' === ai_elenco_servizi( $servizi ) ? '' : "\n" . ai_elenco_servizi( $servizi ) . "\n\n" )
		. ai_regole_articolo() . "\n\n"
		. ai_regole_formato()
		. ( '' === ai_regole_richiamo( $servizi, true )
			? "\n\nChiudi con un blocco che dice cosa fare a " . $citta['nome'] . ", senza slogan."
			: "\n\n" . ai_regole_richiamo( $servizi, true ) );

	$esito = ai_chiedi_testo( $istruzione, 'testo', 0, 20480, 'html' );
	if ( $esito['ok'] ) {
		$esito['testo'] = ai_richiamo_applica(
			$esito['testo'],
			$citta,
			$servizi,
			$articolo['titolo'] . ' ' . $articolo['estratto'],
			true
		);
	}
	return $esito;
}

/** Tag dell'articolo. */
function ai_articolo_tag( $citta, $articolo, $quanti = 5 ) {
	$schema = array( 'type' => 'ARRAY', 'items' => array( 'type' => 'STRING' ) );

	$istruzione = "Elenca {$quanti} tag per questo articolo di blog.\n\n"
		. ai_contesto_articolo( $citta, $articolo ) . "\n\n"
		. "I tag sono le cose concrete di cui parla l'articolo: oggetti, pezzi, situazioni. "
		. "«Transponder», «Cilindro europeo», «Chiave spezzata» vanno bene; "
		. "«Sicurezza», «Professionalità», «Consigli» no.\n"
		. "Una o due parole ciascuno, con la maiuscola iniziale, senza punteggiatura.\n"
		. "Non mettere il nome della città.\n\n"
		. ai_regole_articolo();

	$esito = ai_chiedi( $istruzione, $schema );
	if ( ! $esito['ok'] ) {
		return array( 'ok' => false, 'testo' => '', 'errore' => $esito['errore'] );
	}
	$voci = json_decode( $esito['testo'], true );
	if ( ! is_array( $voci ) ) {
		return array( 'ok' => false, 'testo' => '', 'errore' => 'Risposta non interpretabile.' );
	}
	$pulite = array();
	foreach ( $voci as $v ) {
		$v = trim( ai_ripulisci( (string) $v ), " \t\n\r.,;:•-–—" );
		if ( '' === $v || mb_strlen( $v ) > 28 ) {
			continue;
		}
		$pulite[] = maiuscola( $v );
	}
	$pulite = array_slice( array_unique( $pulite ), 0, $quanti + 1 );
	if ( empty( $pulite ) ) {
		return array( 'ok' => false, 'testo' => '', 'errore' => 'Il modello non ha proposto tag utilizzabili. Riprova.' );
	}
	return array( 'ok' => true, 'testo' => implode( "\n", $pulite ), 'errore' => '' );
}

/** Titolo per Google. */
function ai_articolo_seo_titolo( $citta, $articolo ) {
	$istruzione = "Scrivi il titolo per Google di questo articolo (il tag title).\n\n"
		. ai_contesto_articolo( $citta, $articolo ) . "\n\n"
		. "Fra 45 e 60 caratteri, contando gli spazi. Nomina " . $citta['nome'] . ".\n"
		. "Non ripetere alla lettera il titolo dell'articolo: qui conta la parola che si cerca.\n\n"
		. ai_regole_articolo();
	return ai_chiedi_testo( $istruzione, 'titolo', 62 );
}

/** Descrizione per Google. */
function ai_articolo_seo_desc( $citta, $articolo ) {
	$istruzione = "Scrivi la descrizione per Google di questo articolo (la meta description).\n\n"
		. ai_contesto_articolo( $citta, $articolo ) . "\n\n"
		. "Fra 120 e 155 caratteri. Dice cosa si trova nell'articolo e nomina " . $citta['nome'] . ".\n\n"
		. ai_regole_articolo();
	return ai_chiedi_testo( $istruzione, 'descrizione', 158 );
}

/** Immagine dell'articolo. */
function ai_articolo_immagine( $citta, $articolo, $richiesta = '' ) {
	if ( vuoto( impostazione( 'gemini_key', '' ) ) ) {
		return array( 'ok' => false, 'file' => '', 'alt' => '', 'errore' => 'Chiave Gemini non impostata.' );
	}
	if ( ! function_exists( 'curl_init' ) ) {
		return array( 'ok' => false, 'file' => '', 'alt' => '', 'errore' => 'Estensione cURL non disponibile sul server.' );
	}

	$titolo   = trim( (string) $articolo['titolo'] );
	$soggetto = vuoto( $richiesta )
		? ( '' === $titolo
			? 'il mestiere di ' . impostazione( 'brand', 'questa attività' ) . ', attrezzi e banco di lavoro ordinati'
			: 'una scena che illustra "' . $titolo . '": dettaglio ravvicinato, mani al lavoro, attrezzi veri' )
		: trim( $richiesta );

	$base = vuoto( $articolo['slug'] ) ? 'articolo' : $articolo['slug'];
	return ai_disegna_immagine(
		$soggetto,
		'' === $titolo ? impostazione( 'brand', '' ) . ' a ' . $citta['nome'] : $titolo,
		$base . '-' . $citta['slug'],
		$citta['nome']
	);
}

/** Descrizione di una categoria: il testo che sta in cima all'archivio. */
function ai_categoria_testo( $categoria, $quanti_articoli = 0, $esempi = array() ) {
	$parti   = array();
	$parti[] = ai_contesto_portale();
	$parti[] = 'Categoria: ' . $categoria['nome'];
	if ( $quanti_articoli > 0 ) {
		$parti[] = 'Articoli che contiene: ' . $quanti_articoli;
	}
	if ( ! empty( $esempi ) ) {
		$parti[] = "Titoli di esempio:\n- " . implode( "\n- ", array_slice( $esempi, 0, 8 ) );
	}

	$istruzione = "Scrivi il testo di presentazione della categoria di un blog, quello che si legge "
		. "in cima all'archivio, sopra l'elenco degli articoli.\n\n"
		. implode( "\n", $parti ) . "\n\n"
		. "Due paragrafi, 90-140 parole in tutto. Dice cosa si trova in questa categoria e "
		. "a chi serve. Non elencare i titoli: quelli si vedono già sotto.\n"
		. "Non nominare una città in particolare: la stessa descrizione si legge in tutte.\n\n"
		. ai_regole_portale() . "\n\n"
		. ai_regole_formato();
	return ai_chiedi_testo( $istruzione, 'testo', 0, 8192, 'html' );
}
