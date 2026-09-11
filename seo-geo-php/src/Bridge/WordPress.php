<?php
/**
 * Client verso il plugin installato sul sito WordPress.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Bridge;

use RuntimeException;

/**
 * Applica sul sito le correzioni calcolate dal gestionale, passando dalle rotte
 * REST del plugin. Ogni chiamata porta il token generato in bacheca.
 */
class WordPress {

	const PERCORSO = '/wp-json/mdi-seo/v1';

	/** @var string */
	private $url;

	/** @var string */
	private $token;

	/** @var int */
	private $timeout;

	/**
	 * @param array $cfg Sezione 'wordpress' della configurazione.
	 */
	public function __construct( array $cfg ) {
		$this->url     = rtrim( (string) ( $cfg['url'] ?? '' ), '/' );
		$this->token   = (string) ( $cfg['token'] ?? '' );
		$this->timeout = (int) ( $cfg['timeout'] ?? 60 );
	}

	/**
	 * Indirizzo e token sono configurati?
	 *
	 * @return bool
	 */
	public function pronto() {
		return '' !== $this->url
			&& '' !== $this->token
			&& 0 !== stripos( $this->token, 'DA_COMPILARE' );
	}

	/**
	 * Stato del sito: serve anche come prova di collegamento.
	 *
	 * @return array
	 */
	public function stato() {
		return $this->chiama( 'GET', '/stato' );
	}

	/**
	 * Invia al sito i dati aziendali salvati nelle impostazioni.
	 *
	 * @param array $cfg Configurazione completa.
	 * @return array
	 */
	public function inviaConfigurazione( array $cfg ) {
		return $this->chiama(
			'POST',
			'/config',
			array(
				'config' => array(
					'azienda' => $cfg['azienda'] ?? array(),
					'autori'  => $cfg['autori'] ?? array(),
					'seo'     => $cfg['seo'] ?? array(),
				),
			)
		);
	}

	/**
	 * Invia le meta ottimizzate.
	 *
	 * @param array $righe     Elenco di array con id, title, description, excerpt, focus.
	 * @param bool  $anteprima Se true il plugin non scrive nulla e restituisce il confronto.
	 * @return array
	 */
	public function inviaMeta( array $righe, $anteprima = false ) {
		return $this->chiama( 'POST', '/meta', array( 'contenuti' => array_values( $righe ), 'anteprima' => $anteprima ) );
	}

	/**
	 * Crea o aggiorna la bozza di un articolo.
	 *
	 * @param array $bozza Dati della bozza.
	 * @return array
	 */
	public function inviaBozza( array $bozza ) {
		return $this->chiama( 'POST', '/bozza', $bozza );
	}

	/**
	 * Assegna le categorie.
	 *
	 * @param array $assegnazioni Elenco di array con id e categoria.
	 * @return array
	 */
	public function inviaCategorie( array $assegnazioni ) {
		return $this->chiama( 'POST', '/categoria', array( 'assegnazioni' => array_values( $assegnazioni ) ) );
	}

	/**
	 * Invia la tabella dei redirect 301.
	 *
	 * @param array $righe Elenco di array con da e a.
	 * @return array
	 */
	public function inviaRedirect( array $righe ) {
		return $this->chiama( 'POST', '/redirect', array( 'redirect' => array_values( $righe ) ) );
	}

	/**
	 * Carica un immagine e la imposta come immagine in evidenza.
	 *
	 * @param int    $id          Articolo.
	 * @param string $binario     Contenuto del file.
	 * @param string $mime        Tipo MIME.
	 * @param string $nome        Nome del file senza estensione.
	 * @param string $alt         Testo alternativo.
	 * @param bool   $inEvidenza  Impostare come immagine in evidenza.
	 * @return array
	 */
	public function inviaImmagine( $id, $binario, $mime, $nome, $alt, $inEvidenza = true ) {
		return $this->chiama(
			'POST',
			'/immagine',
			array(
				'id'          => (int) $id,
				'dati_base64' => base64_encode( $binario ),
				'mime'        => $mime,
				'nome'        => $nome,
				'alt'         => $alt,
				'in_evidenza' => $inEvidenza,
			)
		);
	}

	/**
	 * Riversa la bozza dentro l articolo originale, che conserva URL e storia.
	 *
	 * @param int $id Articolo originale.
	 * @return array
	 */
	public function applicaBozza( $id ) {
		return $this->chiama( 'POST', '/applica-bozza', array( 'id' => (int) $id ) );
	}

	/**
	 * Sposta nel cestino i contenuti indicati (operazione reversibile).
	 *
	 * @param array $ids Id degli articoli.
	 * @return array
	 */
	public function cestina( array $ids ) {
		return $this->chiama( 'POST', '/cestina', array( 'ids' => array_map( 'intval', array_values( $ids ) ) ) );
	}

	/**
	 * Ripristina le meta precedenti.
	 *
	 * @param array $ids Id degli articoli.
	 * @return array
	 */
	public function annulla( array $ids ) {
		return $this->chiama( 'POST', '/annulla', array( 'ids' => array_values( $ids ) ) );
	}

	/**
	 * Esegue la chiamata HTTP.
	 *
	 * @param string     $metodo   GET o POST.
	 * @param string     $percorso Rotta.
	 * @param array|null $corpo    Corpo JSON.
	 * @return array
	 * @throws RuntimeException Se la chiamata non riesce.
	 */
	private function chiama( $metodo, $percorso, array $corpo = null ) {
		if ( ! $this->pronto() ) {
			throw new RuntimeException(
				'Collegamento non configurato: inserisci indirizzo del sito e token in Impostazioni. '
				. 'Il token si genera in WordPress → SEO & GEO.'
			);
		}

		$ch = curl_init( $this->url . self::PERCORSO . $percorso );

		$opzioni = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => $this->timeout,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/json',
				'X-MDI-Token: ' . $this->token,
				'Accept: application/json',
			),
		);

		if ( 'POST' === $metodo ) {
			$opzioni[ CURLOPT_POST ]       = true;
			$opzioni[ CURLOPT_POSTFIELDS ] = json_encode( $corpo ?: array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		curl_setopt_array( $ch, $opzioni );

		$risposta = curl_exec( $ch );
		$stato    = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$errore   = curl_error( $ch );
		curl_close( $ch );

		if ( 0 === $stato ) {
			throw new RuntimeException( 'Sito non raggiungibile: ' . $errore );
		}

		$dati = json_decode( (string) $risposta, true );

		if ( $stato >= 400 ) {
			$messaggio = is_array( $dati ) ? ( $dati['message'] ?? 'errore sconosciuto' ) : mb_substr( (string) $risposta, 0, 200 );

			switch ( $stato ) {
				case 401:
					throw new RuntimeException( 'Token rifiutato dal sito: ricopialo da WordPress → SEO & GEO.' );
				case 403:
					throw new RuntimeException( 'Accesso negato: ' . $messaggio );
				case 404:
					throw new RuntimeException(
						'Rotta non trovata. Verifica che il plugin MDI SEO & GEO Booster sia attivo e che i permalink '
						. 'non siano impostati su "Semplice" (Impostazioni → Permalink).'
					);
				default:
					throw new RuntimeException( "Il sito ha risposto con errore $stato: $messaggio" );
			}
		}

		if ( ! is_array( $dati ) ) {
			throw new RuntimeException( 'Risposta non valida dal sito: ' . mb_substr( (string) $risposta, 0, 200 ) );
		}

		return $dati;
	}
}
