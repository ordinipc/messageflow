# 📋 Sezione Preventivi — Contabilità Pro

Modulo preventivi integrato nel gestionale: creazione, elenco, invio via **Email** o **WhatsApp**,
download in **PDF** e conversione in fattura.

## File del modulo

| File | Ruolo |
|------|-------|
| `index.php` | app principale (nuove pagine, modali e handler dei preventivi) |
| `lib-preventivi.php` | **nuovo** — generatore PDF nativo, invio email con allegato, helper WhatsApp |
| `preventivo.php` | **nuovo** — pagina pubblica del preventivo (link condivisibile con il cliente) |
| `manifest.json` | aggiunta scorciatoia PWA "Preventivi" |

Nessuna libreria esterna richiesta: il PDF viene generato in PHP puro (nessun Composer, TCPDF o FPDF).

## Installazione

1. Carica i file in `/app/accounting/` sul server (stessa cartella di `index.php`).
2. Apri il gestionale: le tabelle `acc_quotes` e `acc_quote_items` vengono create automaticamente
   al primo accesso (come per le altre tabelle dell'app).
3. Verifica in **Dati Azienda** ragione sociale, P.IVA, email, telefono e IBAN: compaiono nel PDF
   e nei messaggi inviati al cliente.

## Cosa può fare l'utente

- **Creare un preventivo**: cliente, data, validità, oggetto, righe (descrizione, quantità, prezzo,
  IVA), sconto percentuale, modalità di pagamento, note e condizioni. Totali calcolati in tempo reale.
- **Numerazione automatica**: `PR-ANNO/0001`, progressiva per anno.
- **Elenco con filtri** per stato (bozza, inviato, accettato, rifiutato, fatturato) e statistiche:
  valore in attesa di risposta, valore accettato, tasso di conversione.
- **Scaricare il PDF** del preventivo (intestazione azienda, box destinatario, tabella voci,
  totali, condizioni, spazio per timbro e firma, multipagina).
- **Inviare via email**: il PDF viene allegato automaticamente, oggetto e messaggio sono
  precompilati e modificabili, con opzione "invia una copia anche a me".
- **Inviare su WhatsApp**: apre la chat del cliente con messaggio e link al preventivo già pronti;
  il preventivo viene segnato come "Inviato".
- **Link pubblico**: ogni preventivo ha un URL con token (`preventivo.php?t=...`) dove il cliente
  può vederlo, scaricarlo in PDF, stamparlo e **accettarlo o rifiutarlo online**.
- **Convertire in fattura** con un clic (sconto applicato ai prezzi, scadenza creata in automatico
  nello scadenzario per i piani Pro).
- **Duplicare** un preventivo per riproporlo a un altro cliente.
- **Esportare in CSV** tutti i preventivi (sezione Export, piani Pro).

## Note tecniche

- L'invio email usa `mail()` di PHP con messaggio MIME multipart (testo + HTML + allegato PDF).
  Se il server non ha un MTA configurato, l'app avvisa l'utente e resta possibile inviare il
  preventivo via WhatsApp o allegando manualmente il PDF scaricato.
- Il token del link pubblico è casuale (16 byte, `random_bytes`) e la pagina pubblica espone
  solo quel singolo preventivo; è impostato `noindex, nofollow`.
- Tutte le query filtrano per `customer_id`, come nel resto del gestionale.
