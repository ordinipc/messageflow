<?php
/**
 * Configurazione dell'applicazione SEO & GEO Audit.
 *
 * Il database predefinito è SQLite: nessuna installazione, il file viene creato
 * da solo in storage/. Per usare MySQL basta cambiare 'driver' e compilare le
 * credenziali (utile su hosting condiviso dove c'è già un database).
 */

return array(
	'app' => array(
		'nome'     => 'SEO & GEO Audit',
		'versione' => '1.0.0',
		'lingua'   => 'it',
	),

	'database' => array(
		'driver'   => 'sqlite',                          // 'sqlite' oppure 'mysql'
		'sqlite'   => __DIR__ . '/storage/audit.sqlite',
		'mysql'    => array(
			'host'     => '127.0.0.1',
			'porta'    => 3306,
			'nome'     => 'seo_geo_audit',
			'utente'   => 'root',
			'password' => '',
			'charset'  => 'utf8mb4',
		),
	),

	'azienda' => array(
		'nome'             => 'Max Digital Innovation',
		'nomeLegale'       => 'DA_COMPILARE',
		'descrizioneBreve' => 'Agenzia di comunicazione e web agency a Palermo: siti web, e-commerce, video, social media e strategie digitali per PMI.',
		'fondazione'       => 'DA_COMPILARE',
		'partitaIva'       => 'DA_COMPILARE',
		'telefono'         => 'DA_COMPILARE',
		'cellulare'        => 'DA_COMPILARE',
		'email'            => 'info@maxdigitalinnovation.it',
		'whatsapp'         => 'DA_COMPILARE',
		'indirizzo'        => array(
			'via'         => 'DA_COMPILARE',
			'citta'       => 'Palermo',
			'provincia'   => 'PA',
			'cap'         => 'DA_COMPILARE',
			'regione'     => 'Sicilia',
			'nazione'     => 'IT',
			'latitudine'  => 'DA_COMPILARE',
			'longitudine' => 'DA_COMPILARE',
		),
		'orari'            => array(
			array( 'giorni' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ), 'apre' => '09:00', 'chiude' => '18:00' ),
		),
		'areaServita'      => array( 'Palermo', 'Monreale', 'Bagheria', 'Carini', 'Cefalù', 'Termini Imerese', 'Partinico', 'Misilmeri', 'Sicilia' ),
		'fasciaPrezzo'     => '€€',
		'profili'          => array(
			'googleBusiness' => 'DA_COMPILARE',
			'instagram'      => 'https://www.instagram.com/maxdigitalinnovation',
			'facebook'       => 'DA_COMPILARE',
			'linkedin'       => 'DA_COMPILARE',
		),
		'logo'             => 'https://maxdigitalinnovation.it/wp-content/uploads/2026/08/Logo_Bianco_01.svg',
		'immagineFallback' => 'https://maxdigitalinnovation.it/wp-content/uploads/og-default.jpg',
	),

	'autori' => array(
		array(
			'loginWordPress' => 'Maxdigitalinnovation26@',
			'nome'           => 'DA_COMPILARE',
			'cognome'        => 'DA_COMPILARE',
			'ruolo'          => 'DA_COMPILARE',
			'bio'            => 'DA_COMPILARE',
			'linkedin'       => 'DA_COMPILARE',
		),
	),

	// Modulo di riscrittura assistita. Le bozze non vengono mai pubblicate
	// automaticamente: restano in attesa di revisione.
	'ai' => array(
		'provider'           => 'gemini',
		// Chiave da https://aistudio.google.com/apikey — in alternativa si può
		// usare la variabile d ambiente GEMINI_API_KEY, che non finisce nei backup.
		'chiave'             => 'DA_COMPILARE',
		'modello'            => 'gemini-2.5-flash',
		// Generazione delle immagini in evidenza: richiede un progetto Google
		// con fatturazione attiva. Se il modello non è disponibile sul tuo piano,
		// cambia qui il nome senza toccare il codice.
		'modello_immagini'   => 'gemini-2.5-flash-image',
		'temperatura'        => 0.7,
		'max_token'          => 8192,
		'timeout'            => 120,
		'tentativi'          => 3,
		'articoli_per_volta' => 5,
		// Prezzi in euro per milione di token: aggiornali con quelli del tuo
		// piano, servono solo per la stima mostrata prima di lanciare.
		'prezzo_per_milione' => array( 'input' => 0.10, 'output' => 0.40 ),
	),

	// Collegamento con il sito WordPress: permette di applicare le correzioni
	// direttamente, senza copiare e incollare. Il token si genera nel plugin.
	'wordpress' => array(
		'url'   => 'https://maxdigitalinnovation.it',
		'token' => 'DA_COMPILARE',
	),

	'seo' => array(
		'brandSuffix'             => 'Max Digital Innovation',
		'cittaPrincipale'         => 'Palermo',
		'titleMax'                => 60,
		'descMin'                 => 140,
		'descMax'                 => 158,
		'slugMax'                 => 60,
		'linkInterniPerArticolo'  => 4,
		// I link automatici entrano solo negli articoli: le pagine servizio sono
		// poche e curate a mano, e non devono cambiare da sole.
		'linkAutomaticiNellePagine' => false,
		'sogliaQualita'           => 58,
		'dominiNofollow'          => array( 'linktr.ee', 'instagram.com', 'youtube.com', 'wa.me', 'facebook.com', 'tiktok.com' ),
		'paginePilastro'          => array(
			array( 'slug' => 'servizi-digitali-palermo', 'keyword' => 'servizi digitali Palermo' ),
			array( 'slug' => 'realizzazione-siti-web-a-palermo', 'keyword' => 'realizzazione siti web Palermo' ),
			array( 'slug' => 'gestione-social-media-palermo', 'keyword' => 'gestione social media Palermo' ),
			array( 'slug' => 'digital-marketing-palermo', 'keyword' => 'digital marketing Palermo' ),
			array( 'slug' => 'produzione-video-palermo', 'keyword' => 'produzione video Palermo' ),
			array( 'slug' => 'realizzazione-e-commerce-palermo', 'keyword' => 'e-commerce Palermo' ),
			array( 'slug' => 'graphic-design-palermo', 'keyword' => 'graphic design Palermo' ),
			array( 'slug' => 'sviluppo-crm-gestionali-palermo', 'keyword' => 'sviluppo CRM Palermo' ),
			array( 'slug' => 'naming-palermo', 'keyword' => 'naming Palermo' ),
			array( 'slug' => 'stampe-digitali-palermo', 'keyword' => 'stampe digitali Palermo' ),
		),
	),
);
