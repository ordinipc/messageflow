<?php
/**
 * Collaudo del plugin: esegue le classi reali sulle funzioni simulate.
 *
 * Uso: php test/plugin.php
 *
 * @package SeoGeoAudit
 */

require_once __DIR__ . '/wp-stub.php';

$errori = 0;

/**
 * Verifica un asserto e stampa l esito.
 *
 * @param string $descrizione Cosa si sta verificando.
 * @param bool   $condizione  Esito.
 * @param string $dettaglio   Informazione aggiuntiva in caso di errore.
 * @return void
 */
function verifica( $descrizione, $condizione, $dettaglio = '' ) {
	global $errori;

	if ( $condizione ) {
		echo "  ✔ $descrizione\n";
		return;
	}

	$errori++;
	echo "  ✖ $descrizione" . ( $dettaglio ? " — $dettaglio" : '' ) . "\n";
}

echo "\n▶ Collaudo del plugin MDI SEO & GEO Booster\n\n";

// Avvio come fa WordPress: senza questo nessuna classe aggancia i propri hook,
// e tutta la parte che reagisce agli eventi del sito resterebbe non collaudata.
foreach ( (array) ( $GLOBALS['wp']['azioni']['plugins_loaded'] ?? array() ) as $callback ) {
	call_user_func( $callback );
}

verifica( 'il plugin aggancia i suoi hook all avvio', ! empty( $GLOBALS['wp']['azioni']['save_post'] ) );

// Dati di partenza.
stub_crea_post( 10, 'Articolo originale', '<h2>Sezione</h2><p>Testo.</p>' );
stub_crea_post( 11, 'Secondo articolo' );
update_post_meta( 10, 'rank_math_title', 'Vecchio title' );
update_post_meta( 10, 'rank_math_description', 'Vecchia description' );

// --- Token e autorizzazione ------------------------------------------------
echo "Token e autorizzazione\n";

$token = MDI_Api::token();
verifica( 'il token viene generato', 48 === strlen( $token ), "lunghezza: " . strlen( $token ) );
verifica( 'il token resta stabile fra due letture', $token === MDI_Api::token() );
verifica( 'con --rigenera cambia', $token !== MDI_Api::token( true ) );

$token = MDI_Api::token();
verifica( 'richiesta senza token respinta', is_wp_error( MDI_Api::autorizza( new WP_REST_Request() ) ) );
verifica( 'richiesta con token errato respinta', is_wp_error( MDI_Api::autorizza( new WP_REST_Request( array(), array( 'x-mdi-token' => 'sbagliato' ) ) ) ) );
verifica( 'richiesta con token giusto accettata', true === MDI_Api::autorizza( new WP_REST_Request( array(), array( 'x-mdi-token' => $token ) ) ) );

// --- Dati aziendali ricevuti dal gestionale --------------------------------
echo "\nDati aziendali dal gestionale\n";

verifica( 'prima del collegamento il telefono non risulta', '' === mdi_seo_geo_cfg( 'azienda.telefono' ) );

$configurazione = array(
	'azienda' => array(
		'nome'       => 'Max Digital Innovation',
		'telefono'   => '+39 091 1234567',
		'partitaIva' => '01234567890',
		'indirizzo'  => array( 'via' => 'Via Roma 100', 'cap' => '90133', 'citta' => 'Palermo', 'provincia' => 'PA', 'nazione' => 'IT' ),
		'profili'    => array( 'googleBusiness' => 'https://g.page/maxdigital' ),
	),
	'seo'     => array( 'cittaPrincipale' => 'Palermo' ),
);

$esito = MDI_Api::salva_config( new WP_REST_Request( array( 'config' => $configurazione ) ) );

verifica( 'la configurazione viene accettata', ! is_wp_error( $esito ) && ! empty( $esito['ok'] ) );
verifica( 'il telefono arriva al plugin', '+39 091 1234567' === mdi_seo_geo_cfg( 'azienda.telefono' ) );
verifica( 'la partita IVA arriva al plugin', '01234567890' === mdi_seo_geo_cfg( 'azienda.partitaIva' ) );
verifica( 'l indirizzo arriva al plugin', 'Via Roma 100' === mdi_seo_geo_cfg( 'azienda.indirizzo.via' ) );
verifica( 'il plugin segnala cosa è stato compilato', in_array( 'telefono', $esito['compilati'], true ) && empty( $esito['mancanti'] ) );

$organizzazione = MDI_Schema::organization();
verifica( 'lo schema LocalBusiness usa il telefono ricevuto', '+39 091 1234567' === ( $organizzazione['telephone'] ?? '' ) );
verifica( 'lo schema riporta la partita IVA', '01234567890' === ( $organizzazione['vatID'] ?? '' ) );
verifica( 'lo schema contiene l indirizzo postale', 'Via Roma 100' === ( $organizzazione['address']['streetAddress'] ?? '' ) );
verifica( 'lo schema è di tipo ProfessionalService', in_array( 'ProfessionalService', (array) $organizzazione['@type'], true ) );

$vuota = MDI_Api::salva_config( new WP_REST_Request( array( 'config' => 'non un array' ) ) );
verifica( 'una configurazione malformata viene rifiutata', is_wp_error( $vuota ) );

$stato_sito = MDI_Api::stato();
verifica( 'lo stato dichiara che i dati aziendali sono arrivati', ! empty( $stato_sito['config'] ) && ! empty( $stato_sito['telefono'] ) );

// --- Link automatici: articoli sì, pagine no -------------------------------
echo "\nLink automatici nel contenuto\n";

update_option(
	MDI_Api::OPZIONE_CONFIG,
	array(
		'azienda' => array( 'nome' => 'Max Digital Innovation' ),
		'seo'     => array(
			'linkInterniPerArticolo'    => 4,
			'linkAutomaticiNellePagine' => false,
		),
	),
	false
);

// La mappa keyword → URL vive in data/internal-links.json: per il collaudo si
// scrive davvero, così viene esercitato anche il caricamento del file.
$file_link = MDI_SEO_GEO_DIR . 'data/internal-links.json';
file_put_contents( $file_link, wp_json_encode( array( 'siti web a palermo' => 'https://esempio.it/realizzazione-siti-web-a-palermo/' ) ) );

$testo = '<p>Realizziamo siti web a Palermo per le imprese del territorio.</p>';

$GLOBALS['wp']['singolo']      = 10;
$GLOBALS['wp']['tipo_singolo'] = 'page';
$su_pagina = MDI_Links::auto_internal_links( $testo );

$GLOBALS['wp']['tipo_singolo'] = 'post';
$su_articolo = MDI_Links::auto_internal_links( $testo );

verifica( 'nelle pagine il testo resta identico', $testo === $su_pagina );
verifica( 'negli articoli il link viene inserito', false !== strpos( $su_articolo, 'realizzazione-siti-web-a-palermo' ) );

// Con l interruttore acceso le pagine tornano a riceverli.
$configurazione_link = get_option( MDI_Api::OPZIONE_CONFIG );
$configurazione_link['seo']['linkAutomaticiNellePagine'] = true;
update_option( MDI_Api::OPZIONE_CONFIG, $configurazione_link, false );

$GLOBALS['wp']['tipo_singolo'] = 'page';
verifica( 'con l interruttore acceso anche le pagine ricevono i link', false !== strpos( MDI_Links::auto_internal_links( $testo ), 'realizzazione-siti-web-a-palermo' ) );

$GLOBALS['wp']['singolo'] = 0;
unlink( $file_link );

// --- Anteprima delle meta --------------------------------------------------
echo "\nAnteprima delle meta\n";

$richiesta = new WP_REST_Request(
	array(
		'anteprima' => true,
		'contenuti' => array(
			array( 'id' => 10, 'title' => 'Title nuovo', 'description' => 'Description nuova', 'excerpt' => 'Estratto nuovo', 'focus' => 'keyword' ),
		),
	)
);

$esito = MDI_Api::aggiorna_meta( $richiesta );

verifica( 'l anteprima non scrive nulla', 'Vecchio title' === get_post_meta( 10, 'rank_math_title', true ) );
verifica( 'l anteprima restituisce il confronto', ! empty( $esito['dettaglio'] ), 'dettaglio vuoto: è il bug che nascondeva la pagina di anteprima' );
verifica( 'il confronto contiene il valore precedente', 'Vecchio title' === ( $esito['dettaglio'][0]['prima']['rank_math_title'] ?? '' ) );
verifica( 'il confronto contiene il valore nuovo', 'Title nuovo' === ( $esito['dettaglio'][0]['dopo']['rank_math_title'] ?? '' ) );
verifica( 'il confronto porta il titolo dell articolo', 'Articolo originale' === ( $esito['dettaglio'][0]['titolo'] ?? '' ) );
verifica( 'in anteprima il conteggio degli aggiornati resta a zero', 0 === $esito['aggiornati'] );

// --- Scrittura e ripristino ------------------------------------------------
echo "\nScrittura delle meta e ripristino\n";

$richiesta = new WP_REST_Request(
	array(
		'contenuti' => array(
			array( 'id' => 10, 'title' => 'Title nuovo', 'description' => 'Description nuova', 'excerpt' => 'Estratto nuovo', 'focus' => 'keyword' ),
			array( 'id' => 999, 'title' => 'Inesistente' ),
		),
	)
);

$esito = MDI_Api::aggiorna_meta( $richiesta );

verifica( 'il title viene scritto', 'Title nuovo' === get_post_meta( 10, 'rank_math_title', true ) );
verifica( 'la description viene scritta', 'Description nuova' === get_post_meta( 10, 'rank_math_description', true ) );
verifica( 'l estratto viene scritto', 'Estratto nuovo' === get_post_field( 'post_excerpt', 10 ) );
verifica( 'gli id inesistenti vengono saltati senza errori', 1 === $esito['aggiornati'] && 1 === $esito['saltati'] );
verifica( 'il valore precedente resta conservato', false !== strpos( (string) get_post_meta( 10, MDI_Api::META_BACKUP, true ), 'Vecchio title' ) );

// Una seconda scrittura non deve sovrascrivere il backup originale.
MDI_Api::aggiorna_meta( new WP_REST_Request( array( 'contenuti' => array( array( 'id' => 10, 'title' => 'Title ancora diverso' ) ) ) ) );
verifica( 'il backup non viene sovrascritto dalla seconda scrittura', false !== strpos( (string) get_post_meta( 10, MDI_Api::META_BACKUP, true ), 'Vecchio title' ) );

$esito = MDI_Api::annulla_meta( new WP_REST_Request( array( 'ids' => array( 10 ) ) ) );

verifica( 'il ripristino riporta il title originale', 'Vecchio title' === get_post_meta( 10, 'rank_math_title', true ) );
verifica( 'il ripristino riporta la description originale', 'Vecchia description' === get_post_meta( 10, 'rank_math_description', true ) );
verifica( 'il ripristino conta i contenuti toccati', 1 === $esito['ripristinati'] );
verifica( 'dopo il ripristino il backup viene rimosso', '' === get_post_meta( 10, MDI_Api::META_BACKUP, true ) );

// --- Bozze -----------------------------------------------------------------
echo "\nCreazione delle bozze\n";

$richiesta = new WP_REST_Request(
	array(
		'id'               => 10,
		'titolo'           => 'Versione riscritta',
		'corpo_html'       => '<h2>Nuova sezione</h2><p>Testo riscritto.</p>',
		'in_breve'         => 'Sintesi di apertura.',
		'meta_title'       => 'Title della bozza',
		'meta_description' => 'Description della bozza',
		'faq'              => array( array( 'domanda' => 'Quanto costa?', 'risposta' => 'Dipende.' ) ),
	)
);

$esito    = MDI_Api::crea_bozza( $richiesta );
$id_bozza = $esito['id_bozza'] ?? 0;
$bozza    = get_post( $id_bozza );

verifica( 'la bozza viene creata', $id_bozza > 0 );
verifica( 'la bozza è in stato draft', $bozza && 'draft' === $bozza->post_status );
verifica( 'l articolo pubblicato non viene toccato', '<h2>Sezione</h2><p>Testo.</p>' === get_post( 10 )->post_content );
verifica( 'la bozza è collegata all originale', '10' === (string) get_post_meta( $id_bozza, MDI_Api::META_BOZZA_DI, true ) );
verifica( 'la bozza contiene il blocco di sintesi', false !== strpos( $bozza->post_content, 'mdi-in-breve' ) );
verifica( 'la bozza contiene le FAQ', false !== strpos( $bozza->post_content, 'Quanto costa?' ) );

$secondo = MDI_Api::crea_bozza( $richiesta );
verifica( 'una seconda richiesta aggiorna la bozza invece di duplicarla', $id_bozza === ( $secondo['id_bozza'] ?? 0 ) );

$vuota = MDI_Api::crea_bozza( new WP_REST_Request( array( 'id' => 10, 'corpo_html' => '' ) ) );
verifica( 'una bozza senza corpo viene rifiutata', is_wp_error( $vuota ) );

$assente = MDI_Api::crea_bozza( new WP_REST_Request( array( 'id' => 4242, 'corpo_html' => '<p>x</p>' ) ) );
verifica( 'una bozza su un articolo inesistente viene rifiutata', is_wp_error( $assente ) );

// --- Pubblicazione della bozza nell articolo originale ---------------------
echo "\nPubblicazione della bozza\n";

$prima_del_testo = get_post( 10 )->post_content;
$esito           = MDI_Api::applica_bozza( new WP_REST_Request( array( 'id' => 10 ) ) );

verifica( 'la bozza viene riversata nell articolo originale', ! is_wp_error( $esito ) && false !== strpos( get_post( 10 )->post_content, 'Testo riscritto' ) );
verifica( 'il titolo passa dalla bozza', 'Versione riscritta' === get_post( 10 )->post_title );
verifica( 'le meta della bozza passano all originale', 'Title della bozza' === get_post_meta( 10, 'rank_math_title', true ) );
verifica( 'l articolo originale resta pubblicato', 'publish' === get_post( 10 )->post_status );
verifica( 'la copia in bozza viene rimossa', null === get_post( $id_bozza ) );
verifica( 'l URL non cambia: si aggiorna lo stesso articolo', 10 === (int) $esito['articolo'] );

$senza = MDI_Api::applica_bozza( new WP_REST_Request( array( 'id' => 11 ) ) );
verifica( 'senza bozza collegata l operazione viene rifiutata', is_wp_error( $senza ) );

// --- Cestino ----------------------------------------------------------------
echo "\nCestino\n";

stub_crea_post( 20, 'Da eliminare' );
$esito = MDI_Api::cestina( new WP_REST_Request( array( 'ids' => array( 20, 9999 ) ) ) );

verifica( 'il contenuto finisce nel cestino', 'trash' === get_post( 20 )->post_status );
verifica( 'il contenuto resta recuperabile, non è eliminato', null !== get_post( 20 ) );
verifica( 'gli id inesistenti vengono contati a parte', 1 === $esito['cestinati'] && 1 === $esito['saltati'] );

// --- Categorie -------------------------------------------------------------
echo "\nCategorie\n";

$esito = MDI_Api::assegna_categoria(
	new WP_REST_Request(
		array(
			'assegnazioni' => array(
				array( 'id' => 10, 'categoria' => 'Marketing' ),
				array( 'id' => 11, 'categoria' => 'Marketing' ),
				array( 'id' => 999, 'categoria' => 'Inesistente' ),
			),
		)
	)
);

verifica( 'le categorie vengono assegnate', 2 === $esito['assegnate'] );
verifica( 'la categoria viene creata una volta sola', 1 === count( $GLOBALS['wp']['termini'] ) );
verifica( 'l articolo riceve la categoria', ! empty( wp_get_post_categories( 10 ) ) );

// --- Redirect --------------------------------------------------------------
echo "\nRedirect\n";

$esito = MDI_Api::salva_redirect(
	new WP_REST_Request(
		array(
			'redirect' => array(
				array( 'da' => '/vecchio-articolo', 'a' => 'https://esempio.it/nuovo-articolo/' ),
				array( 'da' => 'https://esempio.it/altro-vecchio/', 'a' => 'https://esempio.it/destinazione/' ),
				array( 'da' => '', 'a' => 'https://esempio.it/x/' ),
			),
		)
	)
);

$tabella = get_option( MDI_Api::OPZIONE_REDIRECT );

verifica( 'i redirect validi vengono salvati', 2 === $esito['redirect'] );
verifica( 'le righe incomplete vengono scartate', 2 === count( $tabella ) );
verifica( 'il percorso viene normalizzato con gli slash', isset( $tabella['/vecchio-articolo/'] ), 'chiavi: ' . implode( ', ', array_keys( $tabella ) ) );
verifica( 'un URL completo viene ridotto a percorso', isset( $tabella['/altro-vecchio/'] ) );

// --- Immagini --------------------------------------------------------------
echo "\nImmagini\n";

$png   = str_repeat( 'dati-immagine', 200 );
$esito = MDI_Api::carica_immagine(
	new WP_REST_Request(
		array(
			'id'          => 10,
			'dati_base64' => base64_encode( $png ),
			'mime'        => 'image/png',
			'nome'        => 'articolo-di-prova',
			'alt'         => 'Testo alternativo',
			'in_evidenza' => true,
		)
	)
);

verifica( 'l immagine viene caricata', ! is_wp_error( $esito ) && ! empty( $esito['allegato'] ) );
verifica( 'viene impostata come immagine in evidenza', (string) $esito['allegato'] === (string) get_post_meta( 10, '_thumbnail_id', true ) );
verifica( 'l alt viene salvato', 'Testo alternativo' === get_post_meta( $esito['allegato'], '_wp_attachment_image_alt', true ) );

$fallita = MDI_Api::carica_immagine( new WP_REST_Request( array( 'id' => 10, 'dati_base64' => base64_encode( 'corto' ), 'mime' => 'image/png' ) ) );
verifica( 'un immagine troppo piccola viene rifiutata', is_wp_error( $fallita ) );

$mime_no = MDI_Api::carica_immagine( new WP_REST_Request( array( 'id' => 10, 'dati_base64' => base64_encode( $png ), 'mime' => 'application/x-php' ) ) );
verifica( 'un formato non ammesso viene rifiutato', is_wp_error( $mime_no ) );

// --- Stato -----------------------------------------------------------------
echo "\nStato\n";

$stato = MDI_Api::stato();
verifica( 'lo stato riporta il numero di articoli', isset( $stato['articoli'] ) && $stato['articoli'] > 0 );
verifica( 'lo stato riporta la versione del plugin', MDI_SEO_GEO_VERSION === ( $stato['plugin'] ?? '' ) );
verifica( 'lo stato conta i redirect attivi', 2 === ( $stato['redirect'] ?? 0 ) );

// --- Lettura del sito per il gestionale ------------------------------------
echo "\nLettura del sito\n";

$conteggi = MDI_Api::conteggi();
verifica( 'i conteggi distinguono articoli e pagine', isset( $conteggi['articoli'], $conteggi['pagine'] ) );
verifica( 'i conteggi riportano gli autori', ! empty( $conteggi['autori'] ) );

$blocco = MDI_Api::contenuti( new WP_REST_Request( array( 'offset' => 0, 'limite' => 5 ) ) );
verifica( 'i contenuti arrivano a blocchi', ! empty( $blocco['contenuti'] ) );
$primo = $blocco['contenuti'][0];
verifica( 'ogni contenuto porta id, titolo e tipo', isset( $primo['wp_id'], $primo['titolo'], $primo['tipo'] ) );
verifica( 'ogni contenuto porta le meta SEO lette dal sito', array_key_exists( 'meta', $primo ) );

$voci = MDI_Api::menu();
verifica( 'il menu di navigazione viene letto', ! empty( $voci['voci'] ) );

// --- Pulsante "Analizza" ---------------------------------------------------
echo "\nPulsante Analizza\n";

delete_option_stub( MDI_Api::OPZIONE_CONFIG );
verifica( 'senza configurazione non c è nessun indirizzo di analisi', '' === MDI_Admin::url_analisi() );

MDI_Api::salva_config(
	new WP_REST_Request(
		array(
			'config' => array(
				'azienda' => array( 'nome' => 'Prova', 'telefono' => '+39 091 000000' ),
				'analisi' => array( 'url' => 'https://gestionale.esempio.it/index.php?p=api-analizza&token=abc' ),
			),
		)
	)
);

verifica(
	'il gestionale comunica l indirizzo di analisi',
	'https://gestionale.esempio.it/index.php?p=api-analizza&token=abc' === MDI_Admin::url_analisi()
);

$stato = MDI_Api::stato();
verifica( 'lo stato dichiara che il pulsante è disponibile', true === ( $stato['analisi'] ?? false ) );

// Un indirizzo non http viene ignorato: il pulsante non deve chiamare a caso.
MDI_Api::salva_config(
	new WP_REST_Request(
		array(
			'config' => array(
				'azienda' => array( 'nome' => 'Prova' ),
				'analisi' => array( 'url' => 'javascript:alert(1)' ),
			),
		)
	)
);
verifica( 'un indirizzo non valido viene scartato', '' === MDI_Admin::url_analisi() );

ob_start();
MDI_Admin::sezione_analisi();
$html = ob_get_clean();
verifica( 'senza indirizzo la pagina spiega cosa fare invece di mostrare il pulsante', false === strpos( $html, 'Analizza adesso' ) );

MDI_Api::salva_config(
	new WP_REST_Request(
		array(
			'config' => array(
				'azienda' => array( 'nome' => 'Prova' ),
				'analisi' => array( 'url' => 'https://gestionale.esempio.it/index.php?p=api-analizza&token=abc' ),
			),
		)
	)
);

ob_start();
MDI_Admin::sezione_analisi();
$html = ob_get_clean();
verifica( 'con indirizzo compare il pulsante', false !== strpos( $html, 'Analizza adesso' ) );
verifica( 'il modulo passa da admin-post con la sua azione', false !== strpos( $html, 'name="action" value="mdi_analizza"' ) );
verifica( 'il modulo è protetto da nonce', false !== strpos( $html, 'mdi_analizza' ) );

$_GET = array( 'analisi' => 'fatta', 'punteggio' => '58', 'variazione' => '7', 'problemi' => '1200', 'scheda' => 'https://gestionale.esempio.it/index.php?p=audit&id=9' );
ob_start();
MDI_Admin::sezione_analisi();
$html = ob_get_clean();
verifica( 'il punteggio tornato dal gestionale viene mostrato', false !== strpos( $html, '58/100' ) );
verifica( 'la variazione viene mostrata col segno', false !== strpos( $html, '+7 rispetto alla volta scorsa' ) );

$_GET = array( 'analisi' => 'fatta', 'punteggio' => '51', 'variazione' => '', 'problemi' => '0', 'scheda' => '' );
ob_start();
MDI_Admin::sezione_analisi();
$html = ob_get_clean();
verifica( 'la prima analisi non inventa una variazione', false !== strpos( $html, 'prima analisi' ) );

$_GET = array( 'analisi' => 'errore', 'messaggio' => 'Token non valido.' );
ob_start();
MDI_Admin::sezione_analisi();
$html = ob_get_clean();
verifica( 'un errore del gestionale viene riportato per intero', false !== strpos( $html, 'Token non valido.' ) );

$_GET = array();

// --- File per i motori generativi ------------------------------------------
// Erano un istantanea congelata dentro lo zip: dopo qualche riscrittura
// raccontavano un sito che non esisteva più.
echo "\nFile per i motori generativi\n";

MDI_AI::svuota_cache();

// Senza dati aziendali non si pubblica una mappa monca: si ripiega sul file
// statico, che almeno è stato scritto da qualcuno.
delete_option_stub( MDI_Api::OPZIONE_CONFIG );
verifica( 'senza dati aziendali non genera niente', '' === MDI_AI::genera( 'llms' ) );

MDI_Api::salva_config(
	new WP_REST_Request(
		array(
			'config' => array(
				'azienda' => array(
					'nome'             => 'Max Digital Innovation',
					'email'            => 'info@esempio.it',
					'telefono'         => '+39 091 000000',
					'partitaIva'       => '01234567890',
					'descrizioneBreve' => 'Web agency a Palermo.',
					'indirizzo'        => array( 'via' => 'Via Roma 1', 'citta' => 'Palermo', 'provincia' => 'PA' ),
				),
			),
		)
	)
);

MDI_AI::svuota_cache();

stub_crea_post( 700, 'Guida alla realizzazione di siti web', str_repeat( 'parola ', 400 ) );
update_post_meta( 700, 'rank_math_description', 'Come si realizza un sito web che porta clienti.' );

$GLOBALS['wp']['post'][701] = (object) array(
	'ID' => 701, 'post_title' => 'Servizi digitali a Palermo', 'post_content' => str_repeat( 'servizio ', 300 ),
	'post_excerpt' => '', 'post_status' => 'publish', 'post_type' => 'page', 'post_author' => 1,
	'post_name' => 'servizi', 'post_date' => '2026-01-01 10:00:00', 'post_date_gmt' => '2026-01-01 09:00:00',
	'post_modified_gmt' => '2026-03-01 09:00:00', 'post_parent' => 0, 'comment_status' => 'closed',
);

$llms = MDI_AI::genera( 'llms' );

verifica( 'con i dati aziendali il file viene composto', '' !== $llms );
verifica( 'porta il nome dell azienda', false !== strpos( $llms, 'Max Digital Innovation' ) );
verifica( 'porta i dati di contatto', false !== strpos( $llms, '+39 091 000000' ) && false !== strpos( $llms, 'Via Roma 1' ) );
verifica( 'elenca gli articoli pubblicati adesso', false !== strpos( $llms, 'Guida alla realizzazione di siti web' ) );
verifica( 'elenca le pagine come servizi', false !== strpos( $llms, 'Servizi digitali a Palermo' ) );
verifica( 'usa la description SEO quando c è', false !== strpos( $llms, 'Come si realizza un sito web che porta clienti.' ) );
verifica( 'dichiara la data di aggiornamento', false !== strpos( $llms, 'Aggiornato: ' . gmdate( 'Y-m-d' ) ) );

// La cache serve a non ricomporre il file a ogni visita di un crawler.
stub_crea_post( 702, 'Articolo pubblicato dopo', str_repeat( 'testo ', 300 ) );
verifica( 'il file viene servito dalla cache', false === strpos( MDI_AI::genera( 'llms' ), 'Articolo pubblicato dopo' ) );

// Ma una modifica ai contenuti la butta via.
foreach ( (array) ( $GLOBALS['wp']['azioni']['save_post'] ?? array() ) as $callback ) {
	call_user_func( $callback, 702 );
}

verifica( 'pubblicare un contenuto invalida la cache', false !== strpos( MDI_AI::genera( 'llms' ), 'Articolo pubblicato dopo' ) );

$pieno = MDI_AI::genera( 'llms-full' );
verifica( 'la versione completa contiene i testi', false !== strpos( $pieno, '# Contenuti completi' ) );
verifica( 'e i contenuti lunghi ci sono', false !== strpos( $pieno, 'Guida alla realizzazione di siti web' ) );

stub_crea_post( 703, 'Troppo corto', 'tre parole soltanto' );
MDI_AI::svuota_cache();

// Il titolo compare comunque nell indice: quello che non deve comparire è la
// sua scheda nella parte dei testi completi.
$completo = MDI_AI::genera( 'llms-full' );
$testi    = substr( $completo, (int) strpos( $completo, '# Contenuti completi' ) );

verifica( 'i contenuti sotto le 200 parole restano fuori dai testi completi', false === strpos( $testi, '## Troppo corto' ) );
verifica( 'ma restano nell indice delle guide', false !== strpos( $completo, 'Troppo corto' ) );

$ai = MDI_AI::genera( 'ai' );
verifica( 'ai.txt dichiara il proprietario', false !== strpos( $ai, 'Owner: Max Digital Innovation' ) );
verifica( 'ai.txt chiede attribuzione per l addestramento', false !== strpos( $ai, 'Usage-training: allow-with-attribution' ) );

// L indirizzo della sitemap dipende da chi la genera: non si indovina.
verifica( 'senza plugin SEO punta alla sitemap di WordPress', false !== strpos( MDI_AI::url_sitemap(), '/wp-sitemap.xml' ) );

if ( ! defined( 'WPSEO_VERSION' ) ) {
	define( 'WPSEO_VERSION', '1.0' );
}

verifica( 'con Yoast o Rank Math punta alla loro', false !== strpos( MDI_AI::url_sitemap(), '/sitemap_index.xml' ) );
verifica( 'e robots.txt dichiara la stessa sitemap', false !== strpos( MDI_AI::robots_txt( '', true ), MDI_AI::url_sitemap() ) );

// --- Ripristino delle categorie --------------------------------------------
// wp_set_post_categories cancella tutte le categorie precedenti: senza una
// copia, quella riassegnazione non si poteva annullare in nessun modo.
echo "\nRipristino delle categorie\n";

stub_crea_post( 810, 'Articolo con categorie sue', '<p>Testo.</p>' );
wp_set_post_categories( 810, array( 3, 7 ) );

MDI_Api::assegna_categoria( new WP_REST_Request( array( 'assegnazioni' => array( array( 'id' => 810, 'categoria' => 'Nuova categoria' ) ) ) ) );

verifica( 'la categoria viene sostituita', array( 3, 7 ) !== wp_get_post_categories( 810 ) );
verifica( 'e quelle di prima vengono messe da parte', '' !== get_post_meta( 810, MDI_Api::META_CATEGORIE, true ) );

$esito = MDI_Api::annulla_meta( new WP_REST_Request( array( 'ids' => array( 810 ) ) ) );

verifica( 'il ripristino conta anche chi aveva solo le categorie cambiate', 1 === ( $esito['ripristinati'] ?? 0 ) );
verifica( 'le categorie tornano quelle di prima', array( 3, 7 ) === wp_get_post_categories( 810 ) );
verifica( 'e la copia viene rimossa', '' === get_post_meta( 810, MDI_Api::META_CATEGORIE, true ) );

// Due giri di riassegnazione non devono sovrascrivere la copia originale.
wp_set_post_categories( 810, array( 3, 7 ) );
MDI_Api::assegna_categoria( new WP_REST_Request( array( 'assegnazioni' => array( array( 'id' => 810, 'categoria' => 'Prima' ) ) ) ) );
MDI_Api::assegna_categoria( new WP_REST_Request( array( 'assegnazioni' => array( array( 'id' => 810, 'categoria' => 'Seconda' ) ) ) ) );
MDI_Api::annulla_meta( new WP_REST_Request( array( 'ids' => array( 810 ) ) ) );

verifica( 'dopo due riassegnazioni si torna comunque all originale', array( 3, 7 ) === wp_get_post_categories( 810 ) );

echo "\nRicompressione delle immagini gia caricate\n";

if ( ! function_exists( 'imagewebp' ) ) {
	echo "  · GD senza WebP su questa macchina: verifiche saltate\n";
} else {
	// Tre immagini vere sul disco: una in evidenza (si puo ricomprimere),
	// una usata dentro al testo di un articolo (non si tocca, perche
	// cambiarle il nome la farebbe sparire dalla pagina) e una leggera.
	$fileEvidenza = stub_crea_allegato( 920, 'foto-in-evidenza.png', 1536, 28 );
	$fileTesto    = stub_crea_allegato( 921, 'foto-dentro-al-testo.png', 1536, 28 );
	$fileLeggera  = stub_crea_allegato( 922, 'foto-leggera.png', 300, 2 );

	stub_crea_post( 930, 'Articolo che mostra l immagine', '<p>Testo</p><img src="https://esempio.it/wp-content/uploads/foto-dentro-al-testo.png" alt="">' );

	$pesoPrima = filesize( $fileEvidenza );

	verifica( 'l immagine di prova pesa piu della soglia', $pesoPrima > 204800, round( $pesoPrima / 1024 ) . ' KB' );

	$elenco = MDI_Api::immagini_pesanti( new WP_REST_Request( array( 'oltre' => 204800 ) ) );
	$per_id = array();

	foreach ( (array) $elenco['immagini'] as $riga ) {
		$per_id[ (int) $riga['wp_id'] ] = $riga;
	}

	verifica( 'l immagine pesante compare nell elenco', isset( $per_id[920] ) );
	verifica( 'quella leggera resta fuori', ! isset( $per_id[922] ) );
	verifica( 'quella usata nel testo e segnalata come da non toccare', ! empty( $per_id[921]['nel_testo'] ) );
	verifica( 'quella in evidenza invece si puo comprimere', empty( $per_id[920]['nel_testo'] ) );

	$esito = MDI_Api::comprimi_immagine( new WP_REST_Request( array( 'id' => 920, 'lato' => 1200, 'qualita' => 82, 'peso_max' => 190000 ) ) );

	verifica( 'la compressione dice di aver cambiato il file', ! empty( $esito['cambiata'] ) );
	verifica( 'il file nuovo pesa meno di prima', (int) $esito['dopo'] < (int) $esito['prima'], $esito['dopo'] . ' < ' . $esito['prima'] );
	verifica( 'e sta sotto i 200 KB (IMG-03)', (int) $esito['dopo'] < 204800, round( (int) $esito['dopo'] / 1024 ) . ' KB' );

	$nuovo = get_attached_file( 920 );

	verifica( 'l allegato punta a un file .webp', 'webp' === strtolower( pathinfo( $nuovo, PATHINFO_EXTENSION ) ), basename( $nuovo ) );
	verifica( 'il file nuovo esiste davvero sul disco', file_exists( $nuovo ) );
	verifica( 'il tipo MIME dell allegato e aggiornato', 'image/webp' === get_post_mime_type( 920 ) );
	verifica( 'e il file sul disco e davvero un WebP', 'image/webp' === ( getimagesize( $nuovo )['mime'] ?? '' ) );

	// Il ripristino dipende tutto dall originale ancora sul disco.
	verifica( 'l originale non viene cancellato', file_exists( $fileEvidenza ) );
	verifica( 'e il gestionale sa dove trovarlo', '' !== get_post_meta( 920, MDI_Api::META_IMG_PRIMA, true ) );

	$ancora = MDI_Api::comprimi_immagine( new WP_REST_Request( array( 'id' => 920 ) ) );

	verifica( 'una immagine gia ricompressa non viene rifatta', is_wp_error( $ancora ) );

	$ripristino = MDI_Api::ripristina_immagine( new WP_REST_Request( array( 'ids' => array( 920 ) ) ) );

	verifica( 'il ripristino ne rimette una', 1 === (int) ( $ripristino['ripristinate'] ?? 0 ) );
	verifica( 'l allegato torna al file originale', $fileEvidenza === get_attached_file( 920 ) );
	verifica( 'il tipo MIME torna quello di prima', 'image/png' === get_post_mime_type( 920 ) );
	verifica( 'e la copia di sicurezza viene rimossa', '' === get_post_meta( 920, MDI_Api::META_IMG_PRIMA, true ) );

	// Dopo il ripristino si deve poter ricomprimere di nuovo.
	$dinuovo = MDI_Api::comprimi_immagine( new WP_REST_Request( array( 'id' => 920 ) ) );

	verifica( 'dopo il ripristino si puo ricomprimere di nuovo', ! is_wp_error( $dinuovo ) && ! empty( $dinuovo['cambiata'] ) );

	MDI_Api::ripristina_immagine( new WP_REST_Request( array( 'ids' => array( 920 ) ) ) );

	// Un allegato che non esiste non deve mandare in errore la procedura.
	verifica(
		'un allegato inesistente da un errore chiaro',
		is_wp_error( MDI_Api::comprimi_immagine( new WP_REST_Request( array( 'id' => 999999 ) ) ) )
	);

	// Un tentativo interrotto a meta. Il segnale "gia fatta" veniva scritto
	// PRIMA dello scambio del file: se la richiesta moriva durante la
	// rigenerazione delle miniature - su immagini da megabyte capita -
	// l immagine restava marcata come fatta senza esserlo, e da li in poi
	// ogni tentativo rispondeva 409 senza piu rimediare. Sul sito vero questo
	// ha prodotto immagini pesanti che non si riuscivano piu a comprimere.
	$fileMozzo = stub_crea_allegato( 950, 'interrotta-a-meta.png', 1536, 28 );

	// Si simula l interruzione: il segnale c e, ma il file e ancora quello.
	$relativoMozzo = ltrim( str_replace( wp_upload_dir()['basedir'], '', $fileMozzo ), '/' );
	update_post_meta( 950, MDI_Api::META_IMG_PRIMA, $relativoMozzo );

	$ripresa = MDI_Api::comprimi_immagine( new WP_REST_Request( array( 'id' => 950 ) ) );

	verifica( 'una compressione interrotta non si blocca sul 409', ! is_wp_error( $ripresa ) );

	// Se la precedente fallisce, $ripresa e un WP_Error: senza questa rete
	// il file di verifica andrebbe in errore fatale invece di riportare
	// quali verifiche non sono passate.
	$ripresa = is_wp_error( $ripresa ) ? array() : (array) $ripresa;

	verifica( 'e viene portata a termine', ! empty( $ripresa['cambiata'] ) );
	verifica( 'con il file davvero sostituito', 'webp' === strtolower( pathinfo( get_attached_file( 950 ), PATHINFO_EXTENSION ) ) );

	// E una gia fatta per davvero deve continuare a essere rifiutata.
	verifica(
		'una davvero gia fatta resta rifiutata',
		is_wp_error( MDI_Api::comprimi_immagine( new WP_REST_Request( array( 'id' => 950 ) ) ) )
	);

	// Il conteggio di quante ne sono gia state fatte.
	delete_transient( 'mdi_nomi_immagini_usate' );
	$conteggio = MDI_Api::immagini_pesanti( new WP_REST_Request( array( 'oltre' => 204800 ) ) );

	verifica( 'la rotta dice quante ne sono gia state ricompresse', (int) $conteggio['gia_fatte'] >= 1, (string) $conteggio['gia_fatte'] );

	$restano = array();

	foreach ( (array) $conteggio['immagini'] as $riga ) {
		$restano[] = (int) $riga['wp_id'];
	}

	verifica( 'e una appena ricompressa non resta fra quelle da fare', ! in_array( 950, $restano, true ) );

	// Una libreria media grande come quella vera: la rotta deve rispondere a
	// blocchi e non fare una query per immagine, altrimenti va in timeout
	// prima di mostrare qualsiasi cosa.
	delete_transient( 'mdi_nomi_immagini_usate' );

	foreach ( range( 1, 40 ) as $n ) {
		stub_crea_allegato( 1000 + $n, 'massa-' . $n . '.png', 900, 26 );
	}

	$GLOBALS['wp']['query_fatte'] = 0;

	$primo = MDI_Api::immagini_pesanti( new WP_REST_Request( array( 'oltre' => 100000, 'blocco' => 25 ) ) );

	verifica( 'il primo blocco non guarda tutta la libreria', 25 === (int) $primo['guardati'], (string) $primo['guardati'] );
	verifica( 'e dice che non ha finito', empty( $primo['finito'] ) );
	verifica( 'e da dove riprendere', 25 === (int) $primo['prossimo'] );
	verifica( 'il totale invece e quello vero', (int) $primo['totale'] >= 40, (string) $primo['totale'] );

	$visti  = count( (array) $primo['immagini'] );
	$offset = (int) $primo['prossimo'];
	$giri   = 1;

	while ( empty( $primo['finito'] ) && $giri < 20 ) {
		$primo   = MDI_Api::immagini_pesanti( new WP_REST_Request( array( 'oltre' => 100000, 'blocco' => 25, 'offset' => $offset ) ) );
		$visti  += count( (array) $primo['immagini'] );
		$offset  = (int) $primo['prossimo'];
		$giri++;
	}

	verifica( 'continuando a blocchi si arriva in fondo', ! empty( $primo['finito'] ) );
	verifica( 'e si sono viste tutte le immagini pesanti', $visti >= 40, $visti . ' viste' );

	verifica(
		'le query sul contenuto sono una sola, non una per immagine',
		(int) ( $GLOBALS['wp']['query_fatte'] ?? 0 ) <= 1,
		(int) ( $GLOBALS['wp']['query_fatte'] ?? 0 ) . ' query'
	);

	array_map( 'unlink', glob( stub_cartella_caricamenti() . '/*' ) );
}

echo "\n" . ( $errori ? "✖ $errori verifiche fallite\n\n" : "✔ tutte le verifiche superate\n\n" );

exit( $errori ? 1 : 0 );
