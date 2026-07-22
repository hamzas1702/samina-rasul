<?php
/**
 * Samina Rasul child theme.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
	$dir     = get_stylesheet_directory();
	$assets  = get_stylesheet_directory_uri() . '/assets/js';
	$css_ver = (string) filemtime( $dir . '/style.css' );
	$js_ver  = (string) filemtime( $dir . '/assets/js/sr-ui.js' );
	wp_enqueue_style( 'sr-fonts', get_stylesheet_directory_uri() . '/fonts/fonts-local.css', array(), '1' );
	wp_enqueue_style( 'storefront-parent', get_template_directory_uri() . '/style.css', array( 'sr-fonts' ), $css_ver );
	wp_enqueue_style( 'samina-rasul', get_stylesheet_uri(), array( 'storefront-parent' ), $css_ver );

	wp_enqueue_script( 'sr-gsap', $assets . '/gsap.min.js', array(), '3.12.5', true );
	wp_enqueue_script( 'sr-scrolltrigger', $assets . '/ScrollTrigger.min.js', array( 'sr-gsap' ), '3.12.5', true );
	wp_enqueue_script( 'sr-ui', $assets . '/sr-ui.js', array( 'sr-gsap', 'sr-scrolltrigger' ), $js_ver, true );
}, 20 );

/**
 * Pre-paint flags: preloader (once per session) and curtain-arrival state.
 * Runs inline in <head> so the overlay states exist before first paint.
 */
add_action( 'wp_head', function () {
	?>
	<script>
	(function () {
		try {
			var d = document.documentElement;
			var rm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			if (rm) return;
			d.classList.add('sr-anim'); /* JS + motion OK: CSS may hold pre-reveal states */
			if (!sessionStorage.getItem('srSeen') && <?php echo is_front_page() ? 'true' : 'false'; ?>) { d.classList.add('sr-preload'); }
			if (sessionStorage.getItem('srCurtain')) { d.classList.add('sr-curtain-in'); }
		} catch (e) {}
	})();
	</script>
	<?php
}, 1 );

/**
 * Overlay chrome: preloader, page-transition curtain, custom cursor.
 */
add_action( 'wp_footer', function () {
	?>
	<div class="sr-preloader" aria-hidden="true">
		<span class="sr-preloader__word"><?php
			foreach ( str_split( 'SAMINA RASUL' ) as $ch ) {
				echo '<span>' . ( ' ' === $ch ? '&nbsp;' : esc_html( $ch ) ) . '</span>';
			}
		?></span>
	</div>
	<div class="sr-curtain" aria-hidden="true"></div>
	<div class="sr-cursor-ring" aria-hidden="true"></div>
	<div class="sr-cursor-dot" aria-hidden="true"></div>
	<?php
}, 5 );

/**
 * Shop-loop cards: wrap the thumbnail so hover zoom can be clipped.
 */
remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
add_action( 'woocommerce_before_shop_loop_item_title', function () {
	echo '<div class="sr-card-media">' . woocommerce_get_product_thumbnail() . '<span class="sr-card-veil" aria-hidden="true"></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}, 10 );

/**
 * Storefront layout adjustments.
 */
add_action( 'init', function () {
	// No sidebar anywhere - editorial full-width layout.
	add_filter( 'storefront_page_layout', function () {
		return 'full-width';
	} );
} );

add_action( 'wp', function () {
	// Homepage: no page title, custom sections do the work.
	if ( is_front_page() ) {
		remove_action( 'storefront_page', 'storefront_page_header', 10 );
	}
} );

add_filter( 'body_class', function ( $classes ) {
	$classes[] = 'sr-theme';
	return $classes;
} );

/**
 * Size chart - button on product pages opening a native <dialog>.
 * Measurements are placeholders until the client supplies the real chart.
 */
add_action( 'woocommerce_single_product_summary', function () {
	?>
	<p><button type="button" class="sr-size-chart-open sr-linklike" aria-haspopup="dialog"><?php esc_html_e( 'Size chart', 'samina-rasul' ); ?></button></p>
	<dialog class="sr-size-chart" aria-label="<?php esc_attr_e( 'Size chart', 'samina-rasul' ); ?>">
		<h3><?php esc_html_e( 'Size chart', 'samina-rasul' ); ?></h3>
		<table>
			<thead>
				<tr><th><?php esc_html_e( 'Size', 'samina-rasul' ); ?></th><th><?php esc_html_e( 'Bust (in)', 'samina-rasul' ); ?></th><th><?php esc_html_e( 'Waist (in)', 'samina-rasul' ); ?></th><th><?php esc_html_e( 'Hip (in)', 'samina-rasul' ); ?></th></tr>
			</thead>
			<tbody>
				<tr><td>XS</td><td>-</td><td>-</td><td>-</td></tr>
				<tr><td>S</td><td>-</td><td>-</td><td>-</td></tr>
				<tr><td>M</td><td>-</td><td>-</td><td>-</td></tr>
				<tr><td>ML</td><td>-</td><td>-</td><td>-</td></tr>
				<tr><td>L</td><td>-</td><td>-</td><td>-</td></tr>
				<tr><td>XL</td><td>-</td><td>-</td><td>-</td></tr>
			</tbody>
		</table>
		<p class="sr-size-chart__note"><?php esc_html_e( 'Between sizes, or after a different fit? Choose “Customized” and we will cut to your measurements.', 'samina-rasul' ); ?></p>
		<button type="button" class="button sr-size-chart-close"><?php esc_html_e( 'Close', 'samina-rasul' ); ?></button>
	</dialog>
	<script>
	(function () {
		document.addEventListener('click', function (e) {
			if (e.target.closest('.sr-size-chart-open')) { document.querySelector('.sr-size-chart').showModal(); }
			if (e.target.closest('.sr-size-chart-close')) { document.querySelector('.sr-size-chart').close(); }
		});
	})();
	</script>
	<?php
}, 24 );

/**
 * Footer: oversized outlined wordmark above the link columns.
 */
add_action( 'storefront_footer', function () {
	echo '<div class="sr-footer-wordmark" aria-hidden="true"><span>Samina&nbsp;Rasul</span></div>';
}, 4 );

/**
 * Footer columns per the brief: About the Brand / Customer Service / Information.
 */
add_action( 'storefront_footer', function () {
	$columns = array(
		__( 'About the Brand', 'samina-rasul' )   => array(
			__( 'About Us', 'samina-rasul' ) => '/about-us/',
			__( 'FAQs', 'samina-rasul' )     => '/faqs/',
		),
		__( 'Customer Service', 'samina-rasul' ) => array(
			__( 'Contact Us', 'samina-rasul' )           => '/contact/',
			__( 'Payments & Shipping', 'samina-rasul' )  => '/shipping-policy/',
		),
		__( 'Information', 'samina-rasul' )      => array(
			__( 'My Account', 'samina-rasul' )       => '/my-account/',
			__( 'Shipping Policy', 'samina-rasul' )  => '/shipping-policy/',
			__( 'Refund Policy', 'samina-rasul' )    => '/refund-policy/',
			__( 'Terms of Service', 'samina-rasul' ) => '/terms-of-service/',
			__( 'Privacy Policy', 'samina-rasul' )   => '/privacy-policy/',
		),
	);
	echo '<div class="sr-footer-cols">';
	foreach ( $columns as $heading => $links ) {
		echo '<div class="sr-footer-col"><h4>' . esc_html( $heading ) . '</h4><ul>';
		foreach ( $links as $label => $url ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( $url ) ), esc_html( $label ) );
		}
		echo '</ul></div>';
	}
	echo '</div>';
}, 5 );

/**
 * Footer: replace Storefront credit with brand note.
 */
add_filter( 'storefront_credit_link', '__return_false' );
add_filter( 'storefront_copyright_text', function () {
	return sprintf(
		/* translators: %s: year */
		esc_html__( '© %s Samina Rasul, every piece made to order and hand finished in Pakistan.', 'samina-rasul' ),
		gmdate( 'Y' )
	);
} );
