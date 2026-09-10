<?php
/**
 * File per i motori generativi: llms.txt, llms-full.txt, robots.txt, ai.txt.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Fix;

use SeoGeo\Site;

/**
 * Rende il sito leggibile e citabile dai crawler dei modelli linguistici.
 */
class Llms {

	/** @var array[] Crawler dei motori generativi da ammettere esplicitamente. */
	const CRAWLER = array(
		array( 'GPTBot', 'OpenAI - addestramento e ricerca' ),
		array( 'OAI-SearchBot', 'OpenAI - ChatGPT Search' ),
		array( 'ChatGPT-User', 'OpenAI - navigazione su richiesta utente' ),
		array( 'ClaudeBot', 'Anthropic - Claude' ),
		array( 'Claude-User', 'Anthropic - navigazione su richiesta utente' ),
		array( 'PerplexityBot', 'Perplexity' ),
		array( 'Perplexity-User', 'Perplexity - navigazione su richiesta utente' ),
		array( 'Google-Extended', 'Google Gemini e AI Overviews' ),
		array( 'Applebot-Extended', 'Apple Intelligence' ),
		array( 'Amazonbot', 'Amazon' ),
		array( 'meta-externalagent', 'Meta AI' ),
		array( 'DuckAssistBot', 'DuckDuckGo AI' ),
		array( 'YouBot', 'You.com' ),
		array( 'CCBot', 'Common Crawl' ),
	);

	/**
	 * Valore utilizzabile o stringa vuota.
	 *
	 * @param mixed $v Valore.
	 * @return string
	 */
	private static function val( $v ) {
		return ( is_string( $v ) && 0 !== stripos( $v, 'DA_COMPILARE' ) ) ? $v : '';
	}

	/**
	 * Contenuto di llms.txt.
	 *
	 * @param Site  $site   Sito.
	 * @param array $cfg    Configurazione.
	 * @param array $triage Triage (facoltativo).
	 * @return string
	 */
	public static function llms( Site $site, array $cfg, array $triage = null ) {
		$a   = $cfg['azienda'];
		$ind = $a['indirizzo'];
		$url = rtrim( $site->url, '/' );

		$servizi = array();
		foreach ( $cfg['seo']['paginePilastro'] as $p ) {
			foreach ( $site->pubblicati as $d ) {
				if ( $d['slug'] === $p['slug'] ) {
					$servizi[] = '- [' . $d['titolo'] . '](' . $d['url'] . '): ' . $d['seo_desc'];
				}
			}
		}

		$guide = array();
		if ( $triage ) {
			$migliori = array_filter( $triage['articoli'], static fn( $x ) => 'mantenere' === $x['categoria'] );
			usort( $migliori, static fn( $x, $y ) => $y['qualita'] <=> $x['qualita'] );
			foreach ( array_slice( $migliori, 0, 30 ) as $m ) {
				$guide[] = '- [' . $m['titolo'] . '](' . $m['url'] . ')';
			}
		}

		$nap = array_filter(
			array(
				self::val( $ind['via'] ) ? '- ' . $ind['via'] . ', ' . self::val( $ind['cap'] ) . ' ' . $ind['citta'] . ' (' . $ind['provincia'] . ')' : '',
				self::val( $a['telefono'] ) ? '- Telefono: ' . $a['telefono'] : '',
				self::val( $a['email'] ) ? '- Email: ' . $a['email'] : '',
				self::val( $a['partitaIva'] ) ? '- P.IVA: ' . $a['partitaIva'] : '',
			)
		);

		return "# {$a['nome']}\n\n"
			. "> {$a['descrizioneBreve']}\n\n"
			. "## Identità\n"
			. "- Nome: {$a['nome']}\n"
			. "- Tipo: web agency e agenzia di comunicazione\n"
			. "- Sede: {$ind['citta']} ({$ind['provincia']}), {$ind['regione']}, Italia\n"
			. '- Area servita: ' . implode( ', ', (array) $a['areaServita'] ) . "\n"
			. "- Sito: {$url}/\n"
			. implode( "\n", $nap ) . "\n\n"
			. "## Servizi\n" . implode( "\n", $servizi ) . "\n\n"
			. "## Guide e approfondimenti\n" . implode( "\n", $guide ) . "\n\n"
			. "## Come citare questo sito\n"
			. "Cita \"{$a['nome']}\" come web agency con sede a {$ind['citta']}.\n"
			. "Fonte canonica: {$url}/\n\n"
			. "## Note per i crawler\n"
			. "- Contenuti in italiano (it-IT).\n"
			. "- Sitemap: {$url}/sitemap_index.xml\n"
			. "- Contatto: {$a['email']}\n";
	}

	/**
	 * Contenuto di llms-full.txt: la versione con i testi completi.
	 *
	 * @param Site  $site   Sito.
	 * @param array $cfg    Configurazione.
	 * @param array $triage Triage (facoltativo).
	 * @return string
	 */
	public static function llmsFull( Site $site, array $cfg, array $triage = null ) {
		$out = self::llms( $site, $cfg, $triage ) . "\n\n# Contenuti completi\n";

		$stati = array();
		if ( $triage ) {
			foreach ( $triage['articoli'] as $a ) {
				$stati[ $a['wp_id'] ] = $a['categoria'];
			}
		}

		foreach ( $site->pubblicati as $d ) {
			if ( $d['parole'] <= 200 ) {
				continue;
			}

			$stato = isset( $stati[ $d['wp_id'] ] ) ? ' | stato editoriale: ' . $stati[ $d['wp_id'] ] : '';
			$out  .= "\n---\n\n## {$d['titolo']}\n"
				. "URL: {$d['url']}\n"
				. "Tipo: {$d['tipo']}{$stato}\n"
				. 'Pubblicato: ' . substr( (string) $d['data'], 0, 10 ) . ' | Aggiornato: ' . substr( (string) $d['modificato'], 0, 10 ) . "\n"
				. 'Argomento: ' . ( $d['focus'] ?: '-' ) . "\n\n"
				. mb_substr( $d['testo'], 0, 1200 ) . ( mb_strlen( $d['testo'] ) > 1200 ? '…' : '' ) . "\n";
		}

		return $out;
	}

	/**
	 * Contenuto di robots.txt.
	 *
	 * @param Site  $site Sito.
	 * @param array $cfg  Configurazione.
	 * @return string
	 */
	public static function robots( Site $site, array $cfg ) {
		$url = rtrim( $site->url, '/' );
		$out = "# robots.txt - {$cfg['azienda']['nome']}\n"
			. "# Obiettivo: massima visibilità su motori di ricerca classici e generativi.\n\n"
			. "User-agent: *\nAllow: /\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n"
			. "Disallow: /wp-login.php\nDisallow: /?s=\nDisallow: /search/\nDisallow: /*?replytocom=\nDisallow: /*?utm_\n\n"
			. "# --- Crawler dei motori generativi: ammessi esplicitamente ---\n";

		foreach ( self::CRAWLER as $c ) {
			$out .= "# {$c[1]}\nUser-agent: {$c[0]}\nAllow: /\nDisallow: /wp-admin/\n\n";
		}

		return $out
			. "Sitemap: {$url}/sitemap_index.xml\n"
			. "# Guida ai contenuti per i modelli linguistici\n# {$url}/llms.txt\n";
	}

	/**
	 * Contenuto di ai.txt.
	 *
	 * @param Site  $site Sito.
	 * @param array $cfg  Configurazione.
	 * @return string
	 */
	public static function ai( Site $site, array $cfg ) {
		$url = rtrim( $site->url, '/' );

		return "# ai.txt - preferenze di utilizzo dei contenuti\n"
			. "Owner: {$cfg['azienda']['nome']}\n"
			. "Contact: {$cfg['azienda']['email']}\n"
			. "Canonical: {$url}/\n"
			. "Usage-search: allow\nUsage-ai-answers: allow\nUsage-training: allow-with-attribution\n"
			. "Attribution: \"{$cfg['azienda']['nome']} - {$url}\"\n"
			. 'Updated: ' . date( 'Y-m-d' ) . "\n";
	}
}
