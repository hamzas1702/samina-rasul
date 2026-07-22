<?php
/**
 * Per-product custom fields, editable in the WooCommerce product data panel:
 * - _sr_delivery_time (e.g. "7–8 weeks") — shown on product pages as part of the
 *   made-to-order brand story, varies by collection.
 */

defined( 'ABSPATH' ) || exit;

// Admin: field in Product data → General.
add_action( 'woocommerce_product_options_general_product_data', function () {
	woocommerce_wp_text_input( array(
		'id'          => '_sr_delivery_time',
		'label'       => __( 'Delivery time', 'samina' ),
		'placeholder' => __( 'e.g. 7–8 weeks', 'samina' ),
		'desc_tip'    => true,
		'description' => __( 'Made-to-order lead time shown on the product page (Dhanak ≈ 7–8 weeks, Ujala ≈ 8–9 weeks).', 'samina' ),
	) );
} );

add_action( 'woocommerce_process_product_meta', function ( $post_id ) {
	if ( isset( $_POST['_sr_delivery_time'] ) ) {
		update_post_meta( $post_id, '_sr_delivery_time', sanitize_text_field( wp_unslash( $_POST['_sr_delivery_time'] ) ) );
	}
} );

/**
 * Helper: delivery time for a product.
 */
function sr_get_delivery_time( $product_id ) {
	return get_post_meta( $product_id, '_sr_delivery_time', true );
}

// Frontend: delivery estimate under the price / before the form.
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! $product ) {
		return;
	}
	$time = sr_get_delivery_time( $product->get_id() );
	if ( $time ) {
		printf(
			'<p class="sr-delivery-note">%s</p>',
			esc_html( sprintf(
				/* translators: %s: lead time, e.g. "7–8 weeks" */
				__( 'Each piece is hand finished to order, ready in %s.', 'samina' ),
				$time
			) )
		);
	}
}, 25 );
