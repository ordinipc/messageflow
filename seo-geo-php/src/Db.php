<?php
/**
 * Accesso al database e creazione dello schema.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

use PDO;
use RuntimeException;

/**
 * Wrapper PDO con creazione automatica dello schema, per SQLite e MySQL.
 */
class Db {

	/** @var PDO */
	private $pdo;

	/** @var string */
	private $driver;

	/**
	 * @param array $config Sezione 'database' della configurazione.
	 */
	public function __construct( array $config ) {
		$this->driver = $config['driver'];

		if ( 'sqlite' === $this->driver ) {
			$file = $config['sqlite'];
			$dir  = dirname( $file );

			if ( ! is_dir( $dir ) && ! mkdir( $dir, 0775, true ) && ! is_dir( $dir ) ) {
				throw new RuntimeException( "Impossibile creare la cartella $dir" );
			}

			$this->pdo = new PDO( 'sqlite:' . $file );
			$this->pdo->exec( 'PRAGMA journal_mode = WAL' );
			$this->pdo->exec( 'PRAGMA foreign_keys = ON' );
		} else {
			$m   = $config['mysql'];
			$dsn = sprintf( 'mysql:host=%s;port=%d;dbname=%s;charset=%s', $m['host'], $m['porta'], $m['nome'], $m['charset'] );
			$this->pdo = new PDO( $dsn, $m['utente'], $m['password'] );
		}

		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->pdo->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );

		$this->migrate();
	}

	/**
	 * @return PDO
	 */
	public function pdo() {
		return $this->pdo;
	}

	/**
	 * Crea le tabelle se mancanti. Lo schema è volutamente compatibile con
	 * entrambi i driver: niente tipi esotici, chiavi autoincrementali gestite
	 * con la sintassi del driver in uso.
	 *
	 * @return void
	 */
	private function migrate() {
		$pk   = 'sqlite' === $this->driver ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
		$txt  = 'sqlite' === $this->driver ? 'TEXT' : 'LONGTEXT';
		$vc   = 'sqlite' === $this->driver ? 'TEXT' : 'VARCHAR(255)';
		$suff = 'sqlite' === $this->driver ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

		$tabelle = array(

			"CREATE TABLE IF NOT EXISTS audit (
				id $pk,
				sito_nome $vc,
				sito_url $vc,
				file_origine $vc,
				creato_il $vc,
				punteggio INT,
				problemi_totali INT,
				articoli INT,
				pagine INT,
				media INT
			)$suff",

			"CREATE TABLE IF NOT EXISTS documento (
				id $pk,
				audit_id INT NOT NULL,
				wp_id $vc,
				tipo $vc,
				stato $vc,
				titolo $txt,
				slug $vc,
				url $txt,
				percorso $vc,
				parole INT,
				h1 INT,
				h2 INT,
				immagini INT,
				link_in INT,
				link_out INT,
				focus_keyword $vc,
				seo_title $txt,
				seo_description $txt,
				gulpease INT,
				pubblicato $vc,
				modificato $vc
			)$suff",

			"CREATE TABLE IF NOT EXISTS area (
				id $pk,
				audit_id INT NOT NULL,
				chiave $vc,
				etichetta $vc,
				punteggio INT,
				rilievi INT,
				peso INT
			)$suff",

			"CREATE TABLE IF NOT EXISTS rilievo (
				id $pk,
				audit_id INT NOT NULL,
				regola $vc,
				area $vc,
				gravita $vc,
				titolo $txt,
				perche $txt,
				soluzione $txt,
				automatico INT,
				occorrenze INT
			)$suff",

			"CREATE TABLE IF NOT EXISTS occorrenza (
				id $pk,
				rilievo_id INT NOT NULL,
				riferimento $txt,
				dettaglio $txt
			)$suff",

			"CREATE TABLE IF NOT EXISTS triage (
				id $pk,
				audit_id INT NOT NULL,
				documento_id INT NOT NULL,
				categoria $vc,
				qualita INT,
				intento $vc,
				similarita_max REAL,
				motivo $txt,
				azione $txt,
				redirect_a $txt
			)$suff",

			"CREATE TABLE IF NOT EXISTS meta_piano (
				id $pk,
				audit_id INT NOT NULL,
				documento_id INT NOT NULL,
				title_nuovo $txt,
				description_nuova $txt,
				excerpt_nuovo $txt,
				slug_nuovo $vc,
				title_cambiato INT,
				description_cambiata INT,
				slug_cambiato INT
			)$suff",

			"CREATE TABLE IF NOT EXISTS bozza (
				id $pk,
				audit_id INT NOT NULL,
				documento_id INT NOT NULL,
				wp_id $vc,
				stato $vc,
				modello $vc,
				titolo $txt,
				meta_title $txt,
				meta_description $txt,
				in_breve $txt,
				corpo_html $txt,
				faq $txt,
				da_verificare $txt,
				note $txt,
				parole INT,
				token_in INT,
				token_out INT,
				errore $txt,
				creato_il $vc
			)$suff",

			"CREATE TABLE IF NOT EXISTS coda (
				id $pk,
				audit_id INT NOT NULL,
				ordine INT,
				tipo $vc,
				riferimento $vc,
				etichetta $txt,
				stato $vc,
				messaggio $txt,
				creato_il $vc,
				eseguito_il $vc
			)$suff",

			"CREATE TABLE IF NOT EXISTS gsc_rilevazione (
				id $pk,
				sito_url $vc,
				proprieta $vc,
				creato_il $vc,
				periodo_da $vc,
				periodo_a $vc,
				giorni INT,
				clic INT,
				impression INT,
				posizione_media REAL,
				pagine INT,
				query INT
			)$suff",

			"CREATE TABLE IF NOT EXISTS gsc_pagina (
				id $pk,
				rilevazione_id INT NOT NULL,
				url $txt,
				clic INT,
				impression INT,
				ctr REAL,
				posizione REAL
			)$suff",

			"CREATE TABLE IF NOT EXISTS gsc_query (
				id $pk,
				rilevazione_id INT NOT NULL,
				query $txt,
				url $txt,
				clic INT,
				impression INT,
				ctr REAL,
				posizione REAL
			)$suff",

			"CREATE TABLE IF NOT EXISTS gsc_segnale (
				id $pk,
				rilevazione_id INT NOT NULL,
				tipo $vc,
				titolo $vc,
				url $txt,
				query $txt,
				posizione REAL,
				impression INT,
				clic INT,
				priorita INT,
				azione $vc,
				spiegazione $txt,
				dettaglio $txt
			)$suff",

			"CREATE TABLE IF NOT EXISTS gsc_indice (
				id $pk,
				audit_id INT NOT NULL,
				documento_id INT,
				url $txt,
				verdetto $vc,
				copertura $txt,
				canonica_google $txt,
				ultima_scansione $vc,
				chiesto_il $vc
			)$suff",

			"CREATE TABLE IF NOT EXISTS link_piano (
				id $pk,
				audit_id INT NOT NULL,
				da $vc,
				da_titolo $txt,
				a $vc,
				a_titolo $txt,
				anchor $txt,
				motivo $vc,
				punteggio REAL
			)$suff",
		);

		foreach ( $tabelle as $sql ) {
			$this->pdo->exec( $sql );
		}

		// Colonne introdotte dopo il primo rilascio: aggiunte solo se mancanti,
		// così un database esistente continua a funzionare senza reinstallazione.
		$this->aggiungiColonna( 'documento', 'testo', $txt );
		$this->aggiungiColonna( 'documento', 'ha_thumbnail', 'INT' );
		// Un occorrenza applicata sul sito non deve continuare a contare:
		// il conteggio dei pulsanti legge l analisi, che e la fotografia di
		// quel momento, e restava fermo anche dopo aver premuto.
		$this->aggiungiColonna( 'occorrenza', 'applicato', 'INT' );
		$this->aggiungiColonna( 'bozza', 'file', $vc );
		// Quando il testo nuovo e stato scritto sull articolo pubblicato:
		// serve a distinguere nell elenco quelle gia online da quelle ancora
		// da decidere.
		$this->aggiungiColonna( 'bozza', 'inviata_il', $vc );
		// Che cosa la riscrittura non ha chiuso, e quanti tentativi sono
		// serviti. Prima la bozza si salvava comunque e il rilievo rispuntava
		// alla rilettura successiva senza che nessuno sapesse perche.
		$this->aggiungiColonna( 'bozza', 'rimaste', $txt );
		$this->aggiungiColonna( 'bozza', 'tentativi', 'INT' );
		$this->aggiungiColonna( 'gsc_rilevazione', 'sitemap', $txt );
		$this->aggiungiColonna( 'coda', 'dettaglio', $txt );
		$this->aggiungiColonna( 'coda', 'origine', $vc );

		foreach ( array(
			'CREATE INDEX IF NOT EXISTS idx_doc_audit ON documento (audit_id)',
			'CREATE INDEX IF NOT EXISTS idx_ril_audit ON rilievo (audit_id)',
			'CREATE INDEX IF NOT EXISTS idx_occ_ril ON occorrenza (rilievo_id)',
			'CREATE INDEX IF NOT EXISTS idx_tri_audit ON triage (audit_id)',
			'CREATE INDEX IF NOT EXISTS idx_boz_audit ON bozza (audit_id)',
			'CREATE INDEX IF NOT EXISTS idx_coda_audit ON coda (audit_id, stato)',
			'CREATE INDEX IF NOT EXISTS idx_gsc_pag ON gsc_pagina (rilevazione_id)',
			'CREATE INDEX IF NOT EXISTS idx_gsc_qry ON gsc_query (rilevazione_id)',
			'CREATE INDEX IF NOT EXISTS idx_gsc_seg ON gsc_segnale (rilevazione_id, priorita)',
			'CREATE INDEX IF NOT EXISTS idx_gsc_idx ON gsc_indice (audit_id, documento_id)',
		) as $sql ) {
			$this->pdo->exec( $sql );
		}
	}

	/**
	 * Aggiunge una colonna se non esiste già.
	 *
	 * @param string $tabella Tabella.
	 * @param string $colonna Colonna.
	 * @param string $tipo    Tipo SQL.
	 * @return void
	 */
	private function aggiungiColonna( $tabella, $colonna, $tipo ) {
		try {
			if ( 'sqlite' === $this->driver ) {
				$colonne = $this->pdo->query( "PRAGMA table_info($tabella)" )->fetchAll();
				foreach ( $colonne as $c ) {
					if ( $c['name'] === $colonna ) {
						return;
					}
				}
			} else {
				$esiste = $this->pdo->query( "SHOW COLUMNS FROM `$tabella` LIKE " . $this->pdo->quote( $colonna ) )->fetch();
				if ( $esiste ) {
					return;
				}
			}

			$this->pdo->exec( "ALTER TABLE $tabella ADD COLUMN $colonna $tipo" );
		} catch ( \Throwable $e ) {
			// La tabella potrebbe non esistere ancora al primo avvio: nessun problema.
			return;
		}
	}

	/**
	 * Esegue una query preparata.
	 *
	 * @param string $sql    SQL.
	 * @param array  $params Parametri.
	 * @return \PDOStatement
	 */
	public function run( $sql, array $params = array() ) {
		$stmt = $this->pdo->prepare( $sql );
		$stmt->execute( $params );

		return $stmt;
	}

	/**
	 * Tutte le righe di una query.
	 *
	 * @param string $sql    SQL.
	 * @param array  $params Parametri.
	 * @return array
	 */
	public function all( $sql, array $params = array() ) {
		return $this->run( $sql, $params )->fetchAll();
	}

	/**
	 * Prima riga di una query.
	 *
	 * @param string $sql    SQL.
	 * @param array  $params Parametri.
	 * @return array|null
	 */
	public function one( $sql, array $params = array() ) {
		$row = $this->run( $sql, $params )->fetch();

		return false === $row ? null : $row;
	}

	/**
	 * Inserisce una riga e restituisce l'id generato.
	 *
	 * @param string $tabella Nome tabella.
	 * @param array  $dati    Colonne => valori.
	 * @return int
	 */
	public function insert( $tabella, array $dati ) {
		$colonne = array_keys( $dati );
		$segna   = array_map( static fn( $c ) => ':' . $c, $colonne );

		$this->run(
			sprintf( 'INSERT INTO %s (%s) VALUES (%s)', $tabella, implode( ',', $colonne ), implode( ',', $segna ) ),
			array_combine( $segna, array_values( $dati ) )
		);

		return (int) $this->pdo->lastInsertId();
	}

	/**
	 * Inserimento massivo in una transazione: indispensabile con migliaia di righe.
	 *
	 * @param string $tabella Nome tabella.
	 * @param array  $righe   Elenco di array associativi omogenei.
	 * @return int Righe inserite.
	 */
	public function insertMany( $tabella, array $righe ) {
		if ( empty( $righe ) ) {
			return 0;
		}

		$colonne = array_keys( $righe[0] );
		$sql     = sprintf(
			'INSERT INTO %s (%s) VALUES (%s)',
			$tabella,
			implode( ',', $colonne ),
			implode( ',', array_fill( 0, count( $colonne ), '?' ) )
		);

		$stmt = $this->pdo->prepare( $sql );
		$this->pdo->beginTransaction();

		foreach ( $righe as $riga ) {
			$stmt->execute( array_values( $riga ) );
		}

		$this->pdo->commit();

		return count( $righe );
	}
}
