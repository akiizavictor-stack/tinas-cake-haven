<?php
/**
 * Orders list table in wp-admin.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Orders table.
 */
class TCH_Orders_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array( 'singular' => 'order', 'plural' => 'orders', 'ajax' => false ) );
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'order_ref'      => __( 'Order', 'tch' ),
			'customer'       => __( 'Customer', 'tch' ),
			'summary'        => __( 'Items', 'tch' ),
			'needed_by'      => __( 'Needed by', 'tch' ),
			'total_amount'   => __( 'Total', 'tch' ),
			'balance'        => __( 'Balance', 'tch' ),
			'order_status'   => __( 'Order status', 'tch' ),
			'payment_status' => __( 'Payment', 'tch' ),
			'created_at'     => __( 'Placed', 'tch' ),
		);
	}

	/**
	 * Sortable.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'order_ref'    => array( 'order_ref', false ),
			'needed_by'    => array( 'needed_by', false ),
			'total_amount' => array( 'total_amount', false ),
			'created_at'   => array( 'created_at', true ),
		);
	}

	/**
	 * Status filter links.
	 *
	 * @return array
	 */
	protected function get_views() {
		$stats   = TCH_Orders::stats();
		$current = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$base    = admin_url( 'admin.php?page=tch-orders' );
		$views   = array( 'all' => sprintf( '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', esc_url( $base ), '' === $current ? 'current' : '', esc_html__( 'All', 'tch' ), $stats['total'] ) );
		foreach ( tch_order_statuses() as $k => $label ) {
			if ( ! $stats['by_status'][ $k ] ) {
				continue;
			}
			$views[ $k ] = sprintf( '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'status', $k, $base ) ), $current === $k ? 'current' : '', esc_html( $label ), $stats['by_status'][ $k ] );
		}
		return $views;
	}

	/**
	 * Payment filter dropdown.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$pay  = isset( $_GET['pay'] ) ? sanitize_key( $_GET['pay'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="alignleft actions"><select name="pay"><option value="">' . esc_html__( 'All payment statuses', 'tch' ) . '</option>';
		foreach ( tch_order_payment_statuses() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $pay, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> <select name="type"><option value="">' . esc_html__( 'All order types', 'tch' ) . '</option><option value="standard"' . selected( $type, 'standard', false ) . '>' . esc_html__( 'Standard', 'tch' ) . '</option><option value="custom"' . selected( $type, 'custom', false ) . '>' . esc_html__( 'Customized cake', 'tch' ) . '</option></select> ';
		submit_button( __( 'Filter', 'tch' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Loads rows.
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification
		$per  = 20;
		$res  = TCH_Orders::query(
			array(
				'status'   => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
				'pay'      => isset( $_GET['pay'] ) ? sanitize_key( $_GET['pay'] ) : '',
				'type'     => isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '',
				'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
				'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at',
				'order'    => isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'desc',
				'per_page' => $per,
				'page'     => $this->get_pagenum(),
			)
		);
		// phpcs:enable
		$this->items           = $res['rows'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args( array( 'total_items' => $res['total'], 'per_page' => $per ) );
	}

	/**
	 * Default column.
	 *
	 * @param object $item Row.
	 * @param string $col  Column.
	 * @return string
	 */
	protected function column_default( $item, $col ) {
		switch ( $col ) {
			case 'order_ref':
				$url = admin_url( 'admin.php?page=tch-orders&view=' . (int) $item->id );
				return '<strong><a href="' . esc_url( $url ) . '">' . esc_html( $item->order_ref ) . '</a></strong>' . ( 'custom' === $item->order_type ? '<br><span class="tch-pill">' . esc_html__( 'Custom cake', 'tch' ) . '</span>' : '' );
			case 'customer':
				return esc_html( $item->customer_name ) . '<br><small>' . esc_html( $item->customer_phone ) . '</small>' . ( $item->user_id ? ' <span class="dashicons dashicons-admin-users" title="' . esc_attr__( 'Registered customer', 'tch' ) . '"></span>' : '' );
			case 'summary':
				return esc_html( (string) $item->summary );
			case 'needed_by':
				return esc_html( tch_date( $item->needed_by ) ) . '<br><small>' . ( 'delivery' === $item->fulfilment ? esc_html__( 'Delivery', 'tch' ) : esc_html__( 'Collection', 'tch' ) ) . '</small>';
			case 'total_amount':
				return esc_html( tch_money( $item->total_amount ) );
			case 'balance':
				return esc_html( tch_money( TCH_Payments::outstanding( $item ) ) );
			case 'order_status':
				return tch_badge( $item->order_status, tch_order_statuses() );
			case 'payment_status':
				return tch_badge( $item->payment_status, tch_order_payment_statuses() );
			case 'created_at':
				return esc_html( tch_date( $item->created_at ) );
		}
		return '';
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		esc_html_e( 'No orders found.', 'tch' );
	}
}
