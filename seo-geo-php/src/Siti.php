<?php
/**
 * I siti seguiti: uno per cliente.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

use RuntimeException;

/**
 * Il programma e nato per un sito solo: una cartella storage, un file di
 * impostazioni, un database. Per un agenzia non basta - i clienti sono
 * tanti, e le impostazioni di uno non devono nemmeno poter finire
 * sull altro.
 *
 * La separazione e per cartella, non per colonna: ogni cliente ha la sua
 * cartella con dentro le sue impostazioni e il suo database. Costa qualche
 * riga in piu all avvio e in cambio toglie di mezzo una classe intera di
 * guai: nessuna query puo pescare i contenuti del cliente sbagliato, perche
 * nel database aperto ci sono solo i suoi. Con una tabella condivisa
 * sarebbe bastato dimenticare un «WHERE sito» in una delle centocinquanta
 * query per mostrare a un cliente il lavoro fatto per un altro.
 *
 * Restano condivise solo le chiavi che si pagano a consumo - Gemini, Google
 * - perche sono dell agenzia, non del cliente: si scrivono una volta e
 * valgono per tutti, mentre il consumo resta separato perche i token li
 * conta il database di ognuno.
 */
class Siti {

	/** Dove stanno le cartelle dei clienti. */
	const DENTRO = 'siti';

	/** Le chiavi che si pagano a consumo: dell agenzia, non del cliente. */
	const GLOBALI = 'globali.json';

	/**
	 * La cartella storage.
	 *
	 * @return string
	 */
	public static function base() {
		return self::$base ?: dirname( __DIR__ ) . '/storage';
	}

	/**
	 * La cartella storage, se non e quella di sempre.
	 *
	 * Serve alle prove, che non devono toccare i dati veri: una prova sulla
	 * migrazione che sposta l archivio dell installazione al posto di uno
	 * finto sarebbe la prova piu costosa mai scritta.
	 *
	 * @var string
	 */
	private static $base = '';

	/**
	 * @param string $percorso Cartella storage.
	 * @return void
	 */
	public static function usaBase( $percorso ) {
		self::$base = (string) $percorso;
	}

	/**
	 * La cartella del cliente che si sta guardando.
	 *
	 * @var string
	 */
	private static $attiva = '';

	/**
	 * Dice su quale cliente si sta lavorando.
	 *
	 * Si chiama una volta all avvio. Serve a tutto quello che scrive file -
	 * export, bozze, immagini generate - che altrimenti finirebbe in una
	 * cartella sola per tutti: i numeri delle analisi ripartono da uno in
	 * ogni cliente, quindi «audit-1» di due clienti diversi si
	 * sovrascriverebbero a vicenda.
	 *
	 * @param string $cartella Cartella del cliente.
	 * @return void
	 */
	public static function usa( $cartella ) {
		self::$attiva = (string) $cartella;
	}

	/**
	 * Dove vanno i file di un analisi.
	 *
	 * @param int $auditId Analisi, 0 per la cartella che le contiene tutte.
	 * @return string
	 */
	public static function export( $auditId = 0 ) {
		$dentro = ( self::$attiva ?: self::base() ) . '/export';

		return $auditId ? $dentro . '/audit-' . (int) $auditId : $dentro;
	}

	/**
	 * La cartella di un cliente.
	 *
	 * @param string $slug Identificativo.
	 * @return string
	 */
	public static function cartella( $slug ) {
		return self::base() . '/' . self::DENTRO . '/' . self::slug( $slug );
	}

	/**
	 * Forma sicura di un identificativo: niente barre, niente punti, niente
	 * modo di uscire dalla cartella storage.
	 *
	 * @param string $nome Nome o slug.
	 * @return string
	 */
	public static function slug( $nome ) {
		$slug = mb_strtolower( trim( (string) $nome ) );
		$slug = preg_replace( '~^https?://~i', '', $slug );
		$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
		$slug = trim( (string) $slug, '-' );

		return '' !== $slug ? mb_substr( $slug, 0, 60 ) : 'sito';
	}

	/**
	 * I clienti seguiti, con quello che serve per sceglierli.
	 *
	 * @return array[] 'slug', 'nome', 'url', 'analisi', 'punteggio', 'quando'.
	 */
	public static function elenco() {
		$dentro = self::base() . '/' . self::DENTRO;

		if ( ! is_dir( $dentro ) ) {
			return array();
		}

		$fuori = array();

		foreach ( (array) glob( $dentro . '/*', GLOB_ONLYDIR ) as $cartella ) {
			$slug = basename( (string) $cartella );
			$dati = self::leggiImpostazioni( $cartella );

			$fuori[] = array(
				'slug' => $slug,
				'nome' => (string) ( $dati['azienda']['nome'] ?? '' ) ?: $slug,
				'url'  => (string) ( $dati['wordpress']['url'] ?? '' ),
			) + self::ultimaAnalisi( $cartella );
		}

		usort(
			$fuori,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['nome'], (string) $b['nome'] );
			}
		);

		return $fuori;
	}

	/**
	 * Quale cliente si sta guardando.
	 *
	 * Arriva dall indirizzo e resta nel cookie: chi lavora su un cliente
	 * per un ora non deve riselezionarlo a ogni pagina, ma deve anche
	 * poterlo cambiare con un link.
	 *
	 * @param string $chiesto Valore arrivato dall indirizzo.
	 * @return string Vuoto se non c e nessun cliente.
	 */
	public static function corrente( $chiesto = '' ) {
		$elenco = array_column( self::elenco(), 'slug' );

		if ( ! $elenco ) {
			return '';
		}

		foreach ( array( $chiesto, $_COOKIE['seo_sito'] ?? '' ) as $candidato ) {
			$slug = self::slug( (string) $candidato );

			if ( '' !== (string) $candidato && in_array( $slug, $elenco, true ) ) {
				return $slug;
			}
		}

		return $elenco[0];
	}

	/**
	 * Crea la cartella di un cliente nuovo.
	 *
	 * @param string $nome Nome del cliente.
	 * @return string Lo slug creato.
	 * @throws RuntimeException Se la cartella non si puo creare.
	 */
	public static function crea( $nome ) {
		$slug     = self::slug( $nome );
		$cartella = self::cartella( $slug );

		// Due clienti con lo stesso nome non devono scriversi addosso.
		$contatore = 2;

		while ( is_dir( $cartella ) ) {
			$slug     = self::slug( $nome ) . '-' . $contatore;
			$cartella = self::cartella( $slug );
			$contatore++;
		}

		if ( ! mkdir( $cartella, 0775, true ) && ! is_dir( $cartella ) ) {
			throw new RuntimeException( 'Non riesco a creare la cartella ' . $cartella . ': controlla i permessi di storage/.' );
		}

		return $slug;
	}

	/**
	 * Porta dentro alla struttura per clienti quello che c era prima.
	 *
	 * Chi usa il programma da mesi ha impostazioni e analisi nella vecchia
	 * posizione: diventano il primo cliente, senza perdere niente. I file
	 * si spostano una volta sola, e se qualcosa va storto restano dove
	 * sono - meglio non migrare che migrare a meta.
	 *
	 * @return string Lo slug del cliente creato, vuoto se non c era niente.
	 */
	public static function migra() {
		$vecchieImpostazioni = self::base() . '/impostazioni.json';
		$vecchioDatabase     = self::base() . '/audit.sqlite';

		if ( ! is_file( $vecchieImpostazioni ) && ! is_file( $vecchioDatabase ) ) {
			return '';
		}

		$dati = is_file( $vecchieImpostazioni )
			? (array) json_decode( (string) file_get_contents( $vecchieImpostazioni ), true )
			: array();

		$nome = (string) ( $dati['azienda']['nome'] ?? '' );

		if ( '' === trim( $nome ) ) {
			$nome = (string) ( $dati['wordpress']['url'] ?? 'primo-sito' );
		}

		$slug     = self::crea( $nome );
		$cartella = self::cartella( $slug );

		foreach ( array(
			$vecchieImpostazioni => $cartella . '/impostazioni.json',
			$vecchioDatabase     => $cartella . '/audit.sqlite',
		) as $da => $a ) {
			if ( is_file( $da ) ) {
				rename( $da, $a );
			}
		}

		// L export e le bozze stanno in cartelle numerate per analisi: si
		// spostano con il resto, se no la prima apertura del cliente nuovo
		// trova un archivio vuoto.
		foreach ( array( 'export', 'bozze' ) as $sotto ) {
			if ( is_dir( self::base() . '/' . $sotto ) && ! is_dir( $cartella . '/' . $sotto ) ) {
				rename( self::base() . '/' . $sotto, $cartella . '/' . $sotto );
			}
		}

		return $slug;
	}

	/**
	 * Le chiavi dell agenzia, valide per tutti i clienti.
	 *
	 * @return array
	 */
	public static function globali() {
		$file = self::base() . '/' . self::GLOBALI;

		if ( ! is_file( $file ) ) {
			return array();
		}

		$dati = json_decode( (string) file_get_contents( $file ), true );

		return is_array( $dati ) ? $dati : array();
	}

	/**
	 * Salva le chiavi dell agenzia.
	 *
	 * @param array $dati Struttura da salvare.
	 * @return void
	 * @throws RuntimeException Se non si puo scrivere.
	 */
	public static function salvaGlobali( array $dati ) {
		$file = self::base() . '/' . self::GLOBALI;

		if ( false === file_put_contents( $file, json_encode( $dati, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) {
			throw new RuntimeException( 'Salvataggio non riuscito: controlla i permessi di storage/.' );
		}

		// Ci sono dentro le chiavi API: leggibile solo dal proprietario.
		@chmod( $file, 0600 );
	}

	/**
	 * Le impostazioni di un cliente, senza passare da Impostazioni: serve
	 * solo per l elenco, dove interessano il nome e l indirizzo.
	 *
	 * @param string $cartella Cartella del cliente.
	 * @return array
	 */
	private static function leggiImpostazioni( $cartella ) {
		$file = $cartella . '/impostazioni.json';

		if ( ! is_file( $file ) ) {
			return array();
		}

		$dati = json_decode( (string) file_get_contents( $file ), true );

		return is_array( $dati ) ? $dati : array();
	}

	/**
	 * Quando e come e andata l ultima analisi di quel cliente.
	 *
	 * Si legge dal suo database, aperto in sola lettura e chiuso subito:
	 * l elenco dei clienti non deve tenere aperti dieci database per
	 * mostrare dieci numeri.
	 *
	 * @param string $cartella Cartella del cliente.
	 * @return array 'analisi', 'punteggio', 'quando'.
	 */
	private static function ultimaAnalisi( $cartella ) {
		$vuoto = array( 'analisi' => 0, 'punteggio' => null, 'quando' => '' );
		$file  = $cartella . '/audit.sqlite';

		if ( ! is_file( $file ) ) {
			return $vuoto;
		}

		try {
			$pdo = new \PDO( 'sqlite:' . $file, null, null, array( \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ) );

			$quante = (int) $pdo->query( 'SELECT COUNT(*) FROM audit' )->fetchColumn();
			$riga   = $pdo->query( 'SELECT punteggio, creato_il FROM audit ORDER BY id DESC LIMIT 1' )->fetch( \PDO::FETCH_ASSOC );

			return array(
				'analisi'   => $quante,
				'punteggio' => isset( $riga['punteggio'] ) ? (int) $riga['punteggio'] : null,
				'quando'    => (string) ( $riga['creato_il'] ?? '' ),
			);
		} catch ( \Throwable $e ) {
			// Un database appena creato non ha ancora le tabelle: non e un
			// guasto, e un cliente senza analisi.
			return $vuoto;
		}
	}
}
