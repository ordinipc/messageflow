<?php
/**
 * Regole E-E-A-T: esperienza, competenza, autorevolezza, affidabilità.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Rules;

use SeoGeo\Site;

/**
 * Segnali di fiducia che Google usa per valutare chi sta dietro al sito.
 */
class Eeat {

	/**
	 * @return array[]
	 */
	public static function rules() {
		return array(
			array(
				'id' => 'EAT-01', 'area' => 'eeat', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Nessuna biografia autore collegata ai contenuti',
				'perche' => 'Per i servizi professionali Google valuta chi scrive: senza author box e schema Person manca il segnale di competenza.',
				'soluzione' => 'Author box e schema Person con ruolo, esperienza e profili verificabili.',
				'check' => static function ( Site $s ) {
					// Lo schema Person sta nella testata della pagina, lo
					// stampa il plugin: nel testo salvato degli articoli non
					// c e e non ci sara mai. Cercarlo li teneva aperto un
					// rilievo che nessuno poteva chiudere.
					if ( Base::loFaIlSito( $s, 'autore' ) ) {
						return array();
					}

					foreach ( $s->pubblicati as $d ) {
						if ( preg_match( '/"@type"\s*:\s*"Person"/i', $d['contenuto'] ) ) {
							return array();
						}
					}
					return array( Base::sito( 'nessuno schema Person o author box rilevato' ) );
				},
			),
			array(
				'id' => 'EAT-02', 'area' => 'eeat', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'Nessun caso studio o portfolio verificabile',
				'perche' => 'L esperienza dimostrata, la prima E di E-E-A-T, per un agenzia si prova con lavori reali, risultati misurati e clienti citabili.',
				'soluzione' => 'Pagina portfolio con 5-8 casi studio (problema, intervento, risultato numerico).',
				'check' => static function ( Site $s ) {
					foreach ( $s->pagine as $p ) {
						if ( preg_match( '/portfolio|case-stud|casi-studio|lavori|progetti/i', $p['slug'] ) ) {
							return array();
						}
					}
					return array( Base::sito( 'nessuna pagina portfolio o casi studio pubblicata' ) );
				},
			),
			array(
				'id' => 'EAT-03', 'area' => 'eeat', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Contenuti con tratti di produzione automatica non revisionata',
				'perche' => 'Testi uniformi per lunghezza e struttura, senza dati, esempi o firme, rientrano nei contenuti scalati che le linee guida antispam di Google penalizzano.',
				'soluzione' => 'Revisione umana: esperienza diretta, dati propri, esempi di clienti reali, foto originali.',
				'check' => static function ( Site $s ) {
					$posts = array();
					foreach ( $s->articoli as $d ) {
						if ( $d['parole'] > 100 ) {
							$posts[] = $d;
						}
					}
					if ( count( $posts ) < 20 ) {
						return array();
					}
					$sospetti = 0;
					foreach ( $posts as $d ) {
						if ( 0 === count( $d['immagini'] ) && 0 === count( $d['link_interni'] ) && $d['parole'] < 900 && 0 === $d['tabelle'] ) {
							$sospetti++;
						}
					}
					$quota = (int) round( $sospetti / count( $posts ) * 100 );
					return $quota > 40
						? array( Base::sito( "$sospetti articoli su " . count( $posts ) . " ($quota%) senza immagini, link interni, tabelle o dati: profilo tipico di contenuto prodotto in serie" ) )
						: array();
				},
			),
			array(
				'id' => 'EAT-04', 'area' => 'eeat', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Commenti chiusi ovunque',
				'perche' => 'Interazione e contenuti generati dagli utenti sono segnali di sito vivo: non sono decisivi ma contribuiscono alla percezione di attività.',
				'soluzione' => 'Valutare l apertura dei commenti moderati sugli articoli guida.',
				'check' => static function ( Site $s ) {
					foreach ( $s->articoli as $d ) {
						if ( 'open' === $d['commenti'] ) {
							return array();
						}
					}
					return array( Base::sito( 'commenti chiusi su tutti i ' . count( $s->articoli ) . ' articoli' ) );
				},
			),
		);
	}
}
