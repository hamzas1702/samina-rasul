<?php
/**
 * Layer-2 pricing: additive add-ons on top of the selected variation price.
 *  - Fabric upgrades (choose one, radio): e.g. Raw Silk 80gm +16,500
 *  - Optional extras (checkboxes): e.g. Matching shawl +12,000
 *
 * Stored as product meta, one option per line in the admin panel: "Label | fee".
 * Meta keys: _sr_fabric_addons, _sr_extra_addons.
 *
 * NOTE: stands in for the paid WooCommerce Product Add-ons extension; the data
 * model (label + additive fee) migrates 1:1 if the official extension is bought.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parse "Label | fee" lines into [ [ 'label' => ..., 'fee' => float ], ... ].
 */
function sr_parse_addon_lines( $raw ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line ) );
		$out[] = array(
			'label' => $parts[0],
			'fee'   => isset( $parts[1] ) ? (float) str_replace( ',', '', $parts[1] ) : 0.0,
		);
	}
	return $out;
}

function sr_get_addons( $product_id, $type = 'fabric' ) {
	$key = 'fabric' === $type ? '_sr_fabric_addons' : '_sr_extra_addons';
	return sr_parse_addon_lines( get_post_meta( $product_id, $key, true ) );
}

/* ---------- Admin ---------- */

add_action( 'woocommerce_product_options_general_product_data', function () {
	echo '<div class="options_group">';
	woocommerce_wp_textarea_input( array(
		'id'          => '_sr_fabric_addons',
		'label'       => __( 'Fabric upgrades', 'samina' ),
		'placeholder' => "Raw Silk 80gm | 16500\nSheesha Silk | 12000",
		'desc_tip'    => true,
		'description' => __( 'One per line: "Label | fee". Customer picks one; fee is added to the item price. Leave empty if none.', 'samina' ),
		'style'       => 'height:6em',
	) );
	woocommerce_wp_textarea_input( array(
		'id'          => '_sr_extra_addons',
		'label'       => __( 'Optional extras', 'samina' ),
		'placeholder' => 'Matching shawl | 12000',
		'desc_tip'    => true,
		'description' => __( 'One per line: "Label | fee". Checkboxes — customer can add any.', 'samina' ),
		'style'       => 'height:4em',
	) );
	echo '</div>';
} );

add_action( 'woocommerce_process_product_meta', function ( $post_id ) {
	foreach ( array( '_sr_fabric_addons', '_sr_extra_addons' ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
} );

/* ---------- Frontend form ---------- */

add_action( 'woocommerce_before_add_to_cart_button', function () {
	global $product;
	if ( ! $product ) {
		return;
	}
	$fabrics = sr_get_addons( $product->get_id(), 'fabric' );
	$extras  = sr_get_addons( $product->get_id(), 'extra' );

	if ( $fabrics ) {
		echo '<fieldset class="sr-addons sr-addons--fabric">';
		echo '<legend>' . esc_html__( 'Fabric', 'samina' ) . '</legend>';
		printf(
			'<label class="sr-addon-option"><input type="radio" name="sr_fabric" value="" checked> <span>%s</span></label>',
			esc_html__( 'Standard (included)', 'samina' )
		);
		foreach ( $fabrics as $i => $addon ) {
			printf(
				'<label class="sr-addon-option"><input type="radio" name="sr_fabric" value="%d"> <span>%s</span> <span class="sr-addon-fee">+%s</span></label>',
				(int) $i,
				esc_html( $addon['label'] ),
				wp_kses_post( wc_price( $addon['fee'] ) )
			);
		}
		echo '</fieldset>';
	}

	if ( $extras ) {
		echo '<fieldset class="sr-addons sr-addons--extras">';
		echo '<legend>' . esc_html__( 'Complete the look', 'samina' ) . '</legend>';
		foreach ( $extras as $i => $addon ) {
			printf(
				'<label class="sr-addon-option"><input type="checkbox" name="sr_extras[]" value="%d"> <span>%s</span> <span class="sr-addon-fee">+%s</span></label>',
				(int) $i,
				esc_html( $addon['label'] ),
				wp_kses_post( wc_price( $addon['fee'] ) )
			);
		}
		echo '</fieldset>';
	}
} );

/* ---------- Cart integration ---------- */

// Capture selections when the item is added to cart.
add_filter( 'woocommerce_add_cart_item_data', function ( $cart_item_data, $product_id ) {
	$selected = array();

	if ( isset( $_POST['sr_fabric'] ) && '' !== $_POST['sr_fabric'] ) {
		$fabrics = sr_get_addons( $product_id, 'fabric' );
		$i       = (int) $_POST['sr_fabric'];
		if ( isset( $fabrics[ $i ] ) ) {
			$selected[] = array( 'group' => __( 'Fabric', 'samina' ) ) + $fabrics[ $i ];
		}
	}

	if ( ! empty( $_POST['sr_extras'] ) && is_array( $_POST['sr_extras'] ) ) {
		$extras = sr_get_addons( $product_id, 'extra' );
		foreach ( array_map( 'intval', $_POST['sr_extras'] ) as $i ) {
			if ( isset( $extras[ $i ] ) ) {
				$selected[] = array( 'group' => __( 'Add-on', 'samina' ) ) + $extras[ $i ];
			}
		}
	}

	if ( $selected ) {
		$cart_item_data['sr_addons'] = $selected;
	}
	return $cart_item_data;
}, 10, 2 );

// Apply the additive fees on top of the (variation) price.
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	foreach ( $cart->get_cart() as $item ) {
		if ( empty( $item['sr_addons'] ) ) {
			continue;
		}
		$fee = array_sum( wp_list_pluck( $item['sr_addons'], 'fee' ) );
		if ( $fee > 0 ) {
			$item['data']->set_price( (float) $item['data']->get_price() + $fee );
		}
	}
}, 20 );

// Show selections on cart/checkout lines.
add_filter( 'woocommerce_get_item_data', function ( $item_data, $cart_item ) {
	if ( ! empty( $cart_item['sr_addons'] ) ) {
		foreach ( $cart_item['sr_addons'] as $addon ) {
			$item_data[] = array(
				'key'   => $addon['group'],
				'value' => sprintf( '%s (+%s)', $addon['label'], wp_strip_all_tags( wc_price( $addon['fee'] ) ) ),
			);
		}
	}
	return $item_data;
}, 10, 2 );

// Persist to the order line item.
add_action( 'woocommerce_checkout_create_order_line_item', function ( $line_item, $cart_item_key, $values ) {
	if ( ! empty( $values['sr_addons'] ) ) {
		foreach ( $values['sr_addons'] as $addon ) {
			$line_item->add_meta_data(
				$addon['group'],
				sprintf( '%s (+%s)', $addon['label'], wp_strip_all_tags( wc_price( $addon['fee'] ) ) )
			);
		}
	}
}, 10, 3 );
