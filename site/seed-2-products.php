<?php
// Seed: 5 sample SKUs from the brief (Section 5), one per pricing pattern.
// Placeholder names/labels marked (Sample) — replaced when the real catalog arrives.
// Run via: wp eval-file seed-2-products.php

function sr_seed_terms( $taxonomy, $names ) {
	$ids = array();
	foreach ( $names as $name ) {
		$term = term_exists( $name, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $name, $taxonomy );
		}
		$ids[ $name ] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
	}
	return $ids;
}

function sr_seed_attr( $taxonomy, $options, $for_variation = true ) {
	sr_seed_terms( $taxonomy, $options );
	$attr = new WC_Product_Attribute();
	$attr->set_id( wc_attribute_taxonomy_id_by_name( str_replace( 'pa_', '', $taxonomy ) ) );
	$attr->set_name( $taxonomy );
	$attr->set_options( array_map( function ( $n ) use ( $taxonomy ) {
		return (int) get_term_by( 'name', $n, $taxonomy )->term_id;
	}, $options ) );
	$attr->set_visible( true );
	$attr->set_variation( $for_variation );
	return $attr;
}

function sr_seed_assign( $product_id, $category_slug, $collection_slug, $delivery ) {
	wp_set_object_terms( $product_id, $category_slug, 'product_cat' );
	wp_set_object_terms( $product_id, $collection_slug, 'sr_collection' );
	update_post_meta( $product_id, '_sr_delivery_time', $delivery );
}

$sizes = array( 'XS', 'S', 'M', 'ML', 'L', 'XL', 'Customized' );

// Skip if already seeded.
if ( wc_get_product_id_by_sku( 'DK-001' ) ) {
	WP_CLI::warning( 'Sample products already exist — skipping.' );
	return;
}

/* DK-001 — fixed single price. */
$p = new WC_Product_Simple();
$p->set_name( 'Dhanak Formal DK-001 (Sample)' );
$p->set_sku( 'DK-001' );
$p->set_regular_price( '87500' );
$p->set_description( 'Sample product — real name, imagery and description come from the client catalog. Hand-embellished formal from the Dhanak collection; each piece is made to order.' );
$id = $p->save();
sr_seed_assign( $id, 'formals', 'dhanak', '7–8 weeks' );
WP_CLI::log( "DK-001 simple #$id" );

/* DK-002 — size variations at base price + fabric upgrade add-on. */
$p = new WC_Product_Variable();
$p->set_name( 'Dhanak Formal DK-002 (Sample)' );
$p->set_sku( 'DK-002' );
$p->set_description( 'Sample product — demonstrates Layer 1 (size variations) + Layer 2 (fabric upgrade fee on top of the variation price).' );
$p->set_attributes( array( sr_seed_attr( 'pa_size', $sizes ) ) );
$id = $p->save();
foreach ( $sizes as $size ) {
	$v = new WC_Product_Variation();
	$v->set_parent_id( $id );
	$v->set_attributes( array( 'pa_size' => sanitize_title( $size ) ) );
	$v->set_regular_price( '39500' );
	$v->save();
}
sr_seed_assign( $id, 'formals', 'dhanak', '7–8 weeks' );
update_post_meta( $id, '_sr_fabric_addons', "Raw Silk 80gm | 16500" );
WC_Product_Variable::sync( $id );
WP_CLI::log( "DK-002 variable #$id" );

/* DK-008 — Bridal: fixed price stored, never shown; inquiry-only flow. */
$p = new WC_Product_Simple();
$p->set_name( 'Dhanak Bridal DK-008 (Sample)' );
$p->set_sku( 'DK-008' );
$p->set_regular_price( '120000' );
$p->set_description( 'Sample bridal piece — price stored internally (120,000) but hidden on the storefront; the only CTA is Inquire on WhatsApp.' );
$p->set_attributes( array( sr_seed_attr( 'pa_size', $sizes, false ) ) );
$id = $p->save();
sr_seed_assign( $id, 'bridals', 'dhanak', '7–8 weeks' );
WP_CLI::log( "DK-008 bridal #$id" );

/* UJ-003 — four fabric/embellishment combos, each its own absolute price. */
$combo_prices = array(
	'Fabric Combo 1 (Sample)' => '110500',
	'Fabric Combo 2 (Sample)' => '69500',
	'Fabric Combo 3 (Sample)' => '94000',
	'Fabric Combo 4 (Sample)' => '54000',
);
$p = new WC_Product_Variable();
$p->set_name( 'Ujala Formal UJ-003 (Sample)' );
$p->set_sku( 'UJ-003' );
$p->set_description( 'Sample product — four distinct fabric/embellishment combinations, each with its own absolute price (not additive). Combo names are placeholders pending real catalog data.' );
$p->set_attributes( array( sr_seed_attr( 'pa_fabric', array_keys( $combo_prices ) ) ) );
$id = $p->save();
foreach ( $combo_prices as $combo => $price ) {
	$v = new WC_Product_Variation();
	$v->set_parent_id( $id );
	$v->set_attributes( array( 'pa_fabric' => sanitize_title( $combo ) ) );
	$v->set_regular_price( $price );
	$v->save();
}
sr_seed_assign( $id, 'formals', 'ujala', '8–9 weeks' );
WC_Product_Variable::sync( $id );
WP_CLI::log( "UJ-003 variable #$id" );

/* UJ-009 — base price + optional standalone add-on item (matching shawl). */
$p = new WC_Product_Simple();
$p->set_name( 'Ujala Formal UJ-009 (Sample)' );
$p->set_sku( 'UJ-009' );
$p->set_regular_price( '65000' ); // PLACEHOLDER — base price not in brief.
$p->set_description( 'Sample product — base price (placeholder) plus an optional matching shawl added via checkbox. Shawl fee is a placeholder pending real catalog data.' );
$id = $p->save();
sr_seed_assign( $id, 'formals', 'ujala', '8–9 weeks' );
update_post_meta( $id, '_sr_extra_addons', "Matching shawl | 12000" );
WP_CLI::log( "UJ-009 simple+extra #$id" );

WP_CLI::success( 'Product seed done.' );
