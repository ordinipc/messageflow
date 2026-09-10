<?php
/**
 * Piano dei link interni.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Fix;

use SeoGeo\Db;
use SeoGeo\Site;
use SeoGeo\Text;

/**
 * Obiettivi: nessuna pagina orfana, ogni articolo collegato alle pagine
 * pilastro pertinenti, anchor text descrittivi.
 */
class InternalLinks {

	/**
	 * Termini rappresentativi di un documento.
	 *
	 * @param array $doc Documento.
	 * @return array<string,bool>
	 */
	private static function termini( array $doc ) {
		$set = array();

		foreach ( Text::words( $doc['titolo'] . ' ' . $doc['focus'] . ' ' . $doc['focus'] ) as $w ) {
			if ( mb_strlen( $w ) > 3 && ! in_array( $w, Text::$stopwords, true ) ) {
				$set[ $w ] = true;
			}
		}

		foreach ( array_keys( Text::topTerms( $doc['testo'], 15 ) ) as $t ) {
			$set[ $t ] = true;
		}

		return $set;
	}

	/**
	 * Somiglianza fra due insiemi di termini.
	 *
	 * @param array $a Primo insieme.
	 * @param array $b Secondo insieme.
	 * @return float
	 */
	private static function affinita( array $a, array $b ) {
		$comuni = count( array_intersect_key( $a, $b ) );

		return $comuni / sqrt( max( 1, count( $a ) ) * max( 1, count( $b ) ) );
	}

	/**
	 * Costruisce il piano completo.
	 *
	 * @param Site  $site Sito.
	 * @param array $cfg  Configurazione.
	 * @return array
	 */
	public static function piano( Site $site, array $cfg ) {
		$perDoc    = (int) $cfg['seo']['linkInterniPerArticolo'];
		$pilastri  = array();
		foreach ( $cfg['seo']['paginePilastro'] as $p ) {
			$pilastri[ $p['slug'] ] = true;
		}

		// Le pagine legali non partecipano: non devono né ricevere né distribuire
		// link editoriali.
		$docs = array();
		foreach ( $site->pubblicati as $d ) {
			if ( $d['parole'] > 80 && ! preg_match( '/privacy|cookie|termini|condizioni|note-legali/i', $d['slug'] ) ) {
				$docs[] = $d;
			}
		}

		$termini = array();
		foreach ( $docs as $i => $d ) {
			$termini[ $i ] = self::termini( $d );
		}

		$ePilastro = static function ( array $d ) use ( $pilastri ) {
			return isset( $pilastri[ $d['slug'] ] ) || 'page' === $d['tipo'];
		};

		$piano       = array();
		$nuoviInbound = array_fill( 0, count( $docs ), 0 );
		$n           = count( $docs );

		for ( $i = 0; $i < $n; $i++ ) {
			$sorgente = $docs[ $i ];
			$gia      = array();
			foreach ( $sorgente['link_interni'] as $l ) {
				$gia[ $site->percorso( $l['href'] ) ] = true;
			}

			$candidati = array();
			for ( $j = 0; $j < $n; $j++ ) {
				if ( $i === $j || isset( $gia[ $docs[ $j ]['percorso'] ] ) ) {
					continue;
				}

				$punteggio = self::affinita( $termini[ $i ], $termini[ $j ] );
				$punteggio += $ePilastro( $docs[ $j ] ) ? 0.22 : 0;                       // le pagine servizio vanno spinte
				$punteggio += 0 === $site->inbound( $docs[ $j ]['percorso'] ) ? 0.12 : 0; // e le orfane recuperate
				if ( isset( $docs[ $j ]['categorie'][0]['slug'], $sorgente['categorie'][0]['slug'] )
					&& $docs[ $j ]['categorie'][0]['slug'] === $sorgente['categorie'][0]['slug'] ) {
					$punteggio += 0.05;
				}

				if ( $punteggio > 0.12 ) {
					$candidati[ $j ] = $punteggio;
				}
			}

			arsort( $candidati );

			$scelti   = array();
			$pilastro = 0;

			foreach ( $candidati as $j => $punteggio ) {
				if ( count( $scelti ) >= $perDoc ) {
					break;
				}
				// Al massimo una pagina pilastro ogni due link: il resto correlati.
				if ( $ePilastro( $docs[ $j ] ) ) {
					if ( $pilastro >= (int) ceil( $perDoc / 2 ) ) {
						continue;
					}
					$pilastro++;
				}
				$scelti[ $j ] = $punteggio;
			}

			foreach ( $scelti as $j => $punteggio ) {
				$nuoviInbound[ $j ]++;
				$piano[] = array(
					'da'        => $sorgente['percorso'],
					'da_titolo' => $sorgente['titolo'],
					'a'         => $docs[ $j ]['percorso'],
					'a_titolo'  => $docs[ $j ]['titolo'],
					'a_url'     => $docs[ $j ]['url'],
					'anchor'    => $docs[ $j ]['focus'] ?: Text::truncate( $docs[ $j ]['titolo'], 60 ),
					'punteggio' => round( $punteggio, 3 ),
					'motivo'    => $ePilastro( $docs[ $j ] ) ? 'pagina servizio pertinente' : 'articolo correlato',
				);
			}
		}

		// Recupero esplicito delle orfane rimaste scoperte.
		for ( $j = 0; $j < $n; $j++ ) {
			if ( 0 !== $site->inbound( $docs[ $j ]['percorso'] ) || $nuoviInbound[ $j ] >= 2 ) {
				continue;
			}

			$sorgenti = array();
			for ( $i = 0; $i < $n; $i++ ) {
				if ( $i !== $j ) {
					$sorgenti[ $i ] = self::affinita( $termini[ $j ], $termini[ $i ] );
				}
			}
			arsort( $sorgenti );

			$mancanti = 3 - $nuoviInbound[ $j ];
			foreach ( array_slice( $sorgenti, 0, max( 0, $mancanti ), true ) as $i => $punteggio ) {
				$nuoviInbound[ $j ]++;
				$piano[] = array(
					'da'        => $docs[ $i ]['percorso'],
					'da_titolo' => $docs[ $i ]['titolo'],
					'a'         => $docs[ $j ]['percorso'],
					'a_titolo'  => $docs[ $j ]['titolo'],
					'a_url'     => $docs[ $j ]['url'],
					'anchor'    => $docs[ $j ]['focus'] ?: Text::truncate( $docs[ $j ]['titolo'], 60 ),
					'punteggio' => round( $punteggio, 3 ),
					'motivo'    => 'recupero pagina orfana',
				);
			}
		}

		// Mappa keyword -> URL usata dal plugin per i link automatici.
		$mappa = array();
		foreach ( $cfg['seo']['paginePilastro'] as $p ) {
			foreach ( $site->pubblicati as $d ) {
				if ( $d['slug'] === $p['slug'] ) {
					$mappa[ mb_strtolower( $p['keyword'] ) ] = $d['url'];
				}
			}
		}
		foreach ( $site->pubblicati as $d ) {
			$k = mb_strtolower( trim( $d['focus'] ) );
			if ( '' !== $k && mb_strlen( $k ) > 8 && ! isset( $mappa[ $k ] ) ) {
				$mappa[ $k ] = $d['url'];
			}
		}

		$orfaniPrima = 0;
		$orfaniDopo  = 0;
		for ( $j = 0; $j < $n; $j++ ) {
			if ( 0 === $site->inbound( $docs[ $j ]['percorso'] ) ) {
				$orfaniPrima++;
				if ( 0 === $nuoviInbound[ $j ] ) {
					$orfaniDopo++;
				}
			}
		}

		return array(
			'piano'       => $piano,
			'mappa'       => $mappa,
			'statistiche' => array(
				'link_proposti' => count( $piano ),
				'documenti'     => $n,
				'orfani_prima'  => $orfaniPrima,
				'orfani_dopo'   => $orfaniDopo,
			),
		);
	}

	/**
	 * Salva il piano sul database.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit.
	 * @param array $piano   Piano.
	 * @return int
	 */
	public static function salva( Db $db, $auditId, array $piano ) {
		$righe = array();

		foreach ( $piano as $l ) {
			$righe[] = array(
				'audit_id'  => $auditId,
				'da'        => $l['da'],
				'da_titolo' => $l['da_titolo'],
				'a'         => $l['a'],
				'a_titolo'  => $l['a_titolo'],
				'anchor'    => $l['anchor'],
				'motivo'    => $l['motivo'],
				'punteggio' => $l['punteggio'],
			);
		}

		return $db->insertMany( 'link_piano', $righe );
	}
}
