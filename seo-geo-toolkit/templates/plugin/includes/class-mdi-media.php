<?php
/**
 * Immagini: alt mancanti, dimensioni esplicite, lazy loading.
 *
 * @package MDI_SEO_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Corregge a runtime i problemi delle immagini rilevati dall'audit.
 */
class MDI_Media {

	/**
	 * Aggancia i filtri.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'fix_images' ), 18 );
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'fix_attachment_alt' ), 10, 2 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'fix_images' ) );
	}

	/**
	 * Aggiunge alt, loading e decoding alle immagini del contenuto.
	 *
	 * @param string $content Contenuto.
	 * @return string
	 */
	public static function fix_images( $content ) {
		$titolo = is_singular() ? wp_strip_all_tags( get_the_title() ) : get_bloginfo( 'name' );
		$citta  = mdi_seo_geo_cfg( 'seo.cittaPrincipale', '' );
		$indice = 0;

		return preg_replace_callback(
			'/<img\b([^>]*)>/i',
			function ( $m ) use ( $titolo, $citta, &$indice ) {
				$attr = $m[1];
				++$indice;

				// Alt mancante o vuoto: si compone dal titolo della pagina.
				if ( ! preg_match( '/\balt=("|\')(?!\s*\1)[^"\']+\1/i', $attr ) ) {
					$attr = preg_replace( '/\balt=("|\')\s*\1/i', '', $attr );
					$alt  = $titolo . ( $indice > 1 ? ' - immagine ' . $indice : '' ) . ( $citta ? ' | ' . $citta : '' );
					$attr .= ' alt="' . esc_attr( $alt ) . '"';
				}

				// La prima immagine resta eager (di solito è l'LCP), le altre lazy.
				if ( ! preg_match( '/\bloading=/i', $attr ) ) {
					$attr .= 1 === $indice ? ' loading="eager" fetchpriority="high"' : ' loading="lazy"';
				}

				if ( ! preg_match( '/\bdecoding=/i', $attr ) ) {
					$attr .= ' decoding="async"';
				}

				return '<img' . $attr . '>';
			},
			$content
		);
	}

	/**
	 * Alt di ripiego per le immagini inserite dal tema.
	 *
	 * @param array   $attr       Attributi.
	 * @param WP_Post $attachment Allegato.
	 * @return array
	 */
	public static function fix_attachment_alt( $attr, $attachment ) {
		if ( ! empty( $attr['alt'] ) ) {
			return $attr;
		}

		$titolo = $attachment instanceof WP_Post ? $attachment->post_title : '';
		$citta  = mdi_seo_geo_cfg( 'seo.cittaPrincipale', '' );

		// I nomi file tecnici non fanno un buon alt: si preferisce il contesto.
		if ( ! $titolo || preg_match( '/^(img|dsc|photo|image|screenshot)[-_ ]?\d*$/i', $titolo ) ) {
			$titolo = is_singular() ? wp_strip_all_tags( get_the_title() ) : get_bloginfo( 'name' );
		}

		$attr['alt'] = trim( $titolo . ( $citta ? ' | ' . $citta : '' ) );

		return $attr;
	}
}
