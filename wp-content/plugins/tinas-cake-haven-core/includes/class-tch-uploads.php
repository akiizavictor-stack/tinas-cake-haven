<?php
/**
 * Private uploads: payment screenshots and design reference photos.
 *
 * Files are stored outside the public media library in uploads/tch-private/
 * (direct web access denied by .htaccess) with random names, validated by real
 * image content, and served only to staff through a capability + nonce checked endpoint.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Uploads.
 */
class TCH_Uploads {

	const MAX_BYTES = 3145728; // 3 MB.

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_tch_view_file', array( __CLASS__, 'serve' ) );
	}

	/**
	 * Absolute private directory.
	 *
	 * @return string
	 */
	public static function dir() {
		$up = wp_upload_dir( null, false );
		return trailingslashit( $up['basedir'] ) . 'tch-private';
	}

	/**
	 * Creates the directory and its deny rules.
	 */
	public static function protect_dir() {
		$dir = self::dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$ht = $dir . '/.htaccess';
		if ( ! file_exists( $ht ) ) {
			file_put_contents( $ht, "# Private customer files - served only via WordPress after a permission check\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}

	/**
	 * Validates and stores an uploaded image.
	 *
	 * @param array  $file   Entry from $_FILES.
	 * @param string $prefix pay|ref.
	 * @return string|null|WP_Error Stored file name, null when nothing was uploaded.
	 */
	public static function store( $file, $prefix ) {
		if ( empty( $file ) || ! isset( $file['error'] ) || UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
			return null;
		}
		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'upload', __( 'The photo could not be uploaded. Please try a smaller image.', 'tch' ) );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return new WP_Error( 'upload', __( 'Photos must be smaller than 3 MB.', 'tch' ) );
		}
		if ( (int) $file['size'] < 100 ) {
			return new WP_Error( 'upload', __( 'That file is not a valid photo. Only JPG, PNG or WEBP photos are allowed.', 'tch' ) );
		}
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'upload', __( 'Invalid upload.', 'tch' ) );
		}
		// Check the real content, not the browser-supplied type or extension.
		$info = @getimagesize( $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$map  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' );
		$mime = is_array( $info ) && isset( $info['mime'] ) ? $info['mime'] : '';
		if ( ! isset( $map[ $mime ] ) ) {
			return new WP_Error( 'upload', __( 'Only JPG, PNG or WEBP photos are allowed.', 'tch' ) );
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], 'upload.' . $map[ $mime ] );
		if ( empty( $check['type'] ) ) {
			return new WP_Error( 'upload', __( 'Only JPG, PNG or WEBP photos are allowed.', 'tch' ) );
		}
		if ( $info[0] > 8000 || $info[1] > 8000 ) {
			return new WP_Error( 'upload', __( 'The photo dimensions are too large.', 'tch' ) );
		}
		self::protect_dir();
		$name = sanitize_key( $prefix ) . '-' . gmdate( 'Ymd' ) . '-' . bin2hex( random_bytes( 12 ) ) . '.' . $map[ $mime ];
		$dest = self::dir() . '/' . $name;
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return new WP_Error( 'upload', __( 'The photo could not be saved.', 'tch' ) );
		}
		@chmod( $dest, 0640 ); // phpcs:ignore
		return $name;
	}

	/**
	 * Staff-only URL to view a private file.
	 *
	 * @param string $name File name.
	 * @return string
	 */
	public static function view_url( $name ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=tch_view_file&f=' . rawurlencode( $name ) ), 'tch_view_file_' . $name );
	}

	/**
	 * Streams a private file to authorised staff.
	 */
	public static function serve() {
		$name = isset( $_GET['f'] ) ? sanitize_file_name( wp_unslash( $_GET['f'] ) ) : '';
		if ( ! current_user_can( 'tch_manage_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this file.', 'tch' ), 403 );
		}
		check_admin_referer( 'tch_view_file_' . $name );
		if ( ! preg_match( '/^(pay|ref)-\d{8}-[a-f0-9]{24}\.(jpg|png|webp)$/', $name ) ) {
			wp_die( esc_html__( 'File not found.', 'tch' ), 404 );
		}
		$path = self::dir() . '/' . $name;
		if ( ! is_file( $path ) ) {
			wp_die( esc_html__( 'File not found.', 'tch' ), 404 );
		}
		$types = array( 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
		$ext   = pathinfo( $name, PATHINFO_EXTENSION );
		nocache_headers();
		header( 'Content-Type: ' . $types[ $ext ] );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: inline; filename="' . $name . '"' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Deletes a private file (used when an order is deleted).
	 *
	 * @param string|null $name File.
	 */
	public static function delete( $name ) {
		if ( $name && preg_match( '/^(pay|ref)-\d{8}-[a-f0-9]{24}\.(jpg|png|webp)$/', $name ) ) {
			wp_delete_file( self::dir() . '/' . $name );
		}
	}
}
