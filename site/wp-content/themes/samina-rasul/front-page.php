<?php
/**
 * Homepage - a narrative arc, not a grid of sections:
 * arrival (split hero) → belief (manifesto) → the two paths (Formals / Bridals
 * splits) → proof (atelier pieces) → values (drifting words) → how it works
 * (process) → invitation (newsletter).
 *
 * Visual slots use CSS-crafted placeholders (.sr-ph) until client photography
 * arrives - swap each .sr-ph for an <img>/<picture> then.
 */

get_header();

$sr_ornament = '<svg class="sr-ornament" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
	<circle cx="100" cy="100" r="96" stroke="currentColor" stroke-width="0.6"/>
	<circle cx="100" cy="100" r="78" stroke="currentColor" stroke-width="0.4"/>
	<circle cx="100" cy="100" r="34" stroke="currentColor" stroke-width="0.5"/>
	<g stroke="currentColor" stroke-width="0.5">'
	. implode( '', array_map( function ( $i ) {
		return '<g transform="rotate(' . ( $i * 30 ) . ' 100 100)"><path d="M100 4 C 108 28, 108 44, 100 62 C 92 44, 92 28, 100 4 Z"/><circle cx="100" cy="70" r="1.6" fill="currentColor" stroke="none"/></g>';
	}, range( 0, 11 ) ) )
	. '</g></svg>';

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

$sr_resham_motif = '<svg class="sr-motif sr-motif--resham" viewBox="0 0 180 140" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
	<path class="sr-motif__thread sr-motif__thread--one" d="M8 104 C48 6 90 144 172 32" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-dasharray="6 8"/>
	<path class="sr-motif__thread sr-motif__thread--two" d="M10 118 C58 16 108 140 170 46" stroke="currentColor" stroke-width=".8" stroke-linecap="round" stroke-dasharray="2 6"/>
	<circle class="sr-motif__knot" cx="91" cy="74" r="6" fill="currentColor"/>
</svg>';
?>

<main id="main" class="site-main sr-home">

	<!-- 01 · Arrival -->
	<section class="sr-hero sr-hero--split">
		<div class="sr-hero__content">
			<span class="sr-eyebrow"><?php esc_html_e( 'Hand-embellished · Made to order', 'samina-rasul' ); ?></span>
			<h1>
				<span class="sr-line"><span class="sr-line-inner"><?php esc_html_e( 'Couture that', 'samina-rasul' ); ?></span></span>
				<span class="sr-line"><span class="sr-line-inner"><?php esc_html_e( 'remembers the hand', 'samina-rasul' ); ?></span></span>
				<span class="sr-line"><span class="sr-line-inner"><em><?php esc_html_e( 'that made it', 'samina-rasul' ); ?></em></span></span>
			</h1>
			<p><?php esc_html_e( 'Formals and bridals from the house of Samina Rasul - zardozi, mukesh and resham worked by hand, cut to your measure, finished to order.', 'samina-rasul' ); ?></p>
			<div class="sr-hero-actions">
				<a class="button" href="<?php echo esc_url( get_term_link( 'formals', 'product_cat' ) ); ?>"><span><?php esc_html_e( 'Shop Formals', 'samina-rasul' ); ?></span></a>
				<a class="button sr-ghost" href="<?php echo esc_url( get_term_link( 'bridals', 'product_cat' ) ); ?>"><span><?php esc_html_e( 'Explore Bridals', 'samina-rasul' ); ?></span></a>
			</div>
		</div>
		<div class="sr-hero__visual">
			<div class="sr-ph sr-ph--warm" data-sr-parallax="7">
				<?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="sr-ph__caption"><?php esc_html_e( 'Campaign photography - in production', 'samina-rasul' ); ?></span>
			</div>
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

	<!-- 02 · A clear shopping index: categories first, collections second. -->
	<section class="sr-section sr-shop-gateway">
		<div class="sr-section__inner sr-shop-gateway__layout">
			<header class="sr-shop-gateway__intro" data-sr-reveal>
				<div class="sr-shop-gateway__seal"><?php echo $sr_gota_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<span class="sr-eyebrow"><?php esc_html_e( 'Explore the house', 'samina-rasul' ); ?></span>
				<h2><?php echo wp_kses_post( __( 'Choose your <em>way in</em>', 'samina-rasul' ) ); ?></h2>
				<p><?php esc_html_e( 'Begin with the way you want to be dressed. Then discover the collection that feels like your own.', 'samina-rasul' ); ?></p>
			</header>
			<nav class="sr-shop-gateway__grid" aria-label="<?php esc_attr_e( 'Shop the Samina Rasul house', 'samina-rasul' ); ?>">
				<a class="sr-route sr-route--formal" href="<?php echo esc_url( get_term_link( 'formals', 'product_cat' ) ); ?>" data-sr-reveal>
					<div class="sr-route__art"><?php echo $sr_mukesh_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<div class="sr-route__copy"><span class="sr-route__index">01 · <?php esc_html_e( 'Ready to order', 'samina-rasul' ); ?></span><h3><?php esc_html_e( 'Formals', 'samina-rasul' ); ?></h3><p><?php esc_html_e( 'Made-to-order occasion pieces, selected and ordered online.', 'samina-rasul' ); ?></p><span class="sr-route__cta"><?php esc_html_e( 'Shop Formals', 'samina-rasul' ); ?> <b aria-hidden="true">→</b></span></div>
				</a>
				<a class="sr-route sr-route--bridal" href="<?php echo esc_url( get_term_link( 'bridals', 'product_cat' ) ); ?>" data-sr-reveal>
					<div class="sr-route__art"><?php echo $sr_zardozi_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<div class="sr-route__copy"><span class="sr-route__index">02 · <?php esc_html_e( 'By consultation', 'samina-rasul' ); ?></span><h3><?php esc_html_e( 'Bridal commissions', 'samina-rasul' ); ?></h3><p><?php esc_html_e( 'A considered conversation for a piece made only around you.', 'samina-rasul' ); ?></p><span class="sr-route__cta"><?php esc_html_e( 'Explore Bridals', 'samina-rasul' ); ?> <b aria-hidden="true">→</b></span></div>
				</a>
				<a class="sr-route sr-route--collection sr-route--dhanak" href="<?php echo esc_url( get_term_link( 'dhanak', 'sr_collection' ) ); ?>" data-sr-reveal>
					<div class="sr-route__art"><?php echo $sr_gota_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<div class="sr-route__copy"><span class="sr-route__index">03 · <?php esc_html_e( 'Collection', 'samina-rasul' ); ?></span><h3><?php esc_html_e( 'Dhanak', 'samina-rasul' ); ?></h3><span class="sr-route__cta"><?php esc_html_e( 'View collection', 'samina-rasul' ); ?> <b aria-hidden="true">→</b></span></div>
				</a>
				<a class="sr-route sr-route--collection sr-route--ujala" href="<?php echo esc_url( get_term_link( 'ujala', 'sr_collection' ) ); ?>" data-sr-reveal>
					<div class="sr-route__art"><?php echo $sr_resham_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<div class="sr-route__copy"><span class="sr-route__index">04 · <?php esc_html_e( 'Collection', 'samina-rasul' ); ?></span><h3><?php esc_html_e( 'Ujala', 'samina-rasul' ); ?></h3><span class="sr-route__cta"><?php esc_html_e( 'View collection', 'samina-rasul' ); ?> <b aria-hidden="true">→</b></span></div>
				</a>
			</nav>
		</div>
	</section>

	<!-- 03 · Latest pieces stay close to the top, in a browsable editorial rail. -->
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
					<a class="button sr-ghost" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>"><span><?php esc_html_e( 'View all pieces', 'samina-rasul' ); ?></span></a>
				</div>
			</div>
			<div class="sr-product-rail" data-sr-product-rail>
				<?php echo do_shortcode( '[products limit="8" columns="4" orderby="date"]' ); ?>
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
			<div class="sr-ph sr-ph--warm sr-ph--tall" data-sr-parallax="9">
				<?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="sr-ph__caption"><?php esc_html_e( 'Formals - lookbook imagery pending', 'samina-rasul' ); ?></span>
			</div>
		</div>
		<div class="sr-split__content" data-sr-reveal>
			<span class="sr-eyebrow"><?php esc_html_e( 'The Formals', 'samina-rasul' ); ?></span>
			<h2><?php echo wp_kses_post( __( 'Worn once,<br><em>remembered longer</em>', 'samina-rasul' ) ); ?></h2>
			<p><?php esc_html_e( 'Occasionwear you can order today. Choose your pieces and size, add a fabric upgrade if you wish - every order is still cut and embellished by hand, for you alone.', 'samina-rasul' ); ?></p>
			<a class="button" href="<?php echo esc_url( get_term_link( 'formals', 'product_cat' ) ); ?>"><span><?php esc_html_e( 'Shop Formals', 'samina-rasul' ); ?></span></a>
		</div>
	</section>

	<!-- 06 · The second path: Bridals -->
	<section class="sr-split sr-split--flip sr-split--burgundy">
		<div class="sr-split__visual">
			<div class="sr-ph sr-ph--deep sr-ph--tall" data-sr-parallax="9">
				<?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="sr-ph__caption"><?php esc_html_e( 'Bridals - campaign imagery pending', 'samina-rasul' ); ?></span>
			</div>
		</div>
		<div class="sr-split__content" data-sr-reveal>
			<span class="sr-eyebrow"><?php esc_html_e( 'The Bridals', 'samina-rasul' ); ?></span>
			<h2><?php echo wp_kses_post( __( 'Begin the<br><em>conversation</em>', 'samina-rasul' ) ); ?></h2>
			<p><?php esc_html_e( 'A bridal piece is never bought from a shelf, so you will find no price tags here. Tell us about your day, and the atelier will design around you - fabric, embellishment, silhouette and fit.', 'samina-rasul' ); ?></p>
			<a class="button sr-ghost" href="<?php echo esc_url( get_term_link( 'bridals', 'product_cat' ) ); ?>"><span><?php esc_html_e( 'Explore Bridals', 'samina-rasul' ); ?></span></a>
		</div>
	</section>

	<!-- 07 · Values: what the house believes -->
	<section class="sr-section sr-values">
		<div class="sr-values__ornament" aria-hidden="true"><?php echo $sr_ornament; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<span class="sr-values__word sr-values__word--1" data-sr-drift="16" aria-hidden="true"><?php esc_html_e( 'Heritage', 'samina-rasul' ); ?></span>
		<span class="sr-values__word sr-values__word--2" data-sr-drift="-12" aria-hidden="true"><?php esc_html_e( 'Handwork', 'samina-rasul' ); ?></span>
		<span class="sr-values__word sr-values__word--3" data-sr-drift="20" aria-hidden="true"><?php esc_html_e( 'Patience', 'samina-rasul' ); ?></span>
		<div class="sr-values__body" data-sr-reveal>
			<p><?php esc_html_e( 'Nothing here is mass-produced. Every order begins as uncut cloth and passes through the hands of embellishers who have practised zardozi, mukesh, resham and gota for generations. That is why a piece takes seven to nine weeks - and why no two are ever quite the same.', 'samina-rasul' ); ?></p>
			<a class="button sr-ghost" href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>"><span><?php esc_html_e( 'About the house', 'samina-rasul' ); ?></span></a>
		</div>
	</section>

	<!-- 08 · How it works -->
	<section class="sr-section sr-process">
		<div class="sr-section__inner">
			<div class="sr-section__intro" data-sr-reveal>
				<span class="sr-eyebrow"><?php esc_html_e( 'How it works', 'samina-rasul' ); ?></span>
				<h2><?php esc_html_e( 'We take time - here is where it goes', 'samina-rasul' ); ?></h2>
			</div>
			<ol class="sr-timeline">
				<li class="sr-timeline__step">
					<span class="sr-timeline__marker" aria-hidden="true"></span>
					<div class="sr-timeline__card">
						<div class="sr-timeline__heading">
							<span class="sr-process__num" aria-hidden="true">01</span>
							<span class="sr-timeline__eyebrow"><?php esc_html_e( 'First measure', 'samina-rasul' ); ?></span>
						</div>
						<div class="sr-timeline__motif"><?php echo $sr_stitch_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
						<h3><?php esc_html_e( 'The conversation', 'samina-rasul' ); ?></h3>
						<p><?php esc_html_e( 'Order Formals directly with your size - or choose “Customized” and share your measurements. For Bridals, everything starts with a WhatsApp consultation.', 'samina-rasul' ); ?></p>
					</div>
				</li>
				<li class="sr-timeline__step">
					<span class="sr-timeline__marker" aria-hidden="true"></span>
					<div class="sr-timeline__card">
						<div class="sr-timeline__heading">
							<span class="sr-process__num" aria-hidden="true">02</span>
							<span class="sr-timeline__eyebrow"><?php esc_html_e( 'In the atelier', 'samina-rasul' ); ?></span>
						</div>
						<div class="sr-timeline__motif"><?php echo $sr_stitch_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
						<h3><?php esc_html_e( 'The making', 'samina-rasul' ); ?></h3>
						<p><?php esc_html_e( 'Your piece is cut, embellished and finished by hand over seven to nine weeks. A 50% advance confirms the order - 100% for international orders.', 'samina-rasul' ); ?></p>
					</div>
				</li>
				<li class="sr-timeline__step">
					<span class="sr-timeline__marker" aria-hidden="true"></span>
					<div class="sr-timeline__card">
						<div class="sr-timeline__heading">
							<span class="sr-process__num" aria-hidden="true">03</span>
							<span class="sr-timeline__eyebrow"><?php esc_html_e( 'Final detail', 'samina-rasul' ); ?></span>
						</div>
						<div class="sr-timeline__motif"><?php echo $sr_stitch_motif; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
						<h3><?php esc_html_e( 'The arrival', 'samina-rasul' ); ?></h3>
						<p><?php esc_html_e( 'Made once, for you - which is why customized pieces cannot be exchanged. Delivered to your door, ready for the occasion it was imagined for.', 'samina-rasul' ); ?></p>
					</div>
				</li>
			</ol>
		</div>
	</section>

	<!-- 09 · Invitation -->
	<section class="sr-section sr-section--cream sr-newsletter">
		<div class="sr-section__inner" data-sr-reveal>
			<span class="sr-eyebrow"><?php esc_html_e( 'The list', 'samina-rasul' ); ?></span>
			<h2><?php esc_html_e( 'First to see each drop', 'samina-rasul' ); ?></h2>
			<p><?php esc_html_e( 'One email when a new collection opens for order. Nothing else.', 'samina-rasul' ); ?></p>
			<form action="#" method="post" aria-label="<?php esc_attr_e( 'Newsletter signup', 'samina-rasul' ); ?>">
				<div class="sr-field">
					<input type="email" name="sr_newsletter_email" id="sr_newsletter_email" placeholder=" " required>
					<label for="sr_newsletter_email"><?php esc_html_e( 'Your email address', 'samina-rasul' ); ?></label>
				</div>
				<button type="submit" class="button"><span><?php esc_html_e( 'Subscribe', 'samina-rasul' ); ?></span></button>
			</form>
		</div>
	</section>

</main>

<?php get_footer(); ?>
