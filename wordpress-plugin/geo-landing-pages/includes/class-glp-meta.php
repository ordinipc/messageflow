<?php
/**
 * Lettura, sanitizzazione e salvataggio delle risposte del questionario.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Meta {

	public static function init() {
		add_action( 'save_post_' . GLP_POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Valore grezzo di un campo (senza ereditarietà).
	 *
	 * @param int    $post_id ID post.
	 * @param string $key     Chiave campo.
	 * @return mixed
	 */
	public static function raw( $post_id, $key ) {
		$value = get_post_meta( (int) $post_id, GLP_META_PREFIX . $key, true );
		return $value;
	}

	/**
	 * Valore effettivo di un campo.
	 *
	 * Se il campo è vuoto ed è ereditabile, la pagina servizio prende il
	 * valore dalla pagina città; in ultima istanza si usa il valore
	 * predefinito dichiarato nel questionario.
	 *
	 * @param int    $post_id ID post.
	 * @param string $key     Chiave campo.
	 * @return mixed
	 */
	public static function get( $post_id, $key ) {
		$post_id = (int) $post_id;
		$field   = GLP_Questionnaire::field( $key );
		$value   = self::raw( $post_id, $key );

		if ( ! self::is_empty( $value ) ) {
			return $value;
		}

		if ( $field && ! empty( $field['inherit'] ) ) {
			$post = get_post( $post_id );
			if ( $post && (int) $post->post_parent ) {
				$inherited = self::raw( (int) $post->post_parent, $key );
				if ( ! self::is_empty( $inherited ) ) {
					return $inherited;
				}
			}
		}

		if ( $field && isset( $field['default'] ) ) {
			return $field['default'];
		}

		return self::is_empty( $value ) ? '' : $value;
	}

	/**
	 * Il valore è considerato vuoto?
	 *
	 * @param mixed $value Valore.
	 * @return bool
	 */
	public static function is_empty( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $row ) {
				if ( is_array( $row ) ) {
					foreach ( $row as $cell ) {
						if ( '' !== trim( (string) $cell ) ) {
							return false;
						}
					}
				} elseif ( '' !== trim( (string) $row ) ) {
					return false;
				}
			}
			return true;
		}
		return '' === trim( (string) $value );
	}

	/**
	 * Tutte le risposte effettive di un post.
	 *
	 * @param int $post_id ID post.
	 * @return array
	 */
	public static function all( $post_id ) {
		$out = array();
		foreach ( array_keys( GLP_Questionnaire::fields() ) as $key ) {
			$out[ $key ] = self::get( $post_id, $key );
		}
		return $out;
	}

	/**
	 * Punteggio di completezza (0-100) e campi mancanti.
	 *
	 * @param int $post_id ID post.
	 * @return array {
	 *     @type int   $score   Percentuale.
	 *     @type array $missing Elenco chiave => label dei campi mancanti.
	 * }
	 */
	public static function score( $post_id ) {
		$total   = 0;
		$filled  = 0;
		$missing = array();

		$is_city = GLP_Post_Types::is_city( $post_id );

		foreach ( GLP_Questionnaire::fields() as $key => $field ) {
			$weight = isset( $field['weight'] ) ? (int) $field['weight'] : 0;
			if ( $weight <= 0 ) {
				continue;
			}
			// Prezzi, processo e contenuti di offerta riguardano le pagine
			// servizio: non vanno conteggiati sull'hub della città.
			if ( $is_city && isset( $field['scope'] ) && 'service' === $field['scope'] ) {
				continue;
			}
			$total += $weight;
			if ( ! self::is_empty( self::get( $post_id, $key ) ) ) {
				$filled += $weight;
			} else {
				$missing[ $key ] = $field['label'];
			}
		}

		$score = $total > 0 ? (int) round( $filled / $total * 100 ) : 0;

		return array(
			'score'   => $score,
			'missing' => $missing,
		);
	}

	/**
	 * La pagina può essere indicizzata?
	 *
	 * @param int $post_id ID post.
	 * @return bool
	 */
	public static function is_indexable( $post_id ) {
		$robots = self::get( $post_id, 'robots' );
		if ( 'noindex' === $robots ) {
			return false;
		}
		if ( 'index' === $robots ) {
			return true;
		}
		$score  = self::score( $post_id );
		$minimo = (int) GLP_Settings::get( 'min_score', 60 );
		return $score['score'] >= $minimo;
	}

	/**
	 * Salva le risposte inviate dalla schermata di modifica.
	 *
	 * @param int     $post_id ID post.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['glp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['glp_nonce'] ) ), 'glp_save_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$submitted = isset( $_POST['glp'] ) && is_array( $_POST['glp'] ) ? wp_unslash( $_POST['glp'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizzato per campo sotto.

		foreach ( GLP_Questionnaire::fields() as $key => $field ) {
			// Un campo protetto non viene toccato da chi non ha il permesso:
			// altrimenti un redattore che salva la pagina cancellerebbe il
			// codice inserito da un amministratore.
			if ( ! empty( $field['cap'] ) && ! current_user_can( $field['cap'] ) ) {
				continue;
			}

			$type  = isset( $field['type'] ) ? $field['type'] : 'text';
			$value = isset( $submitted[ $key ] ) ? $submitted[ $key ] : ( 'checkbox' === $type ? '' : null );

			if ( null === $value ) {
				continue;
			}

			$clean = self::sanitize_value( $value, $field );

			if ( self::is_empty( $clean ) ) {
				delete_post_meta( $post_id, GLP_META_PREFIX . $key );
			} else {
				update_post_meta( $post_id, GLP_META_PREFIX . $key, $clean );
			}
		}

		// Memorizza il punteggio per poterlo mostrare in elenco e filtrare.
		$score = self::score( $post_id );
		update_post_meta( $post_id, GLP_META_PREFIX . 'score', $score['score'] );
	}

	/**
	 * Sanitizza un valore secondo il tipo di campo.
	 *
	 * @param mixed $value Valore grezzo.
	 * @param array $field Definizione del campo.
	 * @return mixed
	 */
	public static function sanitize_value( $value, $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : 'text';

		switch ( $type ) {
			case 'repeater':
				$rows = array();
				if ( ! is_array( $value ) ) {
					return $rows;
				}
				foreach ( $value as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$clean_row = array();
					foreach ( $field['subfields'] as $sub_key => $sub ) {
						$raw               = isset( $row[ $sub_key ] ) ? $row[ $sub_key ] : '';
						$clean_row[ $sub_key ] = self::sanitize_value( $raw, $sub );
					}
					if ( ! self::is_empty( $clean_row ) ) {
						$rows[] = $clean_row;
					}
				}
				return $rows;

			case 'hours':
				$days  = array_keys( self::days() );
				$clean = array();
				if ( ! is_array( $value ) ) {
					return $clean;
				}
				foreach ( $days as $day ) {
					$row = isset( $value[ $day ] ) && is_array( $value[ $day ] ) ? $value[ $day ] : array();
					$clean[ $day ] = array(
						'closed' => empty( $row['closed'] ) ? '' : '1',
						'open'   => isset( $row['open'] ) ? sanitize_text_field( $row['open'] ) : '',
						'close'  => isset( $row['close'] ) ? sanitize_text_field( $row['close'] ) : '',
					);
				}
				return $clean;

			case 'list':
				$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
				$lines = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', (array) $lines ) ), 'strlen' ) );
				return $lines;

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			case 'url':
				return esc_url_raw( trim( (string) $value ) );

			case 'email':
				return sanitize_email( (string) $value );

			case 'number':
				$value = trim( (string) $value );
				return '' === $value ? '' : (string) preg_replace( '/[^0-9.,\-]/', '', $value );

			case 'checkbox':
				return empty( $value ) ? '' : '1';

			case 'select':
				$options = isset( $field['options'] ) ? array_keys( $field['options'] ) : array();
				$value   = sanitize_text_field( (string) $value );
				return in_array( $value, $options, true ) ? $value : '';

			case 'code':
				// Volutamente non filtrato: è codice, e può salvarlo solo chi
				// ha già il permesso di pubblicare HTML non filtrato.
				return (string) $value;

			case 'date':
			case 'tel':
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Giorni della settimana: chiave => [etichetta, codice schema.org].
	 *
	 * @return array
	 */
	public static function days() {
		return array(
			'mon' => array( __( 'Lunedì', 'geo-landing-pages' ), 'Monday' ),
			'tue' => array( __( 'Martedì', 'geo-landing-pages' ), 'Tuesday' ),
			'wed' => array( __( 'Mercoledì', 'geo-landing-pages' ), 'Wednesday' ),
			'thu' => array( __( 'Giovedì', 'geo-landing-pages' ), 'Thursday' ),
			'fri' => array( __( 'Venerdì', 'geo-landing-pages' ), 'Friday' ),
			'sat' => array( __( 'Sabato', 'geo-landing-pages' ), 'Saturday' ),
			'sun' => array( __( 'Domenica', 'geo-landing-pages' ), 'Sunday' ),
		);
	}
}
