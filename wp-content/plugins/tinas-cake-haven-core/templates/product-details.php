<?php
/**
 * Price table and order button on a product page.
 *
 * @var array    $cfg
 * @var object[] $flavours
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;
$is_custom = (int) get_option( 'tch_custom_product_id' ) === $cfg['id'];
$order_url = $is_custom ? tch_page_url( 'custom' ) : tch_page_url( 'order', array( 'product' => $cfg['id'] ) );
$hours     = (int) tch_setting( 'custom_notice_hours', '24' );
?>
<div class="tch-details">
	<p class="tch-details__price"><?php echo esc_html( TCH_Catalogue::price_summary( $cfg ) ); ?></p>
	<table class="tch-table tch-table--prices">
		<caption><?php echo 'sized' === $cfg['type'] ? esc_html__( 'Sizes and prices', 'tch' ) : esc_html__( 'Prices', 'tch' ); ?></caption>
		<tbody>
		<?php foreach ( TCH_Catalogue::price_rows( $cfg ) as $row ) : ?>
			<tr><th scope="row"><?php echo esc_html( $row[0] ); ?></th><td><?php echo esc_html( $row[1] ); ?></td></tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( $cfg['uses_flavours'] && $flavours ) : ?>
		<p class="tch-details__label"><?php esc_html_e( 'Available flavours', 'tch' ); ?></p>
		<ul class="tch-chips">
			<?php foreach ( $flavours as $f ) : ?>
				<li><?php echo esc_html( $f->name ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p class="tch-note"><?php esc_html_e( 'The price depends on the size only – every flavour costs the same.', 'tch' ); ?></p>
	<?php endif; ?>
	<?php if ( 'bundle' === $cfg['type'] ) : ?>
		<p class="tch-note"><?php echo esc_html( sprintf( /* translators: %d pack size */ __( 'Sold in packs of %d.', 'tch' ), (int) $cfg['bundle_size'] ) ); ?></p>
	<?php endif; ?>
	<?php if ( $is_custom ) : ?>
		<p class="tch-note"><?php echo esc_html( sprintf( /* translators: %d hours */ __( 'Customized birthday cakes need at least %d hours of notice.', 'tch' ), $hours ) ); ?></p>
	<?php endif; ?>
	<div class="tch-details__actions">
		<?php if ( $cfg['orderable'] ) : ?>
			<a class="tch-btn tch-btn--lg" href="<?php echo esc_url( $order_url ); ?>"><?php echo $is_custom ? esc_html__( 'Request a customized cake', 'tch' ) : esc_html__( 'Order this now', 'tch' ); ?></a>
		<?php endif; ?>
		<a class="tch-btn tch-btn--whatsapp" href="<?php echo esc_url( tch_whatsapp_link( sprintf( 'Hello, I would like to ask about the %s.', $cfg['name'] ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ask on WhatsApp', 'tch' ); ?></a>
	</div>
</div>
