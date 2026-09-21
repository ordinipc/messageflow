<?php
/**
 * Funzioni di supporto del template città.
 *
 * Nessuna dipendenza: PHP 7.4 o superiore e basta.
 */

/**
 * Stampa sicura di un testo dentro l'HTML.
 *
 * @param string $testo Testo.
 * @return string
 */
function e( $testo ) {
	return htmlspecialchars( (string) $testo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Valore di configurazione, con ripiego.
 *
 * @param array  $c       Configurazione.
 * @param string $chiave  Chiave, anche annidata con il punto: 'prezzo.da'.
 * @param mixed  $ripiego Valore se manca.
 * @return mixed
 */
function conf( $c, $chiave, $ripiego = '' ) {
	$parti = explode( '.', $chiave );
	$val   = $c;

	foreach ( $parti as $parte ) {
		if ( ! is_array( $val ) || ! isset( $val[ $parte ] ) ) {
			return $ripiego;
		}
		$val = $val[ $parte ];
	}

	return $val;
}

/**
 * Il valore è vuoto? (0 e "0" non sono vuoti)
 *
 * @param mixed $val Valore.
 * @return bool
 */
function vuoto( $val ) {
	if ( is_array( $val ) ) {
		return empty( $val );
	}
	return '' === trim( (string) $val );
}

/**
 * Numero di telefono pronto per il link tel:
 *
 * @param string $numero Numero.
 * @return string
 */
function tel( $numero ) {
	return preg_replace( '/[^0-9+]/', '', (string) $numero );
}

/**
 * Titolo H1 della pagina.
 *
 * @param array $c Configurazione.
 * @return string HTML.
 */
function titolo_hero( $c ) {
	$servizio = conf( $c, 'servizio' );
	$citta    = conf( $c, 'citta' );

	if ( vuoto( $servizio ) ) {
		return e( $citta );
	}

	// L'iniziale maiuscola, e la città in evidenza.
	return e( maiuscola( $servizio ) ) . ' a <span>' . e( $citta ) . '</span>';
}

/**
 * Iniziale maiuscola, compatibile con gli accenti.
 *
 * @param string $testo Testo.
 * @return string
 */
function maiuscola( $testo ) {
	$testo = (string) $testo;

	if ( '' === $testo ) {
		return '';
	}

	return mb_strtoupper( mb_substr( $testo, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $testo, 1, null, 'UTF-8' );
}

/**
 * Apre una sezione con micro-etichetta e titolo.
 *
 * @param string $id        Identificativo.
 * @param string $titolo    Titolo.
 * @param string $etichetta Micro-etichetta.
 * @return string
 */
function sezione_apri( $id, $titolo, $etichetta = '' ) {
	$html = '<section class="glp-section glp-section--' . e( $id ) . ' glp-reveal" id="' . e( $id ) . '">';

	if ( ! vuoto( $etichetta ) ) {
		$html .= '<p class="glp-section__label">' . e( $etichetta ) . '</p>';
	}
	if ( ! vuoto( $titolo ) ) {
		$html .= '<h2 class="glp-section__title">' . e( $titolo ) . '</h2>';
	}

	return $html;
}

/**
 * Paragrafi da un testo con righe vuote di separazione.
 *
 * @param string $testo Testo.
 * @return string
 */
function paragrafi( $testo ) {
	$html = '';

	foreach ( preg_split( '/\n\s*\n/', trim( (string) $testo ) ) as $pezzo ) {
		if ( '' === trim( $pezzo ) ) {
			continue;
		}
		$html .= '<p>' . nl2br( e( trim( $pezzo ) ) ) . '</p>';
	}

	return $html;
}

/**
 * Dati strutturati JSON-LD.
 *
 * Solo con i dati presenti: niente campi inventati.
 *
 * @param array $c Configurazione.
 * @return string
 */
function json_ld( $c ) {
	$url   = conf( $c, 'url' );
	$citta = conf( $c, 'citta' );
	$grafo = array();

	/* Attività locale */

	$attivita = array(
		'@type' => 'LocalBusiness',
		'@id'   => $url . '#business',
		'name'  => conf( $c, 'brand' ) . ( vuoto( $citta ) ? '' : ' — ' . $citta ),
		'url'   => $url,
	);

	$indirizzo = array( '@type' => 'PostalAddress', 'addressLocality' => $citta );
	if ( ! vuoto( conf( $c, 'indirizzo' ) ) ) {
		$indirizzo['streetAddress'] = conf( $c, 'indirizzo' );
	}
	if ( ! vuoto( conf( $c, 'cap' ) ) ) {
		$indirizzo['postalCode'] = conf( $c, 'cap' );
	}
	if ( ! vuoto( conf( $c, 'provincia' ) ) ) {
		$indirizzo['addressRegion'] = conf( $c, 'provincia' );
	}
	$indirizzo['addressCountry'] = conf( $c, 'nazione', 'IT' );
	$attivita['address']         = $indirizzo;

	if ( ! vuoto( conf( $c, 'telefono' ) ) ) {
		$attivita['telephone'] = conf( $c, 'telefono' );
	}
	if ( ! vuoto( conf( $c, 'email' ) ) ) {
		$attivita['email'] = conf( $c, 'email' );
	}
	if ( ! vuoto( conf( $c, 'lat' ) ) && ! vuoto( conf( $c, 'lng' ) ) ) {
		$attivita['geo'] = array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) str_replace( ',', '.', conf( $c, 'lat' ) ),
			'longitude' => (float) str_replace( ',', '.', conf( $c, 'lng' ) ),
		);
	}

	$aree = array_merge( array( $citta ), (array) conf( $c, 'zone', array() ), (array) conf( $c, 'comuni', array() ) );
	$aree = array_values( array_unique( array_filter( $aree ) ) );
	if ( ! empty( $aree ) ) {
		$attivita['areaServed'] = array_map(
			static function ( $a ) {
				return array( '@type' => 'Place', 'name' => $a );
			},
			$aree
		);
	}

	// Recensioni: solo quelle inserite davvero.
	$recensioni = (array) conf( $c, 'recensioni', array() );
	if ( ! empty( $recensioni ) ) {
		$voci   = array();
		$somma  = 0;
		$votate = 0;

		foreach ( $recensioni as $r ) {
			if ( vuoto( conf( $r, 'testo' ) ) ) {
				continue;
			}
			$voce = array(
				'@type'      => 'Review',
				'author'     => array( '@type' => 'Person', 'name' => conf( $r, 'nome', 'Cliente' ) ),
				'reviewBody' => conf( $r, 'testo' ),
			);
			if ( ! vuoto( conf( $r, 'data' ) ) ) {
				$voce['datePublished'] = conf( $r, 'data' );
			}
			$voto = (int) conf( $r, 'voto', 0 );
			if ( $voto > 0 && $voto <= 5 ) {
				$voce['reviewRating'] = array( '@type' => 'Rating', 'ratingValue' => $voto, 'bestRating' => 5, 'worstRating' => 1 );
				$somma  += $voto;
				$votate++;
			}
			$voci[] = $voce;
		}

		if ( ! empty( $voci ) ) {
			$attivita['review'] = $voci;
		}
		if ( $votate > 0 ) {
			$attivita['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => round( $somma / $votate, 1 ),
				'reviewCount' => $votate,
				'bestRating'  => 5,
				'worstRating' => 1,
			);
		}
	}

	$grafo[] = $attivita;

	/* Servizio */

	if ( ! vuoto( conf( $c, 'servizio' ) ) ) {
		$servizio = array(
			'@type'       => 'Service',
			'@id'         => $url . '#service',
			'name'        => conf( $c, 'servizio' ) . ' a ' . $citta,
			'serviceType' => conf( $c, 'servizio' ),
			'provider'    => array( '@id' => $url . '#business' ),
			'areaServed'  => array( '@type' => 'City', 'name' => $citta ),
			'url'         => $url,
		);

		if ( ! vuoto( conf( $c, 'prezzo.da' ) ) ) {
			$servizio['offers'] = array(
				'@type'         => 'Offer',
				'price'         => (float) conf( $c, 'prezzo.da' ),
				'priceCurrency' => conf( $c, 'prezzo.valuta', 'EUR' ),
				'url'           => $url,
			);
		}

		$grafo[] = $servizio;
	}

	/* Domande frequenti */

	$faq = (array) conf( $c, 'faq', array() );
	if ( ! empty( $faq ) ) {
		$voci = array();
		foreach ( $faq as $riga ) {
			if ( vuoto( conf( $riga, 'domanda' ) ) || vuoto( conf( $riga, 'risposta' ) ) ) {
				continue;
			}
			$voci[] = array(
				'@type'          => 'Question',
				'name'           => conf( $riga, 'domanda' ),
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => conf( $riga, 'risposta' ) ),
			);
		}
		if ( ! empty( $voci ) ) {
			$grafo[] = array( '@type' => 'FAQPage', '@id' => $url . '#faq', 'mainEntity' => $voci );
		}
	}

	$dati = array( '@context' => 'https://schema.org', '@graph' => $grafo );

	return json_encode( $dati, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
}
