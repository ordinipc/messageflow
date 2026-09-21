<?php
/** Utenti dell'amministrazione: chi entra e fin dove può arrivare. */
defined( 'PC_AVVIO' ) || exit;

$io = utente_corrente();

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	verifica_token();

	$id        = trim( (string) ( $_POST['id'] ?? '' ) );
	$esistente = '' === $id ? null : utente( $id );
	$nome      = utente_nome_pulito( $_POST['nome'] ?? '' );
	$password  = (string) ( $_POST['password'] ?? '' );
	$ruolo     = ruolo_valido( $_POST['ruolo'] ?? '' );
	$stato     = 'sospeso' === ( $_POST['stato'] ?? '' ) ? 'sospeso' : 'attivo';
	$errore    = '';

	if ( '' === $nome ) {
		$errore = 'Il nome utente è obbligatorio: lettere, numeri, punto o trattino.';
	} elseif ( ! utente_nome_libero( $nome, $id ) ) {
		$errore = 'Il nome «' . $nome . '» è già usato da un altro utente.';
	} elseif ( ! $esistente && mb_strlen( $password ) < 8 ) {
		$errore = 'Serve una password di almeno 8 caratteri per il nuovo utente.';
	} elseif ( '' !== $password && mb_strlen( $password ) < 8 ) {
		$errore = 'La password deve avere almeno 8 caratteri: non è stata cambiata.';
	}

	// Non ci si può chiudere fuori da soli: l'ultimo amministratore attivo
	// resta amministratore e resta attivo, qualunque cosa dica il modulo.
	if ( '' === $errore && $esistente && 'amministratore' === $esistente['ruolo'] && 'attivo' === $esistente['stato'] ) {
		if ( ( 'amministratore' !== $ruolo || 'attivo' !== $stato ) && amministratori_attivi() <= 1 ) {
			$errore = 'È l\'unico amministratore attivo: prima nominane un altro.';
		}
	}

	if ( '' !== $errore ) {
		avviso( $errore, 'errore' );
		vai_a( 'admin.php?p=utenti' . ( $esistente ? '&id=' . rawurlencode( $id ) : '' ) );
	}

	$dati = array(
		'id'            => $esistente ? $esistente['id'] : '',
		'nome'          => $nome,
		'etichetta'     => trim( (string) ( $_POST['etichetta'] ?? '' ) ),
		'email'         => trim( (string) ( $_POST['email'] ?? '' ) ),
		'ruolo'         => $ruolo,
		'stato'         => $stato,
		'password_hash' => $esistente ? $esistente['password_hash'] : '',
		'creato'        => $esistente ? $esistente['creato'] : date( 'Y-m-d H:i' ),
		'ultimo_accesso'=> $esistente ? $esistente['ultimo_accesso'] : '',
	);
	if ( '' !== $password ) {
		$dati['password_hash'] = password_hash( $password, PASSWORD_DEFAULT );
	}
	$salvato = utente_salva( $dati );

	avviso( $esistente ? 'Utente aggiornato.' : 'Utente «' . $salvato['nome'] . '» creato.' );
	vai_a( 'admin.php?p=utenti' );
}

if ( isset( $_GET['azione'] ) && 'elimina' === $_GET['azione'] ) {
	verifica_token();
	$vittima = utente( (string) ( $_GET['id'] ?? '' ) );
	if ( ! $vittima ) {
		avviso( 'Utente non trovato.', 'errore' );
	} elseif ( $io && $vittima['id'] === $io['id'] ) {
		avviso( 'Non puoi eliminare l\'utente con cui sei collegato.', 'errore' );
	} elseif ( 'amministratore' === $vittima['ruolo'] && 'attivo' === $vittima['stato'] && amministratori_attivi() <= 1 ) {
		avviso( 'È l\'unico amministratore attivo: non si può eliminare.', 'errore' );
	} else {
		utente_elimina( $vittima['id'] );
		avviso( 'Utente «' . $vittima['nome'] . '» eliminato.' );
	}
	vai_a( 'admin.php?p=utenti' );
}

$modifica = isset( $_GET['id'] ) ? utente( (string) $_GET['id'] ) : null;
$lista    = utenti_tutti();
$elenco   = ruoli();
$tok      = '&token=' . rawurlencode( token() );
?>

<div class="pc-titolo">
	<div>
		<h1>Utenti</h1>
		<p>Chi può entrare nel pannello. Ognuno ha il suo nome e la sua password: così si sa sempre chi ha scritto cosa.</p>
	</div>
</div>

<div class="pc-griglia-2">
<div>
	<table class="pc-tabella">
		<thead><tr><th>Utente</th><th>Ruolo</th><th>Ultimo accesso</th><th></th></tr></thead>
		<tbody>
		<?php foreach ( $lista as $u ) : ?>
			<tr>
				<td>
					<strong><?php echo e( $u['nome'] ); ?></strong>
					<?php if ( $io && $u['id'] === $io['id'] ) : ?>
						<span class="pc-nota">(sei tu)</span>
					<?php endif; ?>
					<?php if ( 'attivo' !== $u['stato'] ) : ?>
						<span class="pc-nota">— sospeso, non può entrare</span>
					<?php endif; ?>
					<?php if ( ! vuoto( $u['etichetta'] ) && $u['etichetta'] !== maiuscola( $u['nome'] ) ) : ?>
						<br><span class="pc-nota"><?php echo e( $u['etichetta'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! vuoto( $u['email'] ) ) : ?>
						<br><span class="pc-nota"><?php echo e( $u['email'] ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo e( $elenco[ $u['ruolo'] ]['nome'] ?? $u['ruolo'] ); ?></td>
				<td><?php echo vuoto( $u['ultimo_accesso'] ) ? '<span class="pc-nota">mai</span>' : e( $u['ultimo_accesso'] ); ?></td>
				<td class="pc-tabella__azioni">
					<a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=utenti&id=<?php echo e( $u['id'] ); ?>">Modifica</a>
					<?php if ( ! $io || $u['id'] !== $io['id'] ) : ?>
						<a class="pc-btn pc-btn--rosso pc-btn--piccolo" href="admin.php?p=utenti&azione=elimina&id=<?php echo e( $u['id'] ) . $tok; ?>"
							data-conferma="Eliminare l'utente &quot;<?php echo e( $u['nome'] ); ?>&quot;? Non potrà più entrare.">Elimina</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<div class="pc-scheda" style="margin-top:18px">
		<h2>I due ruoli</h2>
		<?php foreach ( $elenco as $chiave => $r ) : ?>
			<p class="pc-scheda__nota" style="margin:0 0 10px">
				<strong><?php echo e( $r['nome'] ); ?></strong> — <?php echo e( $r['nota'] ); ?>
			</p>
		<?php endforeach; ?>
		<p class="pc-scheda__nota" style="margin:0">
			Se sospendi un utente invece di eliminarlo, il suo account resta ma non entra più:
			comodo per chi lavora con te solo ogni tanto.
		</p>
	</div>
</div>

<aside>
	<div class="pc-scheda">
		<h2><?php echo $modifica ? 'Modifica utente' : 'Nuovo utente'; ?></h2>
		<form method="post">
			<?php echo campo_token(); ?>
			<input type="hidden" name="id" value="<?php echo e( $modifica['id'] ?? '' ); ?>">
			<label>Nome utente *
				<input type="text" name="nome" value="<?php echo e( $modifica['nome'] ?? '' ); ?>" required
					autocomplete="off" placeholder="giuseppe">
				<small>È quello che si scrive per entrare: minuscolo, senza spazi.</small>
			</label>
			<label>Nome e cognome
				<input type="text" name="etichetta" value="<?php echo e( $modifica['etichetta'] ?? '' ); ?>" placeholder="Giuseppe Rossi">
			</label>
			<label>Email
				<input type="email" name="email" value="<?php echo e( $modifica['email'] ?? '' ); ?>">
			</label>
			<label><?php echo $modifica ? 'Nuova password' : 'Password *'; ?>
				<input type="password" name="password" autocomplete="new-password" minlength="8" <?php echo $modifica ? '' : 'required'; ?>>
				<small><?php echo $modifica ? 'Lascia vuoto per non cambiarla. ' : ''; ?>Minimo 8 caratteri.</small>
			</label>
			<label>Ruolo
				<select name="ruolo">
					<?php foreach ( $elenco as $chiave => $r ) : ?>
						<option value="<?php echo e( $chiave ); ?>" <?php selected_pc( $modifica['ruolo'] ?? 'redattore', $chiave ); ?>>
							<?php echo e( $r['nome'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Stato
				<select name="stato">
					<option value="attivo" <?php selected_pc( $modifica['stato'] ?? 'attivo', 'attivo' ); ?>>Attivo</option>
					<option value="sospeso" <?php selected_pc( $modifica['stato'] ?? 'attivo', 'sospeso' ); ?>>Sospeso</option>
				</select>
			</label>
			<button class="pc-btn" type="submit"><?php echo $modifica ? 'Salva' : 'Aggiungi utente'; ?></button>
			<?php if ( $modifica ) : ?>
				<a class="pc-btn pc-btn--ghost" href="admin.php?p=utenti">Annulla</a>
			<?php endif; ?>
		</form>
	</div>
</aside>
</div>
