<?php
/**
 * I clienti seguiti.
 *
 * @package SeoGeoAudit
 * @var array[] $clienti   Elenco dei clienti.
 * @var string  $corrente  Quello su cui si sta lavorando.
 * @var array   $globali   Chiavi dell agenzia.
 * @var string  $messaggio Esito dell ultima operazione.
 * @var string  $errore    Errore dell ultima operazione.
 */
?>
<section class="intestazione">
	<h1>Clienti</h1>
	<p class="guida">
		Ogni cliente ha la sua cartella: le sue impostazioni, le sue analisi, le sue riscritture.
		Non è una comodità — è il motivo per cui il lavoro fatto per uno non può comparire
		nell'altro, nemmeno per un errore di programmazione.
	</p>
</section>

<?php if ( $errore ) : ?><p class="avviso grave"><?php echo e( $errore ); ?></p><?php endif; ?>
<?php if ( $messaggio ) : ?><p class="avviso ok-bg"><?php echo e( $messaggio ); ?></p><?php endif; ?>

<section class="scheda">
	<h2><?php echo num( count( $clienti ) ); ?> <?php echo 1 === count( $clienti ) ? 'cliente' : 'clienti'; ?></h2>

	<?php if ( $clienti ) : ?>
		<div class="tabellabox">
			<table>
				<thead>
					<tr><th>Cliente</th><th class="num">Analisi</th><th class="num">Punteggio</th><th>Ultima</th><th class="stretta"></th></tr>
				</thead>
				<tbody>
				<?php foreach ( $clienti as $c ) : ?>
					<tr>
						<td>
							<strong><?php echo e( $c['nome'] ); ?></strong>
							<?php if ( $c['slug'] === $corrente ) : ?>
								<span class="tag ok">in lavorazione</span>
							<?php endif; ?>
							<div class="sotto">
								<?php if ( '' !== $c['url'] ) : ?>
									<a href="<?php echo e( $c['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $c['url'] ); ?></a>
								<?php else : ?>
									<em>indirizzo del sito non ancora impostato</em>
								<?php endif; ?>
							</div>
						</td>
						<td class="num"><?php echo num( (int) $c['analisi'] ); ?></td>
						<td class="num"><?php echo null === $c['punteggio'] ? '—' : num( (int) $c['punteggio'] ); ?></td>
						<td class="sotto"><?php echo e( substr( (string) $c['quando'], 0, 16 ) ) ?: '—'; ?></td>
						<td class="stretta">
							<?php if ( $c['slug'] === $corrente ) : ?>
								<a href="?p=home">apri</a>
							<?php else : ?>
								<a href="?p=home&amp;sito=<?php echo e( $c['slug'] ); ?>">passa a questo</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<p class="guida">Non c'è ancora nessun cliente. Creane uno qui sotto.</p>
	<?php endif; ?>

	<form method="post" action="?p=clienti-nuovo">
		<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
		<label for="nome-cliente">Nome del cliente nuovo</label>
		<input id="nome-cliente" type="text" name="nome" placeholder="Officina Pastore"
			style="width:min(360px,100%);padding:8px;border:1px solid var(--linea);border-radius:4px" required>
		<button class="bottone" type="submit">Crea</button>
	</form>

	<p class="nota">
		Creandolo si apre la sua scheda impostazioni: lì vanno l'indirizzo del sito, il token del
		plugin installato su quel WordPress e i dati aziendali, che finiscono nei dati strutturati.
		Il database del cliente nasce da solo alla prima analisi.
	</p>
</section>

<section class="scheda">
	<h2>Le chiavi dell'agenzia</h2>
	<p class="guida">
		Gemini e Google li paghi tu, non il cliente: la chiave si scrive una volta e vale per tutti.
		<strong>Il consumo resta separato</strong>, perché i token li conta il database di ognuno —
		così sai quanto ti è costato ogni cliente senza doverti fare i conti a mano.
	</p>
	<div class="tabellabox">
		<table>
			<tbody>
				<tr>
					<td>Chiave Gemini condivisa</td>
					<td><?php echo ! empty( $globali['ai']['chiave'] ) ? '<span class="tag ok">impostata</span>' : 'non impostata'; ?></td>
				</tr>
				<tr>
					<td>Account di servizio Google condiviso</td>
					<td><?php echo ! empty( $globali['google']['chiave_json'] ) ? '<span class="tag ok">impostato</span>' : 'non impostato'; ?></td>
				</tr>
			</tbody>
		</table>
	</div>
	<p class="nota">
		Si scrivono dalle <a href="?p=impostazioni">impostazioni</a> di un cliente qualsiasi, spuntando
		«vale per tutti i clienti». Un cliente può sempre averne una sua: quella del cliente vince su
		quella dell'agenzia. La proprietà di Search Console invece è sempre del singolo cliente, perché
		è il suo sito.
	</p>
</section>
