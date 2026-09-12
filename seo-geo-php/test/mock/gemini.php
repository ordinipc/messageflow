<?php
/**
 * Finto servizio Gemini per il collaudo.
 *
 * Riproduce i tre casi che contano: risposta completa, risposta tagliata a
 * metà per mancanza di spazio (con finishReason MAX_TOKENS, come fa Google) e
 * risposta che non è JSON.
 *
 * @package SeoGeoAudit
 */

$richiesta = json_decode( (string) file_get_contents( 'php://input' ), true );
$spazio    = (int) ( $richiesta['generationConfig']['maxOutputTokens'] ?? 0 );
$modo      = $_GET['modo'] ?? 'completo';

header( 'Content-Type: application/json' );

if ( '' === (string) ( $_SERVER['HTTP_X_GOOG_API_KEY'] ?? '' ) ) {
	http_response_code( 400 );
	echo json_encode( array( 'error' => array( 'code' => 400, 'message' => 'API key not valid.' ) ) );
	exit;
}

// Ricerca su Google: la risposta porta groundingMetadata con le fonti, ed e
// in chiaro perche lo strumento non si combina con responseMimeType.
if ( isset( $richiesta['tools'] ) ) {
	$senza_fonti = 'senza-fonti' === $modo;
	$niente      = 'non-trovato' === $modo;

	echo json_encode(
		array(
			'candidates' => array(
				array(
					'content'  => array(
						'parts' => array(
							array(
								'text' => $niente
									? "VALORE: \nTIPO: non_trovato\nNOTA: nessuna fonte affidabile."
									: "VALORE: da 1.500 a 4.000 euro\nTIPO: mercato\nNOTA: costo medio di un e-commerce per PMI in Italia.",
							),
						),
					),
					'finishReason' => 'STOP',
					'groundingMetadata' => $senza_fonti ? array( 'webSearchQueries' => array( 'costo e-commerce pmi italia' ) ) : array(
						'webSearchQueries' => array( 'costo e-commerce pmi italia 2026' ),
						'groundingChunks'  => array(
							array( 'web' => array( 'uri' => 'https://esempio-fonte.it/costi-ecommerce', 'title' => 'Quanto costa un e-commerce' ) ),
							array( 'web' => array( 'uri' => 'https://altra-fonte.it/listini', 'title' => 'Listini 2026' ) ),
							// Ripetuta di proposito: non deve comparire due volte.
							array( 'web' => array( 'uri' => 'https://esempio-fonte.it/costi-ecommerce', 'title' => 'Quanto costa un e-commerce' ) ),
						),
					),
				),
			),
			'usageMetadata' => array( 'promptTokenCount' => 400, 'candidatesTokenCount' => 90 ),
		)
	);
	exit;
}

$completo = json_encode(
	array(
		'titolo'           => 'Agenzia di Marketing a Palermo: Crescita su Misura per PMI',
		'meta_title'       => 'Agenzia Marketing Palermo: Crescita e Visibilità',
		'meta_description' => 'Strategie di marketing su misura per le PMI di Palermo, dai canali social alla pubblicità locale. Parliamone insieme.',
		'in_breve'         => 'Sintesi di prova.',
		'corpo_html'       => '<h2>Sezione</h2><p>Testo riscritto.</p>',
		'faq'              => array( array( 'domanda' => 'Quanto costa?', 'risposta' => 'Dipende.' ) ),
		'da_verificare'    => array(),
		'note'             => '',
	),
	JSON_UNESCAPED_UNICODE
);

// "tronca" taglia finché lo spazio richiesto non è abbastanza: al secondo
// tentativo, con il budget raddoppiato, la risposta arriva intera.
if ( 'tronca' === $modo && $spazio < 16000 ) {
	echo json_encode(
		array(
			'candidates'    => array(
				array(
					'content'      => array( 'parts' => array( array( 'text' => mb_substr( $completo, 0, 120 ) ) ) ),
					'finishReason' => 'MAX_TOKENS',
				),
			),
			'usageMetadata' => array( 'promptTokenCount' => 900, 'candidatesTokenCount' => $spazio, 'thoughtsTokenCount' => (int) ( $spazio * 0.8 ) ),
		)
	);
	exit;
}

// "sempre-tronca" non cede mai: serve a verificare il messaggio finale.
if ( 'sempre-tronca' === $modo ) {
	echo json_encode(
		array(
			'candidates'    => array(
				array(
					'content'      => array( 'parts' => array( array( 'text' => mb_substr( $completo, 0, 120 ) ) ) ),
					'finishReason' => 'MAX_TOKENS',
				),
			),
			'usageMetadata' => array( 'promptTokenCount' => 900, 'candidatesTokenCount' => $spazio, 'thoughtsTokenCount' => (int) ( $spazio * 0.8 ) ),
		)
	);
	exit;
}

if ( 'non-json' === $modo ) {
	echo json_encode(
		array(
			'candidates'    => array( array( 'content' => array( 'parts' => array( array( 'text' => 'Mi dispiace, non posso aiutarti con questo.' ) ) ), 'finishReason' => 'STOP' ) ),
			'usageMetadata' => array( 'promptTokenCount' => 100, 'candidatesTokenCount' => 20 ),
		)
	);
	exit;
}

echo json_encode(
	array(
		'candidates'    => array( array( 'content' => array( 'parts' => array( array( 'text' => $completo ) ) ), 'finishReason' => 'STOP' ) ),
		'usageMetadata' => array( 'promptTokenCount' => 900, 'candidatesTokenCount' => 700 ),
	)
);
