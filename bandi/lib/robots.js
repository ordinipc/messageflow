'use strict';

/**
 * Parser minimale di robots.txt (RFC 9309, sottoinsieme utile).
 * Nessuna dipendenza esterna: gestisce i gruppi User-agent con
 * direttive Allow / Disallow e il match per prefisso più lungo.
 */

function parseRobots(text) {
    const groups = [];
    let current = null;
    let lastLineWasUserAgent = false;

    for (const rawLine of String(text || '').split(/\r?\n/)) {
        const line = rawLine.replace(/#.*$/, '').trim();
        if (!line) continue;

        const idx = line.indexOf(':');
        if (idx === -1) continue;

        const field = line.slice(0, idx).trim().toLowerCase();
        const value = line.slice(idx + 1).trim();

        if (field === 'user-agent') {
            if (!current || !lastLineWasUserAgent) {
                current = { agents: [], rules: [] };
                groups.push(current);
            }
            current.agents.push(value.toLowerCase());
            lastLineWasUserAgent = true;
            continue;
        }

        lastLineWasUserAgent = false;
        if (!current) continue;

        if (field === 'allow' || field === 'disallow') {
            // Un Disallow vuoto significa "tutto permesso": si ignora.
            if (field === 'disallow' && value === '') continue;
            current.rules.push({ allow: field === 'allow', path: value });
        } else if (field === 'crawl-delay') {
            const delay = Number(value.replace(',', '.'));
            if (Number.isFinite(delay)) current.crawlDelay = delay;
        }
    }

    return groups;
}

function matchLength(pattern, pathname) {
    // Supporta i wildcard * e l'ancoraggio finale $.
    if (!pattern.includes('*') && !pattern.endsWith('$')) {
        return pathname.startsWith(pattern) ? pattern.length : -1;
    }
    const anchored = pattern.endsWith('$');
    const body = anchored ? pattern.slice(0, -1) : pattern;
    const regex = new RegExp(
        '^' + body.split('*').map((part) => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('.*') + (anchored ? '$' : '')
    );
    return regex.test(pathname) ? body.length : -1;
}

function selectGroup(groups, userAgent) {
    const ua = String(userAgent || '*').toLowerCase();
    let best = null;
    let bestScore = -1;

    for (const group of groups) {
        for (const agent of group.agents) {
            let score = -1;
            if (agent === '*') score = 0;
            else if (ua.includes(agent)) score = agent.length;
            if (score > bestScore) {
                bestScore = score;
                best = group;
            }
        }
    }
    return best;
}

/**
 * @returns {boolean} true se l'URL può essere scaricato dal nostro crawler.
 */
function isAllowed(robotsText, url, userAgent) {
    const groups = parseRobots(robotsText);
    if (groups.length === 0) return true;

    const group = selectGroup(groups, userAgent);
    if (!group || group.rules.length === 0) return true;

    let pathname;
    try {
        const parsed = new URL(url);
        pathname = parsed.pathname + parsed.search;
    } catch {
        pathname = url;
    }

    let decision = true;
    let bestLength = -1;

    for (const rule of group.rules) {
        const length = matchLength(rule.path, pathname);
        if (length < 0) continue;
        // A parità di lunghezza vince Allow (comportamento Google).
        if (length > bestLength || (length === bestLength && rule.allow)) {
            bestLength = length;
            decision = rule.allow;
        }
    }

    return decision;
}

function crawlDelay(robotsText, userAgent) {
    const group = selectGroup(parseRobots(robotsText), userAgent);
    return group && Number.isFinite(group.crawlDelay) ? group.crawlDelay : null;
}

module.exports = { parseRobots, isAllowed, crawlDelay };
