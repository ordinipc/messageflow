<?php
/**
 * Applicazione delle correzioni sul sito WordPress.
 *
 * @package SeoGeoAudit
 * @var array  $audit        Riga audit.
 * @var bool   $pronto       Collegamento configurato.
 * @var array[] $archivio     Analisi archiviate da cui ripescare le meta.
 * @var array[] $cambiati     Contenuti che hanno cambiato indirizzo.
 * @var array  $stato        Risposta del sito.
 * @var string $errore_stato Errore della prova di collegamento.
 * @var array  $conteggi     Quantità per ogni operazione.
 */

/**
 * Pulsante di un operazione.
 *
 * @param int    $id         Audit.
 * @param string $azione     Azione.
 * @param string $etichetta  Testo del pulsante.
 * @param string $conferma   Domanda di conferma.
 * @param string $classe     Classe del pulsante.
 * @return void
 */
function azione( $id, $azione, $etichetta, $conferma = '', $classe = 'bottone', $limite = null, $extra = array() ) {
	?>
	<form method="post" action="?p=applica" <?php echo $conferma ? 'onsubmit="return confirm(' . "'" . e( $conferma ) . "'" . ')"' : ''; ?>>
		<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
		<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<input type="hidden" name="azione" value="<?php echo e( $azione ); ?>">
		<?php if ( null !== $limite ) : ?>
			<input type="hidden" name="limite" value="<?php echo (int) $limite; ?>">
		<?php endif; ?>
		<?php foreach ( $extra as $chiave => $valore ) : ?>
			<input type="hidden" name="<?php echo e( $chiave ); ?>" value="<?php echo e( $valore ); ?>">
		<?php endforeach; ?>
		<button class="<?php echo e( $classe ); ?>" type="submit"><?php echo e( $etichetta ); ?></button>
	</form>
	<?php
}

?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>"><?php echo e( $audit['sito_nome'] ); ?></a> › Applica sul sito</p>
	<h1>Applica le correzioni sul sito</h1>
	<p class="guida">Il gestionale parla con il plugin installato su WordPress e scrive direttamente lì: niente copia e incolla. Le meta si possono annullare, le bozze non toccano mai i contenuti pubblicati.</p>
</section>

<?php if ( $esito ) : ?><p class="avviso ok-bg"><?php echo e( $esito ); ?></p><?php endif; ?>
<?php if ( $errore ) : ?><p class="avviso grave"><?php echo e( $errore ); ?></p><?php endif; ?>

<?php if ( ! $pronto ) : ?>
	<section class="scheda">
		<h2>Collegamento da configurare</h2>
		<ol>
			<li>Sul sito WordPress apri <strong>SEO &amp; GEO</strong> nel menu di sinistra.</li>
			<li>Nel riquadro <em>Collegamento con il gestionale</em> copia indirizzo e token.</li>
			<li>Incollali qui, in <a href="?p=impostazioni">Impostazioni</a>.</li>
		</ol>
		<p class="nota">Se non vedi il menu SEO &amp; GEO, il plugin non è attivo: installa lo zip generato dalla scheda dell'audit.</p>
	</section>
<?php elseif ( $errore_stato ) : ?>
	<section class="scheda">
		<h2>Il sito non risponde</h2>
		<p class="avviso grave"><?php echo e( $errore_stato ); ?></p>
		<p>Controlla che il plugin sia attivo, che i permalink non siano impostati su "Semplice" e che l'indirizzo in Impostazioni sia esatto (con <code>https://</code>).</p>
	</section>
<?php else : ?>
	<div class="riquadri">
		<div class="riquadro">
			<span class="etichetta">Sito collegato</span>
			<strong style="font-size:18px"><?php echo e( $stato['sito'] ); ?></strong>
			<span class="sotto"><?php echo e( $stato['url'] ); ?></span>
		</div>
		<div class="riquadro">
			<span class="etichetta">WordPress</span>
			<strong style="font-size:18px"><?php echo e( $stato['wordpress'] ); ?></strong>
			<span class="sotto">plugin <?php echo e( $stato['plugin'] ); ?></span>
		</div>
		<div class="riquadro">
			<span class="etichetta">Contenuti sul sito</span>
			<strong><?php echo num( $stato['articoli'] ); ?></strong>
			<span class="sotto">articoli · <?php echo num( $stato['pagine'] ); ?> pagine</span>
		</div>
		<div class="riquadro">
			<span class="etichetta">Plugin SEO</span>
			<strong style="font-size:18px"><?php echo $stato['rank_math'] ? 'Rank Math' : 'nessuno'; ?></strong>
			<span class="sotto"><?php echo $stato['yoast'] ? '⚠ anche Yoast attivo' : 'nessun conflitto'; ?></span>
		</div>
	</div>

	<?php if ( ! empty( $vecchio ) ) : ?>
		<p class="avviso grave">
			Sul sito è installata la versione <?php echo e( $stato['plugin'] ); ?> del plugin: non sa ricevere i dati
			aziendali né pubblicare le riscritture. Aggiornalo alla 1.1.0 dalla scheda dell'audit
			(Plugin → Aggiungi nuovo → Carica plugin), poi torna qui.
		</p>
	<?php endif; ?>

	<section class="scheda">
		<h2>Dati aziendali</h2>
		<p class="guida">
			Telefono, partita IVA, indirizzo e scheda Google Business vivono nelle Impostazioni del
			gestionale: il plugin da solo non li conosce. Vengono inviati in automatico a ogni
			salvataggio delle impostazioni; da qui puoi rimandarli quando vuoi.
		</p>
		<p>
			<?php if ( ! empty( $stato['config'] ) ) : ?>
				<span class="tag ok">ricevuti dal sito</span>
				<?php if ( empty( $stato['telefono'] ) || empty( $stato['piva'] ) ) : ?>
					<span class="tag alto">ma telefono o partita IVA mancano ancora</span>
				<?php endif; ?>
			<?php else : ?>
				<span class="tag grave">il sito non li ha ancora</span>
			<?php endif; ?>
		</p>
		<p>
			<?php if ( ! empty( $stato['analisi'] ) ) : ?>
				<span class="tag ok">pulsante «Analizza adesso» attivo nella bacheca</span>
			<?php elseif ( ! empty( $stato['config'] ) ) : ?>
				<span class="tag alto">pulsante «Analizza adesso» non ancora attivo: aggiorna il plugin alla 1.3.0 e risalva le impostazioni</span>
			<?php endif; ?>
		</p>
		<div class="azioni"><?php azione( $audit['id'], 'config', 'Invia i dati aziendali al sito' ); ?></div>
	</section>

	<section class="scheda">
		<h2>Meta degli articoli</h2>
		<p class="guida">
			<?php echo num( $conteggi['cambi_articoli'] ); ?> dei <?php echo num( $conteggi['meta_articoli'] ); ?> articoli
			hanno title o description da correggere. Prima di scrivere, il plugin mette da parte i valori
			attuali: l'operazione si può annullare.
		</p>
		<p class="nota">Il modo sensato di procedere: <strong>anteprima</strong> per leggere il confronto, poi <strong>prova su 5</strong> e controlla su WordPress, infine applica a tutti.</p>

		<div class="azioni">
			<?php azione( $audit['id'], 'meta_anteprima', 'Anteprima', '', 'bottone chiaro', null, array( 'ambito' => 'post' ) ); ?>
			<a class="bottone chiaro" href="?p=anteprima&amp;id=<?php echo (int) $audit['id']; ?>">Vedi il confronto</a>
			<?php azione( $audit['id'], 'meta', 'Prova su 5 articoli', 'Applicare le meta ai primi 5 articoli?', 'bottone chiaro', 5, array( 'ambito' => 'post' ) ); ?>
			<?php azione( $audit['id'], 'meta', 'Applica a tutti gli articoli', 'Applicare le meta ottimizzate a ' . $conteggi['meta_articoli'] . ' articoli? I valori attuali verranno conservati.', 'bottone', null, array( 'ambito' => 'post' ) ); ?>
		</div>
	</section>

	<section class="scheda">
		<h2>Meta delle pagine</h2>
		<p class="guida">
			Le <?php echo num( $conteggi['meta_pagine'] ); ?> pagine sono poche e curate a mano: il programma
			propone modifiche solo su <strong><?php echo num( $conteggi['cambi_pagine'] ); ?></strong> di esse.
			Sono tenute separate apposta — se le hai scritte tu e ti convincono, lasciale stare.
		</p>

		<div class="azioni">
			<?php azione( $audit['id'], 'meta_anteprima', 'Anteprima delle pagine', '', 'bottone chiaro', null, array( 'ambito' => 'page' ) ); ?>
			<?php azione( $audit['id'], 'meta', 'Applica alle pagine', 'Applicare le meta alle ' . $conteggi['meta_pagine'] . ' pagine?', 'bottone chiaro', null, array( 'ambito' => 'page' ) ); ?>
		</div>
	</section>

	<section class="scheda">
		<h2>Annulla</h2>
		<p class="guida">
			Riporta title, description ed estratto ai valori che c'erano prima. Il ripristino delle sole
			pagine serve quando gli articoli vanno bene e a essere state toccate per sbaglio sono le
			pagine servizio, che di solito sono scritte a mano.
		</p>
		<div class="azioni">
			<?php azione( $audit['id'], 'annulla_pagine', 'Ripristina solo le pagine', 'Riportare le pagine alle meta precedenti?', 'bottone chiaro' ); ?>
			<?php azione( $audit['id'], 'annulla', 'Annulla tutto e ripristina', 'Ripristinare le meta precedenti su tutti i contenuti?', 'bottone chiaro' ); ?>
		</div>
	</section>

	<?php if ( ! empty( $cambiati ) ) : ?>
		<section class="scheda" id="indirizzi">
			<h2>Indirizzi cambiati</h2>
			<p class="guida">
				<?php echo num( count( $cambiati ) ); ?> contenuti oggi rispondono a un indirizzo diverso da prima:
				succede quando si cambia il titolo e WordPress o chi scrive aggiorna anche lo slug. Il vecchio
				indirizzo da quel momento dà <strong>pagina non trovata</strong>: i link che arrivano da fuori si
				perdono e la posizione in Google riparte da zero.
			</p>

			<div class="tabellabox" style="margin-bottom:14px">
				<table>
					<thead><tr><th>Contenuto</th><th>Indirizzo vecchio</th><th>Nuovo</th></tr></thead>
					<tbody>
					<?php foreach ( array_slice( $cambiati, 0, 30 ) as $riga ) : ?>
						<tr>
							<td><?php echo e( $riga['titolo'] ); ?></td>
							<td class="sotto"><?php echo e( $riga['da'] ); ?></td>
							<td class="sotto"><?php echo e( $riga['a'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="azioni"><?php azione( $audit['id'], 'redirect_cambiati', 'Manda i vecchi indirizzi sui nuovi (301)', 'Creare i redirect 301 dai vecchi indirizzi ai nuovi?' ); ?></div>

			<p class="nota">
				Il plugin applica i 301 solo sulle pagine che danno 404, quindi non interferisce con niente
				di quello che funziona. Questa operazione sostituisce la tabella dei redirect sul sito.
			</p>
		</section>
	<?php endif; ?>

	<section class="scheda">
		<h2>Riporta indietro da un analisi archiviata</h2>
		<p class="guida">
			Seconda rete di sicurezza, che non dipende dal plugin: ogni analisi conserva title,
			description e parola chiave come erano sul sito quel giorno. La prima di tutte viene
			dall export XML, cioè da prima di qualsiasi modifica. Da qui si rimettono sul sito.
		</p>

		<?php if ( ! empty( $archivio ) ) : ?>
			<form method="post" action="?p=applica" onsubmit="return confirm('Riportare title e description come erano in quell analisi?')">
				<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
				<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">
				<input type="hidden" name="azione" value="ripristina_analisi">
				<div class="campo">
					<label for="da_audit">Analisi da cui ripescare</label>
					<select id="da_audit" name="da_audit">
						<?php foreach ( $archivio as $voce ) : ?>
							<option value="<?php echo (int) $voce['id']; ?>">
								<?php echo e( substr( (string) $voce['creato_il'], 0, 16 ) ); ?> —
								<?php echo num( $voce['contenuti'] ); ?> contenuti<?php echo 1 === (int) $voce['id'] ? ' (la prima, dall export XML)' : ''; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<button class="bottone chiaro" type="submit">Rimetti quei title e description</button>
			</form>
		<?php else : ?>
			<p class="nota">Nessuna analisi archiviata da cui ripescare.</p>
		<?php endif; ?>
	</section>

	<section class="scheda">
		<h2>Bozze riscritte</h2>
		<p class="guida">
			<?php if ( $conteggi['bozze'] ) : ?>
				Crea sul sito <?php echo num( min( 25, $conteggi['bozze'] ) ); ?> articoli in stato <strong>Bozza</strong>, collegati agli originali. Nessun contenuto pubblicato viene modificato: confronti, correggi i segnaposto e pubblichi tu.
			<?php else : ?>
				Nessuna bozza ancora generata. Vai a <a href="?p=bozze&amp;id=<?php echo (int) $audit['id']; ?>">Riscrittura assistita</a>.
			<?php endif; ?>
		</p>
		<?php if ( $conteggi['bozze'] ) : ?>
			<div class="azioni"><?php azione( $audit['id'], 'bozze', 'Invia le bozze al sito' ); ?></div>
		<?php endif; ?>
	</section>

	<section class="scheda">
		<h2>Redirect 301</h2>
		<p class="guida">
			Un redirect serve quando un URL sparisce davvero. Gli articoli riscritti restano
			al loro indirizzo e non ne hanno bisogno.
		</p>

		<ul>
			<li><strong><?php echo num( $conteggi['redirect'] ); ?> obbligatori</strong> — contenuti eliminati o assorbiti in un altro articolo. Vanno impostati <strong>prima</strong> di cestinare.</li>
			<li><strong><?php echo num( $conteggi['redirect_slug'] ); ?> facoltativi</strong> — slug accorciati su articoli che restano online. Guadagno marginale, costo certo: attivali solo se hai deciso di cambiare davvero quegli indirizzi in WordPress.</li>
		</ul>

		<div class="azioni">
			<?php azione( $audit['id'], 'redirect', 'Attiva i ' . $conteggi['redirect'] . ' obbligatori' ); ?>
			<?php azione( $audit['id'], 'redirect', 'Attiva anche gli slug accorciati', 'Attivare anche i ' . $conteggi['redirect_slug'] . ' redirect degli slug? Poi dovrai cambiare quegli slug in WordPress, altrimenti non servono a niente.', 'bottone chiaro', null, array( 'slug' => '1' ) ); ?>
		</div>
	</section>

	<section class="scheda">
		<h2>Categorie</h2>
		<p class="guida">Riassegna le categorie ai <?php echo num( $conteggi['categorie'] ); ?> articoli che l'audit segnala come classificati fuori tema.</p>
		<div class="azioni"><?php azione( $audit['id'], 'categorie', 'Ricategorizza', 'Riassegnare le categorie a ' . $conteggi['categorie'] . ' articoli?' ); ?></div>
	</section>
<?php endif; ?>
