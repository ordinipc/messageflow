<?php
/**
 * Spinta delle pagine con i dati di Search Console.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Search;

use SeoGeo\Db;
use SeoGeo\Text;

/**
 * Su «max digital innovation» Google alterna trentatre pagine dello stesso
 * sito. La home e in posizione 1,2 con 928 impression e 51 clic: per una
 * ricerca di marca in prima posizione i clic dovrebbero essere dieci volte
 * tanti. Trentatre pagine che si contendono lo stesso nome non fanno
 * trentatre volte la forza: fanno un trentatreesimo.
 *
 * Il piano dei link interni finora nasceva dalla somiglianza fra i testi:
 * un indovinello ragionevole. Qui nasce dai dati di Search Console, che non
 * sono un indovinello - e Google stesso a dire su quale ricerca le pagine si
 * alternano e quale delle sue sta messa meglio. Si prende quella, e tutte le
 * altre le passano forza con un link che ha la ricerca per testo.
 *
 * Perche questo e automatico davvero:
 *
 * - non costa token: non c e niente da far scrivere a un modello;
 * - non cancella e non accorpa niente: nessun 301, nessun testo riscritto;
 * - non tocca il database di WordPress: il link lo mette il plugin mentre
 *   serve la pagina, e si toglie rimettendo a zero un numero.
 *
 * Quello che non fa, ed e giusto dirlo dove si scrive il codice: non porta in
 * prima posizione una pagina su una ricerca che nessuno fa. Le duecentoundici
 * pagine senza nemmeno una impression non hanno una posizione da migliorare;
 * qui diventano utili in un altro modo, passando forza a quelle che una
 * posizione ce l hanno.
 */
class Spinta {

	/** Sotto questo numero di impression la ricerca non dice niente. */
	const MIN_IMPRESSION = 5;

	/** Un ancora di una parola sola sparge link a caso per tutto il sito. */
	const MIN_PAROLE = 2;

	/** E una troppo corta finisce dentro ad altre parole. */
	const MIN_CARATTERI = 10;

	/** Oltre questo numero di ancore il testo diventa una rete di link. */
	const MAX_ANCORE = 60;

	/**
	 * Le posizioni dove un link interno in piu cambia qualcosa.
	 *
	 * Sopra la terza si e gia dove si voleva arrivare; oltre la trentesima
	 * non e un link interno a colmare la distanza.
	 */
	const DA_POSIZIONE = 3.0;
	const A_POSIZIONE  = 30.0;

	/**
	 * Il piano di spinta dall ultima rilevazione salvata.
	 *
	 * @param Db    $db            Database.
	 * @param int   $rilevazioneId Rilevazione di Search Console.
	 * @param array $opzioni       Vedi calcola().
	 * @return array
	 */
	public static function daRilevazione( Db $db, $rilevazioneId, array $opzioni = array() ) {
		$righe = $db->all(
			'SELECT query, url, clic, impression, posizione FROM gsc_query WHERE rilevazione_id = ?',
			array( (int) $rilevazioneId )
		);

		return self::calcola( $righe, $opzioni );
	}

	/**
	 * Chi vince ogni ricerca, chi le passa forza, e la mappa per il plugin.
	 *
	 * @param array $righe   Righe query/url di Search Console.
	 * @param array $opzioni 'max_ancore', 'escludi' => percorsi da non usare
	 *                       come destinazione.
	 * @return array 'gruppi', 'mappa', 'conteggi'.
	 */
	public static function calcola( array $righe, array $opzioni = array() ) {
		$escludi = array_flip( array_map( array( __CLASS__, 'confrontabile' ), (array) ( $opzioni['escludi'] ?? array() ) ) );
		$perQuery = array();

		foreach ( $righe as $r ) {
			$query = trim( (string) ( $r['query'] ?? '' ) );
			$url   = trim( (string) ( $r['url'] ?? '' ) );

			if ( '' === $query || '' === $url ) {
				continue;
			}

			$perQuery[ $query ][] = array(
				'url'        => $url,
				'clic'       => (int) $r['clic'],
				'impression' => (int) $r['impression'],
				'posizione'  => (float) $r['posizione'],
			);
		}

		$gruppi = array();

		foreach ( $perQuery as $query => $pagine ) {
			if ( ! self::ancoraValida( $query ) ) {
				continue;
			}

			$impression = array_sum( array_column( $pagine, 'impression' ) );

			if ( $impression < self::MIN_IMPRESSION ) {
				continue;
			}

			// Chi vince: piu clic. A parita di clic - e con zero clic capita
			// sempre - piu impression, e poi la posizione migliore.
			usort(
				$pagine,
				static function ( $a, $b ) {
					return array( $b['clic'], $b['impression'], $a['posizione'] )
						<=> array( $a['clic'], $a['impression'], $b['posizione'] );
				}
			);

			$vincitore = $pagine[0];

			if ( isset( $escludi[ self::confrontabile( $vincitore['url'] ) ] ) ) {
				continue;
			}

			$contesa = count( $pagine ) > 1;

			// Due ragioni per entrare, e sono diverse. O piu pagine tue si
			// alternano - li il link serve a dire a Google quale delle tue -
			// oppure c e una sola pagina, in una posizione da cui si puo
			// ancora salire.
			$spingibile = $vincitore['posizione'] >= self::DA_POSIZIONE
				&& $vincitore['posizione'] <= self::A_POSIZIONE;

			if ( ! $contesa && ! $spingibile ) {
				continue;
			}

			$gruppi[] = array(
				'query'      => (string) $query,
				'vincitore'  => $vincitore['url'],
				'posizione'  => $vincitore['posizione'],
				'clic'       => $vincitore['clic'],
				'impression' => $impression,
				'contesa'    => $contesa,
				'perdenti'   => array_values( array_column( array_slice( $pagine, 1 ), 'url' ) ),
				'motivo'     => $contesa
					? count( $pagine ) . ' tue pagine si alternano su questa ricerca'
					: 'posizione ' . number_format( $vincitore['posizione'], 1, ',', '.' ) . ': si puo salire',
			);
		}

		// Prima le ricerche che valgono di piu: se si deve tagliare, si
		// taglia da quelle che contano meno.
		usort(
			$gruppi,
			static function ( $a, $b ) {
				return array( $b['contesa'] ? 1 : 0, $b['impression'] ) <=> array( $a['contesa'] ? 1 : 0, $a['impression'] );
			}
		);

		$max    = (int) ( $opzioni['max_ancore'] ?? self::MAX_ANCORE );
		$gruppi = array_slice( $gruppi, 0, max( 1, $max ) );

		$mappa    = array();
		$contese  = 0;
		$perdenti = array();

		foreach ( $gruppi as $g ) {
			$mappa[ $g['query'] ] = $g['vincitore'];

			if ( $g['contesa'] ) {
				$contese++;

				foreach ( $g['perdenti'] as $p ) {
					$perdenti[ self::confrontabile( $p ) ] = true;
				}
			}
		}

		return array(
			'gruppi'   => $gruppi,
			'mappa'    => $mappa,
			'conteggi' => array(
				'ricerche'          => count( $gruppi ),
				'contese'           => $contese,
				'pagine_che_cedono' => count( $perdenti ),
			),
		);
	}

	/**
	 * Una ricerca si puo usare come testo del link?
	 *
	 * @param string $query Ricerca.
	 * @return bool
	 */
	public static function ancoraValida( $query ) {
		$query = trim( (string) $query );

		if ( mb_strlen( $query ) < self::MIN_CARATTERI ) {
			return false;
		}

		if ( count( Text::words( $query ) ) < self::MIN_PAROLE ) {
			return false;
		}

		// Gli indirizzi e le ricerche con caratteri strani non sono testo che
		// si possa mettere dentro a una frase.
		return ! preg_match( '~https?://|[<>{}\[\]"]|www\.~i', $query );
	}

	/**
	 * Percorso confrontabile, per riconoscere lo stesso indirizzo scritto in
	 * due modi.
	 *
	 * @param string $url Indirizzo.
	 * @return string
	 */
	public static function confrontabile( $url ) {
		$url = preg_replace( '~^https?://[^/]+~i', '', (string) $url );

		return '/' . strtolower( trim( (string) $url, '/' ) );
	}
}
