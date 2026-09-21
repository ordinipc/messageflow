<?php
/**
 * Tipi di servizio collegati alle pagine del sito.
 *
 * Ogni tipo può puntare alla pagina nazionale del servizio già presente
 * sul sito (per esempio /duplicazione-chiavi-auto/). Da lì il plugin
 * costruisce due elenchi a schede: i servizi sulla pagina "Servizi", e
 * le città sulla pagina di ciascun servizio.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Services {

	const META_PAGE  = 'glp_page';
	const META_IMAGE = 'glp_image';

	public static function init() {
		add_action( GLP_TAXONOMY . '_add_form_fields', array( __CLASS__, 'add_fields' ) );
		add_action( GLP_TAXONOMY . '_edit_form_fields', array( __CLASS__, 'edit_fields' ), 10, 2 );
		add_action( 'created_' . GLP_TAXONOMY, array( __CLASS__, 'save_fields' ) );
		add_action( 'edited_' . GLP_TAXONOMY, array( __CLASS__, 'save_fields' ) );

		add_filter( 'manage_edit-' . GLP_TAXONOMY . '_columns', array( __CLASS__, 'columns' ) );
		add_filter( 'manage_' . GLP_TAXONOMY . '_custom_column', array( __CLASS__, 'column' ), 10, 3 );
	}

	/* ------------------------------------------------------------------
	 * Dati
	 * ------------------------------------------------------------------ */

	/**
	 * Pagina collegata a un tipo di servizio.
	 *
	 * @param int $term_id ID termine.
	 * @return WP_Post|null
	 */
	public static function page_of( $term_id ) {
		$page_id = (int) get_term_meta( (int) $term_id, self::META_PAGE, true );
		if ( ! $page_id ) {
			return null;
		}
		$page = get_post( $page_id );
		return $page && 'publish' === $page->post_status ? $page : null;
	}

	/**
	 * Tipo di servizio collegato a una pagina.
	 *
	 * @param int $page_id ID pagina.
	 * @return WP_Term|null
	 */
	public static function term_of_page( $page_id ) {
		$termini = get_terms(
			array(
				'taxonomy'   => GLP_TAXONOMY,
				'hide_empty' => false,
				'meta_key'   => self::META_PAGE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => (int) $page_id,  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		if ( is_wp_error( $termini ) || empty( $termini ) ) {
			return null;
		}
		return $termini[0];
	}

	/**
	 * Pagine città in cui quel servizio è pubblicato.
	 *
	 * @param int $term_id ID termine.
	 * @return array Elenco di array post/citta.
	 */
	public static function cities_with( $term_id ) {
		$posts = get_posts(
			array(
				'post_type'      => GLP_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => GLP_TAXONOMY,
						'field'    => 'term_id',
						'terms'    => (int) $term_id,
					),
				),
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			if ( 0 === (int) $post->post_parent ) {
				continue; // Senza città non è una pagina locale.
			}
			$citta = get_post( $post->post_parent );
			if ( ! $citta ) {
				continue;
			}
			$out[] = array(
				'post'  => $post,
				'citta' => $citta,
			);
		}

		return $out;
	}

	/* ------------------------------------------------------------------
	 * Campi del termine
	 * ------------------------------------------------------------------ */

	/** Campi nella schermata di creazione. */
	public static function add_fields() {
		wp_nonce_field( 'glp_service_fields', 'glp_service_nonce' );
		?>
		<div class="form-field">
			<label for="glp_page"><?php esc_html_e( 'Pagina del servizio', 'geo-landing-pages' ); ?></label>
			<?php
			wp_dropdown_pages(
				array(
					'name'              => 'glp_page',
					'id'                => 'glp_page',
					'show_option_none'  => __( '— nessuna —', 'geo-landing-pages' ),
					'option_none_value' => '0',
				)
			);
			?>
			<p><?php esc_html_e( 'La pagina del sito che descrive questo servizio. È lì che puntano le schede.', 'geo-landing-pages' ); ?></p>
		</div>
		<div class="form-field">
			<label for="glp_image"><?php esc_html_e( 'Immagine o icona (URL)', 'geo-landing-pages' ); ?></label>
			<input type="url" name="glp_image" id="glp_image" value="" />
		</div>
		<?php
	}

	/**
	 * Campi nella schermata di modifica.
	 *
	 * @param WP_Term $term Termine.
	 */
	public static function edit_fields( $term ) {
		$page_id = (int) get_term_meta( $term->term_id, self::META_PAGE, true );
		$image   = (string) get_term_meta( $term->term_id, self::META_IMAGE, true );
		wp_nonce_field( 'glp_service_fields', 'glp_service_nonce' );
		?>
		<tr class="form-field">
			<th scope="row"><label for="glp_page"><?php esc_html_e( 'Pagina del servizio', 'geo-landing-pages' ); ?></label></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'glp_page',
						'id'                => 'glp_page',
						'selected'          => $page_id,
						'show_option_none'  => __( '— nessuna —', 'geo-landing-pages' ),
						'option_none_value' => '0',
					)
				);
				?>
				<p class="description"><?php esc_html_e( 'La pagina del sito che descrive questo servizio. È lì che puntano le schede, ed è lì che puoi mostrare le città con lo shortcode [glp_servizio_citta].', 'geo-landing-pages' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="glp_image"><?php esc_html_e( 'Immagine o icona (URL)', 'geo-landing-pages' ); ?></label></th>
			<td><input type="url" name="glp_image" id="glp_image" value="<?php echo esc_attr( $image ); ?>" class="regular-text" /></td>
		</tr>
		<?php
	}

	/**
	 * Salva i campi del termine.
	 *
	 * @param int $term_id ID termine.
	 */
	public static function save_fields( $term_id ) {
		if ( ! isset( $_POST['glp_service_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['glp_service_nonce'] ) ), 'glp_service_fields' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		if ( isset( $_POST['glp_page'] ) ) {
			$page_id = (int) $_POST['glp_page'];
			if ( $page_id ) {
				update_term_meta( $term_id, self::META_PAGE, $page_id );
			} else {
				delete_term_meta( $term_id, self::META_PAGE );
			}
		}

		if ( isset( $_POST['glp_image'] ) ) {
			$url = esc_url_raw( wp_unslash( $_POST['glp_image'] ) );
			if ( '' !== $url ) {
				update_term_meta( $term_id, self::META_IMAGE, $url );
			} else {
				delete_term_meta( $term_id, self::META_IMAGE );
			}
		}
	}

	/**
	 * Colonne dell'elenco tipi.
	 *
	 * @param array $columns Colonne.
	 * @return array
	 */
	public static function columns( $columns ) {
		$nuove = array();
		foreach ( $columns as $chiave => $etichetta ) {
			$nuove[ $chiave ] = $etichetta;
			if ( 'description' === $chiave ) {
				$nuove['glp_page'] = __( 'Pagina collegata', 'geo-landing-pages' );
			}
		}
		if ( ! isset( $nuove['glp_page'] ) ) {
			$nuove['glp_page'] = __( 'Pagina collegata', 'geo-landing-pages' );
		}
		return $nuove;
	}

	/**
	 * Contenuto delle colonne aggiunte.
	 *
	 * @param string $contenuto Contenuto.
	 * @param string $colonna   Colonna.
	 * @param int    $term_id   ID termine.
	 * @return string
	 */
	public static function column( $contenuto, $colonna, $term_id ) {
		if ( 'glp_page' !== $colonna ) {
			return $contenuto;
		}

		$page = self::page_of( $term_id );
		if ( ! $page ) {
			return '<span style="color:#b32d2e">' . esc_html__( 'nessuna', 'geo-landing-pages' ) . '</span>';
		}

		return '<a href="' . esc_url( get_permalink( $page ) ) . '" target="_blank" rel="noopener">'
			. esc_html( $page->post_title ) . '</a>';
	}

	/* ------------------------------------------------------------------
	 * Schede
	 * ------------------------------------------------------------------ */

	/**
	 * Schede dei servizi: [glp_servizi_cards]
	 *
	 * @param array $atts Attributi.
	 * @return string
	 */
	public static function cards( $atts ) {
		$atts = shortcode_atts(
			array(
				'colonne'   => 3,
				'descrizione' => 'si',
				'citta'     => 'si',
			),
			$atts,
			'glp_servizi_cards'
		);

		$termini = get_terms(
			array(
				'taxonomy'   => GLP_TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $termini ) || empty( $termini ) ) {
			return '';
		}

		$html = '<ul class="glp-grid" style="--glp-cols:' . (int) $atts['colonne'] . '">';

		foreach ( $termini as $termine ) {
			$page  = self::page_of( $termine->term_id );
			$url   = $page ? get_permalink( $page ) : '';
			$image = (string) get_term_meta( $termine->term_id, self::META_IMAGE, true );
			$citta = 'si' === $atts['citta'] ? count( self::cities_with( $termine->term_id ) ) : 0;

			$interno = '';
			if ( '' !== $image ) {
				$interno .= '<span class="glp-grid__media"><img src="' . esc_url( $image ) . '" alt="" loading="lazy" /></span>';
			}
			$interno .= '<span class="glp-grid__title">' . esc_html( $termine->name ) . '</span>';

			if ( 'si' === $atts['descrizione'] && '' !== $termine->description ) {
				$interno .= '<span class="glp-grid__text">' . esc_html( wp_trim_words( $termine->description, 26 ) ) . '</span>';
			}

			if ( $citta > 0 ) {
				$interno .= '<span class="glp-grid__meta">' . esc_html(
					sprintf(
						/* translators: %d: numero di città. */
						_n( 'in %d città', 'in %d città', $citta, 'geo-landing-pages' ),
						$citta
					)
				) . '</span>';
			}

			$html .= '<li class="glp-grid__item">';
			$html .= '' !== $url
				? '<a class="glp-grid__link" href="' . esc_url( $url ) . '">' . $interno . '</a>'
				: '<div class="glp-grid__link glp-grid__link--muto">' . $interno . '</div>';
			$html .= '</li>';
		}

		return $html . '</ul>';
	}

	/**
	 * Città in cui il servizio è disponibile: [glp_servizio_citta]
	 *
	 * Senza attributi riconosce da sola il servizio, se la pagina corrente
	 * è quella collegata al tipo.
	 *
	 * @param array $atts Attributi.
	 * @return string
	 */
	public static function city_cards( $atts ) {
		$atts = shortcode_atts(
			array(
				'tipo'    => '',
				'colonne' => 4,
			),
			$atts,
			'glp_servizio_citta'
		);

		$termine = null;

		if ( '' !== $atts['tipo'] ) {
			$trovato = get_term_by( 'slug', sanitize_title( $atts['tipo'] ), GLP_TAXONOMY );
			$termine = $trovato ? $trovato : null;
		} else {
			$page_id = get_the_ID();
			$termine = $page_id ? self::term_of_page( $page_id ) : null;
		}

		if ( ! $termine ) {
			return '';
		}

		$righe = self::cities_with( $termine->term_id );
		if ( empty( $righe ) ) {
			return '';
		}

		$html = '<ul class="glp-grid glp-grid--citta" style="--glp-cols:' . (int) $atts['colonne'] . '">';

		foreach ( $righe as $riga ) {
			$provincia = GLP_Meta::get( $riga['citta']->ID, 'provincia' );

			$html .= '<li class="glp-grid__item"><a class="glp-grid__link" href="' . esc_url( get_permalink( $riga['post'] ) ) . '">'
				. '<span class="glp-grid__title">' . esc_html( $riga['citta']->post_title ) . '</span>'
				. ( '' !== $provincia ? '<span class="glp-grid__meta">' . esc_html( $provincia ) . '</span>' : '' )
				. '</a></li>';
		}

		return $html . '</ul>';
	}
}
