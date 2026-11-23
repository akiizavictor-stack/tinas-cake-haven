<?php
/**
 * Orders repository: validation, server-side totals and all order SQL (prepared).
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Orders.
 */
class TCH_Orders {

	/* ------------------------------------------------------------------
	 * CREATE
	 * ------------------------------------------------------------------ */

	/**
	 * Validates customer/fulfilment fields shared by both order forms.
	 *
	 * @param array $in     Unslashed POST.
	 * @param array $errors Errors (by reference).
	 * @return array Clean values.
	 */
	public static function clean_customer( $in, &$errors ) {
		$v = array(
			'name'     => sanitize_text_field( $in['full_name'] ?? '' ),
			'phone'    => sanitize_text_field( $in['phone'] ?? '' ),
			'email'    => sanitize_email( $in['email'] ?? '' ),
			'fulfil'   => sanitize_key( $in['fulfilment'] ?? 'collection' ),
			'address'  => sanitize_text_field( $in['delivery_address'] ?? '' ),
			'plan'     => sanitize_key( $in['payment_plan'] ?? 'full' ),
			'notes'    => sanitize_textarea_field( $in['customer_notes'] ?? '' ),
		);
		if ( mb_strlen( $v['name'] ) < 2 || mb_strlen( $v['name'] ) > 120 ) {
			$errors[] = __( 'Please enter your full name.', 'tch' );
		}
		if ( ! tch_valid_phone( $v['phone'] ) ) {
			$errors[] = __( 'Please enter a valid phone number, e.g. 0772 123456.', 'tch' );
		}
		if ( ! empty( $in['email'] ) && ( ! is_email( $v['email'] ) || mb_strlen( $v['email'] ) > 120 ) ) {
			$errors[] = __( 'The email address is not valid.', 'tch' );
		}
		if ( ! in_array( $v['fulfil'], array( 'collection', 'delivery' ), true ) ) {
			$errors[] = __( 'Please choose collection or delivery.', 'tch' );
		}
		if ( 'delivery' === $v['fulfil'] && ( mb_strlen( $v['address'] ) < 5 || mb_strlen( $v['address'] ) > 255 ) ) {
			$errors[] = __( 'Please describe your delivery location.', 'tch' );
		}
		if ( ! in_array( $v['plan'], array( 'full', 'deposit' ), true ) ) {
			$errors[] = __( 'Please choose full payment or a 50% deposit.', 'tch' );
		}
		if ( mb_strlen( $v['notes'] ) > 1000 ) {
			$errors[] = __( 'Your notes are too long (maximum 1000 characters).', 'tch' );
		}
		return $v;
	}

	/**
	 * Places a standard order from the Order Online form.
	 *
	 * @param array $in Unslashed POST.
	 * @return array{ok:bool,errors:array,ref:?string}
	 */
	public static function place_standard( $in, $submission_key ) {
		$errors = array();
		$c      = self::clean_customer( $in, $errors );

		$needed = null;
		$raw    = sanitize_text_field( $in['needed_by'] ?? '' );
		if ( '' === $raw ) {
			$errors[] = __( 'Please tell us the date and time you need your order.', 'tch' );
		} else {
			$needed = tch_parse_datetime( $raw );
			if ( ! $needed || $needed < tch_now()->modify( '+1 hour' ) ) {
				$errors[] = __( 'The date you need your order must be in the future.', 'tch' );
				$needed   = null;
			}
		}

		$raw_items = isset( $in['items'] ) && is_array( $in['items'] ) ? $in['items'] : array();
		if ( ! $raw_items ) {
			$errors[] = __( 'Please add at least one item to your order.', 'tch' );
		}
		if ( count( $raw_items ) > 15 ) {
			$errors[]  = __( 'Too many items in one order. Please contact us for large orders.', 'tch' );
			$raw_items = array();
		}

		$lines = array();
		foreach ( $raw_items as $it ) {
			if ( ! is_array( $it ) || empty( $it['product_id'] ) ) {
				continue;
			}
			$line = TCH_Catalogue::price_line( absint( $it['product_id'] ), absint( $it['size_id'] ?? 0 ), absint( $it['flavour_id'] ?? 0 ), absint( $it['quantity'] ?? 0 ) );
			if ( is_wp_error( $line ) ) {
				$errors[] = $line->get_error_message();
				continue;
			}
			$lines[] = $line;
		}
		if ( ! $errors && ! $lines ) {
			$errors[] = __( 'Please add at least one item to your order.', 'tch' );
		}
		if ( $errors ) {
			return array( 'ok' => false, 'errors' => array_values( array_unique( $errors ) ), 'ref' => null );
		}
		if ( ! preg_match( '/^[a-f0-9]{64}$/', (string) $submission_key ) ) {
			return array( 'ok' => false, 'errors' => array( __( 'The order form expired. Reload it and submit again.', 'tch' ) ), 'ref' => null );
		}
		return self::insert( 'standard', $c, $needed, $lines, null, $submission_key );
	}

	/**
	 * Places a customized birthday cake request.
	 *
	 * @param array $in    Unslashed POST.
	 * @param array $files $_FILES.
	 * @return array{ok:bool,errors:array,ref:?string}
	 */
	public static function place_custom( $in, $files, $submission_key ) {
		$errors = array();
		$c      = self::clean_customer( $in, $errors );
		$hours  = (int) tch_setting( 'custom_notice_hours', '24' );

		$event = tch_parse_datetime( sanitize_text_field( $in['event_date'] ?? '' ) );
		if ( ! $event ) {
			$errors[] = __( 'Please choose the date and time you need the cake.', 'tch' );
		} elseif ( $event < tch_now()->modify( '+' . $hours . ' hours' ) ) {
			/* translators: %d hours */
			$errors[] = sprintf( __( 'Customized birthday cakes must be ordered at least %d hours in advance. Please choose a later date or time.', 'tch' ), $hours );
		}
		$design = sanitize_textarea_field( $in['design_description'] ?? '' );
		$insc   = sanitize_text_field( $in['inscription'] ?? '' );
		$instr  = sanitize_textarea_field( $in['special_instructions'] ?? '' );
		if ( mb_strlen( $design ) < 10 || mb_strlen( $design ) > 2000 ) {
			$errors[] = __( 'Please describe the cake design you would like (at least a sentence).', 'tch' );
		}
		if ( mb_strlen( $insc ) > 150 ) {
			$errors[] = __( 'The message on the cake is too long (maximum 150 characters).', 'tch' );
		}
		if ( mb_strlen( $instr ) > 1000 ) {
			$errors[] = __( 'Additional instructions are too long.', 'tch' );
		}

		$product_id = (int) get_option( 'tch_custom_product_id' );
		$line       = TCH_Catalogue::price_line( $product_id, absint( $in['size_id'] ?? 0 ), absint( $in['flavour_id'] ?? 0 ), 1 );
		if ( is_wp_error( $line ) ) {
			$errors[] = $line->get_error_message();
		}

		$ref_file = null;
		if ( ! $errors && isset( $files['reference_photo'] ) ) {
			$ref_file = TCH_Uploads::store( $files['reference_photo'], 'ref' );
			if ( is_wp_error( $ref_file ) ) {
				$errors[] = $ref_file->get_error_message();
				$ref_file = null;
			}
		}
		if ( $errors ) {
			return array( 'ok' => false, 'errors' => array_values( array_unique( $errors ) ), 'ref' => null );
		}
		$custom = array(
			'design_description'   => $design,
			'inscription'          => '' !== $insc ? $insc : null,
			'event_date'           => $event->format( 'Y-m-d H:i:s' ),
			'reference_file'       => $ref_file,
			'special_instructions' => '' !== $instr ? $instr : null,
		);
		if ( ! preg_match( '/^[a-f0-9]{64}$/', (string) $submission_key ) ) {
			if ( $ref_file ) {
				TCH_Uploads::delete( $ref_file );
			}
			return array( 'ok' => false, 'errors' => array( __( 'The order form expired. Reload it and submit again.', 'tch' ) ), 'ref' => null );
		}
		return self::insert( 'custom', $c, $event, array( $line ), $custom, $submission_key );
	}

	/**
	 * Writes an order, its items, optional custom request and first history row in one transaction.
	 *
	 * @param string                  $type   standard|custom.
	 * @param array                   $c      Customer values.
	 * @param DateTimeImmutable|null  $needed Needed by.
	 * @param array                   $lines  Priced lines.
	 * @param array|null              $custom Custom request.
	 * @param string                  $submission_key SHA-256 form submission key.
	 * @return array{ok:bool,errors:array,ref:?string}
	 */
	private static function insert( $type, $c, $needed, $lines, $custom, $submission_key ) {
		global $wpdb;
		$total = 0;
		foreach ( $lines as $l ) {
			$total += (int) $l['line_total'];
		}
		$expected = 'deposit' === $c['plan'] ? TCH_Payments::deposit_amount( $total ) : $total;
		$now      = current_time( 'mysql' );
		$user_id  = get_current_user_id();

		$wpdb->last_error = '';
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			self::log_database_failure( 'begin_order_transaction' );
			if ( $custom && $custom['reference_file'] ) {
				TCH_Uploads::delete( $custom['reference_file'] );
			}
			return array( 'ok' => false, 'errors' => array( __( 'Sorry, we could not save your order. Please try again or contact us on WhatsApp.', 'tch' ) ), 'ref' => null );
		}
		$ref = self::new_ref();
		$wpdb->last_error = '';
		$ok  = $wpdb->insert(
			tch_table( 'orders' ),
			array(
				'order_ref'           => $ref,
				'submission_key'      => $submission_key,
				'user_id'             => $user_id ? $user_id : null,
				'order_type'          => $type,
				'customer_name'       => $c['name'],
				'customer_phone'      => $c['phone'],
				'customer_phone_norm' => tch_normalise_phone( $c['phone'] ),
				'customer_email'      => $c['email'] ? $c['email'] : null,
				'fulfilment'          => $c['fulfil'],
				'delivery_address'    => 'delivery' === $c['fulfil'] ? $c['address'] : null,
				'needed_by'           => $needed ? $needed->format( 'Y-m-d H:i:s' ) : null,
				'total_amount'        => $total,
				'payment_plan'        => $c['plan'],
				'amount_expected'     => $expected,
				'amount_paid'         => 0,
				'order_status'        => 'pending',
				'payment_status'      => 'unpaid',
				'customer_notes'      => '' !== $c['notes'] ? $c['notes'] : null,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		$order_id = (int) $wpdb->insert_id;

		foreach ( $lines as $l ) {
			$ok = $ok && $wpdb->insert(
				tch_table( 'order_items' ),
				array_merge( array( 'order_id' => $order_id ), $l ),
				array( '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%d', '%d' )
			);
		}
		if ( $custom ) {
			$ok = $ok && $wpdb->insert( tch_table( 'custom_requests' ), array_merge( array( 'order_id' => $order_id ), $custom ), array( '%d', '%s', '%s', '%s', '%s', '%s' ) );
		}
		$ok = $ok && self::log_status( $order_id, null, 'pending', $user_id ? $user_id : null, __( 'Order placed online', 'tch' ) );

		if ( ! $ok || ! $order_id ) {
			$is_duplicate = false !== strpos( strtolower( $wpdb->last_error ), 'duplicate' );
			if ( $is_duplicate ) {
				self::rollback_order_validation();
			} else {
				self::rollback_order_transaction( 'insert_order_or_related_rows' );
			}
			$duplicate = $is_duplicate ? self::get_by_submission_key( $submission_key ) : null;
			if ( $duplicate ) {
				if ( $custom && $custom['reference_file'] ) {
					TCH_Uploads::delete( $custom['reference_file'] );
				}
				return array( 'ok' => true, 'errors' => array(), 'ref' => $duplicate->order_ref );
			}
			if ( $custom && $custom['reference_file'] ) {
				TCH_Uploads::delete( $custom['reference_file'] );
			}
			return array( 'ok' => false, 'errors' => array( __( 'Sorry, we could not save your order. Please try again or contact us on WhatsApp.', 'tch' ) ), 'ref' => null );
		}
		$wpdb->last_error = '';
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			self::log_database_failure( 'commit_order_transaction' );
			self::rollback_order_transaction( 'commit_order_transaction' );
			return array( 'ok' => false, 'errors' => array( __( 'We could not confirm that your order was saved. Please reload and check your order history before retrying.', 'tch' ) ), 'ref' => null );
		}

		if ( $user_id ) {
			update_user_meta( $user_id, 'tch_phone', $c['phone'] );
		}
		do_action( 'tch_order_placed', $order_id );
		return array( 'ok' => true, 'errors' => array(), 'ref' => $ref );
	}

	/**
	 * Finds an existing order after a duplicate idempotency-key insert.
	 *
	 * @param string $submission_key SHA-256 form submission key.
	 * @return object|null
	 */
	private static function get_by_submission_key( $submission_key ) {
		global $wpdb;
		$table = tch_table( 'orders' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE submission_key = %s LIMIT 1", $submission_key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Logs a fixed operation label without customer or SQL data.
	 *
	 * @param string $operation Operation label.
	 */
	private static function log_database_failure( $operation ) {
		error_log( 'TCH: order database operation failed (' . sanitize_key( $operation ) . ').' );
	}

	/**
	 * Rolls back order writes and reports rollback failures without exposing SQL.
	 *
	 * @param string $operation Failed operation.
	 */
	private static function rollback_order_transaction( $operation ) {
		global $wpdb;
		if ( false === $wpdb->query( 'ROLLBACK' ) ) {
			self::log_database_failure( 'rollback_order_transaction' );
		}
		self::log_database_failure( $operation );
	}

	/**
	 * Rolls back expected duplicate idempotency submissions without a fault log.
	 */
	private static function rollback_order_validation() {
		global $wpdb;
		if ( false === $wpdb->query( 'ROLLBACK' ) ) {
			self::log_database_failure( 'rollback_order_validation' );
		}
	}

	/**
	 * Unique reference like TCH-261108-7KQ4M (no confusing 0/O/1/I).
	 *
	 * @return string
	 */
	private static function new_ref() {
		global $wpdb;
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		do {
			$code = '';
			for ( $i = 0; $i < 5; $i++ ) {
				$code .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
			}
			$ref    = 'TCH-' . wp_date( 'ymd' ) . '-' . $code;
			$t      = tch_table( 'orders' );
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE order_ref = %s", $ref ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} while ( $exists );
		return $ref;
	}

	/* ------------------------------------------------------------------
	 * READ
	 * ------------------------------------------------------------------ */

	/**
	 * Order by id.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$t = tch_table( 'orders' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Order by reference.
	 *
	 * @param string $ref Ref.
	 * @return object|null
	 */
	public static function get_by_ref( $ref ) {
		global $wpdb;
		$t = tch_table( 'orders' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE order_ref = %s", strtoupper( trim( $ref ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Guest lookup: requires BOTH the reference and the phone used on the order.
	 *
	 * @param string $ref   Ref.
	 * @param string $phone Phone.
	 * @return object|null
	 */
	public static function find_for_guest( $ref, $phone ) {
		global $wpdb;
		$t = tch_table( 'orders' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE order_ref = %s AND customer_phone_norm = %s", strtoupper( trim( $ref ) ), tch_normalise_phone( $phone ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Logged-in lookup: the order must belong to the user (prevents IDOR).
	 *
	 * @param string $ref     Ref.
	 * @param int    $user_id User.
	 * @return object|null
	 */
	public static function find_for_user( $ref, $user_id ) {
		global $wpdb;
		$t = tch_table( 'orders' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE order_ref = %s AND user_id = %d", strtoupper( trim( $ref ) ), $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Order items JOINed with the size and flavour tables and product posts.
	 *
	 * @param int $order_id Order.
	 * @return array<int,object>
	 */
	public static function items( $order_id ) {
		global $wpdb;
		$i = tch_table( 'order_items' );
		$s = tch_table( 'cake_sizes' );
		$f = tch_table( 'flavours' );
		$sql = "SELECT i.*, s.weight_label AS current_size_weight, f.name AS current_flavour, p.post_status AS product_status
			FROM $i i
			LEFT JOIN $s s ON s.id = i.size_id
			LEFT JOIN $f f ON f.id = i.flavour_id
			LEFT JOIN {$wpdb->posts} p ON p.ID = i.product_id
			WHERE i.order_id = %d ORDER BY i.id";
		return $wpdb->get_results( $wpdb->prepare( $sql, $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Custom cake request for an order.
	 *
	 * @param int $order_id Order.
	 * @return object|null
	 */
	public static function custom_request( $order_id ) {
		global $wpdb;
		$t = tch_table( 'custom_requests' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE order_id = %d", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Status history.
	 *
	 * @param int $order_id Order.
	 * @return array<int,object>
	 */
	public static function history( $order_id ) {
		global $wpdb;
		$t = tch_table( 'status_history' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE order_id = %d ORDER BY created_at, id", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * A customer's orders, newest first, with an item count from a JOIN.
	 *
	 * @param int $user_id User.
	 * @param int $limit   Limit.
	 * @param int $offset  Offset.
	 * @return array<int,object>
	 */
	public static function for_user( $user_id, $limit = 3, $offset = 0 ) {
		global $wpdb;
		$o   = tch_table( 'orders' );
		$i   = tch_table( 'order_items' );
		$sql = "SELECT o.*, COUNT(i.id) AS item_lines, COALESCE(SUM(i.quantity),0) AS item_qty
			FROM $o o LEFT JOIN $i i ON i.order_id = o.id
			WHERE o.user_id = %d
			GROUP BY o.id ORDER BY o.created_at DESC, o.id DESC LIMIT %d OFFSET %d";
		return $wpdb->get_results( $wpdb->prepare( $sql, $user_id, $limit, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Number of orders for a user.
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	public static function count_for_user( $user_id ) {
		global $wpdb;
		$o = tch_table( 'orders' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $o WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Admin list query with filters.
	 *
	 * @param array $args status, pay, search, per_page, page, orderby, order.
	 * @return array{rows:array,total:int}
	 */
	public static function query( $args ) {
		global $wpdb;
		$o      = tch_table( 'orders' );
		$i      = tch_table( 'order_items' );
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['status'] ) && array_key_exists( $args['status'], tch_order_statuses() ) ) {
			$where[]  = 'o.order_status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['pay'] ) && array_key_exists( $args['pay'], tch_order_payment_statuses() ) ) {
			$where[]  = 'o.payment_status = %s';
			$params[] = $args['pay'];
		}
		if ( ! empty( $args['type'] ) && in_array( $args['type'], array( 'standard', 'custom' ), true ) ) {
			$where[]  = 'o.order_type = %s';
			$params[] = $args['type'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(o.order_ref LIKE %s OR o.customer_name LIKE %s OR o.customer_phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$orderby_map = array( 'created_at' => 'o.created_at', 'needed_by' => 'o.needed_by', 'total_amount' => 'o.total_amount', 'order_ref' => 'o.order_ref' );
		$orderby     = isset( $args['orderby'], $orderby_map[ $args['orderby'] ] ) ? $orderby_map[ $args['orderby'] ] : 'o.created_at';
		$order       = isset( $args['order'] ) && 'asc' === strtolower( $args['order'] ) ? 'ASC' : 'DESC';
		$per         = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page        = max( 1, (int) ( $args['page'] ?? 1 ) );
		$w           = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM $o o WHERE $w";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$sql  = "SELECT o.*, GROUP_CONCAT(CONCAT(i.quantity, ' x ', i.product_name) ORDER BY i.id SEPARATOR ', ') AS summary
			FROM $o o LEFT JOIN $i i ON i.order_id = o.id
			WHERE $w GROUP BY o.id ORDER BY $orderby $order LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $per, ( $page - 1 ) * $per ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array( 'rows' => $rows, 'total' => $total );
	}

	/* ------------------------------------------------------------------
	 * UPDATE
	 * ------------------------------------------------------------------ */

	/**
	 * Writes a status history row.
	 *
	 * @param int         $order_id Order.
	 * @param string|null $old      Old.
	 * @param string      $new      New.
	 * @param int|null    $actor    User id.
	 * @param string      $note     Note.
	 * @return bool
	 */
	public static function log_status( $order_id, $old, $new, $actor, $note = '' ) {
		global $wpdb;
		return (bool) $wpdb->insert(
			tch_table( 'status_history' ),
			array(
				'order_id'   => $order_id,
				'old_status' => $old,
				'new_status' => $new,
				'changed_by' => $actor ? $actor : null,
				'note'       => '' !== $note ? mb_substr( $note, 0, 255 ) : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Changes the order status and records who did it.
	 *
	 * @param int      $order_id Order.
	 * @param string   $status   New status.
	 * @param int|null $actor    User id (null = system/customer action).
	 * @param string   $note     Note.
	 * @param bool     $notify   Notify the customer.
	 * @return bool|WP_Error
	 */
	public static function set_status( $order_id, $status, $actor, $note = '', $notify = true, $in_transaction = false, $dispatch = true ) {
		global $wpdb;
		if ( ! array_key_exists( $status, tch_order_statuses() ) ) {
			return new WP_Error( 'status', __( 'Unknown status.', 'tch' ) );
		}
		if ( ! $in_transaction ) {
			$wpdb->last_error = '';
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				self::log_database_failure( 'begin_status_transaction' );
				return new WP_Error( 'database', __( 'The order status could not be updated.', 'tch' ) );
			}
		}
		$table = tch_table( 'orders' );
		$wpdb->last_error = '';
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d FOR UPDATE", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error || ! $order ) {
			if ( ! $in_transaction ) {
				self::rollback_order_transaction( 'lock_order_for_status' );
			} else {
				self::log_database_failure( 'lock_order_for_status' );
			}
			return new WP_Error( $wpdb->last_error ? 'database' : 'order', __( 'The order could not be loaded for a status update.', 'tch' ) );
		}
		if ( $order->order_status === $status && '' === $note ) {
			if ( ! $in_transaction && false === $wpdb->query( 'COMMIT' ) ) {
				self::rollback_order_transaction( 'commit_unchanged_status' );
				return new WP_Error( 'database', __( 'The order status update could not be confirmed.', 'tch' ) );
			}
			return true;
		}
		$wpdb->last_error = '';
		$updated = $wpdb->update(
			tch_table( 'orders' ),
			array( 'order_status' => $status, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $order_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			if ( ! $in_transaction ) {
				self::rollback_order_transaction( 'update_order_status' );
			} else {
				self::log_database_failure( 'update_order_status' );
			}
			return new WP_Error( 'database', __( 'The order status could not be saved.', 'tch' ) );
		}
		if ( ! self::log_status( $order_id, $order->order_status, $status, $actor, $note ) ) {
			if ( ! $in_transaction ) {
				self::rollback_order_transaction( 'insert_status_history' );
			} else {
				self::log_database_failure( 'insert_status_history' );
			}
			return new WP_Error( 'database', __( 'The order history could not be saved. The status was not changed.', 'tch' ) );
		}
		if ( ! $in_transaction ) {
			$wpdb->last_error = '';
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				self::rollback_order_transaction( 'commit_status_transaction' );
				return new WP_Error( 'database', __( 'The order status update could not be confirmed. Please reload the order.', 'tch' ) );
			}
		}
		if ( ! $in_transaction && $dispatch && $order->order_status !== $status ) {
			do_action( 'tch_order_status_changed', $order_id, $order->order_status, $status, $notify );
		}
		return true;
	}

	/**
	 * Updates staff notes / agreed total (e.g. after discussing a custom design).
	 *
	 * @param int      $order_id Order.
	 * @param string   $notes    Notes.
	 * @param int|null $total    New agreed total, or null to keep.
	 * @param int      $actor    Staff user.
	 * @return true|WP_Error
	 */
	public static function update_admin_fields( $order_id, $notes, $total, $actor ) {
		global $wpdb;
		$wpdb->last_error = '';
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			self::log_database_failure( 'begin_admin_order_update' );
			return new WP_Error( 'database', __( 'The order changes could not be started.', 'tch' ) );
		}
		$order = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . tch_table( 'orders' ) . ' WHERE id = %d FOR UPDATE', $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $order ) {
			self::rollback_order_transaction( 'lock_admin_order' );
			return new WP_Error( 'order', __( 'Order not found.', 'tch' ) );
		}
		if ( null !== $total && $total > 0 && (int) $order->total_amount !== $total ) {
			$payments_table = tch_table( 'payments' );
			$wpdb->last_error = '';
			$pending_amount = $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM $payments_table WHERE order_id = %d AND status = %s", $order_id, 'pending_verification' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->last_error ) {
				self::rollback_order_transaction( 'read_pending_order_payments' );
				return new WP_Error( 'database', __( 'Could not verify the current payment balance.', 'tch' ) );
			}
			if ( $total < (int) $order->amount_paid + (int) $pending_amount ) {
				self::rollback_order_validation();
				return new WP_Error( 'amount', __( 'The agreed total cannot be lower than verified payments plus payments awaiting verification.', 'tch' ) );
			}
		}
		$data = array( 'admin_notes' => '' !== $notes ? $notes : null, 'updated_at' => current_time( 'mysql' ) );
		$fmt  = array( '%s', '%s' );
		if ( null !== $total && $total > 0 && (int) $order->total_amount !== $total ) {
			$data['total_amount']    = $total;
			$data['amount_expected'] = 'deposit' === $order->payment_plan ? TCH_Payments::deposit_amount( $total ) : $total;
			$fmt[]                   = '%d';
			$fmt[]                   = '%d';
		}
		$wpdb->last_error = '';
		$updated = $wpdb->update( tch_table( 'orders' ), $data, array( 'id' => $order_id ), $fmt, array( '%d' ) );
		if ( false === $updated ) {
			self::rollback_order_transaction( 'update_admin_order_fields' );
			return new WP_Error( 'database', __( 'The order changes could not be saved.', 'tch' ) );
		}
		if ( isset( $data['total_amount'] ) ) {
			/* translators: 1: old total 2: new total */
			if ( ! self::log_status( $order_id, $order->order_status, $order->order_status, $actor, sprintf( __( 'Order total changed from %1$s to %2$s', 'tch' ), tch_money( $order->total_amount ), tch_money( $total ) ) ) ) {
				self::rollback_order_transaction( 'log_admin_order_total_change' );
				return new WP_Error( 'database', __( 'The order change history could not be saved.', 'tch' ) );
			}
			$recalculated = TCH_Payments::recalculate( $order_id );
			if ( is_wp_error( $recalculated ) ) {
				self::rollback_order_transaction( 'recalculate_after_total_change' );
				return $recalculated;
			}
		}
		$wpdb->last_error = '';
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			self::rollback_order_transaction( 'commit_admin_order_update' );
			return new WP_Error( 'database', __( 'The order changes could not be confirmed. Reload the order before retrying.', 'tch' ) );
		}
		return true;
	}

	/* ------------------------------------------------------------------
	 * DELETE
	 * ------------------------------------------------------------------ */

	/**
	 * Permanently deletes an order. Items, custom request, payments, payment log and
	 * history are removed by ON DELETE CASCADE foreign keys; private files are removed here.
	 *
	 * @param int $order_id Order.
	 * @return bool
	 */
	public static function delete( $order_id ) {
		global $wpdb;
		$custom = self::custom_request( $order_id );
		if ( $custom ) {
			TCH_Uploads::delete( $custom->reference_file );
		}
		foreach ( TCH_Payments::for_order( $order_id ) as $p ) {
			TCH_Uploads::delete( $p->evidence_file );
		}
		return (bool) $wpdb->delete( tch_table( 'orders' ), array( 'id' => $order_id ), array( '%d' ) );
	}

	/* ------------------------------------------------------------------
	 * Dashboard statistics (real records only)
	 * ------------------------------------------------------------------ */

	/**
	 * Aggregated figures.
	 *
	 * @return array
	 */
	public static function stats() {
		global $wpdb;
		$o = tch_table( 'orders' );
		$p = tch_table( 'payments' );
		$i = tch_table( 'order_items' );

		$by_status = array_fill_keys( array_keys( tch_order_statuses() ), 0 );
		foreach ( $wpdb->get_results( "SELECT order_status, COUNT(*) n FROM $o GROUP BY order_status" ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$by_status[ $r->order_status ] = (int) $r->n;
		}
		$since = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 2 * DAY_IN_SECONDS );
		return array(
			'total'          => array_sum( $by_status ),
			'by_status'      => $by_status,
			'new'            => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $o WHERE created_at >= %s", $since ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'pending_pay'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $p WHERE status = %s", 'pending_verification' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'verified_sales' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(p.amount),0) FROM $p p INNER JOIN $o o ON o.id = p.order_id WHERE p.status = %s AND o.order_status <> %s", 'verified', 'cancelled' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'outstanding'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(GREATEST(CAST(total_amount AS SIGNED) - CAST(amount_paid AS SIGNED), 0)),0) FROM $o WHERE order_status <> %s", 'cancelled' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'recent'         => $wpdb->get_results( "SELECT * FROM $o ORDER BY created_at DESC, id DESC LIMIT 8" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'popular'        => $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
					"SELECT i.product_id, i.product_name, SUM(i.quantity) qty, COUNT(DISTINCT i.order_id) orders, SUM(i.line_total) revenue
					FROM $i i INNER JOIN $o o ON o.id = i.order_id
					WHERE o.order_status <> %s GROUP BY i.product_id, i.product_name ORDER BY orders DESC, qty DESC LIMIT 5",
					'cancelled'
				)
			),
		);
	}
}
