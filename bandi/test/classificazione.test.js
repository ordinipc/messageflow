'use strict';

const test = require('node:test');
const assert = require('node:assert');

const { classify, detectEnte, extractClassiConcorso, detectRegione } = require('../lib/classify');

const pertinente = (title, context = '') => classify({ title, context }).pertinente;

test('riconosce i bandi universitari per docenti e ricercatori', () => {
    assert.ok(pertinente('Procedura valutativa per la chiamata di n. 1 professore di seconda fascia'));
    assert.ok(pertinente('Bando per n. 2 posti di ricercatore a tempo determinato lettera B'));
    assert.ok(pertinente('Selezione pubblica per il conferimento di un assegno di ricerca', 'Dipartimento di Fisica'));
    assert.ok(pertinente('Avviso per incarichi di insegnamento a contratto a.a. 2026/2027'));
});

test('riconosce i bandi della scuola', () => {
    assert.ok(pertinente('Avviso messa a disposizione MAD per supplenze', 'classe di concorso A-28'));
    assert.ok(pertinente('Concorso ordinario per il personale docente della scuola secondaria'));
    assert.ok(pertinente('Graduatorie provinciali per le supplenze GPS: avvio delle domande'));
});

test('scarta i bandi non riferiti all’insegnamento', () => {
    assert.ok(!pertinente('Concorso per 3 collaboratori scolastici', 'personale ATA'));
    assert.ok(!pertinente('Affidamento della fornitura di materiale informatico', 'gara di appalto'));
    assert.ok(!pertinente('Selezione per assistente amministrativo area B'));
    assert.ok(!pertinente('Avviso di manutenzione della palestra'));
});

test('distingue il livello scuola dal livello università', () => {
    assert.strictEqual(classify({ title: 'Chiamata di professore ordinario', context: 'Università degli Studi di Pisa' }).livello, 'universita');
    assert.strictEqual(classify({ title: 'Supplenze brevi', context: 'Istituto Comprensivo di Lecco - classe di concorso A-22' }).livello, 'scuola');
    assert.strictEqual(classify({ title: 'Avviso per docenti', context: '' }).livello, 'sconosciuto');
});

test('assegna la categoria corretta', () => {
    assert.strictEqual(classify({ title: 'Avviso MAD messa a disposizione docenti' }).categoria, 'supplenza');
    assert.strictEqual(classify({ title: 'Bando per assegno di ricerca in didattica della matematica' }).categoria, 'ricerca');
    assert.strictEqual(classify({ title: 'Concorso ordinario docenti scuola primaria' }).categoria, 'concorso');
    assert.strictEqual(classify({ title: 'Incarico di insegnamento a contratto di Analisi I' }).categoria, 'contratto');
});

test('il titolo pesa più del contesto', () => {
    const conTitolo = classify({ title: 'Selezione per docente di sostegno', context: '' }).punteggio;
    const conContesto = classify({ title: 'Avviso', context: 'Selezione per docente di sostegno' }).punteggio;
    assert.ok(conTitolo > conContesto, `${conTitolo} deve superare ${conContesto}`);
});

test('la soglia di pertinenza è configurabile', () => {
    const item = { title: 'Bando per attività didattica integrativa' };
    assert.ok(!classify(item, { soglia: 20 }).pertinente);
    assert.ok(classify(item, { soglia: 1 }).pertinente);
});

test('estrae ente, regione e classi di concorso', () => {
    assert.strictEqual(detectEnte('Bando dell’Università degli Studi di Padova per ricercatori'), 'Università degli Studi di Padova');
    assert.strictEqual(detectEnte('Avviso del Politecnico di Torino'), 'Politecnico di Torino');
    assert.deepStrictEqual(extractClassiConcorso('classi A-28, A-26 e B-16'), ['A-28', 'A-26', 'B-16']);
    assert.strictEqual(detectRegione('istituti della lombardia'), 'lombardia');
    assert.strictEqual(detectRegione('nessuna regione qui'), null);
});
