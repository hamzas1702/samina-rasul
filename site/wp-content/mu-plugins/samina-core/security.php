<?php
/**
 * Hardening.
 *
 * A shop that takes bank transfers and stores customers' payment receipts is
 * worth attacking, and the store shipped with a handful of doors open by
 * default. What this file closes, in order of how much it mattered:
 *
 * 1. **User enumeration.** `/wp-json/wp/v2/users` published `{"id":1,
 *    "name":"<the owner's email address>", "is_super_admin":true}` — a valid
 *    login identifier, confirmation of which account is the administrator, and
 *    a `?author=1` redirect that leaked the same thing again through the
 *    author archive. Half of a credential-stuffing attempt, handed over.
 * 2. **XML-RPC.** Open, which is a brute-force amplifier (`system.multicall`
 *    tries hundreds of passwords in one request) and a pingback reflection
 *    vector. Nothing on this site uses it.
 * 3. **No transport or framing headers.** No HSTS, no `X-Frame-Options`, no
 *    `X-Content-Type-Options`, no `Referrer-Policy`.
 * 4. **No brute-force limit** on wp-login, and none on password resets, which
 *    doubles as an enumeration oracle and a way to spam a customer's inbox.
 *
 * Deliberately not here: CSRF tokens (WordPress nonces already cover every
 * form this theme adds, including the receipt upload), server-side pricing
 * (WooCommerce reads the price from the product, never the request — see
 * tools/qa/test-addon-pricing.php), and payment webhook verification (bank
 * transfer is reconciled by hand; there is no webhook).
 *
 * @package samina-core
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1 · Response headers
 * ---------------------------------------------------------------------- */

/**
 * Transport and framing headers.
 *
 * Sent on the front end and on admin/login alike — an admin session is exactly
 * what an attacker wants framed or downgraded.
 */
function sr_security_headers( $headers ) {
	// HSTS only over TLS. Sent on plain HTTP it is ignored by browsers and, if
	// the site were ever served without TLS, would be a footgun rather than a
	// protection.
	if ( is_ssl() ) {
		$headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
	}

	// SAMEORIGIN rather than DENY: WooCommerce and the customiser both frame
	// their own pages.
	$headers['X-Frame-Options']        = 'SAMEORIGIN';
	$headers['X-Content-Type-Options'] = 'nosniff';

	// Send the origin cross-site, the full path same-site. Payment references
	// and order keys live in URLs here, and they should not travel to a third
	// party in a Referer.
	$headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';

	// Nothing on this store needs any of these.
	$headers['Permissions-Policy'] = 'geolocation=(), microphone=(), camera=(), payment=(), usb=(), interest-cohort=()';

	return $headers;
}
add_filter( 'wp_headers', 'sr_security_headers' );

/**
 * The same headers on wp-admin and wp-login, which do not run through
 * `wp_headers`.
 */
add_action( 'admin_init', function () {
	foreach ( sr_security_headers( array() ) as $name => $value ) {
		if ( ! headers_sent() ) {
			header( $name . ': ' . $value );
		}
	}
} );
add_action( 'login_init', function () {
	foreach ( sr_security_headers( array() ) as $name => $value ) {
		if ( ! headers_sent() ) {
			header( $name . ': ' . $value );
		}
	}
} );

/** Stop advertising the exact PHP and WordPress versions to every visitor. */
add_action( 'init', function () {
	if ( ! headers_sent() ) {
		header_remove( 'X-Powered-By' );
	}
} );
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

/* -------------------------------------------------------------------------
 * 2 · User enumeration
 * ---------------------------------------------------------------------- */

/**
 * The REST users endpoint, for anyone who cannot already list users in wp-admin.
 *
 * Not removed outright: the block editor asks for it when assigning an author,
 * so it is gated on the same capability wp-admin gates the user list on. A
 * logged-in editor sees exactly what they always did; the public sees nothing.
 */
add_filter( 'rest_authentication_errors', function ( $result ) {
	if ( ! empty( $result ) ) {
		return $result;
	}

	$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the route, not acting on input.
	if ( '' === $route && isset( $_SERVER['REQUEST_URI'] ) ) {
		$route = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
	}

	if ( ! preg_match( '#/wp/v2/users#', $route ) ) {
		return $result;
	}

	if ( current_user_can( 'list_users' ) ) {
		return $result;
	}

	return new WP_Error(
		'rest_user_cannot_view',
		__( 'Sorry, you are not allowed to list users.', 'samina' ),
		array( 'status' => rest_authorization_required_code() )
	);
} );

/**
 * `?author=1`, which core answers with a 301 to the author archive — and the
 * archive slug is derived from the account's login or display name.
 */
add_action( 'template_redirect', function () {
	if ( is_admin() || is_user_logged_in() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public query var, not an action.
	$probing_author = isset( $_GET['author'] ) && '' !== $_GET['author'];

	if ( $probing_author || is_author() ) {
		sr_security_log( 'author-enumeration', array( 'uri' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) );
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 0 );

/** Author archives serve no purpose on a shop with one account. */
add_filter( 'author_rewrite_rules', '__return_empty_array' );

/** oEmbed hands back the author name and URL for any post. */
add_filter( 'oembed_response_data', function ( $data ) {
	unset( $data['author_name'], $data['author_url'] );
	return $data;
} );

/** The RSD/wlwmanifest links only advertise remote-publishing endpoints. */
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );

/* -------------------------------------------------------------------------
 * 3 · XML-RPC
 * ---------------------------------------------------------------------- */

add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );

/** Refuse the request outright rather than letting it reach the parser. */
add_action( 'init', function () {
	if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
		return;
	}
	sr_security_log( 'xmlrpc-blocked', array() );
	wp_die( 'XML-RPC is disabled on this site.', '', array( 'response' => 403 ) );
}, 1 );

/* -------------------------------------------------------------------------
 * 4 · Login and password-reset throttling
 * ---------------------------------------------------------------------- */

/** Failed logins allowed from one IP before it is locked out. */
const SR_LOGIN_MAX_ATTEMPTS = 5;

/** How long the lockout lasts, and the window failures are counted over. */
const SR_LOGIN_LOCKOUT_SECONDS = 900; // 15 minutes.

/**
 * The caller's IP, as far as it can be trusted.
 *
 * REMOTE_ADDR only. `X-Forwarded-For` is attacker-controlled unless the proxy
 * in front is known and strips it, so throttling on it would let anyone reset
 * their own counter by inventing a header — which is why this does not reuse
 * the theme's sr_client_ip(), which does trust the last forwarded entry. That
 * is a reasonable trade for labelling a contact-form submission and the wrong
 * one for a lockout that must not be resettable at will. Named apart from it
 * so the two cannot collide: mu-plugins load first, and a bare redeclaration
 * here takes the whole site down with a fatal in functions.php.
 *
 * @return string
 */
function sr_security_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';

	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Transient key for a throttle bucket.
 *
 * The IP is hashed so the options table does not become a list of everyone who
 * has ever mistyped a password.
 *
 * @param string $bucket Throttle name.
 * @return string
 */
function sr_throttle_key( $bucket ) {
	return 'sr_thr_' . $bucket . '_' . substr( sha1( sr_security_client_ip() . wp_salt( 'auth' ) ), 0, 20 );
}

/** Refuse a login from an IP that has already failed too often. */
add_filter( 'authenticate', function ( $user, $username ) {
	if ( empty( $username ) ) {
		return $user;
	}

	$key      = sr_throttle_key( 'login' );
	$attempts = (int) get_transient( $key );

	if ( $attempts >= SR_LOGIN_MAX_ATTEMPTS ) {
		return new WP_Error(
			'sr_locked_out',
			sprintf(
				/* translators: %d: number of minutes. */
				__( '<strong>Too many attempts.</strong> Please wait %d minutes and try again.', 'samina' ),
				(int) ceil( SR_LOGIN_LOCKOUT_SECONDS / 60 )
			)
		);
	}

	return $user;
}, 30, 2 );

add_action( 'wp_login_failed', function ( $username ) {
	$key      = sr_throttle_key( 'login' );
	$attempts = (int) get_transient( $key ) + 1;

	set_transient( $key, $attempts, SR_LOGIN_LOCKOUT_SECONDS );

	sr_security_log(
		'login-failed',
		array(
			'attempt'  => $attempts,
			'username' => substr( sanitize_user( (string) $username, true ), 0, 60 ),
		)
	);

	if ( $attempts >= SR_LOGIN_MAX_ATTEMPTS ) {
		sr_security_log( 'login-lockout', array( 'attempts' => $attempts ) );
	}
} );

/** A clean login clears the counter so a legitimate user is never held over. */
add_action( 'wp_login', function () {
	delete_transient( sr_throttle_key( 'login' ) );
} );

/**
 * Password resets: three per IP per hour.
 *
 * Unthrottled, the reset form is both an enumeration oracle and a way to fill
 * a customer's inbox with mail that genuinely came from this shop.
 */
add_filter( 'allow_password_reset', function ( $allow ) {
	$key = sr_throttle_key( 'reset' );

	if ( (int) get_transient( $key ) >= 3 ) {
		sr_security_log( 'reset-throttled', array() );
		return new WP_Error(
			'sr_reset_throttled',
			__( 'Too many password reset requests. Please try again later.', 'samina' )
		);
	}

	return $allow;
} );

add_action( 'retrieve_password', function () {
	$key = sr_throttle_key( 'reset' );
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
} );

/** Reset links last a day by default; six hours is ample and halves the window. */
add_filter( 'password_reset_expiration', function () {
	return 6 * HOUR_IN_SECONDS;
} );

/**
 * Never confirm whether an account exists.
 *
 * Core's "Error: The username is not registered on this site" tells an attacker
 * which of their guesses to keep. Both failure modes now read the same.
 */
add_filter( 'login_errors', function ( $error ) {
	$leaky = array( 'invalid_username', 'invalid_email', 'incorrect_password', 'invalidcombo' );

	foreach ( $leaky as $code ) {
		if ( false !== strpos( (string) $error, $code ) ) {
			return __( '<strong>Error:</strong> Those details are not correct.', 'samina' );
		}
	}

	// Core renders these as prose rather than as codes, so match the text too.
	if ( preg_match( '/(is not registered|Unknown username|incorrect password|password you entered)/i', (string) $error ) ) {
		return __( '<strong>Error:</strong> Those details are not correct.', 'samina' );
	}

	return $error;
} );

/** The same for WooCommerce's own lost-password form. */
add_filter( 'woocommerce_registration_error_email_exists', function () {
	return __( 'If that address has an account, a message is on its way to it.', 'samina' );
} );

/* -------------------------------------------------------------------------
 * 5 · Sessions and cookies
 * ---------------------------------------------------------------------- */

/**
 * Log every other session out when a password changes.
 *
 * Core already does this for a self-service change; it does not when an
 * administrator changes someone's password, which is exactly the case where
 * the old session is the one you want gone.
 */
add_action( 'after_password_reset', function ( $user ) {
	if ( $user instanceof WP_User ) {
		WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
		sr_security_log( 'password-reset', array( 'user' => $user->ID ) );
	}
}, 10 );

add_action( 'profile_update', function ( $user_id, $old_user_data ) {
	$new = get_userdata( $user_id );

	if ( ! $new || ! $old_user_data instanceof WP_User ) {
		return;
	}

	if ( $new->user_pass !== $old_user_data->user_pass ) {
		// Everything except the session doing the changing.
		$keep = wp_get_session_token();
		WP_Session_Tokens::get_instance( $user_id )->destroy_others( $keep );
		sr_security_log( 'password-changed', array( 'user' => $user_id ) );
	}
}, 10, 2 );

/** Auth cookies over TLS only, and never readable from JavaScript. */
add_filter( 'secure_signon_cookie', '__return_true' );

/* -------------------------------------------------------------------------
 * 6 · A small security log
 * ---------------------------------------------------------------------- */

/**
 * Record a security event.
 *
 * A bounded ring buffer in the options table rather than a file: this store is
 * on shared hosting with no log shipping, and 200 recent events is enough to
 * answer "is someone trying the door?" without becoming a disk problem or a
 * personal-data one. IPs are hashed, never stored raw.
 *
 * @param string $event Short event slug.
 * @param array  $meta  Extra context. Must be scalar-valued.
 * @return void
 */
function sr_security_log( $event, $meta = array() ) {
	$log = get_option( 'sr_security_log', array() );
	$log = is_array( $log ) ? $log : array();

	$log[] = array(
		'at'    => time(),
		'event' => sanitize_key( $event ),
		'ip'    => substr( sha1( sr_security_client_ip() . wp_salt( 'auth' ) ), 0, 12 ),
		'meta'  => array_map(
			static function ( $v ) {
				return is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '';
			},
			(array) $meta
		),
	);

	if ( count( $log ) > 200 ) {
		$log = array_slice( $log, -200 );
	}

	// autoload 'no': this is read on one admin screen, not on every request.
	update_option( 'sr_security_log', $log, false );
}

/** Read it from wp-admin: Tools → Security log. */
add_action( 'admin_menu', function () {
	add_management_page(
		__( 'Security log', 'samina' ),
		__( 'Security log', 'samina' ),
		'manage_options',
		'sr-security-log',
		function () {
			$log = array_reverse( (array) get_option( 'sr_security_log', array() ) );

			echo '<div class="wrap"><h1>' . esc_html__( 'Security log', 'samina' ) . '</h1>';

			if ( ! $log ) {
				echo '<p>' . esc_html__( 'Nothing recorded yet.', 'samina' ) . '</p></div>';
				return;
			}

			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'When', 'samina' ) .
				'</th><th>' . esc_html__( 'Event', 'samina' ) .
				'</th><th>' . esc_html__( 'Source', 'samina' ) .
				'</th><th>' . esc_html__( 'Detail', 'samina' ) . '</th></tr></thead><tbody>';

			foreach ( $log as $row ) {
				$detail = array();
				foreach ( (array) ( $row['meta'] ?? array() ) as $k => $v ) {
					if ( '' !== $v ) {
						$detail[] = $k . ': ' . $v;
					}
				}

				printf(
					'<tr><td>%s</td><td><code>%s</code></td><td><code>%s</code></td><td>%s</td></tr>',
					esc_html( wp_date( 'Y-m-d H:i', (int) ( $row['at'] ?? 0 ) ) ),
					esc_html( (string) ( $row['event'] ?? '' ) ),
					esc_html( (string) ( $row['ip'] ?? '' ) ),
					esc_html( implode( ' · ', $detail ) )
				);
			}

			echo '</tbody></table></div>';
		}
	);
} );
