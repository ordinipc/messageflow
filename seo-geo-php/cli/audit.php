<?php
/**
 * Interfaccia a riga di comando.
 *
 * Uso:  php cli/audit.php <export.xml> [cartella-output]
 *
 * @package SeoGeoAudit
 */

require_once __DIR__ . '/../src/Autoload.php';

use SeoGeo\Audit;
use SeoGeo\Db;
use SeoGeo\Export;
use SeoGeo\Fix\InternalLinks;
use SeoGeo\Fix\Meta;
use SeoGeo\Site;
use SeoGeo\Triage;
use SeoGeo\WxrParser;

$cfg = require __DIR__ . '/../config.php';

$file = $argv[1] ?? '';

if ( '' === $file || ! is_readable( $file ) ) {
	fwrite( STDERR, "Uso: php cli/audit.php <export-wordpress.xml> [cartella-output]\n" );
	exit( 1 );
}

// La cartella di destinazione si decide dopo il salvataggio: prende il numero
// dell audit, così l interfaccia web trova i file dove si aspetta.
$out    = $argv[2] ?? null;
$inizio = microtime( true );

echo "\n▶ Lettura di " . basename( $file ) . "…\n";
$site = new Site( WxrParser::parse( $file ) );
printf( "  %d articoli · %d pagine · %d media · dominio %s\n", count( $site->articoli ), count( $site->pagine ), count( $site->allegati ), $site->host );

echo "\n▶ Audit SEO e GEO…\n";
$audit = Audit::esegui( $site );
printf( "  punteggio %d/100 — %s problemi su %d controlli non superati\n", $audit['globale'], number_format( $audit['occorrenze'], 0, ',', '.' ), count( $audit['rilievi'] ) );

$ordinate = $audit['punteggi'];
usort( $ordinate, static fn( $a, $b ) => $a['punteggio'] <=> $b['punteggio'] );
foreach ( array_slice( $ordinate, 0, 4 ) as $a ) {
	printf( "    %3d/100  %s\n", $a['punteggio'], $a['etichetta'] );
}

echo "\n▶ Triage editoriale…\n";
$triage = Triage::esegui( $site, $cfg );
foreach ( $triage['conteggi'] as $categoria => $n ) {
	printf( "  %4d  %s\n", $n, $categoria );
}

echo "\n▶ Generazione delle correzioni…\n";
$meta = Meta::piano( $site, $cfg );
$link = InternalLinks::piano( $site, $cfg );
printf(
	"  meta: %d title, %d description, %d slug\n",
	count( array_filter( $meta, static fn( $m ) => $m['title_cambiato'] ) ),
	count( array_filter( $meta, static fn( $m ) => $m['description_cambiata'] ) ),
	count( array_filter( $meta, static fn( $m ) => $m['slug_cambiato'] ) )
);
printf( "  link interni: %d proposti — pagine orfane da %d a %d\n", $link['statistiche']['link_proposti'], $link['statistiche']['orfani_prima'], $link['statistiche']['orfani_dopo'] );

echo "\n▶ Salvataggio su database…\n";
$db      = new Db( $cfg['database'] );
$auditId = Audit::salva( $db, $site, $audit, basename( $file ) );
Triage::salva( $db, $auditId, $triage );
Meta::salva( $db, $auditId, $meta );
InternalLinks::salva( $db, $auditId, $link['piano'] );
printf( "  audit #%d salvato (%s)\n", $auditId, $cfg['database']['driver'] );

if ( null === $out ) {
	$out = __DIR__ . '/../storage/export/audit-' . $auditId;
}

$file_scritti = Export::tutto(
	array(
		'site'     => $site,
		'cfg'      => $cfg,
		'audit'    => $audit,
		'triage'   => $triage,
		'meta'     => $meta,
		'link'     => $link,
		'cartella' => $out,
	)
);

printf( "\n✔ Completato in %.1fs — %d file in %s\n\n", microtime( true ) - $inizio, count( $file_scritti ), realpath( $out ) );

$mancanti = array();
foreach ( array( 'telefono', 'partitaIva' ) as $campo ) {
	if ( 0 === stripos( (string) $cfg['azienda'][ $campo ], 'DA_COMPILARE' ) ) {
		$mancanti[] = $campo;
	}
}

if ( $mancanti ) {
	echo "⚠ Dati aziendali ancora da compilare in config.php: " . implode( ', ', $mancanti ) . "\n";
	echo "  Senza questi valori lo schema locale resta incompleto.\n\n";
}
