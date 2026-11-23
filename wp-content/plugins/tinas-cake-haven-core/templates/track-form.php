<?php
/**
 * Guest order tracking form (reference + phone are both required).
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;
?>
<?php echo TCH_Frontend::render_flashes(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<form class="tch-form tch-form--narrow tch-panel" method="post" action="">
	<?php wp_nonce_field( 'tch_track', '_tch_nonce' ); ?>
	<input type="hidden" name="tch_action" value="track">
	<p class="tch-field"><label for="tch_track_ref"><?php esc_html_e( 'Order reference', 'tch' ); ?></label>
		<input id="tch_track_ref" name="ref" type="text" required maxlength="20" placeholder="TCH-260214-AB3CD" autocapitalize="characters" value="<?php echo esc_attr( TCH_Frontend::old( 'ref' ) ); ?>"></p>
	<p class="tch-field"><label for="tch_track_phone"><?php esc_html_e( 'Phone number used for the order', 'tch' ); ?></label>
		<input id="tch_track_phone" name="phone" type="tel" required maxlength="20" autocomplete="tel" value="<?php echo esc_attr( TCH_Frontend::old( 'phone' ) ); ?>"></p>
	<p class="tch-hp" aria-hidden="true"><label>Website <input type="text" name="tch_website" tabindex="-1" autocomplete="off"></label></p>
	<p><button type="submit" class="tch-btn"><?php esc_html_e( 'Track my order', 'tch' ); ?></button></p>
	<p class="tch-note"><?php esc_html_e( 'Have an account?', 'tch' ); ?> <a href="<?php echo esc_url( tch_page_url( 'my-orders' ) ); ?>"><?php esc_html_e( 'See all your orders in My Orders.', 'tch' ); ?></a></p>
</form>
