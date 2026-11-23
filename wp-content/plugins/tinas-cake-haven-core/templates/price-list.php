<?php
/**
 * Compact price list.
 *
 * @var object[] $sizes
 * @var object[] $flavours
 * @var array[]  $products
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="tch-pricelist">
	<div class="tch-pricelist__col">
		<h3><?php esc_html_e( 'Birthday cakes', 'tch' ); ?></h3>
		<ul>
			<?php foreach ( $sizes as $s ) : ?>
				<li><span><?php echo esc_html( $s->name . ' – ' . $s->weight_label ); ?></span><b><?php echo esc_html( tch_money( $s->price ) ); ?></b></li>
			<?php endforeach; ?>
		</ul>
		<p class="tch-note"><?php esc_html_e( 'Flavours:', 'tch' ); ?> <?php echo esc_html( implode( ' · ', wp_list_pluck( $flavours, 'name' ) ) ); ?></p>
	</div>
	<div class="tch-pricelist__col">
		<h3><?php esc_html_e( 'Cupcakes & cookies', 'tch' ); ?></h3>
		<ul>
			<?php
			foreach ( $products as $p ) :
				if ( 'sized' === $p['type'] ) {
					continue;
				}
				foreach ( TCH_Catalogue::price_rows( $p ) as $row ) :
					?>
					<li><span><?php echo esc_html( $p['name'] . ' – ' . strtolower( $row[0] ) ); ?></span><b><?php echo esc_html( $row[1] ); ?></b></li>
					<?php
				endforeach;
			endforeach;
			?>
		</ul>
		<p class="tch-note"><?php echo esc_html( sprintf( /* translators: %d hours */ __( 'Customized birthday cakes: please order at least %d hours in advance.', 'tch' ), (int) tch_setting( 'custom_notice_hours', '24' ) ) ); ?></p>
	</div>
</div>
