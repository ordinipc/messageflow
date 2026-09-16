<?php
/**
 * Svuotamento della cache di pagina.
 *
 * @package MDI_SEO_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tiene allineata la pagina servita al pubblico con quello che c e nel
 * database.
 *
 * Nasce da una contraddizione che e costata mezza giornata: nella home si
 * vedeva un link interno che nell editor di Elementor non c era. Il link non
 * era mai stato scritto da nessuna parte - lo metteva un filtro mentre la
 * pagina veniva servita - e quel filtro non tocca piu le pagine da versioni.
 * Quello che si stava guardando era una copia in cache fatta prima.
 *
 * Il guaio e piu largo di quel link:
 *
 * 1. Ogni correzione applicata dal gestionale - title, description, dati
 *    strutturati, testo riscritto, immagini - resta invisibile al pubblico e
 *    ai motori finche la copia in cache non scade da se. Il lavoro e fatto,
 *    ma fuori non si vede.
 * 2. Il plugin misura il sito leggendo le proprie pagine pubbliche. Se
 *    risponde la cache, misura com era prima: il rilievo non si chiude e in
 *    elenco torna l errore appena corretto.
 *
 * Perche i nomi sono tanti: non esiste un modo solo di svuotare. Ogni plugin
 * di cache ha il suo, e chi usa il gestionale non deve sapere quale ha.
 */
class MDI_Cache {

	/**
	 * Lo svuotamento totale gia chiesto in questa richiesta.
	 *
	 * Quando si comprimono quaranta immagini in un colpo solo, quaranta
	 * svuotamenti totali sono trentanove di troppo.
	 *
	 * @var string[]|null
	 */
	private static $tutto = null;

	/**
	 * Svuota la copia in cache della pagina, o di tutto il sito.
	 *
	 * Si prova prima la via mirata - un solo contenuto - perche svuotare
	 * tutto su un sito grande vuol dire rigenerare centinaia di pagine.
	 * Quando il plugin di cache non offre la via mirata si svuota tutto:
	 * meglio un sito piu lento per qualche minuto di una correzione che
	 * nessuno vede.
	 *
	 * @param int $id Contenuto da svuotare, 0 per tutto il sito.
	 * @return string[] Nomi dei plugin di cache a cui si e chiesto di svuotare.
	 */
	public static function svuota( $id = 0 ) {
		$id    = (int) $id;

		if ( ! $id && null !== self::$tutto ) {
			return self::$tutto;
		}

		$fatto = array();

		// La cache interna di WordPress: sta in memoria, ma su un sito con
		// Redis o Memcached sopravvive alla richiesta.
		if ( $id && function_exists( 'clean_post_cache' ) ) {
			clean_post_cache( $id );
		}

		foreach ( self::attivi() as $nome => $come ) {
			if ( $id && isset( $come['uno'] ) && self::prova( $come['uno'], $id ) ) {
				$fatto[] = $nome;
				continue;
			}

			if ( isset( $come['tutto'] ) && self::prova( $come['tutto'], 0 ) ) {
				$fatto[] = $nome;
			}
		}

		if ( ! $id ) {
			self::$tutto = $fatto;
		}

		return $fatto;
	}

	/**
	 * Dimentica che si e gia svuotato tutto. Serve solo ai test.
	 *
	 * @return void
	 */
	public static function ricomincia() {
		self::$tutto = null;
	}

	/**
	 * Quali plugin di cache di pagina sono attivi adesso.
	 *
	 * Si guarda se il codice e caricato - costante, classe o funzione - non
	 * se ha lasciato dei dati nel database: un plugin disinstallato lascia
	 * opzioni e cartelle per sempre, e da quelle non si deduce che sia
	 * ancora al lavoro.
	 *
	 * @return array<string,array<string,array>> Nome del plugin => modi per svuotare.
	 */
	public static function attivi() {
		$tutti = array(
			'LiteSpeed Cache' => array(
				'quando' => array( 'costante' => 'LSCWP_V', 'classe' => 'LiteSpeed\\Core' ),
				'uno'    => array( 'azione' => 'litespeed_purge_post' ),
				'tutto'  => array( 'azione' => 'litespeed_purge_all' ),
			),
			'WP Rocket' => array(
				'quando' => array( 'costante' => 'WP_ROCKET_VERSION' ),
				'uno'    => array( 'funzione' => 'rocket_clean_post' ),
				'tutto'  => array( 'funzione' => 'rocket_clean_domain' ),
			),
			'W3 Total Cache' => array(
				'quando' => array( 'costante' => 'W3TC' ),
				'uno'    => array( 'funzione' => 'w3tc_flush_post' ),
				'tutto'  => array( 'funzione' => 'w3tc_flush_all' ),
			),
			'WP Super Cache' => array(
				'quando' => array( 'funzione' => 'wp_cache_post_change' ),
				'uno'    => array( 'funzione' => 'wp_cache_post_change' ),
				'tutto'  => array( 'funzione' => 'wp_cache_clear_cache' ),
			),
			'WP Fastest Cache' => array(
				'quando' => array( 'classe' => 'WpFastestCache' ),
				'uno'    => array( 'metodo' => array( 'wp_fastest_cache', 'singleDeleteCache' ) ),
				'tutto'  => array( 'azione' => 'wpfc_clear_all_cache' ),
			),
			'SiteGround Optimizer' => array(
				'quando' => array( 'funzione' => 'sg_cachepress_purge_cache' ),
				'tutto'  => array( 'funzione' => 'sg_cachepress_purge_cache' ),
			),
			'Breeze' => array(
				'quando' => array( 'classe' => 'Breeze_PurgeCache' ),
				'tutto'  => array( 'azione' => 'breeze_clear_all_cache' ),
			),
			'Hummingbird' => array(
				'quando' => array( 'classe' => 'Hummingbird\\WP_Hummingbird' ),
				'uno'    => array( 'azione' => 'wphb_clear_page_cache' ),
				'tutto'  => array( 'azione' => 'wphb_clear_page_cache' ),
			),
			'Nginx Helper' => array(
				'quando' => array( 'classe' => 'Nginx_Helper' ),
				'tutto'  => array( 'azione' => 'rt_nginx_helper_purge_all' ),
			),
			'Cache Enabler' => array(
				'quando' => array( 'classe' => 'Cache_Enabler' ),
				'uno'    => array( 'statico' => array( 'Cache_Enabler', 'clear_page_cache_by_post_id' ) ),
				'tutto'  => array( 'statico' => array( 'Cache_Enabler', 'clear_complete_cache' ) ),
			),
			'Autoptimize' => array(
				// Non e una cache di pagina: tiene CSS e JS uniti. Va
				// svuotata lo stesso, se no la pagina nuova continua a
				// caricare il foglio di stile vecchio.
				'quando' => array( 'classe' => 'autoptimizeCache' ),
				'tutto'  => array( 'statico' => array( 'autoptimizeCache', 'clearall' ) ),
			),
		);

		$attivi = array();

		foreach ( $tutti as $nome => $voce ) {
			if ( self::caricato( $voce['quando'] ) ) {
				unset( $voce['quando'] );
				$attivi[ $nome ] = $voce;
			}
		}

		return $attivi;
	}

	/**
	 * I soli nomi, per la diagnosi che legge il gestionale.
	 *
	 * @return string[]
	 */
	public static function nomi() {
		return array_keys( self::attivi() );
	}

	/**
	 * Il plugin e caricato?
	 *
	 * @param array $segni Costante, classe o funzione che lo rivelano.
	 * @return bool
	 */
	private static function caricato( array $segni ) {
		if ( isset( $segni['costante'] ) && defined( $segni['costante'] ) ) {
			return true;
		}

		if ( isset( $segni['classe'] ) && class_exists( $segni['classe'] ) ) {
			return true;
		}

		if ( isset( $segni['funzione'] ) && function_exists( $segni['funzione'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Chiede lo svuotamento nel modo previsto da quel plugin.
	 *
	 * @param array $come Modo: azione, funzione, metodo statico o oggetto globale.
	 * @param int   $id   Contenuto, 0 per tutto il sito.
	 * @return bool Vero se la chiamata e stata fatta davvero.
	 */
	private static function prova( array $come, $id ) {
		if ( isset( $come['azione'] ) && function_exists( 'do_action' ) ) {
			$id ? do_action( $come['azione'], $id ) : do_action( $come['azione'] );

			return true;
		}

		if ( isset( $come['funzione'] ) && function_exists( $come['funzione'] ) ) {
			$id ? call_user_func( $come['funzione'], $id ) : call_user_func( $come['funzione'] );

			return true;
		}

		if ( isset( $come['statico'] ) && is_callable( $come['statico'] ) ) {
			$id ? call_user_func( $come['statico'], $id ) : call_user_func( $come['statico'] );

			return true;
		}

		if ( isset( $come['metodo'] ) ) {
			list( $globale, $metodo ) = $come['metodo'];
			$oggetto                  = $GLOBALS[ $globale ] ?? null;

			if ( is_object( $oggetto ) && method_exists( $oggetto, $metodo ) ) {
				$id ? $oggetto->$metodo( $id ) : $oggetto->$metodo( true );

				return true;
			}
		}

		return false;
	}

	/**
	 * Indirizzo da usare quando il plugin legge una propria pagina per
	 * misurarla.
	 *
	 * Senza questo si misura la copia in cache, cioe com era il sito prima
	 * delle correzioni, e i rilievi appena chiusi si riaprono da soli.
	 *
	 * Non c e un modo che valga per tutti i plugin di cache: la marca nella
	 * query basta per quelli che non servono copie agli indirizzi con
	 * parametri, le intestazioni per quelli che rispettano no-cache. Insieme
	 * coprono i casi comuni; dove non bastano resta il fatto che prima di
	 * misurare si e appena svuotato.
	 *
	 * @param string $url Indirizzo pubblico.
	 * @return string
	 */
	public static function senza_cache( $url ) {
		$url = (string) $url;

		if ( '' === $url ) {
			return $url;
		}

		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . 'mdi-misura=' . time();
	}

	/**
	 * Intestazioni da mandare insieme, per la stessa ragione.
	 *
	 * @return array<string,string>
	 */
	public static function intestazioni() {
		return array(
			'Cache-Control' => 'no-cache, max-age=0',
			'Pragma'        => 'no-cache',
		);
	}
}
