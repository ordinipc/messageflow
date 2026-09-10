<?php
/**
 * Archivio dei bandi. Due implementazioni con la stessa interfaccia:
 * MySQL (consigliata) e file JSON (per hosting senza database).
 */

/** Chiave di deduplica: stesso URL normalizzato + stesso titolo. */
function bandi_impronta(array $bando)
{
    $url = (string) ($bando['url'] ?? '');
    $parti = parse_url($url);

    if ($parti && isset($parti['scheme'], $parti['host'])) {
        $query = '';
        if (isset($parti['query'])) {
            parse_str($parti['query'], $parametri);
            foreach (array_keys($parametri) as $chiave) {
                // I parametri di tracciamento non identificano il bando.
                if (preg_match('/^(utm_|fbclid|gclid|_ga)/i', $chiave)) {
                    unset($parametri[$chiave]);
                }
            }
            $query = $parametri ? '?' . http_build_query($parametri) : '';
        }
        $percorso = rtrim($parti['path'] ?? '', '/');
        $url = $parti['scheme'] . '://' . $parti['host'] . (isset($parti['port']) ? ':' . $parti['port'] : '') . $percorso . $query;
    }

    $titolo = mb_strtolower((string) ($bando['titolo'] ?? ''), 'UTF-8');
    $titolo = trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N} ]/u', ' ', $titolo)));

    return substr(sha1($url . '|' . $titolo), 0, 16);
}

/** Applica i filtri di ricerca a una lista di bandi già caricati. */
function bandi_filtra(array $bandi, array $filtri)
{
    $ora = time();

    return array_values(array_filter($bandi, function ($bando) use ($filtri, $ora) {
        if (!empty($filtri['livello']) && $filtri['livello'] !== 'tutti' && $bando['livello'] !== $filtri['livello']) {
            return false;
        }
        if (!empty($filtri['categoria']) && $filtri['categoria'] !== 'tutte' && $bando['categoria'] !== $filtri['categoria']) {
            return false;
        }
        if (!empty($filtri['regione']) && $bando['regione'] !== $filtri['regione']) {
            return false;
        }
        if (!empty($filtri['classe']) && !in_array(strtoupper($filtri['classe']), (array) $bando['classiConcorso'], true)) {
            return false;
        }
        if (isset($filtri['minPunteggio']) && (int) $bando['punteggio'] < (int) $filtri['minPunteggio']) {
            return false;
        }
        if (!empty($filtri['soloAperti']) && $bando['scadenza'] && strtotime($bando['scadenza']) < $ora) {
            return false;
        }
        if (isset($filtri['entro']) && $filtri['entro'] !== '' && $filtri['entro'] !== null) {
            if (!$bando['scadenza']) {
                return false;
            }
            $giorni = (strtotime($bando['scadenza']) - $ora) / 86400;
            if ($giorni < 0 || $giorni > (float) $filtri['entro']) {
                return false;
            }
        }
        if (!empty($filtri['testo'])) {
            $ago = mb_strtolower($filtri['testo'], 'UTF-8');
            $pagliaio = mb_strtolower($bando['titolo'] . ' ' . $bando['ente'] . ' ' . $bando['estratto'], 'UTF-8');
            if (mb_strpos($pagliaio, $ago) === false) {
                return false;
            }
        }
        return true;
    }));
}

/** Ordina per scadenza più vicina, poi per punteggio. */
function bandi_ordina(array $bandi)
{
    usort($bandi, function ($a, $b) {
        $sa = $a['scadenza'] ? strtotime($a['scadenza']) : PHP_INT_MAX;
        $sb = $b['scadenza'] ? strtotime($b['scadenza']) : PHP_INT_MAX;
        if ($sa !== $sb) {
            return $sa <=> $sb;
        }
        return (int) $b['punteggio'] <=> (int) $a['punteggio'];
    });
    return $bandi;
}

interface BandiStoreInterface
{
    public function upsertMany(array $bandi);
    public function cerca(array $filtri = [], $limite = 100);
    public function tutti();
    public function statistiche();
    public function prune($giorni = 120);
    public function aggiornatoIl();
}

/** Archivio su MySQL (o qualsiasi database raggiungibile via PDO). */
class BandiStorePdo implements BandiStoreInterface
{
    private $pdo;
    private $tabella;

    public function __construct(PDO $pdo, $tabella = 'bandi_items')
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->tabella = preg_replace('/[^a-z0-9_]/i', '', $tabella);
    }

    /** Crea le tabelle. SQL portabile: stessa istruzione su MySQL e SQLite. */
    public function creaTabelle()
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $coda = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->tabella} (
            id VARCHAR(32) NOT NULL PRIMARY KEY,
            titolo VARCHAR(500) NOT NULL,
            url VARCHAR(1000) NOT NULL,
            fonte VARCHAR(100) NOT NULL,
            fonte_nome VARCHAR(200) NULL,
            livello VARCHAR(20) NULL,
            categoria VARCHAR(30) NULL,
            ente VARCHAR(255) NULL,
            regione VARCHAR(60) NULL,
            classi_concorso VARCHAR(255) NULL,
            ssd VARCHAR(255) NULL,
            punteggio INT NOT NULL DEFAULT 0,
            pubblicato_il DATETIME NULL,
            scadenza DATETIME NULL,
            scadenza_evidenza VARCHAR(255) NULL,
            estratto TEXT NULL,
            fonti VARCHAR(255) NULL,
            raccolto_il DATETIME NULL,
            ultimo_avvistamento DATETIME NULL
        )" . $coda);

        foreach (['scadenza', 'livello', 'categoria', 'fonte'] as $colonna) {
            try {
                $this->pdo->exec("CREATE INDEX idx_{$this->tabella}_{$colonna} ON {$this->tabella} ({$colonna})");
            } catch (PDOException $e) {
                // L'indice esiste già: MySQL non supporta CREATE INDEX IF NOT EXISTS.
            }
        }
    }

    private static function versoDb($isoDate)
    {
        if (!$isoDate) {
            return null;
        }
        $timestamp = strtotime($isoDate);
        return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
    }

    private static function daDb($valore)
    {
        if (!$valore) {
            return null;
        }
        $timestamp = strtotime($valore . ' UTC');
        return $timestamp === false ? null : gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    private static function riga2bando(array $riga)
    {
        return [
            'id' => $riga['id'],
            'titolo' => $riga['titolo'],
            'url' => $riga['url'],
            'fonte' => $riga['fonte'],
            'fonteNome' => $riga['fonte_nome'],
            'livello' => $riga['livello'],
            'categoria' => $riga['categoria'],
            'ente' => $riga['ente'],
            'regione' => $riga['regione'],
            'classiConcorso' => $riga['classi_concorso'] ? explode(',', $riga['classi_concorso']) : [],
            'ssd' => $riga['ssd'] ? explode(',', $riga['ssd']) : [],
            'punteggio' => (int) $riga['punteggio'],
            'pubblicatoIl' => self::daDb($riga['pubblicato_il']),
            'scadenza' => self::daDb($riga['scadenza']),
            'scadenzaEvidenza' => $riga['scadenza_evidenza'],
            'estratto' => (string) $riga['estratto'],
            'fonti' => $riga['fonti'] ? explode(',', $riga['fonti']) : [],
            'raccoltoIl' => self::daDb($riga['raccolto_il']),
        ];
    }

    public function upsertMany(array $bandi)
    {
        $nuovi = [];
        $aggiornati = [];
        $invariati = 0;

        $leggi = $this->pdo->prepare("SELECT * FROM {$this->tabella} WHERE id = ?");
        $inserisci = $this->pdo->prepare("INSERT INTO {$this->tabella}
            (id, titolo, url, fonte, fonte_nome, livello, categoria, ente, regione, classi_concorso, ssd,
             punteggio, pubblicato_il, scadenza, scadenza_evidenza, estratto, fonti, raccolto_il, ultimo_avvistamento)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $aggiorna = $this->pdo->prepare("UPDATE {$this->tabella} SET
            scadenza = ?, scadenza_evidenza = ?, punteggio = ?, ente = ?, regione = ?,
            classi_concorso = ?, ssd = ?, fonti = ?, ultimo_avvistamento = ?
            WHERE id = ?");

        $adesso = gmdate('Y-m-d H:i:s');

        foreach ($bandi as $bando) {
            $id = $bando['id'] ?? bandi_impronta($bando);
            $leggi->execute([$id]);
            $esistente = $leggi->fetch(PDO::FETCH_ASSOC);

            if (!$esistente) {
                $inserisci->execute([
                    $id,
                    mb_substr((string) $bando['titolo'], 0, 500),
                    mb_substr((string) $bando['url'], 0, 1000),
                    $bando['fonte'],
                    $bando['fonteNome'] ?? null,
                    $bando['livello'] ?? null,
                    $bando['categoria'] ?? null,
                    $bando['ente'] ? mb_substr($bando['ente'], 0, 255) : null,
                    $bando['regione'] ?? null,
                    implode(',', (array) ($bando['classiConcorso'] ?? [])),
                    implode(',', (array) ($bando['ssd'] ?? [])),
                    (int) ($bando['punteggio'] ?? 0),
                    self::versoDb($bando['pubblicatoIl'] ?? null),
                    self::versoDb($bando['scadenza'] ?? null),
                    $bando['scadenzaEvidenza'] ? mb_substr($bando['scadenzaEvidenza'], 0, 255) : null,
                    mb_substr((string) ($bando['estratto'] ?? ''), 0, 1000),
                    $bando['fonte'],
                    $adesso,
                    $adesso,
                ]);
                $bando['id'] = $id;
                $nuovi[] = $bando;
                continue;
            }

            $classi = array_values(array_unique(array_merge(
                $esistente['classi_concorso'] ? explode(',', $esistente['classi_concorso']) : [],
                (array) ($bando['classiConcorso'] ?? [])
            )));
            $ssd = array_values(array_unique(array_merge(
                $esistente['ssd'] ? explode(',', $esistente['ssd']) : [],
                (array) ($bando['ssd'] ?? [])
            )));
            $fonti = array_values(array_unique(array_merge(
                $esistente['fonti'] ? explode(',', $esistente['fonti']) : [],
                [$bando['fonte']]
            )));

            $scadenza = self::versoDb($bando['scadenza'] ?? null) ?: $esistente['scadenza'];
            $punteggio = max((int) $esistente['punteggio'], (int) ($bando['punteggio'] ?? 0));
            $ente = $esistente['ente'] ?: ($bando['ente'] ?? null);
            $regione = $esistente['regione'] ?: ($bando['regione'] ?? null);

            $cambiato = $scadenza !== $esistente['scadenza']
                || $punteggio !== (int) $esistente['punteggio']
                || $ente !== $esistente['ente']
                || $regione !== $esistente['regione']
                || implode(',', $classi) !== (string) $esistente['classi_concorso']
                || implode(',', $ssd) !== (string) $esistente['ssd']
                || implode(',', $fonti) !== (string) $esistente['fonti'];

            $aggiorna->execute([
                $scadenza,
                $bando['scadenzaEvidenza'] ? mb_substr($bando['scadenzaEvidenza'], 0, 255) : $esistente['scadenza_evidenza'],
                $punteggio,
                $ente ? mb_substr($ente, 0, 255) : null,
                $regione,
                implode(',', $classi),
                implode(',', $ssd),
                implode(',', $fonti),
                $adesso,
                $id,
            ]);

            if ($cambiato) {
                $leggi->execute([$id]);
                $aggiornati[] = self::riga2bando($leggi->fetch(PDO::FETCH_ASSOC));
            } else {
                $invariati++;
            }
        }

        return ['nuovi' => $nuovi, 'aggiornati' => $aggiornati, 'invariati' => $invariati];
    }

    public function cerca(array $filtri = [], $limite = 100)
    {
        $dove = [];
        $valori = [];

        if (!empty($filtri['livello']) && $filtri['livello'] !== 'tutti') {
            $dove[] = 'livello = ?';
            $valori[] = $filtri['livello'];
        }
        if (!empty($filtri['categoria']) && $filtri['categoria'] !== 'tutte') {
            $dove[] = 'categoria = ?';
            $valori[] = $filtri['categoria'];
        }
        if (!empty($filtri['regione'])) {
            $dove[] = 'regione = ?';
            $valori[] = $filtri['regione'];
        }
        if (!empty($filtri['classe'])) {
            $dove[] = 'classi_concorso LIKE ?';
            $valori[] = '%' . strtoupper($filtri['classe']) . '%';
        }
        if (!empty($filtri['testo'])) {
            $dove[] = '(titolo LIKE ? OR ente LIKE ? OR estratto LIKE ?)';
            $ago = '%' . $filtri['testo'] . '%';
            array_push($valori, $ago, $ago, $ago);
        }
        if (!empty($filtri['soloAperti'])) {
            $dove[] = '(scadenza IS NULL OR scadenza >= ?)';
            $valori[] = gmdate('Y-m-d H:i:s');
        }
        if (isset($filtri['entro']) && $filtri['entro'] !== '' && $filtri['entro'] !== null) {
            $dove[] = 'scadenza IS NOT NULL AND scadenza >= ? AND scadenza <= ?';
            $valori[] = gmdate('Y-m-d H:i:s');
            $valori[] = gmdate('Y-m-d H:i:s', time() + (int) $filtri['entro'] * 86400);
        }

        $sql = "SELECT * FROM {$this->tabella}";
        if ($dove) {
            $sql .= ' WHERE ' . implode(' AND ', $dove);
        }
        // Le scadenze note vengono prima, ordinate dalla più vicina.
        $sql .= ' ORDER BY (scadenza IS NULL), scadenza ASC, punteggio DESC LIMIT ' . max(1, min((int) $limite, 1000));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($valori);

        return array_map([self::class, 'riga2bando'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function tutti()
    {
        return $this->cerca([], 1000);
    }

    public function statistiche()
    {
        $totale = (int) $this->pdo->query("SELECT COUNT(*) FROM {$this->tabella}")->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->tabella} WHERE scadenza IS NULL OR scadenza >= ?");
        $stmt->execute([gmdate('Y-m-d H:i:s')]);

        $conteggio = function ($colonna) {
            $righe = $this->pdo->query("SELECT COALESCE({$colonna}, 'n.d.') AS chiave, COUNT(*) AS quanti
                FROM {$this->tabella} GROUP BY COALESCE({$colonna}, 'n.d.')")->fetchAll(PDO::FETCH_ASSOC);
            $risultato = [];
            foreach ($righe as $riga) {
                $risultato[$riga['chiave'] ?: 'n.d.'] = (int) $riga['quanti'];
            }
            return $risultato;
        };

        return [
            'totale' => $totale,
            'aperti' => (int) $stmt->fetchColumn(),
            'perLivello' => $conteggio('livello'),
            'perCategoria' => $conteggio('categoria'),
            'perRegione' => $conteggio('regione'),
        ];
    }

    public function prune($giorni = 120)
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tabella} WHERE scadenza IS NOT NULL AND scadenza < ?");
        $stmt->execute([gmdate('Y-m-d H:i:s', time() - (int) $giorni * 86400)]);
        return $stmt->rowCount();
    }

    public function aggiornatoIl()
    {
        $valore = $this->pdo->query("SELECT MAX(ultimo_avvistamento) FROM {$this->tabella}")->fetchColumn();
        return self::daDb($valore);
    }
}

/** Archivio su file JSON, per hosting senza database. */
class BandiStoreJson implements BandiStoreInterface
{
    private $file;
    private $dati = ['version' => 1, 'aggiornatoIl' => null, 'bandi' => []];

    public function __construct($file)
    {
        $this->file = $file;
        if (is_file($file)) {
            $letto = json_decode((string) file_get_contents($file), true);
            if (is_array($letto) && isset($letto['bandi'])) {
                $this->dati = $letto;
            }
        }
    }

    public function salva()
    {
        $this->dati['aggiornatoIl'] = gmdate('Y-m-d\TH:i:s\Z');
        $cartella = dirname($this->file);
        if (!is_dir($cartella)) {
            @mkdir($cartella, 0775, true);
        }
        // Scrittura atomica: niente file troncati se il processo viene interrotto.
        $temporaneo = $this->file . '.tmp';
        file_put_contents($temporaneo, json_encode($this->dati, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        rename($temporaneo, $this->file);
    }

    public function upsertMany(array $bandi)
    {
        $nuovi = [];
        $aggiornati = [];
        $invariati = 0;
        $adesso = gmdate('Y-m-d\TH:i:s\Z');

        foreach ($bandi as $bando) {
            $id = $bando['id'] ?? bandi_impronta($bando);
            $bando['id'] = $id;

            if (!isset($this->dati['bandi'][$id])) {
                $bando['fonti'] = array_values(array_filter([$bando['fonte'] ?? null]));
                $bando['ultimoAvvistamento'] = $adesso;
                $this->dati['bandi'][$id] = $bando;
                $nuovi[] = $bando;
                continue;
            }

            $esistente = $this->dati['bandi'][$id];
            $unito = $esistente;
            $unito['scadenza'] = $bando['scadenza'] ?: ($esistente['scadenza'] ?? null);
            $unito['scadenzaEvidenza'] = $bando['scadenzaEvidenza'] ?: ($esistente['scadenzaEvidenza'] ?? null);
            $unito['punteggio'] = max((int) ($esistente['punteggio'] ?? 0), (int) ($bando['punteggio'] ?? 0));
            $unito['ente'] = $esistente['ente'] ?: ($bando['ente'] ?? null);
            $unito['regione'] = $esistente['regione'] ?: ($bando['regione'] ?? null);
            $unito['classiConcorso'] = array_values(array_unique(array_merge((array) ($esistente['classiConcorso'] ?? []), (array) ($bando['classiConcorso'] ?? []))));
            $unito['ssd'] = array_values(array_unique(array_merge((array) ($esistente['ssd'] ?? []), (array) ($bando['ssd'] ?? []))));
            $unito['fonti'] = array_values(array_unique(array_merge((array) ($esistente['fonti'] ?? []), array_filter([$bando['fonte'] ?? null]))));
            $unito['ultimoAvvistamento'] = $adesso;

            $cambiato = json_encode($unito) !== json_encode($esistente);
            $this->dati['bandi'][$id] = $unito;

            if ($cambiato) {
                $aggiornati[] = $unito;
            } else {
                $invariati++;
            }
        }

        return ['nuovi' => $nuovi, 'aggiornati' => $aggiornati, 'invariati' => $invariati];
    }

    public function cerca(array $filtri = [], $limite = 100)
    {
        return array_slice(bandi_ordina(bandi_filtra($this->tutti(), $filtri)), 0, max(1, (int) $limite));
    }

    public function tutti()
    {
        return array_map(function ($bando) {
            $bando += ['classiConcorso' => [], 'ssd' => [], 'ente' => null, 'regione' => null,
                       'estratto' => '', 'punteggio' => 0, 'scadenza' => null, 'fonti' => []];
            return $bando;
        }, array_values($this->dati['bandi']));
    }

    public function statistiche()
    {
        $bandi = $this->tutti();
        $conteggio = function ($campo) use ($bandi) {
            $risultato = [];
            foreach ($bandi as $bando) {
                $chiave = $bando[$campo] ?: 'n.d.';
                $risultato[$chiave] = ($risultato[$chiave] ?? 0) + 1;
            }
            return $risultato;
        };

        $aperti = array_filter($bandi, function ($b) {
            return !$b['scadenza'] || strtotime($b['scadenza']) >= time();
        });

        return [
            'totale' => count($bandi),
            'aperti' => count($aperti),
            'perLivello' => $conteggio('livello'),
            'perCategoria' => $conteggio('categoria'),
            'perRegione' => $conteggio('regione'),
        ];
    }

    public function prune($giorni = 120)
    {
        $limite = time() - (int) $giorni * 86400;
        $rimossi = 0;

        foreach ($this->dati['bandi'] as $id => $bando) {
            $riferimento = strtotime($bando['scadenza'] ?? $bando['pubblicatoIl'] ?? $bando['raccoltoIl'] ?? '');
            if ($riferimento && $riferimento < $limite) {
                unset($this->dati['bandi'][$id]);
                $rimossi++;
            }
        }

        return $rimossi;
    }

    public function aggiornatoIl()
    {
        return $this->dati['aggiornatoIl'];
    }
}
