<?php
/**
 * The Bridal collection.
 *
 * Bridals are not browsed the way Formals are. There is no price, no cart and
 * no size to pick — the piece is designed in conversation — so a four-up grid
 * of thumbnails asks the wrong question. This is a lookbook instead: one piece
 * at a time, full-bleed, with the specification card overlapping the image and
 * the sides alternating so the eye is carried down the page.
 *
 * Only the bridals term uses this; every other product_cat keeps the shared
 * archive at taxonomy-product_cat.php.
 *
 * @package samina-rasul
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sr_term = get_queried_object();

// Customizer → Site Content → Page banners, falling back to the category's own
// image in wp-admin.
$sr_hero_image = '';
if ( $sr_term instanceof WP_Term ) {
	$sr_hero_id = sr_archive_banner_id( $sr_term );
	if ( $sr_hero_id > 0 ) {
		// The LCP element: srcset and dimensions matter more here than anywhere
		// else on the page, so it goes through wp_get_attachment_image().
		$sr_hero_image = wp_get_attachment_image(
			$sr_hero_id,
			'full',
			false,
			array(
				'class'         => 'sr-lb-hero__img',
				'alt'           => '',
				'sizes'         => '100vw',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
			)
		);
	}
}

$sr_heading = $sr_term instanceof WP_Term ? $sr_term->name : __( 'The Bridal Collection', 'samina-rasul' );
?>

<div class="sr-lookbook">

	<header class="sr-lb-hero<?php echo $sr_hero_image ? ' sr-lb-hero--photo' : ''; ?>">
		<div class="sr-lb-hero__bg" aria-hidden="true" data-sr-parallax="5">
			<?php if ( $sr_hero_image ) : ?>
				<?php echo $sr_hero_image; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() output. ?>
			<?php else : ?>
				<?php echo sr_ornament_svg(); // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG. ?>
			<?php endif; ?>
		</div>
		<div class="sr-lb-hero__scrim" aria-hidden="true"></div>

		<div class="sr-lb-hero__content">
			<p class="sr-eyebrow"><?php echo esc_html( sr_home_text( 'sr_lookbook_eyebrow' ) ); ?></p>
			<h1 class="sr-lb-hero__title"><?php echo esc_html( $sr_heading ); ?></h1>
			<ul class="sr-lb-hero__meta">
				<?php foreach ( array( 'sr_lookbook_meta_1', 'sr_lookbook_meta_2', 'sr_lookbook_meta_3' ) as $sr_meta ) : ?>
					<?php $sr_meta_text = sr_home_text( $sr_meta ); ?>
					<?php if ( '' !== trim( $sr_meta_text ) ) : ?>
						<li><?php echo esc_html( $sr_meta_text ); ?></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</div>
	</header>

	<div class="sr-lb-manifesto">
		<p><?php echo wp_kses_post( sr_home_text( 'sr_lookbook_manifesto' ) ); ?></p>
	</div>

	<?php
	// WooCommerce's notices are hooked to woocommerce_before_main_content; the
	// lookbook still has to emit them or an add-to-cart message from elsewhere
	// surfaces, stale, on whatever page the shopper reaches next.
	do_action( 'woocommerce_before_main_content' );

	if ( woocommerce_product_loop() ) :
		?>
		<div class="sr-lb-items">
			<?php
			$sr_index = 0;
			while ( have_posts() ) :
				the_post();
				global $product;

				if ( ! $product instanceof WC_Product ) {
					continue;
				}

				$sr_index++;
				// Alternate which side the image sits on, starting with the image right.
				$sr_side  = ( 0 === $sr_index % 2 ) ? 'left' : 'right';
				$sr_specs = sr_product_spec_rows( $product, 3 );
				$sr_sku   = $product->get_sku();
				$sr_desc  = $product->get_short_description();
				?>
				<article class="sr-lb-item sr-lb-item--<?php echo esc_attr( $sr_side ); ?>" data-sr-reveal>
					<?php
					/*
					 * The whole entry is one click target. Hidden from assistive
					 * technology and out of the tab order on purpose: the title
					 * and the "View bridal set" link below already point here,
					 * and a third announcement of the same destination is noise.
					 */
					?>
					<a class="sr-lb-item__overlay" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1"></a>

					<div class="sr-lb-item__visual">
						<span class="sr-lb-badge"><?php echo esc_html( sr_home_text( 'sr_lookbook_badge' ) ); ?></span>
						<?php
						if ( has_post_thumbnail() ) {
							the_post_thumbnail(
								'large',
								array(
									'class'    => 'sr-lb-item__img',
									'sizes'    => '(max-width: 900px) 90vw, 60vw',
									'loading'  => 'lazy',
									'decoding' => 'async',
								)
							);
						} else {
							echo '<span class="sr-ph sr-ph--deep" aria-hidden="true">' . sr_ornament_svg() . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG.
						}
						?>
					</div>

					<div class="sr-lb-item__card">
						<?php if ( $sr_sku ) : ?>
							<span class="sr-lb-item__code"><?php echo esc_html( $sr_sku ); ?></span>
						<?php endif; ?>

						<h2 class="sr-lb-item__title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h2>

						<?php if ( $sr_desc ) : ?>
							<div class="sr-lb-item__desc"><?php echo wp_kses_post( $sr_desc ); ?></div>
						<?php endif; ?>

						<?php if ( $sr_specs ) : ?>
							<dl class="sr-lb-item__specs">
								<?php foreach ( $sr_specs as $sr_spec ) : ?>
									<div class="sr-lb-spec">
										<dt><?php echo esc_html( $sr_spec['label'] ); ?></dt>
										<dd><?php echo esc_html( $sr_spec['value'] ); ?></dd>
									</div>
								<?php endforeach; ?>
							</dl>
						<?php endif; ?>

						<div class="sr-lb-item__foot">
							<?php
							/*
							 * Bridals carry no price — samina-core/bridal-flow returns
							 * "Price on inquiry" here, which is the honest label for a
							 * piece that is quoted after a consultation.
							 */
							?>
							<span class="sr-lb-item__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
							<a class="sr-lb-item__link" href="<?php the_permalink(); ?>">
								<?php echo esc_html( sr_home_text( 'sr_lookbook_cta' ) ); ?>
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
							</a>
						</div>
					</div>
				</article>
				<?php
			endwhile;
			?>
		</div>
		<?php
	else :
		do_action( 'woocommerce_no_products_found' );
		?>
		<section class="sr-section">
			<div class="sr-section__inner">
				<p class="sr-archive__empty"><?php esc_html_e( 'The bridal collection is being prepared. Pieces will appear here as soon as the atelier releases them.', 'samina-rasul' ); ?></p>
			</div>
		</section>
		<?php
	endif;

	sr_shop_cta(
		__( 'Bespoke', 'samina-rasul' ),
		__( 'Envisioning something <em>entirely your own?</em>', 'samina-rasul' ),
		__( 'Start a consultation', 'samina-rasul' )
	);

	do_action( 'woocommerce_after_main_content' );
	?>

</div>

<?php get_footer(); ?>
