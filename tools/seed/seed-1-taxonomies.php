<?php
// Seed: categories, collections, global attributes. Run via: wp eval-file seed-1-taxonomies.php

// Product categories.
foreach ( array( 'Formals' => 'formals', 'Bridals' => 'bridals' ) as $name => $slug ) {
	if ( ! term_exists( $slug, 'product_cat' ) ) {
		wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
		WP_CLI::log( "product_cat: $name" );
	}
}

// Collections.
foreach ( array( 'Dhanak' => 'dhanak', 'Ujala' => 'ujala' ) as $name => $slug ) {
	if ( ! term_exists( $slug, 'sr_collection' ) ) {
		wp_insert_term( $name, 'sr_collection', array( 'slug' => $slug ) );
		WP_CLI::log( "sr_collection: $name" );
	}
}

// Global product attributes.
foreach ( array( 'Item' => 'item', 'Size' => 'size', 'Fabric' => 'fabric' ) as $label => $slug ) {
	if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
		$id = wc_create_attribute( array(
			'name'         => $label,
			'slug'         => $slug,
			'type'         => 'select',
			'order_by'     => 'menu_order',
			'has_archives' => false,
		) );
		WP_CLI::log( "attribute: $label (#$id)" );
	}
}
WP_CLI::success( 'Taxonomy seed done.' );
