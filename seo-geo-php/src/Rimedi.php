<?php
/**
 * Dove si risolve ogni problema trovato dall audit.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

/**
 * Segnare un problema come "correzione automatica" e non dire dove si
 * clicchi e meta lavoro: chi legge la tabella resta con l elenco in mano e
 * nessun posto dove andare.
 *
 * Qui ogni regola sa dove si risolve. Tre modi diversi, e la differenza
 * conta:
 *
 * - 'plugin'  il plugin lo fa da solo sulle pagine, appena e installato e
 *             ha ricevuto i dati aziendali. Non c e niente da premere.
 * - 'azione'  c e un pulsante nel gestionale che lo applica.
 * - 'manuale' serve una persona: un testo da scrivere, una scelta da fare.
 *
 * Non tutte le regole stanno qui. Quelle che mancano restano senza
 * collegamento, che e meglio di un collegamento che non risolve.
 */
class Rimedi {

	/**
	 * Regola => dove si risolve.
	 *
	 * @param int $auditId Audit corrente.
	 * @return array
	 */
	public static function mappa( $auditId ) {
		$id = (int) $auditId;

		$collega   = '?p=collega&id=' . $id;
		$bozze     = '?p=bozze&id=' . $id;
		$impostazioni = '?p=impostazioni';

		// Il pulsante deve portare con se il problema da cui si e partiti:
		// arrivare nella pagina della riscrittura senza sapere su che cosa
		// si sta lavorando lascia esattamente dove si era.
		$perRegola = static function ( $regola ) use ( $bozze ) {
			return $bozze . '&regola=' . rawurlencode( $regola );
		};

		// Quello che il plugin fa da solo una volta installato: stampa i dati
		// strutturati, il canonical, le direttive robots, l Open Graph e i
		// file per i crawler AI su ogni pagina del sito.
		$daPlugin = array(
			'SCH-01' => 'Article e Organization su ogni contenuto',
			'SCH-02' => 'FAQPage dove ci sono domande nei titoli',
			'SCH-03' => 'BreadcrumbList su ogni pagina',
			'SCH-04' => 'Service sulle pagine servizio',
			'SCH-05' => 'Open Graph e Twitter Card',
			'LOC-03' => 'LocalBusiness con i dati aziendali',
			'TEC-02' => 'direttiva robots esplicita',
			'TEC-03' => 'max-image-preview:large',
			'TEC-04' => 'canonical esplicito',
			'GEO-01' => 'llms.txt generato dal sito',
			'GEO-02' => 'robots.txt con le direttive per i crawler AI',
			'GEO-07' => 'markup speakable',
			'IMG-06' => 'width e height presi dalla libreria media',
			'LNK-01' => 'link interni verso le pagine senza collegamenti in entrata',
			'LNK-02' => 'link interni inseriti nel contenuto',
			'LNK-05' => 'rel="noopener noreferrer" su ogni link che apre una nuova scheda',
			'LNK-04' => 'rel="nofollow sponsored" sui link esterni configurati',
			'IMG-01' => 'alt generato dal titolo su ogni immagine del contenuto',
			'IMG-02' => 'alt generato anche per gli allegati della libreria media',

		);

		$mappa = array();

		foreach ( $daPlugin as $regola => $cosa ) {
			$mappa[ $regola ] = array(
				'come'      => 'plugin',
				'dove'      => $collega,
				'etichetta' => 'Lo fa il plugin',
				'spiega'    => 'Il plugin installato su WordPress stampa ' . $cosa . ' su ogni pagina: non c è niente da premere.',
			);
		}

		// Quello che si applica da un pulsante del gestionale.
		$daAzione = array(
			'ONP-01' => array( $collega . '#meta', 'Meta degli articoli', 'I title troppo lunghi vengono riscritti e inviati al sito.' ),
			'ONP-02' => array( $collega . '#meta', 'Meta degli articoli', 'I title troppo corti vengono estesi e inviati al sito.' ),
			'ONP-03' => array( $collega . '#meta', 'Meta degli articoli', 'Le description fuori misura vengono riscritte e inviate al sito.' ),
			'ONP-04' => array( $collega . '#meta', 'Meta degli articoli', 'Le description mancanti vengono generate e inviate al sito.' ),
			'ONP-05' => array( $collega . '#meta', 'Meta degli articoli', 'La focus keyword viene messa nel title.' ),
			'ONP-12' => array( $collega . '#meta', 'Meta degli articoli', 'L excerpt viene generato e inviato insieme alle meta.' ),
			'ONP-07' => array( $collega . '#redirect', 'Redirect 301', 'Gli slug accorciati hanno già il loro redirect 301 pronto da attivare; poi lo slug va cambiato in WordPress.' ),
			'IMG-03' => array( $collega . '#immagini-pesanti', 'Immagini pesanti', 'Le immagini oltre i 200 KB vengono ricompresse in WebP sul sito.' ),
			'IMG-04' => array( $collega . '#immagini-pesanti', 'Immagini pesanti', 'La stessa ricompressione converte i JPG e i PNG in WebP.' ),
			'IMG-05' => array( $perRegola( 'IMG-05' ), 'Riscrittura assistita', 'Le immagini in evidenza mancanti vengono generate e caricate.' ),
			'TAX-03' => array( $collega . '#categorie', 'Categorie', 'Gli articoli fuori tema vengono riassegnati alla categoria giusta.' ),
			'CNT-09' => array( $perRegola( 'CNT-09' ), 'Riscrittura assistita', 'La riscrittura rifà i titoletti che ripetono il titolo.' ),
			'GEO-03' => array( $perRegola( 'GEO-03' ), 'Riscrittura assistita', 'La riscrittura apre l articolo con il blocco di sintesi.' ),
			'GEO-04' => array( $perRegola( 'GEO-04' ), 'Riscrittura assistita', 'La riscrittura aggiunge le domande frequenti.' ),
			'GEO-10' => array( $perRegola( 'GEO-10' ), 'Riscrittura assistita', 'La riscrittura aggiunge elenchi e tabelle dove servono.' ),
			'ONP-10' => array( $perRegola( 'ONP-10' ), 'Riscrittura assistita', 'La riscrittura dà una struttura di H2 al testo.' ),
			'CNT-03' => array( $perRegola( 'CNT-03' ), 'Riscrittura assistita', 'I testi sovrapposti si accorpano in uno solo con «Fondi i gruppi».' ),
			'ONP-06' => array( $perRegola( 'ONP-06' ), 'Riscrittura assistita', 'La cannibalizzazione si risolve accorpando con «Fondi i gruppi».' ),
			'LOC-05' => array( $perRegola( 'LOC-05' ), 'Riscrittura assistita', 'Le landing locali sovrapposte si accorpano con «Fondi i gruppi».' ),
			'CNT-07' => array( $perRegola( 'CNT-07' ), 'Riscrittura assistita', 'La riscrittura rifà il testo senza gli stili incollati dentro.' ),
			'CNT-01' => array( $perRegola( 'CNT-01' ), 'Riscrittura assistita', 'Gli articoli troppo corti si riscrivono alla lunghezza giusta.' ),
			'CNT-02' => array( $perRegola( 'CNT-02' ), 'Riscrittura assistita', 'Gli articoli sotto soglia si riscrivono più completi.' ),
			// Questi quattro erano segnati «a mano» e insieme facevano 201
			// segnalazioni che il pulsante «correggi tutto» non toccava mai:
			// restavano in elenco a ogni rilettura. Sono tutti e quattro
			// lavoro di riscrittura, e dopo la riscrittura si controlla che
			// siano davvero chiusi.
			'CNT-06' => array( $perRegola( 'CNT-06' ), 'Riscrittura assistita', 'La riscrittura spezza le frasi lunghe e riporta la leggibilità sopra la soglia.' ),
			'CNT-08' => array( $perRegola( 'CNT-08' ), 'Riscrittura assistita', 'La riscrittura aggiunge un elenco o una tabella dove c è solo testo continuo.' ),
			'ONP-09' => array( $perRegola( 'ONP-09' ), 'Riscrittura assistita', 'La riscrittura lascia un solo H1: quello del titolo, stampato dal tema.' ),
			'ONP-11' => array( $perRegola( 'ONP-11' ), 'Riscrittura assistita', 'La riscrittura rimette i livelli dei titoli in sequenza.' ),
			'CNT-10' => array( $perRegola( 'CNT-10' ), 'Riscrittura assistita', 'La riscrittura porta l anno del titolo a quello corrente, e controlla di averlo fatto.' ),
			'CNT-11' => array( $perRegola( 'CNT-11' ), 'Riscrittura assistita', 'La riscrittura aggiorna gli anni dati per correnti dentro al testo.' ),
			'LOC-02' => array( $impostazioni, 'Impostazioni', 'Compila indirizzo e partita IVA: vengono inviati al sito e finiscono nello schema.' ),
			'LOC-01' => array( $impostazioni, 'Impostazioni', 'Compila il telefono: finisce nello schema LocalBusiness, e lo shortcode [mdi_nap] lo stampa nel footer.' ),
			'LOC-07' => array( $perRegola( 'LOC-07' ), 'Riscrittura assistita', 'La riscrittura aggiunge i riferimenti geografici alle landing locali.' ),
			'GEO-06' => array( $impostazioni, 'Impostazioni', 'Compila i dati dell autore: il plugin li usa per lo schema Person.' ),
			'EAT-01' => array( $impostazioni, 'Impostazioni', 'Compila i dati dell autore: il plugin stampa l author box e lo schema.' ),
		);

		foreach ( $daAzione as $regola => $dati ) {
			$mappa[ $regola ] = array(
				'come'      => 'azione',
				'dove'      => $dati[0],
				'etichetta' => $dati[1],
				'spiega'    => $dati[2],
			);
		}

		return $mappa;
	}

	/**
	 * Dove si risolve una regola, se si sa.
	 *
	 * @param string $regola  Identificativo della regola.
	 * @param int    $auditId Audit.
	 * @return array Vuoto se per quella regola non c e un rimedio guidato.
	 */
	public static function per( $regola, $auditId ) {
		$mappa = self::mappa( $auditId );

		return $mappa[ (string) $regola ] ?? array();
	}

	/**
	 * Quanti problemi hanno un rimedio guidato, e di che tipo.
	 *
	 * @param array $rilievi Rilievi dell audit.
	 * @param int   $auditId Audit.
	 * @return array 'plugin', 'azione', 'manuale' => quanti.
	 */
	public static function riassunto( array $rilievi, $auditId ) {
		$mappa = self::mappa( $auditId );
		$conti = array( 'plugin' => 0, 'azione' => 0, 'manuale' => 0 );

		foreach ( $rilievi as $r ) {
			$come = $mappa[ (string) $r['regola'] ]['come'] ?? 'manuale';
			$conti[ $come ]++;
		}

		return $conti;
	}

	/**
	 * Le stesse tre categorie, ma contando le segnalazioni invece dei
	 * controlli.
	 *
	 * «38 controlli non superati, 18 a mano» non dice quanto lavoro resta:
	 * un controllo a mano puo valere 283 segnalazioni e un altro una sola.
	 * Chi guarda il numero grosso - 1.698 - e preme «correggi tutto» si
	 * aspetta di vederlo andare a zero, e quando non succede conclude che il
	 * gestionale non salva niente. Qui si dice prima quanto ne puo chiudere.
	 *
	 * E si contano leggendo le occorrenze una per una, non i totali per
	 * regola: senza sapere a quale contenuto si riferisce ogni segnalazione
	 * non si puo dire se il pulsante la tocca. Le pagine servizio restano
	 * fuori da tutto quello che il gestionale fa da solo - e un vincolo
	 * voluto, ripetuto da chi usa il programma - e contarle fra quelle che
	 * il pulsante chiude era una promessa che il pulsante non mantiene.
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Audit.
	 * @return array 'plugin', 'azione', 'manuale', 'pagine', 'totale'.
	 */
	public static function occorrenze( Db $db, $auditId ) {
		$mappa = self::mappa( $auditId );
		$conti = array( 'plugin' => 0, 'azione' => 0, 'manuale' => 0, 'pagine' => 0, 'totale' => 0 );

		$righe = $db->all(
			"SELECT r.regola, d.tipo, COUNT(*) AS quante
			 FROM occorrenza o
			 JOIN rilievo r ON r.id = o.rilievo_id
			 LEFT JOIN documento d
					ON d.audit_id = r.audit_id
				   AND ( d.percorso = o.riferimento OR d.url = o.riferimento )
			 WHERE r.audit_id = ? AND COALESCE( o.applicato, 0 ) = 0
			 GROUP BY r.regola, d.tipo",
			array( (int) $auditId )
		);

		foreach ( $righe as $riga ) {
			$quante = (int) $riga['quante'];
			$come   = $mappa[ (string) $riga['regola'] ]['come'] ?? 'manuale';

			$conti['totale'] += $quante;

			// Una correzione automatica su una pagina non si fa: quella
			// segnalazione resta li qualunque cosa si prema.
			if ( 'page' === (string) $riga['tipo'] && 'plugin' !== $come ) {
				$conti['pagine'] += $quante;
				continue;
			}

			$conti[ $come ] += $quante;
		}

		return $conti;
	}

	/**
	 * I controlli che restano a una persona, dal piu pesante.
	 *
	 * @param array $rilievi Rilievi dell audit.
	 * @param int   $auditId Audit.
	 * @return array[] 'regola', 'titolo', 'occorrenze'.
	 */
	public static function aMano( array $rilievi, $auditId ) {
		$mappa = self::mappa( $auditId );
		$fuori = array();

		foreach ( $rilievi as $r ) {
			if ( isset( $mappa[ (string) $r['regola'] ] ) ) {
				continue;
			}

			$fuori[] = array(
				'regola'     => (string) $r['regola'],
				'titolo'     => (string) ( $r['titolo'] ?? '' ),
				'occorrenze' => (int) ( $r['occorrenze'] ?? 0 ),
			);
		}

		usort(
			$fuori,
			static function ( $a, $b ) {
				return $b['occorrenze'] <=> $a['occorrenze'];
			}
		);

		return $fuori;
	}

	/**
	 * Riassunto per area: quanti rimedi guidati ci sono e dove portano.
	 *
	 * La tabella dei problemi e lunga; il riquadro per area no. Qui si dice,
	 * area per area, quanti dei controlli non superati si risolvono da soli
	 * col plugin, quanti con un pulsante e dove sta quel pulsante.
	 *
	 * @param array $rilievi Rilievi dell audit (servono 'area' e 'regola').
	 * @param int   $auditId Audit.
	 * @return array Chiave area => array con conti, 'dove' ed 'etichetta'.
	 */
	public static function perArea( array $rilievi, $auditId ) {
		$mappa = self::mappa( $auditId );
		$fuori = array();

		foreach ( $rilievi as $r ) {
			$area = (string) ( $r['area'] ?? '' );

			if ( ! isset( $fuori[ $area ] ) ) {
				$fuori[ $area ] = array(
					'plugin'    => 0,
					'azione'    => 0,
					'manuale'   => 0,
					'dove'      => '',
					'etichetta' => '',
					'peso'      => -1,
				);
			}

			$rimedio = $mappa[ (string) $r['regola'] ] ?? array();
			$come    = $rimedio['come'] ?? 'manuale';

			$fuori[ $area ][ $come ]++;

			// Fra i rimedi di un area si mostra quello che tocca piu
			// contenuti: e il pulsante che conviene premere per primo.
			$occorrenze = (int) ( $r['occorrenze'] ?? 0 );

			if ( 'azione' === $come && $occorrenze > $fuori[ $area ]['peso'] ) {
				$fuori[ $area ]['dove']      = $rimedio['dove'];
				$fuori[ $area ]['etichetta'] = $rimedio['etichetta'];
				$fuori[ $area ]['peso']      = $occorrenze;
			}
		}

		return $fuori;
	}
}
