<?php
/** Pagina non trovata. */

defined( 'PC_AVVIO' ) || exit;
require_once __DIR__ . '/funzioni-tema.php';

$imp         = impostazioni();
$citta       = null;
$titolo      = 'Pagina non trovata — ' . $imp['brand'];
$descrizione = '';
$canonico    = base_url() . '/';
$indicizza   = false;
$schemi      = array();

include __DIR__ . '/parti/testa.php';
include __DIR__ . '/parti/barra.php';
?>
<main class="glp-main">
<div class="glp-main__inner">
	<div class="glp-sections">
		<section class="glp-section glp-reveal">
			<p class="glp-section__label">Errore 404</p>
			<h1 class="glp-section__title">Questa pagina non esiste</h1>
			<p>L'indirizzo che hai aperto non corrisponde a nessuna pagina pubblicata.</p>
			<?php $lista = citta_tutte( true ); ?>
			<?php if ( ! empty( $lista ) ) : ?>
				<p class="glp-areas__label">Vai a una delle nostre città:</p>
				<ul class="glp-tags glp-tags--links">
					<?php foreach ( $lista as $c ) : ?>
						<li><a href="<?php echo e( url_citta( $c ) ); ?>"><?php echo e( $c['nome'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	</div>
</div>
</main>
<?php
$altre = array();
include __DIR__ . '/parti/piede.php';
