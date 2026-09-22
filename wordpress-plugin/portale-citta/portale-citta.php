<?php
/**
 * Plugin Name: Portale Città — shortcode
 * Description: Mostra dentro WordPress le pagine (o le singole sezioni) del portale città, con uno shortcode.
 * Version:     1.0.0
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * Text Domain: portale-citta
 */

defined( 'ABSPATH' ) || exit;

define( 'PCW_VERSIONE', '1.0.0' );
define( 'PCW_OPZIONI', 'portale_citta_opzioni' );

/* ---------------------------------------------------------------------------
 * Impostazioni
 * ------------------------------------------------------------------------- */

function pcw_opzioni() {
	$salvate = get_option( PCW_OPZIONI, array() );
	return wp_parse_args( is_array( $salvate ) ? $salvate : array(), array(
		'url'    => '',
		'minuti' => 60,
		'stile'  => 1,
	) );
}

/** Indirizzo del portale, senza barra finale. */
function pcw_base() {
	$o = pcw_opzioni();
	return untrailingslashit( trim( (string) $o['url'] ) );
}

add_action( 'admin_menu', 'pcw_menu' );
function pcw_menu() {
	add_options_page(
		__( 'Portale Città', 'portale-citta' ),
		__( 'Portale Città', 'portale-citta' ),
		'manage_options',
		'portale-citta',
		'pcw_schermata'
	);
}

add_action( 'admin_init', 'pcw_registra' );
function pcw_registra() {
	register_setting( 'pcw', PCW_OPZIONI, array( 'sanitize_callback' => 'pcw_pulisci' ) );
}

function pcw_pulisci( $valori ) {
	$valori = is_array( $valori ) ? $valori : array();
	return array(
		'url'    => esc_url_raw( untrailingslashit( trim( (string) ( $valori['url'] ?? '' ) ) ) ),
		'minuti' => max( 0, min( 1440, (int) ( $valori['minuti'] ?? 60 ) ) ),
		'stile'  => empty( $valori['stile'] ) ? 0 : 1,
	);
}

function pcw_schermata() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o = pcw_opzioni();

	// Prova di collegamento: dice subito se l'indirizzo è quello giusto.
	$prova = '';
	if ( '' !== pcw_base() ) {
		$r = wp_remote_get( pcw_base() . '/?incorpora=1', array( 'timeout' => 8 ) );
		if ( is_wp_error( $r ) ) {
			$prova = '<span style="color:#b00">✗ ' . esc_html( $r->get_error_message() ) . '</span>';
		} else {
			$codice = (int) wp_remote_retrieve_response_code( $r );
			$prova  = ( $codice >= 200 && $codice < 400 )
				? '<span style="color:#0a0">✓ Portale raggiunto.</span>'
				: '<span style="color:#b00">✗ Il portale risponde ' . $codice . '.</span>';
		}
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Portale Città', 'portale-citta' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'pcw' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pcw-url">Indirizzo del portale</label></th>
					<td>
						<input name="<?php echo esc_attr( PCW_OPZIONI ); ?>[url]" id="pcw-url" type="url"
							class="regular-text" value="<?php echo esc_attr( $o['url'] ); ?>"
							placeholder="https://www.tuosito.it/zone">
						<p class="description">
							Senza barra finale. È la cartella in cui hai caricato il portale.
							<?php echo wp_kses_post( $prova ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pcw-minuti">Durata della copia locale</label></th>
					<td>
						<input name="<?php echo esc_attr( PCW_OPZIONI ); ?>[minuti]" id="pcw-minuti" type="number"
							min="0" max="1440" value="<?php echo esc_attr( $o['minuti'] ); ?>" class="small-text"> minuti
						<p class="description">
							Per quanto tempo WordPress tiene da parte il contenuto senza richiederlo di nuovo.
							0 = chiede ogni volta (comodo mentre scrivi, pesante in pagina).
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Foglio di stile</th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( PCW_OPZIONI ); ?>[stile]" value="1"
								<?php checked( 1, (int) $o['stile'] ); ?>>
							Carica anche il foglio di stile del portale
						</label>
						<p class="description">
							Serve perché le sezioni si vedano come sul portale. Toglilo solo se preferisci
							rifare tu la grafica nel tema di WordPress.
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2>Come si usa</h2>
		<p>Nel portale, aprendo una pagina, trovi lo shortcode già pronto da copiare. Ha questa forma:</p>
		<p><code>[portale_citta citta="trapani" pagina="contatti"]</code> — tutta la pagina</p>
		<p><code>[portale_citta citta="trapani" pagina="contatti" sezione="modulo"]</code> — solo il modulo di contatto</p>
		<p><code>[portale_citta citta="trapani"]</code> — la pagina principale di quella città</p>
		<p class="description">
			Attenzione ai contenuti doppi: se incorpori una pagina intera, lo stesso testo esiste
			su due indirizzi e Google ne sceglie uno solo. Per questo conviene incorporare
			<strong>singole sezioni</strong> (il modulo, i recapiti, le card dei servizi) invece di pagine intere.
		</p>

		<h2>Svuota la copia locale</h2>
		<form method="post">
			<?php wp_nonce_field( 'pcw_svuota' ); ?>
			<button class="button" name="pcw_svuota" value="1" type="submit">Ricarica tutto dal portale</button>
		</form>
		<?php
		if ( isset( $_POST['pcw_svuota'] ) && check_admin_referer( 'pcw_svuota' ) ) {
			echo '<p><strong>' . (int) pcw_svuota_cache() . '</strong> contenuti ricaricati alla prossima visita.</p>';
		}
		?>
	</div>
	<?php
}

/** Butta via tutte le copie locali. */
function pcw_svuota_cache() {
	global $wpdb;
	$quante = (int) $wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pcw\\_%' OR option_name LIKE '_transient_timeout_pcw\\_%'"
	);
	return (int) floor( $quante / 2 );
}

/* ---------------------------------------------------------------------------
 * Lettura dal portale
 * ------------------------------------------------------------------------- */

/**
 * Chiede una pagina al portale e la tiene da parte.
 *
 * Ritorna l'array del portale, oppure un WP_Error con un messaggio già
 * scritto in italiano: lo shortcode lo mostra solo a chi può modificare.
 */
function pcw_leggi( $citta, $pagina, $sezione ) {
	$base = pcw_base();
	if ( '' === $base ) {
		return new WP_Error( 'pcw_no_url', 'Indirizzo del portale non impostato: Impostazioni → Portale Città.' );
	}

	$percorso = $base . '/' . rawurlencode( $citta ) . '/';
	if ( '' !== $pagina ) {
		$percorso .= rawurlencode( $pagina ) . '/';
	}
	$url = add_query_arg(
		array_filter( array( 'incorpora' => 1, 'sezione' => $sezione ) ),
		$percorso
	);

	$o      = pcw_opzioni();
	$minuti = (int) $o['minuti'];
	$chiave = 'pcw_' . md5( $url . '|' . PCW_VERSIONE );

	if ( $minuti > 0 ) {
		$copia = get_transient( $chiave );
		if ( is_array( $copia ) ) {
			return $copia;
		}
	}

	$r = wp_remote_get( $url, array( 'timeout' => 10 ) );
	if ( is_wp_error( $r ) ) {
		return new WP_Error( 'pcw_rete', 'Portale non raggiungibile: ' . $r->get_error_message() );
	}

	$codice = (int) wp_remote_retrieve_response_code( $r );
	$dati   = json_decode( wp_remote_retrieve_body( $r ), true );

	if ( ! is_array( $dati ) ) {
		return new WP_Error( 'pcw_risposta', 'Il portale ha risposto ' . $codice . ', ma non con un contenuto leggibile. Controlla l\'indirizzo.' );
	}
	if ( empty( $dati['ok'] ) ) {
		$messaggio = isset( $dati['errore'] ) ? $dati['errore'] : 'Pagina non trovata sul portale.';
		if ( ! empty( $dati['sezioni'] ) ) {
			$messaggio .= ' Sezioni disponibili: ' . implode( ', ', array_map( 'sanitize_text_field', $dati['sezioni'] ) ) . '.';
		}
		return new WP_Error( 'pcw_contenuto', $messaggio );
	}

	if ( $minuti > 0 ) {
		set_transient( $chiave, $dati, $minuti * MINUTE_IN_SECONDS );
	}
	return $dati;
}

/* ---------------------------------------------------------------------------
 * Shortcode
 * ------------------------------------------------------------------------- */

add_shortcode( 'portale_citta', 'pcw_shortcode' );
function pcw_shortcode( $attributi ) {
	$a = shortcode_atts( array(
		'citta'   => '',
		'pagina'  => '',
		'sezione' => '',
		'titolo'  => '',
	), $attributi, 'portale_citta' );

	$citta = sanitize_title( $a['citta'] );
	if ( '' === $citta ) {
		return pcw_avviso( 'Manca l\'attributo citta: [portale_citta citta="trapani"].' );
	}

	$dati = pcw_leggi( $citta, sanitize_title( $a['pagina'] ), sanitize_key( $a['sezione'] ) );
	if ( is_wp_error( $dati ) ) {
		return pcw_avviso( $dati->get_error_message() );
	}

	pcw_accoda_stile( $dati );

	$html = '';
	if ( 'si' === strtolower( (string) $a['titolo'] ) || '1' === (string) $a['titolo'] ) {
		$html .= '<h2 class="pcw-titolo">' . esc_html( $dati['h1'] ) . '</h2>';
	}

	// Il portale produce già HTML controllato, ma si ripassa comunque dal
	// filtro: se un giorno l'indirizzo puntasse altrove, da qui non entra
	// uno <script>.
	$html .= wp_kses( $dati['html'], pcw_tag_ammessi() );

	return '<div class="pcw-incorpora ' . esc_attr( $dati['classi'] ?? '' ) . '">' . $html . '</div>';
}

/**
 * Tag ammessi nel contenuto che arriva dal portale.
 *
 * wp_kses_post() da solo non basta: butterebbe via il modulo di contatto
 * (form, input, select) e la mappa (iframe). Qui si aggiungono quelli, e
 * niente altro — <script> resta fuori.
 */
function pcw_tag_ammessi() {
	$ammessi = wp_kses_allowed_html( 'post' );

	$comuni = array( 'id' => true, 'class' => true, 'style' => true, 'title' => true, 'hidden' => true,
		'role' => true, 'tabindex' => true, 'aria-hidden' => true, 'aria-label' => true,
		'aria-labelledby' => true, 'aria-describedby' => true, 'aria-expanded' => true,
		'aria-controls' => true, 'aria-current' => true, 'data-*' => true );

	$ammessi['form']     = $comuni + array( 'action' => true, 'method' => true, 'enctype' => true, 'novalidate' => true, 'target' => true );
	$ammessi['input']    = $comuni + array( 'type' => true, 'name' => true, 'value' => true, 'placeholder' => true,
		'required' => true, 'checked' => true, 'disabled' => true, 'readonly' => true, 'autocomplete' => true,
		'min' => true, 'max' => true, 'minlength' => true, 'maxlength' => true, 'step' => true, 'pattern' => true, 'inputmode' => true );
	$ammessi['textarea'] = $comuni + array( 'name' => true, 'rows' => true, 'cols' => true, 'placeholder' => true,
		'required' => true, 'maxlength' => true, 'autocomplete' => true );
	$ammessi['select']   = $comuni + array( 'name' => true, 'required' => true, 'multiple' => true, 'size' => true );
	$ammessi['option']   = $comuni + array( 'value' => true, 'selected' => true, 'disabled' => true );
	$ammessi['optgroup'] = $comuni + array( 'label' => true );
	$ammessi['label']    = $comuni + array( 'for' => true );
	$ammessi['button']   = $comuni + array( 'type' => true, 'name' => true, 'value' => true, 'disabled' => true );
	$ammessi['fieldset'] = $comuni;
	$ammessi['legend']   = $comuni;

	$ammessi['iframe'] = $comuni + array( 'src' => true, 'width' => true, 'height' => true, 'loading' => true,
		'allowfullscreen' => true, 'referrerpolicy' => true, 'frameborder' => true, 'allow' => true );

	$ammessi['svg']    = $comuni + array( 'viewbox' => true, 'viewBox' => true, 'width' => true, 'height' => true,
		'fill' => true, 'stroke' => true, 'xmlns' => true, 'focusable' => true );
	$ammessi['path']   = array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true );
	$ammessi['circle'] = array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'class' => true );
	$ammessi['g']      = array( 'fill' => true, 'class' => true );

	$ammessi['details'] = $comuni + array( 'open' => true );
	$ammessi['summary'] = $comuni;
	$ammessi['section'] = $comuni;
	$ammessi['article'] = $comuni;
	$ammessi['aside']   = $comuni;
	$ammessi['nav']     = $comuni;
	$ammessi['picture'] = $comuni;
	$ammessi['source']  = $comuni + array( 'srcset' => true, 'type' => true, 'media' => true );

	// Tag di testo che il portale usa e che non è detto siano nella lista
	// di WordPress: meglio metterli noi che scoprire un giorno che una
	// recensione ha perso il nome di chi l'ha scritta.
	foreach ( array( 'cite', 'q', 'abbr', 'code', 'pre', 'mark', 'sub', 'sup', 's', 'del', 'ins',
		'dl', 'dt', 'dd', 'address', 'hgroup', 'header', 'footer', 'main' ) as $tag ) {
		$ammessi[ $tag ] = isset( $ammessi[ $tag ] ) ? $ammessi[ $tag ] + $comuni : $comuni;
	}

	// E gli attributi comuni valgono ovunque: senza, sparirebbero gli
	// aria- che servono a chi naviga con la sintesi vocale e i data- che
	// fanno partire le animazioni.
	foreach ( $ammessi as $tag => $attributi ) {
		if ( is_array( $attributi ) ) {
			$ammessi[ $tag ] = $attributi + $comuni;
		}
	}

	return $ammessi;
}

/** Messaggio visibile solo a chi può sistemare il problema. */
function pcw_avviso( $testo ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return '';
	}
	return '<p style="padding:10px 14px;border-left:3px solid #b00;background:#fff4f4;font:14px/1.5 system-ui,sans-serif">'
		. '<strong>Portale Città:</strong> ' . esc_html( $testo ) . '</p>';
}

/** Foglio di stile e variabili dei colori, una volta sola per pagina. */
function pcw_accoda_stile( $dati ) {
	static $fatto = false;
	$o = pcw_opzioni();
	if ( $fatto || empty( $o['stile'] ) || empty( $dati['css'] ) ) {
		return;
	}
	$fatto = true;

	wp_enqueue_style( 'portale-citta', $dati['css'], array(), null );
	if ( ! empty( $dati['variabili'] ) ) {
		wp_add_inline_style( 'portale-citta', wp_strip_all_tags( $dati['variabili'] ) );
	}
	if ( ! empty( $dati['js'] ) ) {
		wp_enqueue_script( 'portale-citta', $dati['js'], array(), null, true );
	}
}

/* ---------------------------------------------------------------------------
 * Blocco per l'editor a blocchi
 * ------------------------------------------------------------------------- */

add_action( 'init', 'pcw_blocco' );
function pcw_blocco() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	register_block_type( 'portale-citta/incorpora', array(
		'api_version'     => 2,
		'render_callback' => function ( $attributi ) {
			return pcw_shortcode( array(
				'citta'   => $attributi['citta'] ?? '',
				'pagina'  => $attributi['pagina'] ?? '',
				'sezione' => $attributi['sezione'] ?? '',
			) );
		},
		'attributes'      => array(
			'citta'   => array( 'type' => 'string', 'default' => '' ),
			'pagina'  => array( 'type' => 'string', 'default' => '' ),
			'sezione' => array( 'type' => 'string', 'default' => '' ),
		),
	) );
}
