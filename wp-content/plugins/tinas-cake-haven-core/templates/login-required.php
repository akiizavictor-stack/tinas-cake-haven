<?php
/**
 * Prompt to log in.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="tch-panel tch-panel--center">
	<h3><?php esc_html_e( 'Please log in to see your orders', 'tch' ); ?></h3>
	<p><?php esc_html_e( 'Orders placed while logged in are saved to your account. Ordered as a guest? Use Order Tracking with your order reference and phone number.', 'tch' ); ?></p>
	<p class="tch-actions">
		<a class="tch-btn" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Log in', 'tch' ); ?></a>
		<?php if ( get_option( 'users_can_register' ) ) : ?>
			<a class="tch-btn tch-btn--ghost" href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'Create an account', 'tch' ); ?></a>
		<?php endif; ?>
		<a class="tch-btn tch-btn--ghost" href="<?php echo esc_url( tch_page_url( 'track' ) ); ?>"><?php esc_html_e( 'Track an order', 'tch' ); ?></a>
	</p>
</div>
