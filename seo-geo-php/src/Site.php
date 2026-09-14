<?php
/**
 * Modello normalizzato del sito.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

/**
 * Documenti pubblicati, allegati, menu e grafo dei link interni.
 */
class Site {

	/** @var array */
	public $meta;

	/** @var string */
	public $host;

	/** @var string */
	public $url;

	/** @var array[] Documenti pubblicati (articoli e pagine). */
	public $pubblicati = array();

	/** @var array[] */
	public $articoli = array();

	/** @var array[] */
	public $pagine = array();

	/** @var array[] */
	public $cestino = array();

	/** @var array[] */
	public $allegati = array();

	/** @var array[] */
	public $menu = array();

	/** @var array[] */
	public $categorie = array();

	/** @var array[] */
	public $tag = array();

	/** @var array[] */
	public $autori = array();

	/**
	 * Che cosa stampa il plugin nella testata di ogni pagina.
	 *
	 * Vuoto quando l analisi viene da un export, che del sito servito non
	 * sa niente. Le regole che riguardano il <head> lo guardano prima di
	 * segnalare qualcosa: cercare nel testo di un articolo una cosa che sta
	 * nella testata dava un rilievo che non si poteva chiudere mai.
	 *
	 * @var array<string,bool>
	 */
	public $stampa = array();

	/** @var array<string,int> Percorso => numero di link interni ricevuti. */
	private $inbound = array();

	/**
	 * @param array $parsed Risultato di WxrParser::parse().
	 */
	public function __construct( array $parsed ) {
		$this->meta      = $parsed['sito'];
		$this->categorie = $parsed['categorie'];
		$this->tag       = $parsed['tag'];
		$this->autori    = $parsed['sito']['autori'];
		$this->stampa    = (array) ( $parsed['sito']['stampa'] ?? array() );
		$this->url       = rtrim( $parsed['sito']['link'] ?: $parsed['sito']['baseUrl'], '/' );
		$this->host      = preg_replace( '#^www\.#', '', (string) parse_url( $this->url, PHP_URL_HOST ) );

		foreach ( $parsed['items'] as $item ) {
			if ( 'attachment' === $item['tipo'] ) {
				$this->allegati[] = $this->costruisciAllegato( $item );
				continue;
			}

			if ( 'nav_menu_item' === $item['tipo'] ) {
				$this->menu[] = array(
					'titolo' => $item['titolo'],
					'tipo'   => $item['meta']['_menu_item_type'] ?? '',
					'url'    => $item['meta']['_menu_item_url'] ?? '',
				);
				continue;
			}

			if ( ! in_array( $item['tipo'], array( 'post', 'page' ), true ) ) {
				continue;
			}

			$doc = $this->costruisciDocumento( $item );

			if ( 'publish' === $doc['stato'] ) {
				$this->pubblicati[] = $doc;
			} elseif ( 'trash' === $doc['stato'] ) {
				$this->cestino[] = $doc;
			}
		}

		foreach ( $this->pubblicati as $doc ) {
			if ( 'post' === $doc['tipo'] ) {
				$this->articoli[] = $doc;
			} else {
				$this->pagine[] = $doc;
			}
		}

		$this->costruisciGrafo();
	}

	/**
	 * Normalizza un articolo o una pagina.
	 *
	 * @param array $item Elemento grezzo.
	 * @return array
	 */
	private function costruisciDocumento( array $item ) {
		$contenuto = $item['contenuto'];
		// Con i blocchi separati: serve a chi legge il confronto, e anche al
		// modello, che cosi vede la struttura invece di un muro di parole.
		$testo     = Html::testo( $contenuto );
		$titoli    = Html::headings( $contenuto );
		$immagini  = Html::images( $contenuto );
		$link      = Html::links( $contenuto, $this->host );
		$meta      = $item['meta'];

		// Qualunque sia la provenienza (XML, sito, database), qui dentro le meta
		// sono testo: un array come rank_math_robots faceva cadere l analisi.
		$val = static function ( $chiave, $ripiego = '' ) use ( $meta ) {
			$v = $meta[ $chiave ] ?? ( '' !== $ripiego ? ( $meta[ $ripiego ] ?? '' ) : '' );

			if ( is_array( $v ) ) {
				return implode( ',', array_map( static fn( $x ) => is_scalar( $x ) ? (string) $x : '', $v ) );
			}

			return is_scalar( $v ) ? (string) $v : '';
		};

		$seoTitle = $val( 'rank_math_title', '_yoast_wpseo_title' );
		$seoTitle = trim( preg_replace( '/%[a-z_]+%/i', '', $seoTitle ) );
		$seoDesc  = trim( $val( 'rank_math_description', '_yoast_wpseo_metadesc' ) );
		$focus    = trim( explode( ',', $val( 'rank_math_focus_keyword', '_yoast_wpseo_focuskw' ) )[0] );

		$filtraLivello = static function ( $livello ) use ( $titoli ) {
			return array_values( array_filter( $titoli, static fn( $h ) => $h['livello'] === $livello ) );
		};

		return array(
			'wp_id'       => $item['wp_id'],
			'tipo'        => $item['tipo'],
			'stato'       => $item['stato'],
			'titolo'      => $item['titolo'],
			'slug'        => $item['slug'],
			'url'         => $item['link'],
			'percorso'    => $this->percorso( $item['link'] ),
			'data'        => $item['data'],
			'modificato'  => $item['modificato'] ?: $item['data'],
			'autore'      => $item['autore'],
			'categorie'   => $item['categorie'],
			'tag'         => $item['tag'],
			'estratto'    => Html::stripTags( $item['estratto'] ),
			'contenuto'   => $contenuto,
			'testo'       => $testo,
			'parole'      => Text::wordCount( $testo ),
			'gulpease'    => Text::gulpease( $testo ),
			'titoli'      => $titoli,
			'h1'          => $filtraLivello( 1 ),
			'h2'          => $filtraLivello( 2 ),
			'immagini'    => $immagini,
			'links'       => $link,
			'link_interni' => array_values( array_filter( $link, static fn( $l ) => 'interno' === $l['tipo'] ) ),
			'link_esterni' => array_values( array_filter( $link, static fn( $l ) => 'esterno' === $l['tipo'] ) ),
			'primo_paragrafo' => Html::firstParagraph( $contenuto ),
			'ha_jsonld'   => (bool) preg_match( '#application/ld\+json#i', $contenuto ),
			'ha_style'    => (bool) preg_match( '/<style\b/i', $contenuto ),
			'liste'       => preg_match_all( '/<(ul|ol)\b/i', $contenuto ),
			'tabelle'     => preg_match_all( '/<table\b/i', $contenuto ),
			'seo_title'   => $seoTitle,
			'seo_desc'    => $seoDesc,
			'focus'       => $focus,
			'seo_score'   => isset( $meta['rank_math_seo_score'] ) ? (int) $meta['rank_math_seo_score'] : null,
			'canonical'   => $val( 'rank_math_canonical_url' ),
			'robots'      => $val( 'rank_math_robots' ),
			'noindex'     => (bool) preg_match( '/noindex/', $val( 'rank_math_robots' ) ),
			'thumbnail'   => $val( '_thumbnail_id' ),
			'elementor'   => isset( $meta['_elementor_data'] ),
			'commenti'    => $item['commenti'],
			'meta'        => $meta,
		);
	}

	/**
	 * Normalizza un allegato della libreria media.
	 *
	 * @param array $item Elemento grezzo.
	 * @return array
	 */
	private function costruisciAllegato( array $item ) {
		$serial = $item['meta']['_wp_attachment_metadata'] ?? '';
		$peso   = preg_match( '/"filesize";i:(\d+)/', $serial, $m ) ? (int) $m[1] : 0;

		// Quando i dati arrivano dal sito il peso è già un numero.
		if ( isset( $item['peso'] ) ) {
			$peso = (int) $item['peso'];
		}

		return array(
			'wp_id'    => $item['wp_id'],
			'url'      => $item['allegato_url'],
			'file'     => basename( (string) $item['allegato_url'] ),
			'ext'      => strtolower( (string) pathinfo( (string) $item['allegato_url'], PATHINFO_EXTENSION ) ),
			'titolo'   => $item['titolo'],
			'alt'      => $item['meta']['_wp_attachment_image_alt'] ?? '',
			'genitore' => $item['genitore'],
			'peso'     => $peso,
			// Questi tre li sa solo il sito. Da un export WXR restano vuoti,
			// e le regole li trattano come "non si sa".
			'mime'        => (string) ( $item['mime'] ?? '' ),
			'nel_testo'   => ! empty( $item['nel_testo'] ),
			'gia_ridotta' => ! empty( $item['gia_ridotta'] ),
		);
	}

	/**
	 * Percorso relativo di un URL del sito.
	 *
	 * @param string $url URL assoluto o relativo.
	 * @return string
	 */
	public function percorso( $url ) {
		$p = preg_replace( '#^https?://[^/]+#i', '', (string) $url );
		$p = explode( '#', explode( '?', (string) $p )[0] )[0];
		$p = rtrim( (string) $p, '/' );

		return '' === $p ? '/' : $p;
	}

	/**
	 * Costruisce il conteggio dei link interni ricevuti da ogni percorso.
	 *
	 * @return void
	 */
	private function costruisciGrafo() {
		foreach ( $this->pubblicati as $doc ) {
			$visti = array();

			foreach ( $doc['link_interni'] as $l ) {
				$p = $this->percorso( $l['href'] );

				if ( '' === $p || $p === $doc['percorso'] || isset( $visti[ $p ] ) ) {
					continue;
				}

				$visti[ $p ]        = true;
				$this->inbound[ $p ] = ( $this->inbound[ $p ] ?? 0 ) + 1;
			}
		}
	}

	/**
	 * Link interni in entrata su un percorso.
	 *
	 * @param string $percorso Percorso.
	 * @return int
	 */
	public function inbound( $percorso ) {
		return $this->inbound[ $percorso ] ?? 0;
	}
}
