<?php
/**
 * Custom post type, tassonomia servizi e struttura degli URL.
 *
 * Struttura: /{prefisso}/{citta}/{servizio}/
 *  - post senza genitore  => pagina città  (/trapani/)
 *  - post con genitore    => pagina servizio (/trapani/duplicazione-chiavi/)
 *
 * Le regole di rewrite vengono costruite con l'elenco esatto degli slug città
 * esistenti: /trapani/ risponde solo per le città create e non intercetta le
 * altre pagine del sito.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Post_Types {

	/** Numero massimo di slug per singola regola di rewrite. */
	const CHUNK = 200;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ), 20 );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );

		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'request', array( __CLASS__, 'resolve_request' ) );
		add_filter( 'post_type_link', array( __CLASS__, 'permalink' ), 10, 2 );

		// Ogni variazione dell'elenco richiede regole aggiornate.
		add_action( 'save_post_' . GLP_POST_TYPE, array( __CLASS__, 'schedule_flush' ) );
		add_action( 'deleted_post', array( __CLASS__, 'schedule_flush_on_delete' ) );
	}

	/**
	 * Registra il post type delle landing page (gerarchico: città > servizio).
	 */
	public static function register_post_type() {
		$labels = array(
			'name'                  => __( 'Landing locali', 'geo-landing-pages' ),
			'singular_name'         => __( 'Landing locale', 'geo-landing-pages' ),
			'add_new'               => __( 'Aggiungi pagina', 'geo-landing-pages' ),
			'add_new_item'          => __( 'Aggiungi città o servizio', 'geo-landing-pages' ),
			'edit_item'             => __( 'Modifica landing locale', 'geo-landing-pages' ),
			'new_item'              => __( 'Nuova landing locale', 'geo-landing-pages' ),
			'view_item'             => __( 'Vedi landing locale', 'geo-landing-pages' ),
			'search_items'          => __( 'Cerca landing locali', 'geo-landing-pages' ),
			'not_found'             => __( 'Nessuna landing locale trovata', 'geo-landing-pages' ),
			'not_found_in_trash'    => __( 'Nessuna landing locale nel cestino', 'geo-landing-pages' ),
			'all_items'             => __( 'Città e servizi', 'geo-landing-pages' ),
			'parent_item_colon'     => __( 'Città di appartenenza:', 'geo-landing-pages' ),
			'menu_name'             => __( 'Landing locali', 'geo-landing-pages' ),
		);

		register_post_type(
			GLP_POST_TYPE,
			array(
				'labels'          => $labels,
				'public'          => true,
				'hierarchical'    => true,
				'has_archive'     => false,
				'show_in_rest'    => true,
				'menu_icon'       => 'dashicons-location-alt',
				'menu_position'   => 21,
				'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author', 'page-attributes', 'custom-fields' ),
				'rewrite'         => false, // Gli URL li costruiamo noi.
				'query_var'       => false,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Tassonomia dei servizi: raggruppa lo stesso servizio tra città diverse
	 * (serve ai link incrociati, non compare negli URL).
	 */
	public static function register_taxonomy() {
		register_taxonomy(
			GLP_TAXONOMY,
			GLP_POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Tipi di servizio', 'geo-landing-pages' ),
					'singular_name' => __( 'Tipo di servizio', 'geo-landing-pages' ),
					'add_new_item'  => __( 'Aggiungi tipo di servizio', 'geo-landing-pages' ),
					'menu_name'     => __( 'Tipi di servizio', 'geo-landing-pages' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => false,
				'query_var'         => false,
			)
		);
	}

	/**
	 * Registra la variabile di query usata per risolvere il percorso.
	 *
	 * @param array $vars Variabili.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'glp_path';
		return $vars;
	}

	/**
	 * Prefisso URL normalizzato (con slash finale o stringa vuota).
	 *
	 * @return string
	 */
	public static function prefix() {
		$prefix = trim( (string) GLP_Settings::get( 'url_prefix', '' ), '/' );
		return '' === $prefix ? '' : $prefix . '/';
	}

	/**
	 * Costruisce le regole di rewrite dagli slug città esistenti.
	 */
	public static function register_rewrite_rules() {
		$slugs = self::city_slugs();
		if ( empty( $slugs ) ) {
			return;
		}
		$prefix = self::prefix();

		foreach ( self::alternation( $slugs ) as $chunk ) {
			// /citta/servizio/
			add_rewrite_rule(
				'^' . $prefix . '(' . $chunk . ')/([^/]+)/?$',
				'index.php?glp_path=$matches[1]/$matches[2]',
				'top'
			);
			// /citta/ con paginazione dell'hub.
			add_rewrite_rule(
				'^' . $prefix . '(' . $chunk . ')/page/([0-9]{1,})/?$',
				'index.php?glp_path=$matches[1]&paged=$matches[2]',
				'top'
			);
			// /citta/
			add_rewrite_rule(
				'^' . $prefix . '(' . $chunk . ')/?$',
				'index.php?glp_path=$matches[1]',
				'top'
			);
		}
	}

	/**
	 * Trasforma il percorso in una query su un post preciso.
	 *
	 * Risolvere qui (invece di affidarsi al matching per slug) evita le
	 * ambiguità fra servizi omonimi in città diverse.
	 *
	 * @param array $vars Variabili di query.
	 * @return array
	 */
	public static function resolve_request( $vars ) {
		if ( empty( $vars['glp_path'] ) ) {
			return $vars;
		}

		$path = trim( (string) $vars['glp_path'], '/' );
		unset( $vars['glp_path'] );

		$post = get_page_by_path( $path, OBJECT, GLP_POST_TYPE );
		if ( ! $post ) {
			// Slug presente nelle regole ma post non più esistente: 404 esplicito.
			return array(
				'post_type' => GLP_POST_TYPE,
				'name'      => 'glp-404-' . md5( $path ),
			);
		}

		$resolved = array(
			'post_type' => GLP_POST_TYPE,
			'p'         => $post->ID,
		);
		if ( isset( $vars['paged'] ) ) {
			$resolved['paged'] = $vars['paged'];
		}
		if ( isset( $vars['preview'] ) ) {
			$resolved['preview'] = $vars['preview'];
		}

		return $resolved;
	}

	/**
	 * Slug delle città (post di primo livello), in cache.
	 *
	 * @return array
	 */
	public static function city_slugs() {
		$cached = get_transient( 'glp_city_slugs' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$slugs = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_name FROM {$wpdb->posts}
				 WHERE post_type = %s
				   AND post_parent = 0
				   AND post_status IN ('publish','draft','pending','private','future')
				   AND post_name <> ''",
				GLP_POST_TYPE
			)
		);
		$slugs = array_values( array_unique( array_filter( (array) $slugs ) ) );

		set_transient( 'glp_city_slugs', $slugs, DAY_IN_SECONDS );
		return $slugs;
	}

	/**
	 * Spezza gli slug in gruppi e li trasforma in alternative regex.
	 *
	 * @param array $slugs Slug.
	 * @return array
	 */
	private static function alternation( $slugs ) {
		$slugs  = array_map(
			static function ( $slug ) {
				return preg_quote( $slug, '#' );
			},
			array_filter( (array) $slugs )
		);
		$out = array();
		foreach ( array_chunk( $slugs, self::CHUNK ) as $chunk ) {
			$out[] = implode( '|', $chunk );
		}
		return $out;
	}

	/**
	 * Permalink: /{prefisso}/{citta}/{servizio}/
	 *
	 * @param string  $link Link originale.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function permalink( $link, $post ) {
		$post = get_post( $post );
		if ( ! $post || GLP_POST_TYPE !== $post->post_type ) {
			return $link;
		}
		// Gli stati non pubblici mantengono il link nativo, così l'anteprima funziona.
		if ( ! in_array( $post->post_status, array( 'publish', 'private', 'future' ), true ) || '' === $post->post_name ) {
			return $link;
		}

		$segments = array( $post->post_name );
		$parent   = (int) $post->post_parent;
		$guard    = 0;
		while ( $parent && $guard < 5 ) {
			$parent_post = get_post( $parent );
			if ( ! $parent_post || GLP_POST_TYPE !== $parent_post->post_type ) {
				break;
			}
			array_unshift( $segments, $parent_post->post_name );
			$parent = (int) $parent_post->post_parent;
			$guard++;
		}

		return user_trailingslashit( home_url( '/' . self::prefix() . implode( '/', $segments ) ) );
	}

	/**
	 * La pagina è una città (primo livello)?
	 *
	 * @param int|WP_Post $post Post.
	 * @return bool
	 */
	public static function is_city( $post = null ) {
		$post = get_post( $post );
		return $post && GLP_POST_TYPE === $post->post_type && 0 === (int) $post->post_parent;
	}

	/**
	 * Post città di riferimento (se stesso, oppure il genitore).
	 *
	 * @param int|WP_Post $post Post.
	 * @return WP_Post|null
	 */
	public static function city_post( $post = null ) {
		$post = get_post( $post );
		if ( ! $post || GLP_POST_TYPE !== $post->post_type ) {
			return null;
		}
		if ( 0 === (int) $post->post_parent ) {
			return $post;
		}
		$parent = get_post( $post->post_parent );
		return $parent && GLP_POST_TYPE === $parent->post_type ? $parent : null;
	}

	/**
	 * Servizi pubblicati di una città.
	 *
	 * @param int $city_id ID città.
	 * @return WP_Post[]
	 */
	public static function services_of( $city_id ) {
		return get_posts(
			array(
				'post_type'      => GLP_POST_TYPE,
				'post_parent'    => (int) $city_id,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			)
		);
	}

	/**
	 * Tutte le città pubblicate.
	 *
	 * @param int $limit Numero massimo.
	 * @return WP_Post[]
	 */
	public static function cities( $limit = 500 ) {
		return get_posts(
			array(
				'post_type'      => GLP_POST_TYPE,
				'post_parent'    => 0,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $limit,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/** Segna che le regole vanno ricostruite. */
	public static function schedule_flush() {
		delete_transient( 'glp_city_slugs' );
		update_option( 'glp_flush_needed', 1 );
	}

	/**
	 * Come sopra, ma solo se il post cancellato era una landing.
	 *
	 * @param int $post_id ID.
	 */
	public static function schedule_flush_on_delete( $post_id ) {
		if ( GLP_POST_TYPE === get_post_type( $post_id ) ) {
			self::schedule_flush();
		}
	}

	/** Esegue il flush differito una sola volta. */
	public static function maybe_flush() {
		if ( ! get_option( 'glp_flush_needed' ) ) {
			return;
		}
		delete_option( 'glp_flush_needed' );
		flush_rewrite_rules( false );
	}
}
