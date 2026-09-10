<?php
require_once __DIR__ . '/Robots.php';

class BandiHttpError extends Exception
{
    public $codice;

    public function __construct($messaggio, $codice = 'FETCH_FAILED', $status = 0)
    {
        parent::__construct($messaggio, (int) $status);
        $this->codice = $codice;
    }
}

/**
 * Download educato: rispetta robots.txt e il Crawl-delay, mantiene un
 * intervallo minimo fra richieste allo stesso host, usa le richieste
 * condizionali (ETag / Last-Modified) e ritenta con backoff.
 */
class BandiHttp
{
    private $userAgent;
    private $cartellaCache;
    private $intervalloMinimo;
    private $rispettaRobots;
    private $timeout;
    private $robotsPerOrigine = [];

    public function __construct(array $opzioni = [])
    {
        $this->userAgent = $opzioni['userAgent'] ?? 'BandiScuolaBot/1.0 (aggregatore bandi scuola/universita)';
        $this->cartellaCache = rtrim($opzioni['cacheDir'] ?? __DIR__ . '/../cache', '/');
        $this->intervalloMinimo = (float) ($opzioni['minInterval'] ?? 1.5);
        $this->rispettaRobots = $opzioni['respectRobots'] ?? true;
        $this->timeout = (int) ($opzioni['timeout'] ?? 20);

        if (!is_dir($this->cartellaCache)) {
            @mkdir($this->cartellaCache, 0775, true);
        }
    }

    private function fileCache($url, $suffisso = '.json')
    {
        return $this->cartellaCache . '/' . sha1($url) . $suffisso;
    }

    private function leggiCache($url)
    {
        $file = $this->fileCache($url);
        if (!is_file($file)) {
            return null;
        }
        $dati = json_decode((string) file_get_contents($file), true);
        return is_array($dati) ? $dati : null;
    }

    private function scriviCache($url, array $voce)
    {
        @file_put_contents($this->fileCache($url), json_encode($voce), LOCK_EX);
    }

    /** Attende quanto basta per non superare la frequenza consentita su quell'host. */
    private function attendiTurno($host, $intervallo)
    {
        $file = $this->cartellaCache . '/host-' . sha1($host) . '.txt';
        $ultima = is_file($file) ? (float) file_get_contents($file) : 0.0;
        $attesa = $ultima + $intervallo - microtime(true);

        if ($attesa > 0) {
            usleep((int) min($attesa, 10) * 1000000);
        }
        @file_put_contents($file, (string) microtime(true), LOCK_EX);
    }

    /** Esegue la richiesta con cURL, o con i wrapper HTTP se cURL non è disponibile. */
    private function richiesta($url, array $intestazioni)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_HTTPHEADER => $intestazioni,
                CURLOPT_HEADER => true,
                CURLOPT_ENCODING => '',
            ]);

            $risposta = curl_exec($ch);
            if ($risposta === false) {
                $errore = curl_error($ch);
                curl_close($ch);
                throw new BandiHttpError('errore di rete: ' . $errore, 'NETWORK');
            }

            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $dimensioneIntestazioni = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $urlFinale = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);

            return [
                'status' => $status,
                'headers' => $this->intestazioniDaTesto(substr($risposta, 0, $dimensioneIntestazioni)),
                'body' => substr($risposta, $dimensioneIntestazioni),
                'url' => $urlFinale ?: $url,
            ];
        }

        if (!ini_get('allow_url_fopen')) {
            throw new BandiHttpError('né cURL né allow_url_fopen sono disponibili sull\'hosting', 'NO_HTTP_CLIENT');
        }

        $contesto = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", array_merge(['User-Agent: ' . $this->userAgent], $intestazioni)),
            'timeout' => $this->timeout,
            'follow_location' => 1,
            'max_redirects' => 5,
            'ignore_errors' => true,
        ]]);

        $corpo = @file_get_contents($url, false, $contesto);
        if ($corpo === false) {
            throw new BandiHttpError('richiesta fallita verso ' . $url, 'NETWORK');
        }

        $intestazioniRisposta = isset($http_response_header) ? $http_response_header : [];
        $status = 0;
        foreach ($intestazioniRisposta as $riga) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $riga, $m)) {
                $status = (int) $m[1];
            }
        }

        return [
            'status' => $status,
            'headers' => $this->intestazioniDaLista($intestazioniRisposta),
            'body' => $corpo,
            'url' => $url,
        ];
    }

    private function intestazioniDaTesto($testo)
    {
        return $this->intestazioniDaLista(preg_split('/\r?\n/', (string) $testo));
    }

    private function intestazioniDaLista($righe)
    {
        $intestazioni = [];
        foreach ((array) $righe as $riga) {
            if (strpos($riga, ':') === false) {
                continue;
            }
            list($nome, $valore) = explode(':', $riga, 2);
            $intestazioni[strtolower(trim($nome))] = trim($valore);
        }
        return $intestazioni;
    }

    private function robotsPerUrl($url)
    {
        $parti = parse_url($url);
        $origine = $parti['scheme'] . '://' . $parti['host'] . (isset($parti['port']) ? ':' . $parti['port'] : '');

        if (array_key_exists($origine, $this->robotsPerOrigine)) {
            return [$origine, $this->robotsPerOrigine[$origine]];
        }

        $testo = '';
        try {
            $risposta = $this->richiesta($origine . '/robots.txt', ['Accept: text/plain']);
            // 404 o 5xx: nessuna regola nota, si procede (comportamento standard).
            if ($risposta['status'] >= 200 && $risposta['status'] < 300) {
                $testo = $risposta['body'];
            }
        } catch (Exception $e) {
            $testo = '';
        }

        $this->robotsPerOrigine[$origine] = $testo;
        return [$origine, $testo];
    }

    /**
     * @return array{status: int, body: string, url: string, notModified: bool}
     * @throws BandiHttpError
     */
    public function scarica($url, array $opzioni = [])
    {
        $parti = parse_url($url);
        if (!$parti || !isset($parti['scheme'], $parti['host']) || !in_array($parti['scheme'], ['http', 'https'], true)) {
            throw new BandiHttpError('URL non valido: ' . $url, 'BAD_URL');
        }

        $usaCache = $opzioni['useCache'] ?? true;
        $tentativi = (int) ($opzioni['retries'] ?? 2);
        $accept = $opzioni['accept'] ?? 'text/html,application/xhtml+xml,application/xml,application/rss+xml;q=0.9,*/*;q=0.8';
        $intervallo = $this->intervalloMinimo;

        if ($this->rispettaRobots) {
            list(, $robots) = $this->robotsPerUrl($url);
            if (!BandiRobots::isAllowed($robots, $url, $this->userAgent)) {
                throw new BandiHttpError('robots.txt vieta l\'accesso a ' . $url, 'ROBOTS_DISALLOWED');
            }
            $ritardo = BandiRobots::crawlDelay($robots, $this->userAgent);
            if ($ritardo) {
                $intervallo = max($intervallo, $ritardo);
            }
        }

        $cache = $usaCache ? $this->leggiCache($url) : null;
        $ultimoErrore = null;

        for ($tentativo = 0; $tentativo <= $tentativi; $tentativo++) {
            if ($tentativo > 0) {
                sleep(min(8, 1 << ($tentativo - 1)));
            }
            $this->attendiTurno($parti['host'], $intervallo);

            $intestazioni = ['Accept: ' . $accept, 'Accept-Language: it-IT,it;q=0.9'];
            if ($cache && !empty($cache['etag'])) {
                $intestazioni[] = 'If-None-Match: ' . $cache['etag'];
            }
            if ($cache && !empty($cache['lastModified'])) {
                $intestazioni[] = 'If-Modified-Since: ' . $cache['lastModified'];
            }

            try {
                $risposta = $this->richiesta($url, $intestazioni);
            } catch (BandiHttpError $e) {
                $ultimoErrore = $e;
                continue;
            }

            if ($risposta['status'] === 304 && $cache) {
                return ['status' => 304, 'body' => $cache['body'], 'url' => $url, 'notModified' => true];
            }

            if ($risposta['status'] === 429 || $risposta['status'] >= 500) {
                $ultimoErrore = new BandiHttpError('HTTP ' . $risposta['status'] . ' da ' . $url, 'HTTP_ERROR', $risposta['status']);
                continue;
            }

            if ($risposta['status'] < 200 || $risposta['status'] >= 400) {
                throw new BandiHttpError('HTTP ' . $risposta['status'] . ' da ' . $url, 'HTTP_ERROR', $risposta['status']);
            }

            $corpo = $this->convertiInUtf8($risposta['body'], $risposta['headers']['content-type'] ?? '');

            if ($usaCache) {
                $this->scriviCache($url, [
                    'body' => $corpo,
                    'etag' => $risposta['headers']['etag'] ?? null,
                    'lastModified' => $risposta['headers']['last-modified'] ?? null,
                    'fetchedAt' => gmdate('c'),
                ]);
            }

            return ['status' => $risposta['status'], 'body' => $corpo, 'url' => $risposta['url'], 'notModified' => false];
        }

        throw $ultimoErrore ?: new BandiHttpError('impossibile scaricare ' . $url, 'FETCH_FAILED');
    }

    /** Molti siti scolastici servono ancora ISO-8859-1: senza conversione le accentate saltano. */
    private function convertiInUtf8($corpo, $contentType)
    {
        $charset = null;
        if (preg_match('/charset=["\']?([a-z0-9_\-]+)/i', (string) $contentType, $m)) {
            $charset = strtolower($m[1]);
        } elseif (preg_match('/<meta[^>]+charset=["\']?([a-z0-9_\-]+)/i', substr($corpo, 0, 2000), $m)) {
            $charset = strtolower($m[1]);
        }

        if ($charset && !in_array($charset, ['utf-8', 'utf8'], true)) {
            $convertito = @mb_convert_encoding($corpo, 'UTF-8', $charset);
            if ($convertito !== false) {
                return $convertito;
            }
        }

        return mb_check_encoding($corpo, 'UTF-8') ? $corpo : mb_convert_encoding($corpo, 'UTF-8', 'ISO-8859-1');
    }
}
