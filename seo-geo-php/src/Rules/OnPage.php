<?php
/**
 * Regole on-page: title, meta description, URL, gerarchia dei titoli.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Rules;

use SeoGeo\Site;
use SeoGeo\Text;

/**
 * Controlli sugli elementi che il motore legge per primi.
 */
class OnPage {

	/**
	 * @return array[]
	 */
	public static function rules() {
		return array(
			array(
				'id' => 'ONP-01', 'area' => 'onpage', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Title SEO troppo lungo (viene troncato in SERP)',
				'perche' => 'Oltre i 60 caratteri Google tronca il title: il messaggio e la keyword finale si perdono e il CTR cala.',
				'soluzione' => 'Riscrittura automatica entro 60 caratteri con la focus keyword a inizio titolo.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( mb_strlen( $d['seo_title'] ) > 60 ) {
							$out[] = Base::doc( $d, mb_strlen( $d['seo_title'] ) . ' caratteri' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'ONP-02', 'area' => 'onpage', 'gravita' => Base::MEDIO, 'auto' => true,
				'titolo' => 'Title SEO troppo corto (spazio in SERP sprecato)',
				'perche' => 'Sotto i 30 caratteri si perde spazio utile per keyword secondarie e qualificatori geografici.',
				'soluzione' => 'Estensione automatica con keyword, località e brand.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( '' !== $d['seo_title'] && mb_strlen( $d['seo_title'] ) < 30 ) {
							$out[] = Base::doc( $d, mb_strlen( $d['seo_title'] ) . ' caratteri' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'ONP-03', 'area' => 'onpage', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Meta description fuori dalla lunghezza ottimale',
				'perche' => 'Sopra i 158 caratteri viene troncata, sotto i 120 non sfrutta lo snippet: in entrambi i casi si perde CTR.',
				'soluzione' => 'Riscrittura a 140-158 caratteri con keyword, beneficio e invito all azione.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						$n = mb_strlen( $d['seo_desc'] );
						if ( $n > 0 && ( $n > 158 || $n < 120 ) ) {
							$out[] = Base::doc( $d, $n . ' caratteri' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'ONP-04', 'area' => 'onpage', 'gravita' => Base::CRITICO, 'auto' => true,
				'titolo' => 'Meta description mancante',
				'perche' => 'Senza description Google costruisce uno snippet arbitrario, spesso incoerente con l intento di ricerca.',
				'soluzione' => 'Generazione automatica dal primo paragrafo con la focus keyword.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( '' === $d['seo_desc'] ) {
							$out[] = Base::doc( $d, 'assente' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'ONP-05', 'area' => 'onpage', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Focus keyword assente dal title SEO',
				'perche' => 'La keyword nel title resta uno dei segnali on-page più forti per rilevanza e posizionamento.',
				'soluzione' => 'Il title rigenerato porta la focus keyword nei primi 30 caratteri.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( '' !== $d['focus'] && '' !== $d['seo_title'] && false === mb_stripos( $d['seo_title'], $d['focus'] ) ) {
							$out[] = Base::doc( $d, 'keyword "' . $d['focus'] . '" non presente nel title' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'ONP-06', 'area' => 'onpage', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'Focus keyword duplicata su più pagine (cannibalizzazione)',
				'perche' => 'Più URL in gara sulla stessa query si tolgono forza a vicenda: Google non capisce quale posizionare.',
				'soluzione' => 'Consolidare in una pagina pilastro e differenziare le altre su varianti long-tail.',
				'check' => static function ( Site $s ) {
					$gruppi = array();
					foreach ( $s->pubblicati as $d ) {
						$k = mb_strtolower( trim( $d['focus'] ) );
						if ( '' !== $k ) {
							$gruppi[ $k ][] = $d;
						}
					}
					$out = array();
					foreach ( $gruppi as $k => $docs ) {
						if ( count( $docs ) < 2 ) {
							continue;
						}
						foreach ( $docs as $d ) {
							$out[] = Base::doc( $d, 'keyword "' . $k . '" condivisa con altre ' . ( count( $docs ) - 1 ) . ' pagine' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'ONP-07', 'area' => 'onpage', 'gravita' => Base::MEDIO, 'auto' => true,
				'titolo' => 'Slug URL troppo lungo',
				'perche' => 'URL lunghi sono meno cliccabili, si troncano in SERP e diluiscono il peso delle keyword.',
				'soluzione' => 'Slug accorciato entro 60 caratteri senza stopword, con redirect 301 generato.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( mb_strlen( $d['slug'] ) > 60 ) {
							$out[] = Base::doc( $d, mb_strlen( $d['slug'] ) . ' caratteri, proposto: ' . Text::shortSlug( $d['focus'] ?: $d['titolo'] ) );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'ONP-08', 'area' => 'onpage', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'H1 non rilevabile nel contenuto',
				'perche' => 'Se il tema non stampa un H1 unico il documento perde il principale segnale di argomento: va verificato sul front-end.',
				'soluzione' => 'Nelle pagine Elementor impostare il titolo principale come H1, uno solo per pagina.',
				'check' => static function ( Site $s ) {
					// Nei temi WordPress l H1 e il titolo dell articolo, e lo
					// stampa il tema al momento di servire la pagina: nel
					// testo salvato non c e, e non ci deve essere. Cercarlo li
					// segnalava quasi ogni articolo del sito con un problema
					// che non esisteva e che nessuna correzione poteva
					// chiudere. Il sito dice se lo stampa: se lo stampa, qui
					// non c e niente da segnalare.
					if ( Base::loFaIlSito( $s, 'h1' ) ) {
						return array();
					}

					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( 0 === count( $d['h1'] ) && $d['parole'] > 100 ) {
							$out[] = Base::doc( $d, 'nessun tag H1 nel contenuto salvato' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'ONP-09', 'area' => 'onpage', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'H1 multipli nella stessa pagina',
				'perche' => 'Più H1 confondono la gerarchia semantica e diluiscono il tema principale.',
				'soluzione' => 'Lasciare un solo H1 e declassare gli altri a H2.',
				'check' => static function ( Site $s ) {
					// Quando il tema stampa gia il titolo come H1, basta un
					// solo H1 dentro al testo per averne due in pagina: il
					// doppione c e davvero, ed e quello scritto nel contenuto.
					$dalTema = Base::loFaIlSito( $s, 'h1' ) ? 1 : 0;

					$out = array();
					foreach ( $s->pubblicati as $d ) {
						$quanti = count( $d['h1'] ) + $dalTema;

						if ( $quanti <= 1 ) {
							continue;
						}

						// Dire «4 tag H1» e chiedere di crederci. Chi apre l
						// articolo ne vede uno e conclude che il gestionale
						// sbaglia, e non ha modo di verificare. Scritti quali
						// sono, bastano dieci secondi: o si riconoscono, o il
						// rilievo e sbagliato e si vede subito.
						$quali = array();

						foreach ( $d['h1'] as $h ) {
							$testo = trim( preg_replace( '/\s+/', ' ', (string) $h['testo'] ) );
							$quali[] = '«' . ( mb_strlen( $testo ) > 60 ? mb_substr( $testo, 0, 60 ) . '…' : $testo ) . '»';
						}

						if ( $dalTema ) {
							array_unshift( $quali, '«' . $d['titolo'] . '» (stampato dal tema)' );
						}

						$out[] = Base::doc(
							$d,
							$quanti . ' H1 in pagina: ' . implode( ', ', $quali )
						);
					}
					return $out;
				},
			),
			array(
				'id' => 'ONP-10', 'area' => 'onpage', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Nessun H2: contenuto senza struttura',
				'perche' => 'Senza sottotitoli il testo non è scansionabile, non genera featured snippet e i motori generativi non trovano blocchi citabili.',
				'soluzione' => 'Il piano di riscrittura fornisce una scaletta H2/H3 per ogni contenuto.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( 0 === count( $d['h2'] ) && $d['parole'] > 250 ) {
							$out[] = Base::doc( $d, '0 H2 su ' . $d['parole'] . ' parole' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'ONP-11', 'area' => 'onpage', 'gravita' => Base::BASSO, 'auto' => false,
				'titolo' => 'Gerarchia dei titoli saltata (es. H2 seguito da H4)',
				'perche' => 'I salti di livello rompono l outline semantico usato da crawler e lettori di schermo.',
				'soluzione' => 'Riordinare i livelli in sequenza.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						$prec = 0;
						$bad  = 0;
						foreach ( $d['titoli'] as $h ) {
							if ( $prec && $h['livello'] > $prec + 1 ) {
								$bad++;
							}
							$prec = $h['livello'];
						}
						if ( $bad ) {
							$out[] = Base::doc( $d, $bad . ' salti di livello' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'ONP-12', 'area' => 'onpage', 'gravita' => Base::MEDIO, 'auto' => true,
				'titolo' => 'Riassunto (excerpt) mancante',
				'perche' => 'L excerpt alimenta archivi, feed RSS, anteprime social e fa da riserva alla meta description.',
				'soluzione' => 'Generazione automatica di un estratto di 25-35 parole con la focus keyword.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( '' === $d['estratto'] ) {
							$out[] = Base::doc( $d, 'assente' );
						}
					}
					return $out;
				},
			),
		);
	}
}
