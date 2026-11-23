<?php
/**
 * My Account for visitors who are not logged in.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="tch-account-auth">
	<section class="tch-panel">
		<h3><?php esc_html_e( 'Log in', 'tch' ); ?></h3>
		<?php
		wp_login_form(
			array(
				'redirect'       => tch_page_url( 'account' ),
				'label_username' => __( 'Email or username', 'tch' ),
				'remember'       => true,
				'form_id'        => 'tch-loginform',
			)
		);
		?>
		<p><a class="tch-link" href="<?php echo esc_url( wp_lostpassword_url( tch_page_url( 'account' ) ) ); ?>"><?php esc_html_e( 'Forgot your password?', 'tch' ); ?></a></p>
	</section>
	<section class="tch-panel tch-panel--soft">
		<h3><?php esc_html_e( 'New customer?', 'tch' ); ?></h3>
		<p><?php esc_html_e( 'Create a free account to see all your orders, payment status and outstanding balances in one place.', 'tch' ); ?></p>
		<?php if ( get_option( 'users_can_register' ) ) : ?>
			<p><a class="tch-btn" href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'Create an account', 'tch' ); ?></a></p>
		<?php endif; ?>
		<p class="tch-note"><?php esc_html_e( 'You can also order as a guest and track your order with its reference number and your phone number.', 'tch' ); ?></p>
		<p><a class="tch-btn tch-btn--ghost" href="<?php echo esc_url( tch_page_url( 'track' ) ); ?>"><?php esc_html_e( 'Track an order', 'tch' ); ?></a></p>
	</section>
</div>
