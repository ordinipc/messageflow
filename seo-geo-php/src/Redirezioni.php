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
		$ultima = $db->one( 'SELECT id, creato_il FROM audit WHERE sito_url = ? ORDER BY id DESC LIMIT 1', array( $sito ) );

		if ( ! $ultima ) {
			return array();
		}

		// Il confronto è con il primo indirizzo mai registrato, non con quello
		// dell analisi precedente: se un contenuto è stato rinominato tre
		// analisi fa, il vecchio indirizzo è morto lo stesso e va rimandato
		// sul nuovo. Confrontare solo le ultime due lo avrebbe mancato.
		$prima = array();

		foreach ( $db->all(
			"SELECT d.wp_id, d.percorso, d.titolo, a.id AS audit
			 FROM documento d JOIN audit a ON a.id = d.audit_id
			 WHERE a.sito_url = ? AND d.wp_id <> '' AND a.id < ?
			 ORDER BY a.id ASC",
			array( $sito, $ultima['id'] )
		) as $riga ) {
			// Il primo che si incontra è il più vecchio: gli altri non lo
			// sostituiscono.
			if ( ! isset( $prima[ $riga['wp_id'] ] ) ) {
				$prima[ $riga['wp_id'] ] = $riga;
			}
		}

		if ( ! $prima ) {
			return array();
		}

		$cambiati = array();

		foreach ( $db->all( "SELECT wp_id, percorso, titolo FROM documento WHERE audit_id = ? AND wp_id <> ''", array( $ultima['id'] ) ) as $riga ) {
			$vecchio = $prima[ $riga['wp_id'] ] ?? null;

			if ( ! $vecchio ) {
				continue;
			}

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

		return $cambiati;
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
