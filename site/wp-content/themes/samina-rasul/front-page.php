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

/**
 * Homepage imagery. Each entry resolves through sr_image_url(), which prefers
 * the theme's assets/images/ and falls back to wp-content/uploads/, so any of
 * these can be replaced by dropping a file in either place. A missing file
 * returns '' and the template renders its CSS placeholder instead.
 */
$sr_img = array(
	'hero'    => sr_image_url( 'hero-section.jpg' ),
	'story'   => sr_image_url( '2026/07/DSC08661.jpg' ),
	'formals' => sr_image_url( '2026/07/DSC08885.jpg' ),
	'bridals' => sr_image_url( '2026/07/DSC08885-1.jpg' ),
);

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
	<?php $sr_hero_image = $sr_img['hero']; ?>
	<section class="sr-hero sr-hero--full<?php echo $sr_hero_image ? ' sr-hero--photo' : ''; ?>">
		<div class="sr-hero__bg sr-ph--deep" data-sr-parallax="6" aria-hidden="true">
			<?php if ( $sr_hero_image ) : ?>
				<img class="sr-hero__img" src="<?php echo esc_url( $sr_hero_image ); ?>" alt="" fetchpriority="high" decoding="async">
			<?php else : ?>
				<?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php endif; ?>
		</div>
		<div class="sr-hero__scrim" aria-hidden="true"></div>
		<div class="sr-hero__content">
			<span class="sr-eyebrow"><?php esc_html_e( 'Hand embellished · Made to order', 'samina-rasul' ); ?></span>
			<h1>
				<span class="sr-line"><span class="sr-line-inner"><?php esc_html_e( 'Couture that', 'samina-rasul' ); ?></span></span>
				<span class="sr-line"><span class="sr-line-inner"><?php esc_html_e( 'remembers the hand', 'samina-rasul' ); ?></span></span>
				<span class="sr-line"><span class="sr-line-inner"><em><?php esc_html_e( 'that made it', 'samina-rasul' ); ?></em></span></span>
			</h1>
			<p><?php esc_html_e( 'Formals and bridals from the house of Samina Rasul, with zardozi, mukesh and resham worked by hand, cut to your measure and finished to order.', 'samina-rasul' ); ?></p>
			<div class="sr-hero-actions">
				<a class="button" href="<?php echo esc_url( sr_term_url( 'formals', 'product_cat' ) ); ?>"><span><?php esc_html_e( 'Shop Formals', 'samina-rasul' ); ?></span></a>
				<a class="button sr-ghost" href="<?php echo esc_url( sr_term_url( 'bridals', 'product_cat' ) ); ?>"><span><?php esc_html_e( 'Explore Bridals', 'samina-rasul' ); ?></span></a>
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

	<!-- 03 · Latest pieces, in a browsable editorial rail. -->
	<section class="sr-section sr-atelier">
		<div class="sr-section__inner">
			<div class="sr-rowhead" data-sr-reveal>
				<div>
					<span class="sr-eyebrow"><?php esc_html_e( 'Just arrived', 'samina-rasul' ); ?></span>
					<h2><span class="sr-rowhead__arrow" aria-hidden="true">→</span> <?php esc_html_e( 'New from the atelier', 'samina-rasul' ); ?></h2>
				</div>
				<div class="sr-atelier__actions">
					<div class="sr-rail-controls" aria-label="<?php esc_attr_e( 'Browse new pieces', 'samina-rasul' ); ?>">
						<button type="button" class="sr-rail-control" data-sr-product-scroll="prev" aria-label="<?php esc_attr_e( 'Show previous pieces', 'samina-rasul' ); ?>">←</button>
						<button type="button" class="sr-rail-control" data-sr-product-scroll="next" aria-label="<?php esc_attr_e( 'Show next pieces', 'samina-rasul' ); ?>">→</button>
					</div>
					<a class="button sr-ghost" href="<?php echo esc_url( sr_shop_url() ); ?>"><span><?php esc_html_e( 'View all pieces', 'samina-rasul' ); ?></span></a>
				</div>
			</div>
			<div class="sr-product-rail" data-sr-product-rail>
				<?php echo do_shortcode( '[products limit="8" columns="4" orderby="date"]' ); ?>
			</div>
		</div>
	</section>

	<!-- 02b · Featured Collections -->
	<section class="sr-section sr-section--cream">
		<div class="sr-section__inner">
			<div class="sr-section__intro" data-sr-reveal>
				<span class="sr-eyebrow"><?php esc_html_e( 'Explore the house', 'samina-rasul' ); ?></span>
				<h2><?php echo wp_kses_post( __( 'Featured <em>Collections</em>', 'samina-rasul' ) ); ?></h2>
				<p><?php esc_html_e( 'Discover our exquisite range of handcrafted couture, where every stitch tells a story of elegance and tradition.', 'samina-rasul' ); ?></p>
			</div>
			<nav class="sr-shop-gateway__grid sr-shop-gateway__grid--3" aria-label="<?php esc_attr_e( 'Shop the Samina Rasul house', 'samina-rasul' ); ?>">
				<a class="sr-route sr-route--media sr-route--formal" href="<?php echo esc_url( sr_term_url( 'formals', 'product_cat' ) ); ?>" data-sr-reveal>
					<div class="sr-route__media">
						<?php echo sr_term_card_media( 'formals', 'product_cat', 'warm' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper. ?>
						<span class="sr-route__art" data-sr-parallax="4"><?php echo $sr_mukesh_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</div>
					<div class="sr-route__copy"><span class="sr-route__index">01 · <?php esc_html_e( 'Ready to order', 'samina-rasul' ); ?></span><h3><?php esc_html_e( 'Formal Collection', 'samina-rasul' ); ?></h3><p><?php esc_html_e( 'Sophisticated designs for special occasions, cut and embellished to order.', 'samina-rasul' ); ?></p><span class="sr-route__cta"><?php esc_html_e( 'View Collection', 'samina-rasul' ); ?> <b aria-hidden="true">→</b></span></div>
				</a>
				<a class="sr-route sr-route--media sr-route--bridal" href="<?php echo esc_url( sr_term_url( 'bridals', 'product_cat' ) ); ?>" data-sr-reveal>
					<div class="sr-route__media">
						<?php echo sr_term_card_media( 'bridals', 'product_cat', 'deep' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper. ?>
						<span class="sr-route__art" data-sr-parallax="4"><?php echo $sr_zardozi_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</div>
					<div class="sr-route__copy"><span class="sr-route__index">02 · <?php esc_html_e( 'By consultation', 'samina-rasul' ); ?></span><h3><?php esc_html_e( 'Bridal Couture', 'samina-rasul' ); ?></h3><p><?php esc_html_e( 'Breathtaking bridal masterpieces blending tradition with contemporary elegance.', 'samina-rasul' ); ?></p><span class="sr-route__cta"><?php esc_html_e( 'View Collection', 'samina-rasul' ); ?> <b aria-hidden="true">→</b></span></div>
				</a>
				<a class="sr-route sr-route--media sr-route--dhanak" href="<?php echo esc_url( sr_term_url( 'dhanak', 'sr_collection' ) ); ?>" data-sr-reveal>
					<div class="sr-route__media">
						<?php echo sr_term_card_media( 'dhanak', 'sr_collection', 'warm' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper. ?>
						<span class="sr-route__art" data-sr-parallax="4"><?php echo $sr_gota_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</div>
					<div class="sr-route__copy"><span class="sr-route__index">03 · <?php esc_html_e( 'Collection', 'samina-rasul' ); ?></span><h3><?php esc_html_e( 'Dhanak', 'samina-rasul' ); ?></h3><p><?php esc_html_e( 'Refined ready pieces with signature Samina Rasul craftsmanship.', 'samina-rasul' ); ?></p><span class="sr-route__cta"><?php esc_html_e( 'View Collection', 'samina-rasul' ); ?> <b aria-hidden="true">→</b></span></div>
				</a>
			</nav>
		</div>
	</section>

	<!-- 02a · Our Story: framed portrait with the display type breaking over it,
	     opposite a hairline-ruled list of the three pillars. -->
	<?php
	$sr_story_image  = $sr_img['story'];
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

				<a class="sr-story__more" href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>" data-sr-reveal>
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
			<?php if ( $sr_img['formals'] ) : ?>
				<img class="sr-split__img" src="<?php echo esc_url( $sr_img['formals'] ); ?>" alt="<?php esc_attr_e( 'A Samina Rasul formal piece', 'samina-rasul' ); ?>" loading="lazy" decoding="async">
			<?php else : ?>
				<div class="sr-ph sr-ph--warm sr-ph--tall" data-sr-parallax="9">
					<?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="sr-ph__caption"><?php esc_html_e( 'Formals lookbook imagery pending', 'samina-rasul' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<div class="sr-split__content" data-sr-reveal>
			<span class="sr-split__motif" data-sr-parallax="4" aria-hidden="true"><?php echo $sr_mukesh_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<span class="sr-eyebrow"><?php esc_html_e( 'The Formals', 'samina-rasul' ); ?></span>
			<h2><?php echo wp_kses_post( __( 'Worn once,<br><em>remembered longer</em>', 'samina-rasul' ) ); ?></h2>
			<p><?php esc_html_e( 'Occasionwear you can order today. Choose your pieces and size, add a fabric upgrade if you wish. Every order is cut and embellished by hand, for you alone.', 'samina-rasul' ); ?></p>
			<a class="button" href="<?php echo esc_url( sr_term_url( 'formals', 'product_cat' ) ); ?>"><span><?php esc_html_e( 'Shop Formals', 'samina-rasul' ); ?></span></a>
		</div>
	</section>

	<!-- 06 · The second path: Bridals -->
	<section class="sr-split sr-split--flip sr-split--burgundy">
		<div class="sr-split__visual">
			<?php if ( $sr_img['bridals'] ) : ?>
				<img class="sr-split__img" src="<?php echo esc_url( $sr_img['bridals'] ); ?>" alt="<?php esc_attr_e( 'A Samina Rasul bridal piece', 'samina-rasul' ); ?>" loading="lazy" decoding="async">
			<?php else : ?>
				<div class="sr-ph sr-ph--deep sr-ph--tall" data-sr-parallax="9">
					<?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="sr-ph__caption"><?php esc_html_e( 'Bridals campaign imagery pending', 'samina-rasul' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<div class="sr-split__content" data-sr-reveal>
			<span class="sr-split__motif" data-sr-parallax="4" aria-hidden="true"><?php echo $sr_zardozi_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<span class="sr-eyebrow"><?php esc_html_e( 'The Bridals', 'samina-rasul' ); ?></span>
			<h2><?php echo wp_kses_post( __( 'Begin the<br><em>conversation</em>', 'samina-rasul' ) ); ?></h2>
			<p><?php esc_html_e( 'A bridal piece is never bought from a shelf, so you will find no price tags here. Tell us about your day, and the atelier will design around you, from fabric and embellishment to silhouette and fit.', 'samina-rasul' ); ?></p>
			<a class="button sr-ghost" href="<?php echo esc_url( sr_term_url( 'bridals', 'product_cat' ) ); ?>"><span><?php esc_html_e( 'Explore Bridals', 'samina-rasul' ); ?></span></a>
		</div>
	</section>

	<!-- 07 · Values: what the house believes -->
	<section class="sr-section sr-values">
		<div class="sr-values__ornament" aria-hidden="true"><?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<span class="sr-values__word sr-values__word--1" data-sr-drift="16" aria-hidden="true"><?php esc_html_e( 'Heritage', 'samina-rasul' ); ?></span>
		<span class="sr-values__word sr-values__word--2" data-sr-drift="-12" aria-hidden="true"><?php esc_html_e( 'Handwork', 'samina-rasul' ); ?></span>
		<span class="sr-values__word sr-values__word--3" data-sr-drift="20" aria-hidden="true"><?php esc_html_e( 'Patience', 'samina-rasul' ); ?></span>
		<div class="sr-values__body" data-sr-reveal>
			<p><?php esc_html_e( 'Nothing here is mass produced. Every order begins as uncut cloth and passes through the hands of embellishers who have practised zardozi, mukesh, resham and gota for generations. That is why a piece takes seven to nine weeks, and why no two are ever quite the same.', 'samina-rasul' ); ?></p>
			<a class="button sr-ghost" href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>"><span><?php esc_html_e( 'About the house', 'samina-rasul' ); ?></span></a>
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
					'copy'    => __( 'Your piece is cut, embellished and finished by hand over seven to nine weeks. A 50% advance confirms the order, and international orders require 100%.', 'samina-rasul' ),
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
