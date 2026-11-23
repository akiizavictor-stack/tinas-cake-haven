<?php
/**
 * SMTP for professional cPanel mailboxes (info@, orders@, support@).
 *
 * Credentials are NEVER stored in the database or in this code. Add them to
 * wp-config.php on the server (see docs/05-email-smtp.md):
 *
 *   define( 'TCH_SMTP_HOST', 'mail.yourdomain.com' );
 *   define( 'TCH_SMTP_PORT', 465 );            // 465 = SSL, 587 = STARTTLS
 *   define( 'TCH_SMTP_SECURE', 'ssl' );        // 'ssl' or 'tls'
 *   define( 'TCH_SMTP_USER', 'orders@yourdomain.com' );
 *   define( 'TCH_SMTP_PASS', '...mailbox password...' );
 *   define( 'TCH_MAIL_FROM', 'orders@yourdomain.com' );
 *   define( 'TCH_MAIL_FROM_NAME', "Tina's Cake Haven" );
 *
 * When the constants are missing WordPress uses the server's default mail().
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Mail.
 */
class TCH_Mail {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'smtp' ) );
		add_filter( 'wp_mail_from', array( __CLASS__, 'from' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'from_name' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'log_failure' ) );
	}

	/**
	 * Is SMTP configured?
	 *
	 * @return bool
	 */
	public static function configured() {
		return defined( 'TCH_SMTP_HOST' ) && TCH_SMTP_HOST && defined( 'TCH_SMTP_USER' ) && defined( 'TCH_SMTP_PASS' );
	}

	/**
	 * Configures PHPMailer.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $mailer Mailer.
	 */
	public static function smtp( $mailer ) {
		if ( ! self::configured() ) {
			return;
		}
		$mailer->isSMTP();
		$mailer->Host       = TCH_SMTP_HOST;
		$mailer->Port       = defined( 'TCH_SMTP_PORT' ) ? (int) TCH_SMTP_PORT : 465;
		$mailer->SMTPSecure = defined( 'TCH_SMTP_SECURE' ) ? TCH_SMTP_SECURE : 'ssl';
		$mailer->SMTPAuth   = true;
		$mailer->Username   = TCH_SMTP_USER;
		$mailer->Password   = TCH_SMTP_PASS;
		$mailer->Timeout    = 15;
	}

	/**
	 * From address.
	 *
	 * @param string $from Default.
	 * @return string
	 */
	public static function from( $from ) {
		if ( defined( 'TCH_MAIL_FROM' ) && is_email( TCH_MAIL_FROM ) ) {
			return TCH_MAIL_FROM;
		}
		return $from;
	}

	/**
	 * From name.
	 *
	 * @param string $name Default.
	 * @return string
	 */
	public static function from_name( $name ) {
		if ( defined( 'TCH_MAIL_FROM_NAME' ) ) {
			return TCH_MAIL_FROM_NAME;
		}
		return 'WordPress' === $name ? tch_setting( 'business_name', $name ) : $name;
	}

	/**
	 * Logs failures without credentials.
	 *
	 * @param WP_Error $error Error.
	 */
	public static function log_failure( $error ) {
		update_option( 'tch_last_mail_error', array( 'time' => current_time( 'mysql' ), 'message' => wp_strip_all_tags( $error->get_error_message() ) ), false );
	}
}
