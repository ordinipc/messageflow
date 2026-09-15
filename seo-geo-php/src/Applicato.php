<?php
/**
 * Segnare sull analisi il lavoro che e stato davvero fatto sul sito.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

/**
 * Un audit e una fotografia: dice com era il sito nel momento in cui e stato
 * letto. Rileggerlo tutto costa minuti, quindi non lo si rifa a ogni clic.
 *
 * Il problema non e la fotografia: e che finora nessuno ci scriveva sopra.
 * Si mandavano al sito 111 title nuovi, il sito rispondeva "111 aggiornati",
 * e la tabella continuava a dire 111 perche leggeva la fotografia di prima.
 * Gli stessi articoli venivano riproposti all infinito, e chi guardava il
 * gestionale vedeva errori che non esistevano piu.
 *
 * Qui c e il pezzo che mancava. Ogni volta che qualcosa viene scritto sul
 * sito si passa da questa classe, che fa due cose:
 *
 * 1. aggiorna la copia locale del contenuto (documento.seo_title e compagnia)
 *    con quello che il sito ha adesso: cosi i confronti "da cambiare" si
 *    spengono da soli;
 * 2. marca le occorrenze chiuse (occorrenza.applicato) delle regole che quel
 *    lavoro risolve: cosi i conteggi scendono.
 *
 * Non sostituisce la rilettura del sito, che resta l unica verita: la
 * corregge fra una rilettura e l altra, che e esattamente il tempo in cui
 * prima raccontava cose false.
 */
class Applicato {

	/**
	 * Regole che si chiudono scrivendo le meta sul sito.
	 */
	const META = array( 'ONP-01', 'ONP-02', 'ONP-03', 'ONP-04', 'ONP-05', 'ONP-12' );

	/**
	 * Regole che si chiudono riscrivendo il corpo dell articolo.
	 *
	 * Sono quelle che la riscrittura assistita affronta davvero: struttura,
	 * apertura, domande frequenti, lunghezza, stili incollati dentro.
	 */
	const CONTENUTO = array(
		'CNT-01',
		'CNT-02',
		'CNT-07',
		'CNT-09',
		'GEO-03',
		'GEO-04',
		'GEO-05',
		'GEO-10',
		'ONP-10',
		'LOC-07',
	);

	/**
	 * Regole che si chiudono accorpando due contenuti in uno.
	 */
	const FUSIONE = array( 'CNT-03', 'ONP-06', 'LOC-05' );

	/**
	 * Regole che si chiudono ricomprimendo un file.
	 */
	const IMMAGINI = array( 'IMG-03', 'IMG-04' );

	/**
	 * Marca come chiuse le occorrenze di certe regole su certi riferimenti.
	 *
	 * Il riferimento e quello che l audit ha scritto nell occorrenza: il
	 * percorso per i contenuti, il nome del file per gli allegati.
	 *
	 * @param Db       $db           Database.
	 * @param int      $auditId      Audit.
	 * @param string[] $regole       Regole da chiudere.
	 * @param string[] $riferimenti  Riferimenti toccati.
	 * @return int Quante occorrenze sono state chiuse adesso.
	 */
	public static function chiudi( Db $db, $auditId, array $regole, array $riferimenti ) {
		$regole      = array_values( array_unique( array_filter( array_map( 'strval', $regole ) ) ) );
		$riferimenti = array_values( array_unique( array_filter( array_map( 'strval', $riferimenti ) ) ) );

		if ( ! $regole || ! $riferimenti ) {
			return 0;
		}

		$chiuse = 0;

		// A blocchi: una IN con settecento riferimenti supera il limite di
		// segnaposto di parecchi database.
		foreach ( array_chunk( $riferimenti, 200 ) as $blocco ) {
			$segnaRegole = implode( ',', array_fill( 0, count( $regole ), '?' ) );
			$segnaRif    = implode( ',', array_fill( 0, count( $blocco ), '?' ) );

			$sql = "UPDATE occorrenza SET applicato = 1
					WHERE COALESCE( applicato, 0 ) = 0
					  AND riferimento IN ( $segnaRif )
					  AND rilievo_id IN (
						  SELECT id FROM rilievo WHERE audit_id = ? AND regola IN ( $segnaRegole )
					  )";

			$db->run( $sql, array_merge( $blocco, array( (int) $auditId ), $regole ) );

			$chiuse += count( $blocco );
		}

		return $chiuse;
	}

	/**
	 * Le meta di questi contenuti sono state scritte sul sito.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit.
	 * @param array $righe   Righe spedite: ognuna con 'id' (wp_id), 'title',
	 *                       'description', 'excerpt'.
	 * @param bool  $chiudi  Falso quando si sta tornando indietro: la copia
	 *                       locale va comunque allineata, ma i problemi che
	 *                       il ripristino fa tornare non vanno chiusi.
	 * @return void
	 */
	public static function meta( Db $db, $auditId, array $righe, $chiudi = true ) {
		$percorsi = array();

		foreach ( $righe as $r ) {
			$wpId = (string) ( $r['id'] ?? '' );

			if ( '' === $wpId ) {
				continue;
			}

			// La copia locale deve dire quello che il sito dice adesso:
			// altrimenti "solo quelle da cambiare" le ripropone in eterno.
			$db->run(
				'UPDATE documento SET seo_title = ?, seo_description = ? WHERE audit_id = ? AND wp_id = ?',
				array( (string) ( $r['title'] ?? '' ), (string) ( $r['description'] ?? '' ), (int) $auditId, $wpId )
			);

			$doc = $db->one( 'SELECT percorso FROM documento WHERE audit_id = ? AND wp_id = ?', array( (int) $auditId, $wpId ) );

			if ( $doc && '' !== (string) $doc['percorso'] ) {
				$percorsi[] = (string) $doc['percorso'];
			}
		}

		if ( $chiudi ) {
			self::chiudi( $db, $auditId, self::META, $percorsi );
		}
	}

	/**
	 * Il testo nuovo di questo contenuto e stato scritto sul sito.
	 *
	 * @param Db     $db          Database.
	 * @param int    $auditId     Audit.
	 * @param int    $documentoId Documento riscritto.
	 * @param string $testo       Testo che adesso e online.
	 * @param bool   $fusione     Vero se il contenuto ha assorbito un altro.
	 * @return void
	 */
	public static function contenuto( Db $db, $auditId, $documentoId, $testo = '', $fusione = false ) {
		$doc = $db->one( 'SELECT percorso FROM documento WHERE id = ?', array( (int) $documentoId ) );

		if ( ! $doc ) {
			return;
		}

		if ( '' !== (string) $testo ) {
			$pulito = trim( Html::testo( $testo ) );

			// Lo stesso contatore che usa l analisi. Con str_word_count le
			// parole con l apostrofo - un agenzia, l identita - venivano
			// contate diversamente da come le conta l audit, e un articolo
			// vicino alla soglia entrava e usciva dal rilievo a seconda di
			// chi aveva fatto il conto per ultimo.
			$db->run(
				'UPDATE documento SET testo = ?, parole = ? WHERE id = ?',
				array( $pulito, Text::wordCount( $pulito ), (int) $documentoId )
			);
		}

		$regole = self::CONTENUTO;

		if ( $fusione ) {
			$regole = array_merge( $regole, self::FUSIONE );
		}

		self::chiudi( $db, $auditId, $regole, array( (string) $doc['percorso'] ) );
	}

	/**
	 * Questi file sono stati ricompressi sul sito.
	 *
	 * @param Db       $db      Database.
	 * @param int      $auditId Audit.
	 * @param string[] $file    Nomi dei file.
	 * @return void
	 */
	public static function immagini( Db $db, $auditId, array $file ) {
		self::chiudi( $db, $auditId, self::IMMAGINI, $file );
	}

	/**
	 * Questi contenuti hanno adesso l immagine in evidenza.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit.
	 * @param array $wpIds   Identificativi WordPress.
	 * @return void
	 */
	public static function miniature( Db $db, $auditId, array $wpIds ) {
		$percorsi = array();

		foreach ( $wpIds as $wpId ) {
			$doc = $db->one( 'SELECT percorso FROM documento WHERE audit_id = ? AND wp_id = ?', array( (int) $auditId, (string) $wpId ) );

			if ( $doc ) {
				$percorsi[] = (string) $doc['percorso'];
			}
		}

		self::chiudi( $db, $auditId, array( 'IMG-05' ), $percorsi );
	}

	/**
	 * Questi vecchi indirizzi hanno adesso il loro redirect.
	 *
	 * @param Db       $db        Database.
	 * @param int      $auditId   Audit.
	 * @param string[] $percorsi  Vecchi percorsi.
	 * @return void
	 */
	public static function redirect( Db $db, $auditId, array $percorsi ) {
		self::chiudi( $db, $auditId, array( 'ONP-07' ), $percorsi );
	}

	/**
	 * Divide i rilievi in quelli ancora aperti e quelli gia sistemati.
	 *
	 * Il conto delle occorrenze diventa quello vero: non quante ce n erano
	 * quando il sito e stato letto, ma quante ne restano dopo il lavoro fatto
	 * da allora. I rilievi chiusi non spariscono, si spostano: chi guarda
	 * deve poter vedere che quel lavoro e stato registrato, altrimenti non si
	 * distingue un problema risolto da un problema dimenticato.
	 *
	 * @param array $rilievi Rilievi dell audit.
	 * @param array $residui Uscita di residui().
	 * @return array 'aperti' e 'chiusi'.
	 */
	public static function separa( array $rilievi, array $residui ) {
		$aperti = array();
		$chiusi = array();

		foreach ( $rilievi as $r ) {
			$regola = (string) $r['regola'];

			$r['occorrenze_iniziali'] = (int) $r['occorrenze'];
			$r['chiuse']              = 0;

			if ( isset( $residui[ $regola ] ) ) {
				$r['chiuse']     = $residui[ $regola ]['totali'] - $residui[ $regola ]['aperte'];
				$r['occorrenze'] = $residui[ $regola ]['aperte'];
			}

			// Si sposta fra i sistemati solo se qualcosa e stato davvero
			// chiuso: un rilievo senza occorrenze salvate resta dov e, non
			// si finge risolto per un conteggio mancante.
			if ( $r['chiuse'] > 0 && 0 === (int) $r['occorrenze'] ) {
				$chiusi[] = $r;
			} else {
				$aperti[] = $r;
			}
		}

		return array( 'aperti' => $aperti, 'chiusi' => $chiusi );
	}

	/**
	 * Quante occorrenze restano aperte, regola per regola.
	 *
	 * Solo le regole che hanno occorrenze salvate: quelle di sito (una riga
	 * sola, riferimento "(sito)") restano fuori e tengono il loro conto.
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Audit.
	 * @return array Regola => quante ne restano.
	 */
	public static function residui( Db $db, $auditId ) {
		$righe = $db->all(
			'SELECT r.regola AS regola,
					COUNT(*) AS totali,
					SUM( CASE WHEN COALESCE( o.applicato, 0 ) = 0 THEN 1 ELSE 0 END ) AS aperte
			 FROM occorrenza o JOIN rilievo r ON r.id = o.rilievo_id
			 WHERE r.audit_id = ?
			 GROUP BY r.regola',
			array( (int) $auditId )
		);

		$fuori = array();

		foreach ( $righe as $r ) {
			$fuori[ (string) $r['regola'] ] = array(
				'totali' => (int) $r['totali'],
				'aperte' => (int) $r['aperte'],
			);
		}

		return $fuori;
	}
}
