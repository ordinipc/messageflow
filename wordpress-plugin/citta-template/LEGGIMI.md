# Template città — pagina autonoma in PHP

Una pagina locale completa, senza CMS e senza librerie. Serve solo un hosting con PHP 7.4 o
superiore.

```
citta-template/
├── config.php        ← l'unico file da compilare
├── index.php         ← la pagina (non serve toccarlo)
├── funzioni.php      ← funzioni di supporto
├── assets/
│   ├── style.css
│   └── script.js
└── LEGGIMI.md
```

## Metterla online

1. Carica la cartella sul server, rinominandola con il nome della città:
   `chiaviitalia.it/trapani/`
2. Apri `config.php` e compila i campi.
3. Visita `chiaviitalia.it/trapani/` — `index.php` viene servito da solo.

## Aggiungere una città

Duplica l'intera cartella, rinominala e modifica **solo `config.php`**. Niente altro.

```
/trapani/    config.php → Trapani
/marsala/    config.php → Marsala
/erice/      config.php → Erice
```

I campi lasciati vuoti fanno **sparire la sezione corrispondente**: niente titoli senza
contenuto, niente riquadri a metà. Se non hai recensioni, togli l'array e la sezione non
compare.

## Cosa contiene la pagina

Barra in alto (logo, città, telefono) · intestazione con titolo, testo, pulsanti e indice dei
servizi · navigazione della città · approfondimento · cosa comprende · perché sceglierci con i
numeri · come funziona passo per passo · prezzi · zone servite · recensioni · chi se ne occupa ·
dove siamo con la mappa · orari · domande frequenti · contatti · altre città · piè di pagina.

## SEO già inclusa

- `<title>`, meta description, canonical, Open Graph, meta geo
- Dati strutturati JSON-LD: `LocalBusiness`, `Service`, `FAQPage`
- Le recensioni finiscono in `aggregateRating` **solo se le inserisci davvero**: il file non
  inventa niente

Verifica il risultato con il [Rich Results Test](https://search.google.com/test/rich-results).

## Cambiare i colori

In `config.php`, sezione `colori`:

```php
'colori' => array(
    'accento' => '#ffd400',
    'scuro'   => '#0d0d0d',
    'testo'   => '#111111',
    'chiaro'  => '#f5f5f6',
    'raggio'  => '5px',
),
```

Vengono scritti come variabili CSS: non serve toccare `style.css`.

## Le animazioni non nascondono il contenuto

La comparsa progressiva parte da trasparente, quindi un JavaScript che non parte lascerebbe la
pagina vuota. Per questo il contenuto si nasconde **solo dopo** che uno script nell'intestazione
conferma che JavaScript è attivo, e una rete di sicurezza lo mostra comunque dopo 2,5 secondi.
Senza JavaScript la pagina è visibile e completa.

## Quello che questo template NON fa

Per onestà, così non ci sono sorprese:

- **Nessuna sitemap automatica**: va scritta a mano o generata a parte.
- **Nessun pannello di amministrazione**: si modifica `config.php` via FTP o dal file manager.
- **Nessun collegamento automatico fra città**: gli elenchi `servizi_citta` e `altre_citta` si
  compilano a mano in ogni file.
- **Nessun controllo anti-duplicazione**: se venti città hanno lo stesso testo cambiando solo il
  nome, Google le declassa. È la stessa regola di prima, qui senza nessun avviso a fermarti:
  i campi `approfondimento`, `perche`, `recensioni` e `faq` vanno scritti diversi per ogni città.

## Se una pagina resta bianca

Quasi sempre è un errore di sintassi in `config.php`: una virgola mancante o un apice non
chiuso. Per vederlo, da riga di comando:

```
php -l config.php
```
