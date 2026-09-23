<?php
/** Tipi di servizio: il catalogo da cui nascono le pagine servizio. */
defined( 'PC_AVVIO' ) || exit;

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	verifica_token();
	$sid  = '' === (string) ( $_POST['id'] ?? '' ) ? nuovo_id() : (string) $_POST['id'];
	$nome = trim( (string) ( $_POST['nome'] ?? '' ) );
	if ( ! vuoto( $nome ) ) {
		servizio_salva( array(
			'id'          => $sid,
			'slug'        => servizio_slug_libero( vuoto( $_POST['slug'] ?? '' ) ? $nome : $_POST['slug'], $sid ),
			'nome'        => $nome,
			'descrizione' => trim( (string) ( $_POST['descrizione'] ?? '' ) ),
			'icona'       => trim( (string) ( $_POST['icona'] ?? '' ) ),
			'ordine'      => (int) ( $_POST['ordine'] ?? 10 ),
		) );
		avviso( 'Tipo di servizio salvato.' );
	} else {
		avviso( 'Il nome è obbligatorio.', 'errore' );
	}
	vai_a( 'admin.php?p=servizi' );
}

if ( isset( $_GET['azione'] ) && 'elimina' === $_GET['azione'] ) {
	verifica_token();
	servizio_elimina( (string) ( $_GET['id'] ?? '' ) );
	avviso( 'Tipo di servizio eliminato. Le pagine già create restano.' );
	vai_a( 'admin.php?p=servizi' );
}

$modifica = isset( $_GET['id'] ) ? servizio( (string) $_GET['id'] ) : null;
$lista    = servizi();
$citta    = citta_tutte();
$tok      = '&token=' . rawurlencode( token() );
?>

<div class="pc-titolo">
	<div>
		<h1>Tipi di servizio</h1>
		<p>Il catalogo dei servizi. Servono per creare in un colpo solo la stessa pagina in più città.</p>
	</div>
</div>

<div class="pc-griglia-2">
<div>
	<?php if ( empty( $lista ) ) : ?>
		<div class="pc-scheda pc-vuoto">
			<h3>Nessun tipo di servizio</h3>
			<p>Aggiungine uno qui a fianco: per esempio "Duplicazione chiavi auto".</p>
		</div>
	<?php else : ?>
		<table class="pc-tabella">
			<thead><tr><th>Servizio</th><th>Slug</th><th>Pagine collegate</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $lista as $s ) : ?>
				<?php
				$collegate = (int) db_valore( 'SELECT COUNT(*) FROM ' . db_tab( 'pagine' ) . ' WHERE servizio_id = ?', array( $s['id'] ), 0 );
				?>
				<tr>
					<td><strong><?php echo e( $s['nome'] ); ?></strong>
						<?php if ( ! vuoto( $s['descrizione'] ) ) : ?><br><span class="pc-nota"><?php echo e( $s['descrizione'] ); ?></span><?php endif; ?>
					</td>
					<td><code>/<?php echo e( $s['slug'] ); ?>/</code></td>
					<td>
						<?php echo $collegate; ?> su <?php echo count( $citta ); ?> città
						<?php if ( 0 === $collegate && ! empty( $citta ) ) : ?>
							<br><span class="pc-nota">nessuna pagina: creala da <a href="admin.php?p=pagine&citta=<?php echo e( $citta[0]['id'] ); ?>">Pagine</a></span>
						<?php endif; ?>
					</td>
					<td class="pc-tabella__azioni">
						<a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=servizi&id=<?php echo e( $s['id'] ); ?>">Modifica</a>
						<a class="pc-btn pc-btn--rosso pc-btn--piccolo" href="admin.php?p=servizi&azione=elimina&id=<?php echo e( $s['id'] ) . $tok; ?>"
							data-conferma="Eliminare &quot;<?php echo e( $s['nome'] ); ?>&quot;? Le pagine già create non verranno toccate.">Elimina</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div class="pc-scheda" style="margin-top:18px">
			<h2>A cosa servono</h2>
			<p class="pc-scheda__nota" style="margin:0">
				Un tipo di servizio <strong>non è una pagina</strong>: è un modello. Finché non generi la pagina,
				quel servizio non compare da nessuna parte sul sito — né nel menu, né nell'elenco dei servizi.<br><br>
				Le pagine si creano in due momenti: quando crei una città (spuntando i servizi), oppure dopo,
				da <strong>Pagine</strong> → riquadro "Servizi del catalogo senza una pagina".
				Una volta create sono indipendenti: cambi il testo di una città senza toccare le altre.
			</p>
		</div>
	<?php endif; ?>
</div>

<aside>
	<div class="pc-scheda">
		<h2><?php echo $modifica ? 'Modifica servizio' : 'Nuovo tipo di servizio'; ?></h2>
		<form method="post">
			<?php echo campo_token(); ?>
			<input type="hidden" name="id" value="<?php echo e( $modifica['id'] ?? '' ); ?>">
			<label>Nome *
				<input type="text" id="campo-servizio" name="nome" value="<?php echo e( $modifica['nome'] ?? '' ); ?>" required placeholder="Duplicazione chiavi auto">
			</label>
			<label>Slug
				<input type="text" name="slug" data-slug-da="campo-servizio" value="<?php echo e( $modifica['slug'] ?? '' ); ?>">
				<small>/citta/<strong>slug</strong>/</small>
			</label>
			<label>Descrizione breve
				<input type="text" name="descrizione" value="<?php echo e( $modifica['descrizione'] ?? '' ); ?>">
			</label>
			<label>Ordine <input type="number" name="ordine" value="<?php echo (int) ( $modifica['ordine'] ?? 10 ); ?>"></label>

			<?php /* Le icone sono disegnate dentro il portale: si scelgono
				vedendole, non da un elenco di nomi. Senza JavaScript i
				pallini restano dei radio button normali. */ ?>
			<p class="pc-etichetta">Icona nelle card</p>
			<div class="pc-icone">
				<label class="pc-icona" title="Nessuna icona">
					<input type="radio" name="icona" value=""<?php echo vuoto( $modifica['icona'] ?? '' ) ? ' checked' : ''; ?>>
					<span class="pc-icona__segno pc-icona__segno--vuoto" aria-hidden="true">—</span>
					<span class="pc-icona__nome">Nessuna</span>
				</label>
				<?php foreach ( icone_disponibili() as $chiave => $voce ) : ?>
					<label class="pc-icona" title="<?php echo e( $voce[0] ); ?>">
						<input type="radio" name="icona" value="<?php echo e( $chiave ); ?>"<?php echo ( $chiave === ( $modifica['icona'] ?? '' ) ) ? ' checked' : ''; ?>>
						<span class="pc-icona__segno"><?php echo icona_servizio( $chiave, 26 ); ?></span>
						<span class="pc-icona__nome"><?php echo e( $voce[0] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<button class="pc-btn" type="submit"><?php echo $modifica ? 'Salva' : 'Aggiungi'; ?></button>
			<?php if ( $modifica ) : ?>
				<a class="pc-btn pc-btn--ghost" href="admin.php?p=servizi">Annulla</a>
			<?php endif; ?>
		</form>
	</div>
</aside>
</div>
