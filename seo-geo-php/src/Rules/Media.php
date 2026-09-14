<?php
/**
 * Regole su immagini e performance.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Rules;

use SeoGeo\Site;

/**
 * Alt, peso, formati e immagini in evidenza.
 */
class Media {

	/**
	 * Estensioni di file che sono davvero un immagine servita al browser.
	 *
	 * La libreria media contiene anche PDF, documenti, archivi e video. Un
	 * PDF da 3 MB e un problema, ma non e «un immagine pesante»: contarlo
	 * qui gonfiava il numero e lo rendeva incomprensibile, perche lo
	 * strumento che ricomprime le immagini quel file non lo vede nemmeno.
	 */
	const IMMAGINI = array( 'jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'bmp', 'tif', 'tiff' );

	/**
	 * Se questo allegato e un immagine.
	 *
	 * Si guarda il mime quando il sito lo dichiara, altrimenti l estensione:
	 * da un export WXR il mime non c e.
	 *
	 * @param array $allegato Allegato.
	 * @return bool
	 */
	public static function eUnImmagine( array $allegato ) {
		$mime = (string) ( $allegato['mime'] ?? '' );

		if ( '' !== $mime ) {
			return 0 === strpos( $mime, 'image/' );
		}

		return in_array( (string) ( $allegato['ext'] ?? '' ), self::IMMAGINI, true );
	}

	/**
	 * @return array[]
	 */
	public static function rules() {
		return array(
			array(
				'id' => 'IMG-01', 'area' => 'media', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Immagini senza attributo alt',
				'perche' => 'L alt è il testo con cui Google capisce l immagine: senza, si perde traffico da Google Immagini e la pagina non è accessibile.',
				'soluzione' => 'Generazione automatica dell alt da titolo pagina e focus keyword, applicata dal plugin.',
				'check' => static function ( Site $s ) {
					if ( Base::loFaIlSito( $s, 'alt' ) ) {
						return array();
					}

					$out = array();
					foreach ( $s->pubblicati as $d ) {
						$senza = 0;
						foreach ( $d['immagini'] as $i ) {
							if ( ! $i['ha_alt'] ) {
								$senza++;
							}
						}
						if ( $senza ) {
							$out[] = Base::doc( $d, $senza . ' immagini su ' . count( $d['immagini'] ) . ' senza alt' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'IMG-02', 'area' => 'media', 'gravita' => Base::MEDIO, 'auto' => true,
				'titolo' => 'Allegati in libreria media senza testo alternativo',
				'perche' => 'L alt impostato in libreria viene ereditato ovunque l immagine venga inserita: compilarlo una volta risolve decine di pagine.',
				'soluzione' => 'Export CSV con alt suggerito per ogni allegato.',
				'check' => static function ( Site $s ) {
					if ( Base::loFaIlSito( $s, 'alt' ) ) {
						return array();
					}

					$out = array();
					foreach ( $s->allegati as $a ) {
						if ( '' === $a['alt'] && preg_match( '/jpe?g|png|webp|gif|svg/', $a['ext'] ) ) {
							$out[] = array( 'ref' => $a['file'], 'titolo' => $a['titolo'], 'tipo' => 'allegato', 'dettaglio' => 'alt assente' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'IMG-03', 'area' => 'media', 'gravita' => Base::ALTO, 'auto' => false,
				'titolo' => 'Immagini pesanti oltre 200 KB',
				'perche' => 'Il peso delle immagini è la causa principale di LCP lento e i Core Web Vitals influenzano posizionamento e conversioni.',
				'soluzione' => 'Conversione in WebP o AVIF e compressione; il plugin abilita lazy loading e priorità di caricamento.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->allegati as $a ) {
						if ( $a['peso'] <= 204800 || ! self::eUnImmagine( $a ) ) {
							continue;
						}

						// Il dettaglio dice anche perche il pulsante
						// «Immagini pesanti» non la tocchera: senza, il
						// gestionale segnava 40 e lo strumento ne trovava
						// zero, e non c era modo di sapere chi mentiva.
						$nota = '';

						if ( ! empty( $a['nel_testo'] ) ) {
							$nota = ' · dentro al testo di un articolo: si sostituisce a mano';
						} elseif ( ! empty( $a['gia_ridotta'] ) ) {
							$nota = ' · già ricompressa una volta: più di così non scende';
						}

						$out[] = array(
							'ref'       => $a['file'],
							'titolo'    => $a['titolo'],
							'tipo'      => 'allegato',
							'dettaglio' => round( $a['peso'] / 1024 ) . ' KB' . $nota,
						);
					}
					return $out;
				},
			),
			array(
				'id' => 'IMG-04', 'area' => 'media', 'gravita' => Base::MEDIO, 'auto' => false,
				'titolo' => 'Formati immagine non moderni',
				'perche' => 'WebP pesa il 25-35% in meno a parità di qualità: impatto diretto su LCP e crawl budget.',
				'soluzione' => 'Conversione batch in WebP mantenendo i vecchi file come riserva.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->allegati as $a ) {
						if ( in_array( $a['ext'], array( 'jpg', 'jpeg', 'png' ), true ) ) {
							$out[] = array( 'ref' => $a['file'], 'titolo' => $a['titolo'], 'tipo' => 'allegato', 'dettaglio' => 'formato ' . $a['ext'] );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'IMG-05', 'area' => 'media', 'gravita' => Base::ALTO, 'auto' => true,
				'titolo' => 'Contenuti senza immagine in evidenza',
				'perche' => 'Senza featured image mancano og:image e twitter:image: le condivisioni social non hanno anteprima e Google Discover esclude la pagina.',
				'soluzione' => 'Il plugin imposta un immagine di riserva brandizzata e genera og:image.',
				'check' => static function ( Site $s ) {
					$out = array();
					foreach ( $s->pubblicati as $d ) {
						if ( '' === $d['thumbnail'] ) {
							$out[] = Base::doc( $d, 'nessuna immagine in evidenza' );
						}
					}
					return $out;
				},
			),
			array(
				'id' => 'IMG-06', 'area' => 'media', 'gravita' => Base::BASSO, 'auto' => true,
				'titolo' => 'Immagini senza width e height espliciti',
				'perche' => 'Senza dimensioni il browser non riserva lo spazio e genera Cumulative Layout Shift, penalizzato dai Core Web Vitals.',
				'soluzione' => 'Il plugin aggiunge dimensioni, loading e decoding alle immagini del contenuto.',
				'check' => static function ( Site $s ) {
					if ( Base::loFaIlSito( $s, 'dimensioni' ) ) {
						return array();
					}

					$out = array();
					foreach ( $s->pubblicati as $d ) {
						$n = 0;
						foreach ( $d['immagini'] as $i ) {
							if ( '' === $i['width'] || '' === $i['height'] ) {
								$n++;
							}
						}
						if ( $n ) {
							$out[] = Base::doc( $d, $n . ' immagini senza dimensioni' );
						}
					}
					return $out;
				},
			),
		);
	}
}
