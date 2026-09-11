<?php
/**
 * Metriche testuali tarate sull'italiano.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

/**
 * Conteggi, leggibilità, slug e confronto di similarità fra testi.
 */
class Text {

	/** @var string[] */
	public static $stopwords = array(
		'a', 'ad', 'agli', 'ai', 'al', 'alla', 'alle', 'allo', 'anche', 'che', 'chi', 'ci', 'coi', 'col', 'come', 'con',
		'cui', 'da', 'dagli', 'dai', 'dal', 'dalla', 'dalle', 'dallo', 'degli', 'dei', 'del', 'della', 'delle', 'dello',
		'di', 'e', 'ed', 'gli', 'ha', 'hai', 'hanno', 'ho', 'i', 'il', 'in', 'io', 'la', 'le', 'lo', 'loro', 'ma', 'me',
		'mi', 'ne', 'negli', 'nei', 'nel', 'nella', 'nelle', 'nello', 'noi', 'non', 'o', 'per', 'perche', 'piu', 'può',
		'quando', 'quanto', 'quella', 'quelle', 'quelli', 'quello', 'questa', 'queste', 'questi', 'questo', 'se', 'sei',
		'si', 'sia', 'siamo', 'sono', 'su', 'sugli', 'sui', 'sul', 'sulla', 'sulle', 'sullo', 'ti', 'tra', 'tu', 'tuo',
		'un', 'una', 'uno', 'vi', 'voi', 'essere', 'fare', 'tutto', 'tutti', 'molto', 'solo', 'ogni', 'dove', 'sua', 'suo',
	);

	/** @var string[] Parole che non devono chiudere una frase troncata. */
	private static $sospese = array(
		'e', 'ed', 'o', 'od', 'ma', 'se', 'che', 'chi', 'cui', 'di', 'del', 'della', 'dello', 'dei', 'degli', 'delle',
		'da', 'dal', 'dalla', 'a', 'al', 'alla', 'ai', 'agli', 'alle', 'in', 'nel', 'nella', 'nei', 'negli', 'nelle',
		'con', 'col', 'su', 'sul', 'sulla', 'per', 'tra', 'fra', 'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una',
		'anche', 'come', 'piu', 'più', 'ogni', 'quando', 'dove', 'mentre', 'oppure', 'inoltre', 'ovvero', 'cioè',
		// Aggettivi possessivi: un titolo che finisce con "per la Tua" è
		// tagliato quanto uno che finisce con "per la".
		'tuo', 'tua', 'tuoi', 'tue', 'suo', 'sua', 'suoi', 'sue', 'nostro', 'nostra', 'nostri', 'nostre',
	);

	/**
	 * Elenco delle parole di un testo, in minuscolo.
	 *
	 * @param string $testo Testo.
	 * @return string[]
	 */
	public static function words( $testo ) {
		$testo = mb_strtolower( (string) $testo, 'UTF-8' );
		$testo = preg_replace( '/[^\p{L}\p{N}\'\s-]+/u', ' ', $testo );

		return array_values( array_filter( preg_split( '/\s+/u', trim( $testo ) ) ) );
	}

	/**
	 * @param string $testo Testo.
	 * @return int
	 */
	public static function wordCount( $testo ) {
		return count( self::words( $testo ) );
	}

	/**
	 * Frasi con almeno tre parole.
	 *
	 * @param string $testo Testo.
	 * @return string[]
	 */
	public static function sentences( $testo ) {
		$parti = preg_split( '/[.!?]+(\s|$)/u', (string) $testo );
		$out   = array();

		foreach ( (array) $parti as $p ) {
			$p = trim( $p );
			if ( '' !== $p && count( preg_split( '/\s+/u', $p ) ) > 2 ) {
				$out[] = $p;
			}
		}

		return $out;
	}

	/**
	 * Indice Gulpease (0-100): leggibilità per la lingua italiana.
	 * Sotto le 30 parole il calcolo non è significativo e restituisce null.
	 *
	 * @param string $testo Testo.
	 * @return int|null
	 */
	public static function gulpease( $testo ) {
		$parole = self::words( $testo );
		$frasi  = self::sentences( $testo );

		if ( count( $parole ) < 30 || empty( $frasi ) ) {
			return null;
		}

		$lettere = mb_strlen( implode( '', $parole ), 'UTF-8' );
		$n       = count( $parole );
		$valore  = 89 + ( ( 300 * count( $frasi ) ) - ( 10 * $lettere ) ) / $n;

		return (int) max( 0, min( 100, round( $valore ) ) );
	}

	/**
	 * Slug pulito da una stringa qualsiasi.
	 *
	 * @param string $testo Testo.
	 * @return string
	 */
	public static function slugify( $testo ) {
		$s = mb_strtolower( (string) $testo, 'UTF-8' );
		$s = strtr(
			$s,
			array(
				'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
				'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
				'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', '’' => '', "'" => '',
			)
		);
		$s = preg_replace( '/[^a-z0-9]+/u', '-', $s );

		return trim( (string) $s, '-' );
	}

	/**
	 * Slug conciso: elimina le stopword e rispetta i limiti di lunghezza.
	 *
	 * @param string $titolo   Titolo di partenza.
	 * @param int    $maxParole Numero massimo di token.
	 * @param int    $maxChar   Lunghezza massima.
	 * @return string
	 */
	public static function shortSlug( $titolo, $maxParole = 6, $maxChar = 60 ) {
		$src = preg_replace( array( '/e-?commerce/iu', '/e-?mail/iu', '/on-?line/iu' ), array( 'ecommerce', 'email', 'online' ), (string) $titolo );
		$base = array_values( array_filter( explode( '-', self::slugify( $src ) ) ) );

		$tenute = array_values(
			array_filter(
				$base,
				static fn( $w ) => ! in_array( $w, self::$stopwords, true ) && mb_strlen( $w ) > 1
			)
		);

		$sorgente = count( $tenute ) >= 3 ? $tenute : $base;
		$out      = array();

		foreach ( array_slice( $sorgente, 0, $maxParole ) as $w ) {
			$prova = implode( '-', array_merge( $out, array( $w ) ) );
			if ( mb_strlen( $prova ) > $maxChar ) {
				break;
			}
			$out[] = $w;
		}

		if ( empty( $out ) ) {
			$out = array_slice( $sorgente, 0, 3 );
		}

		return implode( '-', $out );
	}

	/**
	 * Taglia un testo senza spezzare le parole.
	 *
	 * @param string $testo Testo.
	 * @param int    $max   Lunghezza massima.
	 * @return string
	 */
	public static function truncate( $testo, $max ) {
		$s = trim( preg_replace( '/\s+/u', ' ', (string) $testo ) );

		if ( mb_strlen( $s, 'UTF-8' ) <= $max ) {
			return $s;
		}

		$taglio = mb_substr( $s, 0, $max, 'UTF-8' );
		$spazio = mb_strrpos( $taglio, ' ', 0, 'UTF-8' );

		if ( false !== $spazio && $spazio > $max * 0.5 ) {
			$taglio = mb_substr( $taglio, 0, $spazio, 'UTF-8' );
		}

		return rtrim( $taglio, " ,;:.-–—" );
	}

	/**
	 * Ripulisce una frase troncata dalle parole sospese finali.
	 *
	 * @param string $testo Testo.
	 * @return string
	 */
	public static function polishClause( $testo ) {
		$s     = rtrim( trim( (string) $testo ), " ;:,.-–—" );
		$parti = preg_split( '/\s+/u', $s );

		while ( count( $parti ) > 2 ) {
			$ultima = mb_strtolower( preg_replace( '/[^\p{L}\']/u', '', end( $parti ) ), 'UTF-8' );
			if ( ! in_array( $ultima, self::$sospese, true ) ) {
				break;
			}
			array_pop( $parti );
		}

		return rtrim( implode( ' ', $parti ), " ;:,.-–—" );
	}

	/**
	 * Insieme di shingle di N parole, per il confronto fra documenti.
	 *
	 * @param string $testo   Testo.
	 * @param int    $ampiezza Parole per shingle.
	 * @return array<string,bool>
	 */
	public static function shingles( $testo, $ampiezza = 5 ) {
		$parole = self::words( $testo );
		$set    = array();
		$n      = count( $parole );

		for ( $i = 0; $i + $ampiezza <= $n; $i++ ) {
			$set[ implode( ' ', array_slice( $parole, $i, $ampiezza ) ) ] = true;
		}

		return $set;
	}

	/**
	 * Indice di Jaccard fra due insiemi di shingle.
	 *
	 * @param array $a Primo insieme.
	 * @param array $b Secondo insieme.
	 * @return float
	 */
	public static function jaccard( array $a, array $b ) {
		if ( empty( $a ) || empty( $b ) ) {
			return 0.0;
		}

		$intersezione = count( array_intersect_key( $a, $b ) );
		$unione       = count( $a ) + count( $b ) - $intersezione;

		return $unione > 0 ? $intersezione / $unione : 0.0;
	}

	/**
	 * Termini più frequenti, escluse le stopword.
	 *
	 * @param string $testo Testo.
	 * @param int    $n     Quanti restituirne.
	 * @return array<string,int>
	 */
	public static function topTerms( $testo, $n = 12 ) {
		$freq = array();

		foreach ( self::words( $testo ) as $w ) {
			if ( mb_strlen( $w ) < 4 || in_array( $w, self::$stopwords, true ) ) {
				continue;
			}
			$freq[ $w ] = ( $freq[ $w ] ?? 0 ) + 1;
		}

		arsort( $freq );

		return array_slice( $freq, 0, $n, true );
	}
}
