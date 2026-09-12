<?php
/**
 * Indirizzi cambiati fra un analisi e l altra.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

/**
 * Quando un contenuto cambia titolo, WordPress non cambia lo slug da solo — ma
 * chi modifica un articolo spesso lo cambia a mano senza pensarci. Da quel
 * momento il vecchio indirizzo dà 404: i link che arrivano da fuori si
 * perdono, e la posizione in Google riparte da zero.
 *
 * Il programma ha in archivio l indirizzo di ogni contenuto a ogni analisi:
 * basta confrontarli per accorgersene, e il 301 lo sa già fare.
 */
class Redirezioni {

	/**
	 * Contenuti che oggi rispondono a un indirizzo diverso da prima.
	 *
	 * Il confronto è per identificativo di WordPress, non per indirizzo: è
	 * l unica cosa che non cambia quando si rinomina un contenuto.
	 *
	 * @param Db     $db   Database.
	 * @param string $sito Indirizzo del sito.
	 * @return array[] 'wp_id', 'titolo', 'da', 'a', 'quando'.
	 */
	public static function cambiati( Db $db, $sito ) {
		$esito = self::confronto( $db, $sito );

		return $esito['cambiati'];
	}

	/**
	 * Il confronto per esteso: cosa è stato messo a confronto e cosa ne è
	 * uscito.
	 *
	 * Non mostrare niente quando non si trova niente costringe chi guarda a
	 * indovinare se il programma ha controllato o no. Qui si dice.
	 *
	 * @param Db     $db   Database.
	 * @param string $sito Indirizzo del sito.
	 * @return array 'cambiati', 'motivo', 'ultima', 'prima', 'confrontati'.
	 */
	public static function confronto( Db $db, $sito ) {
		$vuoto = array( 'cambiati' => array(), 'motivo' => '', 'ultima' => null, 'prima' => null, 'confrontati' => 0 );

		// Le analisi si riconoscono dal dominio, non dalla stringa esatta:
		// "https://sito.it" e "https://sito.it/" sono lo stesso sito, e un
		// confronto che fallisce per una barra è un confronto che non avviene.
		$host = self::host( $sito );

		if ( '' === $host ) {
			return array( 'motivo' => 'Indirizzo del sito non riconosciuto.' ) + $vuoto;
		}

		$analisi = array();

		foreach ( $db->all( 'SELECT id, sito_url, creato_il FROM audit ORDER BY id ASC' ) as $riga ) {
			if ( self::host( $riga['sito_url'] ) === $host ) {
				$analisi[] = $riga;
			}
		}

		if ( count( $analisi ) < 2 ) {
			return array(
				'motivo' => 'Serve almeno una seconda analisi da confrontare: apri "Rileggi il sito e ricalcola".',
			) + $vuoto;
		}

		$ultima = end( $analisi );
		$ids    = array_column( array_slice( $analisi, 0, -1 ), 'id' );

		$prima = array();

		foreach ( $db->all(
			"SELECT d.wp_id, d.percorso, d.titolo, d.audit_id FROM documento d
			 WHERE d.audit_id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ") AND d.wp_id <> ''
			 ORDER BY d.audit_id ASC"
		) as $riga ) {
			// Il primo che si incontra è il più vecchio: gli altri non lo
			// sostituiscono.
			if ( ! isset( $prima[ $riga['wp_id'] ] ) ) {
				$prima[ $riga['wp_id'] ] = $riga;
			}
		}

		$adesso = $db->all( "SELECT wp_id, percorso, titolo FROM documento WHERE audit_id = ? AND wp_id <> ''", array( $ultima['id'] ) );

		if ( ! $prima || ! $adesso ) {
			return array(
				'motivo' => 'Le analisi non contengono gli identificativi di WordPress: rileggi il sito con il plugin collegato.',
				'ultima' => $ultima,
				'prima'  => $analisi[0],
			) + $vuoto;
		}

		$cambiati    = array();
		$confrontati = 0;

		foreach ( $adesso as $riga ) {
			$vecchio = $prima[ $riga['wp_id'] ] ?? null;

			if ( ! $vecchio ) {
				continue;
			}

			$confrontati++;

			$da = self::normalizza( $vecchio['percorso'] );
			$a  = self::normalizza( $riga['percorso'] );

			if ( '' === $da || '' === $a || $da === $a ) {
				continue;
			}

			$cambiati[] = array(
				'wp_id'  => $riga['wp_id'],
				'titolo' => $riga['titolo'],
				'da'     => $da,
				'a'      => $a,
				'quando' => $ultima['creato_il'],
			);
		}

		if ( ! $confrontati ) {
			return array(
				'motivo' => 'Nessun contenuto in comune fra le analisi: gli identificativi non coincidono.',
				'ultima' => $ultima,
				'prima'  => $analisi[0],
			) + $vuoto;
		}

		return array(
			'cambiati'    => $cambiati,
			'motivo'      => $cambiati ? '' : 'Nessun indirizzo è cambiato: tutti i contenuti rispondono dove rispondevano prima.',
			'ultima'      => $ultima,
			'prima'       => $analisi[0],
			'confrontati' => $confrontati,
		);
	}

	/**
	 * Dominio di un indirizzo, senza www.
	 *
	 * @param string $url Indirizzo.
	 * @return string
	 */
	private static function host( $url ) {
		$host = (string) parse_url( (string) $url, PHP_URL_HOST );

		if ( '' === $host ) {
			$host = preg_replace( '~^(https?://)?([^/]+).*$~i', '$2', (string) $url );
		}

		return strtolower( preg_replace( '~^www\.~i', '', (string) $host ) );
	}

	/**
	 * Percorso confrontabile, con la barra davanti e senza quella finale.
	 *
	 * @param string $percorso Percorso.
	 * @return string
	 */
	private static function normalizza( $percorso ) {
		$percorso = trim( (string) $percorso );

		if ( '' === $percorso ) {
			return '';
		}

		return '/' . trim( (string) wp_parse_url_percorso( $percorso ), '/' );
	}
}

/**
 * Percorso di un indirizzo, che sia già un percorso o un indirizzo completo.
 *
 * @param string $valore Indirizzo o percorso.
 * @return string
 */
function wp_parse_url_percorso( $valore ) {
	$percorso = parse_url( (string) $valore, PHP_URL_PATH );

	return null === $percorso ? (string) $valore : (string) $percorso;
}
