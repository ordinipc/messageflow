<?php
/** Funzioni usate dai modelli del tema. */

defined( 'PC_AVVIO' ) || exit;

/** Apre una sezione con etichetta e titolo. */
function sezione_apri( $chiave, $titolo, $etichetta = '' ) {
	$html  = '<section class="glp-section glp-section--' . e( $chiave ) . ' glp-reveal" id="' . e( $chiave ) . '">';
	if ( '' !== $etichetta ) {
		$html .= '<p class="glp-section__label">' . e( $etichetta ) . '</p>';
	}
	$html .= '<h2 class="glp-section__title">' . e( $titolo ) . '</h2>';
	return $html;
}

/** H1 con la città evidenziata. */
function titolo_hero( $citta, $pagina ) {
	$h1 = seo_h1( $citta, $pagina );
	$posizione = mb_strripos( $h1, $citta['nome'] );
	if ( false === $posizione ) {
		return e( $h1 );
	}
	$prima = mb_substr( $h1, 0, $posizione );
	$dopo  = mb_substr( $h1, $posizione + mb_strlen( $citta['nome'] ) );
	return e( $prima ) . '<span>' . e( $citta['nome'] ) . '</span>' . e( $dopo );
}

/** Voci del menu della città. */
function menu_citta( $citta, $escludi_id = '' ) {
	$voci = array();
	foreach ( pagine_di_citta( $citta['id'], ! connesso() ) as $p ) {
		if ( ! (int) $p['menu_mostra'] || 'home' === $p['tipo'] ) {
			continue;
		}
		$voci[] = array(
			'nome'    => $p['titolo'],
			'url'     => url_pagina( $citta, $p ),
			'attiva'  => $p['id'] === $escludi_id,
		);
	}
	return $voci;
}

/**
 * Pagine servizio della città: servono all'indice nell'intestazione e
 * alla pagina che elenca i servizi. Portano anche una riga di testo e il
 * prezzo, così le card dicono qualcosa invece del solo titolo.
 */
function servizi_citta( $citta ) {
	$voci = array();
	foreach ( pagine_di_citta( $citta['id'], ! connesso() ) as $p ) {
		if ( 'servizio' !== $p['tipo'] ) {
			continue;
		}

		$testo = trim( strip_tags( (string) $p['intro'] ) );
		if ( '' === $testo ) {
			$testo = trim( strip_tags( (string) $p['seo_desc'] ) );
		}
		if ( '' === $testo ) {
			$testo = trim( strip_tags( (string) $p['corpo'] ) );
		}
		$testo = preg_replace( '/\s+/u', ' ', $testo );
		if ( mb_strlen( $testo ) > 130 ) {
			$testo = mb_substr( $testo, 0, 127 ) . '…';
		}

		$prezzo = '';
		if ( ! vuoto( $p['prezzo_da'] ) ) {
			$prezzo = 'da ' . $p['prezzo_da'] . ' €';
		}

		$voci[] = array(
			'nome'   => $p['titolo'],
			'url'    => url_pagina( $citta, $p ),
			'testo'  => $testo,
			'prezzo' => $prezzo,
		);
	}
	return $voci;
}

/** Le altre città pubblicate. */
function altre_citta( $escludi_id = '' ) {
	$voci = array();
	foreach ( citta_tutte( true ) as $c ) {
		if ( $c['id'] === $escludi_id ) {
			continue;
		}
		$voci[] = array( 'nome' => $c['nome'], 'url' => url_citta( $c ) );
	}
	return $voci;
}

/** Link WhatsApp precompilato. */
function url_whatsapp( $numero, $testo = '' ) {
	$numero = preg_replace( '/[^0-9]/', '', (string) $numero );
	if ( '' === $numero ) {
		return '';
	}
	$url = 'https://wa.me/' . $numero;
	if ( '' !== $testo ) {
		$url .= '?text=' . rawurlencode( $testo );
	}
	return $url;
}

/** Variabili CSS dalle impostazioni. */
function css_variabili() {
	$imp = impostazioni();
	return ':root{'
		. '--glp-accent:' . e( $imp['colore_accento'] ) . ';'
		. '--glp-dark:' . e( $imp['colore_scuro'] ) . ';'
		. '--glp-text:' . e( $imp['colore_testo'] ) . ';'
		. '--glp-soft:' . e( $imp['colore_chiaro'] ) . ';'
		. '--glp-radius:' . e( $imp['raggio'] ) . ';'
		// Header, contenuto e piè di pagina misurano tutti su questa.
		. '--glp-wrap:' . e( larghezza_contenuto() ) . ';'
		. '--glp-logo:' . e( altezza_logo() ) . 'px;'
		. '}';
}

/** Classi del <body>: stile delle sezioni, effetti, stile grafico. */
function classi_corpo( $extra = '' ) {
	$imp    = impostazioni();
	$classi = array( 'glp-standalone' );
	if ( '' !== trim( (string) $extra ) ) {
		$classi[] = trim( (string) $extra );
	}
	if ( '1' === (string) $imp['effetti'] ) {
		$classi[] = 'glp-effetti';
	}
	$classi[] = 'card' === $imp['stile_sezioni'] ? 'glp-stile-card' : 'glp-stile-elenco';
	$classi[] = 'classico' === $imp['stile_tema'] ? 'glp-tema-classico' : 'glp-tema-vetrina';
	return implode( ' ', $classi );
}

/** True se è attivo lo stile "vetrina". */
function tema_vetrina() {
	return 'classico' !== impostazione( 'stile_tema', 'vetrina' );
}

/**
 * Icona di un social, disegnata a mano.
 *
 * Niente font di icone e niente richieste a server di altri: sono cinque
 * tracciati, pesano meno di un carattere scaricato da fuori.
 */
function icona_social( $chiave ) {
	$tracciati = array(
		'facebook'  => '<path d="M14 8.5h2.2V5.6c-.4-.05-1.7-.17-3.2-.17-3.2 0-5.3 1.9-5.3 5.4V13H5v3.3h2.7V24h3.3v-7.7h2.7l.4-3.3h-3.1v-2c0-1 .3-1.5 1.9-1.5z"/>',
		'instagram' => '<path d="M14.5 4h-5C6.5 4 4 6.5 4 9.5v5C4 17.5 6.5 20 9.5 20h5c3 0 5.5-2.5 5.5-5.5v-5C20 6.5 17.5 4 14.5 4zm3.6 10.5c0 2-1.6 3.6-3.6 3.6h-5c-2 0-3.6-1.6-3.6-3.6v-5c0-2 1.6-3.6 3.6-3.6h5c2 0 3.6 1.6 3.6 3.6z"/><path d="M12 8a4 4 0 100 8 4 4 0 000-8zm0 6.3a2.3 2.3 0 110-4.6 2.3 2.3 0 010 4.6z"/><circle cx="16.3" cy="7.7" r="1"/>',
		'x'         => '<path d="M17.5 4h2.8l-6.1 7 7.2 9.5h-5.6l-4.4-5.8-5.1 5.8H3.5l6.5-7.5L3.1 4h5.8l4 5.3zm-1 14.8h1.6L8.1 5.6H6.4z"/>',
		'youtube'   => '<path d="M21.6 8.2s-.2-1.4-.8-2c-.7-.8-1.5-.8-1.9-.8C16.2 5.2 12 5.2 12 5.2s-4.2 0-6.9.2c-.4 0-1.2 0-1.9.8-.6.6-.8 2-.8 2S2.2 9.8 2.2 11.4v1.5c0 1.6.2 3.2.2 3.2s.2 1.4.8 2c.7.8 1.7.7 2.1.8 1.6.2 6.7.2 6.7.2s4.2 0 6.9-.2c.4 0 1.2 0 1.9-.8.6-.6.8-2 .8-2s.2-1.6.2-3.2v-1.5c0-1.6-.2-3.2-.2-3.2zM10 14.9V9.6l5.2 2.7z"/>',
		'tiktok'    => '<path d="M16.6 3h-3v13.2a2.5 2.5 0 11-2.5-2.5c.3 0 .5 0 .8.1v-3a5.5 5.5 0 102.7 5.3V9.6a6.3 6.3 0 004 1.4V8a3.6 3.6 0 01-2-5z"/>',
		'linkedin'  => '<path d="M6.9 20H3.6V9.3h3.3zM5.2 7.9a1.9 1.9 0 110-3.8 1.9 1.9 0 010 3.8zM20 20h-3.3v-5.2c0-1.2 0-2.8-1.7-2.8s-2 1.3-2 2.7V20H9.7V9.3H13v1.5a3.6 3.6 0 013.2-1.8c3.4 0 4 2.3 4 5.2z"/>',
		'whatsapp'  => '<path d="M12 2a10 10 0 00-8.6 15l-1.3 4.8 5-1.3A10 10 0 1012 2zm0 18.2a8.2 8.2 0 01-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1112 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.6.8-.8 1-.3.2-.6 0a6.7 6.7 0 01-3.3-2.9c-.2-.4.2-.4.6-1.2.1-.2 0-.4 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 00-.7.3 3 3 0 00-1 2.2 5.3 5.3 0 001.1 2.8 12 12 0 004.6 4 5 5 0 002.3.4 2.7 2.7 0 001.8-1.3 2.2 2.2 0 00.2-1.3z"/>',
	);
	if ( ! isset( $tracciati[ $chiave ] ) ) {
		return '';
	}
	return '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false">'
		. $tracciati[ $chiave ] . '</svg>';
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

/** Versione degli asset per forzare l'aggiornamento della cache. */
function versione_asset( $file ) {
	$percorso = PC_RADICE . '/tema/' . $file;
	return is_file( $percorso ) ? (string) filemtime( $percorso ) : PC_VERSIONE;
}

/** Data in italiano: 2026-07-08 → 8 luglio 2026. */
function data_italiana( $iso ) {
	$iso = trim( (string) $iso );
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m ) ) {
		return $iso;
	}
	$mesi = array( 1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
		'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre' );
	$mese = (int) $m[2];
	return (int) $m[3] . ' ' . ( isset( $mesi[ $mese ] ) ? $mesi[ $mese ] : '' ) . ' ' . $m[1];
}
