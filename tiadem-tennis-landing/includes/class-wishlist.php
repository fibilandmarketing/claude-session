<?php
/**
 * Wishlist adapter: YITH WooCommerce Wishlist when available, otherwise user meta.
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wishlist.
 */
class TDL_Wishlist {

	const META = '_tdl_wishlist';

	/**
	 * Is YITH wishlist active.
	 *
	 * @return bool
	 */
	public static function has_yith() {
		return function_exists( 'YITH_WCWL' );
	}

	/**
	 * Provider name exposed to the front-end.
	 *
	 * @return string yith|user|local
	 */
	public static function provider() {
		if ( self::has_yith() ) {
			return 'yith';
		}
		return is_user_logged_in() ? 'user' : 'local';
	}

	/**
	 * Product IDs currently in the wishlist.
	 *
	 * @return int[]
	 */
	public static function ids() {
		if ( self::has_yith() ) {
			try {
				$ids = array();
				if ( function_exists( 'yith_wcwl_get_wishlist_items' ) ) {
					foreach ( (array) yith_wcwl_get_wishlist_items() as $item ) {
						if ( is_object( $item ) && method_exists( $item, 'get_product_id' ) ) {
							$ids[] = (int) $item->get_product_id();
						}
					}
					return $ids;
				}
				if ( method_exists( YITH_WCWL(), 'get_products' ) ) {
					foreach ( (array) YITH_WCWL()->get_products() as $row ) {
						$ids[] = (int) ( is_array( $row ) ? $row['prod_id'] : $row->get_product_id() );
					}
				}
				return $ids;
			} catch ( Throwable $e ) {
				return array();
			}
		}
		if ( is_user_logged_in() ) {
			$ids = get_user_meta( get_current_user_id(), self::META, true );
			return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
		}
		return array();
	}

	/**
	 * Toggle a product.
	 *
	 * @param int $product_id Product.
	 * @return bool|null New state, null when not persisted server-side (guest without YITH).
	 */
	public static function toggle( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return null;
		}
		if ( self::has_yith() ) {
			try {
				$in = in_array( $product_id, self::ids(), true );
				if ( $in ) {
					YITH_WCWL()->remove( array( 'remove_from_wishlist' => $product_id ) );
					return false;
				}
				YITH_WCWL()->add( array( 'add_to_wishlist' => $product_id ) );
				return true;
			} catch ( Throwable $e ) {
				return null;
			}
		}
		if ( is_user_logged_in() ) {
			$ids = self::ids();
			$key = array_search( $product_id, $ids, true );
			if ( false === $key ) {
				$ids[] = $product_id;
				$state = true;
			} else {
				unset( $ids[ $key ] );
				$state = false;
			}
			update_user_meta( get_current_user_id(), self::META, array_values( array_slice( $ids, -200 ) ) );
			return $state;
		}
		return null;
	}
}
