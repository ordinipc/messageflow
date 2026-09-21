<?php
/** Impostazioni generali del portale. */
defined( 'PC_AVVIO' ) || exit;

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	verifica_token();

	$nuove = array(
		'brand'           => trim( (string) ( $_POST['brand'] ?? '' ) ),
		'sito_url'        => rtrim( trim( (string) ( $_POST['sito_url'] ?? '' ) ), '/' ),
		'sito_principale' => trim( (string) ( $_POST['sito_principale'] ?? '' ) ),
		'logo'            => trim( (string) ( $_POST['logo'] ?? '' ) ),
		'favicon'         => trim( (string) ( $_POST['favicon'] ?? '' ) ),
		'colore_accento'  => trim( (string) ( $_POST['colore_accento'] ?? '#ffd400' ) ),
		'colore_scuro'    => trim( (string) ( $_POST['colore_scuro'] ?? '#0b0b0b' ) ),
		'colore_testo'    => trim( (string) ( $_POST['colore_testo'] ?? '#111111' ) ),
		'colore_chiaro'   => trim( (string) ( $_POST['colore_chiaro'] ?? '#f6f6f6' ) ),
		'raggio'          => trim( (string) ( $_POST['raggio'] ?? '10px' ) ),
		'stile_sezioni'   => 'elenco' === ( $_POST['stile_sezioni'] ?? '' ) ? 'elenco' : 'card',
		'effetti'         => isset( $_POST['effetti'] ) ? '1' : '0',
		'telefono'        => trim( (string) ( $_POST['telefono'] ?? '' ) ),
		'whatsapp'        => trim( (string) ( $_POST['whatsapp'] ?? '' ) ),
		'email'           => trim( (string) ( $_POST['email'] ?? '' ) ),
		'piva'            => trim( (string) ( $_POST['piva'] ?? '' ) ),
		'nazione'         => strtoupper( trim( (string) ( $_POST['nazione'] ?? 'IT' ) ) ),
		'lingua'          => trim( (string) ( $_POST['lingua'] ?? 'it-IT' ) ),
		'seo_suffisso'    => trim( (string) ( $_POST['seo_suffisso'] ?? '' ) ),
		'ga_id'           => trim( (string) ( $_POST['ga_id'] ?? '' ) ),
		'gemini_key'      => trim( (string) ( $_POST['gemini_key'] ?? '' ) ),
		'gemini_modello'  => trim( (string) ( $_POST['gemini_modello'] ?? 'gemini-3.6-flash' ) ),
		'gemini_modello_immagini' => trim( (string) ( $_POST['gemini_modello_immagini'] ?? 'gemini-3.1-flash-image' ) ),
		'indicizza'       => isset( $_POST['indicizza'] ) ? '1' : '0',
		'privacy_url'     => trim( (string) ( $_POST['privacy_url'] ?? '' ) ),
		'cookie_url'      => trim( (string) ( $_POST['cookie_url'] ?? '' ) ),
		'css_globale'     => (string) ( $_POST['css_globale'] ?? '' ),
		'js_globale'      => (string) ( $_POST['js_globale'] ?? '' ),
	);

	// La password non è più del portale ma della persona collegata.
	$password = (string) ( $_POST['password_nuova'] ?? '' );
	$io       = utente_corrente();
	if ( '' !== $password && $io ) {
		$guaio = utente_password( $io['id'], $password );
		if ( '' !== $guaio ) {
			avviso( $guaio . ' Non è stata cambiata.', 'errore' );
		}
	}

	impostazioni_salva( $nuove );
	avviso( 'Impostazioni salvate.' );
	vai_a( 'admin.php?p=impostazioni' );
}

$imp      = impostazioni();
$immagini = media_tutti();
$cfg      = db_config();
?>

<div class="pc-titolo">
	<div>
		<h1>Impostazioni</h1>
		<p>Valgono per tutte le città, salvo dove la singola città indica qualcosa di diverso.</p>
	</div>
</div>

<form method="post">
<?php echo campo_token(); ?>

<div class="pc-linguette" data-linguette>
	<button type="button" class="pc-linguetta is-attiva" data-pannello="i-generale">Generale</button>
	<button type="button" class="pc-linguetta" data-pannello="i-aspetto">Aspetto</button>
	<button type="button" class="pc-linguetta" data-pannello="i-contatti">Contatti</button>
	<button type="button" class="pc-linguetta" data-pannello="i-seo">SEO e tracciamento</button>
	<button type="button" class="pc-linguetta" data-pannello="i-ai">Assistente</button>
	<button type="button" class="pc-linguetta" data-pannello="i-sistema">Sistema</button>
</div>

<div class="pc-pannello is-attivo" id="i-generale">
	<div class="pc-scheda">
		<h2>Identità</h2>
		<label>Nome dell'attività <input type="text" name="brand" value="<?php echo e( $imp['brand'] ); ?>" required></label>
		<label>Indirizzo del portale
			<input type="url" name="sito_url" value="<?php echo e( $imp['sito_url'] ); ?>" placeholder="https://www.tuosito.it/citta">
			<small>Senza barra finale. Da qui nascono canonical, sitemap e link interni: se è sbagliato, lo sono tutti.</small>
		</label>
		<label>Indirizzo del sito principale
			<input type="url" name="sito_principale" value="<?php echo e( $imp['sito_principale'] ); ?>" placeholder="https://www.tuosito.it">
			<small>Il logo in alto porta qui.</small>
		</label>
		<div class="pc-riga pc-riga--2">
			<label>Nazione (sigla) <input type="text" name="nazione" maxlength="2" value="<?php echo e( $imp['nazione'] ); ?>"></label>
			<label>Lingua <input type="text" name="lingua" value="<?php echo e( $imp['lingua'] ); ?>" placeholder="it-IT"></label>
		</div>
	</div>
</div>

<div class="pc-pannello" id="i-aspetto">
	<div class="pc-scheda">
		<h2>Colori</h2>
		<p class="pc-scheda__nota">Applicati a tutte le pagine pubbliche.</p>
		<div class="pc-riga pc-riga--2">
			<label>Colore d'accento <input type="text" name="colore_accento" value="<?php echo e( $imp['colore_accento'] ); ?>"></label>
			<label>Colore scuro <input type="text" name="colore_scuro" value="<?php echo e( $imp['colore_scuro'] ); ?>"></label>
			<label>Colore del testo <input type="text" name="colore_testo" value="<?php echo e( $imp['colore_testo'] ); ?>"></label>
			<label>Colore chiaro <input type="text" name="colore_chiaro" value="<?php echo e( $imp['colore_chiaro'] ); ?>"></label>
		</div>
		<label style="max-width:200px">Arrotondamento angoli <input type="text" name="raggio" value="<?php echo e( $imp['raggio'] ); ?>" placeholder="10px"></label>
	</div>

	<div class="pc-scheda">
		<h2>Sezioni ed effetti</h2>
		<p class="pc-scheda__nota">Come si presentano i contenuti dentro le sezioni delle pagine.</p>

		<label>Stile delle sezioni
			<select name="stile_sezioni">
				<option value="card" <?php selected_pc( 'card', $imp['stile_sezioni'] ); ?>>Riquadri (card) — consigliato</option>
				<option value="elenco" <?php selected_pc( 'elenco', $imp['stile_sezioni'] ); ?>>Elenco — righe sottili, più compatto</option>
			</select>
			<small>Vale per: cosa comprende, perché sceglierci, numeri, processo, zone, recensioni, team, orari, FAQ, servizi e altre città.</small>
		</label>

		<label class="pc-inline">
			<input type="checkbox" name="effetti" value="1" <?php checked_pc( '1' === (string) $imp['effetti'] ); ?>>
			Effetti dinamici
		</label>
		<p class="pc-nota">
			Comparsa a scalare quando la sezione entra nello schermo, sollevamento e alone che segue
			il cursore sui riquadri, numeri che salgono fino al valore. Si spengono da soli su chi ha
			chiesto meno animazioni nel sistema, e non servono a niente per il posizionamento: sono
            solo per chi legge.
		</p>
	</div>

	<div class="pc-scheda">
		<h2>Logo e icona</h2>
		<div class="pc-riga pc-riga--2">
			<label>Logo
				<select name="logo">
					<option value="">— nessuno (scritta) —</option>
					<?php foreach ( $immagini as $m ) : ?>
						<option value="<?php echo e( $m['file'] ); ?>" <?php selected_pc( $m['file'], $imp['logo'] ); ?>><?php echo e( $m['file'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Favicon
				<select name="favicon">
					<option value="">— nessuna —</option>
					<?php foreach ( $immagini as $m ) : ?>
						<option value="<?php echo e( $m['file'] ); ?>" <?php selected_pc( $m['file'], $imp['favicon'] ); ?>><?php echo e( $m['file'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
		<p class="pc-nota"><a href="admin.php?p=media">Carica immagini →</a></p>
	</div>

	<div class="pc-scheda">
		<h2>CSS e JavaScript globali</h2>
		<p class="pc-scheda__nota">Su tutte le pagine di tutte le città.</p>
		<label>CSS <textarea class="pc-codice" name="css_globale" spellcheck="false"><?php echo e( $imp['css_globale'] ); ?></textarea></label>
		<label>JavaScript <textarea class="pc-codice" name="js_globale" spellcheck="false"><?php echo e( $imp['js_globale'] ); ?></textarea></label>
	</div>
</div>

<div class="pc-pannello" id="i-contatti">
	<div class="pc-scheda">
		<h2>Contatti generali</h2>
		<p class="pc-scheda__nota">Usati quando una città non ha contatti propri.</p>
		<div class="pc-riga pc-riga--3">
			<label>Telefono <input type="tel" name="telefono" value="<?php echo e( $imp['telefono'] ); ?>"></label>
			<label>WhatsApp <input type="tel" name="whatsapp" value="<?php echo e( $imp['whatsapp'] ); ?>"></label>
			<label>Email <input type="email" name="email" value="<?php echo e( $imp['email'] ); ?>"></label>
		</div>
		<label>Partita IVA <input type="text" name="piva" value="<?php echo e( $imp['piva'] ); ?>"></label>
		<div class="pc-riga pc-riga--2">
			<label>Privacy Policy (URL) <input type="url" name="privacy_url" value="<?php echo e( $imp['privacy_url'] ); ?>"></label>
			<label>Cookie Policy (URL) <input type="url" name="cookie_url" value="<?php echo e( $imp['cookie_url'] ); ?>"></label>
		</div>
	</div>
</div>

<div class="pc-pannello" id="i-seo">
	<div class="pc-scheda">
		<h2>Indicizzazione</h2>
		<label class="pc-inline">
			<input type="checkbox" name="indicizza" value="1" <?php checked_pc( '1' === (string) $imp['indicizza'] ); ?>>
			Permetti a Google di indicizzare il portale
		</label>
		<p class="pc-nota">Se togli la spunta, robots.txt blocca tutto e ogni pagina riceve <code>noindex</code>. Usalo solo finché stai lavorando.</p>
		<label style="margin-top:16px">Suffisso dei titoli
			<input type="text" name="seo_suffisso" value="<?php echo e( $imp['seo_suffisso'] ); ?>" placeholder="<?php echo e( $imp['brand'] ); ?>">
			<small>Compare dopo la barra verticale: "Duplicazione chiavi a Trapani | <strong>suffisso</strong>".</small>
		</label>
	</div>

	<div class="pc-scheda">
		<h2>Google Analytics</h2>
		<label>ID di misurazione
			<input type="text" name="ga_id" value="<?php echo e( $imp['ga_id'] ); ?>" placeholder="G-XXXXXXXXXX">
			<small>Lascia vuoto per non caricare nessuno script di tracciamento.</small>
		</label>
	</div>
</div>

<div class="pc-pannello" id="i-ai">
	<div class="pc-scheda">
		<h2>Google Gemini</h2>
		<p class="pc-scheda__nota">Con una chiave attiva compaiono i pulsanti "scrivi con l'assistente" nell'editor delle pagine.</p>
		<label>Chiave API
			<input type="password" name="gemini_key" value="<?php echo e( $imp['gemini_key'] ); ?>" autocomplete="new-password">
			<small>Si ottiene da Google AI Studio. Resta sul tuo server, nel database.</small>
		</label>
		<label>Modello
			<input type="text" name="gemini_modello" id="campo-modello" list="elenco-modelli"
				value="<?php echo e( $imp['gemini_modello'] ); ?>" placeholder="gemini-3.6-flash" spellcheck="false">
			<datalist id="elenco-modelli"></datalist>
			<small>
				Google ritira i modelli senza preavviso: il campo è libero, così puoi sempre
				scrivere quello nuovo senza toccare il codice.
			</small>
		</label>

		<label>Modello per le immagini
			<input type="text" name="gemini_modello_immagini" id="campo-modello-immagini" list="elenco-modelli-immagini"
				value="<?php echo e( $imp['gemini_modello_immagini'] ); ?>" placeholder="gemini-3.1-flash-image" spellcheck="false">
			<datalist id="elenco-modelli-immagini"></datalist>
			<small>Serve un modello che sappia disegnare: di solito ha "image" nel nome.</small>
		</label>

		<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo" id="carica-modelli"
			data-token="<?php echo e( token() ); ?>">Verifica la chiave e carica i modelli</button>
		<p class="pc-nota" id="esito-modelli" style="margin-top:10px"></p>
		<p class="pc-nota">
			<strong>Da sapere.</strong> Il testo generato va sempre riletto e corretto: il modello non conosce la tua
			attività e inventa volentieri dettagli. Pubblicare testi AI non rivisti, uguali in venti città, è il modo
			più rapido per farsi ignorare da Google.
		</p>
	</div>
</div>

<div class="pc-pannello" id="i-sistema">
	<div class="pc-scheda">
		<h2>Il tuo accesso</h2>
		<?php $io = utente_corrente(); ?>
		<p class="pc-scheda__nota">Sei collegato come <strong><?php echo e( $io ? $io['nome'] : '' ); ?></strong>.</p>
		<label>Nuova password
			<input type="password" name="password_nuova" autocomplete="new-password" minlength="8">
			<small>Cambia solo la tua. Lascia vuoto per non cambiarla. Minimo 8 caratteri.</small>
		</label>
		<p class="pc-scheda__nota" style="margin-bottom:0">
			Gli altri accessi si gestiscono da <a href="admin.php?p=utenti">Utenti</a>.
		</p>
	</div>

	<div class="pc-scheda">
		<h2>Stato del sistema</h2>
		<table class="pc-tabella">
			<tbody>
				<tr><td>Versione</td><td><?php echo e( PC_VERSIONE ); ?></td></tr>
				<tr><td>PHP</td><td><?php echo e( PHP_VERSION ); ?></td></tr>
				<tr><td>Database</td><td><?php echo e( $cfg['driver'] ); ?><?php echo 'sqlite' === $cfg['driver'] ? '' : ' · ' . e( $cfg['nome'] ); ?></td></tr>
				<tr><td>Prefisso tabelle</td><td><code><?php echo e( $cfg['prefisso'] ); ?></code></td></tr>
				<tr><td>Cartella media scrivibile</td><td><?php echo is_writable( PC_MEDIA ) ? '✓ sì' : '✗ no — dai il permesso 755'; ?></td></tr>
				<tr><td>Estensione GD (ridimensionamento)</td><td><?php echo function_exists( 'imagecreatetruecolor' ) ? '✓ attiva' : '✗ assente'; ?></td></tr>
				<tr><td>cURL (assistente)</td><td><?php echo function_exists( 'curl_init' ) ? '✓ attiva' : '✗ assente'; ?></td></tr>
				<tr><td>install.php</td><td><?php echo is_file( PC_RADICE . '/install.php' ) ? '⚠ presente — cancellalo dal server' : '✓ rimosso'; ?></td></tr>
				<?php if ( 'sqlite' === $cfg['driver'] ) : ?>
					<tr>
						<td>Database SQLite protetto</td>
						<td>
							<?php echo is_file( PC_RADICE . '/dati/.htaccess' ) ? '✓ .htaccess presente' : '⚠ manca dati/.htaccess'; ?>
							<br><small class="pc-nota">Su nginx .htaccess non vale: chiedi all'hosting di negare <code>/dati/</code>.</small>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="pc-salva">
	<p class="pc-salva__nota">Le modifiche ai colori si vedono subito sulle pagine pubbliche.</p>
	<button class="pc-btn" type="submit">Salva le impostazioni</button>
</div>
</form>
