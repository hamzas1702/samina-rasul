<?php
/**
 * Search-engine and social meta.
 *
 * Extracted from functions.php, which had grown past 2,500 lines by carrying
 * the head tags, a REST route, the search panel and a whole landing page
 * alongside the theme's actual setup. This is a self-contained unit with one
 * job, and it is the part most likely to be replaced wholesale the day the
 * client installs Yoast - which every function here already checks for.
 *
 * WooCommerce emits the Product and Breadcrumb JSON-LD; core emits the <title>.
 * What lives here is everything neither of them provides.
 *
 * @package samina-rasul
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is a dedicated SEO plugin driving the head?
 *
 * Everything below is skipped when one is, so installing Yoast or Rank Math
 * later does not produce two competing sets of tags.
 *
 * @return bool
 */
function sr_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'All_in_One_SEO_Pack' );
}

/**
 * Canonical URL for the current view.
 *
 * Built from the route rather than from REQUEST_URI. The previous version used
 * home_url( add_query_arg( array() ) ), which echoed back whatever query string
 * the visitor arrived with - so /?fbclid=… self-canonicalised and every ad click
 * minted another indexable copy of the homepage.
 *
 * Paginated archives canonicalise to their own page, not to page 1: pointing
 * page 2 at page 1 tells Google the products that only appear on page 2 live at
 * a URL where they cannot be found.
 *
 * @return string Canonical URL, or '' when this view should not declare one.
 */
function sr_canonical_url() {
	$url = '';

	if ( is_singular() ) {
		$permalink = get_permalink( get_queried_object_id() );
		return is_string( $permalink ) ? $permalink : '';
	}

	if ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();
		$link = $term instanceof WP_Term ? get_term_link( $term ) : '';
		$url  = is_string( $link ) ? $link : '';
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$url = sr_shop_url();
	} elseif ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_home() ) {
		$posts_page = (int) get_option( 'page_for_posts' );
		$permalink  = $posts_page > 0 ? get_permalink( $posts_page ) : '';
		$url        = is_string( $permalink ) && '' !== $permalink ? $permalink : home_url( '/' );
	}

	// Search results and 404s get no canonical: core already marks them
	// noindex, and a canonical on a noindex page is a contradictory signal.
	if ( '' === $url ) {
		return '';
	}

	$paged = (int) get_query_var( 'paged' );
	if ( $paged > 1 ) {
		$url = user_trailingslashit( trailingslashit( $url ) . 'page/' . $paged, 'paged' );
	}

	return $url;
}

/**
 * Description for the current view, never empty.
 *
 * Prefers a hand-written excerpt - pages have excerpt support enabled below
 * precisely so the client has somewhere to write one - then the content, then
 * the tagline, then the house line.
 *
 * @return string
 */
function sr_meta_description() {
	$description = '';

	if ( is_singular() ) {
		$post_id     = get_queried_object_id();
		$excerpt     = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : get_post_field( 'post_content', $post_id );
		$description = wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $excerpt ) ), 30, '' );
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term        = get_queried_object();
		$description = $term instanceof WP_Term ? wp_trim_words( wp_strip_all_tags( term_description( $term ) ), 30, '' ) : '';
	}

	if ( '' === trim( (string) $description ) ) {
		$description = get_bloginfo( 'description', 'display' );
	}
	if ( '' === trim( (string) $description ) ) {
		$description = __( 'Hand embellished formals and bridal couture from the house of Samina Rasul. Zardozi, mukesh and resham worked by hand, cut to your measure and made to order.', 'samina-rasul' );
	}

	return (string) $description;
}

/**
 * Pages get an excerpt field.
 *
 * Without it the client has no way to write a meta description for About,
 * Consultations or the policies - the tag was auto-trimmed from the content,
 * and a shortcode-driven page strips to nothing and fell through to the generic
 * house sentence. Core already supports this; it is only switched off by
 * default for pages.
 */
add_action( 'init', function () {
	add_post_type_support( 'page', 'excerpt' );
} );

/**
 * Baseline SEO and social meta.
 *
 * WooCommerce already emits Product/Offer/Breadcrumb JSON-LD, and core emits
 * the <title>; what is missing for launch is a description, a canonical URL and
 * Open Graph/Twitter tags, without which every share renders as a bare link.
 */
function sr_seo_meta() {
	if ( sr_seo_plugin_active() ) {
		return;
	}

	$title       = wp_get_document_title();
	$description = sr_meta_description();
	$url         = sr_canonical_url();

	/*
	 * og:type is per-view, not "singular means product". Every page on this
	 * site is singular, so the old check shared About Us, the FAQs and the
	 * privacy policy to Facebook as if they were purchasable products.
	 */
	$og_type = 'website';
	if ( function_exists( 'is_product' ) && is_product() ) {
		$og_type = 'product';
	} elseif ( is_singular( 'post' ) ) {
		$og_type = 'article';
	}

	// Prefer an attachment, so the image's real dimensions can be declared -
	// Facebook and LinkedIn crop badly, or defer the crop to a later fetch,
	// when they have to download the file to find out how big it is.
	$image_id = is_singular() ? (int) get_post_thumbnail_id( get_queried_object_id() ) : 0;
	if ( ! $image_id ) {
		$image_id = (int) get_theme_mod( 'sr_home_hero_image', 0 );
	}

	$image  = '';
	$width  = 0;
	$height = 0;
	if ( $image_id > 0 ) {
		$src = wp_get_attachment_image_src( $image_id, 'large' );
		if ( is_array( $src ) && ! empty( $src[0] ) ) {
			$image  = $src[0];
			$width  = (int) $src[1];
			$height = (int) $src[2];
		}
	}
	if ( '' === $image ) {
		// The file the theme ships with, which has no attachment record - so its
		// dimensions are read off disk rather than out of the database.
		$image = sr_home_image_url( 'sr_home_hero_image', 'large' );
		$size  = '' !== $image && function_exists( 'sr_image_dimensions' ) ? sr_image_dimensions( $image ) : false;
		if ( $size ) {
			$width  = $size[0];
			$height = $size[1];
		}
	}

	printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
	if ( '' !== $url ) {
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) );
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	}
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( $og_type ) );
	printf( '<meta property="og:locale" content="%s">' . "\n", esc_attr( str_replace( '-', '_', get_bloginfo( 'language' ) ) ) );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $description ) );
	printf( '<meta name="twitter:card" content="%s">' . "\n", $image ? 'summary_large_image' : 'summary' );
	if ( $image ) {
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
		printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $image ) );
		if ( $width > 0 && $height > 0 ) {
			printf( '<meta property="og:image:width" content="%d">' . "\n", $width );
			printf( '<meta property="og:image:height" content="%d">' . "\n", $height );
		}
	}
}
add_action( 'wp_head', 'sr_seo_meta', 2 );

/**
 * Organization markup for the house itself.
 *
 * WooCommerce describes the products; nothing described the business. This is
 * what connects the site to the brand's social profiles and its logo in search,
 * which for a single-brand atelier is the cheapest structured data on the list.
 *
 * Emitted once per page rather than per view: it describes the publisher, and
 * the publisher does not change between pages.
 */
function sr_organization_schema() {
	if ( sr_seo_plugin_active() ) {
		return;
	}

	$same_as = array();
	foreach ( array( 'instagram', 'facebook', 'pinterest' ) as $network ) {
		$profile = trim( (string) get_option( 'sr_social_' . $network, '' ) );
		if ( '' !== $profile ) {
			$same_as[] = $profile;
		}
	}

	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Organization',
		'name'        => get_bloginfo( 'name' ),
		'url'         => home_url( '/' ),
		'description' => wp_strip_all_tags( sr_meta_description() ),
	);

	$logo = sr_image_url( 'logo-lockup.png' );
	if ( '' !== $logo ) {
		$schema['logo'] = $logo;
	}

	if ( $same_as ) {
		$schema['sameAs'] = $same_as;
	}

	// Only when the client has actually supplied a number - a contactPoint
	// pointing nowhere is worse than none at all.
	$whatsapp = function_exists( 'sr_whatsapp_number' ) ? sr_whatsapp_number() : '';
	if ( '' !== $whatsapp ) {
		$schema['contactPoint'] = array(
			array(
				'@type'       => 'ContactPoint',
				'contactType' => 'sales',
				'telephone'   => '+' . $whatsapp,
			),
		);
	}

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
	);
}
add_action( 'wp_head', 'sr_organization_schema', 3 );

/**
 * Core's rel_canonical() also emits a canonical on singular views, so leaving
 * it in place alongside sr_seo_meta() prints the tag twice. Drop core's copy
 * only when this theme is the one providing it.
 */
add_action( 'wp', function () {
	if ( sr_seo_plugin_active() ) {
		return;
	}
	remove_action( 'wp_head', 'rel_canonical' );
} );
