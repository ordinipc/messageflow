# 🎓 Aggregatore bandi per docenti e insegnanti

Programma che **rileva automaticamente i bandi, i concorsi e gli avvisi per personale docente**
pubblicati da scuole, università, USR e ministeri italiani, li filtra, ne estrae la scadenza
e segnala solo le novità.

Funziona da riga di comando, come API REST e come pagina web (`/bandi`).
Nessuna dipendenza aggiuntiva: usa solo Node.js 18+ (`fetch` nativo) ed Express, già presente nel progetto.

---

## Come funziona

```
fonti (RSS / HTML / JSON)
   ↓  fetch educato: robots.txt, ETag, 1 richiesta ogni 1,5 s per host
estrazione voci (feed item oppure link + testo circostante)
   ↓
classificatore lessicale: è un bando per docenti? scuola o università? che tipo?
   ↓
estrazione scadenza ("entro le ore 12:00 del 15/05/2026"), ente, regione, classe di concorso, SSD
   ↓
archivio JSON con deduplica → solo i bandi nuovi finiscono nel digest
   ↓
console · email · webhook · API REST · pagina /bandi
```

Il filtro è **lessicale, non statistico**: ogni voce riceve un punteggio dai segnali
positivi (`professore`, `ricercatore`, `supplenza`, `messa a disposizione`, `classe di concorso`,
`incarico di insegnamento`, `assegno di ricerca`…) e da quelli negativi
(`personale ATA`, `collaboratore scolastico`, `fornitura`, `appalto`…). Sopra la soglia
(default 5) il bando è considerato pertinente. Il titolo pesa il doppio del contesto.

L'estrazione dei link è volutamente **senza selettori CSS**: i siti di scuole e atenei
cambiano struttura di continuo, mentre "un link + il testo che lo circonda" resta stabile.

---

## Uso rapido

```bash
# 1. Verifica che le fonti configurate rispondano ancora
npm run bandi:doctor

# 2. Prima raccolta (apre anche le pagine di dettaglio per trovare le scadenze)
npm run bandi -- run --approfondisci

# 3. Consulta l'archivio
npm run bandi -- list --livello scuola --entro 30
npm run bandi -- list --classe A-28
npm run bandi -- export --formato csv > bandi.csv
```

Con il server avviato (`npm start`) l'elenco è anche su **http://localhost:3000/bandi**
e in JSON su `/api/bandi`.

### Comandi

| Comando   | Cosa fa |
|-----------|---------|
| `run`     | Scarica le fonti, filtra i bandi, salva le novità, invia il digest |
| `list`    | Mostra i bandi archiviati con i filtri |
| `export`  | Esporta in JSON o CSV |
| `sources` | Elenca le fonti configurate |
| `doctor`  | Verifica che ogni fonte risponda e sia ancora parsabile |

Opzioni principali di `run`: `--fonte`, `--livello`, `--approfondisci`, `--max-dettagli N`,
`--soglia N`, `--dry-run`, `--email [dest]`, `--webhook [url]`, `--digest file.txt`, `--pulisci [giorni]`.
Filtri di `list`/`export`: `--livello`, `--categoria`, `--regione`, `--classe A-28`, `--cerca`,
`--entro N`, `--scaduti`, `--limite N`, `--json`.
L'elenco completo è in `node bandi/cli.js help`.

---

## Fonti

Le fonti predefinite sono in [`sources.json`](./sources.json):
Gazzetta Ufficiale (4ª Serie Speciale – Concorsi ed esami), portale bandi del MUR,
inPA, notizie del MIM, alcuni USR, EURAXESS.

> **Importante:** gli URL dei portali della PA cambiano spesso e alcune pagine
> caricano i contenuti via JavaScript (il programma legge solo l'HTML servito).
> Per questo ogni fonte ha il campo `verificata: false` finché non l'hai
> controllata **sulla tua rete** con `npm run bandi:doctor`: il comando dice per ogni
> fonte quante voci ha estratto e quante sono risultate pertinenti.
> Una fonte che non risponde va corretta nell'URL o disattivata (`"attiva": false`).

### Aggiungere le proprie fonti

Copia `sources.local.example.json` in `sources.local.json` (non versionato) e aggiungi
l'albo online della tua scuola, i bandi del tuo ateneo o l'USP della tua provincia:

```json
{
  "sources": [
    {
      "id": "ic-verdi-albo",
      "nome": "IC Verdi - Albo online",
      "tipo": "html",
      "url": "https://www.icverdi.edu.it/albo-online/",
      "livello": "scuola"
    }
  ]
}
```

Tipi supportati: `rss` (RSS 2.0 / Atom), `html` (estrazione link + contesto),
`json` (API con `jsonPath`, `urlTemplate` e mappatura `campi`).
Una fonte locale con lo stesso `id` di una predefinita la sostituisce: è il modo per
disattivare una fonte di serie senza toccare `sources.json`.

---

## Notifiche

```bash
# Digest via email (usa la configurazione SMTP già presente nel .env del progetto)
BANDI_EMAIL_TO=tu@example.it npm run bandi -- run --email

# Digest verso un webhook (Slack, Telegram bridge, Zapier, n8n…)
npm run bandi -- run --webhook https://hooks.example.com/xyz

# Digest su file
npm run bandi -- run --digest ./digest-bandi.txt
```

Esecuzione periodica con cron (ogni giorno alle 7:00):

```cron
0 7 * * * cd /percorso/messageflow && /usr/bin/node bandi/cli.js run --approfondisci --email >> /var/log/bandi.log 2>&1
```

---

## API REST

| Endpoint | Descrizione |
|----------|-------------|
| `GET /api/bandi` | Elenco filtrabile: `livello`, `categoria`, `regione`, `classe`, `cerca`, `entro`, `scaduti=1`, `limite` |
| `GET /api/bandi/statistiche` | Conteggi per livello, categoria e regione |
| `GET /api/bandi/fonti` | Fonti configurate e loro stato |
| `POST /api/bandi/aggiorna` | Avvia una raccolta. Richiede `BANDI_ADMIN_TOKEN` nell'header `x-bandi-token` |

---

## Variabili d'ambiente

| Variabile | Default | Uso |
|-----------|---------|-----|
| `BANDI_DB` | `bandi/data/bandi.json` | Percorso dell'archivio |
| `BANDI_CACHE_DIR` | `bandi/.cache` | Cache delle richieste condizionali (ETag) |
| `BANDI_USER_AGENT` | `BandiScuolaBot/1.0 …` | **Indica un contatto reale**: è buona educazione verso gli enti |
| `BANDI_MIN_INTERVAL_MS` | `1500` | Intervallo minimo fra due richieste allo stesso host |
| `BANDI_EMAIL_TO` | – | Destinatario del digest |
| `BANDI_WEBHOOK_URL` | – | Webhook del digest |
| `BANDI_ADMIN_TOKEN` | – | Abilita `POST /api/bandi/aggiorna` |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM` | – | Invio email (in alternativa `EMAIL_*` già usate dal progetto) |

---

## Uso corretto e limiti

- Il programma legge **solo pagine pubbliche**, rispetta `robots.txt` e il `Crawl-delay`,
  usa richieste condizionali e attende fra una richiesta e l'altra. Non aggirare questi
  limiti (`--ignora-robots` esiste solo per i test su fonti proprie).
- Il riconoscimento è automatico: **può sbagliare in entrambe le direzioni**. Prima di
  presentare domanda, apri sempre il bando originale sul sito dell'ente. Fa fede
  esclusivamente il testo ufficiale (e, per i concorsi statali, la Gazzetta Ufficiale).
- Le scadenze sono dedotte dal testo: se una scadenza non viene rilevata il campo resta
  vuoto, non viene mai inventata.
- I PDF non vengono letti (solo il titolo del link). Per estrarre le scadenze dai PDF
  serve una libreria dedicata, volutamente non inclusa.

## Test

```bash
npm test     # node --test bandi/test/*.test.js
```

I test coprono parser RSS/Atom, estrazione link, date italiane, robots.txt,
classificatore, deduplica, filtri, CLI e digest — tutti offline, su fixture.
