<?php
/**
 * Seed: specification attributes and short descriptions, from the catalogue CSV.
 *
 * Run with: wp eval-file seed-4-specs.php
 *
 * The catalogue already carries the fabric breakdown, but as free text inside
 * the Description column:
 *
 *     Hand embellished shirt paired with straight pants and mukesh dupatta.
 *     Shirt: Raw Silk 80gm (with lining)
 *     Pants: Raw Silk 80gm (Straight Pants)
 *     Dupatta: Pure Chiffon with Mukaish Embellishment
 *
 * This splits that: the opening prose becomes the product's short description,
 * and each "Label: Value" line becomes a term on the matching attribute, so the
 * product page can print a specification table instead of a paragraph.
 *
 * Re-runnable. Nothing is invented - a product with no line for an attribute is
 * left without it.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$csv_path = __DIR__ . '/../catalog/samina-rasul-product-catalog.csv';
if ( ! is_readable( $csv_path ) ) {
	WP_CLI::error( "catalogue not readable at $csv_path" );
}

// Ensure the attributes exist before any term is inserted against them.
foreach ( sr_spec_attributes() as $slug => $label ) {
	if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
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
			WP_CLI::error( "attribute $slug: " . $created->get_error_message() );
		}
		WP_CLI::log( "attribute: $label" );
	}

	// Taxonomies register on init, which has already run in this process.
	$taxonomy = wc_attribute_taxonomy_name( $slug );
	if ( ! taxonomy_exists( $taxonomy ) ) {
		register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => true ) );
	}
}

/*
 * Which "Label:" in the catalogue feeds which attribute. Bridal pieces use
 * their own garment names (Pishwas, Lehnga); those are left unmapped rather
 * than filed under a shirt, which would be wrong on the page and wrong in the
 * data.
 */
$label_map = array(
	'shirt'    => 'shirt-fabric',
	'pants'    => 'pants-fabric',
	'trousers' => 'pants-fabric',
	'dupatta'  => 'dupatta-fabric',
);

/*
 * The handwork named anywhere in the description.
 *
 * ponytail: keyword scan, not a parsed field - the catalogue has no Work column,
 * so this reads the techniques out of the prose. Give the atelier a Work column
 * in the CSV and this whole block goes away in favour of reading it.
 */
$techniques = array(
	'zardozi'      => 'Zardozi',
	'mukaish'      => 'Mukaish',
	'mukesh'       => 'Mukaish',
	'naqshi'       => 'Naqshi',
	'resham'       => 'Resham',
	'gota'         => 'Gota',
	'chatta patti' => 'Chatta Patti',
	'pearl'        => 'Pearls',
	'swarovski'    => 'Swarovski',
	'sequin'       => 'Sequins',
	'zari'         => 'Zari',
);

$handle = fopen( $csv_path, 'r' );
$header = fgetcsv( $handle );
if ( ! $header ) {
	WP_CLI::error( 'catalogue has no header row' );
}
// Strip a UTF-8 BOM off the first column name so "SKU" matches.
$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );

$updated = 0;
$skipped = array();

while ( ( $row = fgetcsv( $handle ) ) !== false ) {
	$data = array_combine( $header, array_pad( array_slice( $row, 0, count( $header ) ), count( $header ), '' ) );
	$sku  = trim( (string) $data['SKU'] );
	if ( '' === $sku ) {
		continue;
	}

	$product_id = wc_get_product_id_by_sku( $sku );
	if ( ! $product_id ) {
		$skipped[] = $sku;
		continue;
	}

	$description = (string) $data['Description'];
	$lines       = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $description ) ) );
	if ( ! $lines ) {
		continue;
	}

	$prose  = array_shift( $lines );
	$values = array();

	foreach ( $lines as $line ) {
		// Parenthetical asides in the catalogue are notes to the team, not copy.
		if ( '' === $line || '(' === $line[0] ) {
			continue;
		}
		$parts = explode( ':', $line, 2 );
		if ( count( $parts ) < 2 ) {
			continue;
		}
		$key = strtolower( trim( $parts[0] ) );
		if ( isset( $label_map[ $key ] ) ) {
			$values[ $label_map[ $key ] ] = trim( $parts[1] );
		}
	}

	$found = array();
	foreach ( $techniques as $needle => $name ) {
		if ( false !== stripos( $description, $needle ) ) {
			$found[ $name ] = $name;
		}
	}
	if ( $found ) {
		$values['work'] = implode( ', ', $found );
	}

	$product    = wc_get_product( $product_id );
	$attributes = $product->get_attributes();

	foreach ( $values as $slug => $value ) {
		if ( '' === $value ) {
			continue;
		}
		$taxonomy = wc_attribute_taxonomy_name( $slug );

		$term = term_exists( $value, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $value, $taxonomy );
			if ( is_wp_error( $term ) ) {
				WP_CLI::warning( "term '$value': " . $term->get_error_message() );
				continue;
			}
		}
		$term_id = (int) $term['term_id'];
		wp_set_object_terms( $product_id, array( $term_id ), $taxonomy );

		$attribute = new WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $slug ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( array( $term_id ) );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		$attributes[ $taxonomy ] = $attribute;
	}

	$product->set_attributes( $attributes );
	$product->set_short_description( $prose );
	$product->save();

	++$updated;
	WP_CLI::log( "$sku: " . count( $values ) . ' specs' );
}

fclose( $handle );

if ( $skipped ) {
	WP_CLI::warning( 'no product for SKU: ' . implode( ', ', $skipped ) );
}
WP_CLI::success( "$updated products updated" );
