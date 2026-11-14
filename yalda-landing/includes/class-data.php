<?php
/**
 * Turns settings rows (WooCommerce product or manual entry) into ready-to-render items.
 * All prices returned here are in Toman.
 */

defined( 'ABSPATH' ) || exit;

class YL_Data {

	const CARD_WIDTHS = array( 300, 600 );

	private static $memo = array();

	/** Round a Toman amount to the configured step (default 1000). */
	public static function round_price( $n ) {
		$r = (int) YL_Settings::get( 'general', 'round', 1000 );
		return $r > 0 ? round( $n / $r ) * $r : round( $n );
	}

	/**
	 * Normalize one row.
	 *
	 * @return array|null [ key, pid, name, img, url, price, regular, sale, add ]
	 */
	public static function item( $row, $widths = self::CARD_WIDTHS ) {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$src = isset( $row['src'] ) ? $row['src'] : 'manual';

		if ( 'woo' === $src ) {
			if ( ! yl_woo() || empty( $row['pid'] ) ) {
				return null;
			}
			$p = wc_get_product( absint( $row['pid'] ) );
			if ( ! $p || 'publish' !== $p->get_status() ) {
				return null;
			}
			$f     = yl_toman_factor();
			$price = (float) $p->get_price() * $f;
			$reg   = (float) $p->get_regular_price() * $f;
			if ( $p->is_type( 'variable' ) ) {
				// A variable product has no single regular price; use its cheapest variation.
				$price = (float) $p->get_variation_price( 'min', true ) * $f;
				$reg   = (float) $p->get_variation_regular_price( 'min', true ) * $f;
			}
			if ( $reg <= 0 ) {
				$reg = $price;
			}
			$img = YL_Images::get( $p->get_image_id(), $widths );
			if ( ! $img && ! empty( $row['img'] ) ) {
				$img = YL_Images::get( $row['img'], $widths );
			}
			$name = ! empty( $row['name'] ) ? $row['name'] : $p->get_name();
			return array(
				'key'     => 'w' . $p->get_id(),
				'pid'     => $p->get_id(),
				'name'    => wp_strip_all_tags( $name ),
				'img'     => $img,
				'url'     => get_permalink( $p->get_id() ),
				'price'   => $price,
				'regular' => $reg,
				'sale'    => $p->is_on_sale(),
				'add'     => $p->is_type( 'simple' ) && $p->is_purchasable() && $p->is_in_stock(),
			);
		}

		if ( empty( $row['name'] ) ) {
			return null;
		}
		$price = (float) ( isset( $row['price'] ) ? $row['price'] : 0 );
		return array(
			'key'     => 'm' . substr( md5( $row['name'] . ( isset( $row['url'] ) ? $row['url'] : '' ) ), 0, 8 ),
			'pid'     => 0,
			'name'    => $row['name'],
			'img'     => YL_Images::get( isset( $row['img'] ) ? $row['img'] : '', $widths ),
			'url'     => isset( $row['url'] ) ? $row['url'] : '',
			'price'   => $price,
			'regular' => $price,
			'sale'    => false,
			'add'     => false,
		);
	}

	/** Discounted products for the "Yalda off" rail. */
	public static function deals() {
		if ( isset( self::$memo['deals'] ) ) {
			return self::$memo['deals'];
		}
		$s    = YL_Settings::section( 'yaldaf' );
		$rows = array();
		if ( 'woo' === $s['source'] && yl_woo() ) {
			$scan  = self::woo_on_sale( (int) $s['woo_cat'] );
			$limit = max( 1, (int) $s['woo_limit'] );
			foreach ( $scan['ids'] as $id ) {
				$p = wc_get_product( $id );
				if ( ! $p || 'publish' !== $p->get_status() ) {
					continue; // deleted/unpublished since the list was cached: the next product takes its place
				}
				$rows[] = array( 'src' => 'woo', 'pid' => $id, 'pct' => 0 );
				if ( count( $rows ) >= $limit ) {
					break;
				}
			}
		} else {
			$rows = is_array( $s['items'] ) ? $s['items'] : array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$it = self::item( $row );
			if ( ! $it ) {
				continue;
			}
			$pct  = isset( $row['pct'] ) ? (float) $row['pct'] : 0;
			$deal = 0;
			if ( $pct > 0 ) {
				$was  = $it['regular'];
				$now  = self::round_price( $was * ( 1 - $pct / 100 ) );
				$deal = $pct;
			} elseif ( $it['sale'] && $it['regular'] > $it['price'] ) {
				$was = $it['regular'];
				$now = $it['price'];
				$pct = round( ( 1 - $now / $was ) * 100 );
			} else {
				$was = 0;
				$now = $it['price'];
			}
			$it['was']  = $was;
			$it['now']  = $now;
			$it['pct']  = $pct;
			$it['deal'] = $deal;
			$out[]      = $it;
		}
		return self::$memo['deals'] = $out;
	}

	const SALE_CACHE = 'yl_onsale_scan';

	/**
	 * Published products that are on sale right now, newest first, optionally limited to one
	 * category (sub-categories included).
	 *
	 * Each product is asked directly (is_on_sale), instead of relying only on WooCommerce's
	 * on-sale ID list: that list misses discounts set by discount/price plugins. The result is
	 * cached for 10 minutes and dropped whenever a product is saved.
	 *
	 * @return array{ids: int[], in_cat: int, cat: string}
	 */
	public static function woo_on_sale( $cat_id ) {
		$key   = (int) $cat_id;
		$cache = get_transient( self::SALE_CACHE );
		if ( is_array( $cache ) && isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$args = array(
			'status'  => 'publish',
			'limit'   => 300,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'ids',
		);
		$cat = '';
		if ( $cat_id ) {
			$term = get_term( $cat_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$args['category'] = array( $term->slug );
				$cat              = $term->name;
			}
		}
		$ids = wc_get_products( $args );
		if ( ! $cat_id ) {
			// No category: also include older on-sale products beyond the newest 300.
			$ids = array_values( array_unique( array_merge( $ids, array_map( 'intval', wc_get_product_ids_on_sale() ) ) ) );
		}
		$sale = array();
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p || 'publish' !== $p->get_status() || $p->is_type( 'variation' ) ) {
				continue;
			}
			if ( $p->is_on_sale() ) {
				$sale[ $id ] = $p->get_date_created() ? $p->get_date_created()->getTimestamp() : 0;
			}
		}
		arsort( $sale );
		$out = array(
			'ids'    => array_map( 'intval', array_keys( $sale ) ),
			'in_cat' => count( $ids ),
			'cat'    => $cat,
		);
		$cache         = is_array( $cache ) ? $cache : array();
		$cache[ $key ] = $out;
		set_transient( self::SALE_CACHE, $cache, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	/** Plain-language status line for the settings page: why the rail shows the number it shows. */
	public static function woo_report() {
		if ( ! yl_woo() ) {
			return 'ووکامرس فعال نیست.';
		}
		$s    = YL_Settings::section( 'yaldaf' );
		$scan = self::woo_on_sale( (int) $s['woo_cat'] );
		$n    = count( $scan['ids'] );
		$lim  = max( 1, (int) $s['woo_limit'] );
		$where = '' !== $scan['cat'] ? 'دسته‌ی «' . $scan['cat'] . '» (با زیردسته‌ها)' : 'کل فروشگاه';
		$msg   = 'وضعیت فعلی: ' . $where . ' ' . yl_fa( $scan['in_cat'] ) . ' محصول منتشرشده دارد که ' . yl_fa( $n ) . ' تای آن الان تخفیف دارند؛ ' . yl_fa( min( $n, $lim ) ) . ' محصول در لندینگ نمایش داده می‌شود.';
		if ( $n > $lim ) {
			$msg .= ' سقف «حداکثر تعداد» ' . yl_fa( $lim ) . ' است؛ برای نمایش همه آن را بالا ببرید.';
		} elseif ( $n < 5 ) {
			$msg .= ' برای نمایش بیشتر، به محصولات این دسته «قیمت فروش ویژه» بدهید یا دسته‌ی دیگری انتخاب کنید.';
		}
		return $msg . ' (این عدد هر ۱۰ دقیقه یا با ذخیره‌ی هر محصول به‌روز می‌شود.)';
	}

	public static function flush_sale_cache() {
		delete_transient( self::SALE_CACHE );
	}

	/** Any product publish/unpublish/trash/delete changes what is on sale. */
	public static function flush_on_post( $post_id ) {
		if ( 'product' === get_post_type( $post_id ) ) {
			self::flush_sale_cache();
		}
	}

	public static function flush_on_status( $new, $old, $post ) {
		if ( $new !== $old && $post && 'product' === $post->post_type ) {
			self::flush_sale_cache();
		}
	}

	/** Landing-only discount (percent) configured for a WooCommerce product in the deals list. */
	public static function deal_pct_for( $pid ) {
		$s = YL_Settings::section( 'yaldaf' );
		if ( 'woo' === $s['source'] || empty( $s['items'] ) ) {
			return 0;
		}
		foreach ( (array) $s['items'] as $row ) {
			if ( isset( $row['src'], $row['pid'], $row['pct'] ) && 'woo' === $row['src'] && (int) $row['pid'] === (int) $pid && (float) $row['pct'] > 0 ) {
				return min( 95, (float) $row['pct'] );
			}
		}
		return 0;
	}

	/** Pack catalog used by the 5-step builder and the "change pack" list. */
	public static function catalog() {
		if ( isset( self::$memo['catalog'] ) ) {
			return self::$memo['catalog'];
		}
		$out = array();
		foreach ( (array) YL_Settings::get( 'catalog', 'items', array() ) as $row ) {
			$it = self::item( $row );
			if ( ! $it ) {
				continue;
			}
			$it['tags'] = isset( $row['tags'] ) ? array_values( (array) $row['tags'] ) : array();
			$out[]      = $it;
		}
		return self::$memo['catalog'] = $out;
	}

	/** "Recently built packs". */
	public static function recent() {
		if ( isset( self::$memo['recent'] ) ) {
			return self::$memo['recent'];
		}
		$out = array();
		foreach ( (array) YL_Settings::get( 'recent', 'items', array() ) as $row ) {
			$it = self::item( $row, array( 200 ) );
			if ( ! $it ) {
				continue;
			}
			$it['who']    = isset( $row['who'] ) ? $row['who'] : '';
			$it['people'] = max( 1, (int) ( isset( $row['people'] ) ? $row['people'] : 1 ) );
			$it['cta']    = ! empty( $row['cta'] ) ? $row['cta'] : 'همین پک را سفارش دهید';
			$out[]        = $it;
		}
		return self::$memo['recent'] = $out;
	}

	/** Compact shape for the front-end script. */
	public static function js_item( $it, $price = null ) {
		return array(
			'name' => $it['name'],
			'img'  => $it['img'] ? $it['img']['src'] : '',
			'url'  => $it['url'],
			'price' => (float) ( null === $price ? $it['price'] : $price ),
			'pid'  => (int) $it['pid'],
			'add'  => (bool) $it['add'],
		);
	}
}
