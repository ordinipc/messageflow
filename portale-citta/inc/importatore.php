<?php
/**
 * Importazione di articoli da un'esportazione WordPress (file WXR).
 *
 * Il file si legge in streaming con XMLReader: un'esportazione di
 * quaranta megabyte non deve finire tutta in memoria.
 */

defined( 'PC_AVVIO' ) || exit;

/** Cartella dove si possono caricare i file da FTP. */
function import_cartella() {
	$dir = PC_DATI . '/import';
	if ( ! is_dir( $dir ) ) {
		@mkdir( $dir, 0755, true );
	}
	return $dir;
}

/** File di esportazione già presenti sul server. */
function import_file_disponibili() {
	$out = array();
	foreach ( glob( import_cartella() . '/*.{xml,XML,zip,ZIP}', GLOB_BRACE ) as $f ) {
		$out[] = array(
			'nome' => basename( $f ),
			'peso' => filesize( $f ),
			'data' => date( 'Y-m-d H:i', filemtime( $f ) ),
		);
	}
	usort( $out, function ( $a, $b ) {
		return strcmp( $b['data'], $a['data'] );
	} );
	return $out;
}

/**
 * Se il file è uno zip, ne estrae l'XML e restituisce il percorso.
 * Ritorna array( 'ok' => bool, 'file' => string, 'errore' => string ).
 */
function import_prepara( $percorso ) {
	if ( ! is_readable( $percorso ) ) {
		return array( 'ok' => false, 'file' => '', 'errore' => 'File non leggibile: ' . basename( $percorso ) );
	}
	if ( 'zip' !== strtolower( (string) pathinfo( $percorso, PATHINFO_EXTENSION ) ) ) {
		return array( 'ok' => true, 'file' => $percorso, 'errore' => '' );
	}
	if ( ! class_exists( 'ZipArchive' ) ) {
		return array( 'ok' => false, 'file' => '', 'errore' => 'Estensione zip non disponibile: carica direttamente il file .xml.' );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $percorso ) ) {
		return array( 'ok' => false, 'file' => '', 'errore' => 'Archivio zip non valido.' );
	}
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$nome = $zip->getNameIndex( $i );
		if ( 'xml' !== strtolower( (string) pathinfo( $nome, PATHINFO_EXTENSION ) ) ) {
			continue;
		}
		$destinazione = import_cartella() . '/' . basename( $nome );
		$flusso       = $zip->getStream( $nome );
		if ( ! $flusso ) {
			continue;
		}
		$uscita = fopen( $destinazione, 'wb' );
		while ( ! feof( $flusso ) ) {
			fwrite( $uscita, fread( $flusso, 65536 ) );
		}
		fclose( $uscita );
		fclose( $flusso );
		$zip->close();
		return array( 'ok' => true, 'file' => $destinazione, 'errore' => '' );
	}
	$zip->close();
	return array( 'ok' => false, 'file' => '', 'errore' => 'Nessun file .xml dentro lo zip.' );
}

/**
 * Scorre gli articoli del file e chiama $per_ogni( $articolo ).
 * Se la funzione restituisce false la lettura si ferma.
 */
function import_scorri( $file, $per_ogni ) {
	$lettore = new XMLReader();
	if ( ! @$lettore->open( $file ) ) {
		return false;
	}

	$letti = 0;
	while ( @$lettore->read() ) {
		if ( XMLReader::ELEMENT !== $lettore->nodeType || 'item' !== $lettore->name ) {
			continue;
		}
		$grezzo = $lettore->readOuterXml();
		$lettore->next();
		if ( '' === $grezzo ) {
			continue;
		}

		$articolo = import_leggi_item( $grezzo );
		if ( null === $articolo ) {
			continue;
		}
		$letti++;
		if ( false === $per_ogni( $articolo ) ) {
			break;
		}
	}
	$lettore->close();
	return $letti;
}

/** Trasforma un <item> dell'esportazione in un articolo, o null. */
function import_leggi_item( $xml ) {
	$vecchio = libxml_use_internal_errors( true );
	$el      = simplexml_load_string(
		'<?xml version="1.0" encoding="UTF-8"?><rss xmlns:wp="http://wordpress.org/export/1.2/" '
		. 'xmlns:content="http://purl.org/rss/1.0/modules/content/" '
		. 'xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"><channel>' . $xml . '</channel></rss>'
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $vecchio );

	if ( false === $el || ! isset( $el->channel->item ) ) {
		return null;
	}
	$i  = $el->channel->item;
	$wp = $i->children( 'http://wordpress.org/export/1.2/' );
	$co = $i->children( 'http://purl.org/rss/1.0/modules/content/' );
	$ex = $i->children( 'http://wordpress.org/export/1.2/excerpt/' );

	if ( 'post' !== (string) $wp->post_type ) {
		return null;
	}

	return array(
		'titolo'    => trim( (string) $i->title ),
		'origine'   => trim( (string) $i->link ),
		'slug'      => trim( (string) $wp->post_name ),
		'data'      => substr( trim( (string) $wp->post_date ), 0, 10 ),
		'stato'     => trim( (string) $wp->status ),
		'corpo'     => (string) $co->encoded,
		'estratto'  => trim( (string) $ex->encoded ),
	);
}

/* ---------------------------------------------------------------------------
 * A quale città appartiene un articolo
 * ------------------------------------------------------------------------- */

/**
 * Sceglie la città in base al titolo.
 *
 * Si confrontano solo le città che esistono già nel portale: un articolo
 * su Marsala finisce a Marsala se quella città c'è, altrimenti nella città
 * di riserva. Vince il nome che compare più avanti nel titolo, perché la
 * geolocalizzazione sta di solito in fondo ("… a Paceco").
 *
 * @return string id della città, o '' se nessuna corrisponde.
 */
function import_citta_di( $titolo, $citta, $riserva_id = '' ) {
	$titolo  = ' ' . mb_strtolower( (string) $titolo ) . ' ';
	$scelta  = '';
	$ultima  = -1;

	foreach ( $citta as $c ) {
		$nome = mb_strtolower( $c['nome'] );
		if ( '' === $nome ) {
			continue;
		}
		$posizione = mb_strrpos( $titolo, $nome );
		if ( false === $posizione ) {
			continue;
		}
		// Deve essere una parola intera, non un pezzo di un'altra.
		$prima = mb_substr( $titolo, $posizione - 1, 1 );
		$dopo  = mb_substr( $titolo, $posizione + mb_strlen( $nome ), 1 );
		if ( preg_match( '/[\p{L}]/u', $prima ) || preg_match( '/[\p{L}]/u', $dopo ) ) {
			continue;
		}
		if ( $posizione > $ultima ) {
			$ultima = $posizione;
			$scelta = $c['id'];
		}
	}

	return '' !== $scelta ? $scelta : $riserva_id;
}

/* ---------------------------------------------------------------------------
 * Pulizia del contenuto
 * ------------------------------------------------------------------------- */

/** Tag che teniamo negli articoli importati. */
function import_tag_ammessi() {
	return '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a><table><thead><tbody><tr><th><td><hr>';
}

/**
 * Contenitori di blocco: attorno a questi si spezza il testo.
 * Dentro restano i loro tag interni (li, tr, td…), che non vanno toccati.
 */
function import_tag_contenitore() {
	return array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
		'ul', 'ol', 'blockquote', 'table', 'hr', 'div', 'section', 'pre' );
}

/**
 * Ricostruisce i paragrafi dove il testo è separato da righe vuote.
 *
 * WordPress non salva i <p>: li aggiunge al momento di mostrare la pagina.
 * Fuori da WordPress quel testo arriverebbe come un muro unico, quindi la
 * conversione va rifatta qui.
 */
function import_paragrafi( $html ) {
	$c = implode( '|', import_tag_contenitore() );

	// Una riga vuota PRIMA di ogni apertura e DOPO ogni chiusura: così un
	// <h2>testo</h2> resta un pezzo solo invece di spezzarsi in tre.
	$html = preg_replace( '#(<(?:' . $c . ')\b[^>]*>)#i', "\n\n$1", $html );
	$html = preg_replace( '#(</(?:' . $c . ')>)#i', "$1\n\n", $html );
	$html = preg_replace( '#(<hr\b[^>]*>)#i', "\n\n$1\n\n", $html );
	$html = preg_replace( "/\n{3,}/", "\n\n", $html );

	$out = '';
	foreach ( preg_split( "/\n[ \t]*\n/", $html ) as $pezzo ) {
		$pezzo = trim( $pezzo );
		if ( '' === $pezzo ) {
			continue;
		}
		// Comincia con un contenitore: si lascia intatto.
		if ( preg_match( '#^</?(?:' . $c . ')\b#i', $pezzo ) ) {
			$out .= $pezzo . "\n";
			continue;
		}
		// Testo libero: diventa un paragrafo, con gli a capo singoli ridotti.
		$pezzo = preg_replace( "/\s*\n\s*/", ' ', $pezzo );
		$out  .= '<p>' . $pezzo . "</p>\n";
	}

	return $out;
}

/**
 * Ripulisce il corpo: via i commenti di blocco Gutenberg, gli shortcode,
 * gli script e gli attributi pericolosi. Resta l'HTML utile.
 *
 * Le righe vuote si toccano solo alla fine: sono l'unica traccia dei
 * paragrafi negli articoli che WordPress salva senza <p>.
 */
function import_pulisci_corpo( $html ) {
	$html = (string) $html;
	$html = str_replace( array( "\r\n", "\r" ), "\n", $html );

	// Commenti dei blocchi e shortcode.
	$html = preg_replace( '/<!--\s*\/?wp:.*?-->/s', '', $html );
	$html = preg_replace( '/<!--.*?-->/s', '', $html );
	$html = preg_replace( '/\[\/?[a-z0-9_-]+[^\]]*\]/i', '', $html );

	// Script, stili e simili non entrano.
	$html = preg_replace( '#<(script|style|iframe|object|embed|form|noscript)\b.*?</\1>#is', '', $html );
	$html = preg_replace( '#<(script|style|iframe|object|embed|form|noscript)\b[^>]*/?>#i', '', $html );

	$html = strip_tags( $html, import_tag_ammessi() );

	// Attributi: sui link restano href e rel; altrove nessuno.
	$html = preg_replace_callback( '/<([a-z0-9]+)\b([^>]*)>/i', function ( $m ) {
		$tag   = strtolower( $m[1] );
		$attr  = $m[2];
		$tieni = '';
		if ( 'a' === $tag && preg_match( '/href\s*=\s*["\']?([^"\'\s>]+)/i', $attr, $h ) ) {
			$url = trim( html_entity_decode( $h[1], ENT_QUOTES, 'UTF-8' ) );
			if ( ! preg_match( '#^(javascript|data|vbscript):#i', $url ) ) {
				$tieni = ' href="' . htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) . '"';
				if ( preg_match( '#^https?://#i', $url ) ) {
					$tieni .= ' rel="noopener"';
				}
			}
		}
		return '<' . $tag . $tieni . '>';
	}, $html );

	// Se i paragrafi non ci sono, si ricostruiscono dalle righe vuote.
	if ( ! preg_match( '#<p[\s>]#i', $html ) ) {
		$html = import_paragrafi( $html );
	}

	// Ora sì: via gli spazi di troppo, ma senza toccare gli a capo.
	$html = preg_replace( '/[ \t]+/', ' ', $html );
	$html = preg_replace( '#<p>(\s|&nbsp;|<br>)*</p>#i', '', $html );
	$html = preg_replace( "/\n{3,}/", "\n\n", $html );

	return trim( $html );
}

/** Estratto: le prime parole del testo, senza HTML. */
function import_estratto( $estratto, $corpo, $max = 200 ) {
	$testo = trim( strip_tags( (string) $estratto ) );
	if ( '' === $testo ) {
		$testo = trim( strip_tags( (string) $corpo ) );
	}
	$testo = preg_replace( '/\s+/u', ' ', html_entity_decode( $testo, ENT_QUOTES, 'UTF-8' ) );
	if ( mb_strlen( $testo ) <= $max ) {
		return $testo;
	}
	$corto  = mb_substr( $testo, 0, $max );
	$spazio = mb_strrpos( $corto, ' ' );
	return rtrim( false === $spazio ? $corto : mb_substr( $corto, 0, $spazio ), ' ,;:' ) . '…';
}

/* ---------------------------------------------------------------------------
 * Quali località nominano i titoli
 * ------------------------------------------------------------------------- */

/** Parole che sembrano nomi di luogo ma non lo sono. */
function import_non_luoghi() {
	return array( 'sant', 'santa', 'san', 'via', 'viale', 'piazza', 'piazzale', 'corso',
		'zona', 'casa', 'villa', 'lido', 'porto', 'stazione', 'aeroporto', 'centro',
		'riserva', 'laguna', 'baia', 'lungomare', 'citta', 'città', 'provincia', 'comune',
		'noi', 'te', 'voi', 'casa tua', 'domicilio', 'casa mia' );
}

/**
 * Estrae dai titoli i nomi di località, con quante volte compaiono.
 *
 * Serve prima di importare: se il portale ha una sola città, tutto finisce
 * lì e non si capisce che nel file ci sono anche gli altri comuni. Qui si
 * vedono, e si possono creare prima di importare.
 *
 * @return array Elenco ordinato per frequenza:
 *               array( 'nome', 'quante', 'citta_id' ) — citta_id '' se manca.
 */
function import_luoghi( $file, $citta ) {
	$conteggio = array();

	import_scorri( $file, function ( $a ) use ( &$conteggio ) {
		$titolo = (string) $a['titolo'];

		// Un nome proprio dopo "a", "ad", "in", "di", "zona", "presso".
		// Le preposizioni articolate no: "della chiave" non è un luogo.
		if ( ! preg_match_all(
			'/\b(?:a|ad|in|zona|presso|verso)\s+((?:\p{Lu}[\p{L}\'’]+)(?:\s+(?:del|della|dei|di|lo|la|le|Lo|La|Le|Del|Della|Di)\s+\p{Lu}[\p{L}\'’]+|\s+\p{Lu}[\p{L}\'’]+)*)/u',
			$titolo,
			$trovati
		) ) {
			return true;
		}

		foreach ( $trovati[1] as $grezzo ) {
			$nome = trim( preg_replace( '/\s+/u', ' ', $grezzo ) );
			if ( mb_strlen( $nome ) < 4 ) {
				continue;
			}
			if ( in_array( mb_strtolower( $nome ), import_non_luoghi(), true ) ) {
				continue;
			}
			$conteggio[ $nome ] = ( $conteggio[ $nome ] ?? 0 ) + 1;
		}
		return true;
	} );

	// I nomi delle città già note: servono a scartare le varianti che le contengono.
	$noti = array();
	foreach ( $citta as $c ) {
		$noti[] = mb_strtolower( $c['nome'] );
	}

	$out = array();
	foreach ( $conteggio as $nome => $quante ) {
		$basso = mb_strtolower( $nome );

		// "Villa Rosina Trapani" o "Trapani Marausa": è una zona, non un comune.
		$variante = false;
		foreach ( $noti as $n ) {
			if ( $basso !== $n && false !== mb_strpos( $basso, $n ) ) {
				$variante = true;
				break;
			}
		}
		if ( $variante ) {
			continue;
		}

		// Comincia con una parola che non è un luogo ("Casa Santa", "Via Fardella").
		$prima = mb_strtolower( (string) strtok( $nome, ' ' ) );
		if ( in_array( $prima, import_non_luoghi(), true ) ) {
			continue;
		}

		$id = '';
		foreach ( $citta as $c ) {
			if ( mb_strtolower( $c['nome'] ) === $basso ) {
				$id = $c['id'];
				break;
			}
		}

		$out[] = array( 'nome' => $nome, 'quante' => $quante, 'citta_id' => $id );
	}

	usort( $out, function ( $a, $b ) {
		return $b['quante'] <=> $a['quante'];
	} );

	return $out;
}
