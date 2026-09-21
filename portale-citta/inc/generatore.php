<?php
/**
 * Generatore di pagine: crea le pagine di una città partendo dai modelli
 * standard e dal catalogo dei tipi di servizio.
 *
 * Serve sia alla creazione di una città sia dopo, quando si aggiunge un
 * tipo di servizio e le città esistenti devono poterlo recuperare.
 */

defined( 'PC_AVVIO' ) || exit;

/** Modelli delle pagine standard che Google si aspetta. */
function pagine_base() {
	return array(
		'home'               => array( 'Principale', '', 'home', 0, 'La pagina raggiungibile da /citta/.' ),
		'chi-siamo'          => array( 'Chi siamo', 'chi-siamo', 'fissa', 20, 'Chi siete, da quanto lavorate, perché fidarsi.' ),
		'servizi'            => array( 'Servizi', 'servizi', 'servizi', 30, 'Elenca da sola tutte le pagine servizio della città.' ),
		'contatti'           => array( 'Contatti', 'contatti', 'fissa', 40, 'Telefono, indirizzo, orari e mappa.' ),
		'domande-frequenti'  => array( 'Domande frequenti', 'domande-frequenti', 'fissa', 50, 'Le FAQ della città.' ),
		'zone-servite'       => array( 'Zone servite', 'zone-servite', 'fissa', 60, 'Quartieri e comuni coperti.' ),
		'recensioni'         => array( 'Recensioni', 'recensioni', 'fissa', 70, 'Cosa dicono i clienti.' ),
	);
}

function genera_pagina_base( $citta, $chiave ) {
	$modelli = pagine_base();
	if ( ! isset( $modelli[ $chiave ] ) ) {
		return false;
	}
	list( $titolo, $slug, $tipo, $ordine ) = $modelli[ $chiave ];
	if ( 'home' === $tipo && pagina_home( $citta['id'] ) ) {
		return false;
	}
	$pagina             = pagina_predefinita();
	$pagina['id']       = nuovo_id();
	$pagina['citta_id'] = $citta['id'];
	$pagina['tipo']     = $tipo;
	$pagina['titolo']   = 'home' === $tipo ? $citta['nome'] : $titolo;
	$pagina['slug']     = 'home' === $tipo ? 'home' : pagina_slug_libero( $slug, $citta['id'] );
	$pagina['menu_ordine'] = $ordine;
	$pagina['menu_mostra'] = 'home' === $tipo ? 0 : 1;
	if ( 'home' === $tipo ) {
		$pagina['h1'] = 'Servizi a ' . $citta['nome'];
	}
	return pagina_salva( $pagina );
}

function genera_pagina_servizio( $citta, $servizio_id ) {
	$s = servizio( $servizio_id );
	if ( ! $s ) {
		return false;
	}
	$pagina                = pagina_predefinita();
	$pagina['id']          = nuovo_id();
	$pagina['citta_id']    = $citta['id'];
	$pagina['servizio_id'] = $s['id'];
	$pagina['tipo']        = 'servizio';
	$pagina['titolo']      = $s['nome'];
	$pagina['slug']        = pagina_slug_libero( $s['slug'], $citta['id'] );
	$pagina['menu_ordine'] = 10;
	// Fuori dal menu: con sei o sette servizi la barra diventa illeggibile.
	// I servizi si raggiungono dall'indice nell'intestazione e dalla pagina
	// "Elenco servizi". Si può sempre rimetterli dall'editor della pagina.
	$pagina['menu_mostra'] = 0;
	return pagina_salva( $pagina );
}


/**
 * Tipi di servizio del catalogo che in questa città non hanno ancora
 * una pagina. Sono quelli che l'elenco dei servizi non può mostrare.
 */
function servizi_senza_pagina( $citta_id ) {
	$usati = array();
	foreach ( pagine_di_citta( $citta_id ) as $p ) {
		if ( ! vuoto( $p['servizio_id'] ) ) {
			$usati[] = $p['servizio_id'];
		}
		// Anche una pagina servizio creata a mano con lo stesso slug conta.
		$usati[] = 'slug:' . $p['slug'];
	}

	$mancanti = array();
	foreach ( servizi() as $s ) {
		if ( in_array( $s['id'], $usati, true ) || in_array( 'slug:' . $s['slug'], $usati, true ) ) {
			continue;
		}
		$mancanti[] = $s;
	}
	return $mancanti;
}
