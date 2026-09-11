<?php
/**
 * Simulazione minima di WordPress per collaudare il codice reale del plugin.
 *
 * Non è un finto servizio HTTP: qui vengono eseguite le classi vere del plugin,
 * con le funzioni di WordPress sostituite da versioni in memoria. Serve a far
 * emergere gli errori di logica che una simulazione di rete non intercetta.
 *
 * @package SeoGeoAudit
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MDI_PLUGIN_FILE', dirname( __DIR__ ) . '/plugin-wordpress/mdi-seo-geo-booster/mdi-seo-geo-booster.php' );

/** @var array Stato in memoria che sostituisce il database di WordPress. */
$GLOBALS['wp'] = array(
	'post'     => array(),
	'meta'     => array(),
	'opzioni'  => array(),
	'termini'  => array(),
	'allegati' => array(),
	'azioni'   => array(),
);

/**
 * Crea un articolo di prova.
 *
 * @param int    $id        Identificativo.
 * @param string $titolo    Titolo.
 * @param string $contenuto Contenuto.
 * @return void
 */
function stub_crea_post( $id, $titolo, $contenuto = '' ) {
	$GLOBALS['wp']['post'][ $id ] = (object) array(
		'ID'                => $id,
		'post_title'        => $titolo,
		'post_content'      => $contenuto,
		'post_excerpt'      => '',
		'post_status'       => 'publish',
		'post_type'         => 'post',
		'post_author'       => 1,
		'post_name'         => 'articolo-' . $id,
		'post_date'         => '2026-01-01 10:00:00',
		'post_date_gmt'     => '2026-01-01 09:00:00',
		'post_modified_gmt' => '2026-02-01 09:00:00',
		'post_parent'       => 0,
		'comment_status'    => 'closed',
	);
}

// --- Hook e REST -----------------------------------------------------------

function add_action( $hook, $callback, $priorita = 10, $argomenti = 1 ) {
	$GLOBALS['wp']['azioni'][ $hook ][] = $callback;
}

function add_filter( $hook, $callback, $priorita = 10, $argomenti = 1 ) {
	$GLOBALS['wp']['azioni'][ $hook ][] = $callback;
}

function register_rest_route( $spazio, $rotta, $opzioni ) {
	$GLOBALS['wp']['rotte'][ $spazio . $rotta ] = $opzioni;
}

function rest_ensure_response( $dati ) {
	return $dati;
}

function rest_url( $percorso = '' ) {
	return 'https://esempio.it/wp-json/' . ltrim( $percorso, '/' );
}

class WP_Error {

	public $codice;
	public $messaggio;
	public $dati;

	public function __construct( $codice = '', $messaggio = '', $dati = array() ) {
		$this->codice    = $codice;
		$this->messaggio = $messaggio;
		$this->dati      = $dati;
	}

	public function get_error_message() {
		return $this->messaggio;
	}
}

function is_wp_error( $cosa ) {
	return $cosa instanceof WP_Error;
}

class WP_REST_Request {

	private $parametri;
	private $intestazioni;

	public function __construct( array $parametri = array(), array $intestazioni = array() ) {
		$this->parametri    = $parametri;
		$this->intestazioni = array_change_key_case( $intestazioni );
	}

	public function get_param( $nome ) {
		return $this->parametri[ $nome ] ?? null;
	}

	public function get_header( $nome ) {
		return $this->intestazioni[ strtolower( $nome ) ] ?? '';
	}
}

class WP_Post {} // Usata solo nei controlli instanceof.

// --- Post e meta -----------------------------------------------------------

function get_post( $id ) {
	return $GLOBALS['wp']['post'][ (int) $id ] ?? null;
}

function get_the_title( $id ) {
	$post = get_post( $id );

	return $post ? $post->post_title : '';
}

function get_post_field( $campo, $id ) {
	$post = get_post( $id );

	return $post ? ( $post->$campo ?? '' ) : '';
}

function get_post_meta( $id, $chiave, $singolo = false ) {
	return $GLOBALS['wp']['meta'][ (int) $id ][ $chiave ] ?? '';
}

function update_post_meta( $id, $chiave, $valore ) {
	$GLOBALS['wp']['meta'][ (int) $id ][ $chiave ] = $valore;

	return true;
}

function delete_post_meta( $id, $chiave ) {
	unset( $GLOBALS['wp']['meta'][ (int) $id ][ $chiave ] );

	return true;
}

function wp_update_post( $dati, $errore = false ) {
	$id = (int) ( $dati['ID'] ?? 0 );

	if ( ! isset( $GLOBALS['wp']['post'][ $id ] ) ) {
		return $errore ? new WP_Error( 'invalid_post', 'Articolo inesistente' ) : 0;
	}

	foreach ( $dati as $campo => $valore ) {
		if ( 'ID' !== $campo ) {
			$GLOBALS['wp']['post'][ $id ]->$campo = $valore;
		}
	}

	return $id;
}

function wp_insert_post( $dati, $errore = false ) {
	$id = max( array_keys( $GLOBALS['wp']['post'] ) ) + 1;

	$GLOBALS['wp']['post'][ $id ] = (object) array_merge(
		array( 'ID' => $id, 'post_title' => '', 'post_content' => '', 'post_excerpt' => '', 'post_status' => 'draft', 'post_type' => 'post', 'post_author' => 1 ),
		$dati
	);

	return $id;
}

function get_posts( $argomenti ) {
	$trovati = array();

	foreach ( $GLOBALS['wp']['post'] as $id => $post ) {
		if ( isset( $argomenti['post_status'] ) && $post->post_status !== $argomenti['post_status'] ) {
			continue;
		}

		if ( isset( $argomenti['meta_key'] ) ) {
			$valore = get_post_meta( $id, $argomenti['meta_key'], true );

			if ( (string) $valore !== (string) $argomenti['meta_value'] ) {
				continue;
			}
		}

		$trovati[] = $id;
	}

	return $trovati;
}

function wp_delete_post( $id, $forza = false ) {
	unset( $GLOBALS['wp']['post'][ (int) $id ] );

	return true;
}

function wp_trash_post( $id ) {
	if ( ! isset( $GLOBALS['wp']['post'][ (int) $id ] ) ) {
		return false;
	}

	$GLOBALS['wp']['post'][ (int) $id ]->post_status = 'trash';

	return true;
}

function wp_count_posts( $tipo = 'post' ) {
	$n = 0;

	foreach ( $GLOBALS['wp']['post'] as $post ) {
		if ( $post->post_type === $tipo && 'publish' === $post->post_status ) {
			$n++;
		}
	}

	return (object) array( 'publish' => $n );
}

// --- Tassonomie ------------------------------------------------------------

function get_term_by( $campo, $valore, $tassonomia ) {
	foreach ( $GLOBALS['wp']['termini'] as $id => $nome ) {
		if ( $nome === $valore ) {
			return (object) array( 'term_id' => $id, 'name' => $nome );
		}
	}

	return false;
}

function wp_insert_term( $nome, $tassonomia ) {
	$id = count( $GLOBALS['wp']['termini'] ) + 10;
	$GLOBALS['wp']['termini'][ $id ] = $nome;

	return array( 'term_id' => $id );
}

function wp_get_post_categories( $id ) {
	return $GLOBALS['wp']['meta'][ (int) $id ]['__categorie'] ?? array();
}

function wp_set_post_categories( $id, $categorie, $aggiungi = false ) {
	$attuali = $aggiungi ? wp_get_post_categories( $id ) : array();
	$GLOBALS['wp']['meta'][ (int) $id ]['__categorie'] = array_values( array_unique( array_merge( $attuali, $categorie ) ) );

	return true;
}

// --- Opzioni e utilità -----------------------------------------------------

function get_option( $nome, $predefinito = false ) {
	return $GLOBALS['wp']['opzioni'][ $nome ] ?? $predefinito;
}

function update_option( $nome, $valore, $autoload = true ) {
	$GLOBALS['wp']['opzioni'][ $nome ] = $valore;

	return true;
}

function delete_option_stub( $nome ) {
	unset( $GLOBALS['wp']['opzioni'][ $nome ] );
}

function home_url( $percorso = '' ) {
	return 'https://esempio.it' . $percorso;
}

function get_bloginfo( $cosa = '' ) {
	$valori = array( 'name' => 'Sito di prova', 'version' => '6.7.1', 'description' => 'Descrizione del sito' );

	return $valori[ $cosa ] ?? '';
}

function admin_url( $percorso = '' ) {
	return 'https://esempio.it/wp-admin/' . $percorso;
}

// --- Transient (la cache a scadenza di WordPress) --------------------------

define( 'HOUR_IN_SECONDS', 3600 );

function set_transient( $chiave, $valore, $durata = 0 ) {
	$GLOBALS['wp']['transient'][ $chiave ] = array( 'valore' => $valore, 'scade' => $durata ? time() + $durata : 0 );

	return true;
}

function get_transient( $chiave ) {
	$voce = $GLOBALS['wp']['transient'][ $chiave ] ?? null;

	if ( ! $voce ) {
		return false;
	}

	if ( $voce['scade'] && $voce['scade'] < time() ) {
		unset( $GLOBALS['wp']['transient'][ $chiave ] );

		return false;
	}

	return $voce['valore'];
}

function delete_transient( $chiave ) {
	unset( $GLOBALS['wp']['transient'][ $chiave ] );

	return true;
}

function untrailingslashit( $stringa ) {
	return rtrim( $stringa, '/' );
}

function wp_unslash( $valore ) {
	return is_array( $valore ) ? array_map( 'wp_unslash', $valore ) : stripslashes( (string) $valore );
}

function sanitize_text_field( $valore ) {
	return trim( strip_tags( (string) $valore ) );
}

function sanitize_file_name( $nome ) {
	return preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $nome );
}

function wp_kses_post( $html ) {
	return $html;
}

function esc_html( $testo ) {
	return htmlspecialchars( (string) $testo, ENT_QUOTES );
}

function wp_json_encode( $dati, $opzioni = 0 ) {
	return json_encode( $dati, $opzioni );
}

function wp_parse_url( $url, $componente = -1 ) {
	return parse_url( $url, $componente );
}

function esc_url_raw( $url ) {
	return $url;
}

function wp_trim_words( $testo, $quante, $fine = '' ) {
	$parole = preg_split( '/\s+/u', (string) $testo );

	return implode( ' ', array_slice( $parole, 0, $quante ) ) . ( count( $parole ) > $quante ? $fine : '' );
}

function wp_strip_all_tags( $testo ) {
	return trim( strip_tags( (string) $testo ) );
}

// --- Media -----------------------------------------------------------------

function wp_upload_bits( $nome, $deprecato, $contenuto ) {
	$cartella = sys_get_temp_dir() . '/stub-uploads';

	if ( ! is_dir( $cartella ) ) {
		mkdir( $cartella, 0775, true );
	}

	$percorso = $cartella . '/' . $nome;
	file_put_contents( $percorso, $contenuto );

	return array( 'file' => $percorso, 'url' => 'https://esempio.it/uploads/' . $nome, 'error' => false );
}

function wp_insert_attachment( $dati, $file, $genitore ) {
	$id = 900 + count( $GLOBALS['wp']['allegati'] );
	$GLOBALS['wp']['allegati'][ $id ] = array( 'file' => $file, 'genitore' => $genitore ) + $dati;

	return $id;
}

function wp_generate_attachment_metadata( $id, $file ) {
	return array( 'file' => $file );
}

function wp_update_attachment_metadata( $id, $dati ) {
	return true;
}

function set_post_thumbnail( $id, $allegato ) {
	return update_post_meta( $id, '_thumbnail_id', $allegato );
}

// --- Funzioni di contorno usate dal resto del plugin ------------------------

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
	return 'https://esempio.it/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function register_activation_hook( $file, $callback ) {}

function register_deactivation_hook( $file, $callback ) {}

function flush_rewrite_rules() {}

function add_shortcode( $nome, $callback ) {
	$GLOBALS['wp']['shortcode'][ $nome ] = $callback;
}

function add_menu_page() {}

function add_rewrite_rule( $regola, $destinazione, $priorita = 'bottom' ) {}

function get_query_var( $nome ) {
	return $GLOBALS['wp']['query_var'][ $nome ] ?? '';
}

function status_header( $codice ) {}

function current_user_can( $permesso ) {
	return true;
}

function check_admin_referer( $azione, $campo = '_wpnonce' ) {
	return true;
}

function wp_nonce_field( $azione, $campo = '_wpnonce', $referer = true, $stampa = true ) {
	return '<input type="hidden" name="' . $campo . '" value="nonce">';
}

function wp_die( $messaggio = '' ) {
	throw new RuntimeException( 'wp_die: ' . $messaggio );
}

function wp_safe_redirect( $url, $stato = 302 ) {
	$GLOBALS['wp']['redirect'] = $url;

	return true;
}

function esc_attr( $valore ) {
	return htmlspecialchars( (string) $valore, ENT_QUOTES );
}

function esc_url( $url ) {
	return $url;
}

function sanitize_title( $titolo ) {
	return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', (string) $titolo ) );
}

function is_admin() {
	return false;
}

function is_404() {
	return ! empty( $GLOBALS['wp']['e404'] );
}

function is_singular( $tipo = '' ) {
	if ( empty( $GLOBALS['wp']['singolo'] ) ) {
		return false;
	}

	if ( '' === $tipo ) {
		return true;
	}

	return ( $GLOBALS['wp']['tipo_singolo'] ?? 'post' ) === $tipo;
}

function in_the_loop() {
	return true;
}

function is_main_query() {
	return true;
}

function get_queried_object_id() {
	return (int) ( $GLOBALS['wp']['singolo'] ?? 0 );
}

function get_permalink( $id = 0 ) {
	$id = $id ?: get_queried_object_id();

	return 'https://esempio.it/articolo-' . $id . '/';
}

function get_the_category( $id = 0 ) {
	return array();
}

function get_category_link( $id ) {
	return 'https://esempio.it/category/esempio/';
}

function get_the_date( $formato = '', $id = 0 ) {
	return '2026-01-01T10:00:00+01:00';
}

function get_the_modified_date( $formato = '', $id = 0 ) {
	return '2026-02-01T10:00:00+01:00';
}

function get_the_excerpt( $id = 0 ) {
	return 'Estratto di prova.';
}

function has_post_thumbnail( $id = null ) {
	return false;
}

function get_the_post_thumbnail_url( $id = null, $misura = 'full' ) {
	return '';
}

function wp_get_document_title() {
	return 'Titolo del documento';
}

function get_header() {}

// --- Utenti, tassonomie, menu e allegati -----------------------------------

function get_users( $argomenti = array() ) {
	return array(
		(object) array( 'ID' => 1, 'user_login' => 'redazione', 'display_name' => 'Redazione', 'user_email' => 'redazione@esempio.it' ),
	);
}

function get_user_meta( $id, $chiave, $singolo = false ) {
	$valori = array( 'first_name' => 'Redazione', 'last_name' => '' );

	return $valori[ $chiave ] ?? '';
}

function get_the_author_meta( $campo, $id = 0 ) {
	return 'Redazione';
}

function get_the_tags( $id = 0 ) {
	return array( (object) array( 'slug' => 'seo', 'name' => 'SEO' ) );
}

function wp_get_nav_menus( $argomenti = array() ) {
	return array( (object) array( 'term_id' => 7, 'name' => 'Principale', 'slug' => 'principale' ) );
}

function wp_get_nav_menu_items( $menu, $argomenti = array() ) {
	return array(
		(object) array( 'ID' => 900, 'title' => 'Home', 'url' => 'https://esempio.it/', 'menu_item_parent' => '0', 'object_id' => '0', 'type' => 'custom', 'object' => 'custom' ),
		(object) array( 'ID' => 901, 'title' => 'Servizi', 'url' => 'https://esempio.it/servizi/', 'menu_item_parent' => '0', 'object_id' => '101', 'type' => 'post_type', 'object' => 'page' ),
	);
}

function get_attached_file( $id ) {
	return '';
}

function wp_get_attachment_metadata( $id ) {
	return array( 'filesize' => 120000, 'width' => 1200, 'height' => 800 );
}

function wp_get_attachment_url( $id ) {
	return 'https://esempio.it/wp-content/uploads/immagine-' . (int) $id . '.jpg';
}

function wp_get_post_parent_id( $id ) {
	$post = get_post( $id );

	return $post ? (int) ( $post->post_parent ?? 0 ) : 0;
}

// Si carica il plugin vero e proprio: costanti, funzioni e tutte le classi.
require_once MDI_PLUGIN_FILE;

