<?php
/**
 * Generatore delle pagine che un sito locale deve avere.
 *
 * Crea pagine WordPress normali (non landing) già impostate con lo stile
 * del plugin e con i dati aziendali disponibili. Vengono create in bozza:
 * i testi vanno completati prima della pubblicazione.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Pages {

	const META_FLAG = '_glp_generated_page';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 11 );
		add_action( 'admin_post_glp_create_pages', array( __CLASS__, 'handle_create' ) );
	}

	/**
	 * Le pagine previste, con il motivo per cui servono.
	 *
	 * @return array
	 */
	public static function definitions() {
		$definizioni = array(

			'chi-siamo' => array(
				'title'   => __( 'Chi siamo', 'geo-landing-pages' ),
				'slug'    => 'chi-siamo',
				'gruppo'  => 'fiducia',
				'perche'  => __( 'Google valuta chi c\'è dietro al sito (esperienza, competenza, autorevolezza, affidabilità). Una pagina "Chi siamo" con persone e storia reali è il segnale più diretto.', 'geo-landing-pages' ),
				'builder' => 'build_chi_siamo',
			),

			'servizi' => array(
				'title'   => __( 'I nostri servizi', 'geo-landing-pages' ),
				'slug'    => 'servizi',
				'gruppo'  => 'fiducia',
				'perche'  => __( 'Raccoglie tutti i servizi in un unico punto e distribuisce link verso le pagine città: aiuta Google a scoprirle e a capire come sono organizzate.', 'geo-landing-pages' ),
				'builder' => 'build_servizi',
			),

			'dove-operiamo' => array(
				'title'   => __( 'Dove operiamo', 'geo-landing-pages' ),
				'slug'    => 'dove-operiamo',
				'gruppo'  => 'fiducia',
				'perche'  => __( 'È l\'indice di tutte le città: senza una pagina che le elenchi, le landing locali restano isolate e vengono scansionate con fatica.', 'geo-landing-pages' ),
				'builder' => 'build_dove_operiamo',
			),

			'contatti' => array(
				'title'   => __( 'Contatti', 'geo-landing-pages' ),
				'slug'    => 'contatti',
				'gruppo'  => 'fiducia',
				'perche'  => __( 'Recapiti verificabili e coerenti con il profilo Google Business. È tra i primi elementi che un valutatore cerca per stabilire se un\'attività è reale.', 'geo-landing-pages' ),
				'builder' => 'build_contatti',
			),

			'faq' => array(
				'title'   => __( 'Domande frequenti', 'geo-landing-pages' ),
				'slug'    => 'domande-frequenti',
				'gruppo'  => 'fiducia',
				'perche'  => __( 'Intercetta le ricerche in forma di domanda e può ottenere i dati strutturati FAQ.', 'geo-landing-pages' ),
				'builder' => 'build_faq',
			),

			'recensioni' => array(
				'title'   => __( 'Recensioni', 'geo-landing-pages' ),
				'slug'    => 'recensioni',
				'gruppo'  => 'fiducia',
				'perche'  => __( 'Le opinioni dei clienti sono un segnale di affidabilità. Devono essere reali e verificabili: inventarle viola le policy di Google e la normativa sulle pratiche commerciali scorrette.', 'geo-landing-pages' ),
				'builder' => 'build_recensioni',
			),

			'note-legali' => array(
				'title'   => __( 'Note legali e dati aziendali', 'geo-landing-pages' ),
				'slug'    => 'note-legali',
				'gruppo'  => 'legali',
				'perche'  => __( 'In Italia ragione sociale, partita IVA e sede devono essere indicati sul sito. Sono anche un segnale di trasparenza per Google.', 'geo-landing-pages' ),
				'builder' => 'build_note_legali',
			),

			'privacy-policy' => array(
				'title'   => __( 'Privacy Policy', 'geo-landing-pages' ),
				'slug'    => 'privacy-policy',
				'gruppo'  => 'legali',
				'perche'  => __( 'Obbligatoria per il GDPR se raccogli dati con moduli, analytics o pixel. La sua assenza è un problema legale prima ancora che SEO.', 'geo-landing-pages' ),
				'builder' => 'build_privacy',
			),

			'cookie-policy' => array(
				'title'   => __( 'Cookie Policy', 'geo-landing-pages' ),
				'slug'    => 'cookie-policy',
				'gruppo'  => 'legali',
				'perche'  => __( 'Richiesta se il sito usa cookie non tecnici (statistiche, marketing, mappe, video).', 'geo-landing-pages' ),
				'builder' => 'build_cookie',
			),

			'termini' => array(
				'title'   => __( 'Termini e condizioni di servizio', 'geo-landing-pages' ),
				'slug'    => 'termini-e-condizioni',
				'gruppo'  => 'legali',
				'perche'  => __( 'Definisce cosa comprende il servizio, tempi, garanzie e responsabilità. Riduce le contestazioni e rafforza la percezione di serietà.', 'geo-landing-pages' ),
				'builder' => 'build_termini',
			),

			'mappa-del-sito' => array(
				'title'   => __( 'Mappa del sito', 'geo-landing-pages' ),
				'slug'    => 'mappa-del-sito',
				'gruppo'  => 'fiducia',
				'perche'  => __( 'Indice leggibile da persone e motori: utile soprattutto quando le città diventano molte.', 'geo-landing-pages' ),
				'builder' => 'build_mappa',
			),
		);

		/**
		 * Pagine generabili.
		 *
		 * @param array $definizioni Definizioni.
		 */
		return apply_filters( 'glp_generated_pages', $definizioni );
	}

	/**
	 * Pagina esistente con quello slug, se c'è.
	 *
	 * @param string $slug Slug.
	 * @return WP_Post|null
	 */
	public static function existing( $slug ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		return $page ? $page : null;
	}

	/**
	 * La pagina mostrata è stata generata dal plugin?
	 *
	 * @return bool
	 */
	public static function is_generated_page() {
		if ( ! is_page() ) {
			return false;
		}
		return (bool) get_post_meta( get_queried_object_id(), self::META_FLAG, true );
	}

	/* ------------------------------------------------------------------
	 * Mattoni comuni
	 * ------------------------------------------------------------------ */

	/**
	 * Intestazione in stile brand.
	 *
	 * @param string $titolo    Titolo.
	 * @param string $testo     Sottotitolo.
	 * @param bool   $con_cta   Mostra i pulsanti di contatto.
	 * @return string
	 */
	private static function hero( $titolo, $testo, $con_cta = true ) {
		$brand = GLP_Settings::get( 'brand', get_bloginfo( 'name' ) );
		$tel   = GLP_Settings::get( 'telefono', '' );
		$wa    = GLP_Settings::get( 'whatsapp', '' );

		// Stessa impostazione delle landing: se il tema stampa già il titolo
		// della pagina, qui non va ripetuto (due H1 sono un problema SEO).
		$modo = GLP_Settings::get( 'hero_mode', 'h1' );
		if ( 'off' === $modo ) {
			return '';
		}

		$html = '<div class="glp-hero"><div class="glp-hero__inner">';

		if ( '' !== $brand ) {
			$html .= '<div class="glp-hero__top glp-reveal">'
				. '<p class="glp-hero__eyebrow">' . esc_html( $brand ) . '</p>'
				. '</div>';
		}

		$html .= '<div class="glp-hero__main glp-hero__main--solo"><div class="glp-hero__content">';

		if ( 'h1' === $modo ) {
			$html .= '<h1 class="glp-hero__title glp-reveal">' . esc_html( $titolo ) . '</h1>';
		}

		if ( '' !== $testo ) {
			$html .= '<p class="glp-hero__text glp-reveal">' . esc_html( $testo ) . '</p>';
		}

		if ( $con_cta && ( '' !== $tel || '' !== $wa ) ) {
			$html .= '<div class="glp-hero__cta glp-reveal">';
			if ( '' !== $tel ) {
				$html .= '<a class="glp-btn glp-btn--tel" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ) . '">'
					/* translators: %s: numero di telefono. */
					. esc_html( sprintf( __( 'Chiama %s', 'geo-landing-pages' ), $tel ) ) . '</a>';
			}
			if ( '' !== $wa ) {
				$html .= '<a class="glp-btn glp-btn--ghost" rel="nofollow noopener" target="_blank" href="https://wa.me/'
					. esc_attr( preg_replace( '/[^0-9]/', '', $wa ) ) . '">'
					. esc_html__( 'Scrivici su WhatsApp', 'geo-landing-pages' ) . '</a>';
			}
			$html .= '</div>';
		}

		$html .= '</div></div>';

		$html .= '<div class="glp-hero__bottom glp-reveal">'
			. '<span>' . esc_html( $titolo ) . '</span>'
			. '<div class="glp-hero__bottom-line" aria-hidden="true"></div>'
			. '<span>' . esc_html( $brand ) . '</span>'
			. '</div>';

		return $html . '</div><div class="glp-hero__corner" aria-hidden="true"></div></div>';
	}

	/**
	 * Apre una sezione.
	 *
	 * @param string $titolo Titolo.
	 * @return string
	 */
	private static function sezione( $titolo, $etichetta = '' ) {
		$html = '<section class="glp-section glp-reveal">';
		if ( '' !== $etichetta ) {
			$html .= '<p class="glp-section__label">' . esc_html( $etichetta ) . '</p>';
		}
		return $html . '<h2 class="glp-section__title">' . esc_html( $titolo ) . '</h2>';
	}

	/**
	 * Segnaposto da completare, ben visibile in bozza.
	 *
	 * @param string $cosa Testo guida.
	 * @return string
	 */
	private static function completare( $cosa ) {
		/* translators: %s: indicazione su cosa scrivere. */
		return '<p>[DA COMPLETARE: ' . esc_html( $cosa ) . ']</p>';
	}

	/**
	 * Elenco puntato con spunte.
	 *
	 * @param array $voci Voci.
	 * @return string
	 */
	private static function elenco( $voci ) {
		$html = '<ul class="glp-list glp-list--check">';
		foreach ( $voci as $voce ) {
			$html .= '<li>' . $voce . '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Servizi distinti presenti nel sito: titolo => elenco di città.
	 *
	 * @return array
	 */
	private static function servizi_del_sito() {
		$servizi = array();
		foreach ( GLP_Post_Types::cities( 500 ) as $citta ) {
			foreach ( GLP_Post_Types::services_of( $citta->ID ) as $servizio ) {
				$nome = $servizio->post_title;
				if ( ! isset( $servizi[ $nome ] ) ) {
					$servizi[ $nome ] = array();
				}
				$servizi[ $nome ][] = array(
					'citta' => $citta->post_title,
					'url'   => get_permalink( $servizio ),
				);
			}
		}
		ksort( $servizi );
		return $servizi;
	}

	/* ------------------------------------------------------------------
	 * Contenuti delle pagine
	 * ------------------------------------------------------------------ */

	/** Chi siamo. */
	public static function build_chi_siamo() {
		$brand = GLP_Settings::get( 'brand', get_bloginfo( 'name' ) );

		$html  = self::hero(
			__( 'Chi siamo', 'geo-landing-pages' ),
			/* translators: %s: nome dell'attività. */
			sprintf( __( 'Le persone e il lavoro dietro a %s.', 'geo-landing-pages' ), $brand )
		);

		$html .= self::sezione( __( 'La nostra storia', 'geo-landing-pages' ) );
		$html .= self::completare( __( 'quando è nata l\'attività, da chi, perché. Bastano due paragrafi, ma devono essere veri e verificabili: è la parte che Google usa per capire chi siete', 'geo-landing-pages' ) );
		$html .= '</section>';

		$html .= self::sezione( __( 'Come lavoriamo', 'geo-landing-pages' ) );
		$html .= self::elenco( array(
			esc_html__( '[DA COMPLETARE: il metodo di lavoro, in una frase]', 'geo-landing-pages' ),
			esc_html__( '[DA COMPLETARE: attrezzature o competenze che vi distinguono]', 'geo-landing-pages' ),
			esc_html__( '[DA COMPLETARE: garanzie che offrite]', 'geo-landing-pages' ),
		) );
		$html .= '</section>';

		$html .= self::sezione( __( 'Il team', 'geo-landing-pages' ) );
		$html .= self::completare( __( 'nome, ruolo, qualifiche e foto di chi lavora con voi. Nomi e volti reali valgono più di qualsiasi testo promozionale', 'geo-landing-pages' ) );
		$html .= '</section>';

		$html .= self::sezione( __( 'Dove ci trovi', 'geo-landing-pages' ) );
		$html .= '[glp_citta colonne="4"]';
		$html .= '</section>';

		return $html;
	}

	/** I nostri servizi. */
	public static function build_servizi() {
		$servizi = self::servizi_del_sito();

		$html  = self::hero(
			__( 'I nostri servizi', 'geo-landing-pages' ),
			__( 'Cosa facciamo e in quali città.', 'geo-landing-pages' )
		);

		if ( empty( $servizi ) ) {
			$html .= self::sezione( __( 'Servizi', 'geo-landing-pages' ) );
			$html .= self::completare( __( 'non ci sono ancora pagine servizio pubblicate. Creale da "Landing locali" e poi rigenera questa pagina', 'geo-landing-pages' ) );
			return $html . '</section>';
		}

		foreach ( $servizi as $nome => $citta ) {
			$html .= self::sezione( $nome );
			$html .= self::completare( __( 'due righe su questo servizio, valide ovunque', 'geo-landing-pages' ) );
			$html .= '<ul class="glp-tags glp-tags--links">';
			foreach ( $citta as $riga ) {
				$html .= '<li><a href="' . esc_url( $riga['url'] ) . '">' . esc_html( $riga['citta'] ) . '</a></li>';
			}
			$html .= '</ul></section>';
		}

		return $html;
	}

	/** Dove operiamo. */
	public static function build_dove_operiamo() {
		$html  = self::hero(
			__( 'Dove operiamo', 'geo-landing-pages' ),
			__( 'Tutte le città e le zone che serviamo.', 'geo-landing-pages' )
		);
		$html .= self::sezione( __( 'Le nostre zone', 'geo-landing-pages' ) );
		$html .= '[glp_citta colonne="4"]';
		$html .= '</section>';
		return $html;
	}

	/** Contatti. */
	public static function build_contatti() {
		$brand = GLP_Settings::get( 'brand', get_bloginfo( 'name' ) );
		$tel   = GLP_Settings::get( 'telefono', '' );
		$wa    = GLP_Settings::get( 'whatsapp', '' );
		$email = GLP_Settings::get( 'email', '' );

		$html  = self::hero( __( 'Contatti', 'geo-landing-pages' ), __( 'Scrivici o chiamaci: rispondiamo negli orari di apertura.', 'geo-landing-pages' ) );

		$html .= self::sezione( __( 'Come raggiungerci', 'geo-landing-pages' ) );
		$voci  = array();
		if ( '' !== $brand ) {
			$voci[] = '<strong>' . esc_html( $brand ) . '</strong>';
		}
		if ( '' !== $tel ) {
			$voci[] = esc_html__( 'Telefono:', 'geo-landing-pages' ) . ' <a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ) . '">' . esc_html( $tel ) . '</a>';
		}
		if ( '' !== $wa ) {
			$voci[] = esc_html__( 'WhatsApp:', 'geo-landing-pages' ) . ' <a href="https://wa.me/' . esc_attr( preg_replace( '/[^0-9]/', '', $wa ) ) . '" rel="nofollow noopener" target="_blank">' . esc_html__( 'scrivici', 'geo-landing-pages' ) . '</a>';
		}
		if ( '' !== $email ) {
			$voci[] = esc_html__( 'Email:', 'geo-landing-pages' ) . ' <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
		}
		$voci[] = esc_html__( '[DA COMPLETARE: indirizzo della sede e orari di apertura]', 'geo-landing-pages' );
		$html  .= self::elenco( $voci );
		$html  .= '</section>';

		$html .= self::sezione( __( 'Scrivici', 'geo-landing-pages' ) );
		$html .= self::completare( __( 'inserisci qui lo shortcode del tuo modulo di contatto, per esempio [contact-form-7 id="123"]', 'geo-landing-pages' ) );
		$html .= '</section>';

		$html .= self::sezione( __( 'Le nostre sedi e zone', 'geo-landing-pages' ) );
		$html .= '[glp_citta colonne="4"]';
		$html .= '</section>';

		return $html;
	}

	/** Domande frequenti. */
	public static function build_faq() {
		$html = self::hero( __( 'Domande frequenti', 'geo-landing-pages' ), __( 'Le risposte alle domande che ci fanno più spesso.', 'geo-landing-pages' ) );

		$html .= self::sezione( __( 'Domande e risposte', 'geo-landing-pages' ) );
		$html .= '<div class="glp-faq">';
		foreach ( GLP_Questionnaire::faq_suggestions_site() as $domanda ) {
			$html   .= '<details class="glp-faq__item"><summary class="glp-faq__q">' . esc_html( $domanda ) . '</summary>'
				. '<div class="glp-faq__a">' . self::completare( __( 'la risposta, con parole tue', 'geo-landing-pages' ) ) . '</div></details>';
		}
		$html .= '</div></section>';

		return $html;
	}

	/** Recensioni. */
	public static function build_recensioni() {
		$html  = self::hero( __( 'Recensioni', 'geo-landing-pages' ), __( 'Cosa dicono i clienti che ci hanno scelto.', 'geo-landing-pages' ) );
		$html .= self::sezione( __( 'Le opinioni dei clienti', 'geo-landing-pages' ) );
		$html .= self::completare( __( 'incolla qui le recensioni reali, con nome, zona e data, oppure lo shortcode del widget che mostra le recensioni Google. Non inserire recensioni inventate: è vietato dalle policy di Google e dalla legge sulle pratiche commerciali scorrette', 'geo-landing-pages' ) );
		$html .= '</section>';
		return $html;
	}

	/** Note legali e dati aziendali. */
	public static function build_note_legali() {
		$brand = GLP_Settings::get( 'brand', get_bloginfo( 'name' ) );
		$piva  = GLP_Settings::get( 'partita_iva', '' );
		$email = GLP_Settings::get( 'email', '' );

		$html  = self::hero( __( 'Note legali e dati aziendali', 'geo-landing-pages' ), '', false );
		$html .= self::sezione( __( 'Dati dell\'attività', 'geo-landing-pages' ) );

		$voci = array();
		$voci[] = esc_html__( 'Ragione sociale:', 'geo-landing-pages' ) . ' ' . ( '' !== $brand ? esc_html( $brand ) : esc_html__( '[DA COMPLETARE]', 'geo-landing-pages' ) );
		$voci[] = esc_html__( 'Partita IVA:', 'geo-landing-pages' ) . ' ' . ( '' !== $piva ? esc_html( $piva ) : esc_html__( '[DA COMPLETARE]', 'geo-landing-pages' ) );
		$voci[] = esc_html__( 'Sede legale: [DA COMPLETARE]', 'geo-landing-pages' );
		$voci[] = esc_html__( 'Numero REA: [DA COMPLETARE]', 'geo-landing-pages' );
		$voci[] = esc_html__( 'PEC: [DA COMPLETARE]', 'geo-landing-pages' );
		if ( '' !== $email ) {
			$voci[] = esc_html__( 'Email:', 'geo-landing-pages' ) . ' ' . esc_html( $email );
		}
		$html .= self::elenco( $voci );
		$html .= '</section>';

		$html .= self::sezione( __( 'Proprietà dei contenuti', 'geo-landing-pages' ) );
		$html .= '<p>' . esc_html__( 'Testi, immagini e marchi presenti su questo sito appartengono ai rispettivi titolari e non possono essere riprodotti senza autorizzazione.', 'geo-landing-pages' ) . '</p>';
		$html .= '</section>';

		return $html;
	}

	/** Privacy policy (traccia). */
	public static function build_privacy() {
		$html  = self::hero( __( 'Privacy Policy', 'geo-landing-pages' ), '', false );
		$html .= '<p><em>' . esc_html__( 'Questa è una traccia, non un testo conforme. Va completata con i dati reali del trattamento e fatta verificare da chi se ne occupa per voi.', 'geo-landing-pages' ) . '</em></p>';

		$capitoli = array(
			__( 'Titolare del trattamento', 'geo-landing-pages' )      => __( 'ragione sociale, sede, contatti del titolare e, se nominato, del responsabile della protezione dei dati', 'geo-landing-pages' ),
			__( 'Dati raccolti', 'geo-landing-pages' )                 => __( 'quali dati raccogliete e come: moduli di contatto, telefonate, WhatsApp, statistiche, pixel pubblicitari', 'geo-landing-pages' ),
			__( 'Finalità e base giuridica', 'geo-landing-pages' )     => __( 'perché li trattate e su quale base: consenso, contratto, obbligo di legge, legittimo interesse', 'geo-landing-pages' ),
			__( 'Periodo di conservazione', 'geo-landing-pages' )      => __( 'per quanto tempo conservate ciascun tipo di dato', 'geo-landing-pages' ),
			__( 'Destinatari', 'geo-landing-pages' )                   => __( 'a chi vengono comunicati: fornitore di hosting, servizi di statistica, gestionale, eventuali trasferimenti fuori dall\'Unione Europea', 'geo-landing-pages' ),
			__( 'Diritti dell\'interessato', 'geo-landing-pages' )     => __( 'accesso, rettifica, cancellazione, limitazione, opposizione, portabilità, reclamo al Garante, e come esercitarli', 'geo-landing-pages' ),
		);

		foreach ( $capitoli as $titolo => $cosa ) {
			$html .= self::sezione( $titolo ) . self::completare( $cosa ) . '</section>';
		}

		return $html;
	}

	/** Cookie policy (traccia). */
	public static function build_cookie() {
		$html  = self::hero( __( 'Cookie Policy', 'geo-landing-pages' ), '', false );
		$html .= '<p><em>' . esc_html__( 'Traccia da completare con l\'elenco reale dei cookie installati dal sito.', 'geo-landing-pages' ) . '</em></p>';

		$capitoli = array(
			__( 'Cosa sono i cookie', 'geo-landing-pages' )        => __( 'spiegazione breve e comprensibile', 'geo-landing-pages' ),
			__( 'Cookie tecnici', 'geo-landing-pages' )            => __( 'quelli necessari al funzionamento, che non richiedono consenso', 'geo-landing-pages' ),
			__( 'Cookie di statistica', 'geo-landing-pages' )      => __( 'per esempio Google Analytics: nome, finalità, durata, se anonimizzati', 'geo-landing-pages' ),
			__( 'Cookie di marketing', 'geo-landing-pages' )       => __( 'per esempio pixel pubblicitari: nome, finalità, durata', 'geo-landing-pages' ),
			__( 'Servizi di terze parti', 'geo-landing-pages' )    => __( 'mappe, video, chat: ciascuno con il link alla propria informativa', 'geo-landing-pages' ),
			__( 'Come gestire il consenso', 'geo-landing-pages' )  => __( 'come revocare o modificare le scelte, e come intervenire dal browser', 'geo-landing-pages' ),
		);

		foreach ( $capitoli as $titolo => $cosa ) {
			$html .= self::sezione( $titolo ) . self::completare( $cosa ) . '</section>';
		}

		return $html;
	}

	/** Termini e condizioni (traccia). */
	public static function build_termini() {
		$html = self::hero( __( 'Termini e condizioni di servizio', 'geo-landing-pages' ), '', false );

		$capitoli = array(
			__( 'Oggetto del servizio', 'geo-landing-pages' )     => __( 'cosa comprende esattamente il servizio e cosa no', 'geo-landing-pages' ),
			__( 'Preventivi e prezzi', 'geo-landing-pages' )      => __( 'come si formano, quanto valgono, cosa può farli variare', 'geo-landing-pages' ),
			__( 'Tempi di intervento', 'geo-landing-pages' )      => __( 'tempi dichiarati e cosa può allungarli', 'geo-landing-pages' ),
			__( 'Pagamenti', 'geo-landing-pages' )                => __( 'metodi accettati, tempi, fatturazione', 'geo-landing-pages' ),
			__( 'Garanzia', 'geo-landing-pages' )                 => __( 'durata, cosa copre, come attivarla', 'geo-landing-pages' ),
			__( 'Diritto di recesso', 'geo-landing-pages' )       => __( 'condizioni per i consumatori, con i termini di legge', 'geo-landing-pages' ),
			__( 'Reclami e foro competente', 'geo-landing-pages' ) => __( 'come presentare un reclamo e quale foro si applica', 'geo-landing-pages' ),
		);

		foreach ( $capitoli as $titolo => $cosa ) {
			$html .= self::sezione( $titolo ) . self::completare( $cosa ) . '</section>';
		}

		return $html;
	}

	/** Mappa del sito. */
	public static function build_mappa() {
		$html  = self::hero( __( 'Mappa del sito', 'geo-landing-pages' ), __( 'Tutte le pagine, in un colpo d\'occhio.', 'geo-landing-pages' ), false );

		$html .= self::sezione( __( 'Città', 'geo-landing-pages' ) );
		$html .= '[glp_citta colonne="4"]';
		$html .= '</section>';

		$servizi = self::servizi_del_sito();
		if ( ! empty( $servizi ) ) {
			$html .= self::sezione( __( 'Servizi per città', 'geo-landing-pages' ) );
			$html .= '<ul class="glp-list">';
			foreach ( $servizi as $nome => $citta ) {
				$link = array();
				foreach ( $citta as $riga ) {
					$link[] = '<a href="' . esc_url( $riga['url'] ) . '">' . esc_html( $riga['citta'] ) . '</a>';
				}
				$html .= '<li><strong>' . esc_html( $nome ) . '</strong>: ' . implode( ', ', $link ) . '</li>';
			}
			$html .= '</ul></section>';
		}

		return $html;
	}

	/* ------------------------------------------------------------------
	 * Amministrazione
	 * ------------------------------------------------------------------ */

	/** Voce di menu. */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . GLP_POST_TYPE,
			__( 'Pagine del sito', 'geo-landing-pages' ),
			__( 'Pagine del sito', 'geo-landing-pages' ),
			'publish_pages',
			'glp-pages',
			array( __CLASS__, 'render' )
		);
	}

	/** Schermata. */
	public static function render() {
		if ( ! current_user_can( 'publish_pages' ) ) {
			return;
		}
		$definizioni = self::definitions();
		?>
		<div class="wrap glp-pages">
			<h1><?php esc_html_e( 'Pagine del sito', 'geo-landing-pages' ); ?></h1>
			<p><?php esc_html_e( 'Le pagine che un sito di servizi locali dovrebbe avere. Vengono create in bozza, con lo stile delle landing e i dati aziendali già inseriti dove disponibili: i testi segnati [DA COMPLETARE] vanno scritti da te.', 'geo-landing-pages' ); ?></p>

			<?php if ( isset( $_GET['glp_pages'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success"><p>
					<?php
					printf(
						/* translators: %d: numero di pagine create. */
						esc_html__( 'Pagine create in bozza: %d.', 'geo-landing-pages' ),
						(int) $_GET['glp_pages'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					);
					?>
				</p></div>
			<?php endif; ?>

			<div class="notice notice-warning inline"><p>
				<strong><?php esc_html_e( 'Pagine legali:', 'geo-landing-pages' ); ?></strong>
				<?php esc_html_e( 'privacy, cookie e termini vengono generate come traccia, non come testo conforme. Servono i dati reali del trattamento e una verifica da parte di chi se ne occupa: pubblicare una privacy policy incompleta è un problema legale, non un dettaglio.', 'geo-landing-pages' ); ?>
			</p></div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="glp_create_pages" />
				<?php wp_nonce_field( 'glp_create_pages' ); ?>

				<table class="widefat striped glp-pages__table">
					<thead>
						<tr>
							<td class="check-column"></td>
							<th><?php esc_html_e( 'Pagina', 'geo-landing-pages' ); ?></th>
							<th><?php esc_html_e( 'Stato', 'geo-landing-pages' ); ?></th>
							<th><?php esc_html_e( 'Perché serve', 'geo-landing-pages' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $definizioni as $key => $def ) : ?>
							<?php $esistente = self::existing( $def['slug'] ); ?>
							<tr>
								<th class="check-column">
									<input type="checkbox" name="pagine[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( ! $esistente ); ?> <?php disabled( (bool) $esistente ); ?> />
								</th>
								<td>
									<strong><?php echo esc_html( $def['title'] ); ?></strong><br />
									<code>/<?php echo esc_html( $def['slug'] ); ?>/</code>
									<?php if ( 'legali' === $def['gruppo'] ) : ?>
										<span class="glp-badge-legal"><?php esc_html_e( 'legale', 'geo-landing-pages' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $esistente ) : ?>
										<span class="glp-badge is-ok"><?php echo esc_html( 'publish' === $esistente->post_status ? __( 'pubblicata', 'geo-landing-pages' ) : __( 'bozza', 'geo-landing-pages' ) ); ?></span>
										<a href="<?php echo esc_url( get_edit_post_link( $esistente->ID ) ); ?>"><?php esc_html_e( 'modifica', 'geo-landing-pages' ); ?></a>
									<?php else : ?>
										<span class="glp-badge is-low"><?php esc_html_e( 'mancante', 'geo-landing-pages' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="glp-pages__why"><?php echo esc_html( $def['perche'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Crea le pagine selezionate (in bozza)', 'geo-landing-pages' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** Crea le pagine richieste. */
	public static function handle_create() {
		if ( ! current_user_can( 'publish_pages' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'geo-landing-pages' ) );
		}
		check_admin_referer( 'glp_create_pages' );

		$richieste = isset( $_POST['pagine'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['pagine'] ) ) : array();
		$definizioni = self::definitions();
		$create      = 0;

		foreach ( $richieste as $key ) {
			if ( ! isset( $definizioni[ $key ] ) ) {
				continue;
			}
			$def = $definizioni[ $key ];

			// Non si sovrascrive mai una pagina che esiste già.
			if ( self::existing( $def['slug'] ) ) {
				continue;
			}

			$contenuto = call_user_func( array( __CLASS__, $def['builder'] ) );

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_title'   => $def['title'],
					'post_name'    => $def['slug'],
					'post_status'  => 'draft',
					'post_content' => $contenuto,
				)
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, self::META_FLAG, $key );
			$create++;
		}

		wp_safe_redirect( add_query_arg( 'glp_pages', $create, admin_url( 'edit.php?post_type=' . GLP_POST_TYPE . '&page=glp-pages' ) ) );
		exit;
	}
}
