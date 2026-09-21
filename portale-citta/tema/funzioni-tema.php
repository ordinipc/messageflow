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

/** Telefono della città, con ripiego sull'impostazione globale. */
function contatto( $citta, $campo ) {
	if ( ! vuoto( $citta[ $campo ] ) ) {
		return $citta[ $campo ];
	}
	return impostazione( $campo, '' );
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
		. '}';
}

/** Versione degli asset per forzare l'aggiornamento della cache. */
function versione_asset( $file ) {
	$percorso = PC_RADICE . '/tema/' . $file;
	return is_file( $percorso ) ? (string) filemtime( $percorso ) : PC_VERSIONE;
}
