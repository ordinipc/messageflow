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
				'titolo' => 'Due plugin SEO attivi contemporaneamente (Rank Math e Yoast)',
				'perche' => 'Entrambi stampano title, meta description, canonical, Open Graph e dati strutturati: si generano tag duplicati e in conflitto e Google può scegliere quello sbagliato.',
				'soluzione' => 'Tenere Rank Math, migrare i dati residui di Yoast, disinstallare Yoast e ripulire i postmeta _yoast_*.',
				'check' => static function ( Site $s ) {
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
						? array( Base::sito( "Rank Math su $rm contenuti e Yoast su $yo contenuti: entrambi installati" ) )
						: array();
				},
			),
			array(
				'id' => 'TEC-02', 'area' => 'technical', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Direttiva robots non impostata esplicitamente',
				'perche' => 'Senza direttiva esplicita il comportamento dipende dalle impostazioni globali: pagine di servizio possono finire indicizzate e abbassare la qualità media del sito.',
				'soluzione' => 'Il plugin imposta index,follow,max-snippet:-1,max-image-preview:large sui contenuti utili e noindex sulle pagine di servizio.',
				'check' => static function ( Site $s ) {
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
