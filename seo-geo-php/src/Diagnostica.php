<?php
/**
 * Estrazione dei soli dati che servono a riprodurre un problema.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

/**
 * Un dump del database di WordPress pesa centinaia di megabyte e contiene
 * email, password e chiavi API di cui nessuno ha bisogno. Qui si prende solo
 * la forma dei contenuti — quella che serve a costruire un banco di prova
 * fedele — e si lascia fuori tutto il resto.
 *
 * Niente utenti, niente commenti, niente indirizzi email, niente opzioni del
 * sito: quello che esce da qui si può mandare a qualcuno senza pensarci.
 */
class Diagnostica {

	/**
	 * Meta che interessano: sono quelle da cui dipendono le regole.
	 *
	 * @var string[]
	 */
	const META = array(
		'rank_math_title',
		'rank_math_description',
		'rank_math_focus_keyword',
		'rank_math_robots',
		'rank_math_canonical_url',
		'rank_math_seo_score',
		'_yoast_wpseo_title',
		'_yoast_wpseo_metadesc',
		'_yoast_wpseo_focuskw',
		'_thumbnail_id',
		'_elementor_data',
	);

	/**
	 * Compone il pacchetto.
	 *
	 * @param Site  $site  Sito letto.
	 * @param array $stato Risposta di /stato del plugin.
	 * @return array
	 */
	public static function pacchetto( Site $site, array $stato = array() ) {
		$contenuti = array();

		foreach ( array_merge( $site->articoli, $site->pagine ) as $d ) {
			$meta = array();

			foreach ( self::META as $chiave ) {
				$valore = $d['meta'][ $chiave ] ?? null;

				if ( null === $valore || '' === $valore || array() === $valore ) {
					continue;
				}

				// Di Elementor basta sapere che c è: il contenuto non serve.
				// Le altre restano com erano, array compresi: la forma vera del
				// dato è esattamente quello che serve vedere, ed è da lì che
				// sono nati i problemi.
				$meta[ $chiave ] = '_elementor_data' === $chiave ? '1' : $valore;
			}

			$contenuti[] = array(
				'wp_id'      => $d['wp_id'],
				'tipo'       => $d['tipo'],
				'stato'      => $d['stato'],
				'titolo'     => $d['titolo'],
				'slug'       => $d['slug'],
				'percorso'   => $d['percorso'],
				'data'       => $d['data'],
				'modificato' => $d['modificato'],
				'categorie'  => array_column( (array) $d['categorie'], 'slug' ),
				'tag'        => array_column( (array) $d['tag'], 'slug' ),
				'parole'     => $d['parole'],
				'meta'       => $meta,
				'contenuto'  => $d['contenuto'],
			);
		}

		$allegati = array();

		foreach ( $site->allegati as $a ) {
			$allegati[] = array(
				'wp_id'    => $a['wp_id'] ?? '',
				'url'      => $a['url'] ?? '',
				'alt'      => $a['alt'] ?? '',
				'peso'     => $a['peso'] ?? 0,
				'genitore' => $a['genitore'] ?? '',
			);
		}

		return array(
			'generato_il' => date( 'c' ),
			'programma'   => 'SEO & GEO Audit',
			'sito'        => array(
				'titolo'   => $site->meta['titolo'] ?? '',
				'url'      => $site->url,
				'lingua'   => $site->meta['lingua'] ?? '',
				'articoli' => count( $site->articoli ),
				'pagine'   => count( $site->pagine ),
				'allegati' => count( $site->allegati ),
			),
			// Serve sapere con cosa convive il plugin, non chi ci lavora.
			'ambiente'    => array(
				'wordpress' => $stato['wordpress'] ?? '',
				'plugin'    => $stato['plugin'] ?? '',
				'rank_math' => ! empty( $stato['rank_math'] ),
				'yoast'     => ! empty( $stato['yoast'] ),
				'php'       => PHP_VERSION,
			),
			'categorie'   => array_values( $site->categorie ),
			'tag'         => array_values( $site->tag ),
			'menu'        => $site->menu,
			'contenuti'   => $contenuti,
			'allegati'    => $allegati,
		);
	}

	/**
	 * Elenco di quello che il pacchetto non contiene, da mostrare all utente.
	 *
	 * @return string[]
	 */
	public static function esclusi() {
		return array(
			'utenti, email e password',
			'commenti e indirizzi IP di chi ha commentato',
			'chiavi API, licenze e impostazioni del sito',
			'revisioni degli articoli',
			'il contenuto dei blocchi Elementor',
			'i file delle immagini',
		);
	}
}
