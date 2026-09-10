<?php
/**
 * Regole su categorie e tag.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Rules;

use SeoGeo\Site;

/**
 * Cluster tematici e archivi.
 */
class Taxonomy {

	/**
	 * @return array[]
	 */
	public static function rules() {
		return array(
			array(
				'id' => 'TAX-01', 'area' => 'taxonomy', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'Categoria sovraccarica',
				'perche' => 'Una categoria che raccoglie la maggioranza degli articoli non crea alcun cluster tematico: l archivio è inutile per l utente e non aiuta Google a capire i temi del sito.',
				'soluzione' => 'Ricategorizzazione in base alle keyword dominanti di ogni articolo.',
				'check' => static function ( Site $s ) {
					$conteggi = array();
					foreach ( $s->articoli as $d ) {
						foreach ( $d['categorie'] as $c ) {
							$conteggi[ $c['nome'] ] = ( $conteggi[ $c['nome'] ] ?? 0 ) + 1;
						}
					}
					$totale = max( 1, count( $s->articoli ) );
					$out    = array();
					foreach ( $conteggi as $nome => $n ) {
						if ( $n / $totale > 0.5 ) {
							$out[] = Base::sito( sprintf( 'categoria "%s": %d articoli su %d (%d%%)', $nome, $n, $totale, round( $n / $totale * 100 ) ) );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'TAX-02', 'area' => 'taxonomy', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Nessun tag utilizzato',
				'perche' => 'Senza tag mancano gli archivi tematici che possono intercettare query di coda lunga e collegare articoli correlati.',
				'soluzione' => 'Set di tag ricavato dalle entità ricorrenti nei testi, massimo cinque per articolo.',
				'check' => static function ( Site $s ) {
					return empty( $s->tag ) ? array( Base::sito( '0 tag definiti sul sito' ) ) : array();
				},
			),
			array(
				'id' => 'TAX-03', 'area' => 'taxonomy', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Categorie fuori tema rispetto al contenuto',
				'perche' => 'Articoli su social o video classificati come e-commerce mandano segnali contraddittori sull argomento della pagina e degli archivi.',
				'soluzione' => 'Assegnare a ogni articolo la categoria coerente con le keyword dominanti.',
				'check' => static function ( Site $s ) {
					$mappa = array(
						'E-commerce'      => '/e-?commerce|shop online|negozio online|carrello|vendita online/i',
						'Marketing'       => '/marketing|social|campagn|advertis|ads|brand|content/i',
						'Web development' => '/sito web|siti web|sviluppo|wordpress|landing|hosting/i',
						'Cyber Security'  => '/sicurezza|cyber|attacco|malware|phishing|backup/i',
						'Software'        => '/software|gestionale|crm|app|automazione/i',
					);
					$out = array();
					foreach ( $s->articoli as $d ) {
						$cat = $d['categorie'][0]['nome'] ?? '';
						if ( '' === $cat || ! isset( $mappa[ $cat ] ) ) {
							continue;
						}
						$hay = $d['titolo'] . ' ' . mb_substr( $d['testo'], 0, 1500 );
						if ( ! preg_match( $mappa[ $cat ], $hay ) ) {
							$migliore = '';
							foreach ( $mappa as $nome => $re ) {
								if ( preg_match( $re, $hay ) ) {
									$migliore = $nome;
									break;
								}
							}
							$out[] = Base::doc( $d, 'categoria "' . $cat . '" non coerente' . ( $migliore ? ', suggerita "' . $migliore . '"' : '' ) );
						}
					}
					return $out;
				},
			),
		);
	}
}
