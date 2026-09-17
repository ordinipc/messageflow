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
	/**
	 * Toglie dall elenco i redirect che sul sito sono gia attivi.
	 *
	 * Se il sito non risponde si lascia l elenco com e: meglio un avviso di
	 * troppo che nascondere un indirizzo rotto perche il collegamento era
	 * giu per un minuto.
	 *
	 * @param array $cambiati Indirizzi cambiati.
	 * @param mixed $ponte    Collegamento al sito, se c e.
	 * @return array
	 */
	private static function senzaQuelliGiaFatti( array $cambiati, $ponte ) {
		if ( ! $cambiati || ! $ponte || ! method_exists( $ponte, 'redirectAttivi' ) || ! $ponte->pronto() ) {
			return $cambiati;
		}

		try {
			$risposta = $ponte->redirectAttivi();
		} catch ( \Throwable $e ) {
			return $cambiati;
		}

		$attivi = array();

		foreach ( (array) ( $risposta['percorsi'] ?? array() ) as $percorso ) {
			$attivi[ self::normalizza( (string) $percorso ) ] = true;
		}

		if ( ! $attivi ) {
			return $cambiati;
		}

		return array_values(
			array_filter(
				$cambiati,
				static function ( $riga ) use ( $attivi ) {
					return ! isset( $attivi[ self::normalizza( (string) $riga['da'] ) ] );
				}
			)
		);
	}

	/**
	 * Gli indirizzi che Google mostra e che il sito non ha piu.
	 *
	 * Il confronto fra due analisi trova solo i contenuti che sono stati
	 * rinominati mentre il programma guardava. Gli indirizzi rotti piu
	 * costosi sono altri: quelli che Google ha in memoria da prima della
	 * prima analisi, e che oggi danno pagina non trovata. Il programma non
	 * puo dedurli - non li ha mai visti - ma Search Console si, e dice anche
	 * quante impression valgono.
	 *
	 * Si controlla che diano davvero 404 prima di proporre un redirect: un
	 * indirizzo che risponde bene e semplicemente un contenuto pubblicato
	 * dopo l ultima lettura, e mandarlo altrove sarebbe un danno.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Analisi da cui prendere i contenuti di oggi.
	 * @param array $pagine  Righe pagina di Search Console: 'url', 'impression', 'clic'.
	 * @param int   $quanti  Quanti indirizzi controllare al massimo.
	 * @return array[] 'da', 'a', 'titolo', 'impression', 'clic', 'perche'.
	 */
	public static function orfaniDaGoogle( Db $db, $auditId, array $pagine, $quanti = 25 ) {
		$documenti = $db->all(
			"SELECT percorso, titolo FROM documento WHERE audit_id = ? AND stato = 'publish' AND percorso <> ''",
			array( (int) $auditId )
		);

		if ( ! $documenti ) {
			return array();
		}

		$vivi = array();

		foreach ( $documenti as $d ) {
			$vivi[ self::normalizza( $d['percorso'] ) ] = $d;
		}

		// Prima quelli che valgono di piu: se si deve tagliare, si taglia da
		// quelli che portano meno gente.
		usort(
			$pagine,
			static function ( $a, $b ) {
				return (int) ( $b['impression'] ?? 0 ) <=> (int) ( $a['impression'] ?? 0 );
			}
		);

		$fuori = array();

		foreach ( $pagine as $riga ) {
			if ( count( $fuori ) >= (int) $quanti ) {
				break;
			}

			$da = self::normalizza( (string) parse_url( (string) ( $riga['url'] ?? '' ), PHP_URL_PATH ) );

			if ( '' === $da || '/' === $da || isset( $vivi[ $da ] ) ) {
				continue;
			}

			$come = \SeoGeo\Search\Azioni::traduci( self::stato( (string) $riga['url'] ) );

			if ( 'redirect' !== ( $come['azione'] ?? '' ) ) {
				continue;
			}

			$dove = self::destinazione( $da, $vivi );

			$fuori[] = array(
				'da'         => $da,
				'a'          => $dove['percorso'],
				'titolo'     => $dove['titolo'],
				'impression' => (int) ( $riga['impression'] ?? 0 ),
				'clic'       => (int) ( $riga['clic'] ?? 0 ),
				'perche'     => $dove['perche'],
			);
		}

		return $fuori;
	}

	/**
	 * Dove mandare un indirizzo morto.
	 *
	 * Si sceglie il contenuto vivo che gli somiglia di piu nelle parole
	 * dell indirizzo: e il criterio che un redirect deve rispettare, perche
	 * mandare tutto in home fa perdere comunque la posizione. Quando non
	 * somiglia a niente si dice, e si propone la home: e una scelta che va
	 * guardata, non una che si applica a occhi chiusi.
	 *
	 * @param string $morto Percorso che non risponde piu.
	 * @param array  $vivi  Percorso => documento.
	 * @return array 'percorso', 'titolo', 'perche'.
	 */
	public static function destinazione( $morto, array $vivi ) {
		$parole = self::parole( $morto );
		$meglio = array( 'percorso' => '/', 'titolo' => '', 'perche' => 'nessun contenuto somigliante: proposta la home, guardala prima di applicare' );
		$punti  = 0.0;

		foreach ( $vivi as $percorso => $doc ) {
			$sue = self::parole( $percorso );

			if ( ! $sue || ! $parole ) {
				continue;
			}

			$comuni = count( array_intersect_key( $parole, $sue ) );
			$quanto = $comuni / max( count( $parole ), count( $sue ) );

			if ( $quanto > $punti ) {
				$punti  = $quanto;
				$meglio = array(
					'percorso' => $percorso,
					'titolo'   => (string) $doc['titolo'],
					'perche'   => 'parole in comune nell indirizzo: ' . $comuni,
				);
			}
		}

		return $punti >= 0.34
			? $meglio
			: array( 'percorso' => '/', 'titolo' => '', 'perche' => 'nessun contenuto somigliante: proposta la home, guardala prima di applicare' );
	}

	/**
	 * Le parole di un percorso, senza quelle che non distinguono niente.
	 *
	 * @param string $percorso Percorso.
	 * @return array<string,bool>
	 */
	private static function parole( $percorso ) {
		$fuori = array();

		foreach ( preg_split( '/[^a-z0-9]+/i', mb_strtolower( (string) $percorso ) ) as $p ) {
			if ( mb_strlen( $p ) > 2 && ! in_array( $p, array( 'per', 'del', 'della', 'con', 'una', 'the', 'and' ), true ) ) {
				$fuori[ $p ] = true;
			}
		}

		return $fuori;
	}

	/**
	 * Che cosa risponde un indirizzo, adesso.
	 *
	 * @param string $url Indirizzo.
	 * @return int Codice HTTP, 0 se non si e capito.
	 */
	private static function stato( $url ) {
		$ch = curl_init( (string) $url );

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_NOBODY         => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_TIMEOUT        => 6,
				CURLOPT_USERAGENT      => 'SeoGeoAudit',
			)
		);

		curl_exec( $ch );
		$stato = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		return $stato;
	}

	public static function confronto( Db $db, $sito, $ponte = null ) {
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

		// Quelli gia sistemati non si segnalano piu.
		//
		// L avviso nasce dal confronto fra due analisi archiviate, e attivare
		// il redirect non cambia ne l una ne l altra: senza questo, «un
		// contenuto ha cambiato indirizzo» restava li per sempre anche dopo
		// averlo sistemato. Non si tiene un segno a parte, si chiede al sito:
		// conta quello che e attivo adesso, non quello che si ricorda di aver
		// premuto.
		$cambiati = self::senzaQuelliGiaFatti( $cambiati, $ponte );

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
