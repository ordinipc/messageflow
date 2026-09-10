<?php
/**
 * Lettura dell'esportazione WordPress (WXR).
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

use RuntimeException;
use SimpleXMLElement;
use XMLReader;

/**
 * Legge il file in streaming: un export può superare i 10 MB e caricarlo
 * interamente come DOM sprecherebbe memoria su hosting condiviso.
 */
class WxrParser {

	const NS_WP      = 'http://wordpress.org/export/1.2/';
	const NS_CONTENT = 'http://purl.org/rss/1.0/modules/content/';
	const NS_EXCERPT = 'http://wordpress.org/export/1.2/excerpt/';
	const NS_DC      = 'http://purl.org/dc/elements/1.1/';

	/**
	 * Analizza il file e restituisce sito, elementi, categorie e tag.
	 *
	 * @param string $percorso Percorso del file XML.
	 * @return array
	 * @throws RuntimeException Se il file non è leggibile.
	 */
	public static function parse( $percorso ) {
		if ( ! is_readable( $percorso ) ) {
			throw new RuntimeException( "File non leggibile: $percorso" );
		}

		$sito = self::leggiIntestazione( $percorso );

		$reader = new XMLReader();
		$reader->open( $percorso );

		$items      = array();
		$categorie  = array();
		$tag        = array();

		while ( $reader->read() ) {
			if ( XMLReader::ELEMENT !== $reader->nodeType ) {
				continue;
			}

			if ( 'item' === $reader->name ) {
				$xml = $reader->readOuterXml();
				$reader->next();
				$items[] = self::leggiItem( $xml );
				continue;
			}

			if ( 'wp:category' === $reader->name ) {
				$categorie[] = self::leggiTermine( $reader->readOuterXml(), 'category_nicename', 'cat_name' );
				continue;
			}

			if ( 'wp:tag' === $reader->name ) {
				$tag[] = self::leggiTermine( $reader->readOuterXml(), 'tag_slug', 'tag_name' );
			}
		}

		$reader->close();

		return array(
			'sito'      => $sito,
			'items'     => $items,
			'categorie' => $categorie,
			'tag'       => $tag,
		);
	}

	/**
	 * Dati del canale e autori, letti dalla porzione iniziale del file.
	 *
	 * @param string $percorso Percorso del file.
	 * @return array
	 */
	private static function leggiIntestazione( $percorso ) {
		$handle = fopen( $percorso, 'r' );
		$testa  = fread( $handle, 65536 );
		fclose( $handle );

		$campo = static function ( $tag ) use ( $testa ) {
			if ( preg_match( '#<' . $tag . '>(?:<!\[CDATA\[)?([\s\S]*?)(?:\]\]>)?</' . $tag . '>#', $testa, $m ) ) {
				return trim( $m[1] );
			}

			return '';
		};

		$autori = array();

		if ( preg_match_all( '#<wp:author>([\s\S]*?)</wp:author>#', $testa, $m ) ) {
			foreach ( $m[1] as $blocco ) {
				$leggi = static function ( $tag ) use ( $blocco ) {
					return preg_match( '#<' . $tag . '>(?:<!\[CDATA\[)?([\s\S]*?)(?:\]\]>)?</' . $tag . '>#', $blocco, $x ) ? trim( $x[1] ) : '';
				};

				$autori[] = array(
					'id'      => $leggi( 'wp:author_id' ),
					'login'   => $leggi( 'wp:author_login' ),
					'email'   => $leggi( 'wp:author_email' ),
					'nome'    => $leggi( 'wp:author_display_name' ),
					'first'   => $leggi( 'wp:author_first_name' ),
					'last'    => $leggi( 'wp:author_last_name' ),
				);
			}
		}

		return array(
			'titolo'      => $campo( 'title' ),
			'link'        => $campo( 'link' ),
			'descrizione' => $campo( 'description' ),
			'lingua'      => $campo( 'language' ),
			'baseUrl'     => $campo( 'wp:base_site_url' ),
			'autori'      => $autori,
		);
	}

	/**
	 * Converte un blocco <item> in array.
	 *
	 * @param string $xml XML dell'elemento.
	 * @return array
	 */
	private static function leggiItem( $xml ) {
		$sx = new SimpleXMLElement( $xml, LIBXML_NOCDATA );
		$wp = $sx->children( self::NS_WP );
		$co = $sx->children( self::NS_CONTENT );
		$ex = $sx->children( self::NS_EXCERPT );
		$dc = $sx->children( self::NS_DC );

		$meta = array();

		foreach ( $wp->postmeta as $pm ) {
			$chiave = (string) $pm->meta_key;
			if ( '' !== $chiave ) {
				$meta[ $chiave ] = (string) $pm->meta_value;
			}
		}

		$categorie = array();
		$tag       = array();

		foreach ( $sx->category as $cat ) {
			$dominio = (string) $cat['domain'];
			$voce    = array(
				'slug' => (string) $cat['nicename'],
				'nome' => (string) $cat,
			);

			if ( 'category' === $dominio ) {
				$categorie[] = $voce;
			} elseif ( 'post_tag' === $dominio ) {
				$tag[] = $voce;
			}
		}

		return array(
			'titolo'         => (string) $sx->title,
			'link'           => (string) $sx->link,
			'autore'         => (string) $dc->creator,
			'contenuto'      => (string) $co->encoded,
			'estratto'       => (string) $ex->encoded,
			'wp_id'          => (string) $wp->post_id,
			'data'           => (string) $wp->post_date_gmt,
			'modificato'     => (string) $wp->post_modified_gmt,
			'slug'           => (string) $wp->post_name,
			'stato'          => (string) $wp->status,
			'tipo'           => (string) $wp->post_type,
			'genitore'       => (string) $wp->post_parent,
			'commenti'       => (string) $wp->comment_status,
			'allegato_url'   => (string) $wp->attachment_url,
			'categorie'      => $categorie,
			'tag'            => $tag,
			'meta'           => $meta,
		);
	}

	/**
	 * Converte un termine di tassonomia in array.
	 *
	 * @param string $xml      XML del termine.
	 * @param string $tagSlug  Nome del tag con lo slug.
	 * @param string $tagNome  Nome del tag con l'etichetta.
	 * @return array
	 */
	private static function leggiTermine( $xml, $tagSlug, $tagNome ) {
		$leggi = static function ( $tag ) use ( $xml ) {
			return preg_match( '#<wp:' . $tag . '>(?:<!\[CDATA\[)?([\s\S]*?)(?:\]\]>)?</wp:' . $tag . '>#', $xml, $m ) ? trim( $m[1] ) : '';
		};

		return array(
			'id'   => $leggi( 'term_id' ),
			'slug' => $leggi( $tagSlug ),
			'nome' => $leggi( $tagNome ),
		);
	}
}
