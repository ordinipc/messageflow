<?php
/**
 * Che cosa dice Google, articolo per articolo.
 *
 * @package SeoGeoAudit
 * @var array  $audit       Riga audit.
 * @var bool   $configurato Search Console collegata.
 * @var array  $quadro      Conteggi per caso.
 * @var int    $restano     Quanti indirizzi non sono ancora stati chiesti.
 * @var array  $gruppi      Doppioni raggruppati per la pagina che Google tiene.
 * @var array  $piano       Canoniche da allineare.
 * @var int    $pagine_fuori Pagine servizio escluse dal piano.
 * @var string $messaggio   Esito dell ultimo lotto.
 * @var bool   $continua    Se deve rilanciare il lotto successivo da se.
 * @var string $errore      Errore dell ultimo lotto.
 */
?>
<?php $piano = $piano ?? array(); $pagine_fuori = $pagine_fuori ?? 0; $continua = $continua ?? false; ?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>"><?php echo e( $audit['sito_nome'] ); ?></a> › Che cosa dice Google</p>
	<h1>Che cosa dice Google</h1>
	<p class="guida">
		Qui non c'è nessun punteggio: Google non ne dà, e nessuno può leggerne uno. C'è il suo
		giudizio su ogni indirizzo — se lo tiene nell'indice, e se no perché. Lo si chiede a Google
		un indirizzo per volta, ed è la stessa cosa che vedi nel rapporto «Indicizzazione delle
		pagine» dentro Search Console, con in più i doppioni già raggruppati.
	</p>
</section>

<?php if ( $errore ) : ?><p class="avviso grave"><?php echo e( $errore ); ?></p><?php endif; ?>
<?php if ( $messaggio ) : ?><p class="avviso ok-bg"><?php echo e( $messaggio ); ?></p><?php endif; ?>

<?php if ( ! $configurato ) : ?>
	<section class="scheda">
		<p class="guida">
			Serve il collegamento a Search Console: si configura in <a href="?p=impostazioni">Impostazioni</a>.
		</p>
	</section>
<?php else : ?>

<section class="scheda">
	<h2>Il quadro</h2>

	<?php if ( ! $quadro['totale'] ) : ?>
		<p class="guida">
			Non è ancora stato chiesto niente. Ogni indirizzo costa una richiesta e il limite di Google
			è duemila al giorno, quindi si lavora a lotti: premi, aspetta, ripremi.
		</p>
	<?php else : ?>
		<div class="tabellabox">
			<table>
				<tbody>
					<tr><td>Nell'indice: Google le tiene</td><td class="num"><strong><?php echo num( $quadro['dentro'] ); ?></strong></td></tr>
					<tr>
						<td><strong>Doppioni</strong>: Google ha scelto un'altra tua pagina al loro posto</td>
						<td class="num"><strong style="color:var(--grave)"><?php echo num( $quadro['doppioni'] ); ?></strong></td>
					</tr>
					<tr><td>Lette e scartate: le ha guardate e ha deciso di no</td><td class="num"><?php echo num( $quadro['scartati'] ); ?></td></tr>
					<tr><td>In coda: le conosce, non le ha ancora lette</td><td class="num"><?php echo num( $quadro['in_attesa'] ); ?></td></tr>
					<tr><td>Altro (noindex, errori, sconosciute)</td><td class="num"><?php echo num( $quadro['altro'] ); ?></td></tr>
					<tr><td>Chiesti finora</td><td class="num"><?php echo num( $quadro['totale'] ); ?></td></tr>
				</tbody>
			</table>
		</div>

		<p class="nota">
			«Lette e scartate» e «in coda» si somigliano e vogliono dire il contrario: sulle prime
			aspettare non serve a niente, sulle seconde è l'unica cosa da fare.
		</p>
	<?php endif; ?>

	<?php if ( $restano > 0 ) : ?>
		<form method="post" action="?p=chiedi-indice" id="modulo-indice">
			<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
			<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">
			<label for="quanti-indice">Quanti indirizzi in questo lotto</label>
			<input id="quanti-indice" type="number" name="quanti" value="25" min="1" max="100" style="width:90px;padding:8px;border:1px solid var(--linea);border-radius:4px">

			<label class="scelta">
				<input type="checkbox" name="continua" value="1" <?php echo $continua ? 'checked' : ''; ?>>
				<span>
					<strong>Continua da solo fino alla fine</strong>
					<small>
						Rilancia il lotto successivo da sé finché non finiscono. Puoi fermarlo in qualsiasi
						momento chiudendo la pagina: quello che è già stato chiesto resta.
					</small>
				</span>
			</label>

			<button class="bottone" type="submit">Chiedi a Google (ne restano <?php echo num( $restano ); ?>)</button>
		</form>

		<p class="nota">
			Il numero che scrivi è il <strong>massimo</strong> per lotto, non il minimo: se il tuo hosting
			chiude la richiesta prima, il lotto si ferma lì e il resto va al giro dopo. Niente va perso —
			quello che è stato chiesto non si richiede, e si riparte sempre da dove si era arrivati.
		</p>

		<?php if ( $continua ) : ?>
			<p class="avviso ok-bg" id="avviso-continua">Riparto da solo fra pochi secondi…</p>
			<script>
			(function () {
				var modulo = document.getElementById('modulo-indice');
				var avviso = document.getElementById('avviso-continua');

				if (!modulo) { return; }

				var secondi = 3;

				var orologio = setInterval(function () {
					secondi--;

					if (avviso) {
						avviso.textContent = secondi > 0
							? 'Riparto da solo fra ' + secondi + ' second' + (1 === secondi ? 'o' : 'i') + '…'
							: 'Riparto…';
					}

					if (secondi <= 0) {
						clearInterval(orologio);
						modulo.submit();
					}
				}, 1000);
			})();
			</script>
		<?php endif; ?>
	<?php else : ?>
		<p class="nota">Chiesti tutti. Per rifare il giro serve una lettura nuova del sito.</p>
	<?php endif; ?>
</section>

<?php if ( $gruppi ) : ?>
	<section class="scheda">
		<h2>I doppioni, raggruppati da Google</h2>
		<p class="guida">
			Questo non è un sospetto nostro: per ognuna di queste pagine <strong>Google ha scelto un'altra
			tua pagina</strong> al suo posto. È il piano di accorpamento già fatto, e l'ha fatto chi decide.
			Riscrivere una pagina che sta in questo elenco non cambia il giudizio: o si accorpa con quella
			che Google tiene, o le si dà un argomento che quella non copre.
		</p>

		<?php if ( ! empty( $piano ) ) : ?>
			<div class="tabellabox" style="margin-bottom:14px">
				<table>
					<tbody>
						<tr>
							<td>Contenuti che dichiarano sé stessi originali mentre Google dice il contrario</td>
							<td class="num"><strong><?php echo num( count( $piano ) ); ?></strong></td>
						</tr>
					</tbody>
				</table>
			</div>

			<form method="post" action="?p=allinea-canoniche">
				<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
				<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">

				<?php if ( $pagine_fuori > 0 ) : ?>
					<label class="scelta">
						<input type="checkbox" name="pagine" value="1">
						<span>
							<strong>Tocca anche le <?php echo num( $pagine_fuori ); ?> pagine servizio</strong>
							<small>
								Escluse di default, come sempre. Qui non si riscrive niente: cambia solo quale
								indirizzo la pagina dichiara come originale.
							</small>
						</span>
					</label>
				<?php endif; ?>

				<button class="bottone" type="submit"
					onclick="return confirm('Questi contenuti smetteranno di dichiararsi originali e indicheranno la pagina che Google ha già scelto. Restano online, non viene cancellato né riscritto niente, e si disfa in un clic. Procedere?')">
					Allinea le canoniche a quello che Google ha scelto
				</button>
			</form>

			<p class="nota">
				<strong>Che cosa fa davvero:</strong> scrive in Rank Math, su ognuno di questi contenuti, che
				l'originale è la pagina qui sotto — cioè mette per iscritto quello che Google ha già deciso da
				solo. Finché i due non sono d'accordo, Google continua a segnalare il motivo a ogni giro.
			</p>
			<p class="nota">
				<strong>Che cosa non fa:</strong> non cancella niente, non crea redirect, non tocca una riga di
				testo. Le pagine restano online e leggibili, i loro link continuano a valere, e il peso che
				hanno va a rinforzare quella che Google tiene invece di disperdersi. Si disfa da
				<a href="?p=collega&amp;id=<?php echo (int) $audit['id']; ?>">Applica sul sito → «Rimetti le meta com'erano»</a>.
			</p>
		<?php endif; ?>

		<?php foreach ( $gruppi as $g ) : ?>
			<div class="tabellabox">
				<table>
					<thead>
						<tr>
							<th>
								Google tiene: <strong><?php echo e( $g['titolo'] ?: $g['canonica'] ); ?></strong>
								<div class="sotto mono"><?php echo e( $g['canonica'] ); ?></div>
							</th>
							<th class="num"><?php echo num( $g['quante'] ); ?> ignorate</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $g['membri'] as $m ) : ?>
						<tr>
							<td>
								<a href="<?php echo e( $m['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $m['titolo'] ); ?></a>
								<div class="sotto"><?php echo 'page' === $m['tipo'] ? 'pagina servizio' : 'articolo'; ?> #<?php echo e( $m['wp_id'] ); ?></div>
							</td>
							<td class="num stretta">
								<a href="?p=bozze&amp;id=<?php echo (int) $audit['id']; ?>&amp;cerca=<?php echo rawurlencode( (string) $m['titolo'] ); ?>">apri</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>
	</section>
<?php endif; ?>

<?php endif; ?>
