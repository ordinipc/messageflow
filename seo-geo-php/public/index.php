<?php
/**
 * Front controller dell applicazione web.
 *
 * @package SeoGeoAudit
 */

require_once __DIR__ . '/../src/Autoload.php';

use SeoGeo\Ai\Gemini;
use SeoGeo\Ai\Immagini;
use SeoGeo\Ai\Rewriter as Riscrittura;
use SeoGeo\Ai\Rewriter;
use SeoGeo\Audit;
use SeoGeo\Bridge\WordPress;
use SeoGeo\Coda;
use SeoGeo\Db;
use SeoGeo\Diagnosi;
use SeoGeo\Diagnostica;
use SeoGeo\Redirezioni;
use SeoGeo\Export;
use SeoGeo\Impostazioni;
use SeoGeo\Media\Compressione;
use SeoGeo\Fix\InternalLinks;
use SeoGeo\Fix\Meta;
use SeoGeo\Site;
use SeoGeo\Search\Azioni;
use SeoGeo\Search\Prestazioni;
use SeoGeo\Search\Segnali;
use SeoGeo\Sync\Sito as SitoRemoto;
use SeoGeo\Triage;
use SeoGeo\WxrParser;

session_start();

$cfg = Impostazioni::carica( require __DIR__ . '/../config.php' );

try {
	$db = new Db( $cfg['database'] );
} catch ( Throwable $e ) {
	// Quasi sempre significa che storage/ non è scrivibile: senza un messaggio
	// chiaro qui l utente vedrebbe solo una pagina bianca.
	http_response_code( 500 );

	echo '<!doctype html><meta charset="utf-8"><title>Configurazione da completare</title>'
		. '<link rel="stylesheet" href="assets/app.css">'
		. '<main class="contenitore"><section class="intestazione"><h1>Il database non è raggiungibile</h1>'
		. '<p class="guida">' . htmlspecialchars( $e->getMessage(), ENT_QUOTES ) . '</p></section>'
		. '<section class="scheda"><p>Con SQLite (impostazione predefinita) il motivo è quasi sempre che la cartella '
		. '<code>storage/</code> non è scrivibile: impostale i permessi <code>775</code> via FTP o dal pannello dell hosting.</p>'
		. '<p><a class="bottone" href="verifica.php">Apri la verifica dei requisiti</a></p></section></main>';
	exit;
}

/**
 * Scorciatoia per l escape dell output.
 *
 * @param mixed $v Valore.
 * @return string
 */
function e( $v ) {
	return htmlspecialchars( (string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Numero formattato all italiana.
 *
 * @param int|float $n Numero.
 * @return string
 */
function num( $n ) {
	return number_format( (float) $n, 0, ',', '.' );
}

/**
 * Token anti-CSRF di sessione.
 *
 * @return string
 */
function token() {
	if ( empty( $_SESSION['token'] ) ) {
		$_SESSION['token'] = bin2hex( random_bytes( 16 ) );
	}

	return $_SESSION['token'];
}

/**
 * Indirizzo con cui si identifica il sito nelle rilevazioni di Google.
 *
 * @param array $cfg Configurazione.
 * @return string
 */
function chiave_sito( array $cfg ) {
	return Prestazioni::chiaveSito( $cfg );
}

/**
 * Indirizzo dell account di servizio, se la chiave è leggibile.
 *
 * Serve nell interfaccia: è il valore da aggiungere fra gli utenti di Search
 * Console, ed è la domanda che si fanno tutti al primo collegamento.
 *
 * @param array $cfg Configurazione.
 * @return string Vuoto se la chiave manca o non è valida.
 */
function google_indirizzo_account( array $cfg ) {
	$json = (string) ( $cfg['google']['chiave_json'] ?? '' );

	if ( '' === trim( $json ) ) {
		return '';
	}

	try {
		return ( new \SeoGeo\Google\ServiceAccount( \SeoGeo\Google\ServiceAccount::daJson( $json ) ) )->indirizzo();
	} catch ( Throwable $e ) {
		return '';
	}
}

/**
 * Indirizzo pubblico di questa installazione, senza barra finale.
 *
 * @return string Vuoto quando si gira da riga di comando.
 */
function indirizzo_base() {
	if ( empty( $_SERVER['HTTP_HOST'] ) ) {
		return '';
	}

	$sicuro = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== strtolower( (string) $_SERVER['HTTPS'] ) )
		|| 'https' === strtolower( (string) ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) );

	return ( $sicuro ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST']
		. rtrim( str_replace( '\\', '/', dirname( $_SERVER['SCRIPT_NAME'] ) ), '/' );
}

/**
 * Codice pronto da incollare per avere un pulsante "Analizza" in WordPress.
 *
 * @param string $base  Indirizzo del gestionale.
 * @param string $token Token di avvio.
 * @return string
 */
function snippet_pulsante_analizza( $base, $token ) {
	$url = $base . '/index.php?p=api-analizza&token=' . $token;

	$codice = <<<'PHP'
add_action( 'admin_menu', function () {
	add_submenu_page( 'mdi-seo-geo', 'Analizza', 'Analizza', 'manage_options', 'mdi-analizza', function () {
		if ( ! empty( $_POST['analizza'] ) && check_admin_referer( 'mdi_analizza' ) ) {
			$risposta = wp_remote_get( '__URL__', array( 'timeout' => 300 ) );
			$dati     = json_decode( wp_remote_retrieve_body( $risposta ), true );

			if ( is_array( $dati ) && ! empty( $dati['ok'] ) ) {
				printf(
					'<div class="notice notice-success"><p>Punteggio: <strong>%d/100</strong> (%s%d) &mdash; <a href="%s" target="_blank">apri la scheda</a></p></div>',
					(int) $dati['punteggio'],
					$dati['variazione'] > 0 ? '+' : '',
					(int) $dati['variazione'],
					esc_url( $dati['scheda'] )
				);
			} else {
				$errore = is_array( $dati ) && ! empty( $dati['errore'] ) ? $dati['errore'] : 'Analisi non riuscita';
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $errore ) );
			}
		}

		echo '<div class="wrap"><h1>Analizza il sito</h1>';
		echo '<p>Rilegge il sito, ricalcola il punteggio e aggiorna la scheda. Richiede fino a un paio di minuti.</p>';
		echo '<form method="post">';
		wp_nonce_field( 'mdi_analizza' );
		echo '<p><button class="button button-primary" name="analizza" value="1">Analizza adesso</button></p>';
		echo '</form></div>';
	} );
} );
PHP;

	return str_replace( '__URL__', $url, $codice );
}

/**
 * Rende una vista dentro il layout.
 *
 * @param string $vista Nome della vista.
 * @param array  $dati  Variabili disponibili nella vista.
 * @return void
 */
function vista( $vista, array $dati = array() ) {
	extract( $dati, EXTR_SKIP );
	$percorso_vista = __DIR__ . '/../views/' . $vista . '.php';

	ob_start();
	require $percorso_vista;
	$contenuto = ob_get_clean();

	require __DIR__ . '/../views/layout.php';
}

$pagina = $_GET['p'] ?? 'home';

// Da qui in poi il gestionale sa come lo si raggiunge dall esterno: serve al
// plugin per il pulsante "Analizza" e alla coda, che non ha un HTTP_HOST.
Impostazioni::ricordaIndirizzo( indirizzo_base() );

// ---------------------------------------------------------------- Nuovo audit
if ( 'analizza' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	$caricato = $_FILES['export'] ?? null;

	if ( ! $caricato || UPLOAD_ERR_OK !== $caricato['error'] ) {
		$errore = 'Caricamento non riuscito (codice ' . ( $caricato['error'] ?? '?' ) . '). Verifica upload_max_filesize e post_max_size in php.ini: un export WordPress supera spesso i 10 MB.';
		vista( 'nuovo', compact( 'errore' ) + array( 'titolo' => 'Nuovo audit', 'cfg' => $cfg ) );
		exit;
	}

	if ( ! preg_match( '/\.xml$/i', $caricato['name'] ) ) {
		$errore = 'Il file deve essere l esportazione XML di WordPress (Strumenti → Esporta → Tutti i contenuti).';
		vista( 'nuovo', compact( 'errore' ) + array( 'titolo' => 'Nuovo audit', 'cfg' => $cfg ) );
		exit;
	}

	$destinazione = __DIR__ . '/../storage/import/' . date( 'Ymd-His' ) . '-' . preg_replace( '/[^A-Za-z0-9._-]/', '', $caricato['name'] );

	if ( ! is_dir( dirname( $destinazione ) ) ) {
		mkdir( dirname( $destinazione ), 0775, true );
	}

	move_uploaded_file( $caricato['tmp_name'], $destinazione );

	set_time_limit( 300 );

	$site   = new Site( WxrParser::parse( $destinazione ) );
	$audit  = Audit::esegui( $site );
	$triage = Triage::esegui( $site, $cfg );
	$meta   = Meta::piano( $site, $cfg );
	$link   = InternalLinks::piano( $site, $cfg );

	$auditId = Audit::salva( $db, $site, $audit, basename( $caricato['name'] ) );
	Triage::salva( $db, $auditId, $triage );
	Meta::salva( $db, $auditId, $meta );
	InternalLinks::salva( $db, $auditId, $link['piano'] );

	Export::tutto(
		array(
			'site'     => $site,
			'cfg'      => $cfg,
			'audit'    => $audit,
			'triage'   => $triage,
			'meta'     => $meta,
			'link'     => $link,
			'cartella' => __DIR__ . '/../storage/export/audit-' . $auditId,
		)
	);

	header( 'Location: ?p=audit&id=' . $auditId );
	exit;
}

/**
 * Controlla il token di avvio esterno e ferma tutto se non torna.
 *
 * @return void
 */
function pretendi_token_esterno() {
	$atteso  = (string) ( Impostazioni::salvate()['gestionale']['token'] ?? '' );
	$fornito = (string) ( $_REQUEST['token'] ?? $_SERVER['HTTP_X_MDI_ANALISI'] ?? '' );

	// Nessun token configurato significa nessun avvio da fuori: si rifiuta.
	if ( '' === $atteso || '' === $fornito || ! hash_equals( $atteso, $fornito ) ) {
		http_response_code( 401 );
		echo json_encode( array( 'ok' => false, 'errore' => 'Token non valido.' ) );
		exit;
	}
}

/**
 * Fa passare una sola esecuzione ogni tot secondi.
 *
 * @param string $nome    Nome del contrassegno in storage/.
 * @param int    $secondi Distanza minima fra due esecuzioni.
 * @param string $errore  Messaggio da restituire a chi arriva troppo presto.
 * @return void
 */
function pretendi_attesa( $nome, $secondi, $errore ) {
	$segnale = __DIR__ . '/../storage/' . $nome;

	if ( is_file( $segnale ) && ( time() - (int) file_get_contents( $segnale ) ) < $secondi ) {
		http_response_code( 429 );
		echo json_encode( array( 'ok' => false, 'errore' => $errore ) );
		exit;
	}

	file_put_contents( $segnale, (string) time() );
}

// ------------- Aggiornamento dei dati di Google da un cron o da un pulsante
if ( 'api-prestazioni' === $pagina ) {
	header( 'Content-Type: application/json; charset=utf-8' );

	pretendi_token_esterno();

	if ( ! Prestazioni::configurata( $cfg ) ) {
		http_response_code( 400 );
		echo json_encode(
			array(
				'ok'     => false,
				'errore' => 'Search Console non è collegata: manca ' . implode(
					' e ',
					array_map(
						static fn( $c ) => 'chiave' === $c ? 'la chiave dell account di servizio' : 'la proprietà',
						Prestazioni::cosaManca( $cfg )
					)
				) . '.',
			)
		);
		exit;
	}

	// Dieci minuti: i dati di Google si consolidano in giorni, chiamarlo più
	// spesso non aggiunge niente e consuma la quota.
	pretendi_attesa( 'ultima-google.txt', 600, 'Dati aggiornati da poco: riprova fra dieci minuti.' );
	set_time_limit( 0 );

	try {
		$ultimo    = $db->one( 'SELECT id FROM audit ORDER BY id DESC LIMIT 1' );
		$documenti = $ultimo
			? $db->all( "SELECT url, titolo, tipo, pubblicato FROM documento WHERE audit_id = ? AND stato = 'publish'", array( $ultimo['id'] ) )
			: array();

		$esito      = Prestazioni::esegui( $db, $cfg, $documenti );
		$precedenti = $db->all(
			'SELECT clic, impression, posizione_media FROM gsc_rilevazione WHERE sito_url = ? ORDER BY id DESC LIMIT 2',
			array( Prestazioni::chiaveSito( $cfg ) )
		);

		$prima = $precedenti[1] ?? null;

		$per_tipo = array();

		foreach ( $esito['segnali'] as $segnale ) {
			$per_tipo[ $segnale['tipo'] ] = ( $per_tipo[ $segnale['tipo'] ] ?? 0 ) + 1;
		}

		echo json_encode(
			array(
				'ok'          => true,
				'rilevazione' => $esito['rilevazione'],
				'periodo'     => $esito['periodo']['da'] . ' → ' . $esito['periodo']['a'],
				'clic'        => $esito['totali']['clic'],
				'impression'  => $esito['totali']['impression'],
				'variazione'  => $prima
					? array(
						'clic'       => $esito['totali']['clic'] - (int) $prima['clic'],
						'impression' => $esito['totali']['impression'] - (int) $prima['impression'],
					)
					: null,
				'da_fare'     => count( $esito['segnali'] ),
				'per_tipo'    => $per_tipo,
				'scheda'      => indirizzo_base() . '/index.php?p=prestazioni',
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
	} catch ( Throwable $e ) {
		http_response_code( 500 );
		echo json_encode( array( 'ok' => false, 'errore' => $e->getMessage() ), JSON_UNESCAPED_UNICODE );
	}

	exit;
}

// ------------------------------- Avvio dell analisi da un pulsante esterno
if ( 'api-analizza' === $pagina ) {
	header( 'Content-Type: application/json; charset=utf-8' );

	pretendi_token_esterno();

	// Una analisi ogni due minuti: evita che un pulsante premuto più volte
	// faccia partire dieci letture del sito in parallelo.
	pretendi_attesa( 'ultima-analisi.txt', 120, 'Analisi avviata da poco: riprova fra un paio di minuti.' );
	set_time_limit( 0 );

	try {
		$lettore = new SitoRemoto( new WordPress( $cfg['wordpress'] ) );
		$site    = new Site( $lettore->leggi() );

		$audit  = Audit::esegui( $site );
		$triage = Triage::esegui( $site, $cfg );
		$meta   = Meta::piano( $site, $cfg );
		$link   = InternalLinks::piano( $site, $cfg );

		$precedente = $db->one( 'SELECT punteggio FROM audit WHERE sito_url = ? ORDER BY id DESC LIMIT 1', array( $site->url ) );

		$auditId = Audit::salva( $db, $site, $audit, 'avviata dall esterno' );
		Triage::salva( $db, $auditId, $triage );
		Meta::salva( $db, $auditId, $meta );
		InternalLinks::salva( $db, $auditId, $link['piano'] );

		Export::tutto(
			array(
				'site'     => $site,
				'cfg'      => $cfg,
				'audit'    => $audit,
				'triage'   => $triage,
				'meta'     => $meta,
				'link'     => $link,
				'cartella' => __DIR__ . '/../storage/export/audit-' . $auditId,
			)
		);

		$indirizzo = indirizzo_base() . '/index.php?p=audit&id=' . $auditId;

		echo json_encode(
			array(
				'ok'         => true,
				'audit'      => $auditId,
				'punteggio'  => $audit['globale'],
				'variazione' => $precedente ? $audit['globale'] - (int) $precedente['punteggio'] : null,
				'problemi'   => $audit['occorrenze'],
				'articoli'   => count( $site->articoli ),
				'pagine'     => count( $site->pagine ),
				'scheda'     => $indirizzo,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
	} catch ( Throwable $e ) {
		http_response_code( 500 );
		echo json_encode( array( 'ok' => false, 'errore' => $e->getMessage() ), JSON_UNESCAPED_UNICODE );
	}

	exit;
}

// --------------------------------- Aggiornamento dei dati di Search Console
if ( 'aggiorna-google' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	set_time_limit( 0 );

	try {
		// I contenuti noti servono a riconoscere le pagine che Google non
		// mostra mai: senza, quel segnale non si potrebbe calcolare.
		$ultimo    = $db->one( 'SELECT id FROM audit ORDER BY id DESC LIMIT 1' );
		$documenti = $ultimo
			? $db->all( "SELECT url, titolo, tipo, pubblicato FROM documento WHERE audit_id = ? AND stato = 'publish'", array( $ultimo['id'] ) )
			: array();

		$esito = Prestazioni::esegui( $db, $cfg, $documenti );

		header(
			'Location: ?p=prestazioni&messaggio=' . rawurlencode(
				sprintf(
					'Dati aggiornati: %s clic e %s impression negli ultimi %d giorni, %d cose da fare in ordine di convenienza.',
					num( $esito['totali']['clic'] ),
					num( $esito['totali']['impression'] ),
					(int) $esito['periodo']['giorni'],
					count( $esito['segnali'] )
				)
			)
		);
	} catch ( Throwable $e ) {
		header( 'Location: ?p=prestazioni&errore=' . rawurlencode( $e->getMessage() ) );
	}

	exit;
}

// ----------------------------- Pacchetto diagnostico, senza dati personali
if ( 'scarica-diagnostica' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	set_time_limit( 0 );

	try {
		$ponte = new WordPress( $cfg['wordpress'] );

		if ( ! $ponte->pronto() ) {
			throw new RuntimeException( 'Collegamento al sito non configurato: serve per leggere i contenuti attuali.' );
		}

		$site      = new Site( ( new SitoRemoto( $ponte ) )->leggi() );
		$pacchetto = Diagnostica::pacchetto( $site, $ponte->stato() );
		$json      = json_encode( $pacchetto, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		// Compresso: un sito di trecento articoli sta in pochi megabyte, e non
		// resta niente sul server.
		$compresso = gzencode( $json, 9 );
		$nome      = 'diagnostica-' . preg_replace( '/[^a-z0-9]+/i', '-', (string) parse_url( $site->url, PHP_URL_HOST ) ) . '-' . date( 'Ymd-Hi' ) . '.json.gz';

		header( 'Content-Type: application/gzip' );
		header( 'Content-Disposition: attachment; filename="' . $nome . '"' );
		header( 'Content-Length: ' . strlen( $compresso ) );
		header( 'X-Contenuti: ' . count( $pacchetto['contenuti'] ) );

		echo $compresso;
	} catch ( Throwable $e ) {
		header( 'Location: ?p=diagnostica&errore=' . rawurlencode( $e->getMessage() ) );
	}

	exit;
}

// ------------------ Dalle indicazioni di Google ai compiti del pilota
if ( 'applica-segnali' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	$audit  = $db->one( 'SELECT id FROM audit ORDER BY id DESC LIMIT 1' );
	$ultima = Prestazioni::ultima( $db, Prestazioni::chiaveSito( $cfg ) );

	if ( ! $audit || ! $ultima ) {
		header( 'Location: ?p=prestazioni&errore=' . rawurlencode( 'Serve prima un analisi del sito e una lettura di Search Console.' ) );
		exit;
	}

	$piano = Azioni::piano(
		Azioni::abbina( $db, $audit['id'], Prestazioni::segnali( $db, $ultima['id'], 500 ) ),
		array( 'pagine' => ! empty( $_POST['pagine'] ) )
	);

	if ( ! $piano ) {
		header( 'Location: ?p=prestazioni&errore=' . rawurlencode( 'Nessuna indicazione di Google si traduce in una modifica automatica: guarda la colonna "Contenuto sul sito".' ) );
		exit;
	}

	Azioni::inCoda( $db, $audit['id'], $piano );

	header( 'Location: ?p=pilota&id=' . (int) $audit['id'] . '&da=google' );
	exit;
}

// ----------------------- Rilevamento delle proprietà viste dall account
if ( 'rileva-proprieta' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	try {
		// Si costruisce il client senza proprietà: qui la stiamo cercando.
		$account = new \SeoGeo\Google\ServiceAccount( \SeoGeo\Google\ServiceAccount::daJson( Impostazioni::chiaveGoogle( $cfg ) ) );
		$console = new \SeoGeo\Google\SearchConsole( $account, '' );
		$nomi    = array_column( $console->siti(), 'proprieta' );

		if ( ! $nomi ) {
			throw new RuntimeException(
				'La chiave funziona, ma l account ' . $account->indirizzo() . ' non vede nessuna proprietà. '
				. 'Vai in Search Console → Impostazioni → Utenti e autorizzazioni e aggiungilo come utente con permesso "Con limitazioni".'
			);
		}

		// Una sola proprietà: non c è niente da scegliere, si imposta.
		if ( 1 === count( $nomi ) ) {
			$salvate = Impostazioni::salvate();
			$salvate['google']['proprieta'] = $nomi[0];
			Impostazioni::salva( $salvate );

			header( 'Location: ?p=impostazioni&salvato=1&google=' . rawurlencode( 'Proprietà impostata: ' . $nomi[0] ) );
			exit;
		}

		header( 'Location: ?p=impostazioni&proprieta=' . rawurlencode( implode( '|', array_slice( $nomi, 0, 20 ) ) ) . '#search-console' );
	} catch ( Throwable $e ) {
		header( 'Location: ?p=impostazioni&errore=' . rawurlencode( $e->getMessage() ) );
	}

	exit;
}

// ---------------------------------- Scelta della proprietà fra quelle viste
if ( 'scegli-proprieta' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	$salvate = Impostazioni::salvate();
	$salvate['google']['proprieta'] = trim( (string) ( $_POST['proprieta'] ?? '' ) );

	Impostazioni::salva( $salvate );

	header( 'Location: ?p=impostazioni&salvato=1&google=' . rawurlencode( 'Proprietà impostata: ' . $salvate['google']['proprieta'] ) );
	exit;
}

// ------------------------------------- Prova del collegamento con Google
if ( 'prova-google' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	try {
		$console = Prestazioni::client( $cfg );
		$siti    = $console->siti();
		$nomi    = array_column( $siti, 'proprieta' );

		if ( ! in_array( $console->proprieta(), $nomi, true ) ) {
			throw new RuntimeException(
				'Il collegamento funziona, ma fra le proprietà accessibili non c è "' . $console->proprieta() . '". '
				. ( $nomi
					? 'L account di servizio vede: ' . implode( ', ', $nomi ) . '. Correggi il campo Proprietà.'
					: 'L account di servizio non vede nessuna proprietà: aggiungilo come utente in Search Console → Impostazioni → Utenti e autorizzazioni.' )
			);
		}

		header(
			'Location: ?p=impostazioni&salvato=1&google=' . rawurlencode(
				'Collegamento a Search Console riuscito: ' . $console->proprieta() . ' è accessibile.'
			)
		);
	} catch ( Throwable $e ) {
		header( 'Location: ?p=impostazioni&errore=' . rawurlencode( $e->getMessage() ) );
	}

	exit;
}

if ( 'rigenera-token' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido.' );
	}

	Impostazioni::rigeneraTokenEsterno();

	header( 'Location: ?p=impostazioni&salvato=1' );
	exit;
}

// --------------------------------------- Nuova analisi leggendo dal sito
if ( 'risincronizza' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	$ponte = new WordPress( $cfg['wordpress'] );

	set_time_limit( 0 );

	try {
		$lettore = new SitoRemoto( $ponte );
		$site    = new Site( $lettore->leggi() );

		$audit   = Audit::esegui( $site );
		$triage  = Triage::esegui( $site, $cfg );
		$meta    = Meta::piano( $site, $cfg );
		$link    = InternalLinks::piano( $site, $cfg );

		$auditId = Audit::salva( $db, $site, $audit, 'letto dal sito' );
		Triage::salva( $db, $auditId, $triage );
		Meta::salva( $db, $auditId, $meta );
		InternalLinks::salva( $db, $auditId, $link['piano'] );

		Export::tutto(
			array(
				'site'     => $site,
				'cfg'      => $cfg,
				'audit'    => $audit,
				'triage'   => $triage,
				'meta'     => $meta,
				'link'     => $link,
				'cartella' => __DIR__ . '/../storage/export/audit-' . $auditId,
			)
		);

		header( 'Location: ?p=audit&id=' . $auditId . '&nuovo=1' );
	} catch ( Throwable $e ) {
		header( 'Location: ?p=home&errore=' . rawurlencode( $e->getMessage() ) );
	}

	exit;
}

// ------------------------------------------------------- Pilota automatico
if ( 'pilota-avvia-coda' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	// Si avvia la coda che c è già: rigenerarla dall audit cancellerebbe il
	// piano costruito sulle indicazioni di Google.
	header( 'Location: ?p=pilota&id=' . (int) ( $_POST['id'] ?? 0 ) . '&attivo=1' );
	exit;
}

if ( 'pilota-avvia' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	$id = (int) ( $_POST['id'] ?? 0 );

	// Si esegue solo quello che è stato spuntato: prima la casella delle
	// immagini si aggiungeva a tutto il resto, e chi voleva le sole immagini
	// si ritrovava in coda anche duecento riscritture.
	$includi = array_values( array_intersect( array_keys( Coda::GRUPPI ), (array) ( $_POST['includi'] ?? array() ) ) );

	Coda::prepara(
		$db,
		$id,
		$cfg,
		array(
			'includi'  => $includi,
			'immagini' => in_array( 'immagine', $includi, true ),
			'pubblica' => ! empty( $_POST['pubblica'] ),
			'cestina'  => ! empty( $_POST['cestina'] ),
			'pagine'   => ! empty( $_POST['pagine'] ),
		)
	);

	header( 'Location: ?p=pilota&id=' . $id . '&attivo=1' );
	exit;
}

if ( 'pilota-ferma' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido.' );
	}

	$id = (int) ( $_POST['id'] ?? 0 );
	$db->run( 'DELETE FROM coda WHERE audit_id = ? AND stato = ?', array( $id, Coda::ATTESA ) );

	header( 'Location: ?p=pilota&id=' . $id );
	exit;
}

if ( 'api-comprimi' === $pagina ) {
	header( 'Content-Type: application/json; charset=utf-8' );

	if ( ! hash_equals( token(), $_GET['token'] ?? '' ) ) {
		http_response_code( 400 );
		echo json_encode( array( 'errore' => 'Sessione scaduta: ricarica la pagina.' ) );
		exit;
	}

	set_time_limit( 0 );

	// Un giro corto e il browser richiama subito dopo. Il lavoro lungo non
	// puo stare in una richiesta sola - gli hosting condivisi la chiudono -
	// ma nessuno deve premere un pulsante venti volte per arrivare in fondo.
	$limite_php = (int) ini_get( 'max_execution_time' );
	$secondi    = $limite_php > 0 ? max( 10, min( 40, $limite_php - 10 ) ) : 40;

	try {
		$ponte = new WordPress( $cfg['wordpress'] );

		$esito = Compressione::esegui(
			$ponte,
			array(
				'lato'        => (int) ( $cfg['ai']['immagine_lato_max'] ?? 1200 ),
				'qualita'     => (int) ( $cfg['ai']['immagine_qualita'] ?? 82 ),
				'peso_max'    => (int) ( $cfg['ai']['immagine_peso_max'] ?? 190000 ),
				'secondi_max' => $secondi,
			)
		);

		echo json_encode(
			array(
				'compresse'   => (int) $esito['compresse'],
				'restanti'    => (int) $esito['restanti'],
				'in_tutto'    => (int) $esito['in_tutto'],
				'risparmio'   => (int) $esito['risparmio'],
				'leggibile'   => Compressione::peso( $esito['risparmio'] ),
				'non_toccate' => (int) $esito['non_toccate'],
				'errori'      => array_slice( (array) $esito['errori'], 0, 5 ),
				// Fermarsi anche quando il giro non conclude niente: senza
				// questa condizione, un errore che si ripete manderebbe il
				// browser in un ciclo infinito.
				'finito'      => 0 === (int) $esito['restanti'] || 0 === (int) $esito['compresse'],
			)
		);
	} catch ( Throwable $e ) {
		http_response_code( 500 );
		echo json_encode( array( 'errore' => $e->getMessage(), 'finito' => true ) );
	}

	exit;
}

if ( 'pilota-esegui' === $pagina ) {
	header( 'Content-Type: application/json; charset=utf-8' );

	if ( ! hash_equals( token(), $_GET['token'] ?? '' ) ) {
		http_response_code( 400 );
		echo json_encode( array( 'errore' => 'Sessione scaduta: ricarica la pagina.' ) );
		exit;
	}

	$id = (int) ( $_GET['id'] ?? 0 );

	set_time_limit( 0 );
	ignore_user_abort( false );

	// Un giro corto: il browser richiama subito dopo, e una eventuale
	// interruzione della connessione lascia la coda in ordine.
	$limite_php = (int) ini_get( 'max_execution_time' );
	$budget     = $limite_php > 0 ? max( 10, min( 25, $limite_php - 10 ) ) : 25;

	echo json_encode( Coda::esegui( $db, $id, $cfg, $budget ), JSON_UNESCAPED_UNICODE );
	exit;
}

// -------------------------------------------- Applicazione sul sito WordPress
if ( 'applica' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	$id     = (int) ( $_POST['id'] ?? 0 );
	$azione = preg_replace( '/[^a-z_]/', '', (string) ( $_POST['azione'] ?? '' ) );
	$ponte  = new WordPress( $cfg['wordpress'] );

	set_time_limit( 0 );

	try {
		switch ( $azione ) {

			case 'config':
				$risposta  = $ponte->inviaConfigurazione( $cfg );
				$mancanti  = (array) ( $risposta['mancanti'] ?? array() );
				$messaggio = 'Dati aziendali inviati al sito.'
					. ( $mancanti ? ' Restano da compilare in Impostazioni: ' . implode( ', ', $mancanti ) . '.' : ' Sono tutti compilati.' );
				break;

			case 'meta':
			case 'meta_anteprima':
				// Con un limite si prova su pochi contenuti prima di toccare tutto:
				// è il modo sensato di verificare sul proprio sito.
				$limite = max( 0, min( 500, (int) ( $_POST['limite'] ?? 0 ) ) );

				// Articoli e pagine sono cose diverse: le pagine servizio sono
				// poche e curate a mano, gli articoli sono centinaia e generici.
				$ambito = in_array( $_POST['ambito'] ?? '', array( 'post', 'page' ), true ) ? $_POST['ambito'] : '';

				$sql = 'SELECT d.wp_id AS id, m.title_nuovo AS title, m.description_nuova AS description,
							m.excerpt_nuovo AS excerpt, d.focus_keyword AS focus
					 FROM meta_piano m JOIN documento d ON d.id = m.documento_id
					 WHERE m.audit_id = ?';

				$parametri = array( $id );

				if ( $ambito ) {
					$sql        .= ' AND d.tipo = ?';
					$parametri[] = $ambito;
				}

				// Solo i contenuti che cambiano davvero. Senza questo filtro
				// "prova su 5" prendeva i cinque articoli piu lunghi, che quasi
				// mai sono fra quelli da correggere: il sito rispondeva "5
				// aggiornati" riscrivendo cinque volte gli stessi valori, e non
				// si capiva piu che cosa fosse stato toccato.
				$sql .= Coda::soloDaCambiare();

				$sql .= ' ORDER BY d.tipo DESC, d.parole DESC' . ( $limite ? ' LIMIT ' . $limite : '' );

				$righe = $db->all( $sql, $parametri );

				$anteprima = ( 'meta_anteprima' === $azione );
				$fatti     = 0;
				$confronto = array();

				// Si spedisce a blocchi: un unica richiesta con 326 contenuti
				// supererebbe i limiti di memoria e di tempo di molti hosting.
				foreach ( array_chunk( $righe, 80 ) as $blocco ) {
					$esito     = $ponte->inviaMeta( $blocco, $anteprima );
					$fatti    += (int) ( $esito['aggiornati'] ?? 0 );
					$confronto = array_merge( $confronto, (array) ( $esito['dettaglio'] ?? array() ) );
				}

				if ( $anteprima ) {
					// Il confronto va su file: in sessione occuperebbe troppo e
					// così resta consultabile anche dopo aver chiuso il browser.
					Export::scrivi(
						__DIR__ . '/../storage/export/audit-' . $id . '/anteprima-meta.json',
						json_encode(
							array( 'quando' => date( 'Y-m-d H:i:s' ), 'ambito' => $ambito, 'righe' => $confronto ),
							JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
						)
					);

					header( 'Location: ?p=anteprima&id=' . $id );
					exit;
				}

				// Quali contenuti sono stati toccati, non solo quanti: senza
				// l elenco non c e modo di andare a controllarli su WordPress.
				Export::scrivi(
					__DIR__ . '/../storage/export/audit-' . $id . '/applicate-meta.json',
					json_encode(
						array( 'quando' => date( 'Y-m-d H:i:s' ), 'ambito' => $ambito, 'righe' => $confronto ),
						JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
					)
				);

				$etichetta_ambito = 'post' === $ambito ? ' articoli' : ( 'page' === $ambito ? ' pagine' : ' contenuti' );
				$messaggio        = "Meta aggiornate su $fatti$etichetta_ambito."
					. ( $fatti ? ' Apri «Quali ho cambiato» per vedere quali sono e andare a controllarli.' : '' )
					. ( $limite && $fatti ? ' Poi applica il resto.' : '' );
				break;

			case 'pilota':
		$id    = (int) ( $_GET['id'] ?? 0 );
		$audit = $db->one( 'SELECT * FROM audit WHERE id = ?', array( $id ) );

		if ( ! $audit ) {
			http_response_code( 404 );
			exit( 'Audit non trovato.' );
		}

		$gemini = new Gemini( $cfg['ai'] );
		$ponte  = new WordPress( $cfg['wordpress'] );

		vista(
			'pilota',
			array(
				'titolo'     => 'Pilota automatico',
				'audit'      => $audit,
				'cfg'        => $cfg,
				'ai_pronto'  => $gemini->pronto(),
				'wp_pronto'  => $ponte->pronto(),
				'stato'      => Coda::stato( $db, $id ),
				'attivo'     => isset( $_GET['attivo'] ),
				// Una coda preparata dalle indicazioni di Google non va
				// ricostruita dall audit: la si avvia com è, o si perde.
				// Con ?piano=audit si chiede espressamente il piano completo.
				'coda_google' => isset( $_GET['piano'] ) ? array() : $db->all(
					"SELECT tipo, etichetta FROM coda WHERE audit_id = ? AND stato = ? AND origine = 'google' ORDER BY ordine",
					array( $id, Coda::ATTESA )
				),
				'ultime'     => $db->all(
					'SELECT tipo, etichetta, stato, messaggio, eseguito_il FROM coda
					 WHERE audit_id = ? AND stato <> ? ORDER BY eseguito_il DESC, id DESC LIMIT 40',
					array( $id, Coda::ATTESA )
				),
				'stima'      => Coda::stima( $db, $id, $cfg ),
				'meta_cambiano' => Coda::metaDaCambiare( $db, $id, 'post' ),
				'meta_anteprima' => Coda::anteprimaMeta( $db, $id, 'post', 60 ),
				'previsione' => array(
					'meta'          => (int) $db->one( 'SELECT COUNT(*) n FROM meta_piano WHERE audit_id = ?', array( $id ) )['n'],
					'meta_articoli' => (int) $db->one( "SELECT COUNT(*) n FROM meta_piano m JOIN documento d ON d.id = m.documento_id WHERE m.audit_id = ? AND d.tipo = 'post'", array( $id ) )['n'],
					'meta_pagine'   => (int) $db->one( "SELECT COUNT(*) n FROM meta_piano m JOIN documento d ON d.id = m.documento_id WHERE m.audit_id = ? AND d.tipo = 'page'", array( $id ) )['n'],
					// Stessa funzione che decide che cosa spedire davvero: il
					// numero scritto nella pagina e quello che viene scritto sul
					// sito devono venire dallo stesso conto, altrimenti si legge
					// 18 e se ne aggiornano altri.
					'cambi_articoli' => Coda::metaDaCambiare( $db, $id, 'post' ),
					'cambi_pagine'  => Coda::metaDaCambiare( $db, $id, 'page' ),
					'redirect'  => (int) $db->one( "SELECT COUNT(*) n FROM triage WHERE audit_id = ? AND redirect_a <> ''", array( $id ) )['n'],
					'categorie' => (int) $db->one( "SELECT COUNT(*) n FROM occorrenza o JOIN rilievo r ON r.id = o.rilievo_id WHERE r.audit_id = ? AND r.regola = 'TAX-03'", array( $id ) )['n'],
					'riscritture' => count( Riscrittura::candidati( $db, $id, array() ) ),
					'gruppi'    => count( Riscrittura::gruppi( $db, $id ) ),
					'immagini'  => count( Immagini::candidati( $db, $id, array() ) ),
					'cestino'   => (int) $db->one( "SELECT COUNT(*) n FROM triage WHERE audit_id = ? AND categoria = 'eliminare'", array( $id ) )['n'],
				),
			)
		);
		break;

	case 'bozza':
		$idb   = (int) ( $_GET['b'] ?? 0 );
		$bozza = $db->one(
			'SELECT b.*, d.titolo AS titolo_originale, d.url AS url_originale, d.parole AS parole_originali, d.slug
			 FROM bozza b JOIN documento d ON d.id = b.documento_id WHERE b.id = ?',
			array( $idb )
		);

		if ( ! $bozza ) {
			http_response_code( 404 );
			exit( 'Bozza non trovata.' );
		}

		// Il file si ricostruisce dai dati salvati: anche se è stato cancellato
		// o rinominato, il download continua a funzionare.
		$contenuto = Riscrittura::fileBozza(
			array(
				'titolo'           => $bozza['titolo'],
				'meta_title'       => $bozza['meta_title'],
				'meta_description' => $bozza['meta_description'],
				'in_breve'         => $bozza['in_breve'],
				'corpo_html'       => $bozza['corpo_html'],
				'faq'              => json_decode( (string) $bozza['faq'], true ) ?: array(),
				'da_verificare'    => json_decode( (string) $bozza['da_verificare'], true ) ?: array(),
			),
			array( 'titolo' => $bozza['titolo_originale'], 'url' => $bozza['url_originale'] ),
			$bozza['modello']
		);

		if ( isset( $_GET['scarica'] ) ) {
			$nome = $bozza['file'] ?: ( $bozza['slug'] . '.html' );

			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $nome . '"' );
			header( 'Content-Length: ' . strlen( $contenuto ) );
			echo $contenuto;
			exit;
		}

		vista(
			'bozza',
			array(
				'titolo'    => 'Bozza: ' . $bozza['titolo'],
				'bozza'     => $bozza,
				'contenuto' => $contenuto,
				'audit_id'  => (int) $bozza['audit_id'],
			)
		);
		break;

	case 'anteprima':
		$id    = (int) ( $_GET['id'] ?? 0 );
		$audit = $db->one( 'SELECT * FROM audit WHERE id = ?', array( $id ) );

		if ( ! $audit ) {
			http_response_code( 404 );
			exit( 'Audit non trovato.' );
		}

		$file      = __DIR__ . '/../storage/export/audit-' . $id . '/anteprima-meta.json';
		$dal_sito  = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
		$righe     = array();
		$sorgente  = 'sito';

		if ( is_array( $dal_sito ) && ! empty( $dal_sito['righe'] ) ) {
			// Il sito risponde con gli id di WordPress: il tipo e l URL si
			// recuperano dal database, servono per distinguere articoli e pagine.
			$per_wp_id = array();

			foreach ( $db->all( 'SELECT wp_id, tipo, url FROM documento WHERE audit_id = ?', array( $id ) ) as $documento ) {
				$per_wp_id[ (string) $documento['wp_id'] ] = $documento;
			}

			foreach ( $dal_sito['righe'] as $r ) {
				$documento = $per_wp_id[ (string) ( $r['id'] ?? '' ) ] ?? array();

				$righe[] = array(
					'id'     => $r['id'] ?? 0,
					'titolo' => $r['titolo'] ?? '',
					'tipo'   => $documento['tipo'] ?? '',
					'url'    => $documento['url'] ?? '',
					'campi'  => array(
						'Title SEO'        => array( $r['prima']['rank_math_title'] ?? '', $r['dopo']['rank_math_title'] ?? '' ),
						'Meta description' => array( $r['prima']['rank_math_description'] ?? '', $r['dopo']['rank_math_description'] ?? '' ),
						'Focus keyword'    => array( $r['prima']['rank_math_focus_keyword'] ?? '', $r['dopo']['rank_math_focus_keyword'] ?? '' ),
						'Estratto'         => array( $r['prima']['post_excerpt'] ?? '', $r['dopo']['post_excerpt'] ?? '' ),
					),
				);
			}
		} else {
			// Nessuna anteprima dal sito: si mostra il confronto con i valori
			// letti dall export, che è comunque quello che verrà scritto.
			$sorgente = 'export';

			foreach ( $db->all(
				'SELECT d.wp_id, d.titolo, d.url, d.tipo, d.seo_title, d.seo_description, d.focus_keyword,
						m.title_nuovo, m.description_nuova, m.excerpt_nuovo
				 FROM meta_piano m JOIN documento d ON d.id = m.documento_id
				 WHERE m.audit_id = ? ORDER BY d.tipo DESC, d.parole DESC',
				array( $id )
			) as $r ) {
				$righe[] = array(
					'id'     => $r['wp_id'],
					'titolo' => $r['titolo'],
					'tipo'   => $r['tipo'],
					'url'    => $r['url'],
					'campi'  => array(
						'Title SEO'        => array( $r['seo_title'], $r['title_nuovo'] ),
						'Meta description' => array( $r['seo_description'], $r['description_nuova'] ),
						'Focus keyword'    => array( $r['focus_keyword'], $r['focus_keyword'] ),
						'Estratto'         => array( '', $r['excerpt_nuovo'] ),
					),
				);
			}
		}

		vista(
			'anteprima',
			array(
				'titolo'   => 'Anteprima delle modifiche',
				'audit'    => $audit,
				'righe'    => $righe,
				'sorgente' => $sorgente,
				'quando'   => $dal_sito['quando'] ?? '',
				'fatte'    => false,
				'solo'     => isset( $_GET['tutti'] ) ? 'tutti' : 'modificati',
				'tipo'     => in_array( $_GET['tipo'] ?? '', array( 'post', 'page' ), true ) ? $_GET['tipo'] : '',
			)
		);
		break;

	case 'collega':
		$id    = (int) ( $_GET['id'] ?? 0 );
		$audit = $db->one( 'SELECT * FROM audit WHERE id = ?', array( $id ) );

		if ( ! $audit ) {
			http_response_code( 404 );
			exit( 'Audit non trovato.' );
		}

		$ponte  = new WordPress( $cfg['wordpress'] );
		$stato  = null;
		$errore_stato = '';

		if ( $ponte->pronto() ) {
			try {
				$stato = $ponte->stato();
			} catch ( Throwable $e ) {
				$errore_stato = $e->getMessage();
			}
		}

		vista(
			'collega',
			array(
				'confronto' => Redirezioni::confronto( $db, $audit['sito_url'] ),
				'archivio'  => $db->all(
					'SELECT a.id, a.creato_il, (SELECT COUNT(*) FROM documento d WHERE d.audit_id = a.id) AS contenuti
					 FROM audit a WHERE a.sito_url = (SELECT sito_url FROM audit WHERE id = ?) ORDER BY a.id ASC LIMIT 20',
					array( $id )
				),
				'titolo'       => 'Applica sul sito',
				'audit'        => $audit,
				'cfg'          => $cfg,
				'pronto'       => $ponte->pronto(),
				'stato'        => $stato,
				'errore_stato' => $errore_stato,
				'esito'        => (string) ( $_GET['esito'] ?? '' ),
				'errore'       => (string) ( $_GET['errore'] ?? '' ),
				// L elenco delle immagini pesanti si legge dal sito, non
				// dall archivio: qui conta quello che c e adesso in libreria
				// media. Se il sito non risponde la sezione lo dice e basta.
				'immagini'     => ( static function () use ( $ponte, $stato ) {
					if ( ! $ponte->pronto() || ! $stato ) {
						return array();
					}

					try {
						return Compressione::elenco( $ponte );
					} catch ( Throwable $e ) {
						return array( 'errore' => 'Elenco non leggibile: ' . $e->getMessage() );
					}
				} )(),
				// Quando e stata scritta l ultima volta la lista delle meta:
				// serve a offrire il link a "quali contenuti ho cambiato".
				'applicate'    => ( static function () use ( $id ) {
					$f = __DIR__ . '/../storage/export/audit-' . $id . '/applicate-meta.json';
					$d = is_file( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : null;
					return is_array( $d ) && ! empty( $d['righe'] ) ? (string) ( $d['quando'] ?? '' ) : '';
				} )(),
				'conteggi'     => array(
					'meta'          => (int) $db->one( 'SELECT COUNT(*) n FROM meta_piano WHERE audit_id = ?', array( $id ) )['n'],
					'meta_articoli' => (int) $db->one( "SELECT COUNT(*) n FROM meta_piano m JOIN documento d ON d.id = m.documento_id WHERE m.audit_id = ? AND d.tipo = 'post'", array( $id ) )['n'],
					'meta_pagine'   => (int) $db->one( "SELECT COUNT(*) n FROM meta_piano m JOIN documento d ON d.id = m.documento_id WHERE m.audit_id = ? AND d.tipo = 'page'", array( $id ) )['n'],
					// Stessa funzione che decide che cosa spedire davvero: il
					// numero scritto nella pagina e quello che viene scritto sul
					// sito devono venire dallo stesso conto, altrimenti si legge
					// 18 e se ne aggiornano altri.
					'cambi_articoli' => Coda::metaDaCambiare( $db, $id, 'post' ),
					'cambi_pagine'  => Coda::metaDaCambiare( $db, $id, 'page' ),
					'bozze'     => (int) $db->one( "SELECT COUNT(*) n FROM bozza WHERE audit_id = ? AND stato = 'ok'", array( $id ) )['n'],
					'redirect'      => (int) $db->one( "SELECT COUNT(*) n FROM triage WHERE audit_id = ? AND redirect_a <> ''", array( $id ) )['n'],
					'redirect_slug' => (int) $db->one( 'SELECT COUNT(*) n FROM meta_piano WHERE audit_id = ? AND slug_cambiato = 1', array( $id ) )['n'],
					'categorie' => (int) $db->one( "SELECT COUNT(*) n FROM occorrenza o JOIN rilievo r ON r.id = o.rilievo_id WHERE r.audit_id = ? AND r.regola = 'TAX-03'", array( $id ) )['n'],
				),
			)
		);
		break;

	case 'bozze':
				$bozze = $db->all(
					"SELECT b.*, d.wp_id FROM bozza b JOIN documento d ON d.id = b.documento_id
					 WHERE b.audit_id = ? AND b.stato = 'ok' ORDER BY b.id DESC LIMIT 25",
					array( $id )
				);

				$inviate = 0;

				foreach ( $bozze as $b ) {
					$ponte->inviaBozza(
						array(
							'id'               => (int) $b['wp_id'],
							'titolo'           => $b['titolo'],
							'corpo_html'       => $b['corpo_html'],
							'in_breve'         => $b['in_breve'],
							'meta_title'       => $b['meta_title'],
							'meta_description' => $b['meta_description'],
							'faq'              => json_decode( (string) $b['faq'], true ) ?: array(),
						)
					);
					$inviate++;
				}

				$messaggio = "$inviate bozze create sul sito come articoli in stato Bozza: nessun contenuto pubblicato è stato toccato.";
				break;

			case 'redirect':
				$righe = array();

				// Obbligatori: gli URL che spariscono davvero (contenuti eliminati
				// o assorbiti in un altro articolo).
				foreach ( $db->all( "SELECT t.redirect_a, d.percorso FROM triage t JOIN documento d ON d.id = t.documento_id WHERE t.audit_id = ? AND t.redirect_a <> ''", array( $id ) ) as $r ) {
					$righe[] = array( 'da' => $r['percorso'], 'a' => $r['redirect_a'] );
				}

				$obbligatori = count( $righe );
				$con_slug    = ! empty( $_POST['slug'] );

				// Facoltativi: gli slug accorciati. Cambiare URL a un articolo che
				// resta online è un costo certo per un guadagno marginale, quindi
				// non succede se non lo si chiede esplicitamente.
				if ( $con_slug ) {
					foreach ( $db->all( 'SELECT m.slug_nuovo, d.percorso FROM meta_piano m JOIN documento d ON d.id = m.documento_id WHERE m.audit_id = ? AND m.slug_cambiato = 1', array( $id ) ) as $r ) {
						$righe[] = array( 'da' => $r['percorso'], 'a' => rtrim( $cfg['wordpress']['url'], '/' ) . '/' . $r['slug_nuovo'] . '/' );
					}
				}

				$esito     = $ponte->inviaRedirect( $righe );
				$attivi    = (int) ( $esito['redirect'] ?? 0 );
				$messaggio = $con_slug
					? "$attivi redirect attivi: $obbligatori per i contenuti rimossi più quelli degli slug accorciati. Ricorda di cambiare davvero gli slug in WordPress, altrimenti i redirect non servono a niente."
					: "$attivi redirect attivi, solo per i contenuti eliminati o accorpati. Gli slug degli articoli che restano online non sono stati toccati.";
				break;

			case 'categorie':
				// Le proposte arrivano dalla regola TAX-03, già registrata nell audit.
				$occorrenze = $db->all(
					"SELECT o.riferimento, o.dettaglio FROM occorrenza o
					 JOIN rilievo r ON r.id = o.rilievo_id
					 WHERE r.audit_id = ? AND r.regola = 'TAX-03'",
					array( $id )
				);

				$assegnazioni = array();

				foreach ( $occorrenze as $o ) {
					if ( ! preg_match( '/suggerita "([^"]+)"/', $o['dettaglio'], $m ) ) {
						continue;
					}

					$doc = $db->one( 'SELECT wp_id FROM documento WHERE audit_id = ? AND percorso = ?', array( $id, $o['riferimento'] ) );

					if ( $doc ) {
						$assegnazioni[] = array( 'id' => (int) $doc['wp_id'], 'categoria' => $m[1] );
					}
				}

				$esito     = $ponte->inviaCategorie( $assegnazioni );
				$messaggio = ( (int) ( $esito['assegnate'] ?? 0 ) ) . ' articoli ricategorizzati.';
				break;

			case 'redirect_cambiati':
				// Un contenuto rinominato lascia indietro il suo vecchio
				// indirizzo: senza un 301 i link che arrivano da fuori si
				// perdono, e la posizione in Google riparte da zero.
				$cambiati = Redirezioni::cambiati( $db, $audit['sito_url'] );

				if ( ! $cambiati ) {
					throw new RuntimeException( 'Nessun indirizzo è cambiato fra le ultime due analisi.' );
				}

				$righe = array();

				foreach ( $cambiati as $riga ) {
					$righe[] = array( 'da' => $riga['da'], 'a' => rtrim( $audit['sito_url'], '/' ) . $riga['a'] );
				}

				$esito     = $ponte->inviaRedirect( $righe );
				$messaggio = sprintf(
					'%d indirizzi vecchi ora rimandano a quelli nuovi. Attenzione: questo sostituisce la tabella dei redirect sul sito.',
					count( $righe )
				);
				break;

			case 'ripristina_analisi':
				// Seconda rete di sicurezza, indipendente dal plugin: ogni
				// analisi archiviata contiene title, description e focus come
				// erano sul sito quel giorno. La prima di tutte viene
				// dall export XML, cioè da prima di qualsiasi modifica.
				$da = (int) ( $_POST['da_audit'] ?? 0 );

				if ( ! $da || ! $db->one( 'SELECT id FROM audit WHERE id = ?', array( $da ) ) ) {
					throw new RuntimeException( 'Analisi di partenza non trovata.' );
				}

				$righe = array();

				foreach ( $db->all(
					"SELECT wp_id, seo_title, seo_description, focus_keyword FROM documento
					 WHERE audit_id = ? AND wp_id <> '' AND ( seo_title <> '' OR seo_description <> '' )",
					array( $da )
				) as $riga ) {
					$righe[] = array(
						'id'          => $riga['wp_id'],
						'title'       => $riga['seo_title'],
						'description' => $riga['seo_description'],
						'excerpt'     => '',
						'focus'       => $riga['focus_keyword'],
					);
				}

				if ( ! $righe ) {
					throw new RuntimeException( 'In quell analisi non risultano title e description da rimettere.' );
				}

				$fatti = 0;

				// A blocchi, come per l invio: rimettere trecento contenuti in
				// una richiesta sola esaurirebbe il tempo di esecuzione.
				foreach ( array_chunk( $righe, 40 ) as $blocco ) {
					$esito  = $ponte->inviaMeta( $blocco, false );
					$fatti += (int) ( $esito['aggiornati'] ?? 0 );
				}

				$messaggio = sprintf(
					'%d contenuti riportati a title e description dell analisi del %s.',
					$fatti,
					substr( (string) $db->one( 'SELECT creato_il FROM audit WHERE id = ?', array( $da ) )['creato_il'], 0, 10 )
				);
				break;

			case 'annulla':
			case 'annulla_pagine':
				// Si può tornare indietro su tutto o solo sulle pagine: quelle
				// sono scritte a mano e un ripristino mirato evita di buttare
				// via anche le meta degli articoli, che invece vanno bene.
				$solo_pagine = 'annulla_pagine' === $azione;

				$ids = array_column(
					$db->all(
						'SELECT d.wp_id FROM documento d WHERE d.audit_id = ?' . ( $solo_pagine ? " AND d.tipo = 'page'" : '' ),
						array( $id )
					),
					'wp_id'
				);

				$esito     = $ponte->annulla( $ids );
				$messaggio = ( (int) ( $esito['ripristinati'] ?? 0 ) )
					. ( $solo_pagine ? ' pagine riportate' : ' contenuti riportati' ) . ' alle meta precedenti.';
				break;

			case 'comprimi_immagini':
				$limite = max( 0, min( 500, (int) ( $_POST['limite'] ?? 0 ) ) );

				$esito = Compressione::esegui(
					$ponte,
					array(
						'limite'      => $limite,
						'lato'        => (int) ( $cfg['ai']['immagine_lato_max'] ?? 1200 ),
						'qualita'     => (int) ( $cfg['ai']['immagine_qualita'] ?? 82 ),
						'peso_max'    => (int) ( $cfg['ai']['immagine_peso_max'] ?? 190000 ),
						// Gli hosting condivisi chiudono le richieste lunghe: si
						// lavora a blocchi e si ripreme il pulsante.
						'secondi_max' => 45,
					)
				);

				$messaggio = sprintf(
					'%d immagini ricompresse, da %s a %s: %s risparmiati.',
					(int) $esito['compresse'],
					Compressione::peso( $esito['peso_prima'] ),
					Compressione::peso( $esito['peso_dopo'] ),
					Compressione::peso( $esito['risparmio'] )
				);

				// La cifra che serve davvero: quante ne mancano.
				if ( $esito['restanti'] > 0 ) {
					$messaggio .= sprintf(
						' Ne restano %d su %d: ripremi «Comprimi tutte» per continuare (circa %d volte).',
						(int) $esito['restanti'],
						(int) $esito['in_tutto'],
						max( 1, (int) ceil( $esito['restanti'] / max( 1, (int) $esito['compresse'] ?: 14 ) ) )
					);
				} elseif ( $esito['compresse'] ) {
					$messaggio .= ' Non ne restano altre: sono tutte fatte.';
				}

				if ( $esito['non_toccate'] ) {
					$messaggio .= ' ' . (int) $esito['non_toccate'] . ' non toccate perché usate dentro il testo degli articoli.';
				}

				if ( $esito['errori'] ) {
					$messaggio .= ' Errori: ' . implode( '; ', array_slice( $esito['errori'], 0, 3 ) );
				}
				break;

			case 'ripristina_immagini':
				$esito     = $ponte->ripristinaImmagini();
				$messaggio = ( (int) ( $esito['ripristinate'] ?? 0 ) ) . ' immagini riportate agli originali.';

				if ( ! empty( $esito['originali_mancanti'] ) ) {
					$messaggio .= ' ' . (int) $esito['originali_mancanti'] . ' originali non erano più sul disco.';
				}
				break;

			default:
				$messaggio = 'Azione sconosciuta.';
		}

		header( 'Location: ?p=collega&id=' . $id . '&esito=' . rawurlencode( $messaggio ) );
	} catch ( Throwable $e ) {
		header( 'Location: ?p=collega&id=' . $id . '&errore=' . rawurlencode( $e->getMessage() ) );
	}

	exit;
}

// -------------------------------------------------------- Salva impostazioni
if ( 'salva-impostazioni' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	$campo = static function ( $nome ) {
		return trim( (string) ( $_POST[ $nome ] ?? '' ) );
	};

	$salvate = Impostazioni::salvate();

	$nuove = array(
		'azienda' => array(
			'nome'       => $campo( 'az_nome' ),
			'nomeLegale' => $campo( 'az_ragione' ),
			'partitaIva' => $campo( 'az_piva' ),
			'fondazione' => $campo( 'az_fondazione' ),
			'telefono'   => $campo( 'az_telefono' ),
			'cellulare'  => $campo( 'az_cellulare' ),
			'email'      => $campo( 'az_email' ),
			'whatsapp'   => $campo( 'az_whatsapp' ),
			'indirizzo'  => array(
				'via'         => $campo( 'az_via' ),
				'cap'         => $campo( 'az_cap' ),
				'citta'       => $campo( 'az_citta' ),
				'provincia'   => $campo( 'az_provincia' ),
				'latitudine'  => $campo( 'az_lat' ),
				'longitudine' => $campo( 'az_lng' ),
			),
			'profili'    => array(
				'googleBusiness' => $campo( 'az_gbp' ),
				'instagram'      => $campo( 'az_instagram' ),
				'facebook'       => $campo( 'az_facebook' ),
				'linkedin'       => $campo( 'az_linkedin' ),
			),
		),
		'autori'  => array(
			array(
				'nome'     => $campo( 'au_nome' ),
				'cognome'  => $campo( 'au_cognome' ),
				'ruolo'    => $campo( 'au_ruolo' ),
				'bio'      => $campo( 'au_bio' ),
				'linkedin' => $campo( 'au_linkedin' ),
			),
		),
		'ai'      => array(
			'modello'            => $campo( 'ai_modello' ) ?: 'gemini-2.5-flash',
			'modello_immagini'   => $campo( 'ai_modello_immagini' ) ?: 'gemini-2.5-flash-image',
			'articoli_per_volta' => max( 1, min( 25, (int) $campo( 'ai_articoli_per_volta' ) ) ),
			'max_token'          => max( 2048, min( 65536, (int) ( $campo( 'ai_max_token' ) ?: 16384 ) ) ),
			// Un campo lasciato vuoto non vuol dire "gratis": vuol dire "non l ho
			// toccato". Azzerare i prezzi faceva mostrare costo zero dappertutto.
			'prezzo_per_milione' => array(
				'input'  => '' !== $campo( 'ai_prezzo_input' )
					? (float) str_replace( ',', '.', $campo( 'ai_prezzo_input' ) )
					: (float) ( $salvate['ai']['prezzo_per_milione']['input'] ?? 0.10 ),
				'output' => '' !== $campo( 'ai_prezzo_output' )
					? (float) str_replace( ',', '.', $campo( 'ai_prezzo_output' ) )
					: (float) ( $salvate['ai']['prezzo_per_milione']['output'] ?? 0.40 ),
			),
		),
		'wordpress' => array(
			'url' => rtrim( $campo( 'wp_url' ), '/' ),
		),
		'google'  => array(
			'proprieta'      => $campo( 'g_proprieta' ),
			'giorni'         => max( 7, min( 180, (int) ( $campo( 'g_giorni' ) ?: 28 ) ) ),
			'min_impression' => max( 1, min( 1000, (int) ( $campo( 'g_min_impression' ) ?: 20 ) ) ),
		),
		'seo'     => array(
			'brandSuffix'            => $campo( 'seo_brand' ),
			'cittaPrincipale'        => $campo( 'seo_citta' ),
			'linkInterniPerArticolo' => max( 0, min( 10, (int) $campo( 'seo_link' ) ) ),
			'sogliaQualita'          => max( 0, min( 100, (int) $campo( 'seo_soglia' ) ) ),
			'linkAutomaticiNellePagine' => ! empty( $_POST['seo_link_pagine'] ),
		),
	);

	// I segreti si sovrascrivono solo se ne è stato digitato uno nuovo:
	// il campo vuoto significa "lascia quello che c era".
	$chiave_ai = $campo( 'ai_chiave' );
	$nuove['ai']['chiave'] = '' !== $chiave_ai
		? $chiave_ai
		: ( $salvate['ai']['chiave'] ?? '' );

	$token_wp = $campo( 'wp_token' );
	$nuove['wordpress']['token'] = '' !== $token_wp
		? $token_wp
		: ( $salvate['wordpress']['token'] ?? '' );

	// La chiave può arrivare incollata o come file: su diversi hosting il
	// firewall blocca i moduli che contengono una chiave privata, e il file
	// caricato passa dove il campo di testo viene respinto.
	$chiave_google = $campo( 'g_chiave_json' );
	$caricata      = $_FILES['g_chiave_file'] ?? null;

	if ( $caricata && UPLOAD_ERR_OK === $caricata['error'] && $caricata['size'] > 0 ) {
		$chiave_google = (string) file_get_contents( $caricata['tmp_name'] );
	}

	$errore_chiave = '';

	if ( '' !== $chiave_google ) {
		// Meglio accorgersene adesso che fra tre giorni: se il file non è
		// quello giusto, lo si dice subito e non lo si salva.
		try {
			\SeoGeo\Google\ServiceAccount::daJson( $chiave_google );
		} catch ( Throwable $e ) {
			$errore_chiave = $e->getMessage();
			$chiave_google = '';
		}
	}

	$nuove['google']['chiave_json'] = '' !== $chiave_google
		? $chiave_google
		: ( $salvate['google']['chiave_json'] ?? '' );

	try {
		Impostazioni::salva( $nuove );

		// I dati aziendali servono al plugin, non solo al gestionale: appena
		// salvati partono verso il sito, così lo schema LocalBusiness è completo
		// senza dover rigenerare e reinstallare il plugin.
		$esito_invio = '';
		$aggiornata  = Impostazioni::carica( require __DIR__ . '/../config.php' );
		$ponte       = new WordPress( $aggiornata['wordpress'] );

		if ( $ponte->pronto() ) {
			try {
				$risposta    = $ponte->inviaConfigurazione( $aggiornata );
				$esito_invio = empty( $risposta['mancanti'] )
					? '&inviato=1'
					: '&inviato=1&mancanti=' . rawurlencode( implode( ', ', (array) $risposta['mancanti'] ) );
			} catch ( Throwable $e ) {
				$esito_invio = '&invio_errore=' . rawurlencode( $e->getMessage() );
			}
		}

		// Si rilegge dal disco: è l unica prova che il salvataggio è andato a
		// buon fine davvero, e non che lo abbiamo solo creduto.
		$riletta = Impostazioni::chiaveGoogle( $aggiornata );
		$esito_chiave = '';

		if ( '' !== $errore_chiave ) {
			$esito_chiave = '&chiave_errore=' . rawurlencode( $errore_chiave );
		} elseif ( '' !== $chiave_google && trim( $riletta ) !== trim( $chiave_google ) ) {
			$esito_chiave = '&chiave_errore=' . rawurlencode(
				'La chiave Google non è stata scritta su disco: controlla i permessi di storage/ (775) oppure carica il file per FTP in storage/google.json'
			);
		} elseif ( '' !== $chiave_google ) {
			$esito_chiave = '&chiave=' . rawurlencode( \SeoGeo\Google\ServiceAccount::daJson( $riletta )['client_email'] );
		}

		header( 'Location: ?p=impostazioni&salvato=1' . $esito_invio . $esito_chiave );
	} catch ( Throwable $e ) {
		header( 'Location: ?p=impostazioni&errore=' . rawurlencode( $e->getMessage() ) );
	}

	exit;
}

// ------------------------------------------------------- Generazione bozze AI
if ( 'genera' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	$id     = (int) ( $_POST['id'] ?? 0 );
	$quante = max( 1, min( 25, (int) ( $_POST['quante'] ?? $cfg['ai']['articoli_per_volta'] ) ) );
	$tipo   = preg_replace( '/[^a-z]/', '', (string) ( $_POST['tipo'] ?? 'articoli' ) );
	$gemini = new Gemini( $cfg['ai'] );

	if ( ! $gemini->pronto() ) {
		header( 'Location: ?p=bozze&id=' . $id . '&esito=chiave' );
		exit;
	}

	set_time_limit( 0 );

	// Si lavora a lotti con un tetto di tempo: su hosting condiviso una
	// generazione lunga verrebbe interrotta dal server a metà.
	$limite_php = (int) ini_get( 'max_execution_time' );
	$budget     = $limite_php > 0 ? max( 20, $limite_php - 15 ) : 90;
	$opzioni    = array( 'limite' => $quante, 'secondi_max' => $budget );

	try {
		if ( 'accorpa' === $tipo ) {
			$esito = Rewriter::consolida( $db, $gemini, $id, $cfg, $opzioni );
		} elseif ( 'immagini' === $tipo ) {
			$ponte = new WordPress( $cfg['wordpress'] );
			$esito = Immagini::esegui( $db, $gemini, $id, $cfg, $opzioni + array( 'invia' => ! empty( $_POST['invia'] ) ), $ponte );
			$esito['fallite'] = count( $esito['errori'] );
		} else {
			$esito = Rewriter::esegui( $db, $gemini, $id, $cfg, $opzioni );
		}
	} catch ( Throwable $e ) {
		header( 'Location: ?p=bozze&id=' . $id . '&errore=' . rawurlencode( $e->getMessage() ) );
		exit;
	}

	header(
		'Location: ?p=bozze&id=' . $id
		. '&fatte=' . (int) $esito['generate']
		. '&errori=' . (int) ( $esito['fallite'] ?? 0 )
		. '&tipo=' . $tipo
	);
	exit;
}

// ------------------------------------------------------------------- Download
if ( 'download' === $pagina ) {
	$id   = (int) ( $_GET['id'] ?? 0 );
	$nome = basename( (string) ( $_GET['f'] ?? '' ) );
	$sub  = preg_replace( '#[^a-z0-9/-]#i', '', (string) ( $_GET['d'] ?? '' ) );
	$base = realpath( __DIR__ . '/../storage/export/audit-' . $id );

	// Lo zip del plugin viene costruito una volta sola, quando si fa
	// l analisi. Aggiornando il gestionale restava in archivio quello
	// vecchio e il pulsante continuava a servirlo: si scaricava una
	// versione del plugin diversa da quella installata, senza che niente
	// lo dicesse. Se non coincide si ricostruisce adesso.
	if ( $base && 'mdi-seo-geo-booster.zip' === $nome && ! $sub ) {
		$installata = Export::versionePlugin();
		$archiviata = Export::versioneNelloZip( $base . '/' . $nome );

		if ( $installata && $installata !== $archiviata ) {
			try {
				Export::plugin( array( 'cartella' => $base ), $base . '/plugin-data' );
			} catch ( Throwable $e ) {
				// Se la ricostruzione non riesce si serve quello che c e:
				// meglio un plugin vecchio che nessun plugin.
				error_log( 'Ricostruzione del plugin non riuscita: ' . $e->getMessage() );
			}
		}
	}

	$file = realpath( $base . '/' . ( $sub ? $sub . '/' : '' ) . $nome );

	// Il percorso richiesto deve restare dentro la cartella dell audit.
	if ( ! $base || ! $file || 0 !== strpos( $file, $base ) || ! is_file( $file ) ) {
		http_response_code( 404 );
		exit( 'File non disponibile.' );
	}

	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Disposition: attachment; filename="' . $nome . '"' );
	header( 'Content-Length: ' . filesize( $file ) );
	readfile( $file );
	exit;
}

// ------------------------------------------------------------- Elimina audit
if ( 'elimina' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido.' );
	}

	$id = (int) ( $_POST['id'] ?? 0 );

	$db->run( 'DELETE FROM occorrenza WHERE rilievo_id IN (SELECT id FROM rilievo WHERE audit_id = ?)', array( $id ) );
	foreach ( array( 'rilievo', 'documento', 'area', 'triage', 'meta_piano', 'link_piano' ) as $tabella ) {
		$db->run( "DELETE FROM $tabella WHERE audit_id = ?", array( $id ) );
	}
	$db->run( 'DELETE FROM audit WHERE id = ?', array( $id ) );

	header( 'Location: ?p=home' );
	exit;
}

// ---------------------------------------------------------------- Pagine HTML
switch ( $pagina ) {

	case 'nuovo':
		vista( 'nuovo', array( 'titolo' => 'Nuovo audit', 'cfg' => $cfg, 'errore' => null ) );
		break;

	case 'audit':
		$id    = (int) ( $_GET['id'] ?? 0 );
		$audit = $db->one( 'SELECT * FROM audit WHERE id = ?', array( $id ) );

		if ( ! $audit ) {
			http_response_code( 404 );
			exit( 'Audit non trovato.' );
		}

		$precedente = $db->one(
			'SELECT punteggio, creato_il, problemi_totali FROM audit WHERE id < ? AND sito_url = ? ORDER BY id DESC LIMIT 1',
			array( $id, $audit['sito_url'] )
		);

		vista(
			'audit',
			array(
				'confronto'  => Redirezioni::confronto( $db, $audit['sito_url'] ),
				'titolo'    => 'Audit ' . $audit['sito_nome'],
				'audit'     => $audit,
				'precedente' => $precedente,
				'nuovo'     => isset( $_GET['nuovo'] ),
				'aree'      => $db->all( 'SELECT * FROM area WHERE audit_id = ? ORDER BY punteggio ASC', array( $id ) ),
				'rilievi'   => $db->all(
					"SELECT * FROM rilievo WHERE audit_id = ?
					 ORDER BY CASE gravita WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, occorrenze DESC",
					array( $id )
				),
				'conteggi'  => $db->all( 'SELECT categoria, COUNT(*) n FROM triage WHERE audit_id = ? GROUP BY categoria', array( $id ) ),
				'db'        => $db,
				'cfg'       => $cfg,
			)
		);
		break;

	case 'triage':
		$id       = (int) ( $_GET['id'] ?? 0 );
		$filtro   = preg_replace( '/[^a-z]/', '', (string) ( $_GET['c'] ?? '' ) );
		$audit    = $db->one( 'SELECT * FROM audit WHERE id = ?', array( $id ) );

		if ( ! $audit ) {
			http_response_code( 404 );
			exit( 'Audit non trovato.' );
		}

		$sql    = 'SELECT t.*, d.titolo, d.url, d.parole, d.slug FROM triage t JOIN documento d ON d.id = t.documento_id WHERE t.audit_id = ?';
		$params = array( $id );

		if ( in_array( $filtro, array( 'eliminare', 'accorpare', 'riscrivere', 'mantenere' ), true ) ) {
			$sql     .= ' AND t.categoria = ?';
			$params[] = $filtro;
		}

		$sql .= " ORDER BY CASE t.categoria WHEN 'eliminare' THEN 0 WHEN 'accorpare' THEN 1 WHEN 'riscrivere' THEN 2 ELSE 3 END, t.qualita ASC";

		vista(
			'triage',
			array(
				'titolo'   => 'Triage editoriale',
				'audit'    => $audit,
				'articoli' => $db->all( $sql, $params ),
				'conteggi' => $db->all( 'SELECT categoria, COUNT(*) n FROM triage WHERE audit_id = ? GROUP BY categoria', array( $id ) ),
				'filtro'   => $filtro,
			)
		);
		break;

	case 'impostazioni':
		$salvate = Impostazioni::salvate();

		vista(
			'impostazioni',
			array(
				'titolo'              => 'Impostazioni',
				'cfg'                 => $cfg,
				'mascherata'          => Impostazioni::mascherata( $salvate['ai']['chiave'] ?? '' ),
				'token_esterno'       => Impostazioni::tokenEsterno(),
				'snippet'             => snippet_pulsante_analizza(
					indirizzo_base(),
					Impostazioni::tokenEsterno()
				),
				'indirizzo_base'      => indirizzo_base(),
				'token_wp_mascherato' => Impostazioni::mascherata( $salvate['wordpress']['token'] ?? '' ),
				'google_configurato'  => Prestazioni::configurata( $cfg ),
				'google_da_file'      => '' === trim( (string) ( $salvate['google']['chiave_json'] ?? '' ) )
					&& is_file( Impostazioni::fileChiaveGoogle() ),
				'google_messaggio'    => isset( $_GET['chiave'] )
					? 'Chiave Google salvata e verificata. Account: ' . $_GET['chiave']
					: '',
				'google_errore'       => (string) ( $_GET['chiave_errore'] ?? '' ),
				'google_proprieta'    => isset( $_GET['proprieta'] )
					? array_filter( explode( '|', (string) $_GET['proprieta'] ) )
					: array(),
				'google_manca'        => Prestazioni::cosaManca( $cfg ),
				'google_chiave_presente' => '' !== trim( Impostazioni::chiaveGoogle( $cfg ) ),
				'google_account'      => google_indirizzo_account( $cfg ),
				'salvato'             => isset( $_GET['google'] )
					? (string) $_GET['google']
					: ( isset( $_GET['salvato'] )
					? 'Impostazioni salvate.'
						. ( isset( $_GET['inviato'] ) ? ' I dati aziendali sono stati inviati al sito: lo schema LocalBusiness ora li usa.' : '' )
						. ( isset( $_GET['mancanti'] ) ? ' Restano da compilare: ' . $_GET['mancanti'] . '.' : '' )
					: '' ),
				'errore'              => isset( $_GET['errore'] )
					? (string) $_GET['errore']
					: ( isset( $_GET['invio_errore'] ) ? 'Salvate, ma l invio al sito non è riuscito: ' . $_GET['invio_errore'] : '' ),
			)
		);
		break;

	case 'diagnostica':
		vista(
			'diagnostica',
			array(
				'titolo'   => 'Pacchetto diagnostico',
				'esclusi'  => Diagnostica::esclusi(),
				'meta'     => Diagnostica::META,
				'pronto'   => ( new WordPress( $cfg['wordpress'] ) )->pronto(),
				'errore'   => (string) ( $_GET['errore'] ?? '' ),
			)
		);
		break;

	case 'controlla':
		$url   = trim( (string) ( $_POST['url'] ?? $_GET['url'] ?? '' ) );
		$esito = null;

		if ( '' !== $url && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
				http_response_code( 400 );
				exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
			}

			$console = null;

			if ( Prestazioni::configurata( $cfg ) ) {
				try {
					$console = Prestazioni::client( $cfg );
				} catch ( Throwable $e ) {
					$console = null;
				}
			}

			$esito = Diagnosi::esegui( $db, $cfg, $url, new WordPress( $cfg['wordpress'] ), $console );
		}

		vista(
			'controlla',
			array(
				'titolo' => 'Perché questa pagina non si vede',
				'url'    => $url,
				'esito'  => $esito,
				'google' => Prestazioni::configurata( $cfg ),
			)
		);
		break;


	case 'prestazioni':
		$sito     = chiave_sito( $cfg );
		$ultima   = Prestazioni::ultima( $db, $sito );

		// Le indicazioni di Google valgono qualcosa solo se si sa a quale
		// contenuto del sito corrispondono: qui i due mondi vengono uniti.
		$audit_corrente    = $db->one( 'SELECT id FROM audit ORDER BY id DESC LIMIT 1' );
		$segnali_abbinati  = $ultima ? Prestazioni::segnali( $db, $ultima['id'] ) : array();
		$segnali_abbinati  = $audit_corrente
			? Azioni::abbina( $db, $audit_corrente['id'], $segnali_abbinati )
			: array_map( static fn( $x ) => $x + array( 'documento_id' => 0, 'wp_id' => '', 'titolo_sito' => '', 'tipo_sito' => '' ), $segnali_abbinati );
		$storico  = $db->all( 'SELECT * FROM gsc_rilevazione WHERE sito_url = ? ORDER BY id DESC LIMIT 12', array( $sito ) );

		vista(
			'prestazioni',
			array(
				'titolo'       => 'Rendimento in Google',
				'cfg'          => $cfg,
				'configurato'  => Prestazioni::configurata( $cfg ),
				'manca'        => Prestazioni::cosaManca( $cfg ),
				'chiave_ok'    => '' !== trim( Impostazioni::chiaveGoogle( $cfg ) ),
				'account'      => google_indirizzo_account( $cfg ),
				'proprieta'    => (string) ( $cfg['google']['proprieta'] ?? '' ),
				'ultima'       => $ultima,
				'precedente'   => count( $storico ) > 1 ? $storico[1] : null,
				'storico'      => $storico,
				'segnali'      => $segnali_abbinati,
				'piano'        => Azioni::piano( $segnali_abbinati ),
				'piano_pagine' => Azioni::piano( $segnali_abbinati, array( 'pagine' => true ) ),
				'pagine_fuori' => Azioni::pagineEscluse( $segnali_abbinati ),
				'audit_id'     => $audit_corrente ? (int) $audit_corrente['id'] : 0,
				'per_tipo'     => $ultima
					? $db->all(
						'SELECT tipo, titolo, COUNT(*) quanti, SUM(impression) impression FROM gsc_segnale WHERE rilevazione_id = ? GROUP BY tipo, titolo ORDER BY impression DESC',
						array( $ultima['id'] )
					)
					: array(),
				'pagine_top'   => $ultima
					? $db->all( 'SELECT * FROM gsc_pagina WHERE rilevazione_id = ? ORDER BY impression DESC LIMIT 25', array( $ultima['id'] ) )
					: array(),
				'query_top'    => $ultima
					? $db->all( 'SELECT * FROM gsc_query WHERE rilevazione_id = ? ORDER BY impression DESC LIMIT 40', array( $ultima['id'] ) )
					: array(),
				'messaggio'    => (string) ( $_GET['messaggio'] ?? '' ),
				'errore'       => (string) ( $_GET['errore'] ?? '' ),
			)
		);
		break;

	case 'pilota':
		$id    = (int) ( $_GET['id'] ?? 0 );
		$audit = $db->one( 'SELECT * FROM audit WHERE id = ?', array( $id ) );

		if ( ! $audit ) {
			http_response_code( 404 );
			exit( 'Audit non trovato.' );
		}

		$gemini = new Gemini( $cfg['ai'] );
		$ponte  = new WordPress( $cfg['wordpress'] );

		vista(
			'pilota',
			array(
				'titolo'     => 'Pilota automatico',
				'audit'      => $audit,
				'cfg'        => $cfg,
				'ai_pronto'  => $gemini->pronto(),
				'wp_pronto'  => $ponte->pronto(),
				'stato'      => Coda::stato( $db, $id ),
				'attivo'     => isset( $_GET['attivo'] ),
				// Una coda preparata dalle indicazioni di Google non va
				// ricostruita dall audit: la si avvia com è, o si perde.
				// Con ?piano=audit si chiede espressamente il piano completo.
				'coda_google' => isset( $_GET['piano'] ) ? array() : $db->all(
					"SELECT tipo, etichetta FROM coda WHERE audit_id = ? AND stato = ? AND origine = 'google' ORDER BY ordine",
					array( $id, Coda::ATTESA )
				),
				'ultime'     => $db->all(
					'SELECT tipo, etichetta, stato, messaggio, eseguito_il FROM coda
					 WHERE audit_id = ? AND stato <> ? ORDER BY eseguito_il DESC, id DESC LIMIT 40',
					array( $id, Coda::ATTESA )
				),
				'stima'      => Coda::stima( $db, $id, $cfg ),
				'meta_cambiano' => Coda::metaDaCambiare( $db, $id, 'post' ),
				'meta_anteprima' => Coda::anteprimaMeta( $db, $id, 'post', 60 ),
				'previsione' => array(
					'meta'          => (int) $db->one( 'SELECT COUNT(*) n FROM meta_piano WHERE audit_id = ?', array( $id ) )['n'],
					'meta_articoli' => (int) $db->one( "SELECT COUNT(*) n FROM meta_piano m JOIN documento d ON d.id = m.documento_id WHERE m.audit_id = ? AND d.tipo = 'post'", array( $id ) )['n'],
					'meta_pagine'   => (int) $db->one( "SELECT COUNT(*) n FROM meta_piano m JOIN documento d ON d.id = m.documento_id WHERE m.audit_id = ? AND d.tipo = 'page'", array( $id ) )['n'],
					// Stessa funzione che decide che cosa spedire davvero: il
					// numero scritto nella pagina e quello che viene scritto sul
					// sito devono venire dallo stesso conto, altrimenti si legge
					// 18 e se ne aggiornano altri.
					'cambi_articoli' => Coda::metaDaCambiare( $db, $id, 'post' ),
					'cambi_pagine'  => Coda::metaDaCambiare( $db, $id, 'page' ),
					'redirect'  => (int) $db->one( "SELECT COUNT(*) n FROM triage WHERE audit_id = ? AND redirect_a <> ''", array( $id ) )['n'],
					'categorie' => (int) $db->one( "SELECT COUNT(*) n FROM occorrenza o JOIN rilievo r ON r.id = o.rilievo_id WHERE r.audit_id = ? AND r.regola = 'TAX-03'", array( $id ) )['n'],
					'riscritture' => count( Riscrittura::candidati( $db, $id, array() ) ),
					'gruppi'    => count( Riscrittura::gruppi( $db, $id ) ),
					'immagini'  => count( Immagini::candidati( $db, $id, array() ) ),
					'cestino'   => (int) $db->one( "SELECT COUNT(*) n FROM triage WHERE audit_id = ? AND categoria = 'eliminare'", array( $id ) )['n'],
				),
			)
		);
		break;

	case 'bozza':
		$idb   = (int) ( $_GET['b'] ?? 0 );
		$bozza = $db->one(
			'SELECT b.*, d.titolo AS titolo_originale, d.url AS url_originale, d.parole AS parole_originali, d.slug
			 FROM bozza b JOIN documento d ON d.id = b.documento_id WHERE b.id = ?',
			array( $idb )
		);

		if ( ! $bozza ) {
			http_response_code( 404 );
			exit( 'Bozza non trovata.' );
		}

		// Il file si ricostruisce dai dati salvati: anche se è stato cancellato
		// o rinominato, il download continua a funzionare.
		$contenuto = Riscrittura::fileBozza(
			array(
				'titolo'           => $bozza['titolo'],
				'meta_title'       => $bozza['meta_title'],
				'meta_description' => $bozza['meta_description'],
				'in_breve'         => $bozza['in_breve'],
				'corpo_html'       => $bozza['corpo_html'],
				'faq'              => json_decode( (string) $bozza['faq'], true ) ?: array(),
				'da_verificare'    => json_decode( (string) $bozza['da_verificare'], true ) ?: array(),
			),
			array( 'titolo' => $bozza['titolo_originale'], 'url' => $bozza['url_originale'] ),
			$bozza['modello']
		);

		if ( isset( $_GET['scarica'] ) ) {
			$nome = $bozza['file'] ?: ( $bozza['slug'] . '.html' );

			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $nome . '"' );
			header( 'Content-Length: ' . strlen( $contenuto ) );
			echo $contenuto;
			exit;
		}

		vista(
			'bozza',
			array(
				'titolo'    => 'Bozza: ' . $bozza['titolo'],
				'bozza'     => $bozza,
				'contenuto' => $contenuto,
				'audit_id'  => (int) $bozza['audit_id'],
			)
		);
		break;

	case 'anteprima':
		$id    = (int) ( $_GET['id'] ?? 0 );
		$audit = $db->one( 'SELECT * FROM audit WHERE id = ?', array( $id ) );

		if ( ! $audit ) {
			http_response_code( 404 );
			exit( 'Audit non trovato.' );
		}

		// Con ?applicate=1 si guarda l elenco di quello che e stato scritto davvero
		// sul sito, non di quello che verrebbe scritto: stesso confronto,
		// stessa pagina, ma e il registro delle modifiche gia fatte.
		$fatte = isset( $_GET['applicate'] );
		$nome  = $fatte ? 'applicate-meta.json' : 'anteprima-meta.json';

		$file      = __DIR__ . '/../storage/export/audit-' . $id . '/' . $nome;
		$dal_sito  = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
		$righe     = array();
		$sorgente  = 'sito';

		if ( is_array( $dal_sito ) && ! empty( $dal_sito['righe'] ) ) {
			// Il sito risponde con gli id di WordPress: il tipo e l URL si
			// recuperano dal database, servono per distinguere articoli e pagine.
			$per_wp_id = array();

			foreach ( $db->all( 'SELECT wp_id, tipo, url FROM documento WHERE audit_id = ?', array( $id ) ) as $documento ) {
				$per_wp_id[ (string) $documento['wp_id'] ] = $documento;
			}

			foreach ( $dal_sito['righe'] as $r ) {
				$documento = $per_wp_id[ (string) ( $r['id'] ?? '' ) ] ?? array();

				$righe[] = array(
					'id'     => $r['id'] ?? 0,
					'titolo' => $r['titolo'] ?? '',
					'tipo'   => $documento['tipo'] ?? '',
					'url'    => $documento['url'] ?? '',
					'campi'  => array(
						'Title SEO'        => array( $r['prima']['rank_math_title'] ?? '', $r['dopo']['rank_math_title'] ?? '' ),
						'Meta description' => array( $r['prima']['rank_math_description'] ?? '', $r['dopo']['rank_math_description'] ?? '' ),
						'Focus keyword'    => array( $r['prima']['rank_math_focus_keyword'] ?? '', $r['dopo']['rank_math_focus_keyword'] ?? '' ),
						'Estratto'         => array( $r['prima']['post_excerpt'] ?? '', $r['dopo']['post_excerpt'] ?? '' ),
					),
				);
			}
		} else {
			// Nessuna anteprima dal sito: si mostra il confronto con i valori
			// letti dall export, che è comunque quello che verrà scritto.
			$sorgente = 'export';

			foreach ( $db->all(
				'SELECT d.wp_id, d.titolo, d.url, d.tipo, d.seo_title, d.seo_description, d.focus_keyword,
						m.title_nuovo, m.description_nuova, m.excerpt_nuovo
				 FROM meta_piano m JOIN documento d ON d.id = m.documento_id
				 WHERE m.audit_id = ? ORDER BY d.tipo DESC, d.parole DESC',
				array( $id )
			) as $r ) {
				$righe[] = array(
					'id'     => $r['wp_id'],
					'titolo' => $r['titolo'],
					'tipo'   => $r['tipo'],
					'url'    => $r['url'],
					'campi'  => array(
						'Title SEO'        => array( $r['seo_title'], $r['title_nuovo'] ),
						'Meta description' => array( $r['seo_description'], $r['description_nuova'] ),
						'Focus keyword'    => array( $r['focus_keyword'], $r['focus_keyword'] ),
						'Estratto'         => array( '', $r['excerpt_nuovo'] ),
					),
				);
			}
		}

		vista(
			'anteprima',
			array(
				'titolo'   => 'Anteprima delle modifiche',
				'audit'    => $audit,
				'righe'    => $righe,
				'sorgente' => $sorgente,
				'quando'   => $dal_sito['quando'] ?? '',
				'fatte'    => $fatte,
				'solo'     => isset( $_GET['tutti'] ) ? 'tutti' : 'modificati',
				'tipo'     => in_array( $_GET['tipo'] ?? '', array( 'post', 'page' ), true ) ? $_GET['tipo'] : '',
			)
		);
		break;

	case 'collega':
		$id    = (int) ( $_GET['id'] ?? 0 );
		$audit = $db->one( 'SELECT * FROM audit WHERE id = ?', array( $id ) );

		if ( ! $audit ) {
			http_response_code( 404 );
			exit( 'Audit non trovato.' );
		}

		$ponte  = new WordPress( $cfg['wordpress'] );
		$stato  = null;
		$errore_stato = '';

		if ( $ponte->pronto() ) {
			try {
				$stato = $ponte->stato();
			} catch ( Throwable $e ) {
				$errore_stato = $e->getMessage();
			}
		}

		vista(
			'collega',
			array(
				'confronto' => Redirezioni::confronto( $db, $audit['sito_url'] ),
				'archivio'  => $db->all(
					'SELECT a.id, a.creato_il, (SELECT COUNT(*) FROM documento d WHERE d.audit_id = a.id) AS contenuti
					 FROM audit a WHERE a.sito_url = (SELECT sito_url FROM audit WHERE id = ?) ORDER BY a.id ASC LIMIT 20',
					array( $id )
				),
				'titolo'       => 'Applica sul sito',
				'audit'        => $audit,
				'cfg'          => $cfg,
				'pronto'       => $ponte->pronto(),
				'stato'        => $stato,
				'errore_stato' => $errore_stato,
				'esito'        => (string) ( $_GET['esito'] ?? '' ),
				'errore'       => (string) ( $_GET['errore'] ?? '' ),
				// L elenco delle immagini pesanti si legge dal sito, non
				// dall archivio: qui conta quello che c e adesso in libreria
				// media. Se il sito non risponde la sezione lo dice e basta.
				'immagini'     => ( static function () use ( $ponte, $stato ) {
					if ( ! $ponte->pronto() || ! $stato ) {
						return array();
					}

					try {
						return Compressione::elenco( $ponte );
					} catch ( Throwable $e ) {
						return array( 'errore' => 'Elenco non leggibile: ' . $e->getMessage() );
					}
				} )(),
				// Quando e stata scritta l ultima volta la lista delle meta:
				// serve a offrire il link a "quali contenuti ho cambiato".
				'applicate'    => ( static function () use ( $id ) {
					$f = __DIR__ . '/../storage/export/audit-' . $id . '/applicate-meta.json';
					$d = is_file( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : null;
					return is_array( $d ) && ! empty( $d['righe'] ) ? (string) ( $d['quando'] ?? '' ) : '';
				} )(),
				'conteggi'     => array(
					'meta'          => (int) $db->one( 'SELECT COUNT(*) n FROM meta_piano WHERE audit_id = ?', array( $id ) )['n'],
					'meta_articoli' => (int) $db->one( "SELECT COUNT(*) n FROM meta_piano m JOIN documento d ON d.id = m.documento_id WHERE m.audit_id = ? AND d.tipo = 'post'", array( $id ) )['n'],
					'meta_pagine'   => (int) $db->one( "SELECT COUNT(*) n FROM meta_piano m JOIN documento d ON d.id = m.documento_id WHERE m.audit_id = ? AND d.tipo = 'page'", array( $id ) )['n'],
					// Stessa funzione che decide che cosa spedire davvero: il
					// numero scritto nella pagina e quello che viene scritto sul
					// sito devono venire dallo stesso conto, altrimenti si legge
					// 18 e se ne aggiornano altri.
					'cambi_articoli' => Coda::metaDaCambiare( $db, $id, 'post' ),
					'cambi_pagine'  => Coda::metaDaCambiare( $db, $id, 'page' ),
					'bozze'     => (int) $db->one( "SELECT COUNT(*) n FROM bozza WHERE audit_id = ? AND stato = 'ok'", array( $id ) )['n'],
					'redirect'      => (int) $db->one( "SELECT COUNT(*) n FROM triage WHERE audit_id = ? AND redirect_a <> ''", array( $id ) )['n'],
					'redirect_slug' => (int) $db->one( 'SELECT COUNT(*) n FROM meta_piano WHERE audit_id = ? AND slug_cambiato = 1', array( $id ) )['n'],
					'categorie' => (int) $db->one( "SELECT COUNT(*) n FROM occorrenza o JOIN rilievo r ON r.id = o.rilievo_id WHERE r.audit_id = ? AND r.regola = 'TAX-03'", array( $id ) )['n'],
				),
			)
		);
		break;

	case 'bozze':
		$id    = (int) ( $_GET['id'] ?? 0 );
		$audit = $db->one( 'SELECT * FROM audit WHERE id = ?', array( $id ) );

		if ( ! $audit ) {
			http_response_code( 404 );
			exit( 'Audit non trovato.' );
		}

		$gemini    = new Gemini( $cfg['ai'] );
		$candidati = Rewriter::candidati( $db, $id, array() );

		vista(
			'bozze',
			array(
				'titolo'    => 'Riscrittura assistita',
				'audit'     => $audit,
				'cfg'       => $cfg,
				'pronto'    => $gemini->pronto(),
				'stima'     => Rewriter::stima( $candidati, $cfg['ai'] ),
				'bozze'     => $db->all(
					'SELECT b.*, d.url, d.slug, d.parole AS parole_originali
					 FROM bozza b JOIN documento d ON d.id = b.documento_id
					 WHERE b.audit_id = ? ORDER BY b.id DESC',
					array( $id )
				),
				'esito'     => $_GET['esito'] ?? '',
				'errore'    => (string) ( $_GET['errore'] ?? '' ),
				'tipo'      => (string) ( $_GET['tipo'] ?? '' ),
				'fatte'     => (int) ( $_GET['fatte'] ?? 0 ),
				'errori'    => (int) ( $_GET['errori'] ?? 0 ),
				'gruppi'    => count( Rewriter::gruppi( $db, $id ) ),
				'immagini'  => array(
					'mancanti' => (int) $db->one( "SELECT COUNT(*) n FROM documento WHERE audit_id = ? AND ha_thumbnail = 0 AND tipo = 'post'", array( $id ) )['n'],
					// Le analisi precedenti all aggiornamento non registravano
					// l immagine in evidenza: senza quel dato il conteggio sarebbe
					// gonfiato e si pagherebbero immagini già presenti.
					'ignoti'   => (int) $db->one( "SELECT COUNT(*) n FROM documento WHERE audit_id = ? AND ha_thumbnail IS NULL AND tipo = 'post'", array( $id ) )['n'],
					'generate' => is_dir( Immagini::cartella( $id ) ) ? count( glob( Immagini::cartella( $id ) . '/*.*' ) ) : 0,
				),
				'wp_pronto' => ( new WordPress( $cfg['wordpress'] ) )->pronto(),
			)
		);
		break;

	case 'rilievo':
		$id      = (int) ( $_GET['id'] ?? 0 );
		$rilievo = $db->one( 'SELECT * FROM rilievo WHERE id = ?', array( $id ) );

		if ( ! $rilievo ) {
			http_response_code( 404 );
			exit( 'Rilievo non trovato.' );
		}

		vista(
			'rilievo',
			array(
				'titolo'      => $rilievo['regola'],
				'rilievo'     => $rilievo,
				'occorrenze'  => $db->all( 'SELECT * FROM occorrenza WHERE rilievo_id = ? LIMIT 500', array( $id ) ),
				'audit'       => $db->one( 'SELECT * FROM audit WHERE id = ?', array( $rilievo['audit_id'] ) ),
			)
		);
		break;

	default:
		vista(
			'home',
			array(
				'titolo'    => 'Audit SEO e GEO',
				'audit'     => $db->all( 'SELECT * FROM audit ORDER BY id DESC LIMIT 50' ),
				'cfg'       => $cfg,
				'errore'    => (string) ( $_GET['errore'] ?? '' ),
				'collegato' => ( new WordPress( $cfg['wordpress'] ) )->pronto(),
			)
		);
}
