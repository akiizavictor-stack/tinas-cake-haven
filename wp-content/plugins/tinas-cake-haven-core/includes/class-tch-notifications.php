<?php
/**
 * Email notifications (sent through wp_mail, i.e. the SMTP account configured in TCH_Mail).
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notifications.
 */
class TCH_Notifications {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'tch_order_placed', array( __CLASS__, 'order_placed' ) );
		add_action( 'tch_payment_submitted', array( __CLASS__, 'payment_submitted' ), 10, 2 );
		add_action( 'tch_payment_decided', array( __CLASS__, 'payment_decided' ), 10, 2 );
		add_action( 'tch_order_status_changed', array( __CLASS__, 'status_changed' ), 10, 4 );
	}

	/**
	 * Where staff alerts go.
	 *
	 * @return string
	 */
	private static function owner_email() {
		$e = tch_setting( 'notify_email' );
		return is_email( $e ) ? $e : get_option( 'admin_email' );
	}

	/**
	 * Sends a plain-text email.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $body    Body.
	 */
	private static function send( $to, $subject, $body ) {
		if ( ! is_email( $to ) ) {
			return;
		}
		$name = tch_setting( 'business_name', "Tina's Cake Haven" );
		$body .= "\n\n— " . $name . "\n" . tch_setting( 'location' ) . "\n" . __( 'Phone/WhatsApp:', 'tch' ) . ' ' . tch_setting( 'phone' ) . "\n" . home_url( '/' );
		wp_mail( $to, '[' . $name . '] ' . $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	/**
	 * Order summary lines.
	 *
	 * @param object $order Order.
	 * @return string
	 */
	private static function summary( $order ) {
		$lines = array();
		foreach ( TCH_Orders::items( (int) $order->id ) as $it ) {
			$desc    = $it->quantity . ' x ' . $it->product_name;
			$extra   = array_filter( array( $it->size_label, $it->flavour_label ) );
			$lines[] = '- ' . $desc . ( $extra ? ' (' . implode( ', ', $extra ) . ')' : '' ) . ': ' . tch_money( $it->line_total );
		}
		$lines[] = __( 'Total:', 'tch' ) . ' ' . tch_money( $order->total_amount );
		return implode( "\n", $lines );
	}

	/**
	 * New order.
	 *
	 * @param int $order_id Order.
	 */
	public static function order_placed( $order_id ) {
		$o = TCH_Orders::get( $order_id );
		if ( ! $o ) {
			return;
		}
		$track = tch_page_url( 'track' );
		if ( $o->customer_email ) {
			/* translators: %s ref */
			$body  = sprintf( __( "Hello %1\$s,\n\nThank you for your order. Your order reference is %2\$s.\n\n%3\$s\n\nAmount to pay now: %4\$s (%5\$s)\n\nHow to pay: %6\$s\n\nAfter paying, enter your Mobile Money transaction ID on the Order Tracking page so we can check it: %7\$s\nYour payment will show as 'Pending verification' until we have confirmed it on our Mobile Money account.", 'tch' ), $o->customer_name, $o->order_ref, self::summary( $o ), tch_money( $o->amount_expected ), 'deposit' === $o->payment_plan ? __( '50% deposit', 'tch' ) : __( 'full payment', 'tch' ), TCH_Frontend::momo_text(), $track );
			self::send( $o->customer_email, sprintf( __( 'Order %s received', 'tch' ), $o->order_ref ), $body );
		}
		$admin = admin_url( 'admin.php?page=tch-orders&view=' . (int) $o->id );
		/* translators: order details */
		$body = sprintf( __( "New %1\$s order %2\$s from %3\$s (%4\$s).\n\n%5\$s\n\nNeeded by: %6\$s\nFulfilment: %7\$s\n\nOpen the order: %8\$s", 'tch' ), 'custom' === $o->order_type ? __( 'CUSTOMIZED CAKE', 'tch' ) : __( 'online', 'tch' ), $o->order_ref, $o->customer_name, $o->customer_phone, self::summary( $o ), tch_date( $o->needed_by ), 'delivery' === $o->fulfilment ? __( 'Delivery to', 'tch' ) . ' ' . $o->delivery_address : __( 'Collection', 'tch' ), $admin );
		self::send( self::owner_email(), sprintf( __( 'New order %s', 'tch' ), $o->order_ref ), $body );
	}

	/**
	 * Customer said they paid: ask staff to check the real account.
	 *
	 * @param int $payment_id Payment.
	 * @param int $order_id   Order.
	 */
	public static function payment_submitted( $payment_id, $order_id ) {
		$p = TCH_Payments::get( $payment_id );
		$o = TCH_Orders::get( $order_id );
		if ( ! $p || ! $o ) {
			return;
		}
		/* translators: details */
		$body = sprintf( __( "The customer on order %1\$s says they paid %2\$s by Mobile Money.\nTransaction ID: %3\$s\nPaid from: %4\$s\n\nPlease check your Mobile Money account BEFORE verifying. Nothing has been marked as paid yet.\n\nReview it here: %5\$s", 'tch' ), $o->order_ref, tch_money( $p->amount ), $p->transaction_ref, $p->payer_phone, admin_url( 'admin.php?page=tch-orders&view=' . (int) $o->id ) );
		self::send( self::owner_email(), sprintf( __( 'Payment to verify – %s', 'tch' ), $o->order_ref ), $body );
	}

	/**
	 * Staff verified or rejected a payment.
	 *
	 * @param int    $payment_id Payment.
	 * @param string $decision   Decision.
	 */
	public static function payment_decided( $payment_id, $decision ) {
		$p = TCH_Payments::get( $payment_id );
		$o = $p ? TCH_Orders::get( (int) $p->order_id ) : null;
		if ( ! $o || ! $o->customer_email ) {
			return;
		}
		if ( 'verified' === $decision ) {
			/* translators: details */
			$body = sprintf( __( "Hello %1\$s,\n\nWe have confirmed your payment of %2\$s for order %3\$s.\nTotal paid so far: %4\$s\nBalance: %5\$s", 'tch' ), $o->customer_name, tch_money( $p->amount ), $o->order_ref, tch_money( $o->amount_paid ), tch_money( TCH_Payments::outstanding( $o ) ) );
			self::send( $o->customer_email, sprintf( __( 'Payment confirmed – %s', 'tch' ), $o->order_ref ), $body );
		} else {
			/* translators: details */
			$body = sprintf( __( "Hello %1\$s,\n\nWe could not confirm the payment you submitted for order %2\$s (transaction ID %3\$s).\nReason: %4\$s\n\nPlease contact us on %5\$s.", 'tch' ), $o->customer_name, $o->order_ref, $p->transaction_ref, $p->admin_note, tch_setting( 'phone' ) );
			self::send( $o->customer_email, sprintf( __( 'Payment not confirmed – %s', 'tch' ), $o->order_ref ), $body );
		}
	}

	/**
	 * Meaningful order status change.
	 *
	 * @param int    $order_id Order.
	 * @param string $old      Old.
	 * @param string $new      New.
	 * @param bool   $notify   Notify.
	 */
	public static function status_changed( $order_id, $old, $new, $notify ) {
		if ( ! $notify || in_array( $new, array( 'pending', 'awaiting_verification' ), true ) ) {
			return;
		}
		$o = TCH_Orders::get( $order_id );
		if ( ! $o || ! $o->customer_email ) {
			return;
		}
		$labels = tch_order_statuses();
		/* translators: details */
		$body = sprintf( __( "Hello %1\$s,\n\nYour order %2\$s is now: %3\$s.\n\nTrack your order: %4\$s", 'tch' ), $o->customer_name, $o->order_ref, $labels[ $new ], tch_page_url( 'track' ) );
		self::send( $o->customer_email, sprintf( __( 'Order %1$s: %2$s', 'tch' ), $o->order_ref, $labels[ $new ] ), $body );
	}
}
