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
 * Parse "Label | fee | note" lines into
 * [ [ 'label' => ..., 'fee' => float, 'note' => string ], ... ].
 *
 * The note is optional and purely descriptive - it explains what the upgrade
 * buys ("richer fall and finish") under the label on the product page, and is
 * never carried into the cart, where the label and fee are what matter.
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
			'note'  => isset( $parts[2] ) ? $parts[2] : '',
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
		'placeholder' => "Raw Silk 80gm | 16500 | Premium fabric upgrade for a richer fall and finish.\nSheesha Silk | 12000",
		'desc_tip'    => true,
		'description' => __( 'One per line: "Label | fee | note". The note is optional and shows under the label. Customer picks one; fee is added to the item price. Leave empty if none.', 'samina' ),
		'style'       => 'height:6em',
	) );
	woocommerce_wp_textarea_input( array(
		'id'          => '_sr_extra_addons',
		'label'       => __( 'Optional extras', 'samina' ),
		'placeholder' => 'Matching shawl | 12000 | Hand finished to match the dupatta.',
		'desc_tip'    => true,
		'description' => __( 'One per line: "Label | fee | note". The note is optional. Checkboxes — customer can add any.', 'samina' ),
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

	/*
	 * data-fee carries the raw figure so sr-product.js can total the selection
	 * live. The rendered "+PKR 16,500" beside it is formatted for reading and
	 * cannot be parsed back reliably once thousands separators and the currency
	 * code are in it.
	 */
	if ( $fabrics ) {
		echo '<fieldset class="sr-addons sr-addons--fabric">';
		echo '<legend>' . esc_html__( 'Fabric', 'samina' ) . '</legend>';
		echo sr_addon_option_html( 'radio', 'sr_fabric', '', 0.0, __( 'Standard', 'samina' ), __( 'Included in the price shown.', 'samina' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper.
		foreach ( $fabrics as $i => $addon ) {
			echo sr_addon_option_html( 'radio', 'sr_fabric', (string) (int) $i, $addon['fee'], $addon['label'], $addon['note'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper.
		}
		echo '</fieldset>';
	}

	if ( $extras ) {
		echo '<fieldset class="sr-addons sr-addons--extras">';
		echo '<legend>' . esc_html__( 'Complete the look', 'samina' ) . '</legend>';
		foreach ( $extras as $i => $addon ) {
			echo sr_addon_option_html( 'checkbox', 'sr_extras[]', (string) (int) $i, $addon['fee'], $addon['label'], $addon['note'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper.
		}
		echo '</fieldset>';
	}
} );

/**
 * One add-on row: label and note on the left, fee and control hard right.
 *
 * The native input stays in the markup and stays the thing that is submitted -
 * it is only visually replaced by the drawn box, so keyboard focus, the
 * checked state and form submission all behave exactly as stock.
 *
 * @param string $type    'radio' or 'checkbox'.
 * @param string $name    Field name.
 * @param string $value   Field value.
 * @param float  $fee     Additive fee.
 * @param string $label   Option label.
 * @param string $note    Optional descriptive line.
 * @param bool   $checked Whether the option starts selected.
 * @return string Escaped markup.
 */
function sr_addon_option_html( $type, $name, $value, $fee, $label, $note = '', $checked = false ) {
	$html = sprintf(
		'<label class="sr-addon-option"><span class="sr-addon-info"><span class="sr-addon-title">%s</span>',
		esc_html( $label )
	);

	if ( '' !== trim( (string) $note ) ) {
		$html .= sprintf( '<span class="sr-addon-note">%s</span>', esc_html( $note ) );
	}

	$html .= '</span>';

	if ( $fee > 0 ) {
		$html .= sprintf( '<span class="sr-addon-fee">+%s</span>', wp_kses_post( wc_price( $fee ) ) );
	}

	$html .= sprintf(
		'<span class="sr-addon-box"><input type="%1$s" name="%2$s" value="%3$s" data-fee="%4$s"%5$s><span class="sr-addon-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span></span></label>',
		esc_attr( $type ),
		esc_attr( $name ),
		esc_attr( $value ),
		esc_attr( (string) $fee ),
		$checked ? ' checked' : ''
	);

	return $html;
}

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
				$selected[] = array( 'group' => __( 'Add on', 'samina' ) ) + $extras[ $i ];
			}
		}
	}

	if ( $selected ) {
		$cart_item_data['sr_addons'] = $selected;
	}
	return $cart_item_data;
}, 10, 2 );

/**
 * Apply the additive fees on top of the (variation) price.
 *
 * Written to be idempotent, which is the whole difficulty here:
 * woocommerce_before_calculate_totals fires on every calculate_totals(), and
 * WooCommerce calls that several times in a single request - on add-to-cart, on
 * a coupon, on a quantity change, and twice more during checkout. $item['data']
 * is an object living in the cart, so a "current price + fee" increment applied
 * on each pass compounds: a second item added to the cart would re-charge the
 * first item's add-ons, and the order created at checkout would carry the
 * inflated figure.
 *
 * The base is therefore re-read from the product each pass rather than taken
 * from the possibly-already-adjusted cart object, so the result is the same
 * whether this runs once or five times.
 */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	foreach ( $cart->get_cart() as $item ) {
		if ( empty( $item['sr_addons'] ) || ! isset( $item['data'] ) ) {
			continue;
		}
		$fee = (float) array_sum( wp_list_pluck( $item['sr_addons'], 'fee' ) );
		if ( $fee <= 0 ) {
			continue;
		}

		// The variation is what carries the price when there is one.
		$base_id = ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : (int) $item['product_id'];
		$base    = wc_get_product( $base_id );
		if ( ! $base instanceof WC_Product ) {
			continue;
		}

		$item['data']->set_price( (float) $base->get_price() + $fee );
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
