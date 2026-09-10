<?php
require_once __DIR__ . '/Feed.php';

/**
 * Estrazione di testo e link dalle pagine HTML.
 * Volutamente senza selettori CSS: i siti di scuole, atenei e USR cambiano
 * struttura di continuo, mentre "link + testo circostante" resta stabile.
 */
class BandiHtml
{
    // Confini di blocco: il contesto di un link non deve sconfinare nella voce
    // accanto, altrimenti un avviso per docenti eredita le parole di un bando di gara.
    const BLOCCO = '#</?(?:li|tr|td|p|div|article|section|ul|ol|table|dl|dd|dt|h[1-6])\b[^>]*>#i';

    public static function toText($html)
    {
        $testo = preg_replace('#<(script|style|noscript)\b[\s\S]*?</\1>#i', ' ', (string) $html);
        $testo = preg_replace('#<br\s*/?>#i', "\n", $testo);
        $testo = preg_replace('#</(p|div|li|tr|h[1-6])>#i', "\n", $testo);
        $testo = preg_replace('/<[^>]+>/', ' ', $testo);
        $testo = BandiFeed::decodeEntities($testo);
        $testo = preg_replace('/[ \t\x{00a0}]+/u', ' ', $testo);
        $testo = preg_replace('/\n\s*\n\s*/', "\n", $testo);
        return trim($testo);
    }

    public static function titoloPagina($html)
    {
        return preg_match('#<title[^>]*>([\s\S]*?)</title>#i', (string) $html, $m) ? trim(self::toText($m[1])) : '';
    }

    /** Risolve un URL relativo rispetto alla pagina che lo contiene. */
    public static function risolvi($href, $base)
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (preg_match('~^(mailto:|tel:|javascript:|\#)~i', $href)) {
            return null;
        }

        $parti = parse_url($base);
        if (!$parti || !isset($parti['scheme'], $parti['host'])) {
            return null;
        }
        $origine = $parti['scheme'] . '://' . $parti['host'] . (isset($parti['port']) ? ':' . $parti['port'] : '');

        if (strpos($href, '//') === 0) {
            return $parti['scheme'] . ':' . $href;
        }
        if (strpos($href, '/') === 0) {
            return $origine . $href;
        }

        $cartella = isset($parti['path']) ? preg_replace('#/[^/]*$#', '/', $parti['path']) : '/';
        $percorso = $cartella . $href;

        // Normalizza ./ e ../
        $segmenti = [];
        foreach (explode('/', $percorso) as $segmento) {
            if ($segmento === '.' || $segmento === '') {
                continue;
            }
            if ($segmento === '..') {
                array_pop($segmenti);
                continue;
            }
            $segmenti[] = $segmento;
        }

        return $origine . '/' . implode('/', $segmenti);
    }

    private static function contestoDelLink($html, $inizio, $fine, $finestra = 220)
    {
        $prima = substr($html, max(0, $inizio - $finestra), min($finestra, $inizio));
        $dopo = substr($html, $fine, $finestra);

        if (preg_match_all(self::BLOCCO, $prima, $m, PREG_OFFSET_CAPTURE)) {
            $ultimo = end($m[0]);
            $prima = substr($prima, $ultimo[1] + strlen($ultimo[0]));
        }
        if (preg_match(self::BLOCCO, $dopo, $m, PREG_OFFSET_CAPTURE)) {
            $dopo = substr($dopo, 0, $m[0][1]);
        }

        return trim(preg_replace('/\s+/u', ' ', self::toText($prima . ' ' . $dopo)));
    }

    /**
     * @return array<int, array{url: string, text: string, context: string}>
     */
    public static function estraiLink($html, $base)
    {
        $sorgente = preg_replace('#<(script|style|noscript)\b[\s\S]*?</\1>#i', ' ', (string) $html);
        $link = [];
        $visti = [];

        // Delimitatore ~ perché il modello contiene un # (ancore da scartare).
        $modello = '~<a\b[^>]*href=["\']([^"\'#][^"\']*)["\'][^>]*>([\s\S]*?)</a>~i';
        if (!preg_match_all($modello, $sorgente, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        foreach ($m[0] as $indice => $intero) {
            $url = self::risolvi(BandiFeed::decodeEntities($m[1][$indice][0]), $base);
            if ($url === null) {
                continue;
            }

            $testo = trim(preg_replace('/\s+/u', ' ', self::toText($m[2][$indice][0])));
            $chiave = $url . '|' . mb_strtolower($testo, 'UTF-8');
            if (isset($visti[$chiave])) {
                continue;
            }
            $visti[$chiave] = true;

            $inizio = $intero[1];
            $link[] = [
                'url' => $url,
                'text' => $testo,
                'context' => self::contestoDelLink($sorgente, $inizio, $inizio + strlen($intero[0])),
            ];
        }

        return $link;
    }
}
