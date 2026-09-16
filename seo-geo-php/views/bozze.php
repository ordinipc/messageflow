<?php
/**
 * Riscrittura assistita: stima, generazione a lotti ed elenco delle bozze.
 *
 * @package SeoGeoAudit
 * @var string $regola    Problema da cui si e arrivati, se c e.
 * @var array  $rilievo   Riga del rilievo corrispondente.
 * @var array  $da_regola Contenuti che hanno quel problema.
 * @var array  $esclusi   Occorrenze della regola che non finiscono in coda, col perche.
 * @var array  $contese   Ricerche contese: chi vince e chi cede.
 * @var string $cerca     Testo cercato, se c e.
 * @var array  $trovati   Contenuti che corrispondono alla ricerca.
 * @var array $audit  Riga audit.
 * @var array $cfg    Configurazione.
 * @var bool  $pronto Chiave API presente.
 * @var array $stima  Stima di token e costo.
 * @var array $bozze  Bozze già generate.
 */

$riuscite = array_filter( $bozze, static fn( $b ) => 'ok' === $b['stato'] );
$errate   = array_filter( $bozze, static fn( $b ) => 'ok' !== $b['stato'] );

// Questa vista si apre da piu strade: senza questa riga, da quelle che non
// calcolano le ricerche contese la pagina uscirebbe con un avviso di PHP
// dentro.
$contese  = $contese ?? array();
$cerca    = $cerca ?? '';
$trovati  = $trovati ?? array();

?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>"><?php echo e( $audit['sito_nome'] ); ?></a> › Riscrittura</p>
	<h1>Riscrittura assistita</h1>
	<p class="guida">Le bozze partono dalle schede dell'audit: scaletta per intento di ricerca, lunghezza obiettivo, link interni da inserire. <strong>Nulla viene pubblicato</strong>: i testi restano qui in attesa di revisione.</p>
</section>

<?php if ( $errore ) : ?><p class="avviso grave"><?php echo e( $errore ); ?></p><?php endif; ?>

<?php if ( $fatte || $errori ) : ?>
	<?php
	$etichette = array( 'accorpa' => 'accorpamenti', 'immagini' => 'immagini', 'articoli' => 'bozze' );
	$cosa      = $etichette[ $tipo ] ?? 'bozze';
	?>
	<p class="avviso <?php echo $errori ? 'grave' : 'ok-bg'; ?>">
		Ultimo lotto: <?php echo (int) $fatte; ?> <?php echo e( $cosa ); ?><?php echo $errori ? ', ' . (int) $errori . ' errori' : ''; ?>.
	</p>
<?php endif; ?>

<?php if ( 'chiave' === $esito || ! $pronto ) : ?>
	<section class="scheda">
		<h2>Manca la chiave API</h2>
		<p>Il modulo usa <strong>Google Gemini</strong> (modello <code><?php echo e( $cfg['ai']['modello'] ); ?></code>).</p>
		<ol>
			<li>Crea una chiave gratuita su <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</a>.</li>
			<li>Aprila in <code>config.php</code> e incollala in <code>'ai' =&gt; array( 'chiave' =&gt; '...' )</code>.</li>
			<li>In alternativa impostala come variabile d'ambiente <code>GEMINI_API_KEY</code>: non finisce nei backup del sito.</li>
		</ol>
		<p class="nota">Finché la chiave manca, il resto dell'applicazione funziona normalmente: la riscrittura è un modulo separato.</p>
	</section>
<?php endif; ?>

<div class="riquadri">
	<div class="riquadro">
		<span class="etichetta">Articoli in coda</span>
		<strong><?php echo num( $stima['articoli'] ); ?></strong>
		<span class="sotto">da riscrivere o accorpare</span>
	</div>
	<div class="riquadro">
		<span class="etichetta">Bozze pronte</span>
		<strong><?php echo num( count( $riuscite ) ); ?></strong>
		<span class="sotto"><?php echo count( $errate ) ? num( count( $errate ) ) . ' con errori' : 'nessun errore'; ?></span>
	</div>
	<div class="riquadro">
		<span class="etichetta">Costo stimato totale</span>
		<strong><?php echo number_format( $stima['costo_stimato'], 2, ',', '.' ); ?> €</strong>
		<span class="sotto"><?php echo num( $stima['token_in'] + $stima['token_out'] ); ?> token · prezzi da config.php</span>
	</div>
	<div class="riquadro">
		<span class="etichetta">Modello</span>
		<strong style="font-size:17px"><?php echo e( $cfg['ai']['modello'] ); ?></strong>
		<span class="sotto">Google Gemini</span>
	</div>
</div>

<?php
// Alcuni problemi non si risolvono riscrivendo un articolo per volta: due
// pagine che si contendono la stessa ricerca vanno fuse in una. Mandare
// alla riscrittura singola sarebbe mandare allo strumento sbagliato.
$daFondere = in_array( $regola, array( 'LOC-05', 'ONP-06', 'CNT-03' ), true );
?>
<?php $esclusi = $esclusi ?? array(); ?>
<?php if ( ! empty( $regola ) ) : ?>
<section class="scheda">
	<h2>Stai correggendo <?php echo e( $regola ); ?><?php echo ! empty( $rilievo['titolo'] ) ? ': ' . e( $rilievo['titolo'] ) : ''; ?></h2>
	<?php if ( ! empty( $rilievo['perche'] ) ) : ?>
		<p class="guida"><?php echo e( $rilievo['perche'] ); ?></p>
	<?php endif; ?>

	<?php if ( $da_regola ) : ?>
		<?php
		// Quelli che il problema ce l hanno ma non sono in coda, e perche.
		// Serve a rispondere alla domanda «ma non li avevamo gia riscritti
		// ieri?»: chi e in questo elenco non e mai passato dalla riscrittura,
		// e chi c e passato sta fra gli esclusi con scritto quello.
		$giaFatti = array_values( array_filter( $esclusi, static fn( $x ) => 'bozza' === $x['azione'] ) );
		$altriNo  = array_values( array_filter( $esclusi, static fn( $x ) => 'bozza' !== $x['azione'] ) );
		?>
		<p class="guida">
			Riguarda <strong><?php echo num( count( $da_regola ) ); ?> contenuti</strong>.
			<?php if ( ! $daFondere ) : ?>
				La generazione qui sotto lavora <strong>solo su questi</strong>: non ricomincia da capo
				su tutto l'archivio.
			<?php endif; ?>
		</p>
		<p class="guida">
			<strong>Nessuno di questi è ancora passato dalla riscrittura.</strong>
			<?php if ( $giaFatti ) : ?>
				Altri <?php echo num( count( $giaFatti ) ); ?> con lo stesso problema una riscrittura ce
				l'hanno già: sono fuori da questo elenco e li trovi in
				<a href="?p=confronto-bozze&amp;id=<?php echo (int) $audit['id']; ?>">Vecchio e nuovo</a>.
			<?php else : ?>
				Non risultano riscritture fatte per questo problema.
			<?php endif; ?>
			<?php if ( $altriNo ) : ?>
				Altri <?php echo num( count( $altriNo ) ); ?> restano fuori per altri motivi (pagine,
				contenuti già sistemati): sono elencati in fondo al riquadro.
			<?php endif; ?>
		</p>
		<details>
			<summary>Quali sono</summary>
			<ul class="guida">
				<?php foreach ( array_slice( $da_regola, 0, 40 ) as $riga ) : ?>
					<li><a href="<?php echo e( $riga['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $riga['titolo'] ); ?></a> · <?php echo num( $riga['parole'] ); ?> parole</li>
				<?php endforeach; ?>
				<?php if ( count( $da_regola ) > 40 ) : ?>
					<li>e altri <?php echo num( count( $da_regola ) - 40 ); ?></li>
				<?php endif; ?>
			</ul>
		</details>
		<?php if ( $esclusi ) : ?>
			<details>
				<summary>Gli altri <?php echo num( count( $esclusi ) ); ?> con lo stesso problema, e perché non sono in coda</summary>
				<div class="tabellabox">
					<table>
						<thead><tr><th>Contenuto</th><th>Rilevato</th><th>Perché non è in coda</th></tr></thead>
						<tbody>
						<?php foreach ( array_slice( $esclusi, 0, 60 ) as $x ) : ?>
							<tr>
								<td>
									<?php if ( $x['url'] ) : ?>
										<a href="<?php echo e( $x['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $x['titolo'] ); ?></a>
									<?php else : ?>
										<?php echo e( $x['titolo'] ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo e( $x['dettaglio'] ); ?></td>
								<td><?php echo e( $x['motivo'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</details>
		<?php endif; ?>
	<?php elseif ( ! empty( $esclusi ) ) : ?>
		<?php $aMano = array_values( array_filter( $esclusi, static fn( $x ) => 'manuale' === $x['azione'] ) ); ?>
		<p class="avviso">
			La riscrittura assistita non ha niente da fare qui, ma il problema c'è ancora su
			<strong><?php echo num( count( $esclusi ) ); ?></strong>
			<?php echo 1 === count( $esclusi ) ? 'contenuto' : 'contenuti'; ?>.
			Ecco quali sono e perché nessuno di loro finisce in coda.
		</p>
		<div class="tabellabox">
			<table>
				<thead><tr><th>Contenuto</th><th>Rilevato</th><th>Perché non è in coda</th></tr></thead>
				<tbody>
				<?php foreach ( $esclusi as $x ) : ?>
					<tr>
						<td>
							<?php if ( $x['url'] ) : ?>
								<a href="<?php echo e( $x['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $x['titolo'] ); ?></a>
							<?php else : ?>
								<?php echo e( $x['titolo'] ); ?>
							<?php endif; ?>
							<div class="sotto mono"><?php echo e( $x['riferimento'] ); ?></div>
						</td>
						<td><?php echo e( $x['dettaglio'] ); ?></td>
						<td><?php echo e( $x['motivo'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( $aMano ) : ?>
			<p class="guida">
				<?php echo num( count( $aMano ) ); ?>
				<?php echo 1 === count( $aMano ) ? 'è una pagina' : 'sono pagine'; ?>:
				si correggono aprendo la pagina in WordPress e allungando il testo. È una scelta voluta —
				le pagine servizio sono poche e scritte a mano, e il gestionale non le riscrive da solo.
			</p>
		<?php endif; ?>
	<?php else : ?>
		<p class="avviso ok-bg">
			Nessun contenuto ha più questo problema: o è già stato corretto, o riguarda pagine
			che la riscrittura non tocca. Rileggi il sito dalla pagina dell'audit per aggiornare il conto.
		</p>
	<?php endif; ?>

	<?php if ( $daFondere ) : ?>
		<p class="avviso">
			Questo problema <strong>non si risolve riscrivendo un articolo per volta</strong>: due contenuti
			che si contendono la stessa ricerca si tolgono forza finché uno dei due non smette di
			dichiararla. Qui sotto c'è chi vince ogni ricerca e che cosa fanno gli altri.
			<?php if ( $gruppi > 0 ) : ?>
				Se invece sono due articoli davvero sovrapposti, si fondono:
				<a href="#gruppi">ci sono <?php echo num( $gruppi ); ?> gruppi pronti</a>.
			<?php endif; ?>
		</p>
	<?php endif; ?>



	<?php if ( $pronto && ! $daFondere && count( $da_regola ) > 0 ) : ?>
		<form class="a-lotti avvio-regola" method="post" action="?p=genera" data-tipo="articoli" data-restanti="<?php echo (int) count( $da_regola ); ?>" data-nome="bozze">
			<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
			<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">
			<input type="hidden" name="regola" value="<?php echo e( $regola ); ?>">

			<h3>Correggi questi <?php echo num( count( $da_regola ) ); ?> articoli</h3>
			<p class="guida">
				Si lavora a lotti: ogni articolo richiede 10-30 secondi e il server ha un tempo massimo.
				Le riscritture non vanno online da sole — finiscono in «Vecchio e nuovo», dove le guardi
				prima di applicarle.
			</p>

			<label for="quante-regola">Quanti articoli in questo lotto</label>
			<input id="quante-regola" type="number" name="quante" value="<?php echo (int) $cfg['ai']['articoli_per_volta']; ?>" min="1" max="25" style="width:110px;padding:8px;border:1px solid var(--linea);border-radius:4px">

			<p>
				<button class="bottone" type="submit">Genera le bozze per <?php echo e( $regola ); ?></button>
			</p>
		</form>
	<?php elseif ( ! $pronto && count( $da_regola ) > 0 ) : ?>
		<p class="avviso">
			Il pulsante per avviare non c'è perché manca la chiave di Google Gemini: senza, nessuna
			riscrittura può partire. Le istruzioni sono nel riquadro «Manca la chiave API», in cima a
			questa pagina.
		</p>
	<?php endif; ?>

	<p class="nota"><a href="?p=bozze&amp;id=<?php echo (int) $audit['id']; ?>">Lavora invece su tutto l'archivio</a></p>
</section>
<?php endif; ?>

<?php // La ricerca e le ricerche contese valgono sempre, non solo quando
// si arriva da un problema: erano dentro al blocco «stai correggendo», e
// aprendo la pagina dalla riscrittura non comparivano. ?>
<section class="scheda" id="cerca">
	<h3>Riscrivi un contenuto preciso</h3>
	<p class="guida">
		Gli elenchi qui sopra partono dai problemi trovati. Se invece sai già quale contenuto
		vuoi rifare, cercalo: si riscrive anche se non aveva nessun problema segnalato e anche
		se una riscrittura ce l'ha già — in quel caso viene rifatta da capo.
	</p>

	<form method="get" action="">
		<input type="hidden" name="p" value="bozze">
		<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">
		<?php if ( '' !== $regola ) : ?>
			<input type="hidden" name="regola" value="<?php echo e( $regola ); ?>">
		<?php endif; ?>
		<label for="cerca-cosa">Titolo, indirizzo o numero dell'articolo</label>
		<input id="cerca-cosa" type="search" name="cerca" value="<?php echo e( $cerca ); ?>"
			placeholder="video virali · /produzione-video-palermo/ · 4252"
			style="width:min(480px,100%);padding:8px;border:1px solid var(--linea);border-radius:4px">
		<button class="bottone secondario" type="submit">Cerca</button>
	</form>

	<?php if ( '' !== $cerca && ! $trovati ) : ?>
		<p class="nota">
			Nessun contenuto pubblicato corrisponde a «<?php echo e( $cerca ); ?>» in questa analisi.
			Se l'articolo è stato pubblicato dopo l'ultima lettura del sito, rileggi il sito dalla
			pagina dell'audit.
		</p>
	<?php elseif ( $trovati ) : ?>
		<div class="tabellabox">
			<table>
				<thead>
					<tr><th>Contenuto</th><th class="num">Parole</th><th class="num">Problemi</th><th class="stretta"></th></tr>
				</thead>
				<tbody>
				<?php foreach ( $trovati as $t ) : ?>
					<tr>
						<td>
							<a href="<?php echo e( $t['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $t['titolo'] ); ?></a>
							<div class="sotto">
								<span class="mono"><?php echo e( $t['percorso'] ); ?></span>
								<?php if ( 'page' === $t['tipo'] ) : ?>
									· <strong>pagina servizio</strong>
								<?php endif; ?>
								<?php if ( (int) $t['bozze'] > 0 ) : ?>
									· ha già una riscrittura: premendo si rifà da capo
								<?php endif; ?>
							</div>
						</td>
						<td class="num"><?php echo num( $t['parole'] ); ?></td>
						<td class="num"><?php echo num( $t['problemi'] ); ?></td>
						<td class="stretta">
							<?php if ( $pronto ) : ?>
								<form method="post" action="?p=genera" style="margin:0">
									<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
									<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">
									<input type="hidden" name="documento" value="<?php echo (int) $t['doc_id']; ?>">
									<input type="hidden" name="cerca" value="<?php echo e( $cerca ); ?>">
									<button class="bottone" type="submit"
										<?php if ( 'page' === $t['tipo'] ) : ?>
											onclick="return confirm('Questa è una pagina servizio, non un articolo. La riscrittura resta in bozza e non viene pubblicata: la applichi tu da «Vecchio e nuovo» solo se ti convince. Procedere?')"
										<?php endif; ?>
									>Riscrivi</button>
								</form>
							<?php else : ?>
								<small>serve la chiave</small>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="nota">
			La riscrittura finisce in <strong>«Vecchio e nuovo»</strong>: niente viene pubblicato finché
			non lo dici tu. Anche sulle pagine servizio vale lo stesso — la bozza si guarda e si applica
			a mano.
		</p>
	<?php endif; ?>
</section>

<?php if ( ! empty( $contese ) ) : ?>
	<?php $pianoContese = \SeoGeo\Fix\Cannibalizzazione::piano( $contese ); ?>
	<section class="scheda" id="contese">
		<h3>Chi vince la ricerca, e che cosa fanno gli altri</h3>
		<p class="guida">
			Fondere non è l'unico rimedio, e spesso non è quello giusto: una <strong>pagina servizio</strong>
			e un <strong>articolo del blog</strong> non sono due doppioni, sono due lavori diversi che per
			sbaglio dichiarano la stessa parola chiave. La pagina deve vincere la ricerca commerciale;
			l'articolo prende la variante lunga che già racconta e manda forza alla pagina con un link.
		</p>

		<?php foreach ( $contese as $g ) : ?>
			<div class="tabellabox">
				<table>
					<thead>
						<tr>
							<th>Ricerca contesa: <span class="mono"><?php echo e( $g['ancora'] ); ?></span></th>
							<th>Che cosa succede</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>
								<span class="tag ok">vince</span>
								<a href="<?php echo e( $g['vincitore']['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $g['vincitore']['titolo'] ); ?></a>
								<div class="sotto"><?php echo 'page' === $g['vincitore']['tipo'] ? 'pagina servizio' : 'articolo'; ?> · <?php echo num( $g['vincitore']['parole'] ); ?> parole</div>
							</td>
							<td><small><?php echo e( $g['spiega'] ); ?></small></td>
						</tr>
						<?php foreach ( $g['perdenti'] as $p ) : ?>
							<tr>
								<td>
									<span class="tag <?php echo ! empty( $p['automatico'] ) ? 'alto' : 'grave'; ?>"><?php echo ! empty( $p['automatico'] ) ? 'cede' : 'a mano'; ?></span>
									<a href="<?php echo e( $p['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $p['titolo'] ); ?></a>
									<div class="sotto"><?php echo 'page' === $p['tipo'] ? 'pagina servizio' : 'articolo'; ?> · ora dichiara «<?php echo e( $p['focus'] ); ?>»</div>
								</td>
								<td>
									<?php if ( ! empty( $p['variante'] ) ) : ?>
										passa a «<strong><?php echo e( $p['variante'] ); ?></strong>»<br>
									<?php endif; ?>
									<small><?php echo e( $p['motivo'] ); ?></small>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>

		<?php if ( $pianoContese['meta'] || $pianoContese['link'] ) : ?>
			<form method="post" action="?p=applica-contese">
				<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
				<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">
				<button class="bottone" type="submit">Applica: <?php echo num( count( $pianoContese['meta'] ) ); ?> articoli cedono la ricerca</button>
			</form>
			<p class="nota">
				Cambia la <strong>parola chiave dichiarata</strong> degli articoli che cedono e aggiunge il link
				verso chi vince. Non riscrive nessun testo, non crea redirect, non tocca le pagine servizio, e
				si annulla rimettendo la parola chiave di prima da WordPress. Il title segue alla prossima
				passata di «Meta degli articoli», che ora lo costruisce sulla parola chiave nuova.
				<?php if ( $pianoContese['a_mano'] ) : ?>
					<?php echo num( count( $pianoContese['a_mano'] ) ); ?> restano a te: sono pagine servizio, e
					si sistemano aprendo la pagina in WordPress e cambiando lì la parola chiave.
				<?php endif; ?>
			</p>
		<?php else : ?>
			<p class="nota">
				Qui non c'è niente da applicare da solo: i contenuti in gara sono pagine servizio, e quelle
				si sistemano a mano — è una scelta voluta.
			</p>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $pronto && empty( $regola ) && $stima['articoli'] > 0 ) : ?>
<form class="scheda a-lotti" method="post" action="?p=genera" data-tipo="articoli" data-restanti="<?php echo (int) $stima['articoli']; ?>" data-nome="bozze">
	<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
	<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">

	<h2>Genera un lotto</h2>
	<p class="guida">Si procede a lotti per non superare il tempo massimo di esecuzione del server. Ogni articolo richiede 10-30 secondi.</p>

	<label for="quante">Quanti articoli in questo lotto</label>
	<input id="quante" type="number" name="quante" value="<?php echo (int) $cfg['ai']['articoli_per_volta']; ?>" min="1" max="25" style="width:110px;padding:8px;border:1px solid var(--linea);border-radius:4px">

	<p class="nota">Su hosting condiviso conviene restare su 3-5 per volta. Da riga di comando non c'è limite: <code>php cli/riscrivi.php <?php echo (int) $audit['id']; ?> --limite=50</code></p>

	<button class="bottone" type="submit">Genera bozze</button>
</form>
<?php endif; ?>

<?php if ( $pronto && $gruppi > 0 ) : ?>
<form class="scheda a-lotti" id="gruppi" method="post" action="?p=genera" data-tipo="accorpa" data-restanti="<?php echo (int) $gruppi; ?>" data-nome="gruppi">
	<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
	<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">
	<input type="hidden" name="tipo" value="accorpa">

	<h2>Cannibalizzazione: <?php echo num( $gruppi ); ?> gruppi da fondere</h2>
	<p class="guida">
		Questi articoli competono fra loro per la stessa ricerca e si tolgono forza a vicenda.
		Il modello riceve tutti i testi del gruppo e ne produce uno solo, più completo di ciascuno:
		tiene quello che ha valore, elimina le ripetizioni e segnala le contraddizioni fra le fonti.
		Gli articoli assorbiti vanno poi reindirizzati con un 301 sul principale — i redirect sono già
		calcolati in <a href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>">Applica sul sito</a>.
	</p>

	<label for="quante_gruppi">Quanti gruppi in questo lotto</label>
	<input id="quante_gruppi" type="number" name="quante" value="3" min="1" max="10" style="width:110px;padding:8px;border:1px solid var(--linea);border-radius:4px">
	<p class="nota">Un accorpamento richiede più tempo di una riscrittura: il modello legge fino a quattro articoli interi.</p>

	<button class="bottone" type="submit">Fondi i gruppi</button>
</form>
<?php endif; ?>

<?php if ( ! empty( $immagini['ignoti'] ) ) : ?>
	<section class="scheda">
		<h2>Immagini in evidenza: dato non disponibile</h2>
		<p class="guida">
			Questa analisi è stata fatta con una versione precedente del programma, che non
			registrava quali articoli hanno già un'immagine in evidenza: <?php echo num( $immagini['ignoti'] ); ?> contenuti
			risultano senza informazione. Rilancia l'analisi caricando di nuovo l'export
			(<a href="?p=nuovo">Nuova analisi</a>): il conteggio diventa esatto e non paghi
			immagini per articoli che ce l'hanno già.
		</p>
	</section>
<?php endif; ?>

<?php if ( $pronto && $immagini['mancanti'] > 0 ) : ?>
<form class="scheda a-lotti" method="post" action="?p=genera" data-tipo="immagini" data-restanti="<?php echo (int) $immagini['mancanti']; ?>" data-nome="immagini">
	<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
	<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">
	<input type="hidden" name="tipo" value="immagini">

	<h2>Immagini in evidenza: <?php echo num( $immagini['mancanti'] ); ?> mancanti</h2>
	<p class="guida">
		Senza immagine in evidenza mancano og:image e twitter:image: le condivisioni social non hanno
		anteprima e Google Discover esclude la pagina. Le immagini vengono generate in formato
		orizzontale, senza testo e senza logo, e salvate in <code>storage/export/audit-<?php echo (int) $audit['id']; ?>/immagini/</code>.
		<?php if ( $immagini['generate'] ) : ?>
			Finora ne sono state generate <strong><?php echo num( $immagini['generate'] ); ?></strong>.
		<?php endif; ?>
	</p>

	<?php if ( $wp_pronto ) : ?>
		<p class="nota">
			Questo numero viene dall'analisi, che è la fotografia di quel momento: se hai già caricato
			delle immagini, lì dentro non è cambiato niente e il conto resta fermo.
			<a href="?p=ricontrolla-immagini&amp;id=<?php echo (int) $audit['id']; ?>&amp;token=<?php echo e( token() ); ?>">Chiedilo al sito</a>
			e il conto si riallinea, senza rifare l'analisi.
		</p>
	<?php endif; ?>

	<label for="quante_img">Quante immagini in questo lotto</label>
	<input id="quante_img" type="number" name="quante" value="3" min="1" max="10" style="width:110px;padding:8px;border:1px solid var(--linea);border-radius:4px">

	<?php if ( $wp_pronto ) : ?>
		<label style="display:flex;gap:8px;align-items:center;margin:10px 0;font-weight:400">
			<input type="checkbox" name="invia" value="1" checked>
			Caricale subito sul sito e impostale come immagine in evidenza
		</label>
	<?php endif; ?>

	<p class="nota">
		La generazione di immagini richiede un progetto Google con fatturazione attiva: sul piano gratuito
		l'API risponde con un errore di quota. Un avvertimento onesto: per una web agency le foto dei
		lavori veri valgono più di qualsiasi immagine generata — questo serve a coprire l'archivio storico,
		non a sostituire il portfolio.
	</p>

	<button class="bottone" type="submit">Genera immagini</button>
</form>
<?php endif; ?>

<?php if ( $bozze ) : ?>
<section class="scheda">
	<h2>Bozze generate</h2>
	<div class="tabellabox">
		<table>
			<thead><tr><th>Stato</th><th>Articolo</th><th class="num">Parole</th><th>Da verificare</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $bozze as $b ) : ?>
				<?php $verifiche = json_decode( (string) $b['da_verificare'], true ) ?: array(); ?>
				<?php
				// Tre stati, non due: una bozza gia scritta sul sito non e la
				// stessa cosa di una ancora da decidere, e senza distinguerle
				// non si sa piu che cosa si e gia messo online.
				$online = 'ok' === $b['stato'] && ! empty( $b['inviata_il'] );
				$stato  = 'ok' !== $b['stato'] ? 'errore' : ( $online ? 'online' : 'pronta' );
				?>
				<tr>
					<td><span class="tag <?php echo 'errore' === $stato ? 'grave' : 'ok'; ?>"><?php echo e( $stato ); ?></span></td>
					<td>
						<strong><?php echo e( $b['titolo'] ); ?></strong>
						<div class="sotto">
							<?php if ( 'ok' === $b['stato'] ) : ?>
								da <?php echo num( $b['parole_originali'] ); ?> a <?php echo num( $b['parole'] ); ?> parole
								<?php if ( $online ) : ?>
									· <strong>scritta sul sito</strong> il <?php echo e( substr( (string) $b['inviata_il'], 0, 16 ) ); ?>
								<?php endif; ?>
								<?php if ( '' !== trim( (string) $b['note'] ) ) : ?>
									· <?php echo e( $b['note'] ); ?>
								<?php endif; ?>
								<?php if ( '' !== trim( (string) ( $b['rimaste'] ?? '' ) ) ) : ?>
									<br><span class="tag alto">non chiuso</span>
									<?php echo e( (string) $b['rimaste'] ); ?>
									— il modello ha provato due volte e non c'è riuscito: questo pezzo resta da fare a mano.
								<?php endif; ?>
							<?php else : ?>
								<?php echo e( $b['errore'] ); ?>
							<?php endif; ?>
						</div>
					</td>
					<td class="num"><?php echo 'ok' === $b['stato'] ? num( $b['parole'] ) : '—'; ?></td>
					<td class="stretta">
						<?php if ( $verifiche ) : ?>
							<?php echo count( $verifiche ); ?> dati reali da inserire
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td class="num">
						<?php if ( 'ok' === $b['stato'] ) : ?>
							<a href="?p=bozza&amp;b=<?php echo (int) $b['id']; ?>">apri</a> ·
							<a href="?p=bozza&amp;b=<?php echo (int) $b['id']; ?>&amp;scarica=1">scarica</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
<?php endif; ?>

<?php if ( $pronto && ! empty( $segnaposto_aperti ) ) : ?>
<section class="scheda" id="verifiche">
	<h2>Dati da verificare: <?php echo num( count( $segnaposto_aperti ) ); ?> da trovare</h2>
	<p class="guida">
		Le bozze escono con dei buchi al posto di prezzi, tempi e numeri, perché il modello ha
		l'istruzione di non inventarli mai. Qui li cerca su Google e li compila,
		<strong>ma solo se una fonte vera lo sostiene</strong>: dove non trova niente, il buco resta.
	</p>
	<p class="nota">
		Una cosa che nessuna ricerca può sapere sono i <strong>tuoi</strong> prezzi e i tuoi tempi.
		Per quelli trova il valore medio di mercato e lo scrive come tale — «media di mercato in Italia,
		non il nostro listino» — invece di spacciarlo per il tuo. Se poi vuoi metterci le tue cifre,
		sostituisci quelle righe a mano: sono poche e le vedi elencate qui sotto.
	</p>

	<details>
		<summary>Quali buchi ci sono, e quante volte</summary>
		<table class="widefat">
			<tbody>
			<?php foreach ( array_slice( $segnaposto_aperti, 0, 25 ) as $riga ) : ?>
				<tr>
					<td><?php echo e( $riga['etichetta'] ); ?></td>
					<td class="stretta"><?php echo num( $riga['quante'] ); ?> volte · <?php echo num( count( $riga['bozze'] ) ); ?> bozze</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</details>

	<div class="azioni" id="verifiche-azioni">
		<form method="post" action="?p=bozze" id="cerca-verifiche">
			<button class="bottone" type="submit">Cercali su Google e compila</button>
		</form>
	</div>

	<div id="verifiche-corso" hidden>
		<p><span id="verifiche-spia" class="spia"></span> <strong id="verifiche-titolo">Sto cercando…</strong></p>
		<div class="barra" style="height:10px;margin-bottom:12px"><i id="verifiche-barra" class="ok" style="width:1%;height:10px"></i></div>
		<p class="nota">
			<span id="verifiche-risolti">0</span> trovati ·
			<span id="verifiche-restanti"><?php echo (int) count( $segnaposto_aperti ); ?></span> da trovare ·
			<span id="verifiche-bozze">0</span> bozze aggiornate
		</p>
		<p class="nota" id="verifiche-battito"></p>
		<div id="verifiche-fonti"></div>
		<p class="nota" id="verifiche-senza" hidden></p>
		<button type="button" class="bottone chiaro" id="verifiche-stop">Ferma</button>
	</div>

	<script>
	(function () {
		var modulo = document.getElementById('cerca-verifiche');
		var corso = document.getElementById('verifiche-corso');

		if (!modulo || !corso || !window.fetch) { return; }

		var token = <?php echo json_encode( token() ); ?>;
		var idAudit = <?php echo (int) $audit['id']; ?>;
		var restano = <?php echo (int) count( $segnaposto_aperti ); ?>;
		var partenza = restano;
		var risolti = 0;
		var bozze = 0;
		var fermato = false;
		var blocco = 0;
		var iniziato = 0;
		var orologio = null;

		function scrivi(id, testo) { document.getElementById(id).textContent = testo; }

		function battito() {
			var secondi = Math.round((Date.now() - iniziato) / 1000);
			scrivi('verifiche-battito', 'Blocco ' + blocco + ' in corso da ' + secondi + ' second' + (1 === secondi ? 'o' : 'i')
				+ '. Ogni ricerca richiede qualche secondo: finché questo numero sale, sta cercando.');
		}

		function spegni(classe, testo) {
			document.getElementById('verifiche-spia').className = 'spia ' + classe;
			scrivi('verifiche-titolo', testo);
			scrivi('verifiche-battito', '');
			clearInterval(orologio);
			var stop = document.getElementById('verifiche-stop');
			stop.textContent = 'Ricarica la pagina';
			stop.onclick = function () { location.reload(); };
		}

		function mostraFonti(elenco) {
			var contenitore = document.getElementById('verifiche-fonti');

			elenco.forEach(function (voce) {
				var blocco = document.createElement('p');
				blocco.className = 'nota';

				var forte = document.createElement('strong');
				forte.textContent = voce.etichetta + ': ';
				blocco.appendChild(forte);
				blocco.appendChild(document.createTextNode(voce.valore + ' — fonti: '));

				voce.fonti.forEach(function (fonte, i) {
					var a = document.createElement('a');
					a.href = fonte.url;
					a.target = '_blank';
					a.rel = 'noopener';
					a.textContent = fonte.titolo;
					blocco.appendChild(a);

					if (i < voce.fonti.length - 1) { blocco.appendChild(document.createTextNode(' · ')); }
				});

				contenitore.appendChild(blocco);
			});
		}

		function giro() {
			if (fermato) { return; }

			blocco++;
			iniziato = Date.now();
			scrivi('verifiche-titolo', 'Sto cercando…');
			battito();
			clearInterval(orologio);
			orologio = setInterval(battito, 1000);

			fetch('?p=api-verifiche&id=' + idAudit + '&token=' + encodeURIComponent(token))
				.then(function (r) { return r.json(); })
				.then(function (d) {
					if (d.errore) { spegni('guasto', 'Interrotta: ' + d.errore); return; }

					risolti += d.risolti;
					bozze += d.bozze;
					restano = d.restanti;

					scrivi('verifiche-risolti', risolti);
					scrivi('verifiche-restanti', Math.max(0, restano));
					scrivi('verifiche-bozze', bozze);
					document.getElementById('verifiche-barra').style.width =
						Math.max(1, partenza ? Math.round((risolti / partenza) * 100) : 100) + '%';

					if (d.dettaglio && d.dettaglio.length) { mostraFonti(d.dettaglio); }

					if (d.senza_dato && d.senza_dato.length) {
						var p = document.getElementById('verifiche-senza');
						p.hidden = false;
						p.textContent = 'Lasciati vuoti perché le fonti non li danno: ' + d.senza_dato.join(' · ');
					}

					if (d.finito || fermato) {
						spegni(restano > 0 ? 'fermo' : 'fermo', restano > 0
							? 'Finito: ' + restano + ' buchi restano, le fonti non danno quel dato'
							: 'Fatto: tutti i buchi compilati');
						return;
					}

					giro();
				})
				.catch(function (e) { spegni('guasto', 'Connessione interrotta: ' + e.message); });
		}

		modulo.addEventListener('submit', function (evento) {
			evento.preventDefault();
			document.getElementById('verifiche-azioni').hidden = true;
			corso.hidden = false;
			giro();
		});

		document.getElementById('verifiche-stop').addEventListener('click', function () {
			fermato = true;
			scrivi('verifiche-titolo', 'Mi fermo alla fine di questo blocco…');
			document.getElementById('verifiche-spia').className = 'spia fermo';
		});
	})();
	</script>
</section>
<?php endif; ?>

<?php if ( $pronto && ! empty( $bozze_bloccate ) ) : ?>
<section class="scheda" id="senza-dato">
	<h2>Restano <?php echo num( $bozze_bloccate ); ?> bozze con un buco che nessuno riesce a riempire</h2>
	<p class="guida">
		Dopo la ricerca su Google qualche dato non salta fuori: ci sono numeri che nessuna fonte
		pubblica ha. Quelle bozze non si mandano online — in pagina si leggerebbe
		<code>[DA VERIFICARE: …]</code> — e finora l'unica via d'uscita era compilarle a mano.
	</p>
	<p class="guida">
		Questo pulsante fa l'ultimo passo: <strong>gira le frasi</strong> in modo che funzionino
		senza quel dato. «Costa [DA VERIFICARE: prezzo medio]» diventa «il costo dipende da quante
		pagine servono». Non inventa niente e tocca solo le frasi col buco: il resto dell'articolo
		resta come l'hai letto. Dopo, quelle bozze si sovrascrivono come tutte le altre.
	</p>

	<div class="azioni" id="senza-azioni">
		<form method="post" action="?p=bozze" id="gira-frasi">
			<button class="bottone" type="submit">Gira le frasi senza il dato mancante</button>
		</form>
	</div>

	<div id="senza-corso" hidden>
		<p><span id="senza-spia" class="spia"></span> <strong id="senza-titolo">Sto girando le frasi…</strong></p>
		<div class="barra" style="height:10px;margin-bottom:12px"><i id="senza-barra" class="ok" style="width:1%;height:10px"></i></div>
		<p class="nota">
			<span id="senza-fatte">0</span> bozze sistemate ·
			<span id="senza-restanti"><?php echo (int) $bozze_bloccate; ?></span> ancora bloccate ·
			<span id="senza-frasi">0</span> frasi girate
		</p>
		<p class="nota" id="senza-battito"></p>
		<div id="senza-esempi"></div>
		<button type="button" class="bottone chiaro" id="senza-stop">Ferma</button>
	</div>

	<script>
	(function () {
		var modulo = document.getElementById('gira-frasi');
		var corso = document.getElementById('senza-corso');

		if (!modulo || !corso || !window.fetch) { return; }

		var token = <?php echo json_encode( token() ); ?>;
		var idAudit = <?php echo (int) $audit['id']; ?>;
		var partenza = <?php echo (int) $bozze_bloccate; ?>;
		var restano = partenza;
		var fatte = 0;
		var frasi = 0;
		var fermato = false;
		var blocco = 0;
		var iniziato = 0;
		var orologio = null;

		function scrivi(id, testo) { document.getElementById(id).textContent = testo; }

		function battito() {
			var secondi = Math.round((Date.now() - iniziato) / 1000);
			scrivi('senza-battito', 'Blocco ' + blocco + ' in corso da ' + secondi + ' second' + (1 === secondi ? 'o' : 'i')
				+ '. Ogni bozza richiede qualche secondo: finché questo numero sale, sta lavorando.');
		}

		function spegni(classe, testo) {
			document.getElementById('senza-spia').className = 'spia ' + classe;
			scrivi('senza-titolo', testo);
			scrivi('senza-battito', '');
			clearInterval(orologio);
			var stop = document.getElementById('senza-stop');
			stop.textContent = 'Ricarica la pagina';
			stop.onclick = function () { location.reload(); };
		}

		function mostraEsempi(elenco) {
			var dove = document.getElementById('senza-esempi');

			elenco.forEach(function (voce) {
				var p = document.createElement('p');
				p.className = 'nota';

				var forte = document.createElement('strong');
				forte.textContent = voce.titolo + ': ';
				p.appendChild(forte);
				p.appendChild(document.createTextNode('«' + voce.prima + '» → «' + voce.dopo + '»'));
				dove.appendChild(p);
			});
		}

		function giro() {
			if (fermato) { return; }

			blocco++;
			iniziato = Date.now();
			scrivi('senza-titolo', 'Sto girando le frasi…');
			battito();
			clearInterval(orologio);
			orologio = setInterval(battito, 1000);

			fetch('?p=api-senza-dato&id=' + idAudit + '&token=' + encodeURIComponent(token))
				.then(function (r) { return r.json(); })
				.then(function (d) {
					if (d.errore) { spegni('guasto', 'Interrotta: ' + d.errore); return; }

					fatte += d.fatte;
					frasi += d.sistemate;
					restano = d.restanti;

					scrivi('senza-fatte', fatte);
					scrivi('senza-frasi', frasi);
					scrivi('senza-restanti', Math.max(0, restano));
					document.getElementById('senza-barra').style.width =
						Math.max(1, partenza ? Math.round(((partenza - restano) / partenza) * 100) : 100) + '%';

					if (d.esempi && d.esempi.length) { mostraEsempi(d.esempi); }

					if (d.finito || fermato) {
						spegni('fermo', restano > 0
							? 'Finito: ' + restano + ' bozze non si sono sistemate, vanno guardate a mano'
							: 'Fatto: nessuna bozza e piu bloccata, si possono sovrascrivere tutte');
						return;
					}

					giro();
				})
				.catch(function (e) { spegni('guasto', 'Connessione interrotta: ' + e.message); });
		}

		modulo.addEventListener('submit', function (evento) {
			evento.preventDefault();
			document.getElementById('senza-azioni').hidden = true;
			corso.hidden = false;
			giro();
		});

		document.getElementById('senza-stop').addEventListener('click', function () {
			fermato = true;
			scrivi('senza-titolo', 'Mi fermo alla fine di questo blocco…');
			document.getElementById('senza-spia').className = 'spia fermo';
		});
	})();
	</script>
</section>
<?php endif; ?>

<?php if ( $bozze ) : ?>
<section class="scheda">
	<h2>Vecchio e nuovo, affiancati</h2>
	<p class="guida">
		Prima di mettere online, guarda il confronto: a sinistra quello che c'è adesso sul sito,
		a destra il testo migliorato. Da lì si sovrascrive <strong>l'articolo che esiste già</strong>,
		senza creare doppioni e senza cambiare indirizzo.
	</p>
	<p>
		<a class="bottone" href="?p=confronto-bozze&amp;id=<?php echo (int) $audit['id']; ?>">Vedi vecchio e nuovo</a>
	</p>
</section>
<?php endif; ?>

<section class="scheda">
	<h2>Prima di pubblicare</h2>
	<ol>
		<li>Sostituisci ogni <code>[DA VERIFICARE: ...]</code> con dati reali: prezzi, tempi, risultati. Il modello ha l'istruzione di non inventarli mai.</li>
		<li>Aggiungi almeno un'esperienza diretta o un caso vostro: è quello che distingue il testo da mille altri simili.</li>
		<li>Rileggi e taglia. Una bozza da 1.200 parole di solito ne regge 900 di buone.</li>
		<li>Inserisci un'immagine originale con alt descrittivo e imposta l'immagine in evidenza.</li>
	</ol>
	<p class="nota">Pubblicare in massa testo generato senza revisione è ciò che le linee guida antispam di Google chiamano abuso di contenuti scalati: il modulo è costruito per evitarlo, non per aggirare il problema.</p>
</section>

<script>
(function () {
	// Un ciclo solo per i tre lotti: bozze, accorpamenti e immagini fanno
	// la stessa cosa - lavorano a blocchi perche l hosting chiude le
	// richieste lunghe - e si guardano allo stesso modo.
	var moduli = document.querySelectorAll('form.a-lotti');

	if (!moduli.length || !window.fetch) { return; }

	var token = <?php echo json_encode( token() ); ?>;
	var idAudit = <?php echo (int) $audit['id']; ?>;

	Array.prototype.forEach.call(moduli, function (modulo) {
		var tipo = modulo.dataset.tipo;
		var nome = modulo.dataset.nome;
		var restano = parseInt(modulo.dataset.restanti, 10) || 0;
		var partenza = restano;
		var fatte = 0;
		var falliti = 0;
		var fermato = false;
		var blocco = 0;
		var iniziato = 0;
		var orologio = null;
		var avvio = 0;

		var pannello = document.createElement('div');
		pannello.hidden = true;
		pannello.innerHTML =
			'<p><span class="spia"></span> <strong class="lotto-titolo">Sto lavorando…</strong></p>'
			+ '<div class="barra" style="height:10px;margin-bottom:12px"><i class="ok lotto-barra" style="width:1%;height:10px"></i></div>'
			+ '<p class="nota"><span class="lotto-fatte">0</span> fatte · <span class="lotto-restanti">0</span> da fare<span class="lotto-falliti"></span><span class="lotto-quanto"></span></p>'
			+ '<p class="nota lotto-battito"></p>'
			+ '<p class="nota grave lotto-errori" hidden></p>'
			+ '<button type="button" class="bottone chiaro lotto-stop">Ferma</button>';

		modulo.appendChild(pannello);

		function dentro(classe) { return pannello.querySelector('.' + classe); }
		function scrivi(classe, testo) { dentro(classe).textContent = testo; }

		function battito() {
			var secondi = Math.round((Date.now() - iniziato) / 1000);

			scrivi('lotto-battito',
				'Blocco ' + blocco + ' in corso da ' + secondi + ' second' + (1 === secondi ? 'o' : 'i')
				+ '. Ogni contenuto richiede 10-30 secondi: finché questo numero sale, sta lavorando.');
		}

		function aggiorna() {
			var percento = partenza ? Math.round((fatte / partenza) * 100) : 100;
			dentro('lotto-barra').style.width = Math.max(1, percento) + '%';
			scrivi('lotto-fatte', fatte.toLocaleString('it-IT'));
			scrivi('lotto-restanti', Math.max(0, restano).toLocaleString('it-IT'));
			scrivi('lotto-falliti', falliti ? ' · ' + falliti + ' non riuscite' : '');
			scrivi('lotto-quanto', stima());
		}

		// Quanto manca, misurato su quello che e successo finora invece che
		// su una media inventata: con duecento contenuti la differenza fra
		// "dieci minuti" e "due ore" cambia quello che uno decide di fare.
		function stima() {
			if (!fatte || !avvio) { return ''; }

			var perContenuto = (Date.now() - avvio) / fatte;
			var minuti = Math.round((perContenuto * restano) / 60000);

			if (minuti < 1) { return ' · manca meno di un minuto'; }
			if (minuti < 60) { return ' · mancano circa ' + minuti + ' minuti'; }

			var ore = Math.floor(minuti / 60);

			return ' · mancano circa ' + ore + ( 1 === ore ? ' ora' : ' ore' )
				+ ( minuti % 60 ? ' e ' + (minuti % 60) + ' minuti' : '' );
		}

		function spegni(classe, testo) {
			dentro('spia').className = 'spia ' + classe;
			scrivi('lotto-titolo', testo);
			scrivi('lotto-battito', '');
			clearInterval(orologio);
			var stop = dentro('lotto-stop');
			stop.textContent = 'Ricarica la pagina';
			stop.onclick = function () { location.reload(); };
		}

		function giro() {
			if (fermato) { return; }

			blocco++;
			iniziato = Date.now();
			scrivi('lotto-titolo', 'Sto lavorando…');
			battito();
			clearInterval(orologio);
			orologio = setInterval(battito, 1000);

			var invia = modulo.querySelector('[name="invia"]');
			var quante = modulo.querySelector('[name="quante"]');

			// Il problema da cui si e partiti viaggia con la richiesta: senza,
			// il server lavorava sull archivio intero e rispondeva "nessun
			// articolo da riscrivere" davanti a un elenco di duecento.
			var regola = modulo.querySelector('[name="regola"]');

			var indirizzo = '?p=api-bozze&id=' + idAudit
				+ '&tipo=' + encodeURIComponent(tipo)
				+ '&quante=' + encodeURIComponent(quante ? quante.value : 3)
				+ (regola && regola.value ? '&regola=' + encodeURIComponent(regola.value) : '')
				+ (invia && invia.checked ? '&invia=1' : '')
				+ '&token=' + encodeURIComponent(token);

			fetch(indirizzo)
				.then(function (r) { return r.json(); })
				.then(function (d) {
					if (d.errore) { spegni('guasto', 'Interrotta: ' + d.errore); return; }

					fatte += d.fatte;
					falliti += d.falliti || 0;
					restano = d.restanti;
					aggiorna();

					// Gli errori veri restano in rosso; il tetto di tempo e
					// il funzionamento normale e va scritto come tale.
					if (d.errori && d.errori.length) {
						var p = dentro('lotto-errori');
						p.hidden = false;
						p.textContent = 'Non riuscite: ' + d.errori.join(' · ');
					}

					if (d.per_tempo) {
						scrivi('lotto-titolo', 'Sto lavorando… (il blocco si è chiuso al limite di tempo, continuo)');
					}

					if (d.finito || fermato) {
						spegni(restano > 0 ? 'guasto' : 'fermo', restano > 0
							? 'Fermata: restano ' + restano + ' ' + nome
							: 'Fatto: ' + nome + ' completate');
						return;
					}

					giro();
				})
				.catch(function (e) { spegni('guasto', 'Connessione interrotta: ' + e.message); });
		}

		modulo.addEventListener('submit', function (evento) {
			evento.preventDefault();

			if (!confirm('Procedere su ' + restano + ' ' + nome + '? Si può fermare in qualsiasi momento.')) {
				return;
			}

			Array.prototype.forEach.call(modulo.querySelectorAll('button, input'), function (elemento) {
				if (!pannello.contains(elemento)) { elemento.disabled = true; }
			});

			pannello.hidden = false;
			avvio = Date.now();
			aggiorna();
			giro();
		});

		dentro('lotto-stop').addEventListener('click', function () {
			fermato = true;
			scrivi('lotto-titolo', 'Mi fermo alla fine di questo blocco…');
			dentro('spia').className = 'spia fermo';
		});
	});
})();
</script>
