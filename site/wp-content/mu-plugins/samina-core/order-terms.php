<?php
/**
 * Surface the key order terms where customers decide — not buried in policy pages:
 *  - minimum 50% advance (100% for international orders)
 *  - made to order; no return/exchange on customized pieces
 * Shown under the product CTA and as a checkout notice.
 */

defined( 'ABSPATH' ) || exit;

function sr_order_terms_html() {
	return '<ul class="sr-order-terms">'
		. '<li>' . esc_html__( 'Orders are confirmed with a 50% advance, and 100% for international orders.', 'samina' ) . '</li>'
		. '<li>' . esc_html__( 'Every piece is made to order; customized pieces cannot be returned or exchanged.', 'samina' ) . '</li>'
		. '</ul>';
}

/**
 * Under the add-to-cart / inquire area on product pages.
 *
 * A named function, not a closure, so the active theme can fold these terms
 * into a note of its own design with remove_action() instead of printing them
 * twice. The copy stays here, in the store's business logic, either way.
 */
function sr_render_order_terms() {
	echo wp_kses_post( sr_order_terms_html() );
}
add_action( 'woocommerce_single_product_summary', 'sr_render_order_terms', 35 );

// On the checkout page, before the order review.
add_action( 'woocommerce_before_checkout_form', function () {
	wc_print_notice(
		esc_html__( 'A minimum 50% advance confirms your order (100% for international orders). Pieces made to order ship in the delivery window shown on each product.', 'samina' ),
		'notice'
	);
} );
