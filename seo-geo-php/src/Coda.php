<?php
/**
 * Pilota automatico: coda di operazioni eseguite senza sorveglianza.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

use SeoGeo\Ai\Gemini;
use SeoGeo\Ai\Immagini;
use SeoGeo\Ai\Rewriter;
use SeoGeo\Bridge\WordPress;
use SeoGeo\Fix\Meta;
use SeoGeo\Html;
use SeoGeo\Search\Prestazioni;
use Throwable;

/**
 * Trasforma il piano dell audit in una fila di operazioni e le esegue a lotti.
 *
 * Il lavoro non sta in una sola richiesta: 326 meta si scrivono in pochi
 * secondi, ma 220 riscritture con un modello linguistico richiedono ore. La
 * coda viene consumata un pezzo alla volta, con un tetto di tempo per giro, e
 * riprende da dove si era fermata: così funziona anche su hosting condiviso.
 */
class Coda {

	const ATTESA  = 'attesa';
	const FATTO   = 'fatto';
	const ERRORE  = 'errore';
	const SALTATO = 'saltato';

	/** @var array<string,string> Etichette leggibili dei tipi di operazione. */
	const TIPI = array(
		'config'        => 'Dati aziendali al sito',
		'meta'          => 'Meta degli articoli',
		'meta_pagine'   => 'Meta delle pagine',
		'meta_mirata'   => 'Meta sulla ricerca vera',
		'redirect'      => 'Redirect 301',
		'categorie'     => 'Categorie',
		'accorpa'       => 'Accorpamento',
		'bozza'         => 'Riscrittura',
		'immagine'      => 'Immagine in evidenza',
		'comprimi'      => 'Ricompressione delle immagini pesanti',
		'applica_bozza' => 'Pubblicazione della riscrittura',
		'cestina'       => 'Contenuti nel cestino',
	);

	/**
	 * Gruppi di operazioni che si possono scegliere singolarmente.
	 *
	 * Servono perché "genera le immagini mancanti" non deve trascinarsi dietro
	 * duecento riscritture: sono lavori diversi, con costi diversi.
	 *
	 * @var array<string,array>
	 */
	const GRUPPI = array(
		'config'    => array( 'titolo' => 'Dati aziendali al sito', 'costo' => false ),
		'meta'      => array( 'titolo' => 'Meta degli articoli', 'costo' => false ),
		'struttura' => array( 'titolo' => 'Redirect 301 e categorie', 'costo' => false ),
		'accorpa'   => array( 'titolo' => 'Accorpamento degli articoli che si cannibalizzano', 'costo' => true ),
		'bozza'     => array( 'titolo' => 'Riscrittura degli articoli', 'costo' => true ),
		'immagine'  => array( 'titolo' => 'Immagini in evidenza mancanti', 'costo' => true ),
		'comprimi'  => array( 'titolo' => 'Ricompressione delle immagini pesanti', 'costo' => false ),
	);

	/**
	 * Condizione SQL dei contenuti le cui meta sul sito sono diverse da quelle
	 * previste.
	 *
	 * Riscrivere anche le trecento che sono già a posto non fa danno, ma è
	 * lavoro inutile, allunga i tempi e nasconde quello che sta davvero
	 * cambiando. Il confronto è con quello che il sito aveva quando è stato
	 * letto l ultima volta.
	 *
	 * Serve anche al pulsante "Applica sul sito", non solo al pilota: i due
	 * percorsi devono toccare esattamente gli stessi contenuti, altrimenti il
	 * numero mostrato nella pagina e quello che viene scritto non coincidono.
	 *
	 * @return string
	 */
	public static function soloDaCambiare() {
		return " AND ( TRIM(COALESCE(m.title_nuovo, '')) <> TRIM(COALESCE(d.seo_title, ''))
				  OR TRIM(COALESCE(m.description_nuova, '')) <> TRIM(COALESCE(d.seo_description, '')) )";
	}

	/**
	 * Quante meta cambierebbero davvero, per tipo di contenuto.
	 *
	 * @param Db     $db      Database.
	 * @param int    $auditId Audit.
	 * @param string $tipo    'post' oppure 'page'.
	 * @return int
	 */
	public static function metaDaCambiare( Db $db, $auditId, $tipo = 'post' ) {
		return (int) $db->one(
			'SELECT COUNT(*) n FROM meta_piano m JOIN documento d ON d.id = m.documento_id
			 WHERE m.audit_id = ? AND d.tipo = ?' . self::soloDaCambiare(),
			array( $auditId, $tipo )
		)['n'];
	}

	/**
	 * Le meta che cambierebbero, con il prima e il dopo.
	 *
	 * @param Db     $db      Database.
	 * @param int    $auditId Audit.
	 * @param string $tipo    Tipo di contenuto.
	 * @param int    $limite  Quante restituirne.
	 * @return array[]
	 */
	public static function anteprimaMeta( Db $db, $auditId, $tipo = 'post', $limite = 50 ) {
		return $db->all(
			'SELECT d.titolo, d.percorso, d.seo_title AS title_ora, m.title_nuovo AS title_dopo,
					d.seo_description AS desc_ora, m.description_nuova AS desc_dopo
			 FROM meta_piano m JOIN documento d ON d.id = m.documento_id
			 WHERE m.audit_id = ? AND d.tipo = ?' . self::soloDaCambiare() . '
			 ORDER BY m.id ASC LIMIT ' . (int) $limite,
			array( $auditId, $tipo )
		);
	}

	/**
	 * Quanto costerà, prima di premere il pulsante.
	 *
	 * Non è un preventivo al centesimo: è l ordine di grandezza, che è quello
	 * che serve per decidere. Il conto vero arriva dal pannello di Google.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit.
	 * @param array $cfg     Configurazione.
	 * @return array Per gruppo: 'quanti', 'token_in', 'token_out', 'costo'.
	 */
	public static function stima( Db $db, $auditId, array $cfg, array $opzioni = array() ) {
		$prezzi = $cfg['ai']['prezzo_per_milione'] ?? array( 'input' => 0, 'output' => 0 );

		// Le parole dei contenuti da lavorare: è da lì che dipende tutto.
		$parole = static function ( $categorie ) use ( $db, $auditId ) {
			$segnaposto = implode( ',', array_fill( 0, count( $categorie ), '?' ) );

			$riga = $db->one(
				"SELECT COUNT(*) n, COALESCE(SUM(d.parole), 0) parole FROM triage t
				 JOIN documento d ON d.id = t.documento_id
				 WHERE t.audit_id = ? AND t.categoria IN ($segnaposto)",
				array_merge( array( $auditId ), $categorie )
			);

			return array( (int) ( $riga['n'] ?? 0 ), (int) ( $riga['parole'] ?? 0 ) );
		};

		list( $quanti_bozze, $parole_bozze ) = $parole( array( 'riscrivere' ) );
		list( $quanti_fusioni )              = $parole( array( 'accorpare' ) );

		// «Correggi tutto» lavora su chi ha un problema aperto, non sulla
		// categoria del triage: il costo mostrato deve essere quello di
		// quel lavoro li, altrimenti il pulsante promette una cifra e ne
		// spende un altra.
		if ( ! empty( $opzioni['tutto_larchivio'] ) ) {
			$tutti        = Rewriter::daCorreggere( $db, $auditId );
			$quanti_bozze = count( $tutti );
			$parole_bozze = array_sum( array_map( static fn( $r ) => (int) $r['parole'], $tutti ) );
		}

		$immagini = (int) $db->one(
			"SELECT COUNT(*) n FROM documento WHERE audit_id = ? AND tipo = 'post' AND ha_thumbnail = 0",
			array( $auditId )
		)['n'];

		// Una parola italiana sta in circa 1,33 token; l articolo riscritto
		// cresce di circa un quarto, e i modelli 2.5 spendono in ragionamento
		// più o meno il 60% di quello che scrivono.
		$in_bozze  = $parole_bozze * 1.33 + $quanti_bozze * 900;
		$out_bozze = ( $parole_bozze * 1.33 * 1.25 + $quanti_bozze * 300 ) * 1.6;

		$medie     = $quanti_bozze ? $parole_bozze / $quanti_bozze : 600;
		$in_fus    = $quanti_fusioni * ( $medie * 3 * 1.33 + 900 );
		$out_fus   = $quanti_fusioni * ( 1800 * 1.33 + 300 ) * 1.6;

		$costo = static function ( $in, $out ) use ( $prezzi ) {
			return $in / 1000000 * (float) $prezzi['input'] + $out / 1000000 * (float) $prezzi['output'];
		};

		return array(
			'bozza'    => array(
				'quanti'    => $quanti_bozze,
				'token_in'  => (int) $in_bozze,
				'token_out' => (int) $out_bozze,
				'costo'     => $costo( $in_bozze, $out_bozze ),
			),
			'accorpa'  => array(
				'quanti'    => $quanti_fusioni,
				'token_in'  => (int) $in_fus,
				'token_out' => (int) $out_fus,
				'costo'     => $costo( $in_fus, $out_fus ),
			),
			// Le immagini si pagano a immagine e non a token: qui il prezzo
			// dipende dal piano, e inventarlo sarebbe peggio che non dirlo.
			'immagine' => array(
				'quanti'    => $immagini,
				'token_in'  => 0,
				'token_out' => 0,
				'costo'     => null,
			),
			// Il totale, con lo stesso nome che usa Rewriter::stima(). Le due
			// funzioni si chiamano tutte e due «stima» e rispondevano con
			// forme diverse: chi leggeva 'costo_stimato' da questa otteneva
			// niente, e con un «?? 0» in mezzo si vedeva «0,00 €» accanto a
			// centosessantaquattro riscritture. Una cifra sbagliata detta con
			// sicurezza e peggio di nessuna cifra.
			'articoli'      => $quanti_bozze,
			'costo_stimato' => round( $costo( $in_bozze, $out_bozze ) + $costo( $in_fus, $out_fus ), 2 ),
		);
	}

	/**
	 * Costruisce la coda a partire dal piano dell audit.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit.
	 * @param array $cfg     Configurazione.
	 * @param array $opzioni 'immagini', 'pubblica', 'cestina'.
	 * @return array Riepilogo di quante operazioni per tipo.
	 */
	public static function prepara( Db $db, $auditId, array $cfg, array $opzioni = array() ) {
		// Un piano nuovo sostituisce quello vecchio per intero: lasciare le righe
		// dei giri precedenti falserebbe conteggi e percentuale di avanzamento.
		$db->run( 'DELETE FROM coda WHERE audit_id = ?', array( $auditId ) );

		$ora     = date( 'Y-m-d H:i:s' );
		$ordine  = 0;
		$righe   = array();
		$gemini  = new Gemini( $cfg['ai'] );
		$ai_ok   = $gemini->pronto();

		// Senza indicazioni si fa tutto come prima: le chiamate già scritte
		// altrove non devono cambiare comportamento.
		$includi = isset( $opzioni['includi'] ) ? (array) $opzioni['includi'] : array_keys( self::GRUPPI );
		$vuole   = static function ( $gruppo ) use ( $includi ) {
			return in_array( $gruppo, $includi, true );
		};

		$aggiungi = static function ( $tipo, $riferimento, $etichetta ) use ( &$righe, &$ordine, $auditId, $ora ) {
			$righe[] = array(
				'audit_id'    => $auditId,
				'ordine'      => ++$ordine,
				'tipo'        => $tipo,
				'riferimento' => (string) $riferimento,
				'etichetta'   => $etichetta,
				'origine'     => 'audit',
				'stato'       => self::ATTESA,
				'messaggio'   => '',
				'creato_il'   => $ora,
				'eseguito_il' => '',
			);
		};

		// 1. I dati aziendali per primi: schema LocalBusiness, footer e llms.txt
		// dipendono da questi, e il plugin da solo non li conosce.
		if ( $vuole( 'config' ) ) {
			$aggiungi( 'config', '', 'Invio dei dati aziendali al sito' );
		}

		// 2. Meta degli articoli, a blocchi: la scrittura di centinaia di contenuti
		// in una sola richiesta supererebbe i limiti di molti hosting.
		$quanti_articoli = self::metaDaCambiare( $db, $auditId, 'post' );

		// Quaranta per volta invece di ottanta: su hosting condiviso un blocco
		// grande esaurisce il tempo di esecuzione e il sito chiude la risposta
		// a metà, facendo fallire l intero blocco.
		for ( $offset = 0; $vuole( 'meta' ) && $offset < $quanti_articoli; $offset += 40 ) {
			$fino = min( $quanti_articoli, $offset + 40 );
			$aggiungi( 'meta', $offset, sprintf( 'Meta degli articoli da %d a %d', $offset + 1, $fino ) );
		}

		// Le pagine sono poche e scritte a mano: si toccano solo se richiesto.
		if ( ! empty( $opzioni['pagine'] ) && $vuole( 'meta' ) ) {
			$quante_pagine = self::metaDaCambiare( $db, $auditId, 'page' );

			if ( $quante_pagine ) {
				$aggiungi( 'meta_pagine', 0, sprintf( 'Meta delle %d pagine', $quante_pagine ) );
			}
		}

		// 2. Redirect obbligatori e categorie.
		if ( $vuole( 'struttura' ) ) {
			$aggiungi( 'redirect', '', 'Redirect 301 dei contenuti rimossi o accorpati' );
			$aggiungi( 'categorie', '', 'Riassegnazione delle categorie fuori tema' );
		}

		// 3. Accorpamenti: prima le fusioni, poi le riscritture singole.
		if ( $ai_ok ) {
			foreach ( $vuole( 'accorpa' ) ? Rewriter::gruppi( $db, $auditId ) : array() as $gruppo ) {
				$aggiungi(
					'accorpa',
					$gruppo['vincitore']['doc_id'],
					'Fusione di ' . ( count( $gruppo['assorbiti'] ) + 1 ) . ' articoli in "' . Text::truncate( $gruppo['vincitore']['titolo'], 60 ) . '"'
				);
			}

			// Se Search Console è collegata, l ordine lo decidono i dati veri:
			// prima gli articoli che Google mostra già e su cui c è più da
			// guadagnare, poi tutti gli altri nell ordine editoriale.
			// «Tutto» vuol dire tutto: chi ha un problema che la riscrittura
			// sa chiudere, non solo chi il triage ha messo fra quelli da
			// rifare. Su un archivio curato la differenza e enorme - qui 104
			// contro 295 - e il pulsante prometteva di sistemare e lasciava
			// indietro i due terzi.
			$candidati = array();

			if ( $vuole( 'bozza' ) ) {
				$candidati = empty( $opzioni['tutto_larchivio'] )
					? Rewriter::candidati( $db, $auditId, array() )
					: Rewriter::daCorreggere( $db, $auditId );
			}
			$priorita  = Prestazioni::prioritaPerUrl( $db, Prestazioni::chiaveSito( $cfg ) );

			if ( $priorita ) {
				$candidati = self::ordinaPerPriorita( $candidati, $priorita );
			}

			foreach ( $candidati as $articolo ) {
				$segnale = $priorita[ Prestazioni::chiaveUrl( $articolo['url'] ?? '' ) ] ?? null;

				$aggiungi(
					'bozza',
					$articolo['doc_id'],
					'Riscrittura di "' . Text::truncate( $articolo['titolo'], 60 ) . '"'
						. ( $segnale ? ' — ' . $segnale['titolo'] . ' (' . (int) $segnale['impression'] . ' impression)' : '' )
				);
			}

			if ( ! empty( $opzioni['immagini'] ) && $vuole( 'immagine' ) ) {
				foreach ( Immagini::candidati( $db, $auditId, array() ) as $documento ) {
					$aggiungi( 'immagine', $documento['id'], 'Immagine per "' . Text::truncate( $documento['titolo'], 60 ) . '"' );
				}
			}
		}

		// 3-bis. Le immagini pesanti: non costa token, e si ferma da sola al
		// limite di tempo, quindi si mettono alcuni giri in fila.
		if ( $vuole( 'comprimi' ) ) {
			for ( $giro = 0; $giro < 12; $giro++ ) {
				$aggiungi( 'comprimi', $giro, 'Ricompressione delle immagini pesanti, giro ' . ( $giro + 1 ) );
			}
		}

		// 4. Pubblicazione delle riscritture dentro gli articoli originali.
		if ( ! empty( $opzioni['pubblica'] ) ) {
			foreach ( $righe as $riga ) {
				if ( in_array( $riga['tipo'], array( 'bozza', 'accorpa' ), true ) ) {
					$aggiungi( 'applica_bozza', $riga['riferimento'], 'Pubblicazione: ' . $riga['etichetta'] );
				}
			}
		}

		// 5. Cestino dei contenuti da eliminare, sempre dopo i redirect.
		if ( ! empty( $opzioni['cestina'] ) ) {
			$da_eliminare = $db->all(
				"SELECT d.wp_id FROM triage t JOIN documento d ON d.id = t.documento_id
				 WHERE t.audit_id = ? AND t.categoria = 'eliminare'",
				array( $auditId )
			);

			if ( $da_eliminare ) {
				$aggiungi( 'cestina', implode( ',', array_column( $da_eliminare, 'wp_id' ) ), count( $da_eliminare ) . ' contenuti da eliminare spostati nel cestino' );
			}
		}

		$db->insertMany( 'coda', $righe );

		$per_tipo = array();

		foreach ( $righe as $riga ) {
			$per_tipo[ $riga['tipo'] ] = ( $per_tipo[ $riga['tipo'] ] ?? 0 ) + 1;
		}

		return array(
			'totale'   => count( $righe ),
			'per_tipo' => $per_tipo,
			'ai'       => $ai_ok,
		);
	}

	/**
	 * Mette davanti gli articoli per cui Google segnala un guadagno possibile.
	 *
	 * L ordine editoriale resta quello di partenza: cambia solo chi ha un
	 * segnale, e il confronto è stabile per non rimescolare il resto.
	 *
	 * @param array[] $candidati Articoli da riscrivere.
	 * @param array   $priorita  Segnali per indirizzo.
	 * @return array[]
	 */
	private static function ordinaPerPriorita( array $candidati, array $priorita ) {
		$peso = array();

		foreach ( $candidati as $i => $articolo ) {
			$chiave    = Prestazioni::chiaveUrl( $articolo['url'] ?? '' );
			$peso[ $i ] = array( (int) ( $priorita[ $chiave ]['priorita'] ?? 0 ), $i );
		}

		$indici = array_keys( $peso );

		usort(
			$indici,
			static function ( $a, $b ) use ( $peso ) {
				return ( $peso[ $b ][0] <=> $peso[ $a ][0] ) ?: ( $peso[ $a ][1] <=> $peso[ $b ][1] );
			}
		);

		$ordinati = array();

		foreach ( $indici as $i ) {
			$ordinati[] = $candidati[ $i ];
		}

		return $ordinati;
	}

	/**
	 * Stato della coda.
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Audit.
	 * @return array
	 */
	public static function stato( Db $db, $auditId ) {
		$conteggi = array( self::ATTESA => 0, self::FATTO => 0, self::ERRORE => 0, self::SALTATO => 0 );

		foreach ( $db->all( 'SELECT stato, COUNT(*) n FROM coda WHERE audit_id = ? GROUP BY stato', array( $auditId ) ) as $riga ) {
			$conteggi[ $riga['stato'] ] = (int) $riga['n'];
		}

		$totale = array_sum( $conteggi );

		// La domanda che si fa chi guarda questa pagina non e «quante
		// operazioni» ma «le riscritture sono andate sul sito o no?».
		$pubblicate = array( 'fatte' => 0, 'non_fatte' => 0 );

		foreach ( $db->all(
			"SELECT stato, COUNT(*) n FROM coda WHERE audit_id = ? AND tipo = 'applica_bozza' GROUP BY stato",
			array( $auditId )
		) as $riga ) {
			if ( self::FATTO === $riga['stato'] ) {
				$pubblicate['fatte'] += (int) $riga['n'];
			} elseif ( self::ATTESA !== $riga['stato'] ) {
				$pubblicate['non_fatte'] += (int) $riga['n'];
			}
		}

		return array(
			'totale'     => $totale,
			'attesa'     => $conteggi[ self::ATTESA ],
			'fatto'      => $conteggi[ self::FATTO ],
			'errore'     => $conteggi[ self::ERRORE ],
			'saltato'    => $conteggi[ self::SALTATO ],
			'completate' => $totale - $conteggi[ self::ATTESA ],
			'percentuale' => $totale ? (int) round( ( $totale - $conteggi[ self::ATTESA ] ) / $totale * 100 ) : 0,
			'pubblicate' => $pubblicate,
		);
	}

	/**
	 * Esegue le operazioni in attesa finché il tempo lo consente.
	 *
	 * @param Db    $db          Database.
	 * @param int   $auditId     Audit.
	 * @param array $cfg         Configurazione.
	 * @param int   $secondi_max Tetto di tempo per questo giro.
	 * @return array Operazioni eseguite e stato della coda.
	 */
	public static function esegui( Db $db, $auditId, array $cfg, $secondi_max = 60 ) {
		$scadenza = time() + max( 5, (int) $secondi_max );
		$gemini   = new Gemini( $cfg['ai'] );
		$ponte    = new WordPress( $cfg['wordpress'] );
		$eseguite = array();

        // Una operazione alla volta, così un interruzione lascia la coda coerente.
		while ( time() < $scadenza ) {
			$compito = $db->one(
				'SELECT * FROM coda WHERE audit_id = ? AND stato = ? ORDER BY ordine ASC LIMIT 1',
				array( $auditId, self::ATTESA )
			);

			if ( ! $compito ) {
				break;
			}

			try {
				$messaggio = self::eseguiCompito( $db, $auditId, $cfg, $compito, $gemini, $ponte );
				$stato     = self::FATTO;
			} catch ( SaltaCompito $e ) {
				$messaggio = $e->getMessage();
				$stato     = self::SALTATO;
			} catch ( Throwable $e ) {
				$messaggio = $e->getMessage();
				$stato     = self::ERRORE;
			}

			// Se il problema è la configurazione, insistere significa solo
			// bruciare tutta la coda con lo stesso errore: meglio fermarsi.
			$bloccante = self::ERRORE === $stato && preg_match(
				'/chiave api|api key|token|quota|fatturazione|billing|non raggiungibile|rotta non trovata/i',
				(string) $messaggio
			);

			$db->run(
				'UPDATE coda SET stato = ?, messaggio = ?, eseguito_il = ? WHERE id = ?',
				array( $stato, mb_substr( (string) $messaggio, 0, 500 ), date( 'Y-m-d H:i:s' ), $compito['id'] )
			);

			$eseguite[] = array(
				'tipo'      => $compito['tipo'],
				'etichetta' => $compito['etichetta'],
				'stato'     => $stato,
				'messaggio' => $messaggio,
			);

			if ( ! empty( $bloccante ) ) {
				$db->run(
					'UPDATE coda SET stato = ?, messaggio = ? WHERE audit_id = ? AND stato = ? AND tipo = ?',
					array( self::ATTESA, '', $auditId, self::ATTESA, $compito['tipo'] )
				);

				$eseguite[] = array(
					'tipo'      => 'stop',
					'etichetta' => 'Esecuzione interrotta',
					'stato'     => self::ERRORE,
					'messaggio' => 'Le operazioni restanti sono ancora in coda: risolvi il problema qui sopra e riavvia.',
				);

				break;
			}
		}

		return array(
			'eseguite' => $eseguite,
			'stato'    => self::stato( $db, $auditId ),
			'consumo'  => $gemini->consumo(),
		);
	}

	/**
	 * Esegue una singola operazione.
	 *
	 * @param Db        $db      Database.
	 * @param int       $auditId Audit.
	 * @param array     $cfg     Configurazione.
	 * @param array     $compito Riga della coda.
	 * @param Gemini    $gemini  Client del modello.
	 * @param WordPress $ponte   Collegamento al sito.
	 * @return string Messaggio di esito.
	 * @throws SaltaCompito Se l operazione non è applicabile.
	 */
	private static function eseguiCompito( Db $db, $auditId, array $cfg, array $compito, Gemini $gemini, WordPress $ponte ) {
		$serve_sito = in_array( $compito['tipo'], array( 'config', 'meta', 'meta_pagine', 'meta_mirata', 'redirect', 'categorie', 'applica_bozza', 'cestina', 'comprimi' ), true );

		if ( $serve_sito && ! $ponte->pronto() ) {
			throw new SaltaCompito( 'Collegamento a WordPress non configurato: indirizzo e token nelle Impostazioni.' );
		}

		if ( in_array( $compito['tipo'], array( 'bozza', 'accorpa', 'immagine' ), true ) && ! $gemini->pronto() ) {
			throw new SaltaCompito( 'Chiave API Gemini mancante: inseriscila nelle Impostazioni.' );
		}

		switch ( $compito['tipo'] ) {

			case 'config':
				$risposta = $ponte->inviaConfigurazione( $cfg );
				$mancanti = (array) ( $risposta['mancanti'] ?? array() );

				return $mancanti
					? 'inviati, ma restano da compilare: ' . implode( ', ', $mancanti )
					: 'telefono, partita IVA, indirizzo e scheda Google Business ora sul sito';

			case 'meta':
			case 'meta_pagine':
				$tipo_contenuto = 'meta_pagine' === $compito['tipo'] ? 'page' : 'post';

				$righe = $db->all(
					'SELECT d.wp_id AS id, m.title_nuovo AS title, m.description_nuova AS description,
							m.excerpt_nuovo AS excerpt, d.focus_keyword AS focus
					 FROM meta_piano m JOIN documento d ON d.id = m.documento_id
					 WHERE m.audit_id = ? AND d.tipo = ?' . self::soloDaCambiare() . '
					 ORDER BY m.id ASC LIMIT 40 OFFSET ' . (int) $compito['riferimento'],
					array( $auditId, $tipo_contenuto )
				);

				if ( ! $righe ) {
					throw new SaltaCompito( 'le meta sul sito sono già quelle previste' );
				}

				$esito = $ponte->inviaMeta( $righe, false );

				// Il pilota passa di qui centinaia di volte: se non segna
				// quello che ha scritto, al giro dopo riparte dagli stessi
				// contenuti e non finisce mai.
				Applicato::meta( $db, $auditId, $righe );

				return sprintf(
					'%d %s aggiornate',
					(int) ( $esito['aggiornati'] ?? 0 ),
					'page' === $tipo_contenuto ? 'pagine' : 'articoli'
				);

			case 'meta_mirata':
				$documento = $db->one( 'SELECT * FROM documento WHERE id = ?', array( (int) $compito['riferimento'] ) );

				if ( ! $documento ) {
					throw new SaltaCompito( 'Contenuto non più presente.' );
				}

				// La parola chiave non è più quella indovinata leggendo il testo:
				// è la ricerca per cui Google mostra davvero questa pagina.
				$doc = array(
					'titolo'          => (string) $documento['titolo'],
					'slug'            => (string) $documento['slug'],
					'testo'           => (string) $documento['testo'],
					'seo_title'       => (string) $documento['seo_title'],
					'seo_desc'        => (string) $documento['seo_description'],
					'primo_paragrafo' => '',
					'estratto'        => '',
					'focus'           => (string) ( $compito['dettaglio'] ?: $documento['focus_keyword'] ),
				);

				list( $title )       = Meta::title( $doc, $cfg );
				list( $description ) = Meta::description( $doc, $cfg );

				$esito = $ponte->inviaMeta(
					array(
						array(
							'id'          => $documento['wp_id'],
							'title'       => $title,
							'description' => $description,
							'excerpt'     => '',
							'focus'       => $doc['focus'],
						),
					),
					false
				);

				if ( empty( $esito['aggiornati'] ) ) {
					throw new \RuntimeException( 'il sito non ha accettato le meta' );
				}

				// Il piano resta allineato a quello che c è davvero sul sito.
				$db->run(
					'UPDATE meta_piano SET title_nuovo = ?, description_nuova = ? WHERE audit_id = ? AND documento_id = ?',
					array( $title, $description, $auditId, (int) $documento['id'] )
				);

				Applicato::meta(
					$db,
					$auditId,
					array( array( 'id' => $documento['wp_id'], 'title' => $title, 'description' => $description ) )
				);

				return 'title e description riscritti su "' . $doc['focus'] . '"';

			case 'redirect':
				$righe = array();

				foreach ( $db->all(
					"SELECT t.redirect_a, d.percorso FROM triage t JOIN documento d ON d.id = t.documento_id
					 WHERE t.audit_id = ? AND t.redirect_a <> ''",
					array( $auditId )
				) as $riga ) {
					$righe[] = array( 'da' => $riga['percorso'], 'a' => $riga['redirect_a'] );
				}

				if ( ! $righe ) {
					throw new SaltaCompito( 'Nessun redirect necessario.' );
				}

				$esito = $ponte->inviaRedirect( $righe );

				// Un vecchio indirizzo che adesso rimanda da qualche parte non
				// e piu un indirizzo perso.
				Applicato::redirect( $db, $auditId, array_column( $righe, 'da' ) );

				return sprintf( '%d redirect attivi', (int) ( $esito['redirect'] ?? 0 ) );

			case 'categorie':
				$assegnazioni = array();

				foreach ( $db->all(
					"SELECT o.riferimento, o.dettaglio FROM occorrenza o JOIN rilievo r ON r.id = o.rilievo_id
					 WHERE r.audit_id = ? AND r.regola = 'TAX-03'
					   AND COALESCE( o.applicato, 0 ) = 0",
					array( $auditId )
				) as $riga ) {
					if ( ! preg_match( '/suggerita "([^"]+)"/', $riga['dettaglio'], $m ) ) {
						continue;
					}

					$documento = $db->one( 'SELECT wp_id FROM documento WHERE audit_id = ? AND percorso = ?', array( $auditId, $riga['riferimento'] ) );

					if ( $documento ) {
						$assegnazioni[] = array(
							'id'        => (int) $documento['wp_id'],
							'categoria' => $m[1],
							// Serve solo qui, per sapere che cosa chiudere
							// dopo: al sito si mandano id e categoria.
							'percorso'  => (string) $riga['riferimento'],
						);
					}
				}

				if ( ! $assegnazioni ) {
					throw new SaltaCompito( 'Nessuna categoria da correggere.' );
				}

				// Al sito vanno solo id e categoria: il percorso serve qui.
				$esito = $ponte->inviaCategorie(
					array_map(
						static function ( $a ) {
							return array( 'id' => $a['id'], 'categoria' => $a['categoria'] );
						},
						$assegnazioni
					)
				);

				if ( ! empty( $esito['assegnate'] ) ) {
					// Senza questo il pilota ricategorizza gli stessi articoli
					// a ogni giro: il compito legge l analisi, e nell analisi
					// non era cambiato niente.
					Applicato::chiudi(
						$db,
						$auditId,
						array( 'TAX-03' ),
						array_column( $assegnazioni, 'percorso' )
					);
				}

				return sprintf( '%d articoli ricategorizzati', (int) ( $esito['assegnate'] ?? 0 ) );

			case 'accorpa':
				if ( self::bozzaGiaPresente( $db, $auditId, (int) $compito['riferimento'] ) ) {
					throw new SaltaCompito( 'fusione già generata in precedenza' );
				}

				$esito = Rewriter::consolida( $db, $gemini, $auditId, $cfg, array( 'solo_documento' => (int) $compito['riferimento'], 'limite' => 1 ) );

				if ( empty( $esito['generate'] ) ) {
					throw new \RuntimeException( $esito['errori'][0] ?? 'il modello non ha prodotto la fusione' );
				}

				self::inviaBozzaSeCollegato( $db, $auditId, (int) $compito['riferimento'], $ponte );

				return 'articolo unificato pronto';

			case 'bozza':
				if ( self::bozzaGiaPresente( $db, $auditId, (int) $compito['riferimento'] ) ) {
					throw new SaltaCompito( 'bozza già generata in precedenza' );
				}

				$esito = Rewriter::esegui( $db, $gemini, $auditId, $cfg, array( 'solo_documento' => (int) $compito['riferimento'], 'limite' => 1 ) );

				if ( empty( $esito['generate'] ) ) {
					throw new \RuntimeException( $esito['errori'][0] ?? 'il modello non ha prodotto la bozza' );
				}

				self::inviaBozzaSeCollegato( $db, $auditId, (int) $compito['riferimento'], $ponte );

				return 'bozza pronta';

			case 'immagine':
				$esito = Immagini::esegui(
					$db,
					$gemini,
					$auditId,
					$cfg,
					array( 'solo_documento' => (int) $compito['riferimento'], 'limite' => 1, 'invia' => $ponte->pronto() ),
					$ponte
				);

				if ( empty( $esito['generate'] ) ) {
					throw new \RuntimeException( $esito['errori'][0] ?? 'immagine non generata' );
				}

				return $esito['inviate'] ? 'immagine generata e caricata sul sito' : 'immagine generata';

			case 'applica_bozza':
				// Si scrive sull articolo che esiste gia, come fa il pulsante
				// «Sovrascrivi».
				//
				// Prima si chiamava applicaBozza(), che cerca su WordPress una
				// bozza creata da inviaBozza(). Da quando le riscritture non
				// passano piu da una bozza di WordPress - creava un doppione
				// in bacheca da applicare a mano - quella bozza non esiste, e
				// il pilota falliva su ogni riscrittura con «nessuna bozza
				// collegata a questo articolo».
				$riga = $db->one(
					"SELECT b.*, d.wp_id FROM bozza b
					 JOIN documento d ON d.id = b.documento_id
					 WHERE b.audit_id = ? AND b.documento_id = ? AND b.stato = 'ok'
					 ORDER BY b.id DESC LIMIT 1",
					array( (int) $compito['audit_id'], (int) $compito['riferimento'] )
				);

				if ( ! $riga ) {
					// Non e un guasto di questo compito: e la conseguenza di
					// una riscrittura che non e stata prodotta. Detto com era
					// prima sembrava che la pubblicazione fosse rotta.
					throw new SaltaCompito( 'niente da pubblicare: la riscrittura di questo articolo non è stata prodotta, il motivo è nella riga «Riscrittura» dello stesso articolo' );
				}

				// Il testo che parte e quello ripulito dai dati strutturati:
				// il modello a volte li scrive nel corpo, e in pagina si
				// leggono come un muro di parentesi graffe.
				$corpo = Html::senzaDatiStrutturati( (string) $riga['corpo_html'] );

				$ponte->sovrascrivi(
					(int) $riga['wp_id'],
					array(
						'titolo'           => (string) $riga['titolo'],
						'contenuto'        => $corpo,
						'estratto'         => (string) $riga['in_breve'],
						'in_breve'         => (string) $riga['in_breve'],
						'faq'              => json_decode( (string) $riga['faq'], true ) ?: array(),
						'meta_title'       => (string) $riga['meta_title'],
						'meta_description' => (string) $riga['meta_description'],
					)
				);

				$db->run(
					'UPDATE bozza SET inviata_il = ?, corpo_html = ? WHERE id = ?',
					array( date( 'Y-m-d H:i:s' ), $corpo, (int) $riga['id'] )
				);

				Applicato::contenuto(
					$db,
					$auditId,
					(int) $compito['riferimento'],
					$corpo,
					0 === strpos( (string) $riga['note'], 'Accorpa ' )
				);

				return 'riscrittura scritta sull articolo originale';

			case 'comprimi':
				$esito = \SeoGeo\Media\Compressione::esegui(
					$ponte,
					array(
						'lato'        => (int) ( $cfg['ai']['immagine_lato_max'] ?? 1200 ),
						'qualita'     => (int) ( $cfg['ai']['immagine_qualita'] ?? 82 ),
						'peso_max'    => (int) ( $cfg['ai']['immagine_peso_max'] ?? 190000 ),
						'secondi_max' => 40,
					)
				);

				Applicato::immagini( $db, $auditId, (array) ( $esito['riuscite'] ?? array() ) );

				if ( empty( $esito['compresse'] ) && empty( $esito['gia_fatte'] ) ) {
					throw new SaltaCompito( 'nessuna immagine da ricomprimere' );
				}

				return sprintf(
					'%d immagini ricompresse, ne restano %d',
					(int) $esito['compresse'],
					(int) $esito['restanti']
				);

			case 'cestina':
				$ids = array_filter( explode( ',', (string) $compito['riferimento'] ) );

				if ( ! $ids ) {
					throw new SaltaCompito( 'Nessun contenuto da cestinare.' );
				}

				$esito = $ponte->cestina( $ids );

				return sprintf( '%d contenuti nel cestino, recuperabili da WordPress', (int) ( $esito['cestinati'] ?? 0 ) );

			default:
				throw new SaltaCompito( 'Operazione sconosciuta: ' . $compito['tipo'] );
		}
	}

	/**
	 * Esiste già una bozza pronta per questo documento?
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Audit.
	 * @param int $docId   Documento.
	 * @return bool
	 */
	private static function bozzaGiaPresente( Db $db, $auditId, $docId ) {
		$riga = $db->one(
			"SELECT id FROM bozza WHERE audit_id = ? AND documento_id = ? AND stato = 'ok'",
			array( $auditId, $docId )
		);

		return (bool) $riga;
	}

	/**
	 * Manda la bozza appena creata al sito, se il collegamento è attivo.
	 *
	 * @param Db        $db      Database.
	 * @param int       $auditId Audit.
	 * @param int       $docId   Documento.
	 * @param WordPress $ponte   Collegamento.
	 * @return void
	 */
	private static function inviaBozzaSeCollegato( Db $db, $auditId, $docId, WordPress $ponte ) {
		if ( ! $ponte->pronto() ) {
			return;
		}

		$bozza = $db->one(
			"SELECT b.*, d.wp_id FROM bozza b JOIN documento d ON d.id = b.documento_id
			 WHERE b.audit_id = ? AND b.documento_id = ? AND b.stato = 'ok'",
			array( $auditId, $docId )
		);

		if ( ! $bozza ) {
			return;
		}

		$ponte->inviaBozza(
			array(
				'id'               => (int) $bozza['wp_id'],
				'titolo'           => $bozza['titolo'],
				'corpo_html'       => $bozza['corpo_html'],
				'in_breve'         => $bozza['in_breve'],
				'meta_title'       => $bozza['meta_title'],
				'meta_description' => $bozza['meta_description'],
				'faq'              => json_decode( (string) $bozza['faq'], true ) ?: array(),
			)
		);
	}
}

/**
 * Operazione non applicabile: non è un errore, va solo saltata.
 */
class SaltaCompito extends \RuntimeException {}
