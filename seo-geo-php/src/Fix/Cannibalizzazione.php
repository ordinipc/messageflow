<?php
/**
 * Chi vince la ricerca, e che cosa fanno le altre pagine.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Fix;

use SeoGeo\Db;
use SeoGeo\Search\Prestazioni;
use SeoGeo\Text;

/**
 * «Due pagine tue competono sulla stessa ricerca» e una diagnosi, non un
 * rimedio, e il rimedio che il programma proponeva - fondere - qui non si
 * puo applicare: in tutti e due i gruppi trovati sul sito una delle due e
 * una pagina servizio, e le pagine non si toccano. Il risultato era un
 * rilievo che restava aperto con scritto «non c e niente da fondere».
 *
 * Fondere non e nemmeno la cosa giusta da fare, li. Una pagina servizio e un
 * articolo del blog non sono due doppioni: sono due lavori diversi che per
 * sbaglio dichiarano la stessa parola chiave. La pagina deve vincere la
 * ricerca commerciale, l articolo deve smettere di dichiararla e prendersi
 * la variante lunga che gia racconta, e mandare forza alla pagina con un
 * link che ha la ricerca per testo.
 *
 * Tre decisioni, e nessuna richiede di indovinare:
 *
 * 1. Chi vince. Se Search Console ha dei dati su quella ricerca, vince chi
 *    Google gia sceglie: e una misura, non un opinione. Senza dati vince la
 *    pagina servizio sulla ricerca commerciale, e fra contenuti dello stesso
 *    tipo quello piu lungo.
 * 2. Che cosa prende chi perde. La variante lunga si ricava dal suo stesso
 *    titolo, togliendo le parole della ricerca contesa: quello che resta e
 *    l argomento che quell articolo tratta davvero. Se non resta niente di
 *    sensato non si inventa una parola chiave: il gruppo passa a chi decide.
 * 3. Che cosa si fa da soli. Solo sugli articoli, mai sulle pagine, e senza
 *    riscrivere il testo: cambia la parola chiave dichiarata, cambiano
 *    title e description che ne discendono, e si aggiunge il link verso chi
 *    vince. Niente 301, niente fusioni, tutto reversibile.
 */
class Cannibalizzazione {

	/** Sotto queste parole la variante lunga non e una variante: e un residuo. */
	const MIN_PAROLE_VARIANTE = 2;

	/**
	 * I gruppi di contenuti che si contendono la stessa ricerca.
	 *
	 * Si ricostruiscono dalla parola chiave dichiarata, che e la stessa cosa
	 * che guardano le regole ONP-06 e LOC-05: un secondo modo di raggrupparli
	 * finirebbe per dire numeri diversi dalla tabella dei problemi.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit.
	 * @param array $gsc     Righe query/url di Search Console, se ci sono.
	 * @return array[]
	 */
	public static function gruppi( Db $db, $auditId, array $gsc = array() ) {
		$righe = $db->all(
			"SELECT id, wp_id, titolo, slug, url, percorso, tipo, parole, focus_keyword AS focus
			 FROM documento
			 WHERE audit_id = ? AND stato = 'publish' AND focus_keyword <> ''",
			array( (int) $auditId )
		);

		$per_chiave = array();

		foreach ( $righe as $d ) {
			$per_chiave[ self::normalizza( $d['focus'] ) ][] = $d;
		}

		$fuori = array();

		foreach ( $per_chiave as $chiave => $membri ) {
			if ( count( $membri ) < 2 ) {
				continue;
			}

			$fuori[] = self::componi( $chiave, $membri, $gsc );
		}

		usort(
			$fuori,
			static function ( $a, $b ) {
				return count( $b['perdenti'] ) <=> count( $a['perdenti'] );
			}
		);

		return $fuori;
	}

	/**
	 * Un gruppo con dentro la decisione gia presa.
	 *
	 * @param string $chiave Ricerca contesa.
	 * @param array  $membri Contenuti.
	 * @param array  $gsc    Righe di Search Console.
	 * @return array
	 */
	private static function componi( $chiave, array $membri, array $gsc ) {
		$vincitore = self::vincitore( $chiave, $membri, $gsc, $daGoogle );
		$perdenti  = array();

		foreach ( $membri as $m ) {
			if ( (int) $m['id'] === (int) $vincitore['id'] ) {
				continue;
			}

			$variante = self::variante( $m, $chiave );

			$perdenti[] = $m + array(
				'variante'  => $variante,
				// Le pagine servizio sono poche e scritte a mano: qui si dice
				// che cosa andrebbe fatto, e lo fa una persona.
				'automatico' => 'post' === (string) $m['tipo'] && '' !== $variante,
				'motivo'     => self::perche( $m, $variante ),
			);
		}

		return array(
			'chiave'     => (string) $chiave,
			// La chiave del gruppo e ordinata alfabeticamente per riconoscere
			// «produzione video palermo» e «video di produzione a palermo»
			// come la stessa ricerca. Come testo di un link non serve: quella
			// frase non la scrive nessuno, e il link non verrebbe inserito
			// mai. L ancora e la parola chiave vera di chi vince.
			'ancora'     => trim( (string) $vincitore['focus'] ) ?: (string) $chiave,
			'vincitore'  => $vincitore,
			'perdenti'   => $perdenti,
			'da_google'  => (bool) $daGoogle,
			'spiega'     => $daGoogle
				? 'Vince la pagina che Google già sceglie per questa ricerca: è una misura, non un parere.'
				: ( 'page' === (string) $vincitore['tipo']
					? 'Vince la pagina servizio: è quella fatta per farsi cercare con questa parola.'
					: 'Vince il contenuto più esteso: senza dati di Google è il segnale più solido che resta.' ),
		);
	}

	/**
	 * Chi deve tenersi la ricerca.
	 *
	 * @param string $chiave   Ricerca.
	 * @param array  $membri   Contenuti.
	 * @param array  $gsc      Righe di Search Console.
	 * @param bool   $daGoogle Valorizzato a vero se ha deciso Google.
	 * @return array
	 */
	private static function vincitore( $chiave, array $membri, array $gsc, &$daGoogle = false ) {
		$daGoogle = false;
		$punti    = array();

		foreach ( $gsc as $r ) {
			if ( self::normalizza( $r['query'] ?? '' ) !== $chiave ) {
				continue;
			}

			$punti[ self::percorso( $r['url'] ?? '' ) ] = array(
				'clic'       => (int) ( $r['clic'] ?? 0 ),
				'impression' => (int) ( $r['impression'] ?? 0 ),
			);
		}

		$ordinati = $membri;

		usort(
			$ordinati,
			static function ( $a, $b ) use ( $punti ) {
				$pa = $punti[ self::percorso( $a['percorso'] ?: $a['url'] ) ] ?? null;
				$pb = $punti[ self::percorso( $b['percorso'] ?: $b['url'] ) ] ?? null;

				// Prima chi ha dei dati, e fra questi chi rende di piu.
				$sa = array( null !== $pa ? 1 : 0, $pa['clic'] ?? 0, $pa['impression'] ?? 0 );
				$sb = array( null !== $pb ? 1 : 0, $pb['clic'] ?? 0, $pb['impression'] ?? 0 );

				if ( $sa !== $sb ) {
					return $sb <=> $sa;
				}

				// Senza dati: la pagina servizio, poi il contenuto piu lungo.
				$ta = 'page' === (string) $a['tipo'] ? 1 : 0;
				$tb = 'page' === (string) $b['tipo'] ? 1 : 0;

				return array( $tb, (int) $b['parole'] ) <=> array( $ta, (int) $a['parole'] );
			}
		);

		$scelto = $ordinati[0];
		$suo    = $punti[ self::percorso( $scelto['percorso'] ?: $scelto['url'] ) ] ?? null;

		// «Ha deciso Google» solo se davvero ci sono dei numeri, e solo se
		// non li hanno tutti uguali a zero: una riga a zero clic e zero
		// impression non sceglie niente.
		$daGoogle = null !== $suo && ( $suo['clic'] > 0 || $suo['impression'] > 0 );

		return $scelto;
	}

	/**
	 * La variante lunga che resta a chi perde.
	 *
	 * Si ricava dal suo titolo togliendo le parole della ricerca contesa:
	 * quello che avanza e l argomento che quell articolo tratta davvero, ed
	 * e gia scritto li dentro. Non si inventa niente: se non avanza abbastanza
	 * il gruppo passa a chi decide.
	 *
	 * @param array  $doc    Contenuto che perde.
	 * @param string $chiave Ricerca contesa.
	 * @return string Vuoto se non si puo ricavare.
	 */
	public static function variante( array $doc, $chiave ) {
		$contese = array();

		foreach ( Text::words( (string) $chiave ) as $p ) {
			$contese[ $p ] = true;
		}

		$restano = array();

		foreach ( Text::words( (string) $doc['titolo'] ) as $p ) {
			if ( isset( $contese[ $p ] ) || in_array( $p, Text::$stopwords, true ) || mb_strlen( $p ) < 3 ) {
				continue;
			}

			// Un anno non e un argomento: «nel 2025» in coda a un titolo non
			// puo diventare la parola chiave di niente.
			if ( preg_match( '/^(19|20)\d{2}$/', $p ) ) {
				continue;
			}

			$restano[ $p ] = true;
		}

		if ( count( $restano ) < self::MIN_PAROLE_VARIANTE ) {
			return '';
		}

		// La ricerca contesa resta dentro, ma non piu da sola: e la variante
		// lunga che distingue questo contenuto da chi vince.
		return trim( $chiave . ' ' . implode( ' ', array_slice( array_keys( $restano ), 0, 4 ) ) );
	}

	/**
	 * Perche questo contenuto e in questa colonna.
	 *
	 * @param array  $doc      Contenuto.
	 * @param string $variante Variante trovata.
	 * @return string
	 */
	private static function perche( array $doc, $variante ) {
		if ( 'post' !== (string) $doc['tipo'] ) {
			return 'è una pagina servizio: la parola chiave va cambiata a mano, il testo non si tocca';
		}

		if ( '' === $variante ) {
			return 'il titolo non dice niente che lo distingua da chi vince: qui serve una decisione, o si fondono';
		}

		return 'prende la variante lunga che già racconta, e manda forza a chi vince';
	}

	/**
	 * Le modifiche da applicare, contenuto per contenuto.
	 *
	 * @param array[] $gruppi Esito di gruppi().
	 * @return array 'meta' => righe per il sito, 'link' => ancora => url,
	 *               'a_mano' => quello che resta a una persona.
	 */
	public static function piano( array $gruppi ) {
		$meta   = array();
		$link   = array();
		$aMano  = array();

		foreach ( $gruppi as $g ) {
			foreach ( $g['perdenti'] as $p ) {
				if ( empty( $p['automatico'] ) ) {
					$aMano[] = array(
						'titolo' => (string) $p['titolo'],
						'url'    => (string) $p['url'],
						'chiave' => (string) $g['chiave'],
						'motivo' => (string) $p['motivo'],
					);

					continue;
				}

				$meta[] = array(
					'documento_id' => (int) $p['id'],
					'wp_id'        => (string) $p['wp_id'],
					'titolo'       => (string) $p['titolo'],
					'focus'        => (string) $p['variante'],
					'prima'        => (string) $p['focus'],
				);
			}

			// Il link va messo comunque, anche quando chi perde e una pagina:
			// il link lo inseriscono gli articoli, e serve a dire a Google
			// quale delle due pagine vale per quella ricerca.
			if ( $g['perdenti'] ) {
				$link[ (string) ( $g['ancora'] ?? $g['chiave'] ) ] = (string) $g['vincitore']['url'];
			}
		}

		return array( 'meta' => $meta, 'link' => $link, 'a_mano' => $aMano );
	}

	/**
	 * Le righe di Search Console dell ultima rilevazione, se ci sono.
	 *
	 * @param Db    $db  Database.
	 * @param array $cfg Configurazione.
	 * @return array[]
	 */
	public static function daGoogle( Db $db, array $cfg ) {
		try {
			$ultima = Prestazioni::ultima( $db, Prestazioni::chiaveSito( $cfg ) );

			if ( ! $ultima ) {
				return array();
			}

			return $db->all(
				'SELECT query, url, clic, impression, posizione FROM gsc_query WHERE rilevazione_id = ?',
				array( (int) $ultima['id'] )
			);
		} catch ( \Throwable $e ) {
			// Senza Search Console si decide con quello che c e: non e una
			// ragione per non decidere.
			return array();
		}
	}

	/**
	 * Forma confrontabile di una ricerca.
	 *
	 * Le stesse parole in ordine diverso, o con «a», «di», «per» in mezzo,
	 * sono la stessa ricerca: e cosi che le regole le raggruppano, e qui si
	 * fa uguale per non contare gruppi diversi dalla tabella dei problemi.
	 *
	 * @param string $chiave Ricerca.
	 * @return string
	 */
	public static function normalizza( $chiave ) {
		$parole = array();

		foreach ( Text::words( mb_strtolower( (string) $chiave ) ) as $p ) {
			if ( ! in_array( $p, array( 'a', 'di', 'in', 'per', 'la', 'il', 'lo', 'le', 'i', 'gli', 'e', 'da' ), true ) ) {
				$parole[] = $p;
			}
		}

		sort( $parole );

		return implode( ' ', $parole );
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
