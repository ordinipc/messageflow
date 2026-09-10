<?php
/**
 * Pannello di controllo e avvisi in bacheca.
 *
 * @package MDI_SEO_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mostra lo stato delle correzioni e segnala i conflitti fra plugin SEO.
 */
class MDI_Admin {

	/**
	 * Aggancia gli hook di amministrazione.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'conflict_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'config_notice' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	/**
	 * Avvisa quando sono attivi due plugin SEO insieme.
	 *
	 * @return void
	 */
	public static function conflict_notice() {
		$rank  = defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' );
		$yoast = defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Frontend' );

		if ( ! $rank || ! $yoast ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>MDI SEO &amp; GEO Booster:</strong> Rank Math e Yoast SEO sono attivi contemporaneamente. Stampano entrambi title, description, canonical e dati strutturati: i tag risultano duplicati e Google può usare quello sbagliato. Disattiva uno dei due (consigliato: mantenere Rank Math).</p></div>';
	}

	/**
	 * Avvisa quando restano segnaposto non compilati.
	 *
	 * @return void
	 */
	public static function config_notice() {
		$mancanti = array();

		$campi = array(
			'azienda.telefono'              => 'telefono',
			'azienda.partitaIva'            => 'partita IVA',
			'azienda.indirizzo.via'         => 'indirizzo',
			'azienda.indirizzo.cap'         => 'CAP',
			'azienda.profili.googleBusiness' => 'scheda Google Business',
		);

		foreach ( $campi as $path => $label ) {
			if ( ! mdi_seo_geo_cfg( $path ) ) {
				$mancanti[] = $label;
			}
		}

		if ( empty( $mancanti ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>MDI SEO &amp; GEO Booster:</strong> dati aziendali mancanti in <code>data/config.json</code>: %s. Senza questi valori lo schema LocalBusiness resta incompleto e il posizionamento locale non decolla.</p></div>',
			esc_html( implode( ', ', $mancanti ) )
		);
	}

	/**
	 * Voce di menu con il riepilogo delle correzioni attive.
	 *
	 * @return void
	 */
	public static function menu() {
		add_menu_page(
			'SEO & GEO Booster',
			'SEO & GEO',
			'manage_options',
			'mdi-seo-geo',
			array( __CLASS__, 'render' ),
			'dashicons-chart-line',
			81
		);
	}

	/**
	 * Pagina di riepilogo.
	 *
	 * @return void
	 */
	public static function render() {
		$meta  = mdi_seo_geo_data( 'meta-map' );
		$links = mdi_seo_geo_data( 'internal-links' );
		$rel   = mdi_seo_geo_data( 'related' );

		echo '<div class="wrap"><h1>MDI SEO &amp; GEO Booster</h1>';
		echo '<p>Correzioni applicate automaticamente a ogni pagina servita dal sito.</p><table class="widefat striped" style="max-width:820px">';

		$righe = array(
			'Contenuti con title e meta description ottimizzate' => count( $meta ),
			'Keyword mappate per i link interni automatici'      => count( $links ),
			'Articoli con blocco "Approfondimenti correlati"'    => count( $rel ),
			'Dati strutturati JSON-LD'                           => 'attivi su tutte le pagine',
			'Direttive per i crawler AI'                         => 'robots.txt + /llms.txt + /ai.txt',
			'Immagini: alt, lazy loading, decoding'              => 'corretti a runtime',
			'Link esterni: nofollow e noopener'                  => 'applicati ai domini configurati',
		);

		foreach ( $righe as $etichetta => $valore ) {
			printf( '<tr><td><strong>%s</strong></td><td>%s</td></tr>', esc_html( $etichetta ), esc_html( (string) $valore ) );
		}

		echo '</table>';
		echo '<h2>Verifiche consigliate</h2><ol>';
		echo '<li>Apri <a href="' . esc_url( home_url( '/llms.txt' ) ) . '" target="_blank" rel="noopener">/llms.txt</a> e verifica che risponda.</li>';
		echo '<li>Controlla i dati strutturati con il <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Rich Results Test</a>.</li>';
		echo '<li>Invia la sitemap in Google Search Console e chiedi la reindicizzazione delle pagine servizio.</li>';
		echo '</ol></div>';
	}
}
