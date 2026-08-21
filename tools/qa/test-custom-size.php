<?php
/**
 * Custom-size validator regression test.
 *
 * The measurement sheet is the one place on this store where a silent failure
 * costs real money: a garment cut from a partial or mistyped set of numbers is
 * scrapped, not returned. So the rules that decide what reaches the atelier get
 * a test rather than a careful read.
 *
 * Covers what the browser cannot be trusted to enforce — the dialog validates
 * too, but a hand-rolled POST reaches the same filters with none of that.
 *
 * Run from site/:
 *   ../.tools/wp eval-file ../tools/qa/test-custom-size.php
 *
 * Exits non-zero on the first failure so it can gate a deploy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through wp eval-file.\n" );
	exit( 1 );
}

if ( ! function_exists( 'sr_read_measurements' ) ) {
	fwrite( STDERR, "samina-core/custom-size.php is not loaded.\n" );
	exit( 1 );
}

/**
 * Record one assertion.
 *
 * The tally lives in a static rather than a global on purpose: `wp eval-file`
 * includes this file inside a function, so a variable written at what looks
 * like file scope is a local, and `global $sr_failures` in here would bind to a
 * different, permanently empty variable. That is not a hypothetical — it is
 * what this script did on its first run, reporting "0 checks, 0 failures" while
 * thirty-one checks passed, and it would have reported exit 0 just as cheerfully
 * had they failed.
 *
 * @param string $label  What is being asserted.
 * @param bool   $ok     Result.
 * @param string $detail Extra context printed on failure.
 * @return int[] Running [checks, failures].
 */
function sr_check( $label = null, $ok = false, $detail = '' ) {
	static $checks = 0;
	static $failures = 0;

	// Called with no arguments, it reports rather than asserts.
	if ( null === $label ) {
		return array( $checks, $failures );
	}

	$checks++;

	if ( $ok ) {
		printf( "  ok    %s\n", $label );
		return array( $checks, $failures );
	}

	$failures++;
	printf( "  FAIL  %s%s\n", $label, '' !== $detail ? ' — ' . $detail : '' );

	return array( $checks, $failures );
}

/** Build a complete, valid measurement POST. */
function sr_full_sheet( $value = 30 ) {
	$sheet = array();
	foreach ( array_keys( sr_measurement_fields() ) as $key ) {
		$sheet[ $key ] = (string) $value;
	}
	return $sheet;
}

/** Run a closure with $_POST set to the given request. */
function sr_with_post( array $post, callable $fn ) {
	$saved = $_POST;
	$_POST = $post; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.VariableAnalysis -- test harness.
	try {
		return $fn();
	} finally {
		$_POST = $saved; // phpcs:ignore WordPress.Security.NonceVerification -- test harness.
	}
}

echo "Custom size — measurement sheet\n";

$sr_fields = sr_measurement_fields();

sr_check(
	'the sheet has fifteen measurements',
	15 === count( $sr_fields ),
	sprintf( 'found %d', count( $sr_fields ) )
);

sr_check(
	'ten are taken from the front, five from the back',
	10 === count( array_filter( $sr_fields, static fn( $f ) => 'front' === $f['step'] ) )
		&& 5 === count( array_filter( $sr_fields, static fn( $f ) => 'back' === $f['step'] ) )
);

echo "\nCustom size — validation\n";

// A complete sheet passes and comes back as floats.
sr_with_post(
	array( 'sr_measure' => sr_full_sheet( 30 ) ),
	function () {
		$read = sr_read_measurements();
		sr_check( 'a complete sheet is accepted', true === $read['ok'] );
		sr_check( 'every value is returned', 15 === count( $read['values'] ) );
		sr_check( 'values are numeric', 30.0 === $read['values']['waist'], var_export( $read['values']['waist'] ?? null, true ) );
	}
);

// One field missing fails the whole sheet, and says which.
sr_with_post(
	array( 'sr_measure' => array_merge( sr_full_sheet( 30 ), array( 'waist' => '' ) ) ),
	function () {
		$read = sr_read_measurements();
		sr_check( 'a sheet with one blank field is rejected', false === $read['ok'] );
		sr_check( 'the blank field is named', in_array( 'Waist', $read['missing'], true ), implode( ',', $read['missing'] ) );
	}
);

// Zero must not pass. This is the case a naive (float) cast lets through.
sr_with_post(
	array( 'sr_measure' => array_merge( sr_full_sheet( 30 ), array( 'bust' => '0' ) ) ),
	function () {
		$read = sr_read_measurements();
		sr_check( 'zero is rejected', false === $read['ok'] );
	}
);

// Non-numeric text must not pass, and must not become 0.
sr_with_post(
	array( 'sr_measure' => array_merge( sr_full_sheet( 30 ), array( 'hip' => 'thirty' ) ) ),
	function () {
		$read = sr_read_measurements();
		sr_check( 'text is rejected', false === $read['ok'] );
		sr_check( 'text does not become a value', ! isset( $read['values']['hip'] ) );
	}
);

// Out of range in both directions.
foreach ( array( '0.5' => 'below the minimum', '250' => 'above the maximum' ) as $sr_bad => $sr_why ) {
	sr_with_post(
		array( 'sr_measure' => array_merge( sr_full_sheet( 30 ), array( 'ankle' => $sr_bad ) ) ),
		function () use ( $sr_why ) {
			sr_check( 'a measurement ' . $sr_why . ' is rejected', false === sr_read_measurements()['ok'] );
		}
	);
}

// An extra field in the request must not reach the order.
sr_with_post(
	array( 'sr_measure' => array_merge( sr_full_sheet( 30 ), array( 'evil' => '<script>alert(1)</script>' ) ) ),
	function () {
		$read = sr_read_measurements();
		sr_check( 'an unknown field is ignored', true === $read['ok'] && ! isset( $read['values']['evil'] ) );
	}
);

// A non-array sr_measure must not fatal.
sr_with_post(
	array( 'sr_measure' => 'not-an-array' ),
	function () {
		sr_check( 'a scalar sr_measure is rejected without erroring', false === sr_read_measurements()['ok'] );
	}
);

// A nested array for one measurement — sr_measure[waist][]=1 — must be rejected
// rather than casting to the string "Array" and raising a PHP warning on the way.
sr_with_post(
	array( 'sr_measure' => array_merge( sr_full_sheet( 30 ), array( 'waist' => array( '1' ) ) ) ),
	function () {
		$before = did_action( 'doing_it_wrong_run' );
		$read   = sr_read_measurements();
		sr_check( 'an array in place of a measurement is rejected', false === $read['ok'] );
		sr_check( 'and does not become a value', ! isset( $read['values']['waist'] ) );
		unset( $before );
	}
);

echo "\nCustom size — call back\n";

sr_with_post(
	array( 'sr_callback' => array( 'name' => 'Ayesha Khan', 'phone' => '0300 1234567', 'time' => 'Afternoons' ) ),
	function () {
		$read = sr_read_callback_request();
		sr_check( 'a name and a Pakistani mobile are accepted', true === $read['ok'] );
		sr_check( 'the name is kept', 'Ayesha Khan' === $read['values']['name'] );
	}
);

sr_with_post(
	array( 'sr_callback' => array( 'name' => 'Ayesha Khan', 'phone' => '+92 300 1234567' ) ),
	function () {
		sr_check( 'an international number is accepted', true === sr_read_callback_request()['ok'] );
	}
);

foreach (
	array(
		'a missing name'          => array( 'name' => '', 'phone' => '03001234567' ),
		'a missing number'        => array( 'name' => 'Ayesha', 'phone' => '' ),
		'too few digits'          => array( 'name' => 'Ayesha', 'phone' => '12345' ),
		'a number full of letters' => array( 'name' => 'Ayesha', 'phone' => 'call me maybe' ),
	) as $sr_why => $sr_payload
) {
	sr_with_post(
		array( 'sr_callback' => $sr_payload ),
		function () use ( $sr_why ) {
			sr_check( $sr_why . ' is rejected', false === sr_read_callback_request()['ok'] );
		}
	);
}

sr_with_post(
	array( 'sr_callback' => array( 'name' => str_repeat( 'a', 500 ), 'phone' => '03001234567' ) ),
	function () {
		$read = sr_read_callback_request();
		sr_check( 'an over-long name is truncated, not rejected', true === $read['ok'] && 100 === mb_strlen( $read['values']['name'] ) );
	}
);

sr_with_post(
	array( 'sr_callback' => array( 'name' => '<script>alert(1)</script>Ayesha', 'phone' => '03001234567' ) ),
	function () {
		$read = sr_read_callback_request();
		sr_check( 'markup is stripped from the name', false === strpos( $read['values']['name'], '<' ), $read['values']['name'] );
	}
);

sr_with_post(
	array( 'sr_callback' => array( 'name' => array( 'a' ), 'phone' => array( '03001234567' ) ) ),
	function () {
		$read = sr_read_callback_request();
		sr_check( 'arrays in place of name and phone are rejected', false === $read['ok'] );
		sr_check( 'and do not reach the order', '' === $read['values']['name'] && '' === $read['values']['phone'] );
	}
);

sr_with_post(
	array( 'sr_callback' => 'not-an-array' ),
	function () {
		sr_check( 'a scalar sr_callback is rejected without erroring', false === sr_read_callback_request()['ok'] );
	}
);

echo "\nCustom size — mode\n";

foreach ( array( 'manual', 'callback' ) as $sr_mode ) {
	sr_with_post(
		array( 'sr_size_mode' => $sr_mode ),
		function () use ( $sr_mode ) {
			sr_check( sprintf( '"%s" is a valid route', $sr_mode ), $sr_mode === sr_custom_size_mode() );
		}
	);
}

sr_with_post(
	array( 'sr_size_mode' => 'free_dress_please' ),
	function () {
		sr_check( 'an unknown route resolves to none', '' === sr_custom_size_mode() );
	}
);

echo "\nCustom size — rendering\n";

sr_with_post(
	array( 'sr_measure' => sr_full_sheet( 30 ) ),
	function () {
		$read = sr_read_measurements();
		$rows = sr_custom_size_rows( array( 'sr_custom_size' => $read['values'] ) );

		sr_check( 'every measurement becomes an order row', 15 === count( $rows ) );
		sr_check( 'rows carry the unit', '30 in' === $rows[0]['value'], $rows[0]['value'] );
		sr_check( 'rows are in sheet order', 'Neck Depth' === $rows[0]['key'], $rows[0]['key'] );
	}
);

$sr_cb_rows = sr_custom_size_rows(
	array( 'sr_size_callback' => array( 'name' => 'Ayesha', 'phone' => '03001234567', 'time' => '' ) )
);
sr_check( 'a call back with no preferred time yields three rows', 3 === count( $sr_cb_rows ), (string) count( $sr_cb_rows ) );

$sr_cb_rows = sr_custom_size_rows(
	array( 'sr_size_callback' => array( 'name' => 'Ayesha', 'phone' => '03001234567', 'time' => 'Evenings' ) )
);
sr_check( 'a preferred time adds a fourth', 4 === count( $sr_cb_rows ) );

sr_check( 'an item with neither yields nothing', array() === sr_custom_size_rows( array() ) );

list( $sr_checks, $sr_failures ) = sr_check();

printf( "\n%d checks, %d failures.\n", $sr_checks, $sr_failures );

// A suite that runs no checks at all is a broken suite, not a passing one.
if ( $sr_failures > 0 || $sr_checks < 30 ) {
	exit( 1 );
}
