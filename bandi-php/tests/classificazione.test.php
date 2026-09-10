<?php
function pertinente($titolo, $contesto = '')
{
    return BandiClassifier::classifica(['title' => $titolo, 'context' => $contesto])['pertinente'];
}

test('riconosce i bandi universitari per docenti e ricercatori', function () {
    assertVero(pertinente('Procedura valutativa per la chiamata di n. 1 professore di seconda fascia'));
    assertVero(pertinente('Bando per n. 2 posti di ricercatore a tempo determinato lettera B'));
    assertVero(pertinente('Selezione pubblica per il conferimento di un assegno di ricerca', 'Dipartimento di Fisica'));
    assertVero(pertinente('Avviso per incarichi di insegnamento a contratto a.a. 2026/2027'));
});

test('riconosce i bandi della scuola', function () {
    assertVero(pertinente('Avviso messa a disposizione MAD per supplenze', 'classe di concorso A-28'));
    assertVero(pertinente('Concorso ordinario per il personale docente della scuola secondaria'));
    assertVero(pertinente('Graduatorie provinciali per le supplenze GPS: avvio delle domande'));
});

test('scarta i bandi non riferiti all\'insegnamento', function () {
    assertVero(!pertinente('Concorso per 3 collaboratori scolastici', 'personale ATA'));
    assertVero(!pertinente('Affidamento della fornitura di materiale informatico', 'gara di appalto'));
    assertVero(!pertinente('Selezione per assistente amministrativo area B'));
    assertVero(!pertinente('Avviso di manutenzione della palestra'));
});

test('distingue il livello scuola dal livello università', function () {
    assertUguale('universita', BandiClassifier::classifica(['title' => 'Chiamata di professore ordinario', 'context' => 'Università degli Studi di Pisa'])['livello']);
    assertUguale('scuola', BandiClassifier::classifica(['title' => 'Supplenze brevi', 'context' => 'Istituto Comprensivo di Lecco - classe di concorso A-22'])['livello']);
    assertUguale('sconosciuto', BandiClassifier::classifica(['title' => 'Avviso per docenti'])['livello']);
});

test('assegna la categoria corretta', function () {
    assertUguale('supplenza', BandiClassifier::classifica(['title' => 'Avviso MAD messa a disposizione docenti'])['categoria']);
    assertUguale('ricerca', BandiClassifier::classifica(['title' => 'Bando per assegno di ricerca in didattica della matematica'])['categoria']);
    assertUguale('concorso', BandiClassifier::classifica(['title' => 'Concorso ordinario docenti scuola primaria'])['categoria']);
    assertUguale('contratto', BandiClassifier::classifica(['title' => 'Incarico di insegnamento a contratto di Analisi I'])['categoria']);
});

test('il titolo pesa più del contesto', function () {
    $conTitolo = BandiClassifier::classifica(['title' => 'Selezione per docente di sostegno'])['punteggio'];
    $conContesto = BandiClassifier::classifica(['title' => 'Avviso', 'context' => 'Selezione per docente di sostegno'])['punteggio'];
    assertVero($conTitolo > $conContesto, "{$conTitolo} deve superare {$conContesto}");
});

test('la soglia di pertinenza è configurabile', function () {
    $voce = ['title' => 'Bando per attività didattica integrativa'];
    assertVero(!BandiClassifier::classifica($voce, 20)['pertinente']);
    assertVero(BandiClassifier::classifica($voce, 1)['pertinente']);
});

test('estrae ente, regione, classi di concorso e SSD', function () {
    assertUguale('Università degli Studi di Padova', BandiClassifier::ente('Bando dell\'Università degli Studi di Padova per ricercatori'));
    assertUguale('Politecnico di Torino', BandiClassifier::ente('Avviso del Politecnico di Torino'));
    assertUguale('Istituto Comprensivo Verdi', BandiClassifier::ente('Istituto Comprensivo Verdi - classe A-28'));
    assertUguale('Università degli Studi di Bologna', BandiClassifier::ente('Università degli Studi di Bologna. Le domande...'));
    assertUguale(['A-28', 'A-26', 'B-16'], BandiClassifier::classiConcorso('classi A-28, A-26 e B-16'));
    assertUguale(['MAT/05'], BandiClassifier::ssd('settore MAT/05'));
    assertUguale('lombardia', BandiClassifier::regione('istituti della lombardia'));
    assertUguale(null, BandiClassifier::regione('nessuna regione qui'));
});

test('i punteggi coincidono con quelli della versione Node', function () {
    // Stessi casi della suite JavaScript: il porting non deve cambiare gli esiti.
    assertUguale(36, BandiClassifier::classifica([
        'title' => 'Procedura valutativa per la chiamata di n. 1 professore di seconda fascia',
        'context' => 'Università degli Studi di Padova - settore concorsuale 01/A2',
    ])['punteggio']);

    assertUguale(-24, BandiClassifier::classifica([
        'title' => 'Bando per collaboratore scolastico personale ATA',
        'context' => 'graduatoria di istituto',
    ])['punteggio']);
});
