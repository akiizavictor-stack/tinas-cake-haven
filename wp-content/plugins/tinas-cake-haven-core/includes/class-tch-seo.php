<?php
/**
 * Local-business structured data and small SEO helpers.
 * Titles, meta descriptions and the XML sitemap are handled by the SEO plugin
 * (Yoast SEO) or, without it, by WordPress core sitemaps.
 *
 * @package TinasCakeHaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO.
 */
class TCH_SEO {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'schema' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'fallback_description' ), 2 );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'no_user_sitemap' ), 10, 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'noindex_private' ) );
	}

	/**
	 * Bakery schema on the home page; Product schema on product pages.
	 */
	public static function schema() {
		if ( is_front_page() ) {
			$data = array(
				'@context'     => 'https://schema.org',
				'@type'        => 'Bakery',
				'name'         => tch_setting( 'business_name' ),
				'slogan'       => tch_setting( 'tagline' ),
				'url'          => home_url( '/' ),
				'telephone'    => tch_setting( 'phone' ),
				'founder'      => array( '@type' => 'Person', 'name' => tch_setting( 'owner' ) ),
				'foundingDate' => '2026-02-10',
				'address'      => array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => 'Kibimba',
					'addressLocality' => 'Fort Portal',
					'addressCountry'  => 'UG',
				),
				'priceRange'   => 'UGX 1,000 – UGX 100,000',
				'currenciesAccepted' => 'UGX',
				'paymentAccepted'    => 'Mobile Money',
			);
			$logo = get_theme_file_uri( 'assets/images/logo-512.webp' );
			if ( $logo ) {
				$data['logo']  = $logo;
				$data['image'] = $logo;
			}
			$same = array_values( array_filter( array( tch_setting( 'facebook_url' ), tch_setting( 'instagram_url' ), tch_setting( 'tiktok_url' ) ) ) );
			if ( $same ) {
				$data['sameAs'] = $same;
			}
			self::print_json( $data );
		} elseif ( is_singular( TCH_Catalogue::PT ) ) {
			$cfg = TCH_Catalogue::config( get_the_ID() );
			if ( ! $cfg ) {
				return;
			}
			$prices = array();
			if ( 'sized' === $cfg['type'] ) {
				$prices = wp_list_pluck( TCH_Catalogue::sizes_for( $cfg ), 'price' );
			} else {
				$prices = array( $cfg['unit_price'] );
			}
			if ( ! $prices ) {
				return;
			}
			$data = array(
				'@context'    => 'https://schema.org',
				'@type'       => 'Product',
				'name'        => $cfg['name'],
				'description' => wp_strip_all_tags( get_the_excerpt() ),
				'brand'       => array( '@type' => 'Brand', 'name' => tch_setting( 'business_name' ) ),
				'offers'      => array(
					'@type'         => 'AggregateOffer',
					'priceCurrency' => 'UGX',
					'lowPrice'      => (int) min( $prices ),
					'highPrice'     => (int) max( $prices ),
					'availability'  => 'https://schema.org/InStock',
					'url'           => get_permalink(),
				),
			);
			if ( has_post_thumbnail() ) {
				$data['image'] = get_the_post_thumbnail_url( null, 'large' );
			}
			self::print_json( $data );
		}
	}

	/**
	 * Prints JSON-LD safely.
	 *
	 * @param array $data Data.
	 */
	private static function print_json( $data ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n";
	}

	/**
	 * Meta description when no SEO plugin is active (uses the excerpt).
	 */
	public static function fallback_description() {
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}
		$desc = '';
		if ( is_front_page() ) {
			$desc = get_bloginfo( 'description' );
		} elseif ( is_singular() && has_excerpt() ) {
			$desc = get_the_excerpt();
		}
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( wp_trim_words( wp_strip_all_tags( $desc ), 30, '' ) ) . '">' . "\n";
		}
	}

	/**
	 * No author/user sitemap (avoids exposing usernames).
	 *
	 * @param WP_Sitemaps_Provider $provider Provider.
	 * @param string               $name     Name.
	 * @return WP_Sitemaps_Provider|false
	 */
	public static function no_user_sitemap( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Private customer pages are not indexed.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function noindex_private( $robots ) {
		$pages = get_option( 'tch_pages', array() );
		foreach ( array( 'my-orders', 'track', 'account' ) as $k ) {
			if ( ! empty( $pages[ $k ] ) && is_page( $pages[ $k ] ) ) {
				$robots['noindex'] = true;
				$robots['follow']  = true;
			}
		}
		return $robots;
	}
}
