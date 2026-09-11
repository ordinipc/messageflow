<?php
/**
 * Rendimento reale del sito in Google.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Search;

use RuntimeException;
use SeoGeo\Db;
use SeoGeo\Google\SearchConsole;
use SeoGeo\Google\ServiceAccount;
use SeoGeo\Impostazioni;

/**
 * Scarica i dati di Search Console, li conserva e li trasforma in priorità.
 *
 * Da qui in poi il programma non lavora più solo sulle regole: lavora su cosa
 * succede davvero nelle ricerche.
 */
class Prestazioni {

	/**
	 * Costruisce il client dalle impostazioni salvate.
	 *
	 * @param array $cfg Configurazione completa.
	 * @return SearchConsole
	 * @throws RuntimeException Se manca la chiave o la proprietà.
	 */
	public static function client( array $cfg ) {
		$google = $cfg['google'] ?? array();
		$json   = (string) ( $google['chiave_json'] ?? '' );

		if ( '' === trim( $json ) ) {
			throw new RuntimeException(
				'Manca la chiave dell account di servizio Google. Impostazioni → Search Console: '
				. 'incolla lì il file JSON scaricato da Google Cloud.'
			);
		}

		$proprieta = (string) ( $google['proprieta'] ?? '' );

		if ( '' === trim( $proprieta ) ) {
			throw new RuntimeException( 'Manca la proprietà di Search Console (per esempio sc-domain:tuosito.it).' );
		}

		return new SearchConsole( new ServiceAccount( ServiceAccount::daJson( $json ) ), $proprieta );
	}

	/**
	 * Indirizzo con cui il sito viene identificato nelle rilevazioni.
	 *
	 * Deve essere lo stesso quando si salva e quando si rilegge, altrimenti le
	 * rilevazioni ci sono ma non si trovano più.
	 *
	 * @param array $cfg Configurazione.
	 * @return string
	 */
	public static function chiaveSito( array $cfg ) {
		foreach ( array( $cfg['azienda']['sito'] ?? '', $cfg['wordpress']['url'] ?? '' ) as $candidato ) {
			$candidato = rtrim( trim( (string) $candidato ), '/' );

			if ( '' !== $candidato ) {
				return $candidato;
			}
		}

		return (string) ( $cfg['google']['proprieta'] ?? '' );
	}

	/**
	 * Configurata o no.
	 *
	 * @param array $cfg Configurazione.
	 * @return bool
	 */
	public static function configurata( array $cfg ) {
		$google = $cfg['google'] ?? array();

		return '' !== trim( (string) ( $google['chiave_json'] ?? '' ) )
			&& '' !== trim( (string) ( $google['proprieta'] ?? '' ) );
	}

	/**
	 * Scarica il periodo richiesto e quello precedente, per il confronto.
	 *
	 * @param SearchConsole $console Client.
	 * @param int           $giorni  Ampiezza del periodo.
	 * @return array 'pagine', 'query', 'pagine_prec', 'periodo'.
	 */
	public static function scarica( SearchConsole $console, $giorni = 28 ) {
		$giorni = max( 7, min( 180, (int) $giorni ) );

		// Google consolida i dati con circa due giorni di ritardo: partire da
		// ieri darebbe numeri sistematicamente più bassi del vero.
		$a  = gmdate( 'Y-m-d', strtotime( '-3 days' ) );
		$da = gmdate( 'Y-m-d', strtotime( '-3 days -' . ( $giorni - 1 ) . ' days' ) );

		$a_prec  = gmdate( 'Y-m-d', strtotime( $da . ' -1 day' ) );
		$da_prec = gmdate( 'Y-m-d', strtotime( $a_prec . ' -' . ( $giorni - 1 ) . ' days' ) );

		return array(
			'periodo'     => array( 'da' => $da, 'a' => $a, 'da_prec' => $da_prec, 'a_prec' => $a_prec, 'giorni' => $giorni ),
			'pagine'      => $console->rendimento( array( 'da' => $da, 'a' => $a, 'dimensioni' => array( 'page' ) ) ),
			'query'       => $console->rendimento( array( 'da' => $da, 'a' => $a, 'dimensioni' => array( 'query', 'page' ) ) ),
			'pagine_prec' => $console->rendimento( array( 'da' => $da_prec, 'a' => $a_prec, 'dimensioni' => array( 'page' ) ) ),
		);
	}

	/**
	 * Salva rilevazione, righe e segnali.
	 *
	 * @param Db     $db       Database.
	 * @param string $sito     Indirizzo del sito.
	 * @param array  $dati     Risultato di scarica().
	 * @param array  $segnali  Risultato di Segnali::deriva().
	 * @return int Identificativo della rilevazione.
	 */
	public static function salva( Db $db, $sito, array $dati, array $segnali ) {
		$clic       = array_sum( array_column( $dati['pagine'], 'clic' ) );
		$impression = array_sum( array_column( $dati['pagine'], 'impression' ) );
		$posizioni  = array_column( $dati['pagine'], 'posizione' );

		$id = $db->insert(
			'gsc_rilevazione',
			array(
				'sito_url'        => $sito,
				'proprieta'       => (string) ( $dati['proprieta'] ?? '' ),
				'creato_il'       => date( 'Y-m-d H:i:s' ),
				'periodo_da'      => $dati['periodo']['da'],
				'periodo_a'       => $dati['periodo']['a'],
				'giorni'          => (int) $dati['periodo']['giorni'],
				'clic'            => (int) $clic,
				'impression'      => (int) $impression,
				'posizione_media' => $posizioni ? round( array_sum( $posizioni ) / count( $posizioni ), 1 ) : 0,
				'pagine'          => count( $dati['pagine'] ),
				'query'           => count( $dati['query'] ),
			)
		);

		$righe = array();

		foreach ( $dati['pagine'] as $riga ) {
			$righe[] = array(
				'rilevazione_id' => $id,
				'url'            => $riga['page'] ?? '',
				'clic'           => (int) $riga['clic'],
				'impression'     => (int) $riga['impression'],
				'ctr'            => (float) $riga['ctr'],
				'posizione'      => (float) $riga['posizione'],
			);
		}

		if ( $righe ) {
			$db->insertMany( 'gsc_pagina', $righe );
		}

		$righe = array();

		// Le query sono migliaia: si conservano solo quelle che contano
		// qualcosa, altrimenti il database cresce senza dare informazione.
		foreach ( $dati['query'] as $riga ) {
			if ( (int) $riga['impression'] < 3 ) {
				continue;
			}

			$righe[] = array(
				'rilevazione_id' => $id,
				'query'          => $riga['query'] ?? '',
				'url'            => $riga['page'] ?? '',
				'clic'           => (int) $riga['clic'],
				'impression'     => (int) $riga['impression'],
				'ctr'            => (float) $riga['ctr'],
				'posizione'      => (float) $riga['posizione'],
			);
		}

		if ( $righe ) {
			$db->insertMany( 'gsc_query', $righe );
		}

		$righe = array();

		foreach ( $segnali as $segnale ) {
			$righe[] = array(
				'rilevazione_id' => $id,
				'tipo'           => $segnale['tipo'],
				'titolo'         => $segnale['titolo'],
				'url'            => $segnale['url'],
				'query'          => $segnale['query'],
				'posizione'      => (float) $segnale['posizione'],
				'impression'     => (int) $segnale['impression'],
				'clic'           => (int) $segnale['clic'],
				'priorita'       => (int) $segnale['priorita'],
				'azione'         => $segnale['azione'],
				'spiegazione'    => $segnale['spiegazione'],
				'dettaglio'      => isset( $segnale['concorrenti'] ) ? json_encode( $segnale['concorrenti'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '',
			);
		}

		if ( $righe ) {
			$db->insertMany( 'gsc_segnale', $righe );
		}

		return $id;
	}

	/**
	 * Ultima rilevazione registrata per un sito.
	 *
	 * @param Db     $db   Database.
	 * @param string $sito Indirizzo del sito.
	 * @return array|null
	 */
	public static function ultima( Db $db, $sito ) {
		return $db->one( 'SELECT * FROM gsc_rilevazione WHERE sito_url = ? ORDER BY id DESC LIMIT 1', array( $sito ) );
	}

	/**
	 * Segnali di una rilevazione.
	 *
	 * @param Db  $db     Database.
	 * @param int $id     Rilevazione.
	 * @param int $limite Quanti restituirne.
	 * @return array[]
	 */
	public static function segnali( Db $db, $id, $limite = 200 ) {
		return $db->all(
			'SELECT * FROM gsc_segnale WHERE rilevazione_id = ? ORDER BY priorita DESC, impression DESC LIMIT ' . (int) $limite,
			array( (int) $id )
		);
	}

	/**
	 * Indirizzi da lavorare per primi, in ordine di priorità.
	 *
	 * Usato dal pilota automatico: invece di seguire l ordine dell audit,
	 * parte da dove Google dice che c è più da guadagnare.
	 *
	 * @param Db     $db   Database.
	 * @param string $sito Indirizzo del sito.
	 * @return array<string,array> Percorso normalizzato => segnale più importante.
	 */
	public static function prioritaPerUrl( Db $db, $sito ) {
		$ultima = self::ultima( $db, $sito );

		if ( ! $ultima ) {
			return array();
		}

		$fuori = array();

		foreach ( self::segnali( $db, $ultima['id'], 500 ) as $segnale ) {
			$chiave = self::chiaveUrl( $segnale['url'] );

			if ( '' === $chiave || isset( $fuori[ $chiave ] ) ) {
				continue;
			}

			$fuori[ $chiave ] = $segnale;
		}

		return $fuori;
	}

	/**
	 * Indirizzo confrontabile.
	 *
	 * @param string $url Indirizzo.
	 * @return string
	 */
	public static function chiaveUrl( $url ) {
		$url = preg_replace( '~^https?://~i', '', (string) $url );
		$url = preg_replace( '~^www\.~i', '', (string) $url );

		return rtrim( strtolower( (string) $url ), '/' );
	}

	/**
	 * Esegue tutto: scarica, calcola i segnali, salva.
	 *
	 * @param Db            $db        Database.
	 * @param array         $cfg       Configurazione.
	 * @param array         $documenti Contenuti noti ('url', 'titolo').
	 * @param callable|null $progresso Richiamata per l avanzamento.
	 * @return array 'rilevazione', 'segnali', 'totali'.
	 */
	public static function esegui( Db $db, array $cfg, array $documenti = array(), callable $progresso = null ) {
		$avvisa = static function ( $messaggio ) use ( $progresso ) {
			if ( $progresso ) {
				call_user_func( $progresso, $messaggio );
			}
		};

		$console = self::client( $cfg );
		$avvisa( 'Collegato a ' . $console->proprieta() . '…' );

		$giorni = (int) ( $cfg['google']['giorni'] ?? 28 );
		$dati   = self::scarica( $console, $giorni );
		$dati['proprieta'] = $console->proprieta();

		$avvisa( sprintf( 'Scaricate %d pagine e %d ricerche.', count( $dati['pagine'] ), count( $dati['query'] ) ) );

		$segnali = Segnali::deriva(
			$dati + array( 'documenti' => $documenti ),
			array( 'min_impression' => (int) ( $cfg['google']['min_impression'] ?? 20 ) )
		);

		$id = self::salva( $db, self::chiaveSito( $cfg ), $dati, $segnali );

		$avvisa( sprintf( 'Trovate %d cose da fare, in ordine di convenienza.', count( $segnali ) ) );

		return array(
			'rilevazione' => $id,
			'segnali'     => $segnali,
			'periodo'     => $dati['periodo'],
			'totali'      => array(
				'clic'       => array_sum( array_column( $dati['pagine'], 'clic' ) ),
				'impression' => array_sum( array_column( $dati['pagine'], 'impression' ) ),
				'pagine'     => count( $dati['pagine'] ),
				'query'      => count( $dati['query'] ),
			),
		);
	}
}
