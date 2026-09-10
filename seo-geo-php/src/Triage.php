<?php
/**
 * Triage editoriale degli articoli pubblicati.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

/**
 * Classifica ogni articolo in eliminare, accorpare, riscrivere o mantenere,
 * combinando qualità misurata, similarità reale fra i testi e competizione
 * sulle stesse keyword delle pagine servizio.
 */
class Triage {

	/** @var array<string,string> Pattern per il riconoscimento dell intento. */
	const INTENTI = array(
		'transazionale' => '/\b(prezzo|prezzi|costo|costi|quanto costa|preventivo|agenzia|servizi|assistenza|contatt|acquist|vendita)\b/iu',
		'commerciale'   => '/\b(migliori|miglior|come scegliere|scegliere|confronto|alternativ|conviene|recension|classifica)\b/iu',
		'informazionale' => '/\b(come|cosa|perch|quando|dove|guida|strategie|consigli|errori|significa|funziona|esempi|trend)\b/iu',
	);

	/**
	 * Intento di ricerca dell articolo.
	 *
	 * @param array $doc Documento.
	 * @return string
	 */
	public static function intento( array $doc ) {
		$hay = $doc['titolo'] . ' ' . $doc['focus'];

		if ( preg_match( '/\bmax digital\b/i', $hay ) ) {
			return 'navigazionale';
		}

		foreach ( self::INTENTI as $nome => $re ) {
			if ( preg_match( $re, $hay ) ) {
				return $nome;
			}
		}

		return 'informazionale';
	}

	/**
	 * Punteggio di qualità 0-100.
	 *
	 * @param array $doc      Documento.
	 * @param Site  $site     Sito.
	 * @param float $maxSim   Massima similarità con un altro articolo.
	 * @return int
	 */
	public static function qualita( array $doc, Site $site, $maxSim ) {
		$p = 0;
		$w = $doc['parole'];

		if ( $w >= 1300 ) {
			$p += 34;
		} elseif ( $w >= 900 ) {
			$p += 28;
		} elseif ( $w >= 600 ) {
			$p += 18;
		} elseif ( $w >= 350 ) {
			$p += 9;
		}

		$h2 = count( $doc['h2'] );
		if ( $h2 >= 5 ) {
			$p += 14;
		} elseif ( $h2 >= 3 ) {
			$p += 10;
		} elseif ( $h2 >= 1 ) {
			$p += 5;
		}

		$p += $doc['liste'] > 0 ? 4 : 0;
		$p += $doc['tabelle'] > 0 ? 4 : 0;
		$p += count( $doc['immagini'] ) > 0 ? 5 : 0;
		$p += '' !== $doc['thumbnail'] ? 3 : 0;

		$p += (int) round( 16 * ( 1 - min( 1, $maxSim / 0.5 ) ) );

		$p += min( 6, $site->inbound( $doc['percorso'] ) * 2 );
		$p += min( 4, count( $doc['link_interni'] ) * 2 );

		$giorni = ( time() - strtotime( $doc['modificato'] ?: $doc['data'] ) ) / 86400;
		if ( $giorni < 180 ) {
			$p += 6;
		} elseif ( $giorni < 365 ) {
			$p += 3;
		}

		if ( null !== $doc['seo_score'] ) {
			$p += (int) round( min( 1, $doc['seo_score'] / 100 ) * 5 );
		}

		if ( null !== $doc['gulpease'] && $doc['gulpease'] >= 50 ) {
			$p += 3;
		}

		return (int) max( 0, min( 100, $p ) );
	}

	/**
	 * Esegue la classificazione completa.
	 *
	 * @param Site  $site Sito.
	 * @param array $cfg  Configurazione.
	 * @return array
	 */
	public static function esegui( Site $site, array $cfg ) {
		$articoli = $site->articoli;
		$n        = count( $articoli );

		$pilastri = array();
		foreach ( $cfg['seo']['paginePilastro'] as $p ) {
			foreach ( $site->pagine as $pag ) {
				if ( $pag['slug'] === $p['slug'] ) {
					$pilastri[] = $pag;
				}
			}
		}

		$shArt = array();
		foreach ( $articoli as $i => $a ) {
			$shArt[ $i ] = Text::shingles( $a['testo'] );
		}

		$shSrv = array();
		foreach ( $pilastri as $j => $p ) {
			$shSrv[ $j ] = Text::shingles( $p['testo'] );
		}

		// Similarità fra articoli.
		$simili = array_fill( 0, $n, array() );
		for ( $i = 0; $i < $n; $i++ ) {
			for ( $j = $i + 1; $j < $n; $j++ ) {
				$v = Text::jaccard( $shArt[ $i ], $shArt[ $j ] );
				if ( $v >= 0.18 ) {
					$simili[ $i ][ $j ] = $v;
					$simili[ $j ][ $i ] = $v;
				}
			}
		}

		// Sovrapposizione con le pagine servizio.
		$servizio = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$best = array( 'idx' => null, 'v' => 0.0 );
			foreach ( $pilastri as $j => $p ) {
				$v = Text::jaccard( $shArt[ $i ], $shSrv[ $j ] );
				if ( $v > $best['v'] ) {
					$best = array( 'idx' => $j, 'v' => $v );
				}
			}
			$servizio[ $i ] = $best;
		}

		$norm = static function ( $k ) {
			$k = mb_strtolower( (string) $k );
			$k = preg_replace( '/\b(a|di|in|per|la|il|le|i|lo|gli|un|una|e)\b/u', ' ', $k );
			$k = preg_replace( '/[^\p{L}\p{N} ]/u', ' ', $k );

			return trim( preg_replace( '/\s+/u', ' ', $k ) );
		};

		$kwServizio = array();
		foreach ( $pilastri as $j => $p ) {
			$kwServizio[ $norm( $p['focus'] ?: $p['titolo'] ) ] = $j;
		}

		// Schede di lavoro.
		$schede = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$maxSim = empty( $simili[ $i ] ) ? 0.0 : max( $simili[ $i ] );
			$schede[ $i ] = array(
				'doc'       => $articoli[ $i ],
				'intento'   => self::intento( $articoli[ $i ] ),
				'qualita'   => self::qualita( $articoli[ $i ], $site, $maxSim ),
				'maxSim'    => $maxSim,
				'categoria' => null,
				'motivo'    => array(),
				'azione'    => '',
				'redirect'  => null,
			);
		}

		$vincitore = static function ( array $indici ) use ( &$schede, $articoli, $site ) {
			usort(
				$indici,
				static function ( $a, $b ) use ( &$schede, $articoli, $site ) {
					$d = $schede[ $b ]['qualita'] <=> $schede[ $a ]['qualita'];
					if ( 0 !== $d ) {
						return $d;
					}
					$d = $articoli[ $b ]['parole'] <=> $articoli[ $a ]['parole'];
					if ( 0 !== $d ) {
						return $d;
					}

					return $site->inbound( $articoli[ $b ]['percorso'] ) <=> $site->inbound( $articoli[ $a ]['percorso'] );
				}
			);

			return $indici[0];
		};

		// 1. Cluster di quasi-duplicati: resta il migliore, gli altri si eliminano.
		$visti    = array();
		$clusterD = array();
		for ( $i = 0; $i < $n; $i++ ) {
			if ( isset( $visti[ $i ] ) ) {
				continue;
			}
			$pila    = array( $i );
			$gruppo  = array();
			while ( $pila ) {
				$k = array_pop( $pila );
				if ( isset( $visti[ $k ] ) ) {
					continue;
				}
				$visti[ $k ] = true;
				$gruppo[]    = $k;
				foreach ( $simili[ $k ] as $j => $v ) {
					if ( $v >= 0.55 && ! isset( $visti[ $j ] ) ) {
						$pila[] = $j;
					}
				}
			}
			if ( count( $gruppo ) > 1 ) {
				$clusterD[] = $gruppo;
			}
		}

		foreach ( $clusterD as $gruppo ) {
			$win = $vincitore( $gruppo );
			foreach ( $gruppo as $k ) {
				if ( $k === $win ) {
					$schede[ $k ]['categoria'] = 'riscrivere';
					$schede[ $k ]['motivo'][]  = 'versione migliore di un gruppo di ' . count( $gruppo ) . ' articoli quasi identici: assorbe i contenuti degli altri';
					$schede[ $k ]['azione']    = 'unificare i contenuti del gruppo e ampliare';
				} else {
					$schede[ $k ]['categoria'] = 'eliminare';
					$schede[ $k ]['motivo'][]  = sprintf( 'duplicato al %d%% di "%s"', round( $schede[ $k ]['maxSim'] * 100 ), $articoli[ $win ]['titolo'] );
					$schede[ $k ]['redirect']  = $articoli[ $win ];
				}
			}
		}

		// 2. Cluster di sovrapposizione media: da accorpare.
		$visti2   = array();
		$clusterA = array();
		for ( $i = 0; $i < $n; $i++ ) {
			if ( null !== $schede[ $i ]['categoria'] || isset( $visti2[ $i ] ) ) {
				continue;
			}
			$pila   = array( $i );
			$gruppo = array();
			while ( $pila ) {
				$k = array_pop( $pila );
				if ( isset( $visti2[ $k ] ) || null !== $schede[ $k ]['categoria'] ) {
					continue;
				}
				$visti2[ $k ] = true;
				$gruppo[]     = $k;
				foreach ( $simili[ $k ] as $j => $v ) {
					if ( $v >= 0.30 && ! isset( $visti2[ $j ] ) ) {
						$pila[] = $j;
					}
				}
			}
			if ( count( $gruppo ) > 1 ) {
				$clusterA[] = $gruppo;
			}
		}

		// 3. Gruppi che si contendono la stessa focus keyword.
		$perKeyword = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$k = $norm( $articoli[ $i ]['focus'] );
			if ( '' !== $k ) {
				$perKeyword[ $k ][] = $i;
			}
		}
		foreach ( $perKeyword as $indici ) {
			$liberi = array();
			foreach ( $indici as $i ) {
				if ( null === $schede[ $i ]['categoria'] ) {
					$liberi[] = $i;
				}
			}
			if ( count( $liberi ) > 1 ) {
				$clusterA[] = $liberi;
			}
		}

		foreach ( $clusterA as $gruppo ) {
			$win = $vincitore( $gruppo );
			foreach ( $gruppo as $k ) {
				if ( null !== $schede[ $k ]['categoria'] ) {
					continue;
				}
				if ( $k === $win ) {
					$schede[ $k ]['categoria'] = 'accorpare';
					$schede[ $k ]['motivo'][]  = 'articolo principale del gruppo: accoglie i contenuti degli altri ' . ( count( $gruppo ) - 1 );
					$schede[ $k ]['azione']    = 'mantenere e ampliare';
				} else {
					$comune = (int) round( $schede[ $k ]['maxSim'] * 100 );
					$schede[ $k ]['categoria'] = 'accorpare';
					$schede[ $k ]['motivo'][]  = $comune >= 30
						? sprintf( 'sovrapposto a "%s": %d%% di testo in comune', $articoli[ $win ]['titolo'], $comune )
						: sprintf( 'stessa focus keyword di "%s" ("%s"): le due pagine competono per la stessa query', $articoli[ $win ]['titolo'], $articoli[ $k ]['focus'] );
					$schede[ $k ]['azione']   = 'unire nel principale e impostare il 301';
					$schede[ $k ]['redirect'] = $articoli[ $win ];
				}
			}
		}

		// 4. Cannibalizzazione diretta di una pagina servizio.
		for ( $i = 0; $i < $n; $i++ ) {
			if ( null !== $schede[ $i ]['categoria'] ) {
				continue;
			}
			$kw  = $norm( $articoli[ $i ]['focus'] );
			$idx = $kwServizio[ $kw ] ?? null;

			if ( null !== $idx && $articoli[ $i ]['parole'] < 700 ) {
				$schede[ $i ]['categoria'] = 'eliminare';
				$schede[ $i ]['motivo'][]  = sprintf( 'stessa keyword della pagina servizio "%s" con contenuto più debole: toglie forza alla pagina che deve posizionarsi', $pilastri[ $idx ]['titolo'] );
				$schede[ $i ]['redirect']  = $pilastri[ $idx ];
			} elseif ( null !== $servizio[ $i ]['idx'] && $servizio[ $i ]['v'] >= 0.30 ) {
				$p = $pilastri[ $servizio[ $i ]['idx'] ];
				$schede[ $i ]['categoria'] = 'accorpare';
				$schede[ $i ]['motivo'][]  = sprintf( '%d%% di testo in comune con la pagina servizio "%s"', round( $servizio[ $i ]['v'] * 100 ), $p['titolo'] );
				$schede[ $i ]['azione']    = 'confluire nella pagina servizio e impostare il 301';
				$schede[ $i ]['redirect']  = $p;
			}
		}

		// 5. Contenuti troppo scarni per esistere.
		for ( $i = 0; $i < $n; $i++ ) {
			if ( null !== $schede[ $i ]['categoria'] ) {
				continue;
			}
			if ( $articoli[ $i ]['parole'] < 300 && $schede[ $i ]['qualita'] < 30 ) {
				$schede[ $i ]['categoria'] = 'eliminare';
				$schede[ $i ]['motivo'][]  = sprintf( 'solo %d parole e nessun segnale di valore: non può soddisfare nessun intento di ricerca', $articoli[ $i ]['parole'] );
				if ( null !== $servizio[ $i ]['idx'] && $servizio[ $i ]['v'] > 0.12 ) {
					$schede[ $i ]['redirect'] = $pilastri[ $servizio[ $i ]['idx'] ];
				}
			}
		}

		// 6. Il resto: riscrivere o mantenere in base alla qualità.
		$soglia = $cfg['seo']['sogliaQualita'];
		for ( $i = 0; $i < $n; $i++ ) {
			if ( null !== $schede[ $i ]['categoria'] ) {
				continue;
			}
			$doc = $articoli[ $i ];
			$q   = $schede[ $i ]['qualita'];

			// Sotto le 500 parole un articolo non regge il confronto con la prima
			// pagina, per quanto sia scritto bene: va comunque ampliato.
			$promosso = $doc['parole'] >= 500 && ( $q >= $soglia || ( $doc['parole'] >= 900 && $q >= 52 ) );

			if ( $promosso ) {
				$schede[ $i ]['categoria'] = 'mantenere';
				$schede[ $i ]['motivo'][]  = sprintf( 'qualità %d/100: lunghezza, struttura e unicità adeguate', $q );
				$schede[ $i ]['azione']    = 'ottimizzare meta e link interni';
			} else {
				$perche = array();
				if ( $doc['parole'] < 900 ) {
					$perche[] = 'solo ' . $doc['parole'] . ' parole';
				}
				if ( count( $doc['h2'] ) < 3 ) {
					$perche[] = count( $doc['h2'] ) . ' sottotitoli H2';
				}
				if ( 0 === count( $doc['immagini'] ) ) {
					$perche[] = 'nessuna immagine';
				}
				if ( 0 === count( $doc['link_interni'] ) ) {
					$perche[] = 'nessun link interno';
				}
				if ( 0 === $doc['tabelle'] ) {
					$perche[] = 'nessun dato in tabella';
				}
				$schede[ $i ]['categoria'] = 'riscrivere';
				$schede[ $i ]['motivo'][]  = sprintf( 'qualità %d/100 — %s', $q, implode( ', ', $perche ) );
				$schede[ $i ]['azione']    = 'espandere a 1.000-1.400 parole con struttura, FAQ e dati';
			}
		}

		// Azioni definitive per i contenuti da rimuovere.
		$risultato = array();
		$conteggi  = array( 'eliminare' => 0, 'accorpare' => 0, 'riscrivere' => 0, 'mantenere' => 0 );

		for ( $i = 0; $i < $n; $i++ ) {
			$s   = $schede[ $i ];
			$doc = $articoli[ $i ];

			if ( 'eliminare' === $s['categoria'] ) {
				$inbound  = $site->inbound( $doc['percorso'] );
				$s['azione'] = $s['redirect']
					? '301 verso ' . $s['redirect']['url'] . ' poi cestinare'
					: ( $inbound > 0 ? '301 verso la categoria di riferimento poi cestinare' : 'cestinare e restituire 410 (nessun link in entrata da preservare)' );
			}

			$conteggi[ $s['categoria'] ]++;

			$risultato[] = array(
				'wp_id'      => $doc['wp_id'],
				'titolo'     => $doc['titolo'],
				'url'        => $doc['url'],
				'slug'       => $doc['slug'],
				'percorso'   => $doc['percorso'],
				'categoria'  => $s['categoria'],
				'parole'     => $doc['parole'],
				'qualita'    => $s['qualita'],
				'intento'    => $s['intento'],
				'h2'         => count( $doc['h2'] ),
				'immagini'   => count( $doc['immagini'] ),
				'link_in'    => $site->inbound( $doc['percorso'] ),
				'link_out'   => count( $doc['link_interni'] ),
				'focus'      => $doc['focus'],
				'pubblicato' => substr( (string) $doc['data'], 0, 10 ),
				'similarita' => round( $s['maxSim'], 2 ),
				'servizio'   => null !== $servizio[ $i ]['idx']
					? array( 'pagina' => $pilastri[ $servizio[ $i ]['idx'] ]['titolo'], 'url' => $pilastri[ $servizio[ $i ]['idx'] ]['url'], 'percentuale' => (int) round( $servizio[ $i ]['v'] * 100 ) )
					: null,
				'motivo'     => implode( '; ', $s['motivo'] ),
				'azione'     => $s['azione'],
				'redirect'   => $s['redirect'] ? array( 'titolo' => $s['redirect']['titolo'], 'url' => $s['redirect']['url'] ) : null,
			);
		}

		return array(
			'articoli' => $risultato,
			'conteggi' => $conteggi,
			'totale'   => $n,
		);
	}

	/**
	 * Salva il triage sul database.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit di riferimento.
	 * @param array $triage  Risultato di esegui().
	 * @return int
	 */
	public static function salva( Db $db, $auditId, array $triage ) {
		$righe = array();

		foreach ( $triage['articoli'] as $a ) {
			$doc = $db->one( 'SELECT id FROM documento WHERE audit_id = ? AND wp_id = ?', array( $auditId, $a['wp_id'] ) );

			$righe[] = array(
				'audit_id'       => $auditId,
				'documento_id'   => $doc ? (int) $doc['id'] : 0,
				'categoria'      => $a['categoria'],
				'qualita'        => $a['qualita'],
				'intento'        => $a['intento'],
				'similarita_max' => $a['similarita'],
				'motivo'         => $a['motivo'],
				'azione'         => $a['azione'],
				'redirect_a'     => $a['redirect'] ? $a['redirect']['url'] : '',
			);
		}

		return $db->insertMany( 'triage', $righe );
	}
}
