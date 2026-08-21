<?php
/**
 * Attach cover photographs and outfit films to their products.
 *
 * Two jobs, both keyed on SKU:
 *
 * 1. **Covers.** The client re-shot a hero frame for some pieces and named the
 *    file `<SKU>-cover.webp`. That image becomes the product's featured image —
 *    the one the card and the first gallery slide show. The photograph it
 *    replaces is *not* discarded: it moves to the front of the gallery, because
 *    the importer's convention is that image `-1` is the featured one and
 *    nothing else references it, so a straight swap would strand it.
 *
 * 2. **Films.** `<SKU>.mp4`, already encoded for the web, becomes
 *    `_sr_product_video` — the first gallery slide (samina-core/product-fields.php).
 *
 * ## The zero-padding trap
 *
 * The filenames and the SKUs disagree about padding: the bridal pieces are
 * `DK-0010`…`DK-0014` in the catalogue but `DK-010`…`DK-012` on disk. Matching
 * on the string would silently skip five products and — worse — a careless
 * `ltrim` would map `DK-011` onto `DK-11`, which does not exist, rather than
 * onto `DK-0011`, which does. So both sides are parsed to (prefix, integer) and
 * matched on that, and the script refuses to start if two SKUs collapse to the
 * same pair.
 *
 * Idempotent. Each product records the source filename it was given; a second
 * run with the same file does nothing, and a run with a *different* file
 * replaces the attachment.
 *
 * Usage, from the WordPress root:
 *   wp eval-file seed-9-media.php --sr-media=/absolute/path/to/staged/media
 *
 * The staging directory should contain the cover images and the .mp4 files
 * flat, exactly as named above.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through wp eval-file.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/** Where the staged media lives. Passed as --sr-media=… or via the environment. */
$sr_media_dir = '';
foreach ( (array) ( $args ?? array() ) as $sr_arg ) {
	if ( 0 === strpos( (string) $sr_arg, '--sr-media=' ) ) {
		$sr_media_dir = substr( (string) $sr_arg, 11 );
	}
}
if ( '' === $sr_media_dir ) {
	$sr_media_dir = (string) getenv( 'SR_MEDIA_DIR' );
}
$sr_media_dir = rtrim( $sr_media_dir, '/' );

if ( '' === $sr_media_dir || ! is_dir( $sr_media_dir ) ) {
	fwrite( STDERR, "Set SR_MEDIA_DIR (or pass --sr-media=…) to the staged media directory.\n" );
	exit( 1 );
}

/**
 * (prefix, number) for a SKU or a filename stem, or null when it is neither.
 *
 * @param string $value e.g. 'DK-0011', 'DK-011-cover', 'UJ-003'.
 * @return string|null Normalised key, e.g. 'DK|11'.
 */
function sr_media_key( $value ) {
	if ( ! preg_match( '/^([A-Za-z]{2})-0*(\d+)(?:-cover)?$/', trim( (string) $value ), $m ) ) {
		return null;
	}

	return strtoupper( $m[1] ) . '|' . (int) $m[2];
}

/* -------------------------------------------------------------------------
 * Index every product by its normalised key
 * ---------------------------------------------------------------------- */

$sr_products = get_posts(
	array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
	)
);

$sr_index = array();

foreach ( $sr_products as $sr_id ) {
	$sr_sku = (string) get_post_meta( $sr_id, '_sku', true );
	$sr_key = sr_media_key( $sr_sku );

	if ( null === $sr_key ) {
		continue;
	}

	// Two SKUs that normalise to the same pair would make every match ambiguous.
	if ( isset( $sr_index[ $sr_key ] ) ) {
		fwrite(
			STDERR,
			sprintf(
				"FAIL: '%s' and '%s' both normalise to %s. Resolve the SKUs before running this.\n",
				get_post_meta( $sr_index[ $sr_key ], '_sku', true ),
				$sr_sku,
				$sr_key
			)
		);
		exit( 1 );
	}

	$sr_index[ $sr_key ] = $sr_id;
}

printf( "Indexed %d published products.\n", count( $sr_index ) );

/**
 * Import a file into the media library and return its attachment id.
 *
 * @param string $path      Absolute path to the source file.
 * @param int    $parent_id Product to attach it to.
 * @return int|WP_Error
 */
function sr_sideload( $path, $parent_id ) {
	if ( ! is_readable( $path ) ) {
		return new WP_Error( 'sr_unreadable', sprintf( 'Cannot read %s', $path ) );
	}

	// media_handle_sideload() moves the file it is given, so it gets a copy.
	$tmp = wp_tempnam( basename( $path ) );
	if ( ! $tmp || ! copy( $path, $tmp ) ) {
		return new WP_Error( 'sr_copy', sprintf( 'Could not stage %s', basename( $path ) ) );
	}

	$file = array(
		'name'     => basename( $path ),
		'tmp_name' => $tmp,
	);

	$attachment_id = media_handle_sideload( $file, $parent_id );

	if ( is_wp_error( $attachment_id ) && file_exists( $tmp ) ) {
		wp_delete_file( $tmp );
	}

	return $attachment_id;
}

/* -------------------------------------------------------------------------
 * Covers
 * ---------------------------------------------------------------------- */

$sr_cover_files = glob( $sr_media_dir . '/*-cover.*' );
$sr_cover_files = is_array( $sr_cover_files ) ? $sr_cover_files : array();
sort( $sr_cover_files, SORT_STRING );

$sr_covers_set = 0;
$sr_covers_skipped = 0;

echo "\nCovers\n";

foreach ( $sr_cover_files as $sr_file ) {
	$sr_name = basename( $sr_file );
	$sr_key  = sr_media_key( pathinfo( $sr_name, PATHINFO_FILENAME ) );

	if ( null === $sr_key || ! isset( $sr_index[ $sr_key ] ) ) {
		printf( "  ?     %-26s no product for this name\n", $sr_name );
		continue;
	}

	$sr_id      = $sr_index[ $sr_key ];
	$sr_product = wc_get_product( $sr_id );
	$sr_sku     = $sr_product->get_sku();

	if ( (string) get_post_meta( $sr_id, '_sr_cover_source', true ) === $sr_name
		&& $sr_product->get_image_id()
	) {
		printf( "  =     %-26s %s already set\n", $sr_name, $sr_sku );
		$sr_covers_skipped++;
		continue;
	}

	$sr_attachment = sr_sideload( $sr_file, $sr_id );

	if ( is_wp_error( $sr_attachment ) ) {
		printf( "  FAIL  %-26s %s\n", $sr_name, $sr_attachment->get_error_message() );
		continue;
	}

	$sr_previous = (int) $sr_product->get_image_id();
	$sr_gallery  = $sr_product->get_gallery_image_ids();

	/*
	 * The outgoing featured image is kept, at the front of the gallery. The
	 * importer's convention is that `-1` is the featured shot and nothing else
	 * points at it, so swapping without this would lose a photograph.
	 */
	if ( $sr_previous > 0 && $sr_previous !== (int) $sr_attachment && ! in_array( $sr_previous, $sr_gallery, true ) ) {
		array_unshift( $sr_gallery, $sr_previous );
		$sr_product->set_gallery_image_ids( $sr_gallery );
	}

	$sr_product->set_image_id( (int) $sr_attachment );
	$sr_product->update_meta_data( '_sr_cover_source', $sr_name );
	$sr_product->save();

	printf( "  OK    %-26s %-8s featured #%d (was #%d)\n", $sr_name, $sr_sku, $sr_attachment, $sr_previous );
	$sr_covers_set++;
}

/* -------------------------------------------------------------------------
 * Films
 * ---------------------------------------------------------------------- */

$sr_video_files = glob( $sr_media_dir . '/*.mp4' );
$sr_video_files = is_array( $sr_video_files ) ? $sr_video_files : array();
sort( $sr_video_files, SORT_STRING );

$sr_videos_set = 0;
$sr_videos_skipped = 0;

echo "\nFilms\n";

foreach ( $sr_video_files as $sr_file ) {
	$sr_name = basename( $sr_file );
	$sr_key  = sr_media_key( pathinfo( $sr_name, PATHINFO_FILENAME ) );

	if ( null === $sr_key || ! isset( $sr_index[ $sr_key ] ) ) {
		printf( "  ?     %-26s no product for this name\n", $sr_name );
		continue;
	}

	$sr_id  = $sr_index[ $sr_key ];
	$sr_sku = (string) get_post_meta( $sr_id, '_sku', true );

	$sr_existing = function_exists( 'sr_product_video_id' ) ? sr_product_video_id( $sr_id ) : 0;

	if ( (string) get_post_meta( $sr_id, '_sr_video_source', true ) === $sr_name && $sr_existing ) {
		printf( "  =     %-26s %s already set\n", $sr_name, $sr_sku );
		$sr_videos_skipped++;
		continue;
	}

	$sr_attachment = sr_sideload( $sr_file, $sr_id );

	if ( is_wp_error( $sr_attachment ) ) {
		printf( "  FAIL  %-26s %s\n", $sr_name, $sr_attachment->get_error_message() );
		continue;
	}

	if ( 0 !== strpos( (string) get_post_mime_type( $sr_attachment ), 'video/' ) ) {
		printf( "  FAIL  %-26s imported as %s, not a video\n", $sr_name, get_post_mime_type( $sr_attachment ) );
		continue;
	}

	update_post_meta( $sr_id, '_sr_product_video', (int) $sr_attachment );
	update_post_meta( $sr_id, '_sr_video_source', $sr_name );

	printf( "  OK    %-26s %-8s video #%d\n", $sr_name, $sr_sku, $sr_attachment );
	$sr_videos_set++;
}

/* -------------------------------------------------------------------------
 * Report
 * ---------------------------------------------------------------------- */

if ( function_exists( 'wc_delete_product_transients' ) ) {
	foreach ( $sr_index as $sr_id ) {
		wc_delete_product_transients( $sr_id );
	}
}

printf(
	"\nCovers: %d set, %d already current.\nFilms:  %d set, %d already current.\n",
	$sr_covers_set,
	$sr_covers_skipped,
	$sr_videos_set,
	$sr_videos_skipped
);

$sr_without_video = array();
foreach ( $sr_index as $sr_key => $sr_id ) {
	$sr_has = function_exists( 'sr_product_video_id' ) ? sr_product_video_id( $sr_id ) : (int) get_post_meta( $sr_id, '_sr_product_video', true );
	if ( ! $sr_has ) {
		$sr_without_video[] = get_post_meta( $sr_id, '_sku', true );
	}
}

if ( $sr_without_video ) {
	sort( $sr_without_video, SORT_STRING );
	printf( "No film yet: %s\n", implode( ', ', $sr_without_video ) );
}
