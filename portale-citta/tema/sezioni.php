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
 * Elenco completo dei servizi della città: è il corpo della pagina
 * di tipo "Elenco servizi", non una sezione di coda.
 */
function sezione_elenco_servizi( $citta, $servizi ) {
	if ( empty( $servizi ) ) {
		return sezione_apri( 'servizi', 'Servizi a ' . $citta['nome'], 'Servizi' )
			. '<p>Nessuna pagina servizio pubblicata per ' . e( $citta['nome'] ) . '.</p>'
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
