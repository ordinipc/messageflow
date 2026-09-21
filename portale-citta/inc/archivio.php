<?php
/**
 * Archivio: lettura e scrittura dei contenuti sul database.
 * I campi ripetibili (orari, FAQ, recensioni…) sono serializzati in JSON.
 */

defined( 'PC_AVVIO' ) || exit;

require_once __DIR__ . '/db.php';

/** Colonne che contengono JSON. */
function campi_json( $entita ) {
	$mappa = array(
		'citta'  => array( 'orari', 'numeri', 'recensioni', 'team' ),
		'pagine' => array( 'processo', 'faq' ),
	);
	return isset( $mappa[ $entita ] ) ? $mappa[ $entita ] : array();
}

/** Riga del database → array PHP. */
function decodifica( $entita, $riga ) {
	if ( ! is_array( $riga ) ) {
		return $riga;
	}
	foreach ( campi_json( $entita ) as $campo ) {
		$valore = isset( $riga[ $campo ] ) ? $riga[ $campo ] : '';
		if ( is_array( $valore ) ) {
			continue;
		}
		$decodificato   = json_decode( (string) $valore, true );
		$riga[ $campo ] = is_array( $decodificato ) ? $decodificato : array();
	}
	return $riga;
}

/** Array PHP → riga del database. */
function codifica( $entita, $dati ) {
	foreach ( campi_json( $entita ) as $campo ) {
		if ( isset( $dati[ $campo ] ) && is_array( $dati[ $campo ] ) ) {
			$dati[ $campo ] = json_encode( $dati[ $campo ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
	}
	return $dati;
}

/* ---------------------------------------------------------------------------
 * Impostazioni globali
 * ------------------------------------------------------------------------- */

function impostazioni_predefinite() {
	return array(
		'brand'           => 'Il mio sito',
		'sito_url'        => '',
		'logo'            => '',
		'favicon'         => '',
		'colore_accento'  => '#ffd400',
		'colore_scuro'    => '#0b0b0b',
		'colore_testo'    => '#111111',
		'colore_chiaro'   => '#f6f6f6',
		'raggio'          => '10px',
		'stile_sezioni'   => 'card',
		'effetti'         => '1',
		'telefono'        => '',
		'whatsapp'        => '',
		'email'           => '',
		'piva'            => '',
		'nazione'         => 'IT',
		'lingua'          => 'it-IT',
		'seo_suffisso'    => '',
		'ga_id'           => '',
		'gemini_key'      => '',
		'gemini_modello'  => 'gemini-3.6-flash',
		'gemini_modello_immagini' => 'gemini-3.1-flash-image',
		'indicizza'       => '1',
		'css_globale'     => '',
		'js_globale'      => '',
		'password_hash'   => '',
		'privacy_url'     => '',
		'cookie_url'      => '',
		'sito_principale' => '',
	);
}

function impostazioni() {
	if ( isset( $GLOBALS['pc_impostazioni'] ) && is_array( $GLOBALS['pc_impostazioni'] ) ) {
		return $GLOBALS['pc_impostazioni'];
	}
	$valori = array();
	try {
		foreach ( db_righe( 'SELECT chiave, valore FROM ' . db_tab( 'impostazioni' ) ) as $r ) {
			$valori[ $r['chiave'] ] = $r['valore'];
		}
	} catch ( PDOException $ex ) {
		unset( $ex );
	}
	$GLOBALS['pc_impostazioni'] = array_merge( impostazioni_predefinite(), $valori );
	return $GLOBALS['pc_impostazioni'];
}

function impostazioni_salva( $nuove ) {
	foreach ( $nuove as $chiave => $valore ) {
		db_salva( 'impostazioni', array( 'chiave' => (string) $chiave, 'valore' => (string) $valore ), 'chiave' );
	}
	unset( $GLOBALS['pc_impostazioni'] );
	return true;
}

function impostazione( $chiave, $default = '' ) {
	$imp = impostazioni();
	return isset( $imp[ $chiave ] ) && '' !== $imp[ $chiave ] ? $imp[ $chiave ] : $default;
}

/* ---------------------------------------------------------------------------
 * Servizi
 * ------------------------------------------------------------------------- */

function servizi() {
	return db_righe( 'SELECT * FROM ' . db_tab( 'servizi' ) . ' ORDER BY ordine ASC, nome ASC' );
}

function servizio( $id ) {
	return db_riga( 'SELECT * FROM ' . db_tab( 'servizi' ) . ' WHERE id = ?', array( $id ) );
}

function servizio_per_slug( $slug ) {
	return db_riga( 'SELECT * FROM ' . db_tab( 'servizi' ) . ' WHERE slug = ?', array( $slug ) );
}

function servizio_salva( $dati ) {
	$base = array( 'id' => '', 'slug' => '', 'nome' => '', 'descrizione' => '', 'icona' => '', 'ordine' => 10 );
	return db_salva( 'servizi', array_merge( $base, array_intersect_key( $dati, $base ) ) );
}

/** Slug libero per un tipo di servizio: aggiunge -2, -3… se già usato. */
function servizio_slug_libero( $slug, $escludi_id = '' ) {
	$slug = slugifica( $slug );
	if ( '' === $slug ) {
		$slug = 'servizio';
	}
	$base = $slug;
	$n    = 1;
	while ( true ) {
		$usato = db_valore(
			'SELECT id FROM ' . db_tab( 'servizi' ) . ' WHERE slug = ? AND id <> ?',
			array( $slug, (string) $escludi_id )
		);
		if ( null === $usato ) {
			return $slug;
		}
		$n++;
		$slug = $base . '-' . $n;
	}
}

function servizio_elimina( $id ) {
	return db_elimina( 'servizi', 'id = ?', array( $id ) );
}

/* ---------------------------------------------------------------------------
 * Città
 * ------------------------------------------------------------------------- */

function citta_predefinita() {
	return array(
		'id'             => '',
		'slug'           => '',
		'nome'           => '',
		'provincia'      => '',
		'regione'        => '',
		'cap'            => '',
		'lat'            => '',
		'lng'            => '',
		'telefono'       => '',
		'whatsapp'       => '',
		'email'          => '',
		'indirizzo'      => '',
		'mappa'          => '',
		'orari'          => array(),
		'zone'           => '',
		'comuni'         => '',
		'raggiungerci'   => '',
		'intro'          => '',
		'perche'         => '',
		'numeri'         => array(),
		'recensioni'     => array(),
		'team'           => array(),
		'certificazioni' => '',
		'css'            => '',
		'js'             => '',
		'stato'          => 'bozza',
		'aggiornata'     => '',
	);
}

function citta_tutte( $solo_pubblicate = false ) {
	$sql = 'SELECT * FROM ' . db_tab( 'citta' );
	if ( $solo_pubblicate ) {
		$sql .= " WHERE stato = 'pubblicata'";
	}
	$sql .= ' ORDER BY nome ASC';
	$out = array();
	foreach ( db_righe( $sql ) as $r ) {
		$out[] = array_merge( citta_predefinita(), decodifica( 'citta', $r ) );
	}
	return $out;
}

function citta_per_slug( $slug ) {
	$r = db_riga( 'SELECT * FROM ' . db_tab( 'citta' ) . ' WHERE slug = ?', array( $slug ) );
	return $r ? array_merge( citta_predefinita(), decodifica( 'citta', $r ) ) : null;
}

function citta_per_id( $id ) {
	$r = db_riga( 'SELECT * FROM ' . db_tab( 'citta' ) . ' WHERE id = ?', array( $id ) );
	return $r ? array_merge( citta_predefinita(), decodifica( 'citta', $r ) ) : null;
}

function citta_salva( $dati ) {
	$base               = citta_predefinita();
	$dati               = array_merge( $base, array_intersect_key( $dati, $base ) );
	$dati['aggiornata'] = oggi();
	return db_salva( 'citta', codifica( 'citta', $dati ) );
}

function citta_elimina( $id ) {
	db_elimina( 'pagine', 'citta_id = ?', array( $id ) );
	return db_elimina( 'citta', 'id = ?', array( $id ) );
}

/** Slug riservati che non possono essere usati da una città. */
function slug_riservati() {
	return array( 'admin', 'media', 'dati', 'inc', 'tema', 'sitemap', 'robots', 'config', 'install', 'index' );
}

function citta_slug_libero( $slug, $escludi_id = '' ) {
	$slug = slugifica( $slug );
	if ( '' === $slug ) {
		$slug = 'citta';
	}
	$base = $slug;
	$n    = 1;
	while ( true ) {
		$usato = db_valore(
			'SELECT id FROM ' . db_tab( 'citta' ) . ' WHERE slug = ? AND id <> ?',
			array( $slug, (string) $escludi_id )
		);
		if ( null === $usato && ! in_array( $slug, slug_riservati(), true ) ) {
			return $slug;
		}
		$n++;
		$slug = $base . '-' . $n;
	}
}

/* ---------------------------------------------------------------------------
 * Pagine
 * ------------------------------------------------------------------------- */

function pagina_predefinita() {
	return array(
		'id'          => '',
		'citta_id'    => '',
		'servizio_id' => '',
		'tipo'        => 'servizio',
		'slug'        => '',
		'titolo'      => '',
		'h1'          => '',
		'seo_titolo'  => '',
		'seo_desc'    => '',
		'immagine'    => '',
		'intro'       => '',
		'corpo'       => '',
		'inclusi'     => '',
		'processo'    => array(),
		'prezzo_da'   => '',
		'prezzo_a'    => '',
		'prezzo_note' => '',
		'faq'         => array(),
		'html'        => '',
		'css'         => '',
		'js'          => '',
		'menu_mostra' => 1,
		'menu_ordine' => 10,
		'stato'       => 'bozza',
		'aggiornata'  => '',
	);
}

function pagine_tutte() {
	$out = array();
	foreach ( db_righe( 'SELECT * FROM ' . db_tab( 'pagine' ) . ' ORDER BY menu_ordine ASC, titolo ASC' ) as $r ) {
		$out[] = array_merge( pagina_predefinita(), decodifica( 'pagine', $r ) );
	}
	return $out;
}

function pagine_di_citta( $citta_id, $solo_pubblicate = false ) {
	$sql = 'SELECT * FROM ' . db_tab( 'pagine' ) . ' WHERE citta_id = ?';
	if ( $solo_pubblicate ) {
		$sql .= " AND stato = 'pubblicata'";
	}
	$sql .= ' ORDER BY menu_ordine ASC, titolo ASC';
	$out = array();
	foreach ( db_righe( $sql, array( $citta_id ) ) as $r ) {
		$out[] = array_merge( pagina_predefinita(), decodifica( 'pagine', $r ) );
	}
	return $out;
}

function pagina_per_id( $id ) {
	$r = db_riga( 'SELECT * FROM ' . db_tab( 'pagine' ) . ' WHERE id = ?', array( $id ) );
	return $r ? array_merge( pagina_predefinita(), decodifica( 'pagine', $r ) ) : null;
}

function pagina_per_slug( $citta_id, $slug ) {
	$r = db_riga( 'SELECT * FROM ' . db_tab( 'pagine' ) . ' WHERE citta_id = ? AND slug = ?', array( $citta_id, $slug ) );
	return $r ? array_merge( pagina_predefinita(), decodifica( 'pagine', $r ) ) : null;
}

function pagina_home( $citta_id ) {
	$r = db_riga( 'SELECT * FROM ' . db_tab( 'pagine' ) . " WHERE citta_id = ? AND tipo = 'home'", array( $citta_id ) );
	return $r ? array_merge( pagina_predefinita(), decodifica( 'pagine', $r ) ) : null;
}

function pagina_salva( $dati ) {
	$base               = pagina_predefinita();
	$dati               = array_merge( $base, array_intersect_key( $dati, $base ) );
	$dati['aggiornata'] = oggi();
	$dati['menu_mostra'] = (int) $dati['menu_mostra'];
	$dati['menu_ordine'] = (int) $dati['menu_ordine'];
	return db_salva( 'pagine', codifica( 'pagine', $dati ) );
}

function pagina_elimina( $id ) {
	return db_elimina( 'pagine', 'id = ?', array( $id ) );
}

function pagina_slug_libero( $slug, $citta_id, $escludi_id = '' ) {
	$slug = slugifica( $slug );
	if ( '' === $slug ) {
		$slug = 'pagina';
	}
	$base = $slug;
	$n    = 1;
	while ( true ) {
		$usato = db_valore(
			'SELECT id FROM ' . db_tab( 'pagine' ) . ' WHERE citta_id = ? AND slug = ? AND id <> ?',
			array( $citta_id, $slug, (string) $escludi_id )
		);
		if ( null === $usato ) {
			return $slug;
		}
		$n++;
		$slug = $base . '-' . $n;
	}
}

/** Conteggi per la dashboard. */
function statistiche() {
	return array(
		'citta'            => (int) db_valore( 'SELECT COUNT(*) FROM ' . db_tab( 'citta' ), array(), 0 ),
		'citta_pubblicate' => (int) db_valore( 'SELECT COUNT(*) FROM ' . db_tab( 'citta' ) . " WHERE stato = 'pubblicata'", array(), 0 ),
		'pagine'           => (int) db_valore( 'SELECT COUNT(*) FROM ' . db_tab( 'pagine' ), array(), 0 ),
		'pagine_pubblicate'=> (int) db_valore( 'SELECT COUNT(*) FROM ' . db_tab( 'pagine' ) . " WHERE stato = 'pubblicata'", array(), 0 ),
		'servizi'          => (int) db_valore( 'SELECT COUNT(*) FROM ' . db_tab( 'servizi' ), array(), 0 ),
		'media'            => (int) db_valore( 'SELECT COUNT(*) FROM ' . db_tab( 'media' ), array(), 0 ),
	);
}
