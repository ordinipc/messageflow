'use strict';

const test = require('node:test');
const assert = require('node:assert');
const http = require('node:http');
const fs = require('fs');
const os = require('os');
const path = require('path');

const { collectAll, doctorSource } = require('../lib/collect');
const { Store, sortBandi } = require('../lib/store');
const { clearMemoryCaches } = require('../lib/http');

const fixture = (name) => fs.readFileSync(path.join(__dirname, 'fixtures', name), 'utf8');

const PAGINA_DETTAGLIO = `<html><head><title>Bando MAD - Istituto Comprensivo Verdi</title></head>
<body><h1>Messa a disposizione</h1>
<p>Le domande devono pervenire entro le ore 12:00 del 20 settembre 2026.</p>
<p>Sede: Istituto Comprensivo Verdi, Lombardia.</p></body></html>`;

/** Server locale che imita un portale della PA: robots.txt, un feed e una pagina di albo. */
function avviaPortale() {
    return new Promise((resolve) => {
        const server = http.createServer((req, res) => {
            const url = req.url.split('?')[0];

            if (url === '/robots.txt') {
                res.writeHead(200, { 'content-type': 'text/plain' });
                return res.end('User-agent: *\nDisallow: /riservato/\n');
            }
            if (url === '/rss') {
                res.writeHead(200, { 'content-type': 'application/rss+xml' });
                return res.end(fixture('feed-gazzetta.xml'));
            }
            if (url === '/albo-senza-date/') {
                res.writeHead(200, { 'content-type': 'text/html' });
                return res.end(`<html><body><ul>
                    <li><a href="${'/albo/mad-2026'}">Avviso di selezione per docenti: messa a disposizione</a></li>
                </ul></body></html>`);
            }
            if (url === '/albo/') {
                res.writeHead(200, { 'content-type': 'text/html' });
                return res.end(fixture('pagina-scuola.html'));
            }
            if (url === '/albo/mad-2026' || url === '/albo/esperto-pnrr') {
                res.writeHead(200, { 'content-type': 'text/html' });
                return res.end(PAGINA_DETTAGLIO);
            }
            if (url === '/riservato/bandi') {
                res.writeHead(200, { 'content-type': 'text/html' });
                return res.end('<a href="/x">Concorso per professore ordinario</a>');
            }
            res.writeHead(404, { 'content-type': 'text/plain' });
            res.end('non trovato');
        });

        server.listen(0, '127.0.0.1', () => {
            const { port } = server.address();
            resolve({ server, base: `http://127.0.0.1:${port}` });
        });
    });
}

const opzioni = { minIntervalMs: 0, useCache: false, timeoutMs: 5000, now: new Date('2026-03-15T00:00:00Z') };

test('raccolta completa da un portale locale: filtra, deduplica e archivia', async (t) => {
    const { server, base } = await avviaPortale();
    t.after(() => {
        server.close();
        clearMemoryCaches();
    });

    const sources = [
        { id: 'feed-locale', nome: 'Feed locale', tipo: 'rss', url: `${base}/rss`, livello: 'misto' },
        { id: 'albo-locale', nome: 'Albo locale', tipo: 'html', url: `${base}/albo/`, livello: 'scuola' }
    ];

    const { bandi, risultati } = await collectAll(sources, opzioni);

    assert.ok(risultati.every((r) => r.errore === null), JSON.stringify(risultati.map((r) => r.errore)));
    assert.ok(bandi.length >= 3);

    const titoli = bandi.map((b) => b.titolo).join(' | ');
    assert.ok(!/collaboratore scolastico/i.test(titoli), 'gli avvisi ATA non devono passare');
    assert.ok(!/fornitura/i.test(titoli), 'gli appalti non devono passare');
    assert.match(titoli, /ricercatore a tempo determinato/i);
    assert.match(titoli, /messa a disposizione/i);

    const file = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'bandi-int-')), 'db.json');
    const store = await new Store(file).load();

    const prima = store.upsertMany(bandi);
    assert.strictEqual(prima.nuovi.length, bandi.length);

    // Seconda esecuzione sugli stessi dati: nessun bando deve risultare nuovo.
    const dopo = store.upsertMany(bandi);
    assert.strictEqual(dopo.nuovi.length, 0);

    await store.save();
    assert.ok(JSON.parse(fs.readFileSync(file, 'utf8')).bandi);

    const ordinati = sortBandi(store.all());
    assert.ok(new Date(ordinati[0].scadenza) <= new Date(ordinati[ordinati.length - 1].scadenza || '2999-01-01'));
});

test('--approfondisci recupera scadenza ed ente dalla pagina di dettaglio', async (t) => {
    const { server, base } = await avviaPortale();
    t.after(() => {
        server.close();
        clearMemoryCaches();
    });

    // In questo elenco i link non riportano date: la scadenza esiste solo nella pagina di dettaglio.
    const source = { id: 'albo-scarno', nome: 'Albo senza date', tipo: 'html', url: `${base}/albo-senza-date/`, livello: 'scuola' };

    const senza = await collectAll([source], opzioni);
    assert.strictEqual(senza.bandi.length, 1);
    assert.strictEqual(senza.bandi[0].scadenza, null, 'senza --approfondisci la scadenza non è nota');

    const con = await collectAll([source], { ...opzioni, approfondisci: true, maxDettagli: 3 });
    assert.strictEqual(con.bandi[0].scadenza, '2026-09-20T12:00:00.000Z');
    assert.strictEqual(con.bandi[0].regione, 'lombardia');
    assert.match(con.bandi[0].ente, /Istituto Comprensivo Verdi/);
});

test('la scadenza già presente nell’elenco non viene sovrascritta da una fetch inutile', async (t) => {
    const { server, base } = await avviaPortale();
    t.after(() => {
        server.close();
        clearMemoryCaches();
    });

    const source = { id: 'albo-locale', nome: 'Albo locale', tipo: 'html', url: `${base}/albo/`, livello: 'scuola' };
    const { bandi } = await collectAll([source], { ...opzioni, approfondisci: true, maxDettagli: 3 });

    const esperto = bandi.find((b) => /esperto/i.test(b.titolo));
    assert.ok(esperto, 'il bando per esperto formatore deve essere raccolto');
    assert.strictEqual(esperto.scadenza, '2026-10-12T00:00:00.000Z');
});

test('robots.txt viene rispettato', async (t) => {
    const { server, base } = await avviaPortale();
    t.after(() => {
        server.close();
        clearMemoryCaches();
    });

    const vietata = { id: 'riservata', nome: 'Area riservata', tipo: 'html', url: `${base}/riservato/bandi`, livello: 'misto' };
    const { risultati } = await collectAll([vietata], opzioni);

    assert.match(risultati[0].errore, /robots\.txt/);
    assert.strictEqual(risultati[0].bandi.length, 0);
});

test('doctor segnala una fonte irraggiungibile senza far fallire le altre', async (t) => {
    const { server, base } = await avviaPortale();
    t.after(() => {
        server.close();
        clearMemoryCaches();
    });

    const buona = await doctorSource({ id: 'ok', nome: 'Feed', tipo: 'rss', url: `${base}/rss` }, opzioni);
    assert.strictEqual(buona.ok, true);
    assert.strictEqual(buona.voci, 3);
    assert.ok(buona.pertinenti >= 2);

    const rotta = await doctorSource({ id: 'ko', nome: 'Assente', tipo: 'html', url: `${base}/inesistente` }, opzioni);
    assert.strictEqual(rotta.ok, false);
    assert.match(rotta.errore, /404/);
});
