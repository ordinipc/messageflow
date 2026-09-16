<?php
/**
 * Link interni automatici e gestione dei link esterni.
 *
 * @package MDI_SEO_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inserisce link interni contestuali e mette in sicurezza quelli esterni.
 */
class MDI_Links {

	/**
	 * Aggancia i filtri sul contenuto.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'auto_internal_links' ), 12 );
		add_filter( 'the_content', array( __CLASS__, 'secure_external_links' ), 14 );
		add_filter( 'the_content', array( __CLASS__, 'append_related' ), 16 );
	}

	/**
	 * Sostituisce la prima occorrenza di ogni keyword con un link interno.
	 *
	 * Non tocca il testo dentro tag esistenti (a, h1-h6, img, script, style):
	 * si spezza l'HTML in segmenti e si lavora solo su quelli testuali.
	 *
	 * @param string $content Contenuto.
	 * @return string
	 */
	public static function auto_internal_links( $content ) {
		if ( ! is_singular() || is_admin() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		// Le pagine servizio sono scritte a mano: inserirci link in automatico
		// ne altera il testo. Di norma si lasciano stare.
		if ( ! is_singular( 'post' ) && ! mdi_seo_geo_cfg( 'seo.linkAutomaticiNellePagine', false ) ) {
			return $content;
		}

		$mappa = mdi_seo_geo_data( 'internal-links' );

		if ( empty( $mappa ) ) {
			return $content;
		}

		$max     = (int) mdi_seo_geo_cfg( 'seo.linkInterniPerArticolo', 4 );
		$corrente = untrailingslashit( get_permalink() );
		$inseriti = 0;

		// Le keyword più lunghe hanno la precedenza: evita match parziali.
		uksort(
			$mappa,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		$segmenti = preg_split( '/(<a\b[\s\S]*?<\/a>|<h[1-6][\s\S]*?<\/h[1-6]>|<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>|<[^>]+>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );

		foreach ( $mappa as $keyword => $url ) {
			if ( $inseriti >= $max ) {
				break;
			}

			if ( untrailingslashit( $url ) === $corrente ) {
				continue; // Mai autolink alla pagina stessa.
			}

			if ( false !== stripos( $content, 'href="' . $url ) ) {
				continue; // Link già presente.
			}

			$pattern = '/\b(' . preg_quote( $keyword, '/' ) . ')\b/iu';

			foreach ( $segmenti as $i => $segmento ) {
				if ( '' === $segmento || '<' === substr( $segmento, 0, 1 ) ) {
					continue;
				}

				if ( preg_match( $pattern, $segmento ) ) {
					$segmenti[ $i ] = preg_replace(
						$pattern,
						'<a href="' . esc_url( $url ) . '">$1</a>',
						$segmento,
						1
					);
					++$inseriti;
					break;
				}
			}
		}

		return implode( '', $segmenti );
	}

	/**
	 * Applica nofollow/sponsored ai domini che non devono ricevere autorità e
	 * mette in sicurezza i link che aprono una nuova scheda.
	 *
	 * @param string $content Contenuto.
	 * @return string
	 */
	public static function secure_external_links( $content ) {
		$domini = (array) mdi_seo_geo_cfg( 'seo.dominiNofollow', array() );
		$casa   = wp_parse_url( home_url(), PHP_URL_HOST );

		return preg_replace_callback(
			'/<a\b([^>]*)href=("|\')(https?:\/\/[^"\']+)\2([^>]*)>/i',
			function ( $m ) use ( $domini, $casa ) {
				$host   = wp_parse_url( $m[3], PHP_URL_HOST );
				$attr   = $m[1] . $m[4];
				$dicasa = ! $host || false !== strpos( $host, $casa );

				// Un link che apre una nuova scheda va messo in sicurezza
				// anche quando punta a una pagina di casa: il reverse
				// tabnabbing non guarda il dominio, e l audit contava anche
				// quelli. Restavano segnati per sempre, perche il plugin
				// saltava tutto quello che non era esterno.
				$apre = (bool) preg_match( '/target=("|\')_blank\1/i', $attr );

				if ( $dicasa && ! $apre ) {
					return $m[0];
				}

				$rel = array( 'noopener', 'noreferrer' );

				foreach ( $dicasa ? array() : $domini as $dominio ) {
					if ( false !== stripos( $host, $dominio ) ) {
						$rel[] = 'nofollow';
						$rel[] = 'sponsored';
						break;
					}
				}

				// Si riscrive rel unendo quello esistente con le direttive nuove.
				if ( preg_match( '/rel=("|\')([^"\']*)\1/i', $attr, $r ) ) {
					$rel  = array_unique( array_merge( explode( ' ', $r[2] ), $rel ) );
					$attr = preg_replace( '/rel=("|\')[^"\']*\1/i', '', $attr );
				}

				$rel = array_filter( array_unique( $rel ) );

				return '<a' . rtrim( $attr ) . ' href="' . esc_url( $m[3] ) . '" rel="' . esc_attr( implode( ' ', $rel ) ) . '">';
			},
			$content
		);
	}

	/**
	 * Blocco "Articoli correlati" in coda: recupera le pagine orfane e aumenta
	 * la profondità di navigazione.
	 *
	 * @param string $content Contenuto.
	 * @return string
	 */
	public static function append_related( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$piano = mdi_seo_geo_data( 'related' );
		$id    = (string) get_the_ID();

		if ( empty( $piano[ $id ] ) ) {
			return $content;
		}

		$items = '';

		foreach ( array_slice( (array) $piano[ $id ], 0, 5 ) as $rel ) {
			if ( empty( $rel['url'] ) || empty( $rel['titolo'] ) ) {
				continue;
			}
			$items .= '<li><a href="' . esc_url( $rel['url'] ) . '">' . esc_html( $rel['titolo'] ) . '</a></li>';
		}

		if ( '' === $items ) {
			return $content;
		}

		return $content . '<section class="mdi-correlati"><h2>Approfondimenti correlati</h2><ul>' . $items . '</ul></section>';
	}
}
