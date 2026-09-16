<?php
/**
 * Regole su link interni ed esterni.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Rules;

use SeoGeo\Site;

/**
 * Architettura dell informazione e distribuzione dell autorità.
 */
class Links {

	/**
	 * @return array[]
	 */
	public static function rules() {
		return array(
			array(
				'id' => 'LNK-01', 'area' => 'links', 'gravita' => Base::CRITICO, 'auto' => true,
				'titolo' => 'Pagine orfane: nessun link interno in entrata',
				'perche' => 'Una pagina che nessun altra pagina collega riceve pochissimo PageRank interno e viene scansionata di rado: è la prima causa di articoli mai posizionati.',
				'soluzione' => 'Mappa di link interni keyword-URL applicata automaticamente dal plugin.',
				'check' => static function ( Site $s ) {
					if ( Base::loFaIlSito( $s, 'link_interni' ) ) {
						return array();
					}

					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( 0 === $s->inbound( $d['percorso'] ) && 'home' !== $d['slug'] ) {
							$out[] = Base::doc( $d, '0 link interni in entrata' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'LNK-02', 'area' => 'links', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Nessun link interno in uscita',
				'perche' => 'Senza link in uscita l articolo è un vicolo cieco: non distribuisce autorità e non porta l utente verso le pagine che convertono.',
				'soluzione' => 'Inserimento automatico di 3-5 link contestuali verso pagine pilastro e correlati.',
				'check' => static function ( Site $s ) {
					if ( Base::loFaIlSito( $s, 'link_interni' ) ) {
						return array();
					}

					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( 0 === count( $d['link_interni'] ) && $d['parole'] > 150 ) {
							$out[] = Base::doc( $d, '0 link interni in uscita' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'LNK-03', 'area' => 'links', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Anchor text generico',
				'perche' => 'Anchor come "clicca qui" non trasmettono rilevanza semantica alla pagina di destinazione.',
				'soluzione' => 'Sostituire con anchor che descrivono la destinazione.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						$n = 0;
						foreach ( $d['links'] as $l ) {
							if ( preg_match( '/^(clicca qui|qui|leggi di più|scopri di più|link|continua|vai)$/i', trim( $l['anchor'] ) ) ) {
								$n++;
							}
						}
						if ( $n ) {
							$out[] = Base::doc( $d, $n . ' anchor generici' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'LNK-04', 'area' => 'links', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Link esterni in dofollow che disperdono autorità',
				'perche' => 'Centinaia di link dofollow verso social e aggregatori trasferiscono fuori il valore del dominio senza ritorno.',
				'soluzione' => 'Il plugin applica rel="nofollow sponsored" ai domini configurati.',
				'check' => static function ( Site $s ) {
					if ( Base::loFaIlSito( $s, 'nofollow' ) ) {
						return array();
					}

					$conteggi = array();
					foreach ( $s->pubblicati as $d ) {
						foreach ( $d['link_esterni'] as $l ) {
							if ( preg_match( '/nofollow|ugc|sponsored/i', $l['rel'] ) ) {
								continue;
							}
							$host = parse_url( $l['href'], PHP_URL_HOST );
							if ( $host ) {
								$conteggi[ $host ] = ( $conteggi[ $host ] ?? 0 ) + 1;
							}
						}
					}
					arsort( $conteggi );
					$out = array();
					foreach ( $conteggi as $host => $n ) {
						$out[] = Base::sito( $n . ' link dofollow verso ' . $host );
					}
					return $out;
				},
			),
			array(
				'id' => 'LNK-05', 'area' => 'links', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Link con target _blank senza rel di sicurezza',
				'perche' => 'target="_blank" senza rel="noopener" espone al reverse tabnabbing ed è segnalato dagli audit di sicurezza.',
				'soluzione' => 'Aggiunta automatica di rel="noopener noreferrer" dal plugin.',
				'check' => static function ( Site $s ) {
					// Il rel lo aggiunge il plugin mentre serve la pagina:
					// nel testo salvato non c e e non ci sara mai, e
					// cercarlo li lasciava aperto un rilievo che il sito
					// aveva gia risolto.
					if ( Base::loFaIlSito( $s, 'nofollow' ) ) {
						return array();
					}

					$out = array();
					foreach ( $s->pubblicati as $d ) {
						$n = 0;
						foreach ( $d['links'] as $l ) {
							if ( '_blank' === $l['target'] && ! preg_match( '/noopener/i', $l['rel'] ) ) {
								$n++;
							}
						}
						if ( $n ) {
							$out[] = Base::doc( $d, $n . ' link _blank senza noopener' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'LNK-06', 'area' => 'links', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'Il blog non è raggiungibile dal menu principale',
				'perche' => 'Se gli articoli non sono collegati dalla navigazione il crawler li scopre solo dalla sitemap: profondità di click alta e scansione rara.',
				'soluzione' => 'Aggiungere al menu una voce Blog o Risorse e sezioni di articoli correlati nelle pagine servizio.',
				'check' => static function ( Site $s ) {
					foreach ( $s->menu as $m ) {
						if ( preg_match( '/blog|news|risorse|magazine|articoli/i', $m['titolo'] . ' ' . $m['url'] ) ) {
							return array();
						}
					}
					return array( Base::sito( 'menu con ' . count( $s->menu ) . ' voci, nessuna porta al blog (' . count( $s->articoli ) . ' articoli non navigabili)' ) );
				},
			),
			array(
				'id' => 'LNK-07', 'area' => 'links', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'Menu costruito su ancore della home invece che su pagine reali',
				'perche' => 'Voci come /#contatti non creano URL indicizzabili: il sito perde pagine posizionabili per query di brand e di servizio.',
				'soluzione' => 'Creare pagine autonome /chi-siamo/ e /contatti/ e collegarle nel menu.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->menu as $m ) {
						if ( preg_match( '#^/?\#|/\##', (string) $m['url'] ) ) {
							$out[] = Base::sito( 'voce di menu "' . $m['titolo'] . '" punta a ' . $m['url'] );
						}
					}
					return $out;
				},
			),
		);
	}
}
