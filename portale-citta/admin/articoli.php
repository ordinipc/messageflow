<?php
/** Articoli del blog di una città. */
defined( 'PC_AVVIO' ) || exit;

$citta_id = (string) ( $_GET['citta'] ?? '' );
$citta    = '' !== $citta_id ? citta_per_id( $citta_id ) : null;

if ( ! $citta ) {
	$tutte = citta_tutte();
	if ( empty( $tutte ) ) {
		echo '<div class="pc-scheda pc-vuoto"><h3>Nessuna città</h3><p>Gli articoli appartengono a una città.</p><a class="pc-btn" href="admin.php?p=citta-modifica">Crea la prima città</a></div>';
		return;
	}
	$citta    = $tutte[0];
	$citta_id = $citta['id'];
}

/* Azioni singole. */
if ( isset( $_GET['azione'] ) ) {
	verifica_token();
	$a = articolo_per_id( (string) ( $_GET['id'] ?? '' ) );
	if ( $a && $a['citta_id'] === $citta_id ) {
		if ( 'elimina' === $_GET['azione'] ) {
			articolo_elimina( $a['id'] );
			avviso( 'Articolo eliminato.' );
		} elseif ( 'pubblica' === $_GET['azione'] ) {
			$a['stato'] = 'pubblicato';
			articolo_salva( $a );
			avviso( 'Articolo pubblicato.' );
		} elseif ( 'ritira' === $_GET['azione'] ) {
			$a['stato'] = 'bozza';
			articolo_salva( $a );
			avviso( 'Articolo riportato in bozza.' );
		}
	}
	vai_a( 'admin.php?p=articoli&citta=' . rawurlencode( $citta_id ) . '&pag=' . (int) ( $_GET['pag'] ?? 1 ) );
}

/* Azioni in blocco. */
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['massa'] ) ) {
	verifica_token();
	$fatti = 0;
	foreach ( (array) ( $_POST['scelte'] ?? array() ) as $aid ) {
		$a = articolo_per_id( (string) $aid );
		if ( ! $a || $a['citta_id'] !== $citta_id ) {
			continue;
		}
		if ( 'elimina' === $_POST['massa'] ) {
			articolo_elimina( $a['id'] );
			$fatti++;
			continue;
		}
		$a['stato'] = 'pubblica' === $_POST['massa'] ? 'pubblicato' : 'bozza';
		articolo_salva( $a );
		$fatti++;
	}
	avviso( $fatti . ' articoli aggiornati.' );
	vai_a( 'admin.php?p=articoli&citta=' . rawurlencode( $citta_id ) );
}

/* Pubblicazione di tutti gli articoli della città. */
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['pubblica_tutti'] ) ) {
	verifica_token();
	db_esegui(
		'UPDATE ' . db_tab( 'articoli' ) . " SET stato = 'pubblicato' WHERE citta_id = ? AND stato <> 'pubblicato'",
		array( $citta_id )
	);
	avviso( 'Tutti gli articoli di ' . $citta['nome'] . ' sono stati pubblicati.' );
	vai_a( 'admin.php?p=articoli&citta=' . rawurlencode( $citta_id ) );
}

// Filtro per categoria: con cinquanta articoli trovarne uno a occhio non si
// può, e la categoria è il taglio più naturale.
$filtro_cat = (string) ( $_GET['cat'] ?? '' );
$categorie  = categorie_tutte();
$nomi_cat   = array();
foreach ( $categorie as $c ) {
	$nomi_cat[ $c['id'] ] = $c['nome'];
}
if ( '' !== $filtro_cat && ! isset( $nomi_cat[ $filtro_cat ] ) ) {
	$filtro_cat = '';
}

$per_pagina = 40;
$totale     = '' === $filtro_cat ? articoli_conta( $citta_id ) : articoli_conta_categoria( $citta_id, $filtro_cat );
$pagine_tot = max( 1, (int) ceil( $totale / $per_pagina ) );
$corrente   = max( 1, min( $pagine_tot, (int) ( $_GET['pag'] ?? 1 ) ) );
$articoli   = '' === $filtro_cat
	? articoli_di_citta( $citta_id, false, $per_pagina, ( $corrente - 1 ) * $per_pagina )
	: articoli_di_categoria( $citta_id, $filtro_cat, false, $per_pagina, ( $corrente - 1 ) * $per_pagina );
$bozze      = articoli_conta( $citta_id ) - articoli_conta( $citta_id, true );
$pagina_b   = pagina_blog( $citta_id );
$tutte      = citta_tutte();
$tok        = '&token=' . rawurlencode( token() );
?>

<div class="pc-titolo">
	<div>
		<h1>Articoli di <?php echo e( $citta['nome'] ); ?></h1>
		<p><?php echo (int) $totale; ?> in tutto<?php echo $bozze > 0 ? ', di cui ' . (int) $bozze . ' in bozza' : ''; ?></p>
	</div>
	<div class="pc-titolo__azioni">
		<form method="get" style="display:flex;gap:6px;align-items:center">
			<input type="hidden" name="p" value="articoli">
			<select name="citta" onchange="this.form.submit()" style="margin:0;width:auto">
				<?php foreach ( $tutte as $c ) : ?>
					<option value="<?php echo e( $c['id'] ); ?>" <?php selected_pc( $c['id'], $citta_id ); ?>>
						<?php echo e( $c['nome'] ); ?> (<?php echo articoli_conta( $c['id'] ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</form>
		<?php if ( ! empty( $categorie ) ) : ?>
			<form method="get" style="display:flex;gap:6px;align-items:center">
				<input type="hidden" name="p" value="articoli">
				<input type="hidden" name="citta" value="<?php echo e( $citta_id ); ?>">
				<select name="cat" onchange="this.form.submit()" style="margin:0;width:auto">
					<option value="">tutte le categorie</option>
					<?php foreach ( $categorie as $c ) : ?>
						<option value="<?php echo e( $c['id'] ); ?>" <?php selected_pc( $c['id'], $filtro_cat ); ?>><?php echo e( $c['nome'] ); ?> (<?php echo articoli_conta_categoria( $citta_id, $c['id'] ); ?>)</option>
					<?php endforeach; ?>
				</select>
			</form>
		<?php endif; ?>
		<a class="pc-btn pc-btn--ghost" href="admin.php?p=categorie">Categorie</a>
		<a class="pc-btn pc-btn--ghost" href="admin.php?p=importa">Importa da WordPress</a>
		<a class="pc-btn" href="admin.php?p=articolo-modifica&citta=<?php echo e( $citta_id ); ?>">+ Nuovo articolo</a>
	</div>
</div>

<?php if ( ! $pagina_b ) : ?>
	<div class="pc-avviso pc-avviso--errore">
		<strong><?php echo e( $citta['nome'] ); ?> non ha una pagina di tipo "Blog".</strong><br>
		Senza quella pagina gli articoli non hanno un indirizzo pubblico e non finiscono nella sitemap:
		puoi scriverli lo stesso, ma nessuno li vede.
		<a href="admin.php?p=pagina-modifica&citta=<?php echo e( $citta_id ); ?>">Creane una</a> scegliendo il tipo <strong>Blog</strong>.
	</div>
<?php endif; ?>

<?php if ( 0 === $totale ) : ?>
	<div class="pc-scheda pc-vuoto">
		<h3>Nessun articolo per <?php echo e( $citta['nome'] ); ?></h3>
		<p>Scrivine uno, oppure importali da un'esportazione WordPress: vengono smistati da soli fra le città.</p>
		<p>
			<a class="pc-btn" href="admin.php?p=articolo-modifica&citta=<?php echo e( $citta_id ); ?>">+ Nuovo articolo</a>
			<a class="pc-btn pc-btn--ghost" href="admin.php?p=importa">Importa da WordPress</a>
		</p>
	</div>
<?php else : ?>

<?php if ( $bozze > 0 ) : ?>
	<form method="post" class="pc-scheda" style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
		<?php echo campo_token(); ?>
		<p class="pc-nota" style="margin:0">
			<strong><?php echo (int) $bozze; ?> articoli sono in bozza</strong> e non sono visibili al pubblico né a Google.
		</p>
		<button class="pc-btn" type="submit" name="pubblica_tutti" value="1"
			data-conferma="Pubblicare tutti i <?php echo (int) $bozze; ?> articoli in bozza di <?php echo e( $citta['nome'] ); ?>?">
			Pubblica tutti
		</button>
	</form>
<?php endif; ?>

<form method="post">
	<?php echo campo_token(); ?>
	<table class="pc-tabella">
		<thead>
			<tr>
				<th style="width:28px"><input type="checkbox" onclick="document.querySelectorAll('[name=\'scelte[]\']').forEach(c=>c.checked=this.checked)"></th>
				<th>Titolo</th><th>Categoria</th><th>Data</th><th>Indirizzo</th><th>Stato</th><th></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $articoli as $a ) : ?>
			<tr>
				<td><input type="checkbox" name="scelte[]" value="<?php echo e( $a['id'] ); ?>"></td>
				<td>
					<strong><?php echo e( $a['titolo'] ); ?></strong>
					<?php if ( ! vuoto( $a['origine'] ) ) : ?>
						<br><span class="pc-nota">importato da <?php echo e( $a['origine'] ); ?></span>
					<?php endif; ?>
				</td>
				<td class="pc-nota">
					<?php echo vuoto( $a['categoria'] ) || ! isset( $nomi_cat[ $a['categoria'] ] ) ? '&mdash;' : e( $nomi_cat[ $a['categoria'] ] ); ?>
				</td>
				<td class="pc-nota"><?php echo e( $a['data'] ); ?></td>
				<td>
					<?php if ( $pagina_b ) : ?>
						<a href="<?php echo e( url_articolo( $citta, $a, $pagina_b ) ); ?>" target="_blank" rel="noopener">
							<code><?php echo e( str_replace( base_url(), '', url_articolo( $citta, $a, $pagina_b ) ) ); ?></code>
						</a>
					<?php else : ?>
						<span class="pc-nota">manca la pagina blog</span>
					<?php endif; ?>
				</td>
				<td><span class="pc-stato pc-stato--<?php echo 'pubblicato' === $a['stato'] ? 'pubblicata' : 'bozza'; ?>"><?php echo e( $a['stato'] ); ?></span></td>
				<td class="pc-tabella__azioni">
					<a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=articolo-modifica&id=<?php echo e( $a['id'] ); ?>">Modifica</a>
					<?php if ( 'pubblicato' === $a['stato'] ) : ?>
						<a class="pc-btn pc-btn--ghost pc-btn--piccolo" href="admin.php?p=articoli&citta=<?php echo e( $citta_id ); ?>&pag=<?php echo (int) $corrente; ?>&azione=ritira&id=<?php echo e( $a['id'] ) . $tok; ?>">Ritira</a>
					<?php else : ?>
						<a class="pc-btn pc-btn--piccolo" href="admin.php?p=articoli&citta=<?php echo e( $citta_id ); ?>&pag=<?php echo (int) $corrente; ?>&azione=pubblica&id=<?php echo e( $a['id'] ) . $tok; ?>">Pubblica</a>
					<?php endif; ?>
					<a class="pc-btn pc-btn--rosso pc-btn--piccolo" href="admin.php?p=articoli&citta=<?php echo e( $citta_id ); ?>&pag=<?php echo (int) $corrente; ?>&azione=elimina&id=<?php echo e( $a['id'] ) . $tok; ?>"
						data-conferma="Eliminare &quot;<?php echo e( $a['titolo'] ); ?>&quot;?">Elimina</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<div class="pc-salva">
		<p class="pc-salva__nota">
			<?php if ( $pagine_tot > 1 ) : ?>
				Pagina <?php echo (int) $corrente; ?> di <?php echo (int) $pagine_tot; ?> ·
				<?php if ( $corrente > 1 ) : ?>
					<a href="admin.php?p=articoli&citta=<?php echo e( $citta_id ); ?>&pag=<?php echo (int) $corrente - 1; ?>">← precedente</a>
				<?php endif; ?>
				<?php if ( $corrente < $pagine_tot ) : ?>
					<a href="admin.php?p=articoli&citta=<?php echo e( $citta_id ); ?>&pag=<?php echo (int) $corrente + 1; ?>">successiva →</a>
				<?php endif; ?>
			<?php else : ?>
				Con gli articoli selezionati:
			<?php endif; ?>
		</p>
		<div class="pc-titolo__azioni">
			<button class="pc-btn pc-btn--ghost pc-btn--piccolo" type="submit" name="massa" value="pubblica">Pubblica</button>
			<button class="pc-btn pc-btn--ghost pc-btn--piccolo" type="submit" name="massa" value="ritira">Riporta in bozza</button>
			<button class="pc-btn pc-btn--rosso pc-btn--piccolo" type="submit" name="massa" value="elimina" data-conferma="Eliminare gli articoli selezionati?">Elimina</button>
		</div>
	</div>
</form>
<?php endif; ?>
