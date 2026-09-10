<?php
/**
 * Generazione delle bozze con Gemini, da riga di comando.
 *
 * Uso:
 *   php cli/riscrivi.php <id-audit> [--limite=5] [--categoria=riscrivere]
 *                        [--stima] [--rigenera]
 *
 * @package SeoGeoAudit
 */

require_once __DIR__ . '/../src/Autoload.php';

use SeoGeo\Ai\Gemini;
use SeoGeo\Ai\Rewriter;
use SeoGeo\Db;

$cfg = require __DIR__ . '/../config.php';

$auditId = (int) ( $argv[1] ?? 0 );
$opzioni = array(
	'limite'    => null,
	'categorie' => array( 'riscrivere', 'accorpare' ),
	'rigenera'  => false,
);
$soloStima = false;

foreach ( array_slice( $argv, 2 ) as $argomento ) {
	if ( preg_match( '/^--limite=(\d+)$/', $argomento, $m ) ) {
		$opzioni['limite'] = (int) $m[1];
	} elseif ( preg_match( '/^--categoria=([a-z,]+)$/', $argomento, $m ) ) {
		$opzioni['categorie'] = explode( ',', $m[1] );
	} elseif ( '--rigenera' === $argomento ) {
		$opzioni['rigenera'] = true;
	} elseif ( '--stima' === $argomento ) {
		$soloStima = true;
	}
}

if ( ! $auditId ) {
	fwrite( STDERR, "Uso: php cli/riscrivi.php <id-audit> [--limite=5] [--categoria=riscrivere] [--stima] [--rigenera]\n" );
	fwrite( STDERR, "Trovi l id nella colonna a sinistra dell elenco audit, oppure con: php cli/riscrivi.php 0\n\n" );

	try {
		$db = new Db( $cfg['database'] );
		foreach ( $db->all( 'SELECT id, sito_nome, creato_il FROM audit ORDER BY id DESC LIMIT 10' ) as $a ) {
			fwrite( STDERR, sprintf( "  #%d  %s  (%s)\n", $a['id'], $a['sito_nome'], substr( $a['creato_il'], 0, 16 ) ) );
		}
	} catch ( Throwable $e ) {
		fwrite( STDERR, 'Database non raggiungibile: ' . $e->getMessage() . "\n" );
	}

	exit( 1 );
}

$db     = new Db( $cfg['database'] );
$gemini = new Gemini( $cfg['ai'] );

$candidati = Rewriter::candidati( $db, $auditId, $opzioni );
$stima     = Rewriter::stima( $candidati, $cfg['ai'] );

printf(
	"\n▶ Audit #%d — %d articoli da riscrivere%s\n",
	$auditId,
	$stima['articoli'],
	$opzioni['limite'] ? ' (limite ' . $opzioni['limite'] . ')' : ''
);
printf(
	"  stima: %s token in ingresso, %s in uscita → circa %s €\n",
	number_format( $stima['token_in'], 0, ',', '.' ),
	number_format( $stima['token_out'], 0, ',', '.' ),
	number_format( $stima['costo_stimato'], 2, ',', '.' )
);
printf( "  modello: %s\n", $cfg['ai']['modello'] );

if ( $soloStima ) {
	echo "\n(solo stima: nessuna chiamata effettuata)\n\n";
	exit( 0 );
}

if ( ! $gemini->pronto() ) {
	fwrite( STDERR, "\n✖ Chiave API Gemini mancante.\n" );
	fwrite( STDERR, "  Ottienila su https://aistudio.google.com/apikey e mettila in config.php (ai → chiave)\n" );
	fwrite( STDERR, "  oppure esporta la variabile: export GEMINI_API_KEY=la-tua-chiave\n\n" );
	exit( 1 );
}

if ( 0 === $stima['articoli'] ) {
	echo "\nNessun articolo da elaborare: sono già tutti generati (usa --rigenera per rifarli).\n\n";
	exit( 0 );
}

echo "\n";

$esito = Rewriter::esegui(
	$db,
	$gemini,
	$auditId,
	$cfg,
	$opzioni + array(
		'su_progresso' => static function ( $articolo, $fatte, $fallite, $totale ) {
			printf( "  [%d/%d] %s\n", $fatte + $fallite, $totale, mb_substr( $articolo['titolo'], 0, 70 ) );
		},
	)
);

printf(
	"\n✔ %d bozze generate, %d errori — %s token in, %s token out, circa %s €\n",
	$esito['generate'],
	$esito['fallite'],
	number_format( $esito['consumo']['token_in'], 0, ',', '.' ),
	number_format( $esito['consumo']['token_out'], 0, ',', '.' ),
	number_format( $esito['consumo']['costo_stimato'], 2, ',', '.' )
);
printf( "  file in %s\n\n", $esito['cartella'] );
echo "Le bozze NON sono pubblicate: vanno riviste e ripulite dai segnaposto [DA VERIFICARE].\n\n";
