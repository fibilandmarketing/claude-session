<?php
/**
 * Front-end AJAX endpoints (served through ?wc-ajax= for speed).
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ajax.
 */
class TDL_Ajax {

	/**
	 * Register endpoints.
	 */
	public static function init() {
		add_action( 'wc_ajax_tdl_grid', array( __CLASS__, 'grid' ) );
		add_action( 'wc_ajax_tdl_add', array( __CLASS__, 'add' ) );
		add_action( 'wc_ajax_tdl_qv', array( __CLASS__, 'quick_view' ) );
		add_action( 'wc_ajax_tdl_wish', array( __CLASS__, 'wish' ) );
		add_action( 'wc_ajax_tdl_state', array( __CLASS__, 'state' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'fragments' ) );
	}

	/**
	 * Cart count fragment (keeps theme mini-carts and our header in sync).
	 *
	 * @param array $fragments Fragments.
	 * @return array
	 */
	public static function fragments( $fragments ) {
		$count                            = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
		$fragments['span.tdl-cart-count'] = '<span class="tdl-cart-count" data-count="' . (int) $count . '">' . esc_html( tdl_fa( $count ) ) . '</span>';
		return $fragments;
	}

	/**
	 * Send no-cache headers for personalised responses.
	 */
	private static function private_response() {
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}

	/**
	 * Grid page / filter.
	 */
	public static function grid() {
		$filter = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification
		$page   = isset( $_GET['page'] ) ? max( 1, absint( $_GET['page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $filter, array( 'all', 'sale', 'eco', 'master' ), true ) && ! preg_match( '/^cat:\d+$/', $filter ) ) {
			$filter = 'all';
		}
		$cfg = tdl_opt( 'grid' );
		$res = TDL_Products::ids(
			array(
				'filter'   => $filter,
				'page'     => $page,
				'per_page' => (int) $cfg['per_page'],
				'orderby'  => $cfg['orderby'],
			)
		);
		$html = '';
		foreach ( TDL_Products::load( $res['ids'] ) as $i => $product ) {
			$html .= TDL_Render::card( $product, 'grid', $i );
		}
		wp_send_json_success(
			array(
				'html'     => $html,
				'page'     => $page,
				'has_more' => $page < $res['pages'],
				'total'    => $res['total'],
				'count'    => tdl_fa( $res['total'] ) . ' محصول',
			)
		);
	}

	/**
	 * Add to cart (simple products and variations).
	 */
	public static function add() {
		self::private_response();
		// phpcs:disable WordPress.Security.NonceVerification -- mirrors WooCommerce's own ?wc-ajax=add_to_cart (cache-safe, no nonce).
		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$quantity     = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : 1;
		$raw_attrs    = isset( $_POST['attributes'] ) && is_array( $_POST['attributes'] ) ? wp_unslash( $_POST['attributes'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		// phpcs:enable

		$quantity = max( 1, (int) $quantity );
		$product  = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_visible() ) {
			wp_send_json_error( array( 'message' => 'محصول پیدا نشد.' ), 404 );
		}

		$variation = array();
		if ( $product->is_type( 'variable' ) ) {
			$child = $variation_id ? wc_get_product( $variation_id ) : null;
			if ( ! $child || ! $child->is_type( 'variation' ) || $child->get_parent_id() !== $product->get_id() ) {
				wp_send_json_error( array( 'message' => 'لطفاً گزینه‌های محصول را انتخاب کنید.' ), 400 );
			}
			foreach ( $child->get_variation_attributes() as $name => $value ) {
				$posted = isset( $raw_attrs[ $name ] ) ? wc_clean( $raw_attrs[ $name ] ) : '';
				if ( '' === $value ) {
					if ( '' === $posted ) {
						wp_send_json_error( array( 'message' => 'لطفاً همه گزینه‌ها را انتخاب کنید.' ), 400 );
					}
					$value = $posted;
				}
				$variation[ $name ] = $value;
			}
		} elseif ( ! $product->is_type( 'simple' ) ) {
			wp_send_json_error(
				array(
					'message'  => 'برای این محصول به صفحه محصول بروید.',
					'redirect' => $product->get_permalink(),
				),
				400
			);
		}

		$passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variation );
		$key    = false;
		if ( $passed ) {
			$key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
		}

		if ( ! $key ) {
			$errors = wc_get_notices( 'error' );
			wc_clear_notices();
			$msg = 'افزودن به سبد ممکن نشد.';
			if ( $errors ) {
				$first = reset( $errors );
				$msg   = wp_strip_all_tags( is_array( $first ) ? $first['notice'] : $first );
			}
			wp_send_json_error( array( 'message' => $msg ), 409 );
		}

		wc_clear_notices();
		do_action( 'woocommerce_ajax_added_to_cart', $product_id );

		wp_send_json_success(
			array(
				'count'     => WC()->cart->get_cart_contents_count(),
				'count_fa'  => tdl_fa( WC()->cart->get_cart_contents_count() ),
				'cart_url'  => wc_get_cart_url(),
				'message'   => sprintf( '«%s» به سبد خرید اضافه شد.', $product->get_name() ),
				'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
				'cart_hash' => WC()->cart->get_cart_hash(),
			)
		);
	}

	/**
	 * Quick view data for variable products.
	 */
	public static function quick_view() {
		$id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$product = wc_get_product( $id );
		if ( ! $product || ! $product->is_type( 'variable' ) || ! $product->is_visible() ) {
			wp_send_json_error( array( 'message' => 'محصول پیدا نشد.' ), 404 );
		}

		$attributes = array();
		foreach ( $product->get_variation_attributes() as $attr_name => $options ) {
			$key   = 'attribute_' . sanitize_title( $attr_name );
			$opts  = array();
			$terms = taxonomy_exists( $attr_name ) ? wc_get_product_terms( $product->get_id(), $attr_name, array( 'fields' => 'all' ) ) : array();
			if ( $terms ) {
				foreach ( $terms as $term ) {
					if ( in_array( $term->slug, $options, true ) ) {
						$opts[] = array(
							'value' => $term->slug,
							'label' => $term->name,
						);
					}
				}
			} else {
				foreach ( $options as $option ) {
					$opts[] = array(
						'value' => $option,
						'label' => $option,
					);
				}
			}
			$attributes[] = array(
				'key'     => $key,
				'label'   => wc_attribute_label( $attr_name, $product ),
				'options' => $opts,
			);
		}

		$variations = array();
		foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
			if ( ! $variation->variation_is_visible() ) {
				continue;
			}
			$price   = (float) wc_get_price_to_display( $variation );
			$regular = (float) wc_get_price_to_display( $variation, array( 'price' => $variation->get_regular_price() ) );
			$img     = $variation->get_image_id() ? wp_get_attachment_image_url( $variation->get_image_id(), 'woocommerce_single' ) : '';
			$attrs   = array();
			foreach ( $variation->get_variation_attributes() as $k => $v ) {
				$attrs[ $k ] = $v;
			}
			$qty          = $variation->managing_stock() ? (int) $variation->get_stock_quantity() : null;
			$variations[] = array(
				'id'       => $variation->get_id(),
				'attrs'    => $attrs,
				'price'    => tdl_money( $price ),
				'old'      => $regular > $price ? tdl_money( $regular ) : '',
				'in_stock' => $variation->is_in_stock() && $variation->is_purchasable(),
				'stock'    => null === $qty ? '' : ( $qty <= (int) tdl_opt( 'woo', 'low_stock', 10 ) ? 'فقط ' . tdl_fa( $qty ) . ' عدد باقی‌مانده' : tdl_fa( $qty ) . ' عدد در انبار' ),
				'image'    => $img ? $img : '',
			);
		}

		$card = TDL_Products::card( $product );
		wp_send_json_success(
			array(
				'id'         => $product->get_id(),
				'name'       => $product->get_name(),
				'url'        => $product->get_permalink(),
				'image'      => $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_single' ) : wc_placeholder_img_src( 'woocommerce_single' ),
				'price'      => $card['from'] . $card['price'],
				'old'        => $card['old'],
				'tag'        => $card['tag'],
				'currency'   => tdl_currency(),
				'attributes' => $attributes,
				'variations' => $variations,
			)
		);
	}

	/**
	 * Toggle wishlist.
	 */
	public static function wish() {
		self::private_response();
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( is_user_logged_in() ) {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'tdl_wish' ) ) {
				wp_send_json_error( array( 'message' => 'نشست منقضی شده؛ صفحه را تازه کنید.' ), 403 );
			}
		}
		$state = TDL_Wishlist::toggle( $id );
		wp_send_json_success(
			array(
				'state'    => $state,
				'provider' => TDL_Wishlist::provider(),
			)
		);
	}

	/**
	 * Per-visitor state (cart count, wishlist ids, fresh nonce) — keeps cached pages correct.
	 */
	public static function state() {
		self::private_response();
		wp_send_json_success(
			array(
				'count'    => WC()->cart ? WC()->cart->get_cart_contents_count() : 0,
				'wish'     => TDL_Wishlist::ids(),
				'provider' => TDL_Wishlist::provider(),
				'nonce'    => is_user_logged_in() ? wp_create_nonce( 'tdl_wish' ) : '',
			)
		);
	}
}
