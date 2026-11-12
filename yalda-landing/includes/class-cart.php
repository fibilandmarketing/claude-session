<?php
/**
 * WooCommerce: "continue and pay online" from the price modal.
 *
 * The landing posts add-to-cart=<id>&quantity=<n>&yl=1 (&yl_deal=1). Prices are always
 * recomputed on the server: landing discount from settings + quantity tier by cart quantity.
 */

defined( 'ABSPATH' ) || exit;

class YL_Cart {

	public static function init() {
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'tag_item' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'redirect' ), 20 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'prices' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_meta' ), 10, 3 );
	}

	private static function from_landing() {
		return ! empty( $_REQUEST['yl'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function tag_item( $data, $product_id ) {
		if ( ! self::from_landing() ) {
			return $data;
		}
		$deal = ! empty( $_REQUEST['yl_deal'] ) ? YL_Data::deal_pct_for( $product_id ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$data['yl'] = array( 'deal' => (float) $deal );
		return $data;
	}

	public static function redirect( $url ) {
		if ( self::from_landing() && function_exists( 'wc_get_checkout_url' ) ) {
			return wc_get_checkout_url();
		}
		return $url;
	}

	/** Quantity tier percent for a quantity. */
	public static function tier( $qty ) {
		$tiers = (array) YL_Settings::get( 'pricing', 'tiers', array() );
		usort( $tiers, static function ( $a, $b ) {
			return (int) $b['q'] - (int) $a['q'];
		} );
		foreach ( $tiers as $t ) {
			if ( $qty >= (int) $t['q'] ) {
				return min( 95, (float) $t['pct'] );
			}
		}
		return 0;
	}

	public static function prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		$use_tiers = (bool) YL_Settings::get( 'pricing', 'cart_tiers', 1 );
		$f         = yl_toman_factor();
		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['yl'] ) || empty( $item['data'] ) ) {
				continue;
			}
			// Fresh product = untouched base price, so repeated calculations never stack.
			$fresh = wc_get_product( ! empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'] );
			if ( ! $fresh ) {
				continue;
			}
			$deal = isset( $item['yl']['deal'] ) ? (float) $item['yl']['deal'] : 0;
			if ( $deal > 0 ) {
				$reg  = (float) $fresh->get_regular_price();
				$unit = YL_Data::round_price( $reg * $f * ( 1 - $deal / 100 ) ) / $f;
			} else {
				$unit = (float) $fresh->get_price();
			}
			$tier = $use_tiers ? self::tier( (int) $item['quantity'] ) : 0;
			if ( $tier > 0 ) {
				$unit = $unit * ( 1 - $tier / 100 );
			}
			$item['data']->set_price( wc_format_decimal( $unit, wc_get_price_decimals() ) );
		}
	}

	public static function item_data( $data, $item ) {
		if ( empty( $item['yl'] ) ) {
			return $data;
		}
		if ( ! empty( $item['yl']['deal'] ) ) {
			$data[] = array( 'name' => 'تخفیف یلدآف', 'value' => yl_fa( (float) $item['yl']['deal'] ) . '٪' );
		}
		$tier = YL_Settings::get( 'pricing', 'cart_tiers', 1 ) ? self::tier( (int) $item['quantity'] ) : 0;
		if ( $tier > 0 ) {
			$data[] = array( 'name' => 'تخفیف تعداد', 'value' => yl_fa( $tier ) . '٪' );
		}
		return $data;
	}

	public static function order_meta( $line, $key, $values ) {
		if ( empty( $values['yl'] ) ) {
			return;
		}
		$line->add_meta_data( 'سفارش از لندینگ یلدا', 'بله' );
		if ( ! empty( $values['yl']['deal'] ) ) {
			$line->add_meta_data( 'تخفیف یلدآف', yl_fa( (float) $values['yl']['deal'] ) . '٪' );
		}
		$tier = YL_Settings::get( 'pricing', 'cart_tiers', 1 ) ? self::tier( (int) $values['quantity'] ) : 0;
		if ( $tier > 0 ) {
			$line->add_meta_data( 'تخفیف تعداد', yl_fa( $tier ) . '٪' );
		}
	}
}
