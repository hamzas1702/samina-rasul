<?php
/**
 * On-brand 404.
 */

get_header(); ?>

<main id="main" class="site-main sr-404" style="text-align:center; padding: clamp(4rem,10vw,8rem) var(--sr-gutter);">
	<span class="sr-eyebrow"><?php esc_html_e( 'Page not found', 'samina-rasul' ); ?></span>
	<h1><?php esc_html_e( 'This thread leads nowhere', 'samina-rasul' ); ?></h1>
	<p style="max-width:46ch;margin:1.5rem auto 2.25rem;"><?php esc_html_e( 'The page you were looking for has moved or never existed. The collections, at least, are exactly where they should be.', 'samina-rasul' ); ?></p>
	<p>
		<a class="button" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Browse the shop', 'samina-rasul' ); ?></a>
		<a class="button sr-ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go home', 'samina-rasul' ); ?></a>
	</p>
</main>

<?php get_footer(); ?>
