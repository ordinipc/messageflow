<?php
/**
 * Dati strutturati JSON-LD.
 *
 * @package MDI_SEO_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stampa un unico grafo JSON-LD per pagina, con entità collegate da @id.
 */
class MDI_Schema {

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_graph' ), 20 );
		add_shortcode( 'mdi_faq', array( __CLASS__, 'shortcode_faq' ) );
		add_shortcode( 'mdi_breadcrumb', array( __CLASS__, 'shortcode_breadcrumb' ) );
		add_shortcode( 'mdi_nap', array( __CLASS__, 'shortcode_nap' ) );
		add_shortcode( 'mdi_in_breve', array( __CLASS__, 'shortcode_in_breve' ) );
	}

	/**
	 * Nodo Organization + ProfessionalService (entità aziendale).
	 *
	 * @return array
	 */
	public static function organization() {
		$home = untrailingslashit( home_url() );

		$node = array(
			'@type'       => array( 'Organization', 'ProfessionalService' ),
			'@id'         => $home . '/#organization',
			'name'        => mdi_seo_geo_cfg( 'azienda.nome', get_bloginfo( 'name' ) ),
			'url'         => $home . '/',
			'description' => mdi_seo_geo_cfg( 'azienda.descrizioneBreve' ),
			'email'       => mdi_seo_geo_cfg( 'azienda.email' ),
			'telephone'   => mdi_seo_geo_cfg( 'azienda.telefono', mdi_seo_geo_cfg( 'azienda.cellulare' ) ),
			'vatID'       => mdi_seo_geo_cfg( 'azienda.partitaIva' ),
			'priceRange'  => mdi_seo_geo_cfg( 'azienda.fasciaPrezzo' ),
			'logo'        => array(
				'@type' => 'ImageObject',
				'@id'   => $home . '/#logo',
				'url'   => mdi_seo_geo_cfg( 'azienda.logo' ),
			),
		);

		$via = mdi_seo_geo_cfg( 'azienda.indirizzo.via' );

		if ( $via ) {
			$node['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $via,
				'addressLocality' => mdi_seo_geo_cfg( 'azienda.indirizzo.citta' ),
				'addressRegion'   => mdi_seo_geo_cfg( 'azienda.indirizzo.provincia' ),
				'postalCode'      => mdi_seo_geo_cfg( 'azienda.indirizzo.cap' ),
				'addressCountry'  => mdi_seo_geo_cfg( 'azienda.indirizzo.nazione', 'IT' ),
			);
		}

		$lat = mdi_seo_geo_cfg( 'azienda.indirizzo.latitudine' );
		$lng = mdi_seo_geo_cfg( 'azienda.indirizzo.longitudine' );

		if ( $lat && $lng ) {
			$node['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $lat,
				'longitude' => $lng,
			);
		}

		$aree = mdi_seo_geo_cfg( 'azienda.areaServita', array() );

		if ( $aree ) {
			$node['areaServed'] = array_map(
				function ( $citta ) {
					return array(
						'@type' => 'City',
						'name'  => $citta,
					);
				},
				$aree
			);
		}

		$orari = mdi_seo_geo_cfg( 'azienda.orari', array() );

		if ( $orari ) {
			$node['openingHoursSpecification'] = array_map(
				function ( $o ) {
					return array(
						'@type'     => 'OpeningHoursSpecification',
						'dayOfWeek' => isset( $o['giorni'] ) ? $o['giorni'] : array(),
						'opens'     => isset( $o['apre'] ) ? $o['apre'] : '',
						'closes'    => isset( $o['chiude'] ) ? $o['chiude'] : '',
					);
				},
				$orari
			);
		}

		$profili = array_filter( (array) mdi_seo_geo_cfg( 'azienda.profili', array() ) );
		$sameas  = array();

		foreach ( $profili as $url ) {
			if ( is_string( $url ) && 0 !== stripos( $url, 'DA_COMPILARE' ) ) {
				$sameas[] = $url;
			}
		}

		if ( $sameas ) {
			$node['sameAs'] = array_values( $sameas );
		}

		return array_filter( $node );
	}

	/**
	 * Nodo WebSite con SearchAction.
	 *
	 * @return array
	 */
	public static function website() {
		$home = untrailingslashit( home_url() );

		return array(
			'@type'           => 'WebSite',
			'@id'             => $home . '/#website',
			'url'             => $home . '/',
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'inLanguage'      => 'it-IT',
			'publisher'       => array( '@id' => $home . '/#organization' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => $home . '/?s={search_term_string}',
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * Nodo Person dell'autore principale.
	 *
	 * @return array
	 */
	public static function person() {
		$home    = untrailingslashit( home_url() );
		$autori  = mdi_seo_geo_cfg( 'autori', array() );
		$autore  = isset( $autori[0] ) ? $autori[0] : array();
		$nome    = trim( ( isset( $autore['nome'] ) ? $autore['nome'] : '' ) . ' ' . ( isset( $autore['cognome'] ) ? $autore['cognome'] : '' ) );
		$nome    = ( $nome && 0 !== stripos( $nome, 'DA_COMPILARE' ) ) ? $nome : mdi_seo_geo_cfg( 'azienda.nome', get_bloginfo( 'name' ) );

		$node = array(
			'@type'    => 'Person',
			'@id'      => $home . '/#/schema/person/' . sanitize_title( $nome ),
			'name'     => $nome,
			'worksFor' => array( '@id' => $home . '/#organization' ),
		);

		foreach ( array(
			'jobTitle'    => 'ruolo',
			'description' => 'bio',
		) as $prop => $key ) {
			if ( ! empty( $autore[ $key ] ) && 0 !== stripos( $autore[ $key ], 'DA_COMPILARE' ) ) {
				$node[ $prop ] = $autore[ $key ];
			}
		}

		return $node;
	}

	/**
	 * Briciole di pane del contenuto corrente.
	 *
	 * @return array
	 */
	public static function breadcrumb() {
		$home  = untrailingslashit( home_url() );
		$items = array(
			array(
				'name' => 'Home',
				'url'  => $home . '/',
			),
		);

		if ( is_singular( 'post' ) ) {
			$cats = get_the_category();

			if ( ! empty( $cats ) ) {
				$items[] = array(
					'name' => $cats[0]->name,
					'url'  => get_category_link( $cats[0]->term_id ),
				);
			}
		}

		if ( is_singular() ) {
			$items[] = array(
				'name' => get_the_title(),
				'url'  => get_permalink(),
			);
		}

		$list = array();

		foreach ( $items as $i => $item ) {
			$list[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $item['name'],
				'item'     => $item['url'],
			);
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => ( is_singular() ? get_permalink() : $home . '/' ) . '#breadcrumb',
			'itemListElement' => $list,
		);
	}

	/**
	 * Estrae coppie domanda/risposta dagli H2/H3 interrogativi del contenuto.
	 *
	 * @param string $content HTML del contenuto.
	 * @return array
	 */
	public static function extract_faq( $content ) {
		$faq = array();

		if ( ! preg_match_all( '/<h([23])[^>]*>(.*?)<\/h\1>([\s\S]*?)(?=<h[23]|$)/i', $content, $matches, PREG_SET_ORDER ) ) {
			return $faq;
		}

		foreach ( $matches as $m ) {
			$domanda = trim( wp_strip_all_tags( $m[2] ) );

			if ( '' === $domanda || false === strpos( $domanda, '?' ) ) {
				continue;
			}

			$risposta = trim( wp_strip_all_tags( $m[3] ) );

			if ( str_word_count( $risposta ) < 8 ) {
				continue;
			}

			$faq[] = array(
				'@type'          => 'Question',
				'name'           => $domanda,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_trim_words( $risposta, 110, '' ),
				),
			);
		}

		return $faq;
	}

	/**
	 * Nodo dell'articolo corrente, con speakable per le risposte vocali e AI.
	 *
	 * @return array
	 */
	public static function article() {
		$home   = untrailingslashit( home_url() );
		$person = self::person();

		return array(
			'@type'            => is_singular( 'post' ) ? 'BlogPosting' : 'WebPage',
			'@id'              => get_permalink() . '#article',
			'headline'         => wp_strip_all_tags( get_the_title() ),
			'description'      => wp_strip_all_tags( get_the_excerpt() ),
			'inLanguage'       => 'it-IT',
			'datePublished'    => get_the_date( DATE_W3C ),
			'dateModified'     => get_the_modified_date( DATE_W3C ),
			'author'           => array( '@id' => $person['@id'] ),
			'publisher'        => array( '@id' => $home . '/#organization' ),
			'mainEntityOfPage' => array( '@id' => get_permalink() ),
			'wordCount'        => str_word_count( wp_strip_all_tags( get_post_field( 'post_content', get_the_ID() ) ) ),
			'speakable'        => array(
				'@type'       => 'SpeakableSpecification',
				'cssSelector' => array( 'h1', '.mdi-in-breve' ),
			),
		);
	}

	/**
	 * Nodo Service per le pagine servizio dichiarate in configurazione.
	 *
	 * @return array|null
	 */
	public static function service() {
		$home     = untrailingslashit( home_url() );
		$pilastri = mdi_seo_geo_cfg( 'seo.paginePilastro', array() );
		$slug     = get_post_field( 'post_name', get_the_ID() );

		foreach ( $pilastri as $p ) {
			if ( isset( $p['slug'] ) && $p['slug'] === $slug ) {
				return array(
					'@type'       => 'Service',
					'@id'         => get_permalink() . '#service',
					'name'        => get_the_title(),
					'serviceType' => isset( $p['keyword'] ) ? $p['keyword'] : get_the_title(),
					'description' => wp_strip_all_tags( get_the_excerpt() ),
					'provider'    => array( '@id' => $home . '/#organization' ),
					'areaServed'  => array_map(
						function ( $citta ) {
							return array(
								'@type' => 'City',
								'name'  => $citta,
							);
						},
						(array) mdi_seo_geo_cfg( 'azienda.areaServita', array() )
					),
					'offers'      => array(
						'@type'         => 'Offer',
						'availability'  => 'https://schema.org/InStock',
						'priceCurrency' => 'EUR',
						'url'           => get_permalink(),
					),
				);
			}
		}

		return null;
	}

	/**
	 * Stampa il grafo completo.
	 *
	 * @return void
	 */
	public static function print_graph() {
		$graph = array( self::organization(), self::website(), self::person(), self::breadcrumb() );

		if ( is_singular() ) {
			$graph[] = self::article();

			$service = self::service();

			if ( $service ) {
				$graph[] = $service;
			}

			$faq = self::extract_faq( get_post_field( 'post_content', get_the_ID() ) );

			if ( count( $faq ) >= 2 ) {
				$graph[] = array(
					'@type'      => 'FAQPage',
					'@id'        => get_permalink() . '#faq',
					'mainEntity' => $faq,
				);
			}
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		echo "\n<!-- MDI SEO & GEO Booster: dati strutturati -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}

	/**
	 * Shortcode [mdi_faq] — blocco FAQ visibile, che alimenta anche lo schema.
	 *
	 * @param array  $atts    Attributi.
	 * @param string $content Contenuto: righe "D: ... / R: ...".
	 * @return string
	 */
	public static function shortcode_faq( $atts, $content = '' ) {
		$righe = array_filter( array_map( 'trim', explode( "\n", wp_strip_all_tags( $content ) ) ) );
		$out   = '<section class="mdi-faq"><h2>Domande frequenti</h2>';
		$aperto = false;

		foreach ( $righe as $riga ) {
			if ( 0 === stripos( $riga, 'D:' ) ) {
				if ( $aperto ) {
					$out .= '</div>';
				}
				$out   .= '<div class="mdi-faq__item"><h3>' . esc_html( trim( substr( $riga, 2 ) ) ) . '</h3>';
				$aperto = true;
			} elseif ( 0 === stripos( $riga, 'R:' ) ) {
				$out .= '<p>' . esc_html( trim( substr( $riga, 2 ) ) ) . '</p>';
			}
		}

		if ( $aperto ) {
			$out .= '</div>';
		}

		return $out . '</section>';
	}

	/**
	 * Shortcode [mdi_breadcrumb].
	 *
	 * @return string
	 */
	public static function shortcode_breadcrumb() {
		$bc    = self::breadcrumb();
		$parti = array();

		foreach ( $bc['itemListElement'] as $item ) {
			$parti[] = '<a href="' . esc_url( $item['item'] ) . '">' . esc_html( $item['name'] ) . '</a>';
		}

		return '<nav class="mdi-breadcrumb" aria-label="Percorso di navigazione">' . implode( ' <span aria-hidden="true">›</span> ', $parti ) . '</nav>';
	}

	/**
	 * Shortcode [mdi_nap] — nome, indirizzo, telefono coerenti in tutto il sito.
	 *
	 * @return string
	 */
	public static function shortcode_nap() {
		$nome = mdi_seo_geo_cfg( 'azienda.nome', get_bloginfo( 'name' ) );
		$via  = mdi_seo_geo_cfg( 'azienda.indirizzo.via' );
		$cap  = mdi_seo_geo_cfg( 'azienda.indirizzo.cap' );
		$city = mdi_seo_geo_cfg( 'azienda.indirizzo.citta' );
		$prov = mdi_seo_geo_cfg( 'azienda.indirizzo.provincia' );
		$tel  = mdi_seo_geo_cfg( 'azienda.telefono', mdi_seo_geo_cfg( 'azienda.cellulare' ) );
		$mail = mdi_seo_geo_cfg( 'azienda.email' );
		$piva = mdi_seo_geo_cfg( 'azienda.partitaIva' );

		$out = '<address class="mdi-nap"><strong>' . esc_html( $nome ) . '</strong><br />';

		if ( $via ) {
			$out .= esc_html( trim( $via . ', ' . $cap . ' ' . $city . ' (' . $prov . ')' ) ) . '<br />';
		}

		if ( $tel ) {
			$out .= 'Tel. <a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ) . '">' . esc_html( $tel ) . '</a><br />';
		}

		if ( $mail ) {
			$out .= '<a href="mailto:' . esc_attr( $mail ) . '">' . esc_html( $mail ) . '</a><br />';
		}

		if ( $piva ) {
			$out .= 'P.IVA ' . esc_html( $piva );
		}

		return $out . '</address>';
	}

	/**
	 * Shortcode [mdi_in_breve] — risposta sintetica in apertura, il blocco che
	 * i motori generativi citano più volentieri.
	 *
	 * @param array  $atts    Attributi.
	 * @param string $content Testo della sintesi.
	 * @return string
	 */
	public static function shortcode_in_breve( $atts, $content = '' ) {
		$testo = trim( wp_strip_all_tags( $content ) );

		if ( '' === $testo ) {
			$testo = wp_trim_words( get_the_excerpt(), 55, '' );
		}

		return '<div class="mdi-in-breve"><p><strong>In breve:</strong> ' . esc_html( $testo ) . '</p></div>';
	}
}
