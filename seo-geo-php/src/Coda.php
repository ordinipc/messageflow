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
		'meta'          => 'Meta ottimizzate',
		'redirect'      => 'Redirect 301',
		'categorie'     => 'Categorie',
		'accorpa'       => 'Accorpamento',
		'bozza'         => 'Riscrittura',
		'immagine'      => 'Immagine in evidenza',
		'applica_bozza' => 'Pubblicazione della riscrittura',
		'cestina'       => 'Contenuti nel cestino',
	);

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

		$aggiungi = static function ( $tipo, $riferimento, $etichetta ) use ( &$righe, &$ordine, $auditId, $ora ) {
			$righe[] = array(
				'audit_id'    => $auditId,
				'ordine'      => ++$ordine,
				'tipo'        => $tipo,
				'riferimento' => (string) $riferimento,
				'etichetta'   => $etichetta,
				'stato'       => self::ATTESA,
				'messaggio'   => '',
				'creato_il'   => $ora,
				'eseguito_il' => '',
			);
		};

		// 1. I dati aziendali per primi: schema LocalBusiness, footer e llms.txt
		// dipendono da questi, e il plugin da solo non li conosce.
		$aggiungi( 'config', '', 'Invio dei dati aziendali al sito' );

		// 2. Meta, a blocchi: la scrittura di 326 contenuti in una sola richiesta
		// supererebbe i limiti di molti hosting.
		$quante_meta = (int) $db->one( 'SELECT COUNT(*) n FROM meta_piano WHERE audit_id = ?', array( $auditId ) )['n'];

		for ( $offset = 0; $offset < $quante_meta; $offset += 80 ) {
			$fino = min( $quante_meta, $offset + 80 );
			$aggiungi( 'meta', $offset, sprintf( 'Meta dei contenuti da %d a %d', $offset + 1, $fino ) );
		}

		// 2. Redirect obbligatori e categorie.
		$aggiungi( 'redirect', '', 'Redirect 301 dei contenuti rimossi o accorpati' );
		$aggiungi( 'categorie', '', 'Riassegnazione delle categorie fuori tema' );

		// 3. Accorpamenti: prima le fusioni, poi le riscritture singole.
		if ( $ai_ok ) {
			foreach ( Rewriter::gruppi( $db, $auditId ) as $gruppo ) {
				$aggiungi(
					'accorpa',
					$gruppo['vincitore']['doc_id'],
					'Fusione di ' . ( count( $gruppo['assorbiti'] ) + 1 ) . ' articoli in "' . Text::truncate( $gruppo['vincitore']['titolo'], 60 ) . '"'
				);
			}

			foreach ( Rewriter::candidati( $db, $auditId, array() ) as $articolo ) {
				$aggiungi( 'bozza', $articolo['doc_id'], 'Riscrittura di "' . Text::truncate( $articolo['titolo'], 60 ) . '"' );
			}

			if ( ! empty( $opzioni['immagini'] ) ) {
				foreach ( Immagini::candidati( $db, $auditId, array() ) as $documento ) {
					$aggiungi( 'immagine', $documento['id'], 'Immagine per "' . Text::truncate( $documento['titolo'], 60 ) . '"' );
				}
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

		return array(
			'totale'     => $totale,
			'attesa'     => $conteggi[ self::ATTESA ],
			'fatto'      => $conteggi[ self::FATTO ],
			'errore'     => $conteggi[ self::ERRORE ],
			'saltato'    => $conteggi[ self::SALTATO ],
			'completate' => $totale - $conteggi[ self::ATTESA ],
			'percentuale' => $totale ? (int) round( ( $totale - $conteggi[ self::ATTESA ] ) / $totale * 100 ) : 0,
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
		$serve_sito = in_array( $compito['tipo'], array( 'config', 'meta', 'redirect', 'categorie', 'applica_bozza', 'cestina' ), true );

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
				$righe = $db->all(
					'SELECT d.wp_id AS id, m.title_nuovo AS title, m.description_nuova AS description,
							m.excerpt_nuovo AS excerpt, d.focus_keyword AS focus
					 FROM meta_piano m JOIN documento d ON d.id = m.documento_id
					 WHERE m.audit_id = ? ORDER BY m.id ASC LIMIT 80 OFFSET ' . (int) $compito['riferimento'],
					array( $auditId )
				);

				$esito = $ponte->inviaMeta( $righe, false );

				return sprintf( '%d contenuti aggiornati', (int) ( $esito['aggiornati'] ?? 0 ) );

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

				return sprintf( '%d redirect attivi', (int) ( $esito['redirect'] ?? 0 ) );

			case 'categorie':
				$assegnazioni = array();

				foreach ( $db->all(
					"SELECT o.riferimento, o.dettaglio FROM occorrenza o JOIN rilievo r ON r.id = o.rilievo_id
					 WHERE r.audit_id = ? AND r.regola = 'TAX-03'",
					array( $auditId )
				) as $riga ) {
					if ( ! preg_match( '/suggerita "([^"]+)"/', $riga['dettaglio'], $m ) ) {
						continue;
					}

					$documento = $db->one( 'SELECT wp_id FROM documento WHERE audit_id = ? AND percorso = ?', array( $auditId, $riga['riferimento'] ) );

					if ( $documento ) {
						$assegnazioni[] = array( 'id' => (int) $documento['wp_id'], 'categoria' => $m[1] );
					}
				}

				if ( ! $assegnazioni ) {
					throw new SaltaCompito( 'Nessuna categoria da correggere.' );
				}

				$esito = $ponte->inviaCategorie( $assegnazioni );

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
				$documento = $db->one( 'SELECT wp_id FROM documento WHERE id = ?', array( (int) $compito['riferimento'] ) );

				if ( ! $documento ) {
					throw new SaltaCompito( 'Articolo non trovato.' );
				}

				$ponte->applicaBozza( (int) $documento['wp_id'] );

				return 'riscrittura pubblicata nell articolo originale';

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
