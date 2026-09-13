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
	const OPZIONE_CONFIG   = 'mdi_seo_geo_config';
	const META_BACKUP   = '_mdi_backup_meta';
	const META_BOZZA_DI = '_mdi_bozza_di';
	const META_CATEGORIE = '_mdi_backup_categorie';
	const META_IMG_PRIMA = '_mdi_immagine_originale';
	const META_TESTO_PRIMA = '_mdi_testo_originale';
	const META_ELEMENTOR_PRIMA = '_mdi_elementor_originale';

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

		register_rest_route( self::NAMESPACE_API, '/contenuto', $comune + array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'contenuto' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/conteggi', $comune + array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'conteggi' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/contenuti', $comune + array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'contenuti' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/allegati', $comune + array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'allegati' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/menu', $comune + array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'menu' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/config', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'salva_config' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/applica-bozza', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'applica_bozza' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/cestina', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'cestina' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/strutture-elementor', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'strutture_elementor' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/costruttori', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'costruttori' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/sovrascrivi', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'sovrascrivi' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/immagini-pesanti', $comune + array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'immagini_pesanti' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/comprimi-immagine', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'comprimi_immagine' ),
		) );

		register_rest_route( self::NAMESPACE_API, '/ripristina-immagine', $comune + array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'ripristina_immagine' ),
		) );
	}

	/**
	 * Quanti contenuti ci sono da leggere: serve al gestionale per sapere
	 * quante pagine di risultati richiedere.
	 *
	 * @return WP_REST_Response
	 */
	public static function conteggi() {
		$articoli = wp_count_posts( 'post' );
		$pagine    = wp_count_posts( 'page' );

		return rest_ensure_response(
			array(
				'ok'        => true,
				'articoli'  => (int) $articoli->publish,
				'pagine'    => (int) $pagine->publish,
				'contenuti' => (int) $articoli->publish + (int) $pagine->publish,
				'allegati'  => (int) count( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1, 'fields' => 'ids' ) ) ),
				'sito'      => array(
					'titolo'      => get_bloginfo( 'name' ),
					'descrizione' => get_bloginfo( 'description' ),
					'url'         => home_url( '/' ),
					'lingua'      => get_bloginfo( 'language' ),
				),
				// Gli autori servono a valutare l attribuzione dei contenuti:
				// un articolo firmato da una persona reale vale più di uno
				// firmato da un login.
				'autori'    => array_map(
					static function ( $utente ) {
						return array(
							'id'    => (string) $utente->ID,
							'login' => $utente->user_login,
							'nome'  => $utente->display_name,
							'first' => get_user_meta( $utente->ID, 'first_name', true ),
							'last'  => get_user_meta( $utente->ID, 'last_name', true ),
							'email' => $utente->user_email,
						);
					},
					(array) get_users( array( 'number' => 20 ) )
				),
			)
		);
	}

	/**
	 * Restituisce un blocco di contenuti con tutto ciò che serve all analisi.
	 *
	 * @param WP_REST_Request $richiesta Richiesta con 'offset' e 'limite'.
	 * @return WP_REST_Response
	 */
	public static function contenuti( $richiesta ) {
		$offset = max( 0, (int) $richiesta->get_param( 'offset' ) );
		$limite = min( 100, max( 1, (int) ( $richiesta->get_param( 'limite' ) ?: 40 ) ) );

		$ids = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => $limite,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$contenuti = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( ! $post ) {
				continue;
			}

			$categorie = array();

			foreach ( (array) get_the_category( $id ) as $categoria ) {
				$categorie[] = array( 'slug' => $categoria->slug, 'nome' => $categoria->name );
			}

			$etichette = array();

			foreach ( (array) get_the_tags( $id ) as $etichetta ) {
				if ( $etichetta ) {
					$etichette[] = array( 'slug' => $etichetta->slug, 'nome' => $etichetta->name );
				}
			}

			$meta   = array();
			$chiavi = array(
				'rank_math_title',
				'rank_math_description',
				'rank_math_focus_keyword',
				'rank_math_robots',
				'rank_math_canonical_url',
				'rank_math_seo_score',
				'_yoast_wpseo_title',
				'_yoast_wpseo_metadesc',
				'_yoast_wpseo_focuskw',
				'_thumbnail_id',
				'_elementor_data',
			);

			foreach ( $chiavi as $chiave ) {
				$valore = get_post_meta( $id, $chiave, true );

				// WordPress restituisce alcune meta come array (rank_math_robots
				// è index,follow): il gestionale si aspetta del testo.
				if ( is_array( $valore ) ) {
					$valore = implode( ',', array_filter( $valore, 'is_scalar' ) );
				}

				if ( '' !== $valore && null !== $valore && is_scalar( $valore ) ) {
					// Di Elementor basta sapere che c è: il contenuto pesa troppo.
					$meta[ $chiave ] = '_elementor_data' === $chiave ? '1' : (string) $valore;
				}
			}

			$contenuti[] = array(
				'wp_id'      => (string) $id,
				'titolo'     => get_the_title( $id ),
				'link'       => get_permalink( $id ),
				'slug'       => $post->post_name,
				'tipo'       => $post->post_type,
				'stato'      => $post->post_status,
				'data'       => $post->post_date_gmt,
				'modificato' => $post->post_modified_gmt,
				'autore'     => get_the_author_meta( 'display_name', $post->post_author ),
				'contenuto'  => $post->post_content,
				'estratto'   => $post->post_excerpt,
				'genitore'   => (string) $post->post_parent,
				'commenti'   => $post->comment_status,
				'categorie'  => $categorie,
				'tag'        => $etichette,
				'meta'       => $meta,
			);
		}

		return rest_ensure_response( array( 'ok' => true, 'offset' => $offset, 'contenuti' => $contenuti ) );
	}

	/**
	 * Restituisce gli allegati della libreria media.
	 *
	 * @param WP_REST_Request $richiesta Richiesta con 'offset' e 'limite'.
	 * @return WP_REST_Response
	 */
	public static function allegati( $richiesta ) {
		$offset = max( 0, (int) $richiesta->get_param( 'offset' ) );
		$limite = min( 200, max( 1, (int) ( $richiesta->get_param( 'limite' ) ?: 100 ) ) );

		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => $limite,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$allegati = array();

		foreach ( $ids as $id ) {
			$file = get_attached_file( $id );
			$dati = wp_get_attachment_metadata( $id );

			$allegati[] = array(
				'wp_id'    => (string) $id,
				'url'      => wp_get_attachment_url( $id ),
				'titolo'   => get_the_title( $id ),
				'alt'      => get_post_meta( $id, '_wp_attachment_image_alt', true ),
				'genitore' => (string) wp_get_post_parent_id( $id ),
				'peso'     => ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : (int) ( $dati['filesize'] ?? 0 ),
			);
		}

		return rest_ensure_response( array( 'ok' => true, 'offset' => $offset, 'allegati' => $allegati ) );
	}

	/**
	 * Allegati immagine che pesano piu della soglia.
	 *
	 * Per ognuno dice anche se l URL compare dentro il testo di qualche
	 * contenuto: quelle si lasciano stare, perche ricomprimerle cambia il
	 * nome del file e l immagine sparirebbe dall articolo. Le immagini in
	 * evidenza invece sono collegate per identificativo, non per indirizzo,
	 * e si possono sostituire senza rompere niente.
	 *
	 * @param WP_REST_Request $richiesta Richiesta.
	 * @return WP_REST_Response
	 */
	public static function immagini_pesanti( $richiesta ) {
		$soglia = max( 1024, (int) ( $richiesta->get_param( 'oltre' ) ?: 204800 ) );
		$blocco = min( 400, max( 20, (int) ( $richiesta->get_param( 'blocco' ) ?: 150 ) ) );
		$offset = max( 0, (int) $richiesta->get_param( 'offset' ) );

		// Gli identificativi costano poco; i file su disco no. Si guarda solo
		// un blocco per volta e si dice al gestionale da dove riprendere:
		// leggere tutta la libreria media in una richiesta sola supera il
		// tempo massimo su qualsiasi hosting condiviso.
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => array( 'image/png', 'image/jpeg' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$totale = count( $ids );
		$fetta  = array_slice( $ids, $offset, $blocco );
		$usati  = self::nomi_usati_nei_contenuti();

		$pesanti = array();

		foreach ( $fetta as $id ) {
			$file = get_attached_file( $id );

			if ( ! $file || ! file_exists( $file ) ) {
				continue;
			}

			$peso = (int) filesize( $file );

			if ( $peso <= $soglia ) {
				continue;
			}

			$nome = strtolower( (string) pathinfo( (string) $file, PATHINFO_FILENAME ) );

			$pesanti[] = array(
				'wp_id'       => (string) $id,
				'file'        => basename( (string) $file ),
				'url'         => wp_get_attachment_url( $id ),
				'mime'        => get_post_mime_type( $id ),
				'peso'        => $peso,
				'genitore'    => (string) wp_get_post_parent_id( $id ),
				'nel_testo'   => isset( $usati[ $nome ] ),
				'gia_ridotta' => '' !== (string) get_post_meta( $id, self::META_IMG_PRIMA, true ),
			);
		}

		$prossimo = $offset + count( $fetta );

		// Quante sono gia state ricompresse: senza questo numero, chi preme
		// il pulsante piu volte non ha modo di sapere a che punto e.
		$fatte = count(
			get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => self::META_IMG_PRIMA,
				)
			)
		);

		return rest_ensure_response(
			array(
				'ok'        => true,
				'soglia'    => $soglia,
				'totale'    => $totale,
				'guardati'  => $prossimo,
				'prossimo'  => $prossimo,
				'finito'    => $prossimo >= $totale,
				'gia_fatte' => $fatte,
				'immagini'  => $pesanti,
			)
		);
	}

	/**
	 * Nomi dei file che compaiono dentro il testo dei contenuti.
	 *
	 * Una query LIKE per ogni immagine voleva dire, su un archivio con
	 * duecento immagini pesanti, duecento scansioni complete della tabella
	 * dei post: la richiesta andava in timeout e la pagina non mostrava
	 * niente. Qui si legge il contenuto una volta sola, si tirano fuori i
	 * nomi dei file citati e poi ogni immagine si controlla a colpo sicuro.
	 *
	 * Il nome viene ripulito dal suffisso delle copie ridimensionate che
	 * WordPress genera (foto-300x200.jpg), altrimenti un immagine inserita
	 * in formato medio sembrerebbe non essere usata da nessuna parte.
	 *
	 * @return array Nomi (senza estensione) come chiavi.
	 */
	private static function nomi_usati_nei_contenuti() {
		$in_cache = get_transient( 'mdi_nomi_immagini_usate' );

		if ( is_array( $in_cache ) ) {
			return $in_cache;
		}

		$nomi   = array();
		$offset = 0;

		do {
			$pagina = get_posts(
				array(
					'post_type'      => array( 'post', 'page' ),
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page' => 100,
					'offset'         => $offset,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
				)
			);

			foreach ( $pagina as $id ) {
				$contenuto = (string) get_post_field( 'post_content', $id );

				if ( '' === $contenuto || false === strpos( $contenuto, 'uploads' ) ) {
					continue;
				}

				if ( ! preg_match_all( '#/uploads/[^"\')\s]*?([^/"\')\s]+)\.(?:jpe?g|png|webp|gif)#i', $contenuto, $trovati ) ) {
					continue;
				}

				foreach ( $trovati[1] as $nome ) {
					// foto-300x200 e la copia ridimensionata di foto.
					$nomi[ strtolower( preg_replace( '/-\d+x\d+$/', '', $nome ) ) ] = true;
				}
			}

			$offset += count( $pagina );
		} while ( count( $pagina ) === 100 );

		set_transient( 'mdi_nomi_immagini_usate', $nomi, 5 * MINUTE_IN_SECONDS );

		return $nomi;
	}

	/**
	 * Ricomprime un allegato in WebP, lasciando l originale sul disco.
	 *
	 * Il file originale non viene mai cancellato e il suo percorso resta in
	 * un meta: e la sola cosa che rende l operazione annullabile. Chi vuole
	 * recuperare spazio cancella a mano, dopo aver verificato il sito.
	 *
	 * @param WP_REST_Request $richiesta Richiesta.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function comprimi_immagine( $richiesta ) {
		$id = (int) $richiesta->get_param( 'id' );

		if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
			return new WP_Error( 'mdi_allegato_assente', 'Allegato non trovato.', array( 'status' => 404 ) );
		}

		$file = get_attached_file( $id );

		if ( ! $file || ! file_exists( $file ) ) {
			return new WP_Error( 'mdi_file_assente', 'File dell allegato non trovato sul disco.', array( 'status' => 404 ) );
		}

		$segnata   = (string) get_post_meta( $id, self::META_IMG_PRIMA, true );
		$originale = '' !== $segnata ? wp_upload_dir()['basedir'] . '/' . $segnata : '';

		// Gia fatta davvero solo se l allegato punta a un file diverso
		// dall originale. Se punta ancora a quello, il tentativo precedente
		// si e interrotto a meta: si rifa, invece di rispondere per sempre
		// che e gia stata ricompressa.
		//
		// E non e un errore: in un lavoro a blocchi ritrovarsi davanti una
		// immagine gia fatta e normale, e rispondere con un errore HTTP
		// riempiva l elenco dei guasti e faceva credere al ciclo di non
		// avere concluso niente, fermandolo con meta lavoro ancora da fare.
		if ( '' !== $segnata && $originale !== $file ) {
			return rest_ensure_response(
				array(
					'ok'       => true,
					'id'       => $id,
					'cambiata' => false,
					'motivo'   => 'gia_fatta',
					'prima'    => (int) filesize( $file ),
					'dopo'     => (int) filesize( $file ),
				)
			);
		}

		$lato    = max( 200, (int) ( $richiesta->get_param( 'lato' ) ?: 1200 ) );
		$qualita = min( 100, max( 30, (int) ( $richiesta->get_param( 'qualita' ) ?: 82 ) ) );
		$peso_max = max( 20480, (int) ( $richiesta->get_param( 'peso_max' ) ?: 190000 ) );

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$prima = (int) filesize( $file );

		// Una qualita fissa non basta: su una fotografia molto granulosa
		// 1200px a 82 esce ancora sopra i 200 KB. Si scende per gradi e ci si
		// ferma al primo tentativo che sta sotto la soglia.
		$destinazione = preg_replace( '/\.[^.]+$/', '', (string) $file ) . '.webp';
		$riuscito     = false;

		foreach ( array( $lato, (int) round( $lato * 0.83 ), (int) round( $lato * 0.67 ) ) as $lato_prova ) {
			foreach ( array( $qualita, 72, 62, 52 ) as $q ) {
				$editor = wp_get_image_editor( $file );

				if ( is_wp_error( $editor ) ) {
					return $editor;
				}

				$editor->resize( $lato_prova, $lato_prova, false );
				$editor->set_quality( $q );

				$salvato = $editor->save( $destinazione, 'image/webp' );

				if ( is_wp_error( $salvato ) ) {
					return $salvato;
				}

				$riuscito = true;

				if ( filesize( $destinazione ) <= $peso_max ) {
					break 2;
				}
			}
		}

		if ( ! $riuscito || ! file_exists( $destinazione ) ) {
			return new WP_Error( 'mdi_conversione_fallita', 'Conversione non riuscita.', array( 'status' => 500 ) );
		}

		$dopo = (int) filesize( $destinazione );

		// Se non si guadagna niente si tiene quello che c era: il file nuovo
		// viene rimosso e l allegato non si tocca.
		if ( $dopo >= $prima ) {
			@unlink( $destinazione );

			return rest_ensure_response(
				array( 'ok' => true, 'id' => $id, 'cambiata' => false, 'prima' => $prima, 'dopo' => $prima )
			);
		}

		$caricamenti = wp_upload_dir();
		$relativo    = ltrim( str_replace( $caricamenti['basedir'], '', (string) $file ), '/\\' );

		update_attached_file( $id, $destinazione );
		wp_update_post( array( 'ID' => $id, 'post_mime_type' => 'image/webp' ) );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $destinazione ) );

		// Il segnale "gia fatta" si scrive per ultimo, a scambio avvenuto.
		// Scriverlo prima voleva dire che una richiesta morta durante la
		// rigenerazione delle miniature - su immagini da megabyte capita -
		// lasciava l immagine marcata come fatta senza esserlo, e da li in
		// poi ogni tentativo rispondeva 409 senza piu rimediare.
		update_post_meta( $id, self::META_IMG_PRIMA, $relativo );

		return rest_ensure_response(
			array(
				'ok'       => true,
				'id'       => $id,
				'cambiata' => true,
				'prima'    => $prima,
				'dopo'     => $dopo,
				'file'     => basename( $destinazione ),
				'url'      => wp_get_attachment_url( $id ),
			)
		);
	}

	/**
	 * Rimette l immagine originale al posto della versione ricompressa.
	 *
	 * @param WP_REST_Request $richiesta Richiesta.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function ripristina_immagine( $richiesta ) {
		$ids = array_map( 'intval', (array) $richiesta->get_param( 'ids' ) );

		if ( ! $ids ) {
			$ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => self::META_IMG_PRIMA,
				)
			);
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$caricamenti = wp_upload_dir();
		$rimesse     = 0;
		$mancanti    = 0;

		foreach ( $ids as $id ) {
			$relativo = (string) get_post_meta( $id, self::META_IMG_PRIMA, true );

			if ( '' === $relativo ) {
				continue;
			}

			$originale = $caricamenti['basedir'] . '/' . $relativo;

			if ( ! file_exists( $originale ) ) {
				$mancanti++;
				continue;
			}

			$estensione = strtolower( (string) pathinfo( $originale, PATHINFO_EXTENSION ) );
			$mime       = 'png' === $estensione ? 'image/png' : ( 'webp' === $estensione ? 'image/webp' : 'image/jpeg' );

			update_attached_file( $id, $originale );
			wp_update_post( array( 'ID' => $id, 'post_mime_type' => $mime ) );
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $originale ) );
			delete_post_meta( $id, self::META_IMG_PRIMA );

			$rimesse++;
		}

		return rest_ensure_response(
			array( 'ok' => true, 'ripristinate' => $rimesse, 'originali_mancanti' => $mancanti )
		);
	}

	/**
	 * Restituisce le voci dei menu di navigazione.
	 *
	 * @return WP_REST_Response
	 */
	public static function menu() {
		$voci = array();

		foreach ( (array) wp_get_nav_menus() as $menu ) {
			foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $voce ) {
				if ( ! $voce ) {
					continue;
				}

				$voci[] = array(
					'titolo' => $voce->title,
					'tipo'   => $voce->type,
					'url'    => $voce->url,
					'menu'   => $menu->name,
				);
			}
		}

		return rest_ensure_response( array( 'ok' => true, 'voci' => $voci ) );
	}

	/**
	 * Riceve dal gestionale i dati aziendali e li salva.
	 *
	 * Da questo momento schema LocalBusiness, footer NAP e llms.txt usano questi
	 * valori invece di quelli congelati nello zip del plugin.
	 *
	 * @param WP_REST_Request $richiesta Richiesta con 'config'.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function salva_config( $richiesta ) {
		$configurazione = $richiesta->get_param( 'config' );

		if ( ! is_array( $configurazione ) || empty( $configurazione['azienda'] ) ) {
			return new WP_Error( 'mdi_config_invalida', 'Configurazione non valida.', array( 'status' => 400 ) );
		}

		update_option( self::OPZIONE_CONFIG, $configurazione, false );

		$compilati = array();
		$mancanti  = array();

		foreach ( array(
			'telefono'   => $configurazione['azienda']['telefono'] ?? '',
			'partitaIva' => $configurazione['azienda']['partitaIva'] ?? '',
			'indirizzo'  => $configurazione['azienda']['indirizzo']['via'] ?? '',
			'cap'        => $configurazione['azienda']['indirizzo']['cap'] ?? '',
			'google'     => $configurazione['azienda']['profili']['googleBusiness'] ?? '',
		) as $nome => $valore ) {
			if ( '' !== $valore && 0 !== stripos( (string) $valore, 'DA_COMPILARE' ) ) {
				$compilati[] = $nome;
			} else {
				$mancanti[] = $nome;
			}
		}

		return rest_ensure_response(
			array(
				'ok'        => true,
				'compilati' => $compilati,
				'mancanti'  => $mancanti,
			)
		);
	}

	/**
	 * Stato attuale di un singolo contenuto, cercato per indirizzo o per id.
	 *
	 * Serve a rispondere alla domanda "perché questa pagina non si vede?" con
	 * quello che il sito sa davvero, invece che con una supposizione.
	 *
	 * @param WP_REST_Request $richiesta Richiesta con 'id' oppure 'url'.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function contenuto( $richiesta ) {
		$id  = (int) $richiesta->get_param( 'id' );
		$url = (string) $richiesta->get_param( 'url' );

		if ( ! $id && '' !== $url ) {
			$id = (int) url_to_postid( $url );

			// url_to_postid non trova i contenuti che non sono più pubblici:
			// quelli si cercano per slug, ed è proprio il caso che interessa.
			if ( ! $id ) {
				$slug = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
				$slug = substr( strrchr( '/' . $slug, '/' ), 1 );

				if ( '' !== $slug ) {
					$trovati = get_posts(
						array(
							'name'        => $slug,
							'post_type'   => array( 'post', 'page' ),
							'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private', 'trash' ),
							'numberposts' => 1,
							'fields'      => 'ids',
						)
					);

					$id = $trovati ? (int) $trovati[0] : 0;
				}
			}
		}

		if ( ! $id ) {
			return new WP_Error( 'mdi_non_trovato', 'Nessun contenuto trovato per questo indirizzo.', array( 'status' => 404 ) );
		}

		$post = get_post( $id );

		if ( ! $post ) {
			return new WP_Error( 'mdi_non_trovato', 'Contenuto non trovato.', array( 'status' => 404 ) );
		}

		$categorie = array();

		foreach ( (array) get_the_category( $id ) as $categoria ) {
			$categorie[] = $categoria->name;
		}

		$robots = get_post_meta( $id, 'rank_math_robots', true );

		if ( is_array( $robots ) ) {
			$robots = implode( ',', array_filter( $robots, 'is_scalar' ) );
		}

		return rest_ensure_response(
			array(
				'ok'          => true,
				'wp_id'       => (string) $id,
				'titolo'      => get_the_title( $id ),
				'link'        => get_permalink( $id ),
				'slug'        => $post->post_name,
				'tipo'        => $post->post_type,
				'stato'       => $post->post_status,
				'password'    => '' !== (string) $post->post_password,
				'data'        => $post->post_date,
				'modificato'  => $post->post_modified,
				'futuro'      => 'future' === $post->post_status,
				'categorie'   => $categorie,
				'parole'      => str_word_count( wp_strip_all_tags( (string) $post->post_content ) ),
				'robots'      => (string) $robots,
				'canonica'    => (string) get_post_meta( $id, 'rank_math_canonical_url', true ),
				'in_mappa'    => (bool) array_filter(
					(array) mdi_seo_geo_data( 'meta-map' ),
					static function ( $riga ) use ( $id ) {
						return (int) ( $riga['id'] ?? 0 ) === $id;
					}
				),
				'noindex_mappa' => (bool) array_filter(
					(array) mdi_seo_geo_data( 'meta-map' ),
					static function ( $riga ) use ( $id ) {
						return (int) ( $riga['id'] ?? 0 ) === $id && ! empty( $riga['noindex'] );
					}
				),
				'bozza_pronta'  => (bool) get_posts(
					array(
						'post_type'   => $post->post_type,
						'post_status' => 'draft',
						'meta_key'    => self::META_BOZZA_DI,
						'meta_value'  => (string) $id,
						'numberposts' => 1,
						'fields'      => 'ids',
					)
				),
				'meta_toccate'  => (bool) get_post_meta( $id, self::META_BACKUP, true ),
			)
		);
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
				'config'     => (bool) get_option( self::OPZIONE_CONFIG, false ),
				'telefono'   => (bool) mdi_seo_geo_cfg( 'azienda.telefono' ),
				'piva'       => (bool) mdi_seo_geo_cfg( 'azienda.partitaIva' ),
				'analisi'    => class_exists( 'MDI_Admin' ) && '' !== MDI_Admin::url_analisi(),
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
			$backup    = get_post_meta( $id, self::META_BACKUP, true );
			$categorie = get_post_meta( $id, self::META_CATEGORIE, true );
			$testo     = get_post_meta( $id, self::META_TESTO_PRIMA, true );
			$elementor = get_post_meta( $id, self::META_ELEMENTOR_PRIMA, true );

			// Un contenuto può aver avuto solo le categorie cambiate, o solo
			// il testo sovrascritto: senza questo il ripristino lo saltava.
			if ( ! $backup && ! $categorie && ! $testo && ! $elementor ) {
				continue;
			}

			// La struttura di Elementor torna quella di prima. Senza questo,
			// annullare rimetterebbe post_content - che su una pagina fatta
			// con Elementor non si vede - lasciando sulla pagina il testo
			// nuovo e facendo credere che l annulla non funzioni.
			if ( $elementor ) {
				update_post_meta( $id, '_elementor_data', wp_slash( (string) $elementor ) );
				delete_post_meta( $id, '_elementor_css' );
				delete_post_meta( $id, self::META_ELEMENTOR_PRIMA );

				if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
					\Elementor\Plugin::$instance->files_manager->clear_cache();
				}
			}

			// Il testo dell articolo torna quello di prima. E la rete che
			// conta: sovrascrivere un articolo pubblicato senza poter
			// tornare indietro non sarebbe accettabile.
			if ( $testo ) {
				$prima_testo = json_decode( $testo, true );

				if ( is_array( $prima_testo ) && isset( $prima_testo['post_content'] ) ) {
					wp_update_post(
						array(
							'ID'           => $id,
							'post_title'   => $prima_testo['post_title'] ?? get_the_title( $id ),
							'post_content' => $prima_testo['post_content'],
							'post_excerpt' => $prima_testo['post_excerpt'] ?? '',
						)
					);
				}

				delete_post_meta( $id, self::META_TESTO_PRIMA );
			}

			$prima = $backup ? json_decode( $backup, true ) : array();

			if ( ! is_array( $prima ) ) {
				$prima = array();
			}

			if ( $backup && $prima ) {
				foreach ( array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword' ) as $chiave ) {
					update_post_meta( $id, $chiave, $prima[ $chiave ] ?? '' );
				}
			}

			// Anche le categorie tornano come erano, se erano state cambiate.
			$categorie = get_post_meta( $id, self::META_CATEGORIE, true );

			if ( $categorie ) {
				$elenco = json_decode( $categorie, true );

				if ( is_array( $elenco ) && $elenco ) {
					wp_set_post_categories( $id, array_map( 'intval', $elenco ) );
				}

				delete_post_meta( $id, self::META_CATEGORIE );
			}

			if ( $backup && $prima ) {
				wp_update_post( array( 'ID' => $id, 'post_excerpt' => $prima['post_excerpt'] ?? '' ) );
				delete_post_meta( $id, self::META_BACKUP );
			}

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

		$contenuto = self::componi_testo( $corpo, $breve, $faq );

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
	 * Pubblica una bozza dentro l articolo originale.
	 *
	 * Il testo della bozza sostituisce quello dell articolo che è già online, che
	 * così conserva URL, data e storia su Google. WordPress salva in automatico
	 * una revisione del testo precedente: si torna indietro dall editor, voce
	 * "Revisioni". La copia in stato Bozza viene poi rimossa.
	 *
	 * @param WP_REST_Request $richiesta Richiesta con 'id' dell articolo originale.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function applica_bozza( $richiesta ) {
		$originale = (int) $richiesta->get_param( 'id' );
		$post      = get_post( $originale );

		if ( ! $post ) {
			return new WP_Error( 'mdi_post_assente', 'Articolo non trovato: ' . $originale, array( 'status' => 404 ) );
		}

		$bozze = get_posts(
			array(
				'post_type'   => $post->post_type,
				'post_status' => 'draft',
				'meta_key'    => self::META_BOZZA_DI,
				'meta_value'  => (string) $originale,
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);

		if ( empty( $bozze ) ) {
			return new WP_Error( 'mdi_bozza_assente', 'Nessuna bozza collegata a questo articolo.', array( 'status' => 404 ) );
		}

		$id_bozza = (int) $bozze[0];
		$bozza    = get_post( $id_bozza );

		if ( ! $bozza || '' === trim( (string) $bozza->post_content ) ) {
			return new WP_Error( 'mdi_bozza_vuota', 'La bozza è vuota.', array( 'status' => 400 ) );
		}

		$aggiornato = wp_update_post(
			array(
				'ID'           => $originale,
				'post_title'   => $bozza->post_title ?: $post->post_title,
				'post_content' => $bozza->post_content,
			),
			true
		);

		if ( is_wp_error( $aggiornato ) ) {
			return $aggiornato;
		}

		foreach ( array( 'rank_math_title', 'rank_math_description' ) as $chiave ) {
			$valore = get_post_meta( $id_bozza, $chiave, true );

			if ( '' !== $valore ) {
				update_post_meta( $originale, $chiave, $valore );
			}
		}

		wp_delete_post( $id_bozza, true );

		return rest_ensure_response(
			array(
				'ok'        => true,
				'articolo'  => $originale,
				'modifica'  => admin_url( 'post.php?post=' . $originale . '&action=edit' ),
			)
		);
	}

	/**
	 * Il testo completo di un contenuto riscritto.
	 *
	 * Sintesi iniziale, corpo e domande frequenti. Sta in un punto solo
	 * perche prima lo componevano in due: la bozza ci metteva le domande
	 * frequenti, la sovrascrittura no, e sul sito l articolo finiva con
	 * "Ecco alcune delle domande piu comuni" e poi il vuoto.
	 *
	 * @param string $corpo Corpo HTML.
	 * @param string $breve Sintesi iniziale.
	 * @param array  $faq   Domande e risposte.
	 * @return string
	 */
	public static function componi_testo( $corpo, $breve, $faq ) {
		$contenuto = '';

		if ( '' !== trim( (string) $breve ) ) {
			$contenuto .= '<div class="mdi-in-breve"><p><strong>In breve:</strong> ' . esc_html( $breve ) . "</p></div>\n\n";
		}

		$contenuto .= wp_kses_post( (string) $corpo );

		foreach ( (array) $faq as $i => $voce ) {
			if ( 0 === $i ) {
				$contenuto .= "\n\n<h2>Domande frequenti</h2>\n";
			}

			$contenuto .= '<h3>' . esc_html( $voce['domanda'] ?? '' ) . '</h3>' . "\n"
				. '<p>' . esc_html( $voce['risposta'] ?? '' ) . "</p>\n";
		}

		return $contenuto;
	}

	/**
	 * Scrive il testo nuovo direttamente sull articolo pubblicato.
	 *
	 * La strada che passava da una bozza di WordPress creava un secondo
	 * articolo che poi andava applicato a mano: due passaggi e un doppione
	 * in bacheca per ottenere una cosa sola. Qui si scrive sull articolo che
	 * esiste gia, dopo aver messo da parte quello che c era.
	 *
	 * Due reti di sicurezza: la revisione di WordPress, che si crea da sola
	 * a ogni wp_update_post, e una copia del testo precedente in un meta,
	 * perche su parecchi hosting le revisioni sono disattivate.
	 *
	 * @param WP_REST_Request $richiesta Richiesta.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function sovrascrivi( $richiesta ) {
		$id   = (int) $richiesta->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post ) {
			return new WP_Error( 'mdi_post_assente', 'Articolo non trovato: ' . $id, array( 'status' => 404 ) );
		}

		$contenuto = (string) $richiesta->get_param( 'contenuto' );

		if ( '' === trim( $contenuto ) ) {
			return new WP_Error( 'mdi_contenuto_vuoto', 'Nessun contenuto da scrivere.', array( 'status' => 400 ) );
		}

		// Sintesi e domande frequenti fanno parte dell articolo, non sono
		// contorno: senza, sul sito il testo finisce con "Ecco alcune delle
		// domande piu comuni" e poi il vuoto.
		$contenuto = self::componi_testo(
			$contenuto,
			(string) $richiesta->get_param( 'in_breve' ),
			(array) $richiesta->get_param( 'faq' )
		);

		// Se la pagina la disegna un costruttore visuale, quello che si vede
		// non viene da post_content ma dai dati del costruttore. Scrivere in
		// post_content riuscirebbe senza errori e non cambierebbe niente di
		// visibile: il titolo cambia, il testo no. Meglio rifiutare che far
		// credere fatto un lavoro che non si vede.
		$costruttore = self::costruttore( $id );

		// Con Elementor si scrive dentro al suo blocco di testo, che e il
		// posto dove il testo si vede davvero. Se la pagina non e fatta in
		// un modo che si possa toccare senza indovinare, scrivi_in_elementor
		// rifiuta e spiega cosa ha trovato.
		if ( 'Elementor' === $costruttore && ! $richiesta->get_param( 'forza' ) ) {
			$struttura = self::struttura_elementor( $id );

			if ( ! $struttura['post_content'] ) {
				$scritto = self::scrivi_in_elementor( $id, $contenuto );

				if ( is_wp_error( $scritto ) ) {
					return $scritto;
				}

				$dentro_elementor = true;
			}
		} elseif ( '' !== $costruttore && ! $richiesta->get_param( 'forza' ) ) {
			return new WP_Error(
				'mdi_costruttore_visuale',
				sprintf(
					'Questo contenuto e costruito con %s: il testo che si vede non sta in post_content, quindi sovrascriverlo non cambierebbe la pagina. '
					. 'Il testo nuovo va incollato dentro al costruttore.',
					$costruttore
				),
				array( 'status' => 409, 'costruttore' => $costruttore )
			);
		}

		// La copia si scrive una volta sola: se si sovrascrive due volte, la
		// copia deve restare quella del testo originale, non della versione
		// intermedia, altrimenti l annulla riporta a meta strada.
		if ( ! get_post_meta( $id, self::META_TESTO_PRIMA, true ) ) {
			update_post_meta(
				$id,
				self::META_TESTO_PRIMA,
				wp_json_encode(
					array(
						'post_title'   => $post->post_title,
						'post_content' => $post->post_content,
						'post_excerpt' => $post->post_excerpt,
						'quando'       => current_time( 'mysql' ),
					)
				)
			);
		}

		$campi = array( 'ID' => $id, 'post_content' => $contenuto );

		$titolo = (string) $richiesta->get_param( 'titolo' );

		if ( '' !== trim( $titolo ) ) {
			$campi['post_title'] = sanitize_text_field( $titolo );
		}

		$estratto = (string) $richiesta->get_param( 'estratto' );

		if ( '' !== trim( $estratto ) ) {
			$campi['post_excerpt'] = wp_kses_post( $estratto );
		}

		$aggiornato = wp_update_post( $campi, true );

		if ( is_wp_error( $aggiornato ) ) {
			return $aggiornato;
		}

		// Le meta seguono lo stesso backup gia usato dall annulla delle meta.
		$meta = array(
			'rank_math_title'       => (string) $richiesta->get_param( 'meta_title' ),
			'rank_math_description' => (string) $richiesta->get_param( 'meta_description' ),
		);

		$da_scrivere = array_filter( $meta, static function ( $v ) { return '' !== trim( $v ); } );

		if ( $da_scrivere && ! get_post_meta( $id, self::META_BACKUP, true ) ) {
			update_post_meta(
				$id,
				self::META_BACKUP,
				wp_json_encode(
					array(
						'rank_math_title'         => get_post_meta( $id, 'rank_math_title', true ),
						'rank_math_description'   => get_post_meta( $id, 'rank_math_description', true ),
						'rank_math_focus_keyword' => get_post_meta( $id, 'rank_math_focus_keyword', true ),
						'post_excerpt'            => $post->post_excerpt,
					)
				)
			);
		}

		foreach ( $da_scrivere as $chiave => $valore ) {
			update_post_meta( $id, $chiave, sanitize_text_field( $valore ) );
		}

		return rest_ensure_response(
			array(
				'ok'        => true,
				'articolo'  => $id,
				'dove'      => ! empty( $dentro_elementor ) ? 'elementor' : 'contenuto',
				'modifica'  => admin_url( 'post.php?post=' . $id . '&action=edit' ),
				'indirizzo' => get_permalink( $id ),
			)
		);
	}

	/**
	 * Per ognuno degli id, quale costruttore visuale lo disegna.
	 *
	 * Serve a dirlo PRIMA che qualcuno prema "sovrascrivi": scoprirlo dopo
	 * significa aver guardato un articolo convinti che fosse cambiato.
	 *
	 * @param WP_REST_Request $richiesta Richiesta con 'ids'.
	 * @return WP_REST_Response
	 */
	public static function costruttori( $richiesta ) {
		$esito = array();

		foreach ( (array) $richiesta->get_param( 'ids' ) as $id ) {
			$id = (int) $id;

			if ( ! $id ) {
				continue;
			}

			$esito[ (string) $id ] = self::costruttore( $id );
		}

		return rest_ensure_response( array( 'ok' => true, 'costruttori' => $esito ) );
	}

	/**
	 * Quale costruttore visuale disegna questo contenuto, se ce n e uno.
	 *
	 * Su un sito costruito cosi, post_content e un residuo: la pagina si
	 * compone dai dati del costruttore. Una riscrittura scritta in
	 * post_content non darebbe nessun errore e non si vedrebbe da nessuna
	 * parte - ed e esattamente quello che e successo prima che questo
	 * controllo esistesse.
	 *
	 * @param int $id Contenuto.
	 * @return string Nome del costruttore, vuoto se il contenuto e normale.
	 */
	public static function costruttore( $id ) {
		$id = (int) $id;

		// Elementor lo si chiede a Elementor: e la sua API a sapere se un
		// contenuto e costruito con lui. Indovinarlo dai meta porta a
		// sbagliare, ed e quello che e successo: _elementor_data resta sul
		// post anche solo per averlo aperto una volta nell editor visuale e
		// poi essere tornati indietro, e quei contenuti si renderizzano da
		// post_content come tutti gli altri.
		if ( class_exists( '\\Elementor\\Plugin' ) ) {
			$elementor = \Elementor\Plugin::$instance;

			if ( isset( $elementor->documents ) ) {
				$documento = $elementor->documents->get( $id );

				if ( $documento && method_exists( $documento, 'is_built_with_elementor' ) && $documento->is_built_with_elementor() ) {
					return 'Elementor';
				}

				// Elementor c e e dice di no: e la risposta buona, non serve
				// ripiegare sui meta.
				if ( $documento ) {
					return self::altroCostruttore( $id );
				}
			}
		}

		// Elementor non e attivo (o non risponde): allora vale il suo
		// interruttore. Solo "builder" significa che la pagina la compone
		// lui; i dati da soli sono un residuo e non contano.
		if ( 'builder' === (string) get_post_meta( $id, '_elementor_edit_mode', true ) && get_post_meta( $id, '_elementor_data', true ) ) {
			return 'Elementor';
		}

		return self::altroCostruttore( $id );
	}

	/**
	 * Che cosa c e dentro alla struttura di Elementor di un contenuto.
	 *
	 * Non si tocca niente prima di aver guardato: la struttura e il formato
	 * interno di Elementor, cambia fra le versioni, e un articolo costruito
	 * in un modo che non si e previsto va lasciato stare invece di essere
	 * rovinato.
	 *
	 * @param int $id Contenuto.
	 * @return array 'testi' (widget di testo con lunghezza e percorso),
	 *               'post_content' (vero se un widget rende post_content),
	 *               'errore'.
	 */
	public static function struttura_elementor( $id ) {
		$grezzo = get_post_meta( (int) $id, '_elementor_data', true );

		if ( is_array( $grezzo ) ) {
			$grezzo = reset( $grezzo );
		}

		$albero = json_decode( (string) $grezzo, true );

		if ( ! is_array( $albero ) ) {
			return array( 'testi' => array(), 'post_content' => false, 'errore' => 'struttura di Elementor illeggibile' );
		}

		$testi        = array();
		$post_content = false;

		// Percorso come catena di indici: e cosi che si torna a scrivere nel
		// punto esatto senza doversi fidare degli identificativi.
		$scendi = static function ( $nodi, $percorso ) use ( &$scendi, &$testi, &$post_content ) {
			foreach ( (array) $nodi as $i => $nodo ) {
				if ( ! is_array( $nodo ) ) {
					continue;
				}

				$qui  = array_merge( $percorso, array( $i ) );
				$tipo = (string) ( $nodo['widgetType'] ?? '' );

				// Questo widget rende post_content: allora il testo vero sta
				// li, e la sovrascrittura normale funziona.
				if ( in_array( $tipo, array( 'theme-post-content', 'post-content' ), true ) ) {
					$post_content = true;
				}

				if ( 'text-editor' === $tipo && isset( $nodo['settings']['editor'] ) ) {
					$testi[] = array(
						'percorso'  => $qui,
						'caratteri' => strlen( wp_strip_all_tags( (string) $nodo['settings']['editor'] ) ),
					);
				}

				if ( ! empty( $nodo['elements'] ) ) {
					$scendi( $nodo['elements'], array_merge( $qui, array( 'elements' ) ) );
				}
			}
		};

		$scendi( $albero, array() );

		// Il piu lungo per primo: e quello che contiene l articolo.
		usort( $testi, static function ( $a, $b ) { return $b['caratteri'] <=> $a['caratteri']; } );

		return array( 'testi' => $testi, 'post_content' => $post_content, 'errore' => '' );
	}

	/**
	 * Riassunto della struttura per piu contenuti, senza toccarli.
	 *
	 * @param WP_REST_Request $richiesta Richiesta con 'ids'.
	 * @return WP_REST_Response
	 */
	public static function strutture_elementor( $richiesta ) {
		$esito = array();

		foreach ( (array) $richiesta->get_param( 'ids' ) as $id ) {
			$id = (int) $id;

			if ( ! $id || 'Elementor' !== self::costruttore( $id ) ) {
				continue;
			}

			$struttura = self::struttura_elementor( $id );

			$esito[ (string) $id ] = array(
				'blocchi'      => count( $struttura['testi'] ),
				'caratteri'    => $struttura['testi'] ? (int) $struttura['testi'][0]['caratteri'] : 0,
				'post_content' => (bool) $struttura['post_content'],
				'scrivibile'   => '' === $struttura['errore'] && ( $struttura['post_content'] || 1 === count( $struttura['testi'] ) ),
				'errore'       => $struttura['errore'],
			);
		}

		return rest_ensure_response( array( 'ok' => true, 'strutture' => $esito ) );
	}

	/**
	 * Scrive il testo nuovo dentro al blocco di testo di Elementor.
	 *
	 * Si interviene solo quando c e un unico blocco di testo: e l articolo.
	 * Se ce ne sono piu di uno non si puo sapere quale sia il corpo e quale
	 * una didascalia o una promozione, e indovinare vorrebbe dire cancellare
	 * qualcosa che serviva. In quel caso si rifiuta e si dice cosa si e
	 * trovato.
	 *
	 * @param int    $id        Contenuto.
	 * @param string $contenuto HTML nuovo.
	 * @return true|WP_Error
	 */
	public static function scrivi_in_elementor( $id, $contenuto ) {
		$id        = (int) $id;
		$struttura = self::struttura_elementor( $id );

		if ( '' !== $struttura['errore'] ) {
			return new WP_Error( 'mdi_elementor_illeggibile', $struttura['errore'], array( 'status' => 422 ) );
		}

		if ( ! $struttura['testi'] ) {
			return new WP_Error(
				'mdi_elementor_senza_testo',
				'In questa pagina Elementor non ha nessun blocco di testo: il contenuto e fatto di altri elementi e va cambiato a mano.',
				array( 'status' => 422 )
			);
		}

		if ( count( $struttura['testi'] ) > 1 ) {
			return new WP_Error(
				'mdi_elementor_piu_blocchi',
				sprintf(
					'Questa pagina ha %d blocchi di testo in Elementor: non si puo sapere quale sia l articolo e quale una didascalia, quindi non ci si scrive sopra. Va cambiata a mano.',
					count( $struttura['testi'] )
				),
				array( 'status' => 409 )
			);
		}

		$grezzo = get_post_meta( $id, '_elementor_data', true );

		if ( is_array( $grezzo ) ) {
			$grezzo = reset( $grezzo );
		}

		$albero = json_decode( (string) $grezzo, true );

		// La copia si scrive una volta sola, come per il testo: dopo due
		// passaggi deve restare l originale, non la versione intermedia.
		if ( ! get_post_meta( $id, self::META_ELEMENTOR_PRIMA, true ) ) {
			update_post_meta( $id, self::META_ELEMENTOR_PRIMA, wp_slash( (string) $grezzo ) );
		}

		// Si cammina fino al nodo trovato prima e si sostituisce solo il suo
		// testo: tutto il resto della pagina resta identico.
		$riferimento = &$albero;

		foreach ( $struttura['testi'][0]['percorso'] as $passo ) {
			if ( ! isset( $riferimento[ $passo ] ) ) {
				return new WP_Error( 'mdi_elementor_percorso', 'Il blocco di testo non si trova piu dove era.', array( 'status' => 500 ) );
			}

			$riferimento = &$riferimento[ $passo ];
		}

		$riferimento['settings']['editor'] = $contenuto;
		unset( $riferimento );

		$nuovo = wp_json_encode( $albero, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( ! $nuovo ) {
			return new WP_Error( 'mdi_elementor_json', 'Non si e riusciti a ricomporre la struttura di Elementor.', array( 'status' => 500 ) );
		}

		// wp_slash perche update_post_meta toglie le barre: senza, le
		// virgolette dentro all HTML spezzerebbero il JSON di Elementor.
		update_post_meta( $id, '_elementor_data', wp_slash( $nuovo ) );

		// Il CSS di Elementor e generato e messo in cache per contenuto:
		// cambiati i dati va rifatto, altrimenti la pagina puo restare con
		// lo stile di prima.
		delete_post_meta( $id, '_elementor_css' );

		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		return true;
	}

	/**
	 * Costruttori diversi da Elementor.
	 *
	 * @param int $id Contenuto.
	 * @return string
	 */
	private static function altroCostruttore( $id ) {
		$altri = array(
			'_et_pb_use_builder'    => 'Divi',
			'panels_data'           => 'SiteOrigin Page Builder',
			'_fl_builder_enabled'   => 'Beaver Builder',
			'_wpb_vc_js_status'     => 'WPBakery',
			'ct_builder_shortcodes' => 'Oxygen',
			'_brizy'                => 'Brizy',
		);

		foreach ( $altri as $chiave => $nome ) {
			if ( get_post_meta( $id, $chiave, true ) ) {
				return $nome;
			}
		}

		return '';
	}

	/**
	 * Sposta nel cestino i contenuti indicati.
	 *
	 * Il cestino di WordPress è reversibile: i contenuti restano recuperabili
	 * finché non vengono eliminati definitivamente a mano.
	 *
	 * @param WP_REST_Request $richiesta Richiesta con 'ids'.
	 * @return WP_REST_Response
	 */
	public static function cestina( $richiesta ) {
		$ids       = (array) $richiesta->get_param( 'ids' );
		$cestinati = 0;
		$saltati   = 0;

		foreach ( $ids as $id ) {
			$id = (int) $id;

			if ( ! get_post( $id ) ) {
				$saltati++;
				continue;
			}

			// Mai l eliminazione definitiva: solo il cestino, da cui si recupera.
			if ( wp_trash_post( $id ) ) {
				$cestinati++;
			} else {
				$saltati++;
			}
		}

		return rest_ensure_response( array( 'ok' => true, 'cestinati' => $cestinati, 'saltati' => $saltati ) );
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

			// Prima di sostituire si annota com era: wp_set_post_categories
			// cancella tutte le categorie precedenti, e senza questa riga non
			// ci sarebbe modo di tornare indietro.
			$prima = wp_get_post_categories( $id );

			if ( $prima && ! get_post_meta( $id, self::META_CATEGORIE, true ) ) {
				update_post_meta( $id, self::META_CATEGORIE, wp_json_encode( array_map( 'intval', $prima ) ) );
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
