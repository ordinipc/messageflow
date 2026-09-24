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
		'nota' => count( (array) $pagina['faq'] ) . ' FAQ (2 bastano per lo schema FAQPage, 3 sono meglio)',
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

		// Gli articoli del blog, se la città ha una pagina che li ospita.
		$blog = pagina_blog( $citta['id'] );
		if ( $blog && 'pubblicata' === $blog['stato'] ) {
			foreach ( articoli_di_citta( $citta['id'], true ) as $articolo ) {
				$voci[] = array(
					'url'      => url_articolo( $citta, $articolo, $blog ),
					'modifica' => vuoto( $articolo['aggiornata'] ) ? ( vuoto( $articolo['data'] ) ? oggi() : $articolo['data'] ) : $articolo['aggiornata'],
					'priorita' => '0.6',
					'freq'     => 'monthly',
				);
			}
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

/**
 * Le regole da incollare nel .htaccess della radice per non far vedere
 * la sottocartella negli indirizzi.
 *
 * Non si tocca niente sul portale: i file restano dove sono e il server
 * gira le richieste. Le città si elencano una per una apposta — così
 * tutto quello che non è del portale resta di WordPress, comprese le
 * pagine che non esistono ancora.
 */
function regole_htaccess() {
	$cartella = trim( cartella_reale(), '/' );
	if ( '' === $cartella ) {
		return '';
	}

	$slug = array();
	foreach ( citta_tutte( true ) as $c ) {
		$slug[] = preg_quote( $c['slug'], '/' );
	}

	$righe = array(
		'# --- Portale Città: la cartella /' . $cartella . ' non si vede negli indirizzi ---',
		'# Da mettere PRIMA del blocco "# BEGIN WordPress", o WordPress se le',
		'# prende tutte lui e queste regole non le legge nessuno.',
		'<IfModule mod_rewrite.c>',
		"	RewriteEngine On",
		'',
		"	# File e cartelle che esistono davvero restano dove sono.",
		"	RewriteCond %{REQUEST_FILENAME} -f [OR]",
		"	RewriteCond %{REQUEST_FILENAME} -d",
		"	RewriteRule ^ - [L]",
		'',
		"	# Fogli di stile, script e immagini del portale.",
		"	RewriteRule ^(tema|media)/(.*)$ " . $cartella . '/$1/$2 [L]',
	);

	if ( empty( $slug ) ) {
		$righe[] = '';
		$righe[] = "	# Nessuna città pubblicata: qui andranno i loro indirizzi.";
	} else {
		$righe[] = '';
		$righe[] = "	# Le città pubblicate. Aggiungendone una, ricopia queste regole.";
		$righe[] = "	RewriteRule ^(" . implode( '|', $slug ) . ')(/.*)?$ ' . $cartella . '/index.php [L]';
	}

	$righe[] = '';
	$righe[] = "	# Sitemap e indice per gli assistenti IA. Se un plugin SEO usa già";
	$righe[] = "	# /sitemap.xml, togli questa riga e lascia la sitemap del portale";
	$righe[] = "	# al suo indirizzo dentro la cartella.";
	$righe[] = "	RewriteRule ^(sitemap\.xml|sitemap-[a-z0-9-]+\.xml|llms\.txt)$ " . $cartella . '/index.php [L]';
	$righe[] = '</IfModule>';
	$righe[] = '# --- fine Portale Città ---';

	return implode( "\n", $righe );
}

/**
 * Prova davvero gli indirizzi corti, uno per città.
 *
 * Le regole si incollano a mano in un file che il portale non può
 * leggere: l'unico modo di sapere se funzionano è chiederlo al server.
 * Serve anche dopo, quando si aggiunge una città e ci si dimentica di
 * ricopiare le regole: quella città risponde 404 e qui si vede.
 *
 * @return array slug => array( 'url', 'stato', 'ok' )
 */
function prova_indirizzi_nascosti( $quante = 8 ) {
	$fuori = array();
	if ( ! function_exists( 'curl_init' ) ) {
		return $fuori;
	}
	// L'indirizzo corto si costruisce qui, togliendo la cartella: così la
	// prova si può fare PRIMA di cambiare le impostazioni, che è l'ordine
	// giusto — prima si controlla che funzioni, poi ci si sposta.
	$cartella = trim( cartella_reale(), '/' );
	$radice   = preg_replace( '#/' . preg_quote( $cartella, '#' ) . '$#', '', base_url() );

	foreach ( array_slice( citta_tutte( true ), 0, $quante ) as $c ) {
		$url = $radice . '/' . $c['slug'] . '/';
		$ch  = curl_init( $url );
		curl_setopt_array( $ch, array(
			CURLOPT_NOBODY         => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_TIMEOUT        => 5,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_USERAGENT      => 'PortaleCitta/verifica',
		) );
		curl_exec( $ch );
		$stato = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		curl_close( $ch );

		$fuori[ $c['slug'] ] = array(
			'nome'  => $c['nome'],
			'url'   => $url,
			'stato' => $stato,
			'ok'    => 200 === $stato,
		);
	}
	return $fuori;
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

	// Gli assistenti IA leggono il robots.txt come tutti gli altri, ma
	// hanno nomi propri: qui si dice sì o no senza lasciarlo al caso.
	//
	// Un gruppo con il proprio nome sostituisce quello di «*», non lo
	// completa: i divieti sull'amministrazione vanno ripetuti, o questi
	// sarebbero gli unici a potersela leggere.
	$consenti = '0' !== (string) impostazione( 'ia_consenti', '1' );
	$righe[]  = '';
	$righe[]  = '# Assistenti IA';
	foreach ( bot_ia() as $bot ) {
		$righe[] = 'User-agent: ' . $bot;
	}
	if ( $consenti ) {
		$righe[] = 'Disallow: /admin.php';
		$righe[] = 'Disallow: /admin/';
		$righe[] = 'Disallow: /inc/';
		$righe[] = 'Disallow: /dati/';
		$righe[] = 'Allow: /';
	} else {
		$righe[] = 'Disallow: /';
	}

	$righe[] = '';
	$righe[] = 'Sitemap: ' . base_url() . '/sitemap.xml';
	if ( $consenti ) {
		// Non è una direttiva del protocollo: è un commento, perché
		// scriverla come se lo fosse non la farebbe leggere a nessuno.
		$righe[] = '# llms.txt: ' . base_url() . '/llms.txt';
	}
	return implode( "\n", $righe ) . "\n";
}

/** I nomi con cui si presentano gli assistenti IA che leggono il web. */
function bot_ia() {
	return array(
		'GPTBot',
		'OAI-SearchBot',
		'ChatGPT-User',
		'ClaudeBot',
		'Claude-User',
		'Claude-SearchBot',
		'PerplexityBot',
		'Perplexity-User',
		'Google-Extended',
		'Applebot-Extended',
		'meta-externalagent',
		'Bingbot',
		'CCBot',
	);
}

/**
 * llms.txt — l'indice del portale scritto per gli assistenti IA.
 *
 * È una convenzione giovane e non tutti la seguono: costa poco e, se
 * anche servisse solo a metà di loro, è sempre meglio che far ricavare
 * i fatti dell'attività dall'HTML delle pagine.
 */
function llms_txt() {
	$imp   = impostazioni();
	$marca = $imp['brand'];
	$fuori = array( '# ' . $marca );

	$servizi = array();
	foreach ( servizi() as $s ) {
		$servizi[] = $s['nome'];
	}
	if ( ! empty( $servizi ) ) {
		$fuori[] = '';
		$fuori[] = '> ' . $marca . ': ' . implode( ', ', $servizi ) . '.';
	}

	$fuori[] = '';
	$fuori[] = 'Portale delle zone servite. Ogni città ha la sua pagina con recapiti,';
	$fuori[] = 'orari, zone coperte e i servizi disponibili sul posto.';

	$recapiti = array();
	if ( ! vuoto( $imp['telefono'] ) ) {
		$recapiti[] = 'Telefono: ' . $imp['telefono'];
	}
	if ( ! vuoto( $imp['email'] ) ) {
		$recapiti[] = 'Email: ' . $imp['email'];
	}
	if ( ! vuoto( $imp['ragione_sociale'] ) ) {
		$recapiti[] = 'Denominazione sociale: ' . $imp['ragione_sociale'];
	}
	if ( ! vuoto( $imp['piva'] ) ) {
		$recapiti[] = 'Partita IVA: ' . $imp['piva'];
	}
	if ( ! vuoto( $imp['sito_principale'] ) ) {
		$recapiti[] = 'Sito principale: ' . $imp['sito_principale'];
	}
	if ( ! empty( $recapiti ) ) {
		$fuori[] = '';
		$fuori[] = '## Contatti';
		$fuori[] = '';
		foreach ( $recapiti as $r ) {
			$fuori[] = '- ' . $r;
		}
	}

	foreach ( citta_tutte( true ) as $c ) {
		$fuori[] = '';
		$fuori[] = '## ' . $c['nome'] . ( vuoto( $c['provincia'] ) ? '' : ' (' . $c['provincia'] . ')' );
		$fuori[] = '';

		$dati = array();
		if ( ! vuoto( $c['indirizzo'] ) ) {
			$dati[] = 'Indirizzo: ' . trim( $c['indirizzo'] . ', ' . $c['cap'] . ' ' . $c['nome'] );
		}
		$telefono = contatto( $c, 'telefono' );
		if ( ! vuoto( $telefono ) ) {
			$dati[] = 'Telefono: ' . $telefono;
		}
		$comuni = righe( (string) $c['comuni'] );
		if ( ! empty( $comuni ) ) {
			$dati[] = 'Copre anche: ' . implode( ', ', $comuni );
		}
		// Solo se sono diverse da quelle generali, già scritte sotto Contatti.
		if ( ! vuoto( $c['ragione_sociale'] ) && $c['ragione_sociale'] !== $imp['ragione_sociale'] ) {
			$dati[] = 'Denominazione sociale: ' . $c['ragione_sociale'];
		}
		if ( ! vuoto( $c['piva'] ) && $c['piva'] !== $imp['piva'] ) {
			$dati[] = 'Partita IVA: ' . $c['piva'];
		}
		foreach ( $dati as $d ) {
			$fuori[] = '- ' . $d;
		}
		if ( ! empty( $dati ) ) {
			$fuori[] = '';
		}

		foreach ( pagine_di_citta( $c['id'], true ) as $p ) {
			$descrizione = vuoto( $p['seo_desc'] ) ? $p['intro'] : $p['seo_desc'];
			$fuori[]     = '- [' . $p['titolo'] . '](' . url_pagina( $c, $p ) . ')'
				. ( vuoto( $descrizione ) ? '' : ': ' . trim( preg_replace( '/\s+/u', ' ', $descrizione ) ) );
		}
	}

	return implode( "\n", $fuori ) . "\n";
}
