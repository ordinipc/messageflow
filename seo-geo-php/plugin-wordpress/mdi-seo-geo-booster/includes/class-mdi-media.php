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

				// width e height espliciti: senza, il browser non sa quanto
				// spazio riservare e la pagina salta mentre carica (CLS).
				// Le misure si prendono dall allegato, non si inventano: se
				// non si trova l immagine in libreria si lascia com e.
				if ( ! preg_match( '/\bwidth=/i', $attr ) && ! preg_match( '/\bheight=/i', $attr ) ) {
					$misure = self::misure_da_src( $attr );

					if ( $misure ) {
						$attr .= ' width="' . (int) $misure[0] . '" height="' . (int) $misure[1] . '"';
					}
				}

				return '<img' . $attr . '>';
			},
			$content
		);
	}

	/**
	 * Larghezza e altezza di un immagine, prese dalla libreria media.
	 *
	 * Non si indovinano: si cerca l allegato dall indirizzo del file e si
	 * leggono le sue misure. Se l immagine non e in libreria - una
	 * ridimensionata, una caricata altrove - non si scrive niente, che e
	 * meglio di due numeri sbagliati.
	 *
	 * @param string $attr Attributi del tag img.
	 * @return array|null array( larghezza, altezza ) oppure null.
	 */
	private static function misure_da_src( $attr ) {
		if ( ! preg_match( '/\bsrc=("|\')([^"\']+)\1/i', $attr, $trovato ) ) {
			return null;
		}

		$src = $trovato[2];

		// Le varianti generate da WordPress finiscono con -1024x768: si
		// tolgono per ritrovare il file originale in libreria.
		$originale = preg_replace( '/-\d+x\d+(\.[a-z]{3,4})$/i', '$1', $src );

		static $cache = array();

		if ( isset( $cache[ $originale ] ) ) {
			return $cache[ $originale ];
		}

		$id = attachment_url_to_postid( $originale );

		if ( ! $id ) {
			$cache[ $originale ] = null;

			return null;
		}

		$dati = wp_get_attachment_metadata( $id );

		// Se il tag punta a una variante, valgono le misure di quella.
		if ( preg_match( '/-(\d+)x(\d+)\.[a-z]{3,4}$/i', $src, $variante ) ) {
			$cache[ $originale ] = array( (int) $variante[1], (int) $variante[2] );

			return $cache[ $originale ];
		}

		$cache[ $originale ] = ( ! empty( $dati['width'] ) && ! empty( $dati['height'] ) )
			? array( (int) $dati['width'], (int) $dati['height'] )
			: null;

		return $cache[ $originale ];
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
