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
		. '}';
}

/** Classi del <body>: stile delle sezioni, effetti, stile grafico. */
function classi_corpo() {
	$imp    = impostazioni();
	$classi = array( 'glp-standalone' );
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
