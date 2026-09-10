<?php
/**
 * Portale finto usato dai test di integrazione: imita un sito della PA
 * con robots.txt, un feed RSS, una pagina di albo e le pagine di dettaglio.
 * Si avvia con: php -S 127.0.0.1:PORTA tests/server-di-prova.php
 */
$percorso = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$dettaglio = '<html><head><title>Bando MAD - Istituto Comprensivo Verdi</title></head>
<body><h1>Messa a disposizione</h1>
<p>Le domande devono pervenire entro le ore 12:00 del 20 settembre 2026.</p>
<p>Sede: Istituto Comprensivo Verdi, Lombardia.</p></body></html>';

switch ($percorso) {
    case '/robots.txt':
        header('Content-Type: text/plain');
        echo "User-agent: *\nDisallow: /riservato/\n";
        return true;

    case '/rss':
        header('Content-Type: application/rss+xml');
        echo file_get_contents(__DIR__ . '/fixtures/feed-gazzetta.xml');
        return true;

    case '/albo/':
        header('Content-Type: text/html');
        echo file_get_contents(__DIR__ . '/fixtures/pagina-scuola.html');
        return true;

    case '/albo-senza-date/':
        header('Content-Type: text/html');
        echo '<html><body><ul><li><a href="/albo/mad-2026">Avviso di selezione per docenti: messa a disposizione</a></li></ul></body></html>';
        return true;

    case '/albo/mad-2026':
    case '/albo/esperto-pnrr':
        header('Content-Type: text/html');
        echo $dettaglio;
        return true;

    case '/riservato/bandi':
        header('Content-Type: text/html');
        echo '<a href="/x">Concorso per professore ordinario</a>';
        return true;

    case '/latin1':
        header('Content-Type: text/html; charset=ISO-8859-1');
        echo mb_convert_encoding('<a href="/b/1">Concorso per professore associato all\'Università</a>', 'ISO-8859-1', 'UTF-8');
        return true;
}

http_response_code(404);
echo 'non trovato';
return true;
