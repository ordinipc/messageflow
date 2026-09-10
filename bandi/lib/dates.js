'use strict';

const MESI = {
    gennaio: 1, febbraio: 2, marzo: 3, aprile: 4, maggio: 5, giugno: 6,
    luglio: 7, agosto: 8, settembre: 9, ottobre: 10, novembre: 11, dicembre: 12,
    gen: 1, feb: 2, mar: 3, apr: 4, mag: 5, giu: 6, lug: 7, ago: 8, set: 9, sett: 9, ott: 10, nov: 11, dic: 12
};

function iso(year, month, day, hour = 0, minute = 0) {
    if (!year || !month || !day) return null;
    if (month < 1 || month > 12 || day < 1 || day > 31) return null;
    const date = new Date(Date.UTC(year, month - 1, day, hour, minute));
    if (Number.isNaN(date.getTime())) return null;
    if (date.getUTCMonth() !== month - 1) return null; // es. 31 febbraio
    return date.toISOString();
}

function normalizeYear(value) {
    const year = Number(value);
    if (year >= 1000) return year;
    if (year >= 0 && year < 100) return 2000 + year;
    return null;
}

/**
 * Interpreta le date nei formati usati dalla PA italiana:
 * "12 marzo 2026", "12/03/2026", "12-03-2026", "2026-03-12",
 * con eventuale orario ("ore 12:00", "23:59").
 *
 * @returns {string|null} data in formato ISO (UTC) oppure null
 */
function parseItalianDate(input) {
    const raw = String(input || '').trim();
    if (!raw) return null;

    // Date dei feed (RFC 822 / ISO 8601) con fuso orario: le interpreta Date,
    // altrimenti l'offset andrebbe perso e la data slitterebbe di un'ora.
    if (/\d{1,2}:\d{2}(?::\d{2})?\s*(?:z|gmt|utc|[+-]\d{2}:?\d{2})\b/i.test(raw)) {
        const conFuso = new Date(raw);
        if (!Number.isNaN(conFuso.getTime())) return conFuso.toISOString();
    }

    const text = raw.toLowerCase().replace(/\s+/g, ' ');

    const time = text.match(/(?:ore\s*)?\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/);
    const hour = time ? Number(time[1]) : 0;
    const minute = time ? Number(time[2]) : 0;

    let match = text.match(/\b(\d{1,2})\s*(?:°|º)?\s+(?:del\s+mese\s+di\s+)?([a-zà]+)\s+(\d{4})\b/);
    if (match && MESI[match[2]]) return iso(Number(match[3]), MESI[match[2]], Number(match[1]), hour, minute);

    match = text.match(/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/);
    if (match) return iso(Number(match[1]), Number(match[2]), Number(match[3]), hour, minute);

    match = text.match(/\b(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})\b/);
    if (match) {
        const year = normalizeYear(match[3]);
        return iso(year, Number(match[2]), Number(match[1]), hour, minute);
    }

    // Fallback: date RFC 822 / ISO dei feed RSS.
    const parsed = new Date(input);
    return Number.isNaN(parsed.getTime()) ? null : parsed.toISOString();
}

const DEADLINE_HINTS = [
    'scadenza', 'scade', 'scadono', 'entro e non oltre', 'entro le ore', 'entro il',
    'termine di presentazione', 'termine per la presentazione', 'termine ultimo',
    'presentazione delle domande', 'presentazione della domanda', 'domande entro'
];

/**
 * Cerca la data di scadenza in un testo libero: prende la data più vicina
 * a una delle espressioni tipiche dei bandi ("entro le ore 23:59 del ...").
 *
 * @returns {{deadline:string|null, evidence:string|null}}
 */
function extractDeadline(text, referenceDate = new Date()) {
    const source = String(text || '').replace(/\s+/g, ' ');
    if (!source) return { deadline: null, evidence: null };

    const lower = source.toLowerCase();
    const candidates = [];

    for (const hint of DEADLINE_HINTS) {
        let from = 0;
        for (;;) {
            const index = lower.indexOf(hint, from);
            if (index === -1) break;
            from = index + hint.length;
            const window = source.slice(index, Math.min(source.length, index + 140));
            const deadline = parseDateInWindow(window);
            if (deadline) candidates.push({ deadline, evidence: window.trim(), weight: hint.length });
        }
    }

    if (candidates.length === 0) return { deadline: null, evidence: null };

    const reference = referenceDate instanceof Date ? referenceDate : new Date(referenceDate);
    const future = candidates.filter((c) => new Date(c.deadline).getTime() >= reference.getTime() - 86400000);
    const pool = future.length > 0 ? future : candidates;

    pool.sort((a, b) => new Date(a.deadline) - new Date(b.deadline));
    return { deadline: pool[0].deadline, evidence: pool[0].evidence };
}

function parseDateInWindow(window) {
    const patterns = [
        /\b\d{1,2}\s+[a-zA-Zà]+\s+\d{4}\b(?:[^.]{0,30}?ore\s*\d{1,2}[:.]\d{2})?/,
        /\b\d{1,2}[/\-.]\d{1,2}[/\-.]\d{2,4}\b(?:[^.]{0,30}?ore\s*\d{1,2}[:.]\d{2})?/,
        /\b\d{4}-\d{1,2}-\d{1,2}\b/
    ];
    for (const pattern of patterns) {
        const match = window.match(pattern);
        if (!match) continue;
        // L'orario può precedere la data ("entro le ore 12:00 del 5 maggio 2026").
        const time = window.match(/ore\s*([01]?\d|2[0-3])[:.]([0-5]\d)/);
        const parsed = parseItalianDate(time ? `${match[0]} ore ${time[1]}:${time[2]}` : match[0]);
        if (parsed) return parsed;
    }
    return null;
}

function daysUntil(isoDate, referenceDate = new Date()) {
    if (!isoDate) return null;
    const target = new Date(isoDate).getTime();
    if (Number.isNaN(target)) return null;
    const reference = referenceDate instanceof Date ? referenceDate.getTime() : new Date(referenceDate).getTime();
    return Math.ceil((target - reference) / 86400000);
}

module.exports = { parseItalianDate, extractDeadline, daysUntil, MESI };
