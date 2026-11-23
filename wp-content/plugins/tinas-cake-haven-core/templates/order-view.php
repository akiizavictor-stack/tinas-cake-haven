<?php
/**
 * Customer view of one order (status, payment, items, payment submission).
 *
 * @var object   $order
 * @var object[] $items
 * @var object   $custom
 * @var object[] $history
 * @var object[] $payments
 * @var string   $back
 * @var string   $token
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;
$ostatus     = tch_order_statuses();
$pstatus     = tch_order_payment_statuses();
$rstatus     = tch_payment_record_statuses();
$outstanding = TCH_Payments::outstanding( $order );
$due_now     = TCH_Payments::amount_due_now( $order );
$has_pending = TCH_Payments::has_pending( (int) $order->id );
$can_pay     = 'cancelled' !== $order->order_status && $outstanding > 0 && ! $has_pending;
$fixed_initial_amount = 0 === (int) $order->amount_paid;
$initial_amount       = 'deposit' === $order->payment_plan ? TCH_Payments::deposit_amount( (int) $order->total_amount ) : $outstanding;
$payment_input_amount = $fixed_initial_amount ? $initial_amount : (int) TCH_Frontend::old( 'amount', (string) $due_now );

$steps = array( 'pending', 'awaiting_verification', 'confirmed', 'preparing', 'delivery' === $order->fulfilment ? 'out_for_delivery' : 'ready', 'completed' );
$pos   = array_search( $order->order_status, $steps, true );
if ( 'ready' === $order->order_status && 'delivery' === $order->fulfilment ) {
	$pos = 4;
}
?>
<div class="tch-order">
	<?php echo TCH_Frontend::render_flashes(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<p><a class="tch-link" href="<?php echo esc_url( $back ); ?>">← <?php esc_html_e( 'Back', 'tch' ); ?></a></p>

	<header class="tch-order__head">
		<div>
			<p class="tch-eyebrow"><?php echo 'custom' === $order->order_type ? esc_html__( 'Customized birthday cake', 'tch' ) : esc_html__( 'Order', 'tch' ); ?></p>
			<h2 class="tch-order__ref"><?php echo esc_html( $order->order_ref ); ?></h2>
			<p class="tch-muted"><?php echo esc_html( sprintf( /* translators: %s date */ __( 'Placed %s', 'tch' ), tch_date( $order->created_at ) ) ); ?></p>
		</div>
		<div class="tch-order__badges">
			<?php echo tch_badge( $order->order_status, $ostatus ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo tch_badge( $order->payment_status, $pstatus ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
	</header>

	<?php if ( 'cancelled' !== $order->order_status ) : ?>
		<ol class="tch-steps" aria-label="<?php esc_attr_e( 'Order progress', 'tch' ); ?>">
			<?php foreach ( $steps as $i => $st ) : ?>
				<li class="<?php echo false !== $pos && $i < $pos ? 'is-done' : ( $i === $pos ? 'is-current' : '' ); ?>"><span><?php echo esc_html( $ostatus[ $st ] ); ?></span></li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>

	<div class="tch-order__grid">
		<section class="tch-panel">
			<h3><?php esc_html_e( 'Items', 'tch' ); ?></h3>
			<table class="tch-table">
				<thead><tr><th><?php esc_html_e( 'Item', 'tch' ); ?></th><th><?php esc_html_e( 'Qty', 'tch' ); ?></th><th class="num"><?php esc_html_e( 'Amount', 'tch' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $items as $it ) : ?>
					<tr>
						<td><?php echo esc_html( $it->product_name ); ?><?php if ( $it->size_label || $it->flavour_label ) : ?><br><small><?php echo esc_html( implode( ' · ', array_filter( array( $it->size_label, $it->flavour_label ) ) ) ); ?></small><?php endif; ?></td>
						<td><?php echo (int) $it->quantity; ?></td>
						<td class="num"><?php echo esc_html( tch_money( $it->line_total ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr><th colspan="2"><?php esc_html_e( 'Total', 'tch' ); ?></th><td class="num"><strong><?php echo esc_html( tch_money( $order->total_amount ) ); ?></strong></td></tr>
					<tr><th colspan="2"><?php esc_html_e( 'Verified payments', 'tch' ); ?></th><td class="num"><?php echo esc_html( tch_money( $order->amount_paid ) ); ?></td></tr>
					<tr><th colspan="2"><?php esc_html_e( 'Outstanding balance', 'tch' ); ?></th><td class="num"><strong><?php echo esc_html( tch_money( $outstanding ) ); ?></strong></td></tr>
				</tfoot>
			</table>
			<dl class="tch-dl">
				<dt><?php esc_html_e( 'Needed by', 'tch' ); ?></dt><dd><?php echo esc_html( tch_date( $order->needed_by ) ); ?></dd>
				<dt><?php esc_html_e( 'Fulfilment', 'tch' ); ?></dt><dd><?php echo 'delivery' === $order->fulfilment ? esc_html__( 'Delivery to:', 'tch' ) . ' ' . esc_html( $order->delivery_address ) : esc_html__( 'Collection from our premises', 'tch' ); ?></dd>
				<dt><?php esc_html_e( 'Payment plan', 'tch' ); ?></dt><dd><?php echo 'deposit' === $order->payment_plan ? esc_html( sprintf( /* translators: %s amount */ __( 'Deposit of %s, balance on delivery/collection', 'tch' ), tch_money( $order->amount_expected ) ) ) : esc_html__( 'Full payment', 'tch' ); ?></dd>
			</dl>
			<?php if ( $custom ) : ?>
				<h3><?php esc_html_e( 'Your design request', 'tch' ); ?></h3>
				<p><?php echo nl2br( esc_html( $custom->design_description ) ); ?></p>
				<?php if ( $custom->inscription ) : ?>
					<p><strong><?php esc_html_e( 'Message on cake:', 'tch' ); ?></strong> <?php echo esc_html( $custom->inscription ); ?></p>
				<?php endif; ?>
				<?php if ( $custom->reference_file ) : ?>
					<p class="tch-muted"><?php esc_html_e( 'Reference photo received.', 'tch' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</section>

		<section class="tch-panel">
			<h3><?php esc_html_e( 'Payment', 'tch' ); ?></h3>
			<?php if ( 'fully_paid' === $order->payment_status ) : ?>
				<div class="tch-alert tch-alert--success"><?php esc_html_e( 'Fully paid – thank you!', 'tch' ); ?></div>
			<?php elseif ( $has_pending ) : ?>
				<div class="tch-alert tch-alert--info"><?php esc_html_e( 'We have received your payment details and are checking them on our Mobile Money account. This page will update once your payment is verified.', 'tch' ); ?></div>
			<?php elseif ( 'cancelled' === $order->order_status ) : ?>
				<div class="tch-alert tch-alert--error"><?php esc_html_e( 'This order was cancelled. Please contact us if you have questions.', 'tch' ); ?></div>
			<?php endif; ?>

			<?php if ( $can_pay ) : ?>
				<div class="tch-paybox">
					<p class="tch-paybox__label"><?php esc_html_e( 'Amount to pay now', 'tch' ); ?></p>
					<p class="tch-paybox__amount"><?php echo esc_html( tch_money( $due_now ) ); ?></p>
					<p class="tch-paybox__how"><strong><?php esc_html_e( 'How to pay:', 'tch' ); ?></strong> <?php echo nl2br( esc_html( TCH_Frontend::momo_text() ) ); ?></p>
					<p class="tch-note"><?php esc_html_e( 'Use your order reference as the payment reason. Never share your Mobile Money PIN with anyone – we will never ask for it.', 'tch' ); ?></p>
				</div>
				<form class="tch-form tch-form--compact" method="post" action="" enctype="multipart/form-data">
					<?php wp_nonce_field( 'tch_submit_payment', '_tch_nonce' ); ?>
					<input type="hidden" name="tch_action" value="submit_payment">
					<input type="hidden" name="ref" value="<?php echo esc_attr( $order->order_ref ); ?>">
					<input type="hidden" name="k" value="<?php echo esc_attr( $token ); ?>">
					<h4><?php esc_html_e( 'I have paid – send my payment details', 'tch' ); ?></h4>
					<div class="tch-row">
						<p class="tch-field"><label for="tch_txn"><?php esc_html_e( 'Transaction ID', 'tch' ); ?> <span class="req">*</span></label>
							<input id="tch_txn" name="transaction_ref" type="text" required maxlength="40" autocomplete="off" value="<?php echo esc_attr( TCH_Frontend::old( 'transaction_ref' ) ); ?>"></p>
						<p class="tch-field"><label for="tch_amount"><?php esc_html_e( 'Amount sent (UGX)', 'tch' ); ?> <span class="req">*</span></label>
							<input id="tch_amount" name="amount" type="number" required min="<?php echo esc_attr( $fixed_initial_amount ? $initial_amount : TCH_Payments::MIN_PAYMENT ); ?>" max="<?php echo esc_attr( $fixed_initial_amount ? $initial_amount : $outstanding ); ?>" step="1" value="<?php echo esc_attr( $payment_input_amount ); ?>" <?php wp_readonly( $fixed_initial_amount ); ?>></p>
					</div>
					<p class="tch-field"><label for="tch_payer"><?php esc_html_e( 'Phone number you paid from', 'tch' ); ?> <span class="req">*</span></label>
						<input id="tch_payer" name="payer_phone" type="tel" required maxlength="20" value="<?php echo esc_attr( TCH_Frontend::old( 'payer_phone', $order->customer_phone ) ); ?>"></p>
					<p class="tch-field"><label for="tch_shot"><?php esc_html_e( 'Screenshot of the confirmation (optional)', 'tch' ); ?></label>
						<input id="tch_shot" name="payment_screenshot" type="file" accept="image/jpeg,image/png,image/webp">
						<small class="tch-help"><?php esc_html_e( 'Supporting information only – we always check the transaction on our Mobile Money account.', 'tch' ); ?></small></p>
					<p class="tch-hp" aria-hidden="true"><label>Website <input type="text" name="tch_website" tabindex="-1" autocomplete="off"></label></p>
					<p><button class="tch-btn" type="submit"><?php esc_html_e( 'Submit for verification', 'tch' ); ?></button></p>
				</form>
			<?php endif; ?>

			<?php if ( $payments ) : ?>
				<h4><?php esc_html_e( 'Payment history', 'tch' ); ?></h4>
				<ul class="tch-list">
					<?php foreach ( $payments as $p ) : ?>
						<li>
							<strong><?php echo esc_html( tch_money( $p->amount ) ); ?></strong>
							<?php echo tch_badge( $p->status, $rstatus ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<br><small><?php echo esc_html( ( $p->transaction_ref ? $p->transaction_ref . ' · ' : '' ) . tch_date( $p->submitted_at ) ); ?></small>
							<?php if ( 'rejected' === $p->status && $p->admin_note ) : ?>
								<br><small class="tch-error-text"><?php echo esc_html( $p->admin_note ); ?></small>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<h4><?php esc_html_e( 'Order updates', 'tch' ); ?></h4>
			<ul class="tch-timeline">
				<?php foreach ( array_reverse( $history ) as $h ) : ?>
					<li><span class="tch-timeline__date"><?php echo esc_html( tch_date( $h->created_at ) ); ?></span> <?php echo esc_html( $ostatus[ $h->new_status ] ?? $h->new_status ); ?><?php if ( $h->note && ( null === $h->changed_by || $h->old_status !== $h->new_status ) ) : ?><small> – <?php echo esc_html( $h->note ); ?></small><?php endif; ?></li>
				<?php endforeach; ?>
			</ul>
			<p><a class="tch-btn tch-btn--whatsapp tch-btn--sm" href="<?php echo esc_url( tch_whatsapp_link( 'Hello, I have a question about my order ' . $order->order_ref ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Questions? WhatsApp us', 'tch' ); ?></a></p>
		</section>
	</div>
</div>
