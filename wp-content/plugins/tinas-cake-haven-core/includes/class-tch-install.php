<?php
/**
 * Database tables, relationships, roles and capabilities.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class TCH_Install {

	/**
	 * Custom capabilities used by the plugin.
	 *
	 * @return array<string,string>
	 */
	public static function caps() {
		return array(
			'tch_view_dashboard'  => 'View the business dashboard',
			'tch_manage_orders'   => 'View orders and update order status',
			'tch_verify_payments' => 'Verify or reject Mobile Money payments',
			'tch_manage_catalogue' => 'Manage cake sizes, flavours and prices',
			'tch_manage_settings' => 'Change business settings',
			'tch_delete_orders'   => 'Permanently delete orders',
		);
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		self::create_tables();
		self::ensure_payment_transaction_reference_index();
		self::seed();
		self::roles();
		TCH_Catalogue::register_types();
		flush_rewrite_rules();
		TCH_Uploads::protect_dir();
		update_option( 'tch_db_version', TCH_DB_VERSION );
		if ( ! get_option( 'tch_settings' ) ) {
			update_option( 'tch_settings', TCH_Settings::defaults() );
		}
		// Customers may register; new accounts get the limited "Customer" role.
		update_option( 'users_can_register', 1 );
		update_option( 'default_role', 'tch_customer' );
	}

	/**
	 * Deactivation keeps data; only rewrite rules are refreshed.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Re-runs the schema when the version changes (after a plugin update).
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'tch_db_version' ) !== TCH_DB_VERSION ) {
			self::create_tables();
			self::roles();
			update_option( 'tch_db_version', TCH_DB_VERSION );
		}
		if ( 'ready' !== get_option( 'tch_payment_reference_index_status' ) ) {
			self::ensure_payment_transaction_reference_index();
		}
		if ( 'ready' !== get_option( 'tch_payment_reference_index_status' ) && is_admin() ) {
			add_action( 'admin_notices', array( __CLASS__, 'payment_reference_index_notice' ) );
		}
	}

	/**
	 * Creates/updates tables with dbDelta, then adds foreign keys.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = 'ENGINE=InnoDB ' . $wpdb->get_charset_collate();

		$sizes    = tch_table( 'cake_sizes' );
		$flavours = tch_table( 'flavours' );
		$orders   = tch_table( 'orders' );
		$items    = tch_table( 'order_items' );
		$custom   = tch_table( 'custom_requests' );
		$payments = tch_table( 'payments' );
		$plog     = tch_table( 'payment_log' );
		$history  = tch_table( 'status_history' );
		$messages = tch_table( 'messages' );

		// dbDelta needs: two spaces after PRIMARY KEY, one field per line, KEY not INDEX.
		$sql = array();

		$sql[] = "CREATE TABLE $sizes (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(40) NOT NULL,
  weight_label varchar(20) NOT NULL,
  price int(10) unsigned NOT NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_size_name (name)
) $c;";

		$sql[] = "CREATE TABLE $flavours (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(40) NOT NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_flavour_name (name)
) $c;";

		$sql[] = "CREATE TABLE $orders (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_ref varchar(24) NOT NULL,
	submission_key char(64) DEFAULT NULL,
  user_id bigint(20) unsigned DEFAULT NULL,
  order_type varchar(20) NOT NULL DEFAULT 'standard',
  customer_name varchar(120) NOT NULL,
  customer_phone varchar(30) NOT NULL,
  customer_phone_norm varchar(20) NOT NULL,
  customer_email varchar(120) DEFAULT NULL,
  fulfilment varchar(20) NOT NULL DEFAULT 'collection',
  delivery_address varchar(255) DEFAULT NULL,
  needed_by datetime DEFAULT NULL,
  total_amount int(10) unsigned NOT NULL,
  payment_plan varchar(10) NOT NULL DEFAULT 'full',
  amount_expected int(10) unsigned NOT NULL,
  amount_paid int(10) unsigned NOT NULL DEFAULT 0,
  order_status varchar(30) NOT NULL DEFAULT 'pending',
  payment_status varchar(30) NOT NULL DEFAULT 'unpaid',
  customer_notes text DEFAULT NULL,
  admin_notes text DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_order_ref (order_ref),
	UNIQUE KEY uq_order_submission_key (submission_key),
  KEY idx_order_user (user_id),
  KEY idx_order_status (order_status),
  KEY idx_order_payment (payment_status),
  KEY idx_order_phone (customer_phone_norm),
  KEY idx_order_created (created_at)
) $c;";

		$sql[] = "CREATE TABLE $items (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_id bigint(20) unsigned NOT NULL,
  product_id bigint(20) unsigned NOT NULL,
  product_name varchar(160) NOT NULL,
  size_id int(10) unsigned DEFAULT NULL,
  size_label varchar(80) DEFAULT NULL,
  flavour_id int(10) unsigned DEFAULT NULL,
  flavour_label varchar(40) DEFAULT NULL,
  quantity int(10) unsigned NOT NULL,
  unit_price int(10) unsigned NOT NULL,
  line_total int(10) unsigned NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_item_order (order_id),
  KEY idx_item_product (product_id),
  KEY idx_item_size (size_id),
  KEY idx_item_flavour (flavour_id)
) $c;";

		$sql[] = "CREATE TABLE $custom (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_id bigint(20) unsigned NOT NULL,
  design_description text NOT NULL,
  inscription varchar(150) DEFAULT NULL,
  event_date datetime NOT NULL,
  reference_file varchar(255) DEFAULT NULL,
  special_instructions text DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_custom_order (order_id)
) $c;";

		$sql[] = "CREATE TABLE $payments (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_id bigint(20) unsigned NOT NULL,
  payment_type varchar(20) NOT NULL,
  method varchar(30) NOT NULL DEFAULT 'mobile_money',
  amount int(10) unsigned NOT NULL,
  transaction_ref varchar(80) DEFAULT NULL,
  payer_phone varchar(30) DEFAULT NULL,
  evidence_file varchar(255) DEFAULT NULL,
  status varchar(30) NOT NULL DEFAULT 'pending_verification',
  source varchar(20) NOT NULL DEFAULT 'customer',
  submitted_by bigint(20) unsigned DEFAULT NULL,
  submitted_at datetime NOT NULL,
  verified_by bigint(20) unsigned DEFAULT NULL,
  verified_at datetime DEFAULT NULL,
  admin_note varchar(255) DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY idx_payment_order (order_id),
  KEY idx_payment_status (status),
  KEY idx_payment_txn (transaction_ref)
) $c;";

		$sql[] = "CREATE TABLE $plog (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  payment_id bigint(20) unsigned NOT NULL,
  action varchar(30) NOT NULL,
  old_status varchar(30) DEFAULT NULL,
  new_status varchar(30) NOT NULL,
  actor_id bigint(20) unsigned DEFAULT NULL,
  note varchar(255) DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_plog_payment (payment_id)
) $c;";

		$sql[] = "CREATE TABLE $history (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_id bigint(20) unsigned NOT NULL,
  old_status varchar(30) DEFAULT NULL,
  new_status varchar(30) NOT NULL,
  changed_by bigint(20) unsigned DEFAULT NULL,
  note varchar(255) DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_history_order (order_id)
) $c;";

		$sql[] = "CREATE TABLE $messages (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  full_name varchar(120) NOT NULL,
  phone varchar(30) DEFAULT NULL,
  email varchar(120) DEFAULT NULL,
  subject varchar(150) NOT NULL,
  message text NOT NULL,
  is_read tinyint(1) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_msg_read (is_read)
) $c;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		// Referential integrity (dbDelta cannot create foreign keys).
		self::add_fk( $items, 'fk_tch_item_order', 'order_id', $orders, 'id', 'CASCADE' );
		self::add_fk( $items, 'fk_tch_item_size', 'size_id', $sizes, 'id', 'SET NULL' );
		self::add_fk( $items, 'fk_tch_item_flavour', 'flavour_id', $flavours, 'id', 'SET NULL' );
		self::add_fk( $custom, 'fk_tch_custom_order', 'order_id', $orders, 'id', 'CASCADE' );
		self::add_fk( $payments, 'fk_tch_payment_order', 'order_id', $orders, 'id', 'CASCADE' );
		self::add_fk( $plog, 'fk_tch_plog_payment', 'payment_id', $payments, 'id', 'CASCADE' );
		self::add_fk( $history, 'fk_tch_history_order', 'order_id', $orders, 'id', 'CASCADE' );
	}

	/**
	 * Adds a unique transaction reference only when existing records permit it.
	 *
	 * @return bool Whether the unique index is installed.
	 */
	private static function ensure_payment_transaction_reference_index() {
		global $wpdb;
		$table   = tch_table( 'payments' );
		$indexes = $wpdb->get_results( "SHOW INDEX FROM `$table`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) {
			self::set_payment_reference_index_status( 'failed' );
			return false;
		}
		$has_unique = false;
		$has_legacy = false;
		foreach ( $indexes as $index ) {
			if ( 'transaction_ref' !== $index['Column_name'] ) {
				continue;
			}
			if ( 'uq_tch_transaction_ref' === $index['Key_name'] && 0 === (int) $index['Non_unique'] ) {
				$has_unique = true;
			}
			if ( 'idx_payment_txn' === $index['Key_name'] ) {
				$has_legacy = true;
			}
		}
		if ( $has_unique ) {
			self::set_payment_reference_index_status( 'ready' );
			return true;
		}

		$duplicate_groups = (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT transaction_ref FROM `$table` WHERE transaction_ref IS NOT NULL AND transaction_ref <> '' GROUP BY transaction_ref HAVING COUNT(*) > 1) AS duplicate_refs" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error || $duplicate_groups ) {
			self::set_payment_reference_index_status( $wpdb->last_error ? 'failed' : 'duplicates' );
			return false;
		}

		$drop_legacy = $has_legacy ? 'DROP INDEX `idx_payment_txn`, ' : '';
		$result      = $wpdb->query( "ALTER TABLE `$table` $drop_legacy ADD UNIQUE KEY `uq_tch_transaction_ref` (`transaction_ref`)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( false === $result ) {
			self::set_payment_reference_index_status( 'failed' );
			return false;
		}
		self::set_payment_reference_index_status( 'ready' );
		return true;
	}

	/**
	 * Stores migration status and logs only a non-sensitive fixed message.
	 *
	 * @param string $status ready|duplicates|failed.
	 */
	private static function set_payment_reference_index_status( $status ) {
		$previous = get_option( 'tch_payment_reference_index_status' );
		if ( $previous === $status ) {
			return;
		}
		update_option( 'tch_payment_reference_index_status', $status, false );
		if ( 'ready' !== $status ) {
			error_log( 'TCH: payment reference uniqueness migration is not complete; review the administrator notice.' );
		}
	}

	/**
	 * Shows a safe warning when the unique reference constraint cannot be installed.
	 */
	public static function payment_reference_index_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status = get_option( 'tch_payment_reference_index_status' );
		if ( 'duplicates' === $status ) {
			$message = __( 'The payment reference unique index was not installed because existing duplicate references need manual review. No payment records were changed.', 'tch' );
		} else {
			$message = __( 'The payment reference unique index could not be installed. Database write permissions or the schema should be reviewed before accepting payments.', 'tch' );
		}
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Adds a foreign key if it does not already exist.
	 * All identifiers are internal constants (never user input).
	 *
	 * @param string $table     Child table.
	 * @param string $name      Constraint name.
	 * @param string $column    Child column.
	 * @param string $ref_table Parent table.
	 * @param string $ref_col   Parent column.
	 * @param string $on_delete CASCADE|SET NULL|RESTRICT.
	 */
	private static function add_fk( $table, $name, $column, $ref_table, $ref_col, $on_delete ) {
		global $wpdb;
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s AND CONSTRAINT_TYPE = %s',
				$table,
				$name,
				'FOREIGN KEY'
			)
		);
		if ( (int) $exists > 0 ) {
			return;
		}
		$on_delete = in_array( $on_delete, array( 'CASCADE', 'SET NULL', 'RESTRICT' ), true ) ? $on_delete : 'RESTRICT';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers are fixed internal names.
		$wpdb->query( "ALTER TABLE `$table` ADD CONSTRAINT `$name` FOREIGN KEY (`$column`) REFERENCES `$ref_table` (`$ref_col`) ON DELETE $on_delete ON UPDATE CASCADE" );
	}

	/**
	 * Seeds the verified price list, only when the tables are empty.
	 */
	public static function seed() {
		global $wpdb;
		$sizes = tch_table( 'cake_sizes' );
		if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $sizes" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = array(
				array( 'Bento', '500g', 35000, 1 ),
				array( 'Medium', '1kg', 50000, 2 ),
				array( 'Large', '1.5kg', 75000, 3 ),
				array( 'Extra-large', '2kg', 100000, 4 ),
			);
			foreach ( $rows as $r ) {
				$wpdb->insert(
					$sizes,
					array( 'name' => $r[0], 'weight_label' => $r[1], 'price' => $r[2], 'sort_order' => $r[3], 'is_active' => 1, 'updated_at' => current_time( 'mysql' ) ),
					array( '%s', '%s', '%d', '%d', '%d', '%s' )
				);
			}
		}
		$flavours = tch_table( 'flavours' );
		if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $flavours" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( array( 'Vanilla', 'Strawberry', 'Orange', 'Chocolate', 'Mango' ) as $i => $name ) {
				$wpdb->insert( $flavours, array( 'name' => $name, 'sort_order' => $i + 1, 'is_active' => 1 ), array( '%s', '%d', '%d' ) );
			}
		}
	}

	/**
	 * Roles: Customer (front-end only) and Cake Shop Manager (day-to-day staff).
	 * Administrators receive every custom capability.
	 */
	public static function roles() {
		add_role(
			'tch_customer',
			__( 'Cake Customer', 'tch' ),
			array( 'read' => true )
		);

		$manager_caps = array(
			'read'                 => true,
			'upload_files'         => true,
			'edit_posts'           => true,
			'edit_published_posts' => true,
			'publish_posts'        => true,
			'delete_posts'         => true,
			'delete_published_posts' => true,
			'edit_others_posts'    => true,
			'edit_pages'           => true,
			'edit_published_pages' => true,
			'edit_others_pages'    => true,
			'publish_pages'        => true,
			'manage_categories'    => true,
			'moderate_comments'    => true,
			'tch_view_dashboard'   => true,
			'tch_manage_orders'    => true,
			'tch_verify_payments'  => true,
			'tch_manage_catalogue' => true,
		);
		remove_role( 'tch_manager' );
		add_role( 'tch_manager', __( 'Cake Shop Manager', 'tch' ), $manager_caps );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array_keys( self::caps() ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}
}
