<?php
/**
 * Autenticazione a Google con un account di servizio.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Google;

use RuntimeException;

/**
 * Scambia la chiave privata dell account di servizio con un token di accesso.
 *
 * Si usa un account di servizio e non l accesso con il proprio utente Google
 * perché il programma deve poter leggere Search Console anche da un cron, di
 * notte, senza che nessuno apra un browser per confermare.
 *
 * Volutamente senza librerie: firma RS256 con openssl, richiesta con cURL.
 */
class ServiceAccount {

	const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	/**
	 * Indirizzo dove scambiare il JWT con il token.
	 *
	 * Sostituibile con una variabile d ambiente: serve per collaudare senza
	 * chiamare Google, e a chi deve passare da un proxy aziendale.
	 *
	 * @return string
	 */
	private function urlToken() {
		$da_ambiente = getenv( 'GOOGLE_TOKEN_URL' );

		return $da_ambiente ? (string) $da_ambiente : self::TOKEN_URL;
	}

	/** @var array Contenuto del file JSON dell account di servizio. */
	private $chiave;

	/** @var string Percorso dove tenere il token fra una chiamata e l altra. */
	private $cache;

	/**
	 * @param array  $chiave Contenuto del JSON scaricato da Google Cloud.
	 * @param string $cache  File dove conservare il token.
	 */
	public function __construct( array $chiave, $cache = '' ) {
		$this->chiave = $chiave;
		$this->cache  = $cache ?: dirname( __DIR__, 2 ) . '/storage/token-google.json';
	}

	/**
	 * Legge la chiave da una stringa JSON e controlla che sia quella giusta.
	 *
	 * @param string $json Contenuto del file scaricato da Google Cloud.
	 * @return array
	 * @throws RuntimeException Se il file non è un account di servizio valido.
	 */
	public static function daJson( $json ) {
		$dati = json_decode( trim( (string) $json ), true );

		if ( ! is_array( $dati ) ) {
			throw new RuntimeException( 'Il file non è in formato JSON: ricontrolla di aver incollato tutto il contenuto, graffe comprese.' );
		}

		if ( isset( $dati['installed'] ) || isset( $dati['web'] ) ) {
			throw new RuntimeException(
				'Questo è un ID client OAuth, non un account di servizio. In Google Cloud: '
				. 'IAM e amministrazione → Account di servizio → Crea, poi Chiavi → Aggiungi chiave → JSON.'
			);
		}

		foreach ( array( 'type', 'client_email', 'private_key' ) as $campo ) {
			if ( empty( $dati[ $campo ] ) ) {
				throw new RuntimeException( 'Nel file manca il campo "' . $campo . '": non è la chiave di un account di servizio.' );
			}
		}

		if ( 'service_account' !== $dati['type'] ) {
			throw new RuntimeException( 'Il file dichiara type "' . $dati['type'] . '" invece di "service_account".' );
		}

		if ( false === strpos( $dati['private_key'], 'PRIVATE KEY' ) ) {
			throw new RuntimeException( 'La chiave privata nel file non sembra valida.' );
		}

		return $dati;
	}

	/**
	 * Indirizzo dell account di servizio: è quello da autorizzare in Search Console.
	 *
	 * @return string
	 */
	public function indirizzo() {
		return (string) ( $this->chiave['client_email'] ?? '' );
	}

	/**
	 * Token di accesso valido, riusando quello in cache finché non scade.
	 *
	 * @param string $ambito Ambito OAuth richiesto.
	 * @return string
	 * @throws RuntimeException Se Google rifiuta la chiave.
	 */
	public function token( $ambito = 'https://www.googleapis.com/auth/webmasters.readonly' ) {
		$salvato = $this->dallaCache( $ambito );

		if ( '' !== $salvato ) {
			return $salvato;
		}

		$adesso = time();
		$intestazione = array( 'alg' => 'RS256', 'typ' => 'JWT' );
		$corpo        = array(
			'iss'   => $this->indirizzo(),
			'scope' => $ambito,
			'aud'   => $this->urlToken(),
			'iat'   => $adesso,
			'exp'   => $adesso + 3600,
		);

		$firmato = $this->firma( $intestazione, $corpo );

		list( $stato, $risposta, $errore_rete ) = $this->chiama(
			$this->urlToken(),
			http_build_query(
				array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $firmato,
				)
			)
		);

		if ( 0 === $stato ) {
			throw new RuntimeException( 'Connessione a Google non riuscita: ' . $errore_rete );
		}

		$dati = json_decode( $risposta, true );

		if ( $stato >= 400 || empty( $dati['access_token'] ) ) {
			throw new RuntimeException( $this->messaggioErrore( $stato, is_array( $dati ) ? $dati : array() ) );
		}

		$this->inCache( $ambito, (string) $dati['access_token'], $adesso + (int) ( $dati['expires_in'] ?? 3600 ) );

		return (string) $dati['access_token'];
	}

	/**
	 * Compone e firma il JWT.
	 *
	 * @param array $intestazione Intestazione.
	 * @param array $corpo        Rivendicazioni.
	 * @return string
	 * @throws RuntimeException Se la firma non riesce.
	 */
	private function firma( array $intestazione, array $corpo ) {
		$parti = self::base64url( json_encode( $intestazione ) ) . '.' . self::base64url( json_encode( $corpo ) );
		$privata = openssl_pkey_get_private( $this->chiave['private_key'] );

		if ( ! $privata ) {
			throw new RuntimeException( 'La chiave privata non è leggibile: ' . openssl_error_string() );
		}

		$firma = '';

		if ( ! openssl_sign( $parti, $firma, $privata, OPENSSL_ALGO_SHA256 ) ) {
			throw new RuntimeException( 'Firma della richiesta non riuscita: ' . openssl_error_string() );
		}

		return $parti . '.' . self::base64url( $firma );
	}

	/**
	 * Base64 nella variante per URL, come vuole il formato JWT.
	 *
	 * @param string $dati Dati.
	 * @return string
	 */
	public static function base64url( $dati ) {
		return rtrim( strtr( base64_encode( (string) $dati ), '+/', '-_' ), '=' );
	}

	/**
	 * Token ancora valido salvato in precedenza.
	 *
	 * @param string $ambito Ambito.
	 * @return string Vuoto se assente o scaduto.
	 */
	private function dallaCache( $ambito ) {
		if ( ! is_file( $this->cache ) ) {
			return '';
		}

		$dati  = json_decode( (string) file_get_contents( $this->cache ), true );
		$voce  = is_array( $dati ) ? ( $dati[ $this->chiaveCache( $ambito ) ] ?? null ) : null;

		// Un minuto di margine: un token che scade durante la chiamata è inutile.
		if ( is_array( $voce ) && (int) ( $voce['scade'] ?? 0 ) > time() + 60 ) {
			return (string) $voce['token'];
		}

		return '';
	}

	/**
	 * Conserva il token.
	 *
	 * @param string $ambito Ambito.
	 * @param string $token  Token.
	 * @param int    $scade  Scadenza in secondi dall epoca.
	 * @return void
	 */
	private function inCache( $ambito, $token, $scade ) {
		$dati = is_file( $this->cache ) ? json_decode( (string) file_get_contents( $this->cache ), true ) : array();
		$dati = is_array( $dati ) ? $dati : array();

		$dati[ $this->chiaveCache( $ambito ) ] = array( 'token' => $token, 'scade' => (int) $scade );

		if ( false !== @file_put_contents( $this->cache, json_encode( $dati ) ) ) {
			@chmod( $this->cache, 0600 );
		}
	}

	/**
	 * @param string $ambito Ambito.
	 * @return string
	 */
	private function chiaveCache( $ambito ) {
		return substr( sha1( $this->indirizzo() . '|' . $ambito ), 0, 16 );
	}

	/**
	 * Traduce gli errori di Google in italiano comprensibile.
	 *
	 * @param int   $stato Codice HTTP.
	 * @param array $dati  Risposta decodificata.
	 * @return string
	 */
	private function messaggioErrore( $stato, array $dati ) {
		$errore    = (string) ( $dati['error'] ?? '' );
		$dettaglio = (string) ( $dati['error_description'] ?? '' );

		if ( 'invalid_grant' === $errore ) {
			// Google usa lo stesso codice per cause molto diverse: il testo del
			// dettaglio è l unico modo per dire all utente cosa guardare.
			if ( false !== stripos( $dettaglio, 'account not found' ) ) {
				return 'Google non conosce questo account di servizio (' . $this->indirizzo() . '). '
					. 'O è stato cancellato da Google Cloud, o la chiave incollata appartiene a un progetto che non esiste più. '
					. 'Scarica una chiave nuova: Google Cloud → IAM e amministrazione → Account di servizio → Chiavi → Aggiungi chiave → JSON.';
			}

			if ( false !== stripos( $dettaglio, 'jwt' ) || false !== stripos( $dettaglio, 'exp' ) || false !== stripos( $dettaglio, 'clock' ) ) {
				return 'Google ha rifiutato la richiesta per un problema di orario (' . $dettaglio . '). '
					. 'Succede quando l orologio del server è sfasato di parecchi minuti: chiedi all hosting di sincronizzarlo.';
			}

			return 'Google ha rifiutato la chiave (invalid_grant): ' . $dettaglio;
		}

		if ( 'invalid_client' === $errore ) {
			return 'Account di servizio non riconosciuto (invalid_client): la chiave potrebbe essere stata revocata.';
		}

		if ( 403 === $stato ) {
			return 'Google ha negato l accesso: controlla che l API Search Console sia attiva nel progetto Google Cloud. ' . $dettaglio;
		}

		return 'Google ha risposto ' . $stato . ( $errore ? ' (' . $errore . ')' : '' ) . ( $dettaglio ? ': ' . $dettaglio : '' );
	}

	/**
	 * Richiesta HTTP.
	 *
	 * @param string $url   Indirizzo.
	 * @param string $corpo Corpo già codificato.
	 * @return array{0:int,1:string,2:string}
	 */
	private function chiama( $url, $corpo ) {
		$ch = curl_init( $url );

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $corpo,
				CURLOPT_HTTPHEADER     => array( 'Content-Type: application/x-www-form-urlencoded' ),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 30,
			)
		);

		$risposta = curl_exec( $ch );
		$stato    = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$errore   = curl_error( $ch );

		curl_close( $ch );

		return array( $stato, (string) $risposta, $errore );
	}
}
