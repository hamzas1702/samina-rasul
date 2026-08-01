<?php
/**
 * Product category landing page.
 *
 * An editorial arrival for a collection: a full-bleed masthead carrying the
 * category name in display type over its own photograph, then the product grid
 * on the site's shared content width.
 *
 * Deliberately written for any product_cat, not just Formals and Bridals - the
 * only per-category decision is the tone, and that falls back to the warm
 * treatment for anything unrecognised.
 *
 * @package samina-rasul
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sr_term = get_queried_object();

// Categories that read better on the deep burgundy ground. Anything else, and
// anything the client adds later, uses the warm cream treatment.
$sr_deep_categories = array( 'bridals' );
$sr_tone            = ( $sr_term instanceof WP_Term && in_array( $sr_term->slug, $sr_deep_categories, true ) ) ? 'deep' : 'warm';

$sr_heading     = $sr_term instanceof WP_Term ? $sr_term->name : __( 'The collection', 'samina-rasul' );
$sr_description = $sr_term instanceof WP_Term ? term_description( $sr_term ) : '';
$sr_count       = ( $sr_term instanceof WP_Term && $sr_term->count > 0 ) ? (int) $sr_term->count : 0;

// The category's own image, when one has been set in wp-admin.
$sr_hero_image = '';
if ( $sr_term instanceof WP_Term ) {
	$sr_hero_id = (int) get_term_meta( $sr_term->term_id, 'thumbnail_id', true );
	if ( $sr_hero_id > 0 ) {
		$sr_hero_image = wp_get_attachment_image_url( $sr_hero_id, 'full' );
	}
}
?>

<?php
/*
 * A plain wrapper, not <main>: woocommerce_before_main_content below emits
 * Storefront's own <main id="main">, and nesting one inside another produced
 * a duplicate id and two "main" landmarks for screen readers.
 */
?>
<div class="sr-archive">

	<header class="sr-archive__hero sr-archive__hero--<?php echo esc_attr( $sr_tone ); ?><?php echo $sr_hero_image ? ' sr-archive__hero--photo' : ''; ?>">
		<div class="sr-archive__bg" aria-hidden="true" data-sr-parallax="5">
			<?php if ( $sr_hero_image ) : ?>
				<img class="sr-archive__img" src="<?php echo esc_url( $sr_hero_image ); ?>" alt="" fetchpriority="high" decoding="async">
			<?php else : ?>
				<?php echo sr_ornament_svg(); // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG. ?>
			<?php endif; ?>
		</div>
		<div class="sr-archive__scrim" aria-hidden="true"></div>

		<div class="sr-archive__intro">
			<span class="sr-eyebrow"><?php esc_html_e( 'The house of Samina Rasul', 'samina-rasul' ); ?></span>
			<h1 class="sr-archive__title"><?php echo esc_html( $sr_heading ); ?></h1>
			<?php if ( $sr_description ) : ?>
				<div class="sr-archive__lede"><?php echo wp_kses_post( $sr_description ); ?></div>
			<?php endif; ?>
			<?php if ( $sr_count ) : ?>
				<span class="sr-archive__count">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of products in this category. */
							_n( '%s piece', '%s pieces', $sr_count, 'samina-rasul' ),
							number_format_i18n( $sr_count )
						)
					);
					?>
				</span>
			<?php endif; ?>
		</div>
	</header>

	<section class="sr-section sr-archive__products">
		<div class="sr-section__inner">
			<?php
			/**
			 * Standard WooCommerce loop actions rather than a hand-rolled loop.
			 * woocommerce_output_all_notices() is hooked to
			 * woocommerce_before_shop_loop, so skipping it swallowed every
			 * add-to-cart confirmation on this template - the notice then
			 * surfaced, stale, on whatever page the shopper hit next. These
			 * hooks also carry the result count, the archive description and
			 * anything filter/wishlist plugins attach.
			 */
			do_action( 'woocommerce_before_main_content' );

			if ( woocommerce_product_loop() ) {
				echo '<div class="sr-archive__toolbar">';
				do_action( 'woocommerce_before_shop_loop' );
				echo '</div>';

				woocommerce_product_loop_start();

				while ( have_posts() ) {
					the_post();
					wc_get_template_part( 'content', 'product' );
				}

				woocommerce_product_loop_end();

				do_action( 'woocommerce_after_shop_loop' );
			} else {
				do_action( 'woocommerce_no_products_found' );
				?>
				<p class="sr-archive__empty"><?php esc_html_e( 'This collection is being prepared. Pieces will appear here as soon as the atelier releases them.', 'samina-rasul' ); ?></p>
				<a class="button" href="<?php echo esc_url( sr_shop_url() ); ?>"><span><?php esc_html_e( 'Browse all pieces', 'samina-rasul' ); ?></span></a>
				<?php
			}

			do_action( 'woocommerce_after_main_content' );
			?>
		</div>
	</section>

</div>

<?php get_footer(); ?>
