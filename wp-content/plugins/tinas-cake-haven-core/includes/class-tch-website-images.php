<?php
/**
 * Website Images – dashboard-managed images outside the hero slider.
 *
	 * Manages homepage content images and the other website image sections.
	 * The homepage hero slider remains independent and is not changed here.
 *
 * This module is independent of the hero slider (separate option, filter and
 * admin page) and does not modify the theme.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Website Images module.
 */
class TCH_Website_Images {

	const OPTION          = 'tch_home_gallery';
	const CAP             = 'manage_options';
	const MAX_IMAGES      = 24;
	const MAX_BAKE_IMAGES = 3;

	/**
	 * Hooks. Registered for every request (the render filter runs on the
	 * front-end; the admin hooks self-guard with is_admin()).
	 */
	public static function init() {
		add_filter( 'render_block', array( __CLASS__, 'render_gallery_block' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend_assets' ) );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
			add_action( 'admin_post_tch_save_home_gallery', array( __CLASS__, 'save' ) );
			add_action( 'admin_post_tch_reset_home_gallery', array( __CLASS__, 'reset' ) );
			add_action( 'admin_post_tch_save_page_images', array( __CLASS__, 'save_page_images' ) );
			add_action( 'admin_post_tch_save_home_bake', array( __CLASS__, 'save_home_bake_images' ) );
			add_action( 'admin_post_tch_save_gallery_page', array( __CLASS__, 'save_gallery_page' ) );
			add_action( 'admin_post_tch_save_featured_images', array( __CLASS__, 'save_featured_images' ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Front-end data
	 * ------------------------------------------------------------------ */

	/**
	 * The six photos the homepage gallery originally showed, resolved to real
	 * Media Library attachment IDs from the setup option.
	 *
	 * @return array<int,array{id:int}>
	 */
	public static function default_images() {
		$map   = get_option( 'tch_images', array() );
		$keys  = array( 'hero', 'cherry', 'cupcake_vanilla', 'cookies_plate', 'drip', 'rosette' );
		$out   = array();
		$seen  = array();
		foreach ( $keys as $k ) {
			$id = isset( $map[ $k ] ) ? absint( $map[ $k ] ) : 0;
			if ( $id && wp_attachment_is_image( $id ) && ! isset( $seen[ $id ] ) ) {
				$out[]  = array( 'id' => $id );
				$seen[ $id ] = true;
			}
		}
		return $out;
	}

	/**
	 * Returns the gallery images to show, in display order.
	 *
	 * @return array<int,array{id:int}>
	 */
	public static function get_images() {
		$saved  = get_option( self::OPTION, array() );
		$images = self::validate( $saved );
		return $images ? $images : self::default_images();
	}

	/**
	 * Validates a raw stored configuration. Drops anything that is not a real
	 * image attachment and removes duplicates.
	 *
	 * @param mixed $raw Option value.
	 * @return array<int,array{id:int}>
	 */
	public static function validate( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		$seen = array();
		foreach ( $raw as $im ) {
			if ( ! is_array( $im ) ) {
				continue;
			}
			$id = absint( $im['id'] ?? 0 );
			if ( ! $id || ! wp_attachment_is_image( $id ) || isset( $seen[ $id ] ) ) {
				continue;
			}
			$out[]      = array( 'id' => $id );
			$seen[ $id ] = true;
		}
		return $out;
	}

	/**
	 * The images to show in the admin form: saved configuration, or the seeded
	 * defaults so the owner immediately sees the photos currently on the homepage.
	 *
	 * @return array<int,array{id:int}>
	 */
	public static function form_images() {
		$saved = get_option( self::OPTION, array() );
		if ( is_array( $saved ) && ! empty( $saved ) ) {
			return $saved;
		}
		return self::default_images();
	}

	/**
	 * Builds the gallery HTML, matching the original block's classes so the
	 * existing gallery layout and the theme's `.tch-gallery` styling are kept.
	 *
	 * @param array<int,array{id:int}> $images Images.
	 * @return string
	 */
	public static function render_gallery( $images ) {
		$cols  = 3;
		$count = 0;
		$inner = '';
		foreach ( $images as $im ) {
			$id  = absint( $im['id'] ?? 0 );
			$img = $id ? wp_get_attachment_image( $id, 'large', false, array( 'class' => '' ) ) : '';
			if ( ! $img ) {
				continue;
			}
			$inner .= '<figure class="wp-block-image size-large">' . $img . '</figure>'; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image is safe.
			$count++;
		}
		if ( ! $count ) {
			return '';
		}
		return '<figure class="wp-block-gallery has-nested-images columns-' . (int) $cols . ' is-cropped tch-gallery">' . $inner . '</figure>';
	}

	/**
	 * Replaces the homepage `tch-gallery` block with the managed gallery.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public static function render_gallery_block( $block_content, $block ) {
		if ( is_admin() || ! is_front_page() ) {
			return $block_content;
		}
		if ( 'core/gallery' !== $block['blockName'] ) {
			return $block_content;
		}
		$cls = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
		if ( false === strpos( $cls, 'tch-gallery' ) ) {
			return $block_content;
		}
		static $done = false;
		if ( $done ) {
			return $block_content;
		}
		$done   = true;
		$images = self::get_images();
		if ( empty( $images ) ) {
			return $block_content; // No data – keep the original block so the page never breaks.
		}
		return self::render_gallery( $images );
	}

	/* ------------------------------------------------------------------
	 * Admin menu + assets
	 * ------------------------------------------------------------------ */

	/**
	 * Registers the central image dashboard and its image section pages.
	 */
	public static function menu() {
		add_menu_page( __( 'Website Images', 'tch' ), __( 'Website Images', 'tch' ), self::CAP, 'tch-website-images', array( __CLASS__, 'page_overview' ), 'dashicons-images-alt2', 27 );
		add_submenu_page( 'tch-website-images', __( 'Overview', 'tch' ), __( 'Overview', 'tch' ), self::CAP, 'tch-website-images', array( __CLASS__, 'page_overview' ) );
		add_submenu_page( 'tch-website-images', __( 'Homepage – From our kitchen', 'tch' ), __( 'Homepage – From our kitchen', 'tch' ), self::CAP, 'tch-website-images-home', array( __CLASS__, 'page_home_gallery' ) );
		add_submenu_page( 'tch-website-images', __( 'Homepage – What We Bake', 'tch' ), __( 'Homepage – What We Bake', 'tch' ), self::CAP, 'tch-website-images-bake', array( __CLASS__, 'page_home_bake' ) );
		add_submenu_page( 'tch-website-images', __( 'Homepage – Customized Birthday Cakes', 'tch' ), __( 'Homepage – Customized Birthday Cakes', 'tch' ), self::CAP, 'tch-website-images-home-custom', array( __CLASS__, 'page_home_custom_images' ) );
		add_submenu_page( 'tch-website-images', __( 'About Us Images', 'tch' ), __( 'About Us Images', 'tch' ), self::CAP, 'tch-website-images-about', array( __CLASS__, 'page_about_images' ) );
		add_submenu_page( 'tch-website-images', __( 'Birthday Cakes Images', 'tch' ), __( 'Birthday Cakes Images', 'tch' ), self::CAP, 'tch-website-images-birthday', array( __CLASS__, 'page_birthday_images' ) );
		add_submenu_page( 'tch-website-images', __( 'Product Images', 'tch' ), __( 'Product Images', 'tch' ), self::CAP, 'tch-website-images-products', array( __CLASS__, 'page_product_images' ) );
		add_submenu_page( 'tch-website-images', __( 'Gallery Page Images', 'tch' ), __( 'Gallery Page Images', 'tch' ), self::CAP, 'tch-website-images-gallery', array( __CLASS__, 'page_gallery_images' ) );
		add_submenu_page( 'tch-website-images', __( 'Blog Images', 'tch' ), __( 'Blog Images', 'tch' ), self::CAP, 'tch-website-images-blog', array( __CLASS__, 'page_blog_images' ) );
	}

	/**
	 * Admin assets for the Homepage Gallery screen only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'tch-website-images' ) ) {
			return;
		}
		$style_path = TCH_DIR . 'assets/css/website-images.css';
		wp_enqueue_style( 'tch-website-images', TCH_URL . 'assets/css/website-images.css', array(), (string) filemtime( $style_path ) );
		if ( 'toplevel_page_tch-website-images' === $hook ) {
			return;
		}
		wp_enqueue_media();
		$script_path = TCH_DIR . 'assets/js/website-images.js';
		wp_enqueue_script( 'tch-website-images', TCH_URL . 'assets/js/website-images.js', array( 'jquery' ), (string) filemtime( $script_path ), true );
		wp_localize_script(
			'tch-website-images',
			'tchWebsiteImages',
			array(
				'title'      => __( 'Choose an image', 'tch' ),
				'add'        => __( 'Add to gallery', 'tch' ),
				'change'     => __( 'Change image', 'tch' ),
				'remove'     => __( 'Remove', 'tch' ),
				'up'         => __( 'Move up', 'tch' ),
				'down'       => __( 'Move down', 'tch' ),
				'placeholder'=> '',
				'unsaved'    => __( 'You have unsaved changes to the homepage gallery.', 'tch' ),
			)
		);
	}

	/**
	 * Loads the content-image lightbox on the public website.
	 */
	public static function frontend_assets() {
		$style_path  = TCH_DIR . 'assets/css/image-lightbox.css';
		$script_path = TCH_DIR . 'assets/js/image-lightbox.js';
		wp_enqueue_style( 'tch-image-lightbox', TCH_URL . 'assets/css/image-lightbox.css', array(), (string) filemtime( $style_path ) );
		wp_enqueue_script( 'tch-image-lightbox', TCH_URL . 'assets/js/image-lightbox.js', array(), (string) filemtime( $script_path ), true );
	}

	/* ------------------------------------------------------------------
	 * Admin page
	 * ------------------------------------------------------------------ */

	/**
	 * Renders the central dashboard overview.
	 */
	public static function page_overview() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to manage website images.', 'tch' ), 403 );
		}
		$sections = array(
			array( 'Homepage – From our kitchen', __( 'Manage the existing homepage gallery photos and their order.', 'tch' ), 'tch-website-images-home', 'format-gallery' ),
			array( 'Homepage – What We Bake', __( 'Replace, add, remove, and reorder the baked-category card images.', 'tch' ), 'tch-website-images-bake', 'images-alt2' ),
			array( 'Homepage – Customized Birthday Cakes', __( 'Replace the custom-cake image shown on the homepage.', 'tch' ), 'tch-website-images-home-custom', 'format-image' ),
			array( 'About Us Images', __( 'Replace the images used in the About Us page without changing its layout.', 'tch' ), 'tch-website-images-about', 'id' ),
			array( 'Birthday Cakes Images', __( 'Replace the image used on the Customized Birthday Cakes page.', 'tch' ), 'tch-website-images-birthday', 'cake' ),
			array( 'Product Images', __( 'Manage featured images for published cake products.', 'tch' ), 'tch-website-images-products', 'carrot' ),
			array( 'Gallery Page Images', __( 'Manage, add, remove, and reorder images on the Gallery page.', 'tch' ), 'tch-website-images-gallery', 'images-alt2' ),
			array( 'Blog Images', __( 'Manage featured images for published blog posts.', 'tch' ), 'tch-website-images-blog', 'format-image' ),
		);
		?>
		<div class="wrap tch-wrap tch-wi-admin">
			<h1><?php esc_html_e( 'Website Images', 'tch' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Choose a section to manage its images. The homepage hero slider has a separate manager and is not changed here.', 'tch' ); ?></p>
			<div class="tch-wi-overview">
				<?php foreach ( $sections as $section ) : ?>
					<a class="tch-wi-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $section[2] ) ); ?>">
						<span class="dashicons dashicons-<?php echo esc_attr( $section[3] ); ?>" aria-hidden="true"></span>
						<span class="tch-wi-card__title"><?php echo esc_html( $section[0] ); ?></span>
						<span class="tch-wi-card__description"><?php echo esc_html( $section[1] ); ?></span>
						<span class="tch-wi-card__action"><?php esc_html_e( 'Manage images', 'tch' ); ?> &rarr;</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Page configuration for editable standard Gutenberg image blocks.
	 *
	 * @return array<string,array{title:string,slug:string,description:string}>
	 */
	private static function managed_pages() {
		return array(
			'home-custom' => array(
				'title'       => __( 'Homepage – Customized Birthday Cakes', 'tch' ),
				'slug'        => '',
				'description' => __( 'Replace the image in the homepage Customized Birthday Cakes section. Text and layout are left unchanged.', 'tch' ),
			),
			'about' => array(
				'title'       => __( 'About Us Images', 'tch' ),
				'slug'        => 'about-us',
				'description' => __( 'Replace individual images on the About Us page. Text and layout are left unchanged.', 'tch' ),
			),
			'birthday' => array(
				'title'       => __( 'Birthday Cakes Images', 'tch' ),
				'slug'        => 'customized-birthday-cakes',
				'description' => __( 'Replace the image on the Customized Birthday Cakes page. The existing page layout is preserved.', 'tch' ),
			),
		);
	}

	/**
	 * Finds a managed WordPress page by its path.
	 *
	 * @param string $key Managed page key.
	 * @return WP_Post|null
	 */
	private static function managed_page( $key ) {
		if ( 'home-custom' === $key ) {
			$page = get_post( absint( get_option( 'page_on_front' ) ) );
			return $page instanceof WP_Post && 'page' === $page->post_type ? $page : null;
		}
		$pages = self::managed_pages();
		if ( ! isset( $pages[ $key ] ) ) {
			return null;
		}
		$page = get_page_by_path( $pages[ $key ]['slug'], OBJECT, 'page' );
		return $page instanceof WP_Post ? $page : null;
	}

	/**
	 * Renders the About Us image manager.
	 */
	public static function page_about_images() {
		self::page_standard_images( 'about' );
	}

	/**
	 * Renders the Customized Birthday Cakes image manager.
	 */
	public static function page_birthday_images() {
		self::page_standard_images( 'birthday' );
	}

	/**
	 * Renders replace-only management for the homepage custom-cake image.
	 */
	public static function page_home_custom_images() {
		self::page_standard_images( 'home-custom' );
	}

	/**
	 * Renders add, remove, replace, and reorder controls for What We Bake.
	 */
	public static function page_home_bake() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to manage website images.', 'tch' ), 403 );
		}
		$page = self::homepage_page();
		if ( ! $page || ! self::home_bake_section( $page ) ) {
			self::page_error( __( 'Homepage – What We Bake', 'tch' ), __( 'The What We Bake image section could not be found. No images were changed.', 'tch' ) );
			return;
		}
		$images = self::home_bake_images( $page );
		?>
		<div class="wrap tch-wrap tch-wi-admin">
			<h1><?php esc_html_e( 'Homepage – What We Bake', 'tch' ); ?></h1>
			<?php self::notice(); ?>
			<p class="description"><?php esc_html_e( 'Manage the images in the Birthday Cakes, Cupcakes, and Cookies cards. Add, remove, replace, or reorder up to three images; card text and layout are preserved.', 'tch' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="tch-home-bake-form" class="tch-image-manager-form">
				<?php wp_nonce_field( 'tch_save_home_bake' ); ?>
				<input type="hidden" name="action" value="tch_save_home_bake">
				<input type="hidden" name="bake_form_present" value="1">
				<input type="hidden" id="tch-home-bake-count" name="bake_image_count" value="<?php echo (int) count( $images ); ?>">
				<table class="widefat striped tch-wi-table">
					<thead><tr><th style="width:64px"><?php esc_html_e( 'Order', 'tch' ); ?></th><th style="width:120px"><?php esc_html_e( 'Image', 'tch' ); ?></th><th><?php esc_html_e( 'Preview', 'tch' ); ?></th><th><?php esc_html_e( 'Actions', 'tch' ); ?></th></tr></thead>
					<tbody id="tch-home-bake" data-input-name="bake_ids[]" data-max-images="3">
						<?php foreach ( $images as $image ) : ?>
							<?php self::render_row( $image, 'bake_ids[]' ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button button-secondary tch-add-images"><?php esc_html_e( '+ Add images', 'tch' ); ?></button></p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'tch' ); ?></button> <a class="button" href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View homepage', 'tch' ); ?></a></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Returns the configured static homepage.
	 *
	 * @return WP_Post|null
	 */
	private static function homepage_page() {
		$page = get_post( absint( get_option( 'page_on_front' ) ) );
		return $page instanceof WP_Post && 'page' === $page->post_type ? $page : null;
	}

	/**
	 * Finds the homepage's What We Bake columns block.
	 *
	 * @param WP_Post $page Homepage.
	 * @return array{path:string,block:array}|null
	 */
	private static function home_bake_section( $page ) {
		return self::find_block_by_class( parse_blocks( $page->post_content ), 'core/columns', 'tch-cats' );
	}

	/**
	 * Returns the active ordered What We Bake image list.
	 *
	 * @param WP_Post $page Homepage.
	 * @return array<int,array{id:int}>
	 */
	private static function home_bake_images( $page ) {
		$section = self::home_bake_section( $page );
		$images  = array();
		if ( ! $section ) {
			return $images;
		}
		foreach ( $section['block']['innerBlocks'] as $column ) {
			if ( 'core/column' !== ( $column['blockName'] ?? '' ) ) {
				continue;
			}
			$column_images = self::page_image_blocks( $column['innerBlocks'], '', 'tch-cat__img' );
			if ( $column_images ) {
				$images[] = array( 'id' => $column_images[0]['id'] );
			}
		}
		return $images;
	}

	/**
	 * Renders replace-only management for images inside a standard page.
	 *
	 * @param string $key Managed page key.
	 */
	private static function page_standard_images( $key ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to manage website images.', 'tch' ), 403 );
		}
		$config = self::managed_pages()[ $key ];
		$page   = self::managed_page( $key );
		if ( ! $page ) {
			self::page_error( $config['title'], __( 'The page could not be found. No images were changed.', 'tch' ) );
			return;
		}
		$class_filter = 'home-custom' === $key ? 'tch-rounded' : '';
		$images       = self::page_image_blocks( parse_blocks( $page->post_content ), '', $class_filter );
		?>
		<div class="wrap tch-wrap tch-wi-admin">
			<h1><?php echo esc_html( $config['title'] ); ?></h1>
			<?php self::notice(); ?>
			<p class="description"><?php echo esc_html( $config['description'] ); ?> <?php esc_html_e( 'Use Change image to choose a Media Library image or upload a new one.', 'tch' ); ?></p>
			<?php if ( ! $images ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'No Gutenberg image blocks were found on this page.', 'tch' ); ?></p></div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tch-image-manager-form">
					<?php wp_nonce_field( 'tch_save_page_images' ); ?>
					<input type="hidden" name="action" value="tch_save_page_images">
					<input type="hidden" name="page_key" value="<?php echo esc_attr( $key ); ?>">
					<table class="widefat striped tch-wi-table">
						<thead><tr><th><?php esc_html_e( 'Position', 'tch' ); ?></th><th><?php esc_html_e( 'Preview', 'tch' ); ?></th><th><?php esc_html_e( 'Actions', 'tch' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $images as $index => $image ) : ?>
								<?php self::render_image_block_row( $image, $index + 1 ); ?>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'tch' ); ?></button> <a class="button" href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View page', 'tch' ); ?></a></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Checks whether a parsed block has a given custom class.
	 *
	 * @param array  $block Parsed block.
	 * @param string $class Custom class.
	 * @return bool
	 */
	private static function block_has_class( $block, $class ) {
		$classes = (string) ( $block['attrs']['className'] ?? '' );
		return $class && (bool) preg_match( '/(?:^|\s)' . preg_quote( $class, '/' ) . '(?:\s|$)/', $classes );
	}

	/**
	 * Finds a Gutenberg block by its block type and custom class.
	 *
	 * @param array  $blocks Parsed blocks.
	 * @param string $block_name Block type.
	 * @param string $class Custom class.
	 * @param string $prefix Parent path.
	 * @return array{path:string,block:array}|null
	 */
	private static function find_block_by_class( $blocks, $block_name, $class, $prefix = '' ) {
		foreach ( $blocks as $index => $block ) {
			$path = '' === $prefix ? (string) $index : $prefix . '.' . $index;
			if ( $block_name === ( $block['blockName'] ?? '' ) && self::block_has_class( $block, $class ) ) {
				return array( 'path' => $path, 'block' => $block );
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = self::find_block_by_class( $block['innerBlocks'], $block_name, $class, $path );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Recursively lists core/image blocks on a page using stable block paths.
	 *
	 * @param array  $blocks Parsed blocks.
	 * @param string $prefix Parent path.
	 * @param string $class_filter Optional class required on an image block.
	 * @return array<int,array{path:string,id:int,alt:string}>
	 */
	private static function page_image_blocks( $blocks, $prefix = '', $class_filter = '' ) {
		$images = array();
		foreach ( $blocks as $index => $block ) {
			$path = '' === $prefix ? (string) $index : $prefix . '.' . $index;
			if ( 'core/image' === ( $block['blockName'] ?? '' ) && ( ! $class_filter || self::block_has_class( $block, $class_filter ) ) ) {
				$id = absint( $block['attrs']['id'] ?? 0 );
				if ( $id && wp_attachment_is_image( $id ) ) {
					$images[] = array(
						'path' => $path,
						'id'   => $id,
						'alt'  => get_post_meta( $id, '_wp_attachment_image_alt', true ),
					);
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$images = array_merge( $images, self::page_image_blocks( $block['innerBlocks'], $path, $class_filter ) );
			}
		}
		return $images;
	}

	/**
	 * Renders an image block row for page image replacement.
	 *
	 * @param array $image Image block data.
	 * @param int   $index Display index.
	 */
	private static function render_image_block_row( $image, $index ) {
		$id    = absint( $image['id'] );
		$thumb = wp_get_attachment_image_url( $id, 'medium' );
		$label = trim( (string) $image['alt'] );
		?>
		<tr class="tch-image-row">
			<td><?php echo esc_html( sprintf( __( 'Image %d', 'tch' ), $index ) ); ?><?php if ( $label ) : ?><br><span class="description"><?php echo esc_html( $label ); ?></span><?php endif; ?></td>
			<td>
				<input type="hidden" class="tch-image-id" name="image_ids[<?php echo esc_attr( $image['path'] ); ?>]" value="<?php echo (int) $id; ?>">
				<code class="tch-image-id-label">#<?php echo (int) $id; ?></code>
				<img class="tch-row-thumb" src="<?php echo esc_url( $thumb ); ?>" alt="" width="120" height="90">
			</td>
			<td><button type="button" class="button tch-change-image"><?php esc_html_e( 'Change image', 'tch' ); ?></button></td>
		</tr>
		<?php
	}

	/**
	 * Renders the Gallery page image manager.
	 */
	public static function page_gallery_images() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to manage website images.', 'tch' ), 403 );
		}
		$page   = get_page_by_path( 'gallery', OBJECT, 'page' );
		$images = $page ? self::gallery_page_images( $page->post_content ) : array();
		?>
		<div class="wrap tch-wrap tch-wi-admin">
			<h1><?php esc_html_e( 'Gallery Page Images', 'tch' ); ?></h1>
			<?php self::notice(); ?>
			<p class="description"><?php esc_html_e( 'Manage images in the separate Gallery page. Add, remove, reorder, or replace images; the existing three-column gallery layout is preserved.', 'tch' ); ?></p>
			<?php if ( ! $page ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'The Gallery page could not be found. No images were changed.', 'tch' ); ?></p></div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tch-image-manager-form">
					<?php wp_nonce_field( 'tch_save_gallery_page' ); ?>
					<input type="hidden" name="action" value="tch_save_gallery_page">
					<table class="widefat striped tch-wi-table">
						<thead><tr><th style="width:64px"><?php esc_html_e( 'Order', 'tch' ); ?></th><th style="width:120px"><?php esc_html_e( 'Image ID', 'tch' ); ?></th><th><?php esc_html_e( 'Preview', 'tch' ); ?></th><th><?php esc_html_e( 'Actions', 'tch' ); ?></th></tr></thead>
						<tbody id="tch-image-list" data-input-name="gallery_ids[]">
							<?php foreach ( $images as $image ) : ?>
								<?php self::render_row( $image ); ?>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p><button type="button" class="button button-secondary tch-add-images"><?php esc_html_e( '+ Add images', 'tch' ); ?></button></p>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'tch' ); ?></button> <a class="button" href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View page', 'tch' ); ?></a></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Extracts images from the page's first Gutenberg gallery block.
	 *
	 * @param string $content Page content.
	 * @return array<int,array{id:int}>
	 */
	private static function gallery_page_images( $content ) {
		$gallery = self::find_gallery_block( parse_blocks( $content ) );
		if ( ! $gallery ) {
			return array();
		}
		$images = array();
		foreach ( $gallery['block']['innerBlocks'] as $block ) {
			$id = absint( $block['attrs']['id'] ?? 0 );
			if ( 'core/image' === ( $block['blockName'] ?? '' ) && $id && wp_attachment_is_image( $id ) ) {
				$images[] = array( 'id' => $id );
			}
		}
		return $images;
	}

	/**
	 * Finds the first core/gallery block and its path.
	 *
	 * @param array  $blocks Parsed blocks.
	 * @param string $prefix Parent path.
	 * @return array{path:string,block:array}|null
	 */
	private static function find_gallery_block( $blocks, $prefix = '' ) {
		foreach ( $blocks as $index => $block ) {
			$path = '' === $prefix ? (string) $index : $prefix . '.' . $index;
			if ( 'core/gallery' === ( $block['blockName'] ?? '' ) ) {
				return array( 'path' => $path, 'block' => $block );
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = self::find_gallery_block( $block['innerBlocks'], $path );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Renders product or post featured-image management.
	 *
	 * @param string $type Product post type or post.
	 */
	private static function page_featured_images( $type ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to manage website images.', 'tch' ), 403 );
		}
		$is_product = 'tch_product' === $type;
		$title      = $is_product ? __( 'Product Images', 'tch' ) : __( 'Blog Images', 'tch' );
		$description = $is_product
			? __( 'Replace featured images for published products. Product names, prices, descriptions, categories, and stock are not changed.', 'tch' )
			: __( 'Replace featured images for published blog posts. Post content and publication details are not changed.', 'tch' );
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$query  = new WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'paged'          => $paged,
				's'              => $search,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'search_columns' => array( 'post_title' ),
				'no_found_rows'  => false,
			)
		);
		$slug = $is_product ? 'tch-website-images-products' : 'tch-website-images-blog';
		?>
		<div class="wrap tch-wrap tch-wi-admin">
			<h1><?php echo esc_html( $title ); ?></h1>
			<?php self::notice(); ?>
			<p class="description"><?php echo esc_html( $description ); ?> <?php esc_html_e( 'Choose an existing Media Library image or upload a new one.', 'tch' ); ?></p>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="tch-wi-search">
				<input type="hidden" name="page" value="<?php echo esc_attr( $slug ); ?>">
				<label class="screen-reader-text" for="tch-wi-search"><?php esc_html_e( 'Search by name', 'tch' ); ?></label>
				<input id="tch-wi-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by name', 'tch' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'tch' ); ?></button>
			</form>
			<?php if ( $query->have_posts() ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tch-image-manager-form">
					<?php wp_nonce_field( 'tch_save_featured_images' ); ?>
					<input type="hidden" name="action" value="tch_save_featured_images">
					<input type="hidden" name="post_type" value="<?php echo esc_attr( $type ); ?>">
					<input type="hidden" name="return_page" value="<?php echo esc_attr( $slug ); ?>">
					<input type="hidden" name="search" value="<?php echo esc_attr( $search ); ?>">
					<input type="hidden" name="paged" value="<?php echo (int) $paged; ?>">
					<table class="widefat striped tch-wi-table">
						<thead><tr><th><?php esc_html_e( 'Name', 'tch' ); ?></th><th><?php esc_html_e( 'Current image', 'tch' ); ?></th><th><?php esc_html_e( 'Action', 'tch' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $query->posts as $post ) : ?>
								<?php self::render_featured_image_row( $post ); ?>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'tch' ); ?></button></p>
				</form>
				<?php self::render_pagination( $query, $slug, $search ); ?>
			<?php else : ?>
				<p><?php esc_html_e( 'No published items were found.', 'tch' ); ?></p>
			<?php endif; ?>
			<?php wp_reset_postdata(); ?>
		</div>
		<?php
	}

	/**
	 * Product featured-image manager.
	 */
	public static function page_product_images() {
		self::page_featured_images( 'tch_product' );
	}

	/**
	 * Blog featured-image manager.
	 */
	public static function page_blog_images() {
		self::page_featured_images( 'post' );
	}

	/**
	 * Renders one post's current featured image.
	 *
	 * @param WP_Post $post Item.
	 */
	private static function render_featured_image_row( $post ) {
		$id    = get_post_thumbnail_id( $post );
		$thumb = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		?>
		<tr class="tch-image-row">
			<td><strong><?php echo esc_html( get_the_title( $post ) ); ?></strong><br><span class="description"><?php echo esc_html( sprintf( __( 'ID: %d', 'tch' ), $post->ID ) ); ?></span></td>
			<td>
				<input type="hidden" class="tch-image-id" name="image_ids[<?php echo (int) $post->ID; ?>]" value="<?php echo (int) $id; ?>">
				<?php if ( $id && $thumb ) : ?>
					<code class="tch-image-id-label">#<?php echo (int) $id; ?></code>
					<img class="tch-row-thumb" src="<?php echo esc_url( $thumb ); ?>" alt="" width="120" height="90">
				<?php else : ?>
					<code class="tch-image-id-label"><?php esc_html_e( 'No featured image', 'tch' ); ?></code>
					<span class="tch-no-image"><?php esc_html_e( 'No image chosen', 'tch' ); ?></span>
				<?php endif; ?>
			</td>
			<td><button type="button" class="button tch-change-image"><?php esc_html_e( 'Change image', 'tch' ); ?></button></td>
		</tr>
		<?php
	}

	/**
	 * Renders pagination for featured-image lists.
	 *
	 * @param WP_Query $query Query.
	 * @param string   $slug Screen slug.
	 * @param string   $search Search query.
	 */
	private static function render_pagination( $query, $slug, $search ) {
		if ( $query->max_num_pages < 2 ) {
			return;
		}
		$links = paginate_links(
			array(
				'base'      => add_query_arg( array( 'page' => $slug, 's' => $search, 'paged' => '%#%' ), admin_url( 'admin.php' ) ),
				'format'    => '',
				'current'   => max( 1, $query->get( 'paged' ) ),
				'total'     => $query->max_num_pages,
				'type'      => 'list',
				'prev_text' => __( '&laquo; Previous', 'tch' ),
				'next_text' => __( 'Next &raquo;', 'tch' ),
			)
		);
		if ( $links ) {
			echo '<nav class="tch-wi-pagination" aria-label="' . esc_attr__( 'Image list pages', 'tch' ) . '">' . wp_kses_post( $links ) . '</nav>';
		}
	}

	/**
	 * Renders the Homepage Gallery admin page.
	 */
	public static function page_home_gallery() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to manage website images.', 'tch' ), 403 );
		}
		$images   = self::form_images();
		$homepage = home_url( '/' );
		$reset    = wp_nonce_url( admin_url( 'admin-post.php?action=tch_reset_home_gallery' ), 'tch_reset_home_gallery' );
		?>
		<div class="wrap tch-wrap tch-wi-admin">
			<h1><?php esc_html_e( 'Homepage – From our kitchen', 'tch' ); ?></h1>
			<?php self::notice(); ?>
			<p class="description">
				<?php esc_html_e( 'These photos appear in the “From our kitchen” gallery on your homepage. This is separate from the homepage slider — changing them here never affects the slider.', 'tch' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="tch-home-gallery-form" class="tch-image-manager-form">
				<?php wp_nonce_field( 'tch_save_home_gallery' ); ?>
				<input type="hidden" name="action" value="tch_save_home_gallery">

				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:64px"><?php esc_html_e( 'Order', 'tch' ); ?></th>
							<th style="width:120px"><?php esc_html_e( 'Image', 'tch' ); ?></th>
							<th><?php esc_html_e( 'Preview', 'tch' ); ?></th>
							<th style="width:240px"><?php esc_html_e( 'Actions', 'tch' ); ?></th>
						</tr>
					</thead>
					<tbody id="tch-home-gallery" data-input-name="gallery_ids[]">
						<?php foreach ( $images as $im ) : ?>
							<?php self::render_row( $im ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<button type="button" class="button button-secondary tch-add-images"><?php esc_html_e( '+ Add images', 'tch' ); ?></button>
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'tch' ); ?></button>
					<a class="button" href="<?php echo esc_url( $reset ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Restore the original six gallery photos? Your current selection will be cleared.', 'tch' ) ); ?>');"><?php esc_html_e( 'Reset to original photos', 'tch' ); ?></a>
					<a class="button" href="<?php echo esc_url( $homepage ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View homepage', 'tch' ); ?></a>
				</p>
			</form>
			<p class="description"><?php esc_html_e( 'Tip: the gallery keeps its existing 3-column layout and rounded styling automatically. Drag is not needed — use the up/down arrows to reorder.', 'tch' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders one editable gallery row.
	 *
	 * @param array{id:int} $im Image row.
	 */
	private static function render_row( $im, $input_name = 'gallery_ids[]' ) {
		$id    = absint( $im['id'] ?? 0 );
		$thumb = '';
		if ( $id ) {
			$t = wp_get_attachment_image_src( $id, 'thumbnail' );
			if ( $t ) {
				$thumb = $t[0];
			}
		}
		?>
		<tr class="tch-gallery-row tch-image-row">
			<td class="tch-row-order">
				<button type="button" class="button tch-move-up" aria-label="<?php esc_attr_e( 'Move up', 'tch' ); ?>">&uarr;</button>
				<button type="button" class="button tch-move-down" aria-label="<?php esc_attr_e( 'Move down', 'tch' ); ?>">&darr;</button>
			</td>
			<td class="tch-row-id-cell">
				<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" class="tch-row-id tch-image-id" value="<?php echo (int) $id; ?>">
				<?php if ( $id ) : ?>
					<code class="tch-row-id-label">#<?php echo (int) $id; ?></code>
				<?php else : ?>
					<code class="tch-row-id-label tch-id-default"><?php esc_html_e( 'Default', 'tch' ); ?></code>
				<?php endif; ?>
			</td>
			<td class="tch-row-preview">
				<?php if ( $thumb ) : ?>
					<img class="tch-row-thumb" src="<?php echo esc_url( $thumb ); ?>" alt="" width="80" height="80">
				<?php else : ?>
					<span class="tch-no-image"><?php esc_html_e( 'No image chosen', 'tch' ); ?></span>
				<?php endif; ?>
			</td>
			<td class="tch-row-actions">
				<button type="button" class="button tch-change-image"><?php esc_html_e( 'Change image', 'tch' ); ?></button>
				<button type="button" class="button tch-remove-row"><?php esc_html_e( 'Remove', 'tch' ); ?></button>
			</td>
		</tr>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Save / reset handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Saves the homepage gallery from the admin form.
	 */
	public static function save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tch' ), 403 );
		}
		check_admin_referer( 'tch_save_home_gallery' );

		$ids = isset( $_POST['gallery_ids'] ) ? (array) wp_unslash( $_POST['gallery_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized below.
		$out = array();
		$seen = array();
		foreach ( $ids as $raw_id ) {
			$id = absint( $raw_id );
			if ( ! $id || ! wp_attachment_is_image( $id ) || isset( $seen[ $id ] ) ) {
				continue;
			}
			$out[]      = array( 'id' => $id );
			$seen[ $id ] = true;
		}

		$out = array_slice( $out, 0, self::MAX_IMAGES );
		update_option( self::OPTION, $out, false );
		if ( get_option( self::OPTION ) !== $out ) {
			self::back( admin_url( 'admin.php?page=tch-website-images-home' ), __( 'WordPress could not save the homepage gallery.', 'tch' ), true );
		}

		self::back( admin_url( 'admin.php?page=tch-website-images-home' ), sprintf( /* translators: %d: image count */ __( '%d gallery image(s) saved.', 'tch' ), count( $out ) ) );
	}

	/**
	 * Resets the gallery to the original six photos (clears the saved config
	 * so the homepage shows the built-in defaults again).
	 */
	public static function reset() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tch' ), 403 );
		}
		check_admin_referer( 'tch_reset_home_gallery' );
		delete_option( self::OPTION );
		self::back( admin_url( 'admin.php?page=tch-website-images-home' ), __( 'Restored the original gallery photos.', 'tch' ) );
	}

	/**
	 * Saves replacement image IDs to existing Gutenberg image blocks only.
	 */
	public static function save_page_images() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tch' ), 403 );
		}
		check_admin_referer( 'tch_save_page_images' );
		$key = isset( $_POST['page_key'] ) ? sanitize_key( wp_unslash( $_POST['page_key'] ) ) : '';
		$pages = self::managed_pages();
		if ( ! isset( $pages[ $key ] ) ) {
			wp_die( esc_html__( 'The requested image section is invalid.', 'tch' ), 400 );
		}
		$page = self::managed_page( $key );
		if ( ! $page || ! current_user_can( 'edit_post', $page->ID ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this page.', 'tch' ), 403 );
		}
		$redirect = admin_url( 'admin.php?page=' . ( 'about' === $key ? 'tch-website-images-about' : 'tch-website-images-birthday' ) );
		if ( 'home-custom' === $key ) {
			$redirect = admin_url( 'admin.php?page=tch-website-images-home-custom' );
		}
		$blocks   = parse_blocks( $page->post_content );
		$class_filter = 'home-custom' === $key ? 'tch-rounded' : '';
		$current      = self::page_image_blocks( $blocks, '', $class_filter );
		$submitted = isset( $_POST['image_ids'] ) && is_array( $_POST['image_ids'] ) ? wp_unslash( $_POST['image_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.
		if ( count( $current ) !== count( $submitted ) ) {
			self::back( $redirect, __( 'The page images changed while this form was open. Reload the page and try again.', 'tch' ), true );
		}
		foreach ( $current as $image ) {
			$path = $image['path'];
			$id   = isset( $submitted[ $path ] ) ? absint( $submitted[ $path ] ) : 0;
			if ( ! $id || ! wp_attachment_is_image( $id ) || ! wp_get_attachment_image_url( $id, 'full' ) ) {
				self::back( $redirect, __( 'Choose a valid image for every image shown.', 'tch' ), true );
			}
			if ( ! self::replace_image_at_path( $blocks, $path, $id ) ) {
				self::back( $redirect, __( 'An image block could not be updated. No page content was saved.', 'tch' ), true );
			}
		}
		$result = wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => serialize_blocks( $blocks ),
			),
			true
		);
		if ( is_wp_error( $result ) || (int) $result !== (int) $page->ID ) {
			self::back( $redirect, __( 'WordPress could not save the page images.', 'tch' ), true );
		}
		self::back( $redirect, __( 'Page images saved.', 'tch' ) );
	}

	/**
	 * Saves the ordered image set from the homepage What We Bake manager.
	 */
	public static function save_home_bake_images() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tch' ), 403 );
		}
		check_admin_referer( 'tch_save_home_bake' );
		$page     = self::homepage_page();
		$redirect = admin_url( 'admin.php?page=tch-website-images-bake' );
		if ( ! $page || ! current_user_can( 'edit_post', $page->ID ) ) {
			wp_die( esc_html__( 'You do not have permission to edit the homepage.', 'tch' ), 403 );
		}
		$form_present = isset( $_POST['bake_form_present'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['bake_form_present'] ) );
		if ( ! $form_present || ! isset( $_POST['bake_ids'] ) || ! is_array( $_POST['bake_ids'] ) ) {
			self::back( $redirect, __( 'The What We Bake form was incomplete. No images were changed; reload the page and try again.', 'tch' ), true );
		}
		$raw_ids        = wp_unslash( $_POST['bake_ids'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.
		$expected_count = isset( $_POST['bake_image_count'] ) ? absint( $_POST['bake_image_count'] ) : 0;
		if ( $expected_count > self::MAX_BAKE_IMAGES || count( $raw_ids ) > self::MAX_BAKE_IMAGES ) {
			self::back( $redirect, sprintf( __( 'A maximum of %d What We Bake images is allowed.', 'tch' ), self::MAX_BAKE_IMAGES ), true );
		}
		if ( $expected_count < 1 || count( $raw_ids ) !== $expected_count ) {
			self::back( $redirect, __( 'The What We Bake image selection was empty or incomplete. No images were changed; keep at least one image and try again.', 'tch' ), true );
		}
		$ids  = array();
		$seen = array();
		foreach ( $raw_ids as $raw_id ) {
			$id = absint( $raw_id );
			if ( ! $id || ! wp_attachment_is_image( $id ) || ! wp_get_attachment_image_url( $id, 'full' ) ) {
				self::back( $redirect, __( 'Choose a valid image for every What We Bake item.', 'tch' ), true );
			}
			if ( isset( $seen[ $id ] ) ) {
				self::back( $redirect, __( 'The same image cannot be added more than once.', 'tch' ), true );
			}
			$seen[ $id ] = true;
			$ids[]       = $id;
		}
		$blocks  = parse_blocks( $page->post_content );
		$section = self::home_bake_section( $page );
		if ( ! $section || ! self::replace_home_bake_images_at_path( $blocks, $section['path'], $ids ) ) {
			self::back( $redirect, __( 'The What We Bake image blocks could not be updated. No changes were saved.', 'tch' ), true );
		}
		$result = wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => serialize_blocks( $blocks ),
			),
			true
		);
		if ( is_wp_error( $result ) || (int) $result !== (int) $page->ID ) {
			self::back( $redirect, __( 'WordPress could not save the What We Bake images.', 'tch' ), true );
		}
		$saved_page   = get_post( $page->ID );
		$saved_images = $saved_page ? self::home_bake_images( $saved_page ) : array();
		$saved_ids    = array_map( 'absint', array_column( $saved_images, 'id' ) );
		if ( $saved_ids !== $ids ) {
			self::back( $redirect, __( 'The saved What We Bake images could not be verified. Reload the page and check the selection.', 'tch' ), true );
		}
		self::back( $redirect, sprintf( __( '%d What We Bake image(s) saved.', 'tch' ), count( $ids ) ) );
	}

	/**
	 * Replaces the ordered What We Bake image set inside its existing columns.
	 *
	 * @param array  $blocks Parsed blocks, passed by reference.
	 * @param string $path   Dotted path to the columns block.
	 * @param int[]  $ids    Ordered image IDs.
	 * @return bool
	 */
	private static function replace_home_bake_images_at_path( &$blocks, $path, $ids ) {
		$parts = explode( '.', $path );
		$index = (int) array_shift( $parts );
		if ( ! isset( $blocks[ $index ] ) ) {
			return false;
		}
		if ( $parts ) {
			if ( empty( $blocks[ $index ]['innerBlocks'] ) ) {
				return false;
			}
			return self::replace_home_bake_images_at_path( $blocks[ $index ]['innerBlocks'], implode( '.', $parts ), $ids );
		}
		if ( 'core/columns' !== ( $blocks[ $index ]['blockName'] ?? '' ) || ! self::block_has_class( $blocks[ $index ], 'tch-cats' ) ) {
			return false;
		}
		$column_index = 0;
		foreach ( $blocks[ $index ]['innerBlocks'] as &$column ) {
			if ( 'core/column' !== ( $column['blockName'] ?? '' ) ) {
				continue;
			}
			$image_index = null;
			foreach ( $column['innerBlocks'] as $child_index => $child ) {
				if ( 'core/image' === ( $child['blockName'] ?? '' ) && self::block_has_class( $child, 'tch-cat__img' ) ) {
					$image_index = $child_index;
					break;
				}
			}
			$image_id = $ids[ $column_index ] ?? 0;
			if ( $image_id && null !== $image_index ) {
				if ( ! self::replace_image_at_path( $column['innerBlocks'], (string) $image_index, $image_id ) ) {
					return false;
				}
			} elseif ( $image_id ) {
				$image = self::new_home_bake_image_block( $image_id );
				self::insert_child_block( $column, $image, 0 );
			} elseif ( null !== $image_index ) {
				self::remove_child_block( $column, $image_index );
			}
			$column_index++;
		}
		unset( $column );
		return self::MAX_BAKE_IMAGES >= $column_index;
	}

	/**
	 * Creates a category-card image block with the existing classes and size.
	 *
	 * @param int $id Attachment ID.
	 * @return array Parsed Gutenberg image block.
	 */
	private static function new_home_bake_image_block( $id ) {
		$size = 'medium_large';
		$src  = wp_get_attachment_image_url( $id, $size );
		$alt  = get_post_meta( $id, '_wp_attachment_image_alt', true );
		$attrs = array(
			'id'              => $id,
			'sizeSlug'        => $size,
			'linkDestination' => 'none',
			'className'       => 'tch-cat__img',
		);
		$html   = '<figure class="wp-block-image size-' . esc_attr( $size ) . ' tch-cat__img"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . (int) $id . '"/></figure>';
		$markup = '<!-- wp:image ' . wp_json_encode( $attrs ) . ' -->' . $html . '<!-- /wp:image -->';
		$blocks = parse_blocks( $markup );
		return $blocks[0];
	}

	/**
	 * Inserts a child block and its matching innerContent placeholder.
	 *
	 * @param array $parent Parent block, passed by reference.
	 * @param array $child  Child block.
	 * @param int   $index  Child insertion index.
	 */
	private static function insert_child_block( &$parent, $child, $index ) {
		$index = min( $index, count( $parent['innerBlocks'] ) );
		array_splice( $parent['innerBlocks'], $index, 0, array( $child ) );
		$null_index = 0;
		$child_count = 0;
		foreach ( $parent['innerContent'] as $content_index => $content ) {
			if ( null === $content ) {
				if ( $child_count === $index ) {
					$null_index = $content_index;
					break;
				}
				$child_count++;
				$null_index = $content_index + 1;
			}
		}
		array_splice( $parent['innerContent'], $null_index, 0, array( null ) );
	}

	/**
	 * Removes a child block and its matching innerContent placeholder.
	 *
	 * @param array $parent Parent block, passed by reference.
	 * @param int   $index  Child index.
	 */
	private static function remove_child_block( &$parent, $index ) {
		array_splice( $parent['innerBlocks'], $index, 1 );
		$child_count = 0;
		foreach ( $parent['innerContent'] as $content_index => $content ) {
			if ( null === $content ) {
				if ( $child_count === $index ) {
					array_splice( $parent['innerContent'], $content_index, 1 );
					return;
				}
				$child_count++;
			}
		}
	}

	/**
	 * Updates a core/image block while retaining its markup and classes.
	 *
	 * @param array  $blocks Parsed blocks, passed by reference.
	 * @param string $path   Dotted block path.
	 * @param int    $id     Attachment ID.
	 * @return bool
	 */
	private static function replace_image_at_path( &$blocks, $path, $id ) {
		$parts = explode( '.', $path );
		$index = (int) array_shift( $parts );
		if ( ! isset( $blocks[ $index ] ) ) {
			return false;
		}
		if ( $parts ) {
			if ( empty( $blocks[ $index ]['innerBlocks'] ) ) {
				return false;
			}
			return self::replace_image_at_path( $blocks[ $index ]['innerBlocks'], implode( '.', $parts ), $id );
		}
		if ( 'core/image' !== ( $blocks[ $index ]['blockName'] ?? '' ) ) {
			return false;
		}
		$src = wp_get_attachment_image_src( $id, $blocks[ $index ]['attrs']['sizeSlug'] ?? 'large' );
		if ( ! $src ) {
			return false;
		}
		$processor = new WP_HTML_Tag_Processor( $blocks[ $index ]['innerHTML'] );
		if ( ! $processor->next_tag( 'img' ) ) {
			return false;
		}
		$processor->set_attribute( 'src', $src[0] );
		$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		$processor->set_attribute( 'alt', $alt );
		$classes = (string) $processor->get_attribute( 'class' );
		$classes = preg_replace( '/(?:^|\s)wp-image-\d+(?=\s|$)/', '', $classes );
		$classes = trim( $classes . ' wp-image-' . $id );
		$processor->set_attribute( 'class', $classes );
		$srcset = wp_get_attachment_image_srcset( $id, $blocks[ $index ]['attrs']['sizeSlug'] ?? 'large' );
		$sizes  = wp_get_attachment_image_sizes( $id, $blocks[ $index ]['attrs']['sizeSlug'] ?? 'large' );
		if ( $srcset ) {
			$processor->set_attribute( 'srcset', $srcset );
		} else {
			$processor->remove_attribute( 'srcset' );
		}
		if ( $sizes ) {
			$processor->set_attribute( 'sizes', $sizes );
		} else {
			$processor->remove_attribute( 'sizes' );
		}
		$html = $processor->get_updated_html();
		$blocks[ $index ]['attrs']['id']  = $id;
		if ( isset( $blocks[ $index ]['attrs']['alt'] ) ) {
			$blocks[ $index ]['attrs']['alt'] = $alt;
		}
		if ( isset( $blocks[ $index ]['attrs']['url'] ) ) {
			$blocks[ $index ]['attrs']['url'] = $src[0];
		}
		$blocks[ $index ]['innerHTML'] = $html;
		foreach ( $blocks[ $index ]['innerContent'] as $piece => $content ) {
			if ( is_string( $content ) && false !== strpos( $content, '<img' ) ) {
				$blocks[ $index ]['innerContent'][ $piece ] = $html;
				return true;
			}
		}
		return false;
	}

	/**
	 * Saves the separate Gallery page's ordered set of image blocks.
	 */
	public static function save_gallery_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tch' ), 403 );
		}
		check_admin_referer( 'tch_save_gallery_page' );
		$page     = get_page_by_path( 'gallery', OBJECT, 'page' );
		$redirect = admin_url( 'admin.php?page=tch-website-images-gallery' );
		if ( ! $page || ! current_user_can( 'edit_post', $page->ID ) ) {
			wp_die( esc_html__( 'You do not have permission to edit the Gallery page.', 'tch' ), 403 );
		}
		$raw_ids = isset( $_POST['gallery_ids'] ) && is_array( $_POST['gallery_ids'] ) ? wp_unslash( $_POST['gallery_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.
		if ( count( $raw_ids ) > self::MAX_IMAGES ) {
			self::back( $redirect, sprintf( __( 'A maximum of %d gallery images is allowed.', 'tch' ), self::MAX_IMAGES ), true );
		}
		$ids  = array();
		$seen = array();
		foreach ( $raw_ids as $raw_id ) {
			$id = absint( $raw_id );
			if ( ! $id || ! wp_attachment_is_image( $id ) || ! wp_get_attachment_image_url( $id, 'full' ) ) {
				self::back( $redirect, __( 'The gallery contains an invalid image. No changes were saved.', 'tch' ), true );
			}
			if ( isset( $seen[ $id ] ) ) {
				self::back( $redirect, __( 'The same image cannot be added to the gallery more than once.', 'tch' ), true );
			}
			$seen[ $id ] = true;
			$ids[]       = $id;
		}
		$blocks = parse_blocks( $page->post_content );
		$found  = self::find_gallery_block( $blocks );
		if ( ! $found ) {
			self::back( $redirect, __( 'The Gallery page does not contain a Gutenberg gallery block.', 'tch' ), true );
		}
		$gallery = $found['block'];
		$existing = array();
		foreach ( $gallery['innerBlocks'] as $image_block ) {
			$id = absint( $image_block['attrs']['id'] ?? 0 );
			if ( 'core/image' === ( $image_block['blockName'] ?? '' ) && $id ) {
				$existing[ $id ] = $image_block;
			}
		}
		$first_attrs = ! empty( $gallery['innerBlocks'][0]['attrs'] ) ? $gallery['innerBlocks'][0]['attrs'] : array();
		$new_images  = array();
		foreach ( $ids as $id ) {
			$new_images[] = isset( $existing[ $id ] ) ? $existing[ $id ] : self::new_gallery_image_block( $id, $first_attrs );
		}
		if ( ! self::replace_gallery_at_path( $blocks, $found['path'], $new_images ) ) {
			self::back( $redirect, __( 'The Gallery block could not be updated. No changes were saved.', 'tch' ), true );
		}
		$result = wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => serialize_blocks( $blocks ),
			),
			true
		);
		if ( is_wp_error( $result ) || (int) $result !== (int) $page->ID ) {
			self::back( $redirect, __( 'WordPress could not save the Gallery page images.', 'tch' ), true );
		}
		self::back( $redirect, sprintf( __( '%d Gallery page image(s) saved.', 'tch' ), count( $ids ) ) );
	}

	/**
	 * Creates a core/image child block for a newly added Gallery page image.
	 *
	 * @param int   $id Image attachment ID.
	 * @param array $template_attrs Existing gallery image attributes.
	 * @return array Parsed Gutenberg image block.
	 */
	private static function new_gallery_image_block( $id, $template_attrs ) {
		$size = isset( $template_attrs['sizeSlug'] ) ? sanitize_key( $template_attrs['sizeSlug'] ) : 'large';
		$attrs = array(
			'id'               => $id,
			'sizeSlug'         => $size,
			'linkDestination'  => isset( $template_attrs['linkDestination'] ) ? $template_attrs['linkDestination'] : 'none',
		);
		$image_url = wp_get_attachment_image_url( $id, $size );
		if ( ! $image_url ) {
			$image_url = wp_get_attachment_image_url( $id, 'full' );
		}
		$alt       = get_post_meta( $id, '_wp_attachment_image_alt', true );
		$html      = '<figure class="wp-block-image size-' . esc_attr( $size ) . '"><img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . (int) $id . '"/></figure>';
		$markup = '<!-- wp:image ' . wp_json_encode( $attrs ) . ' -->' . $html . '<!-- /wp:image -->';
		$blocks = parse_blocks( $markup );
		return $blocks[0];
	}

	/**
	 * Replaces a gallery block's child image blocks while preserving wrapper markup.
	 *
	 * @param array  $blocks Parsed blocks, passed by reference.
	 * @param string $path   Dotted block path.
	 * @param array  $images New image blocks.
	 * @return bool
	 */
	private static function replace_gallery_at_path( &$blocks, $path, $images ) {
		$parts = explode( '.', $path );
		$index = (int) array_shift( $parts );
		if ( ! isset( $blocks[ $index ] ) ) {
			return false;
		}
		if ( $parts ) {
			if ( empty( $blocks[ $index ]['innerBlocks'] ) ) {
				return false;
			}
			return self::replace_gallery_at_path( $blocks[ $index ]['innerBlocks'], implode( '.', $parts ), $images );
		}
		if ( 'core/gallery' !== ( $blocks[ $index ]['blockName'] ?? '' ) ) {
			return false;
		}
		$inner_content = $blocks[ $index ]['innerContent'];
		$opening       = '';
		$closing       = '';
		$separator     = "\n";
		if ( preg_match( '/^\s*(<figure\b[^>]*>)/', $blocks[ $index ]['innerHTML'], $matches ) ) {
			$opening = $matches[1];
		}
		if ( preg_match( '/(<\/figure>\s*)$/', $blocks[ $index ]['innerHTML'], $matches ) ) {
			$closing = $matches[1];
		}
		if ( ! $opening || ! $closing ) {
			return false;
		}
		$content = array( $opening );
		foreach ( $images as $image ) {
			$content[] = null;
			$content[] = $separator;
		}
		if ( $images ) {
			array_pop( $content );
		}
		$content[] = $closing;
		$blocks[ $index ]['innerBlocks']  = $images;
		$blocks[ $index ]['innerContent'] = $content;
		$blocks[ $index ]['innerHTML']    = implode( '', array_filter( $content, 'is_string' ) );
		return true;
	}

	/**
	 * Saves featured images for published products or blog posts only.
	 */
	public static function save_featured_images() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tch' ), 403 );
		}
		check_admin_referer( 'tch_save_featured_images' );
		$type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		if ( ! in_array( $type, array( 'tch_product', 'post' ), true ) ) {
			wp_die( esc_html__( 'The requested image section is invalid.', 'tch' ), 400 );
		}
		$slug = 'tch_product' === $type ? 'tch-website-images-products' : 'tch-website-images-blog';
		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$paged = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;
		$redirect = add_query_arg(
			array_filter(
				array(
					'page'  => $slug,
					's'     => $search,
					'paged' => 1 < $paged ? $paged : '',
				)
			),
			admin_url( 'admin.php' )
		);
		$submitted = isset( $_POST['image_ids'] ) && is_array( $_POST['image_ids'] ) ? wp_unslash( $_POST['image_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.
		foreach ( $submitted as $raw_post_id => $raw_image_id ) {
			$post_id = absint( $raw_post_id );
			$image_id = absint( $raw_image_id );
			$post = get_post( $post_id );
			if ( ! $post || $type !== $post->post_type || 'publish' !== $post->post_status || ! current_user_can( 'edit_post', $post_id ) ) {
				self::back( $redirect, __( 'An item could not be verified. No changes were saved.', 'tch' ), true );
			}
			if ( ! $image_id ) {
				continue;
			}
			if ( ! wp_attachment_is_image( $image_id ) || ! wp_get_attachment_image_url( $image_id, 'full' ) ) {
				self::back( $redirect, __( 'Choose a valid image for every item.', 'tch' ), true );
			}
			set_post_thumbnail( $post_id, $image_id );
			if ( (int) get_post_thumbnail_id( $post_id ) !== $image_id ) {
				self::back( $redirect, __( 'WordPress could not save one of the featured images.', 'tch' ), true );
			}
		}
		self::back( $redirect, __( 'Featured images saved.', 'tch' ) );
	}

	/* ------------------------------------------------------------------
	 * Small admin helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Admin notice from the query string after a redirect.
	 */
	private static function notice() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_GET['tch_msg'] ) ) {
			$type = ! empty( $_GET['tch_err'] ) ? 'error' : 'success';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['tch_msg'] ) ) ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Displays a section heading and error when its target page is unavailable.
	 *
	 * @param string $title Section heading.
	 * @param string $message Error message.
	 */
	private static function page_error( $title, $message ) {
		?>
		<div class="wrap tch-wrap tch-wi-admin">
			<h1><?php echo esc_html( $title ); ?></h1>
			<div class="notice notice-error"><p><?php echo esc_html( $message ); ?></p></div>
		</div>
		<?php
	}

	/**
	 * Redirect back to the relevant dashboard section with a message.
	 *
	 * @param string $url URL.
	 * @param string $msg Message.
	 * @param bool   $err Error flag.
	 */
	private static function back( $url, $msg, $err = false ) {
		wp_safe_redirect( add_query_arg( array_filter( array( 'tch_msg' => $msg, 'tch_err' => $err ? 1 : 0 ) ), $url ) );
		exit;
	}
}
