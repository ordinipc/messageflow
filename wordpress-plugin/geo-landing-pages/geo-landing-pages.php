<?php
/**
 * Plugin Name:       Geo Landing Pages
 * Plugin URI:        https://chiaviitalia.it/
 * Description:       Crea landing page locali per città (es. /trapani/) con questionario guidato, contenuti reali, FAQ e SEO locale completa (meta tag, JSON-LD, sitemap).
 * Version:           1.4.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Chiavi Italia
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       geo-landing-pages
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GLP_VERSION', '1.4.1' );
define( 'GLP_FILE', __FILE__ );
define( 'GLP_PATH', plugin_dir_path( __FILE__ ) );
define( 'GLP_URL', plugin_dir_url( __FILE__ ) );
define( 'GLP_POST_TYPE', 'glp_landing' );
define( 'GLP_TAXONOMY', 'glp_service' );
define( 'GLP_META_PREFIX', '_glp_' );
define( 'GLP_CITY_PAGE_META', '_glp_city_page' );

require_once GLP_PATH . 'includes/class-glp-questionnaire.php';
require_once GLP_PATH . 'includes/class-glp-settings.php';
require_once GLP_PATH . 'includes/class-glp-meta.php';
require_once GLP_PATH . 'includes/class-glp-post-types.php';
require_once GLP_PATH . 'includes/class-glp-admin.php';
require_once GLP_PATH . 'includes/class-glp-content.php';
require_once GLP_PATH . 'includes/class-glp-seo.php';
require_once GLP_PATH . 'includes/class-glp-schema.php';
require_once GLP_PATH . 'includes/class-glp-shortcodes.php';
require_once GLP_PATH . 'includes/class-glp-ai.php';
require_once GLP_PATH . 'includes/class-glp-pages.php';

/**
 * Bootstrap del plugin.
 */
final class GLP_Plugin {

	/** @var GLP_Plugin|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		GLP_Post_Types::init();
		GLP_Meta::init();
		GLP_Admin::init();
		GLP_Content::init();
		GLP_SEO::init();
		GLP_Schema::init();
		GLP_Shortcodes::init();
		GLP_AI::init();
		GLP_Pages::init();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( __CLASS__, 'migrate_design' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ), 100 );
	}

	/**
	 * Allinea allo stile nuovo le impostazioni mai modificate.
	 *
	 * Tocca solo i valori ancora identici ai vecchi predefiniti: una
	 * palette scelta dall'utente non viene sovrascritta.
	 */
	public static function migrate_design() {
		if ( get_option( 'glp_design_migrated' ) ) {
			return;
		}
		update_option( 'glp_design_migrated', 1 );

		$salvate = get_option( GLP_Settings::OPTION, array() );
		if ( ! is_array( $salvate ) || empty( $salvate ) ) {
			return;
		}

		$vecchi = array(
			'color_primary' => '#f5d400',
			'color_dark'    => '#111111',
			'color_text'    => '#1d1d1f',
			'color_soft'    => '#f4f5f7',
			'radius'        => 14,
		);
		$nuovi = GLP_Settings::defaults();

		$cambiato = false;
		foreach ( $vecchi as $chiave => $vecchio ) {
			if ( isset( $salvate[ $chiave ] ) && (string) $salvate[ $chiave ] === (string) $vecchio ) {
				$salvate[ $chiave ] = $nuovi[ $chiave ];
				$cambiato           = true;
			}
		}

		if ( $cambiato ) {
			update_option( GLP_Settings::OPTION, $salvate );
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'geo-landing-pages', false, dirname( plugin_basename( GLP_FILE ) ) . '/languages' );
	}

	/**
	 * Versione di un file statico, basata sulla data di modifica.
	 *
	 * Evita che browser e plugin di cache continuino a servire un file
	 * vecchio quando il plugin viene aggiornato.
	 *
	 * @param string $relative Percorso relativo alla cartella del plugin.
	 * @return string
	 */
	public static function asset_version( $relative ) {
		$file = GLP_PATH . ltrim( $relative, '/' );
		$time = file_exists( $file ) ? filemtime( $file ) : 0;
		return $time ? GLP_VERSION . '.' . $time : GLP_VERSION;
	}

	public function enqueue_front_assets() {
		// Lo stile serve sulle landing e sulle pagine generate dal plugin,
		// così il sito resta coerente.
		if ( ! is_singular( GLP_POST_TYPE ) && ! GLP_Pages::is_generated_page() ) {
			return;
		}
		if ( ! GLP_Settings::get( 'design_enabled', 1 ) ) {
			return;
		}

		wp_enqueue_style( 'glp-frontend', GLP_URL . 'assets/css/frontend.css', array(), self::asset_version( 'assets/css/frontend.css' ) );
		wp_add_inline_style( 'glp-frontend', GLP_Settings::css_variables() );
		wp_enqueue_script( 'glp-frontend', GLP_URL . 'assets/js/frontend.js', array(), self::asset_version( 'assets/js/frontend.js' ), true );
	}

	/** Attivazione: registra le entità e ricostruisce le regole di rewrite. */
	public static function activate() {
		GLP_Post_Types::register_post_type();
		GLP_Post_Types::register_taxonomy();
		GLP_Post_Types::register_rewrite_rules();
		flush_rewrite_rules();
	}

	/** Disattivazione: pulisce le regole di rewrite. */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}

register_activation_hook( __FILE__, array( 'GLP_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GLP_Plugin', 'deactivate' ) );

GLP_Plugin::instance();
