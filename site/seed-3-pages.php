<?php
// Seed: content pages. Policies carry [AWAITING CLIENT TEXT] markers — the client
// has final written policies that must replace those sections verbatim.
// Run via: wp eval-file seed-3-pages.php

function sr_seed_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) {
		wp_update_post( array( 'ID' => $existing->ID, 'post_title' => $title, 'post_content' => $content ) );
		WP_CLI::log( "updated: /$slug/" );
		return $existing->ID;
	}
	$id = wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
	) );
	WP_CLI::log( "created: /$slug/" );
	return $id;
}

sr_seed_page( 'about-us', 'About Us', <<<HTML
<!-- wp:paragraph -->
<p>Samina Rasul is a house of hand-embellished formals and bridal wear. Every piece begins as uncut cloth and is worked — zardozi, mukesh, resham, gota — by embellishers whose craft has passed through generations.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Nothing we make exists before you order it. Each garment is cut to your measure and finished by hand over seven to nine weeks. That patience is the point: it is the difference between a dress and a piece you keep.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><em>[AWAITING CLIENT TEXT — founder story, atelier history, photography]</em></p>
<!-- /wp:paragraph -->
HTML );

sr_seed_page( 'faqs', 'FAQs', <<<HTML
<!-- wp:heading {"level":3} --><h3>How long does my order take?</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Every piece is made to order. Dhanak pieces are ready in 7–8 weeks; Ujala pieces in 8–9 weeks. The exact window is shown on each product page.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>How do payments work?</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>A minimum 50% advance confirms your order. International orders are paid 100% in advance. The balance on domestic orders is due before dispatch.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>Can I customize a piece?</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Yes — choose “Customized” as your size and we will cut to your measurements. Bridal pieces are fully bespoke: fabric, embellishment, silhouette and fit are decided in consultation over WhatsApp.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>What is your return policy?</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Because each piece is made for you, we do not offer returns or exchanges for change of mind, and customized pieces cannot be exchanged. A refund applies only if a product arrives destroyed. See the full <a href="/refund-policy/">Refund Policy</a>.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>How do bridal orders work?</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Bridal pieces are not sold from the shelf and carry no listed price. Tap “Inquire on WhatsApp” on any bridal piece and our team will guide you from first conversation to final fitting.</p><!-- /wp:paragraph -->
HTML );

sr_seed_page( 'contact', 'Contact', <<<HTML
<!-- wp:paragraph --><p>For orders, customization and bridal consultations, WhatsApp is the fastest way to reach the atelier — use the chat button on any page.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><em>[AWAITING CLIENT DETAILS — WhatsApp number, email address, studio address, Instagram handle]</em></p><!-- /wp:paragraph -->
HTML );

sr_seed_page( 'shipping-policy', 'Shipping Policy', <<<HTML
<!-- wp:paragraph --><p><strong>Summary of confirmed terms:</strong> every piece is made to order (7–9 weeks by collection). Domestic orders are confirmed with a minimum 50% advance; international orders require 100% advance payment.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><em>[AWAITING CLIENT TEXT — replace with the client's full written Shipping Policy verbatim]</em></p><!-- /wp:paragraph -->
HTML );

sr_seed_page( 'refund-policy', 'Refund Policy', <<<HTML
<!-- wp:paragraph --><p><strong>Summary of confirmed terms:</strong> no refund or exchange for change of mind or dislike; no exchange on customized orders; refunds apply only if the product arrives destroyed or burnt.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><em>[AWAITING CLIENT TEXT — replace with the client's full written Refund Policy verbatim]</em></p><!-- /wp:paragraph -->
HTML );

sr_seed_page( 'terms-of-service', 'Terms of Service', <<<HTML
<!-- wp:paragraph --><p><em>[AWAITING CLIENT TEXT — replace with the client's full written Terms of Service verbatim]</em></p><!-- /wp:paragraph -->
HTML );

WP_CLI::success( 'Pages seeded.' );
