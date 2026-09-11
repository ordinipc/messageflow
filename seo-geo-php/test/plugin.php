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

echo "\n" . ( $errori ? "✖ $errori verifiche fallite\n\n" : "✔ tutte le verifiche superate\n\n" );

exit( $errori ? 1 : 0 );
