<?php
/**
 * Controlla che una riscrittura chiuda davvero i problemi per cui e nata.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Ai;

use SeoGeo\Audit;
use SeoGeo\Site;

/**
 * La bozza veniva salvata comunque, qualunque cosa contenesse.
 *
 * Se il modello rispondeva con 412 parole a un articolo segnalato «sotto le
 * 600», la bozza si pubblicava lo stesso e alla rilettura successiva il
 * rilievo era ancora li. Stessa cosa per gli H2 mancanti, per la tabella, per
 * le domande frequenti. Da fuori si vedeva solo che i numeri non scendevano
 * mai - «li correggo e riappaiono sempre» - e ogni giro costava token.
 *
 * Qui la bozza viene misurata prima di salvarla, con le stesse regole che
 * hanno aperto il rilievo: non una copia delle condizioni scritta a parte,
 * che col tempo si scosta, ma proprio quelle. Si costruisce un sito finto con
 * dentro il solo articolo riscritto e ci si passa sopra l audit.
 *
 * Quello che non chiude torna al modello una seconda volta, detto in termini
 * verificabili («il testo ha 412 parole, ne servono almeno 600»). Se nemmeno
 * il secondo tentativo basta, la bozza si tiene - migliorare qualcosa e
 * meglio di niente - ma resta scritto che cosa non ha chiuso, cosi chi guarda
 * non deve scoprirlo dalla rilettura di domani.
 */
class Chiusura {

	/**
	 * Le regole che si possono misurare sul testo di una bozza.
	 *
	 * Fuori restano quelle che una riscrittura non puo chiudere da sola:
	 * IMG-05 vuole un immagine caricata, CNT-03 / ONP-06 / LOC-05 vogliono
	 * due articoli fusi, GEO-11 guarda l archivio intero e non il singolo
	 * pezzo. Prometterle qui vorrebbe dire ritentare all infinito qualcosa
	 * che in questo punto non si risolve.
	 *
	 * @var string[]
	 */
	const VERIFICABILI = array(
		'CNT-01',
		'CNT-02',
		'CNT-06',
		'CNT-07',
		'CNT-08',
		'CNT-09',
		'GEO-03',
		'GEO-04',
		'GEO-10',
		'LOC-07',
		'ONP-09',
		'ONP-10',
		'ONP-11',
	);

	/**
	 * Quali fra queste regole si sanno misurare.
	 *
	 * @param array $regole Identificativi.
	 * @return string[]
	 */
	public static function verificabili( array $regole ) {
		return array_values( array_intersect( array_map( 'strval', $regole ), self::VERIFICABILI ) );
	}

	/**
	 * Il testo come finira sull articolo.
	 *
	 * Sintesi, corpo e domande frequenti, nello stesso ordine in cui li
	 * mette il plugin al momento di scrivere. Misurare il solo corpo_html
	 * darebbe «nessuna domanda fra i titoli» su una bozza che le domande ce
	 * le ha: sono nel campo faq, e in pagina diventano H2 e H3.
	 *
	 * @param array $dati Risposta del modello.
	 * @return string
	 */
	public static function testoCompleto( array $dati ) {
		$html = '';

		if ( '' !== trim( (string) ( $dati['in_breve'] ?? '' ) ) ) {
			$html .= '<div class="mdi-in-breve"><p><strong>In breve:</strong> ' . $dati['in_breve'] . "</p></div>\n\n";
		}

		$html .= (string) ( $dati['corpo_html'] ?? '' );

		foreach ( (array) ( $dati['faq'] ?? array() ) as $i => $voce ) {
			if ( 0 === $i ) {
				$html .= "\n\n<h2>Domande frequenti</h2>\n";
			}

			$html .= '<h3>' . (string) ( $voce['domanda'] ?? '' ) . "</h3>\n"
				. '<p>' . (string) ( $voce['risposta'] ?? '' ) . "</p>\n";
		}

		return $html;
	}

	/**
	 * Che cosa resta aperto dopo la riscrittura.
	 *
	 * @param array $dati     Risposta del modello.
	 * @param array $articolo Riga del documento (url, slug, titolo, focus).
	 * @param array $regole   Regole che questa riscrittura doveva chiudere.
	 * @param array $stampa   Che cosa stampa il sito: serve a ONP-09, perche
	 *                        con l H1 messo dal tema basta un H1 nel testo
	 *                        per averne due in pagina. Quando non si sa si
	 *                        prende la lettura piu severa - il tema lo
	 *                        stampa - perche una bozza senza H1 nel corpo va
	 *                        bene in tutti e due i casi: il titolo diventa
	 *                        H1 da se. Il contrario no.
	 * @return array[] 'regola', 'titolo', 'dettaglio'.
	 */
	public static function controlla( array $dati, array $articolo, array $regole, array $stampa = array() ) {
		$stampa = $stampa ?: array( 'h1' => true );

		$regole = self::verificabili( $regole );

		if ( ! $regole ) {
			return array();
		}

		$sito   = self::sitoFinto( self::testoCompleto( $dati ), $articolo, $dati, $stampa );
		$aperte = array();

		foreach ( Audit::regole() as $regola ) {
			if ( ! in_array( (string) $regola['id'], $regole, true ) ) {
				continue;
			}

			try {
				$problemi = call_user_func( $regola['check'], $sito );
			} catch ( \Throwable $e ) {
				// Una regola che non gira non e una regola superata: si
				// lascia stare, senza ne chiudere ne riaprire niente.
				continue;
			}

			if ( ! $problemi ) {
				continue;
			}

			$aperte[] = array(
				'regola'    => (string) $regola['id'],
				'titolo'    => (string) $regola['titolo'],
				'dettaglio' => (string) ( $problemi[0]['dettaglio'] ?? '' ),
			);
		}

		return $aperte;
	}

	/**
	 * Un sito con dentro un articolo solo: quello appena riscritto.
	 *
	 * @param string $html     Testo completo.
	 * @param array  $articolo Riga del documento.
	 * @param array  $dati     Risposta del modello.
	 * @param array  $stampa   Che cosa stampa il sito.
	 * @return Site
	 */
	private static function sitoFinto( $html, array $articolo, array $dati, array $stampa ) {
		$url = (string) ( $articolo['url'] ?? 'https://esempio.it/articolo/' );

		return new Site(
			array(
				'sito'      => array(
					'link'    => $url,
					'baseUrl' => $url,
					'autori'  => array(),
					'stampa'  => $stampa,
				),
				'categorie' => array(),
				'tag'       => array(),
				'items'     => array(
					array(
						'titolo'       => (string) ( $dati['titolo'] ?? ( $articolo['titolo'] ?? '' ) ),
						'link'         => $url,
						'autore'       => '',
						'contenuto'    => $html,
						'estratto'     => '',
						'wp_id'        => (string) ( $articolo['wp_id'] ?? '0' ),
						'data'         => date( 'Y-m-d H:i:s' ),
						'modificato'   => date( 'Y-m-d H:i:s' ),
						'slug'         => (string) ( $articolo['slug'] ?? '' ),
						'stato'        => 'publish',
						'tipo'         => 'post',
						'genitore'     => '',
						'commenti'     => 'open',
						'allegato_url' => '',
						'categorie'    => array(),
						'tag'          => array(),
						'meta'         => array(
							'rank_math_title'         => (string) ( $dati['meta_title'] ?? '' ),
							'rank_math_description'   => (string) ( $dati['meta_description'] ?? '' ),
							// La focus keyword non la decide la riscrittura:
							// resta quella dell articolo, ed e da li che
							// LOC-07 capisce se la pagina punta a una
							// ricerca locale.
							'rank_math_focus_keyword' => (string) ( $articolo['focus'] ?? '' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Che cosa dire al modello al secondo tentativo.
	 *
	 * In termini che si possono controllare: non «l articolo e corto», ma
	 * quante parole ha e quante ne servono. Un istruzione che non si puo
	 * verificare produce un secondo tentativo identico al primo.
	 *
	 * @param array[] $aperte Esito di controlla().
	 * @return string Vuoto se non c e niente da ridire.
	 */
	public static function istruzioni( array $aperte ) {
		if ( ! $aperte ) {
			return '';
		}

		$come = array(
			'CNT-01' => 'Porta il testo oltre le 300 parole con contenuto vero, non con giri di frase.',
			'CNT-02' => 'Porta il testo oltre le 600 parole: servono sezioni in piu, non paragrafi piu lunghi.',
			'CNT-06' => 'Spezza le frasi: sotto le 25 parole ciascuna, paragrafi di 2-3 frasi, parole comuni.',
			'CNT-07' => 'Togli ogni blocco <style> e ogni attributo style dal corpo.',
			'CNT-08' => 'Aggiungi almeno un elenco puntato o una tabella.',
			'CNT-09' => 'Riscrivi gli H2 che ripetono il titolo dell articolo: devono dire di che cosa parla la sezione.',
			'GEO-03' => 'Il campo in_breve deve contenere una sintesi di 40-60 parole che risponde subito.',
			'GEO-04' => 'Il campo faq deve contenere almeno tre domande vere con la risposta.',
			'GEO-10' => 'Aggiungi una tabella <table> con dati gia presenti nel testo (mai prezzi inventati).',
			'LOC-07' => 'Cita la citta almeno tre volte nel testo, dentro frasi con un senso.',
			'ONP-09' => 'Nessun <h1> nel corpo: il titolo lo stampa gia il tema. Usa <h2>.',
			'ONP-10' => 'Dai al testo una struttura di <h2>: almeno tre sezioni.',
			'ONP-11' => 'I livelli dei titoli in sequenza: dopo un <h2> puo venire un <h3>, mai un <h4>.',
		);

		$righe = '';

		foreach ( $aperte as $a ) {
			$righe .= sprintf(
				"- %s%s %s\n",
				$a['titolo'],
				'' !== (string) $a['dettaglio'] ? ' (adesso: ' . $a['dettaglio'] . ')' : '',
				$come[ $a['regola'] ] ?? ''
			);
		}

		return "IL TENTATIVO PRECEDENTE NON HA CHIUSO QUESTI PUNTI: RIFALLO SISTEMANDOLI TUTTI\n"
			. "Sono misurati sul testo che hai appena prodotto, non opinioni.\n"
			. $righe;
	}

	/**
	 * Come si scrive, nel registro, quello che non si e chiuso.
	 *
	 * @param array[] $aperte Esito di controlla().
	 * @return string
	 */
	public static function riassunto( array $aperte ) {
		$pezzi = array();

		foreach ( $aperte as $a ) {
			$pezzi[] = $a['regola'] . ' ' . $a['titolo'];
		}

		return implode( ' · ', $pezzi );
	}
}
