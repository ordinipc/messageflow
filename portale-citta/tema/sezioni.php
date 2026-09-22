<?php
/**
 * Costruttori delle sezioni pubbliche.
 *
 * Ogni sezione sa disegnarsi in due modi: a riquadri (card) o a elenco.
 * La scelta arriva da Impostazioni → Aspetto e vale per tutto il portale.
 */

defined( 'PC_AVVIO' ) || exit;

/** True se le sezioni vanno disegnate a riquadri. */
function stile_card() {
	return 'card' === impostazione( 'stile_sezioni', 'card' );
}

/** Ritardo della comparsa, a scalare. Si ferma a mezzo secondo. */
function ritardo( $indice ) {
	$ms = min( (int) $indice * 60, 480 );
	return 0 === $ms ? '' : ' style="--glp-ritardo:' . $ms . 'ms"';
}

/** Apre un riquadro. */
function box_apri( $modificatore = '', $indice = 0 ) {
	$classi = 'glp-box';
	if ( '' !== $modificatore ) {
		$classi .= ' glp-box--' . $modificatore;
	}
	return '<li class="' . e( $classi ) . '"' . ritardo( $indice ) . '>';
}

/* ---------------------------------------------------------------------------
 * Cosa comprende il servizio
 * ------------------------------------------------------------------------- */

function sezione_inclusi( $pagina ) {
	$voci = righe( $pagina['inclusi'] );
	if ( empty( $voci ) ) {
		return '';
	}
	$html = sezione_apri( 'incluso', 'Cosa comprende il servizio', 'Incluso' );

	if ( stile_card() ) {
		$html .= '<ul class="glp-boxes">';
		foreach ( $voci as $i => $voce ) {
			$html .= box_apri( 'check', $i )
				. '<span class="glp-box__segno" aria-hidden="true">✓</span>'
				. '<p class="glp-box__titolo">' . e( $voce ) . '</p>'
				. '</li>';
		}
		$html .= '</ul>';
	} else {
		$html .= '<ul class="glp-list glp-list--check">';
		foreach ( $voci as $voce ) {
			$html .= '<li>' . e( $voce ) . '</li>';
		}
		$html .= '</ul>';
	}

	return $html . '</section>';
}

/* ---------------------------------------------------------------------------
 * Perché sceglierci + numeri
 * ------------------------------------------------------------------------- */

function sezione_perche( $citta ) {
	$perche = righe( $citta['perche'] );
	$numeri = array();
	foreach ( (array) $citta['numeri'] as $n ) {
		if ( ! vuoto( isset( $n['valore'] ) ? $n['valore'] : '' ) ) {
			$numeri[] = $n;
		}
	}
	if ( empty( $perche ) && empty( $numeri ) ) {
		return '';
	}

	$html = sezione_apri( 'perche', 'Perché sceglierci a ' . $citta['nome'], 'Perché noi' );

	if ( ! empty( $perche ) ) {
		if ( stile_card() ) {
			$html .= '<ul class="glp-boxes">';
			foreach ( $perche as $i => $voce ) {
				$html .= box_apri( 'why', $i )
					. '<span class="glp-box__num" aria-hidden="true">' . e( sprintf( '%02d', $i + 1 ) ) . '</span>'
					. '<p class="glp-box__titolo">' . e( $voce ) . '</p>'
					. '</li>';
			}
			$html .= '</ul>';
		} else {
			$html .= '<ul class="glp-list glp-list--why">';
			foreach ( $perche as $voce ) {
				$html .= '<li>' . e( $voce ) . '</li>';
			}
			$html .= '</ul>';
		}
	}

	if ( ! empty( $numeri ) ) {
		if ( stile_card() ) {
			$html .= '<ul class="glp-boxes glp-boxes--strette">';
			foreach ( $numeri as $i => $n ) {
				$valore    = (string) $n['valore'];
				$etichetta = isset( $n['etichetta'] ) ? $n['etichetta'] : '';
				// Solo i valori interi puliti vengono animati al primo sguardo.
				$animabile = (bool) preg_match( '/^\d{1,6}$/', $valore );
				$html .= box_apri( 'stat', $i )
					. '<strong' . ( $animabile ? ' data-conta-fino="' . e( $valore ) . '"' : '' ) . '>' . e( $valore ) . '</strong>'
					. '<span>' . e( $etichetta ) . '</span>'
					. '</li>';
			}
			$html .= '</ul>';
		} else {
			$html .= '<ul class="glp-stats">';
			foreach ( $numeri as $n ) {
				$valore   = (string) $n['valore'];
				$numerico = is_numeric( str_replace( array( '.', ',', ' ' ), '', $valore ) );
				$html    .= '<li class="' . ( $numerico ? 'glp-stat--num' : 'glp-stat--text' ) . '">'
					. '<strong>' . e( $valore ) . '</strong>'
					. '<span>' . e( isset( $n['etichetta'] ) ? $n['etichetta'] : '' ) . '</span></li>';
			}
			$html .= '</ul>';
		}
	}

	return $html . '</section>';
}

/* ---------------------------------------------------------------------------
 * Come funziona
 * ------------------------------------------------------------------------- */

function sezione_processo( $pagina ) {
	$passi = array();
	foreach ( (array) $pagina['processo'] as $p ) {
		$t = isset( $p['titolo'] ) ? $p['titolo'] : '';
		$x = isset( $p['testo'] ) ? $p['testo'] : '';
		if ( ! vuoto( $t ) || ! vuoto( $x ) ) {
			$passi[] = $p;
		}
	}
	if ( empty( $passi ) ) {
		return '';
	}

	$html = sezione_apri( 'processo', 'Come funziona, passo per passo', 'Processo' );

	if ( stile_card() ) {
		$html .= '<ul class="glp-boxes glp-boxes--processo">';
		foreach ( $passi as $i => $p ) {
			$html .= box_apri( 'step', $i )
				. '<span class="glp-box__num" aria-hidden="true"></span>';
			if ( ! vuoto( isset( $p['titolo'] ) ? $p['titolo'] : '' ) ) {
				$html .= '<h3 class="glp-box__titolo">' . e( $p['titolo'] ) . '</h3>';
			}
			if ( ! vuoto( isset( $p['testo'] ) ? $p['testo'] : '' ) ) {
				$html .= '<p class="glp-box__testo">' . e( $p['testo'] ) . '</p>';
			}
			if ( ! vuoto( isset( $p['durata'] ) ? $p['durata'] : '' ) ) {
				$html .= '<p class="glp-box__nota">' . e( $p['durata'] ) . '</p>';
			}
			$html .= '</li>';
		}
		$html .= '</ul>';
	} else {
		$html .= '<ol class="glp-steps">';
		foreach ( $passi as $p ) {
			$html .= '<li class="glp-step"><div class="glp-step__body">';
			if ( ! vuoto( isset( $p['titolo'] ) ? $p['titolo'] : '' ) ) {
				$html .= '<h3 class="glp-step__title">' . e( $p['titolo'] ) . '</h3>';
			}
			if ( ! vuoto( isset( $p['testo'] ) ? $p['testo'] : '' ) ) {
				$html .= '<p>' . e( $p['testo'] ) . '</p>';
			}
			if ( ! vuoto( isset( $p['durata'] ) ? $p['durata'] : '' ) ) {
				$html .= '<p class="glp-step__time">' . e( $p['durata'] ) . '</p>';
			}
			$html .= '</div></li>';
		}
		$html .= '</ol>';
	}

	return $html . '</section>';
}

/* ---------------------------------------------------------------------------
 * Zone servite
 * ------------------------------------------------------------------------- */

function sezione_zone( $citta ) {
	$zone   = righe( $citta['zone'] );
	$comuni = righe( $citta['comuni'] );
	if ( empty( $zone ) && empty( $comuni ) ) {
		return '';
	}

	$html = sezione_apri( 'zone', 'Zone servite a ' . $citta['nome'] . ' e dintorni', 'Copertura' );

	if ( stile_card() ) {
		$gruppi = array(
			array( 'Quartieri e zone della città', $zone, 'quartiere' ),
			array( 'Comuni limitrofi', $comuni, 'comune' ),
		);
		foreach ( $gruppi as $g ) {
			if ( empty( $g[1] ) ) {
				continue;
			}
			$html .= '<p class="glp-areas__label">' . e( $g[0] ) . ':</p><ul class="glp-boxes glp-boxes--strette">';
			foreach ( $g[1] as $i => $voce ) {
				$html .= box_apri( 'zona', $i )
					. '<p class="glp-box__titolo">' . e( $voce ) . '</p>'
					. '<p class="glp-box__testo">' . e( $g[2] ) . '</p>'
					. '</li>';
			}
			$html .= '</ul>';
		}
	} else {
		if ( ! empty( $zone ) ) {
			$html .= '<p class="glp-areas__label">Quartieri e zone della città:</p><ul class="glp-tags">';
			foreach ( $zone as $z ) {
				$html .= '<li>' . e( $z ) . '</li>';
			}
			$html .= '</ul>';
		}
		if ( ! empty( $comuni ) ) {
			$html .= '<p class="glp-areas__label">Comuni limitrofi:</p><ul class="glp-tags">';
			foreach ( $comuni as $z ) {
				$html .= '<li>' . e( $z ) . '</li>';
			}
			$html .= '</ul>';
		}
	}

	return $html . '</section>';
}

/* ---------------------------------------------------------------------------
 * Recensioni
 * ------------------------------------------------------------------------- */

function sezione_recensioni( $citta ) {
	$lista = array();
	foreach ( (array) $citta['recensioni'] as $r ) {
		if ( ! vuoto( isset( $r['testo'] ) ? $r['testo'] : '' ) ) {
			$lista[] = $r;
		}
	}
	if ( empty( $lista ) ) {
		return '';
	}

	$html    = sezione_apri( 'recensioni', 'Cosa dicono i clienti di ' . $citta['nome'], 'Recensioni' );
	$classi  = stile_card() ? 'glp-boxes glp-boxes--larghe' : 'glp-reviews';
	$html   .= '<ul class="' . $classi . '">';

	foreach ( $lista as $i => $r ) {
		$voto  = isset( $r['voto'] ) ? (int) $r['voto'] : 0;
		$firma = trim( ( isset( $r['nome'] ) ? $r['nome'] : '' ) . ( vuoto( isset( $r['zona'] ) ? $r['zona'] : '' ) ? '' : ' — ' . $r['zona'] ) );

		$html .= stile_card() ? box_apri( 'review', $i ) : '<li class="glp-review">';
		if ( $voto > 0 ) {
			$html .= '<span class="glp-review__rating" aria-label="' . e( $voto ) . ' su 5">' . str_repeat( '★', min( 5, $voto ) ) . '</span>';
		}
		$html .= '<blockquote>' . e( $r['testo'] ) . '</blockquote>';
		if ( '' !== $firma ) {
			$html .= '<cite>' . e( $firma ) . '</cite>';
		}
		$html .= '</li>';
	}

	return $html . '</ul></section>';
}

/* ---------------------------------------------------------------------------
 * Team e certificazioni
 * ------------------------------------------------------------------------- */

function sezione_team( $citta ) {
	$team = array();
	foreach ( (array) $citta['team'] as $t ) {
		if ( ! vuoto( isset( $t['nome'] ) ? $t['nome'] : '' ) ) {
			$team[] = $t;
		}
	}
	$certificazioni = righe( $citta['certificazioni'] );
	if ( empty( $team ) && empty( $certificazioni ) ) {
		return '';
	}

	$html = sezione_apri( 'team', 'Chi si occupa del servizio', 'Team' );

	if ( stile_card() ) {
		if ( ! empty( $team ) ) {
			$html .= '<ul class="glp-boxes">';
			foreach ( $team as $i => $p ) {
				$html .= box_apri( 'person', $i )
					. '<h3 class="glp-box__titolo">' . e( $p['nome'] ) . '</h3>';
				if ( ! vuoto( isset( $p['ruolo'] ) ? $p['ruolo'] : '' ) ) {
					$html .= '<p class="glp-box__ruolo">' . e( $p['ruolo'] ) . '</p>';
				}
				if ( ! vuoto( isset( $p['qualifiche'] ) ? $p['qualifiche'] : '' ) ) {
					$html .= '<p class="glp-box__testo">' . e( $p['qualifiche'] ) . '</p>';
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
		}
		if ( ! empty( $certificazioni ) ) {
			$html .= '<ul class="glp-boxes">';
			foreach ( $certificazioni as $i => $voce ) {
				$html .= box_apri( 'check', $i )
					. '<span class="glp-box__segno" aria-hidden="true">✓</span>'
					. '<p class="glp-box__titolo">' . e( $voce ) . '</p>'
					. '</li>';
			}
			$html .= '</ul>';
		}
	} else {
		foreach ( $team as $p ) {
			$ruolo = isset( $p['ruolo'] ) ? $p['ruolo'] : '';
			$html .= '<p class="glp-person"><strong>' . e( $p['nome'] ) . '</strong>' . ( vuoto( $ruolo ) ? '' : ' — ' . e( $ruolo ) ) . '</p>';
			if ( ! vuoto( isset( $p['qualifiche'] ) ? $p['qualifiche'] : '' ) ) {
				$html .= '<p class="glp-person__creds">' . e( $p['qualifiche'] ) . '</p>';
			}
		}
		if ( ! empty( $certificazioni ) ) {
			$html .= '<ul class="glp-list glp-list--certs">';
			foreach ( $certificazioni as $voce ) {
				$html .= '<li>' . e( $voce ) . '</li>';
			}
			$html .= '</ul>';
		}
	}

	return $html . '</section>';
}

/* ---------------------------------------------------------------------------
 * Orari
 * ------------------------------------------------------------------------- */

function sezione_orari( $citta ) {
	$orari = array();
	foreach ( (array) $citta['orari'] as $giorno => $fascia ) {
		if ( ! vuoto( $fascia ) ) {
			$orari[ $giorno ] = $fascia;
		}
	}
	if ( empty( $orari ) ) {
		return '';
	}

	$html = sezione_apri( 'orari', 'Orari di apertura', 'Orari' );

	if ( stile_card() ) {
		$html .= '<ul class="glp-boxes glp-boxes--orari">';
		$i     = 0;
		foreach ( $orari as $giorno => $fascia ) {
			$chiuso = false !== mb_stripos( $fascia, 'chius' );
			$html  .= '<li class="glp-box glp-box--orario' . ( $chiuso ? ' is-chiuso' : '' ) . '"' . ritardo( $i ) . '>'
				. '<span>' . e( maiuscola( $giorno ) ) . '</span>'
				. '<strong>' . e( $fascia ) . '</strong>'
				. '</li>';
			$i++;
		}
		$html .= '</ul>';
	} else {
		$html .= '<ul class="glp-hours">';
		foreach ( $orari as $giorno => $fascia ) {
			$html .= '<li><span>' . e( maiuscola( $giorno ) ) . '</span><strong>' . e( $fascia ) . '</strong></li>';
		}
		$html .= '</ul>';
	}

	return $html . '</section>';
}

/* ---------------------------------------------------------------------------
 * Altri servizi della città
 * ------------------------------------------------------------------------- */

/**
 * Elenco degli articoli: è il corpo della pagina di tipo "Blog".
 * Pagina i risultati, perché una città può averne centinaia.
 */
function sezione_blog( $citta, $pagina_blog, $per_pagina = 12 ) {
	$solo_pubblicati = ! connesso();
	$totale = articoli_conta( $citta['id'], $solo_pubblicati );

	if ( 0 === $totale ) {
		if ( $solo_pubblicati ) {
			return '';
		}
		return sezione_apri( 'blog', 'Blog di ' . $citta['nome'], 'Articoli' )
			. '<p>Nessun articolo per ' . e( $citta['nome'] ) . ': i visitatori non vedono questa sezione.</p>'
			. '<p><a href="' . e( base_url() ) . '/admin.php?p=articoli&citta=' . e( $citta['id'] )
			. '">Scrivi o importa gli articoli →</a></p></section>';
	}

	$pagine_totali = max( 1, (int) ceil( $totale / $per_pagina ) );
	$corrente      = isset( $_GET['pag'] ) ? max( 1, min( $pagine_totali, (int) $_GET['pag'] ) ) : 1;
	$articoli      = articoli_di_citta( $citta['id'], $solo_pubblicati, $per_pagina, ( $corrente - 1 ) * $per_pagina );

	$html  = sezione_apri( 'blog', 'Dal blog di ' . $citta['nome'], 'Articoli' );
	$html .= '<p class="glp-areas__label">' . (int) $totale . ' articoli'
		. ( $pagine_totali > 1 ? ' · pagina ' . $corrente . ' di ' . $pagine_totali : '' ) . '</p>';

	$html .= '<ul class="glp-boxes glp-boxes--larghe">';
	foreach ( $articoli as $i => $a ) {
		$html .= box_apri( 'link', $i )
			. '<a href="' . e( url_articolo( $citta, $a, $pagina_blog ) ) . '">'
			. '<span class="glp-box__titolo">' . e( $a['titolo'] ) . '</span>';
		if ( ! vuoto( $a['estratto'] ) ) {
			$html .= '<span class="glp-box__testo">' . e( mb_substr( $a['estratto'], 0, 140 ) ) . '</span>';
		}
		if ( ! vuoto( $a['data'] ) ) {
			$html .= '<span class="glp-box__nota">' . e( data_italiana( $a['data'] ) ) . '</span>';
		}
		$html .= '</a></li>';
	}
	$html .= '</ul>';

	if ( $pagine_totali > 1 ) {
		$base  = url_pagina( $citta, $pagina_blog );
		$html .= '<nav class="glp-paginazione" aria-label="Pagine del blog">';
		if ( $corrente > 1 ) {
			$html .= '<a class="glp-btn glp-btn--ghost" href="' . e( $base . ( $corrente - 1 > 1 ? '?pag=' . ( $corrente - 1 ) : '' ) ) . '">← Più recenti</a>';
		}
		$html .= '<span class="glp-paginazione__stato">' . $corrente . ' / ' . $pagine_totali . '</span>';
		if ( $corrente < $pagine_totali ) {
			$html .= '<a class="glp-btn glp-btn--ghost" href="' . e( $base . '?pag=' . ( $corrente + 1 ) ) . '">Più vecchi →</a>';
		}
		$html .= '</nav>';
	}

	return $html . '</section>';
}

/**
 * Elenco completo dei servizi della città: è il corpo della pagina
 * di tipo "Elenco servizi", non una sezione di coda.
 */
function sezione_elenco_servizi( $citta, $servizi ) {
	if ( empty( $servizi ) ) {
		// Al pubblico non si mostra una sezione vuota: meglio niente che
		// un vicolo cieco. Chi è collegato vede invece cosa manca e dove.
		if ( ! connesso() ) {
			return '';
		}
		return sezione_apri( 'servizi', 'Servizi a ' . $citta['nome'], 'Servizi' )
			. '<p>Questa pagina elenca le pagine di tipo <strong>Servizio</strong> pubblicate a '
			. e( $citta['nome'] ) . ', e al momento non ce ne sono: i visitatori non vedono questa sezione.</p>'
			. '<p><a href="' . e( base_url() ) . '/admin.php?p=pagine&citta=' . e( $citta['id'] )
			. '">Crea le pagine servizio →</a></p>'
			. '</section>';
	}

	$html = sezione_apri( 'servizi', 'Tutti i servizi a ' . $citta['nome'], 'Servizi' );

	if ( stile_card() ) {
		$html .= '<ul class="glp-boxes glp-boxes--larghe">';
		foreach ( $servizi as $i => $s ) {
			$html .= box_apri( 'link', $i )
				. '<a href="' . e_url( $s['url'] ) . '">'
				. '<span class="glp-box__num" aria-hidden="true">' . e( sprintf( '%02d', $i + 1 ) ) . '</span>'
				. '<span class="glp-box__titolo">' . e( $s['nome'] ) . '</span>';
			if ( ! vuoto( isset( $s['testo'] ) ? $s['testo'] : '' ) ) {
				$html .= '<span class="glp-box__testo">' . e( $s['testo'] ) . '</span>';
			}
			if ( ! vuoto( isset( $s['prezzo'] ) ? $s['prezzo'] : '' ) ) {
				$html .= '<span class="glp-box__nota">' . e( $s['prezzo'] ) . '</span>';
			}
			$html .= '</a></li>';
		}
		$html .= '</ul>';
	} else {
		$html .= '<ul class="glp-cards">';
		foreach ( $servizi as $s ) {
			$html .= '<li class="glp-card"><a class="glp-card__link" href="' . e_url( $s['url'] ) . '">'
				. '<span class="glp-card__body">'
				. '<span class="glp-card__title">' . e( $s['nome'] ) . '</span>';
			if ( ! vuoto( isset( $s['testo'] ) ? $s['testo'] : '' ) ) {
				$html .= '<span class="glp-card__text">' . e( $s['testo'] ) . '</span>';
			}
			$html .= '</span>';
			if ( ! vuoto( isset( $s['prezzo'] ) ? $s['prezzo'] : '' ) ) {
				$html .= '<span class="glp-card__price">' . e( $s['prezzo'] ) . '</span>';
			}
			$html .= '</a></li>';
		}
		$html .= '</ul>';
	}

	return $html . '</section>';
}

function sezione_servizi( $citta, $servizi, $escludi_url = '' ) {
	$lista = array();
	foreach ( $servizi as $s ) {
		if ( $s['url'] !== $escludi_url ) {
			$lista[] = $s;
		}
	}
	if ( empty( $lista ) ) {
		return '';
	}

	$html = sezione_apri( 'servizi', 'Gli altri servizi a ' . $citta['nome'], 'Servizi' );

	if ( stile_card() ) {
		$html .= '<ul class="glp-boxes">';
		foreach ( $lista as $i => $s ) {
			$html .= box_apri( 'link', $i )
				. '<a href="' . e_url( $s['url'] ) . '">'
				. '<span class="glp-box__titolo">' . e( $s['nome'] ) . '</span>';
			if ( ! vuoto( isset( $s['testo'] ) ? $s['testo'] : '' ) ) {
				$html .= '<span class="glp-box__testo">' . e( $s['testo'] ) . '</span>';
			}
			$html .= '</a></li>';
		}
		$html .= '</ul>';
	} else {
		$html .= '<ul class="glp-tags glp-tags--links">';
		foreach ( $lista as $s ) {
			$html .= '<li><a href="' . e_url( $s['url'] ) . '">' . e( $s['nome'] ) . '</a></li>';
		}
		$html .= '</ul>';
	}

	return $html . '</section>';
}

/* ---------------------------------------------------------------------------
 * Altre città
 * ------------------------------------------------------------------------- */

function sezione_correlate( $altre ) {
	if ( empty( $altre ) ) {
		return '';
	}

	$html = sezione_apri( 'correlate', 'Operiamo anche in queste città', 'Altre zone' );

	if ( stile_card() ) {
		$html .= '<ul class="glp-boxes glp-boxes--strette">';
		foreach ( $altre as $i => $a ) {
			$html .= box_apri( 'link', $i )
				. '<a href="' . e_url( $a['url'] ) . '">'
				. '<span class="glp-box__titolo">' . e( $a['nome'] ) . '</span>'
				. '</a></li>';
		}
		$html .= '</ul>';
	} else {
		$html .= '<ul class="glp-tags glp-tags--links">';
		foreach ( $altre as $a ) {
			$html .= '<li><a href="' . e_url( $a['url'] ) . '">' . e( $a['nome'] ) . '</a></li>';
		}
		$html .= '</ul>';
	}

	return $html . '</section>';
}

/* ---------------------------------------------------------------------------
 * Recapiti e modulo di contatto
 * ------------------------------------------------------------------------- */

/** Tutti i recapiti della città, in chiaro. */
function sezione_recapiti( $citta ) {
	$imp   = impostazioni();
	$righe = array();

	$telefono = contatto( $citta, 'telefono' );
	$whatsapp = contatto( $citta, 'whatsapp' );
	$email    = contatto( $citta, 'email' );

	if ( ! vuoto( $telefono ) ) {
		$righe[] = array( 'Telefono', '<a href="tel:' . e( tel( $telefono ) ) . '">' . e( $telefono ) . '</a>' );
	}
	if ( ! vuoto( $whatsapp ) ) {
		$righe[] = array( 'WhatsApp', '<a href="' . e( url_whatsapp( $whatsapp ) ) . '" rel="nofollow noopener" target="_blank">' . e( $whatsapp ) . '</a>' );
	}
	if ( ! vuoto( $email ) ) {
		$righe[] = array( 'Email', '<a href="mailto:' . e( $email ) . '">' . e( $email ) . '</a>' );
	}
	if ( ! vuoto( $citta['indirizzo'] ) ) {
		$indirizzo = $citta['indirizzo'] . ', ' . trim( $citta['cap'] . ' ' . $citta['nome'] );
		if ( ! vuoto( $citta['provincia'] ) ) {
			$indirizzo .= ' (' . $citta['provincia'] . ')';
		}
		$righe[] = array( 'Indirizzo', e( $indirizzo ) );
	}
	if ( ! vuoto( $imp['piva'] ) ) {
		$righe[] = array( 'Partita IVA', e( $imp['piva'] ) );
	}

	if ( empty( $righe ) ) {
		return '';
	}

	$html = sezione_apri( 'recapiti', 'Come raggiungerci a ' . $citta['nome'], 'Recapiti' );
	$html .= '<ul class="glp-recapiti">';
	foreach ( $righe as $i => $r ) {
		$html .= '<li class="glp-box glp-box--recapito"' . ritardo( $i ) . '>'
			. '<span class="glp-box__testo">' . e( $r[0] ) . '</span>'
			. '<strong class="glp-box__titolo">' . $r[1] . '</strong>'
			. '</li>';
	}
	return $html . '</ul></section>';
}

/** Modulo di contatto. $esito arriva da modulo_gestisci(). */
function sezione_modulo( $citta, $pagina, $servizi, $esito = null ) {
	$valori = ( $esito && isset( $esito['valori'] ) ) ? $esito['valori'] : array();
	$v      = function ( $campo ) use ( $valori ) {
		return e( isset( $valori[ $campo ] ) ? $valori[ $campo ] : '' );
	};

	$aperto = time();
	$html   = sezione_apri( 'modulo', 'Scrivici', 'Richiesta' );

	if ( $esito ) {
		$html .= '<p class="glp-modulo__esito' . ( $esito['ok'] ? ' is-ok' : ' is-ko' ) . '" role="status">'
			. e( $esito['messaggio'] ) . '</p>';
	}

	if ( $esito && $esito['ok'] ) {
		return $html . '</section>';
	}

	$html .= '<form class="glp-modulo" method="post" action="' . e( url_pagina( $citta, $pagina ) ) . '#modulo">'
		. '<input type="hidden" name="pc_modulo" value="1">'
		. '<input type="hidden" name="aperto" value="' . e( $aperto ) . '">'
		. '<input type="hidden" name="firma" value="' . e( modulo_firma( $aperto ) ) . '">'
		// Campo esca: nascosto e fuori dal percorso di tabulazione.
		. '<div class="glp-modulo__esca" aria-hidden="true">'
		. '<label>Non compilare<input type="text" name="indirizzo2" tabindex="-1" autocomplete="off"></label>'
		. '</div>'

		. '<div class="glp-modulo__riga">'
		. '<label>Nome e cognome *<input type="text" name="nome" required value="' . $v( 'nome' ) . '" autocomplete="name"></label>'
		. '<label>Telefono<input type="tel" name="telefono" value="' . $v( 'telefono' ) . '" autocomplete="tel"></label>'
		. '</div>'

		. '<div class="glp-modulo__riga">'
		. '<label>Email<input type="email" name="email" value="' . $v( 'email' ) . '" autocomplete="email"></label>';

	if ( ! empty( $servizi ) ) {
		$html .= '<label>Di cosa hai bisogno<select name="servizio"><option value="">— scegli —</option>';
		foreach ( $servizi as $s ) {
			$sel   = ( isset( $valori['servizio'] ) && $valori['servizio'] === $s['nome'] ) ? ' selected' : '';
			$html .= '<option value="' . e( $s['nome'] ) . '"' . $sel . '>' . e( $s['nome'] ) . '</option>';
		}
		$html .= '<option value="Altro">Altro</option></select></label>';
	}

	$html .= '</div>'
		. '<label>Messaggio *<textarea name="messaggio" required rows="5" placeholder="Scrivi il modello dell\'auto, o che tipo di serratura hai.">' . $v( 'messaggio' ) . '</textarea></label>'
		. '<p class="glp-modulo__nota">Lascia almeno un recapito fra telefono ed email. I dati servono solo a risponderti.</p>'
		. '<button class="glp-btn" type="submit">Invia la richiesta</button>'
		. '</form>';

	return $html . '</section>';
}

/* ---------------------------------------------------------------------------
 * Le sezioni che prima stavano scritte dentro pagina.php.
 *
 * Servivano solo lì, ma da quando l'ordine lo decide chi scrive la pagina
 * devono essere richiamabili una per una, come tutte le altre.
 * ------------------------------------------------------------------------- */

/** Il testo lungo della pagina. */
function sezione_testo( $citta, $pagina ) {
	if ( vuoto( $pagina['corpo'] ) ) {
		return '';
	}
	return sezione_apri( 'approfondimento', seo_h1( $citta, $pagina ) . ': cosa sapere', 'Approfondimento' )
		. paragrafi( $pagina['corpo'] )
		. '</section>';
}

/** L'HTML scritto a mano nella scheda "Codice". */
function sezione_html( $pagina ) {
	if ( vuoto( $pagina['html'] ) ) {
		return '';
	}
	return '<section class="glp-section glp-section--libero glp-reveal">' . $pagina['html'] . '</section>';
}

/** Prezzi della pagina servizio. */
function sezione_prezzi( $pagina ) {
	if ( vuoto( $pagina['prezzo_da'] ) ) {
		return '';
	}
	$valuta = '€';
	$testo  = vuoto( $pagina['prezzo_a'] )
		? 'a partire da ' . $pagina['prezzo_da'] . ' ' . $valuta
		: 'da ' . $pagina['prezzo_da'] . ' ' . $valuta . ' a ' . $pagina['prezzo_a'] . ' ' . $valuta;

	$html = sezione_apri( 'prezzi', 'Quanto costa', 'Prezzi' )
		. '<p class="glp-price">' . e( $testo ) . '</p>';
	if ( ! vuoto( $pagina['prezzo_note'] ) ) {
		$html .= paragrafi( $pagina['prezzo_note'] );
	}
	return $html . '</section>';
}

/** Indirizzo, indicazioni e mappa della città. */
function sezione_dove( $citta ) {
	if ( vuoto( $citta['indirizzo'] ) && vuoto( $citta['mappa'] ) && vuoto( $citta['raggiungerci'] ) ) {
		return '';
	}
	$html = sezione_apri( 'dove', 'Dove siamo e come raggiungerci', 'Sede' );

	if ( ! vuoto( $citta['indirizzo'] ) ) {
		$completo = $citta['indirizzo'] . ', ' . trim( $citta['cap'] . ' ' . $citta['nome'] );
		if ( ! vuoto( $citta['provincia'] ) ) {
			$completo .= ' (' . $citta['provincia'] . ')';
		}
		$html .= '<p class="glp-address">' . e( $completo ) . '</p>';
	}
	if ( ! vuoto( $citta['raggiungerci'] ) ) {
		$html .= paragrafi( $citta['raggiungerci'] );
	}
	if ( ! vuoto( $citta['mappa'] ) ) {
		$html .= '<div class="glp-map"><iframe src="' . e_url( $citta['mappa'] ) . '" loading="lazy" title="Mappa della sede a '
			. e( $citta['nome'] ) . '" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>';
	}
	return $html . '</section>';
}

/** Le domande frequenti della pagina. */
function sezione_faq( $pagina ) {
	$faq = (array) $pagina['faq'];
	if ( empty( $faq ) ) {
		return '';
	}
	$voci = '';
	$i    = 0;
	foreach ( $faq as $riga ) {
		$d = isset( $riga['domanda'] ) ? $riga['domanda'] : '';
		if ( vuoto( $d ) ) {
			continue;
		}
		$voci .= '<details class="glp-faq__item"' . ( 0 === $i ? ' open' : '' ) . '>'
			. '<summary class="glp-faq__q">' . e( $d ) . '</summary>'
			. '<div class="glp-faq__a">' . paragrafi( isset( $riga['risposta'] ) ? $riga['risposta'] : '' ) . '</div>'
			. '</details>';
		$i++;
	}
	if ( '' === $voci ) {
		return '';
	}
	return sezione_apri( 'faq', 'Domande frequenti', 'FAQ' )
		. '<div class="glp-faq">' . $voci . '</div></section>';
}

/** Il riquadro con i pulsanti per chiamare o scrivere. */
function sezione_cta( $citta, $telefono, $whatsapp ) {
	if ( vuoto( $telefono ) && vuoto( $whatsapp ) ) {
		return '';
	}
	$html = sezione_apri( 'cta', 'Richiedi un intervento a ' . $citta['nome'], 'Contatti' ) . '<div class="glp-cta">';
	if ( ! vuoto( $telefono ) ) {
		$html .= '<a class="glp-btn" href="tel:' . e( tel( $telefono ) ) . '">' . e( 'Chiama ' . $telefono ) . '</a>';
	}
	if ( ! vuoto( $whatsapp ) ) {
		$html .= '<a class="glp-btn glp-btn--ghost" rel="nofollow noopener" target="_blank" href="'
			. e( url_whatsapp( $whatsapp ) ) . '">Scrivici su WhatsApp</a>';
	}
	return $html . '</div></section>';
}

/**
 * Tutto quello che serve alle sezioni per disegnarsi.
 *
 * Lo costruiscono sia la pagina pubblica sia l'incorporamento in
 * WordPress: meglio un posto solo, o i due si allontanano.
 */
function contesto_pagina( $citta, $pagina, $esito_modulo = null ) {
	return array(
		'citta'        => $citta,
		'pagina'       => $pagina,
		'servizi'      => servizi_citta( $citta ),
		'altre'        => altre_citta( $citta['id'] ),
		'esito_modulo' => $esito_modulo,
		'telefono'     => contatto( $citta, 'telefono' ),
		'whatsapp'     => contatto( $citta, 'whatsapp' ),
	);
}

/**
 * Stampa una sezione a partire dalla sua chiave.
 *
 * $ctx porta quello che serve a tutte: città, pagina, servizi, altre città
 * e l'esito del modulo di contatto.
 */
function rendi_sezione( $chiave, $ctx ) {
	$citta  = $ctx['citta'];
	$pagina = $ctx['pagina'];

	switch ( $chiave ) {
		case 'testo':
			return sezione_testo( $citta, $pagina );
		case 'html':
			return sezione_html( $pagina );
		case 'elenco':
			if ( 'blog' === $pagina['tipo'] ) {
				return sezione_blog( $citta, $pagina );
			}
			return sezione_elenco_servizi( $citta, $ctx['servizi'] );
		case 'inclusi':
			return sezione_inclusi( $pagina );
		case 'processo':
			return sezione_processo( $pagina );
		case 'prezzi':
			return sezione_prezzi( $pagina );
		case 'faq':
			return sezione_faq( $pagina );
		case 'perche':
			return sezione_perche( $citta );
		case 'zone':
			return sezione_zone( $citta );
		case 'recensioni':
			return sezione_recensioni( $citta );
		case 'team':
			return sezione_team( $citta );
		case 'dove':
			return sezione_dove( $citta );
		case 'orari':
			return sezione_orari( $citta );
		case 'recapiti':
			return sezione_recapiti( $citta );
		case 'modulo':
			return sezione_modulo( $citta, $pagina, $ctx['servizi'], $ctx['esito_modulo'] );
		case 'cta':
			return sezione_cta( $citta, $ctx['telefono'], $ctx['whatsapp'] );
		case 'servizi':
			// Su una pagina che già elenca i servizi non si ripete l'elenco.
			if ( 'servizi' === $pagina['tipo'] ) {
				return '';
			}
			return sezione_servizi( $citta, $ctx['servizi'], url_pagina( $citta, $pagina ) );
		case 'correlate':
			return sezione_correlate( $ctx['altre'] );
	}
	return '';
}
