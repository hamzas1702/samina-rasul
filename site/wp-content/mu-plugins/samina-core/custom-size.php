<?php
/**
 * Made-to-measure sizing.
 *
 * "Customized" has been the seventh size on every product since the catalogue
 * was imported, and the size guide has been telling customers to choose it and
 * "we will cut to your measurements" — while collecting no measurements at all.
 * An order placed that way reached the atelier with the word "Customized" and
 * nothing else, and someone had to telephone the customer to start again.
 *
 * Choosing it now opens two honest routes:
 *
 *   - Enter Size Manually — fifteen measurements in inches, taken in two steps
 *     (front, then back), which travel with the cart item onto the order.
 *   - Request Call Back — a name and a telephone number, for the customer who
 *     would rather be measured over the phone than guess.
 *
 * Everything follows the pattern fabric-addons.php already established: the
 * browser posts values, the server validates them against a whitelist it owns,
 * and only the server's version is ever stored or displayed. Nothing here
 * touches price — a custom cut costs what the piece costs.
 *
 * @package samina-core
 */

defined( 'ABSPATH' ) || exit;

/** The attribute slug that opens this flow. */
const SR_CUSTOM_SIZE_SLUG = 'customized';

/**
 * The measurement sheet, in the order it is asked for.
 *
 * One definition, read by the form, the validator, the cart display and the
 * order line. A field cannot therefore be rendered but not validated, or
 * validated but not shown to the atelier.
 *
 * @return array<string, array{label:string, step:string}>
 */
function sr_measurement_fields() {
	return array(
		// Front.
		'neck_depth'       => array( 'label' => __( 'Neck Depth', 'samina' ), 'step' => 'front' ),
		'sleeve_length'    => array( 'label' => __( 'Sleeve Length', 'samina' ), 'step' => 'front' ),
		'shirt_length'     => array( 'label' => __( 'Shirt Length', 'samina' ), 'step' => 'front' ),
		'bust'             => array( 'label' => __( 'Bust Circumference', 'samina' ), 'step' => 'front' ),
		'waist'            => array( 'label' => __( 'Waist', 'samina' ), 'step' => 'front' ),
		'hip'              => array( 'label' => __( 'Hip Circumference', 'samina' ), 'step' => 'front' ),
		'thigh'            => array( 'label' => __( 'Thigh Circumference', 'samina' ), 'step' => 'front' ),
		'knee'             => array( 'label' => __( 'Knee Circumference', 'samina' ), 'step' => 'front' ),
		'calf'             => array( 'label' => __( 'Calf Circumference', 'samina' ), 'step' => 'front' ),
		'ankle'            => array( 'label' => __( 'Ankle', 'samina' ), 'step' => 'front' ),

		// Back.
		'back_neck_depth'  => array( 'label' => __( 'Back Neck Depth', 'samina' ), 'step' => 'back' ),
		'cross_shoulder'   => array( 'label' => __( 'Cross Shoulder', 'samina' ), 'step' => 'back' ),
		'trouser_length'   => array( 'label' => __( 'Trouser Length', 'samina' ), 'step' => 'back' ),
		'armhole'          => array( 'label' => __( 'Armhole', 'samina' ), 'step' => 'back' ),
		'bicep'            => array( 'label' => __( 'Bicep', 'samina' ), 'step' => 'back' ),
	);
}

/**
 * Bounds for a single measurement, in inches.
 *
 * Wide enough for any adult garment and narrow enough that a typo — a decimal
 * point in the wrong place, a centimetre figure — is caught before it is cut.
 */
const SR_MEASUREMENT_MIN = 1;
const SR_MEASUREMENT_MAX = 99;

/**
 * Whether a product offers the made-to-measure route at all.
 *
 * @param int $product_id Product id.
 * @return bool
 */
function sr_offers_custom_size( $product_id ) {
	$slugs = wc_get_product_terms( (int) $product_id, 'pa_size', array( 'fields' => 'slugs' ) );

	return is_array( $slugs ) && in_array( SR_CUSTOM_SIZE_SLUG, $slugs, true );
}

/**
 * Whether the size posted with this add-to-cart request is the custom one.
 *
 * Reads the raw request rather than a passed value because WooCommerce calls
 * the validation and cart-data filters at different points with different
 * arguments, and both need the same answer.
 *
 * @return bool
 */
function sr_custom_size_requested() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- reading the same add-to-cart POST WooCommerce itself reads unnonced.
	$size = isset( $_POST['attribute_pa_size'] ) ? sanitize_title( wp_unslash( $_POST['attribute_pa_size'] ) ) : '';

	return SR_CUSTOM_SIZE_SLUG === $size;
}

/* -------------------------------------------------------------------------
 * Reading the request
 * ---------------------------------------------------------------------- */

/**
 * The measurements from the request, validated.
 *
 * Keys come from sr_measurement_fields(), never from the request, so an extra
 * posted field cannot add a row to the order. Values must parse as a number in
 * range; anything else fails the whole sheet rather than being silently
 * dropped, because a garment cut from fourteen of fifteen measurements is
 * worse than one that was never cut.
 *
 * @return array{ok:bool, values:array<string,float>, missing:string[]}
 */
function sr_read_measurements() {
	$fields = sr_measurement_fields();
	$values = array();
	$missing = array();

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see sr_custom_size_requested().
	$posted = isset( $_POST['sr_measure'] ) && is_array( $_POST['sr_measure'] )
		? wp_unslash( $_POST['sr_measure'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each value is sanitised below.
		: array();

	foreach ( $fields as $key => $field ) {
		// is_scalar first: `sr_measure[waist][]=1` posts an array, and casting
		// that to string raises an "Array to string conversion" warning before
		// arriving at the harmless string "Array".
		$raw = isset( $posted[ $key ] ) && is_scalar( $posted[ $key ] )
			? trim( (string) $posted[ $key ] )
			: '';

		// is_numeric before casting: (float) '' is 0.0 and (float) 'abc' is 0.0,
		// so casting first would turn "not filled in" into a valid-looking zero.
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			$missing[] = $field['label'];
			continue;
		}

		$value = round( (float) $raw, 2 );

		if ( $value < SR_MEASUREMENT_MIN || $value > SR_MEASUREMENT_MAX ) {
			$missing[] = $field['label'];
			continue;
		}

		$values[ $key ] = $value;
	}

	return array(
		'ok'      => empty( $missing ),
		'values'  => $values,
		'missing' => $missing,
	);
}

/**
 * The call-back request from the request body, validated.
 *
 * @return array{ok:bool, values:array<string,string>}
 */
function sr_read_callback_request() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see sr_custom_size_requested().
	$posted = isset( $_POST['sr_callback'] ) && is_array( $_POST['sr_callback'] )
		? wp_unslash( $_POST['sr_callback'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each value is sanitised below.
		: array();

	// is_scalar guards against `sr_callback[name][]=x`, which would otherwise
	// reach sanitize_text_field() as an array.
	$field = static function ( $key ) use ( $posted ) {
		return isset( $posted[ $key ] ) && is_scalar( $posted[ $key ] )
			? sanitize_text_field( (string) $posted[ $key ] )
			: '';
	};

	$name  = $field( 'name' );
	$phone = $field( 'phone' );
	$time  = $field( 'time' );

	// Bound every field, so one request cannot write an essay onto an order.
	$name  = mb_substr( $name, 0, 100 );
	$phone = mb_substr( $phone, 0, 32 );
	$time  = mb_substr( $time, 0, 60 );

	// Deliberately permissive on format — this is a Pakistani mobile, a landline
	// or an international number with any of a dozen conventions — but strict on
	// alphabet, so the field cannot carry a message.
	$phone_ok = 1 === preg_match( '/^[0-9+][0-9\s\-()+]{6,31}$/', $phone )
		&& preg_match_all( '/\d/', $phone ) >= 7;

	return array(
		'ok'     => ( '' !== $name && $phone_ok ),
		'values' => array(
			'name'  => $name,
			'phone' => $phone,
			'time'  => $time,
		),
	);
}

/**
 * Which route the customer chose, as posted.
 *
 * @return string 'manual', 'callback' or ''.
 */
function sr_custom_size_mode() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see sr_custom_size_requested().
	$mode = isset( $_POST['sr_size_mode'] ) ? sanitize_key( wp_unslash( $_POST['sr_size_mode'] ) ) : '';

	return in_array( $mode, array( 'manual', 'callback' ), true ) ? $mode : '';
}

/* -------------------------------------------------------------------------
 * Validation, capture, display
 * ---------------------------------------------------------------------- */

/**
 * Refuse a custom-size add-to-cart that carries no usable detail.
 *
 * Without this the piece reaches the cart as "Size: Customized" and the whole
 * point of the flow is lost — silently, because the customer believes they have
 * ordered a made-to-measure garment.
 */
add_filter( 'woocommerce_add_to_cart_validation', function ( $passed, $product_id ) {
	if ( ! $passed || ! sr_custom_size_requested() ) {
		return $passed;
	}

	// A posted size of "customized" against a product that does not offer it is
	// not a made-to-measure request, it is a malformed one. WooCommerce's own
	// attribute validation deals with that; demanding measurements on top would
	// answer the wrong question.
	if ( ! sr_offers_custom_size( $product_id ) ) {
		return $passed;
	}

	$mode = sr_custom_size_mode();

	if ( '' === $mode ) {
		wc_add_notice(
			__( 'Choose how we should take your measurements — request a call back, or enter them yourself.', 'samina' ),
			'error'
		);
		return false;
	}

	if ( 'manual' === $mode ) {
		$read = sr_read_measurements();
		if ( ! $read['ok'] ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: comma-separated list of measurement names. */
					__( 'Please give every measurement in inches, between %1$d and %2$d. Still needed: %3$s.', 'samina' ),
					SR_MEASUREMENT_MIN,
					SR_MEASUREMENT_MAX,
					implode( ', ', $read['missing'] )
				),
				'error'
			);
			return false;
		}
		return true;
	}

	$callback = sr_read_callback_request();
	if ( ! $callback['ok'] ) {
		wc_add_notice(
			__( 'Please give your name and a telephone number we can reach you on.', 'samina' ),
			'error'
		);
		return false;
	}

	return true;
}, 10, 2 );

/**
 * Attach the validated detail to the cart item.
 *
 * WooCommerce hashes cart item data when it builds the cart id, so two sets of
 * measurements for the same piece stay two separate lines rather than merging
 * into a quantity of two — which is exactly right for a made-to-measure order.
 */
add_filter( 'woocommerce_add_cart_item_data', function ( $cart_item_data, $product_id ) {
	if ( ! sr_custom_size_requested() || ! sr_offers_custom_size( $product_id ) ) {
		return $cart_item_data;
	}

	$mode = sr_custom_size_mode();

	if ( 'manual' === $mode ) {
		$read = sr_read_measurements();
		if ( $read['ok'] ) {
			$cart_item_data['sr_custom_size'] = $read['values'];
		}
	} elseif ( 'callback' === $mode ) {
		$callback = sr_read_callback_request();
		if ( $callback['ok'] ) {
			$cart_item_data['sr_size_callback'] = $callback['values'];
		}
	}

	return $cart_item_data;
}, 10, 2 );

/**
 * Render the detail as order-item meta rows.
 *
 * Shared by the cart display and the order line so the customer, the
 * confirmation email and the atelier all read the same list in the same order.
 *
 * @param array $cart_item Cart item.
 * @return array<int, array{key:string, value:string}>
 */
function sr_custom_size_rows( $cart_item ) {
	$rows = array();

	if ( ! empty( $cart_item['sr_custom_size'] ) && is_array( $cart_item['sr_custom_size'] ) ) {
		$fields = sr_measurement_fields();

		foreach ( $fields as $key => $field ) {
			if ( ! isset( $cart_item['sr_custom_size'][ $key ] ) ) {
				continue;
			}
			$rows[] = array(
				'key'   => $field['label'],
				/* translators: %s: a measurement in inches. */
				'value' => sprintf( __( '%s in', 'samina' ), (string) ( 0 + $cart_item['sr_custom_size'][ $key ] ) ),
			);
		}
	}

	if ( ! empty( $cart_item['sr_size_callback'] ) && is_array( $cart_item['sr_size_callback'] ) ) {
		$callback = $cart_item['sr_size_callback'];

		$rows[] = array(
			'key'   => __( 'Sizing', 'samina' ),
			'value' => __( 'Call back requested', 'samina' ),
		);
		$rows[] = array(
			'key'   => __( 'Contact name', 'samina' ),
			'value' => (string) $callback['name'],
		);
		$rows[] = array(
			'key'   => __( 'Telephone', 'samina' ),
			'value' => (string) $callback['phone'],
		);

		if ( '' !== (string) $callback['time'] ) {
			$rows[] = array(
				'key'   => __( 'Best time to call', 'samina' ),
				'value' => (string) $callback['time'],
			);
		}
	}

	return $rows;
}

/** Cart and checkout. */
add_filter( 'woocommerce_get_item_data', function ( $item_data, $cart_item ) {
	return array_merge( (array) $item_data, sr_custom_size_rows( $cart_item ) );
}, 10, 2 );

/** The order itself — this is what reaches the admin screen and the emails. */
add_action( 'woocommerce_checkout_create_order_line_item', function ( $line_item, $cart_item_key, $values ) {
	foreach ( sr_custom_size_rows( $values ) as $row ) {
		$line_item->add_meta_data( $row['key'], $row['value'] );
	}
}, 10, 3 );

/**
 * Flag a call-back request on the order itself, not only on the line.
 *
 * A line-item note is easy to scroll past on a multi-item order, and this one
 * is a promise to telephone someone.
 */
add_action( 'woocommerce_checkout_create_order', function ( $order, $data ) {
	unset( $data );

	if ( ! WC()->cart ) {
		return;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( ! empty( $cart_item['sr_size_callback'] ) ) {
			$order->update_meta_data( '_sr_callback_requested', 'yes' );
			return;
		}
	}
}, 10, 2 );

add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
	if ( 'yes' !== $order->get_meta( '_sr_callback_requested' ) ) {
		return;
	}
	printf(
		'<p class="form-field form-field-wide"><strong style="color:#a00">%s</strong><br><span class="description">%s</span></p>',
		esc_html__( 'Call back requested for sizing', 'samina' ),
		esc_html__( 'The customer asked to be measured over the telephone. The name and number are on the line item below.', 'samina' )
	);
} );

/* -------------------------------------------------------------------------
 * Front end
 * ---------------------------------------------------------------------- */

/**
 * The choice row and the hidden inputs, inside the cart form.
 *
 * Inside the form because that is what makes the values post with the piece;
 * the dialogs themselves are printed after it, since a <dialog> in the top
 * layer should not be nested inside a form it is only writing into.
 *
 * Hidden by default and revealed by sr-custom-size.js when the Customized pill
 * is chosen: with JavaScript off the browser still posts a plain "Customized"
 * size, the server-side validator rejects it, and the customer is told to
 * telephone rather than being sold a garment nobody can cut.
 */
add_action( 'woocommerce_before_add_to_cart_button', 'sr_render_custom_size_controls', 20 );

function sr_render_custom_size_controls() {
	global $product;

	if ( ! $product instanceof WC_Product || ! sr_offers_custom_size( $product->get_id() ) ) {
		return;
	}

	$fields = sr_measurement_fields();
	?>
	<div class="sr-custom-size" data-sr-custom-size hidden>
		<p class="sr-custom-size__lead"><?php esc_html_e( 'How should we take your measurements?', 'samina' ); ?></p>
		<div class="sr-custom-size__choice">
			<button type="button" class="button sr-custom-size__btn" data-sr-cs-open="callback">
				<span><?php esc_html_e( 'Request Call Back', 'samina' ); ?></span>
			</button>
			<span class="sr-custom-size__or"><?php esc_html_e( 'OR', 'samina' ); ?></span>
			<button type="button" class="button sr-ghost sr-custom-size__btn" data-sr-cs-open="manual">
				<span><?php esc_html_e( 'Enter Size Manually', 'samina' ); ?></span>
			</button>
		</div>

		<p class="sr-custom-size__summary" data-sr-cs-summary hidden></p>

		<input type="hidden" name="sr_size_mode" value="" data-sr-cs-mode>
		<?php foreach ( $fields as $key => $field ) : ?>
			<input type="hidden" name="sr_measure[<?php echo esc_attr( $key ); ?>]" value="" data-sr-cs-store="measure.<?php echo esc_attr( $key ); ?>">
		<?php endforeach; ?>
		<?php foreach ( array( 'name', 'phone', 'time' ) as $key ) : ?>
			<input type="hidden" name="sr_callback[<?php echo esc_attr( $key ); ?>]" value="" data-sr-cs-store="callback.<?php echo esc_attr( $key ); ?>">
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * The dialogs, in the footer rather than beside the cart.
 *
 * They only ever write into the hidden inputs above, so they do not need to be
 * inside the form - and they must not be inside `.summary`, which is a flex
 * column whose `.single-product .summary > *` rule sets `order` and `margin: 0`
 * on every direct child. A modal <dialog> centres itself with `margin: auto`;
 * inheriting that reset pinned both dialogs to the top-left corner of the
 * viewport. The footer also keeps them clear of the column's own overflow and
 * stacking context.
 */
add_action( 'wp_footer', 'sr_render_custom_size_dialogs' );

function sr_render_custom_size_dialogs() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	// get_queried_object_id(), not get_the_ID(): this runs in the footer, after
	// the loop and after the related-products query have both finished with the
	// global post.
	$product = wc_get_product( get_queried_object_id() );

	if ( ! $product instanceof WC_Product || ! sr_offers_custom_size( $product->get_id() ) ) {
		return;
	}

	// A bridal piece renders no cart form at all, so there is nothing for the
	// dialogs to write into.
	if ( ! $product->is_purchasable() ) {
		return;
	}

	$fields = sr_measurement_fields();
	$front  = array_filter( $fields, static fn( $f ) => 'front' === $f['step'] );
	$back   = array_filter( $fields, static fn( $f ) => 'back' === $f['step'] );

	$diagrams = array(
		'front' => sr_measurement_diagram( 'front' ),
		'back'  => sr_measurement_diagram( 'back' ),
	);
	?>
	<dialog class="sr-cs-dialog" data-sr-cs-dialog="manual" aria-labelledby="sr-cs-title">
		<form method="dialog" class="sr-cs-dialog__dismiss">
			<button class="sr-cs-dialog__close" value="cancel" aria-label="<?php esc_attr_e( 'Close', 'samina' ); ?>">&times;</button>
		</form>

		<h2 class="sr-cs-dialog__title" id="sr-cs-title"><?php esc_html_e( 'Custom Size', 'samina' ); ?></h2>
		<p class="sr-cs-dialog__lead"><?php esc_html_e( 'Please enter all measurements in inches', 'samina' ); ?></p>

		<ol class="sr-cs-steps" aria-hidden="true">
			<li class="sr-cs-steps__item is-current" data-sr-cs-step-dot="front"><?php esc_html_e( 'Front', 'samina' ); ?></li>
			<li class="sr-cs-steps__item" data-sr-cs-step-dot="back"><?php esc_html_e( 'Back', 'samina' ); ?></li>
		</ol>

		<?php foreach ( array( 'front', 'back' ) as $step ) : ?>
			<?php $step_fields = 'front' === $step ? $front : $back; ?>
			<section class="sr-cs-step sr-cs-step--<?php echo esc_attr( $step ); ?>" data-sr-cs-step="<?php echo esc_attr( $step ); ?>"<?php echo 'front' === $step ? '' : ' hidden'; ?>>
				<div class="sr-cs-step__grid">
					<div class="sr-cs-fields">
						<?php foreach ( $step_fields as $key => $field ) : ?>
							<div class="sr-cs-field">
								<label class="screen-reader-text" for="sr-cs-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
								<input
									type="number"
									id="sr-cs-<?php echo esc_attr( $key ); ?>"
									data-sr-cs-input="measure.<?php echo esc_attr( $key ); ?>"
									placeholder="<?php echo esc_attr( $field['label'] ); ?>"
									inputmode="decimal"
									step="0.25"
									min="<?php echo esc_attr( (string) SR_MEASUREMENT_MIN ); ?>"
									max="<?php echo esc_attr( (string) SR_MEASUREMENT_MAX ); ?>"
									required>
							</div>
						<?php endforeach; ?>
					</div>
					<?php if ( '' !== $diagrams[ $step ] ) : ?>
						<figure class="sr-cs-diagram"><?php echo $diagrams[ $step ]; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image(). ?></figure>
					<?php endif; ?>
				</div>

				<p class="sr-cs-error" data-sr-cs-error role="alert" hidden></p>

				<div class="sr-cs-dialog__actions">
					<?php if ( 'front' === $step ) : ?>
						<button type="button" class="button sr-cs-next" data-sr-cs-goto="back"><span><?php esc_html_e( 'Next', 'samina' ); ?></span></button>
					<?php else : ?>
						<button type="button" class="button sr-ghost sr-cs-back" data-sr-cs-goto="front"><span><?php esc_html_e( 'Back', 'samina' ); ?></span></button>
						<button type="button" class="button sr-cs-commit" data-sr-cs-commit="manual"><span><?php esc_html_e( 'Save measurements', 'samina' ); ?></span></button>
					<?php endif; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</dialog>

	<dialog class="sr-cs-dialog sr-cs-dialog--callback" data-sr-cs-dialog="callback" aria-labelledby="sr-cs-cb-title">
		<form method="dialog" class="sr-cs-dialog__dismiss">
			<button class="sr-cs-dialog__close" value="cancel" aria-label="<?php esc_attr_e( 'Close', 'samina' ); ?>">&times;</button>
		</form>

		<h2 class="sr-cs-dialog__title" id="sr-cs-cb-title"><?php esc_html_e( 'Request a call back', 'samina' ); ?></h2>
		<p class="sr-cs-dialog__lead"><?php esc_html_e( 'Leave your number and the atelier will call to take your measurements before the piece is cut.', 'samina' ); ?></p>

		<div class="sr-cs-fields">
			<div class="sr-cs-field">
				<label class="screen-reader-text" for="sr-cs-cb-name"><?php esc_html_e( 'Full name', 'samina' ); ?></label>
				<input type="text" id="sr-cs-cb-name" data-sr-cs-input="callback.name" placeholder="<?php esc_attr_e( 'Full name', 'samina' ); ?>" maxlength="100" autocomplete="name" required>
			</div>
			<div class="sr-cs-field">
				<label class="screen-reader-text" for="sr-cs-cb-phone"><?php esc_html_e( 'Telephone number', 'samina' ); ?></label>
				<input type="tel" id="sr-cs-cb-phone" data-sr-cs-input="callback.phone" placeholder="<?php esc_attr_e( 'Telephone number', 'samina' ); ?>" maxlength="32" autocomplete="tel" required>
			</div>
			<div class="sr-cs-field">
				<label class="screen-reader-text" for="sr-cs-cb-time"><?php esc_html_e( 'Best time to call (optional)', 'samina' ); ?></label>
				<input type="text" id="sr-cs-cb-time" data-sr-cs-input="callback.time" placeholder="<?php esc_attr_e( 'Best time to call (optional)', 'samina' ); ?>" maxlength="60">
			</div>
		</div>

		<p class="sr-cs-error" data-sr-cs-error role="alert" hidden></p>

		<div class="sr-cs-dialog__actions">
			<button type="button" class="button sr-cs-commit" data-sr-cs-commit="callback"><span><?php esc_html_e( 'Save and continue', 'samina' ); ?></span></button>
		</div>
	</dialog>
	<?php
}

/**
 * The measurement diagram for a step, when the client has uploaded one.
 *
 * Uploaded rather than drawn into the theme: these are the atelier's own
 * technical illustrations, and they change when the house's cut does.
 *
 * @param string $step 'front' or 'back'.
 * @return string <img> markup, or ''.
 */
function sr_measurement_diagram( $step ) {
	if ( ! function_exists( 'sr_home_image_id' ) ) {
		return '';
	}

	$attachment_id = sr_home_image_id( 'sr_size_diagram_' . $step );

	if ( $attachment_id < 1 ) {
		return '';
	}

	return wp_get_attachment_image(
		$attachment_id,
		'medium_large',
		false,
		array(
			'class'   => 'sr-cs-diagram__img',
			'alt'     => 'front' === $step
				? esc_attr__( 'Diagram showing where each front measurement is taken', 'samina' )
				: esc_attr__( 'Diagram showing where each back measurement is taken', 'samina' ),
			'loading' => 'lazy',
		)
	);
}
