<?php
/**
 * Shared helpers for the catalogue tooling.
 *
 * Required by both the WordPress-side seeders (run through `wp eval-file`) and
 * the standalone parser (run through plain `php`). Nothing here touches
 * WordPress at include time - the functions that need it resolve their calls
 * when invoked, so this file is safe to require from either context.
 *
 * @package samina-rasul
 */

// These are maintenance scripts. tools/ is never deployed and never web
// reachable, but a stray copy inside the docroot must not be executable over
// HTTP: a catalogue rewrite triggered by an anonymous GET is not a risk worth
// leaving open for the sake of one line.
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

/* -------------------------------------------------------------------------
 * Catalogue constants
 * ---------------------------------------------------------------------- */

/**
 * The catalogue CSV header, in order. Both the parser and the importer agree on
 * this list; the parser writes it and the importer reads it by name.
 */
function sr_catalog_columns() {
	return array(
		'SKU',
		'Product Name',
		'Category',
		'Collection',
		'Base Price (PKR)',
		'Item Options (one per line: Label | price)',
		'Sizes (comma-separated)',
		'Fabric Upgrades (one per line: Label | added fee)',
		'Optional Extras (one per line: Label | added fee)',
		'Delivery Time',
		'Description',
		'Image Filenames',
	);
}

/**
 * Sizes every garment is offered in. "Customized" is the made-to-measure route
 * and is what the size guide points at - see sr_size_chart_image() in the theme
 * and the measurement dialog in samina-core/custom-size.php.
 *
 * ML was dropped at the house's request: it sat between two sizes it was never
 * cut differently from, and anyone between sizes is better served by
 * "Customized", which now actually collects the measurements.
 */
function sr_catalog_sizes() {
	return 'XS, S, M, L, XL, Customized';
}

/**
 * Lead time for a category.
 *
 * The write-ups state a time per piece, but the real driver is the category:
 * a formal is a shorter build than a bridal, whatever the collection. This is
 * the single source of truth for both the catalogue CSV and the live products,
 * so the site cannot end up quoting one figure on the product page and another
 * in the atelier note.
 *
 * @param string $category_slug 'formals' or 'bridals'.
 * @return string Lead time, e.g. '7–8 weeks'.
 */
function sr_delivery_for_category( $category_slug ) {
	return 'bridals' === strtolower( trim( (string) $category_slug ) )
		? '10–12 weeks'
		: '7–8 weeks';
}

/**
 * House lead time, used when a row states no category at all.
 */
function sr_catalog_default_delivery() {
	return sr_delivery_for_category( 'formals' );
}

/**
 * Collection implied by the SKU prefix. The write-ups never state it.
 *
 * @param string $sku Product SKU.
 * @return string Collection name, or '' when the prefix is unrecognised.
 */
function sr_collection_for_sku( $sku ) {
	$prefix = strtoupper( substr( trim( (string) $sku ), 0, 2 ) );

	switch ( $prefix ) {
		case 'DK':
			return 'Dhanak';
		case 'UJ':
			return 'Ujala';
		default:
			return '';
	}
}

/* -------------------------------------------------------------------------
 * Garment vocabulary
 *
 * The write-ups name whichever garment the piece actually has - a formal has a
 * shirt and a shalwar, a bridal has a pishwas, a lehnga, a choli or a ghrara.
 * Only four of those have a house attribute (product-attributes.php), and
 * filing a lehnga under "Pants Fabric" would put a wrong label on the page, so
 * everything else keeps its own name as a per-product custom attribute.
 * sr_product_spec_rows() in the theme already renders both kinds.
 * ---------------------------------------------------------------------- */

/**
 * Garment stem => house attribute slug, or '' to keep its own name.
 *
 * @return array<string, string>
 */
function sr_garment_keys() {
	return array(
		'shirt'    => 'shirt-fabric',
		'kameez'   => 'shirt-fabric',
		'pants'    => 'pants-fabric',
		'trousers' => 'pants-fabric',
		'dupatta'  => 'dupatta-fabric',
		'odhni'    => 'dupatta-fabric',
		'work'     => 'work',

		'shalwar'         => '',
		'farshi shalwar'  => '',
		'pishwas'         => '',
		'lehnga'          => '',
		'lehenga'         => '',
		'choli'           => '',
		'ghrara'          => '',
		'gharara'         => '',
		'peplum'          => '',
		'shawl'           => '',
		'jacket'          => '',
		'inner'           => '',
		'lining'          => '',
		'blouse'          => '',
		'sleeves'         => '',
	);
}

/**
 * Reduce a SKU to a comparable form by dropping leading zeros in its number.
 *
 * The catalogue numbers the tenth Dhanak piece "DK-0010" while its photographs
 * are filed as "DK-010-1.webp". Both mean the tenth piece, and comparing them
 * literally strands every bridal image. Stripping leading zeros makes the two
 * agree without renaming anything.
 *
 * It cannot introduce a collision: DK-001 reduces to DK-1 and DK-0010 to DK-10,
 * which is exactly the distinction a prefix match would have lost.
 *
 * @param string $sku SKU or filename stem.
 * @return string Comparable key, uppercased.
 */
function sr_sku_key( $sku ) {
	$sku = strtoupper( trim( (string) $sku ) );

	return (string) preg_replace_callback(
		'/(\d+)/',
		function ( $m ) {
			return ltrim( $m[1], '0' ) === '' ? '0' : ltrim( $m[1], '0' );
		},
		$sku
	);
}

/**
 * Reduce a spec key to something comparable.
 *
 * Absorbs the defects the write-ups actually carry: a stray "=" after a
 * heading, leading bullets and spaces, and casing drift.
 *
 * @param string $key Raw key text.
 * @return string Lowercased, punctuation-free key.
 */
function sr_norm_key( $key ) {
	$key = strtolower( trim( (string) $key ) );
	$key = preg_replace( '/^[\s\x{00A0}\-\*\x{2022}\d\.\)]+/u', '', $key );
	$key = preg_replace( '/[:=\s]+$/u', '', $key );
	$key = preg_replace( '/\s+/u', ' ', $key );

	return trim( (string) $key );
}

/**
 * Resolve a spec key to the attribute it belongs on.
 *
 * The write-ups say "Shirt Fabric:" and "Shalwar Fabric:", so the trailing
 * "fabric" is dropped before matching. When a key arrives with leading noise
 * ("Details Shirt Fabric"), the trailing words are tried too rather than
 * dropping the row.
 *
 * @param string $key Raw key text.
 * @return array{type: string, slug: string, label: string}|null Null when it is
 *         not a garment key at all.
 */
function sr_resolve_spec_key( $key ) {
	$norm = sr_norm_key( $key );
	if ( '' === $norm ) {
		return null;
	}

	$stem = trim( (string) preg_replace( '/\s*fabrics?$/u', '', $norm ) );
	if ( '' === $stem ) {
		return null;
	}

	$map   = sr_garment_keys();
	$words = explode( ' ', $stem );

	$candidates = array( $stem );
	if ( count( $words ) > 1 ) {
		$candidates[] = implode( ' ', array_slice( $words, -2 ) );
		$candidates[] = end( $words );
	}

	foreach ( $candidates as $candidate ) {
		if ( ! isset( $map[ $candidate ] ) ) {
			continue;
		}

		if ( '' !== $map[ $candidate ] ) {
			return array(
				'type'  => 'taxonomy',
				'slug'  => $map[ $candidate ],
				'label' => $candidate,
			);
		}

		return array(
			'type'  => 'custom',
			'slug'  => '',
			// Its own name, as written, is the label: "Farshi Shalwar Fabric".
			'label' => ucwords( 0 === strpos( $norm, $candidate ) ? $norm : $candidate . ' fabric' ),
		);
	}

	return null;
}

/* -------------------------------------------------------------------------
 * CSV
 * ---------------------------------------------------------------------- */

/**
 * Read the catalogue CSV into rows keyed by column name, indexed by SKU.
 *
 * @param string $path Absolute path to the CSV.
 * @return array<string, array<string, string>> SKU => row. Empty when unreadable.
 */
function sr_read_catalog_csv( $path ) {
	if ( ! is_readable( $path ) ) {
		return array();
	}

	$handle = fopen( $path, 'r' );
	if ( ! $handle ) {
		return array();
	}

	$header = fgetcsv( $handle );
	if ( ! $header ) {
		fclose( $handle );
		return array();
	}

	// Strip a UTF-8 BOM off the first column name so "SKU" matches. The file is
	// written with one so it opens correctly in Excel.
	$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );

	$rows = array();
	while ( ( $row = fgetcsv( $handle ) ) !== false ) {
		$row  = array_pad( array_slice( $row, 0, count( $header ) ), count( $header ), '' );
		$data = array_combine( $header, $row );
		$sku  = trim( (string) ( $data['SKU'] ?? '' ) );
		if ( '' === $sku ) {
			continue;
		}
		$rows[ $sku ] = $data;
	}

	fclose( $handle );
	return $rows;
}

/**
 * Write rows back out in the canonical column order.
 *
 * Written to a temporary file and renamed into place: a fatal midway through
 * would otherwise leave the catalogue - the only copy of this data outside the
 * client's own documents - truncated.
 *
 * @param string                                 $path Absolute path to write.
 * @param array<string, array<string, string>>   $rows SKU => row.
 * @return bool True on success.
 */
function sr_write_catalog_csv( $path, $rows ) {
	$columns = sr_catalog_columns();
	$tmp     = $path . '.tmp';

	$handle = fopen( $tmp, 'w' );
	if ( ! $handle ) {
		return false;
	}

	fwrite( $handle, "\xEF\xBB\xBF" );
	fputcsv( $handle, $columns );

	foreach ( $rows as $row ) {
		$line = array();
		foreach ( $columns as $column ) {
			$line[] = (string) ( $row[ $column ] ?? '' );
		}
		fputcsv( $handle, $line );
	}

	fclose( $handle );

	return rename( $tmp, $path );
}

/* -------------------------------------------------------------------------
 * Reporting
 *
 * Every stage collects what it could not resolve and writes it out at the end,
 * rather than trusting anyone to have scrolled back through the run log. The
 * blockers are repeated at the top: they are the ones that change what is on
 * the storefront, and they must not be buried under forty notes.
 * ---------------------------------------------------------------------- */

/**
 * The report accumulator.
 *
 * @return array<int, array{sku: string, level: string, message: string}>
 */
function &sr_report_entries() {
	static $entries = array();
	return $entries;
}

/**
 * Record one finding.
 *
 * @param string $sku     SKU it belongs to, or a section name for global notes.
 * @param string $level   'blocker' (needs a human decision), 'placeholder'
 *                        (filled with something deliberate), or 'note'.
 * @param string $message What happened, in plain words.
 * @return void
 */
function sr_report( $sku, $level, $message ) {
	$entries   = &sr_report_entries();
	$entries[] = array(
		'sku'     => (string) $sku,
		'level'   => (string) $level,
		'message' => (string) $message,
	);
}

/**
 * Write the accumulated findings to a markdown file.
 *
 * @param string $path  Absolute path to write.
 * @param string $title Report heading.
 * @return int Number of blockers recorded.
 */
function sr_report_write( $path, $title ) {
	$entries = sr_report_entries();

	$blockers = array_values(
		array_filter(
			$entries,
			function ( $entry ) {
				return 'blocker' === $entry['level'];
			}
		)
	);

	$out  = '# ' . $title . "\n\n";
	$out .= 'Generated ' . gmdate( 'Y-m-d H:i' ) . " UTC. Regenerated on every run.\n\n";

	if ( $blockers ) {
		$out .= "## Blockers — need a decision\n\n";
		foreach ( $blockers as $entry ) {
			$out .= '- **' . $entry['sku'] . '** — ' . $entry['message'] . "\n";
		}
		$out .= "\n";
	} else {
		$out .= "No blockers.\n\n";
	}

	$by_sku = array();
	foreach ( $entries as $entry ) {
		$by_sku[ $entry['sku'] ][] = $entry;
	}

	if ( $by_sku ) {
		$out .= "## Everything, by SKU\n\n";
		foreach ( $by_sku as $sku => $group ) {
			$out .= '### ' . $sku . "\n\n";
			foreach ( $group as $entry ) {
				$label = 'note' === $entry['level'] ? '' : strtoupper( $entry['level'] ) . ': ';
				$out  .= '- ' . $label . $entry['message'] . "\n";
			}
			$out .= "\n";
		}
	}

	file_put_contents( $path, $out );

	return count( $blockers );
}

/* -------------------------------------------------------------------------
 * WordPress-side seed helpers
 *
 * Only called from scripts running inside WordPress. Defined here so
 * seed-2-products.php and seed-5-catalog.php share one copy.
 * ---------------------------------------------------------------------- */

/**
 * Ensure every named term exists on a taxonomy.
 *
 * @param string        $taxonomy Taxonomy name.
 * @param array<string> $names    Term names.
 * @return array<string, int> Name => term id.
 */
function sr_seed_terms( $taxonomy, $names ) {
	$ids = array();
	foreach ( $names as $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			continue;
		}

		$term = term_exists( $name, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $name, $taxonomy );
		}
		if ( is_wp_error( $term ) ) {
			continue;
		}

		$ids[ $name ] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
	}
	return $ids;
}

/**
 * Build a product attribute backed by a global taxonomy.
 *
 * @param string        $taxonomy      Attribute taxonomy, e.g. 'pa_size'.
 * @param array<string> $options       Term names, in display order.
 * @param bool          $for_variation Whether variations are built from it.
 * @return WC_Product_Attribute
 */
function sr_seed_attr( $taxonomy, $options, $for_variation = true ) {
	$ids = sr_seed_terms( $taxonomy, $options );

	$attr = new WC_Product_Attribute();
	$attr->set_id( wc_attribute_taxonomy_id_by_name( str_replace( 'pa_', '', $taxonomy ) ) );
	$attr->set_name( $taxonomy );
	$attr->set_options( array_values( $ids ) );
	$attr->set_visible( true );
	$attr->set_variation( $for_variation );

	return $attr;
}

/**
 * File a product under its category, collection and lead time.
 *
 * @param int    $product_id      Product id.
 * @param string $category_slug   'formals' or 'bridals'.
 * @param string $collection_slug 'dhanak' or 'ujala'.
 * @param string $delivery        Lead time, e.g. '8-9 weeks'.
 * @return void
 */
function sr_seed_assign( $product_id, $category_slug, $collection_slug, $delivery ) {
	if ( $category_slug ) {
		wp_set_object_terms( $product_id, $category_slug, 'product_cat' );
	}
	if ( $collection_slug ) {
		wp_set_object_terms( $product_id, $collection_slug, 'sr_collection' );
	}
	if ( '' !== trim( (string) $delivery ) ) {
		update_post_meta( $product_id, '_sr_delivery_time', $delivery );
	}
}
