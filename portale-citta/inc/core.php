<?php
/**
 * Portale Città — nucleo.
 * Funzioni di base condivise da front-end e amministrazione.
 */

if ( ! defined( 'PC_AVVIO' ) ) {
	define( 'PC_AVVIO', true );
}

define( 'PC_VERSIONE', '1.1.0' );
// Segnaposto salvato fra le sezioni di una pagina: dice che l'ordine è
// stato deciso a mano, e non va più corretto dai valori di una volta.
define( 'PC_ORDINE_DECISO', '--ordine--' );
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
		// «Call to undefined function» dopo un aggiornamento vuol dire
		// quasi sempre file caricati a metà: chi legge deve sapere cosa
		// fare, non solo come si chiama la funzione che manca.
		if ( preg_match( '/undefined (function|method|constant)/i', $errore->getMessage() ) ) {
			echo '<p style="margin:0 0 10px">Sembra un aggiornamento caricato a metà: ricarica <strong>tutta</strong> la cartella del portale, in particolare <code>inc/</code>, <code>admin/</code> e <code>tema/</code>, poi riprova. I tuoi dati non sono stati toccati.</p>';
		}
		echo '<p style="margin:0;font-size:13px;color:#8c1d18">' . htmlspecialchars( basename( $errore->getFile() ) . ':' . $errore->getLine(), ENT_QUOTES, 'UTF-8' ) . '</p>';
	} else {
		echo '<p style="margin:0">La pagina non è al momento disponibile. Riprova fra poco.</p>';
	}
	echo '</div>';
	exit;
} );

require_once __DIR__ . '/archivio.php';
require_once __DIR__ . '/utenti.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/generatore.php';
require_once __DIR__ . '/importatore.php';
require_once __DIR__ . '/modulo.php';

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

/**
 * Testo di una riga con dentro, se serve, un collegamento.
 *
 * Si scrive `[etichetta](indirizzo)` e solo quella parte diventa
 * cliccabile; `{anno}` diventa l'anno corrente, così una riga di
 * copyright non va riscritta a gennaio.
 *
 * Tutto il resto passa da htmlspecialchars prima che il collegamento
 * venga costruito: un tag scritto nel campo resta testo, e un indirizzo
 * javascript: viene scartato da e_url().
 */
function url_completa( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	// Già completo, oppure è un indirizzo interno o una mail: si lascia.
	if ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) || '/' === $url[0] || '#' === $url[0] ) {
		return $url;
	}
	// Scritto come lo si detta a voce — «maxdigitalinnovation.it» — senza
	// https:// davanti. Così com'è il browser lo prenderebbe per una
	// sottopagina del portale e finirebbe su una pagina che non esiste.
	if ( preg_match( '#^[a-z0-9.-]+\.[a-z]{2,}([/?\#].*)?$#i', $url ) ) {
		return 'https://' . $url;
	}
	return $url;
}

function testo_con_link( $testo ) {
	$testo = str_replace( '{anno}', date( 'Y' ), (string) $testo );
	$fuori = e( $testo );

	// Si lavora sul testo già reso innocuo: le parentesi non sono fra i
	// caratteri che htmlspecialchars tocca, quindi il segno si ritrova.
	// Lo spazio fra ] e ( è tollerato: chi scrive a mano lo mette.
	return preg_replace_callback(
		'/\[([^\]]+)\]\s*\(([^)\s]+)\)/',
		function ( $pezzi ) {
			$grezzo = url_completa( html_entity_decode( $pezzi[2], ENT_QUOTES, 'UTF-8' ) );
			$url    = e_url( $grezzo );
			if ( '' === $url ) {
				// Indirizzo rifiutato: si rimette la riga com'era scritta.
				// Vedere le parentesi quadre dice subito che il link non
				// ha preso, invece di lasciare un pezzo di testo monco.
				return $pezzi[0];
			}
			$esterno = preg_match( '#^https?://#i', $grezzo ) ? ' target="_blank" rel="noopener"' : '';
			return '<a href="' . $url . '"' . $esterno . '>' . $pezzi[1] . '</a>';
		},
		$fuori
	);
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

/**
 * Un recapito della città, con ripiego sull'impostazione generale.
 * Serve sia al tema sia alle schermate del pannello.
 */
function contatto( $citta, $campo ) {
	if ( isset( $citta[ $campo ] ) && ! vuoto( $citta[ $campo ] ) ) {
		return $citta[ $campo ];
	}
	return impostazione( $campo, '' );
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

/**
 * True se il testo porta già dentro dell'HTML di blocco.
 *
 * Serve a distinguere i contenuti scritti con l'editor da quelli scritti
 * prima, quando il campo era testo semplice: quelli vanno ancora
 * impaginati da paragrafi(), questi no.
 */
function testo_ha_html( $testo ) {
	return (bool) preg_match( '#<(p|h[1-6]|ul|ol|li|blockquote|table|hr)\b#i', (string) $testo );
}

/**
 * Ripulisce l'HTML scritto nell'editor.
 *
 * È lo stesso filtro degli articoli importati da WordPress: restano i tag
 * del testo, spariscono script, stili, moduli e tutti gli attributi
 * tranne href sui collegamenti. Si applica al salvataggio, ed è
 * idempotente, così si può ripassare anche in lettura.
 */
function corpo_pulisci( $html ) {
	$html = trim( (string) $html );
	if ( '' === $html ) {
		return '';
	}
	return import_pulisci_corpo( $html );
}

/**
 * Le icone che si possono dare a un tipo di servizio.
 *
 * Sono disegnate qui dentro, non caricate da fuori: nessuna richiesta a
 * un altro sito, nessun font da scaricare, e prendono il colore del
 * testo che le circonda perché usano currentColor.
 *
 * @return array chiave => array( nome leggibile, tracciato SVG )
 */
function icone_disponibili() {
	return array(
		'chiave' => array( 'Chiave', '<circle cx="8" cy="8" r="4.2"/><path d="M11 11l8.5 8.5M16.5 16.5l2-2M19 14l1.8 1.8"/>' ),
		'chiave-auto' => array( 'Chiave auto', '<rect x="3" y="7" width="8" height="10" rx="2"/><path d="M11 12h9M17 12v3.5M20 12v2.5M6.5 10.5v3"/>' ),
		'telecomando' => array( 'Telecomando', '<rect x="7" y="3" width="10" height="18" rx="3"/><circle cx="10.5" cy="8" r="1.1"/><circle cx="13.5" cy="8" r="1.1"/><circle cx="10.5" cy="12" r="1.1"/><circle cx="13.5" cy="12" r="1.1"/><path d="M10 16.5h4"/>' ),
		'lucchetto' => array( 'Lucchetto', '<rect x="4.5" y="10" width="15" height="10.5" rx="2.5"/><path d="M8 10V7a4 4 0 018 0v3"/><circle cx="12" cy="15.2" r="1.4"/>' ),
		'serratura' => array( 'Serratura e cilindro', '<circle cx="12" cy="9" r="5.5"/><path d="M12 12.5v6M9.5 18.5h5"/><circle cx="12" cy="9" r="1.6"/>' ),
		'porta' => array( 'Porta', '<rect x="5.5" y="3" width="13" height="18" rx="1.5"/><circle cx="15" cy="12" r="1.2"/>' ),
		'casa' => array( 'Casa', '<path d="M4 10.5L12 4l8 6.5"/><path d="M6 10v10h12V10"/><path d="M10 20v-5.5h4V20"/>' ),
		'auto' => array( 'Auto', '<path d="M4 15.5h16M5.5 15.5l1.6-5a2 2 0 011.9-1.4h6a2 2 0 011.9 1.4l1.6 5"/><rect x="3.5" y="15.5" width="17" height="4" rx="1.5"/><path d="M7 19.5v1.2M17 19.5v1.2"/>' ),
		'moto' => array( 'Moto', '<circle cx="5.5" cy="16.5" r="3.5"/><circle cx="18.5" cy="16.5" r="3.5"/><path d="M5.5 16.5l4-6h5l4 6M9 10.5h6M13.5 7.5h3"/>' ),
		'cancello' => array( 'Cancello', '<path d="M3 20V8l4.5-3L12 8l4.5-3L21 8v12"/><path d="M3 20h18M7.5 20V6.5M12 20V8M16.5 20V6.5M3 13h18"/>' ),
		'cassaforte' => array( 'Cassaforte', '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="11" cy="12" r="3.8"/><path d="M11 8.2v1.4M11 14.4v1.4M7.2 12h1.4M13.4 12h1.4M17.5 9v6"/>' ),
		'attrezzi' => array( 'Attrezzi', '<path d="M14.5 3.5a4.5 4.5 0 00-5.4 5.8L3.5 14.9a2 2 0 102.8 2.8l5.6-5.6a4.5 4.5 0 005.8-5.4l-2.9 2.9-2.4-.6-.6-2.4z"/><path d="M14 14l5.5 5.5"/>' ),
		'scudo' => array( 'Sicurezza', '<path d="M12 3l7.5 3v6c0 4.3-3 7.7-7.5 9.2C7.5 19.7 4.5 16.3 4.5 12V6z"/><path d="M9 12l2.2 2.2L15.5 10"/>' ),
		'orologio' => array( 'Orari e urgenze', '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5.3l3.4 2"/>' ),
		'telefono' => array( 'Telefono', '<path d="M6.5 3.5h3l1.5 4-2 1.5a12 12 0 006 6l1.5-2 4 1.5v3a2 2 0 01-2.2 2A16.8 16.8 0 014.5 5.7 2 2 0 016.5 3.5z"/>' ),
		'mappa' => array( 'Mappa e zone', '<path d="M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>' ),
	);
}

/**
 * L'icona di un tipo di servizio, pronta da stampare.
 *
 * Restituisce stringa vuota se l'icona non è stata scelta o non esiste
 * più: la card esce senza, non con un buco.
 */
function icona_servizio( $chiave, $lato = 28 ) {
	$icone = icone_disponibili();
	$chiave = (string) $chiave;
	if ( '' === $chiave || ! isset( $icone[ $chiave ] ) ) {
		return '';
	}
	return '<svg class="glp-icona" viewBox="0 0 24 24" width="' . (int) $lato . '" height="' . (int) $lato . '"'
		. ' fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"'
		. ' aria-hidden="true" focusable="false">' . $icone[ $chiave ][1] . '</svg>';
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

/**
 * La cartella da cui il portale sta davvero girando.
 *
 * «/zone» se i file stanno in quella sottocartella, «» se stanno nella
 * radice del dominio. Non si indovina: la dice il server.
 */
function cartella_reale() {
	$dir = rtrim( dirname( (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) ), '/\\' );
	return '.' === $dir || '\\' === $dir ? '' : $dir;
}

/**
 * True se l'indirizzo scritto nelle impostazioni non porta dove i file
 * stanno davvero.
 *
 * Si confronta solo la cartella, non il dominio: fra www e non-www, o
 * dietro a un proxy, l'host può legittimamente non combaciare, mentre
 * una cartella sbagliata rompe ogni collegamento del portale.
 *
 * @return string La cartella scritta nelle impostazioni, se è diversa da
 *                quella vera. Stringa vuota se va tutto bene o se non si
 *                può sapere.
 */
function cartella_incoerente() {
	// Chi nasconde la cartella apposta ha una differenza voluta: qui non
	// c'è niente da segnalare, e un avviso che si sa già sbagliato
	// insegna solo a non leggere gli avvisi.
	if ( '1' === (string) impostazione( 'cartella_nascosta', '0' ) ) {
		return '';
	}
	$url = trim( (string) impostazione( 'sito_url', '' ) );
	if ( '' === $url || '' === (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) ) {
		return '';
	}
	$scritta = rtrim( (string) parse_url( $url, PHP_URL_PATH ), '/' );
	$vera    = cartella_reale();
	if ( $scritta === $vera ) {
		return '';
	}
	// Vuota vuol dire radice del dominio: si scrive così, o il messaggio
	// direbbe «la cartella “”».
	return '' === $scritta ? '/' : $scritta;
}

/** URL pubblico di una città. */
function url_citta( $citta ) {
	return base_url() . '/' . $citta['slug'] . '/';
}

/** URL pubblico di una pagina. */
function url_pagina( $citta, $pagina ) {
	// Una pagina principale, o una appena creata che non ha ancora
	// uno slug, vale l'indirizzo della città: mai "/citta//".
	if ( 'home' === $pagina['tipo'] || vuoto( $pagina['slug'] ) ) {
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

/**
 * Lo shortcode che mostra questa pagina dentro WordPress.
 *
 * La pagina principale di una città non ha bisogno dello slug: senza
 * l'attributo «pagina» il plugin prende quella.
 */
function shortcode_pagina( $citta, $pagina, $sezione = '' ) {
	$parti = 'citta="' . $citta['slug'] . '"';
	if ( 'home' !== $pagina['tipo'] ) {
		$parti .= ' pagina="' . $pagina['slug'] . '"';
	}
	if ( '' !== (string) $sezione ) {
		$parti .= ' sezione="' . $sezione . '"';
	}
	return '[portale_citta ' . $parti . ']';
}

/**
 * Dove porta il logo in alto, su ogni pagina del portale.
 *
 * Se l'indirizzo del sito principale è scritto nelle impostazioni si usa
 * quello. Altrimenti si ricava dal portale stesso: quando sta in una
 * sottocartella (`sito.it/zone`) il sito è quello che la contiene, e il
 * logo deve riportare là, non alla radice della sottocartella. Con il
 * portale installato sulla radice del dominio i due coincidono.
 */
function url_sito_principale() {
	$scelto = impostazione( 'sito_principale', '' );
	if ( ! vuoto( $scelto ) ) {
		return $scelto;
	}

	$base  = base_url();
	$parti = parse_url( $base );
	if ( empty( $parti['scheme'] ) || empty( $parti['host'] ) ) {
		return $base . '/';
	}
	$porta = empty( $parti['port'] ) ? '' : ':' . $parti['port'];
	return $parti['scheme'] . '://' . $parti['host'] . $porta . '/';
}

/** Lo shortcode della home del portale: l'elenco delle zone. */
function shortcode_home( $sezione = '' ) {
	return '' === (string) $sezione
		? '[portale_citta]'
		: '[portale_citta sezione="' . $sezione . '"]';
}

/** URL pubblico di un articolo del blog. */
function url_articolo( $citta, $articolo, $pagina_blog = null ) {
	if ( null === $pagina_blog ) {
		$pagina_blog = pagina_blog( $citta['id'] );
	}
	$base = null === $pagina_blog ? 'blog' : $pagina_blog['slug'];
	return base_url() . '/' . $citta['slug'] . '/' . $base . '/' . $articolo['slug'] . '/';
}
