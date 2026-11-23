<?php
/**
 * My Account (logged in).
 *
 * @var WP_User $user
 * @var string  $phone
 * @var int     $orders
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="tch-account">
	<?php echo TCH_Frontend::render_flashes(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<div class="tch-account__summary tch-panel tch-panel--soft">
		<div>
			<p class="tch-eyebrow"><?php esc_html_e( 'Signed in as', 'tch' ); ?></p>
			<h3><?php echo esc_html( $user->display_name ); ?></h3>
			<p class="tch-muted"><?php echo esc_html( $user->user_email ); ?><?php echo $phone ? ' · ' . esc_html( $phone ) : ''; ?></p>
		</div>
		<div class="tch-actions">
			<a class="tch-btn" href="<?php echo esc_url( tch_page_url( 'my-orders' ) ); ?>"><?php echo esc_html( sprintf( /* translators: %d count */ __( 'My orders (%d)', 'tch' ), $orders ) ); ?></a>
			<a class="tch-btn tch-btn--ghost" href="<?php echo esc_url( tch_page_url( 'order' ) ); ?>"><?php esc_html_e( 'Order online', 'tch' ); ?></a>
			<a class="tch-btn tch-btn--ghost" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'tch' ); ?></a>
		</div>
	</div>
	<div class="tch-account__grid">
		<form class="tch-form tch-panel" method="post" action="">
			<h3><?php esc_html_e( 'My details', 'tch' ); ?></h3>
			<?php wp_nonce_field( 'tch_account', '_tch_nonce' ); ?>
			<input type="hidden" name="tch_account_action" value="profile">
			<p class="tch-field"><label for="tch_a_name"><?php esc_html_e( 'Full name', 'tch' ); ?></label>
				<input id="tch_a_name" name="full_name" type="text" required maxlength="120" value="<?php echo esc_attr( $user->display_name ); ?>"></p>
			<p class="tch-field"><label for="tch_a_phone"><?php esc_html_e( 'Phone / WhatsApp', 'tch' ); ?></label>
				<input id="tch_a_phone" name="phone" type="tel" required maxlength="20" value="<?php echo esc_attr( $phone ); ?>"></p>
			<p class="tch-field"><label><?php esc_html_e( 'Email', 'tch' ); ?></label>
				<input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" disabled></p>
			<p><button class="tch-btn" type="submit"><?php esc_html_e( 'Save details', 'tch' ); ?></button></p>
		</form>
		<form class="tch-form tch-panel" method="post" action="" autocomplete="off">
			<h3><?php esc_html_e( 'Change password', 'tch' ); ?></h3>
			<?php wp_nonce_field( 'tch_account', '_tch_nonce' ); ?>
			<input type="hidden" name="tch_account_action" value="password">
			<p class="tch-field"><label for="tch_a_cur"><?php esc_html_e( 'Current password', 'tch' ); ?></label>
				<input id="tch_a_cur" name="current_password" type="password" required autocomplete="current-password"></p>
			<p class="tch-field"><label for="tch_a_new"><?php esc_html_e( 'New password (10+ characters, letters and numbers)', 'tch' ); ?></label>
				<input id="tch_a_new" name="new_password" type="password" required minlength="10" autocomplete="new-password"></p>
			<p class="tch-field"><label for="tch_a_conf"><?php esc_html_e( 'Confirm new password', 'tch' ); ?></label>
				<input id="tch_a_conf" name="confirm_password" type="password" required minlength="10" autocomplete="new-password"></p>
			<p><button class="tch-btn" type="submit"><?php esc_html_e( 'Change password', 'tch' ); ?></button></p>
		</form>
	</div>
</div>
