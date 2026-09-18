<?php
/**
 * Dati strutturati JSON-LD.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Schema {

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 20 );
	}

	/**
	 * Stampa il grafo JSON-LD.
	 */
	public static function output() {
		if ( ! is_singular( GLP_POST_TYPE ) || ! GLP_Settings::get( 'schema_enabled', 1 ) ) {
			return;
		}
		$post_id = (int) get_queried_object_id();

		// Una pagina non indicizzabile non deve dichiarare dati strutturati.
		if ( ! GLP_Meta::is_indexable( $post_id ) ) {
			return;
		}

		$graph = array_values( array_filter( array(
			self::business( $post_id ),
			self::service( $post_id ),
			self::faq( $post_id ),
			self::breadcrumbs( $post_id ),
		) ) );

		if ( empty( $graph ) ) {
			return;
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		/**
		 * Grafo dei dati strutturati.
		 *
		 * @param array $data    Grafo.
		 * @param int   $post_id ID post.
		 */
		$data = apply_filters( 'glp_schema_graph', $data, $post_id );

		echo '<script type="application/ld+json">'
			. wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>' . "\n";
	}

	/**
	 * Identificatore univoco del nodo attività.
	 *
	 * @param int $post_id ID post.
	 * @return string
	 */
	private static function business_id( $post_id ) {
		$city = GLP_Post_Types::city_post( $post_id );
		$base = $city ? get_permalink( $city ) : get_permalink( $post_id );
		return $base . '#business';
	}

	/**
	 * Nodo LocalBusiness.
	 *
	 * @param int $post_id ID post.
	 * @return array|null
	 */
	public static function business( $post_id ) {
		$nome = GLP_Meta::get( $post_id, 'azienda' );
		if ( '' === $nome ) {
			$nome = GLP_Settings::get( 'brand', get_bloginfo( 'name' ) );
		}
		$citta = GLP_Meta::get( $post_id, 'citta' );
		if ( '' === $citta ) {
			return null;
		}

		$tipo = GLP_Settings::get( 'schema_type', 'LocalBusiness' );
		$tel  = GLP_Meta::get( $post_id, 'telefono' );
		$tel  = '' !== $tel ? $tel : GLP_Settings::get( 'telefono', '' );

		$node = array(
			'@type' => $tipo,
			'@id'   => self::business_id( $post_id ),
			'name'  => $nome . ' — ' . $citta,
			'url'   => get_permalink( GLP_Post_Types::city_post( $post_id ) ?: $post_id ),
		);

		$desc = GLP_SEO::description( $post_id );
		if ( '' !== $desc ) {
			$node['description'] = $desc;
		}
		if ( '' !== $tel ) {
			$node['telephone'] = $tel;
		}
		$email = GLP_Meta::get( $post_id, 'email' );
		if ( '' === $email ) {
			$email = GLP_Settings::get( 'email', '' );
		}
		if ( '' !== $email ) {
			$node['email'] = $email;
		}
		$piva = GLP_Meta::get( $post_id, 'partita_iva' );
		if ( '' === $piva ) {
			$piva = GLP_Settings::get( 'partita_iva', '' );
		}
		if ( '' !== $piva ) {
			$node['vatID'] = $piva;
		}

		// Indirizzo: dichiarato solo se esiste una sede reale.
		$indirizzo = GLP_Meta::get( $post_id, 'indirizzo' );
		$address   = array(
			'@type'           => 'PostalAddress',
			'addressLocality' => $citta,
			'addressCountry'  => GLP_Meta::get( $post_id, 'nazione' ) ?: 'IT',
		);
		if ( '' !== $indirizzo ) {
			$address['streetAddress'] = $indirizzo;
		}
		$cap = GLP_Meta::get( $post_id, 'cap' );
		if ( '' !== $cap ) {
			$address['postalCode'] = $cap;
		}
		$prov = GLP_Meta::get( $post_id, 'provincia' );
		if ( '' !== $prov ) {
			$address['addressRegion'] = $prov;
		}
		$node['address'] = $address;

		$lat = GLP_Meta::get( $post_id, 'lat' );
		$lng = GLP_Meta::get( $post_id, 'lng' );
		if ( '' !== $lat && '' !== $lng ) {
			$node['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) str_replace( ',', '.', $lat ),
				'longitude' => (float) str_replace( ',', '.', $lng ),
			);
		}

		// areaServed: città, quartieri e comuni limitrofi.
		$aree = array( $citta );
		foreach ( array( 'zone_servite', 'comuni_limitrofi' ) as $key ) {
			$valori = GLP_Meta::get( $post_id, $key );
			if ( is_array( $valori ) ) {
				$aree = array_merge( $aree, $valori );
			}
		}
		$aree = array_values( array_unique( array_filter( $aree ) ) );
		if ( ! empty( $aree ) ) {
			$node['areaServed'] = array_map(
				static function ( $area ) {
					return array(
						'@type' => 'Place',
						'name'  => $area,
					);
				},
				$aree
			);
		}

		$orari = self::opening_hours( $post_id );
		if ( ! empty( $orari ) ) {
			$node['openingHoursSpecification'] = $orari;
		}

		$fascia = GLP_Meta::get( $post_id, 'fascia_prezzo' );
		if ( '' !== $fascia ) {
			$node['priceRange'] = $fascia;
		}
		$pagamenti = GLP_Meta::get( $post_id, 'metodi_pagamento' );
		if ( is_array( $pagamenti ) && ! empty( $pagamenti ) ) {
			$node['paymentAccepted'] = implode( ', ', $pagamenti );
		}

		$immagine = GLP_Meta::get( $post_id, 'og_image' );
		if ( '' === $immagine ) {
			$immagine = get_the_post_thumbnail_url( $post_id, 'full' );
		}
		if ( $immagine ) {
			$node['image'] = $immagine;
		}

		$referente = GLP_Meta::get( $post_id, 'referente_nome' );
		if ( '' !== $referente ) {
			$node['employee'] = array(
				'@type'    => 'Person',
				'name'     => $referente,
				'jobTitle' => GLP_Meta::get( $post_id, 'referente_ruolo' ),
			);
		}

		// Recensioni: solo se reali e inserite dall'utente.
		$reviews = GLP_Meta::get( $post_id, 'testimonianze' );
		if ( is_array( $reviews ) && ! empty( $reviews ) ) {
			$nodes  = array();
			$somma  = 0;
			$votate = 0;
			foreach ( $reviews as $r ) {
				if ( '' === trim( (string) $r['testo'] ) ) {
					continue;
				}
				$review = array(
					'@type'  => 'Review',
					'author' => array(
						'@type' => 'Person',
						'name'  => '' !== $r['nome'] ? $r['nome'] : __( 'Cliente', 'geo-landing-pages' ),
					),
					'reviewBody' => $r['testo'],
				);
				if ( '' !== $r['data'] ) {
					$review['datePublished'] = $r['data'];
				}
				$voto = (int) $r['voto'];
				if ( $voto > 0 && $voto <= 5 ) {
					$review['reviewRating'] = array(
						'@type'       => 'Rating',
						'ratingValue' => $voto,
						'bestRating'  => 5,
						'worstRating' => 1,
					);
					$somma  += $voto;
					$votate++;
				}
				$nodes[] = $review;
			}
			if ( ! empty( $nodes ) ) {
				$node['review'] = $nodes;
			}
			if ( $votate > 0 ) {
				$node['aggregateRating'] = array(
					'@type'       => 'AggregateRating',
					'ratingValue' => round( $somma / $votate, 1 ),
					'reviewCount' => $votate,
					'bestRating'  => 5,
					'worstRating' => 1,
				);
			}
		}

		return $node;
	}

	/**
	 * openingHoursSpecification.
	 *
	 * @param int $post_id ID post.
	 * @return array
	 */
	private static function opening_hours( $post_id ) {
		if ( GLP_Meta::get( $post_id, 'h24' ) ) {
			return array(
				array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ),
					'opens'     => '00:00',
					'closes'    => '23:59',
				),
			);
		}

		$orari = GLP_Meta::get( $post_id, 'orari' );
		if ( ! is_array( $orari ) ) {
			return array();
		}

		$days = GLP_Meta::days();
		$out  = array();
		foreach ( $orari as $day => $row ) {
			if ( ! isset( $days[ $day ] ) || ! is_array( $row ) ) {
				continue;
			}
			if ( ! empty( $row['closed'] ) || empty( $row['open'] ) || empty( $row['close'] ) ) {
				continue;
			}
			$out[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $days[ $day ][1],
				'opens'     => $row['open'],
				'closes'    => $row['close'],
			);
		}
		return $out;
	}

	/**
	 * Nodo Service.
	 *
	 * @param int $post_id ID post.
	 * @return array|null
	 */
	public static function service( $post_id ) {
		$tokens   = GLP_Content::tokens( $post_id );
		$servizio = $tokens['{servizio}'];
		$citta    = $tokens['{citta}'];
		if ( '' === $servizio || '' === $citta ) {
			return null;
		}

		$node = array(
			'@type'       => 'Service',
			'@id'         => get_permalink( $post_id ) . '#service',
			'name'        => sprintf( '%s a %s', $servizio, $citta ),
			'serviceType' => $servizio,
			'provider'    => array( '@id' => self::business_id( $post_id ) ),
			'areaServed'  => array(
				'@type' => 'City',
				'name'  => $citta,
			),
			'url'         => get_permalink( $post_id ),
		);

		$desc = GLP_SEO::description( $post_id );
		if ( '' !== $desc ) {
			$node['description'] = $desc;
		}

		$inclusi = GLP_Meta::get( $post_id, 'servizi_inclusi' );
		if ( is_array( $inclusi ) && ! empty( $inclusi ) ) {
			$items = array();
			foreach ( $inclusi as $i => $voce ) {
				$items[] = array(
					'@type'    => 'Offer',
					'itemOffered' => array(
						'@type' => 'Service',
						'name'  => $voce,
					),
					'position' => $i + 1,
				);
			}
			$node['hasOfferCatalog'] = array(
				'@type'           => 'OfferCatalog',
				'name'            => sprintf( '%s — %s', $servizio, $citta ),
				'itemListElement' => $items,
			);
		}

		$da = GLP_Meta::get( $post_id, 'prezzo_da' );
		if ( '' !== $da ) {
			$valuta = GLP_Meta::get( $post_id, 'valuta' );
			$offer  = array(
				'@type'         => 'Offer',
				'priceCurrency' => '' !== $valuta ? $valuta : 'EUR',
				'availability'  => 'https://schema.org/InStock',
				'url'           => get_permalink( $post_id ),
			);
			$a = GLP_Meta::get( $post_id, 'prezzo_a' );
			if ( '' !== $a ) {
				$offer['priceSpecification'] = array(
					'@type'         => 'PriceSpecification',
					'minPrice'      => self::to_number( $da ),
					'maxPrice'      => self::to_number( $a ),
					'priceCurrency' => '' !== $valuta ? $valuta : 'EUR',
				);
			} else {
				$offer['price'] = self::to_number( $da );
			}
			$node['offers'] = $offer;
		}

		return $node;
	}

	/**
	 * Nodo FAQPage.
	 *
	 * @param int $post_id ID post.
	 * @return array|null
	 */
	public static function faq( $post_id ) {
		$faq = GLP_Meta::get( $post_id, 'faq' );
		if ( ! is_array( $faq ) || empty( $faq ) ) {
			return null;
		}

		$items = array();
		foreach ( $faq as $row ) {
			$domanda  = trim( (string) $row['domanda'] );
			$risposta = trim( (string) $row['risposta'] );
			if ( '' === $domanda || '' === $risposta ) {
				continue;
			}
			$items[] = array(
				'@type'          => 'Question',
				'name'           => GLP_Content::render( $domanda, $post_id ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => GLP_Content::render( $risposta, $post_id ),
				),
			);
		}

		if ( empty( $items ) ) {
			return null;
		}

		return array(
			'@type'      => 'FAQPage',
			'@id'        => get_permalink( $post_id ) . '#faq',
			'mainEntity' => $items,
		);
	}

	/**
	 * Nodo BreadcrumbList.
	 *
	 * @param int $post_id ID post.
	 * @return array|null
	 */
	public static function breadcrumbs( $post_id ) {
		$items = GLP_SEO::breadcrumb_items( $post_id );
		if ( count( $items ) < 2 ) {
			return null;
		}
		$list = array();
		foreach ( $items as $i => $item ) {
			$list[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $item['label'],
				'item'     => $item['url'],
			);
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => get_permalink( $post_id ) . '#breadcrumb',
			'itemListElement' => $list,
		);
	}

	/**
	 * Converte un prezzo inserito dall'utente in numero.
	 *
	 * @param string $value Valore.
	 * @return float
	 */
	private static function to_number( $value ) {
		return (float) str_replace( ',', '.', preg_replace( '/[^0-9.,]/', '', (string) $value ) );
	}
}
