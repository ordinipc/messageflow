<?php
/**
 * Perché una pagina non si vede.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

use SeoGeo\Bridge\WordPress;
use SeoGeo\Google\SearchConsole;
use Throwable;

/**
 * Mette insieme le tre cose che sanno qualcosa su una pagina: il sito, Google e
 * quello che il programma stesso ha fatto o ha in piano.
 *
 * Serve a rispondere con dei fatti invece che con una diagnosi a occhio, che è
 * il modo in cui si perde un pomeriggio dietro a una pagina sparita.
 */
class Diagnosi {

	/**
	 * Raccoglie tutto quello che si sa su un indirizzo.
	 *
	 * @param Db                 $db      Database.
	 * @param array              $cfg     Configurazione.
	 * @param string             $url     Indirizzo da controllare.
	 * @param WordPress          $ponte   Collegamento al sito.
	 * @param SearchConsole|null $console Search Console, se collegata.
	 * @return array
	 */
	public static function esegui( Db $db, array $cfg, $url, WordPress $ponte, SearchConsole $console = null ) {
		$esito = array(
			'url'       => $url,
			'sito'      => null,
			'sito_err'  => '',
			'google'    => null,
			'google_err' => '',
			'nostro'    => null,
			'cause'     => array(),
		);

		// 1. Il sito: è l unico che sa se il contenuto è nel cestino.
		if ( $ponte->pronto() ) {
			try {
				$esito['sito'] = $ponte->contenuto( $url );
			} catch ( Throwable $e ) {
				$esito['sito_err'] = $e->getMessage();
			}
		} else {
			$esito['sito_err'] = 'Collegamento al sito non configurato.';
		}

		// 2. Google: dice se la conosce, e con quali parole.
		if ( $console ) {
			try {
				$esito['google'] = $console->ispeziona( $url );
			} catch ( Throwable $e ) {
				$esito['google_err'] = $e->getMessage();
			}
		}

		// 3. Noi: cosa ne ha detto l analisi, e cosa abbiamo in piano.
		$percorso = Search\Azioni::percorso( $url );
		$audit    = $db->one( 'SELECT id FROM audit ORDER BY id DESC LIMIT 1' );

		if ( $audit ) {
			$documento = $db->one(
				'SELECT * FROM documento WHERE audit_id = ? AND (percorso = ? OR percorso = ?)',
				array( $audit['id'], $percorso, $percorso . '/' )
			);

			if ( $documento ) {
				$esito['nostro'] = array(
					'documento'  => $documento,
					'triage'     => $db->one( 'SELECT * FROM triage WHERE audit_id = ? AND documento_id = ?', array( $audit['id'], $documento['id'] ) ),
					'meta_piano' => $db->one( 'SELECT * FROM meta_piano WHERE audit_id = ? AND documento_id = ?', array( $audit['id'], $documento['id'] ) ),
					'bozza'      => $db->one( 'SELECT id, stato, creato_il, parole FROM bozza WHERE audit_id = ? AND documento_id = ? ORDER BY id DESC LIMIT 1', array( $audit['id'], $documento['id'] ) ),
					'coda'       => $db->all( 'SELECT tipo, stato, messaggio, eseguito_il FROM coda WHERE audit_id = ? AND riferimento = ? ORDER BY id DESC LIMIT 10', array( $audit['id'], (string) $documento['id'] ) ),
				);
			}
		}

		$esito['cause'] = self::cause( $esito );

		return $esito;
	}

	/**
	 * Traduce i dati raccolti in spiegazioni, dalla più probabile in giù.
	 *
	 * @param array $e Dati raccolti.
	 * @return array[] 'gravita', 'titolo', 'spiegazione', 'rimedio'.
	 */
	private static function cause( array $e ) {
		$cause = array();
		$sito  = $e['sito'];
		$g     = $e['google'];

		if ( is_array( $sito ) ) {
			if ( 'trash' === ( $sito['stato'] ?? '' ) ) {
				$cause[] = array(
					'gravita'     => 'grave',
					'titolo'      => 'È nel cestino',
					'spiegazione' => 'Il contenuto esiste ma WordPress lo tiene nel cestino: per i visitatori è una pagina non trovata.',
					'rimedio'     => 'WordPress → Articoli → Cestino → Ripristina.',
				);
			} elseif ( in_array( $sito['stato'] ?? '', array( 'draft', 'pending' ), true ) ) {
				$cause[] = array(
					'gravita'     => 'grave',
					'titolo'      => 'Non è pubblicato: è una bozza',
					'spiegazione' => 'Lo stato sul sito è "' . $sito['stato'] . '", quindi lo vedi solo tu da dentro WordPress.',
					'rimedio'     => 'Aprilo in WordPress e premi Pubblica, se è quello che vuoi.',
				);
			} elseif ( 'future' === ( $sito['stato'] ?? '' ) ) {
				$cause[] = array(
					'gravita'     => 'alto',
					'titolo'      => 'È programmato per una data futura',
					'spiegazione' => 'Data di pubblicazione: ' . ( $sito['data'] ?? '' ) . '. Prima di allora non esiste per nessuno.',
					'rimedio'     => 'Cambia la data in WordPress se lo vuoi online adesso.',
				);
			} elseif ( 'private' === ( $sito['stato'] ?? '' ) ) {
				$cause[] = array(
					'gravita'     => 'grave',
					'titolo'      => 'È privato',
					'spiegazione' => 'Lo vedono solo gli utenti connessi con i permessi giusti.',
					'rimedio'     => 'WordPress → Articolo → Visibilità → Pubblico.',
				);
			}

			if ( ! empty( $sito['password'] ) ) {
				$cause[] = array(
					'gravita'     => 'grave',
					'titolo'      => 'È protetto da password',
					'spiegazione' => 'Chi arriva trova la richiesta della password, e Google non entra.',
					'rimedio'     => 'WordPress → Articolo → Visibilità → togli la password.',
				);
			}

			if ( false !== stripos( (string) ( $sito['robots'] ?? '' ), 'noindex' ) ) {
				$cause[] = array(
					'gravita'     => 'grave',
					'titolo'      => 'È marcato noindex',
					'spiegazione' => 'La pagina chiede a Google di non indicizzarla (rank_math_robots: ' . $sito['robots'] . '). Resta visibile a chi ha il link, ma fuori dalle ricerche.',
					'rimedio'     => 'Togli il noindex dalle impostazioni SEO dell articolo.',
				);
			}

			if ( ! empty( $sito['noindex_mappa'] ) ) {
				$cause[] = array(
					'gravita'     => 'grave',
					'titolo'      => 'Il plugin lo tiene fuori dall indice',
					'spiegazione' => 'Nella mappa generata dall analisi questo contenuto risulta marcato noindex: succede a privacy, cookie, termini, condizioni e pagine di ringraziamento.',
					'rimedio'     => 'Se è un errore, rifai l analisi dopo aver corretto lo slug, oppure segnalamelo.',
				);
			}

			if ( ! empty( $sito['canonica'] ) && false === stripos( $sito['canonica'], (string) ( $sito['slug'] ?? 'x' ) ) ) {
				$cause[] = array(
					'gravita'     => 'alto',
					'titolo'      => 'Punta a un altra pagina come originale',
					'spiegazione' => 'La canonica indica ' . $sito['canonica'] . ': stai dicendo a Google che la versione buona è quella, non questa.',
					'rimedio'     => 'Se non è voluto, svuota il campo URL canonico nelle impostazioni SEO dell articolo.',
				);
			}

			if ( (int) ( $sito['parole'] ?? 0 ) < 50 ) {
				$cause[] = array(
					'gravita'     => 'alto',
					'titolo'      => 'Il contenuto è quasi vuoto',
					'spiegazione' => 'Sul sito risultano ' . (int) ( $sito['parole'] ?? 0 ) . ' parole: la pagina esiste ma non ha praticamente testo.',
					'rimedio'     => 'Controlla le revisioni in WordPress: se una riscrittura è stata pubblicata a metà, si torna indietro da lì.',
				);
			}
		}

		if ( is_array( $g ) ) {
			$copertura = (string) ( $g['copertura'] ?? '' );

			if ( false !== stripos( $copertura, 'duplicat' ) || ( ! empty( $g['canonica_google'] ) && false === stripos( (string) $g['canonica_google'], trim( (string) parse_url( $e['url'], PHP_URL_PATH ), '/' ) ) ) ) {
				$cause[] = array(
					'gravita'     => 'alto',
					'titolo'      => 'Google la considera un doppione',
					'spiegazione' => 'Google dice: "' . $copertura . '"'
						. ( ! empty( $g['canonica_google'] ) ? ' e come originale ha scelto ' . $g['canonica_google'] : '' )
						. '. Succede quando due articoli dicono la stessa cosa: ne mostra uno solo.',
					'rimedio'     => 'Accorpa i due articoli in uno e manda il vecchio in 301 sul nuovo. Il triage lo propone già.',
				);
			} elseif ( false !== stripos( $copertura, 'non indicizzata' ) ) {
				$cause[] = array(
					'gravita'     => 'alto',
					'titolo'      => 'Google l ha vista ma non l ha indicizzata',
					'spiegazione' => 'Google dice: "' . $copertura . '". Di solito significa che non l ha ritenuta abbastanza utile rispetto a quello che ha già.',
					'rimedio'     => 'Rafforza il contenuto e i link interni verso questa pagina, poi chiedi la reindicizzazione da Search Console.',
				);
			} elseif ( '' !== $copertura && 'PASS' !== ( $g['stato'] ?? '' ) ) {
				$cause[] = array(
					'gravita'     => 'medio',
					'titolo'      => 'Google riporta: ' . $copertura,
					'spiegazione' => 'Stato dell indicizzazione: ' . ( $g['stato'] ?? '' ) . '.',
					'rimedio'     => 'Apri lo strumento di controllo URL in Search Console per il dettaglio completo.',
				);
			}
		}

		return $cause;
	}
}
