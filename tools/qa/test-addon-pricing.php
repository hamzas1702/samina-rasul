<?php
/**
 * Add-on pricing is idempotent.
 *
 * The one check this repo really needs: samina-core/fabric-addons applies its
 * fees from woocommerce_before_calculate_totals, which WooCommerce fires several
 * times in a single request. An implementation that increments the current price
 * compounds - the customer is charged the add-on twice, or three times, or five,
 * depending on how many times totals happened to be recalculated. That bug is
 * invisible in a single-item cart on a page that only calculates once, which is
 * exactly why it survived.
 *
 * Run from site/:
 *   ../.tools/wp eval-file ../tools/qa/test-addon-pricing.php
 *
 * Exits non-zero on failure, so it can be dropped into CI once the pipeline has
 * a WordPress to run against.
 *
 * @package samina-rasul
 */

defined( 'ABSPATH' ) || exit( 1 );

/**
 * Assert two amounts are equal to the cent, and remember the failures.
 *
 * The tally is static rather than a global: `wp eval-file` runs this file
 * inside a function, so a variable declared at the top of it is not in the
 * global scope, and `global $failures` in here would silently address a
 * different, always-empty array - the checks would run, print FAIL, and the
 * script would still exit 0. Which is precisely how a green test suite that
 * tests nothing gets built.
 *
 * @param string|null $label    What is being checked, or null to read the tally.
 * @param float       $expected Expected amount.
 * @param float       $actual   Actual amount.
 * @return array Failures so far.
 */
function sr_qa_assert_money( $label = null, $expected = 0, $actual = 0 ) {
	static $failures = array();

	if ( null === $label ) {
		return $failures;
	}

	if ( abs( (float) $expected - (float) $actual ) < 0.001 ) {
		WP_CLI::log( sprintf( '  ok    %s (%s)', $label, $actual ) );
		return $failures;
	}

	$failures[] = sprintf( '%s: expected %s, got %s', $label, $expected, $actual );
	WP_CLI::log( sprintf( '  FAIL  %s: expected %s, got %s', $label, $expected, $actual ) );

	return $failures;
}

if ( ! function_exists( 'WC' ) ) {
	WP_CLI::error( 'WooCommerce is not active.' );
}

$base_price = 40000.00;
$fee        = 16500.00;

// A throwaway product carrying one fabric upgrade.
$product = new WC_Product_Simple();
$product->set_name( 'SR QA — add-on pricing' );
$product->set_regular_price( $base_price );
$product->set_catalog_visibility( 'hidden' );
$product->set_status( 'publish' );
$product_id = $product->save();

update_post_meta( $product_id, '_sr_fabric_addons', 'Raw Silk 80gm | ' . $fee );

WP_CLI::log( sprintf( 'Product #%d at %s, one add-on at %s', $product_id, $base_price, $fee ) );

// Fake the form submission the add-on markup produces.
$_POST['sr_fabric'] = '0';

wc_load_cart();
WC()->cart->empty_cart();
WC()->cart->add_to_cart( $product_id, 1 );

unset( $_POST['sr_fabric'] );

$items = WC()->cart->get_cart();
$item  = reset( $items );

if ( ! $item || empty( $item['sr_addons'] ) ) {
	WP_CLI::error( 'The add-on selection was not captured into the cart item.' );
}

$expected = $base_price + $fee;

// Once.
WC()->cart->calculate_totals();
sr_qa_assert_money( 'line price after 1 calculation', $expected, $item['data']->get_price() );

/*
 * And four more times. In one real request this happens on add-to-cart, on a
 * coupon, on a quantity change, and twice during checkout - so five is not a
 * contrived number, it is a Tuesday.
 */
for ( $i = 0; $i < 4; $i++ ) {
	WC()->cart->calculate_totals();
}
sr_qa_assert_money( 'line price after 5 calculations', $expected, $item['data']->get_price() );
sr_qa_assert_money( 'cart subtotal after 5 calculations', $expected, WC()->cart->get_subtotal() );

/*
 * The same piece, same add-on, added again: WooCommerce merges it into the
 * existing line at quantity 2, because sr_addons is part of the cart item data
 * the item key is hashed from. Both units carry the fee once each.
 */
$_POST['sr_fabric'] = '0';
WC()->cart->add_to_cart( $product_id, 1 );
unset( $_POST['sr_fabric'] );
WC()->cart->calculate_totals();
sr_qa_assert_money( 'subtotal at quantity 2', $expected * 2, WC()->cart->get_subtotal() );

/*
 * And the same piece without the upgrade is a separate line at the base price -
 * proof the fee rides on the selection rather than on the product.
 */
WC()->cart->add_to_cart( $product_id, 1 );
WC()->cart->calculate_totals();
sr_qa_assert_money( 'subtotal with a plain unit added', ( $expected * 2 ) + $base_price, WC()->cart->get_subtotal() );

// Clean up: leave no QA product or cart behind.
WC()->cart->empty_cart();
wp_delete_post( $product_id, true );

$sr_failures = sr_qa_assert_money();

if ( $sr_failures ) {
	WP_CLI::error( sprintf( "%d check(s) failed:\n - %s", count( $sr_failures ), implode( "\n - ", $sr_failures ) ) );
}

WP_CLI::success( 'Add-on pricing is idempotent across repeated total calculations.' );
