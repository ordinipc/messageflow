<?php
/**
 * Pannello delle impostazioni.
 *
 * @package SeoGeoAudit
 * @var array  $cfg      Configurazione effettiva.
 * @var string $salvato  Messaggio di esito.
 * @var string $errore   Messaggio di errore.
 */

$a   = $cfg['azienda'];
$ind = $a['indirizzo'];
$au  = $cfg['autori'][0] ?? array();
$ai  = $cfg['ai'];
$wp  = $cfg['wordpress'] ?? array( 'url' => '', 'token' => '' );

/**
 * Campo di testo con etichetta e nota.
 *
 * @param string $nome      Nome del campo.
 * @param string $etichetta Etichetta.
 * @param string $valore    Valore corrente.
 * @param string $nota      Testo di aiuto.
 * @param string $tipo      Tipo di input.
 * @return void
 */
function campo( $nome, $etichetta, $valore, $nota = '', $tipo = 'text' ) {
	$vuoto = 0 === stripos( (string) $valore, 'DA_COMPILARE' );
	?>
	<div class="campo <?php echo $vuoto ? 'mancante' : ''; ?>">
		<label for="<?php echo e( $nome ); ?>"><?php echo e( $etichetta ); ?></label>
		<input type="<?php echo e( $tipo ); ?>" id="<?php echo e( $nome ); ?>" name="<?php echo e( $nome ); ?>"
			value="<?php echo $vuoto ? '' : e( $valore ); ?>"
			<?php echo $vuoto ? 'placeholder="da compilare"' : ''; ?>>
		<?php if ( $nota ) : ?><small><?php echo $nota; ?></small><?php endif; ?>
	</div>
	<?php
}

?>
<section class="intestazione">
	<h1>Impostazioni</h1>
	<p class="guida">Quello che salvi qui finisce in <code>storage/impostazioni.json</code> e si sovrappone a <code>config.php</code>: non devi più modificare file via FTP, e un aggiornamento del programma non cancella i tuoi dati.</p>
</section>

<?php if ( $salvato ) : ?><p class="avviso ok-bg"><?php echo e( $salvato ); ?></p><?php endif; ?>
<?php if ( $errore ) : ?><p class="avviso grave"><?php echo e( $errore ); ?></p><?php endif; ?>

<form method="post" action="?p=salva-impostazioni">
	<input type="hidden" name="token" value="<?php echo e( token() ); ?>">

	<section class="scheda">
		<h2>Intelligenza artificiale</h2>
		<p class="guida">Serve solo al modulo di riscrittura e alla generazione delle immagini. Tutto il resto dell'analisi funziona senza.</p>

		<div class="griglia">
			<div class="campo">
				<label for="ai_chiave">Chiave API Google Gemini</label>
				<input type="password" id="ai_chiave" name="ai_chiave" autocomplete="off"
					placeholder="<?php echo $mascherata ? e( $mascherata ) . ' (lascia vuoto per non cambiarla)' : 'incolla qui la chiave'; ?>">
				<small>Si crea gratis su <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</a>. Viene salvata con permessi 600 in una cartella non raggiungibile dal web.</small>
			</div>

			<?php campo( 'ai_modello', 'Modello per i testi', $ai['modello'], 'Consigliato <code>gemini-2.5-flash</code>: rapido ed economico. Per testi più curati <code>gemini-2.5-pro</code>.' ); ?>
			<?php campo( 'ai_modello_immagini', 'Modello per le immagini', $ai['modello_immagini'] ?? 'gemini-2.5-flash-image', 'Richiede un progetto Google con fatturazione attiva.' ); ?>
			<?php campo( 'ai_articoli_per_volta', 'Articoli per lotto', $ai['articoli_per_volta'], 'Da 3 a 5 su hosting condiviso, per non superare il tempo massimo di esecuzione.', 'number' ); ?>
			<?php campo( 'ai_prezzo_input', 'Prezzo per milione di token in ingresso (€)', $ai['prezzo_per_milione']['input'], 'Serve solo alla stima mostrata prima di generare.' ); ?>
			<?php campo( 'ai_prezzo_output', 'Prezzo per milione di token in uscita (€)', $ai['prezzo_per_milione']['output'] ); ?>
		</div>
	</section>

	<section class="scheda">
		<h2>Collegamento al sito WordPress</h2>
		<p class="guida">Con questi due dati il programma applica le correzioni direttamente sul sito, senza copiare e incollare. Il token si genera nel plugin, alla voce di menu <strong>SEO &amp; GEO</strong>.</p>

		<div class="griglia">
			<?php campo( 'wp_url', 'Indirizzo del sito', $wp['url'] ?? '', 'Per esempio <code>https://maxdigitalinnovation.it</code>' ); ?>
			<div class="campo">
				<label for="wp_token">Token del plugin</label>
				<input type="password" id="wp_token" name="wp_token" autocomplete="off"
					placeholder="<?php echo $token_wp_mascherato ? e( $token_wp_mascherato ) . ' (lascia vuoto per non cambiarlo)' : 'incolla il token generato dal plugin'; ?>">
				<small>WordPress → SEO &amp; GEO → Collegamento con il gestionale → Genera token.</small>
			</div>
		</div>
	</section>

	<section class="scheda">
		<h2>Dati aziendali</h2>
		<p class="guida">Alimentano lo schema LocalBusiness, il footer, la pagina Contatti e llms.txt. I campi vuoti non vengono mai stampati con dati finti: restano semplicemente assenti, e il posizionamento locale ne risente.</p>

		<div class="griglia">
			<?php campo( 'az_nome', 'Nome commerciale', $a['nome'] ); ?>
			<?php campo( 'az_ragione', 'Ragione sociale', $a['nomeLegale'] ); ?>
			<?php campo( 'az_piva', 'Partita IVA', $a['partitaIva'], '11 cifre. Obbligatoria per legge sui siti aziendali italiani.' ); ?>
			<?php campo( 'az_fondazione', 'Anno di fondazione', $a['fondazione'] ); ?>
			<?php campo( 'az_telefono', 'Telefono fisso', $a['telefono'], 'Formato internazionale: <code>+39 091 1234567</code>' ); ?>
			<?php campo( 'az_cellulare', 'Cellulare', $a['cellulare'] ); ?>
			<?php campo( 'az_email', 'Email', $a['email'], '', 'email' ); ?>
			<?php campo( 'az_whatsapp', 'Numero WhatsApp', $a['whatsapp'], 'Solo cifre con prefisso: <code>393331234567</code>' ); ?>
			<?php campo( 'az_via', 'Via e numero civico', $ind['via'] ); ?>
			<?php campo( 'az_cap', 'CAP', $ind['cap'] ); ?>
			<?php campo( 'az_citta', 'Città', $ind['citta'] ); ?>
			<?php campo( 'az_provincia', 'Provincia', $ind['provincia'], 'Sigla di due lettere.' ); ?>
			<?php campo( 'az_lat', 'Latitudine', $ind['latitudine'], 'Da Google Maps: clic destro sulla sede → il primo valore.' ); ?>
			<?php campo( 'az_lng', 'Longitudine', $ind['longitudine'] ); ?>
			<?php campo( 'az_gbp', 'Scheda Google Business', $a['profili']['googleBusiness'], 'Il link della scheda: rafforza il collegamento fra sito ed entità.' ); ?>
			<?php campo( 'az_instagram', 'Instagram', $a['profili']['instagram'] ); ?>
			<?php campo( 'az_facebook', 'Facebook', $a['profili']['facebook'] ); ?>
			<?php campo( 'az_linkedin', 'LinkedIn', $a['profili']['linkedin'] ); ?>
		</div>
	</section>

	<section class="scheda">
		<h2>Autore dei contenuti</h2>
		<p class="guida">Un contenuto firmato da una persona reale con biografia verificabile vale più di uno firmato da un login. Alimenta lo schema Person.</p>

		<div class="griglia">
			<?php campo( 'au_nome', 'Nome', $au['nome'] ?? '' ); ?>
			<?php campo( 'au_cognome', 'Cognome', $au['cognome'] ?? '' ); ?>
			<?php campo( 'au_ruolo', 'Ruolo', $au['ruolo'] ?? '', 'Per esempio: Founder e Digital Strategist' ); ?>
			<?php campo( 'au_linkedin', 'Profilo LinkedIn', $au['linkedin'] ?? '' ); ?>
		</div>

		<div class="campo">
			<label for="au_bio">Biografia</label>
			<textarea id="au_bio" name="au_bio" rows="3"><?php echo 0 === stripos( (string) ( $au['bio'] ?? '' ), 'DA_COMPILARE' ) ? '' : e( $au['bio'] ?? '' ); ?></textarea>
			<small>Due o tre frasi con anni di esperienza e specializzazione.</small>
		</div>
	</section>

	<section class="scheda" id="search-console">
		<h2>Google Search Console</h2>
		<p class="guida">
			È la sola fonte di verità su come va il sito nelle ricerche. Collegata, il programma smette
			di lavorare a occhio: sa quali pagine sono a un passo dalla prima pagina, quali vengono
			viste ma non cliccate, e quali Google non mostra mai.
			<?php if ( $google_configurato ) : ?>
				<span class="tag ok">collegata</span>
			<?php endif; ?>
		</p>

		<details<?php echo $google_configurato ? '' : ' open'; ?>>
			<summary>Come si ottiene la chiave (cinque minuti, gratis)</summary>
			<ol class="guida">
				<li>Vai su <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">console.cloud.google.com</a> e crea un progetto (un nome qualsiasi).</li>
				<li>Nel menù: <strong>API e servizi → Libreria</strong>, cerca <strong>Google Search Console API</strong> e premi <strong>Abilita</strong>.</li>
				<li>Sempre nel menù: <strong>IAM e amministrazione → Account di servizio → Crea account di servizio</strong>. Nome a piacere, nessun ruolo da assegnare.</li>
				<li>Apri l account appena creato → scheda <strong>Chiavi</strong> → <strong>Aggiungi chiave → Crea nuova chiave → JSON</strong>. Si scarica un file.</li>
				<li>Apri quel file con un editor di testo, copia <strong>tutto</strong> il contenuto e incollalo qui sotto.</li>
				<li>Ultimo passo, quello che si dimenticano tutti: in <a href="https://search.google.com/search-console/users" target="_blank" rel="noopener">Search Console → Impostazioni → Utenti e autorizzazioni</a> aggiungi come utente l indirizzo dell account di servizio (finisce per <code>.iam.gserviceaccount.com</code>), con permesso <strong>Con limitazioni</strong>: basta e avanza, legge soltanto.</li>
			</ol>
			<p class="nota">
				La chiave resta sul tuo server, in <code>storage/impostazioni.json</code>, leggibile solo dal proprietario.
				Dà accesso in sola lettura ai dati di Search Console: non può modificare né il sito né l account Google.
			</p>
		</details>

		<div class="campo">
			<label for="g_chiave_json">Chiave dell account di servizio (contenuto del file JSON)</label>
			<textarea id="g_chiave_json" name="g_chiave_json" rows="4" placeholder="<?php echo $google_configurato ? 'Chiave già salvata: lascia vuoto per non cambiarla' : '{ &quot;type&quot;: &quot;service_account&quot;, ... }'; ?>"></textarea>
			<small>
				<?php if ( $google_account ) : ?>
					Salvata. Account: <code><?php echo e( $google_account ); ?></code> — è questo l indirizzo da autorizzare in Search Console.
				<?php else : ?>
					Incolla tutto il file, graffe comprese.
				<?php endif; ?>
			</small>
		</div>

		<div class="griglia">
			<?php campo( 'g_proprieta', 'Proprietà di Search Console', $cfg['google']['proprieta'] ?? '', 'Come compare lì: sc-domain:tuosito.it oppure https://tuosito.it/' ); ?>
			<?php campo( 'g_giorni', 'Giorni da analizzare', $cfg['google']['giorni'] ?? 28, 'Da 7 a 180. Con 28 si confronta con i 28 precedenti.', 'number' ); ?>
			<?php campo( 'g_min_impression', 'Impression minime perché un dato conti', $cfg['google']['min_impression'] ?? 20, 'Sotto questa soglia i numeri sono rumore.', 'number' ); ?>
		</div>

		<?php if ( $google_configurato ) : ?>
			<p class="nota">Salva prima le modifiche, poi prova il collegamento: la prova usa quello che c è salvato.</p>
		<?php endif; ?>
	</section>

	<section class="scheda">
		<h2>Parametri SEO</h2>
		<div class="griglia">
			<?php campo( 'seo_brand', 'Suffisso del brand nei title', $cfg['seo']['brandSuffix'] ); ?>
			<?php campo( 'seo_citta', 'Città principale', $cfg['seo']['cittaPrincipale'] ); ?>
			<?php campo( 'seo_link', 'Link interni per articolo', $cfg['seo']['linkInterniPerArticolo'], '', 'number' ); ?>
			<?php campo( 'seo_soglia', 'Soglia qualità per "da mantenere"', $cfg['seo']['sogliaQualita'], 'Da 0 a 100. Più alta, più articoli finiscono fra quelli da riscrivere.', 'number' ); ?>
		</div>

		<label class="scelta">
			<input type="checkbox" name="seo_link_pagine" value="1" <?php echo ! empty( $cfg['seo']['linkAutomaticiNellePagine'] ) ? 'checked' : ''; ?>>
			<span>
				<strong>Inserisci i link interni automatici anche nelle pagine</strong>
				<small>
					Spento, il plugin aggiunge link automatici solo negli articoli e lascia intatto il testo
					delle pagine servizio. È l'unica cosa che il plugin cambia dentro il contenuto di una
					pagina: dati strutturati, meta robots e alt delle immagini restano attivi ovunque, ma
					non toccano quello che hai scritto.
				</small>
			</span>
		</label>
	</section>

	<p><button class="bottone" type="submit">Salva impostazioni</button></p>
</form>

<?php if ( $google_configurato ) : ?>
<section class="scheda">
	<h2>Prova il collegamento con Search Console</h2>
	<p class="guida">
		Controlla che la chiave sia valida e che l account veda davvero la proprietà indicata.
		Se qualcosa non torna, il messaggio dice quale dei due passaggi manca.
	</p>
	<form method="post" action="?p=prova-google">
		<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
		<button class="bottone chiaro" type="submit">Prova il collegamento</button>
	</form>
</section>
<?php endif; ?>

<section class="scheda">
	<h2>Avviare l'analisi da un pulsante</h2>
	<p class="guida">
		<strong>Nella bacheca di WordPress il pulsante c'è già.</strong> Dalla versione 1.3.0 del plugin,
		in <em>SEO &amp; GEO → Analizza adesso</em>: appare da solo appena salvi qui le impostazioni,
		perché insieme ai dati aziendali il gestionale gli manda anche l'indirizzo qui sotto.
		Il resto di questa scheda serve solo se vuoi il pulsante anche altrove: in un tuo gestionale,
		in uno script, in una pagina tua.
	</p>

	<div class="campo">
		<label for="url-analisi">Indirizzo da chiamare</label>
		<input id="url-analisi" type="text" readonly value="<?php echo e( $indirizzo_base ); ?>/index.php?p=api-analizza&amp;token=<?php echo e( $token_esterno ); ?>">
		<small>Il token vale come una password: chi ha questo indirizzo può far partire un'analisi. Usalo sempre su <code>https</code>.</small>
	</div>

	<p class="guida">Risposta tipo:</p>
	<pre class="mono">{"ok":true,"audit":7,"punteggio":52,"variazione":18,"problemi":2140,
 "articoli":311,"pagine":15,"scheda":"…/index.php?p=audit&amp;id=7"}</pre>

	<p class="guida">Codice pronto, se preferisci un tuo pulsante nel <code>functions.php</code> o in uno snippet:</p>
	<pre class="mono"><?php echo e( $snippet ); ?></pre>

	<p class="nota">
		L'analisi rilegge il sito e ricalcola il punteggio: non modifica nulla.
		Un'analisi ogni due minuti al massimo: le richieste più ravvicinate ricevono un rifiuto,
		così un doppio clic non fa partire due letture insieme.
	</p>

	<form method="post" action="?p=rigenera-token" onsubmit="return confirm('Rigenerare il token? Il vecchio indirizzo smetterà di funzionare.')">
		<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
		<button class="bottone chiaro" type="submit">Rigenera il token</button>
	</form>
</section>
