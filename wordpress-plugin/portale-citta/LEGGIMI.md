# Portale Città — shortcode

Mostra dentro WordPress le pagine (o le singole sezioni) del portale città.

## Installazione

1. Comprimi questa cartella in un file `.zip`.
2. In WordPress: **Plugin → Aggiungi nuovo → Carica plugin** → scegli lo zip → **Installa** → **Attiva**.
3. **Impostazioni → Portale Città** → scrivi l'indirizzo del portale
   (per esempio `https://www.tuosito.it/zone`, senza barra finale) e salva.
   Sotto al campo compare subito ✓ o ✗: se è ✗, l'indirizzo è sbagliato.

## Come si usa

Nel portale, aprendo una pagina, nella colonna a destra trovi lo shortcode già
pronto: ci clicchi sopra e si copia.

```
[portale_citta citta="trapani" pagina="contatti"]
```

| Cosa scrivi | Cosa esce |
|---|---|
| `[portale_citta]` | l'elenco di tutte le zone in cui lavori |
| `[portale_citta sezione="citta"]` | solo la griglia delle zone, senza i testi intorno |
| `[portale_citta citta="trapani"]` | la pagina principale di Trapani |
| `[portale_citta citta="trapani" pagina="contatti"]` | tutta la pagina Contatti |
| `[portale_citta citta="trapani" pagina="contatti" sezione="modulo"]` | solo il modulo di contatto |
| `[portale_citta citta="trapani" sezione="servizi"]` | solo le card dei servizi |
| `... titolo="si"` | aggiunge in cima il titolo della pagina |

Funziona anche nell'editor a blocchi: cerca il blocco **Portale Città**, oppure
usa un blocco *Shortcode*.

## Meglio le sezioni che le pagine intere

Se incorpori una pagina intera, lo stesso testo esiste su due indirizzi — quello
del portale e quello di WordPress — e Google ne sceglie uno solo, scartando
l'altro. Non è un errore tecnico, è uno spreco.

Le sezioni singole invece servono davvero: il modulo di contatto di Trapani
dentro la pagina "Contatti" di WordPress, le card dei servizi dentro la home,
i recapiti dentro una pagina qualsiasi. Quelle non fanno doppione.

## Cose da sapere

- **Il modulo di contatto invia al portale.** Chi lo compila da WordPress
  finisce sulla pagina del portale, dove legge la conferma. L'email ti arriva
  come sempre.
- **La copia locale.** WordPress tiene da parte il contenuto per il tempo che
  imposti (60 minuti di partenza), così non interroga il portale a ogni
  visita. Se hai appena cambiato un testo e non lo vedi, usa il pulsante
  *Ricarica tutto dal portale*, oppure metti 0 minuti mentre lavori.
- **Il foglio di stile.** Il plugin carica quello del portale, così le sezioni
  si vedono come là. Se il tuo tema WordPress fa a pugni con qualcosa, puoi
  toglierlo dalle impostazioni e rifare la grafica tu.
- **Errori.** Se qualcosa non va, il messaggio lo vede solo chi può modificare
  i contenuti. Al visitatore non compare niente.

## Sicurezza

Il contenuto che arriva dal portale passa dal filtro `wp_kses` con una lista di
tag scritta a mano: passano modulo, mappa e icone, non passa `<script>` né
nessun attributo `on...`. Se un giorno l'indirizzo del portale puntasse altrove,
da lì dentro non entra codice eseguibile.
