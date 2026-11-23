<?php
/**
 * Contact form.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;
$pre = TCH_Frontend::customer_prefill();
?>
<?php echo TCH_Frontend::render_flashes(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<form class="tch-form" method="post" action="">
	<?php wp_nonce_field( 'tch_contact', '_tch_nonce' ); ?>
	<input type="hidden" name="tch_action" value="contact">
	<div class="tch-row">
		<p class="tch-field"><label for="tch_c_name"><?php esc_html_e( 'Your name', 'tch' ); ?> <span class="req">*</span></label>
			<input id="tch_c_name" name="full_name" type="text" required maxlength="120" autocomplete="name" value="<?php echo esc_attr( $pre['full_name'] ); ?>"></p>
		<p class="tch-field"><label for="tch_c_phone"><?php esc_html_e( 'Phone', 'tch' ); ?></label>
			<input id="tch_c_phone" name="phone" type="tel" maxlength="20" autocomplete="tel" value="<?php echo esc_attr( $pre['phone'] ); ?>"></p>
	</div>
	<p class="tch-field"><label for="tch_c_email"><?php esc_html_e( 'Email', 'tch' ); ?></label>
		<input id="tch_c_email" name="email" type="email" maxlength="120" autocomplete="email" value="<?php echo esc_attr( $pre['email'] ); ?>"></p>
	<p class="tch-field"><label for="tch_c_subject"><?php esc_html_e( 'Subject', 'tch' ); ?> <span class="req">*</span></label>
		<input id="tch_c_subject" name="subject" type="text" required maxlength="150" value="<?php echo esc_attr( TCH_Frontend::old( 'subject' ) ); ?>"></p>
	<p class="tch-field"><label for="tch_c_message"><?php esc_html_e( 'Message', 'tch' ); ?> <span class="req">*</span></label>
		<textarea id="tch_c_message" name="message" rows="5" required maxlength="3000"><?php echo esc_textarea( TCH_Frontend::old( 'message' ) ); ?></textarea></p>
	<p class="tch-hp" aria-hidden="true"><label>Website <input type="text" name="tch_website" tabindex="-1" autocomplete="off"></label></p>
	<p class="tch-note"><?php esc_html_e( 'We use your details only to reply to you. See our Privacy Policy.', 'tch' ); ?></p>
	<p><button type="submit" class="tch-btn"><?php esc_html_e( 'Send message', 'tch' ); ?></button></p>
</form>
