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

			// Aspetto grafico.
			'design_enabled'    => 1,
			'hero_mode'         => 'h1',        // off | h1 | notitle
			'color_primary'     => '#ffd400',
			'color_dark'        => '#0d0d0d',
			'color_text'        => '#111111',
			'color_soft'        => '#f5f5f6',
			'radius'            => 5,
			'full_bleed'        => 0,

			// Codice personalizzato valido su tutte le landing.
			'custom_css'        => '',
			'custom_js'         => '',

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

		// Codice globale: modificabile solo da chi può pubblicare HTML non filtrato.
		if ( current_user_can( 'unfiltered_html' ) ) {
			foreach ( array( 'custom_css', 'custom_js' ) as $chiave ) {
				if ( isset( $input[ $chiave ] ) ) {
					$out[ $chiave ] = (string) wp_unslash( $input[ $chiave ] );
				}
			}
		}

		$out['design_enabled'] = empty( $input['design_enabled'] ) ? 0 : 1;
		$out['full_bleed']     = empty( $input['full_bleed'] ) ? 0 : 1;
		$out['ai_enabled']     = empty( $input['ai_enabled'] ) ? 0 : 1;

		$modi = array( 'off', 'h1', 'notitle' );
		if ( isset( $input['hero_mode'] ) && in_array( $input['hero_mode'], $modi, true ) ) {
			$out['hero_mode'] = $input['hero_mode'];
		}

		foreach ( array( 'color_primary', 'color_dark', 'color_text', 'color_soft' ) as $colore ) {
			if ( isset( $input[ $colore ] ) ) {
				$valore = sanitize_hex_color( trim( (string) $input[ $colore ] ) );
				if ( $valore ) {
					$out[ $colore ] = $valore;
				}
			}
		}

		if ( isset( $input['radius'] ) ) {
			$out['radius'] = max( 0, min( 40, (int) $input['radius'] ) );
		}
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
	 * Variabili CSS derivate dalle impostazioni di aspetto.
	 *
	 * @return string Dichiarazioni CSS.
	 */
	public static function css_variables() {
		$s = self::all();

		$primary = $s['color_primary'];
		$dark    = $s['color_dark'];

		$vars = array(
			'--glp-accent'      => $primary,
			'--glp-on-accent'   => self::readable_text( $primary ),
			'--glp-dark'        => $dark,
			'--glp-on-dark'     => self::readable_text( $dark ),
			'--glp-text'        => $s['color_text'],
			'--glp-soft'        => $s['color_soft'],
			'--glp-radius'      => (int) $s['radius'] . 'px',
		);

		$out = '';
		foreach ( $vars as $nome => $valore ) {
			$out .= $nome . ':' . $valore . ';';
		}
		$css = ':root{' . $out . '}';

		// Bande a tutta larghezza: l'intestazione e la CTA escono dal contenitore.
		if ( ! empty( $s['full_bleed'] ) ) {
			$css .= '.glp-hero,.glp-section--cta{margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);'
				. 'border-radius:0;padding-left:max(1.25rem,calc(50vw - 36rem));padding-right:max(1.25rem,calc(50vw - 36rem));}';
		}

		return $css;
	}

	/**
	 * Colore di testo leggibile sopra uno sfondo dato.
	 *
	 * @param string $hex Colore di sfondo.
	 * @return string
	 */
	public static function readable_text( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return '#111111';
		}
		// Luminanza relativa approssimata (formula WCAG semplificata).
		$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 4, 2 ) ) / 255;

		foreach ( array( 'r', 'g', 'b' ) as $canale ) {
			$v = $$canale;
			$$canale = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		$luminanza = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

		return $luminanza > 0.45 ? '#111111' : '#ffffff';
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
