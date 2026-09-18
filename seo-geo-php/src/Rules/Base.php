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
	 * Se il sito stampa gia questa cosa nella testata di ogni pagina.
	 *
	 * I dati strutturati, il canonical, le direttive robots e l Open Graph
	 * non stanno nel testo degli articoli: li mette il plugin nel <head> al
	 * momento di servire la pagina. Cercarli nel contenuto dava un rilievo
	 * critico su tutti i contenuti che non si poteva chiudere in nessun
	 * modo - nemmeno riscrivendo gli articoli. E infatti il modello, a cui
	 * quel rilievo veniva passato, ha provato a "risolverlo" scrivendo il
	 * JSON-LD dentro all articolo.
	 *
	 * Quando l analisi viene da un export il dato non c e, e le regole
	 * segnalano come prima: di quel sito non si sa che cosa serva.
	 *
	 * @param Site   $s    Sito.
	 * @param string $cosa Chiave dichiarata dal plugin.
	 * @return bool
	 */
	public static function loFaIlSito( Site $s, $cosa ) {
		return ! empty( $s->stampa[ $cosa ] );
	}

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
		foreach ( self::luoghi() as $c ) {
			if ( '' !== $c && false !== mb_stripos( (string) $testo, $c ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * La geografia del sito che si sta analizzando.
	 *
	 * Era scritta dentro alle regole: «palermo», «sicilia», «monreale». Su
	 * questo sito funzionava, su qualunque altro le regole locali dicevano
	 * cose senza senso - una landing di Bolzano non cita mai Palermo, e
	 * risultava sempre sbagliata. Adesso arriva dalle impostazioni, e i
	 * valori di prima restano come ripiego: cosi nessuna analisi gia fatta
	 * cambia risultato.
	 *
	 * @var array
	 */
	private static $geografia = array();

	/**
	 * Dice alle regole dove si trova questo sito.
	 *
	 * Si chiama una volta prima di eseguire l audit. Non e un bel modo di
	 * passare un valore - lo stato statico non lo e mai - ma le regole sono
	 * settantacinque chiusure che ricevono solo il sito, e cambiarne la
	 * firma per un dato che serve a tre di loro costerebbe piu di quanto
	 * renda.
	 *
	 * @param array $cfg Configurazione completa.
	 * @return void
	 */
	public static function configura( array $cfg ) {
		$seo = (array) ( $cfg['seo'] ?? array() );

		self::$geografia = array(
			'citta'  => trim( (string) ( $seo['cittaPrincipale'] ?? '' ) ),
			'comuni' => array_values( array_filter( array_map( 'trim', (array) ( $seo['comuniVicini'] ?? array() ) ) ) ),
			'zona'   => array_values( array_filter( array_map( 'trim', (array) ( $seo['zona'] ?? array() ) ) ) ),
		);
	}

	/**
	 * La citta principale, minuscola.
	 *
	 * @return string
	 */
	public static function citta() {
		return mb_strtolower( (string) ( self::$geografia['citta'] ?? '' ) ) ?: 'palermo';
	}

	/**
	 * I comuni intorno, minuscoli.
	 *
	 * @return string[]
	 */
	public static function comuni() {
		$comuni = (array) ( self::$geografia['comuni'] ?? array() );

		if ( ! $comuni ) {
			$comuni = array( 'Monreale', 'Bagheria', 'Carini', 'Cefalù', 'Termini Imerese', 'Partinico', 'Misilmeri' );
		}

		return array_map( 'mb_strtolower', $comuni );
	}

	/**
	 * Tutti i nomi di luogo che rendono locale una parola chiave.
	 *
	 * @return string[]
	 */
	public static function luoghi() {
		$zona = (array) ( self::$geografia['zona'] ?? array() );

		if ( ! $zona ) {
			$zona = array( 'Sicilia', 'siciliana', 'siciliano' );
		}

		return array_merge( array( self::citta() ), self::comuni(), array_map( 'mb_strtolower', $zona ) );
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
