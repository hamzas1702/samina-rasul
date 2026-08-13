<?php
/**
 * The house's specification attributes.
 *
 * A couture piece is bought on its cloth and its handwork, so those four facts
 * need to be structured data rather than a sentence buried in the description:
 * the product page prints them as a specification table, and being taxonomies
 * means "Sheesha Silk" is one term reused across the catalogue rather than
 * re-typed (and re-misspelled) on every product.
 *
 * Registered as global attributes, so they appear under Products → Attributes
 * and can be added to any product from the product data panel.
 *
 * @package samina-rasul
 */

defined( 'ABSPATH' ) || exit;

/**
 * The attributes this store expects every garment to be describable by.
 *
 * @return array<string, string> Slug => label.
 */
function sr_spec_attributes() {
	return array(
		'shirt-fabric'   => __( 'Shirt Fabric', 'samina' ),
		'pants-fabric'   => __( 'Pants Fabric', 'samina' ),
		'dupatta-fabric' => __( 'Dupatta Fabric', 'samina' ),
		'work'           => __( 'Work', 'samina' ),
	);
}

/**
 * Create any of them that do not exist yet.
 *
 * Runs on admin_init rather than init: wc_create_attribute() writes to the
 * attribute table and flushes the taxonomy cache, which has no business
 * happening on a front-end request. The version option keeps it to one pass;
 * bump it if the list above ever grows.
 */
function sr_register_spec_attributes() {
	if ( ! function_exists( 'wc_create_attribute' ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	if ( '1' === get_option( 'sr_spec_attributes_version' ) ) {
		return;
	}

	$existing = array();
	foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
		$existing[] = $taxonomy->attribute_name;
	}

	foreach ( sr_spec_attributes() as $slug => $label ) {
		// WooCommerce stores the attribute name without the pa_ prefix and
		// truncated to 28 characters; compare on the same basis.
		if ( in_array( wc_sanitize_taxonomy_name( $slug ), $existing, true ) ) {
			continue;
		}

		$created = wc_create_attribute(
			array(
				'name'         => $label,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);

		if ( is_wp_error( $created ) ) {
			// A failure here is a setup problem, not a request problem - leave
			// the version unset so the next admin load tries again.
			return;
		}
	}

	update_option( 'sr_spec_attributes_version', '1', false );
}
add_action( 'admin_init', 'sr_register_spec_attributes' );
