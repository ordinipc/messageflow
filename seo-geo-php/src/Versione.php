<?php
/**
 * Versione del gestionale.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo;

/**
 * Il numero sta nel codice, non in config.php: config.php si puo tenere
 * senza sovrascriverlo quando si aggiorna, e allora direbbe la versione
 * sbagliata. Qui invece cambia insieme ai file che cambiano davvero.
 *
 * Serve a rispondere a colpo d occhio alla domanda "che versione ho
 * installato?", che senza un numero visibile si puo solo indovinare.
 */
class Versione {

	/** Stessa numerazione del plugin, cosi le due si confrontano. */
	const NUMERO = '2.30.0';

	/**
	 * Versione del gestionale.
	 *
	 * @return string
	 */
	public static function numero() {
		return self::NUMERO;
	}
}
