<?php
/**
 * Regole GEO: Generative Engine Optimization.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Rules;

use SeoGeo\Site;

/**
 * Quanto il sito è estraibile, attribuibile e citabile dai motori generativi
 * (AI Overviews, ChatGPT Search, Perplexity, Copilot, Gemini).
 */
class Generative {

	/**
	 * @return array[]
	 */
	public static function rules() {
		return array(
			array(
				'id' => 'GEO-01', 'area' => 'generative', 'gravita' => Base::CRITICO, 'auto' => true,
				'titolo' => 'File llms.txt assente',
				'perche' => 'llms.txt è lo standard con cui si dichiara ai modelli quali contenuti del sito sono autorevoli e come vanno citati. Senza, il crawler AI deve indovinare la struttura del sito.',
				'soluzione' => 'Generazione di llms.txt e llms-full.txt con mappa dei servizi, delle guide e dei dati aziendali.',
				'check' => static function ( Site $s ) {
					return array( Base::sito( 'nessun llms.txt dichiarato: da pubblicare in radice' ) );
				},
			),
			array(
				'id' => 'GEO-02', 'area' => 'generative', 'gravita' => Base::CRITICO, 'auto' => true,
				'titolo' => 'robots.txt non regola i crawler AI',
				'perche' => 'Se GPTBot, ClaudeBot, PerplexityBot e Google-Extended non sono ammessi esplicitamente il sito non può comparire nelle risposte AI, che intercettano una quota crescente di ricerche informazionali.',
				'soluzione' => 'robots.txt rigenerato con allow espliciti per i crawler generativi, sitemap e llms.txt.',
				'check' => static function ( Site $s ) {
					return array( Base::sito( 'robots.txt da rigenerare con direttive esplicite per i crawler AI' ) );
				},
			),
			array(
				'id' => 'GEO-03', 'area' => 'generative', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Nessuna risposta diretta in apertura',
				'perche' => 'I modelli generativi estraggono la risposta dalle prime 40-60 parole dopo il titolo: se l articolo apre con un introduzione narrativa non c è nulla da citare.',
				'soluzione' => 'Blocco "In breve" di 40-60 parole in cima a ogni articolo.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( $d['parole'] <= 250 ) {
							continue;
						}
						if ( ! preg_match( '/in breve|in sintesi|risposta rapida|tl;dr|punti chiave/i', mb_substr( $d['testo'], 0, 600 ) ) ) {
							$out[] = Base::doc( $d, 'nessun blocco di sintesi iniziale citabile' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'GEO-04', 'area' => 'generative', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Nessuna sezione FAQ esplicita',
				'perche' => 'Le coppie domanda/risposta brevi sono il formato più citato dai motori generativi e alimentano i rich result FAQ.',
				'soluzione' => 'Blocco FAQ con 3-5 domande reali più schema FAQPage.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( $d['parole'] <= 250 ) {
							continue;
						}
						$haDomande = false;
						foreach ( $d['titoli'] as $h ) {
							if ( Base::eDomanda( $h['testo'] ) ) {
								$haDomande = true;
								break;
							}
						}
						if ( ! $haDomande ) {
							$out[] = Base::doc( $d, 'nessuna domanda fra i titoli di sezione' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'GEO-05', 'area' => 'generative', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'Contenuto senza dati, numeri o fonti citabili',
				'perche' => 'Statistiche, citazioni e fonti aumentano molto la probabilità che un contenuto venga ripreso dentro una risposta generativa.',
				'soluzione' => 'Inserire almeno due dati verificabili con fonte per ogni articolo pilastro.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( $d['parole'] <= 300 ) {
							continue;
						}
						$n = preg_match_all( '/\b\d{1,3}(?:[.,]\d+)?\s?%|\b\d{4}\b|\b\d+\s?(?:milioni|miliardi|mila)\b/iu', $d['testo'] );
						if ( $n < 2 ) {
							$out[] = Base::doc( $d, 'nessun dato numerico o percentuale nel testo' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'GEO-06', 'area' => 'generative', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Autore non attribuito a una persona reale',
				'perche' => 'I motori generativi valutano l attribuibilità: un contenuto firmato da una persona con biografia e profili verificabili è più affidabile di uno firmato da un login generico.',
				'soluzione' => 'Schema Person e author box; rinominare gli utenti WordPress con nome e cognome reali.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->autori as $a ) {
						if ( '' === $a['first'] || '' === $a['last'] || preg_match( '/@|\d$|digital$/i', $a['nome'] ) ) {
							$out[] = Base::sito( 'autore "' . ( $a['nome'] ?: $a['login'] ) . '" senza nome e cognome reali' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'GEO-07', 'area' => 'generative', 'gravita' => Base::MEDIO, 'auto' => true,
				'titolo' => 'Nessun markup speakable',
				'perche' => 'Lo schema speakable indica quali frammenti sono adatti alla lettura vocale e alla sintesi: aiuta assistenti vocali e riassunti AI.',
				'soluzione' => 'speakable aggiunto allo schema Article dal plugin.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( ! preg_match( '/speakable/i', $d['contenuto'] ) ) {
							$out[] = Base::doc( $d, 'speakable assente' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'GEO-08', 'area' => 'generative', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'Entità del brand non definita in modo coerente',
				'perche' => 'Perché un modello citi l azienda deve trovare la stessa descrizione ripetuta in modo coerente su sito, schema e profili esterni collegati con sameAs.',
				'soluzione' => 'Definire una descrizione canonica dell entità e replicarla identica ovunque.',
				'check' => static function ( Site $s ) {
					$org  = false;
					$same = false;
					foreach ( $s->pubblicati as $d ) {
						if ( preg_match( '/"@type"\s*:\s*"Organization"/i', $d['contenuto'] ) ) {
							$org = true;
						}
						if ( preg_match( '/sameAs/i', $d['contenuto'] ) ) {
							$same = true;
						}
					}
					$out = array();
					if ( ! $org ) {
						$out[] = Base::sito( 'nessuno schema Organization che definisca l entità aziendale' );
					}
					if ( ! $same ) {
						$out[] = Base::sito( 'nessun sameAs verso profili social o directory: l entità non è collegabile' );
					}
					return $out;
				},
			),
			array(
				'id' => 'GEO-09', 'area' => 'generative', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Contenuti senza aggiornamento datato',
				'perche' => 'I motori generativi privilegiano fonti recenti e con data esplicita: senza "aggiornato al" il contenuto è considerato potenzialmente obsoleto.',
				'soluzione' => 'Mostrare data di pubblicazione e ultimo aggiornamento, allineate a dateModified.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( $d['parole'] > 300 && substr( (string) $d['data'], 0, 10 ) === substr( (string) $d['modificato'], 0, 10 ) ) {
							$out[] = Base::doc( $d, 'mai aggiornato dalla pubblicazione (' . substr( (string) $d['data'], 0, 10 ) . ')' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'GEO-10', 'area' => 'generative', 'gravita' => Base::MEDIO, 'auto' => true,
				'titolo' => 'Nessun blocco di dati sintetici (tabelle)',
				'perche' => 'Tabelle e liste chiave/valore sono la struttura che i modelli estraggono con più affidabilità: prezzi, tempi, requisiti, confronti.',
				'soluzione' => 'Aggiungere per ogni servizio una tabella "cosa include, tempi, a chi serve".',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( $d['parole'] > 400 && 0 === $d['tabelle'] ) {
							$out[] = Base::doc( $d, 'nessuna tabella di dati strutturati' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'GEO-11', 'area' => 'generative', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Titoli non allineati alle query conversazionali',
				'perche' => 'Le ricerche nei motori generativi sono frasi lunghe e in forma di domanda: i titoli devono rispecchiare quel linguaggio per intercettarle.',
				'soluzione' => 'Riformulare i titoli di sezione come domande reali.',
				'check' => static function ( Site $s ) {
					$q = 0;
					foreach ( $s->pubblicati as $d ) {
						if ( false !== strpos( $d['titolo'], '?' ) ) {
							$q++;
						}
					}
					$quota = (int) round( $q / max( 1, count( $s->pubblicati ) ) * 100 );
					return $quota < 25
						? array( Base::sito( "solo $q contenuti su " . count( $s->pubblicati ) . " ($quota%) hanno un titolo in forma di domanda" ) )
						: array();
				},
			),
		);
	}
}
