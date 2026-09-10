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
define( 'MDI_SEO_GEO_VERSION', '1.0.0' );
define( 'MDI_SEO_GEO_DIR', dirname( __DIR__ ) . '/plugin-wordpress/mdi-seo-geo-booster/' );

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
		'ID'           => $id,
		'post_title'   => $titolo,
		'post_content' => $contenuto,
		'post_excerpt' => '',
		'post_status'  => 'publish',
		'post_type'    => 'post',
		'post_author'  => 1,
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

function untrailingslashit( $stringa ) {
	return rtrim( $stringa, '/' );
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

require_once MDI_SEO_GEO_DIR . 'includes/class-mdi-api.php';
