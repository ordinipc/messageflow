<?php
/** Libreria immagini: caricamento, ridimensionamento, elenco. */

defined( 'PC_AVVIO' ) || exit;

function media_tipi_ammessi() {
	return array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
		'image/gif'  => 'gif',
		'image/svg+xml' => 'svg',
	);
}

function media_tutti() {
	return db_righe( 'SELECT * FROM ' . db_tab( 'media' ) . ' ORDER BY caricata DESC, nome ASC' );
}

/**
 * Il testo alternativo scritto in libreria per un file.
 *
 * Vuoto se l'immagine non è in archivio o se nessuno l'ha scritto: chi
 * chiama decide cosa metterci al posto suo.
 */
function media_alt( $file ) {
	$file = trim( (string) $file );
	if ( '' === $file ) {
		return '';
	}
	$r = db_riga( 'SELECT alt FROM ' . db_tab( 'media' ) . ' WHERE file = ?', array( $file ) );
	return $r ? trim( (string) $r['alt'] ) : '';
}

function media_per_id( $id ) {
	return db_riga( 'SELECT * FROM ' . db_tab( 'media' ) . ' WHERE id = ?', array( $id ) );
}

/**
 * Gestisce un file caricato.
 * Restituisce array( 'ok' => bool, 'messaggio' => string, 'file' => string ).
 */
function media_carica( $file ) {
	if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return array( 'ok' => false, 'messaggio' => 'Nessun file ricevuto.' );
	}
	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		return array( 'ok' => false, 'messaggio' => 'Errore durante il caricamento (codice ' . (int) $file['error'] . ').' );
	}
	$limite = 8 * 1024 * 1024;
	if ( (int) $file['size'] > $limite ) {
		return array( 'ok' => false, 'messaggio' => 'File troppo grande: massimo 8 MB.' );
	}

	$finfo = new finfo( FILEINFO_MIME_TYPE );
	$mime  = (string) $finfo->file( $file['tmp_name'] );
	$tipi  = media_tipi_ammessi();
	if ( ! isset( $tipi[ $mime ] ) ) {
		return array( 'ok' => false, 'messaggio' => 'Formato non ammesso. Usa JPG, PNG, WebP, GIF o SVG.' );
	}
	$estensione = $tipi[ $mime ];

	if ( ! is_dir( PC_MEDIA ) ) {
		@mkdir( PC_MEDIA, 0755, true );
	}

	$nome_base = slugifica( pathinfo( (string) $file['name'], PATHINFO_FILENAME ) );
	if ( '' === $nome_base ) {
		$nome_base = 'immagine';
	}
	$nome = $nome_base . '.' . $estensione;
	$n    = 1;
	while ( file_exists( PC_MEDIA . '/' . $nome ) ) {
		$n++;
		$nome = $nome_base . '-' . $n . '.' . $estensione;
	}
	$destinazione = PC_MEDIA . '/' . $nome;

	if ( ! move_uploaded_file( $file['tmp_name'], $destinazione ) ) {
		return array( 'ok' => false, 'messaggio' => 'Impossibile scrivere nella cartella media/. Controlla i permessi (755).' );
	}
	@chmod( $destinazione, 0644 );

	$larghezza = 0;
	$altezza   = 0;
	if ( 'svg' !== $estensione ) {
		$dimensioni = @getimagesize( $destinazione );
		if ( is_array( $dimensioni ) ) {
			$larghezza = (int) $dimensioni[0];
			$altezza   = (int) $dimensioni[1];
		}
		if ( $larghezza > 1800 && function_exists( 'imagecreatetruecolor' ) ) {
			media_ridimensiona( $destinazione, $mime, 1800 );
			$dimensioni = @getimagesize( $destinazione );
			if ( is_array( $dimensioni ) ) {
				$larghezza = (int) $dimensioni[0];
				$altezza   = (int) $dimensioni[1];
			}
		}
	}

	db_salva( 'media', array(
		'id'        => nuovo_id(),
		'file'      => $nome,
		'nome'      => (string) $file['name'],
		'alt'       => str_replace( '-', ' ', $nome_base ),
		'mime'      => $mime,
		'larghezza' => $larghezza,
		'altezza'   => $altezza,
		'peso'      => (int) filesize( $destinazione ),
		'caricata'  => date( 'Y-m-d H:i:s' ),
	) );

	return array( 'ok' => true, 'messaggio' => 'Immagine caricata.', 'file' => $nome );
}

/**
 * Salva nella libreria un'immagine già in memoria (per esempio generata
 * dall'assistente), applicando gli stessi controlli di un caricamento.
 *
 * @param string $binario   Byte dell'immagine.
 * @param string $mime      Tipo dichiarato; viene comunque riverificato.
 * @param string $nome_base Nome del file senza estensione.
 * @param string $alt       Testo alternativo.
 * @return array( 'ok' => bool, 'messaggio' => string, 'file' => string )
 */
function media_salva_dati( $binario, $mime, $nome_base, $alt = '' ) {
	if ( '' === (string) $binario ) {
		return array( 'ok' => false, 'messaggio' => 'Immagine vuota.' );
	}
	$limite = 8 * 1024 * 1024;
	if ( strlen( $binario ) > $limite ) {
		return array( 'ok' => false, 'messaggio' => 'Immagine troppo grande: massimo 8 MB.' );
	}

	// Il tipo si verifica dai byte, non da quello che dichiara chi la manda.
	$finfo = new finfo( FILEINFO_MIME_TYPE );
	$vero  = (string) $finfo->buffer( $binario );
	$tipi  = media_tipi_ammessi();
	if ( ! isset( $tipi[ $vero ] ) || 'svg' === $tipi[ $vero ] ) {
		return array( 'ok' => false, 'messaggio' => 'Formato non ammesso: ' . $vero );
	}
	$estensione = $tipi[ $vero ];
	unset( $mime );

	if ( ! is_dir( PC_MEDIA ) ) {
		@mkdir( PC_MEDIA, 0755, true );
	}

	$nome_base = slugifica( $nome_base );
	if ( '' === $nome_base ) {
		$nome_base = 'immagine';
	}
	$nome = $nome_base . '.' . $estensione;
	$n    = 1;
	while ( file_exists( PC_MEDIA . '/' . $nome ) ) {
		$n++;
		$nome = $nome_base . '-' . $n . '.' . $estensione;
	}
	$destinazione = PC_MEDIA . '/' . $nome;

	if ( false === file_put_contents( $destinazione, $binario ) ) {
		return array( 'ok' => false, 'messaggio' => 'Impossibile scrivere nella cartella media/. Controlla i permessi (755).' );
	}
	@chmod( $destinazione, 0644 );

	$larghezza  = 0;
	$altezza    = 0;
	$dimensioni = @getimagesize( $destinazione );
	if ( is_array( $dimensioni ) ) {
		$larghezza = (int) $dimensioni[0];
		$altezza   = (int) $dimensioni[1];
	}
	if ( $larghezza > 1800 && function_exists( 'imagecreatetruecolor' ) ) {
		media_ridimensiona( $destinazione, $vero, 1800 );
		$dimensioni = @getimagesize( $destinazione );
		if ( is_array( $dimensioni ) ) {
			$larghezza = (int) $dimensioni[0];
			$altezza   = (int) $dimensioni[1];
		}
	}

	db_salva( 'media', array(
		'id'        => nuovo_id(),
		'file'      => $nome,
		'nome'      => $nome,
		'alt'       => '' === $alt ? str_replace( '-', ' ', $nome_base ) : $alt,
		'mime'      => $vero,
		'larghezza' => $larghezza,
		'altezza'   => $altezza,
		'peso'      => (int) filesize( $destinazione ),
		'caricata'  => date( 'Y-m-d H:i:s' ),
	) );

	return array( 'ok' => true, 'messaggio' => 'Immagine salvata.', 'file' => $nome );
}

/** Riduce la larghezza massima di un'immagine. */
function media_ridimensiona( $percorso, $mime, $larghezza_max ) {
	switch ( $mime ) {
		case 'image/jpeg':
			$sorgente = @imagecreatefromjpeg( $percorso );
			break;
		case 'image/png':
			$sorgente = @imagecreatefrompng( $percorso );
			break;
		case 'image/webp':
			$sorgente = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $percorso ) : false;
			break;
		default:
			return false;
	}
	if ( ! $sorgente ) {
		return false;
	}
	$l = imagesx( $sorgente );
	$a = imagesy( $sorgente );
	if ( $l <= $larghezza_max ) {
		imagedestroy( $sorgente );
		return false;
	}
	$nuova_l = $larghezza_max;
	$nuova_a = (int) round( $a * ( $larghezza_max / $l ) );
	$dest    = imagecreatetruecolor( $nuova_l, $nuova_a );
	if ( 'image/png' === $mime || 'image/webp' === $mime ) {
		imagealphablending( $dest, false );
		imagesavealpha( $dest, true );
	}
	imagecopyresampled( $dest, $sorgente, 0, 0, 0, 0, $nuova_l, $nuova_a, $l, $a );
	switch ( $mime ) {
		case 'image/jpeg':
			imagejpeg( $dest, $percorso, 82 );
			break;
		case 'image/png':
			imagepng( $dest, $percorso, 6 );
			break;
		case 'image/webp':
			imagewebp( $dest, $percorso, 82 );
			break;
	}
	imagedestroy( $sorgente );
	imagedestroy( $dest );
	return true;
}

function media_elimina( $id ) {
	$m = media_per_id( $id );
	if ( ! $m ) {
		return false;
	}
	$percorso = PC_MEDIA . '/' . basename( $m['file'] );
	if ( is_file( $percorso ) ) {
		@unlink( $percorso );
	}
	return db_elimina( 'media', 'id = ?', array( $id ) );
}

/** Peso leggibile. */
function media_peso( $byte ) {
	$byte = (int) $byte;
	if ( $byte >= 1048576 ) {
		return round( $byte / 1048576, 1 ) . ' MB';
	}
	if ( $byte >= 1024 ) {
		return round( $byte / 1024 ) . ' KB';
	}
	return $byte . ' B';
}
