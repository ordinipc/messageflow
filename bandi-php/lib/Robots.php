<?php
/**
 * Parser minimale di robots.txt: gruppi User-agent con Allow/Disallow,
 * match per prefisso più lungo, wildcard * e ancoraggio $.
 */
class BandiRobots
{
    public static function parse($testo)
    {
        $gruppi = [];
        $indice = -1; // gruppo corrente: si usa l'indice, non un riferimento
        $rigaPrecedenteEraUserAgent = false;

        foreach (preg_split('/\r?\n/', (string) $testo) as $rigaGrezza) {
            $riga = trim(preg_replace('/#.*$/', '', $rigaGrezza));
            if ($riga === '' || strpos($riga, ':') === false) {
                continue;
            }

            list($campo, $valore) = array_map('trim', explode(':', $riga, 2));
            $campo = strtolower($campo);

            if ($campo === 'user-agent') {
                // User-agent consecutivi condividono lo stesso gruppo di regole.
                if ($indice < 0 || !$rigaPrecedenteEraUserAgent) {
                    $gruppi[] = ['agents' => [], 'rules' => [], 'crawlDelay' => null];
                    $indice = count($gruppi) - 1;
                }
                $gruppi[$indice]['agents'][] = strtolower($valore);
                $rigaPrecedenteEraUserAgent = true;
                continue;
            }

            $rigaPrecedenteEraUserAgent = false;
            if ($indice < 0) {
                continue;
            }

            if ($campo === 'allow' || $campo === 'disallow') {
                if ($campo === 'disallow' && $valore === '') {
                    continue; // Disallow vuoto = tutto permesso
                }
                $gruppi[$indice]['rules'][] = ['allow' => $campo === 'allow', 'path' => $valore];
            } elseif ($campo === 'crawl-delay' && is_numeric(str_replace(',', '.', $valore))) {
                $gruppi[$indice]['crawlDelay'] = (float) str_replace(',', '.', $valore);
            }
        }

        return $gruppi;
    }

    private static function lunghezzaMatch($modello, $percorso)
    {
        if (strpos($modello, '*') === false && substr($modello, -1) !== '$') {
            return strpos($percorso, $modello) === 0 ? strlen($modello) : -1;
        }

        $ancorato = substr($modello, -1) === '$';
        $corpo = $ancorato ? substr($modello, 0, -1) : $modello;
        $regex = '#^' . implode('.*', array_map('preg_quote', explode('*', $corpo))) . ($ancorato ? '$' : '') . '#';

        return preg_match($regex, $percorso) ? strlen($corpo) : -1;
    }

    private static function gruppoPerAgente($gruppi, $userAgent)
    {
        $ua = strtolower((string) $userAgent);
        $migliore = null;
        $punteggio = -1;

        foreach ($gruppi as $gruppo) {
            foreach ($gruppo['agents'] as $agente) {
                $valore = -1;
                if ($agente === '*') {
                    $valore = 0;
                } elseif ($agente !== '' && strpos($ua, $agente) !== false) {
                    $valore = strlen($agente);
                }
                if ($valore > $punteggio) {
                    $punteggio = $valore;
                    $migliore = $gruppo;
                }
            }
        }

        return $migliore;
    }

    /** @return bool true se il nostro crawler può scaricare l'URL */
    public static function isAllowed($robotsTxt, $url, $userAgent)
    {
        $gruppi = self::parse($robotsTxt);
        if (!$gruppi) {
            return true;
        }

        $gruppo = self::gruppoPerAgente($gruppi, $userAgent);
        if (!$gruppo || !$gruppo['rules']) {
            return true;
        }

        $parti = parse_url($url);
        $percorso = ($parti['path'] ?? '/') . (isset($parti['query']) ? '?' . $parti['query'] : '');

        $decisione = true;
        $migliore = -1;

        foreach ($gruppo['rules'] as $regola) {
            $lunghezza = self::lunghezzaMatch($regola['path'], $percorso);
            if ($lunghezza < 0) {
                continue;
            }
            // A parità di lunghezza vince Allow.
            if ($lunghezza > $migliore || ($lunghezza === $migliore && $regola['allow'])) {
                $migliore = $lunghezza;
                $decisione = $regola['allow'];
            }
        }

        return $decisione;
    }

    public static function crawlDelay($robotsTxt, $userAgent)
    {
        $gruppo = self::gruppoPerAgente(self::parse($robotsTxt), $userAgent);
        return $gruppo && $gruppo['crawlDelay'] !== null ? $gruppo['crawlDelay'] : null;
    }
}
