<?php
/**
 * Deploy hook: flush OPcache after the theme/mu-plugin directories are swapped.
 *
 * Two things this deliberately does NOT do:
 *
 * - Run at file scope. The loader in mu-plugins/samina-core.php requires every
 *   module on every request; work at load time is work every visitor pays for.
 *   The handler only exists once WordPress says so.
 * - Take the secret from the query string. A token in the URL lands in the
 *   web server's access log, in any CDN or proxy log in front of it, and in
 *   Referer headers. It arrives in a header instead, which none of those record.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle a reset request, if this is one.
 *
 * Answers only to POST /?sr_opcache_reset=1 carrying an X-SR-Deploy-Token
 * header that matches SR_OPCACHE_SECRET from wp-config.php.
 *
 * @return void
 */
function sr_maybe_reset_opcache() {
	if ( ! isset( $_GET['sr_opcache_reset'] ) || '1' !== $_GET['sr_opcache_reset'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- authenticated by shared secret below.
		return;
	}

	// GET is for reading. A cache flush is a side effect, and a GET endpoint is
	// one a crawler or a link prefetcher can fire on its own.
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '' ) ) {
		status_header( 405 );
		header( 'Allow: POST' );
		exit( 'Method Not Allowed' );
	}

	$token = isset( $_SERVER['HTTP_X_SR_DEPLOY_TOKEN'] ) ? wp_unslash( $_SERVER['HTTP_X_SR_DEPLOY_TOKEN'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- compared byte-for-byte, never printed or stored.

	/*
	 * is_string() before hash_equals(): it throws a TypeError on anything else,
	 * which would turn a malformed request into a fatal 500.
	 */
	if ( ! defined( 'SR_OPCACHE_SECRET' ) || '' === (string) SR_OPCACHE_SECRET || ! is_string( $token ) || ! hash_equals( (string) SR_OPCACHE_SECRET, $token ) ) {
		status_header( 403 );
		exit( 'Forbidden' );
	}

	if ( function_exists( 'opcache_reset' ) ) {
		opcache_reset();
	}

	status_header( 200 );
	exit( 'OPcache reset successful' );
}
add_action( 'muplugins_loaded', 'sr_maybe_reset_opcache' );
