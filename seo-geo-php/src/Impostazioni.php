<?php
/**
 * Impostazioni modificabili dall interfaccia.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

use RuntimeException;

/**
 * config.php resta il file dei valori predefiniti; quello che si salva dalle
 * impostazioni finisce in storage/impostazioni.json e vi si sovrappone.
 *
 * Così l applicazione si configura dal browser senza toccare i file via FTP, e
 * un aggiornamento del programma non cancella i dati inseriti.
 */
class Impostazioni {

	/** @var string */
	private static $file;

	/**
	 * Percorso del file delle impostazioni.
	 *
	 * @return string
	 */
	public static function file() {
		if ( ! self::$file ) {
			self::$file = dirname( __DIR__ ) . '/storage/impostazioni.json';
		}

		return self::$file;
	}

	/**
	 * Configurazione completa: valori predefiniti più quelli salvati.
	 *
	 * @param array $predefiniti Contenuto di config.php.
	 * @return array
	 */
	public static function carica( array $predefiniti ) {
		$file = self::file();

		if ( ! is_file( $file ) ) {
			return $predefiniti;
		}

		$salvate = json_decode( (string) file_get_contents( $file ), true );

		return is_array( $salvate ) ? self::unisci( $predefiniti, $salvate ) : $predefiniti;
	}

	/**
	 * Fusione ricorsiva: gli array associativi si fondono chiave per chiave,
	 * le liste (orari, aree servite) vengono sostituite in blocco.
	 *
	 * @param array $base    Valori di partenza.
	 * @param array $sopra   Valori che prevalgono.
	 * @return array
	 */
	private static function unisci( array $base, array $sopra ) {
		foreach ( $sopra as $chiave => $valore ) {
			if ( is_array( $valore ) && isset( $base[ $chiave ] ) && is_array( $base[ $chiave ] )
				&& self::eAssociativo( $valore ) ) {
				$base[ $chiave ] = self::unisci( $base[ $chiave ], $valore );
			} else {
				$base[ $chiave ] = $valore;
			}
		}

		return $base;
	}

	/**
	 * @param array $array Array.
	 * @return bool
	 */
	private static function eAssociativo( array $array ) {
		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}

	/**
	 * Salva le impostazioni.
	 *
	 * @param array $dati Struttura da salvare.
	 * @return void
	 * @throws RuntimeException Se la cartella non è scrivibile.
	 */
	public static function salva( array $dati ) {
		$file = self::file();
		$dir  = dirname( $file );

		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0775, true ) && ! is_dir( $dir ) ) {
			throw new RuntimeException( "Impossibile creare la cartella $dir" );
		}

		if ( ! is_writable( $dir ) ) {
			throw new RuntimeException( 'La cartella storage/ non è scrivibile: imposta i permessi 775.' );
		}

		$json = json_encode( $dati, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( false === file_put_contents( $file, $json ) ) {
			throw new RuntimeException( 'Salvataggio non riuscito.' );
		}

		// Il file contiene la chiave API: leggibile solo dal proprietario.
		@chmod( $file, 0600 );
	}

	/**
	 * Impostazioni salvate finora (senza i valori predefiniti).
	 *
	 * @return array
	 */
	public static function salvate() {
		$file = self::file();

		if ( ! is_file( $file ) ) {
			return array();
		}

		$dati = json_decode( (string) file_get_contents( $file ), true );

		return is_array( $dati ) ? $dati : array();
	}

	/**
	 * Token con cui un pulsante esterno può avviare l analisi.
	 *
	 * Viene creato alla prima richiesta e salvato: è l unica credenziale che
	 * autorizza l avvio da fuori, quindi vale come una password.
	 *
	 * @return string
	 */
	public static function tokenEsterno() {
		$salvate = self::salvate();

		if ( ! empty( $salvate['gestionale']['token'] ) ) {
			return (string) $salvate['gestionale']['token'];
		}

		$token = bin2hex( random_bytes( 20 ) );

		$salvate['gestionale']['token'] = $token;
		self::salva( $salvate );

		return $token;
	}

	/**
	 * Ricorda l indirizzo pubblico del gestionale.
	 *
	 * Serve al plugin: il pulsante "Analizza" dentro WordPress deve sapere chi
	 * chiamare, e una richiesta della coda o della riga di comando non ha un
	 * HTTP_HOST da cui ricavarlo.
	 *
	 * @param string $indirizzo Indirizzo base, senza barra finale.
	 * @return void
	 */
	public static function ricordaIndirizzo( $indirizzo ) {
		$indirizzo = rtrim( trim( (string) $indirizzo ), '/' );

		if ( '' === $indirizzo || ! preg_match( '~^https?://~i', $indirizzo ) ) {
			return;
		}

		$salvate = self::salvate();

		if ( ( $salvate['gestionale']['url'] ?? '' ) === $indirizzo ) {
			return;
		}

		$salvate['gestionale']['url'] = $indirizzo;
		self::salva( $salvate );
	}

	/**
	 * Indirizzo completo che avvia una analisi, token compreso.
	 *
	 * @return string Vuoto se il gestionale non è mai stato aperto dal browser.
	 */
	public static function urlAnalisi() {
		$base = (string) ( self::salvate()['gestionale']['url'] ?? '' );

		if ( '' === $base ) {
			return '';
		}

		return $base . '/index.php?p=api-analizza&token=' . self::tokenEsterno();
	}

	/**
	 * Rigenera il token di avvio esterno.
	 *
	 * @return string
	 */
	public static function rigeneraTokenEsterno() {
		$salvate                        = self::salvate();
		$salvate['gestionale']['token'] = bin2hex( random_bytes( 20 ) );

		self::salva( $salvate );

		return $salvate['gestionale']['token'];
	}

	/**
	 * Mostra solo le ultime quattro cifre di una chiave.
	 *
	 * @param string $chiave Chiave.
	 * @return string
	 */
	public static function mascherata( $chiave ) {
		$chiave = (string) $chiave;

		if ( '' === $chiave || 0 === stripos( $chiave, 'DA_COMPILARE' ) ) {
			return '';
		}

		return str_repeat( '•', max( 4, min( 24, mb_strlen( $chiave ) - 4 ) ) ) . mb_substr( $chiave, -4 );
	}
}
