<?php
/**
 * Regole tecniche e di indicizzazione.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Rules;

use SeoGeo\Site;

/**
 * Conflitti fra plugin, direttive robots, canonical, pagine mancanti.
 */
class Technical {

	/**
	 * @return array[]
	 */
	public static function rules() {
		return array(
			array(
				'id' => 'TEC-01', 'area' => 'technical', 'gravita' => Base::CRITICO, 'auto' => false,
				'titolo' => 'Due plugin SEO attivi contemporaneamente',
				'perche' => 'Entrambi stampano title, meta description, canonical, Open Graph e dati strutturati: si generano tag duplicati e in conflitto e Google può scegliere quello sbagliato.',
				'soluzione' => 'Tenerne uno solo: migrare i dati, disattivare l altro e ripulire i suoi postmeta.',
				'check' => static function ( Site $s ) {
					// Prima si guardavano i postmeta: se c erano sia
					// rank_math_title sia _yoast_wpseo_title si concludeva che
					// i due plugin erano entrambi attivi. Ma un Yoast
					// disinstallato lascia i suoi postmeta nel database per
					// sempre, e da quelli non si puo dedurre niente sul
					// presente. Il gestionale segnalava un conflitto critico a
					// chi aveva un plugin solo.
					//
					// Adesso si chiede al sito quali sono caricati davvero.
					if ( $s->seoAttivi ) {
						if ( count( $s->seoAttivi ) < 2 ) {
							return array();
						}

						return array(
							Base::sito( 'attivi insieme: ' . implode( ' e ', $s->seoAttivi ) ),
						);
					}

					// Il sito non lo dichiara - analisi da un export, o plugin
					// non aggiornato - e allora si torna a guardare i
					// postmeta, ma dicendo che cosa si e visto davvero.
					$rm = 0;
					$yo = 0;
					foreach ( $s->pubblicati as $d ) {
						if ( isset( $d['meta']['rank_math_title'] ) ) {
							$rm++;
						}
						if ( isset( $d['meta']['_yoast_wpseo_title'] ) || isset( $d['meta']['_yoast_wpseo_metadesc'] ) ) {
							$yo++;
						}
					}

					return ( $rm && $yo )
						? array( Base::sito( "dati di Rank Math su $rm contenuti e di Yoast su $yo: da verificare quali plugin siano attivi" ) )
						: array();
				},
			),
			array(
				'id' => 'TEC-09', 'area' => 'technical', 'gravita' => Base::BASSO, 'auto' => false,
				'titolo' => 'Dati di un vecchio plugin SEO rimasti nel database',
				'perche' => 'I postmeta di un plugin disinstallato non fanno danno a Google, ma restano nel database per sempre, appesantiscono i backup e confondono chi cerca di capire da dove esce un title.',
				'soluzione' => 'Ripulire i postmeta del plugin non più in uso, dopo aver verificato che i dati siano stati migrati.',
				'check' => static function ( Site $s ) {
					// Ha senso solo quando si sa che cosa e attivo: senza,
					// non si distingue un residuo da un plugin in funzione.
					if ( ! $s->seoAttivi ) {
						return array();
					}

					$altri = array(
						'Yoast SEO'    => array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ),
						'Rank Math'    => array( 'rank_math_title', 'rank_math_description' ),
					);

					$out = array();

					foreach ( $altri as $nome => $chiavi ) {
						if ( in_array( $nome, $s->seoAttivi, true ) ) {
							continue;
						}

						$quanti = 0;

						foreach ( $s->pubblicati as $d ) {
							foreach ( $chiavi as $chiave ) {
								if ( isset( $d['meta'][ $chiave ] ) ) {
									$quanti++;
									break;
								}
							}
						}

						if ( $quanti ) {
							$out[] = Base::sito( $nome . ' non è più attivo ma ha lasciato dati su ' . $quanti . ' contenuti' );
						}
					}

					return $out;
				},
			),
			array(
				'id' => 'TEC-02', 'area' => 'technical', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Direttiva robots non impostata esplicitamente',
				'perche' => 'Senza direttiva esplicita il comportamento dipende dalle impostazioni globali: pagine di servizio possono finire indicizzate e abbassare la qualità media del sito.',
				'soluzione' => 'Il plugin imposta index,follow,max-snippet:-1,max-image-preview:large sui contenuti utili e noindex sulle pagine di servizio.',
				'check' => static function ( Site $s ) {
					if ( Base::loFaIlSito( $s, 'robots' ) ) {
						return array();
					}

					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( '' === $d['robots'] ) {
							$out[] = Base::doc( $d, 'nessun meta robots salvato' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'TEC-03', 'area' => 'technical', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Direttiva max-image-preview:large assente',
				'perche' => 'Senza questa direttiva Google mostra miniature piccole e la pagina non è ammessa in Google Discover.',
				'soluzione' => 'Aggiunta automatica nella meta robots dal plugin.',
				'check' => static function ( Site $s ) {
					if ( Base::loFaIlSito( $s, 'robots' ) ) {
						return array();
					}

					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( ! preg_match( '/max-image-preview/', $d['robots'] ) ) {
							$out[] = Base::doc( $d, 'direttiva assente' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'TEC-04', 'area' => 'technical', 'gravita' => Base::MEDIO, 'auto' => true,
				'titolo' => 'Canonical non dichiarato esplicitamente',
				'perche' => 'Il canonical esplicito protegge dalle duplicazioni generate da parametri UTM, paginazione e varianti con o senza slash finale.',
				'soluzione' => 'Il plugin stampa un canonical assoluto e autoreferenziale su ogni URL.',
				'check' => static function ( Site $s ) {
					if ( Base::loFaIlSito( $s, 'canonical' ) ) {
						return array();
					}

					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( '' === $d['canonical'] ) {
							$out[] = Base::doc( $d, 'canonical non impostato' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'TEC-05', 'area' => 'technical', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Pagine di servizio indicizzabili',
				'perche' => 'Pagine legali o di ringraziamento indicizzate abbassano la qualità media del sito e sprecano crawl budget.',
				'soluzione' => 'Impostare noindex,follow su queste pagine.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( preg_match( '/privacy|cookie|grazie|thank|termini|condizioni/i', $d['slug'] ) && ! $d['noindex'] ) {
							$out[] = Base::doc( $d, 'indicizzabile, dovrebbe essere noindex' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'TEC-06', 'area' => 'technical', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Contenuti nel cestino mai eliminati',
				'perche' => 'Le pagine in cestino restano nel database, appesantiscono le query e se ripristinate per errore creano URL duplicati.',
				'soluzione' => 'Svuotare il cestino dopo aver verificato che non servano redirect dai vecchi URL.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->cestino as $d ) {
						$out[] = Base::doc( $d, 'in cestino, slug ' . $d['slug'] );
					}
					return $out;
				},
			),
			array(
				'id' => 'TEC-07', 'area' => 'technical', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Pagine chiave assenti (Chi siamo, Contatti)',
				'perche' => 'Sono le pagine con cui Google valuta l affidabilità e le uniche che intercettano le ricerche di brand: qui esistono solo come ancore della home o nel cestino.',
				'soluzione' => 'Pubblicare /chi-siamo/ e /contatti/ con dati aziendali e dati strutturati.',
				'check' => static function ( Site $s ) {
					$attese = array(
						array( 'chi-siamo', 'Chi siamo', '/chi-siamo|about|azienda|team/i' ),
						array( 'contatti', 'Contatti', '/contatt|contact/i' ),
					);
					$out = array();
					foreach ( $attese as $a ) {
						$trovata = false;
						foreach ( $s->pagine as $p ) {
							if ( preg_match( $a[2], $p['slug'] ) ) {
								$trovata = true;
								break;
							}
						}
						if ( ! $trovata ) {
							$out[] = Base::sito( 'pagina "' . $a[1] . '" (/' . $a[0] . '/) non presente fra le pagine pubblicate' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'TEC-08', 'area' => 'technical', 'gravita' => Base::BASSO, 'auto' => false,
				'titolo' => 'Quota alta di pagine costruite con page builder',
				'perche' => 'Elementor genera markup annidato e CSS pesante: incide su LCP e INP, due Core Web Vitals usati come segnali.',
				'soluzione' => 'Attivare gli asset ottimizzati di Elementor, disattivare i widget inutilizzati, usare cache e critical CSS.',
				'check' => static function ( Site $s ) {
					$n = 0;
					foreach ( $s->pubblicati as $d ) {
						if ( $d['elementor'] ) {
							$n++;
						}
					}
					$perc = (int) round( $n / max( 1, count( $s->pubblicati ) ) * 100 );
					return $perc > 50 ? array( Base::sito( "$n contenuti su " . count( $s->pubblicati ) . " ($perc%) generati con Elementor" ) ) : array();
				},
			),
		);
	}
}
