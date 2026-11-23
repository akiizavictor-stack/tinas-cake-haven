<?php
/**
 * Title: Tina's Cake Haven footer
 * Slug: tinas-cake-haven/footer
 * Categories: footer
 * Block Types: core/template-part/footer
 * Inserter: no
 *
 * @package TinasCakeHavenTheme
 */

if ( function_exists( 'hfe_footer_enabled' ) && hfe_footer_enabled() ) {
	hfe_render_footer();
	return;
}

$get   = static function ( $key, $default ) {
	return function_exists( 'tch_setting' ) ? tch_setting( $key, $default ) : $default;
};
$phone = $get( 'phone', '+256 792104798' );
$tel   = 'tel:' . preg_replace( '/[^0-9+]/', '', $phone );
$wa    = function_exists( 'tch_whatsapp_link' ) ? tch_whatsapp_link() : 'https://wa.me/256792104798';
$email = $get( 'public_email', '' );
$socials = array_filter( array( 'Facebook' => $get( 'facebook_url', '' ), 'Instagram' => $get( 'instagram_url', '' ), 'TikTok' => $get( 'tiktok_url', '' ) ) );
?>
<!-- wp:group {"tagName":"footer","className":"tch-footer","style":{"spacing":{"padding":{"top":"3.5rem","bottom":"1.5rem","left":"var:preset|spacing|40","right":"var:preset|spacing|40"},"margin":{"top":"3rem"}}},"backgroundColor":"chocolate","textColor":"cream","layout":{"type":"constrained","contentSize":"1200px"}} -->
<footer class="wp-block-group tch-footer has-cream-color has-chocolate-background-color has-text-color has-background" style="margin-top:3rem;padding-top:3.5rem;padding-right:var(--wp--preset--spacing--40);padding-bottom:1.5rem;padding-left:var(--wp--preset--spacing--40)"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"width":"34%"} -->
<div class="wp-block-column" style="flex-basis:34%"><!-- wp:site-logo {"width":96,"shouldSyncIcon":false} /-->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"1.6rem"}},"textColor":"white","fontFamily":"script"} -->
<p class="has-white-color has-text-color has-script-font-family" style="font-size:1.6rem">Sweet Moments, Always!</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Freshly baked birthday cakes, cupcakes and cookies from Kibimba, Fort Portal. Customized birthday cakes with 24 hours' notice.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"1.15rem"}}} -->
<h3 class="wp-block-heading" style="font-size:1.15rem">Shop</h3>
<!-- /wp:heading -->

<!-- wp:navigation {"className":"tch-nav-footer","overlayMenu":"never","layout":{"type":"flex","orientation":"vertical"}} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"1.15rem"}}} -->
<h3 class="wp-block-heading" style="font-size:1.15rem">Information</h3>
<!-- /wp:heading -->

<!-- wp:navigation {"className":"tch-nav-legal","overlayMenu":"never","layout":{"type":"flex","orientation":"vertical"}} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"26%"} -->
<div class="wp-block-column" style="flex-basis:26%"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"1.15rem"}}} -->
<h3 class="wp-block-heading" style="font-size:1.15rem">Contact</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>📍 Kibimba, Fort Portal, Uganda<br>📞 <a href="<?php echo esc_url( $tel ); ?>"><?php echo esc_html( $phone ); ?></a><br>💬 <a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noreferrer noopener">WhatsApp us</a><?php echo $email ? '<br>✉️ <a href="mailto:' . esc_attr( antispambot( $email ) ) . '">' . esc_html( antispambot( $email ) ) . '</a>' : ''; ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Payment: Mobile Money (full or 50% deposit)</p>
<!-- /wp:paragraph -->
<?php if ( $socials ) : ?>
<!-- wp:paragraph -->
<p><?php echo implode( ' · ', array_map( static function ( $label, $url ) { return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noreferrer noopener">' . esc_html( $label ) . '</a>'; }, array_keys( $socials ), $socials ) ); // phpcs:ignore ?></p>
<!-- /wp:paragraph -->
<?php endif; ?></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"className":"tch-footer__bottom","style":{"spacing":{"padding":{"top":"1rem"}},"typography":{"fontSize":"0.85rem"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group tch-footer__bottom" style="padding-top:1rem;font-size:0.85rem"><!-- wp:paragraph -->
<p>© <?php echo esc_html( wp_date( 'Y' ) ); ?> Tina's Cake Haven · Owner: Aluka Christin Hellen · Established 10 February 2026</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="<?php echo esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a> · <a href="<?php echo esc_url( home_url( '/terms-and-conditions/' ) ); ?>">Terms</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></footer>
<!-- /wp:group -->
