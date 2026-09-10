<?php
/**
 * Autoload PSR-4 minimale: il progetto non usa Composer per restare
 * installabile su qualsiasi hosting con un semplice upload FTP.
 *
 * @package SeoGeoAudit
 */

spl_autoload_register(
	static function ( $classe ) {
		$prefisso = 'SeoGeo\\';

		if ( 0 !== strpos( $classe, $prefisso ) ) {
			return;
		}

		$relativo = substr( $classe, strlen( $prefisso ) );
		$file     = __DIR__ . '/' . str_replace( '\\', '/', $relativo ) . '.php';

		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
);
