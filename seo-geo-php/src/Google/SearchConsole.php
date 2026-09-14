<?php
/**
 * Client dell API di Google Search Console.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Google;

use RuntimeException;

/**
 * Legge dati reali: per quali ricerche il sito compare, in che posizione,
 * quante volte viene cliccato.
 *
 * È l unica fonte autorevole sul comportamento del sito in Google: tutto il
 * resto, punteggio compreso, è una previsione. Qui ci sono i fatti.
 */
class SearchConsole {

	const BASE = 'https://searchconsole.googleapis.com';

	/** @var ServiceAccount */
	private $account;

	/** @var string Proprietà: https://sito.it/ oppure sc-domain:sito.it */
	private $proprieta;

	/** @var string Indirizzo di base, sostituibile in fase di prova. */
	private $base;

	/**
	 * @param ServiceAccount $account   Autenticazione.
	 * @param string         $proprieta Proprietà di Search Console.
	 * @param string         $base      Indirizzo di base alternativo.
	 */
	public function __construct( ServiceAccount $account, $proprieta = '', $base = '' ) {
		$this->account   = $account;
		$this->proprieta = self::normalizzaProprieta( $proprieta );
		$this->base      = rtrim( $base ?: (string) ( getenv( 'GSC_ENDPOINT' ) ?: self::BASE ), '/' );
	}

	/**
	 * Accetta sia "sito.it" sia l indirizzo completo e restituisce la forma
	 * che vuole l API.
	 *
	 * @param string $proprieta Come l ha scritta l utente.
	 * @return string
	 */
	public static function normalizzaProprieta( $proprieta ) {
		$proprieta = trim( (string) $proprieta );

		if ( '' === $proprieta ) {
			return '';
		}

		// Proprietà di dominio: copre http, https e tutti i sottodomini.
		if ( 0 === stripos( $proprieta, 'sc-domain:' ) ) {
			return 'sc-domain:' . strtolower( trim( substr( $proprieta, 10 ) ) );
		}

		if ( preg_match( '~^https?://~i', $proprieta ) ) {
			return rtrim( $proprieta, '/' ) . '/';
		}

		return 'sc-domain:' . strtolower( $proprieta );
	}

	/**
	 * @return string
	 */
	public function proprieta() {
		return $this->proprieta;
	}

	/**
	 * Pagina di Search Console dove si aggiungono gli utenti di una proprietà.
	 *
	 * Aggiungere un utente si fa solo dall interfaccia di Google: l API di
	 * Search Console non lo prevede. La cosa migliore che si può fare da qui è
	 * portare l utente sulla pagina esatta, con la proprietà già scelta.
	 *
	 * @param string $proprieta Proprietà; se vuota si apre l elenco generale.
	 * @return string
	 */
	public static function urlUtenti( $proprieta = '' ) {
		$proprieta = self::normalizzaProprieta( $proprieta );

		if ( '' === $proprieta ) {
			return 'https://search.google.com/search-console/users';
		}

		return 'https://search.google.com/search-console/users?resource_id=' . rawurlencode( $proprieta );
	}

	/**
	 * Proprietà a cui l account di servizio ha accesso.
	 *
	 * Serve soprattutto per un messaggio utile quando l utente si dimentica di
	 * aggiungerlo come utente in Search Console: gli si può dire cosa vede.
	 *
	 * @return array[] Elenco di array con 'proprieta' e 'permesso'.
	 */
	public function siti() {
		$dati  = $this->chiama( 'GET', '/webmasters/v3/sites' );
		$siti  = array();

		foreach ( (array) ( $dati['siteEntry'] ?? array() ) as $voce ) {
			$siti[] = array(
				'proprieta' => (string) ( $voce['siteUrl'] ?? '' ),
				'permesso'  => (string) ( $voce['permissionLevel'] ?? '' ),
			);
		}

		return $siti;
	}

	/**
	 * Interroga il rapporto sul rendimento.
	 *
	 * @param array $opzioni 'da', 'a' (AAAA-MM-GG), 'dimensioni', 'righe', 'filtri'.
	 * @return array[] Righe con le dimensioni richieste più i quattro numeri.
	 * @throws RuntimeException Se la proprietà non è configurata.
	 */
	public function rendimento( array $opzioni = array() ) {
		if ( '' === $this->proprieta ) {
			throw new RuntimeException( 'Nessuna proprietà di Search Console configurata.' );
		}

		$dimensioni = $opzioni['dimensioni'] ?? array( 'query' );
		$limite     = min( 25000, max( 1, (int) ( $opzioni['righe'] ?? 25000 ) ) );
		$righe      = array();
		$inizio     = 0;

		// L API restituisce al massimo 25.000 righe per richiesta: si continua
		// finché ne torna una pagina piena.
		do {
			$corpo = array(
				'startDate'  => $opzioni['da'] ?? gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
				'endDate'    => $opzioni['a'] ?? gmdate( 'Y-m-d', strtotime( '-2 days' ) ),
				'dimensions' => array_values( $dimensioni ),
				'rowLimit'   => $limite,
				'startRow'   => $inizio,
			);

			if ( ! empty( $opzioni['filtri'] ) ) {
				$corpo['dimensionFilterGroups'] = array( array( 'filters' => $opzioni['filtri'] ) );
			}

			$dati    = $this->chiama( 'POST', '/webmasters/v3/sites/' . rawurlencode( $this->proprieta ) . '/searchAnalytics/query', $corpo );
			$blocco  = (array) ( $dati['rows'] ?? array() );

			foreach ( $blocco as $riga ) {
				$voce = array();

				foreach ( array_values( $dimensioni ) as $i => $nome ) {
					$voce[ $nome ] = (string) ( $riga['keys'][ $i ] ?? '' );
				}

				$voce['clic']       = (int) ( $riga['clicks'] ?? 0 );
				$voce['impression'] = (int) ( $riga['impressions'] ?? 0 );
				$voce['ctr']        = round( (float) ( $riga['ctr'] ?? 0 ) * 100, 2 );
				$voce['posizione']  = round( (float) ( $riga['position'] ?? 0 ), 1 );

				$righe[] = $voce;
			}

			$inizio += $limite;
		} while ( count( $blocco ) >= $limite && $inizio < 100000 );

		return $righe;
	}

	/**
	 * Stato di indicizzazione di un singolo indirizzo.
	 *
	 * Quota limitata da Google (circa duemila indirizzi al giorno), quindi si
	 * usa solo sulle pagine che interessano davvero.
	 *
	 * @param string $url Indirizzo da controllare.
	 * @return array 'stato', 'copertura', 'ultima_scansione', 'canonica_google'.
	 */
	public function ispeziona( $url ) {
		$dati = $this->chiama(
			'POST',
			'/v1/urlInspection/index:inspect',
			array(
				'inspectionUrl' => $url,
				'siteUrl'       => $this->proprieta,
				'languageCode'  => 'it',
			)
		);

		$indice = (array) ( $dati['inspectionResult']['indexStatusResult'] ?? array() );

		return array(
			'url'              => $url,
			'stato'            => (string) ( $indice['verdict'] ?? '' ),
			'copertura'        => (string) ( $indice['coverageState'] ?? '' ),
			'ultima_scansione' => (string) ( $indice['lastCrawlTime'] ?? '' ),
			'canonica_google'  => (string) ( $indice['googleCanonical'] ?? '' ),
			'robots'           => (string) ( $indice['robotsTxtState'] ?? '' ),
		);
	}

	/**
	 * Sitemap dichiarate nella proprietà.
	 *
	 * @return array[]
	 */
	public function sitemap() {
		$dati = $this->chiama( 'GET', '/webmasters/v3/sites/' . rawurlencode( $this->proprieta ) . '/sitemaps' );
		$out  = array();

		foreach ( (array) ( $dati['sitemap'] ?? array() ) as $voce ) {
			$out[] = array(
				'percorso'   => (string) ( $voce['path'] ?? '' ),
				'inviata'    => (string) ( $voce['lastSubmitted'] ?? '' ),
				'errori'     => (int) ( $voce['errors'] ?? 0 ),
				'avvisi'     => (int) ( $voce['warnings'] ?? 0 ),
				'in_sospeso' => ! empty( $voce['isPending'] ),
			);
		}

		return $out;
	}

	/**
	 * Dice a Google di rileggere una sitemap.
	 *
	 * Serve dopo aver cambiato parecchi contenuti: la sitemap sul sito e
	 * gia aggiornata, ma Google la ripassa quando gli pare. Questo glielo
	 * chiede subito.
	 *
	 * Non forza l indicizzazione e non la accelera per forza: dice solo
	 * «guarda che questo elenco e cambiato». Promettere di piu sarebbe
	 * falso - l API che indicizza a richiesta Google la riserva alle offerte
	 * di lavoro e agli eventi in diretta, non alle pagine normali.
	 *
	 * @param string $indirizzo Indirizzo completo della sitemap.
	 * @return array
	 * @throws RuntimeException Se Google rifiuta.
	 */
	public function inviaSitemap( $indirizzo ) {
		$this->chiama(
			'PUT',
			'/webmasters/v3/sites/' . rawurlencode( $this->proprieta )
				. '/sitemaps/' . rawurlencode( (string) $indirizzo ),
			array(),
			// In lettura basta l ambito di sola lettura; per dire a Google
			// di rileggere qualcosa serve quello pieno.
			'https://www.googleapis.com/auth/webmasters'
		);

		return array( 'ok' => true, 'sitemap' => (string) $indirizzo );
	}

	/**
	 * Esegue la chiamata e traduce gli errori.
	 *
	 * @param string $metodo   GET, POST o PUT.
	 * @param string $percorso Percorso dell API.
	 * @param array  $corpo    Corpo JSON per il POST.
	 * @param string $ambito   Ambito OAuth, se diverso dalla sola lettura.
	 * @return array
	 * @throws RuntimeException Se Google risponde con un errore.
	 */
	private function chiama( $metodo, $percorso, array $corpo = array(), $ambito = '' ) {
		$token = '' !== $ambito ? $this->account->token( $ambito ) : $this->account->token();
		$ch    = curl_init( $this->base . $percorso );

		$opzioni = array(
			CURLOPT_HTTPHEADER     => array(
				'Authorization: Bearer ' . $token,
				'Content-Type: application/json',
			),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 60,
		);

		if ( 'POST' === $metodo ) {
			$opzioni[ CURLOPT_POST ]       = true;
			$opzioni[ CURLOPT_POSTFIELDS ] = json_encode( $corpo );
		}

		if ( 'PUT' === $metodo ) {
			$opzioni[ CURLOPT_CUSTOMREQUEST ] = 'PUT';
		}

		curl_setopt_array( $ch, $opzioni );

		$risposta = curl_exec( $ch );
		$stato    = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$errore   = curl_error( $ch );

		curl_close( $ch );

		if ( 0 === $stato ) {
			throw new RuntimeException( 'Connessione a Search Console non riuscita: ' . $errore );
		}

		$dati = json_decode( (string) $risposta, true );

		if ( $stato >= 400 ) {
			throw new RuntimeException( $this->messaggioErrore( $stato, is_array( $dati ) ? $dati : array() ) );
		}

		return is_array( $dati ) ? $dati : array();
	}

	/**
	 * @param int   $stato Codice HTTP.
	 * @param array $dati  Risposta decodificata.
	 * @return string
	 */
	private function messaggioErrore( $stato, array $dati ) {
		$messaggio = (string) ( $dati['error']['message'] ?? '' );

		if ( 403 === $stato ) {
			// Google distingue i due casi nel testo: inutile far scegliere
			// all utente fra due ipotesi quando la risposta è già lì dentro.
			if ( false !== stripos( $messaggio, 'sufficient permission' ) || false !== stripos( $messaggio, 'does not have permission' ) ) {
				return 'L account di servizio non è ancora autorizzato sulla proprietà ' . $this->proprieta . '. '
					. 'Non c entra il tuo accesso personale: l account di servizio è un utente a sé, e va aggiunto una volta. '
					. 'In Search Console apri la proprietà giusta → Impostazioni → Utenti e autorizzazioni → Aggiungi utente, '
					. 'incolla ' . $this->account->indirizzo() . ' e scegli il permesso "Con limitazioni". '
					. 'Serve essere proprietari della proprietà per poter aggiungere utenti. '
					. 'Fatto questo funziona da qualsiasi computer e da qualsiasi server, perché l autorizzazione sta sull account, non sul tuo browser.';
			}

			if ( false !== stripos( $messaggio, 'has not been used' ) || false !== stripos( $messaggio, 'is disabled' )
				|| false !== stripos( $messaggio, 'accessNotConfigured' ) ) {
				return 'L API Search Console non è attiva nel progetto Google Cloud. Aprila da '
					. 'API e servizi → Libreria → "Google Search Console API" → Abilita, aspetta un minuto e riprova. Dettaglio: ' . $messaggio;
			}

			return 'Search Console ha negato l accesso alla proprietà ' . $this->proprieta . '. Due cause possibili: '
				. 'l account di servizio (' . $this->account->indirizzo() . ') non è stato aggiunto fra gli utenti della '
				. 'proprietà, oppure l API Search Console non è attiva nel progetto Google Cloud. Dettaglio: ' . $messaggio;
		}

		if ( 404 === $stato ) {
			return 'Proprietà "' . $this->proprieta . '" non trovata in Search Console. Controlla che sia scritta '
				. 'esattamente come compare lì: "https://sito.it/" per una proprietà con prefisso URL, '
				. '"sc-domain:sito.it" per una proprietà di dominio.';
		}

		if ( 429 === $stato ) {
			return 'Limite di richieste di Search Console raggiunto: riprova fra qualche minuto.';
		}

		return 'Search Console ha risposto ' . $stato . ( $messaggio ? ': ' . $messaggio : '' );
	}
}
