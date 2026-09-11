<?php
/**
 * Pilota automatico da riga di comando.
 *
 * Uso:
 *   php cli/pilota.php <id-audit> --avvia [--pagine] [--immagini] [--pubblica] [--cestina]
 *   php cli/pilota.php <id-audit>                 riprende una coda già avviata
 *   php cli/pilota.php <id-audit> --minuti=4      lavora per 4 minuti e si ferma (per il cron)
 *   php cli/pilota.php <id-audit> --stato         solo il riepilogo
 *
 * @package SeoGeoAudit
 */

require_once __DIR__ . '/../src/Autoload.php';

use SeoGeo\Coda;
use SeoGeo\Db;
use SeoGeo\Impostazioni;

$cfg     = Impostazioni::carica( require __DIR__ . '/../config.php' );
$auditId = (int) ( $argv[1] ?? 0 );

$avvia    = in_array( '--avvia', $argv, true );
$soloStato = in_array( '--stato', $argv, true );
$opzioni  = array(
	'immagini' => in_array( '--immagini', $argv, true ),
	'pubblica' => in_array( '--pubblica', $argv, true ),
	'cestina'  => in_array( '--cestina', $argv, true ),
	'pagine'   => in_array( '--pagine', $argv, true ),
);

$minuti = 0;

foreach ( $argv as $argomento ) {
	if ( preg_match( '/^--minuti=(\d+)$/', $argomento, $m ) ) {
		$minuti = (int) $m[1];
	}
}

if ( ! $auditId ) {
	fwrite( STDERR, "Uso: php cli/pilota.php <id-audit> [--avvia] [--immagini] [--pubblica] [--cestina] [--minuti=N]\n\n" );
	exit( 1 );
}

$db = new Db( $cfg['database'] );

if ( $soloStato ) {
	print_r( Coda::stato( $db, $auditId ) );
	exit( 0 );
}

if ( $avvia ) {
	$piano = Coda::prepara( $db, $auditId, $cfg, $opzioni );

	printf( "\n▶ Piano preparato: %d operazioni\n", $piano['totale'] );

	foreach ( $piano['per_tipo'] as $tipo => $quante ) {
		printf( "  %4d  %s\n", $quante, Coda::TIPI[ $tipo ] ?? $tipo );
	}

	if ( ! $piano['ai'] ) {
		echo "\n  (chiave Gemini assente: riscritture e immagini non sono in programma)\n";
	}

	echo "\n";
}

$stato = Coda::stato( $db, $auditId );

if ( 0 === $stato['attesa'] ) {
	echo "Nessuna operazione in coda. Usa --avvia per prepararne una.\n\n";
	exit( 0 );
}

printf( "▶ Esecuzione di %d operazioni%s\n\n", $stato['attesa'], $minuti ? " (per $minuti minuti)" : '' );

$scadenza = $minuti ? time() + $minuti * 60 : null;

do {
	$giro = Coda::esegui( $db, $auditId, $cfg, 30 );

	foreach ( $giro['eseguite'] as $operazione ) {
		$segno = 'fatto' === $operazione['stato'] ? '✔' : ( 'errore' === $operazione['stato'] ? '✖' : '–' );
		printf( "  %s %s — %s\n", $segno, mb_substr( $operazione['etichetta'], 0, 62 ), mb_substr( $operazione['messaggio'], 0, 60 ) );
	}

	$stato = $giro['stato'];

	printf( "  … %d%% (%s di %s)\n", $stato['percentuale'], number_format( $stato['completate'], 0, ',', '.' ), number_format( $stato['totale'], 0, ',', '.' ) );

	if ( $scadenza && time() > $scadenza ) {
		printf( "\n⏸ Tempo esaurito: restano %d operazioni, riprenderanno al prossimo giro.\n\n", $stato['attesa'] );
		exit( 0 );
	}
} while ( $stato['attesa'] > 0 );

printf(
	"\n✔ Completato: %d eseguite, %d errori, %d saltate — circa %s €\n\n",
	$stato['fatto'],
	$stato['errore'],
	$stato['saltato'],
	number_format( $giro['consumo']['costo_stimato'] ?? 0, 2, ',', '.' )
);

if ( $stato['errore'] ) {
	echo "Operazioni con errore:\n";

	foreach ( $db->all( "SELECT etichetta, messaggio FROM coda WHERE audit_id = ? AND stato = 'errore' LIMIT 10", array( $auditId ) ) as $riga ) {
		printf( "  ✖ %s — %s\n", mb_substr( $riga['etichetta'], 0, 60 ), mb_substr( $riga['messaggio'], 0, 80 ) );
	}

	echo "\n";
}
