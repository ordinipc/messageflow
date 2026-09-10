<?php
/**
 * Helper condivisi dalle regole.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Rules;

use SeoGeo\Site;

/**
 * Costruttori di problemi e piccole utility ricorrenti.
 */
class Base {

	const CRITICO = 'critical';
	const ALTO    = 'high';
	const MEDIO   = 'medium';
	const BASSO   = 'low';

	/**
	 * Problema riferito a un documento.
	 *
	 * @param array  $doc       Documento.
	 * @param string $dettaglio Descrizione.
	 * @return array
	 */
	public static function doc( array $doc, $dettaglio ) {
		return array(
			'ref'       => $doc['percorso'],
			'titolo'    => $doc['titolo'],
			'tipo'      => $doc['tipo'],
			'dettaglio' => $dettaglio,
		);
	}

	/**
	 * Problema a livello di sito.
	 *
	 * @param string $dettaglio Descrizione.
	 * @return array
	 */
	public static function sito( $dettaglio ) {
		return array(
			'ref'       => '(sito)',
			'titolo'    => '',
			'tipo'      => 'site',
			'dettaglio' => $dettaglio,
		);
	}

	/**
	 * Il testo contiene un riferimento al territorio servito?
	 *
	 * @param string $testo Testo.
	 * @return bool
	 */
	public static function citaCitta( $testo ) {
		foreach ( array( 'palermo', 'sicilia', 'siciliana', 'siciliano', 'monreale', 'bagheria', 'cefalù' ) as $c ) {
			if ( false !== mb_stripos( (string) $testo, $c ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Il testo è formulato come una domanda?
	 *
	 * @param string $testo Testo.
	 * @return bool
	 */
	public static function eDomanda( $testo ) {
		$t = mb_strtolower( trim( (string) $testo ), 'UTF-8' );

		if ( '' === $t ) {
			return false;
		}

		if ( '?' === mb_substr( $t, -1 ) ) {
			return true;
		}

		foreach ( array( 'come', 'perché', 'perche', 'quanto', 'quali', 'quale', 'cosa', 'quando', 'dove', 'chi', 'conviene' ) as $q ) {
			if ( 0 === mb_strpos( $t, $q ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Documenti pubblicati che superano un filtro.
	 *
	 * @param Site     $site   Sito.
	 * @param callable $filtro Predicato.
	 * @return array[]
	 */
	public static function filtra( Site $site, callable $filtro ) {
		return array_values( array_filter( $site->pubblicati, $filtro ) );
	}
}
