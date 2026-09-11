<?php
/**
 * Traduzione dei dati di Search Console in cose da fare.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Search;

/**
 * Dai numeri di Google ricava le priorità di lavoro.
 *
 * Il punteggio dell audit dice cosa è sbagliato secondo le linee guida; questi
 * segnali dicono cosa sta effettivamente succedendo in Google. Quando i due si
 * contraddicono, hanno ragione questi.
 *
 * Nessuna chiamata di rete qui dentro: solo righe in ingresso e segnali in
 * uscita, così il comportamento si può collaudare per intero.
 */
class Segnali {

	/**
	 * CTR che ci si aspetta a ogni posizione.
	 *
	 * Valori d ordine di grandezza, non una legge: servono a distinguere "il
	 * titolo non funziona" da "sei semplicemente in fondo".
	 *
	 * @var array<int,float>
	 */
	const CTR_ATTESO = array(
		1 => 27.0, 2 => 15.0, 3 => 11.0, 4 => 8.0, 5 => 6.0,
		6 => 4.5, 7 => 3.5, 8 => 3.0, 9 => 2.5, 10 => 2.2,
	);

	/**
	 * Calcola tutti i segnali.
	 *
	 * @param array $dati    'pagine', 'query', 'pagine_prec' (facoltativo),
	 *                       'documenti' (i contenuti noti al programma).
	 * @param array $opzioni Soglie.
	 * @return array[] Segnali ordinati per priorità decrescente.
	 */
	public static function deriva( array $dati, array $opzioni = array() ) {
		$minImpression = (int) ( $opzioni['min_impression'] ?? 20 );
		$segnali       = array();

		$segnali = array_merge( $segnali, self::quasiPrimaPagina( $dati['query'] ?? array(), $minImpression ) );
		$segnali = array_merge( $segnali, self::titoloCheNonRende( $dati['query'] ?? array(), max( 50, $minImpression ) ) );
		$segnali = array_merge( $segnali, self::cannibalizzazione( $dati['query'] ?? array(), $minImpression ) );
		$segnali = array_merge( $segnali, self::inCalo( $dati['pagine'] ?? array(), $dati['pagine_prec'] ?? array(), $minImpression ) );
		$segnali = array_merge( $segnali, self::invisibili( $dati['pagine'] ?? array(), $dati['documenti'] ?? array(), $dati['periodo']['da'] ?? '' ) );

		usort(
			$segnali,
			static function ( $a, $b ) {
				return ( $b['priorita'] <=> $a['priorita'] ) ?: ( $b['impression'] <=> $a['impression'] );
			}
		);

		return $segnali;
	}

	/**
	 * Pagine a un passo dalla prima pagina.
	 *
	 * È il lavoro che rende di più: chi è in posizione 11-20 ha già la fiducia
	 * di Google sull argomento, gli manca poco. Chi è in posizione 60 ha un
	 * problema diverso e più lungo.
	 *
	 * @param array[] $query         Righe query+pagina.
	 * @param int     $minImpression Soglia sotto la quale il dato non è significativo.
	 * @return array[]
	 */
	private static function quasiPrimaPagina( array $query, $minImpression ) {
		$segnali = array();

		foreach ( $query as $riga ) {
			$posizione = (float) $riga['posizione'];

			if ( $posizione < 8 || $posizione > 20 || (int) $riga['impression'] < $minImpression ) {
				continue;
			}

			// Più si è vicini alla prima pagina, più la spinta conviene.
			$priorita = (int) round( 100 - ( $posizione - 8 ) * 4 );

			$segnali[] = array(
				'tipo'       => 'quasi_prima_pagina',
				'titolo'     => 'A un passo dalla prima pagina',
				'url'        => (string) ( $riga['page'] ?? '' ),
				'query'      => (string) ( $riga['query'] ?? '' ),
				'posizione'  => $posizione,
				'impression' => (int) $riga['impression'],
				'clic'       => (int) $riga['clic'],
				'priorita'   => $priorita,
				'azione'     => 'riscrivi_e_collega',
				'spiegazione' => sprintf(
					'Posizione %s per "%s" con %d impression: mancano poche posizioni. Vale più di qualsiasi articolo nuovo.',
					self::numero( $posizione ),
					$riga['query'] ?? '',
					(int) $riga['impression']
				),
			);
		}

		return $segnali;
	}

	/**
	 * Pagine già in alto che però non vengono cliccate.
	 *
	 * Qui il contenuto va bene e il problema è la vetrina: title e description.
	 * Sono le correzioni più veloci e più redditizie.
	 *
	 * @param array[] $query         Righe query+pagina.
	 * @param int     $minImpression Soglia.
	 * @return array[]
	 */
	private static function titoloCheNonRende( array $query, $minImpression ) {
		$segnali = array();

		foreach ( $query as $riga ) {
			$posizione = (float) $riga['posizione'];

			if ( $posizione > 10.5 || (int) $riga['impression'] < $minImpression ) {
				continue;
			}

			$atteso = self::CTR_ATTESO[ (int) round( $posizione ) ] ?? 2.0;
			$vero   = (float) $riga['ctr'];

			// Metà del previsto: sotto questa soglia non è rumore statistico.
			if ( $vero > $atteso * 0.5 ) {
				continue;
			}

			$segnali[] = array(
				'tipo'       => 'titolo_che_non_rende',
				'titolo'     => 'Ti vedono ma non ti cliccano',
				'url'        => (string) ( $riga['page'] ?? '' ),
				'query'      => (string) ( $riga['query'] ?? '' ),
				'posizione'  => $posizione,
				'impression' => (int) $riga['impression'],
				'clic'       => (int) $riga['clic'],
				'priorita'   => (int) round( 85 + min( 10, ( $atteso - $vero ) ) ),
				'azione'     => 'riscrivi_meta',
				'spiegazione' => sprintf(
					'Posizione %s per "%s", ma solo %s%% di clic contro il %s%% atteso: il problema è il titolo in Google, non il contenuto.',
					self::numero( $posizione ),
					$riga['query'] ?? '',
					number_format( $vero, 1, ',', '' ),
					number_format( $atteso, 1, ',', '' )
				),
			);
		}

		return $segnali;
	}

	/**
	 * Cannibalizzazione misurata, non ipotizzata.
	 *
	 * Il triage editoriale trova le sovrapposizioni leggendo i testi; qui si
	 * vede quando Google davvero alterna due pagine sulla stessa ricerca, che
	 * è la definizione operativa del problema.
	 *
	 * @param array[] $query         Righe query+pagina.
	 * @param int     $minImpression Soglia.
	 * @return array[]
	 */
	private static function cannibalizzazione( array $query, $minImpression ) {
		$perQuery = array();

		foreach ( $query as $riga ) {
			$chiave = mb_strtolower( (string) ( $riga['query'] ?? '' ), 'UTF-8' );

			if ( '' === $chiave || '' === (string) ( $riga['page'] ?? '' ) ) {
				continue;
			}

			$perQuery[ $chiave ][] = $riga;
		}

		$segnali = array();

		foreach ( $perQuery as $chiave => $righe ) {
			if ( count( $righe ) < 2 ) {
				continue;
			}

			$totale = array_sum( array_column( $righe, 'impression' ) );

			if ( $totale < $minImpression ) {
				continue;
			}

			usort( $righe, static fn( $a, $b ) => $b['impression'] <=> $a['impression'] );

			// Se una pagina prende quasi tutto, Google ha già scelto: non è un problema.
			if ( $righe[0]['impression'] > $totale * 0.8 ) {
				continue;
			}

			$segnali[] = array(
				'tipo'       => 'cannibalizzazione',
				'titolo'     => 'Due pagine tue competono sulla stessa ricerca',
				'url'        => (string) $righe[0]['page'],
				'query'      => (string) $righe[0]['query'],
				'posizione'  => (float) $righe[0]['posizione'],
				'impression' => (int) $totale,
				'clic'       => (int) array_sum( array_column( $righe, 'clic' ) ),
				'priorita'   => 75,
				'azione'     => 'accorpa',
				'concorrenti' => array_map(
					static fn( $r ) => array( 'url' => $r['page'], 'impression' => (int) $r['impression'], 'posizione' => (float) $r['posizione'] ),
					array_slice( $righe, 0, 5 )
				),
				'spiegazione' => sprintf(
					'Su "%s" Google alterna %d tue pagine: si tolgono forza a vicenda. Da accorpare tenendo la più forte.',
					$righe[0]['query'],
					count( $righe )
				),
			);
		}

		return $segnali;
	}

	/**
	 * Pagine che stanno perdendo posizioni.
	 *
	 * Serve anche come rete di sicurezza sulle riscritture fatte dal programma:
	 * se dopo un intervento la pagina scende, si vede qui.
	 *
	 * @param array[] $pagine        Periodo attuale.
	 * @param array[] $precedenti    Periodo precedente.
	 * @param int     $minImpression Soglia.
	 * @return array[]
	 */
	private static function inCalo( array $pagine, array $precedenti, $minImpression ) {
		if ( ! $precedenti ) {
			return array();
		}

		$prima = array();

		foreach ( $precedenti as $riga ) {
			$prima[ (string) ( $riga['page'] ?? '' ) ] = $riga;
		}

		$segnali = array();

		foreach ( $pagine as $riga ) {
			$url = (string) ( $riga['page'] ?? '' );

			if ( ! isset( $prima[ $url ] ) || (int) $riga['impression'] < $minImpression ) {
				continue;
			}

			$delta = (float) $riga['posizione'] - (float) $prima[ $url ]['posizione'];

			if ( $delta < 3 ) {
				continue;
			}

			$segnali[] = array(
				'tipo'       => 'in_calo',
				'titolo'     => 'Pagina in calo',
				'url'        => $url,
				'query'      => '',
				'posizione'  => (float) $riga['posizione'],
				'impression' => (int) $riga['impression'],
				'clic'       => (int) $riga['clic'],
				'priorita'   => (int) round( min( 95, 70 + $delta ) ),
				'azione'     => 'verifica',
				'spiegazione' => sprintf(
					'Scesa di %s posizioni rispetto al periodo precedente (da %s a %s). Se è stata modificata di recente, guarda cosa è cambiato.',
					number_format( $delta, 1, ',', '' ),
					self::numero( (float) $prima[ $url ]['posizione'] ),
					self::numero( (float) $riga['posizione'] )
				),
			);
		}

		return $segnali;
	}

	/**
	 * Contenuti che Google non mostra mai.
	 *
	 * Zero impression in tutto il periodo su una pagina pubblicata significa
	 * che non è indicizzata, oppure che non risponde a nessuna domanda reale.
	 * Entrambe le cose vanno sapute.
	 *
	 * @param array[] $pagine    Pagine con dati.
	 * @param array[] $documenti Contenuti noti al programma ('url', 'titolo', 'pubblicato').
	 * @param string  $daQuando  Inizio del periodo osservato (AAAA-MM-GG).
	 * @return array[]
	 */
	private static function invisibili( array $pagine, array $documenti, $daQuando = '' ) {
		if ( ! $documenti ) {
			return array();
		}

		$viste = array();

		foreach ( $pagine as $riga ) {
			$viste[ self::normalizza( (string) ( $riga['page'] ?? '' ) ) ] = true;
		}

		$segnali = array();

		foreach ( $documenti as $documento ) {
			$url = (string) ( $documento['url'] ?? '' );

			if ( '' === $url || isset( $viste[ self::normalizza( $url ) ] ) ) {
				continue;
			}

			// Un contenuto pubblicato dopo l inizio del periodo, o pochi giorni
			// prima, non è invisibile: è solo nuovo. Google impiega settimane.
			if ( '' !== $daQuando && self::troppoRecente( (string) ( $documento['pubblicato'] ?? '' ), $daQuando ) ) {
				continue;
			}

			$segnali[] = array(
				'tipo'       => 'mai_mostrata',
				'titolo'     => 'Google non la mostra mai',
				'url'        => $url,
				'query'      => '',
				'posizione'  => 0.0,
				'impression' => 0,
				'clic'       => 0,
				'priorita'   => 60,
				'azione'     => 'ispeziona',
				'spiegazione' => sprintf(
					'"%s" non ha ricevuto nemmeno una impression nel periodo: o non è indicizzata, o non risponde a nessuna ricerca reale.',
					(string) ( $documento['titolo'] ?? $url )
				),
			);
		}

		return $segnali;
	}

	/**
	 * Il contenuto è troppo nuovo perché l assenza di dati significhi qualcosa?
	 *
	 * @param string $pubblicato Data di pubblicazione.
	 * @param string $daQuando   Inizio del periodo osservato.
	 * @return bool
	 */
	private static function troppoRecente( $pubblicato, $daQuando ) {
		if ( '' === trim( $pubblicato ) ) {
			return false;
		}

		$quando = strtotime( $pubblicato );
		$inizio = strtotime( $daQuando );

		if ( ! $quando || ! $inizio ) {
			return false;
		}

		// Due settimane di margine prima dell inizio del periodo: sotto questa
		// soglia Google spesso non ha ancora fatto in tempo a mostrarlo.
		return $quando > ( $inizio - 14 * 86400 );
	}

	/**
	 * Indirizzo confrontabile: senza schema, www e barra finale.
	 *
	 * @param string $url Indirizzo.
	 * @return string
	 */
	private static function normalizza( $url ) {
		$url = preg_replace( '~^https?://~i', '', (string) $url );
		$url = preg_replace( '~^www\.~i', '', (string) $url );

		return rtrim( strtolower( (string) $url ), '/' );
	}

	/**
	 * Posizione leggibile.
	 *
	 * @param float $posizione Posizione media.
	 * @return string
	 */
	private static function numero( $posizione ) {
		return number_format( (float) $posizione, 1, ',', '' );
	}
}
