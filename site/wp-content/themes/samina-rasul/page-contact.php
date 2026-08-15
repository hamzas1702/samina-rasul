<?php
/**
 * Contact.
 *
 * A two-column split: the atelier's own details on the deep ground, held
 * sticky, against the form on cream. The details are the answer for most
 * visitors — a phone number and a WhatsApp link close more inquiries than a
 * form does — so they lead, and the form supports them rather than the reverse.
 *
 * A template rather than page content: the layout then ships with the theme and
 * survives a deploy, which page content (a database row) does not.
 *
 * @package samina-rasul
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sr_status   = isset( $_GET['sr_contact'] ) ? sanitize_key( wp_unslash( $_GET['sr_contact'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag, no action taken.
$sr_subjects = sr_contact_subjects();

$sr_whats = function_exists( 'sr_whatsapp_number' ) ? sr_whatsapp_number() : '';
$sr_phone = function_exists( 'sr_contact_phone_display' ) ? sr_contact_phone_display() : $sr_whats;
$sr_email = function_exists( 'sr_contact_email' ) ? sr_contact_email() : get_option( 'admin_email' );

// The store address doubles as the atelier address; WooCommerce already holds it.
$sr_address = array();
if ( function_exists( 'WC' ) && WC()->countries ) {
	foreach ( array( WC()->countries->get_base_address(), WC()->countries->get_base_city() ) as $sr_line ) {
		if ( '' !== trim( (string) $sr_line ) ) {
			$sr_address[] = $sr_line;
		}
	}
}
?>

<div class="sr-contact">

	<aside class="sr-contact__info">
		<span class="sr-contact__art" aria-hidden="true"><?php echo sr_arch_motif_svg(); // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG. ?></span>

		<div class="sr-contact__info-inner">
			<span class="sr-eyebrow"><?php esc_html_e( 'Samina Rasul Atelier', 'samina-rasul' ); ?></span>
			<h1 class="sr-contact__title"><?php echo wp_kses_post( __( 'Begin the <em>conversation.</em>', 'samina-rasul' ) ); ?></h1>
			<p class="sr-contact__lede"><?php esc_html_e( 'Whether you are asking about a particular piece, tracking an order, or arranging a visit to the studio, we are glad to help.', 'samina-rasul' ); ?></p>

			<ul class="sr-contact__details">
				<?php if ( $sr_email ) : ?>
					<li>
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
						<div>
							<span class="sr-contact__label"><?php esc_html_e( 'General inquiries', 'samina-rasul' ); ?></span>
							<a class="sr-contact__value" href="<?php echo esc_url( 'mailto:' . $sr_email ); ?>"><?php echo esc_html( $sr_email ); ?></a>
						</div>
					</li>
				<?php endif; ?>

				<?php if ( '' !== $sr_whats ) : ?>
					<li>
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
						<div>
							<span class="sr-contact__label"><?php esc_html_e( 'WhatsApp', 'samina-rasul' ); ?></span>
							<a class="sr-contact__value" href="<?php echo esc_url( 'https://wa.me/' . rawurlencode( $sr_whats ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $sr_phone ); ?></a>
						</div>
					</li>
				<?php endif; ?>

				<?php if ( $sr_address ) : ?>
					<li>
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
						<div>
							<span class="sr-contact__label"><?php esc_html_e( 'The atelier', 'samina-rasul' ); ?></span>
							<p class="sr-contact__value"><?php echo esc_html( implode( ', ', $sr_address ) ); ?></p>
						</div>
					</li>
				<?php endif; ?>
			</ul>

			<?php
			$sr_social = sr_footer_social_html();
			if ( '' !== $sr_social ) {
				echo '<div class="sr-contact__social">' . $sr_social . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- assembled from escaped parts.
			}
			?>
		</div>
	</aside>

	<div class="sr-contact__form-col">
		<header class="sr-contact__form-head" data-sr-reveal>
			<h2><?php esc_html_e( 'Send a message', 'samina-rasul' ); ?></h2>
			<p><?php esc_html_e( 'Allow up to 24 hours for a reply from client care. For a bridal commission, book a consultation instead — it starts the design conversation properly.', 'samina-rasul' ); ?></p>
		</header>

		<?php if ( '' !== $sr_status ) : ?>
			<p class="sr-contact__status sr-contact__status--<?php echo esc_attr( 'ok' === $sr_status ? 'ok' : 'error' ); ?>" role="status">
				<?php
				if ( 'ok' === $sr_status ) {
					esc_html_e( 'Thank you. Your message is with the atelier and we will reply shortly.', 'samina-rasul' );
				} elseif ( 'throttled' === $sr_status ) {
					esc_html_e( 'That message has already been sent. Please wait a moment before sending another.', 'samina-rasul' );
				} elseif ( 'unsent' === $sr_status ) {
					printf(
						/* translators: %s: mailto link to the atelier. */
						esc_html__( 'Your message could not be sent just now. Please write to us at %s and we will pick it up from there.', 'samina-rasul' ),
						'<a href="' . esc_url( 'mailto:' . $sr_email ) . '">' . esc_html( $sr_email ) . '</a>'
					);
				} else {
					esc_html_e( 'That did not go through. Please check the fields and try again.', 'samina-rasul' );
				}
				?>
			</p>
		<?php endif; ?>

		<form id="sr-contact-form" class="sr-contact__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-sr-reveal>
			<input type="hidden" name="action" value="sr_contact">
			<?php wp_nonce_field( 'sr_contact', 'sr_contact_nonce' ); ?>

			<div class="sr-field">
				<input type="text" id="sr_name" name="sr_name" placeholder=" " maxlength="100" required autocomplete="name">
				<label for="sr_name"><?php esc_html_e( 'Full name', 'samina-rasul' ); ?></label>
			</div>

			<div class="sr-field">
				<input type="email" id="sr_email" name="sr_email" placeholder=" " required autocomplete="email">
				<label for="sr_email"><?php esc_html_e( 'Email address', 'samina-rasul' ); ?></label>
			</div>

			<div class="sr-field">
				<input type="tel" id="sr_phone" name="sr_phone" placeholder=" " maxlength="40" autocomplete="tel">
				<label for="sr_phone"><?php esc_html_e( 'Phone number', 'samina-rasul' ); ?></label>
			</div>

			<div class="sr-field sr-field--select">
				<select id="sr_subject" name="sr_subject" required>
					<?php foreach ( $sr_subjects as $sr_key => $sr_label ) : ?>
						<option value="<?php echo esc_attr( $sr_key ); ?>"><?php echo esc_html( wp_strip_all_tags( $sr_label ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="sr_subject"><?php esc_html_e( 'Nature of inquiry', 'samina-rasul' ); ?></label>
			</div>

			<div class="sr-field sr-field--full">
				<textarea id="sr_message" name="sr_message" placeholder=" " rows="4" maxlength="4000" required></textarea>
				<label for="sr_message"><?php esc_html_e( 'Your message', 'samina-rasul' ); ?></label>
			</div>

			<?php // Honeypot: a real visitor never fills this; bots usually do. ?>
			<div class="sr-hp" aria-hidden="true">
				<label for="sr_website"><?php esc_html_e( 'Leave this field empty', 'samina-rasul' ); ?></label>
				<input type="text" name="sr_website" id="sr_website" tabindex="-1" autocomplete="off">
			</div>

			<button type="submit" class="button sr-contact__submit">
				<span><?php esc_html_e( 'Send message', 'samina-rasul' ); ?></span>
			</button>
		</form>

	</div>

</div>

<?php
/*
 * The same closing invitation every archive ends on. It replaces the bridal
 * aside that used to sit under the form: a second consultation prompt inside
 * the form column competed with the send button directly above it, and the
 * page now ends the way the rest of the site does.
 */
sr_shop_cta(
	__( 'Bespoke', 'samina-rasul' ),
	__( 'Planning something <em>entirely your own?</em>', 'samina-rasul' ),
	__( 'Book a consultation', 'samina-rasul' )
);
?>

<?php get_footer(); ?>
