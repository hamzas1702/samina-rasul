<?php
/**
 * Export the content pages to catalog/pages.json.
 *
 * Run locally: wp eval-file tools/seed/export-pages.php
 *
 * Pages are database rows, so they do not travel with a code deploy - which is
 * why the policy pages existed locally and nowhere else. This writes them to a
 * file that is tracked in git, and seed-6-pages.php puts them back on the
 * server. Exporting rather than re-running seed-3-pages.php on purpose: the
 * point is that live matches *this* install, including anything edited here
 * since it was first seeded.
 *
 * WooCommerce's own pages are skipped. Cart, checkout and account are built
 * from blocks that reference IDs local to each install, and overwriting them on
 * the live store would break checkout to no purpose.
 *
 * @package samina-rasul
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$sr_out = dirname( __DIR__, 2 ) . '/catalog/pages.json';

// The Woo-owned pages, by the options that name them.
$sr_skip = array();
foreach ( array( 'woocommerce_shop_page_id', 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id', 'woocommerce_terms_page_id' ) as $sr_option ) {
	$sr_id = (int) get_option( $sr_option, 0 );
	if ( $sr_id > 0 ) {
		$sr_skip[ $sr_id ] = true;
	}
}

$sr_pages = array();

foreach ( get_posts(
	array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
	)
) as $sr_page ) {
	if ( isset( $sr_skip[ $sr_page->ID ] ) ) {
		WP_CLI::log( 'skip (WooCommerce): /' . $sr_page->post_name . '/' );
		continue;
	}

	$sr_pages[] = array(
		'slug'     => $sr_page->post_name,
		'title'    => $sr_page->post_title,
		'content'  => $sr_page->post_content,
		'excerpt'  => $sr_page->post_excerpt,
		'menu'     => (int) $sr_page->menu_order,
		'template' => (string) get_page_template_slug( $sr_page->ID ),
	);

	WP_CLI::log( 'exported: /' . $sr_page->post_name . '/' );
}

if ( ! $sr_pages ) {
	WP_CLI::error( 'No pages to export.' );
}

// JSON_PRETTY_PRINT and unescaped slashes/unicode so the file reads as a plain
// diff in git rather than a wall of \u escapes.
file_put_contents(
	$sr_out,
	wp_json_encode( $sr_pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

WP_CLI::success( count( $sr_pages ) . ' pages written to catalog/pages.json' );
