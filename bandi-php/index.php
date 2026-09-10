<?php
/**
 * Elenco pubblico dei bandi, generato lato server (niente JavaScript necessario).
 */
require_once __DIR__ . '/lib/Bootstrap.php';

$errore = null;
$bandi = [];
$aggiornatoIl = null;

$filtri = [
    'livello' => $_GET['livello'] ?? '',
    'categoria' => $_GET['categoria'] ?? '',
    'classe' => trim($_GET['classe'] ?? ''),
    'testo' => trim($_GET['cerca'] ?? ''),
    'entro' => isset($_GET['entro']) && $_GET['entro'] !== '' ? (int) $_GET['entro'] : null,
    'soloAperti' => ($_GET['scaduti'] ?? '') !== '1',
];

try {
    $store = bandi_store();
    $bandi = $store->cerca($filtri, 200);
    $aggiornatoIl = $store->aggiornatoIl();
} catch (Exception $e) {
    $errore = $e->getMessage();
}

function e($testo)
{
    return htmlspecialchars((string) $testo, ENT_QUOTES, 'UTF-8');
}

function selezionato($valore, $attuale)
{
    return $valore === $attuale ? ' selected' : '';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bandi per docenti e insegnanti | Scuole e Università italiane</title>
<meta name="description" content="Elenco aggiornato dei bandi, concorsi e avvisi per professori, ricercatori e insegnanti pubblicati da scuole, università e ministeri italiani.">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    :root {
        --primary: #667eea; --secondary: #764ba2; --dark: #1a202c; --light: #f7fafc;
        --danger: #f56565; --warning: #dd6b20; --border: #e2e8f0;
    }
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: var(--dark); line-height: 1.6; background: var(--light); }
    header { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: #fff; padding: 48px 20px 40px; }
    .wrap { max-width: 1000px; margin: 0 auto; padding: 0 20px; }
    header h1 { font-size: clamp(1.6rem, 4vw, 2.4rem); margin-bottom: 8px; }
    header p { opacity: .92; max-width: 62ch; }
    form.filtri { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin: -28px auto 24px; display: flex; flex-wrap: wrap; gap: 10px; box-shadow: 0 8px 24px rgba(26,32,44,.08); max-width: 1000px; }
    form.filtri select, form.filtri input { font: inherit; padding: 9px 12px; border: 1px solid var(--border); border-radius: 8px; background: #fff; min-width: 140px; flex: 1 1 140px; }
    form.filtri button { font: inherit; padding: 9px 20px; border: 0; border-radius: 8px; background: var(--primary); color: #fff; cursor: pointer; font-weight: 600; }
    form.filtri button:hover { background: var(--secondary); }
    .stato { color: #4a5568; font-size: .92rem; margin-bottom: 14px; }
    .bando { background: #fff; border: 1px solid var(--border); border-left: 4px solid var(--primary); border-radius: 10px; padding: 16px 18px; margin-bottom: 12px; }
    .bando h2 { font-size: 1.03rem; line-height: 1.4; margin-bottom: 6px; }
    .bando a { color: #4c51bf; text-decoration: none; }
    .bando a:hover { text-decoration: underline; }
    .meta { color: #4a5568; font-size: .88rem; display: flex; flex-wrap: wrap; gap: 6px 14px; }
    .tag { display: inline-block; font-size: .74rem; text-transform: uppercase; letter-spacing: .04em; padding: 2px 8px; border-radius: 999px; background: #edf2f7; color: #2d3748; }
    .tag.universita { background: #e6f0ff; color: #2c5282; }
    .tag.scuola { background: #e6fffa; color: #22543d; }
    .scadenza { font-weight: 600; }
    .scadenza.urgente { color: var(--danger); }
    .scadenza.vicina { color: var(--warning); }
    .vuoto { background: #fff; border: 1px dashed var(--border); border-radius: 10px; padding: 32px; text-align: center; color: #4a5568; }
    footer { padding: 28px 20px 48px; color: #4a5568; font-size: .85rem; text-align: center; }
    main { padding-bottom: 24px; }
    code { background: #edf2f7; padding: 2px 6px; border-radius: 4px; }
</style>
</head>
<body>
<header>
    <div class="wrap">
        <h1>Bandi per docenti e insegnanti</h1>
        <p>Concorsi, supplenze, chiamate e incarichi di insegnamento raccolti dalle fonti ufficiali di scuole, università e ministeri italiani.</p>
    </div>
</header>

<form class="filtri wrap" method="get" action="index.php">
    <select name="livello" aria-label="Livello">
        <option value="">Tutti i livelli</option>
        <option value="universita"<?= selezionato('universita', $filtri['livello']) ?>>Università / AFAM</option>
        <option value="scuola"<?= selezionato('scuola', $filtri['livello']) ?>>Scuola</option>
    </select>
    <select name="categoria" aria-label="Tipo di bando">
        <option value="">Tutte le tipologie</option>
        <?php foreach (['concorso' => 'Concorso', 'supplenza' => 'Supplenza / MAD', 'ricerca' => 'Ricerca / assegni',
                        'chiamata' => 'Chiamata / mobilità', 'contratto' => 'Incarico a contratto', 'formazione' => 'Formazione / PNRR'] as $valore => $etichetta): ?>
            <option value="<?= e($valore) ?>"<?= selezionato($valore, $filtri['categoria']) ?>><?= e($etichetta) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="entro" aria-label="Scadenza">
        <option value="">Qualsiasi scadenza</option>
        <?php foreach ([7, 15, 30] as $giorni): ?>
            <option value="<?= $giorni ?>"<?= selezionato((string) $giorni, (string) ($filtri['entro'] ?? '')) ?>>Entro <?= $giorni ?> giorni</option>
        <?php endforeach; ?>
    </select>
    <input type="search" name="cerca" value="<?= e($filtri['testo']) ?>" placeholder="Cerca (ente, materia…)" aria-label="Cerca">
    <input type="text" name="classe" value="<?= e($filtri['classe']) ?>" placeholder="Classe (es. A-28)" aria-label="Classe di concorso">
    <button type="submit">Filtra</button>
</form>

<main class="wrap">
<?php if ($errore): ?>
    <div class="vuoto">
        <strong>Archivio non disponibile.</strong><br>
        <?= e($errore) ?><br><br>
        Se è la prima installazione, apri <code>install.php?token=...</code> e poi <code>cron.php?token=...</code>.
    </div>
<?php elseif (!$bandi): ?>
    <div class="vuoto">
        Nessun bando trovato con questi filtri.<br>
        Se l'archivio è vuoto, lancia la raccolta con <code>cron.php?token=IL_TUO_TOKEN</code>.
    </div>
<?php else: ?>
    <p class="stato">
        <?= count($bandi) ?> risultati<?= $aggiornatoIl ? ' · archivio aggiornato al ' . e(BandiDates::formatta($aggiornatoIl)) : '' ?>
    </p>

    <?php foreach ($bandi as $bando): ?>
        <?php
            $giorni = BandiDates::daysUntil($bando['scadenza']);
            $classeScadenza = $giorni === null ? '' : ($giorni <= 3 ? ' urgente' : ($giorni <= 10 ? ' vicina' : ''));
            $etichettaLivello = $bando['livello'] === 'universita' ? 'Università' : ($bando['livello'] === 'scuola' ? 'Scuola' : 'Da classificare');
            // Solo http(s): l'URL arriva da pagine esterne.
            $url = preg_match('~^https?://~i', (string) $bando['url']) ? $bando['url'] : '#';
        ?>
        <article class="bando">
            <h2><a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"><?= e($bando['titolo']) ?></a></h2>
            <div class="meta">
                <span class="tag <?= e($bando['livello']) ?>"><?= e($etichettaLivello) ?></span>
                <span class="tag"><?= e($bando['categoria'] ?: 'altro') ?></span>
                <?php if ($bando['ente']): ?><span><?= e($bando['ente']) ?></span><?php endif; ?>
                <?php if ($bando['classiConcorso']): ?><span>Classi: <?= e(implode(', ', $bando['classiConcorso'])) ?></span><?php endif; ?>
                <?php if ($bando['ssd']): ?><span>SSD: <?= e(implode(', ', $bando['ssd'])) ?></span><?php endif; ?>
                <span class="scadenza<?= $classeScadenza ?>">
                    <?php if ($bando['scadenza']): ?>
                        Scade il <?= e(BandiDates::formatta($bando['scadenza'])) ?><?= $giorni >= 0 ? ' · ' . $giorni . ' gg' : ' · scaduto' ?>
                    <?php else: ?>
                        Scadenza non rilevata
                    <?php endif; ?>
                </span>
                <span>Fonte: <?= e($bando['fonteNome'] ?: $bando['fonte']) ?></span>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
</main>

<footer class="wrap">
    Dati raccolti automaticamente dalle fonti pubbliche configurate.
    Verificare sempre il bando originale sul sito dell'ente prima di presentare domanda.
</footer>
</body>
</html>
