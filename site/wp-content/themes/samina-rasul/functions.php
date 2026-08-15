<?php
/**
 * Samina Rasul child theme.
 */

defined( 'ABSPATH' ) || exit;

// Client-editable homepage copy and imagery (Customizer → Homepage Content).
require_once get_stylesheet_directory() . '/inc/homepage-content.php';

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
	add_action( 'storefront_header', 'sr_header_saved_link', 40 );
	/*
	 * Priority 51 lands this after storefront_primary_navigation (50), i.e.
	 * inside the navigation row rather than the icon row above it — a labelled
	 * button sitting among bare icons read as a stray element.
	 */
	add_action( 'storefront_header', 'sr_header_consult_link', 51 );
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
 * Saved-items icon for the header row. The count is written by sr-saved-count.js
 * from the same localStorage key the product-page heart uses, so it is correct
 * on the first paint after a save without a round trip.
 */
function sr_header_saved_link() {
	printf(
		'<a class="sr-header-icon sr-header-icon--saved" href="%1$s" data-sr-saved-link>%2$s<span class="screen-reader-text">%3$s</span></a>',
		esc_url( sr_page_url( 'saved' ) ),
		'<svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 1 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/></svg>', // phpcs:ignore WordPress.Security.EscapeOutput -- static, hand-written inline SVG.
		esc_html__( 'Saved pieces', 'samina-rasul' )
	);
}

/**
 * Header consultation CTA.
 *
 * The bridal half of the store has no price and no add-to-cart, so booking a
 * consultation is its checkout button. It is the one primary action in the
 * header — the rest of the row is icons — which is what keeps the hierarchy
 * readable rather than making everything shout.
 */
function sr_header_consult_link() {
	printf(
		'<a class="button sr-header-consult" href="%1$s"><span>%2$s</span></a>',
		esc_url( sr_page_url( 'consultations' ) ),
		esc_html__( 'Book a consultation', 'samina-rasul' )
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
 * Permalink for a content page referenced by slug.
 *
 * Every link to About, Contact, Consultations, FAQs, Saved and the policy pages
 * used to be a hardcoded home_url( '/about-us/' ). The client renames one page
 * in wp-admin - which they are entitled to do - and the link 404s, including
 * the header's primary "Book a consultation" CTA and the bridal enquiry
 * fallback that exists precisely so nothing is ever a dead link.
 *
 * The slug is looked up as a page first; when it no longer resolves, the URL is
 * still built from it, so behaviour is unchanged on a site whose slugs match
 * and self-correcting on one whose slugs have moved but whose pages still exist
 * under the same name.
 *
 * @param string $slug Page slug as shipped, e.g. 'about-us'.
 * @return string Permalink.
 */
function sr_page_url( $slug ) {
	static $cache = array();

	$slug = trim( (string) $slug, '/' );
	if ( '' === $slug ) {
		return home_url( '/' );
	}

	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}

	$url  = home_url( '/' . $slug . '/' );
	$page = get_page_by_path( $slug );

	if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
		$permalink = get_permalink( $page );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			$url = $permalink;
		}
	}

	$cache[ $slug ] = $url;

	return $url;
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
function sr_term_card_media( $slug, $taxonomy, $tone = 'warm', $override_id = 0 ) {
	/*
	 * The Customizer field wins when it is set, so a card on the home page can
	 * be given its own photograph without changing the picture that heads the
	 * category archive - the two are different crops of a different job. Left
	 * empty, the category's own image is used and the pair stay in step, which
	 * is the sensible default and the one that needs no decision.
	 */
	$image_id = (int) $override_id;

	if ( $image_id <= 0 ) {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			$image_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		}
	}

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

/*
 * Every front-end asset this theme owns, in one callback.
 *
 * Priority 25, not 20: Storefront registers its WooCommerce sheet on this same
 * hook at 20, so the stylesheet dependency below only resolves once it has run.
 */
/**
 * Does this view load the GSAP/Lenis animation bundle?
 *
 * One answer, read by both the enqueue below and the pre-paint script in
 * wp_head. They have to agree: that script adds classes which hide every
 * reveal element until sr-ui.js clears them, so a page that sets the classes
 * without loading the bundle shows nothing until the 2.5s failsafe fires.
 *
 * Excluded:
 * - Cart, checkout and account. None of these effects appear there, and ~125 KB
 *   of JS that can only cost a conversion has no business on the buying path.
 * - The policy and FAQ pages. They are body copy with no choreography on them;
 *   they were paying for the whole bundle to animate nothing.
 *
 * @return bool
 */
function sr_uses_animation_bundle() {
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
		return false;
	}

	if ( is_page() && array_key_exists( (string) get_post_field( 'post_name', get_queried_object_id() ), sr_editorial_pages() ) ) {
		return false;
	}

	return true;
}

add_action( 'wp_enqueue_scripts', function () {
	$assets  = get_stylesheet_directory_uri() . '/assets/js';
	$css_ver = sr_asset_version( 'style.css' );
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

	if ( sr_uses_animation_bundle() ) {
		$ui_deps = array( 'sr-gsap', 'sr-scrolltrigger' );
		/* Lenis only ever starts where the document opts in with
		 * data-sr-smooth (see the language_attributes filter below), which is
		 * the front page alone - everywhere else it was bytes that parsed and
		 * did nothing. sr-ui.js already treats an absent Lenis as "no smooth
		 * layer", so it degrades to native scrolling on its own. */
		if ( is_front_page() ) {
			wp_enqueue_script( 'sr-lenis', $assets . '/lenis.min.js', array(), '1.1.14', true );
			$ui_deps[] = 'sr-lenis';
		}
		wp_enqueue_script( 'sr-gsap', $assets . '/gsap.min.js', array(), '3.12.5', true );
		wp_enqueue_script( 'sr-scrolltrigger', $assets . '/ScrollTrigger.min.js', array( 'sr-gsap' ), '3.12.5', true );
		wp_enqueue_script( 'sr-ui', $assets . '/sr-ui.js', $ui_deps, sr_asset_version( 'assets/js/sr-ui.js' ), true );
	}

	if ( class_exists( 'WooCommerce' ) ) {
		// Search panel: printed on every page, including the commerce path
		// above, which is why it carries no dependency on the bundle.
		wp_enqueue_script( 'sr-search', $assets . '/sr-search.js', array(), sr_asset_version( 'assets/js/sr-search.js' ), true );
		wp_localize_script(
			'sr-search',
			'srSearchL10n',
			array(
				'empty'   => __( 'Nothing matched that. Try an article code, a collection name, or a fabric.', 'samina-rasul' ),
				'error'   => __( 'Search is unavailable right now. Please try again.', 'samina-rasul' ),
				'viewAll' => __( 'View all results', 'samina-rasul' ),
			)
		);
	}

	// The swatch enhancement for variation dropdowns. Product pages only.
	if ( function_exists( 'is_product' ) && is_product() ) {
		// jQuery declared: WooCommerce announces the chosen variation only on
		// its own jQuery events, which is where the live price figure comes from.
		wp_enqueue_script( 'sr-product', $assets . '/sr-product.js', array( 'jquery' ), sr_asset_version( 'assets/js/sr-product.js' ), true );
		wp_localize_script(
			'sr-product',
			'srProductL10n',
			array(
				// The shop's own money formatting, so the live figure cannot
				// drift from what the cart will charge.
				'price'   => array(
					'decimals'    => wc_get_price_decimals(),
					'decimalSep'  => wc_get_price_decimal_separator(),
					'thousandSep' => wc_get_price_thousand_separator(),
				),
				/*
				 * Strings the swatch and quantity controls build at runtime.
				 * They were English literals in the JS, on controls every
				 * customer uses - the rest of this theme is translatable and
				 * these were the two places that silently were not.
				 */
				'qty'     => array(
					'less' => __( 'Decrease quantity', 'samina-rasul' ),
					'more' => __( 'Increase quantity', 'samina-rasul' ),
				),
				'strings' => array(
					/* translators: %s: attribute name, e.g. "Size". */
					'selectPrefix' => __( 'Select %s', 'samina-rasul' ),
				),
			)
		);
	}
}, 25 );

/*
 * Opt the document into the Lenis layer used by sr-ui.js - front page only.
 *
 * Lenis takes ownership of the scroll: it preventDefaults the wheel and drives
 * the page from its own rAF loop against a cached document height. When that
 * height is measured before the page has finished laying out - which is what
 * happens on a product whose gallery has no image, so the slot is a CSS
 * placeholder that sizes itself rather than an <img> that fires load - the
 * cached limit can be stale and the wheel then moves nothing at all. A product
 * page that will not scroll is a lost sale; the homepage is the only page with
 * scrub-driven choreography that actually needs the smooth layer.
 */
add_filter( 'language_attributes', function ( $output ) {
	return is_front_page() ? $output . ' data-sr-smooth="true"' : $output;
} );

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
 * Preload the display face used by the hero headline.
 *
 * That headline is the LCP element on the homepage and on every category
 * archive. Without this the browser only discovers the file after it has
 * fetched and parsed the stylesheet that references it, so the headline paints
 * in the fallback face and swaps visibly late. One file - the latin subset of
 * the weight used above the fold - not the whole family, which would cost more
 * than it saves.
 */
add_action( 'wp_head', function () {
	// Bodoni Moda, upright, latin subset — the file fonts-local.css resolves to
	// for the headline. Named rather than derived: the hashed filenames come
	// from Google's CSS and there is nothing in them to compute from.
	$file = 'aFTQ7PxzY382XsXX63LUYJSKSKjWXFBP.woff2';

	if ( ! file_exists( get_stylesheet_directory() . '/fonts/' . $file ) ) {
		return;
	}

	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( get_stylesheet_directory_uri() . '/fonts/' . $file )
	);
}, 1 );

/**
 * Pre-paint flags: preloader (once per session) and curtain-arrival state.
 * Runs inline in <head> so the overlay states exist before first paint.
 *
 * Skipped entirely where the animation bundle is not loaded: these classes hide
 * every reveal element and are cleared only by sr-ui.js, so setting them on a
 * page that never loads it would blank the content until the failsafe fires.
 */
add_action( 'wp_head', function () {
	if ( ! sr_uses_animation_bundle() ) {
		return;
	}
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

		/* The header form stays in the DOM and stays submittable, so search
		 * still works with JavaScript off; with JS on, its button becomes the
		 * trigger for the full-width search panel instead (see sr-search.js). */
		/* Saved count, from the same localStorage key the product heart writes. */
		var savedLink = document.querySelector('[data-sr-saved-link]');
		if (savedLink) {
			try {
				var saved = JSON.parse(localStorage.getItem('srSaved')) || [];
				savedLink.setAttribute('data-count', String(saved.length));
				savedLink.classList.toggle('has-items', saved.length > 0);
			} catch (e) {}
		}

		var searchWrap = document.querySelector('.site-header .site-search');
		var submitBtn = searchWrap && searchWrap.querySelector('form.woocommerce-product-search button[type="submit"]');
		if (submitBtn) {
			submitBtn.insertAdjacentHTML('afterbegin', SEARCH_ICON_SVG);
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
/**
 * Weave motif: warp and weft on the bias, inside a bounded circle.
 *
 * Drawn for the principles band on the about page, where an oversized outlined
 * wordmark used to sit. That device belongs to the footer, and repeating it
 * behind body copy read as a watermark someone forgot to remove - it fought the
 * text it was meant to sit behind and broke out of the section on narrow
 * screens because a nowrap string cannot be made to fit.
 *
 * A lattice is the honest image for this section: cloth, before anything is
 * done to it. Same construction as the other house marks - hairline strokes,
 * currentColor, one viewBox, no fill - so it inherits colour and opacity from
 * whatever it is placed on.
 *
 * @return string Inline SVG.
 */
function sr_weave_motif_svg() {
	static $svg = null;
	if ( null !== $svg ) {
		return $svg;
	}

	// The lattice: two sets of parallel threads at right angles to each other,
	// laid on the bias so the grid reads as woven rather than as graph paper.
	$threads = '';
	for ( $i = -12; $i <= 12; $i++ ) {
		$offset   = $i * 16;
		$threads .= '<line x1="' . ( 100 + $offset ) . '" y1="-60" x2="' . ( 100 + $offset ) . '" y2="260"/>';
		$threads .= '<line x1="-60" y1="' . ( 100 + $offset ) . '" x2="260" y2="' . ( 100 + $offset ) . '"/>';
	}

	// Knots where the threads cross, thinning towards the edge of the circle.
	$knots = '';
	for ( $x = -2; $x <= 2; $x++ ) {
		for ( $y = -2; $y <= 2; $y++ ) {
			$knots .= '<circle cx="' . ( 100 + $x * 32 ) . '" cy="' . ( 100 + $y * 32 ) . '" r="1.4" fill="currentColor" stroke="none"/>';
		}
	}

	$svg = '<svg class="sr-weave" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
		<defs><clipPath id="sr-weave-clip"><circle cx="100" cy="100" r="94"/></clipPath></defs>
		<g clip-path="url(#sr-weave-clip)" stroke="currentColor" stroke-width="0.5" transform="rotate(45 100 100)">' . $threads . '</g>
		<g clip-path="url(#sr-weave-clip)" transform="rotate(45 100 100)">' . $knots . '</g>
		<circle cx="100" cy="100" r="94" stroke="currentColor" stroke-width="0.8"/>
		<circle cx="100" cy="100" r="64" stroke="currentColor" stroke-width="0.4"/>
		<path d="M100 22 L178 100 L100 178 L22 100 Z" stroke="currentColor" stroke-width="0.6"/>
	</svg>';

	return $svg;
}

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
 * Search results masthead.
 *
 * The stock header prints a lone <h1> inside a tall empty band, which reads as
 * a broken rectangle. Replace it with an eyebrow, the query, a live count and a
 * refine field, so the page states what was searched and lets it be corrected
 * without going back to the header.
 */
function sr_search_masthead() {
	if ( ! is_search() ) {
		return;
	}

	$term  = get_search_query();
	$total = (int) ( $GLOBALS['wp_query']->found_posts ?? 0 );
	?>
	<header class="sr-searchhead">
		<span class="sr-eyebrow sr-eyebrow--ruled"><?php esc_html_e( 'Search the house', 'samina-rasul' ); ?></span>
		<h1 class="sr-searchhead__title">
			<?php
			echo esc_html(
				$total > 0
					/* translators: %s: the search term. */
					? sprintf( __( 'Results for “%s”', 'samina-rasul' ), $term )
					/* translators: %s: the search term. */
					: sprintf( __( 'Nothing for “%s”', 'samina-rasul' ), $term )
			);
			?>
		</h1>
		<?php if ( $total > 0 ) : ?>
			<p class="sr-searchhead__count">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: number of matching products. */
						_n( '%s piece found', '%s pieces found', $total, 'samina-rasul' ),
						number_format_i18n( $total )
					)
				);
				?>
			</p>
		<?php endif; ?>
		<div class="sr-searchhead__form">
			<?php if ( function_exists( 'get_product_search_form' ) ) { get_product_search_form(); } ?>
		</div>
	</header>
	<?php
}
add_action( 'woocommerce_before_main_content', 'sr_search_masthead', 20 );

// The custom masthead above replaces it; two page titles would be redundant.
add_filter( 'woocommerce_show_page_title', function ( $show ) {
	return is_search() ? false : $show;
} );

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

// Short description, then the actions row. A card sells the piece and gets
// out of the way; fabrics, work and sizes are the product page's job.
add_action( 'woocommerce_after_shop_loop_item_title', function () {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$excerpt = $product->get_short_description();
	if ( '' !== $excerpt ) {
		printf( '<p class="sr-card-excerpt">%s</p>', esc_html( wp_strip_all_tags( $excerpt ) ) );
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

// Search-engine and social meta: canonical, description, Open Graph, schema.
require_once get_stylesheet_directory() . '/inc/seo.php';

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
		/* translators: %s: per-product lead time, e.g. "8-9 weeks". */
		? sprintf( __( 'Hand-stitched in %s', 'samina-rasul' ), $lead )
		: __( 'Hand-stitched in eight to nine weeks', 'samina-rasul' );

	$points = array(
		array(
			// Stacked layers: cloth cut and built up by hand, one piece at a time.
			'icon'  => '<path d="M12 3 3 7.5 12 12l9-4.5L12 3z"/><path d="M3 12.5 12 17l9-4.5"/><path d="M3 17.5 12 22l9-4.5"/>',
			'title' => __( 'Made to Order', 'samina-rasul' ),
			'copy'  => $made_copy,
		),
		array(
			// A clock: how long the piece takes to reach them.
			'icon'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2"/>',
			'title' => __( 'Worldwide Shipping', 'samina-rasul' ),
			'copy'  => __( 'Tracked global delivery', 'samina-rasul' ),
		),
		array(
			// A heart: the fit is the part of this that is made for one person.
			'icon'  => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 1 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/>',
			'title' => __( 'Custom Fit', 'samina-rasul' ),
			'copy'  => __( 'Cut to your measurements', 'samina-rasul' ),
		),
	);

	echo '<ul class="sr-trust">';
	foreach ( $points as $point ) {
		printf(
			'<li class="sr-trust__item"><span class="sr-trust__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">%s</svg></span><strong>%s</strong><span class="sr-trust__copy">%s</span></li>',
			$point['icon'], // phpcs:ignore WordPress.Security.EscapeOutput -- static, hand-written inline SVG paths.
			esc_html( $point['title'] ),
			esc_html( $point['copy'] )
		);
	}
	echo '</ul>';
}

/**
 * Specification table for the product summary.
 *
 * The fabric and handwork of a couture piece are the buying decision, and until
 * now they were only readable inside the "Additional information" accordion at
 * the foot of the page. This lifts every attribute the shop owner marked visible
 * into the summary, beside the price, where the decision is actually made.
 *
 * Anything the customer chooses is excluded - size, and any attribute used for
 * variations - because those are the selectors below, not specifications;
 * listing every fabric combo here as well would state four answers to a
 * question the pills are about to ask once. Renders nothing at all when a
 * product carries no visible, non-selectable attributes.
 */
function sr_single_specs() {
	global $product;

	$rows = '';
	foreach ( sr_product_spec_rows( $product ) as $spec ) {
		$rows .= sprintf(
			'<div class="sr-spec"><dt>%s</dt><dd>%s</dd></div>',
			esc_html( $spec['label'] ),
			esc_html( $spec['value'] )
		);
	}

	if ( '' === $rows ) {
		return;
	}

	echo '<dl class="sr-specs">' . $rows . '</dl>'; // phpcs:ignore WordPress.Security.EscapeOutput -- assembled from escaped parts above.
}

/**
 * A product's specification rows, as label/value pairs.
 *
 * Anything the customer chooses is excluded - size, and any attribute used for
 * variations - because those are selectors, not specifications. The delivery
 * time is appended as a final row: it is the question every made-to-order
 * shopper asks, and it lives in post meta rather than an attribute.
 *
 * Shared by the product page and the bridal lookbook so the two never describe
 * the same garment differently.
 *
 * @param WC_Product|null $product Product to describe.
 * @param int             $limit   Maximum rows to return, 0 for all.
 * @return array<int, array{label: string, value: string}>
 */
function sr_product_spec_rows( $product, $limit = 0 ) {
	if ( ! $product instanceof WC_Product ) {
		return array();
	}

	$specs = array();

	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute->get_visible() || $attribute->get_variation() || 'pa_size' === $attribute->get_name() ) {
			continue;
		}

		$values = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
		if ( ! $values ) {
			// A custom (non-taxonomy) attribute stores its values on the product.
			$values = $attribute->get_options();
		}
		if ( ! $values ) {
			continue;
		}

		$specs[] = array(
			'label' => wc_attribute_label( $attribute->get_name() ),
			'value' => implode( ', ', $values ),
		);
	}

	$delivery = trim( (string) get_post_meta( $product->get_id(), '_sr_delivery_time', true ) );
	if ( '' !== $delivery ) {
		$specs[] = array(
			'label' => __( 'Atelier time', 'samina-rasul' ),
			'value' => $delivery,
		);
	}

	return $limit > 0 ? array_slice( $specs, 0, $limit ) : $specs;
}

/**
 * Closing call to action for a shop archive.
 *
 * Every collection page ends on a dead stop otherwise: the grid runs out and
 * there is nothing to do next. Made to order means the answer to "none of
 * these are quite right" is a conversation, not another page of products.
 *
 * @param string $eyebrow Small line above the heading.
 * @param string $heading Display heading; <em> is honoured.
 * @param string $label   Button label.
 * @return void
 */
function sr_shop_cta( $eyebrow = '', $heading = '', $label = '' ) {
	$eyebrow = '' !== $eyebrow ? $eyebrow : __( 'Bespoke', 'samina-rasul' );
	$heading = '' !== $heading ? $heading : __( 'Envisioning something <em>entirely your own?</em>', 'samina-rasul' );
	$label   = '' !== $label ? $label : __( 'Start a consultation', 'samina-rasul' );

	printf(
		'<section class="sr-shop-cta">' .
		'<span class="sr-shop-cta__art" aria-hidden="true">%1$s</span>' .
		'<div class="sr-shop-cta__inner" data-sr-reveal><span class="sr-eyebrow">%2$s</span><h2>%3$s</h2>' .
		'<a class="button" href="%4$s"><span>%5$s</span></a></div></section>',
		sr_arch_motif_svg(), // phpcs:ignore WordPress.Security.EscapeOutput -- static, hand-written inline SVG.
		esc_html( $eyebrow ),
		wp_kses( $heading, array( 'em' => array(), 'br' => array() ) ),
		esc_url( home_url( '/consultations/' ) ),
		esc_html( $label )
	);
}

/**
 * Close every product archive with the consultation invitation.
 *
 * Bridals is excluded because its own template ends on a bespoke CTA already,
 * and two in a row reads as a mistake.
 *
 * @return void
 */
function sr_shop_loop_cta() {
	if ( is_tax( 'product_cat', 'bridals' ) ) {
		return;
	}

	sr_shop_cta();
}
/*
 * Priority 40, not 30. Storefront hooks woocommerce_pagination at 30 too, and
 * this callback is registered first (the child theme's functions.php loads
 * before the parent's template-hooks.php), so at equal priority the closing CTA
 * printed above the pager - page 2 sat below the "start a consultation" block
 * that is supposed to end the page.
 */
add_action( 'woocommerce_after_shop_loop', 'sr_shop_loop_cta', 40 );
add_action( 'woocommerce_single_product_summary', 'sr_single_specs', 21 );

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
 * The subscriber list.
 *
 * One private post per address rather than one option holding an array of them.
 * The array version read, unserialised, linear-scanned, re-serialised and wrote
 * the entire list on every signup - a multi-megabyte write on a public endpoint
 * once the list is a few thousand long - and, having no removal path, collected
 * a consent it could not honour.
 *
 * A post type costs no schema, dedupes on an indexed column, and gives the
 * client a screen in wp-admin where a subscriber can be read, exported and
 * deleted, which is the unsubscribe route until an email provider is chosen.
 */
function sr_register_subscriber_post_type() {
	register_post_type(
		'sr_subscriber',
		array(
			'labels'          => array(
				'name'          => __( 'Subscribers', 'samina-rasul' ),
				'singular_name' => __( 'Subscriber', 'samina-rasul' ),
				'menu_name'     => __( 'Subscribers', 'samina-rasul' ),
				'search_items'  => __( 'Search subscribers', 'samina-rasul' ),
				'not_found'     => __( 'No subscribers yet.', 'samina-rasul' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'options-general.php',
			'show_in_rest'    => false,
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
			// Addresses are personal data: never a URL, never in a sitemap,
			// never in a feed.
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
		)
	);
}
add_action( 'init', 'sr_register_subscriber_post_type' );

/**
 * Carry addresses over from the old sr_newsletter_emails option.
 *
 * Runs once, in wp-admin only, and leaves the option in place afterwards rather
 * than deleting it - if this migration is wrong, the original list is still
 * there to re-read. These are people who asked to be contacted; losing them
 * silently is not an acceptable outcome of a refactor.
 */
function sr_migrate_newsletter_option() {
	if ( get_option( 'sr_subscribers_migrated' ) ) {
		return;
	}

	$list = get_option( 'sr_newsletter_emails', array() );

	if ( is_array( $list ) ) {
		foreach ( $list as $email ) {
			$email = sanitize_email( (string) $email );
			if ( ! is_email( $email ) ) {
				continue;
			}
			$slug = 'sub-' . md5( strtolower( $email ) );
			if ( get_page_by_path( $slug, OBJECT, 'sr_subscriber' ) instanceof WP_Post ) {
				continue;
			}
			wp_insert_post(
				array(
					'post_type'   => 'sr_subscriber',
					'post_status' => 'publish',
					'post_title'  => $email,
					'post_name'   => $slug,
				)
			);
		}
	}

	update_option( 'sr_subscribers_migrated', '1', false );
}
add_action( 'admin_init', 'sr_migrate_newsletter_option' );

/**
 * The visitor's IP address, for rate limiting.
 *
 * REMOTE_ADDR is the only value the site can trust on its own. Behind a proxy
 * or CDN it is the proxy, which collapses every visitor into one rate-limit
 * bucket - so a forwarded header is honoured, but only when the site has been
 * told in wp-config.php that there is a trusted proxy in front of it. Reading
 * X-Forwarded-For unconditionally would be worse than useless: the header is
 * attacker-controlled, and a rate limit keyed on it limits nobody.
 *
 * @return string
 */
function sr_client_ip() {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- validated by filter_var below.

	if ( defined( 'SR_TRUSTED_PROXY' ) && SR_TRUSTED_PROXY && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$forwarded = explode( ',', (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- validated by filter_var below.
		// The last hop is the one the trusted proxy itself appended; earlier
		// entries are whatever the client chose to claim.
		$candidate = trim( (string) end( $forwarded ) );
		if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
			return $candidate;
		}
	}

	return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : 'unknown';
}

/**
 * Newsletter signup handler.
 *
 * Stores confirmed addresses as sr_subscriber posts until the client picks an
 * email provider; swap the storage block for the provider's API call at that
 * point. Public endpoint, so it is defended accordingly: nonce, honeypot,
 * explicit consent, address validation, and a per-IP rate limit.
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
	$key  = 'sr_nl_' . md5( sr_client_ip() );
	$hits = (int) get_transient( $key );
	if ( $hits >= 5 ) {
		$fail( 'err' );
	}
	set_transient( $key, $hits + 1, HOUR_IN_SECONDS );

	/*
	 * Dedupe on post_name, which is an indexed column, rather than by scanning
	 * the list. The address is hashed into the slug so it is not repeated in a
	 * second place, and looked up with a direct query rather than WP_Query,
	 * which would not use the index for a post_name lookup on a private type.
	 */
	$existing = get_page_by_path( 'sub-' . md5( strtolower( $email ) ), OBJECT, 'sr_subscriber' );

	if ( ! $existing instanceof WP_Post ) {
		wp_insert_post(
			array(
				'post_type'   => 'sr_subscriber',
				'post_status' => 'publish',
				'post_title'  => $email,
				'post_name'   => 'sub-' . md5( strtolower( $email ) ),
			)
		);
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
 * Size guide - a link beside the size pills opening the atelier's own chart.
 *
 * The chart is an image the atelier maintains (uploads/size-chart.*), which is
 * the form they actually keep it in. No image, no guide: a table of em-dashes
 * is worse than no link at all, and the "Customized" route below the pills is
 * the real answer for anyone between sizes.
 *
 * sr-product.js lifts the trigger up into the size pill row; it stays here in
 * the markup so it is still reachable when that script does not run.
 */
function sr_size_chart_image() {
	static $url = null;
	if ( null !== $url ) {
		return $url;
	}
	$url = '';
	foreach ( array( 'size-chart.jpeg', 'size-chart.jpg', 'size-chart.png', 'size-chart.webp' ) as $file ) {
		$url = sr_image_url( $file );
		if ( '' !== $url ) {
			break;
		}
	}
	return $url;
}

add_action( 'woocommerce_single_product_summary', function () {
	global $product;

	/*
	 * Only where there is a size to choose. sr-product.js lifts this trigger up
	 * into the size pill row; on a product whose only variation is fabric there
	 * is no such row, and the link was left stranded mid-column pointing at a
	 * chart for a choice the page never offers.
	 */
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$sizes = wc_get_product_terms( $product->get_id(), 'pa_size', array( 'fields' => 'ids' ) );
	if ( ! $sizes ) {
		return;
	}

	$chart = sr_size_chart_image();
	if ( '' === $chart ) {
		return;
	}
	?>
	<div class="sr-size-chart-row"><button type="button" class="sr-size-chart-open sr-linklike" aria-haspopup="dialog"><?php esc_html_e( 'Size guide', 'samina-rasul' ); ?></button></div>
	<dialog class="sr-size-chart sr-size-chart--image" aria-labelledby="sr-size-chart-title">
		<button type="button" class="sr-size-chart-close" aria-label="<?php esc_attr_e( 'Close size guide', 'samina-rasul' ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
		</button>
		<span class="sr-eyebrow"><?php esc_html_e( 'Fit', 'samina-rasul' ); ?></span>
		<h3 id="sr-size-chart-title"><?php esc_html_e( 'Size guide', 'samina-rasul' ); ?></h3>
		<img class="sr-size-chart__image" src="<?php echo esc_url( $chart ); ?>" alt="<?php esc_attr_e( 'Samina Rasul size chart, measurements in inches', 'samina-rasul' ); ?>" loading="lazy" decoding="async">
		<p class="sr-size-chart__note"><?php esc_html_e( 'Between sizes, or after a different fit? Choose “Customized” and we will cut to your measurements.', 'samina-rasul' ); ?></p>
	</dialog>
	<script>
	(function () {
		var dialog = document.querySelector('.sr-size-chart');
		if (!dialog) { return; }
		document.addEventListener('click', function (e) {
			if (e.target.closest('.sr-size-chart-open')) { dialog.showModal(); return; }
			if (e.target.closest('.sr-size-chart-close')) { dialog.close(); return; }
			/* Clicking the backdrop: the event lands on the dialog itself, so
			 * anything outside its content box is a click on the backdrop. */
			if (e.target === dialog) {
				var box = dialog.getBoundingClientRect();
				if (e.clientX < box.left || e.clientX > box.right || e.clientY < box.top || e.clientY > box.bottom) { dialog.close(); }
			}
		});
	})();
	</script>
	<?php
}, 24 );

/**
 * The house star: the four-point mark used as the footer crest and as the
 * separator between footer links. Drawn rather than shipped as an image so it
 * inherits currentColor and stays crisp at any size.
 *
 * @param string $class Class attribute for the SVG.
 * @return string Inline SVG.
 */
function sr_star_svg( $class = 'sr-star' ) {
	return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
		<path d="M32 3 C34 20 44 30 61 32 C44 34 34 44 32 61 C30 44 20 34 3 32 C20 30 30 20 32 3 Z" stroke="currentColor" stroke-width="1.1"/>
		<circle cx="32" cy="32" r="2.6" fill="currentColor"/>
	</svg>';
}

/**
 * The mark that crowns the footer lockup.
 *
 * Prefers the WordPress Site Icon, because that is the one mark the client can
 * already change from Customizer → Site Identity without touching the theme;
 * then the custom logo; then the drawn star, so the crest is never missing.
 *
 * @return string Escaped markup.
 */
function sr_footer_mark() {
	$icon = get_site_icon_url( 192 );
	if ( $icon ) {
		return sprintf(
			'<img src="%s" alt="" width="96" height="96" loading="lazy" decoding="async">',
			esc_url( $icon )
		);
	}

	/*
	 * The house monogram, cut from the client lockup and rendered in cream so it
	 * reads on the burgundy footer. Ahead of the custom logo because that one is
	 * the full lockup — the wordmark repeats the site name printed directly below.
	 */
	$mark = sr_image_url( 'logo-mark.png' );
	if ( '' !== $mark ) {
		return sprintf(
			'<img src="%s" alt="" width="164" height="146" loading="lazy" decoding="async">',
			esc_url( $mark )
		);
	}

	if ( has_custom_logo() ) {
		return wp_get_attachment_image( (int) get_theme_mod( 'custom_logo' ), 'medium', false, array( 'alt' => '' ) );
	}

	return sr_star_svg( 'sr-star sr-star--crest' );
}

/**
 * Site branding: the client's lockup, shipped with the theme.
 *
 * Overrides Storefront's pluggable original — a child theme's functions.php
 * loads first, so the parent skips its own definition. The logo lives in
 * assets/images/ rather than the media library because attachments are database
 * rows and the deploy syncs no database; a Customizer logo still wins when set.
 *
 * @param bool $echo Echo the markup, or return it.
 * @return string
 */
function storefront_site_title_or_logo( $echo = true ) {
	/*
	 * Always a div, never an h1. Storefront promotes the logo to <h1> on the
	 * front page, where front-page.php already carries the hero headline as the
	 * page's h1 - two h1s on the most important page on the site, one of them
	 * saying nothing but the brand name that is already in the <title>.
	 */
	$tag = 'div';

	// Horizontal lockup: the client's original is stacked, which is too tall for
	// a header row — its wordmark ran into the navigation beneath.
	$logo = sr_image_url( 'logo-header.png' );

	if ( has_custom_logo() ) {
		$inner = get_custom_logo();
	} elseif ( '' !== $logo ) {
		$inner = sprintf(
			'<a class="sr-brand" href="%1$s" rel="home"><img src="%2$s" width="693" height="96" alt="%3$s" decoding="async"></a>',
			esc_url( home_url( '/' ) ),
			esc_url( $logo ),
			esc_attr( get_bloginfo( 'name' ) )
		);
	} else {
		$inner = sprintf(
			'<a href="%s" rel="home">%s</a>',
			esc_url( home_url( '/' ) ),
			esc_html( get_bloginfo( 'name' ) )
		);
	}

	$html = sprintf( '<%1$s class="sr-branding">%2$s</%1$s>', $tag, $inner );

	if ( ! $echo ) {
		return $html;
	}

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- assembled from escaped parts.
	return '';
}

/**
 * Footer crest: mark over the wordmark over the house line, centred.
 */
function sr_footer_lockup() {
	printf(
		'<div class="sr-footer-crest"><span class="sr-footer-crest__mark" aria-hidden="true">%1$s</span><a class="sr-footer-crest__name" href="%2$s" rel="home">%3$s</a><p class="sr-footer-crest__line">%4$s</p></div>',
		sr_footer_mark(), // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in sr_footer_mark().
		esc_url( home_url( '/' ) ),
		esc_html( get_bloginfo( 'name' ) ),
		esc_html__( 'Bridal Atelier', 'samina-rasul' )
	);
}
add_action( 'storefront_footer', 'sr_footer_lockup', 5 );

/**
 * Primary footer row: the five destinations a visitor actually navigates to,
 * on one rule-bounded line separated by the house star.
 */
function sr_footer_nav() {
	/*
	 * Label/URL pairs as list rows rather than translated-string array keys:
	 * as keys, two entries that happen to translate identically in some locale
	 * would silently overwrite each other and vanish from the footer.
	 */
	$links = array(
		array( 'label' => __( 'About Us', 'samina-rasul' ), 'url' => sr_page_url( 'about-us' ) ),
		array( 'label' => __( 'Client Care', 'samina-rasul' ), 'url' => sr_page_url( 'contact' ) ),
		array( 'label' => __( 'Consultations', 'samina-rasul' ), 'url' => sr_page_url( 'consultations' ) ),
		array( 'label' => __( 'Information', 'samina-rasul' ), 'url' => sr_page_url( 'faqs' ) ),
		array( 'label' => __( 'Saved', 'samina-rasul' ), 'url' => sr_page_url( 'saved' ) ),
		array( 'label' => __( 'My Account', 'samina-rasul' ), 'url' => sr_page_url( 'my-account' ) ),
	);

	echo '<nav class="sr-footer-nav" aria-label="' . esc_attr__( 'Footer', 'samina-rasul' ) . '">';
	foreach ( $links as $i => $link ) {
		if ( $i ) {
			echo '<span class="sr-footer-nav__sep" aria-hidden="true">' . sr_star_svg( 'sr-star sr-star--sep' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG.
		}
		printf( '<a href="%s">%s</a>', esc_url( $link['url'] ), esc_html( $link['label'] ) );
	}
	echo '</nav>';
}
add_action( 'storefront_footer', 'sr_footer_nav', 10 );

/**
 * Policy links. Kept out of the primary row so that row stays legible, but
 * still crawlable and one click away, which is what the policies are for.
 */
function sr_footer_legal_nav() {
	echo '<nav class="sr-footer-legal" aria-label="' . esc_attr__( 'Policies', 'samina-rasul' ) . '">';
	foreach ( sr_legal_pages() as $slug => $label ) {
		printf( '<a href="%s">%s</a>', esc_url( sr_page_url( $slug ) ), esc_html( $label ) );
	}
	echo '</nav>';
}

/**
 * The reference pages: policies and FAQs.
 *
 * Single source of truth for two things that must agree — the footer's policy
 * row, and the only pages allowed to show a breadcrumb. Kept as slug => label
 * so adding a policy updates both at once.
 *
 * @return array<string, string> Page slug => link label.
 */
function sr_legal_pages() {
	return array(
		'faqs'             => __( 'FAQs', 'samina-rasul' ),
		'shipping-policy'  => __( 'Payments &amp; Shipping', 'samina-rasul' ),
		'refund-policy'    => __( 'Refund Policy', 'samina-rasul' ),
		'terms-of-service' => __( 'Terms of Service', 'samina-rasul' ),
		'privacy-policy'   => __( 'Privacy Policy', 'samina-rasul' ),
	);
}
add_action( 'storefront_footer', 'sr_footer_legal_nav', 15 );

/**
 * Social links, as wordmarks rather than icon buttons to match the rest of the
 * footer's typographic treatment. Rendered only for handles the client has
 * actually set, so the row never shows a link that goes nowhere.
 *
 * @return string Markup, or '' when no handle is configured.
 */
function sr_footer_social_html() {
	$networks = array(
		'instagram' => __( 'Instagram', 'samina-rasul' ),
		'pinterest' => __( 'Pinterest', 'samina-rasul' ),
		'facebook'  => __( 'Facebook', 'samina-rasul' ),
	);

	$links = array();
	foreach ( $networks as $network => $label ) {
		$url = trim( (string) get_option( 'sr_social_' . $network, '' ) );
		if ( '' === $url ) {
			continue;
		}
		$links[] = sprintf(
			'<a class="sr-social" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	return $links ? '<div class="sr-footer-social">' . implode( '', $links ) . '</div>' : '';
}

/**
 * Bottom bar: copyright and house note on the left, social wordmarks right.
 *
 * Replaces storefront_credit() (removed below) rather than sitting beside it,
 * so the two halves of the row are siblings inside one flex container instead
 * of two stacked blocks.
 */
function sr_footer_bottom() {
	printf(
		'<div class="site-info sr-footer-bottom"><p class="sr-footer-bottom__copy"><span>%1$s</span><em>%2$s</em></p>%3$s</div>',
		esc_html(
			sprintf(
				/* translators: 1: year, 2: site name. */
				__( '© %1$s %2$s.', 'samina-rasul' ),
				gmdate( 'Y' ),
				get_bloginfo( 'name' )
			)
		),
		esc_html__( 'Every piece made to order and hand finished in Pakistan.', 'samina-rasul' ),
		sr_footer_social_html() // phpcs:ignore WordPress.Security.EscapeOutput -- assembled from escaped parts.
	);
}
add_action( 'storefront_footer', 'sr_footer_bottom', 20 );

/*
 * Storefront hooks its credit line in from the parent functions.php, which
 * loads after this file, so the removal has to wait for after_setup_theme.
 */
add_action( 'after_setup_theme', function () {
	remove_action( 'storefront_footer', 'storefront_credit', 20 );
} );

/**
 * Social profile URLs (WooCommerce → Settings → General).
 */
add_filter( 'woocommerce_general_settings', function ( $settings ) {
	foreach ( array( 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'pinterest' => 'Pinterest' ) as $key => $label ) {
		$settings[] = array(
			/* translators: %s: social network name. */
			'title'    => sprintf( __( '%s profile URL', 'samina-rasul' ), $label ),
			'desc'     => __( 'Full URL. Leave blank to hide the link in the footer.', 'samina-rasul' ),
			'id'       => 'sr_social_' . $key,
			'type'     => 'url',
			'default'  => '',
			'desc_tip' => true,
		);
	}
	return $settings;
} );

/**
 * A horizontally browsable row of products.
 *
 * The homepage puts merchandise in front of the visitor at several points in
 * the scroll rather than once, so this is rendered more than once per page and
 * the controls have to be scoped: each rail carries its own `data-sr-rail` key
 * and its buttons carry the same key, which is what sr-ui.js pairs them by.
 *
 * The row renders nothing at all when the query matches no products, so an
 * empty category cannot leave a heading stranded above WooCommerce's
 * "No products were found" notice.
 *
 * @param array $args {
 *     @type string $key       Unique slug for this rail. Required.
 *     @type string $eyebrow   Small caps line above the heading.
 *     @type string $heading   Section heading.
 *     @type array  $atts      [products] shortcode attributes.
 *     @type string $cta_url   Destination for the trailing "view all" button.
 *     @type string $cta_label Label for that button.
 *     @type string $class     Extra class on the <section>.
 *     @type string $layout    'rail' (default) for a horizontal scroller, or
 *                             'grid' for a static grid across the content
 *                             width. A page of identical scrollers stops
 *                             reading as separate offers.
 * }
 */
function sr_product_rail( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'key'       => '',
			'eyebrow'   => '',
			'heading'   => '',
			'atts'      => array(),
			'cta_url'   => '',
			'cta_label' => '',
			'class'     => '',
			'layout'    => 'rail',
		)
	);

	$is_grid = 'grid' === $args['layout'];

	if ( '' === $args['key'] || ! shortcode_exists( 'products' ) ) {
		return;
	}

	$atts = wp_parse_args( $args['atts'], array( 'limit' => 8, 'columns' => 4 ) );
	$pairs = '';
	foreach ( $atts as $name => $value ) {
		$pairs .= sprintf( ' %s="%s"', sanitize_key( $name ), esc_attr( $value ) );
	}

	$html = do_shortcode( '[products' . $pairs . ']' );

	// The shortcode returns its own "no products were found" notice rather than
	// an empty string. That notice carries no list item, so the presence of one
	// is what distinguishes a rendered row from an empty category.
	if ( false === strpos( $html, '<li' ) ) {
		return;
	}

	$key = sanitize_key( $args['key'] );
	?>
	<section class="sr-section sr-rail <?php echo $is_grid ? 'sr-rail--grid ' : ''; ?><?php echo esc_attr( $args['class'] ); ?>">
		<div class="sr-section__inner">
			<div class="sr-rowhead" data-sr-reveal>
				<div>
					<?php if ( '' !== $args['eyebrow'] ) : ?>
						<span class="sr-eyebrow"><?php echo esc_html( $args['eyebrow'] ); ?></span>
					<?php endif; ?>
					<h2><span class="sr-rowhead__arrow" aria-hidden="true">→</span> <?php echo esc_html( $args['heading'] ); ?></h2>
				</div>
				<div class="sr-atelier__actions">
					<?php if ( ! $is_grid ) : ?>
					<div class="sr-rail-controls" aria-label="<?php esc_attr_e( 'Browse this row', 'samina-rasul' ); ?>">
						<button type="button" class="sr-rail-control" data-sr-product-scroll="prev" data-sr-rail="<?php echo esc_attr( $key ); ?>" aria-label="<?php esc_attr_e( 'Show previous pieces', 'samina-rasul' ); ?>">←</button>
						<button type="button" class="sr-rail-control" data-sr-product-scroll="next" data-sr-rail="<?php echo esc_attr( $key ); ?>" aria-label="<?php esc_attr_e( 'Show next pieces', 'samina-rasul' ); ?>">→</button>
					</div>
					<?php endif; ?>
					<?php if ( '' !== $args['cta_url'] && '' !== $args['cta_label'] ) : ?>
						<a class="button sr-ghost" href="<?php echo esc_url( $args['cta_url'] ); ?>"><span><?php echo esc_html( $args['cta_label'] ); ?></span></a>
					<?php endif; ?>
				</div>
			</div>
			<div class="<?php echo $is_grid ? 'sr-product-grid' : 'sr-product-rail'; ?>"<?php echo $is_grid ? '' : ' data-sr-product-rail="' . esc_attr( $key ) . '"'; ?>>
				<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce shortcode output. ?>
			</div>
		</div>
	</section>
	<?php
}

/* ---------------------------------------------------------------------------
 * Search panel
 *
 * The header icon opens a full-width panel that answers as the visitor types,
 * rather than sending them to a results page for every guess. The panel is
 * progressive enhancement over the real search form, which stays in the header
 * and stays submittable, so search still works with JavaScript off.
 * ------------------------------------------------------------------------ */

/**
 * Read-only product search for the panel.
 *
 * Public on purpose - it returns exactly what the public search results page
 * already returns, so there is nothing here to gate. It is deliberately narrow:
 * published products only, catalogue-visible only, a hard result ceiling, and
 * every field escaped or plain-text before it leaves.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function sr_rest_search( WP_REST_Request $request ) {
	// The route is registered unconditionally; WooCommerce is not guaranteed to
	// be active when it is called, and every product helper below needs it.
	if ( ! function_exists( 'wc_get_product' ) ) {
		return new WP_REST_Response( array( 'query' => '', 'results' => array() ) );
	}

	$query_string = trim( (string) $request->get_param( 'q' ) );

	/*
	 * The saved-items page asks for a specific set of products rather than a
	 * search. Same shape of response, same escaping, so it reuses this route
	 * instead of a second one that would drift from it.
	 */
	$ids = array_slice(
		array_filter( array_map( 'absint', (array) $request->get_param( 'ids' ) ) ),
		0,
		60
	);

	$limit = $ids ? count( $ids ) : 8;

	$args = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => $limit,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'suppress_filters'    => false,
	);

	if ( $ids ) {
		$args['post__in'] = $ids;
		$args['orderby']  = 'post__in';
	} elseif ( '' !== $query_string ) {
		// Bound the term: a megabyte of "s" is a pointless LIKE scan.
		$args['s'] = mb_substr( $query_string, 0, 120 );
	} else {
		// Nothing typed yet: show the newest pieces, so the panel opens onto
		// merchandise instead of an empty box.
		$args['orderby'] = 'date';
		$args['order']   = 'DESC';
	}

	/*
	 * Respect the catalogue visibility flags the shop owner set per product.
	 *
	 * Applied to the id list as well as to searches. The id list was previously
	 * exempt on the reasoning that it is "the visitor's own saved set" - but the
	 * server has no way to know that. Anyone can call
	 * /wp-json/samina/v1/search?ids[]=1&ids[]=2… and read back the title, price
	 * and permalink of published products the shop owner deliberately hid from
	 * the catalogue, one integer at a time.
	 */
	if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
		$visibility = wc_get_product_visibility_term_ids();
		$hidden     = ( ! $ids && '' !== $query_string ) ? 'exclude-from-search' : 'exclude-from-catalog';
		if ( ! empty( $visibility[ $hidden ] ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded to 8 rows.
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'term_taxonomy_id',
					'terms'    => array( $visibility[ $hidden ] ),
					'operator' => 'NOT IN',
				),
			);
		}
	}

	$results = array();
	$query   = new WP_Query( $args );

	foreach ( $query->posts as $post ) {
		$product = wc_get_product( $post );
		if ( ! $product instanceof WC_Product ) {
			continue;
		}

		/*
		 * The panel prints the price as text, so the rendered price HTML has to
		 * be reduced to text: drop the screen-reader duplicate WooCommerce adds
		 * to a price range ("Price range: x through y") before stripping tags,
		 * then decode the entities (&#8360;, &nbsp;) that stripping leaves
		 * behind - textContent would otherwise show them literally.
		 */
		$price = preg_replace( '#<span class="screen-reader-text">.*?</span>#s', '', (string) $product->get_price_html() );
		$price = trim( html_entity_decode( wp_strip_all_tags( $price ), ENT_QUOTES, 'UTF-8' ) );
		$meta  = array_filter(
			array(
				sr_first_term_name( $product->get_id(), 'sr_collection' ),
				sr_first_term_name( $product->get_id(), 'product_cat' ),
			)
		);

		$results[] = array(
			'id'    => $product->get_id(),
			'title' => $product->get_name(),
			'url'   => $product->get_permalink(),
			'image' => (string) wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ),
			'meta'  => implode( ' · ', $meta ),
			// Short description where the shop owner wrote one, the full
			// description otherwise, so a card is never left with a bare title.
			'blurb' => wp_html_excerpt(
				wp_strip_all_tags( '' !== trim( $product->get_short_description() ) ? $product->get_short_description() : $product->get_description() ),
				110,
				'…'
			),
			'price' => '' !== $price ? $price : __( 'Price on inquiry', 'samina-rasul' ),
		);
	}

	wp_reset_postdata();

	return new WP_REST_Response(
		array(
			'query'   => $query_string,
			'results' => $results,
		)
	);
}

add_action( 'rest_api_init', function () {
	register_rest_route(
		'samina/v1',
		'/search',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
			'callback'            => 'sr_rest_search',
			'args'                => array(
				'q'   => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'ids' => array(
					'type'    => 'array',
					'default' => array(),
					'items'   => array( 'type' => 'integer' ),
				),
			),
		)
	);
} );

/**
 * The search panel itself. Printed on every page (including cart and checkout,
 * where the animation bundle is deliberately skipped), which is why its script
 * is a standalone file with no dependencies.
 */
function sr_search_panel() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	?>
	<div class="sr-search" id="sr-search" hidden data-sr-search-endpoint="<?php echo esc_url( rest_url( 'samina/v1/search' ) ); ?>">
		<div class="sr-search__backdrop" data-sr-search-close></div>
		<div class="sr-search__panel" role="dialog" aria-modal="true" aria-labelledby="sr-search-title">
			<button type="button" class="sr-search__close" data-sr-search-close aria-label="<?php esc_attr_e( 'Close search', 'samina-rasul' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</button>
			<div class="sr-search__inner">
				<h2 class="sr-search__title" id="sr-search-title"><?php esc_html_e( 'Search Couture', 'samina-rasul' ); ?></h2>
				<p class="sr-search__lede"><?php esc_html_e( 'Find formals and bridals by article code, collection, or style.', 'samina-rasul' ); ?></p>
				<form class="sr-search__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="screen-reader-text" for="sr-search-field"><?php esc_html_e( 'Search products', 'samina-rasul' ); ?></label>
					<input type="search" id="sr-search-field" name="s" value="" autocomplete="off"
						placeholder="<?php esc_attr_e( 'Search DK-001, Ujala, bridal, organza…', 'samina-rasul' ); ?>">
					<input type="hidden" name="post_type" value="product">
				</form>
				<div class="sr-search__results" data-sr-search-results aria-live="polite" aria-busy="false"></div>
				<p class="sr-search__status" data-sr-search-status hidden></p>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'sr_search_panel', 7 );

/*
 * Footer palette, fed to Storefront rather than fought with CSS.
 *
 * Storefront prints its Customizer colours as an inline stylesheet after the
 * child theme's, using selectors like
 * `.site-footer a:not(.button):not(.components-button)`. Any child rule for a
 * footer link therefore has to out-specify a generated selector to win, which
 * is a fight worth not having: giving Storefront the right defaults makes it
 * generate the right CSS instead.
 *
 * Filtered through `storefront_setting_default_values` and not through
 * `theme_mod_*`: Storefront registers its own `theme_mod_*` filters on `init`,
 * i.e. after this child theme's, and at the same priority, so it would simply
 * overwrite anything set that way. These are defaults, so a colour the client
 * later picks in the Customizer still wins.
 */
add_filter( 'storefront_setting_default_values', function ( $defaults ) {
	$defaults['storefront_footer_background_color'] = '#4a1f24';
	$defaults['storefront_footer_text_color']       = '#d9cfc2';
	$defaults['storefront_footer_link_color']       = '#f8f4ed';
	$defaults['storefront_footer_heading_color']    = '#f8f4ed';
	return $defaults;
} );

/* ---------------------------------------------------------------------------
 * Currency
 * ------------------------------------------------------------------------ */

/**
 * Print the currency as "PKR" rather than the ₨ ligature.
 *
 * The ₨ glyph is absent from both webfonts, so it fell back to a system face
 * and rendered as a cramped, mismatched mark tight against the figures. A
 * three-letter code is unambiguous for an international customer as well.
 *
 * @param string $symbol   Currency symbol.
 * @param string $currency Currency code.
 * @return string
 */
add_filter( 'woocommerce_currency_symbol', function ( $symbol, $currency ) {
	return 'PKR' === $currency ? 'PKR' : $symbol;
}, 10, 2 );

// A non-breaking space between code and figures, and never a line break
// between them. WooCommerce's stock left/right formats have no separator.
add_filter( 'woocommerce_price_format', function ( $format, $position ) {
	// %1$s is the currency, %2$s the figures; the order follows the shop's own
	// currency-position setting rather than forcing one.
	switch ( $position ) {
		case 'left':
		case 'left_space':
			return '%1$s&nbsp;%2$s';
		case 'right':
		case 'right_space':
			return '%2$s&nbsp;%1$s';
		default:
			return $format;
	}
}, 10, 2 );

/* ---------------------------------------------------------------------------
 * Single product page
 * ------------------------------------------------------------------------ */

/*
 * Storefront extras removed from the product page.
 *
 * - Sticky add-to-cart: it re-renders the product image, and this theme
 *   substitutes a full-bleed ornament placeholder for WooCommerce's grey
 *   camera icon, so on any product without a thumbnail the bar inflated to
 *   ~1000px pinned over the page and swallowed the scroll.
 * - Product pagination: floats a previous/next card against each viewport
 *   edge, which reads as stray products beside the content.
 * - Upsells: related products at the foot of the page are enough; two
 *   near-identical product grids in a row are not a recommendation, they are
 *   noise.
 *
 * On wp_loaded because Storefront registers these from its own template-hooks
 * file, which is included after this theme's functions.php.
 */
add_action( 'wp_loaded', function () {
	remove_action( 'storefront_after_footer', 'storefront_sticky_single_add_to_cart', 999 );
	remove_action( 'woocommerce_after_single_product_summary', 'storefront_single_product_pagination', 30 );
	remove_action( 'woocommerce_after_single_product_summary', 'storefront_upsell_display', 15 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );

	// Share icons are not part of this design.
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );

	/*
	 * No tab strip and no accordion under the summary: the spec table and the
	 * order terms are stated once in the summary itself, and Reviews is empty on
	 * every product.
	 *
	 * The description is not dropped with them - it is the piece's whole story,
	 * and behind a tab nobody opened it may as well not have been written.
	 * sr_single_description() prints it plainly in the tabs' place.
	 */
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );

	// Replaced by sr_single_price(), which stays current as choices are made.
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );

	/*
	 * The lead time and the payment terms both move out of the summary body.
	 * The lead time is already the first thing the trust row states, and the
	 * payment terms are the first thing the Order Terms accordion states, so
	 * printing them again between the size pills and the button was the same
	 * sentence three times in one column.
	 */
	remove_action( 'woocommerce_single_product_summary', 'sr_render_delivery_note', 25 );
	remove_action( 'woocommerce_single_product_summary', 'sr_render_order_terms', 35 );

	// SKU and category: the breadcrumb states the category and the specification
	// table carries the piece's identity, so the stock meta line was noise.
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
} );

/**
 * Save-for-later heart, beside the add-to-cart button.
 *
 * Deliberately local-only: the store has no wishlist page and no account-side
 * storage, so this remembers the piece in the browser rather than pretending to
 * a saved list that does not exist yet. Swap the two localStorage calls in
 * sr-product.js for REST calls when that list is built.
 */
add_action( 'woocommerce_after_add_to_cart_button', function () {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	printf(
		'<button type="button" class="sr-save" data-sr-save="%1$d" aria-pressed="false"><span class="screen-reader-text">%2$s</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 1 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/></svg></button>',
		(int) $product->get_id(),
		esc_html__( 'Save this piece', 'samina-rasul' )
	);
} );

/**
 * Breadcrumb, inside the summary column rather than in a full-width band above
 * the product.
 *
 * Storefront puts it in its own strip across the page, which on this layout
 * left a line of small caps floating over the gallery with nothing beside it.
 * Here it heads the column it belongs to, directly above the house line.
 * Removed from Storefront's hook on product pages only - archives keep the
 * full-width treatment their heroes are built around.
 */
function sr_single_breadcrumb() {
	woocommerce_breadcrumb(
		array(
			'wrap_before' => '<nav class="sr-crumbs woocommerce-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'samina-rasul' ) . '">',
			'wrap_after'  => '</nav>',
		)
	);
}
add_action( 'woocommerce_single_product_summary', 'sr_single_breadcrumb', 2 );

/*
 * Breadcrumbs are reference furniture, not navigation this store needs: the
 * catalogue is two categories deep and every page carries its own hero. They
 * are kept only on the policy/FAQ pages, where a visitor really is reading
 * reference material and wants a way back out. Product pages keep their own
 * crumb, which sr_single_breadcrumb() places inside the summary column.
 */
add_action( 'wp', function () {
	$is_product = function_exists( 'is_product' ) && is_product();

	if ( $is_product || ! is_page( array_keys( sr_legal_pages() ) ) ) {
		remove_action( 'storefront_before_content', 'woocommerce_breadcrumb', 10 );
	}
} );

/*
 * Comments are off sitewide. WooCommerce implements product reviews as
 * comments, so the product post type has to be exempted or closing comments
 * closes reviews with them.
 */
add_filter( 'comments_open', function ( $open, $post_id ) {
	return 'product' === get_post_type( $post_id ) ? $open : false;
}, 20, 2 );

add_filter( 'pings_open', '__return_false', 20 );

add_filter( 'comments_array', function ( $comments, $post_id ) {
	return 'product' === get_post_type( $post_id ) ? $comments : array();
}, 20, 2 );


/**
 * Price block: the live figure, with the catalogue range beside it.
 *
 * WooCommerce's own price line states a range on a variable product and then
 * never moves, so a shopper choosing a heavier dupatta and a fabric upgrade
 * watches "PKR 54,000 - 110,500" sit still while the real cost changes twice.
 * This prints one figure that sr-product.js keeps current - the chosen
 * variation plus whatever add-ons are ticked - and demotes the range to the
 * quiet context line it should have been.
 *
 * Falls back to WooCommerce's rendered price html whenever there is no figure
 * to total: that is what a bridal piece returns ("Price on inquiry", from
 * samina-core/bridal-flow), and it must pass through untouched.
 */
function sr_single_price() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$html = (string) $product->get_price_html();
	$text = trim( wp_strip_all_tags( $html ) );
	if ( '' === $text ) {
		return;
	}

	/*
	 * A rendered price carrying no digits means something upstream has decided
	 * this piece is not to be priced publicly - samina-core/bridal-flow returns
	 * "Price on inquiry" from woocommerce_get_price_html while the product
	 * still holds a real figure underneath. Print exactly what was rendered and
	 * go no further: reading get_price() here would put that figure on screen.
	 */
	if ( ! preg_match( '/\d/', $text ) ) {
		echo '<div class="sr-price">' . wp_kses_post( $html ) . '</div>';
		return;
	}

	/*
	 * get_variation_price() only exists on WC_Product_Variable, so the type has
	 * to be established before it is called - reaching for it on a simple
	 * product is a fatal, not an empty result.
	 */
	if ( $product->is_type( 'variable' ) ) {
		$min = wc_get_price_to_display( $product, array( 'price' => $product->get_variation_price( 'min', true ) ) );
		$max = wc_get_price_to_display( $product, array( 'price' => $product->get_variation_price( 'max', true ) ) );
	} else {
		$min = wc_get_price_to_display( $product );
		$max = $min;
	}

	// No usable figure - a bridal inquiry, or a product with no price set at
	// all. Print whatever WooCommerce decided to say and leave it alone.
	if ( ! is_numeric( $min ) || $min <= 0 ) {
		echo '<div class="sr-price">' . wp_kses_post( $html ) . '</div>';
		return;
	}

	/*
	 * The figure gets an element of its own, holding nothing but the digits, so
	 * sr-product.js can keep it current with textContent alone - no markup is
	 * ever rebuilt from a string on the client.
	 *
	 * Symbol and figure are assembled through the shop's own currency-position
	 * setting rather than forcing the symbol to the left.
	 */
	$symbol = '<span class="woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol() . '</span>';
	$figure = sprintf(
		'<span class="sr-price__figure" data-sr-price data-sr-base="%s">%s</span>',
		esc_attr( (string) $min ),
		esc_html( number_format( $min, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ) )
	);
	$right  = in_array( get_option( 'woocommerce_currency_pos' ), array( 'right', 'right_space' ), true );

	echo '<div class="sr-price">';
	printf(
		'<span class="sr-price__now woocommerce-Price-amount amount">%s</span>',
		wp_kses_post( $right ? $figure . '&nbsp;' . $symbol : $symbol . '&nbsp;' . $figure )
	);

	if ( $max > $min ) {
		printf(
			'<span class="sr-price__range">%s</span>',
			wp_kses_post(
				sprintf(
					/* translators: 1: lowest price, 2: highest price. */
					__( 'Ranges from %1$s &ndash; %2$s', 'samina-rasul' ),
					wc_price( $min ),
					// Figure only: the currency has already been stated once.
					number_format( $max, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() )
				)
			)
		);
	}

	echo '</div>';
}
add_action( 'woocommerce_single_product_summary', 'sr_single_price', 10 );

/**
 * Related products: eight, four across, rather than WooCommerce's three.
 *
 * Three pieces read as the tail end of a row that ran out rather than a
 * deliberate selection, and with 17 formals in the catalogue there is plenty to
 * show. Eight fills two clean rows at the width the grid below settles on.
 *
 * @param array $args Query args.
 * @return array
 */
function sr_related_products_args( $args ) {
	$args['posts_per_page'] = 8;
	$args['columns']        = 4;

	return $args;
}
add_filter( 'woocommerce_output_related_products_args', 'sr_related_products_args', 20 );

/**
 * The full description, under the price.
 *
 * WooCommerce prints the short description here, which is the one-line summary
 * meant for a card in a grid. On the product page itself there is room for the
 * whole thing, and the paragraph naming the handwork is the reason someone is
 * on this page - so the full description takes the slot and the short one stays
 * what it is for, the grids and the search results.
 *
 * Deliberately not a tab and not an accordion below the gallery: a panel that
 * has to be opened is a panel that is not read, and a second description under
 * the photographs describes the same piece twice.
 *
 * Falls back to the short description when a product has no long one, so a
 * half-filled product still says something.
 *
 * @return void
 */
function sr_single_description() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$copy = (string) $product->get_description();
	if ( '' === trim( wp_strip_all_tags( $copy ) ) ) {
		$copy = (string) $product->get_short_description();
	}
	if ( '' === trim( wp_strip_all_tags( $copy ) ) ) {
		return;
	}

	printf(
		'<div class="sr-product-copy woocommerce-product-details__short-description">%s</div>',
		wp_kses_post( wc_format_content( $copy ) )
	);
}
add_action( 'wp_loaded', function () {
	// Priority 20 is the slot the short description had; the reading order of
	// the summary column is unchanged, only which field fills it.
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
	add_action( 'woocommerce_single_product_summary', 'sr_single_description', 20 );
} );

/**
 * Breadcrumb separator: a hairline rule drawn by CSS rather than the "/" glyph.
 *
 * Storefront's own delimiter is styled by its icon font, which this theme does
 * not load, so the crumbs ran together with nothing legible between them.
 */
add_filter( 'woocommerce_breadcrumb_defaults', function ( $defaults ) {
	$defaults['delimiter'] = '<span class="sr-crumb-sep" aria-hidden="true"></span>';
	return $defaults;
}, 20 );

/*
 * Storefront's handheld footer bar duplicates the account, search and cart
 * icons this theme already puts in the header, and on a phone it sat on top of
 * the WhatsApp button. One set of controls is enough.
 */
add_action( 'wp_loaded', function () {
	remove_action( 'storefront_footer', 'storefront_handheld_footer_bar', 999 );
} );

/*
 * WooCommerce's magnifier zoom is the zoom: it tracks the cursor, which is what
 * a fabric close-up needs. It broke once because this theme forced
 * `width: 100% !important` onto every gallery image and the overlay copy
 * (.zoomImg) inherited it, so the magnified image was squashed to the column
 * width and read as a vertical stretch. That override is now scoped to the
 * slide image only - see style.css - and the overlay is left at its natural
 * size, where jQuery.zoom expects it.
 */

/**
 * Atelier note: the made-to-order promise, stated once at the point of decision
 * rather than left for the terms accordion below the fold.
 *
 * Lead time comes from the product's own `_sr_delivery_time` meta, so a piece
 * that takes longer says so; the deposit rule is the house policy and is fixed.
 * Sits directly above the cart, which is where the commitment is made.
 */
function sr_atelier_note() {
	$lead = (string) get_post_meta( get_the_ID(), '_sr_delivery_time', true );
	$lead = '' !== trim( $lead ) ? $lead : __( '8-9 weeks', 'samina-rasul' );

	/*
	 * Every write-up in the catalogue ends its customisation block with "Prices
	 * may vary depending on fabric selection", and the storefront said it
	 * nowhere. It belongs here rather than repeated in twenty-three
	 * descriptions - but only where it is true. On a piece with one price and no
	 * fabric choice it would be an invented caveat, so it is shown only when the
	 * page actually offers a choice that moves the figure.
	 */
	$product      = wc_get_product( get_the_ID() );
	$price_varies = false;

	if ( $product instanceof WC_Product ) {
		/*
		 * Being a variable product is not enough. Almost every formal is variable
		 * so the customer can state a size, and size does not move the figure - so
		 * the test is whether the variation prices actually differ, not whether
		 * variations exist.
		 */
		$price_varies = ( $product->is_type( 'variable' )
				&& (float) $product->get_variation_price( 'min', true ) !== (float) $product->get_variation_price( 'max', true ) )
			|| ( function_exists( 'sr_get_addons' ) && sr_get_addons( $product->get_id(), 'fabric' ) );
	}
	?>
	<aside class="sr-atelier-note">
		<svg class="sr-atelier-note__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
			<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><circle cx="12" cy="8" r=".6" fill="currentColor"/>
		</svg>
		<div class="sr-atelier-note__body">
			<span class="sr-atelier-note__title"><?php esc_html_e( 'Atelier Note', 'samina-rasul' ); ?></span>
			<p>
				<?php
				printf(
					/* translators: 1: lead time e.g. "8-9 weeks", 2: deposit share e.g. "50% deposit". */
					esc_html__( 'Each piece is custom-made. Please allow %1$s for delivery. Colour, fabric and size can be customised to your preference. A %2$s is required to begin production.', 'samina-rasul' ),
					'<strong>' . esc_html( $lead ) . '</strong>',
					'<strong>' . esc_html__( '50% deposit', 'samina-rasul' ) . '</strong>'
				);

				if ( $price_varies ) {
					echo ' ' . esc_html__( 'Prices vary with the fabric selected.', 'samina-rasul' );
				}
				?>
			</p>
		</div>
	</aside>
	<?php
}
/*
 * Directly under the cart row: the terms are read after the decision to buy,
 * not before it. Priority 20 puts it after the save-for-later heart, which
 * hooks the same action at 10.
 *
 * A bridal piece is not purchasable, so no cart form renders and that hook
 * never fires - which left the note off the one page whose whole proposition
 * is "made to order, 50% deposit". Priority 36 on the summary puts it in the
 * same place there, just after the trust row's own hook at 35.
 */
add_action( 'woocommerce_after_add_to_cart_button', 'sr_atelier_note', 20 );
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( $product instanceof WC_Product && ! $product->is_purchasable() ) {
		sr_atelier_note();
	}
}, 36 );

/**
 * Wrap the gallery in a plain block that owns the grid area.
 *
 * A sticky grid item is meant to be clamped by its grid area, but Chrome does
 * not clamp this one - pinned at the top it ran on past the accordions and
 * straight over the related products. Giving the column a normal block wrapper
 * puts the sticky element inside an ordinary containing block, where the clamp
 * is not in doubt.
 */
function sr_gallery_column() {
	echo '<div class="sr-gallery-col">';
	woocommerce_show_product_images();
	echo '</div>';
}
add_action( 'wp_loaded', function () {
	if ( ! function_exists( 'woocommerce_show_product_images' ) ) {
		return;
	}
	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
	add_action( 'woocommerce_before_single_product_summary', 'sr_gallery_column', 20 );
} );

/* ---------------------------------------------------------------------------
 * Saved items
 *
 * The heart on a product page writes its id to localStorage. This is where that
 * list is read back. Deliberately client-side end to end: there is no account
 * requirement to save a piece, so there is no server-side list to reconcile.
 * When accounts own the list, swap the two localStorage calls in sr-product.js
 * and the fetch below for REST calls and the page keeps working.
 * ------------------------------------------------------------------------ */

/**
 * [sr_saved] - the saved-items grid.
 *
 * Renders an empty, labelled shell; sr-saved.js fills it from localStorage.
 * The no-JS and empty states are both real markup rather than a spinner that
 * never resolves.
 *
 * @return string
 */
function sr_saved_shortcode() {
	ob_start();
	?>
	<section class="sr-saved" data-sr-saved data-sr-endpoint="<?php echo esc_url( rest_url( 'samina/v1/search' ) ); ?>">
		<header class="sr-saved__head" data-sr-reveal>
			<span class="sr-saved__art" aria-hidden="true"><?php echo sr_arch_motif_svg(); // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG. ?></span>
			<span class="sr-eyebrow"><?php esc_html_e( 'Your selection', 'samina-rasul' ); ?></span>
			<h2 class="sr-saved__title"><?php echo wp_kses_post( __( 'Pieces you have <em>set aside</em>', 'samina-rasul' ) ); ?></h2>
			<p class="sr-saved__lede"><?php esc_html_e( 'Kept on this device, so you can return to a piece while you decide. Nothing here is reserved — a made-to-order piece is only held once the order is confirmed.', 'samina-rasul' ); ?></p>
		</header>

		<div class="sr-saved__results" data-sr-saved-results aria-live="polite"></div>

		<div class="sr-saved__empty" data-sr-saved-empty>
			<span class="sr-saved__empty-mark" aria-hidden="true"><?php echo sr_star_svg( 'sr-star sr-star--crest' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG. ?></span>
			<p><?php esc_html_e( 'Nothing saved yet. Tap the heart on any piece and it will wait for you here.', 'samina-rasul' ); ?></p>
			<div class="sr-saved__empty-actions">
				<a class="button" href="<?php echo esc_url( sr_shop_url() ); ?>"><span><?php esc_html_e( 'Browse the collection', 'samina-rasul' ); ?></span></a>
				<a class="button sr-ghost" href="<?php echo esc_url( sr_term_url( 'bridals', 'product_cat' ) ); ?>"><span><?php esc_html_e( 'See the Bridals', 'samina-rasul' ); ?></span></a>
			</div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'sr_saved', 'sr_saved_shortcode' );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! has_shortcode( (string) get_post_field( 'post_content', get_the_ID() ), 'sr_saved' ) ) {
		return;
	}
	wp_enqueue_script(
		'sr-saved',
		get_stylesheet_directory_uri() . '/assets/js/sr-saved.js',
		array(),
		sr_asset_version( 'assets/js/sr-saved.js' ),
		true
	);
	wp_localize_script(
		'sr-saved',
		'srSavedL10n',
		array(
			'remove' => __( 'Remove from saved', 'samina-rasul' ),
			'error'  => __( 'Your saved pieces could not be loaded. Please refresh.', 'samina-rasul' ),
		)
	);
}, 25 );

/* ---------------------------------------------------------------------------
 * Editorial pages (policies, FAQs)
 *
 * These were a plain h1 and a wall of body copy. They carry real weight for a
 * made-to-order business - the deposit rule and the no-exchange rule are the
 * two things a first-time customer checks - so they get a masthead, a section
 * rhythm and, on the long ones, a contents rail.
 * ------------------------------------------------------------------------ */

/**
 * Pages that take the editorial treatment, and the eyebrow each one carries.
 *
 * @return array<string,string> slug => eyebrow.
 */
function sr_editorial_pages() {
	return array(
		'privacy-policy'    => __( 'Your data', 'samina-rasul' ),
		'shipping-policy'   => __( 'Orders &amp; delivery', 'samina-rasul' ),
		'refund-policy'     => __( 'Returns', 'samina-rasul' ),
		'terms-of-service'  => __( 'The agreement', 'samina-rasul' ),
		'faqs'              => __( 'Before you order', 'samina-rasul' ),
	);
}

add_filter( 'body_class', function ( $classes ) {
	if ( is_page() && array_key_exists( (string) get_post_field( 'post_name', get_the_ID() ), sr_editorial_pages() ) ) {
		$classes[] = 'sr-editorial';
	}
	return $classes;
} );

/**
 * Masthead: eyebrow, title, and the date the page last changed.
 *
 * The date is the post's own modified date rather than a hand-typed line, so
 * it cannot go stale the next time the client edits the copy.
 */
add_action( 'storefront_page_before', function () {
	$pages = sr_editorial_pages();
	$slug  = (string) get_post_field( 'post_name', get_the_ID() );
	if ( ! is_page() || ! isset( $pages[ $slug ] ) ) {
		return;
	}
	?>
	<header class="sr-page-head">
		<span class="sr-eyebrow"><?php echo wp_kses_post( $pages[ $slug ] ); ?></span>
		<h1 class="sr-page-head__title"><?php echo esc_html( get_the_title() ); ?></h1>
		<p class="sr-page-head__meta">
			<?php
			printf(
				/* translators: %s: date this page was last edited. */
				esc_html__( 'Last updated %s', 'samina-rasul' ),
				esc_html( get_the_modified_date( get_option( 'date_format' ) ) )
			);
			?>
		</p>
	</header>
	<?php
} );

/*
 * The masthead above states the title; Storefront's page header would say it a
 * second time. Removed on the template hook rather than filtered, because
 * storefront_page_header() prints the title directly.
 */
add_action( 'wp', function () {
	if ( ! is_page() ) {
		return;
	}
	$slug = (string) get_post_field( 'post_name', get_the_ID() );
	// Editorial pages print their own masthead; the landing pages open on a
	// hero that already names them.
	$own_title = array_merge( array_keys( sr_editorial_pages() ), array( 'consultations' ) );
	if ( in_array( $slug, $own_title, true ) ) {
		remove_action( 'storefront_page', 'storefront_page_header', 10 );
	}
} );

/**
 * Contents rail for the long policy pages, built from the sections already in
 * the content. Progressive enhancement: without it the page is simply a page.
 */
add_action( 'wp_footer', function () {
	$slug = (string) get_post_field( 'post_name', get_the_ID() );
	if ( ! is_page() || ! array_key_exists( $slug, sr_editorial_pages() ) || 'faqs' === $slug ) {
		return;
	}
	?>
	<script>
	(function () {
		var body = document.querySelector('.sr-legal');
		if (!body) { return; }
		var sections = body.querySelectorAll('.sr-legal__section[id] > h2');
		if (sections.length < 3) { return; }

		var nav = document.createElement('nav');
		nav.className = 'sr-legal__toc';
		nav.setAttribute('aria-label', <?php echo wp_json_encode( __( 'On this page', 'samina-rasul' ) ); ?>);
		var heading = document.createElement('span');
		heading.className = 'sr-legal__toc-title';
		heading.textContent = <?php echo wp_json_encode( __( 'On this page', 'samina-rasul' ) ); ?>;
		nav.appendChild(heading);

		var list = document.createElement('ol');
		Array.prototype.forEach.call(sections, function (h2) {
			var li = document.createElement('li');
			var a = document.createElement('a');
			a.href = '#' + h2.parentElement.id;
			a.textContent = h2.textContent.replace(/^\s*\d+\.\s*/, '');
			li.appendChild(a);
			list.appendChild(li);
		});
		nav.appendChild(list);
		body.insertBefore(nav, body.firstChild);
		body.classList.add('sr-legal--has-toc');
	})();
	</script>
	<?php
} );

/* ---------------------------------------------------------------------------
 * Consultations landing page
 *
 * The footer link used to drop people straight into the Bridals archive, which
 * answers "what does it look like" but not "how do I start". Bridal is the
 * highest-value, longest-lead thing the house sells and it is priced on
 * enquiry, so it earns a page whose only job is to turn interest into a first
 * message.
 * ------------------------------------------------------------------------ */

/**
 * WhatsApp link for a consultation, falling back to the contact page when no
 * number is configured, so the primary call to action is never a dead link.
 *
 * @return string
 */
function sr_consultation_url() {
	$number = function_exists( 'sr_whatsapp_number' ) ? sr_whatsapp_number() : '';
	if ( '' === $number ) {
		return sr_page_url( 'contact' );
	}
	return 'https://wa.me/' . $number . '?text=' . rawurlencode(
		__( 'Hello Samina Rasul, I would like to book a bridal consultation.', 'samina-rasul' )
	);
}

/**
 * Testimonials for the consultation page.
 *
 * Empty by default and rendered only when filled: a bridal page carries more
 * weight from three real names than from a wall of invented ones, and invented
 * ones are not ours to write. Fill via WooCommerce -> Settings -> General, one
 * per line as "Quote | Name | City".
 *
 * @return array<int,array{quote:string,author:string}>
 */
function sr_consultation_testimonials() {
	$raw = trim( (string) get_option( 'sr_bridal_testimonials', '' ) );
	if ( '' === $raw ) {
		return array();
	}

	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line ) );
		if ( '' === $parts[0] ) {
			continue;
		}
		$author = trim( implode( ' — ', array_filter( array_slice( $parts, 1 ) ) ) );
		$out[]  = array(
			'quote'  => $parts[0],
			'author' => $author,
		);
	}
	return $out;
}

add_filter( 'woocommerce_general_settings', function ( $settings ) {
	$settings[] = array(
		'title'    => __( 'Bridal testimonials', 'samina-rasul' ),
		'desc'     => __( 'One per line: "Quote | Name | City". Leave blank to hide the section on the consultations page.', 'samina-rasul' ),
		'id'       => 'sr_bridal_testimonials',
		'type'     => 'textarea',
		'default'  => '',
		'css'      => 'height:9em;',
		'desc_tip' => true,
	);
	return $settings;
} );

/**
 * [sr_consultation] - the consultation landing page.
 *
 * @return string
 */
function sr_consultation_shortcode() {
	$url      = sr_consultation_url();
	$external = 0 === strpos( $url, 'https://wa.me/' );
	$attrs    = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
	$arrow    = '<svg class="sr-btn-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';

	$pillars = array(
		array(
			'title' => __( 'The Silhouette', 'samina-rasul' ),
			'copy'  => __( 'We begin with the architecture of the piece. Whether it is a regal farshi gharara or a sleek modern pishwas, the silhouette has to flatter your form and suit the occasion.', 'samina-rasul' ),
		),
		array(
			'title' => __( 'Fabric &amp; Handwork', 'samina-rasul' ),
			'copy'  => __( 'From pure sheesha silk to mukesh-embellished organza. The cloth decides the zardozi, naqshi and resham it can carry — and it is the largest part of the price.', 'samina-rasul' ),
		),
		array(
			'title' => __( 'Colour &amp; Timing', 'samina-rasul' ),
			'copy'  => __( 'The palette has to hold up in your event\'s daylight or lamplight. Set against our seven to nine week making time, that is what makes a date realistic.', 'samina-rasul' ),
		),
	);

	$steps = array(
		array(
			'eyebrow' => __( 'Day one', 'samina-rasul' ),
			'title'   => __( 'The message', 'samina-rasul' ),
			'copy'    => __( 'You reach out with the occasion, the date and any inspiration you have gathered. A WhatsApp message is enough to begin.', 'samina-rasul' ),
		),
		array(
			'eyebrow' => __( 'Week one', 'samina-rasul' ),
			'title'   => __( 'The consultation', 'samina-rasul' ),
			'copy'    => __( 'A detailed conversation, at no charge, about silhouette, cloth, colour and the story behind the piece. You see samples of the handwork.', 'samina-rasul' ),
		),
		array(
			'eyebrow' => __( 'Within days', 'samina-rasul' ),
			'title'   => __( 'Design and quote', 'samina-rasul' ),
			'copy'    => __( 'A design concept and a written quote against the exact piece discussed. Nothing is charged until you accept it.', 'samina-rasul' ),
		),
		array(
			'eyebrow' => __( 'Weeks two to nine', 'samina-rasul' ),
			'title'   => __( 'Cutting and handwork', 'samina-rasul' ),
			'copy'    => __( 'A 50% advance secures your slot in the atelier and cutting begins. You are sent progress as the embellishment goes on.', 'samina-rasul' ),
		),
		array(
			'eyebrow' => __( 'Before the date', 'samina-rasul' ),
			'title'   => __( 'Fitting and delivery', 'samina-rasul' ),
			'copy'    => __( 'Finished with a complimentary fitting adjustment, pressed, and delivered with time in hand.', 'samina-rasul' ),
		),
	);

	$prepare = array(
		array( __( 'The occasion date', 'samina-rasul' ), __( 'Your deadline tells us what is achievable inside the seven to nine week window.', 'samina-rasul' ) ),
		array( __( 'Colour palette', 'samina-rasul' ), __( 'Any shades, inspiration or family heirloom you want the piece to sit beside.', 'samina-rasul' ) ),
		array( __( 'Silhouette preference', 'samina-rasul' ), __( 'Lehnga, pishwas, gharara, or a contemporary long shirt.', 'samina-rasul' ) ),
		array( __( 'Budget range', 'samina-rasul' ), __( 'This is what lets us recommend the right cloth and the right weight of handwork.', 'samina-rasul' ) ),
		array( __( 'Measurements', 'samina-rasul' ), __( 'Optional. Rough figures help us picture proportion; exact sizing happens later.', 'samina-rasul' ) ),
	);

	$faqs = array(
		array(
			__( 'Why is a 50% advance required?', 'samina-rasul' ),
			__( 'Every piece is made to order. The advance buys the cloth and books your slot with the artisans who will do the handwork. The balance is due before dispatch.', 'samina-rasul' ),
		),
		array(
			__( 'What if I need the piece in under seven weeks?', 'samina-rasul' ),
			__( 'Sometimes possible, depending on what is already in the atelier. Say your date in the first message and we will tell you honestly whether we can meet it.', 'samina-rasul' ),
		),
		array(
			__( 'Can I change the design after paying the advance?', 'samina-rasul' ),
			__( 'Colour shades and dupatta fabric can still move in the first week. Once cutting and handwork begin, the silhouette is fixed.', 'samina-rasul' ),
		),
	);

	$testimonials = sr_consultation_testimonials();

	$routes = array(
		array(
			'icon'  => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
			'title' => __( 'Message the atelier', 'samina-rasul' ),
			'copy'  => __( 'For quick questions and sharing inspiration. The fastest way to an answer.', 'samina-rasul' ),
			'cta'   => __( 'Start on WhatsApp', 'samina-rasul' ),
			'url'   => $url,
			'ext'   => $external,
		),
		array(
			'icon'  => '<rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
			'title' => __( 'Write to us', 'samina-rasul' ),
			'copy'  => __( 'Send your date, budget and references in one place and we will reply with a plan.', 'samina-rasul' ),
			'cta'   => __( 'Go to contact', 'samina-rasul' ),
			'url'   => sr_page_url( 'contact' ),
			'ext'   => false,
		),
		array(
			'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
			'title' => __( 'See the bridal work', 'samina-rasul' ),
			'copy'  => __( 'Browse finished commissions before you decide what you want yours to be.', 'samina-rasul' ),
			'cta'   => __( 'View bridals', 'samina-rasul' ),
			'url'   => sr_term_url( 'bridals', 'product_cat' ),
			'ext'   => false,
		),
	);

	ob_start();
	?>
	<div class="sr-consultation">

		<section class="sr-cs-hero">
			<div class="sr-cs-hero__inner">
				<p class="sr-cs-eyebrow" data-sr-reveal><?php esc_html_e( 'Samina Rasul Bridal Atelier', 'samina-rasul' ); ?></p>
				<?php
				/*
				 * h1, not h2. Storefront's page header is removed for this page
				 * (see the $own_title list above), so with an h2 here the
				 * consultations landing page - the entry point for the highest
				 * value thing the house sells - shipped with no h1 at all.
				 */
				?>
				<h1 class="sr-cs-hero__title" data-sr-reveal><?php echo wp_kses_post( __( 'A piece made for <em>one occasion,</em> and <em>one person.</em>', 'samina-rasul' ) ); ?></h1>
				<div class="sr-cs-hero__actions" data-sr-reveal>
					<a class="sr-cs-btn" href="#sr-cs-book"><?php esc_html_e( 'Begin your journey', 'samina-rasul' ); ?><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG. ?></a>
					<a class="sr-cs-btn sr-cs-btn--ghost" href="<?php echo esc_url( sr_term_url( 'bridals', 'product_cat' ) ); ?>"><?php esc_html_e( 'View the collection', 'samina-rasul' ); ?></a>
				</div>
				<ul class="sr-cs-hero__badges" data-sr-reveal>
					<li><span class="sr-cs-badge__title"><?php esc_html_e( 'No consultation fee', 'samina-rasul' ); ?></span><span class="sr-cs-badge__desc"><?php esc_html_e( 'Initial design discussion', 'samina-rasul' ); ?></span></li>
					<li><span class="sr-cs-badge__title"><?php esc_html_e( '7–9 weeks', 'samina-rasul' ); ?></span><span class="sr-cs-badge__desc"><?php esc_html_e( 'Average delivery time', 'samina-rasul' ); ?></span></li>
					<li><span class="sr-cs-badge__title"><?php esc_html_e( '50% advance', 'samina-rasul' ); ?></span><span class="sr-cs-badge__desc"><?php esc_html_e( 'To begin hand-crafting', 'samina-rasul' ); ?></span></li>
				</ul>
			</div>
		</section>

		<section class="sr-cs-philosophy">
			<div class="sr-cs-philosophy__inner">
				<div class="sr-cs-phil__left" data-sr-reveal>
					<h2><?php echo wp_kses_post( __( 'Three things <em>settle</em> the piece.', 'samina-rasul' ) ); ?></h2>
					<p><?php esc_html_e( 'Before a single thread is drawn we settle the foundation. Your first consultation exists to understand these three, and nothing is quoted until they are agreed.', 'samina-rasul' ); ?></p>
					<a class="sr-cs-btn sr-cs-btn--outline" href="#sr-cs-book"><?php esc_html_e( 'Book a discussion', 'samina-rasul' ); ?></a>
				</div>
				<div class="sr-cs-phil__right">
					<?php foreach ( $pillars as $i => $pillar ) : ?>
						<div class="sr-cs-phil__item" data-sr-reveal>
							<span class="sr-cs-phil__num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
							<div>
								<h3><?php echo wp_kses_post( $pillar['title'] ); ?></h3>
								<p><?php echo esc_html( $pillar['copy'] ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<section class="sr-section sr-process sr-cs-process">
			<div class="sr-section__inner">
				<div class="sr-section__intro" data-sr-reveal>
					<span class="sr-eyebrow"><?php esc_html_e( 'The atelier process', 'samina-rasul' ); ?></span>
					<h2><?php esc_html_e( 'From the first message to the final fitting', 'samina-rasul' ); ?></h2>
				</div>
				<ol class="sr-timeline">
					<?php foreach ( $steps as $i => $step ) : ?>
						<li class="sr-timeline__step">
							<span class="sr-timeline__marker" aria-hidden="true"></span>
							<div class="sr-timeline__card">
								<div class="sr-timeline__heading">
									<span class="sr-process__num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
									<span class="sr-timeline__eyebrow"><?php echo esc_html( $step['eyebrow'] ); ?></span>
								</div>
								<h3><?php echo esc_html( $step['title'] ); ?></h3>
								<p><?php echo esc_html( $step['copy'] ); ?></p>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		</section>

		<?php if ( $testimonials ) : ?>
			<section class="sr-cs-testimonials">
				<h2 data-sr-reveal><?php echo wp_kses_post( __( 'Cherished by our <em>brides</em>', 'samina-rasul' ) ); ?></h2>
				<div class="sr-cs-test__grid">
					<?php foreach ( $testimonials as $t ) : ?>
						<figure class="sr-cs-test__card" data-sr-reveal>
							<blockquote><?php echo esc_html( $t['quote'] ); ?></blockquote>
							<?php if ( '' !== $t['author'] ) : ?>
								<figcaption><?php echo esc_html( $t['author'] ); ?></figcaption>
							<?php endif; ?>
						</figure>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="sr-cs-prepare">
			<div class="sr-cs-prepare__inner">
				<div data-sr-reveal>
					<h2><?php echo wp_kses_post( __( 'Five things that make the <em>first conversation</em> useful.', 'samina-rasul' ) ); ?></h2>
				</div>
				<ol class="sr-cs-prep__list" data-sr-reveal>
					<?php foreach ( $prepare as $item ) : ?>
						<li>
							<div>
								<h4><?php echo esc_html( $item[0] ); ?></h4>
								<p><?php echo esc_html( $item[1] ); ?></p>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		</section>

		<section class="sr-cs-book" id="sr-cs-book">
			<div class="sr-cs-book__header" data-sr-reveal>
				<h2><?php esc_html_e( 'Begin your consultation', 'samina-rasul' ); ?></h2>
				<p><?php esc_html_e( 'Choose whichever feels most comfortable. We typically reply within 24 hours.', 'samina-rasul' ); ?></p>
			</div>
			<div class="sr-cs-book__grid">
				<?php foreach ( $routes as $route ) : ?>
					<a class="sr-cs-book__card" href="<?php echo esc_url( $route['url'] ); ?>"<?php echo $route['ext'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?> data-sr-reveal>
						<svg class="sr-cs-book__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $route['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG paths. ?></svg>
						<h3><?php echo esc_html( $route['title'] ); ?></h3>
						<p><?php echo esc_html( $route['copy'] ); ?></p>
						<span class="sr-cs-book__link"><?php echo esc_html( $route['cta'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="sr-cs-faq">
			<h2 data-sr-reveal><?php esc_html_e( 'Frequently asked questions', 'samina-rasul' ); ?></h2>
			<?php foreach ( $faqs as $faq ) : ?>
				<details class="sr-faq sr-cs-faq__item">
					<summary class="sr-faq__q"><span><?php echo esc_html( $faq[0] ); ?></span><i class="sr-faq__mark" aria-hidden="true"></i></summary>
					<div class="sr-faq__a"><p><?php echo esc_html( $faq[1] ); ?></p></div>
				</details>
			<?php endforeach; ?>
			<p class="sr-cs-faq__more" data-sr-reveal>
				<?php esc_html_e( 'More questions about ordering, delivery and returns:', 'samina-rasul' ); ?>
				<a href="<?php echo esc_url( sr_page_url( 'faqs' ) ); ?>"><?php esc_html_e( 'read the full FAQs', 'samina-rasul' ); ?></a>
			</p>
		</section>

		<section class="sr-cs-final">
			<span class="sr-cs-final__ghost" aria-hidden="true"><?php esc_html_e( 'BRIDAL', 'samina-rasul' ); ?></span>
			<div class="sr-cs-final__inner" data-sr-reveal>
				<h2><?php echo wp_kses_post( __( 'Tell us the date.<br><em>We will tell you what is possible.</em>', 'samina-rasul' ) ); ?></h2>
				<a class="sr-cs-btn sr-cs-btn--gold" href="<?php echo esc_url( $url ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- static attribute string. ?>><?php esc_html_e( 'Start a consultation', 'samina-rasul' ); ?><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG. ?></a>
			</div>
		</section>

	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'sr_consultation', 'sr_consultation_shortcode' );

/**
 * Pages the theme depends on, created if they are missing.
 *
 * The consultations page is a shortcode in an otherwise empty page, and it was
 * only ever created by hand in wp-admin. Pages are database rows and the deploy
 * syncs no database, so it existed locally and 404'd on the live site. Anything
 * the theme links to unconditionally has to be able to create itself.
 *
 * Idempotent: matches on slug, restores a trashed page rather than making a
 * duplicate, and does nothing when the page is already published.
 *
 * @return void
 */
function sr_ensure_pages() {
	$pages = array(
		'consultations' => array(
			'title'   => __( 'Consultations', 'samina-rasul' ),
			'content' => '[sr_consultation]',
		),
		'saved'         => array(
			'title'   => __( 'Saved', 'samina-rasul' ),
			'content' => '[sr_saved]',
		),
	);

	foreach ( $pages as $slug => $page ) {
		$existing = get_page_by_path( $slug );

		if ( $existing instanceof WP_Post ) {
			// A trashed page still owns the slug, so publishing it back is the
			// only way the URL resolves again without a duplicate.
			if ( 'publish' !== $existing->post_status ) {
				wp_update_post(
					array(
						'ID'          => $existing->ID,
						'post_status' => 'publish',
					)
				);
			}
			continue;
		}

		wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_name'      => $slug,
				'post_title'     => $page['title'],
				'post_content'   => $page['content'],
				'post_status'    => 'publish',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);
	}
}

/*
 * Run once per theme version rather than on every request: the check is three
 * queries, and a released theme should not pay for them on every page view.
 * Bumping SR_PAGES_VERSION re-runs it after the list above changes.
 */
define( 'SR_PAGES_VERSION', '1' );
add_action( 'admin_init', function () {
	if ( get_option( 'sr_pages_version' ) === SR_PAGES_VERSION ) {
		return;
	}
	sr_ensure_pages();
	update_option( 'sr_pages_version', SR_PAGES_VERSION );
} );
add_action( 'after_switch_theme', 'sr_ensure_pages' );

/**
 * Comments are off, so nothing about them renders.
 *
 * Storefront calls comments_template() whenever comments are open OR the post
 * reports any comments, and the second half of that test is what surfaced the
 * "Comments are closed." notice on ordinary pages: Action Scheduler stores its
 * logs as rows in the comments table, keyed by action id, and those ids collide
 * with page ids — so get_comments_number() counted scheduler logs as comments.
 *
 * Overrides Storefront's pluggable original (the child theme loads first).
 *
 * @return void
 */
function storefront_display_comments() {
}

/*
 * Belt and braces: with comment support removed, WordPress stops offering the
 * discussion box in the editor and no other template can render a comment form
 * for these post types. Products keep support — WooCommerce reviews are
 * comments, and removing it would remove reviews.
 */
add_action( 'init', function () {
	remove_post_type_support( 'page', 'comments' );
	remove_post_type_support( 'page', 'trackbacks' );
	remove_post_type_support( 'post', 'comments' );
	remove_post_type_support( 'post', 'trackbacks' );
}, 20 );

/**
 * The atelier arch: a Mughal cusped arch over a hairline horizon.
 *
 * A second house mark, distinct from sr_ornament_svg(). The rosette had ended
 * up on the values band, the craft section, the empty cart and every archive
 * CTA at once, which is the point at which a motif stops being a signature and
 * starts being wallpaper. The arch is the doorway figure from the campaign
 * photography, so it carries the same vocabulary without repeating the mark.
 *
 * @return string Inline SVG.
 */
function sr_arch_motif_svg() {
	return '<svg class="sr-arch" viewBox="0 0 240 180" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
		<g stroke="currentColor" stroke-width="1.1" stroke-linecap="round">
			<path d="M60 172V86c0-33 27-60 60-60s60 27 60 60v86"/>
			<path d="M78 172V88c0-23 19-42 42-42s42 19 42 42v84"/>
			<path d="M120 26c-9-9-9-17 0-26 9 9 9 17 0 26Z"/>
			<path d="M20 172h200"/>
			<path d="M96 172v-46c0-13 11-24 24-24s24 11 24 24v46"/>
		</g>
		<g fill="currentColor">
			<circle cx="120" cy="112" r="3.4"/>
			<circle cx="60" cy="86" r="2.2"/>
			<circle cx="180" cy="86" r="2.2"/>
		</g>
		<path d="M40 152c26-14 54-21 80-21s54 7 80 21" stroke="currentColor" stroke-width="1" stroke-dasharray="3 7"/>
	</svg>';
}

/**
 * Contact form subjects, as slug => label.
 *
 * A fixed list rather than a free-text field, so the value can be validated
 * against a whitelist before it reaches the mail subject line.
 *
 * @return array<string, string>
 */
function sr_contact_subjects() {
	return array(
		'general'     => __( 'General inquiry', 'samina-rasul' ),
		'order'       => __( 'Order status', 'samina-rasul' ),
		'appointment' => __( 'Studio appointment', 'samina-rasul' ),
		'press'       => __( 'Press &amp; collaboration', 'samina-rasul' ),
	);
}

/**
 * Handle a contact form submission.
 *
 * Mirrors the newsletter handler: nonce, honeypot, whitelist, then a redirect
 * carrying a status flag so a refresh never resubmits. The message is mailed to
 * the site admin with the sender on Reply-To — the address is never used as
 * From, which would fail SPF and land the mail in spam.
 *
 * @return void
 */
function sr_handle_contact() {
	$back = wp_get_referer() ? wp_get_referer() : home_url( '/contact/' );

	$redirect = function ( $status ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'sr_contact', $status, remove_query_arg( 'sr_contact', $back ) ) . '#sr-contact-form' );
		exit;
	};

	if ( ! isset( $_POST['sr_contact_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sr_contact_nonce'] ) ), 'sr_contact' ) ) {
		$redirect( 'error' );
	}

	// Honeypot: a real visitor never fills this, bots usually do.
	if ( ! empty( $_POST['sr_website'] ) ) {
		$redirect( 'ok' ); // Silent success, so the bot learns nothing.
	}

	// One submission per address per minute, keyed by IP, to stop a flood.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'sr_contact_' . md5( $ip );
	if ( '' !== $ip && get_transient( $key ) ) {
		$redirect( 'throttled' );
	}

	$name    = isset( $_POST['sr_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sr_name'] ) ) : '';
	$email   = isset( $_POST['sr_email'] ) ? sanitize_email( wp_unslash( $_POST['sr_email'] ) ) : '';
	$phone   = isset( $_POST['sr_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['sr_phone'] ) ) : '';
	$subject = isset( $_POST['sr_subject'] ) ? sanitize_key( wp_unslash( $_POST['sr_subject'] ) ) : '';
	$message = isset( $_POST['sr_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sr_message'] ) ) : '';

	$subjects = sr_contact_subjects();

	if ( '' === $name || ! is_email( $email ) || '' === $message || ! isset( $subjects[ $subject ] ) ) {
		$redirect( 'error' );
	}

	// Bound the payload so a single request cannot post a novel into the inbox.
	$name    = mb_substr( $name, 0, 100 );
	$phone   = mb_substr( $phone, 0, 40 );
	$message = mb_substr( $message, 0, 4000 );

	$body = sprintf(
		/* translators: 1: name, 2: email, 3: phone, 4: subject, 5: message. */
		__( "Name: %1\$s\nEmail: %2\$s\nPhone: %3\$s\nSubject: %4\$s\n\n%5\$s\n", 'samina-rasul' ),
		$name,
		$email,
		'' !== $phone ? $phone : __( '(not given)', 'samina-rasul' ),
		wp_strip_all_tags( $subjects[ $subject ] ),
		$message
	);

	// The client inquiries inbox, not the administrator address - see
	// samina-core/contact-details.php.
	$inbox = function_exists( 'sr_contact_email' ) ? sr_contact_email() : get_option( 'admin_email' );

	$sent = $inbox && wp_mail(
		$inbox,
		sprintf(
			/* translators: 1: site name, 2: inquiry subject. */
			__( '[%1$s] %2$s', 'samina-rasul' ),
			get_bloginfo( 'name' ),
			wp_strip_all_tags( $subjects[ $subject ] )
		),
		$body,
		array( 'Reply-To: ' . $name . ' <' . $email . '>' )
	);

	if ( '' !== $ip ) {
		set_transient( $key, 1, MINUTE_IN_SECONDS );
	}

	// 'unsent', not 'error': the submission was valid and the mail transport
	// failed. Telling the visitor to check the fields would send them round a
	// loop they cannot win.
	$redirect( $sent ? 'ok' : 'unsent' );
}
add_action( 'admin_post_nopriv_sr_contact', 'sr_handle_contact' );
add_action( 'admin_post_sr_contact', 'sr_handle_contact' );
