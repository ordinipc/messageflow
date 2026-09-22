<?php
/**
 * Installazione guidata: dati del database, tabelle, primo accesso.
 * Dopo l'installazione questo file può essere cancellato.
 */

define( 'PC_AVVIO', true );
require_once __DIR__ . '/inc/core.php';

$passo    = 1;
$errore   = '';
$fatto    = false;
$config   = PC_RADICE . '/config.php';

if ( db_configurato() ) {
	try {
		db();
		// Installato vuol dire: tabelle create e almeno un utente che può entrare.
		$passo = ( db_installato() && ( utenti_conta() > 0 || '' !== impostazione( 'password_hash', '' ) ) ) ? 3 : 2;
	} catch ( PDOException $ex ) {
		$errore = 'Connessione fallita: ' . $ex->getMessage();
		$passo  = 1;
	}
}

/* --- Passo 1: scrittura di config.php ------------------------------------ */
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['azione'] ) && 'database' === $_POST['azione'] ) {
	$driver = 'sqlite' === ( $_POST['driver'] ?? '' ) ? 'sqlite' : 'mysql';
	$dati   = array(
		'driver'   => $driver,
		'host'     => trim( (string) ( $_POST['host'] ?? 'localhost' ) ),
		'porta'    => (int) ( $_POST['porta'] ?? 3306 ),
		'nome'     => trim( (string) ( $_POST['nome'] ?? '' ) ),
		'utente'   => trim( (string) ( $_POST['utente'] ?? '' ) ),
		'password' => (string) ( $_POST['password'] ?? '' ),
		'prefisso' => preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $_POST['prefisso'] ?? 'pc_' ) ) ),
	);
	if ( 'mysql' === $driver && ( '' === $dati['nome'] || '' === $dati['utente'] ) ) {
		$errore = 'Nome del database e utente sono obbligatori.';
	} else {
		// Il file SQLite prende un nome imprevedibile: se l'hosting ignora
		// .htaccess, nessuno può scaricarlo indovinando l'indirizzo.
		$file_sqlite = 'portale-' . bin2hex( random_bytes( 8 ) ) . '.sqlite';
		$php = "<?php\n"
			. "/**\n * Dati di connessione del Portale Città.\n * Generato dall'installazione guidata.\n */\n\n"
			. "return array(\n"
			. "\t'driver'   => " . var_export( $dati['driver'], true ) . ",\n"
			. "\t'host'     => " . var_export( $dati['host'], true ) . ",\n"
			. "\t'porta'    => " . var_export( $dati['porta'], true ) . ",\n"
			. "\t'nome'     => " . var_export( $dati['nome'], true ) . ",\n"
			. "\t'utente'   => " . var_export( $dati['utente'], true ) . ",\n"
			. "\t'password' => " . var_export( $dati['password'], true ) . ",\n"
			. "\t'prefisso' => " . var_export( '' === $dati['prefisso'] ? 'pc_' : $dati['prefisso'], true ) . ",\n"
			. "\t'charset'  => 'utf8mb4',\n"
			. "\t'file'     => __DIR__ . '/dati/" . $file_sqlite . "',\n"
			. ");\n";
		if ( false === @file_put_contents( $config, $php ) ) {
			$errore = 'Impossibile scrivere config.php: dai il permesso di scrittura alla cartella, oppure crea il file a mano con questo contenuto:' . "\n\n" . $php;
		} else {
			vai_a( 'install.php' );
		}
	}
}

/* --- Passo 2: tabelle e amministratore ----------------------------------- */
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['azione'] ) && 'avvio' === $_POST['azione'] ) {
	$brand    = trim( (string) ( $_POST['brand'] ?? '' ) );
	$url      = rtrim( trim( (string) ( $_POST['sito_url'] ?? '' ) ), '/' );
	$password = (string) ( $_POST['password'] ?? '' );
	$utente   = utente_nome_pulito( $_POST['utente'] ?? 'admin' );
	$principale = rtrim( trim( (string) ( $_POST['sito_principale'] ?? '' ) ), '/' );
	$email    = trim( (string) ( $_POST['email'] ?? '' ) );
	if ( '' === $utente ) {
		$utente = 'admin';
	}
	if ( mb_strlen( $password ) < 8 ) {
		$errore = 'La password deve avere almeno 8 caratteri.';
		$passo  = 2;
	} else {
		try {
			db_installa();
			impostazioni_salva( array(
				'brand'           => '' === $brand ? 'Il mio sito' : $brand,
				'sito_url'        => $url,
				'email'           => $email,
				'sito_principale' => $principale,
			) );
			utente_salva( array(
				'nome'          => $utente,
				'etichetta'     => 'Amministratore',
				'email'         => $email,
				'password_hash' => password_hash( $password, PASSWORD_DEFAULT ),
				'ruolo'         => 'amministratore',
				'stato'         => 'attivo',
			) );
			$fatto = true;
			$passo = 3;
		} catch ( PDOException $ex ) {
			$errore = 'Errore del database: ' . $ex->getMessage();
			$passo  = 2;
		}
	}
}

$cfg = db_config();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installazione — Portale Città</title>
<link rel="stylesheet" href="admin/admin.css">
</head>
<body class="pc-install">
<div class="pc-install__box">
	<h1>Portale Città</h1>
	<p class="pc-install__sub">Installazione guidata — passo <?php echo (int) $passo; ?> di 3</p>

	<?php if ( '' !== $errore ) : ?>
		<div class="pc-avviso pc-avviso--errore"><pre><?php echo e( $errore ); ?></pre></div>
	<?php endif; ?>

	<?php if ( 1 === $passo ) : ?>
		<form method="post">
			<input type="hidden" name="azione" value="database">
			<h2>1. Database</h2>
			<label>Tipo di database
				<select name="driver" id="pc-driver">
					<option value="mysql">MySQL / MariaDB (consigliato)</option>
					<option value="sqlite">SQLite (nessun database da creare)</option>
				</select>
			</label>
			<div id="pc-mysql">
				<label>Host <input type="text" name="host" value="localhost"></label>
				<label>Porta <input type="number" name="porta" value="3306"></label>
				<label>Nome del database <input type="text" name="nome" placeholder="portale_citta"></label>
				<label>Utente <input type="text" name="utente"></label>
				<label>Password <input type="password" name="password"></label>
			</div>
			<label>Prefisso delle tabelle <input type="text" name="prefisso" value="pc_"></label>
			<button class="pc-btn" type="submit">Salva e continua</button>
		</form>
		<script>
		document.getElementById('pc-driver').addEventListener('change',function(){
			document.getElementById('pc-mysql').style.display = this.value==='sqlite' ? 'none' : '';
		});
		</script>

	<?php elseif ( 2 === $passo ) : ?>
		<form method="post">
			<input type="hidden" name="azione" value="avvio">
			<h2>2. Il tuo sito</h2>
			<p class="pc-nota">Connessione riuscita (<?php echo e( $cfg['driver'] ); ?>). Ora creiamo le tabelle e il tuo accesso.</p>
			<label>Nome dell'attività <input type="text" name="brand" placeholder="Chiavi Italia" required></label>
			<label>Indirizzo del portale
				<input type="url" name="sito_url" placeholder="https://www.tuosito.it/citta" value="<?php echo e( base_url() ); ?>">
				<small>Senza barra finale. È l'indirizzo da cui si raggiungono le pagine città.</small>
			</label>
			<label>Indirizzo del sito principale
				<input type="url" name="sito_principale" placeholder="https://www.tuosito.it">
				<small>Il sito a cui il portale si appoggia: il logo in alto porterà lì.
					Vuoto: si usa la radice del dominio.</small>
			</label>
			<label>Nome utente
				<input type="text" name="utente" value="admin" autocomplete="off">
				<small>Il nome con cui entrerai. Minuscolo, senza spazi. Altri utenti si aggiungono dopo.</small>
			</label>
			<label>La tua email
				<input type="email" name="email" placeholder="tu@tuosito.it">
				<small>Serve per ricevere i messaggi dal modulo contatti e per entrare
					anche scrivendo l'email al posto del nome utente.</small>
			</label>
			<label>Password di amministrazione
				<input type="password" name="password" minlength="8" required>
				<small>Almeno 8 caratteri. Servirà per entrare nel pannello.</small>
			</label>
			<button class="pc-btn" type="submit">Crea le tabelle ed entra</button>
		</form>

	<?php else : ?>
		<h2>3. Tutto pronto</h2>
		<?php if ( $fatto ) : ?>
			<p>Le tabelle sono state create e il tuo accesso è attivo.</p>
		<?php else : ?>
			<p>Il portale risulta già installato.</p>
		<?php endif; ?>
		<p class="pc-nota"><strong>Per sicurezza cancella il file <code>install.php</code> dal server.</strong></p>
		<p><a class="pc-btn" href="admin.php">Vai all'amministrazione</a></p>
	<?php endif; ?>
</div>
</body>
</html>
