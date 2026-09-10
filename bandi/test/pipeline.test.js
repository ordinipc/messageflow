'use strict';

const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const os = require('os');
const path = require('path');

const { candidatesFromFeed, candidatesFromHtml, candidatesFromJson, toBando } = require('../lib/collect');
const { classify } = require('../lib/classify');
const { Store, fingerprint, filterBandi, sortBandi } = require('../lib/store');
const { loadSources } = require('../lib/sources');
const { parseArgs, toCsv } = require('../cli');
const { digestTesto } = require('../lib/notify');

const fixture = (name) => fs.readFileSync(path.join(__dirname, 'fixtures', name), 'utf8');
const source = { id: 'test', nome: 'Fonte di prova', tipo: 'rss', url: 'https://example.gov.it/rss', livello: 'misto' };
const ORA = new Date('2026-03-15T00:00:00Z');

function pipeline(candidates, sorgente = source) {
    return candidates
        .filter((c) => c.url && c.title)
        .map((c) => ({ c, verdict: classify(c) }))
        .filter(({ verdict }) => verdict.pertinente)
        .map(({ c, verdict }) => toBando(c, sorgente, verdict, ORA));
}

test('dal feed della Gazzetta restano solo i bandi per docenti', () => {
    const bandi = pipeline(candidatesFromFeed(fixture('feed-gazzetta.xml'), source));
    assert.strictEqual(bandi.length, 2);
    assert.ok(bandi.every((b) => !/collaboratore scolastico/i.test(b.titolo)));
});

test('il bando conserva scadenza, ente e livello dedotti', () => {
    const bandi = pipeline(candidatesFromFeed(fixture('feed-gazzetta.xml'), source));
    const ricercatore = bandi.find((b) => /ricercatore/i.test(b.titolo));
    assert.strictEqual(ricercatore.scadenza, '2026-04-30T00:00:00.000Z');
    assert.strictEqual(ricercatore.ente, 'Università degli Studi di Bologna');
    assert.strictEqual(ricercatore.livello, 'universita');
    assert.strictEqual(ricercatore.pubblicatoIl, '2026-03-10T08:00:00.000Z');

    const contratto = bandi.find((b) => /insegnamento/i.test(b.titolo));
    assert.strictEqual(contratto.scadenza, '2026-05-15T12:00:00.000Z');
});

test('dalla pagina di una scuola restano MAD ed esperto formatore, non ATA né appalti', () => {
    const html = fixture('pagina-scuola.html');
    const candidates = candidatesFromHtml(html, { ...source, tipo: 'html' }, 'https://icverdi.edu.it/albo/');
    const bandi = pipeline(candidates, { ...source, tipo: 'html' });
    const titoli = bandi.map((b) => b.titolo).join(' | ');

    assert.match(titoli, /messa a disposizione/i);
    assert.ok(!/fornitura/i.test(titoli));
    assert.ok(!/ATA/.test(titoli));

    const mad = bandi.find((b) => /messa a disposizione/i.test(b.titolo));
    assert.deepStrictEqual(mad.classiConcorso, ['A-28']);
    assert.strictEqual(mad.scadenza, '2026-09-20T00:00:00.000Z');
    assert.strictEqual(mad.categoria, 'supplenza');
});

test('la sorgente JSON usa la mappatura dei campi e il template di URL', () => {
    const body = JSON.stringify({
        data: {
            items: [
                { codice: 42, oggetto: 'Concorso per professore associato', testo: 'Università degli Studi di Genova', chiude: '2026-06-30' },
                { codice: 43, oggetto: 'Gara per fornitura arredi', testo: 'appalto', chiude: '2026-06-30' }
            ]
        }
    });
    const jsonSource = {
        ...source,
        tipo: 'json',
        jsonPath: 'data.items',
        urlTemplate: 'https://portale.example.it/bando/{id}',
        campi: { titolo: 'oggetto', url: 'codice', descrizione: 'testo', scadenza: 'chiude' }
    };

    const bandi = pipeline(candidatesFromJson(body, jsonSource), jsonSource);
    assert.strictEqual(bandi.length, 1);
    assert.strictEqual(bandi[0].url, 'https://portale.example.it/bando/42');
    assert.strictEqual(bandi[0].scadenza, '2026-06-30T00:00:00.000Z');
    assert.strictEqual(bandi[0].scadenzaEvidenza, 'campo scadenza della fonte');
});

test('candidatesFromJson segnala un jsonPath sbagliato', () => {
    assert.throws(() => candidatesFromJson('{"a":1}', { ...source, tipo: 'json', jsonPath: 'b.c' }), /non punta a un array/);
    assert.throws(() => candidatesFromJson('non-json', { ...source, tipo: 'json' }), /JSON non valida/);
});

test('la deduplica ignora i parametri di tracciamento e lo slash finale', () => {
    const a = { titolo: 'Concorso docenti', url: 'https://x.it/b/1?utm_source=news' };
    const b = { titolo: 'Concorso docenti', url: 'https://x.it/b/1' };
    const c = { titolo: 'Concorso docenti', url: 'https://x.it/b/2' };
    assert.strictEqual(fingerprint(a), fingerprint(b));
    assert.notStrictEqual(fingerprint(a), fingerprint(c));
});

test('lo store distingue i nuovi bandi dai già visti e unisce le fonti', async () => {
    const file = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'bandi-')), 'db.json');
    const store = await new Store(file).load();

    const primo = store.upsertMany([{ titolo: 'Concorso docenti', url: 'https://x.it/1', fonte: 'a', punteggio: 6 }]);
    assert.strictEqual(primo.nuovi.length, 1);

    const secondo = store.upsertMany([
        { titolo: 'Concorso docenti', url: 'https://x.it/1', fonte: 'b', punteggio: 9, scadenza: '2026-05-01T00:00:00.000Z' }
    ]);
    assert.strictEqual(secondo.nuovi.length, 0);
    assert.strictEqual(secondo.aggiornati.length, 1);
    assert.deepStrictEqual(secondo.aggiornati[0].fonti, ['a', 'b']);
    assert.strictEqual(secondo.aggiornati[0].punteggio, 9);
    assert.strictEqual(secondo.aggiornati[0].scadenza, '2026-05-01T00:00:00.000Z');

    await store.save();
    const riletto = await new Store(file).load();
    assert.strictEqual(riletto.all().length, 1);
});

test('prune elimina i bandi scaduti da troppo tempo', async () => {
    const file = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'bandi-')), 'db.json');
    const store = await new Store(file).load();
    store.upsertMany([
        { titolo: 'Vecchio', url: 'https://x.it/vecchio', scadenza: '2020-01-01T00:00:00.000Z' },
        { titolo: 'Futuro', url: 'https://x.it/futuro', scadenza: '2099-01-01T00:00:00.000Z' }
    ]);
    assert.strictEqual(store.prune(120), 1);
    assert.strictEqual(store.all().length, 1);
});

test('i filtri di ricerca funzionano su livello, scadenza e classe di concorso', () => {
    const bandi = [
        { titolo: 'Uni', url: 'u', livello: 'universita', categoria: 'chiamata', scadenza: '2099-01-01T00:00:00.000Z', classiConcorso: [] },
        { titolo: 'Scuola A-28', url: 's', livello: 'scuola', categoria: 'supplenza', scadenza: '2099-01-01T00:00:00.000Z', classiConcorso: ['A-28'] },
        { titolo: 'Scaduto', url: 'x', livello: 'scuola', categoria: 'concorso', scadenza: '2020-01-01T00:00:00.000Z', classiConcorso: [] }
    ];

    assert.strictEqual(filterBandi(bandi, { livello: 'scuola', soloAperti: true }).length, 1);
    assert.strictEqual(filterBandi(bandi, { classe: 'a-28' }).length, 1);
    assert.strictEqual(filterBandi(bandi, { soloAperti: false }).length, 3);
    assert.strictEqual(filterBandi(bandi, { testo: 'uni' }).length, 1);
    assert.strictEqual(filterBandi(bandi, { entro: 30 }).length, 0);
});

test('sortBandi mette in cima le scadenze più vicine', () => {
    const ordinati = sortBandi([
        { titolo: 'c', scadenza: null, punteggio: 20 },
        { titolo: 'b', scadenza: '2026-12-01T00:00:00.000Z', punteggio: 1 },
        { titolo: 'a', scadenza: '2026-04-01T00:00:00.000Z', punteggio: 1 }
    ]);
    assert.deepStrictEqual(ordinati.map((b) => b.titolo), ['a', 'b', 'c']);
});

test('le fonti predefinite sono valide e filtrabili per livello', () => {
    const tutte = loadSources({ soloAttive: false });
    assert.ok(tutte.length >= 5);
    for (const fonte of tutte) {
        assert.ok(['rss', 'html', 'json'].includes(fonte.tipo));
        assert.doesNotThrow(() => new URL(fonte.url));
    }
    assert.ok(loadSources({ livello: 'scuola' }).every((f) => ['scuola', 'misto'].includes(f.livello)));
});

test('la CLI interpreta gli argomenti ed esporta in CSV', () => {
    const args = parseArgs(['run', '--livello', 'scuola', '--entro=30', '--approfondisci']);
    assert.deepStrictEqual(args._, ['run']);
    assert.strictEqual(args.livello, 'scuola');
    assert.strictEqual(args.entro, '30');
    assert.strictEqual(args.approfondisci, true);

    const csv = toCsv([{ id: '1', titolo: 'Bando, con virgola', classiConcorso: ['A-28'], url: 'https://x.it' }]);
    assert.match(csv.split('\n')[1], /"Bando, con virgola"/);
    assert.match(csv.split('\n')[1], /A-28/);
});

test('il digest raggruppa per livello e segnala i giorni residui', () => {
    const testo = digestTesto([
        { titolo: 'Uni', url: 'https://u.it', livello: 'universita', scadenza: '2099-01-01T00:00:00.000Z' },
        { titolo: 'Scuola', url: 'https://s.it', livello: 'scuola', scadenza: null }
    ]);
    assert.match(testo, /Università \/ AFAM \(1\)/);
    assert.match(testo, /Scuola \(1\)/);
    assert.match(testo, /scadenza non rilevata/);
});
