<?php
/**
 * Business settings stored in one option, editable by the owner.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
class TCH_Settings {

	/**
	 * Cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'tch_info', array( __CLASS__, 'shortcode' ) );
		add_action( 'update_option_tch_settings', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Verified defaults supplied by the client. Unknown values are left empty on purpose.
	 *
	 * @return array<string,string>
	 */
	public static function defaults() {
		return array(
			'business_name'       => "Tina's Cake Haven",
			'owner'               => 'Aluka Christin Hellen',
			'tagline'             => 'Sweet Moments, Always!',
			'location'            => 'Kibimba, Fort Portal, Uganda',
			'established'         => '10 February 2026',
			'phone'               => '+256 792104798',
			'whatsapp'            => '256792104798',
			'public_email'        => '',
			'notify_email'        => '',
			'momo_instructions'   => '',
			'delivery_note'       => 'Delivery is generally available within about 1 km of our premises in Kibimba. Customers further away usually collect their orders, unless another arrangement is agreed with us.',
			'custom_notice_hours' => '24',
			'facebook_url'        => '',
			'instagram_url'       => '',
			'tiktok_url'          => '',
			'map_url'             => 'https://www.google.com/maps/search/?api=1&query=Kibimba%2C+Fort+Portal%2C+Uganda',
		);
	}

	/**
	 * Field labels for the settings screen.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function fields() {
		return array(
			'business_name'       => array( 'Business name', 'text' ),
			'owner'               => array( 'Owner', 'text' ),
			'tagline'             => array( 'Tagline', 'text' ),
			'location'            => array( 'Location', 'text' ),
			'established'         => array( 'Established', 'text' ),
			'phone'               => array( 'Phone (shown on the website)', 'text' ),
			'whatsapp'            => array( 'WhatsApp number (international digits, e.g. 256792104798)', 'text' ),
			'public_email'        => array( 'Public email address (e.g. info@yourdomain)', 'email' ),
			'notify_email'        => array( 'Send new order alerts to (e.g. orders@yourdomain)', 'email' ),
			'momo_instructions'   => array( 'Mobile Money payment instructions shown to customers (number / merchant code and registered name)', 'textarea' ),
			'delivery_note'       => array( 'Delivery note', 'textarea' ),
			'custom_notice_hours' => array( 'Minimum notice for customized cakes (hours)', 'number' ),
			'facebook_url'        => array( 'Facebook page URL', 'url' ),
			'instagram_url'       => array( 'Instagram URL', 'url' ),
			'tiktok_url'          => array( 'TikTok URL', 'url' ),
			'map_url'             => array( 'Map link (Google Maps)', 'url' ),
		);
	}

	/**
	 * Reads a setting.
	 *
	 * @param string $key     Key.
	 * @param string $default Default.
	 * @return string
	 */
	public static function get( $key, $default = '' ) {
		if ( null === self::$cache ) {
			self::$cache = wp_parse_args( (array) get_option( 'tch_settings', array() ), self::defaults() );
		}
		return isset( self::$cache[ $key ] ) && '' !== self::$cache[ $key ] ? (string) self::$cache[ $key ] : (string) $default;
	}

	/**
	 * Clears the cache.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Sanitises the posted settings form.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$out = array();
		foreach ( self::fields() as $key => $def ) {
			$val = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '';
			switch ( $def[1] ) {
				case 'email':
					$out[ $key ] = sanitize_email( $val );
					break;
				case 'url':
					$out[ $key ] = esc_url_raw( $val, array( 'http', 'https' ) );
					break;
				case 'number':
					$out[ $key ] = (string) absint( $val );
					break;
				case 'textarea':
					$out[ $key ] = sanitize_textarea_field( $val );
					break;
				default:
					$out[ $key ] = sanitize_text_field( $val );
			}
		}
		$out['whatsapp']            = preg_replace( '/\D+/', '', $out['whatsapp'] );
		$out['custom_notice_hours'] = (string) min( 720, max( 1, (int) $out['custom_notice_hours'] ) );
		return $out;
	}

	/**
	 * [tch_info key="phone" link="1"] prints a business detail so page text stays in sync.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'key' => 'phone', 'link' => '0' ), $atts, 'tch_info' );
		$key  = sanitize_key( $atts['key'] );
		if ( ! array_key_exists( $key, self::defaults() ) ) {
			return '';
		}
		$val = self::get( $key );
		if ( '1' === $atts['link'] ) {
			if ( 'phone' === $key ) {
				return '<a href="' . esc_url( tch_tel_link() ) . '">' . esc_html( $val ) . '</a>';
			}
			if ( 'whatsapp' === $key ) {
				return '<a href="' . esc_url( tch_whatsapp_link() ) . '" rel="noopener" target="_blank">' . esc_html( self::get( 'phone' ) ) . '</a>';
			}
			if ( 'public_email' === $key && $val ) {
				return '<a href="mailto:' . esc_attr( antispambot( $val ) ) . '">' . esc_html( antispambot( $val ) ) . '</a>';
			}
		}
		return esc_html( $val );
	}
}
