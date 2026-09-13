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
				WHERE audit_id = ? AND ha_thumbnail = 0 AND tipo = 'post'";

		$parametri = array( $auditId );

		if ( ! empty( $opzioni['solo_documento'] ) ) {
			$sql        .= ' AND id = ?';
			$parametri[] = (int) $opzioni['solo_documento'];
		}

		$righe = $db->all( $sql . ' ORDER BY parole DESC', $parametri );

		if ( empty( $opzioni['rigenera'] ) ) {
			$cartella = $opzioni['cartella'] ?? self::cartella( $auditId );

			// Si cerca in tutte le estensioni possibili: cercando solo il .png
			// una immagine gia generata in WebP non verrebbe trovata e si
			// pagherebbe una seconda volta per rifare la stessa cosa.
			$righe = array_values(
				array_filter(
					$righe,
					static function ( $r ) use ( $cartella ) {
						foreach ( array( 'webp', 'png', 'jpg' ) as $estensione ) {
							if ( is_file( $cartella . '/' . $r['slug'] . '.' . $estensione ) ) {
								return false;
							}
						}

						return true;
					}
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
	 * Riduce e converte l immagine prima di caricarla sul sito.
	 *
	 * Il modello restituisce un PNG da uno o due megabyte. Caricato cosi
	 * com e risolve IMG-05 (manca l immagine in evidenza) ma fa scattare
	 * IMG-03 (oltre 200 KB) e IMG-04 (formato non moderno): si sostituisce
	 * un problema con due, e soprattutto si mettono sul sito duecento file
	 * pesanti che peggiorano LCP su altrettanti articoli.
	 *
	 * WebP a lato lungo 1200 e qualita 82 sta sotto i 200 KB su qualsiasi
	 * fotografia. Se GD non c e o non sa scrivere WebP si tiene l originale:
	 * meglio un immagine pesante che nessuna immagine.
	 *
	 * @param string $binario Immagine come arriva dal modello.
	 * @param string $mime    Tipo dichiarato dal modello.
	 * @param array  $cfg     Configurazione.
	 * @return array{0:string,1:string} Mime e binario da caricare.
	 */
	public static function ottimizza( $binario, $mime, array $cfg = array() ) {
		if ( ! function_exists( 'imagewebp' ) || ! function_exists( 'imagecreatefromstring' ) ) {
			return array( $mime, $binario );
		}

		$lato    = (int) ( $cfg['ai']['immagine_lato_max'] ?? 1200 );
		$qualita = (int) ( $cfg['ai']['immagine_qualita'] ?? 82 );
		$peso    = (int) ( $cfg['ai']['immagine_peso_max'] ?? 190000 );

		$immagine = @imagecreatefromstring( $binario );

		if ( ! $immagine ) {
			return array( $mime, $binario );
		}

		$webp = '';

		try {
			// Non basta una qualita fissa: su una fotografia molto granulosa
			// 1200px a qualita 82 esce ancora sopra i 200 KB, e la regola
			// IMG-03 scatterebbe lo stesso. Si scende per gradi finche il file
			// sta sotto la soglia, prima sulla qualita e poi sulle dimensioni,
			// fermandosi al primo tentativo che ci riesce.
			foreach ( array( $lato, (int) round( $lato * 0.83 ), (int) round( $lato * 0.67 ) ) as $larghezzaMax ) {
				$tela = self::ridimensiona( $immagine, $larghezzaMax );

				foreach ( array( $qualita, 72, 62, 52 ) as $q ) {
					ob_start();
					$riuscito = imagewebp( $tela, null, $q );
					$prova    = (string) ob_get_clean();

					if ( $riuscito && '' !== $prova && ( '' === $webp || strlen( $prova ) < strlen( $webp ) ) ) {
						$webp = $prova;
					}

					if ( '' !== $webp && strlen( $webp ) <= $peso ) {
						break 2;
					}
				}

				if ( $tela !== $immagine ) {
					imagedestroy( $tela );
				}
			}
		} finally {
			imagedestroy( $immagine );
		}

		// Se la conversione e uscita vuota o piu pesante dell originale,
		// l originale resta la scelta migliore: meglio un immagine pesante
		// che nessuna immagine.
		if ( '' === $webp || strlen( $webp ) >= strlen( $binario ) ) {
			return array( $mime, $binario );
		}

		return array( 'image/webp', $webp );
	}

	/**
	 * Copia ridimensionata al lato lungo richiesto.
	 *
	 * Restituisce l originale, senza copiarlo, quando e gia abbastanza
	 * piccolo: chi chiama deve distruggere il risultato solo se e diverso.
	 *
	 * @param resource|\GdImage $immagine     Immagine.
	 * @param int               $larghezzaMax Lato lungo massimo.
	 * @return resource|\GdImage
	 */
	private static function ridimensiona( $immagine, $larghezzaMax ) {
		$larghezza = imagesx( $immagine );
		$altezza   = imagesy( $immagine );
		$massimo   = max( $larghezza, $altezza );

		if ( $massimo <= $larghezzaMax ) {
			return $immagine;
		}

		$scala   = $larghezzaMax / $massimo;
		$ridotta = imagescale( $immagine, (int) round( $larghezza * $scala ), (int) round( $altezza * $scala ) );

		return $ridotta ?: $immagine;
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

		$interrotto = false;

		foreach ( $documenti as $doc ) {
			if ( $scadenza && time() > $scadenza ) {
				$interrotto = true;
				break;
			}

			try {
				list( $mime, $binario ) = $gemini->generaImmagine( self::descrizione( $doc, $cfg ) );

				// Si converte prima di scrivere e prima di caricare: il file che
				// resta in archivio deve essere lo stesso che finisce sul sito.
				list( $mime, $binario ) = self::ottimizza( $binario, $mime, $cfg );

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
			'candidati'  => count( $documenti ),
			'interrotto' => $interrotto,
			'generate'   => $fatte,
			'inviate'   => $inviate,
			'errori'    => $errori,
			'cartella'  => $cartella,
			'consumo'   => $gemini->consumo(),
		);
	}
}
