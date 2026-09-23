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
		'citta'  => array( 'orari', 'numeri', 'recensioni', 'team', 'social' ),
		'pagine' => array( 'processo', 'faq', 'sezioni' ),
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
		'stile_tema'      => 'vetrina',
		'larghezza'       => '1440px',
		'logo_altezza'    => '56',
		'effetti'         => '1',
		'telefono_etichetta' => 'Assistenza 24h',
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
		'ia_consenti'     => '1',
		'css_globale'     => '',
		'js_globale'      => '',
		'password_hash'   => '',
		'privacy_url'     => '',
		'cookie_url'      => '',
		'sito_principale' => '',

		// Home del portale: l'elenco delle città.
		'home_soprattitolo'  => '',
		'home_titolo'        => 'Dove *operiamo*',
		'home_intro'         => 'Scegli la città più vicina a te: trovi contatti, orari, zone servite e i servizi disponibili.',
		'home_immagine'      => '',
		'home_testo'         => '',
		'home_html'          => '',
		'home_elenco_titolo' => 'Le nostre città',
		'home_elenco_occhio' => 'Copertura',
		'home_sotto_elenco'  => '',
		'home_seo_titolo'    => '',
		'home_seo_desc'      => '',

		// Piè di pagina.
		'piede_testo'        => '',
		'piede_citta_titolo' => 'Dove operiamo',
		'piede_link_titolo'  => 'Sito',
		'piede_link'         => '',
		'piede_copy'         => '',
		'piede_social_titolo' => 'Social',
		'social_facebook'    => '',
		'social_instagram'   => '',
		'social_x'           => '',
		'social_youtube'     => '',
		'social_linkedin'    => '',
		'social_tiktok'      => '',
		'social_whatsapp'    => '',
	);
}

/** I social configurati, nell'ordine in cui vanno mostrati. */
function social_disponibili() {
	return array(
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'x'         => 'X (Twitter)',
		'youtube'   => 'YouTube',
		'tiktok'    => 'TikTok',
		'linkedin'  => 'LinkedIn',
		'whatsapp'  => 'WhatsApp',
	);
}

/**
 * Solo i social con un indirizzo scritto.
 *
 * Il controllo è rete per rete, come per telefono ed email: se una città
 * ha la sua pagina Facebook ma non un suo Instagram, esce il Facebook
 * della città accanto all'Instagram del marchio. L'alternativa — o tutti
 * della città o tutti generali — farebbe sparire profili che esistono.
 */
function social_attivi( $citta = null ) {
	$della_citta = ( is_array( $citta ) && isset( $citta['social'] ) ) ? (array) $citta['social'] : array();

	$voci = array();
	foreach ( social_disponibili() as $chiave => $nome ) {
		$url = isset( $della_citta[ $chiave ] ) ? trim( (string) $della_citta[ $chiave ] ) : '';
		if ( vuoto( $url ) ) {
			$url = impostazione( 'social_' . $chiave, '' );
		}
		if ( ! vuoto( $url ) ) {
			$voci[ $chiave ] = array( 'nome' => $nome, 'url' => $url );
		}
	}
	return $voci;
}

/**
 * La partita IVA da mostrare.
 *
 * Quella della città se c'è, altrimenti quella generale delle
 * impostazioni: chi ha una sola società la scrive una volta sola, chi ha
 * una società per città la scrive su ognuna.
 */
function piva_da_mostrare( $citta = null ) {
	if ( is_array( $citta ) && ! vuoto( isset( $citta['piva'] ) ? $citta['piva'] : '' ) ) {
		return trim( (string) $citta['piva'] );
	}
	return trim( (string) impostazione( 'piva', '' ) );
}

/** Altezza del logo in barra, in pixel, dentro limiti ragionevoli. */
function altezza_logo() {
	$px = (int) impostazione( 'logo_altezza', '56' );
	return (string) max( 24, min( 140, 0 === $px ? 56 : $px ) );
}

/**
 * Larghezza del contenuto, in una forma che il CSS accetta.
 *
 * Header, testo e piè di pagina la usano tutti: è quella che decide
 * quanto il portale respira sugli schermi grandi.
 */
function larghezza_contenuto() {
	$valore = trim( (string) impostazione( 'larghezza', '1440px' ) );
	if ( ! preg_match( '/^\d{3,4}(px|%)?$/', $valore ) ) {
		return '1440px';
	}
	return preg_match( '/(px|%)$/', $valore ) ? $valore : $valore . 'px';
}

/**
 * Titolo con una parte evidenziata.
 *
 * Fra asterischi si scrive la parola in giallo: "Dove *operiamo*". Senza
 * asterischi il titolo esce tutto dello stesso colore.
 */
function titolo_evidenziato( $testo ) {
	$pezzi = preg_split( '/\*([^*]+)\*/u', (string) $testo, -1, PREG_SPLIT_DELIM_CAPTURE );
	$fuori = '';
	foreach ( $pezzi as $i => $pezzo ) {
		$fuori .= ( 1 === $i % 2 ) ? '<span>' . e( $pezzo ) . '</span>' : e( $pezzo );
	}
	return $fuori;
}

/**
 * Righe "Etichetta | indirizzo" trasformate in collegamenti.
 *
 * Senza la barra verticale si prende la riga intera come etichetta e come
 * indirizzo: così un indirizzo scritto da solo funziona lo stesso.
 */
function link_da_righe( $testo ) {
	$voci = array();
	foreach ( righe( $testo ) as $riga ) {
		$parti = array_map( 'trim', explode( '|', $riga, 2 ) );
		$url   = isset( $parti[1] ) && '' !== $parti[1] ? $parti[1] : $parti[0];
		if ( '' === $parti[0] || '' === $url ) {
			continue;
		}
		$voci[] = array( 'nome' => $parti[0], 'url' => $url );
	}
	return $voci;
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
		'piva'           => '',
		'social'         => array(),
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
	db_elimina( 'articoli', 'citta_id = ?', array( $id ) );
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
		'tag'         => '',
		'sezioni'     => array(),
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
		'articoli'         => (int) db_valore( 'SELECT COUNT(*) FROM ' . db_tab( 'articoli' ), array(), 0 ),
		'articoli_pubblicati' => (int) db_valore( 'SELECT COUNT(*) FROM ' . db_tab( 'articoli' ) . " WHERE stato = 'pubblicato'", array(), 0 ),
	);
}

/* ---------------------------------------------------------------------------
 * Articoli del blog
 * ------------------------------------------------------------------------- */

function articolo_predefinito() {
	return array(
		'id'         => '',
		'citta_id'   => '',
		'slug'       => '',
		'titolo'     => '',
		'seo_titolo' => '',
		'seo_desc'   => '',
		'estratto'   => '',
		'corpo'      => '',
		'immagine'   => '',
		'data'       => '',
		'origine'    => '',
		'stato'      => 'bozza',
		'aggiornata' => '',
	);
}

/** Articoli di una città, dal più recente. */
function articoli_di_citta( $citta_id, $solo_pubblicati = false, $limite = 0, $salta = 0 ) {
	$sql = 'SELECT * FROM ' . db_tab( 'articoli' ) . ' WHERE citta_id = ?';
	if ( $solo_pubblicati ) {
		$sql .= " AND stato = 'pubblicato'";
	}
	$sql .= ' ORDER BY data DESC, titolo ASC';
	if ( $limite > 0 ) {
		$sql .= ' LIMIT ' . (int) $limite . ' OFFSET ' . (int) $salta;
	}
	$out = array();
	foreach ( db_righe( $sql, array( $citta_id ) ) as $r ) {
		$out[] = array_merge( articolo_predefinito(), $r );
	}
	return $out;
}

function articoli_conta( $citta_id, $solo_pubblicati = false ) {
	$sql = 'SELECT COUNT(*) FROM ' . db_tab( 'articoli' ) . ' WHERE citta_id = ?';
	if ( $solo_pubblicati ) {
		$sql .= " AND stato = 'pubblicato'";
	}
	return (int) db_valore( $sql, array( $citta_id ), 0 );
}

function articolo_per_id( $id ) {
	$r = db_riga( 'SELECT * FROM ' . db_tab( 'articoli' ) . ' WHERE id = ?', array( $id ) );
	return $r ? array_merge( articolo_predefinito(), $r ) : null;
}

function articolo_per_slug( $citta_id, $slug ) {
	$r = db_riga( 'SELECT * FROM ' . db_tab( 'articoli' ) . ' WHERE citta_id = ? AND slug = ?', array( $citta_id, $slug ) );
	return $r ? array_merge( articolo_predefinito(), $r ) : null;
}

/** True se l'indirizzo di origine è già stato importato. */
function articolo_gia_importato( $origine ) {
	if ( vuoto( $origine ) ) {
		return false;
	}
	return null !== db_valore( 'SELECT id FROM ' . db_tab( 'articoli' ) . ' WHERE origine = ?', array( $origine ) );
}

function articolo_salva( $dati ) {
	$base               = articolo_predefinito();
	$dati               = array_merge( $base, array_intersect_key( $dati, $base ) );
	$dati['aggiornata'] = oggi();
	return db_salva( 'articoli', $dati );
}

function articolo_elimina( $id ) {
	return db_elimina( 'articoli', 'id = ?', array( $id ) );
}

function articolo_slug_libero( $slug, $citta_id, $escludi_id = '' ) {
	$slug = slugifica( $slug );
	if ( '' === $slug ) {
		$slug = 'articolo';
	}
	$base = $slug;
	$n    = 1;
	while ( true ) {
		$usato = db_valore(
			'SELECT id FROM ' . db_tab( 'articoli' ) . ' WHERE citta_id = ? AND slug = ? AND id <> ?',
			array( $citta_id, $slug, (string) $escludi_id )
		);
		if ( null === $usato ) {
			return $slug;
		}
		$n++;
		$slug = $base . '-' . $n;
	}
}

/** La pagina di tipo blog di una città, se esiste. */
function pagina_blog( $citta_id ) {
	$r = db_riga( 'SELECT * FROM ' . db_tab( 'pagine' ) . " WHERE citta_id = ? AND tipo = 'blog'", array( $citta_id ) );
	return $r ? array_merge( pagina_predefinita(), decodifica( 'pagine', $r ) ) : null;
}

/* ---------------------------------------------------------------------------
 * Quali sezioni mostra una pagina
 * ------------------------------------------------------------------------- */

/** Tutte le sezioni attivabili, con etichetta e spiegazione. */
function sezioni_disponibili( $tipo = '' ) {
	// L'ordine di questo elenco è quello con cui le sezioni uscivano prima
	// che si potessero spostare: serve da ordine di riferimento.
	$blocchi = array(
		'testo' => array( 'Testo di approfondimento', 'Il testo lungo scritto in questa pagina' ),
		'html'  => array( 'HTML libero', 'Il codice scritto nella scheda "Codice"' ),
	);
	if ( 'servizi' === $tipo ) {
		$blocchi['elenco'] = array( 'Elenco dei servizi', 'Tutte le pagine servizio della città' );
	}
	if ( 'blog' === $tipo ) {
		$blocchi['elenco'] = array( 'Elenco degli articoli', 'Gli articoli del blog di questa città' );
	}

	return $blocchi + array(
		'inclusi'   => array( 'Cosa comprende', 'Le voci scritte in questa pagina' ),
		'perche'    => array( 'Perché sceglierci + numeri', 'Dai dati della città' ),
		'processo'  => array( 'Come funziona', 'I passi scritti in questa pagina' ),
		'prezzi'    => array( 'Prezzi', 'Il prezzo scritto in questa pagina' ),
		'zone'      => array( 'Zone servite', 'Dai dati della città' ),
		'recensioni'=> array( 'Recensioni', 'Dai dati della città' ),
		'team'      => array( 'Team e certificazioni', 'Dai dati della città' ),
		'dove'      => array( 'Dove siamo e mappa', 'Dai dati della città' ),
		'orari'     => array( 'Orari di apertura', 'Dai dati della città' ),
		'faq'       => array( 'Domande frequenti', 'Le FAQ di questa pagina' ),
		'recapiti'  => array( 'Recapiti completi', 'Telefono, WhatsApp, email, indirizzo, P. IVA' ),
		'modulo'    => array( 'Modulo di contatto', 'Il visitatore scrive e tu ricevi una email' ),
		'cta'       => array( 'Chiamata all\'azione', 'Il riquadro nero con i pulsanti' ),
		'servizi'   => array( 'Altri servizi', 'Le altre pagine servizio della città' ),
		'correlate' => array( 'Altre città', 'I collegamenti alle altre città' ),
	);
}

/** I blocchi che ci sono sempre, a meno che non li si tolga apposta. */
function sezioni_di_base() {
	return array( 'testo', 'html', 'elenco' );
}

/**
 * Sezioni proposte per un tipo di pagina.
 *
 * Servono a non far uscire tutte le pagine uguali: una pagina servizio
 * parla del servizio, i contatti parlano di come raggiungerti, e il resto
 * non si ripete ovunque.
 */
function sezioni_predefinite( $tipo, $slug = '' ) {
	$slug = slugifica( (string) $slug );

	if ( 'home' === $tipo ) {
		// Le FAQ ci sono anche qui: è la pagina che gli assistenti IA
		// leggono per prima, e le domande sono quello che citano.
		return array( 'perche', 'zone', 'faq', 'recensioni', 'dove', 'orari', 'cta', 'servizi', 'correlate' );
	}
	if ( 'servizio' === $tipo ) {
		return array( 'inclusi', 'processo', 'prezzi', 'faq', 'perche', 'cta', 'servizi' );
	}
	if ( 'servizi' === $tipo ) {
		return array( 'perche', 'faq', 'cta', 'correlate' );
	}
	if ( 'blog' === $tipo ) {
		return array( 'cta' );
	}

	// Pagine fisse: si va a naso sullo slug, ed è comunque modificabile.
	$per_slug = array(
		'contatti'          => array( 'recapiti', 'modulo', 'dove', 'orari' ),
		'chi-siamo'         => array( 'team', 'perche', 'cta' ),
		'domande-frequenti' => array( 'faq', 'cta' ),
		'zone-servite'      => array( 'zone', 'dove', 'cta' ),
		'recensioni'        => array( 'recensioni', 'cta' ),
		'orari'             => array( 'orari', 'dove', 'recapiti' ),
	);
	if ( isset( $per_slug[ $slug ] ) ) {
		return $per_slug[ $slug ];
	}
	return array( 'faq', 'cta' );
}

/** True se la pagina deve mostrare quella sezione. */
/**
 * Le sezioni di una pagina, nell'ordine in cui vanno stampate.
 *
 * L'ordine è quello in cui sono salvate: la prima della lista è la prima
 * che si vede. Chi non ha mai toccato l'ordine ritrova quello di sempre,
 * perché i blocchi di testo vengono rimessi in testa.
 */
function pagina_sezioni( $pagina ) {
	$tipo   = isset( $pagina['tipo'] ) ? (string) $pagina['tipo'] : '';
	$scelte = isset( $pagina['sezioni'] ) ? (array) $pagina['sezioni'] : array();

	// Il segnaposto dice che l'ordine l'ha deciso qualcuno: senza di lui la
	// pagina è stata salvata prima che l'ordine si potesse cambiare.
	$deciso = in_array( PC_ORDINE_DECISO, $scelte, true );

	if ( empty( $scelte ) ) {
		// Pagina mai salvata con le nuove opzioni: si usa il criterio del tipo.
		$scelte = sezioni_predefinite( $tipo, isset( $pagina['slug'] ) ? $pagina['slug'] : '' );
	}

	// array_intersect tiene l'ordine del primo argomento: è quello che serve.
	$ammesse = array_keys( sezioni_disponibili( $tipo ) );
	$scelte  = array_values( array_intersect( $scelte, $ammesse ) );

	if ( ! $deciso ) {
		// Nessuno ha mai toccato l'ordine: si usa quello di riferimento, che
		// è come la pagina usciva prima. Così aggiornare il portale non
		// rimescola le pagine già online, e i blocchi di testo restano.
		$attive = array_flip( $scelte );
		$base   = sezioni_di_base();
		$scelte = array();
		foreach ( $ammesse as $chiave ) {
			if ( isset( $attive[ $chiave ] ) || in_array( $chiave, $base, true ) ) {
				$scelte[] = $chiave;
			}
		}
	}

	return $scelte;
}

/**
 * Applica uno spostamento all'elenco delle sezioni.
 *
 * Il comando arriva dai pulsanti della schermata ed è nella forma
 * "su:faq", "giu:faq", "fuori:faq", "dentro:faq". Serve a far funzionare
 * la schermata anche senza JavaScript.
 */
function sezioni_sposta( $scelte, $comando, $ammesse ) {
	$scelte = array_values( (array) $scelte );
	list( $cosa, $chiave ) = array_pad( explode( ':', (string) $comando, 2 ), 2, '' );

	if ( ! in_array( $chiave, (array) $ammesse, true ) ) {
		return $scelte;
	}
	$posizione = array_search( $chiave, $scelte, true );

	if ( 'dentro' === $cosa ) {
		if ( false === $posizione ) {
			$scelte[] = $chiave;
		}
		return $scelte;
	}
	if ( 'fuori' === $cosa ) {
		if ( false !== $posizione ) {
			array_splice( $scelte, (int) $posizione, 1 );
		}
		return $scelte;
	}
	if ( false === $posizione ) {
		return $scelte;
	}

	$vicina = 'su' === $cosa ? (int) $posizione - 1 : (int) $posizione + 1;
	if ( isset( $scelte[ $vicina ] ) ) {
		$appoggio            = $scelte[ $vicina ];
		$scelte[ $vicina ]   = $scelte[ $posizione ];
		$scelte[ $posizione ] = $appoggio;
	}
	return $scelte;
}

/** True se la pagina mostra una certa sezione. */
function pagina_mostra( $pagina, $chiave ) {
	return in_array( $chiave, pagina_sezioni( $pagina ), true );
}

/**
 * Infila una sezione dentro un elenco già scelto, al posto giusto.
 *
 * Il posto giusto è quello dell'ordine di riferimento: la sezione entra
 * prima della prima che nell'elenco di riferimento viene dopo di lei.
 * Quello che c'era non si sposta, e il segnaposto dell'ordine deciso
 * resta in testa perché nel riferimento non compare.
 */
function sezioni_inserisci( $scelte, $chiave, $riferimento ) {
	if ( in_array( $chiave, $scelte, true ) ) {
		return $scelte;
	}
	$posto = array_search( $chiave, $riferimento, true );
	if ( false === $posto ) {
		return $scelte;
	}

	$fuori = array();
	$messa = false;
	foreach ( $scelte as $sezione ) {
		$dove = array_search( $sezione, $riferimento, true );
		if ( ! $messa && false !== $dove && $dove > $posto ) {
			$fuori[] = $chiave;
			$messa   = true;
		}
		$fuori[] = $sezione;
	}
	if ( ! $messa ) {
		$fuori[] = $chiave;
	}
	return $fuori;
}

/**
 * Aggiunge la sezione FAQ alle pagine già salvate. Passa una volta sola.
 *
 * Le sezioni predefinite valgono solo per le pagine nuove: quelle già
 * salvate portano scritto nel database l'elenco esatto scelto allora, e
 * un aggiornamento del portale non deve rimescolarle. Le FAQ però sono
 * il pezzo che gli assistenti IA citano, e le pagine principali delle
 * città create prima non ce l'hanno.
 *
 * Tocca solo i tipi dove la sezione è arrivata adesso, non sposta niente
 * di quello che c'era e non cambia la data di aggiornamento della
 * pagina. Chi l'aveva tolta apposta se la ritrova: «tolta» e «mai
 * avuta» nel database si scrivono nello stesso modo. Resta comunque
 * invisibile finché quella pagina non ha domande scritte.
 *
 * @return int Quante pagine sono state toccate.
 */
function sezioni_migra_faq() {
	if ( '1' === (string) impostazione( 'migrazione_faq', '' ) ) {
		return 0;
	}

	$fatte = 0;
	foreach ( pagine_tutte() as $pagina ) {
		$tipo = isset( $pagina['tipo'] ) ? (string) $pagina['tipo'] : '';
		if ( 'home' !== $tipo && 'servizi' !== $tipo ) {
			continue;
		}
		$scelte = (array) $pagina['sezioni'];
		// Elenco vuoto: la pagina prende già le predefinite, e adesso le
		// predefinite le FAQ ce l'hanno. Niente da scrivere.
		if ( empty( $scelte ) || in_array( 'faq', $scelte, true ) ) {
			continue;
		}

		$nuove = sezioni_inserisci( $scelte, 'faq', array_keys( sezioni_disponibili( $tipo ) ) );
		db_esegui(
			'UPDATE ' . db_tab( 'pagine' ) . ' SET sezioni = ? WHERE id = ?',
			array( json_encode( $nuove, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), $pagina['id'] )
		);
		$fatte++;
	}

	impostazioni_salva( array( 'migrazione_faq' => '1' ) );
	return $fatte;
}
