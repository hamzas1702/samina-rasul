<?php
/**
 * Reconcile every product's size list and lead time with the catalogue rules.
 *
 * Two changes the house asked for after the first import:
 *
 * 1. `ML` is retired. It sat between two sizes it was never cut differently
 *    from, and anyone between sizes belongs in "Customized", which now collects
 *    real measurements (mu-plugins/samina-core/custom-size.php).
 * 2. Bridals take 10–12 weeks, not the 7–8 the importer inherited from their
 *    Dhanak write-ups. Formals stay at 7–8, including the Ujala pieces that
 *    came in at 8–9.
 *
 * Idempotent: safe to run repeatedly, and safe to run before or after a
 * re-import. Nothing here depends on the CSV - the rules live in seed-lib.php
 * (sr_catalog_sizes(), sr_delivery_for_category()) and this script applies them.
 *
 * Run from site/:
 *   ../.tools/wp eval-file ../tools/seed/seed-7-sizes-delivery.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through wp eval-file.\n" );
	exit( 1 );
}

require_once __DIR__ . '/seed-lib.php';

/** Size terms the catalogue offers, in display order. */
$sr_wanted = array_values(
	array_filter( array_map( 'trim', explode( ',', sr_catalog_sizes() ) ) )
);

$sr_wanted_slugs = array_map( 'sanitize_title', $sr_wanted );

$sr_products = get_posts(
	array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

printf( "Reconciling %d products.\n", count( $sr_products ) );

$sr_size_changed     = 0;
$sr_delivery_changed = 0;

foreach ( $sr_products as $sr_id ) {
	$sr_product = wc_get_product( $sr_id );
	if ( ! $sr_product instanceof WC_Product ) {
		continue;
	}

	$sr_sku = $sr_product->get_sku();

	/*
	 * --- Sizes -----------------------------------------------------------
	 * Only touched when the product already offers sizes. A piece deliberately
	 * sold in one size should not gain a size list because this script ran.
	 */
	$sr_current = wc_get_product_terms( $sr_id, 'pa_size', array( 'fields' => 'slugs' ) );

	if ( $sr_current && array_values( $sr_current ) !== $sr_wanted_slugs ) {
		wp_set_object_terms( $sr_id, $sr_wanted_slugs, 'pa_size' );

		// The attribute row itself stores the term ids, so it has to be
		// rewritten too or the product page keeps rendering the old list.
		$sr_attributes = $sr_product->get_attributes();
		if ( isset( $sr_attributes['pa_size'] ) && $sr_attributes['pa_size'] instanceof WC_Product_Attribute ) {
			$sr_ids = wc_get_product_terms( $sr_id, 'pa_size', array( 'fields' => 'ids' ) );
			$sr_attributes['pa_size']->set_options( $sr_ids );
			$sr_product->set_attributes( $sr_attributes );
		}

		$sr_size_changed++;
		printf( "  %-8s sizes -> %s\n", $sr_sku, implode( ', ', $sr_wanted ) );
	}

	/*
	 * --- Lead time --------------------------------------------------------
	 * Category-driven, so a bridal filed under the Dhanak collection still
	 * gets the bridal window.
	 */
	$sr_cats     = wp_get_post_terms( $sr_id, 'product_cat', array( 'fields' => 'slugs' ) );
	$sr_category = in_array( 'bridals', (array) $sr_cats, true ) ? 'bridals' : 'formals';
	$sr_lead     = sr_delivery_for_category( $sr_category );

	if ( (string) $sr_product->get_meta( '_sr_delivery_time' ) !== $sr_lead ) {
		$sr_product->update_meta_data( '_sr_delivery_time', $sr_lead );
		$sr_delivery_changed++;
		printf( "  %-8s delivery -> %s (%s)\n", $sr_sku, $sr_lead, $sr_category );
	}

	$sr_product->save();
}

/*
 * The ML term itself, once nothing points at it. Deleted last so a failure
 * above cannot strand products against a term that no longer exists.
 */
$sr_ml = get_term_by( 'slug', 'ml', 'pa_size' );
if ( $sr_ml instanceof WP_Term ) {
	$sr_still_used = get_objects_in_term( array( $sr_ml->term_id ), 'pa_size' );
	if ( is_wp_error( $sr_still_used ) || $sr_still_used ) {
		printf(
			"! pa_size 'ML' still attached to %d products - left in place.\n",
			is_wp_error( $sr_still_used ) ? -1 : count( $sr_still_used )
		);
	} else {
		wp_delete_term( $sr_ml->term_id, 'pa_size' );
		echo "Deleted pa_size term 'ML'.\n";
	}
}

if ( function_exists( 'wc_delete_product_transients' ) ) {
	foreach ( $sr_products as $sr_id ) {
		wc_delete_product_transients( $sr_id );
	}
}

printf( "Done. %d size lists, %d lead times updated.\n", $sr_size_changed, $sr_delivery_changed );
