<?php
/**
 * Indirizzamento alla vera radice pubblica.
 *
 * L applicazione vive in public/: quando il file .htaccess di questa cartella
 * non può funzionare (mod_rewrite assente o AllowOverride disattivato) questo
 * file interviene e reindirizza il browser, così l app resta raggiungibile
 * anche caricando il progetto in una sottocartella dello spazio web.
 *
 * @package SeoGeoAudit
 */

header( 'Location: public/', true, 302 );

echo '<!doctype html><meta charset="utf-8">'
	. '<title>SEO &amp; GEO Audit</title>'
	. '<p>L applicazione si trova in <a href="public/">public/</a>.</p>';
