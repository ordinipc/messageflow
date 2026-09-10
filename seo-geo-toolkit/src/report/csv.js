'use strict';

/** Serializza righe in CSV compatibile con Excel italiano (separatore ;). */
function toCsv(rows, columns, sep = ';') {
  const esc = (v) => {
    const s = v === null || v === undefined ? '' : String(v);
    return /["\n;,]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  const head = columns.map((c) => esc(c.label)).join(sep);
  const body = rows.map((r) => columns.map((c) => esc(typeof c.value === 'function' ? c.value(r) : r[c.key])).join(sep));
  return `﻿${[head, ...body].join('\r\n')}\r\n`;
}

module.exports = { toCsv };
