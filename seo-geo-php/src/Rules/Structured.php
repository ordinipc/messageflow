<?php
/**
 * Regole sui dati strutturati.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Rules;

use SeoGeo\Site;

/**
 * JSON-LD, FAQ, breadcrumb, Service, Open Graph.
 */
class Structured {

	/**
	 * @return array[]
	 */
	public static function rules() {
		return array(
			array(
				'id' => 'SCH-01', 'area' => 'structured', 'gravita' => Base::CRITICO, 'auto' => true,
				'titolo' => 'Nessun dato strutturato personalizzato nei contenuti',
				'perche' => 'Senza schema Google non ottiene entità esplicite: niente rich result, niente knowledge panel e i motori generativi faticano ad attribuire le informazioni al brand.',
				'soluzione' => 'Il plugin inietta Organization, WebSite, BreadcrumbList, Article, FAQPage, Service e Person.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( ! $d['ha_jsonld'] ) {
							$out[] = Base::doc( $d, 'nessun JSON-LD nel contenuto' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'SCH-02', 'area' => 'structured', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'FAQPage assente su pagine che contengono domande',
				'perche' => 'Le pagine con domande nei titoli sono candidate naturali ai rich result FAQ e alle citazioni dirette degli assistenti AI.',
				'soluzione' => 'Il plugin genera FAQPage dalle coppie domanda/risposta trovate negli H2 e H3.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( $d['ha_jsonld'] ) {
							continue;
						}
						$domande = 0;
						foreach ( $d['titoli'] as $h ) {
							if ( false !== strpos( $h['testo'], '?' ) ) {
								$domande++;
							}
						}
						if ( $domande ) {
							$out[] = Base::doc( $d, $domande . ' domande nei titoli, nessun FAQPage' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'SCH-03', 'area' => 'structured', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'BreadcrumbList assente',
				'perche' => 'I breadcrumb chiariscono la gerarchia del sito e sostituiscono l URL nello snippet, aumentando il CTR.',
				'soluzione' => 'Breadcrumb e relativo JSON-LD generati dal plugin.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( ! preg_match( '/BreadcrumbList/i', $d['contenuto'] ) ) {
							$out[] = Base::doc( $d, 'nessun BreadcrumbList' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'SCH-04', 'area' => 'structured', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Nessuno schema Service sulle pagine servizio',
				'perche' => 'Le pagine servizio senza schema Service non comunicano cosa si vende, dove e a chi: informazione chiave per il local pack e per le risposte AI.',
				'soluzione' => 'Schema Service con areaServed, provider e offerta generato per ogni pagina servizio.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pagine as $d ) {
						if ( preg_match( '/servizi|realizzazione|gestione|produzione|sviluppo|marketing|design|naming|stampe/i', $d['slug'] )
							&& ! preg_match( '/"@type"\s*:\s*"Service"/i', $d['contenuto'] ) ) {
							$out[] = Base::doc( $d, 'schema Service assente' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'SCH-05', 'area' => 'structured', 'gravita' => Base::MEDIO, 'auto' => true,
				'titolo' => 'Open Graph e Twitter Card non personalizzati',
				'perche' => 'Senza og:title, og:description e og:image le condivisioni social perdono anteprima e CTR, e alcuni crawler AI usano proprio l Open Graph come riassunto.',
				'soluzione' => 'Il plugin stampa Open Graph e Twitter Card completi con immagine di riserva.',
				'check' => static function ( Site $s ) {
					$con = 0;
					foreach ( $s->pubblicati as $d ) {
						if ( isset( $d['meta']['rank_math_facebook_title'] ) || isset( $d['meta']['_yoast_wpseo_opengraph-title'] ) ) {
							$con++;
						}
					}
					return 0 === $con ? array( Base::sito( 'nessuno dei ' . count( $s->pubblicati ) . ' contenuti ha Open Graph personalizzato' ) ) : array();
				},
			),
		);
	}
}
