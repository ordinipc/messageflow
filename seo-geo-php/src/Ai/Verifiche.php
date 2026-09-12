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
