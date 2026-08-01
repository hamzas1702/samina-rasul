<?php
/**
 * Samina Rasul child theme.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Header: pull the mini-cart out of the primary-nav row and into the icon
 * row alongside search, then add an account icon next to it. The nav row
 * is left holding only the primary menu, which CSS centers on its own.
 * Visual left-to-right order (search / account / cart) is set with CSS
 * `order` in style.css, not hook priority - priority only has to land
 * each callback inside the header's first .col-full (i.e. below 41,
 * storefront_header_container_close's priority).
 */
function sr_rehook_header_controls() {
	// Storefront only defines storefront_header_cart() when WooCommerce is
	// active. Re-hooking the name unconditionally turns a routine WooCommerce
	// deactivation into a fatal on every page, so bail when it is absent.
	if ( ! function_exists( 'storefront_header_cart' ) ) {
		return;
	}
	remove_action( 'storefront_header', 'storefront_header_cart', 60 );
	add_action( 'storefront_header', 'storefront_header_cart', 38 );
	add_action( 'storefront_header', 'sr_header_account_link', 39 );
}
add_action( 'after_setup_theme', 'sr_rehook_header_controls' );

/**
 * Account icon link for the header icon row. Routes to WooCommerce's
 * My Account page, which natively serves the login/register form when
 * the visitor is logged out and the account dashboard when logged in.
 */
function sr_header_account_link() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$account_url = wc_get_page_permalink( 'myaccount' );
	if ( ! $account_url ) {
		return;
	}
	printf(
		'<a class="sr-header-icon sr-header-icon--account" href="%1$s">%2$s<span class="screen-reader-text">%3$s</span></a>',
		esc_url( $account_url ),
		'<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>', // phpcs:ignore WordPress.Security.EscapeOutput -- static, hand-written inline SVG, no user input.
		esc_html__( 'My Account', 'samina-rasul' )
	);
}

/**
 * URL for a site image, or an empty string when the file has not been
 * supplied yet. Lets templates fall back to a CSS placeholder instead of
 * emitting a broken <img> while photography is still in production.
 *
 * Looks in the theme's assets/images/ first, so an art-directed image shipped
 * with the theme always wins, then in wp-content/uploads/ so the client can
 * drop a file in over FTP or the media library without touching the theme.
 * Uploads lookups accept a sub-path ('2026/07/shot.jpg'); theme lookups are
 * flat, so only the basename is used there.
 *
 * @param string $file File name in assets/images/, or a path relative to the uploads dir.
 * @return string Image URL, or '' when absent.
 */
function sr_image_url( $file ) {
	$name = basename( $file );
	if ( '' !== $name && is_readable( get_stylesheet_directory() . '/assets/images/' . $name ) ) {
		return get_stylesheet_directory_uri() . '/assets/images/' . $name;
	}

	// Normalise separators and refuse anything that tries to climb out of uploads.
	$relative = ltrim( str_replace( '\\', '/', (string) $file ), '/' );
	if ( '' === $relative || preg_match( '#(^|/)\.\.(/|$)#', $relative ) ) {
		return '';
	}

	$uploads = wp_get_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return '';
	}

	$path = $uploads['basedir'] . '/' . $relative;
	// realpath() resolves symlinks so the containment check cannot be fooled.
	$real = realpath( $path );
	$base = realpath( $uploads['basedir'] );
	if ( false === $real || false === $base || 0 !== strpos( $real, $base . DIRECTORY_SEPARATOR ) ) {
		return '';
	}
	if ( ! is_readable( $real ) ) {
		return '';
	}

	return $uploads['baseurl'] . '/' . $relative;
}

/**
 * Safe permalink for a taxonomy term referenced by slug.
 *
 * get_term_link() returns a WP_Error when the term has been renamed or
 * deleted; passing that straight into esc_url() throws on PHP 8. Homepage
 * cards link to editable terms, so degrade to the shop page instead of
 * fatalling the front page.
 *
 * @param string $slug     Term slug.
 * @param string $taxonomy Taxonomy name.
 * @return string Permalink, or the shop URL as a fallback.
 */
function sr_term_url( $slug, $taxonomy ) {
	$link = get_term_link( $slug, $taxonomy );
	if ( is_string( $link ) && '' !== $link ) {
		return $link;
	}
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
	return $shop ? $shop : home_url( '/' );
}

/**
 * Shop page URL, guaranteed non-empty.
 *
 * get_permalink( wc_get_page_id( 'shop' ) ) returns false when no Shop page is
 * configured, and esc_url( false ) yields '', so the button renders href=""
 * and silently reloads the current page instead of going anywhere.
 *
 * @return string Shop URL, or the site root as a fallback.
 */
function sr_shop_url() {
	$id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
	if ( $id > 0 ) {
		$url = get_permalink( $id );
		if ( is_string( $url ) && '' !== $url ) {
			return $url;
		}
	}
	return home_url( '/' );
}

/**
 * Card artwork for a taxonomy term.
 *
 * Renders the term's own image when one is set - WooCommerce stores product
 * category images as the `thumbnail_id` term meta, so a client uploading a
 * category image in wp-admin lights these cards up with no code change.
 * Until then it falls back to the theme's branded CSS placeholder, the same
 * treatment the hero and split sections use.
 *
 * Alt text is intentionally empty: each card is a single link whose heading
 * already names the collection, so a described image would be announced twice.
 *
 * @param string $slug     Term slug.
 * @param string $taxonomy Taxonomy name.
 * @param string $tone     Placeholder tone, 'warm' or 'deep'.
 * @return string Escaped markup.
 */
function sr_term_card_media( $slug, $taxonomy, $tone = 'warm' ) {
	$term = get_term_by( 'slug', $slug, $taxonomy );

	if ( $term && ! is_wp_error( $term ) ) {
		$image_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		if ( $image_id > 0 ) {
			$image = wp_get_attachment_image(
				$image_id,
				'large',
				false,
				array(
					'class'   => 'sr-route__img',
					'alt'     => '',
					'loading' => 'lazy',
					// Cards are a third of the content width on desktop; without this
					// the browser assumes 100vw and pulls a needlessly large file.
					'sizes'   => '(max-width: 700px) 100vw, (max-width: 1200px) 33vw, 380px',
				)
			);
			if ( $image ) {
				return $image;
			}
		}
	}

	return sprintf(
		'<span class="sr-route__ph sr-route__ph--%s" aria-hidden="true"></span>',
		'deep' === $tone ? 'deep' : 'warm'
	);
}

/**
 * Cache-busting version for a theme file: its mtime, or the theme version when
 * the file is missing. A bare filemtime() on an absent file emits a warning on
 * every request and versions the asset as an empty string.
 *
 * @param string $relative Path relative to the stylesheet directory.
 * @return string Version string.
 */
function sr_asset_version( $relative ) {
	$path = get_stylesheet_directory() . '/' . ltrim( $relative, '/' );
	if ( file_exists( $path ) ) {
		return (string) filemtime( $path );
	}
	return (string) wp_get_theme()->get( 'Version' );
}

add_action( 'wp_enqueue_scripts', function () {
	$assets  = get_stylesheet_directory_uri() . '/assets/js';
	$css_ver = sr_asset_version( 'style.css' );
	$js_ver  = sr_asset_version( 'assets/js/sr-ui.js' );
	wp_enqueue_style( 'sr-fonts', get_stylesheet_directory_uri() . '/fonts/fonts-local.css', array(), '1' );
	wp_enqueue_style( 'storefront-parent', get_template_directory_uri() . '/style.css', array( 'sr-fonts' ), $css_ver );
	/* Storefront enqueues its WooCommerce and icon sheets after the child theme,
	 * so equal-specificity child rules lose the cascade. Declaring them as
	 * dependencies forces this stylesheet out last, where a child theme belongs. */
	$deps = array( 'storefront-parent' );
	foreach ( array( 'storefront-icons', 'storefront-woocommerce-style' ) as $handle ) {
		if ( wp_style_is( $handle, 'registered' ) ) {
			$deps[] = $handle;
		}
	}
	wp_enqueue_style( 'samina-rasul', get_stylesheet_uri(), $deps, $css_ver );

	/* No animation bundle on the commerce path. None of these effects appear on
	 * cart, checkout or account, and ~116 KB of JS that can only cost a
	 * conversion has no business loading there. */
	$sr_commerce_page = ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) );
	if ( ! $sr_commerce_page ) {
		wp_enqueue_script( 'sr-gsap', $assets . '/gsap.min.js', array(), '3.12.5', true );
		wp_enqueue_script( 'sr-scrolltrigger', $assets . '/ScrollTrigger.min.js', array( 'sr-gsap' ), '3.12.5', true );
		wp_enqueue_script( 'sr-ui', $assets . '/sr-ui.js', array( 'sr-gsap', 'sr-scrolltrigger' ), $js_ver, true );
	}
	/* Priority 25, not 20: Storefront registers its WooCommerce sheet on this
	 * same hook at 20, so the dependency above only resolves once it has run. */
}, 25 );

/**
 * Storefront auto-enqueues every child theme's style.css a second time
 * under the `storefront-child-style` handle (class-storefront.php,
 * `child_scripts()`, priority 30 on this same hook) as a safety net for
 * child themes that don't enqueue their own CSS. This theme already does
 * (handle `samina-rasul` above, correctly chained to the parent), so the
 * auto copy is a duplicate HTTP request and duplicate parse on every
 * page load - drop it once Storefront has registered it.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_style( 'storefront-child-style' );
	wp_deregister_style( 'storefront-child-style' );
}, 31 );

/**
 * Pre-paint flags: preloader (once per session) and curtain-arrival state.
 * Runs inline in <head> so the overlay states exist before first paint.
 */
add_action( 'wp_head', function () {
	?>
	<script>
	(function () {
		try {
			var d = document.documentElement;
			var rm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			if (rm) return;
			d.classList.add('sr-anim'); /* JS + motion OK: CSS may hold pre-reveal states */
			if (!sessionStorage.getItem('srSeen') && <?php echo is_front_page() ? 'true' : 'false'; ?>) { d.classList.add('sr-preload'); }
			if (sessionStorage.getItem('srCurtain')) { d.classList.add('sr-curtain-in'); }

			/* Watchdog. These classes hide every reveal element and, on the front
			 * page, hold a full-screen preloader over a locked body - all of it
			 * undone only by a successful sr-ui.js init(). If the animation bundle
			 * 404s, is blocked, or throws, the visitor is left with a blank,
			 * unscrollable page that still returned HTTP 200. Strip the states
			 * unless init() reports in first; it cancels this on success. */
			window.__srFailSafe = setTimeout(function () {
				d.classList.remove('sr-anim', 'sr-preload', 'sr-curtain-in');
				var pre = document.querySelector('.sr-preloader');
				if (pre && pre.parentNode) { pre.parentNode.removeChild(pre); }
			}, 2500);
		} catch (e) {}
	})();
	</script>
	<?php
}, 1 );

/**
 * Overlay chrome: preloader, page-transition curtain, custom cursor.
 */
add_action( 'wp_footer', function () {
	?>
	<div class="sr-preloader" aria-hidden="true">
		<span class="sr-preloader__word"><?php
			foreach ( str_split( 'SAMINA RASUL' ) as $ch ) {
				echo '<span>' . ( ' ' === $ch ? '&nbsp;' : esc_html( $ch ) ) . '</span>';
			}
		?></span>
	</div>
	<div class="sr-curtain" aria-hidden="true"></div>
	<div class="sr-cursor-ring" aria-hidden="true"></div>
	<div class="sr-cursor-dot" aria-hidden="true"></div>
	<?php
}, 5 );

/**
 * Header icon-row behaviour: the search field expands from an icon on
 * demand, and the cart icon carries a live item-count badge. Deliberately
 * framework-independent (no GSAP dependency) so it keeps working even if
 * the animation bundle fails to load.
 */
add_action( 'wp_footer', function () {
	?>
	<script>
	(function () {
		var SEARCH_ICON_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.2" y2="16.2"/></svg>';
		var CART_ICON_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>';

		var searchWrap = document.querySelector('.site-header .site-search');
		var form = searchWrap && searchWrap.querySelector('form.woocommerce-product-search');
		var input = form && form.querySelector('input.search-field');
		var submitBtn = form && form.querySelector('button[type="submit"]');
		if (searchWrap && form && input && submitBtn) {
			submitBtn.insertAdjacentHTML('afterbegin', SEARCH_ICON_SVG);
			submitBtn.addEventListener('click', function (e) {
				if (!searchWrap.classList.contains('is-open')) {
					e.preventDefault();
					searchWrap.classList.add('is-open');
					input.focus();
				}
			});
			input.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') {
					input.value = '';
					searchWrap.classList.remove('is-open');
					submitBtn.focus();
				}
			});
			document.addEventListener('click', function (e) {
				if (searchWrap.classList.contains('is-open') && !searchWrap.contains(e.target) && !input.value) {
					searchWrap.classList.remove('is-open');
				}
			});
		}

		/* Observe the stable #site-header-cart wrapper, not the .cart-contents
		 * link itself - wc-cart-fragments.js replaces that link wholesale on
		 * its post-load AJAX refresh (and again on every add-to-cart), which
		 * would otherwise detach an observer bound to the original node. */
		var cartWrap = document.getElementById('site-header-cart');
		if (cartWrap && 'MutationObserver' in window) {
			var syncCartBadge = function () {
				var link = cartWrap.querySelector('.cart-contents');
				if (!link) { return; }
				if (!link.querySelector('svg')) {
					link.insertAdjacentHTML('afterbegin', CART_ICON_SVG);
				}
				var countEl = link.querySelector('.count');
				var count = countEl ? ( parseInt( countEl.textContent, 10 ) || 0 ) : 0;
				link.classList.toggle('has-items', count > 0);
				link.setAttribute('data-count', String(count));
			};
			syncCartBadge();
			new MutationObserver(syncCartBadge).observe(cartWrap, { childList: true, subtree: true, characterData: true });
		}
	})();
	</script>
	<?php
}, 6 );

/**
 * The house ornament: concentric rules with twelve radiating petals. Shared by
 * the homepage and the category archives so the mark is defined once.
 *
 * @return string Inline SVG.
 */
function sr_ornament_svg() {
	static $svg = null;
	if ( null !== $svg ) {
		return $svg;
	}

	$petals = '';
	for ( $i = 0; $i < 12; $i++ ) {
		$petals .= '<g transform="rotate(' . ( $i * 30 ) . ' 100 100)"><path d="M100 4 C 108 28, 108 44, 100 62 C 92 44, 92 28, 100 4 Z"/><circle cx="100" cy="70" r="1.6" fill="currentColor" stroke="none"/></g>';
	}

	$svg = '<svg class="sr-ornament" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<circle cx="100" cy="100" r="96" stroke="currentColor" stroke-width="0.6"/>
		<circle cx="100" cy="100" r="78" stroke="currentColor" stroke-width="0.4"/>
		<circle cx="100" cy="100" r="34" stroke="currentColor" stroke-width="0.5"/>
		<g stroke="currentColor" stroke-width="0.5">' . $petals . '</g></svg>';

	return $svg;
}

/**
 * Name of the first term a product holds in a taxonomy.
 *
 * @param int    $post_id  Product ID.
 * @param string $taxonomy Taxonomy name.
 * @return string Term name, or '' when the product has none.
 */
function sr_first_term_name( $post_id, $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	$first = reset( $terms );
	return $first instanceof WP_Term ? $first->name : '';
}

/**
 * Card badge text: the collection line and the SKU, e.g. "Dhanak · DK-001".
 * Either half may be missing, in which case only the other is shown; with
 * neither, callers skip the badge entirely.
 *
 * @param WC_Product $product Product.
 * @return string Unescaped badge text, '' when there is nothing to show.
 */
function sr_product_badge_text( $product ) {
	$bits = array();

	$collection = sr_first_term_name( $product->get_id(), 'sr_collection' );
	if ( '' !== $collection ) {
		$bits[] = $collection;
	}

	$sku = $product->get_sku();
	if ( '' !== $sku ) {
		$bits[] = $sku;
	}

	return implode( ' · ', $bits );
}

/**
 * A search or category that returns nothing is otherwise a dead end: one blue
 * notice and no way onward. Offer a retry field and the collections.
 */
function sr_no_products_found_recovery() {
	$cats = array(
		'formals' => __( 'Formals', 'samina-rasul' ),
		'bridals' => __( 'Bridals', 'samina-rasul' ),
	);
	?>
	<div class="sr-deadend">
		<p><?php esc_html_e( 'Nothing matched that search. Try another word, or start from one of the collections.', 'samina-rasul' ); ?></p>
		<?php if ( function_exists( 'get_product_search_form' ) ) { get_product_search_form(); } ?>
		<div class="sr-deadend__actions">
			<?php foreach ( $cats as $slug => $label ) : ?>
				<a class="button sr-ghost" href="<?php echo esc_url( sr_term_url( $slug, 'product_cat' ) ); ?>"><span><?php echo esc_html( $label ); ?></span></a>
			<?php endforeach; ?>
			<a class="button" href="<?php echo esc_url( sr_shop_url() ); ?>"><span><?php esc_html_e( 'All pieces', 'samina-rasul' ); ?></span></a>
		</div>
	</div>
	<?php
}
add_action( 'woocommerce_no_products_found', 'sr_no_products_found_recovery', 20 );

/**
 * The block cart's empty state is an emoji and a dead end. Add a way back to
 * the collections beneath it.
 */
function sr_empty_cart_recovery() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}
	?>
	<script>
	(function () {
		var place = function () {
			var empty = document.querySelector('.wp-block-woocommerce-empty-cart-block');
			if (!empty || document.getElementById('sr-cart-out')) { return true; }
			var box = document.createElement('div');
			box.id = 'sr-cart-out';
			box.className = 'sr-deadend';
			box.innerHTML = <?php
				echo wp_json_encode(
					'<div class="sr-deadend__actions">'
					. '<a class="button" href="' . esc_url( sr_term_url( 'formals', 'product_cat' ) ) . '"><span>' . esc_html__( 'Shop Formals', 'samina-rasul' ) . '</span></a>'
					. '<a class="button sr-ghost" href="' . esc_url( sr_term_url( 'bridals', 'product_cat' ) ) . '"><span>' . esc_html__( 'Explore Bridals', 'samina-rasul' ) . '</span></a>'
					. '</div>'
				);
			?>;
			empty.insertAdjacentElement('afterbegin', box);
			return true;
		};
		if (!place()) {
			var mo = new MutationObserver(function () { if (place()) { mo.disconnect(); } });
			mo.observe(document.body, { childList: true, subtree: true });
			setTimeout(function () { mo.disconnect(); }, 10000);
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'sr_empty_cart_recovery', 21 );

/**
 * Replace WooCommerce's grey camera placeholder with the house ornament
 * everywhere it appears - product page gallery, block grids, cross-sells -
 * not just in the loop cards. A garment at this price point showing a stock
 * broken-image box is worse than showing nothing.
 *
 * @param string $html Placeholder markup from WooCommerce.
 * @return string
 */
function sr_branded_placeholder_img( $html ) {
	unset( $html );
	return '<span class="sr-card-ph sr-card-ph--solo" aria-hidden="true">' . sr_ornament_svg() . '</span>';
}
add_filter( 'woocommerce_placeholder_img', 'sr_branded_placeholder_img', 10, 1 );

/**
 * The single-product gallery builds its placeholder <img> inline from
 * wc_placeholder_img_src() rather than going through woocommerce_placeholder_img,
 * so the filter above never reaches it. Swap the markup here instead - this is
 * the product page hero, the worst place to show a stock grey box.
 *
 * @param string $html             Gallery image markup.
 * @param int    $post_thumbnail_id Attachment ID, 0 when the product has none.
 * @return string
 */
function sr_branded_gallery_placeholder( $html, $post_thumbnail_id ) {
	if ( $post_thumbnail_id ) {
		return $html;
	}
	return '<div class="woocommerce-product-gallery__image--placeholder sr-gallery-ph">'
		. '<span class="sr-card-ph sr-card-ph--solo" aria-hidden="true">' . sr_ornament_svg() . '</span>'
		. '<span class="screen-reader-text">' . esc_html__( 'Photography for this piece is in production.', 'samina-rasul' ) . '</span>'
		. '</div>';
}
add_filter( 'woocommerce_single_product_image_thumbnail_html', 'sr_branded_gallery_placeholder', 10, 2 );

/**
 * Shop-loop cards.
 *
 * The stock loop wraps the whole tile in a single <a>, which cannot legally
 * contain the size links and the two calls to action this card carries, so the
 * wrapper is removed and the image and title are linked individually instead.
 * Every block below renders only when its data exists, so a product with no
 * SKU, excerpt, attributes or sizes still produces a clean card.
 */
remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );

// Media: linked thumbnail, collection/SKU badge, hover veil.
add_action( 'woocommerce_before_shop_loop_item_title', function () {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$badge = sr_product_badge_text( $product );

	// A product with no image gets the house placeholder rather than
	// WooCommerce's grey camera icon, which broke the run of the grid.
	$media = $product->get_image_id()
		? woocommerce_get_product_thumbnail()
		: '<span class="sr-card-ph" aria-hidden="true">' . sr_ornament_svg() . '</span>';

	echo '<div class="sr-card-media">';
	printf(
		'<a class="sr-card-media__link" href="%s" tabindex="-1" aria-hidden="true">%s</a>',
		esc_url( $product->get_permalink() ),
		$media // phpcs:ignore WordPress.Security.EscapeOutput -- core markup or static SVG.
	);
	if ( '' !== $badge ) {
		printf( '<span class="sr-card-badge">%s</span>', esc_html( $badge ) );
	}
	echo '<span class="sr-card-veil" aria-hidden="true"></span>';
	echo '</div>';
}, 10 );

// Title, linked, opening the card body.
add_action( 'woocommerce_shop_loop_item_title', function () {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	printf(
		'<div class="sr-card-body"><h2 class="woocommerce-loop-product__title"><a href="%s">%s</a></h2>',
		esc_url( $product->get_permalink() ),
		esc_html( $product->get_name() )
	);
}, 10 );

// Bridal pieces are quoted, not priced - say so where the price would be.
// Tested against the rendered price, not get_price(): a variable product with
// a price range returns '' from get_price() and would wrongly be labelled
// "Price on inquiry" alongside its own range.
add_action( 'woocommerce_after_shop_loop_item_title', function () {
	global $product;
	if ( $product instanceof WC_Product && '' === trim( wp_strip_all_tags( (string) $product->get_price_html() ) ) ) {
		printf( '<span class="sr-price-on-inquiry">%s</span>', esc_html__( 'Price on inquiry', 'samina-rasul' ) );
	}
}, 9 );

// Short description, visible attributes, then the size pills.
add_action( 'woocommerce_after_shop_loop_item_title', function () {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$excerpt = $product->get_short_description();
	if ( '' !== $excerpt ) {
		printf( '<p class="sr-card-excerpt">%s</p>', esc_html( wp_strip_all_tags( $excerpt ) ) );
	}

	// Any attribute the shop owner marked visible, minus size, which is shown
	// as pills below. Renders whatever the client configures - Fabric today,
	// Shirt and Trousers when they add them - with no code change.
	$specs = '';
	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute->get_visible() || 'pa_size' === $attribute->get_name() ) {
			continue;
		}
		$values = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
		if ( ! $values ) {
			continue;
		}
		$specs .= sprintf(
			'<div class="sr-card-spec"><dt>%s</dt><dd>%s</dd></div>',
			esc_html( wc_attribute_label( $attribute->get_name() ) ),
			esc_html( implode( ', ', $values ) )
		);
	}
	if ( '' !== $specs ) {
		echo '<dl class="sr-card-specs">' . $specs . '</dl>'; // phpcs:ignore WordPress.Security.EscapeOutput -- assembled from escaped parts above.
	}

	// Sizes link through to the product page with that size preselected, which
	// is as far as a loop card can honestly go for a variable product.
	$sizes = wc_get_product_terms( $product->get_id(), 'pa_size', array( 'fields' => 'all' ) );
	if ( $sizes ) {
		echo '<ul class="sr-card-sizes" aria-label="' . esc_attr__( 'Available sizes', 'samina-rasul' ) . '">';
		foreach ( $sizes as $size ) {
			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( add_query_arg( 'attribute_pa_size', $size->slug, $product->get_permalink() ) ),
				esc_html( $size->name )
			);
		}
		echo '</ul>';
	}

	echo '<div class="sr-card-actions">';
}, 20 );

// Close the actions row and the card body after the add-to-cart button.
add_action( 'woocommerce_after_shop_loop_item', function () {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	printf(
		'<a class="button sr-ghost sr-card-details" href="%s"><span>%s</span></a>',
		esc_url( $product->get_permalink() ),
		esc_html__( 'View details', 'samina-rasul' )
	);
	echo '</div></div>';
}, 20 );

/**
 * Baseline SEO and social meta.
 *
 * WooCommerce already emits Product/Offer/Breadcrumb JSON-LD, and core emits
 * the <title>; what is missing for launch is a description, a canonical URL and
 * Open Graph/Twitter tags, without which every share renders as a bare link.
 *
 * Skipped entirely when a dedicated SEO plugin is active, so installing Yoast
 * or Rank Math later does not produce two competing sets of tags.
 */
function sr_seo_meta() {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'All_in_One_SEO_Pack' ) ) {
		return;
	}

	$title       = wp_get_document_title();
	$description = '';
	$image       = '';
	$url         = home_url( add_query_arg( array() ) );

	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		$excerpt = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : get_post_field( 'post_content', $post_id );
		$description = wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $excerpt ) ), 30, '' );
		$image       = get_the_post_thumbnail_url( $post_id, 'large' );
		$url         = get_permalink( $post_id );
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term        = get_queried_object();
		$description = $term instanceof WP_Term ? wp_trim_words( wp_strip_all_tags( term_description( $term ) ), 30, '' ) : '';
		$url         = $term instanceof WP_Term ? get_term_link( $term ) : $url;
		if ( ! is_string( $url ) ) {
			$url = home_url( '/' );
		}
	}

	// Fall back to the tagline, then to a house description, so the tag is
	// never emitted empty (worse for search results than omitting it).
	if ( '' === trim( (string) $description ) ) {
		$description = get_bloginfo( 'description', 'display' );
	}
	if ( '' === trim( (string) $description ) ) {
		$description = __( 'Hand embellished formals and bridal couture from the house of Samina Rasul. Zardozi, mukesh and resham worked by hand, cut to your measure and made to order.', 'samina-rasul' );
	}
	if ( ! $image ) {
		$image = sr_image_url( 'hero-section.jpg' );
	}

	printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
	printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) );
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:type" content="%s">' . "\n", is_singular() ? 'product' : 'website' );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $description ) );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	printf( '<meta name="twitter:card" content="%s">' . "\n", $image ? 'summary_large_image' : 'summary' );
	if ( $image ) {
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
		printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $image ) );
	}
}
add_action( 'wp_head', 'sr_seo_meta', 2 );

/**
 * Core's rel_canonical() also emits a canonical on singular views, so leaving
 * it in place alongside sr_seo_meta() prints the tag twice. Drop core's copy
 * only when this theme is the one providing it.
 */
add_action( 'wp', function () {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'All_in_One_SEO_Pack' ) ) {
		return;
	}
	remove_action( 'wp_head', 'rel_canonical' );
} );

/**
 * Trust strip shown on the product page and in the cart.
 *
 * Cart-abandonment research puts unexpected costs and unclear delivery timing
 * at the top of the list of reasons people leave, and this house sells a
 * made-to-order product with a seven-to-nine week lead time and a part-advance
 * payment. Stating all of that before checkout - rather than letting a shopper
 * discover it later - is what the strip is for. Deliberately answers concrete
 * questions instead of showing generic badges.
 */
function sr_trust_signals() {
	/*
	 * Prefer the product's own _sr_delivery_time. The strip previously stated a
	 * catalogue-wide "seven to nine weeks" a few hundred pixels below a product
	 * line reading "7-8 weeks" - two different answers to the most important
	 * question on the page, in one viewport.
	 */
	$lead = '';
	if ( function_exists( 'is_product' ) && is_product() ) {
		$lead = (string) get_post_meta( get_the_ID(), '_sr_delivery_time', true );
	}
	$made_copy = '' !== trim( $lead )
		/* translators: %s: per-product lead time, e.g. "7-8 weeks". */
		? sprintf( __( 'Cut and hand embellished for you in %s.', 'samina-rasul' ), $lead )
		: __( 'Cut and hand embellished for you, typically in seven to nine weeks.', 'samina-rasul' );

	$points = array(
		array(
			'title' => __( 'Made to order', 'samina-rasul' ),
			'copy'  => $made_copy,
		),
		array(
			'title' => __( 'Clear terms', 'samina-rasul' ),
			'copy'  => __( 'A 50% advance confirms your order; international orders are paid in full.', 'samina-rasul' ),
		),
		array(
			'title' => __( 'We answer', 'samina-rasul' ),
			'copy'  => __( 'Questions about fit or fabric? Talk to the atelier on WhatsApp before you order.', 'samina-rasul' ),
		),
	);

	echo '<ul class="sr-trust">';
	foreach ( $points as $point ) {
		printf(
			'<li class="sr-trust__item"><strong>%s</strong><span>%s</span></li>',
			esc_html( $point['title'] ),
			esc_html( $point['copy'] )
		);
	}
	echo '</ul>';
}

// On the product page, directly under the add-to-cart area.
add_action( 'woocommerce_single_product_summary', 'sr_trust_signals', 35 );
// And in the cart, where the same doubts resurface before checkout.
add_action( 'woocommerce_after_cart', 'sr_trust_signals', 10 );
/**
 * Relocates the trust strip once the block cart has mounted.
 *
 * Registered as a named callback on wp_footer directly, not from inside
 * woocommerce_blocks_cart_enqueue_data: that action fires from both the Cart
 * and Mini Cart blocks, and a closure registered per firing never dedupes, so
 * any page carrying a mini cart would emit this script - and its observer -
 * more than once.
 */
function sr_cart_trust_script() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}
	?>
	<script>
	(function () {
		var strip = document.getElementById('sr-cart-trust');
		if (!strip) { return; }
		var mo = null;
		var place = function () {
			if (strip.dataset.placed) { return; }
			var target = document.querySelector('.wp-block-woocommerce-cart');
			if (!target) { return; }
			target.insertAdjacentElement('afterend', strip);
			strip.hidden = false;
			strip.dataset.placed = '1';
			/* The block cart mutates on every quantity/total change; stop
			 * observing as soon as the strip has landed. */
			if (mo) { mo.disconnect(); }
		};
		place();
		if (!strip.dataset.placed) {
			mo = new MutationObserver(place);
			mo.observe(document.body, { childList: true, subtree: true });
			/* Give up rather than observe forever if the cart never mounts. */
			setTimeout(function () { if (mo) { mo.disconnect(); } }, 10000);
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'sr_cart_trust_script', 20 );

// Server-rendered strip the script above relocates once the block cart mounts.
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}
	echo '<div id="sr-cart-trust" class="sr-cart-trust" hidden>';
	sr_trust_signals();
	echo '</div>';
}, 5 );

/**
 * Newsletter signup handler.
 *
 * Stores confirmed addresses in an option until the client picks an email
 * provider; swap the storage line for the provider's API call at that point.
 * Public endpoint, so it is defended accordingly: nonce, honeypot, explicit
 * consent, address validation, and a per-IP rate limit so it cannot be used to
 * flood the option row.
 */
function sr_handle_newsletter() {
	$referer = wp_get_referer();
	$back    = $referer ? $referer : home_url( '/' );

	$fail = function ( $reason ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'sr_sub', $reason, remove_query_arg( 'sr_sub', $back ) ) . '#sr_newsletter_email' );
		exit;
	};

	if ( ! isset( $_POST['sr_newsletter_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sr_newsletter_nonce'] ) ), 'sr_newsletter' ) ) {
		$fail( 'err' );
	}

	// Honeypot filled, or consent not given: drop silently as a success so a
	// bot learns nothing from the response.
	if ( ! empty( $_POST['sr_website'] ) || empty( $_POST['sr_consent'] ) ) {
		$fail( 'ok' );
	}

	$email = isset( $_POST['sr_newsletter_email'] ) ? sanitize_email( wp_unslash( $_POST['sr_newsletter_email'] ) ) : '';
	if ( ! is_email( $email ) ) {
		$fail( 'err' );
	}

	// Rate limit: five attempts per IP per hour.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key = 'sr_nl_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 5 ) {
		$fail( 'err' );
	}
	set_transient( $key, $hits + 1, HOUR_IN_SECONDS );

	$list = get_option( 'sr_newsletter_emails', array() );
	if ( ! is_array( $list ) ) {
		$list = array();
	}
	if ( ! in_array( $email, $list, true ) ) {
		$list[] = $email;
		// Not autoloaded: this can grow, and it is not needed on every request.
		update_option( 'sr_newsletter_emails', $list, false );
	}

	$fail( 'ok' );
}
add_action( 'admin_post_nopriv_sr_newsletter', 'sr_handle_newsletter' );
add_action( 'admin_post_sr_newsletter', 'sr_handle_newsletter' );

/**
 * Storefront renders the sorting select and result count both above and below
 * the product grid. One toolbar is enough for a catalogue this size, and the
 * repeat reads as clutter in an editorial layout - drop the lower copy while
 * leaving pagination (priority 30) in place.
 *
 * Runs on wp_loaded because the parent's template-hooks file is loaded after
 * the child's functions.php, so a file-scope remove_action() would be too early.
 */
function sr_trim_duplicate_catalog_toolbar() {
	remove_action( 'woocommerce_after_shop_loop', 'storefront_sorting_wrapper', 9 );
	remove_action( 'woocommerce_after_shop_loop', 'woocommerce_catalog_ordering', 10 );
	remove_action( 'woocommerce_after_shop_loop', 'woocommerce_result_count', 20 );
	remove_action( 'woocommerce_after_shop_loop', 'storefront_sorting_wrapper_close', 31 );
}
add_action( 'wp_loaded', 'sr_trim_duplicate_catalog_toolbar' );

/**
 * Storefront layout adjustments.
 */
// No sidebar anywhere - editorial full-width layout. Registered at file scope;
// there was no reason for this to be nested inside an init callback.
add_filter( 'storefront_page_layout', function () {
	return 'full-width';
} );

add_filter( 'body_class', function ( $classes ) {
	$classes[] = 'sr-theme';
	return $classes;
} );

/**
 * Size chart - button on product pages opening a native <dialog>.
 * Measurements are placeholders until the client supplies the real chart.
 */
add_action( 'woocommerce_single_product_summary', function () {
	?>
	<p><button type="button" class="sr-size-chart-open sr-linklike" aria-haspopup="dialog"><?php esc_html_e( 'Size chart', 'samina-rasul' ); ?></button></p>
	<dialog class="sr-size-chart" aria-label="<?php esc_attr_e( 'Size chart', 'samina-rasul' ); ?>">
		<h3><?php esc_html_e( 'Size chart', 'samina-rasul' ); ?></h3>
		<table>
			<thead>
				<tr><th><?php esc_html_e( 'Size', 'samina-rasul' ); ?></th><th><?php esc_html_e( 'Bust (in)', 'samina-rasul' ); ?></th><th><?php esc_html_e( 'Waist (in)', 'samina-rasul' ); ?></th><th><?php esc_html_e( 'Hip (in)', 'samina-rasul' ); ?></th></tr>
			</thead>
			<tbody>
				<tr><td>XS</td><td>-</td><td>-</td><td>-</td></tr>
				<tr><td>S</td><td>-</td><td>-</td><td>-</td></tr>
				<tr><td>M</td><td>-</td><td>-</td><td>-</td></tr>
				<tr><td>ML</td><td>-</td><td>-</td><td>-</td></tr>
				<tr><td>L</td><td>-</td><td>-</td><td>-</td></tr>
				<tr><td>XL</td><td>-</td><td>-</td><td>-</td></tr>
			</tbody>
		</table>
		<p class="sr-size-chart__note"><?php esc_html_e( 'Between sizes, or after a different fit? Choose “Customized” and we will cut to your measurements.', 'samina-rasul' ); ?></p>
		<button type="button" class="button sr-size-chart-close"><?php esc_html_e( 'Close', 'samina-rasul' ); ?></button>
	</dialog>
	<script>
	(function () {
		document.addEventListener('click', function (e) {
			if (e.target.closest('.sr-size-chart-open')) { document.querySelector('.sr-size-chart').showModal(); }
			if (e.target.closest('.sr-size-chart-close')) { document.querySelector('.sr-size-chart').close(); }
		});
	})();
	</script>
	<?php
}, 24 );

/**
 * Footer: oversized outlined wordmark above the link columns.
 */
add_action( 'storefront_footer', function () {
	echo '<div class="sr-footer-wordmark" aria-hidden="true"><span>Samina&nbsp;Rasul</span></div>';
}, 4 );

/**
 * Footer columns per the brief: About the Brand / Customer Service / Information.
 */
function sr_footer_columns() {
	/*
	 * Label/URL pairs as list rows rather than translated-string array keys:
	 * as keys, two entries that happen to translate identically in some locale
	 * would silently overwrite each other and vanish from the footer.
	 */
	$columns = array(
		array(
			'heading' => __( 'About the Brand', 'samina-rasul' ),
			'links'   => array(
				array( 'label' => __( 'About Us', 'samina-rasul' ), 'path' => '/about-us/' ),
				array( 'label' => __( 'FAQs', 'samina-rasul' ), 'path' => '/faqs/' ),
			),
		),
		array(
			'heading' => __( 'Customer Service', 'samina-rasul' ),
			'links'   => array(
				array( 'label' => __( 'Contact Us', 'samina-rasul' ), 'path' => '/contact/' ),
				array( 'label' => __( 'Payments & Shipping', 'samina-rasul' ), 'path' => '/shipping-policy/' ),
			),
		),
		array(
			'heading' => __( 'Information', 'samina-rasul' ),
			'links'   => array(
				array( 'label' => __( 'My Account', 'samina-rasul' ), 'path' => '/my-account/' ),
				array( 'label' => __( 'Shipping Policy', 'samina-rasul' ), 'path' => '/shipping-policy/' ),
				array( 'label' => __( 'Refund Policy', 'samina-rasul' ), 'path' => '/refund-policy/' ),
				array( 'label' => __( 'Terms of Service', 'samina-rasul' ), 'path' => '/terms-of-service/' ),
				array( 'label' => __( 'Privacy Policy', 'samina-rasul' ), 'path' => '/privacy-policy/' ),
			),
		),
	);

	echo '<div class="sr-footer-cols">';
	foreach ( $columns as $column ) {
		// h3, not h4: the page's own content stops at h2, and skipping a level
		// breaks heading navigation for screen-reader users.
		echo '<div class="sr-footer-col"><h3>' . esc_html( $column['heading'] ) . '</h3><ul>';
		foreach ( $column['links'] as $link ) {
			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( home_url( $link['path'] ) ),
				esc_html( $link['label'] )
			);
		}
		echo '</ul></div>';
	}
	echo '</div>';
}
add_action( 'storefront_footer', 'sr_footer_columns', 5 );

/**
 * Footer: replace Storefront credit with brand note.
 */
add_filter( 'storefront_credit_link', '__return_false' );
add_filter( 'storefront_copyright_text', function () {
	return sprintf(
		/* translators: %s: year */
		esc_html__( '© %s Samina Rasul, every piece made to order and hand finished in Pakistan.', 'samina-rasul' ),
		gmdate( 'Y' )
	);
} );
