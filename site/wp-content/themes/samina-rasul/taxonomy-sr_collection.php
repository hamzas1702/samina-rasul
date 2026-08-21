<?php
/**
 * Collection landing page.
 *
 * Collections (Dhanak, Ujala) had no template of their own, so /collection/ujala/
 * fell all the way through to Storefront's generic archive - a sidebar, a plain
 * H1 and none of the masthead the category pages carry. Two links in the main
 * navigation therefore led somewhere that did not look like this site.
 *
 * taxonomy-product_cat.php is written for any product taxonomy - the only
 * per-term decision in it is the tone, which falls back to the warm treatment
 * for anything it does not recognise - so it renders this page too rather than
 * being copied and left to drift.
 *
 * @package samina-rasul
 */

defined( 'ABSPATH' ) || exit;

require locate_template( 'taxonomy-product_cat.php' );
