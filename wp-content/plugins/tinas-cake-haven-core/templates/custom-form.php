<?php
/**
 * Customized birthday cake request form.
 *
 * @var object[] $sizes
 * @var object[] $flavours
 * @var int      $hours
 * @var int      $deposit
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;
$min_date = tch_now()->modify( '+' . $hours . ' hours' )->modify( '+10 minutes' )->format( 'Y-m-d\TH:i' );
$size_id  = absint( TCH_Frontend::old( 'size_id' ) );
$flav_id  = absint( TCH_Frontend::old( 'flavour_id' ) );
$prices   = array();
foreach ( $sizes as $s ) {
	$prices[ (int) $s->id ] = (int) $s->price;
}
?>
<?php echo TCH_Frontend::render_flashes(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<form class="tch-form" method="post" action="" enctype="multipart/form-data" novalidate data-tch-custom-form data-tch-deposit="<?php echo esc_attr( $deposit ); ?>">
	<script type="application/json" data-tch-size-prices><?php echo wp_json_encode( $prices ); ?></script>
	<?php wp_nonce_field( 'tch_place_custom', '_tch_nonce' ); ?>
	<input type="hidden" name="tch_action" value="place_custom">
	<input type="hidden" name="submission_token" value="<?php echo esc_attr( TCH_Frontend::order_submission_token( 'custom', TCH_Frontend::old( 'submission_token' ) ) ); ?>">

	<div class="tch-alert tch-alert--info"><?php echo esc_html( sprintf( /* translators: %d hours */ __( 'Customized birthday cakes must be ordered at least %d hours before you need them.', 'tch' ), $hours ) ); ?></div>

	<fieldset class="tch-fieldset">
		<legend><?php esc_html_e( 'Your cake', 'tch' ); ?></legend>
		<div class="tch-row">
			<p class="tch-field"><label for="tch_size"><?php esc_html_e( 'Cake size', 'tch' ); ?> <span class="req">*</span></label>
				<select id="tch_size" name="size_id" required data-tch-custom-size>
					<option value=""><?php esc_html_e( 'Choose size…', 'tch' ); ?></option>
					<?php foreach ( $sizes as $s ) : ?>
						<option value="<?php echo esc_attr( $s->id ); ?>" <?php selected( $size_id, (int) $s->id ); ?>><?php echo esc_html( $s->name . ' ' . $s->weight_label . ' – ' . tch_money( $s->price ) ); ?></option>
					<?php endforeach; ?>
				</select></p>
			<p class="tch-field"><label for="tch_flavour"><?php esc_html_e( 'Flavour', 'tch' ); ?> <span class="req">*</span></label>
				<select id="tch_flavour" name="flavour_id" required>
					<option value=""><?php esc_html_e( 'Choose flavour…', 'tch' ); ?></option>
					<?php foreach ( $flavours as $f ) : ?>
						<option value="<?php echo esc_attr( $f->id ); ?>" <?php selected( $flav_id, (int) $f->id ); ?>><?php echo esc_html( $f->name ); ?></option>
					<?php endforeach; ?>
				</select></p>
		</div>
		<p class="tch-field"><label for="tch_design"><?php esc_html_e( 'Describe the design you would like', 'tch' ); ?> <span class="req">*</span></label>
			<textarea id="tch_design" name="design_description" rows="4" required minlength="10" maxlength="2000" placeholder="<?php esc_attr_e( 'Colours, theme, decorations, the age or name of the birthday person…', 'tch' ); ?>"><?php echo esc_textarea( TCH_Frontend::old( 'design_description' ) ); ?></textarea></p>
		<p class="tch-field"><label for="tch_inscription"><?php esc_html_e( 'Message on the cake (optional)', 'tch' ); ?></label>
			<input id="tch_inscription" name="inscription" type="text" maxlength="150" placeholder="<?php esc_attr_e( 'e.g. Happy 7th Birthday, Amani!', 'tch' ); ?>" value="<?php echo esc_attr( TCH_Frontend::old( 'inscription' ) ); ?>"></p>
		<p class="tch-field"><label for="tch_ref_photo"><?php esc_html_e( 'Reference photo (optional)', 'tch' ); ?></label>
			<input id="tch_ref_photo" name="reference_photo" type="file" accept="image/jpeg,image/png,image/webp">
			<small class="tch-help"><?php esc_html_e( 'JPG, PNG or WEBP, up to 3 MB. Only our team can see it.', 'tch' ); ?></small></p>
	</fieldset>

	<fieldset class="tch-fieldset">
		<legend><?php esc_html_e( 'When do you need it?', 'tch' ); ?></legend>
		<p class="tch-field"><label for="tch_event"><?php esc_html_e( 'Collection or delivery date and time', 'tch' ); ?> <span class="req">*</span></label>
			<input id="tch_event" name="event_date" type="datetime-local" required min="<?php echo esc_attr( $min_date ); ?>" value="<?php echo esc_attr( TCH_Frontend::old( 'event_date' ) ); ?>"></p>
		<p class="tch-field"><label for="tch_instr"><?php esc_html_e( 'Additional instructions (optional)', 'tch' ); ?></label>
			<textarea id="tch_instr" name="special_instructions" rows="3" maxlength="1000"><?php echo esc_textarea( TCH_Frontend::old( 'special_instructions' ) ); ?></textarea></p>
	</fieldset>

	<?php echo tch_template( 'partial-customer', array( 'deposit' => $deposit ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<div class="tch-summary" data-tch-summary hidden aria-live="polite">
		<div><span><?php esc_html_e( 'Price for the chosen size', 'tch' ); ?></span><strong data-tch-total>UGX 0</strong></div>
		<div><span><?php esc_html_e( 'To pay now', 'tch' ); ?></span><strong data-tch-now>UGX 0</strong></div>
		<small><?php esc_html_e( 'We will contact you to confirm the design details before your order is confirmed.', 'tch' ); ?></small>
	</div>
	<p><button type="submit" class="tch-btn tch-btn--lg"><?php esc_html_e( 'Send my cake request', 'tch' ); ?></button></p>
</form>
