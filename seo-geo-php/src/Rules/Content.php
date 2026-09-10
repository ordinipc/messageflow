<?php
/**
 * Regole sulla qualità dei contenuti.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Rules;

use SeoGeo\Site;
use SeoGeo\Text;

/**
 * Lunghezza, duplicazioni, freschezza e leggibilità.
 */
class Content {

	/**
	 * @return array[]
	 */
	public static function rules() {
		return array(
			array(
				'id' => 'CNT-01', 'area' => 'content', 'gravita' => Base::CRITICO, 'auto' => false,
				'titolo' => 'Contenuto molto scarno (meno di 300 parole)',
				'perche' => 'Sotto le 300 parole la pagina raramente soddisfa un intento di ricerca: Google la considera di scarso valore e spesso non la indicizza.',
				'soluzione' => 'Il piano di riscrittura indica la scaletta da sviluppare fino a 900-1.500 parole utili.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( $d['parole'] < 300 && ! preg_match( '/privacy|cookie|termini|grazie/i', $d['slug'] ) ) {
							$out[] = Base::doc( $d, $d['parole'] . ' parole' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'CNT-02', 'area' => 'content', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'Contenuto sotto la soglia competitiva (meno di 600 parole)',
				'perche' => 'Per query commerciali le pagine in prima pagina superano quasi sempre le 900 parole: con meno testo mancano entità e domande che i motori cercano.',
				'soluzione' => 'Espansione guidata: sezioni H2 aggiuntive, FAQ, caso studio, dati locali.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( $d['parole'] >= 300 && $d['parole'] < 600 ) {
							$out[] = Base::doc( $d, $d['parole'] . ' parole' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'CNT-03', 'area' => 'content', 'gravita' => Base::CRITICO, 'auto' => false,
				'titolo' => 'Contenuti quasi duplicati fra loro',
				'perche' => 'Testi sovrapposti generano cannibalizzazione e segnalano produzione in serie: Google ne indicizza uno solo e svaluta il resto.',
				'soluzione' => 'Unire in un unico articolo approfondito e impostare i 301 dalle versioni ridondanti.',
				'check' => static function ( Site $s ) {
					$docs = Base::filtra( $s, static fn( $d ) => $d['parole'] > 120 );
					$sh   = array();
					foreach ( $docs as $i => $d ) {
						$sh[ $i ] = Text::shingles( $d['testo'] );
					}
					$out = array();
					$n   = count( $docs );
					for ( $i = 0; $i < $n; $i++ ) {
						for ( $j = $i + 1; $j < $n; $j++ ) {
							$sim = Text::jaccard( $sh[ $i ], $sh[ $j ] );
							if ( $sim >= 0.28 ) {
								$out[] = Base::doc( $docs[ $i ], sprintf( 'similarità %d%% con %s', round( $sim * 100 ), $docs[ $j ]['percorso'] ) );
							}
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'CNT-04', 'area' => 'content', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'Contenuti non aggiornati da oltre 12 mesi',
				'perche' => 'La freschezza è un fattore di posizionamento per i temi in evoluzione e i motori generativi preferiscono citare fonti recenti.',
				'soluzione' => 'Piano di refresh: aggiornare dati, esempi, anno nel titolo e data di modifica.',
				'check' => static function ( Site $s ) {
					$out = array();
					$ora = time();
					foreach ( $s->pubblicati as $d ) {
						$t = strtotime( $d['modificato'] ?: $d['data'] );
						if ( $t && ( $ora - $t ) > 365 * 86400 ) {
							$out[] = Base::doc( $d, 'ultimo aggiornamento ' . substr( $d['modificato'] ?: $d['data'], 0, 10 ) . ' (' . round( ( $ora - $t ) / 86400 ) . ' giorni fa)' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'CNT-05', 'area' => 'content', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Frequenza di pubblicazione discontinua',
				'perche' => 'Lunghi periodi senza pubblicazioni riducono la frequenza di scansione di Googlebot e la percezione di sito attivo.',
				'soluzione' => 'Calendario editoriale con cadenza settimanale e cluster tematici.',
				'check' => static function ( Site $s ) {
					$mesi = array();
					foreach ( $s->articoli as $d ) {
						$m = substr( (string) $d['data'], 0, 7 );
						if ( '' !== $m ) {
							$mesi[ $m ] = ( $mesi[ $m ] ?? 0 ) + 1;
						}
					}
					ksort( $mesi );
					$chiavi = array_keys( $mesi );
					$out    = array();
					for ( $i = 1; $i < count( $chiavi ); $i++ ) {
						$a = new \DateTime( $chiavi[ $i - 1 ] . '-01' );
						$b = new \DateTime( $chiavi[ $i ] . '-01' );
						$diff = ( (int) $b->format( 'Y' ) - (int) $a->format( 'Y' ) ) * 12 + ( (int) $b->format( 'n' ) - (int) $a->format( 'n' ) );
						if ( $diff > 2 ) {
							$out[] = Base::sito( sprintf( 'nessuna pubblicazione tra %s e %s (%d mesi di silenzio)', $chiavi[ $i - 1 ], $chiavi[ $i ], $diff - 1 ) );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'CNT-06', 'area' => 'content', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Leggibilità bassa (indice Gulpease sotto 50)',
				'perche' => 'Testi difficili aumentano la frequenza di rimbalzo e riducono il tempo di permanenza, segnali correlati al posizionamento.',
				'soluzione' => 'Frasi sotto le 25 parole, paragrafi da 2-3 frasi, elenchi puntati.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( null !== $d['gulpease'] && $d['gulpease'] < 50 && $d['parole'] > 200 ) {
							$out[] = Base::doc( $d, 'Gulpease ' . $d['gulpease'] . '/100' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'CNT-07', 'area' => 'content', 'gravita' => Base::BASSO, 'auto' => true,
				'titolo' => 'CSS inline dentro il contenuto',
				'perche' => 'Il CSS finito nel campo contenuto viene indicizzato come testo, abbassa il rapporto testo/codice e può comparire negli snippet.',
				'soluzione' => 'Spostare gli stili nel foglio del tema o nel CSS personalizzato di Elementor.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( $d['ha_style'] ) {
							$out[] = Base::doc( $d, 'blocco <style> nel contenuto' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'CNT-08', 'area' => 'content', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Nessun elemento visuale o strutturato',
				'perche' => 'Blocchi di testo continuo non producono featured snippet e sono difficili da estrarre per le risposte generative.',
				'soluzione' => 'Aggiungere almeno un elenco, una tabella comparativa o un immagine con didascalia.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( $d['parole'] > 300 && 0 === $d['liste'] && 0 === $d['tabelle'] && 0 === count( $d['immagini'] ) ) {
							$out[] = Base::doc( $d, 'nessuna lista, tabella o immagine' );
						}
					}
					return $out;
				},
			),
		);
	}
}
