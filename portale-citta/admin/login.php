<?php
/** Schermata di accesso. */
defined( 'PC_AVVIO' ) || exit;

$errore = '';
if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( accedi( (string) ( $_POST['password'] ?? '' ) ) ) {
		vai_a( 'admin.php' );
	}
	$errore = 'Password non corretta.';
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
		<label>Password <input type="password" name="password" autofocus required></label>
		<button class="pc-btn" type="submit">Entra</button>
	</form>
</div>
</body>
</html>
