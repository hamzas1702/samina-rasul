<?php
/**
 * Bank transfer, and proving it happened.
 *
 * The house takes direct bank transfer and nothing else. WooCommerce's built-in
 * BACS gateway already does the first half of that — it shows the account on
 * checkout, on the thank-you page and in the confirmation email, and leaves the
 * order On hold. What it has never done is tell the shop owner that the money
 * actually arrived, which on this store meant reconciling every order against a
 * bank statement by hand and telephoning anyone whose payment could not be found.
 *
 * So: the order is placed first and the receipt is uploaded afterwards, from the
 * thank-you page or from My account → Orders. An upload moves the order into a
 * status of its own — "Payment under review" — writes an order note and emails
 * the house. Nothing about the order depends on the upload succeeding: a
 * customer who never returns still has an order on hold, which is exactly what
 * they had before this file existed.
 *
 * The receipt itself is a photograph of a bank screen, so it is treated as
 * sensitive: it is stored outside the media library, under an unguessable name,
 * in a directory that denies direct HTTP access, and is served back only to
 * users who can edit orders.
 *
 * @package samina-core
 */

defined( 'ABSPATH' ) || exit;

/** Order status for "they say they have paid, we have not checked yet". */
const SR_STATUS_VERIFYING = 'sr-verifying';

/** Directory under uploads/ that holds receipts. */
const SR_RECEIPT_DIR = 'sr-receipts';

/** Hard ceiling on an uploaded receipt. A phone screenshot is well under this. */
const SR_RECEIPT_MAX_BYTES = 5242880; // 5 MB.

/**
 * What a receipt is allowed to be, as extension => MIME type.
 *
 * A whitelist, checked against what the file actually contains rather than what
 * it is called, so `invoice.php` renamed to `invoice.jpg` is rejected.
 */
function sr_receipt_allowed_types() {
	return array(
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
		'pdf'      => 'application/pdf',
	);
}

/* -------------------------------------------------------------------------
 * The order status
 * ---------------------------------------------------------------------- */

add_action( 'init', function () {
	register_post_status(
		'wc-' . SR_STATUS_VERIFYING,
		array(
			'label'                     => _x( 'Payment under review', 'Order status', 'samina' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: order count. */
			'label_count'               => _n_noop(
				'Payment under review <span class="count">(%s)</span>',
				'Payment under review <span class="count">(%s)</span>',
				'samina'
			),
		)
	);
} );

/**
 * Slot the status in directly after On hold, which is where it belongs in the
 * shop owner's reading of the list: awaiting money, then money claimed, then
 * money confirmed.
 */
add_filter( 'wc_order_statuses', function ( $statuses ) {
	$reordered = array();

	foreach ( $statuses as $key => $label ) {
		$reordered[ $key ] = $label;
		if ( 'wc-on-hold' === $key ) {
			$reordered[ 'wc-' . SR_STATUS_VERIFYING ] = _x( 'Payment under review', 'Order status', 'samina' );
		}
	}

	// On-hold is a core status and should always be there, but never assume.
	if ( ! isset( $reordered[ 'wc-' . SR_STATUS_VERIFYING ] ) ) {
		$reordered[ 'wc-' . SR_STATUS_VERIFYING ] = _x( 'Payment under review', 'Order status', 'samina' );
	}

	return $reordered;
} );

/** The order is still unpaid and still needs the customer to be able to see it. */
add_filter( 'woocommerce_valid_order_statuses_for_payment', function ( $statuses ) {
	$statuses[] = SR_STATUS_VERIFYING;
	return $statuses;
} );

/* -------------------------------------------------------------------------
 * How the account is presented
 * ---------------------------------------------------------------------- */

/**
 * The single configured account, as an array.
 *
 * Returns nothing when the shop has more than one: the two filters below inject
 * one account's title and branch into a fields list WooCommerce reuses for
 * every account it renders, so with two accounts on the page the first one's
 * details would be printed under both. The house has one account; if that ever
 * changes, this steps aside and WooCommerce's own presentation takes over
 * rather than quietly printing the wrong number.
 *
 * @return array
 */
function sr_bacs_account() {
	$accounts = get_option( 'woocommerce_bacs_accounts', array() );

	if ( ! is_array( $accounts ) || 1 !== count( $accounts ) || ! isset( $accounts[0] ) || ! is_array( $accounts[0] ) ) {
		return array();
	}

	return $accounts[0];
}

/**
 * Take the account holder's name out of the heading.
 *
 * WooCommerce prints it as an <h3> above the details list — so the page read
 * "NOOR UL AEIN ARSHAD:" as a title, with Bank, Account number and IBAN listed
 * underneath it as though they belonged to a section rather than to the same
 * account. It is a field like any other; it just needs a label.
 */
add_filter( 'woocommerce_bacs_accounts', function ( $accounts ) {
	// Only when this file is also putting the name back as a row; otherwise
	// blanking the heading would lose the account holder's name entirely.
	if ( ! sr_bacs_account() ) {
		return $accounts;
	}

	foreach ( $accounts as $i => $account ) {
		$account = (array) $account;
		if ( isset( $account['account_name'] ) ) {
			$account['account_name'] = '';
		}
		$accounts[ $i ] = $account;
	}

	return $accounts;
}, 20 );

/**
 * Put it back as the first row, and add the branch after the IBAN.
 *
 * The branch was previously buried in the instructions paragraph, where it read
 * as prose rather than as something to copy into a transfer form.
 */
add_filter( 'woocommerce_bacs_account_fields', function ( $fields ) {
	$account = sr_bacs_account();

	$title = isset( $account['account_name'] ) ? trim( (string) $account['account_name'] ) : '';

	if ( '' !== $title ) {
		$fields = array_merge(
			array(
				'account_title' => array(
					'label' => __( 'Account title', 'samina' ),
					'value' => $title,
				),
			),
			$fields
		);
	}

	$branch = isset( $account['branch'] ) ? trim( (string) $account['branch'] ) : '';

	if ( '' !== $branch ) {
		$fields['branch'] = array(
			'label' => __( 'Branch', 'samina' ),
			'value' => $branch,
		);
	}

	/*
	 * Drop sort code and BIC only while they are actually empty. WooCommerce
	 * already skips empty rows when rendering, so removing them outright bought
	 * nothing and would have silently swallowed a BIC the day someone typed one
	 * into WooCommerce → Settings → Payments.
	 */
	foreach ( array( 'sort_code', 'bic' ) as $optional ) {
		if ( isset( $fields[ $optional ] ) && '' === trim( (string) $fields[ $optional ]['value'] ) ) {
			unset( $fields[ $optional ] );
		}
	}

	return $fields;
}, 10 );

/* -------------------------------------------------------------------------
 * Storage
 * ---------------------------------------------------------------------- */

/**
 * The receipts directory, created and sealed on first use.
 *
 * Sealed three ways because no single one covers every host: an .htaccess for
 * Apache and LiteSpeed (which is what this site is deployed onto), a web.config
 * for IIS, and an index.php so a server with directory listing on cannot
 * enumerate what is in there.
 *
 * None of those are honoured by nginx or by PHP's built-in development server,
 * which is why the filename carries 128 bits of randomness as well: on a host
 * that ignores the guard files, a receipt is still not reachable by anyone who
 * knows only the order number. That is the layer that holds everywhere, and the
 * guard files are what turn "unguessable" into "refused".
 *
 * ponytail: config-file guards, not an application-level file proxy. If this
 * ever moves to nginx, either add the equivalent `location` deny block or route
 * uploads through the same capability-checked endpoint the admin download uses.
 *
 * @return string|WP_Error Absolute path with a trailing slash.
 */
function sr_receipt_dir() {
	$uploads = wp_upload_dir();

	if ( ! empty( $uploads['error'] ) ) {
		return new WP_Error( 'sr_uploads', $uploads['error'] );
	}

	$dir = trailingslashit( $uploads['basedir'] ) . SR_RECEIPT_DIR . '/';

	if ( ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'sr_mkdir', __( 'Could not create the receipts folder.', 'samina' ) );
	}

	$guards = array(
		'.htaccess'  => "Require all denied\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n",
		'web.config' => "<?xml version=\"1.0\"?>\n<configuration><system.webServer><authorization>\n<deny users=\"*\" />\n</authorization></system.webServer></configuration>\n",
		'index.php'  => "<?php\n// Silence is golden.\n",
	);

	foreach ( $guards as $name => $contents ) {
		if ( ! file_exists( $dir . $name ) ) {
			file_put_contents( $dir . $name, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing a local guard file before any receipt exists; WP_Filesystem is not initialised this early on a front-end request.
		}
	}

	return $dir;
}

/**
 * Absolute path of the receipt stored against an order, if any.
 *
 * The stored value is a basename and is re-derived through basename() again
 * here, so a meta value tampered with in the database still cannot escape the
 * receipts directory.
 *
 * @param WC_Order $order Order.
 * @return string Absolute path, or '' when there is no readable receipt.
 */
function sr_receipt_path( $order ) {
	$stored = (string) $order->get_meta( '_sr_receipt_file' );

	if ( '' === $stored ) {
		return '';
	}

	$dir = sr_receipt_dir();
	if ( is_wp_error( $dir ) ) {
		return '';
	}

	$path = $dir . basename( $stored );

	return is_readable( $path ) ? $path : '';
}

/* -------------------------------------------------------------------------
 * Who may do what
 * ---------------------------------------------------------------------- */

/**
 * The order this request is allowed to act on, or null.
 *
 * Guests reach their own order through the order key WooCommerce puts in the
 * thank-you URL, which is the same proof the thank-you page itself accepts.
 * Logged-in customers are matched on ownership as well, so a stale key from
 * someone else's order is not enough.
 *
 * @param int    $order_id Order id.
 * @param string $key      Order key from the request.
 * @return WC_Order|null
 */
function sr_receipt_authorise( $order_id, $key ) {
	$order = wc_get_order( $order_id );

	if ( ! $order instanceof WC_Order ) {
		return null;
	}

	$customer_id = (int) $order->get_customer_id();

	if ( $customer_id > 0 && $customer_id === get_current_user_id() ) {
		return $order;
	}

	// hash_equals: comparing a secret with == leaks its length through timing.
	if ( '' !== $key && hash_equals( (string) $order->get_order_key(), (string) $key ) ) {
		return $order;
	}

	return null;
}

/**
 * Whether an order is at a point where a receipt still means something.
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function sr_receipt_accepted_for( $order ) {
	return in_array(
		$order->get_status(),
		array( 'pending', 'on-hold', 'failed', SR_STATUS_VERIFYING ),
		true
	);
}

/* -------------------------------------------------------------------------
 * The upload form
 * ---------------------------------------------------------------------- */

add_action( 'woocommerce_thankyou', 'sr_render_receipt_form', 20 );
add_action( 'woocommerce_view_order', 'sr_render_receipt_form', 20 );

/**
 * @param int $order_id Order id.
 * @return void
 */
function sr_render_receipt_form( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( ! $order instanceof WC_Order || 'bacs' !== $order->get_payment_method() ) {
		return;
	}

	if ( ! sr_receipt_accepted_for( $order ) ) {
		return;
	}

	$existing = '' !== sr_receipt_path( $order );
	$uploaded = (string) $order->get_meta( '_sr_receipt_name' );

	// Printed from here rather than from a page-level hook: the two pages this
	// form appears on have no notice hook in common, and a logged-in customer is
	// redirected to view-order, where an earlier `woocommerce_before_thankyou`
	// version of this never fired at all.
	sr_receipt_notice();
	?>
	<section class="sr-receipt">
		<h2 class="sr-receipt__title"><?php esc_html_e( 'Confirm your transfer', 'samina' ); ?></h2>

		<?php if ( $existing ) : ?>
			<p class="sr-receipt__done">
				<?php
				printf(
					/* translators: %s: the uploaded file's name. */
					esc_html__( 'We have your receipt (%s) and are checking it against our account. You will hear from us once the payment is confirmed.', 'samina' ),
					esc_html( $uploaded )
				);
				?>
			</p>
			<p class="sr-receipt__note"><?php esc_html_e( 'Uploaded the wrong file? Choose another and it will replace it.', 'samina' ); ?></p>
		<?php else : ?>
			<p class="sr-receipt__lead">
				<?php esc_html_e( 'Once you have made the transfer, upload a screenshot or a PDF of the confirmation here. We check it against our account and confirm your order — usually the same working day.', 'samina' ); ?>
			</p>
		<?php endif; ?>

		<form class="sr-receipt__form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sr_receipt_upload">
			<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>">
			<input type="hidden" name="order_key" value="<?php echo esc_attr( $order->get_order_key() ); ?>">
			<?php wp_nonce_field( 'sr_receipt_upload_' . $order->get_id(), 'sr_receipt_nonce' ); ?>

			<label class="sr-receipt__file">
				<span class="screen-reader-text"><?php esc_html_e( 'Transfer receipt', 'samina' ); ?></span>
				<input type="file" name="sr_receipt" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required>
			</label>

			<button type="submit" class="button sr-receipt__submit">
				<span><?php echo $existing ? esc_html__( 'Replace receipt', 'samina' ) : esc_html__( 'Upload receipt', 'samina' ); ?></span>
			</button>

			<p class="sr-receipt__note">
				<?php
				printf(
					/* translators: %s: maximum file size, e.g. "5 MB". */
					esc_html__( 'JPG, PNG, WEBP or PDF, up to %s. Your receipt is stored privately and seen only by our accounts team.', 'samina' ),
					esc_html( size_format( SR_RECEIPT_MAX_BYTES ) )
				);
				?>
			</p>
		</form>

		<?php sr_render_receipt_whatsapp( $order ); ?>
	</section>
	<?php
}

/**
 * The WhatsApp alternative to uploading.
 *
 * Not everyone will upload a file on a phone, and the house is reachable on
 * WhatsApp anyway — so say so, rather than letting a customer who cannot work
 * the upload assume the order is stuck. The order number is what ties the
 * message back to the order, so it is stated rather than implied.
 *
 * @param WC_Order $order Order.
 * @return void
 */
function sr_render_receipt_whatsapp( $order ) {
	$number = function_exists( 'sr_whatsapp_number' ) ? sr_whatsapp_number() : '';

	$line = sprintf(
		/* translators: %s: the customer's order number. */
		__( 'Prefer WhatsApp? Send the screenshot to us there instead, quoting order %s.', 'samina' ),
		'<strong>' . esc_html( $order->get_order_number() ) . '</strong>'
	);

	if ( '' === $number ) {
		printf( '<p class="sr-receipt__whatsapp">%s</p>', wp_kses_post( $line ) );
		return;
	}

	$url = sprintf(
		'https://wa.me/%1$s?text=%2$s',
		rawurlencode( $number ),
		rawurlencode(
			sprintf(
				/* translators: %s: the customer's order number. */
				__( 'Hello, here is my transfer receipt for order %s.', 'samina' ),
				$order->get_order_number()
			)
		)
	);

	printf(
		'<p class="sr-receipt__whatsapp">%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a></p>',
		wp_kses_post( $line ),
		esc_url( $url ),
		esc_html__( 'Open WhatsApp', 'samina' )
	);
}

/**
 * Show the outcome of an upload after the redirect back.
 *
 * A plain query flag rather than the message itself, so the URL can never be
 * used to print arbitrary text onto the customer's order page. Called from
 * sr_render_receipt_form(), which is the one code path both pages share.
 */
function sr_receipt_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a display-only flag, not an action.
	$result = isset( $_GET['sr_receipt'] ) ? sanitize_key( wp_unslash( $_GET['sr_receipt'] ) ) : '';

	$messages = array(
		'ok'      => array( 'success', __( 'Thank you — your receipt is with us. We will confirm your order as soon as the transfer is verified.', 'samina' ) ),
		'toobig'  => array( 'error', sprintf( /* translators: %s: maximum file size. */ __( 'That file is larger than %s. Please upload a smaller screenshot.', 'samina' ), size_format( SR_RECEIPT_MAX_BYTES ) ) ),
		'type'    => array( 'error', __( 'Please upload a JPG, PNG, WEBP or PDF.', 'samina' ) ),
		'failed'  => array( 'error', __( 'The upload did not complete. Please try again, or send the receipt to us directly.', 'samina' ) ),
		'nofile'  => array( 'error', __( 'Please choose a file first.', 'samina' ) ),
	);

	if ( ! isset( $messages[ $result ] ) ) {
		return;
	}

	printf(
		'<div class="woocommerce-message sr-receipt__flash sr-receipt__flash--%1$s" role="status">%2$s</div>',
		esc_attr( $messages[ $result ][0] ),
		esc_html( $messages[ $result ][1] )
	);
}

/* -------------------------------------------------------------------------
 * The upload itself
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_nopriv_sr_receipt_upload', 'sr_handle_receipt_upload' );
add_action( 'admin_post_sr_receipt_upload', 'sr_handle_receipt_upload' );

function sr_handle_receipt_upload() {
	$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
	$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';

	if ( ! $order_id
		|| ! isset( $_POST['sr_receipt_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sr_receipt_nonce'] ) ), 'sr_receipt_upload_' . $order_id )
	) {
		wp_die( esc_html__( 'That form has expired. Please reload your order page and try again.', 'samina' ), '', array( 'response' => 403 ) );
	}

	$order = sr_receipt_authorise( $order_id, $order_key );

	if ( ! $order instanceof WC_Order || ! sr_receipt_accepted_for( $order ) ) {
		wp_die( esc_html__( 'That order cannot accept a receipt.', 'samina' ), '', array( 'response' => 403 ) );
	}

	sr_receipt_redirect( $order, sr_store_receipt( $order ) );
}

/**
 * Validate and store the uploaded file.
 *
 * @param WC_Order $order Order.
 * @return string Result key for sr_receipt_notice().
 */
function sr_store_receipt( $order ) {
	if ( empty( $_FILES['sr_receipt'] ) || ! isset( $_FILES['sr_receipt']['error'] ) ) {
		return 'nofile';
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- individual members are validated below.
	$file = $_FILES['sr_receipt'];

	if ( UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
		return 'nofile';
	}

	if ( UPLOAD_ERR_INI_SIZE === (int) $file['error'] || UPLOAD_ERR_FORM_SIZE === (int) $file['error'] ) {
		return 'toobig';
	}

	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		return 'failed';
	}

	$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

	// The one check that cannot be spoofed by the request: PHP itself vouches
	// that this path came from a multipart upload and not from the filesystem.
	if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
		return 'failed';
	}

	if ( (int) $file['size'] > SR_RECEIPT_MAX_BYTES || filesize( $tmp ) > SR_RECEIPT_MAX_BYTES ) {
		return 'toobig';
	}

	$original = sanitize_file_name( (string) $file['name'] );
	$allowed  = sr_receipt_allowed_types();

	// Name and contents must agree, and both must be on the whitelist. WordPress
	// sniffs the file itself here rather than trusting the browser's Content-Type.
	$checked = wp_check_filetype_and_ext( $tmp, $original, $allowed );

	if ( empty( $checked['ext'] ) || empty( $checked['type'] ) || ! in_array( $checked['type'], $allowed, true ) ) {
		return 'type';
	}

	// An image must also decode as one. A PDF is left to the MIME sniff; it is
	// never executed or rendered by us.
	if ( 0 === strpos( $checked['type'], 'image/' ) && false === @getimagesize( $tmp ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a corrupt upload is an expected outcome, not an error to log.
		return 'type';
	}

	$dir = sr_receipt_dir();
	if ( is_wp_error( $dir ) ) {
		return 'failed';
	}

	// Unguessable, and derived from the sniffed extension rather than the one
	// the customer's file arrived with.
	$filename = sprintf( 'r-%d-%s.%s', $order->get_id(), bin2hex( random_bytes( 16 ) ), $checked['ext'] );
	$target   = $dir . $filename;

	if ( ! move_uploaded_file( $tmp, $target ) ) {
		return 'failed';
	}

	// Not world-readable: the guard files stop the web server serving it, this
	// stops another account on a shared host reading it off disk.
	@chmod( $target, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- fails harmlessly on hosts that do not permit it.

	sr_replace_previous_receipt( $order, $dir );

	$order->update_meta_data( '_sr_receipt_file', $filename );
	$order->update_meta_data( '_sr_receipt_name', $original );
	$order->update_meta_data( '_sr_receipt_time', (string) time() );

	$note = sprintf(
		/* translators: %s: original file name. */
		__( 'Customer uploaded a transfer receipt (%s). Awaiting verification against the bank account.', 'samina' ),
		$original
	);

	if ( SR_STATUS_VERIFYING !== $order->get_status() ) {
		$order->update_status( SR_STATUS_VERIFYING, $note );
	} else {
		$order->add_order_note( $note );
		$order->save();
	}

	sr_notify_receipt_uploaded( $order, $original );

	return 'ok';
}

/**
 * Delete the file this upload replaces, if any.
 *
 * @param WC_Order $order Order.
 * @param string   $dir   Receipts directory.
 * @return void
 */
function sr_replace_previous_receipt( $order, $dir ) {
	$previous = (string) $order->get_meta( '_sr_receipt_file' );

	if ( '' === $previous ) {
		return;
	}

	$path = $dir . basename( $previous );

	if ( is_file( $path ) ) {
		wp_delete_file( $path );
	}
}

/**
 * Send the customer back where they came from, with a flag.
 *
 * @param WC_Order $order  Order.
 * @param string   $result Result key.
 * @return void
 */
function sr_receipt_redirect( $order, $result ) {
	$url = $order->get_checkout_order_received_url();

	// A logged-in customer is better returned to the order in My account, which
	// survives a page refresh; the received URL is a one-time landing page.
	if ( $order->get_customer_id() > 0 && $order->get_customer_id() === get_current_user_id() ) {
		$url = $order->get_view_order_url();
	}

	wp_safe_redirect( add_query_arg( 'sr_receipt', $result, $url ) );
	exit;
}

/**
 * Tell the house a receipt is waiting.
 *
 * Plain wp_mail rather than a WooCommerce email class: this is an internal
 * notification with no customer-facing template, and adding a gateway-specific
 * email class would put a new entry in WooCommerce → Emails that nobody asked
 * for.
 *
 * @param WC_Order $order    Order.
 * @param string   $filename Original file name.
 * @return void
 */
function sr_notify_receipt_uploaded( $order, $filename ) {
	$to = apply_filters( 'sr_receipt_notification_email', get_option( 'admin_email' ) );

	if ( ! is_email( $to ) ) {
		return;
	}

	$subject = sprintf(
		/* translators: 1: site name, 2: order number. */
		__( '[%1$s] Transfer receipt uploaded for order %2$s', 'samina' ),
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		$order->get_order_number()
	);

	$lines = array(
		sprintf( __( 'Order: %s', 'samina' ), $order->get_order_number() ),
		sprintf( __( 'Customer: %s', 'samina' ), $order->get_formatted_billing_full_name() ),
		sprintf( __( 'Total: %s', 'samina' ), wp_strip_all_tags( $order->get_formatted_order_total() ) ),
		sprintf( __( 'File: %s', 'samina' ), $filename ),
		'',
		__( 'Check the amount against the bank account, then move the order to Processing — or contact the customer if it does not match.', 'samina' ),
		'',
		$order->get_edit_order_url(),
	);

	wp_mail( $to, $subject, implode( "\n", $lines ) );
}

/* -------------------------------------------------------------------------
 * The admin side
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes', function () {
	$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
		&& function_exists( 'wc_get_page_screen_id' )
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

	add_meta_box(
		'sr-receipt',
		__( 'Transfer receipt', 'samina' ),
		'sr_receipt_meta_box',
		$screen,
		'side',
		'high'
	);
} );

/**
 * @param WP_Post|WC_Order $post_or_order Order screen subject.
 * @return void
 */
function sr_receipt_meta_box( $post_or_order ) {
	$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	if ( '' === sr_receipt_path( $order ) ) {
		echo '<p>' . esc_html__( 'No receipt uploaded yet.', 'samina' ) . '</p>';
		return;
	}

	$when = (int) $order->get_meta( '_sr_receipt_time' );

	$url = wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'sr_receipt_download',
				'order'  => $order->get_id(),
			),
			admin_url( 'admin-post.php' )
		),
		'sr_receipt_download_' . $order->get_id()
	);

	printf(
		'<p><strong>%1$s</strong><br><span class="description">%2$s</span></p><p><a class="button button-primary" href="%3$s">%4$s</a></p>',
		esc_html( (string) $order->get_meta( '_sr_receipt_name' ) ),
		esc_html(
			$when
				? sprintf(
					/* translators: %s: human-readable time difference, e.g. "2 hours". */
					__( 'Uploaded %s ago', 'samina' ),
					human_time_diff( $when, time() )
				)
				: ''
		),
		esc_url( $url ),
		esc_html__( 'View receipt', 'samina' )
	);
}

add_action( 'admin_post_sr_receipt_download', function () {
	$order_id = isset( $_GET['order'] ) ? absint( wp_unslash( $_GET['order'] ) ) : 0;

	// Capability first: an unauthorised user should learn nothing, not even
	// whether the order exists.
	if ( ! $order_id || ! current_user_can( 'edit_shop_orders' ) ) {
		wp_die( esc_html__( 'You are not allowed to view this.', 'samina' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'sr_receipt_download_' . $order_id );

	$order = wc_get_order( $order_id );
	$path  = $order instanceof WC_Order ? sr_receipt_path( $order ) : '';

	if ( '' === $path ) {
		wp_die( esc_html__( 'That receipt is no longer on file.', 'samina' ), '', array( 'response' => 404 ) );
	}

	$type = wp_check_filetype( $path, sr_receipt_allowed_types() );

	nocache_headers();
	header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
	header( 'Content-Length: ' . filesize( $path ) );
	// inline, not attachment: the shop owner wants to glance at a screenshot,
	// not collect a downloads folder full of them.
	header( 'Content-Disposition: inline; filename="' . basename( $path ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );

	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a validated local file to the browser.
	exit;
} );

/**
 * Take the receipt with the order when the order is deleted.
 *
 * Without this, every cancelled or pruned order leaves a customer's bank
 * screenshot on disk indefinitely.
 */
add_action( 'woocommerce_before_delete_order', function ( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$path = sr_receipt_path( $order );

	if ( '' !== $path ) {
		wp_delete_file( $path );
	}
} );

// The legacy post-based store fires a different hook.
add_action( 'before_delete_post', function ( $post_id ) {
	if ( 'shop_order' === get_post_type( $post_id ) ) {
		do_action( 'woocommerce_before_delete_order', $post_id );
	}
} );

/**
 * Erase receipts when WordPress erases a customer's personal data.
 *
 * A photograph of someone's bank confirmation is personal data by any reading
 * of it, and the store has no other route to remove it.
 */
add_filter( 'woocommerce_privacy_remove_order_personal_data_meta', function ( $meta ) {
	$meta['_sr_receipt_name'] = 'text';
	$meta['_sr_receipt_time'] = 'numeric';
	return $meta;
} );

add_action( 'woocommerce_privacy_before_remove_order_personal_data', function ( $order ) {
	$path = sr_receipt_path( $order );

	if ( '' !== $path ) {
		wp_delete_file( $path );
		$order->delete_meta_data( '_sr_receipt_file' );
	}
} );
