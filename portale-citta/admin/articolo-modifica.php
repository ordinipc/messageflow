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

/* --- Caricamento di un'immagine dalla stessa schermata ------------------- */
// Si carica qui e non solo nella libreria: chi scrive un articolo ha la foto
// sul desktop, e mandarlo in un'altra schermata vuol dire perdere quello che
// ha scritto. Il salvataggio dell'articolo è un invio a parte.
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_FILES['nuova_immagine'] )
	&& UPLOAD_ERR_NO_FILE !== (int) $_FILES['nuova_immagine']['error'] ) {
	verifica_token();

	// Prima si salva quello che c'è nel modulo: il caricamento ricarica la
	// pagina, e il testo scritto non deve andare perso.
	$articolo['titolo']     = trim( (string) ( $_POST['titolo'] ?? $articolo['titolo'] ) );
	$articolo['slug']       = articolo_slug_libero( (string) ( $_POST['slug'] ?? $articolo['titolo'] ), $citta['id'], $articolo['id'] );
	$articolo['seo_titolo'] = trim( (string) ( $_POST['seo_titolo'] ?? '' ) );
	$articolo['seo_desc']   = trim( (string) ( $_POST['seo_desc'] ?? '' ) );
	$articolo['estratto']   = trim( (string) ( $_POST['estratto'] ?? '' ) );
	$articolo['categoria']  = trim( (string) ( $_POST['categoria'] ?? '' ) );
	$articolo['tag']        = trim( (string) ( $_POST['tag'] ?? '' ) );
	$articolo['data']       = trim( (string) ( $_POST['data'] ?? $articolo['data'] ) );
	$articolo['corpo']      = import_pulisci_corpo( (string) ( $_POST['corpo'] ?? '' ) );
	$articolo['stato']      = 'pubblicato' === ( $_POST['stato'] ?? '' ) ? 'pubblicato' : 'bozza';

	$esito = media_carica( $_FILES['nuova_immagine'] );
	if ( $esito['ok'] ) {
		$articolo['immagine'] = $esito['file'];
		if ( vuoto( $articolo['titolo'] ) ) {
			$articolo['titolo'] = 'Articolo senza titolo';
		}
		articolo_salva( $articolo );
		avviso( 'Immagine caricata e messa sull\'articolo: ' . $esito['file'] . '.' );
	} else {
		avviso( 'Immagine non caricata — ' . $esito['messaggio'], 'errore' );
		if ( ! vuoto( $articolo['titolo'] ) ) {
			articolo_salva( $articolo );
		}
	}
	vai_a( 'admin.php?p=articolo-modifica&id=' . $articolo['id'] );
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	verifica_token();

	$articolo['titolo']     = trim( (string) ( $_POST['titolo'] ?? '' ) );
	$articolo['slug']       = articolo_slug_libero( (string) ( $_POST['slug'] ?? $articolo['titolo'] ), $citta['id'], $articolo['id'] );
	$articolo['seo_titolo'] = trim( (string) ( $_POST['seo_titolo'] ?? '' ) );
	$articolo['seo_desc']   = trim( (string) ( $_POST['seo_desc'] ?? '' ) );
	$articolo['estratto']   = trim( (string) ( $_POST['estratto'] ?? '' ) );
	$articolo['immagine']   = trim( (string) ( $_POST['immagine'] ?? '' ) );
	$articolo['categoria']  = trim( (string) ( $_POST['categoria'] ?? '' ) );
	$articolo['tag']        = trim( (string) ( $_POST['tag'] ?? '' ) );
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

$pagina_b   = pagina_blog( $citta['id'] );
$immagini   = media_tutti();
$categorie  = categorie_tutte();
$ia         = ai_attiva();
$limite_img = ini_get( 'upload_max_filesize' );
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

<form method="post" enctype="multipart/form-data">
<?php echo campo_token(); ?>
<div class="pc-griglia-2">
<div>
	<div class="pc-scheda">
		<h2>Articolo</h2>
		<div class="pc-riga pc-riga--2">
			<label>Titolo *
				<input type="text" id="campo-titolo" name="titolo" value="<?php echo e( $articolo['titolo'] ); ?>" required>
				<?php if ( $ia ) : ?>
					<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo"
						data-ai="art_titolo" data-ai-campo="campo-titolo" data-ai-articolo="<?php echo e( $articolo['id'] ); ?>" data-ai-citta="<?php echo e( $citta['id'] ); ?>">✦ Scrivi il titolo</button>
				<?php endif; ?>
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
		<div class="pc-riga pc-riga--2">
			<label>Categoria
				<select name="categoria" id="campo-categoria">
					<option value="">&mdash; nessuna &mdash;</option>
					<?php foreach ( $categorie as $c ) : ?>
						<option value="<?php echo e( $c['id'] ); ?>" <?php selected_pc( $c['id'], $articolo['categoria'] ); ?>><?php echo e( $c['nome'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<small>
					<?php if ( empty( $categorie ) ) : ?>
						Non ce ne sono ancora: <a href="admin.php?p=categorie">creane una</a>.
					<?php else : ?>
						Ogni categoria ha una sua pagina per citt&agrave;, e finisce nella sitemap.
						<a href="admin.php?p=categorie">Gestiscile</a>.
					<?php endif; ?>
				</small>
			</label>
			<label>Tag (uno per riga)
				<textarea name="tag" id="campo-tag" style="min-height:70px"><?php echo e( $articolo['tag'] ); ?></textarea>
				<?php if ( $ia ) : ?>
					<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo"
						data-ai="art_tag" data-ai-campo="campo-tag" data-ai-articolo="<?php echo e( $articolo['id'] ); ?>" data-ai-citta="<?php echo e( $citta['id'] ); ?>">&#10022; Proponi i tag</button>
				<?php endif; ?>
				<small>Si vedono in fondo all'articolo, e nei dati strutturati come keywords.</small>
			</label>
		</div>
		<label>Estratto
			<textarea name="estratto" id="campo-estratto" style="min-height:70px"><?php echo e( $articolo['estratto'] ); ?></textarea>
			<?php if ( $ia ) : ?>
				<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo"
					data-ai="art_estratto" data-ai-campo="campo-estratto" data-ai-articolo="<?php echo e( $articolo['id'] ); ?>" data-ai-citta="<?php echo e( $citta['id'] ); ?>">&#10022; Scrivi l'estratto</button>
			<?php endif; ?>
			<small>Compare nell'elenco del blog e, se manca la descrizione SEO, anche su Google.</small>
		</label>
	</div>

	<div class="pc-scheda">
		<h2>Testo</h2>
		<p class="pc-scheda__nota">
			Grassetto, corsivo, sottotitoli, elenchi e collegamenti dalla barra qui sotto.
			Script e attributi vengono tolti al salvataggio. <?php echo (int) $parole; ?> parole.
		</p>
		<?php if ( $ia ) : ?>
			<p>
				<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo"
					data-ai="art_corpo" data-ai-campo="campo-corpo-articolo" data-ai-articolo="<?php echo e( $articolo['id'] ); ?>" data-ai-citta="<?php echo e( $citta['id'] ); ?>">&#10022; Scrivi con l'assistente</button>
				<span class="pc-nota">500-650 parole gi&agrave; divise in blocchi coi sottotitoli. Rileggile: i fatti li conosci tu.</span>
			</p>
		<?php endif; ?>
		<textarea id="campo-corpo-articolo" name="corpo" data-editor style="min-height:420px" aria-label="Testo dell'articolo"><?php echo e( $articolo['corpo'] ); ?></textarea>
	</div>
</div>

<aside>
	<div class="pc-scheda">
		<h2>Come appare su Google</h2>
		<label>Titolo
			<input type="text" id="campo-seo-titolo" name="seo_titolo" value="<?php echo e( $articolo['seo_titolo'] ); ?>" data-conta data-conta-min="30" data-conta-max="65"
				placeholder="<?php echo e( $articolo['titolo'] ); ?>">
			<?php if ( $ia ) : ?>
				<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo"
					data-ai="art_seo_titolo" data-ai-campo="campo-seo-titolo" data-ai-articolo="<?php echo e( $articolo['id'] ); ?>" data-ai-citta="<?php echo e( $citta['id'] ); ?>">✦ Scrivi il titolo</button>
			<?php endif; ?>
		</label>
		<label>Descrizione
			<textarea id="campo-seo-desc" name="seo_desc" style="min-height:70px" data-conta data-conta-min="70" data-conta-max="160"
				placeholder="<?php echo e( mb_substr( $articolo['estratto'], 0, 155 ) ); ?>"><?php echo e( $articolo['seo_desc'] ); ?></textarea>
			<?php if ( $ia ) : ?>
				<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo"
					data-ai="art_seo_desc" data-ai-campo="campo-seo-desc" data-ai-articolo="<?php echo e( $articolo['id'] ); ?>" data-ai-citta="<?php echo e( $citta['id'] ); ?>">✦ Scrivi la descrizione</button>
			<?php endif; ?>
		</label>
	</div>

	<div class="pc-scheda">
		<h2>Immagine</h2>
		<label>Dalla libreria
			<select name="immagine" id="campo-immagine">
				<option value="">— nessuna —</option>
				<?php foreach ( $immagini as $m ) : ?>
					<option value="<?php echo e( $m['file'] ); ?>" <?php selected_pc( $m['file'], $articolo['immagine'] ); ?>><?php echo e( $m['file'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<div class="pc-anteprima" id="anteprima-immagine" <?php echo vuoto( $articolo['immagine'] ) ? 'style="display:none"' : ''; ?>>
			<img id="anteprima-immagine-img" src="<?php echo e( vuoto( $articolo['immagine'] ) ? '' : url_media( $articolo['immagine'] ) ); ?>" alt="">
		</div>

		<label style="margin-top:14px">Oppure carica un file
			<input type="file" name="nuova_immagine" accept="image/*">
			<small>
				JPG, PNG, WebP, GIF o SVG. Sopra i 1800 px viene rimpicciolita da sé.
				Limite di questo server: <strong><?php echo e( $limite_img ); ?></strong>.
			</small>
		</label>
		<button class="pc-btn pc-btn--ghost pc-btn--piccolo" type="submit" name="carica_immagine" value="1">Carica e salva</button>
		<p class="pc-nota">Il caricamento salva anche l'articolo, così non perdi quello che hai scritto.</p>

		<?php if ( $ia ) : ?>
			<h3 style="font-size:14px;margin:18px 0 8px">Oppure generala</h3>
			<label>Cosa deve mostrare
				<input type="text" id="campo-richiesta-immagine" placeholder="un dettaglio ravvicinato che illustra l'articolo">
				<small>Lascia vuoto e la ricava dal titolo.</small>
			</label>
			<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo" id="genera-immagine"
				data-ai-compito="art_immagine" data-ai-articolo="<?php echo e( $articolo['id'] ); ?>" data-ai-citta="<?php echo e( $citta['id'] ); ?>">✦ Genera immagine</button>
			<p class="pc-nota" id="esito-immagine" style="margin-top:10px"></p>
			<p class="pc-nota">
				<strong>Occhio.</strong> Un'immagine generata è decorativa: non spacciarla
				per una foto del tuo lavoro. Una foto vera vale molto di più.
			</p>
		<?php endif; ?>

		<p class="pc-nota"><a href="admin.php?p=media">Tutte le immagini →</a></p>
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
