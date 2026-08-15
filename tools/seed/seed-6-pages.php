<?php
/**
 * Seed: content pages, from catalog/pages.json.
 *
 * Run with: wp eval-file tools/seed/seed-6-pages.php
 *
 * The counterpart to export-pages.php. Matches on slug, so a page that already
 * exists is updated in place and keeps its ID, its menu entries and any links
 * pointing at it. Nothing is ever deleted: a page on the server that is not in
 * the file is left exactly as it is.
 *
 * This is what puts the policy pages on the live store. They were written here,
 * live in the database, and a code deploy carries no database.
 *
 * @package samina-rasul
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$sr_file = dirname( __DIR__, 2 ) . '/catalog/pages.json';

if ( ! is_readable( $sr_file ) ) {
	WP_CLI::error( "No page export at $sr_file. Run export-pages.php first." );
}

$sr_pages = json_decode( (string) file_get_contents( $sr_file ), true );

if ( ! is_array( $sr_pages ) || ! $sr_pages ) {
	WP_CLI::error( 'catalog/pages.json is empty or not valid JSON.' );
}

$sr_created = 0;
$sr_updated = 0;

foreach ( $sr_pages as $sr_page ) {
	$sr_slug = isset( $sr_page['slug'] ) ? sanitize_title( $sr_page['slug'] ) : '';
	if ( '' === $sr_slug ) {
		WP_CLI::warning( 'A row has no slug - skipped.' );
		continue;
	}

	/*
	 * wp_insert_post() runs the content through KSES for a user without
	 * unfiltered_html, which strips the block comments the editor needs. Under
	 * WP-CLI there is no user at all, so the filters are removed explicitly and
	 * the content lands exactly as it was exported.
	 *
	 * Safe here and nowhere else: the input is a file from this repository, not
	 * anything a visitor can reach.
	 */
	kses_remove_filters();

	$sr_data = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $sr_slug,
		'post_title'   => isset( $sr_page['title'] ) ? sanitize_text_field( $sr_page['title'] ) : $sr_slug,
		'post_content' => isset( $sr_page['content'] ) ? (string) $sr_page['content'] : '',
		'post_excerpt' => isset( $sr_page['excerpt'] ) ? (string) $sr_page['excerpt'] : '',
		'menu_order'   => isset( $sr_page['menu'] ) ? (int) $sr_page['menu'] : 0,
	);

	$sr_existing = get_page_by_path( $sr_slug );

	if ( $sr_existing instanceof WP_Post ) {
		$sr_data['ID'] = $sr_existing->ID;
		$sr_id         = wp_update_post( $sr_data, true );
		$sr_verb       = 'updated';
	} else {
		$sr_id   = wp_insert_post( $sr_data, true );
		$sr_verb = 'created';
	}

	kses_init_filters();

	if ( is_wp_error( $sr_id ) ) {
		WP_CLI::warning( "/$sr_slug/: " . $sr_id->get_error_message() );
		continue;
	}

	// A page template is a file in the theme; only set it when that file is
	// actually there, or the page renders blank on a server mid-deploy.
	$sr_template = isset( $sr_page['template'] ) ? (string) $sr_page['template'] : '';
	if ( '' !== $sr_template && locate_template( $sr_template ) ) {
		update_post_meta( $sr_id, '_wp_page_template', $sr_template );
	}

	if ( 'created' === $sr_verb ) {
		++$sr_created;
	} else {
		++$sr_updated;
	}

	WP_CLI::log( "$sr_verb: /$sr_slug/ (#$sr_id)" );
}

WP_CLI::success( "$sr_created created, $sr_updated updated." );
