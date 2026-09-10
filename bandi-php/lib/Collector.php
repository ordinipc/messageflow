<?php
require_once __DIR__ . '/Http.php';
require_once __DIR__ . '/Feed.php';
require_once __DIR__ . '/Html.php';
require_once __DIR__ . '/Dates.php';
require_once __DIR__ . '/Classifier.php';
require_once __DIR__ . '/Store.php';

/**
 * Orchestrazione della raccolta: scarica una fonte, ne estrae le voci,
 * tiene solo i bandi per docenti e ne ricava scadenza ed ente.
 */
class BandiCollector
{
    private $http;
    private $soglia;
    private $maxPerFonte;
    private $ora;

    public function __construct(BandiHttp $http, array $opzioni = [])
    {
        $this->http = $http;
        $this->soglia = (int) ($opzioni['soglia'] ?? 5);
        $this->maxPerFonte = (int) ($opzioni['maxPerFonte'] ?? 60);
        $this->ora = $opzioni['ora'] ?? gmdate('c');
    }

    private static function valoreAnnidato($dati, $percorso)
    {
        if (!$percorso) {
            return $dati;
        }
        foreach (explode('.', $percorso) as $chiave) {
            if (!is_array($dati) || !array_key_exists($chiave, $dati)) {
                return null;
            }
            $dati = $dati[$chiave];
        }
        return $dati;
    }

    public function candidatiDaFeed($corpo)
    {
        $voci = [];
        foreach (BandiFeed::parse($corpo)['items'] as $voce) {
            $voci[] = [
                'title' => $voce['title'],
                'url' => $voce['link'],
                'context' => trim($voce['summary'] . ' ' . implode(' ', $voce['categories'])),
                'publishedAt' => $voce['publishedAt'] ? BandiDates::parse($voce['publishedAt']) : null,
            ];
        }
        return $voci;
    }

    public function candidatiDaHtml($corpo, $urlFinale)
    {
        $titoloPagina = BandiHtml::titoloPagina($corpo);
        $voci = [];

        foreach (BandiHtml::estraiLink($corpo, $urlFinale) as $link) {
            // Alcuni portali usano ancore mute ("leggi tutto"): il titolo della pagina salva il contesto.
            $titolo = mb_strlen($link['text']) > 8 ? $link['text'] : trim(($link['text'] ?: 'avviso') . ' — ' . $titoloPagina);
            $voci[] = [
                'title' => $titolo,
                'url' => $link['url'],
                'context' => $link['context'],
                'publishedAt' => null,
            ];
        }

        return $voci;
    }

    public function candidatiDaJson($corpo, array $fonte)
    {
        $dati = json_decode($corpo, true);
        if (!is_array($dati)) {
            throw new RuntimeException('risposta JSON non valida: ' . json_last_error_msg());
        }

        $righe = self::valoreAnnidato($dati, $fonte['jsonPath'] ?? null);
        if (!is_array($righe) || !isset($righe[0])) {
            throw new RuntimeException('jsonPath "' . ($fonte['jsonPath'] ?? '(radice)') . '" non punta a un elenco');
        }

        $campi = $fonte['campi'] ?? [];
        $voci = [];

        foreach ($righe as $riga) {
            $urlGrezzo = self::valoreAnnidato($riga, $campi['url'] ?? 'url');
            $url = !empty($fonte['urlTemplate']) && $urlGrezzo !== null
                ? str_replace('{id}', (string) $urlGrezzo, $fonte['urlTemplate'])
                : (string) $urlGrezzo;
            $pubblicato = self::valoreAnnidato($riga, $campi['data'] ?? 'data');

            $voci[] = [
                'title' => (string) self::valoreAnnidato($riga, $campi['titolo'] ?? 'titolo'),
                'url' => $url,
                'context' => (string) self::valoreAnnidato($riga, $campi['descrizione'] ?? 'descrizione'),
                'publishedAt' => $pubblicato ? BandiDates::parse($pubblicato) : null,
                'deadlineRaw' => self::valoreAnnidato($riga, $campi['scadenza'] ?? 'scadenza'),
            ];
        }

        return $voci;
    }

    private function voce2bando(array $voce, array $fonte, array $verdetto)
    {
        $testo = $voce['title'] . ' ' . ($voce['context'] ?? '');
        $daCampo = !empty($voce['deadlineRaw']) ? BandiDates::parse($voce['deadlineRaw']) : null;
        $scadenza = $daCampo
            ? ['deadline' => $daCampo, 'evidence' => 'campo scadenza della fonte']
            : BandiDates::extractDeadline($testo, $this->ora);

        $livello = $verdetto['livello'] !== 'sconosciuto'
            ? $verdetto['livello']
            : (($fonte['livello'] ?? 'misto') === 'misto' ? 'sconosciuto' : $fonte['livello']);

        $bando = [
            'titolo' => trim(preg_replace('/\s+/u', ' ', $voce['title'])),
            'url' => $voce['url'],
            'fonte' => $fonte['id'],
            'fonteNome' => $fonte['nome'] ?? $fonte['id'],
            'livello' => $livello,
            'categoria' => $verdetto['categoria'],
            'ente' => $verdetto['ente'],
            'regione' => $verdetto['regione'],
            'classiConcorso' => $verdetto['classiConcorso'],
            'ssd' => $verdetto['ssd'],
            'punteggio' => $verdetto['punteggio'],
            'pubblicatoIl' => $voce['publishedAt'] ?? null,
            'scadenza' => $scadenza['deadline'],
            'scadenzaEvidenza' => $scadenza['evidence'],
            'estratto' => mb_substr((string) ($voce['context'] ?? ''), 0, 400),
            'raccoltoIl' => $this->ora,
        ];

        $bando['id'] = bandi_impronta($bando);
        return $bando;
    }

    private function scaricaFonte(array $fonte, array $opzioni)
    {
        $risposta = $this->http->scarica($fonte['url'], [
            'useCache' => $opzioni['useCache'] ?? true,
            'accept' => ($fonte['tipo'] === 'json') ? 'application/json,*/*;q=0.8' : null,
        ]);

        if ($fonte['tipo'] === 'json') {
            $candidati = $this->candidatiDaJson($risposta['body'], $fonte);
        } elseif ($fonte['tipo'] === 'rss' || BandiFeed::sembraUnFeed($risposta['body'])) {
            $candidati = $this->candidatiDaFeed($risposta['body']);
        } else {
            $candidati = $this->candidatiDaHtml($risposta['body'], $risposta['url']);
        }

        return [$risposta, $candidati];
    }

    /**
     * @return array{fonte: string, nome: string, bandi: array, esaminati: int, errore: string|null}
     */
    public function raccogli(array $fonte, array $opzioni = [])
    {
        try {
            list(, $candidati) = $this->scaricaFonte($fonte, $opzioni);

            $bandi = [];
            $visti = [];

            foreach ($candidati as $voce) {
                if (empty($voce['url']) || empty($voce['title'])) {
                    continue;
                }
                $verdetto = BandiClassifier::classifica($voce, $this->soglia);
                if (!$verdetto['pertinente']) {
                    continue;
                }
                $bando = $this->voce2bando($voce, $fonte, $verdetto);
                if (isset($visti[$bando['id']])) {
                    continue;
                }
                $visti[$bando['id']] = true;
                $bandi[] = $bando;
            }

            usort($bandi, function ($a, $b) {
                return $b['punteggio'] <=> $a['punteggio'];
            });
            $bandi = array_slice($bandi, 0, $this->maxPerFonte);

            if (!empty($opzioni['approfondisci'])) {
                $bandi = $this->arricchisci($bandi, $opzioni);
            }

            return ['fonte' => $fonte['id'], 'nome' => $fonte['nome'], 'bandi' => $bandi, 'esaminati' => count($candidati), 'errore' => null];
        } catch (Exception $e) {
            return ['fonte' => $fonte['id'], 'nome' => $fonte['nome'], 'bandi' => [], 'esaminati' => 0, 'errore' => $e->getMessage()];
        }
    }

    /**
     * Per i bandi senza scadenza apre la pagina di dettaglio.
     * È la parte più costosa: di default poche pagine per fonte, così
     * l'aggregatore non diventa un crawler aggressivo (né supera il
     * max_execution_time degli hosting condivisi).
     */
    private function arricchisci(array $bandi, array $opzioni)
    {
        $massimo = (int) ($opzioni['maxDettagli'] ?? 5);
        $fatti = 0;

        foreach ($bandi as $indice => $bando) {
            if ($fatti >= $massimo) {
                break;
            }
            if ($bando['scadenza'] || preg_match('~\.(pdf|zip|doc|docx|xls|xlsx|p7m)(\?|$)~i', $bando['url'])) {
                continue;
            }

            try {
                $risposta = $this->http->scarica($bando['url'], ['useCache' => $opzioni['useCache'] ?? true]);
                $testo = mb_substr(BandiHtml::toText($risposta['body']), 0, 20000);

                $scadenza = BandiDates::extractDeadline($testo, $this->ora);
                if ($scadenza['deadline']) {
                    $bandi[$indice]['scadenza'] = $scadenza['deadline'];
                    $bandi[$indice]['scadenzaEvidenza'] = $scadenza['evidence'];
                }

                if (!$bando['ente'] || !$bando['regione'] || $bando['livello'] === 'sconosciuto') {
                    $verdetto = BandiClassifier::classifica(
                        ['title' => $bando['titolo'], 'context' => mb_substr($testo, 0, 3000)],
                        $this->soglia
                    );
                    $bandi[$indice]['ente'] = $bando['ente'] ?: $verdetto['ente'];
                    $bandi[$indice]['regione'] = $bando['regione'] ?: $verdetto['regione'];
                    if ($bando['livello'] === 'sconosciuto' && $verdetto['livello'] !== 'sconosciuto') {
                        $bandi[$indice]['livello'] = $verdetto['livello'];
                    }
                }
            } catch (Exception $e) {
                // La pagina di dettaglio è un extra: se non risponde si tiene il bando com'è.
            }

            $fatti++;
        }

        return $bandi;
    }

    /**
     * Raccoglie da tutte le fonti, una per volta.
     * Con 'budget' (secondi) si ferma prima di sforare il tempo massimo di
     * esecuzione dell'hosting: le fonti rimaste vengono elaborate al giro dopo.
     */
    public function raccogliTutte(array $fonti, array $opzioni = [])
    {
        $inizio = microtime(true);
        $budget = isset($opzioni['budget']) ? (float) $opzioni['budget'] : null;

        $risultati = [];
        $bandi = [];
        $saltate = [];

        foreach ($fonti as $fonte) {
            // La prima fonte parte sempre: altrimenti un budget stretto non
            // farebbe mai avanzare la raccolta.
            if ($budget !== null && $risultati && (microtime(true) - $inizio) > $budget) {
                $saltate[] = $fonte['id'];
                continue;
            }

            $risultato = $this->raccogli($fonte, $opzioni);
            $risultati[] = $risultato;
            $bandi = array_merge($bandi, $risultato['bandi']);

            if (isset($opzioni['onSource']) && is_callable($opzioni['onSource'])) {
                call_user_func($opzioni['onSource'], $risultato);
            }
        }

        return ['risultati' => $risultati, 'bandi' => $bandi, 'saltate' => $saltate];
    }

    /** Diagnostica una fonte: URL ancora valido? quante voci estrae? */
    public function diagnostica(array $fonte, array $opzioni = [])
    {
        $inizio = microtime(true);

        try {
            list($risposta, $candidati) = $this->scaricaFonte($fonte, ['useCache' => false] + $opzioni);

            $pertinenti = array_values(array_filter($candidati, function ($voce) {
                return !empty($voce['url']) && !empty($voce['title'])
                    && BandiClassifier::classifica($voce, $this->soglia)['pertinente'];
            }));

            return [
                'fonte' => $fonte['id'],
                'nome' => $fonte['nome'],
                'ok' => count($candidati) > 0,
                'status' => $risposta['status'],
                'voci' => count($candidati),
                'pertinenti' => count($pertinenti),
                'esempi' => array_map(function ($v) {
                    return mb_substr($v['title'], 0, 90);
                }, array_slice($pertinenti, 0, 3)),
                'ms' => (int) round((microtime(true) - $inizio) * 1000),
                'errore' => count($candidati) === 0
                    ? 'nessuna voce estratta: struttura della pagina cambiata o contenuto caricato via JavaScript'
                    : null,
            ];
        } catch (Exception $e) {
            return [
                'fonte' => $fonte['id'],
                'nome' => $fonte['nome'],
                'ok' => false,
                'status' => $e->getCode() ?: null,
                'voci' => 0,
                'pertinenti' => 0,
                'esempi' => [],
                'ms' => (int) round((microtime(true) - $inizio) * 1000),
                'errore' => $e->getMessage(),
            ];
        }
    }
}
