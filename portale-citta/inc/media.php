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

/* ---------------------------------------------------------------------------
 * Peso delle immagini
 *
 * Un'immagine generata dall'IA arriva a 800 KB per 1376 px: è il peso di
 * una pagina intera, per una foto che in una card si vede larga 330 px.
 * Ricomprimerla a qualità 82 ne toglie l'80% senza che si veda la
 * differenza, e per le card si tiene da parte una copia piccola.
 * ------------------------------------------------------------------------- */

/** Larghezza massima di un'immagine in libreria. */
function media_larghezza_max() {
	return max( 600, min( 4000, (int) impostazione( 'media_larghezza', '1600' ) ) );
}

/** Qualità di ricompressione, 50-95. */
function media_qualita() {
	return max( 50, min( 95, (int) impostazione( 'media_qualita', '82' ) ) );
}

/** Larghezza della copia piccola usata nelle card. */
function media_mini_larghezza() {
	return max( 320, min( 1600, (int) impostazione( 'media_mini', '800' ) ) );
}

/** La cartella delle copie piccole. */
function media_mini_cartella() {
	$dir = PC_MEDIA . '/mini';
	if ( ! is_dir( $dir ) ) {
		@mkdir( $dir, 0755, true );
	}
	return $dir;
}

/** Apre un file immagine come risorsa GD, o false. */
function media_apri( $percorso, $mime ) {
	switch ( $mime ) {
		case 'image/jpeg':
			return @imagecreatefromjpeg( $percorso );
		case 'image/png':
			return @imagecreatefrompng( $percorso );
		case 'image/webp':
			return function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $percorso ) : false;
	}
	return false;
}

/** Scrive una risorsa GD su file, nel formato giusto. */
function media_scrivi( $risorsa, $percorso, $mime, $qualita ) {
	switch ( $mime ) {
		case 'image/jpeg':
			// Progressiva: la foto compare sfocata e si definisce, invece
			// di scendere una riga per volta.
			imageinterlace( $risorsa, true );
			return imagejpeg( $risorsa, $percorso, $qualita );
		case 'image/png':
			return imagepng( $risorsa, $percorso, 9 );
		case 'image/webp':
			return function_exists( 'imagewebp' ) ? imagewebp( $risorsa, $percorso, $qualita ) : false;
	}
	return false;
}

/** Una copia rimpicciolita di una risorsa GD, o la stessa se è già stretta. */
function media_scala( $sorgente, $larghezza_max, $mime ) {
	$l = imagesx( $sorgente );
	$a = imagesy( $sorgente );
	if ( $l <= $larghezza_max ) {
		return null;
	}
	$nuova_a = max( 1, (int) round( $a * ( $larghezza_max / $l ) ) );
	$dest    = imagecreatetruecolor( $larghezza_max, $nuova_a );
	if ( 'image/png' === $mime || 'image/webp' === $mime ) {
		imagealphablending( $dest, false );
		imagesavealpha( $dest, true );
	}
	imagecopyresampled( $dest, $sorgente, 0, 0, 0, 0, $larghezza_max, $nuova_a, $l, $a );
	return $dest;
}

/**
 * Ricomprime un'immagine già salvata, sul posto.
 *
 * Il file nuovo si tiene solo se pesa meno: un'immagine già ottimizzata non
 * deve ingrassare per essere passata di qui. Il nome non cambia mai, così
 * nessun collegamento si rompe.
 *
 * @return array( 'fatto' => bool, 'prima' => int, 'dopo' => int,
 *                'larghezza' => int, 'altezza' => int )
 */
function media_alleggerisci( $percorso, $larghezza_max = 0, $qualita = 0 ) {
	$vuoto = array( 'fatto' => false, 'prima' => 0, 'dopo' => 0, 'larghezza' => 0, 'altezza' => 0 );
	if ( ! is_file( $percorso ) || ! function_exists( 'imagecreatetruecolor' ) ) {
		return $vuoto;
	}

	$dati = @getimagesize( $percorso );
	if ( ! is_array( $dati ) ) {
		return $vuoto;
	}
	$mime  = (string) $dati['mime'];
	$prima = (int) filesize( $percorso );
	$vuoto = array( 'fatto' => false, 'prima' => $prima, 'dopo' => $prima,
		'larghezza' => (int) $dati[0], 'altezza' => (int) $dati[1] );

	// Le GIF possono essere animate e il PNG con trasparenza spesso è un
	// logo, dove la ricompressione rende poco: si toccano solo se sono
	// più larghe del massimo.
	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
		return $vuoto;
	}

	$larghezza_max = $larghezza_max > 0 ? $larghezza_max : media_larghezza_max();
	$qualita       = $qualita > 0 ? $qualita : media_qualita();

	$sorgente = media_apri( $percorso, $mime );
	if ( ! $sorgente ) {
		return $vuoto;
	}

	$scalata = media_scala( $sorgente, $larghezza_max, $mime );
	$lavoro  = null === $scalata ? $sorgente : $scalata;

	// Prima in memoria: così si confronta il peso senza aver già
	// sovrascritto l'originale.
	ob_start();
	$ok    = media_scrivi( $lavoro, null, $mime, $qualita );
	$nuovo = ob_get_clean();

	$esito = $vuoto;
	if ( $ok && '' !== $nuovo && ( strlen( $nuovo ) < $prima || null !== $scalata ) ) {
		if ( false !== file_put_contents( $percorso, $nuovo ) ) {
			@chmod( $percorso, 0644 );
			$esito = array(
				'fatto'     => true,
				'prima'     => $prima,
				'dopo'      => strlen( $nuovo ),
				'larghezza' => imagesx( $lavoro ),
				'altezza'   => imagesy( $lavoro ),
			);
		}
	}

	if ( null !== $scalata ) {
		imagedestroy( $scalata );
	}
	imagedestroy( $sorgente );
	return $esito;
}

/**
 * Crea la copia piccola per le card, se serve.
 * Se l'originale è già stretto non si crea niente: sarebbe una copia uguale.
 */
function media_mini_crea( $file ) {
	$file = basename( (string) $file );
	if ( '' === $file ) {
		return false;
	}
	$origine = PC_MEDIA . '/' . $file;
	if ( ! is_file( $origine ) || ! function_exists( 'imagecreatetruecolor' ) ) {
		return false;
	}
	$dati = @getimagesize( $origine );
	if ( ! is_array( $dati ) || ! in_array( $dati['mime'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
		return false;
	}
	if ( (int) $dati[0] <= media_mini_larghezza() ) {
		return false;
	}

	$sorgente = media_apri( $origine, (string) $dati['mime'] );
	if ( ! $sorgente ) {
		return false;
	}
	$piccola = media_scala( $sorgente, media_mini_larghezza(), (string) $dati['mime'] );
	$ok      = false;
	if ( null !== $piccola ) {
		$ok = media_scrivi( $piccola, media_mini_cartella() . '/' . $file, (string) $dati['mime'], media_qualita() );
		@chmod( media_mini_cartella() . '/' . $file, 0644 );
		imagedestroy( $piccola );
	}
	imagedestroy( $sorgente );
	return (bool) $ok;
}

/**
 * Il nome da usare per la copia piccola, o '' se non c'è.
 * Chi chiama ripiega sull'originale: una miniatura mancante non è un guasto.
 */
function media_mini( $file ) {
	$file = basename( (string) $file );
	if ( '' === $file ) {
		return '';
	}
	return is_file( PC_MEDIA . '/mini/' . $file ) ? 'mini/' . $file : '';
}

/** Cancella la copia piccola di un file. */
function media_mini_elimina( $file ) {
	$file = basename( (string) $file );
	$p    = PC_MEDIA . '/mini/' . $file;
	if ( '' !== $file && is_file( $p ) ) {
		@unlink( $p );
	}
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
		// Taglia la larghezza e ricomprime: un'immagine dal telefono arriva
		// a 4 MB, e sul sito serve larga al massimo quanto lo schermo.
		$alleggerita = media_alleggerisci( $destinazione );
		if ( $alleggerita['fatto'] ) {
			$larghezza = $alleggerita['larghezza'];
			$altezza   = $alleggerita['altezza'];
		}
		media_mini_crea( $nome );
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
	// Un'immagine appena disegnata dall'IA arriva sugli 800 KB: qui si
	// ricomprime prima ancora di finire in archivio.
	$alleggerita = media_alleggerisci( $destinazione );
	if ( $alleggerita['fatto'] ) {
		$larghezza = $alleggerita['larghezza'];
		$altezza   = $alleggerita['altezza'];
	}
	media_mini_crea( $nome );

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

function media_elimina( $id ) {
	$m = media_per_id( $id );
	if ( ! $m ) {
		return false;
	}
	$percorso = PC_MEDIA . '/' . basename( $m['file'] );
	if ( is_file( $percorso ) ) {
		@unlink( $percorso );
	}
	media_mini_elimina( $m['file'] );
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
