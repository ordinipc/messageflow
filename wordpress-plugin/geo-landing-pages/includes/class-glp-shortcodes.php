<?php
/**
 * Shortcode del plugin.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Shortcodes {

	public static function init() {
		add_shortcode( 'glp_citta', array( __CLASS__, 'cities' ) );
		add_shortcode( 'glp_servizi', array( __CLASS__, 'services' ) );
		add_shortcode( 'glp_faq', array( __CLASS__, 'faq' ) );
		add_shortcode( 'glp_breadcrumbs', array( __CLASS__, 'breadcrumbs' ) );
		add_shortcode( 'glp_sezioni', array( __CLASS__, 'sections' ) );
	}

	/**
	 * Elenco di tutte le città: [glp_citta colonne="3" limite="200"]
	 *
	 * @param array $atts Attributi.
	 * @return string
	 */
	public static function cities( $atts ) {
		$atts = shortcode_atts(
			array(
				'colonne' => 3,
				'limite'  => 200,
				'titolo'  => '',
			),
			$atts,
			'glp_citta'
		);

		$cities = GLP_Post_Types::cities( (int) $atts['limite'] );
		if ( empty( $cities ) ) {
			return '';
		}

		$html = '<div class="glp-index" style="--glp-cols:' . (int) $atts['colonne'] . '">';
		if ( '' !== $atts['titolo'] ) {
			$html .= '<h2 class="glp-index__title">' . esc_html( $atts['titolo'] ) . '</h2>';
		}
		$html .= '<ul class="glp-index__list">';
		foreach ( $cities as $city ) {
			$prov  = GLP_Meta::get( $city->ID, 'provincia' );
			$html .= '<li><a href="' . esc_url( get_permalink( $city ) ) . '">'
				. esc_html( $city->post_title )
				. ( '' !== $prov ? ' <span class="glp-index__prov">(' . esc_html( $prov ) . ')</span>' : '' )
				. '</a></li>';
		}
		return $html . '</ul></div>';
	}

	/**
	 * Servizi di una città: [glp_servizi citta="123"]
	 *
	 * @param array $atts Attributi.
	 * @return string
	 */
	public static function services( $atts ) {
		$atts = shortcode_atts( array( 'citta' => 0 ), $atts, 'glp_servizi' );

		$city_id = (int) $atts['citta'];
		if ( ! $city_id ) {
			$city    = GLP_Post_Types::city_post( get_the_ID() );
			$city_id = $city ? $city->ID : 0;
		}
		if ( ! $city_id ) {
			return '';
		}

		$services = GLP_Post_Types::services_of( $city_id );
		if ( empty( $services ) ) {
			return '';
		}

		$html = '<ul class="glp-index__list glp-index__list--services">';
		foreach ( $services as $service ) {
			$html .= '<li><a href="' . esc_url( get_permalink( $service ) ) . '">' . esc_html( $service->post_title ) . '</a></li>';
		}
		return $html . '</ul>';
	}

	/**
	 * FAQ della pagina corrente: [glp_faq]
	 *
	 * @param array $atts Attributi.
	 * @return string
	 */
	public static function faq( $atts ) {
		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'glp_faq' );
		$post_id = (int) $atts['id'] ? (int) $atts['id'] : get_the_ID();
		return $post_id ? GLP_Content::section_faq( $post_id ) : '';
	}

	/**
	 * Briciole di pane: [glp_breadcrumbs]
	 *
	 * @return string
	 */
	public static function breadcrumbs() {
		$post_id = get_the_ID();
		return $post_id ? GLP_SEO::breadcrumbs_html( $post_id ) : '';
	}

	/**
	 * Tutte le sezioni generate: [glp_sezioni]
	 *
	 * @return string
	 */
	public static function sections() {
		$post_id = get_the_ID();
		return $post_id ? GLP_Content::sections( $post_id ) : '';
	}
}
