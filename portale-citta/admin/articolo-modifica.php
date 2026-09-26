<?php
/** Modifica di un articolo. */
defined( 'PC_AVVIO' ) || exit;

$id       = (string) ( $_GET['id'] ?? '' );
$articolo = '' !== $id ? articolo_per_id( $id ) : null;
$nuovo    = null === $articolo;

if ( $nuovo ) {
	$citta_id = (string) ( $_GET['citta'] ?? '' );
	$citta    = citta_per_id( $citta_id );
	if ( ! $citta ) {
		echo '<div class="pc-scheda pc-vuoto"><h3>Città mancante</h3><a class="pc-btn" href="admin.php?p=articoli">Vai agli articoli</a></div>';
		return;
	}
	$articolo             = articolo_predefinito();
	$articolo['id']       = nuovo_id();
	$articolo['citta_id'] = $citta['id'];
	$articolo['data']     = oggi();
} else {
	$citta = citta_per_id( $articolo['citta_id'] );
	if ( ! $citta ) {
		echo '<div class="pc-scheda pc-vuoto"><h3>Città mancante</h3><p>Questo articolo punta a una città che non esiste più.</p></div>';
		return;
	}
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	verifica_token();

	$articolo['titolo']     = trim( (string) ( $_POST['titolo'] ?? '' ) );
	$articolo['slug']       = articolo_slug_libero( (string) ( $_POST['slug'] ?? $articolo['titolo'] ), $citta['id'], $articolo['id'] );
	$articolo['seo_titolo'] = trim( (string) ( $_POST['seo_titolo'] ?? '' ) );
	$articolo['seo_desc']   = trim( (string) ( $_POST['seo_desc'] ?? '' ) );
	$articolo['estratto']   = trim( (string) ( $_POST['estratto'] ?? '' ) );
	$articolo['immagine']   = trim( (string) ( $_POST['immagine'] ?? '' ) );
	$articolo['data']       = trim( (string) ( $_POST['data'] ?? '' ) );
	// Il corpo viene ripulito con le stesse regole dell'importazione.
	$articolo['corpo']      = import_pulisci_corpo( (string) ( $_POST['corpo'] ?? '' ) );
	$articolo['stato']      = 'pubblicato' === ( $_POST['stato'] ?? '' ) ? 'pubblicato' : 'bozza';

	$nuova_citta = citta_per_id( (string) ( $_POST['citta_id'] ?? '' ) );
	if ( $nuova_citta && $nuova_citta['id'] !== $articolo['citta_id'] ) {
		$articolo['citta_id'] = $nuova_citta['id'];
		$articolo['slug']     = articolo_slug_libero( $articolo['slug'], $nuova_citta['id'], $articolo['id'] );
		$citta                = $nuova_citta;
	}

	if ( vuoto( $articolo['titolo'] ) ) {
		avviso( 'Il titolo è obbligatorio.', 'errore' );
	} else {
		articolo_salva( $articolo );
		avviso( 'Articolo salvato.' );
		vai_a( 'admin.php?p=articolo-modifica&id=' . $articolo['id'] );
	}
}

$pagina_b = pagina_blog( $citta['id'] );
$immagini = media_tutti();
$parole   = str_word_count( strip_tags( (string) $articolo['corpo'] ), 0, 'àáâäèéêëìíîïòóôöùúûüçñÀÈÉÌÒÙ0123456789' );
?>

<div class="pc-titolo">
	<div>
		<h1><?php echo $nuovo ? 'Nuovo articolo' : e( $articolo['titolo'] ); ?></h1>
		<p>
			<?php echo e( $citta['nome'] ); ?>
			<?php if ( $pagina_b ) : ?>
				· <a href="<?php echo e( url_articolo( $citta, $articolo, $pagina_b ) ); ?>" target="_blank" rel="noopener"><?php echo e( str_replace( base_url(), '', url_articolo( $citta, $articolo, $pagina_b ) ) ); ?> ↗</a>
			<?php else : ?>
				· <span class="pc-nota">manca la pagina di tipo Blog: l'articolo non ha ancora un indirizzo</span>
			<?php endif; ?>
		</p>
	</div>
	<div class="pc-titolo__azioni">
		<a class="pc-btn pc-btn--ghost" href="admin.php?p=articoli&citta=<?php echo e( $citta['id'] ); ?>">← Articoli di <?php echo e( $citta['nome'] ); ?></a>
	</div>
</div>

<form method="post">
<?php echo campo_token(); ?>
<div class="pc-griglia-2">
<div>
	<div class="pc-scheda">
		<h2>Articolo</h2>
		<div class="pc-riga pc-riga--2">
			<label>Titolo *
				<input type="text" id="campo-titolo" name="titolo" value="<?php echo e( $articolo['titolo'] ); ?>" required>
			</label>
			<label>Indirizzo nell'URL
				<input type="text" name="slug" data-slug-da="campo-titolo" value="<?php echo e( $articolo['slug'] ); ?>">
			</label>
		</div>
		<div class="pc-riga pc-riga--2">
			<label>Città
				<select name="citta_id">
					<?php foreach ( citta_tutte() as $c ) : ?>
						<option value="<?php echo e( $c['id'] ); ?>" <?php selected_pc( $c['id'], $articolo['citta_id'] ); ?>><?php echo e( $c['nome'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<small>Spostandolo cambia anche il suo indirizzo.</small>
			</label>
			<label>Data <input type="date" name="data" value="<?php echo e( substr( $articolo['data'], 0, 10 ) ); ?>"></label>
		</div>
		<label>Estratto
			<textarea name="estratto" style="min-height:70px"><?php echo e( $articolo['estratto'] ); ?></textarea>
			<small>Compare nell'elenco del blog e, se manca la descrizione SEO, anche su Google.</small>
		</label>
	</div>

	<div class="pc-scheda">
		<h2>Testo</h2>
		<p class="pc-scheda__nota">
			Grassetto, corsivo, sottotitoli, elenchi e collegamenti dalla barra qui sotto.
			Script e attributi vengono tolti al salvataggio. <?php echo (int) $parole; ?> parole.
		</p>
		<textarea id="campo-corpo-articolo" name="corpo" data-editor style="min-height:420px" aria-label="Testo dell'articolo"><?php echo e( $articolo['corpo'] ); ?></textarea>
	</div>
</div>

<aside>
	<div class="pc-scheda">
		<h2>Come appare su Google</h2>
		<label>Titolo
			<input type="text" name="seo_titolo" value="<?php echo e( $articolo['seo_titolo'] ); ?>" data-conta data-conta-min="30" data-conta-max="65"
				placeholder="<?php echo e( $articolo['titolo'] ); ?>">
		</label>
		<label>Descrizione
			<textarea name="seo_desc" style="min-height:70px" data-conta data-conta-min="70" data-conta-max="160"
				placeholder="<?php echo e( mb_substr( $articolo['estratto'], 0, 155 ) ); ?>"><?php echo e( $articolo['seo_desc'] ); ?></textarea>
		</label>
	</div>

	<div class="pc-scheda">
		<h2>Immagine</h2>
		<label>
			<select name="immagine">
				<option value="">— nessuna —</option>
				<?php foreach ( $immagini as $m ) : ?>
					<option value="<?php echo e( $m['file'] ); ?>" <?php selected_pc( $m['file'], $articolo['immagine'] ); ?>><?php echo e( $m['file'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php if ( ! vuoto( $articolo['immagine'] ) ) : ?>
			<div class="pc-anteprima"><img src="<?php echo e( url_media( $articolo['immagine'] ) ); ?>" alt=""></div>
		<?php endif; ?>
	</div>

	<?php if ( ! vuoto( $articolo['origine'] ) ) : ?>
		<div class="pc-scheda">
			<h2>Provenienza</h2>
			<p class="pc-nota" style="margin:0">
				Importato da<br>
				<a href="<?php echo e_url( $articolo['origine'] ); ?>" target="_blank" rel="noopener"><?php echo e( $articolo['origine'] ); ?></a>
			</p>
		</div>
	<?php endif; ?>
</aside>
</div>

<div class="pc-salva">
	<label class="pc-inline" style="margin:0">
		<input type="checkbox" name="stato" value="pubblicato" <?php checked_pc( 'pubblicato' === $articolo['stato'] ); ?>>
		Articolo pubblicato
		<?php if ( 'pubblicata' !== $citta['stato'] ) : ?>
			<span class="pc-nota">— la città è in bozza, quindi resta comunque invisibile</span>
		<?php endif; ?>
	</label>
	<div class="pc-titolo__azioni">
		<?php if ( $pagina_b ) : ?>
			<a class="pc-btn pc-btn--ghost" href="<?php echo e( url_articolo( $citta, $articolo, $pagina_b ) ); ?>" target="_blank" rel="noopener">Anteprima ↗</a>
		<?php endif; ?>
		<button class="pc-btn" type="submit"><?php echo $nuovo ? 'Crea' : 'Salva'; ?></button>
	</div>
</div>
</form>
