<?php
/**
 * Dai segnali di Google alle modifiche sul contenuto giusto.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Search;

use SeoGeo\Coda;
use SeoGeo\Db;
use SeoGeo\Text;

/**
 * Un segnale dice "questa pagina è a un passo dalla prima pagina". Da solo non
 * serve a niente: bisogna sapere quale contenuto del sito è, e cosa farci.
 *
 * Qui i due mondi vengono uniti — l indirizzo che arriva da Search Console e il
 * contenuto letto dal sito — e ne escono compiti che il pilota sa eseguire.
 */
class Azioni {

	/**
	 * Cosa si sa fare, per ogni tipo di segnale.
	 *
	 * Chi non compare qui non ha un azione automatica sensata: "pagina in calo"
	 * e "mai mostrata" vogliono un occhio umano, e fingere il contrario
	 * significherebbe far scrivere all AI sopra un problema che non ha capito.
	 *
	 * @var array<string,array>
	 */
	const AZIONI = array(
		'titolo_che_non_rende' => array(
			'compito'  => 'meta_mirata',
			'titolo'   => 'Riscrivi title e description sulla ricerca vera',
			'perche'   => 'La pagina è già in alto: cambia la vetrina, non il contenuto.',
			'serve_ai' => false,
		),
		'quasi_prima_pagina'   => array(
			'compito'  => 'bozza',
			'titolo'   => 'Riscrivi e rafforza il contenuto',
			'perche'   => 'Poche posizioni dalla prima pagina: è il lavoro che rende di più.',
			'serve_ai' => true,
		),
		'cannibalizzazione'    => array(
			'compito'  => 'accorpa',
			'titolo'   => 'Unisci le pagine che competono',
			'perche'   => 'Due pagine sulla stessa ricerca si tolgono forza a vicenda.',
			'serve_ai' => true,
		),
	);

	/**
	 * Abbina ogni segnale al contenuto del sito a cui si riferisce.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit da cui prendere i contenuti.
	 * @param array $segnali Segnali.
	 * @return array[] Gli stessi segnali con 'documento_id', 'wp_id', 'titolo_sito'.
	 */
	public static function abbina( Db $db, $auditId, array $segnali ) {
		$per_percorso = array();

		foreach ( $db->all( 'SELECT id, wp_id, titolo, percorso, url, tipo, pubblicato, modificato FROM documento WHERE audit_id = ?', array( (int) $auditId ) ) as $documento ) {
			foreach ( array( $documento['percorso'], $documento['url'] ) as $chiave ) {
				$normale = self::percorso( $chiave );

				if ( '' !== $normale ) {
					$per_percorso[ $normale ] = $documento;
				}
			}
		}

		foreach ( $segnali as &$segnale ) {
			$documento = $per_percorso[ self::percorso( $segnale['url'] ) ] ?? null;

			$segnale['documento_id'] = $documento ? (int) $documento['id'] : 0;
			$segnale['wp_id']        = $documento ? (string) $documento['wp_id'] : '';
			$segnale['titolo_sito']  = $documento ? (string) $documento['titolo'] : '';
			$segnale['tipo_sito']    = $documento ? (string) $documento['tipo'] : '';
			// Quando quel contenuto e stato toccato l ultima volta. «Se e
			// stata modificata di recente, guarda cosa e cambiato» e un
			// consiglio che senza la data non si puo seguire: non si sa se
			// «di recente» sia ieri o due anni fa.
			$segnale['modificato']   = $documento ? (string) ( $documento['modificato'] ?: $documento['pubblicato'] ) : '';
			$segnale['pubblicato']   = $documento ? (string) $documento['pubblicato'] : '';
		}

		unset( $segnale );

		return $segnali;
	}

	/**
	 * Percorso confrontabile: senza dominio, senza barra finale, minuscolo.
	 *
	 * @param string $url Indirizzo o percorso.
	 * @return string
	 */
	public static function percorso( $url ) {
		$url = (string) $url;
		$url = preg_replace( '~^https?://[^/]+~i', '', $url );

		return '/' . strtolower( trim( (string) $url, '/' ) );
	}

	/**
	 * Che cosa risponde oggi un indirizzo che l analisi non conosce.
	 *
	 * «Non abbinata a un contenuto: rifai l analisi del sito» era un consiglio
	 * sbagliato per meta dei casi. Un indirizzo che Google mostra e che nel
	 * sito non c e puo essere due cose opposte: una pagina pubblicata dopo
	 * l ultima lettura - e allora si rilegge - oppure una pagina cancellata,
	 * e allora rileggere non serve a niente: quelle impression si perdono
	 * finche non le si manda da qualche parte con un redirect.
	 *
	 * Distinguerle non si puo dedurre: si chiede al sito.
	 *
	 * @param array $segnali Segnali gia abbinati.
	 * @param int   $quanti  Quanti indirizzi controllare al massimo.
	 * @return array<string,array> url => 'stato', 'dice', 'azione'.
	 */
	public static function statoDegliSconosciuti( array $segnali, $quanti = 8 ) {
		$fuori = array();
		$visti = array();

		foreach ( $segnali as $segnale ) {
			if ( ! empty( $segnale['documento_id'] ) || count( $visti ) >= (int) $quanti ) {
				continue;
			}

			$url = (string) ( $segnale['url'] ?? '' );

			if ( '' === $url || isset( $visti[ $url ] ) ) {
				continue;
			}

			$visti[ $url ] = true;
			$fuori[ $url ] = self::comeRisponde( $url );
		}

		return $fuori;
	}

	/**
	 * Una sola richiesta, e la sua traduzione in italiano.
	 *
	 * @param string $url Indirizzo.
	 * @return array 'stato', 'dice', 'azione'.
	 */
	private static function comeRisponde( $url ) {
		$ch = curl_init( $url );

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

		return array( 'stato' => $stato ) + self::traduci( $stato );
	}

	/**
	 * @param int $stato Codice HTTP.
	 * @return array 'dice', 'azione'.
	 */
	public static function traduci( $stato ) {
		$stato = (int) $stato;

		if ( 404 === $stato || 410 === $stato ) {
			return array(
				'dice'   => 'questa pagina non esiste più: le impression qui accanto si perdono tutte',
				'azione' => 'redirect',
			);
		}

		if ( $stato >= 300 && $stato < 400 ) {
			return array( 'dice' => 'è già reindirizzata altrove: non c è altro da fare', 'azione' => '' );
		}

		if ( 200 === $stato ) {
			return array(
				'dice'   => 'la pagina c è ed è viva: manca solo dall analisi, che è più vecchia. Rileggi il sito',
				'azione' => 'rileggi',
			);
		}

		if ( 0 === $stato ) {
			return array( 'dice' => 'il sito non ha risposto: riprova più tardi', 'azione' => '' );
		}

		return array( 'dice' => 'il sito risponde ' . $stato . ': è un errore del server, non un problema di SEO', 'azione' => '' );
	}

	/**
	 * Compone il piano: un compito per ogni segnale che sa dove andare a parare.
	 *
	 * Un contenuto riceve un compito solo: se è insieme "a un passo" e
	 * "cannibalizzato", conta il segnale con la priorità più alta. Riscrivere e
	 * accorpare lo stesso articolo nello stesso giro significherebbe lavorare
	 * due volte sopra sé stessi.
	 *
	 * @param array $segnali Segnali già abbinati.
	 * @param array $opzioni 'pagine' => true per toccare anche le pagine.
	 * @return array[]
	 */
	public static function piano( array $segnali, array $opzioni = array() ) {
		$piano = array();
		$presi = array();

		foreach ( $segnali as $segnale ) {
			$azione = self::AZIONI[ $segnale['tipo'] ] ?? null;

			if ( ! $azione || ! $segnale['documento_id'] || isset( $presi[ $segnale['documento_id'] ] ) ) {
				continue;
			}

			// Le pagine servizio sono poche e scritte a mano: restano fuori a
			// meno che non lo si chieda espressamente, come per il resto del
			// programma. Vale anche quando è Google a segnalarle.
			if ( 'page' === ( $segnale['tipo_sito'] ?? '' ) && empty( $opzioni['pagine'] ) ) {
				continue;
			}

			$presi[ $segnale['documento_id'] ] = true;

			$piano[] = array(
				'compito'     => $azione['compito'],
				'titolo'      => $azione['titolo'],
				'perche'      => $azione['perche'],
				'serve_ai'    => $azione['serve_ai'],
				'tipo'        => $segnale['tipo'],
				'documento_id' => (int) $segnale['documento_id'],
				'wp_id'       => $segnale['wp_id'],
				'titolo_sito' => $segnale['titolo_sito'],
				'query'       => (string) $segnale['query'],
				'impression'  => (int) $segnale['impression'],
				'posizione'   => (float) $segnale['posizione'],
				'priorita'    => (int) $segnale['priorita'],
			);
		}

		return $piano;
	}

	/**
	 * Quante pagine sarebbero toccate, se lo si chiedesse.
	 *
	 * @param array $segnali Segnali già abbinati.
	 * @return int
	 */
	public static function pagineEscluse( array $segnali ) {
		$viste = array();

		foreach ( $segnali as $segnale ) {
			if ( ! isset( self::AZIONI[ $segnale['tipo'] ] ) || empty( $segnale['documento_id'] ) ) {
				continue;
			}

			if ( 'page' === ( $segnale['tipo_sito'] ?? '' ) ) {
				$viste[ $segnale['documento_id'] ] = true;
			}
		}

		return count( $viste );
	}

	/**
	 * Scrive il piano nella coda del pilota automatico.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit.
	 * @param array $piano   Piano da eseguire.
	 * @return array 'totale' e conteggio per tipo di compito.
	 */
	public static function inCoda( Db $db, $auditId, array $piano ) {
		$db->run( 'DELETE FROM coda WHERE audit_id = ?', array( (int) $auditId ) );

		$ora    = date( 'Y-m-d H:i:s' );
		$righe  = array();
		$ordine = 0;

		foreach ( $piano as $voce ) {
			$righe[] = array(
				'audit_id'    => (int) $auditId,
				'ordine'      => ++$ordine,
				'tipo'        => $voce['compito'],
				'riferimento' => (string) $voce['documento_id'],
				'dettaglio'   => (string) $voce['query'],
				'etichetta'   => $voce['titolo'] . ': "' . Text::truncate( $voce['titolo_sito'], 50 ) . '"'
					. ( '' !== $voce['query'] ? ' — ricerca "' . $voce['query'] . '"' : '' ),
				'origine'     => 'google',
				'stato'       => Coda::ATTESA,
				'messaggio'   => '',
				'creato_il'   => $ora,
				'eseguito_il' => '',
			);
		}

		if ( $righe ) {
			$db->insertMany( 'coda', $righe );
		}

		$per_tipo = array();

		foreach ( $righe as $riga ) {
			$per_tipo[ $riga['tipo'] ] = ( $per_tipo[ $riga['tipo'] ] ?? 0 ) + 1;
		}

		return array( 'totale' => count( $righe ), 'per_tipo' => $per_tipo );
	}
}
