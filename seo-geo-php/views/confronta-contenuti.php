<?php
/**
 * Che cosa c e dentro a due contenuti, letto dal sito e messo a confronto.
 *
 * @package SeoGeoAudit
 * @var array  $audit    Riga audit.
 * @var bool   $pronto   Collegamento configurato.
 * @var array  $cercati  I due indirizzi o numeri chiesti.
 * @var array  $sito     Stato del CSS generato del sito.
 * @var array  $diagnosi Risposte del sito, per posizione.
 * @var string $errore   Errore.
 */

/**
 * Appiattisce la diagnosi in coppie chiave => valore leggibile.
 *
 * Serve per poterle mettere una accanto all altra e vedere dove
 * differiscono: e l unica cosa che conta in questa pagina.
 *
 * @param array  $dati    Diagnosi.
 * @param string $prefisso Percorso corrente.
 * @return array<string,string>
 */
function appiattisci( array $dati, $prefisso = '' ) {
	$fuori = array();

	foreach ( $dati as $chiave => $valore ) {
		if ( in_array( $chiave, array( 'ok' ), true ) ) {
			continue;
		}

		$nome = $prefisso ? $prefisso . ' › ' . $chiave : (string) $chiave;

		if ( is_array( $valore ) ) {
			$fuori += appiattisci( $valore, $nome );
			continue;
		}

		if ( is_bool( $valore ) ) {
			$valore = $valore ? 'sì' : 'no';
		}

		$fuori[ $nome ] = (string) $valore;
	}

	return $fuori;
}

$a = isset( $diagnosi[0] ) ? appiattisci( (array) $diagnosi[0] ) : array();
$b = isset( $diagnosi[1] ) ? appiattisci( (array) $diagnosi[1] ) : array();

// Le voci che cambiano da un articolo all altro: sono quelle che spiegano
// perche due pagine si vedono diverse.
$chiavi = array_keys( $a + $b );

$diverse = array_values(
	array_filter(
		$chiavi,
		static function ( $c ) use ( $a, $b ) {
			return ( $a[ $c ] ?? '' ) !== ( $b[ $c ] ?? '' );
		}
	)
);

// Il titolo e la data cambiano sempre fra due articoli diversi: dirlo non
// aiuta nessuno.
$ovvie = array( 'id', 'titolo', 'modificato', 'revisioni' );
?>
<section class="intestazione">
	<p class="briciole"><a href="?p=home">Audit archiviati</a> › <a href="?p=audit&amp;id=<?php echo (int) $audit['id']; ?>"><?php echo e( $audit['sito_nome'] ); ?></a> › Che cosa c'è dentro</p>
	<h1>Perché il sito si vede diverso</h1>
	<p class="guida">
		Legge dal database del sito, non dalla pagina. Serve quando due articoli si vedono diversi
		e non si capisce perché: qui si vede <strong>che cosa è diverso davvero</strong> — chi ha scritto
		il testo, com'è fatta la struttura di Elementor, e soprattutto come sono impostati nel tema,
		che è quello che decide l'aspetto dell'intestazione.
	</p>
</section>

<?php if ( ! $pronto ) : ?>
	<p class="avviso grave">Il collegamento a WordPress non è configurato: senza, non si può leggere niente dal sito.</p>
<?php endif; ?>

<?php if ( $errore ) : ?><p class="avviso grave"><?php echo e( $errore ); ?></p><?php endif; ?>

<?php if ( ! empty( $sito ) ) : ?>
	<?php
	$cartella = (array) ( $sito['cartella'] ?? array() );
	$kit      = (array) ( $sito['kit'] ?? array() );

	// Le due condizioni che spiegano un cambio di aspetto su tutto il sito
	// insieme: il file dei colori globali che non c e, o la cartella che non
	// si lascia scrivere e quindi non lo fa tornare.
	$guasto = empty( $kit['esiste'] ) || empty( $cartella['scrivibile'] );
	?>
	<section class="scheda">
		<h2>Il CSS generato del sito</h2>
		<p class="guida">
			Elementor genera dei file CSS e li tiene in una cartella. Dentro c'è anche il file del
			<strong>kit</strong>, che porta i colori e i caratteri globali: se quello manca, cambia
			l'aspetto di tutto il sito in una volta, senza che nessun articolo sia stato toccato.
		</p>

		<?php if ( $guasto ) : ?>
			<p class="avviso grave">
				<?php if ( empty( $kit['esiste'] ) ) : ?>
					<strong>Il file dei colori globali non c'è.</strong>
					Elementor lo rigenera da solo alla prima visita: se non è tornato,
					è perché non riesce a scriverlo.
				<?php else : ?>
					<strong>La cartella non è scrivibile.</strong>
					Elementor non può rigenerare i suoi file: finché resta così, gli stili non tornano.
				<?php endif; ?>
			</p>
			<p class="guida">
				Da sistemare sull'hosting: la cartella <code><?php echo e( (string) ( $cartella['percorso'] ?? '' ) ); ?></code>
				deve essere scrivibile (permessi 755, proprietario l'utente del sito). Poi in WordPress:
				<em>Elementor → Strumenti → Cancella file e dati</em>, e ricarica una pagina.
			</p>
		<?php else : ?>
			<p class="avviso ok-bg">
				Il CSS del sito è a posto: il file dei colori globali c'è ed è stato scritto il
				<?php echo e( (string) ( $kit['quando'] ?? '' ) ); ?>. Se l'aspetto è cambiato,
				non è per un file mancante.
			</p>
		<?php endif; ?>

		<div class="tabellabox">
			<table>
				<tbody>
					<tr><td>Cartella</td><td class="mono"><?php echo e( (string) ( $cartella['percorso'] ?? '' ) ); ?></td></tr>
					<tr><td>Esiste</td><td><?php echo ! empty( $cartella['esiste'] ) ? 'sì' : 'no'; ?></td></tr>
					<tr><td>Scrivibile</td><td><?php echo ! empty( $cartella['scrivibile'] ) ? 'sì' : '<strong>no</strong>'; ?></td></tr>
					<tr><td>File dentro</td><td><?php echo num( $cartella['file'] ?? 0 ); ?></td></tr>
					<tr><td>File dei colori globali</td><td><?php echo ! empty( $kit['esiste'] ) ? e( (string) $kit['file'] ) . ' · ' . num( ( $kit['byte'] ?? 0 ) / 1024 ) . ' KB · ' . e( (string) $kit['quando'] ) : '<strong>manca</strong>'; ?></td></tr>
					<tr><td>Come stampa il CSS</td><td><?php echo e( (string) ( $sito['elementor']['modo_css'] ?? '' ) ); ?></td></tr>
					<tr><td>Elementor</td><td><?php echo e( (string) ( $sito['elementor']['versione'] ?? '' ) ); ?></td></tr>
					<tr><td>Tema</td><td><?php echo e( (string) ( $sito['tema']['nome'] ?? '' ) ); ?> <?php echo e( (string) ( $sito['tema']['versione'] ?? '' ) ); ?></td></tr>
				</tbody>
			</table>
		</div>

		<?php if ( ! empty( $cartella['ultimi'] ) ) : ?>
			<details>
				<summary>Gli ultimi file scritti</summary>
				<div class="tabellabox">
					<table>
						<tbody>
						<?php foreach ( (array) $cartella['ultimi'] as $f ) : ?>
							<tr>
								<td class="mono"><?php echo e( (string) $f['nome'] ); ?></td>
								<td><?php echo num( ( $f['byte'] ?? 0 ) / 1024 ); ?> KB</td>
								<td><?php echo e( (string) $f['quando'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</details>
		<?php endif; ?>
	</section>
<?php endif; ?>

<form class="scheda" method="get" action="">
	<input type="hidden" name="p" value="confronta-contenuti">
	<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">
	<h2>Confronta due articoli</h2>
	<p class="guida">
		Serve quando <strong>alcuni</strong> articoli si vedono diversi da altri. Se sono cambiati
		tutti insieme, la risposta è qui sopra, non in questo confronto.
		Indirizzo completo dell'articolo, oppure il suo numero.
	</p>
	<p><input type="text" name="a" value="<?php echo e( $cercati[0] ?? '' ); ?>" placeholder="https://iltuosito.it/primo-articolo/" style="width:100%;max-width:520px;padding:8px;border:1px solid var(--linea);border-radius:4px"></p>
	<p><input type="text" name="b" value="<?php echo e( $cercati[1] ?? '' ); ?>" placeholder="https://iltuosito.it/secondo-articolo/" style="width:100%;max-width:520px;padding:8px;border:1px solid var(--linea);border-radius:4px"></p>
	<p><button class="bottone" type="submit">Confronta</button></p>
</form>

<?php if ( $a || $b ) : ?>
<section class="scheda">
	<h2><?php echo num( count( array_diff( $diverse, $ovvie ) ) ); ?> differenze</h2>
	<?php if ( ! array_diff( $diverse, $ovvie ) ) : ?>
		<p class="guida">
			I due articoli sono impostati allo stesso modo. Se sullo schermo si vedono diversi,
			la differenza non è nei dati di questi contenuti: guarda la larghezza della finestra,
			l'immagine in evidenza, o una regola CSS che dipende da qualcos'altro.
		</p>
	<?php endif; ?>
	<div class="tabellabox">
		<table>
			<thead>
				<tr>
					<th>Che cosa</th>
					<th><?php echo e( $diagnosi[0]['titolo'] ?? 'Primo' ); ?></th>
					<th><?php echo e( $diagnosi[1]['titolo'] ?? 'Secondo' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $chiavi as $chiave ) : ?>
				<?php
				$uno  = $a[ $chiave ] ?? '—';
				$due  = $b[ $chiave ] ?? '—';
				$cambia = $uno !== $due && ! in_array( $chiave, $ovvie, true );
				?>
				<tr>
					<td<?php echo $cambia ? ' class="mono"' : ''; ?>>
						<?php echo $cambia ? '<strong>' . e( $chiave ) . '</strong>' : e( $chiave ); ?>
						<?php echo $cambia ? ' <span class="tag alto">diverso</span>' : ''; ?>
					</td>
					<td><?php echo e( mb_substr( $uno, 0, 200 ) ); ?></td>
					<td><?php echo e( mb_substr( $due, 0, 200 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
<?php endif; ?>
