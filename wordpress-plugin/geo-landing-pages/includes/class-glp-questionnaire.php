<?php
/**
 * Definizione del questionario guidato.
 *
 * Ogni campo dichiara: label (la domanda), hint (perché Google la chiede),
 * tipo, peso per il punteggio di completezza ed eventuali sotto-campi.
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GLP_Questionnaire {

	/**
	 * Gruppi di domande.
	 *
	 * @return array
	 */
	public static function groups() {
		$groups = array(

			'luogo' => array(
				'title'  => __( '1. Servizio e luogo', 'geo-landing-pages' ),
				'intro'  => __( 'Sono i dati che rendono la pagina univoca. Senza questi, Google considera la pagina un duplicato delle altre città.', 'geo-landing-pages' ),
				'fields' => array(
					'citta' => array(
						'inherit' => true,
						'label'    => __( 'In quale città (o zona) si trova questa pagina?', 'geo-landing-pages' ),
						'hint'     => __( 'Esempio: Trapani. Viene usato in title, H1, testi e dati strutturati.', 'geo-landing-pages' ),
						'type'     => 'text',
						'weight'   => 10,
						'required' => true,
					),
					'provincia' => array(
						'inherit' => true,
						'label'  => __( 'Sigla della provincia', 'geo-landing-pages' ),
						'hint'   => __( 'Esempio: TP. Serve a disambiguare i comuni omonimi.', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 3,
					),
					'regione' => array(
						'inherit' => true,
						'label'  => __( 'Regione', 'geo-landing-pages' ),
						'hint'   => __( 'Esempio: Sicilia.', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 2,
					),
					'cap' => array(
						'inherit' => true,
						'label'  => __( 'CAP principale', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 2,
					),
					'nazione' => array(
						'inherit' => true,
						'label'   => __( 'Nazione (codice ISO)', 'geo-landing-pages' ),
						'type'    => 'text',
						'default' => 'IT',
						'weight'  => 1,
					),
					'servizio_nome' => array(
						'label'  => __( 'Come si chiama il servizio offerto in questa città?', 'geo-landing-pages' ),
						'hint'   => __( 'Esempio: duplicazione chiavi, apertura porte, pronto intervento fabbro. Se lasci vuoto viene usato il servizio predefinito nelle impostazioni.', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 6,
					),
					'keyword_principale' => array(
						'label'  => __( 'Qual è la ricerca esatta che vuoi intercettare?', 'geo-landing-pages' ),
						'hint'   => __( 'Esempio: duplicazione chiavi Trapani. Una sola keyword per pagina: due pagine sulla stessa keyword si cannibalizzano.', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 6,
					),
					'keyword_secondarie' => array(
						'label'  => __( 'Ricerche correlate da coprire (una per riga)', 'geo-landing-pages' ),
						'hint'   => __( 'Esempio: fabbro Trapani urgente / copia chiavi auto Trapani / apertura porta bloccata Trapani.', 'geo-landing-pages' ),
						'type'   => 'list',
						'weight' => 4,
					),
				),
			),

			'nap' => array(
				'title'  => __( '2. Dati di contatto locali (NAP)', 'geo-landing-pages' ),
				'intro'  => __( 'Name-Address-Phone: devono coincidere carattere per carattere con il tuo profilo Google Business. È il principale segnale di local ranking.', 'geo-landing-pages' ),
				'fields' => array(
					'azienda' => array(
						'inherit' => true,
						'label'  => __( 'Nome dell\'attività così come appare su Google Business', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 5,
					),
					'indirizzo' => array(
						'inherit' => true,
						'label'  => __( 'Hai una sede fisica in questa città? Se sì, indirizzo completo', 'geo-landing-pages' ),
						'hint'   => __( 'Via e numero civico. Se NON hai sede fisica lascia vuoto e compila "zone servite": la pagina verrà marcata come area di servizio, non come sede (dichiarare sedi inesistenti è una violazione delle linee guida Google).', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 5,
					),
					'telefono' => array(
						'inherit' => true,
						'label'  => __( 'Numero di telefono', 'geo-landing-pages' ),
						'hint'   => __( 'Meglio un numero locale o unico e coerente ovunque.', 'geo-landing-pages' ),
						'type'   => 'tel',
						'weight' => 6,
					),
					'whatsapp' => array(
						'inherit' => true,
						'label'  => __( 'Numero WhatsApp (solo cifre, con prefisso internazionale)', 'geo-landing-pages' ),
						'hint'   => __( 'Esempio: 393331234567.', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 2,
					),
					'email' => array(
						'inherit' => true,
						'label'  => __( 'Email di contatto', 'geo-landing-pages' ),
						'type'   => 'email',
						'weight' => 2,
					),
					'lat' => array(
						'inherit' => true,
						'label'  => __( 'Latitudine', 'geo-landing-pages' ),
						'hint'   => __( 'Da Google Maps: clic destro sul punto, il primo valore. Serve per il campo geo dei dati strutturati.', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 3,
					),
					'lng' => array(
						'inherit' => true,
						'label'  => __( 'Longitudine', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 3,
					),
					'mappa_embed_url' => array(
						'inherit' => true,
						'label'  => __( 'URL di embed della mappa', 'geo-landing-pages' ),
						'hint'   => __( 'Google Maps > Condividi > Incorpora una mappa: copia solo l\'URL dentro src="".', 'geo-landing-pages' ),
						'type'   => 'url',
						'weight' => 2,
					),
					'orari' => array(
						'inherit' => true,
						'label'  => __( 'Quali sono gli orari di apertura?', 'geo-landing-pages' ),
						'hint'   => __( 'Finiscono nei dati strutturati e nello snippet "Aperto ora" di Google.', 'geo-landing-pages' ),
						'type'   => 'hours',
						'weight' => 5,
					),
					'h24' => array(
						'inherit' => true,
						'label'  => __( 'Il servizio è attivo 24 ore su 24, 7 giorni su 7?', 'geo-landing-pages' ),
						'type'   => 'checkbox',
						'weight' => 1,
					),
					'metodi_pagamento' => array(
						'inherit' => true,
						'label'  => __( 'Metodi di pagamento accettati (uno per riga)', 'geo-landing-pages' ),
						'type'   => 'list',
						'weight' => 2,
					),
				),
			),

			'prova_locale' => array(
				'title'  => __( '3. Prova di presenza locale', 'geo-landing-pages' ),
				'intro'  => __( 'È la sezione che distingue una landing page reale da una doorway page. Google confronta le tue pagine città tra loro: se cambia solo il nome della città, le declassa tutte.', 'geo-landing-pages' ),
				'fields' => array(
					'perche_noi' => array(
						'ai' => 'perche_noi',
						'label'  => __( 'Perché un cliente di questa città dovrebbe scegliere voi? (un motivo per riga)', 'geo-landing-pages' ),
						'hint'   => __( 'Motivi concreti e verificabili, diversi da città a città. Esempio: intervento in 30 minuti in tutto il centro storico.', 'geo-landing-pages' ),
						'type'   => 'list',
						'weight' => 8,
					),
					'anni_attivita' => array(
						'inherit' => true,
						'label'  => __( 'Da quanti anni operate in questa zona?', 'geo-landing-pages' ),
						'type'   => 'number',
						'weight' => 3,
					),
					'interventi_anno' => array(
						'inherit' => true,
						'label'  => __( 'Quanti clienti/interventi servite qui ogni anno?', 'geo-landing-pages' ),
						'hint'   => __( 'Un numero reale e difendibile. Non gonfiarlo: è il tipo di dato che i clienti verificano.', 'geo-landing-pages' ),
						'type'   => 'number',
						'weight' => 3,
					),
					'tempo_intervento' => array(
						'inherit' => true,
						'label'  => __( 'In quanto tempo arrivate/consegnate in questa città?', 'geo-landing-pages' ),
						'hint'   => __( 'Esempio: entro 30 minuti in centro, entro 1 ora in provincia.', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 4,
					),
					'zone_servite' => array(
						'inherit' => true,
						'label'  => __( 'Quali quartieri e zone coprite in città? (uno per riga)', 'geo-landing-pages' ),
						'hint'   => __( 'I nomi dei quartieri intercettano le ricerche iper-locali e alimentano il campo areaServed.', 'geo-landing-pages' ),
						'type'   => 'list',
						'weight' => 6,
					),
					'comuni_limitrofi' => array(
						'inherit' => true,
						'label'  => __( 'Quali comuni limitrofi servite? (uno per riga)', 'geo-landing-pages' ),
						'type'   => 'list',
						'weight' => 4,
					),
					'come_raggiungerci' => array(
						'ai' => 'come_raggiungerci',
						'inherit' => true,
						'label'  => __( 'Come si raggiunge la sede? Parcheggio, mezzi, riferimenti', 'geo-landing-pages' ),
						'hint'   => __( 'Riferimenti reali del posto (piazze, stazioni, vie) sono il segnale locale più difficile da falsificare.', 'geo-landing-pages' ),
						'type'   => 'textarea',
						'weight' => 4,
					),
					'testimonianze' => array(
						'inherit' => true,
						'label'    => __( 'Recensioni di clienti di questa città', 'geo-landing-pages' ),
						'hint'     => __( 'Solo recensioni reali e verificabili. Inventarle viola le policy Google e la normativa sulle pratiche commerciali scorrette.', 'geo-landing-pages' ),
						'type'     => 'repeater',
						'weight'   => 6,
						'subfields' => array(
							'nome'   => array( 'label' => __( 'Nome cliente', 'geo-landing-pages' ), 'type' => 'text' ),
							'zona'   => array( 'label' => __( 'Zona/quartiere', 'geo-landing-pages' ), 'type' => 'text' ),
							'voto'   => array( 'label' => __( 'Voto (1-5)', 'geo-landing-pages' ), 'type' => 'number' ),
							'data'   => array( 'label' => __( 'Data', 'geo-landing-pages' ), 'type' => 'date' ),
							'testo'  => array( 'label' => __( 'Testo della recensione', 'geo-landing-pages' ), 'type' => 'textarea' ),
							'fonte'  => array( 'label' => __( 'URL della recensione originale', 'geo-landing-pages' ), 'type' => 'url' ),
						),
					),
					'approfondimento' => array(
						'label'  => __( 'Approfondimento: cosa deve sapere chi cerca questo servizio qui', 'geo-landing-pages' ),
						'hint'   => __( 'Due o tre paragrafi di testo utile, specifico per questa città. È la parte che dà profondità alla pagina: se lo lasci vuoto la pagina resta un elenco di dati.', 'geo-landing-pages' ),
						'type'   => 'textarea',
						'weight' => 8,
						'ai'     => 'approfondimento',
					),
					'foto_locali' => array(
						'inherit' => true,
						'label'  => __( 'Hai foto scattate in questa città? (URL, una per riga)', 'geo-landing-pages' ),
						'hint'   => __( 'Foto originali e geolocalizzate valgono più di qualsiasi stock photo.', 'geo-landing-pages' ),
						'type'   => 'list',
						'weight' => 3,
					),
				),
			),

			'eeat' => array(
				'title'  => __( '4. Chi risponde del contenuto (E-E-A-T)', 'geo-landing-pages' ),
				'intro'  => __( 'Esperienza, competenza, autorevolezza, affidabilità: Google chiede di poter identificare una persona o azienda responsabile del servizio.', 'geo-landing-pages' ),
				'fields' => array(
					'referente_nome' => array(
						'inherit' => true,
						'label'  => __( 'Chi è il responsabile/professionista di riferimento?', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 4,
					),
					'referente_ruolo' => array(
						'inherit' => true,
						'label'  => __( 'Qual è il suo ruolo?', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 2,
					),
					'referente_qualifiche' => array(
						'inherit' => true,
						'label'  => __( 'Qualifiche, abilitazioni, numero di iscrizione all\'albo o alla CCIAA', 'geo-landing-pages' ),
						'type'   => 'textarea',
						'weight' => 4,
					),
					'referente_url' => array(
						'inherit' => true,
						'label'  => __( 'URL della pagina biografia/chi siamo', 'geo-landing-pages' ),
						'type'   => 'url',
						'weight' => 2,
					),
					'certificazioni' => array(
						'inherit' => true,
						'label'  => __( 'Certificazioni, assicurazioni e garanzie (una per riga)', 'geo-landing-pages' ),
						'type'   => 'list',
						'weight' => 3,
					),
					'partita_iva' => array(
						'inherit' => true,
						'label'  => __( 'Partita IVA', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 2,
					),
				),
			),

			'offerta' => array(
				'title'  => __( '5. Offerta, prezzi e processo', 'geo-landing-pages' ),
				'intro'  => __( 'Il prezzo è la prima domanda di ogni ricerca locale. Rispondere in pagina riduce il pogo-sticking verso i concorrenti.', 'geo-landing-pages' ),
				'fields' => array(
					'servizi_inclusi' => array(
						'ai' => 'servizi_inclusi',
						'scope' => 'service',
						'label'  => __( 'Cosa comprende esattamente il servizio? (una voce per riga)', 'geo-landing-pages' ),
						'type'   => 'list',
						'weight' => 6,
					),
					'prezzo_da' => array(
						'scope' => 'service',
						'label'  => __( 'Prezzo di partenza (solo numero)', 'geo-landing-pages' ),
						'type'   => 'number',
						'weight' => 5,
					),
					'prezzo_a' => array(
						'scope' => 'service',
						'label'  => __( 'Prezzo massimo indicativo (solo numero)', 'geo-landing-pages' ),
						'type'   => 'number',
						'weight' => 2,
					),
					'valuta' => array(
						'scope' => 'service',
						'label'   => __( 'Valuta', 'geo-landing-pages' ),
						'type'    => 'text',
						'default' => 'EUR',
						'weight'  => 1,
					),
					'fascia_prezzo' => array(
						'scope' => 'service',
						'label'   => __( 'Fascia di prezzo', 'geo-landing-pages' ),
						'hint'    => __( 'Campo priceRange dei dati strutturati.', 'geo-landing-pages' ),
						'type'    => 'select',
						'options' => array( '' => '—', '€' => '€', '€€' => '€€', '€€€' => '€€€', '€€€€' => '€€€€' ),
						'weight'  => 1,
					),
					'preventivo_gratuito' => array(
						'scope' => 'service',
						'label'  => __( 'Il preventivo è gratuito?', 'geo-landing-pages' ),
						'type'   => 'checkbox',
						'weight' => 1,
					),
					'processo' => array(
						'ai' => 'processo',
						'scope' => 'service',
						'label'     => __( 'Come si svolge il servizio, passo per passo?', 'geo-landing-pages' ),
						'hint'      => __( 'Genera la sezione "Come funziona" e può essere mostrato come elenco ordinato nei risultati di ricerca.', 'geo-landing-pages' ),
						'type'      => 'repeater',
						'weight'    => 6,
						'subfields' => array(
							'titolo'      => array( 'label' => __( 'Titolo del passaggio', 'geo-landing-pages' ), 'type' => 'text' ),
							'descrizione' => array( 'label' => __( 'Descrizione', 'geo-landing-pages' ), 'type' => 'textarea' ),
							'durata'      => array( 'label' => __( 'Durata indicativa', 'geo-landing-pages' ), 'type' => 'text' ),
						),
					),
					'garanzia' => array(
						'scope' => 'service',
						'label'  => __( 'Che garanzia offrite sul lavoro svolto?', 'geo-landing-pages' ),
						'type'   => 'textarea',
						'weight' => 3,
					),
				),
			),

			'faq' => array(
				'title'  => __( '6. Domande frequenti (FAQ)', 'geo-landing-pages' ),
				'intro'  => __( 'Sono le domande che compaiono nel riquadro "Le persone chiedono anche". Rispondere con parole tue è ciò che rende il contenuto originale. Usa il pulsante qui sotto per precaricare le domande tipiche della ricerca locale, poi riscrivi le risposte.', 'geo-landing-pages' ),
				'fields' => array(
					'faq' => array(
						'ai' => 'faq',
						'label'     => __( 'Domande e risposte', 'geo-landing-pages' ),
						'hint'      => __( 'Minimo 5 domande consigliate. Le risposte generiche non portano traffico: rispondi con dati della tua città.', 'geo-landing-pages' ),
						'type'      => 'repeater',
						'weight'    => 10,
						'subfields' => array(
							'domanda'  => array( 'label' => __( 'Domanda', 'geo-landing-pages' ), 'type' => 'text' ),
							'risposta' => array( 'label' => __( 'Risposta', 'geo-landing-pages' ), 'type' => 'textarea' ),
						),
					),
				),
			),

			'seo' => array(
				'title'  => __( '7. SEO e indicizzazione', 'geo-landing-pages' ),
				'intro'  => __( 'Se lasci vuoti title e description vengono generati dai modelli impostati nelle opzioni del plugin.', 'geo-landing-pages' ),
				'fields' => array(
					'seo_title' => array(
						'ai' => 'seo_title',
						'label'  => __( 'Title del motore di ricerca', 'geo-landing-pages' ),
						'hint'   => __( 'Massimo ~60 caratteri, con città e servizio all\'inizio.', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 5,
					),
					'seo_description' => array(
						'ai' => 'seo_description',
						'label'  => __( 'Meta description', 'geo-landing-pages' ),
						'hint'   => __( 'Massimo ~155 caratteri, con una ragione per cliccare e una call to action.', 'geo-landing-pages' ),
						'type'   => 'textarea',
						'weight' => 5,
					),
					'robots' => array(
						'label'   => __( 'Indicizzazione', 'geo-landing-pages' ),
						'hint'    => __( 'In automatico la pagina resta noindex finché il punteggio di completezza non supera la soglia impostata: evita di pubblicare pagine deboli che trascinano giù tutto il sito.', 'geo-landing-pages' ),
						'type'    => 'select',
						'options' => array(
							'auto'    => __( 'Automatico (in base al punteggio)', 'geo-landing-pages' ),
							'index'   => __( 'Indicizza sempre', 'geo-landing-pages' ),
							'noindex' => __( 'Non indicizzare mai', 'geo-landing-pages' ),
						),
						'default' => 'auto',
						'weight'  => 0,
					),
					'canonical' => array(
						'label'  => __( 'URL canonico personalizzato', 'geo-landing-pages' ),
						'hint'   => __( 'Da usare solo se questa pagina è una variante di un\'altra.', 'geo-landing-pages' ),
						'type'   => 'url',
						'weight' => 0,
					),
					'og_image' => array(
						'label'  => __( 'Immagine per la condivisione social (URL)', 'geo-landing-pages' ),
						'type'   => 'url',
						'weight' => 2,
					),
					'citta_correlate' => array(
						'label'  => __( 'Città correlate da collegare (slug, uno per riga)', 'geo-landing-pages' ),
						'hint'   => __( 'Link interni tra pagine vicine geograficamente: distribuiscono autorevolezza e aiutano la scansione. Se lasci vuoto vengono suggerite in automatico.', 'geo-landing-pages' ),
						'type'   => 'list',
						'weight' => 2,
					),
				),
			),

			'cta' => array(
				'title'  => __( '8. Conversione', 'geo-landing-pages' ),
				'intro'  => __( 'Una landing page senza azione chiara non converte il traffico che arriva da Google.', 'geo-landing-pages' ),
				'fields' => array(
					'cta_testo' => array(
						'label'   => __( 'Testo del pulsante principale', 'geo-landing-pages' ),
						'type'    => 'text',
						'default' => '',
						'weight'  => 3,
					),
					'cta_url' => array(
						'label'  => __( 'Link del pulsante principale', 'geo-landing-pages' ),
						'hint'   => __( 'Lascia vuoto per usare il numero di telefono.', 'geo-landing-pages' ),
						'type'   => 'url',
						'weight' => 2,
					),
					'form_shortcode' => array(
						'label'  => __( 'Shortcode del modulo di contatto', 'geo-landing-pages' ),
						'hint'   => __( 'Esempio: [contact-form-7 id="123"]. Viene inserito nella sezione finale.', 'geo-landing-pages' ),
						'type'   => 'text',
						'weight' => 2,
					),
				),
			),

			'codice' => array(
				'title'  => __( '9. Codice personalizzato', 'geo-landing-pages' ),
				'intro'  => __( 'Solo per chi sa cosa sta facendo: il codice inserito qui viene stampato nella pagina così com\'è. Un errore di sintassi in JavaScript può bloccare gli script del sito, e script pesanti peggiorano i tempi di caricamento che Google misura. Questi campi sono visibili solo agli amministratori.', 'geo-landing-pages' ),
				'fields' => array(
					'codice_html_top' => array(
						'label'  => __( 'HTML da inserire prima delle sezioni', 'geo-landing-pages' ),
						'hint'   => __( 'Accetta anche gli shortcode. Utile per banner, slider o blocchi del page builder.', 'geo-landing-pages' ),
						'type'   => 'code',
						'cap'    => 'unfiltered_html',
						'weight' => 0,
					),
					'codice_html_bottom' => array(
						'label'  => __( 'HTML da inserire dopo le sezioni', 'geo-landing-pages' ),
						'type'   => 'code',
						'cap'    => 'unfiltered_html',
						'weight' => 0,
					),
					'codice_css' => array(
						'label'  => __( 'CSS solo per questa pagina', 'geo-landing-pages' ),
						'hint'   => __( 'Senza i tag <style>. Viene stampato nell\'intestazione della pagina.', 'geo-landing-pages' ),
						'type'   => 'code',
						'cap'    => 'unfiltered_html',
						'weight' => 0,
					),
					'codice_js' => array(
						'label'  => __( 'JavaScript solo per questa pagina', 'geo-landing-pages' ),
						'hint'   => __( 'Senza i tag <script>. Viene eseguito a fine pagina. Provalo sempre prima su una bozza.', 'geo-landing-pages' ),
						'type'   => 'code',
						'cap'    => 'unfiltered_html',
						'weight' => 0,
					),
				),
			),
		);

		/**
		 * Permette a temi/plugin di aggiungere domande al questionario.
		 *
		 * @param array $groups
		 */
		return apply_filters( 'glp_questionnaire_groups', $groups );
	}

	/**
	 * Elenco piatto dei campi: chiave => definizione.
	 *
	 * @return array
	 */
	public static function fields() {
		static $flat = null;
		if ( null !== $flat ) {
			return $flat;
		}
		$flat = array();
		foreach ( self::groups() as $group_key => $group ) {
			foreach ( $group['fields'] as $key => $field ) {
				$field['group'] = $group_key;
				$flat[ $key ]   = $field;
			}
		}
		return $flat;
	}

	/**
	 * Definizione di un singolo campo.
	 *
	 * @param string $key Chiave.
	 * @return array|null
	 */
	public static function field( $key ) {
		$fields = self::fields();
		return isset( $fields[ $key ] ) ? $fields[ $key ] : null;
	}

	/**
	 * Domande FAQ suggerite, con i segnaposto già inseriti.
	 *
	 * Sono i pattern ricorrenti di "Le persone chiedono anche" per le
	 * ricerche locali di servizio.
	 *
	 * @return array
	 */
	public static function faq_suggestions() {
		$suggestions = array(
			__( 'Quanto costa {servizio} a {citta}?', 'geo-landing-pages' ),
			__( 'Quanto tempo ci vuole per {servizio} a {citta}?', 'geo-landing-pages' ),
			__( 'Intervenite anche di notte e nei festivi a {citta}?', 'geo-landing-pages' ),
			__( 'Quali zone di {citta} coprite?', 'geo-landing-pages' ),
			__( 'Serve un appuntamento o si può venire direttamente?', 'geo-landing-pages' ),
			__( 'Il preventivo è gratuito e vincolante?', 'geo-landing-pages' ),
			__( 'Quali documenti servono per {servizio}?', 'geo-landing-pages' ),
			__( 'Che garanzia avete sul lavoro svolto?', 'geo-landing-pages' ),
			__( 'Quali metodi di pagamento accettate a {citta}?', 'geo-landing-pages' ),
			__( 'Come posso contattarvi in caso di urgenza a {citta}?', 'geo-landing-pages' ),
		);

		return apply_filters( 'glp_faq_suggestions', $suggestions );
	}

	/**
	 * Domande per la pagina FAQ generale del sito.
	 *
	 * Sono scritte apposta senza riferimenti alla città: togliere i
	 * segnaposto da quelle locali produrrebbe frasi sgrammaticate.
	 *
	 * @return array
	 */
	public static function faq_suggestions_site() {
		$suggestions = array(
			__( 'Quanto costa il servizio?', 'geo-landing-pages' ),
			__( 'Quanto tempo ci vuole?', 'geo-landing-pages' ),
			__( 'In quali città intervenite?', 'geo-landing-pages' ),
			__( 'Intervenite anche di notte, nei fine settimana e nei giorni festivi?', 'geo-landing-pages' ),
			__( 'Serve un appuntamento o posso venire direttamente?', 'geo-landing-pages' ),
			__( 'Il preventivo è gratuito e vincolante?', 'geo-landing-pages' ),
			__( 'Quali documenti servono?', 'geo-landing-pages' ),
			__( 'Che garanzia offrite sul lavoro svolto?', 'geo-landing-pages' ),
			__( 'Quali metodi di pagamento accettate?', 'geo-landing-pages' ),
			__( 'Come posso contattarvi in caso di urgenza?', 'geo-landing-pages' ),
		);

		return apply_filters( 'glp_faq_suggestions_site', $suggestions );
	}
}
