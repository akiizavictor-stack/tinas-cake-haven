<?php
/**
 * One-click site builder: imports the photos, creates products, pages, menus,
 * blog posts and core settings so the site can be rebuilt on any server.
 * Safe to run more than once (existing items are reused, not duplicated).
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Setup.
 */
class TCH_Setup {

	/**
	 * Attachment ids by key.
	 *
	 * @var array<string,int>
	 */
	private static $img = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_tch_run_setup', array( __CLASS__, 'run_action' ) );
	}

	/**
	 * Menu item (administrators only).
	 */
	public static function menu() {
		add_submenu_page( 'tch-dashboard', __( 'Website setup', 'tch' ), __( 'Website setup', 'tch' ), 'manage_options', 'tch-setup', array( __CLASS__, 'page' ) );
	}

	/**
	 * Screen.
	 */
	public static function page() {
		$done = get_option( 'tch_setup_done' );
		?>
		<div class="wrap tch-wrap">
			<h1><?php esc_html_e( 'Website setup', 'tch' ); ?></h1>
			<?php if ( isset( $_GET['done'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Setup finished. Visit the site to check it.', 'tch' ); ?> <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'View site', 'tch' ); ?></a></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Creates the pages, menus, products, photos, blog posts and settings for Tina\'s Cake Haven. Existing items are reused, so running it again will not create duplicates.', 'tch' ); ?></p>
			<p><?php echo $done ? esc_html( sprintf( /* translators: %s date */ __( 'Last run: %s', 'tch' ), $done ) ) : esc_html__( 'Not run yet.', 'tch' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'tch_run_setup' ); ?>
				<input type="hidden" name="action" value="tch_run_setup">
				<p><label><input type="checkbox" name="reset_content" value="1"> <?php esc_html_e( 'Also overwrite the text of the standard pages with the original content (your edits to those pages will be lost)', 'tch' ); ?></label></p>
				<?php submit_button( __( 'Run setup', 'tch' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handler.
	 */
	public static function run_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'tch' ), 403 );
		}
		check_admin_referer( 'tch_run_setup' );
		self::run( ! empty( $_POST['reset_content'] ) );
		wp_safe_redirect( admin_url( 'admin.php?page=tch-setup&done=1' ) );
		exit;
	}

	/**
	 * Runs every step (also callable from WP-CLI: wp eval 'TCH_Setup::run(true);').
	 *
	 * @param bool $reset Overwrite page content.
	 */
	public static function run( $reset = false ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore
		}
		TCH_Install::create_tables();
		TCH_Install::seed();
		TCH_Install::roles();
		self::settings();
		self::images();
		self::products();
		$pages = self::pages( $reset );
		self::posts();
		self::menus( $pages );
		self::seo_meta();
		flush_rewrite_rules();
		update_option( 'tch_setup_done', current_time( 'mysql' ) );
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	/**
	 * Core options.
	 */
	private static function settings() {
		update_option( 'blogname', "Tina's Cake Haven" );
		update_option( 'blogdescription', 'Sweet Moments, Always! Birthday cakes, cupcakes and cookies in Kibimba, Fort Portal, Uganda.' );
		update_option( 'timezone_string', 'Africa/Kampala' );
		update_option( 'date_format', 'j F Y' );
		update_option( 'time_format', 'g:i a' );
		update_option( 'start_of_week', 1 );
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( 'default_comment_status', 'closed' );
		update_option( 'default_ping_status', 'closed' );
		update_option( 'comment_moderation', 1 );
		update_option( 'users_can_register', 1 );
		update_option( 'default_role', 'tch_customer' );
		update_option( 'blog_public', 1 );
		if ( ! get_option( 'tch_settings' ) ) {
			update_option( 'tch_settings', TCH_Settings::defaults() );
		}
		$cat = get_category_by_slug( 'uncategorized' );
		if ( $cat ) {
			wp_update_term( $cat->term_id, 'category', array( 'name' => 'News', 'slug' => 'news' ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Media
	 * ------------------------------------------------------------------ */

	/**
	 * Photo register: file => [key, title, alt, caption, illustrative?].
	 *
	 * @return array
	 */
	private static function image_list() {
		$stock = 'Illustrative photo';
		return array(
			'hero-chocolate-happy-birthday-cake.webp'       => array( 'hero', 'Chocolate Happy Birthday cake', 'Chocolate drip birthday cake with piped rosettes and Happy Birthday writing', '', false ),
			'floral-happy-birthday-cake.webp'               => array( 'floral', 'Floral Happy Birthday cake', 'White birthday cake decorated with pink and yellow buttercream roses and Happy Birthday writing', '', false ),
			'chocolate-cherry-layer-cake.webp'              => array( 'cherry', 'Chocolate cherry layer cake', 'Chocolate layer cake topped with whipped cream and fresh cherries', '', false ),
			'pink-and-turquoise-rosette-birthday-cake.webp' => array( 'rosette', 'Pink and turquoise rosette cake', 'Two-tier birthday cake with turquoise rosettes and a pink flower and bee top tier', '', false ),
			'colourful-drip-birthday-cake-with-candles.webp' => array( 'drip', 'Colourful drip birthday cake', 'Three-tier colourful drip birthday cake with candles and sweets', '', false ),
			'vanilla-cupcakes-with-sprinkles.webp'          => array( 'cupcake_vanilla', 'Vanilla cupcakes with sprinkles', 'Vanilla cupcakes with swirled white frosting and rainbow sprinkles', '', false ),
			'chocolate-swirl-cupcake.webp'                  => array( 'cupcake_choc', 'Chocolate swirl cupcake', 'Chocolate cupcake with a tall chocolate frosting swirl', '', false ),
			'chocolate-chip-cookies.webp'                   => array( 'cookies', 'Chocolate chip cookies', 'Stack of golden chocolate chip cookies', '', false ),
			'chocolate-chip-cookies-on-plate.webp'          => array( 'cookies_plate', 'Chocolate chip cookies on a plate', 'Plate of freshly baked chocolate chip cookies', '', false ),
			'owner-bento-anniversary-cake.webp'            => array( 'stock_small', 'Bento Anniversary Cake', 'Square celebration and birthday bento cake with pink and white piping and gold pearls', '', false ),
			'owner-butterfly-birthday-cake.webp'           => array( 'stock_slice', 'Pink Butterfly Birthday Cake', 'White birthday cake decorated with pink butterflies and floral toppers', '', false ),
			'owner-alora-pink-birthday-cake.webp'          => array( 'stock_first', 'Alora Pink Birthday Cake', 'Pink birthday cake with teddy bear topper, clouds and gold candle', '', false ),
			'owner-butterfly-birthday-cake.webp'           => array( 'stock_flour', 'Kitchen Cake Creations', 'Freshly baked celebratory cake decorated in Tina\'s Cake Haven kitchen', '', false ),
			'tinas-cake-haven-logo.webp'                    => array( 'logo', "Tina's Cake Haven logo", "Tina's Cake Haven logo – Sweet Moments, Always!", '', false ),
			'site-icon.png'                                 => array( 'icon', 'Site icon', "Tina's Cake Haven", '', false ),
		);
	}

	/**
	 * Imports seed photos into the Media Library once.
	 */
	private static function images() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$map = get_option( 'tch_images', array() );
		foreach ( self::image_list() as $file => $meta ) {
			$key = $meta[0];
			if ( ! empty( $map[ $key ] ) && get_post( $map[ $key ] ) ) {
				self::$img[ $key ] = (int) $map[ $key ];
				continue;
			}
			$src = TCH_DIR . 'seed/images/' . $file;
			if ( ! is_readable( $src ) ) {
				continue;
			}
			$tmp = wp_tempnam( $file );
			copy( $src, $tmp );
			$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0, $meta[1] );
			if ( is_wp_error( $id ) ) {
				wp_delete_file( $tmp );
				continue;
			}
			update_post_meta( $id, '_wp_attachment_image_alt', $meta[2] );
			wp_update_post( array( 'ID' => $id, 'post_excerpt' => $meta[3] ) );
			if ( $meta[4] ) {
				update_post_meta( $id, '_tch_illustrative', 1 );
			}
			$map[ $key ]       = $id;
			self::$img[ $key ] = $id;
		}
		update_option( 'tch_images', $map );
		if ( ! empty( self::$img['icon'] ) ) {
			update_option( 'site_icon', self::$img['icon'] );
		}
		if ( ! empty( self::$img['logo'] ) ) {
			update_option( 'site_logo', self::$img['logo'] );
			set_theme_mod( 'custom_logo', self::$img['logo'] );
		}
	}

	/**
	 * Attachment URL.
	 *
	 * @param string $key  Key.
	 * @param string $size Size.
	 * @return string
	 */
	private static function url( $key, $size = 'large' ) {
		return empty( self::$img[ $key ] ) ? '' : (string) wp_get_attachment_image_url( self::$img[ $key ], $size );
	}

	/* ------------------------------------------------------------------
	 * Products
	 * ------------------------------------------------------------------ */

	/**
	 * Categories and products with verified prices.
	 */
	private static function products() {
		$cats = array(
			'birthday-cakes' => array( 'Birthday Cakes', 'Freshly baked birthday cakes in four sizes and five flavours.' ),
			'cupcakes'       => array( 'Cupcakes', 'Cupcakes at UGX 1,000 each.' ),
			'cookies'        => array( 'Cookies', 'Cookies in packs of 3, 6 or 9.' ),
		);
		$term_ids = array();
		foreach ( $cats as $slug => $c ) {
			$t = term_exists( $slug, TCH_Catalogue::TAX );
			if ( ! $t ) {
				$t = wp_insert_term( $c[0], TCH_Catalogue::TAX, array( 'slug' => $slug, 'description' => $c[1] ) );
			}
			if ( ! is_wp_error( $t ) ) {
				$term_ids[ $slug ] = (int) $t['term_id'];
			}
		}
		$sizes   = TCH_Catalogue::sizes( false );
		$bento   = array();
		foreach ( $sizes as $s ) {
			if ( 'Bento' === $s->name ) {
				$bento[] = (int) $s->id;
			}
		}
		$products = array(
			'birthday-cake'            => array(
				'title'   => 'Birthday Cake',
				'excerpt' => 'A freshly baked birthday cake in your choice of size and flavour – from a 500g bento cake to a 2kg extra-large cake for a big celebration.',
				'content' => "<!-- wp:paragraph -->\n<p>Celebrate with a freshly baked birthday cake from Tina's Cake Haven in Kibimba, Fort Portal. Choose the size that suits your party and one of our five flavours: vanilla, strawberry, orange, chocolate or mango.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>The price depends only on the size, so every flavour costs the same. Need a special theme, colours or a photo design? Request a <a href=\"" . esc_url( home_url( '/customized-birthday-cakes/' ) ) . "\">customized birthday cake</a> at least 24 hours in advance.</p>\n<!-- /wp:paragraph -->",
				'cat'     => 'birthday-cakes',
				'img'     => 'floral',
				'meta'    => array( '_tch_pricing_type' => 'sized', '_tch_uses_flavours' => 1, '_tch_allowed_sizes' => array(), '_tch_min_qty' => 1, '_tch_max_qty' => 10 ),
				'order'   => 1,
			),
			'bento-cake'               => array(
				'title'   => 'Bento Cake (500g)',
				'excerpt' => 'A cute 500g bento cake – the perfect small celebration cake for one or two people, in any of our five flavours.',
				'content' => "<!-- wp:paragraph -->\n<p>Our bento cake is a small 500g cake, ideal for a personal birthday surprise, a gift or a small celebration. Choose vanilla, strawberry, orange, chocolate or mango.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Baked fresh to order in our Fort Portal kitchen with your choice of personalized message and beautiful piped decorations.</p>\n<!-- /wp:paragraph -->",
				'cat'     => 'birthday-cakes',
				'img'     => 'stock_small',
				'meta'    => array( '_tch_pricing_type' => 'sized', '_tch_uses_flavours' => 1, '_tch_allowed_sizes' => $bento, '_tch_min_qty' => 1, '_tch_max_qty' => 20 ),
				'order'   => 2,
			),
			'customized-birthday-cake' => array(
				'title'   => 'Customized Birthday Cake',
				'excerpt' => 'Your theme, your colours, your message. Describe your dream birthday cake or upload a reference photo – order at least 24 hours in advance.',
				'content' => "<!-- wp:paragraph -->\n<p>Tell us about the birthday you are celebrating – the theme, colours, decorations and the message you would like on the cake – and we will bake a customized birthday cake for you.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Customized birthday cakes must be ordered at least 24 hours before you need them. Choose any of our four sizes and five flavours.</p>\n<!-- /wp:paragraph -->",
				'cat'     => 'birthday-cakes',
				'img'     => 'rosette',
				'meta'    => array( '_tch_pricing_type' => 'sized', '_tch_uses_flavours' => 1, '_tch_allowed_sizes' => array(), '_tch_min_qty' => 1, '_tch_max_qty' => 1 ),
				'order'   => 3,
				'custom'  => true,
			),
			'cupcakes'                 => array(
				'title'   => 'Cupcakes',
				'excerpt' => 'Soft, freshly baked cupcakes topped with frosting – UGX 1,000 each. Order as many as you need for parties, school events or treats.',
				'content' => "<!-- wp:paragraph -->\n<p>Our cupcakes are freshly baked and topped with a swirl of frosting. At UGX 1,000 each they are perfect for birthday parties, school events, office treats and gifts.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Order any quantity online, or WhatsApp us for large party orders.</p>\n<!-- /wp:paragraph -->",
				'cat'     => 'cupcakes',
				'img'     => 'cupcake_vanilla',
				'meta'    => array( '_tch_pricing_type' => 'per_unit', '_tch_unit_price' => 1000, '_tch_uses_flavours' => 0, '_tch_min_qty' => 1, '_tch_max_qty' => 200 ),
				'order'   => 4,
			),
			'cookies'                  => array(
				'title'   => 'Cookies',
				'excerpt' => 'Homemade cookies sold in packs: 3 cookies for UGX 1,000, 6 for UGX 2,000 or 9 for UGX 3,000.',
				'content' => "<!-- wp:paragraph -->\n<p>Golden, homemade cookies – a sweet snack for home, school or the office. Cookies are sold in packs of three: 3 cookies for UGX 1,000, 6 cookies for UGX 2,000 and 9 cookies for UGX 3,000.</p>\n<!-- /wp:paragraph -->",
				'cat'     => 'cookies',
				'img'     => 'cookies_plate',
				'meta'    => array( '_tch_pricing_type' => 'bundle', '_tch_unit_price' => 1000, '_tch_bundle_size' => 3, '_tch_uses_flavours' => 0, '_tch_min_qty' => 3, '_tch_max_qty' => 300 ),
				'order'   => 5,
			),
		);
		foreach ( $products as $slug => $p ) {
			$existing = get_page_by_path( $slug, OBJECT, TCH_Catalogue::PT );
			$id       = $existing ? $existing->ID : wp_insert_post(
				array(
					'post_type'    => TCH_Catalogue::PT,
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $p['title'],
					'post_excerpt' => $p['excerpt'],
					'post_content' => $p['content'],
					'menu_order'   => $p['order'],
				)
			);
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			if ( ! $existing ) {
				foreach ( $p['meta'] as $k => $v ) {
					update_post_meta( $id, $k, $v );
				}
				update_post_meta( $id, '_tch_orderable', '1' );
				if ( ! empty( self::$img[ $p['img'] ] ) ) {
					set_post_thumbnail( $id, self::$img[ $p['img'] ] );
				}
			}
			if ( isset( $term_ids[ $p['cat'] ] ) ) {
				wp_set_object_terms( $id, array( $term_ids[ $p['cat'] ] ), TCH_Catalogue::TAX );
			}
			if ( ! empty( $p['custom'] ) ) {
				update_option( 'tch_custom_product_id', (int) $id );
			}
		}
	}

	/* ------------------------------------------------------------------
	 * Block markup helpers (core block serialisation)
	 * ------------------------------------------------------------------ */

	/** Paragraph. */
	private static function p( $html, $class = '' ) {
		return $class ? "<!-- wp:paragraph {\"className\":\"$class\"} -->\n<p class=\"$class\">$html</p>\n<!-- /wp:paragraph -->\n\n" : "<!-- wp:paragraph -->\n<p>$html</p>\n<!-- /wp:paragraph -->\n\n";
	}

	/** Heading. */
	private static function h( $text, $level = 2, $class = '' ) {
		$attrs = array();
		if ( 2 !== $level ) {
			$attrs['level'] = $level;
		}
		if ( $class ) {
			$attrs['className'] = $class;
		}
		$json = $attrs ? ' ' . wp_json_encode( $attrs ) : '';
		$cls  = trim( 'wp-block-heading ' . $class );
		return "<!-- wp:heading$json -->\n<h$level class=\"$cls\">$text</h$level>\n<!-- /wp:heading -->\n\n";
	}

	/** Buttons: [ [label, url, style(''|'outline'|'whatsapp')] ]. */
	private static function buttons( $btns, $class = '' ) {
		$out = $class ? "<!-- wp:buttons {\"className\":\"$class\"} -->\n<div class=\"wp-block-buttons $class\">" : "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">";
		foreach ( $btns as $b ) {
			$style = isset( $b[2] ) && $b[2] ? 'is-style-' . $b[2] : '';
			$rel   = 0 === strpos( $b[1], 'https://wa.me' ) ? ' target="_blank" rel="noreferrer noopener"' : '';
			$out  .= $style ? "<!-- wp:button {\"className\":\"$style\"} -->\n<div class=\"wp-block-button $style\">" : "<!-- wp:button -->\n<div class=\"wp-block-button\">";
			$out  .= '<a class="wp-block-button__link wp-element-button" href="' . esc_url( $b[1] ) . "\"$rel>" . esc_html( $b[0] ) . "</a></div>\n<!-- /wp:button -->";
		}
		return $out . "</div>\n<!-- /wp:buttons -->\n\n";
	}

	/** Group. */
	private static function group( $inner, $class = '', $tag = 'div' ) {
		$attrs = array();
		if ( 'div' !== $tag ) {
			$attrs['tagName'] = $tag;
		}
		if ( $class ) {
			$attrs['className'] = $class;
		}
		$attrs['layout'] = array( 'type' => 'constrained' );
		$cls             = trim( 'wp-block-group ' . $class );
		return '<!-- wp:group ' . wp_json_encode( $attrs ) . " -->\n<$tag class=\"$cls\">$inner</$tag>\n<!-- /wp:group -->\n\n";
	}

	/** Columns. */
	private static function cols( $cols, $class = '' ) {
		$out = $class ? "<!-- wp:columns {\"className\":\"$class\"} -->\n<div class=\"wp-block-columns $class\">" : "<!-- wp:columns -->\n<div class=\"wp-block-columns\">";
		foreach ( $cols as $c ) {
			$out .= "<!-- wp:column -->\n<div class=\"wp-block-column\">$c</div>\n<!-- /wp:column -->";
		}
		return $out . "</div>\n<!-- /wp:columns -->\n\n";
	}

	/** Image. */
	private static function img( $key, $class = '', $size = 'large', $caption = true ) {
		if ( empty( self::$img[ $key ] ) ) {
			return '';
		}
		$id    = self::$img[ $key ];
		$alt   = esc_attr( get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		$cap   = $caption ? wp_get_attachment_caption( $id ) : '';
		$attrs = array( 'id' => $id, 'sizeSlug' => $size, 'linkDestination' => 'none' );
		if ( $class ) {
			$attrs['className'] = $class;
		}
		$cls = trim( "wp-block-image size-$size $class" );
		$fig = $cap ? '<figcaption class="wp-element-caption">' . esc_html( $cap ) . '</figcaption>' : '';
		return '<!-- wp:image ' . wp_json_encode( $attrs ) . " -->\n<figure class=\"$cls\"><img src=\"" . esc_url( self::url( $key, $size ) ) . "\" alt=\"$alt\" class=\"wp-image-$id\"/>$fig</figure>\n<!-- /wp:image -->\n\n";
	}

	/** Shortcode block. */
	private static function sc( $code ) {
		return "<!-- wp:shortcode -->\n$code\n<!-- /wp:shortcode -->\n\n";
	}

	/** List. */
	private static function ul( $items, $class = '' ) {
		$out = $class ? "<!-- wp:list {\"className\":\"$class\"} -->\n<ul class=\"wp-block-list $class\">" : "<!-- wp:list -->\n<ul class=\"wp-block-list\">";
		foreach ( $items as $i ) {
			$out .= "<!-- wp:list-item -->\n<li>$i</li>\n<!-- /wp:list-item -->";
		}
		return $out . "</ul>\n<!-- /wp:list -->\n\n";
	}

	/** Gallery of attachment keys. */
	private static function gallery( $keys, $columns = 3, $class = '' ) {
		$attrs = array( 'columns' => $columns, 'linkTo' => 'none' );
		if ( $class ) {
			$attrs['className'] = $class;
		}
		$out = '<!-- wp:gallery ' . wp_json_encode( $attrs ) . " -->\n<figure class=\"" . trim( "wp-block-gallery has-nested-images columns-$columns is-cropped $class" ) . '">';
		foreach ( $keys as $k ) {
			$out .= self::img( $k, '', 'large', false );
		}
		return $out . "</figure>\n<!-- /wp:gallery -->\n\n";
	}

	/** Details (FAQ). */
	private static function faq( $q, $a ) {
		return "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>$q</summary>" . self::p( $a ) . "</details>\n<!-- /wp:details -->\n\n";
	}

	/* ------------------------------------------------------------------
	 * Pages
	 * ------------------------------------------------------------------ */

	/**
	 * Creates/updates pages.
	 *
	 * @param bool $reset Overwrite content.
	 * @return array<string,int>
	 */
	private static function pages( $reset ) {
		$wa    = tch_whatsapp_link( "Hello Tina's Cake Haven, I would like to order a cake." );
		$defs  = array(
			'home'      => array( 'Home', 'home', self::content_home( $wa ), "Order fresh birthday cakes, cupcakes and cookies in Kibimba, Fort Portal. Five flavours, clear prices from UGX 35,000 and customized birthday cakes." ),
			'about'     => array( 'About Us', 'about-us', self::content_about(), "Meet Tina's Cake Haven, a home of fresh birthday cakes, cupcakes and cookies in Kibimba, Fort Portal, owned by Aluka Christin Hellen since February 2026." ),
			'products'  => array( 'Our Products', 'our-products', self::content_products(), 'Birthday cakes from UGX 35,000, cupcakes at UGX 1,000 and cookies from UGX 1,000 for 3. See all sizes, flavours and prices and order online.' ),
			'custom'    => array( 'Customized Birthday Cakes', 'customized-birthday-cakes', self::content_custom(), 'Request a customized birthday cake in Fort Portal: choose the size and flavour, describe your design or upload a photo. Order at least 24 hours ahead.' ),
			'gallery'   => array( 'Gallery', 'gallery', self::content_gallery(), "Photos of birthday cakes, cupcakes and cookies from Tina's Cake Haven, Kibimba, Fort Portal." ),
			'order'     => array( 'Order Online', 'order-online', self::content_order(), 'Order birthday cakes, cupcakes and cookies online. See your total before you submit, choose collection or delivery and pay by Mobile Money.' ),
			'account'   => array( 'My Account', 'my-account', self::sc( '[tch_account]' ), 'Log in or create an account to see your orders.' ),
			'my-orders' => array( 'My Orders', 'my-orders', self::p( 'Here are your most recent orders, their payment status and any balance still to pay.', 'tch-lead' ) . self::sc( '[tch_my_orders]' ), 'Your orders with Tina\'s Cake Haven.' ),
			'track'     => array( 'Order Tracking', 'order-tracking', self::p( 'Enter your order reference (for example TCH-260214-AB3CD) and the phone number you used when ordering.', 'tch-lead' ) . self::sc( '[tch_track_order]' ), 'Track your Tina\'s Cake Haven order with your order reference and phone number.' ),
			'blog'      => array( 'Blog', 'blog', '', 'Cake tips, birthday ideas and news from Tina\'s Cake Haven in Fort Portal.' ),
			'contact'   => array( 'Contact Us', 'contact-us', self::content_contact( $wa ), "Call or WhatsApp Tina's Cake Haven on +256 792104798, or send us a message. We are in Kibimba, Fort Portal, Uganda." ),
			'privacy'   => array( 'Privacy Policy', 'privacy-policy', self::content_privacy(), "How Tina's Cake Haven collects, uses and protects your personal information." ),
			'terms'     => array( 'Terms and Conditions', 'terms-and-conditions', self::content_terms(), "Ordering, payment, collection and delivery terms for Tina's Cake Haven." ),
		);
		$ids   = get_option( 'tch_pages', array() );
		foreach ( $defs as $key => $d ) {
			$id = ! empty( $ids[ $key ] ) && get_post( $ids[ $key ] ) ? (int) $ids[ $key ] : 0;
			if ( ! $id ) {
				$found = get_page_by_path( $d[1] );
				$id    = $found ? $found->ID : 0;
			}
			$data = array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $d[0],
				'post_name'    => $d[1],
				'post_content' => $d[2],
				'post_excerpt' => $d[3],
			);
			if ( $id ) {
				if ( $reset ) {
					$data['ID'] = $id;
					wp_update_post( $data );
				}
			} else {
				$id = wp_insert_post( $data );
			}
			$ids[ $key ] = (int) $id;
		}
		update_option( 'tch_pages', $ids );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home'] );
		update_option( 'page_for_posts', $ids['blog'] );
		update_option( 'wp_page_for_privacy_policy', $ids['privacy'] );
		// Remove the WordPress sample content.
		$sample = get_page_by_path( 'sample-page' );
		if ( $sample ) {
			wp_delete_post( $sample->ID, true );
		}
		$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
		if ( $hello ) {
			wp_delete_post( $hello->ID, true );
		}
		return $ids;
	}

	/**
	 * Home page.
	 *
	 * @param string $wa WhatsApp link.
	 * @return string
	 */
	private static function content_home( $wa ) {
		$order  = home_url( '/order-online/' );
		$custom = home_url( '/customized-birthday-cakes/' );
		$hero   = self::cols(
			array(
				self::p( 'Kibimba · Fort Portal · Uganda', 'tch-eyebrow' )
				. self::h( "Tina's Cake Haven", 1, 'tch-hero__title' )
				. self::p( 'Sweet Moments, Always!', 'tch-script' )
				. self::p( 'Freshly baked birthday cakes, cupcakes and cookies – in vanilla, strawberry, orange, chocolate and mango. Order online, see your price straight away, and pay by Mobile Money.', 'tch-hero__text' )
				. self::buttons( array( array( 'Order Now', $order ), array( 'Customized cakes', $custom, 'outline' ), array( 'WhatsApp us', $wa, 'whatsapp' ) ) )
				. self::p( 'Birthday cakes from <strong>UGX 35,000</strong> · Cupcakes <strong>UGX 1,000</strong> · Cookies <strong>3 for UGX 1,000</strong>', 'tch-hero__prices' ),
				self::img( 'hero', 'tch-hero__img', 'large' ),
			),
			'tch-hero__cols'
		);
		$out  = self::group( $hero, 'tch-hero alignfull', 'section' );
		$out .= self::group(
			self::cols(
				array(
					self::h( 'Baked fresh', 3 ) . self::p( 'Every order is baked for you – not taken from a shelf.' ),
					self::h( '5 flavours', 3 ) . self::p( 'Vanilla, strawberry, orange, chocolate and mango.' ),
					self::h( 'Your design', 3 ) . self::p( 'Customized birthday cakes with 24 hours\' notice.' ),
					self::h( 'Mobile Money', 3 ) . self::p( 'Pay in full or a 50% deposit, balance on collection.' ),
				),
				'tch-features'
			),
			'tch-strip alignfull',
			'section'
		);
		$out .= self::group(
			self::p( 'What we bake', 'tch-eyebrow has-text-align-center' )
			. self::h( 'Cakes, cupcakes &amp; cookies', 2, 'has-text-align-center' )
			. self::cols(
				array(
					self::img( 'drip', 'tch-cat__img', 'medium_large', false ) . self::h( '<a href="' . esc_url( home_url( '/product-category/birthday-cakes/' ) ) . '">Birthday Cakes</a>', 3 ) . self::p( 'Bento 500g to extra-large 2kg, from UGX 35,000.' ),
					self::img( 'cupcake_choc', 'tch-cat__img', 'medium_large', false ) . self::h( '<a href="' . esc_url( home_url( '/product-category/cupcakes/' ) ) . '">Cupcakes</a>', 3 ) . self::p( 'Frosted cupcakes at UGX 1,000 each.' ),
					self::img( 'cookies', 'tch-cat__img', 'medium_large', false ) . self::h( '<a href="' . esc_url( home_url( '/product-category/cookies/' ) ) . '">Cookies</a>', 3 ) . self::p( '3 for UGX 1,000 · 6 for 2,000 · 9 for 3,000.' ),
				),
				'tch-cats'
			),
			'tch-section',
			'section'
		);
		$out .= self::group(
			self::p( 'Our menu', 'tch-eyebrow has-text-align-center' )
			. self::h( 'Featured products', 2, 'has-text-align-center' )
			. self::sc( '[tch_products limit="5"]' ),
			'tch-section tch-section--cream alignfull',
			'section'
		);
		$out .= self::group(
			self::cols(
				array(
					self::img( 'rosette', 'tch-rounded', 'large', false ),
					self::p( 'Made just for you', 'tch-eyebrow' )
					. self::h( 'Customized birthday cakes', 2 )
					. self::p( 'Choose the size and flavour, describe the theme, colours and message you want, and upload a reference photo if you have one. We will contact you to confirm the details.' )
					. self::ul( array( 'Four sizes: Bento 500g, Medium 1kg, Large 1.5kg, Extra-large 2kg', 'Five flavours – every flavour costs the same', 'Please order at least <strong>24 hours</strong> in advance' ), 'tch-ticks' )
					. self::buttons( array( array( 'Request a custom cake', $custom ) ) ),
				),
				'tch-promo'
			),
			'tch-section',
			'section'
		);
		$out .= self::group(
			self::p( 'Simple, clear prices', 'tch-eyebrow has-text-align-center' )
			. self::h( 'Price list', 2, 'has-text-align-center' )
			. self::sc( '[tch_price_list]' ),
			'tch-section tch-section--pink alignfull',
			'section'
		);
		$out .= self::group(
			self::p( 'Why order from us', 'tch-eyebrow has-text-align-center' )
			. self::h( 'Sweet, simple and reliable', 2, 'has-text-align-center' )
			. self::cols(
				array(
					self::h( 'Clear prices', 3 ) . self::p( 'You see the full price before you order – no surprises.' ),
					self::h( 'Easy online ordering', 3 ) . self::p( 'Order any time, get an order reference and track progress online.' ),
					self::h( 'Flexible payment', 3 ) . self::p( 'Pay the full amount or a 50% deposit by Mobile Money.' ),
					self::h( 'Local &amp; friendly', 3 ) . self::p( 'Based in Kibimba, Fort Portal – call or WhatsApp us any time.' ),
				),
				'tch-why'
			),
			'tch-section',
			'section'
		);
		$out .= self::group(
			self::h( 'From our kitchen', 2, 'has-text-align-center' )
			. self::gallery( array( 'hero', 'cherry', 'cupcake_vanilla', 'cookies_plate', 'drip', 'rosette' ), 3, 'tch-gallery' )
			. self::buttons( array( array( 'See the full gallery', home_url( '/gallery/' ), 'outline' ) ), 'tch-center' ),
			'tch-section tch-section--cream alignfull',
			'section'
		);
		$out .= self::group(
			self::cols(
				array(
					self::h( 'Visit, call or WhatsApp', 2 )
					. self::p( '📍 [tch_info key="location"]' )
					. self::p( '📞 [tch_info key="phone" link="1"]' )
					. self::p( 'We deliver within about 1 km of our premises. Customers further away usually collect their orders, unless we agree another arrangement.' ),
					self::buttons( array( array( 'Order Now', $order ), array( 'WhatsApp us', $wa, 'whatsapp' ), array( 'Find us on the map', tch_setting( 'map_url' ), 'outline' ) ), 'tch-cta-buttons' ),
				),
				'tch-contact-band'
			),
			'tch-section tch-section--choc alignfull',
			'section'
		);
		return $out;
	}

	/** About page. */
	private static function content_about() {
		return self::cols(
			array(
				self::p( 'Our story', 'tch-eyebrow' )
				. self::h( 'A sweet home for celebrations in Fort Portal', 2 )
				. self::p( "Tina's Cake Haven is a home bakery in Kibimba, Fort Portal, Uganda, owned and run by <strong>Aluka Christin Hellen</strong>. The business was established on <strong>10 February 2026</strong> to bring freshly baked cakes and treats to families, friends and colleagues who want to make their special days sweeter." )
				. self::p( 'We bake birthday cakes in four sizes – from a 500g bento cake to a 2kg extra-large cake – in five flavours: vanilla, strawberry, orange, chocolate and mango. We also bake cupcakes and cookies, and we create customized birthday cakes when you give us at least 24 hours\' notice.' ),
				self::img( 'logo', 'tch-about__logo', 'large', false ),
			),
			'tch-about'
		)
		. self::group(
			self::h( 'Our mission', 2 )
			. self::p( 'To bake fresh, beautiful and delicious cakes, cupcakes and cookies at clear and fair prices, and to make ordering easy – so every customer in and around Fort Portal can celebrate their sweet moments, always.' )
			. self::cols(
				array(
					self::h( 'What we offer', 3 ) . self::ul( array( 'Birthday cakes: Bento, Medium, Large and Extra-large', 'Customized birthday cakes (24 hours\' notice)', 'Cupcakes – UGX 1,000 each', 'Cookies – 3 for UGX 1,000' ) ),
					self::h( 'How it works', 3 ) . self::ul( array( 'Order online, by phone or on WhatsApp', 'Pay by Mobile Money – in full or a 50% deposit', 'Collect from Kibimba, or ask for delivery within about 1 km', 'Track your order online with your order reference' ) ),
				)
			),
			'tch-panel-group'
		)
		. self::img( 'stock_flour', 'tch-rounded', 'large' )
		. self::buttons( array( array( 'See our products', home_url( '/our-products/' ) ), array( 'Contact us', home_url( '/contact-us/' ), 'outline' ) ) );
	}

	/** Products page. */
	private static function content_products() {
		return self::p( 'Choose from our birthday cakes, cupcakes and cookies. Prices are shown in Uganda shillings (UGX). Cake prices depend on the size only – every flavour costs the same.', 'tch-lead' )
			. self::sc( '[tch_products]' )
			. self::group( self::h( 'Full price list', 2, 'has-text-align-center' ) . self::sc( '[tch_price_list]' ), 'tch-section tch-section--pink alignfull', 'section' )
			. self::h( 'Good to know', 2 )
			. self::faq( 'Which flavours are available?', 'Vanilla, strawberry, orange, chocolate and mango. All flavours cost the same.' )
			. self::faq( 'How far in advance should I order?', 'Customized birthday cakes must be ordered at least 24 hours in advance. For other orders, please order as early as you can and choose the date and time you need your order.' )
			. self::faq( 'Do you deliver?', 'Delivery is generally available within about 1 km of our premises in Kibimba. Customers further away usually collect their orders, unless another arrangement is agreed with us.' )
			. self::faq( 'How do I pay?', 'By Mobile Money. You can pay the full amount or a 50% deposit, with the balance paid on delivery or collection. After paying, enter your transaction ID on your order page – we confirm each payment on our Mobile Money account.' );
	}

	/** Custom cakes page. */
	private static function content_custom() {
		return self::cols(
			array(
				self::p( 'Your birthday cake, your way. Tell us the theme, colours, decorations and message you would like, and choose the size and flavour.', 'tch-lead' )
				. self::ul( array( '<strong>1.</strong> Choose the size and flavour', '<strong>2.</strong> Describe your design and add a reference photo (optional)', '<strong>3.</strong> Pick the date and time – at least 24 hours from now', '<strong>4.</strong> Pay in full or a 50% deposit by Mobile Money', '<strong>5.</strong> We confirm the details with you and bake your cake' ), 'tch-steps-list' ),
				self::img( 'drip', 'tch-rounded', 'large', false ),
			)
		)
		. self::sc( '[tch_custom_cake_form]' );
	}

	/** Gallery page (client photos only). */
	private static function content_gallery() {
		return self::p( "A taste of what comes out of our kitchen – birthday cakes, cupcakes and cookies. Follow us or WhatsApp us to see more of our latest cakes.", 'tch-lead' )
			. self::gallery( array( 'hero', 'floral', 'drip', 'rosette', 'cherry', 'cupcake_vanilla', 'cupcake_choc', 'cookies', 'cookies_plate' ), 3, 'tch-gallery' )
			. self::buttons( array( array( 'Order a cake', home_url( '/order-online/' ) ), array( 'Request a custom design', home_url( '/customized-birthday-cakes/' ), 'outline' ) ) );
	}

	/** Order page. */
	private static function content_order() {
		return self::p( 'Choose your items, tell us when you need them and whether you will collect or need delivery. You will see your total straight away, and receive an order reference.', 'tch-lead' )
			. self::ul( array( 'Prices are calculated by our system from our current price list.', 'Pay by Mobile Money – the full amount or a 50% deposit.', 'Your payment shows as "Pending verification" until we confirm it on our account.' ), 'tch-ticks' )
			. self::sc( '[tch_order_form]' );
	}

	/** Contact page. */
	private static function content_contact( $wa ) {
		return self::cols(
			array(
				self::h( 'Get in touch', 2 )
				. self::p( 'The quickest way to reach us is by phone or WhatsApp.' )
				. self::p( '<strong>Phone / WhatsApp:</strong> [tch_info key="phone" link="1"]' )
				. self::p( '<strong>Location:</strong> [tch_info key="location"]' )
				. self::p( '<strong>Owner:</strong> [tch_info key="owner"]' )
				. self::buttons( array( array( 'WhatsApp us', $wa, 'whatsapp' ), array( 'Open map', tch_setting( 'map_url' ), 'outline' ) ) )
				. self::p( 'Delivery is generally available within about 1 km of our premises. Customers further away usually collect their orders.', 'tch-note' ),
				self::h( 'Send us a message', 2 ) . self::sc( '[tch_contact_form]' ),
			),
			'tch-contact'
		);
	}

	/** Privacy policy. */
	private static function content_privacy() {
		return self::p( 'Last updated: ' . wp_date( 'j F Y' ), 'tch-note' )
			. self::p( "This policy explains how Tina's Cake Haven (\"we\", \"us\"), owned by Aluka Christin Hellen, Kibimba, Fort Portal, Uganda, collects and uses your personal information when you use this website. We handle personal data in line with Uganda's Data Protection and Privacy Act, 2019." )
			. self::h( 'Information we collect', 2 )
			. self::ul( array( 'Your name, phone number and (optionally) email address when you order, register or contact us.', 'Order details: products, cake design descriptions, messages for the cake, dates, and your delivery location if you ask for delivery.', 'Payment information you give us: the Mobile Money transaction ID, the phone number you paid from and, if you choose, a screenshot. We never ask for your Mobile Money PIN.', 'Reference photos you upload for a customized cake.', 'Basic technical data needed to run and protect the website, such as cookies that keep you logged in and security logs.' ) )
			. self::h( 'How we use it', 2 )
			. self::ul( array( 'To prepare, deliver or hand over your order and contact you about it.', 'To check your payment against our Mobile Money account.', 'To manage your customer account and show your order history.', 'To answer your messages.', 'To keep the website secure and prevent abuse.' ) )
			. self::h( 'Sharing', 2 )
			. self::p( 'We do not sell your information. Only the owner and authorised staff can see orders and uploaded photos. Our website hosting and email providers process data on our behalf to run the service.' )
			. self::h( 'How long we keep it', 2 )
			. self::p( 'We keep order and payment records for as long as needed for our business and accounting records, and delete uploaded photos and messages when they are no longer needed.' )
			. self::h( 'Security', 2 )
			. self::p( 'The website uses an encrypted (HTTPS) connection, protected logins and restricted access to customer files. Uploaded photos are stored in a private folder that is not publicly accessible.' )
			. self::h( 'Your rights', 2 )
			. self::p( 'You may ask to see, correct or delete the personal information we hold about you. Contact us on [tch_info key="phone" link="1"].' )
			. self::h( 'Cookies', 2 )
			. self::p( 'We use only the cookies needed for the website to work (for example to keep you logged in and protect forms). We do not use advertising cookies.' );
	}

	/** Terms. */
	private static function content_terms() {
		return self::p( 'Last updated: ' . wp_date( 'j F Y' ), 'tch-note' )
			. self::p( "These terms apply to orders placed with Tina's Cake Haven, Kibimba, Fort Portal, Uganda, through this website, by phone or on WhatsApp." )
			. self::h( '1. Orders', 2 )
			. self::ul( array( 'Every order receives an order reference. An order is only confirmed when we mark it as "Confirmed".', 'Customized birthday cakes must be ordered at least 24 hours before the collection or delivery time.', 'We will contact you if we cannot make an order as requested.' ) )
			. self::h( '2. Prices', 2 )
			. self::ul( array( 'Prices are in Uganda shillings (UGX) and are calculated by our system from our current price list when you place your order.', 'Birthday cakes: Bento 500g UGX 35,000; Medium 1kg UGX 50,000; Large 1.5kg UGX 75,000; Extra-large 2kg UGX 100,000.', 'Cupcakes: UGX 1,000 each. Cookies: 3 for UGX 1,000, 6 for UGX 2,000, 9 for UGX 3,000.', 'If a customized design needs a different price, we will agree it with you before confirming your order.' ) )
			. self::h( '3. Payment', 2 )
			. self::ul( array( 'We accept Mobile Money. You may pay the full amount or a 50% deposit, with the balance paid on delivery or collection.', 'After paying, submit your transaction ID on your order page. A payment counts only after we have verified it on our Mobile Money account; a screenshot alone is not proof of payment.', 'Never share your Mobile Money PIN with anyone. We will never ask for it.' ) )
			. self::h( '4. Collection and delivery', 2 )
			. self::ul( array( 'Delivery is generally available within about 1 km of our premises in Kibimba.', 'Customers outside this area generally collect their orders unless another arrangement is agreed with us.' ) )
			. self::h( '5. Changes and cancellations', 2 )
			. self::p( 'Please contact us as early as possible if you need to change or cancel an order. Because cakes are baked to order, any refund of a payment for an order that has already been prepared is at our discretion and will be discussed with you.' )
			. self::h( '6. Contact', 2 )
			. self::p( 'Questions about these terms? Call or WhatsApp [tch_info key="phone" link="1"].' );
	}

	/* ------------------------------------------------------------------
	 * Blog
	 * ------------------------------------------------------------------ */

	/**
	 * Blog categories and starter posts (owner can edit/delete them).
	 */
	private static function posts() {
		$cats = array( 'cake-tips' => 'Cake Tips', 'birthday-ideas' => 'Birthday Ideas', 'news' => 'News' );
		$ids  = array();
		foreach ( $cats as $slug => $name ) {
			$t = term_exists( $slug, 'category' );
			if ( ! $t ) {
				$t = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
			}
			if ( ! is_wp_error( $t ) ) {
				$ids[ $slug ] = (int) $t['term_id'];
			}
		}
		$posts = array(
			'how-to-choose-the-right-birthday-cake-size-in-fort-portal' => array(
				'How to Choose the Right Birthday Cake Size in Fort Portal',
				'birthday-ideas',
				'stock_first',
				'Planning a party? Our simple guide to choosing the right birthday cake size in Fort Portal – from a 500g bento cake to a 2kg cake – plus flavours, prices and ordering tips.',
				self::article_cake_size(),
			),
			'how-to-keep-your-cake-fresh' => array(
				'How to Keep Your Cake Fresh After Collection',
				'cake-tips',
				'stock_slice',
				'Simple tips to keep your birthday cake, cupcakes and cookies fresh and beautiful from the moment you collect them until the party.',
				self::p( 'You have collected your cake – now let us keep it looking and tasting its best until the celebration.' )
				. self::h( 'On the way home', 2 )
				. self::ul( array( 'Carry the cake box flat, on your lap or on a flat surface – never on a seat that slopes.', 'Keep it out of direct sun. Buttercream softens quickly in the heat.', 'Avoid sudden braking if you are driving or on a boda boda – hold the box level with both hands.' ) )
				. self::h( 'Before the party', 2 )
				. self::ul( array( 'Keep the cake in a cool, shaded place and in its box until you are ready to serve.', 'If your home is warm, a fridge helps keep cream decorations firm. Take the cake out a little before serving so it is not too cold.', 'Keep cupcakes covered so the frosting does not dry out.' ) )
				. self::h( 'Leftovers', 2 )
				. self::ul( array( 'Cover cut surfaces with cling film or keep slices in an airtight container.', 'Store cookies in an airtight tin to keep them crisp.' ) )
				. self::p( 'Questions about your order? <a href="' . esc_url( home_url( '/contact-us/' ) ) . '">Contact us</a> – we are happy to help.' ),
			),
			'welcome-to-tinas-cake-haven' => array(
				"Welcome to Tina's Cake Haven Online",
				'news',
				'hero',
				"Our new website is live! You can now browse our cakes, see clear prices, order online, pay by Mobile Money and track your order.",
				self::p( "Tina's Cake Haven was established on 10 February 2026 in Kibimba, Fort Portal, by Aluka Christin Hellen. Our new website makes it easier than ever to order your favourite treats." )
				. self::h( 'What you can do on the website', 2 )
				. self::ul( array( 'Browse our <a href="' . esc_url( home_url( '/our-products/' ) ) . '">birthday cakes, cupcakes and cookies</a> with clear prices.', '<a href="' . esc_url( home_url( '/order-online/' ) ) . '">Order online</a> and receive an order reference straight away.', 'Request a <a href="' . esc_url( home_url( '/customized-birthday-cakes/' ) ) . '">customized birthday cake</a> (at least 24 hours\' notice).', 'Pay by Mobile Money – the full amount or a 50% deposit – and submit your transaction ID for us to verify.', '<a href="' . esc_url( home_url( '/order-tracking/' ) ) . '">Track your order</a> or create an account to see all your orders.' ) )
				. self::p( 'Thank you for supporting a local Fort Portal business. Sweet Moments, Always!' ),
			),
		);
		foreach ( $posts as $slug => $p ) {
			if ( get_page_by_path( $slug, OBJECT, 'post' ) ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'     => 'post',
					'post_status'   => 'publish',
					'post_name'     => $slug,
					'post_title'    => $p[0],
					'post_excerpt'  => $p[3],
					'post_content'  => $p[4],
					'post_category' => isset( $ids[ $p[1] ] ) ? array( $ids[ $p[1] ] ) : array(),
					'comment_status' => 'closed',
				)
			);
			if ( $id && ! is_wp_error( $id ) && ! empty( self::$img[ $p[2] ] ) ) {
				set_post_thumbnail( $id, self::$img[ $p[2] ] );
			}
		}
	}

	/**
	 * Keyword-targeted article (600+ words): "birthday cake Fort Portal".
	 *
	 * @return string
	 */
	private static function article_cake_size() {
		$order  = esc_url( home_url( '/order-online/' ) );
		$custom = esc_url( home_url( '/customized-birthday-cakes/' ) );
		return self::p( "Choosing a birthday cake should be the fun part of planning a party, but one question always comes up first: <strong>what size of cake do I need?</strong> Order too small and some guests miss out; order too big and you pay for cake you did not need. In this guide we explain the four birthday cake sizes we bake at Tina's Cake Haven in Kibimba, Fort Portal, what each one costs, and how to pick the right one for your celebration." )
			. self::h( 'Our four birthday cake sizes and prices', 2 )
			. self::p( 'Every birthday cake we bake comes in one of four sizes. The price depends only on the size – vanilla, strawberry, orange, chocolate and mango all cost the same.' )
			. self::ul( array( '<strong>Bento – 500g: UGX 35,000.</strong> A small, personal cake.', '<strong>Medium – 1kg: UGX 50,000.</strong> Our most flexible size for family birthdays.', '<strong>Large – 1.5kg: UGX 75,000.</strong> For bigger family gatherings and small parties.', '<strong>Extra-large – 2kg: UGX 100,000.</strong> For school, office and big celebrations.' ) )
			. self::h( '1. Count your guests first', 2 )
			. self::p( 'Start with the number of people who will actually eat cake. Children usually eat smaller slices than adults, and at many birthday parties there are other snacks too, so not everyone takes a large piece. How thickly the cake is cut makes a big difference to how far it goes, so treat any serving numbers as a rough guide. If you are unsure, call or WhatsApp us with your guest list and we will help you choose.' )
			. self::h( '2. Match the size to the occasion', 2 )
			. self::p( '<strong>A bento cake (500g)</strong> is perfect for a surprise at home, a birthday for one or two people, or a thoughtful gift for a friend or colleague. It is small, pretty and easy to carry.' )
			. self::p( '<strong>A medium cake (1kg)</strong> suits a typical family birthday at home. It gives enough room on top for a "Happy Birthday" message and simple decorations.' )
			. self::p( '<strong>A large cake (1.5kg)</strong> is a good choice when relatives and neighbours are joining the celebration, or for a small children\'s party.' )
			. self::p( '<strong>An extra-large cake (2kg)</strong> is the one to choose for a class party, an office celebration or a big family event in Fort Portal where you want everyone to get a slice.' )
			. self::h( '3. Think about the design you want', 2 )
			. self::p( 'If you have a special theme in mind – favourite colours, a cartoon character, a football team, the birthday person\'s age or name – a bigger cake gives more space for decoration. For any special design, use our <a href="' . $custom . '">customized birthday cake form</a>. You can describe the design, add the message you want written on the cake and upload a reference photo. Please order customized birthday cakes <strong>at least 24 hours in advance</strong> so we have time to bake and decorate properly.' )
			. self::h( '4. Pick a flavour everyone will enjoy', 2 )
			. self::p( 'Our five flavours are vanilla, strawberry, orange, chocolate and mango. Vanilla and chocolate are classic crowd-pleasers, while strawberry, orange and mango bring a fresh, fruity taste. Because every flavour costs the same, you can simply choose the one the birthday person loves most.' )
			. self::h( '5. Add cupcakes or cookies for big groups', 2 )
			. self::p( 'For school or office parties, many customers combine a cake with extras. Our cupcakes cost UGX 1,000 each and our cookies come in packs of three for UGX 1,000, so it is easy to make sure every guest gets a treat without ordering a huge cake. You can add them to the same order on our <a href="' . $order . '">Order Online</a> page.' )
			. self::h( '6. Plan collection or delivery', 2 )
			. self::p( 'We deliver within about 1 km of our premises in Kibimba. If you are further away in Fort Portal, you can collect your cake from us, unless we agree another arrangement. Remember to carry the cake flat and out of the sun on the way home.' )
			. self::h( 'Quick checklist before you order', 2 )
			. self::ul( array( 'How many people will eat cake?', 'Which size fits that number and your budget?', 'Which flavour does the birthday person love?', 'Do you need a customized design (order 24 hours ahead)?', 'Will you collect, or are you within our delivery area?', 'Will you pay in full or a 50% deposit by Mobile Money?' ) )
			. self::h( 'Order your birthday cake in Fort Portal today', 2 )
			. self::p( "When you order online you see the exact price before you submit, receive an order reference, and can track your order and payment status at any time. Ready to celebrate? <a href=\"$order\">Order your birthday cake online</a>, or call or WhatsApp us on [tch_info key=\"phone\" link=\"1\"]. Sweet Moments, Always!" );
	}

	/**
	 * Unique meta descriptions (Yoast SEO fields) from each excerpt, without overwriting the owner's edits.
	 */
	private static function seo_meta() {
		$items = get_posts( array( 'post_type' => array( 'page', 'post', TCH_Catalogue::PT ), 'post_status' => 'publish', 'posts_per_page' => 200 ) );
		foreach ( $items as $p ) {
			if ( $p->post_excerpt && ! get_post_meta( $p->ID, '_yoast_wpseo_metadesc', true ) ) {
				update_post_meta( $p->ID, '_yoast_wpseo_metadesc', wp_html_excerpt( $p->post_excerpt, 155, '' ) );
			}
		}
		$article = get_page_by_path( 'how-to-choose-the-right-birthday-cake-size-in-fort-portal', OBJECT, 'post' );
		if ( $article && ! get_post_meta( $article->ID, '_yoast_wpseo_focuskw', true ) ) {
			update_post_meta( $article->ID, '_yoast_wpseo_focuskw', 'birthday cake Fort Portal' );
			update_post_meta( $article->ID, '_yoast_wpseo_title', 'Birthday Cake Sizes & Prices in Fort Portal | Tina\'s Cake Haven' );
		}
		$home = (int) get_option( 'page_on_front' );
		if ( $home && ! get_post_meta( $home, '_yoast_wpseo_title', true ) ) {
			update_post_meta( $home, '_yoast_wpseo_title', 'Birthday Cakes, Cupcakes & Cookies in Fort Portal | Tina\'s Cake Haven' );
		}
	}

	/* ------------------------------------------------------------------
	 * Menus (navigation posts used by the block theme)
	 * ------------------------------------------------------------------ */

	/**
	 * Navigation menus.
	 *
	 * @param array $pages Page ids.
	 */
	private static function menus( $pages ) {
		$link = static function ( $key, $label = '' ) use ( $pages ) {
			$id    = (int) $pages[ $key ];
			$label = $label ? $label : get_the_title( $id );
			return '<!-- wp:navigation-link ' . wp_json_encode( array( 'label' => $label, 'type' => 'page', 'id' => $id, 'url' => get_permalink( $id ), 'kind' => 'post-type' ) ) . ' /-->';
		};
		$main = $link( 'home' ) . $link( 'about' )
			. '<!-- wp:navigation-submenu ' . wp_json_encode( array( 'label' => 'Products', 'type' => 'page', 'id' => (int) $pages['products'], 'url' => get_permalink( $pages['products'] ), 'kind' => 'post-type' ) ) . ' -->'
			. $link( 'products', 'All products' ) . $link( 'custom' ) . $link( 'gallery' )
			. '<!-- /wp:navigation-submenu -->'
			. $link( 'blog' ) . $link( 'contact' )
			. '<!-- wp:navigation-submenu ' . wp_json_encode( array( 'label' => 'My Account', 'type' => 'page', 'id' => (int) $pages['account'], 'url' => get_permalink( $pages['account'] ), 'kind' => 'post-type' ) ) . ' -->'
			. $link( 'account', 'Login / Profile' ) . $link( 'my-orders' ) . $link( 'track' )
			. '<!-- /wp:navigation-submenu -->';
		$footer = $link( 'products' ) . $link( 'custom' ) . $link( 'order' ) . $link( 'track' ) . $link( 'gallery' ) . $link( 'blog' );
		$legal  = $link( 'about' ) . $link( 'contact' ) . $link( 'privacy' ) . $link( 'terms' ) . $link( 'account' );

		foreach ( array( 'tch_nav_main' => array( 'Main menu', $main ), 'tch_nav_footer' => array( 'Footer – shop', $footer ), 'tch_nav_legal' => array( 'Footer – info', $legal ) ) as $opt => $m ) {
			$id   = (int) get_option( $opt );
			$data = array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => $m[0], 'post_content' => $m[1] );
			if ( $id && get_post( $id ) ) {
				$data['ID'] = $id;
				wp_update_post( $data );
			} else {
				$id = wp_insert_post( $data );
				update_option( $opt, (int) $id );
			}
		}
	}
}
