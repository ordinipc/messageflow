<?php
/**
 * Collegamento con il gestionale: API REST autenticata a token.
 *
 * @package MDI_SEO_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Espone le operazioni che il gestionale può eseguire sul sito.
 *
 * Regole di sicurezza applicate qui:
 * - ogni richiesta deve portare il token generato in bacheca;
 * - nessun contenuto pubblicato viene sovrascritto: le riscritture diventano
 *   bozze nuove collegate all originale;
 * - prima di cambiare una meta il valore precedente viene messo da parte, così
 *   l operazione si può annullare.
 */
class MDI_Api {

	const NAMESPACE_API = 'mdi-seo/v1';
	const OPZIONE_TOKEN = 'mdi_seo_geo_token';
	const OPZIONE_REDIRECT = 'mdi_seo_geo_redirect';
	const META_BACKUP   = '_mdi_backup_meta';
	const META_BOZZA_DI = '_mdi_bozza_di';

	/**
	 * Aggancia le rotte e il gestore dei redirect.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'registra_rotte' ) );
		add_action( 'template_redirect', array( __CLASS__, 'applica_redirect' ), 1 );
	}

	/**
	 * Token corrente, creato al primo utilizzo.
	 *
	 * @param bool $rigenera Forza un token nuovo.
	 * @return string
	 */
	public static function token( $rigenera = false ) {
		$token = get_option( self::OPZIONE_TOKEN, '' );

		if ( $rigenera || ! $token ) {
			$token = bin2hex( random_bytes( 24 ) );
			update_option( self::OPZIONE_TOKEN, $token, false );
		}

		return $token;
	}

	/**
	 * Verifica il token della richiesta.
	 *
	 * @param WP_REST_Request $richiesta Richiesta.
	 * @return bool|WP_Error
	 */
	public static function autorizza( $richiesta ) {
		$ricevuto = (string) $richiesta->get_header( 'x-mdi-token' );
		$atteso   = (string) get_option( self::OPZIONE_TOKEN, '' );

		if ( '' === $atteso ) {
			return new WP_Error( 'mdi_token_assente', 'Nessun token configurato: generalo in WordPress → SEO & GEO.', array( 'status' => 403 ) );
		}

		if ( '' === $ricevuto || ! hash_equals( $atteso, $ricevuto ) ) {
			return new WP_Error( 'mdi_token_errato', 'Token non valido.', array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Registra le rotte.
	 *
	 * @return void
	 */
	public static function registra_rotte() {
		$comune = array( 'permission_callback' => array( __CLASS__, 'autorizza' ) );

		register_rest_route( self::NAMESPACE_API, '/stato', $comune + array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'stato' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/meta', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'aggiorna_meta' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/bozza', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'crea_bozza' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/categoria', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'assegna_categoria' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/redirect', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'salva_redirect' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/immagine', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'carica_immagine' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/annulla', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'annulla_meta' ),
		) );
	}

	/**
	 * Stato del sito, per la prova di collegamento.
	 *
	 * @return WP_REST_Response
	 */
	public static function stato() {
		$conteggi = wp_count_posts( 'post' );
		$pagine   = wp_count_posts( 'page' );

		return rest_ensure_response(
			array(
				'ok'         => true,
				'sito'       => get_bloginfo( 'name' ),
				'url'        => home_url( '/' ),
				'wordpress'  => get_bloginfo( 'version' ),
				'plugin'     => MDI_SEO_GEO_VERSION,
				'articoli'   => (int) $conteggi->publish,
				'pagine'     => (int) $pagine->publish,
				'rank_math'  => defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ),
				'yoast'      => defined( 'WPSEO_VERSION' ),
				'redirect'   => count( (array) get_option( self::OPZIONE_REDIRECT, array() ) ),
			)
		);
	}

	/**
	 * Aggiorna title, description, focus keyword ed estratto.
	 *
	 * @param WP_REST_Request $richiesta Richiesta con 'contenuti' e 'anteprima'.
	 * @return WP_REST_Response
	 */
	public static function aggiorna_meta( $richiesta ) {
		$contenuti = (array) $richiesta->get_param( 'contenuti' );
		$anteprima = (bool) $richiesta->get_param( 'anteprima' );

		$fatti  = 0;
		$saltati = 0;
		$dettaglio = array();

		foreach ( $contenuti as $riga ) {
			$id = isset( $riga['id'] ) ? (int) $riga['id'] : 0;

			if ( ! $id || ! get_post( $id ) ) {
				$saltati++;
				continue;
			}

			$prima = array(
				'rank_math_title'         => get_post_meta( $id, 'rank_math_title', true ),
				'rank_math_description'   => get_post_meta( $id, 'rank_math_description', true ),
				'rank_math_focus_keyword' => get_post_meta( $id, 'rank_math_focus_keyword', true ),
				'post_excerpt'            => get_post_field( 'post_excerpt', $id ),
			);

			$dopo = array(
				'rank_math_title'         => isset( $riga['title'] ) ? sanitize_text_field( $riga['title'] ) : $prima['rank_math_title'],
				'rank_math_description'   => isset( $riga['description'] ) ? sanitize_text_field( $riga['description'] ) : $prima['rank_math_description'],
				'rank_math_focus_keyword' => isset( $riga['focus'] ) ? sanitize_text_field( $riga['focus'] ) : $prima['rank_math_focus_keyword'],
				'post_excerpt'            => isset( $riga['excerpt'] ) ? wp_kses_post( $riga['excerpt'] ) : $prima['post_excerpt'],
			);

			$dettaglio[] = array(
				'id'     => $id,
				'titolo' => get_the_title( $id ),
				'prima'  => $prima,
				'dopo'   => $dopo,
			);

			if ( $anteprima ) {
				continue;
			}

			// Il valore precedente resta da parte: l operazione è annullabile.
			if ( ! get_post_meta( $id, self::META_BACKUP, true ) ) {
				update_post_meta( $id, self::META_BACKUP, wp_json_encode( $prima ) );
			}

			update_post_meta( $id, 'rank_math_title', $dopo['rank_math_title'] );
			update_post_meta( $id, 'rank_math_description', $dopo['rank_math_description'] );
			update_post_meta( $id, 'rank_math_focus_keyword', $dopo['rank_math_focus_keyword'] );

			if ( $dopo['post_excerpt'] !== $prima['post_excerpt'] ) {
				wp_update_post( array( 'ID' => $id, 'post_excerpt' => $dopo['post_excerpt'] ) );
			}

			$fatti++;
		}

		return rest_ensure_response(
			array(
				'ok'        => true,
				'anteprima' => $anteprima,
				'aggiornati' => $fatti,
				'saltati'   => $saltati,
				// Il confronto torna per intero: è quello che il gestionale mostra
				// nella pagina di anteprima. I blocchi arrivano già limitati.
				'dettaglio' => array_slice( $dettaglio, 0, 200 ),
			)
		);
	}

	/**
	 * Ripristina le meta salvate prima dell ultimo aggiornamento.
	 *
	 * @param WP_REST_Request $richiesta Richiesta con 'ids'.
	 * @return WP_REST_Response
	 */
	public static function annulla_meta( $richiesta ) {
		$ids       = (array) $richiesta->get_param( 'ids' );
		$ripristinati = 0;

		foreach ( $ids as $id ) {
			$id     = (int) $id;
			$backup = get_post_meta( $id, self::META_BACKUP, true );

			if ( ! $backup ) {
				continue;
			}

			$prima = json_decode( $backup, true );

			if ( ! is_array( $prima ) ) {
				continue;
			}

			foreach ( array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword' ) as $chiave ) {
				update_post_meta( $id, $chiave, $prima[ $chiave ] ?? '' );
			}

			wp_update_post( array( 'ID' => $id, 'post_excerpt' => $prima['post_excerpt'] ?? '' ) );
			delete_post_meta( $id, self::META_BACKUP );
			$ripristinati++;
		}

		return rest_ensure_response( array( 'ok' => true, 'ripristinati' => $ripristinati ) );
	}

	/**
	 * Crea una bozza nuova collegata all articolo originale.
	 *
	 * Non tocca il contenuto pubblicato: la revisione la fa una persona,
	 * confrontando i due testi e pubblicando quando è soddisfatta.
	 *
	 * @param WP_REST_Request $richiesta Richiesta.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function crea_bozza( $richiesta ) {
		$originale = (int) $richiesta->get_param( 'id' );
		$post      = get_post( $originale );

		if ( ! $post ) {
			return new WP_Error( 'mdi_post_assente', 'Articolo non trovato: ' . $originale, array( 'status' => 404 ) );
		}

		$titolo = (string) $richiesta->get_param( 'titolo' );
		$corpo  = (string) $richiesta->get_param( 'corpo_html' );
		$faq    = (array) $richiesta->get_param( 'faq' );
		$breve  = (string) $richiesta->get_param( 'in_breve' );

		if ( '' === trim( $corpo ) ) {
			return new WP_Error( 'mdi_corpo_vuoto', 'Corpo della bozza vuoto.', array( 'status' => 400 ) );
		}

		$contenuto = '';

		if ( '' !== $breve ) {
			$contenuto .= '<div class="mdi-in-breve"><p><strong>In breve:</strong> ' . esc_html( $breve ) . "</p></div>\n\n";
		}

		$contenuto .= wp_kses_post( $corpo );

		if ( $faq ) {
			$contenuto .= "\n\n<h2>Domande frequenti</h2>\n";

			foreach ( $faq as $voce ) {
				$contenuto .= '<h3>' . esc_html( $voce['domanda'] ?? '' ) . '</h3>' . "\n"
					. '<p>' . esc_html( $voce['risposta'] ?? '' ) . "</p>\n";
			}
		}

		// Se esiste già una bozza per questo articolo, viene aggiornata.
		$esistenti = get_posts(
			array(
				'post_type'   => $post->post_type,
				'post_status' => 'draft',
				'meta_key'    => self::META_BOZZA_DI,
				'meta_value'  => (string) $originale,
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);

		$dati = array(
			'post_title'   => $titolo ?: $post->post_title,
			'post_content' => $contenuto,
			'post_status'  => 'draft',
			'post_type'    => $post->post_type,
			'post_author'  => $post->post_author,
		);

		if ( $esistenti ) {
			$dati['ID'] = (int) $esistenti[0];
			$id_bozza   = wp_update_post( $dati, true );
		} else {
			$id_bozza = wp_insert_post( $dati, true );
		}

		if ( is_wp_error( $id_bozza ) ) {
			return $id_bozza;
		}

		update_post_meta( $id_bozza, self::META_BOZZA_DI, (string) $originale );

		foreach ( wp_get_post_categories( $originale ) as $categoria ) {
			wp_set_post_categories( $id_bozza, array( $categoria ), true );
		}

		if ( $richiesta->get_param( 'meta_title' ) ) {
			update_post_meta( $id_bozza, 'rank_math_title', sanitize_text_field( (string) $richiesta->get_param( 'meta_title' ) ) );
		}

		if ( $richiesta->get_param( 'meta_description' ) ) {
			update_post_meta( $id_bozza, 'rank_math_description', sanitize_text_field( (string) $richiesta->get_param( 'meta_description' ) ) );
		}

		return rest_ensure_response(
			array(
				'ok'        => true,
				'id_bozza'  => (int) $id_bozza,
				'modifica'  => admin_url( 'post.php?post=' . (int) $id_bozza . '&action=edit' ),
				'originale' => $originale,
			)
		);
	}

	/**
	 * Assegna la categoria indicata, creandola se non esiste.
	 *
	 * @param WP_REST_Request $richiesta Richiesta con 'assegnazioni'.
	 * @return WP_REST_Response
	 */
	public static function assegna_categoria( $richiesta ) {
		$assegnazioni = (array) $richiesta->get_param( 'assegnazioni' );
		$fatte        = 0;

		foreach ( $assegnazioni as $riga ) {
			$id   = isset( $riga['id'] ) ? (int) $riga['id'] : 0;
			$nome = isset( $riga['categoria'] ) ? sanitize_text_field( $riga['categoria'] ) : '';

			if ( ! $id || '' === $nome || ! get_post( $id ) ) {
				continue;
			}

			$termine = get_term_by( 'name', $nome, 'category' );

			if ( ! $termine ) {
				$creato = wp_insert_term( $nome, 'category' );

				if ( is_wp_error( $creato ) ) {
					continue;
				}

				$id_termine = (int) $creato['term_id'];
			} else {
				$id_termine = (int) $termine->term_id;
			}

			wp_set_post_categories( $id, array( $id_termine ) );
			$fatte++;
		}

		return rest_ensure_response( array( 'ok' => true, 'assegnate' => $fatte ) );
	}

	/**
	 * Salva la tabella dei redirect 301.
	 *
	 * @param WP_REST_Request $richiesta Richiesta con 'redirect'.
	 * @return WP_REST_Response
	 */
	public static function salva_redirect( $richiesta ) {
		$righe   = (array) $richiesta->get_param( 'redirect' );
		$tabella = array();

		foreach ( $righe as $riga ) {
			$da = isset( $riga['da'] ) ? '/' . trim( wp_parse_url( $riga['da'], PHP_URL_PATH ) ?? $riga['da'], '/' ) . '/' : '';
			$a  = isset( $riga['a'] ) ? esc_url_raw( $riga['a'] ) : '';

			if ( '' === $da || '' === $a || '//' === $da ) {
				continue;
			}

			$tabella[ $da ] = $a;
		}

		update_option( self::OPZIONE_REDIRECT, $tabella, false );

		return rest_ensure_response( array( 'ok' => true, 'redirect' => count( $tabella ) ) );
	}

	/**
	 * Applica i redirect 301 salvati.
	 *
	 * @return void
	 */
	public static function applica_redirect() {
		if ( is_admin() || ! is_404() ) {
			return;
		}

		$tabella = (array) get_option( self::OPZIONE_REDIRECT, array() );

		if ( empty( $tabella ) ) {
			return;
		}

		$percorso = '/' . trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ) . '/';

		if ( isset( $tabella[ $percorso ] ) ) {
			wp_safe_redirect( $tabella[ $percorso ], 301 );
			exit;
		}
	}

	/**
	 * Carica un immagine nella libreria e la imposta come immagine in evidenza.
	 *
	 * @param WP_REST_Request $richiesta Richiesta.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function carica_immagine( $richiesta ) {
		$id = (int) $richiesta->get_param( 'id' );

		if ( ! get_post( $id ) ) {
			return new WP_Error( 'mdi_post_assente', 'Articolo non trovato.', array( 'status' => 404 ) );
		}

		$dati = base64_decode( (string) $richiesta->get_param( 'dati_base64' ), true );

		if ( false === $dati || strlen( $dati ) < 1024 ) {
			return new WP_Error( 'mdi_immagine_invalida', 'Dati dell immagine non validi.', array( 'status' => 400 ) );
		}

		$mime      = (string) ( $richiesta->get_param( 'mime' ) ?: 'image/png' );
		$estensioni = array( 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp' );

		if ( ! isset( $estensioni[ $mime ] ) ) {
			return new WP_Error( 'mdi_mime_non_ammesso', 'Formato non ammesso: ' . $mime, array( 'status' => 400 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$nome     = sanitize_file_name( (string) ( $richiesta->get_param( 'nome' ) ?: 'immagine' ) ) . '.' . $estensioni[ $mime ];
		$caricato = wp_upload_bits( $nome, null, $dati );

		if ( ! empty( $caricato['error'] ) ) {
			return new WP_Error( 'mdi_upload_fallito', $caricato['error'], array( 'status' => 500 ) );
		}

		$allegato = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => (string) ( $richiesta->get_param( 'titolo' ) ?: get_the_title( $id ) ),
				'post_status'    => 'inherit',
			),
			$caricato['file'],
			$id
		);

		if ( is_wp_error( $allegato ) ) {
			return $allegato;
		}

		wp_update_attachment_metadata( $allegato, wp_generate_attachment_metadata( $allegato, $caricato['file'] ) );

		$alt = (string) $richiesta->get_param( 'alt' );

		if ( '' !== $alt ) {
			update_post_meta( $allegato, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}

		if ( $richiesta->get_param( 'in_evidenza' ) ) {
			set_post_thumbnail( $id, $allegato );
		}

		return rest_ensure_response(
			array(
				'ok'       => true,
				'allegato' => (int) $allegato,
				'url'      => $caricato['url'],
			)
		);
	}
}
