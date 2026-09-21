<?php
/** Importazione degli articoli da un'esportazione WordPress. */
defined( 'PC_AVVIO' ) || exit;

$citta    = citta_tutte();
$elenco   = array();
foreach ( $citta as $c ) {
	$elenco[] = array( 'id' => $c['id'], 'nome' => $c['nome'], 'slug' => $c['slug'] );
}
$cartella = import_cartella();
$file_pronti = import_file_disponibili();

/* --- Caricamento di un file --------------------------------------------- */
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_FILES['export'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['export']['error'] ) {
	verifica_token();
	$err = (int) $_FILES['export']['error'];
	if ( UPLOAD_ERR_OK !== $err ) {
		$motivo = ( UPLOAD_ERR_INI_SIZE === $err || UPLOAD_ERR_FORM_SIZE === $err )
			? 'Il file supera il limite di caricamento del server (' . ini_get( 'upload_max_filesize' ) . '). Caricalo via FTP nella cartella dati/import/.'
			: 'Errore durante il caricamento (codice ' . $err . ').';
		avviso( $motivo, 'errore' );
	} else {
		$nome = basename( (string) $_FILES['export']['name'] );
		$est  = strtolower( (string) pathinfo( $nome, PATHINFO_EXTENSION ) );
		if ( ! in_array( $est, array( 'xml', 'zip' ), true ) ) {
			avviso( 'Serve il file .xml dell\'esportazione WordPress, o lo .zip che lo contiene.', 'errore' );
		} elseif ( ! move_uploaded_file( $_FILES['export']['tmp_name'], $cartella . '/' . $nome ) ) {
			avviso( 'Impossibile salvare il file: controlla i permessi di dati/import/.', 'errore' );
		} else {
			avviso( 'File caricato: ' . $nome );
		}
	}
	vai_a( 'admin.php?p=importa' );
}

/* --- Analisi e importazione --------------------------------------------- */
$analisi = null;
$scelto  = (string) ( $_POST['file'] ?? $_GET['file'] ?? '' );
$scelto  = '' === $scelto ? '' : $cartella . '/' . basename( $scelto );

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['azione'] ) && '' !== $scelto ) {
	verifica_token();

	$pronto = import_prepara( $scelto );
	if ( ! $pronto['ok'] ) {
		avviso( $pronto['errore'], 'errore' );
		vai_a( 'admin.php?p=importa' );
	}
	$xml = $pronto['file'];

	$riserva = (string) ( $_POST['riserva'] ?? '' );
	$solo    = (array) ( $_POST['solo_citta'] ?? array() );

	/* --- Anteprima --- */
	if ( 'analizza' === $_POST['azione'] ) {
		$per_citta = array();
		$totale    = 0;
		$gia       = 0;
		$riconosciuti = 0;
		$di_riserva   = 0;
		$esempi    = array();

		import_scorri( $xml, function ( $a ) use ( &$per_citta, &$totale, &$gia, &$esempi, &$riconosciuti, &$di_riserva, $elenco, $riserva ) {
			$totale++;
			if ( articolo_gia_importato( $a['origine'] ) ) {
				$gia++;
				return true;
			}
			// Senza riserva si vede chi viene riconosciuto davvero.
			$vero = import_citta_di( $a['titolo'], $elenco, '' );
			if ( '' === $vero ) {
				$di_riserva++;
			} else {
				$riconosciuti++;
			}

			$id = '' !== $vero ? $vero : ( '' !== $riserva ? $riserva : '(nessuna)' );
			if ( ! isset( $per_citta[ $id ] ) ) {
				$per_citta[ $id ] = 0;
				$esempi[ $id ]    = $a['titolo'];
			}
			$per_citta[ $id ]++;
			return true;
		} );

		arsort( $per_citta );
		$analisi = array(
			'file'         => basename( $scelto ),
			'xml'          => basename( $xml ),
			'totale'       => $totale,
			'gia'          => $gia,
			'riconosciuti' => $riconosciuti,
			'di_riserva'   => $di_riserva,
			'per_citta'    => $per_citta,
			'esempi'       => $esempi,
			'riserva'      => $riserva,
			'luoghi'       => import_luoghi( $xml, $elenco ),
		);
	}

	/* --- Importazione vera --- */
	if ( 'importa' === $_POST['azione'] ) {
		$limite    = max( 1, min( 2000, (int) ( $_POST['limite'] ?? 500 ) ) );
		$pubblica  = isset( $_POST['pubblica'] );
		$importati = 0;
		$saltati   = 0;
		$per_citta = array();

		import_scorri( $xml, function ( $a ) use ( &$importati, &$saltati, &$per_citta, $elenco, $riserva, $solo, $limite, $pubblica ) {
			if ( $importati >= $limite ) {
				return false;
			}
			if ( 'publish' !== $a['stato'] || vuoto( $a['titolo'] ) ) {
				$saltati++;
				return true;
			}
			if ( articolo_gia_importato( $a['origine'] ) ) {
				$saltati++;
				return true;
			}

			$citta_id = import_citta_di( $a['titolo'], $elenco, $riserva );
			if ( '' === $citta_id ) {
				$saltati++;
				return true;
			}
			if ( ! empty( $solo ) && ! in_array( $citta_id, $solo, true ) ) {
				$saltati++;
				return true;
			}

			$corpo = import_pulisci_corpo( $a['corpo'] );
			if ( vuoto( $corpo ) ) {
				$saltati++;
				return true;
			}

			articolo_salva( array(
				'id'       => nuovo_id(),
				'citta_id' => $citta_id,
				'slug'     => articolo_slug_libero( vuoto( $a['slug'] ) ? $a['titolo'] : $a['slug'], $citta_id ),
				'titolo'   => $a['titolo'],
				'estratto' => import_estratto( $a['estratto'], $corpo ),
				'corpo'    => $corpo,
				'data'     => $a['data'],
				'origine'  => $a['origine'],
				'stato'    => $pubblica ? 'pubblicato' : 'bozza',
			) );

			$importati++;
			$per_citta[ $citta_id ] = ( $per_citta[ $citta_id ] ?? 0 ) + 1;
			return true;
		} );

		$dettaglio = array();
		foreach ( $per_citta as $id => $n ) {
			$c = citta_per_id( $id );
			$dettaglio[] = ( $c ? $c['nome'] : '?' ) . ' ' . $n;
		}

		avviso(
			$importati . ' articoli importati' . ( $pubblica ? ' e pubblicati' : ' in bozza' )
			. ( empty( $dettaglio ) ? '' : ' (' . implode( ', ', $dettaglio ) . ')' )
			. '. Saltati ' . $saltati . ' (già importati, non pubblicati o senza testo).'
			. ( $importati >= $limite ? ' Raggiunto il limite di questo giro: premi di nuovo per continuare.' : '' ),
			$importati > 0 ? 'ok' : 'errore'
		);
		vai_a( 'admin.php?p=importa&file=' . rawurlencode( basename( $scelto ) ) );
	}
}

/* --- Creazione delle città trovate nei titoli ---------------------------- */
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['crea_citta'] ) ) {
	verifica_token();
	$create = array();
	foreach ( (array) ( $_POST['luogo'] ?? array() ) as $nome ) {
		$nome = trim( (string) $nome );
		if ( vuoto( $nome ) || citta_per_slug( slugifica( $nome ) ) ) {
			continue;
		}
		$c = citta_predefinita();
		$c['id']        = nuovo_id();
		$c['nome']      = $nome;
		$c['slug']      = citta_slug_libero( $nome );
		$c['provincia'] = strtoupper( trim( (string) ( $_POST['provincia'] ?? '' ) ) );
		$c['stato']     = 'bozza';
		citta_salva( $c );
		$create[] = $nome;
	}
	avviso( empty( $create )
		? 'Nessuna città creata.'
		: count( $create ) . ' città create in bozza (' . implode( ', ', $create ) . '). '
			. 'Compila i loro dati, poi torna qui e rianalizza il file: gli articoli si smisteranno da soli.',
		empty( $create ) ? 'errore' : 'ok' );
	vai_a( 'admin.php?p=importa&file=' . rawurlencode( basename( $scelto ) ) );
}

/* --- Riscrittura dei link interni --------------------------------------- */
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['riscrivi_link'] ) ) {
	verifica_token();

	// Mappa: indirizzo di origine → indirizzo nel portale.
	$mappa = array();
	foreach ( db_righe( 'SELECT id, citta_id, slug, origine FROM ' . db_tab( 'articoli' ) . " WHERE origine <> ''" ) as $r ) {
		$c = citta_per_id( $r['citta_id'] );
		$b = $c ? pagina_blog( $c['id'] ) : null;
		if ( ! $c || ! $b ) {
			continue;
		}
		$nuovo = url_articolo( $c, array( 'slug' => $r['slug'] ), $b );
		$mappa[ rtrim( $r['origine'], '/' ) ]       = $nuovo;
		$mappa[ rtrim( $r['origine'], '/' ) . '/' ] = $nuovo;
	}

	$cambiati = 0;
	$link     = 0;
	foreach ( db_righe( 'SELECT id, corpo FROM ' . db_tab( 'articoli' ) ) as $r ) {
		$prima = (string) $r['corpo'];
		$dopo  = strtr( $prima, $mappa );
		if ( $dopo !== $prima ) {
			$link += substr_count( $prima, 'href=' ) - substr_count( $dopo, 'href=' ) + 1;
			db_esegui( 'UPDATE ' . db_tab( 'articoli' ) . ' SET corpo = ? WHERE id = ?', array( $dopo, $r['id'] ) );
			$cambiati++;
		}
	}
	avviso( $cambiati > 0
		? 'Link interni riscritti in ' . $cambiati . ' articoli: ora puntano alle pagine del portale.'
		: 'Nessun link da riscrivere: o non ce ne sono, o puntano ad articoli non ancora importati.' );
	vai_a( 'admin.php?p=importa' );
}

$totale_articoli = (int) db_valore( 'SELECT COUNT(*) FROM ' . db_tab( 'articoli' ), array(), 0 );
$limite_upload   = ini_get( 'upload_max_filesize' );
?>

<div class="pc-titolo">
	<div>
		<h1>Importa da WordPress</h1>
		<p>Gli articoli vengono smistati fra le città leggendo il titolo.</p>
	</div>
	<?php if ( $totale_articoli > 0 ) : ?>
		<div class="pc-titolo__azioni">
			<a class="pc-btn pc-btn--ghost" href="admin.php?p=articoli"><?php echo $totale_articoli; ?> articoli già nel portale</a>
		</div>
	<?php endif; ?>
</div>

<?php if ( empty( $citta ) ) : ?>
	<div class="pc-avviso pc-avviso--errore">
		Prima di importare servono le città: gli articoli vanno assegnati a una di esse.
		<a href="admin.php?p=citta-modifica">Crea la prima città</a>.
	</div>
<?php endif; ?>

<div class="pc-scheda">
	<h2>1. Il file dell'esportazione</h2>
	<p class="pc-scheda__nota">
		In WordPress: Strumenti → Esporta → Articoli → Scarica il file. Va bene sia il <code>.xml</code> sia lo <code>.zip</code>.
	</p>

	<form method="post" enctype="multipart/form-data">
		<?php echo campo_token(); ?>
		<label>Carica il file
			<input type="file" name="export" accept=".xml,.zip">
			<small>Limite di questo server: <strong><?php echo e( $limite_upload ); ?></strong>.
				Se il file è più grande, caricalo via FTP dentro <code>dati/import/</code> e comparirà qui sotto.</small>
		</label>
		<button class="pc-btn" type="submit">Carica</button>
	</form>
</div>

<?php if ( ! empty( $file_pronti ) ) : ?>
<div class="pc-scheda">
	<h2>2. Cosa contiene</h2>
	<form method="post">
		<?php echo campo_token(); ?>
		<label>File
			<select name="file">
				<?php foreach ( $file_pronti as $f ) : ?>
					<option value="<?php echo e( $f['nome'] ); ?>" <?php selected_pc( $f['nome'], basename( $scelto ) ); ?>>
						<?php echo e( $f['nome'] ); ?> — <?php echo e( media_peso( $f['peso'] ) ); ?> — <?php echo e( $f['data'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>Città di riserva
			<select name="riserva">
				<option value="">— scarta gli articoli senza città riconosciuta —</option>
				<?php foreach ( $citta as $c ) : ?>
					<option value="<?php echo e( $c['id'] ); ?>" <?php selected_pc( $c['id'], (string) ( $_POST['riserva'] ?? '' ) ); ?>><?php echo e( $c['nome'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<small>Dove finiscono gli articoli il cui titolo non nomina nessuna delle tue città.</small>
		</label>
		<button class="pc-btn" type="submit" name="azione" value="analizza">Analizza il file</button>
	</form>
</div>
<?php endif; ?>

<?php if ( $analisi ) : ?>
<div class="pc-scheda">
	<h2>3. Risultato dell'analisi</h2>
	<p class="pc-scheda__nota" id="esito-analisi">
		<?php echo (int) $analisi['totale']; ?> articoli nel file<?php echo $analisi['gia'] > 0 ? ', di cui ' . (int) $analisi['gia'] . ' già importati (verranno saltati)' : ''; ?>.
		<?php echo (int) $analisi['riconosciuti']; ?> nominano una delle tue città;
		gli altri <?php echo (int) $analisi['di_riserva']; ?>
		<?php if ( vuoto( $analisi['riserva'] ) ) : ?>
			verrebbero scartati: scegli una città di riserva.
		<?php else : ?>
			vanno alla città di riserva.
		<?php endif; ?>
	</p>

	<?php
	$mancanti = array_filter( $analisi['luoghi'], function ( $l ) {
		return '' === $l['citta_id'];
	} );
	?>

	<?php if ( ! empty( $mancanti ) ) : ?>
		<?php if ( vuoto( $analisi['riserva'] ) ) : ?>
			<div class="pc-avviso pc-avviso--errore">
				<strong><?php echo (int) $analisi['di_riserva']; ?> articoli non nominano nessuna delle tue città e verrebbero scartati.</strong><br>
				Scegli una città di riserva qui sopra, oppure creane altre dall'elenco qui sotto.
			</div>
		<?php endif; ?>

		<details class="pc-scheda" <?php echo vuoto( $analisi['riserva'] ) ? 'open' : ''; ?>>
			<summary style="cursor:pointer;font-weight:700;font-size:15px">
				I titoli nominano altre <?php echo count( $mancanti ); ?> località
				<span class="pc-nota" style="font-weight:400">— apri solo se vuoi pagine dedicate anche a quei comuni</span>
			</summary>

			<p class="pc-scheda__nota" style="margin-top:14px">
				Non serve fare niente: senza una città loro, questi articoli finiscono nella città
				di riserva, ed è giusto così se vuoi un blog solo. Crea una città soltanto se quel
				comune merita pagine e indirizzi suoi.
			</p>

			<form method="post">
				<?php echo campo_token(); ?>
				<input type="hidden" name="file" value="<?php echo e( $analisi['file'] ); ?>">

				<label style="max-width:200px">Provincia da assegnare
					<input type="text" name="provincia" maxlength="4" placeholder="TP" value="TP">
				</label>

				<table class="pc-tabella">
					<thead><tr><th style="width:28px"></th><th>Località</th><th>Articoli che la nominano</th><th>Nel portale</th></tr></thead>
					<tbody>
					<?php foreach ( $analisi['luoghi'] as $l ) : ?>
						<tr>
							<td>
								<?php if ( '' === $l['citta_id'] ) : ?>
									<input type="checkbox" name="luogo[]" value="<?php echo e( $l['nome'] ); ?>">
								<?php endif; ?>
							</td>
							<td><strong><?php echo e( $l['nome'] ); ?></strong></td>
							<td><?php echo (int) $l['quante']; ?></td>
							<td>
								<?php if ( '' === $l['citta_id'] ) : ?>
									<span class="pc-stato pc-stato--bozza">non è una città</span>
								<?php else : ?>
									<span class="pc-stato pc-stato--pubblicata">c'è</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<button class="pc-btn pc-btn--ghost" type="submit" name="crea_citta" value="1">Crea le località selezionate</button>
			</form>
		</details>
	<?php endif; ?>

	<form method="post">
		<?php echo campo_token(); ?>
		<input type="hidden" name="file" value="<?php echo e( $analisi['file'] ); ?>">
		<input type="hidden" name="riserva" value="<?php echo e( $analisi['riserva'] ); ?>">

		<h3 style="font-size:14px;margin:18px 0 10px">Come verrebbero smistati adesso</h3>
		<table class="pc-tabella" id="tabella-smistamento">
			<thead><tr><th style="width:28px"></th><th>Città</th><th>Articoli</th><th>Esempio di titolo</th></tr></thead>
			<tbody>
			<?php foreach ( $analisi['per_citta'] as $id => $n ) : ?>
				<?php $c = '(nessuna)' === $id ? null : citta_per_id( $id ); ?>
				<tr>
					<td>
						<?php if ( $c ) : ?>
							<input type="checkbox" name="solo_citta[]" value="<?php echo e( $id ); ?>" checked>
						<?php endif; ?>
					</td>
					<td><strong><?php echo $c ? e( $c['nome'] ) : '<span class="pc-nota">nessuna città riconosciuta</span>'; ?></strong></td>
					<td><?php echo (int) $n; ?></td>
					<td class="pc-nota"><?php echo e( mb_substr( $analisi['esempi'][ $id ] ?? '', 0, 70 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div class="pc-riga pc-riga--2" style="margin-top:16px">
			<label>Quanti per volta
				<input type="number" name="limite" value="500" min="1" max="2000">
				<small>Su hosting lenti conviene poco alla volta: il pulsante si ripreme.</small>
			</label>
			<label class="pc-inline" style="align-self:end;margin-bottom:14px">
				<input type="checkbox" name="pubblica" value="1">
				Pubblicali subito
			</label>
		</div>
		<p class="pc-nota">
			Senza la spunta entrano in bozza: li rileggi e li pubblichi quando vuoi, anche in blocco.
		</p>

		<button class="pc-btn" type="submit" name="azione" value="importa">Importa gli articoli selezionati</button>
	</form>
</div>
<?php endif; ?>

<?php if ( $totale_articoli > 0 ) : ?>
<div class="pc-scheda">
	<h2>Link interni</h2>
	<p class="pc-scheda__nota">
		Gli articoli importati contengono link che puntano ancora al sito di origine.
		Questo li fa puntare agli articoli corrispondenti dentro il portale, dove esistono.
	</p>
	<form method="post">
		<?php echo campo_token(); ?>
		<button class="pc-btn pc-btn--ghost" type="submit" name="riscrivi_link" value="1">Riscrivi i link interni</button>
	</form>
</div>

<div class="pc-scheda">
	<h2>Una cosa da decidere</h2>
	<p class="pc-scheda__nota" style="margin:0">
		Gli articoli importati restano pubblicati anche sul sito di origine: lo stesso testo
		finisce a due indirizzi diversi dello stesso dominio, e Google ne sceglie uno solo.
		Delle due l'una: <strong>o</strong> li tieni dove sono e non li pubblichi qui,
		<strong>o</strong> li pubblichi qui e fai un redirect 301 dai vecchi indirizzi ai nuovi.
		La schermata <a href="admin.php?p=seo">SEO e sitemap</a> prepara l'elenco dei redirect.
	</p>
</div>
<?php endif; ?>
