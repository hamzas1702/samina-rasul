<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the #content div and all content after
 *
 * @package storefront
 */

?>

		</div><!-- .col-full -->
	</div><!-- #content -->

	<?php do_action( 'storefront_before_footer' ); ?>

	<footer id="colophon" class="site-footer" role="contentinfo">
		<div class="col-full">

			<?php
			/**
			 * Functions hooked in to storefront_footer action
			 *
			 * @hooked storefront_footer_widgets - 10
			 * @hooked storefront_credit         - 20
			 */
			do_action( 'storefront_footer' );
			?>

		</div><!-- .col-full -->
	</footer><!-- #colophon -->

	<?php do_action( 'storefront_after_footer' ); ?>

</div><!-- #page -->

<?php
wp_footer();

/*
 * Build marker. The deploy pipeline substitutes the commit SHA; when the
 * placeholder is still present (local work, or a deploy that skipped the
 * substitution) nothing is emitted rather than leaking a literal
 * "{{GITHUB_SHA}}" into production markup.
 */
$sr_build = '{{GITHUB_SHA}}';
if ( 0 !== strpos( $sr_build, '{{' ) ) {
	// The value arrives by text substitution into a PHP literal, so it is not
	// trusted: strip everything that is not hex before printing, which keeps a
	// stray quote or "-->" from breaking out of the comment or the literal.
	$sr_build = substr( preg_replace( '/[^a-f0-9]/i', '', $sr_build ), 0, 40 );
	if ( '' !== $sr_build ) {
		printf( "<!-- sr_build: %s -->\n", esc_html( $sr_build ) );
	}
}
?>
</body>
</html>
