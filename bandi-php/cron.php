<?php
/**
 * Raccolta dei bandi.
 *
 * Da cron (consigliato):   php /percorso/bandi/cron.php
 * Da browser o cron-job.org: https://tuo-dominio.it/bandi/cron.php?token=IL_TUO_TOKEN
 *
 * Azioni: (nessuna) = raccolta, ?azione=doctor = verifica fonti.
 */
require_once __DIR__ . '/lib/Bootstrap.php';

$daRigaDiComando = (PHP_SAPI === 'cli');

if (!$daRigaDiComando) {
    header('Content-Type: text/plain; charset=UTF-8');
    if (!bandi_token_valido($_GET['token'] ?? '')) {
        http_response_code(403);
        echo "Token mancante o errato.\n";
        exit(1);
    }
}

// Sugli hosting condivisi il limite di tempo è basso: si alza se permesso,
// e in ogni caso il budget in config.php ferma la raccolta prima del taglio.
@set_time_limit(0);
@ini_set('memory_limit', '256M');

$azione = $daRigaDiComando ? ($argv[1] ?? 'run') : ($_GET['azione'] ?? 'run');
$config = bandi_config();

try {
    if ($azione === 'doctor') {
        $collector = bandi_collector();
        $ok = 0;
        $fonti = BandiSources::carica(['soloAttive' => true]);

        echo "Verifica delle fonti (rispettando robots.txt)\n\n";
        foreach ($fonti as $fonte) {
            $esito = $collector->diagnostica($fonte);
            $ok += $esito['ok'] ? 1 : 0;
            printf("%s %s\n", $esito['ok'] ? '[OK]  ' : '[KO]  ', $esito['fonte']);
            printf("      stato: %s | voci: %d | pertinenti: %d | %d ms\n", $esito['status'] ?: 'n.d.', $esito['voci'], $esito['pertinenti'], $esito['ms']);
            if ($esito['errore']) {
                printf("      problema: %s\n", $esito['errore']);
            }
            foreach ($esito['esempi'] as $esempio) {
                printf("      es. \"%s\"\n", $esempio);
            }
        }
        printf("\n%d/%d fonti funzionanti.\n", $ok, count($fonti));
        exit($ok === count($fonti) ? 0 : 1);
    }

    $inizio = microtime(true);
    echo "Raccolta bandi — " . gmdate('d/m/Y H:i') . " UTC\n\n";

    $esito = bandi_esegui_raccolta([
        'onSource' => function ($risultato) {
            printf(
                "  %s %s: %s\n",
                $risultato['errore'] ? '[KO]' : '[OK]',
                $risultato['nome'],
                $risultato['errore'] ? 'ERRORE: ' . $risultato['errore'] : sprintf('%d bandi su %d voci', count($risultato['bandi']), $risultato['esaminati'])
            );
        },
    ]);

    printf("\nNuovi: %d | aggiornati: %d | invariati: %d | %.1f s\n",
        count($esito['nuovi']), count($esito['aggiornati']), $esito['invariati'], microtime(true) - $inizio);

    if ($esito['saltate']) {
        // Non è un errore: le fonti rimaste vengono elaborate alla prossima esecuzione.
        printf("Fonti rimandate per limite di tempo: %s\n", implode(', ', $esito['saltate']));
    }

    if ($esito['nuovi']) {
        $ordinati = bandi_ordina($esito['nuovi']);
        echo "\n" . BandiNotifier::digestTesto($ordinati) . "\n";

        if (!empty($config['email_a'])) {
            $mail = BandiNotifier::inviaEmail($ordinati, $config['email_a'], $config['email_da'] ?? null);
            echo "\n" . ($mail['inviata'] ? 'Email inviata a ' . $mail['destinatario'] : 'Email non inviata: ' . $mail['motivo']) . "\n";
        }
        if (!empty($config['webhook'])) {
            $hook = BandiNotifier::inviaWebhook($ordinati, $config['webhook']);
            echo ($hook['inviata'] ? 'Webhook notificato.' : 'Webhook non inviato: ' . $hook['motivo']) . "\n";
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo 'Errore: ' . $e->getMessage() . "\n";
    exit(1);
}
