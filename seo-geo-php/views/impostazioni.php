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
