'use strict';
/** Metriche testuali tarate sull'italiano. */

const STOPWORDS_IT = new Set(('a ad agli ai al alla alle allo anche c che chi ci coi col come con cui da dagli dai dal dalla '
  + 'dalle dallo degli dei del della delle dello di e ed gli ha hai hanno ho i il in io la le lo loro ma me mi ne negli nei '
  + 'nel nella nelle nello noi non o per perche piu può qual quale quali quando quanto quella quelle quelli quello questa '
  + 'queste questi questo se sei si sia siamo sono sto su sugli sui sul sulla sulle sullo ti tra tu tuo tra un una uno vi voi '
  + 'essere fare tutto tutti molto anche solo ogni dove ce sua suo suoi loro nostro nostra nostri nostre').split(' '));

function words(text) {
  return String(text || '')
    .toLowerCase()
    .replace(/[^a-zà-ÿ0-9'\s-]/gi, ' ')
    .split(/\s+/)
    .filter(Boolean);
}

function wordCount(text) {
  return words(text).length;
}

function sentences(text) {
  return String(text || '').split(/[.!?]+(?:\s|$)/).map((s) => s.trim()).filter((s) => s.split(/\s+/).length > 2);
}

/**
 * Indice Gulpease: leggibilita' per l'italiano (0-100, piu' alto = piu' facile).
 * >= 80 facile, 60-80 medio, < 40 difficile per un lettore con licenza media.
 */
function gulpease(text) {
  const w = words(text);
  const s = sentences(text);
  if (w.length < 30 || s.length === 0) return null;
  const letters = w.join('').length;
  return Math.max(0, Math.min(100, Math.round(89 + (300 * s.length - 10 * letters) / w.length)));
}

function slugify(str) {
  return String(str || '')
    .toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/['’]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

/** Slug conciso: rimuove stopword e limita a `maxWords` token / `maxChars`. */
const COMPOUNDS = [[/e-?commerce/gi, 'ecommerce'], [/e-?mail/gi, 'email'], [/on-?line/gi, 'online'], [/web-?agency/gi, 'web agency']];

function shortSlug(title, maxWords = 6, maxChars = 60) {
  let src = String(title || '');
  for (const [re, rep] of COMPOUNDS) src = src.replace(re, rep);
  const base = slugify(src).split('-').filter(Boolean);
  const kept = base.filter((w) => !STOPWORDS_IT.has(w) && w.length > 1);
  const source = kept.length >= 3 ? kept : base;
  let out = [];
  for (const w of source.slice(0, maxWords)) {
    if ([...out, w].join('-').length > maxChars) break;
    out.push(w);
  }
  if (!out.length) out = source.slice(0, 3);
  return out.join('-');
}

/** Taglia a lunghezza massima senza spezzare le parole. */
function truncate(str, max) {
  const s = String(str || '').trim().replace(/\s+/g, ' ');
  if (s.length <= max) return s;
  const cut = s.slice(0, max);
  const i = cut.lastIndexOf(' ');
  return (i > max * 0.5 ? cut.slice(0, i) : cut).replace(/[\s,;:.\-–—]+$/, '');
}


const DANGLING = new Set(('e ed o od ma se che chi cui di del della dello dei degli delle da dal dalla a al alla ai agli alle '
  + 'in nel nella nei negli nelle con col su sul sulla per tra fra il lo la i gli le un uno una anche come piu più ogni '
  + 'quando dove mentre oppure inoltre ovvero cioe cioè verso presso senza sopra sotto dopo prima').split(' '));

/**
 * Ripulisce una frase troncata: taglia all'ultima virgola utile e rimuove le
 * parole "sospese" finali (congiunzioni, preposizioni, articoli).
 */
function polishClause(str) {
  let s = String(str || '').trim().replace(/[\s;:,.\-–—]+$/, '');
  let parts = s.split(/\s+/);
  while (parts.length > 2 && DANGLING.has(parts[parts.length - 1].toLowerCase().replace(/[^a-zà-ù']/gi, ''))) {
    parts.pop();
  }
  s = parts.join(' ').replace(/[\s;:,.\-–—]+$/, '');
  return s;
}

function keywordDensity(text, keyword) {
  const w = words(text);
  if (!w.length || !keyword) return 0;
  const kw = words(keyword);
  if (!kw.length) return 0;
  let hits = 0;
  for (let i = 0; i <= w.length - kw.length; i++) {
    if (kw.every((k, j) => w[i + j] === k)) hits++;
  }
  return (hits * kw.length) / w.length * 100;
}

function topTerms(text, n = 12) {
  const freq = new Map();
  for (const w of words(text)) {
    if (w.length < 4 || STOPWORDS_IT.has(w)) continue;
    freq.set(w, (freq.get(w) || 0) + 1);
  }
  return [...freq.entries()].sort((a, b) => b[1] - a[1]).slice(0, n).map(([t, c]) => ({ term: t, count: c }));
}

/** Shingle di 5 parole, per il confronto near-duplicate (Jaccard). */
function shingles(text, size = 5) {
  const w = words(text);
  const set = new Set();
  for (let i = 0; i + size <= w.length; i++) set.add(w.slice(i, i + size).join(' '));
  return set;
}

function jaccard(a, b) {
  if (!a.size || !b.size) return 0;
  let inter = 0;
  const [small, big] = a.size < b.size ? [a, b] : [b, a];
  for (const x of small) if (big.has(x)) inter++;
  return inter / (a.size + b.size - inter);
}

module.exports = {
  polishClause, words, wordCount, sentences, gulpease, slugify, shortSlug, truncate,
  keywordDensity, topTerms, shingles, jaccard, STOPWORDS_IT,
};
