<?php
/**
 * Diagnostica: dice cosa manca, invece di lasciarlo indovinare.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Health {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 12 );
		add_action( 'admin_post_glp_assign_parent', array( __CLASS__, 'handle_assign' ) );
	}

	/** Voce di menu. */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . GLP_POST_TYPE,
			__( 'Diagnostica', 'geo-landing-pages' ),
			__( 'Diagnostica', 'geo-landing-pages' ),
			'edit_others_posts',
			'glp-health',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Landing di primo livello che sembrano servizi finiti fuori posto.
	 *
	 * Un post di primo livello dovrebbe essere una città. Se ha un tipo di
	 * servizio assegnato, quasi certamente è un servizio a cui manca la
	 * città genitore: è il motivo più frequente per cui una città resta
	 * vuota.
	 *
	 * @return WP_Post[]
	 */
	public static function orphans() {
		$primo_livello = get_posts(
			array(
				'post_type'      => GLP_POST_TYPE,
				'post_parent'    => 0,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$orfani = array();
		foreach ( $primo_livello as $post ) {
			$termini = wp_get_object_terms( $post->ID, GLP_TAXONOMY, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $termini ) && ! empty( $termini ) ) {
				$orfani[] = $post;
			}
		}

		return $orfani;
	}

	/**
	 * Controlli, con esito e spiegazione.
	 *
	 * @return array
	 */
	public static function checks() {
		$esiti = array();

		// 1. Permalink.
		$permalink = get_option( 'permalink_structure' );
		$esiti[]   = array(
			'ok'    => '' !== $permalink,
			'nome'  => __( 'Struttura dei permalink', 'geo-landing-pages' ),
			'testo' => '' !== $permalink
				? __( 'Attiva: gli indirizzi /citta/servizio/ possono funzionare.', 'geo-landing-pages' )
				: __( 'È impostata su "Semplice". Vai in Impostazioni → Permalink e scegli qualsiasi altra opzione, altrimenti nessun indirizzo del plugin funziona.', 'geo-landing-pages' ),
		);

		// 2. Città e figli. Le pagine orfane sono di primo livello ma non
		// sono città: vengono segnalate dal controllo successivo.
		$orfani_id = wp_list_pluck( self::orphans(), 'ID' );
		$citta     = array_filter(
			GLP_Post_Types::cities( 500 ),
			static function ( $c ) use ( $orfani_id ) {
				return ! in_array( $c->ID, $orfani_id, true );
			}
		);
		$vuote  = array();
		$totale = 0;

		foreach ( $citta as $c ) {
			$figli = get_posts(
				array(
					'post_type'      => GLP_POST_TYPE,
					'post_parent'    => $c->ID,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'fields'         => 'ids',
				)
			);
			$totale += count( $figli );
			if ( empty( $figli ) ) {
				$vuote[] = $c;
			}
		}

		$esiti[] = array(
			'ok'    => ! empty( $citta ) && empty( $vuote ),
			'nome'  => __( 'Città con contenuti', 'geo-landing-pages' ),
			'testo' => empty( $citta )
				? __( 'Nessuna città pubblicata.', 'geo-landing-pages' )
				: ( empty( $vuote )
					/* translators: 1: numero città, 2: numero pagine figlie. */
					? sprintf( __( '%1$d città, %2$d fra pagine e servizi pubblicati sotto di esse.', 'geo-landing-pages' ), count( $citta ), $totale )
					: sprintf(
						/* translators: %s: elenco città. */
						__( 'Queste città non hanno nessuna pagina né servizio pubblicato sotto: %s. Finché restano vuote non compaiono né il menu della città né il pannello dei servizi nell\'intestazione.', 'geo-landing-pages' ),
						implode( ', ', wp_list_pluck( $vuote, 'post_title' ) )
					) ),
		);

		// 3. Servizi senza città.
		$orfani  = self::orphans();
		$esiti[] = array(
			'ok'    => empty( $orfani ),
			'nome'  => __( 'Servizi collegati a una città', 'geo-landing-pages' ),
			'testo' => empty( $orfani )
				? __( 'Tutti i servizi hanno una città genitore.', 'geo-landing-pages' )
				: sprintf(
					/* translators: %d: numero di servizi. */
					__( '%d pagine sembrano servizi senza città: stanno al primo livello, quindi rispondono su /nome-servizio/ invece che su /citta/nome-servizio/. Assegnale qui sotto.', 'geo-landing-pages' ),
					count( $orfani )
				),
		);

		// 4. Tipi di servizio senza pagina collegata.
		$termini     = get_terms( array( 'taxonomy' => GLP_TAXONOMY, 'hide_empty' => false ) );
		$scollegati  = array();
		if ( ! is_wp_error( $termini ) ) {
			foreach ( $termini as $termine ) {
				if ( ! GLP_Services::page_of( $termine->term_id ) ) {
					$scollegati[] = $termine->name;
				}
			}
		}

		$esiti[] = array(
			'ok'    => empty( $scollegati ),
			'nome'  => __( 'Tipi di servizio collegati a una pagina', 'geo-landing-pages' ),
			'testo' => empty( $scollegati )
				? __( 'Tutti i tipi puntano a una pagina del sito.', 'geo-landing-pages' )
				: sprintf(
					/* translators: %s: elenco tipi. */
					__( 'Senza pagina collegata: %s. Nelle schede appaiono, ma non sono cliccabili.', 'geo-landing-pages' ),
					implode( ', ', $scollegati )
				),
		);

		// 5. Menu della città.
		$posizione = GLP_Settings::get( 'city_menu', 'sotto' );
		$esiti[]   = array(
			'ok'    => 'off' !== $posizione,
			'nome'  => __( 'Menu della città', 'geo-landing-pages' ),
			'testo' => 'off' !== $posizione
				? __( 'Attivo. Compare solo se la città ha almeno una pagina o un servizio pubblicato.', 'geo-landing-pages' )
				: __( 'Disattivato nelle impostazioni.', 'geo-landing-pages' ),
		);

		// 6. Menu del tema: lasciarlo com'è è legittimo.
		$nav     = GLP_Settings::get( 'nav_mode', 'off' );
		$esiti[] = array(
			'ok'    => true,
			'info'  => 'off' === $nav,
			'nome'  => __( 'Menu in alto del tema', 'geo-landing-pages' ),
			'testo' => 'off' === $nav
				? __( 'Il menu del tema resta quello del sito: il plugin non lo tocca. Per farci comparire la città, cambia l\'impostazione.', 'geo-landing-pages' )
				: __( 'Il plugin inserisce la città nel menu del tema sulle pagine locali.', 'geo-landing-pages' ),
		);

		// 7. Regole degli indirizzi.
		$regole   = get_option( 'rewrite_rules' );
		$slugs    = GLP_Post_Types::city_slugs();
		$presente = false;
		if ( is_array( $regole ) && ! empty( $slugs ) ) {
			foreach ( array_keys( $regole ) as $regola ) {
				if ( false !== strpos( $regola, $slugs[0] ) ) {
					$presente = true;
					break;
				}
			}
		}

		$esiti[] = array(
			'ok'    => $presente || empty( $slugs ),
			'nome'  => __( 'Indirizzi delle città registrati', 'geo-landing-pages' ),
			'testo' => $presente || empty( $slugs )
				? __( 'Le regole degli indirizzi contengono le città.', 'geo-landing-pages' )
				: __( 'Le città non risultano nelle regole degli indirizzi: vai in Impostazioni → Permalink e premi Salva.', 'geo-landing-pages' ),
		);

		// 8. Assistente AI: non usarlo è una scelta, non un errore.
		$esiti[] = array(
			'ok'    => true,
			'info'  => ! GLP_AI::is_enabled(),
			'nome'  => __( 'Assistente AI', 'geo-landing-pages' ),
			'testo' => GLP_AI::is_enabled()
				? __( 'Configurato e attivo.', 'geo-landing-pages' )
				: __( 'Non attivo: manca la chiave Gemini o la spunta nelle impostazioni. Non è un errore, se non lo usi.', 'geo-landing-pages' ),
		);

		return $esiti;
	}

	/** Schermata. */
	public static function render() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		$esiti  = self::checks();
		$orfani = self::orphans();
		$citta  = GLP_Post_Types::cities( 500 );
		?>
		<div class="wrap glp-health">
			<h1><?php esc_html_e( 'Diagnostica', 'geo-landing-pages' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: numero di versione. */
					esc_html__( 'Geo Landing Pages versione %s. Qui sotto c\'è lo stato reale del sito: se qualcosa non compare sulle pagine, il motivo è quasi sempre in questo elenco.', 'geo-landing-pages' ),
					esc_html( GLP_VERSION )
				);
				?>
			</p>

			<?php if ( isset( $_GET['glp_fixed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success"><p>
					<?php
					printf(
						/* translators: %d: numero di pagine spostate. */
						esc_html__( 'Pagine spostate sotto la città: %d.', 'geo-landing-pages' ),
						(int) $_GET['glp_fixed'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					);
					?>
				</p></div>
			<?php endif; ?>

			<table class="widefat striped glp-health__table">
				<tbody>
					<?php foreach ( $esiti as $esito ) : ?>
						<tr>
							<td class="glp-health__icon">
								<?php
								if ( ! $esito['ok'] ) {
									echo '<span class="glp-badge is-low">!</span>';
								} elseif ( ! empty( $esito['info'] ) ) {
									echo '<span class="glp-badge is-info">i</span>';
								} else {
									echo '<span class="glp-badge is-ok">OK</span>';
								}
								?>
							</td>
							<td class="glp-health__name"><strong><?php echo esc_html( $esito['nome'] ); ?></strong></td>
							<td><?php echo esc_html( $esito['testo'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( ! empty( $orfani ) && ! empty( $citta ) ) : ?>
				<h2><?php esc_html_e( 'Assegna i servizi a una città', 'geo-landing-pages' ); ?></h2>
				<p><?php esc_html_e( 'Queste pagine hanno un tipo di servizio ma nessuna città: scegli dove metterle. L\'indirizzo cambierà di conseguenza.', 'geo-landing-pages' ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="glp_assign_parent" />
					<?php wp_nonce_field( 'glp_assign_parent' ); ?>

					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Pagina', 'geo-landing-pages' ); ?></th>
								<th><?php esc_html_e( 'Indirizzo attuale', 'geo-landing-pages' ); ?></th>
								<th><?php esc_html_e( 'Spostala sotto', 'geo-landing-pages' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $orfani as $orfano ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $orfano->post_title ); ?></strong></td>
									<td><code><?php echo esc_html( wp_make_link_relative( get_permalink( $orfano ) ) ); ?></code></td>
									<td>
										<select name="genitore[<?php echo esc_attr( $orfano->ID ); ?>]">
											<option value="0"><?php esc_html_e( '— lascia com\'è —', 'geo-landing-pages' ); ?></option>
											<?php foreach ( $citta as $c ) : ?>
												<option value="<?php echo esc_attr( $c->ID ); ?>"><?php echo esc_html( $c->post_title ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php submit_button( __( 'Sposta le pagine selezionate', 'geo-landing-pages' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Sposta le pagine sotto la città scelta. */
	public static function handle_assign() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'geo-landing-pages' ) );
		}
		check_admin_referer( 'glp_assign_parent' );

		$scelte = isset( $_POST['genitore'] ) ? (array) wp_unslash( $_POST['genitore'] ) : array();
		$fatte  = 0;

		foreach ( $scelte as $post_id => $genitore_id ) {
			$post_id     = (int) $post_id;
			$genitore_id = (int) $genitore_id;

			if ( ! $post_id || ! $genitore_id ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$post     = get_post( $post_id );
			$genitore = get_post( $genitore_id );

			if ( ! $post || ! $genitore || GLP_POST_TYPE !== $post->post_type || GLP_POST_TYPE !== $genitore->post_type ) {
				continue;
			}
			// Il genitore deve essere una città, non un altro servizio.
			if ( 0 !== (int) $genitore->post_parent ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_parent' => $genitore_id,
				)
			);
			$fatte++;
		}

		GLP_Post_Types::schedule_flush();

		wp_safe_redirect( add_query_arg( 'glp_fixed', $fatte, admin_url( 'edit.php?post_type=' . GLP_POST_TYPE . '&page=glp-health' ) ) );
		exit;
	}
}
