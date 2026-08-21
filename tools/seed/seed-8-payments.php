<?php
/**
 * Configure payment: direct bank transfer, and nothing else.
 *
 * The store shipped with no payment gateway configured at all — there was no
 * `woocommerce_bacs_settings` option in the database, which means the checkout
 * had no way to take an order. This turns on WooCommerce's BACS gateway, writes
 * the house account onto it, and disables every other gateway so a customer
 * cannot land on a half-configured one.
 *
 * Gateway settings live in options rather than in code, so this is a script
 * rather than a module. It is idempotent — running it twice changes nothing —
 * and it is the companion to mu-plugins/samina-core/bank-transfer.php, which
 * handles what happens after the order is placed.
 *
 * Run from site/:
 *   ../.tools/wp eval-file ../tools/seed/seed-8-payments.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through wp eval-file.\n" );
	exit( 1 );
}

if ( ! function_exists( 'WC' ) ) {
	fwrite( STDERR, "WooCommerce is not active.\n" );
	exit( 1 );
}

/*
 * The house account. Held here rather than typed into wp-admin so that a
 * restored database, a staging copy or a fresh install all end up with the same
 * details, and so a change to them is a reviewable commit.
 */
$sr_account = array(
	'account_name'   => 'NOOR UL AEIN ARSHAD',
	'account_number' => '03031825002486013',
	'bank_name'      => 'Bank Al Habib',
	'sort_code'      => '',
	'iban'           => 'PK37BAHL0303182500248601',
	'bic'            => '',
	/*
	 * Not a field WooCommerce knows about. samina-core/bank-transfer.php adds it
	 * to the details list through `woocommerce_bacs_account_fields`, so it sits
	 * with the IBAN as something to copy rather than inside the instructions
	 * paragraph, where it read as prose.
	 */
	'branch'         => '0303-KHAYABAN E IQBAL DHA LHR',
);

$sr_instructions = implode(
	"\n\n",
	array(
		'Please transfer the total to the account below and then upload your receipt on the order page. We check every transfer against our account and confirm the order — usually the same working day.',
		'Quote your order number as the payment reference. Your pieces enter the atelier once the transfer is confirmed.',
	)
);

/* -------------------------------------------------------------------------
 * BACS
 * ---------------------------------------------------------------------- */

$sr_bacs = get_option( 'woocommerce_bacs_settings', array() );
$sr_bacs = is_array( $sr_bacs ) ? $sr_bacs : array();

$sr_bacs = array_merge(
	$sr_bacs,
	array(
		'enabled'      => 'yes',
		'title'        => 'Direct bank transfer',
		'description'  => 'Pay directly into our bank account, then upload your receipt. Your order is confirmed once we have verified the transfer.',
		'instructions' => $sr_instructions,
	)
);

update_option( 'woocommerce_bacs_settings', $sr_bacs );

// The account list is a separate option, not part of the settings array.
update_option( 'woocommerce_bacs_accounts', array( $sr_account ) );

echo "BACS enabled.\n";
printf( "  %s / %s\n", $sr_account['account_name'], $sr_account['account_number'] );
printf( "  IBAN %s\n", $sr_account['iban'] );
printf( "  Branch: %s\n", $sr_account['branch'] );

/* -------------------------------------------------------------------------
 * Everything else off
 * ---------------------------------------------------------------------- */

$sr_disabled = array();

foreach ( WC()->payment_gateways()->payment_gateways() as $sr_gateway ) {
	if ( 'bacs' === $sr_gateway->id ) {
		continue;
	}

	$sr_key      = 'woocommerce_' . $sr_gateway->id . '_settings';
	$sr_settings = get_option( $sr_key, array() );
	$sr_settings = is_array( $sr_settings ) ? $sr_settings : array();

	// Only write when it would actually change something, so this does not
	// create a settings row for every gateway WooCommerce happens to ship.
	if ( ! isset( $sr_settings['enabled'] ) || 'no' !== $sr_settings['enabled'] ) {
		if ( isset( $sr_settings['enabled'] ) ) {
			$sr_settings['enabled'] = 'no';
			update_option( $sr_key, $sr_settings );
			$sr_disabled[] = $sr_gateway->id;
		}
	}
}

// BACS first in the list, since it is the only one a customer should ever see.
update_option( 'woocommerce_gateway_order', array( 'bacs' => 0 ) );

printf(
	"Other gateways disabled: %s\n",
	$sr_disabled ? implode( ', ', $sr_disabled ) : '(none were enabled)'
);

/* -------------------------------------------------------------------------
 * Verify
 * ---------------------------------------------------------------------- */

WC()->payment_gateways()->init();

$sr_available = array_keys( WC()->payment_gateways()->get_available_payment_gateways() );

printf( "Available at checkout: %s\n", $sr_available ? implode( ', ', $sr_available ) : 'NONE — check the settings' );

if ( array( 'bacs' ) !== $sr_available ) {
	fwrite( STDERR, "! Expected bacs to be the only available gateway.\n" );
}
