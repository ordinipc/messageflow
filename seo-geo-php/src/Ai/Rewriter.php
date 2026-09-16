<?php
/**
 * Generazione delle bozze con il modello.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Ai;

use SeoGeo\Db;
use SeoGeo\Html;
use SeoGeo\Rimedi;
use SeoGeo\Text;
use Throwable;

/**
 * Prende gli articoli classificati dall audit e produce una bozza per ciascuno.
 *
 * Le bozze non vengono mai pubblicate: restano nel database e come file HTML,
 * in attesa di revisione umana. È una scelta deliberata — pubblicare in massa
 * testo generato senza revisione è esattamente ciò che le linee guida antispam
 * di Google classificano come abuso di contenuti scalati.
 */
class Rewriter {

	/**
	 * Articoli candidati alla riscrittura.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit.
	 * @param array $opzioni 'categorie', 'limite', 'rigenera'.
	 * @return array[]
	 */
	public static function candidati( Db $db, $auditId, array $opzioni = array() ) {
		$regola = trim( (string) ( $opzioni['regola'] ?? '' ) );

		// Quando si sta correggendo un problema preciso - «manca la sezione
		// FAQ», «nessun H2» - contano i contenuti che hanno quel problema,
		// non la categoria del triage: un articolo puo essere buono e
		// mancargli comunque le domande frequenti.
		//
		// E quando si chiede un contenuto preciso la scelta e gia stata
		// fatta da chi lo ha chiesto: filtrarlo di nuovo per categoria lo
		// faceva sparire. E successo davvero: il pilota metteva in coda 349
		// articoli classificati «da mantenere», e qui venivano scartati tutti
		// con un messaggio che dava la colpa a una bozza gia pronta che non
		// c era. Centotre errori, una causa sola.
		$categorie = $opzioni['categorie'] ?? (
			( '' !== $regola || ! empty( $opzioni['solo_documento'] ) )
				? array( 'riscrivere', 'accorpare', 'mantenere' )
				: array( 'riscrivere', 'accorpare' )
		);

		$segnaposto = implode( ',', array_fill( 0, count( $categorie ), '?' ) );

		// Quando la scelta l ha gia fatta una persona - ha cercato quel
		// contenuto e ha premuto «riscrivi questo» - il triage non deve
		// poterlo escludere. E si parte dal documento, non dal triage: un
		// contenuto senza riga di triage non risultava esistere, e il
		// pulsante rispondeva «nessun articolo da riscrivere» su un articolo
		// che si stava guardando.
		$uno = ! empty( $opzioni['solo_documento'] );

		$sql = $uno
			? "SELECT t.*, d.id AS doc_id, d.wp_id, d.titolo, d.url, d.slug, d.parole, d.testo,
					d.focus_keyword AS focus, d.percorso, d.tipo
				FROM documento d
				LEFT JOIN triage t ON t.documento_id = d.id AND t.audit_id = d.audit_id
				WHERE d.audit_id = ?"
			: "SELECT t.*, d.id AS doc_id, d.wp_id, d.titolo, d.url, d.slug, d.parole, d.testo,
					d.focus_keyword AS focus, d.percorso, d.tipo
				FROM triage t
				JOIN documento d ON d.id = t.documento_id
				WHERE t.audit_id = ? AND t.categoria IN ($segnaposto)";

		$parametri = $uno ? array( $auditId ) : array_merge( array( $auditId ), array_values( $categorie ) );

		if ( '' !== $regola ) {
			$sql .= " AND EXISTS (
						SELECT 1 FROM occorrenza o
						JOIN rilievo r ON r.id = o.rilievo_id
						WHERE r.audit_id = t.audit_id AND r.regola = ?
						  AND COALESCE( o.applicato, 0 ) = 0
						  AND ( o.riferimento = d.url
								OR o.riferimento = d.percorso
								OR o.riferimento = RTRIM( d.url, '/' ) )
					)";
			$parametri[] = $regola;
		}

		if ( ! empty( $opzioni['solo_documento'] ) ) {
			$sql        .= ' AND d.id = ?';
			$parametri[] = (int) $opzioni['solo_documento'];
		}

		if ( empty( $opzioni['rigenera'] ) ) {
			// Una riscrittura fatta prima che il problema esistesse non lo
			// chiude. Sei articoli con l anno vecchio nel titolo erano fuori
			// dalla coda con scritto «ha gia una riscrittura pronta»: quella
			// riscrittura l anno vecchio ce l aveva ancora, e cosi il
			// rilievo non si poteva chiudere in nessun modo. Chi ha una
			// bozza che non chiude questa regola rientra.
			$riaperti = self::bozzeCheNonChiudono( $db, $auditId, $regola );
			$senzaBozza = "d.id NOT IN (SELECT documento_id FROM bozza WHERE audit_id = ? AND stato = 'ok')";

			$sql .= $riaperti
				? ' AND ( ' . $senzaBozza . ' OR d.id IN (' . implode( ',', array_map( 'intval', $riaperti ) ) . ') )'
				: ' AND ' . $senzaBozza;

			$parametri[] = $auditId;
		}

		// L intento manca quando il triage non ha classificato il contenuto:
		// senza questo un articolo appena trovato non si ordinerebbe.
		$sql .= " ORDER BY CASE COALESCE( t.intento, '' )
					WHEN 'transazionale' THEN 0 WHEN 'commerciale' THEN 1 ELSE 2 END,
					d.parole ASC";

		if ( ! empty( $opzioni['limite'] ) ) {
			$sql .= ' LIMIT ' . (int) $opzioni['limite'];
		}

		return $db->all( $sql, $parametri );
	}

	/**
	 * Le regole che la riscrittura assistita sa davvero chiudere.
	 *
	 * @return string[]
	 */
	public static function regoleRiscrivibili() {
		$fuori = array();

		foreach ( Rimedi::mappa( 0 ) as $regola => $rimedio ) {
			if ( 'azione' === ( $rimedio['come'] ?? '' ) && false !== strpos( (string) $rimedio['etichetta'], 'Riscrittura' ) ) {
				$fuori[] = (string) $regola;
			}
		}

		return $fuori;
	}

	/**
	 * Tutti i contenuti che hanno almeno un problema che la riscrittura chiude.
	 *
	 * Il pilota automatico prendeva solo gli articoli che il triage segna come
	 * «da riscrivere» o «da accorpare». Su un archivio curato quelli sono
	 * pochi: qui erano 104 su 295, e i restanti 191 - classificati «da
	 * mantenere», perche sono articoli buoni - restavano fuori anche quando
	 * avevano tre o quattro rilievi aperti ciascuno. Il pulsante «fai tutto»
	 * ne lasciava indietro la maggior parte, e i numeri non scendevano.
	 *
	 * Qui il criterio e un altro e piu semplice: si riscrive chi ha qualcosa
	 * da correggere.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit.
	 * @param array $opzioni 'limite'.
	 * @return array[]
	 */
	public static function daCorreggere( Db $db, $auditId, array $opzioni = array() ) {
		$regole = self::regoleRiscrivibili();

		if ( ! $regole ) {
			return array();
		}

		$segnaposto = implode( ',', array_fill( 0, count( $regole ), '?' ) );

		$sql = "SELECT d.id AS doc_id, d.wp_id, d.titolo, d.url, d.slug, d.parole, d.testo,
					d.focus_keyword AS focus, d.percorso,
					COUNT(*) AS quanti
				FROM occorrenza o
				JOIN rilievo r ON r.id = o.rilievo_id
				JOIN documento d ON d.audit_id = r.audit_id AND d.percorso = o.riferimento
				WHERE r.audit_id = ?
				  AND r.regola IN ( $segnaposto )
				  AND COALESCE( o.applicato, 0 ) = 0
				  AND d.tipo = 'post'
				  AND d.id NOT IN ( SELECT documento_id FROM bozza WHERE audit_id = ? AND stato = 'ok' )
				GROUP BY d.id, d.wp_id, d.titolo, d.url, d.slug, d.parole, d.testo, d.focus_keyword, d.percorso
				ORDER BY COUNT(*) DESC, d.parole ASC";

		if ( ! empty( $opzioni['limite'] ) ) {
			$sql .= ' LIMIT ' . (int) $opzioni['limite'];
		}

		return $db->all( $sql, array_merge( array( (int) $auditId ), $regole, array( (int) $auditId ) ) );
	}

	/**
	 * I contenuti la cui riscrittura gia pronta non chiude questa regola.
	 *
	 * Le bozze nascono da una fotografia del sito: una regola aggiunta dopo
	 * - l anno superato nel titolo, per dirne una - non era fra i problemi
	 * quando quel testo e stato scritto, e quindi il testo non la risolve.
	 * Tenerli fuori con scritto «ha gia una riscrittura pronta» vuol dire
	 * che quel rilievo non si chiude piu, comunque si prema.
	 *
	 * Si guardano solo le regole che si sanno misurare: sulle altre non c e
	 * modo di dire se la bozza le chiuda, e nel dubbio non si rigenera
	 * niente - rigenerare costa token.
	 *
	 * @param Db     $db      Database.
	 * @param int    $auditId Audit.
	 * @param string $regola  Regola su cui si sta lavorando.
	 * @return int[] Identificativi dei documenti.
	 */
	public static function bozzeCheNonChiudono( Db $db, $auditId, $regola ) {
		$regola = trim( (string) $regola );

		if ( '' === $regola || ! Chiusura::verificabili( array( $regola ) ) ) {
			return array();
		}

		$righe = $db->all(
			"SELECT b.documento_id, b.titolo, b.meta_title, b.corpo_html, b.in_breve, b.faq,
					d.url, d.slug, d.wp_id, d.focus_keyword AS focus
			 FROM bozza b JOIN documento d ON d.id = b.documento_id
			 WHERE b.audit_id = ? AND b.stato = 'ok' AND d.tipo = 'post'",
			array( (int) $auditId )
		);

		$fuori = array();

		foreach ( $righe as $r ) {
			$dati = array(
				'titolo'     => (string) $r['titolo'],
				'meta_title' => (string) $r['meta_title'],
				'in_breve'   => (string) $r['in_breve'],
				'corpo_html' => (string) $r['corpo_html'],
				'faq'        => json_decode( (string) $r['faq'], true ) ?: array(),
			);

			if ( Chiusura::controlla( $dati, $r, array( $regola ) ) ) {
				$fuori[] = (int) $r['documento_id'];
			}
		}

		return $fuori;
	}

	/**
	 * Cerca un contenuto per titolo, indirizzo, slug o numero.
	 *
	 * Serve a riscrivere una cosa precisa senza passare dagli elenchi: chi
	 * conosce il proprio sito sa quale articolo vuole rifare, e finora
	 * l unica strada era che lo segnalasse una regola.
	 *
	 * Si cerca dentro all analisi, non sul sito: e li che stanno il testo e
	 * i problemi da cui parte la riscrittura.
	 *
	 * @param Db     $db      Database.
	 * @param int    $auditId Audit.
	 * @param string $cosa    Titolo, indirizzo, slug o numero WordPress.
	 * @param int    $quanti  Quanti risultati al massimo.
	 * @return array[]
	 */
	public static function cerca( Db $db, $auditId, $cosa, $quanti = 20 ) {
		$cosa = trim( (string) $cosa );

		if ( mb_strlen( $cosa ) < 2 ) {
			return array();
		}

		// Chi incolla l indirizzo completo cerca il percorso, non una frase
		// che contiene «https».
		$percorso = (string) parse_url( $cosa, PHP_URL_PATH );
		$ago      = '%' . ( '' !== $percorso && '/' !== $percorso ? trim( $percorso, '/' ) : $cosa ) . '%';

		return $db->all(
			"SELECT d.id AS doc_id, d.wp_id, d.titolo, d.url, d.slug, d.percorso, d.tipo, d.parole,
					d.focus_keyword AS focus,
					( SELECT COUNT(*) FROM bozza b WHERE b.documento_id = d.id AND b.stato = 'ok' ) AS bozze,
					( SELECT COUNT(*) FROM occorrenza o
						JOIN rilievo r ON r.id = o.rilievo_id
						WHERE r.audit_id = d.audit_id
						  AND COALESCE( o.applicato, 0 ) = 0
						  AND ( o.riferimento = d.percorso OR o.riferimento = d.url ) ) AS problemi
			 FROM documento d
			 WHERE d.audit_id = ? AND d.stato = 'publish'
			   AND ( d.titolo LIKE ? OR d.slug LIKE ? OR d.percorso LIKE ? OR d.wp_id = ? )
			 ORDER BY d.tipo ASC, d.titolo ASC
			 LIMIT " . max( 1, (int) $quanti ),
			array( (int) $auditId, $ago, $ago, $ago, $cosa )
		);
	}

	/**
	 * Perche i contenuti con questo problema non sono in lista.
	 *
	 * «Nessun contenuto ha piu questo problema» e una risposta che non si
	 * puo controllare: la tabella dell audit dice 2, questa pagina dice 0, e
	 * chi legge deve scegliere a quale delle due credere. Qui si prende ogni
	 * occorrenza della regola e si dice, una per una, che fine ha fatto.
	 *
	 * @param Db     $db      Database.
	 * @param int    $auditId Audit.
	 * @param string $regola  Regola.
	 * @param array  $inCoda  Identificativi dei documenti gia in coda: quelli
	 *                        non sono esclusi, e contarli fra gli esclusi
	 *                        darebbe due volte lo stesso contenuto.
	 * @return array[] 'riferimento', 'titolo', 'url', 'tipo', 'motivo', 'azione'.
	 */
	public static function esclusi( Db $db, $auditId, $regola, array $inCoda = array() ) {
		$inCoda      = array_flip( array_map( 'intval', $inCoda ) );
		$nonChiudono = self::bozzeCheNonChiudono( $db, $auditId, $regola );
		$righe = $db->all(
			"SELECT o.riferimento, o.applicato, o.dettaglio,
					d.id AS doc_id, d.titolo, d.url, d.tipo, d.parole,
					t.categoria,
					( SELECT COUNT(*) FROM bozza b WHERE b.documento_id = d.id AND b.stato = 'ok' ) AS bozze
			 FROM occorrenza o
			 JOIN rilievo r ON r.id = o.rilievo_id
			 LEFT JOIN documento d
					ON d.audit_id = r.audit_id
				   AND ( d.percorso = o.riferimento OR d.url = o.riferimento )
			 LEFT JOIN triage t ON t.documento_id = d.id AND t.audit_id = r.audit_id
			 WHERE r.audit_id = ? AND r.regola = ?
			 ORDER BY COALESCE( o.applicato, 0 ) ASC, o.id ASC
			 LIMIT 200",
			array( (int) $auditId, (string) $regola )
		);

		$fuori = array();

		foreach ( $righe as $r ) {
			if ( null !== $r['doc_id'] && isset( $inCoda[ (int) $r['doc_id'] ] ) ) {
				continue;
			}

			$motivo = '';
			$azione = '';

			if ( ! empty( $r['applicato'] ) ) {
				$motivo = 'già sistemato dal gestionale dopo la lettura del sito';
			} elseif ( null === $r['doc_id'] ) {
				$motivo = 'non risulta più fra i contenuti analizzati';
			} elseif ( 'page' === (string) $r['tipo'] ) {
				// Vincolo voluto: le pagine servizio sono poche e scritte a
				// mano, la riscrittura automatica non le tocca.
				$motivo = 'è una pagina, non un articolo: la riscrittura automatica non tocca le pagine';
				$azione = 'manuale';
			} elseif ( (int) $r['bozze'] > 0 ) {
				$motivo = 'ha già una riscrittura pronta';
				$azione = 'bozza';

				if ( in_array( (int) $r['doc_id'], $nonChiudono, true ) ) {
					// Dire solo «ha gia una riscrittura pronta» mandava a
					// guardare una bozza che il problema non lo risolve.
					$motivo = 'ha una riscrittura pronta, ma quella riscrittura non chiude questo problema: va rigenerata';
					$azione = 'rigenera';
				}
			} elseif ( null === $r['categoria'] ) {
				$motivo = 'non è stato classificato dal triage';
			} else {
				$motivo = 'classificato come «' . $r['categoria'] . '»';
			}

			$fuori[] = array(
				'riferimento' => (string) $r['riferimento'],
				'titolo'      => (string) ( $r['titolo'] ?: $r['riferimento'] ),
				'url'         => (string) $r['url'],
				'tipo'        => (string) $r['tipo'],
				'dettaglio'   => (string) $r['dettaglio'],
				'motivo'      => $motivo,
				'azione'      => $azione,
			);
		}

		return $fuori;
	}

	/**
	 * Stima di token e costo senza chiamare l API.
	 *
	 * @param array $articoli Candidati.
	 * @param array $cfgAi    Sezione 'ai' della configurazione.
	 * @return array
	 */
	public static function stima( array $articoli, array $cfgAi ) {
		$in  = 0;
		$out = 0;

		foreach ( $articoli as $a ) {
			// Prompt: istruzioni fisse più il testo di partenza (troncato a 6.000 caratteri).
			$in  += Gemini::stimaToken( mb_substr( (string) $a['testo'], 0, 6000 ) ) + 900;
			$out += 2200; // Un articolo da 1.200 parole più le meta e le FAQ.
		}

		$prezzi = $cfgAi['prezzo_per_milione'] ?? array( 'input' => 0, 'output' => 0 );

		return array(
			'articoli'      => count( $articoli ),
			'token_in'      => $in,
			'token_out'     => $out,
			'costo_stimato' => round( $in / 1000000 * (float) $prezzi['input'] + $out / 1000000 * (float) $prezzi['output'], 2 ),
		);
	}

	/**
	 * Le note del modello ridotte a una riga leggibile.
	 *
	 * Nel modo "migliora" si chiede un elenco di cosa e stato cambiato, e il
	 * modello risponde con un elenco vero: un array. Salvato con un cast a
	 * stringa diventava la parola "Array", che e quello che compariva nella
	 * tabella delle bozze.
	 *
	 * @param mixed $note Quello che ha risposto il modello.
	 * @return string
	 */
	public static function note( $note ) {
		if ( is_array( $note ) ) {
			$righe = array();

			foreach ( $note as $voce ) {
				$voce = is_array( $voce ) ? implode( ' ', array_map( 'strval', $voce ) ) : (string) $voce;
				$voce = trim( $voce );

				if ( '' !== $voce ) {
					$righe[] = $voce;
				}
			}

			return implode( ' · ', $righe );
		}

		return trim( (string) $note );
	}

	/**
	 * Problemi che l audit ha trovato su questa pagina.
	 *
	 * Sono la sola ragione per cui il testo viene toccato: senza l elenco il
	 * modello non sa che cosa sistemare e rifa tutto da capo.
	 *
	 * @param Db    $db       Database.
	 * @param int   $auditId  Audit.
	 * @param array $articolo Riga con url e percorso.
	 * @return array[]
	 */
	public static function problemi( Db $db, $auditId, array $articolo ) {
		$riferimenti = array_values(
			array_unique(
				array_filter(
					array(
						(string) ( $articolo['url'] ?? '' ),
						(string) ( $articolo['percorso'] ?? '' ),
						rtrim( (string) ( $articolo['url'] ?? '' ), '/' ),
					)
				)
			)
		);

		if ( ! $riferimenti ) {
			return array();
		}

		$segnaposto = implode( ',', array_fill( 0, count( $riferimenti ), '?' ) );

		$trovati = $db->all(
			"SELECT r.regola, r.titolo, r.gravita, o.dettaglio
			 FROM occorrenza o JOIN rilievo r ON r.id = o.rilievo_id
			 WHERE r.audit_id = ? AND o.riferimento IN ($segnaposto)
			   AND COALESCE( o.applicato, 0 ) = 0
			 ORDER BY CASE r.gravita WHEN 'alto' THEN 0 WHEN 'medio' THEN 1 ELSE 2 END
			 LIMIT 40",
			array_merge( array( $auditId ), $riferimenti )
		);

		// Chi scrive il testo non puo risolvere i problemi tecnici, e dirglielo
		// fa danni: davanti a «manca lo schema Article» un modello scrive il
		// JSON-LD dentro all articolo, e quello finisce in pagina come testo.
		// Quelli li stampa il plugin: qui restano solo i problemi del testo.
		$rimedi  = Rimedi::mappa( (int) $auditId );
		$restano = array();

		foreach ( $trovati as $riga ) {
			if ( 'plugin' === ( $rimedi[ (string) $riga['regola'] ]['come'] ?? '' ) ) {
				continue;
			}

			$restano[] = $riga;
		}

		return array_slice( $restano, 0, 25 );
	}

	/**
	 * Link interni suggeriti per un documento, dal piano già calcolato.
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Audit.
	 * @param string $percorso Percorso del documento.
	 * @return array<string,string> anchor => url
	 */
	private static function linkSuggeriti( Db $db, $auditId, $percorso ) {
		$righe = $db->all(
			'SELECT l.anchor, d.url FROM link_piano l
			 LEFT JOIN documento d ON d.audit_id = l.audit_id AND d.percorso = l.a
			 WHERE l.audit_id = ? AND l.da = ? LIMIT 4',
			array( $auditId, $percorso )
		);

		$out = array();

		foreach ( $righe as $r ) {
			if ( ! empty( $r['url'] ) ) {
				$out[ $r['anchor'] ] = $r['url'];
			}
		}

		return $out;
	}

	/**
	 * Genera le bozze.
	 *
	 * @param Db       $db       Database.
	 * @param Gemini   $gemini   Client.
	 * @param int      $auditId  Audit.
	 * @param array    $cfg      Configurazione completa.
	 * @param array    $opzioni  'categorie', 'limite', 'rigenera', 'cartella', 'su_progresso'.
	 * @return array Riepilogo dell esecuzione.
	 */
	public static function esegui( Db $db, Gemini $gemini, $auditId, array $cfg, array $opzioni = array() ) {
		$articoli   = self::candidati( $db, $auditId, $opzioni );
		$istruzioni = Prompt::istruzioni( $cfg );
		$modello    = $cfg['ai']['modello'] ?? 'gemini-2.5-flash';
		$cartella   = $opzioni['cartella'] ?? __DIR__ . '/../../storage/export/audit-' . (int) $auditId . '/bozze';
		$progresso  = $opzioni['su_progresso'] ?? null;

		// Migliorare e il modo giusto per un archivio che gia funziona; la
		// riscrittura da zero serve quando il testo e davvero da buttare.
		// Si sceglie in configurazione, e il valore predefinito e migliorare.
		$migliora = array_key_exists( 'migliora', $opzioni )
			? ! empty( $opzioni['migliora'] )
			: ! empty( $cfg['ai']['migliora_invece_di_riscrivere'] );

		// Che cosa stampa il sito da se: serve al controllo su ONP-09.
		$stampa = (array) ( $opzioni['stampa'] ?? array() );

		if ( ! is_dir( $cartella ) ) {
			mkdir( $cartella, 0775, true );
		}

		$fatte   = 0;
		$fallite = 0;
		$errori  = array();
		$scadenza = isset( $opzioni['secondi_max'] ) ? time() + (int) $opzioni['secondi_max'] : null;

		// Zero candidati e tempo scaduto sono cose diverse, e chi legge il
		// registro deve poterle distinguere: "il modello non ha prodotto la
		// bozza" le confondeva in un unica frase che dava la colpa al modello.
		if ( ! $articoli ) {
			// Il messaggio mandava «all elenco delle bozze», che non e il nome
			// di nessuna pagina del gestionale: la pagina si chiama «Vecchio
			// e nuovo». Un messaggio che indica un posto inesistente e
			// peggio di nessun messaggio.
			$errori[] = ! empty( $opzioni['solo_documento'] )
				? 'questo contenuto ha già una riscrittura pronta: si vede in «Vecchio e nuovo», dove si applica al sito o si rigenera'
				: 'nessun articolo da riscrivere: o hanno già tutti una riscrittura in «Vecchio e nuovo», o il triage non ne ha segnalato nessuno';
		}

		// Fermarsi al tetto di tempo non e un guasto: e il funzionamento
		// normale su hosting condiviso, e chi guarda lo deve leggere come
		// "continuo", non come "qualcosa e andato storto".
		$interrotto = false;

		foreach ( $articoli as $a ) {
			if ( $scadenza && time() > $scadenza ) {
				$interrotto = true;
				break;
			}

			try {
				$link     = self::linkSuggeriti( $db, $auditId, $a['percorso'] );
				$problemi = self::problemi( $db, $auditId, $a );

				// Si misura tutto quello che si sa misurare, non solo i
				// rilievi da cui si e partiti. Controllando le sole regole
				// richieste, una riscrittura che chiudeva «meno di 600
				// parole» scendendo a 250 risultava riuscita, e intanto
				// apriva «meno di 300 parole», che e critico. E cosi che il
				// totale saliva - da 1.290 a 1.910 - proprio mentre si
				// correggeva.
				$daChiudere = Chiusura::VERIFICABILI;

				$ancora    = '';
				$aperte    = array();
				$dati      = array();
				$corpo     = '';
				$tentativi = 0;

				// Due tentativi al massimo, e il secondo non e una
				// ripetizione: riparte con l elenco di quello che il primo
				// non ha chiuso, misurato sul testo che ha prodotto.
				for ( $t = 0; $t < 2; $t++ ) {
					$tentativi = $t + 1;

					// Migliorare invece di rifare da capo: il testo pubblicato
					// resta quello dell autore e si interviene solo dove l audit
					// ha trovato un problema. Riscrivere tutto butta via anche
					// quello che funzionava.
					$dati = $migliora
						? $gemini->generaJson( $istruzioni, Prompt::miglioramento( $a, $a, $link, $problemi, $cfg, $ancora ) )
						: $gemini->generaJson( $istruzioni, Prompt::articolo( $a, $a, $link, $cfg ) );

					// Il modello, davanti a un audit che dice «manca lo schema
					// Article», a volte lo scrive nel corpo dell articolo. Il
					// tag <script> lo toglie WordPress, il JSON dentro no: resta
					// in pagina come testo. Lo schema lo stampa il plugin, qui
					// non ci deve arrivare.
					$corpo = Html::senzaDatiStrutturati( (string) ( $dati['corpo_html'] ?? '' ) );

					if ( '' === trim( $corpo ) ) {
						throw new \RuntimeException( 'il modello non ha restituito il corpo dell articolo' );
					}

					$dati['corpo_html'] = $corpo;
					$aperte             = Chiusura::controlla( $dati, $a, $daChiudere, $stampa );

					// Niente da ridire, oppure niente che si sappia misurare:
					// un secondo giro costerebbe token senza dire altro.
					// E la riscrittura da zero non riceve l elenco dei
					// problemi, quindi nemmeno un secondo giro utile.
					if ( ! $aperte || ! $migliora ) {
						break;
					}

					$ancora = Chiusura::istruzioni( $aperte );
				}

				$parole = Text::wordCount( Html::stripTags( $corpo ) );

				$db->run( 'DELETE FROM bozza WHERE audit_id = ? AND documento_id = ?', array( $auditId, $a['doc_id'] ) );

				$nome_file = $a['slug'] . '.html';

				// Un tentativo fallito in precedenza ha lasciato una riga di
				// errore: adesso che la bozza c e, quella riga racconta una
				// cosa che non e piu vera e va tolta, altrimenti nell elenco
				// resta un ERRORE accanto a una bozza riuscita.
				$db->run(
					"DELETE FROM bozza WHERE audit_id = ? AND documento_id = ? AND stato <> 'ok'",
					array( $auditId, (int) $a['doc_id'] )
				);

				$db->insert(
					'bozza',
					array(
						'audit_id'         => $auditId,
						'documento_id'     => (int) $a['doc_id'],
						'wp_id'            => $a['wp_id'],
						'stato'            => 'ok',
						'modello'          => $modello,
						'titolo'           => (string) ( $dati['titolo'] ?? $a['titolo'] ),
						'meta_title'       => (string) ( $dati['meta_title'] ?? '' ),
						'meta_description' => (string) ( $dati['meta_description'] ?? '' ),
						'in_breve'         => (string) ( $dati['in_breve'] ?? '' ),
						'corpo_html'       => $corpo,
						'faq'              => json_encode( $dati['faq'] ?? array(), JSON_UNESCAPED_UNICODE ),
						'da_verificare'    => json_encode( $dati['da_verificare'] ?? array(), JSON_UNESCAPED_UNICODE ),
						'note'             => self::note( $dati['note'] ?? '' ),
						'parole'           => $parole,
						'token_in'         => 0,
						'token_out'        => 0,
						'errore'           => '',
						'file'             => $nome_file,
						// Che cosa la riscrittura non e riuscita a chiudere: scritto
						// adesso, invece di farlo scoprire dalla rilettura di domani.
						'rimaste'          => Chiusura::riassunto( $aperte ),
						'tentativi'        => $tentativi,
						'creato_il'        => date( 'Y-m-d H:i:s' ),
					)
				);

				file_put_contents( $cartella . '/' . $nome_file, self::fileBozza( $dati, $a, $modello ) );
				$fatte++;
			} catch ( Throwable $e ) {
				// Una riga di errore per contenuto, non una per tentativo.
				$db->run(
					"DELETE FROM bozza WHERE audit_id = ? AND documento_id = ? AND stato <> 'ok'",
					array( $auditId, (int) $a['doc_id'] )
				);

				$db->insert(
					'bozza',
					array(
						'audit_id'     => $auditId,
						'documento_id' => (int) $a['doc_id'],
						'wp_id'        => $a['wp_id'],
						'stato'        => 'errore',
						'modello'      => $modello,
						'titolo'       => $a['titolo'],
						'errore'       => $e->getMessage(),
						'creato_il'    => date( 'Y-m-d H:i:s' ),
					)
				);
				$fallite++;
				// Il motivo va restituito a chi ha chiamato: "non ha prodotto la
				// bozza" non dice se manca la chiave, se è finita la quota o altro.
				$errori[] = $e->getMessage();
			}

			if ( $progresso ) {
				$progresso( $a, $fatte, $fallite, count( $articoli ) );
			}
		}

		return array(
			'candidati'  => count( $articoli ),
			'generate'   => $fatte,
			'fallite'    => $fallite,
			'errori'     => $errori,
			'interrotto' => $interrotto,
			'consumo'    => $gemini->consumo(),
			'cartella'   => $cartella,
		);
	}

	/**
	 * Gruppi di articoli che si contendono la stessa ricerca.
	 *
	 * Si ricostruiscono dai redirect calcolati dal triage: tutti gli articoli
	 * che puntano allo stesso URL confluiscono in quell articolo.
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Audit.
	 * @return array[] Elenco di gruppi con 'vincitore' e 'assorbiti'.
	 */
	public static function gruppi( Db $db, $auditId, array $opzioni = array() ) {
		$righe = $db->all(
			"SELECT t.redirect_a, t.categoria, d.id AS doc_id, d.wp_id, d.titolo, d.url, d.slug,
					d.testo, d.parole, d.percorso, d.focus_keyword AS focus
			 FROM triage t JOIN documento d ON d.id = t.documento_id
			 WHERE t.audit_id = ? AND t.categoria = 'accorpare' AND t.redirect_a <> ''",
			array( $auditId )
		);

		// I gruppi gia fusi restano fuori: senza questo, premere il pulsante
		// una seconda volta rifaceva sempre i primi della lista e si pagava
		// due volte lo stesso lavoro, senza mai arrivare in fondo.
		$gia_fatti = array();

		if ( empty( $opzioni['rigenera'] ) ) {
			foreach ( $db->all( "SELECT documento_id FROM bozza WHERE audit_id = ? AND stato = 'ok'", array( $auditId ) ) as $b ) {
				$gia_fatti[ (int) $b['documento_id'] ] = true;
			}
		}

		$per_destinazione = array();

		foreach ( $righe as $r ) {
			$per_destinazione[ $r['redirect_a'] ][] = $r;
		}

		$gruppi = array();

		foreach ( $per_destinazione as $url => $assorbiti ) {
			$vincitore = $db->one(
				'SELECT id AS doc_id, wp_id, titolo, url, slug, testo, parole, percorso, focus_keyword AS focus
				 FROM documento WHERE audit_id = ? AND url = ?',
				array( $auditId, $url )
			);

			if ( ! $vincitore || isset( $gia_fatti[ (int) $vincitore['doc_id'] ] ) ) {
				continue;
			}

			$gruppi[] = array( 'vincitore' => $vincitore, 'assorbiti' => $assorbiti );
		}

		return $gruppi;
	}

	/**
	 * Fonde i gruppi di articoli sovrapposti in un unico testo.
	 *
	 * @param Db     $db      Database.
	 * @param Gemini $gemini  Client.
	 * @param int    $auditId Audit.
	 * @param array  $cfg     Configurazione.
	 * @param array  $opzioni 'limite', 'cartella', 'su_progresso', 'secondi_max'.
	 * @return array
	 */
	public static function consolida( Db $db, Gemini $gemini, $auditId, array $cfg, array $opzioni = array() ) {
		$gruppi     = self::gruppi( $db, $auditId, $opzioni );
		$istruzioni = Prompt::istruzioni( $cfg );

		if ( ! empty( $opzioni['solo_documento'] ) ) {
			$gruppi = array_values(
				array_filter(
					$gruppi,
					static fn( $g ) => (int) $g['vincitore']['doc_id'] === (int) $opzioni['solo_documento']
				)
			);
		}
		$modello    = $cfg['ai']['modello'] ?? 'gemini-2.5-flash';
		$cartella   = $opzioni['cartella'] ?? dirname( __DIR__, 2 ) . '/storage/export/audit-' . (int) $auditId . '/bozze';
		$progresso  = $opzioni['su_progresso'] ?? null;

		if ( ! empty( $opzioni['limite'] ) ) {
			$gruppi = array_slice( $gruppi, 0, (int) $opzioni['limite'] );
		}

		if ( ! is_dir( $cartella ) ) {
			mkdir( $cartella, 0775, true );
		}

		$fatti    = 0;
		$falliti  = 0;
		$errori   = array();
		$scadenza = isset( $opzioni['secondi_max'] ) ? time() + (int) $opzioni['secondi_max'] : null;

		if ( ! $gruppi ) {
			$errori[] = ! empty( $opzioni['solo_documento'] )
				? 'per questo contenuto non risulta più un gruppo da accorpare: forse la fusione è già stata fatta'
				: 'nessun gruppo di articoli da accorpare';
		}

		$interrotto = false;

		foreach ( $gruppi as $gruppo ) {
			if ( $scadenza && time() > $scadenza ) {
				$interrotto = true;
				break;
			}

			$vincitore = $gruppo['vincitore'];

			try {
				$link = self::linkSuggeriti( $db, $auditId, $vincitore['percorso'] );
				$dati = $gemini->generaJson( $istruzioni, Prompt::accorpamento( $vincitore, $gruppo['assorbiti'], $link, $cfg ) );

				// Il modello, davanti a un audit che dice «manca lo schema
				// Article», a volte lo scrive nel corpo dell articolo. Il
				// tag <script> lo toglie WordPress, il JSON dentro no: resta
				// in pagina come testo. Lo schema lo stampa il plugin, qui
				// non ci deve arrivare.
				$corpo = Html::senzaDatiStrutturati( (string) ( $dati['corpo_html'] ?? '' ) );

				if ( '' === trim( $corpo ) ) {
					throw new \RuntimeException( 'il modello non ha restituito il corpo dell articolo' );
				}

				$titoli_assorbiti = implode( ', ', array_column( $gruppo['assorbiti'], 'titolo' ) );

				$db->run( 'DELETE FROM bozza WHERE audit_id = ? AND documento_id = ?', array( $auditId, $vincitore['doc_id'] ) );

				$nome_file = $vincitore['slug'] . '-accorpato.html';

				$db->insert(
					'bozza',
					array(
						'audit_id'         => $auditId,
						'documento_id'     => (int) $vincitore['doc_id'],
						'wp_id'            => $vincitore['wp_id'],
						'stato'            => 'ok',
						'modello'          => $modello,
						'titolo'           => (string) ( $dati['titolo'] ?? $vincitore['titolo'] ),
						'meta_title'       => (string) ( $dati['meta_title'] ?? '' ),
						'meta_description' => (string) ( $dati['meta_description'] ?? '' ),
						'in_breve'         => (string) ( $dati['in_breve'] ?? '' ),
						'corpo_html'       => $corpo,
						'faq'              => json_encode( $dati['faq'] ?? array(), JSON_UNESCAPED_UNICODE ),
						'da_verificare'    => json_encode( $dati['da_verificare'] ?? array(), JSON_UNESCAPED_UNICODE ),
						'note'             => 'Accorpa ' . count( $gruppo['assorbiti'] ) . ' articoli: ' . $titoli_assorbiti
							. '. ' . self::note( $dati['note'] ?? '' ),
						'parole'           => Text::wordCount( Html::stripTags( $corpo ) ),
						'token_in'         => 0,
						'token_out'        => 0,
						'errore'           => '',
						'file'             => $nome_file,
						'creato_il'        => date( 'Y-m-d H:i:s' ),
					)
				);

				file_put_contents( $cartella . '/' . $nome_file, self::fileBozza( $dati, $vincitore, $modello ) );
				$fatti++;
			} catch ( Throwable $e ) {
				$falliti++;
				$errori[] = $e->getMessage();
			}

			if ( $progresso ) {
				$progresso( $vincitore, $fatti, $falliti, count( $gruppi ) );
			}
		}

		return array(
			'gruppi'     => count( $gruppi ),
			'generate'   => $fatti,
			'fallite'    => $falliti,
			'errori'     => $errori,
			'interrotto' => $interrotto,
			'consumo'    => $gemini->consumo(),
			'cartella'   => $cartella,
		);
	}

	/**
	 * File HTML della bozza, pronto da incollare nell editor di WordPress.
	 *
	 * @param array  $dati     Risposta del modello.
	 * @param array  $articolo Articolo di partenza.
	 * @param string $modello  Modello usato.
	 * @return string
	 */
	public static function fileBozza( array $dati, array $articolo, $modello ) {
		$esc = static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' );

		$faq = '';
		foreach ( (array) ( $dati['faq'] ?? array() ) as $f ) {
			$faq .= '<h3>' . $esc( $f['domanda'] ?? '' ) . '</h3><p>' . $esc( $f['risposta'] ?? '' ) . "</p>\n";
		}

		$verifiche = '';
		foreach ( (array) ( $dati['da_verificare'] ?? array() ) as $v ) {
			$verifiche .= '<li>' . $esc( $v ) . "</li>\n";
		}

		return '<!-- BOZZA generata con ' . $esc( $modello ) . ' il ' . date( 'Y-m-d H:i' ) . " -->\n"
			. '<!-- Articolo di partenza: ' . $esc( $articolo['url'] ) . " -->\n"
			. '<!-- DA RIVEDERE PRIMA DELLA PUBBLICAZIONE: sostituisci ogni [DA VERIFICARE: ...] con dati reali -->'
			. "\n\n<!-- Title SEO: " . $esc( $dati['meta_title'] ?? '' ) . " -->\n"
			. '<!-- Meta description: ' . $esc( $dati['meta_description'] ?? '' ) . " -->\n\n"
			. '<h1>' . $esc( $dati['titolo'] ?? $articolo['titolo'] ) . "</h1>\n\n"
			. '<div class="mdi-in-breve"><p><strong>In breve:</strong> ' . $esc( $dati['in_breve'] ?? '' ) . "</p></div>\n\n"
			. ( $dati['corpo_html'] ?? '' ) . "\n"
			. ( '' !== $faq ? "\n<h2>Domande frequenti</h2>\n" . $faq : '' )
			. ( '' !== $verifiche ? "\n<!-- DATI DA INSERIRE PRIMA DI PUBBLICARE:\n<ul>\n" . $verifiche . "</ul>\n-->\n" : '' );
	}
}
