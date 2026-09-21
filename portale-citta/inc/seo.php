<?php
/** SEO: titoli, descrizioni, sitemap, robots, analisi dei contenuti. */

defined( 'PC_AVVIO' ) || exit;

/** Titolo <title> della pagina. */
function seo_titolo( $citta, $pagina ) {
	if ( ! vuoto( $pagina['seo_titolo'] ) ) {
		return $pagina['seo_titolo'];
	}
	$suffisso = impostazione( 'seo_suffisso', impostazione( 'brand', '' ) );
	$titolo   = seo_h1( $citta, $pagina );
	return vuoto( $suffisso ) ? $titolo : $titolo . ' | ' . $suffisso;
}

/** H1 della pagina. */
function seo_h1( $citta, $pagina ) {
	if ( ! vuoto( $pagina['h1'] ) ) {
		return $pagina['h1'];
	}
	if ( 'home' === $pagina['tipo'] ) {
		return maiuscola( $pagina['titolo'] );
	}
	$titolo = trim( (string) $pagina['titolo'] );
	if ( '' === $titolo ) {
		$titolo = 'Servizio';
	}
	// Non ripetere la città se è già nel titolo.
	if ( false !== mb_stripos( $titolo, $citta['nome'] ) ) {
		return maiuscola( $titolo );
	}
	return maiuscola( $titolo ) . ' a ' . $citta['nome'];
}

/** Meta description. */
function seo_descrizione( $citta, $pagina ) {
	if ( ! vuoto( $pagina['seo_desc'] ) ) {
		return mb_substr( trim( (string) $pagina['seo_desc'] ), 0, 300 );
	}
	$testo = trim( strip_tags( (string) $pagina['intro'] ) );
	if ( '' === $testo ) {
		$testo = trim( strip_tags( (string) $pagina['corpo'] ) );
	}
	if ( '' === $testo ) {
		$testo = seo_h1( $citta, $pagina ) . '. Contattaci per un intervento rapido.';
	}
	$testo = preg_replace( '/\s+/u', ' ', $testo );
	if ( mb_strlen( $testo ) > 158 ) {
		$testo = mb_substr( $testo, 0, 155 ) . '…';
	}
	return $testo;
}

/** URL canonico. */
function seo_canonico( $citta, $pagina ) {
	return url_pagina( $citta, $pagina );
}

/** True se la pagina può essere indicizzata. */
function seo_indicizzabile( $citta, $pagina ) {
	if ( '1' !== (string) impostazione( 'indicizza', '1' ) ) {
		return false;
	}
	return 'pubblicata' === $pagina['stato'] && 'pubblicata' === $citta['stato'];
}

/* ---------------------------------------------------------------------------
 * Analisi SEO della pagina (punteggio 0-100)
 * ------------------------------------------------------------------------- */

/** Restituisce array( 'punteggio' => int, 'controlli' => array ). */
function seo_analisi( $citta, $pagina ) {
	$controlli = array();
	$chiave    = mb_strtolower( seo_h1( $citta, $pagina ) );
	$citta_l   = mb_strtolower( $citta['nome'] );

	$titolo = seo_titolo( $citta, $pagina );
	$lung_t = mb_strlen( $titolo );
	$controlli[] = array(
		'nome' => 'Lunghezza del titolo',
		'ok'   => $lung_t >= 30 && $lung_t <= 65,
		'nota' => $lung_t . ' caratteri (ideale 30-65)',
		'peso' => 15,
	);

	$desc   = seo_descrizione( $citta, $pagina );
	$lung_d = mb_strlen( $desc );
	$controlli[] = array(
		'nome' => 'Lunghezza della descrizione',
		'ok'   => $lung_d >= 70 && $lung_d <= 160,
		'nota' => $lung_d . ' caratteri (ideale 70-160)',
		'peso' => 10,
	);

	$controlli[] = array(
		'nome' => 'Città nel titolo',
		'ok'   => false !== mb_stripos( $titolo, $citta['nome'] ),
		'nota' => 'Il nome della città deve comparire nel <title>',
		'peso' => 15,
	);

	$controlli[] = array(
		'nome' => 'Città nella descrizione',
		'ok'   => false !== mb_stripos( $desc, $citta['nome'] ),
		'nota' => 'Rafforza la geolocalizzazione',
		'peso' => 5,
	);

	$testo = strip_tags( (string) $pagina['intro'] . ' ' . $pagina['corpo'] . ' ' . $pagina['inclusi'] );

	// Una pagina "elenco servizi" ha per contenuto le schede che mostra:
	// contarle è più onesto che chiederle 300 parole scritte a mano.
	$minimo = 300;
	if ( 'servizi' === $pagina['tipo'] ) {
		$testo .= ' ' . seo_testo_dei_servizi( $pagina['citta_id'] );
		$minimo = 150;
	}

	$parole = str_word_count( $testo, 0, 'àáâäèéêëìíîïòóôöùúûüçñÀÈÉÌÒÙ0123456789' );
	$controlli[] = array(
		'nome' => 'Quantità di testo',
		'ok'   => $parole >= $minimo,
		'nota' => $parole . ' parole (minimo consigliato ' . $minimo . ')',
		'peso' => 20,
	);

	$controlli[] = array(
		'nome' => 'Domande frequenti',
		'ok'   => count( (array) $pagina['faq'] ) >= 3,
		'nota' => count( (array) $pagina['faq'] ) . ' FAQ (minimo 3 per lo schema FAQPage)',
		'peso' => 10,
	);

	$controlli[] = array(
		'nome' => 'Immagine di anteprima',
		'ok'   => ! vuoto( $pagina['immagine'] ),
		'nota' => 'Serve per Open Graph e per la condivisione',
		'peso' => 5,
	);

	$controlli[] = array(
		'nome' => 'Dati di contatto della città',
		'ok'   => ! vuoto( $citta['telefono'] ) && ! vuoto( $citta['indirizzo'] ),
		'nota' => 'Telefono e indirizzo alimentano lo schema LocalBusiness',
		'peso' => 10,
	);

	$controlli[] = array(
		'nome' => 'Coordinate geografiche',
		'ok'   => ! vuoto( $citta['lat'] ) && ! vuoto( $citta['lng'] ),
		'nota' => 'Latitudine e longitudine per il geotagging',
		'peso' => 5,
	);

	$controlli[] = array(
		'nome' => 'Testo originale rispetto alle altre città',
		'ok'   => seo_testo_originale( $pagina ),
		'nota' => 'Il testo non deve essere identico a quello di un\'altra città',
		'peso' => 5,
	);

	$totale  = 0;
	$ottenuto = 0;
	foreach ( $controlli as $c ) {
		$totale += $c['peso'];
		if ( $c['ok'] ) {
			$ottenuto += $c['peso'];
		}
	}
	$punteggio = $totale > 0 ? (int) round( $ottenuto / $totale * 100 ) : 0;

	return array( 'punteggio' => $punteggio, 'controlli' => $controlli );
}

/** Titoli e introduzioni delle pagine servizio di una città. */
function seo_testo_dei_servizi( $citta_id ) {
	$righe = db_righe(
		'SELECT titolo, intro, seo_desc FROM ' . db_tab( 'pagine' )
		. " WHERE citta_id = ? AND tipo = 'servizio'",
		array( (string) $citta_id )
	);
	$pezzi = array();
	foreach ( $righe as $r ) {
		$pezzi[] = $r['titolo'] . ' ' . $r['intro'] . ' ' . $r['seo_desc'];
	}
	return strip_tags( implode( ' ', $pezzi ) );
}

/** Confronta il corpo con quello delle altre pagine: true se è abbastanza diverso. */
function seo_testo_originale( $pagina ) {
	$mio = preg_replace( '/\s+/u', ' ', mb_strtolower( strip_tags( (string) $pagina['corpo'] ) ) );
	if ( mb_strlen( $mio ) < 40 ) {
		return false;
	}
	$righe = db_righe(
		'SELECT id, corpo FROM ' . db_tab( 'pagine' ) . ' WHERE id <> ?',
		array( (string) $pagina['id'] )
	);
	foreach ( $righe as $altra ) {
		$suo = preg_replace( '/\s+/u', ' ', mb_strtolower( strip_tags( (string) $altra['corpo'] ) ) );
		if ( '' === $suo ) {
			continue;
		}
		if ( $suo === $mio ) {
			return false;
		}
		similar_text( mb_substr( $mio, 0, 1200 ), mb_substr( $suo, 0, 1200 ), $percentuale );
		if ( $percentuale > 92 ) {
			return false;
		}
	}
	return true;
}

/* ---------------------------------------------------------------------------
 * Sitemap e robots
 * ------------------------------------------------------------------------- */

/** Elenco delle URL indicizzabili. */
function sitemap_voci( $citta_id = '' ) {
	$voci = array();
	foreach ( citta_tutte( true ) as $citta ) {
		if ( '' !== $citta_id && $citta['id'] !== $citta_id ) {
			continue;
		}
		foreach ( pagine_di_citta( $citta['id'], true ) as $pagina ) {
			$voci[] = array(
				'url'      => url_pagina( $citta, $pagina ),
				'modifica' => vuoto( $pagina['aggiornata'] ) ? oggi() : $pagina['aggiornata'],
				'priorita' => 'home' === $pagina['tipo'] ? '0.9' : '0.8',
				'freq'     => 'weekly',
			);
		}
	}
	return $voci;
}

/** XML della sitemap di una città (o di tutto il portale). */
function sitemap_xml( $citta_id = '' ) {
	$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ( sitemap_voci( $citta_id ) as $v ) {
		$xml .= "\t<url>\n";
		$xml .= "\t\t<loc>" . e( $v['url'] ) . "</loc>\n";
		$xml .= "\t\t<lastmod>" . e( $v['modifica'] ) . "</lastmod>\n";
		$xml .= "\t\t<changefreq>" . e( $v['freq'] ) . "</changefreq>\n";
		$xml .= "\t\t<priority>" . e( $v['priorita'] ) . "</priority>\n";
		$xml .= "\t</url>\n";
	}
	$xml .= '</urlset>';
	return $xml;
}

/** Indice delle sitemap: una sitemap per città. */
function sitemap_indice() {
	$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ( citta_tutte( true ) as $citta ) {
		$pagine = pagine_di_citta( $citta['id'], true );
		if ( empty( $pagine ) ) {
			continue;
		}
		$ultima = oggi();
		foreach ( $pagine as $p ) {
			if ( ! vuoto( $p['aggiornata'] ) && $p['aggiornata'] > $ultima ) {
				$ultima = $p['aggiornata'];
			}
		}
		$xml .= "\t<sitemap>\n";
		$xml .= "\t\t<loc>" . e( base_url() . '/sitemap-' . $citta['slug'] . '.xml' ) . "</loc>\n";
		$xml .= "\t\t<lastmod>" . e( $ultima ) . "</lastmod>\n";
		$xml .= "\t</sitemap>\n";
	}
	$xml .= '</sitemapindex>';
	return $xml;
}

/**
 * La sottocartella in cui vive il portale, o '' se sta nella radice.
 * Esempio: con https://sito.it/zone restituisce '/zone'.
 */
function sottocartella() {
	$percorso = parse_url( base_url(), PHP_URL_PATH );
	return null === $percorso ? '' : rtrim( $percorso, '/' );
}

/** Contenuto di robots.txt. */
function robots_txt() {
	$righe = array( 'User-agent: *' );
	if ( '1' !== (string) impostazione( 'indicizza', '1' ) ) {
		$righe[] = 'Disallow: /';
		return implode( "\n", $righe ) . "\n";
	}
	$righe[] = 'Disallow: /admin.php';
	$righe[] = 'Disallow: /admin/';
	$righe[] = 'Disallow: /inc/';
	$righe[] = 'Disallow: /dati/';
	$righe[] = 'Allow: /';
	$righe[] = '';
	$righe[] = 'Sitemap: ' . base_url() . '/sitemap.xml';
	return implode( "\n", $righe ) . "\n";
}
