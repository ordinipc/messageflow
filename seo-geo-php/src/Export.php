<?php
/**
 * Generazione dei file di correzione scaricabili.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

use SeoGeo\Fix\Llms;
use SeoGeo\Fix\Schema;

/**
 * Scrive su disco CSV, file per i crawler, redirect e dati per il plugin.
 */
class Export {

	/**
	 * CSV con separatore punto e virgola e BOM, compatibile con Excel italiano.
	 *
	 * @param array $righe    Righe associative.
	 * @param array $colonne  Etichetta => chiave o callable.
	 * @return string
	 */
	public static function csv( array $righe, array $colonne ) {
		$out = "\xEF\xBB\xBF" . implode( ';', array_map( array( __CLASS__, 'campo' ), array_keys( $colonne ) ) ) . "\r\n";

		foreach ( $righe as $r ) {
			$cella = array();

			foreach ( $colonne as $chiave ) {
				// Attenzione: is_callable() considera callable anche una stringa che
				// coincide con il nome di una funzione PHP (es. 'file'): serve il tipo.
				$v       = $chiave instanceof \Closure ? $chiave( $r ) : ( $r[ $chiave ] ?? '' );
				$cella[] = self::campo( is_bool( $v ) ? ( $v ? 'sì' : 'no' ) : (string) $v );
			}

			$out .= implode( ';', $cella ) . "\r\n";
		}

		return $out;
	}

	/**
	 * Protegge un campo CSV.
	 *
	 * @param string $v Valore.
	 * @return string
	 */
	private static function campo( $v ) {
		$v = (string) $v;

		return preg_match( '/["\r\n;,]/', $v ) ? '"' . str_replace( '"', '""', $v ) . '"' : $v;
	}

	/**
	 * Scrive un file creando le cartelle mancanti.
	 *
	 * @param string $percorso Percorso.
	 * @param string $contenuto Contenuto.
	 * @return string
	 */
	public static function scrivi( $percorso, $contenuto ) {
		$dir = dirname( $percorso );

		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0775, true );
		}

		file_put_contents( $percorso, $contenuto );

		return $percorso;
	}

	/**
	 * Genera l intero pacchetto di correzioni.
	 *
	 * @param array $ctx Contesto: site, cfg, audit, triage, meta, link, cartella.
	 * @return string[] Percorsi dei file scritti.
	 */
	public static function tutto( array $ctx ) {
		$site   = $ctx['site'];
		$cfg    = $ctx['cfg'];
		$audit  = $ctx['audit'];
		$triage = $ctx['triage'];
		$meta   = $ctx['meta'];
		$link   = $ctx['link'];
		$dir    = rtrim( $ctx['cartella'], '/' );

		$scritti = array();

		// 1. Elenco dei problemi.
		$problemi = array();
		foreach ( $audit['rilievi'] as $r ) {
			foreach ( $r['problemi'] as $p ) {
				$problemi[] = array(
					'regola'    => $r['id'],
					'gravita'   => $r['gravita'],
					'area'      => $r['area'],
					'titolo'    => $r['titolo'],
					'ref'       => $p['ref'],
					'dettaglio' => $p['dettaglio'],
					'auto'      => $r['auto'] ? 'sì' : 'no',
				);
			}
		}
		$scritti[] = self::scrivi(
			"$dir/problemi.csv",
			self::csv(
				$problemi,
				array(
					'ID regola'            => 'regola',
					'Gravità'              => 'gravita',
					'Area'                 => 'area',
					'Problema'             => 'titolo',
					'URL / elemento'       => 'ref',
					'Dettaglio'            => 'dettaglio',
					'Risolto dal toolkit'  => 'auto',
				)
			)
		);

		// 2. Triage editoriale.
		$scritti[] = self::scrivi(
			"$dir/triage.csv",
			self::csv(
				$triage['articoli'],
				array(
					'Categoria'      => 'categoria',
					'Titolo'         => 'titolo',
					'URL'            => 'url',
					'Parole'         => 'parole',
					'Qualità'        => 'qualita',
					'Intento'        => 'intento',
					'H2'             => 'h2',
					'Immagini'       => 'immagini',
					'Link in entrata' => 'link_in',
					'Link in uscita' => 'link_out',
					'Focus keyword'  => 'focus',
					'Pubblicato'     => 'pubblicato',
					'Similarità max' => 'similarita',
					'Sovrapposizione con pagina servizio' => static fn( $r ) => $r['servizio'] ? $r['servizio']['pagina'] . ' (' . $r['servizio']['percentuale'] . '%)' : '',
					'Motivo'         => 'motivo',
					'Azione'         => 'azione',
					'Redirect 301 verso' => static fn( $r ) => $r['redirect'] ? $r['redirect']['url'] : '',
				)
			)
		);

		// 3. Meta ottimizzate.
		$scritti[] = self::scrivi(
			"$dir/meta-ottimizzate.csv",
			self::csv(
				$meta,
				array(
					'ID'                   => 'wp_id',
					'Tipo'                 => 'tipo',
					'URL'                  => 'url',
					'Title attuale'        => 'title_attuale',
					'Title nuovo'          => 'title_nuovo',
					'Lunghezza title'      => static fn( $r ) => mb_strlen( $r['title_nuovo'] ),
					'Description attuale'  => 'description_attuale',
					'Description nuova'    => 'description_nuova',
					'Lunghezza description' => static fn( $r ) => mb_strlen( $r['description_nuova'] ),
					'Excerpt nuovo'        => 'excerpt_nuovo',
					'Slug attuale'         => 'slug_attuale',
					'Slug nuovo'           => 'slug_nuovo',
					'Focus keyword'        => 'focus',
				)
			)
		);

		// 4. Formato dell editor massivo di Rank Math.
		$rank = "id,rank_math_title,rank_math_description,rank_math_focus_keyword\r\n";
		foreach ( $meta as $m ) {
			$rank .= sprintf(
				"%s,\"%s\",\"%s\",\"%s\"\r\n",
				$m['wp_id'],
				str_replace( '"', '""', $m['title_nuovo'] ),
				str_replace( '"', '""', $m['description_nuova'] ),
				str_replace( '"', '""', $m['focus'] )
			);
		}
		$scritti[] = self::scrivi( "$dir/rank-math-bulk.csv", $rank );

		// 5. Redirect 301.
		$redirect = array();
		foreach ( $meta as $m ) {
			if ( $m['slug_cambiato'] ) {
				$redirect[] = array( 'da' => $m['percorso'], 'a' => '/' . $m['slug_nuovo'] . '/', 'motivo' => 'slug accorciato' );
			}
		}
		foreach ( $triage['articoli'] as $a ) {
			if ( $a['redirect'] ) {
				$redirect[] = array(
					'da'     => $a['percorso'],
					'a'      => parse_url( $a['redirect']['url'], PHP_URL_PATH ),
					'motivo' => 'eliminare' === $a['categoria'] ? 'contenuto eliminato' : 'contenuto accorpato',
				);
			}
		}
		$scritti[] = self::scrivi(
			"$dir/redirect-301.csv",
			self::csv( $redirect, array( 'URL di partenza' => 'da', 'URL di destinazione' => 'a', 'Motivo' => 'motivo' ) )
		);

		$htaccess = "# Redirect 301 generati da SEO & GEO Audit - " . date( 'Y-m-d' ) . "\n"
			. "# Inserire PRIMA delle regole di WordPress.\n<IfModule mod_rewrite.c>\nRewriteEngine On\n";
		foreach ( $redirect as $r ) {
			$htaccess .= 'Redirect 301 ' . rtrim( $r['da'], '/' ) . '/ ' . $r['a'] . "\n";
		}
		$scritti[] = self::scrivi( "$dir/redirect.htaccess", $htaccess . "</IfModule>\n" );

		// 6. Piano dei link interni.
		$scritti[] = self::scrivi(
			"$dir/piano-link-interni.csv",
			self::csv(
				$link['piano'],
				array(
					'Pagina di partenza'     => 'da',
					'Titolo partenza'        => 'da_titolo',
					'Pagina di destinazione' => 'a',
					'Titolo destinazione'    => 'a_titolo',
					'Anchor text'            => 'anchor',
					'Motivo'                 => 'motivo',
					'Punteggio'              => 'punteggio',
				)
			)
		);

		// 7. Alt delle immagini.
		$immagini = array();
		foreach ( $site->allegati as $a ) {
			if ( ! preg_match( '/jpe?g|png|webp|gif|svg/', $a['ext'] ) ) {
				continue;
			}

			$immagini[] = array(
				'file'          => $a['file'],
				'url'           => $a['url'],
				'alt_attuale'   => $a['alt'],
				'alt_suggerito' => $a['alt'] ?: trim( str_replace( array( '-', '_' ), ' ', $a['titolo'] ) . ' | ' . $cfg['seo']['brandSuffix'] . ' ' . $cfg['seo']['cittaPrincipale'] ),
				'peso'          => $a['peso'] ? round( $a['peso'] / 1024 ) . ' KB' : '',
				'formato'       => $a['ext'],
				'webp'          => in_array( $a['ext'], array( 'jpg', 'jpeg', 'png' ), true ) ? 'sì' : 'no',
				'comprimere'    => $a['peso'] > 204800 ? 'sì' : 'no',
			);
		}
		$scritti[] = self::scrivi(
			"$dir/immagini-alt.csv",
			self::csv(
				$immagini,
				array(
					'File'               => 'file',
					'URL'                => 'url',
					'Alt attuale'        => 'alt_attuale',
					'Alt suggerito'      => 'alt_suggerito',
					'Peso'               => 'peso',
					'Formato'            => 'formato',
					'Convertire in WebP' => 'webp',
					'Comprimere'         => 'comprimere',
				)
			)
		);

		// 8. File per i crawler.
		$scritti[] = self::scrivi( "$dir/file-root/llms.txt", Llms::llms( $site, $cfg, $triage ) );
		$scritti[] = self::scrivi( "$dir/file-root/llms-full.txt", Llms::llmsFull( $site, $cfg, $triage ) );
		$scritti[] = self::scrivi( "$dir/file-root/robots.txt", Llms::robots( $site, $cfg ) );
		$scritti[] = self::scrivi( "$dir/file-root/ai.txt", Llms::ai( $site, $cfg ) );

		// 9. Dati strutturati.
		$schema    = Schema::piano( $site, $cfg );
		$scritti[] = self::scrivi( "$dir/schema/tutti-gli-schema.json", json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

		$esempio = $schema[0];
		foreach ( $schema as $s ) {
			if ( in_array( 'Service', $s['tipi'], true ) ) {
				$esempio = $s;
				break;
			}
		}
		$scritti[] = self::scrivi( "$dir/schema/esempio-pagina-servizio.json", json_encode( $esempio['schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

		// 10. Dati pronti per il plugin WordPress.
		$mappaMeta = array();
		foreach ( $meta as $m ) {
			$mappaMeta[] = array(
				'id'          => (int) $m['wp_id'],
				'slug'        => $m['slug_attuale'],
				'title'       => $m['title_nuovo'],
				'description' => $m['description_nuova'],
				'excerpt'     => $m['excerpt_nuovo'],
				'noindex'     => $m['noindex'],
			);
		}
		$scritti[] = self::scrivi( "$dir/plugin-data/meta-map.json", json_encode( $mappaMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$scritti[] = self::scrivi( "$dir/plugin-data/internal-links.json", json_encode( $link['mappa'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

		$correlati = array();
		$perPercorso = array();
		foreach ( $site->pubblicati as $d ) {
			$perPercorso[ $d['percorso'] ] = $d;
		}
		foreach ( $link['piano'] as $l ) {
			$da = $perPercorso[ $l['da'] ] ?? null;
			$a  = $perPercorso[ $l['a'] ] ?? null;
			if ( ! $da || ! $a ) {
				continue;
			}
			$id = $da['wp_id'];
			if ( ! isset( $correlati[ $id ] ) ) {
				$correlati[ $id ] = array();
			}
			if ( count( $correlati[ $id ] ) < 5 ) {
				$correlati[ $id ][] = array( 'titolo' => $a['titolo'], 'url' => $a['url'] );
			}
		}
		$scritti[] = self::scrivi( "$dir/plugin-data/related.json", json_encode( $correlati, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$scritti[] = self::scrivi( "$dir/plugin-data/config.json", json_encode( $cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

		// 11. Plugin WordPress pronto da installare.
		$plugin = self::plugin( $ctx, "$dir/plugin-data" );

		if ( $plugin['zip'] ) {
			$scritti[] = $plugin['zip'];
		}

		return $scritti;
	}

	/**
	 * Assembla il plugin WordPress con i dati calcolati e ne crea lo zip.
	 *
	 * @param array  $ctx      Stesso contesto di tutto().
	 * @param string $datiDir  Cartella con i JSON già generati (plugin-data).
	 * @return array{cartella:string,zip:string|null}
	 */
	public static function plugin( array $ctx, $datiDir ) {
		$sorgente = __DIR__ . '/../plugin-wordpress/mdi-seo-geo-booster';
		$dir      = rtrim( $ctx['cartella'], '/' ) . '/plugin-wordpress/mdi-seo-geo-booster';

		self::copiaCartella( $sorgente, $dir );

		if ( ! is_dir( $dir . '/data' ) ) {
			mkdir( $dir . '/data', 0775, true );
		}

		foreach ( array( 'meta-map.json', 'internal-links.json', 'related.json', 'config.json' ) as $f ) {
			if ( is_file( "$datiDir/$f" ) ) {
				copy( "$datiDir/$f", "$dir/data/$f" );
			}
		}

		$root = rtrim( $ctx['cartella'], '/' ) . '/file-root';
		foreach ( array( 'llms.txt', 'llms-full.txt', 'ai.txt' ) as $f ) {
			if ( is_file( "$root/$f" ) ) {
				copy( "$root/$f", "$dir/data/$f" );
			}
		}

		$zip = null;

		if ( class_exists( '\ZipArchive' ) ) {
			$zip     = rtrim( $ctx['cartella'], '/' ) . '/mdi-seo-geo-booster.zip';
			$archivio = new \ZipArchive();

			if ( true === $archivio->open( $zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
				self::aggiungiCartella( $archivio, $dir, 'mdi-seo-geo-booster' );
				$archivio->close();
			} else {
				$zip = null;
			}
		}

		return array( 'cartella' => $dir, 'zip' => $zip );
	}

	/**
	 * Copia ricorsiva di una cartella.
	 *
	 * @param string $da Sorgente.
	 * @param string $a  Destinazione.
	 * @return void
	 */
	private static function copiaCartella( $da, $a ) {
		if ( ! is_dir( $a ) ) {
			mkdir( $a, 0775, true );
		}

		foreach ( scandir( $da ) as $voce ) {
			if ( '.' === $voce || '..' === $voce ) {
				continue;
			}

			$sorgente = "$da/$voce";
			$destinazione = "$a/$voce";

			if ( is_dir( $sorgente ) ) {
				self::copiaCartella( $sorgente, $destinazione );
			} else {
				copy( $sorgente, $destinazione );
			}
		}
	}

	/**
	 * Aggiunge una cartella a un archivio zip.
	 *
	 * @param \ZipArchive $zip     Archivio.
	 * @param string      $dir     Cartella.
	 * @param string      $prefisso Percorso interno all archivio.
	 * @return void
	 */
	private static function aggiungiCartella( \ZipArchive $zip, $dir, $prefisso ) {
		$zip->addEmptyDir( $prefisso );

		foreach ( scandir( $dir ) as $voce ) {
			if ( '.' === $voce || '..' === $voce ) {
				continue;
			}

			if ( is_dir( "$dir/$voce" ) ) {
				self::aggiungiCartella( $zip, "$dir/$voce", "$prefisso/$voce" );
			} else {
				$zip->addFile( "$dir/$voce", "$prefisso/$voce" );
			}
		}
	}
}
