<?php
/** Elenco delle città. */
defined( 'PC_AVVIO' ) || exit;

/* Azioni rapide. */
if ( isset( $_GET['azione'] ) ) {
	verifica_token();
	$id = (string) ( $_GET['id'] ?? '' );
	$c  = citta_per_id( $id );
	if ( $c ) {
		if ( 'elimina' === $_GET['azione'] ) {
			citta_elimina( $id );
			avviso( 'Città "' . $c['nome'] . '" eliminata con tutte le sue pagine.' );
		} elseif ( 'pubblica' === $_GET['azione'] ) {
			$c['stato'] = 'pubblicata';
			citta_salva( $c );
			avviso( $c['nome'] . ' è ora pubblicata.' );
		} elseif ( 'ritira' === $_GET['azione'] ) {
			$c['stato'] = 'bozza';
			citta_salva( $c );
			avviso( $c['nome'] . ' è tornata in bozza.' );
		} elseif ( 'duplica' === $_GET['azione'] ) {
			$nuova          = $c;
			$nuova['id']    = nuovo_id();
			$nuova['nome']  = $c['nome'] . ' (copia)';
			$nuova['slug']  = citta_slug_libero( $c['slug'] );
			$nuova['stato'] = 'bozza';
			citta_salva( $nuova );
			foreach ( pagine_di_citta( $id ) as $p ) {
				$p['id']       = nuovo_id();
				$p['citta_id'] = $nuova['id'];
				$p['stato']    = 'bozza';
				pagina_salva( $p );
			}
			avviso( 'Città duplicata: ricordati di riscrivere i testi, Google penalizza le copie identiche.', 'errore' );
			vai_a( 'admin.php?p=citta-modifica&id=' . $nuova['id'] );
		}
	}
	vai_a( 'admin.php?p=citta' );
}

$lista = citta_tutte();
$tok   = '&token=' . rawurlencode( token() );
?>

<div class="pc-titolo">
	<div>
		<h1>Città</h1>
		<p>Ogni città è una cartella dell'indirizzo: <code>/nome-citta/</code>.</p>
	</div>
	<div class="pc-titolo__azioni">
		<a class="pc-btn" href="admin.php?p=citta-modifica">+ Nuova città</a>
	</div>
</div>

<?php if ( empty( $lista ) ) : ?>
	<div class="pc-scheda pc-vuoto">
		<h3>Nessuna città</h3>
		<p>Crea la prima città: sarà raggiungibile all'indirizzo <code><?php echo e( base_url() ); ?>/nome-citta/</code>.</p>
		<a class="pc-btn" href="admin.php?p=citta-modifica">Crea la prima città</a>
	</div>
<?php else : ?>
	<table class="pc-tabella">
		<thead>
			<tr><th>Città</th><th>Indirizzo</th><th>Pagine</th><th>Aggiornata</th><th>Stato</th><th></th></tr>
		</thead>
		<tbody>
		<?php foreach ( $lista as $c ) : ?>
			<?php $n = count( pagine_di_citta( $c['id'] ) ); ?>
			<tr>
				<td>
					<strong><?php echo e( $c['nome'] ); ?></strong>
					<?php if ( ! vuoto( $c['provincia'] ) ) : ?>
						<span class="pc-nota">(<?php echo e( $c['provincia'] ); ?>)</span>
					<?php endif; ?>
				</td>
				<td><a href="<?php echo e( url_citta( $c ) ); ?>" target="_blank" rel="noopener"><code>/<?php echo e( $c['slug'] ); ?>/</code></a></td>
				<td><?php echo (int) $n; ?></td>
				<td class="pc-nota"><?php echo e( $c['aggiornata'] ); ?></td>
				<td><span class="pc-stato pc-stato--<?php echo e( $c['stato'] ); ?>"><?php echo e( $c['stato'] ); ?></span></td>
				<td class="pc-tabella__azioni">
					<a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=pagine&citta=<?php echo e( $c['id'] ); ?>">Pagine (<?php echo (int) $n; ?>)</a>
					<a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=citta-modifica&id=<?php echo e( $c['id'] ); ?>">Modifica</a>
					<?php if ( 'pubblicata' === $c['stato'] ) : ?>
						<a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=citta&azione=ritira&id=<?php echo e( $c['id'] ) . $tok; ?>">Ritira</a>
					<?php else : ?>
						<a class="pc-btn pc-btn--piccolo" href="admin.php?p=citta&azione=pubblica&id=<?php echo e( $c['id'] ) . $tok; ?>">Pubblica</a>
					<?php endif; ?>
					<a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=citta&azione=duplica&id=<?php echo e( $c['id'] ) . $tok; ?>"
						data-conferma="Duplicare <?php echo e( $c['nome'] ); ?> con tutte le sue pagine?">Duplica</a>
					<a class="pc-btn pc-btn--rosso pc-btn--piccolo" href="admin.php?p=citta&azione=elimina&id=<?php echo e( $c['id'] ) . $tok; ?>"
						data-conferma="Eliminare <?php echo e( $c['nome'] ); ?> e tutte le sue pagine? L'operazione non è reversibile.">Elimina</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
