<?php
/**
 * "Collection" taxonomy — cuts across product categories.
 * A product is in one Category (Formals/Bridals) and one Collection (Dhanak/Ujala).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	register_taxonomy(
		'sr_collection',
		'product',
		array(
			'labels'            => array(
				'name'          => __( 'Collections', 'samina' ),
				'singular_name' => __( 'Collection', 'samina' ),
				'menu_name'     => __( 'Collections', 'samina' ),
				'add_new_item'  => __( 'Add New Collection', 'samina' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'collection', 'with_front' => false ),
		)
	);
} );
