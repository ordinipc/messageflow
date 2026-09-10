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

		$percorso = MDI_SEO_GEO_DIR . 'data/' . $mappa[ $file ];

		if ( ! file_exists( $percorso ) ) {
			status_header( 404 );
			exit;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: all' );
		header( 'Cache-Control: public, max-age=3600' );

		// phpcs:ignore WordPress.Security.EscapeOutput -- file di testo statico generato dal toolkit.
		echo file_get_contents( $percorso );
		exit;
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
		$extra .= "Sitemap: {$home}/sitemap_index.xml\n";

		return $output . $extra;
	}
}
