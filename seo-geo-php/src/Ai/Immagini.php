<?php
/**
 * Generazione delle immagini in evidenza mancanti.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Ai;

use SeoGeo\Bridge\WordPress;
use SeoGeo\Db;
use SeoGeo\Text;
use Throwable;

/**
 * Copre il problema IMG-05: senza immagine in evidenza mancano og:image e
 * twitter:image, le condivisioni social non hanno anteprima e Google Discover
 * esclude la pagina.
 *
 * Un avvertimento che vale la pena leggere: per una web agency le foto dei
 * lavori veri valgono più di qualsiasi immagine generata. Questo strumento
 * serve a coprire l archivio storico, non a sostituire il portfolio.
 */
class Immagini {

	/**
	 * Articoli pubblicati senza immagine in evidenza.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit.
	 * @param array $opzioni 'limite', 'rigenera', 'cartella'.
	 * @return array[]
	 */
	public static function candidati( Db $db, $auditId, array $opzioni = array() ) {
		$sql = "SELECT id, wp_id, titolo, slug, url, focus_keyword AS focus, tipo
				FROM documento
				WHERE audit_id = ? AND ha_thumbnail = 0 AND tipo = 'post'
				ORDER BY parole DESC";

		$righe = $db->all( $sql, array( $auditId ) );

		if ( empty( $opzioni['rigenera'] ) ) {
			$cartella = $opzioni['cartella'] ?? self::cartella( $auditId );

			$righe = array_values(
				array_filter(
					$righe,
					static fn( $r ) => ! is_file( $cartella . '/' . $r['slug'] . '.png' )
				)
			);
		}

		if ( ! empty( $opzioni['limite'] ) ) {
			$righe = array_slice( $righe, 0, (int) $opzioni['limite'] );
		}

		return $righe;
	}

	/**
	 * Cartella delle immagini di un audit.
	 *
	 * @param int $auditId Audit.
	 * @return string
	 */
	public static function cartella( $auditId ) {
		return dirname( __DIR__, 2 ) . '/storage/export/audit-' . (int) $auditId . '/immagini';
	}

	/**
	 * Descrizione visiva per il modello.
	 *
	 * Niente testo dentro l immagine: i modelli lo rendono male e un titolo
	 * scritto storto su un immagine in evidenza si nota subito.
	 *
	 * @param array $documento Documento.
	 * @param array $cfg       Configurazione.
	 * @return string
	 */
	public static function descrizione( array $documento, array $cfg ) {
		$tema  = $documento['focus'] ?: $documento['titolo'];
		$citta = $cfg['seo']['cittaPrincipale'];

		return "Fotografia editoriale orizzontale (16:9) per l'articolo di un'agenzia di comunicazione "
			. "che parla di: {$tema}.\n"
			. "Scena realistica di lavoro in un piccolo ufficio o in un'attività italiana, luce naturale morbida, "
			. "colori sobri, profondità di campo contenuta, nessuna posa da fotografia stock.\n"
			. "Ambientazione mediterranea coerente con {$citta}, senza monumenti riconoscibili.\n"
			. "Nessun testo, nessuna scritta, nessun logo, nessun marchio, nessun volto in primo piano riconoscibile. "
			. 'Composizione con spazio libero a sinistra per un eventuale titolo sovrapposto.';
	}

	/**
	 * Testo alternativo dell immagine.
	 *
	 * @param array $documento Documento.
	 * @param array $cfg       Configurazione.
	 * @return string
	 */
	public static function alt( array $documento, array $cfg ) {
		$tema = $documento['focus'] ?: $documento['titolo'];

		return Text::truncate( ucfirst( $tema ) . ' — ' . $cfg['azienda']['nome'] . ', ' . $cfg['seo']['cittaPrincipale'], 120 );
	}

	/**
	 * Genera le immagini mancanti.
	 *
	 * @param Db        $db      Database.
	 * @param Gemini    $gemini  Client.
	 * @param int       $auditId Audit.
	 * @param array     $cfg     Configurazione.
	 * @param array     $opzioni 'limite', 'rigenera', 'invia', 'su_progresso'.
	 * @param WordPress $ponte   Collegamento al sito, per l invio immediato.
	 * @return array
	 */
	public static function esegui( Db $db, Gemini $gemini, $auditId, array $cfg, array $opzioni = array(), WordPress $ponte = null ) {
		$cartella = $opzioni['cartella'] ?? self::cartella( $auditId );

		if ( ! is_dir( $cartella ) && ! mkdir( $cartella, 0775, true ) && ! is_dir( $cartella ) ) {
			throw new \RuntimeException( "Impossibile creare la cartella $cartella" );
		}

		$documenti = self::candidati( $db, $auditId, $opzioni + array( 'cartella' => $cartella ) );
		$progresso = $opzioni['su_progresso'] ?? null;
		$invia     = ! empty( $opzioni['invia'] ) && $ponte && $ponte->pronto();

		$fatte   = 0;
		$inviate = 0;
		$errori  = array();
		$scadenza = isset( $opzioni['secondi_max'] ) ? time() + (int) $opzioni['secondi_max'] : null;

		foreach ( $documenti as $doc ) {
			if ( $scadenza && time() > $scadenza ) {
				break;
			}

			try {
				list( $mime, $binario ) = $gemini->generaImmagine( self::descrizione( $doc, $cfg ) );

				$estensione = 'image/jpeg' === $mime ? 'jpg' : ( 'image/webp' === $mime ? 'webp' : 'png' );
				$percorso   = $cartella . '/' . $doc['slug'] . '.' . $estensione;
				file_put_contents( $percorso, $binario );
				$fatte++;

				if ( $invia ) {
					$ponte->inviaImmagine( (int) $doc['wp_id'], $binario, $mime, $doc['slug'], self::alt( $doc, $cfg ), true );
					$inviate++;
				}
			} catch ( Throwable $e ) {
				$errori[] = $doc['titolo'] . ': ' . $e->getMessage();
			}

			if ( $progresso ) {
				$progresso( $doc, $fatte, count( $errori ), count( $documenti ) );
			}
		}

		return array(
			'candidati' => count( $documenti ),
			'generate'  => $fatte,
			'inviate'   => $inviate,
			'errori'    => $errori,
			'cartella'  => $cartella,
			'consumo'   => $gemini->consumo(),
		);
	}
}
