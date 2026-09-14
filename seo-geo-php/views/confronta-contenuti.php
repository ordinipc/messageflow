<?php
/**
 * Che cosa c e dentro a due contenuti, letto dal sito e messo a confronto.
 *
 * @package SeoGeoAudit
 * @var array  $audit    Riga audit.
 * @var bool   $pronto   Collegamento configurato.
 * @var array  $cercati  I due indirizzi o numeri chiesti.
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
	<h1>Che cosa c'è dentro a due articoli</h1>
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

<form class="scheda" method="get" action="">
	<input type="hidden" name="p" value="confronta-contenuti">
	<input type="hidden" name="id" value="<?php echo (int) $audit['id']; ?>">
	<h2>Quali due</h2>
	<p class="guida">Indirizzo completo dell'articolo, oppure il suo numero.</p>
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
