<?php
/**
 * Test suite senza dipendenze esterne (niente Composer né PHPUnit:
 * deve poter girare anche sull'hosting).
 *
 *   php tests/run.php
 */
declare(ticks=1);

$GLOBALS['bandi_test'] = ['passati' => 0, 'falliti' => 0, 'errori' => []];

function test($nome, callable $corpo)
{
    try {
        $corpo();
        $GLOBALS['bandi_test']['passati']++;
        echo "  ok   {$nome}\n";
    } catch (Throwable $e) {
        $GLOBALS['bandi_test']['falliti']++;
        $GLOBALS['bandi_test']['errori'][] = $nome . ' → ' . $e->getMessage();
        echo "  FAIL {$nome}\n       " . $e->getMessage() . "\n";
    }
}

function assertVero($condizione, $messaggio = 'condizione falsa')
{
    if (!$condizione) {
        throw new Exception($messaggio);
    }
}

function assertUguale($atteso, $ottenuto, $messaggio = '')
{
    if ($atteso !== $ottenuto) {
        throw new Exception(trim($messaggio . ' atteso: ' . var_export($atteso, true) . ' — ottenuto: ' . var_export($ottenuto, true)));
    }
}

function assertContiene($ago, $pagliaio, $messaggio = '')
{
    if (mb_strpos((string) $pagliaio, (string) $ago) === false) {
        throw new Exception(trim($messaggio . ' "' . $ago . '" non trovato in "' . mb_substr($pagliaio, 0, 200) . '"'));
    }
}

function fixture($nome)
{
    return file_get_contents(__DIR__ . '/fixtures/' . $nome);
}

require_once __DIR__ . '/../lib/Http.php';
require_once __DIR__ . '/../lib/Feed.php';
require_once __DIR__ . '/../lib/Html.php';
require_once __DIR__ . '/../lib/Dates.php';
require_once __DIR__ . '/../lib/Classifier.php';
require_once __DIR__ . '/../lib/Store.php';
require_once __DIR__ . '/../lib/Sources.php';
require_once __DIR__ . '/../lib/Collector.php';
require_once __DIR__ . '/../lib/Notifier.php';

foreach (['parsing', 'classificazione', 'archivio', 'integrazione'] as $gruppo) {
    echo "\n# {$gruppo}\n";
    require __DIR__ . '/' . $gruppo . '.test.php';
}

$stato = $GLOBALS['bandi_test'];
echo "\n" . str_repeat('-', 50) . "\n";
printf("passati: %d | falliti: %d\n", $stato['passati'], $stato['falliti']);

exit($stato['falliti'] > 0 ? 1 : 0);
