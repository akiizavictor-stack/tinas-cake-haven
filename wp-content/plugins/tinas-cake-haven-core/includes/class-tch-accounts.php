<?php
/**
 * Customer accounts built on WordPress users (no separate login system).
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Accounts.
 */
class TCH_Accounts {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'register_form', array( __CLASS__, 'register_fields' ) );
		add_filter( 'registration_errors', array( __CLASS__, 'register_validate' ), 10, 3 );
		add_action( 'user_register', array( __CLASS__, 'register_save' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_style' ) );
		add_filter( 'login_headerurl', 'home_url' );
		add_filter( 'login_headertext', array( __CLASS__, 'login_title' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_action( 'admin_init', array( __CLASS__, 'keep_customers_out_of_admin' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
		add_shortcode( 'tch_account', array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ) );
	}

	/**
	 * Staff = anyone who can manage orders or edit content.
	 *
	 * @param WP_User|null $user User.
	 * @return bool
	 */
	public static function is_staff( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		return $user && ( user_can( $user, 'edit_posts' ) || user_can( $user, 'tch_manage_orders' ) );
	}

	/**
	 * Extra registration fields.
	 */
	public static function register_fields() {
		$name  = isset( $_POST['tch_full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tch_full_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$phone = isset( $_POST['tch_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['tch_phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		wp_nonce_field( 'tch_register', 'tch_register_nonce' );
		?>
		<p><label for="tch_full_name"><?php esc_html_e( 'Full name', 'tch' ); ?></label>
			<input type="text" name="tch_full_name" id="tch_full_name" class="input" value="<?php echo esc_attr( $name ); ?>" autocomplete="name" required maxlength="120"></p>
		<p><label for="tch_phone"><?php esc_html_e( 'Phone number (for order updates)', 'tch' ); ?></label>
			<input type="tel" name="tch_phone" id="tch_phone" class="input" value="<?php echo esc_attr( $phone ); ?>" autocomplete="tel" required placeholder="07XX XXX XXX"></p>
		<?php
	}

	/**
	 * Validates registration fields.
	 *
	 * @param WP_Error $errors Errors.
	 * @return WP_Error
	 */
	public static function register_validate( $errors ) {
		if ( ! isset( $_POST['tch_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tch_register_nonce'] ) ), 'tch_register' ) ) {
			$errors->add( 'tch_nonce', __( 'Your session expired. Please reload the page and try again.', 'tch' ) );
			return $errors;
		}
		$name  = isset( $_POST['tch_full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tch_full_name'] ) ) : '';
		$phone = isset( $_POST['tch_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['tch_phone'] ) ) : '';
		if ( mb_strlen( $name ) < 2 ) {
			$errors->add( 'tch_name', __( '<strong>Error:</strong> Please enter your full name.', 'tch' ) );
		}
		if ( ! tch_valid_phone( $phone ) ) {
			$errors->add( 'tch_phone', __( '<strong>Error:</strong> Please enter a valid phone number.', 'tch' ) );
		}
		if ( ! tch_rate_limit( 'register', 5, HOUR_IN_SECONDS ) ) {
			$errors->add( 'tch_rate', __( '<strong>Error:</strong> Too many registrations from this network. Please try again later.', 'tch' ) );
		}
		return $errors;
	}

	/**
	 * Saves registration fields. New users always get the Customer role.
	 *
	 * @param int $user_id User.
	 */
	public static function register_save( $user_id ) {
		if ( ! isset( $_POST['tch_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tch_register_nonce'] ) ), 'tch_register' ) ) {
			return;
		}
		$name  = isset( $_POST['tch_full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tch_full_name'] ) ) : '';
		$phone = isset( $_POST['tch_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['tch_phone'] ) ) : '';
		if ( $name ) {
			wp_update_user( array( 'ID' => $user_id, 'display_name' => $name, 'first_name' => $name ) );
		}
		if ( $phone ) {
			update_user_meta( $user_id, 'tch_phone', $phone );
		}
	}

	/**
	 * Branded login / register screen.
	 */
	public static function login_style() {
		$logo = get_theme_file_uri( 'assets/images/logo-256.webp' );
		?>
		<style>
			body.login{background:#fff8ee;font-family:Nunito,system-ui,sans-serif}
			body.login h1 a{background-image:url(<?php echo esc_url( $logo ); ?>);background-size:contain;width:160px;height:160px}
			.login form{border:0;border-radius:18px;box-shadow:0 10px 30px rgba(74,44,33,.12)}
			.wp-core-ui .button-primary{background:#c2185b;border-color:#c2185b;border-radius:999px;padding:0 22px}
			.wp-core-ui .button-primary:hover,.wp-core-ui .button-primary:focus{background:#8e0f43;border-color:#8e0f43}
			.login #nav a,.login #backtoblog a{color:#4a2c21}
			input[type=text]:focus,input[type=password]:focus,input[type=email]:focus,input[type=tel]:focus{border-color:#c2185b;box-shadow:0 0 0 1px #c2185b}
		</style>
		<?php
	}

	/**
	 * Login logo text.
	 *
	 * @return string
	 */
	public static function login_title() {
		return tch_setting( 'business_name', get_bloginfo( 'name' ) );
	}

	/**
	 * Customers land on My Account; staff go to the dashboard.
	 *
	 * @param string           $to        Redirect.
	 * @param string           $requested Requested.
	 * @param WP_User|WP_Error $user      User.
	 * @return string
	 */
	public static function login_redirect( $to, $requested, $user ) {
		if ( $user instanceof WP_User && ! self::is_staff( $user ) ) {
			$requested_local = wp_validate_redirect( $requested, '' );
			if ( $requested_local && false === strpos( $requested_local, '/wp-admin' ) ) {
				return $requested_local;
			}
			return tch_page_url( 'account' );
		}
		if ( $user instanceof WP_User && user_can( $user, 'tch_view_dashboard' ) && ( '' === $requested || admin_url() === $requested ) ) {
			return admin_url( 'admin.php?page=tch-dashboard' );
		}
		return $to;
	}

	/**
	 * Customers never see wp-admin.
	 */
	public static function keep_customers_out_of_admin() {
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		if ( is_user_logged_in() && ! self::is_staff() && 'admin-post.php' !== $script ) {
			wp_safe_redirect( tch_page_url( 'account' ) );
			exit;
		}
	}

	/**
	 * Hides the toolbar for customers.
	 *
	 * @param bool $show Show.
	 * @return bool
	 */
	public static function admin_bar( $show ) {
		return is_user_logged_in() && ! self::is_staff() ? false : $show;
	}

	/**
	 * Profile / password updates from the My Account page.
	 */
	public static function handle_post() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['tch_account_action'] ) || ! is_user_logged_in() ) {
			return;
		}
		if ( ! isset( $_POST['_tch_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_tch_nonce'] ) ), 'tch_account' ) ) {
			TCH_Frontend::flash( 'error', __( 'Your session expired. Please try again.', 'tch' ) );
			return;
		}
		$user   = wp_get_current_user();
		$action = sanitize_key( $_POST['tch_account_action'] );
		if ( 'profile' === $action ) {
			$name  = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
			$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
			if ( mb_strlen( $name ) < 2 || ! tch_valid_phone( $phone ) ) {
				TCH_Frontend::flash( 'error', __( 'Please enter your full name and a valid phone number.', 'tch' ) );
				return;
			}
			wp_update_user( array( 'ID' => $user->ID, 'display_name' => $name, 'first_name' => $name ) );
			update_user_meta( $user->ID, 'tch_phone', $phone );
			TCH_Frontend::flash( 'success', __( 'Your details were updated.', 'tch' ) );
		} elseif ( 'password' === $action ) {
			$current = (string) wp_unslash( $_POST['current_password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are not sanitised.
			$new     = (string) wp_unslash( $_POST['new_password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$confirm = (string) wp_unslash( $_POST['confirm_password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
				TCH_Frontend::flash( 'error', __( 'Your current password is not correct.', 'tch' ) );
				return;
			}
			if ( strlen( $new ) < 10 || ! preg_match( '/[A-Za-z]/', $new ) || ! preg_match( '/\d/', $new ) ) {
				TCH_Frontend::flash( 'error', __( 'Your new password must be at least 10 characters and contain letters and numbers.', 'tch' ) );
				return;
			}
			if ( $new !== $confirm ) {
				TCH_Frontend::flash( 'error', __( 'The new passwords do not match.', 'tch' ) );
				return;
			}
			wp_set_password( $new, $user->ID );
			wp_set_auth_cookie( $user->ID );
			wp_set_current_user( $user->ID );
			TCH_Frontend::flash( 'success', __( 'Your password was changed.', 'tch' ) );
		}
		wp_safe_redirect( tch_page_url( 'account' ) );
		exit;
	}

	/**
	 * [tch_account] – login/register or profile.
	 *
	 * @return string
	 */
	public static function shortcode() {
		TCH_Frontend::enqueue();
		if ( ! is_user_logged_in() ) {
			return tch_template( 'account-login' );
		}
		$user = wp_get_current_user();
		return tch_template(
			'account',
			array(
				'user'   => $user,
				'phone'  => (string) get_user_meta( $user->ID, 'tch_phone', true ),
				'orders' => TCH_Orders::count_for_user( $user->ID ),
			)
		);
	}
}
