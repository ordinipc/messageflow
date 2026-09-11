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
		add_action( 'admin_post_mdi_analizza', array( __CLASS__, 'analizza' ) );
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
			'azienda.telefono'               => 'telefono',
			'azienda.partitaIva'             => 'partita IVA',
			'azienda.indirizzo.via'          => 'indirizzo',
			'azienda.indirizzo.cap'          => 'CAP',
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

		$dal_gestionale = (bool) get_option( MDI_Api::OPZIONE_CONFIG, false );

		printf(
			'<div class="notice notice-warning"><p><strong>MDI SEO &amp; GEO Booster:</strong> dati aziendali mancanti: %s. '
				. 'Senza questi valori lo schema LocalBusiness resta incompleto e il posizionamento locale non decolla.</p><p>%s</p></div>',
			esc_html( implode( ', ', $mancanti ) ),
			$dal_gestionale
				? 'I dati sono arrivati dal gestionale, ma questi campi erano vuoti: compilali nelle Impostazioni del gestionale e premi Salva.'
				: 'Compila i dati nelle <strong>Impostazioni del gestionale</strong> e premi Salva: arrivano qui da soli. '
					. 'Se non succede nulla, controlla che in quelle impostazioni siano inseriti indirizzo del sito e token (li trovi qui sotto, in SEO &amp; GEO).'
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

		self::sezione_dati_aziendali();

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
	 * Stato dei dati aziendali: da dove arrivano e quali mancano.
	 *
	 * @return void
	 */
	public static function sezione_dati_aziendali() {
		$dal_gestionale = (bool) get_option( MDI_Api::OPZIONE_CONFIG, false );

		echo '<h2>Dati aziendali</h2>';

		printf(
			'<p>Origine: <strong>%s</strong></p>',
			$dal_gestionale
				? 'inviati dal gestionale'
				: 'nessun invio ricevuto — in uso il file incluso nello zip, che è solo una fotografia del momento in cui il plugin è stato generato'
		);

		$campi = array(
			'Telefono'               => 'azienda.telefono',
			'Partita IVA'            => 'azienda.partitaIva',
			'Indirizzo'              => 'azienda.indirizzo.via',
			'CAP'                    => 'azienda.indirizzo.cap',
			'Città'                  => 'azienda.indirizzo.citta',
			'Scheda Google Business' => 'azienda.profili.googleBusiness',
			'Email'                  => 'azienda.email',
		);

		echo '<table class="widefat striped" style="max-width:820px"><tbody>';

		foreach ( $campi as $etichetta => $percorso ) {
			$valore = mdi_seo_geo_cfg( $percorso );

			printf(
				'<tr><td style="width:220px"><strong>%s</strong></td><td>%s</td></tr>',
				esc_html( $etichetta ),
				$valore
					? '<code>' . esc_html( $valore ) . '</code>'
					: '<span style="color:#b32d2e">mancante</span>'
			);
		}

		printf(
			'<tr><td><strong>Versione del plugin</strong></td><td><code>%s</code></td></tr>',
			esc_html( MDI_SEO_GEO_VERSION )
		);

		echo '</tbody></table>';

		echo '<p class="description">Questi valori si compilano una volta sola nelle Impostazioni del gestionale: '
			. 'a ogni salvataggio vengono rispediti qui, subito. Non serve reinstallare il plugin.</p>';
	}

	/**
	 * Indirizzo che il gestionale ha comunicato per avviare una analisi.
	 *
	 * @return string Vuoto finché il gestionale non ha mai salvato le impostazioni.
	 */
	public static function url_analisi() {
		$configurazione = get_option( MDI_Api::OPZIONE_CONFIG, array() );
		$url            = is_array( $configurazione ) ? ( $configurazione['analisi']['url'] ?? '' ) : '';

		return preg_match( '~^https?://~i', (string) $url ) ? (string) $url : '';
	}

	/**
	 * Chiede al gestionale una nuova analisi e torna indietro con l esito.
	 *
	 * Il sito non viene toccato: si rilegge soltanto, per ricalcolare il
	 * punteggio dopo le correzioni applicate.
	 *
	 * @return void
	 */
	public static function analizza() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mdi_analizza' ) ) {
			wp_die( 'Operazione non consentita.' );
		}

		$url = self::url_analisi();

		if ( '' === $url ) {
			wp_safe_redirect( admin_url( 'admin.php?page=mdi-seo-geo&analisi=assente' ) );
			exit;
		}

		$risposta = wp_remote_get( $url, array( 'timeout' => 300 ) );

		if ( is_wp_error( $risposta ) ) {
			wp_safe_redirect(
				admin_url( 'admin.php?page=mdi-seo-geo&analisi=errore&messaggio=' . rawurlencode( $risposta->get_error_message() ) )
			);
			exit;
		}

		$dati = json_decode( wp_remote_retrieve_body( $risposta ), true );

		if ( ! is_array( $dati ) || empty( $dati['ok'] ) ) {
			$messaggio = is_array( $dati ) && ! empty( $dati['errore'] ) ? $dati['errore'] : 'Il gestionale non ha risposto come previsto.';

			wp_safe_redirect( admin_url( 'admin.php?page=mdi-seo-geo&analisi=errore&messaggio=' . rawurlencode( $messaggio ) ) );
			exit;
		}

		wp_safe_redirect(
			admin_url(
				'admin.php?page=mdi-seo-geo&analisi=fatta'
				. '&punteggio=' . (int) $dati['punteggio']
				. '&variazione=' . rawurlencode( (string) ( $dati['variazione'] ?? '' ) )
				. '&problemi=' . (int) ( $dati['problemi'] ?? 0 )
				. '&scheda=' . rawurlencode( (string) ( $dati['scheda'] ?? '' ) )
			)
		);
		exit;
	}

	/**
	 * Pulsante "Analizza adesso" e riepilogo dell ultima risposta.
	 *
	 * @return void
	 */
	public static function sezione_analisi() {
		$url = self::url_analisi();

		echo '<h2>Ricalcolare il punteggio</h2>';

		if ( '' === $url ) {
			echo '<p>Apri le Impostazioni del gestionale e premi <strong>Salva</strong> una volta: '
				. 'da quel momento compare qui il pulsante per far ripartire l analisi.</p>';
			return;
		}

		$esito = isset( $_GET['analisi'] ) ? sanitize_text_field( wp_unslash( $_GET['analisi'] ) ) : '';

		if ( 'fatta' === $esito ) {
			$variazione = isset( $_GET['variazione'] ) ? sanitize_text_field( wp_unslash( $_GET['variazione'] ) ) : '';
			$segno      = ( '' === $variazione ) ? 'prima analisi' : ( ( (int) $variazione > 0 ? '+' : '' ) . (int) $variazione . ' rispetto alla volta scorsa' );

			printf(
				'<div class="notice notice-success"><p>Punteggio: <strong>%d/100</strong> (%s) &mdash; %d problemi aperti. <a href="%s" target="_blank" rel="noopener">Apri la scheda nel gestionale</a></p></div>',
				(int) ( $_GET['punteggio'] ?? 0 ),
				esc_html( $segno ),
				(int) ( $_GET['problemi'] ?? 0 ),
				esc_url( wp_unslash( $_GET['scheda'] ?? '' ) )
			);
		} elseif ( 'errore' === $esito ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( sanitize_text_field( wp_unslash( $_GET['messaggio'] ?? 'Analisi non riuscita.' ) ) )
			);
		} elseif ( 'assente' === $esito ) {
			echo '<div class="notice notice-error"><p>Il gestionale non ha comunicato nessun indirizzo di analisi.</p></div>';
		}

		echo '<p>Rilegge il sito cosi com e adesso e ricalcola il punteggio. Non modifica nulla. '
			. 'Puo impiegare qualche minuto sui siti grandi, e si puo lanciare una volta ogni due minuti.</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="mdi_analizza">';
		wp_nonce_field( 'mdi_analizza' );
		echo '<p><button class="button button-primary">Analizza adesso</button></p>';
		echo '</form>';
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

		self::sezione_analisi();

		self::sezione_collegamento();

		echo '<h2>Verifiche consigliate</h2><ol>';
		echo '<li>Apri <a href="' . esc_url( home_url( '/llms.txt' ) ) . '" target="_blank" rel="noopener">/llms.txt</a> e verifica che risponda.</li>';
		echo '<li>Controlla i dati strutturati con il <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Rich Results Test</a>.</li>';
		echo '<li>Invia la sitemap in Google Search Console e chiedi la reindicizzazione delle pagine servizio.</li>';
		echo '</ol></div>';
	}
}
