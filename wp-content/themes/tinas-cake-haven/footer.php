<?php
/**
 * Classic Footer template for Tina's Cake Haven.
 * Supports Elementor Header & Footer Builder.
 *
 * @package TinasCakeHavenTheme
 */

	if ( function_exists( 'hfe_footer_enabled' ) && hfe_footer_enabled() ) {
		hfe_render_footer();
	} else {
		get_template_part( 'patterns/footer' );
	}
	?>
</div><!-- #page -->
<?php wp_footer(); ?>
</body>
</html>
