<?php
/** Dati strutturati JSON-LD: LocalBusiness, Service, FAQPage, BreadcrumbList. */

defined( 'PC_AVVIO' ) || exit;

/** Stampa un blocco <script type="application/ld+json">. */
function json_ld( $dati ) {
	if ( empty( $dati ) ) {
		return '';
	}
	// JSON_HEX_TAG e compagni trasformano < > & ' " in sequenze \u00XX:
	// così nessun contenuto può chiudere il tag <script> e iniettare codice.
	$json = json_encode(
		$dati,
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		| JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
	);
	if ( false === $json ) {
		return '';
	}
	return '<script type="application/ld+json">' . $json . '</script>';
}

/** Rimuove ricorsivamente le chiavi vuote. */
function schema_pulisci( $nodo ) {
	if ( ! is_array( $nodo ) ) {
		return $nodo;
	}
	$out = array();
	foreach ( $nodo as $chiave => $valore ) {
		if ( is_array( $valore ) ) {
			$valore = schema_pulisci( $valore );
		}
		if ( null === $valore || '' === $valore || array() === $valore ) {
			continue;
		}
		$out[ $chiave ] = $valore;
	}
	return $out;
}

/** Orari in formato schema.org (Mo 09:00-19:00). */
function schema_orari( $orari ) {
	$mappa = array(
		'lunedi'    => 'Monday',
		'martedi'   => 'Tuesday',
		'mercoledi' => 'Wednesday',
		'giovedi'   => 'Thursday',
		'venerdi'   => 'Friday',
		'sabato'    => 'Saturday',
		'domenica'  => 'Sunday',
	);
	$out = array();
	foreach ( (array) $orari as $giorno => $fascia ) {
		$chiave = slugifica( $giorno );
		$chiave = str_replace( array( 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì' ), array( 'lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi' ), $chiave );
		if ( ! isset( $mappa[ $chiave ] ) ) {
			continue;
		}
		$fascia = trim( (string) $fascia );
		if ( '' === $fascia || false !== mb_stripos( $fascia, 'chius' ) ) {
			continue;
		}
		if ( ! preg_match( '/(\d{1,2}[:.]\d{2})\s*[\x{2010}-\x{2015}\x{2212}\-]\s*(\d{1,2}[:.]\d{2})/u', $fascia, $m ) ) {
			continue;
		}
		$out[] = array(
			'@type'     => 'OpeningHoursSpecification',
			'dayOfWeek' => $mappa[ $chiave ],
			'opens'     => schema_ora( $m[1] ),
			'closes'    => schema_ora( $m[2] ),
		);
	}
	return $out;
}

/** Orario in formato HH:MM, come lo vuole schema.org. */
function schema_ora( $testo ) {
	$testo = str_replace( '.', ':', trim( (string) $testo ) );
	list( $ore, $minuti ) = array_pad( explode( ':', $testo, 2 ), 2, '00' );
	return sprintf( '%02d:%02d', (int) $ore, (int) $minuti );
}

/** Nodo LocalBusiness per una città. */
function schema_attivita( $citta ) {
	$imp = impostazioni();
	$nodo = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'LocalBusiness',
		'@id'         => url_citta( $citta ) . '#attivita',
		'name'        => impostazione( 'brand', '' ) . ' — ' . $citta['nome'],
		'url'         => url_citta( $citta ),
		'telephone'   => ! vuoto( $citta['telefono'] ) ? $citta['telefono'] : $imp['telefono'],
		'email'       => ! vuoto( $citta['email'] ) ? $citta['email'] : $imp['email'],
		'image'       => url_media( impostazione( 'logo', '' ) ),
		'vatID'       => $imp['piva'],
		'address'     => schema_pulisci( array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $citta['indirizzo'],
			'addressLocality' => $citta['nome'],
			'addressRegion'   => ! vuoto( $citta['provincia'] ) ? $citta['provincia'] : $citta['regione'],
			'postalCode'      => $citta['cap'],
			'addressCountry'  => $imp['nazione'],
		) ),
		'areaServed'  => schema_area( $citta ),
		'openingHoursSpecification' => schema_orari( $citta['orari'] ),
	);
	if ( ! vuoto( $citta['lat'] ) && ! vuoto( $citta['lng'] ) ) {
		$nodo['geo'] = array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => (string) $citta['lat'],
			'longitude' => (string) $citta['lng'],
		);
	}
	$valutazione = schema_valutazione( $citta );
	if ( $valutazione ) {
		$nodo['aggregateRating'] = $valutazione;
	}
	return schema_pulisci( $nodo );
}

/** Zone servite come elenco di luoghi. */
function schema_area( $citta ) {
	$luoghi = array( array( '@type' => 'City', 'name' => $citta['nome'] ) );
	foreach ( righe( (string) $citta['comuni'] ) as $comune ) {
		$luoghi[] = array( '@type' => 'City', 'name' => $comune );
	}
	return $luoghi;
}

/** Media delle recensioni inserite a mano. */
function schema_valutazione( $citta ) {
	$recensioni = (array) $citta['recensioni'];
	$voti       = array();
	foreach ( $recensioni as $r ) {
		$voto = isset( $r['voto'] ) ? (float) $r['voto'] : 0;
		if ( $voto > 0 ) {
			$voti[] = $voto;
		}
	}
	if ( count( $voti ) < 1 ) {
		return null;
	}
	return array(
		'@type'       => 'AggregateRating',
		'ratingValue' => (string) round( array_sum( $voti ) / count( $voti ), 1 ),
		'reviewCount' => (string) count( $voti ),
		'bestRating'  => '5',
		'worstRating' => '1',
	);
}

/** Nodo Service per una pagina servizio. */
function schema_servizio( $citta, $pagina ) {
	if ( 'servizio' !== $pagina['tipo'] ) {
		return null;
	}
	$nodo = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Service',
		'name'        => seo_h1( $citta, $pagina ),
		'description' => seo_descrizione( $citta, $pagina ),
		'url'         => url_pagina( $citta, $pagina ),
		'serviceType' => $pagina['titolo'],
		'areaServed'  => array( '@type' => 'City', 'name' => $citta['nome'] ),
		'provider'    => array( '@id' => url_citta( $citta ) . '#attivita' ),
	);
	if ( ! vuoto( $pagina['prezzo_da'] ) ) {
		$offerta = array(
			'@type'         => 'Offer',
			'priceCurrency' => 'EUR',
			'url'           => url_pagina( $citta, $pagina ),
		);
		if ( ! vuoto( $pagina['prezzo_a'] ) ) {
			$offerta['priceSpecification'] = array(
				'@type'         => 'PriceSpecification',
				'minPrice'      => preg_replace( '/[^0-9.,]/', '', (string) $pagina['prezzo_da'] ),
				'maxPrice'      => preg_replace( '/[^0-9.,]/', '', (string) $pagina['prezzo_a'] ),
				'priceCurrency' => 'EUR',
			);
		} else {
			$offerta['price'] = preg_replace( '/[^0-9.,]/', '', (string) $pagina['prezzo_da'] );
		}
		$nodo['offers'] = $offerta;
	}
	return schema_pulisci( $nodo );
}

/** Nodo FAQPage. */
function schema_faq( $faq ) {
	$voci = array();
	foreach ( (array) $faq as $f ) {
		$d = isset( $f['domanda'] ) ? trim( (string) $f['domanda'] ) : '';
		$r = isset( $f['risposta'] ) ? trim( (string) $f['risposta'] ) : '';
		if ( '' === $d || '' === $r ) {
			continue;
		}
		$voci[] = array(
			'@type'          => 'Question',
			'name'           => $d,
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $r ),
		);
	}
	if ( count( $voci ) < 2 ) {
		return null;
	}
	return array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $voci,
	);
}

/** Briciole di pane. */
function schema_breadcrumb( $citta, $pagina ) {
	$voci = array(
		array( '@type' => 'ListItem', 'position' => 1, 'name' => impostazione( 'brand', 'Home' ), 'item' => base_url() . '/' ),
		array( '@type' => 'ListItem', 'position' => 2, 'name' => $citta['nome'], 'item' => url_citta( $citta ) ),
	);
	if ( 'home' !== $pagina['tipo'] ) {
		$voci[] = array( '@type' => 'ListItem', 'position' => 3, 'name' => $pagina['titolo'], 'item' => url_pagina( $citta, $pagina ) );
	}
	return array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $voci,
	);
}
