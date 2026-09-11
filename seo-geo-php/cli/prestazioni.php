<?php
/**
 * Aggiornamento dei dati di Search Console da riga di comando.
 *
 * Uso:  php cli/prestazioni.php [giorni]
 *
 * Pensato per il cron: una volta al giorno bastano e avanzano, i dati di
 * Google si consolidano con due o tre giorni di ritardo.
 *
 * @package SeoGeoAudit
 */

require_once __DIR__ . '/../src/Autoload.php';

use SeoGeo\Db;
use SeoGeo\Impostazioni;
use SeoGeo\Search\Prestazioni;

$cfg = Impostazioni::carica( require __DIR__ . '/../config.php' );

if ( ! Prestazioni::configurata( $cfg ) ) {
	fwrite( STDERR, "Search Console non è configurata: apri le Impostazioni del gestionale e incolla la chiave dell account di servizio.\n" );
	exit( 1 );
}

if ( isset( $argv[1] ) ) {
	$cfg['google']['giorni'] = max( 7, min( 180, (int) $argv[1] ) );
}

$db      = new Db( $cfg['database'] );
$ultimo  = $db->one( 'SELECT id FROM audit ORDER BY id DESC LIMIT 1' );
$documenti = $ultimo
	? $db->all( "SELECT url, titolo, tipo, pubblicato FROM documento WHERE audit_id = ? AND stato = 'publish'", array( $ultimo['id'] ) )
	: array();

try {
	$esito = Prestazioni::esegui(
		$db,
		$cfg,
		$documenti,
		static function ( $messaggio ) {
			echo '  ', $messaggio, "\n";
		}
	);
} catch ( Throwable $e ) {
	fwrite( STDERR, 'Errore: ' . $e->getMessage() . "\n" );
	exit( 1 );
}

printf(
	"\nPeriodo %s → %s: %d clic, %d impression su %d pagine.\n",
	$esito['periodo']['da'],
	$esito['periodo']['a'],
	$esito['totali']['clic'],
	$esito['totali']['impression'],
	$esito['totali']['pagine']
);

$per_tipo = array();

foreach ( $esito['segnali'] as $segnale ) {
	$per_tipo[ $segnale['titolo'] ] = ( $per_tipo[ $segnale['titolo'] ] ?? 0 ) + 1;
}

echo "\nDa fare:\n";

foreach ( $per_tipo as $titolo => $quanti ) {
	printf( "  %-42s %d\n", $titolo, $quanti );
}

echo "\nPrime dieci per convenienza:\n";

foreach ( array_slice( $esito['segnali'], 0, 10 ) as $segnale ) {
	printf( "  · %s\n    %s\n", $segnale['url'], $segnale['spiegazione'] );
}

echo "\n";
