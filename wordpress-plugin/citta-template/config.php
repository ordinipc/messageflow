<?php
/**
 * DATI DELLA CITTÀ — è l'unico file da compilare.
 *
 * Per aggiungere una città: duplica l'intera cartella, rinominala con il
 * nome della città e modifica questo file. Niente altro.
 *
 * I campi lasciati vuoti (stringa vuota o array vuoto) fanno sparire la
 * sezione corrispondente: non restano buchi né titoli senza contenuto.
 */

return array(

	/* --------------------------------------------------------------
	 * Identità e aspetto
	 * -------------------------------------------------------------- */

	'brand'   => 'Chiavi Italia',
	'logo'    => '',            // es. 'assets/logo.svg'; vuoto = solo il nome
	'sito'    => 'https://chiaviitalia.it/',

	'colori'  => array(
		'accento' => '#ffd400',
		'scuro'   => '#0d0d0d',
		'testo'   => '#111111',
		'chiaro'  => '#f5f5f6',
		'raggio'  => '5px',
	),

	/* --------------------------------------------------------------
	 * Luogo e servizio
	 * -------------------------------------------------------------- */

	'citta'     => 'Trapani',
	'provincia' => 'TP',
	'regione'   => 'Sicilia',
	'cap'       => '91100',
	'nazione'   => 'IT',

	'servizio'  => 'duplicazione chiavi auto',

	// Indirizzo pubblico di QUESTA pagina: serve al canonical e ai dati
	// strutturati. Mettilo esatto, con lo slash finale.
	'url'       => 'https://chiaviitalia.it/trapani/duplicazione-chiavi-auto/',

	/* --------------------------------------------------------------
	 * Contatti
	 * -------------------------------------------------------------- */

	'telefono'  => '389 1943760',
	'whatsapp'  => '393891943760',   // solo cifre, con prefisso internazionale
	'email'     => 'info@chiaviitalia.it',
	'indirizzo' => 'Via Conte Agostino Pepoli 123',
	'piva'      => '',

	'lat'       => '',               // da Google Maps, clic destro sul punto
	'lng'       => '',
	'mappa'     => '',               // URL dentro src="" del codice di incorporamento

	// Giorno => orario. Lascia l'array vuoto per non mostrare la sezione.
	'orari'     => array(
		'Lunedì'    => '09:00 – 19:00',
		'Martedì'   => '09:00 – 19:00',
		'Mercoledì' => '09:00 – 19:00',
		'Giovedì'   => '09:00 – 19:00',
		'Venerdì'   => '09:00 – 19:00',
		'Sabato'    => '09:00 – 13:00',
		'Domenica'  => 'chiuso',
	),

	// Testo accanto al pallino giallo in alto a destra.
	'stato'     => 'Intervento entro 30 minuti',

	/* --------------------------------------------------------------
	 * SEO
	 * -------------------------------------------------------------- */

	'seo' => array(
		// Massimo ~60 caratteri, con servizio e città all'inizio.
		'title'       => 'Duplicazione chiavi auto Trapani | Chiavi Italia',
		// Massimo ~155 caratteri, con un motivo per cliccare.
		'description' => 'Duplicazione chiavi auto a Trapani: transponder, telecomandi e Smart Key programmati in sede. Preventivo rapido, chiama 389 1943760.',
		'immagine'    => '',   // URL assoluto per le condivisioni social
	),

	/* --------------------------------------------------------------
	 * Contenuti
	 * -------------------------------------------------------------- */

	// Paragrafo sotto il titolo, dentro il riquadro scuro.
	'intro' => 'Duplichiamo e programmiamo chiavi auto a Trapani e in provincia: transponder, telecomandi e Smart Key dei principali marchi, senza passare dalla concessionaria.',

	// Due o tre paragrafi separati da una riga vuota.
	'approfondimento' => "Una chiave auto moderna non si copia: si programma. Serve leggere il transponder, scrivere il codice nella centralina e verificare l'avviamento.\n\nA Trapani lavoriamo con attrezzature che coprono la maggior parte dei marchi in circolazione. Per le chiavi con telecomando integrato sostituiamo anche il guscio e la batteria.",

	'perche' => array(
		'Chiavi con transponder programmate in 20 minuti',
		'Attrezzature per la maggior parte dei marchi',
		'Garanzia 12 mesi sulla programmazione',
	),

	'inclusi' => array(
		'Lettura del transponder esistente',
		'Taglio della chiave grezza',
		'Programmazione della centralina',
		'Prova di avviamento insieme al cliente',
	),

	'numeri' => array(
		array( 'valore' => '12',  'etichetta' => 'anni di attività' ),
		array( 'valore' => '800', 'etichetta' => 'interventi all\'anno' ),
	),

	'processo' => array(
		array( 'titolo' => 'Contatto', 'testo' => 'Ci mandi su WhatsApp la foto della chiave e il modello del veicolo.', 'durata' => '5 minuti' ),
		array( 'titolo' => 'Verifica', 'testo' => 'Controlliamo il tipo di transponder e confermiamo prezzo e tempi.', 'durata' => '' ),
		array( 'titolo' => 'Duplicazione', 'testo' => 'Tagliamo la chiave e programmiamo la centralina in sede.', 'durata' => '30-60 minuti' ),
		array( 'titolo' => 'Prova e consegna', 'testo' => 'Proviamo accensione e telecomando insieme a te.', 'durata' => '' ),
	),

	'prezzo' => array(
		'da'         => '15',
		'a'          => '180',
		'valuta'     => 'EUR',
		'gratuito'   => true,          // preventivo gratuito
		'garanzia'   => 'Ogni chiave programmata è garantita 12 mesi: se non funziona la rifacciamo senza costi.',
		'pagamenti'  => array( 'Contanti', 'Bancomat', 'Carte di credito' ),
	),

	'zone'   => array( 'Centro storico', 'Casa Santa', 'Xitta', 'Rilievo' ),
	'comuni' => array( 'Erice', 'Paceco', 'Valderice', 'Custonaci' ),

	'come_raggiungerci' => 'Siamo sulla salita verso il Santuario dell\'Annunziata, a cinque minuti dalla stazione. Parcheggio libero davanti al civico.',

	// SOLO recensioni reali e verificabili.
	'recensioni' => array(
		array( 'nome' => 'Giuseppe M.', 'zona' => 'Centro storico', 'voto' => 5, 'data' => '2026-06-12', 'testo' => 'Persa l\'unica chiave della Fiat 500. Rifatta e programmata in un\'ora.' ),
		array( 'nome' => 'Rosa L.', 'zona' => 'Casa Santa', 'voto' => 5, 'data' => '2026-05-03', 'testo' => 'Chiave con telecomando sostituita e programmata sul posto.' ),
	),

	'team' => array(
		'nome'       => 'Antonino Rizzo',
		'ruolo'      => 'Responsabile tecnico',
		'qualifiche' => 'Iscrizione CCIAA Trapani n. 123456 — formazione certificata sui sistemi Smart Key.',
	),

	'certificazioni' => array(
		'Attrezzature certificate per la programmazione transponder',
		'Assicurazione RC professionale',
	),

	'faq' => array(
		array( 'domanda' => 'Quanto costa duplicare una chiave auto a Trapani?', 'risposta' => 'Si parte da 15 € per una chiave meccanica. Per transponder e telecomandi dipende dal modello: mandaci una foto su WhatsApp e ti diamo la cifra esatta.' ),
		array( 'domanda' => 'Serve il libretto dell\'auto?', 'risposta' => 'Sì: chiediamo libretto e documento del proprietario. È una verifica che tutela te.' ),
		array( 'domanda' => 'Quanto tempo ci vuole?', 'risposta' => 'In genere da trenta a sessanta minuti, compresa la prova di avviamento.' ),
	),

	/* --------------------------------------------------------------
	 * Collegamenti
	 * -------------------------------------------------------------- */

	// Compaiono nell'indice dell'intestazione e nella barra di navigazione.
	'servizi_citta' => array(
		array( 'nome' => 'Duplicazione chiavi casa', 'url' => '/trapani/duplicazione-chiavi-casa/' ),
		array( 'nome' => 'Apertura porte',           'url' => '/trapani/apertura-porte/' ),
		array( 'nome' => 'Serrature e cilindri',     'url' => '/trapani/serrature-cilindri/' ),
	),

	'altre_citta' => array(
		array( 'nome' => 'Marsala', 'url' => '/marsala/' ),
		array( 'nome' => 'Erice',   'url' => '/erice/' ),
	),

	'cta' => array(
		'testo' => 'Richiedi un preventivo',
		'url'   => '',   // vuoto = usa il telefono
	),

	'legali' => array(
		array( 'nome' => 'Privacy Policy', 'url' => '/privacy-policy/' ),
		array( 'nome' => 'Cookie Policy',  'url' => '/cookie-policy/' ),
	),
);
