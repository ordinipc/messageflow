<?php
/**
 * Plugin Name:       MDI SEO & GEO Booster
 * Plugin URI:        https://maxdigitalinnovation.it/
 * Description:       Correzione automatica dei problemi SEO e GEO rilevati dall'audit: dati strutturati, meta ottimizzate, link interni, immagini, llms.txt e direttive per i crawler AI.
 * Version:           2.6.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            seo-geo-toolkit
 * License:           GPL-2.0-or-later
 * Text Domain:       mdi-seo-geo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MDI_SEO_GEO_VERSION', '2.6.1' );
define( 'MDI_SEO_GEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'MDI_SEO_GEO_URL', plugin_dir_url( __FILE__ ) );

/**
 * Carica un file JSON dalla cartella data/ una sola volta per richiesta.
 *
 * @param string $name Nome del file senza estensione.
 * @return array
 */
function mdi_seo_geo_data( $name ) {
	static $cache = array();

	if ( isset( $cache[ $name ] ) ) {
		return $cache[ $name ];
	}

	$file = MDI_SEO_GEO_DIR . 'data/' . sanitize_file_name( $name ) . '.json';

	if ( ! file_exists( $file ) ) {
		$cache[ $name ] = array();
		return $cache[ $name ];
	}

	$decoded       = json_decode( file_get_contents( $file ), true );
	$cache[ $name ] = is_array( $decoded ) ? $decoded : array();

	return $cache[ $name ];
}

/**
 * Valore di configurazione con notazione a punti: mdi_seo_geo_cfg('azienda.telefono').
 *
 * @param string $path    Percorso della chiave.
 * @param mixed  $default Valore di ripiego.
 * @return mixed
 */
function mdi_seo_geo_cfg( $path, $default = '' ) {
	// I dati salvati dal gestionale hanno la precedenza sul file incluso nello
	// zip: quello è solo l istantanea del momento in cui il plugin è stato
	// generato, e invecchia appena si cambia qualcosa nelle impostazioni.
	// Niente cache statica: WordPress tiene già le opzioni in memoria, e così
	// il valore è aggiornato anche subito dopo un salvataggio.
	$dal_gestionale = get_option( 'mdi_seo_geo_config', array() );
	$node           = is_array( $dal_gestionale ) && $dal_gestionale
		? array_replace_recursive( mdi_seo_geo_data( 'config' ), $dal_gestionale )
		: mdi_seo_geo_data( 'config' );

	foreach ( explode( '.', $path ) as $key ) {
		if ( ! is_array( $node ) || ! isset( $node[ $key ] ) ) {
			return $default;
		}
		$node = $node[ $key ];
	}

	// I segnaposto non compilati non devono mai finire nel front-end.
	if ( is_string( $node ) && 0 === stripos( $node, 'DA_COMPILARE' ) ) {
		return $default;
	}

	return $node;
}

require_once MDI_SEO_GEO_DIR . 'includes/class-mdi-meta.php';
require_once MDI_SEO_GEO_DIR . 'includes/class-mdi-schema.php';
require_once MDI_SEO_GEO_DIR . 'includes/class-mdi-links.php';
require_once MDI_SEO_GEO_DIR . 'includes/class-mdi-media.php';
require_once MDI_SEO_GEO_DIR . 'includes/class-mdi-ai.php';
require_once MDI_SEO_GEO_DIR . 'includes/class-mdi-api.php';
require_once MDI_SEO_GEO_DIR . 'includes/class-mdi-admin.php';

add_action(
	'plugins_loaded',
	function () {
		MDI_Meta::init();
		MDI_Schema::init();
		MDI_Links::init();
		MDI_Media::init();
		MDI_AI::init();
		MDI_Api::init();
		MDI_Admin::init();
	}
);

/**
 * Alla attivazione si forza il rigenero delle regole di rewrite: servono per
 * gli endpoint /llms.txt e /ai.txt.
 */
register_activation_hook(
	__FILE__,
	function () {
		MDI_AI::add_rewrite_rules();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
