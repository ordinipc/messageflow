<?php
/**
 * Rendimento reale in Google e cose da fare che ne derivano.
 *
 * @package SeoGeoAudit
 * @var array      $cfg         Configurazione.
 * @var bool       $configurato Collegamento a Search Console configurato.
 * @var string[]   $manca       Cosa manca: 'chiave', 'proprieta'.
 * @var bool       $chiave_ok   La chiave c è ed è valida.
 * @var string     $account     Indirizzo dell account di servizio.
 * @var string     $proprieta   Proprietà configurata.
 * @var array[]    $sitemap_sito   Sitemap trovate sul sito.
 * @var array[]    $sitemap_google Sitemap che Google gia conosce.
 * @var array|null $ultima      Ultima rilevazione.
 * @var array|null $precedente  Rilevazione prima di quella.
 * @var array      $spinta      Piano di spinta dai dati di Search Console.
 * @var array[]    $storico     Rilevazioni recenti.
 * @var array[]    $segnali     Cose da fare.
 * @var array[]    $piano       Cose che si possono fare da sole.
 * @var array[]    $piano_pagine Le stesse, contando anche le pagine.
 * @var int        $pagine_fuori Pagine escluse dal piano.
 * @var int        $audit_id    Ultimo audit.
 * @var array[]    $per_tipo    Segnali raggruppati.
 * @var array[]    $pagine_top  Pagine con più impression.
 * @var array[]    $query_top   Ricerche con più impression.
 * @var string     $messaggio   Esito dell ultimo aggiornamento.
 * @var string     $errore      Errore dell ultimo aggiornamento.
 */

$colori = array(
	'quasi_prima_pagina'   => 'alto',
	'titolo_che_non_rende' => 'medio',
	'cannibalizzazione'    => 'grave',
	'in_calo'              => 'grave',
	'mai_mostrata'         => 'basso',
);

/**
 * Variazione fra due numeri, con il segno.
 *
 * @param int|float $ora    Valore attuale.
 * @param int|float $prima  Valore precedente.
 * @param bool      $meglio Se true, scendere è un miglioramento (la posizione).
 * @return string
 */
function delta( $ora, $prima, $meglio = false ) {
	$d = round( (float) $ora - (float) $prima, 1 );

	if ( abs( $d ) < 0.05 ) {
		return '<span class="sotto">invariato</span>';
	}

	$bene   = $meglio ? $d < 0 : $d > 0;
	$segno  = $d > 0 ? '+' : '';
	$numero = number_format( $d, ( $d == (int) $d ) ? 0 : 1, ',', '.' );

	return '<span class="tag ' . ( $bene ? 'ok' : 'grave' ) . '">' . $segno . $numero . '</span>';
}

?>
<section class="intestazione">
	<h1>Rendimento in Google</h1>
	<p class="guida">
		Qui non ci sono previsioni: sono i dati di Search Console, cioè quello che succede davvero
		quando qualcuno cerca. Quando questi numeri e il punteggio dell audit dicono cose diverse,
		hanno ragione questi.
	</p>
</section>

<?php if ( $messaggio ) : ?>
	<p class="avviso ok-bg"><?php echo e( $messaggio ); ?></p>
<?php endif; ?>

<?php if ( $errore ) : ?>
	<p class="avviso grave"><?php echo e( $errore ); ?></p>

	<?php if ( false !== stripos( $errore, 'non è ancora autorizzato' ) || false !== stripos( $errore, 'nessuna proprietà' ) ) : ?>
		<section class="scheda">
			<h2>Autorizza l account in Search Console</h2>
			<?php require __DIR__ . '/parti/autorizza-google.php'; ?>
		</section>
	<?php endif; ?>
<?php endif; ?>

<?php if ( ! $configurato ) : ?>
	<section class="scheda">
		<h2>Search Console non è ancora collegata</h2>

		<?php if ( $chiave_ok ) : ?>
			<p class="avviso ok-bg">La chiave dell account di servizio c è ed è valida.</p>
		<?php endif; ?>

		<p class="guida">
			<?php if ( in_array( 'chiave', $manca, true ) && in_array( 'proprieta', $manca, true ) ) : ?>
				Mancano due cose: la <strong>chiave</strong> dell account di servizio di Google Cloud e la
				<strong>proprietà</strong> di Search Console. Sono cinque minuti e non costa niente:
				le istruzioni passo passo sono nelle Impostazioni.
			<?php elseif ( in_array( 'chiave', $manca, true ) ) : ?>
				Manca la <strong>chiave</strong> dell account di servizio: si carica dalle Impostazioni
				come file JSON, oppure si mette per FTP in <code>storage/google.json</code>.
			<?php else : ?>
				Manca solo la <strong>proprietà</strong>: è il campo
				<em>Proprietà di Search Console</em> nelle Impostazioni, e va scritto esattamente come
				compare in Search Console — <code>sc-domain:tuosito.it</code> se l hai verificata come
				dominio, <code>https://tuosito.it/</code> se l hai verificata con prefisso URL.
			<?php endif; ?>
		</p>

		<p><a class="bottone" href="?p=impostazioni#search-console">Vai alle impostazioni</a></p>
	</section>

	<?php if ( $chiave_ok ) : ?>
		<section class="scheda">
			<h2>E poi: autorizza l account</h2>
			<?php require __DIR__ . '/parti/autorizza-google.php'; ?>
		</section>
	<?php endif; ?>
<?php else : ?>

	<section class="scheda">
		<h2>Aggiorna i dati</h2>
		<p class="guida">
			Scarica gli ultimi <?php echo (int) ( $cfg['google']['giorni'] ?? 28 ); ?> giorni di ricerche e ricalcola le priorità.
			Su molte pagine può richiedere un minuto.
			<?php if ( $account ) : ?>
				Account usato: <code><?php echo e( $account ); ?></code>.
			<?php endif; ?>
		</p>
		<form method="post" action="?p=aggiorna-google">
			<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
			<button class="bottone" type="submit">Aggiorna da Search Console</button>
		</form>
	</section>

	<section class="scheda">
		<h2>Sitemap</h2>
		<p class="guida">
			Dopo aver cambiato parecchi contenuti la sitemap sul sito è già aggiornata — la genera
			WordPress o il plugin SEO — ma Google la ripassa quando gli pare, e possono volerci giorni.
			Da qui gli si dice che è cambiata.
		</p>
		<p class="nota">
			Una cosa che questo <strong>non</strong> fa: forzare l'indicizzazione. L'API di Google che
			indicizza a richiesta è riservata alle offerte di lavoro e agli eventi in diretta, e usarla
			per le pagine normali è fuori dalle sue condizioni. Per una singola pagina urgente, il modo
			giusto resta «Richiedi indicizzazione» dentro a Search Console.
		</p>

		<?php if ( ! $sitemap_sito ) : ?>
			<p class="avviso grave">
				Sul sito non si trova nessuna sitemap agli indirizzi soliti
				(<code>/sitemap_index.xml</code>, <code>/sitemap.xml</code>, <code>/wp-sitemap.xml</code>).
				Controlla che Rank Math abbia le sitemap attive.
			</p>
		<?php else : ?>
			<div class="tabellabox">
				<table>
					<thead>
						<tr><th>Sitemap trovata sul sito</th><th class="num">Voci</th><th>Google la conosce</th><th></th></tr>
					</thead>
					<tbody>
					<?php foreach ( $sitemap_sito as $sm ) : ?>
						<?php
						$nota = null;

						foreach ( $sitemap_google as $g ) {
							if ( rtrim( (string) $g['percorso'], '/' ) === rtrim( $sm['url'], '/' ) ) {
								$nota = $g;
								break;
							}
						}
						?>
						<tr>
							<td class="mono"><a href="<?php echo e( $sm['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( $sm['url'] ); ?></a></td>
							<td class="num"><?php echo num( $sm['url_dentro'] ); ?></td>
							<td>
								<?php if ( $nota ) : ?>
									<?php echo $nota['inviata'] ? 'dal ' . e( substr( (string) $nota['inviata'], 0, 10 ) ) : 'sì'; ?>
									<?php if ( (int) $nota['errori'] > 0 ) : ?>
										<span class="tag grave"><?php echo num( $nota['errori'] ); ?> errori</span>
									<?php endif; ?>
								<?php else : ?>
									<span class="tag alto">no</span>
								<?php endif; ?>
							</td>
							<td>
								<form method="post" action="?p=invia-sitemap" style="margin:0">
									<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
									<input type="hidden" name="sitemap" value="<?php echo e( $sm['url'] ); ?>">
									<button class="bottone chiaro piccolo" type="submit">Dillo a Google</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="nota">
				Serve il permesso di scrittura su Search Console: l'account
				<?php echo $account ? '<code>' . e( $account ) . '</code>' : 'di servizio'; ?>
				dev'essere <strong>proprietario o utente con autorizzazione completa</strong> della proprietà,
				non solo in sola lettura. Se Google risponde «permesso negato», è questo.
			</p>
		<?php endif; ?>
	</section>

	<?php if ( ! $ultima ) : ?>
		<p class="nota">Nessuna rilevazione ancora: premi il pulsante qui sopra per la prima.</p>
	<?php else : ?>

		<div class="riquadri">
			<div class="riquadro">
				<span class="etichetta">Clic</span>
				<strong><?php echo num( $ultima['clic'] ); ?></strong>
				<span class="sotto"><?php echo $precedente ? delta( $ultima['clic'], $precedente['clic'] ) . ' sul periodo prima' : 'prima rilevazione'; ?></span>
			</div>
			<div class="riquadro">
				<span class="etichetta">Impression</span>
				<strong><?php echo num( $ultima['impression'] ); ?></strong>
				<span class="sotto"><?php echo $precedente ? delta( $ultima['impression'], $precedente['impression'] ) . ' sul periodo prima' : 'quante volte sei comparso'; ?></span>
			</div>
			<div class="riquadro">
				<span class="etichetta">Posizione media</span>
				<strong><?php echo number_format( (float) $ultima['posizione_media'], 1, ',', '.' ); ?></strong>
				<span class="sotto"><?php echo $precedente ? delta( $ultima['posizione_media'], $precedente['posizione_media'], true ) . ' (scendere è meglio)' : 'più è bassa, meglio è'; ?></span>
			</div>
			<div class="riquadro">
				<span class="etichetta">Pagine che ricevono visite</span>
				<strong><?php echo num( $ultima['pagine'] ); ?></strong>
				<span class="sotto"><?php echo num( $ultima['query'] ); ?> ricerche diverse</span>
			</div>
		</div>

		<p class="nota">
			Periodo: dal <?php echo e( $ultima['periodo_da'] ); ?> al <?php echo e( $ultima['periodo_a'] ); ?>
			(<?php echo (int) $ultima['giorni']; ?> giorni) · proprietà <code><?php echo e( $ultima['proprieta'] ); ?></code>
			· rilevato il <?php echo e( $ultima['creato_il'] ); ?>
		</p>

		<?php if ( $per_tipo ) : ?>
			<div class="pillole">
				<?php foreach ( $per_tipo as $gruppo ) : ?>
					<span class="pillola <?php echo e( $colori[ $gruppo['tipo'] ] ?? '' ); ?>">
						<?php echo e( $gruppo['titolo'] ); ?> <b><?php echo num( $gruppo['quanti'] ); ?></b>
					</span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<section class="scheda">
			<h2>Da fare, in ordine di convenienza</h2>
			<p class="guida">
				L ordine non è il mio: è quello dei numeri. In cima c è dove si guadagna di più con meno lavoro.
			</p>

			<?php if ( $piano ) : ?>
				<?php
				$per_compito = array();

				foreach ( $piano as $voce ) {
					$per_compito[ $voce['titolo'] ] = ( $per_compito[ $voce['titolo'] ] ?? 0 ) + 1;
				}
				?>

				<p class="guida">
					Di queste, <strong><?php echo num( count( $piano ) ); ?></strong> <?php echo 1 === count( $piano ) ? 'si può applicare da sola' : 'si possono applicare da sole'; ?>:
					il contenuto sul sito è stato riconosciuto, quindi il programma sa dove mettere le mani.
					<?php if ( 1 === $pagine_fuori ) : ?>
						Un altra riguarda una <strong>pagina</strong> e resta fuori: la tocca solo se lo chiedi qui sotto.
					<?php elseif ( $pagine_fuori ) : ?>
						Altre <strong><?php echo num( $pagine_fuori ); ?></strong> riguardano <strong>pagine</strong> e
						restano fuori: le tocca solo se lo chiedi qui sotto.
					<?php endif; ?>
				</p>

				<ul class="elenco-azioni">
					<?php foreach ( $per_compito as $titolo => $quanti ) : ?>
						<li><strong><?php echo num( $quanti ); ?></strong> — <?php echo e( $titolo ); ?></li>
					<?php endforeach; ?>
				</ul>

				<form method="post" action="?p=applica-segnali">
					<input type="hidden" name="token" value="<?php echo e( token() ); ?>">

					<?php if ( $pagine_fuori ) : ?>
						<label class="scelta">
							<input type="checkbox" name="pagine" value="1">
							<span>
								<strong><?php echo 1 === $pagine_fuori ? 'Tocca anche la pagina segnalata' : 'Tocca anche le ' . num( $pagine_fuori ) . ' pagine segnalate'; ?></strong>
								<small>
									Escluse di default: le pagine servizio sono poche e scritte a mano, e qui si
									cambierebbero title e description senza che tu le veda prima. Con la casella
									spuntata le operazioni diventano <?php echo num( count( $piano_pagine ) ); ?> invece
									di <?php echo num( count( $piano ) ); ?>.
								</small>
							</span>
						</label>
					<?php endif; ?>

					<button class="bottone" type="submit">Prepara le modifiche e vai al pilota</button>
				</form>

				<p class="nota">
					Prepara la coda e ti porta al pilota automatico, dove la avvii e la segui. Le riscritture
					passano dalle bozze: niente viene pubblicato senza che tu lo dica. Le meta invece vanno
					sul sito subito, perché sono reversibili con un clic.
				</p>
			<?php else : ?>
				<p class="nota">
					Nessuna di queste indicazioni si traduce in una modifica automatica: o i contenuti non sono
					stati riconosciuti (colonna <em>Contenuto sul sito</em>), o sono segnali che vogliono un occhio
					umano, come le pagine in calo.
				</p>
			<?php endif; ?>
		</section>

		<?php if ( ! empty( $spinta['gruppi'] ) ) : ?>
		<section class="scheda">
			<h2>Spingi le pagine con i link interni</h2>
			<p class="guida">
				Questa non riscrive niente e non costa token. Google stesso dice, ricerca per ricerca,
				quali tue pagine si alternano e quale delle tue sta messa meglio: si prende quella e
				tutte le altre le passano forza, con un link che ha la ricerca per testo.
				<strong>Trentatré pagine che si contendono lo stesso nome non fanno trentatré volte la
				forza: ne fanno un trentatreesimo.</strong>
			</p>

			<div class="tabellabox">
				<table>
					<tbody>
						<tr><td>Ricerche su cui si interviene</td><td class="num"><strong><?php echo num( $spinta['conteggi']['ricerche'] ); ?></strong></td></tr>
						<tr><td>Di queste, contese fra più pagine tue</td><td class="num"><?php echo num( $spinta['conteggi']['contese'] ); ?></td></tr>
						<tr><td>Pagine che smettono di farsi concorrenza e passano forza</td><td class="num"><?php echo num( $spinta['conteggi']['pagine_che_cedono'] ); ?></td></tr>
					</tbody>
				</table>
			</div>

			<details>
				<summary>Le prime dieci, e chi vince</summary>
				<div class="tabellabox">
					<table>
						<thead><tr><th>Ricerca</th><th>Va a</th><th class="num">Pos.</th><th class="num">Impr.</th><th>Perché</th></tr></thead>
						<tbody>
						<?php foreach ( array_slice( $spinta['gruppi'], 0, 10 ) as $g ) : ?>
							<tr>
								<td><strong><?php echo e( $g['query'] ); ?></strong></td>
								<td class="mono"><?php echo e( \SeoGeo\Search\Spinta::confrontabile( $g['vincitore'] ) ); ?></td>
								<td class="num"><?php echo number_format( (float) $g['posizione'], 1, ',', '' ); ?></td>
								<td class="num"><?php echo num( $g['impression'] ); ?></td>
								<td><small><?php echo e( $g['motivo'] ); ?></small></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</details>

			<form method="post" action="?p=applica-spinta">
				<input type="hidden" name="token" value="<?php echo e( token() ); ?>">
				<label class="scelta">
					<span>
						<strong>Quanti link per articolo</strong>
						<small>
							Tre è il valore che si usa di norma: abbastanza per contare, non tanti da
							trasformare il testo in una rete di collegamenti.
						</small>
					</span>
					<input type="number" name="per_articolo" value="3" min="1" max="8" style="width:5rem">
				</label>
				<button class="bottone" type="submit">Attiva la spinta sul sito</button>
			</form>

			<p class="nota">
				I link li mette il plugin <strong>mentre serve la pagina</strong>: nei tuoi contenuti non
				viene scritto niente, nessun 301, nessun testo toccato, e le pagine servizio restano fuori.
				Si spegne rimettendo a zero «link interni per articolo» nelle impostazioni.
				Google ci mette da qualche giorno a qualche settimana a rileggere e a spostare le posizioni.
			</p>
		</section>
		<?php endif; ?>

		<div class="tabellabox">
			<table>
				<thead>
					<tr>
						<th>Cosa</th>
						<th>Contenuto sul sito</th>
						<th class="num">Pos.</th>
						<th class="num">Impr.</th>
						<th class="num">Clic</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( array_slice( $segnali, 0, 100 ) as $segnale ) : ?>
					<tr>
						<td>
							<span class="tag <?php echo e( $colori[ $segnale['tipo'] ] ?? 'basso' ); ?>"><?php echo e( $segnale['titolo'] ); ?></span>
							<div class="sotto"><?php echo e( $segnale['spiegazione'] ); ?></div>
						</td>
						<td>
							<?php if ( ! empty( $segnale['titolo_sito'] ) ) : ?>
								<strong><?php echo e( $segnale['titolo_sito'] ); ?></strong>
								<div class="sotto">
									<?php echo 'page' === $segnale['tipo_sito'] ? 'pagina' : 'articolo'; ?>
									#<?php echo e( $segnale['wp_id'] ); ?> ·
									<a href="<?php echo e( $segnale['url'] ); ?>" target="_blank" rel="noopener">apri</a>
								</div>
							<?php else : ?>
								<a href="<?php echo e( $segnale['url'] ); ?>" target="_blank" rel="noopener"><?php echo e( ltrim( (string) parse_url( $segnale['url'], PHP_URL_PATH ), '/' ) ?: '/' ); ?></a>
								<div class="sotto">non abbinata a un contenuto: rifai l analisi del sito</div>
							<?php endif; ?>
						</td>
						<td class="num"><?php echo $segnale['posizione'] > 0 ? number_format( (float) $segnale['posizione'], 1, ',', '' ) : '—'; ?></td>
						<td class="num"><?php echo num( $segnale['impression'] ); ?></td>
						<td class="num"><?php echo num( $segnale['clic'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $segnali ) : ?>
					<tr><td colspan="5">Nessun segnale: o i dati sono troppo pochi per dire qualcosa, o non c è niente di urgente.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

		<section class="scheda">
			<h2>Le ricerche che ti portano più gente</h2>
			<div class="tabellabox">
				<table>
					<thead><tr><th>Ricerca</th><th class="num">Impr.</th><th class="num">Clic</th><th class="num">CTR</th><th class="num">Pos.</th></tr></thead>
					<tbody>
					<?php foreach ( array_slice( $query_top, 0, 25 ) as $riga ) : ?>
						<tr>
							<td><?php echo e( $riga['query'] ); ?></td>
							<td class="num"><?php echo num( $riga['impression'] ); ?></td>
							<td class="num"><?php echo num( $riga['clic'] ); ?></td>
							<td class="num"><?php echo number_format( (float) $riga['ctr'], 1, ',', '' ); ?>%</td>
							<td class="num"><?php echo number_format( (float) $riga['posizione'], 1, ',', '' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>

		<?php
		$sitemap = json_decode( (string) ( $ultima['sitemap'] ?? '' ), true );
		$sitemap = is_array( $sitemap ) ? $sitemap : array();
		?>

		<?php if ( $sitemap ) : ?>
			<section class="scheda">
				<h2>Sitemap</h2>
				<p class="guida">
					La sitemap la genera WordPress (o il tuo plugin SEO) e si aggiorna da sola a ogni pubblicazione.
					Qui si vede l ultima volta che Google l ha letta: se la data è recente, il giro funziona e non
					c è niente da reinviare.
				</p>
				<div class="tabellabox">
					<table>
						<thead><tr><th>Sitemap</th><th>Letta da Google</th><th class="num">Errori</th><th class="num">Avvisi</th></tr></thead>
						<tbody>
						<?php foreach ( $sitemap as $riga ) : ?>
							<tr>
								<td><a href="<?php echo e( $riga['percorso'] ); ?>" target="_blank" rel="noopener"><?php echo e( $riga['percorso'] ); ?></a></td>
								<td><?php echo $riga['inviata'] ? e( substr( (string) $riga['inviata'], 0, 10 ) ) : '—'; ?></td>
								<td class="num"><?php echo $riga['errori'] ? '<span class="tag grave">' . num( $riga['errori'] ) . '</span>' : '0'; ?></td>
								<td class="num"><?php echo $riga['avvisi'] ? '<span class="tag alto">' . num( $riga['avvisi'] ) . '</span>' : '0'; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php else : ?>
			<p class="nota">
				Nessuna sitemap risulta dichiarata in Search Console. WordPress ne genera una da solo
				(<code>/wp-sitemap.xml</code>, oppure <code>/sitemap_index.xml</code> se usi Rank Math o Yoast):
				inviala una volta da Search Console → Sitemap, poi Google la rilegge per conto suo.
			</p>
		<?php endif; ?>

		<?php if ( count( $storico ) > 1 ) : ?>
			<section class="scheda">
				<h2>Come sta andando nel tempo</h2>
				<div class="tabellabox">
					<table>
						<thead><tr><th>Rilevazione</th><th>Periodo</th><th class="num">Clic</th><th class="num">Impr.</th><th class="num">Pos. media</th></tr></thead>
						<tbody>
						<?php foreach ( $storico as $riga ) : ?>
							<tr>
								<td><?php echo e( $riga['creato_il'] ); ?></td>
								<td class="sotto"><?php echo e( $riga['periodo_da'] ); ?> → <?php echo e( $riga['periodo_a'] ); ?></td>
								<td class="num"><?php echo num( $riga['clic'] ); ?></td>
								<td class="num"><?php echo num( $riga['impression'] ); ?></td>
								<td class="num"><?php echo number_format( (float) $riga['posizione_media'], 1, ',', '' ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

	<?php endif; ?>
<?php endif; ?>
