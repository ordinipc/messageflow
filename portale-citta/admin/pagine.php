<?php
/** Elenco delle pagine di una città. */
defined( 'PC_AVVIO' ) || exit;

$citta_id = (string) ( $_GET['citta'] ?? '' );
$citta    = '' !== $citta_id ? citta_per_id( $citta_id ) : null;

if ( ! $citta ) {
	$tutte = citta_tutte();
	if ( empty( $tutte ) ) {
		echo '<div class="pc-scheda pc-vuoto"><h3>Nessuna città</h3><p>Le pagine appartengono a una città: creane una prima.</p><a class="pc-btn" href="admin.php?p=citta-modifica">Crea la prima città</a></div>';
		return;
	}
	$citta    = $tutte[0];
	$citta_id = $citta['id'];
}

/* Azioni rapide. */
if ( isset( $_GET['azione'] ) ) {
	verifica_token();
	$id = (string) ( $_GET['id'] ?? '' );
	$p  = pagina_per_id( $id );
	if ( $p && $p['citta_id'] === $citta_id ) {
		if ( 'elimina' === $_GET['azione'] ) {
			pagina_elimina( $id );
			avviso( 'Pagina eliminata.' );
		} elseif ( 'pubblica' === $_GET['azione'] ) {
			$p['stato'] = 'pubblicata';
			pagina_salva( $p );
			avviso( 'Pagina pubblicata.' );
		} elseif ( 'ritira' === $_GET['azione'] ) {
			$p['stato'] = 'bozza';
			pagina_salva( $p );
			avviso( 'Pagina riportata in bozza.' );
		} elseif ( 'duplica' === $_GET['azione'] ) {
			$n            = $p;
			$n['id']      = nuovo_id();
			$n['titolo']  = $p['titolo'] . ' (copia)';
			$n['slug']    = pagina_slug_libero( $p['slug'], $citta_id );
			$n['tipo']    = 'home' === $p['tipo'] ? 'fissa' : $p['tipo'];
			$n['stato']   = 'bozza';
			pagina_salva( $n );
			avviso( 'Pagina duplicata in bozza.' );
		}
	}
	vai_a( 'admin.php?p=pagine&citta=' . rawurlencode( $citta_id ) );
}

/* Pubblicazione in blocco. */
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['massa'] ) ) {
	verifica_token();
	$scelte = (array) ( $_POST['scelte'] ?? array() );
	$fatte  = 0;
	foreach ( $scelte as $pid ) {
		$p = pagina_per_id( (string) $pid );
		if ( ! $p || $p['citta_id'] !== $citta_id ) {
			continue;
		}
		if ( 'pubblica' === $_POST['massa'] ) {
			$p['stato'] = 'pubblicata';
		} elseif ( 'ritira' === $_POST['massa'] ) {
			$p['stato'] = 'bozza';
		} elseif ( 'elimina' === $_POST['massa'] ) {
			pagina_elimina( $p['id'] );
			$fatte++;
			continue;
		}
		pagina_salva( $p );
		$fatte++;
	}
	avviso( $fatte . ' pagine aggiornate.' );
	vai_a( 'admin.php?p=pagine&citta=' . rawurlencode( $citta_id ) );
}

$pagine = pagine_di_citta( $citta_id );
$tutte  = citta_tutte();
$tok    = '&token=' . rawurlencode( token() );
?>

<div class="pc-titolo">
	<div>
		<h1>Pagine di <?php echo e( $citta['nome'] ); ?></h1>
		<p><a href="<?php echo e( url_citta( $citta ) ); ?>" target="_blank" rel="noopener"><?php echo e( url_citta( $citta ) ); ?> ↗</a></p>
	</div>
	<div class="pc-titolo__azioni">
		<form method="get" style="display:flex;gap:6px;align-items:center">
			<input type="hidden" name="p" value="pagine">
			<select name="citta" onchange="this.form.submit()" style="margin:0;width:auto">
				<?php foreach ( $tutte as $c ) : ?>
					<option value="<?php echo e( $c['id'] ); ?>" <?php selected_pc( $c['id'], $citta_id ); ?>><?php echo e( $c['nome'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</form>
		<a class="pc-btn" href="admin.php?p=pagina-modifica&citta=<?php echo e( $citta_id ); ?>">+ Nuova pagina</a>
	</div>
</div>

<?php if ( empty( $pagine ) ) : ?>
	<div class="pc-scheda pc-vuoto">
		<h3>Nessuna pagina</h3>
		<p>Finché <?php echo e( $citta['nome'] ); ?> non ha pagine, <code>/<?php echo e( $citta['slug'] ); ?>/</code> restituisce 404.</p>
		<a class="pc-btn" href="admin.php?p=pagina-modifica&citta=<?php echo e( $citta_id ); ?>">Crea la prima pagina</a>
	</div>
<?php else : ?>
<form method="post">
	<?php echo campo_token(); ?>
	<table class="pc-tabella">
		<thead>
			<tr>
				<th style="width:28px"><input type="checkbox" onclick="document.querySelectorAll('[name=\'scelte[]\']').forEach(c=>c.checked=this.checked)"></th>
				<th>Titolo</th><th>Indirizzo</th><th>Tipo</th><th>Menu</th><th>SEO</th><th>Stato</th><th></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $pagine as $p ) : ?>
			<?php
			$analisi = seo_analisi( $citta, $p );
			$punti   = $analisi['punteggio'];
			?>
			<tr>
				<td><input type="checkbox" name="scelte[]" value="<?php echo e( $p['id'] ); ?>"></td>
				<td>
					<strong><?php echo e( $p['titolo'] ); ?></strong>
					<?php if ( ! vuoto( $p['aggiornata'] ) ) : ?><br><span class="pc-nota">agg. <?php echo e( $p['aggiornata'] ); ?></span><?php endif; ?>
				</td>
				<td><a href="<?php echo e( url_pagina( $citta, $p ) ); ?>" target="_blank" rel="noopener"><code><?php echo e( str_replace( base_url(), '', url_pagina( $citta, $p ) ) ); ?></code></a></td>
				<td class="pc-nota"><?php echo e( 'home' === $p['tipo'] ? 'principale' : $p['tipo'] ); ?></td>
				<td><?php echo (int) $p['menu_mostra'] ? '✓ ' . (int) $p['menu_ordine'] : '—'; ?></td>
				<td><span class="pc-stato pc-stato--<?php echo $punti >= 80 ? 'pubblicata' : 'bozza'; ?>"><?php echo (int) $punti; ?></span></td>
				<td><span class="pc-stato pc-stato--<?php echo e( $p['stato'] ); ?>"><?php echo e( $p['stato'] ); ?></span></td>
				<td class="pc-tabella__azioni">
					<a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=pagina-modifica&id=<?php echo e( $p['id'] ); ?>">Modifica</a>
					<?php if ( 'pubblicata' === $p['stato'] ) : ?>
						<a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=pagine&citta=<?php echo e( $citta_id ); ?>&azione=ritira&id=<?php echo e( $p['id'] ) . $tok; ?>">Ritira</a>
					<?php else : ?>
						<a class="pc-btn pc-btn--piccolo" href="admin.php?p=pagine&citta=<?php echo e( $citta_id ); ?>&azione=pubblica&id=<?php echo e( $p['id'] ) . $tok; ?>">Pubblica</a>
					<?php endif; ?>
					<a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=pagine&citta=<?php echo e( $citta_id ); ?>&azione=duplica&id=<?php echo e( $p['id'] ) . $tok; ?>">Duplica</a>
					<a class="pc-btn pc-btn--rosso pc-btn--piccolo" href="admin.php?p=pagine&citta=<?php echo e( $citta_id ); ?>&azione=elimina&id=<?php echo e( $p['id'] ) . $tok; ?>"
						data-conferma="Eliminare la pagina &quot;<?php echo e( $p['titolo'] ); ?>&quot;?">Elimina</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<div class="pc-salva">
		<p class="pc-salva__nota">Con le pagine selezionate:</p>
		<div class="pc-titolo__azioni">
			<button class="pc-btn pc-btn--ghost pc-btn--piccolo" type="submit" name="massa" value="pubblica">Pubblica</button>
			<button class="pc-btn pc-btn--ghost pc-btn--piccolo" type="submit" name="massa" value="ritira">Riporta in bozza</button>
			<button class="pc-btn pc-btn--rosso pc-btn--piccolo" type="submit" name="massa" value="elimina" data-conferma="Eliminare le pagine selezionate?">Elimina</button>
		</div>
	</div>
</form>
<?php endif; ?>
