<?php
/**
 * Turn the atelier's raw product write-ups into the catalogue CSV.
 *
 * Run with: php tools/seed/parse-raw-catalog.php
 *
 * Reads every file in catalog/raw/ and rewrites
 * catalog/samina-rasul-product-catalog.csv, merging by SKU. The CSV is tracked
 * in git, so `git diff catalog/` is the review gate: a misread price shows up as
 * a diff line rather than as a wrong figure on a live storefront.
 *
 * Deliberately standalone - no WordPress. Nothing here touches the database, so
 * a bad parse costs a `git checkout` and nothing more.
 *
 * The write-ups look like this, and the shape repeats:
 *
 *     UJ-004(Formals)
 *
 *     Short description:Hand embellished Raw silk shirt paired with ...
 *     Long Description:
 *     Details
 *     Shirt Fabric: Raw Silk 80gm (with lining)
 *      Shalwar Fabric: Raw Silk 80gm (Plain Dyed, Traditional Style)
 *     Design & Embellishment:
 *      An exquisite hand-embellished raw silk shirt, ...
 *     Customization:
 *      Colour, fabric, and size can be customized to your preference.
 *     Delivery Time:
 *      Each piece is custom-made. Please allow 8-9 weeks for delivery.
 *     Price: 96,500
 *     Sheesha silk: 80,000
 *
 * @package samina-rasul
 */

require_once __DIR__ . '/seed-lib.php';

$root       = dirname( __DIR__, 2 );
$raw_dir    = $root . '/catalog/raw';
$csv_path   = $root . '/catalog/samina-rasul-product-catalog.csv';
$report_out = $root . '/catalog/parse-report.md';

/* -------------------------------------------------------------------------
 * Section headings
 *
 * The write-ups mark sections with a heading that looks exactly like the
 * "Shirt Fabric: value" spec lines it contains, so the two are told apart by
 * this list and nothing else. Anything not listed here is content.
 * ---------------------------------------------------------------------- */
$sr_sections = array(
	'short description'        => 'short',
	'short desc'               => 'short',
	'long description'         => 'skip',
	'long desc'                => 'skip',
	'details'                  => 'details',
	'detail'                   => 'details',
	'design & embellishment'   => 'design',
	'design and embellishment' => 'design',
	'design'                   => 'design',
	'embellishment'            => 'design',
	'customization'            => 'terms',
	'customisation'            => 'terms',
	'delivery time'            => 'delivery',
	'delivery'                 => 'delivery',
	'price'                    => 'price',
	'pricing'                  => 'price',
	'optional add-on'          => 'addon',
	'optional add on'          => 'addon',
	'optional addon'           => 'addon',
	'add-on'                   => 'addon',
	'note'                     => 'terms',
);

/**
 * The house terms, repeated verbatim on every write-up.
 *
 * These are not per-product copy: sr_atelier_note() in the theme already states
 * the customisation promise, the lead time and the deposit above the cart. Kept
 * here so a product whose terms genuinely differ can be spotted and reported
 * rather than silently flattened into the boilerplate.
 */
/**
 * Is this terms line the house boilerplate, however it happens to be phrased?
 *
 * The write-ups say the same three things twenty-three times over in twenty-
 * three slightly different sentences - "All our dresses are custom-made",
 * "Each piece is made to order", "As all dresses are made to order". Matching
 * on exact strings turns every one of those into a false report, which buries
 * the deviation that actually matters. So this matches on substance.
 *
 * @param string $line Terms line.
 * @return bool
 */
function sr_is_house_terms( $line ) {
	$line = strtolower( (string) $line );

	$customisable = ( false !== strpos( $line, 'customiz' ) || false !== strpos( $line, 'customis' ) )
		&& ( false !== strpos( $line, 'fabric' ) || false !== strpos( $line, 'size' ) || false !== strpos( $line, 'color' ) || false !== strpos( $line, 'colour' ) );

	$price_caveat = false !== strpos( $line, 'prices may vary' ) || false !== strpos( $line, 'price may vary' );

	$lead_time = ( false !== strpos( $line, 'made to order' ) || false !== strpos( $line, 'custom-made' ) || false !== strpos( $line, 'custom made' ) )
		&& ( false !== strpos( $line, 'week' ) || false !== strpos( $line, 'delivery' ) );

	return $customisable || $price_caveat || $lead_time;
}

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * Put every heading and spec key on a line of its own.
 *
 * The Word document does not keep one field per paragraph. Whole products
 * arrive as a single line:
 *
 *     Short description:...embellished laceLong Description: Details Shirt
 *     Fabric: Korean Raw Silk Pants Fabric: Korean Raw Silk (Flared) Dupatta:
 *     Mukesh-embellished Bamber Chiffon
 *
 * A line-based parser reads all of that as the short description and finds no
 * specs at all. Splitting before every known "Key:" recovers the structure -
 * and the key is what ends the previous field's value, which is why the split
 * has to happen before anything else looks at the text.
 *
 * A colon is required, so the word "dupatta" inside a sentence is left alone
 * and only "Dupatta:" starts a new line. No leading word boundary is required,
 * because the document really does run words together ("laceLong Description").
 *
 * @param string $text        Raw document text.
 * @param array  $section_map Section headings to split on.
 * @return string Text with one field per line.
 */
function sr_split_inline_keys( $text, $section_map ) {
	$keys = array_keys( $section_map );

	foreach ( array_keys( sr_garment_keys() ) as $garment ) {
		$keys[] = $garment;
		$keys[] = $garment . ' fabric';
	}

	// Longest first, so "shirt fabric" wins the match over "shirt" and the word
	// "Fabric" is not left stranded at the head of the value.
	usort(
		$keys,
		function ( $a, $b ) {
			return strlen( $b ) <=> strlen( $a );
		}
	);

	$alternation = implode( '|', array_map( 'preg_quote', $keys ) );

	return (string) preg_replace(
		'/(?!^)(' . $alternation . ')(\s*):/iu',
		"\n$1:",
		$text
	);
}

/**
 * Read one raw file as plain text.
 *
 * .docx is a zip with the text in word/document.xml. Paragraph ends have to be
 * turned into newlines *before* the tags are stripped, or the whole document
 * arrives as one line and every section heading merges into its content.
 *
 * @param string $path File path.
 * @return string Text, or '' when it could not be read.
 */
function sr_read_raw_file( $path ) {
	$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

	if ( 'docx' !== $ext ) {
		return (string) file_get_contents( $path );
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		fwrite( STDERR, "SKIP $path: PHP has no ZipArchive, cannot read .docx. Save it as .txt.\n" );
		return '';
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		fwrite( STDERR, "SKIP $path: not a readable .docx.\n" );
		return '';
	}

	$xml = $zip->getFromName( 'word/document.xml' );
	$zip->close();

	if ( false === $xml ) {
		return '';
	}

	$xml = preg_replace( '/<\/w:p>/', "\n", $xml );
	$xml = preg_replace( '/<w:br\s*\/?>/', "\n", $xml );

	return html_entity_decode( strip_tags( $xml ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
}

/**
 * Pull a price off the end of a line.
 *
 * Every form the write-ups use ends the same way - with the figure:
 *
 *     Price: 96,500                                        -> 96500, no label
 *     Price: 96,500(as shown in picture)                   -> 96500, no label
 *     Sheesha silk : 80,000                                -> 80000, "Sheesha silk"
 *     Raw silk, Dupatta with minimal ( no borders) 69,500  -> 69500, that label
 *
 * A line that does not end in a figure is not a price. That matters: UJ-002's
 * write-up ends with the instruction "Price will be written as (80000-96500) on
 * main page as range", which carries two numbers and must never be read as an
 * option.
 *
 * A "+" in front of the figure changes its meaning completely:
 *
 *     Raw silk 80gm +16500
 *
 * is an upgrade fee added to the price above it, not a price of 16,500. Read as
 * an absolute price it would put a PKR 16,500 variation on a PKR 56,000 outfit.
 *
 * @param string $line Raw line, with any leading "Price:" already stripped.
 * @return array{label: string, price: int, additive: bool}|null Null when the
 *         line holds no price.
 */
function sr_parse_price_line( $line ) {
	$line = trim( (string) $line );
	if ( '' === $line ) {
		return null;
	}

	// A trailing parenthetical is a note to the reader ("(as shown in picture)"),
	// not part of the figure. Drop it before looking for the number.
	$line = preg_replace( '/\([^)]*\)\s*$/u', '', $line );
	$line = trim( $line );

	if ( ! preg_match( '/^(.*?)([\d][\d,\.\s]*)$/u', $line, $m ) ) {
		return null;
	}

	$digits = preg_replace( '/[^\d]/', '', $m[2] );
	if ( '' === $digits ) {
		return null;
	}

	// Four figures is the floor for a garment here; anything smaller is a stray
	// number caught off the end of a sentence, not a price.
	$price = (int) $digits;
	if ( $price < 1000 ) {
		return null;
	}

	$label    = rtrim( (string) $m[1] );
	$additive = ( '' !== $label && '+' === substr( $label, -1 ) );
	if ( $additive ) {
		$label = rtrim( substr( $label, 0, -1 ) );
	}
	$label = trim( (string) preg_replace( '/[\s:\-–—,]+$/u', '', $label ) );

	return array(
		'label'    => $label,
		'price'    => $price,
		'additive' => $additive,
	);
}

/**
 * Strip parentheticals and trailing punctuation off a fabric name so it reads
 * as an option label: "Raw Silk 80gm (with lining)" -> "Raw Silk 80gm".
 *
 * @param string $value Fabric value from a spec line.
 * @return string
 */
function sr_fabric_label( $value ) {
	$value = preg_replace( '/\([^)]*\)/u', '', (string) $value );
	$value = preg_replace( '/\s+/u', ' ', $value );

	return trim( $value, " \t\n\r\0\x0B,;-" );
}

/* -------------------------------------------------------------------------
 * Read the raw files
 * ---------------------------------------------------------------------- */

if ( ! is_dir( $raw_dir ) ) {
	fwrite( STDERR, "No $raw_dir directory. Create it and drop the write-ups in.\n" );
	exit( 1 );
}

$files = array();
foreach ( array( 'txt', 'md', 'markdown', 'docx' ) as $ext ) {
	$files = array_merge( $files, (array) glob( $raw_dir . '/*.' . $ext ) );
}
sort( $files );

if ( ! $files ) {
	fwrite( STDERR, "No .txt/.md/.docx files in $raw_dir.\n" );
	exit( 1 );
}

$text = '';
foreach ( $files as $file ) {
	$text .= "\n" . sr_read_raw_file( $file ) . "\n";
	echo 'read ' . basename( $file ) . "\n";
}

// Normalise line endings and non-breaking spaces once, here, so no later regex
// has to care which word processor the text came out of.
$text = str_replace( array( "\r\n", "\r", "\xC2\xA0" ), array( "\n", "\n", ' ' ), $text );

// Recover the structure Word threw away before anything reads the text.
$text = sr_split_inline_keys( $text, $sr_sections );

/* -------------------------------------------------------------------------
 * Split into SKU blocks
 *
 * The header is the only reliable record separator: "UJ-004(Formals)", with any
 * amount of space before the bracket and any casing inside it.
 * ---------------------------------------------------------------------- */

$pattern = '/^[ \t]*([A-Z]{2}-\d{3,4})[ \t]*\(\s*(formals?|bridals?)\s*\)[ \t]*$/im';

if ( ! preg_match_all( $pattern, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
	fwrite( STDERR, "No \"SKU(Category)\" headers found. Expected lines like: UJ-004(Formals)\n" );
	exit( 1 );
}

$blocks = array();
$count  = count( $matches[0] );
for ( $i = 0; $i < $count; $i++ ) {
	$sku   = strtoupper( $matches[1][ $i ][0] );
	$start = $matches[0][ $i ][1] + strlen( $matches[0][ $i ][0] );
	$end   = ( $i + 1 < $count ) ? $matches[0][ $i + 1 ][1] : strlen( $text );

	$blocks[ $sku ] = array(
		'category' => ucfirst( rtrim( strtolower( $matches[2][ $i ][0] ), 's' ) ) . 's',
		'body'     => substr( $text, $start, $end - $start ),
	);
}

/* -------------------------------------------------------------------------
 * Parse each block
 * ---------------------------------------------------------------------- */

$existing = sr_read_catalog_csv( $csv_path );
$rows     = $existing;

foreach ( $blocks as $sku => $block ) {
	$state    = 'none';
	$short    = '';
	$details  = array();
	$design   = array();
	$delivery = '';
	$prices   = array();

	foreach ( explode( "\n", $block['body'] ) as $line ) {
		$line = rtrim( $line );
		if ( '' === trim( $line ) ) {
			continue;
		}

		$parts = explode( ':', $line, 2 );
		$key   = sr_norm_key( $parts[0] );

		// A garment spec line is recognised wherever it appears, rather than
		// only under a "Details" heading. Word drops that heading into the
		// middle of another line often enough that relying on it loses the
		// whole spec table.
		if ( count( $parts ) > 1 ) {
			$spec = sr_resolve_spec_key( $parts[0] );
			if ( $spec && '' !== trim( $parts[1] ) ) {
				$details[] = array(
					'spec'  => $spec,
					'key'   => trim( $parts[0] ),
					'value' => trim( $parts[1] ),
				);
				continue;
			}
		}

		// A heading may carry its content on the same line
		// ("Short description:Hand embellished ..."), so the key is split off
		// first and whatever follows is handled as content of the new section.
		if ( isset( $sr_sections[ $key ] ) ) {
			$state   = $sr_sections[ $key ];
			$content = isset( $parts[1] ) ? trim( $parts[1] ) : '';

			// "Details" carries no colon; the line is the heading and nothing else.
			if ( 1 === count( $parts ) ) {
				$content = '';
			}

			// A heading with nothing but punctuation after it is still an empty
			// heading - "Customization:=" is in the supplied write-ups, and the
			// stray "=" must not be carried through as content.
			if ( ! preg_match( '/[\p{L}\d]/u', $content ) ) {
				continue;
			}
		} else {
			$content = trim( $line );

			if ( count( $parts ) > 1 && ! in_array( $state, array( 'design', 'terms', 'delivery', 'addon', 'price' ), true ) ) {
				sr_report( $sku, 'note', 'Unrecognised "' . trim( $parts[0] ) . ':" line, kept as prose: "' . $content . '"' );
			}
		}

		switch ( $state ) {
			case 'short':
				$short .= ( '' === $short ? '' : ' ' ) . $content;
				break;

			case 'design':
			case 'addon':
				$design[] = $content;
				break;

			case 'delivery':
				if ( '' === $delivery && preg_match( '/(\d+\s*(?:[–—-]\s*\d+\s*)?(?:weeks?|days?|months?))/iu', $content, $m ) ) {
					$delivery = preg_replace( '/\s+/u', ' ', trim( $m[1] ) );
				}
				// fall through - the sentence itself is still terms copy.

			case 'terms':
				// The house terms are already on the page: sr_atelier_note()
				// states the customisation promise, the lead time and the deposit
				// above the cart. Repeating them in every description prints the
				// same sentences twice.
				//
				// Anything that is *not* those terms is real product copy - DK-008
				// explains that reworking the dupatta border costs extra - and
				// dropping it would lose information the shopper needs. It is kept
				// in the description and reported so it can be checked.
				if ( ! sr_is_house_terms( $content ) ) {
					$design[] = $content;
					sr_report( $sku, 'note', 'Not the house terms, so it was kept in the description rather than dropped: "' . $content . '"' );
				}
				break;

			case 'price':
				$parsed = sr_parse_price_line( $content );
				if ( $parsed ) {
					$prices[] = $parsed;
				} else {
					sr_report( $sku, 'note', 'Line under Price was not read as a figure and was ignored: "' . $content . '"' );
				}
				break;
		}
	}

	/* ---- Description cell: the summary line, the spec lines, then the prose ----
	 *
	 * The write-up's two blocks stay two fields, exactly as the document has
	 * them. The one-line "Short description" becomes the product's short
	 * description - what a card in a grid shows - and the "Design &
	 * Embellishment" prose becomes the full description, printed under the
	 * price on the product page.
	 */

	/*
	 * Hand-written copy wins over anything derived from the write-up.
	 *
	 * Some entries are a note to the team rather than copy for a customer
	 * ("This stunning article consists of..."), and rewriting them in the CSV
	 * would be undone by the next parse. A file per SKU is the durable place to
	 * put a rewrite, and it survives the client sending a new document. It
	 * replaces the prose, which is the block that gets read.
	 */
	$override = $root . '/catalog/descriptions/' . $sku . '.md';
	if ( is_readable( $override ) ) {
		$written = trim( (string) file_get_contents( $override ) );
		if ( '' !== $written ) {
			$design = array( $written );
			sr_report( $sku, 'note', 'Used the hand-written description from catalog/descriptions/' . $sku . '.md instead of the write-up.' );
		}
	}

	if ( '' === trim( $short ) ) {
		// Fall back to the first sentence of the prose rather than inventing
		// copy: it is the same garment, said by the same person.
		$first = $design ? trim( (string) preg_replace( '/(?<=\.)\s.*$/su', '', $design[0] ) ) : '';
		$short = '' !== $first ? $first : 'Hand-finished piece from the Samina Rasul atelier.';
		sr_report( $sku, 'placeholder', 'No short description in the write-up. Used ' . ( '' !== $first ? 'the first sentence of the prose.' : 'a placeholder.' ) );
	}

	$short = trim( (string) preg_replace( '/\s+/u', ' ', $short ) );

	$spec_lines = array();
	foreach ( $details as $detail ) {
		$spec_lines[] = $detail['key'] . ': ' . $detail['value'];

		if ( 'custom' === $detail['spec']['type'] ) {
			sr_report( $sku, 'note', 'No house attribute for "' . $detail['key'] . '"; it becomes a per-product spec row labelled "' . $detail['spec']['label'] . '".' );
		}
	}

	$description = array_merge( array( $short ), $spec_lines, array( '' ), $design );
	$description = trim( implode( "\n", $description ) );

	if ( ! $details ) {
		sr_report( $sku, 'note', 'No fabric detail lines found, so this product will have no spec table.' );
	}
	if ( ! $design ) {
		sr_report( $sku, 'note', 'No "Design & Embellishment" prose, so the product page will show no description under the price.' );
	}

	/* ---- Pricing ---- */

	$base     = '';
	$options  = '';
	$upgrades = array();

	$absolute = array();
	foreach ( $prices as $price ) {
		if ( $price['additive'] ) {
			$label      = '' !== $price['label'] ? $price['label'] : 'Fabric upgrade';
			$upgrades[] = $label . ' | ' . $price['price'];
		} else {
			$absolute[] = $price;
		}
	}

	if ( 1 === count( $absolute ) ) {
		$base = (string) $absolute[0]['price'];

		if ( '' !== $absolute[0]['label'] ) {
			sr_report( $sku, 'note', 'The price line was labelled "' . $absolute[0]['label'] . '". Only the figure was used; the label is already in the description.' );
		}
	} elseif ( count( $absolute ) > 1 ) {
		// Several absolute figures means several buildable versions of the piece,
		// each its own variation. Additive add-ons cannot express this - see
		// UJ-003, whose fabric delta is 16,500 on one dupatta and 15,500 on the
		// other - and only variations put a range on the shop card.
		$shirt_fabric = '';
		foreach ( $details as $detail ) {
			if ( 'taxonomy' === $detail['spec']['type'] && 'shirt-fabric' === $detail['spec']['slug'] ) {
				$shirt_fabric = sr_fabric_label( $detail['value'] );
				break;
			}
		}

		$lines = array();
		foreach ( $absolute as $price ) {
			$label = $price['label'];
			if ( '' === $label ) {
				// The unlabelled figure is the piece as photographed. Name it from
				// the shirt fabric so the option reads as a choice, not a blank.
				$label = '' !== $shirt_fabric ? $shirt_fabric . ' (as shown)' : 'As shown';
			}
			$lines[] = $label . ' | ' . $price['price'];
		}
		$options = implode( "\n", $lines );

		sr_report( $sku, 'note', count( $absolute ) . ' price options: ' . str_replace( "\n", ' // ', $options ) );
	}

	if ( $upgrades ) {
		sr_report( $sku, 'note', 'Fabric upgrade (added on top of the price, not a price of its own): ' . implode( ' // ', $upgrades ) );
	}

	/* ---- Merge into the row, preserving what the write-ups do not carry ---- */

	$row = isset( $rows[ $sku ] ) ? $rows[ $sku ] : array_fill_keys( sr_catalog_columns(), '' );

	$row['SKU']         = $sku;
	$row['Category']    = $block['category'];
	$row['Description'] = $description;

	if ( '' === trim( (string) $row['Product Name'] ) ) {
		$row['Product Name'] = sr_collection_for_sku( $sku ) . ' ' . $sku;
	}
	if ( '' === trim( (string) $row['Collection'] ) ) {
		$row['Collection'] = sr_collection_for_sku( $sku );
	}
	if ( '' === trim( (string) $row['Sizes (comma-separated)'] ) ) {
		$row['Sizes (comma-separated)'] = sr_catalog_sizes();
	}

	$row['Delivery Time'] = '' !== $delivery ? $delivery : ( trim( (string) $row['Delivery Time'] ) ?: sr_catalog_default_delivery() );

	// Only overwrite pricing when the write-up actually stated some, so a
	// re-parse of a partial batch cannot blank a figure already in the CSV.
	if ( '' !== $base || '' !== $options ) {
		$row['Base Price (PKR)']                          = $base;
		$row['Item Options (one per line: Label | price)'] = $options;
	} elseif ( '' === trim( (string) $row['Base Price (PKR)'] ) && '' === trim( (string) $row['Item Options (one per line: Label | price)'] ) ) {
		sr_report( $sku, 'blocker', 'No price anywhere - not in the write-up and not in the CSV.' );
	}

	if ( $upgrades ) {
		$row['Fabric Upgrades (one per line: Label | added fee)'] = implode( "\n", $upgrades );
	}

	$rows[ $sku ] = $row;
}

/* -------------------------------------------------------------------------
 * Deliberate placeholders
 *
 * Two values are filled rather than left blank, because a blank is worse: a
 * bridal with no price cannot be saved as a sane product, and an add-on whose
 * fee will not parse is silently dropped at checkout by fabric-addons.php:211.
 * Both are conspicuous and both are reported.
 * ---------------------------------------------------------------------- */

$bridal_prices = array();
foreach ( $rows as $row ) {
	if ( 0 !== strcasecmp( trim( (string) $row['Category'] ), 'Bridals' ) ) {
		continue;
	}
	$price = (int) preg_replace( '/[^\d]/', '', (string) $row['Base Price (PKR)'] );
	if ( $price > 0 ) {
		$bridal_prices[] = $price;
	}
}

sort( $bridal_prices );
$bridal_fallback = $bridal_prices
	? (int) ( round( $bridal_prices[ (int) floor( ( count( $bridal_prices ) - 1 ) / 2 ) ] / 500 ) * 500 )
	: 125000;

foreach ( $rows as $sku => $row ) {
	$is_bridal = 0 === strcasecmp( trim( (string) $row['Category'] ), 'Bridals' );
	$has_price = '' !== trim( (string) $row['Base Price (PKR)'] ) || '' !== trim( (string) $row['Item Options (one per line: Label | price)'] );

	if ( $is_bridal && ! $has_price ) {
		$rows[ $sku ]['Base Price (PKR)'] = (string) $bridal_fallback;
		sr_report(
			$sku,
			'placeholder',
			'No price supplied. Stored ' . number_format( $bridal_fallback ) . ', the median of the other bridals. '
			. 'Never shown publicly - bridal-flow.php replaces it with "Price on inquiry" - but replace it with the real figure.'
		);
	}

	// An add-on line whose fee is not a number would be stored as a 0 fee and
	// then skipped by fabric-addons.php:211 without a word. 9999 renders as
	// "+PKR 9,999" on the product page instead, where it cannot be missed.
	foreach ( array(
		'Fabric Upgrades (one per line: Label | added fee)',
		'Optional Extras (one per line: Label | added fee)',
	) as $column ) {
		$raw = trim( (string) $row[ $column ] );
		if ( '' === $raw ) {
			continue;
		}

		$fixed = array();
		foreach ( preg_split( '/\n/', $raw ) as $addon_line ) {
			$addon_line = trim( $addon_line );
			if ( '' === $addon_line ) {
				continue;
			}

			$bits  = array_map( 'trim', explode( '|', $addon_line ) );
			$label = $bits[0];
			$fee   = isset( $bits[1] ) ? preg_replace( '/[^\d]/', '', $bits[1] ) : '';

			if ( '' === $fee || 0 === (int) $fee ) {
				$fee = '9999';
				sr_report(
					$sku,
					'blocker',
					'Add-on "' . $label . '" has no usable fee. Stored 9999 so it shows as "+PKR 9,999" on the page. Replace it with the real fee.'
				);
			}

			$fixed[] = $label . ' | ' . $fee . ( isset( $bits[2] ) && '' !== $bits[2] ? ' | ' . $bits[2] : '' );
		}

		$rows[ $sku ][ $column ] = implode( "\n", $fixed );
	}
}

/* -------------------------------------------------------------------------
 * Write
 * ---------------------------------------------------------------------- */

ksort( $rows );

if ( ! sr_write_catalog_csv( $csv_path, $rows ) ) {
	fwrite( STDERR, "FAILED to write $csv_path\n" );
	exit( 1 );
}

$blockers = sr_report_write( $report_out, 'Catalogue parse report' );

echo "\n" . count( $blocks ) . ' write-ups parsed, ' . count( $rows ) . " rows in the catalogue.\n";
echo 'report: ' . $report_out . ' (' . $blockers . " blockers)\n";
echo "Review with: git diff catalog/\n";
