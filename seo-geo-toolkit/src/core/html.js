'use strict';
/** Utility di estrazione dal markup dei contenuti WordPress. */

const BLOCK_NOISE = /<(script|style)[\s\S]*?<\/\1>/gi;

function stripTags(html) {
  return String(html || '')
    .replace(BLOCK_NOISE, ' ')
    .replace(/<!--[\s\S]*?-->/g, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&#8217;|&rsquo;/g, "'")
    .replace(/&[a-z]+;/gi, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function headings(html) {
  const out = [];
  const re = /<h([1-6])(\s[^>]*)?>([\s\S]*?)<\/h\1>/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    out.push({ level: Number(m[1]), text: stripTags(m[3]), raw: m[0], index: m.index });
  }
  return out;
}

function images(html) {
  const out = [];
  const re = /<img\b[^>]*>/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    const t = m[0];
    const attr = (n) => (t.match(new RegExp(`${n}\\s*=\\s*["']([^"']*)["']`, 'i')) || [])[1] || '';
    out.push({
      raw: t,
      src: attr('src') || attr('data-src'),
      alt: attr('alt'),
      title: attr('title'),
      width: attr('width'),
      height: attr('height'),
      loading: attr('loading'),
      hasAlt: /alt\s*=\s*["'][^"']+["']/i.test(t),
    });
  }
  return out;
}

function links(html, siteHost) {
  const out = [];
  const re = /<a\b([^>]*)>([\s\S]*?)<\/a>/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    const attrs = m[1];
    const href = (attrs.match(/href\s*=\s*["']([^"']*)["']/i) || [])[1] || '';
    const rel = (attrs.match(/rel\s*=\s*["']([^"']*)["']/i) || [])[1] || '';
    const target = (attrs.match(/target\s*=\s*["']([^"']*)["']/i) || [])[1] || '';
    const anchor = stripTags(m[2]);
    let kind = 'other';
    if (!href || href.startsWith('#')) kind = 'anchor';
    else if (href.startsWith('mailto:')) kind = 'mailto';
    else if (href.startsWith('tel:')) kind = 'tel';
    else if (href.startsWith('/') || (siteHost && href.includes(siteHost))) kind = 'internal';
    else if (/^https?:\/\//i.test(href)) kind = 'external';
    out.push({ href, rel, target, anchor, kind });
  }
  return out;
}

function paragraphs(html) {
  const out = [];
  const re = /<p\b[^>]*>([\s\S]*?)<\/p>/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    const text = stripTags(m[1]);
    if (text) out.push(text);
  }
  return out;
}

function hasInlineStyleBlock(html) {
  return /<style\b/i.test(String(html || ''));
}

function hasJsonLd(html) {
  return /application\/ld\+json/i.test(String(html || ''));
}

function listCount(html) {
  return (String(html || '').match(/<(ul|ol)\b/gi) || []).length;
}

function tableCount(html) {
  return (String(html || '').match(/<table\b/gi) || []).length;
}

function firstParagraph(html) {
  const ps = paragraphs(html);
  return ps.find((p) => p.split(/\s+/).length > 8) || ps[0] || '';
}

module.exports = {
  stripTags, headings, images, links, paragraphs,
  hasInlineStyleBlock, hasJsonLd, listCount, tableCount, firstParagraph,
};
