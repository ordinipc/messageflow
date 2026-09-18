<?php
/**
 * Interfaccia di amministrazione: questionario, punteggio, impostazioni,
 * generatore massivo di città.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_glp_bulk_cities', array( __CLASS__, 'handle_bulk' ) );

		add_filter( 'manage_' . GLP_POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . GLP_POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	/**
	 * Script e stili dell'area di amministrazione.
	 *
	 * @param string $hook Hook corrente.
	 */
	public static function assets( $hook ) {
		unset( $hook );

		$screen  = get_current_screen();
		$is_edit = $screen && GLP_POST_TYPE === $screen->post_type;

		// Gli hook delle sottopagine dipendono dal titolo tradotto del menu:
		// controllo direttamente lo slug della pagina.
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_page = in_array( $page, array( 'glp-settings', 'glp-bulk' ), true );

		if ( ! $is_edit && ! $is_page ) {
			return;
		}

		wp_enqueue_style( 'glp-admin', GLP_URL . 'assets/css/admin.css', array(), GLP_VERSION );
		wp_enqueue_script( 'glp-admin', GLP_URL . 'assets/js/admin.js', array(), GLP_VERSION, true );
		wp_localize_script(
			'glp-admin',
			'GLP',
			array(
				'confirmRemove' => __( 'Vuoi eliminare questa riga?', 'geo-landing-pages' ),
				'faqSuggestions' => GLP_Questionnaire::faq_suggestions(),
			)
		);
	}

	/**
	 * Registra i riquadri del questionario.
	 */
	public static function meta_boxes() {
		add_meta_box(
			'glp-score',
			__( 'Qualità della pagina', 'geo-landing-pages' ),
			array( __CLASS__, 'render_score' ),
			GLP_POST_TYPE,
			'side',
			'high'
		);

		foreach ( GLP_Questionnaire::groups() as $key => $group ) {
			add_meta_box(
				'glp-group-' . $key,
				$group['title'],
				array( __CLASS__, 'render_group' ),
				GLP_POST_TYPE,
				'normal',
				'high',
				array( 'group' => $key )
			);
		}
	}

	/**
	 * Riquadro del punteggio di completezza.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_score( $post ) {
		$data   = GLP_Meta::score( $post->ID );
		$minimo = (int) GLP_Settings::get( 'min_score', 60 );
		$ok     = $data['score'] >= $minimo;
		$robots = GLP_Meta::get( $post->ID, 'robots' );

		echo '<div class="glp-score ' . ( $ok ? 'is-ok' : 'is-low' ) . '">';
		echo '<div class="glp-score__value">' . esc_html( $data['score'] ) . '<span>/100</span></div>';
		echo '<div class="glp-score__bar"><span style="width:' . esc_attr( $data['score'] ) . '%"></span></div>';

		if ( 'noindex' === $robots ) {
			echo '<p class="glp-score__msg">' . esc_html__( 'Pagina impostata manualmente su noindex.', 'geo-landing-pages' ) . '</p>';
		} elseif ( 'index' === $robots ) {
			echo '<p class="glp-score__msg">' . esc_html__( 'Pagina forzata su index: assicurati che il contenuto sia completo.', 'geo-landing-pages' ) . '</p>';
		} elseif ( $ok ) {
			echo '<p class="glp-score__msg">' . esc_html__( 'La pagina supera la soglia e verrà indicizzata.', 'geo-landing-pages' ) . '</p>';
		} else {
			echo '<p class="glp-score__msg">' . sprintf(
				/* translators: %d: soglia minima. */
				esc_html__( 'Sotto la soglia di %d/100: la pagina resta noindex finché non completi le risposte mancanti.', 'geo-landing-pages' ),
				(int) $minimo
			) . '</p>';
		}

		if ( ! empty( $data['missing'] ) ) {
			echo '<p class="glp-score__missing-title">' . esc_html__( 'Risposte mancanti:', 'geo-landing-pages' ) . '</p><ul class="glp-score__missing">';
			foreach ( array_slice( $data['missing'], 0, 12 ) as $label ) {
				echo '<li>' . esc_html( $label ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';

		if ( GLP_Post_Types::is_city( $post ) ) {
			echo '<p class="glp-hint">' . esc_html__( 'Questa è una pagina città: i dati di contatto e di zona verranno ereditati da tutte le pagine servizio figlie.', 'geo-landing-pages' ) . '</p>';
		} else {
			$city = GLP_Post_Types::city_post( $post );
			if ( $city ) {
				echo '<p class="glp-hint">' . sprintf(
					/* translators: %s: nome della città. */
					esc_html__( 'Pagina servizio di %s: i campi lasciati vuoti ereditano il valore della pagina città.', 'geo-landing-pages' ),
					esc_html( $city->post_title )
				) . '</p>';
			} else {
				echo '<p class="glp-hint glp-hint--warn">' . esc_html__( 'Nessuna città selezionata. Scegli la città in "Attributi pagina" > Genitore per ottenere un URL del tipo /citta/servizio/.', 'geo-landing-pages' ) . '</p>';
			}
		}
	}

	/**
	 * Riquadro di un gruppo di domande.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $box  Argomenti del riquadro.
	 */
	public static function render_group( $post, $box ) {
		$groups = GLP_Questionnaire::groups();
		$key    = $box['args']['group'];
		if ( ! isset( $groups[ $key ] ) ) {
			return;
		}
		$group = $groups[ $key ];

		// Il nonce viene stampato una sola volta, con il primo riquadro.
		static $nonce_done = false;
		if ( ! $nonce_done ) {
			wp_nonce_field( 'glp_save_' . $post->ID, 'glp_nonce' );
			$nonce_done = true;
		}

		if ( ! empty( $group['intro'] ) ) {
			echo '<p class="glp-group__intro">' . esc_html( $group['intro'] ) . '</p>';
		}

		if ( 'faq' === $key ) {
			echo '<p><button type="button" class="button glp-faq-suggest">' . esc_html__( 'Precarica le domande tipiche', 'geo-landing-pages' ) . '</button> ';
			echo '<span class="glp-hint">' . esc_html__( 'Aggiunge le domande più frequenti nelle ricerche locali: le risposte scrivile tu.', 'geo-landing-pages' ) . '</span></p>';
		}

		echo '<div class="glp-fields">';
		foreach ( $group['fields'] as $field_key => $field ) {
			self::render_field( $post, $field_key, $field );
		}
		echo '</div>';
	}

	/**
	 * Un singolo campo.
	 *
	 * @param WP_Post $post  Post.
	 * @param string  $key   Chiave.
	 * @param array   $field Definizione.
	 */
	private static function render_field( $post, $key, $field ) {
		$type  = isset( $field['type'] ) ? $field['type'] : 'text';
		$value = GLP_Meta::raw( $post->ID, $key );
		$name  = 'glp[' . $key . ']';
		$id    = 'glp-' . $key;

		// Valore ereditato dalla pagina città, mostrato come segnaposto.
		$inherited = '';
		if ( ! empty( $field['inherit'] ) && (int) $post->post_parent ) {
			$parent_value = GLP_Meta::raw( (int) $post->post_parent, $key );
			if ( ! GLP_Meta::is_empty( $parent_value ) && ! is_array( $parent_value ) ) {
				$inherited = (string) $parent_value;
			}
		}

		if ( GLP_Meta::is_empty( $value ) && isset( $field['default'] ) && ! is_array( $value ) ) {
			$value = $field['default'];
		}

		$classes = array( 'glp-field', 'glp-field--' . $type );
		if ( ! empty( $field['required'] ) ) {
			$classes[] = 'glp-field--required';
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		echo '<label class="glp-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] );
		if ( ! empty( $field['required'] ) ) {
			echo ' <span class="glp-req">*</span>';
		}
		echo '</label>';

		switch ( $type ) {
			case 'textarea':
				printf(
					'<textarea class="widefat" rows="3" id="%1$s" name="%2$s" placeholder="%4$s">%3$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_textarea( (string) $value ),
					esc_attr( $inherited )
				);
				break;

			case 'list':
				$lines = is_array( $value ) ? implode( "\n", $value ) : (string) $value;
				printf(
					'<textarea class="widefat" rows="4" id="%1$s" name="%2$s" placeholder="%3$s">%4$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr__( 'Una voce per riga', 'geo-landing-pages' ),
					esc_textarea( $lines )
				);
				break;

			case 'select':
				echo '<select class="widefat" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $field['options'] as $opt_value => $opt_label ) {
					printf(
						'<option value="%1$s" %3$s>%2$s</option>',
						esc_attr( $opt_value ),
						esc_html( $opt_label ),
						selected( (string) $value, (string) $opt_value, false )
					);
				}
				echo '</select>';
				break;

			case 'checkbox':
				printf(
					'<label class="glp-checkbox"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (string) $value, '1', false ),
					esc_html__( 'Sì', 'geo-landing-pages' )
				);
				break;

			case 'hours':
				self::render_hours( $name, $value );
				break;

			case 'repeater':
				self::render_repeater( $key, $name, $field, $value );
				break;

			case 'number':
			case 'url':
			case 'email':
			case 'tel':
			case 'date':
			case 'text':
			default:
				$input_type = in_array( $type, array( 'url', 'email', 'date' ), true ) ? $type : ( 'number' === $type ? 'text' : 'text' );
				printf(
					'<input class="widefat" type="%1$s" id="%2$s" name="%3$s" value="%4$s" placeholder="%5$s" />',
					esc_attr( $input_type ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( $inherited )
				);
				break;
		}

		if ( '' !== $inherited && ! in_array( $type, array( 'repeater', 'hours', 'checkbox' ), true ) ) {
			echo '<p class="glp-field__inherit">' . sprintf(
				/* translators: %s: valore ereditato. */
				esc_html__( 'Se lasci vuoto viene usato il valore della pagina città: %s', 'geo-landing-pages' ),
				'<code>' . esc_html( wp_html_excerpt( $inherited, 60, '…' ) ) . '</code>'
			) . '</p>';
		}

		if ( ! empty( $field['hint'] ) ) {
			echo '<p class="glp-field__hint">' . esc_html( $field['hint'] ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Campo orari.
	 *
	 * @param string $name  Nome base.
	 * @param mixed  $value Valore.
	 */
	private static function render_hours( $name, $value ) {
		$value = is_array( $value ) ? $value : array();
		echo '<table class="glp-hours"><tbody>';
		foreach ( GLP_Meta::days() as $day => $info ) {
			$row    = isset( $value[ $day ] ) && is_array( $value[ $day ] ) ? $value[ $day ] : array();
			$open   = isset( $row['open'] ) ? $row['open'] : '';
			$close  = isset( $row['close'] ) ? $row['close'] : '';
			$closed = ! empty( $row['closed'] );

			echo '<tr>';
			echo '<th scope="row">' . esc_html( $info[0] ) . '</th>';
			printf(
				'<td><input type="time" name="%1$s[%2$s][open]" value="%3$s" /></td>',
				esc_attr( $name ),
				esc_attr( $day ),
				esc_attr( $open )
			);
			printf(
				'<td><input type="time" name="%1$s[%2$s][close]" value="%3$s" /></td>',
				esc_attr( $name ),
				esc_attr( $day ),
				esc_attr( $close )
			);
			printf(
				'<td><label><input type="checkbox" name="%1$s[%2$s][closed]" value="1" %3$s /> %4$s</label></td>',
				esc_attr( $name ),
				esc_attr( $day ),
				checked( $closed, true, false ),
				esc_html__( 'Chiuso', 'geo-landing-pages' )
			);
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Campo ripetibile.
	 *
	 * @param string $key   Chiave campo.
	 * @param string $name  Nome base.
	 * @param array  $field Definizione.
	 * @param mixed  $value Valore.
	 */
	private static function render_repeater( $key, $name, $field, $value ) {
		$rows = is_array( $value ) ? $value : array();
		if ( empty( $rows ) ) {
			$rows = array( array() );
		}

		echo '<div class="glp-repeater" data-field="' . esc_attr( $key ) . '" data-name="' . esc_attr( $name ) . '">';
		echo '<div class="glp-repeater__rows">';
		foreach ( array_values( $rows ) as $index => $row ) {
			self::render_repeater_row( $name, $field, $row, $index );
		}
		echo '</div>';
		echo '<p><button type="button" class="button glp-repeater__add">' . esc_html__( '+ Aggiungi', 'geo-landing-pages' ) . '</button></p>';

		// Modello per le nuove righe.
		echo '<script type="text/html" class="glp-repeater__tpl">';
		self::render_repeater_row( $name, $field, array(), '__i__' );
		echo '</script>';
		echo '</div>';
	}

	/**
	 * Riga di un campo ripetibile.
	 *
	 * @param string     $name  Nome base.
	 * @param array      $field Definizione.
	 * @param array      $row   Valori della riga.
	 * @param int|string $index Indice.
	 */
	private static function render_repeater_row( $name, $field, $row, $index ) {
		echo '<div class="glp-repeater__row">';
		echo '<span class="glp-repeater__handle" aria-hidden="true">⋮⋮</span>';
		echo '<div class="glp-repeater__cells">';
		foreach ( $field['subfields'] as $sub_key => $sub ) {
			$sub_name  = $name . '[' . $index . '][' . $sub_key . ']';
			$sub_value = isset( $row[ $sub_key ] ) ? $row[ $sub_key ] : '';
			$sub_type  = isset( $sub['type'] ) ? $sub['type'] : 'text';

			echo '<label class="glp-repeater__cell glp-repeater__cell--' . esc_attr( $sub_type ) . '">';
			echo '<span>' . esc_html( $sub['label'] ) . '</span>';
			if ( 'textarea' === $sub_type ) {
				printf(
					'<textarea class="widefat" rows="2" name="%1$s">%2$s</textarea>',
					esc_attr( $sub_name ),
					esc_textarea( (string) $sub_value )
				);
			} else {
				$input_type = in_array( $sub_type, array( 'url', 'date' ), true ) ? $sub_type : 'text';
				printf(
					'<input class="widefat" type="%1$s" name="%2$s" value="%3$s" />',
					esc_attr( $input_type ),
					esc_attr( $sub_name ),
					esc_attr( (string) $sub_value )
				);
			}
			echo '</label>';
		}
		echo '</div>';
		echo '<button type="button" class="button-link glp-repeater__remove" aria-label="' . esc_attr__( 'Elimina riga', 'geo-landing-pages' ) . '">×</button>';
		echo '</div>';
	}

	/**
	 * Colonne aggiuntive nell'elenco.
	 *
	 * @param array $columns Colonne.
	 * @return array
	 */
	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['glp_score']  = __( 'Qualità', 'geo-landing-pages' );
				$new['glp_index']  = __( 'Indicizzata', 'geo-landing-pages' );
				$new['glp_url']    = __( 'URL', 'geo-landing-pages' );
			}
		}
		return $new;
	}

	/**
	 * Contenuto delle colonne aggiuntive.
	 *
	 * @param string $column  Colonna.
	 * @param int    $post_id ID post.
	 */
	public static function column( $column, $post_id ) {
		switch ( $column ) {
			case 'glp_score':
				$data = GLP_Meta::score( $post_id );
				$ok   = $data['score'] >= (int) GLP_Settings::get( 'min_score', 60 );
				echo '<span class="glp-badge ' . ( $ok ? 'is-ok' : 'is-low' ) . '">' . esc_html( $data['score'] ) . '</span>';
				break;

			case 'glp_index':
				echo GLP_Meta::is_indexable( $post_id )
					? '<span class="glp-badge is-ok">index</span>'
					: '<span class="glp-badge is-low">noindex</span>';
				break;

			case 'glp_url':
				$url = get_permalink( $post_id );
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><code>' . esc_html( wp_make_link_relative( $url ) ) . '</code></a>';
				break;
		}
	}

	/**
	 * Voci di menu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . GLP_POST_TYPE,
			__( 'Impostazioni landing locali', 'geo-landing-pages' ),
			__( 'Impostazioni', 'geo-landing-pages' ),
			'manage_options',
			'glp-settings',
			array( __CLASS__, 'render_settings' )
		);
		add_submenu_page(
			'edit.php?post_type=' . GLP_POST_TYPE,
			__( 'Crea città in blocco', 'geo-landing-pages' ),
			__( 'Crea città in blocco', 'geo-landing-pages' ),
			'edit_others_posts',
			'glp-bulk',
			array( __CLASS__, 'render_bulk' )
		);
	}

	/**
	 * Registra le impostazioni.
	 */
	public static function register_settings() {
		register_setting(
			'glp_settings_group',
			GLP_Settings::OPTION,
			array(
				'sanitize_callback' => array( 'GLP_Settings', 'sanitize' ),
				'default'           => GLP_Settings::defaults(),
			)
		);
	}

	/**
	 * Pagina delle impostazioni.
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s    = GLP_Settings::all();
		$name = GLP_Settings::OPTION;
		$esempio = home_url( '/' . ( '' !== $s['url_prefix'] ? $s['url_prefix'] . '/' : '' ) . 'trapani/duplicazione-chiavi/' );
		?>
		<div class="wrap glp-settings">
			<h1><?php esc_html_e( 'Landing locali — Impostazioni', 'geo-landing-pages' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'glp_settings_group' ); ?>

				<h2><?php esc_html_e( 'Struttura degli URL', 'geo-landing-pages' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="glp-url-prefix"><?php esc_html_e( 'Prefisso (facoltativo)', 'geo-landing-pages' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="glp-url-prefix" name="<?php echo esc_attr( $name ); ?>[url_prefix]" value="<?php echo esc_attr( $s['url_prefix'] ); ?>" placeholder="es. citta" />
							<p class="description">
								<?php esc_html_e( 'Lascia vuoto per avere la città alla radice del sito. Struttura risultante:', 'geo-landing-pages' ); ?>
								<code><?php echo esc_html( $esempio ); ?></code>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rendering', 'geo-landing-pages' ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[template_mode]" value="filter" <?php checked( $s['template_mode'], 'filter' ); ?> /> <?php esc_html_e( 'Usa il template del tema e aggiungi le sezioni al contenuto (consigliato)', 'geo-landing-pages' ); ?></label><br />
							<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[template_mode]" value="template" <?php checked( $s['template_mode'], 'template' ); ?> /> <?php esc_html_e( 'Usa il template del plugin', 'geo-landing-pages' ); ?></label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Dati dell\'attività', 'geo-landing-pages' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$campi = array(
						'brand'            => __( 'Nome dell\'attività', 'geo-landing-pages' ),
						'servizio_default' => __( 'Servizio principale (usato nelle pagine città)', 'geo-landing-pages' ),
						'telefono'         => __( 'Telefono', 'geo-landing-pages' ),
						'whatsapp'         => __( 'WhatsApp (solo cifre con prefisso)', 'geo-landing-pages' ),
						'email'            => __( 'Email', 'geo-landing-pages' ),
						'partita_iva'      => __( 'Partita IVA', 'geo-landing-pages' ),
					);
					foreach ( $campi as $key => $label ) :
						?>
						<tr>
							<th scope="row"><label for="glp-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="text" class="regular-text" id="glp-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $s[ $key ] ); ?>" /></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Modelli di testo', 'geo-landing-pages' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Segnaposto disponibili:', 'geo-landing-pages' ); ?>
					<code>{citta}</code> <code>{provincia}</code> <code>{regione}</code> <code>{servizio}</code>
					<code>{servizio_default}</code> <code>{brand}</code> <code>{telefono}</code> <code>{prezzo_da}</code>
					<code>{anni}</code> <code>{anni_frase}</code> <code>{interventi_frase}</code>
					<code>{tempo_intervento}</code> <code>{perche_primo}</code> <code>{sep}</code> <code>{anno}</code>
				</p>
				<table class="form-table" role="presentation">
					<?php
					$modelli = array(
						'city_title_template' => __( 'Title — pagina città', 'geo-landing-pages' ),
						'title_template'      => __( 'Title — pagina servizio', 'geo-landing-pages' ),
						'desc_template'       => __( 'Meta description', 'geo-landing-pages' ),
						'h1_template'         => __( 'H1', 'geo-landing-pages' ),
						'intro_template'      => __( 'Paragrafo introduttivo', 'geo-landing-pages' ),
					);
					foreach ( $modelli as $key => $label ) :
						?>
						<tr>
							<th scope="row"><label for="glp-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><textarea class="large-text" rows="2" id="glp-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $s[ $key ] ); ?></textarea></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'SEO e qualità', 'geo-landing-pages' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="glp-min-score"><?php esc_html_e( 'Punteggio minimo per l\'indicizzazione', 'geo-landing-pages' ); ?></label></th>
						<td>
							<input type="number" min="0" max="100" id="glp-min-score" name="<?php echo esc_attr( $name ); ?>[min_score]" value="<?php echo esc_attr( $s['min_score'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Le pagine con punteggio inferiore restano noindex ed escono dalla sitemap. È la protezione contro le pagine copia-incolla che Google classifica come doorway page.', 'geo-landing-pages' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="glp-schema-type"><?php esc_html_e( 'Tipo di attività (schema.org)', 'geo-landing-pages' ); ?></label></th>
						<td>
							<select id="glp-schema-type" name="<?php echo esc_attr( $name ); ?>[schema_type]">
								<?php foreach ( GLP_Settings::schema_types() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['schema_type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Opzioni', 'geo-landing-pages' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[schema_enabled]" value="1" <?php checked( $s['schema_enabled'], 1 ); ?> /> <?php esc_html_e( 'Genera i dati strutturati JSON-LD', 'geo-landing-pages' ); ?></label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[breadcrumbs]" value="1" <?php checked( $s['breadcrumbs'], 1 ); ?> /> <?php esc_html_e( 'Mostra le briciole di pane', 'geo-landing-pages' ); ?></label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php if ( GLP_SEO::has_seo_plugin() ) : ?>
				<div class="notice notice-info inline"><p>
					<?php esc_html_e( 'È attivo un plugin SEO: title, description e canonical vengono passati a quel plugin invece di essere stampati due volte.', 'geo-landing-pages' ); ?>
				</p></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Pagina del generatore massivo.
	 */
	public static function render_bulk() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		$cities = GLP_Post_Types::cities( 500 );
		?>
		<div class="wrap glp-bulk">
			<h1><?php esc_html_e( 'Crea città in blocco', 'geo-landing-pages' ); ?></h1>
			<p><?php esc_html_e( 'Crea le pagine come bozze da completare. Pubblicale solo dopo aver risposto al questionario: una pagina vuota pubblicata è un danno, non un vantaggio.', 'geo-landing-pages' ); ?></p>

			<?php if ( isset( $_GET['glp_done'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success"><p>
					<?php
					printf(
						/* translators: %d: numero di pagine create. */
						esc_html__( 'Pagine create: %d (in bozza).', 'geo-landing-pages' ),
						(int) $_GET['glp_done'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					);
					?>
				</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="glp_bulk_cities" />
				<?php wp_nonce_field( 'glp_bulk_cities' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="glp-bulk-mode"><?php esc_html_e( 'Cosa vuoi creare', 'geo-landing-pages' ); ?></label></th>
						<td>
							<label><input type="radio" name="mode" value="cities" checked /> <?php esc_html_e( 'Pagine città', 'geo-landing-pages' ); ?></label><br />
							<label><input type="radio" name="mode" value="services" /> <?php esc_html_e( 'Pagine servizio dentro una città esistente', 'geo-landing-pages' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="glp-bulk-parent"><?php esc_html_e( 'Città di destinazione', 'geo-landing-pages' ); ?></label></th>
						<td>
							<select id="glp-bulk-parent" name="parent">
								<option value="0"><?php esc_html_e( '— nessuna (creo pagine città) —', 'geo-landing-pages' ); ?></option>
								<?php foreach ( $cities as $city ) : ?>
									<option value="<?php echo esc_attr( $city->ID ); ?>"><?php echo esc_html( $city->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Obbligatoria se crei pagine servizio.', 'geo-landing-pages' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="glp-bulk-list"><?php esc_html_e( 'Elenco', 'geo-landing-pages' ); ?></label></th>
						<td>
							<textarea id="glp-bulk-list" name="list" rows="12" class="large-text code" placeholder="Trapani|TP|Sicilia&#10;Marsala|TP|Sicilia&#10;Palermo|PA|Sicilia"></textarea>
							<p class="description">
								<?php esc_html_e( 'Una voce per riga. Per le città: Nome|Provincia|Regione. Per i servizi: basta il nome del servizio.', 'geo-landing-pages' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Crea le bozze', 'geo-landing-pages' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Crea le pagine richieste dal generatore massivo.
	 */
	public static function handle_bulk() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'geo-landing-pages' ) );
		}
		check_admin_referer( 'glp_bulk_cities' );

		$mode   = isset( $_POST['mode'] ) && 'services' === $_POST['mode'] ? 'services' : 'cities';
		$parent = isset( $_POST['parent'] ) ? (int) $_POST['parent'] : 0;
		$list   = isset( $_POST['list'] ) ? sanitize_textarea_field( wp_unslash( $_POST['list'] ) ) : '';

		if ( 'services' === $mode && ! $parent ) {
			wp_safe_redirect( add_query_arg( 'glp_done', 0, admin_url( 'edit.php?post_type=' . GLP_POST_TYPE . '&page=glp-bulk' ) ) );
			exit;
		}
		if ( 'cities' === $mode ) {
			$parent = 0;
		}

		$created = 0;
		foreach ( preg_split( '/\r\n|\r|\n/', $list ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line ) );
			$title = $parts[0];
			if ( '' === $title ) {
				continue;
			}

			$slug = sanitize_title( $title );

			// Evita i duplicati sullo stesso livello.
			$existing = get_page_by_path(
				$parent ? get_post_field( 'post_name', $parent ) . '/' . $slug : $slug,
				OBJECT,
				GLP_POST_TYPE
			);
			if ( $existing ) {
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'   => GLP_POST_TYPE,
					'post_title'  => $title,
					'post_name'   => $slug,
					'post_status' => 'draft',
					'post_parent' => $parent,
				)
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			if ( 'cities' === $mode ) {
				update_post_meta( $post_id, GLP_META_PREFIX . 'citta', $title );
				if ( isset( $parts[1] ) && '' !== $parts[1] ) {
					update_post_meta( $post_id, GLP_META_PREFIX . 'provincia', $parts[1] );
				}
				if ( isset( $parts[2] ) && '' !== $parts[2] ) {
					update_post_meta( $post_id, GLP_META_PREFIX . 'regione', $parts[2] );
				}
			} else {
				update_post_meta( $post_id, GLP_META_PREFIX . 'servizio_nome', $title );
			}

			$created++;
		}

		GLP_Post_Types::schedule_flush();

		wp_safe_redirect( add_query_arg( 'glp_done', $created, admin_url( 'edit.php?post_type=' . GLP_POST_TYPE . '&page=glp-bulk' ) ) );
		exit;
	}
}
