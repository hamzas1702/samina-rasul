<?php
/**
 * Homepage, ordered to put merchandise in front of narrative:
 * arrival (hero) → new arrivals rail → collections → our story → belief
 * (manifesto) → the two paths (Formals / Bridals) → values → how it works
 * → invitation (newsletter).
 *
 * Shoppable rows sit directly under the hero so a returning visitor reaches
 * product without scrolling past the brand story; the story earns its place
 * lower down, where it supports consideration rather than blocking it.
 *
 * Visual slots fall back to CSS-crafted placeholders (.sr-ph) when the
 * corresponding image is absent, so a missing file never breaks the layout.
 */

get_header();

/*
 * Homepage imagery. The hero, Formals and Bridals images are client-editable
 * and resolve through sr_home_image() at the point of use (Customizer →
 * Homepage Content). The Story portrait is not exposed, so it still resolves
 * through sr_image_url(), which prefers the theme's assets/images/ and falls
 * back to wp-content/uploads/. Either way a missing file yields '' and the
 * template renders its CSS placeholder instead of a broken <img>.
 */

$sr_ornament = sr_ornament_svg();

// A small hand-stitch motif used as a recurring couture detail, not decoration for its own sake.
$sr_stitch_motif = '<svg class="sr-stitch-motif" viewBox="0 0 180 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
	<path class="sr-stitch-motif__thread" d="M4 32 C25 4 43 60 65 32 S105 4 126 32 S156 60 176 32" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-dasharray="5 7"/>
	<path class="sr-stitch-motif__needle" d="M87 13 L96 47" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
	<circle class="sr-stitch-motif__bead" cx="92" cy="32" r="3.4" fill="currentColor"/>
</svg>';

$sr_mukesh_motif = '<svg class="sr-motif sr-motif--mukesh" viewBox="0 0 180 140" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
	<g class="sr-motif__beads" fill="currentColor"><circle cx="32" cy="32" r="5"/><circle cx="76" cy="18" r="3"/><circle cx="122" cy="40" r="6"/><circle cx="156" cy="82" r="3"/><circle cx="104" cy="102" r="4"/><circle cx="48" cy="98" r="6"/><circle cx="26" cy="122" r="2.5"/><circle cx="146" cy="124" r="5"/></g>
	<path class="sr-motif__line" d="M20 112 C42 61 70 124 94 62 S138 10 164 48" stroke="currentColor" stroke-width="1" stroke-dasharray="3 7"/>
</svg>';

$sr_zardozi_motif = '<svg class="sr-motif sr-motif--zardozi" viewBox="0 0 180 140" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
	<g class="sr-motif__petals" stroke="currentColor" stroke-width="1.2"><path d="M90 70 C70 37 78 18 90 8 C102 18 110 37 90 70Z"/><path d="M90 70 C123 50 144 58 160 70 C144 82 123 90 90 70Z"/><path d="M90 70 C110 103 102 122 90 132 C78 122 70 103 90 70Z"/><path d="M90 70 C57 90 36 82 20 70 C36 58 57 50 90 70Z"/></g>
	<circle class="sr-motif__heart" cx="90" cy="70" r="10" fill="currentColor"/><circle cx="90" cy="70" r="3" fill="#4A1F24"/>
</svg>';

$sr_gota_motif = '<svg class="sr-motif sr-motif--gota" viewBox="0 0 180 140" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
	<g class="sr-motif__ribbons" stroke="currentColor" stroke-width="1.1"><path d="M90 10 L154 70 L90 130 L26 70 Z"/><path d="M90 28 L136 70 L90 112 L44 70 Z"/><path d="M26 70 H154 M90 10 V130"/></g>
	<path class="sr-motif__spark" d="M90 48 L96 64 L112 70 L96 76 L90 92 L84 76 L68 70 L84 64 Z" fill="currentColor"/>
</svg>';

/**
 * Process marks - one per timeline step, drawn on the same 180x64 field as the
 * stitch motif and reusing its __thread / __needle / __bead class hooks, so the
 * existing draw-in animation applies to all three without new keyframes.
 * A tailor's rule for the measuring, the running stitch for the making, a tied
 * ribbon for the finished piece.
 */
$sr_process_marks = array(
	'measure' => '<svg class="sr-stitch-motif sr-stitch-motif--measure" viewBox="0 0 180 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path class="sr-stitch-motif__thread" d="M5 32 H175" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-dasharray="5 7"/>
		<g stroke="currentColor" stroke-width="1.1" stroke-linecap="round">
			<path d="M24 22 V32"/><path d="M47 27 V32"/><path d="M70 22 V32"/>
			<path d="M110 32 V42"/><path d="M133 32 V37"/><path d="M156 32 V42"/>
		</g>
		<path class="sr-stitch-motif__needle" d="M90 12 V52" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
		<circle class="sr-stitch-motif__bead" cx="90" cy="32" r="3.6" fill="currentColor"/>
	</svg>',
	'stitch'  => $sr_stitch_motif,
	'ribbon'  => '<svg class="sr-stitch-motif sr-stitch-motif--ribbon" viewBox="0 0 180 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path class="sr-stitch-motif__thread" d="M90 30 C68 10 34 10 30 24 C26 39 60 42 90 30 C120 18 154 21 150 36 C146 50 112 50 90 30Z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-dasharray="5 7"/>
		<path class="sr-stitch-motif__needle" d="M90 30 L74 56 M90 30 L106 56" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
		<circle class="sr-stitch-motif__bead" cx="90" cy="30" r="3.6" fill="currentColor"/>
	</svg>',
);

/**
 * Pillar marks for Our Story - one per heading, drawn on a shared 48-square
 * so the three read as a set: a threaded needle for the making, a zardozi
 * rosette for the cloth, a tailor's tape for the fitting.
 */
$sr_pillar_marks = array(
	'craft'   => '<svg class="sr-pillar-mark" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M9 39 L34 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
		<path d="M31 11 L37 17 L40 8 Z" fill="currentColor"/>
		<ellipse cx="13.5" cy="34.5" rx="2.6" ry="1.5" transform="rotate(-45 13.5 34.5)" stroke="currentColor" stroke-width="1.2"/>
		<path d="M11 37 C4 34 6 26 13 25 S21 17 16 12" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-dasharray="3 4"/>
	</svg>',
	'quality' => '<svg class="sr-pillar-mark" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<g stroke="currentColor" stroke-width="1.3">
			<path d="M24 24 C16 12 18 6 24 2 C30 6 32 12 24 24Z"/>
			<path d="M24 24 C36 16 42 18 46 24 C42 30 36 32 24 24Z"/>
			<path d="M24 24 C32 36 30 42 24 46 C18 42 16 36 24 24Z"/>
			<path d="M24 24 C12 32 6 30 2 24 C6 18 12 16 24 24Z"/>
		</g>
		<circle cx="24" cy="24" r="4" fill="currentColor"/>
	</svg>',
	'touch'   => '<svg class="sr-pillar-mark" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M6 17 H42 A4 4 0 0 1 42 25 H12 A4 4 0 0 0 12 33 H44" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
		<g stroke="currentColor" stroke-width="1.1" stroke-linecap="round">
			<path d="M13 17 V21"/><path d="M20 17 V21"/><path d="M27 17 V21"/><path d="M34 17 V21"/>
			<path d="M19 29 V33"/><path d="M26 29 V33"/><path d="M33 29 V33"/>
		</g>
	</svg>',
);

?>

<main id="main" class="site-main sr-home">

	<!-- 01 · Arrival: full-bleed image hero, text centred over a scrim. -->
	<?php
	// Decorative: the section sits behind a scrim and is aria-hidden, so alt
	// stays empty. Eager + high priority because this is the LCP element.
	$sr_hero_image = sr_home_image(
		'sr_home_hero_image',
		array(
			'class'         => 'sr-hero__img',
			'alt'           => '',
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'decoding'      => 'async',
		)
	);
	?>
	<section class="sr-hero sr-hero--full<?php echo $sr_hero_image ? ' sr-hero--photo' : ''; ?>">
		<div class="sr-hero__bg sr-ph--deep" data-sr-parallax="6" aria-hidden="true">
			<?php if ( $sr_hero_image ) : ?>
				<?php echo $sr_hero_image; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in sr_home_image(). ?>
			<?php else : ?>
				<?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php endif; ?>
		</div>
		<div class="sr-hero__scrim" aria-hidden="true"></div>
		<div class="sr-hero__content">
			<span class="sr-eyebrow"><?php echo esc_html( sr_home_text( 'sr_home_hero_eyebrow' ) ); ?></span>
			<h1>
				<span class="sr-line"><span class="sr-line-inner"><?php echo esc_html( sr_home_text( 'sr_home_hero_line_1' ) ); ?></span></span>
				<span class="sr-line"><span class="sr-line-inner"><?php echo esc_html( sr_home_text( 'sr_home_hero_line_2' ) ); ?></span></span>
				<span class="sr-line"><span class="sr-line-inner"><em><?php echo esc_html( sr_home_text( 'sr_home_hero_line_3' ) ); ?></em></span></span>
			</h1>
			<p><?php echo esc_html( sr_home_text( 'sr_home_hero_body' ) ); ?></p>
			<div class="sr-hero-actions">
				<a class="button" href="<?php echo esc_url( sr_term_url( 'formals', 'product_cat' ) ); ?>"><span><?php echo esc_html( sr_home_text( 'sr_home_hero_cta_primary' ) ); ?></span></a>
				<a class="button sr-ghost" href="<?php echo esc_url( sr_term_url( 'bridals', 'product_cat' ) ); ?>"><span><?php echo esc_html( sr_home_text( 'sr_home_hero_cta_secondary' ) ); ?></span></a>
			</div>
			<?php if ( ! $sr_hero_image ) : ?>
				<span class="sr-hero__caption"><?php esc_html_e( 'Campaign photography in production', 'samina-rasul' ); ?></span>
			<?php endif; ?>
		</div>
	</section>

	<div class="sr-marquee" aria-hidden="true">
		<div class="sr-marquee__track">
			<?php for ( $i = 0; $i < 2; $i++ ) : ?>
			<span class="sr-marquee__group">
				<span>Zardozi</span><i>✦</i><span>Mukesh</span><i>✦</i><span>Resham</span><i>✦</i><span>Gota</span><i>✦</i><span><em>Made to order</em></span><i>✦</i><span>Dhanak</span><i>✦</i><span>Ujala</span><i>✦</i>
			</span>
			<?php endfor; ?>
		</div>
	</div>

	<!-- 03 · Latest pieces, in a browsable editorial rail. Scoped to the Ujala
	     collection: "new arrivals" on a made-to-order catalogue means the
	     collection currently being cut, not whatever was published last. -->
	<?php
	sr_product_rail(
		array(
			'key'        => 'new',
			'class'      => 'sr-atelier',
			'eyebrow'    => sr_home_text( 'sr_home_atelier_eyebrow' ),
			'heading'    => sr_home_text( 'sr_home_atelier_heading' ),
			'collection' => 'ujala',
			'atts'       => array( 'limit' => 8, 'columns' => 4, 'orderby' => 'date' ),
			'cta_url'    => sr_term_url( 'ujala', 'sr_collection' ),
			'cta_label'  => __( 'View the Ujala collection', 'samina-rasul' ),
		)
	);
	?>

	<!-- 02b · Featured Collections -->
	<section class="sr-section sr-section--cream">
		<div class="sr-section__inner">
			<div class="sr-section__intro" data-sr-reveal>
				<span class="sr-eyebrow"><?php echo esc_html( sr_home_text( 'sr_home_collections_eyebrow' ) ); ?></span>
				<h2><?php echo wp_kses_post( sr_home_text( 'sr_home_collections_heading' ) ); ?></h2>
				<p><?php echo esc_html( sr_home_text( 'sr_home_collections_body' ) ); ?></p>
			</div>
			<nav class="sr-shop-gateway__grid sr-shop-gateway__grid--3" aria-label="<?php esc_attr_e( 'Shop the Samina Rasul house', 'samina-rasul' ); ?>">
				<a class="sr-route sr-route--media sr-route--formal" href="<?php echo esc_url( sr_term_url( 'formals', 'product_cat' ) ); ?>" data-sr-reveal>
					<div class="sr-route__media">
						<?php echo sr_term_card_media( 'formals', 'product_cat', 'warm', sr_home_image_id( 'sr_home_tile_1_image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper. ?>
						<span class="sr-route__art" data-sr-parallax="4"><?php echo $sr_mukesh_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</div>
					<div class="sr-route__copy"><span class="sr-route__index">01 · <?php echo esc_html( sr_home_text( 'sr_home_tile_1_badge' ) ); ?></span><h3><?php echo esc_html( sr_home_text( 'sr_home_tile_1_title' ) ); ?></h3><p><?php echo esc_html( sr_home_text( 'sr_home_tile_1_body' ) ); ?></p><span class="sr-route__cta"><?php echo esc_html( sr_home_text( 'sr_home_tile_1_cta' ) ); ?> <b aria-hidden="true">→</b></span></div>
				</a>
				<a class="sr-route sr-route--media sr-route--bridal" href="<?php echo esc_url( sr_term_url( 'bridals', 'product_cat' ) ); ?>" data-sr-reveal>
					<div class="sr-route__media">
						<?php echo sr_term_card_media( 'bridals', 'product_cat', 'deep', sr_home_image_id( 'sr_home_tile_2_image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper. ?>
						<span class="sr-route__art" data-sr-parallax="4"><?php echo $sr_zardozi_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</div>
					<div class="sr-route__copy"><span class="sr-route__index">02 · <?php echo esc_html( sr_home_text( 'sr_home_tile_2_badge' ) ); ?></span><h3><?php echo esc_html( sr_home_text( 'sr_home_tile_2_title' ) ); ?></h3><p><?php echo esc_html( sr_home_text( 'sr_home_tile_2_body' ) ); ?></p><span class="sr-route__cta"><?php echo esc_html( sr_home_text( 'sr_home_tile_2_cta' ) ); ?> <b aria-hidden="true">→</b></span></div>
				</a>
				<a class="sr-route sr-route--media sr-route--dhanak" href="<?php echo esc_url( sr_term_url( 'dhanak', 'sr_collection' ) ); ?>" data-sr-reveal>
					<div class="sr-route__media">
						<?php echo sr_term_card_media( 'dhanak', 'sr_collection', 'warm', sr_home_image_id( 'sr_home_tile_3_image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper. ?>
						<span class="sr-route__art" data-sr-parallax="4"><?php echo $sr_gota_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</div>
					<div class="sr-route__copy"><span class="sr-route__index">03 · <?php echo esc_html( sr_home_text( 'sr_home_tile_3_badge' ) ); ?></span><h3><?php echo esc_html( sr_home_text( 'sr_home_tile_3_title' ) ); ?></h3><p><?php echo esc_html( sr_home_text( 'sr_home_tile_3_body' ) ); ?></p><span class="sr-route__cta"><?php echo esc_html( sr_home_text( 'sr_home_tile_3_cta' ) ); ?> <b aria-hidden="true">→</b></span></div>
				</a>
			</nav>
		</div>
	</section>

	<!-- 03b · The Dhanak edit: the everyday-luxury path, shoppable in place. -->
	<?php
	sr_product_rail(
		array(
			'key'        => 'dhanak',
			'class'      => 'sr-rail--formals',
			// The house's main revenue collection gets the full width of the
			// content column, laid out rather than scrolled.
			'layout'     => 'grid',
			'eyebrow'    => __( 'Ready to order', 'samina-rasul' ),
			'heading'    => __( 'The Dhanak edit', 'samina-rasul' ),
			'collection' => 'dhanak',
			// Six, not eight: the cards run three across now, so eight left a
			// short row of two under two full ones.
			'atts'       => array( 'limit' => 6, 'columns' => 3, 'orderby' => 'date' ),
			'cta_url'    => sr_term_url( 'dhanak', 'sr_collection' ),
			'cta_label'  => __( 'Shop all Dhanak', 'samina-rasul' ),
		)
	);
	?>

	<!-- 02a · Our Story: framed portrait with the display type breaking over it,
	     opposite a hairline-ruled list of the three pillars. -->
	<?php
	$sr_story_image  = sr_image_url( '2026/07/DSC08661.jpg' );
	$sr_story_alt    = __( 'A Samina Rasul piece, hand embellished in the atelier', 'samina-rasul' );
	$sr_story_pillars = array(
		array(
			'mark'  => 'craft',
			'title' => __( 'Passionate <em>Craftsmanship</em>', 'samina-rasul' ),
			'copy'  => __( 'Every piece created with love, and a stubborn dedication to perfection.', 'samina-rasul' ),
		),
		array(
			'mark'  => 'quality',
			'title' => __( 'Premium <em>Quality</em>', 'samina-rasul' ),
			'copy'  => __( 'Only the finest fabrics, and techniques practised for generations.', 'samina-rasul' ),
		),
		array(
			'mark'  => 'touch',
			'title' => __( 'Personal <em>Touch</em>', 'samina-rasul' ),
			'copy'  => __( 'Custom consultations that bring your own unique vision to life.', 'samina-rasul' ),
		),
	);
	?>
	<section class="sr-section sr-story">
		<div class="sr-section__inner sr-story__layout">
			<div class="sr-story__visual" data-sr-reveal>
				<figure class="sr-story__frame" data-sr-parallax="5">
					<?php if ( $sr_story_image ) : ?>
						<img src="<?php echo esc_url( $sr_story_image ); ?>" alt="<?php echo esc_attr( $sr_story_alt ); ?>" loading="lazy" decoding="async">
					<?php else : ?>
						<span class="sr-ph sr-ph--warm" aria-hidden="true"><?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<?php endif; ?>
				</figure>
				<h2 class="sr-story__title"><?php echo wp_kses_post( __( 'Our <em>Story</em>', 'samina-rasul' ) ); ?></h2>
			</div>

			<div class="sr-story__aside">
				<header class="sr-story__intro" data-sr-reveal>
					<span class="sr-eyebrow sr-eyebrow--ruled"><?php esc_html_e( 'The house of Samina Rasul', 'samina-rasul' ); ?></span>
					<p class="sr-story__lede"><?php echo wp_kses_post( __( 'Born from a passion for the timeless art of Eastern couture, and a vision to create pieces that celebrate femininity and <em>elegance</em>.', 'samina-rasul' ) ); ?></p>
				</header>

				<ul class="sr-story__pillars" data-sr-reveal>
					<?php foreach ( $sr_story_pillars as $sr_i => $sr_pillar ) : ?>
						<li class="sr-pillar">
							<span class="sr-pillar__index" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $sr_i + 1 ) ); ?></span>
							<div class="sr-pillar__body">
								<h3><?php echo wp_kses_post( $sr_pillar['title'] ); ?></h3>
								<div class="sr-pillar__reveal"><p><?php echo esc_html( $sr_pillar['copy'] ); ?></p></div>
							</div>
							<span class="sr-pillar__mark"><?php echo $sr_pillar_marks[ $sr_pillar['mark'] ]; // phpcs:ignore WordPress.Security.EscapeOutput -- static, hand-written inline SVG. ?></span>
						</li>
					<?php endforeach; ?>
				</ul>

				<a class="sr-story__more" href="<?php echo esc_url( sr_page_url( 'about-us' ) ); ?>" data-sr-reveal>
					<span><?php esc_html_e( 'Learn more about us', 'samina-rasul' ); ?></span>
					<b aria-hidden="true">→</b>
				</a>
			</div>
		</div>
	</section>

	<!-- 04 · Belief -->
	<section class="sr-section sr-manifesto" data-sr-lines>
		<div class="sr-section__inner">
			<p class="sr-manifesto__text">
				<span class="sr-line"><span class="sr-line-inner"><?php esc_html_e( 'A dress can be made in a day.', 'samina-rasul' ); ?></span></span>
				<span class="sr-line"><span class="sr-line-inner"><em><?php esc_html_e( 'Ours are not.', 'samina-rasul' ); ?></em></span></span>
				<span class="sr-line"><span class="sr-line-inner"><?php esc_html_e( 'Seven weeks. A thousand stitches.', 'samina-rasul' ); ?></span></span>
				<span class="sr-line"><span class="sr-line-inner"><?php esc_html_e( 'One pair of hands.', 'samina-rasul' ); ?></span></span>
			</p>
		</div>
	</section>

	<!-- 05 · The first path: Formals -->
	<section class="sr-split">
		<div class="sr-split__visual">
			<?php
			$sr_formals_image = sr_home_image(
				'sr_home_formals_image',
				array(
					'class'    => 'sr-split__img',
					'alt'      => sr_home_text( 'sr_home_formals_image_alt' ),
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);
			?>
			<?php if ( $sr_formals_image ) : ?>
				<?php echo $sr_formals_image; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in sr_home_image(). ?>
			<?php else : ?>
				<div class="sr-ph sr-ph--warm sr-ph--tall" data-sr-parallax="9">
					<?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="sr-ph__caption"><?php esc_html_e( 'Formals lookbook imagery pending', 'samina-rasul' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<div class="sr-split__content" data-sr-reveal>
			<span class="sr-split__motif" data-sr-parallax="4" aria-hidden="true"><?php echo $sr_gota_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<span class="sr-eyebrow"><?php esc_html_e( 'The Formals', 'samina-rasul' ); ?></span>
			<h2><?php echo wp_kses_post( sr_home_text( 'sr_home_formals_heading' ) ); ?></h2>
			<p><?php echo esc_html( sr_home_text( 'sr_home_formals_body' ) ); ?></p>
			<a class="button" href="<?php echo esc_url( sr_term_url( 'formals', 'product_cat' ) ); ?>"><span><?php esc_html_e( 'Shop Formals', 'samina-rasul' ); ?></span></a>
		</div>
	</section>

	<!-- 06 · The second path: Bridals -->
	<section class="sr-split sr-split--flip sr-split--burgundy">
		<div class="sr-split__visual">
			<?php
			$sr_bridals_image = sr_home_image(
				'sr_home_bridals_image',
				array(
					'class'    => 'sr-split__img',
					'alt'      => sr_home_text( 'sr_home_bridals_image_alt' ),
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);
			?>
			<?php if ( $sr_bridals_image ) : ?>
				<?php echo $sr_bridals_image; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in sr_home_image(). ?>
			<?php else : ?>
				<div class="sr-ph sr-ph--deep sr-ph--tall" data-sr-parallax="9">
					<?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="sr-ph__caption"><?php esc_html_e( 'Bridals campaign imagery pending', 'samina-rasul' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<div class="sr-split__content" data-sr-reveal>
			<span class="sr-split__motif" data-sr-parallax="4" aria-hidden="true"><?php echo $sr_zardozi_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<span class="sr-eyebrow"><?php echo esc_html( sr_home_text( 'sr_home_bridals_eyebrow' ) ); ?></span>
			<h2><?php echo wp_kses_post( sr_home_text( 'sr_home_bridals_heading' ) ); ?></h2>
			<p><?php echo esc_html( sr_home_text( 'sr_home_bridals_body' ) ); ?></p>
			<?php // Bridals carry no price and no cart, so booking is the primary action here and browsing the collection is secondary. ?>
			<div class="sr-split__actions">
				<a class="button" href="<?php echo esc_url( sr_page_url( 'consultations' ) ); ?>"><span><?php esc_html_e( 'Book a consultation', 'samina-rasul' ); ?></span></a>
				<a class="button sr-ghost" href="<?php echo esc_url( sr_term_url( 'bridals', 'product_cat' ) ); ?>"><span><?php esc_html_e( 'Explore Bridals', 'samina-rasul' ); ?></span></a>
			</div>
		</div>
	</section>

	<!-- 06b · The Bridal edit: bridal pieces are quoted, not priced, so this row
	     opens the conversation rather than the cart. -->
	<?php
	sr_product_rail(
		array(
			'key'       => 'bridals',
			'class'     => 'sr-rail--bridals',
			'layout'    => 'grid',
			'eyebrow'   => __( 'By consultation', 'samina-rasul' ),
			'heading'   => __( 'The Bridal edit', 'samina-rasul' ),
			'atts'      => array( 'limit' => 3, 'columns' => 3, 'category' => 'bridals', 'orderby' => 'date' ),
			'cta_url'   => sr_term_url( 'bridals', 'product_cat' ),
			'cta_label' => __( 'See all Bridals', 'samina-rasul' ),
		)
	);
	?>

	<!-- 02c · Assurances: the four objections that stop a first order, answered
	     before the visitor has to go looking for them. -->
	<?php
	$sr_assurances = array(
		array(
			'title' => __( 'Made to order', 'samina-rasul' ),
			'copy'  => __( 'Cut for you after you order, never pulled off a shelf.', 'samina-rasul' ),
		),
		array(
			'title' => __( 'Hand embellished', 'samina-rasul' ),
			'copy'  => __( 'Zardozi, mukesh, resham and gota, worked by hand.', 'samina-rasul' ),
		),
		array(
			'title' => __( '50% to begin', 'samina-rasul' ),
			'copy'  => __( 'Half the price confirms your order, the rest on dispatch.', 'samina-rasul' ),
		),
		array(
			'title' => __( 'Shipped worldwide', 'samina-rasul' ),
			'copy'  => __( 'Delivered anywhere, tracked from the atelier to your door.', 'samina-rasul' ),
		),
	);
	?>
	<section class="sr-assurances" aria-label="<?php esc_attr_e( 'How we work', 'samina-rasul' ); ?>" data-sr-reveal>
		<ul class="sr-assurances__list">
			<?php foreach ( $sr_assurances as $sr_assurance ) : ?>
				<li class="sr-assurance">
					<span class="sr-assurance__mark" aria-hidden="true"><?php echo sr_star_svg( 'sr-star sr-star--tick' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG. ?></span>
					<strong><?php echo esc_html( $sr_assurance['title'] ); ?></strong>
					<span><?php echo esc_html( $sr_assurance['copy'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<!-- 07 · Values: what the house believes -->
	<section class="sr-section sr-values">
		<div class="sr-values__ornament" aria-hidden="true"><?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<span class="sr-values__word sr-values__word--1" data-sr-drift="16" aria-hidden="true"><?php esc_html_e( 'Heritage', 'samina-rasul' ); ?></span>
		<span class="sr-values__word sr-values__word--2" data-sr-drift="-12" aria-hidden="true"><?php esc_html_e( 'Handwork', 'samina-rasul' ); ?></span>
		<span class="sr-values__word sr-values__word--3" data-sr-drift="20" aria-hidden="true"><?php esc_html_e( 'Patience', 'samina-rasul' ); ?></span>
		<div class="sr-values__body" data-sr-reveal>
			<p><?php esc_html_e( 'Nothing here is mass produced. Every order begins as uncut cloth and passes through the hands of embellishers who have practised zardozi, mukesh, resham and gota for generations. That is why a formal piece takes seven to eight weeks and a bridal ten to twelve, and why no two are ever quite the same.', 'samina-rasul' ); ?></p>
			<a class="button sr-ghost" href="<?php echo esc_url( sr_page_url( 'about-us' ) ); ?>"><span><?php esc_html_e( 'About the house', 'samina-rasul' ); ?></span></a>
		</div>
	</section>

	<!-- 08 · How it works -->
	<section class="sr-section sr-process">
		<div class="sr-section__inner">
			<div class="sr-section__intro" data-sr-reveal>
				<span class="sr-eyebrow"><?php esc_html_e( 'How it works', 'samina-rasul' ); ?></span>
				<h2><?php esc_html_e( 'We take time, here is where it goes', 'samina-rasul' ); ?></h2>
			</div>
			<?php
			$sr_process_steps = array(
				array(
					'mark'    => 'measure',
					'eyebrow' => __( 'First measure', 'samina-rasul' ),
					'title'   => __( 'The conversation', 'samina-rasul' ),
					'copy'    => __( 'Order Formals directly with your size, or choose “Customized” and share your measurements. For Bridals, everything starts with a WhatsApp consultation.', 'samina-rasul' ),
				),
				array(
					'mark'    => 'stitch',
					'eyebrow' => __( 'In the atelier', 'samina-rasul' ),
					'title'   => __( 'The making', 'samina-rasul' ),
					'copy'    => __( 'Your piece is cut, embellished and finished by hand — seven to eight weeks for a formal, ten to twelve for a bridal. A 50% advance confirms the order, and international orders require 100%.', 'samina-rasul' ),
				),
				array(
					'mark'    => 'ribbon',
					'eyebrow' => __( 'Final detail', 'samina-rasul' ),
					'title'   => __( 'The arrival', 'samina-rasul' ),
					'copy'    => __( 'Made once for you, which is why customized pieces cannot be exchanged. Delivered to your door, ready for the occasion it was imagined for.', 'samina-rasul' ),
				),
			);
			?>
			<ol class="sr-timeline">
				<?php foreach ( $sr_process_steps as $sr_i => $sr_step ) : ?>
					<li class="sr-timeline__step">
						<span class="sr-timeline__marker" aria-hidden="true"></span>
						<div class="sr-timeline__card">
							<div class="sr-timeline__heading">
								<span class="sr-process__num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $sr_i + 1 ) ); ?></span>
								<span class="sr-timeline__eyebrow"><?php echo esc_html( $sr_step['eyebrow'] ); ?></span>
							</div>
							<div class="sr-timeline__motif"><?php echo $sr_process_marks[ $sr_step['mark'] ]; // phpcs:ignore WordPress.Security.EscapeOutput -- static, hand-written inline SVG. ?></div>
							<h3><?php echo esc_html( $sr_step['title'] ); ?></h3>
							<p><?php echo esc_html( $sr_step['copy'] ); ?></p>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</section>

	<!-- 08b · Most loved: the last shoppable row before the page closes, so the
	     scroll ends on merchandise rather than on a form. -->
	<?php
	sr_product_rail(
		array(
			'key'       => 'loved',
			'class'     => 'sr-rail--loved',
			'eyebrow'   => __( 'Chosen most often', 'samina-rasul' ),
			'heading'   => __( 'Most loved this season', 'samina-rasul' ),
			'atts'      => array( 'limit' => 8, 'columns' => 4, 'best_selling' => 'true' ),
			'cta_url'   => sr_shop_url(),
			'cta_label' => __( 'Shop everything', 'samina-rasul' ),
		)
	);
	?>

	<!-- 08c · The undecided visitor: one direct line rather than a dead end. -->
	<?php
	// A configured WhatsApp number is the fastest route to a person; without
	// one, the contact page is the honest fallback rather than a broken wa.me link.
	$sr_wa_number = function_exists( 'sr_whatsapp_number' ) ? sr_whatsapp_number() : '';
	$sr_talk_url  = $sr_wa_number
		? 'https://wa.me/' . $sr_wa_number . '?text=' . rawurlencode( __( 'Hello Samina Rasul, I would like some help choosing a piece.', 'samina-rasul' ) )
		: sr_page_url( 'contact' );
	?>
	<section class="sr-consult">
		<div class="sr-consult__inner" data-sr-reveal>
			<span class="sr-consult__ornament" aria-hidden="true"><?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<span class="sr-eyebrow"><?php esc_html_e( 'Not sure where to begin', 'samina-rasul' ); ?></span>
			<h2><?php echo wp_kses_post( __( 'Tell us the occasion.<br>We will suggest the <em>piece</em>.', 'samina-rasul' ) ); ?></h2>
			<p><?php esc_html_e( 'Sizing, fabrics, delivery dates, or a bridal commission from scratch — talk to the atelier before you order.', 'samina-rasul' ); ?></p>
			<div class="sr-consult__actions">
				<a class="button" href="<?php echo esc_url( $sr_talk_url ); ?>"<?php echo $sr_wa_number ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><span><?php esc_html_e( 'Talk to the atelier', 'samina-rasul' ); ?></span></a>
				<a class="button sr-ghost" href="<?php echo esc_url( sr_shop_url() ); ?>"><span><?php esc_html_e( 'Browse the collection', 'samina-rasul' ); ?></span></a>
			</div>
		</div>
	</section>

	<!-- 09 · Invitation -->
	<section class="sr-section sr-section--cream sr-newsletter">
		<div class="sr-section__inner" data-sr-reveal>
			<span class="sr-eyebrow"><?php esc_html_e( 'The list', 'samina-rasul' ); ?></span>
			<h2><?php esc_html_e( 'First to see each drop', 'samina-rasul' ); ?></h2>
			<p><?php esc_html_e( 'One email when a new collection opens for order. Nothing else.', 'samina-rasul' ); ?></p>
			<?php if ( isset( $_GET['sr_sub'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag, no action taken. ?>
				<p class="sr-newsletter__status" role="status">
					<?php
					echo 'ok' === $_GET['sr_sub'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						? esc_html__( 'Thank you. You are on the list.', 'samina-rasul' )
						: esc_html__( 'That email address did not look right. Please try again.', 'samina-rasul' );
					?>
				</p>
			<?php endif; ?>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" aria-label="<?php esc_attr_e( 'Newsletter signup', 'samina-rasul' ); ?>">
				<input type="hidden" name="action" value="sr_newsletter">
				<?php wp_nonce_field( 'sr_newsletter', 'sr_newsletter_nonce' ); ?>
				<div class="sr-field">
					<input type="email" name="sr_newsletter_email" id="sr_newsletter_email" placeholder=" " required autocomplete="email">
					<label for="sr_newsletter_email"><?php esc_html_e( 'Your email address', 'samina-rasul' ); ?></label>
				</div>
				<?php // Honeypot: a real visitor never fills this; bots usually do. ?>
				<div class="sr-hp" aria-hidden="true">
					<label for="sr_website"><?php esc_html_e( 'Leave this field empty', 'samina-rasul' ); ?></label>
					<input type="text" name="sr_website" id="sr_website" tabindex="-1" autocomplete="off">
				</div>
				<p class="sr-newsletter__consent">
					<label>
						<input type="checkbox" name="sr_consent" value="1" required>
						<?php esc_html_e( 'I agree to receive occasional emails about new collections.', 'samina-rasul' ); ?>
					</label>
				</p>
				<button type="submit" class="button"><span><?php esc_html_e( 'Subscribe', 'samina-rasul' ); ?></span></button>
			</form>
		</div>
	</section>

</main>

<?php get_footer(); ?>
