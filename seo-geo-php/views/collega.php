<?php
/**
 * Applicazione delle correzioni sul sito WordPress.
 *
 * @package SeoGeoAudit
 * @var array  $audit        Riga audit.
 * @var bool   $pronto       Collegamento configurato.
 * @var array[] $archivio     Analisi archiviate da cui ripescare le meta.
 * @var array   $confronto    Esito del confronto fra le analisi.
 * @var string $plugin_qui Versione del plugin che questa copia sa generare.
 * @var array  $stato        Risposta del sito.
 * @var string $errore_stato Errore della prova di collegamento.
 * @var array  $conteggi     Quantità per ogni operazione.
 * @var int[]  $ids_articoli Id WordPress degli articoli, per il ripristino a blocchi.
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
function azione( $id, $azione, $etichetta, $conferma = '', $classe = 'bottone', $limite = null, $extra = array(), $idModulo = '' ) {
	?>
	<form method="post" action="?p=applica" <?php echo $idModulo ? 'id="' . e( $idModulo ) . '"' : ''; ?> <?php echo $conferma ? 'onsubmit="return confirm(' . "'" . e( $conferma ) . "'" . ')"' : ''; ?>>
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

	<?php
	// La versione sul sito contro quella che questa copia del gestionale sa
	// generare. Prima qui si guardava una variabile che nessuno valorizzava:
	// l avviso non e mai comparso, e le versioni che non si parlavano si
	// scoprivano da un errore a meta di un lavoro lungo.
	$plugin_qui  = $plugin_qui ?? '';
	$plugin_la   = (string) ( $stato['plugin'] ?? '' );
	$da_aggiornare = '' !== $plugin_qui && '' !== $plugin_la && version_compare( $plugin_la, $plugin_qui, '<' );
	?>
	<?php if ( $da_aggiornare ) : ?>
		<p class="avviso grave">
			<strong>Il plugin sul sito è indietro.</strong>
			Lì c'è la <?php echo e( $plugin_la ); ?>, questo gestionale genera la <?php echo e( $plugin_qui ); ?>.
			Le cose aggiunte nel frattempo il sito non le sa fare, e te ne accorgeresti da un errore a metà
			di un lavoro lungo. Lo zip aggiornato è nella
			<a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>#plugin">scheda dell'analisi</a>:
			WordPress → Plugin → Aggiungi nuovo → Carica plugin, sovrascrivendo.
		</p>
	<?php elseif ( '' !== $plugin_qui && $plugin_la === $plugin_qui ) : ?>
		<p class="nota">Plugin allineato: <?php echo e( $plugin_la ); ?> sul sito e nel gestionale.</p>
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

	<section class="scheda" id="meta">
		<h2>Meta degli articoli</h2>
		<p class="guida">
			<?php echo num( $conteggi['cambi_articoli'] ); ?> dei <?php echo num( $conteggi['meta_articoli'] ); ?> articoli
			hanno title o description da correggere. Prima di scrivere, il plugin mette da parte i valori
			attuali: l'operazione si può annullare.
		</p>
		<p class="nota">Il modo sensato di procedere: <strong>anteprima</strong> per leggere il confronto, poi <strong>prova su 5</strong> e controlla su WordPress, infine applica agli altri. Vengono inviati solo i <?php echo num( $conteggi['cambi_articoli'] ); ?> che cambiano: gli altri non vengono toccati.</p>

		<div class="azioni">
			<?php azione( $audit['id'], 'meta_anteprima', 'Anteprima', '', 'bottone chiaro', null, array( 'ambito' => 'post' ) ); ?>
			<a class="bottone chiaro" href="?p=anteprima&amp;id=<?php echo (int) $audit['id']; ?>">Vedi il confronto</a>
			<?php azione( $audit['id'], 'meta', 'Prova su 5 articoli', 'Applicare le meta ai primi 5 dei ' . $conteggi['cambi_articoli'] . ' articoli da correggere?', 'bottone chiaro', 5, array( 'ambito' => 'post' ) ); ?>
			<?php azione( $audit['id'], 'meta', 'Applica a tutti gli articoli', 'Applicare le meta ottimizzate ai ' . $conteggi['cambi_articoli'] . ' articoli da correggere? I valori attuali verranno conservati.', 'bottone', null, array( 'ambito' => 'post' ) ); ?>
		</div>

		<?php if ( ! empty( $applicate ) ) : ?>
			<p class="nota">
				Ultima scrittura sul sito: <?php echo e( substr( $applicate, 0, 16 ) ); ?> —
				<a href="?p=anteprima&amp;applicate=1&amp;id=<?php echo (int) $audit['id']; ?>">quali contenuti ho cambiato</a>
			</p>
		<?php endif; ?>
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
			<?php azione( $audit['id'], 'meta', 'Applica alle pagine', 'Applicare le meta alle ' . $conteggi['cambi_pagine'] . ' pagine da correggere?', 'bottone chiaro', null, array( 'ambito' => 'page' ) ); ?>
		</div>
	</section>

	<section class="scheda" id="immagini-pesanti">
		<h2>Immagini pesanti</h2>
		<?php if ( empty( $immagini ) ) : ?>
			<p class="guida">Il sito non risponde: l'elenco delle immagini pesanti si legge dal plugin.</p>
		<?php elseif ( ! empty( $immagini['errore'] ) ) : ?>
			<p class="guida"><?php echo e( $immagini['errore'] ); ?></p>
		<?php else : ?>
			<p class="guida">
				<strong><?php echo num( count( $immagini['sicure'] ) ); ?> ancora da ricomprimere</strong>,
				oltre i <?php echo (int) round( $immagini['soglia'] / 1024 ); ?> KB,
				per <?php echo e( \SeoGeo\Media\Compressione::peso( $immagini['peso_sicure'] ) ); ?> in tutto.
				<?php if ( ! empty( $immagini['gia_fatte'] ) ) : ?>
					Ne hai già fatte <strong><?php echo num( $immagini['gia_fatte'] ); ?></strong>.
				<?php endif; ?>
				L'originale resta sul disco: l'operazione si annulla.
			</p>
			<p class="nota" id="compressione-nota">Il lavoro va a blocchi, perché l'hosting chiude le richieste lunghe: premi una volta sola e la pagina continua da sé fino alla fine. Puoi fermarla quando vuoi, quello che è già fatto resta fatto.</p>

			<div id="compressione-corso" hidden>
				<p>
					<span id="compressione-spia" class="spia"></span>
					<strong id="compressione-titolo">Sto lavorando…</strong>
				</p>
				<div class="barra" style="height:10px;margin-bottom:12px"><i id="compressione-barra" class="ok" style="width:1%;height:10px"></i></div>
				<p class="nota">
					<span id="compressione-fatte">0</span> fatte ·
					<span id="compressione-restanti"><?php echo (int) count( $immagini['sicure'] ); ?></span> da fare ·
					<span id="compressione-risparmio">0 KB</span> risparmiati
				</p>
				<p class="nota" id="compressione-battito">Blocco 1 in corso da 0 secondi. Ogni blocco dura fino a una quarantina di secondi: finché questo numero sale, sta lavorando.</p>
				<p class="nota grave" id="compressione-errori" hidden></p>
				<button type="button" class="bottone chiaro" id="compressione-stop">Ferma</button>
			</div>
			<?php
			// Perche quelle che restano sono ancora li. Senza, la pagina
			// diceva «9 ancora da ricomprimere» e premendo si riottenevano
			// 9: il motivo compariva per un attimo e spariva.
			$falliti = \SeoGeo\Media\Compressione::falliti();
			$resistono = array();

			foreach ( (array) ( $immagini['sicure'] ?? array() ) as $img ) {
				if ( isset( $falliti[ $img['file'] ] ) ) {
					$resistono[] = array( 'file' => $img['file'], 'motivo' => $falliti[ $img['file'] ], 'peso' => $img['peso'] );
				}
			}
			?>
			<?php if ( $resistono ) : ?>
				<p class="nota grave">
					<strong><?php echo num( count( $resistono ) ); ?> hanno già resistito a un tentativo.</strong>
					Premere ancora non le cambia: il motivo è scritto qui sotto, per ognuna.
				</p>
				<details>
					<summary>Quali sono e perché</summary>
					<div class="tabellabox">
						<table>
							<tbody>
							<?php foreach ( array_slice( $resistono, 0, 30 ) as $r ) : ?>
								<tr>
									<td class="mono"><?php echo e( $r['file'] ); ?></td>
									<td><?php echo num( $r['peso'] / 1024 ); ?> KB</td>
									<td><?php echo e( $r['motivo'] ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</details>
				<p class="nota">
					Di solito sono immagini enormi di partenza, o formati che GD non sa riscrivere.
					Per quelle serve un plugin di ottimizzazione immagini, oppure rifarle a mano più piccole.
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $immagini['nel_testo'] ) ) : ?>
				<p class="nota">
					<strong><?php echo num( count( $immagini['nel_testo'] ) ); ?> non vengono toccate</strong>
					perché compaiono dentro il testo di un articolo: ricomprimerle cambia il nome del file
					e l'immagine sparirebbe dalla pagina. Per quelle serve un plugin di ottimizzazione immagini.
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $immagini['gia_ridotte'] ) ) : ?>
				<p class="nota"><?php echo num( count( $immagini['gia_ridotte'] ) ); ?> sono già state ricompresse e restano sopra la soglia: quelle non migliorano.</p>
			<?php endif; ?>
			<?php if ( empty( $immagini['completo'] ) ) : ?>
				<p class="nota">La libreria media è grande: finora ho guardato <?php echo num( $immagini['guardati'] ); ?> allegati su <?php echo num( $immagini['totale'] ); ?>. Comprimi questi, poi ricarica la pagina per vedere i successivi.</p>
			<?php endif; ?>

			<?php if ( ! empty( $immagini['sicure'] ) ) : ?>
				<details>
					<summary>Le dieci più pesanti</summary>
					<table class="widefat">
						<tbody>
						<?php foreach ( array_slice( $immagini['sicure'], 0, 10 ) as $riga ) : ?>
							<tr>
								<td><a href="<?php echo e( $riga['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $riga['file'] ); ?></a></td>
								<td><?php echo e( \SeoGeo\Media\Compressione::peso( $riga['peso'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</details>
			<?php endif; ?>

			<div class="azioni" id="compressione-azioni">
				<?php azione( $audit['id'], 'comprimi_immagini', 'Prova su 5 immagini', 'Ricomprimere le 5 immagini più pesanti? Gli originali restano sul disco.', 'bottone chiaro', 5 ); ?>
				<?php azione( $audit['id'], 'comprimi_immagini', 'Comprimi tutte', 'Ricomprimere ' . count( $immagini['sicure'] ) . ' immagini? Gli originali restano sul disco e si può annullare.', 'bottone', null, array(), 'comprimi-tutte' ); ?>
				<?php azione( $audit['id'], 'ripristina_immagini', 'Rimetti gli originali', 'Rimettere le immagini originali al posto di quelle ricompresse?', 'bottone chiaro' ); ?>
			</div>

			<script>
			(function () {
				var modulo = document.getElementById('comprimi-tutte');
				var corso = document.getElementById('compressione-corso');

				// Senza JavaScript il modulo resta quello di prima: fa un
				// blocco per volta e si ripreme. Non deve smettere di funzionare.
				if (!modulo || !corso) { return; }

				var token = <?php echo json_encode( token() ); ?>;
				var idAudit = <?php echo (int) $audit['id']; ?>;
				var daFare = <?php echo (int) count( $immagini['sicure'] ); ?>;
				// I motivi si accumulano lungo tutti i blocchi: uno per blocco
				// cancellava quelli di prima.
				var saltate = [];
				var partenza = daFare;
				var fatte = 0;
				var risparmio = 0;
				var fermato = false;
				var blocco = 0;
				var iniziatoBlocco = 0;
				var orologio = null;

				var azioni = document.getElementById('compressione-azioni');
				var nota = document.getElementById('compressione-nota');

				function scrivi(id, testo) { document.getElementById(id).textContent = testo; }

				function aggiorna() {
					var percento = partenza ? Math.round((fatte / partenza) * 100) : 100;
					document.getElementById('compressione-barra').style.width = Math.max(1, percento) + '%';
					scrivi('compressione-fatte', fatte.toLocaleString('it-IT'));
					scrivi('compressione-restanti', Math.max(0, daFare).toLocaleString('it-IT'));
				}

				function battito() {
					var secondi = Math.round((Date.now() - iniziatoBlocco) / 1000);

					scrivi('compressione-battito',
						'Blocco ' + blocco + ' in corso da ' + secondi + ' second' + (1 === secondi ? 'o' : 'i')
						+ '. Ogni blocco dura fino a una quarantina di secondi: finché questo numero sale, sta lavorando.');
				}

				function spegni(classe, testo) {
					document.getElementById('compressione-spia').className = 'spia ' + classe;
					scrivi('compressione-titolo', testo);
					scrivi('compressione-battito', '');
					clearInterval(orologio);
					var stop = document.getElementById('compressione-stop');
					stop.textContent = 'Ricarica la pagina';
					stop.onclick = function () { location.reload(); };
				}

				function giro() {
					if (fermato) { return; }

					blocco++;
					iniziatoBlocco = Date.now();
					scrivi('compressione-titolo', 'Sto lavorando…');
					battito();
					clearInterval(orologio);
					orologio = setInterval(battito, 1000);

					fetch('?p=api-comprimi&id=' + idAudit + '&token=' + encodeURIComponent(token))
						.then(function (r) { return r.json(); })
						.then(function (d) {
							if (d.errore) {
								spegni('guasto', 'Interrotta: ' + d.errore);
								return;
							}

							fatte += d.compresse;
							daFare = d.restanti;
							risparmio += d.risparmio;
							scrivi('compressione-risparmio', d.leggibile && risparmio ? formatta(risparmio) : '0 KB');
							aggiorna();

							// Le gia fatte sono avanzamento, non un guasto: senza
							// dirlo, un giro che ne trova venti sembra non avere
							// fatto niente.
							if (d.gia_fatte) {
								scrivi('compressione-titolo', 'Sto lavorando… (' + d.gia_fatte + ' erano già fatte)');
							}

							if (d.errori && d.errori.length) {
								// Si accumulano: prima ogni blocco cancellava
								// i motivi del blocco prima, e alla fine
								// restava solo l ultimo.
								d.errori.forEach(function (riga) {
									if (-1 === saltate.indexOf(riga)) { saltate.push(riga); }
								});

								var p = document.getElementById('compressione-errori');
								p.hidden = false;
								p.textContent = 'Non si sono ridotte: ' + saltate.join(' · ');
							}

							if (d.finito || fermato) {
								spegni(daFare > 0 ? 'guasto' : 'fermo', daFare > 0
									? daFare + ' non si sono ridotte: il motivo di ognuna è qui sotto, e resta scritto anche se ricarichi'
									: 'Fatto: tutte ricompresse');
								return;
							}

							giro();
						})
						.catch(function (e) {
							spegni('guasto', 'Connessione interrotta: ' + e.message);
						});
				}

				function formatta(byte) {
					return byte >= 1048576
						? (byte / 1048576).toFixed(1).replace('.', ',') + ' MB'
						: Math.round(byte / 1024).toLocaleString('it-IT') + ' KB';
				}

				modulo.addEventListener('submit', function (evento) {
					evento.preventDefault();

					if (!confirm('Ricomprimere ' + daFare + ' immagini? Gli originali restano sul disco e si può annullare.')) {
						return;
					}

					azioni.hidden = true;
					nota.hidden = true;
					corso.hidden = false;
					aggiorna();
					giro();
				});

				document.getElementById('compressione-stop').addEventListener('click', function () {
					fermato = true;
					scrivi('compressione-titolo', 'Mi fermo alla fine di questo blocco…');
					document.getElementById('compressione-spia').className = 'spia fermo';
				});
			})();
			</script>
		<?php endif; ?>
	</section>

	<section class="scheda" id="annulla">
		<h2>Annulla</h2>
		<p class="guida">
			Riporta tutto com'era prima: <strong>il testo dell'articolo</strong>, il title, la description,
			l'estratto e le categorie. Se l'articolo è fatto con Elementor viene rimessa anche la sua
			struttura, se no il testo tornerebbe in WordPress e sulla pagina non si vedrebbe.
		</p>
		<p class="nota">
			Gli articoli si rimettono a posto <strong>a blocchi</strong>, uno dopo l'altro, con la barra
			che dice a che punto è: tutti in una richiesta sola l'hosting la chiuderebbe a metà e non si
			saprebbe nemmeno quanti ne sono tornati indietro. Si può fermare quando vuoi, quello che è già
			fatto resta fatto.
		</p>
		<p class="nota">
			Per rimettere il testo di <strong>un articolo solo</strong>, il pulsante «Rimetti il testo di
			prima» è accanto a quell'articolo in
			<a href="?p=confronto-bozze&amp;id=<?php echo (int) $audit['id']; ?>&amp;filtro=online">Vecchio e nuovo → Già online</a>.
			Il ripristino delle sole pagine serve quando gli articoli vanno bene e a essere state toccate
			per sbaglio sono le pagine servizio, che di solito sono scritte a mano.
		</p>
		<div class="azioni" id="annulla-azioni">
			<button class="bottone" type="button" id="annulla-tutti">Rimetti tutti gli articoli com'erano</button>
			<?php azione( $audit['id'], 'annulla_pagine', 'Ripristina solo le pagine', 'Riportare le pagine alle meta precedenti?', 'bottone chiaro' ); ?>
		</div>

	<div id="annulla-corso" hidden>
		<p><span id="annulla-spia" class="spia"></span> <strong id="annulla-titolo">Sto rimettendo i testi di prima…</strong></p>
		<div class="barra" style="height:10px;margin-bottom:12px"><i id="annulla-barra" class="ok" style="width:1%;height:10px"></i></div>
		<p class="nota">
			<span id="annulla-fatti">0</span> di <span id="annulla-totale">0</span> controllati ·
			<span id="annulla-ripristinati">0</span> riportati indietro
		</p>
		<p class="nota" id="annulla-battito"></p>
		<button type="button" class="bottone chiaro" id="annulla-stop">Ferma</button>
	</div>

	<script>
	(function () {
		var avvio = document.getElementById('annulla-tutti');

		if (!avvio || !window.fetch) { return; }

		var token = <?php echo json_encode( token() ); ?>;
		var idAudit = <?php echo (int) $audit['id']; ?>;
		var coda = <?php echo json_encode( array_values( $ids_articoli ?? array() ) ); ?>;
		var totale = coda.length;
		var fatti = 0;
		var ripristinati = 0;
		var fermato = false;
		var iniziato = 0;
		var orologio = null;

		// A blocchi piccoli: ogni ripristino riscrive il contenuto e la
		// struttura di Elementor, che non e un lavoro istantaneo.
		var PER_GIRO = 8;

		function scrivi(id, testo) { document.getElementById(id).textContent = testo; }

		function battito() {
			var secondi = Math.round((Date.now() - iniziato) / 1000);
			scrivi('annulla-battito', 'Blocco in corso da ' + secondi + ' second' + (1 === secondi ? 'o' : 'i')
				+ '. Finche questo numero sale, sta lavorando.');
		}

		function spegni(classe, testo) {
			document.getElementById('annulla-spia').className = 'spia ' + classe;
			scrivi('annulla-titolo', testo);
			scrivi('annulla-battito', '');
			clearInterval(orologio);
			var stop = document.getElementById('annulla-stop');
			stop.textContent = 'Ricarica la pagina';
			stop.onclick = function () { location.reload(); };
		}

		function giro() {
			if (fermato || !coda.length) {
				spegni('fermo', fermato
					? 'Fermato: ' + ripristinati + ' articoli riportati indietro'
					: 'Fatto: ' + ripristinati + ' articoli riportati indietro');
				return;
			}

			var blocco = coda.splice(0, PER_GIRO);

			iniziato = Date.now();
			battito();
			clearInterval(orologio);
			orologio = setInterval(battito, 1000);

			fetch('?p=api-annulla&id=' + idAudit + '&token=' + encodeURIComponent(token) + '&ids=' + blocco.join(','))
				.then(function (r) { return r.json(); })
				.then(function (d) {
					if (d.errore) { spegni('guasto', 'Interrotto: ' + d.errore); return; }

					fatti += d.fatti || blocco.length;
					ripristinati += d.ripristinati || 0;

					scrivi('annulla-fatti', fatti);
					scrivi('annulla-ripristinati', ripristinati);
					document.getElementById('annulla-barra').style.width =
						Math.max(1, Math.round((fatti / totale) * 100)) + '%';

					giro();
				})
				.catch(function (e) { spegni('guasto', 'Connessione interrotta: ' + e.message); });
		}

		avvio.addEventListener('click', function () {
			if (!totale) { alert('Non ci sono articoli da rimettere a posto.'); return; }

			if (!confirm('Rimettere ' + totale + ' articoli come erano prima della riscrittura? Torna il testo, il titolo e la struttura di Elementor.')) {
				return;
			}

			document.getElementById('annulla-azioni').hidden = true;
			document.getElementById('annulla-corso').hidden = false;
			scrivi('annulla-totale', totale);
			giro();
		});

		document.getElementById('annulla-stop').addEventListener('click', function () {
			fermato = true;
			scrivi('annulla-titolo', 'Mi fermo alla fine di questo blocco…');
			document.getElementById('annulla-spia').className = 'spia fermo';
		});
	})();
	</script>
	</section>

	<?php $cambiati = $confronto['cambiati']; ?>
	<?php $orfani = $orfani ?? array(); ?>

	<section class="scheda" id="indirizzi">
		<h2>Indirizzi cambiati</h2>

		<?php if ( ! empty( $confronto['ultima'] ) && ! empty( $confronto['prima'] ) ) : ?>
			<p class="nota">
				Confronto fra l analisi del <?php echo e( substr( (string) $confronto['prima']['creato_il'], 0, 16 ) ); ?>
				e quella del <?php echo e( substr( (string) $confronto['ultima']['creato_il'], 0, 16 ) ); ?>,
				su <?php echo num( $confronto['confrontati'] ); ?> contenuti presenti in entrambe.
			</p>
		<?php endif; ?>

		<?php if ( ! $cambiati ) : ?>
			<p class="guida"><?php echo e( $confronto['motivo'] ?: 'Nessun indirizzo cambiato.' ); ?></p>
		<?php endif; ?>

	<?php if ( $cambiati ) : ?>
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

	<?php endif; ?>

		<?php if ( ! empty( $orfani ) ) : ?>
			<h3>Indirizzi che Google mostra e il sito non ha più</h3>
			<p class="guida">
				Questi non li può trovare il confronto fra due analisi: il programma non li ha mai visti,
				perché erano già spariti prima della prima lettura. Li conosce Google, che continua a
				mostrarli e a mandarci gente: <strong><?php echo num( array_sum( array_column( $orfani, 'impression' ) ) ); ?> impression</strong>
				che oggi finiscono su una pagina non trovata. La destinazione qui sotto è proposta dalle
				parole dell'indirizzo: <strong>guardala prima di applicare</strong>.
			</p>

			<div class="tabellabox" style="margin-bottom:14px">
				<table>
					<thead><tr><th>Indirizzo morto</th><th>Dove mandarlo</th><th class="num">Impr.</th><th class="num">Clic</th></tr></thead>
					<tbody>
					<?php foreach ( $orfani as $riga ) : ?>
						<tr>
							<td class="mono"><?php echo e( $riga['da'] ); ?></td>
							<td>
								<span class="mono"><?php echo e( $riga['a'] ); ?></span>
								<div class="sotto">
									<?php echo $riga['titolo'] ? e( $riga['titolo'] ) . ' · ' : ''; ?><?php echo e( $riga['perche'] ); ?>
								</div>
							</td>
							<td class="num"><?php echo num( $riga['impression'] ); ?></td>
							<td class="num"><?php echo num( $riga['clic'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php if ( $cambiati || ! empty( $orfani ) ) : ?>
			<div class="azioni"><?php azione( $audit['id'], 'redirect_cambiati', 'Manda i vecchi indirizzi sui nuovi (301)', 'Creare i redirect 301? Controlla prima le destinazioni proposte.' ); ?></div>

			<p class="nota">
				Il plugin applica i 301 solo sulle pagine che danno 404, quindi non interferisce con niente
				di quello che funziona. I due elenchi vengono mandati insieme, perché questa operazione
				sostituisce la tabella dei redirect sul sito.
			</p>
		<?php endif; ?>
	</section>

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

	<section class="scheda" id="redirect">
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

	<section class="scheda" id="cestina">
		<h2>Cestina i contenuti assorbiti</h2>
		<p class="guida">
			Gli articoli che un accorpamento ha assorbito, e quelli che l'audit ha classificato da
			eliminare, restano pubblicati finché non li togli: il loro testo è anche dentro all'articolo
			principale, quindi il contenuto resta doppio. Sono <strong><?php echo num( $conteggi['redirect'] ); ?></strong>.
		</p>
		<p class="nota">
			Vanno nel <strong>cestino di WordPress</strong>, non cancellati: da lì si recuperano.
			<?php
			$redirect_attivi = (int) ( $stato['redirect'] ?? 0 );
			$redirect_serve  = (int) $conteggi['redirect'];
			?>
			<?php if ( $redirect_serve > 0 && $redirect_attivi < $redirect_serve ) : ?>
				<br><strong>Prima però servono i redirect:</strong> sul sito ne risultano attivi
				<?php echo num( $redirect_attivi ); ?> su <?php echo num( $redirect_serve ); ?>.
				Premi «Attiva i <?php echo num( $redirect_serve ); ?> obbligatori» qui sopra, poi torna qui.
				Cestinare adesso darebbe pagina non trovata a chi arriva da Google.
			<?php else : ?>
				I <?php echo num( $redirect_attivi ); ?> redirect risultano attivi sul sito: si può procedere.
			<?php endif; ?>
		</p>
		<div class="azioni">
			<?php
			azione(
				$audit['id'],
				'cestina',
				'Sposta nel cestino i ' . $conteggi['redirect'] . ' assorbiti',
				'Spostare ' . $conteggi['redirect'] . ' contenuti nel cestino di WordPress? Si recuperano da lì, e i redirect restano attivi.',
				$redirect_serve > 0 && $redirect_attivi < $redirect_serve ? 'bottone chiaro' : 'bottone'
			);
			?>
		</div>
	</section>

	<section class="scheda" id="categorie">
		<h2>Categorie</h2>
		<p class="guida">Riassegna le categorie ai <?php echo num( $conteggi['categorie'] ); ?> articoli che l'audit segnala come classificati fuori tema.</p>
		<div class="azioni"><?php azione( $audit['id'], 'categorie', 'Ricategorizza', 'Riassegnare le categorie a ' . $conteggi['categorie'] . ' articoli?' ); ?></div>
	</section>
<?php endif; ?>
