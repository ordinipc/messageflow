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

function get_post_meta( $id, $chiave = '', $singolo = false ) {
	$tutte = $GLOBALS['wp']['meta'][ (int) $id ] ?? array();

	// Come WordPress: senza chiave restituisce tutte le meta, ognuna dentro
	// a un array. Il gestionale lo usa per leggere le impostazioni del tema,
	// di cui non conosce i nomi in anticipo.
	if ( '' === $chiave ) {
		$fuori = array();

		foreach ( $tutte as $nome => $valore ) {
			$fuori[ $nome ] = array( is_scalar( $valore ) ? $valore : serialize( $valore ) );
		}

		return $fuori;
	}

	return $tutte[ $chiave ] ?? '';
}

function maybe_unserialize( $valore ) {
	if ( ! is_string( $valore ) ) {
		return $valore;
	}

	$dati = @unserialize( $valore );

	return false === $dati && 'b:0;' !== $valore ? $valore : $dati;
}

function wp_get_post_revisions( $id ) {
	return $GLOBALS['wp']['revisioni'][ (int) $id ] ?? array();
}

function update_post_meta( $id, $chiave, $valore ) {
	// Come WordPress: le barre aggiunte da wp_slash vengono tolte qui. Se lo
	// stub le tenesse, il JSON di Elementor arriverebbe pieno di barre e la
	// verifica direbbe il falso in un senso o nell altro.
	$GLOBALS['wp']['meta'][ (int) $id ][ $chiave ] = is_string( $valore ) ? stripslashes( $valore ) : $valore;

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
	$tipi  = (array) ( $argomenti['post_type'] ?? array( 'post' ) );
	$stati = (array) ( $argomenti['post_status'] ?? array( 'publish' ) );
	$trovati = array();

	foreach ( $GLOBALS['wp']['post'] as $id => $post ) {
		if ( ! in_array( $post->post_status, $stati, true ) && ! in_array( 'any', $stati, true ) ) {
			continue;
		}

		if ( ! in_array( $post->post_type, $tipi, true ) && ! in_array( 'any', $tipi, true ) ) {
			continue;
		}

		// Ricerca per slug: è così che si trova un contenuto che non è più
		// pubblico, e quindi non ha più un indirizzo valido.
		if ( isset( $argomenti['name'] ) && ( $post->post_name ?? '' ) !== $argomenti['name'] ) {
			continue;
		}

		if ( isset( $argomenti['post_mime_type'] ) ) {
			$mime = (array) $argomenti['post_mime_type'];

			if ( ! in_array( (string) ( $post->post_mime_type ?? '' ), $mime, true ) ) {
				continue;
			}
		}

		if ( isset( $argomenti['meta_key'] ) ) {
			$valore = get_post_meta( $id, $argomenti['meta_key'], true );

			// Senza meta_value il filtro chiede solo che la chiave ci sia:
			// e cosi che si trovano le immagini gia ricompresse.
			if ( ! isset( $argomenti['meta_value'] ) ) {
				if ( '' === (string) $valore ) {
					continue;
				}
			} elseif ( (string) $valore !== (string) $argomenti['meta_value'] ) {
				continue;
			}
		}

		$trovati[] = $id;
	}

	$quanti = (int) ( $argomenti['numberposts'] ?? $argomenti['posts_per_page'] ?? 0 );

	if ( $quanti > 0 ) {
		$trovati = array_slice( $trovati, (int) ( $argomenti['offset'] ?? 0 ), $quanti );
	} elseif ( isset( $argomenti['offset'] ) ) {
		$trovati = array_slice( $trovati, (int) $argomenti['offset'] );
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

define( 'MINUTE_IN_SECONDS', 60 );
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

function url_to_postid( $url ) {
	foreach ( $GLOBALS['wp']['post'] as $id => $post ) {
		if ( 'publish' === $post->post_status && get_permalink( $id ) === $url ) {
			return (int) $id;
		}
	}

	return 0;
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

function stub_cartella_caricamenti() {
	static $cartella = null;

	if ( null === $cartella ) {
		$cartella = sys_get_temp_dir() . '/mdi-uploads-' . getmypid();
		@mkdir( $cartella, 0775, true );
	}

	return $cartella;
}

function wp_upload_dir() {
	return array(
		'basedir' => stub_cartella_caricamenti(),
		'baseurl' => 'https://esempio.it/wp-content/uploads',
		'error'   => false,
	);
}

/**
 * Crea un allegato con un file vero sul disco.
 *
 * Il file deve esistere davvero: la compressione lo legge, lo ridimensiona
 * e lo riscrive, e una verifica su un file finto non proverebbe niente.
 *
 * @param int    $id        Identificativo.
 * @param string $nome      Nome del file.
 * @param int    $lato      Lato lungo dell immagine.
 * @param int    $grana     Quanto rumore: piu grana, file piu pesante.
 * @param int    $genitore  Articolo a cui appartiene.
 * @return string Percorso del file creato.
 */
function stub_crea_allegato( $id, $nome, $lato = 1536, $grana = 28, $genitore = 0 ) {
	$percorso = stub_cartella_caricamenti() . '/' . $nome;
	$altezza  = (int) round( $lato * 2 / 3 );

	mt_srand( 11 );
	$im = imagecreatetruecolor( $lato, $altezza );

	for ( $x = 0; $x < $lato; $x++ ) {
		for ( $y = 0; $y < $altezza; $y++ ) {
			$b = (int) ( 128 + 100 * sin( $x / 70 ) * cos( $y / 90 ) + mt_rand( -$grana, $grana ) );
			$b = max( 0, min( 255, $b ) );
			imagesetpixel( $im, $x, $y, imagecolorallocate( $im, $b, (int) ( $b * 0.85 ), (int) ( $b * 0.7 ) ) );
		}
	}

	imagepng( $im, $percorso );
	imagedestroy( $im );

	$GLOBALS['wp']['post'][ $id ] = (object) array(
		'ID'             => $id,
		'post_title'     => $nome,
		'post_content'   => '',
		'post_excerpt'   => '',
		'post_status'    => 'inherit',
		'post_type'      => 'attachment',
		'post_mime_type' => 'image/png',
		'post_name'      => pathinfo( $nome, PATHINFO_FILENAME ),
		'post_parent'    => $genitore,
		'post_date'      => '2026-01-01 10:00:00',
	);

	$GLOBALS['wp']['allegati'][ $id ] = $percorso;

	return $percorso;
}

function get_attached_file( $id ) {
	return $GLOBALS['wp']['allegati'][ (int) $id ] ?? '';
}

function update_attached_file( $id, $file ) {
	$GLOBALS['wp']['allegati'][ (int) $id ] = $file;

	return true;
}

function apply_filters( $hook, $valore ) {
	return $valore;
}

function wp_slash( $valore ) {
	// Come WordPress: aggiunge le barre, perche update_post_meta le toglie.
	return is_array( $valore ) ? array_map( 'wp_slash', $valore ) : addslashes( (string) $valore );
}

function current_time( $tipo = 'mysql', $gmt = 0 ) {
	return 'timestamp' === $tipo ? time() : date( 'Y-m-d H:i:s' );
}

function get_post_type( $id ) {
	$post = get_post( $id );

	return $post ? (string) $post->post_type : false;
}

function get_post_mime_type( $id ) {
	$post = get_post( $id );

	return $post ? (string) ( $post->post_mime_type ?? '' ) : '';
}

/**
 * Editor immagini con GD vero: ridimensiona e scrive WebP davvero.
 */
class Stub_Image_Editor {
	private $file;
	private $lato = 0;
	private $qualita = 82;

	public function __construct( $file ) {
		$this->file = $file;
	}

	public function resize( $larghezza, $altezza, $ritaglia = false ) {
		$this->lato = (int) max( $larghezza, $altezza );

		return true;
	}

	public function set_quality( $qualita ) {
		$this->qualita = (int) $qualita;

		return true;
	}

	public function save( $destinazione, $mime = 'image/webp' ) {
		$immagine = @imagecreatefromstring( (string) file_get_contents( $this->file ) );

		if ( ! $immagine ) {
			return new WP_Error( 'stub_immagine', 'Immagine illeggibile.' );
		}

		if ( $this->lato > 0 && max( imagesx( $immagine ), imagesy( $immagine ) ) > $this->lato ) {
			$scala   = $this->lato / max( imagesx( $immagine ), imagesy( $immagine ) );
			$ridotta = imagescale( $immagine, (int) round( imagesx( $immagine ) * $scala ), (int) round( imagesy( $immagine ) * $scala ) );

			if ( $ridotta ) {
				imagedestroy( $immagine );
				$immagine = $ridotta;
			}
		}

		if ( 'image/webp' === $mime ) {
			imagewebp( $immagine, $destinazione, $this->qualita );
		} else {
			imagejpeg( $immagine, $destinazione, $this->qualita );
		}

		imagedestroy( $immagine );

		return array( 'path' => $destinazione, 'file' => basename( $destinazione ), 'mime-type' => $mime );
	}
}

function wp_get_image_editor( $file ) {
	if ( ! file_exists( $file ) ) {
		return new WP_Error( 'stub_file', 'File assente.' );
	}

	return new Stub_Image_Editor( $file );
}

/**
 * Il minimo di $wpdb che serve alla ricerca dell immagine dentro al testo.
 */
class Stub_Wpdb {
	public $posts = 'wp_posts';

	public function prepare( $sql, ...$argomenti ) {
		foreach ( $argomenti as $valore ) {
			$sql = preg_replace( '/%s/', "'" . str_replace( "'", "''", (string) $valore ) . "'", $sql, 1 );
		}

		return $sql;
	}

	public function esc_like( $testo ) {
		return addcslashes( (string) $testo, '_%\\' );
	}

	public function get_var( $sql ) {
		// Si contano: una query per immagine e proprio il difetto da evitare.
		$GLOBALS['wp']['query_fatte'] = ( $GLOBALS['wp']['query_fatte'] ?? 0 ) + 1;

		// Si interpreta solo la query che serve: quante volte il nome del
		// file compare dentro il contenuto di un articolo non cestinato.
		if ( ! preg_match( "/post_content LIKE '%(.+)%'/U", $sql, $m ) ) {
			return 0;
		}

		$ago = trim( str_replace( '%', '', $m[1] ) );
		$n   = 0;

		foreach ( $GLOBALS['wp']['post'] as $post ) {
			if ( 'trash' === $post->post_status ) {
				continue;
			}

			if ( '' !== $ago && false !== strpos( (string) $post->post_content, $ago ) ) {
				$n++;
			}
		}

		return $n;
	}
}

$GLOBALS['wpdb'] = new Stub_Wpdb();

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

