<?php
/**
 * Client per l API Gemini di Google.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Ai;

use RuntimeException;

/**
 * Chiamate a generateContent con nuovi tentativi sugli errori temporanei.
 *
 * Volutamente basato solo su cURL: nessuna libreria esterna da installare.
 */
class Gemini {

	const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

	/**
	 * URL di base: configurabile per usare un proxy aziendale o un finto
	 * servizio in fase di prova.
	 *
	 * @return string
	 */
	private function endpointBase() {
		$da_ambiente = getenv( 'GEMINI_ENDPOINT' );

		if ( $da_ambiente ) {
			return (string) $da_ambiente;
		}

		return (string) ( $this->cfg['endpoint'] ?? self::ENDPOINT );
	}

	/** @var array Sezione 'ai' della configurazione. */
	private $cfg;

	/** @var array Contatori dell ultima sessione. */
	private $consumo = array( 'chiamate' => 0, 'token_in' => 0, 'token_out' => 0 );

	/**
	 * @param array $cfg Sezione 'ai' della configurazione.
	 */
	public function __construct( array $cfg ) {
		$this->cfg = $cfg;
	}

	/**
	 * La chiave è configurata?
	 *
	 * @return bool
	 */
	public function pronto() {
		$chiave = $this->chiave();

		return '' !== $chiave && 0 !== stripos( $chiave, 'DA_COMPILARE' );
	}

	/**
	 * Chiave API, dalla configurazione o dalla variabile d ambiente.
	 *
	 * @return string
	 */
	private function chiave() {
		$da_ambiente = getenv( 'GEMINI_API_KEY' );

		return $da_ambiente ? (string) $da_ambiente : (string) ( $this->cfg['chiave'] ?? '' );
	}

	/**
	 * Consumo cumulato delle chiamate fatte finora.
	 *
	 * @return array
	 */
	public function consumo() {
		$prezzi = $this->cfg['prezzo_per_milione'] ?? array( 'input' => 0, 'output' => 0 );

		return $this->consumo + array(
			'costo_stimato' => round(
				$this->consumo['token_in'] / 1000000 * (float) $prezzi['input']
				+ $this->consumo['token_out'] / 1000000 * (float) $prezzi['output'],
				4
			),
		);
	}

	/**
	 * Genera testo.
	 *
	 * @param string $istruzioni Istruzioni di sistema.
	 * @param string $richiesta  Prompt dell utente.
	 * @param array  $opzioni    'json' => true per una risposta strutturata,
	 *                           'max_token', 'temperatura'.
	 * @return string Testo prodotto dal modello.
	 * @throws RuntimeException Se la chiamata non riesce.
	 */
	public function genera( $istruzioni, $richiesta, array $opzioni = array() ) {
		if ( ! $this->pronto() ) {
			throw new RuntimeException(
				'Chiave API Gemini mancante. Inseriscila in config.php (sezione ai → chiave) '
				. 'oppure nella variabile d ambiente GEMINI_API_KEY. Si ottiene su https://aistudio.google.com/apikey'
			);
		}

		$corpo = array(
			'contents'          => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $richiesta ) ),
				),
			),
			'systemInstruction' => array(
				'parts' => array( array( 'text' => $istruzioni ) ),
			),
			'generationConfig'  => array(
				'temperature'     => (float) ( $opzioni['temperatura'] ?? $this->cfg['temperatura'] ?? 0.7 ),
				'maxOutputTokens' => (int) ( $opzioni['max_token'] ?? $this->cfg['max_token'] ?? 4096 ),
			),
		);

		if ( ! empty( $opzioni['json'] ) ) {
			$corpo['generationConfig']['responseMimeType'] = 'application/json';
		}

		$modello  = $opzioni['modello'] ?? ( $this->cfg['modello'] ?? 'gemini-2.5-flash' );
		$endpoint = sprintf( $this->endpointBase(), rawurlencode( $modello ) );
		$tentativi = (int) ( $this->cfg['tentativi'] ?? 3 );

		for ( $tentativo = 1; $tentativo <= $tentativi; $tentativo++ ) {
			list( $stato, $risposta, $errore_rete ) = $this->chiama( $endpoint, $corpo );

			// Errori temporanei: si riprova con attesa crescente.
			if ( 429 === $stato || $stato >= 500 || 0 === $stato ) {
				if ( $tentativo < $tentativi ) {
					sleep( 2 ** $tentativo );
					continue;
				}
			}

			if ( 0 === $stato ) {
				throw new RuntimeException( 'Connessione a Gemini non riuscita: ' . $errore_rete );
			}

			$dati = json_decode( $risposta, true );

			if ( $stato >= 400 ) {
				throw new RuntimeException( $this->messaggioErrore( $stato, $dati ) );
			}

			if ( isset( $dati['promptFeedback']['blockReason'] ) ) {
				throw new RuntimeException( 'Richiesta bloccata dai filtri di sicurezza di Gemini (' . $dati['promptFeedback']['blockReason'] . ').' );
			}

			$testo = $dati['candidates'][0]['content']['parts'][0]['text'] ?? '';
			$fine  = $dati['candidates'][0]['finishReason'] ?? '';

			if ( '' === $testo ) {
				throw new RuntimeException( 'Gemini ha risposto senza testo' . ( $fine ? " (finishReason: $fine)" : '' ) . '.' );
			}

			$this->consumo['chiamate']++;
			$this->consumo['token_in']  += (int) ( $dati['usageMetadata']['promptTokenCount'] ?? 0 );
			$this->consumo['token_out'] += (int) ( $dati['usageMetadata']['candidatesTokenCount'] ?? 0 );

			if ( 'MAX_TOKENS' === $fine ) {
				$testo .= "\n\n[TESTO TRONCATO: alza max_token nella configurazione]";
			}

			return $testo;
		}

		throw new RuntimeException( 'Gemini non ha risposto dopo ' . $tentativi . ' tentativi.' );
	}

	/**
	 * Genera e decodifica una risposta JSON.
	 *
	 * @param string $istruzioni Istruzioni di sistema.
	 * @param string $richiesta  Prompt.
	 * @param array  $opzioni    Opzioni.
	 * @return array
	 * @throws RuntimeException Se la risposta non è JSON valido.
	 */
	public function generaJson( $istruzioni, $richiesta, array $opzioni = array() ) {
		$testo = $this->genera( $istruzioni, $richiesta, $opzioni + array( 'json' => true ) );
		$dati  = json_decode( $testo, true );

		if ( ! is_array( $dati ) ) {
			// Alcune risposte arrivano incapsulate in un blocco di codice.
			if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/', $testo, $m ) ) {
				$dati = json_decode( trim( $m[1] ), true );
			}
		}

		if ( ! is_array( $dati ) ) {
			throw new RuntimeException( 'Risposta non in formato JSON: ' . mb_substr( $testo, 0, 200 ) );
		}

		return $dati;
	}

	/**
	 * Genera un immagine.
	 *
	 * @param string $descrizione Prompt visivo.
	 * @param array  $opzioni     'modello'.
	 * @return array{0:string,1:string} Tipo MIME e contenuto binario.
	 * @throws RuntimeException Se la generazione non riesce.
	 */
	public function generaImmagine( $descrizione, array $opzioni = array() ) {
		if ( ! $this->pronto() ) {
			throw new RuntimeException( 'Chiave API Gemini mancante: impostala nelle impostazioni del gestionale.' );
		}

		$modello  = $opzioni['modello'] ?? ( $this->cfg['modello_immagini'] ?? 'gemini-2.5-flash-image' );
		$endpoint = sprintf( $this->endpointBase(), rawurlencode( $modello ) );

		$corpo = array(
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $descrizione ) ),
				),
			),
		);

		list( $stato, $risposta, $errore_rete ) = $this->chiama( $endpoint, $corpo );

		if ( 0 === $stato ) {
			throw new RuntimeException( 'Connessione a Gemini non riuscita: ' . $errore_rete );
		}

		$dati = json_decode( $risposta, true );

		if ( $stato >= 400 ) {
			$messaggio = $this->messaggioErrore( $stato, $dati );

			// La generazione di immagini richiede un progetto con fatturazione attiva.
			if ( 429 === $stato || false !== stripos( $messaggio, 'quota' ) || false !== stripos( $messaggio, 'billing' ) ) {
				$messaggio .= ' La generazione di immagini non rientra nel piano gratuito: serve un progetto Google con fatturazione attiva.';
			}

			throw new RuntimeException( $messaggio );
		}

		foreach ( (array) ( $dati['candidates'][0]['content']['parts'] ?? array() ) as $parte ) {
			if ( isset( $parte['inlineData']['data'] ) ) {
				$binario = base64_decode( $parte['inlineData']['data'], true );

				if ( false !== $binario && strlen( $binario ) > 1024 ) {
					$this->consumo['chiamate']++;
					$this->consumo['token_in']  += (int) ( $dati['usageMetadata']['promptTokenCount'] ?? 0 );
					$this->consumo['token_out'] += (int) ( $dati['usageMetadata']['candidatesTokenCount'] ?? 0 );

					return array( (string) ( $parte['inlineData']['mimeType'] ?? 'image/png' ), $binario );
				}
			}
		}

		throw new RuntimeException(
			'Il modello non ha restituito un immagine. Verifica che "' . $modello . '" sia un modello di generazione immagini '
			. 'disponibile sul tuo piano: puoi cambiarlo nelle impostazioni.'
		);
	}

	/**
	 * Esegue la richiesta HTTP.
	 *
	 * @param string $endpoint URL.
	 * @param array  $corpo    Corpo JSON.
	 * @return array{0:int,1:string,2:string} Stato, risposta, errore di rete.
	 */
	private function chiama( $endpoint, array $corpo ) {
		$ch = curl_init( $endpoint );

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => (int) ( $this->cfg['timeout'] ?? 120 ),
				CURLOPT_CONNECTTIMEOUT => 15,
				CURLOPT_HTTPHEADER     => array(
					'Content-Type: application/json',
					// La chiave viaggia nell intestazione, non nell URL: così non
					// finisce nei log del server né nella cronologia del browser.
					'x-goog-api-key: ' . $this->chiave(),
				),
				CURLOPT_POSTFIELDS     => json_encode( $corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			)
		);

		$risposta = curl_exec( $ch );
		$stato    = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$errore   = curl_error( $ch );
		curl_close( $ch );

		return array( $stato, (string) $risposta, $errore );
	}

	/**
	 * Traduce gli errori dell API in messaggi comprensibili.
	 *
	 * @param int   $stato Codice HTTP.
	 * @param array $dati  Corpo decodificato.
	 * @return string
	 */
	private function messaggioErrore( $stato, $dati ) {
		$messaggio = $dati['error']['message'] ?? 'errore sconosciuto';

		switch ( $stato ) {
			case 400:
				if ( false !== stripos( $messaggio, 'API key not valid' ) ) {
					return 'La chiave API non è valida. Controllala su https://aistudio.google.com/apikey e riportala in config.php.';
				}
				return "Richiesta rifiutata da Gemini: $messaggio";
			case 403:
				return "Accesso negato: $messaggio. Verifica che la chiave abbia accesso all API Generative Language.";
			case 404:
				return "Modello non trovato: $messaggio. Cambia il valore di ai → modello in config.php.";
			case 429:
				return 'Limite di richieste raggiunto: attendi qualche minuto oppure abbassa ai → articoli_per_volta.';
			default:
				return "Gemini ha risposto con errore $stato: $messaggio";
		}
	}

	/**
	 * Stima dei token di un testo: circa 4 caratteri per token in italiano.
	 *
	 * @param string $testo Testo.
	 * @return int
	 */
	public static function stimaToken( $testo ) {
		return (int) ceil( mb_strlen( (string) $testo ) / 4 );
	}
}
