<?php
/**
 * Verifica dei requisiti del server.
 *
 * Pagina volutamente autonoma: non carica l applicazione, così resta
 * consultabile anche quando qualcosa impedisce l avvio. Serve a capire in un
 * colpo d occhio se lo spazio web è pronto e a quale indirizzo aprire l app.
 *
 * @package SeoGeoAudit
 */

$radice  = dirname( __DIR__ );
$storage = $radice . '/storage';

if ( ! is_dir( $storage ) ) {
	@mkdir( $storage, 0775, true );
}

/**
 * Converte un valore del php.ini (es. "64M") in byte.
 *
 * @param string $valore Valore.
 * @return int
 */
function in_byte( $valore ) {
	$valore = trim( (string) $valore );
	$numero = (int) $valore;

	switch ( strtolower( substr( $valore, -1 ) ) ) {
		case 'g':
			return $numero * 1024 * 1024 * 1024;
		case 'm':
			return $numero * 1024 * 1024;
		case 'k':
			return $numero * 1024;
		default:
			return $numero;
	}
}

$controlli = array();

$controlli[] = array(
	'nome'   => 'Versione di PHP',
	'valore' => PHP_VERSION,
	'esito'  => version_compare( PHP_VERSION, '7.4', '>=' ) ? 'ok' : 'grave',
	'nota'   => 'Serve PHP 7.4 o superiore.',
);

$estensioni = array(
	'pdo'        => 'accesso al database',
	'pdo_sqlite' => 'database SQLite (predefinito)',
	'xmlreader'  => 'lettura dell export WordPress',
	'simplexml'  => 'lettura dell export WordPress',
	'mbstring'   => 'gestione dei testi accentati',
);

foreach ( $estensioni as $estensione => $a_cosa_serve ) {
	$controlli[] = array(
		'nome'   => 'Estensione ' . $estensione,
		'valore' => extension_loaded( $estensione ) ? 'attiva' : 'assente',
		'esito'  => extension_loaded( $estensione ) ? 'ok' : 'grave',
		'nota'   => ucfirst( $a_cosa_serve ) . '.',
	);
}

$controlli[] = array(
	'nome'   => 'Estensione zip',
	'valore' => extension_loaded( 'zip' ) ? 'attiva' : 'assente',
	'esito'  => extension_loaded( 'zip' ) ? 'ok' : 'medio',
	'nota'   => 'Senza zip il plugin viene generato come cartella invece che come archivio pronto.',
);

$controlli[] = array(
	'nome'   => 'Estensione pdo_mysql',
	'valore' => extension_loaded( 'pdo_mysql' ) ? 'attiva' : 'assente',
	'esito'  => extension_loaded( 'pdo_mysql' ) ? 'ok' : 'medio',
	'nota'   => 'Necessaria solo se in config.php scegli MySQL al posto di SQLite.',
);

$scrivibile  = is_dir( $storage ) && is_writable( $storage );
$controlli[] = array(
	'nome'   => 'Cartella storage/ scrivibile',
	'valore' => $scrivibile ? 'sì' : 'no',
	'esito'  => $scrivibile ? 'ok' : 'grave',
	'nota'   => $scrivibile
		? 'Qui vengono creati database, upload e file generati.'
		: 'Imposta i permessi 775 sulla cartella storage/ dal pannello dell hosting o via FTP.',
);

$upload      = in_byte( ini_get( 'upload_max_filesize' ) );
$controlli[] = array(
	'nome'   => 'upload_max_filesize',
	'valore' => ini_get( 'upload_max_filesize' ),
	'esito'  => $upload >= 32 * 1024 * 1024 ? 'ok' : ( $upload >= 16 * 1024 * 1024 ? 'medio' : 'grave' ),
	'nota'   => 'Un export WordPress con qualche centinaio di articoli pesa 5-20 MB: consigliati 64M.',
);

$post        = in_byte( ini_get( 'post_max_size' ) );
$controlli[] = array(
	'nome'   => 'post_max_size',
	'valore' => ini_get( 'post_max_size' ),
	'esito'  => $post >= $upload ? 'ok' : 'grave',
	'nota'   => 'Deve essere uguale o superiore a upload_max_filesize, altrimenti il caricamento fallisce senza messaggio.',
);

$memoria     = in_byte( ini_get( 'memory_limit' ) );
$controlli[] = array(
	'nome'   => 'memory_limit',
	'valore' => ini_get( 'memory_limit' ),
	'esito'  => ( -1 === $memoria || $memoria >= 256 * 1024 * 1024 ) ? 'ok' : 'medio',
	'nota'   => 'L analisi di 300 articoli usa circa 60 MB: consigliati 256M.',
);

$tempo       = (int) ini_get( 'max_execution_time' );
$controlli[] = array(
	'nome'   => 'max_execution_time',
	'valore' => 0 === $tempo ? 'illimitato' : $tempo . ' s',
	'esito'  => ( 0 === $tempo || $tempo >= 120 ) ? 'ok' : 'medio',
	'nota'   => 'Un sito da 300 articoli richiede pochi secondi, ma con siti grandi servono almeno 300 s.',
);

// Prova di scrittura reale sul database: è il controllo che conta davvero.
$prova = 'non eseguita';
$esito_prova = 'medio';

if ( $scrivibile && extension_loaded( 'pdo_sqlite' ) ) {
	try {
		$file = $storage . '/prova-scrittura.sqlite';
		$pdo  = new PDO( 'sqlite:' . $file );
		$pdo->exec( 'CREATE TABLE IF NOT EXISTS prova (id INTEGER PRIMARY KEY)' );
		$pdo  = null;
		unlink( $file );
		$prova       = 'riuscita';
		$esito_prova = 'ok';
	} catch ( Throwable $e ) {
		$prova       = 'fallita: ' . $e->getMessage();
		$esito_prova = 'grave';
	}
}

$controlli[] = array(
	'nome'   => 'Creazione del database',
	'valore' => $prova,
	'esito'  => $esito_prova,
	'nota'   => 'Il database viene creato da solo al primo avvio: non esiste una procedura di installazione da eseguire.',
);

$gravi  = count( array_filter( $controlli, static fn( $c ) => 'grave' === $c['esito'] ) );
$avvisi = count( array_filter( $controlli, static fn( $c ) => 'medio' === $c['esito'] ) );

$base = rtrim( dirname( $_SERVER['SCRIPT_NAME'] ), '/' );

?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Verifica dei requisiti — SEO &amp; GEO Audit</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="testa">
	<div class="contenitore">
		<a class="marchio" href="index.php">SEO &amp; GEO <span>Audit</span></a>
		<nav><a class="bottone" href="index.php">Vai all applicazione</a></nav>
	</div>
</header>

<main class="contenitore">
	<section class="intestazione">
		<h1>Verifica dei requisiti</h1>
		<p class="guida">
			<?php if ( $gravi ) : ?>
				Ci sono <strong><?php echo $gravi; ?></strong> problemi da risolvere prima di usare l applicazione.
			<?php elseif ( $avvisi ) : ?>
				Tutto essenziale è a posto. Restano <strong><?php echo $avvisi; ?></strong> avvisi non bloccanti.
			<?php else : ?>
				Il server è pronto: puoi caricare l esportazione WordPress.
			<?php endif; ?>
		</p>
	</section>

	<section class="scheda">
		<h2>Indirizzo dell applicazione</h2>
		<p>Apri questo indirizzo per usare il programma:</p>
		<p class="mono"><strong><?php echo htmlspecialchars( ( isset( $_SERVER['HTTPS'] ) ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $base . '/index.php', ENT_QUOTES ); ?></strong></p>
		<p class="nota">Non esiste una pagina di installazione: il database e le tabelle vengono creati automaticamente alla prima apertura.</p>
	</section>

	<section class="scheda">
		<h2>Controlli</h2>
		<div class="tabellabox">
			<table>
				<thead><tr><th>Requisito</th><th>Valore</th><th>Esito</th><th>Nota</th></tr></thead>
				<tbody>
				<?php foreach ( $controlli as $c ) : ?>
					<tr>
						<td><strong><?php echo htmlspecialchars( $c['nome'], ENT_QUOTES ); ?></strong></td>
						<td class="mono"><?php echo htmlspecialchars( $c['valore'], ENT_QUOTES ); ?></td>
						<td>
							<span class="tag <?php echo $c['esito']; ?>">
								<?php echo 'ok' === $c['esito'] ? 'ok' : ( 'grave' === $c['esito'] ? 'da risolvere' : 'avviso' ); ?>
							</span>
						</td>
						<td class="stretta"><?php echo htmlspecialchars( $c['nota'], ENT_QUOTES ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>

	<section class="scheda">
		<h2>Se i limiti di caricamento sono troppo bassi</h2>
		<p>Crea un file <code>.user.ini</code> nella cartella <code>public/</code> con queste righe (su molti hosting condivisi funziona subito, altrove va usato il pannello PHP):</p>
		<pre class="mono">upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 256M
max_execution_time = 300</pre>
		<p class="nota">In alternativa puoi evitare del tutto il caricamento web: carica l export via FTP ed esegui <code>php cli/audit.php export.xml</code> dalla riga di comando, se l hosting la mette a disposizione.</p>
	</section>
</main>

<footer class="piede"><div class="contenitore">Pagina di sola diagnosi: non modifica nulla.</div></footer>
</body>
</html>
