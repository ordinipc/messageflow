'use strict';

const fsp = require('fs/promises');
const path = require('path');
const { daysUntil } = require('./dates');

function formatDate(iso) {
    if (!iso) return 'n.d.';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return 'n.d.';
    return date.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'Europe/Rome' });
}

function riga(bando) {
    const giorni = daysUntil(bando.scadenza);
    const scadenza = bando.scadenza
        ? `scade il ${formatDate(bando.scadenza)}${giorni != null && giorni >= 0 ? ` (fra ${giorni} gg)` : ' (scaduto)'}`
        : 'scadenza non rilevata';
    const dettagli = [bando.ente, bando.regione, (bando.classiConcorso || []).join(', ')].filter(Boolean).join(' · ');

    return `• ${bando.titolo}\n  ${dettagli ? dettagli + '\n  ' : ''}${scadenza}\n  ${bando.url}`;
}

function digestTesto(bandi, opzioni = {}) {
    const titolo = opzioni.titolo || 'Nuovi bandi per docenti e insegnanti';
    if (bandi.length === 0) return `${titolo}\n\nNessun nuovo bando trovato.`;

    const perLivello = { universita: [], scuola: [], sconosciuto: [] };
    for (const bando of bandi) (perLivello[bando.livello] || perLivello.sconosciuto).push(bando);

    const sezioni = [
        ['Università / AFAM', perLivello.universita],
        ['Scuola', perLivello.scuola],
        ['Da classificare', perLivello.sconosciuto]
    ]
        .filter(([, items]) => items.length > 0)
        .map(([nome, items]) => `${nome} (${items.length})\n${items.map(riga).join('\n\n')}`);

    return `${titolo} — ${bandi.length} risultati\n\n${sezioni.join('\n\n')}`;
}

function escapeHtml(text) {
    return String(text || '').replace(/[&<>"']/g, (char) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char])
    );
}

function digestHtml(bandi, opzioni = {}) {
    const titolo = escapeHtml(opzioni.titolo || 'Nuovi bandi per docenti e insegnanti');
    if (bandi.length === 0) return `<h2>${titolo}</h2><p>Nessun nuovo bando trovato.</p>`;

    const items = bandi
        .map((bando) => {
            const giorni = daysUntil(bando.scadenza);
            const meta = [bando.ente, bando.regione, (bando.classiConcorso || []).join(', ')]
                .filter(Boolean)
                .map(escapeHtml)
                .join(' · ');
            const scadenza = bando.scadenza
                ? `Scade il ${formatDate(bando.scadenza)}${giorni != null && giorni >= 0 ? ` (fra ${giorni} giorni)` : ''}`
                : 'Scadenza non rilevata';

            return `<li style="margin:0 0 16px 0">
                <a href="${escapeHtml(bando.url)}" style="font-weight:600;color:#1a4fd6;text-decoration:none">${escapeHtml(bando.titolo)}</a>
                ${meta ? `<div style="color:#555;font-size:13px">${meta}</div>` : ''}
                <div style="color:#8a5a00;font-size:13px">${escapeHtml(scadenza)}</div>
            </li>`;
        })
        .join('\n');

    return `<h2 style="font-family:system-ui,sans-serif">${titolo}</h2>
<p style="font-family:system-ui,sans-serif;color:#555">${bandi.length} risultati</p>
<ul style="font-family:system-ui,sans-serif;padding-left:18px">${items}</ul>`;
}

/** Invia il digest via email (SMTP configurato via .env). Nodemailer è opzionale. */
async function inviaEmail(bandi, opzioni = {}) {
    const to = opzioni.to || process.env.BANDI_EMAIL_TO;
    if (!to) return { inviata: false, motivo: 'BANDI_EMAIL_TO non configurato' };

    let nodemailer;
    try {
        nodemailer = require('nodemailer');
    } catch {
        return { inviata: false, motivo: 'nodemailer non installato (npm install)' };
    }

    const host = process.env.SMTP_HOST || process.env.EMAIL_HOST;
    if (!host) return { inviata: false, motivo: 'SMTP_HOST non configurato' };

    const transporter = nodemailer.createTransport({
        host,
        port: Number(process.env.SMTP_PORT || process.env.EMAIL_PORT || 587),
        secure: String(process.env.SMTP_SECURE || 'false') === 'true',
        auth: {
            user: process.env.SMTP_USER || process.env.EMAIL_USER,
            pass: process.env.SMTP_PASS || process.env.EMAIL_PASSWORD
        }
    });

    const titolo = opzioni.titolo || `Bandi docenti: ${bandi.length} nuovi risultati`;
    await transporter.sendMail({
        from: process.env.SMTP_FROM || process.env.EMAIL_USER,
        to,
        subject: titolo,
        text: digestTesto(bandi, { titolo }),
        html: digestHtml(bandi, { titolo })
    });

    return { inviata: true, destinatario: to };
}

/** Invia il digest a un webhook generico (Slack, Telegram bridge, Zapier, ...). */
async function inviaWebhook(bandi, opzioni = {}) {
    const url = opzioni.url || process.env.BANDI_WEBHOOK_URL;
    if (!url) return { inviata: false, motivo: 'BANDI_WEBHOOK_URL non configurato' };

    const response = await fetch(url, {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
            testo: digestTesto(bandi, opzioni),
            text: digestTesto(bandi, opzioni),
            conteggio: bandi.length,
            bandi
        })
    });

    if (!response.ok) throw new Error(`webhook ha risposto ${response.status}`);
    return { inviata: true, destinatario: url };
}

async function scriviDigest(bandi, file, opzioni = {}) {
    await fsp.mkdir(path.dirname(file), { recursive: true });
    await fsp.writeFile(file, digestTesto(bandi, opzioni), 'utf8');
    return file;
}

module.exports = { digestTesto, digestHtml, inviaEmail, inviaWebhook, scriviDigest, formatDate };
