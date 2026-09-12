<?php
/**
 * Ricompressione delle immagini gia caricate sul sito.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Media;

use SeoGeo\Bridge\WordPress;
use Throwable;

/**
 * Copre il problema IMG-03 sulle immagini che sono gia in libreria media.
 *
 * La generazione converte in WebP prima di caricare, ma le immagini messe
 * sul sito prima di quella correzione restano pesanti: qui si rimedia a
 * cose fatte, chiedendo al plugin di ricomprimerle una per una.
 *
 * Due cautele che valgono piu di qualsiasi risparmio di byte:
 *
 * 1. Le immagini che compaiono dentro il testo di un articolo non si
 *    toccano. Ricomprimere cambia il nome del file e l immagine sparirebbe
 *    dall articolo. Quelle in evidenza invece sono collegate per
 *    identificativo e si possono sostituire senza rompere niente.
 * 2. Il file originale resta sul disco e il plugin ne conserva il percorso:
 *    l operazione si annulla.
 */
class Compressione {

	/**
	 * Immagini che si possono ricomprimere, e quelle che non si toccano.
	 *
	 * @param WordPress $ponte  Collegamento al sito.
	 * @param int       $soglia Soglia in byte.
	 * @return array
	 */
	public static function elenco( WordPress $ponte, $soglia = 204800, $secondi_max = 25 ) {
		// Il sito risponde a blocchi: leggere tutta la libreria media in una
		// richiesta sola andava in timeout dopo sessanta secondi e la pagina
		// non mostrava niente. Si continua finche il sito dice di aver
		// finito, o finche il tempo concesso alla pagina e esaurito.
		$immagini = array();
		$offset   = 0;
		$totale   = 0;
		$guardati = 0;
		$fatte    = 0;
		$finito   = false;
		$scadenza = time() + (int) $secondi_max;

		do {
			$risposta = $ponte->immaginiPesanti( $soglia, 150, $offset );

			$immagini = array_merge( $immagini, (array) ( $risposta['immagini'] ?? array() ) );
			$totale   = (int) ( $risposta['totale'] ?? 0 );
			$guardati = (int) ( $risposta['guardati'] ?? 0 );
			$fatte    = (int) ( $risposta['gia_fatte'] ?? 0 );
			$finito   = ! empty( $risposta['finito'] );
			$avanzato = (int) ( $risposta['prossimo'] ?? 0 ) > $offset;
			$offset   = (int) ( $risposta['prossimo'] ?? 0 );
		} while ( ! $finito && $avanzato && time() < $scadenza );

		$sicure    = array();
		$nel_testo = array();
		$gia       = array();

		foreach ( $immagini as $i ) {
			if ( ! empty( $i['gia_ridotta'] ) ) {
				$gia[] = $i;
				continue;
			}

			if ( ! empty( $i['nel_testo'] ) ) {
				$nel_testo[] = $i;
				continue;
			}

			$sicure[] = $i;
		}

		// Le piu pesanti per prime: sono quelle che pesano davvero sul tempo
		// di caricamento, e se il lavoro si interrompe si e fatto il grosso.
		usort( $sicure, static fn( $a, $b ) => (int) $b['peso'] <=> (int) $a['peso'] );

		return array(
			'soglia'      => (int) ( $risposta['soglia'] ?? $soglia ),
			'totale'      => $totale,
			'guardati'    => $guardati,
			'completo'    => $finito,
			'gia_fatte'   => $fatte,
			'sicure'      => $sicure,
			'nel_testo'   => $nel_testo,
			'gia_ridotte' => $gia,
			'peso_sicure' => array_sum( array_map( static fn( $i ) => (int) $i['peso'], $sicure ) ),
		);
	}

	/**
	 * Ricomprime, fermandosi al limite richiesto.
	 *
	 * @param WordPress $ponte   Collegamento al sito.
	 * @param array     $opzioni 'limite', 'soglia', 'lato', 'qualita',
	 *                           'peso_max', 'secondi_max', 'su_progresso'.
	 * @return array
	 */
	public static function esegui( WordPress $ponte, array $opzioni = array() ) {
		$elenco = self::elenco( $ponte, (int) ( $opzioni['soglia'] ?? 204800 ) );
		$coda   = $elenco['sicure'];

		if ( ! empty( $opzioni['limite'] ) ) {
			$coda = array_slice( $coda, 0, (int) $opzioni['limite'] );
		}

		$progresso = $opzioni['su_progresso'] ?? null;
		$scadenza  = isset( $opzioni['secondi_max'] ) ? time() + (int) $opzioni['secondi_max'] : null;

		$fatte     = 0;
		$gia_fatte = 0;
		$invariate = 0;
		$prima     = 0;
		$dopo      = 0;
		$errori    = array();

		foreach ( $coda as $immagine ) {
			if ( $scadenza && time() > $scadenza ) {
				break;
			}

			try {
				$esito = $ponte->comprimiImmagine( (int) $immagine['wp_id'], $opzioni );

				$prima += (int) ( $esito['prima'] ?? 0 );
				$dopo  += (int) ( $esito['dopo'] ?? 0 );

				if ( ! empty( $esito['cambiata'] ) ) {
					$fatte++;
				} elseif ( 'gia_fatta' === ( $esito['motivo'] ?? '' ) ) {
					$gia_fatte++;
				} else {
					$invariate++;
				}
			} catch ( Throwable $e ) {
				// Una vecchia versione del plugin risponde ancora con un
				// errore per le immagini gia fatte: si conta come tale invece
				// di trattarla come un guasto, altrimenti un archivio a meta
				// strada blocca tutto il resto.
				if ( false !== stripos( $e->getMessage(), 'gia stata ricompressa' ) ) {
					$gia_fatte++;
				} else {
					$errori[] = $immagine['file'] . ': ' . $e->getMessage();
				}
			}

			if ( $progresso ) {
				$progresso( $immagine, $fatte, count( $errori ), count( $coda ) );
			}
		}

		return array(
			'candidate'   => count( $coda ),
			// Quante ne restano dopo questo giro: e la sola cifra che dice
			// se manca poco o se bisogna premere ancora dieci volte.
			'restanti'    => max( 0, count( $elenco['sicure'] ) - $fatte - $gia_fatte - $invariate ),
			'in_tutto'    => count( $elenco['sicure'] ),
			'compresse'   => $fatte,
			'gia_fatte'   => $gia_fatte,
			'invariate'   => $invariate,
			'peso_prima'  => $prima,
			'peso_dopo'   => $dopo,
			'risparmio'   => max( 0, $prima - $dopo ),
			'errori'      => $errori,
			'non_toccate' => count( $elenco['nel_testo'] ),
		);
	}

	/**
	 * Byte in una forma leggibile.
	 *
	 * @param int $byte Byte.
	 * @return string
	 */
	public static function peso( $byte ) {
		$byte = (int) $byte;

		if ( $byte >= 1048576 ) {
			return number_format( $byte / 1048576, 1, ',', '.' ) . ' MB';
		}

		return number_format( max( 0, $byte ) / 1024, 0, ',', '.' ) . ' KB';
	}
}
