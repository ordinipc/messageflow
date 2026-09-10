<?php
/**
 * Esecuzione delle regole, punteggi e salvataggio su database.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

use SeoGeo\Rules\Content;
use SeoGeo\Rules\Eeat;
use SeoGeo\Rules\Generative;
use SeoGeo\Rules\Links;
use SeoGeo\Rules\Local;
use SeoGeo\Rules\Media;
use SeoGeo\Rules\OnPage;
use SeoGeo\Rules\Structured;
use SeoGeo\Rules\Taxonomy;
use SeoGeo\Rules\Technical;

/**
 * Motore di audit: esegue tutte le regole e calcola i punteggi per area.
 */
class Audit {

	/** @var array<string,array> Aree con etichetta, peso e icona. */
	const AREE = array(
		'technical'  => array( 'Tecnico e indicizzazione', 15, '⚙️' ),
		'onpage'     => array( 'On-page (title, meta, URL)', 15, '📝' ),
		'content'    => array( 'Qualità dei contenuti', 15, '📄' ),
		'links'      => array( 'Link interni e architettura', 12, '🔗' ),
		'media'      => array( 'Immagini e performance', 8, '🖼️' ),
		'structured' => array( 'Dati strutturati', 10, '🧩' ),
		'local'      => array( 'SEO locale (GEO geografico)', 10, '📍' ),
		'generative' => array( 'GEO — Generative Engine Optimization', 10, '🤖' ),
		'eeat'       => array( 'E-E-A-T e affidabilità', 5, '🏅' ),
		'taxonomy'   => array( 'Tassonomie e archivi', 5, '🗂️' ),
	);

	/** @var array<string,int> Penalità per gravità. */
	const PESO_GRAVITA = array( 'critical' => 30, 'high' => 16, 'medium' => 7, 'low' => 2 );

	/** @var array<string,int> Ordine di presentazione. */
	const ORDINE_GRAVITA = array( 'critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3 );

	/**
	 * Tutte le regole registrate.
	 *
	 * @return array[]
	 */
	public static function regole() {
		return array_merge(
			Technical::rules(),
			OnPage::rules(),
			Content::rules(),
			Links::rules(),
			Media::rules(),
			Structured::rules(),
			Local::rules(),
			Generative::rules(),
			Eeat::rules(),
			Taxonomy::rules()
		);
	}

	/**
	 * Esegue l audit completo.
	 *
	 * @param Site $site Sito analizzato.
	 * @return array
	 */
	public static function esegui( Site $site ) {
		$risultati = array();
		$totale    = max( 1, count( $site->pubblicati ) );

		foreach ( self::regole() as $regola ) {
			try {
				$problemi = call_user_func( $regola['check'], $site );
			} catch ( \Throwable $e ) {
				$problemi = array( array( 'ref' => '(errore)', 'titolo' => '', 'tipo' => 'error', 'dettaglio' => 'regola non eseguita: ' . $e->getMessage() ) );
			}

			if ( empty( $problemi ) ) {
				continue;
			}

			$riferimenti = array();
			foreach ( $problemi as $p ) {
				$riferimenti[ $p['ref'] ] = true;
			}

			$coinvolti = count( $riferimenti );
			$quota     = ( isset( $problemi[0]['tipo'] ) && 'site' === $problemi[0]['tipo'] )
				? 1.0
				: min( 1.0, $coinvolti / $totale );

			$risultati[] = array(
				'id'         => $regola['id'],
				'area'       => $regola['area'],
				'gravita'    => $regola['gravita'],
				'titolo'     => $regola['titolo'],
				'perche'     => $regola['perche'],
				'soluzione'  => $regola['soluzione'],
				'auto'       => ! empty( $regola['auto'] ),
				'occorrenze' => count( $problemi ),
				'coinvolti'  => $coinvolti,
				'penalita'   => self::PESO_GRAVITA[ $regola['gravita'] ] * ( 0.35 + 0.65 * $quota ),
				'problemi'   => $problemi,
			);
		}

		usort(
			$risultati,
			static function ( $a, $b ) {
				$d = self::ORDINE_GRAVITA[ $a['gravita'] ] <=> self::ORDINE_GRAVITA[ $b['gravita'] ];

				return 0 !== $d ? $d : ( $b['penalita'] <=> $a['penalita'] );
			}
		);

		$punteggi = array();
		$pesi     = 0;
		$somma    = 0;

		foreach ( self::AREE as $chiave => $info ) {
			$penalita = 0;
			$rilievi  = 0;

			foreach ( $risultati as $r ) {
				if ( $r['area'] === $chiave ) {
					$penalita += $r['penalita'];
					$rilievi++;
				}
			}

			$punteggio            = (int) max( 0, round( 100 - $penalita ) );
			$punteggi[ $chiave ] = array(
				'chiave'    => $chiave,
				'etichetta' => $info[0],
				'peso'      => $info[1],
				'icona'     => $info[2],
				'punteggio' => $punteggio,
				'rilievi'   => $rilievi,
			);

			$pesi  += $info[1];
			$somma += $punteggio * $info[1];
		}

		$gravita = array( 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0 );
		$occorrenze = 0;

		foreach ( $risultati as $r ) {
			$gravita[ $r['gravita'] ]++;
			$occorrenze += $r['occorrenze'];
		}

		return array(
			'generato'   => date( 'c' ),
			'globale'    => (int) round( $somma / max( 1, $pesi ) ),
			'punteggi'   => $punteggi,
			'gravita'    => $gravita,
			'occorrenze' => $occorrenze,
			'rilievi'    => $risultati,
		);
	}

	/**
	 * Salva un audit completo sul database e restituisce l id.
	 *
	 * @param Db     $db      Database.
	 * @param Site   $site    Sito.
	 * @param array  $audit   Risultato di esegui().
	 * @param string $origine Nome del file analizzato.
	 * @return int
	 */
	public static function salva( Db $db, Site $site, array $audit, $origine ) {
		$auditId = $db->insert(
			'audit',
			array(
				'sito_nome'       => $site->meta['titolo'],
				'sito_url'        => $site->url,
				'file_origine'    => $origine,
				'creato_il'       => date( 'Y-m-d H:i:s' ),
				'punteggio'       => $audit['globale'],
				'problemi_totali' => $audit['occorrenze'],
				'articoli'        => count( $site->articoli ),
				'pagine'          => count( $site->pagine ),
				'media'           => count( $site->allegati ),
			)
		);

		$documenti = array();
		foreach ( $site->pubblicati as $d ) {
			$documenti[] = array(
				'audit_id'        => $auditId,
				'wp_id'           => $d['wp_id'],
				'tipo'            => $d['tipo'],
				'stato'           => $d['stato'],
				'titolo'          => $d['titolo'],
				'slug'            => $d['slug'],
				'url'             => $d['url'],
				'percorso'        => $d['percorso'],
				'parole'          => $d['parole'],
				'h1'              => count( $d['h1'] ),
				'h2'              => count( $d['h2'] ),
				'immagini'        => count( $d['immagini'] ),
				'link_in'         => $site->inbound( $d['percorso'] ),
				'link_out'        => count( $d['link_interni'] ),
				'focus_keyword'   => $d['focus'],
				'seo_title'       => $d['seo_title'],
				'seo_description' => $d['seo_desc'],
				'gulpease'        => $d['gulpease'],
				'pubblicato'      => substr( (string) $d['data'], 0, 10 ),
				'modificato'      => substr( (string) $d['modificato'], 0, 10 ),
				// Il testo resta nel database: serve ai prompt del modulo AI senza
				// dover rileggere ogni volta l export da 10 MB.
				'testo'           => $d['testo'],
			);
		}
		$db->insertMany( 'documento', $documenti );

		$aree = array();
		foreach ( $audit['punteggi'] as $p ) {
			$aree[] = array(
				'audit_id'  => $auditId,
				'chiave'    => $p['chiave'],
				'etichetta' => $p['etichetta'],
				'punteggio' => $p['punteggio'],
				'rilievi'   => $p['rilievi'],
				'peso'      => $p['peso'],
			);
		}
		$db->insertMany( 'area', $aree );

		foreach ( $audit['rilievi'] as $r ) {
			$rilievoId = $db->insert(
				'rilievo',
				array(
					'audit_id'   => $auditId,
					'regola'     => $r['id'],
					'area'       => $r['area'],
					'gravita'    => $r['gravita'],
					'titolo'     => $r['titolo'],
					'perche'     => $r['perche'],
					'soluzione'  => $r['soluzione'],
					'automatico' => $r['auto'] ? 1 : 0,
					'occorrenze' => $r['occorrenze'],
				)
			);

			$righe = array();
			foreach ( $r['problemi'] as $p ) {
				$righe[] = array(
					'rilievo_id'  => $rilievoId,
					'riferimento' => $p['ref'],
					'dettaglio'   => $p['dettaglio'],
				);
			}
			$db->insertMany( 'occorrenza', $righe );
		}

		return $auditId;
	}
}
