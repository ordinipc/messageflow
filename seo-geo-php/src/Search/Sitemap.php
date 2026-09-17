<?php
/**
 * Le sitemap del sito e la richiesta a Google di rileggerle.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Search;

use Throwable;

/**
 * Trovare la sitemap e dire a Google che e cambiata.
 *
 * Dopo aver riscritto duecento articoli la sitemap sul sito e gia
 * aggiornata - la genera WordPress o il plugin SEO - ma Google la ripassa
 * quando gli pare, e possono volerci giorni. Qui gliela si segnala.
 *
 * Quello che questo NON fa, ed e bene dirlo: non forza l indicizzazione.
 * L API che indicizza a richiesta Google la riserva alle offerte di lavoro
 * e agli eventi in diretta; usarla per le pagine normali e fuori dalle sue
 * condizioni d uso. Segnalare la sitemap e la cosa vera che si puo fare.
 */
class Sitemap {

	/** @var string[] I posti dove le mettono i plugin piu diffusi. */
	const CANDIDATE = array(
		'/sitemap_index.xml',
		'/sitemap.xml',
		'/wp-sitemap.xml',
		'/sitemap-index.xml',
	);

	/**
	 * Quali di questi indirizzi rispondono davvero con una sitemap.
	 *
	 * Non si indovina dal plugin installato: si chiede al sito. Un indirizzo
	 * che risponde 404, o che risponde HTML invece di XML, non e una
	 * sitemap e mandarlo a Google farebbe solo comparire un errore nella
	 * sua console.
	 *
	 * @param string $sito Indirizzo del sito.
	 * @return array[] 'url', 'byte', 'url_dentro'.
	 */
	public static function trova( $sito ) {
		$base  = rtrim( (string) $sito, '/' );
		$fuori = array();

		foreach ( self::CANDIDATE as $percorso ) {
			$indirizzo = $base . $percorso;
			$risposta  = self::leggi( $indirizzo );

			if ( ! $risposta ) {
				continue;
			}

			$fuori[] = array(
				'url'        => $indirizzo,
				'byte'       => strlen( $risposta ),
				// Quante voci contiene: un indice di sitemap ne elenca altre,
				// una sitemap normale elenca pagine. In tutti e due i casi il
				// numero dice se e viva o vuota.
				'url_dentro' => (int) preg_match_all( '#<loc>#i', $risposta ),
			);
		}

		return $fuori;
	}

	/**
	 * Tutti gli indirizzi elencati nelle sitemap del sito.
	 *
	 * Segue gli indici: una sitemap di WordPress e quasi sempre un indice
	 * che ne elenca altre, e fermarsi al primo livello vuol dire leggere
	 * dieci indirizzi invece di trecento.
	 *
	 * @param string $sito     Indirizzo del sito.
	 * @param int    $maxFigli Quante sotto-sitemap seguire al massimo.
	 * @return array<string,bool> Percorso confrontabile => true.
	 */
	public static function indirizzi( $sito, $maxFigli = 30 ) {
		$base   = rtrim( (string) $sito, '/' );
		$dentro = array();
		$viste  = array();

		foreach ( self::CANDIDATE as $percorso ) {
			$corpo = self::leggi( $base . $percorso );

			if ( '' === $corpo ) {
				continue;
			}

			$figlie = array();

			// Un indice elenca sitemap, una sitemap elenca pagine: la
			// differenza sta nel tag che le contiene, non nel nome del file.
			if ( false !== stripos( $corpo, '<sitemapindex' ) ) {
				preg_match_all( '#<loc>\s*([^<]+?)\s*</loc>#i', $corpo, $m );
				$figlie = array_slice( (array) $m[1], 0, max( 1, (int) $maxFigli ) );
			} else {
				self::raccogli( $corpo, $dentro );
			}

			foreach ( $figlie as $figlia ) {
				if ( isset( $viste[ $figlia ] ) ) {
					continue;
				}

				$viste[ $figlia ] = true;
				self::raccogli( self::leggi( $figlia ), $dentro );
			}
		}

		return $dentro;
	}

	/**
	 * Mette i <loc> di una sitemap dentro all elenco, in forma confrontabile.
	 *
	 * @param string $corpo  XML.
	 * @param array  $dentro Elenco da riempire.
	 * @return void
	 */
	private static function raccogli( $corpo, array &$dentro ) {
		if ( '' === (string) $corpo ) {
			return;
		}

		preg_match_all( '#<loc>\s*([^<]+?)\s*</loc>#i', (string) $corpo, $m );

		foreach ( (array) $m[1] as $url ) {
			$dentro[ self::confrontabile( $url ) ] = true;
		}
	}

	/**
	 * Percorso confrontabile: senza dominio, senza barra finale, minuscolo.
	 *
	 * @param string $url Indirizzo.
	 * @return string
	 */
	public static function confrontabile( $url ) {
		$url = preg_replace( '~^https?://[^/]+~i', '', html_entity_decode( (string) $url ) );

		return '/' . strtolower( trim( (string) $url, '/' ) );
	}

	/**
	 * Scarica un indirizzo e restituisce il corpo solo se e una sitemap.
	 *
	 * @param string $indirizzo Indirizzo.
	 * @return string Vuoto se non lo e.
	 */
	private static function leggi( $indirizzo ) {
		$ch = curl_init( $indirizzo );

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 3,
				CURLOPT_TIMEOUT        => 15,
				CURLOPT_USERAGENT      => 'SeoGeoAudit/1.0',
			)
		);

		$corpo = (string) curl_exec( $ch );
		$stato = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		curl_close( $ch );

		if ( 200 !== $stato || '' === $corpo ) {
			return '';
		}

		// Deve essere XML con dentro dei <loc>: parecchi siti rispondono
		// alla pagina 404 con un 200 e una pagina HTML.
		if ( false === stripos( $corpo, '<loc>' ) && false === stripos( $corpo, '<sitemapindex' ) ) {
			return '';
		}

		return $corpo;
	}

	/**
	 * Le sitemap che Google gia conosce, senza far esplodere la pagina se
	 * la proprieta non e raggiungibile.
	 *
	 * @param \SeoGeo\Google\SearchConsole $console Client.
	 * @return array[]
	 */
	public static function conosciute( $console ) {
		try {
			return $console->sitemap();
		} catch ( Throwable $e ) {
			return array();
		}
	}
}
