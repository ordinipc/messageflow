<?php
/**
 * Generazione dei dati strutturati JSON-LD.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Fix;

use SeoGeo\Site;

/**
 * Un solo grafo per pagina, con entità collegate da @id: è il formato che
 * Google raccomanda per evitare schema duplicati e scollegati.
 */
class Schema {

	/**
	 * Valore utilizzabile, oppure null se è rimasto un segnaposto.
	 *
	 * @param mixed $v Valore.
	 * @return mixed|null
	 */
	private static function val( $v ) {
		if ( is_string( $v ) && ( '' === $v || 0 === stripos( $v, 'DA_COMPILARE' ) ) ) {
			return null;
		}

		return $v;
	}

	/**
	 * Rimuove ricorsivamente chiavi vuote: uno schema con campi vuoti genera warning.
	 *
	 * @param mixed $dati Struttura.
	 * @return mixed
	 */
	public static function pulisci( $dati ) {
		if ( ! is_array( $dati ) ) {
			return $dati;
		}

		$out = array();

		foreach ( $dati as $k => $v ) {
			$v = self::pulisci( $v );

			if ( null === $v || '' === $v || ( is_array( $v ) && empty( $v ) ) ) {
				continue;
			}

			$out[ $k ] = $v;
		}

		return $out;
	}

	/**
	 * Nodo Organization + ProfessionalService.
	 *
	 * @param array  $cfg Configurazione.
	 * @param string $url URL del sito.
	 * @return array
	 */
	public static function organization( array $cfg, $url ) {
		$a   = $cfg['azienda'];
		$ind = $a['indirizzo'];

		$sameAs = array();
		foreach ( (array) $a['profili'] as $p ) {
			if ( null !== self::val( $p ) ) {
				$sameAs[] = $p;
			}
		}

		$nodo = array(
			'@type'       => array( 'Organization', 'ProfessionalService' ),
			'@id'         => $url . '/#organization',
			'name'        => $a['nome'],
			'legalName'   => self::val( $a['nomeLegale'] ) ?: $a['nome'],
			'url'         => $url . '/',
			'description' => $a['descrizioneBreve'],
			'email'       => self::val( $a['email'] ),
			'telephone'   => self::val( $a['telefono'] ) ?: self::val( $a['cellulare'] ),
			'vatID'       => self::val( $a['partitaIva'] ),
			'foundingDate' => self::val( $a['fondazione'] ),
			'priceRange'  => $a['fasciaPrezzo'],
			'logo'        => array(
				'@type' => 'ImageObject',
				'@id'   => $url . '/#logo',
				'url'   => $a['logo'],
			),
			'image'       => array( '@id' => $url . '/#logo' ),
			'knowsAbout'  => array( 'Realizzazione siti web', 'E-commerce', 'SEO locale', 'Social media marketing', 'Produzione video', 'Graphic design', 'CRM e gestionali' ),
			'sameAs'      => $sameAs,
		);

		if ( self::val( $ind['via'] ) || self::val( $ind['cap'] ) ) {
			$nodo['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => self::val( $ind['via'] ),
				'addressLocality' => $ind['citta'],
				'addressRegion'   => $ind['provincia'],
				'postalCode'      => self::val( $ind['cap'] ),
				'addressCountry'  => $ind['nazione'],
			);
		}

		if ( self::val( $ind['latitudine'] ) && self::val( $ind['longitudine'] ) ) {
			$nodo['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $ind['latitudine'],
				'longitude' => $ind['longitudine'],
			);
		}

		$nodo['areaServed'] = array_map(
			static fn( $c ) => array( '@type' => 'City', 'name' => $c ),
			(array) $a['areaServita']
		);

		$nodo['openingHoursSpecification'] = array_map(
			static fn( $o ) => array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $o['giorni'],
				'opens'     => $o['apre'],
				'closes'    => $o['chiude'],
			),
			(array) $a['orari']
		);

		return $nodo;
	}

	/**
	 * Nodo WebSite con SearchAction.
	 *
	 * @param array  $cfg Configurazione.
	 * @param string $url URL del sito.
	 * @return array
	 */
	public static function website( array $cfg, $url ) {
		return array(
			'@type'           => 'WebSite',
			'@id'             => $url . '/#website',
			'url'             => $url . '/',
			'name'            => $cfg['azienda']['nome'],
			'description'     => $cfg['azienda']['descrizioneBreve'],
			'inLanguage'      => 'it-IT',
			'publisher'       => array( '@id' => $url . '/#organization' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array( '@type' => 'EntryPoint', 'urlTemplate' => $url . '/?s={search_term_string}' ),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * Nodo Person dell autore principale.
	 *
	 * @param array  $cfg Configurazione.
	 * @param string $url URL del sito.
	 * @return array
	 */
	public static function person( array $cfg, $url ) {
		$autore = $cfg['autori'][0] ?? array();
		$nome   = trim( ( self::val( $autore['nome'] ?? '' ) ?: '' ) . ' ' . ( self::val( $autore['cognome'] ?? '' ) ?: '' ) );
		$nome   = '' !== $nome ? $nome : $cfg['azienda']['nome'];

		return array(
			'@type'       => 'Person',
			'@id'         => $url . '/#/schema/person/' . rawurlencode( strtolower( str_replace( ' ', '-', $nome ) ) ),
			'name'        => $nome,
			'jobTitle'    => self::val( $autore['ruolo'] ?? '' ),
			'description' => self::val( $autore['bio'] ?? '' ),
			'worksFor'    => array( '@id' => $url . '/#organization' ),
			'sameAs'      => array_filter( array( self::val( $autore['linkedin'] ?? '' ) ) ),
		);
	}

	/**
	 * Coppie domanda/risposta estratte dai titoli interrogativi.
	 *
	 * @param array $doc Documento.
	 * @return array[]
	 */
	public static function faq( array $doc ) {
		$out = array();

		foreach ( $doc['titoli'] as $h ) {
			if ( $h['livello'] < 2 || false === strpos( $h['testo'], '?' ) ) {
				continue;
			}

			$dopo = substr( $doc['contenuto'], $h['posizione'] + strlen( $h['raw'] ) );

			if ( ! preg_match( '#<p[^>]*>([\s\S]*?)</p>#i', $dopo, $m ) ) {
				continue;
			}

			$risposta = trim( preg_replace( '/\s+/u', ' ', strip_tags( $m[1] ) ) );

			if ( count( preg_split( '/\s+/u', $risposta ) ) < 8 ) {
				continue;
			}

			$out[] = array(
				'@type'          => 'Question',
				'name'           => $h['testo'],
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => mb_substr( $risposta, 0, 600 ) ),
			);
		}

		return $out;
	}

	/**
	 * Grafo completo per un documento.
	 *
	 * @param Site  $site Sito.
	 * @param array $cfg  Configurazione.
	 * @param array $doc  Documento.
	 * @return array
	 */
	public static function perDocumento( Site $site, array $cfg, array $doc ) {
		$url    = rtrim( $site->url, '/' );
		$person = self::person( $cfg, $url );

		$briciole = array( array( 'name' => 'Home', 'item' => $url . '/' ) );
		if ( 'post' === $doc['tipo'] && isset( $doc['categorie'][0] ) ) {
			$briciole[] = array( 'name' => $doc['categorie'][0]['nome'], 'item' => $url . '/category/' . $doc['categorie'][0]['slug'] . '/' );
		}
		$briciole[] = array( 'name' => $doc['titolo'], 'item' => $doc['url'] );

		$elenco = array();
		foreach ( $briciole as $i => $b ) {
			$elenco[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $b['name'], 'item' => $b['item'] );
		}

		$grafo = array(
			self::organization( $cfg, $url ),
			self::website( $cfg, $url ),
			$person,
			array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $doc['url'] . '#breadcrumb',
				'itemListElement' => $elenco,
			),
		);

		$eServizio = 'page' === $doc['tipo'] && preg_match( '/servizi|realizzazione|gestione|produzione|sviluppo|marketing|design|naming|stampe/i', $doc['slug'] );

		if ( $eServizio ) {
			$grafo[] = array(
				'@type'       => 'Service',
				'@id'         => $doc['url'] . '#service',
				'name'        => $doc['titolo'],
				'serviceType' => $doc['focus'] ?: $doc['titolo'],
				'description' => $doc['seo_desc'],
				'provider'    => array( '@id' => $url . '/#organization' ),
				'areaServed'  => array_map( static fn( $c ) => array( '@type' => 'City', 'name' => $c ), (array) $cfg['azienda']['areaServita'] ),
				'audience'    => array( '@type' => 'BusinessAudience', 'name' => 'PMI e professionisti' ),
				'offers'      => array( '@type' => 'Offer', 'availability' => 'https://schema.org/InStock', 'priceCurrency' => 'EUR', 'url' => $doc['url'] ),
			);
		}

		if ( 'post' === $doc['tipo'] ) {
			$grafo[] = array(
				'@type'            => 'BlogPosting',
				'@id'              => $doc['url'] . '#article',
				'headline'         => $doc['seo_title'] ?: $doc['titolo'],
				'description'      => $doc['seo_desc'],
				'inLanguage'       => 'it-IT',
				'datePublished'    => str_replace( ' ', 'T', (string) $doc['data'] ) . '+02:00',
				'dateModified'     => str_replace( ' ', 'T', (string) ( $doc['modificato'] ?: $doc['data'] ) ) . '+02:00',
				'author'           => array( '@id' => $person['@id'] ),
				'publisher'        => array( '@id' => $url . '/#organization' ),
				'mainEntityOfPage' => array( '@id' => $doc['url'] ),
				'articleSection'   => $doc['categorie'][0]['nome'] ?? '',
				'keywords'         => array_filter( array( $doc['focus'] ) ),
				'wordCount'        => $doc['parole'],
				// speakable: indica ad assistenti vocali e riassunti AI cosa leggere.
				'speakable'        => array( '@type' => 'SpeakableSpecification', 'cssSelector' => array( 'h1', '.mdi-in-breve' ) ),
			);
		}

		$faq = self::faq( $doc );

		if ( count( $faq ) >= 2 ) {
			$grafo[] = array(
				'@type'      => 'FAQPage',
				'@id'        => $doc['url'] . '#faq',
				'mainEntity' => $faq,
			);
		}

		return self::pulisci(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $grafo,
			)
		);
	}

	/**
	 * Grafi per tutti i contenuti pubblicati.
	 *
	 * @param Site  $site Sito.
	 * @param array $cfg  Configurazione.
	 * @return array[]
	 */
	public static function piano( Site $site, array $cfg ) {
		$out = array();

		foreach ( $site->pubblicati as $doc ) {
			$schema = self::perDocumento( $site, $cfg, $doc );
			$tipi   = array();

			foreach ( $schema['@graph'] as $nodo ) {
				$tipi[] = is_array( $nodo['@type'] ) ? implode( '+', $nodo['@type'] ) : $nodo['@type'];
			}

			$out[] = array(
				'percorso' => $doc['percorso'],
				'url'      => $doc['url'],
				'tipi'     => $tipi,
				'schema'   => $schema,
			);
		}

		return $out;
	}
}
