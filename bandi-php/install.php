<?php
/**
 * Installazione: verifica i requisiti dell'hosting e crea le tabelle.
 * Apri nel browser: https://tuo-dominio.it/bandi/install.php?token=IL_TUO_TOKEN
 * Quando hai finito, CANCELLA questo file dal server.
 */
require_once __DIR__ . '/lib/Bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

$esiti = [];
$errore = null;

try {
    $config = bandi_config();

    if (!bandi_token_valido($_GET['token'] ?? ($argv[1] ?? null))) {
        http_response_code(403);
        echo '<h1>Token mancante o errato</h1><p>Imposta un token in config.php e richiama questa pagina con <code>?token=IL_TUO_TOKEN</code>.</p>';
        exit;
    }

    // --- Requisiti ---
    $esiti[] = ['PHP ' . PHP_VERSION, PHP_VERSION_ID >= 70400, 'serve PHP 7.4 o superiore'];
    $esiti[] = ['Estensione mbstring', extension_loaded('mbstring'), 'necessaria per i testi accentati'];
    $esiti[] = ['Download HTTP', function_exists('curl_init') || ini_get('allow_url_fopen'), 'serve cURL oppure allow_url_fopen'];
    $esiti[] = ['Cartella cache scrivibile', is_writable($config['cartella_cache'] ?? __DIR__ . '/cache'), 'imposta i permessi 755 o 775 su cache/'];

    // --- Archivio ---
    $pdo = bandi_pdo();
    if ($pdo) {
        $store = bandi_store();
        $store->creaTabelle();
        $esiti[] = ['Database MySQL: tabelle create', true, 'tabella ' . ($config['db']['tabella'] ?? 'bandi_items')];
    } else {
        $cartellaDati = dirname($config['archivio_json'] ?? __DIR__ . '/data/bandi.json');
        if (!is_dir($cartellaDati)) {
            @mkdir($cartellaDati, 0775, true);
        }
        $esiti[] = ['Archivio su file JSON', is_writable($cartellaDati), 'la cartella ' . basename($cartellaDati) . '/ deve essere scrivibile'];
    }

    // --- Fonti ---
    $fonti = BandiSources::carica(['soloAttive' => false]);
    $esiti[] = ['Fonti configurate', count($fonti) > 0, count($fonti) . ' fonti in sources.json'];
} catch (Exception $e) {
    $errore = $e->getMessage();
}

$tuttoOk = !$errore && !array_filter($esiti, function ($e) {
    return !$e[1];
});
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installazione aggregatore bandi</title>
<style>
    body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; max-width: 760px; margin: 40px auto; padding: 0 20px; color: #1a202c; line-height: 1.6; }
    h1 { font-size: 1.5rem; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .ok { color: #22803c; font-weight: 600; }
    .ko { color: #c53030; font-weight: 600; }
    .nota { color: #4a5568; font-size: .9rem; }
    .box { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 18px; }
    code { background: #edf2f7; padding: 2px 6px; border-radius: 4px; }
</style>
</head>
<body>
<h1>Installazione aggregatore bandi</h1>

<?php if ($errore): ?>
    <div class="box"><strong class="ko">Errore:</strong> <?= htmlspecialchars($errore, ENT_QUOTES, 'UTF-8') ?></div>
<?php else: ?>
    <table>
        <?php foreach ($esiti as $esito): ?>
        <tr>
            <td><?= htmlspecialchars($esito[0], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="<?= $esito[1] ? 'ok' : 'ko' ?>"><?= $esito[1] ? 'OK' : 'DA SISTEMARE' ?></td>
            <td class="nota"><?= htmlspecialchars($esito[2], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php if ($tuttoOk): ?>
        <div class="box">
            <p><strong class="ok">Tutto pronto.</strong> Prossimi passi:</p>
            <ol>
                <li>Controlla le fonti: <a href="cron.php?token=<?= urlencode($_GET['token'] ?? '') ?>&amp;azione=doctor">verifica fonti</a></li>
                <li>Prima raccolta: <a href="cron.php?token=<?= urlencode($_GET['token'] ?? '') ?>">avvia la raccolta</a></li>
                <li>Guarda l'elenco: <a href="index.php">apri l'elenco bandi</a></li>
                <li>Imposta il cron giornaliero (vedi README.md)</li>
                <li><strong>Cancella install.php dal server.</strong></li>
            </ol>
        </div>
    <?php else: ?>
        <div class="box">Sistema le voci contrassegnate come <span class="ko">DA SISTEMARE</span> e ricarica la pagina.</div>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
