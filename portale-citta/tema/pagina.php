<?php
/**
 * Modello di una pagina città.
 * Variabili attese da index.php: $citta, $pagina.
 */

defined( 'PC_AVVIO' ) || exit;
require_once __DIR__ . '/funzioni-tema.php';

$imp      = impostazioni();
$telefono = contatto( $citta, 'telefono' );
$whatsapp = contatto( $citta, 'whatsapp' );
$servizi  = servizi_citta( $citta );
$altre    = altre_citta( $citta['id'] );
$menu     = menu_citta( $citta, $pagina['id'] );

// Dati per la testa del documento.
$titolo      = seo_titolo( $citta, $pagina );
$descrizione = seo_descrizione( $citta, $pagina );
$canonico    = seo_canonico( $citta, $pagina );
$immagine    = vuoto( $pagina['immagine'] ) ? url_media( $imp['logo'] ) : url_media( $pagina['immagine'] );
$indicizza   = seo_indicizzabile( $citta, $pagina );
$css_extra   = $citta['css'] . "\n" . $pagina['css'];
$js_extra    = $citta['js'] . "\n" . $pagina['js'];
$schemi      = array(
	schema_attivita( $citta ),
	schema_servizio( $citta, $pagina ),
	schema_faq( $pagina['faq'] ),
	schema_breadcrumb( $citta, $pagina ),
);

// Pulsante principale.
$cta_url   = '';
$cta_testo = '';
if ( ! vuoto( $telefono ) ) {
	$cta_url   = 'tel:' . tel( $telefono );
	$cta_testo = 'Chiama ' . $telefono;
}

include __DIR__ . '/parti/testa.php';
include __DIR__ . '/parti/barra.php';
?>

<main class="glp-main">
<div class="glp-main__inner">

<?php if ( 'pubblicata' !== $pagina['stato'] || 'pubblicata' !== $citta['stato'] ) : ?>
	<p style="background:#b00;color:#fff;padding:10px 14px;border-radius:6px;font:600 13px/1.4 system-ui,sans-serif;margin:0 0 16px">
		Anteprima: questa pagina è in bozza e non è visibile al pubblico né a Google.
	</p>
<?php endif; ?>

<!-- INTESTAZIONE -->
<div class="glp-hero">
	<div class="glp-hero__inner">

		<div class="glp-hero__top glp-reveal">
			<p class="glp-hero__eyebrow"><?php echo e( trim( $imp['brand'] . ' · ' . $citta['nome'], ' ·' ) ); ?></p>
			<?php if ( ! vuoto( $telefono ) ) : ?>
				<div class="glp-hero__status">
					<span class="glp-hero__status-dot" aria-hidden="true"></span>
					Interveniamo a <?php echo e( $citta['nome'] ); ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="glp-hero__main<?php echo empty( $servizi ) ? ' glp-hero__main--solo' : ''; ?>">
			<div class="glp-hero__content">
				<h1 class="glp-hero__title glp-reveal"><?php echo titolo_hero( $citta, $pagina ); // HTML controllato. ?></h1>

				<?php
				$intro = vuoto( $pagina['intro'] ) ? $citta['intro'] : $pagina['intro'];
				if ( ! vuoto( $intro ) ) :
					?>
					<p class="glp-hero__text glp-reveal"><?php echo e( $intro ); ?></p>
				<?php endif; ?>

				<div class="glp-hero__cta glp-reveal">
					<?php if ( ! vuoto( $cta_url ) ) : ?>
						<a class="glp-btn glp-btn--tel" href="<?php echo e( $cta_url ); ?>"><?php echo e( $cta_testo ); ?></a>
					<?php endif; ?>
					<?php if ( ! vuoto( $whatsapp ) ) : ?>
						<a class="glp-btn glp-btn--ghost" rel="nofollow noopener" target="_blank"
							href="<?php echo e( url_whatsapp( $whatsapp, 'Salve, vi scrivo da ' . $citta['nome'] . ': avrei bisogno di ' . mb_strtolower( $pagina['titolo'] ) . '.' ) ); ?>">
							Scrivici su WhatsApp
						</a>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! empty( $servizi ) ) : ?>
				<nav class="glp-hero__index glp-reveal" aria-label="Servizi a <?php echo e( $citta['nome'] ); ?>">
					<div class="glp-hero__index-title" data-count="<?php echo e( sprintf( '%02d', count( $servizi ) ) ); ?>">
						Servizi a <?php echo e( $citta['nome'] ); ?>
					</div>
					<?php foreach ( $servizi as $i => $s ) : ?>
						<a class="glp-hero__service" href="<?php echo e_url( $s['url'] ); ?>">
							<span class="glp-hero__service-number"><?php echo e( sprintf( '%02d', $i + 1 ) ); ?></span>
							<span class="glp-hero__service-name"><?php echo e( $s['nome'] ); ?></span>
							<span class="glp-hero__service-arrow" aria-hidden="true">→</span>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>

		<div class="glp-hero__bottom glp-reveal">
			<span><?php echo e( trim( $citta['nome'] . ' · ' . $citta['provincia'], ' ·' ) ); ?></span>
			<div class="glp-hero__bottom-line" aria-hidden="true"></div>
			<span><?php echo e( $imp['brand'] ); ?></span>
		</div>
	</div>
	<div class="glp-hero__corner" aria-hidden="true"></div>
</div>

<!-- MENU DELLA CITTÀ -->
<?php if ( ! empty( $menu ) ) : ?>
	<nav class="glp-citymenu" aria-label="Navigazione di <?php echo e( $citta['nome'] ); ?>">
		<div class="glp-citymenu__inner">
			<a class="glp-citymenu__home<?php echo 'home' === $pagina['tipo'] ? ' is-current' : ''; ?>" href="<?php echo e( url_citta( $citta ) ); ?>"<?php echo 'home' === $pagina['tipo'] ? ' aria-current="page"' : ''; ?>>
				<span class="glp-citymenu__dot" aria-hidden="true"></span>
				<?php echo e( $citta['nome'] ); ?>
			</a>
			<ul class="glp-citymenu__list">
				<?php foreach ( $menu as $v ) : ?>
					<li><a class="glp-citymenu__link<?php echo $v['attiva'] ? ' is-current' : ''; ?>" href="<?php echo e_url( $v['url'] ); ?>"<?php echo $v['attiva'] ? ' aria-current="page"' : ''; ?>><?php echo e( $v['nome'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</nav>
<?php endif; ?>

<div class="glp-sections">
<?php
/* --- Testo principale --------------------------------------------------- */
if ( ! vuoto( $pagina['corpo'] ) ) {
	echo sezione_apri( 'approfondimento', seo_h1( $citta, $pagina ) . ': cosa sapere', 'Approfondimento' );
	echo paragrafi( $pagina['corpo'] );
	echo '</section>';
}

/* --- HTML libero della pagina ------------------------------------------- */
if ( ! vuoto( $pagina['html'] ) ) {
	echo '<section class="glp-section glp-section--libero glp-reveal">' . $pagina['html'] . '</section>';
}

/* --- Cosa comprende ------------------------------------------------------ */
$inclusi = righe( $pagina['inclusi'] );
if ( ! empty( $inclusi ) ) {
	echo sezione_apri( 'incluso', 'Cosa comprende il servizio', 'Incluso' );
	echo '<ul class="glp-list glp-list--check">';
	foreach ( $inclusi as $voce ) {
		echo '<li>' . e( $voce ) . '</li>';
	}
	echo '</ul></section>';
}

/* --- Perché noi + numeri ------------------------------------------------- */
$perche = righe( $citta['perche'] );
$numeri = (array) $citta['numeri'];
if ( ! empty( $perche ) || ! empty( $numeri ) ) {
	echo sezione_apri( 'perche', 'Perché sceglierci a ' . $citta['nome'], 'Perché noi' );
	if ( ! empty( $perche ) ) {
		echo '<ul class="glp-list glp-list--why">';
		foreach ( $perche as $voce ) {
			echo '<li>' . e( $voce ) . '</li>';
		}
		echo '</ul>';
	}
	if ( ! empty( $numeri ) ) {
		echo '<ul class="glp-stats">';
		foreach ( $numeri as $n ) {
			$valore = isset( $n['valore'] ) ? $n['valore'] : '';
			if ( vuoto( $valore ) ) {
				continue;
			}
			$numerico = is_numeric( str_replace( array( '.', ',', ' ' ), '', $valore ) );
			echo '<li class="' . ( $numerico ? 'glp-stat--num' : 'glp-stat--text' ) . '">'
				. '<strong>' . e( $valore ) . '</strong>'
				. '<span>' . e( isset( $n['etichetta'] ) ? $n['etichetta'] : '' ) . '</span></li>';
		}
		echo '</ul>';
	}
	echo '</section>';
}

/* --- Come funziona ------------------------------------------------------- */
$processo = (array) $pagina['processo'];
if ( ! empty( $processo ) ) {
	echo sezione_apri( 'processo', 'Come funziona, passo per passo', 'Processo' );
	echo '<ol class="glp-steps">';
	foreach ( $processo as $passo ) {
		$t = isset( $passo['titolo'] ) ? $passo['titolo'] : '';
		$x = isset( $passo['testo'] ) ? $passo['testo'] : '';
		$d = isset( $passo['durata'] ) ? $passo['durata'] : '';
		if ( vuoto( $t ) && vuoto( $x ) ) {
			continue;
		}
		echo '<li class="glp-step"><div class="glp-step__body">';
		if ( ! vuoto( $t ) ) {
			echo '<h3 class="glp-step__title">' . e( $t ) . '</h3>';
		}
		if ( ! vuoto( $x ) ) {
			echo '<p>' . e( $x ) . '</p>';
		}
		if ( ! vuoto( $d ) ) {
			echo '<p class="glp-step__time">' . e( $d ) . '</p>';
		}
		echo '</div></li>';
	}
	echo '</ol></section>';
}

/* --- Prezzi -------------------------------------------------------------- */
if ( ! vuoto( $pagina['prezzo_da'] ) ) {
	echo sezione_apri( 'prezzi', 'Quanto costa', 'Prezzi' );
	$testo = vuoto( $pagina['prezzo_a'] )
		? 'a partire da ' . $pagina['prezzo_da'] . ' €'
		: 'da ' . $pagina['prezzo_da'] . ' € a ' . $pagina['prezzo_a'] . ' €';
	echo '<p class="glp-price">' . e( $testo ) . '</p>';
	if ( ! vuoto( $pagina['prezzo_note'] ) ) {
		echo paragrafi( $pagina['prezzo_note'] );
	}
	echo '</section>';
}

/* --- Zone servite -------------------------------------------------------- */
$zone   = righe( $citta['zone'] );
$comuni = righe( $citta['comuni'] );
if ( ! empty( $zone ) || ! empty( $comuni ) ) {
	echo sezione_apri( 'zone', 'Zone servite a ' . $citta['nome'] . ' e dintorni', 'Copertura' );
	if ( ! empty( $zone ) ) {
		echo '<p class="glp-areas__label">Quartieri e zone della città:</p><ul class="glp-tags">';
		foreach ( $zone as $z ) {
			echo '<li>' . e( $z ) . '</li>';
		}
		echo '</ul>';
	}
	if ( ! empty( $comuni ) ) {
		echo '<p class="glp-areas__label">Comuni limitrofi:</p><ul class="glp-tags">';
		foreach ( $comuni as $z ) {
			echo '<li>' . e( $z ) . '</li>';
		}
		echo '</ul>';
	}
	echo '</section>';
}

/* --- Recensioni ---------------------------------------------------------- */
$recensioni = (array) $citta['recensioni'];
if ( ! empty( $recensioni ) ) {
	echo sezione_apri( 'recensioni', 'Cosa dicono i clienti di ' . $citta['nome'], 'Recensioni' );
	echo '<ul class="glp-reviews">';
	foreach ( $recensioni as $r ) {
		$testo = isset( $r['testo'] ) ? $r['testo'] : '';
		if ( vuoto( $testo ) ) {
			continue;
		}
		$voto = isset( $r['voto'] ) ? (int) $r['voto'] : 0;
		echo '<li class="glp-review">';
		if ( $voto > 0 ) {
			echo '<span class="glp-review__rating" aria-label="' . e( $voto ) . ' su 5">' . str_repeat( '★', min( 5, $voto ) ) . '</span>';
		}
		echo '<blockquote>' . e( $testo ) . '</blockquote>';
		$firma = trim( ( isset( $r['nome'] ) ? $r['nome'] : '' ) . ( vuoto( isset( $r['zona'] ) ? $r['zona'] : '' ) ? '' : ' — ' . $r['zona'] ) );
		if ( '' !== $firma ) {
			echo '<cite>' . e( $firma ) . '</cite>';
		}
		echo '</li>';
	}
	echo '</ul></section>';
}

/* --- Team e certificazioni ----------------------------------------------- */
$team           = (array) $citta['team'];
$certificazioni = righe( $citta['certificazioni'] );
if ( ! empty( $team ) || ! empty( $certificazioni ) ) {
	echo sezione_apri( 'team', 'Chi si occupa del servizio', 'Team' );
	foreach ( $team as $persona ) {
		$nome = isset( $persona['nome'] ) ? $persona['nome'] : '';
		if ( vuoto( $nome ) ) {
			continue;
		}
		$ruolo = isset( $persona['ruolo'] ) ? $persona['ruolo'] : '';
		echo '<p class="glp-person"><strong>' . e( $nome ) . '</strong>' . ( vuoto( $ruolo ) ? '' : ' — ' . e( $ruolo ) ) . '</p>';
		if ( ! vuoto( isset( $persona['qualifiche'] ) ? $persona['qualifiche'] : '' ) ) {
			echo '<p class="glp-person__creds">' . e( $persona['qualifiche'] ) . '</p>';
		}
	}
	if ( ! empty( $certificazioni ) ) {
		echo '<ul class="glp-list glp-list--certs">';
		foreach ( $certificazioni as $voce ) {
			echo '<li>' . e( $voce ) . '</li>';
		}
		echo '</ul>';
	}
	echo '</section>';
}

/* --- Dove siamo ---------------------------------------------------------- */
if ( ! vuoto( $citta['indirizzo'] ) || ! vuoto( $citta['mappa'] ) || ! vuoto( $citta['raggiungerci'] ) ) {
	echo sezione_apri( 'dove', 'Dove siamo e come raggiungerci', 'Sede' );
	if ( ! vuoto( $citta['indirizzo'] ) ) {
		$completo = $citta['indirizzo'] . ', ' . trim( $citta['cap'] . ' ' . $citta['nome'] );
		if ( ! vuoto( $citta['provincia'] ) ) {
			$completo .= ' (' . $citta['provincia'] . ')';
		}
		echo '<p class="glp-address">' . e( $completo ) . '</p>';
	}
	if ( ! vuoto( $citta['raggiungerci'] ) ) {
		echo paragrafi( $citta['raggiungerci'] );
	}
	if ( ! vuoto( $citta['mappa'] ) ) {
		echo '<div class="glp-map"><iframe src="' . e_url( $citta['mappa'] ) . '" loading="lazy" title="Mappa della sede a ' . e( $citta['nome'] ) . '" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>';
	}
	echo '</section>';
}

/* --- Orari --------------------------------------------------------------- */
$orari = array_filter( (array) $citta['orari'], function ( $v ) { return ! vuoto( $v ); } );
if ( ! empty( $orari ) ) {
	echo sezione_apri( 'orari', 'Orari di apertura', 'Orari' );
	echo '<ul class="glp-hours">';
	foreach ( $orari as $giorno => $fascia ) {
		echo '<li><span>' . e( maiuscola( $giorno ) ) . '</span><strong>' . e( $fascia ) . '</strong></li>';
	}
	echo '</ul></section>';
}

/* --- FAQ ----------------------------------------------------------------- */
$faq = (array) $pagina['faq'];
if ( ! empty( $faq ) ) {
	echo sezione_apri( 'faq', 'Domande frequenti', 'FAQ' );
	echo '<div class="glp-faq">';
	$i = 0;
	foreach ( $faq as $riga ) {
		$d = isset( $riga['domanda'] ) ? $riga['domanda'] : '';
		if ( vuoto( $d ) ) {
			continue;
		}
		echo '<details class="glp-faq__item"' . ( 0 === $i ? ' open' : '' ) . '>';
		echo '<summary class="glp-faq__q">' . e( $d ) . '</summary>';
		echo '<div class="glp-faq__a">' . paragrafi( isset( $riga['risposta'] ) ? $riga['risposta'] : '' ) . '</div>';
		echo '</details>';
		$i++;
	}
	echo '</div></section>';
}

/* --- Chiamata all'azione -------------------------------------------------- */
if ( ! vuoto( $telefono ) || ! vuoto( $whatsapp ) ) {
	echo sezione_apri( 'cta', 'Richiedi un intervento a ' . $citta['nome'], 'Contatti' );
	echo '<div class="glp-cta">';
	if ( ! vuoto( $cta_url ) ) {
		echo '<a class="glp-btn" href="' . e( $cta_url ) . '">' . e( $cta_testo ) . '</a>';
	}
	if ( ! vuoto( $whatsapp ) ) {
		echo '<a class="glp-btn glp-btn--ghost" rel="nofollow noopener" target="_blank" href="' . e( url_whatsapp( $whatsapp ) ) . '">Scrivici su WhatsApp</a>';
	}
	echo '</div></section>';
}

/* --- Altre città ---------------------------------------------------------- */
if ( ! empty( $altre ) ) {
	echo sezione_apri( 'correlate', 'Operiamo anche in queste città', 'Altre zone' );
	echo '<ul class="glp-tags glp-tags--links">';
	foreach ( $altre as $a ) {
		echo '<li><a href="' . e_url( $a['url'] ) . '">' . e( $a['nome'] ) . '</a></li>';
	}
	echo '</ul></section>';
}
?>
</div><!-- .glp-sections -->
</div><!-- .glp-main__inner -->
</main>

<?php include __DIR__ . '/parti/piede.php'; ?>
