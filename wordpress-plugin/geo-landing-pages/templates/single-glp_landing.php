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

			<?php echo GLP_Content::hero( $glp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già messo in sicurezza. ?>

			<div class="glp-content">
				<?php the_content(); ?>
			</div>

			<?php echo GLP_Content::sections( $glp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già messo in sicurezza. ?>

		</article>
	</main>
	<?php
endwhile;

get_footer();
