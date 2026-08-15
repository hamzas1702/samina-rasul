<?php
/**
 * About us.
 *
 * The house's own story, told the way the rest of the site tells things: a
 * full-bleed opening, one statement held on its own, then the craft explained
 * against a picture that stays still while the words move past it.
 *
 * A template rather than page content, for the same reason the contact page is
 * one — the layout then ships with the theme and survives a deploy, which a
 * database row does not. Every line of copy is a Customizer field under
 * Site Content → About us page, so the client can rewrite the page without
 * touching the layout.
 *
 * @package samina-rasul
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sr_hero_id   = sr_home_image_id( 'sr_about_hero_image' );
$sr_craft_id  = sr_home_image_id( 'sr_about_craft_image' );
$sr_founder   = sr_home_image_id( 'sr_about_founder_image' );

$sr_craft_blocks = array( 1, 2, 3 );
?>

<div class="sr-about">

	<header class="sr-about-hero<?php echo $sr_hero_id ? ' sr-about-hero--photo' : ''; ?>">
		<div class="sr-about-hero__bg" aria-hidden="true" data-sr-parallax="5">
			<?php
			if ( $sr_hero_id ) {
				echo wp_get_attachment_image(
					$sr_hero_id,
					'full',
					false,
					array(
						// The largest thing on the page and the LCP element, so it
						// is fetched early and never lazily.
						'class'         => 'sr-about-hero__img',
						'alt'           => '',
						'sizes'         => '100vw',
						'fetchpriority' => 'high',
						'decoding'      => 'async',
					)
				);
			} else {
				echo sr_ornament_svg(); // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG.
			}
			?>
		</div>
		<div class="sr-about-hero__scrim" aria-hidden="true"></div>

		<div class="sr-about-hero__content">
			<span class="sr-eyebrow"><?php echo esc_html( sr_home_text( 'sr_about_eyebrow' ) ); ?></span>
			<h1 class="sr-about-hero__title"><?php echo wp_kses_post( sr_home_text( 'sr_about_title' ) ); ?></h1>
		</div>
	</header>

	<section class="sr-about-manifesto">
		<p data-sr-reveal><?php echo wp_kses_post( sr_home_text( 'sr_about_manifesto' ) ); ?></p>
	</section>

	<?php
	/*
	 * The craft. The picture is sticky and the notes scroll past it, so one image
	 * carries three points instead of three images competing for the same idea.
	 * Below 900px the picture unsticks and simply leads — see style.css.
	 */
	?>
	<section class="sr-about-craft">
		<div class="sr-about-craft__visual">
			<?php
			if ( $sr_craft_id ) {
				echo wp_get_attachment_image(
					$sr_craft_id,
					'large',
					false,
					array(
						'class'    => 'sr-about-craft__img',
						'alt'      => '',
						'sizes'    => '(max-width: 900px) 100vw, 50vw',
						'loading'  => 'lazy',
						'decoding' => 'async',
					)
				);
			} else {
				echo '<span class="sr-ph sr-ph--deep" aria-hidden="true">' . sr_ornament_svg() . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG.
			}
			?>
			<div class="sr-about-craft__caption">
				<h2><?php echo wp_kses_post( sr_home_text( 'sr_about_craft_caption' ) ); ?></h2>
			</div>
		</div>

		<div class="sr-about-craft__notes">
			<?php foreach ( $sr_craft_blocks as $sr_n ) : ?>
				<?php
				$sr_title = sr_home_text( 'sr_about_craft_' . $sr_n . '_title' );
				$sr_body  = sr_home_text( 'sr_about_craft_' . $sr_n . '_body' );

				if ( '' === trim( $sr_title ) && '' === trim( $sr_body ) ) {
					continue;
				}
				?>
				<article class="sr-about-note" data-sr-reveal>
					<span class="sr-about-note__num"><?php echo esc_html( sprintf( '%02d', $sr_n ) ); ?></span>
					<h3><?php echo esc_html( $sr_title ); ?></h3>
					<p><?php echo esc_html( $sr_body ); ?></p>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="sr-about-principles">
		<span class="sr-about-principles__art" aria-hidden="true"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>

		<div class="sr-about-principles__inner">
			<h2 data-sr-reveal><?php echo wp_kses_post( sr_home_text( 'sr_about_pillars_heading' ) ); ?></h2>

			<div class="sr-about-pillars">
				<?php foreach ( array( 1, 2, 3 ) as $sr_p ) : ?>
					<?php
					$sr_p_title = sr_home_text( 'sr_about_pillar_' . $sr_p . '_title' );
					$sr_p_body  = sr_home_text( 'sr_about_pillar_' . $sr_p . '_body' );

					if ( '' === trim( $sr_p_title ) && '' === trim( $sr_p_body ) ) {
						continue;
					}
					?>
					<div class="sr-about-pillar" data-sr-reveal>
						<h3><?php echo esc_html( $sr_p_title ); ?></h3>
						<p><?php echo esc_html( $sr_p_body ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<?php $sr_quote = sr_home_text( 'sr_about_founder_quote' ); ?>
	<?php if ( '' !== trim( wp_strip_all_tags( $sr_quote ) ) ) : ?>
		<section class="sr-about-founder">
			<div class="sr-about-founder__visual">
				<?php
				if ( $sr_founder ) {
					echo wp_get_attachment_image(
						$sr_founder,
						'large',
						false,
						array(
							'class'    => 'sr-about-founder__img',
							'alt'      => '',
							'sizes'    => '(max-width: 900px) 100vw, 50vw',
							'loading'  => 'lazy',
							'decoding' => 'async',
						)
					);
				} else {
					echo '<span class="sr-ph sr-ph--warm" aria-hidden="true">' . sr_arch_motif_svg() . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG.
				}
				?>
			</div>

			<figure class="sr-about-founder__note" data-sr-reveal>
				<span class="sr-eyebrow"><?php esc_html_e( 'A word from the founder', 'samina-rasul' ); ?></span>
				<blockquote><?php echo wp_kses_post( $sr_quote ); ?></blockquote>
				<figcaption>
					<span class="sr-about-founder__name"><?php echo esc_html( sr_home_text( 'sr_about_founder_name' ) ); ?></span>
					<span class="sr-about-founder__role"><?php echo esc_html( sr_home_text( 'sr_about_founder_role' ) ); ?></span>
				</figcaption>
			</figure>
		</section>
	<?php endif; ?>

	<?php
	// The same closing invitation every other page ends on.
	sr_shop_cta(
		__( 'Bespoke', 'samina-rasul' ),
		__( 'Step into the <em>atelier.</em>', 'samina-rasul' ),
		__( 'Start a consultation', 'samina-rasul' )
	);
	?>

</div>

<?php get_footer(); ?>
