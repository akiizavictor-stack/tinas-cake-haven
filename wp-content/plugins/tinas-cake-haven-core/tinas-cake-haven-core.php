<?php
/**
 * Plugin Name:       Tina's Cake Haven Core
 * Plugin URI:        https://example.invalid/tinas-cake-haven
 * Description:       Business features for Tina's Cake Haven: cake catalogue, online ordering with server-side pricing, customized birthday cake requests, manual Mobile Money payment verification, order tracking, customer order history and an owner dashboard. Theme-independent.
	 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Tina's Cake Haven web project (BIT 2103)
 * License:           GPL-2.0-or-later
 * Text Domain:       tch
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

define( 'TCH_VERSION', '1.1.0' );
define( 'TCH_DB_VERSION', '1.1.0' );
define( 'TCH_FILE', __FILE__ );
define( 'TCH_DIR', plugin_dir_path( __FILE__ ) );
define( 'TCH_URL', plugin_dir_url( __FILE__ ) );

require_once TCH_DIR . 'includes/helpers.php';
require_once TCH_DIR . 'includes/class-tch-install.php';
require_once TCH_DIR . 'includes/class-tch-settings.php';
require_once TCH_DIR . 'includes/class-tch-catalogue.php';
require_once TCH_DIR . 'includes/class-tch-uploads.php';
require_once TCH_DIR . 'includes/class-tch-orders.php';
require_once TCH_DIR . 'includes/class-tch-payments.php';
require_once TCH_DIR . 'includes/class-tch-notifications.php';
require_once TCH_DIR . 'includes/class-tch-accounts.php';
require_once TCH_DIR . 'includes/class-tch-frontend.php';
require_once TCH_DIR . 'includes/class-tch-hero-slider.php';
require_once TCH_DIR . 'includes/class-tch-website-images.php';
require_once TCH_DIR . 'includes/class-tch-security.php';
require_once TCH_DIR . 'includes/class-tch-mail.php';
require_once TCH_DIR . 'includes/class-tch-seo.php';

if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	require_once TCH_DIR . 'includes/admin/class-tch-admin.php';
	require_once TCH_DIR . 'includes/admin/class-tch-orders-table.php';
	require_once TCH_DIR . 'includes/admin/class-tch-setup.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * `wp tch setup [--reset]` – builds pages, products, menus and media.
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Flags.
	 */
	WP_CLI::add_command(
		'tch setup',
		static function ( $args, $assoc_args ) {
			TCH_Setup::run( ! empty( $assoc_args['reset'] ) );
			WP_CLI::success( "Tina's Cake Haven setup finished." );
		}
	);
}

register_activation_hook( __FILE__, array( 'TCH_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TCH_Install', 'deactivate' ) );

add_action( 'plugins_loaded', 'tch_boot' );

/**
 * Boots every module. Each module only registers hooks.
 */
function tch_boot() {
	TCH_Install::maybe_upgrade();
	TCH_Settings::init();
	TCH_Catalogue::init();
	TCH_Uploads::init();
	TCH_Notifications::init();
	TCH_Accounts::init();
	TCH_Frontend::init();
	TCH_Security::init();
	TCH_Mail::init();
	TCH_SEO::init();
	TCH_Website_Images::init();
	if ( is_admin() ) {
		TCH_Admin::init();
		TCH_Setup::init();
		TCH_Hero_Slider::init();
	}
}
