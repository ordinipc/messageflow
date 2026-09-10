<?php
/**
 * Generazione delle bozze con il modello.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Ai;

use SeoGeo\Db;
use SeoGeo\Html;
use SeoGeo\Text;
use Throwable;

/**
 * Prende gli articoli classificati dall audit e produce una bozza per ciascuno.
 *
 * Le bozze non vengono mai pubblicate: restano nel database e come file HTML,
 * in attesa di revisione umana. È una scelta deliberata — pubblicare in massa
 * testo generato senza revisione è esattamente ciò che le linee guida antispam
 * di Google classificano come abuso di contenuti scalati.
 */
class Rewriter {

	/**
	 * Articoli candidati alla riscrittura.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit.
	 * @param array $opzioni 'categorie', 'limite', 'rigenera'.
	 * @return array[]
	 */
	public static function candidati( Db $db, $auditId, array $opzioni = array() ) {
		$categorie = $opzioni['categorie'] ?? array( 'riscrivere', 'accorpare' );
		$segnaposto = implode( ',', array_fill( 0, count( $categorie ), '?' ) );

		$sql = "SELECT t.*, d.id AS doc_id, d.wp_id, d.titolo, d.url, d.slug, d.parole, d.testo,
					d.focus_keyword AS focus, d.percorso
				FROM triage t
				JOIN documento d ON d.id = t.documento_id
				WHERE t.audit_id = ? AND t.categoria IN ($segnaposto)";

		$parametri = array_merge( array( $auditId ), array_values( $categorie ) );

		if ( empty( $opzioni['rigenera'] ) ) {
			$sql .= " AND d.id NOT IN (SELECT documento_id FROM bozza WHERE audit_id = ? AND stato = 'ok')";
			$parametri[] = $auditId;
		}

		// Prima gli articoli con l intento più commerciale e più corti: sono
		// quelli dove la riscrittura rende di più.
		$sql .= " ORDER BY CASE t.intento
					WHEN 'transazionale' THEN 0 WHEN 'commerciale' THEN 1 ELSE 2 END,
					d.parole ASC";

		if ( ! empty( $opzioni['limite'] ) ) {
			$sql .= ' LIMIT ' . (int) $opzioni['limite'];
		}

		return $db->all( $sql, $parametri );
	}

	/**
	 * Stima di token e costo senza chiamare l API.
	 *
	 * @param array $articoli Candidati.
	 * @param array $cfgAi    Sezione 'ai' della configurazione.
	 * @return array
	 */
	public static function stima( array $articoli, array $cfgAi ) {
		$in  = 0;
		$out = 0;

		foreach ( $articoli as $a ) {
			// Prompt: istruzioni fisse più il testo di partenza (troncato a 6.000 caratteri).
			$in  += Gemini::stimaToken( mb_substr( (string) $a['testo'], 0, 6000 ) ) + 900;
			$out += 2200; // Un articolo da 1.200 parole più le meta e le FAQ.
		}

		$prezzi = $cfgAi['prezzo_per_milione'] ?? array( 'input' => 0, 'output' => 0 );

		return array(
			'articoli'      => count( $articoli ),
			'token_in'      => $in,
			'token_out'     => $out,
			'costo_stimato' => round( $in / 1000000 * (float) $prezzi['input'] + $out / 1000000 * (float) $prezzi['output'], 2 ),
		);
	}

	/**
	 * Link interni suggeriti per un documento, dal piano già calcolato.
	 *
	 * @param Db  $db      Database.
	 * @param int $auditId Audit.
	 * @param string $percorso Percorso del documento.
	 * @return array<string,string> anchor => url
	 */
	private static function linkSuggeriti( Db $db, $auditId, $percorso ) {
		$righe = $db->all(
			'SELECT l.anchor, d.url FROM link_piano l
			 LEFT JOIN documento d ON d.audit_id = l.audit_id AND d.percorso = l.a
			 WHERE l.audit_id = ? AND l.da = ? LIMIT 4',
			array( $auditId, $percorso )
		);

		$out = array();

		foreach ( $righe as $r ) {
			if ( ! empty( $r['url'] ) ) {
				$out[ $r['anchor'] ] = $r['url'];
			}
		}

		return $out;
	}

	/**
	 * Genera le bozze.
	 *
	 * @param Db       $db       Database.
	 * @param Gemini   $gemini   Client.
	 * @param int      $auditId  Audit.
	 * @param array    $cfg      Configurazione completa.
	 * @param array    $opzioni  'categorie', 'limite', 'rigenera', 'cartella', 'su_progresso'.
	 * @return array Riepilogo dell esecuzione.
	 */
	public static function esegui( Db $db, Gemini $gemini, $auditId, array $cfg, array $opzioni = array() ) {
		$articoli   = self::candidati( $db, $auditId, $opzioni );
		$istruzioni = Prompt::istruzioni( $cfg );
		$modello    = $cfg['ai']['modello'] ?? 'gemini-2.5-flash';
		$cartella   = $opzioni['cartella'] ?? __DIR__ . '/../../storage/export/audit-' . (int) $auditId . '/bozze';
		$progresso  = $opzioni['su_progresso'] ?? null;

		if ( ! is_dir( $cartella ) ) {
			mkdir( $cartella, 0775, true );
		}

		$fatte   = 0;
		$fallite = 0;
		$scadenza = isset( $opzioni['secondi_max'] ) ? time() + (int) $opzioni['secondi_max'] : null;

		foreach ( $articoli as $a ) {
			if ( $scadenza && time() > $scadenza ) {
				break; // Su hosting condiviso conviene fermarsi prima del limite di esecuzione.
			}

			try {
				$link = self::linkSuggeriti( $db, $auditId, $a['percorso'] );
				$dati = $gemini->generaJson( $istruzioni, Prompt::articolo( $a, $a, $link, $cfg ) );

				$corpo = (string) ( $dati['corpo_html'] ?? '' );

				if ( '' === trim( $corpo ) ) {
					throw new \RuntimeException( 'il modello non ha restituito il corpo dell articolo' );
				}

				$parole = Text::wordCount( Html::stripTags( $corpo ) );

				$db->run( 'DELETE FROM bozza WHERE audit_id = ? AND documento_id = ?', array( $auditId, $a['doc_id'] ) );

				$db->insert(
					'bozza',
					array(
						'audit_id'         => $auditId,
						'documento_id'     => (int) $a['doc_id'],
						'wp_id'            => $a['wp_id'],
						'stato'            => 'ok',
						'modello'          => $modello,
						'titolo'           => (string) ( $dati['titolo'] ?? $a['titolo'] ),
						'meta_title'       => (string) ( $dati['meta_title'] ?? '' ),
						'meta_description' => (string) ( $dati['meta_description'] ?? '' ),
						'in_breve'         => (string) ( $dati['in_breve'] ?? '' ),
						'corpo_html'       => $corpo,
						'faq'              => json_encode( $dati['faq'] ?? array(), JSON_UNESCAPED_UNICODE ),
						'da_verificare'    => json_encode( $dati['da_verificare'] ?? array(), JSON_UNESCAPED_UNICODE ),
						'note'             => (string) ( $dati['note'] ?? '' ),
						'parole'           => $parole,
						'token_in'         => 0,
						'token_out'        => 0,
						'errore'           => '',
						'creato_il'        => date( 'Y-m-d H:i:s' ),
					)
				);

				file_put_contents( $cartella . '/' . $a['slug'] . '.html', self::fileBozza( $dati, $a, $modello ) );
				$fatte++;
			} catch ( Throwable $e ) {
				$db->insert(
					'bozza',
					array(
						'audit_id'     => $auditId,
						'documento_id' => (int) $a['doc_id'],
						'wp_id'        => $a['wp_id'],
						'stato'        => 'errore',
						'modello'      => $modello,
						'titolo'       => $a['titolo'],
						'errore'       => $e->getMessage(),
						'creato_il'    => date( 'Y-m-d H:i:s' ),
					)
				);
				$fallite++;
			}

			if ( $progresso ) {
				$progresso( $a, $fatte, $fallite, count( $articoli ) );
			}
		}

		return array(
			'candidati' => count( $articoli ),
			'generate'  => $fatte,
			'fallite'   => $fallite,
			'consumo'   => $gemini->consumo(),
			'cartella'  => $cartella,
		);
	}

	/**
	 * File HTML della bozza, pronto da incollare nell editor di WordPress.
	 *
	 * @param array  $dati     Risposta del modello.
	 * @param array  $articolo Articolo di partenza.
	 * @param string $modello  Modello usato.
	 * @return string
	 */
	private static function fileBozza( array $dati, array $articolo, $modello ) {
		$esc = static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' );

		$faq = '';
		foreach ( (array) ( $dati['faq'] ?? array() ) as $f ) {
			$faq .= '<h3>' . $esc( $f['domanda'] ?? '' ) . '</h3><p>' . $esc( $f['risposta'] ?? '' ) . "</p>\n";
		}

		$verifiche = '';
		foreach ( (array) ( $dati['da_verificare'] ?? array() ) as $v ) {
			$verifiche .= '<li>' . $esc( $v ) . "</li>\n";
		}

		return '<!-- BOZZA generata con ' . $esc( $modello ) . ' il ' . date( 'Y-m-d H:i' ) . " -->\n"
			. '<!-- Articolo di partenza: ' . $esc( $articolo['url'] ) . " -->\n"
			. '<!-- DA RIVEDERE PRIMA DELLA PUBBLICAZIONE: sostituisci ogni [DA VERIFICARE: ...] con dati reali -->'
			. "\n\n<!-- Title SEO: " . $esc( $dati['meta_title'] ?? '' ) . " -->\n"
			. '<!-- Meta description: ' . $esc( $dati['meta_description'] ?? '' ) . " -->\n\n"
			. '<h1>' . $esc( $dati['titolo'] ?? $articolo['titolo'] ) . "</h1>\n\n"
			. '<div class="mdi-in-breve"><p><strong>In breve:</strong> ' . $esc( $dati['in_breve'] ?? '' ) . "</p></div>\n\n"
			. ( $dati['corpo_html'] ?? '' ) . "\n"
			. ( '' !== $faq ? "\n<h2>Domande frequenti</h2>\n" . $faq : '' )
			. ( '' !== $verifiche ? "\n<!-- DATI DA INSERIRE PRIMA DI PUBBLICARE:\n<ul>\n" . $verifiche . "</ul>\n-->\n" : '' );
	}
}
