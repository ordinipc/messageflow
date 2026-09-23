<?php
/**
 * Connessione al database e creazione automatica delle tabelle.
 * Driver supportati: mysql (consigliato) e sqlite (ripiego).
 */

defined( 'PC_AVVIO' ) || exit;

$GLOBALS['pc_pdo'] = null;

/** Configurazione di connessione letta da config.php. */
function db_config() {
	static $cfg = null;
	if ( null !== $cfg ) {
		return $cfg;
	}
	$file = PC_RADICE . '/config.php';
	$cfg  = array(
		'driver'    => 'mysql',
		'host'      => 'localhost',
		'porta'     => 3306,
		'nome'      => '',
		'utente'    => '',
		'password'  => '',
		'prefisso'  => 'pc_',
		'charset'   => 'utf8mb4',
		'file'      => PC_DATI . '/portale.sqlite',
	);
	if ( is_readable( $file ) ) {
		$letta = include $file;
		if ( is_array( $letta ) ) {
			$cfg = array_merge( $cfg, $letta );
		}
	}
	return $cfg;
}

/** True se config.php esiste ed è compilato. */
function db_configurato() {
	$cfg = db_config();
	if ( ! is_readable( PC_RADICE . '/config.php' ) ) {
		return false;
	}
	if ( 'sqlite' === $cfg['driver'] ) {
		return true;
	}
	return '' !== $cfg['nome'] && '' !== $cfg['utente'];
}

/** Prefisso tabelle. */
function db_tab( $nome ) {
	$cfg = db_config();
	return $cfg['prefisso'] . $nome;
}

/** Connessione PDO condivisa. Lancia PDOException in caso di errore. */
function db() {
	if ( $GLOBALS['pc_pdo'] instanceof PDO ) {
		return $GLOBALS['pc_pdo'];
	}
	$cfg = db_config();
	if ( 'sqlite' === $cfg['driver'] ) {
		$dir = dirname( $cfg['file'] );
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0755, true );
		}
		$dsn = 'sqlite:' . $cfg['file'];
		$pdo = new PDO( $dsn, null, null, array(
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		) );
		$pdo->exec( 'PRAGMA journal_mode = WAL' );
		$pdo->exec( 'PRAGMA foreign_keys = ON' );
	} else {
		$dsn = sprintf( 'mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], (int) $cfg['porta'], $cfg['nome'], $cfg['charset'] );
		$pdo = new PDO( $dsn, $cfg['utente'], $cfg['password'], array(
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES   => false,
		) );
	}
	$GLOBALS['pc_pdo'] = $pdo;
	return $pdo;
}

/** Esegue una query preparata e restituisce lo statement. */
function db_esegui( $sql, $parametri = array() ) {
	$stm = db()->prepare( $sql );
	$stm->execute( $parametri );
	return $stm;
}

/** Tutte le righe. */
function db_righe( $sql, $parametri = array() ) {
	return db_esegui( $sql, $parametri )->fetchAll();
}

/** Prima riga o null. */
function db_riga( $sql, $parametri = array() ) {
	$r = db_esegui( $sql, $parametri )->fetch();
	return false === $r ? null : $r;
}

/** Primo valore della prima riga. */
function db_valore( $sql, $parametri = array(), $default = null ) {
	$v = db_esegui( $sql, $parametri )->fetchColumn();
	return false === $v ? $default : $v;
}

/** Inserisce o aggiorna una riga identificata da id. */
function db_salva( $tabella, $dati, $chiave = 'id' ) {
	$tab      = db_tab( $tabella );
	$esiste   = db_valore( "SELECT {$chiave} FROM {$tab} WHERE {$chiave} = ?", array( $dati[ $chiave ] ) );
	$colonne  = array_keys( $dati );
	if ( null !== $esiste ) {
		$set = array();
		foreach ( $colonne as $c ) {
			if ( $c === $chiave ) {
				continue;
			}
			$set[] = "{$c} = ?";
		}
		$valori   = array();
		foreach ( $colonne as $c ) {
			if ( $c !== $chiave ) {
				$valori[] = $dati[ $c ];
			}
		}
		$valori[] = $dati[ $chiave ];
		db_esegui( "UPDATE {$tab} SET " . implode( ', ', $set ) . " WHERE {$chiave} = ?", $valori );
	} else {
		$segnaposto = implode( ', ', array_fill( 0, count( $colonne ), '?' ) );
		db_esegui( "INSERT INTO {$tab} (" . implode( ', ', $colonne ) . ") VALUES ({$segnaposto})", array_values( $dati ) );
	}
	return true;
}

/** Elimina righe. */
function db_elimina( $tabella, $dove, $parametri = array() ) {
	$tab = db_tab( $tabella );
	db_esegui( "DELETE FROM {$tab} WHERE {$dove}", $parametri );
	return true;
}

/* ---------------------------------------------------------------------------
 * Schema
 * ------------------------------------------------------------------------- */

/** Adatta i tipi di colonna al driver in uso. */
function db_tipo( $tipo ) {
	$cfg = db_config();
	if ( 'sqlite' !== $cfg['driver'] ) {
		return $tipo;
	}
	$mappa = array(
		'LONGTEXT'     => 'TEXT',
		'MEDIUMTEXT'   => 'TEXT',
		'TINYINT(1)'   => 'INTEGER',
		'INT'          => 'INTEGER',
	);
	$tipo = strtr( $tipo, $mappa );
	return preg_replace( '/VARCHAR\((\d+)\)/i', 'TEXT', $tipo );
}

/** Coda della CREATE TABLE (motore e charset solo su MySQL). */
function db_coda() {
	$cfg = db_config();
	return 'sqlite' === $cfg['driver'] ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=' . $cfg['charset'] . ' COLLATE=' . $cfg['charset'] . '_unicode_ci';
}

/** Crea le tabelle se mancano. Idempotente. */
function db_installa() {
	$t = function ( $x ) { return db_tipo( $x ); };
	$coda = db_coda();

	$sql = array();

	$sql[] = 'CREATE TABLE IF NOT EXISTS ' . db_tab( 'impostazioni' ) . ' (
		chiave ' . $t( 'VARCHAR(64)' ) . ' NOT NULL,
		valore ' . $t( 'LONGTEXT' ) . ' NULL,
		PRIMARY KEY (chiave)
	)' . $coda;

	$sql[] = 'CREATE TABLE IF NOT EXISTS ' . db_tab( 'servizi' ) . ' (
		id ' . $t( 'VARCHAR(16)' ) . ' NOT NULL,
		slug ' . $t( 'VARCHAR(190)' ) . ' NOT NULL,
		nome ' . $t( 'VARCHAR(190)' ) . ' NOT NULL,
		descrizione ' . $t( 'LONGTEXT' ) . ' NULL,
		icona ' . $t( 'VARCHAR(190)' ) . ' NULL,
		ordine ' . $t( 'INT' ) . ' NOT NULL DEFAULT 10,
		PRIMARY KEY (id)
	)' . $coda;

	$sql[] = 'CREATE TABLE IF NOT EXISTS ' . db_tab( 'citta' ) . ' (
		id ' . $t( 'VARCHAR(16)' ) . ' NOT NULL,
		slug ' . $t( 'VARCHAR(190)' ) . ' NOT NULL,
		nome ' . $t( 'VARCHAR(190)' ) . ' NOT NULL,
		provincia ' . $t( 'VARCHAR(10)' ) . ' NULL,
		regione ' . $t( 'VARCHAR(90)' ) . ' NULL,
		cap ' . $t( 'VARCHAR(10)' ) . ' NULL,
		lat ' . $t( 'VARCHAR(32)' ) . ' NULL,
		lng ' . $t( 'VARCHAR(32)' ) . ' NULL,
		telefono ' . $t( 'VARCHAR(60)' ) . ' NULL,
		whatsapp ' . $t( 'VARCHAR(60)' ) . ' NULL,
		email ' . $t( 'VARCHAR(190)' ) . ' NULL,
		piva ' . $t( 'VARCHAR(40)' ) . ' NULL,
		social ' . $t( 'LONGTEXT' ) . ' NULL,
		indirizzo ' . $t( 'VARCHAR(255)' ) . ' NULL,
		mappa ' . $t( 'LONGTEXT' ) . ' NULL,
		orari ' . $t( 'LONGTEXT' ) . ' NULL,
		zone ' . $t( 'LONGTEXT' ) . ' NULL,
		comuni ' . $t( 'LONGTEXT' ) . ' NULL,
		raggiungerci ' . $t( 'LONGTEXT' ) . ' NULL,
		intro ' . $t( 'LONGTEXT' ) . ' NULL,
		perche ' . $t( 'LONGTEXT' ) . ' NULL,
		numeri ' . $t( 'LONGTEXT' ) . ' NULL,
		recensioni ' . $t( 'LONGTEXT' ) . ' NULL,
		team ' . $t( 'LONGTEXT' ) . ' NULL,
		certificazioni ' . $t( 'LONGTEXT' ) . ' NULL,
		css ' . $t( 'LONGTEXT' ) . ' NULL,
		js ' . $t( 'LONGTEXT' ) . ' NULL,
		stato ' . $t( 'VARCHAR(20)' ) . ' NOT NULL DEFAULT \'bozza\',
		aggiornata ' . $t( 'VARCHAR(20)' ) . ' NULL,
		PRIMARY KEY (id)
	)' . $coda;

	$sql[] = 'CREATE TABLE IF NOT EXISTS ' . db_tab( 'pagine' ) . ' (
		id ' . $t( 'VARCHAR(16)' ) . ' NOT NULL,
		citta_id ' . $t( 'VARCHAR(16)' ) . ' NOT NULL,
		servizio_id ' . $t( 'VARCHAR(16)' ) . ' NULL,
		tipo ' . $t( 'VARCHAR(20)' ) . ' NOT NULL DEFAULT \'servizio\',
		slug ' . $t( 'VARCHAR(190)' ) . ' NOT NULL,
		titolo ' . $t( 'VARCHAR(255)' ) . ' NOT NULL,
		h1 ' . $t( 'VARCHAR(255)' ) . ' NULL,
		seo_titolo ' . $t( 'VARCHAR(255)' ) . ' NULL,
		seo_desc ' . $t( 'LONGTEXT' ) . ' NULL,
		immagine ' . $t( 'VARCHAR(255)' ) . ' NULL,
		intro ' . $t( 'LONGTEXT' ) . ' NULL,
		corpo ' . $t( 'LONGTEXT' ) . ' NULL,
		inclusi ' . $t( 'LONGTEXT' ) . ' NULL,
		tag ' . $t( 'LONGTEXT' ) . ' NULL,
		processo ' . $t( 'LONGTEXT' ) . ' NULL,
		prezzo_da ' . $t( 'VARCHAR(40)' ) . ' NULL,
		prezzo_a ' . $t( 'VARCHAR(40)' ) . ' NULL,
		prezzo_note ' . $t( 'LONGTEXT' ) . ' NULL,
		faq ' . $t( 'LONGTEXT' ) . ' NULL,
		html ' . $t( 'LONGTEXT' ) . ' NULL,
		css ' . $t( 'LONGTEXT' ) . ' NULL,
		js ' . $t( 'LONGTEXT' ) . ' NULL,
		sezioni ' . $t( 'LONGTEXT' ) . ' NULL,
		menu_mostra ' . $t( 'TINYINT(1)' ) . ' NOT NULL DEFAULT 1,
		menu_ordine ' . $t( 'INT' ) . ' NOT NULL DEFAULT 10,
		stato ' . $t( 'VARCHAR(20)' ) . ' NOT NULL DEFAULT \'bozza\',
		aggiornata ' . $t( 'VARCHAR(20)' ) . ' NULL,
		PRIMARY KEY (id)
	)' . $coda;

	$sql[] = 'CREATE TABLE IF NOT EXISTS ' . db_tab( 'articoli' ) . ' (
		id ' . $t( 'VARCHAR(16)' ) . ' NOT NULL,
		citta_id ' . $t( 'VARCHAR(16)' ) . ' NOT NULL,
		slug ' . $t( 'VARCHAR(190)' ) . ' NOT NULL,
		titolo ' . $t( 'VARCHAR(255)' ) . ' NOT NULL,
		seo_titolo ' . $t( 'VARCHAR(255)' ) . ' NULL,
		seo_desc ' . $t( 'LONGTEXT' ) . ' NULL,
		estratto ' . $t( 'LONGTEXT' ) . ' NULL,
		corpo ' . $t( 'LONGTEXT' ) . ' NULL,
		immagine ' . $t( 'VARCHAR(255)' ) . ' NULL,
		data ' . $t( 'VARCHAR(20)' ) . ' NULL,
		origine ' . $t( 'VARCHAR(255)' ) . ' NULL,
		stato ' . $t( 'VARCHAR(20)' ) . ' NOT NULL DEFAULT \'bozza\',
		aggiornata ' . $t( 'VARCHAR(20)' ) . ' NULL,
		PRIMARY KEY (id)
	)' . $coda;

	$sql[] = 'CREATE TABLE IF NOT EXISTS ' . db_tab( 'utenti' ) . ' (
		id ' . $t( 'VARCHAR(16)' ) . ' NOT NULL,
		nome ' . $t( 'VARCHAR(60)' ) . ' NOT NULL,
		etichetta ' . $t( 'VARCHAR(190)' ) . ' NULL,
		email ' . $t( 'VARCHAR(190)' ) . ' NULL,
		password_hash ' . $t( 'VARCHAR(255)' ) . ' NOT NULL,
		ruolo ' . $t( 'VARCHAR(20)' ) . ' NOT NULL DEFAULT \'redattore\',
		stato ' . $t( 'VARCHAR(20)' ) . ' NOT NULL DEFAULT \'attivo\',
		creato ' . $t( 'VARCHAR(20)' ) . ' NULL,
		ultimo_accesso ' . $t( 'VARCHAR(20)' ) . ' NULL,
		PRIMARY KEY (id)
	)' . $coda;

	$sql[] = 'CREATE TABLE IF NOT EXISTS ' . db_tab( 'media' ) . ' (
		id ' . $t( 'VARCHAR(16)' ) . ' NOT NULL,
		file ' . $t( 'VARCHAR(255)' ) . ' NOT NULL,
		nome ' . $t( 'VARCHAR(255)' ) . ' NULL,
		alt ' . $t( 'VARCHAR(255)' ) . ' NULL,
		mime ' . $t( 'VARCHAR(90)' ) . ' NULL,
		larghezza ' . $t( 'INT' ) . ' NULL,
		altezza ' . $t( 'INT' ) . ' NULL,
		peso ' . $t( 'INT' ) . ' NULL,
		caricata ' . $t( 'VARCHAR(20)' ) . ' NULL,
		PRIMARY KEY (id)
	)' . $coda;

	foreach ( $sql as $q ) {
		db()->exec( $q );
	}

	$indici = array(
		'CREATE UNIQUE INDEX pc_idx_citta_slug ON ' . db_tab( 'citta' ) . ' (slug)',
		'CREATE INDEX pc_idx_pagine_citta ON ' . db_tab( 'pagine' ) . ' (citta_id)',
		'CREATE UNIQUE INDEX pc_idx_pagine_slug ON ' . db_tab( 'pagine' ) . ' (citta_id, slug)',
		'CREATE UNIQUE INDEX pc_idx_servizi_slug ON ' . db_tab( 'servizi' ) . ' (slug)',
		'CREATE INDEX pc_idx_articoli_citta ON ' . db_tab( 'articoli' ) . ' (citta_id)',
		'CREATE UNIQUE INDEX pc_idx_articoli_slug ON ' . db_tab( 'articoli' ) . ' (citta_id, slug)',
		'CREATE INDEX pc_idx_articoli_origine ON ' . db_tab( 'articoli' ) . ' (origine)',
		'CREATE UNIQUE INDEX pc_idx_utenti_nome ON ' . db_tab( 'utenti' ) . ' (nome)',
	);
	foreach ( $indici as $q ) {
		try {
			db()->exec( $q );
		} catch ( PDOException $ex ) {
			// L'indice esiste già: nessun problema.
			unset( $ex );
		}
	}

	return true;
}

/** True se le tabelle esistono. */
function db_installato() {
	try {
		db_valore( 'SELECT COUNT(*) FROM ' . db_tab( 'impostazioni' ) );
		return true;
	} catch ( PDOException $ex ) {
		unset( $ex );
		return false;
	}
}

/* ---------------------------------------------------------------------------
 * Aggiornamento dello schema
 * ------------------------------------------------------------------------- */

/** Colonne attese in ogni tabella, per chi ha installato una versione prima. */
function db_colonne_attese() {
	return array(
		'pagine'   => array(
			'sezioni' => 'LONGTEXT NULL',
			'tag'     => 'LONGTEXT NULL',
		),
		'citta'    => array(
			'css'    => 'LONGTEXT NULL',
			'js'     => 'LONGTEXT NULL',
			'piva'   => 'VARCHAR(40) NULL',
			'social' => 'LONGTEXT NULL',
		),
		'articoli' => array(),
		'servizi'  => array(
			'ordine' => 'INT NOT NULL DEFAULT 10',
			'icona'  => 'VARCHAR(190) NULL',
		),
		'utenti'   => array(
			'ultimo_accesso' => 'VARCHAR(20) NULL',
		),
	);
}

/** Colonne già presenti in una tabella. */
function db_colonne_di( $tabella ) {
	$cfg  = db_config();
	$tab  = db_tab( $tabella );
	$out  = array();
	try {
		if ( 'sqlite' === $cfg['driver'] ) {
			foreach ( db_righe( 'PRAGMA table_info(' . $tab . ')' ) as $r ) {
				$out[] = strtolower( (string) $r['name'] );
			}
		} else {
			foreach ( db_righe( 'SHOW COLUMNS FROM ' . $tab ) as $r ) {
				$out[] = strtolower( (string) $r['Field'] );
			}
		}
	} catch ( PDOException $ex ) {
		unset( $ex );
	}
	return $out;
}

/**
 * Aggiunge le colonne mancanti alle tabelle già esistenti.
 *
 * CREATE TABLE IF NOT EXISTS non tocca una tabella che c'è già: senza
 * questo passaggio chi aggiorna il portale si ritroverebbe con le
 * funzioni nuove e le colonne vecchie.
 *
 * @return array Elenco delle colonne aggiunte.
 */
function db_aggiorna() {
	$aggiunte = array();

	foreach ( db_colonne_attese() as $tabella => $colonne ) {
		if ( empty( $colonne ) ) {
			continue;
		}
		$presenti = db_colonne_di( $tabella );
		if ( empty( $presenti ) ) {
			continue;
		}
		foreach ( $colonne as $nome => $tipo ) {
			if ( in_array( strtolower( $nome ), $presenti, true ) ) {
				continue;
			}
			try {
				db()->exec( 'ALTER TABLE ' . db_tab( $tabella ) . ' ADD COLUMN ' . $nome . ' ' . db_tipo( $tipo ) );
				$aggiunte[] = $tabella . '.' . $nome;
			} catch ( PDOException $ex ) {
				unset( $ex );
			}
		}
	}

	return $aggiunte;
}
