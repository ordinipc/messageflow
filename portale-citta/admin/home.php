<?php
/** Home del portale e piè di pagina: i due pezzi che non appartengono a nessuna città. */
defined( 'PC_AVVIO' ) || exit;

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	verifica_token();

	$campi = array(
		'home_soprattitolo', 'home_titolo', 'home_intro', 'home_immagine',
		'home_testo', 'home_html', 'home_elenco_titolo', 'home_elenco_occhio',
		'home_sotto_elenco', 'home_seo_titolo', 'home_seo_desc',
		'piede_testo', 'piede_citta_titolo', 'piede_link_titolo', 'piede_link', 'piede_copy',
		'piede_social_titolo',
	);
	foreach ( array_keys( social_disponibili() ) as $rete ) {
		$campi[] = 'social_' . $rete;
	}
	$nuove = array();
	foreach ( $campi as $c ) {
		// L'HTML libero non si tocca: gli spazi lì dentro possono contare.
		$nuove[ $c ] = 'home_html' === $c ? (string) ( $_POST[ $c ] ?? '' ) : trim( (string) ( $_POST[ $c ] ?? '' ) );
	}

	impostazioni_salva( $nuove );
	avviso( 'Home e piè di pagina salvati.' );
	vai_a( 'admin.php?p=home' );
}

// sezioni_home() e rendi_sezione_home() stanno nel tema: servono per
// elencare le sezioni incorporabili di questa pagina.
require_once PC_RADICE . '/tema/funzioni-tema.php';
require_once PC_RADICE . '/tema/sezioni.php';

$imp      = impostazioni();
$immagini = media_tutti();
$citta    = citta_tutte( true );
?>

<div class="pc-titolo">
	<div>
		<h1>Home del portale</h1>
		<p>La pagina che si apre su <a href="<?php echo e( base_url() ); ?>/" target="_blank" rel="noopener"><?php echo e( str_replace( array( 'https://', 'http://' ), '', base_url() ) ); ?>/ ↗</a> e il piè di pagina di tutto il portale.</p>
	</div>
</div>

<form method="post">
<?php echo campo_token(); ?>

<div class="pc-linguette" data-linguette>
	<button type="button" class="pc-linguetta is-attiva" data-pannello="h-pagina">Pagina principale</button>
	<button type="button" class="pc-linguetta" data-pannello="h-piede">Piè di pagina</button>
</div>

<!-- PAGINA PRINCIPALE -->
<div class="pc-pannello is-attivo" id="h-pagina">
	<div class="pc-griglia-2">
	<div>
		<div class="pc-scheda">
			<h2>Intestazione</h2>
			<label>Soprattitolo
				<input type="text" name="home_soprattitolo" value="<?php echo e( $imp['home_soprattitolo'] ); ?>" placeholder="<?php echo e( $imp['brand'] ); ?>">
				<small>La riga piccola sopra il titolo. Vuota: si usa il nome dell'attività.</small>
			</label>
			<label>Titolo
				<input type="text" name="home_titolo" value="<?php echo e( $imp['home_titolo'] ); ?>" placeholder="Dove *operiamo*">
				<small>Fra <strong>*asterischi*</strong> la parte che esce in giallo: <code>Dove *operiamo*</code>.</small>
			</label>
			<label>Testo sotto il titolo
				<textarea name="home_intro" rows="3"><?php echo e( $imp['home_intro'] ); ?></textarea>
			</label>
			<label>Immagine di sfondo
				<select name="home_immagine">
					<option value="">— nessuna —</option>
					<?php foreach ( $immagini as $m ) : ?>
						<option value="<?php echo e( $m['file'] ); ?>" <?php selected_pc( $m['file'], $imp['home_immagine'] ); ?>><?php echo e( $m['file'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<small>Come per le pagine città: sta dietro all'intestazione. Si caricano da <a href="admin.php?p=media">Immagini</a>.</small>
			</label>
		</div>

		<div class="pc-scheda">
			<h2>Testo della pagina</h2>
			<label>Testo sopra l'elenco
				<textarea name="home_testo" rows="6" placeholder="Chi siete, da quanto lavorate, cosa trova chi apre questa pagina."><?php echo e( $imp['home_testo'] ); ?></textarea>
				<small>Una riga vuota fra un paragrafo e l'altro. Lascia vuoto per non mostrare niente.</small>
			</label>
			<label>Testo sotto l'elenco
				<textarea name="home_sotto_elenco" rows="4"><?php echo e( $imp['home_sotto_elenco'] ); ?></textarea>
			</label>
		</div>

		<div class="pc-scheda">
			<h2>Elenco delle città</h2>
			<div class="pc-riga pc-riga--2">
				<label>Soprattitolo
					<input type="text" name="home_elenco_occhio" value="<?php echo e( $imp['home_elenco_occhio'] ); ?>" placeholder="Copertura">
				</label>
				<label>Titolo
					<input type="text" name="home_elenco_titolo" value="<?php echo e( $imp['home_elenco_titolo'] ); ?>" placeholder="Le nostre città">
				</label>
			</div>
			<p class="pc-scheda__nota" style="margin:0">
				L'elenco si costruisce da solo con le città pubblicate: al momento
				<strong><?php echo count( $citta ); ?></strong><?php echo 1 === count( $citta ) ? ' città' : ' città'; ?>.
				Si aggiungono da <a href="admin.php?p=citta">Città</a>.
			</p>
		</div>

		<div class="pc-scheda">
			<h2>HTML libero</h2>
			<label>Va in fondo alla pagina
				<textarea name="home_html" rows="6" class="pc-mono" spellcheck="false"><?php echo e( $imp['home_html'] ); ?></textarea>
				<small>Per un banner, un video, un modulo esterno. Esce così com'è scritto.</small>
			</label>
		</div>
	</div>

	<aside>
		<div class="pc-scheda">
			<h2>SEO di questa pagina</h2>
			<label>Titolo per Google
				<input type="text" id="campo-home-titolo" name="home_seo_titolo" value="<?php echo e( $imp['home_seo_titolo'] ); ?>"
					placeholder="<?php echo e( $imp['brand'] . ' — tutte le città in cui operiamo' ); ?>">
				<small>Vuoto: si usa il nome dell'attività. Ideale 30-65 caratteri.</small>
			</label>
			<label>Descrizione per Google
				<textarea id="campo-home-desc" name="home_seo_desc" rows="3"><?php echo e( $imp['home_seo_desc'] ); ?></textarea>
				<small>Ideale 70-160 caratteri.</small>
			</label>
		</div>

		<div class="pc-scheda">
			<h2>Shortcode per WordPress</h2>
			<p class="pc-scheda__nota">
				Per mostrare l'elenco delle zone dentro una pagina di WordPress.
				Serve il plugin <strong>Portale Città — shortcode</strong>.
			</p>

			<label>Tutta la pagina
				<input type="text" class="pc-mono" readonly value="<?php echo e( shortcode_home() ); ?>"
					title="<?php echo e( shortcode_home() ); ?>" onfocus="this.select()">
				<small>Clicca per copiare.</small>
			</label>

			<label>Una sezione sola
				<select id="scelta-sezione">
					<?php foreach ( sezioni_home() as $chiave => $nome ) : ?>
						<option value="<?php echo e( $chiave ); ?>" <?php selected_pc( 'citta', $chiave ); ?>><?php echo e( $nome ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<input type="text" class="pc-mono" readonly id="shortcode-sezione"
				value="<?php echo e( shortcode_home( 'citta' ) ); ?>"
				title="<?php echo e( shortcode_home( 'citta' ) ); ?>"
				onfocus="this.select()"
				data-modello="<?php echo e( shortcode_home( '__SEZIONE__' ) ); ?>">
			<small>
				<strong>Solo la griglia delle zone</strong> è quello che serve quasi sempre:
				si incolla dove vuoi e non fa doppione con la pagina del portale.
			</small>
		</div>

		<div class="pc-scheda">
			<h2>Com'è fatta</h2>
			<p class="pc-scheda__nota" style="margin:0">
				Questa pagina non appartiene a nessuna città: è la porta d'ingresso del
				portale. Serve a chi arriva da fuori e non sa ancora quale città gli
				interessa — e a Google, che da qui raggiunge tutte le città.
			</p>
		</div>
	</aside>
	</div>
</div>

<!-- PIÈ DI PAGINA -->
<div class="pc-pannello" id="h-piede">
	<div class="pc-griglia-2">
	<div>
		<div class="pc-scheda">
			<h2>Prima colonna</h2>
			<p class="pc-scheda__nota">
				Sotto il nome dell'attività ci sono già indirizzo, telefono, email e
				P. IVA: vengono dalla città che si sta guardando, o dalle impostazioni.
			</p>
			<label>Riga di presentazione
				<textarea name="piede_testo" rows="3" placeholder="Duplicazione chiavi e serrature dal 2013."><?php echo e( $imp['piede_testo'] ); ?></textarea>
				<small>Una frase breve sotto il nome. Lascia vuoto per non metterla.</small>
			</label>
		</div>

		<div class="pc-scheda">
			<h2>Colonna delle città</h2>
			<label>Titolo
				<input type="text" name="piede_citta_titolo" value="<?php echo e( $imp['piede_citta_titolo'] ); ?>" placeholder="Dove operiamo">
				<small>L'elenco sotto è automatico: le altre città del portale.</small>
			</label>
		</div>

		<div class="pc-scheda">
			<h2>Colonna dei collegamenti</h2>
			<label>Titolo
				<input type="text" name="piede_link_titolo" value="<?php echo e( $imp['piede_link_titolo'] ); ?>" placeholder="Sito">
			</label>
			<label>Collegamenti tuoi
				<textarea name="piede_link" rows="5" placeholder="Preventivi | https://www.chiaviitalia.it/preventivi/&#10;Blog | https://www.chiaviitalia.it/blog/"><?php echo e( $imp['piede_link'] ); ?></textarea>
				<small>Uno per riga, nella forma <code>Etichetta | indirizzo</code>. Si aggiungono a quelli che ci sono già.</small>
			</label>
			<p class="pc-scheda__nota" style="margin:0">
				Sito principale, privacy e cookie si mettono nelle
				<a href="admin.php?p=impostazioni">impostazioni</a> e compaiono qui da soli.
			</p>
		</div>

		<div class="pc-scheda">
			<h2>Social</h2>
			<p class="pc-scheda__nota">
				Le icone escono nel piè di pagina, sotto i recapiti. Compaiono solo quelle
				con un indirizzo scritto: lascia vuote le altre.
			</p>
			<label>Titolo
				<input type="text" name="piede_social_titolo" value="<?php echo e( $imp['piede_social_titolo'] ); ?>" placeholder="Social">
			</label>
			<div class="pc-riga pc-riga--2">
				<?php foreach ( social_disponibili() as $rete => $nome ) : ?>
					<label><?php echo e( $nome ); ?>
						<input type="url" name="social_<?php echo e( $rete ); ?>"
							value="<?php echo e( $imp[ 'social_' . $rete ] ); ?>"
							placeholder="https://...">
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="pc-scheda">
			<h2>Riga in fondo</h2>
			<label>Testo del copyright
				<input type="text" name="piede_copy" value="<?php echo e( $imp['piede_copy'] ); ?>" placeholder="© <?php echo e( date( 'Y' ) ); ?> <?php echo e( $imp['brand'] ); ?>">
				<small>Vuoto: esce «© <?php echo e( date( 'Y' ) . ' ' . $imp['brand'] ); ?>». L'anno si aggiorna da solo.</small>
			</label>
		</div>
	</div>

	<aside>
		<div class="pc-scheda">
			<h2>Dove si vede</h2>
			<p class="pc-scheda__nota" style="margin:0">
				Il piè di pagina è lo stesso su tutte le pagine del portale: home,
				città, pagine e articoli. Cambiarlo qui lo cambia ovunque.
			</p>
		</div>
	</aside>
	</div>
</div>

<div class="pc-salva">
	<p class="pc-salva__nota">Le modifiche si vedono subito sul sito.</p>
	<a class="pc-btn pc-btn--ghost" href="<?php echo e( base_url() ); ?>/" target="_blank" rel="noopener">Anteprima ↗</a>
	<button class="pc-btn" type="submit">Salva</button>
</div>
</form>
