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
		'stile_tema'      => 'classico' === ( $_POST['stile_tema'] ?? '' ) ? 'classico' : 'vetrina',
		'larghezza'       => trim( (string) ( $_POST['larghezza'] ?? '1440px' ) ),
		'logo_altezza'    => (string) max( 24, min( 140, (int) ( $_POST['logo_altezza'] ?? 56 ) ) ),
		// Il taglio vero lo fa dimensione_menu() in lettura: qui si tiene
		// solo il numero scritto, virgola compresa.
		'menu_dimensione' => trim( (string) ( $_POST['menu_dimensione'] ?? '12.5' ) ),
		'cartella_nascosta' => isset( $_POST['cartella_nascosta'] ) ? '1' : '0',
		'telefono_etichetta' => trim( (string) ( $_POST['telefono_etichetta'] ?? '' ) ),
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
		'ia_consenti'     => isset( $_POST['ia_consenti'] ) ? '1' : '0',
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
				<?php if ( '' !== trim( cartella_reale(), '/' ) ) : ?>
					<label class="pc-inline" style="margin-top:12px">
						<input type="checkbox" name="cartella_nascosta" value="1"<?php echo '1' === (string) $imp['cartella_nascosta'] ? ' checked' : ''; ?>>
						Nascondi la cartella <code><?php echo e( cartella_reale() ); ?></code> dagli indirizzi
					</label>
					<small>
						Da spuntare <strong>solo dopo</strong> aver messo le regole nel
						<code>.htaccess</code> della radice: le trovi già scritte in
						<a href="admin.php?p=seo">SEO e sitemap</a>, con la prova che funzionino.
						Poi qui sopra togli la cartella dall'indirizzo.
					</small>
				<?php endif; ?>

				<?php $sbagliata = cartella_incoerente(); ?>
				<?php if ( '' !== $sbagliata ) : ?>
					<small class="pc-allarme">
						⚠ Qui c'è la cartella <code><?php echo e( $sbagliata ); ?></code>, ma il portale
						gira da <code><?php echo e( '' === cartella_reale() ? '/' : cartella_reale() ); ?></code>.
						Questo campo dice <strong>dove i file stanno</strong>, non dove vorresti che fossero:
						cambiarlo non sposta niente, manda solo tutti i collegamenti su indirizzi che
						rispondono 404.
					</small>
				<?php endif; ?>
		</label>
		<label>Indirizzo del sito principale
			<input type="url" name="sito_principale" value="<?php echo e( $imp['sito_principale'] ); ?>" placeholder="https://www.tuosito.it">
			<small>
				Il logo in alto porta qui, su <strong>tutte</strong> le pagine del portale.
				<?php if ( vuoto( $imp['sito_principale'] ) ) : ?>
					Vuoto: si usa la radice del dominio, cioè
					<code><?php echo e( url_sito_principale() ); ?></code>.
				<?php endif; ?>
				Nel piè di pagina compare anche il collegamento «Torna al sito principale»,
				ma solo se lo scrivi qui.
			</small>
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
		<h2>Impianto della pagina</h2>
		<p class="pc-scheda__nota">Come sono messi intestazione, contenuto e piè di pagina.</p>

		<label>Stile grafico
			<select name="stile_tema">
				<option value="vetrina" <?php selected_pc( 'vetrina', $imp['stile_tema'] ); ?>>Vetrina — intestazione a tutta larghezza, testo al centro</option>
				<option value="classico" <?php selected_pc( 'classico', $imp['stile_tema'] ); ?>>Classico — intestazione a scheda, testo a sinistra</option>
			</select>
			<small>
				In <strong>Vetrina</strong> l'intestazione va da bordo a bordo con l'immagine dietro,
				titolo e pulsanti al centro, il telefono in barra con l'etichetta. In
				<strong>Classico</strong> resta la scheda arrotondata di prima.
			</small>
		</label>

		<label style="max-width:260px">Larghezza del contenuto
			<select name="larghezza">
				<option value="1240px" <?php selected_pc( '1240px', $imp['larghezza'] ); ?>>1240 px — stretta</option>
				<option value="1440px" <?php selected_pc( '1440px', $imp['larghezza'] ); ?>>1440 px — consigliata</option>
				<option value="1600px" <?php selected_pc( '1600px', $imp['larghezza'] ); ?>>1600 px — larga</option>
				<option value="1800px" <?php selected_pc( '1800px', $imp['larghezza'] ); ?>>1800 px — molto larga</option>
			</select>
			<small>La misura che usano header, contenuto e piè di pagina: sono sempre allineati fra loro.</small>
		</label>

		<label style="max-width:260px">Altezza del logo in barra
			<input type="number" name="logo_altezza" value="<?php echo e( $imp['logo_altezza'] ); ?>"
				min="24" max="140" step="2"> px
			<small>Da 24 a 140. Sullo schermo del telefono si rimpicciolisce da solo.</small>
		</label>

		<label style="max-width:260px">Testo del menu
			<input type="number" name="menu_dimensione" value="<?php echo e( dimensione_menu() ); ?>"
				min="10" max="22" step="0.5"> px
			<small>Da 10 a 22. Il pulsante della città e il menu del telefono si muovono insieme, così la barra resta in proporzione. Di serie: 12,5.</small>
		</label>

		<label style="max-width:320px">Etichetta sopra il telefono in barra
			<input type="text" name="telefono_etichetta" value="<?php echo e( $imp['telefono_etichetta'] ); ?>" placeholder="Assistenza 24h">
			<small>Solo nello stile Vetrina. Vuota: si vede il numero da solo.</small>
		</label>
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

		<label class="pc-inline" style="margin-top:14px">
			<input type="checkbox" name="ia_consenti" value="1" <?php checked_pc( '0' !== (string) $imp['ia_consenti'] ); ?>>
			Permetti agli assistenti IA di leggere il portale
		</label>
		<p class="pc-nota">
			ChatGPT, Gemini, Perplexity, Claude e gli altri. Con la spunta vengono
			nominati uno per uno nel <code>robots.txt</code> ed esce
			<code>/llms.txt</code>, l'indice del portale scritto per loro.
			Senza, il portale gli dice di stare alla larga: lo si fa solo se non si
			vuole essere citati nelle risposte generate.
		</p>
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
