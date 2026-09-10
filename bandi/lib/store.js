'use strict';

const fs = require('fs');
const fsp = require('fs/promises');
const path = require('path');
const crypto = require('crypto');

const DEFAULT_FILE = process.env.BANDI_DB || path.join(__dirname, '..', 'data', 'bandi.json');

/** Chiave di deduplica: stesso URL (normalizzato) oppure stesso titolo dello stesso ente. */
function fingerprint(bando) {
    let canonicalUrl = String(bando.url || '');
    try {
        const url = new URL(canonicalUrl);
        url.hash = '';
        // I parametri di tracciamento non identificano il bando.
        for (const key of [...url.searchParams.keys()]) {
            if (/^(utm_|fbclid|gclid|_ga)/i.test(key)) url.searchParams.delete(key);
        }
        canonicalUrl = url.toString().replace(/\/$/, '');
    } catch {
        /* URL non parsabile: si usa la stringa così com'è */
    }

    const title = String(bando.titolo || '')
        .toLowerCase()
        .replace(/[^a-z0-9à-ù ]/gi, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    return crypto.createHash('sha1').update(`${canonicalUrl}|${title}`).digest('hex').slice(0, 16);
}

class Store {
    constructor(file = DEFAULT_FILE) {
        this.file = file;
        this.data = { version: 1, aggiornatoIl: null, bandi: {} };
    }

    async load() {
        try {
            const raw = await fsp.readFile(this.file, 'utf8');
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object' && parsed.bandi) this.data = parsed;
        } catch (error) {
            if (error.code !== 'ENOENT') throw error;
        }
        return this;
    }

    async save() {
        this.data.aggiornatoIl = new Date().toISOString();
        await fsp.mkdir(path.dirname(this.file), { recursive: true });
        const tmp = `${this.file}.tmp`;
        await fsp.writeFile(tmp, JSON.stringify(this.data, null, 2), 'utf8');
        await fsp.rename(tmp, this.file); // scrittura atomica: niente file troncati
        return this;
    }

    /**
     * Inserisce i bandi raccolti, distinguendo i nuovi dai già visti.
     * Su un bando già presente aggiorna i campi che possono cambiare
     * (scadenza scoperta in seguito, punteggio, fonti aggiuntive).
     *
     * @returns {{nuovi:Array, aggiornati:Array, invariati:number}}
     */
    upsertMany(bandi) {
        const nuovi = [];
        const aggiornati = [];
        let invariati = 0;

        for (const bando of bandi) {
            const id = bando.id || fingerprint(bando);
            const existing = this.data.bandi[id];

            if (!existing) {
                this.data.bandi[id] = {
                    ...bando,
                    id,
                    visitoIl: new Date().toISOString(),
                    fonti: [...new Set([bando.fonte].filter(Boolean))]
                };
                nuovi.push(this.data.bandi[id]);
                continue;
            }

            const merged = {
                ...existing,
                scadenza: bando.scadenza || existing.scadenza,
                scadenzaEvidenza: bando.scadenzaEvidenza || existing.scadenzaEvidenza,
                punteggio: Math.max(existing.punteggio || 0, bando.punteggio || 0),
                ente: existing.ente || bando.ente,
                regione: existing.regione || bando.regione,
                classiConcorso: [...new Set([...(existing.classiConcorso || []), ...(bando.classiConcorso || [])])],
                ssd: [...new Set([...(existing.ssd || []), ...(bando.ssd || [])])],
                fonti: [...new Set([...(existing.fonti || []), bando.fonte].filter(Boolean))],
                ultimoAvvistamento: new Date().toISOString()
            };

            const changed = JSON.stringify(merged) !== JSON.stringify(existing);
            this.data.bandi[id] = merged;
            if (changed) aggiornati.push(merged);
            else invariati++;
        }

        return { nuovi, aggiornati, invariati };
    }

    all() {
        return Object.values(this.data.bandi);
    }

    /** Rimuove i bandi scaduti da più di `giorni` e mai più riavvistati. */
    prune(giorni = 120) {
        const limite = Date.now() - giorni * 86400000;
        let rimossi = 0;
        for (const [id, bando] of Object.entries(this.data.bandi)) {
            const riferimento = new Date(bando.scadenza || bando.pubblicatoIl || bando.visitoIl || 0).getTime();
            if (Number.isFinite(riferimento) && riferimento > 0 && riferimento < limite) {
                delete this.data.bandi[id];
                rimossi++;
            }
        }
        return rimossi;
    }
}

function filterBandi(bandi, filtri = {}) {
    const { livello, categoria, regione, classe, testo, soloAperti, entro, minPunteggio } = filtri;
    const now = new Date();

    return bandi.filter((bando) => {
        if (livello && livello !== 'tutti' && bando.livello !== livello) return false;
        if (categoria && categoria !== 'tutte' && bando.categoria !== categoria) return false;
        if (regione && bando.regione !== regione) return false;
        if (classe && !(bando.classiConcorso || []).includes(String(classe).toUpperCase())) return false;
        if (Number.isFinite(minPunteggio) && (bando.punteggio || 0) < minPunteggio) return false;

        if (soloAperti && bando.scadenza && new Date(bando.scadenza) < now) return false;

        if (Number.isFinite(entro)) {
            if (!bando.scadenza) return false;
            const giorni = (new Date(bando.scadenza) - now) / 86400000;
            if (giorni < 0 || giorni > entro) return false;
        }

        if (testo) {
            const needle = String(testo).toLowerCase();
            const haystack = `${bando.titolo || ''} ${bando.ente || ''} ${bando.estratto || ''}`.toLowerCase();
            if (!haystack.includes(needle)) return false;
        }

        return true;
    });
}

function sortBandi(bandi) {
    return [...bandi].sort((a, b) => {
        const aDeadline = a.scadenza ? new Date(a.scadenza).getTime() : Infinity;
        const bDeadline = b.scadenza ? new Date(b.scadenza).getTime() : Infinity;
        if (aDeadline !== bDeadline) return aDeadline - bDeadline;
        return (b.punteggio || 0) - (a.punteggio || 0);
    });
}

module.exports = { Store, fingerprint, filterBandi, sortBandi, DEFAULT_FILE, storeExists: () => fs.existsSync(DEFAULT_FILE) };
