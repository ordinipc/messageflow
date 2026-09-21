<?php
/** Barra superiore. Variabili attese: $citta (facoltativa), $telefono. */
defined( 'PC_AVVIO' ) || exit;
$imp      = impostazioni();
$telefono = isset( $telefono ) ? $telefono : $imp['telefono'];
$home     = vuoto( $imp['sito_principale'] ) ? base_url() . '/' : $imp['sito_principale'];
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

		<?php if ( ! empty( $citta ) ) : ?>
			<span class="glp-topbar__city">
				<span class="glp-citymenu__dot" aria-hidden="true"></span>
				<?php echo e( $citta['nome'] ); ?>
			</span>
		<?php endif; ?>

		<?php if ( ! vuoto( $telefono ) ) : ?>
			<a class="glp-btn glp-btn--tel glp-topbar__cta" href="tel:<?php echo e( tel( $telefono ) ); ?>"><?php echo e( $telefono ); ?></a>
		<?php endif; ?>
	</div>
</header>
