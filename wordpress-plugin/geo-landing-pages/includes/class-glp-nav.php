<?php
/**
 * Integrazione con il menu del tema.
 *
 * Sulle pagine di una città il menu dell'intestazione può mostrare la
 * navigazione di quella zona, come voce a tendina oppure al posto del
 * menu abituale.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Nav {

	/** Base degli ID per non sovrapporsi alle voci di menu vere. */
	const ID_BASE = 990000;

	public static function init() {
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'filter_menu' ), 20, 2 );
		add_action( 'admin_post_glp_build_menu', array( __CLASS__, 'handle_build_menu' ) );
	}

	/**
	 * Crea (o aggiorna) un menu WordPress vero per una città.
	 *
	 * I tipi di servizio non compaiono in Aspetto → Menu perché sono
	 * etichette senza un indirizzo proprio. Quello che serve davvero è un
	 * menu con la città e sotto le sue pagine: lo costruiamo qui, poi si
	 * gestisce a mano come qualsiasi altro menu.
	 *
	 * @param int $citta_id ID della città.
	 * @return int|WP_Error ID del menu.
	 */
	public static function build_menu( $citta_id ) {
		$citta = get_post( (int) $citta_id );
		if ( ! $citta || GLP_POST_TYPE !== $citta->post_type || 0 !== (int) $citta->post_parent ) {
			return new WP_Error( 'glp_no_city', __( 'Città non valida.', 'geo-landing-pages' ) );
		}

		$nome = $citta->post_title;
		$menu = wp_get_nav_menu_object( $nome );

		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( $nome );
			if ( is_wp_error( $menu_id ) ) {
				return $menu_id;
			}
		} else {
			$menu_id = (int) $menu->term_id;

			// Ripulisce le voci create in precedenza, per non duplicarle.
			foreach ( wp_get_nav_menu_items( $menu_id ) as $voce ) {
				wp_delete_post( $voce->ID, true );
			}
		}

		// La città come prima voce.
		$padre = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => $citta->post_title,
				'menu-item-object'    => GLP_POST_TYPE,
				'menu-item-object-id' => $citta->ID,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);

		if ( is_wp_error( $padre ) ) {
			return $padre;
		}

		// Pagine e servizi come sottovoci, nello stesso ordine del menu interno.
		$figli = get_posts(
			array(
				'post_type'      => GLP_POST_TYPE,
				'post_parent'    => $citta->ID,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			)
		);

		$ordine = class_exists( 'GLP_Pages' ) ? array_keys( GLP_Pages::city_definitions() ) : array();
		$pagine  = array();
		$servizi = array();

		foreach ( $figli as $figlio ) {
			$tipo = get_post_meta( $figlio->ID, GLP_CITY_PAGE_META, true );
			if ( $tipo ) {
				$pos      = array_search( $tipo, $ordine, true );
				$pagine[] = array( 'post' => $figlio, 'pos' => false === $pos ? 99 : $pos );
			} else {
				$servizi[] = array( 'post' => $figlio, 'pos' => 0 );
			}
		}

		usort(
			$pagine,
			static function ( $a, $b ) {
				return $a['pos'] <=> $b['pos'];
			}
		);

		$creati = 1;
		foreach ( array_merge( $pagine, $servizi ) as $voce ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => GLP_Content::menu_label( $voce['post'] ),
					'menu-item-object'    => GLP_POST_TYPE,
					'menu-item-object-id' => $voce['post']->ID,
					'menu-item-type'      => 'post_type',
					'menu-item-parent-id' => $padre,
					'menu-item-status'    => 'publish',
				)
			);
			$creati++;
		}

		return $menu_id;
	}

	/** Crea il menu dalla schermata di diagnostica. */
	public static function handle_build_menu() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'geo-landing-pages' ) );
		}
		check_admin_referer( 'glp_build_menu' );

		$citta_id = isset( $_POST['citta'] ) ? (int) $_POST['citta'] : 0;
		$esito    = self::build_menu( $citta_id );

		$argomenti = is_wp_error( $esito )
			? array( 'glp_menu' => 'errore' )
			: array( 'glp_menu' => $esito );

		wp_safe_redirect( add_query_arg( $argomenti, admin_url( 'edit.php?post_type=' . GLP_POST_TYPE . '&page=glp-health' ) ) );
		exit;
	}

	/**
	 * Posizioni di menu registrate dal tema.
	 *
	 * @return array
	 */
	public static function locations() {
		$posizioni = get_registered_nav_menus();
		return is_array( $posizioni ) ? $posizioni : array();
	}

	/**
	 * Inserisce le voci della città nel menu del tema.
	 *
	 * @param array  $items Voci del menu.
	 * @param object $args  Argomenti di wp_nav_menu().
	 * @return array
	 */
	public static function filter_menu( $items, $args ) {
		$modo = GLP_Settings::get( 'nav_mode', 'off' );
		if ( 'off' === $modo || is_admin() ) {
			return $items;
		}

		if ( ! is_singular( GLP_POST_TYPE ) ) {
			return $items;
		}

		// Solo nella posizione scelta, se ne è stata scelta una.
		$posizione = GLP_Settings::get( 'nav_location', '' );
		if ( '' !== $posizione ) {
			$corrente = isset( $args->theme_location ) ? $args->theme_location : '';
			if ( $corrente !== $posizione ) {
				return $items;
			}
		}

		$nostre = self::city_items( (int) get_queried_object_id() );
		if ( empty( $nostre ) ) {
			return $items;
		}

		if ( 'sostituisci' === $modo ) {
			return $nostre;
		}

		return array_merge( $items, $nostre );
	}

	/**
	 * Costruisce le voci di menu della città.
	 *
	 * @param int $post_id ID della pagina mostrata.
	 * @return array
	 */
	public static function city_items( $post_id ) {
		$citta = GLP_Post_Types::city_post( $post_id );
		if ( ! $citta ) {
			return array();
		}

		$figli = get_posts(
			array(
				'post_type'      => GLP_POST_TYPE,
				'post_parent'    => $citta->ID,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			)
		);

		$ordine = class_exists( 'GLP_Pages' ) ? array_keys( GLP_Pages::city_definitions() ) : array();

		$pagine  = array();
		$servizi = array();
		foreach ( $figli as $figlio ) {
			$tipo = get_post_meta( $figlio->ID, GLP_CITY_PAGE_META, true );
			if ( $tipo ) {
				$posizione = array_search( $tipo, $ordine, true );
				$pagine[]  = array( 'post' => $figlio, 'posizione' => false === $posizione ? 99 : $posizione );
			} else {
				$servizi[] = array( 'post' => $figlio, 'posizione' => 0 );
			}
		}

		usort(
			$pagine,
			static function ( $a, $b ) {
				return $a['posizione'] <=> $b['posizione'];
			}
		);

		$figli_ordinati = array_merge( $pagine, $servizi );

		$voci     = array();
		$id_padre = self::ID_BASE + (int) $citta->ID;

		$voci[] = self::make_item(
			array(
				'id'      => $id_padre,
				'parent'  => 0,
				'post'    => $citta,
				'title'   => $citta->post_title,
				'current' => (int) $citta->ID === (int) $post_id,
				'figli'   => ! empty( $figli_ordinati ),
				'ordine'  => 1,
			)
		);

		$n = 1;
		foreach ( $figli_ordinati as $voce ) {
			$n++;
			$figlio = $voce['post'];

			$voci[] = self::make_item(
				array(
					'id'      => self::ID_BASE + (int) $figlio->ID,
					'parent'  => $id_padre,
					'post'    => $figlio,
					'title'   => GLP_Content::menu_label( $figlio ),
					'current' => (int) $figlio->ID === (int) $post_id,
					'figli'   => false,
					'ordine'  => $n,
				)
			);
		}

		/**
		 * Voci di menu della città.
		 *
		 * @param array $voci    Voci.
		 * @param int   $post_id ID della pagina mostrata.
		 */
		return apply_filters( 'glp_nav_items', $voci, $post_id );
	}

	/**
	 * Crea un oggetto voce di menu compatibile con i temi.
	 *
	 * @param array $dati Dati della voce.
	 * @return object
	 */
	private static function make_item( $dati ) {
		$classi = array( 'menu-item', 'menu-item-type-post_type', 'menu-item-object-' . GLP_POST_TYPE, 'glp-nav-item' );

		if ( ! empty( $dati['figli'] ) ) {
			$classi[] = 'menu-item-has-children';
		}
		if ( ! empty( $dati['current'] ) ) {
			$classi[] = 'current-menu-item';
		}

		$voce = new stdClass();

		$voce->ID               = (int) $dati['id'];
		$voce->db_id            = (int) $dati['id'];
		$voce->menu_item_parent = (string) $dati['parent'];
		$voce->object_id        = (int) $dati['post']->ID;
		$voce->object           = GLP_POST_TYPE;
		$voce->type             = 'post_type';
		$voce->type_label       = __( 'Landing locale', 'geo-landing-pages' );
		$voce->title            = $dati['title'];
		$voce->url              = get_permalink( $dati['post'] );
		$voce->target           = '';
		$voce->attr_title       = '';
		$voce->description      = '';
		$voce->classes          = $classi;
		$voce->xfn              = '';
		$voce->menu_order       = (int) $dati['ordine'];
		$voce->post_parent      = 0;
		$voce->current          = ! empty( $dati['current'] );
		$voce->current_item_ancestor = false;
		$voce->current_item_parent   = false;

		return $voce;
	}
}
