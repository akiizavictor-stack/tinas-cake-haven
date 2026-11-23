<?php
/**
 * Title: Tina's Cake Haven header
 * Slug: tinas-cake-haven/header
 * Categories: header
 * Block Types: core/template-part/header
 * Inserter: no
 *
 * @package TinasCakeHavenTheme
 */

if ( function_exists( 'hfe_header_enabled' ) && hfe_header_enabled() ) {
	hfe_render_header();
	return;
}

$phone   = function_exists( 'tch_setting' ) ? tch_setting( 'phone', '+256 792104798' ) : '+256 792104798';
$tel     = 'tel:' . preg_replace( '/[^0-9+]/', '', $phone );
$wa      = function_exists( 'tch_whatsapp_link' ) ? tch_whatsapp_link() : 'https://wa.me/256792104798';
$track   = function_exists( 'tch_page_url' ) ? tch_page_url( 'track' ) : home_url( '/order-tracking/' );
$order   = function_exists( 'tch_page_url' ) ? tch_page_url( 'order' ) : home_url( '/order-online/' );
$account = function_exists( 'tch_page_url' ) ? tch_page_url( 'account' ) : home_url( '/my-account/' );
?>
<!-- wp:group {"className":"tch-topbar","style":{"spacing":{"padding":{"top":"0.45rem","bottom":"0.45rem","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"backgroundColor":"magenta","textColor":"white","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group tch-topbar has-white-color has-magenta-background-color has-text-color has-background" style="padding-top:0.45rem;padding-right:var(--wp--preset--spacing--40);padding-bottom:0.45rem;padding-left:var(--wp--preset--spacing--40)"><!-- wp:paragraph -->
<p>📍 Kibimba, Fort Portal · 📞 <a href="<?php echo esc_url( $tel ); ?>"><?php echo esc_html( $phone ); ?></a> · <a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noreferrer noopener">WhatsApp</a></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="<?php echo esc_url( $track ); ?>">Track my order</a> · <a href="<?php echo esc_url( $account ); ?>">My account</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"header","className":"tch-header","style":{"spacing":{"padding":{"top":"0.7rem","bottom":"0.7rem","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<header class="wp-block-group tch-header" style="padding-top:0.7rem;padding-right:var(--wp--preset--spacing--40);padding-bottom:0.7rem;padding-left:var(--wp--preset--spacing--40)"><!-- wp:group {"className":"tch-brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group tch-brand"><!-- wp:site-logo {"width":64,"shouldSyncIcon":false} /-->

<!-- wp:group {"layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap"},"style":{"spacing":{"blockGap":"0.1rem"}}} -->
<div class="wp-block-group"><!-- wp:site-title {"level":0} /-->

<!-- wp:paragraph {"className":"tch-brand__tag","style":{"typography":{"fontSize":"1.15rem"}},"textColor":"magenta","fontFamily":"script"} -->
<p class="tch-brand__tag has-magenta-color has-text-color has-script-font-family" style="font-size:1.15rem">Sweet Moments, Always!</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"},"style":{"spacing":{"blockGap":"0.6rem"}}} -->
<div class="wp-block-group"><!-- wp:navigation {"className":"tch-nav-main","overlayMenu":"mobile","layout":{"type":"flex","justifyContent":"right"}} /-->

<!-- wp:buttons {"className":"tch-header-cta"} -->
<div class="wp-block-buttons tch-header-cta"><!-- wp:button {"style":{"spacing":{"padding":{"top":"0.6rem","bottom":"0.6rem","left":"1.2rem","right":"1.2rem"}}}} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $order ); ?>" style="padding-top:0.6rem;padding-right:1.2rem;padding-bottom:0.6rem;padding-left:1.2rem">Order Now</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></header>
<!-- /wp:group -->
