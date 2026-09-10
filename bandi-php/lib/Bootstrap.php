<?php
require_once __DIR__ . '/Http.php';
require_once __DIR__ . '/Store.php';
require_once __DIR__ . '/Sources.php';
require_once __DIR__ . '/Collector.php';
require_once __DIR__ . '/Notifier.php';

/** Carica config.php (o si ferma spiegando cosa manca). */
function bandi_config()
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $file = __DIR__ . '/../config.php';
    if (!is_file($file)) {
        throw new RuntimeException(
            'Manca config.php. Copia config.example.php in config.php e inserisci i tuoi dati.'
        );
    }

    $config = require $file;
    if (!is_array($config)) {
        throw new RuntimeException('config.php deve restituire un array di configurazione.');
    }

    return $config;
}

/** Connessione PDO al database, oppure null se non configurato. */
function bandi_pdo()
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }

    $db = bandi_config()['db'] ?? [];
    if (empty($db['nome'])) {
        return $pdo = null;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['porta'] ?? 3306, $db['nome']);

    $pdo = new PDO($dsn, $db['utente'] ?? '', $db['password'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

/** Archivio: MySQL se configurato, altrimenti file JSON. */
function bandi_store()
{
    static $store = null;
    if ($store !== null) {
        return $store;
    }

    $config = bandi_config();
    $pdo = bandi_pdo();

    if ($pdo) {
        $store = new BandiStorePdo($pdo, $config['db']['tabella'] ?? 'bandi_items');
    } else {
        $store = new BandiStoreJson($config['archivio_json'] ?? __DIR__ . '/../data/bandi.json');
    }

    return $store;
}

/** Salva l'archivio JSON (con MySQL il salvataggio è già avvenuto). */
function bandi_salva_store($store)
{
    if ($store instanceof BandiStoreJson) {
        $store->salva();
    }
}

function bandi_http()
{
    $config = bandi_config();
    return new BandiHttp([
        'userAgent' => $config['user_agent'] ?? null,
        'cacheDir' => $config['cartella_cache'] ?? __DIR__ . '/../cache',
        'minInterval' => $config['intervallo_minimo'] ?? 1.5,
        'respectRobots' => $config['rispetta_robots'] ?? true,
        'timeout' => $config['timeout'] ?? 20,
    ]);
}

function bandi_collector()
{
    $config = bandi_config();
    return new BandiCollector(bandi_http(), [
        'soglia' => $config['soglia'] ?? 5,
        'maxPerFonte' => $config['max_per_fonte'] ?? 60,
    ]);
}

/** Confronto del token a tempo costante (niente scorciatoie sui confronti di segreti). */
function bandi_token_valido($fornito)
{
    $atteso = (string) (bandi_config()['token'] ?? '');
    if ($atteso === '') {
        return false;
    }
    return is_string($fornito) && hash_equals($atteso, $fornito);
}

/** Esegue una raccolta completa e restituisce il resoconto. */
function bandi_esegui_raccolta(array $opzioni = [])
{
    $config = bandi_config();
    $fonti = BandiSources::carica([
        'ids' => $opzioni['fonti'] ?? null,
        'livello' => $opzioni['livello'] ?? null,
    ]);

    $collector = bandi_collector();
    $esito = $collector->raccogliTutte($fonti, [
        'approfondisci' => $opzioni['approfondisci'] ?? ($config['approfondisci'] ?? true),
        'maxDettagli' => $config['max_dettagli'] ?? 5,
        'budget' => $opzioni['budget'] ?? ($config['budget_secondi'] ?? 20),
        'useCache' => $opzioni['useCache'] ?? true,
        'onSource' => $opzioni['onSource'] ?? null,
    ]);

    $store = bandi_store();
    $salvataggio = $store->upsertMany($esito['bandi']);
    if (!empty($opzioni['pulisci'])) {
        $store->prune((int) $opzioni['pulisci']);
    }
    bandi_salva_store($store);

    return [
        'nuovi' => $salvataggio['nuovi'],
        'aggiornati' => $salvataggio['aggiornati'],
        'invariati' => $salvataggio['invariati'],
        'risultati' => $esito['risultati'],
        'saltate' => $esito['saltate'],
    ];
}
