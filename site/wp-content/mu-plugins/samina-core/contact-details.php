<?php
/**
 * The atelier's public contact details.
 *
 * The inbox that client inquiries reach is not the WordPress administrator
 * address: that one is the login and recovery address for whoever maintains the
 * site, it is not printed on the storefront, and changing it to a shared inbox
 * to make the contact form work would move password resets there too.
 *
 * The WhatsApp number lives beside this, in samina-core/bridal-flow.php, and is
 * registered into the same settings section.
 *
 * @package samina-rasul
 */

defined( 'ABSPATH' ) || exit;

/**
 * Where client inquiries go, and the address shown on the contact page.
 *
 * Falls back to the administrator address so a fresh install still delivers
 * rather than silently dropping the form.
 *
 * @return string A valid email address, or '' when neither is usable.
 */
function sr_contact_email() {
	$email = sanitize_email( (string) get_option( 'sr_contact_email', '' ) );

	if ( '' === $email || ! is_email( $email ) ) {
		$email = sanitize_email( (string) get_option( 'admin_email', '' ) );
	}

	return is_email( $email ) ? $email : '';
}

/**
 * The number as a human reads it, for display rather than for a wa.me link.
 *
 * sr_whatsapp_number() stores digits only in international format, which is
 * what the link needs and the worst possible thing to print on a page.
 *
 * @return string Formatted number, or '' when none is set.
 */
function sr_contact_phone_display() {
	$digits = function_exists( 'sr_whatsapp_number' ) ? sr_whatsapp_number() : '';
	if ( '' === $digits ) {
		return '';
	}

	// Pakistan: 92 3XX XXXXXXX reads locally as 03XX XXXXXXX.
	if ( 0 === strpos( $digits, '92' ) && 12 === strlen( $digits ) ) {
		return '0' . substr( $digits, 2, 3 ) . ' ' . substr( $digits, 5 );
	}

	return '+' . $digits;
}

add_filter( 'woocommerce_general_settings', function ( $settings ) {
	$settings[] = array(
		'title'    => __( 'Client inquiries email', 'samina' ),
		'desc'     => __( 'Where the contact form is delivered, and the address shown on the contact page. Falls back to the site administrator address when empty.', 'samina' ),
		'id'       => 'sr_contact_email',
		'type'     => 'email',
		'default'  => '',
		'desc_tip' => true,
	);
	return $settings;
} );
