<?php
/**
 * Riscrittura di title, meta description, estratto e slug.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Fix;

use SeoGeo\Db;
use SeoGeo\Site;
use SeoGeo\Text;
use SeoGeo\Rules\Content;

/**
 * Genera le meta ottimizzate per ogni contenuto pubblicato.
 */
class Meta {

	/** @var string[] Nomi propri da normalizzare nelle keyword. */
	private static $propri = array( 'Instagram', 'Facebook', 'TikTok', 'YouTube', 'LinkedIn', 'WordPress', 'Google', 'WhatsApp', 'SEO', 'SEM', 'CRM', 'AI', 'E-commerce', 'Shopify', 'WooCommerce', 'Palermo', 'Sicilia', 'Reels' );

	/** @var array[] Benefici usati per completare i title corti. */
	private static $benefici = array(
		array( '/e-?commerce|negozio online|shop/i', 'Vendi di più online' ),
		array( '/sito web|siti web|landing/i', 'Sito veloce e che converte' ),
		array( '/social|instagram|facebook|tiktok|reels/i', 'Strategia social che funziona' ),
		array( '/video|reel|spot|riprese/i', 'Video che fanno crescere il brand' ),
		array( '/seo|posizionamento|google/i', 'Più visibilità su Google' ),
		array( '/crm|gestionale|software|automazione/i', 'Processi automatizzati' ),
		array( '/grafic|logo|brand|naming|design/i', 'Identità di marca riconoscibile' ),
		array( '/sicurezza|cyber|malware|phishing/i', 'Proteggi i tuoi dati' ),
		array( '/stamp|pannell|insegn/i', 'Stampa professionale su misura' ),
		array( '/marketing|campagn|ads|advertis/i', 'Campagne che portano clienti' ),
	);

	/**
	 * Normalizza i nomi propri che nelle keyword arrivano in minuscolo.
	 *
	 * @param string $testo Testo.
	 * @return string
	 */
	public static function nomiPropri( $testo ) {
		$out = (string) $testo;

		foreach ( self::$propri as $w ) {
			$out = preg_replace( '/\b' . preg_quote( $w, '/' ) . '\b/iu', $w, $out );
		}

		return $out;
	}

	/**
	 * Iniziale maiuscola.
	 *
	 * @param string $testo Testo.
	 * @return string
	 */
	private static function maiuscola( $testo ) {
		$t = (string) $testo;

		return '' === $t ? $t : mb_strtoupper( mb_substr( $t, 0, 1 ) ) . mb_substr( $t, 1 );
	}

	/**
	 * Beneficio più coerente con il tema della pagina.
	 *
	 * @param string $testo Testo di riferimento.
	 * @return string
	 */
	private static function beneficio( $testo ) {
		foreach ( self::$benefici as $b ) {
			if ( preg_match( $b[0], $testo ) ) {
				return $b[1];
			}
		}

		// Nessuna corrispondenza: su un testo abbastanza lungo si può ancora
		// dire qualcosa di generico, su quattro parole no. "Guida pratica per
		// le PMI" scritto sopra una pagina servizi è una promessa inventata, e
		// adesso questi title finiscono sul sito da soli.
		return mb_strlen( trim( (string) $testo ) ) >= 200 ? 'Guida pratica per le PMI' : '';
	}

	/**
	 * Title entro il limite, con la focus keyword in apertura.
	 *
	 * @param array $doc Documento.
	 * @param array $cfg Configurazione.
	 * @return array{0:string,1:bool}
	 */
	public static function title( array $doc, array $cfg ) {
		$max     = $cfg['seo']['titleMax'];
		$brand   = $cfg['seo']['brandSuffix'];
		$kw      = self::nomiPropri( self::maiuscola( trim( $doc['focus'] ) ) );
		$attuale = trim( $doc['seo_title'] ?: $doc['titolo'] );
		$legale  = (bool) preg_match( '/privacy|cookie|termini|condizioni|note-legali/i', $doc['slug'] );

		// Un title della lunghezza giusta ma tagliato a metà non è un title
		// buono: quattordici erano finiti in pagina proprio perché questo
		// controllo guardava solo la lunghezza e la parola chiave.
		$monco = $attuale !== Text::polishClause( $attuale );

		// Un anno superato nel title si vede in SERP prima di ogni altra
		// cosa: «Strategie 2025» letto nel 2026 manda il clic al risultato
		// sotto. Il generatore lo portava avanti tale e quale - o perche il
		// title andava gia bene per lunghezza e parola chiave, o perche lo
		// ricostruiva dal titolo dell articolo, che l anno ce l ha dentro.
		if ( ! $monco && mb_strlen( $attuale ) <= $max && mb_strlen( $attuale ) >= 30
			&& ( '' === $kw || false !== mb_stripos( $attuale, $kw ) ) ) {
			$aggiornato = self::annoAggiornato( $attuale );

			return array( $aggiornato, $aggiornato !== $attuale );
		}

		if ( $legale ) {
			$t = Text::truncate( $doc['titolo'] . ' | ' . $brand, $max );

			return array( $t, $t !== $attuale );
		}

		$beneficio = self::beneficio( $doc['titolo'] . ' ' . $doc['focus'] . ' ' . mb_substr( $doc['testo'], 0, 400 ) );
		$titoloPro = self::nomiPropri( $doc['titolo'] );
		$resto     = '' !== $kw
			? trim( preg_replace( '/\s{2,}/', ' ', preg_replace( '/^[\s:,\-–|]+/u', '', preg_replace( '/' . preg_quote( $kw, '/' ) . '/iu', '', $titoloPro ) ) ) )
			: $titoloPro;

		// Il resto del titolo si riusa solo se la keyword ne è il prefisso:
		// toglierla dal mezzo lascerebbe un frammento senza senso.
		$kwPrefisso  = '' !== $kw && 0 === mb_stripos( $titoloPro, $kw );
		$restoPulito = mb_strlen( $resto ) > 12 && preg_match( '/^[\p{L}\p{N}]/u', $resto ) && count( preg_split( '/\s+/u', $resto ) ) >= 3;

		$candidati = array();
		if ( '' !== $kw ) {
			if ( $kwPrefisso && $restoPulito ) {
				$candidati[] = $kw . ': ' . Text::polishClause( Text::truncate( $resto, $max - mb_strlen( $kw ) - 2 ) );
			}
			if ( '' !== $beneficio ) {
				$candidati[] = $kw . ': ' . $beneficio;
			}

			$candidati[] = $kw . ' | ' . $brand;
			$candidati[] = $kw;
		}
		$candidati[] = Text::truncate( $titoloPro, $max );

		$dedup = static function ( $s ) {
			$parti  = array_filter( array_map( 'trim', preg_split( '/\s*[:|]\s*/u', (string) $s ) ) );
			$tenute = array();
			foreach ( $parti as $p ) {
				$k       = mb_strtolower( $p );
				$doppione = false;
				foreach ( $tenute as $t ) {
					$tl = mb_strtolower( $t );
					if ( false !== mb_strpos( $tl, $k ) || false !== mb_strpos( $k, $tl ) ) {
						$doppione = true;
						break;
					}
				}
				if ( ! $doppione ) {
					$tenute[] = $p;
				}
			}

			return implode( ': ', $tenute );
		};

		$scelto = null;
		foreach ( $candidati as $c ) {
			$d = $dedup( $c );
			if ( mb_strlen( $d ) >= 30 && mb_strlen( $d ) <= $max ) {
				$scelto = $d;
				break;
			}
		}

		// Quando si taglia, si taglia a fine concetto: un title che finisce con
		// "per la Tua" è una frase monca, e in Google si legge per quello che è.
		if ( null === $scelto ) {
			foreach ( $candidati as $c ) {
				$d = Text::polishClause( Text::truncate( $dedup( $c ), $max ) );
				if ( mb_strlen( $d ) >= 25 ) {
					$scelto = $d;
					break;
				}
			}
		}

		if ( null === $scelto ) {
			$scelto = Text::polishClause( Text::truncate( $dedup( $attuale ), $max ) );
		}

		if ( mb_strlen( $scelto ) + mb_strlen( $brand ) + 3 <= $max && false === mb_stripos( $scelto, $brand ) ) {
			$scelto .= ' | ' . $brand;
		}

		// Un titolo di una parola sola - «Blog», «Contatti» - col marchio
		// accanto arrivava a ventinove caratteri: uno sotto la soglia della
		// regola ONP-02, che quindi bocciava il title appena scritto dal
		// gestionale. Si completa con la citta, che e vera e serve anche a
		// posizionarsi.
		$citta = trim( (string) ( $cfg['seo']['cittaPrincipale'] ?? '' ) );

		if ( mb_strlen( $scelto ) < 30 && '' !== $citta && false === mb_stripos( $scelto, $citta ) ) {
			foreach ( array( ' a ' . $citta, ' | ' . $citta, ' ' . $citta ) as $coda ) {
				if ( mb_strlen( $scelto . $coda ) <= $max && mb_strlen( $scelto . $coda ) >= 30 ) {
					$scelto .= $coda;
					break;
				}
			}
		}

		// Rete finale: da qualunque ramo arrivi, un title non esce mai monco.
		// Su un titolo ben formato questa riga non cambia niente, perché non
		// finisce con una preposizione o un possessivo.
		$scelto = trim( Text::polishClause( $scelto ) );
		$scelto = self::annoAggiornato( $scelto );

		return array( $scelto, $scelto !== $attuale );
	}

	/**
	 * Lo stesso testo con gli anni superati portati a quello corrente.
	 *
	 * Solo gli anni che un titolo usa come promessa di attualita: quali
	 * siano lo decide Content, che e anche chi apre il rilievo. Due elenchi
	 * scritti in due posti diversi finiscono per non dire piu la stessa
	 * cosa, e allora il gestionale scriverebbe un title che la sua stessa
	 * regola boccia - e successo gia con le description troppo corte.
	 *
	 * @param string $testo Titolo.
	 * @return string
	 */
	public static function annoAggiornato( $testo ) {
		$testo = (string) $testo;
		$anno  = (int) date( 'Y' );

		foreach ( Content::anniSuperati( $testo, $anno ) as $vecchio ) {
			$testo = preg_replace( '/\b' . preg_quote( $vecchio, '/' ) . '\b/', (string) $anno, $testo );
		}

		return $testo;
	}

	/**
	 * Meta description costruita su frasi complete.
	 *
	 * @param array $doc Documento.
	 * @param array $cfg Configurazione.
	 * @return array{0:string,1:bool}
	 */
	public static function description( array $doc, array $cfg ) {
		$min     = $cfg['seo']['descMin'];
		$max     = $cfg['seo']['descMax'];
		$citta   = $cfg['seo']['cittaPrincipale'];
		$attuale = trim( $doc['seo_desc'] );

		// Una description della lunghezza giusta ma tagliata a metà resta inutile:
		// si considera valida solo se chiude una frase compiuta.
		$benFormata = preg_match( '/[.!?]$/u', $attuale ) && mb_strlen( Text::polishClause( $attuale ) ) >= mb_strlen( $attuale ) - 1;

		if ( mb_strlen( $attuale ) >= $min && mb_strlen( $attuale ) <= $max && $benFormata ) {
			return array( $attuale, false );
		}

		$kw       = self::nomiPropri( self::maiuscola( trim( $doc['focus'] ) ) );
		$sorgente = '';
		foreach ( array( $attuale, $doc['primo_paragrafo'], $doc['estratto'], $doc['testo'] ) as $c ) {
			if ( mb_strlen( (string) $c ) > 40 ) {
				$sorgente = $c;
				break;
			}
		}
		if ( '' === $sorgente ) {
			$sorgente = $doc['titolo'];
		}

		$frasi = preg_split( '/(?<=[.!?])\s+/u', self::nomiPropri( preg_replace( '/\s+/u', ' ', $sorgente ) ) );
		$corpo = '';
		foreach ( (array) $frasi as $f ) {
			$prova = '' === $corpo ? $f : $corpo . ' ' . $f;
			if ( mb_strlen( $prova ) > $max - 2 ) {
				break;
			}
			$corpo = $prova;
		}

		if ( '' === $corpo ) {
			$corpo = Text::polishClause( Text::truncate( self::nomiPropri( $sorgente ), $max - 26 ) );
		}

		$corpo = trim( $corpo );
		if ( ! preg_match( '/[.!?]$/u', $corpo ) ) {
			$corpo = Text::polishClause( $corpo ) . '.';
		}

		if ( '' !== $kw && false === mb_stripos( $corpo, $kw ) ) {
			$prefisso = $kw . ': ';
			$spazio   = $max - mb_strlen( $prefisso ) - 1;
			if ( mb_strlen( $corpo ) > $spazio ) {
				$corpo = Text::polishClause( Text::truncate( $corpo, $spazio ) ) . '.';
			}
			$corpo = $prefisso . $corpo;
		}

		// Un solo invito all azione, e solo se serve davvero ad arrivare alla
		// lunghezza minima. Tre di fila uno dopo l altro non sono una
		// description: sono riempitivo, e in Google si vedono per quello che sono.
		if ( mb_strlen( $corpo ) < $min ) {
			// L ordine conta. Prima quello che dice qualcosa - che cosa fa l
			// azienda, come si chiama, dove sta - e un solo invito all azione
			// alla fine, se ancora serve. Tre inviti uno dopo l altro non
			// sono una description: sono riempitivo, e in Google si vedono
			// per quello che sono.
			$cta = array_values(
				array_filter(
					array(
						self::fraseAzienda( $cfg ),
						'' !== $citta ? ' ' . $cfg['azienda']['nome'] . ', a ' . $citta . '.' : ' ' . $cfg['azienda']['nome'] . '.',
						'' !== $citta ? ' Scopri come lavoriamo a ' . $citta . '.' : ' Richiedi una consulenza gratuita.',
					)
				)
			);

			// Prima se ne accodava uno solo, il piu lungo fra quelli che ci
			// stavano, e non si ricontrollava il risultato. Su un articolo
			// con poco testo si arrivava a un centinaio di caratteri: sotto
			// la soglia della regola ONP-03, che quindi segnalava come
			// sbagliata una description scritta dal gestionale stesso. Da qui
			// le description fuori misura passate da 47 a 103 dopo un giro
			// del pilota.
			//
			// Adesso se ne accodano finche servono, senza ripetere lo stesso
			// due volte, e si smette appena la lunghezza e quella giusta.
			foreach ( $cta as $c ) {
				if ( mb_strlen( $corpo ) >= $min ) {
					break;
				}

				if ( mb_strlen( $corpo . $c ) <= $max && false === mb_strpos( $corpo, trim( $c ) ) ) {
					$corpo .= $c;
				}
			}

			// Se ancora non basta - succede coi titoli di due o tre parole -
			// si completa con quello che l azienda fa davvero, tagliato su
			// misura dello spazio che resta. Meglio una frase vera accorciata
			// che una description che la regola boccera.
			if ( mb_strlen( $corpo ) < $min ) {
				$spazio = $max - mb_strlen( $corpo ) - 2;
				$coda   = trim( self::fraseAzienda( $cfg ) );

				if ( $spazio > 20 && '' !== $coda && false === mb_strpos( $corpo, $coda ) ) {
					$pezzo = Text::polishClause( Text::truncate( $coda, $spazio ) );

					if ( mb_strlen( $pezzo ) > 15 ) {
						$corpo .= ' ' . $pezzo . '.';
					}
				}
			}
		}

		$finale = trim( Text::truncate( $corpo, $max ) );
		if ( ! preg_match( '/[.!?]$/u', $finale ) ) {
			$finale = Text::polishClause( $finale ) . '.';
		}

		return array( $finale, $finale !== $attuale );
	}

	/**
	 * Una frase vera sull azienda, da usare quando la description e corta.
	 *
	 * @param array $cfg Configurazione.
	 * @return string
	 */
	private static function fraseAzienda( array $cfg ) {
		$breve = trim( (string) ( $cfg['azienda']['descrizioneBreve'] ?? '' ) );

		if ( '' === $breve || 0 === stripos( $breve, 'DA_COMPILARE' ) ) {
			return '';
		}

		// Si prende la prima frase: la descrizione aziendale intera sarebbe
		// piu lunga di tutta la description.
		$prima = preg_split( '/(?<=[.!?])\s+/u', $breve )[0];
		$prima = trim( (string) $prima );

		if ( '' === $prima ) {
			return '';
		}

		if ( ! preg_match( '/[.!?]$/u', $prima ) ) {
			$prima .= '.';
		}

		return ' ' . $prima;
	}

	/**
	 * Estratto di 25-35 parole.
	 *
	 * @param array $doc Documento.
	 * @return array{0:string,1:bool}
	 */
	public static function excerpt( array $doc ) {
		if ( '' !== $doc['estratto'] && count( preg_split( '/\s+/u', $doc['estratto'] ) ) >= 20 ) {
			return array( $doc['estratto'], false );
		}

		$src    = preg_replace( '/\s+/u', ' ', $doc['primo_paragrafo'] ?: $doc['testo'] );
		$parole = preg_split( '/\s+/u', (string) $src );
		$out    = implode( ' ', array_slice( $parole, 0, 34 ) );

		if ( count( $parole ) > 34 ) {
			$out = Text::polishClause( $out ) . '…';
		}

		return array( $out, true );
	}

	/**
	 * Slug entro il limite di lunghezza, univoco nel sito.
	 *
	 * @param array $doc     Documento.
	 * @param array $cfg     Configurazione.
	 * @param array $occupati Slug già assegnati (per riferimento).
	 * @return array{0:string,1:bool}
	 */
	public static function slug( array $doc, array $cfg, array &$occupati ) {
		$max = $cfg['seo']['slugMax'];

		if ( mb_strlen( $doc['slug'] ) <= $max ) {
			return array( $doc['slug'], false );
		}

		$base = Text::shortSlug( $doc['focus'] ?: $doc['titolo'], 7, $max );
		if ( '' === $base ) {
			$base = preg_replace( '/-[^-]*$/', '', Text::truncate( $doc['slug'], $max ) );
		}

		$candidato = $base;
		$n         = 2;
		while ( isset( $occupati[ $candidato ] ) && $candidato !== $doc['slug'] ) {
			$candidato = $base . '-' . $n++;
		}

		$occupati[ $candidato ] = true;

		return array( $candidato, $candidato !== $doc['slug'] );
	}

	/**
	 * Piano completo per tutti i contenuti pubblicati.
	 *
	 * @param Site  $site Sito.
	 * @param array $cfg  Configurazione.
	 * @return array[]
	 */
	public static function piano( Site $site, array $cfg ) {
		$occupati = array();
		foreach ( $site->pubblicati as $d ) {
			$occupati[ $d['slug'] ] = true;
		}

		$piano = array();

		foreach ( $site->pubblicati as $doc ) {
			list( $t, $tCambiato ) = self::title( $doc, $cfg );
			list( $d, $dCambiata ) = self::description( $doc, $cfg );
			list( $e, $eCambiato ) = self::excerpt( $doc );
			list( $s, $sCambiato ) = self::slug( $doc, $cfg, $occupati );

			$piano[] = array(
				'wp_id'                => $doc['wp_id'],
				'tipo'                 => $doc['tipo'],
				'url'                  => $doc['url'],
				'percorso'             => $doc['percorso'],
				'title_attuale'        => $doc['seo_title'],
				'title_nuovo'          => $t,
				'title_cambiato'       => $tCambiato,
				'description_attuale'  => $doc['seo_desc'],
				'description_nuova'    => $d,
				'description_cambiata' => $dCambiata,
				'excerpt_nuovo'        => $e,
				'excerpt_cambiato'     => $eCambiato,
				'slug_attuale'         => $doc['slug'],
				'slug_nuovo'           => $s,
				'slug_cambiato'        => $sCambiato,
				'focus'                => $doc['focus'],
				'noindex'              => (bool) preg_match( '/privacy|cookie|termini|condizioni|grazie/i', $doc['slug'] ),
			);
		}

		return $piano;
	}

	/**
	 * Salva il piano meta sul database.
	 *
	 * @param Db    $db      Database.
	 * @param int   $auditId Audit.
	 * @param array $piano   Piano.
	 * @return int
	 */
	public static function salva( Db $db, $auditId, array $piano ) {
		$righe = array();

		foreach ( $piano as $m ) {
			$doc = $db->one( 'SELECT id FROM documento WHERE audit_id = ? AND wp_id = ?', array( $auditId, $m['wp_id'] ) );

			$righe[] = array(
				'audit_id'             => $auditId,
				'documento_id'         => $doc ? (int) $doc['id'] : 0,
				'title_nuovo'          => $m['title_nuovo'],
				'description_nuova'    => $m['description_nuova'],
				'excerpt_nuovo'        => $m['excerpt_nuovo'],
				'slug_nuovo'           => $m['slug_nuovo'],
				'title_cambiato'       => $m['title_cambiato'] ? 1 : 0,
				'description_cambiata' => $m['description_cambiata'] ? 1 : 0,
				'slug_cambiato'        => $m['slug_cambiato'] ? 1 : 0,
			);
		}

		return $db->insertMany( 'meta_piano', $righe );
	}
}
