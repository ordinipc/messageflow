'use strict';
/** Modello normalizzato del sito: documenti + indici derivati. */

const H = require('./html');
const T = require('./text');

const SEO_META = {
  rankmath: { title: 'rank_math_title', desc: 'rank_math_description', focus: 'rank_math_focus_keyword', robots: 'rank_math_robots', canonical: 'rank_math_canonical_url', score: 'rank_math_seo_score' },
  yoast: { title: '_yoast_wpseo_title', desc: '_yoast_wpseo_metadesc', focus: '_yoast_wpseo_focuskw', canonical: '_yoast_wpseo_canonical' },
};

function hostOf(url) {
  const m = String(url || '').match(/^https?:\/\/([^/]+)/i);
  return m ? m[1].replace(/^www\./, '') : '';
}

function pathOf(url, host) {
  return String(url || '').replace(/^https?:\/\/[^/]+/i, '').replace(/\/$/, '') || '/';
}

function buildDoc(item, host) {
  const content = item.content || '';
  const text = H.stripTags(content);
  const hs = H.headings(content);
  const imgs = H.images(content);
  const lks = H.links(content, host);
  const meta = item.meta || {};

  const seoTitleRaw = meta[SEO_META.rankmath.title] || meta[SEO_META.yoast.title] || '';
  const seoTitle = seoTitleRaw.replace(/%[a-z_]+%/gi, '').replace(/\s*[|\-–]\s*$/, '').trim();
  const seoDesc = (meta[SEO_META.rankmath.desc] || meta[SEO_META.yoast.desc] || '').trim();
  const focus = (meta[SEO_META.rankmath.focus] || meta[SEO_META.yoast.focus] || '').split(',')[0].trim();

  return {
    id: item.id,
    type: item.type,
    status: item.status,
    title: item.title,
    slug: item.slug,
    link: item.link,
    path: pathOf(item.link, host),
    date: item.dateGmt || item.date,
    modified: item.modifiedGmt || item.modified,
    author: item.creator,
    categories: item.categories,
    tags: item.tags,
    excerpt: H.stripTags(item.excerpt),
    content,
    text,
    words: T.wordCount(text),
    gulpease: T.gulpease(text),
    headings: hs,
    h1: hs.filter((h) => h.level === 1),
    h2: hs.filter((h) => h.level === 2),
    h3: hs.filter((h) => h.level === 3),
    images: imgs,
    links: lks,
    internalLinks: lks.filter((l) => l.kind === 'internal'),
    externalLinks: lks.filter((l) => l.kind === 'external'),
    paragraphs: H.paragraphs(content),
    hasJsonLd: H.hasJsonLd(content),
    hasInlineStyle: H.hasInlineStyleBlock(content),
    lists: H.listCount(content),
    tables: H.tableCount(content),
    firstParagraph: H.firstParagraph(content),
    seoTitle,
    seoTitleRaw,
    seoDesc,
    focusKeyword: focus,
    seoScore: meta[SEO_META.rankmath.score] ? Number(meta[SEO_META.rankmath.score]) : null,
    canonical: meta[SEO_META.rankmath.canonical] || meta[SEO_META.yoast.canonical] || '',
    robots: meta[SEO_META.rankmath.robots] || '',
    noindex: /noindex/.test(meta[SEO_META.rankmath.robots] || ''),
    thumbnailId: meta._thumbnail_id || '',
    isElementor: Boolean(meta._elementor_data),
    template: meta._wp_page_template || '',
    meta,
    isQuestionTitle: /\?/.test(item.title),
    commentStatus: item.commentStatus,
  };
}

function buildSite(parsed) {
  const host = hostOf(parsed.site.link || parsed.site.baseSiteUrl);
  const all = parsed.items.map((i) => buildDoc(i, host));

  const docs = all.filter((d) => ['post', 'page'].includes(d.type));
  const published = docs.filter((d) => d.status === 'publish');
  const attachments = parsed.items.filter((i) => i.type === 'attachment').map((i) => ({
    id: i.id,
    url: i.attachmentUrl,
    file: (i.attachmentUrl || '').split('/').pop() || '',
    ext: ((i.attachmentUrl || '').split('.').pop() || '').toLowerCase(),
    title: i.title,
    alt: i.meta._wp_attachment_image_alt || '',
    parent: i.parent,
    caption: H.stripTags(i.excerpt),
    description: H.stripTags(i.content),
    filesize: Number(((i.meta._wp_attachment_metadata || '').match(/"filesize";i:(\d+)/) || [])[1] || 0),
    width: Number(((i.meta._wp_attachment_metadata || '').match(/s:5:"width";i:(\d+)/) || [])[1] || 0),
    height: Number(((i.meta._wp_attachment_metadata || '').match(/s:6:"height";i:(\d+)/) || [])[1] || 0),
  }));

  const menuItems = parsed.items.filter((i) => i.type === 'nav_menu_item').map((i) => ({
    title: i.title,
    type: i.meta._menu_item_type,
    object: i.meta._menu_item_object,
    objectId: i.meta._menu_item_object_id,
    url: i.meta._menu_item_url || '',
    order: Number(i.menuOrder || 0),
  })).sort((a, b) => a.order - b.order);

  // Grafo dei link interni: path -> documenti che lo linkano.
  const byPath = new Map();
  published.forEach((d) => byPath.set(d.path, d));
  const inbound = new Map();
  published.forEach((d) => {
    const seen = new Set();
    d.internalLinks.forEach((l) => {
      const p = pathOf(l.href.startsWith('/') ? `https://${host}${l.href}` : l.href, host).split('#')[0].split('?')[0];
      if (!p || p === d.path || seen.has(p)) return;
      seen.add(p);
      if (!inbound.has(p)) inbound.set(p, []);
      inbound.get(p).push(d.path);
    });
  });

  return {
    meta: parsed.site,
    host,
    url: parsed.site.link || `https://${host}`,
    all,
    docs,
    published,
    posts: published.filter((d) => d.type === 'post'),
    pages: published.filter((d) => d.type === 'page'),
    trashed: docs.filter((d) => d.status === 'trash'),
    drafts: docs.filter((d) => ['draft', 'pending', 'private'].includes(d.status)),
    attachments,
    menuItems,
    categories: parsed.categories,
    tags: parsed.tags,
    authors: parsed.site.authors,
    byPath,
    inbound,
    inboundCount: (p) => (inbound.get(p) || []).length,
  };
}

module.exports = { buildSite, hostOf, pathOf, SEO_META };
