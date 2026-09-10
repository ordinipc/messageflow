<?php
/** Avvia il portale di prova con il server integrato di PHP. */
function avviaPortale()
{
    $porta = random_int(8100, 8999);
    $comando = sprintf(
        '%s -S 127.0.0.1:%d -t %s %s',
        escapeshellarg(PHP_BINARY),
        $porta,
        escapeshellarg(dirname(__DIR__)),
        escapeshellarg(__DIR__ . '/server-di-prova.php')
    );

    $processo = proc_open($comando, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipe);
    $base = 'http://127.0.0.1:' . $porta;

    for ($tentativo = 0; $tentativo < 50; $tentativo++) {
        usleep(100000);
        $socket = @fsockopen('127.0.0.1', $porta, $errno, $errstr, 0.2);
        if ($socket) {
            fclose($socket);
            return [$processo, $base];
        }
    }

    throw new Exception('il server di prova non si è avviato');
}

function fermaPortale($processo)
{
    if (is_resource($processo)) {
        proc_terminate($processo);
        proc_close($processo);
    }
}

function collectorDiProva()
{
    $cache = sys_get_temp_dir() . '/bandi-test-' . bin2hex(random_bytes(4));
    $http = new BandiHttp([
        'userAgent' => 'BandiScuolaBot/1.0 (test)',
        'cacheDir' => $cache,
        'minInterval' => 0,
        'respectRobots' => true,
        'timeout' => 5,
    ]);
    return new BandiCollector($http, ['ora' => '2026-03-15T00:00:00Z']);
}

test('raccolta completa dal portale di prova: filtra, deduplica e archivia', function () {
    list($processo, $base) = avviaPortale();
    try {
        $collector = collectorDiProva();
        $fonti = [
            ['id' => 'feed-locale', 'nome' => 'Feed locale', 'tipo' => 'rss', 'url' => $base . '/rss', 'livello' => 'misto'],
            ['id' => 'albo-locale', 'nome' => 'Albo locale', 'tipo' => 'html', 'url' => $base . '/albo/', 'livello' => 'scuola'],
        ];

        $esito = $collector->raccogliTutte($fonti, ['useCache' => false]);

        foreach ($esito['risultati'] as $risultato) {
            assertUguale(null, $risultato['errore'], 'fonte ' . $risultato['fonte']);
        }
        assertVero(count($esito['bandi']) >= 3, 'attesi almeno 3 bandi, trovati ' . count($esito['bandi']));

        $titoli = implode(' | ', array_column($esito['bandi'], 'titolo'));
        assertVero(stripos($titoli, 'collaboratore scolastico') === false, 'gli avvisi ATA non devono passare');
        assertVero(stripos($titoli, 'fornitura') === false, 'gli appalti non devono passare');
        assertContiene('ricercatore a tempo determinato', $titoli);
        assertContiene('messa a disposizione', $titoli);

        $store = new BandiStoreJson(tempnam(sys_get_temp_dir(), 'bandi') . '.json');
        $primo = $store->upsertMany($esito['bandi']);
        assertUguale(count($esito['bandi']), count($primo['nuovi']));

        // Seconda esecuzione sugli stessi dati: nessun bando nuovo.
        $secondo = $store->upsertMany($esito['bandi']);
        assertUguale(0, count($secondo['nuovi']));

        $store->salva();
        assertVero($store->aggiornatoIl() !== null);
    } finally {
        fermaPortale($processo);
    }
});

test('i dati del feed finiscono nei campi giusti', function () {
    list($processo, $base) = avviaPortale();
    try {
        $collector = collectorDiProva();
        $esito = $collector->raccogli(
            ['id' => 'feed', 'nome' => 'Feed', 'tipo' => 'rss', 'url' => $base . '/rss', 'livello' => 'misto'],
            ['useCache' => false]
        );

        $ricercatore = null;
        foreach ($esito['bandi'] as $bando) {
            if (stripos($bando['titolo'], 'ricercatore') !== false) {
                $ricercatore = $bando;
            }
        }

        assertVero($ricercatore !== null, 'bando del ricercatore non trovato');
        assertUguale('2026-04-30T00:00:00Z', $ricercatore['scadenza']);
        assertUguale('Università degli Studi di Bologna', $ricercatore['ente']);
        assertUguale('universita', $ricercatore['livello']);
        assertUguale('2026-03-10T08:00:00Z', $ricercatore['pubblicatoIl']);
    } finally {
        fermaPortale($processo);
    }
});

test('l\'approfondimento recupera scadenza ed ente dalla pagina di dettaglio', function () {
    list($processo, $base) = avviaPortale();
    try {
        $collector = collectorDiProva();
        // In questo elenco i link non riportano date: la scadenza è solo nel dettaglio.
        $fonte = ['id' => 'albo-scarno', 'nome' => 'Albo senza date', 'tipo' => 'html', 'url' => $base . '/albo-senza-date/', 'livello' => 'scuola'];

        $senza = $collector->raccogli($fonte, ['useCache' => false]);
        assertUguale(1, count($senza['bandi']));
        assertUguale(null, $senza['bandi'][0]['scadenza'], 'senza approfondimento la scadenza non è nota');

        $con = $collector->raccogli($fonte, ['useCache' => false, 'approfondisci' => true, 'maxDettagli' => 3]);
        assertUguale('2026-09-20T12:00:00Z', $con['bandi'][0]['scadenza']);
        assertUguale('lombardia', $con['bandi'][0]['regione']);
        assertContiene('Istituto Comprensivo Verdi', $con['bandi'][0]['ente']);
    } finally {
        fermaPortale($processo);
    }
});

test('robots.txt viene rispettato', function () {
    list($processo, $base) = avviaPortale();
    try {
        $esito = collectorDiProva()->raccogli(
            ['id' => 'riservata', 'nome' => 'Area riservata', 'tipo' => 'html', 'url' => $base . '/riservato/bandi', 'livello' => 'misto'],
            ['useCache' => false]
        );
        assertContiene('robots.txt', $esito['errore']);
        assertUguale(0, count($esito['bandi']));
    } finally {
        fermaPortale($processo);
    }
});

test('le pagine in ISO-8859-1 vengono convertite in UTF-8', function () {
    list($processo, $base) = avviaPortale();
    try {
        $esito = collectorDiProva()->raccogli(
            ['id' => 'latin1', 'nome' => 'Pagina latin1', 'tipo' => 'html', 'url' => $base . '/latin1', 'livello' => 'universita'],
            ['useCache' => false]
        );
        assertUguale(1, count($esito['bandi']));
        assertContiene('Università', $esito['bandi'][0]['titolo']);
    } finally {
        fermaPortale($processo);
    }
});

test('la diagnostica segnala le fonti irraggiungibili senza bloccare le altre', function () {
    list($processo, $base) = avviaPortale();
    try {
        $collector = collectorDiProva();

        $buona = $collector->diagnostica(['id' => 'ok', 'nome' => 'Feed', 'tipo' => 'rss', 'url' => $base . '/rss']);
        assertVero($buona['ok']);
        assertUguale(3, $buona['voci']);
        assertVero($buona['pertinenti'] >= 2);

        $rotta = $collector->diagnostica(['id' => 'ko', 'nome' => 'Assente', 'tipo' => 'html', 'url' => $base . '/inesistente']);
        assertVero(!$rotta['ok']);
        assertContiene('404', $rotta['errore']);
    } finally {
        fermaPortale($processo);
    }
});

test('il budget di tempo rimanda le fonti non elaborate al giro successivo', function () {
    list($processo, $base) = avviaPortale();
    try {
        $collector = collectorDiProva();
        $fonti = [
            ['id' => 'prima', 'nome' => 'Prima', 'tipo' => 'rss', 'url' => $base . '/rss', 'livello' => 'misto'],
            ['id' => 'seconda', 'nome' => 'Seconda', 'tipo' => 'html', 'url' => $base . '/albo/', 'livello' => 'scuola'],
        ];

        // Budget a zero: la prima fonte parte comunque, le altre vengono rimandate.
        $esito = $collector->raccogliTutte($fonti, ['useCache' => false, 'budget' => 0]);
        assertUguale(1, count($esito['risultati']));
        assertUguale(['seconda'], $esito['saltate']);
    } finally {
        fermaPortale($processo);
    }
});
