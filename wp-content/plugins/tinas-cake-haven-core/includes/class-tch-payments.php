<?php
/**
 * Manual Mobile Money payment records and verification.
 *
 * IMPORTANT BUSINESS RULE: nothing in this class talks to a payment provider and
 * nothing marks money as received automatically. A customer submission is only a
 * claim ("pending_verification"). Only a staff member with the tch_verify_payments
 * capability can mark it verified or rejected after checking the real Mobile Money
 * account. Every decision is logged with the user and time.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payments.
 */
class TCH_Payments {

	const DEPOSIT_PERCENT = 50;
	const MIN_PAYMENT     = 500;

	/**
	 * Payments for an order.
	 *
	 * @param int $order_id Order.
	 * @return array<int,object>
	 */
	public static function for_order( $order_id ) {
		global $wpdb;
		$t = tch_table( 'payments' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE order_id = %d ORDER BY submitted_at, id", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * One payment.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$t = tch_table( 'payments' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Verification history for all payments of an order (JOIN payment_log -> payments).
	 *
	 * @param int $order_id Order.
	 * @return array<int,object>
	 */
	public static function log_for_order( $order_id ) {
		global $wpdb;
		$l = tch_table( 'payment_log' );
		$p = tch_table( 'payments' );
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT l.*, p.amount, p.transaction_ref FROM $l l INNER JOIN $p p ON p.id = l.payment_id WHERE p.order_id = %d ORDER BY l.created_at, l.id",
				$order_id
			)
		);
	}

	/**
	 * Payments waiting for a decision, with order and customer details (JOIN).
	 *
	 * @return array<int,object>
	 */
	public static function pending_queue() {
		global $wpdb;
		$p = tch_table( 'payments' );
		$o = tch_table( 'orders' );
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT p.*, o.order_ref, o.customer_name, o.customer_phone, o.total_amount, o.amount_paid, o.amount_expected
				FROM $p p INNER JOIN $o o ON o.id = p.order_id
				WHERE p.status = %s ORDER BY p.submitted_at ASC",
				'pending_verification'
			)
		);
	}

	/**
	 * Balance still owed.
	 *
	 * @param object $order Order row.
	 * @return int
	 */
	public static function outstanding( $order ) {
		return max( 0, (int) $order->total_amount - (int) $order->amount_paid );
	}

	/**
	 * Calculates 50% in integer UGX, rounding an odd shilling upward.
	 *
	 * @param int $total Whole-shilling order total.
	 * @return int
	 */
	public static function deposit_amount( $total ) {
		$total = max( 0, (int) $total );
		return intdiv( $total, 2 ) + ( $total % 2 );
	}

	/**
	 * Canonical transaction reference used by validation and the unique index.
	 *
	 * @param string $reference Raw transaction reference.
	 * @return string
	 */
	public static function normalize_transaction_reference( $reference ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9.\-]/', '', sanitize_text_field( $reference ) ) );
	}

	/**
	 * Begins a write transaction and reports failure instead of continuing.
	 *
	 * @return true|WP_Error
	 */
	private static function begin_transaction() {
		global $wpdb;
		$wpdb->last_error = '';
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			self::log_database_failure( 'begin_transaction' );
			return new WP_Error( 'database', __( 'We could not start a safe payment update. Please try again.', 'tch' ) );
		}
		return true;
	}

	/**
	 * Commits a transaction or reports an uncertain save as a failure.
	 *
	 * @return true|WP_Error
	 */
	private static function commit_transaction() {
		global $wpdb;
		$wpdb->last_error = '';
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			self::log_database_failure( 'commit_transaction' );
			if ( false === $wpdb->query( 'ROLLBACK' ) ) {
				self::log_database_failure( 'rollback_after_commit_failure' );
			}
			return new WP_Error( 'database', __( 'The payment update could not be confirmed as saved. Please check the order before retrying.', 'tch' ) );
		}
		return true;
	}

	/**
	 * Rolls back a failed write and logs only a fixed operation label.
	 *
	 * @param string $operation Failed operation.
	 */
	private static function rollback_transaction( $operation ) {
		global $wpdb;
		if ( false === $wpdb->query( 'ROLLBACK' ) ) {
			self::log_database_failure( 'rollback_transaction' );
		}
		self::log_database_failure( $operation );
	}

	/**
	 * Rolls back a business-rule rejection without treating it as a database fault.
	 */
	private static function rollback_validation() {
		global $wpdb;
		if ( false === $wpdb->query( 'ROLLBACK' ) ) {
			self::log_database_failure( 'rollback_validation' );
		}
	}

	/**
	 * Logs a fixed operation name without customer data, SQL, or credentials.
	 *
	 * @param string $operation Operation label.
	 */
	private static function log_database_failure( $operation ) {
		error_log( 'TCH: payment database operation failed (' . sanitize_key( $operation ) . ').' );
	}

	/**
	 * Locks and reloads an order inside the current transaction.
	 *
	 * @param int $order_id Order ID.
	 * @return object|WP_Error
	 */
	private static function lock_order( $order_id ) {
		global $wpdb;
		$table = tch_table( 'orders' );
		$wpdb->last_error = '';
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d FOR UPDATE", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) {
			self::log_database_failure( 'lock_order' );
			return new WP_Error( 'database', __( 'We could not safely read the order. Please try again.', 'tch' ) );
		}
		return $order ? $order : new WP_Error( 'order', __( 'Order not found.', 'tch' ) );
	}

	/**
	 * Checked pending-payment lookup. Query errors fail closed.
	 *
	 * @param int $order_id Order ID.
	 * @return bool|WP_Error
	 */
	private static function pending_exists( $order_id ) {
		global $wpdb;
		$table = tch_table( 'payments' );
		$wpdb->last_error = '';
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE order_id = %d AND status = %s", $order_id, 'pending_verification' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) {
			self::log_database_failure( 'check_pending_payment' );
			return new WP_Error( 'database', __( 'We could not check the existing payment status. Please try again.', 'tch' ) );
		}
		return (int) $count > 0;
	}

	/**
	 * Serializes reference checks when a database cannot install the unique index.
	 *
	 * @param string $reference Normalized transaction reference.
	 * @return string|WP_Error Empty string when the database unique index is active.
	 */
	private static function acquire_reference_lock( $reference ) {
		if ( 'ready' === get_option( 'tch_payment_reference_index_status' ) ) {
			return '';
		}
		global $wpdb;
		$lock_name = 'tch_pay_' . substr( hash( 'sha256', $reference ), 0, 55 );
		$wpdb->last_error = '';
		$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, 5 ) );
		if ( $wpdb->last_error || 1 !== (int) $locked ) {
			self::log_database_failure( 'acquire_reference_lock' );
			return new WP_Error( 'database', __( 'We could not safely reserve this transaction reference. Please try again.', 'tch' ) );
		}
		return $lock_name;
	}

	/**
	 * Releases a fallback reference lock.
	 *
	 * @param string $lock_name Lock name, empty when not acquired.
	 */
	private static function release_reference_lock( $lock_name ) {
		if ( '' === $lock_name ) {
			return;
		}
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
	}

	/**
	 * Amount the customer should pay next (deposit first, then balance).
	 *
	 * @param object $order Order row.
	 * @return int
	 */
	public static function amount_due_now( $order ) {
		$paid = (int) $order->amount_paid;
		if ( 'deposit' === $order->payment_plan && $paid < (int) $order->amount_expected ) {
			return (int) $order->amount_expected - $paid;
		}
		return self::outstanding( $order );
	}

	/**
	 * Whether a pending submission already exists for the order.
	 *
	 * @param int $order_id Order.
	 * @return bool
	 */
	public static function has_pending( $order_id ) {
		$result = self::pending_exists( $order_id );
		return is_wp_error( $result ) ? true : $result;
	}

	/**
	 * Customer submits Mobile Money payment details for checking.
	 *
	 * @param object $order Order (already authorised for this visitor).
	 * @param array  $in    Unslashed POST.
	 * @param array  $files $_FILES.
	 * @return true|WP_Error
	 */
	public static function submit( $order, $in, $files ) {
		global $wpdb;
		$txn   = self::normalize_transaction_reference( $in['transaction_ref'] ?? '' );
		$payer = sanitize_text_field( $in['payer_phone'] ?? '' );
		$raw_amount = is_scalar( $in['amount'] ?? null ) ? trim( (string) $in['amount'] ) : '';
		$errors = array();
		if ( strlen( $txn ) < 4 || strlen( $txn ) > 40 ) {
			$errors[] = __( 'Please enter the Mobile Money transaction ID from your confirmation SMS.', 'tch' );
		}
		if ( ! tch_valid_phone( $payer ) ) {
			$errors[] = __( 'Please enter the phone number you paid from.', 'tch' );
		}
		if ( '' === $raw_amount || ! preg_match( '/^\d{1,10}$/', $raw_amount ) ) {
			$errors[] = __( 'Enter a valid whole-shilling payment amount.', 'tch' );
		} elseif ( (int) $raw_amount < self::MIN_PAYMENT ) {
			$errors[] = sprintf( /* translators: %s minimum amount */ __( 'Payments must be at least %s.', 'tch' ), tch_money( self::MIN_PAYMENT ) );
		}
		$amount = preg_match( '/^\d{1,10}$/', $raw_amount ) ? (int) $raw_amount : 0;
		if ( $errors ) {
			return new WP_Error( 'invalid', implode( "\n", $errors ) );
		}

		$file = null;
		if ( isset( $files['payment_screenshot'] ) ) {
			$file = TCH_Uploads::store( $files['payment_screenshot'], 'pay' );
			if ( is_wp_error( $file ) ) {
				return $file;
			}
		}

		$transaction = self::begin_transaction();
		if ( is_wp_error( $transaction ) ) {
			TCH_Uploads::delete( $file );
			return $transaction;
		}
		$lock_name = '';
		$order_status_changed = false;
		try {
			$current_order = self::lock_order( (int) $order->id );
			if ( is_wp_error( $current_order ) ) {
				self::rollback_transaction( 'lock_order' );
				TCH_Uploads::delete( $file );
				return $current_order;
			}
			if ( 'cancelled' === $current_order->order_status ) {
				self::rollback_validation();
				TCH_Uploads::delete( $file );
				return new WP_Error( 'state', __( 'This order was cancelled. Please contact us.', 'tch' ) );
			}
			$outstanding = self::outstanding( $current_order );
			if ( $outstanding < 1 ) {
				self::rollback_validation();
				TCH_Uploads::delete( $file );
				return new WP_Error( 'state', __( 'This order is already fully paid.', 'tch' ) );
			}
			$pending = self::pending_exists( (int) $current_order->id );
			if ( is_wp_error( $pending ) ) {
				self::rollback_transaction( 'check_pending_payment' );
				TCH_Uploads::delete( $file );
				return $pending;
			}
			if ( $pending ) {
				self::rollback_validation();
				TCH_Uploads::delete( $file );
				return new WP_Error( 'state', __( 'We are still checking your previous payment. Please wait for it to be verified before sending another.', 'tch' ) );
			}
			if ( $amount > $outstanding ) {
				self::rollback_validation();
				TCH_Uploads::delete( $file );
				return new WP_Error( 'amount', sprintf( /* translators: %s outstanding */ __( 'The payment cannot exceed the outstanding balance of %s.', 'tch' ), tch_money( $outstanding ) ) );
			}
			if ( (int) $current_order->amount_paid < 1 ) {
				$required = 'deposit' === $current_order->payment_plan ? self::deposit_amount( (int) $current_order->total_amount ) : $outstanding;
				if ( $amount !== $required ) {
					self::rollback_validation();
					$message = 'deposit' === $current_order->payment_plan
						? sprintf( /* translators: %s required deposit */ __( 'The first payment for this order must equal the 50%% deposit of %s.', 'tch' ), tch_money( $required ) )
						: sprintf( /* translators: %s outstanding */ __( 'Full payment must equal the outstanding balance of %s.', 'tch' ), tch_money( $required ) );
					TCH_Uploads::delete( $file );
					return new WP_Error( 'amount', $message );
				}
			}

			$lock_name = self::acquire_reference_lock( $txn );
			if ( is_wp_error( $lock_name ) ) {
				self::rollback_transaction( 'acquire_reference_lock' );
				TCH_Uploads::delete( $file );
				return $lock_name;
			}
			$table = tch_table( 'payments' );
			$wpdb->last_error = '';
			$duplicate = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE transaction_ref = %s LIMIT 1", $txn ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->last_error ) {
				self::rollback_transaction( 'check_transaction_reference' );
				TCH_Uploads::delete( $file );
				return new WP_Error( 'database', __( 'We could not check that transaction reference. Please try again.', 'tch' ) );
			}
			if ( $duplicate ) {
				self::rollback_validation();
				TCH_Uploads::delete( $file );
				return new WP_Error( 'duplicate', __( 'This Mobile Money transaction ID has already been submitted.', 'tch' ) );
			}

			$type = (int) $current_order->amount_paid > 0 ? 'balance' : ( 'deposit' === $current_order->payment_plan ? 'deposit' : 'full' );
			$user = get_current_user_id();
			$wpdb->last_error = '';
			$inserted = $wpdb->insert(
				$table,
				array(
					'order_id'        => (int) $current_order->id,
					'payment_type'    => $type,
					'method'          => 'mobile_money',
					'amount'          => $amount,
					'transaction_ref' => $txn,
					'payer_phone'     => $payer,
					'evidence_file'   => $file,
					'status'          => 'pending_verification',
					'source'          => 'customer',
					'submitted_by'    => $user ? $user : null,
					'submitted_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
			$payment_id = (int) $wpdb->insert_id;
			if ( false === $inserted || ! $payment_id ) {
				$is_duplicate = false !== strpos( strtolower( $wpdb->last_error ), 'duplicate' );
				if ( $is_duplicate ) {
					self::rollback_validation();
					TCH_Uploads::delete( $file );
					return new WP_Error( 'duplicate', __( 'This Mobile Money transaction ID has already been submitted.', 'tch' ) );
				}
				$wpdb->last_error = '';
				$duplicate = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE transaction_ref = %s LIMIT 1", $txn ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $duplicate ) {
					self::rollback_validation();
				} else {
					self::rollback_transaction( 'insert_payment' );
				}
				TCH_Uploads::delete( $file );
				return new WP_Error( $duplicate ? 'duplicate' : 'database', $duplicate ? __( 'This Mobile Money transaction ID has already been submitted.', 'tch' ) : __( 'We could not save the payment details. Please try again.', 'tch' ) );
			}
			if ( ! self::log( $payment_id, 'submitted', null, 'pending_verification', $user ? $user : null, __( 'Submitted by customer', 'tch' ) ) ) {
				self::rollback_transaction( 'insert_payment_log' );
				TCH_Uploads::delete( $file );
				return new WP_Error( 'database', __( 'We could not save the payment history. No payment was submitted; please try again.', 'tch' ) );
			}
			$recalculated = self::recalculate( (int) $current_order->id );
			if ( is_wp_error( $recalculated ) ) {
				self::rollback_transaction( 'recalculate_payment' );
				TCH_Uploads::delete( $file );
				return $recalculated;
			}

			if ( 'pending' === $current_order->order_status ) {
				$status = TCH_Orders::set_status( (int) $current_order->id, 'awaiting_verification', null, __( 'Customer submitted payment details', 'tch' ), false, true, false );
				if ( is_wp_error( $status ) ) {
					self::rollback_transaction( 'set_awaiting_verification' );
					TCH_Uploads::delete( $file );
					return new WP_Error( 'database', __( 'We could not update the order status. No payment was submitted; please try again.', 'tch' ) );
				}
				$order_status_changed = true;
			}
			$committed = self::commit_transaction();
			if ( is_wp_error( $committed ) ) {
				return $committed;
			}
		} finally {
			self::release_reference_lock( is_string( $lock_name ) ? $lock_name : '' );
		}
		if ( $order_status_changed ) {
			do_action( 'tch_order_status_changed', (int) $current_order->id, 'pending', 'awaiting_verification', false );
		}
		do_action( 'tch_payment_submitted', $payment_id, (int) $current_order->id );
		return true;
	}

	/**
	 * Staff decision on a pending payment. Requires tch_verify_payments.
	 *
	 * @param int    $payment_id Payment.
	 * @param string $decision   verified|rejected.
	 * @param string $note       Note.
	 * @return true|WP_Error
	 */
	public static function decide( $payment_id, $decision, $note = '' ) {
		global $wpdb;
		if ( ! current_user_can( 'tch_verify_payments' ) ) {
			return new WP_Error( 'cap', __( 'You are not allowed to verify payments.', 'tch' ) );
		}
		if ( ! in_array( $decision, array( 'verified', 'rejected' ), true ) ) {
			return new WP_Error( 'decision', __( 'Unknown decision.', 'tch' ) );
		}
		$payment = self::get( $payment_id );
		if ( ! $payment ) {
			return new WP_Error( 'payment', __( 'Payment not found.', 'tch' ) );
		}
		if ( 'rejected' === $decision && '' === trim( $note ) ) {
			return new WP_Error( 'note', __( 'Please give a reason for rejecting the payment (the customer will see it).', 'tch' ) );
		}
		$transaction = self::begin_transaction();
		if ( is_wp_error( $transaction ) ) {
			return $transaction;
		}
		$order = self::lock_order( (int) $payment->order_id );
		if ( is_wp_error( $order ) ) {
			self::rollback_transaction( 'lock_order_for_decision' );
			return $order;
		}
		$table = tch_table( 'payments' );
		$wpdb->last_error = '';
		$payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d FOR UPDATE", $payment_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error || ! $payment ) {
			self::rollback_transaction( 'lock_payment_for_decision' );
			return new WP_Error( 'database', __( 'We could not safely read the payment. Please reload and try again.', 'tch' ) );
		}
		if ( 'pending_verification' !== $payment->status ) {
			self::rollback_validation();
			return new WP_Error( 'state', __( 'This payment has already been decided.', 'tch' ) );
		}
		if ( 'verified' === $decision && (int) $payment->amount > self::outstanding( $order ) ) {
			self::rollback_validation();
			return new WP_Error( 'amount', __( 'This payment now exceeds the outstanding balance. Review the order before deciding it.', 'tch' ) );
		}
		$user = get_current_user_id();
		$wpdb->last_error = '';
		$updated = $wpdb->update(
			$table,
			array( 'status' => $decision, 'verified_by' => $user, 'verified_at' => current_time( 'mysql' ), 'admin_note' => '' !== $note ? mb_substr( $note, 0, 255 ) : null ),
			array( 'id' => $payment_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $updated || 1 !== (int) $updated ) {
			self::rollback_transaction( 'update_payment_decision' );
			return new WP_Error( 'database', __( 'The payment decision could not be saved.', 'tch' ) );
		}
		if ( ! self::log( $payment_id, $decision, $payment->status, $decision, $user, $note ) ) {
			self::rollback_transaction( 'insert_payment_log' );
			return new WP_Error( 'database', __( 'The payment history could not be saved. No decision was applied.', 'tch' ) );
		}
		$recalculated = self::recalculate( (int) $order->id );
		if ( is_wp_error( $recalculated ) ) {
			self::rollback_transaction( 'recalculate_payment' );
			return $recalculated;
		}
		$pending = self::pending_exists( (int) $order->id );
		if ( is_wp_error( $pending ) ) {
			self::rollback_transaction( 'check_pending_payment' );
			return $pending;
		}
		$order_status_changed = false;
		if ( 'awaiting_verification' === $order->order_status && ! $pending ) {
			$msg = 'verified' === $decision ? __( 'Payment verified – waiting for the bakery to confirm your order', 'tch' ) : __( 'Payment rejected – waiting for a valid payment', 'tch' );
			$status = TCH_Orders::set_status( (int) $order->id, 'pending', $user, $msg, false, true, false );
			if ( is_wp_error( $status ) ) {
				self::rollback_transaction( 'reset_order_after_payment_decision' );
				return new WP_Error( 'database', __( 'The order status could not be updated. The payment decision was not applied.', 'tch' ) );
			}
			$order_status_changed = true;
		}
		$committed = self::commit_transaction();
		if ( is_wp_error( $committed ) ) {
			return $committed;
		}
		if ( $order_status_changed ) {
			do_action( 'tch_order_status_changed', (int) $order->id, 'awaiting_verification', 'pending', false );
		}
		do_action( 'tch_payment_decided', $payment_id, $decision );
		return true;
	}

	/**
	 * Staff records money received directly (e.g. balance paid in cash on collection).
	 * Recorded by staff, so it is verified at the same time and logged.
	 *
	 * @param int    $order_id Order.
	 * @param string|int $amount Amount as submitted, validated without absint.
	 * @param string $method   mobile_money|cash.
	 * @param string $ref      Reference.
	 * @param string $note     Note.
	 * @return true|WP_Error
	 */
	public static function record_by_staff( $order_id, $amount, $method, $ref, $note ) {
		global $wpdb;
		if ( ! current_user_can( 'tch_verify_payments' ) ) {
			return new WP_Error( 'cap', __( 'You are not allowed to record payments.', 'tch' ) );
		}
		$raw_amount = is_scalar( $amount ) ? trim( (string) $amount ) : '';
		if ( '' === $raw_amount || ! preg_match( '/^\d{1,10}$/', $raw_amount ) ) {
			return new WP_Error( 'amount', __( 'Enter a valid whole-shilling payment amount.', 'tch' ) );
		}
		$amount = (int) $raw_amount;
		if ( $amount < self::MIN_PAYMENT ) {
			return new WP_Error( 'amount', sprintf( /* translators: %s minimum */ __( 'Payments must be at least %s.', 'tch' ), tch_money( self::MIN_PAYMENT ) ) );
		}
		$method = in_array( $method, array( 'mobile_money', 'cash' ), true ) ? $method : 'mobile_money';
		$ref    = self::normalize_transaction_reference( $ref );
		if ( 'mobile_money' === $method && ( strlen( $ref ) < 4 || strlen( $ref ) > 40 ) ) {
			return new WP_Error( 'reference', __( 'Enter a valid Mobile Money transaction reference.', 'tch' ) );
		}
		if ( strlen( $ref ) > 40 ) {
			return new WP_Error( 'reference', __( 'The transaction reference is too long.', 'tch' ) );
		}
		$transaction = self::begin_transaction();
		if ( is_wp_error( $transaction ) ) {
			return $transaction;
		}
		$lock_name = '';
		try {
			$order = self::lock_order( $order_id );
			if ( is_wp_error( $order ) ) {
				self::rollback_transaction( 'lock_order_for_staff_payment' );
				return $order;
			}
			$outstanding = self::outstanding( $order );
			if ( 'cancelled' === $order->order_status || $amount > $outstanding ) {
				self::rollback_validation();
				return new WP_Error( 'amount', __( 'The order is cancelled or the payment exceeds the outstanding balance.', 'tch' ) );
			}
			if ( (int) $order->amount_paid < 1 ) {
				$required = 'deposit' === $order->payment_plan ? self::deposit_amount( (int) $order->total_amount ) : $outstanding;
				if ( $amount !== $required ) {
					self::rollback_validation();
					return new WP_Error( 'amount', __( 'The first recorded payment must match the order payment plan.', 'tch' ) );
				}
			}
			$pending = self::pending_exists( $order_id );
			if ( is_wp_error( $pending ) || $pending ) {
				self::rollback_validation();
				return is_wp_error( $pending ) ? $pending : new WP_Error( 'state', __( 'Resolve the pending customer payment before recording another payment.', 'tch' ) );
			}
			if ( '' !== $ref ) {
				$lock_name = self::acquire_reference_lock( $ref );
				if ( is_wp_error( $lock_name ) ) {
					self::rollback_transaction( 'acquire_reference_lock' );
					return $lock_name;
				}
				$table = tch_table( 'payments' );
				$wpdb->last_error = '';
				$duplicate = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE transaction_ref = %s LIMIT 1", $ref ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $wpdb->last_error || $duplicate ) {
					self::rollback_transaction( 'check_staff_transaction_reference' );
					return new WP_Error( $duplicate ? 'duplicate' : 'database', $duplicate ? __( 'This transaction reference has already been recorded.', 'tch' ) : __( 'We could not check that transaction reference.', 'tch' ) );
				}
			}
			$user = get_current_user_id();
			$now  = current_time( 'mysql' );
			$table = tch_table( 'payments' );
			$wpdb->last_error = '';
			$inserted = $wpdb->insert(
				$table,
				array(
					'order_id'        => $order_id,
					'payment_type'    => (int) $order->amount_paid > 0 ? 'balance' : ( 'deposit' === $order->payment_plan ? 'deposit' : 'full' ),
					'method'          => $method,
					'amount'          => $amount,
					'transaction_ref' => '' !== $ref ? $ref : null,
					'status'          => 'verified',
					'source'          => 'staff',
					'submitted_by'    => $user,
					'submitted_at'    => $now,
					'verified_by'     => $user,
					'verified_at'     => $now,
					'admin_note'      => '' !== $note ? mb_substr( $note, 0, 255 ) : null,
				),
				array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
			);
			$pid = (int) $wpdb->insert_id;
			if ( false === $inserted || ! $pid ) {
				$is_duplicate = false !== strpos( strtolower( $wpdb->last_error ), 'duplicate' );
				if ( $is_duplicate ) {
					self::rollback_validation();
					return new WP_Error( 'duplicate', __( 'This transaction reference has already been recorded.', 'tch' ) );
				}
				self::rollback_transaction( 'insert_staff_payment' );
				return new WP_Error( 'database', __( 'The staff payment could not be saved.', 'tch' ) );
			}
			if ( ! self::log( $pid, 'recorded', null, 'verified', $user, '' !== $note ? $note : __( 'Recorded by staff', 'tch' ) ) ) {
				self::rollback_transaction( 'insert_payment_log' );
				return new WP_Error( 'database', __( 'The payment history could not be saved. No payment was recorded.', 'tch' ) );
			}
			$recalculated = self::recalculate( $order_id );
			if ( is_wp_error( $recalculated ) ) {
				self::rollback_transaction( 'recalculate_staff_payment' );
				return $recalculated;
			}
			$committed = self::commit_transaction();
			if ( is_wp_error( $committed ) ) {
				return $committed;
			}
		} finally {
			self::release_reference_lock( is_string( $lock_name ) ? $lock_name : '' );
		}
		do_action( 'tch_payment_decided', $pid, 'verified' );
		return true;
	}

	/**
	 * Recomputes amount_paid and the order payment status from VERIFIED payments only.
	 *
	 * @param int $order_id Order.
	 */
	public static function recalculate( $order_id ) {
		global $wpdb;
		$t     = tch_table( 'payments' );
		$order = TCH_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'order', __( 'Order not found while recalculating payments.', 'tch' ) );
		}
		$wpdb->last_error = '';
		$paid = $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM $t WHERE order_id = %d AND status = %s", $order_id, 'verified' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) {
			self::log_database_failure( 'sum_verified_payments' );
			return new WP_Error( 'database', __( 'We could not calculate verified payments.', 'tch' ) );
		}
		$paid    = (int) $paid;
		$pending = self::pending_exists( $order_id );
		if ( is_wp_error( $pending ) ) {
			return $pending;
		}
		$wpdb->last_error = '';
		$last = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $t WHERE order_id = %d ORDER BY submitted_at DESC, id DESC LIMIT 1", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) {
			self::log_database_failure( 'read_latest_payment_status' );
			return new WP_Error( 'database', __( 'We could not read the payment history.', 'tch' ) );
		}
		if ( $paid > (int) $order->total_amount ) {
			return new WP_Error( 'amount', __( 'Verified payments exceed the order total. Manual review is required.', 'tch' ) );
		}

		if ( $paid >= (int) $order->total_amount && $paid > 0 ) {
			$status = 'fully_paid';
		} elseif ( $pending ) {
			$status = 'pending_verification';
		} elseif ( $paid > 0 ) {
			$status = 'partially_paid';
		} elseif ( 'rejected' === $last ) {
			$status = 'rejected';
		} else {
			$status = 'unpaid';
		}
		$wpdb->last_error = '';
		$updated = $wpdb->update(
			tch_table( 'orders' ),
			array( 'amount_paid' => $paid, 'payment_status' => $status, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $order_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			self::log_database_failure( 'update_order_payment_status' );
			return new WP_Error( 'database', __( 'The order payment status could not be saved.', 'tch' ) );
		}
		return true;
	}

	/**
	 * Verification audit log row.
	 *
	 * @param int         $payment_id Payment.
	 * @param string      $action     Action.
	 * @param string|null $old        Old status.
	 * @param string      $new        New status.
	 * @param int|null    $actor      User.
	 * @param string      $note       Note.
	 */
	private static function log( $payment_id, $action, $old, $new, $actor, $note ) {
		global $wpdb;
		$wpdb->last_error = '';
		$result = $wpdb->insert(
			tch_table( 'payment_log' ),
			array(
				'payment_id' => $payment_id,
				'action'     => $action,
				'old_status' => $old,
				'new_status' => $new,
				'actor_id'   => $actor ? $actor : null,
				'note'       => '' !== (string) $note ? mb_substr( $note, 0, 255 ) : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( false === $result ) {
			self::log_database_failure( 'insert_payment_log' );
			return false;
		}
		return true;
	}
}
