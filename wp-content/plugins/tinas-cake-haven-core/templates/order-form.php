<?php
/**
 * Order Online form. One item row is rendered server-side (works without JavaScript);
 * order.js clones it to add more rows and shows a live ESTIMATE. The server recalculates
 * every price from the database when the form is submitted.
 *
 * @var array[]  $products
 * @var object[] $sizes
 * @var object[] $flavours
 * @var array    $data
 * @var int      $pre
 * @var int      $deposit
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;
$old_items = isset( TCH_Frontend::$old['items'] ) && is_array( TCH_Frontend::$old['items'] ) ? array_values( TCH_Frontend::$old['items'] ) : array( array( 'product_id' => $pre, 'quantity' => '' ) );
$min_date  = tch_now()->modify( '+2 hours' )->format( 'Y-m-d\TH:i' );

$row = static function ( $i, $it ) use ( $products, $sizes, $flavours ) {
	$pid = absint( $it['product_id'] ?? 0 );
	?>
	<div class="tch-item" data-tch-item>
		<div class="tch-item__grid">
			<p class="tch-field tch-field--product"><label><?php esc_html_e( 'Product', 'tch' ); ?></label>
				<select name="items[<?php echo esc_attr( (string) $i ); ?>][product_id]" data-tch-product required>
					<option value=""><?php esc_html_e( 'Choose…', 'tch' ); ?></option>
					<?php foreach ( $products as $p ) : ?>
						<option value="<?php echo esc_attr( $p['id'] ); ?>" <?php selected( $pid, $p['id'] ); ?>><?php echo esc_html( $p['name'] . ' (' . TCH_Catalogue::price_summary( $p ) . ')' ); ?></option>
					<?php endforeach; ?>
				</select></p>
			<p class="tch-field" data-tch-size-wrap><label><?php esc_html_e( 'Size', 'tch' ); ?></label>
				<select name="items[<?php echo esc_attr( (string) $i ); ?>][size_id]" data-tch-size>
					<option value=""><?php esc_html_e( 'Choose size…', 'tch' ); ?></option>
					<?php foreach ( $sizes as $s ) : ?>
						<option value="<?php echo esc_attr( $s->id ); ?>" <?php selected( absint( $it['size_id'] ?? 0 ), (int) $s->id ); ?>><?php echo esc_html( $s->name . ' ' . $s->weight_label . ' – ' . tch_money( $s->price ) ); ?></option>
					<?php endforeach; ?>
				</select></p>
			<p class="tch-field" data-tch-flavour-wrap><label><?php esc_html_e( 'Flavour', 'tch' ); ?></label>
				<select name="items[<?php echo esc_attr( (string) $i ); ?>][flavour_id]" data-tch-flavour>
					<option value=""><?php esc_html_e( 'Choose flavour…', 'tch' ); ?></option>
					<?php foreach ( $flavours as $f ) : ?>
						<option value="<?php echo esc_attr( $f->id ); ?>" <?php selected( absint( $it['flavour_id'] ?? 0 ), (int) $f->id ); ?>><?php echo esc_html( $f->name ); ?></option>
					<?php endforeach; ?>
				</select></p>
			<p class="tch-field tch-field--qty"><label><?php esc_html_e( 'Quantity', 'tch' ); ?> <small data-tch-qty-hint></small></label>
				<input type="number" name="items[<?php echo esc_attr( (string) $i ); ?>][quantity]" data-tch-qty min="1" max="200" step="1" inputmode="numeric" required value="<?php echo esc_attr( $it['quantity'] ?? '' ); ?>"></p>
		</div>
		<div class="tch-item__foot"><span class="tch-item__line" data-tch-line></span>
			<button type="button" class="tch-link-btn" data-tch-remove><?php esc_html_e( 'Remove', 'tch' ); ?></button></div>
	</div>
	<?php
};
?>
<?php echo TCH_Frontend::render_flashes(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
<form class="tch-form" method="post" action="" novalidate data-tch-order-form data-tch-deposit="<?php echo esc_attr( $deposit ); ?>">
	<script type="application/json" data-tch-products><?php echo wp_json_encode( $data ); ?></script>
	<?php wp_nonce_field( 'tch_place_order', '_tch_nonce' ); ?>
	<input type="hidden" name="tch_action" value="place_order">
	<input type="hidden" name="submission_token" value="<?php echo esc_attr( TCH_Frontend::order_submission_token( 'standard', TCH_Frontend::old( 'submission_token' ) ) ); ?>">

	<fieldset class="tch-fieldset">
		<legend><?php esc_html_e( 'What would you like?', 'tch' ); ?></legend>
		<div data-tch-items>
			<?php
			foreach ( $old_items as $i => $it ) {
				$row( $i, is_array( $it ) ? $it : array() );
			}
			?>
		</div>
		<template data-tch-item-template><?php $row( '__i__', array() ); ?></template>
		<button type="button" class="tch-btn tch-btn--ghost tch-btn--sm" data-tch-add hidden>+ <?php esc_html_e( 'Add another item', 'tch' ); ?></button>
		<p class="tch-note"><?php echo esc_html( sprintf( /* translators: %s link text */ __( 'Want a special design? Use the Customized Birthday Cakes form (at least %d hours\' notice).', 'tch' ), (int) tch_setting( 'custom_notice_hours', '24' ) ) ); ?> <a href="<?php echo esc_url( tch_page_url( 'custom' ) ); ?>"><?php esc_html_e( 'Open the custom cake form →', 'tch' ); ?></a></p>
	</fieldset>

	<fieldset class="tch-fieldset">
		<legend><?php esc_html_e( 'When do you need it?', 'tch' ); ?></legend>
		<p class="tch-field"><label for="tch_needed_by"><?php esc_html_e( 'Date and time', 'tch' ); ?> <span class="req">*</span></label>
			<input id="tch_needed_by" name="needed_by" type="datetime-local" required min="<?php echo esc_attr( $min_date ); ?>" value="<?php echo esc_attr( TCH_Frontend::old( 'needed_by' ) ); ?>"></p>
	</fieldset>

	<?php echo tch_template( 'partial-customer', array( 'deposit' => $deposit ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<p class="tch-field"><label for="tch_notes"><?php esc_html_e( 'Notes for Tina (optional)', 'tch' ); ?></label>
		<textarea id="tch_notes" name="customer_notes" rows="3" maxlength="1000"><?php echo esc_textarea( TCH_Frontend::old( 'customer_notes' ) ); ?></textarea></p>

	<div class="tch-summary" data-tch-summary hidden aria-live="polite">
		<div><span><?php esc_html_e( 'Estimated total', 'tch' ); ?></span><strong data-tch-total>UGX 0</strong></div>
		<div><span><?php esc_html_e( 'To pay now', 'tch' ); ?></span><strong data-tch-now>UGX 0</strong></div>
		<small><?php esc_html_e( 'Final prices are confirmed by our system when you submit.', 'tch' ); ?></small>
	</div>
	<p><button type="submit" class="tch-btn tch-btn--lg"><?php esc_html_e( 'Place my order', 'tch' ); ?></button></p>
	<?php if ( ! is_user_logged_in() ) : ?>
		<p class="tch-note"><?php esc_html_e( 'Tip:', 'tch' ); ?> <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'log in', 'tch' ); ?></a> <?php esc_html_e( 'or', 'tch' ); ?> <a href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'create an account', 'tch' ); ?></a> <?php esc_html_e( 'to keep all your orders in one place. Guests can still track orders with the reference and phone number.', 'tch' ); ?></p>
	<?php endif; ?>
</form>
