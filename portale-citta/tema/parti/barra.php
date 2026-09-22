<?php
/**
 * Intestazione: logo, città, menu della città e pulsante di chiamata.
 *
 * Il menu vive qui dentro, non sotto l'intestazione. Su schermi stretti
 * si chiude dietro un pulsante e si apre come pannello a discesa.
 *
 * Variabili attese: $citta (facoltativa), $menu (facoltativo), $telefono.
 */

defined( 'PC_AVVIO' ) || exit;

$imp      = impostazioni();
$telefono = isset( $telefono ) ? $telefono : $imp['telefono'];
$menu     = isset( $menu ) ? (array) $menu : array();
$home     = vuoto( $imp['sito_principale'] ) ? base_url() . '/' : $imp['sito_principale'];
$in_home  = isset( $pagina ) && 'home' === $pagina['tipo'];
?>
<header class="glp-topbar">
	<div class="glp-topbar__inner">

		<a class="glp-topbar__brand" href="<?php echo e_url( $home ); ?>">
			<?php if ( ! vuoto( $imp['logo'] ) ) : ?>
				<img src="<?php echo e( url_media( $imp['logo'] ) ); ?>" alt="<?php echo e( $imp['brand'] ); ?>">
			<?php else : ?>
				<span class="glp-topbar__name"><?php echo e( $imp['brand'] ); ?></span>
			<?php endif; ?>
		</a>

		<?php
		// Senza menu la pastiglia della città sta in barra. Con il menu
		// entra nel menu stesso: sul telefono il pannello si apre con la
		// città in cima e la barra resta larga abbastanza per il numero.
		if ( ! empty( $citta ) && empty( $menu ) ) :
			?>
			<a class="glp-topbar__city<?php echo $in_home ? ' is-current' : ''; ?>" href="<?php echo e( url_citta( $citta ) ); ?>"<?php echo $in_home ? ' aria-current="page"' : ''; ?>>
				<span class="glp-citymenu__dot" aria-hidden="true"></span>
				<?php echo e( $citta['nome'] ); ?>
			</a>
		<?php endif; ?>

		<?php if ( ! empty( $menu ) ) : ?>
			<button class="glp-topbar__toggle" type="button"
				aria-expanded="false" aria-controls="glp-menu"
				aria-label="Apri il menu di <?php echo e( $citta['nome'] ); ?>">
				<span class="glp-topbar__burger" aria-hidden="true"></span>
			</button>

			<nav class="glp-topbar__nav" id="glp-menu" aria-label="Pagine di <?php echo e( $citta['nome'] ); ?>">
				<ul class="glp-topbar__list">
					<li class="glp-topbar__voce-citta">
						<a class="glp-topbar__city<?php echo $in_home ? ' is-current' : ''; ?>" href="<?php echo e( url_citta( $citta ) ); ?>"<?php echo $in_home ? ' aria-current="page"' : ''; ?>>
							<span class="glp-citymenu__dot" aria-hidden="true"></span>
							<?php echo e( $citta['nome'] ); ?>
						</a>
					</li>
					<?php foreach ( $menu as $v ) : ?>
						<li>
							<a class="glp-topbar__link<?php echo $v['attiva'] ? ' is-current' : ''; ?>"
								href="<?php echo e_url( $v['url'] ); ?>"<?php echo $v['attiva'] ? ' aria-current="page"' : ''; ?>>
								<?php echo e( $v['nome'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>

		<?php if ( ! vuoto( $telefono ) ) : ?>
			<?php $etichetta = impostazione( 'telefono_etichetta', '' ); ?>
			<a class="glp-btn glp-btn--tel glp-topbar__cta" href="tel:<?php echo e( tel( $telefono ) ); ?>">
				<span class="glp-topbar__cta-icona" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor" focusable="false"><path d="M6.6 10.8a15.1 15.1 0 006.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.2.4 2.4.6 3.7.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 013 4c0-.6.4-1 1-1h3.4c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.7.1.4 0 .8-.2 1z"/></svg>
				</span>
				<span class="glp-topbar__cta-testo">
					<?php if ( ! vuoto( $etichetta ) ) : ?>
						<small class="glp-topbar__cta-etichetta"><?php echo e( $etichetta ); ?></small>
					<?php endif; ?>
					<strong class="glp-topbar__cta-numero"><?php echo e( $telefono ); ?></strong>
				</span>
			</a>
		<?php endif; ?>

	</div>
</header>
