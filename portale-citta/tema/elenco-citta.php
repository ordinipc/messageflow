<?php
/** Home del portale: elenco di tutte le città pubblicate. */

defined( 'PC_AVVIO' ) || exit;
require_once __DIR__ . '/funzioni-tema.php';

$imp    = impostazioni();
$lista  = citta_tutte( true );
$citta  = null;

$titolo      = $imp['brand'] . ' — tutte le città in cui operiamo';
$descrizione = 'Scegli la tua città: ' . implode( ', ', array_slice( array_column( $lista, 'nome' ), 0, 12 ) ) . '.';
$canonico    = base_url() . '/';
$indicizza   = '1' === (string) $imp['indicizza'];
$schemi      = array(
	array(
		'@context' => 'https://schema.org',
		'@type'    => 'CollectionPage',
		'name'     => $titolo,
		'url'      => $canonico,
	),
);

include __DIR__ . '/parti/testa.php';
include __DIR__ . '/parti/barra.php';
?>

<main class="glp-main">
<div class="glp-main__inner">

	<div class="glp-hero">
		<div class="glp-hero__inner">
			<div class="glp-hero__top glp-reveal">
				<p class="glp-hero__eyebrow"><?php echo e( $imp['brand'] ); ?></p>
			</div>
			<div class="glp-hero__main glp-hero__main--solo">
				<div class="glp-hero__content">
					<h1 class="glp-hero__title glp-reveal">Dove <span>operiamo</span></h1>
					<p class="glp-hero__text glp-reveal">Scegli la città più vicina a te: trovi contatti, orari, zone servite e i servizi disponibili.</p>
				</div>
			</div>
		</div>
		<div class="glp-hero__corner" aria-hidden="true"></div>
	</div>

	<div class="glp-sections">
		<?php if ( empty( $lista ) ) : ?>
			<section class="glp-section glp-reveal">
				<h2 class="glp-section__title">Nessuna città pubblicata</h2>
				<p>Accedi all'<a href="<?php echo e( base_url() ); ?>/admin.php">amministrazione</a> per creare la prima città.</p>
			</section>
		<?php else : ?>
			<?php echo sezione_apri( 'citta', 'Le nostre città', 'Copertura' ); ?>
			<ul class="glp-grid glp-grid--citta">
				<?php foreach ( $lista as $c ) : ?>
					<?php $n_pagine = count( pagine_di_citta( $c['id'], true ) ); ?>
					<li>
						<a class="glp-grid__link" href="<?php echo e( url_citta( $c ) ); ?>">
							<span class="glp-grid__title"><?php echo e( $c['nome'] ); ?></span>
							<span class="glp-grid__meta">
								<?php echo e( vuoto( $c['provincia'] ) ? $c['regione'] : $c['provincia'] ); ?>
								<?php if ( $n_pagine > 0 ) : ?>
									· <?php echo (int) $n_pagine; ?> pagine
								<?php endif; ?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			</section>
		<?php endif; ?>
	</div>

</div>
</main>

<?php
$altre = array();
include __DIR__ . '/parti/piede.php';
