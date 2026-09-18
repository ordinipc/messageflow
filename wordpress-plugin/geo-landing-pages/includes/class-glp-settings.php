<?php
/**
 * Impostazioni globali del plugin.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Settings {

	const OPTION = 'glp_settings';

	/**
	 * Valori predefiniti.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Struttura URL: /{prefisso}/{citta}/{servizio}/
			'url_prefix'        => '',          // prefisso opzionale, es. "citta".

			// Identità.
			'brand'             => get_bloginfo( 'name' ),
			'servizio_default'  => '',
			'telefono'          => '',
			'whatsapp'          => '',
			'email'             => '',
			'partita_iva'       => '',

			// Modelli SEO.
			'title_template'       => '{servizio} a {citta}{sep}{brand}',
			'city_title_template'  => '{servizio_default} a {citta} ({provincia}){sep}{brand}',
			'desc_template'     => '{servizio} a {citta} ({provincia}): {perche_primo}. Preventivo rapido, chiama {telefono}.',
			'h1_template'       => '{servizio} a {citta}',
			'intro_template'    => "Cerchi un servizio di {servizio} a {citta}? {brand} opera a {citta} e in provincia di {provincia}{anni_frase}{interventi_frase}.",

			// Dati strutturati.
			'schema_enabled'    => 1,
			'schema_type'       => 'LocalBusiness',
			'breadcrumbs'       => 1,

			// Assistente AI (Google Gemini).
			'ai_enabled'        => 0,
			'gemini_key'        => '',
			'gemini_model'      => 'gemini-2.5-flash',
			'ai_style'          => '',
			'ai_temperature'    => '0.4',

			// Qualità.
			'min_score'         => 60,
			'template_mode'     => 'filter',    // filter | template
			'archive_title'     => __( 'Dove operiamo', 'geo-landing-pages' ),
		);
	}

	/**
	 * Tutte le impostazioni.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Singola impostazione.
	 *
	 * @param string $key     Chiave.
	 * @param mixed  $fallback Valore di riserva.
	 * @return mixed
	 */
	public static function get( $key, $fallback = '' ) {
		$all = self::all();
		return isset( $all[ $key ] ) && '' !== $all[ $key ] ? $all[ $key ] : $fallback;
	}

	/**
	 * Sanitizza il salvataggio delle impostazioni.
	 *
	 * @param array $input Valori inviati.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$out      = self::all();
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();

		$text_keys = array( 'brand', 'servizio_default', 'telefono', 'whatsapp', 'partita_iva', 'schema_type', 'archive_title', 'gemini_model' );
		foreach ( $text_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
			}
		}

		if ( isset( $input['email'] ) ) {
			$out['email'] = sanitize_email( wp_unslash( $input['email'] ) );
		}

		$template_keys = array( 'title_template', 'city_title_template', 'desc_template', 'h1_template', 'intro_template', 'ai_style' );
		foreach ( $template_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_textarea_field( wp_unslash( $input[ $key ] ) );
			}
		}

		$out['template_mode'] = isset( $input['template_mode'] ) && 'template' === $input['template_mode'] ? 'template' : 'filter';

		// Il prefisso può contenere più segmenti: sanitizzo ogni pezzo.
		$prefix = isset( $input['url_prefix'] ) ? wp_unslash( $input['url_prefix'] ) : '';
		$parts  = array_filter( array_map( 'sanitize_title', explode( '/', (string) $prefix ) ) );
		$out['url_prefix'] = implode( '/', $parts );

		$out['ai_enabled']     = empty( $input['ai_enabled'] ) ? 0 : 1;
		$out['schema_enabled'] = empty( $input['schema_enabled'] ) ? 0 : 1;

		// La chiave API: il campo mascherato non deve sovrascrivere quella salvata.
		if ( isset( $input['gemini_key'] ) ) {
			$chiave = trim( sanitize_text_field( wp_unslash( $input['gemini_key'] ) ) );
			if ( false === strpos( $chiave, '•' ) ) {
				$out['gemini_key'] = $chiave;
			}
		}

		if ( isset( $input['ai_temperature'] ) ) {
			$temp = (float) str_replace( ',', '.', (string) $input['ai_temperature'] );
			$out['ai_temperature'] = (string) max( 0, min( 1, $temp ) );
		}
		$out['breadcrumbs']    = empty( $input['breadcrumbs'] ) ? 0 : 1;

		$score = isset( $input['min_score'] ) ? (int) $input['min_score'] : $defaults['min_score'];
		$out['min_score'] = max( 0, min( 100, $score ) );

		// La struttura URL è cambiata: le regole vanno ricostruite.
		update_option( 'glp_flush_needed', 1 );

		return $out;
	}

	/**
	 * Tipi di schema.org selezionabili.
	 *
	 * @return array
	 */
	public static function schema_types() {
		return apply_filters(
			'glp_schema_types',
			array(
				'LocalBusiness'      => 'LocalBusiness (generico)',
				'HomeAndConstructionBusiness' => 'HomeAndConstructionBusiness (edilizia/impianti)',
				'Locksmith'          => 'Locksmith (fabbro/serrature)',
				'ProfessionalService' => 'ProfessionalService',
				'AutomotiveBusiness' => 'AutomotiveBusiness',
				'MedicalClinic'      => 'MedicalClinic',
				'Dentist'            => 'Dentist',
				'LegalService'       => 'LegalService',
				'RealEstateAgent'    => 'RealEstateAgent',
				'Store'              => 'Store',
				'Plumber'            => 'Plumber',
				'Electrician'        => 'Electrician',
				'MovingCompany'      => 'MovingCompany',
				'HVACBusiness'       => 'HVACBusiness',
			)
		);
	}
}
