<?php
/** Archivio SQLite in memoria: verifica il SQL usato anche su MySQL. */
function storeSqlite()
{
    $pdo = new PDO('sqlite::memory:');
    $store = new BandiStorePdo($pdo, 'bandi_items');
    $store->creaTabelle();
    return $store;
}

function bandoDiProva(array $sovrascritture = [])
{
    return array_merge([
        'titolo' => 'Concorso docenti',
        'url' => 'https://x.it/bando/1',
        'fonte' => 'a',
        'fonteNome' => 'Fonte A',
        'livello' => 'scuola',
        'categoria' => 'concorso',
        'ente' => null,
        'regione' => null,
        'classiConcorso' => [],
        'ssd' => [],
        'punteggio' => 6,
        'pubblicatoIl' => null,
        'scadenza' => null,
        'scadenzaEvidenza' => null,
        'estratto' => '',
        'raccoltoIl' => gmdate('c'),
    ], $sovrascritture);
}

test('la deduplica ignora i parametri di tracciamento e lo slash finale', function () {
    $a = ['titolo' => 'Concorso docenti', 'url' => 'https://x.it/b/1?utm_source=news'];
    $b = ['titolo' => 'Concorso docenti', 'url' => 'https://x.it/b/1/'];
    $c = ['titolo' => 'Concorso docenti', 'url' => 'https://x.it/b/2'];
    assertUguale(bandi_impronta($a), bandi_impronta($b));
    assertVero(bandi_impronta($a) !== bandi_impronta($c));
});

foreach (['MySQL/SQL' => 'sql', 'file JSON' => 'json'] as $etichetta => $tipo) {
    test("archivio {$etichetta}: distingue i nuovi dai già visti e unisce le fonti", function () use ($tipo) {
        $store = $tipo === 'sql'
            ? storeSqlite()
            : new BandiStoreJson(tempnam(sys_get_temp_dir(), 'bandi') . '.json');

        $primo = $store->upsertMany([bandoDiProva()]);
        assertUguale(1, count($primo['nuovi']));

        $secondo = $store->upsertMany([bandoDiProva([
            'fonte' => 'b',
            'punteggio' => 9,
            'scadenza' => '2099-05-01T00:00:00Z',
            'classiConcorso' => ['A-28'],
        ])]);

        assertUguale(0, count($secondo['nuovi']), 'lo stesso bando non deve risultare nuovo');
        assertUguale(1, count($secondo['aggiornati']));

        $tutti = $store->tutti();
        assertUguale(1, count($tutti));
        assertUguale(9, $tutti[0]['punteggio'], 'il punteggio più alto deve prevalere');
        assertUguale(['a', 'b'], $tutti[0]['fonti']);
        assertUguale(['A-28'], $tutti[0]['classiConcorso']);
        assertContiene('2099-05-01', $tutti[0]['scadenza']);

        $terzo = $store->upsertMany([bandoDiProva([
            'fonte' => 'b',
            'punteggio' => 9,
            'scadenza' => '2099-05-01T00:00:00Z',
            'classiConcorso' => ['A-28'],
        ])]);
        assertUguale(1, $terzo['invariati'], 'una raccolta identica non deve segnalare modifiche');
    });

    test("archivio {$etichetta}: filtri, statistiche e pulizia", function () use ($tipo) {
        $store = $tipo === 'sql'
            ? storeSqlite()
            : new BandiStoreJson(tempnam(sys_get_temp_dir(), 'bandi') . '.json');

        $store->upsertMany([
            bandoDiProva(['titolo' => 'Uni', 'url' => 'https://x.it/u', 'livello' => 'universita', 'categoria' => 'chiamata', 'scadenza' => '2099-01-01T00:00:00Z']),
            bandoDiProva(['titolo' => 'Scuola A-28', 'url' => 'https://x.it/s', 'livello' => 'scuola', 'categoria' => 'supplenza', 'scadenza' => '2099-01-01T00:00:00Z', 'classiConcorso' => ['A-28']]),
            bandoDiProva(['titolo' => 'Scaduto', 'url' => 'https://x.it/v', 'livello' => 'scuola', 'categoria' => 'concorso', 'scadenza' => '2020-01-01T00:00:00Z']),
        ]);

        assertUguale(1, count($store->cerca(['livello' => 'scuola', 'soloAperti' => true])), 'filtro livello + solo aperti');
        assertUguale(1, count($store->cerca(['classe' => 'A-28'])), 'filtro classe di concorso');
        assertUguale(3, count($store->cerca([])), 'senza filtri restituisce tutto');
        assertUguale(1, count($store->cerca(['testo' => 'Uni'])), 'ricerca testuale');
        assertUguale(0, count($store->cerca(['entro' => 30])), 'nessuna scadenza entro 30 giorni');

        $statistiche = $store->statistiche();
        assertUguale(3, $statistiche['totale']);
        assertUguale(2, $statistiche['aperti']);
        assertUguale(1, $statistiche['perLivello']['universita']);

        assertUguale(1, $store->prune(120), 'il bando scaduto da anni va rimosso');
        assertUguale(2, count($store->tutti()));
    });
}

test('l\'ordinamento mette in cima le scadenze più vicine', function () {
    $ordinati = bandi_ordina([
        ['titolo' => 'c', 'scadenza' => null, 'punteggio' => 20],
        ['titolo' => 'b', 'scadenza' => '2026-12-01T00:00:00Z', 'punteggio' => 1],
        ['titolo' => 'a', 'scadenza' => '2026-04-01T00:00:00Z', 'punteggio' => 1],
    ]);
    assertUguale(['a', 'b', 'c'], array_column($ordinati, 'titolo'));
});

test('le fonti predefinite sono valide e filtrabili per livello', function () {
    $fonti = BandiSources::carica(['soloAttive' => false]);
    assertVero(count($fonti) >= 5, 'servono almeno 5 fonti predefinite');
    foreach ($fonti as $fonte) {
        assertVero(in_array($fonte['tipo'], ['rss', 'html', 'json'], true), 'tipo non valido: ' . $fonte['id']);
        assertVero((bool) filter_var($fonte['url'], FILTER_VALIDATE_URL), 'URL non valido: ' . $fonte['id']);
    }
    foreach (BandiSources::carica(['livello' => 'scuola']) as $fonte) {
        assertVero(in_array($fonte['livello'], ['scuola', 'misto'], true));
    }
});

test('il digest raggruppa per livello e segnala i giorni residui', function () {
    $testo = BandiNotifier::digestTesto([
        bandoDiProva(['titolo' => 'Uni', 'url' => 'https://u.it', 'livello' => 'universita', 'scadenza' => '2099-01-01T00:00:00Z']),
        bandoDiProva(['titolo' => 'Scuola', 'url' => 'https://s.it', 'livello' => 'scuola', 'scadenza' => null]),
    ]);
    assertContiene('Università / AFAM (1)', $testo);
    assertContiene('Scuola (1)', $testo);
    assertContiene('scadenza non rilevata', $testo);
});

test('il digest HTML non lascia passare HTML dai dati esterni', function () {
    $html = BandiNotifier::digestHtml([
        bandoDiProva(['titolo' => '<script>alert(1)</script>', 'url' => 'https://x.it']),
    ]);
    assertVero(mb_strpos($html, '<script>alert') === false, 'il titolo malevolo non deve finire nell\'HTML');
    assertContiene('&lt;script&gt;', $html);
});
