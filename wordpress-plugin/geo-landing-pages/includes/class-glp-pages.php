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
	const META_CITY_PAGE = '_glp_city_page';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 11 );
		add_action( 'admin_post_glp_create_pages', array( __CLASS__, 'handle_create' ) );
		add_action( 'admin_post_glp_create_city_pages', array( __CLASS__, 'handle_create_city' ) );
	}

	/**
	 * Pagine che ogni città deve avere.
	 *
	 * Vengono create come figlie della pagina città, quindi rispondono
	 * su /citta/slug/ ed ereditano i dati della città.
	 *
	 * @return array
	 */
	public static function city_definitions() {
		$definizioni = array(

			'chi-siamo' => array(
				/* translators: %s: nome della città. */
				'title'   => __( 'Chi siamo a %s', 'geo-landing-pages' ),
				'slug'    => 'chi-siamo',
				'perche'  => __( 'Chi risponde del servizio in questa città: è il segnale di affidabilità che Google cerca (E-E-A-T).', 'geo-landing-pages' ),
				'builder' => 'city_chi_siamo',
			),

			'servizi' => array(
				/* translators: %s: nome della città. */
				'title'   => __( 'Servizi a %s', 'geo-landing-pages' ),
				'slug'    => 'servizi',
				'perche'  => __( 'Indice dei servizi attivi in città: distribuisce link verso le singole pagine servizio.', 'geo-landing-pages' ),
				'builder' => 'city_servizi',
			),

			'contatti' => array(
				/* translators: %s: nome della città. */
				'title'   => __( 'Contatti a %s', 'geo-landing-pages' ),
				'slug'    => 'contatti',
				'perche'  => __( 'Recapiti, orari e mappa della zona: vanno coerenti con il profilo Google Business locale.', 'geo-landing-pages' ),
				'builder' => 'city_contatti',
			),

			'zone' => array(
				/* translators: %s: nome della città. */
				'title'   => __( 'Zone servite a %s', 'geo-landing-pages' ),
				'slug'    => 'zone-servite',
				'perche'  => __( 'Quartieri e comuni limitrofi: intercetta le ricerche iper-locali.', 'geo-landing-pages' ),
				'builder' => 'city_zone',
			),

			'prezzi' => array(
				/* translators: %s: nome della città. */
				'title'   => __( 'Prezzi a %s', 'geo-landing-pages' ),
				'slug'    => 'prezzi',
				'perche'  => __( 'Il costo è la prima domanda di ogni ricerca locale: rispondere in pagina trattiene chi cerca.', 'geo-landing-pages' ),
				'builder' => 'city_prezzi',
			),

			'recensioni' => array(
				/* translators: %s: nome della città. */
				'title'   => __( 'Recensioni a %s', 'geo-landing-pages' ),
				'slug'    => 'recensioni',
				'perche'  => __( 'Opinioni di clienti di quella zona. Devono essere reali e verificabili.', 'geo-landing-pages' ),
				'builder' => 'city_recensioni',
			),

			'faq' => array(
				/* translators: %s: nome della città. */
				'title'   => __( 'Domande frequenti a %s', 'geo-landing-pages' ),
				'slug'    => 'domande-frequenti',
				'perche'  => __( 'Le domande del riquadro "Le persone chiedono anche", con i dati strutturati FAQ.', 'geo-landing-pages' ),
				'builder' => 'city_faq',
			),
		);

		/**
		 * Pagine generabili per ogni città.
		 *
		 * @param array $definizioni Definizioni.
		 */
		return apply_filters( 'glp_city_pages', $definizioni );
	}

	/**
	 * Pagine legali: una sola per tutto il sito, non una per città.
	 *
	 * Duplicare una privacy policy per ogni città non ha senso né
	 * legale né SEO: il trattamento dei dati è uno solo.
	 *
	 * @return array
	 */
	public static function definitions() {
		$definizioni = array(

			'note-legali' => array(
				'title'   => __( 'Note legali e dati aziendali', 'geo-landing-pages' ),
				'slug'    => 'note-legali',
				'gruppo'  => 'legali',
				'perche'  => __( 'In Italia ragione sociale, partita IVA e sede devono essere indicati sul sito.', 'geo-landing-pages' ),
				'builder' => 'build_note_legali',
			),

			'privacy-policy' => array(
				'title'   => __( 'Privacy Policy', 'geo-landing-pages' ),
				'slug'    => 'privacy-policy',
				'gruppo'  => 'legali',
				'perche'  => __( 'Obbligatoria per il GDPR se raccogli dati con moduli, analytics o pixel.', 'geo-landing-pages' ),
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
				'perche'  => __( 'Cosa comprende il servizio, tempi, garanzie e responsabilità.', 'geo-landing-pages' ),
				'builder' => 'build_termini',
			),

			'mappa-del-sito' => array(
				'title'   => __( 'Mappa del sito', 'geo-landing-pages' ),
				'slug'    => 'mappa-del-sito',
				'gruppo'  => 'fiducia',
				'perche'  => __( 'Indice leggibile da persone e motori: utile quando le città diventano molte.', 'geo-landing-pages' ),
				'builder' => 'build_mappa',
			),
		);

		/**
		 * Pagine generabili a livello di sito.
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

	/* ------------------------------------------------------------------
	 * Contenuti delle pagine di città
	 *
	 * Il testo introduttivo è modificabile; i blocchi di dati sono
	 * shortcode con da="citta", così restano allineati alla scheda
	 * della città anche quando la aggiorni.
	 * ------------------------------------------------------------------ */

	/**
	 * Introduzione da completare.
	 *
	 * @param string $cosa Indicazione su cosa scrivere.
	 * @return string
	 */
	private static function intro( $cosa ) {
		return '<p class="glp-intro">[DA COMPLETARE: ' . esc_html( $cosa ) . ']</p>';
	}

	/** Chi siamo in città. */
	public static function city_chi_siamo( $citta ) {
		/* translators: %s: nome della città. */
		$cosa = sprintf( __( 'chi siete e da quanto lavorate a %s, con riferimenti reali alla zona', 'geo-landing-pages' ), $citta->post_title );

		return self::intro( $cosa )
			. '[glp_sezione tipo="perche" da="citta"]'
			. '[glp_sezione tipo="team" da="citta"]'
			. '[glp_sezione tipo="zone" da="citta"]'
			. '[glp_sezione tipo="cta" da="citta"]';
	}

	/** Indice dei servizi in città. */
	public static function city_servizi( $citta ) {
		/* translators: %s: nome della città. */
		$cosa = sprintf( __( 'una frase su cosa offrite a %s', 'geo-landing-pages' ), $citta->post_title );

		return self::intro( $cosa )
			. '[glp_sezione tipo="servizi" da="citta"]'
			. '[glp_sezione tipo="incluso" da="citta"]'
			. '[glp_sezione tipo="cta" da="citta"]';
	}

	/** Contatti in città. */
	public static function city_contatti( $citta ) {
		/* translators: %s: nome della città. */
		$cosa = sprintf( __( 'come preferite essere contattati a %s e in quanto tempo rispondete', 'geo-landing-pages' ), $citta->post_title );

		return self::intro( $cosa )
			. '[glp_sezione tipo="dove" da="citta"]'
			. '[glp_sezione tipo="orari" da="citta"]'
			. '[glp_sezione tipo="cta" da="citta"]';
	}

	/** Zone servite. */
	public static function city_zone( $citta ) {
		/* translators: %s: nome della città. */
		$cosa = sprintf( __( 'fino a dove arrivate da %s e con quali tempi', 'geo-landing-pages' ), $citta->post_title );

		return self::intro( $cosa )
			. '[glp_sezione tipo="zone" da="citta"]'
			. '[glp_sezione tipo="dove" da="citta"]'
			. '[glp_sezione tipo="cta" da="citta"]';
	}

	/** Prezzi. */
	public static function city_prezzi( $citta ) {
		/* translators: %s: nome della città. */
		$cosa = sprintf( __( 'come si forma il prezzo a %s e cosa lo fa variare', 'geo-landing-pages' ), $citta->post_title );

		return self::intro( $cosa )
			. '[glp_sezione tipo="prezzi" da="citta"]'
			. '[glp_sezione tipo="incluso" da="citta"]'
			. '[glp_sezione tipo="processo" da="citta"]'
			. '[glp_sezione tipo="cta" da="citta"]';
	}

	/** Recensioni locali. */
	public static function city_recensioni( $citta ) {
		/* translators: %s: nome della città. */
		$cosa = sprintf( __( 'una riga sulle recensioni raccolte a %s. Inserisci solo recensioni reali: inventarle viola le policy di Google e la legge sulle pratiche commerciali scorrette', 'geo-landing-pages' ), $citta->post_title );

		return self::intro( $cosa )
			. '[glp_sezione tipo="recensioni" da="citta"]'
			. '[glp_sezione tipo="cta" da="citta"]';
	}

	/** Domande frequenti locali. */
	public static function city_faq( $citta ) {
		/* translators: %s: nome della città. */
		$cosa = sprintf( __( 'una riga di apertura sulle domande che vi fanno a %s', 'geo-landing-pages' ), $citta->post_title );

		return self::intro( $cosa )
			. '[glp_sezione tipo="faq" da="citta"]'
			. '[glp_sezione tipo="cta" da="citta"]';
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

	/**
	 * Pagine di città già esistenti: chiave => numero di città che ce l'hanno.
	 *
	 * @param WP_Post[] $citta Città.
	 * @return array
	 */
	private static function city_pages_count( $citta ) {
		$conteggio = array();
		foreach ( array_keys( self::city_definitions() ) as $chiave ) {
			$conteggio[ $chiave ] = 0;
		}

		foreach ( $citta as $c ) {
			foreach ( get_children( array( 'post_parent' => $c->ID, 'post_type' => GLP_POST_TYPE, 'post_status' => 'any' ) ) as $figlio ) {
				$tipo = get_post_meta( $figlio->ID, self::META_CITY_PAGE, true );
				if ( $tipo && isset( $conteggio[ $tipo ] ) ) {
					$conteggio[ $tipo ]++;
				}
			}
		}

		return $conteggio;
	}

	/** Schermata. */
	public static function render() {
		if ( ! current_user_can( 'publish_pages' ) ) {
			return;
		}

		$citta       = GLP_Post_Types::cities( 500 );
		$per_citta   = self::city_definitions();
		$conteggio   = self::city_pages_count( $citta );
		$definizioni = self::definitions();
		?>
		<div class="wrap glp-pages">
			<h1><?php esc_html_e( 'Pagine del sito', 'geo-landing-pages' ); ?></h1>

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

			<h2><?php esc_html_e( 'Pagine di ogni città', 'geo-landing-pages' ); ?></h2>
			<p>
				<?php esc_html_e( 'Vengono create dentro la città, quindi rispondono su indirizzi come', 'geo-landing-pages' ); ?>
				<code><?php echo esc_html( home_url( '/trapani/contatti/' ) ); ?></code>
				<?php esc_html_e( 'ed ereditano i dati della scheda città: recapiti, orari, zone e recensioni restano allineati anche quando li aggiorni.', 'geo-landing-pages' ); ?>
			</p>

			<?php if ( empty( $citta ) ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php esc_html_e( 'Non ci sono ancora città pubblicate. Creane una da "Aggiungi città o servizio", poi torna qui.', 'geo-landing-pages' ); ?>
				</p></div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="glp_create_city_pages" />
					<?php wp_nonce_field( 'glp_create_city_pages' ); ?>

					<table class="widefat striped glp-pages__table">
						<thead>
							<tr>
								<td class="check-column"></td>
								<th><?php esc_html_e( 'Pagina', 'geo-landing-pages' ); ?></th>
								<th><?php esc_html_e( 'Già presente in', 'geo-landing-pages' ); ?></th>
								<th><?php esc_html_e( 'Perché serve', 'geo-landing-pages' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $per_citta as $chiave => $def ) : ?>
								<tr>
									<th class="check-column">
										<input type="checkbox" name="tipi[]" value="<?php echo esc_attr( $chiave ); ?>" checked />
									</th>
									<td>
										<strong><?php echo esc_html( sprintf( $def['title'], __( '{città}', 'geo-landing-pages' ) ) ); ?></strong><br />
										<code>/{città}/<?php echo esc_html( $def['slug'] ); ?>/</code>
									</td>
									<td>
										<?php
										printf(
											/* translators: 1: città che hanno la pagina, 2: totale città. */
											esc_html__( '%1$d di %2$d città', 'geo-landing-pages' ),
											(int) $conteggio[ $chiave ],
											count( $citta )
										);
										?>
									</td>
									<td class="glp-pages__why"><?php echo esc_html( $def['perche'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Per quali città', 'geo-landing-pages' ); ?></th>
							<td>
								<select name="citta[]" multiple size="<?php echo esc_attr( min( 10, max( 3, count( $citta ) ) ) ); ?>" style="min-width:260px">
									<?php foreach ( $citta as $c ) : ?>
										<option value="<?php echo esc_attr( $c->ID ); ?>" selected><?php echo esc_html( $c->post_title ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Sono selezionate tutte: tieni premuto Ctrl (o Cmd) per scegliere solo alcune città. Le pagine già esistenti non vengono toccate né duplicate.', 'geo-landing-pages' ); ?></p>
							</td>
						</tr>
					</table>

					<div class="notice notice-warning inline"><p>
						<?php esc_html_e( 'Queste pagine nascono quasi identiche fra città: sono i tuoi dati locali a renderle diverse. Finché il punteggio di qualità resta sotto la soglia restano noindex, ed è giusto così: decine di "Chi siamo" uguali tranne il nome del comune sono esattamente ciò che Google declassa.', 'geo-landing-pages' ); ?>
					</p></div>

					<?php submit_button( __( 'Crea le pagine di città (in bozza)', 'geo-landing-pages' ) ); ?>
				</form>
			<?php endif; ?>

			<hr />

			<h2><?php esc_html_e( 'Pagine del sito (una sola per tutto il sito)', 'geo-landing-pages' ); ?></h2>
			<p><?php esc_html_e( 'Queste non vanno duplicate per città: il trattamento dei dati e le condizioni di servizio sono unici.', 'geo-landing-pages' ); ?></p>

			<div class="notice notice-warning inline"><p>
				<strong><?php esc_html_e( 'Pagine legali:', 'geo-landing-pages' ); ?></strong>
				<?php esc_html_e( 'vengono generate come traccia, non come testo conforme. Servono i dati reali del trattamento e una verifica da parte di chi se ne occupa.', 'geo-landing-pages' ); ?>
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

				<?php submit_button( __( 'Crea le pagine del sito (in bozza)', 'geo-landing-pages' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	/** Crea le pagine dentro le città selezionate. */
	public static function handle_create_city() {
		if ( ! current_user_can( 'publish_pages' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'geo-landing-pages' ) );
		}
		check_admin_referer( 'glp_create_city_pages' );

		$tipi  = isset( $_POST['tipi'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['tipi'] ) ) : array();
		$scelte = isset( $_POST['citta'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['citta'] ) ) : array();

		$definizioni = self::city_definitions();
		$create      = 0;

		foreach ( $scelte as $citta_id ) {
			$citta = get_post( $citta_id );
			if ( ! $citta || GLP_POST_TYPE !== $citta->post_type || 0 !== (int) $citta->post_parent ) {
				continue;
			}

			foreach ( $tipi as $chiave ) {
				if ( ! isset( $definizioni[ $chiave ] ) ) {
					continue;
				}
				$def = $definizioni[ $chiave ];

				// Mai sovrascrivere una pagina che esiste già sotto quella città.
				if ( get_page_by_path( $citta->post_name . '/' . $def['slug'], OBJECT, GLP_POST_TYPE ) ) {
					continue;
				}

				$post_id = wp_insert_post(
					array(
						'post_type'    => GLP_POST_TYPE,
						'post_parent'  => $citta->ID,
						'post_title'   => sprintf( $def['title'], $citta->post_title ),
						'post_name'    => $def['slug'],
						'post_status'  => 'draft',
						'post_content' => call_user_func( array( __CLASS__, $def['builder'] ), $citta ),
					)
				);

				if ( is_wp_error( $post_id ) || ! $post_id ) {
					continue;
				}

				update_post_meta( $post_id, self::META_CITY_PAGE, $chiave );
				$create++;
			}
		}

		GLP_Post_Types::schedule_flush();

		wp_safe_redirect( add_query_arg( 'glp_pages', $create, admin_url( 'edit.php?post_type=' . GLP_POST_TYPE . '&page=glp-pages' ) ) );
		exit;
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
