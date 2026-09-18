<?php
/**
 * Template della landing locale (usato solo se attivi "template del plugin").
 *
 * Per personalizzarlo copialo nel tema in:
 * /wp-content/themes/<tuo-tema>/geo-landing-pages/single-glp_landing.php
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$glp_id = get_the_ID();
	?>
	<main id="primary" class="glp-main" role="main">
		<article <?php post_class( 'glp-page' ); ?>>

			<?php echo GLP_SEO::breadcrumbs_html( $glp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già messo in sicurezza. ?>

			<header class="glp-hero">
				<h1 class="glp-hero__title"><?php echo esc_html( GLP_Content::h1( $glp_id ) ); ?></h1>
				<?php
				$glp_tel = GLP_Meta::get( $glp_id, 'telefono' );
				$glp_tel = '' !== $glp_tel ? $glp_tel : GLP_Settings::get( 'telefono', '' );
				if ( '' !== $glp_tel ) :
					?>
					<p class="glp-hero__cta">
						<a class="glp-btn glp-btn--primary" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $glp_tel ) ); ?>">
							<?php
							/* translators: %s: numero di telefono. */
							echo esc_html( sprintf( __( 'Chiama %s', 'geo-landing-pages' ), $glp_tel ) );
							?>
						</a>
					</p>
				<?php endif; ?>

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="glp-hero__image"><?php the_post_thumbnail( 'large' ); ?></div>
				<?php endif; ?>
			</header>

			<div class="glp-content">
				<?php the_content(); ?>
			</div>

			<?php echo GLP_Content::sections( $glp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già messo in sicurezza. ?>

		</article>
	</main>
	<?php
endwhile;

get_footer();
