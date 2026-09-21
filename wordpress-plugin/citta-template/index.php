<?php
/**
 * Pagina città — template autonomo.
 *
 * Funziona da solo: nessun CMS, nessuna libreria. Per aggiungere una
 * città duplica la cartella e modifica soltanto config.php.
 */

$c = require __DIR__ . '/config.php';
require __DIR__ . '/funzioni.php';

$telefono = conf( $c, 'telefono' );
$whatsapp = conf( $c, 'whatsapp' );
$servizi  = (array) conf( $c, 'servizi_citta', array() );

// Testo e indirizzo del pulsante principale.
$cta_url   = conf( $c, 'cta.url' );
$cta_testo = conf( $c, 'cta.testo', 'Richiedi un preventivo' );
if ( vuoto( $cta_url ) && ! vuoto( $telefono ) ) {
	$cta_url   = 'tel:' . tel( $telefono );
	$cta_testo = 'Chiama ' . $telefono;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />

<title><?php echo e( conf( $c, 'seo.title', conf( $c, 'servizio' ) . ' a ' . conf( $c, 'citta' ) ) ); ?></title>

<?php if ( ! vuoto( conf( $c, 'seo.description' ) ) ) : ?>
	<meta name="description" content="<?php echo e( conf( $c, 'seo.description' ) ); ?>" />
<?php endif; ?>

<?php if ( ! vuoto( conf( $c, 'url' ) ) ) : ?>
	<link rel="canonical" href="<?php echo e( conf( $c, 'url' ) ); ?>" />
<?php endif; ?>

<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1" />

<meta property="og:type" content="website" />
<meta property="og:title" content="<?php echo e( conf( $c, 'seo.title' ) ); ?>" />
<meta property="og:description" content="<?php echo e( conf( $c, 'seo.description' ) ); ?>" />
<meta property="og:url" content="<?php echo e( conf( $c, 'url' ) ); ?>" />
<meta property="og:locale" content="it_IT" />
<?php if ( ! vuoto( conf( $c, 'seo.immagine' ) ) ) : ?>
	<meta property="og:image" content="<?php echo e( conf( $c, 'seo.immagine' ) ); ?>" />
	<meta name="twitter:card" content="summary_large_image" />
<?php endif; ?>

<?php if ( ! vuoto( conf( $c, 'lat' ) ) && ! vuoto( conf( $c, 'lng' ) ) ) : ?>
	<meta name="geo.position" content="<?php echo e( conf( $c, 'lat' ) . ';' . conf( $c, 'lng' ) ); ?>" />
	<meta name="ICBM" content="<?php echo e( conf( $c, 'lat' ) . ', ' . conf( $c, 'lng' ) ); ?>" />
<?php endif; ?>
<meta name="geo.placename" content="<?php echo e( conf( $c, 'citta' ) ); ?>" />

<link rel="stylesheet" href="assets/style.css" />

<style>
	:root{
		--glp-accent:<?php echo e( conf( $c, 'colori.accento', '#ffd400' ) ); ?>;
		--glp-dark:<?php echo e( conf( $c, 'colori.scuro', '#0d0d0d' ) ); ?>;
		--glp-text:<?php echo e( conf( $c, 'colori.testo', '#111111' ) ); ?>;
		--glp-soft:<?php echo e( conf( $c, 'colori.chiaro', '#f5f5f6' ) ); ?>;
		--glp-radius:<?php echo e( conf( $c, 'colori.raggio', '5px' ) ); ?>;
	}
</style>

<!-- Il contenuto si nasconde solo se il JavaScript è attivo: senza, resta visibile. -->
<script>
	document.documentElement.classList.add('glp-js');
	setTimeout(function(){document.documentElement.classList.add('glp-reveal-fallback');},2500);
</script>

<script type="application/ld+json"><?php echo json_ld( $c ); // phpcs:ignore ?></script>
</head>

<body class="glp-standalone">

<!-- ==========================================================
     BARRA IN ALTO
=========================================================== -->

<header class="glp-topbar">
	<div class="glp-topbar__inner">
		<a class="glp-topbar__brand" href="<?php echo e( conf( $c, 'sito', '/' ) ); ?>">
			<?php if ( ! vuoto( conf( $c, 'logo' ) ) ) : ?>
				<img src="<?php echo e( conf( $c, 'logo' ) ); ?>" alt="<?php echo e( conf( $c, 'brand' ) ); ?>" />
			<?php else : ?>
				<span class="glp-topbar__name"><?php echo e( conf( $c, 'brand' ) ); ?></span>
			<?php endif; ?>
		</a>

		<span class="glp-topbar__city">
			<span class="glp-citymenu__dot" aria-hidden="true"></span>
			<?php echo e( conf( $c, 'citta' ) ); ?>
		</span>

		<?php if ( ! vuoto( $telefono ) ) : ?>
			<a class="glp-btn glp-btn--tel glp-topbar__cta" href="tel:<?php echo e( tel( $telefono ) ); ?>">
				<?php echo e( $telefono ); ?>
			</a>
		<?php endif; ?>
	</div>
</header>

<main class="glp-main">
<div class="glp-main__inner">

<!-- ==========================================================
     INTESTAZIONE
=========================================================== -->

<div class="glp-hero">
	<div class="glp-hero__inner">

		<div class="glp-hero__top glp-reveal">
			<p class="glp-hero__eyebrow">
				<?php echo e( trim( conf( $c, 'brand' ) . ' · ' . conf( $c, 'citta' ), ' ·' ) ); ?>
			</p>
			<?php if ( ! vuoto( conf( $c, 'stato' ) ) ) : ?>
				<div class="glp-hero__status">
					<span class="glp-hero__status-dot" aria-hidden="true"></span>
					<?php echo e( conf( $c, 'stato' ) ); ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="glp-hero__main<?php echo empty( $servizi ) ? ' glp-hero__main--solo' : ''; ?>">
			<div class="glp-hero__content">

				<h1 class="glp-hero__title glp-reveal"><?php echo titolo_hero( $c ); // phpcs:ignore ?></h1>

				<?php if ( ! vuoto( conf( $c, 'intro' ) ) ) : ?>
					<p class="glp-hero__text glp-reveal"><?php echo e( conf( $c, 'intro' ) ); ?></p>
				<?php endif; ?>

				<div class="glp-hero__cta glp-reveal">
					<?php if ( ! vuoto( $cta_url ) ) : ?>
						<a class="glp-btn<?php echo 0 === strpos( $cta_url, 'tel:' ) ? ' glp-btn--tel' : ''; ?>" href="<?php echo e( $cta_url ); ?>">
							<?php echo e( $cta_testo ); ?>
						</a>
					<?php endif; ?>

					<?php if ( ! vuoto( $whatsapp ) ) : ?>
						<a class="glp-btn glp-btn--ghost" rel="nofollow noopener" target="_blank"
							href="https://wa.me/<?php echo e( preg_replace( '/[^0-9]/', '', $whatsapp ) ); ?>?text=<?php echo rawurlencode( 'Salve, vi scrivo da ' . conf( $c, 'citta' ) . ': avrei bisogno di ' . conf( $c, 'servizio' ) . '.' ); ?>">
							Scrivici su WhatsApp
						</a>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! empty( $servizi ) ) : ?>
				<nav class="glp-hero__index glp-reveal" aria-label="Servizi a <?php echo e( conf( $c, 'citta' ) ); ?>">
					<div class="glp-hero__index-title" data-count="<?php echo e( sprintf( '%02d', count( $servizi ) ) ); ?>">
						Servizi a <?php echo e( conf( $c, 'citta' ) ); ?>
					</div>
					<?php foreach ( $servizi as $i => $s ) : ?>
						<a class="glp-hero__service" href="<?php echo e( conf( $s, 'url', '#' ) ); ?>">
							<span class="glp-hero__service-number"><?php echo e( sprintf( '%02d', $i + 1 ) ); ?></span>
							<span class="glp-hero__service-name"><?php echo e( conf( $s, 'nome' ) ); ?></span>
							<span class="glp-hero__service-arrow" aria-hidden="true">→</span>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>

		<div class="glp-hero__bottom glp-reveal">
			<span><?php echo e( trim( conf( $c, 'citta' ) . ' · ' . conf( $c, 'provincia' ), ' ·' ) ); ?></span>
			<div class="glp-hero__bottom-line" aria-hidden="true"></div>
			<span><?php echo e( conf( $c, 'brand' ) ); ?></span>
		</div>

	</div>
	<div class="glp-hero__corner" aria-hidden="true"></div>
</div>

<!-- ==========================================================
     NAVIGAZIONE DELLA CITTÀ
=========================================================== -->

<?php if ( ! empty( $servizi ) ) : ?>
	<nav class="glp-citymenu" aria-label="Navigazione di <?php echo e( conf( $c, 'citta' ) ); ?>">
		<div class="glp-citymenu__inner">
			<a class="glp-citymenu__home is-current" href="<?php echo e( conf( $c, 'url', '#' ) ); ?>" aria-current="page">
				<span class="glp-citymenu__dot" aria-hidden="true"></span>
				<?php echo e( conf( $c, 'citta' ) ); ?>
			</a>
			<ul class="glp-citymenu__list">
				<?php foreach ( $servizi as $s ) : ?>
					<li><a class="glp-citymenu__link" href="<?php echo e( conf( $s, 'url', '#' ) ); ?>"><?php echo e( conf( $s, 'nome' ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</nav>
<?php endif; ?>

<div class="glp-sections">

<?php
/* ==========================================================
   APPROFONDIMENTO
   ========================================================== */
if ( ! vuoto( conf( $c, 'approfondimento' ) ) ) :
	echo sezione_apri( 'approfondimento', maiuscola( conf( $c, 'servizio' ) ) . ' a ' . conf( $c, 'citta' ) . ': cosa sapere', 'Approfondimento' );
	echo paragrafi( conf( $c, 'approfondimento' ) );
	echo '</section>';
endif;

/* ==========================================================
   COSA COMPRENDE
   ========================================================== */
$inclusi = (array) conf( $c, 'inclusi', array() );
if ( ! empty( $inclusi ) ) :
	echo sezione_apri( 'incluso', 'Cosa comprende il servizio', 'Incluso' );
	echo '<ul class="glp-list glp-list--check">';
	foreach ( $inclusi as $voce ) {
		echo '<li>' . e( $voce ) . '</li>';
	}
	echo '</ul></section>';
endif;

/* ==========================================================
   PERCHÉ NOI + NUMERI
   ========================================================== */
$perche = (array) conf( $c, 'perche', array() );
$numeri = (array) conf( $c, 'numeri', array() );
if ( ! empty( $perche ) || ! empty( $numeri ) ) :
	echo sezione_apri( 'perche', 'Perché sceglierci a ' . conf( $c, 'citta' ), 'Perché noi' );

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
			$valore   = conf( $n, 'valore' );
			$numerico = is_numeric( str_replace( array( '.', ',', ' ' ), '', $valore ) );
			echo '<li class="' . ( $numerico ? 'glp-stat--num' : 'glp-stat--text' ) . '">'
				. '<strong>' . e( $valore ) . '</strong>'
				. '<span>' . e( conf( $n, 'etichetta' ) ) . '</span></li>';
		}
		echo '</ul>';
	}

	echo '</section>';
endif;

/* ==========================================================
   COME FUNZIONA
   ========================================================== */
$processo = (array) conf( $c, 'processo', array() );
if ( ! empty( $processo ) ) :
	echo sezione_apri( 'processo', 'Come funziona, passo per passo', 'Processo' );
	echo '<ol class="glp-steps">';
	foreach ( $processo as $passo ) {
		echo '<li class="glp-step"><div class="glp-step__body">';
		if ( ! vuoto( conf( $passo, 'titolo' ) ) ) {
			echo '<h3 class="glp-step__title">' . e( conf( $passo, 'titolo' ) ) . '</h3>';
		}
		if ( ! vuoto( conf( $passo, 'testo' ) ) ) {
			echo '<p>' . e( conf( $passo, 'testo' ) ) . '</p>';
		}
		if ( ! vuoto( conf( $passo, 'durata' ) ) ) {
			echo '<p class="glp-step__time">' . e( conf( $passo, 'durata' ) ) . '</p>';
		}
		echo '</div></li>';
	}
	echo '</ol></section>';
endif;

/* ==========================================================
   PREZZI
   ========================================================== */
$da = conf( $c, 'prezzo.da' );
if ( ! vuoto( $da ) ) :
	$a = conf( $c, 'prezzo.a' );
	echo sezione_apri( 'prezzi', 'Quanto costa', 'Prezzi' );
	echo '<p class="glp-price">' . e( vuoto( $a ) ? 'a partire da ' . $da . ' €' : 'da ' . $da . ' € a ' . $a . ' €' ) . '</p>';

	if ( conf( $c, 'prezzo.gratuito' ) ) {
		echo '<p class="glp-price__free">Preventivo gratuito e senza impegno.</p>';
	}
	if ( ! vuoto( conf( $c, 'prezzo.garanzia' ) ) ) {
		echo '<p class="glp-guarantee">' . e( conf( $c, 'prezzo.garanzia' ) ) . '</p>';
	}
	$pagamenti = (array) conf( $c, 'prezzo.pagamenti', array() );
	if ( ! empty( $pagamenti ) ) {
		echo '<p class="glp-payments">Pagamenti accettati: ' . e( implode( ', ', $pagamenti ) ) . '</p>';
	}
	echo '</section>';
endif;

/* ==========================================================
   ZONE SERVITE
   ========================================================== */
$zone   = (array) conf( $c, 'zone', array() );
$comuni = (array) conf( $c, 'comuni', array() );
if ( ! empty( $zone ) || ! empty( $comuni ) ) :
	echo sezione_apri( 'zone', 'Zone servite a ' . conf( $c, 'citta' ) . ' e dintorni', 'Copertura' );

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
endif;

/* ==========================================================
   RECENSIONI
   ========================================================== */
$recensioni = (array) conf( $c, 'recensioni', array() );
if ( ! empty( $recensioni ) ) :
	echo sezione_apri( 'recensioni', 'Cosa dicono i clienti di ' . conf( $c, 'citta' ), 'Recensioni' );
	echo '<ul class="glp-reviews">';
	foreach ( $recensioni as $r ) {
		if ( vuoto( conf( $r, 'testo' ) ) ) {
			continue;
		}
		$voto = (int) conf( $r, 'voto', 0 );
		echo '<li class="glp-review">';
		if ( $voto > 0 ) {
			echo '<span class="glp-review__rating" aria-label="' . e( $voto ) . ' su 5">' . str_repeat( '★', min( 5, $voto ) ) . '</span>';
		}
		echo '<blockquote>' . e( conf( $r, 'testo' ) ) . '</blockquote>';
		echo '<cite>' . e( trim( conf( $r, 'nome' ) . ( vuoto( conf( $r, 'zona' ) ) ? '' : ' — ' . conf( $r, 'zona' ) ) ) ) . '</cite>';
		echo '</li>';
	}
	echo '</ul></section>';
endif;

/* ==========================================================
   CHI SE NE OCCUPA
   ========================================================== */
$certificazioni = (array) conf( $c, 'certificazioni', array() );
if ( ! vuoto( conf( $c, 'team.nome' ) ) || ! empty( $certificazioni ) ) :
	echo sezione_apri( 'team', 'Chi si occupa del servizio', 'Team' );

	if ( ! vuoto( conf( $c, 'team.nome' ) ) ) {
		echo '<p class="glp-person"><strong>' . e( conf( $c, 'team.nome' ) ) . '</strong>'
			. ( vuoto( conf( $c, 'team.ruolo' ) ) ? '' : ' — ' . e( conf( $c, 'team.ruolo' ) ) ) . '</p>';
		if ( ! vuoto( conf( $c, 'team.qualifiche' ) ) ) {
			echo '<p class="glp-person__creds">' . e( conf( $c, 'team.qualifiche' ) ) . '</p>';
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
endif;

/* ==========================================================
   DOVE SIAMO
   ========================================================== */
if ( ! vuoto( conf( $c, 'indirizzo' ) ) || ! vuoto( conf( $c, 'mappa' ) ) || ! vuoto( conf( $c, 'come_raggiungerci' ) ) ) :
	echo sezione_apri( 'dove', 'Dove siamo e come raggiungerci', 'Sede' );

	if ( ! vuoto( conf( $c, 'indirizzo' ) ) ) {
		$completo = conf( $c, 'indirizzo' ) . ', ' . trim( conf( $c, 'cap' ) . ' ' . conf( $c, 'citta' ) );
		if ( ! vuoto( conf( $c, 'provincia' ) ) ) {
			$completo .= ' (' . conf( $c, 'provincia' ) . ')';
		}
		echo '<p class="glp-address">' . e( $completo ) . '</p>';
	}
	if ( ! vuoto( conf( $c, 'come_raggiungerci' ) ) ) {
		echo paragrafi( conf( $c, 'come_raggiungerci' ) );
	}
	if ( ! vuoto( conf( $c, 'mappa' ) ) ) {
		echo '<div class="glp-map"><iframe src="' . e( conf( $c, 'mappa' ) ) . '" loading="lazy" title="Mappa della sede" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>';
	}
	echo '</section>';
endif;

/* ==========================================================
   ORARI
   ========================================================== */
$orari = (array) conf( $c, 'orari', array() );
if ( ! empty( $orari ) ) :
	echo sezione_apri( 'orari', 'Orari di apertura', 'Orari' );
	echo '<ul class="glp-hours">';
	foreach ( $orari as $giorno => $fascia ) {
		echo '<li><span>' . e( $giorno ) . '</span><strong>' . e( $fascia ) . '</strong></li>';
	}
	echo '</ul></section>';
endif;

/* ==========================================================
   DOMANDE FREQUENTI
   ========================================================== */
$faq = (array) conf( $c, 'faq', array() );
if ( ! empty( $faq ) ) :
	echo sezione_apri( 'faq', 'Domande frequenti', 'FAQ' );
	echo '<div class="glp-faq">';
	$i = 0;
	foreach ( $faq as $riga ) {
		if ( vuoto( conf( $riga, 'domanda' ) ) ) {
			continue;
		}
		echo '<details class="glp-faq__item"' . ( 0 === $i ? ' open' : '' ) . '>';
		echo '<summary class="glp-faq__q">' . e( conf( $riga, 'domanda' ) ) . '</summary>';
		echo '<div class="glp-faq__a">' . paragrafi( conf( $riga, 'risposta' ) ) . '</div>';
		echo '</details>';
		$i++;
	}
	echo '</div></section>';
endif;

/* ==========================================================
   CHIAMATA ALL'AZIONE
   ========================================================== */
if ( ! vuoto( $telefono ) || ! vuoto( $whatsapp ) ) :
	echo sezione_apri( 'cta', 'Richiedi un intervento a ' . conf( $c, 'citta' ), 'Contatti' );
	echo '<div class="glp-cta">';
	if ( ! vuoto( $cta_url ) ) {
		echo '<a class="glp-btn" href="' . e( $cta_url ) . '">' . e( $cta_testo ) . '</a>';
	}
	if ( ! vuoto( $whatsapp ) ) {
		echo '<a class="glp-btn glp-btn--ghost" rel="nofollow noopener" target="_blank" href="https://wa.me/'
			. e( preg_replace( '/[^0-9]/', '', $whatsapp ) ) . '">Scrivici su WhatsApp</a>';
	}
	echo '</div></section>';
endif;

/* ==========================================================
   ALTRE CITTÀ
   ========================================================== */
$altre = (array) conf( $c, 'altre_citta', array() );
if ( ! empty( $altre ) ) :
	echo sezione_apri( 'correlate', 'Operiamo anche in queste città', 'Altre zone' );
	echo '<ul class="glp-tags glp-tags--links">';
	foreach ( $altre as $a ) {
		echo '<li><a href="' . e( conf( $a, 'url', '#' ) ) . '">' . e( conf( $a, 'nome' ) ) . '</a></li>';
	}
	echo '</ul></section>';
endif;
?>

</div><!-- .glp-sections -->
</div><!-- .glp-main__inner -->
</main>

<!-- ==========================================================
     PIÈ DI PAGINA
=========================================================== -->

<footer class="glp-bottombar">
	<div class="glp-bottombar__inner">

		<div class="glp-bottombar__col">
			<p class="glp-bottombar__name"><?php echo e( conf( $c, 'brand' ) ); ?></p>
			<?php if ( ! vuoto( conf( $c, 'indirizzo' ) ) ) : ?>
				<p><?php echo e( conf( $c, 'indirizzo' ) . ', ' . trim( conf( $c, 'cap' ) . ' ' . conf( $c, 'citta' ) ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! vuoto( $telefono ) ) : ?>
				<p><a href="tel:<?php echo e( tel( $telefono ) ); ?>"><?php echo e( $telefono ); ?></a></p>
			<?php endif; ?>
			<?php if ( ! vuoto( conf( $c, 'email' ) ) ) : ?>
				<p><a href="mailto:<?php echo e( conf( $c, 'email' ) ); ?>"><?php echo e( conf( $c, 'email' ) ); ?></a></p>
			<?php endif; ?>
			<?php if ( ! vuoto( conf( $c, 'piva' ) ) ) : ?>
				<p>P. IVA <?php echo e( conf( $c, 'piva' ) ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $altre ) ) : ?>
			<div class="glp-bottombar__col">
				<p class="glp-bottombar__label">Dove operiamo</p>
				<ul>
					<?php foreach ( $altre as $a ) : ?>
						<li><a href="<?php echo e( conf( $a, 'url', '#' ) ); ?>"><?php echo e( conf( $a, 'nome' ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="glp-bottombar__col">
			<p class="glp-bottombar__label">Sito</p>
			<ul>
				<li><a href="<?php echo e( conf( $c, 'sito', '/' ) ); ?>">Torna al sito principale</a></li>
				<?php foreach ( (array) conf( $c, 'legali', array() ) as $l ) : ?>
					<li><a href="<?php echo e( conf( $l, 'url', '#' ) ); ?>"><?php echo e( conf( $l, 'nome' ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>

	</div>

	<p class="glp-bottombar__copy">© <?php echo e( date( 'Y' ) ); ?> <?php echo e( conf( $c, 'brand' ) ); ?></p>
</footer>

<script src="assets/script.js"></script>
</body>
</html>
