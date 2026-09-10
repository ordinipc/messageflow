'use strict';

/**
 * Parser RSS 2.0 / Atom senza dipendenze.
 * Volutamente tollerante: i feed della PA italiana sono spesso
 * malformati (entity non codificate, namespace misti, CDATA annidati).
 */

const NAMED_ENTITIES = {
    amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ',
    egrave: 'è', eacute: 'é', agrave: 'à', igrave: 'ì', ograve: 'ò', ugrave: 'ù',
    Egrave: 'È', Agrave: 'À', laquo: '«', raquo: '»', deg: '°', euro: '€', hellip: '…',
    ndash: '–', mdash: '—', rsquo: '’', lsquo: '‘', ldquo: '“', rdquo: '”'
};

function decodeEntities(text) {
    return String(text || '')
        .replace(/&#x([0-9a-fA-F]+);/g, (_, hex) => String.fromCodePoint(parseInt(hex, 16)))
        .replace(/&#(\d+);/g, (_, dec) => String.fromCodePoint(parseInt(dec, 10)))
        .replace(/&([a-zA-Z]+);/g, (match, name) =>
            Object.prototype.hasOwnProperty.call(NAMED_ENTITIES, name) ? NAMED_ENTITIES[name] : match
        );
}

function stripCdata(text) {
    return String(text || '').replace(/<!\[CDATA\[([\s\S]*?)\]\]>/g, '$1');
}

function clean(text) {
    return decodeEntities(stripCdata(text))
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function tagName(name) {
    // "dc:date" e "date" devono corrispondere entrambi.
    return name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function firstTag(xml, name) {
    const pattern = new RegExp(`<(?:[a-zA-Z0-9_-]+:)?${tagName(name)}(\\s[^>]*)?>([\\s\\S]*?)</(?:[a-zA-Z0-9_-]+:)?${tagName(name)}>`, 'i');
    const match = xml.match(pattern);
    return match ? match[2] : null;
}

function attribute(xml, name, attr) {
    const pattern = new RegExp(`<(?:[a-zA-Z0-9_-]+:)?${tagName(name)}\\s[^>]*${tagName(attr)}=["']([^"']+)["'][^>]*/?>`, 'i');
    const match = xml.match(pattern);
    return match ? decodeEntities(match[1]) : null;
}

function blocks(xml, name) {
    const pattern = new RegExp(`<(?:[a-zA-Z0-9_-]+:)?${tagName(name)}(?:\\s[^>]*)?>([\\s\\S]*?)</(?:[a-zA-Z0-9_-]+:)?${tagName(name)}>`, 'gi');
    const out = [];
    let match;
    while ((match = pattern.exec(xml)) !== null) out.push(match[1]);
    return out;
}

function entryLink(block) {
    const link = firstTag(block, 'link');
    if (link && clean(link)) return clean(link);

    // Atom: <link rel="alternate" href="..."/>
    const hrefs = [...block.matchAll(/<(?:[a-zA-Z0-9_-]+:)?link\b[^>]*>/gi)].map((m) => m[0]);
    for (const tag of hrefs) {
        if (/rel=["']?(?!alternate)(self|edit|replies|enclosure)/i.test(tag)) continue;
        const href = tag.match(/href=["']([^"']+)["']/i);
        if (href) return decodeEntities(href[1]);
    }

    const guid = firstTag(block, 'guid');
    const guidText = guid ? clean(guid) : '';
    return /^https?:\/\//i.test(guidText) ? guidText : '';
}

/**
 * @param {string} xml contenuto del feed
 * @returns {{title:string, items:Array<{title:string,link:string,summary:string,publishedAt:string|null,guid:string|null,categories:string[]}>}}
 */
function parseFeed(xml) {
    const text = String(xml || '');
    const channel = firstTag(text, 'channel') || text;
    const feedTitle = clean(firstTag(channel, 'title') || '');

    const rawItems = [...blocks(text, 'item'), ...blocks(text, 'entry')];

    const items = rawItems.map((block) => {
        const published =
            firstTag(block, 'pubDate') ||
            firstTag(block, 'published') ||
            firstTag(block, 'updated') ||
            firstTag(block, 'date') ||
            null;

        const summary =
            firstTag(block, 'description') ||
            firstTag(block, 'summary') ||
            firstTag(block, 'content') ||
            '';

        return {
            title: clean(firstTag(block, 'title') || ''),
            link: entryLink(block),
            summary: clean(summary),
            publishedAt: published ? clean(published) : null,
            guid: clean(firstTag(block, 'guid') || firstTag(block, 'id') || '') || null,
            categories: blocks(block, 'category').map(clean).filter(Boolean).concat(
                [...block.matchAll(/<category\b[^>]*term=["']([^"']+)["']/gi)].map((m) => decodeEntities(m[1]))
            )
        };
    });

    return { title: feedTitle, items: items.filter((item) => item.title || item.link) };
}

function looksLikeFeed(text) {
    return /<rss[\s>]|<feed[\s>]|<rdf:RDF[\s>]/i.test(String(text || '').slice(0, 2000));
}

module.exports = { parseFeed, looksLikeFeed, decodeEntities, clean, firstTag, attribute };
