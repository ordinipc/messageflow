<?php
/**
 * Front controller dell applicazione web.
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

session_start();

$cfg = require __DIR__ . '/../config.php';
$db  = new Db( $cfg['database'] );

/**
 * Scorciatoia per l escape dell output.
 *
 * @param mixed $v Valore.
 * @return string
 */
function e( $v ) {
	return htmlspecialchars( (string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Numero formattato all italiana.
 *
 * @param int|float $n Numero.
 * @return string
 */
function num( $n ) {
	return number_format( (float) $n, 0, ',', '.' );
}

/**
 * Token anti-CSRF di sessione.
 *
 * @return string
 */
function token() {
	if ( empty( $_SESSION['token'] ) ) {
		$_SESSION['token'] = bin2hex( random_bytes( 16 ) );
	}

	return $_SESSION['token'];
}

/**
 * Rende una vista dentro il layout.
 *
 * @param string $vista Nome della vista.
 * @param array  $dati  Variabili disponibili nella vista.
 * @return void
 */
function vista( $vista, array $dati = array() ) {
	extract( $dati, EXTR_SKIP );
	$percorso_vista = __DIR__ . '/../views/' . $vista . '.php';

	ob_start();
	require $percorso_vista;
	$contenuto = ob_get_clean();

	require __DIR__ . '/../views/layout.php';
}

$pagina = $_GET['p'] ?? 'home';

// ---------------------------------------------------------------- Nuovo audit
if ( 'analizza' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido: ricarica la pagina e riprova.' );
	}

	$caricato = $_FILES['export'] ?? null;

	if ( ! $caricato || UPLOAD_ERR_OK !== $caricato['error'] ) {
		$errore = 'Caricamento non riuscito (codice ' . ( $caricato['error'] ?? '?' ) . '). Verifica upload_max_filesize e post_max_size in php.ini: un export WordPress supera spesso i 10 MB.';
		vista( 'nuovo', compact( 'errore' ) + array( 'titolo' => 'Nuovo audit', 'cfg' => $cfg ) );
		exit;
	}

	if ( ! preg_match( '/\.xml$/i', $caricato['name'] ) ) {
		$errore = 'Il file deve essere l esportazione XML di WordPress (Strumenti → Esporta → Tutti i contenuti).';
		vista( 'nuovo', compact( 'errore' ) + array( 'titolo' => 'Nuovo audit', 'cfg' => $cfg ) );
		exit;
	}

	$destinazione = __DIR__ . '/../storage/import/' . date( 'Ymd-His' ) . '-' . preg_replace( '/[^A-Za-z0-9._-]/', '', $caricato['name'] );

	if ( ! is_dir( dirname( $destinazione ) ) ) {
		mkdir( dirname( $destinazione ), 0775, true );
	}

	move_uploaded_file( $caricato['tmp_name'], $destinazione );

	set_time_limit( 300 );

	$site   = new Site( WxrParser::parse( $destinazione ) );
	$audit  = Audit::esegui( $site );
	$triage = Triage::esegui( $site, $cfg );
	$meta   = Meta::piano( $site, $cfg );
	$link   = InternalLinks::piano( $site, $cfg );

	$auditId = Audit::salva( $db, $site, $audit, basename( $caricato['name'] ) );
	Triage::salva( $db, $auditId, $triage );
	Meta::salva( $db, $auditId, $meta );
	InternalLinks::salva( $db, $auditId, $link['piano'] );

	Export::tutto(
		array(
			'site'     => $site,
			'cfg'      => $cfg,
			'audit'    => $audit,
			'triage'   => $triage,
			'meta'     => $meta,
			'link'     => $link,
			'cartella' => __DIR__ . '/../storage/export/audit-' . $auditId,
		)
	);

	header( 'Location: ?p=audit&id=' . $auditId );
	exit;
}

// ------------------------------------------------------------------- Download
if ( 'download' === $pagina ) {
	$id   = (int) ( $_GET['id'] ?? 0 );
	$nome = basename( (string) ( $_GET['f'] ?? '' ) );
	$sub  = preg_replace( '#[^a-z0-9/-]#i', '', (string) ( $_GET['d'] ?? '' ) );
	$base = realpath( __DIR__ . '/../storage/export/audit-' . $id );
	$file = realpath( $base . '/' . ( $sub ? $sub . '/' : '' ) . $nome );

	// Il percorso richiesto deve restare dentro la cartella dell audit.
	if ( ! $base || ! $file || 0 !== strpos( $file, $base ) || ! is_file( $file ) ) {
		http_response_code( 404 );
		exit( 'File non disponibile.' );
	}

	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Disposition: attachment; filename="' . $nome . '"' );
	header( 'Content-Length: ' . filesize( $file ) );
	readfile( $file );
	exit;
}

// ------------------------------------------------------------- Elimina audit
if ( 'elimina' === $pagina && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! hash_equals( token(), $_POST['token'] ?? '' ) ) {
		http_response_code( 400 );
		exit( 'Token di sessione non valido.' );
	}

	$id = (int) ( $_POST['id'] ?? 0 );

	$db->run( 'DELETE FROM occorrenza WHERE rilievo_id IN (SELECT id FROM rilievo WHERE audit_id = ?)', array( $id ) );
	foreach ( array( 'rilievo', 'documento', 'area', 'triage', 'meta_piano', 'link_piano' ) as $tabella ) {
		$db->run( "DELETE FROM $tabella WHERE audit_id = ?", array( $id ) );
	}
	$db->run( 'DELETE FROM audit WHERE id = ?', array( $id ) );

	header( 'Location: ?p=home' );
	exit;
}

// ---------------------------------------------------------------- Pagine HTML
switch ( $pagina ) {

	case 'nuovo':
		vista( 'nuovo', array( 'titolo' => 'Nuovo audit', 'cfg' => $cfg, 'errore' => null ) );
		break;

	case 'audit':
		$id    = (int) ( $_GET['id'] ?? 0 );
		$audit = $db->one( 'SELECT * FROM audit WHERE id = ?', array( $id ) );

		if ( ! $audit ) {
			http_response_code( 404 );
			exit( 'Audit non trovato.' );
		}

		vista(
			'audit',
			array(
				'titolo'    => 'Audit ' . $audit['sito_nome'],
				'audit'     => $audit,
				'aree'      => $db->all( 'SELECT * FROM area WHERE audit_id = ? ORDER BY punteggio ASC', array( $id ) ),
				'rilievi'   => $db->all(
					"SELECT * FROM rilievo WHERE audit_id = ?
					 ORDER BY CASE gravita WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, occorrenze DESC",
					array( $id )
				),
				'conteggi'  => $db->all( 'SELECT categoria, COUNT(*) n FROM triage WHERE audit_id = ? GROUP BY categoria', array( $id ) ),
				'db'        => $db,
				'cfg'       => $cfg,
			)
		);
		break;

	case 'triage':
		$id       = (int) ( $_GET['id'] ?? 0 );
		$filtro   = preg_replace( '/[^a-z]/', '', (string) ( $_GET['c'] ?? '' ) );
		$audit    = $db->one( 'SELECT * FROM audit WHERE id = ?', array( $id ) );

		if ( ! $audit ) {
			http_response_code( 404 );
			exit( 'Audit non trovato.' );
		}

		$sql    = 'SELECT t.*, d.titolo, d.url, d.parole, d.slug FROM triage t JOIN documento d ON d.id = t.documento_id WHERE t.audit_id = ?';
		$params = array( $id );

		if ( in_array( $filtro, array( 'eliminare', 'accorpare', 'riscrivere', 'mantenere' ), true ) ) {
			$sql     .= ' AND t.categoria = ?';
			$params[] = $filtro;
		}

		$sql .= " ORDER BY CASE t.categoria WHEN 'eliminare' THEN 0 WHEN 'accorpare' THEN 1 WHEN 'riscrivere' THEN 2 ELSE 3 END, t.qualita ASC";

		vista(
			'triage',
			array(
				'titolo'   => 'Triage editoriale',
				'audit'    => $audit,
				'articoli' => $db->all( $sql, $params ),
				'conteggi' => $db->all( 'SELECT categoria, COUNT(*) n FROM triage WHERE audit_id = ? GROUP BY categoria', array( $id ) ),
				'filtro'   => $filtro,
			)
		);
		break;

	case 'rilievo':
		$id      = (int) ( $_GET['id'] ?? 0 );
		$rilievo = $db->one( 'SELECT * FROM rilievo WHERE id = ?', array( $id ) );

		if ( ! $rilievo ) {
			http_response_code( 404 );
			exit( 'Rilievo non trovato.' );
		}

		vista(
			'rilievo',
			array(
				'titolo'      => $rilievo['regola'],
				'rilievo'     => $rilievo,
				'occorrenze'  => $db->all( 'SELECT * FROM occorrenza WHERE rilievo_id = ? LIMIT 500', array( $id ) ),
				'audit'       => $db->one( 'SELECT * FROM audit WHERE id = ?', array( $rilievo['audit_id'] ) ),
			)
		);
		break;

	default:
		vista(
			'home',
			array(
				'titolo' => 'Audit SEO e GEO',
				'audit'  => $db->all( 'SELECT * FROM audit ORDER BY id DESC LIMIT 50' ),
				'cfg'    => $cfg,
			)
		);
}
