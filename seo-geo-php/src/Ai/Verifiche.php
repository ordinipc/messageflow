<?php
/**
 * Compilazione dei segnaposto [DA VERIFICARE: ...] con dati cercati online.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Ai;

use SeoGeo\Db;
use Throwable;

/**
 * Le bozze escono con dei buchi al posto di prezzi, tempi e numeri, perche
 * il modello ha l istruzione di non inventarli mai. Qui quei buchi si
 * riempiono cercando su Google, e la regola resta la stessa di prima: un
 * valore entra solo se una fonte vera lo sostiene, altrimenti il buco
 * rimane.
 *
 * Una cosa che il programma non puo fare e va detta a chi lo usa: i
 * segnaposto che chiedono i prezzi dell agenzia non li sa nessuno tranne
 * l agenzia. Per quelli la ricerca restituisce un dato di mercato, e viene
 * marcato come tale con la sua fonte, non spacciato per listino proprio.
 */
class Verifiche {

	/** Come sono fatti i segnaposto nelle bozze. */
	const SCHEMA = '/\[DA VERIFICARE:\s*([^\]]+)\]/u';

	/**
	 * Tutti i segnaposto presenti nelle bozze, raggruppati per etichetta.
	 *
	 * Raggruppare e il punto: su duecento bozze le etichette distinte sono
	 * poche decine, e una ricerca per etichetta basta per tutte.
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Audit.
	 * @return array Etichetta => array( 'quante' => n, 'bozze' => int[] ).
	 */
	public static function segnaposto( Db $db, $auditId ) {
		$trovati = array();

		foreach ( $db->all( "SELECT id, corpo_html, in_breve, meta_description FROM bozza WHERE audit_id = ? AND stato = 'ok'", array( $auditId ) ) as $bozza ) {
			$testo = (string) $bozza['corpo_html'] . ' ' . (string) $bozza['in_breve'] . ' ' . (string) $bozza['meta_description'];

			if ( ! preg_match_all( self::SCHEMA, $testo, $trovate ) ) {
				continue;
			}

			foreach ( $trovate[1] as $etichetta ) {
				$chiave = self::normalizza( $etichetta );

				if ( ! isset( $trovati[ $chiave ] ) ) {
					$trovati[ $chiave ] = array( 'etichetta' => trim( $etichetta ), 'quante' => 0, 'bozze' => array() );
				}

				$trovati[ $chiave ]['quante']++;
				$trovati[ $chiave ]['bozze'][ (int) $bozza['id'] ] = true;
			}
		}

		foreach ( $trovati as $chiave => $dati ) {
			$trovati[ $chiave ]['bozze'] = array_keys( $dati['bozze'] );
		}

		// Prima le etichette che compaiono piu volte: sono quelle dove una
		// sola ricerca risolve piu articoli.
		uasort( $trovati, static fn( $a, $b ) => $b['quante'] <=> $a['quante'] );

		return $trovati;
	}

	/**
	 * Quanti segnaposto restano in una bozza.
	 *
	 * Serve prima di scrivere sul sito: un articolo pubblicato con dentro
	 * «[DA VERIFICARE: data di pubblicazione]» e peggio dell articolo di
	 * prima, e chi legge la pagina lo vede subito.
	 *
	 * @param array $bozza Riga bozza.
	 * @return string[] Etichette ancora da compilare.
	 */
	public static function restano( array $bozza ) {
		$testo = (string) ( $bozza['corpo_html'] ?? '' )
			. ' ' . (string) ( $bozza['in_breve'] ?? '' )
			. ' ' . (string) ( $bozza['meta_description'] ?? '' )
			. ' ' . (string) ( $bozza['titolo'] ?? '' )
			. ' ' . (string) ( $bozza['faq'] ?? '' );

		if ( ! preg_match_all( self::SCHEMA, $testo, $trovate ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'trim', $trovate[1] ) ) );
	}

	/**
	 * Le frasi che contengono un segnaposto, una per volta.
	 *
	 * Si isola la frase e non il paragrafo perche quello che si vuole
	 * cambiare e solo dove manca il dato: il resto del testo e gia stato
	 * scritto e riletto, e rifarlo vorrebbe dire rimetterci le mani.
	 *
	 * @param string $testo Testo o markup.
	 * @return string[] Frasi distinte, nell ordine in cui compaiono.
	 */
	public static function frasiCon( $testo ) {
		$testo = (string) $testo;
		$frasi = array();

		if ( ! preg_match_all( self::SCHEMA, $testo, $trovate, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		foreach ( $trovate[0] as $occorrenza ) {
			$inizio = self::inizioFrase( $testo, (int) $occorrenza[1] );
			$fine   = self::fineFrase( $testo, (int) $occorrenza[1] + strlen( $occorrenza[0] ) );
			$frase  = trim( substr( $testo, $inizio, $fine - $inizio ) );

			if ( '' !== $frase && ! in_array( $frase, $frasi, true ) ) {
				$frasi[] = $frase;
			}
		}

		return $frasi;
	}

	/**
	 * Dove comincia la frase che contiene questa posizione.
	 *
	 * @param string $testo   Testo.
	 * @param int    $dove    Posizione dentro la frase.
	 * @return int
	 */
	private static function inizioFrase( $testo, $dove ) {
		for ( $i = $dove; $i > 0; $i-- ) {
			$c = $testo[ $i - 1 ];

			// Un tag chiuso e un a capo sono confini certi quanto un punto:
			// la frase non attraversa un </p> o un <li>.
			if ( '>' === $c || "\n" === $c ) {
				return $i;
			}

			if ( in_array( $c, array( '.', '!', '?' ), true ) ) {
				return $i;
			}
		}

		return 0;
	}

	/**
	 * Dove finisce la frase che contiene questa posizione.
	 *
	 * @param string $testo Testo.
	 * @param int    $dove  Posizione da cui cercare la fine.
	 * @return int
	 */
	private static function fineFrase( $testo, $dove ) {
		$lunghezza = strlen( $testo );

		for ( $i = $dove; $i < $lunghezza; $i++ ) {
			$c = $testo[ $i ];

			if ( '<' === $c || "\n" === $c ) {
				return $i;
			}

			if ( in_array( $c, array( '.', '!', '?' ), true ) ) {
				return $i + 1;
			}
		}

		return $lunghezza;
	}

	/**
	 * Riscrive le frasi rimaste col buco, in modo che funzionino senza il
	 * dato che non si e trovato.
	 *
	 * E l ultimo passo, e serve: dopo la ricerca online qualche segnaposto
	 * resta sempre — ci sono dati che nessuna fonte pubblica ha. Senza questo
	 * quelle bozze restano bloccate per sempre, e l unica via d uscita e
	 * compilarle a mano.
	 *
	 * Non si inventa niente: la frase viene girata in modo da dire la stessa
	 * cosa senza la cifra. «Costa [DA VERIFICARE: prezzo]» diventa «Il costo
	 * dipende da quante pagine servono», non «Costa 1500 euro».
	 *
	 * @param Gemini $gemini Client.
	 * @param array  $frasi  Frasi da girare.
	 * @param array  $cfg    Configurazione.
	 * @return array<string,string> Frase originale => frase nuova. Manca la
	 *                              voce per quelle che non si sono sistemate.
	 */
	public static function riscriviFrasi( Gemini $gemini, array $frasi, array $cfg ) {
		$frasi = array_values( array_filter( array_map( 'trim', $frasi ) ) );

		if ( ! $frasi ) {
			return array();
		}

		$settore = (string) ( $cfg['azienda']['settore'] ?? 'agenzia di comunicazione e sviluppo web' );

		$istruzioni = "Sei un editor. Ti arrivano frasi di un articolo in cui manca un dato che nessuna "
			. "fonte ha saputo dare. Le giri in modo che funzionino senza quel dato.\n"
			. "Regole tassative:\n"
			. "- Non inventi MAI il dato mancante: niente numeri, prezzi, tempi, percentuali o date.\n"
			. "- Non lasci il segnaposto [DA VERIFICARE: ...] nella frase che restituisci.\n"
			. "- Dici la stessa cosa in modo qualitativo: «il costo dipende da quante pagine servono» "
			. "al posto di «costa X euro».\n"
			. "- Tieni i tag HTML che trovi nella frase, identici.\n"
			. "- Se togliendo il dato la frase non ha piu niente da dire, restituisci una stringa vuota: "
			. "verra tolta.\n"
			. "- Non cambi il tono ne la persona.";

		$elenco = '';

		foreach ( $frasi as $i => $frase ) {
			$elenco .= ( $i + 1 ) . '. ' . $frase . "\n";
		}

		$richiesta = "Contesto: articolo di un'azienda che si occupa di $settore.\n\n"
			. "Frasi da girare:\n" . $elenco . "\n"
			. "Rispondi SOLO con un oggetto JSON:\n"
			. '{"frasi": [{"numero": 1, "nuova": "la frase girata, o stringa vuota"}]}';

		try {
			$dati = $gemini->generaJson( $istruzioni, $richiesta, array( 'max_token' => 4000 ) );
		} catch ( Throwable $e ) {
			return array();
		}

		$fuori = array();

		foreach ( (array) ( $dati['frasi'] ?? array() ) as $voce ) {
			$numero = (int) ( $voce['numero'] ?? 0 );

			if ( $numero < 1 || $numero > count( $frasi ) ) {
				continue;
			}

			$nuova = trim( (string) ( $voce['nuova'] ?? '' ) );

			// Se il segnaposto e ancora li, il modello non ha fatto il
			// lavoro: tenere quella frase e peggio che lasciarla com era.
			if ( preg_match( self::SCHEMA, $nuova ) ) {
				continue;
			}

			$fuori[ $frasi[ $numero - 1 ] ] = $nuova;
		}

		return $fuori;
	}

	/**
	 * Le bozze che hanno ancora un buco, con i campi che servono a chiuderlo.
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Audit.
	 * @param int $quante  Massimo da riportare.
	 * @return array
	 */
	public static function bozzeAperte( Db $db, $auditId, $quante = 200 ) {
		$fuori = array();

		foreach ( $db->all( "SELECT id, titolo, corpo_html, in_breve, meta_description FROM bozza WHERE audit_id = ? AND stato = 'ok' ORDER BY id", array( $auditId ) ) as $bozza ) {
			if ( ! self::restano( $bozza ) ) {
				continue;
			}

			$fuori[] = $bozza;

			if ( count( $fuori ) >= (int) $quante ) {
				break;
			}
		}

		return $fuori;
	}

	/**
	 * Mette le frasi girate al posto di quelle col buco, in una bozza.
	 *
	 * @param Db    $db             Database.
	 * @param array $bozza          Riga bozza.
	 * @param array $sostituzioni   Frase vecchia => frase nuova.
	 * @return int Quante frasi sono state sistemate.
	 */
	public static function applicaFrasi( Db $db, array $bozza, array $sostituzioni ) {
		if ( ! $sostituzioni ) {
			return 0;
		}

		$sistemate = 0;
		$nuovi     = array();

		foreach ( array( 'corpo_html', 'in_breve', 'meta_description' ) as $campo ) {
			$testo   = (string) ( $bozza[ $campo ] ?? '' );
			$partito = $testo;

			foreach ( $sostituzioni as $vecchia => $nuova ) {
				if ( false === strpos( $testo, $vecchia ) ) {
					continue;
				}

				// Frase svuotata: si toglie insieme allo spazio che la
				// separava da quella prima, se no restano doppi spazi.
				$testo = str_replace(
					'' === $nuova ? ' ' . $vecchia : $vecchia,
					$nuova,
					$testo
				);

				if ( '' === $nuova ) {
					$testo = str_replace( $vecchia, '', $testo );
				}

				$sistemate++;
			}

			if ( $testo !== $partito ) {
				$nuovi[ $campo ] = preg_replace( '/[ \t]{2,}/', ' ', $testo );
			}
		}

		if ( ! $nuovi ) {
			return 0;
		}

		$pezzi  = array();
		$valori = array();

		foreach ( $nuovi as $campo => $valore ) {
			$pezzi[]  = $campo . ' = ?';
			$valori[] = $valore;
		}

		$valori[] = (int) $bozza['id'];

		$db->run( 'UPDATE bozza SET ' . implode( ', ', $pezzi ) . ' WHERE id = ?', $valori );

		return $sistemate;
	}

	/**
	 * Etichette diverse che chiedono la stessa cosa vanno insieme.
	 *
	 * @param string $etichetta Etichetta grezza.
	 * @return string
	 */
	public static function normalizza( $etichetta ) {
		$pulita = mb_strtolower( trim( (string) $etichetta ) );
		$pulita = preg_replace( '/[^\p{L}\p{N} ]+/u', ' ', $pulita );

		return trim( preg_replace( '/\s+/', ' ', (string) $pulita ) );
	}

	/**
	 * Cerca online il valore di un segnaposto.
	 *
	 * @param Gemini $gemini    Client.
	 * @param string $etichetta Che cosa serve.
	 * @param array  $cfg       Configurazione.
	 * @return array 'valore', 'fonti', 'motivo' quando non si e trovato.
	 */
	public static function cercaValore( Gemini $gemini, $etichetta, array $cfg ) {
		$citta   = (string) ( $cfg['seo']['cittaPrincipale'] ?? '' );
		$settore = (string) ( $cfg['azienda']['settore'] ?? 'agenzia di comunicazione e sviluppo web' );
		$anno    = date( 'Y' );

		$istruzioni = "Sei un ricercatore. Cerchi su Google dati verificabili e riferisci solo quello che le fonti dicono.\n"
			. "Regole tassative:\n"
			. "- Non inventi numeri. Se le fonti non danno un dato, lo dici.\n"
			. "- Riporti intervalli, non cifre secche, quando le fonti danno intervalli.\n"
			. "- Non spacci un dato di mercato per il listino di una singola azienda.\n"
			. "Rispondi in italiano, in questo formato esatto e niente altro:\n"
			. "VALORE: <il dato, breve, adatto a essere inserito in una frase>\n"
			. "TIPO: mercato | generale | non_trovato\n"
			. "NOTA: <una riga su che cosa rappresenta il dato>";

		$domanda = "Cerca su Google un dato aggiornato al $anno per: \"$etichetta\".\n"
			. "Contesto: servizi di $settore per piccole e medie imprese in Italia"
			. ( $citta ? ", con riferimento a $citta" : '' ) . ".\n"
			. "Se si tratta del prezzo o del tempo di lavorazione di una singola azienda, "
			. "non puoi saperlo: in quel caso cerca il valore medio di mercato in Italia e indica TIPO: mercato.\n"
			. "Se non trovi fonti attendibili, scrivi TIPO: non_trovato.";

		try {
			$esito = $gemini->cerca( $istruzioni, $domanda, array( 'max_token' => 1200 ) );
		} catch ( Throwable $e ) {
			return array( 'valore' => '', 'fonti' => array(), 'motivo' => $e->getMessage() );
		}

		$testo = (string) $esito['testo'];

		$valore = preg_match( '/^VALORE:\s*(.+)$/mi', $testo, $m ) ? trim( $m[1] ) : '';
		$tipo   = preg_match( '/^TIPO:\s*(\w+)$/mi', $testo, $m2 ) ? strtolower( trim( $m2[1] ) ) : '';
		$nota   = preg_match( '/^NOTA:\s*(.+)$/mi', $testo, $m3 ) ? trim( $m3[1] ) : '';

		// Senza fonti non si scrive niente: un numero di cui non si sa la
		// provenienza e esattamente quello che il segnaposto serve a evitare.
		if ( 'non_trovato' === $tipo || '' === $valore || ! $esito['fonti'] ) {
			return array(
				'valore' => '',
				'fonti'  => $esito['fonti'],
				'motivo' => 'non_trovato' === $tipo
					? 'le fonti non danno questo dato'
					: ( $esito['fonti'] ? 'il modello non ha prodotto un valore' : 'nessuna fonte citata: il dato non sarebbe verificabile' ),
			);
		}

		return array(
			'valore'   => $valore,
			'tipo'     => $tipo ?: 'generale',
			'nota'     => $nota,
			'fonti'    => $esito['fonti'],
			'ricerche' => $esito['ricerche'],
			'motivo'   => '',
		);
	}

	/**
	 * Come va scritto in pagina un valore trovato.
	 *
	 * Un dato di mercato non puo comparire come se fosse il listino dell
	 * agenzia: si dice che e una media di mercato, e da dove viene.
	 *
	 * @param array $trovato Esito di cercaValore().
	 * @return string
	 */
	public static function comeScriverlo( array $trovato ) {
		$valore = (string) $trovato['valore'];

		if ( 'mercato' === ( $trovato['tipo'] ?? '' ) ) {
			return $valore . ' (media di mercato in Italia, non il nostro listino)';
		}

		return $valore;
	}

	/**
	 * Sostituisce i segnaposto risolti dentro le bozze.
	 *
	 * @param Db    $db       Database.
	 * @param int   $auditId  Audit.
	 * @param array $risolti  Chiave normalizzata => esito di cercaValore().
	 * @return array Quante bozze toccate e quanti segnaposto chiusi.
	 */
	public static function applica( Db $db, $auditId, array $risolti ) {
		$bozze     = 0;
		$sostituiti = 0;

		foreach ( $db->all( "SELECT id, corpo_html, in_breve, meta_description FROM bozza WHERE audit_id = ? AND stato = 'ok'", array( $auditId ) ) as $bozza ) {
			$cambiata = false;
			$nuovi    = array();

			foreach ( array( 'corpo_html', 'in_breve', 'meta_description' ) as $campo ) {
				$testo = (string) $bozza[ $campo ];

				$risultato = preg_replace_callback(
					self::SCHEMA,
					static function ( $trovato ) use ( $risolti, &$sostituiti ) {
						$chiave = self::normalizza( $trovato[1] );

						if ( empty( $risolti[ $chiave ]['valore'] ) ) {
							return $trovato[0];
						}

						$sostituiti++;

						return self::comeScriverlo( $risolti[ $chiave ] );
					},
					$testo
				);

				if ( $risultato !== $testo ) {
					$cambiata      = true;
					$nuovi[ $campo ] = $risultato;
				}
			}

			if ( ! $cambiata ) {
				continue;
			}

			$bozze++;

			$db->run(
				'UPDATE bozza SET ' . implode( ', ', array_map( static fn( $c ) => "$c = ?", array_keys( $nuovi ) ) ) . ' WHERE id = ?',
				array_merge( array_values( $nuovi ), array( (int) $bozza['id'] ) )
			);
		}

		return array( 'bozze' => $bozze, 'segnaposto' => $sostituiti );
	}
}
