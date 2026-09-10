<?php
/**
 * Estrazione di elementi dal markup dei contenuti WordPress.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

/**
 * Titoli, immagini, link e paragrafi ricavati dall'HTML salvato nel database.
 */
class Html {

	/**
	 * Testo semplice senza tag, script e stili.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	public static function stripTags( $html ) {
		$s = preg_replace( '#<(script|style)\b[\s\S]*?</\1>#i', ' ', (string) $html );
		$s = preg_replace( '/<!--[\s\S]*?-->/', ' ', (string) $s );
		$s = preg_replace( '/<[^>]+>/', ' ', (string) $s );
		$s = html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( preg_replace( '/\s+/u', ' ', $s ) );
	}

	/**
	 * Titoli H1-H6 con livello, testo e posizione.
	 *
	 * @param string $html Markup.
	 * @return array
	 */
	public static function headings( $html ) {
		$out = array();

		if ( preg_match_all( '#<h([1-6])(\s[^>]*)?>([\s\S]*?)</h\1>#i', (string) $html, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m as $match ) {
				$out[] = array(
					'livello'  => (int) $match[1][0],
					'testo'    => self::stripTags( $match[3][0] ),
					'raw'      => $match[0][0],
					'posizione' => $match[0][1],
				);
			}
		}

		return $out;
	}

	/**
	 * Immagini con i loro attributi.
	 *
	 * @param string $html Markup.
	 * @return array
	 */
	public static function images( $html ) {
		$out = array();

		if ( ! preg_match_all( '/<img\b[^>]*>/i', (string) $html, $m ) ) {
			return $out;
		}

		foreach ( $m[0] as $tag ) {
			$attr = static function ( $nome ) use ( $tag ) {
				return preg_match( '/' . $nome . '\s*=\s*["\']([^"\']*)["\']/i', $tag, $x ) ? $x[1] : '';
			};

			$out[] = array(
				'raw'      => $tag,
				'src'      => $attr( 'src' ),
				'alt'      => $attr( 'alt' ),
				'width'    => $attr( 'width' ),
				'height'   => $attr( 'height' ),
				'loading'  => $attr( 'loading' ),
				'ha_alt'   => (bool) preg_match( '/alt\s*=\s*["\'][^"\']+["\']/i', $tag ),
			);
		}

		return $out;
	}

	/**
	 * Link con destinazione classificata (interna, esterna, ancora, mail).
	 *
	 * @param string $html Markup.
	 * @param string $host Dominio del sito.
	 * @return array
	 */
	public static function links( $html, $host ) {
		$out = array();

		if ( ! preg_match_all( '#<a\b([^>]*)>([\s\S]*?)</a>#i', (string) $html, $m, PREG_SET_ORDER ) ) {
			return $out;
		}

		foreach ( $m as $match ) {
			$attr   = $match[1];
			$href   = preg_match( '/href\s*=\s*["\']([^"\']*)["\']/i', $attr, $x ) ? $x[1] : '';
			$rel    = preg_match( '/rel\s*=\s*["\']([^"\']*)["\']/i', $attr, $x ) ? $x[1] : '';
			$target = preg_match( '/target\s*=\s*["\']([^"\']*)["\']/i', $attr, $x ) ? $x[1] : '';

			if ( '' === $href || 0 === strpos( $href, '#' ) ) {
				$tipo = 'ancora';
			} elseif ( 0 === strpos( $href, 'mailto:' ) ) {
				$tipo = 'mail';
			} elseif ( 0 === strpos( $href, 'tel:' ) ) {
				$tipo = 'telefono';
			} elseif ( 0 === strpos( $href, '/' ) || ( $host && false !== strpos( $href, $host ) ) ) {
				$tipo = 'interno';
			} elseif ( preg_match( '#^https?://#i', $href ) ) {
				$tipo = 'esterno';
			} else {
				$tipo = 'altro';
			}

			$out[] = array(
				'href'   => $href,
				'rel'    => $rel,
				'target' => $target,
				'anchor' => self::stripTags( $match[2] ),
				'tipo'   => $tipo,
			);
		}

		return $out;
	}

	/**
	 * Paragrafi testuali.
	 *
	 * @param string $html Markup.
	 * @return string[]
	 */
	public static function paragraphs( $html ) {
		$out = array();

		if ( preg_match_all( '#<p\b[^>]*>([\s\S]*?)</p>#i', (string) $html, $m ) ) {
			foreach ( $m[1] as $p ) {
				$t = self::stripTags( $p );
				if ( '' !== $t ) {
					$out[] = $t;
				}
			}
		}

		return $out;
	}

	/**
	 * Ripulisce l HTML prodotto dal modello prima di mostrarlo a schermo.
	 *
	 * Il testo arriva da un modello linguistico: non è codice di cui fidarsi.
	 * Si tolgono script, stili, iframe, gestori di eventi e URL javascript:
	 * prima di stamparlo nella pagina del gestionale.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	public static function sanifica( $html ) {
		$out = preg_replace( '#<(script|style|iframe|object|embed|form|input)\b[\s\S]*?</\1>#i', '', (string) $html );
		$out = preg_replace( '#<(script|style|iframe|object|embed|form|input|link|meta)\b[^>]*>#i', '', (string) $out );
		$out = preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string) $out );
		$out = preg_replace( '/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '$1="#"', (string) $out );

		return (string) $out;
	}

	/**
	 * Primo paragrafo con un minimo di sostanza.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	public static function firstParagraph( $html ) {
		$ps = self::paragraphs( $html );

		foreach ( $ps as $p ) {
			if ( count( preg_split( '/\s+/u', $p ) ) > 8 ) {
				return $p;
			}
		}

		return $ps[0] ?? '';
	}
}
