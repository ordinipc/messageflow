'use strict';

const CATEGORIES = {
  technical:  { label: 'Tecnico e indicizzazione', weight: 15, icon: '⚙️' },
  onpage:     { label: 'On-page (title, meta, URL)', weight: 15, icon: '📝' },
  content:    { label: 'Qualità dei contenuti', weight: 15, icon: '📄' },
  links:      { label: 'Link interni e architettura', weight: 12, icon: '🔗' },
  media:      { label: 'Immagini e performance', weight: 8, icon: '🖼️' },
  structured: { label: 'Dati strutturati', weight: 10, icon: '🧩' },
  local:      { label: 'SEO locale (GEO geografico)', weight: 10, icon: '📍' },
  generative: { label: 'GEO — Generative Engine Optimization', weight: 10, icon: '🤖' },
  eeat:       { label: 'E-E-A-T e affidabilità', weight: 5, icon: '🏅' },
  taxonomy:   { label: 'Tassonomie e archivi', weight: 5, icon: '🗂️' },
};

const RULES = [].concat(
  require('./technical'),
  require('./onpage'),
  require('./content'),
  require('./links'),
  require('./media'),
  require('./structured'),
  require('./local'),
  require('./generative'),
  require('./eeat'),
  require('./taxonomy'),
);

module.exports = { RULES, CATEGORIES };
