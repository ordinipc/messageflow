<?php
/**
 * Configurazione dell'aggregatore bandi.
 * Copia questo file in config.php e compila i tuoi dati.
 * config.php non va mai pubblicato né condiviso: contiene le password.
 */
return [
    // ==================== DATABASE ====================
    // Lascia 'nome' vuoto per non usare MySQL: i bandi verranno salvati
    // nel file indicato in 'archivio_json' (funziona ovunque, senza database).
    'db' => [
        'host' => 'localhost',
        'porta' => 3306,
        'nome' => '',            // es. 'Sql123456_1' (nome del database del tuo hosting)
        'utente' => '',          // es. 'Sql123456'
        'password' => '',
        'tabella' => 'bandi_items',
    ],

    'archivio_json' => __DIR__ . '/data/bandi.json',

    // ==================== SICUREZZA ====================
    // Token richiesto per install.php e per lanciare la raccolta da browser
    // (cron.php?token=...). Metti una stringa lunga e casuale.
    'token' => '',

    // ==================== NOTIFICHE ====================
    'email_a' => '',             // destinatario del digest, es. 'tu@dominio.it'
    'email_da' => '',            // mittente, es. 'bandi@tuo-dominio.it'
    'webhook' => '',             // opzionale: URL webhook (Slack, Telegram, n8n…)

    // ==================== RACCOLTA ====================
    // Indica un contatto reale: è buona educazione verso gli enti pubblici.
    'user_agent' => 'BandiScuolaBot/1.0 (+https://tuo-dominio.it; contatto: tu@dominio.it)',

    'intervallo_minimo' => 1.5,  // secondi fra due richieste allo stesso sito
    'rispetta_robots' => true,   // NON disattivare senza un buon motivo
    'soglia' => 5,               // soglia di pertinenza del classificatore
    'max_per_fonte' => 60,       // massimo bandi tenuti per fonte
    'approfondisci' => true,     // apre le pagine di dettaglio per trovare le scadenze
    'max_dettagli' => 5,         // quante pagine di dettaglio per fonte

    // Tempo massimo di una singola esecuzione: sugli hosting condivisi
    // max_execution_time è spesso 30 s. Le fonti non elaborate vengono
    // riprese alla chiamata successiva del cron.
    'budget_secondi' => 20,

    'cartella_cache' => __DIR__ . '/cache',
];
