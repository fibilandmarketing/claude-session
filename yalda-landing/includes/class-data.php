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
			$ids = wc_get_product_ids_on_sale();
			if ( $ids ) {
				$args = array(
					'include' => $ids,
					'status'  => 'publish',
					'limit'   => max( 1, (int) $s['woo_limit'] ),
					'orderby' => 'date',
					'order'   => 'DESC',
					'return'  => 'ids',
				);
				if ( ! empty( $s['woo_cat'] ) ) {
					$term = get_term( (int) $s['woo_cat'], 'product_cat' );
					if ( $term && ! is_wp_error( $term ) ) {
						$args['category'] = array( $term->slug );
					}
				}
				foreach ( wc_get_products( $args ) as $id ) {
					$rows[] = array( 'src' => 'woo', 'pid' => $id, 'pct' => 0 );
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
