<?php
/**
 * Costruzione dei prompt.
 *
 * @package SeoGeoAudit
 */

namespace SeoGeo\Ai;

use SeoGeo\Text;

/**
 * I prompt sono costruiti sulle schede già prodotte dall audit: scaletta,
 * intento di ricerca, lunghezza obiettivo, link interni da inserire. Il modello
 * non decide la strategia, la esegue.
 */
class Prompt {

	/**
	 * Scalette per intento di ricerca.
	 *
	 * @param string $intento Intento.
	 * @param string $keyword Focus keyword.
	 * @param string $citta   Città di riferimento.
	 * @return string[]
	 */
	public static function scaletta( $intento, $keyword, $citta ) {
		switch ( $intento ) {
			case 'transazionale':
				return array(
					"Che cos'è $keyword e a chi serve",
					// Non "fasce di prezzo": chiedere un listino a chi non lo
					// conosce produce una griglia di segnaposto invece di un
					// testo. Che cosa fa salire o scendere il costo lo si puo
					// spiegare senza cifre, ed e la parte utile al lettore.
					"Da cosa dipende il costo di $keyword a $citta",
					'Cosa comprende il servizio, passo per passo',
					'Tempi di realizzazione e svolgimento del lavoro',
					'Errori da evitare nella scelta del fornitore',
					'Un caso reale: problema, intervento, risultato',
					'Domande frequenti',
				);
			case 'commerciale':
				return array(
					"Come scegliere $keyword: i criteri che contano",
					'Confronto fra le soluzioni disponibili',
					'Vantaggi e limiti di ciascuna opzione',
					'Che cosa incide sul budget e come si ripaga',
					'Come valutare i risultati dopo tre mesi',
					'Domande frequenti',
				);
			case 'navigazionale':
				return array( 'Chi siamo e cosa facciamo', "Perché scegliere $keyword", 'I nostri lavori', 'Domande frequenti' );
			default:
				return array(
					Text::truncate( $keyword, 50 ) . ': definizione in due righe',
					'Perché è importante adesso',
					'Come funziona: spiegazione passo per passo',
					'Esempi pratici applicati a una PMI',
					'Errori più comuni e come evitarli',
					'Strumenti e risorse consigliate',
					'Domande frequenti',
				);
		}
	}

	/**
	 * Istruzioni di sistema: identità, vincoli e regola sui dati inventati.
	 *
	 * @param array $cfg Configurazione.
	 * @return string
	 */
	public static function istruzioni( array $cfg ) {
		$a     = $cfg['azienda'];
		$citta = $cfg['seo']['cittaPrincipale'];

		return <<<TXT
Sei un copywriter SEO senior che scrive in italiano per {$a['nome']}, {$a['descrizioneBreve']}
La sede è a {$citta} e i clienti sono piccole e medie imprese e professionisti del territorio.

COME SCRIVI
- Italiano naturale, diretto, in seconda persona singolare quando ti rivolgi al lettore.
- Frasi sotto le 25 parole, paragrafi di 2-3 frasi.
- Concreto: esempi riferiti a mestieri reali (ristoranti, studi medici, negozi, artigiani).
- Niente superlativi vuoti, niente "nel mondo digitale di oggi", niente introduzioni
  che rimandano il punto. La prima frase risponde alla domanda del titolo.

REGOLA NON NEGOZIABILE SUI DATI
Non inventare MAI numeri, percentuali, statistiche, nomi di clienti, premi, anni di
attività, prezzi o risultati. Se un dato servirebbe, scrivi al suo posto un segnaposto
nella forma [DA VERIFICARE: descrizione del dato che serve] e continua. È preferibile un
testo con dieci segnaposto a un testo con un solo dato inventato: un dato falso pubblicato
su un sito aziendale è un danno legale e reputazionale, non un errore di stile.
Vale anche per le recensioni e per i casi studio: descrivi la struttura del caso e lascia
i segnaposto dove servono i fatti reali.

COSA NON FARE
- Non promettere posizionamenti, primi posti su Google o risultati garantiti.
- Non copiare il testo di partenza: riscrivilo, ampliandolo con sostanza nuova.
- Non usare emoji, non usare grassetti a pioggia, non aprire con una domanda retorica.
TXT;
	}

	/**
	 * Prompt per la riscrittura di un articolo.
	 *
	 * @param array $articolo Riga del triage con i dati del documento.
	 * @param array $doc      Documento completo del sito.
	 * @param array $link     Link interni da inserire: titolo => url.
	 * @param array $cfg      Configurazione.
	 * @return string
	 */
	public static function articolo( array $articolo, array $doc, array $link, array $cfg ) {
		$citta    = $cfg['seo']['cittaPrincipale'];
		$keyword  = $articolo['focus'] ?: $articolo['titolo'];
		$scaletta = self::scaletta( $articolo['intento'], $keyword, $citta );
		$obiettivo = $articolo['parole'] < 500 ? 1200 : min( 1800, max( 1000, (int) round( $articolo['parole'] * 1.8 / 100 ) * 100 ) );

		$elencoScaletta = '';
		foreach ( $scaletta as $i => $h ) {
			$elencoScaletta .= sprintf( "%d. %s\n", $i + 1, $h );
		}

		$elencoLink = '';
		foreach ( $link as $anchor => $url ) {
			$elencoLink .= sprintf( "- %s → %s\n", $anchor, $url );
		}
		if ( '' === $elencoLink ) {
			$elencoLink = "- nessuno\n";
		}

		$testoOriginale = Text::truncate( $doc['testo'], 6000 );

		// Il motivo arriva dal triage, ma non tutte le strade lo hanno:
		// senza questa riga una riga senza motivo faceva uscire un avviso
		// PHP dentro alla risposta.
		$motivo = (string) ( $articolo['motivo'] ?? 'struttura e copertura dell intento di ricerca da migliorare' );

		return <<<TXT
Riscrivi questo articolo del blog.

DATI DELLA PAGINA
- Titolo attuale: {$articolo['titolo']}
- URL: {$articolo['url']}
- Focus keyword: {$keyword}
- Intento di ricerca rilevato: {$articolo['intento']}
- Lunghezza attuale: {$articolo['parole']} parole → obiettivo: {$obiettivo} parole
- Motivo della riscrittura: {$motivo}

SCALETTA DA SEGUIRE (usa questi H2, adattandone la formulazione al contenuto)
{$elencoScaletta}
LINK INTERNI DA INSERIRE NEL TESTO (anchor descrittivo, dentro una frase, mai "clicca qui")
{$elencoLink}
REQUISITI
- Apri con un blocco di sintesi di 40-60 parole che risponde subito alla domanda
  principale: è il testo che i motori generativi citano.
- Almeno un elenco puntato.
- NON costruire tabelle di prezzi, listini o fasce di costo: senza le cifre vere
  diventano griglie di segnaposto che non si possono pubblicare. Se il prezzo va
  citato, scrivilo in una frase, una volta sola.
- Una tabella va bene solo per confrontare cose che non sono prezzi (tempi,
  requisiti, differenze fra opzioni) e solo se i dati li hai dal testo originale.
- Chiudi con 4 domande frequenti, risposte di 40-60 parole ciascuna.
- Cita {$citta} e il territorio dove è pertinente, senza forzature.
- La focus keyword compare nel primo paragrafo, in almeno due H2 e nel titolo.

TESTO ATTUALE (da usare come base di contenuto, non da copiare)
---
{$testoOriginale}
---

Rispondi SOLO con un oggetto JSON con questa struttura esatta:
{
  "titolo": "titolo dell articolo, max 65 caratteri",
  "meta_title": "title SEO, max 60 caratteri, con la focus keyword all inizio",
  "meta_description": "meta description fra 140 e 158 caratteri, con la keyword e un invito all azione",
  "in_breve": "il blocco di sintesi di 40-60 parole",
  "corpo_html": "il corpo dell articolo in HTML: <h2>, <h3>, <p>, <ul>, <li>, <table>, <a href>. Nessun <h1>, nessun <html> o <body>.",
  "faq": [{"domanda": "...", "risposta": "..."}],
  "da_verificare": ["elenco dei dati reali che l azienda deve inserire al posto dei segnaposto"],
  "note": "una riga su cosa hai cambiato rispetto all originale"
}
TXT;
	}

	/**
	 * Prompt per migliorare un articolo invece di rifarlo da capo.
	 *
	 * La riscrittura completa butta via anche quello che funzionava: la voce
	 * dell autore, gli esempi veri, i passaggi che il lettore apprezzava.
	 * Qui il testo resta il suo e si interviene solo dove l audit ha
	 * trovato un problema, seguendo la stessa scaletta per intento di
	 * ricerca.
	 *
	 * @param array $articolo Riga del triage con il documento.
	 * @param array $doc      Documento con il testo.
	 * @param array $link     Link interni da inserire.
	 * @param array $problemi Rilievi dell audit su questa pagina.
	 * @param array $cfg      Configurazione.
	 * @return string
	 */
	public static function miglioramento( array $articolo, array $doc, array $link, array $problemi, array $cfg ) {
		$citta    = $cfg['seo']['cittaPrincipale'];
		$keyword  = $articolo['focus'] ?: $articolo['titolo'];
		$scaletta = self::scaletta( $articolo['intento'], $keyword, $citta );

		$elencoScaletta = '';
		foreach ( $scaletta as $i => $h ) {
			$elencoScaletta .= sprintf( "%d. %s\n", $i + 1, $h );
		}

		$elencoLink = '';
		foreach ( $link as $anchor => $url ) {
			$elencoLink .= sprintf( "- %s → %s\n", $anchor, $url );
		}
		if ( '' === $elencoLink ) {
			$elencoLink = "- nessuno\n";
		}

		// I problemi veri trovati su questa pagina: sono la sola ragione per
		// cui si tocca il testo. Senza elenco, il modello rifa tutto.
		$elencoProblemi = '';
		foreach ( $problemi as $p ) {
			$elencoProblemi .= sprintf(
				"- [%s] %s%s\n",
				$p['regola'] ?? '',
				$p['titolo'] ?? '',
				! empty( $p['dettaglio'] ) ? ' — ' . $p['dettaglio'] : ''
			);
		}
		if ( '' === $elencoProblemi ) {
			$elencoProblemi = "- nessun problema specifico: intervieni solo se la scaletta non è coperta\n";
		}

		// Qui serve il testo intero, non un assaggio: quello che non arriva
		// al modello verrebbe perso nel rimando.
		$testoOriginale = Text::truncate( $doc['testo'], (int) ( $cfg['ai']['testo_max_caratteri'] ?? 14000 ) );

		return <<<TXT
Migliora questo articolo del blog. NON riscriverlo da capo.

Il testo è già pubblicato e funziona: il tuo compito è correggere i problemi
elencati, non produrre un articolo nuovo. Conserva la voce dell autore, gli
esempi concreti, i riferimenti a fatti e persone, e tutti i passaggi che non
hanno un problema. Se una sezione va bene, riportala com è.

DATI DELLA PAGINA
- Titolo attuale: {$articolo['titolo']}
- URL: {$articolo['url']}
- Focus keyword: {$keyword}
- Intento di ricerca rilevato: {$articolo['intento']}
- Lunghezza attuale: {$articolo['parole']} parole

PROBLEMI RILEVATI DALL AUDIT SU QUESTA PAGINA (è quello che devi sistemare)
{$elencoProblemi}
SCALETTA DI RIFERIMENTO PER QUESTO INTENTO
Non stravolgere i titoli esistenti per farli combaciare. Aggiungi una sezione
solo se un argomento della scaletta manca del tutto e serve al lettore.
{$elencoScaletta}
LINK INTERNI DA INSERIRE (anchor descrittivo, dentro una frase, mai "clicca qui")
{$elencoLink}
COSA PUOI FARE
- Aggiungere il blocco di sintesi iniziale di 40-60 parole se manca.
- Correggere o aggiungere gli H2 dove la struttura è carente.
- Aggiungere un elenco puntato se i dati sono in un muro di testo.
- Una tabella solo per confrontare cose che non sono prezzi, e solo con dati
  gia presenti nel testo originale.
- Inserire i link interni indicati.
- Sistemare la keyword nel primo paragrafo e nei titoli se non c è.
- Aggiungere le domande frequenti se mancano.

COSA NON DEVI FARE
- Non cancellare sezioni che non hanno problemi.
- Non cambiare affermazioni, cifre, nomi o date già presenti: non li puoi
  verificare. Se un dato manca usa [DA VERIFICARE: ...].
- Non cambiare il tono né la persona (se dà del tu, continua a dare del tu).
- Non allungare per allungare: se l articolo è già completo, tocca poco.
- Non costruire tabelle di prezzi o fasce di costo: senza le cifre vere
  diventano griglie di [DA VERIFICARE] che non si possono pubblicare.

TESTO ATTUALE
---
{$testoOriginale}
---

Rispondi SOLO con un oggetto JSON con questa struttura esatta:
{
  "titolo": "titolo dell articolo, max 65 caratteri: cambialo solo se quello attuale ha un problema",
  "meta_title": "title SEO, max 60 caratteri, con la focus keyword all inizio",
  "meta_description": "meta description fra 140 e 158 caratteri, con la keyword e un invito all azione",
  "in_breve": "il blocco di sintesi di 40-60 parole",
  "corpo_html": "l articolo intero migliorato in HTML: <h2>, <h3>, <p>, <ul>, <li>, <table>, <a href>. Nessun <h1>, nessun <html> o <body>.",
  "faq": [{"domanda": "...", "risposta": "..."}],
  "da_verificare": ["elenco dei dati reali che l azienda deve inserire al posto dei segnaposto"],
  "note": "elenco puntato di cosa hai cambiato e cosa hai lasciato intatto"
}
TXT;
	}

	/**
	 * Prompt per l accorpamento di più articoli sovrapposti.
	 *
	 * @param array $vincitore Articolo che resta.
	 * @param array $assorbiti Articoli che confluiscono.
	 * @param array $link      Link interni da inserire.
	 * @param array $cfg       Configurazione.
	 * @return string
	 */
	public static function accorpamento( array $vincitore, array $assorbiti, array $link, array $cfg ) {
		$citta   = $cfg['seo']['cittaPrincipale'];
		$keyword = $vincitore['focus'] ?: $vincitore['titolo'];

		$fonti = 'ARTICOLO PRINCIPALE (resta online, questo è il suo URL: ' . $vincitore['url'] . ")
"
			. 'Titolo: ' . $vincitore['titolo'] . "\n---\n" . Text::truncate( $vincitore['testo'], 5000 ) . "\n---\n\n";

		foreach ( $assorbiti as $i => $a ) {
			$fonti .= 'ARTICOLO DA ASSORBIRE ' . ( $i + 1 ) . ' (verrà eliminato con un redirect 301 verso il principale)' . "\n"
				. 'Titolo: ' . $a['titolo'] . "\n---\n" . Text::truncate( $a['testo'], 3500 ) . "\n---\n\n";
		}

		$elencoLink = '';
		foreach ( $link as $anchor => $url ) {
			$elencoLink .= sprintf( "- %s → %s\n", $anchor, $url );
		}
		if ( '' === $elencoLink ) {
			$elencoLink = "- nessuno\n";
		}

		$quanti = count( $assorbiti );

		return <<<TXT
Questi {$quanti} articoli più il principale competono per la stessa ricerca su Google:
si tolgono forza a vicenda e nessuno si posiziona. Vanno fusi in un unico articolo,
più completo di ognuno dei precedenti.

FOCUS KEYWORD DELL ARTICOLO UNIFICATO: {$keyword}
LUNGHEZZA OBIETTIVO: 1.400-1.800 parole

COSA DEVI FARE
- Tieni tutto ciò che ha valore in ciascuna fonte: se due spiegano la stessa cosa in modo
  diverso, tieni la versione migliore e integra i dettagli dell altra.
- Elimina le ripetizioni: nel testo finale ogni concetto compare una volta sola.
- Organizza per sezioni tematiche, non per fonte: chi legge non deve accorgersi
  che il testo nasce da più articoli.
- Se le fonti si contraddicono, scegli la versione più prudente e segnala il punto
  con [DA VERIFICARE: due fonti in contrasto su ...].
- Apri con un blocco di sintesi di 40-60 parole.
- Chiudi con 5 domande frequenti che coprano le domande di tutte le fonti.
- Cita {$citta} dove è pertinente.

LINK INTERNI DA INSERIRE
{$elencoLink}
FONTI DA FONDERE

{$fonti}
Rispondi SOLO con un oggetto JSON con questa struttura esatta:
{
  "titolo": "titolo dell articolo unificato, max 65 caratteri",
  "meta_title": "title SEO, max 60 caratteri",
  "meta_description": "meta description fra 140 e 158 caratteri",
  "in_breve": "blocco di sintesi di 40-60 parole",
  "corpo_html": "corpo in HTML con <h2>, <h3>, <p>, <ul>, <table>, <a href>. Nessun <h1>.",
  "faq": [{"domanda": "...", "risposta": "..."}],
  "da_verificare": ["dati reali da inserire"],
  "note": "cosa hai preso da ciascuna fonte, una riga"
}
TXT;
	}

	/**
	 * Prompt per le sole meta di una pagina.
	 *
	 * @param array $doc Documento.
	 * @param array $cfg Configurazione.
	 * @return string
	 */
	public static function meta( array $doc, array $cfg ) {
		$estratto = Text::truncate( $doc['testo'], 1500 );
		$keyword  = $doc['focus'] ?: $doc['titolo'];
		$brand    = $cfg['seo']['brandSuffix'];

		return <<<TXT
Scrivi title SEO e meta description per questa pagina.

- Titolo attuale: {$doc['titolo']}
- Focus keyword: {$keyword}
- Brand: {$brand}

Contenuto della pagina:
---
{$estratto}
---

Vincoli: title massimo 60 caratteri con la keyword nei primi 30; description fra 140 e 158
caratteri, che dica cosa trova il lettore e chiuda con un invito all azione concreto.
Niente promesse di posizionamento, niente dati inventati.

Rispondi SOLO con: {"meta_title": "...", "meta_description": "..."}
TXT;
	}

	/**
	 * Prompt per la ricategorizzazione di un gruppo di articoli.
	 *
	 * @param array $articoli   Elenco di array con titolo e keyword.
	 * @param array $categorie  Nomi delle categorie disponibili.
	 * @return string
	 */
	public static function categorie( array $articoli, array $categorie ) {
		$elenco = '';
		foreach ( $articoli as $a ) {
			$elenco .= sprintf( "- id %s | %s | keyword: %s\n", $a['id'], $a['titolo'], $a['focus'] );
		}

		$disponibili = implode( ', ', $categorie );

		return <<<TXT
Assegna a ciascun articolo la categoria più coerente fra queste: {$disponibili}.
Se nessuna è adatta, proponi il nome di una nuova categoria pertinente al tema.

Articoli:
{$elenco}
Rispondi SOLO con: {"assegnazioni": [{"id": "...", "categoria": "...", "motivo": "max 12 parole"}]}
TXT;
	}
}
