<?php
/**
 * Public shortcodes and form handlers.
 *
 * Shortcodes (usable in any theme / page builder):
 *   [tch_products]        product cards grid      [tch_product_details] price & order box on a product page
 *   [tch_price_list]      compact price list      [tch_order_form]      Order Online
 *   [tch_custom_cake_form] customized cake form   [tch_my_orders]       logged-in order history
 *   [tch_track_order]     guest order tracking    [tch_contact_form]    contact form
 *   [tch_account]         login / profile (TCH_Accounts)   [tch_info key=".."] business details
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend.
 */
class TCH_Frontend {

	/**
	 * Flash messages for this request.
	 *
	 * @var array
	 */
	private static $flashes = array();

	/**
	 * Old input to refill forms after a validation error.
	 *
	 * @var array
	 */
	public static $old = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'tch_products', array( __CLASS__, 'products' ) );
		add_shortcode( 'tch_product_details', array( __CLASS__, 'product_details' ) );
		add_shortcode( 'tch_price_list', array( __CLASS__, 'price_list' ) );
		add_shortcode( 'tch_order_form', array( __CLASS__, 'order_form' ) );
		add_shortcode( 'tch_custom_cake_form', array( __CLASS__, 'custom_form' ) );
		add_shortcode( 'tch_my_orders', array( __CLASS__, 'my_orders' ) );
		add_shortcode( 'tch_track_order', array( __CLASS__, 'track_order' ) );
		add_shortcode( 'tch_contact_form', array( __CLASS__, 'contact_form' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'wp_redirect', array( __CLASS__, 'persist_flashes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'no_cache_private_pages' ), 1 );
		add_filter( 'render_block_core/post-featured-image', array( __CLASS__, 'label_illustrative' ), 10, 2 );
	}

	/**
	 * Stock photos are never presented as real products: label them wherever a
	 * featured image is shown (blog posts, product pages).
	 *
	 * @param string $html  Block HTML.
	 * @param array  $block Block.
	 * @return string
	 */
	public static function label_illustrative( $html, $block ) {
		$thumb = (int) get_post_thumbnail_id();
		if ( $html && $thumb && get_post_meta( $thumb, '_tch_illustrative', true ) ) {
			$cap   = wp_get_attachment_caption( $thumb );
			$html .= '<p class="tch-illus">' . esc_html( $cap ? $cap : __( 'Illustrative photo', 'tch' ) ) . '</p>';
		}
		return $html;
	}

	/* ------------------------------------------------------------------
	 * Assets, flash messages, helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Registers CSS/JS.
	 */
	public static function register_assets() {
		wp_register_style( 'tch-frontend', TCH_URL . 'assets/css/frontend.css', array(), TCH_VERSION . '.' . filemtime( TCH_DIR . 'assets/css/frontend.css' ) );
		wp_register_script( 'tch-order', TCH_URL . 'assets/js/order.js', array(), TCH_VERSION . '.' . filemtime( TCH_DIR . 'assets/js/order.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_enqueue_style( 'tch-frontend' );
	}

	/**
	 * Enqueues assets when a shortcode renders.
	 *
	 * @param bool $js Also load the order script.
	 */
	public static function enqueue( $js = false ) {
		wp_enqueue_style( 'tch-frontend' );
		if ( $js ) {
			wp_enqueue_script( 'tch-order' );
		}
	}

	/**
	 * Pages with personal data must never be cached by page caches or browsers.
	 */
	public static function no_cache_private_pages() {
		$pages = get_option( 'tch_pages', array() );
		$keys  = array( 'order', 'custom', 'my-orders', 'track', 'account', 'contact' );
		foreach ( $keys as $k ) {
			if ( ! empty( $pages[ $k ] ) && is_page( $pages[ $k ] ) ) {
				if ( ! defined( 'DONOTCACHEPAGE' ) ) {
					define( 'DONOTCACHEPAGE', true ); // Honoured by WP Super Cache, W3TC, LiteSpeed Cache.
				}
				nocache_headers();
				return;
			}
		}
	}

	/**
	 * Adds a flash message.
	 *
	 * @param string $type success|error|info.
	 * @param string $msg  Message.
	 */
	public static function flash( $type, $msg ) {
		self::$flashes[] = array( $type, $msg );
	}

	/**
	 * Storage key for flashes across a redirect.
	 *
	 * @param bool $create Create cookie for guests.
	 * @return string
	 */
	private static function flash_key( $create = false ) {
		if ( is_user_logged_in() ) {
			return 'tch_flash_u' . get_current_user_id();
		}
		$token = isset( $_COOKIE['tch_fk'] ) ? preg_replace( '/[^a-f0-9]/', '', sanitize_text_field( wp_unslash( $_COOKIE['tch_fk'] ) ) ) : '';
		if ( 32 !== strlen( $token ) ) {
			if ( ! $create || headers_sent() ) {
				return '';
			}
			$token = bin2hex( random_bytes( 16 ) );
			setcookie( 'tch_fk', $token, array( 'expires' => time() + 600, 'path' => COOKIEPATH, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		}
		return 'tch_flash_g' . $token;
	}

	/**
	 * Saves pending flashes before a redirect.
	 *
	 * @param string $location Location.
	 * @return string
	 */
	public static function persist_flashes( $location ) {
		if ( self::$flashes ) {
			$key = self::flash_key( true );
			if ( $key ) {
				set_transient( $key, self::$flashes, 300 );
			}
			self::$flashes = array();
		}
		return $location;
	}

	/**
	 * Renders and clears flash messages.
	 *
	 * @return string
	 */
	public static function render_flashes() {
		$key    = self::flash_key();
		$stored = $key ? get_transient( $key ) : array();
		if ( $key && $stored ) {
			delete_transient( $key );
		}
		$all           = array_merge( is_array( $stored ) ? $stored : array(), self::$flashes );
		self::$flashes = array();
		$html          = '';
		foreach ( $all as $f ) {
			$html .= '<div class="tch-alert tch-alert--' . esc_attr( $f[0] ) . '" role="' . ( 'error' === $f[0] ? 'alert' : 'status' ) . '">' . nl2br( esc_html( $f[1] ) ) . '</div>';
		}
		return $html;
	}

	/**
	 * Old input value.
	 *
	 * @param string $key     Key.
	 * @param string $default Default.
	 * @return string
	 */
	public static function old( $key, $default = '' ) {
		return isset( self::$old[ $key ] ) && is_scalar( self::$old[ $key ] ) ? (string) self::$old[ $key ] : $default;
	}

	/**
	 * Mobile Money instructions (never invented: falls back to "contact us").
	 *
	 * @return string
	 */
	public static function momo_text() {
		$txt = tch_setting( 'momo_instructions' );
		if ( '' !== trim( $txt ) ) {
			return $txt;
		}
		/* translators: %s phone */
		return sprintf( __( 'Please call or WhatsApp us on %s to receive our Mobile Money payment details before paying.', 'tch' ), tch_setting( 'phone' ) );
	}

	/**
	 * Signed token that lets a guest open their order after placing/looking it up.
	 *
	 * @param object $order Order.
	 * @return string
	 */
	public static function guest_token( $order ) {
		return substr( hash_hmac( 'sha256', 'tch_guest|' . $order->order_ref . '|' . $order->customer_phone_norm, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Issues a signed token for one intended order submission.
	 *
	 * @param string $type     standard|custom.
	 * @param string $existing Previously rendered token to preserve after validation errors.
	 * @return string
	 */
	public static function order_submission_token( $type, $existing = '' ) {
		if ( self::valid_order_submission_token( $existing, $type ) ) {
			return $existing;
		}
		$issued = time();
		$nonce  = bin2hex( random_bytes( 16 ) );
		$body   = $issued . '.' . $nonce;
		return $body . '.' . hash_hmac( 'sha256', self::order_submission_payload( $type, $body ), wp_salt( 'auth' ) );
	}

	/**
	 * Validates a signed order submission token and its expiry.
	 *
	 * @param string $token Token.
	 * @param string $type  standard|custom.
	 * @return bool
	 */
	public static function valid_order_submission_token( $token, $type ) {
		if ( ! in_array( $type, array( 'standard', 'custom' ), true ) || ! preg_match( '/^(\d{10})\.([a-f0-9]{32})\.([a-f0-9]{64})$/D', (string) $token, $matches ) ) {
			return false;
		}
		$issued = (int) $matches[1];
		if ( $issued > time() + 300 || time() - $issued > DAY_IN_SECONDS ) {
			return false;
		}
		$body     = $matches[1] . '.' . $matches[2];
		$expected = hash_hmac( 'sha256', self::order_submission_payload( $type, $body ), wp_salt( 'auth' ) );
		return hash_equals( $expected, $matches[3] );
	}

	/**
	 * Submission token binding for this order type, user, and remote network address.
	 *
	 * @param string $type Order type.
	 * @param string $body Token body.
	 * @return string
	 */
	private static function order_submission_payload( $type, $body ) {
		return 'tch_order|' . $type . '|' . get_current_user_id() . '|' . tch_client_ip() . '|' . $body;
	}

	/**
	 * URL where the visitor can see an order.
	 *
	 * @param object $order Order.
	 * @param array  $extra Extra args.
	 * @return string
	 */
	public static function order_url( $order, $extra = array() ) {
		if ( is_user_logged_in() && (int) $order->user_id === get_current_user_id() ) {
			return tch_page_url( 'my-orders', array_merge( array( 'order' => $order->order_ref ), $extra ) );
		}
		return tch_page_url( 'track', array_merge( array( 'ref' => $order->order_ref, 'k' => self::guest_token( $order ) ), $extra ) );
	}

	/**
	 * Resolves the order the current visitor is allowed to see (owner or signed guest link).
	 *
	 * @param string $ref   Order ref.
	 * @param string $token Guest token.
	 * @return object|null
	 */
	public static function authorised_order( $ref, $token = '' ) {
		$ref = strtoupper( sanitize_text_field( $ref ) );
		if ( ! preg_match( '/^TCH-\d{6}-[A-Z0-9]{5}$/', $ref ) ) {
			return null;
		}
		if ( is_user_logged_in() ) {
			$own = TCH_Orders::find_for_user( $ref, get_current_user_id() );
			if ( $own ) {
				return $own;
			}
		}
		if ( '' !== $token ) {
			$order = TCH_Orders::get_by_ref( $ref );
			if ( $order && hash_equals( self::guest_token( $order ), $token ) ) {
				return $order;
			}
		}
		return null;
	}

	/**
	 * Verifies nonce + honeypot for public forms.
	 *
	 * @param string $action Nonce action.
	 * @return bool
	 */
	private static function verify_form( $action ) {
		$nonce = isset( $_POST['_tch_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_tch_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			self::flash( 'error', __( 'Your session expired. Please check your details and submit again.', 'tch' ) );
			return false;
		}
		if ( ! empty( $_POST['tch_website'] ) ) { // Honeypot: real visitors never fill this hidden field.
			self::flash( 'error', __( 'Your submission could not be accepted.', 'tch' ) );
			return false;
		}
		return true;
	}

	/* ------------------------------------------------------------------
	 * POST handling (Post/Redirect/Get)
	 * ------------------------------------------------------------------ */

	/**
	 * Routes form posts.
	 */
	public static function handle_post() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['tch_action'] ) ) {
			return;
		}
		$action = sanitize_key( $_POST['tch_action'] );
		$in     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification -- verified per action below; each field is sanitised where used.
		self::$old = is_array( $in ) ? $in : array();

		switch ( $action ) {
			case 'place_order':
				if ( ! self::verify_form( 'tch_place_order' ) ) {
					return;
				}
				$submission_token = sanitize_text_field( $in['submission_token'] ?? '' );
				if ( ! self::valid_order_submission_token( $submission_token, 'standard' ) ) {
					self::flash( 'error', __( 'This order form expired. Reload it and submit again.', 'tch' ) );
					return;
				}
				if ( ! tch_rate_limit( 'order', 10, HOUR_IN_SECONDS ) ) {
					self::flash( 'error', __( 'Too many orders from this network. Please contact us on WhatsApp.', 'tch' ) );
					return;
				}
				$res = TCH_Orders::place_standard( $in, hash( 'sha256', $submission_token ) );
				self::after_order( $res );
				break;

			case 'place_custom':
				if ( ! self::verify_form( 'tch_place_custom' ) ) {
					return;
				}
				$submission_token = sanitize_text_field( $in['submission_token'] ?? '' );
				if ( ! self::valid_order_submission_token( $submission_token, 'custom' ) ) {
					self::flash( 'error', __( 'This cake request form expired. Reload it and submit again.', 'tch' ) );
					return;
				}
				if ( ! tch_rate_limit( 'order', 10, HOUR_IN_SECONDS ) ) {
					self::flash( 'error', __( 'Too many orders from this network. Please contact us on WhatsApp.', 'tch' ) );
					return;
				}
				$res = TCH_Orders::place_custom( $in, $_FILES, hash( 'sha256', $submission_token ) );
				self::after_order( $res );
				break;

			case 'submit_payment':
				if ( ! self::verify_form( 'tch_submit_payment' ) ) {
					return;
				}
				if ( ! tch_rate_limit( 'payment', 10, HOUR_IN_SECONDS ) ) {
					self::flash( 'error', __( 'Too many attempts. Please try again later.', 'tch' ) );
					return;
				}
				$order = self::authorised_order( $in['ref'] ?? '', sanitize_text_field( $in['k'] ?? '' ) );
				if ( ! $order ) {
					self::flash( 'error', __( 'We could not find that order.', 'tch' ) );
					return;
				}
				$res = TCH_Payments::submit( $order, $in, $_FILES );
				if ( is_wp_error( $res ) ) {
					self::flash( 'error', $res->get_error_message() );
					return;
				}
				self::$old = array();
				self::flash( 'success', __( 'Thank you! Your payment details were sent. Your payment is now "Pending verification" until we confirm it on our Mobile Money account.', 'tch' ) );
				wp_safe_redirect( self::order_url( TCH_Orders::get( (int) $order->id ) ) );
				exit;

			case 'track':
				if ( ! self::verify_form( 'tch_track' ) ) {
					return;
				}
				if ( ! tch_rate_limit( 'track', 15, 15 * MINUTE_IN_SECONDS ) ) {
					self::flash( 'error', __( 'Too many lookups. Please wait 15 minutes and try again.', 'tch' ) );
					return;
				}
				$order = TCH_Orders::find_for_guest( sanitize_text_field( $in['ref'] ?? '' ), sanitize_text_field( $in['phone'] ?? '' ) );
				if ( ! $order ) {
					self::flash( 'error', __( 'No order matches that reference and phone number. Please check both and try again.', 'tch' ) );
					return;
				}
				wp_safe_redirect( tch_page_url( 'track', array( 'ref' => $order->order_ref, 'k' => self::guest_token( $order ) ) ) );
				exit;

			case 'contact':
				if ( ! self::verify_form( 'tch_contact' ) ) {
					return;
				}
				if ( ! tch_rate_limit( 'contact', 5, HOUR_IN_SECONDS ) ) {
					self::flash( 'error', __( 'Too many messages. Please call or WhatsApp us instead.', 'tch' ) );
					return;
				}
				self::save_contact( $in );
				break;
		}
	}

	/**
	 * Redirects after an order attempt.
	 *
	 * @param array $res Result.
	 */
	private static function after_order( $res ) {
		if ( ! $res['ok'] ) {
			self::flash( 'error', implode( "\n", $res['errors'] ) );
			return;
		}
		self::$old = array();
		$order     = TCH_Orders::get_by_ref( $res['ref'] );
		/* translators: %s ref */
		self::flash( 'success', sprintf( __( 'Thank you! Your order %s has been received. Please keep this reference number.', 'tch' ), $res['ref'] ) );
		wp_safe_redirect( self::order_url( $order, array( 'placed' => 1 ) ) );
		exit;
	}

	/**
	 * Stores a contact message.
	 *
	 * @param array $in Input.
	 */
	private static function save_contact( $in ) {
		global $wpdb;
		$name    = sanitize_text_field( $in['full_name'] ?? '' );
		$phone   = sanitize_text_field( $in['phone'] ?? '' );
		$email   = sanitize_email( $in['email'] ?? '' );
		$subject = sanitize_text_field( $in['subject'] ?? '' );
		$message = sanitize_textarea_field( $in['message'] ?? '' );
		$errors  = array();
		if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 120 ) {
			$errors[] = __( 'Please enter your name.', 'tch' );
		}
		if ( '' === $phone && '' === $email ) {
			$errors[] = __( 'Please give a phone number or email so we can reply.', 'tch' );
		}
		if ( '' !== $phone && ! tch_valid_phone( $phone ) ) {
			$errors[] = __( 'The phone number is not valid.', 'tch' );
		}
		if ( ! empty( $in['email'] ) && ! is_email( $email ) ) {
			$errors[] = __( 'The email address is not valid.', 'tch' );
		}
		if ( mb_strlen( $subject ) < 2 || mb_strlen( $subject ) > 150 ) {
			$errors[] = __( 'Please enter a subject.', 'tch' );
		}
		if ( mb_strlen( $message ) < 5 || mb_strlen( $message ) > 3000 ) {
			$errors[] = __( 'Please enter your message.', 'tch' );
		}
		if ( $errors ) {
			self::flash( 'error', implode( "\n", $errors ) );
			return;
		}
		$wpdb->insert(
			tch_table( 'messages' ),
			array( 'full_name' => $name, 'phone' => $phone ? $phone : null, 'email' => $email ? $email : null, 'subject' => $subject, 'message' => $message, 'is_read' => 0, 'created_at' => current_time( 'mysql' ) ),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		$to = tch_setting( 'notify_email' ) ? tch_setting( 'notify_email' ) : get_option( 'admin_email' );
		wp_mail(
			$to,
			'[' . tch_setting( 'business_name' ) . '] ' . __( 'Website message:', 'tch' ) . ' ' . $subject,
			$name . "\n" . $phone . "\n" . $email . "\n\n" . $message,
			$email ? array( 'Reply-To: ' . $name . ' <' . $email . '>' ) : array()
		);
		self::$old = array();
		self::flash( 'success', __( 'Thank you! Your message was sent. We will get back to you soon.', 'tch' ) );
		wp_safe_redirect( tch_page_url( 'contact' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Shortcodes
	 * ------------------------------------------------------------------ */

	/**
	 * [tch_products category="cupcakes" limit="6" button="1"].
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public static function products( $atts ) {
		self::enqueue();
		$atts = shortcode_atts( array( 'category' => '', 'limit' => 12, 'exclude' => '', 'exclude_current' => '0' ), $atts, 'tch_products' );
		if ( '1' === $atts['exclude_current'] && is_singular( TCH_Catalogue::PT ) ) {
			$atts['exclude'] = trim( $atts['exclude'] . ',' . get_queried_object_id(), ',' );
		}
		if ( '' === $atts['category'] && is_tax( TCH_Catalogue::TAX ) ) {
			$atts['category'] = get_queried_object()->slug; // Category archive pages.
		}
		$args = array(
			'post_type'      => TCH_Catalogue::PT,
			'post_status'    => 'publish',
			'posts_per_page' => min( 50, absint( $atts['limit'] ) ),
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'no_found_rows'  => true,
		);
		if ( $atts['category'] ) {
			$args['tax_query'] = array( array( 'taxonomy' => TCH_Catalogue::TAX, 'field' => 'slug', 'terms' => array_map( 'sanitize_title', explode( ',', $atts['category'] ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		if ( $atts['exclude'] ) {
			$args['post__not_in'] = array_map( 'absint', explode( ',', $atts['exclude'] ) );
		}
		$products = get_posts( $args );
		return tch_template( 'product-grid', array( 'products' => $products ) );
	}

	/**
	 * [tch_product_details] on a single product page.
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public static function product_details( $atts ) {
		self::enqueue();
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'tch_product_details' );
		$id   = $atts['id'] ? absint( $atts['id'] ) : get_the_ID();
		$cfg  = TCH_Catalogue::config( $id );
		if ( ! $cfg ) {
			return '';
		}
		return tch_template( 'product-details', array( 'cfg' => $cfg, 'flavours' => TCH_Catalogue::flavours() ) );
	}

	/**
	 * [tch_price_list].
	 *
	 * @return string
	 */
	public static function price_list() {
		self::enqueue();
		return tch_template( 'price-list', array( 'sizes' => TCH_Catalogue::sizes(), 'flavours' => TCH_Catalogue::flavours(), 'products' => TCH_Catalogue::orderable_products() ) );
	}

	/**
	 * [tch_order_form].
	 *
	 * @return string
	 */
	public static function order_form() {
		self::enqueue( true );
		$products = TCH_Catalogue::orderable_products();
		$custom   = (int) get_option( 'tch_custom_product_id' );
		$products = array_values(
			array_filter(
				$products,
				static function ( $p ) use ( $custom ) {
					return $p['id'] !== $custom;
				}
			)
		);
		$data = array();
		foreach ( $products as $p ) {
			$data[ $p['id'] ] = array(
				'type'     => $p['type'],
				'price'    => $p['unit_price'],
				'bundle'   => $p['bundle_size'],
				'flavours' => $p['uses_flavours'],
				'min'      => $p['min_qty'],
				'max'      => $p['max_qty'],
				'sizes'    => array_map(
					static function ( $s ) {
						return array( 'id' => (int) $s->id, 'price' => (int) $s->price );
					},
					TCH_Catalogue::sizes_for( $p )
				),
			);
		}
		$pre = isset( $_GET['product'] ) ? absint( $_GET['product'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		return tch_template(
			'order-form',
			array(
				'products' => $products,
				'sizes'    => TCH_Catalogue::sizes(),
				'flavours' => TCH_Catalogue::flavours(),
				'data'     => $data,
				'pre'      => $pre,
				'deposit'  => TCH_Payments::DEPOSIT_PERCENT,
			)
		);
	}

	/**
	 * [tch_custom_cake_form].
	 *
	 * @return string
	 */
	public static function custom_form() {
		self::enqueue( true );
		$cfg = TCH_Catalogue::config( (int) get_option( 'tch_custom_product_id' ) );
		if ( ! $cfg ) {
			return '<p class="tch-alert tch-alert--info">' . esc_html__( 'Custom cake ordering is being set up. Please contact us on WhatsApp.', 'tch' ) . '</p>';
		}
		return tch_template(
			'custom-form',
			array(
				'sizes'    => TCH_Catalogue::sizes_for( $cfg ),
				'flavours' => TCH_Catalogue::flavours(),
				'hours'    => (int) tch_setting( 'custom_notice_hours', '24' ),
				'deposit'  => TCH_Payments::DEPOSIT_PERCENT,
			)
		);
	}

	/**
	 * Common prefill for customer fields.
	 *
	 * @return array
	 */
	public static function customer_prefill() {
		$user = wp_get_current_user();
		return array(
			'full_name' => self::old( 'full_name', $user->ID ? $user->display_name : '' ),
			'phone'     => self::old( 'phone', $user->ID ? (string) get_user_meta( $user->ID, 'tch_phone', true ) : '' ),
			'email'     => self::old( 'email', $user->ID ? $user->user_email : '' ),
		);
	}

	/**
	 * [tch_my_orders] – latest three, then "view older orders".
	 *
	 * @return string
	 */
	public static function my_orders() {
		self::enqueue();
		if ( ! is_user_logged_in() ) {
			return tch_template( 'login-required' );
		}
		$uid = get_current_user_id();
		$ref = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $ref ) {
			$order = TCH_Orders::find_for_user( $ref, $uid ); // Ownership enforced in SQL.
			if ( ! $order ) {
				return self::render_flashes() . '<div class="tch-alert tch-alert--error">' . esc_html__( 'Order not found in your account.', 'tch' ) . '</div><p><a class="tch-btn tch-btn--ghost" href="' . esc_url( tch_page_url( 'my-orders' ) ) . '">' . esc_html__( '← Back to my orders', 'tch' ) . '</a></p>';
			}
			return self::render_order( $order, tch_page_url( 'my-orders' ) );
		}
		$all      = ! empty( $_GET['all'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$page     = max( 1, isset( $_GET['pg'] ) ? absint( $_GET['pg'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification
		$per      = $all ? 10 : 3;
		$total    = TCH_Orders::count_for_user( $uid );
		$orders   = TCH_Orders::for_user( $uid, $per, $all ? ( $page - 1 ) * $per : 0 );
		return tch_template( 'my-orders', array( 'orders' => $orders, 'total' => $total, 'all' => $all, 'page' => $page, 'per' => $per ) );
	}

	/**
	 * [tch_track_order].
	 *
	 * @return string
	 */
	public static function track_order() {
		self::enqueue();
		$ref   = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$token = isset( $_GET['k'] ) ? sanitize_text_field( wp_unslash( $_GET['k'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $ref ) {
			$order = self::authorised_order( $ref, $token );
			if ( $order ) {
				return self::render_order( $order, tch_page_url( 'track' ) );
			}
		}
		return tch_template( 'track-form' );
	}

	/**
	 * Order detail view (shared by My Orders and Track Order).
	 *
	 * @param object $order Order (already authorised).
	 * @param string $back  Back link.
	 * @return string
	 */
	public static function render_order( $order, $back ) {
		return tch_template(
			'order-view',
			array(
				'order'    => $order,
				'items'    => TCH_Orders::items( (int) $order->id ),
				'custom'   => TCH_Orders::custom_request( (int) $order->id ),
				'history'  => TCH_Orders::history( (int) $order->id ),
				'payments' => TCH_Payments::for_order( (int) $order->id ),
				'back'     => $back,
				'token'    => is_user_logged_in() && (int) $order->user_id === get_current_user_id() ? '' : self::guest_token( $order ),
			)
		);
	}

	/**
	 * [tch_contact_form].
	 *
	 * @return string
	 */
	public static function contact_form() {
		self::enqueue();
		return tch_template( 'contact-form' );
	}
}
