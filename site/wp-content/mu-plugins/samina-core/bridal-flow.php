<?php
/**
 * Bridals purchase flow: no price, no cart — WhatsApp inquiry only.
 * Formals keep the standard WooCommerce flow untouched.
 *
 * Pattern (per Sania Maskatiya reference): wa.me link with pre-filled
 * "Hello, I am interested in <product> <url>" message.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is this product in the Bridals category?
 */
function sr_is_bridal( $product ) {
	$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
	if ( ! $product ) {
		return false;
	}
	$id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
	return has_term( 'bridals', 'product_cat', $id );
}

/**
 * Sitewide WhatsApp number (digits only, international format, no "+").
 * Set under WooCommerce → Settings → General. Placeholder until the client
 * confirms the business number (open question #9 in the brief).
 */
function sr_whatsapp_number() {
	return preg_replace( '/\D/', '', (string) get_option( 'sr_whatsapp_number', '' ) );
}

function sr_whatsapp_url( $product ) {
	$number = sr_whatsapp_number();
	$text   = sprintf(
		/* translators: 1: product name, 2: product URL */
		__( 'Hello, I am interested in %1$s %2$s', 'samina' ),
		$product->get_name(),
		$product->get_permalink()
	);
	return 'https://wa.me/' . $number . '?text=' . rawurlencode( $text );
}

function sr_inquire_button_html( $product, $classes = 'sr-whatsapp-inquire button alt' ) {
	return sprintf(
		'<a class="%s" href="%s" target="_blank" rel="noopener">%s</a>',
		esc_attr( $classes ),
		esc_url( sr_whatsapp_url( $product ) ),
		esc_html__( 'Inquire on WhatsApp', 'samina' )
	);
}

/* ---------- Settings field (WooCommerce → Settings → General) ---------- */

add_filter( 'woocommerce_general_settings', function ( $settings ) {
	$settings[] = array(
		'title'    => __( 'WhatsApp business number', 'samina' ),
		'desc'     => __( 'International format, digits only (e.g. 9230xxxxxxxx). Used for Bridals inquiries and the floating chat button.', 'samina' ),
		'id'       => 'sr_whatsapp_number',
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	);
	return $settings;
} );

/* ---------- Hide price ---------- */

add_filter( 'woocommerce_get_price_html', function ( $html, $product ) {
	if ( is_admin() ) {
		return $html;
	}
	if ( sr_is_bridal( $product ) ) {
		return '<span class="sr-price-on-inquiry">' . esc_html__( 'Price on inquiry', 'samina' ) . '</span>';
	}
	return $html;
}, 100, 2 );

/* ---------- Block purchase ---------- */

add_filter( 'woocommerce_is_purchasable', function ( $purchasable, $product ) {
	return sr_is_bridal( $product ) ? false : $purchasable;
}, 100, 2 );

add_filter( 'woocommerce_variation_is_purchasable', function ( $purchasable, $variation ) {
	return sr_is_bridal( $variation ) ? false : $purchasable;
}, 100, 2 );

/* ---------- Single product page: swap cart UI for the inquire CTA ---------- */

add_action( 'wp', function () {
	if ( ! is_product() ) {
		return;
	}
	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product || ! sr_is_bridal( $product ) ) {
		return;
	}
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	add_action( 'woocommerce_single_product_summary', function () use ( $product ) {
		// Sizes shown as reference only — not selectable.
		$sizes = array();
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute ) {
				continue;
			}
			if ( false !== stripos( wc_attribute_label( $attribute->get_name() ), 'size' ) ) {
				$sizes = $attribute->is_taxonomy()
					? wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) )
					: $attribute->get_options();
			}
		}
		if ( $sizes ) {
			echo '<p class="sr-bridal-sizes"><span class="sr-bridal-sizes__label">' . esc_html__( 'Available sizes', 'samina' ) . ':</span> ' . esc_html( implode( ' · ', $sizes ) ) . '</p>';
		}
		echo '<p class="sr-bridal-note">' . esc_html__( 'Bridal pieces are made to order and priced per customization. Share your details on WhatsApp and our team will guide you through fabrics, embellishment and sizing.', 'samina' ) . '</p>';
		echo wp_kses_post( sr_inquire_button_html( $product ) );
	}, 30 );
}, 20 );

/* ---------- Archive cards: "Inquire" instead of "Add to cart" ---------- */

add_filter( 'woocommerce_loop_add_to_cart_link', function ( $link, $product ) {
	if ( sr_is_bridal( $product ) ) {
		return sr_inquire_button_html( $product, 'sr-whatsapp-inquire button' );
	}
	return $link;
}, 100, 2 );

/* ---------- Structured data: no Offer schema without a public price ---------- */

add_filter( 'woocommerce_structured_data_product', function ( $markup, $product ) {
	if ( sr_is_bridal( $product ) ) {
		unset( $markup['offers'] );
	}
	return $markup;
}, 100, 2 );

/* ---------- Sitewide floating WhatsApp button ---------- */

add_action( 'wp_footer', function () {
	$number = sr_whatsapp_number();
	if ( ! $number ) {
		return;
	}
	$text = rawurlencode( __( 'Hello, I would like to know more about Samina Rasul.', 'samina' ) );
	printf(
		'<a class="sr-whatsapp-float" href="https://wa.me/%s?text=%s" target="_blank" rel="noopener" aria-label="%s"><svg viewBox="0 0 32 32" width="28" height="28" fill="currentColor" aria-hidden="true"><path d="M16 3C9.4 3 4 8.4 4 15c0 2.1.6 4.2 1.6 6L4 29l8.2-1.6c1.2.5 2.5.8 3.8.8 6.6 0 12-5.4 12-12S22.6 3 16 3zm0 21.8c-1.2 0-2.4-.3-3.5-.8l-.6-.3-4.9 1 1-4.7-.3-.6c-.9-1.5-1.4-3.2-1.4-4.9 0-5.4 4.4-9.8 9.8-9.8s9.8 4.4 9.8 9.8-4.4 9.8-9.8 9.8zm5.4-7.3c-.3-.1-1.7-.9-2-1-.3-.1-.5-.1-.7.1-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.2-.5-2.4-1.5-.9-.8-1.5-1.8-1.6-2.1-.2-.3 0-.5.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5 0-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.7-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.7-.4z"/></svg></a>',
		esc_attr( $number ),
		$text, // already URL-encoded
		esc_attr__( 'Chat on WhatsApp', 'samina' )
	);
} );
