<?php
/**
 * Che cosa dice Google, articolo per articolo.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Search;

use SeoGeo\Db;
use SeoGeo\Google\SearchConsole;
use Throwable;

/**
 * Il 16 settembre Search Console ha mandato questo messaggio:
 *
 *   «Pagina duplicata, Google ha scelto una pagina canonica diversa da
 *   quella specificata dall utente»
 *
 * Non e un sospetto nostro: e Google che dice quali pagine considera
 * doppioni, e quale delle due tiene. Il rapporto dentro Search Console lo
 * mostra a schermo e basta - non esiste un API per scaricarlo - ma l API di
 * ispezione risponde su un indirizzo per volta, e degli indirizzi abbiamo
 * l elenco completo.
 *
 * Chiedendoglielo uno per uno si ottiene la stessa cosa dentro al
 * gestionale, e una in piu: i doppioni raggruppati per la pagina che Google
 * ha scelto. Quello e il piano di accorpamento, deciso da chi decide.
 *
 * Si lavora a lotti perche ogni risposta costa una richiesta e qualche
 * decimo di secondo, e il limite di Google e duemila al giorno: su trecento
 * articoli non e un problema, ma tutte insieme in una pagina web no.
 */
class Indicizzazione {

	/** Quanti indirizzi per lotto: oltre, l hosting chiude la richiesta a meta. */
	const PER_LOTTO = 25;

	/**
	 * Chiede a Google che fine hanno fatto i contenuti non ancora chiesti.
	 *
	 * @param Db            $db        Database.
	 * @param SearchConsole $console   Client.
	 * @param int           $auditId   Analisi.
	 * @param array         $opzioni   'quanti', 'secondi_max', 'tipi'.
	 * @return array 'chiesti', 'errori', 'restano', 'messaggi'.
	 */
	public static function esegui( Db $db, SearchConsole $console, $auditId, array $opzioni = array() ) {
		$quanti   = max( 1, min( 200, (int) ( $opzioni['quanti'] ?? self::PER_LOTTO ) ) );
		// Il tetto lo decide chi chiama, che sa quanto dura una richiesta su
		// questo hosting. Qui si mette solo un fondo, per non ritrovarsi con
		// zero secondi e un lotto che non parte mai.
		$secondi  = max( 1, (int) ( $opzioni['secondi_max'] ?? 60 ) );
		$scadenza = time() + $secondi;

		$righe = self::daChiedere( $db, $auditId, $quanti );

		$chiesti    = 0;
		$errori     = array();
		$interrotto = false;

		foreach ( $righe as $riga ) {
			// Il tetto di tempo non e un guasto: e il funzionamento normale
			// su hosting condiviso. Ma chi ha scritto 25 e ne vede fare 10
			// deve leggere perche, se no pensa che il numero non venga
			// nemmeno guardato.
			if ( time() > $scadenza ) {
				$interrotto = true;
				break;
			}

			try {
				$esito = $console->ispeziona( (string) $riga['url'] );
			} catch ( Throwable $e ) {
				// Un indirizzo che non si riesce a chiedere non deve fermare
				// il lotto: si annota e si va avanti.
				$errori[] = $riga['url'] . ': ' . $e->getMessage();
				continue;
			}

			$db->run(
				'DELETE FROM gsc_indice WHERE audit_id = ? AND documento_id = ?',
				array( (int) $auditId, (int) $riga['id'] )
			);

			$db->insert(
				'gsc_indice',
				array(
					'audit_id'         => (int) $auditId,
					'documento_id'     => (int) $riga['id'],
					'url'              => (string) $riga['url'],
					'verdetto'         => (string) $esito['stato'],
					'copertura'        => (string) $esito['copertura'],
					'canonica_google'  => (string) $esito['canonica_google'],
					'ultima_scansione' => (string) $esito['ultima_scansione'],
					'chiesto_il'       => date( 'Y-m-d H:i:s' ),
				)
			);

			$chiesti++;
		}

		return array(
			'chiesti'    => $chiesti,
			'chiesti_di' => count( $righe ),
			'errori'     => $errori,
			'interrotto' => $interrotto,
			'secondi'    => $secondi,
			'restano'    => self::quantiRestano( $db, $auditId ),
		);
	}

	/**
	 * I contenuti di cui non si e ancora chiesto niente.
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Analisi.
	 * @param int $quanti  Quanti.
	 * @return array[]
	 */
	public static function daChiedere( Db $db, $auditId, $quanti ) {
		return $db->all(
			"SELECT d.id, d.url FROM documento d
			 WHERE d.audit_id = ? AND d.stato = 'publish' AND d.url <> ''
			   AND d.id NOT IN ( SELECT documento_id FROM gsc_indice WHERE audit_id = ? )
			 ORDER BY d.id ASC
			 LIMIT " . max( 1, (int) $quanti ),
			array( (int) $auditId, (int) $auditId )
		);
	}

	/**
	 * @param Db  $db      Database.
	 * @param int $auditId Analisi.
	 * @return int
	 */
	public static function quantiRestano( Db $db, $auditId ) {
		return (int) $db->one(
			"SELECT COUNT(*) n FROM documento d
			 WHERE d.audit_id = ? AND d.stato = 'publish' AND d.url <> ''
			   AND d.id NOT IN ( SELECT documento_id FROM gsc_indice WHERE audit_id = ? )",
			array( (int) $auditId, (int) $auditId )
		)['n'];
	}

	/**
	 * Il quadro: quanti stanno in ciascuno dei casi.
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Analisi.
	 * @return array 'totale', 'dentro', 'doppioni', 'scartati', 'in_attesa', 'altro'.
	 */
	public static function quadro( Db $db, $auditId ) {
		$conti = array( 'totale' => 0, 'dentro' => 0, 'doppioni' => 0, 'scartati' => 0, 'in_attesa' => 0, 'altro' => 0 );

		foreach ( $db->all( 'SELECT verdetto, copertura FROM gsc_indice WHERE audit_id = ?', array( (int) $auditId ) ) as $riga ) {
			$conti['totale']++;
			$conti[ self::caso( (string) $riga['verdetto'], (string) $riga['copertura'] ) ]++;
		}

		return $conti;
	}

	/**
	 * In quale dei casi ricade una risposta.
	 *
	 * Le parole di Google cambiano con la lingua della richiesta: si
	 * riconoscono le radici, in italiano e in inglese, invece di confrontare
	 * frasi intere che al primo cambio di formulazione smettono di combaciare.
	 *
	 * @param string $verdetto  PASS, NEUTRAL, FAIL.
	 * @param string $copertura Frase di Google.
	 * @return string Chiave di quadro().
	 */
	public static function caso( $verdetto, $copertura ) {
		$c = mb_strtolower( (string) $copertura );

		if ( false !== mb_strpos( $c, 'duplicat' ) || false !== mb_strpos( $c, 'canonic' ) ) {
			return 'doppioni';
		}

		if ( false !== mb_strpos( $c, 'scansionat' ) || false !== mb_strpos( $c, 'crawled' ) ) {
			return 'scartati';
		}

		if ( false !== mb_strpos( $c, 'rilevat' ) || false !== mb_strpos( $c, 'discovered' ) ) {
			return 'in_attesa';
		}

		if ( 'PASS' === strtoupper( (string) $verdetto ) ) {
			return 'dentro';
		}

		return 'altro';
	}

	/**
	 * I doppioni raggruppati per la pagina che Google ha scelto di tenere.
	 *
	 * E il piano di accorpamento gia fatto: da una parte la pagina che
	 * resta, dall altra quelle che Google sta gia ignorando.
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Analisi.
	 * @return array[] 'canonica', 'titolo', 'quante', 'membri'.
	 */
	public static function gruppi( Db $db, $auditId ) {
		$righe = $db->all(
			"SELECT i.url, i.copertura, i.canonica_google, d.titolo, d.wp_id, d.tipo
			 FROM gsc_indice i
			 LEFT JOIN documento d ON d.id = i.documento_id
			 WHERE i.audit_id = ? AND i.canonica_google <> ''",
			array( (int) $auditId )
		);

		$gruppi = array();

		foreach ( $righe as $riga ) {
			if ( 'doppioni' !== self::caso( '', (string) $riga['copertura'] ) ) {
				continue;
			}

			$scelta = self::percorso( (string) $riga['canonica_google'] );

			// Se Google ha scelto proprio questa pagina, doppione non e.
			if ( $scelta === self::percorso( (string) $riga['url'] ) ) {
				continue;
			}

			$gruppi[ $scelta ][] = array(
				'url'    => (string) $riga['url'],
				'titolo' => (string) ( $riga['titolo'] ?: $riga['url'] ),
				'wp_id'  => (string) $riga['wp_id'],
				'tipo'   => (string) $riga['tipo'],
			);
		}

		$fuori = array();

		foreach ( $gruppi as $canonica => $membri ) {
			$titolo = $db->one(
				'SELECT titolo FROM documento WHERE audit_id = ? AND ( percorso = ? OR percorso = ? ) LIMIT 1',
				array( (int) $auditId, $canonica, $canonica . '/' )
			);

			$fuori[] = array(
				'canonica' => (string) $canonica,
				'titolo'   => (string) ( $titolo['titolo'] ?? '' ),
				'quante'   => count( $membri ),
				'membri'   => $membri,
			);
		}

		usort(
			$fuori,
			static function ( $a, $b ) {
				return $b['quante'] <=> $a['quante'];
			}
		);

		return $fuori;
	}

	/**
	 * Le risposte di Google raggruppate per quello che dicono davvero.
	 *
	 * Il quadro mette in «altro» tutto quello che non ricade nei quattro
	 * casi noti, e su questo sito sono sessanta contenuti su trecento: un
	 * quinto dell archivio dentro a una casella che non spiega niente. Qui
	 * ci sono le frasi vere, con quante volte compaiono, cosi si vede che
	 * cosa c e dentro invece di indovinarlo.
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Analisi.
	 * @return array[] 'copertura', 'verdetto', 'quanti', 'caso', 'esempi'.
	 */
	public static function perCopertura( Db $db, $auditId ) {
		$righe = $db->all(
			"SELECT i.copertura, i.verdetto, i.url, d.titolo
			 FROM gsc_indice i
			 LEFT JOIN documento d ON d.id = i.documento_id
			 WHERE i.audit_id = ?",
			array( (int) $auditId )
		);

		$gruppi = array();

		foreach ( $righe as $riga ) {
			$chiave = (string) $riga['verdetto'] . '|' . (string) $riga['copertura'];

			if ( ! isset( $gruppi[ $chiave ] ) ) {
				$gruppi[ $chiave ] = array(
					'copertura' => (string) $riga['copertura'],
					'verdetto'  => (string) $riga['verdetto'],
					'caso'      => self::caso( (string) $riga['verdetto'], (string) $riga['copertura'] ),
					'quanti'    => 0,
					'esempi'    => array(),
				);
			}

			$gruppi[ $chiave ]['quanti']++;

			if ( count( $gruppi[ $chiave ]['esempi'] ) < 5 ) {
				$gruppi[ $chiave ]['esempi'][] = array(
					'url'    => (string) $riga['url'],
					'titolo' => (string) ( $riga['titolo'] ?: $riga['url'] ),
				);
			}
		}

		usort(
			$gruppi,
			static function ( $a, $b ) {
				return $b['quanti'] <=> $a['quanti'];
			}
		);

		return array_values( $gruppi );
	}

	/**
	 * Le canoniche da allineare a quello che Google ha gia scelto.
	 *
	 * «Pagina duplicata, Google ha scelto una pagina canonica diversa da
	 * quella specificata dall utente» vuol dire che la pagina dichiara se
	 * stessa come originale e Google non e d accordo. Finche resta cosi, il
	 * disaccordo resta, e Google segnala il motivo a ogni giro.
	 *
	 * Allineare la canonica e dire per iscritto quello che Google ha gia
	 * deciso. Non e una rinuncia: la pagina resta online e leggibile, i suoi
	 * link continuano a valere, e il valore che ha va a rinforzare quella che
	 * Google tiene invece di disperdersi. Ed e l unica strada che si disfa
	 * togliendo un campo: nessun 301, niente cancellato, niente riscritto.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Analisi.
	 * @param array $opzioni 'pagine' => true per includere le pagine servizio.
	 * @return array[] 'wp_id', 'titolo', 'tipo', 'da', 'canonical'.
	 */
	public static function pianoCanoniche( Db $db, $auditId, array $opzioni = array() ) {
		$piano = array();

		foreach ( self::gruppi( $db, $auditId ) as $gruppo ) {
			$dove = $db->one(
				'SELECT url FROM documento WHERE audit_id = ? AND ( percorso = ? OR percorso = ? ) LIMIT 1',
				array( (int) $auditId, $gruppo['canonica'], $gruppo['canonica'] . '/' )
			);

			// Senza l indirizzo per esteso non si scrive niente: una
			// canonica relativa o inventata e peggio di nessuna canonica.
			if ( empty( $dove['url'] ) ) {
				continue;
			}

			foreach ( $gruppo['membri'] as $membro ) {
				if ( 'post' !== (string) $membro['tipo'] && empty( $opzioni['pagine'] ) ) {
					continue;
				}

				if ( '' === (string) $membro['wp_id'] ) {
					continue;
				}

				$piano[] = array(
					'wp_id'     => (string) $membro['wp_id'],
					'titolo'    => (string) $membro['titolo'],
					'tipo'      => (string) $membro['tipo'],
					'da'        => (string) $membro['url'],
					'canonical' => (string) $dove['url'],
				);
			}
		}

		return $piano;
	}

	/**
	 * Quante pagine servizio resterebbero fuori dal piano.
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Analisi.
	 * @return int
	 */
	public static function paginePerse( Db $db, $auditId ) {
		return count( self::pianoCanoniche( $db, $auditId, array( 'pagine' => true ) ) )
			- count( self::pianoCanoniche( $db, $auditId ) );
	}

	/**
	 * Percorso confrontabile.
	 *
	 * @param string $url Indirizzo.
	 * @return string
	 */
	private static function percorso( $url ) {
		$url = preg_replace( '~^https?://[^/]+~i', '', (string) $url );

		return '/' . strtolower( trim( (string) $url, '/' ) );
	}
}
