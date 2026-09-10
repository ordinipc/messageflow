'use strict';

/**
 * Riconoscimento e classificazione dei bandi per personale docente
 * di scuole e università italiane.
 *
 * Il filtro è puramente lessicale (nessun modello esterno, nessuna chiave API):
 * ogni voce raccolta viene valutata su titolo + contesto e riceve un punteggio.
 * Sopra la soglia il bando è considerato pertinente.
 */

const norm = (text) =>
    String(text || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/\s+/g, ' ')
        .trim();

// Segnali che indicano un incarico di insegnamento/ricerca (punteggio positivo).
const SEGNALI_DOCENZA = [
    [/\bprofessor(?:e|essa|i)\b/, 5],
    [/\bprofessore (?:ordinario|associato|di (?:prima|i|seconda|ii) fascia)\b/, 7],
    [/\bdocent(?:e|i|a)\b/, 4],
    [/\binsegnant(?:e|i)\b/, 5],
    [/\bcattedr(?:a|e)\b/, 4],
    [/\bricercator(?:e|i)\b/, 5],
    [/\brtd[ab]?\b|\brtt\b|\bricercatore a tempo determinato\b/, 6],
    [/\bassegn(?:o|i) di ricerca\b|\bassegnist(?:a|i)\b/, 4],
    [/\bdottorat(?:o|i) di ricerca\b/, 2],
    [/\bsupplenz(?:a|e)\b|\bsupplent(?:e|i)\b/, 5],
    [/\bmessa a disposizione\b|\bm\.a\.d\.\b|\bmad\b/, 5],
    [/\bclasse di concorso\b|\b[ab]-\d{2}\b/, 5],
    [/\bgps\b|\bgraduatorie provinciali per le supplenze\b/, 4],
    [/\bincarico di insegnamento\b|\bincarichi di insegnamento\b/, 6],
    [/\bcontratt(?:o|i) di docenza\b|\bdocenza a contratto\b|\bdocente a contratto\b/, 6],
    [/\battivita didattica\b|\battivita di docenza\b|\bcarico didattico\b/, 3],
    [/\bespert(?:o|i)\b.*\b(?:pnrr|formazione|corso|percorso)\b/, 2],
    [/\btutor\b.*\b(?:pnrr|didattic|orientament)/, 2],
    [/\bcultore della materia\b/, 3],
    [/\bconcorso (?:ordinario|straordinario)\b.*\bdocent/, 6],
    [/\bpersonale docente\b/, 5],
    [/\bselezione pubblica\b.*\b(?:docenz|insegnament|professor|ricercator)/, 5],
    [/\bprocedura (?:comparativa|di selezione|valutativa|di chiamata)\b.*\b(?:professor|ricercator|docent)/, 6],
    [/\bchiamata\b.*\b(?:art\.? ?18|art\.? ?24)\b/, 4]
];

// Segnali che indicano ruoli NON docenti (punteggio negativo).
const SEGNALI_ESCLUSIONE = [
    [/\bpersonale ata\b/, -6],
    [/\bcollaborator(?:e|i) scolastic(?:o|i)\b/, -6],
    [/\bassistente amministrativ/, -5],
    [/\bassistente tecnic/, -4],
    [/\bdirettore dei servizi generali\b|\bdsga\b/, -5],
    [/\bpersonale tecnico[- ]amministrativo\b|\bcategoria [bcd]\b/, -4],
    [/\bdirigente scolastic/, -3],
    [/\boperatore socio[- ]sanitario\b|\binfermier/, -5],
    [/\bautist(?:a|i)\b|\bcuoc(?:o|hi)\b|\bmanutentore\b/, -5],
    [/\bagente di polizia\b|\bvigil(?:e|i) urban/, -6],
    [/\bfornitura\b|\baffidamento (?:del )?servizio\b|\bappalto\b|\bgara\b/, -4],
    [/\bnoleggio\b|\bacquisto\b|\bmanutenzione\b/, -4],
    [/\bborsa di studio\b(?!.*docen)/, -1]
];

const SEGNALI_UNIVERSITA = [
    /\buniversita\b/, /\bateneo\b/, /\bpolitecnico\b/, /\bdipartimento di\b/,
    /\bprofessore (?:ordinario|associato)\b/, /\brtd[ab]?\b|\brtt\b/, /\bassegno di ricerca\b/,
    /\bsettore (?:scientifico[- ]disciplinare|concorsuale)\b/, /\bssd\b/, /\bafam\b/,
    /\bconservatorio\b/, /\baccademia di belle arti\b/, /\bscuola superiore\b.*\bstudi\b/
];

const SEGNALI_SCUOLA = [
    /\bistituto comprensivo\b/, /\bistituto (?:tecnico|professionale|superiore)\b/, /\bliceo\b/,
    /\bscuola (?:dell'infanzia|primaria|secondaria|paritaria|statale)\b/, /\bdirezione didattica\b/,
    /\bclasse di concorso\b/, /\bsupplenz/, /\bmessa a disposizione\b/, /\bgps\b/,
    /\bufficio scolastico (?:regionale|provinciale|territoriale)\b/, /\bambito territoriale\b/,
    /\bconcorso (?:ordinario|straordinario)\b/, /\bposto comune\b/, /\bsostegno\b/
];

const CATEGORIE = [
    ['supplenza', /\bsupplenz|\bmessa a disposizione\b|\bmad\b|\bgps\b|\bgraduatori(?:a|e) d'istituto\b/],
    ['concorso', /\bconcorso\b|\bconcorsi\b|\bprova (?:scritta|orale)\b/],
    ['ricerca', /\bassegno di ricerca\b|\bricercator|\brtd[ab]?\b|\brtt\b|\bborsa di ricerca\b/],
    ['chiamata', /\bprocedura (?:valutativa|di chiamata)\b|\bart\.? ?(?:18|24)\b|\btrasferiment|\bmobilita\b/],
    ['contratto', /\bdocenza a contratto\b|\bincarico di insegnamento\b|\bcontratt(?:o|i) di docenza\b|\bcollaborazione\b/],
    ['formazione', /\bcorso di formazione\b|\bpercorso formativo\b|\bpnrr\b|\btutor\b|\bespert/]
];

const REGIONI = [
    'abruzzo', 'basilicata', 'calabria', 'campania', 'emilia-romagna', 'emilia romagna',
    'friuli-venezia giulia', 'friuli venezia giulia', 'lazio', 'liguria', 'lombardia', 'marche',
    'molise', 'piemonte', 'puglia', 'sardegna', 'sicilia', 'toscana', 'trentino-alto adige',
    'trentino alto adige', 'umbria', "valle d'aosta", 'veneto'
];

const CLASSE_CONCORSO = /\b([AB])-(\d{2})\b/gi;
const SSD = /\b([A-Z]{3,6}-\d{2}\/[A-Z]|[A-Z]{3,4}-\d{2}\/[A-Z]{1,2}|\b(?:MAT|FIS|CHIM|INF|ING-INF|ING-IND|L-LIN|L-FIL-LET|M-PED|M-PSI|SECS-P|IUS|BIO|MED)\/\d{2})\b/g;

function scoreText(text) {
    let score = 0;
    const matched = [];

    for (const [pattern, weight] of SEGNALI_DOCENZA) {
        if (pattern.test(text)) {
            score += weight;
            matched.push(pattern.source);
        }
    }
    for (const [pattern, weight] of SEGNALI_ESCLUSIONE) {
        if (pattern.test(text)) {
            score += weight;
            matched.push(pattern.source);
        }
    }
    return { score, matched };
}

function detectLivello(text) {
    const uni = SEGNALI_UNIVERSITA.filter((p) => p.test(text)).length;
    const scuola = SEGNALI_SCUOLA.filter((p) => p.test(text)).length;
    if (uni === 0 && scuola === 0) return 'sconosciuto';
    if (uni > scuola) return 'universita';
    if (scuola > uni) return 'scuola';
    return 'sconosciuto';
}

function detectCategoria(text) {
    for (const [name, pattern] of CATEGORIE) {
        if (pattern.test(text)) return name;
    }
    return 'altro';
}

function detectRegione(text) {
    for (const regione of REGIONI) {
        if (text.includes(regione)) return regione.replace(/\s/g, '-');
    }
    return null;
}

function detectEnte(originalText) {
    const patterns = [
        /Universit[àa](?:\s+degli\s+Studi)?(?:\s+(?:di|del|della|dell'|per))?\s+[A-ZÀ-Ù][\w'’.-]*(?:\s+[A-ZÀ-Ù][\w'’.-]*){0,3}/,
        /Politecnico\s+di\s+[A-ZÀ-Ù][\w'’.-]*/,
        /Istituto\s+(?:Comprensivo|Tecnico|Professionale|Superiore|d'Istruzione\s+Superiore)[\s\w'’.-]{0,40}/i,
        /Liceo\s+[\w'’.-]+(?:\s+[\w'’.-]+){0,3}/i,
        /Ufficio\s+Scolastico\s+(?:Regionale|Provinciale|Territoriale)[\s\w'’.-]{0,30}/i,
        /Conservatorio(?:\s+(?:di|statale))?\s+[\w'’.-]+(?:\s+[\w'’.-]+){0,3}/i,
        /Accademia\s+di\s+Belle\s+Arti[\s\w'’.-]{0,30}/i
    ];
    for (const pattern of patterns) {
        const match = String(originalText || '').match(pattern);
        if (match) {
            // Il match può sbordare oltre il nome dell'ente: si taglia al primo separatore.
            return match[0]
                // Il punto taglia solo dopo una parola intera, per non spezzare "S. Giovanni".
                .split(/\s[-–—]\s|[,:(]|\s\|\s|(?<=[a-zà-ù]{2})\.\s/)[0]
                .replace(/[.\s]+$/, '')
                .replace(/\s+/g, ' ')
                .trim();
        }
    }
    return null;
}

function extractClassiConcorso(originalText) {
    const found = new Set();
    for (const match of String(originalText || '').matchAll(CLASSE_CONCORSO)) {
        found.add(`${match[1].toUpperCase()}-${match[2]}`);
    }
    return [...found];
}

function extractSSD(originalText) {
    const found = new Set();
    for (const match of String(originalText || '').matchAll(SSD)) {
        found.add(match[0].toUpperCase());
    }
    return [...found];
}

/**
 * Valuta una voce raccolta da una fonte.
 *
 * @param {{title?:string, context?:string, summary?:string}} item
 * @param {{soglia?:number, includiDirigenti?:boolean}} [options]
 * @returns {{pertinente:boolean, punteggio:number, livello:string, categoria:string,
 *            regione:string|null, ente:string|null, classiConcorso:string[], ssd:string[], segnali:string[]}}
 */
function classify(item, options = {}) {
    const soglia = Number.isFinite(options.soglia) ? options.soglia : 5;

    const titleRaw = String(item.title || '');
    const contextRaw = [item.summary, item.context].filter(Boolean).join(' ');
    const title = norm(titleRaw);
    const context = norm(contextRaw);
    const combined = `${title} ${context}`.trim();

    // Il titolo pesa il doppio del contesto: il contesto di pagina è rumoroso.
    const titleScore = scoreText(title);
    const contextScore = scoreText(context);
    const punteggio = titleScore.score * 2 + contextScore.score;

    const livello = detectLivello(combined);
    const categoria = detectCategoria(combined);

    return {
        pertinente: punteggio >= soglia,
        punteggio,
        livello,
        categoria,
        regione: detectRegione(combined),
        ente: detectEnte(`${titleRaw} ${contextRaw}`),
        classiConcorso: extractClassiConcorso(`${titleRaw} ${contextRaw}`),
        ssd: extractSSD(`${titleRaw} ${contextRaw}`),
        segnali: [...new Set([...titleScore.matched, ...contextScore.matched])]
    };
}

module.exports = { classify, norm, detectLivello, detectCategoria, detectRegione, detectEnte, extractClassiConcorso };
