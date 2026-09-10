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
		add_action( 'admin_post_mdi_genera_token', array( __CLASS__, 'genera_token' ) );
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
	 * Rigenera il token e torna alla pagina del plugin.
	 *
	 * @return void
	 */
	public static function genera_token() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mdi_genera_token' ) ) {
			wp_die( 'Operazione non consentita.' );
		}

		MDI_Api::token( true );

		wp_safe_redirect( admin_url( 'admin.php?page=mdi-seo-geo&token=nuovo' ) );
		exit;
	}

	/**
	 * Blocco con il token e l indirizzo da incollare nel gestionale.
	 *
	 * @return void
	 */
	public static function sezione_collegamento() {
		$token = MDI_Api::token();

		echo '<h2>Collegamento con il gestionale</h2>';
		echo '<p>Incolla questi due valori nelle impostazioni dell applicazione SEO &amp; GEO Audit: '
			. 'da lì potrai applicare meta, bozze, categorie, redirect e immagini senza copiare e incollare.</p>';

		echo '<table class="widefat striped" style="max-width:820px"><tbody>';
		printf(
			'<tr><td style="width:180px"><strong>Indirizzo del sito</strong></td><td><code>%s</code></td></tr>',
			esc_html( untrailingslashit( home_url() ) )
		);
		printf(
			'<tr><td><strong>Token</strong></td><td><code style="user-select:all">%s</code></td></tr>',
			esc_html( $token )
		);
		printf(
			'<tr><td><strong>Endpoint</strong></td><td><code>%s</code></td></tr>',
			esc_html( rest_url( MDI_Api::NAMESPACE_API . '/stato' ) )
		);
		echo '</tbody></table>';

		printf(
			'<p><form method="post" action="%s" onsubmit="return confirm(\'Rigenerare il token? Il gestionale andrà ricollegato.\')">'
				. '%s<input type="hidden" name="action" value="mdi_genera_token">'
				. '<button type="submit" class="button">Genera un token nuovo</button></form></p>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'mdi_genera_token', '_wpnonce', true, false )
		);

		echo '<p class="description">Il token vale come una password: chi lo possiede può modificare meta e creare bozze. '
			. 'Rigeneralo se pensi sia stato esposto.</p>';
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

		self::sezione_collegamento();

		echo '<h2>Verifiche consigliate</h2><ol>';
		echo '<li>Apri <a href="' . esc_url( home_url( '/llms.txt' ) ) . '" target="_blank" rel="noopener">/llms.txt</a> e verifica che risponda.</li>';
		echo '<li>Controlla i dati strutturati con il <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Rich Results Test</a>.</li>';
		echo '<li>Invia la sitemap in Google Search Console e chiedi la reindicizzazione delle pagine servizio.</li>';
		echo '</ol></div>';
	}
}
