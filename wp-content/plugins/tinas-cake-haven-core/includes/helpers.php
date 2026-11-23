<?php
/**
 * Shared helper functions.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Full table name with the WordPress prefix, e.g. wp_tch_orders.
 * Only whitelisted names are accepted so a table name can never come from user input.
 *
 * @param string $name Short table name.
 * @return string
 */
function tch_table( $name ) {
	global $wpdb;
	static $allowed = array( 'cake_sizes', 'flavours', 'orders', 'order_items', 'custom_requests', 'payments', 'payment_log', 'status_history', 'messages' );
	if ( ! in_array( $name, $allowed, true ) ) {
		throw new InvalidArgumentException( 'Unknown table' );
	}
	return $wpdb->prefix . 'tch_' . $name;
}

/**
 * Formats a whole-shilling amount: UGX 35,000.
 *
 * @param int|float $amount Amount.
 * @return string
 */
function tch_money( $amount ) {
	return 'UGX ' . number_format( (int) $amount );
}

/**
 * Order statuses (workflow).
 *
 * @return array<string,string>
 */
function tch_order_statuses() {
	return array(
		'pending'               => __( 'Pending', 'tch' ),
		'awaiting_verification' => __( 'Awaiting payment verification', 'tch' ),
		'confirmed'             => __( 'Confirmed', 'tch' ),
		'preparing'             => __( 'Preparing', 'tch' ),
		'ready'                 => __( 'Ready for collection', 'tch' ),
		'out_for_delivery'      => __( 'Out for delivery', 'tch' ),
		'completed'             => __( 'Completed', 'tch' ),
		'cancelled'             => __( 'Cancelled', 'tch' ),
	);
}

/**
 * Order-level payment statuses (derived from verified payment records).
 *
 * @return array<string,string>
 */
function tch_order_payment_statuses() {
	return array(
		'unpaid'               => __( 'Awaiting payment', 'tch' ),
		'pending_verification' => __( 'Pending verification', 'tch' ),
		'rejected'             => __( 'Rejected', 'tch' ),
		'partially_paid'       => __( 'Partially paid', 'tch' ),
		'fully_paid'           => __( 'Fully paid', 'tch' ),
	);
}

/**
 * Statuses of a single payment submission.
 *
 * @return array<string,string>
 */
function tch_payment_record_statuses() {
	return array(
		'pending_verification' => __( 'Pending verification', 'tch' ),
		'verified'             => __( 'Verified', 'tch' ),
		'rejected'             => __( 'Rejected', 'tch' ),
	);
}

/**
 * HTML badge for a status.
 *
 * @param string $status Status key.
 * @param array  $labels Labels map.
 * @return string
 */
function tch_badge( $status, $labels ) {
	$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	return '<span class="tch-badge tch-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
}

/**
 * Business setting with default.
 *
 * @param string $key     Key.
 * @param string $default Default.
 * @return string
 */
function tch_setting( $key, $default = '' ) {
	return TCH_Settings::get( $key, $default );
}

/**
 * wa.me link to the business WhatsApp.
 *
 * @param string $text Optional prefilled text.
 * @return string
 */
function tch_whatsapp_link( $text = '' ) {
	$number = preg_replace( '/\D+/', '', tch_setting( 'whatsapp', '256792104798' ) );
	return 'https://wa.me/' . $number . ( '' !== $text ? '?text=' . rawurlencode( $text ) : '' );
}

/**
 * wa.me link to any phone number (used by staff to message a customer).
 *
 * @param string $phone Phone as typed by the customer.
 * @param string $text  Message.
 * @return string
 */
function tch_whatsapp_to( $phone, $text = '' ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	if ( 0 === strpos( $digits, '0' ) ) {
		$digits = '256' . substr( $digits, 1 ); // Ugandan local format 07xx -> 2567xx.
	}
	return 'https://wa.me/' . $digits . ( '' !== $text ? '?text=' . rawurlencode( $text ) : '' );
}

/**
 * tel: link for the business phone.
 *
 * @return string
 */
function tch_tel_link() {
	return 'tel:' . preg_replace( '/[^0-9+]/', '', tch_setting( 'phone', '+256792104798' ) );
}

/**
 * Normalises a phone number for comparisons (digits only, 07.. => 2567..).
 *
 * @param string $phone Phone.
 * @return string
 */
function tch_normalise_phone( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	if ( 0 === strpos( $digits, '0' ) ) {
		$digits = '256' . substr( $digits, 1 );
	}
	return $digits;
}

/**
 * Validates a phone number (Ugandan or international formats).
 *
 * @param string $phone Phone.
 * @return bool
 */
function tch_valid_phone( $phone ) {
	if ( ! preg_match( '/^\+?[0-9 ()\-]{9,20}$/', (string) $phone ) ) {
		return false;
	}
	$len = strlen( tch_normalise_phone( $phone ) );
	return $len >= 9 && $len <= 15;
}

/**
 * Client IP used only for rate limiting (never stored with orders).
 *
 * @return string
 */
function tch_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Simple fixed-window rate limiter backed by transients.
 *
 * @param string $bucket  Bucket name.
 * @param int    $limit   Max hits.
 * @param int    $seconds Window.
 * @return bool True when the request is allowed.
 */
function tch_rate_limit( $bucket, $limit, $seconds ) {
	$key  = 'tch_rl_' . md5( $bucket . '|' . tch_client_ip() );
	$hits = (int) get_transient( $key );
	if ( $hits >= $limit ) {
		return false;
	}
	set_transient( $key, $hits + 1, $seconds );
	return true;
}

/**
 * Permalink of one of the pages created by the setup tool.
 *
 * @param string $key  Page key (order, custom, my-orders, track, account, products, contact).
 * @param array  $args Query args.
 * @return string
 */
function tch_page_url( $key, $args = array() ) {
	$pages = get_option( 'tch_pages', array() );
	$url   = ( isset( $pages[ $key ] ) && get_post_status( $pages[ $key ] ) === 'publish' ) ? get_permalink( $pages[ $key ] ) : home_url( '/' );
	return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * Current site-local DateTime.
 *
 * @return DateTimeImmutable
 */
function tch_now() {
	return new DateTimeImmutable( 'now', wp_timezone() );
}

/**
 * Parses an HTML datetime-local / date value in the site timezone.
 *
 * @param string $value Value.
 * @return DateTimeImmutable|null
 */
function tch_parse_datetime( $value ) {
	$value = str_replace( 'T', ' ', trim( (string) $value ) );
	foreach ( array( 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d' ) as $fmt ) {
		$d = DateTimeImmutable::createFromFormat( '!' . $fmt, $value, wp_timezone() );
		if ( $d && $d->format( $fmt ) === $value ) {
			return $d;
		}
	}
	return null;
}

/**
 * Formats a MySQL datetime for display in the site timezone.
 *
 * @param string|null $mysql Stored value (site-local time).
 * @return string
 */
function tch_date( $mysql ) {
	if ( empty( $mysql ) ) {
		return '—';
	}
	$ts = strtotime( $mysql );
	return $ts ? wp_date( 'j M Y, g:i a', $ts, new DateTimeZone( 'UTC' ) ) : '—';
}

/**
 * Display name of a WordPress user id (for audit trails).
 *
 * @param int|null $user_id User id.
 * @return string
 */
function tch_user_label( $user_id ) {
	if ( ! $user_id ) {
		return __( 'Customer / system', 'tch' );
	}
	$u = get_userdata( (int) $user_id );
	return $u ? $u->display_name : __( '(deleted user)', 'tch' );
}

/**
 * Renders a template from /templates with variables, returning HTML.
 *
 * @param string $name Template file name without .php.
 * @param array  $vars Variables.
 * @return string
 */
function tch_template( $name, $vars = array() ) {
	$file = TCH_DIR . 'templates/' . $name . '.php';
	if ( ! is_readable( $file ) ) {
		return '';
	}
	ob_start();
	extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	include $file;
	return (string) ob_get_clean();
}
