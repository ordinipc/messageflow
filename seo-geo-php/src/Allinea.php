<?php
/**
 * Allineare l analisi a quello che il sito ha adesso, senza rileggerlo tutto.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

use SeoGeo\Bridge\WordPress;
use Throwable;

/**
 * Rileggere il sito per intero costa minuti: 330 contenuti, la libreria
 * media, i link. Non si puo fare a ogni caricamento di pagina, ed e per
 * questo che l analisi resta ferma a quando e stata fatta.
 *
 * Ma non tutto costa minuti. Alcune domande al sito costano una richiesta
 * sola e rispondono per tutto il sito in un colpo: che cosa stampa il
 * plugin nella testata, quali redirect sono attivi, quali articoli hanno
 * l immagine in evidenza. Altre non costano niente perche la risposta e
 * gia qui: se il telefono aziendale e compilato, il rilievo «telefono
 * assente» e chiuso e basta.
 *
 * Questa classe fa quelle domande a ogni apertura della pagina dell audit,
 * con un tetto di tempo e senza ripetersi troppo spesso, e chiude nell
 * analisi quello che risulta gia fatto. Non sostituisce la rilettura: la
 * copre nell intervallo fra una e l altra.
 */
class Allinea {

	/**
	 * Ogni quanti secondi, al massimo, si torna a chiedere al sito.
	 */
	const OGNI = 180;

	/**
	 * Regola dell audit => cosa deve stampare il plugin perche sia chiusa.
	 */
	const DAL_PLUGIN = array(
		'SCH-01' => 'jsonld',
		'SCH-02' => 'jsonld',
		'SCH-03' => 'jsonld',
		'SCH-04' => 'jsonld',
		'SCH-05' => 'opengraph',
		'LOC-03' => 'local',
		'TEC-02' => 'robots',
		'TEC-03' => 'robots',
		'TEC-04' => 'canonical',
		'GEO-01' => 'llms',
		'GEO-02' => 'robots_txt',
		'GEO-07' => 'jsonld',
		'LNK-01' => 'link_interni',
		'LNK-02' => 'link_interni',
		'LNK-04' => 'nofollow',
		'IMG-01' => 'alt',
		'IMG-02' => 'alt',
		'IMG-06' => 'dimensioni',
		// L H1 lo stampa il tema col titolo dell articolo: se il sito dice
		// che c e, non c e piu niente da segnalare su nessun contenuto.
		'ONP-08' => 'h1',
	);

	/**
	 * Dove si segna l ultimo allineamento.
	 *
	 * @param int $auditId Audit.
	 * @return string
	 */
	public static function segno( $auditId ) {
		return dirname( __DIR__ ) . '/storage/allineamento-' . (int) $auditId . '.json';
	}

	/**
	 * Quando e stato fatto l ultimo allineamento, e com e andato.
	 *
	 * @param int $auditId Audit.
	 * @return array Vuoto se non e mai stato fatto.
	 */
	public static function ultimo( $auditId ) {
		$file = self::segno( $auditId );

		if ( ! is_file( $file ) ) {
			return array();
		}

		$dati = json_decode( (string) file_get_contents( $file ), true );

		return is_array( $dati ) ? $dati : array();
	}

	/**
	 * Se e il momento di rifarlo.
	 *
	 * @param int $auditId Audit.
	 * @return bool
	 */
	public static function scaduto( $auditId ) {
		$ultimo = self::ultimo( $auditId );

		if ( empty( $ultimo['quando'] ) ) {
			return true;
		}

		return ( time() - (int) strtotime( (string) $ultimo['quando'] ) ) > self::OGNI;
	}

	/**
	 * Chiede al sito e chiude quello che risulta gia fatto.
	 *
	 * Ogni passo e protetto: se il sito non risponde a una domanda si passa
	 * alla successiva. Un sito lento non deve impedire di aprire la pagina, e
	 * soprattutto non deve far sparire un problema che invece c e: quando non
	 * si sa, non si chiude niente.
	 *
	 * @param Db        $db         Database.
	 * @param WordPress $ponte      Collegamento al sito.
	 * @param int       $auditId    Audit.
	 * @param array     $cfg        Configurazione.
	 * @param int       $secondiMax Tetto di tempo.
	 * @return array Regola => quante occorrenze sono state chiuse.
	 */
	public static function esegui( Db $db, WordPress $ponte, $auditId, array $cfg, $secondiMax = 20 ) {
		$scadenza = time() + (int) $secondiMax;
		$chiuse   = array();
		$parziale = false;

		$segna = static function ( $regola, $quante ) use ( &$chiuse ) {
			if ( $quante > 0 ) {
				$chiuse[ $regola ] = ( $chiuse[ $regola ] ?? 0 ) + $quante;
			}
		};

		// 1. I dati aziendali. Non costano niente: la risposta e qui.
		foreach ( self::daiDatiAziendali( $cfg ) as $regola ) {
			$segna( $regola, self::chiudiTutto( $db, $auditId, $regola ) );
		}

		// 2. Che cosa stampa il plugin sulle pagine. Una richiesta, e
		//    risponde per diciotto regole insieme.
		if ( $ponte->pronto() && time() < $scadenza ) {
			try {
				$stato  = $ponte->conteggi();
				$stampa = (array) ( $stato['sito']['stampa'] ?? array() );

				foreach ( self::DAL_PLUGIN as $regola => $cosa ) {
					if ( ! empty( $stampa[ $cosa ] ) ) {
						$segna( $regola, self::chiudiTutto( $db, $auditId, $regola ) );
					}
				}
			} catch ( Throwable $e ) {
				// Il sito non risponde: i rilievi restano aperti.
				unset( $e );
			}
		}

		// 3. I redirect davvero attivi.
		if ( $ponte->pronto() && time() < $scadenza ) {
			try {
				$segna( 'ONP-07', self::daiRedirect( $db, $ponte, $auditId ) );
			} catch ( Throwable $e ) {
				unset( $e );
			}
		}

		// 4. Le misure vere dei contenuti, cento per richiesta: lunghezza del
		//    title e della description, chiave nel title, estratto, immagine
		//    in evidenza, parole. Con queste si rifanno i conti delle regole
		//    che prima restavano ferme per sempre.
		if ( $ponte->pronto() ) {
			try {
				foreach ( self::daiContenuti( $db, $ponte, $auditId, $scadenza, $parziale ) as $regola => $quante ) {
					$segna( $regola, $quante );
				}
			} catch ( Throwable $e ) {
				// Il sito non risponde: i conti restano quelli di prima.
				unset( $e );
			}
		}

		self::scrivi( $auditId, $chiuse, $parziale );

		return $chiuse;
	}

	/**
	 * Regole chiuse dal solo fatto che un dato e compilato.
	 *
	 * @param array $cfg Configurazione.
	 * @return string[]
	 */
	private static function daiDatiAziendali( array $cfg ) {
		$azienda = (array) ( $cfg['azienda'] ?? array() );

		$compilato = static function ( $valore ) {
			$valore = trim( (string) $valore );

			return '' !== $valore && 0 !== stripos( $valore, 'DA_COMPILARE' );
		};

		$fuori = array();

		if ( $compilato( $azienda['telefono'] ?? '' ) || $compilato( $azienda['cellulare'] ?? '' ) ) {
			$fuori[] = 'LOC-01';
		}

		$indirizzo = (array) ( $azienda['indirizzo'] ?? array() );

		if ( $compilato( $indirizzo['via'] ?? '' ) && $compilato( $azienda['partitaIva'] ?? '' ) ) {
			$fuori[] = 'LOC-02';
		}

		foreach ( (array) ( $cfg['autori'] ?? array() ) as $autore ) {
			if ( $compilato( $autore['nome'] ?? '' ) && $compilato( $autore['ruolo'] ?? '' ) ) {
				$fuori[] = 'GEO-06';
				$fuori[] = 'EAT-01';
				break;
			}
		}

		return $fuori;
	}

	/**
	 * Chiude ONP-07 per i vecchi indirizzi che adesso rimandano altrove.
	 *
	 * @param Db        $db      Database.
	 * @param WordPress $ponte   Sito.
	 * @param int       $auditId Audit.
	 * @return int
	 */
	private static function daiRedirect( Db $db, WordPress $ponte, $auditId ) {
		$risposta = $ponte->redirectAttivi();
		$attivi   = array();

		// Il sito risponde con l elenco dei percorsi che hanno un redirect.
		// Si chiude sia la forma con la barra finale sia quella senza: l
		// analisi ha salvato una delle due e non si sa quale.
		foreach ( (array) ( $risposta['percorsi'] ?? array() ) as $percorso ) {
			$percorso = trim( (string) $percorso );

			if ( '' === $percorso ) {
				continue;
			}

			$attivi[] = $percorso;
			$attivi[] = rtrim( $percorso, '/' );
			$attivi[] = rtrim( $percorso, '/' ) . '/';
		}

		if ( ! $attivi ) {
			return 0;
		}

		return Applicato::chiudi( $db, $auditId, array( 'ONP-07' ), $attivi );
	}

	/**
	 * Regola => come si rilegge sulle misure vere del contenuto.
	 *
	 * Ogni funzione risponde a una domanda sola: guardando il sito adesso,
	 * questo contenuto ha ancora quel problema? Chi risponde «no» esce dal
	 * conto. Chi non ha una misura utile - la chiave di ricerca non e
	 * impostata, per dire - resta dov e: non sapere non e una risoluzione.
	 *
	 * @return array<string,callable>
	 */
	private static function riletture() {
		return array(
			// Title oltre i 60 caratteri.
			'ONP-01' => static fn( array $m ) => (int) $m['titolo_lungh'] <= 60,
			// Title sotto i 30, ma solo se c e.
			'ONP-02' => static fn( array $m ) => 0 === (int) $m['titolo_lungh'] || (int) $m['titolo_lungh'] >= 30,
			// Description fuori dalla finestra 120-158.
			'ONP-03' => static function ( array $m ) {
				$n = (int) $m['descr_lungh'];

				return 0 === $n || ( $n >= 120 && $n <= 158 );
			},
			// Description assente.
			'ONP-04' => static fn( array $m ) => (int) $m['descr_lungh'] > 0,
			// Chiave di ricerca dentro al title.
			'ONP-05' => static fn( array $m ) => ! empty( $m['ha_chiave'] ) && ! empty( $m['chiave_titolo'] ),
			// Estratto assente.
			'ONP-12' => static fn( array $m ) => ! empty( $m['estratto'] ),
			// Immagine in evidenza assente.
			'IMG-05' => static fn( array $m ) => ! empty( $m['thumbnail'] ),
			// Testo troppo corto.
			'CNT-01' => static fn( array $m ) => (int) $m['parole'] >= 300,
			'CNT-02' => static fn( array $m ) => (int) $m['parole'] >= 600,
		);
	}

	/**
	 * Rilegge le regole di contenuto sulle misure vere del sito.
	 *
	 * Si lavora a blocchi di cento e si smette quando il tempo e finito: su un
	 * sito grande i conti si sistemano in qualche apertura di pagina invece
	 * che in una sola lunghissima.
	 *
	 * @param Db        $db       Database.
	 * @param WordPress $ponte    Sito.
	 * @param int       $auditId  Audit.
	 * @param int       $scadenza Istante oltre il quale ci si ferma.
	 * @param bool      $parziale Diventa vero se si e smesso a meta.
	 * @return array Regola => quante occorrenze sono state chiuse.
	 */
	private static function daiContenuti( Db $db, WordPress $ponte, $auditId, $scadenza, &$parziale = false ) {
		$riletture = self::riletture();
		$segnaposto = implode( ',', array_fill( 0, count( $riletture ), '?' ) );

		$righe = $db->all(
			"SELECT o.id AS occ, r.regola, d.id AS doc, d.wp_id, d.percorso
			 FROM occorrenza o
			 JOIN rilievo r ON r.id = o.rilievo_id
			 JOIN documento d ON d.audit_id = r.audit_id AND d.percorso = o.riferimento
			 WHERE r.audit_id = ? AND COALESCE( o.applicato, 0 ) = 0
			   AND r.regola IN ( $segnaposto )
			   AND d.wp_id <> ''
			 ORDER BY d.id ASC",
			array_merge( array( (int) $auditId ), array_keys( $riletture ) )
		);

		if ( ! $righe ) {
			return array();
		}

		// Uno stesso contenuto compare sotto piu regole: si chiede una volta
		// sola e si rilegge tutto quello che lo riguarda.
		$perWp = array();

		foreach ( $righe as $riga ) {
			$perWp[ (string) $riga['wp_id'] ][] = $riga;
		}

		$chiuse = array();

		foreach ( array_chunk( array_keys( $perWp ), 100 ) as $blocco ) {
			if ( time() >= $scadenza ) {
				$parziale = true;
				break;
			}

			try {
				$risposta = $ponte->misure( $blocco );
			} catch ( Throwable $e ) {
				// Un blocco che non arriva non deve buttare via quelli gia
				// letti: si tiene quello che si e capito e si smette.
				$parziale = true;
				break;
			}

			$misure = (array) ( $risposta['misure'] ?? array() );

			foreach ( $blocco as $wpId ) {
				$m = $misure[ (string) $wpId ] ?? null;

				if ( ! is_array( $m ) ) {
					continue;
				}

				foreach ( $perWp[ (string) $wpId ] as $riga ) {
					$regola = (string) $riga['regola'];

					if ( ! isset( $riletture[ $regola ] ) || ! $riletture[ $regola ]( $m ) ) {
						continue;
					}

					$db->run( 'UPDATE occorrenza SET applicato = 1 WHERE id = ?', array( (int) $riga['occ'] ) );

					$chiuse[ $regola ] = ( $chiuse[ $regola ] ?? 0 ) + 1;

					if ( 'IMG-05' === $regola ) {
						$db->run( 'UPDATE documento SET ha_thumbnail = 1 WHERE id = ?', array( (int) $riga['doc'] ) );
					}
				}
			}
		}

		return $chiuse;
	}

	/**
	 * Chiude tutte le occorrenze aperte di una regola.
	 *
	 * @param Db     $db      Database.
	 * @param int    $auditId Audit.
	 * @param string $regola  Regola.
	 * @return int Quante ne ha chiuse.
	 */
	private static function chiudiTutto( Db $db, $auditId, $regola ) {
		$aperte = (int) $db->one(
			'SELECT COUNT(*) n FROM occorrenza o JOIN rilievo r ON r.id = o.rilievo_id
			 WHERE r.audit_id = ? AND r.regola = ? AND COALESCE( o.applicato, 0 ) = 0',
			array( (int) $auditId, (string) $regola )
		)['n'];

		if ( ! $aperte ) {
			return 0;
		}

		$db->run(
			'UPDATE occorrenza SET applicato = 1
			 WHERE COALESCE( applicato, 0 ) = 0
			   AND rilievo_id IN ( SELECT id FROM rilievo WHERE audit_id = ? AND regola = ? )',
			array( (int) $auditId, (string) $regola )
		);

		return $aperte;
	}

	/**
	 * Segna quando e stato fatto e che cosa ha chiuso.
	 *
	 * @param int   $auditId Audit.
	 * @param array $chiuse   Regola => quante.
	 * @param bool  $parziale Se il giro si e fermato prima della fine.
	 * @return void
	 */
	private static function scrivi( $auditId, array $chiuse, $parziale = false ) {
		$file = self::segno( $auditId );
		$dir  = dirname( $file );

		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0775, true ) && ! is_dir( $dir ) ) {
			return;
		}

		file_put_contents(
			$file,
			(string) json_encode(
				array( 'quando' => date( 'Y-m-d H:i:s' ), 'chiuse' => $chiuse, 'parziale' => (bool) $parziale ),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			)
		);
	}
}
