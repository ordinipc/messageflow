<?php
/**
 * Generazione del contenuto della landing page a partire dalle risposte.
 *
 * Il plugin non inventa testo: compone in sezioni i dati inseriti nel
 * questionario e sostituisce i segnaposto nei modelli.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Content {

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'append_sections' ), 20 );
		add_filter( 'template_include', array( __CLASS__, 'template' ) );
	}

	/**
	 * Segnaposto disponibili nei modelli.
	 *
	 * @param int $post_id ID post.
	 * @return array
	 */
	public static function tokens( $post_id ) {
		$m       = GLP_Meta::all( $post_id );
		$post    = get_post( $post_id );
		$city    = GLP_Post_Types::city_post( $post_id );
		$is_city = GLP_Post_Types::is_city( $post_id );

		$servizio = $m['servizio_nome'];
		if ( '' === $servizio ) {
			$servizio = $is_city
				? GLP_Settings::get( 'servizio_default', '' )
				: ( $post ? $post->post_title : '' );
		}

		$citta = $m['citta'];
		if ( '' === $citta && $city ) {
			$citta = $city->post_title;
		}

		$perche      = is_array( $m['perche_noi'] ) ? $m['perche_noi'] : array();
		$perche_uno  = ! empty( $perche ) ? rtrim( (string) $perche[0], '.' ) : '';

		$anni_frase = '';
		if ( '' !== (string) $m['anni_attivita'] ) {
			/* translators: %s: numero di anni. */
			$anni_frase = ' ' . sprintf( __( 'da oltre %s anni', 'geo-landing-pages' ), $m['anni_attivita'] );
		}

		$interventi_frase = '';
		if ( '' !== (string) $m['interventi_anno'] ) {
			/* translators: %s: numero di interventi. */
			$interventi_frase = ' ' . sprintf( __( 'con circa %s interventi all\'anno in zona', 'geo-landing-pages' ), $m['interventi_anno'] );
		}

		$tokens = array(
			'{citta}'            => $citta,
			'{provincia}'        => $m['provincia'],
			'{regione}'          => $m['regione'],
			'{cap}'              => $m['cap'],
			'{servizio}'         => $servizio,
			'{servizio_default}' => GLP_Settings::get( 'servizio_default', $servizio ),
			'{brand}'            => $m['azienda'] ? $m['azienda'] : GLP_Settings::get( 'brand', get_bloginfo( 'name' ) ),
			'{telefono}'         => $m['telefono'] ? $m['telefono'] : GLP_Settings::get( 'telefono', '' ),
			'{indirizzo}'        => $m['indirizzo'],
			'{prezzo_da}'        => $m['prezzo_da'],
			'{anni}'             => $m['anni_attivita'],
			'{anni_frase}'       => $anni_frase,
			'{interventi}'       => $m['interventi_anno'],
			'{interventi_frase}' => $interventi_frase,
			'{tempo_intervento}' => $m['tempo_intervento'],
			'{perche_primo}'     => $perche_uno,
			'{titolo}'           => $post ? $post->post_title : '',
			'{sep}'              => ' | ',
			'{anno}'             => gmdate( 'Y' ),
		);

		/**
		 * Segnaposto aggiuntivi.
		 *
		 * @param array $tokens  Segnaposto.
		 * @param int   $post_id ID post.
		 */
		return apply_filters( 'glp_tokens', $tokens, $post_id );
	}

	/**
	 * Applica i segnaposto a una stringa.
	 *
	 * @param string $template Modello.
	 * @param int    $post_id  ID post.
	 * @return string
	 */
	public static function render( $template, $post_id ) {
		$template = (string) $template;
		$tokens   = self::tokens( $post_id );

		// Quali segnaposto presenti nel testo si sono risolti in stringa vuota?
		$empty_tokens = array();
		foreach ( $tokens as $token => $value ) {
			if ( '' === trim( (string) $value ) && false !== strpos( $template, $token ) ) {
				$empty_tokens[] = $token;
			}
		}

		$out = strtr( $template, $tokens );
		$out = preg_replace( '/\s{2,}/u', ' ', $out );

		// Le ripuliture aggressive servono solo dove un segnaposto è rimasto
		// vuoto: su un testo scritto a mano cancellerebbero parole vere.
		if ( ! empty( $empty_tokens ) ) {
			$out = preg_replace( '/\(\s*\)/u', '', $out );        // parentesi rimaste vuote
			$out = preg_replace( '/\s+([,.;:])/u', '$1', $out );    // spazio prima della punteggiatura
			$out = preg_replace( '/\s{2,}/u', ' ', $out );

			// Preposizione orfana solo se il testo iniziava con il segnaposto vuoto.
			if ( preg_match( '/^(\{[a-z_]+\})/u', $template, $m ) && in_array( $m[1], $empty_tokens, true ) ) {
				$out = preg_replace( '/^\s*(?:a|di|in|per|da)\s+/iu', '', $out );
			}

			$out = preg_replace( '/^[\s|]+|[\s|]+$/u', '', $out ); // separatori orfani ai bordi
			$out = preg_replace( '/[\s]*[–—-][\s]*$/u', '', $out );
		}

		return trim( $out );
	}

	/**
	 * Iniziale maiuscola, compatibile con i caratteri accentati.
	 *
	 * @param string $text Testo.
	 * @return string
	 */
	public static function ucfirst_text( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return '';
		}
		if ( function_exists( 'mb_substr' ) ) {
			return mb_strtoupper( mb_substr( $text, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $text, 1, null, 'UTF-8' );
		}
		return ucfirst( $text );
	}

	/**
	 * H1 della pagina.
	 *
	 * @param int $post_id ID post.
	 * @return string
	 */
	public static function h1( $post_id ) {
		$template = GLP_Settings::get( 'h1_template', '{servizio} a {citta}' );
		$h1       = self::ucfirst_text( self::render( $template, $post_id ) );
		return '' !== $h1 ? $h1 : get_the_title( $post_id );
	}

	/**
	 * Le sezioni generate vengono accodate al contenuto dell'editor.
	 *
	 * @param string $content Contenuto.
	 * @return string
	 */
	public static function append_sections( $content ) {
		if ( ! is_singular( GLP_POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( 'filter' !== GLP_Settings::get( 'template_mode', 'filter' ) ) {
			return $content;
		}
		$post_id = get_the_ID();
		return self::hero( $post_id ) . $content . self::sections( $post_id );
	}

	/**
	 * Intestazione grafica in stile brand.
	 *
	 * @param int $post_id ID post.
	 * @return string
	 */
	public static function hero( $post_id ) {
		$modo = GLP_Settings::get( 'hero_mode', 'h1' );
		if ( 'off' === $modo ) {
			return '';
		}

		$post_id = (int) $post_id;
		$tokens  = self::tokens( $post_id );

		$occhiello = $tokens['{brand}'];
		$titolo    = self::h1( $post_id );
		$testo     = GLP_Meta::get( $post_id, 'seo_description' );
		if ( '' === $testo ) {
			$testo = self::render( GLP_Settings::get( 'intro_template', '' ), $post_id );
		}

		$tel = $tokens['{telefono}'];
		$wa  = GLP_Meta::get( $post_id, 'whatsapp' );
		$wa  = '' !== $wa ? $wa : GLP_Settings::get( 'whatsapp', '' );

		$sfondo = GLP_Meta::get( $post_id, 'og_image' );
		if ( '' === $sfondo ) {
			$sfondo = get_the_post_thumbnail_url( $post_id, 'full' );
		}

		$stile = $sfondo ? ' style="background-image:url(' . esc_url( $sfondo ) . ')"' : '';
		$class = 'glp-hero' . ( $sfondo ? ' glp-hero--image' : '' );

		$html = '<header class="' . $class . '"' . $stile . '><div class="glp-hero__inner">';

		if ( '' !== $occhiello ) {
			$html .= '<p class="glp-hero__eyebrow">' . esc_html( $occhiello ) . '</p>';
		}

		// Con 'notitle' il titolo lo stampa già il tema: non lo ripetiamo.
		if ( 'h1' === $modo && '' !== $titolo ) {
			$html .= '<h1 class="glp-hero__title">' . esc_html( $titolo ) . '</h1>';
		}

		if ( '' !== $testo ) {
			$html .= '<p class="glp-hero__text">' . esc_html( $testo ) . '</p>';
		}

		$bottoni = '';
		if ( '' !== $tel ) {
			$bottoni .= '<a class="glp-btn glp-btn--primary" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ) . '">'
				/* translators: %s: numero di telefono. */
				. esc_html( sprintf( __( 'Chiama %s', 'geo-landing-pages' ), $tel ) ) . '</a>';
		}
		if ( '' !== $wa ) {
			$messaggio = rawurlencode( self::render( __( 'Salve, vi scrivo da {citta}: avrei bisogno di {servizio}.', 'geo-landing-pages' ), $post_id ) );
			$bottoni  .= '<a class="glp-btn glp-btn--ghost" rel="nofollow noopener" target="_blank" href="https://wa.me/'
				. esc_attr( preg_replace( '/[^0-9]/', '', $wa ) ) . '?text=' . $messaggio . '">'
				. esc_html__( 'Scrivici su WhatsApp', 'geo-landing-pages' ) . '</a>';
		}
		if ( '' !== $bottoni ) {
			$html .= '<div class="glp-hero__cta">' . $bottoni . '</div>';
		}

		$html .= '</div></header>';

		/**
		 * HTML dell'intestazione.
		 *
		 * @param string $html    HTML.
		 * @param int    $post_id ID post.
		 */
		return apply_filters( 'glp_hero_html', $html, $post_id );
	}

	/**
	 * HTML completo delle sezioni generate.
	 *
	 * @param int $post_id ID post.
	 * @return string
	 */
	public static function sections( $post_id ) {
		$post_id = (int) $post_id;
		$is_city = GLP_Post_Types::is_city( $post_id );

		$parts = array(
			self::section_intro( $post_id ),
			self::section_deep( $post_id ),
			$is_city ? self::section_services( $post_id ) : self::section_included( $post_id ),
			self::section_why( $post_id ),
			self::section_process( $post_id ),
			self::section_prices( $post_id ),
			self::section_areas( $post_id ),
			self::section_reviews( $post_id ),
			self::section_team( $post_id ),
			self::section_directions( $post_id ),
			self::section_faq( $post_id ),
			self::section_cta( $post_id ),
			self::section_related( $post_id ),
		);

		$html = implode( "\n", array_filter( $parts ) );

		/**
		 * HTML delle sezioni generate.
		 *
		 * @param string $html    HTML.
		 * @param int    $post_id ID post.
		 */
		$html = apply_filters( 'glp_sections_html', $html, $post_id );

		return '' === $html ? '' : '<div class="glp-sections">' . $html . '</div>';
	}

	/**
	 * Valore di una cella di un campo ripetibile.
	 *
	 * @param array  $row Riga.
	 * @param string $key Chiave.
	 * @return string
	 */
	private static function cell( $row, $key ) {
		return isset( $row[ $key ] ) ? trim( (string) $row[ $key ] ) : '';
	}

	/**
	 * Apre una sezione con titolo.
	 *
	 * @param string $id    Slug sezione.
	 * @param string $title Titolo.
	 * @return string
	 */
	private static function open( $id, $title ) {
		return '<section class="glp-section glp-section--' . esc_attr( $id ) . '" id="glp-' . esc_attr( $id ) . '">'
			. ( '' === $title ? '' : '<h2 class="glp-section__title">' . esc_html( $title ) . '</h2>' );
	}

	/** Introduzione. */
	private static function section_intro( $post_id ) {
		$intro = GLP_Meta::get( $post_id, 'seo_description' );
		$tpl   = GLP_Settings::get( 'intro_template', '' );
		$text  = '' !== $tpl ? self::render( $tpl, $post_id ) : '';

		if ( '' === $text ) {
			return '';
		}

		$tempo = GLP_Meta::get( $post_id, 'tempo_intervento' );
		if ( '' !== $tempo ) {
			/* translators: %s: tempo di intervento dichiarato. */
			$text .= ' ' . sprintf( esc_html__( 'Tempo di intervento dichiarato: %s.', 'geo-landing-pages' ), $tempo );
		}
		unset( $intro );

		return self::open( 'intro', '' ) . '<p class="glp-intro">' . esc_html( $text ) . '</p></section>';
	}

	/** Approfondimento redazionale. */
	private static function section_deep( $post_id ) {
		$testo = GLP_Meta::get( $post_id, 'approfondimento' );
		if ( '' === $testo ) {
			return '';
		}
		$citta = GLP_Meta::get( $post_id, 'citta' );
		$tokens = self::tokens( $post_id );
		$title  = '' !== $citta
			/* translators: 1: servizio, 2: città. */
			? self::ucfirst_text( trim( sprintf( __( '%1$s a %2$s: cosa sapere', 'geo-landing-pages' ), $tokens['{servizio}'], $citta ), ' :' ) )
			: __( 'Approfondimento', 'geo-landing-pages' );

		return self::open( 'approfondimento', $title )
			. wpautop( esc_html( $testo ) )
			. '</section>';
	}

	/** Elenco dei servizi della città (solo pagina città). */
	private static function section_services( $post_id ) {
		$services = GLP_Post_Types::services_of( $post_id );
		if ( empty( $services ) ) {
			return '';
		}
		$citta = GLP_Meta::get( $post_id, 'citta' );
		$citta = '' !== $citta ? $citta : get_the_title( $post_id );

		/* translators: %s: nome della città. */
		$html = self::open( 'servizi', sprintf( __( 'I nostri servizi a %s', 'geo-landing-pages' ), $citta ) );
		$html .= '<ul class="glp-cards">';
		foreach ( $services as $service ) {
			$excerpt = GLP_Meta::get( $service->ID, 'seo_description' );
			$prezzo  = GLP_Meta::get( $service->ID, 'prezzo_da' );
			$html   .= '<li class="glp-card"><a class="glp-card__link" href="' . esc_url( get_permalink( $service ) ) . '">'
				. '<span class="glp-card__title">' . esc_html( $service->post_title ) . '</span>'
				. ( '' !== $excerpt ? '<span class="glp-card__text">' . esc_html( wp_trim_words( $excerpt, 22 ) ) . '</span>' : '' )
				/* translators: %s: prezzo di partenza. */
				. ( '' !== $prezzo ? '<span class="glp-card__price">' . esc_html( sprintf( __( 'da %s €', 'geo-landing-pages' ), $prezzo ) ) . '</span>' : '' )
				. '</a></li>';
		}
		$html .= '</ul></section>';
		return $html;
	}

	/** Cosa comprende il servizio. */
	private static function section_included( $post_id ) {
		$items = GLP_Meta::get( $post_id, 'servizi_inclusi' );
		if ( ! is_array( $items ) || empty( $items ) ) {
			return '';
		}
		$html = self::open( 'incluso', __( 'Cosa comprende il servizio', 'geo-landing-pages' ) ) . '<ul class="glp-list glp-list--check">';
		foreach ( $items as $item ) {
			$html .= '<li>' . esc_html( $item ) . '</li>';
		}
		return $html . '</ul></section>';
	}

	/** Perché sceglierci qui. */
	private static function section_why( $post_id ) {
		$items = GLP_Meta::get( $post_id, 'perche_noi' );
		if ( ! is_array( $items ) || empty( $items ) ) {
			return '';
		}
		$citta = GLP_Meta::get( $post_id, 'citta' );
		/* translators: %s: nome della città. */
		$title = '' !== $citta ? sprintf( __( 'Perché sceglierci a %s', 'geo-landing-pages' ), $citta ) : __( 'Perché sceglierci', 'geo-landing-pages' );

		$html = self::open( 'perche', $title ) . '<ul class="glp-list glp-list--why">';
		foreach ( $items as $item ) {
			$html .= '<li>' . esc_html( $item ) . '</li>';
		}
		$html .= '</ul>';

		$stats = array();
		$anni  = GLP_Meta::get( $post_id, 'anni_attivita' );
		$int   = GLP_Meta::get( $post_id, 'interventi_anno' );
		$tempo = GLP_Meta::get( $post_id, 'tempo_intervento' );
		if ( '' !== $anni ) {
			$stats[] = array( $anni, __( 'anni di attività', 'geo-landing-pages' ) );
		}
		if ( '' !== $int ) {
			$stats[] = array( $int, __( 'interventi all\'anno', 'geo-landing-pages' ) );
		}
		if ( '' !== $tempo ) {
			$stats[] = array( $tempo, __( 'tempo di intervento', 'geo-landing-pages' ) );
		}
		if ( ! empty( $stats ) ) {
			$html .= '<ul class="glp-stats">';
			foreach ( $stats as $stat ) {
				// Un valore non numerico (es. "entro 30 minuti") va reso come
				// testo: alla dimensione di una cifra risulterebbe sproporzionato.
				$numerico = is_numeric( str_replace( array( '.', ',', ' ' ), '', (string) $stat[0] ) );
				$html .= '<li class="' . ( $numerico ? 'glp-stat--num' : 'glp-stat--text' ) . '">'
					. '<strong>' . esc_html( $stat[0] ) . '</strong>'
					. '<span>' . esc_html( $stat[1] ) . '</span></li>';
			}
			$html .= '</ul>';
		}

		return $html . '</section>';
	}

	/** Come funziona, passo per passo. */
	private static function section_process( $post_id ) {
		$steps = GLP_Meta::get( $post_id, 'processo' );
		if ( ! is_array( $steps ) || empty( $steps ) ) {
			return '';
		}
		$html = self::open( 'processo', __( 'Come funziona, passo per passo', 'geo-landing-pages' ) ) . '<ol class="glp-steps">';
		foreach ( $steps as $step ) {
			$titolo = self::cell( $step, 'titolo' );
			$desc   = self::cell( $step, 'descrizione' );
			$durata = self::cell( $step, 'durata' );
			if ( '' === $titolo && '' === $desc ) {
				continue;
			}
			$html .= '<li class="glp-step">'
				. ( '' !== $titolo ? '<h3 class="glp-step__title">' . esc_html( $titolo ) . '</h3>' : '' )
				. ( '' !== $desc ? '<p>' . esc_html( $desc ) . '</p>' : '' )
				. ( '' !== $durata ? '<p class="glp-step__time">' . esc_html( $durata ) . '</p>' : '' )
				. '</li>';
		}
		return $html . '</ol></section>';
	}

	/** Prezzi e garanzia. */
	private static function section_prices( $post_id ) {
		$da       = GLP_Meta::get( $post_id, 'prezzo_da' );
		$a        = GLP_Meta::get( $post_id, 'prezzo_a' );
		$garanzia = GLP_Meta::get( $post_id, 'garanzia' );
		$gratis   = GLP_Meta::get( $post_id, 'preventivo_gratuito' );
		$pagamenti = GLP_Meta::get( $post_id, 'metodi_pagamento' );

		if ( '' === $da && '' === $garanzia && empty( $pagamenti ) ) {
			return '';
		}

		$html = self::open( 'prezzi', __( 'Quanto costa', 'geo-landing-pages' ) );

		if ( '' !== $da ) {
			$prezzo = '' !== $a
				/* translators: 1: prezzo minimo, 2: prezzo massimo. */
				? sprintf( __( 'da %1$s € a %2$s €', 'geo-landing-pages' ), $da, $a )
				/* translators: %s: prezzo minimo. */
				: sprintf( __( 'a partire da %s €', 'geo-landing-pages' ), $da );
			$html .= '<p class="glp-price">' . esc_html( $prezzo ) . '</p>';
		}
		if ( $gratis ) {
			$html .= '<p class="glp-price__free">' . esc_html__( 'Preventivo gratuito e senza impegno.', 'geo-landing-pages' ) . '</p>';
		}
		if ( '' !== $garanzia ) {
			$html .= '<p class="glp-guarantee">' . esc_html( $garanzia ) . '</p>';
		}
		if ( is_array( $pagamenti ) && ! empty( $pagamenti ) ) {
			$html .= '<p class="glp-payments">' . esc_html__( 'Pagamenti accettati:', 'geo-landing-pages' ) . ' ' . esc_html( implode( ', ', $pagamenti ) ) . '</p>';
		}

		return $html . '</section>';
	}

	/** Zone servite. */
	private static function section_areas( $post_id ) {
		$zone    = GLP_Meta::get( $post_id, 'zone_servite' );
		$comuni  = GLP_Meta::get( $post_id, 'comuni_limitrofi' );
		$zone    = is_array( $zone ) ? $zone : array();
		$comuni  = is_array( $comuni ) ? $comuni : array();
		if ( empty( $zone ) && empty( $comuni ) ) {
			return '';
		}
		$citta = GLP_Meta::get( $post_id, 'citta' );
		/* translators: %s: nome della città. */
		$title = '' !== $citta ? sprintf( __( 'Zone servite a %s e dintorni', 'geo-landing-pages' ), $citta ) : __( 'Zone servite', 'geo-landing-pages' );

		$html = self::open( 'zone', $title );
		if ( ! empty( $zone ) ) {
			$html .= '<p class="glp-areas__label">' . esc_html__( 'Quartieri e zone della città:', 'geo-landing-pages' ) . '</p>';
			$html .= '<ul class="glp-tags">';
			foreach ( $zone as $z ) {
				$html .= '<li>' . esc_html( $z ) . '</li>';
			}
			$html .= '</ul>';
		}
		if ( ! empty( $comuni ) ) {
			$html .= '<p class="glp-areas__label">' . esc_html__( 'Comuni limitrofi:', 'geo-landing-pages' ) . '</p>';
			$html .= '<ul class="glp-tags">';
			foreach ( $comuni as $c ) {
				$html .= '<li>' . esc_html( $c ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html . '</section>';
	}

	/** Recensioni locali. */
	private static function section_reviews( $post_id ) {
		$reviews = GLP_Meta::get( $post_id, 'testimonianze' );
		if ( ! is_array( $reviews ) || empty( $reviews ) ) {
			return '';
		}
		$citta = GLP_Meta::get( $post_id, 'citta' );
		/* translators: %s: nome della città. */
		$title = '' !== $citta ? sprintf( __( 'Cosa dicono i clienti di %s', 'geo-landing-pages' ), $citta ) : __( 'Cosa dicono i clienti', 'geo-landing-pages' );

		$html = self::open( 'recensioni', $title ) . '<ul class="glp-reviews">';
		foreach ( $reviews as $r ) {
			$testo = self::cell( $r, 'testo' );
			if ( '' === $testo ) {
				continue;
			}
			$voto  = (int) self::cell( $r, 'voto' );
			$nome  = self::cell( $r, 'nome' );
			$zona  = self::cell( $r, 'zona' );
			$fonte = self::cell( $r, 'fonte' );
			$html .= '<li class="glp-review">'
				. ( $voto > 0 ? '<span class="glp-review__rating" aria-label="' . esc_attr( sprintf( '%d/5', $voto ) ) . '">' . esc_html( str_repeat( '★', min( 5, $voto ) ) ) . '</span>' : '' )
				. '<blockquote>' . esc_html( $testo ) . '</blockquote>'
				. '<cite>' . esc_html( trim( $nome . ( '' !== $zona ? ' — ' . $zona : '' ) ) ) . '</cite>'
				. ( '' !== $fonte ? ' <a class="glp-review__src" rel="nofollow noopener" target="_blank" href="' . esc_url( $fonte ) . '">' . esc_html__( 'recensione originale', 'geo-landing-pages' ) . '</a>' : '' )
				. '</li>';
		}
		return $html . '</ul></section>';
	}

	/** Chi risponde del servizio. */
	private static function section_team( $post_id ) {
		$nome = GLP_Meta::get( $post_id, 'referente_nome' );
		$cert = GLP_Meta::get( $post_id, 'certificazioni' );
		$cert = is_array( $cert ) ? $cert : array();
		if ( '' === $nome && empty( $cert ) ) {
			return '';
		}

		$html = self::open( 'team', __( 'Chi si occupa del servizio', 'geo-landing-pages' ) );
		if ( '' !== $nome ) {
			$ruolo      = GLP_Meta::get( $post_id, 'referente_ruolo' );
			$qualifiche = GLP_Meta::get( $post_id, 'referente_qualifiche' );
			$url        = GLP_Meta::get( $post_id, 'referente_url' );

			$label = '' !== $url
				? '<a href="' . esc_url( $url ) . '">' . esc_html( $nome ) . '</a>'
				: esc_html( $nome );

			$html .= '<p class="glp-person"><strong>' . $label . '</strong>'
				. ( '' !== $ruolo ? ' — ' . esc_html( $ruolo ) : '' ) . '</p>';
			if ( '' !== $qualifiche ) {
				$html .= '<p class="glp-person__creds">' . esc_html( $qualifiche ) . '</p>';
			}
		}
		if ( ! empty( $cert ) ) {
			$html .= '<ul class="glp-list glp-list--certs">';
			foreach ( $cert as $c ) {
				$html .= '<li>' . esc_html( $c ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html . '</section>';
	}

	/** Come raggiungerci e mappa. */
	private static function section_directions( $post_id ) {
		$testo = GLP_Meta::get( $post_id, 'come_raggiungerci' );
		$mappa = GLP_Meta::get( $post_id, 'mappa_embed_url' );
		$ind   = GLP_Meta::get( $post_id, 'indirizzo' );
		if ( '' === $testo && '' === $mappa && '' === $ind ) {
			return '';
		}

		$html = self::open( 'dove', __( 'Dove siamo e come raggiungerci', 'geo-landing-pages' ) );
		if ( '' !== $ind ) {
			$citta = GLP_Meta::get( $post_id, 'citta' );
			$cap   = GLP_Meta::get( $post_id, 'cap' );
			$prov  = GLP_Meta::get( $post_id, 'provincia' );
			$full  = trim( $ind . ', ' . trim( $cap . ' ' . $citta ) . ( '' !== $prov ? ' (' . $prov . ')' : '' ), ', ' );
			$html .= '<p class="glp-address">' . esc_html( $full ) . '</p>';
		}
		if ( '' !== $testo ) {
			$html .= wpautop( esc_html( $testo ) );
		}
		if ( '' !== $mappa ) {
			$html .= '<div class="glp-map"><iframe src="' . esc_url( $mappa ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="' . esc_attr__( 'Mappa della sede', 'geo-landing-pages' ) . '" allowfullscreen></iframe></div>';
		}
		return $html . '</section>';
	}

	/** FAQ. */
	public static function section_faq( $post_id ) {
		$faq = GLP_Meta::get( $post_id, 'faq' );
		if ( ! is_array( $faq ) || empty( $faq ) ) {
			return '';
		}
		$html = self::open( 'faq', __( 'Domande frequenti', 'geo-landing-pages' ) ) . '<div class="glp-faq">';
		$i = 0;
		foreach ( $faq as $row ) {
			$domanda = self::cell( $row, 'domanda' );
			if ( '' === $domanda ) {
				continue;
			}
			$html .= '<details class="glp-faq__item"' . ( 0 === $i ? ' open' : '' ) . '>'
				. '<summary class="glp-faq__q">' . esc_html( self::render( $domanda, $post_id ) ) . '</summary>'
				. '<div class="glp-faq__a">' . wpautop( esc_html( self::render( self::cell( $row, 'risposta' ), $post_id ) ) ) . '</div>'
				. '</details>';
			$i++;
		}
		return $html . '</div></section>';
	}

	/** Call to action finale. */
	private static function section_cta( $post_id ) {
		$tel       = GLP_Meta::get( $post_id, 'telefono' );
		$tel       = '' !== $tel ? $tel : GLP_Settings::get( 'telefono', '' );
		$wa        = GLP_Meta::get( $post_id, 'whatsapp' );
		$wa        = '' !== $wa ? $wa : GLP_Settings::get( 'whatsapp', '' );
		$cta_testo = GLP_Meta::get( $post_id, 'cta_testo' );
		$cta_url   = GLP_Meta::get( $post_id, 'cta_url' );
		$form      = GLP_Meta::get( $post_id, 'form_shortcode' );

		if ( '' === $tel && '' === $wa && '' === $cta_url && '' === $form ) {
			return '';
		}

		$citta = GLP_Meta::get( $post_id, 'citta' );
		/* translators: %s: nome della città. */
		$title = '' !== $citta ? sprintf( __( 'Richiedi un intervento a %s', 'geo-landing-pages' ), $citta ) : __( 'Contattaci', 'geo-landing-pages' );

		$html = self::open( 'cta', $title ) . '<div class="glp-cta">';

		if ( '' !== $cta_url ) {
			$label = '' !== $cta_testo ? $cta_testo : __( 'Richiedi un preventivo', 'geo-landing-pages' );
			$html .= '<a class="glp-btn glp-btn--primary" href="' . esc_url( $cta_url ) . '">' . esc_html( $label ) . '</a>';
		} elseif ( '' !== $tel ) {
			$label = '' !== $cta_testo ? $cta_testo : sprintf( '%s %s', __( 'Chiama', 'geo-landing-pages' ), $tel );
			$html .= '<a class="glp-btn glp-btn--primary" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ) . '">' . esc_html( $label ) . '</a>';
		}
		if ( '' !== $wa ) {
			$msg   = rawurlencode( self::render( __( 'Salve, vi scrivo da {citta}: avrei bisogno di {servizio}.', 'geo-landing-pages' ), $post_id ) );
			$html .= '<a class="glp-btn glp-btn--wa" rel="nofollow noopener" target="_blank" href="https://wa.me/' . esc_attr( preg_replace( '/[^0-9]/', '', $wa ) ) . '?text=' . $msg . '">' . esc_html__( 'Scrivi su WhatsApp', 'geo-landing-pages' ) . '</a>';
		}
		$html .= '</div>';

		if ( '' !== $form ) {
			$html .= '<div class="glp-form">' . do_shortcode( $form ) . '</div>';
		}

		return $html . '</section>';
	}

	/** Link interni verso le città vicine o lo stesso servizio altrove. */
	private static function section_related( $post_id ) {
		$links = self::related_links( $post_id );
		if ( empty( $links ) ) {
			return '';
		}
		$is_city = GLP_Post_Types::is_city( $post_id );
		$title   = $is_city
			? __( 'Operiamo anche in queste città', 'geo-landing-pages' )
			: __( 'Lo stesso servizio in altre città', 'geo-landing-pages' );

		$html = self::open( 'correlate', $title ) . '<ul class="glp-tags glp-tags--links">';
		foreach ( $links as $link ) {
			$html .= '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a></li>';
		}
		return $html . '</ul></section>';
	}

	/**
	 * Calcola i link correlati.
	 *
	 * @param int $post_id ID post.
	 * @return array
	 */
	public static function related_links( $post_id ) {
		$links  = array();
		$manual = GLP_Meta::get( $post_id, 'citta_correlate' );

		if ( is_array( $manual ) && ! empty( $manual ) ) {
			foreach ( $manual as $slug ) {
				$related = get_page_by_path( sanitize_title( $slug ), OBJECT, GLP_POST_TYPE );
				if ( $related && 'publish' === $related->post_status ) {
					$links[] = array(
						'url'   => get_permalink( $related ),
						'label' => $related->post_title,
					);
				}
			}
			return $links;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $links;
		}

		if ( GLP_Post_Types::is_city( $post_id ) ) {
			// Altre città, ordinate per titolo, escludendo la corrente.
			foreach ( GLP_Post_Types::cities( 12 ) as $city ) {
				if ( (int) $city->ID === (int) $post_id ) {
					continue;
				}
				$links[] = array(
					'url'   => get_permalink( $city ),
					'label' => $city->post_title,
				);
			}
			return array_slice( $links, 0, 10 );
		}

		// Pagina servizio: stesso servizio nelle altre città.
		$terms = wp_get_object_terms( $post_id, GLP_TAXONOMY, array( 'fields' => 'ids' ) );
		$args  = array(
			'post_type'      => GLP_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'post__not_in'   => array( (int) $post_id ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => GLP_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $terms,
				),
			);
		} else {
			$args['name'] = $post->post_name;
		}

		foreach ( get_posts( $args ) as $sibling ) {
			if ( 0 === (int) $sibling->post_parent ) {
				continue;
			}
			$city    = get_post( $sibling->post_parent );
			$links[] = array(
				'url'   => get_permalink( $sibling ),
				'label' => $sibling->post_title . ( $city ? ' — ' . $city->post_title : '' ),
			);
		}

		return $links;
	}

	/**
	 * Carica il template del plugin quando richiesto.
	 *
	 * @param string $template Template scelto da WordPress.
	 * @return string
	 */
	public static function template( $template ) {
		if ( ! is_singular( GLP_POST_TYPE ) ) {
			return $template;
		}
		if ( 'template' !== GLP_Settings::get( 'template_mode', 'filter' ) ) {
			return $template;
		}
		// Il tema può sovrascrivere il template del plugin.
		$theme = locate_template( array( 'geo-landing-pages/single-' . GLP_POST_TYPE . '.php' ) );
		if ( $theme ) {
			return $theme;
		}
		$plugin = GLP_PATH . 'templates/single-' . GLP_POST_TYPE . '.php';
		return file_exists( $plugin ) ? $plugin : $template;
	}
}
