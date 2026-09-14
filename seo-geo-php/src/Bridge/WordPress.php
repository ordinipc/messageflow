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
	 * Conteggi, dati del sito e autori: la prima chiamata di una sincronizzazione.
	 *
	 * @return array
	 */
	public function conteggi() {
		return $this->chiama( 'GET', '/conteggi' );
	}

	/**
	 * Un blocco di contenuti pubblicati.
	 *
	 * @param int $offset Da quale posizione.
	 * @param int $limite Quanti.
	 * @return array
	 */
	public function contenuti( $offset = 0, $limite = 40 ) {
		return $this->chiama( 'GET', '/contenuti?offset=' . (int) $offset . '&limite=' . (int) $limite );
	}

	/**
	 * Un blocco di allegati della libreria media.
	 *
	 * @param int $offset Da quale posizione.
	 * @param int $limite Quanti.
	 * @return array
	 */
	public function allegati( $offset = 0, $limite = 100 ) {
		return $this->chiama( 'GET', '/allegati?offset=' . (int) $offset . '&limite=' . (int) $limite );
	}

	/**
	 * Le voci dei menu di navigazione.
	 *
	 * @return array
	 */
	/**
	 * Stato attuale di un singolo contenuto.
	 *
	 * @param string $url Indirizzo del contenuto.
	 * @return array
	 */
	public function contenuto( $url ) {
		return $this->chiama( 'GET', '/contenuto?url=' . rawurlencode( $url ) );
	}

	public function menu() {
		return $this->chiama( 'GET', '/menu' );
	}

	/**
	 * Invia al sito i dati aziendali salvati nelle impostazioni.
	 *
	 * @param array $cfg Configurazione completa.
	 * @return array
	 */
	public function inviaConfigurazione( array $cfg ) {
		try {
			return $this->chiama(
				'POST',
				'/config',
				array(
					'config' => array(
						'azienda' => $cfg['azienda'] ?? array(),
						'autori'  => $cfg['autori'] ?? array(),
						'seo'     => $cfg['seo'] ?? array(),
						// Con questo indirizzo il plugin mostra il pulsante
						// "Analizza adesso" dentro la bacheca di WordPress.
						'analisi' => array( 'url' => \SeoGeo\Impostazioni::urlAnalisi() ),
					),
				)
			);
		} catch ( RuntimeException $e ) {
			// La rotta /config esiste dalla versione 1.1.0 del plugin: se manca,
			// il problema non è la configurazione ma il plugin da aggiornare.
			if ( false !== stripos( $e->getMessage(), 'Rotta non trovata' ) ) {
				throw new RuntimeException(
					'Il plugin installato sul sito è una versione precedente e non sa ricevere i dati aziendali. '
					. 'Aggiorna MDI SEO & GEO Booster alla 1.1.0 (Plugin → Aggiungi nuovo → Carica plugin) e risalva le impostazioni.'
				);
			}

			throw $e;
		}
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
	 * Scrive il testo nuovo direttamente sull articolo pubblicato.
	 *
	 * @param int   $id    Articolo su WordPress.
	 * @param array $dati  'titolo', 'contenuto', 'estratto', 'in_breve',
	 *                     'faq', 'meta_title', 'meta_description'.
	 * @return array
	 */
	public function sovrascrivi( $id, array $dati ) {
		return $this->chiama(
			'POST',
			'/sovrascrivi',
			array(
				'id'               => (int) $id,
				'titolo'           => (string) ( $dati['titolo'] ?? '' ),
				'contenuto'        => (string) ( $dati['contenuto'] ?? '' ),
				'estratto'         => (string) ( $dati['estratto'] ?? '' ),
				// La sintesi e le domande frequenti sono parte dell articolo:
				// vanno scritte dentro al testo, non solo tenute a parte.
				'in_breve'         => (string) ( $dati['in_breve'] ?? '' ),
				'faq'              => array_values( (array) ( $dati['faq'] ?? array() ) ),
				'meta_title'       => (string) ( $dati['meta_title'] ?? '' ),
				'meta_description' => (string) ( $dati['meta_description'] ?? '' ),
			)
		);
	}

	/**
	 * Che cosa c e davvero dentro a un contenuto, adesso, sul sito.
	 *
	 * @param int $wpId Contenuto su WordPress.
	 * @return array
	 */
	public function diagnosiContenuto( $wpId ) {
		return $this->chiama( 'GET', '/diagnosi-contenuto?id=' . (int) $wpId );
	}

	/**
	 * Quali fra questi contenuti hanno l immagine in evidenza, adesso, sul
	 * sito.
	 *
	 * @param int[] $ids Identificativi WordPress.
	 * @return array
	 */
	public function miniature( array $ids ) {
		return $this->chiama( 'POST', '/miniature', array( 'ids' => array_values( $ids ) ) );
	}

	/**
	 * Per ognuno degli id, quale costruttore visuale disegna il contenuto.
	 *
	 * @param array $ids Contenuti su WordPress.
	 * @return array
	 */
	public function costruttori( array $ids ) {
		return $this->chiama( 'POST', '/costruttori', array( 'ids' => array_values( $ids ) ) );
	}

	/**
	 * Che cosa contiene la struttura di Elementor di questi contenuti.
	 *
	 * @param array $ids Contenuti su WordPress.
	 * @return array
	 */
	public function struttureElementor( array $ids ) {
		return $this->chiama( 'POST', '/strutture-elementor', array( 'ids' => array_values( $ids ) ) );
	}

	/**
	 * Immagini della libreria media che pesano piu della soglia.
	 *
	 * @param int $oltre  Soglia in byte.
	 * @param int $limite Quante restituirne.
	 * @param int $offset Da quale partire.
	 * @return array
	 */
	public function immaginiPesanti( $oltre = 204800, $blocco = 150, $offset = 0 ) {
		return $this->chiama(
			'GET',
			'/immagini-pesanti?oltre=' . (int) $oltre . '&blocco=' . (int) $blocco . '&offset=' . (int) $offset
		);
	}

	/**
	 * Ricomprime un allegato in WebP.
	 *
	 * @param int   $id      Allegato.
	 * @param array $opzioni 'lato', 'qualita', 'peso_max'.
	 * @return array
	 */
	public function comprimiImmagine( $id, array $opzioni = array() ) {
		return $this->chiama(
			'POST',
			'/comprimi-immagine',
			array(
				'id'       => (int) $id,
				'lato'     => (int) ( $opzioni['lato'] ?? 1200 ),
				'qualita'  => (int) ( $opzioni['qualita'] ?? 82 ),
				'peso_max' => (int) ( $opzioni['peso_max'] ?? 190000 ),
			)
		);
	}

	/**
	 * Rimette gli originali al posto delle immagini ricompresse.
	 *
	 * @param array $ids Allegati; vuoto significa tutti.
	 * @return array
	 */
	public function ripristinaImmagini( array $ids = array() ) {
		return $this->chiama( 'POST', '/ripristina-immagine', array( 'ids' => array_values( $ids ) ) );
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
		$durata   = (float) curl_getinfo( $ch, CURLINFO_TOTAL_TIME );
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
			// Corpo vuoto con stato 200: WordPress è morto durante l elaborazione
			// senza riuscire a scrivere niente. Dirlo come "risposta non valida"
			// lasciava l utente davanti a un messaggio che finiva nel nulla.
			if ( '' === trim( (string) $risposta ) ) {
				throw new RuntimeException(
					sprintf(
						'Il sito ha chiuso la risposta senza scrivere niente (stato %d, dopo %s secondi). '
						. 'Di solito è il tempo massimo di esecuzione o la memoria di PHP, su un blocco di contenuti troppo grande: '
						. 'riprova, il pilota riparte da questo punto con lo stesso blocco. Se ricapita sempre nello stesso punto, '
						. 'chiedi all hosting di alzare max_execution_time e memory_limit.',
						$stato,
						number_format( $durata, 1, ',', '' )
					)
				);
			}

			throw new RuntimeException(
				sprintf(
					'Risposta non leggibile dal sito (stato %d): %s',
					$stato,
					mb_substr( trim( (string) $risposta ), 0, 200 )
				)
			);
		}

		return $dati;
	}
}
