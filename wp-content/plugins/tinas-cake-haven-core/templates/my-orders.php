<?php
/**
 * Logged-in customer's orders: the latest three, then all with pagination.
 *
 * @var object[] $orders
 * @var int      $total
 * @var bool     $all
 * @var int      $page
 * @var int      $per
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;
$ostatus = tch_order_statuses();
$pstatus = tch_order_payment_statuses();
$pages   = (int) ceil( $total / max( 1, $per ) );
?>
<div class="tch-myorders">
	<?php echo TCH_Frontend::render_flashes(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<div class="tch-myorders__head">
		<p><?php echo esc_html( sprintf( /* translators: %s name */ __( 'Hello %s 👋', 'tch' ), wp_get_current_user()->display_name ) ); ?></p>
		<a class="tch-btn tch-btn--sm" href="<?php echo esc_url( tch_page_url( 'order' ) ); ?>"><?php esc_html_e( 'New order', 'tch' ); ?></a>
	</div>
	<?php if ( ! $orders ) : ?>
		<div class="tch-panel tch-panel--center">
			<p><?php esc_html_e( 'You have not placed any orders while logged in yet.', 'tch' ); ?></p>
			<a class="tch-btn" href="<?php echo esc_url( tch_page_url( 'products' ) ); ?>"><?php esc_html_e( 'Browse our cakes', 'tch' ); ?></a>
		</div>
	<?php else : ?>
		<h3><?php echo $all ? esc_html__( 'All orders', 'tch' ) : esc_html__( 'Your most recent orders', 'tch' ); ?></h3>
		<div class="tch-ordercards">
			<?php foreach ( $orders as $o ) : ?>
				<a class="tch-ordercard" href="<?php echo esc_url( tch_page_url( 'my-orders', array( 'order' => $o->order_ref ) ) ); ?>">
					<span class="tch-ordercard__ref"><?php echo esc_html( $o->order_ref ); ?></span>
					<span class="tch-ordercard__date"><?php echo esc_html( tch_date( $o->created_at ) ); ?> · <?php echo esc_html( sprintf( _n( '%d item', '%d items', (int) $o->item_qty, 'tch' ), (int) $o->item_qty ) ); ?></span>
					<span class="tch-ordercard__badges"><?php echo tch_badge( $o->order_status, $ostatus ) . ' ' . tch_badge( $o->payment_status, $pstatus ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="tch-ordercard__money"><?php esc_html_e( 'Total', 'tch' ); ?> <b><?php echo esc_html( tch_money( $o->total_amount ) ); ?></b> · <?php esc_html_e( 'Balance', 'tch' ); ?> <b><?php echo esc_html( tch_money( TCH_Payments::outstanding( $o ) ) ); ?></b></span>
				</a>
			<?php endforeach; ?>
		</div>
		<?php if ( ! $all && $total > $per ) : ?>
			<p><a class="tch-btn tch-btn--ghost" href="<?php echo esc_url( tch_page_url( 'my-orders', array( 'all' => 1 ) ) ); ?>"><?php echo esc_html( sprintf( /* translators: %d count */ __( 'View older orders (%d in total)', 'tch' ), $total ) ); ?></a></p>
		<?php elseif ( $all ) : ?>
			<nav class="tch-pager" aria-label="<?php esc_attr_e( 'Order pages', 'tch' ); ?>">
				<?php if ( $page > 1 ) : ?>
					<a href="<?php echo esc_url( tch_page_url( 'my-orders', array( 'all' => 1, 'pg' => $page - 1 ) ) ); ?>">← <?php esc_html_e( 'Newer', 'tch' ); ?></a>
				<?php endif; ?>
				<span><?php echo esc_html( sprintf( /* translators: 1: page 2: pages */ __( 'Page %1$d of %2$d', 'tch' ), $page, max( 1, $pages ) ) ); ?></span>
				<?php if ( $page < $pages ) : ?>
					<a href="<?php echo esc_url( tch_page_url( 'my-orders', array( 'all' => 1, 'pg' => $page + 1 ) ) ); ?>"><?php esc_html_e( 'Older', 'tch' ); ?> →</a>
				<?php endif; ?>
			</nav>
			<p><a class="tch-link" href="<?php echo esc_url( tch_page_url( 'my-orders' ) ); ?>"><?php esc_html_e( 'Show only recent orders', 'tch' ); ?></a></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
