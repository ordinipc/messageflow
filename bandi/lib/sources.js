'use strict';

const fs = require('fs');
const path = require('path');

const DEFAULT_PATH = path.join(__dirname, '..', 'sources.json');
const LOCAL_PATH = path.join(__dirname, '..', 'sources.local.json');

function readJson(file) {
    try {
        return JSON.parse(fs.readFileSync(file, 'utf8'));
    } catch (error) {
        if (error.code === 'ENOENT') return null;
        throw new Error(`File fonti non valido (${file}): ${error.message}`);
    }
}

function validate(source, index) {
    const where = source && source.id ? `fonte "${source.id}"` : `fonte #${index + 1}`;
    if (!source || typeof source !== 'object') throw new Error(`${where}: deve essere un oggetto`);
    if (!source.id) throw new Error(`${where}: campo "id" mancante`);
    if (!source.url) throw new Error(`${where}: campo "url" mancante`);
    if (!['rss', 'html', 'json'].includes(source.tipo)) {
        throw new Error(`${where}: "tipo" deve essere rss, html o json`);
    }
    try {
        new URL(source.url);
    } catch {
        throw new Error(`${where}: URL non valido (${source.url})`);
    }
    return {
        livello: 'misto',
        attiva: true,
        verificata: false,
        nome: source.id,
        ...source
    };
}

/**
 * Carica le fonti predefinite più quelle personali (sources.local.json,
 * non versionato: è lì che si aggiungono la propria scuola o il proprio ateneo).
 * Una fonte locale con lo stesso id sovrascrive quella predefinita.
 */
function loadSources(options = {}) {
    const base = readJson(options.file || DEFAULT_PATH) || { sources: [] };
    const local = readJson(options.localFile || LOCAL_PATH);

    const merged = new Map();
    for (const source of base.sources || []) merged.set(source.id, source);
    if (local) {
        for (const source of local.sources || []) {
            merged.set(source.id, { ...(merged.get(source.id) || {}), ...source });
        }
    }

    let sources = [...merged.values()].map(validate);

    if (options.soloAttive !== false) sources = sources.filter((s) => s.attiva !== false);
    if (options.ids && options.ids.length > 0) {
        const wanted = new Set(options.ids);
        sources = sources.filter((s) => wanted.has(s.id));
    }
    if (options.livello && options.livello !== 'tutti') {
        sources = sources.filter((s) => s.livello === options.livello || s.livello === 'misto');
    }

    return sources;
}

module.exports = { loadSources, DEFAULT_PATH, LOCAL_PATH };
