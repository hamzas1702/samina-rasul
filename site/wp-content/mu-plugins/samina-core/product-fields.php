<?php
/**
 * Per-product custom fields, editable in the WooCommerce product data panel:
 * - _sr_delivery_time (e.g. "7–8 weeks") — shown on product pages as part of the
 *   made-to-order brand story; formals ≈ 7–8 weeks, bridals ≈ 10–12.
 * - _sr_product_video (attachment id) — a short film of the piece in movement,
 *   played as the first slide of the product gallery.
 */

defined( 'ABSPATH' ) || exit;

// Admin: field in Product data → General.
add_action( 'woocommerce_product_options_general_product_data', function () {
	woocommerce_wp_text_input( array(
		'id'          => '_sr_delivery_time',
		'label'       => __( 'Delivery time', 'samina' ),
		'placeholder' => __( 'e.g. 7–8 weeks', 'samina' ),
		'desc_tip'    => true,
		'description' => __( 'Made-to-order lead time shown on the product page (formals ≈ 7–8 weeks, bridals ≈ 10–12 weeks).', 'samina' ),
	) );

	sr_product_video_field();
} );

add_action( 'woocommerce_process_product_meta', function ( $post_id ) {
	if ( ! current_user_can( 'edit_product', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['_sr_delivery_time'] ) ) {
		update_post_meta( $post_id, '_sr_delivery_time', sanitize_text_field( wp_unslash( $_POST['_sr_delivery_time'] ) ) );
	}

	sr_save_product_video( $post_id );
} );

/**
 * ---------------------------------------------------------------------------
 * Product video
 * ---------------------------------------------------------------------------
 *
 * A media-library id rather than a URL. A URL field invites someone to paste a
 * YouTube link, which would put a third-party player and its cookies on every
 * product page; an id can be checked against the library, which is what makes
 * the front end safe to render without escaping guesswork.
 */

/**
 * Video attachment id for a product, validated against the media library.
 *
 * @param int $product_id Product id.
 * @return int Attachment id, or 0 when unset or no longer a video in the library.
 */
function sr_product_video_id( $product_id ) {
	$attachment_id = (int) get_post_meta( (int) $product_id, '_sr_product_video', true );

	if ( $attachment_id < 1 ) {
		return 0;
	}

	// The attachment may have been deleted, or replaced by something that is
	// not a video, long after the id was saved.
	if ( 'attachment' !== get_post_type( $attachment_id ) ) {
		return 0;
	}

	return 0 === strpos( (string) get_post_mime_type( $attachment_id ), 'video/' ) ? $attachment_id : 0;
}

/**
 * The media picker in Product data → General.
 */
function sr_product_video_field() {
	global $post;

	// wp.media is what the picker below is built on. WooCommerce happens to
	// enqueue it on this screen already, but relying on that would make this
	// field break silently the day it stops.
	wp_enqueue_media();

	$product_id    = $post instanceof WP_Post ? (int) $post->ID : 0;
	$attachment_id = sr_product_video_id( $product_id );
	$filename      = $attachment_id ? basename( (string) get_attached_file( $attachment_id ) ) : '';
	?>
	<div class="options_group sr-video-field">
		<p class="form-field">
			<label for="sr_product_video_id"><?php esc_html_e( 'Product video', 'samina' ); ?></label>
			<input
				type="hidden"
				id="sr_product_video_id"
				name="_sr_product_video"
				value="<?php echo esc_attr( $attachment_id ? (string) $attachment_id : '' ); ?>">
			<button type="button" class="button" id="sr_product_video_pick"><?php esc_html_e( 'Choose video', 'samina' ); ?></button>
			<button type="button" class="button" id="sr_product_video_clear"<?php echo $attachment_id ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Remove', 'samina' ); ?></button>
			<span id="sr_product_video_name" class="description" style="display:block;margin-top:.4em"><?php echo esc_html( $filename ); ?></span>
			<span class="description">
				<?php esc_html_e( 'Plays as the first slide of the gallery. MP4 (H.264) is the only format every browser can play; keep it short and under about 15 MB — it is muted and loops, so it is a moving photograph, not a film.', 'samina' ); ?>
			</span>
		</p>
	</div>
	<script>
	( function ( $ ) {
		var frame;
		$( '#sr_product_video_pick' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( frame ) { frame.open(); return; }
			frame = wp.media( {
				title: <?php echo wp_json_encode( __( 'Select product video', 'samina' ) ); ?>,
				library: { type: 'video' },
				button: { text: <?php echo wp_json_encode( __( 'Use this video', 'samina' ) ); ?> },
				multiple: false
			} );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				$( '#sr_product_video_id' ).val( a.id );
				$( '#sr_product_video_name' ).text( a.filename || a.url );
				$( '#sr_product_video_clear' ).show();
			} );
			frame.open();
		} );
		$( '#sr_product_video_clear' ).on( 'click', function ( e ) {
			e.preventDefault();
			$( '#sr_product_video_id' ).val( '' );
			$( '#sr_product_video_name' ).text( '' );
			$( this ).hide();
		} );
	} )( jQuery );
	</script>
	<?php
}

/**
 * Persist the picker's choice.
 *
 * The posted id is never trusted: it is only stored once it resolves to a video
 * attachment that actually exists, so a hand-crafted POST cannot make the
 * product page embed an arbitrary post id.
 *
 * @param int $post_id Product id.
 * @return void
 */
function sr_save_product_video( $post_id ) {
	if ( ! isset( $_POST['_sr_product_video'] ) ) {
		return;
	}

	$attachment_id = absint( wp_unslash( $_POST['_sr_product_video'] ) );

	if ( $attachment_id < 1
		|| 'attachment' !== get_post_type( $attachment_id )
		|| 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'video/' )
	) {
		delete_post_meta( $post_id, '_sr_product_video' );
		return;
	}

	update_post_meta( $post_id, '_sr_product_video', $attachment_id );
}

/**
 * The video, as the first slide of the product gallery.
 *
 * Prepended to the featured image's own markup rather than hooked onto
 * `woocommerce_product_thumbnails`, because that action fires *after* the
 * featured image and would make the film slide two.
 *
 * It has to be a real slide, not a block bolted underneath: FlexSlider is
 * initialised with the selector
 * `.woocommerce-product-gallery__wrapper > .woocommerce-product-gallery__image`
 * (woocommerce/assets/js/frontend/single-product.js), and builds its thumbnail
 * strip from each slide's `data-thumb`. Carrying both means the film gets its
 * own thumbnail and its own position in the strip for free, and nothing about
 * the slider has to be re-taught.
 *
 * Muted, looping and playsinline: a film that autoplays with sound is blocked
 * by every browser and resented by every visitor. `preload="metadata"` keeps
 * the page weight to a poster frame until someone presses play - which matters
 * on a catalogue whose customers are largely on mobile data.
 *
 * @param string $html          Featured-image slide markup.
 * @param int    $thumbnail_id  Featured-image attachment id.
 * @return string
 */
add_filter( 'woocommerce_single_product_image_thumbnail_html', function ( $html, $thumbnail_id ) {
	global $product;

	if ( ! $product instanceof WC_Product || ! is_product() ) {
		return $html;
	}

	// Only against the featured image, or the film would be prepended to every
	// gallery slide - this filter runs for each of them.
	if ( (int) $thumbnail_id !== (int) $product->get_image_id() ) {
		return $html;
	}

	$video = sr_product_video_slide_html( $product );

	return '' === $video ? $html : $video . $html;
}, 20, 2 );

/**
 * Markup for the video slide, or '' when the product has no video.
 *
 * @param WC_Product $product Product.
 * @return string
 */
function sr_product_video_slide_html( $product ) {
	$attachment_id = sr_product_video_id( $product->get_id() );
	if ( ! $attachment_id ) {
		return '';
	}

	$src = wp_get_attachment_url( $attachment_id );
	if ( ! $src ) {
		return '';
	}

	// The featured image doubles as the poster frame and as the slide's entry in
	// the thumbnail strip, so the film reads as part of the same set rather than
	// as a black rectangle among the photographs.
	$poster = (string) get_the_post_thumbnail_url( $product->get_id(), 'woocommerce_single' );
	$thumb  = (string) get_the_post_thumbnail_url( $product->get_id(), 'gallery_thumbnail' );

	return sprintf(
		'<div data-thumb="%1$s" data-thumb-alt="%2$s" class="woocommerce-product-gallery__image sr-gallery-video">
			<span class="sr-gallery-video__label" aria-hidden="true">%3$s</span>
			<video class="sr-gallery-video__player" controls muted loop playsinline preload="metadata"%4$s>
				<source src="%5$s" type="%6$s">
				%7$s
			</video>
		</div>',
		esc_url( $thumb ),
		esc_attr__( 'Video of the piece', 'samina' ),
		esc_html__( 'The piece in movement', 'samina' ),
		'' !== $poster ? ' poster="' . esc_url( $poster ) . '"' : '',
		esc_url( $src ),
		esc_attr( (string) get_post_mime_type( $attachment_id ) ),
		esc_html__( 'Your browser cannot play this video.', 'samina' )
	);
}

/**
 * Frontend: delivery estimate under the price / before the form.
 *
 * A named function, not a closure, so the active theme can fold the lead time
 * into a note of its own design with remove_action() rather than showing the
 * same answer twice in one viewport.
 */
function sr_render_delivery_note() {
	global $product;
	if ( ! $product ) {
		return;
	}
	$time = get_post_meta( $product->get_id(), '_sr_delivery_time', true );
	if ( $time ) {
		printf(
			'<p class="sr-delivery-note">%s</p>',
			esc_html( sprintf(
				/* translators: %s: lead time, e.g. "7–8 weeks" */
				__( 'Each piece is hand finished to order, ready in %s.', 'samina' ),
				$time
			) )
		);
	}
}
add_action( 'woocommerce_single_product_summary', 'sr_render_delivery_note', 25 );
