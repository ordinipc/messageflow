<?php
/**
 * Meta tag SEO, canonical, robots, Open Graph, sitemap e breadcrumb.
 *
 * Se è attivo un plugin SEO (Yoast, Rank Math, SEOPress, AIOSEO) i valori
 * vengono passati a quel plugin invece di stampare tag duplicati.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_SEO {

	public static function init() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );

		// Esclude dalla sitemap di WordPress le pagine non indicizzabili.
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_args' ), 10, 2 );

		// Integrazione con i plugin SEO più diffusi.
		add_filter( 'wpseo_title', array( __CLASS__, 'filter_title' ) );
		add_filter( 'wpseo_metadesc', array( __CLASS__, 'filter_description' ) );
		add_filter( 'wpseo_canonical', array( __CLASS__, 'filter_canonical' ) );
		add_filter( 'rank_math/frontend/title', array( __CLASS__, 'filter_title' ) );
		add_filter( 'rank_math/frontend/description', array( __CLASS__, 'filter_description' ) );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'filter_canonical' ) );
		add_filter( 'seopress_titles_title', array( __CLASS__, 'filter_title' ) );
		add_filter( 'seopress_titles_desc', array( __CLASS__, 'filter_description' ) );
		add_filter( 'aioseo_title', array( __CLASS__, 'filter_title' ) );
		add_filter( 'aioseo_description', array( __CLASS__, 'filter_description' ) );
	}

	/**
	 * È attivo un plugin SEO che gestisce già i meta tag?
	 *
	 * @return bool
	 */
	public static function has_seo_plugin() {
		return defined( 'WPSEO_VERSION' )
			|| defined( 'RANK_MATH_VERSION' )
			|| defined( 'SEOPRESS_VERSION' )
			|| defined( 'AIOSEO_VERSION' );
	}

	/**
	 * Siamo su una landing page del plugin?
	 *
	 * @return int ID del post, 0 altrimenti.
	 */
	private static function current() {
		if ( ! is_singular( GLP_POST_TYPE ) ) {
			return 0;
		}
		return (int) get_queried_object_id();
	}

	/**
	 * Title calcolato.
	 *
	 * @param int $post_id ID post.
	 * @return string
	 */
	public static function title( $post_id ) {
		$custom = GLP_Meta::get( $post_id, 'seo_title' );
		if ( '' !== $custom ) {
			return GLP_Content::render( $custom, $post_id );
		}
		if ( get_post_meta( $post_id, GLP_CITY_PAGE_META, true ) ) {
			return GLP_Content::ucfirst_text( GLP_Content::render( '{titolo}{sep}{brand}', $post_id ) );
		}

		$template = GLP_Post_Types::is_city( $post_id )
			? GLP_Settings::get( 'city_title_template', '{servizio_default} a {citta}{sep}{brand}' )
			: GLP_Settings::get( 'title_template', '{servizio} a {citta}{sep}{brand}' );

		$title = GLP_Content::ucfirst_text( GLP_Content::render( $template, $post_id ) );
		return '' !== $title ? $title : get_the_title( $post_id );
	}

	/**
	 * Meta description calcolata.
	 *
	 * @param int $post_id ID post.
	 * @return string
	 */
	public static function description( $post_id ) {
		$custom = GLP_Meta::get( $post_id, 'seo_description' );
		if ( '' !== $custom ) {
			return GLP_Content::render( $custom, $post_id );
		}
		$desc = GLP_Content::render( GLP_Settings::get( 'desc_template', '' ), $post_id );
		if ( '' === $desc ) {
			$post = get_post( $post_id );
			$desc = $post ? wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 ) : '';
		}
		return $desc;
	}

	/**
	 * URL canonico.
	 *
	 * @param int $post_id ID post.
	 * @return string
	 */
	public static function canonical( $post_id ) {
		$custom = GLP_Meta::get( $post_id, 'canonical' );
		return '' !== $custom ? $custom : get_permalink( $post_id );
	}

	/**
	 * Sostituisce il title del documento.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function document_title( $title ) {
		$post_id = self::current();
		if ( ! $post_id || self::has_seo_plugin() ) {
			return $title;
		}
		return self::title( $post_id );
	}

	/**
	 * Stampa i meta tag quando nessun plugin SEO li gestisce.
	 */
	public static function head() {
		$post_id = self::current();
		if ( ! $post_id || self::has_seo_plugin() ) {
			return;
		}

		$desc      = self::description( $post_id );
		$canonical = self::canonical( $post_id );
		$image     = GLP_Meta::get( $post_id, 'og_image' );
		if ( '' === $image ) {
			$image = get_the_post_thumbnail_url( $post_id, 'full' );
		}

		echo "\n<!-- Geo Landing Pages -->\n";
		if ( '' !== $desc ) {
			echo '<meta name="description" content="' . esc_attr( wp_html_excerpt( $desc, 300 ) ) . '" />' . "\n";
		}
		if ( $canonical ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
		}

		echo '<meta property="og:type" content="website" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( self::title( $post_id ) ) . '" />' . "\n";
		if ( '' !== $desc ) {
			echo '<meta property="og:description" content="' . esc_attr( wp_html_excerpt( $desc, 300 ) ) . '" />' . "\n";
		}
		echo '<meta property="og:url" content="' . esc_url( $canonical ) . '" />' . "\n";
		echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";
		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
		}
		echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '" />' . "\n";

		$lat = GLP_Meta::get( $post_id, 'lat' );
		$lng = GLP_Meta::get( $post_id, 'lng' );
		if ( '' !== $lat && '' !== $lng ) {
			echo '<meta name="geo.position" content="' . esc_attr( $lat . ';' . $lng ) . '" />' . "\n";
			echo '<meta name="ICBM" content="' . esc_attr( $lat . ', ' . $lng ) . '" />' . "\n";
		}
		$citta = GLP_Meta::get( $post_id, 'citta' );
		if ( '' !== $citta ) {
			echo '<meta name="geo.placename" content="' . esc_attr( $citta ) . '" />' . "\n";
		}
		$regione = GLP_Meta::get( $post_id, 'regione' );
		if ( '' !== $regione ) {
			echo '<meta name="geo.region" content="' . esc_attr( GLP_Meta::get( $post_id, 'nazione' ) . '-' . $regione ) . '" />' . "\n";
		}
		echo "<!-- /Geo Landing Pages -->\n\n";
	}

	/**
	 * Applica noindex alle pagine sotto soglia.
	 *
	 * @param array $robots Direttive.
	 * @return array
	 */
	public static function robots( $robots ) {
		$post_id = self::current();
		if ( ! $post_id ) {
			return $robots;
		}
		if ( GLP_Meta::is_indexable( $post_id ) ) {
			$robots['index']             = true;
			$robots['follow']            = true;
			$robots['max-image-preview'] = 'large';
			$robots['max-snippet']       = -1;
			unset( $robots['noindex'] );
		} else {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}
		return $robots;
	}

	/**
	 * Esclude dalla sitemap le landing non indicizzabili.
	 *
	 * @param array  $args      Argomenti query.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public static function sitemap_args( $args, $post_type ) {
		if ( GLP_POST_TYPE !== $post_type ) {
			return $args;
		}
		$exclude = array();
		$ids     = get_posts(
			array(
				'post_type'      => GLP_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 2000,
				'fields'         => 'ids',
			)
		);

		// Scalda la cache dei metadati: evita una query per ogni pagina.
		if ( ! empty( $ids ) ) {
			update_meta_cache( 'post', $ids );
		}

		foreach ( $ids as $id ) {
			if ( ! GLP_Meta::is_indexable( $id ) ) {
				$exclude[] = $id;
			}
		}
		if ( ! empty( $exclude ) ) {
			$args['post__not_in'] = isset( $args['post__not_in'] ) ? array_merge( (array) $args['post__not_in'], $exclude ) : $exclude;
		}
		return $args;
	}

	/**
	 * Passa il title al plugin SEO attivo.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function filter_title( $title ) {
		$post_id = self::current();
		if ( ! $post_id ) {
			return $title;
		}
		$custom = GLP_Meta::get( $post_id, 'seo_title' );
		// Se l'utente ha compilato il campo del plugin SEO, lo rispettiamo.
		return '' !== $custom || '' === trim( (string) $title ) ? self::title( $post_id ) : $title;
	}

	/**
	 * Passa la description al plugin SEO attivo.
	 *
	 * @param string $desc Description.
	 * @return string
	 */
	public static function filter_description( $desc ) {
		$post_id = self::current();
		if ( ! $post_id ) {
			return $desc;
		}
		$custom = GLP_Meta::get( $post_id, 'seo_description' );
		return '' !== $custom || '' === trim( (string) $desc ) ? self::description( $post_id ) : $desc;
	}

	/**
	 * Passa il canonical al plugin SEO attivo.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function filter_canonical( $url ) {
		$post_id = self::current();
		if ( ! $post_id ) {
			return $url;
		}
		$custom = GLP_Meta::get( $post_id, 'canonical' );
		return '' !== $custom ? $custom : $url;
	}

	/**
	 * Briciole di pane (usate anche dai dati strutturati).
	 *
	 * @param int $post_id ID post.
	 * @return array Elenco di array url/label.
	 */
	public static function breadcrumb_items( $post_id ) {
		$items = array(
			array(
				'url'   => home_url( '/' ),
				'label' => __( 'Home', 'geo-landing-pages' ),
			),
		);

		$city = GLP_Post_Types::city_post( $post_id );
		if ( $city ) {
			$items[] = array(
				'url'   => get_permalink( $city ),
				'label' => $city->post_title,
			);
		}
		if ( $city && (int) $city->ID !== (int) $post_id ) {
			$items[] = array(
				'url'   => get_permalink( $post_id ),
				'label' => get_the_title( $post_id ),
			);
		}
		return $items;
	}

	/**
	 * HTML delle briciole di pane.
	 *
	 * @param int $post_id ID post.
	 * @return string
	 */
	public static function breadcrumbs_html( $post_id ) {
		if ( ! GLP_Settings::get( 'breadcrumbs', 1 ) ) {
			return '';
		}
		$items = self::breadcrumb_items( $post_id );
		if ( count( $items ) < 2 ) {
			return '';
		}
		$html  = '<nav class="glp-breadcrumbs" aria-label="' . esc_attr__( 'Percorso di navigazione', 'geo-landing-pages' ) . '"><ol>';
		$last  = count( $items ) - 1;
		foreach ( $items as $i => $item ) {
			$html .= '<li>';
			$html .= $i === $last
				? '<span aria-current="page">' . esc_html( $item['label'] ) . '</span>'
				: '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
			$html .= '</li>';
		}
		return $html . '</ol></nav>';
	}
}
