'use strict';
const { SEV, docIssue } = require('./_helpers');

module.exports = [
  {
    id: 'IMG-01', category: 'media', severity: SEV.high, auto: true,
    title: 'Immagini senza attributo alt',
    why: 'L’alt è il testo che Google usa per capire l’immagine: senza, si perde traffico da Google Immagini e la pagina non è accessibile.',
    fix: 'Generazione automatica dell’alt da titolo pagina + focus keyword, applicata dal plugin a runtime.',
    check: (s) => {
      const out = [];
      s.published.forEach((d) => {
        const n = d.images.filter((i) => !i.hasAlt).length;
        if (n) out.push(docIssue(d, `${n} immagini su ${d.images.length} senza alt`));
      });
      return out;
    },
  },
  {
    id: 'IMG-02', category: 'media', severity: SEV.medium, auto: true,
    title: 'Allegati in libreria media senza testo alternativo',
    why: 'L’alt impostato in libreria viene ereditato ovunque l’immagine sia inserita: compilarlo una volta risolve decine di pagine.',
    fix: 'File CSV con alt suggerito per ogni allegato + importer del plugin.',
    check: (s) => s.attachments.filter((a) => !a.alt && /jpe?g|png|webp|gif|svg/.test(a.ext))
      .map((a) => ({ ref: a.file, title: a.title, type: 'attachment', detail: 'alt assente' })),
  },
  {
    id: 'IMG-03', category: 'media', severity: SEV.high, auto: false,
    title: 'Immagini pesanti (> 200 KB) che rallentano il caricamento',
    why: 'Il peso delle immagini è la causa principale di LCP lento; i Core Web Vitals influenzano ranking e conversioni.',
    fix: 'Conversione in WebP/AVIF e compressione; il plugin abilita lazy loading e dimensioni esplicite.',
    check: (s) => s.attachments.filter((a) => a.filesize > 200 * 1024)
      .map((a) => ({ ref: a.file, title: a.title, type: 'attachment', detail: `${Math.round(a.filesize / 1024)} KB` })),
  },
  {
    id: 'IMG-04', category: 'media', severity: SEV.medium, auto: false,
    title: 'Formati immagine non moderni (JPG/PNG invece di WebP)',
    why: 'WebP pesa il 25-35% in meno a parità di qualità: impatto diretto su LCP e crawl budget.',
    fix: 'Conversione batch in WebP mantenendo i vecchi file come fallback.',
    check: (s) => s.attachments.filter((a) => ['jpg', 'jpeg', 'png'].includes(a.ext))
      .map((a) => ({ ref: a.file, title: a.title, type: 'attachment', detail: `formato ${a.ext}` })),
  },
  {
    id: 'IMG-05', category: 'media', severity: SEV.high, auto: true,
    title: 'Articoli senza immagine in evidenza',
    why: 'Senza featured image mancano og:image e twitter:image: le condivisioni social sono senza anteprima e Google Discover esclude la pagina.',
    fix: 'Il plugin imposta un’immagine di fallback brandizzata e genera og:image dinamico.',
    check: (s) => s.published.filter((d) => !d.thumbnailId)
      .map((d) => docIssue(d, 'nessuna immagine in evidenza')),
  },
  {
    id: 'IMG-06', category: 'media', severity: SEV.low, auto: true,
    title: 'Immagini senza width/height espliciti',
    why: 'Senza dimensioni il browser non riserva lo spazio e si genera Cumulative Layout Shift (CLS), penalizzato dai Core Web Vitals.',
    fix: 'Il plugin aggiunge width/height e loading="lazy" alle immagini del contenuto.',
    check: (s) => {
      const out = [];
      s.published.forEach((d) => {
        const n = d.images.filter((i) => !i.width || !i.height).length;
        if (n) out.push(docIssue(d, `${n} immagini senza dimensioni`));
      });
      return out;
    },
  },
];
