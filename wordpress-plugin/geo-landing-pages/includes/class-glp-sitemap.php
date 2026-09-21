<?php
/**
 * Sitemap dedicata alla sezione città.
 *
 * Una sitemap per ogni città più un indice che le raccoglie, così ogni
 * zona si misura per conto suo in Search Console senza doverla staccare
 * dal dominio principale.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Sitemap {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rules' ), 21 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'output' ), 0 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots' ), 20, 2 );
	}

	/** Regole degli indirizzi delle sitemap. */
	public static function rules() {
		add_rewrite_rule( '^sitemap-citta\.xml$', 'index.php?glp_sitemap=index', 'top' );
		add_rewrite_rule( '^sitemap-citta-([^/]+)\.xml$', 'index.php?glp_sitemap=$matches[1]', 'top' );
	}

	/**
	 * Variabile di query.
	 *
	 * @param array $vars Variabili.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'glp_sitemap';
		return $vars;
	}

	/**
	 * Indirizzo dell'indice.
	 *
	 * @return string
	 */
	public static function index_url() {
		return home_url( '/sitemap-citta.xml' );
	}

	/**
	 * Indirizzo della sitemap di una città.
	 *
	 * @param WP_Post $citta Città.
	 * @return string
	 */
	public static function city_url( $citta ) {
		return home_url( '/sitemap-citta-' . $citta->post_name . '.xml' );
	}

	/**
	 * Pagine indicizzabili di una città, la città compresa.
	 *
	 * @param WP_Post $citta Città.
	 * @return WP_Post[]
	 */
	public static function city_entries( $citta ) {
		$voci = array();

		if ( GLP_Meta::is_indexable( $citta->ID ) ) {
			$voci[] = $citta;
		}

		$figli = get_posts(
			array(
				'post_type'      => GLP_POST_TYPE,
				'post_parent'    => $citta->ID,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $figli as $figlio ) {
			if ( GLP_Meta::is_indexable( $figlio->ID ) ) {
				$voci[] = $figlio;
			}
		}

		return $voci;
	}

	/** Stampa la sitemap richiesta. */
	public static function output() {
		$richiesta = get_query_var( 'glp_sitemap' );
		if ( '' === $richiesta || null === $richiesta ) {
			return;
		}

		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

		if ( 'index' === $richiesta ) {
			self::print_index();
		} else {
			$citta = get_page_by_path( sanitize_title( $richiesta ), OBJECT, GLP_POST_TYPE );
			if ( ! $citta || 0 !== (int) $citta->post_parent ) {
				status_header( 404 );
				echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
				exit;
			}
			self::print_city( $citta );
		}

		exit;
	}

	/** Indice di tutte le città. */
	private static function print_index() {
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( GLP_Post_Types::cities( 500 ) as $citta ) {
			$voci = self::city_entries( $citta );
			if ( empty( $voci ) ) {
				continue; // Una città senza pagine indicizzabili non va dichiarata.
			}

			$ultima = '';
			foreach ( $voci as $voce ) {
				$data = get_post_modified_time( 'c', true, $voce );
				if ( $data && $data > $ultima ) {
					$ultima = $data;
				}
			}

			echo "\t<sitemap>\n";
			echo "\t\t<loc>" . esc_url( self::city_url( $citta ) ) . "</loc>\n";
			if ( '' !== $ultima ) {
				echo "\t\t<lastmod>" . esc_html( $ultima ) . "</lastmod>\n";
			}
			echo "\t</sitemap>\n";
		}

		echo '</sitemapindex>';
	}

	/**
	 * Sitemap di una singola città.
	 *
	 * @param WP_Post $citta Città.
	 */
	private static function print_city( $citta ) {
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( self::city_entries( $citta ) as $voce ) {
			$data = get_post_modified_time( 'c', true, $voce );

			echo "\t<url>\n";
			echo "\t\t<loc>" . esc_url( get_permalink( $voce ) ) . "</loc>\n";
			if ( $data ) {
				echo "\t\t<lastmod>" . esc_html( $data ) . "</lastmod>\n";
			}
			// La pagina città è il punto d'ingresso della zona.
			echo "\t\t<priority>" . ( (int) $voce->ID === (int) $citta->ID ? '0.8' : '0.6' ) . "</priority>\n";
			echo "\t</url>\n";
		}

		echo '</urlset>';
	}

	/**
	 * Dichiara la sitemap nel robots.txt.
	 *
	 * @param string $output Contenuto.
	 * @param bool   $public Sito visibile ai motori.
	 * @return string
	 */
	public static function robots( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}

		$citta = GLP_Post_Types::cities( 1 );
		if ( empty( $citta ) ) {
			return $output;
		}

		return $output . "\nSitemap: " . esc_url_raw( self::index_url() ) . "\n";
	}
}
