'use strict';

const express = require('express');
const { Store, filterBandi, sortBandi } = require('./store');
const { loadSources } = require('./sources');
const { collectAll } = require('./collect');

/**
 * Router Express per l'aggregatore bandi.
 * Sola lettura per default; l'aggiornamento manuale richiede BANDI_ADMIN_TOKEN.
 *
 *   app.use('/api/bandi', require('./bandi/lib/router')());
 */
function creaRouter(options = {}) {
    const router = express.Router();
    const dbFile = options.db;

    async function caricaBandi() {
        const store = await new Store(dbFile).load();
        return store.all();
    }

    router.get('/', async (req, res) => {
        try {
            const limite = Math.min(Number(req.query.limite) || 100, 500);
            const bandi = sortBandi(
                filterBandi(await caricaBandi(), {
                    livello: req.query.livello,
                    categoria: req.query.categoria,
                    regione: req.query.regione,
                    classe: req.query.classe,
                    testo: req.query.cerca,
                    entro: req.query.entro !== undefined ? Number(req.query.entro) : undefined,
                    soloAperti: req.query.scaduti !== '1'
                })
            );

            res.json({
                totale: bandi.length,
                aggiornatoIl: (await new Store(dbFile).load()).data.aggiornatoIl,
                bandi: bandi.slice(0, limite)
            });
        } catch (error) {
            res.status(500).json({ errore: 'Impossibile leggere l\'archivio bandi', dettaglio: error.message });
        }
    });

    router.get('/statistiche', async (req, res) => {
        try {
            const bandi = await caricaBandi();
            const conteggio = (campo) =>
                bandi.reduce((acc, bando) => {
                    const chiave = bando[campo] || 'n.d.';
                    acc[chiave] = (acc[chiave] || 0) + 1;
                    return acc;
                }, {});

            const aperti = bandi.filter((b) => !b.scadenza || new Date(b.scadenza) >= new Date());
            res.json({
                totale: bandi.length,
                aperti: aperti.length,
                perLivello: conteggio('livello'),
                perCategoria: conteggio('categoria'),
                perRegione: conteggio('regione')
            });
        } catch (error) {
            res.status(500).json({ errore: 'Impossibile calcolare le statistiche', dettaglio: error.message });
        }
    });

    router.get('/fonti', (req, res) => {
        const fonti = loadSources({ soloAttive: false }).map(({ id, nome, tipo, livello, attiva, verificata, url }) => ({
            id, nome, tipo, livello, attiva, verificata, url
        }));
        res.json({ totale: fonti.length, fonti });
    });

    router.post('/aggiorna', express.json({ limit: '4kb' }), async (req, res) => {
        const token = process.env.BANDI_ADMIN_TOKEN;
        if (!token) {
            return res.status(503).json({ errore: 'Aggiornamento via API disabilitato: BANDI_ADMIN_TOKEN non configurato' });
        }
        const fornito = req.get('x-bandi-token') || (req.body && req.body.token);
        if (fornito !== token) return res.status(401).json({ errore: 'Token non valido' });

        try {
            const sources = loadSources({ ids: req.body && req.body.fonti });
            const { bandi, risultati } = await collectAll(sources, { maxPerFonte: 60 });
            const store = await new Store(dbFile).load();
            const esito = store.upsertMany(bandi);
            await store.save();

            res.json({
                nuovi: esito.nuovi.length,
                aggiornati: esito.aggiornati.length,
                fonti: risultati.map((r) => ({ fonte: r.fonte, bandi: r.bandi.length, errore: r.errore }))
            });
        } catch (error) {
            res.status(500).json({ errore: 'Aggiornamento fallito', dettaglio: error.message });
        }
    });

    return router;
}

module.exports = creaRouter;
