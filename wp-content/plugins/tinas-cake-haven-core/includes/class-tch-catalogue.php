<?php
/**
 * Product catalogue: "Cake Products" post type + trusted pricing rules.
 *
 * Products are WordPress posts so the owner edits names, descriptions and photos
 * in the normal editor. Prices live in post meta (per unit / bundle) and in the
 * tch_cake_sizes table (cake sizes), and are ONLY ever read on the server.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Catalogue.
 */
class TCH_Catalogue {

	const PT  = 'tch_product';
	const TAX = 'tch_product_cat';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_types' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::PT, array( __CLASS__, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::PT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::PT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	/**
	 * Post type and taxonomy.
	 */
	public static function register_types() {
		register_post_type(
			self::PT,
			array(
				'labels'        => array(
					'name'          => __( 'Cake Products', 'tch' ),
					'singular_name' => __( 'Cake Product', 'tch' ),
					'add_new_item'  => __( 'Add new product', 'tch' ),
					'edit_item'     => __( 'Edit product', 'tch' ),
					'menu_name'     => __( 'Cake Products', 'tch' ),
					'all_items'     => __( 'All products', 'tch' ),
				),
				'public'        => true,
				'show_in_rest'  => true,
				'menu_icon'     => 'dashicons-carrot',
				'menu_position' => 26,
				'has_archive'   => false,
				'rewrite'       => array( 'slug' => 'products', 'with_front' => false ),
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
				'capability_type' => 'post',
			)
		);
		register_taxonomy(
			self::TAX,
			self::PT,
			array(
				'labels'            => array(
					'name'          => __( 'Product categories', 'tch' ),
					'singular_name' => __( 'Product category', 'tch' ),
				),
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'product-category', 'with_front' => false ),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Sizes and flavours (custom tables)
	 * ------------------------------------------------------------------ */

	/**
	 * Cake sizes.
	 *
	 * @param bool $active_only Only active rows.
	 * @return array<int,object>
	 */
	public static function sizes( $active_only = true ) {
		global $wpdb;
		$t     = tch_table( 'cake_sizes' );
		$where = $active_only ? 'WHERE is_active = 1' : '';
		return $wpdb->get_results( "SELECT * FROM $t $where ORDER BY sort_order, price" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Flavours.
	 *
	 * @param bool $active_only Only active rows.
	 * @return array<int,object>
	 */
	public static function flavours( $active_only = true ) {
		global $wpdb;
		$t     = tch_table( 'flavours' );
		$where = $active_only ? 'WHERE is_active = 1' : '';
		return $wpdb->get_results( "SELECT * FROM $t $where ORDER BY sort_order, name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * One active size.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function size( $id ) {
		global $wpdb;
		$t = tch_table( 'cake_sizes' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d AND is_active = 1", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * One active flavour.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function flavour( $id ) {
		global $wpdb;
		$t = tch_table( 'flavours' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d AND is_active = 1", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/* ------------------------------------------------------------------
	 * Product configuration
	 * ------------------------------------------------------------------ */

	/**
	 * Pricing configuration of a product from trusted post meta.
	 *
	 * @param int $post_id Product id.
	 * @return array|null Null when not an orderable published product.
	 */
	public static function config( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::PT !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		$type = get_post_meta( $post_id, '_tch_pricing_type', true );
		$type = in_array( $type, array( 'sized', 'per_unit', 'bundle' ), true ) ? $type : 'per_unit';
		$allowed = array_filter( array_map( 'absint', (array) get_post_meta( $post_id, '_tch_allowed_sizes', true ) ) );
		return array(
			'id'            => (int) $post_id,
			'name'          => get_the_title( $post ),
			'type'          => $type,
			'unit_price'    => (int) get_post_meta( $post_id, '_tch_unit_price', true ),
			'bundle_size'   => max( 1, (int) get_post_meta( $post_id, '_tch_bundle_size', true ) ),
			'uses_flavours' => (bool) get_post_meta( $post_id, '_tch_uses_flavours', true ),
			'allowed_sizes' => $allowed, // Empty = all active sizes.
			'min_qty'       => max( 1, (int) get_post_meta( $post_id, '_tch_min_qty', true ) ),
			'max_qty'       => max( 1, (int) ( get_post_meta( $post_id, '_tch_max_qty', true ) ? get_post_meta( $post_id, '_tch_max_qty', true ) : 200 ) ),
			'orderable'     => '0' !== get_post_meta( $post_id, '_tch_orderable', true ),
		);
	}

	/**
	 * Active sizes allowed for a product.
	 *
	 * @param array $cfg Product config.
	 * @return array<int,object>
	 */
	public static function sizes_for( $cfg ) {
		$sizes = self::sizes();
		if ( empty( $cfg['allowed_sizes'] ) ) {
			return $sizes;
		}
		return array_values(
			array_filter(
				$sizes,
				static function ( $s ) use ( $cfg ) {
					return in_array( (int) $s->id, $cfg['allowed_sizes'], true );
				}
			)
		);
	}

	/**
	 * Server-side price of one order line. This is the ONLY place prices are calculated.
	 *
	 * @param int $product_id Product id.
	 * @param int $size_id    Size id (sized products).
	 * @param int $flavour_id Flavour id (when flavours apply).
	 * @param int $qty        Quantity (pieces).
	 * @return array|WP_Error
	 */
	public static function price_line( $product_id, $size_id, $flavour_id, $qty ) {
		$cfg = self::config( $product_id );
		if ( ! $cfg || ! $cfg['orderable'] ) {
			return new WP_Error( 'product', __( 'Please choose a product from our menu.', 'tch' ) );
		}
		$qty = (int) $qty;
		if ( $qty < $cfg['min_qty'] || $qty > $cfg['max_qty'] ) {
			/* translators: 1: product, 2: min, 3: max */
			return new WP_Error( 'qty', sprintf( __( '%1$s: quantity must be between %2$d and %3$d.', 'tch' ), $cfg['name'], $cfg['min_qty'], $cfg['max_qty'] ) );
		}
		$size    = null;
		$flavour = null;
		if ( $cfg['uses_flavours'] ) {
			$flavour = self::flavour( (int) $flavour_id );
			if ( ! $flavour ) {
				/* translators: %s product */
				return new WP_Error( 'flavour', sprintf( __( '%s: please choose a flavour.', 'tch' ), $cfg['name'] ) );
			}
		}
		switch ( $cfg['type'] ) {
			case 'sized':
				$size = self::size( (int) $size_id );
				if ( ! $size || ( $cfg['allowed_sizes'] && ! in_array( (int) $size->id, $cfg['allowed_sizes'], true ) ) ) {
					/* translators: %s product */
					return new WP_Error( 'size', sprintf( __( '%s: please choose a cake size.', 'tch' ), $cfg['name'] ) );
				}
				$unit = (int) $size->price;
				$line = $unit * $qty;
				break;
			case 'bundle':
				if ( 0 !== $qty % $cfg['bundle_size'] ) {
					/* translators: 1: product, 2: bundle size */
					return new WP_Error( 'bundle', sprintf( __( '%1$s are sold in packs of %2$d. Please choose a multiple of %2$d.', 'tch' ), $cfg['name'], $cfg['bundle_size'] ) );
				}
				$unit = $cfg['unit_price'];
				$line = intdiv( $qty, $cfg['bundle_size'] ) * $unit;
				break;
			default:
				$unit = $cfg['unit_price'];
				$line = $unit * $qty;
		}
		if ( $line < 1 ) {
			return new WP_Error( 'price', __( 'This product has no price yet. Please contact us.', 'tch' ) );
		}
		return array(
			'product_id'    => $cfg['id'],
			'product_name'  => $cfg['name'],
			'size_id'       => $size ? (int) $size->id : null,
			'size_label'    => $size ? $size->name . ' – ' . $size->weight_label : null,
			'flavour_id'    => $flavour ? (int) $flavour->id : null,
			'flavour_label' => $flavour ? $flavour->name : null,
			'quantity'      => $qty,
			'unit_price'    => $unit,
			'line_total'    => $line,
		);
	}

	/**
	 * Human price summary, e.g. "From UGX 35,000".
	 *
	 * @param array $cfg Config.
	 * @return string
	 */
	public static function price_summary( $cfg ) {
		if ( 'sized' === $cfg['type'] ) {
			$sizes = self::sizes_for( $cfg );
			if ( ! $sizes ) {
				return '';
			}
			$prices = wp_list_pluck( $sizes, 'price' );
			$min    = min( $prices );
			return count( $sizes ) > 1 ? sprintf( __( 'From %s', 'tch' ), tch_money( $min ) ) : tch_money( $min );
		}
		if ( 'bundle' === $cfg['type'] ) {
			/* translators: 1: price, 2: pack size */
			return sprintf( __( '%1$s per %2$d', 'tch' ), tch_money( $cfg['unit_price'] ), $cfg['bundle_size'] );
		}
		/* translators: %s: price */
		return sprintf( __( '%s each', 'tch' ), tch_money( $cfg['unit_price'] ) );
	}

	/**
	 * Price table rows for a product (used on product pages).
	 *
	 * @param array $cfg Config.
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function price_rows( $cfg ) {
		$rows = array();
		if ( 'sized' === $cfg['type'] ) {
			foreach ( self::sizes_for( $cfg ) as $s ) {
				$rows[] = array( $s->name . ' – ' . $s->weight_label, tch_money( $s->price ) );
			}
		} elseif ( 'bundle' === $cfg['type'] ) {
			for ( $n = 1; $n <= 3; $n++ ) {
				$q      = $n * $cfg['bundle_size'];
				$rows[] = array( sprintf( _n( '%d piece', '%d pieces', $q, 'tch' ), $q ), tch_money( $n * $cfg['unit_price'] ) );
			}
		} else {
			$rows[] = array( __( 'Each', 'tch' ), tch_money( $cfg['unit_price'] ) );
		}
		return $rows;
	}

	/**
	 * All orderable products with config (for the order form).
	 *
	 * @return array<int,array>
	 */
	public static function orderable_products() {
		$posts = get_posts(
			array(
				'post_type'      => self::PT,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$cfg = self::config( $p->ID );
			if ( $cfg && $cfg['orderable'] ) {
				$out[] = $cfg;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Admin: product pricing meta box
	 * ------------------------------------------------------------------ */

	/**
	 * Registers the pricing box.
	 */
	public static function meta_boxes() {
		add_meta_box( 'tch_pricing', __( 'Pricing and ordering options', 'tch' ), array( __CLASS__, 'render_meta_box' ), self::PT, 'normal', 'high' );
	}

	/**
	 * Pricing box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'tch_save_product', 'tch_product_nonce' );
		$type     = get_post_meta( $post->ID, '_tch_pricing_type', true ) ? get_post_meta( $post->ID, '_tch_pricing_type', true ) : 'per_unit';
		$unit     = (int) get_post_meta( $post->ID, '_tch_unit_price', true );
		$bundle   = max( 1, (int) get_post_meta( $post->ID, '_tch_bundle_size', true ) );
		$flav     = (bool) get_post_meta( $post->ID, '_tch_uses_flavours', true );
		$min      = max( 1, (int) get_post_meta( $post->ID, '_tch_min_qty', true ) );
		$max      = (int) get_post_meta( $post->ID, '_tch_max_qty', true );
		$max      = $max ? $max : 200;
		$allowed  = array_map( 'absint', (array) get_post_meta( $post->ID, '_tch_allowed_sizes', true ) );
		$orderable = '0' !== get_post_meta( $post->ID, '_tch_orderable', true );
		?>
		<p class="description"><?php esc_html_e( 'Cake size prices are managed once for all cakes under Cake Orders → Sizes & Flavours. Prices are always recalculated on the server when an order is placed.', 'tch' ); ?></p>
		<table class="form-table" role="presentation">
			<tr><th><label for="tch_pricing_type"><?php esc_html_e( 'Pricing type', 'tch' ); ?></label></th>
				<td><select name="tch_pricing_type" id="tch_pricing_type">
					<option value="sized" <?php selected( $type, 'sized' ); ?>><?php esc_html_e( 'Cake – priced by size', 'tch' ); ?></option>
					<option value="per_unit" <?php selected( $type, 'per_unit' ); ?>><?php esc_html_e( 'Per piece (e.g. cupcakes)', 'tch' ); ?></option>
					<option value="bundle" <?php selected( $type, 'bundle' ); ?>><?php esc_html_e( 'Per pack (e.g. cookies, 3 for UGX 1,000)', 'tch' ); ?></option>
				</select></td></tr>
			<tr><th><label for="tch_unit_price"><?php esc_html_e( 'Price per piece / per pack (UGX)', 'tch' ); ?></label></th>
				<td><input type="number" min="0" step="50" name="tch_unit_price" id="tch_unit_price" value="<?php echo esc_attr( $unit ); ?>"> <span class="description"><?php esc_html_e( 'Not used for cakes priced by size.', 'tch' ); ?></span></td></tr>
			<tr><th><label for="tch_bundle_size"><?php esc_html_e( 'Pieces per pack', 'tch' ); ?></label></th>
				<td><input type="number" min="1" name="tch_bundle_size" id="tch_bundle_size" value="<?php echo esc_attr( $bundle ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'Sizes offered', 'tch' ); ?></th><td>
				<?php foreach ( self::sizes( false ) as $s ) : ?>
					<label style="margin-right:12px"><input type="checkbox" name="tch_allowed_sizes[]" value="<?php echo esc_attr( $s->id ); ?>" <?php checked( in_array( (int) $s->id, $allowed, true ) ); ?>> <?php echo esc_html( $s->name . ' ' . $s->weight_label . ' (' . tch_money( $s->price ) . ')' ); ?></label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Leave all unticked to offer every active size.', 'tch' ); ?></p></td></tr>
			<tr><th><?php esc_html_e( 'Flavours', 'tch' ); ?></th><td><label><input type="checkbox" name="tch_uses_flavours" value="1" <?php checked( $flav ); ?>> <?php esc_html_e( 'Customer chooses a flavour', 'tch' ); ?></label></td></tr>
			<tr><th><?php esc_html_e( 'Quantity limits (pieces)', 'tch' ); ?></th><td>
				<label><?php esc_html_e( 'Min', 'tch' ); ?> <input type="number" min="1" name="tch_min_qty" value="<?php echo esc_attr( $min ); ?>" style="width:90px"></label>
				<label><?php esc_html_e( 'Max', 'tch' ); ?> <input type="number" min="1" name="tch_max_qty" value="<?php echo esc_attr( $max ); ?>" style="width:90px"></label></td></tr>
			<tr><th><?php esc_html_e( 'Online ordering', 'tch' ); ?></th><td><label><input type="checkbox" name="tch_orderable" value="1" <?php checked( $orderable ); ?>> <?php esc_html_e( 'Customers can order this online', 'tch' ); ?></label></td></tr>
		</table>
		<?php
	}

	/**
	 * Saves pricing meta (nonce + capability checked).
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post.
	 */
	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['tch_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tch_product_nonce'] ) ), 'tch_save_product' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$type = isset( $_POST['tch_pricing_type'] ) ? sanitize_key( $_POST['tch_pricing_type'] ) : 'per_unit';
		$type = in_array( $type, array( 'sized', 'per_unit', 'bundle' ), true ) ? $type : 'per_unit';
		update_post_meta( $post_id, '_tch_pricing_type', $type );
		update_post_meta( $post_id, '_tch_unit_price', isset( $_POST['tch_unit_price'] ) ? absint( $_POST['tch_unit_price'] ) : 0 );
		update_post_meta( $post_id, '_tch_bundle_size', max( 1, isset( $_POST['tch_bundle_size'] ) ? absint( $_POST['tch_bundle_size'] ) : 1 ) );
		update_post_meta( $post_id, '_tch_uses_flavours', empty( $_POST['tch_uses_flavours'] ) ? 0 : 1 );
		update_post_meta( $post_id, '_tch_allowed_sizes', isset( $_POST['tch_allowed_sizes'] ) ? array_map( 'absint', (array) $_POST['tch_allowed_sizes'] ) : array() );
		$min = max( 1, isset( $_POST['tch_min_qty'] ) ? absint( $_POST['tch_min_qty'] ) : 1 );
		$max = max( $min, isset( $_POST['tch_max_qty'] ) ? absint( $_POST['tch_max_qty'] ) : 200 );
		update_post_meta( $post_id, '_tch_min_qty', $min );
		update_post_meta( $post_id, '_tch_max_qty', $max );
		update_post_meta( $post_id, '_tch_orderable', empty( $_POST['tch_orderable'] ) ? '0' : '1' );
	}

	/**
	 * Admin list columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			if ( 'title' === $k ) {
				$new['tch_thumb'] = __( 'Photo', 'tch' );
			}
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['tch_price'] = __( 'Price', 'tch' );
			}
		}
		return $new;
	}

	/**
	 * Column output.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post.
	 */
	public static function column( $col, $post_id ) {
		if ( 'tch_thumb' === $col ) {
			echo get_the_post_thumbnail( $post_id, array( 56, 56 ), array( 'style' => 'border-radius:6px;object-fit:cover' ) );
		}
		if ( 'tch_price' === $col ) {
			$cfg = self::config( $post_id );
			echo $cfg ? esc_html( self::price_summary( $cfg ) ) : '—';
		}
	}
}
