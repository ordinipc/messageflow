'use strict';
/**
 * Parser WXR (WordPress eXtended RSS) senza dipendenze esterne.
 * L'export WordPress e' un RSS 2.0 con namespace wp:/content:/excerpt:/dc:
 * I valori testuali sono quasi sempre in CDATA, quindi si estraggono con
 * regex ancorate al tag invece di usare un parser DOM generico (file da ~10MB).
 */

const fs = require('fs');

function decodeEntities(s) {
  if (!s) return '';
  return s
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;/g, "'")
    .replace(/&apos;/g, "'")
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&');
}

function unwrap(raw) {
  if (raw == null) return '';
  const m = raw.match(/^\s*<!\[CDATA\[([\s\S]*?)\]\]>\s*$/);
  if (m) return m[1];
  return decodeEntities(raw.trim());
}

/** Estrae il contenuto del primo tag `name` presente in `xml`. */
function tag(xml, name) {
  const re = new RegExp(`<${name}(?:\\s[^>]*)?>([\\s\\S]*?)</${name}>`);
  const m = xml.match(re);
  return m ? unwrap(m[1]) : '';
}

/** Estrae tutte le occorrenze di un tag. */
function tagAll(xml, name) {
  const re = new RegExp(`<${name}(?:\\s[^>]*)?>([\\s\\S]*?)</${name}>`, 'g');
  const out = [];
  let m;
  while ((m = re.exec(xml)) !== null) out.push(unwrap(m[1]));
  return out;
}

/** postmeta -> mappa chiave/valore (l'ultima occorrenza vince, come in WP). */
function parseMeta(itemXml) {
  const meta = {};
  const re = /<wp:postmeta>([\s\S]*?)<\/wp:postmeta>/g;
  let m;
  while ((m = re.exec(itemXml)) !== null) {
    const key = tag(m[1], 'wp:meta_key');
    const value = tag(m[1], 'wp:meta_value');
    if (key) meta[key] = value;
  }
  return meta;
}

function parseTerms(itemXml) {
  const categories = [];
  const tags = [];
  const re = /<category domain="([^"]+)" nicename="([^"]+)"><!\[CDATA\[([\s\S]*?)\]\]><\/category>/g;
  let m;
  while ((m = re.exec(itemXml)) !== null) {
    const entry = { slug: m[2], name: m[3] };
    if (m[1] === 'category') categories.push(entry);
    else if (m[1] === 'post_tag') tags.push(entry);
  }
  return { categories, tags };
}

function parseChannelCategories(xml) {
  const out = [];
  const re = /<wp:category>([\s\S]*?)<\/wp:category>/g;
  let m;
  while ((m = re.exec(xml)) !== null) {
    const block = m[1];
    const termmeta = {};
    const tm = /<wp:termmeta>([\s\S]*?)<\/wp:termmeta>/g;
    let t;
    while ((t = tm.exec(block)) !== null) {
      termmeta[tag(t[1], 'wp:meta_key')] = tag(t[1], 'wp:meta_value');
    }
    out.push({
      termId: tag(block, 'wp:term_id'),
      slug: tag(block, 'wp:category_nicename'),
      name: tag(block, 'wp:cat_name'),
      parent: tag(block, 'wp:category_parent'),
      meta: termmeta,
    });
  }
  return out;
}

function parseChannelTags(xml) {
  const out = [];
  const re = /<wp:tag>([\s\S]*?)<\/wp:tag>/g;
  let m;
  while ((m = re.exec(xml)) !== null) {
    out.push({
      termId: tag(m[1], 'wp:term_id'),
      slug: tag(m[1], 'wp:tag_slug'),
      name: tag(m[1], 'wp:tag_name'),
    });
  }
  return out;
}

function parseItem(itemXml) {
  const meta = parseMeta(itemXml);
  const { categories, tags } = parseTerms(itemXml);
  return {
    title: tag(itemXml, 'title'),
    link: tag(itemXml, 'link'),
    pubDate: tag(itemXml, 'pubDate'),
    creator: tag(itemXml, 'dc:creator'),
    guid: tag(itemXml, 'guid'),
    description: tag(itemXml, 'description'),
    content: tag(itemXml, 'content:encoded'),
    excerpt: tag(itemXml, 'excerpt:encoded'),
    id: tag(itemXml, 'wp:post_id'),
    date: tag(itemXml, 'wp:post_date'),
    dateGmt: tag(itemXml, 'wp:post_date_gmt'),
    modified: tag(itemXml, 'wp:post_modified'),
    modifiedGmt: tag(itemXml, 'wp:post_modified_gmt'),
    commentStatus: tag(itemXml, 'wp:comment_status'),
    slug: tag(itemXml, 'wp:post_name'),
    status: tag(itemXml, 'wp:status'),
    parent: tag(itemXml, 'wp:post_parent'),
    menuOrder: tag(itemXml, 'wp:menu_order'),
    type: tag(itemXml, 'wp:post_type'),
    password: tag(itemXml, 'wp:post_password'),
    isSticky: tag(itemXml, 'wp:is_sticky') === '1',
    attachmentUrl: tag(itemXml, 'wp:attachment_url'),
    categories,
    tags,
    meta,
    raw: itemXml,
  };
}

function parseFile(filePath) {
  const xml = fs.readFileSync(filePath, 'utf8');
  return parseString(xml);
}

function parseString(xml) {
  const headEnd = xml.indexOf('<item>');
  const head = headEnd === -1 ? xml : xml.slice(0, headEnd);

  const site = {
    title: tag(head, 'title'),
    link: tag(head, 'link'),
    description: tag(head, 'description'),
    language: tag(head, 'language'),
    pubDate: tag(head, 'pubDate'),
    baseSiteUrl: tag(head, 'wp:base_site_url'),
    baseBlogUrl: tag(head, 'wp:base_blog_url'),
    generator: (xml.match(/generator="([^"]+)"/) || [])[1] || '',
    authors: [],
  };

  const authRe = /<wp:author>([\s\S]*?)<\/wp:author>/g;
  let a;
  while ((a = authRe.exec(head)) !== null) {
    site.authors.push({
      id: tag(a[1], 'wp:author_id'),
      login: tag(a[1], 'wp:author_login'),
      email: tag(a[1], 'wp:author_email'),
      displayName: tag(a[1], 'wp:author_display_name'),
      firstName: tag(a[1], 'wp:author_first_name'),
      lastName: tag(a[1], 'wp:author_last_name'),
    });
  }

  const items = [];
  const itemRe = /<item>([\s\S]*?)<\/item>/g;
  let m;
  while ((m = itemRe.exec(xml)) !== null) items.push(parseItem(m[1]));

  return {
    site,
    items,
    categories: parseChannelCategories(head),
    tags: parseChannelTags(head),
    rawLength: xml.length,
  };
}

module.exports = { parseFile, parseString, decodeEntities, tag, tagAll, unwrap };
