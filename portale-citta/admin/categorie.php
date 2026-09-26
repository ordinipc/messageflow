<?php
/** Categorie degli articoli: valgono per tutto il portale. */
defined( 'PC_AVVIO' ) || exit;

$in_modifica = null;

/* --- Salvataggio --------------------------------------------------------- */
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['salva'] ) ) {
	verifica_token();

	$id  = (string) ( $_POST['id'] ?? '' );
	$cat = '' !== $id ? categoria_per_id( $id ) : null;
	if ( ! $cat ) {
		$cat       = categoria_predefinita();
		$cat['id'] = nuovo_id();
	}

	$cat['nome']        = trim( (string) ( $_POST['nome'] ?? '' ) );
	$cat['slug']        = categoria_slug_libero( (string) ( $_POST['slug'] ?? $cat['nome'] ), $cat['id'] );
	$cat['descrizione'] = corpo_pulisci( (string) ( $_POST['descrizione'] ?? '' ) );
	$cat['seo_titolo']  = trim( (string) ( $_POST['seo_titolo'] ?? '' ) );
	$cat['seo_desc']    = trim( (string) ( $_POST['seo_desc'] ?? '' ) );
	$cat['immagine']    = trim( (string) ( $_POST['immagine'] ?? '' ) );
	$cat['ordine']      = (int) ( $_POST['ordine'] ?? 10 );

	if ( vuoto( $cat['nome'] ) ) {
		avviso( 'Il nome della categoria è obbligatorio.', 'errore' );
		$in_modifica = $cat;
	} else {
		categoria_salva( $cat );
		avviso( 'Categoria salvata: ' . $cat['nome'] . '.' );
		vai_a( 'admin.php?p=categorie' );
	}
}

/* --- Eliminazione -------------------------------------------------------- */
if ( isset( $_GET['elimina'] ) ) {
	verifica_token();
	$cat = categoria_per_id( (string) $_GET['elimina'] );
	if ( $cat ) {
		$quanti = (int) ( categorie_conteggio()[ $cat['id'] ] ?? 0 );
		categoria_elimina( $cat['id'] );
		avviso( 'Categoria "' . $cat['nome'] . '" eliminata.'
			. ( $quanti > 0 ? ' I suoi ' . $quanti . ' articoli restano, senza categoria.' : '' ) );
	}
	vai_a( 'admin.php?p=categorie' );
}

/* --- Assegnazione in blocco ---------------------------------------------- */
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['assegna'] ) ) {
	verifica_token();
	$cat = categoria_per_id( (string) ( $_POST['categoria'] ?? '' ) );
	$che = trim( (string) ( $_POST['parole'] ?? '' ) );
	if ( ! $cat || '' === $che ) {
		avviso( 'Serve una categoria e almeno una parola da cercare nei titoli.', 'errore' );
	} else {
		$fatti = 0;
		foreach ( righe( $che ) as $parola ) {
			$parola = trim( $parola );
			if ( '' === $parola ) {
				continue;
			}
			// LOWER() c'è sia su MySQL sia su SQLite, e il confronto va fatto
			// in minuscolo: chi scrive "chiave" non deve perdere "Chiave".
			$stm    = db_esegui(
				'UPDATE ' . db_tab( 'articoli' ) . " SET categoria = ? WHERE (categoria = '' OR categoria IS NULL) AND LOWER(titolo) LIKE ?",
				array( $cat['id'], '%' . mb_strtolower( $parola ) . '%' )
			);
			$fatti += $stm->rowCount();
		}
		avviso( $fatti > 0
			? $fatti . ' articoli senza categoria sono passati a "' . $cat['nome'] . '".'
			: 'Nessun articolo senza categoria contiene quelle parole nel titolo.',
			$fatti > 0 ? 'ok' : 'errore' );
	}
	vai_a( 'admin.php?p=categorie' );
}

/* --- Modifica ------------------------------------------------------------ */
if ( isset( $_GET['modifica'] ) && null === $in_modifica ) {
	$in_modifica = categoria_per_id( (string) $_GET['modifica'] );
}

$categorie = categorie_tutte();
$conteggio = categorie_conteggio();
$immagini  = media_tutti();
$senza     = (int) db_valore( 'SELECT COUNT(*) FROM ' . db_tab( 'articoli' ) . " WHERE categoria = '' OR categoria IS NULL", array(), 0 );
$tok       = '&token=' . rawurlencode( token() );
$nuova     = null === $in_modifica ? categoria_predefinita() : $in_modifica;
?>

<div class="pc-titolo">
	<div>
		<h1>Categorie degli articoli</h1>
		<p>Valgono per tutto il portale: ogni città mostra la sua pagina di categoria con i suoi articoli.</p>
	</div>
	<div class="pc-titolo__azioni">
		<a class="pc-btn pc-btn--ghost" href="admin.php?p=articoli">← Articoli</a>
	</div>
</div>

<?php if ( $senza > 0 ) : ?>
	<div class="pc-avviso">
		<strong><?php echo (int) $senza; ?> articoli non hanno ancora una categoria.</strong>
		Restano visibili e nella sitemap: la categoria è un'aggiunta, non un obbligo.
		Con il riquadro "Assegna in blocco" qui sotto li smisti in un colpo.
	</div>
<?php endif; ?>

<div class="pc-griglia-2">
<div>
	<?php if ( empty( $categorie ) ) : ?>
		<div class="pc-scheda pc-vuoto">
			<h3>Nessuna categoria</h3>
			<p>Creane una col riquadro accanto. Una categoria raggruppa gli articoli
				e diventa una pagina in più per ogni città: <code>/trapani/blog/categoria/chiavi-auto/</code>.</p>
		</div>
	<?php else : ?>
		<div class="pc-scheda">
			<h2><?php echo count( $categorie ); ?> categorie</h2>
			<table class="pc-tabella">
				<thead><tr><th>Nome</th><th>Indirizzo</th><th>Articoli</th><th>Ordine</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $categorie as $c ) : ?>
					<tr>
						<td>
							<strong><?php echo e( $c['nome'] ); ?></strong>
							<?php if ( ! vuoto( $c['descrizione'] ) ) : ?>
								<br><span class="pc-nota"><?php echo e( mb_substr( strip_tags( $c['descrizione'] ), 0, 80 ) ); ?></span>
							<?php endif; ?>
						</td>
						<td class="pc-nota">categoria/<?php echo e( $c['slug'] ); ?>/</td>
						<td><?php echo (int) ( $conteggio[ $c['id'] ] ?? 0 ); ?></td>
						<td><?php echo (int) $c['ordine']; ?></td>
						<td class="pc-tabella__azioni">
							<a class="pc-btn pc-btn--ghost pc-btn--mini" href="admin.php?p=categorie&modifica=<?php echo e( $c['id'] ); ?>">Modifica</a>
							<a class="pc-btn pc-btn--ghost pc-btn--mini" href="admin.php?p=categorie&elimina=<?php echo e( $c['id'] ); ?><?php echo $tok; ?>"
								data-conferma="Eliminare la categoria &quot;<?php echo e( $c['nome'] ); ?>&quot;? Gli articoli restano, senza categoria.">Elimina</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="pc-scheda">
			<h2>Assegna in blocco</h2>
			<p class="pc-scheda__nota">
				Cinquanta articoli non si categorizzano uno per uno. Scrivi una parola per riga:
				ogni articolo <em>senza categoria</em> che ha quella parola nel titolo passa alla
				categoria scelta. Gli articoli che ne hanno già una non si toccano.
			</p>
			<form method="post">
				<?php echo campo_token(); ?>
				<div class="pc-riga pc-riga--2">
					<label>Categoria
						<select name="categoria">
							<?php foreach ( $categorie as $c ) : ?>
								<option value="<?php echo e( $c['id'] ); ?>"><?php echo e( $c['nome'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>Parole nei titoli (una per riga)
						<textarea name="parole" style="min-height:90px" placeholder="chiave auto&#10;transponder&#10;telecomando"></textarea>
					</label>
				</div>
				<button class="pc-btn" type="submit" name="assegna" value="1">Assegna</button>
			</form>
		</div>
	<?php endif; ?>
</div>

<aside>
	<div class="pc-scheda">
		<h2><?php echo vuoto( $nuova['id'] ) ? 'Nuova categoria' : 'Modifica categoria'; ?></h2>
		<form method="post">
			<?php echo campo_token(); ?>
			<input type="hidden" name="id" value="<?php echo e( $nuova['id'] ); ?>">
			<label>Nome *
				<input type="text" id="campo-nome-categoria" name="nome" value="<?php echo e( $nuova['nome'] ); ?>" required>
			</label>
			<label>Indirizzo nell'URL
				<input type="text" name="slug" data-slug-da="campo-nome-categoria" value="<?php echo e( $nuova['slug'] ); ?>">
				<small>Diventa <code>/&lt;città&gt;/&lt;blog&gt;/categoria/…/</code>. Una volta su Google non si cambia più.</small>
			</label>
			<label>Descrizione
				<textarea id="campo-desc-categoria" name="descrizione" data-editor style="min-height:130px"><?php echo e( $nuova['descrizione'] ); ?></textarea>
				<?php if ( ai_attiva() ) : ?>
					<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo"
						data-ai="cat_testo" data-ai-campo="campo-desc-categoria"
						data-ai-categoria="<?php echo e( $nuova['id'] ); ?>">✦ Scrivi con l'assistente</button>
				<?php endif; ?>
				<small>Si legge in cima all'archivio. Due righe di testo vero valgono più di un elenco di link nudo, per Google e per chi legge.</small>
			</label>
			<label>Ordine
				<input type="number" name="ordine" value="<?php echo (int) $nuova['ordine']; ?>" min="0" max="999">
			</label>
			<h3 style="font-size:14px;margin:18px 0 8px">Come appare su Google</h3>
			<label>Titolo
				<input type="text" name="seo_titolo" value="<?php echo e( $nuova['seo_titolo'] ); ?>"
					data-conta data-conta-min="30" data-conta-max="65"
					placeholder="<?php echo e( vuoto( $nuova['nome'] ) ? 'Chiavi auto a Trapani' : $nuova['nome'] ); ?>">
				<small>Il nome della città viene aggiunto da sé, se non c'è già.</small>
			</label>
			<label>Descrizione
				<textarea name="seo_desc" style="min-height:70px" data-conta data-conta-min="70" data-conta-max="160"><?php echo e( $nuova['seo_desc'] ); ?></textarea>
			</label>
			<label>Immagine
				<select name="immagine">
					<option value="">— nessuna —</option>
					<?php foreach ( $immagini as $m ) : ?>
						<option value="<?php echo e( $m['file'] ); ?>" <?php selected_pc( $m['file'], $nuova['immagine'] ); ?>><?php echo e( $m['file'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<div class="pc-titolo__azioni" style="margin-top:14px">
				<?php if ( ! vuoto( $nuova['id'] ) ) : ?>
					<a class="pc-btn pc-btn--ghost" href="admin.php?p=categorie">Annulla</a>
				<?php endif; ?>
				<button class="pc-btn" type="submit" name="salva" value="1"><?php echo vuoto( $nuova['id'] ) ? 'Crea' : 'Salva'; ?></button>
			</div>
		</form>
	</div>
</aside>
</div>
