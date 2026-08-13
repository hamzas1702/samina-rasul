<?php
/**
 * Client-editable homepage content.
 *
 * Registers a "Homepage Content" Customizer panel so the hero, atelier row,
 * featured collections and the two split sections can be edited on the live
 * site without a deploy. Every field defaults to the copy the theme shipped
 * with, so an untouched install renders exactly as before.
 *
 * Fields are declared once in sr_home_fields() and driven from that registry —
 * registration, sanitising and template output all read the same array, so a
 * new field is one entry rather than three edits that can drift apart.
 *
 * @package samina-rasul
 */

defined( 'ABSPATH' ) || exit;

/**
 * Editable homepage field registry.
 *
 * Types:
 *  - text     Single line. Sanitised and escaped as plain text.
 *  - textarea Multi-line body copy. Plain text.
 *  - html     Limited inline markup (<em>, <br>) for display headings.
 *  - image    Media library attachment ID, with a file fallback.
 *
 * 'fallback_file' on image fields is resolved through sr_image_url(), which
 * keeps the theme's shipped photography working until the client picks their
 * own from the media library.
 *
 * @return array<string, array<string, mixed>> Setting ID => definition.
 */
function sr_home_fields() {
	static $fields = null;

	if ( null !== $fields ) {
		return $fields;
	}

	$fields = array(

		// 01 · Hero.
		'sr_home_hero_image'         => array(
			'section'       => 'sr_home_hero',
			'type'          => 'image',
			'label'         => __( 'Background image', 'samina-rasul' ),
			'description'   => __( 'Full-bleed. Landscape, at least 1920px wide.', 'samina-rasul' ),
			'fallback_file' => 'hero-section.jpg',
		),
		'sr_home_hero_eyebrow'       => array(
			'section' => 'sr_home_hero',
			'type'    => 'text',
			'label'   => __( 'Eyebrow', 'samina-rasul' ),
			'default' => __( 'Hand embellished · Made to order', 'samina-rasul' ),
		),
		'sr_home_hero_line_1'        => array(
			'section'     => 'sr_home_hero',
			'type'        => 'text',
			'label'       => __( 'Headline line 1', 'samina-rasul' ),
			'description' => __( 'The three headline lines animate in one at a time, so each needs its own field. Keep them short enough to sit on one line.', 'samina-rasul' ),
			'default'     => __( 'Couture that', 'samina-rasul' ),
		),
		'sr_home_hero_line_2'        => array(
			'section' => 'sr_home_hero',
			'type'    => 'text',
			'label'   => __( 'Headline line 2', 'samina-rasul' ),
			'default' => __( 'remembers the hand', 'samina-rasul' ),
		),
		'sr_home_hero_line_3'        => array(
			'section'     => 'sr_home_hero',
			'type'        => 'text',
			'label'       => __( 'Headline line 3', 'samina-rasul' ),
			'description' => __( 'Shown in italic.', 'samina-rasul' ),
			'default'     => __( 'that made it', 'samina-rasul' ),
		),
		'sr_home_hero_body'          => array(
			'section' => 'sr_home_hero',
			'type'    => 'textarea',
			'label'   => __( 'Body copy', 'samina-rasul' ),
			'default' => __( 'Formals and bridals from the house of Samina Rasul, with zardozi, mukesh and resham worked by hand, cut to your measure and finished to order.', 'samina-rasul' ),
		),
		'sr_home_hero_cta_primary'   => array(
			'section' => 'sr_home_hero',
			'type'    => 'text',
			'label'   => __( 'Primary button label', 'samina-rasul' ),
			'default' => __( 'Shop Formals', 'samina-rasul' ),
		),
		'sr_home_hero_cta_secondary' => array(
			'section' => 'sr_home_hero',
			'type'    => 'text',
			'label'   => __( 'Secondary button label', 'samina-rasul' ),
			'default' => __( 'Explore Bridals', 'samina-rasul' ),
		),

		// 03 · New from the atelier.
		'sr_home_atelier_eyebrow'    => array(
			'section' => 'sr_home_atelier',
			'type'    => 'text',
			'label'   => __( 'Eyebrow', 'samina-rasul' ),
			'default' => __( 'Just arrived', 'samina-rasul' ),
		),
		'sr_home_atelier_heading'    => array(
			'section' => 'sr_home_atelier',
			'type'    => 'text',
			'label'   => __( 'Heading', 'samina-rasul' ),
			'default' => __( 'New from the atelier', 'samina-rasul' ),
		),

		// 04 · Featured collections.
		'sr_home_collections_eyebrow' => array(
			'section' => 'sr_home_collections',
			'type'    => 'text',
			'label'   => __( 'Eyebrow', 'samina-rasul' ),
			'default' => __( 'Explore the house', 'samina-rasul' ),
		),
		'sr_home_collections_heading' => array(
			'section' => 'sr_home_collections',
			'type'    => 'html',
			'label'   => __( 'Heading', 'samina-rasul' ),
			'default' => __( 'Featured <em>Collections</em>', 'samina-rasul' ),
		),
		'sr_home_collections_body'    => array(
			'section' => 'sr_home_collections',
			'type'    => 'textarea',
			'label'   => __( 'Body copy', 'samina-rasul' ),
			'default' => __( 'Discover our exquisite range of handcrafted couture, where every stitch tells a story of elegance and tradition.', 'samina-rasul' ),
		),
		'sr_home_tile_1_badge'        => array(
			'section'     => 'sr_home_collections',
			'type'        => 'text',
			'label'       => __( 'Card 1 · badge', 'samina-rasul' ),
			'description' => __( 'Card images are set on the category itself, under Products → Categories.', 'samina-rasul' ),
			'default'     => __( 'Ready to order', 'samina-rasul' ),
		),
		'sr_home_tile_1_title'        => array(
			'section' => 'sr_home_collections',
			'type'    => 'text',
			'label'   => __( 'Card 1 · title', 'samina-rasul' ),
			'default' => __( 'Formal Collection', 'samina-rasul' ),
		),
		'sr_home_tile_1_body'         => array(
			'section' => 'sr_home_collections',
			'type'    => 'textarea',
			'label'   => __( 'Card 1 · body', 'samina-rasul' ),
			'default' => __( 'Sophisticated designs for special occasions, cut and embellished to order.', 'samina-rasul' ),
		),
		'sr_home_tile_1_cta'          => array(
			'section' => 'sr_home_collections',
			'type'    => 'text',
			'label'   => __( 'Card 1 · link label', 'samina-rasul' ),
			'default' => __( 'View Collection', 'samina-rasul' ),
		),
		'sr_home_tile_2_badge'        => array(
			'section' => 'sr_home_collections',
			'type'    => 'text',
			'label'   => __( 'Card 2 · badge', 'samina-rasul' ),
			'default' => __( 'By consultation', 'samina-rasul' ),
		),
		'sr_home_tile_2_title'        => array(
			'section' => 'sr_home_collections',
			'type'    => 'text',
			'label'   => __( 'Card 2 · title', 'samina-rasul' ),
			'default' => __( 'Bridal Couture', 'samina-rasul' ),
		),
		'sr_home_tile_2_body'         => array(
			'section' => 'sr_home_collections',
			'type'    => 'textarea',
			'label'   => __( 'Card 2 · body', 'samina-rasul' ),
			'default' => __( 'Breathtaking bridal masterpieces blending tradition with contemporary elegance.', 'samina-rasul' ),
		),
		'sr_home_tile_2_cta'          => array(
			'section' => 'sr_home_collections',
			'type'    => 'text',
			'label'   => __( 'Card 2 · link label', 'samina-rasul' ),
			'default' => __( 'View Collection', 'samina-rasul' ),
		),
		'sr_home_tile_3_badge'        => array(
			'section' => 'sr_home_collections',
			'type'    => 'text',
			'label'   => __( 'Card 3 · badge', 'samina-rasul' ),
			'default' => __( 'Collection', 'samina-rasul' ),
		),
		'sr_home_tile_3_title'        => array(
			'section' => 'sr_home_collections',
			'type'    => 'text',
			'label'   => __( 'Card 3 · title', 'samina-rasul' ),
			'default' => __( 'Dhanak', 'samina-rasul' ),
		),
		'sr_home_tile_3_body'         => array(
			'section' => 'sr_home_collections',
			'type'    => 'textarea',
			'label'   => __( 'Card 3 · body', 'samina-rasul' ),
			'default' => __( 'Refined ready pieces with signature Samina Rasul craftsmanship.', 'samina-rasul' ),
		),
		'sr_home_tile_3_cta'          => array(
			'section' => 'sr_home_collections',
			'type'    => 'text',
			'label'   => __( 'Card 3 · link label', 'samina-rasul' ),
			'default' => __( 'View Collection', 'samina-rasul' ),
		),

		// 05 · Formals split.
		'sr_home_formals_image'     => array(
			'section'       => 'sr_home_formals',
			'type'          => 'image',
			'label'         => __( 'Image', 'samina-rasul' ),
			'description'   => __( 'Portrait crop works best.', 'samina-rasul' ),
			'fallback_file' => '2026/07/DSC08885.jpg',
		),
		'sr_home_formals_image_alt' => array(
			'section'     => 'sr_home_formals',
			'type'        => 'text',
			'label'       => __( 'Image alt text', 'samina-rasul' ),
			'description' => __( 'Describes the image for screen readers and search engines.', 'samina-rasul' ),
			'default'     => __( 'A Samina Rasul formal piece', 'samina-rasul' ),
		),
		'sr_home_formals_heading'   => array(
			'section'     => 'sr_home_formals',
			'type'        => 'html',
			'label'       => __( 'Heading', 'samina-rasul' ),
			'description' => __( 'Use &lt;br&gt; for a line break and &lt;em&gt; for italics.', 'samina-rasul' ),
			'default'     => __( 'Worn once,<br><em>remembered longer</em>', 'samina-rasul' ),
		),
		'sr_home_formals_body'      => array(
			'section' => 'sr_home_formals',
			'type'    => 'textarea',
			'label'   => __( 'Body copy', 'samina-rasul' ),
			'default' => __( 'Occasionwear you can order today. Choose your pieces and size, add a fabric upgrade if you wish. Every order is cut and embellished by hand, for you alone.', 'samina-rasul' ),
		),

		// 06 · Bridals split.
		'sr_home_bridals_image'     => array(
			'section'       => 'sr_home_bridals',
			'type'          => 'image',
			'label'         => __( 'Image', 'samina-rasul' ),
			'description'   => __( 'Portrait crop works best.', 'samina-rasul' ),
			'fallback_file' => '2026/07/DSC08885-1.jpg',
		),
		'sr_home_bridals_image_alt' => array(
			'section'     => 'sr_home_bridals',
			'type'        => 'text',
			'label'       => __( 'Image alt text', 'samina-rasul' ),
			'description' => __( 'Describes the image for screen readers and search engines.', 'samina-rasul' ),
			'default'     => __( 'A Samina Rasul bridal piece', 'samina-rasul' ),
		),
		'sr_home_bridals_eyebrow'   => array(
			'section' => 'sr_home_bridals',
			'type'    => 'text',
			'label'   => __( 'Eyebrow', 'samina-rasul' ),
			'default' => __( 'The Bridals', 'samina-rasul' ),
		),
		'sr_home_bridals_heading'   => array(
			'section'     => 'sr_home_bridals',
			'type'        => 'html',
			'label'       => __( 'Heading', 'samina-rasul' ),
			'description' => __( 'Use &lt;br&gt; for a line break and &lt;em&gt; for italics.', 'samina-rasul' ),
			'default'     => __( 'Begin the<br><em>conversation</em>', 'samina-rasul' ),
		),
		'sr_home_bridals_body'      => array(
			'section' => 'sr_home_bridals',
			'type'    => 'textarea',
			'label'   => __( 'Body copy', 'samina-rasul' ),
			'default' => __( 'A bridal piece is never bought from a shelf, so you will find no price tags here. Tell us about your day, and the atelier will design around you, from fabric and embellishment to silhouette and fit.', 'samina-rasul' ),
		),
	);

	return $fields;
}

/**
 * Stored value for a homepage field, falling back to the shipped copy.
 *
 * Returns the raw string — templates apply the escaping appropriate to the
 * field type: esc_html() for text/textarea, wp_kses_post() for html.
 *
 * @param string $id Setting ID.
 * @return string
 */
function sr_home_text( $id ) {
	$fields = sr_home_fields();

	if ( ! isset( $fields[ $id ] ) ) {
		return '';
	}

	$default = isset( $fields[ $id ]['default'] ) ? $fields[ $id ]['default'] : '';
	$value   = get_theme_mod( $id, $default );

	// A theme mod saved as an empty string is a deliberate "hide this", not a
	// missing value, so it is returned as-is rather than falling back.
	return is_string( $value ) ? $value : $default;
}

/**
 * Image URL for a homepage image field.
 *
 * Prefers the client's media-library choice, then the file the theme shipped
 * with. Returns '' when neither exists, which is the signal every template
 * uses to draw its CSS placeholder instead of a broken <img>.
 *
 * Templates should prefer sr_home_image(); this is for contexts that need a
 * bare URL, such as the og:image meta tag.
 *
 * @param string $id   Setting ID.
 * @param string $size Registered image size.
 * @return string Image URL, or '' when absent.
 */
function sr_home_image_url( $id, $size = 'full' ) {
	$fields = sr_home_fields();

	if ( ! isset( $fields[ $id ] ) ) {
		return '';
	}

	$attachment_id = (int) get_theme_mod( $id, 0 );

	if ( $attachment_id > 0 ) {
		$url = wp_get_attachment_image_url( $attachment_id, $size );
		if ( $url ) {
			return $url;
		}
	}

	// The attachment was deleted, or none was chosen — use the shipped file.
	if ( ! empty( $fields[ $id ]['fallback_file'] ) && function_exists( 'sr_image_url' ) ) {
		return sr_image_url( $fields[ $id ]['fallback_file'] );
	}

	return '';
}

/**
 * Escaped <img> markup for a homepage image field, or '' when none is set.
 *
 * A media-library choice goes through wp_get_attachment_image() so the browser
 * gets srcset and sizes. That matters here: the client uploads their own
 * photography, and without srcset a 6000px camera original would be served
 * whole into a 900px slot on every visit.
 *
 * The theme's shipped fallback has no attachment record, so it is emitted as a
 * plain tag — those files are already web-sized.
 *
 * @param string               $id   Setting ID.
 * @param array<string,string> $attr Attributes for the tag, e.g. class and alt.
 * @return string Escaped <img> markup, or ''.
 */
function sr_home_image( $id, $attr = array() ) {
	$fields = sr_home_fields();

	if ( ! isset( $fields[ $id ] ) ) {
		return '';
	}

	$attachment_id = (int) get_theme_mod( $id, 0 );

	if ( $attachment_id > 0 && wp_attachment_is_image( $attachment_id ) ) {
		// An empty alt field means "no alt supplied", so let WordPress use the
		// alt text stored on the attachment rather than shipping alt="".
		if ( isset( $attr['alt'] ) && '' === $attr['alt'] ) {
			unset( $attr['alt'] );
		}

		return wp_get_attachment_image( $attachment_id, 'full', false, $attr );
	}

	$url = ! empty( $fields[ $id ]['fallback_file'] ) && function_exists( 'sr_image_url' )
		? sr_image_url( $fields[ $id ]['fallback_file'] )
		: '';

	if ( '' === $url ) {
		return '';
	}

	$attr['src'] = $url;
	if ( ! isset( $attr['alt'] ) ) {
		$attr['alt'] = '';
	}

	/*
	 * Intrinsic dimensions, read off the file. Without them the browser cannot
	 * reserve the space, so the page reflows when the image lands - and the hero
	 * is both the largest element on the page and the first one painted, which
	 * makes this the biggest single contributor to layout shift on the homepage.
	 */
	if ( ! isset( $attr['width'] ) || ! isset( $attr['height'] ) ) {
		$size = sr_image_dimensions( $url );
		if ( $size ) {
			$attr['width']  = $size[0];
			$attr['height'] = $size[1];
		}
	}

	$markup = '';
	foreach ( $attr as $name => $value ) {
		$markup .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
	}

	return '<img' . $markup . '>';
}

/**
 * Pixel dimensions of a local image URL.
 *
 * Only for the theme's own shipped files and the uploads directory - anything
 * that does not map back to a readable local path returns false rather than
 * being fetched. Memoised per request: the homepage renders three of these and
 * getimagesize() opens the file each time it is called.
 *
 * @param string $url Image URL.
 * @return array{0:int,1:int}|false Width and height, or false.
 */
function sr_image_dimensions( $url ) {
	static $cache = array();

	if ( array_key_exists( $url, $cache ) ) {
		return $cache[ $url ];
	}

	$uploads = wp_get_upload_dir();
	$path    = str_replace(
		array( get_stylesheet_directory_uri(), $uploads['baseurl'] ),
		array( get_stylesheet_directory(), $uploads['basedir'] ),
		$url
	);

	$cache[ $url ] = false;

	// Unchanged means nothing was substituted: not a local file this theme owns.
	if ( $path === $url || ! is_readable( $path ) ) {
		return false;
	}

	// A corrupt or truncated image should degrade to "no dimensions", not emit
	// a PHP warning into the middle of the markup.
	$size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- see above.

	if ( is_array( $size ) && ! empty( $size[0] ) && ! empty( $size[1] ) ) {
		$cache[ $url ] = array( (int) $size[0], (int) $size[1] );
	}

	return $cache[ $url ];
}

/**
 * Sanitise a display heading down to the inline markup the field documents.
 *
 * wp_kses_post() would also allow links, images and block elements, which a
 * pasted-in heading could use to break these tightly art-directed layouts.
 *
 * @param string $value Raw value.
 * @return string
 */
function sr_sanitize_inline_html( $value ) {
	return wp_kses(
		(string) $value,
		array(
			'em'     => array(),
			'strong' => array(),
			'br'     => array(),
			'span'   => array( 'class' => array() ),
		)
	);
}

/**
 * Sanitise an attachment ID, rejecting anything that is not a real image.
 *
 * Guards against a hand-crafted request pointing a setting at an arbitrary
 * post ID, which would otherwise leak a private attachment's URL.
 *
 * @param mixed $value Raw setting value.
 * @return int Attachment ID, or 0.
 */
function sr_sanitize_attachment_id( $value ) {
	$id = absint( $value );

	if ( $id < 1 || 'attachment' !== get_post_type( $id ) ) {
		return 0;
	}

	return wp_attachment_is_image( $id ) ? $id : 0;
}

/**
 * Register the Homepage Content panel.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 * @return void
 */
function sr_register_homepage_customizer( $wp_customize ) {
	$wp_customize->add_panel(
		'sr_homepage',
		array(
			'title'       => __( 'Homepage Content', 'samina-rasul' ),
			'description' => __( 'Text and imagery for the homepage. Leave a field untouched to keep the copy the theme shipped with.', 'samina-rasul' ),
			'priority'    => 30,
		)
	);

	// Sections, in display order.
	$sections = array(
		'sr_home_hero'        => __( 'Hero', 'samina-rasul' ),
		'sr_home_atelier'     => __( 'New from the atelier', 'samina-rasul' ),
		'sr_home_collections' => __( 'Featured collections', 'samina-rasul' ),
		'sr_home_formals'     => __( 'Formals section', 'samina-rasul' ),
		'sr_home_bridals'     => __( 'Bridals section', 'samina-rasul' ),
	);

	$priority = 10;
	foreach ( $sections as $section_id => $section_title ) {
		$wp_customize->add_section(
			$section_id,
			array(
				'title'    => $section_title,
				'panel'    => 'sr_homepage',
				'priority' => $priority,
			)
		);
		$priority += 10;
	}

	$sanitizers = array(
		'text'     => 'sanitize_text_field',
		'textarea' => 'sanitize_textarea_field',
		'html'     => 'sr_sanitize_inline_html',
		'image'    => 'sr_sanitize_attachment_id',
	);

	foreach ( sr_home_fields() as $id => $field ) {
		$type = $field['type'];

		$wp_customize->add_setting(
			$id,
			array(
				'default'           => 'image' === $type ? 0 : ( isset( $field['default'] ) ? $field['default'] : '' ),
				'sanitize_callback' => $sanitizers[ $type ],
				'transport'         => 'refresh',
				'capability'        => 'edit_theme_options',
			)
		);

		$args = array(
			'label'   => $field['label'],
			'section' => $field['section'],
		);

		if ( ! empty( $field['description'] ) ) {
			$args['description'] = $field['description'];
		}

		if ( 'image' === $type ) {
			$args['mime_type'] = 'image';
			$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, $id, $args ) );
			continue;
		}

		// <em>/<br> headings get a textarea so the markup is not cramped.
		$args['type'] = 'text' === $type ? 'text' : 'textarea';
		$wp_customize->add_control( $id, $args );
	}
}
add_action( 'customize_register', 'sr_register_homepage_customizer' );
