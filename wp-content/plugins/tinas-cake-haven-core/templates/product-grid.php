<?php
/**
 * Product cards.
 *
 * @var WP_Post[] $products
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;
if ( ! $products ) {
	echo '<p>' . esc_html__( 'Products will appear here soon.', 'tch' ) . '</p>';
	return;
}
$custom_id = (int) get_option( 'tch_custom_product_id' );
?>
<div class="tch-grid">
	<?php
	foreach ( $products as $p ) :
		$cfg = TCH_Catalogue::config( $p->ID );
		if ( ! $cfg ) {
			continue;
		}
		$terms     = get_the_terms( $p, TCH_Catalogue::TAX );
		$is_custom = $p->ID === $custom_id;
		$order_url = $is_custom ? tch_page_url( 'custom' ) : tch_page_url( 'order', array( 'product' => $p->ID ) );
		?>
		<article class="tch-card">
			<a class="tch-card__media" href="<?php echo esc_url( get_permalink( $p ) ); ?>" tabindex="-1" aria-hidden="true">
				<?php
				if ( has_post_thumbnail( $p ) ) {
					echo get_the_post_thumbnail( $p, 'medium_large', array( 'loading' => 'lazy', 'sizes' => '(max-width: 600px) 92vw, (max-width: 1000px) 45vw, 360px' ) );
				}
				?>
				<?php if ( get_post_meta( (int) get_post_thumbnail_id( $p ), '_tch_illustrative', true ) ) : ?>
					<span class="tch-card__illus"><?php esc_html_e( 'Illustrative photo', 'tch' ); ?></span>
				<?php endif; ?>
				<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
					<span class="tch-card__tag"><?php echo esc_html( $terms[0]->name ); ?></span>
				<?php endif; ?>
			</a>
			<div class="tch-card__body">
				<h3 class="tch-card__title"><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></h3>
				<p class="tch-card__price"><?php echo esc_html( TCH_Catalogue::price_summary( $cfg ) ); ?></p>
				<p class="tch-card__text"><?php echo esc_html( wp_trim_words( has_excerpt( $p ) ? $p->post_excerpt : wp_strip_all_tags( $p->post_content ), 22 ) ); ?></p>
				<?php if ( $cfg['uses_flavours'] ) : ?>
					<p class="tch-card__meta"><?php echo esc_html( implode( ' · ', wp_list_pluck( TCH_Catalogue::flavours(), 'name' ) ) ); ?></p>
				<?php endif; ?>
				<div class="tch-card__actions">
					<?php if ( $cfg['orderable'] ) : ?>
						<a class="tch-btn" href="<?php echo esc_url( $order_url ); ?>"><?php echo $is_custom ? esc_html__( 'Request design', 'tch' ) : esc_html__( 'Order now', 'tch' ); ?></a>
					<?php endif; ?>
					<a class="tch-btn tch-btn--ghost" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php esc_html_e( 'Details', 'tch' ); ?><span class="screen-reader-text"> – <?php echo esc_html( get_the_title( $p ) ); ?></span></a>
				</div>
			</div>
		</article>
	<?php endforeach; ?>
</div>
