<?php
/**
 * Lightweight hardening that works on any host. A full security suite
 * (Wordfence: firewall, malware scan, 2FA) is still recommended on the live server;
 * the login limiter switches itself off when Wordfence is active to avoid overlap.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Security.
 */
class TCH_Security {

	const MAX_FAILS = 5;
	const LOCK_MIN  = 15;

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( ! self::wordfence_active() ) {
			add_filter( 'authenticate', array( __CLASS__, 'block_locked' ), 30, 3 );
			add_action( 'wp_login_failed', array( __CLASS__, 'login_failed' ) );
			add_action( 'wp_login', array( __CLASS__, 'login_ok' ), 10, 2 );
		}
		add_filter( 'login_errors', array( __CLASS__, 'generic_login_error' ) );
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		add_action( 'send_headers', array( __CLASS__, 'headers' ) );
		add_action( 'login_init', array( __CLASS__, 'headers' ) );
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
		add_action( 'template_redirect', array( __CLASS__, 'block_author_scan' ), 1 ); // Before core's redirect_canonical (10), which would reveal the username.
		add_filter( 'author_link', array( __CLASS__, 'hide_author_link' ) );
		add_filter( 'rest_endpoints', array( __CLASS__, 'hide_user_endpoints' ) );
		add_action( 'user_profile_update_errors', array( __CLASS__, 'strong_staff_passwords' ), 10, 3 );
	}

	/**
	 * Is Wordfence active?
	 *
	 * @return bool
	 */
	private static function wordfence_active() {
		return class_exists( 'wordfence' ) || defined( 'WORDFENCE_VERSION' );
	}

	/**
	 * Transient key for an IP.
	 *
	 * @return string
	 */
	private static function key() {
		return 'tch_lf_' . md5( tch_client_ip() );
	}

	/**
	 * Stops authentication while an IP is locked out.
	 *
	 * @param WP_User|WP_Error|null $user User.
	 * @return WP_User|WP_Error|null
	 */
	public static function block_locked( $user ) {
		$fails = (int) get_transient( self::key() );
		if ( $fails >= self::MAX_FAILS ) {
			/* translators: %d minutes */
			return new WP_Error( 'tch_locked', sprintf( __( '<strong>Too many failed login attempts.</strong> Please try again in %d minutes or reset your password.', 'tch' ), self::LOCK_MIN ) );
		}
		return $user;
	}

	/**
	 * Counts a failure.
	 */
	public static function login_failed() {
		$fails = (int) get_transient( self::key() );
		set_transient( self::key(), $fails + 1, self::LOCK_MIN * MINUTE_IN_SECONDS );
	}

	/**
	 * Resets after success.
	 */
	public static function login_ok() {
		delete_transient( self::key() );
	}

	/**
	 * Do not reveal whether the username or the password was wrong.
	 *
	 * @param string $error Error HTML.
	 * @return string
	 */
	public static function generic_login_error( $error ) {
		if ( false !== strpos( $error, 'Too many failed' ) ) {
			return $error;
		}
		if ( false !== stripos( $error, 'password' ) || false !== stripos( $error, 'username' ) || false !== stripos( $error, 'email' ) ) {
			return __( '<strong>Error:</strong> The login details are not correct.', 'tch' );
		}
		return $error;
	}

	/**
	 * Security headers (HSTS is added only on HTTPS).
	 */
	public static function headers() {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000' );
		}
	}

	/**
	 * Stops ?author=1 username discovery.
	 */
	public static function block_author_scan() {
		if ( ! is_user_logged_in() && ( is_author() || isset( $_GET['author'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	/**
	 * Author archive links would expose login names; point them to the About page instead.
	 *
	 * @return string
	 */
	public static function hide_author_link() {
		return tch_page_url( 'about' );
	}

	/**
	 * Hides the public users list in the REST API from visitors.
	 *
	 * @param array $endpoints Endpoints.
	 * @return array
	 */
	public static function hide_user_endpoints( $endpoints ) {
		if ( ! current_user_can( 'list_users' ) ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}

	/**
	 * Staff accounts must use strong passwords (12+ chars, upper, lower, digit, symbol).
	 *
	 * @param WP_Error $errors Errors.
	 * @param bool     $update Update.
	 * @param object   $user   User data.
	 */
	public static function strong_staff_passwords( $errors, $update, $user ) {
		if ( empty( $user->user_pass ) ) {
			return;
		}
		$role  = isset( $user->role ) ? $user->role : '';
		$staff = in_array( $role, array( 'administrator', 'editor', 'tch_manager' ), true );
		if ( ! $role && ! empty( $user->ID ) ) {
			$staff = TCH_Accounts::is_staff( get_userdata( $user->ID ) );
		}
		$p = $user->user_pass;
		if ( $staff && ( strlen( $p ) < 12 || ! preg_match( '/[A-Z]/', $p ) || ! preg_match( '/[a-z]/', $p ) || ! preg_match( '/\d/', $p ) || ! preg_match( '/[^A-Za-z0-9]/', $p ) ) ) {
			$errors->add( 'tch_weak', __( '<strong>Error:</strong> Staff passwords must be at least 12 characters and include upper and lower case letters, a number and a symbol.', 'tch' ) );
		}
	}
}
