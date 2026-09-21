<?php
/** Gestione del menu di una città. */
defined( 'PC_AVVIO' ) || exit;

$citta_id = (string) ( $_GET['citta'] ?? '' );
$citta    = '' !== $citta_id ? citta_per_id( $citta_id ) : null;

if ( ! $citta ) {
	$tutte = citta_tutte();
	if ( empty( $tutte ) ) {
		echo '<div class="pc-scheda pc-vuoto"><h3>Nessuna città</h3><p>Il menu appartiene a una città.</p><a class="pc-btn" href="admin.php?p=citta-modifica">Crea la prima città</a></div>';
		return;
	}
	$citta    = $tutte[0];
	$citta_id = $citta['id'];
}

/* --- Salvataggio --------------------------------------------------------- */
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['salva_menu'] ) ) {
	verifica_token();

	// L'ordine arriva dalla sequenza dei campi: la prima voce è la prima.
	$dentro  = array_values( (array) ( $_POST['dentro'] ?? array() ) );
	$cambiate = 0;

	foreach ( pagine_di_citta( $citta_id ) as $p ) {
		$posizione = array_search( $p['id'], $dentro, true );
		$nel_menu  = false !== $posizione;

		$nuovo_mostra = $nel_menu ? 1 : 0;
		$nuovo_ordine = $nel_menu ? ( ( (int) $posizione + 1 ) * 10 ) : (int) $p['menu_ordine'];

		if ( (int) $p['menu_mostra'] === $nuovo_mostra && (int) $p['menu_ordine'] === $nuovo_ordine ) {
			continue;
		}
		$p['menu_mostra'] = $nuovo_mostra;
		$p['menu_ordine'] = $nuovo_ordine;
		pagina_salva( $p );
		$cambiate++;
	}

	avviso( $cambiate > 0 ? 'Menu di ' . $citta['nome'] . ' aggiornato.' : 'Nessuna modifica da salvare.' );
	vai_a( 'admin.php?p=menu&citta=' . rawurlencode( $citta_id ) );
}

/* --- Spostamenti singoli (funzionano anche senza JavaScript) -------------- */
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['muovi'] ) ) {
	verifica_token();

	list( $cosa, $id ) = array_pad( explode( ':', (string) $_POST['muovi'], 2 ), 2, '' );
	$bersaglio = pagina_per_id( $id );

	if ( $bersaglio && $bersaglio['citta_id'] === $citta_id ) {
		if ( 'fuori' === $cosa || 'dentro' === $cosa ) {
			$bersaglio['menu_mostra'] = 'dentro' === $cosa ? 1 : 0;
			if ( 'dentro' === $cosa ) {
				// In fondo al menu: l'ordine più alto più dieci.
				$massimo = 0;
				foreach ( pagine_di_citta( $citta_id ) as $p ) {
					if ( (int) $p['menu_mostra'] && (int) $p['menu_ordine'] > $massimo ) {
						$massimo = (int) $p['menu_ordine'];
					}
				}
				$bersaglio['menu_ordine'] = $massimo + 10;
			}
			pagina_salva( $bersaglio );
		} elseif ( 'su' === $cosa || 'giu' === $cosa ) {
			// Si scambia l'ordine con la voce vicina.
			$nel_menu = array();
			foreach ( pagine_di_citta( $citta_id ) as $p ) {
				if ( (int) $p['menu_mostra'] && 'home' !== $p['tipo'] ) {
					$nel_menu[] = $p;
				}
			}
			$posizione = null;
			foreach ( $nel_menu as $i => $p ) {
				if ( $p['id'] === $bersaglio['id'] ) {
					$posizione = $i;
					break;
				}
			}
			$vicina = 'su' === $cosa ? $posizione - 1 : $posizione + 1;
			if ( null !== $posizione && isset( $nel_menu[ $vicina ] ) ) {
				$a = $nel_menu[ $posizione ];
				$b = $nel_menu[ $vicina ];
				$o = (int) $a['menu_ordine'];
				$a['menu_ordine'] = (int) $b['menu_ordine'];
				$b['menu_ordine'] = $o;
				// Ordini identici: non si scambierebbe niente, si rinumera.
				if ( $a['menu_ordine'] === $b['menu_ordine'] ) {
					$a['menu_ordine'] = 'su' === $cosa ? $b['menu_ordine'] - 1 : $b['menu_ordine'] + 1;
				}
				pagina_salva( $a );
				pagina_salva( $b );
			}
		}
	}

	vai_a( 'admin.php?p=menu&citta=' . rawurlencode( $citta_id ) );
}

/* --- Copia il menu su un'altra città ------------------------------------- */
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['copia_su'] ) ) {
	verifica_token();

	$modello = array();
	foreach ( pagine_di_citta( $citta_id ) as $p ) {
		$modello[ $p['slug'] ] = array( (int) $p['menu_mostra'], (int) $p['menu_ordine'] );
	}

	$toccate = 0;
	$nomi    = array();
	foreach ( (array) ( $_POST['destinazione'] ?? array() ) as $altro_id ) {
		$altra = citta_per_id( (string) $altro_id );
		if ( ! $altra || $altra['id'] === $citta_id ) {
			continue;
		}
		foreach ( pagine_di_citta( $altra['id'] ) as $p ) {
			if ( ! isset( $modello[ $p['slug'] ] ) ) {
				continue;
			}
			list( $mostra, $ordine ) = $modello[ $p['slug'] ];
			if ( (int) $p['menu_mostra'] === $mostra && (int) $p['menu_ordine'] === $ordine ) {
				continue;
			}
			$p['menu_mostra'] = $mostra;
			$p['menu_ordine'] = $ordine;
			pagina_salva( $p );
			$toccate++;
		}
		$nomi[] = $altra['nome'];
	}

	avviso( empty( $nomi )
		? 'Nessuna città scelta.'
		: 'Menu copiato su ' . implode( ', ', $nomi ) . ' (' . $toccate . ' pagine allineate). '
			. 'Le pagine che in quelle città non esistono sono state saltate.',
		empty( $nomi ) ? 'errore' : 'ok' );
	vai_a( 'admin.php?p=menu&citta=' . rawurlencode( $citta_id ) );
}

/* --- Dati per la schermata ----------------------------------------------- */
$pagine = pagine_di_citta( $citta_id );
$dentro = array();
$fuori  = array();

foreach ( $pagine as $p ) {
	// La pagina principale è già il pulsante con il nome della città.
	if ( 'home' === $p['tipo'] ) {
		continue;
	}
	if ( (int) $p['menu_mostra'] ) {
		$dentro[] = $p;
	} else {
		$fuori[] = $p;
	}
}

usort( $fuori, function ( $a, $b ) {
	return strcmp( $a['titolo'], $b['titolo'] );
} );

$tutte_citta = citta_tutte();

/** Quanto spazio occupa una voce, all'ingrosso: serve solo per avvisare. */
$larghezza = 0;
foreach ( $dentro as $p ) {
	$larghezza += mb_strlen( $p['titolo'] ) * 7 + 26;
}
$stretto = $larghezza > 820;
?>

<div class="pc-titolo">
	<div>
		<h1>Menu di <?php echo e( $citta['nome'] ); ?></h1>
		<p>La barra in alto delle pagine di questa città.</p>
	</div>
	<div class="pc-titolo__azioni">
		<form method="get" style="display:flex;gap:6px;align-items:center">
			<input type="hidden" name="p" value="menu">
			<select name="citta" onchange="this.form.submit()" style="margin:0;width:auto">
				<?php foreach ( $tutte_citta as $c ) : ?>
					<option value="<?php echo e( $c['id'] ); ?>" <?php selected_pc( $c['id'], $citta_id ); ?>><?php echo e( $c['nome'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</form>
		<a class="pc-btn pc-btn--ghost" href="<?php echo e( url_citta( $citta ) ); ?>" target="_blank" rel="noopener">Vedi la città ↗</a>
	</div>
</div>

<?php if ( empty( $pagine ) ) : ?>
	<div class="pc-scheda pc-vuoto">
		<h3>Nessuna pagina</h3>
		<p>Il menu si costruisce dalle pagine della città: creane qualcuna.</p>
		<a class="pc-btn" href="admin.php?p=pagine&citta=<?php echo e( $citta_id ); ?>">Vai alle pagine</a>
	</div>
	<?php return; ?>
<?php endif; ?>

<!-- Anteprima -->
<div class="pc-scheda">
	<h2>Come si vedrà</h2>
	<div class="pc-barra-finta">
		<span class="pc-barra-finta__marchio"><?php echo e( impostazione( 'brand', '' ) ); ?></span>
		<span class="pc-barra-finta__citta">● <?php echo e( mb_strtoupper( $citta['nome'] ) ); ?></span>
		<span class="pc-barra-finta__voci" id="anteprima-menu">
			<?php foreach ( $dentro as $p ) : ?>
				<span class="pc-barra-finta__voce"><?php echo e( $p['titolo'] ); ?></span>
			<?php endforeach; ?>
		</span>
		<span class="pc-barra-finta__tel"><?php echo e( contatto( $citta, 'telefono' ) ); ?></span>
	</div>
	<p class="pc-nota" id="avviso-larghezza" style="margin-top:10px<?php echo $stretto ? '' : ';display:none'; ?>">
		⚠ Con queste voci la barra va a capo su una seconda riga. Non è un errore —
		i collegamenti restano tutti visibili — ma se preferisci una riga sola, togli qualche voce.
	</p>
</div>

<form method="post" id="modulo-menu">
	<?php echo campo_token(); ?>

	<div class="pc-griglia-2">
		<!-- NEL MENU -->
		<div class="pc-scheda">
			<h2>Nel menu <span class="pc-nota" id="conta-dentro">(<?php echo count( $dentro ); ?>)</span></h2>
			<p class="pc-scheda__nota">Dall'alto in basso = da sinistra a destra nella barra.</p>

			<ul class="pc-lista-menu" id="lista-dentro">
				<?php foreach ( $dentro as $i => $p ) : ?>
					<li class="pc-voce" data-titolo="<?php echo e( $p['titolo'] ); ?>">
						<input type="hidden" name="dentro[]" value="<?php echo e( $p['id'] ); ?>">
						<span class="pc-voce__num"><?php echo (int) $i + 1; ?></span>
						<span class="pc-voce__nome">
							<?php echo e( $p['titolo'] ); ?>
							<small><?php echo e( 'home' === $p['tipo'] ? 'principale' : $p['tipo'] ); ?> · /<?php echo e( $p['slug'] ); ?>/</small>
						</span>
						<span class="pc-voce__azioni">
							<button type="submit" name="muovi" value="su:<?php echo e( $p['id'] ); ?>" class="pc-tondo" title="Sposta su" aria-label="Sposta su">↑</button>
							<button type="submit" name="muovi" value="giu:<?php echo e( $p['id'] ); ?>" class="pc-tondo" title="Sposta giù" aria-label="Sposta giù">↓</button>
							<button type="submit" name="muovi" value="fuori:<?php echo e( $p['id'] ); ?>" class="pc-tondo pc-tondo--rosso" title="Togli dal menu" aria-label="Togli dal menu">×</button>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( empty( $dentro ) ) : ?>
				<p class="pc-nota" id="vuoto-dentro">Il menu è vuoto: la barra mostrerà solo il nome della città e il telefono.</p>
			<?php else : ?>
				<p class="pc-nota" id="vuoto-dentro" style="display:none">Il menu è vuoto.</p>
			<?php endif; ?>
		</div>

		<!-- FUORI -->
		<div class="pc-scheda">
			<h2>Fuori dal menu <span class="pc-nota" id="conta-fuori">(<?php echo count( $fuori ); ?>)</span></h2>
			<p class="pc-scheda__nota">Esistono e si aprono, ma non compaiono nella barra.</p>

			<ul class="pc-lista-menu" id="lista-fuori">
				<?php foreach ( $fuori as $p ) : ?>
					<li class="pc-voce" data-titolo="<?php echo e( $p['titolo'] ); ?>">
						<span class="pc-voce__nome">
							<?php echo e( $p['titolo'] ); ?>
							<small><?php echo e( $p['tipo'] ); ?> · /<?php echo e( $p['slug'] ); ?>/</small>
						</span>
						<span class="pc-voce__azioni">
							<button type="submit" name="muovi" value="dentro:<?php echo e( $p['id'] ); ?>" class="pc-tondo pc-tondo--verde" title="Metti nel menu" aria-label="Metti nel menu">+</button>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( empty( $fuori ) ) : ?>
				<p class="pc-nota" id="vuoto-fuori">Tutte le pagine sono nel menu.</p>
			<?php else : ?>
				<p class="pc-nota" id="vuoto-fuori" style="display:none">Tutte le pagine sono nel menu.</p>
			<?php endif; ?>
		</div>
	</div>

	<div class="pc-salva">
		<p class="pc-salva__nota">La pagina principale non compare qui: è già il pulsante con il nome della città.</p>
		<button class="pc-btn" type="submit" name="salva_menu" value="1">Salva il menu</button>
	</div>
</form>

<?php if ( count( $tutte_citta ) > 1 ) : ?>
<div class="pc-scheda">
	<h2>Usa questo menu anche altrove</h2>
	<p class="pc-scheda__nota">
		Copia quali pagine stanno nel menu e in che ordine, abbinandole per indirizzo.
		Le pagine che in quelle città non esistono vengono saltate.
	</p>
	<form method="post">
		<?php echo campo_token(); ?>
		<?php foreach ( $tutte_citta as $c ) : ?>
			<?php if ( $c['id'] === $citta_id ) { continue; } ?>
			<p class="pc-inline">
				<input type="checkbox" name="destinazione[]" value="<?php echo e( $c['id'] ); ?>" id="dest-<?php echo e( $c['id'] ); ?>">
				<label for="dest-<?php echo e( $c['id'] ); ?>" style="margin:0;font-weight:600"><?php echo e( $c['nome'] ); ?></label>
			</p>
		<?php endforeach; ?>
		<button class="pc-btn pc-btn--ghost" type="submit" name="copia_su" value="1"
			data-conferma="Sovrascrivere il menu delle città selezionate?">Copia il menu</button>
	</form>
</div>
<?php endif; ?>
