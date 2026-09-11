<?php
/**
 * Collaudo del collegamento con Google Search Console.
 *
 * Uso: php test/google.php
 *
 * La firma JWT viene verificata davvero, con una coppia di chiavi generata sul
 * momento; le risposte dell API arrivano da un finto servizio locale con le
 * stesse forme di quelle di Google.
 *
 * @package SeoGeoAudit
 */

require_once __DIR__ . '/../src/Autoload.php';

use SeoGeo\Google\SearchConsole;
use SeoGeo\Google\ServiceAccount;
use SeoGeo\Search\Segnali;

$errori = 0;

/**
 * @param string $descrizione Cosa si verifica.
 * @param bool   $condizione  Esito.
 * @param string $dettaglio   Dettaglio in caso di errore.
 * @return void
 */
function verifica( $descrizione, $condizione, $dettaglio = '' ) {
	global $errori;

	if ( $condizione ) {
		echo "  ✔ $descrizione\n";
		return;
	}

	$errori++;
	echo "  ✖ $descrizione" . ( $dettaglio ? " — $dettaglio" : '' ) . "\n";
}

/**
 * Esegue una funzione e restituisce il messaggio dell eccezione.
 *
 * @param callable $azione Codice da eseguire.
 * @return string Vuoto se non ha sollevato nulla.
 */
function errore_di( callable $azione ) {
	try {
		$azione();
	} catch ( Throwable $e ) {
		return $e->getMessage();
	}

	return '';
}

echo "\n▶ Collaudo del collegamento con Search Console\n\n";

// --- Lettura della chiave --------------------------------------------------
echo "Chiave dell account di servizio\n";

verifica(
	'un file che non è JSON viene respinto con una spiegazione',
	false !== stripos( errore_di( static fn() => ServiceAccount::daJson( 'non sono json' ) ), 'formato JSON' )
);

verifica(
	'un ID client OAuth viene riconosciuto e spiegato',
	false !== stripos( errore_di( static fn() => ServiceAccount::daJson( '{"installed":{"client_id":"x"}}' ) ), 'account di servizio' )
);

verifica(
	'un JSON senza client_email viene respinto',
	false !== stripos( errore_di( static fn() => ServiceAccount::daJson( '{"type":"service_account","private_key":"x"}' ) ), 'client_email' )
);

// Il messaggio deve dire cosa fare, e le cause di invalid_grant sono diverse
// fra loro: questo è il testo vero restituito da Google a una chiave inventata.
$messaggio = new ReflectionMethod( ServiceAccount::class, 'messaggioErrore' );
$messaggio->setAccessible( true );
$finto     = new ServiceAccount( array( 'client_email' => 'tizio@progetto.iam.gserviceaccount.com' ), '/dev/null' );

verifica(
	'"account not found" viene spiegato come chiave da rigenerare',
	false !== stripos(
		$messaggio->invoke( $finto, 400, array( 'error' => 'invalid_grant', 'error_description' => 'Invalid grant: account not found' ) ),
		'Scarica una chiave nuova'
	)
);

verifica(
	'un JWT scaduto viene spiegato come orologio sfasato',
	false !== stripos(
		$messaggio->invoke( $finto, 400, array( 'error' => 'invalid_grant', 'error_description' => 'Invalid JWT: Token must be a short-lived token and in a reasonable timeframe' ) ),
		'orologio'
	)
);

verifica(
	'un 403 rimanda all attivazione dell API',
	false !== stripos( $messaggio->invoke( $finto, 403, array() ), 'Search Console sia attiva' )
);

verifica(
	'un tipo diverso da service_account viene respinto',
	false !== stripos( errore_di( static fn() => ServiceAccount::daJson( '{"type":"authorized_user","client_email":"a@b.c","private_key":"x"}' ) ), 'service_account' )
);

// Coppia di chiavi vera, generata adesso: la firma va verificata sul serio.
$coppia  = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
openssl_pkey_export( $coppia, $privata );
$pubblica = openssl_pkey_get_details( $coppia )['key'];

$chiave = array(
	'type'         => 'service_account',
	'client_email' => 'seo-audit@progetto.iam.gserviceaccount.com',
	'private_key'  => $privata,
);

verifica( 'una chiave valida viene accettata', 'service_account' === ServiceAccount::daJson( json_encode( $chiave ) )['type'] );

$account = new ServiceAccount( $chiave, sys_get_temp_dir() . '/prova-token-' . getmypid() . '.json' );
verifica( 'l indirizzo da autorizzare in Search Console è quello dell account', 'seo-audit@progetto.iam.gserviceaccount.com' === $account->indirizzo() );

// --- Firma della richiesta -------------------------------------------------
echo "\nFirma della richiesta\n";

$metodo = new ReflectionMethod( ServiceAccount::class, 'firma' );
$metodo->setAccessible( true );

$jwt   = $metodo->invoke( $account, array( 'alg' => 'RS256', 'typ' => 'JWT' ), array( 'iss' => 'prova', 'exp' => time() + 3600 ) );
$parti = explode( '.', $jwt );

verifica( 'il JWT ha le tre parti previste', 3 === count( $parti ) );

$decodifica = static fn( $p ) => json_decode( base64_decode( strtr( $p, '-_', '+/' ) ), true );

verifica( 'l intestazione dichiara RS256', 'RS256' === ( $decodifica( $parti[0] )['alg'] ?? '' ) );
verifica( 'il corpo contiene le rivendicazioni', 'prova' === ( $decodifica( $parti[1] )['iss'] ?? '' ) );

$firma = base64_decode( strtr( $parti[2], '-_', '+/' ) );
verifica(
	'la firma è valida per la chiave pubblica corrispondente',
	1 === openssl_verify( $parti[0] . '.' . $parti[1], $firma, $pubblica, OPENSSL_ALGO_SHA256 )
);
verifica(
	'una firma su dati alterati non passa',
	1 !== openssl_verify( $parti[0] . '.' . $parti[1] . 'x', $firma, $pubblica, OPENSSL_ALGO_SHA256 )
);

// --- Riuso del token -------------------------------------------------------
echo "\nRiuso del token\n";

$cache = sys_get_temp_dir() . '/prova-cache-' . getmypid() . '.json';
$conto = new ServiceAccount( $chiave, $cache );

$chiaveCache = new ReflectionMethod( ServiceAccount::class, 'chiaveCache' );
$chiaveCache->setAccessible( true );
$ambito = 'https://www.googleapis.com/auth/webmasters.readonly';

file_put_contents(
	$cache,
	json_encode( array( $chiaveCache->invoke( $conto, $ambito ) => array( 'token' => 'token-salvato', 'scade' => time() + 3600 ) ) )
);

// Se non riusasse il token andrebbe in rete e fallirebbe: qui non c è Google.
verifica( 'un token ancora valido viene riusato senza richiederne un altro', 'token-salvato' === $conto->token( $ambito ) );

file_put_contents(
	$cache,
	json_encode( array( $chiaveCache->invoke( $conto, $ambito ) => array( 'token' => 'token-scaduto', 'scade' => time() + 10 ) ) )
);

$scaduto = new ReflectionMethod( ServiceAccount::class, 'dallaCache' );
$scaduto->setAccessible( true );
verifica( 'un token che sta per scadere viene scartato', '' === $scaduto->invoke( $conto, $ambito ) );

@unlink( $cache );

// --- Proprietà -------------------------------------------------------------
echo "\nNome della proprietà\n";

verifica( 'il dominio nudo diventa una proprietà di dominio', 'sc-domain:esempio.it' === SearchConsole::normalizzaProprieta( 'esempio.it' ) );
verifica( 'sc-domain resta com è', 'sc-domain:esempio.it' === SearchConsole::normalizzaProprieta( 'sc-domain:esempio.it' ) );
verifica( 'un indirizzo completo prende la barra finale', 'https://esempio.it/' === SearchConsole::normalizzaProprieta( 'https://esempio.it' ) );
verifica( 'le maiuscole non rompono il confronto', 'sc-domain:esempio.it' === SearchConsole::normalizzaProprieta( 'Esempio.IT' ) );

// Il pulsante deve portare sulla pagina esatta: aggiungere un utente a una
// proprietà si fa solo dall interfaccia di Google, non esiste un API.
verifica(
	'il collegamento porta agli utenti della proprietà di dominio',
	'https://search.google.com/search-console/users?resource_id=sc-domain%3Aesempio.it' === SearchConsole::urlUtenti( 'sc-domain:esempio.it' )
);
verifica(
	'con una proprietà a prefisso URL l indirizzo viene codificato',
	'https://search.google.com/search-console/users?resource_id=https%3A%2F%2Fesempio.it%2F' === SearchConsole::urlUtenti( 'https://esempio.it/' )
);
verifica(
	'un dominio scritto nudo diventa comunque una proprietà valida nel collegamento',
	false !== strpos( SearchConsole::urlUtenti( 'esempio.it' ), 'sc-domain%3Aesempio.it' )
);
verifica(
	'senza proprietà si apre l elenco generale',
	'https://search.google.com/search-console/users' === SearchConsole::urlUtenti( '' )
);

// --- Lettura dei dati ------------------------------------------------------
echo "\nLettura dei dati\n";

$porta  = 8871;
$server = proc_open(
	sprintf( 'php -S 127.0.0.1:%d %s', $porta, escapeshellarg( __DIR__ . '/mock-gsc.php' ) ),
	array( 1 => array( 'file', '/dev/null', 'w' ), 2 => array( 'file', '/dev/null', 'w' ) ),
	$tubi
);

// Il server impiega un istante ad accettare connessioni.
for ( $i = 0; $i < 50; $i++ ) {
	$prova = @fsockopen( '127.0.0.1', $porta, $n, $m, 0.1 );

	if ( $prova ) {
		fclose( $prova );
		break;
	}

	usleep( 100000 );
}

$cache = sys_get_temp_dir() . '/prova-gsc-' . getmypid() . '.json';
$conto = new ServiceAccount( $chiave, $cache );
$ambito = 'https://www.googleapis.com/auth/webmasters.readonly';
$chiaveCache = new ReflectionMethod( ServiceAccount::class, 'chiaveCache' );
$chiaveCache->setAccessible( true );
file_put_contents( $cache, json_encode( array( $chiaveCache->invoke( $conto, $ambito ) => array( 'token' => 'finto', 'scade' => time() + 3600 ) ) ) );

$console = new SearchConsole( $conto, 'sc-domain:esempio.it', 'http://127.0.0.1:' . $porta );

$siti = $console->siti();
verifica( 'le proprietà accessibili vengono elencate', 'sc-domain:esempio.it' === ( $siti[0]['proprieta'] ?? '' ) );
verifica( 'vengono elencate tutte, non solo la prima', 2 === count( $siti ), count( $siti ) . ' proprietà' );
verifica( 'di ciascuna si sa il livello di permesso', 'siteFullUser' === ( $siti[0]['permesso'] ?? '' ) );

$pagine = $console->rendimento( array( 'dimensioni' => array( 'page' ) ) );
verifica( 'le pagine vengono lette', 3 === count( $pagine ), 'lette ' . count( $pagine ) );
verifica( 'il CTR viene convertito in percentuale', 4.4 === $pagine[0]['ctr'], var_export( $pagine[0]['ctr'] ?? null, true ) );
verifica( 'la posizione viene arrotondata a un decimale', 6.2 === $pagine[0]['posizione'] );
verifica( 'i nomi delle dimensioni diventano chiavi', isset( $pagine[0]['page'] ) );

$query = $console->rendimento( array( 'dimensioni' => array( 'query', 'page' ) ) );
verifica( 'query e pagina arrivano insieme', isset( $query[0]['query'], $query[0]['page'] ) );

$paginato = $console->rendimento( array( 'dimensioni' => array( 'page' ), 'righe' => 2 ) );
verifica( 'la paginazione recupera tutte le righe', 3 === count( $paginato ), 'lette ' . count( $paginato ) );

$sitemap = $console->sitemap();
verifica( 'le sitemap dichiarate vengono lette', 2 === ( $sitemap[0]['avvisi'] ?? 0 ) );

$ispezione = $console->ispeziona( 'https://esempio.it/blog/vecchio/' );
verifica( 'lo stato di indicizzazione viene letto', false !== stripos( $ispezione['copertura'], 'non indicizzata' ) );

// Errori: la spiegazione deve dire cosa fare, non solo il codice.
$vuoto   = new SearchConsole( $conto, 'sc-domain:esempio.it', 'http://127.0.0.1:' . $porta );
$rifiuto = errore_di( static fn() => ( new SearchConsole( new ServiceAccount( $chiave, '/dev/null' ), 'sc-domain:esempio.it', 'http://127.0.0.1:' . $porta ) )->siti() );
verifica( 'senza token la chiamata fallisce', '' !== $rifiuto );

$senzaProprieta = errore_di( static fn() => ( new SearchConsole( $conto, '', 'http://127.0.0.1:' . $porta ) )->rendimento() );
verifica( 'senza proprietà lo dice chiaramente', false !== stripos( $senzaProprieta, 'proprietà' ) );

// I 403 di Search Console hanno due cause diverse, e Google le distingue nel
// testo: questi sono i messaggi veri restituiti nei due casi.
$msg = new ReflectionMethod( SearchConsole::class, 'messaggioErrore' );
$msg->setAccessible( true );
$console403 = new SearchConsole( $conto, 'https://esempio.it/', 'http://127.0.0.1:' . $porta );

$permesso = $msg->invoke(
	$console403,
	403,
	array( 'error' => array( 'message' => "User does not have sufficient permission for site 'https://esempio.it/'. See also: https://support.google.com/webmasters/answer/2451999." ) )
);

verifica( 'il 403 da permesso mancante manda a Utenti e autorizzazioni', false !== stripos( $permesso, 'Utenti e autorizzazioni' ) );
verifica( 'e chiarisce che non c entra l accesso personale', false !== stripos( $permesso, 'accesso personale' ) );
verifica( 'e nomina l account da autorizzare', false !== stripos( $permesso, 'seo-audit@progetto.iam.gserviceaccount.com' ) );
verifica( 'e non parla di API da attivare', false === stripos( $permesso, 'Libreria' ) );

$api = $msg->invoke(
	$console403,
	403,
	array( 'error' => array( 'message' => 'Google Search Console API has not been used in project 123 before or it is disabled.' ) )
);

verifica( 'il 403 da API spenta manda alla Libreria di Google Cloud', false !== stripos( $api, 'Libreria' ) );
verifica( 'e non manda a Utenti e autorizzazioni', false === stripos( $api, 'Utenti e autorizzazioni' ) );

if ( is_resource( $server ) ) {
	proc_terminate( $server );
	proc_close( $server );
}

@unlink( $cache );

// --- Segnali ---------------------------------------------------------------
echo "\nSegnali ricavati dai dati\n";

$dati = array(
	'query'  => array(
		array( 'query' => 'siti web palermo', 'page' => 'https://esempio.it/blog/guida/', 'clic' => 2, 'impression' => 500, 'ctr' => 0.4, 'posizione' => 12.8 ),
		array( 'query' => 'siti web palermo', 'page' => 'https://esempio.it/servizi/', 'clic' => 5, 'impression' => 400, 'ctr' => 1.25, 'posizione' => 14.0 ),
		array( 'query' => 'agenzia web palermo', 'page' => 'https://esempio.it/servizi/', 'clic' => 3, 'impression' => 800, 'ctr' => 0.37, 'posizione' => 3.4 ),
		array( 'query' => 'roba rara', 'page' => 'https://esempio.it/blog/vecchio/', 'clic' => 0, 'impression' => 4, 'ctr' => 0.0, 'posizione' => 44.0 ),
	),
	'pagine' => array(
		array( 'page' => 'https://esempio.it/servizi/', 'clic' => 40, 'impression' => 900, 'ctr' => 4.4, 'posizione' => 9.5 ),
		array( 'page' => 'https://esempio.it/blog/guida/', 'clic' => 2, 'impression' => 500, 'ctr' => 0.4, 'posizione' => 12.8 ),
	),
	'pagine_prec' => array(
		array( 'page' => 'https://esempio.it/servizi/', 'clic' => 60, 'impression' => 950, 'ctr' => 6.3, 'posizione' => 4.1 ),
		array( 'page' => 'https://esempio.it/blog/guida/', 'clic' => 3, 'impression' => 520, 'ctr' => 0.6, 'posizione' => 12.5 ),
	),
	'documenti' => array(
		array( 'url' => 'https://esempio.it/servizi/', 'titolo' => 'Servizi' ),
		array( 'url' => 'https://esempio.it/blog/guida/', 'titolo' => 'Guida' ),
		array( 'url' => 'https://www.esempio.it/blog/mai-vista/', 'titolo' => 'Articolo mai mostrato' ),
	),
);

$segnali = Segnali::deriva( $dati );
$tipi    = array_count_values( array_column( $segnali, 'tipo' ) );

verifica( 'la pagina in posizione 12,8 con 500 impression è "a un passo"', ! empty( $tipi['quasi_prima_pagina'] ) );
verifica( 'la ricerca con 800 impression in posizione 3,4 e pochi clic è un problema di titolo', ! empty( $tipi['titolo_che_non_rende'] ) );
verifica( 'due pagine sulla stessa ricerca diventano cannibalizzazione', ! empty( $tipi['cannibalizzazione'] ) );
verifica( 'la pagina scesa di 5 posizioni viene segnalata', ! empty( $tipi['in_calo'] ) );
verifica( 'il contenuto senza nessuna impression viene segnalato', ! empty( $tipi['mai_mostrata'] ) );

$recenti = Segnali::deriva(
	array(
		'periodo'   => array( 'da' => '2026-08-12', 'a' => '2026-09-08' ),
		'pagine'    => array(),
		'documenti' => array(
			array( 'url' => 'https://esempio.it/vecchio/', 'titolo' => 'Vecchio', 'pubblicato' => '2025-03-01 10:00:00' ),
			array( 'url' => 'https://esempio.it/appena-uscito/', 'titolo' => 'Appena uscito', 'pubblicato' => '2026-09-05 10:00:00' ),
		),
	)
);

verifica( 'un contenuto vecchio senza impression viene segnalato', 1 === count( $recenti ), count( $recenti ) . ' segnali' );
verifica(
	'un contenuto appena pubblicato non viene scambiato per invisibile',
	empty( array_filter( $recenti, static fn( $s ) => false !== strpos( $s['url'], 'appena-uscito' ) ) )
);
verifica( 'la ricerca con 4 impression non genera rumore', empty( array_filter( $segnali, static fn( $s ) => 'roba rara' === $s['query'] ) ) );

$primo = $segnali[0];
verifica( 'i segnali sono ordinati per priorità', $primo['priorita'] >= end( $segnali )['priorita'] );
verifica( 'ogni segnale dice cosa fare', '' !== $primo['azione'] && '' !== $primo['spiegazione'] );

$cannibale = array_values( array_filter( $segnali, static fn( $s ) => 'cannibalizzazione' === $s['tipo'] ) )[0];
verifica( 'la cannibalizzazione elenca le pagine in gara', 2 === count( $cannibale['concorrenti'] ) );
verifica( 'la cannibalizzazione somma le impression delle due pagine', 900 === $cannibale['impression'] );

// Quando Google ha già scelto una pagina, non è un problema da risolvere.
$scelta = Segnali::deriva(
	array(
		'query' => array(
			array( 'query' => 'x', 'page' => 'https://esempio.it/a/', 'clic' => 10, 'impression' => 950, 'ctr' => 1.0, 'posizione' => 4.0 ),
			array( 'query' => 'x', 'page' => 'https://esempio.it/b/', 'clic' => 0, 'impression' => 20, 'ctr' => 0.0, 'posizione' => 40.0 ),
		),
	)
);
verifica(
	'se una pagina prende quasi tutte le impression non si parla di cannibalizzazione',
	empty( array_filter( $scelta, static fn( $s ) => 'cannibalizzazione' === $s['tipo'] ) )
);

// L indirizzo con e senza www è lo stesso contenuto.
$conWww = Segnali::deriva(
	array(
		'pagine'    => array( array( 'page' => 'https://esempio.it/pagina/', 'clic' => 1, 'impression' => 100, 'ctr' => 1.0, 'posizione' => 5.0 ) ),
		'documenti' => array( array( 'url' => 'https://www.esempio.it/pagina', 'titolo' => 'Pagina' ) ),
	)
);
verifica( 'www e barra finale non fanno sembrare invisibile una pagina vista', empty( $conWww ) );

echo "\n" . ( $errori ? "✖ $errori verifiche fallite\n\n" : "✔ tutte le verifiche superate\n\n" );

exit( $errori ? 1 : 0 );
