<?php
/**
 * Title: Products and home buttons
 * Slug: tinas-cake-haven/home-buttons
 * Inserter: no
 *
 * @package TinasCakeHavenTheme
 */

$products = function_exists( 'tch_page_url' ) ? tch_page_url( 'products' ) : home_url( '/our-products/' );
?>
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $products ); ?>">See all our cakes</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
