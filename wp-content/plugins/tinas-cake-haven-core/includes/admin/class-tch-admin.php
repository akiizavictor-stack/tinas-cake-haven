<?php
/**
 * wp-admin screens: dashboard, orders, payment verification, sizes & flavours,
 * messages and business settings. Every action checks a capability AND a nonce.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
class TCH_Admin {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_widget' ) );
		foreach ( array( 'update_status', 'update_admin', 'decide_payment', 'record_payment', 'delete_order', 'save_size', 'delete_size', 'save_flavour', 'delete_flavour', 'message' ) as $a ) {
			add_action( 'admin_post_tch_' . $a, array( __CLASS__, 'action_' . $a ) );
		}
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		$pending = TCH_Payments::pending_queue();
		$bubble  = $pending ? ' <span class="awaiting-mod">' . count( $pending ) . '</span>' : '';
		add_menu_page( __( 'Cake Orders', 'tch' ), __( 'Cake Orders', 'tch' ) . $bubble, 'tch_view_dashboard', 'tch-dashboard', array( __CLASS__, 'page_dashboard' ), 'dashicons-store', 25 );
		add_submenu_page( 'tch-dashboard', __( 'Business dashboard', 'tch' ), __( 'Dashboard', 'tch' ), 'tch_view_dashboard', 'tch-dashboard', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'tch-dashboard', __( 'Orders', 'tch' ), __( 'All orders', 'tch' ), 'tch_manage_orders', 'tch-orders', array( __CLASS__, 'page_orders' ) );
		add_submenu_page( 'tch-dashboard', __( 'Payments to verify', 'tch' ), __( 'Verify payments', 'tch' ) . $bubble, 'tch_verify_payments', 'tch-payments', array( __CLASS__, 'page_payments' ) );
		add_submenu_page( 'tch-dashboard', __( 'Sizes & flavours', 'tch' ), __( 'Sizes & flavours', 'tch' ), 'tch_manage_catalogue', 'tch-catalogue', array( __CLASS__, 'page_catalogue' ) );
		add_submenu_page( 'tch-dashboard', __( 'Messages', 'tch' ), __( 'Messages', 'tch' ), 'tch_manage_orders', 'tch-messages', array( __CLASS__, 'page_messages' ) );
		add_submenu_page( 'tch-dashboard', __( 'Business settings', 'tch' ), __( 'Business settings', 'tch' ), 'tch_manage_settings', 'tch-settings', array( __CLASS__, 'page_settings' ) );
	}

	/**
	 * CSS.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		wp_enqueue_style( 'tch-admin', TCH_URL . 'assets/css/admin.css', array(), TCH_VERSION );
	}

	/**
	 * Settings API registration.
	 */
	public static function register_settings() {
		register_setting(
			'tch_settings_group',
			'tch_settings',
			array( 'type' => 'array', 'sanitize_callback' => array( 'TCH_Settings', 'sanitize' ) )
		);
	}

	/**
	 * Admin notice from the query string (after redirects).
	 */
	private static function notice() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_GET['tch_msg'] ) ) {
			$type = ! empty( $_GET['tch_err'] ) ? 'error' : 'success';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['tch_msg'] ) ) ) . '</p></div>';
		}
		// phpcs:enable
	}

	/**
	 * Redirect back with a message.
	 *
	 * @param string $url Url.
	 * @param string $msg Message.
	 * @param bool   $err Error.
	 */
	private static function back( $url, $msg, $err = false ) {
		wp_safe_redirect( add_query_arg( array_filter( array( 'tch_msg' => rawurlencode( $msg ), 'tch_err' => $err ? 1 : 0 ) ), $url ) );
		exit;
	}

	/**
	 * Capability + nonce guard for admin-post actions.
	 *
	 * @param string $cap    Capability.
	 * @param string $action Nonce action.
	 */
	private static function guard( $cap, $action ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tch' ), 403 );
		}
		check_admin_referer( $action );
	}

	/* ------------------------------------------------------------------
	 * Dashboard
	 * ------------------------------------------------------------------ */

	/**
	 * Business dashboard.
	 */
	public static function page_dashboard() {
		$s       = TCH_Orders::stats();
		$ostatus = tch_order_statuses();
		$pstatus = tch_order_payment_statuses();
		$link    = static function ( $args ) {
			return esc_url( add_query_arg( $args, admin_url( 'admin.php?page=tch-orders' ) ) );
		};
		$tiles = array(
			array( __( 'Total orders', 'tch' ), $s['total'], $link( array() ) ),
			array( __( 'New (last 48 hours)', 'tch' ), $s['new'], $link( array() ) ),
			array( __( 'Pending', 'tch' ), $s['by_status']['pending'], $link( array( 'status' => 'pending' ) ) ),
			array( __( 'Payments awaiting verification', 'tch' ), $s['pending_pay'], esc_url( admin_url( 'admin.php?page=tch-payments' ) ), 'alert' ),
			array( __( 'Confirmed', 'tch' ), $s['by_status']['confirmed'], $link( array( 'status' => 'confirmed' ) ) ),
			array( __( 'Preparing', 'tch' ), $s['by_status']['preparing'], $link( array( 'status' => 'preparing' ) ) ),
			array( __( 'Completed', 'tch' ), $s['by_status']['completed'], $link( array( 'status' => 'completed' ) ) ),
			array( __( 'Cancelled', 'tch' ), $s['by_status']['cancelled'], $link( array( 'status' => 'cancelled' ) ) ),
		);
		?>
		<div class="wrap tch-wrap">
			<h1><?php esc_html_e( 'Business dashboard', 'tch' ); ?></h1>
			<?php self::notice(); ?>
			<div class="tch-money-tiles">
				<div class="tch-tile tch-tile--money"><span><?php esc_html_e( 'Total sales (verified payments)', 'tch' ); ?></span><strong><?php echo esc_html( tch_money( $s['verified_sales'] ) ); ?></strong></div>
				<div class="tch-tile tch-tile--money tch-tile--warn"><span><?php esc_html_e( 'Outstanding balances (open orders)', 'tch' ); ?></span><strong><?php echo esc_html( tch_money( $s['outstanding'] ) ); ?></strong></div>
			</div>
			<div class="tch-tiles">
				<?php foreach ( $tiles as $t ) : ?>
					<a class="tch-tile <?php echo isset( $t[3] ) && $t[2] && $t[1] ? 'tch-tile--alert' : ''; ?>" href="<?php echo $t[2]; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>"><span><?php echo esc_html( $t[0] ); ?></span><strong><?php echo (int) $t[1]; ?></strong></a>
				<?php endforeach; ?>
			</div>
			<p class="description"><?php esc_html_e( 'All figures come from real orders and payments in the database. Sales count only payments you have verified.', 'tch' ); ?></p>
			<div class="tch-cols">
				<div class="tch-box">
					<h2><?php esc_html_e( 'Recent orders', 'tch' ); ?></h2>
					<?php if ( ! $s['recent'] ) : ?>
						<p><?php esc_html_e( 'No orders yet.', 'tch' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead><tr><th><?php esc_html_e( 'Order', 'tch' ); ?></th><th><?php esc_html_e( 'Customer', 'tch' ); ?></th><th><?php esc_html_e( 'Total', 'tch' ); ?></th><th><?php esc_html_e( 'Status', 'tch' ); ?></th><th><?php esc_html_e( 'Payment', 'tch' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( $s['recent'] as $o ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=tch-orders&view=' . (int) $o->id ) ); ?>"><?php echo esc_html( $o->order_ref ); ?></a><br><small><?php echo esc_html( tch_date( $o->created_at ) ); ?></small></td>
									<td><?php echo esc_html( $o->customer_name ); ?></td>
									<td><?php echo esc_html( tch_money( $o->total_amount ) ); ?></td>
									<td><?php echo tch_badge( $o->order_status, $ostatus ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
									<td><?php echo tch_badge( $o->payment_status, $pstatus ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
				<div class="tch-box">
					<h2><?php esc_html_e( 'Popular products', 'tch' ); ?></h2>
					<?php if ( ! $s['popular'] ) : ?>
						<p><?php esc_html_e( 'This list fills in as orders arrive.', 'tch' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead><tr><th><?php esc_html_e( 'Product', 'tch' ); ?></th><th><?php esc_html_e( 'Orders', 'tch' ); ?></th><th><?php esc_html_e( 'Pieces', 'tch' ); ?></th><th><?php esc_html_e( 'Value ordered', 'tch' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( $s['popular'] as $p ) : ?>
								<tr><td><?php echo esc_html( $p->product_name ); ?></td><td><?php echo (int) $p->orders; ?></td><td><?php echo (int) $p->qty; ?></td><td><?php echo esc_html( tch_money( $p->revenue ) ); ?></td></tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
					<h2><?php esc_html_e( 'Quick links', 'tch' ); ?></h2>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=tch-payments' ) ); ?>"><?php esc_html_e( 'Verify payments', 'tch' ); ?></a>
						<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . TCH_Catalogue::PT ) ); ?>"><?php esc_html_e( 'Edit products', 'tch' ); ?></a>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tch-catalogue' ) ); ?>"><?php esc_html_e( 'Change cake prices', 'tch' ); ?></a>
						<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'Write a blog post', 'tch' ); ?></a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Small widget on the main WordPress dashboard.
	 */
	public static function dashboard_widget() {
		if ( ! current_user_can( 'tch_view_dashboard' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'tch_widget',
			__( "Tina's Cake Haven – today", 'tch' ),
			static function () {
				$s = TCH_Orders::stats();
				echo '<ul>';
				echo '<li>' . esc_html__( 'New orders (48h):', 'tch' ) . ' <strong>' . (int) $s['new'] . '</strong></li>';
				echo '<li>' . esc_html__( 'Payments awaiting verification:', 'tch' ) . ' <strong>' . (int) $s['pending_pay'] . '</strong></li>';
				echo '<li>' . esc_html__( 'Verified sales:', 'tch' ) . ' <strong>' . esc_html( tch_money( $s['verified_sales'] ) ) . '</strong></li>';
				echo '</ul><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=tch-dashboard' ) ) . '">' . esc_html__( 'Open business dashboard', 'tch' ) . '</a></p>';
			}
		);
	}

	/* ------------------------------------------------------------------
	 * Orders
	 * ------------------------------------------------------------------ */

	/**
	 * Orders list or single order.
	 */
	public static function page_orders() {
		$view = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $view ) {
			self::page_order( $view );
			return;
		}
		$table = new TCH_Orders_Table();
		$table->prepare_items();
		?>
		<div class="wrap tch-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Orders', 'tch' ); ?></h1>
			<?php self::notice(); ?>
			<?php $table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="tch-orders">
				<?php
				if ( isset( $_GET['status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					echo '<input type="hidden" name="status" value="' . esc_attr( sanitize_key( $_GET['status'] ) ) . '">'; // phpcs:ignore WordPress.Security.NonceVerification
				}
				$table->search_box( __( 'Search ref, name or phone', 'tch' ), 'tch-order-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Single order screen.
	 *
	 * @param int $id Order id.
	 */
	private static function page_order( $id ) {
		$o = TCH_Orders::get( $id );
		if ( ! $o ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Order not found', 'tch' ) . '</h1></div>';
			return;
		}
		$items    = TCH_Orders::items( $id );
		$custom   = TCH_Orders::custom_request( $id );
		$history  = TCH_Orders::history( $id );
		$payments = TCH_Payments::for_order( $id );
		$plog     = TCH_Payments::log_for_order( $id );
		$ostatus  = tch_order_statuses();
		$pstatus  = tch_order_payment_statuses();
		$rstatus  = tch_payment_record_statuses();
		$out      = TCH_Payments::outstanding( $o );
		$wa_text  = sprintf( 'Hello %s, this is Tina\'s Cake Haven about your order %s. ', $o->customer_name, $o->order_ref );
		?>
		<div class="wrap tch-wrap">
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=tch-orders' ) ); ?>">← <?php esc_html_e( 'All orders', 'tch' ); ?></a></p>
			<h1><?php echo esc_html( $o->order_ref ); ?> <?php echo tch_badge( $o->order_status, $ostatus ) . ' ' . tch_badge( $o->payment_status, $pstatus ); // phpcs:ignore WordPress.Security.EscapeOutput ?></h1>
			<?php self::notice(); ?>
			<div class="tch-cols">
				<div>
					<div class="tch-box">
						<h2><?php esc_html_e( 'Customer', 'tch' ); ?></h2>
						<p><strong><?php echo esc_html( $o->customer_name ); ?></strong><br>
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $o->customer_phone ) ); ?>"><?php echo esc_html( $o->customer_phone ); ?></a>
							· <a href="<?php echo esc_url( tch_whatsapp_to( $o->customer_phone, $wa_text ) ); ?>" target="_blank" rel="noopener">WhatsApp</a>
							<?php if ( $o->customer_email ) : ?>
								<br><a href="mailto:<?php echo esc_attr( $o->customer_email ); ?>"><?php echo esc_html( $o->customer_email ); ?></a>
							<?php endif; ?>
							<br><small><?php echo $o->user_id ? esc_html__( 'Registered customer account', 'tch' ) : esc_html__( 'Guest order', 'tch' ); ?></small></p>
						<p><strong><?php esc_html_e( 'Needed by:', 'tch' ); ?></strong> <?php echo esc_html( tch_date( $o->needed_by ) ); ?><br>
							<strong><?php esc_html_e( 'Fulfilment:', 'tch' ); ?></strong> <?php echo 'delivery' === $o->fulfilment ? esc_html__( 'Delivery –', 'tch' ) . ' ' . esc_html( $o->delivery_address ) : esc_html__( 'Collection', 'tch' ); ?><br>
							<strong><?php esc_html_e( 'Placed:', 'tch' ); ?></strong> <?php echo esc_html( tch_date( $o->created_at ) ); ?></p>
						<?php if ( $o->customer_notes ) : ?>
							<p><strong><?php esc_html_e( 'Customer notes:', 'tch' ); ?></strong><br><?php echo nl2br( esc_html( $o->customer_notes ) ); ?></p>
						<?php endif; ?>
					</div>

					<div class="tch-box">
						<h2><?php esc_html_e( 'Items', 'tch' ); ?></h2>
						<table class="widefat striped">
							<thead><tr><th><?php esc_html_e( 'Product', 'tch' ); ?></th><th><?php esc_html_e( 'Options', 'tch' ); ?></th><th><?php esc_html_e( 'Qty', 'tch' ); ?></th><th><?php esc_html_e( 'Unit', 'tch' ); ?></th><th><?php esc_html_e( 'Line total', 'tch' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( $items as $it ) : ?>
								<tr><td><?php echo esc_html( $it->product_name ); ?></td><td><?php echo esc_html( implode( ' · ', array_filter( array( $it->size_label, $it->flavour_label ) ) ) ); ?></td><td><?php echo (int) $it->quantity; ?></td><td><?php echo esc_html( tch_money( $it->unit_price ) ); ?></td><td><?php echo esc_html( tch_money( $it->line_total ) ); ?></td></tr>
							<?php endforeach; ?>
							</tbody>
							<tfoot>
								<tr><th colspan="4"><?php esc_html_e( 'Order total', 'tch' ); ?></th><th><?php echo esc_html( tch_money( $o->total_amount ) ); ?></th></tr>
								<tr><th colspan="4"><?php echo 'deposit' === $o->payment_plan ? esc_html__( 'Expected first payment (deposit)', 'tch' ) : esc_html__( 'Expected payment (full)', 'tch' ); ?></th><th><?php echo esc_html( tch_money( $o->amount_expected ) ); ?></th></tr>
								<tr><th colspan="4"><?php esc_html_e( 'Verified payments', 'tch' ); ?></th><th><?php echo esc_html( tch_money( $o->amount_paid ) ); ?></th></tr>
								<tr><th colspan="4"><?php esc_html_e( 'Outstanding balance', 'tch' ); ?></th><th><?php echo esc_html( tch_money( $out ) ); ?></th></tr>
							</tfoot>
						</table>
					</div>

					<?php if ( $custom ) : ?>
						<div class="tch-box">
							<h2><?php esc_html_e( 'Customized cake request', 'tch' ); ?></h2>
							<p><strong><?php esc_html_e( 'Event date:', 'tch' ); ?></strong> <?php echo esc_html( tch_date( $custom->event_date ) ); ?></p>
							<p><strong><?php esc_html_e( 'Design:', 'tch' ); ?></strong><br><?php echo nl2br( esc_html( $custom->design_description ) ); ?></p>
							<?php if ( $custom->inscription ) : ?>
								<p><strong><?php esc_html_e( 'Message on cake:', 'tch' ); ?></strong> <?php echo esc_html( $custom->inscription ); ?></p>
							<?php endif; ?>
							<?php if ( $custom->special_instructions ) : ?>
								<p><strong><?php esc_html_e( 'Instructions:', 'tch' ); ?></strong><br><?php echo nl2br( esc_html( $custom->special_instructions ) ); ?></p>
							<?php endif; ?>
							<?php if ( $custom->reference_file ) : ?>
								<p><a class="button" href="<?php echo esc_url( TCH_Uploads::view_url( $custom->reference_file ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View reference photo', 'tch' ); ?></a></p>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<div class="tch-box">
						<h2><?php esc_html_e( 'Payments', 'tch' ); ?></h2>
						<?php if ( ! $payments ) : ?>
							<p><?php esc_html_e( 'No payment has been submitted yet.', 'tch' ); ?></p>
						<?php endif; ?>
						<?php foreach ( $payments as $p ) : ?>
							<div class="tch-payment tch-payment--<?php echo esc_attr( $p->status ); ?>">
								<p><strong><?php echo esc_html( tch_money( $p->amount ) ); ?></strong> <?php echo tch_badge( $p->status, $rstatus ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<small><?php echo esc_html( ucfirst( $p->payment_type ) . ' · ' . ( 'cash' === $p->method ? __( 'Cash', 'tch' ) : __( 'Mobile Money', 'tch' ) ) . ' · ' . ( 'staff' === $p->source ? __( 'recorded by staff', 'tch' ) : __( 'submitted by customer', 'tch' ) ) ); ?></small></p>
								<p><?php esc_html_e( 'Transaction ID:', 'tch' ); ?> <code><?php echo esc_html( $p->transaction_ref ? $p->transaction_ref : '—' ); ?></code>
									<?php if ( $p->payer_phone ) : ?>
										· <?php esc_html_e( 'Paid from:', 'tch' ); ?> <?php echo esc_html( $p->payer_phone ); ?>
									<?php endif; ?>
									· <?php echo esc_html( tch_date( $p->submitted_at ) ); ?></p>
								<?php if ( $p->evidence_file ) : ?>
									<p><a class="button button-small" href="<?php echo esc_url( TCH_Uploads::view_url( $p->evidence_file ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View screenshot (supporting only)', 'tch' ); ?></a></p>
								<?php endif; ?>
								<?php if ( $p->verified_by ) : ?>
									<p><small><?php echo esc_html( sprintf( /* translators: 1: status 2: user 3: date */ __( '%1$s by %2$s on %3$s', 'tch' ), $rstatus[ $p->status ] ?? $p->status, tch_user_label( $p->verified_by ), tch_date( $p->verified_at ) ) ); ?><?php echo $p->admin_note ? ' – ' . esc_html( $p->admin_note ) : ''; ?></small></p>
								<?php endif; ?>
								<?php if ( 'pending_verification' === $p->status && current_user_can( 'tch_verify_payments' ) ) : ?>
									<?php self::decision_form( $p, admin_url( 'admin.php?page=tch-orders&view=' . $id ) ); ?>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>

						<?php if ( $out > 0 && current_user_can( 'tch_verify_payments' ) && 'cancelled' !== $o->order_status ) : ?>
							<details class="tch-details-box">
								<summary><?php esc_html_e( 'Record a payment received directly (e.g. balance paid on collection)', 'tch' ); ?></summary>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'tch_record_payment_' . $id ); ?>
									<input type="hidden" name="action" value="tch_record_payment">
									<input type="hidden" name="order_id" value="<?php echo (int) $id; ?>">
									<p><label><?php esc_html_e( 'Amount (UGX)', 'tch' ); ?> <input type="number" name="amount" min="1" max="<?php echo (int) $out; ?>" value="<?php echo (int) $out; ?>" required></label>
										<label><?php esc_html_e( 'Method', 'tch' ); ?> <select name="method"><option value="mobile_money"><?php esc_html_e( 'Mobile Money', 'tch' ); ?></option><option value="cash"><?php esc_html_e( 'Cash', 'tch' ); ?></option></select></label>
										<label><?php esc_html_e( 'Reference', 'tch' ); ?> <input type="text" name="reference" maxlength="40"></label></p>
									<p><label><?php esc_html_e( 'Note', 'tch' ); ?> <input type="text" name="note" maxlength="200" class="regular-text"></label></p>
									<p><button class="button button-primary"><?php esc_html_e( 'I have received this money – record it', 'tch' ); ?></button></p>
								</form>
							</details>
						<?php endif; ?>

						<?php if ( $plog ) : ?>
							<h3><?php esc_html_e( 'Verification history', 'tch' ); ?></h3>
							<table class="widefat striped">
								<thead><tr><th><?php esc_html_e( 'When', 'tch' ); ?></th><th><?php esc_html_e( 'Payment', 'tch' ); ?></th><th><?php esc_html_e( 'Action', 'tch' ); ?></th><th><?php esc_html_e( 'By', 'tch' ); ?></th><th><?php esc_html_e( 'Note', 'tch' ); ?></th></tr></thead>
								<tbody>
								<?php foreach ( $plog as $l ) : ?>
									<tr><td><?php echo esc_html( tch_date( $l->created_at ) ); ?></td><td><?php echo esc_html( tch_money( $l->amount ) . ( $l->transaction_ref ? ' · ' . $l->transaction_ref : '' ) ); ?></td><td><?php echo esc_html( ucfirst( $l->action ) ); ?></td><td><?php echo esc_html( tch_user_label( $l->actor_id ) ); ?></td><td><?php echo esc_html( (string) $l->note ); ?></td></tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>

				<div>
					<div class="tch-box">
						<h2><?php esc_html_e( 'Update order status', 'tch' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Order confirmation is separate from payment verification. Confirm an order only when you are ready to make it.', 'tch' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'tch_update_status_' . $id ); ?>
							<input type="hidden" name="action" value="tch_update_status">
							<input type="hidden" name="order_id" value="<?php echo (int) $id; ?>">
							<p><select name="status">
								<?php foreach ( $ostatus as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o->order_status, $k ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select></p>
							<p><label><?php esc_html_e( 'Note (the customer can see this)', 'tch' ); ?><br><input type="text" name="note" maxlength="200" class="widefat"></label></p>
							<p><label><input type="checkbox" name="notify" value="1" checked> <?php esc_html_e( 'Email the customer (if they gave an email)', 'tch' ); ?></label></p>
							<p><button class="button button-primary"><?php esc_html_e( 'Update status', 'tch' ); ?></button>
								<a class="button" href="<?php echo esc_url( tch_whatsapp_to( $o->customer_phone, $wa_text . 'Status: ' . $ostatus[ $o->order_status ] . '.' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Message on WhatsApp', 'tch' ); ?></a></p>
						</form>
					</div>

					<div class="tch-box">
						<h2><?php esc_html_e( 'Status history', 'tch' ); ?></h2>
						<ul class="tch-history">
							<?php foreach ( array_reverse( $history ) as $h ) : ?>
								<li><strong><?php echo esc_html( $ostatus[ $h->new_status ] ?? $h->new_status ); ?></strong><br>
									<small><?php echo esc_html( tch_date( $h->created_at ) . ' · ' . tch_user_label( $h->changed_by ) ); ?></small>
									<?php echo $h->note ? '<br><em>' . esc_html( $h->note ) . '</em>' : ''; ?></li>
							<?php endforeach; ?>
						</ul>
					</div>

					<div class="tch-box">
						<h2><?php esc_html_e( 'Staff notes & agreed price', 'tch' ); ?></h2>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'tch_update_admin_' . $id ); ?>
							<input type="hidden" name="action" value="tch_update_admin">
							<input type="hidden" name="order_id" value="<?php echo (int) $id; ?>">
							<p><label><?php esc_html_e( 'Private notes (not shown to the customer)', 'tch' ); ?><br><textarea name="admin_notes" rows="4" class="widefat"><?php echo esc_textarea( (string) $o->admin_notes ); ?></textarea></label></p>
							<p><label><?php esc_html_e( 'Agreed order total (UGX)', 'tch' ); ?><br><input type="number" name="total_amount" min="1" value="<?php echo (int) $o->total_amount; ?>"></label><br>
								<small><?php esc_html_e( 'Change only if you agreed a different price with the customer (e.g. extra decoration). The change is logged.', 'tch' ); ?></small></p>
							<p><button class="button"><?php esc_html_e( 'Save', 'tch' ); ?></button></p>
						</form>
					</div>

					<?php if ( current_user_can( 'tch_delete_orders' ) ) : ?>
						<div class="tch-box tch-box--danger">
							<h2><?php esc_html_e( 'Delete order', 'tch' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Permanently removes the order, its items, payments, history and uploaded photos. Prefer "Cancelled" for real orders.', 'tch' ); ?></p>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this order permanently? This cannot be undone.', 'tch' ) ); ?>');">
								<?php wp_nonce_field( 'tch_delete_order_' . $id ); ?>
								<input type="hidden" name="action" value="tch_delete_order">
								<input type="hidden" name="order_id" value="<?php echo (int) $id; ?>">
								<p><button class="button button-link-delete"><?php esc_html_e( 'Delete permanently', 'tch' ); ?></button></p>
							</form>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Verify / reject buttons.
	 *
	 * @param object $p        Payment.
	 * @param string $redirect Return URL.
	 */
	private static function decision_form( $p, $redirect ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tch-decision">
			<?php wp_nonce_field( 'tch_decide_payment_' . $p->id ); ?>
			<input type="hidden" name="action" value="tch_decide_payment">
			<input type="hidden" name="payment_id" value="<?php echo (int) $p->id; ?>">
			<input type="hidden" name="redirect" value="<?php echo esc_url( $redirect ); ?>">
			<p class="tch-warning"><?php esc_html_e( 'Open your Mobile Money account / statement and confirm this transaction ID and amount were really received before verifying. A screenshot alone is not proof.', 'tch' ); ?></p>
			<p><input type="text" name="note" maxlength="200" class="regular-text" placeholder="<?php esc_attr_e( 'Note (required when rejecting – the customer will see it)', 'tch' ); ?>"></p>
			<p><button class="button button-primary" name="decision" value="verified"><?php esc_html_e( '✓ I checked my account – mark as verified', 'tch' ); ?></button>
				<button class="button button-link-delete" name="decision" value="rejected"><?php esc_html_e( '✗ Reject', 'tch' ); ?></button></p>
		</form>
		<?php
	}

	/**
	 * Status update.
	 */
	public static function action_update_status() {
		$id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		self::guard( 'tch_manage_orders', 'tch_update_status_' . $id );
		$status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
		$note   = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
		$res    = TCH_Orders::set_status( $id, $status, get_current_user_id(), $note, ! empty( $_POST['notify'] ) );
		$url    = admin_url( 'admin.php?page=tch-orders&view=' . $id );
		if ( is_wp_error( $res ) ) {
			self::back( $url, $res->get_error_message(), true );
		}
		self::back( $url, __( 'Order status updated.', 'tch' ) );
	}

	/**
	 * Notes / agreed total.
	 */
	public static function action_update_admin() {
		$id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		self::guard( 'tch_manage_orders', 'tch_update_admin_' . $id );
		$notes = isset( $_POST['admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ) ) : '';
		$total = isset( $_POST['total_amount'] ) ? absint( $_POST['total_amount'] ) : null;
		$result = TCH_Orders::update_admin_fields( $id, $notes, $total, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			self::back( admin_url( 'admin.php?page=tch-orders&view=' . $id ), $result->get_error_message(), true );
		}
		self::back( admin_url( 'admin.php?page=tch-orders&view=' . $id ), __( 'Saved.', 'tch' ) );
	}

	/**
	 * Verify / reject a payment.
	 */
	public static function action_decide_payment() {
		$pid = isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0;
		self::guard( 'tch_verify_payments', 'tch_decide_payment_' . $pid );
		$decision = isset( $_POST['decision'] ) ? sanitize_key( $_POST['decision'] ) : '';
		$note     = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
		$redirect = isset( $_POST['redirect'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['redirect'] ) ), admin_url( 'admin.php?page=tch-payments' ) ) : admin_url( 'admin.php?page=tch-payments' );
		$res      = TCH_Payments::decide( $pid, $decision, $note );
		if ( is_wp_error( $res ) ) {
			self::back( $redirect, $res->get_error_message(), true );
		}
		self::back( $redirect, 'verified' === $decision ? __( 'Payment marked as verified.', 'tch' ) : __( 'Payment rejected.', 'tch' ) );
	}

	/**
	 * Staff-recorded payment.
	 */
	public static function action_record_payment() {
		$id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		self::guard( 'tch_verify_payments', 'tch_record_payment_' . $id );
		$res = TCH_Payments::record_by_staff(
			$id,
			isset( $_POST['amount'] ) && is_scalar( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '',
			isset( $_POST['method'] ) ? sanitize_key( $_POST['method'] ) : 'mobile_money',
			isset( $_POST['reference'] ) ? preg_replace( '/[^A-Za-z0-9.\-]/', '', sanitize_text_field( wp_unslash( $_POST['reference'] ) ) ) : '',
			isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : ''
		);
		$url = admin_url( 'admin.php?page=tch-orders&view=' . $id );
		if ( is_wp_error( $res ) ) {
			self::back( $url, $res->get_error_message(), true );
		}
		self::back( $url, __( 'Payment recorded.', 'tch' ) );
	}

	/**
	 * Delete an order.
	 */
	public static function action_delete_order() {
		$id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		self::guard( 'tch_delete_orders', 'tch_delete_order_' . $id );
		TCH_Orders::delete( $id );
		self::back( admin_url( 'admin.php?page=tch-orders' ), __( 'Order deleted.', 'tch' ) );
	}

	/* ------------------------------------------------------------------
	 * Payments queue
	 * ------------------------------------------------------------------ */

	/**
	 * Pending payments.
	 */
	public static function page_payments() {
		$queue = TCH_Payments::pending_queue();
		?>
		<div class="wrap tch-wrap">
			<h1><?php esc_html_e( 'Payments to verify', 'tch' ); ?></h1>
			<?php self::notice(); ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Payments are NEVER confirmed automatically. For each one, check your real Mobile Money account for the transaction ID and amount, then verify or reject it. Your name and the time are recorded.', 'tch' ); ?></p></div>
			<?php if ( ! $queue ) : ?>
				<p><?php esc_html_e( 'Nothing to verify right now. 🎉', 'tch' ); ?></p>
			<?php endif; ?>
			<?php foreach ( $queue as $p ) : ?>
				<div class="tch-box tch-payment">
					<h2><a href="<?php echo esc_url( admin_url( 'admin.php?page=tch-orders&view=' . (int) $p->order_id ) ); ?>"><?php echo esc_html( $p->order_ref ); ?></a> – <?php echo esc_html( $p->customer_name ); ?> (<?php echo esc_html( $p->customer_phone ); ?>)</h2>
					<p><?php esc_html_e( 'Claimed amount:', 'tch' ); ?> <strong><?php echo esc_html( tch_money( $p->amount ) ); ?></strong>
						· <?php esc_html_e( 'Transaction ID:', 'tch' ); ?> <code><?php echo esc_html( (string) $p->transaction_ref ); ?></code>
						· <?php esc_html_e( 'Paid from:', 'tch' ); ?> <?php echo esc_html( (string) $p->payer_phone ); ?>
						· <?php echo esc_html( tch_date( $p->submitted_at ) ); ?></p>
					<p><?php esc_html_e( 'Order total:', 'tch' ); ?> <?php echo esc_html( tch_money( $p->total_amount ) ); ?> · <?php esc_html_e( 'Expected now:', 'tch' ); ?> <?php echo esc_html( tch_money( $p->amount_expected ) ); ?> · <?php esc_html_e( 'Already verified:', 'tch' ); ?> <?php echo esc_html( tch_money( $p->amount_paid ) ); ?> · <?php esc_html_e( 'Outstanding:', 'tch' ); ?> <?php echo esc_html( tch_money( max( 0, $p->total_amount - $p->amount_paid ) ) ); ?></p>
					<?php if ( $p->evidence_file ) : ?>
						<p><a class="button button-small" href="<?php echo esc_url( TCH_Uploads::view_url( $p->evidence_file ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View screenshot (supporting only)', 'tch' ); ?></a></p>
					<?php endif; ?>
					<?php self::decision_form( $p, admin_url( 'admin.php?page=tch-payments' ) ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Sizes & flavours CRUD
	 * ------------------------------------------------------------------ */

	/**
	 * Catalogue screen.
	 */
	public static function page_catalogue() {
		global $wpdb;
		$edit_size = isset( $_GET['edit_size'] ) ? TCH_Catalogue::size( absint( $_GET['edit_size'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['edit_size'] ) && ! $edit_size ) { // phpcs:ignore WordPress.Security.NonceVerification
			$t         = tch_table( 'cake_sizes' );
			$edit_size = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", absint( $_GET['edit_size'] ) ) ); // phpcs:ignore
		}
		$edit_flav = null;
		if ( isset( $_GET['edit_flavour'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$t         = tch_table( 'flavours' );
			$edit_flav = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", absint( $_GET['edit_flavour'] ) ) ); // phpcs:ignore
		}
		$base = admin_url( 'admin.php?page=tch-catalogue' );
		?>
		<div class="wrap tch-wrap">
			<h1><?php esc_html_e( 'Cake sizes, prices & flavours', 'tch' ); ?></h1>
			<?php self::notice(); ?>
			<p class="description"><?php esc_html_e( 'These prices are used by the website to calculate every cake order. Changes apply to new orders only – existing orders keep the price they were placed at.', 'tch' ); ?></p>
			<div class="tch-cols">
				<div class="tch-box">
					<h2><?php esc_html_e( 'Cake sizes', 'tch' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Size', 'tch' ); ?></th><th><?php esc_html_e( 'Weight', 'tch' ); ?></th><th><?php esc_html_e( 'Price', 'tch' ); ?></th><th><?php esc_html_e( 'Shown', 'tch' ); ?></th><th></th></tr></thead>
						<tbody>
						<?php foreach ( TCH_Catalogue::sizes( false ) as $s ) : ?>
							<tr>
								<td><?php echo esc_html( $s->name ); ?></td><td><?php echo esc_html( $s->weight_label ); ?></td><td><?php echo esc_html( tch_money( $s->price ) ); ?></td><td><?php echo $s->is_active ? '✓' : '—'; ?></td>
								<td><a href="<?php echo esc_url( add_query_arg( 'edit_size', $s->id, $base ) ); ?>"><?php esc_html_e( 'Edit', 'tch' ); ?></a> |
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this size? Old orders keep their size name.', 'tch' ) ); ?>');">
										<?php wp_nonce_field( 'tch_delete_size_' . $s->id ); ?><input type="hidden" name="action" value="tch_delete_size"><input type="hidden" name="id" value="<?php echo (int) $s->id; ?>">
										<button class="button-link button-link-delete"><?php esc_html_e( 'Delete', 'tch' ); ?></button></form></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<h3><?php echo $edit_size ? esc_html__( 'Edit size', 'tch' ) : esc_html__( 'Add a size', 'tch' ); ?></h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'tch_save_size' ); ?>
						<input type="hidden" name="action" value="tch_save_size">
						<input type="hidden" name="id" value="<?php echo $edit_size ? (int) $edit_size->id : 0; ?>">
						<p><label><?php esc_html_e( 'Name', 'tch' ); ?> <input type="text" name="name" required maxlength="40" value="<?php echo esc_attr( $edit_size->name ?? '' ); ?>"></label>
							<label><?php esc_html_e( 'Weight', 'tch' ); ?> <input type="text" name="weight_label" required maxlength="20" value="<?php echo esc_attr( $edit_size->weight_label ?? '' ); ?>" placeholder="1kg" style="width:90px"></label></p>
						<p><label><?php esc_html_e( 'Price (UGX)', 'tch' ); ?> <input type="number" name="price" required min="100" step="50" value="<?php echo esc_attr( $edit_size->price ?? '' ); ?>"></label>
							<label><?php esc_html_e( 'Order', 'tch' ); ?> <input type="number" name="sort_order" value="<?php echo esc_attr( $edit_size->sort_order ?? 0 ); ?>" style="width:70px"></label>
							<label><input type="checkbox" name="is_active" value="1" <?php checked( $edit_size ? (int) $edit_size->is_active : 1, 1 ); ?>> <?php esc_html_e( 'Show on website', 'tch' ); ?></label></p>
						<p><button class="button button-primary"><?php esc_html_e( 'Save size', 'tch' ); ?></button>
							<?php if ( $edit_size ) : ?><a class="button" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Cancel', 'tch' ); ?></a><?php endif; ?></p>
					</form>
				</div>
				<div class="tch-box">
					<h2><?php esc_html_e( 'Flavours', 'tch' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Flavour', 'tch' ); ?></th><th><?php esc_html_e( 'Shown', 'tch' ); ?></th><th></th></tr></thead>
						<tbody>
						<?php foreach ( TCH_Catalogue::flavours( false ) as $f ) : ?>
							<tr><td><?php echo esc_html( $f->name ); ?></td><td><?php echo $f->is_active ? '✓' : '—'; ?></td>
								<td><a href="<?php echo esc_url( add_query_arg( 'edit_flavour', $f->id, $base ) ); ?>"><?php esc_html_e( 'Edit', 'tch' ); ?></a> |
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this flavour?', 'tch' ) ); ?>');">
										<?php wp_nonce_field( 'tch_delete_flavour_' . $f->id ); ?><input type="hidden" name="action" value="tch_delete_flavour"><input type="hidden" name="id" value="<?php echo (int) $f->id; ?>">
										<button class="button-link button-link-delete"><?php esc_html_e( 'Delete', 'tch' ); ?></button></form></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<h3><?php echo $edit_flav ? esc_html__( 'Edit flavour', 'tch' ) : esc_html__( 'Add a flavour', 'tch' ); ?></h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'tch_save_flavour' ); ?>
						<input type="hidden" name="action" value="tch_save_flavour">
						<input type="hidden" name="id" value="<?php echo $edit_flav ? (int) $edit_flav->id : 0; ?>">
						<p><label><?php esc_html_e( 'Name', 'tch' ); ?> <input type="text" name="name" required maxlength="40" value="<?php echo esc_attr( $edit_flav->name ?? '' ); ?>"></label>
							<label><?php esc_html_e( 'Order', 'tch' ); ?> <input type="number" name="sort_order" value="<?php echo esc_attr( $edit_flav->sort_order ?? 0 ); ?>" style="width:70px"></label>
							<label><input type="checkbox" name="is_active" value="1" <?php checked( $edit_flav ? (int) $edit_flav->is_active : 1, 1 ); ?>> <?php esc_html_e( 'Show on website', 'tch' ); ?></label></p>
						<p><button class="button button-primary"><?php esc_html_e( 'Save flavour', 'tch' ); ?></button>
							<?php if ( $edit_flav ) : ?><a class="button" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Cancel', 'tch' ); ?></a><?php endif; ?></p>
					</form>
					<p class="description"><?php esc_html_e( 'Cupcake and cookie prices are edited on each product under Cake Products.', 'tch' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * INSERT / UPDATE a size.
	 */
	public static function action_save_size() {
		global $wpdb;
		self::guard( 'tch_manage_catalogue', 'tch_save_size' );
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data = array(
			'name'         => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'weight_label' => sanitize_text_field( wp_unslash( $_POST['weight_label'] ?? '' ) ),
			'price'        => absint( $_POST['price'] ?? 0 ),
			'sort_order'   => (int) ( $_POST['sort_order'] ?? 0 ),
			'is_active'    => empty( $_POST['is_active'] ) ? 0 : 1,
			'updated_at'   => current_time( 'mysql' ),
		);
		$base = admin_url( 'admin.php?page=tch-catalogue' );
		if ( '' === $data['name'] || '' === $data['weight_label'] || $data['price'] < 100 ) {
			self::back( $base, __( 'Please enter a name, weight and price.', 'tch' ), true );
		}
		$fmt = array( '%s', '%s', '%d', '%d', '%d', '%s' );
		$ok  = $id ? $wpdb->update( tch_table( 'cake_sizes' ), $data, array( 'id' => $id ), $fmt, array( '%d' ) ) : $wpdb->insert( tch_table( 'cake_sizes' ), $data, $fmt );
		self::back( $base, false === $ok ? __( 'Could not save (is the name already used?).', 'tch' ) : __( 'Size saved.', 'tch' ), false === $ok );
	}

	/**
	 * DELETE a size (order items keep their label; FK sets size_id NULL).
	 */
	public static function action_delete_size() {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		self::guard( 'tch_manage_catalogue', 'tch_delete_size_' . $id );
		$wpdb->delete( tch_table( 'cake_sizes' ), array( 'id' => $id ), array( '%d' ) );
		self::back( admin_url( 'admin.php?page=tch-catalogue' ), __( 'Size deleted.', 'tch' ) );
	}

	/**
	 * INSERT / UPDATE a flavour.
	 */
	public static function action_save_flavour() {
		global $wpdb;
		self::guard( 'tch_manage_catalogue', 'tch_save_flavour' );
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data = array(
			'name'       => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'sort_order' => (int) ( $_POST['sort_order'] ?? 0 ),
			'is_active'  => empty( $_POST['is_active'] ) ? 0 : 1,
		);
		$base = admin_url( 'admin.php?page=tch-catalogue' );
		if ( '' === $data['name'] ) {
			self::back( $base, __( 'Please enter a flavour name.', 'tch' ), true );
		}
		$fmt = array( '%s', '%d', '%d' );
		$ok  = $id ? $wpdb->update( tch_table( 'flavours' ), $data, array( 'id' => $id ), $fmt, array( '%d' ) ) : $wpdb->insert( tch_table( 'flavours' ), $data, $fmt );
		self::back( $base, false === $ok ? __( 'Could not save (is the name already used?).', 'tch' ) : __( 'Flavour saved.', 'tch' ), false === $ok );
	}

	/**
	 * DELETE a flavour.
	 */
	public static function action_delete_flavour() {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		self::guard( 'tch_manage_catalogue', 'tch_delete_flavour_' . $id );
		$wpdb->delete( tch_table( 'flavours' ), array( 'id' => $id ), array( '%d' ) );
		self::back( admin_url( 'admin.php?page=tch-catalogue' ), __( 'Flavour deleted.', 'tch' ) );
	}

	/* ------------------------------------------------------------------
	 * Messages
	 * ------------------------------------------------------------------ */

	/**
	 * Contact messages.
	 */
	public static function page_messages() {
		global $wpdb;
		$t    = tch_table( 'messages' );
		$rows = $wpdb->get_results( "SELECT * FROM $t ORDER BY created_at DESC LIMIT 200" ); // phpcs:ignore
		?>
		<div class="wrap tch-wrap">
			<h1><?php esc_html_e( 'Messages from the contact form', 'tch' ); ?></h1>
			<?php self::notice(); ?>
			<?php if ( ! $rows ) : ?>
				<p><?php esc_html_e( 'No messages yet.', 'tch' ); ?></p>
			<?php endif; ?>
			<?php foreach ( $rows as $m ) : ?>
				<div class="tch-box <?php echo $m->is_read ? '' : 'tch-box--unread'; ?>">
					<h2><?php echo esc_html( $m->subject ); ?></h2>
					<p><strong><?php echo esc_html( $m->full_name ); ?></strong> · <?php echo esc_html( (string) $m->phone ); ?> · <?php echo esc_html( (string) $m->email ); ?> · <?php echo esc_html( tch_date( $m->created_at ) ); ?></p>
					<p><?php echo nl2br( esc_html( $m->message ) ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'tch_message_' . $m->id ); ?>
						<input type="hidden" name="action" value="tch_message"><input type="hidden" name="id" value="<?php echo (int) $m->id; ?>">
						<?php if ( ! $m->is_read ) : ?><button class="button" name="do" value="read"><?php esc_html_e( 'Mark as read', 'tch' ); ?></button><?php endif; ?>
						<?php if ( $m->phone ) : ?><a class="button" href="<?php echo esc_url( tch_whatsapp_to( $m->phone ) ); ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
						<button class="button button-link-delete" name="do" value="delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this message?', 'tch' ) ); ?>');"><?php esc_html_e( 'Delete', 'tch' ); ?></button>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Message actions.
	 */
	public static function action_message() {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		self::guard( 'tch_manage_orders', 'tch_message_' . $id );
		$do = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
		if ( 'delete' === $do ) {
			$wpdb->delete( tch_table( 'messages' ), array( 'id' => $id ), array( '%d' ) );
		} else {
			$wpdb->update( tch_table( 'messages' ), array( 'is_read' => 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
		}
		self::back( admin_url( 'admin.php?page=tch-messages' ), __( 'Done.', 'tch' ) );
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	/**
	 * Business settings form.
	 */
	public static function page_settings() {
		$values = wp_parse_args( (array) get_option( 'tch_settings', array() ), TCH_Settings::defaults() );
		$err    = get_option( 'tch_last_mail_error' );
		?>
		<div class="wrap tch-wrap">
			<h1><?php esc_html_e( 'Business settings', 'tch' ); ?></h1>
			<p class="description"><?php esc_html_e( 'These details appear across the website, in emails and in the order forms.', 'tch' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'tch_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( TCH_Settings::fields() as $key => $def ) : ?>
						<tr><th scope="row"><label for="tch_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def[0] ); ?></label></th><td>
							<?php if ( 'textarea' === $def[1] ) : ?>
								<textarea id="tch_<?php echo esc_attr( $key ); ?>" name="tch_settings[<?php echo esc_attr( $key ); ?>]" rows="3" class="large-text"><?php echo esc_textarea( $values[ $key ] ); ?></textarea>
							<?php else : ?>
								<input id="tch_<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( $def[1] ); ?>" name="tch_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $values[ $key ] ); ?>" class="regular-text">
							<?php endif; ?>
							<?php if ( 'momo_instructions' === $key ) : ?>
								<p class="description"><?php esc_html_e( 'Until you fill this in, customers are asked to call/WhatsApp you for the payment details.', 'tch' ); ?></p>
							<?php endif; ?>
						</td></tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button(); ?>
			</form>
			<h2><?php esc_html_e( 'Email delivery', 'tch' ); ?></h2>
			<p><?php echo TCH_Mail::configured() ? esc_html__( 'SMTP is configured in wp-config.php.', 'tch' ) : esc_html__( 'SMTP is not configured yet – emails use the server default. See docs/05-email-smtp.md.', 'tch' ); ?></p>
			<?php if ( is_array( $err ) ) : ?>
				<p class="notice notice-error inline"><?php echo esc_html( sprintf( /* translators: 1: time 2: message */ __( 'Last email error (%1$s): %2$s', 'tch' ), $err['time'], $err['message'] ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
