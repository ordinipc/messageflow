<?php
/**
 * Finto Search Console per il collaudo: stesse forme di risposta di Google.
 *
 * @package SeoGeoAudit
 */

$percorso = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$corpo    = json_decode( (string) file_get_contents( 'php://input' ), true );

header( 'Content-Type: application/json' );

// Il token deve arrivare: senza, Google risponde 401.
if ( 0 !== stripos( $_SERVER['HTTP_AUTHORIZATION'] ?? '', 'Bearer ' ) ) {
	http_response_code( 401 );
	echo json_encode( array( 'error' => array( 'code' => 401, 'message' => 'Login Required.' ) ) );
	exit;
}

if ( false !== strpos( $percorso, '/sites' ) && false === strpos( $percorso, 'searchAnalytics' ) && false === strpos( $percorso, 'sitemaps' ) ) {
	echo json_encode(
		array(
			'siteEntry' => array(
				array( 'siteUrl' => 'sc-domain:esempio.it', 'permissionLevel' => 'siteFullUser' ),
				array( 'siteUrl' => 'https://altro.esempio.it/', 'permissionLevel' => 'siteRestrictedUser' ),
			),
		)
	);
	exit;
}

if ( false !== strpos( $percorso, 'searchAnalytics/query' ) ) {
	$dimensioni = $corpo['dimensions'] ?? array();
	$inizio     = (int) ( $corpo['startRow'] ?? 0 );
	$limite     = (int) ( $corpo['rowLimit'] ?? 1000 );

	// Con rowLimit piccolo si verifica anche la paginazione.
	$tutte = array();

	if ( array( 'page' ) === $dimensioni ) {
		$tutte = array(
			array( 'keys' => array( 'https://esempio.it/articolo-101/' ), 'clicks' => 40, 'impressions' => 900, 'ctr' => 0.044, 'position' => 6.2 ),
			array( 'keys' => array( 'https://esempio.it/articolo-1/' ), 'clicks' => 2, 'impressions' => 500, 'ctr' => 0.004, 'position' => 12.8 ),
			array( 'keys' => array( 'https://esempio.it/articolo-9/' ), 'clicks' => 0, 'impressions' => 60, 'ctr' => 0.0, 'position' => 31.0 ),
		);
	} else {
		$tutte = array(
			array( 'keys' => array( 'siti web palermo', 'https://esempio.it/articolo-1/' ), 'clicks' => 2, 'impressions' => 500, 'ctr' => 0.004, 'position' => 12.8 ),
			array( 'keys' => array( 'siti web palermo', 'https://esempio.it/articolo-101/' ), 'clicks' => 5, 'impressions' => 400, 'ctr' => 0.0125, 'position' => 14.0 ),
			array( 'keys' => array( 'agenzia web palermo', 'https://esempio.it/articolo-101/' ), 'clicks' => 3, 'impressions' => 800, 'ctr' => 0.00375, 'position' => 3.4 ),
			array( 'keys' => array( 'niente', 'https://esempio.it/articolo-9/' ), 'clicks' => 0, 'impressions' => 4, 'ctr' => 0.0, 'position' => 44.0 ),
		);
	}

	$pagina = array_slice( $tutte, $inizio, $limite );

	echo json_encode( $pagina ? array( 'rows' => $pagina ) : array() );
	exit;
}

if ( false !== strpos( $percorso, 'sitemaps' ) ) {
	echo json_encode(
		array(
			'sitemap' => array(
				array( 'path' => 'https://esempio.it/sitemap_index.xml', 'lastSubmitted' => '2026-09-01T10:00:00Z', 'errors' => 0, 'warnings' => 2 ),
			),
		)
	);
	exit;
}

if ( false !== strpos( $percorso, 'urlInspection' ) ) {
	echo json_encode(
		array(
			'inspectionResult' => array(
				'indexStatusResult' => array(
					'verdict'         => 'NEUTRAL',
					'coverageState'   => 'Scansionata, attualmente non indicizzata',
					'lastCrawlTime'   => '2026-08-30T04:12:00Z',
					'googleCanonical' => false !== strpos( (string) ( $corpo['inspectionUrl'] ?? '' ), 'video-2' )
						? 'https://esempio.it/perche-ogni-attivita-dovrebbe-pubblicare-video/'
						: 'https://esempio.it/articolo-9/',
					'robotsTxtState'  => 'ALLOWED',
				),
			),
		)
	);
	exit;
}

http_response_code( 404 );
echo json_encode( array( 'error' => array( 'code' => 404, 'message' => 'Not found: ' . $percorso ) ) );
