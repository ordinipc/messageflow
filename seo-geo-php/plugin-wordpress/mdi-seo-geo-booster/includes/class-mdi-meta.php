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

		$id  = get_queried_object_id();
		$row = self::$map[ $id ] ?? array();

		return self::eDiQuestoContenuto( $id, $row ) ? $row : array();
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
			if ( isset( $row['id'] ) && (int) $row['id'] === $post->ID && ! empty( $row['excerpt'] )
				&& self::eDiQuestoContenuto( $post->ID, $row ) ) {
				return $row['excerpt'];
			}
		}

		return $excerpt;
	}

	/**
	 * Questa riga parla davvero di questo contenuto?
	 *
	 * La mappa si cerca per numero, e i numeri si ripetono da un sito
	 * all altro: la riga numero 10 di un altra installazione finisce sulla
	 * pagina numero 10 di questa. E successo davvero, con un piano di prova
	 * finito dentro allo zip: la home di un sito vero ha stampato per ore
	 * il title «Articolo di prova numero 10 sulla realizzazione siti web».
	 *
	 * Lo slug e la controprova: ce l ha ogni riga e ce l ha ogni contenuto,
	 * e due contenuti diversi non hanno lo stesso. Quando non combaciano, la
	 * riga non e di questa pagina e si lascia stare: meglio il title del tema
	 * che quello di un altro sito.
	 *
	 * Se lo slug viene cambiato in WordPress dopo l esportazione, le meta
	 * smettono di applicarsi finche il piano non viene rifatto. E la
	 * direzione giusta in cui sbagliare.
	 *
	 * @param int   $id  Identificativo del contenuto.
	 * @param array $row Riga della mappa.
	 * @return bool
	 */
	private static function eDiQuestoContenuto( $id, array $row ) {
		if ( ! $row ) {
			return false;
		}

		$atteso = trim( (string) ( $row['slug'] ?? '' ) );

		if ( '' === $atteso ) {
			return true;
		}

		$vero = (string) get_post_field( 'post_name', (int) $id );

		return '' === $vero || $vero === $atteso;
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
			// Quando il gestionale ha allineato la canonica a quella che
			// Google ha gia scelto, e quella che va stampata: ristampare
			// l indirizzo della pagina stessa vorrebbe dire continuare a
			// dichiarare originale un contenuto che Google considera un
			// doppione, cioe il problema che si stava togliendo.
			$scelta = (string) get_post_meta( get_queried_object_id(), 'rank_math_canonical_url', true );

			printf(
				"<link rel=\"canonical\" href=\"%s\" />\n",
				esc_url( '' !== $scelta ? $scelta : get_permalink() )
			);
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
