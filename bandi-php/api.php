<?php
/**
 * API JSON in sola lettura.
 *
 *   api.php?livello=scuola&entro=30&cerca=A-28
 *   api.php?azione=statistiche
 *   api.php?azione=fonti
 */
require_once __DIR__ . '/lib/Bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

try {
    $store = bandi_store();
    $azione = $_GET['azione'] ?? 'elenco';

    if ($azione === 'statistiche') {
        echo json_encode($store->statistiche(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($azione === 'fonti') {
        $fonti = array_map(function ($f) {
            return [
                'id' => $f['id'], 'nome' => $f['nome'], 'tipo' => $f['tipo'],
                'livello' => $f['livello'], 'attiva' => $f['attiva'],
                'verificata' => $f['verificata'], 'url' => $f['url'],
            ];
        }, BandiSources::carica(['soloAttive' => false]));

        echo json_encode(['totale' => count($fonti), 'fonti' => $fonti], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $limite = min(max((int) ($_GET['limite'] ?? 100), 1), 500);
    $bandi = $store->cerca([
        'livello' => $_GET['livello'] ?? null,
        'categoria' => $_GET['categoria'] ?? null,
        'regione' => $_GET['regione'] ?? null,
        'classe' => $_GET['classe'] ?? null,
        'testo' => $_GET['cerca'] ?? null,
        'entro' => isset($_GET['entro']) && $_GET['entro'] !== '' ? (int) $_GET['entro'] : null,
        'soloAperti' => ($_GET['scaduti'] ?? '') !== '1',
    ], $limite);

    echo json_encode([
        'totale' => count($bandi),
        'aggiornatoIl' => $store->aggiornatoIl(),
        'bandi' => $bandi,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['errore' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
