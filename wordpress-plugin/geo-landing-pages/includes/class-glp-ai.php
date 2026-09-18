<?php
/**
 * Assistente di redazione basato su Google Gemini.
 *
 * Il modello riceve solo le risposte del questionario e ha il divieto
 * esplicito di inventare dati verificabili (prezzi, contatti, recensioni,
 * certificazioni). Il testo generato viene sempre proposto all'utente,
 * mai pubblicato in automatico.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_AI {

	const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta';
	const META_FLAG = '_glp_ai_fields';

	public static function init() {
		add_action( 'wp_ajax_glp_ai_generate', array( __CLASS__, 'ajax_generate' ) );
		add_action( 'wp_ajax_glp_ai_models', array( __CLASS__, 'ajax_models' ) );
		add_action( 'wp_ajax_glp_ai_accept', array( __CLASS__, 'ajax_accept' ) );
	}

	/**
	 * Chiave API: la costante in wp-config.php ha la precedenza sul database.
	 *
	 * @return string
	 */
	public static function api_key() {
		if ( defined( 'GLP_GEMINI_API_KEY' ) && GLP_GEMINI_API_KEY ) {
			return (string) GLP_GEMINI_API_KEY;
		}
		return (string) GLP_Settings::get( 'gemini_key', '' );
	}

	/**
	 * La chiave arriva da wp-config.php?
	 *
	 * @return bool
	 */
	public static function key_is_constant() {
		return defined( 'GLP_GEMINI_API_KEY' ) && GLP_GEMINI_API_KEY;
	}

	/**
	 * L'assistente è utilizzabile?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) GLP_Settings::get( 'ai_enabled', 0 ) && '' !== self::api_key();
	}

	/**
	 * Attività di generazione disponibili.
	 *
	 * Ogni attività dichiara il campo di destinazione, il formato atteso e
	 * l'istruzione specifica per il modello.
	 *
	 * @return array
	 */
	public static function tasks() {
		$tasks = array(
			'seo_title' => array(
				'label'  => __( 'Genera il title', 'geo-landing-pages' ),
				'target' => 'seo_title',
				'format' => 'text',
				'limit'  => 60,
				'prompt' => __( 'Scrivi il tag title per questa pagina: massimo 60 caratteri, con il servizio e la città nei primi 30 caratteri. Nessuna virgoletta, nessun punto finale.', 'geo-landing-pages' ),
			),
			'seo_description' => array(
				'label'  => __( 'Genera la meta description', 'geo-landing-pages' ),
				'target' => 'seo_description',
				'format' => 'text',
				'limit'  => 155,
				'prompt' => __( 'Scrivi la meta description: massimo 155 caratteri, una frase che dice cosa offri e dove, più un motivo concreto per sceglierti preso dai dati forniti, e una chiamata all\'azione. Niente superlativi generici come "i migliori".', 'geo-landing-pages' ),
			),
			'perche_noi' => array(
				'label'  => __( 'Riscrivi i motivi', 'geo-landing-pages' ),
				'target' => 'perche_noi',
				'format' => 'list',
				'prompt' => __( 'Riscrivi in modo chiaro e concreto i motivi per scegliere questa attività in questa città. Parti dai motivi già indicati e riformulali: non aggiungere motivi nuovi che non siano deducibili dai dati forniti. Da 3 a 6 voci, una frase ciascuna, senza superlativi.', 'geo-landing-pages' ),
			),
			'servizi_inclusi' => array(
				'label'  => __( 'Riscrivi cosa comprende', 'geo-landing-pages' ),
				'target' => 'servizi_inclusi',
				'format' => 'list',
				'prompt' => __( 'Riscrivi l\'elenco di cosa comprende il servizio, in voci brevi e comprensibili a un cliente. Usa solo le voci già indicate: riformulale, non inventarne di nuove.', 'geo-landing-pages' ),
			),
			'processo' => array(
				'label'  => __( 'Scrivi i passaggi', 'geo-landing-pages' ),
				'target' => 'processo',
				'format' => 'processo',
				'prompt' => __( 'Descrivi come si svolge il servizio passo per passo, dal primo contatto alla conclusione. Da 3 a 5 passaggi. Se i passaggi sono già indicati riformulali senza cambiarne la sostanza; indica la durata solo se ricavabile dai dati forniti, altrimenti lasciala vuota.', 'geo-landing-pages' ),
			),
			'faq' => array(
				'label'  => __( 'Scrivi le risposte alle FAQ', 'geo-landing-pages' ),
				'target' => 'faq',
				'format' => 'faq',
				'prompt' => __( 'Rispondi alle domande frequenti già inserite, mantenendo le stesse domande nello stesso ordine. Ogni risposta: 2-4 frasi, diretta, con i dati reali forniti quando disponibili. Se per rispondere servirebbe un dato che non ti è stato fornito, scrivi una risposta utile senza quel dato, senza inventarlo. Se le domande inserite sono meno di cinque, aggiungine altre che un cliente di questa città farebbe davvero.', 'geo-landing-pages' ),
			),
			'approfondimento' => array(
				'label'  => __( 'Scrivi l\'approfondimento', 'geo-landing-pages' ),
				'target' => 'approfondimento',
				'format' => 'text',
				'limit'  => 1800,
				'prompt' => __( 'Scrivi un approfondimento di 2-4 paragrafi su questo servizio in questa città: a chi serve, quali situazioni risolve, cosa distingue questo intervento sul posto, quali zone copre. Usa i riferimenti locali forniti. Niente introduzioni generiche sul settore, niente elenchi: solo prosa utile a chi deve decidere. Non superare i 1500 caratteri.', 'geo-landing-pages' ),
			),
			'come_raggiungerci' => array(
				'label'  => __( 'Riscrivi le indicazioni', 'geo-landing-pages' ),
				'target' => 'come_raggiungerci',
				'format' => 'text',
				'limit'  => 600,
				'prompt' => __( 'Riscrivi in modo scorrevole le indicazioni per raggiungere la sede, usando solo i riferimenti forniti (vie, piazze, mezzi, parcheggi). Non aggiungere riferimenti che non ti sono stati dati.', 'geo-landing-pages' ),
			),
		);

		/**
		 * Attività di generazione disponibili.
		 *
		 * @param array $tasks Attività.
		 */
		return apply_filters( 'glp_ai_tasks', $tasks );
	}

	/**
	 * Istruzioni di sistema comuni a tutte le richieste.
	 *
	 * @param int $post_id ID post.
	 * @return string
	 */
	private static function system_instruction( $post_id ) {
		$stile = GLP_Settings::get( 'ai_style', '' );

		$rules = array(
			__( 'Sei un redattore italiano specializzato in pagine di servizi locali. Scrivi in italiano, in seconda persona plurale riferita all\'azienda ("operiamo", "interveniamo").', 'geo-landing-pages' ),
			__( 'REGOLA INDEROGABILE: usa esclusivamente i dati che ti vengono forniti. Non inventare mai prezzi, numeri di telefono, indirizzi, orari, tempi di intervento, anni di attività, quantità di clienti, certificazioni, garanzie, partner, premi o recensioni. Se un dato non è tra quelli forniti, scrivi il testo senza quel dato.', 'geo-landing-pages' ),
			__( 'Non promettere risultati, non usare superlativi non dimostrabili ("i migliori", "i più veloci", "leader"), non scrivere affermazioni che l\'azienda non potrebbe dimostrare.', 'geo-landing-pages' ),
			__( 'Scrivi in modo concreto e specifico per la città indicata: un testo che funzionerebbe identico per qualsiasi altra città è sbagliato.', 'geo-landing-pages' ),
			__( 'Niente markdown, niente asterischi, niente titoli: solo testo semplice.', 'geo-landing-pages' ),
		);

		if ( '' !== $stile ) {
			$rules[] = __( 'Indicazioni di stile fornite dal cliente:', 'geo-landing-pages' ) . ' ' . $stile;
		}

		/**
		 * Istruzioni di sistema.
		 *
		 * @param array $rules   Regole.
		 * @param int   $post_id ID post.
		 */
		$rules = apply_filters( 'glp_ai_system_rules', $rules, $post_id );

		return implode( "\n", $rules );
	}

	/**
	 * Riepilogo delle risposte da passare al modello.
	 *
	 * @param int   $post_id   ID post.
	 * @param array $overrides Valori non ancora salvati, dal modulo aperto.
	 * @return string
	 */
	public static function context( $post_id, $overrides = array() ) {
		$righe   = array();
		$is_city = GLP_Post_Types::is_city( $post_id );

		$righe[] = __( 'TIPO DI PAGINA:', 'geo-landing-pages' ) . ' ' . ( $is_city
			? __( 'pagina città (panoramica dei servizi in quella città)', 'geo-landing-pages' )
			: __( 'pagina di un singolo servizio in una città', 'geo-landing-pages' ) );
		$righe[] = __( 'TITOLO DELLA PAGINA:', 'geo-landing-pages' ) . ' ' . get_the_title( $post_id );

		$city = GLP_Post_Types::city_post( $post_id );
		if ( $city && (int) $city->ID !== (int) $post_id ) {
			$righe[] = __( 'CITTÀ DI APPARTENENZA:', 'geo-landing-pages' ) . ' ' . $city->post_title;
		}

		$brand = GLP_Settings::get( 'brand', '' );
		if ( '' !== $brand ) {
			$righe[] = __( 'ATTIVITÀ:', 'geo-landing-pages' ) . ' ' . $brand;
		}

		foreach ( GLP_Questionnaire::fields() as $key => $field ) {
			// I campi SEO non sono fatti da raccontare: sono risultati.
			if ( in_array( $key, array( 'seo_title', 'seo_description', 'robots', 'canonical', 'og_image', 'citta_correlate', 'form_shortcode' ), true ) ) {
				continue;
			}

			$value = array_key_exists( $key, $overrides ) ? $overrides[ $key ] : GLP_Meta::get( $post_id, $key );
			if ( GLP_Meta::is_empty( $value ) ) {
				continue;
			}

			$righe[] = self::format_value( $field, $value, $post_id );
		}

		return implode( "\n", array_filter( $righe ) );
	}

	/**
	 * Trasforma una risposta in una riga leggibile dal modello.
	 *
	 * @param array $field   Definizione campo.
	 * @param mixed $value   Valore.
	 * @param int   $post_id ID post.
	 * @return string
	 */
	private static function format_value( $field, $value, $post_id ) {
		$label = rtrim( $field['label'], '?:' );

		if ( 'repeater' === $field['type'] ) {
			$righe = array();
			foreach ( (array) $value as $row ) {
				$cells = array();
				foreach ( $field['subfields'] as $sub_key => $sub ) {
					if ( isset( $row[ $sub_key ] ) && '' !== trim( (string) $row[ $sub_key ] ) ) {
						$cells[] = $sub['label'] . ': ' . GLP_Content::render( $row[ $sub_key ], $post_id );
					}
				}
				if ( ! empty( $cells ) ) {
					$righe[] = '  - ' . implode( ' | ', $cells );
				}
			}
			return empty( $righe ) ? '' : $label . ":\n" . implode( "\n", $righe );
		}

		if ( 'hours' === $field['type'] ) {
			$giorni = GLP_Meta::days();
			$righe  = array();
			foreach ( (array) $value as $day => $row ) {
				if ( ! isset( $giorni[ $day ] ) || ! is_array( $row ) ) {
					continue;
				}
				if ( ! empty( $row['closed'] ) ) {
					$righe[] = $giorni[ $day ][0] . ': chiuso';
				} elseif ( ! empty( $row['open'] ) && ! empty( $row['close'] ) ) {
					$righe[] = $giorni[ $day ][0] . ': ' . $row['open'] . '-' . $row['close'];
				}
			}
			return empty( $righe ) ? '' : $label . ': ' . implode( '; ', $righe );
		}

		if ( is_array( $value ) ) {
			$voci = array();
			foreach ( $value as $voce ) {
				$voci[] = GLP_Content::render( (string) $voce, $post_id );
			}
			return $label . ': ' . implode( '; ', $voci );
		}

		if ( 'checkbox' === $field['type'] ) {
			return $label . ': ' . ( $value ? __( 'sì', 'geo-landing-pages' ) : __( 'no', 'geo-landing-pages' ) );
		}

		return $label . ': ' . GLP_Content::render( (string) $value, $post_id );
	}

	/**
	 * Schema della risposta strutturata, per formato.
	 *
	 * @param string $format Formato.
	 * @return array|null
	 */
	private static function response_schema( $format ) {
		switch ( $format ) {
			case 'list':
				return array(
					'type'  => 'ARRAY',
					'items' => array( 'type' => 'STRING' ),
				);

			case 'faq':
				return array(
					'type'  => 'ARRAY',
					'items' => array(
						'type'       => 'OBJECT',
						'properties' => array(
							'domanda'  => array( 'type' => 'STRING' ),
							'risposta' => array( 'type' => 'STRING' ),
						),
						'required'   => array( 'domanda', 'risposta' ),
					),
				);

			case 'processo':
				return array(
					'type'  => 'ARRAY',
					'items' => array(
						'type'       => 'OBJECT',
						'properties' => array(
							'titolo'      => array( 'type' => 'STRING' ),
							'descrizione' => array( 'type' => 'STRING' ),
							'durata'      => array( 'type' => 'STRING' ),
						),
						'required'   => array( 'titolo', 'descrizione' ),
					),
				);
		}
		return null;
	}

	/**
	 * Esegue una richiesta all'API Gemini.
	 *
	 * @param string $prompt  Richiesta.
	 * @param string $system  Istruzioni di sistema.
	 * @param string $format  Formato atteso.
	 * @return array|WP_Error Risposta decodificata.
	 */
	public static function request( $prompt, $system, $format = 'text' ) {
		$key = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'glp_no_key', __( 'Chiave API Gemini non configurata.', 'geo-landing-pages' ) );
		}

		$model = GLP_Settings::get( 'gemini_model', 'gemini-2.5-flash' );

		$body = array(
			'system_instruction' => array(
				'parts' => array( array( 'text' => $system ) ),
			),
			'contents'           => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $prompt ) ),
				),
			),
			'generationConfig'   => array(
				'temperature'     => (float) GLP_Settings::get( 'ai_temperature', '0.4' ),
				'maxOutputTokens' => 2048,
			),
		);

		$schema = self::response_schema( $format );
		if ( $schema ) {
			$body['generationConfig']['responseMimeType'] = 'application/json';
			$body['generationConfig']['responseSchema']   = $schema;
		}

		$response = wp_remote_post(
			self::ENDPOINT . '/models/' . rawurlencode( $model ) . ':generateContent',
			array(
				'timeout' => 45,
				'headers' => array(
					'Content-Type'    => 'application/json',
					'x-goog-api-key'  => $key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : wp_remote_retrieve_response_message( $response );
			return new WP_Error(
				'glp_api_error',
				sprintf(
					/* translators: 1: codice HTTP, 2: messaggio di errore. */
					__( 'Gemini ha risposto con errore %1$d: %2$s', 'geo-landing-pages' ),
					$code,
					$message
				)
			);
		}

		// Blocchi dei filtri di sicurezza del modello.
		if ( isset( $data['promptFeedback']['blockReason'] ) ) {
			return new WP_Error(
				'glp_blocked',
				sprintf(
					/* translators: %s: motivo del blocco. */
					__( 'Richiesta bloccata dai filtri del modello (%s).', 'geo-landing-pages' ),
					$data['promptFeedback']['blockReason']
				)
			);
		}

		$text = isset( $data['candidates'][0]['content']['parts'][0]['text'] )
			? $data['candidates'][0]['content']['parts'][0]['text']
			: '';

		if ( '' === trim( $text ) ) {
			$finish = isset( $data['candidates'][0]['finishReason'] ) ? $data['candidates'][0]['finishReason'] : '';
			return new WP_Error(
				'glp_empty',
				'' !== $finish
					/* translators: %s: motivo di interruzione. */
					? sprintf( __( 'Il modello non ha prodotto testo (%s).', 'geo-landing-pages' ), $finish )
					: __( 'Il modello non ha prodotto testo.', 'geo-landing-pages' )
			);
		}

		return array( 'text' => $text );
	}

	/**
	 * Converte la risposta nel formato del campo di destinazione.
	 *
	 * @param string $text   Testo grezzo.
	 * @param string $format Formato.
	 * @return array|string|WP_Error
	 */
	private static function parse( $text, $format ) {
		if ( 'text' === $format ) {
			// Ripulisce eventuali virgolette o markdown residui.
			$clean = trim( $text );
			$clean = preg_replace( '/^```[a-z]*\s*|\s*```$/mu', '', $clean );
			$clean = preg_replace( '/\*\*(.+?)\*\*/su', '$1', $clean );        // grassetto
			$clean = preg_replace( '/__(.+?)__/su', '$1', $clean );              // grassetto alternativo
			$clean = preg_replace( '/(?<![\w*])\*([^*\n]+?)\*(?![\w*])/su', '$1', $clean ); // corsivo
			$clean = preg_replace( '/^\s*(?:#{1,6}|[*+>-])\s+/mu', '', $clean ); // titoli ed elenchi
			return trim( $clean, " \t\n\"'" );
		}

		$decoded = json_decode( trim( $text ), true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'glp_parse', __( 'Risposta del modello non interpretabile.', 'geo-landing-pages' ) );
		}

		switch ( $format ) {
			case 'list':
				$out = array();
				foreach ( $decoded as $voce ) {
					if ( is_string( $voce ) && '' !== trim( $voce ) ) {
						$out[] = sanitize_text_field( trim( $voce ) );
					}
				}
				return $out;

			case 'faq':
				$out = array();
				foreach ( $decoded as $row ) {
					if ( ! is_array( $row ) || empty( $row['domanda'] ) ) {
						continue;
					}
					$out[] = array(
						'domanda'  => sanitize_text_field( $row['domanda'] ),
						'risposta' => sanitize_textarea_field( isset( $row['risposta'] ) ? $row['risposta'] : '' ),
					);
				}
				return $out;

			case 'processo':
				$out = array();
				foreach ( $decoded as $row ) {
					if ( ! is_array( $row ) || empty( $row['titolo'] ) ) {
						continue;
					}
					$out[] = array(
						'titolo'      => sanitize_text_field( $row['titolo'] ),
						'descrizione' => sanitize_textarea_field( isset( $row['descrizione'] ) ? $row['descrizione'] : '' ),
						'durata'      => sanitize_text_field( isset( $row['durata'] ) ? $row['durata'] : '' ),
					);
				}
				return $out;
		}

		return new WP_Error( 'glp_parse', __( 'Formato non gestito.', 'geo-landing-pages' ) );
	}

	/**
	 * Legge i valori non salvati inviati dal modulo di modifica.
	 *
	 * @return array
	 */
	private static function submitted_overrides() {
		if ( ! isset( $_POST['glp'] ) || ! is_array( $_POST['glp'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verificato dal chiamante.
			return array();
		}
		$raw       = wp_unslash( $_POST['glp'] ); // phpcs:ignore WordPress.Security -- sanitizzato per campo qui sotto.
		$overrides = array();

		foreach ( GLP_Questionnaire::fields() as $key => $field ) {
			if ( ! isset( $raw[ $key ] ) ) {
				continue;
			}
			$overrides[ $key ] = GLP_Meta::sanitize_value( $raw[ $key ], $field );
		}
		return $overrides;
	}

	/**
	 * Endpoint AJAX: genera il testo di un campo.
	 */
	public static function ajax_generate() {
		check_ajax_referer( 'glp_ai', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$task_id = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'geo-landing-pages' ) ), 403 );
		}
		if ( ! self::is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Assistente AI non attivo: configura la chiave Gemini nelle impostazioni.', 'geo-landing-pages' ) ), 400 );
		}

		$tasks = self::tasks();
		if ( ! isset( $tasks[ $task_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Operazione non riconosciuta.', 'geo-landing-pages' ) ), 400 );
		}
		$task = $tasks[ $task_id ];

		$overrides = self::submitted_overrides();
		$context   = self::context( $post_id, $overrides );

		$prompt = __( 'DATI FORNITI DALL\'AZIENDA (sono gli unici fatti che puoi usare):', 'geo-landing-pages' )
			. "\n\n" . $context . "\n\n"
			. __( 'COMPITO:', 'geo-landing-pages' ) . ' ' . $task['prompt'];

		if ( ! empty( $task['limit'] ) && 'text' === $task['format'] ) {
			$prompt .= "\n" . sprintf(
				/* translators: %d: numero massimo di caratteri. */
				__( 'Non superare i %d caratteri.', 'geo-landing-pages' ),
				(int) $task['limit']
			);
		}

		$result = self::request( $prompt, self::system_instruction( $post_id ), $task['format'] );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
		}

		$value = self::parse( $result['text'], $task['format'] );
		if ( is_wp_error( $value ) ) {
			wp_send_json_error( array( 'message' => $value->get_error_message() ), 502 );
		}
		if ( is_array( $value ) && empty( $value ) ) {
			wp_send_json_error( array( 'message' => __( 'Il modello non ha restituito contenuti utilizzabili. Aggiungi qualche risposta in più e riprova.', 'geo-landing-pages' ) ), 502 );
		}

		// Segna il campo come generato: va riletto prima di pubblicare.
		$flagged = get_post_meta( $post_id, self::META_FLAG, true );
		$flagged = is_array( $flagged ) ? $flagged : array();
		if ( ! in_array( $task['target'], $flagged, true ) ) {
			$flagged[] = $task['target'];
			update_post_meta( $post_id, self::META_FLAG, $flagged );
		}

		wp_send_json_success(
			array(
				'target' => $task['target'],
				'format' => $task['format'],
				'value'  => $value,
				'notice' => __( 'Testo generato: rileggilo e correggilo prima di salvare.', 'geo-landing-pages' ),
			)
		);
	}

	/**
	 * Endpoint AJAX: elenca i modelli disponibili per la chiave configurata.
	 */
	public static function ajax_models() {
		check_ajax_referer( 'glp_ai', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'geo-landing-pages' ) ), 403 );
		}

		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		if ( '' === $key || false !== strpos( $key, '•' ) ) {
			$key = self::api_key();
		}
		if ( '' === $key ) {
			wp_send_json_error( array( 'message' => __( 'Inserisci prima la chiave API.', 'geo-landing-pages' ) ), 400 );
		}

		$response = wp_remote_get(
			self::ENDPOINT . '/models',
			array(
				'timeout' => 20,
				'headers' => array( 'x-goog-api-key' => $key ),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ), 502 );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Chiave non valida o servizio non raggiungibile.', 'geo-landing-pages' );
			wp_send_json_error( array( 'message' => $message ), 502 );
		}

		$models = array();
		foreach ( (array) ( isset( $data['models'] ) ? $data['models'] : array() ) as $model ) {
			$methods = isset( $model['supportedGenerationMethods'] ) ? (array) $model['supportedGenerationMethods'] : array();
			if ( ! in_array( 'generateContent', $methods, true ) ) {
				continue;
			}
			$name = isset( $model['name'] ) ? str_replace( 'models/', '', $model['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$models[] = array(
				'id'    => $name,
				'label' => isset( $model['displayName'] ) ? $model['displayName'] . ' (' . $name . ')' : $name,
			);
		}

		if ( empty( $models ) ) {
			wp_send_json_error( array( 'message' => __( 'La chiave funziona ma non espone modelli compatibili.', 'geo-landing-pages' ) ), 502 );
		}

		wp_send_json_success( array( 'models' => $models ) );
	}

	/**
	 * Endpoint AJAX: l'utente conferma di aver riletto un campo generato.
	 */
	public static function ajax_accept() {
		check_ajax_referer( 'glp_ai', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$field   = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'geo-landing-pages' ) ), 403 );
		}

		$flagged = get_post_meta( $post_id, self::META_FLAG, true );
		$flagged = is_array( $flagged ) ? array_values( array_diff( $flagged, array( $field ) ) ) : array();

		if ( empty( $flagged ) ) {
			delete_post_meta( $post_id, self::META_FLAG );
		} else {
			update_post_meta( $post_id, self::META_FLAG, $flagged );
		}

		wp_send_json_success( array( 'remaining' => count( $flagged ) ) );
	}

	/**
	 * Campi ancora da rileggere.
	 *
	 * @param int $post_id ID post.
	 * @return array
	 */
	public static function pending_fields( $post_id ) {
		$flagged = get_post_meta( $post_id, self::META_FLAG, true );
		return is_array( $flagged ) ? $flagged : array();
	}
}
