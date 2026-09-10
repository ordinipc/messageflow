<?php
test('parseFeed legge titoli, link, date e CDATA di un feed della PA', function () {
    $feed = BandiFeed::parse(fixture('feed-gazzetta.xml'));
    assertUguale('Gazzetta Ufficiale - Concorsi ed Esami', $feed['title']);
    assertUguale(3, count($feed['items']));
    assertContiene('ricercatore a tempo determinato', $feed['items'][0]['title']);
    assertUguale('https://example.gov.it/concorso/1', $feed['items'][0]['link']);
    assertContiene('Università degli Studi di Bologna', $feed['items'][0]['summary']);
});

test('parseFeed gestisce Atom con link rel=alternate', function () {
    $atom = '<feed xmlns="http://www.w3.org/2005/Atom"><entry>
        <title>Professore di seconda fascia</title>
        <link rel="self" href="https://x.it/self"/>
        <link rel="alternate" href="https://x.it/bando/9"/>
        <updated>2026-04-01T10:00:00Z</updated>
        <summary>Dipartimento di Matematica</summary>
    </entry></feed>';
    $feed = BandiFeed::parse($atom);
    assertUguale('https://x.it/bando/9', $feed['items'][0]['link']);
    assertUguale('Dipartimento di Matematica', $feed['items'][0]['summary']);
});

test('sembraUnFeed distingue XML e HTML', function () {
    assertVero(BandiFeed::sembraUnFeed('<?xml version="1.0"?><rss version="2.0"><channel/></rss>'));
    assertVero(!BandiFeed::sembraUnFeed('<!doctype html><html><body>ciao</body></html>'));
});

test('estraiLink risolve i relativi e scarta javascript:/mailto:', function () {
    $link = BandiHtml::estraiLink(fixture('pagina-scuola.html'), 'https://icverdi.edu.it/albo/');
    $url = array_column($link, 'url');
    assertVero(in_array('https://icverdi.edu.it/albo/mad-2026', $url, true), 'link relativo non risolto');
    assertVero(in_array('https://altro.it/ata', $url, true), 'link assoluto mancante');
    foreach ($url as $u) {
        assertVero(strpos($u, 'javascript') !== 0, 'javascript: non deve passare');
    }
});

test('il contesto del link non sconfina nella voce accanto', function () {
    $link = BandiHtml::estraiLink(fixture('pagina-scuola.html'), 'https://icverdi.edu.it/albo/');
    $indice = array_search('https://icverdi.edu.it/albo/mad-2026', array_column($link, 'url'), true);
    assertContiene('A-28', $link[$indice]['context']);
    assertVero(mb_strpos($link[$indice]['context'], 'fornitura') === false, 'il contesto ha inglobato la voce successiva');
});

test('risolvi normalizza i percorsi relativi', function () {
    assertUguale('https://a.it/b/x/y.html', BandiHtml::risolvi('../x/y.html', 'https://a.it/b/c/d.html'));
    assertUguale('https://a.it/x', BandiHtml::risolvi('/x', 'https://a.it/b/c/'));
    assertUguale(null, BandiHtml::risolvi('mailto:a@b.it', 'https://a.it/'));
});

test('toText e titoloPagina ripuliscono la pagina', function () {
    $html = '<html><head><title>Albo</title><style>a{}</style></head><body><script>x=1</script><p>Ciao&nbsp;mondo</p></body></html>';
    assertUguale('Albo', BandiHtml::titoloPagina($html));
    assertUguale('Albo Ciao mondo', trim(preg_replace('/\s+/u', ' ', BandiHtml::toText($html))));
});

test('le date italiane vengono interpretate nei formati usati dai bandi', function () {
    assertUguale('2026-03-12T00:00:00Z', BandiDates::parse('12 marzo 2026'));
    assertUguale('2026-03-12T00:00:00Z', BandiDates::parse('12/03/2026'));
    assertUguale('2026-03-12T00:00:00Z', BandiDates::parse('12-03-26'));
    assertUguale('2026-03-12T00:00:00Z', BandiDates::parse('2026-03-12'));
    assertUguale('2026-05-05T23:59:00Z', BandiDates::parse('5 maggio 2026 ore 23:59'));
    assertUguale(null, BandiDates::parse('31 febbraio 2026'));
    assertUguale(null, BandiDates::parse(''));
});

test('le date dei feed conservano il fuso orario', function () {
    assertUguale('2026-03-10T08:00:00Z', BandiDates::parse('Tue, 10 Mar 2026 09:00:00 +0100'));
    assertUguale('2026-04-01T10:00:00Z', BandiDates::parse('2026-04-01T10:00:00Z'));
});

test('extractDeadline preferisce la data legata a una formula di scadenza', function () {
    $testo = 'Bando pubblicato il 1 marzo 2026. Le domande vanno presentate entro le ore 12:00 del 15/05/2026.';
    $esito = BandiDates::extractDeadline($testo, '2026-03-02');
    assertUguale('2026-05-15T12:00:00Z', $esito['deadline']);
    assertContiene('entro le ore', $esito['evidence']);
});

test('extractDeadline restituisce null senza formule di scadenza', function () {
    assertUguale(null, BandiDates::extractDeadline('Avviso generico senza date')['deadline']);
});

test('extractDeadline sceglie la prima scadenza futura', function () {
    $testo = 'Termine ultimo 01/01/2020. Scadenza 10 giugno 2026. Scadenza 10 dicembre 2026.';
    assertUguale('2026-06-10T00:00:00Z', BandiDates::extractDeadline($testo, '2026-01-01')['deadline']);
});

test('robots.txt: Disallow, Allow più specifico, wildcard e gruppo per agente', function () {
    $robots = "User-agent: *\nDisallow: /privato/\nAllow: /privato/pubblico/\nDisallow: /*.pdf$\n\nUser-agent: BandiScuolaBot\nDisallow: /vietato/";

    assertVero(BandiRobots::isAllowed($robots, 'https://x.it/albo/1', 'AltroBot'));
    assertVero(!BandiRobots::isAllowed($robots, 'https://x.it/privato/1', 'AltroBot'));
    assertVero(BandiRobots::isAllowed($robots, 'https://x.it/privato/pubblico/1', 'AltroBot'));
    assertVero(!BandiRobots::isAllowed($robots, 'https://x.it/doc/file.pdf', 'AltroBot'));
    assertVero(!BandiRobots::isAllowed($robots, 'https://x.it/vietato/x', 'BandiScuolaBot/1.0'));
    assertVero(BandiRobots::isAllowed($robots, 'https://x.it/privato/1', 'BandiScuolaBot/1.0'));
});

test('robots.txt: nessuna regola significa accesso consentito', function () {
    assertVero(BandiRobots::isAllowed('', 'https://x.it/a', 'BandiScuolaBot'));
    assertVero(BandiRobots::isAllowed("User-agent: *\nDisallow:", 'https://x.it/a', 'BandiScuolaBot'));
    assertUguale(5.0, BandiRobots::crawlDelay("User-agent: *\nCrawl-delay: 5", 'BandiScuolaBot'));
});
