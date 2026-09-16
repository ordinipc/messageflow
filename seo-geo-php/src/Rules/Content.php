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
			array(
				'id' => 'CNT-09', 'area' => 'content', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Titolo dell articolo ripetuto dentro ai titoletti',
				'perche' => 'Ripetere la stessa frase lunga in piu H2 e riempimento di parole chiave: Google lo riconosce da anni e non premia la pagina, mentre per chi legge i titoletti smettono di dire dove si trova.',
				'soluzione' => 'Riscrivere gli H2 in modo che dicano di che cosa parla la sezione, con parole diverse. La riscrittura assistita lo fa da sola.',
				'check' => static function ( Site $s ) {
					$out = array();

					foreach ( $s->pubblicati as $d ) {
						$titolo = self::confrontabile( $d['titolo'] );

						// Sotto una certa lunghezza non e riempimento: un
						// titolo di due parole puo ricomparire senza colpa.
						if ( mb_strlen( $titolo ) < 25 ) {
							continue;
						}

						$dentro = 0;

						foreach ( $d['titoli'] as $h ) {
							if ( 1 === (int) $h['livello'] ) {
								continue;
							}

							if ( false !== mb_strpos( self::confrontabile( $h['testo'] ), $titolo ) ) {
								$dentro++;
							}
						}

						if ( $dentro >= 2 ) {
							$out[] = Base::doc( $d, $dentro . ' titoletti contengono il titolo intero dell articolo' );
						}
					}

					return $out;
				},
			),
			array(
				'id' => 'CNT-10', 'area' => 'content', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Anno superato nel titolo',
				'perche' => 'Un anno vecchio nel titolo si vede in SERP prima di ogni altra cosa: «Strategie 2025» letto nel 2026 dice «questo articolo è di un altro anno» e il clic va al risultato sotto. Google usa anche la freschezza dichiarata per le ricerche che la chiedono.',
				'soluzione' => 'Portare l anno a quello corrente insieme al contenuto che lo giustifica. La riscrittura assistita lo fa e poi controlla di averlo fatto.',
				'check' => static function ( Site $s ) {
					$out  = array();
					$anno = (int) date( 'Y' );

					foreach ( $s->pubblicati as $d ) {
						// Il titolo che si vede in SERP puo essere quello SEO
						// o quello dell articolo: sono due posti diversi e
						// l anno vecchio in uno solo basta a rovinare il clic.
						$vecchi = self::anniSuperati( $d['titolo'] . ' ' . $d['seo_title'], $anno );

						if ( $vecchi ) {
							$out[] = Base::doc( $d, 'il titolo dice ' . implode( ', ', $vecchi ) . ', siamo nel ' . $anno );
						}
					}

					return $out;
				},
			),
			array(
				'id' => 'CNT-11', 'area' => 'content', 'gravita' => Base::MEDIO, 'auto' => true,
				'titolo' => 'Anno superato dentro al testo',
				'perche' => 'Frasi come «nel 2025» o «guida aggiornata al 2025» dicono a chi legge, e ai modelli che citano, che il pezzo non è più attuale: il contenuto può anche essere valido, ma si presenta scaduto.',
				'soluzione' => 'Aggiornare l anno dove è una promessa di attualità. Dove invece è un fatto («dal 2015 lavoriamo a Palermo») si lascia stare: qui non viene segnalato.',
				'check' => static function ( Site $s ) {
					$out  = array();
					$anno = (int) date( 'Y' );

					foreach ( $s->pubblicati as $d ) {
						$vecchi = self::anniDiAttualita( $d['testo'], $anno );

						if ( $vecchi ) {
							$out[] = Base::doc( $d, implode( ', ', $vecchi ) . ' dato come anno in corso, siamo nel ' . $anno );
						}
					}

					return $out;
				},
			),
		);
	}

	/**
	 * Gli anni superati citati in un testo breve, tipo un titolo.
	 *
	 * Si guardano solo gli ultimi sei anni: piu indietro un anno e quasi
	 * sempre un fatto - quando e nata l azienda, quando e uscita una legge -
	 * e segnalarlo vorrebbe dire chiedere di cambiare una cosa vera.
	 * L anno in corso e quelli futuri non sono superati.
	 *
	 * @param string $testo Testo.
	 * @param int    $anno  Anno corrente.
	 * @return string[] Anni trovati, dal piu recente.
	 */
	public static function anniSuperati( $testo, $anno ) {
		$anno = (int) $anno;

		if ( ! preg_match_all( '/\b(20\d{2})\b/', (string) $testo, $m ) ) {
			return array();
		}

		$trovati = array();

		foreach ( array_map( 'intval', $m[1] ) as $trovato ) {
			if ( $trovato < $anno && $trovato >= $anno - 6 ) {
				$trovati[ $trovato ] = true;
			}
		}

		krsort( $trovati );

		return array_map( 'strval', array_keys( $trovati ) );
	}

	/**
	 * Gli anni che dentro a un testo lungo si danno per correnti.
	 *
	 * Qui non basta trovare il numero. «Dal 2015 lavoriamo a Palermo» e vero
	 * e deve restare; «le strategie del 2025» no. La differenza sta nella
	 * parola che precede, e si cercano solo quelle che promettono attualita:
	 * cosi un anno storico non diventa un rilievo da correggere.
	 *
	 * @param string $testo Testo dell articolo.
	 * @param int    $anno  Anno corrente.
	 * @return string[]
	 */
	public static function anniDiAttualita( $testo, $anno ) {
		$anno   = (int) $anno;
		$schema = '/\b(?:nel|del|per il|entro il|aggiornat[oaie]+ al|aggiornat[oaie]+ a|guida|trend|novit[aà]|edizione|previsioni|strategie|classifica)\s+(?:del\s+)?(20\d{2})\b/iu';

		if ( ! preg_match_all( $schema, (string) $testo, $m ) ) {
			return array();
		}

		$trovati = array();

		foreach ( array_map( 'intval', $m[1] ) as $trovato ) {
			if ( $trovato < $anno && $trovato >= $anno - 6 ) {
				$trovati[ $trovato ] = true;
			}
		}

		krsort( $trovati );

		return array_map( 'strval', array_keys( $trovati ) );
	}

	/**
	 * Forma confrontabile di un testo: senza accenti di formattazione,
	 * spazi doppi e differenze di maiuscole.
	 *
	 * @param string $testo Testo.
	 * @return string
	 */
	private static function confrontabile( $testo ) {
		$pulito = mb_strtolower( trim( (string) $testo ) );

		return trim( preg_replace( '/\s+/u', ' ', $pulito ) );
	}
}
