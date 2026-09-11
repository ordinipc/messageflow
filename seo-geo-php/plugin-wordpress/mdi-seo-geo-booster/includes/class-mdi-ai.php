<?php
/**
 * Visibilità sui motori generativi: llms.txt, ai.txt, robots.txt.
 *
 * @package MDI_SEO_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Espone ai crawler dei modelli linguistici una mappa leggibile del sito.
 */
class MDI_AI {

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'serve' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );

		// I file per i motori generativi seguono i contenuti: appena qualcosa
		// cambia, la copia in cache va buttata.
		foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $quando ) {
			add_action( $quando, array( __CLASS__, 'svuota_cache' ) );
		}
	}

	/**
	 * Registra gli endpoint /llms.txt, /llms-full.txt e /ai.txt.
	 *
	 * @return void
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?mdi_ai_file=llms', 'top' );
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?mdi_ai_file=llms-full', 'top' );
		add_rewrite_rule( '^ai\.txt$', 'index.php?mdi_ai_file=ai', 'top' );
	}

	/**
	 * Registra la query var usata dagli endpoint.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'mdi_ai_file';

		return $vars;
	}

	/**
	 * Serve il file richiesto come testo semplice.
	 *
	 * @return void
	 */
	public static function serve() {
		$file = get_query_var( 'mdi_ai_file' );

		if ( ! $file ) {
			return;
		}

		$mappa = array(
			'llms'      => 'llms.txt',
			'llms-full' => 'llms-full.txt',
			'ai'        => 'ai.txt',
		);

		if ( ! isset( $mappa[ $file ] ) ) {
			return;
		}

		// Prima si prova a comporlo con i contenuti di adesso: il file dentro
		// lo zip è l istantanea di quando il plugin è stato generato, e dopo
		// qualche riscrittura racconta un sito che non esiste più.
		$testo   = self::genera( $file );
		$origine = 'dinamico';

		if ( '' === $testo ) {
			$percorso = MDI_SEO_GEO_DIR . 'data/' . $mappa[ $file ];

			if ( ! file_exists( $percorso ) ) {
				status_header( 404 );
				exit;
			}

			$testo   = (string) file_get_contents( $percorso );
			$origine = 'statico';
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: all' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'X-Mdi-Origine: ' . $origine );

		// phpcs:ignore WordPress.Security.EscapeOutput -- testo semplice, non HTML.
		echo $testo;
		exit;
	}

	const CACHE = 'mdi_seo_geo_generativi_';

	/**
	 * Svuota la cache dei file per i motori generativi.
	 *
	 * @return void
	 */
	public static function svuota_cache() {
		foreach ( array( 'llms', 'llms-full', 'ai' ) as $tipo ) {
			delete_transient( self::CACHE . $tipo );
		}
	}

	/**
	 * Compone il file richiesto dai contenuti pubblicati adesso.
	 *
	 * @param string $tipo 'llms', 'llms-full' oppure 'ai'.
	 * @return string Vuoto se mancano i dati aziendali: in quel caso si ripiega
	 *                sul file statico invece di pubblicare una mappa monca.
	 */
	public static function genera( $tipo ) {
		$in_cache = get_transient( self::CACHE . $tipo );

		if ( is_string( $in_cache ) && '' !== $in_cache ) {
			return $in_cache;
		}

		$nome = (string) mdi_seo_geo_cfg( 'azienda.nome' );

		if ( '' === $nome || 0 === stripos( $nome, 'DA_COMPILARE' ) ) {
			return '';
		}

		switch ( $tipo ) {
			case 'ai':
				$testo = self::genera_ai( $nome );
				break;

			case 'llms-full':
				$testo = self::genera_llms( $nome ) . self::genera_contenuti();
				break;

			default:
				$testo = self::genera_llms( $nome );
		}

		// Un ora: i contenuti non cambiano di minuto in minuto, e ogni modifica
		// butta comunque la cache da sé.
		set_transient( self::CACHE . $tipo, $testo, HOUR_IN_SECONDS );

		return $testo;
	}

	/**
	 * Corpo di llms.txt.
	 *
	 * @param string $nome Nome dell azienda.
	 * @return string
	 */
	private static function genera_llms( $nome ) {
		$url  = untrailingslashit( home_url() );
		$citta = (string) mdi_seo_geo_cfg( 'azienda.indirizzo.citta' );
		$prov  = (string) mdi_seo_geo_cfg( 'azienda.indirizzo.provincia' );

		$identita = array(
			'- Nome: ' . $nome,
			'- Sito: ' . $url . '/',
		);

		if ( '' !== $citta ) {
			$identita[] = '- Sede: ' . $citta . ( $prov ? ' (' . $prov . ')' : '' ) . ', Italia';
		}

		foreach ( array(
			'azienda.indirizzo.via' => '- Indirizzo: ',
			'azienda.telefono'      => '- Telefono: ',
			'azienda.email'         => '- Email: ',
			'azienda.partitaIva'    => '- P.IVA: ',
		) as $chiave => $etichetta ) {
			$valore = (string) mdi_seo_geo_cfg( $chiave );

			if ( '' !== $valore && 0 !== stripos( $valore, 'DA_COMPILARE' ) ) {
				$identita[] = $etichetta . $valore;
			}
		}

		$descrizione = (string) mdi_seo_geo_cfg( 'azienda.descrizioneBreve' );

		$testo = '# ' . $nome . "\n\n";

		if ( '' !== $descrizione && 0 !== stripos( $descrizione, 'DA_COMPILARE' ) ) {
			$testo .= '> ' . $descrizione . "\n\n";
		}

		$testo .= "## Identità\n" . implode( "\n", $identita ) . "\n\n";

		$servizi = self::elenco( 'page', 40 );

		if ( $servizi ) {
			$testo .= "## Servizi e pagine principali\n" . implode( "\n", $servizi ) . "\n\n";
		}

		$guide = self::elenco( 'post', 30 );

		if ( $guide ) {
			$testo .= "## Guide e approfondimenti\n" . implode( "\n", $guide ) . "\n\n";
		}

		return $testo
			. "## Come citare questo sito\n"
			. 'Cita "' . $nome . '"' . ( $citta ? ' come attività con sede a ' . $citta : '' ) . ".\n"
			. 'Fonte canonica: ' . $url . "/\n\n"
			. "## Note per i crawler\n"
			. "- Contenuti in italiano (it-IT).\n"
			. '- Sitemap: ' . self::url_sitemap() . "\n"
			. '- Aggiornato: ' . gmdate( 'Y-m-d' ) . "\n";
	}

	/**
	 * Elenco di contenuti pubblicati, dal più recente.
	 *
	 * @param string $tipo   'post' oppure 'page'.
	 * @param int    $quanti Quanti al massimo.
	 * @return string[]
	 */
	private static function elenco( $tipo, $quanti ) {
		$ids = get_posts(
			array(
				'post_type'      => $tipo,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $quanti,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		$righe = array();

		foreach ( (array) $ids as $id ) {
			$riga = '- [' . get_the_title( $id ) . '](' . get_permalink( $id ) . ')';

			// La description SEO, quando c è, spiega la pagina meglio di
			// qualsiasi taglio automatico del testo.
			$descrizione = get_post_meta( $id, 'rank_math_description', true );

			if ( ! $descrizione ) {
				$descrizione = get_post_meta( $id, '_yoast_wpseo_metadesc', true );
			}

			if ( is_array( $descrizione ) ) {
				$descrizione = implode( ' ', $descrizione );
			}

			$descrizione = trim( wp_strip_all_tags( (string) $descrizione ) );

			if ( '' !== $descrizione ) {
				$riga .= ': ' . $descrizione;
			}

			$righe[] = $riga;
		}

		return $righe;
	}

	/**
	 * Testi dei contenuti, per llms-full.txt.
	 *
	 * @return string
	 */
	private static function genera_contenuti() {
		$ids = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 120,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		$out = "\n# Contenuti completi\n";

		foreach ( (array) $ids as $id ) {
			$post = get_post( $id );

			if ( ! $post ) {
				continue;
			}

			$testo = trim( wp_strip_all_tags( (string) $post->post_content ) );

			// Sotto le duecento parole non è un contenuto: è un segnaposto.
			if ( str_word_count( $testo ) <= 200 ) {
				continue;
			}

			$out .= "\n---\n\n## " . get_the_title( $id ) . "\n"
				. 'URL: ' . get_permalink( $id ) . "\n"
				. 'Tipo: ' . $post->post_type . "\n"
				. 'Aggiornato: ' . substr( (string) $post->post_modified_gmt, 0, 10 ) . "\n\n"
				. mb_substr( $testo, 0, 1200 ) . ( mb_strlen( $testo ) > 1200 ? '…' : '' ) . "\n";
		}

		return $out;
	}

	/**
	 * Corpo di ai.txt.
	 *
	 * @param string $nome Nome dell azienda.
	 * @return string
	 */
	private static function genera_ai( $nome ) {
		$email = (string) mdi_seo_geo_cfg( 'azienda.email' );

		return "# ai.txt - preferenze di utilizzo dei contenuti\n"
			. 'Owner: ' . $nome . "\n"
			. ( $email ? 'Contact: ' . $email . "\n" : '' )
			. 'Canonical: ' . untrailingslashit( home_url() ) . "/\n"
			. "Usage-search: allow\nUsage-ai-answers: allow\nUsage-training: allow-with-attribution\n"
			. 'Attribution: "' . $nome . ' - ' . untrailingslashit( home_url() ) . "\"\n"
			. 'Updated: ' . gmdate( 'Y-m-d' ) . "\n";
	}

	/**
	 * Indirizzo della sitemap, secondo chi la genera sul sito.
	 *
	 * @return string
	 */
	public static function url_sitemap() {
		$url = untrailingslashit( home_url() );

		// Rank Math e Yoast sostituiscono la sitemap di WordPress con la loro.
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) || defined( 'WPSEO_VERSION' ) ) {
			return $url . '/sitemap_index.xml';
		}

		return $url . '/wp-sitemap.xml';
	}

	/**
	 * Aggiunge a robots.txt le direttive per i crawler dei motori generativi.
	 *
	 * @param string $output Contenuto corrente.
	 * @param bool   $public Sito pubblico.
	 * @return string
	 */
	public static function robots_txt( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}

		$home = untrailingslashit( home_url() );
		$bot  = array(
			'GPTBot',
			'OAI-SearchBot',
			'ChatGPT-User',
			'ClaudeBot',
			'Claude-User',
			'anthropic-ai',
			'PerplexityBot',
			'Perplexity-User',
			'Google-Extended',
			'Applebot-Extended',
			'Amazonbot',
			'meta-externalagent',
			'DuckAssistBot',
			'YouBot',
			'CCBot',
		);

		$extra = "\n# Crawler dei motori generativi: accesso consentito.\n";

		foreach ( $bot as $agent ) {
			$extra .= "User-agent: {$agent}\nAllow: /\nDisallow: /wp-admin/\n\n";
		}

		$extra .= "# Guida ai contenuti per i modelli linguistici\n# {$home}/llms.txt\n";
		// Non si indovina: dipende da chi genera la sitemap sul sito.
		$extra .= 'Sitemap: ' . self::url_sitemap() . "\n";

		return $output . $extra;
	}
}
