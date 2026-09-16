<?php
/**
 * Che cosa passa da un analisi alla successiva.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

/**
 * Ogni rilettura del sito crea un analisi nuova, con un suo numero. Le
 * riscritture, pero, erano legate all analisi in cui erano nate: rileggere il
 * sito le faceva sparire dalla vista, la riscrittura assistita ripartiva da
 * zero e si ripagava Gemini per rifare un lavoro gia fatto.
 *
 * Non tutto deve passare, e la differenza conta:
 *
 * - le RISCRITTURE si: sono lavoro fatto e pagato, e valgono finche
 *   l articolo esiste;
 * - i PROBLEMI RILEVATI no: una rilettura e una fotografia nuova del sito, e
 *   se una regola scatta di nuovo vuol dire che il problema c e davvero.
 *   Trascinarsi dietro i segni di «gia sistemato» nasconderebbe cose vere.
 *
 * E la ragione per cui i conti a volte risalgono dopo una rilettura: non e
 * il gestionale che dimentica, e il sito che risponde di nuovo.
 */
class Continuita {

	/**
	 * Porta le riscritture dell analisi precedente dentro a quella nuova.
	 *
	 * I contenuti si riconoscono dall identificativo WordPress, che non cambia
	 * fra una lettura e l altra: il percorso invece puo cambiare, e il numero
	 * di riga del documento cambia sempre.
	 *
	 * @param Db  $db      Database.
	 * @param int $nuovo   Analisi appena salvata.
	 * @param int $vecchio Analisi da cui prendere le riscritture.
	 * @return int Quante ne sono state portate avanti.
	 */
	public static function riportaBozze( Db $db, $nuovo, $vecchio ) {
		$nuovo   = (int) $nuovo;
		$vecchio = (int) $vecchio;

		if ( ! $nuovo || ! $vecchio || $nuovo === $vecchio ) {
			return 0;
		}

		// I contenuti della nuova analisi, per identificativo WordPress.
		$destinazione = array();

		foreach ( $db->all( "SELECT id, wp_id FROM documento WHERE audit_id = ? AND wp_id <> ''", array( $nuovo ) ) as $riga ) {
			$destinazione[ (string) $riga['wp_id'] ] = (int) $riga['id'];
		}

		if ( ! $destinazione ) {
			return 0;
		}

		// Quelli che una riscrittura ce l hanno gia nella nuova analisi si
		// lasciano stare: questa operazione si puo ripetere senza fare
		// doppioni.
		$gia = array();

		foreach ( $db->all( 'SELECT documento_id FROM bozza WHERE audit_id = ?', array( $nuovo ) ) as $riga ) {
			$gia[ (int) $riga['documento_id'] ] = true;
		}

		$righe = $db->all(
			"SELECT b.*, d.wp_id AS wp_origine
			 FROM bozza b JOIN documento d ON d.id = b.documento_id
			 WHERE b.audit_id = ? AND b.stato = 'ok' AND d.wp_id <> ''
			 ORDER BY b.id ASC",
			array( $vecchio )
		);

		$portate = 0;
		$viste   = array();

		foreach ( $righe as $b ) {
			$wp = (string) $b['wp_origine'];

			// Se di uno stesso contenuto ci sono piu riscritture si tiene la
			// piu recente: l ordine e crescente, quindi l ultima vince.
			if ( ! isset( $destinazione[ $wp ] ) ) {
				continue;
			}

			$docNuovo = $destinazione[ $wp ];

			if ( isset( $gia[ $docNuovo ] ) ) {
				continue;
			}

			$viste[ $docNuovo ] = $b;
		}

		foreach ( $viste as $docNuovo => $b ) {
			$db->insert(
				'bozza',
				array(
					'audit_id'         => $nuovo,
					'documento_id'     => $docNuovo,
					'wp_id'            => (string) $b['wp_id'],
					'stato'            => 'ok',
					'modello'          => (string) $b['modello'],
					'titolo'           => (string) $b['titolo'],
					'meta_title'       => (string) $b['meta_title'],
					'meta_description' => (string) $b['meta_description'],
					'in_breve'         => (string) $b['in_breve'],
					'corpo_html'       => (string) $b['corpo_html'],
					'faq'              => (string) $b['faq'],
					'da_verificare'    => (string) $b['da_verificare'],
					'note'             => (string) $b['note'],
					'parole'           => (int) $b['parole'],
					'token_in'         => (int) $b['token_in'],
					'token_out'        => (int) $b['token_out'],
					'errore'           => '',
					'creato_il'        => (string) $b['creato_il'],
					// Se era gia stata scritta sul sito lo resta: perdere
					// questo dato farebbe riproporre come «da applicare» un
					// testo che e gia online.
					'inviata_il'       => (string) ( $b['inviata_il'] ?? '' ),
					// Anche quello che la riscrittura non era riuscita a
					// chiudere: e la ragione per cui quel rilievo si
					// ripresenta, e perderla vuol dire ricominciare a
					// chiederselo a ogni rilettura.
					'rimaste'          => (string) ( $b['rimaste'] ?? '' ),
					'tentativi'        => (int) ( $b['tentativi'] ?? 0 ),
				)
			);

			$portate++;
		}

		return $portate;
	}

	/**
	 * L analisi piu recente dello stesso sito, se non e questa.
	 *
	 * Si puo restare per ore su una pagina di un analisi vecchia - il
	 * pilota, i problemi, le riscritture - senza accorgersene, e lavorare su
	 * una fotografia superata mentre ce n e una nuova.
	 *
	 * @param Db     $db      Database.
	 * @param int    $auditId Analisi che si sta guardando.
	 * @param string $sito    Indirizzo del sito.
	 * @return array Vuoto se questa e gia la piu recente.
	 */
	public static function piuRecente( Db $db, $auditId, $sito ) {
		$riga = $db->one(
			'SELECT id, creato_il FROM audit WHERE id > ? AND sito_url = ? ORDER BY id DESC LIMIT 1',
			array( (int) $auditId, (string) $sito )
		);

		return $riga ?: array();
	}

	/**
	 * L analisi precedente dello stesso sito, se c e.
	 *
	 * @param Db     $db      Database.
	 * @param int    $auditId Analisi corrente.
	 * @param string $sito    Indirizzo del sito.
	 * @return int Zero se non ce n e una.
	 */
	public static function precedente( Db $db, $auditId, $sito ) {
		$riga = $db->one(
			'SELECT id FROM audit WHERE id < ? AND sito_url = ? ORDER BY id DESC LIMIT 1',
			array( (int) $auditId, (string) $sito )
		);

		return $riga ? (int) $riga['id'] : 0;
	}
}
