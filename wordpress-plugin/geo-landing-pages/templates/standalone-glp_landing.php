<?php
/**
 * Pagina locale autonoma.
 *
 * Non usa l'intestazione né il piè di pagina del tema: il plugin
 * costruisce tutta la pagina con i colori del brand. L'indirizzo resta
 * quello del sito (/trapani/...), che è ciò che serve per il
 * posizionamento; cambia solo chi disegna la pagina.
 *
 * Per personalizzarlo copialo nel tema in:
 * /wp-content/themes/<tuo-tema>/geo-landing-pages/standalone-glp_landing.php
 *
 * @package geo-landing-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$glp_id    = get_queried_object_id();
$glp_citta = GLP_Post_Types::city_post( $glp_id );
$glp_tel   = GLP_Meta::get( $glp_id, 'telefono' );
$glp_tel   = '' !== $glp_tel ? $glp_tel : GLP_Settings::get( 'telefono', '' );
$glp_brand = GLP_Settings::get( 'brand', get_bloginfo( 'name' ) );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'glp-standalone' ); ?>>

<header class="glp-topbar">
	<div class="glp-topbar__inner">
		<a class="glp-topbar__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php
			if ( has_custom_logo() ) {
				the_custom_logo();
			} else {
				echo '<span class="glp-topbar__name">' . esc_html( $glp_brand ) . '</span>';
			}
			?>
		</a>

		<?php if ( $glp_citta ) : ?>
			<a class="glp-topbar__city" href="<?php echo esc_url( get_permalink( $glp_citta ) ); ?>">
				<span class="glp-citymenu__dot" aria-hidden="true"></span>
				<?php echo esc_html( $glp_citta->post_title ); ?>
			</a>
		<?php endif; ?>

		<?php if ( '' !== $glp_tel ) : ?>
			<a class="glp-btn glp-btn--tel glp-topbar__cta" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $glp_tel ) ); ?>">
				<?php echo esc_html( $glp_tel ); ?>
			</a>
		<?php endif; ?>
	</div>
</header>

<main class="glp-main" role="main">
	<div class="glp-main__inner">
		<?php
		echo GLP_SEO::breadcrumbs_html( $glp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già messo in sicurezza.
		echo GLP_Content::hero( $glp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già messo in sicurezza.
		echo GLP_Content::city_menu( $glp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già messo in sicurezza.

		while ( have_posts() ) :
			the_post();
			?>
			<div class="glp-content"><?php the_content(); ?></div>
			<?php
		endwhile;

		echo GLP_Content::sections( $glp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già messo in sicurezza.
		?>
	</div>
</main>

<footer class="glp-bottombar">
	<div class="glp-bottombar__inner">
		<div class="glp-bottombar__col">
			<p class="glp-bottombar__name"><?php echo esc_html( $glp_brand ); ?></p>
			<?php
			$glp_ind = GLP_Meta::get( $glp_id, 'indirizzo' );
			$glp_cit = GLP_Meta::get( $glp_id, 'citta' );
			$glp_cap = GLP_Meta::get( $glp_id, 'cap' );
			$glp_piv = GLP_Meta::get( $glp_id, 'partita_iva' );
			$glp_piv = '' !== $glp_piv ? $glp_piv : GLP_Settings::get( 'partita_iva', '' );

			if ( '' !== $glp_ind ) :
				?>
				<p><?php echo esc_html( trim( $glp_ind . ', ' . trim( $glp_cap . ' ' . $glp_cit ) ) ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $glp_tel ) : ?>
				<p><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $glp_tel ) ); ?>"><?php echo esc_html( $glp_tel ); ?></a></p>
			<?php endif; ?>

			<?php if ( '' !== $glp_piv ) : ?>
				<p><?php echo esc_html__( 'P. IVA', 'geo-landing-pages' ) . ' ' . esc_html( $glp_piv ); ?></p>
			<?php endif; ?>
		</div>

		<div class="glp-bottombar__col">
			<p class="glp-bottombar__label"><?php esc_html_e( 'Dove operiamo', 'geo-landing-pages' ); ?></p>
			<ul>
				<?php foreach ( GLP_Post_Types::cities( 12 ) as $glp_altra ) : ?>
					<li><a href="<?php echo esc_url( get_permalink( $glp_altra ) ); ?>"><?php echo esc_html( $glp_altra->post_title ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="glp-bottombar__col">
			<p class="glp-bottombar__label"><?php esc_html_e( 'Sito', 'geo-landing-pages' ); ?></p>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Torna al sito principale', 'geo-landing-pages' ); ?></a></li>
				<?php
				foreach ( array( 'privacy-policy', 'cookie-policy', 'note-legali' ) as $glp_slug ) :
					$glp_pag = get_page_by_path( $glp_slug, OBJECT, 'page' );
					if ( ! $glp_pag || 'publish' !== $glp_pag->post_status ) {
						continue;
					}
					?>
					<li><a href="<?php echo esc_url( get_permalink( $glp_pag ) ); ?>"><?php echo esc_html( $glp_pag->post_title ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>

	<p class="glp-bottombar__copy">
		<?php echo esc_html( '© ' . gmdate( 'Y' ) . ' ' . $glp_brand ); ?>
	</p>
</footer>

<?php wp_footer(); ?>
</body>
</html>
