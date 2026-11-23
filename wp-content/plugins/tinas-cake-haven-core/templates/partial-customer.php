<?php
/**
 * Customer, fulfilment and payment-plan fields shared by both order forms.
 *
 * @var int $deposit Deposit percent.
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;
$pre    = TCH_Frontend::customer_prefill();
$fulfil = TCH_Frontend::old( 'fulfilment', 'collection' );
$plan   = TCH_Frontend::old( 'payment_plan', 'full' );
?>
<fieldset class="tch-fieldset">
	<legend><?php esc_html_e( 'Your details', 'tch' ); ?></legend>
	<div class="tch-row">
		<p class="tch-field"><label for="tch_full_name"><?php esc_html_e( 'Full name', 'tch' ); ?> <span class="req">*</span></label>
			<input id="tch_full_name" name="full_name" type="text" required maxlength="120" autocomplete="name" value="<?php echo esc_attr( $pre['full_name'] ); ?>"></p>
		<p class="tch-field"><label for="tch_phone"><?php esc_html_e( 'Phone / WhatsApp', 'tch' ); ?> <span class="req">*</span></label>
			<input id="tch_phone" name="phone" type="tel" required maxlength="20" autocomplete="tel" placeholder="07XX XXX XXX" value="<?php echo esc_attr( $pre['phone'] ); ?>"></p>
	</div>
	<p class="tch-field"><label for="tch_email"><?php esc_html_e( 'Email (optional – for order updates)', 'tch' ); ?></label>
		<input id="tch_email" name="email" type="email" maxlength="120" autocomplete="email" value="<?php echo esc_attr( $pre['email'] ); ?>"></p>
</fieldset>

<fieldset class="tch-fieldset">
	<legend><?php esc_html_e( 'Collection or delivery', 'tch' ); ?></legend>
	<div class="tch-choice">
		<label class="tch-choice__item"><input type="radio" name="fulfilment" value="collection" <?php checked( $fulfil, 'collection' ); ?>> <span><strong><?php esc_html_e( 'I will collect', 'tch' ); ?></strong><small><?php echo esc_html( tch_setting( 'location' ) ); ?></small></span></label>
		<label class="tch-choice__item"><input type="radio" name="fulfilment" value="delivery" <?php checked( $fulfil, 'delivery' ); ?>> <span><strong><?php esc_html_e( 'Please deliver', 'tch' ); ?></strong><small><?php esc_html_e( 'Within about 1 km of our premises', 'tch' ); ?></small></span></label>
	</div>
	<p class="tch-field" data-tch-delivery><label for="tch_address"><?php esc_html_e( 'Delivery location / directions', 'tch' ); ?></label>
		<input id="tch_address" name="delivery_address" type="text" maxlength="255" placeholder="<?php esc_attr_e( 'e.g. near Kibimba trading centre, blue gate', 'tch' ); ?>" value="<?php echo esc_attr( TCH_Frontend::old( 'delivery_address' ) ); ?>"></p>
	<p class="tch-note"><?php echo esc_html( tch_setting( 'delivery_note' ) ); ?></p>
</fieldset>

<fieldset class="tch-fieldset">
	<legend><?php esc_html_e( 'Payment (Mobile Money)', 'tch' ); ?></legend>
	<div class="tch-choice">
		<label class="tch-choice__item"><input type="radio" name="payment_plan" value="full" <?php checked( $plan, 'full' ); ?>> <span><strong><?php esc_html_e( 'Pay in full', 'tch' ); ?></strong><small data-tch-show-full><?php esc_html_e( '100% of the total', 'tch' ); ?></small></span></label>
		<label class="tch-choice__item"><input type="radio" name="payment_plan" value="deposit" <?php checked( $plan, 'deposit' ); ?>> <span><strong><?php echo esc_html( sprintf( /* translators: %d percent */ __( 'Pay a %d%% deposit', 'tch' ), $deposit ) ); ?></strong><small><?php esc_html_e( 'Balance on delivery or collection', 'tch' ); ?></small></span></label>
	</div>
	<p class="tch-note"><?php esc_html_e( 'After you submit, you will see the exact amount to send and our payment details. Then enter your Mobile Money transaction ID so we can check it. Your payment is only confirmed after we have verified it on our account.', 'tch' ); ?></p>
</fieldset>
<p class="tch-hp" aria-hidden="true"><label>Website <input type="text" name="tch_website" tabindex="-1" autocomplete="off"></label></p>
