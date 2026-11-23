<?php
/**
 * Hero slider – the full-width image carousel at the top of the homepage.
 *
 * Front-end: get_slides() returns the slides to show (saved configuration, or
 * the built-in default bakery photos when nothing is configured yet).
 *
 * wp-admin: an "Hero Slider" menu lets the owner pick photos from the Media
 * Library, add/remove slides, reorder them and save an optional title for each
 * slide. Every action checks the capability and a nonce.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hero Slider module.
 */
class TCH_Hero_Slider {

	const OPTION    = 'tch_hero_slider';
	const CAP       = 'manage_options';
	const MAX_SLIDES = 12;

	/**
	 * Hooks. Registered only in wp-admin (see tch_boot()).
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_tch_save_hero_slider', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_tch_reset_hero_slider', array( __CLASS__, 'reset' ) );
	}

	/* ------------------------------------------------------------------
	 * Front-end data
	 * ------------------------------------------------------------------ */

	/**
	 * The default bakery photos used until the owner configures their own.
	 *
	 * @return array<int,array{id:int,width:int,height:int,url:string,alt:string,srcset:string}>
	 */
	public static function default_images() {
		$uploads = wp_get_upload_dir();
		$base    = isset( $uploads['baseurl'] ) ? trailingslashit( $uploads['baseurl'] ) . '2026/11/' : home_url( '/wp-content/uploads/2026/11/' );

		return array(
			array( 'id' => 0, 'url' => $base . 'hero-chocolate-happy-birthday-cake-1536x1229.webp', 'width' => 1536, 'height' => 1229, 'alt' => __( 'Chocolate drip birthday cake with piped rosettes and Happy Birthday writing', 'tch' ), 'srcset' => '' ),
			array( 'id' => 0, 'url' => $base . 'floral-happy-birthday-cake-1536x1152.webp',         'width' => 1536, 'height' => 1152, 'alt' => __( 'White birthday cake decorated with pink and yellow buttercream roses', 'tch' ), 'srcset' => '' ),
			array( 'id' => 0, 'url' => $base . 'stock-flower-topped-small-cake-1536x1023.webp',      'width' => 1536, 'height' => 1023, 'alt' => __( 'Small birthday cake decorated with fresh flowers', 'tch' ), 'srcset' => '' ),
			array( 'id' => 0, 'url' => $base . 'vanilla-cupcakes-with-sprinkles-1024x1536.webp',     'width' => 1024, 'height' => 1536, 'alt' => __( 'Vanilla cupcakes with swirled white frosting and rainbow sprinkles', 'tch' ), 'srcset' => '' ),
			array( 'id' => 0, 'url' => $base . 'chocolate-chip-cookies-on-plate-768x768.webp',       'width' => 768,  'height' => 768,  'alt' => __( 'Plate of freshly baked chocolate chip cookies', 'tch' ), 'srcset' => '' ),
		);
	}

	/**
	 * Returns the slides for the front-end, in display order.
	 *
	 * Uses the saved configuration when valid slides exist, otherwise falls
	 * back to the built-in default photos so the homepage never breaks.
	 *
	 * @return array<int,array{id:int,width:int,height:int,url:string,alt:string,srcset:string}>
	 */
	public static function get_slides() {
		$saved = get_option( self::OPTION, array() );
		$slides = self::validate( $saved );
		return $slides ? $slides : self::default_images();
	}

	/**
	 * Validates a raw stored configuration into render-ready slides.
	 * Drops anything that is not a real image attachment.
	 *
	 * @param mixed $raw Option value.
	 * @return array<int,array{id:int,width:int,height:int,url:string,alt:string,srcset:string}>
	 */
	public static function validate( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $slide ) {
			if ( ! is_array( $slide ) ) {
				continue;
			}
			$id = absint( $slide['id'] ?? 0 );
			if ( ! $id || ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			$src = wp_get_attachment_image_src( $id, 'large' );
			if ( empty( $src[0] ) ) {
				continue;
			}
			$title = isset( $slide['title'] ) ? sanitize_text_field( $slide['title'] ) : '';
			$alt   = '' !== $title ? $title : (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			$out[] = array(
				'id'     => $id,
				'url'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
				'alt'    => $alt,
				'srcset' => (string) wp_get_attachment_image_srcset( $id, 'large' ),
			);
		}
		return $out;
	}

	/**
	 * The default photos expressed as editable form rows (attachment ids resolved
	 * from the Media Library), used to pre-fill the admin screen on first visit.
	 *
	 * attachment_url_to_postid() matches the original file URL, so the -WxH size
	 * suffix is stripped before the lookup.
	 *
	 * @return array<int,array{id:int,title:string}>
	 */
	public static function seed_defaults() {
		$rows = array();
		foreach ( self::default_images() as $d ) {
			$orig = preg_replace( '/-\d+x\d+(?=\.[a-z0-9]+?$)/i', '', $d['url'] );
			$id   = (int) attachment_url_to_postid( $orig );
			$rows[] = array( 'id' => $id, 'title' => '' );
		}
		return $rows;
	}

	/**
	 * The slides to show in the admin form: the saved configuration, or the
	 * seeded defaults so the owner immediately sees the photos currently on the
	 * homepage and can edit them.
	 *
	 * @return array<int,array{id:int,title:string}>
	 */
	public static function form_slides() {
		$saved = get_option( self::OPTION, array() );
		if ( is_array( $saved ) && ! empty( $saved ) ) {
			return $saved;
		}
		return self::seed_defaults();
	}

	/* ------------------------------------------------------------------
	 * Admin menu + assets
	 * ------------------------------------------------------------------ */

	/**
	 * Registers the "Hero Slider" admin menu.
	 */
	public static function menu() {
		add_menu_page(
			__( 'Hero Slider', 'tch' ),
			__( 'Hero Slider', 'tch' ),
			self::CAP,
			'tch-hero-slider',
			array( __CLASS__, 'page' ),
			'dashicons-format-image',
			26
		);
		// Remove the duplicate submenu WordPress auto-creates for a single-page menu.
		remove_submenu_page( 'tch-hero-slider', 'tch-hero-slider' );
	}

	/**
	 * Admin assets for the Hero Slider screen only.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public static function assets( $hook ) {
		if ( 'toplevel_page_tch-hero-slider' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'tch-hero-slider-admin', TCH_URL . 'assets/css/hero-slider-admin.css', array(), TCH_VERSION );
		wp_enqueue_script( 'tch-hero-slider-admin', TCH_URL . 'assets/js/hero-slider-admin.js', array( 'jquery' ), TCH_VERSION, true );
		wp_localize_script(
			'tch-hero-slider-admin',
			'tchHeroSliderAdmin',
			array(
				'title'       => __( 'Choose a slider image', 'tch' ),
				'use'          => __( 'Use this image', 'tch' ),
				'change'       => __( 'Change image', 'tch' ),
				'remove'       => __( 'Remove', 'tch' ),
				'up'           => __( 'Move up', 'tch' ),
				'down'         => __( 'Move down', 'tch' ),
				'placeholder'  => __( 'Optional title / description', 'tch' ),
				'unsaved'      => __( 'You have unsaved changes to the hero slider.', 'tch' ),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Admin page
	 * ------------------------------------------------------------------ */

	/**
	 * Renders the Hero Slider admin page.
	 */
	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the hero slider.', 'tch' ), 403 );
		}
		$rows     = self::form_slides();
		$homepage = home_url( '/' );
		$reset    = wp_nonce_url( admin_url( 'admin-post.php?action=tch_reset_hero_slider' ), 'tch_reset_hero_slider' );
		?>
		<div class="wrap tch-wrap tch-hero-slider-admin">
			<h1><?php esc_html_e( 'Hero Slider', 'tch' ); ?></h1>
			<?php self::notice(); ?>
			<p class="description">
				<?php esc_html_e( 'These photos appear in the large image slider at the top of your homepage. The slider changes images automatically every few seconds and visitors can use the arrows, dots and pause button.', 'tch' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="tch-hero-slider-form">
				<?php wp_nonce_field( 'tch_save_hero_slider' ); ?>
				<input type="hidden" name="action" value="tch_save_hero_slider">

				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:64px"><?php esc_html_e( 'Order', 'tch' ); ?></th>
							<th style="width:120px"><?php esc_html_e( 'Image', 'tch' ); ?></th>
							<th><?php esc_html_e( 'Preview', 'tch' ); ?></th>
							<th><?php esc_html_e( 'Title / description (optional)', 'tch' ); ?></th>
							<th style="width:240px"><?php esc_html_e( 'Actions', 'tch' ); ?></th>
						</tr>
					</thead>
					<tbody id="tch-hero-slides">
						<?php foreach ( $rows as $row ) : ?>
							<?php self::render_row( $row ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<button type="button" class="button button-secondary tch-add-slide"><?php esc_html_e( '+ Add slide', 'tch' ); ?></button>
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save slider', 'tch' ); ?></button>
					<a class="button" href="<?php echo esc_url( $reset ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Restore the default bakery photos? Your current selection will be cleared.', 'tch' ) ); ?>');"><?php esc_html_e( 'Reset to default photos', 'tch' ); ?></a>
					<a class="button" href="<?php echo esc_url( $homepage ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View homepage', 'tch' ); ?></a>
				</p>
			</form>

			<p class="description"><?php esc_html_e( 'Tip: the title is optional and is used as the alt text for accessibility and SEO. If you leave it blank, the image caption from the Media Library is used.', 'tch' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders one editable slide row.
	 *
	 * @param array{id:int,title:string} $row Slide row.
	 */
	private static function render_row( $row ) {
		$id    = absint( $row['id'] ?? 0 );
		$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
		$thumb = '';
		if ( $id ) {
			$t = wp_get_attachment_image_src( $id, 'thumbnail' );
			if ( $t ) {
				$thumb = $t[0];
			}
		}
		?>
		<tr class="tch-slide-row">
			<td class="tch-slide-order">
				<button type="button" class="button tch-move-up" aria-label="<?php esc_attr_e( 'Move up', 'tch' ); ?>">&uarr;</button>
				<button type="button" class="button tch-move-down" aria-label="<?php esc_attr_e( 'Move down', 'tch' ); ?>">&darr;</button>
			</td>
			<td class="tch-slide-id-cell">
				<input type="hidden" name="slide_ids[]" class="tch-slide-id" value="<?php echo (int) $id; ?>">
				<?php if ( $id ) : ?>
					<code class="tch-slide-id-label">#<?php echo (int) $id; ?></code>
				<?php else : ?>
					<code class="tch-slide-id-label tch-id-default"><?php esc_html_e( 'Default', 'tch' ); ?></code>
				<?php endif; ?>
			</td>
			<td class="tch-slide-preview">
				<?php if ( $thumb ) : ?>
					<img class="tch-slide-thumb" src="<?php echo esc_url( $thumb ); ?>" alt="" width="80" height="80">
				<?php else : ?>
					<span class="tch-no-image"><?php esc_html_e( 'No image chosen', 'tch' ); ?></span>
				<?php endif; ?>
			</td>
			<td>
				<input type="text" name="slide_titles[]" class="regular-text" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'Optional title / description', 'tch' ); ?>" maxlength="200">
			</td>
			<td class="tch-slide-actions">
				<button type="button" class="button tch-change-image"><?php esc_html_e( 'Change image', 'tch' ); ?></button>
				<button type="button" class="button tch-remove-slide"><?php esc_html_e( 'Remove', 'tch' ); ?></button>
			</td>
		</tr>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Save / reset handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Saves the slider configuration from the admin form.
	 */
	public static function save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tch' ), 403 );
		}
		check_admin_referer( 'tch_save_hero_slider' );

		$ids    = isset( $_POST['slide_ids'] ) ? (array) wp_unslash( $_POST['slide_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized below.
		$titles = isset( $_POST['slide_titles'] ) ? (array) wp_unslash( $_POST['slide_titles'] ) : array();

		$out = array();
		$n   = count( $ids );
		for ( $i = 0; $i < $n; $i++ ) {
			$id = absint( $ids[ $i ] ?? 0 );
			if ( ! $id || ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			$title    = isset( $titles[ $i ] ) ? sanitize_text_field( wp_unslash( $titles[ $i ] ) ) : '';
			$out[] = array(
				'id'    => $id,
				'title' => mb_substr( $title, 0, 200 ),
			);
		}

		$out = array_slice( $out, 0, self::MAX_SLIDES );
		update_option( self::OPTION, $out, false );

		self::back( admin_url( 'admin.php?page=tch-hero-slider' ), sprintf( /* translators: %d: number of slides */ __( '%d slide(s) saved.', 'tch' ), count( $out ) ) );
	}

	/**
	 * Resets to the default bakery photos (clears the saved configuration so the
	 * homepage shows the built-in defaults again).
	 */
	public static function reset() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tch' ), 403 );
		}
		check_admin_referer( 'tch_reset_hero_slider' );
		delete_option( self::OPTION );
		self::back( admin_url( 'admin.php?page=tch-hero-slider' ), __( 'Restored the default bakery photos.', 'tch' ) );
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
	 * Redirect back to the slider page with a message.
	 *
	 * @param string $url URL.
	 * @param string $msg Message.
	 * @param bool   $err Error flag.
	 */
	private static function back( $url, $msg, $err = false ) {
		wp_safe_redirect( add_query_arg( array_filter( array( 'tch_msg' => rawurlencode( $msg ), 'tch_err' => $err ? 1 : 0 ) ), $url ) );
		exit;
	}
}
