'use strict';

const fs = require('fs');
const fsp = require('fs/promises');
const path = require('path');
const crypto = require('crypto');
const { isAllowed, crawlDelay } = require('./robots');

const DEFAULT_UA =
    process.env.BANDI_USER_AGENT ||
    'BandiScuolaBot/1.0 (+aggregatore bandi scuola/universita; contatto: configurare BANDI_CONTACT)';

const CACHE_DIR = process.env.BANDI_CACHE_DIR || path.join(__dirname, '..', '.cache');

const lastRequestByHost = new Map();
const robotsByOrigin = new Map();

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function cacheKey(url) {
    return crypto.createHash('sha1').update(url).digest('hex');
}

async function readCache(url) {
    try {
        const raw = await fsp.readFile(path.join(CACHE_DIR, cacheKey(url) + '.json'), 'utf8');
        return JSON.parse(raw);
    } catch {
        return null;
    }
}

async function writeCache(url, entry) {
    try {
        await fsp.mkdir(CACHE_DIR, { recursive: true });
        await fsp.writeFile(path.join(CACHE_DIR, cacheKey(url) + '.json'), JSON.stringify(entry), 'utf8');
    } catch {
        /* la cache è best-effort: un errore di scrittura non deve fermare la raccolta */
    }
}

/** Attende quanto basta per rispettare l'intervallo minimo fra due richieste allo stesso host. */
async function throttle(host, minIntervalMs) {
    const last = lastRequestByHost.get(host) || 0;
    const wait = last + minIntervalMs - Date.now();
    if (wait > 0) await sleep(wait);
    lastRequestByHost.set(host, Date.now());
}

async function loadRobots(origin, { userAgent, timeoutMs }) {
    if (robotsByOrigin.has(origin)) return robotsByOrigin.get(origin);

    let text = '';
    try {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeoutMs);
        const response = await fetch(origin + '/robots.txt', {
            headers: { 'user-agent': userAgent, accept: 'text/plain,*/*' },
            signal: controller.signal,
            redirect: 'follow'
        });
        clearTimeout(timer);
        // 404 o 5xx: nessuna regola nota, si procede (comportamento standard).
        if (response.ok) text = await response.text();
    } catch {
        text = '';
    }

    robotsByOrigin.set(origin, text);
    return text;
}

class HttpError extends Error {
    constructor(message, code, status) {
        super(message);
        this.name = 'HttpError';
        this.code = code;
        this.status = status;
    }
}

/**
 * Scarica una risorsa testuale rispettando robots.txt, un intervallo minimo
 * fra richieste allo stesso host e le ETag (richieste condizionali).
 *
 * @returns {Promise<{url:string, body:string, status:number, notModified:boolean, fromCache:boolean}>}
 */
async function fetchText(url, options = {}) {
    const {
        userAgent = DEFAULT_UA,
        timeoutMs = 20000,
        retries = 2,
        minIntervalMs = Number(process.env.BANDI_MIN_INTERVAL_MS || 1500),
        respectRobots = process.env.BANDI_IGNORE_ROBOTS !== '1',
        useCache = true,
        accept = 'text/html,application/xhtml+xml,application/xml,application/rss+xml;q=0.9,*/*;q=0.8'
    } = options;

    const parsed = new URL(url);
    if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
        throw new HttpError(`Protocollo non supportato: ${parsed.protocol}`, 'BAD_PROTOCOL');
    }

    let hostMinInterval = minIntervalMs;

    if (respectRobots) {
        const robotsText = await loadRobots(parsed.origin, { userAgent, timeoutMs });
        if (!isAllowed(robotsText, url, userAgent)) {
            throw new HttpError(`robots.txt vieta l'accesso a ${url}`, 'ROBOTS_DISALLOWED');
        }
        const declaredDelay = crawlDelay(robotsText, userAgent);
        if (declaredDelay) hostMinInterval = Math.max(hostMinInterval, declaredDelay * 1000);
    }

    const cached = useCache ? await readCache(url) : null;
    let lastError = null;

    for (let attempt = 0; attempt <= retries; attempt++) {
        if (attempt > 0) await sleep(Math.min(8000, 1000 * 2 ** (attempt - 1)));
        await throttle(parsed.host, hostMinInterval);

        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeoutMs);
        try {
            const headers = { 'user-agent': userAgent, accept, 'accept-language': 'it-IT,it;q=0.9' };
            if (cached && cached.etag) headers['if-none-match'] = cached.etag;
            if (cached && cached.lastModified) headers['if-modified-since'] = cached.lastModified;

            const response = await fetch(url, { headers, signal: controller.signal, redirect: 'follow' });
            clearTimeout(timer);

            if (response.status === 304 && cached) {
                return { url: response.url || url, body: cached.body, status: 304, notModified: true, fromCache: true };
            }

            if (response.status === 429 || response.status >= 500) {
                lastError = new HttpError(`HTTP ${response.status} da ${url}`, 'HTTP_ERROR', response.status);
                continue;
            }

            if (!response.ok) {
                throw new HttpError(`HTTP ${response.status} da ${url}`, 'HTTP_ERROR', response.status);
            }

            const body = await response.text();
            if (useCache) {
                await writeCache(url, {
                    body,
                    etag: response.headers.get('etag') || null,
                    lastModified: response.headers.get('last-modified') || null,
                    fetchedAt: new Date().toISOString()
                });
            }
            return { url: response.url || url, body, status: response.status, notModified: false, fromCache: false };
        } catch (error) {
            clearTimeout(timer);
            if (error instanceof HttpError && error.code !== 'HTTP_ERROR') throw error;
            if (error instanceof HttpError && error.status && error.status < 500 && error.status !== 429) throw error;
            lastError = error;
        }
    }

    throw lastError || new HttpError(`Impossibile scaricare ${url}`, 'FETCH_FAILED');
}

function clearMemoryCaches() {
    lastRequestByHost.clear();
    robotsByOrigin.clear();
}

function cacheDir() {
    return CACHE_DIR;
}

function cacheExists() {
    return fs.existsSync(CACHE_DIR);
}

module.exports = { fetchText, HttpError, DEFAULT_UA, clearMemoryCaches, cacheDir, cacheExists };
