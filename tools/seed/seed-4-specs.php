<?php
/**
 * Seed: specification attributes, from the catalogue CSV.
 *
 * Run with: wp eval-file tools/seed/seed-4-specs.php  (after seed-5-catalog.php)
 *
 * The catalogue carries the fabric breakdown inside the Description column:
 *
 *     Hand embellished shirt paired with straight pants and mukesh dupatta.
 *     Shirt Fabric: Raw Silk 80gm (with lining)
 *     Shalwar Fabric: Raw Silk 80gm (Plain Dyed, Traditional Style)
 *     Dupatta Fabric: Pure Chiffon with Mukaish Embellishment
 *
 * Each "Label: Value" line becomes a term on the matching attribute, so the
 * product page prints a specification table instead of a paragraph.
 *
 * Four garments have a house attribute (samina-core/product-attributes.php).
 * Everything else the atelier names - a pishwas, a lehnga, a choli, a ghrara -
 * becomes a per-product custom attribute carrying its own label, because filing
 * a lehnga under "Pants Fabric" would put a wrong word on a bridal page.
 *
 * Re-runnable. Nothing is invented: a product with no line for an attribute is
 * left without it.
 *
 * @package samina-rasul
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once __DIR__ . '/seed-lib.php';

$sr_root       = dirname( __DIR__, 2 );
$sr_csv        = $sr_root . '/catalog/samina-rasul-product-catalog.csv';
$sr_report_out = $sr_root . '/catalog/specs-report.md';

$sr_rows = sr_read_catalog_csv( $sr_csv );
if ( ! $sr_rows ) {
	WP_CLI::error( "No readable catalogue at $sr_csv." );
}

// Ensure the house attributes exist before any term is inserted against them.
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
 * The handwork named anywhere in the description.
 *
 * ponytail: keyword scan, not a parsed field - the catalogue has no Work column,
 * so this reads the techniques out of the prose. Give the atelier a Work column
 * in the CSV and this whole block goes away in favour of reading it.
 */
$sr_techniques = array(
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

$sr_updated = 0;
$sr_skipped = array();

foreach ( $sr_rows as $sku => $row ) {
	$product_id = wc_get_product_id_by_sku( $sku );
	if ( ! $product_id ) {
		$sr_skipped[] = $sku;
		continue;
	}

	$description = (string) $row['Description'];
	$lines       = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $description ) ) );
	if ( ! $lines ) {
		continue;
	}

	$prose = array_shift( $lines );
	$taxes = array();
	$plain = array();

	foreach ( $lines as $line ) {
		// Parenthetical asides in the catalogue are notes to the team, not copy.
		if ( '' === $line || '(' === $line[0] ) {
			continue;
		}

		$parts = explode( ':', $line, 2 );
		if ( count( $parts ) < 2 || '' === trim( $parts[1] ) ) {
			continue;
		}

		$spec = sr_resolve_spec_key( $parts[0] );
		if ( ! $spec ) {
			// Only worth reporting when the line reads like a field rather than a
			// sentence - a short key before the colon.
			if ( str_word_count( $parts[0] ) <= 4 ) {
				sr_report( $sku, 'note', 'Unrecognised spec key "' . trim( $parts[0] ) . '", left as prose: "' . $line . '"' );
			}
			continue;
		}

		if ( 'taxonomy' === $spec['type'] ) {
			$taxes[ $spec['slug'] ] = trim( $parts[1] );
		} else {
			$plain[ $spec['label'] ] = trim( $parts[1] );
		}
	}

	$found = array();
	foreach ( $sr_techniques as $needle => $name ) {
		if ( false !== stripos( $description, $needle ) ) {
			$found[ $name ] = $name;
		}
	}
	if ( $found ) {
		$taxes['work'] = implode( ', ', $found );
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		$sr_skipped[] = $sku;
		continue;
	}

	$attributes = $product->get_attributes();

	foreach ( $taxes as $slug => $value ) {
		if ( '' === $value ) {
			continue;
		}
		$taxonomy = wc_attribute_taxonomy_name( $slug );

		$term = term_exists( $value, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $value, $taxonomy );
			if ( is_wp_error( $term ) ) {
				sr_report( $sku, 'note', "term '$value': " . $term->get_error_message() );
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

	/*
	 * Custom attributes carry their own label and their value as plain text.
	 * sr_product_spec_rows() already reads both kinds - it falls back to
	 * get_options() when a term lookup returns nothing - so these print in the
	 * spec table exactly like the house four, under the garment's real name.
	 */
	foreach ( $plain as $label => $value ) {
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( $label );
		$attribute->set_options( array( $value ) );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		$attributes[ sanitize_title( $label ) ] = $attribute;
	}

	$product->set_attributes( $attributes );
	$product->set_short_description( $prose );
	$product->save();

	++$sr_updated;
	WP_CLI::log( $sku . ': ' . ( count( $taxes ) + count( $plain ) ) . ' specs' );
}

if ( $sr_skipped ) {
	sr_report( 'Catalogue', 'blocker', 'No product exists for: ' . implode( ', ', $sr_skipped ) . '. Run seed-5-catalog.php first.' );
	WP_CLI::warning( 'no product for SKU: ' . implode( ', ', $sr_skipped ) );
}

$sr_blockers = sr_report_write( $sr_report_out, 'Specification attributes report' );

WP_CLI::success( "$sr_updated products updated. $sr_blockers blockers — see catalog/specs-report.md" );
