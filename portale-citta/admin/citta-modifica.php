<?php
/** Creazione e modifica di una città. */
defined( 'PC_AVVIO' ) || exit;

$id      = (string) ( $_GET['id'] ?? '' );
$citta   = '' !== $id ? citta_per_id( $id ) : null;
$nuova   = null === $citta;
$giorni  = array( 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica' );

if ( $nuova ) {
	$citta       = citta_predefinita();
	$citta['id'] = nuovo_id();
	foreach ( $giorni as $g ) {
		$citta['orari'][ $g ] = '';
	}
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	verifica_token();

	$citta['nome']           = trim( (string) ( $_POST['nome'] ?? '' ) );
	$citta['slug']           = citta_slug_libero( (string) ( $_POST['slug'] ?? $citta['nome'] ), $citta['id'] );
	$citta['provincia']      = strtoupper( trim( (string) ( $_POST['provincia'] ?? '' ) ) );
	$citta['regione']        = trim( (string) ( $_POST['regione'] ?? '' ) );
	$citta['cap']            = trim( (string) ( $_POST['cap'] ?? '' ) );
	$citta['lat']            = trim( (string) ( $_POST['lat'] ?? '' ) );
	$citta['lng']            = trim( (string) ( $_POST['lng'] ?? '' ) );
	$citta['telefono']       = trim( (string) ( $_POST['telefono'] ?? '' ) );
	$citta['whatsapp']       = trim( (string) ( $_POST['whatsapp'] ?? '' ) );
	$citta['email']          = trim( (string) ( $_POST['email'] ?? '' ) );
	$citta['indirizzo']      = trim( (string) ( $_POST['indirizzo'] ?? '' ) );
	$citta['mappa']          = trim( (string) ( $_POST['mappa'] ?? '' ) );
	$citta['zone']           = trim( (string) ( $_POST['zone'] ?? '' ) );
	$citta['comuni']         = trim( (string) ( $_POST['comuni'] ?? '' ) );
	$citta['raggiungerci']   = trim( (string) ( $_POST['raggiungerci'] ?? '' ) );
	$citta['intro']          = trim( (string) ( $_POST['intro'] ?? '' ) );
	$citta['perche']         = trim( (string) ( $_POST['perche'] ?? '' ) );
	$citta['certificazioni'] = trim( (string) ( $_POST['certificazioni'] ?? '' ) );
	$citta['css']            = (string) ( $_POST['css'] ?? '' );
	$citta['js']             = (string) ( $_POST['js'] ?? '' );
	$citta['stato']          = 'pubblicata' === ( $_POST['stato'] ?? '' ) ? 'pubblicata' : 'bozza';

	$orari = array();
	foreach ( $giorni as $g ) {
		$orari[ $g ] = trim( (string) ( $_POST['orari'][ $g ] ?? '' ) );
	}
	$citta['orari'] = $orari;

	$citta['numeri'] = array();
	foreach ( (array) ( $_POST['numeri'] ?? array() ) as $n ) {
		if ( vuoto( $n['valore'] ?? '' ) ) {
			continue;
		}
		$citta['numeri'][] = array(
			'valore'    => trim( (string) $n['valore'] ),
			'etichetta' => trim( (string) ( $n['etichetta'] ?? '' ) ),
		);
	}

	$citta['recensioni'] = array();
	foreach ( (array) ( $_POST['recensioni'] ?? array() ) as $r ) {
		if ( vuoto( $r['testo'] ?? '' ) ) {
			continue;
		}
		$citta['recensioni'][] = array(
			'testo' => trim( (string) $r['testo'] ),
			'nome'  => trim( (string) ( $r['nome'] ?? '' ) ),
			'zona'  => trim( (string) ( $r['zona'] ?? '' ) ),
			'voto'  => max( 0, min( 5, (int) ( $r['voto'] ?? 0 ) ) ),
		);
	}

	$citta['team'] = array();
	foreach ( (array) ( $_POST['team'] ?? array() ) as $t ) {
		if ( vuoto( $t['nome'] ?? '' ) ) {
			continue;
		}
		$citta['team'][] = array(
			'nome'       => trim( (string) $t['nome'] ),
			'ruolo'      => trim( (string) ( $t['ruolo'] ?? '' ) ),
			'qualifiche' => trim( (string) ( $t['qualifiche'] ?? '' ) ),
		);
	}

	if ( vuoto( $citta['nome'] ) ) {
		avviso( 'Il nome della città è obbligatorio.', 'errore' );
	} else {
		citta_salva( $citta );

		// Alla creazione generiamo le pagine di base scelte.
		$create = 0;
		if ( $nuova ) {
			foreach ( (array) ( $_POST['genera'] ?? array() ) as $chiave ) {
				if ( genera_pagina_base( $citta, (string) $chiave ) ) {
					$create++;
				}
			}
			foreach ( (array) ( $_POST['genera_servizi'] ?? array() ) as $servizio_id ) {
				if ( genera_pagina_servizio( $citta, (string) $servizio_id ) ) {
					$create++;
				}
			}
		}

		avviso( 'Città salvata.' . ( $create > 0 ? ' Create ' . $create . ' pagine in bozza: apri ognuna e scrivi i testi.' : '' ) );
		vai_a( $nuova ? 'admin.php?p=pagine&citta=' . $citta['id'] : 'admin.php?p=citta-modifica&id=' . $citta['id'] );
	}
}

/** Modelli delle pagine standard che Google si aspetta. */
function pagine_base() {
	return array(
		'home'               => array( 'Principale', '', 'home', 0, 'La pagina raggiungibile da /citta/.' ),
		'chi-siamo'          => array( 'Chi siamo', 'chi-siamo', 'fissa', 20, 'Chi siete, da quanto lavorate, perché fidarsi.' ),
		'servizi'            => array( 'Servizi', 'servizi', 'servizi', 30, 'Elenca da sola tutte le pagine servizio della città.' ),
		'contatti'           => array( 'Contatti', 'contatti', 'fissa', 40, 'Telefono, indirizzo, orari e mappa.' ),
		'domande-frequenti'  => array( 'Domande frequenti', 'domande-frequenti', 'fissa', 50, 'Le FAQ della città.' ),
		'zone-servite'       => array( 'Zone servite', 'zone-servite', 'fissa', 60, 'Quartieri e comuni coperti.' ),
		'recensioni'         => array( 'Recensioni', 'recensioni', 'fissa', 70, 'Cosa dicono i clienti.' ),
	);
}

function genera_pagina_base( $citta, $chiave ) {
	$modelli = pagine_base();
	if ( ! isset( $modelli[ $chiave ] ) ) {
		return false;
	}
	list( $titolo, $slug, $tipo, $ordine ) = $modelli[ $chiave ];
	if ( 'home' === $tipo && pagina_home( $citta['id'] ) ) {
		return false;
	}
	$pagina             = pagina_predefinita();
	$pagina['id']       = nuovo_id();
	$pagina['citta_id'] = $citta['id'];
	$pagina['tipo']     = $tipo;
	$pagina['titolo']   = 'home' === $tipo ? $citta['nome'] : $titolo;
	$pagina['slug']     = 'home' === $tipo ? 'home' : pagina_slug_libero( $slug, $citta['id'] );
	$pagina['menu_ordine'] = $ordine;
	$pagina['menu_mostra'] = 'home' === $tipo ? 0 : 1;
	if ( 'home' === $tipo ) {
		$pagina['h1'] = 'Servizi a ' . $citta['nome'];
	}
	return pagina_salva( $pagina );
}

function genera_pagina_servizio( $citta, $servizio_id ) {
	$s = servizio( $servizio_id );
	if ( ! $s ) {
		return false;
	}
	$pagina                = pagina_predefinita();
	$pagina['id']          = nuovo_id();
	$pagina['citta_id']    = $citta['id'];
	$pagina['servizio_id'] = $s['id'];
	$pagina['tipo']        = 'servizio';
	$pagina['titolo']      = $s['nome'];
	$pagina['slug']        = pagina_slug_libero( $s['slug'], $citta['id'] );
	$pagina['menu_ordine'] = 10;
	return pagina_salva( $pagina );
}

$lista_servizi = servizi();
$immagini      = media_tutti();
?>

<div class="pc-titolo">
	<div>
		<h1><?php echo $nuova ? 'Nuova città' : e( $citta['nome'] ); ?></h1>
		<?php if ( ! $nuova ) : ?>
			<p><a href="<?php echo e( url_citta( $citta ) ); ?>" target="_blank" rel="noopener"><?php echo e( url_citta( $citta ) ); ?> ↗</a></p>
		<?php endif; ?>
	</div>
	<div class="pc-titolo__azioni">
		<a class="pc-btn pc-btn--ghost" href="admin.php?p=citta">← Tutte le città</a>
		<?php if ( ! $nuova ) : ?>
			<a class="pc-btn pc-btn--ghost" href="admin.php?p=pagine&citta=<?php echo e( $citta['id'] ); ?>">Pagine della città</a>
		<?php endif; ?>
	</div>
</div>

<form method="post">
	<?php echo campo_token(); ?>

	<div class="pc-linguette" data-linguette>
		<button type="button" class="pc-linguetta is-attiva" data-pannello="p-base">Dati principali</button>
		<button type="button" class="pc-linguetta" data-pannello="p-contatti">Contatti e orari</button>
		<button type="button" class="pc-linguetta" data-pannello="p-copertura">Copertura</button>
		<button type="button" class="pc-linguetta" data-pannello="p-fiducia">Fiducia</button>
		<button type="button" class="pc-linguetta" data-pannello="p-codice">Codice</button>
		<?php if ( $nuova ) : ?>
			<button type="button" class="pc-linguetta" data-pannello="p-pagine">Pagine da creare</button>
		<?php endif; ?>
	</div>

	<!-- DATI PRINCIPALI -->
	<div class="pc-pannello is-attivo" id="p-base">
		<div class="pc-scheda">
			<h2>Identità della città</h2>
			<p class="pc-scheda__nota">Il nome compare nei titoli, nell'H1 e nei dati strutturati.</p>
			<div class="pc-riga pc-riga--2">
				<label>Nome della città *
					<input type="text" id="campo-nome" name="nome" value="<?php echo e( $citta['nome'] ); ?>" required placeholder="Trapani">
				</label>
				<label>Indirizzo nell'URL
					<input type="text" name="slug" data-slug-da="campo-nome" value="<?php echo e( $citta['slug'] ); ?>" placeholder="trapani">
					<small><?php echo e( base_url() ); ?>/<strong>slug</strong>/</small>
				</label>
			</div>
			<div class="pc-riga pc-riga--3">
				<label>Provincia (sigla) <input type="text" name="provincia" maxlength="4" value="<?php echo e( $citta['provincia'] ); ?>" placeholder="TP"></label>
				<label>Regione <input type="text" name="regione" value="<?php echo e( $citta['regione'] ); ?>" placeholder="Sicilia"></label>
				<label>CAP <input type="text" name="cap" value="<?php echo e( $citta['cap'] ); ?>" placeholder="91100"></label>
			</div>
			<div class="pc-riga pc-riga--2">
				<label>Latitudine <input type="text" name="lat" value="<?php echo e( $citta['lat'] ); ?>" placeholder="38.0176"></label>
				<label>Longitudine <input type="text" name="lng" value="<?php echo e( $citta['lng'] ); ?>" placeholder="12.5365"></label>
			</div>
			<p class="pc-nota">Le coordinate si prendono da Google Maps: clic destro sul punto → il primo valore è la latitudine.</p>
		</div>

		<div class="pc-scheda">
			<h2>Testi della città</h2>
			<p class="pc-scheda__nota">Valgono per tutte le pagine di questa città, se la singola pagina non li sovrascrive.</p>
			<label>Introduzione
				<textarea name="intro" placeholder="Una o due frasi su cosa fate<?php echo vuoto( $citta['nome'] ) ? ' in questa città' : ' a ' . e( $citta['nome'] ); ?>."><?php echo e( $citta['intro'] ); ?></textarea>
			</label>
			<label>Perché sceglierci (una riga per punto)
				<textarea name="perche" placeholder="Interveniamo in 30 minuti&#10;Preventivo fisso concordato prima&#10;Garanzia 12 mesi"><?php echo e( $citta['perche'] ); ?></textarea>
			</label>
		</div>
	</div>

	<!-- CONTATTI -->
	<div class="pc-pannello" id="p-contatti">
		<div class="pc-scheda">
			<h2>Contatti di questa città</h2>
			<p class="pc-scheda__nota">Se lasci vuoto si usa il valore generale delle Impostazioni.</p>
			<div class="pc-riga pc-riga--3">
				<label>Telefono <input type="tel" name="telefono" value="<?php echo e( $citta['telefono'] ); ?>"></label>
				<label>WhatsApp <input type="tel" name="whatsapp" value="<?php echo e( $citta['whatsapp'] ); ?>"></label>
				<label>Email <input type="email" name="email" value="<?php echo e( $citta['email'] ); ?>"></label>
			</div>
			<label>Indirizzo della sede <input type="text" name="indirizzo" value="<?php echo e( $citta['indirizzo'] ); ?>" placeholder="Via Roma 12"></label>
			<label>Indirizzo della mappa incorporata
				<input type="url" name="mappa" value="<?php echo e( $citta['mappa'] ); ?>" placeholder="https://www.google.com/maps/embed?pb=...">
				<small>Su Google Maps: Condividi → Incorpora una mappa → copia solo l'indirizzo dentro <code>src="…"</code>.</small>
			</label>
			<label>Come raggiungerci
				<textarea name="raggiungerci" placeholder="Siamo a cinque minuti dalla stazione, parcheggio davanti al civico."><?php echo e( $citta['raggiungerci'] ); ?></textarea>
			</label>
		</div>

		<div class="pc-scheda">
			<h2>Orari di apertura</h2>
			<p class="pc-scheda__nota">Formato <code>09:00 - 19:00</code>. Lascia vuoto o scrivi "chiuso" per i giorni di chiusura.</p>
			<div class="pc-riga pc-riga--3">
				<?php foreach ( $giorni as $g ) : ?>
					<label><?php echo e( maiuscola( $g ) ); ?>
						<input type="text" name="orari[<?php echo e( $g ); ?>]" value="<?php echo e( $citta['orari'][ $g ] ?? '' ); ?>" placeholder="09:00 - 19:00">
					</label>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- COPERTURA -->
	<div class="pc-pannello" id="p-copertura">
		<div class="pc-scheda">
			<h2>Zone servite</h2>
			<p class="pc-scheda__nota">Alimentano la sezione "Copertura" e il campo <code>areaServed</code> dei dati strutturati.</p>
			<label>Quartieri e zone della città (una riga per voce)
				<textarea name="zone" placeholder="Centro storico&#10;Casa Santa&#10;Xitta"><?php echo e( $citta['zone'] ); ?></textarea>
			</label>
			<label>Comuni limitrofi (una riga per voce)
				<textarea name="comuni" placeholder="Erice&#10;Paceco&#10;Valderice"><?php echo e( $citta['comuni'] ); ?></textarea>
			</label>
			<p class="pc-nota"><strong>Attenzione:</strong> se un comune merita davvero traffico, creagli una città sua. Elencarlo qui non fa posizionare la pagina su quel nome.</p>
		</div>
	</div>

	<!-- FIDUCIA -->
	<div class="pc-pannello" id="p-fiducia">
		<div class="pc-scheda">
			<h2>Numeri</h2>
			<p class="pc-scheda__nota">Due o tre cifre che dicono qualcosa di concreto.</p>
			<div data-ripeti="numeri">
				<?php foreach ( (array) $citta['numeri'] as $i => $n ) : ?>
					<div class="pc-ripeti__voce">
						<button type="button" class="pc-ripeti__togli" title="Togli">×</button>
						<div class="pc-riga pc-riga--2">
							<label>Valore <input type="text" name="numeri[<?php echo (int) $i; ?>][valore]" value="<?php echo e( $n['valore'] ?? '' ); ?>" placeholder="12"></label>
							<label>Etichetta <input type="text" name="numeri[<?php echo (int) $i; ?>][etichetta]" value="<?php echo e( $n['etichetta'] ?? '' ); ?>" placeholder="anni di attività"></label>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo" data-aggiungi="numeri">+ Aggiungi numero</button>
			<template id="modello-numeri">
				<div class="pc-ripeti__voce">
					<button type="button" class="pc-ripeti__togli" title="Togli">×</button>
					<div class="pc-riga pc-riga--2">
						<label>Valore <input type="text" name="numeri[__i__][valore]" placeholder="12"></label>
						<label>Etichetta <input type="text" name="numeri[__i__][etichetta]" placeholder="anni di attività"></label>
					</div>
				</div>
			</template>
		</div>

		<div class="pc-scheda">
			<h2>Recensioni</h2>
			<p class="pc-scheda__nota">Devono essere reali. Le stelle finiscono in <code>aggregateRating</code>: inventarle è contro le linee guida di Google.</p>
			<div data-ripeti="recensioni">
				<?php foreach ( (array) $citta['recensioni'] as $i => $r ) : ?>
					<div class="pc-ripeti__voce">
						<button type="button" class="pc-ripeti__togli" title="Togli">×</button>
						<label>Testo <textarea name="recensioni[<?php echo (int) $i; ?>][testo]"><?php echo e( $r['testo'] ?? '' ); ?></textarea></label>
						<div class="pc-riga pc-riga--3">
							<label>Nome <input type="text" name="recensioni[<?php echo (int) $i; ?>][nome]" value="<?php echo e( $r['nome'] ?? '' ); ?>"></label>
							<label>Zona <input type="text" name="recensioni[<?php echo (int) $i; ?>][zona]" value="<?php echo e( $r['zona'] ?? '' ); ?>"></label>
							<label>Voto (1-5) <input type="number" min="1" max="5" name="recensioni[<?php echo (int) $i; ?>][voto]" value="<?php echo (int) ( $r['voto'] ?? 5 ); ?>"></label>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo" data-aggiungi="recensioni">+ Aggiungi recensione</button>
			<template id="modello-recensioni">
				<div class="pc-ripeti__voce">
					<button type="button" class="pc-ripeti__togli" title="Togli">×</button>
					<label>Testo <textarea name="recensioni[__i__][testo]"></textarea></label>
					<div class="pc-riga pc-riga--3">
						<label>Nome <input type="text" name="recensioni[__i__][nome]"></label>
						<label>Zona <input type="text" name="recensioni[__i__][zona]"></label>
						<label>Voto (1-5) <input type="number" min="1" max="5" name="recensioni[__i__][voto]" value="5"></label>
					</div>
				</div>
			</template>
		</div>

		<div class="pc-scheda">
			<h2>Chi lavora in questa città</h2>
			<p class="pc-scheda__nota">Nomi e qualifiche reali: è il segnale di competenza che Google chiama E-E-A-T.</p>
			<div data-ripeti="team">
				<?php foreach ( (array) $citta['team'] as $i => $t ) : ?>
					<div class="pc-ripeti__voce">
						<button type="button" class="pc-ripeti__togli" title="Togli">×</button>
						<div class="pc-riga pc-riga--2">
							<label>Nome <input type="text" name="team[<?php echo (int) $i; ?>][nome]" value="<?php echo e( $t['nome'] ?? '' ); ?>"></label>
							<label>Ruolo <input type="text" name="team[<?php echo (int) $i; ?>][ruolo]" value="<?php echo e( $t['ruolo'] ?? '' ); ?>"></label>
						</div>
						<label>Qualifiche <input type="text" name="team[<?php echo (int) $i; ?>][qualifiche]" value="<?php echo e( $t['qualifiche'] ?? '' ); ?>" placeholder="Iscrizione CCIAA n. 123456"></label>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="pc-btn pc-btn--ghost pc-btn--piccolo" data-aggiungi="team">+ Aggiungi persona</button>
			<template id="modello-team">
				<div class="pc-ripeti__voce">
					<button type="button" class="pc-ripeti__togli" title="Togli">×</button>
					<div class="pc-riga pc-riga--2">
						<label>Nome <input type="text" name="team[__i__][nome]"></label>
						<label>Ruolo <input type="text" name="team[__i__][ruolo]"></label>
					</div>
					<label>Qualifiche <input type="text" name="team[__i__][qualifiche]"></label>
				</div>
			</template>

			<label style="margin-top:16px">Certificazioni e assicurazioni (una riga per voce)
				<textarea name="certificazioni" placeholder="Attrezzature certificate&#10;Assicurazione RC professionale"><?php echo e( $citta['certificazioni'] ); ?></textarea>
			</label>
		</div>
	</div>

	<!-- CODICE -->
	<div class="pc-pannello" id="p-codice">
		<div class="pc-scheda">
			<h2>CSS e JavaScript della città</h2>
			<p class="pc-scheda__nota">Si applicano a tutte le pagine di questa città, dopo il foglio di stile del tema.</p>
			<label>CSS <textarea class="pc-codice" name="css" spellcheck="false"><?php echo e( $citta['css'] ); ?></textarea></label>
			<label>JavaScript <textarea class="pc-codice" name="js" spellcheck="false"><?php echo e( $citta['js'] ); ?></textarea></label>
		</div>
	</div>

	<!-- PAGINE DA CREARE -->
	<?php if ( $nuova ) : ?>
	<div class="pc-pannello" id="p-pagine">
		<div class="pc-scheda">
			<h2>Pagine da creare subito</h2>
			<p class="pc-scheda__nota">Vengono create in bozza, vuote: i testi li scrivi tu (o l'assistente) pagina per pagina.</p>
			<?php foreach ( pagine_base() as $chiave => $m ) : ?>
				<p class="pc-inline">
					<input type="checkbox" name="genera[]" value="<?php echo e( $chiave ); ?>" id="gen-<?php echo e( $chiave ); ?>"
						<?php echo in_array( $chiave, array( 'home', 'chi-siamo', 'contatti' ), true ) ? 'checked' : ''; ?>>
					<label for="gen-<?php echo e( $chiave ); ?>" style="margin:0;font-weight:600">
						<?php echo e( $m[0] ); ?> <span class="pc-nota" style="font-weight:400"><?php echo e( $m[4] ); ?></span>
					</label>
				</p>
			<?php endforeach; ?>
		</div>

		<?php if ( ! empty( $lista_servizi ) ) : ?>
		<div class="pc-scheda">
			<h2>Pagine servizio</h2>
			<p class="pc-scheda__nota">Una pagina per servizio: è così che ci si posiziona su ricerche distinte.</p>
			<?php foreach ( $lista_servizi as $s ) : ?>
				<p class="pc-inline">
					<input type="checkbox" name="genera_servizi[]" value="<?php echo e( $s['id'] ); ?>" id="srv-<?php echo e( $s['id'] ); ?>" checked>
					<label for="srv-<?php echo e( $s['id'] ); ?>" style="margin:0;font-weight:600">
						<?php echo e( $s['nome'] ); ?> <code class="pc-nota">/<?php echo e( $s['slug'] ); ?>/</code>
					</label>
				</p>
			<?php endforeach; ?>
		</div>
		<?php else : ?>
		<div class="pc-scheda">
			<h2>Pagine servizio</h2>
			<p class="pc-scheda__nota">Non hai ancora definito nessun tipo di servizio. <a href="admin.php?p=servizi">Creane uno</a> e ricomparirà qui.</p>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="pc-salva">
		<label class="pc-inline" style="margin:0">
			<input type="checkbox" name="stato" value="pubblicata" <?php checked_pc( 'pubblicata' === $citta['stato'] ); ?>>
			Città pubblicata (visibile e indicizzabile)
		</label>
		<div class="pc-titolo__azioni">
			<?php if ( ! $nuova ) : ?>
				<a class="pc-btn pc-btn--ghost" href="<?php echo e( url_citta( $citta ) ); ?>" target="_blank" rel="noopener">Anteprima ↗</a>
			<?php endif; ?>
			<button class="pc-btn" type="submit"><?php echo $nuova ? 'Crea la città' : 'Salva'; ?></button>
		</div>
	</div>
</form>
