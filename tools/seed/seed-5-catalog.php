<?php
/**
 * Seed: build the whole catalogue from the CSV.
 *
 * Run with: wp eval-file tools/seed/seed-5-catalog.php
 * Then:     wp eval-file tools/seed/seed-4-specs.php
 *
 * Keyed on SKU, re-runnable, and it never deletes a product. Run it twice and
 * the second pass reports updates and changes nothing else - which matters,
 * because this is meant to be run on the live store over SSH, where the
 * database is the only copy of anything.
 *
 * Three product shapes come out of it, decided by the row and nothing else:
 *
 *   Bridals            simple, price stored but never shown, sizes for
 *                      reference only (samina-core/bridal-flow.php).
 *   Item Options set   variable on pa_fabric x pa_size, one variation per
 *                      option at its own absolute price. This is the only
 *                      shape that puts a price range on the shop card.
 *   Base Price only    variable on pa_size at one price.
 *
 * @package samina-rasul
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once __DIR__ . '/seed-lib.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$sr_root       = dirname( __DIR__, 2 );
$sr_csv        = $sr_root . '/catalog/samina-rasul-product-catalog.csv';
$sr_report_out = $sr_root . '/catalog/import-report.md';

$sr_rows = sr_read_catalog_csv( $sr_csv );
if ( ! $sr_rows ) {
	WP_CLI::error( "No readable catalogue at $sr_csv. Run parse-raw-catalog.php first." );
}

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * Ensure a term exists with a known slug, and return its id.
 *
 * @param string $taxonomy Taxonomy.
 * @param string $name     Display name.
 * @param string $slug     Desired slug.
 * @return int Term id, or 0 on failure.
 */
function sr_ensure_term( $taxonomy, $name, $slug ) {
	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( $term ) {
		return (int) $term->term_id;
	}

	$created = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );

	return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
}

/**
 * Split a "Label | fee | note" block, dropping lines whose fee is unusable.
 *
 * A non-numeric fee stored as 0 is silently skipped at checkout by
 * fabric-addons.php:211, so it would look configured and charge nothing.
 *
 * @param string $raw    Raw textarea block.
 * @param string $sku    SKU, for reporting.
 * @param string $label  Which block this is, for reporting.
 * @return string Cleaned block.
 */
function sr_clean_addon_block( $raw, $sku, $label ) {
	$out = array();

	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}

		$parts = array_map( 'trim', explode( '|', $line ) );
		$fee   = isset( $parts[1] ) ? preg_replace( '/[^\d.]/', '', $parts[1] ) : '';

		if ( '' === $fee || (float) $fee <= 0 ) {
			sr_report( $sku, 'blocker', $label . ' "' . $parts[0] . '" has no usable fee and was not imported. A zero fee is dropped at checkout without a word.' );
			continue;
		}

		$parts[1] = $fee;
		$out[]    = implode( ' | ', $parts );
	}

	return implode( "\n", $out );
}

/**
 * Turn one catalogue Description cell into the two fields the page renders.
 *
 * Line 1 is the blurb. The "Shirt Fabric: ..." lines belong to the spec table
 * and are handled by seed-4-specs.php, so they are kept out of the long
 * description - printed as prose they would repeat the table word for word.
 *
 * @param string $description Description cell.
 * @return array{short: string, long: string}
 */
function sr_split_description( $description ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $description );
	$short = trim( (string) array_shift( $lines ) );
	$prose = array();

	foreach ( $lines as $line ) {
		$line = trim( $line );

		// Parenthetical asides in the catalogue are notes to the team, not copy -
		// the same rule seed-4-specs.php applies.
		if ( '' === $line || '(' === $line[0] ) {
			continue;
		}

		$parts = explode( ':', $line, 2 );
		if ( count( $parts ) > 1 && sr_resolve_spec_key( $parts[0] ) ) {
			continue;
		}

		$prose[] = $line;
	}

	return array(
		'short' => $short,
		'long'  => implode( "\n\n", $prose ),
	);
}

/**
 * Register a file that is already inside uploads/ as a media library item.
 *
 * media_sideload_image() is deliberately not used: it copies and renames the
 * file, so a re-run would fill uploads/ with numbered duplicates of every
 * photo. These files are already in the right place - they only need a post.
 *
 * Idempotent by _wp_attached_file, which is the one value that identifies the
 * file rather than the post.
 *
 * @param string $abs    Absolute path to the file.
 * @param string $rel    Path relative to the uploads basedir.
 * @param int    $parent Product to attach to, or 0 to leave unattached.
 * @return int Attachment id, or 0 on failure.
 */
function sr_register_attachment( $abs, $rel, $parent = 0 ) {
	$found = get_posts(
		array(
			'post_type'              => 'attachment',
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-off CLI import.
				array(
					'key'   => '_wp_attached_file',
					'value' => $rel,
				),
			),
		)
	);

	if ( $found ) {
		return (int) $found[0];
	}

	$filetype = wp_check_filetype( $abs );
	if ( empty( $filetype['type'] ) ) {
		return 0;
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_text_field( pathinfo( $abs, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$abs,
		$parent
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		return 0;
	}

	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $abs ) );

	return (int) $attachment_id;
}

/* -------------------------------------------------------------------------
 * Index the image folder once
 *
 * Photographs are authored in catalog/images/ and copied into uploads/ here,
 * because WordPress can only serve and resize a file that lives under uploads.
 *
 * Two buckets. A product image is "<SKU>-<n>.<ext>" for a SKU in the catalogue;
 * the trailing number orders the gallery and -1 is the featured shot.
 * Everything else is site imagery - the collection and category pictures - and
 * is imported unattached so it can be picked in the Customizer.
 *
 * The SKU is the whole of the filename before the last "-<n>", not a prefix
 * glob: "DK-001-*" as a glob would also match DK-0010-2.webp, and both SKUs
 * exist here. Comparison goes through sr_sku_key() so DK-010 and DK-0010 are
 * understood to be the same piece.
 * ---------------------------------------------------------------------- */

$sr_uploads = wp_upload_dir();
$sr_img_dir = trailingslashit( $sr_uploads['basedir'] ) . 'catalog';
$sr_src_dir = $sr_root . '/catalog/images';

$sr_by_sku  = array();
$sr_other   = array();
$sr_orphans = array();

// SKU lookup keyed on the comparable form, so a filename written with a
// different number of leading zeros still finds its product.
$sr_sku_lookup = array();
foreach ( array_keys( $sr_rows ) as $sr_known ) {
	$sr_sku_lookup[ sr_sku_key( $sr_known ) ] = $sr_known;
}

if ( is_dir( $sr_src_dir ) ) {
	wp_mkdir_p( $sr_img_dir );

	foreach ( (array) scandir( $sr_src_dir ) as $file ) {
		if ( '.' === $file[0] || ! is_file( $sr_src_dir . '/' . $file ) ) {
			continue;
		}
		if ( ! preg_match( '/\.(jpe?g|png|webp)$/i', $file ) ) {
			continue;
		}

		// Copy into uploads/ only when it is not already there and identical, so
		// a re-run does not rewrite 23 MB of photographs.
		$dest = $sr_img_dir . '/' . $file;
		if ( ! is_file( $dest ) || filesize( $dest ) !== filesize( $sr_src_dir . '/' . $file ) ) {
			copy( $sr_src_dir . '/' . $file, $dest );
		}

		if ( preg_match( '/^(.+)-(\d+)\.(?:jpe?g|png|webp)$/i', $file, $m ) ) {
			$key = sr_sku_key( $m[1] );

			if ( isset( $sr_sku_lookup[ $key ] ) ) {
				$sku = $sr_sku_lookup[ $key ];
				$sr_by_sku[ $sku ][ (int) $m[2] ] = $file;

				if ( strtoupper( $m[1] ) !== strtoupper( $sku ) ) {
					sr_report( $sku, 'note', 'Image "' . $file . '" is filed as ' . strtoupper( $m[1] ) . ' but the SKU is ' . $sku . '. Matched on the number; rename one of them so they agree.' );
				}
				continue;
			}

			$sr_orphans[] = $file;
		} else {
			/*
			 * Not "<something>-<n>". If it nonetheless starts with a real SKU then
			 * it is a mistyped product photo, not site imagery - "DK-001-7webp",
			 * "UJ-004-9-" - and it would silently vanish from that product's
			 * gallery. Worth naming.
			 */
			foreach ( $sr_sku_lookup as $known ) {
				if ( 0 === stripos( $file, $known . '-' ) ) {
					sr_report( $known, 'blocker', 'Image "' . $file . '" looks like a photo of this piece but is not named <SKU>-<number>.<ext>, so it was not attached. Rename it.' );
					break;
				}
			}
		}

		$sr_other[] = $file;
	}
}

foreach ( $sr_by_sku as $sku => $files ) {
	// Numeric order: string sort would put -10 ahead of -2 and change which
	// photograph is the featured image.
	ksort( $sr_by_sku[ $sku ], SORT_NUMERIC );
}

/* -------------------------------------------------------------------------
 * Import
 * ---------------------------------------------------------------------- */

$sr_sizes    = array_map( 'trim', explode( ',', sr_catalog_sizes() ) );
$sr_created  = 0;
$sr_updated  = 0;

foreach ( $sr_rows as $sku => $row ) {
	$name       = trim( (string) $row['Product Name'] );
	$category   = strtolower( trim( (string) $row['Category'] ) );
	$collection = strtolower( trim( (string) $row['Collection'] ) );
	$base       = preg_replace( '/[^\d.]/', '', (string) $row['Base Price (PKR)'] );
	$options    = trim( (string) $row['Item Options (one per line: Label | price)'] );
	$is_bridal  = ( 0 === strpos( $category, 'bridal' ) );

	$sizes = array_filter( array_map( 'trim', explode( ',', (string) $row['Sizes (comma-separated)'] ) ) );
	if ( ! $sizes ) {
		$sizes = $sr_sizes;
	}

	/* ---- Parse the item options ---- */

	$combos = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $options ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line ) );
		$price = isset( $parts[1] ) ? preg_replace( '/[^\d.]/', '', $parts[1] ) : '';
		if ( '' === $parts[0] || '' === $price || (float) $price <= 0 ) {
			sr_report( $sku, 'blocker', 'Item option "' . $line . '" has no usable price and was skipped.' );
			continue;
		}
		$combos[ $parts[0] ] = $price;
	}

	if ( ! $combos && ( '' === $base || (float) $base <= 0 ) ) {
		sr_report( $sku, 'blocker', 'No price at all. Imported without one; it cannot be bought until a figure is set.' );
	}

	/* ---- Resolve or create the product ---- */

	$product_id = wc_get_product_id_by_sku( $sku );
	$wanted     = $is_bridal ? 'simple' : 'variable';

	if ( $product_id ) {
		$existing = wc_get_product( $product_id );
		$product  = $existing;

		if ( $existing && $existing->get_type() !== $wanted ) {
			/*
			 * Changing the type means changing the PHP class, and a
			 * WC_Product_Simple can never grow variations however many times its
			 * product_type term is rewritten.
			 *
			 * The target class is therefore constructed directly against the post
			 * id. Setting the term and calling wc_get_product() again does not
			 * work: the factory resolves the class through a cache that the term
			 * write does not always clear, so the object comes back as the old
			 * class and saving it writes the old type straight back.
			 */
			wp_set_object_terms( $product_id, $wanted, 'product_type' );
			wc_delete_product_transients( $product_id );

			$product = ( 'variable' === $wanted )
				? new WC_Product_Variable( $product_id )
				: new WC_Product_Simple( $product_id );
		}

		++$sr_updated;
	} else {
		$product = ( 'variable' === $wanted ) ? new WC_Product_Variable() : new WC_Product_Simple();
		$product->set_sku( $sku );
		++$sr_created;
	}

	if ( ! $product instanceof WC_Product ) {
		sr_report( $sku, 'blocker', 'Could not load or create the product. Skipped.' );
		continue;
	}

	/* ---- Name: the CSV owns it, unless someone has renamed it by hand ---- */

	$current = trim( (string) $product->get_name() );
	if ( '' === $current || false !== stripos( $current, '(sample)' ) ) {
		$product->set_name( $name );
	} elseif ( $current !== $name ) {
		sr_report( $sku, 'note', 'Kept the name set in wp-admin ("' . $current . '") rather than the CSV\'s ("' . $name . '"). Update the CSV if the site is right.' );
	}

	/*
	 * The slug is derived from the name once, at creation, and never again - so
	 * a placeholder that has been renamed keeps serving
	 * /product/dhanak-formal-dk-001-sample/ forever. Checked against the slug
	 * rather than the name so it still repairs itself on a later run, after the
	 * name has already been corrected. Nothing links to these yet.
	 */
	if ( false !== stripos( (string) $product->get_slug(), 'sample' ) ) {
		$product->set_slug( sanitize_title( $product->get_name() ) );
	}

	/* ---- Descriptions ---- */

	$split = sr_split_description( (string) $row['Description'] );
	$product->set_short_description( $split['short'] );
	$product->set_description( $split['long'] );

	if ( '' === $split['long'] ) {
		sr_report( $sku, 'note', 'No long description, so the story block will not render on the product page.' );
	}

	/* ---- Attributes ---- */

	/*
	 * Merged, not replaced. This script owns pa_size and pa_fabric - the two
	 * that drive variations - and nothing else. The spec attributes are written
	 * by seed-4-specs.php, and replacing the whole set here silently wiped every
	 * product's specification table on any later re-run.
	 */
	$attributes = $product->get_attributes();

	if ( 'variable' === $wanted ) {
		$attributes['pa_size'] = sr_seed_attr( 'pa_size', $sizes, true );

		if ( $combos ) {
			$attributes['pa_fabric'] = sr_seed_attr( 'pa_fabric', array_keys( $combos ), true );
		} else {
			// No options any more: the fabric attribute would leave an orphan
			// dropdown the customer cannot resolve.
			unset( $attributes['pa_fabric'] );
			$product->set_regular_price( $base );
		}
	} else {
		// Bridal: sizes are shown for reference only, never selected.
		$attributes['pa_size'] = sr_seed_attr( 'pa_size', $sizes, false );
		unset( $attributes['pa_fabric'] );
		$product->set_regular_price( $base );
	}

	$product->set_attributes( $attributes );

	$product_id = $product->save();

	/* ---- Category, collection, lead time ---- */

	sr_ensure_term( 'product_cat', ucfirst( $category ), sanitize_title( $category ) );
	sr_ensure_term( 'sr_collection', ucfirst( $collection ), sanitize_title( $collection ) );
	sr_seed_assign(
		$product_id,
		sanitize_title( $category ),
		sanitize_title( $collection ),
		trim( (string) $row['Delivery Time'] )
	);

	/* ---- Add-ons ---- */

	update_post_meta(
		$product_id,
		'_sr_fabric_addons',
		sr_clean_addon_block( $row['Fabric Upgrades (one per line: Label | added fee)'], $sku, 'Fabric upgrade' )
	);
	update_post_meta(
		$product_id,
		'_sr_extra_addons',
		sr_clean_addon_block( $row['Optional Extras (one per line: Label | added fee)'], $sku, 'Optional extra' )
	);

	/* ---- Variations ---- */

	if ( 'variable' === $wanted ) {
		$parent = wc_get_product( $product_id );
		$want   = array();

		if ( $combos ) {
			foreach ( $combos as $label => $price ) {
				$term = get_term_by( 'name', $label, 'pa_fabric' );
				if ( ! $term ) {
					sr_report( $sku, 'blocker', 'Option "' . $label . '" has no pa_fabric term, so no variation was built for it.' );
					continue;
				}
				// pa_size is left empty on purpose: "Any size". One variation per
				// fabric instead of one per fabric x size, because size does not
				// move the price - and the size the customer picks is still
				// recorded on the order.
				$want[ $term->slug ] = array(
					'attributes' => array(
						'pa_fabric' => $term->slug,
						'pa_size'   => '',
					),
					'price'      => $price,
				);
			}
		} else {
			$want[''] = array(
				'attributes' => array( 'pa_size' => '' ),
				'price'      => $base,
			);
		}

		$seen = array();
		foreach ( $parent->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation ) {
				continue;
			}

			$attributes = $variation->get_attributes();
			$key        = isset( $attributes['pa_fabric'] ) ? (string) $attributes['pa_fabric'] : '';

			if ( ! isset( $want[ $key ] ) || isset( $seen[ $key ] ) ) {
				// A variation for an option the catalogue no longer lists, or a
				// duplicate. Left behind it would keep selling at its old price.
				$variation->delete( true );
				sr_report( $sku, 'note', 'Removed a stale variation (' . ( '' === $key ? 'no fabric' : $key ) . ').' );
				continue;
			}

			$variation->set_attributes( $want[ $key ]['attributes'] );
			$variation->set_regular_price( $want[ $key ]['price'] );
			$variation->save();
			$seen[ $key ] = true;
		}

		foreach ( $want as $key => $spec ) {
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			$variation->set_attributes( $spec['attributes'] );
			$variation->set_regular_price( $spec['price'] );
			$variation->save();
		}

		WC_Product_Variable::sync( $product_id );
	}

	/* ---- Images ---- */

	if ( empty( $sr_by_sku[ $sku ] ) ) {
		sr_report( $sku, 'note', 'No images found in uploads/catalog/ for this SKU.' );
	} else {
		$ids = array();
		foreach ( $sr_by_sku[ $sku ] as $file ) {
			$id = sr_register_attachment( $sr_img_dir . '/' . $file, 'catalog/' . $file, $product_id );
			if ( $id ) {
				$ids[] = $id;
			} else {
				sr_report( $sku, 'note', 'Could not register image "' . $file . '".' );
			}
		}

		if ( $ids ) {
			$product = wc_get_product( $product_id );
			$product->set_image_id( array_shift( $ids ) );
			$product->set_gallery_image_ids( $ids );
			$product->save();
		}
	}

	WP_CLI::log( $sku . ': ' . $wanted . ' #' . $product_id . ' (' . ( $combos ? count( $combos ) . ' options' : 'single price' ) . ')' );
}

/* -------------------------------------------------------------------------
 * Site imagery: everything in the folder that is not a product photo
 * ---------------------------------------------------------------------- */

foreach ( $sr_other as $file ) {
	$id = sr_register_attachment( $sr_img_dir . '/' . $file, 'catalog/' . $file, 0 );
	if ( ! $id ) {
		continue;
	}

	$orphan = in_array( $file, $sr_orphans, true );
	sr_report(
		'Site imagery',
		'note',
		( $orphan ? 'ORPHAN (named like a product photo, but "' . strtoupper( (string) preg_replace( '/-\d+\.[^.]+$/', '', $file ) ) . '" is not a SKU — check for a typo): ' : '' )
		. $file . ' — attachment #' . $id . ' — ' . wp_get_attachment_url( $id )
	);
}

/* -------------------------------------------------------------------------
 * Done
 * ---------------------------------------------------------------------- */

$sr_blockers = sr_report_write( $sr_report_out, 'Catalogue import report' );

WP_CLI::success(
	$sr_created . ' created, ' . $sr_updated . ' updated. '
	. $sr_blockers . ' blockers — see catalog/import-report.md'
);
