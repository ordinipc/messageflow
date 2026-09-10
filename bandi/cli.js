#!/usr/bin/env node
'use strict';

/**
 * Aggregatore di bandi per docenti e insegnanti (scuole e università italiane).
 *
 *   node bandi/cli.js run --livello universita --approfondisci
 *   node bandi/cli.js list --entro 30 --classe A-28
 *   node bandi/cli.js doctor
 *   node bandi/cli.js export --formato csv > bandi.csv
 */

const path = require('path');
const { loadSources } = require('./lib/sources');
const { collectAll, doctorSource } = require('./lib/collect');
const { Store, filterBandi, sortBandi } = require('./lib/store');
const { digestTesto, inviaEmail, inviaWebhook, scriviDigest, formatDate } = require('./lib/notify');
const { daysUntil } = require('./lib/dates');

function parseArgs(argv) {
    const args = { _: [] };
    for (let i = 0; i < argv.length; i++) {
        const token = argv[i];
        if (!token.startsWith('--')) {
            args._.push(token);
            continue;
        }
        const [key, inline] = token.slice(2).split('=');
        if (inline !== undefined) {
            args[key] = inline;
        } else if (argv[i + 1] && !argv[i + 1].startsWith('--')) {
            args[key] = argv[++i];
        } else {
            args[key] = true;
        }
    }
    return args;
}

function numero(value, fallback) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function filtriDaArgs(args) {
    return {
        livello: args.livello,
        categoria: args.categoria,
        regione: args.regione,
        classe: args.classe,
        testo: args.cerca,
        entro: args.entro !== undefined ? numero(args.entro, undefined) : undefined,
        soloAperti: args.scaduti ? false : true,
        minPunteggio: args.punteggio !== undefined ? numero(args.punteggio, undefined) : undefined
    };
}

function stampaBandi(bandi, { json } = {}) {
    if (json) {
        process.stdout.write(JSON.stringify(bandi, null, 2) + '\n');
        return;
    }
    if (bandi.length === 0) {
        console.log('Nessun bando corrisponde ai filtri.');
        return;
    }
    for (const bando of bandi) {
        const giorni = daysUntil(bando.scadenza);
        const scadenza = bando.scadenza
            ? `${formatDate(bando.scadenza)}${giorni != null ? ` (${giorni >= 0 ? `fra ${giorni} gg` : 'scaduto'})` : ''}`
            : 'scadenza n.d.';
        console.log(`\n[${bando.livello}/${bando.categoria}] ${bando.titolo}`);
        console.log(`  ente:     ${bando.ente || 'n.d.'}${bando.regione ? ` (${bando.regione})` : ''}`);
        console.log(`  scadenza: ${scadenza}`);
        if ((bando.classiConcorso || []).length) console.log(`  classi:   ${bando.classiConcorso.join(', ')}`);
        if ((bando.ssd || []).length) console.log(`  ssd:      ${bando.ssd.join(', ')}`);
        console.log(`  fonte:    ${bando.fonteNome || bando.fonte}`);
        console.log(`  link:     ${bando.url}`);
    }
    console.log(`\n${bandi.length} bandi.`);
}

function toCsv(bandi) {
    const colonne = ['id', 'titolo', 'livello', 'categoria', 'ente', 'regione', 'classiConcorso', 'ssd', 'scadenza', 'pubblicatoIl', 'fonte', 'url'];
    const escape = (value) => {
        const text = Array.isArray(value) ? value.join(' ') : value == null ? '' : String(value);
        return /[",;\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
    };
    return [colonne.join(','), ...bandi.map((b) => colonne.map((c) => escape(b[c])).join(','))].join('\n');
}

async function comandoRun(args) {
    const sources = loadSources({
        ids: args.fonte ? String(args.fonte).split(',') : null,
        livello: args.livello
    });

    if (sources.length === 0) {
        console.error('Nessuna fonte attiva corrisponde ai criteri. Controlla bandi/sources.json.');
        process.exitCode = 1;
        return;
    }

    console.log(`Raccolta da ${sources.length} fonti...\n`);

    const { risultati, bandi } = await collectAll(sources, {
        soglia: numero(args.soglia, undefined),
        maxPerFonte: numero(args['max-fonte'], 60),
        approfondisci: Boolean(args.approfondisci),
        maxDettagli: numero(args['max-dettagli'], 5),
        timeoutMs: numero(args.timeout, 20000),
        useCache: !args['no-cache'],
        respectRobots: !args['ignora-robots'],
        onSource: (r) => {
            const stato = r.errore ? `ERRORE: ${r.errore}` : `${r.bandi.length} bandi su ${r.esaminati} voci`;
            console.log(`  ${r.errore ? '✗' : '✓'} ${r.nome || r.fonte}: ${stato}`);
        }
    });

    const store = await new Store(args.db).load();
    const { nuovi, aggiornati, invariati } = store.upsertMany(bandi);
    if (args.pulisci) store.prune(numero(args.pulisci, 120));
    if (!args['dry-run']) await store.save();

    console.log(`\nNuovi: ${nuovi.length} | aggiornati: ${aggiornati.length} | invariati: ${invariati}`);
    const fallite = risultati.filter((r) => r.errore);
    if (fallite.length) console.log(`Fonti non raggiungibili: ${fallite.map((r) => r.fonte).join(', ')}`);

    if (nuovi.length > 0) {
        console.log('\n' + digestTesto(sortBandi(nuovi)));
    }

    if (args.email) {
        const esito = await inviaEmail(sortBandi(nuovi), { to: args.email === true ? undefined : args.email });
        console.log(esito.inviata ? `\nEmail inviata a ${esito.destinatario}` : `\nEmail non inviata: ${esito.motivo}`);
    }
    if (args.webhook) {
        const esito = await inviaWebhook(sortBandi(nuovi), { url: args.webhook === true ? undefined : args.webhook });
        console.log(esito.inviata ? `Webhook notificato: ${esito.destinatario}` : `Webhook non inviato: ${esito.motivo}`);
    }
    if (args.digest) {
        const file = await scriviDigest(sortBandi(nuovi), path.resolve(String(args.digest)));
        console.log(`Digest scritto in ${file}`);
    }
}

async function comandoList(args) {
    const store = await new Store(args.db).load();
    const bandi = sortBandi(filterBandi(store.all(), filtriDaArgs(args)));
    const limite = numero(args.limite, 50);
    stampaBandi(bandi.slice(0, limite), { json: Boolean(args.json) });
}

async function comandoExport(args) {
    const store = await new Store(args.db).load();
    const bandi = sortBandi(filterBandi(store.all(), filtriDaArgs(args)));
    if (String(args.formato || 'json') === 'csv') process.stdout.write(toCsv(bandi) + '\n');
    else process.stdout.write(JSON.stringify(bandi, null, 2) + '\n');
}

async function comandoSources(args) {
    const sources = loadSources({ soloAttive: false });
    for (const source of sources) {
        console.log(`${source.attiva === false ? '○' : '●'} ${source.id.padEnd(28)} ${source.tipo.padEnd(5)} ${String(source.livello).padEnd(11)} ${source.url}`);
    }
    console.log(`\n${sources.length} fonti (● attiva, ○ disattivata). Aggiungi le tue in bandi/sources.local.json.`);
}

async function comandoDoctor(args) {
    const sources = loadSources({
        soloAttive: !args.tutte,
        ids: args.fonte ? String(args.fonte).split(',') : null
    });

    console.log('Verifica delle fonti (una richiesta per fonte, rispettando robots.txt)...\n');
    let ok = 0;

    for (const source of sources) {
        const esito = await doctorSource(source, {
            timeoutMs: numero(args.timeout, 20000),
            respectRobots: !args['ignora-robots']
        });
        if (esito.ok) ok++;
        console.log(`${esito.ok ? '✓' : '✗'} ${esito.fonte}`);
        console.log(`    stato: ${esito.status ?? 'n.d.'} | voci: ${esito.voci} | pertinenti: ${esito.pertinenti} | ${esito.ms} ms`);
        if (esito.errore) console.log(`    problema: ${esito.errore}`);
        for (const esempio of esito.esempi) console.log(`    es. "${esempio}"`);
    }

    console.log(`\n${ok}/${sources.length} fonti funzionanti.`);
    if (ok < sources.length) {
        console.log('Le fonti in errore vanno aggiornate in bandi/sources.json (o disattivate con "attiva": false).');
        process.exitCode = 1;
    }
}

function aiuto() {
    console.log(`Aggregatore bandi per docenti e insegnanti (scuole e università italiane)

Uso: node bandi/cli.js <comando> [opzioni]

Comandi:
  run        Scarica le fonti, filtra i bandi per docenti e salva le novità
  list       Mostra i bandi già archiviati
  export     Esporta i bandi (--formato json|csv)
  sources    Elenca le fonti configurate
  doctor     Verifica che le fonti rispondano e siano ancora parsabili

Opzioni di raccolta (run):
  --fonte a,b          Limita alle fonti indicate
  --livello X          scuola | universita | tutti
  --approfondisci      Apre le pagine di dettaglio per trovare le scadenze
  --max-dettagli N     Massimo pagine di dettaglio per fonte (default 5)
  --max-fonte N        Massimo bandi per fonte (default 60)
  --soglia N           Soglia di pertinenza del classificatore (default 5)
  --no-cache           Ignora la cache ETag
  --dry-run            Non scrive nell'archivio
  --email [dest]       Invia il digest via SMTP (vedi .env)
  --webhook [url]      Invia il digest a un webhook
  --digest file.txt    Scrive il digest su file
  --pulisci [giorni]   Elimina i bandi scaduti da più di N giorni (default 120)

Filtri (list/export):
  --livello, --categoria, --regione, --classe A-28, --cerca "testo"
  --entro N            Solo bandi in scadenza entro N giorni
  --scaduti            Includi anche i bandi già scaduti
  --limite N           Numero massimo di risultati (default 50)
  --json               Output JSON

Variabili d'ambiente: BANDI_DB, BANDI_CACHE_DIR, BANDI_USER_AGENT,
BANDI_MIN_INTERVAL_MS, BANDI_EMAIL_TO, BANDI_WEBHOOK_URL, SMTP_*`);
}

async function main() {
    const args = parseArgs(process.argv.slice(2));
    const comando = args._[0] || 'help';

    const comandi = {
        run: comandoRun,
        list: comandoList,
        export: comandoExport,
        sources: comandoSources,
        doctor: comandoDoctor
    };

    if (!comandi[comando]) {
        aiuto();
        if (comando !== 'help') process.exitCode = 1;
        return;
    }

    await comandi[comando](args);
}

if (require.main === module) {
    main().catch((error) => {
        console.error(`Errore: ${error.message}`);
        process.exitCode = 1;
    });
}

module.exports = { parseArgs, toCsv, main };
