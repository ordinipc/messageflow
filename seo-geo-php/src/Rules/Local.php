<?php
/**
 * Regole di SEO locale (GEO geografico).
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Rules;

use SeoGeo\Site;

/**
 * NAP, LocalBusiness, copertura territoriale, recensioni.
 */
class Local {

	/**
	 * @return array[]
	 */
	public static function rules() {
		return array(
			array(
				'id' => 'LOC-01', 'area' => 'local', 'gravita' => Base::CRITICO, 'auto' => true,
				'titolo' => 'NAP incompleto: numero di telefono assente dal sito',
				'perche' => 'Nome, indirizzo e telefono coerenti sono il segnale fondante del local SEO: senza telefono non c è allineamento con il profilo Google Business e il local pack resta irraggiungibile.',
				'soluzione' => 'Il plugin stampa il NAP nel footer e nello schema LocalBusiness.',
				'check' => static function ( Site $s ) {
					$testo = '';
					foreach ( $s->pubblicati as $d ) {
						$testo .= ' ' . $d['testo'];
					}
					return preg_match( '/(?:\+39[\s.]?)?(?:0\d{1,3}[\s.\-\/]?\d{5,8}|3\d{2}[\s.\-]?\d{3}[\s.\-]?\d{3,4})/', $testo )
						? array()
						: array( Base::sito( 'nessun numero di telefono trovato in tutto il contenuto pubblicato' ) );
				},
			),
			array(
				'id' => 'LOC-02', 'area' => 'local', 'gravita' => Base::CRITICO, 'auto' => true,
				'titolo' => 'Indirizzo fisico e partita IVA assenti',
				'perche' => 'Indirizzo e partita IVA sono richiesti dalla normativa italiana per i siti aziendali e sono segnali di fiducia usati da Google per E-E-A-T e posizionamento locale.',
				'soluzione' => 'Footer, pagina Contatti e schema PostalAddress generati dal toolkit.',
				'check' => static function ( Site $s ) {
					$testo = '';
					foreach ( $s->pubblicati as $d ) {
						$testo .= ' ' . $d['testo'];
					}
					$out = array();
					if ( ! preg_match( '/(?:P\.?\s?IVA|partita iva)[:\s]*\d{11}/i', $testo ) ) {
						$out[] = Base::sito( 'partita IVA non presente nel sito' );
					}
					if ( ! preg_match( '/\b(via|viale|piazza|corso|largo)\s+[A-ZÀ-Ù]/u', $testo ) ) {
						$out[] = Base::sito( 'nessun indirizzo postale rilevato' );
					}
					return $out;
				},
			),
			array(
				'id' => 'LOC-03', 'area' => 'local', 'gravita' => Base::CRITICO, 'auto' => true,
				'titolo' => 'Schema LocalBusiness assente',
				'perche' => 'Senza LocalBusiness o ProfessionalService Google non ha un entità aziendale da associare a orari, area servita e recensioni: è il prerequisito tecnico del local pack.',
				'soluzione' => 'Schema ProfessionalService completo generato dal plugin.',
				'check' => static function ( Site $s ) {
					if ( Base::loFaIlSito( $s, 'local' ) ) {
						return array();
					}

					foreach ( $s->pubblicati as $d ) {
						if ( preg_match( '/LocalBusiness|ProfessionalService/i', $d['contenuto'] ) ) {
							return array();
						}
					}
					return array( Base::sito( 'nessuno schema LocalBusiness o ProfessionalService in tutto il sito' ) );
				},
			),
			array(
				'id' => 'LOC-04', 'area' => 'local', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'Nessun collegamento al profilo Google Business',
				'perche' => 'Il collegamento fra sito e scheda Google Business rafforza la corrispondenza dell entità e alimenta le recensioni mostrate in SERP.',
				'soluzione' => 'Aggiungere il link alla scheda e il sameAs nello schema, poi raccogliere recensioni.',
				'check' => static function ( Site $s ) {
					// Quando i dati aziendali sono compilati il plugin mette
					// la scheda Google fra i sameAs dello schema: sta nella
					// testata, non nel testo degli articoli.
					if ( Base::loFaIlSito( $s, 'local' ) ) {
						return array();
					}

					foreach ( $s->pubblicati as $d ) {
						if ( preg_match( '#g\.page|google\.com/maps|maps\.app\.goo\.gl|business\.google#i', $d['contenuto'] ) ) {
							return array();
						}
					}
					return array( Base::sito( 'nessun riferimento alla scheda Google Business trovato' ) );
				},
			),
			array(
				'id' => 'LOC-05', 'area' => 'local', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'Cannibalizzazione fra le landing locali',
				'perche' => 'Più pagine ottimizzate su varianti quasi identiche della stessa query locale si contendono lo stesso posizionamento.',
				'soluzione' => 'Una pagina pilastro per la query principale e pagine servizio differenziate per intento.',
				'check' => static function ( Site $s ) {
					$gruppi = array();
					foreach ( $s->pubblicati as $d ) {
						$k = mb_strtolower( $d['focus'] );
						if ( '' === $k || ! Base::citaCitta( $k ) ) {
							continue;
						}
						$norm = trim( preg_replace( '/\s+/', ' ', preg_replace( '/\b(a|di|in|per|la|il)\b/', ' ', $k ) ) );
						$gruppi[ $norm ][] = $d;
					}
					$out = array();
					foreach ( $gruppi as $k => $docs ) {
						if ( count( $docs ) > 1 ) {
							foreach ( $docs as $d ) {
								$out[] = Base::doc( $d, 'keyword locale "' . $k . '" condivisa con altre ' . ( count( $docs ) - 1 ) . ' pagine' );
							}
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'LOC-06', 'area' => 'local', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Nessuna copertura dei comuni della provincia',
				'perche' => 'Le ricerche locali si frammentano per comune: senza pagine o sezioni dedicate si perde tutta la coda lunga geografica.',
				'soluzione' => 'Creare pagine servizio-comune con contenuto realmente differenziato, mai duplicato.',
				'check' => static function ( Site $s ) {
					$comuni = array( 'monreale', 'bagheria', 'carini', 'cefalù', 'cefalu', 'termini imerese', 'partinico', 'misilmeri' );
					$testo  = '';
					foreach ( $s->pubblicati as $d ) {
						$testo .= ' ' . mb_strtolower( $d['testo'] );
					}
					$trovati = array();
					foreach ( $comuni as $c ) {
						if ( false !== mb_strpos( $testo, $c ) ) {
							$trovati[] = $c;
						}
					}
					return count( $trovati ) >= 3 ? array() : array( Base::sito( 'solo ' . count( $trovati ) . ' comuni della provincia citati (' . ( implode( ', ', $trovati ) ?: 'nessuno' ) . ')' ) );
				},
			),
			array(
				'id' => 'LOC-07', 'area' => 'local', 'gravita' => Base::MEDIO, 'auto' => true,
				'titolo' => 'Landing locali senza segnali geografici nel testo',
				'perche' => 'Una pagina che punta a una keyword locale deve contenere riferimenti reali al territorio: quartieri, comuni, indirizzo, area servita.',
				'soluzione' => 'Blocco "Dove operiamo" con area servita e riferimenti locali.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( ! Base::citaCitta( $d['focus'] ) ) {
							continue;
						}
						$n = preg_match_all( '/palermo/i', $d['testo'] );
						if ( $n < 3 ) {
							$out[] = Base::doc( $d, "keyword locale ma solo $n menzioni della città nel testo" );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'LOC-08', 'area' => 'local', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'Nessuna recensione o testimonianza strutturata',
				'perche' => 'Le recensioni sono un fattore di posizionamento locale, alimentano le stelline in SERP e sono fra i contenuti più citati dalle risposte AI sui fornitori di servizi.',
				'soluzione' => 'Raccogliere recensioni Google e pubblicare testimonianze reali con schema Review.',
				'check' => static function ( Site $s ) {
					foreach ( $s->pubblicati as $d ) {
						if ( preg_match( '/"@type"\s*:\s*"(Review|AggregateRating)"/i', $d['contenuto'] ) ) {
							return array();
						}
					}
					return array( Base::sito( 'nessuna recensione strutturata sul sito' ) );
				},
			),
		);
	}
}
