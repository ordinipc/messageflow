<?php
/** Creazione e modifica di una pagina. */
defined( 'PC_AVVIO' ) || exit;

$id     = (string) ( $_GET['id'] ?? '' );
$pagina = '' !== $id ? pagina_per_id( $id ) : null;
$nuova  = null === $pagina;

if ( $nuova ) {
	$citta_id = (string) ( $_GET['citta'] ?? '' );
	$citta    = citta_per_id( $citta_id );
	if ( ! $citta ) {
		echo '<div class="pc-scheda pc-vuoto"><h3>Città mancante</h3><p>Apri le pagine di una città per creare una pagina.</p><a class="pc-btn" href="admin.php?p=citta">Vai alle città</a></div>';
		return;
	}
	$pagina             = pagina_predefinita();
	$pagina['id']       = nuovo_id();
	$pagina['citta_id'] = $citta['id'];
} else {
	$citta = citta_per_id( $pagina['citta_id'] );
	if ( ! $citta ) {
		echo '<div class="pc-scheda pc-vuoto"><h3>Città mancante</h3><p>Questa pagina punta a una città che non esiste più.</p></div>';
		return;
	}
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	verifica_token();

	$pagina['titolo'] = trim( (string) ( $_POST['titolo'] ?? '' ) );
	$pagina['tipo']   = in_array( $_POST['tipo'] ?? '', array( 'home', 'servizio', 'fissa' ), true ) ? $_POST['tipo'] : 'fissa';

	// Una sola pagina principale per città.
	$nota = '';
	if ( 'home' === $pagina['tipo'] ) {
		$esistente = pagina_home( $citta['id'] );
		if ( $esistente && $esistente['id'] !== $pagina['id'] ) {
			$pagina['tipo'] = 'fissa';
			$nota = ' Attenzione: "' . $esistente['titolo'] . '" è già la pagina principale di ' . $citta['nome'] . ', quindi questa è stata salvata come pagina fissa.';
		}
	}

	$pagina['slug']        = pagina_slug_libero( (string) ( $_POST['slug'] ?? $pagina['titolo'] ), $citta['id'], $pagina['id'] );
	$pagina['servizio_id'] = (string) ( $_POST['servizio_id'] ?? '' );
	$pagina['h1']          = trim( (string) ( $_POST['h1'] ?? '' ) );
	$pagina['seo_titolo']  = trim( (string) ( $_POST['seo_titolo'] ?? '' ) );
	$pagina['seo_desc']    = trim( (string) ( $_POST['seo_desc'] ?? '' ) );
	$pagina['immagine']    = trim( (string) ( $_POST['immagine'] ?? '' ) );
	$pagina['intro']       = trim( (string) ( $_POST['intro'] ?? '' ) );
	$pagina['corpo']       = trim( (string) ( $_POST['corpo'] ?? '' ) );
	$pagina['inclusi']     = trim( (string) ( $_POST['inclusi'] ?? '' ) );
	$pagina['prezzo_da']   = trim( (string) ( $_POST['prezzo_da'] ?? '' ) );
	$pagina['prezzo_a']    = trim( (string) ( $_POST['prezzo_a'] ?? '' ) );
	$pagina['prezzo_note'] = trim( (string) ( $_POST['prezzo_note'] ?? '' ) );
	$pagina['html']        = (string) ( $_POST['html'] ?? '' );
	$pagina['css']         = (string) ( $_POST['css'] ?? '' );
	$pagina['js']          = (string) ( $_POST['js'] ?? '' );
	$pagina['menu_mostra'] = isset( $_POST['menu_mostra'] ) ? 1 : 0;
	$pagina['menu_ordine'] = (int) ( $_POST['menu_ordine'] ?? 10 );
	$pagina['stato']       = 'pubblicata' === ( $_POST['stato'] ?? '' ) ? 'pubblicata' : 'bozza';

	$pagina['processo'] = array();
	foreach ( (array) ( $_POST['processo'] ?? array() ) as $p ) {
		if ( vuoto( $p['titolo'] ?? '' ) && vuoto( $p['testo'] ?? '' ) ) {
			continue;
		}
		$pagina['processo'][] = array(
			'titolo' => trim( (string) ( $p['titolo'] ?? '' ) ),
			'testo'  => trim( (string) ( $p['testo'] ?? '' ) ),
			'durata' => trim( (string) ( $p['durata'] ?? '' ) ),
		);
	}

	$pagina['faq'] = array();
	foreach ( (array) ( $_POST['faq'] ?? array() ) as $f ) {
		if ( vuoto( $f['domanda'] ?? '' ) ) {
			continue;
		}
		$pagina['faq'][] = array(
			'domanda'  => trim( (string) $f['domanda'] ),
			'risposta' => trim( (string) ( $f['risposta'] ?? '' ) ),
		);
	}

	if ( vuoto( $pagina['titolo'] ) ) {
		avviso( 'Il titolo della pagina è obbligatorio.', 'errore' );
	} else {
		pagina_salva( $pagina );
		avviso( 'Pagina salvata.' . $nota, '' === $nota ? 'ok' : 'errore' );
		vai_a( 'admin.php?p=pagina-modifica&id=' . $pagina['id'] );
	}
}

$analisi  = seo_analisi( $citta, $pagina );
$punti    = $analisi['punteggio'];
$classe   = $punti >= 80 ? 'is-alto' : ( $punti >= 55 ? 'is-medio' : 'is-basso' );
$immagini = media_tutti();
$lista_s  = servizi();
?>

<div class="pc-titolo">
	<div>
		<h1><?php echo $nuova ? 'Nuova pagina' : e( $pagina['titolo'] ); ?></h1>
		<p><?php echo e( $citta['nome'] ); ?> · <a href="<?php echo e( url_pagina( $citta, $pagina ) ); ?>" target="_blank" rel="noopener"><?php echo e( str_replace( base_url(), '', url_pagina( $citta, $pagina ) ) ); ?> ↗</a></p>
	</div>
	<div class="pc-titolo__azioni">
		<a class="pc-btn pc-btn--ghost" href="admin.php?p=pagine&citta=<?php echo e( $citta['id'] ); ?>">← Pagine di <?php echo e( $citta['nome'] ); ?></a>
	</div>
</div>

<form method="post">
<?php echo campo_token(); ?>
<div class="pc-griglia-2">
<div>

	<div class="pc-linguette" data-linguette>
		<button type="button" class="pc-linguetta is-attiva" data-pannello="q-testi">Testi</button>
		<button type="button" class="pc-linguetta" data-pannello="q-dettagli">Dettagli del servizio</button>
		<button type="button" class="pc-linguetta" data-pannello="q-faq">FAQ</button>
		<button type="button" class="pc-linguetta" data-pannello="q-seo">SEO</button>
		<button type="button" class="pc-linguetta" data-pannello="q-codice">Codice</button>
	</div>

	<!-- TESTI -->
	<div class="pc-pannello is-attivo" id="q-testi">
		<div class="pc-scheda">
			<h2>Identità della pagina</h2>
			<div class="pc-riga pc-riga--2">
				<label>Titolo *
					<input type="text" id="campo-titolo" name="titolo" value="<?php echo e( $pagina['titolo'] ); ?>" required placeholder="Duplicazione chiavi auto">
					<small>Scrivi il servizio senza la città: la città viene aggiunta da sola.</small>
				</label>
				<label>Indirizzo nell'URL
					<input type="text" name="slug" data-slug-da="campo-titolo" value="<?php echo e( $pagina['slug'] ); ?>">
					<small>/<?php echo e( $citta['slug'] ); ?>/<strong>slug</strong>/</small>
				</label>
			</div>
			<div class="pc-riga pc-riga--2">
				<label>Tipo di pagina
					<select name="tipo">
						<option value="home" <?php selected_pc( 'home', $pagina['tipo'] ); ?>>Principale (è /<?php echo e( $citta['slug'] ); ?>/)</option>
						<option value="servizio" <?php selected_pc( 'servizio', $pagina['tipo'] ); ?>>Servizio (genera lo schema Service)</option>
						<option value="fissa" <?php selected_pc( 'fissa', $pagina['tipo'] ); ?>>Pagina fissa (chi siamo, contatti…)</option>
					</select>
				</label>
				<label>Tipo di servizio collegato
					<select name="servizio_id">
						<option value="">— nessuno —</option>
						<?php foreach ( $lista_s as $s ) : ?>
							<option value="<?php echo e( $s['id'] ); ?>" <?php selected_pc( $s['id'], $pagina['servizio_id'] ); ?>><?php echo e( $s['nome'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<small>Serve solo per raggruppare le pagine dello stesso servizio fra città diverse.</small>
				</label>
			</div>
			<label>Titolo H1 personalizzato
				<input type="text" name="h1" value="<?php echo e( $pagina['h1'] ); ?>" placeholder="<?php echo e( seo_h1( $citta, $pagina ) ); ?>">
				<small>Lascia vuoto per usare: <strong><?php echo e( seo_h1( $citta, $pagina ) ); ?></strong></small>
			</label>
		</div>

		<div class="pc-scheda">
			<h2>Introduzione
				<?php if ( ai_attiva() ) : ?>
					<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo" style="float:right"
						data-ai="intro" data-ai-campo="campo-intro" data-ai-pagina="<?php echo e( $pagina['id'] ); ?>" data-ai-citta="<?php echo e( $citta['id'] ); ?>">✦ Scrivi con l'assistente</button>
				<?php endif; ?>
			</h2>
			<p class="pc-scheda__nota">Una o due frasi sotto il titolo principale.</p>
			<label><textarea id="campo-intro" name="intro" style="min-height:80px"><?php echo e( $pagina['intro'] ); ?></textarea></label>
		</div>

		<div class="pc-scheda">
			<h2>Testo di approfondimento
				<?php if ( ai_attiva() ) : ?>
					<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo" style="float:right"
						data-ai="corpo" data-ai-campo="campo-corpo" data-ai-pagina="<?php echo e( $pagina['id'] ); ?>" data-ai-citta="<?php echo e( $citta['id'] ); ?>">✦ Scrivi con l'assistente</button>
				<?php endif; ?>
			</h2>
			<p class="pc-scheda__nota">Il corpo della pagina. Separa i paragrafi con una riga vuota. Minimo consigliato: 300 parole.</p>
			<label><textarea id="campo-corpo" name="corpo" style="min-height:260px"><?php echo e( $pagina['corpo'] ); ?></textarea></label>
			<p class="pc-nota"><strong>Deve essere diverso da quello delle altre città.</strong> Testi identici con solo il nome cambiato è il motivo numero uno per cui Google non indicizza le pagine locali.</p>
		</div>
	</div>

	<!-- DETTAGLI -->
	<div class="pc-pannello" id="q-dettagli">
		<div class="pc-scheda">
			<h2>Cosa comprende</h2>
			<label>Una riga per voce
				<textarea name="inclusi" placeholder="Lettura del transponder&#10;Taglio della chiave grezza&#10;Prova di avviamento"><?php echo e( $pagina['inclusi'] ); ?></textarea>
			</label>
		</div>

		<div class="pc-scheda">
			<h2>Come funziona, passo per passo</h2>
			<div data-ripeti="processo">
				<?php foreach ( (array) $pagina['processo'] as $i => $p ) : ?>
					<div class="pc-ripeti__voce">
						<button type="button" class="pc-ripeti__togli" title="Togli">×</button>
						<div class="pc-riga pc-riga--2">
							<label>Titolo del passo <input type="text" name="processo[<?php echo (int) $i; ?>][titolo]" value="<?php echo e( $p['titolo'] ?? '' ); ?>"></label>
							<label>Durata <input type="text" name="processo[<?php echo (int) $i; ?>][durata]" value="<?php echo e( $p['durata'] ?? '' ); ?>" placeholder="5 minuti"></label>
						</div>
						<label>Descrizione <textarea name="processo[<?php echo (int) $i; ?>][testo]" style="min-height:70px"><?php echo e( $p['testo'] ?? '' ); ?></textarea></label>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo" data-aggiungi="processo">+ Aggiungi passo</button>
			<template id="modello-processo">
				<div class="pc-ripeti__voce">
					<button type="button" class="pc-ripeti__togli" title="Togli">×</button>
					<div class="pc-riga pc-riga--2">
						<label>Titolo del passo <input type="text" name="processo[__i__][titolo]"></label>
						<label>Durata <input type="text" name="processo[__i__][durata]" placeholder="5 minuti"></label>
					</div>
					<label>Descrizione <textarea name="processo[__i__][testo]" style="min-height:70px"></textarea></label>
				</div>
			</template>
		</div>

		<div class="pc-scheda">
			<h2>Prezzi</h2>
			<p class="pc-scheda__nota">Se compili il prezzo minimo, la pagina genera anche lo schema <code>Offer</code>.</p>
			<div class="pc-riga pc-riga--2">
				<label>Da (€) <input type="text" name="prezzo_da" value="<?php echo e( $pagina['prezzo_da'] ); ?>" placeholder="15"></label>
				<label>A (€) <input type="text" name="prezzo_a" value="<?php echo e( $pagina['prezzo_a'] ); ?>" placeholder="180"></label>
			</div>
			<label>Note sul prezzo
				<textarea name="prezzo_note" style="min-height:80px" placeholder="Preventivo gratuito e senza impegno.&#10;Pagamenti accettati: contanti, bancomat, carte."><?php echo e( $pagina['prezzo_note'] ); ?></textarea>
			</label>
		</div>
	</div>

	<!-- FAQ -->
	<div class="pc-pannello" id="q-faq">
		<div class="pc-scheda">
			<h2>Domande frequenti
				<?php if ( ai_attiva() ) : ?>
					<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo" style="float:right"
						data-ai="faq" data-ai-pagina="<?php echo e( $pagina['id'] ); ?>" data-ai-citta="<?php echo e( $citta['id'] ); ?>">✦ Proponi 6 FAQ</button>
				<?php endif; ?>
			</h2>
			<p class="pc-scheda__nota">Da 3 in su generano lo schema <code>FAQPage</code>, che può comparire direttamente nei risultati.</p>
			<div data-ripeti="faq">
				<?php foreach ( (array) $pagina['faq'] as $i => $f ) : ?>
					<div class="pc-ripeti__voce">
						<button type="button" class="pc-ripeti__togli" title="Togli">×</button>
						<label>Domanda <input type="text" name="faq[<?php echo (int) $i; ?>][domanda]" value="<?php echo e( $f['domanda'] ?? '' ); ?>"></label>
						<label>Risposta <textarea name="faq[<?php echo (int) $i; ?>][risposta]" style="min-height:80px"><?php echo e( $f['risposta'] ?? '' ); ?></textarea></label>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo" data-aggiungi="faq">+ Aggiungi domanda</button>
			<template id="modello-faq">
				<div class="pc-ripeti__voce">
					<button type="button" class="pc-ripeti__togli" title="Togli">×</button>
					<label>Domanda <input type="text" name="faq[__i__][domanda]"></label>
					<label>Risposta <textarea name="faq[__i__][risposta]" style="min-height:80px"></textarea></label>
				</div>
			</template>
		</div>
	</div>

	<!-- SEO -->
	<div class="pc-pannello" id="q-seo">
		<div class="pc-scheda">
			<h2>Come appare su Google</h2>
			<label>Titolo per Google (title)
				<input type="text" name="seo_titolo" value="<?php echo e( $pagina['seo_titolo'] ); ?>" data-conta data-conta-min="30" data-conta-max="65"
					placeholder="<?php echo e( seo_titolo( $citta, $pagina ) ); ?>">
				<small>Lascia vuoto per generarlo da titolo + città + nome dell'attività.</small>
			</label>
			<label>Descrizione per Google (meta description)
				<textarea name="seo_desc" id="campo-desc" style="min-height:80px" data-conta data-conta-min="70" data-conta-max="160"
					placeholder="<?php echo e( seo_descrizione( $citta, $pagina ) ); ?>"><?php echo e( $pagina['seo_desc'] ); ?></textarea>
			</label>
			<?php if ( ai_attiva() ) : ?>
				<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo"
					data-ai="descrizione" data-ai-campo="campo-desc" data-ai-pagina="<?php echo e( $pagina['id'] ); ?>" data-ai-citta="<?php echo e( $citta['id'] ); ?>">✦ Scrivi la descrizione</button>
			<?php endif; ?>
		</div>

		<div class="pc-scheda">
			<h2>Immagine di anteprima</h2>
			<p class="pc-scheda__nota">Usata da Open Graph quando la pagina viene condivisa.</p>
			<label>
				<select name="immagine">
					<option value="">— nessuna —</option>
					<?php foreach ( $immagini as $m ) : ?>
						<option value="<?php echo e( $m['file'] ); ?>" <?php selected_pc( $m['file'], $pagina['immagine'] ); ?>><?php echo e( $m['file'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php if ( ! vuoto( $pagina['immagine'] ) ) : ?>
				<div class="pc-anteprima"><img src="<?php echo e( url_media( $pagina['immagine'] ) ); ?>" alt=""></div>
			<?php endif; ?>
			<p class="pc-nota"><a href="admin.php?p=media">Carica altre immagini →</a></p>
		</div>

		<div class="pc-scheda">
			<h2>Posizione nel menu</h2>
			<label class="pc-inline">
				<input type="checkbox" name="menu_mostra" value="1" <?php checked_pc( (int) $pagina['menu_mostra'] ); ?>>
				Mostra questa pagina nel menu di <?php echo e( $citta['nome'] ); ?>
			</label>
			<label style="max-width:160px">Ordine <input type="number" name="menu_ordine" value="<?php echo (int) $pagina['menu_ordine']; ?>"><small>Numero più basso = più a sinistra.</small></label>
		</div>
	</div>

	<!-- CODICE -->
	<div class="pc-pannello" id="q-codice">
		<div class="pc-scheda">
			<h2>HTML libero</h2>
			<p class="pc-scheda__nota">Inserito come sezione in fondo al testo di approfondimento.</p>
			<label><textarea class="pc-codice" name="html" spellcheck="false"><?php echo e( $pagina['html'] ); ?></textarea></label>
		</div>
		<div class="pc-scheda">
			<h2>CSS e JavaScript della pagina</h2>
			<label>CSS <textarea class="pc-codice" name="css" spellcheck="false"><?php echo e( $pagina['css'] ); ?></textarea></label>
			<label>JavaScript <textarea class="pc-codice" name="js" spellcheck="false"><?php echo e( $pagina['js'] ); ?></textarea></label>
		</div>
	</div>

</div>

<!-- COLONNA LATERALE -->
<aside>
	<div class="pc-scheda">
		<h2>Punteggio SEO</h2>
		<div class="pc-punteggio">
			<div class="pc-punteggio__valore <?php echo e( $classe ); ?>"><?php echo (int) $punti; ?></div>
			<p class="pc-nota" style="margin:0">Calcolato all'ultimo salvataggio. Salva per aggiornarlo.</p>
		</div>
		<ul class="pc-controlli">
			<?php foreach ( $analisi['controlli'] as $c ) : ?>
				<li class="<?php echo $c['ok'] ? 'is-ok' : 'is-ko'; ?>">
					<b><?php echo $c['ok'] ? '✓' : '!'; ?></b>
					<div><?php echo e( $c['nome'] ); ?><span><?php echo e( $c['nota'] ); ?></span></div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="pc-scheda">
		<h2>Anteprima del risultato</h2>
		<div style="font-family:arial,sans-serif;line-height:1.35">
			<div style="color:#202124;font-size:12px"><?php echo e( str_replace( array( 'https://', 'http://' ), '', url_pagina( $citta, $pagina ) ) ); ?></div>
			<div style="color:#1a0dab;font-size:18px;margin:2px 0"><?php echo e( mb_substr( seo_titolo( $citta, $pagina ), 0, 65 ) ); ?></div>
			<div style="color:#4d5156;font-size:13px"><?php echo e( mb_substr( seo_descrizione( $citta, $pagina ), 0, 160 ) ); ?></div>
		</div>
	</div>

	<?php if ( ! ai_attiva() ) : ?>
	<div class="pc-scheda">
		<h2>Assistente di scrittura</h2>
		<p class="pc-scheda__nota">Inserisci una chiave Gemini nelle <a href="admin.php?p=impostazioni">Impostazioni</a> per far proporre testi e FAQ al modello. Restano comunque da rivedere a mano.</p>
	</div>
	<?php endif; ?>
</aside>
</div>

<div class="pc-salva">
	<label class="pc-inline" style="margin:0">
		<input type="checkbox" name="stato" value="pubblicata" <?php checked_pc( 'pubblicata' === $pagina['stato'] ); ?>>
		Pagina pubblicata
		<?php if ( 'pubblicata' !== $citta['stato'] ) : ?>
			<span class="pc-nota">— attenzione: la città è in bozza, quindi resta comunque invisibile</span>
		<?php endif; ?>
	</label>
	<div class="pc-titolo__azioni">
		<a class="pc-btn pc-btn--ghost" href="<?php echo e( url_pagina( $citta, $pagina ) ); ?>" target="_blank" rel="noopener">Anteprima ↗</a>
		<button class="pc-btn" type="submit"><?php echo $nuova ? 'Crea la pagina' : 'Salva'; ?></button>
	</div>
</div>
</form>
