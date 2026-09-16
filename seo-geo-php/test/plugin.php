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
// La cartella data/ nel plugin la riempie l export: in un albero appena
// clonato non c e, e senza questo il file non si scriveva e il collaudo
// misurava una mappa vuota invece dei link.
$cartella_link = MDI_SEO_GEO_DIR . 'data';

if ( ! is_dir( $cartella_link ) ) {
	mkdir( $cartella_link, 0777, true );
}

$file_link = $cartella_link . '/internal-links.json';
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

// --- L H1 lo stampa il tema ------------------------------------------------
// Il gestionale segnava «H1 non rilevabile nel contenuto» su 279 articoli di
// 295. Nei temi WordPress l H1 e il titolo dell articolo e lo stampa il tema:
// nel testo salvato non c e, e non ci deve essere. L unico posto dove la
// risposta esiste e la pagina servita, quindi il plugin la va a leggere.
$chiediH1 = static function ( $risposta ) {
	unset( $GLOBALS['wp']['transient']['mdi_h1_dal_tema'] );
	$GLOBALS['wp']['http']['*'] = $risposta;

	return ! empty( MDI_Api::conteggi()['sito']['stampa']['h1'] );
};

verifica(
	'una pagina con H1 chiude il rilievo',
	true === $chiediH1( array( 'response' => array( 'code' => 200 ), 'body' => '<html><body><h1 class="titolo">Articolo</h1><p>x</p></body></html>' ) )
);

// Il difetto vero, trovato sul sito: l articolo piu recente aveva un H1
// scritto dentro al suo testo. Contare gli H1 della pagina e chiamarli «del
// tema» dava «si» anche con un tema che non ne stampa nessuno, e da li ogni
// articolo con un H1 nel testo ne risultava due: il rilievo sui doppioni e
// passato da cinque a trentadue in una rilettura.
// Quale articolo venga scelto come campione lo decide il plugin: si mette lo
// stesso testo su tutti quelli pubblicati, cosi la prova vale comunque.
$contenutiPrima = array();

foreach ( (array) $GLOBALS['wp']['post'] as $unId => $unPost ) {
	if ( 'post' === $unPost->post_type && 'publish' === $unPost->post_status ) {
		$contenutiPrima[ $unId ] = $unPost->post_content;
		$GLOBALS['wp']['post'][ $unId ]->post_content = '<h1>Titolo scritto nel testo</h1><p>corpo</p>';
	}
}

verifica(
	'un H1 che era gia nel testo non si conta come messo dal tema',
	false === $chiediH1( array( 'response' => array( 'code' => 200 ), 'body' => '<html><body><h1>Titolo scritto nel testo</h1><p>corpo</p></body></html>' ) ),
	'il tema non stampa niente, ma il controllo dice di si'
);

verifica(
	'mentre se la pagina ne ha uno in piu, quello e del tema',
	true === $chiediH1( array( 'response' => array( 'code' => 200 ), 'body' => '<html><body><h1>Titolo del tema</h1><h1>Titolo scritto nel testo</h1><p>corpo</p></body></html>' ) ),
	'il doppione vero non viene visto'
);

foreach ( $contenutiPrima as $unId => $testoPrima ) {
	$GLOBALS['wp']['post'][ $unId ]->post_content = $testoPrima;
}

verifica(
	'una pagina senza H1 lo lascia aperto',
	false === $chiediH1( array( 'response' => array( 'code' => 200 ), 'body' => '<html><body><h2>Articolo</h2><p>x</p></body></html>' ) )
);

// Molti hosting bloccano le richieste del sito verso se stesso: li non si sa,
// e non sapere non e una risoluzione.
verifica(
	'un sito che non risponde non chiude niente',
	false === $chiediH1( new WP_Error( 'http_request_failed', 'loopback bloccato' ) )
);

verifica(
	'nemmeno una pagina che risponde con un errore',
	false === $chiediH1( array( 'response' => array( 'code' => 503 ), 'body' => '<h1>Manutenzione</h1>' ) )
);

// La risposta si tiene da parte: cambia solo cambiando tema, e non deve
// costare una richiesta a ogni lettura del sito.
unset( $GLOBALS['wp']['transient']['mdi_h1_dal_tema'] );
$GLOBALS['wp']['http']['*'] = array( 'response' => array( 'code' => 200 ), 'body' => '<h1>Articolo</h1>' );
MDI_Api::conteggi();
$GLOBALS['wp']['http']['*'] = new WP_Error( 'http_request_failed', 'non deve nemmeno provarci' );

verifica(
	'e non si richiede a ogni lettura',
	! empty( MDI_Api::conteggi()['sito']['stampa']['h1'] )
);

unset( $GLOBALS['wp']['transient']['mdi_h1_dal_tema'], $GLOBALS['wp']['http'] );

// --- Il testo di una pagina costruita con Elementor -------------------------
// Su una pagina Elementor il testo sta dentro alla struttura del costruttore,
// non in post_content: li c e un residuo o il vuoto. L analisi leggeva quello
// e concludeva «contenuto molto scarno», «nessun H2», «nessuna tabella» su
// pagine piene di roba.
$idElem = null;

foreach ( (array) $GLOBALS['wp']['post'] as $unId => $unPost ) {
	if ( 'page' === $unPost->post_type ) {
		$idElem = (int) $unId;
		break;
	}
}

if ( null === $idElem ) {
	$idElem = array_key_first( (array) $GLOBALS['wp']['post'] );
}

$contenutoOriginale = $GLOBALS['wp']['post'][ $idElem ]->post_content;
$GLOBALS['wp']['post'][ $idElem ]->post_content = '';
$GLOBALS['wp']['meta'][ $idElem ]['_elementor_data'] = array(
	wp_json_encode(
		array(
			array(
				'elements' => array(
					array( 'widgetType' => 'heading', 'settings' => array( 'title' => 'Servizi di video making', 'header_size' => 'h2' ) ),
					array( 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Realizziamo video per aziende a Palermo con troupe interna.</p>' ) ),
					array( 'widgetType' => 'image', 'settings' => array( 'image' => array( 'url' => 'https://esempio.it/foto.jpg', 'alt' => 'Troupe al lavoro' ) ) ),
				),
			),
		)
	),
);

$lettoElem = null;

foreach ( MDI_Api::contenuti( new WP_REST_Request( array( 'offset' => 0, 'limite' => 50 ) ) )['contenuti'] as $unContenuto ) {
	if ( (string) $idElem === (string) $unContenuto['wp_id'] ) {
		$lettoElem = $unContenuto;
	}
}

verifica( 'il testo scritto dentro a Elementor arriva all analisi', false !== strpos( (string) ( $lettoElem['contenuto'] ?? '' ), 'troupe interna' ), (string) ( $lettoElem['contenuto'] ?? '(niente)' ) );
verifica( 'e i titoletti diventano veri H2', false !== strpos( (string) ( $lettoElem['contenuto'] ?? '' ), '<h2>Servizi di video making</h2>' ), (string) ( $lettoElem['contenuto'] ?? '(niente)' ) );
verifica( 'e le immagini si contano', false !== strpos( (string) ( $lettoElem['contenuto'] ?? '' ), 'foto.jpg' ), (string) ( $lettoElem['contenuto'] ?? '(niente)' ) );

// Quando dentro a Elementor c e il widget che rende il contenuto dell
// articolo, il testo vero e post_content: non si ricostruisce niente.
$GLOBALS['wp']['post'][ $idElem ]->post_content = '<p>Questo e il testo vero dell articolo.</p>';
$GLOBALS['wp']['meta'][ $idElem ]['_elementor_data'] = array(
	wp_json_encode( array( array( 'elements' => array( array( 'widgetType' => 'theme-post-content', 'settings' => array() ) ) ) ) ),
);

$lettoRende = null;

foreach ( MDI_Api::contenuti( new WP_REST_Request( array( 'offset' => 0, 'limite' => 50 ) ) )['contenuti'] as $unContenuto ) {
	if ( (string) $idElem === (string) $unContenuto['wp_id'] ) {
		$lettoRende = $unContenuto;
	}
}

verifica(
	'ma se Elementor rende il contenuto dell articolo si tiene quello',
	false !== strpos( (string) ( $lettoRende['contenuto'] ?? '' ), 'testo vero dell articolo' ),
	(string) ( $lettoRende['contenuto'] ?? '(niente)' )
);

// Una struttura illeggibile non deve far sparire il contenuto.
$GLOBALS['wp']['meta'][ $idElem ]['_elementor_data'] = array( 'non e json' );

$lettoRotto = null;

foreach ( MDI_Api::contenuti( new WP_REST_Request( array( 'offset' => 0, 'limite' => 50 ) ) )['contenuti'] as $unContenuto ) {
	if ( (string) $idElem === (string) $unContenuto['wp_id'] ) {
		$lettoRotto = $unContenuto;
	}
}

verifica(
	'e una struttura illeggibile non cancella il testo',
	false !== strpos( (string) ( $lettoRotto['contenuto'] ?? '' ), 'testo vero dell articolo' ),
	(string) ( $lettoRotto['contenuto'] ?? '(niente)' )
);

unset( $GLOBALS['wp']['meta'][ $idElem ]['_elementor_data'] );
$GLOBALS['wp']['post'][ $idElem ]->post_content = $contenutoOriginale;

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

	$ancora = is_wp_error( $ancora ) ? array() : (array) $ancora;

	verifica( 'una immagine gia ricompressa non viene rifatta', empty( $ancora['cambiata'] ) && 'gia_fatta' === ( $ancora['motivo'] ?? '' ), (string) ( $ancora['motivo'] ?? 'errore' ) );

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

	// Una gia fatta per davvero non si rifa, ma non e nemmeno un errore: in
	// un lavoro a blocchi ritrovarsela davanti e normale. Rispondere con un
	// errore riempiva l elenco dei guasti e faceva credere al ciclo di non
	// avere concluso niente, fermandolo con meta lavoro ancora da fare.
	$ripetuta = MDI_Api::comprimi_immagine( new WP_REST_Request( array( 'id' => 950 ) ) );

	verifica( 'una davvero gia fatta non e un errore', ! is_wp_error( $ripetuta ) );

	$ripetuta = is_wp_error( $ripetuta ) ? array() : (array) $ripetuta;

	verifica( 'ma non viene rifatta', empty( $ripetuta['cambiata'] ) );
	verifica( 'e dice perche', 'gia_fatta' === ( $ripetuta['motivo'] ?? '' ), (string) ( $ripetuta['motivo'] ?? '' ) );

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

echo "\nSovrascrivere l articolo invece di creare un doppione\n";

stub_crea_post( 970, 'Titolo originale', '<p>Testo originale dell articolo, scritto a mano.</p>' );
update_post_meta( 970, 'rank_math_title', 'Title di prima' );
update_post_meta( 970, 'rank_math_description', 'Description di prima' );

$quantiPrima = count( $GLOBALS['wp']['post'] );

$scritto = MDI_Api::sovrascrivi(
	new WP_REST_Request(
		array(
			'id'               => 970,
			'titolo'           => 'Titolo migliorato',
			'contenuto'        => '<p>Testo migliorato, con la struttura sistemata.</p>',
			'meta_title'       => 'Title nuovo',
			'meta_description' => 'Description nuova',
		)
	)
);

verifica( 'la sovrascrittura riesce', ! is_wp_error( $scritto ) );
verifica( 'e non crea un secondo articolo', $quantiPrima === count( $GLOBALS['wp']['post'] ), count( $GLOBALS['wp']['post'] ) . ' contro ' . $quantiPrima );

$dopoScrittura = get_post( 970 );

verifica( 'il testo sull articolo e quello nuovo', false !== strpos( $dopoScrittura->post_content, 'Testo migliorato' ) );
verifica( 'e anche il titolo', 'Titolo migliorato' === $dopoScrittura->post_title );
verifica( 'le meta seguono', 'Title nuovo' === get_post_meta( 970, 'rank_math_title', true ) );

// La rete che conta: sovrascrivere un articolo pubblicato senza poter
// tornare indietro non sarebbe accettabile.
verifica( 'il testo di prima resta da parte', '' !== get_post_meta( 970, MDI_Api::META_TESTO_PRIMA, true ) );

// Sovrascrivere due volte non deve far perdere l originale: la copia deve
// restare quella del primo testo, non della versione intermedia.
MDI_Api::sovrascrivi( new WP_REST_Request( array( 'id' => 970, 'contenuto' => '<p>Terza versione.</p>' ) ) );

$copia = json_decode( (string) get_post_meta( 970, MDI_Api::META_TESTO_PRIMA, true ), true );

verifica(
	'e resta quella originale anche dopo due passaggi',
	false !== strpos( (string) ( $copia['post_content'] ?? '' ), 'Testo originale' ),
	(string) ( $copia['post_content'] ?? '' )
);

$rimesso = MDI_Api::annulla_meta( new WP_REST_Request( array( 'ids' => array( 970 ) ) ) );

verifica( 'l annulla lo conta', 1 === (int) ( $rimesso['ripristinati'] ?? 0 ) );

$tornato = get_post( 970 );

verifica( 'il testo torna quello originale', false !== strpos( $tornato->post_content, 'Testo originale' ), $tornato->post_content );
verifica( 'e il titolo pure', 'Titolo originale' === $tornato->post_title, $tornato->post_title );
verifica( 'le meta tornano quelle di prima', 'Title di prima' === get_post_meta( 970, 'rank_math_title', true ) );
verifica( 'e la copia viene rimossa', '' === get_post_meta( 970, MDI_Api::META_TESTO_PRIMA, true ) );

verifica(
	'un contenuto vuoto viene rifiutato',
	is_wp_error( MDI_Api::sovrascrivi( new WP_REST_Request( array( 'id' => 970, 'contenuto' => '   ' ) ) ) )
);

// Il caso vero: su 328 contenuti del sito, 271 hanno _elementor_data. Il
// testo che si vede lo compone Elementor, non post_content. Sovrascrivere
// riusciva senza errori e cambiava solo il titolo: un lavoro che sembrava
// fatto e non si vedeva da nessuna parte.
stub_crea_post( 980, 'Articolo con Elementor', '<p>Residuo in post_content.</p>' );
update_post_meta( 980, '_elementor_data', '[{"elType":"section"}]' );
update_post_meta( 980, '_elementor_edit_mode', 'builder' );

verifica( 'un contenuto con Elementor viene riconosciuto', 'Elementor' === MDI_Api::costruttore( 980 ) );

// Il difetto segnalato: _elementor_data resta sul post anche solo per
// averlo aperto una volta nell editor visuale e poi essere tornati
// indietro. Quei contenuti si renderizzano da post_content come gli altri
// e bloccarli voleva dire impedire una sovrascrittura che funziona.
stub_crea_post( 981, 'Articolo aperto una volta con Elementor', '<p>Questo e il testo vero.</p>' );
update_post_meta( 981, '_elementor_data', '[{"elType":"section"}]' );

verifica(
	'i dati di Elementor rimasti da una prova non bloccano il contenuto',
	'' === MDI_Api::costruttore( 981 ),
	MDI_Api::costruttore( 981 )
);

$scrittoResiduo = MDI_Api::sovrascrivi( new WP_REST_Request( array( 'id' => 981, 'contenuto' => '<p>Testo nuovo.</p>' ) ) );

verifica( 'e si sovrascrive normalmente', ! is_wp_error( $scrittoResiduo ) );
verifica( 'con il testo che cambia davvero', false !== strpos( get_post( 981 )->post_content, 'Testo nuovo' ), get_post( 981 )->post_content );
verifica( 'e uno normale no', '' === MDI_Api::costruttore( 970 ), MDI_Api::costruttore( 970 ) );

$rifiutato = MDI_Api::sovrascrivi( new WP_REST_Request( array( 'id' => 980, 'contenuto' => '<p>Testo nuovo.</p>' ) ) );

verifica( 'sovrascriverlo viene rifiutato invece di non fare niente', is_wp_error( $rifiutato ) );
// Questa struttura non ha nessuna colonna: non c e nessun posto dove
// scrivere, ed e l unico caso che resta impossibile.
verifica(
	'e il motivo dice che non c e un posto dove scrivere',
	is_wp_error( $rifiutato ) && false !== stripos( $rifiutato->get_error_message(), 'colonna' ),
	is_wp_error( $rifiutato ) ? $rifiutato->get_error_message() : ''
);
verifica( 'e il titolo non viene toccato', 'Articolo con Elementor' === get_post( 980 )->post_title );

// Chi sa quello che fa deve poterlo forzare lo stesso.
$forzato = MDI_Api::sovrascrivi( new WP_REST_Request( array( 'id' => 980, 'contenuto' => '<p>Testo nuovo.</p>', 'forza' => 1 ) ) );

verifica( 'ma si puo forzare sapendo cosa si fa', ! is_wp_error( $forzato ) );

// L elenco in blocco: serve a dirlo prima di premere il pulsante.
$elencoCostruttori = MDI_Api::costruttori( new WP_REST_Request( array( 'ids' => array( 970, 980 ) ) ) );

verifica( 'l elenco in blocco distingue i due casi',
	'Elementor' === ( $elencoCostruttori['costruttori']['980'] ?? null )
		&& '' === ( $elencoCostruttori['costruttori']['970'] ?? null ),
	json_encode( $elencoCostruttori['costruttori'] ?? array() )
);

verifica(
	'e un articolo inesistente pure',
	is_wp_error( MDI_Api::sovrascrivi( new WP_REST_Request( array( 'id' => 999999, 'contenuto' => '<p>x</p>' ) ) ) )
);

echo "\nScrivere dentro a Elementor\n";

// Struttura come la produce Elementor: sezione, colonna, widget. Il testo
// dell articolo sta nelle impostazioni del widget text-editor.
$strutturaElementor = static function ( array $widget ) {
	return array(
		array(
			'id'       => 'sez1',
			'elType'   => 'section',
			'settings' => array(),
			'elements' => array(
				array(
					'id'       => 'col1',
					'elType'   => 'column',
					'settings' => array(),
					'elements' => $widget,
				),
			),
		),
	);
};

$unSoloTesto = $strutturaElementor(
	array(
		array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Titolo in pagina' ) ),
		array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Il testo vecchio dell articolo, abbastanza lungo.</p>' ) ),
	)
);

stub_crea_post( 990, 'Articolo Elementor', '<p>Residuo.</p>' );
update_post_meta( 990, '_elementor_data', wp_json_encode( $unSoloTesto ) );
update_post_meta( 990, '_elementor_edit_mode', 'builder' );

$letta = MDI_Api::struttura_elementor( 990 );

verifica( 'la struttura viene letta', '' === $letta['errore'], $letta['errore'] );
verifica( 'e trova un solo blocco di testo', 1 === count( $letta['testi'] ), (string) count( $letta['testi'] ) );

$esitoScrittura = MDI_Api::sovrascrivi(
	new WP_REST_Request( array( 'id' => 990, 'titolo' => 'Titolo nuovo', 'contenuto' => '<h2>Sezione</h2><p>Il testo nuovo, con le "virgolette" dentro.</p>' ) )
);

verifica( 'la sovrascrittura riesce', ! is_wp_error( $esitoScrittura ) );

$esitoScrittura = is_wp_error( $esitoScrittura ) ? array() : (array) $esitoScrittura;

verifica( 'e dice di aver scritto dentro a Elementor', 'elementor' === ( $esitoScrittura['dove'] ?? '' ), (string) ( $esitoScrittura['dove'] ?? '' ) );

$dopoScrittura = json_decode( (string) get_post_meta( 990, '_elementor_data', true ), true );

verifica( 'la struttura resta valida', is_array( $dopoScrittura ) );

$widgetDopo = $dopoScrittura[0]['elements'][0]['elements'] ?? array();

verifica( 'il testo dentro al widget e quello nuovo', false !== strpos( (string) ( $widgetDopo[1]['settings']['editor'] ?? '' ), 'testo nuovo' ), (string) ( $widgetDopo[1]['settings']['editor'] ?? '' ) );
verifica( 'le virgolette sopravvivono al giro nel JSON', false !== strpos( (string) ( $widgetDopo[1]['settings']['editor'] ?? '' ), '"virgolette"' ) );
verifica( 'il resto della pagina non viene toccato', 'Titolo in pagina' === ( $widgetDopo[0]['settings']['title'] ?? '' ) );
verifica( 'e il CSS in cache viene buttato', '' === get_post_meta( 990, '_elementor_css', true ) );
verifica( 'la struttura di prima resta da parte', '' !== get_post_meta( 990, MDI_Api::META_ELEMENTOR_PRIMA, true ) );

// Due blocchi di testo: non si puo sapere quale sia l articolo e quale una
// didascalia. Indovinare vorrebbe dire cancellare qualcosa che serviva.
$dueTesti = $strutturaElementor(
	array(
		array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Primo blocco lungo con il corpo dell articolo.</p>' ) ),
		array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Chiamaci per un preventivo.</p>' ) ),
	)
);

stub_crea_post( 991, 'Articolo con due blocchi', '<p>Residuo.</p>' );
update_post_meta( 991, '_elementor_data', wp_json_encode( $dueTesti ) );
update_post_meta( 991, '_elementor_edit_mode', 'builder' );

$dueEsito = MDI_Api::sovrascrivi( new WP_REST_Request( array( 'id' => 991, 'contenuto' => '<p>Nuovo.</p>' ) ) );
$dueEsito = is_wp_error( $dueEsito ) ? array() : (array) $dueEsito;

verifica( 'con piu blocchi di testo scrive lo stesso', ! empty( $dueEsito['ok'] ) );

$dueDopo = json_decode( (string) get_post_meta( 991, '_elementor_data', true ), true );
$dueWidget = $dueDopo[0]['elements'][0]['elements'] ?? array();

verifica( 'il testo nuovo va nel blocco piu lungo', false !== strpos( (string) ( $dueWidget[0]['settings']['editor'] ?? '' ), 'Nuovo' ), (string) ( $dueWidget[0]['settings']['editor'] ?? '' ) );
verifica( 'il blocco corto resta dov era', false !== strpos( (string) ( $dueWidget[1]['settings']['editor'] ?? '' ), 'preventivo' ), (string) ( $dueWidget[1]['settings']['editor'] ?? '' ) );
verifica( 'e non viene svuotato niente di corto', 0 === (int) ( $dueEsito['svuotati'] ?? -1 ), (string) ( $dueEsito['svuotati'] ?? 'assente' ) );

// Due blocchi lunghi. Prima il secondo veniva svuotato, dando per scontato
// che fosse il resto dello stesso articolo. Su una pagina vera quella
// supposizione si e rivelata falsa e ha smontato il disegno: un blocco lungo
// puo essere l apertura o un riquadro. Adesso non si tocca niente.
$dueLunghi = $strutturaElementor(
	array(
		array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>' . str_repeat( 'Prima meta dell articolo. ', 20 ) . '</p>' ) ),
		array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>' . str_repeat( 'Seconda meta dell articolo. ', 15 ) . '</p>' ) ),
	)
);

stub_crea_post( 993, 'Articolo spezzato in due', '<p>Residuo.</p>' );
update_post_meta( 993, '_elementor_data', wp_json_encode( $dueLunghi ) );
update_post_meta( 993, '_elementor_edit_mode', 'builder' );

$spezzato = MDI_Api::sovrascrivi( new WP_REST_Request( array( 'id' => 993, 'contenuto' => '<p>Articolo riscritto.</p>' ) ) );
$spezzato = is_wp_error( $spezzato ) ? array() : (array) $spezzato;

$spezzatoDopo = json_decode( (string) get_post_meta( 993, '_elementor_data', true ), true );
$spezzatoWidget = $spezzatoDopo[0]['elements'][0]['elements'] ?? array();

verifica( 'nessun blocco viene svuotato', 0 === (int) ( $spezzato['svuotati'] ?? -1 ), (string) ( $spezzato['svuotati'] ?? 'assente' ) );
verifica( 'il secondo blocco resta dov era', false !== strpos( (string) ( $spezzatoWidget[1]['settings']['editor'] ?? '' ), 'Seconda meta' ) );
verifica( 'e il nuovo c e', false !== strpos( (string) ( $spezzatoWidget[0]['settings']['editor'] ?? '' ), 'Articolo riscritto' ) );

// Chi rivuole il vecchio comportamento lo accende dal filtro: resta
// possibile, ma non e piu quello che succede senza chiederlo.
verifica(
	'lo svuotamento resta disponibile, ma va acceso',
	false !== strpos( file_get_contents( MDI_SEO_GEO_DIR . 'includes/class-mdi-api.php' ), "apply_filters( 'mdi_seo_geo_soglia_blocco', 0 )" )
);

// Il CSS: si rifa quello della pagina, non quello di tutto il sito.
$sorgenteApi = file_get_contents( MDI_SEO_GEO_DIR . 'includes/class-mdi-api.php' );

verifica(
	'non si butta via il CSS di tutto il sito per un articolo',
	false === strpos( $sorgenteApi, 'files_manager->clear_cache();' )
);
verifica(
	'si rifa solo quello della pagina toccata',
	false !== strpos( $sorgenteApi, 'private static function rigenera_css_elementor' )
		&& 2 === substr_count( $sorgenteApi, 'self::rigenera_css_elementor( $id );' )
);

// La struttura si consegna a Elementor scritta come la scrive lui.
verifica(
	'la struttura si codifica come fa Elementor',
	false === strpos( $sorgenteApi, 'JSON_UNESCAPED_SLASHES' )
);

// Nessun blocco di testo: se ne aggiunge uno, senza toccare quello che c e.
$senzaTesto = $strutturaElementor(
	array( array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'image', 'settings' => array( 'image' => array( 'url' => 'x.jpg' ) ) ) )
);

stub_crea_post( 994, 'Articolo senza blocchi di testo', '<p>Residuo.</p>' );
update_post_meta( 994, '_elementor_data', wp_json_encode( $senzaTesto ) );
update_post_meta( 994, '_elementor_edit_mode', 'builder' );

$senza = MDI_Api::sovrascrivi( new WP_REST_Request( array( 'id' => 994, 'contenuto' => '<p>Testo aggiunto.</p>' ) ) );
$senza = is_wp_error( $senza ) ? array() : (array) $senza;

verifica( 'senza blocchi di testo ne viene aggiunto uno', ! empty( $senza['aggiunto'] ) );

$senzaDopo = json_decode( (string) get_post_meta( 994, '_elementor_data', true ), true );
$senzaWidget = $senzaDopo[0]['elements'][0]['elements'] ?? array();

verifica( 'l immagine che c era resta', 'image' === ( $senzaWidget[0]['widgetType'] ?? '' ) );
verifica( 'e il testo nuovo si aggiunge in fondo', false !== strpos( (string) ( $senzaWidget[1]['settings']['editor'] ?? '' ), 'Testo aggiunto' ), json_encode( $senzaWidget[1] ?? null ) );
verifica( 'il blocco aggiunto e un text-editor', 'text-editor' === ( $senzaWidget[1]['widgetType'] ?? '' ) );

// Tutto resta annullabile: la struttura di prima e messa da parte anche
// in questi due casi nuovi.
verifica( 'anche qui la struttura di prima resta da parte', '' !== get_post_meta( 993, MDI_Api::META_ELEMENTOR_PRIMA, true ) && '' !== get_post_meta( 994, MDI_Api::META_ELEMENTOR_PRIMA, true ) );

MDI_Api::annulla_meta( new WP_REST_Request( array( 'ids' => array( 993 ) ) ) );
$tornatoSpezzato = json_decode( (string) get_post_meta( 993, '_elementor_data', true ), true );

verifica(
	'e l annulla rimette tutti e due i blocchi',
	false !== strpos( (string) ( $tornatoSpezzato[0]['elements'][0]['elements'][1]['settings']['editor'] ?? '' ), 'Seconda meta' )
);

// Se la pagina ha un widget che rende post_content, il testo vero sta li e
// la sovrascrittura normale funziona.
$conPostContent = $strutturaElementor(
	array( array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'theme-post-content', 'settings' => array() ) )
);

stub_crea_post( 992, 'Articolo che mostra post_content', '<p>Testo vecchio.</p>' );
update_post_meta( 992, '_elementor_data', wp_json_encode( $conPostContent ) );
update_post_meta( 992, '_elementor_edit_mode', 'builder' );

$esitoPC = MDI_Api::sovrascrivi( new WP_REST_Request( array( 'id' => 992, 'contenuto' => '<p>Testo nuovo.</p>' ) ) );
$esitoPC = is_wp_error( $esitoPC ) ? array() : (array) $esitoPC;

verifica( 'quando Elementor mostra post_content si scrive li', 'contenuto' === ( $esitoPC['dove'] ?? '' ), (string) ( $esitoPC['dove'] ?? '' ) );
verifica( 'e il testo cambia davvero', false !== strpos( get_post( 992 )->post_content, 'Testo nuovo' ) );

// L annulla deve rimettere la struttura di Elementor, non solo
// post_content: rimettere post_content su una pagina Elementor non si
// vedrebbe, e sembrerebbe che l annulla non funzioni.
MDI_Api::annulla_meta( new WP_REST_Request( array( 'ids' => array( 990 ) ) ) );

$tornata = json_decode( (string) get_post_meta( 990, '_elementor_data', true ), true );
$widgetTornato = $tornata[0]['elements'][0]['elements'][1]['settings']['editor'] ?? '';

verifica( 'l annulla rimette il testo di prima dentro a Elementor', false !== strpos( (string) $widgetTornato, 'testo vecchio' ), (string) $widgetTornato );
verifica( 'e toglie la copia di sicurezza', '' === get_post_meta( 990, MDI_Api::META_ELEMENTOR_PRIMA, true ) );

// Resta fuori solo quello che non si riesce ad aprire: da quando anche i
// contenuti con piu blocchi di testo si sovrascrivono, avere due blocchi non
// e piu un motivo per fermarsi.
stub_crea_post( 989, 'Articolo con la struttura rotta', '<p>Residuo.</p>' );
update_post_meta( 989, '_elementor_data', 'questo non e JSON {{{' );
update_post_meta( 989, '_elementor_edit_mode', 'builder' );

// La diagnosi del sito: risponde alla domanda «il CSS si e rigenerato o no?».
// Se gli articoli cambiano aspetto tutti insieme, la causa non e dentro a
// nessuno di loro.
$diagnosiSito = MDI_Api::diagnosi_sito();
$diagnosiSito = is_wp_error( $diagnosiSito ) ? array() : (array) $diagnosiSito;

verifica( 'la diagnosi del sito dice dove stanno i file', '' !== (string) ( $diagnosiSito['cartella']['percorso'] ?? '' ) );
verifica( 'e se la cartella si lascia scrivere', array_key_exists( 'scrivibile', (array) ( $diagnosiSito['cartella'] ?? array() ) ) );
verifica( 'e se il file dei colori globali c e', array_key_exists( 'esiste', (array) ( $diagnosiSito['kit'] ?? array() ) ) );
verifica( 'e come Elementor stampa il CSS', array_key_exists( 'modo_css', (array) ( $diagnosiSito['elementor'] ?? array() ) ) );
verifica( 'e quale tema c e sotto', array_key_exists( 'nome', (array) ( $diagnosiSito['tema'] ?? array() ) ) );

// La diagnosi: che cosa c e davvero dentro a un contenuto. Nata dopo mezza
// giornata passata a confrontare screenshot senza capire se una pagina fosse
// cambiata o no.
stub_crea_post( 987, 'Articolo da diagnosticare', '<div class="mdi-in-breve"><p><strong>In breve:</strong> x</p></div><p>Testo [DA VERIFICARE: prezzo].</p><h2>Domande frequenti</h2>' );
update_post_meta( 987, 'blocksy_post_meta', array( 'hero_alignment' => 'center' ) );

$diagnosi = MDI_Api::diagnosi_contenuto( new WP_REST_Request( array( 'id' => 987 ) ) );
$diagnosi = is_wp_error( $diagnosi ) ? array() : (array) $diagnosi;

verifica( 'la diagnosi riconosce il testo scritto da noi', true === ( $diagnosi['scritto_da_noi']['in_breve'] ?? null ) );
verifica( 'e le domande frequenti', true === ( $diagnosi['scritto_da_noi']['domande_frequenti'] ?? null ) );
verifica( 'e conta i segnaposto rimasti', 1 === ( $diagnosi['scritto_da_noi']['segnaposto'] ?? 0 ), (string) ( $diagnosi['scritto_da_noi']['segnaposto'] ?? 'assente' ) );
verifica(
	'e riporta le impostazioni del tema, che decidono l aspetto',
	isset( $diagnosi['impostazioni_tema']['blocksy_post_meta'] )
		&& false !== strpos( (string) $diagnosi['impostazioni_tema']['blocksy_post_meta'], 'hero_alignment' ),
	wp_json_encode( $diagnosi['impostazioni_tema'] ?? array() )
);
verifica( 'e dice se la copia di sicurezza c e', false === ( $diagnosi['copie_di_sicurezza']['testo'] ?? null ) );

// Non restituisce il testo: serve a capire che cosa e successo, non a
// leggere i contenuti da fuori.
verifica( 'ma non manda fuori il testo dell articolo', ! isset( $diagnosi['testo']['contenuto'] ) && is_int( $diagnosi['testo']['caratteri'] ?? null ) );

// Un modello di Elementor non e un contenuto: e il disegno con cui il sito
// stampa TUTTI gli articoli. Scriverci dentro il testo di un articolo li
// smonterebbe tutti in una volta.
stub_crea_post( 988, 'Articolo singolo', '<p>Modello.</p>' );
$GLOBALS['wp']['post'][988]->post_type = 'elementor_library';

$suModello = MDI_Api::sovrascrivi( new WP_REST_Request( array( 'id' => 988, 'contenuto' => '<p>Testo di un articolo.</p>' ) ) );

verifica( 'su un modello di Elementor non si scrive', is_wp_error( $suModello ) );
verifica(
	'e si dice perche',
	false !== stripos( is_wp_error( $suModello ) ? $suModello->get_error_message() : '', 'articoli e pagine' ),
	is_wp_error( $suModello ) ? $suModello->get_error_message() : 'nessun errore'
);
verifica( 'e il modello non viene toccato', '<p>Modello.</p>' === get_post( 988 )->post_content );

// Il riassunto in blocco, per dire in pagina che cosa si potra fare.
$riassunto = MDI_Api::strutture_elementor( new WP_REST_Request( array( 'ids' => array( 990, 991, 992, 989 ) ) ) );

verifica( 'il riassunto dice quale si puo scrivere', true === ( $riassunto['strutture']['990']['scrivibile'] ?? null ) );
verifica( 'anche con piu blocchi di testo', true === ( $riassunto['strutture']['991']['scrivibile'] ?? null ) );
verifica( 'e quale no', false === ( $riassunto['strutture']['989']['scrivibile'] ?? null ) );
verifica( 'dicendo anche perche', false !== strpos( (string) ( $riassunto['strutture']['989']['errore'] ?? '' ), 'illeggibile' ), (string) ( $riassunto['strutture']['989']['errore'] ?? '' ) );
verifica( 'e riconosce quello che mostra post_content', true === ( $riassunto['strutture']['992']['post_content'] ?? null ) );

echo "\nSintesi e domande frequenti dentro all articolo\n";

// Sul sito vero l articolo finiva con "Ecco alcune delle domande piu comuni"
// e poi il vuoto: le domande frequenti stanno in un campo a parte della
// bozza e la sovrascrittura non le mandava. Il testo lo componevano in due
// posti diversi e solo uno le metteva.

$faqProva = array(
	array( 'domanda' => 'Quanto costa?', 'risposta' => 'Dipende dal progetto.' ),
	array( 'domanda' => 'Quanto tempo serve?', 'risposta' => 'Qualche settimana.' ),
);

stub_crea_post( 995, 'Articolo da riscrivere', '<p>Vecchio.</p>' );

MDI_Api::sovrascrivi(
	new WP_REST_Request(
		array(
			'id'        => 995,
			'contenuto' => '<h2>Sezione</h2><p>Il corpo.</p>',
			'in_breve'  => 'La sintesi iniziale.',
			'faq'       => $faqProva,
		)
	)
);

$scrittoConFaq = get_post( 995 )->post_content;

verifica( 'il corpo c e', false !== strpos( $scrittoConFaq, 'Il corpo' ) );
verifica( 'la sintesi iniziale c e', false !== strpos( $scrittoConFaq, 'La sintesi iniziale' ), $scrittoConFaq );
verifica( 'il titoletto delle domande frequenti c e', false !== strpos( $scrittoConFaq, '<h2>Domande frequenti</h2>' ) );
verifica( 'e ci sono tutte e due le domande', false !== strpos( $scrittoConFaq, 'Quanto costa?' ) && false !== strpos( $scrittoConFaq, 'Quanto tempo serve?' ) );
verifica( 'con le risposte', false !== strpos( $scrittoConFaq, 'Dipende dal progetto' ) && false !== strpos( $scrittoConFaq, 'Qualche settimana' ) );
verifica( 'il titoletto compare una volta sola', 1 === substr_count( $scrittoConFaq, '<h2>Domande frequenti</h2>' ) );

// Senza domande frequenti non deve comparire un titoletto vuoto.
stub_crea_post( 996, 'Articolo senza faq', '<p>Vecchio.</p>' );
MDI_Api::sovrascrivi( new WP_REST_Request( array( 'id' => 996, 'contenuto' => '<p>Solo corpo.</p>' ) ) );

verifica( 'senza domande frequenti non compare il titoletto', false === strpos( get_post( 996 )->post_content, 'Domande frequenti' ), get_post( 996 )->post_content );

// Bozza e sovrascrittura devono comporre lo stesso testo: e il motivo per
// cui adesso passano dallo stesso metodo.
$daBozza = MDI_Api::componi_testo( '<h2>Sezione</h2><p>Il corpo.</p>', 'La sintesi iniziale.', $faqProva );

verifica(
	'la bozza e la sovrascrittura compongono lo stesso testo',
	$daBozza === $scrittoConFaq,
	'differiscono'
);

// E dentro a Elementor devono finirci lo stesso.
$strutturaFaq = array(
	array(
		'id' => 's', 'elType' => 'section', 'settings' => array(),
		'elements' => array(
			array(
				'id' => 'c', 'elType' => 'column', 'settings' => array(),
				'elements' => array(
					array( 'id' => 'w', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Vecchio testo.</p>' ) ),
				),
			),
		),
	),
);

stub_crea_post( 997, 'Articolo Elementor con faq', '<p>Residuo.</p>' );
update_post_meta( 997, '_elementor_data', wp_json_encode( $strutturaFaq ) );
update_post_meta( 997, '_elementor_edit_mode', 'builder' );

MDI_Api::sovrascrivi(
	new WP_REST_Request( array( 'id' => 997, 'contenuto' => '<p>Il corpo.</p>', 'in_breve' => 'Sintesi.', 'faq' => $faqProva ) )
);

$dentroElementor = json_decode( (string) get_post_meta( 997, '_elementor_data', true ), true );
$testoElementor  = $dentroElementor[0]['elements'][0]['elements'][0]['settings']['editor'] ?? '';

verifica( 'anche dentro a Elementor ci finiscono le domande frequenti', false !== strpos( (string) $testoElementor, 'Quanto costa?' ), (string) $testoElementor );
verifica( 'e la sintesi iniziale', false !== strpos( (string) $testoElementor, 'Sintesi.' ) );

// --- La cache di pagina ----------------------------------------------------
// Nella home si vedeva un link interno che nell editor di Elementor non c era.
// Il link non era mai stato scritto da nessuna parte: lo metteva un filtro
// mentre la pagina veniva servita, e quel filtro non tocca piu le pagine da
// versioni. Quello che si guardava era una copia in cache fatta prima.
//
// Il guaio non e il link: e che ogni correzione applicata dal gestionale resta
// invisibile al pubblico finche la copia non scade, e che il plugin misura il
// sito leggendo le proprie pagine - se risponde la cache, misura com era
// prima e il rilievo appena chiuso torna in elenco.
echo "\nLa cache di pagina\n";

MDI_Cache::ricomincia();
$GLOBALS['wp']['svuotamenti'] = array();

verifica( 'senza plugin di cache non si trova niente da svuotare', array() === MDI_Cache::nomi() );

$svuotamenti = static function ( $azione ) {
	return array_values(
		array_filter(
			(array) $GLOBALS['wp']['svuotamenti'],
			static function ( $voce ) use ( $azione ) {
				return $azione === $voce['azione'];
			}
		)
	);
};

// Da qui in poi il sito ha LiteSpeed davanti, come quello vero.
define( 'LSCWP_V', '7.9.1' );

verifica( 'LiteSpeed viene riconosciuto', array( 'LiteSpeed Cache' ) === MDI_Cache::nomi() );

MDI_Cache::ricomincia();
$GLOBALS['wp']['svuotamenti'] = array();

verifica( 'e gli si chiede di svuotare la singola pagina', array( 'LiteSpeed Cache' ) === MDI_Cache::svuota( 321 ) );

$unaPagina = $svuotamenti( 'litespeed_purge_post' );

verifica( 'indicando quale', 1 === count( $unaPagina ) && array( 321 ) === $unaPagina[0]['argomenti'], wp_json_encode( $unaPagina ) );
verifica( 'insieme alla cache interna di WordPress', 1 === count( $svuotamenti( 'clean_post_cache' ) ) );

// Quaranta immagini compresse in un colpo solo non devono diventare quaranta
// svuotamenti totali: il sito si rigenererebbe da capo quaranta volte.
MDI_Cache::ricomincia();
$GLOBALS['wp']['svuotamenti'] = array();
MDI_Cache::svuota();
MDI_Cache::svuota();
MDI_Cache::svuota();

verifica( 'lo svuotamento totale si chiede una volta sola per richiesta', 1 === count( $svuotamenti( 'litespeed_purge_all' ) ), (string) count( $svuotamenti( 'litespeed_purge_all' ) ) );

// Il punto di tutto: la correzione deve arrivare in pagina, non fermarsi nel
// database.
stub_crea_post( 880, 'Articolo da correggere', '<p>Testo.</p>' );
MDI_Cache::ricomincia();
$GLOBALS['wp']['svuotamenti'] = array();

MDI_Api::aggiorna_meta(
	new WP_REST_Request(
		array(
			'contenuti' => array(
				array(
					'id'          => 880,
					'title'       => 'Un titolo lungo abbastanza da essere accettato',
					'description' => str_repeat( 'Una descrizione con abbastanza sostanza. ', 4 ),
				),
			),
			'anteprima' => false,
		)
	)
);

$dopoMeta = $svuotamenti( 'litespeed_purge_post' );

verifica(
	'dopo aver scritto title e description si svuota la pagina di quell articolo',
	1 === count( $dopoMeta ) && array( 880 ) === $dopoMeta[0]['argomenti'],
	'la correzione resta nel database e fuori si continua a vedere il sito di ieri'
);

// Cambiare la configurazione cambia quello che il plugin stampa in ogni
// pagina: li non basta svuotarne una.
MDI_Cache::ricomincia();
$GLOBALS['wp']['svuotamenti'] = array();
MDI_Api::salva_config( new WP_REST_Request( array( 'config' => $configurazione ) ) );

verifica( 'cambiando la configurazione si svuota tutto il sito', 1 === count( $svuotamenti( 'litespeed_purge_all' ) ) );

// E quando il plugin misura il sito non deve leggere la copia in cache: e com
// era prima delle correzioni, e i rilievi appena chiusi si riaprirebbero.
unset( $GLOBALS['wp']['transient']['mdi_h1_dal_tema'] );
$GLOBALS['wp']['richieste'] = array();
$GLOBALS['wp']['http']['*'] = array( 'response' => array( 'code' => 200 ), 'body' => '<html><body><h1>Articolo</h1></body></html>' );
MDI_Api::conteggi();

$letture = (array) $GLOBALS['wp']['richieste'];

verifica( 'per misurare il sito il plugin legge una pagina', 1 === count( $letture ), (string) count( $letture ) );
verifica(
	'ma chiedendo di saltare la cache',
	$letture && false !== strpos( (string) $letture[0]['url'], 'mdi-misura=' )
		&& 'no-cache, max-age=0' === ( $letture[0]['argomenti']['headers']['Cache-Control'] ?? '' ),
	wp_json_encode( $letture[0] ?? array() )
);
verifica(
	'senza perdere il resto delle intestazioni',
	$letture && 0 === strpos( (string) ( $letture[0]['argomenti']['headers']['User-Agent'] ?? '' ), 'MDI-SEO-GEO/' )
);

unset( $GLOBALS['wp']['transient']['mdi_h1_dal_tema'], $GLOBALS['wp']['http'] );

// --- I piani che il gestionale manda dopo ----------------------------------
// I file della cartella data/ sono l istantanea del momento in cui il plugin
// e stato generato. La mappa dei link interni cambia a ogni lettura di Search
// Console, e aggiornarla voleva dire rigenerare lo zip e ricaricarlo a mano.
echo "\nI piani aggiornati dal gestionale\n";

$esitoDati = MDI_Api::salva_dati(
	new WP_REST_Request(
		array(
			'nome' => 'internal-links',
			'dati' => array( 'agenzia di comunicazione a palermo' => 'https://esempio.it/' ),
		)
	)
);

verifica( 'il piano dei link interni si puo mandare al sito', ! empty( $esitoDati['ok'] ) && 1 === (int) $esitoDati['voci'], wp_json_encode( $esitoDati ) );
verifica( 'e da quel momento e quello che il plugin usa', array( 'agenzia di comunicazione a palermo' => 'https://esempio.it/' ) === mdi_seo_geo_data( 'internal-links' ), wp_json_encode( mdi_seo_geo_data( 'internal-links' ) ) );

// Un nome libero vorrebbe dire lasciar scrivere una opzione qualsiasi del
// sito da fuori.
$rifiutato = MDI_Api::salva_dati( new WP_REST_Request( array( 'nome' => 'active_plugins', 'dati' => array( 'x' ) ) ) );

verifica( 'un nome non previsto viene rifiutato', $rifiutato instanceof WP_Error, is_object( $rifiutato ) ? get_class( $rifiutato ) : gettype( $rifiutato ) );
verifica( 'senza scrivere niente', ! isset( $GLOBALS['wp']['opzioni']['mdi_seo_geo_dati_active_plugins'] ) );

// E mandare un piano nuovo svuota la cache: se no il sito continua a servire
// le pagine costruite sul piano di prima.
$GLOBALS['wp']['svuotamenti'] = array();
MDI_Cache::ricomincia();
MDI_Api::salva_dati( new WP_REST_Request( array( 'nome' => 'related', 'dati' => array() ) ) );

verifica( 'e il sito smette di servire le pagine costruite sul piano di prima', 1 === count( $svuotamenti( 'litespeed_purge_all' ) ) );

// Se la spinta sia accesa lo deve dire il sito. Tenersene un segno nel
// gestionale vuol dire scrivere «attiva» anche dopo che qualcuno ha rimesso
// il numero a zero da WordPress, ed e esattamente il difetto che ha fatto
// perdere mezza giornata con i link nella home.
$GLOBALS['wp']['opzioni']['mdi_seo_geo_dati_internal-links'] = array(
	'agenzia di comunicazione a palermo' => 'https://esempio.it/',
	'web agency a palermo'               => 'https://esempio.it/servizi/',
);
$GLOBALS['wp']['opzioni']['mdi_seo_geo_config'] = array_replace_recursive(
	(array) ( $GLOBALS['wp']['opzioni']['mdi_seo_geo_config'] ?? array() ),
	array( 'seo' => array( 'linkInterniPerArticolo' => 3 ) )
);

$statoSpinta = MDI_Api::stato();

verifica( 'il sito dice quante ricerche ha nella mappa', 2 === (int) ( $statoSpinta['spinta']['ricerche'] ?? 0 ), wp_json_encode( $statoSpinta['spinta'] ?? array() ) );
verifica( 'e quanti link per articolo sta mettendo', 3 === (int) ( $statoSpinta['spinta']['per_articolo'] ?? 0 ), wp_json_encode( $statoSpinta['spinta'] ?? array() ) );

// Spenta da WordPress: il gestionale lo deve vedere alla richiesta dopo.
$GLOBALS['wp']['opzioni']['mdi_seo_geo_config']['seo']['linkInterniPerArticolo'] = 0;

verifica( 'e quando qualcuno la spegne da WordPress si vede subito', 0 === (int) ( MDI_Api::stato()['spinta']['per_articolo'] ?? -1 ) );

// --- Un piano di un altro sito non deve finire in pagina -------------------
// La mappa delle meta si cerca per numero, e i numeri si ripetono da un sito
// all altro. Un piano di prova finito dentro allo zip ha fatto stampare alla
// home di un sito vero il title «Articolo di prova numero 10 sulla
// realizzazione siti web»: la home era la pagina numero 10, e la riga numero
// 10 del piano era di un altra installazione.
echo "\nUn piano di un altro sito\n";

$GLOBALS['wp']['opzioni']['mdi_seo_geo_dati_meta-map'] = array(
	array( 'id' => 700, 'slug' => 'articolo-di-prova-10', 'title' => 'Articolo di prova numero 10', 'description' => 'Testo di prova.', 'excerpt' => 'Prova.' ),
	array( 'id' => 701, 'slug' => 'la-home-vera', 'title' => 'Il title giusto', 'description' => 'La descrizione giusta.', 'excerpt' => 'Sintesi vera.' ),
);

stub_crea_post( 700, 'La home vera', '<p>Contenuto.</p>' );
$GLOBALS['wp']['post'][700]->post_name = 'la-home-vera';
stub_crea_post( 701, 'La home vera', '<p>Contenuto.</p>' );
$GLOBALS['wp']['post'][701]->post_name = 'la-home-vera';

$GLOBALS['wp']['singolo']      = 700;
$GLOBALS['wp']['tipo_singolo'] = 'page';

verifica(
	'una riga con lo slug di un altro contenuto non stampa niente',
	'Titolo del tema' === MDI_Meta::filter_title( 'Titolo del tema' ),
	MDI_Meta::filter_title( 'Titolo del tema' )
);

$GLOBALS['wp']['singolo'] = 701;

verifica(
	'mentre quella giusta si',
	'Il title giusto' === MDI_Meta::filter_title( 'Titolo del tema' ),
	MDI_Meta::filter_title( 'Titolo del tema' )
);

verifica( 'e nemmeno l estratto di un altro contenuto passa', '' === MDI_Meta::filter_excerpt( '', $GLOBALS['wp']['post'][700] ) );

$GLOBALS['wp']['singolo'] = 0;
unset( $GLOBALS['wp']['opzioni']['mdi_seo_geo_dati_meta-map'] );

// Stessa cosa per gli articoli correlati: un piano di un altro sito manderebbe
// i lettori fuori, sotto il titolo «Approfondimenti correlati».
// Il sito di prova e esempio.it: l altro dominio qui e quello del piano
// sbagliato, come lo era esempio.it sul sito vero.
$GLOBALS['wp']['opzioni']['mdi_seo_geo_dati_related'] = array(
	'702' => array(
		array( 'titolo' => 'Di un altro sito', 'url' => 'https://un-altro-sito.it/articolo-1/' ),
		array( 'titolo' => 'Di questo sito', 'url' => 'https://esempio.it/x/' ),
	),
);

stub_crea_post( 702, 'Un articolo', '<p>Corpo.</p>' );
$GLOBALS['wp']['singolo']      = 702;
$GLOBALS['wp']['tipo_singolo'] = 'post';

$correlati = MDI_Links::append_related( '<p>Corpo.</p>' );

verifica( 'i correlati di un altro dominio non finiscono in pagina', false === strpos( $correlati, 'un-altro-sito.it' ), $correlati );
verifica( 'quelli di casa si', false !== strpos( $correlati, 'esempio.it/x/' ), $correlati );

$GLOBALS['wp']['singolo'] = 0;
unset( $GLOBALS['wp']['opzioni']['mdi_seo_geo_dati_related'] );

echo "\n" . ( $errori ? "✖ $errori verifiche fallite\n\n" : "✔ tutte le verifiche superate\n\n" );

exit( $errori ? 1 : 0 );
