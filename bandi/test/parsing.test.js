'use strict';

const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const path = require('path');

const { parseFeed, looksLikeFeed } = require('../lib/feed');
const { extractLinks, htmlToText, pageTitle } = require('../lib/html');
const { parseItalianDate, extractDeadline, daysUntil } = require('../lib/dates');
const { isAllowed, crawlDelay } = require('../lib/robots');

const fixture = (name) => fs.readFileSync(path.join(__dirname, 'fixtures', name), 'utf8');

test('parseFeed legge titoli, link, date e CDATA di un feed della PA', () => {
    const feed = parseFeed(fixture('feed-gazzetta.xml'));
    assert.strictEqual(feed.title, 'Gazzetta Ufficiale - Concorsi ed Esami');
    assert.strictEqual(feed.items.length, 3);
    assert.match(feed.items[0].title, /ricercatore a tempo determinato/);
    assert.strictEqual(feed.items[0].link, 'https://example.gov.it/concorso/1');
    assert.match(feed.items[0].summary, /Università degli Studi di Bologna/);
});

test('parseFeed gestisce Atom con link rel=alternate', () => {
    const atom = `<feed xmlns="http://www.w3.org/2005/Atom">
        <title>Bandi</title>
        <entry>
            <title>Professore di seconda fascia</title>
            <link rel="self" href="https://x.it/self"/>
            <link rel="alternate" href="https://x.it/bando/9"/>
            <updated>2026-04-01T10:00:00Z</updated>
            <summary>Dipartimento di Matematica</summary>
        </entry>
    </feed>`;
    const feed = parseFeed(atom);
    assert.strictEqual(feed.items[0].link, 'https://x.it/bando/9');
    assert.strictEqual(feed.items[0].summary, 'Dipartimento di Matematica');
});

test('looksLikeFeed distingue XML e HTML', () => {
    assert.ok(looksLikeFeed('<?xml version="1.0"?><rss version="2.0"><channel/></rss>'));
    assert.ok(!looksLikeFeed('<!doctype html><html><body>ciao</body></html>'));
});

test('extractLinks risolve i relativi e scarta javascript:/mailto:', () => {
    const links = extractLinks(fixture('pagina-scuola.html'), 'https://icverdi.edu.it/albo/');
    const urls = links.map((l) => l.url);
    assert.ok(urls.includes('https://icverdi.edu.it/albo/mad-2026'));
    assert.ok(urls.includes('https://altro.it/ata'));
    assert.ok(!urls.some((u) => u.startsWith('javascript')));
});

test('extractLinks conserva il contesto attorno al link', () => {
    const links = extractLinks(fixture('pagina-scuola.html'), 'https://icverdi.edu.it/albo/');
    const mad = links.find((l) => l.url.endsWith('mad-2026'));
    assert.match(mad.context, /A-28/);
});

test('htmlToText e pageTitle ripuliscono la pagina', () => {
    const html = '<html><head><title>Albo</title><style>a{}</style></head><body><script>x=1</script><p>Ciao&nbsp;mondo</p></body></html>';
    assert.strictEqual(pageTitle(html), 'Albo');
    // Il testo del <title> resta nel corpo: aiuta a riconoscere l'ente nella pagina di dettaglio.
    assert.strictEqual(htmlToText(html).replace(/\s+/g, ' ').trim(), 'Albo Ciao mondo');
});

test('parseItalianDate copre i formati usati nei bandi', () => {
    assert.strictEqual(parseItalianDate('12 marzo 2026'), '2026-03-12T00:00:00.000Z');
    assert.strictEqual(parseItalianDate('12/03/2026'), '2026-03-12T00:00:00.000Z');
    assert.strictEqual(parseItalianDate('12-03-26'), '2026-03-12T00:00:00.000Z');
    assert.strictEqual(parseItalianDate('2026-03-12'), '2026-03-12T00:00:00.000Z');
    assert.strictEqual(parseItalianDate('5 maggio 2026 ore 23:59'), '2026-05-05T23:59:00.000Z');
    assert.strictEqual(parseItalianDate('31 febbraio 2026'), null);
    assert.strictEqual(parseItalianDate(''), null);
});

test('extractDeadline preferisce la data legata a una formula di scadenza', () => {
    const testo = 'Bando pubblicato il 1 marzo 2026. Le domande vanno presentate entro le ore 12:00 del 15/05/2026.';
    const { deadline, evidence } = extractDeadline(testo, new Date('2026-03-02'));
    assert.strictEqual(deadline, '2026-05-15T12:00:00.000Z');
    assert.match(evidence, /entro le ore/);
});

test('extractDeadline restituisce null se non ci sono formule di scadenza', () => {
    assert.strictEqual(extractDeadline('Avviso generico senza date').deadline, null);
});

test('extractDeadline sceglie la prima scadenza futura fra più candidate', () => {
    const testo = 'Termine ultimo 01/01/2020. Scadenza 10 giugno 2026. Scadenza 10 dicembre 2026.';
    const { deadline } = extractDeadline(testo, new Date('2026-01-01'));
    assert.strictEqual(deadline, '2026-06-10T00:00:00.000Z');
});

test('daysUntil calcola i giorni residui', () => {
    assert.strictEqual(daysUntil('2026-01-11T00:00:00.000Z', new Date('2026-01-01T00:00:00.000Z')), 10);
    assert.strictEqual(daysUntil(null), null);
});

test('robots.txt: Disallow, Allow più specifico e wildcard', () => {
    const robots = `User-agent: *
Disallow: /privato/
Allow: /privato/pubblico/
Disallow: /*.pdf$

User-agent: BandiScuolaBot
Disallow: /vietato/`;

    assert.ok(isAllowed(robots, 'https://x.it/albo/1', 'AltroBot'));
    assert.ok(!isAllowed(robots, 'https://x.it/privato/1', 'AltroBot'));
    assert.ok(isAllowed(robots, 'https://x.it/privato/pubblico/1', 'AltroBot'));
    assert.ok(!isAllowed(robots, 'https://x.it/doc/file.pdf', 'AltroBot'));
    // Il gruppo specifico per il nostro UA sostituisce quello generico.
    assert.ok(!isAllowed(robots, 'https://x.it/vietato/x', 'BandiScuolaBot/1.0'));
    assert.ok(isAllowed(robots, 'https://x.it/privato/1', 'BandiScuolaBot/1.0'));
});

test('robots.txt: nessuna regola significa accesso consentito', () => {
    assert.ok(isAllowed('', 'https://x.it/a', 'BandiScuolaBot'));
    assert.ok(isAllowed('User-agent: *\nDisallow:', 'https://x.it/a', 'BandiScuolaBot'));
});

test('crawlDelay viene letto quando presente', () => {
    assert.strictEqual(crawlDelay('User-agent: *\nCrawl-delay: 5', 'BandiScuolaBot'), 5);
    assert.strictEqual(crawlDelay('User-agent: *\nDisallow: /x', 'BandiScuolaBot'), null);
});
