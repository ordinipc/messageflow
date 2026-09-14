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
	 * Testo senza markup, ma con i blocchi ancora separati.
	 *
	 * stripTags() schiaccia ogni spazio bianco in uno solo: comodo per
	 * contare le parole, illeggibile per una persona. Il titoletto si
	 * incolla al paragrafo che segue e il risultato sembra una frase rotta
	 * anche quando l articolo e scritto bene - e nel confronto vecchio/nuovo
	 * era proprio quello che si vedeva.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	public static function testo( $html ) {
		$s = preg_replace( '#<(script|style)\b[\s\S]*?</\1>#i', ' ', (string) $html );
		$s = preg_replace( '/<!--[\s\S]*?-->/', ' ', (string) $s );

		// Una riga vuota dove finisce un blocco, cosi la struttura si vede.
		$s = preg_replace( '#</(p|div|h[1-6]|li|tr|blockquote|section|article|figcaption)>#i', "\n\n", (string) $s );
		$s = preg_replace( '#<br\s*/?>#i', "\n", (string) $s );

		$s = preg_replace( '/<[^>]+>/', ' ', (string) $s );
		$s = html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Spazi orizzontali normalizzati, a capo conservati.
		$s = preg_replace( '/[^\S\n]+/u', ' ', (string) $s );
		$s = preg_replace( '/ *\n */u', "\n", (string) $s );
		$s = preg_replace( '/\n{3,}/', "\n\n", (string) $s );

		return trim( (string) $s );
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
	 * Toglie dal testo i dati strutturati finiti dentro al contenuto.
	 *
	 * In produzione un articolo e andato online con il JSON-LD stampato in
	 * mezzo al testo, segnaposto compresi: il modello, a cui l audit aveva
	 * detto che mancava lo schema Article, lo aveva "risolto" scrivendo lo
	 * schema nel corpo dell articolo. WordPress toglie il tag <script> ma
	 * tiene quello che c e dentro, e il risultato e un muro di parentesi
	 * graffe in mezzo alla pagina.
	 *
	 * Lo schema lo stampa il plugin nel <head>, dove va. Nel corpo non ci
	 * deve arrivare, ne dentro a un tag ne come testo nudo.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	public static function senzaDatiStrutturati( $html ) {
		$out = preg_replace(
			'#<script\b[^>]*application/ld\+json[^>]*>[\s\S]*?</script>#i',
			'',
			(string) $html
		);

		return self::senzaJsonNudo( (string) $out );
	}

	/**
	 * Toglie i blocchi JSON-LD rimasti come testo, senza il tag che li
	 * conteneva.
	 *
	 * Si scorre carattere per carattere invece di usare un unica espressione
	 * regolare perche il JSON-LD e annidato: contare le graffe e l unico modo
	 * di sapere dove finisce senza mangiarsi il testo che segue.
	 *
	 * @param string $testo Testo o markup.
	 * @return string
	 */
	private static function senzaJsonNudo( $testo ) {
		$lunghezza = strlen( $testo );
		$out       = '';
		$i         = 0;

		while ( $i < $lunghezza ) {
			if ( '{' !== $testo[ $i ] || ! self::apreJsonLd( $testo, $i ) ) {
				$out .= $testo[ $i ];
				$i++;
				continue;
			}

			$fine = self::fineDellOggetto( $testo, $i );

			if ( $fine < 0 ) {
				// Graffe non bilanciate: meglio lasciare il testo com e che
				// tagliare fino in fondo alla pagina.
				$out .= $testo[ $i ];
				$i++;
				continue;
			}

			$i = $fine + 1;
		}

		return trim( preg_replace( '/\n{3,}/', "\n\n", $out ) );
	}

	/**
	 * Se a partire da questa graffa comincia un oggetto JSON-LD.
	 *
	 * Si riconosce dalla chiave @context di schema.org: un oggetto qualsiasi
	 * scritto nel testo (un esempio di codice, una formula) non si tocca.
	 *
	 * @param string $testo   Testo.
	 * @param int    $inizio  Posizione della graffa aperta.
	 * @return bool
	 */
	private static function apreJsonLd( $testo, $inizio ) {
		$finestra = substr( $testo, $inizio, 120 );

		return (bool) preg_match( '/^\{\s*["\']?@context["\']?\s*:/', $finestra );
	}

	/**
	 * Posizione della graffa che chiude l oggetto aperto in $inizio.
	 *
	 * @param string $testo  Testo.
	 * @param int    $inizio Posizione della graffa aperta.
	 * @return int -1 se non si chiude.
	 */
	private static function fineDellOggetto( $testo, $inizio ) {
		$lunghezza = strlen( $testo );
		$aperte    = 0;
		$dentro    = false;
		$virgoletta = '';

		for ( $i = $inizio; $i < $lunghezza; $i++ ) {
			$c = $testo[ $i ];

			if ( $dentro ) {
				if ( '\\' === $c ) {
					$i++;
					continue;
				}

				if ( $c === $virgoletta ) {
					$dentro = false;
				}

				continue;
			}

			if ( '"' === $c || "'" === $c ) {
				$dentro     = true;
				$virgoletta = $c;
				continue;
			}

			if ( '{' === $c ) {
				$aperte++;
				continue;
			}

			if ( '}' === $c ) {
				$aperte--;

				if ( 0 === $aperte ) {
					return $i;
				}
			}
		}

		return -1;
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
