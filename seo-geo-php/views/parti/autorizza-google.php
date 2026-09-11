<?php
/**
 * Blocco per autorizzare l account di servizio in Search Console.
 *
 * Aggiungere un utente a una proprietà si fa solo dall interfaccia di Google:
 * non esiste un API per farlo. Quindi qui si toglie di mezzo tutto il resto —
 * trovare la pagina, scegliere la proprietà, ricopiare l indirizzo a mano.
 *
 * @package SeoGeoAudit
 * @var string $account   Indirizzo dell account di servizio.
 * @var string $proprieta Proprietà di Search Console, anche vuota.
 */

if ( '' === $account ) {
	return;
}

$url = \SeoGeo\Google\SearchConsole::urlUtenti( $proprieta );

?>
<div class="autorizza">
	<p class="guida">
		L account di servizio è un utente a sé: il tuo accesso personale non vale per lui.
		Va autorizzato una volta sola, e da lì funziona da qualsiasi computer e da qualsiasi server.
	</p>

	<div class="campo">
		<label for="account-da-copiare">Indirizzo da autorizzare</label>
		<div class="con-bottone">
			<input id="account-da-copiare" type="text" readonly value="<?php echo e( $account ); ?>">
			<button type="button" class="bottone chiaro" data-copia="account-da-copiare">Copia</button>
		</div>
	</div>

	<p>
		<a class="bottone" href="<?php echo e( $url ); ?>" target="_blank" rel="noopener">
			Apri Search Console e autorizza
		</a>
	</p>

	<ol class="nota">
		<li>Si apre <strong>Utenti e autorizzazioni</strong><?php echo '' !== $proprieta ? ' della proprietà ' . e( $proprieta ) : ''; ?> (se Google chiede quale proprietà, scegli quella).</li>
		<li>Premi <strong>Aggiungi utente</strong>, incolla l indirizzo qui sopra, permesso <strong>Con limitazioni</strong>.</li>
		<li>Torna qui e premi di nuovo <strong>Aggiorna da Search Console</strong>.</li>
	</ol>

	<p class="nota">
		Il pulsante <em>Aggiungi utente</em> lo vedono solo i <strong>proprietari</strong> della proprietà:
		se non c è, l autorizzazione la deve dare chi lo è.
	</p>
</div>

<script>
document.querySelectorAll('[data-copia]').forEach(function (bottone) {
	bottone.addEventListener('click', function () {
		var campo = document.getElementById(bottone.dataset.copia);

		campo.select();
		campo.setSelectionRange(0, 99999);

		var fatto = function () {
			var prima = bottone.textContent;
			bottone.textContent = 'Copiato';
			setTimeout(function () { bottone.textContent = prima; }, 1500);
		};

		// Il primo modo non funziona fuori da https: il secondo sì.
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(campo.value).then(fatto, function () {
				document.execCommand('copy');
				fatto();
			});
		} else {
			document.execCommand('copy');
			fatto();
		}
	});
});
</script>
