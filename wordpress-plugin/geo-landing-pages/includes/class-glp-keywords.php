<?php
/**
 * Controllo delle parole chiave.
 *
 * Verifica dove la keyword compare davvero nella pagina e segnala se
 * un'altra pagina punta alla stessa ricerca (cannibalizzazione).
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Keywords {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 13 );
	}

	/**
	 * Normalizza un testo per il confronto.
	 *
	 * @param string $text Testo.
	 * @return string
	 */
	public static function normalize( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$text = str_replace( array( '’', '‘', '“', '”' ), array( "'", "'", '"', '"' ), $text );
		$text = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $text );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Il testo contiene la ricerca?
	 *
	 * Non basta il confronto letterale: in italiano fra le parole si
	 * infilano le preposizioni ("duplicazione chiavi auto a Trapani"),
	 * e un controllo rigido darebbe un rosso sbagliato. Qui le parole
	 * devono esserci tutte e nell'ordine, con poco in mezzo.
	 *
	 * @param string $testo   Testo già normalizzato.
	 * @param string $keyword Ricerca già normalizzata.
	 * @return bool
	 */
	public static function contains( $testo, $keyword ) {
		if ( '' === $keyword || '' === $testo ) {
			return false;
		}
		if ( false !== strpos( $testo, $keyword ) ) {
			return true;
		}

		$parole = preg_split( '/\s+/u', $keyword );
		if ( count( $parole ) < 2 ) {
			return false;
		}

		// Fino a due parole di mezzo fra un pezzo e l'altro.
		$schema = implode( '(?:\s+\S+){0,2}\s+', array_map( 'preg_quote', $parole ) );

		return (bool) preg_match( '/' . $schema . '/u', $testo );
	}

	/**
	 * Tutte le parole della ricerca sono presenti, in qualunque ordine?
	 *
	 * Serve per l'indirizzo: lì la città sta all'inizio
	 * (/trapani/duplicazione-chiavi-auto/) mentre nella ricerca sta in
	 * fondo, quindi pretendere l'ordine darebbe un rosso sbagliato.
	 *
	 * @param string $testo   Testo già normalizzato.
	 * @param string $keyword Ricerca già normalizzata.
	 * @return bool
	 */
	public static function contains_all_words( $testo, $keyword ) {
		if ( '' === $keyword || '' === $testo ) {
			return false;
		}
		foreach ( preg_split( '/\s+/u', $keyword ) as $parola ) {
			if ( false === strpos( $testo, $parola ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Tutto il testo che finisce sulla pagina, in chiaro.
	 *
	 * @param int $post_id ID post.
	 * @return string
	 */
	public static function text_blob( $post_id ) {
		$post  = get_post( $post_id );
		$pezzi = array( $post ? $post->post_content : '' );

		$campi = array(
			'approfondimento',
			'perche_noi',
			'servizi_inclusi',
			'come_raggiungerci',
			'zone_servite',
			'comuni_limitrofi',
			'garanzia',
		);

		foreach ( $campi as $campo ) {
			$valore  = GLP_Meta::get( $post_id, $campo );
			$pezzi[] = is_array( $valore ) ? implode( ' ', $valore ) : (string) $valore;
		}

		foreach ( array( 'faq', 'processo' ) as $ripetibile ) {
			$righe = GLP_Meta::get( $post_id, $ripetibile );
			if ( is_array( $righe ) ) {
				foreach ( $righe as $riga ) {
					$pezzi[] = implode( ' ', array_map( 'strval', (array) $riga ) );
				}
			}
		}

		return self::normalize( implode( ' ', $pezzi ) );
	}

	/**
	 * Quante parole contiene la pagina.
	 *
	 * @param int $post_id ID post.
	 * @return int
	 */
	public static function word_count( $post_id ) {
		$testo = self::text_blob( $post_id );
		return '' === $testo ? 0 : count( preg_split( '/\s+/u', $testo ) );
	}

	/**
	 * Analisi della keyword principale.
	 *
	 * @param int $post_id ID post.
	 * @return array|null
	 */
	public static function analyze( $post_id ) {
		$keyword = trim( (string) GLP_Meta::get( $post_id, 'keyword_principale' ) );
		if ( '' === $keyword ) {
			return null;
		}

		$k     = self::normalize( $keyword );
		$post  = get_post( $post_id );
		$testo = self::text_blob( $post_id );

		$titolo = self::normalize( GLP_SEO::title( $post_id ) );
		$h1     = self::normalize( GLP_Content::h1( $post_id ) );
		$desc   = self::normalize( GLP_SEO::description( $post_id ) );
		// Il percorso completo: la città sta nella parte prima dello slug.
		$percorso = wp_parse_url( (string) get_permalink( $post_id ), PHP_URL_PATH );
		$slug     = self::normalize( str_replace( array( '-', '/' ), ' ', (string) $percorso ) );

		$faq      = GLP_Meta::get( $post_id, 'faq' );
		$faq_ok   = false;
		if ( is_array( $faq ) ) {
			foreach ( $faq as $riga ) {
				if ( isset( $riga['domanda'] ) && self::contains( self::normalize( $riga['domanda'] ), $k ) ) {
					$faq_ok = true;
					break;
				}
			}
		}

		$occorrenze = '' === $k ? 0 : substr_count( $testo, $k );
		$parole     = self::word_count( $post_id );

		$controlli = array(
			array(
				'ok'    => self::contains( $titolo, $k ),
				'nome'  => __( 'Nel title del motore di ricerca', 'geo-landing-pages' ),
				'aiuto' => __( 'È il fattore più diretto: la ricerca deve comparire nel titolo, possibilmente all\'inizio.', 'geo-landing-pages' ),
			),
			array(
				'ok'    => self::contains( $h1, $k ),
				'nome'  => __( 'Nel titolo H1 della pagina', 'geo-landing-pages' ),
				'aiuto' => __( 'Il titolo grande in cima alla pagina.', 'geo-landing-pages' ),
			),
			array(
				'ok'    => self::contains_all_words( $slug, $k ),
				'nome'  => __( 'Nell\'indirizzo della pagina', 'geo-landing-pages' ),
				'aiuto' => __( 'Lo slug. Se la pagina è già pubblicata e indicizzata, cambiarlo costa: valutalo solo su pagine nuove.', 'geo-landing-pages' ),
			),
			array(
				'ok'    => self::contains( $desc, $k ),
				'nome'  => __( 'Nella meta description', 'geo-landing-pages' ),
				'aiuto' => __( 'Non conta per il posizionamento, ma la ricerca viene evidenziata in grassetto nei risultati e fa cliccare.', 'geo-landing-pages' ),
			),
			array(
				'ok'    => $faq_ok,
				'nome'  => __( 'In almeno una domanda frequente', 'geo-landing-pages' ),
				'aiuto' => __( 'Intercetta le ricerche in forma di domanda e alimenta i dati strutturati FAQ.', 'geo-landing-pages' ),
			),
			array(
				'ok'    => $occorrenze >= 3,
				'nome'  => sprintf(
					/* translators: %d: numero di occorrenze. */
					__( 'Nel testo della pagina (%d volte)', 'geo-landing-pages' ),
					$occorrenze
				),
				'aiuto' => __( 'Tre o quattro occorrenze naturali bastano. Ripeterla venti volte non aiuta e peggiora la lettura.', 'geo-landing-pages' ),
			),
			array(
				'ok'    => $parole >= 300,
				'nome'  => sprintf(
					/* translators: %d: numero di parole. */
					__( 'Contenuto sufficiente (%d parole)', 'geo-landing-pages' ),
					$parole
				),
				'aiuto' => __( 'Sotto le 300 parole è difficile essere più utili di chi ti precede. Non è una regola di Google: è il confronto con i concorrenti.', 'geo-landing-pages' ),
			),
		);

		$superati = count( array_filter( wp_list_pluck( $controlli, 'ok' ) ) );

		// Keyword secondarie coperte.
		$secondarie = GLP_Meta::get( $post_id, 'keyword_secondarie' );
		$coperte    = 0;
		$totale_sec = 0;
		if ( is_array( $secondarie ) ) {
			foreach ( $secondarie as $sec ) {
				if ( '' === trim( (string) $sec ) ) {
					continue;
				}
				$totale_sec++;
				if ( self::contains( $testo, self::normalize( $sec ) ) ) {
					$coperte++;
				}
			}
		}

		return array(
			'keyword'    => $keyword,
			'controlli'  => $controlli,
			'superati'   => $superati,
			'totale'     => count( $controlli ),
			'occorrenze' => $occorrenze,
			'parole'     => $parole,
			'secondarie' => array( 'coperte' => $coperte, 'totale' => $totale_sec ),
			'duplicati'  => self::duplicates( $post_id, $keyword ),
		);
	}

	/**
	 * Altre pagine che puntano alla stessa ricerca.
	 *
	 * @param int    $post_id ID post da escludere.
	 * @param string $keyword Keyword.
	 * @return WP_Post[]
	 */
	public static function duplicates( $post_id, $keyword ) {
		$k = self::normalize( $keyword );
		if ( '' === $k ) {
			return array();
		}

		$altri = get_posts(
			array(
				'post_type'      => GLP_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 500,
				'post__not_in'   => array( (int) $post_id ),
				'fields'         => 'ids',
			)
		);

		$trovati = array();
		foreach ( $altri as $altro_id ) {
			$sua = GLP_Meta::raw( $altro_id, 'keyword_principale' );
			if ( '' !== trim( (string) $sua ) && self::normalize( $sua ) === $k ) {
				$trovati[] = get_post( $altro_id );
			}
		}

		return $trovati;
	}

	/* ------------------------------------------------------------------
	 * Amministrazione
	 * ------------------------------------------------------------------ */

	/** Riquadro nell'editor. */
	public static function meta_box() {
		add_meta_box(
			'glp-keyword',
			__( 'Parola chiave', 'geo-landing-pages' ),
			array( __CLASS__, 'render_box' ),
			GLP_POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Contenuto del riquadro.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_box( $post ) {
		$analisi = self::analyze( $post->ID );

		if ( ! $analisi ) {
			echo '<p>' . esc_html__( 'Nessuna parola chiave impostata. Scrivila nella sezione 1 del questionario ("Qual è la ricerca esatta che vuoi intercettare?") e salva: qui comparirà il controllo.', 'geo-landing-pages' ) . '</p>';
			return;
		}

		$ok = $analisi['superati'];
		$tot = $analisi['totale'];

		echo '<p class="glp-kw__key"><strong>' . esc_html( $analisi['keyword'] ) . '</strong></p>';
		echo '<p class="glp-kw__score ' . ( $ok >= $tot - 1 ? 'is-ok' : ( $ok >= 4 ? 'is-mid' : 'is-low' ) ) . '">'
			. esc_html( sprintf( '%d/%d', $ok, $tot ) ) . '</p>';

		echo '<ul class="glp-kw__list">';
		foreach ( $analisi['controlli'] as $controllo ) {
			echo '<li class="' . ( $controllo['ok'] ? 'is-ok' : 'is-low' ) . '" title="' . esc_attr( $controllo['aiuto'] ) . '">'
				. '<span aria-hidden="true">' . ( $controllo['ok'] ? '✓' : '×' ) . '</span> '
				. esc_html( $controllo['nome'] ) . '</li>';
		}
		echo '</ul>';

		if ( $analisi['secondarie']['totale'] > 0 ) {
			echo '<p class="glp-kw__sec">' . esc_html(
				sprintf(
					/* translators: 1: coperte, 2: totale. */
					__( 'Ricerche correlate presenti nel testo: %1$d su %2$d', 'geo-landing-pages' ),
					$analisi['secondarie']['coperte'],
					$analisi['secondarie']['totale']
				)
			) . '</p>';
		}

		if ( ! empty( $analisi['duplicati'] ) ) {
			echo '<div class="glp-kw__dup"><p><strong>' . esc_html__( 'Due pagine sulla stessa ricerca', 'geo-landing-pages' ) . '</strong></p><p>'
				. esc_html__( 'Si ostacolano a vicenda: Google ne sceglie una e spesso non è quella giusta. Differenzia la ricerca o unisci le pagine.', 'geo-landing-pages' )
				. '</p><ul>';
			foreach ( $analisi['duplicati'] as $doppione ) {
				echo '<li><a href="' . esc_url( (string) get_edit_post_link( $doppione->ID ) ) . '">'
					. esc_html( $doppione->post_title ) . '</a></li>';
			}
			echo '</ul></div>';
		}
	}

	/** Voce di menu. */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . GLP_POST_TYPE,
			__( 'Parole chiave', 'geo-landing-pages' ),
			__( 'Parole chiave', 'geo-landing-pages' ),
			'edit_others_posts',
			'glp-keywords',
			array( __CLASS__, 'render_screen' )
		);
	}

	/** Schermata generale. */
	public static function render_screen() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		$posts = get_posts(
			array(
				'post_type'      => GLP_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 300,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="wrap glp-keywords">
			<h1><?php esc_html_e( 'Parole chiave', 'geo-landing-pages' ); ?></h1>
			<p><?php esc_html_e( 'Una ricerca per pagina. Se due pagine puntano alla stessa, si tolgono forza a vicenda: è l\'errore che affossa i progetti multi-città.', 'geo-landing-pages' ); ?></p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Pagina', 'geo-landing-pages' ); ?></th>
						<th><?php esc_html_e( 'Ricerca da intercettare', 'geo-landing-pages' ); ?></th>
						<th><?php esc_html_e( 'Controlli', 'geo-landing-pages' ); ?></th>
						<th><?php esc_html_e( 'Parole', 'geo-landing-pages' ); ?></th>
						<th><?php esc_html_e( 'Indicizzata', 'geo-landing-pages' ); ?></th>
						<th><?php esc_html_e( 'Doppioni', 'geo-landing-pages' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $posts as $post ) : ?>
						<?php $analisi = self::analyze( $post->ID ); ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>"><strong><?php echo esc_html( $post->post_title ); ?></strong></a><br />
								<code><?php echo esc_html( wp_make_link_relative( get_permalink( $post ) ) ); ?></code>
							</td>
							<td>
								<?php if ( $analisi ) : ?>
									<?php echo esc_html( $analisi['keyword'] ); ?>
								<?php else : ?>
									<span class="glp-badge is-low"><?php esc_html_e( 'mancante', 'geo-landing-pages' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $analisi ) : ?>
									<span class="glp-badge <?php echo $analisi['superati'] >= $analisi['totale'] - 1 ? 'is-ok' : 'is-low'; ?>">
										<?php echo esc_html( sprintf( '%d/%d', $analisi['superati'], $analisi['totale'] ) ); ?>
									</span>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) self::word_count( $post->ID ) ); ?></td>
							<td>
								<?php echo GLP_Meta::is_indexable( $post->ID )
									? '<span class="glp-badge is-ok">index</span>'
									: '<span class="glp-badge is-low">noindex</span>'; ?>
							</td>
							<td>
								<?php if ( $analisi && ! empty( $analisi['duplicati'] ) ) : ?>
									<span class="glp-badge is-low"><?php echo esc_html( (string) count( $analisi['duplicati'] ) ); ?></span>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
