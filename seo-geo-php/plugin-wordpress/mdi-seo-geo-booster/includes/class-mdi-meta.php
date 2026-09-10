<?php
/**
 * Title, meta description, robots, canonical e Open Graph.
 *
 * @package MDI_SEO_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applica le meta ottimizzate calcolate dall'audit.
 *
 * Se Rank Math è attivo si passa dai suoi filtri (così resta una sola fonte di
 * verità e non si generano tag duplicati); altrimenti il plugin stampa i tag
 * da sé.
 */
class MDI_Meta {

	/**
	 * Mappa post_id => meta ottimizzate.
	 *
	 * @var array|null
	 */
	private static $map = null;

	/**
	 * Aggancia i filtri.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::has_rank_math() ) {
			add_filter( 'rank_math/frontend/title', array( __CLASS__, 'filter_title' ) );
			add_filter( 'rank_math/frontend/description', array( __CLASS__, 'filter_description' ) );
			add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'filter_robots_array' ) );
		} else {
			add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_title' ), 20 );
			add_action( 'wp_head', array( __CLASS__, 'print_meta' ), 1 );
		}

		add_filter( 'wp_robots', array( __CLASS__, 'filter_wp_robots' ) );
		add_filter( 'get_the_excerpt', array( __CLASS__, 'filter_excerpt' ), 10, 2 );
		add_action( 'wp_head', array( __CLASS__, 'print_open_graph' ), 5 );
	}

	/**
	 * Rank Math è attivo?
	 *
	 * @return bool
	 */
	private static function has_rank_math() {
		return class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' );
	}

	/**
	 * Meta ottimizzate del contenuto corrente.
	 *
	 * @return array
	 */
	private static function current() {
		if ( null === self::$map ) {
			self::$map = array();
			foreach ( mdi_seo_geo_data( 'meta-map' ) as $row ) {
				if ( isset( $row['id'] ) ) {
					self::$map[ (int) $row['id'] ] = $row;
				}
			}
		}

		if ( ! is_singular() ) {
			return array();
		}

		$id = get_queried_object_id();

		return isset( self::$map[ $id ] ) ? self::$map[ $id ] : array();
	}

	/**
	 * Sostituisce il title.
	 *
	 * @param string $title Title corrente.
	 * @return string
	 */
	public static function filter_title( $title ) {
		$row = self::current();

		return ! empty( $row['title'] ) ? $row['title'] : $title;
	}

	/**
	 * Sostituisce la meta description.
	 *
	 * @param string $description Description corrente.
	 * @return string
	 */
	public static function filter_description( $description ) {
		$row = self::current();

		return ! empty( $row['description'] ) ? $row['description'] : $description;
	}

	/**
	 * Direttive robots complete (Rank Math).
	 *
	 * @param array $robots Direttive correnti.
	 * @return array
	 */
	public static function filter_robots_array( $robots ) {
		$row = self::current();

		if ( ! empty( $row['noindex'] ) ) {
			$robots['index'] = 'noindex';
			return $robots;
		}

		$robots['index']              = 'index';
		$robots['follow']             = 'follow';
		$robots['max-snippet']        = 'max-snippet:-1';
		$robots['max-image-preview']  = 'max-image-preview:large';
		$robots['max-video-preview']  = 'max-video-preview:-1';

		return $robots;
	}

	/**
	 * Stesse direttive per l'API wp_robots del core.
	 *
	 * @param array $robots Direttive correnti.
	 * @return array
	 */
	public static function filter_wp_robots( $robots ) {
		$row = self::current();

		if ( ! empty( $row['noindex'] ) ) {
			$robots['noindex'] = true;
			unset( $robots['index'] );
			return $robots;
		}

		$robots['max-image-preview'] = 'large';
		$robots['max-snippet']       = -1;
		$robots['max-video-preview'] = -1;

		return $robots;
	}

	/**
	 * Estratto di ripiego quando manca: alimenta archivi, feed e anteprime.
	 *
	 * @param string  $excerpt Estratto corrente.
	 * @param WP_Post $post    Post.
	 * @return string
	 */
	public static function filter_excerpt( $excerpt, $post = null ) {
		if ( ! empty( $excerpt ) || ! $post instanceof WP_Post ) {
			return $excerpt;
		}

		foreach ( mdi_seo_geo_data( 'meta-map' ) as $row ) {
			if ( isset( $row['id'] ) && (int) $row['id'] === $post->ID && ! empty( $row['excerpt'] ) ) {
				return $row['excerpt'];
			}
		}

		return $excerpt;
	}

	/**
	 * Tag base quando non c'è nessun plugin SEO attivo.
	 *
	 * @return void
	 */
	public static function print_meta() {
		$row = self::current();

		if ( ! empty( $row['description'] ) ) {
			printf( "<meta name=\"description\" content=\"%s\" />\n", esc_attr( $row['description'] ) );
		}

		if ( is_singular() ) {
			printf( "<link rel=\"canonical\" href=\"%s\" />\n", esc_url( get_permalink() ) );
		}
	}

	/**
	 * Open Graph e Twitter Card con immagine di ripiego.
	 *
	 * @return void
	 */
	public static function print_open_graph() {
		if ( self::has_rank_math() ) {
			return; // Rank Math li stampa già: evitiamo tag duplicati.
		}

		$row      = self::current();
		$title    = ! empty( $row['title'] ) ? $row['title'] : wp_get_document_title();
		$desc     = ! empty( $row['description'] ) ? $row['description'] : get_bloginfo( 'description' );
		$fallback = mdi_seo_geo_cfg( 'azienda.immagineFallback' );
		$image    = ( is_singular() && has_post_thumbnail() ) ? get_the_post_thumbnail_url( null, 'full' ) : $fallback;
		$url      = is_singular() ? get_permalink() : home_url( '/' );

		$tags = array(
			'og:locale'      => 'it_IT',
			'og:type'        => is_singular( 'post' ) ? 'article' : 'website',
			'og:title'       => $title,
			'og:description' => $desc,
			'og:url'         => $url,
			'og:site_name'   => get_bloginfo( 'name' ),
			'og:image'       => $image,
		);

		foreach ( $tags as $property => $content ) {
			if ( $content ) {
				printf( "<meta property=\"%s\" content=\"%s\" />\n", esc_attr( $property ), esc_attr( $content ) );
			}
		}

		printf( "<meta name=\"twitter:card\" content=\"summary_large_image\" />\n" );
		printf( "<meta name=\"twitter:title\" content=\"%s\" />\n", esc_attr( $title ) );
		printf( "<meta name=\"twitter:description\" content=\"%s\" />\n", esc_attr( $desc ) );

		if ( $image ) {
			printf( "<meta name=\"twitter:image\" content=\"%s\" />\n", esc_url( $image ) );
		}
	}
}
