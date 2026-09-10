<?php
/**
 * Parser RSS 2.0 / Atom tollerante: i feed della PA italiana sono spesso
 * malformati (entity non codificate, namespace misti, CDATA annidati),
 * quindi si lavora con espressioni regolari invece che con un parser XML rigido.
 */
class BandiFeed
{
    public static function decodeEntities($testo)
    {
        $testo = (string) $testo;
        $testo = preg_replace_callback('/&#x([0-9a-fA-F]+);/', function ($m) {
            return mb_chr(hexdec($m[1]), 'UTF-8');
        }, $testo);
        $testo = preg_replace_callback('/&#(\d+);/', function ($m) {
            return mb_chr((int) $m[1], 'UTF-8');
        }, $testo);

        return html_entity_decode($testo, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function pulisci($testo)
    {
        $testo = preg_replace('/<!\[CDATA\[([\s\S]*?)\]\]>/', '$1', (string) $testo);
        $testo = self::decodeEntities($testo);
        $testo = preg_replace('/<[^>]+>/', ' ', $testo);
        return trim(preg_replace('/\s+/u', ' ', $testo));
    }

    private static function primoTag($xml, $nome)
    {
        $n = preg_quote($nome, '#');
        $modello = '#<(?:[a-zA-Z0-9_-]+:)?' . $n . '(\s[^>]*)?>([\s\S]*?)</(?:[a-zA-Z0-9_-]+:)?' . $n . '>#i';
        return preg_match($modello, $xml, $m) ? $m[2] : null;
    }

    private static function blocchi($xml, $nome)
    {
        $n = preg_quote($nome, '#');
        $modello = '#<(?:[a-zA-Z0-9_-]+:)?' . $n . '(?:\s[^>]*)?>([\s\S]*?)</(?:[a-zA-Z0-9_-]+:)?' . $n . '>#i';
        return preg_match_all($modello, $xml, $m) ? $m[1] : [];
    }

    private static function linkVoce($blocco)
    {
        $link = self::primoTag($blocco, 'link');
        if ($link !== null && self::pulisci($link) !== '') {
            return self::pulisci($link);
        }

        // Atom: <link rel="alternate" href="..."/>
        if (preg_match_all('#<(?:[a-zA-Z0-9_-]+:)?link\b[^>]*>#i', $blocco, $m)) {
            foreach ($m[0] as $tag) {
                if (preg_match('/rel=["\']?(self|edit|replies|enclosure)/i', $tag)) {
                    continue;
                }
                if (preg_match('/href=["\']([^"\']+)["\']/i', $tag, $href)) {
                    return self::decodeEntities($href[1]);
                }
            }
        }

        $guid = self::pulisci((string) self::primoTag($blocco, 'guid'));
        return preg_match('#^https?://#i', $guid) ? $guid : '';
    }

    /**
     * @return array{title: string, items: array<int, array>}
     */
    public static function parse($xml)
    {
        $testo = (string) $xml;
        $canale = self::primoTag($testo, 'channel') ?: $testo;
        $titoloFeed = self::pulisci((string) self::primoTag($canale, 'title'));

        $blocchi = array_merge(self::blocchi($testo, 'item'), self::blocchi($testo, 'entry'));
        $voci = [];

        foreach ($blocchi as $blocco) {
            $pubblicato = self::primoTag($blocco, 'pubDate')
                ?? self::primoTag($blocco, 'published')
                ?? self::primoTag($blocco, 'updated')
                ?? self::primoTag($blocco, 'date');

            $descrizione = self::primoTag($blocco, 'description')
                ?? self::primoTag($blocco, 'summary')
                ?? self::primoTag($blocco, 'content')
                ?? '';

            $categorie = array_filter(array_map([self::class, 'pulisci'], self::blocchi($blocco, 'category')));
            if (preg_match_all('/<category\b[^>]*term=["\']([^"\']+)["\']/i', $blocco, $m)) {
                foreach ($m[1] as $termine) {
                    $categorie[] = self::decodeEntities($termine);
                }
            }

            $voce = [
                'title' => self::pulisci((string) self::primoTag($blocco, 'title')),
                'link' => self::linkVoce($blocco),
                'summary' => self::pulisci($descrizione),
                'publishedAt' => $pubblicato !== null ? self::pulisci($pubblicato) : null,
                'categories' => array_values($categorie),
            ];

            if ($voce['title'] !== '' || $voce['link'] !== '') {
                $voci[] = $voce;
            }
        }

        return ['title' => $titoloFeed, 'items' => $voci];
    }

    public static function sembraUnFeed($testo)
    {
        return (bool) preg_match('/<rss[\s>]|<feed[\s>]|<rdf:RDF[\s>]/i', substr((string) $testo, 0, 2000));
    }
}
