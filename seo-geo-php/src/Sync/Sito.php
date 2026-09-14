<?php
/**
 * Lettura dello stato attuale del sito attraverso il plugin.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Sync;

use RuntimeException;
use SeoGeo\Bridge\WordPress;

/**
 * Sostituisce il caricamento manuale dell export WXR: interroga il plugin e
 * restituisce la stessa struttura che produce il parser, così l analisi non
 * sa nemmeno da dove arrivano i dati.
 *
 * Serve a rispondere alla domanda "il punteggio è migliorato?" senza dover
 * riesportare l XML da WordPress ogni volta.
 */
class Sito {

	/** @var WordPress */
	private $ponte;

	/** @var callable|null */
	private $progresso;

	/**
	 * @param WordPress     $ponte     Collegamento al sito.
	 * @param callable|null $progresso Richiamata per l avanzamento.
	 */
	public function __construct( WordPress $ponte, callable $progresso = null ) {
		$this->ponte     = $ponte;
		$this->progresso = $progresso;
	}

	/**
	 * Avvisa chi sta seguendo l operazione.
	 *
	 * @param string $messaggio Testo.
	 * @return void
	 */
	private function avvisa( $messaggio ) {
		if ( $this->progresso ) {
			call_user_func( $this->progresso, $messaggio );
		}
	}

	/**
	 * Riduce a stringa le meta che WordPress restituisce come array.
	 *
	 * rank_math_robots, per dirne una, in WordPress è un array ( index,
	 * follow ): letta dal sito arriva come array, letta dall export XML come
	 * stringa serializzata. Le regole si aspettano del testo, quindi qui le
	 * due strade vengono fatte coincidere.
	 *
	 * @param mixed $meta Meta del contenuto.
	 * @return array<string,string>
	 */
	private static function meta( $meta ) {
		$pulite = array();

		foreach ( (array) $meta as $chiave => $valore ) {
			if ( is_array( $valore ) ) {
				$valore = implode( ',', array_map( static fn( $v ) => is_scalar( $v ) ? (string) $v : '', $valore ) );
			} elseif ( is_bool( $valore ) ) {
				$valore = $valore ? '1' : '';
			} elseif ( ! is_scalar( $valore ) ) {
				$valore = '';
			}

			$pulite[ $chiave ] = (string) $valore;
		}

		return $pulite;
	}

	/**
	 * Scarica tutto il necessario e compone la struttura dell analisi.
	 *
	 * @return array
	 * @throws RuntimeException Se il sito non risponde come previsto.
	 */
	public function leggi() {
		$conteggi = $this->ponte->conteggi();

		if ( empty( $conteggi['sito'] ) ) {
			throw new RuntimeException(
				'Il plugin installato non sa ancora inviare i contenuti: aggiornalo alla versione 1.2.0.'
			);
		}

		$this->avvisa( sprintf( 'Il sito dichiara %d contenuti e %d file media.', (int) $conteggi['contenuti'], (int) $conteggi['allegati'] ) );

		$items      = array();
		$categorie  = array();
		$tag        = array();
		$totale     = (int) $conteggi['contenuti'];
		$passo      = 40;

		for ( $offset = 0; $offset < $totale; $offset += $passo ) {
			$blocco = $this->ponte->contenuti( $offset, $passo );

			foreach ( (array) ( $blocco['contenuti'] ?? array() ) as $contenuto ) {
				$items[] = array(
					'titolo'       => $contenuto['titolo'] ?? '',
					'link'         => $contenuto['link'] ?? '',
					'autore'       => $contenuto['autore'] ?? '',
					'contenuto'    => $contenuto['contenuto'] ?? '',
					'estratto'     => $contenuto['estratto'] ?? '',
					'wp_id'        => $contenuto['wp_id'] ?? '',
					'data'         => $contenuto['data'] ?? '',
					'modificato'   => $contenuto['modificato'] ?? '',
					'slug'         => $contenuto['slug'] ?? '',
					'stato'        => $contenuto['stato'] ?? 'publish',
					'tipo'         => $contenuto['tipo'] ?? 'post',
					'genitore'     => $contenuto['genitore'] ?? '0',
					'commenti'     => $contenuto['commenti'] ?? 'closed',
					'allegato_url' => '',
					'categorie'    => $contenuto['categorie'] ?? array(),
					'tag'          => $contenuto['tag'] ?? array(),
					'meta'         => self::meta( $contenuto['meta'] ?? array() ),
				);

				foreach ( (array) ( $contenuto['categorie'] ?? array() ) as $categoria ) {
					$categorie[ $categoria['slug'] ] = array( 'id' => '', 'slug' => $categoria['slug'], 'nome' => $categoria['nome'] );
				}

				foreach ( (array) ( $contenuto['tag'] ?? array() ) as $etichetta ) {
					$tag[ $etichetta['slug'] ] = array( 'id' => '', 'slug' => $etichetta['slug'], 'nome' => $etichetta['nome'] );
				}
			}

			$this->avvisa( sprintf( 'Letti %d contenuti su %d…', min( $totale, $offset + $passo ), $totale ) );
		}

		// Allegati: servono alle regole sulle immagini.
		$totale_allegati = (int) ( $conteggi['allegati'] ?? 0 );

		for ( $offset = 0; $offset < $totale_allegati; $offset += 100 ) {
			$blocco = $this->ponte->allegati( $offset, 100 );

			foreach ( (array) ( $blocco['allegati'] ?? array() ) as $allegato ) {
				$items[] = array(
					'titolo'       => $allegato['titolo'] ?? '',
					'link'         => '',
					'autore'       => '',
					'contenuto'    => '',
					'estratto'     => '',
					'wp_id'        => $allegato['wp_id'] ?? '',
					'data'         => '',
					'modificato'   => '',
					'slug'         => '',
					'stato'        => 'inherit',
					'tipo'         => 'attachment',
					'genitore'     => $allegato['genitore'] ?? '0',
					'commenti'     => 'closed',
					'allegato_url' => $allegato['url'] ?? '',
					'peso'         => (int) ( $allegato['peso'] ?? 0 ),
					'mime'         => $allegato['mime'] ?? '',
					'nel_testo'    => ! empty( $allegato['nel_testo'] ),
					'gia_ridotta'  => ! empty( $allegato['gia_ridotta'] ),
					'categorie'    => array(),
					'tag'          => array(),
					'meta'         => array( '_wp_attachment_image_alt' => $allegato['alt'] ?? '' ),
				);
			}
		}

		$this->avvisa( sprintf( 'Letti %d file media.', $totale_allegati ) );

		// Voci di menu: servono alle regole sull architettura del sito.
		$menu = $this->ponte->menu();

		foreach ( (array) ( $menu['voci'] ?? array() ) as $voce ) {
			$items[] = array(
				'titolo'       => $voce['titolo'] ?? '',
				'link'         => '',
				'autore'       => '',
				'contenuto'    => '',
				'estratto'     => '',
				'wp_id'        => '',
				'data'         => '',
				'modificato'   => '',
				'slug'         => '',
				'stato'        => 'publish',
				'tipo'         => 'nav_menu_item',
				'genitore'     => '0',
				'commenti'     => 'closed',
				'allegato_url' => '',
				'categorie'    => array(),
				'tag'          => array(),
				'meta'         => array(
					'_menu_item_type' => $voce['tipo'] ?? '',
					'_menu_item_url'  => $voce['url'] ?? '',
				),
			);
		}

		$autori = array();

		foreach ( (array) ( $conteggi['autori'] ?? array() ) as $autore ) {
			$autori[] = array(
				'id'    => $autore['id'] ?? '',
				'login' => $autore['login'] ?? '',
				'email' => $autore['email'] ?? '',
				'nome'  => $autore['nome'] ?? '',
				'first' => $autore['first'] ?? '',
				'last'  => $autore['last'] ?? '',
			);
		}

		return array(
			'sito'      => array(
				'titolo'      => $conteggi['sito']['titolo'] ?? '',
				'link'        => rtrim( (string) ( $conteggi['sito']['url'] ?? '' ), '/' ),
				'descrizione' => $conteggi['sito']['descrizione'] ?? '',
				'lingua'      => $conteggi['sito']['lingua'] ?? 'it-IT',
				'baseUrl'     => rtrim( (string) ( $conteggi['sito']['url'] ?? '' ), '/' ),
				// Che cosa stampa il plugin nella testata: i dati strutturati
				// stanno nel <head>, non nel testo degli articoli, e le
				// regole devono saperlo invece di cercarli dove non sono.
				'stampa'      => (array) ( $conteggi['sito']['stampa'] ?? array() ),
				'autori'      => $autori,
			),
			'items'     => $items,
			'categorie' => array_values( $categorie ),
			'tag'       => array_values( $tag ),
		);
	}
}
