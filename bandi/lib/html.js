'use strict';

const { decodeEntities } = require('./feed');

/** Rimuove script/style e converte l'HTML in testo leggibile. */
function htmlToText(html) {
    return decodeEntities(
        String(html || '')
            .replace(/<(script|style|noscript)\b[\s\S]*?<\/\1>/gi, ' ')
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<\/(p|div|li|tr|h[1-6])>/gi, '\n')
            .replace(/<[^>]+>/g, ' ')
    )
        .replace(/[ \t ]+/g, ' ')
        .replace(/\n\s*\n\s*/g, '\n')
        .trim();
}

// Confini di blocco: il contesto di un link non deve sconfinare nella voce accanto,
// altrimenti un avviso per docenti eredita le parole di un bando di gara vicino.
const BLOCCO = /<\/?(?:li|tr|td|p|div|article|section|ul|ol|table|dl|dd|dt|h[1-6])\b[^>]*>/gi;

function contestoDelLink(html, inizioLink, fineLink, finestra = 220) {
    const daInizio = Math.max(0, inizioLink - finestra);
    const prima = html.slice(daInizio, inizioLink);
    const dopo = html.slice(fineLink, Math.min(html.length, fineLink + finestra));

    const confiniPrima = [...prima.matchAll(BLOCCO)];
    const ultimo = confiniPrima.length ? confiniPrima[confiniPrima.length - 1] : null;
    const testaPulita = ultimo ? prima.slice(ultimo.index + ultimo[0].length) : prima;

    BLOCCO.lastIndex = 0;
    const primoDopo = BLOCCO.exec(dopo);
    BLOCCO.lastIndex = 0;
    const codaPulita = primoDopo ? dopo.slice(0, primoDopo.index) : dopo;

    return htmlToText(`${testaPulita} ${codaPulita}`).replace(/\s+/g, ' ').trim();
}

function resolve(href, baseUrl) {
    try {
        return new URL(href, baseUrl).toString();
    } catch {
        return null;
    }
}

/**
 * Estrae i link di una pagina con il testo dell'ancora e un po' di contesto.
 * Approccio volutamente "selector-free": i siti di scuole, atenei e USR
 * cambiano struttura di continuo, mentre il link + il testo intorno
 * restano l'unico segnale stabile su cui filtrare.
 *
 * @returns {Array<{url:string, text:string, context:string}>}
 */
function extractLinks(html, baseUrl) {
    const source = String(html || '');
    const plain = source
        .replace(/<(script|style|noscript)\b[\s\S]*?<\/\1>/gi, ' ');

    const links = [];
    const seen = new Set();
    const pattern = /<a\b[^>]*href=["']([^"'#][^"']*)["'][^>]*>([\s\S]*?)<\/a>/gi;

    let match;
    while ((match = pattern.exec(plain)) !== null) {
        const href = decodeEntities(match[1].trim());
        if (/^(mailto:|tel:|javascript:|#)/i.test(href)) continue;

        const url = resolve(href, baseUrl);
        if (!url || !/^https?:/i.test(url)) continue;

        const text = htmlToText(match[2]).replace(/\s+/g, ' ').trim();

        const context = contestoDelLink(plain, match.index, pattern.lastIndex);

        const key = url + '|' + text.toLowerCase();
        if (seen.has(key)) continue;
        seen.add(key);

        links.push({ url, text, context });
    }

    return links;
}

/** Titolo della pagina, usato come fallback quando l'ancora è muta ("leggi tutto"). */
function pageTitle(html) {
    const match = String(html || '').match(/<title[^>]*>([\s\S]*?)<\/title>/i);
    return match ? htmlToText(match[1]).trim() : '';
}

module.exports = { htmlToText, extractLinks, pageTitle, resolve };
