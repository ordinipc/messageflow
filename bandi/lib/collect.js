'use strict';

const { fetchText } = require('./http');
const { parseFeed, looksLikeFeed } = require('./feed');
const { extractLinks, pageTitle, htmlToText } = require('./html');
const { classify } = require('./classify');
const { extractDeadline, parseItalianDate } = require('./dates');
const { fingerprint } = require('./store');

/** Estrae dal JSON un array annidato seguendo un percorso tipo "data.items". */
function pick(object, pathString) {
    if (!pathString) return object;
    return pathString.split('.').reduce((acc, key) => (acc == null ? acc : acc[key]), object);
}

function candidatesFromFeed(body, source) {
    return parseFeed(body).items.map((item) => ({
        title: item.title,
        url: item.link,
        context: [item.summary, item.categories.join(' ')].filter(Boolean).join(' '),
        publishedAt: item.publishedAt ? parseItalianDate(item.publishedAt) : null
    }));
}

function candidatesFromHtml(body, source, finalUrl) {
    const title = pageTitle(body);
    return extractLinks(body, finalUrl || source.url).map((link) => ({
        // Alcuni portali usano ancore mute ("leggi tutto"): il contesto salva il titolo.
        title: link.text && link.text.length > 8 ? link.text : `${link.text || 'avviso'} — ${title}`.trim(),
        url: link.url,
        context: link.context,
        publishedAt: null
    }));
}

function candidatesFromJson(body, source) {
    let parsed;
    try {
        parsed = JSON.parse(body);
    } catch (error) {
        throw new Error(`risposta JSON non valida: ${error.message}`);
    }

    const rows = pick(parsed, source.jsonPath);
    if (!Array.isArray(rows)) {
        throw new Error(`jsonPath "${source.jsonPath || '(radice)'}" non punta a un array`);
    }

    const campi = source.campi || {};
    return rows.map((row) => {
        const rawUrl = campi.url ? pick(row, campi.url) : row.url;
        const url = source.urlTemplate && rawUrl != null
            ? source.urlTemplate.replace('{id}', String(rawUrl))
            : String(rawUrl || '');
        const publishedRaw = campi.data ? pick(row, campi.data) : row.data;

        return {
            title: String((campi.titolo ? pick(row, campi.titolo) : row.titolo) || ''),
            url,
            context: String((campi.descrizione ? pick(row, campi.descrizione) : row.descrizione) || ''),
            publishedAt: publishedRaw ? parseItalianDate(publishedRaw) : null,
            deadlineRaw: campi.scadenza ? pick(row, campi.scadenza) : row.scadenza
        };
    });
}

function toBando(candidate, source, verdict, now) {
    const testo = `${candidate.title} ${candidate.context || ''}`;
    const fromField = candidate.deadlineRaw ? parseItalianDate(candidate.deadlineRaw) : null;
    const dedotta = fromField ? { deadline: fromField, evidence: 'campo scadenza della fonte' } : extractDeadline(testo, now);

    const bando = {
        titolo: candidate.title.replace(/\s+/g, ' ').trim(),
        url: candidate.url,
        fonte: source.id,
        fonteNome: source.nome || source.id,
        livello: verdict.livello !== 'sconosciuto' ? verdict.livello : (source.livello === 'misto' ? 'sconosciuto' : source.livello),
        categoria: verdict.categoria,
        ente: verdict.ente,
        regione: verdict.regione,
        classiConcorso: verdict.classiConcorso,
        ssd: verdict.ssd,
        punteggio: verdict.punteggio,
        pubblicatoIl: candidate.publishedAt || null,
        scadenza: dedotta.deadline,
        scadenzaEvidenza: dedotta.evidence,
        estratto: (candidate.context || '').slice(0, 400),
        raccoltoIl: now.toISOString()
    };

    bando.id = fingerprint(bando);
    return bando;
}

async function fetchSource(source, options) {
    const response = await fetchText(source.url, {
        timeoutMs: options.timeoutMs,
        useCache: options.useCache,
        respectRobots: options.respectRobots,
        minIntervalMs: options.minIntervalMs,
        accept: source.tipo === 'json' ? 'application/json,*/*;q=0.8' : undefined
    });

    let candidates;
    if (source.tipo === 'json') {
        candidates = candidatesFromJson(response.body, source);
    } else if (source.tipo === 'rss' || looksLikeFeed(response.body)) {
        candidates = candidatesFromFeed(response.body, source);
    } else {
        candidates = candidatesFromHtml(response.body, source, response.url);
    }

    return { response, candidates };
}

/**
 * Scarica una singola fonte e restituisce i bandi pertinenti.
 *
 * @returns {Promise<{fonte:string, bandi:Array, esaminati:number, errore:string|null}>}
 */
async function collectSource(source, options = {}) {
    const now = options.now || new Date();
    const soglia = options.soglia;

    try {
        const { candidates } = await fetchSource(source, options);

        const bandi = [];
        const visti = new Set();

        for (const candidate of candidates) {
            if (!candidate.url || !candidate.title) continue;
            const verdict = classify(candidate, { soglia });
            if (!verdict.pertinente) continue;

            const bando = toBando(candidate, source, verdict, now);
            if (visti.has(bando.id)) continue;
            visti.add(bando.id);
            bandi.push(bando);
        }

        bandi.sort((a, b) => b.punteggio - a.punteggio);
        const limitati = options.maxPerFonte ? bandi.slice(0, options.maxPerFonte) : bandi;

        if (options.approfondisci) {
            await arricchisci(limitati, options);
        }

        return { fonte: source.id, nome: source.nome, bandi: limitati, esaminati: candidates.length, errore: null };
    } catch (error) {
        return { fonte: source.id, nome: source.nome, bandi: [], esaminati: 0, errore: error.message };
    }
}

/**
 * Per i bandi senza scadenza apre la pagina di dettaglio e la rilegge.
 * Limitato di default a poche pagine per fonte: è la parte più costosa
 * e non deve trasformare l'aggregatore in un crawler aggressivo.
 */
async function arricchisci(bandi, options) {
    const massimo = Number.isFinite(options.maxDettagli) ? options.maxDettagli : 5;
    let fatti = 0;

    for (const bando of bandi) {
        if (fatti >= massimo) break;
        if (bando.scadenza) continue;
        if (/\.(pdf|zip|doc|docx|xls|xlsx|p7m)(\?|$)/i.test(bando.url)) continue;

        try {
            const response = await fetchText(bando.url, {
                timeoutMs: options.timeoutMs,
                useCache: options.useCache,
                respectRobots: options.respectRobots,
                minIntervalMs: options.minIntervalMs
            });
            const testo = htmlToText(response.body).slice(0, 20000);
            const { deadline, evidence } = extractDeadline(testo, options.now || new Date());
            if (deadline) {
                bando.scadenza = deadline;
                bando.scadenzaEvidenza = evidence;
            }
            if (!bando.ente || !bando.regione) {
                const verdict = classify({ title: bando.titolo, context: testo.slice(0, 3000) }, { soglia: options.soglia });
                bando.ente = bando.ente || verdict.ente;
                bando.regione = bando.regione || verdict.regione;
                if (bando.livello === 'sconosciuto' && verdict.livello !== 'sconosciuto') bando.livello = verdict.livello;
            }
        } catch {
            /* la pagina di dettaglio è un extra: se non risponde si tiene il bando così com'è */
        }
        fatti++;
    }
}

/** Raccoglie da tutte le fonti indicate, in sequenza (una fonte per volta, per non sovraccaricare i portali). */
async function collectAll(sources, options = {}) {
    const risultati = [];
    for (const source of sources) {
        const risultato = await collectSource(source, options);
        risultati.push(risultato);
        if (typeof options.onSource === 'function') options.onSource(risultato);
    }

    const bandi = risultati.flatMap((r) => r.bandi);
    return { risultati, bandi };
}

/** Diagnostica una fonte senza classificarla: serve a capire se l'URL è ancora valido. */
async function doctorSource(source, options = {}) {
    const started = Date.now();
    try {
        const { response, candidates } = await fetchSource(source, { ...options, useCache: false });
        const pertinenti = candidates.filter((c) => c.url && c.title && classify(c, { soglia: options.soglia }).pertinente);

        return {
            fonte: source.id,
            nome: source.nome,
            ok: candidates.length > 0,
            status: response.status,
            urlFinale: response.url,
            voci: candidates.length,
            pertinenti: pertinenti.length,
            esempi: pertinenti.slice(0, 3).map((c) => c.title.slice(0, 90)),
            ms: Date.now() - started,
            errore: candidates.length === 0 ? 'nessuna voce estratta: struttura della pagina cambiata o contenuto caricato via JavaScript' : null
        };
    } catch (error) {
        return {
            fonte: source.id,
            nome: source.nome,
            ok: false,
            status: error.status || null,
            voci: 0,
            pertinenti: 0,
            esempi: [],
            ms: Date.now() - started,
            errore: error.message
        };
    }
}

module.exports = { collectSource, collectAll, doctorSource, candidatesFromFeed, candidatesFromHtml, candidatesFromJson, toBando };
