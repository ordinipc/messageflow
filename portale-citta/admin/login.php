<?php
/** Schermata di accesso. */
defined( 'PC_AVVIO' ) || exit;

// Con un utente solo non c'è niente da distinguere: il campo si mostra
// lo stesso, ma quello che ci finisce dentro non decide nulla. Serve a non
// far rifiutare una password giusta quando il gestore di password del
// browser riempie il campo da sé con un indirizzo che non corrisponde.
$uno    = 1 === utenti_conta( true );
$errore = '';
$nome   = trim( (string) ( $_POST['nome'] ?? '' ) );

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( accedi( (string) ( $_POST['password'] ?? '' ), $nome ) ) {
		vai_a( 'admin.php' );
	}
	$errore = $uno ? 'Password non corretta.' : 'Nome utente o password non corretti.';
	usleep( 400000 );
}
$imp = impostazioni();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Accesso — <?php echo e( $imp['brand'] ); ?></title>
<link rel="stylesheet" href="admin/admin.css">
</head>
<body class="pc-install">
<div class="pc-install__box">
	<h1><?php echo e( $imp['brand'] ); ?></h1>
	<p class="pc-install__sub">Amministrazione del portale città</p>
	<?php if ( '' !== $errore ) : ?>
		<div class="pc-avviso pc-avviso--errore"><?php echo e( $errore ); ?></div>
	<?php endif; ?>
	<form method="post">
		<label>Nome utente o email
			<input type="text" name="nome" value="<?php echo e( $nome ); ?>" autocomplete="username"
				<?php echo $uno ? '' : 'required'; ?> autofocus>
			<?php if ( $uno ) : ?>
				<small>C'è un solo utente: basta la password.</small>
			<?php endif; ?>
		</label>
		<label>Password
			<input type="password" name="password" autocomplete="current-password" required>
		</label>
		<button class="pc-btn" type="submit">Entra</button>
	</form>
</div>
</body>
</html>
