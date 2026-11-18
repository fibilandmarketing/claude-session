<?php
/**
 * WooCommerce product queries, caching and card data.
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Products.
 */
class TDL_Products {

	const VER_OPTION = 'tdl_cache_ver';

	/**
	 * Hooks that invalidate the cache.
	 */
	public static function init() {
		$bump = array( __CLASS__, 'bump_cache' );
		foreach ( array( 'woocommerce_update_product', 'woocommerce_new_product', 'woocommerce_delete_product', 'woocommerce_trash_product', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status', 'woocommerce_scheduled_sales', 'woocommerce_update_product_variation', 'edited_product_cat', 'created_product_cat', 'delete_product_cat' ) as $hook ) {
			add_action( $hook, $bump );
		}
		add_action(
			'transition_post_status',
			static function ( $new, $old, $post ) {
				if ( $post instanceof WP_Post && 'product' === $post->post_type && $new !== $old ) {
					TDL_Products::bump_cache();
				}
			},
			10,
			3
		);
		add_action(
			'wp_update_comment_count',
			static function ( $post_id ) {
				if ( 'product' === get_post_type( $post_id ) ) {
					TDL_Products::bump_cache();
				}
			}
		);
	}

	/**
	 * Invalidate cached queries.
	 */
	public static function bump_cache() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		update_option( self::VER_OPTION, (string) microtime( true ), true );
	}

	/**
	 * Run a callback through a transient cache.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Producer.
	 * @return mixed
	 */
	public static function cached( $key, $callback ) {
		$hours = (int) tdl_opt( 'advanced', 'cache_hours', 12 );
		if ( $hours <= 0 ) {
			return call_user_func( $callback );
		}
		$tkey = 'tdl_' . md5( get_option( self::VER_OPTION, '1' ) . '|' . $key );
		$hit  = get_transient( $tkey );
		if ( false !== $hit ) {
			return $hit;
		}
		$value = call_user_func( $callback );
		set_transient( $tkey, $value, $hours * HOUR_IN_SECONDS );
		return $value;
	}

	/**
	 * Term id plus all descendants.
	 *
	 * @param int $term_id Term.
	 * @return int[]
	 */
	public static function term_family( $term_id ) {
		$term_id = (int) $term_id;
		if ( ! $term_id ) {
			return array();
		}
		$children = get_term_children( $term_id, 'product_cat' );
		$ids      = is_wp_error( $children ) ? array() : array_map( 'intval', $children );
		array_unshift( $ids, $term_id );
		return $ids;
	}

	/**
	 * Build a WP_Query args array for product IDs.
	 *
	 * @param array $q filter (all|sale|eco|master|cat:ID), page, per_page, orderby, include.
	 * @return array
	 */
	private static function query_args( $q ) {
		$q = wp_parse_args(
			$q,
			array(
				'filter'   => 'all',
				'page'     => 1,
				'per_page' => 12,
				'orderby'  => 'menu_order',
				'include'  => array(),
				'scope'    => true,
			)
		);

		$tax = array( 'relation' => 'AND' );

		// Visibility: hide products excluded from the catalog (and out of stock when configured).
		$exclude = array();
		$vis     = wc_get_product_visibility_term_ids();
		if ( ! empty( $vis['exclude-from-catalog'] ) ) {
			$exclude[] = (int) $vis['exclude-from-catalog'];
		}
		if ( tdl_opt( 'woo', 'hide_out_of_stock', 1 ) && ! empty( $vis['outofstock'] ) ) {
			$exclude[] = (int) $vis['outofstock'];
		}
		if ( $exclude ) {
			$tax[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => $exclude,
				'operator' => 'NOT IN',
			);
		}

		$scope = (array) tdl_opt( 'woo', 'scope_cats', array() );
		if ( $q['scope'] && $scope ) {
			$tax[] = array(
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => array_map( 'intval', $scope ),
				'include_children' => true,
			);
		}

		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'fields'              => 'ids',
			'posts_per_page'      => max( 1, (int) $q['per_page'] ),
			'paged'               => max( 1, (int) $q['page'] ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);

		$filter = (string) $q['filter'];
		if ( 'eco' === $filter || 'master' === $filter ) {
			$term = (int) tdl_opt( 'woo', 'eco' === $filter ? 'eco_cat' : 'master_cat', 0 );
			if ( $term ) {
				$tax[] = array(
					'taxonomy'         => 'product_cat',
					'field'            => 'term_id',
					'terms'            => array( $term ),
					'include_children' => true,
				);
			}
		} elseif ( 0 === strpos( $filter, 'cat:' ) ) {
			$tax[] = array(
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => array( (int) substr( $filter, 4 ) ),
				'include_children' => true,
			);
		} elseif ( 'sale' === $filter ) {
			$on_sale          = wc_get_product_ids_on_sale();
			$args['post__in'] = $on_sale ? array_map( 'intval', $on_sale ) : array( 0 );
		}

		if ( ! empty( $q['include'] ) ) {
			$args['post__in'] = array_map( 'intval', (array) $q['include'] );
			$args['orderby']  = 'post__in';
		}

		if ( count( $tax ) > 1 ) {
			$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		if ( empty( $args['orderby'] ) ) {
			switch ( $q['orderby'] ) {
				case 'popularity':
					$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery
					$args['orderby']  = array( 'meta_value_num' => 'DESC', 'date' => 'DESC' );
					break;
				case 'rating':
					$args['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery
					$args['orderby']  = array( 'meta_value_num' => 'DESC', 'date' => 'DESC' );
					break;
				case 'price':
					$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery
					$args['orderby']  = array( 'meta_value_num' => 'ASC' );
					break;
				case 'price-desc':
					$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery
					$args['orderby']  = array( 'meta_value_num' => 'DESC' );
					break;
				case 'date':
					$args['orderby'] = array( 'date' => 'DESC' );
					break;
				case 'rand':
					$args['orderby'] = 'rand';
					break;
				default:
					$args['orderby'] = array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					);
			}
		}
		return $args;
	}

	/**
	 * Query product IDs (cached).
	 *
	 * @param array $q Query.
	 * @return array ids, total, pages
	 */
	public static function ids( $q ) {
		$args = self::query_args( $q );
		$run  = static function () use ( $args ) {
			$query = new WP_Query( $args );
			return array(
				'ids'   => array_map( 'intval', $query->posts ),
				'total' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
			);
		};
		if ( 'rand' === ( is_string( $args['orderby'] ) ? $args['orderby'] : '' ) ) {
			return $run();
		}
		return self::cached( 'ids|' . wp_json_encode( $args ), $run );
	}

	/**
	 * Products for the economy/master rows.
	 *
	 * @param string $section economy|master.
	 * @return WC_Product[]
	 */
	public static function row( $section ) {
		$cfg = tdl_opt( $section );
		if ( 'manual' === $cfg['source'] && ! empty( $cfg['products'] ) ) {
			$res = self::ids(
				array(
					'include'  => $cfg['products'],
					'per_page' => (int) $cfg['limit'],
					'scope'    => false,
				)
			);
		} else {
			$res = self::ids(
				array(
					'filter'   => 'economy' === $section ? 'eco' : 'master',
					'per_page' => (int) $cfg['limit'],
					'orderby'  => $cfg['orderby'],
				)
			);
			// No category mapped: show nothing rather than a misleading mix.
			if ( ! tdl_opt( 'woo', 'economy' === $section ? 'eco_cat' : 'master_cat', 0 ) ) {
				return array();
			}
		}
		return self::load( $res['ids'] );
	}

	/**
	 * Festival products, highest discount first.
	 *
	 * @return WC_Product[]
	 */
	public static function festival() {
		$cfg   = tdl_opt( 'festival' );
		$limit = max( 1, (int) $cfg['limit'] );
		if ( 'manual' === $cfg['source'] && ! empty( $cfg['products'] ) ) {
			$res = self::ids(
				array(
					'include'  => $cfg['products'],
					'per_page' => $limit,
					'scope'    => false,
				)
			);
			return self::load( $res['ids'] );
		}
		$res      = self::ids(
			array(
				'filter'   => 'sale',
				'per_page' => min( 60, $limit * 3 ),
				'orderby'  => 'popularity',
			)
		);
		$products = self::load( $res['ids'] );
		usort(
			$products,
			static function ( $a, $b ) {
				return self::discount( $b ) <=> self::discount( $a );
			}
		);
		return array_slice( $products, 0, $limit );
	}

	/**
	 * Load products by IDs preserving order, skipping invalid ones.
	 *
	 * @param int[] $ids IDs.
	 * @return WC_Product[]
	 */
	public static function load( $ids ) {
		$out = array();
		foreach ( (array) $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product && $product->is_visible() ) {
				$out[] = $product;
			}
		}
		return $out;
	}

	/**
	 * Regular & active price for display (min for variable products).
	 *
	 * @param WC_Product $product Product.
	 * @return array [price, regular, is_range]
	 */
	public static function prices( $product ) {
		if ( $product->is_type( 'variable' ) ) {
			$min   = (float) $product->get_variation_price( 'min', true );
			$max   = (float) $product->get_variation_price( 'max', true );
			$reg   = (float) $product->get_variation_regular_price( 'min', true );
			$range = $min !== $max;
			return array( $min, $product->is_on_sale() ? max( $reg, $min ) : $min, $range );
		}
		if ( $product->is_type( 'grouped' ) ) {
			$children = array_filter( array_map( 'wc_get_product', $product->get_children() ) );
			$prices   = array();
			foreach ( $children as $child ) {
				$prices[] = (float) wc_get_price_to_display( $child );
			}
			$min = $prices ? min( $prices ) : 0;
			return array( $min, $min, count( array_unique( $prices ) ) > 1 );
		}
		$price   = (float) wc_get_price_to_display( $product );
		$regular = (float) wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
		return array( $price, $product->is_on_sale() ? max( $regular, $price ) : $price, false );
	}

	/**
	 * Discount percentage.
	 *
	 * @param WC_Product $product Product.
	 * @return int
	 */
	public static function discount( $product ) {
		if ( ! $product->is_on_sale() ) {
			return 0;
		}
		if ( $product->is_type( 'variable' ) ) {
			$best   = 0;
			$prices = $product->get_variation_prices( true );
			foreach ( $prices['regular_price'] as $vid => $reg ) {
				$sale = isset( $prices['price'][ $vid ] ) ? $prices['price'][ $vid ] : $reg;
				if ( $reg > 0 && $sale < $reg ) {
					$best = max( $best, (int) round( ( 1 - $sale / $reg ) * 100 ) );
				}
			}
			return $best;
		}
		list( $price, $regular ) = self::prices( $product );
		return ( $regular > 0 && $price < $regular ) ? (int) round( ( 1 - $price / $regular ) * 100 ) : 0;
	}

	/**
	 * Which quality a product belongs to.
	 *
	 * @param WC_Product $product Product.
	 * @return string eco|master|''
	 */
	public static function quality( $product ) {
		$terms = wc_get_product_term_ids( $product->get_id(), 'product_cat' );
		if ( array_intersect( $terms, self::term_family( tdl_opt( 'woo', 'master_cat', 0 ) ) ) ) {
			return 'master';
		}
		if ( array_intersect( $terms, self::term_family( tdl_opt( 'woo', 'eco_cat', 0 ) ) ) ) {
			return 'eco';
		}
		return '';
	}

	/**
	 * Clasp label for a product.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function clasp( $product ) {
		$woo = tdl_opt( 'woo' );
		if ( 'category' === $woo['clasp_mode'] ) {
			$terms = wc_get_product_term_ids( $product->get_id(), 'product_cat' );
			if ( $woo['clasp_cuban_cat'] && array_intersect( $terms, self::term_family( $woo['clasp_cuban_cat'] ) ) ) {
				return $woo['clasp_cuban_label'];
			}
			if ( $woo['clasp_book_cat'] && array_intersect( $terms, self::term_family( $woo['clasp_book_cat'] ) ) ) {
				return $woo['clasp_book_label'];
			}
		} elseif ( 'attribute' === $woo['clasp_mode'] && $woo['clasp_attr'] ) {
			$value = $product->get_attribute( $woo['clasp_attr'] );
			if ( $value ) {
				$first = trim( explode( ',', $value )[0] );
				return $woo['clasp_prefix'] . $first;
			}
		}
		return '';
	}

	/**
	 * All data needed to render a product card.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function card( $product ) {
		$woo                            = tdl_opt( 'woo' );
		list( $price, $regular, $range ) = self::prices( $product );
		$quality                        = self::quality( $product );
		$q_label                        = 'master' === $quality ? $woo['master_label'] : ( 'eco' === $quality ? $woo['eco_label'] : '' );
		$clasp                          = self::clasp( $product );
		$off                            = self::discount( $product );

		$stock_qty = $product->managing_stock() ? (int) $product->get_stock_quantity() : null;
		if ( null === $stock_qty && $product->is_type( 'variable' ) ) {
			$sum = 0;
			$has = false;
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child && $child->managing_stock() ) {
					$has  = true;
					$sum += max( 0, (int) $child->get_stock_quantity() );
				}
			}
			$stock_qty = $has ? $sum : null;
		}
		$in_stock = $product->is_in_stock();
		$low      = (int) $woo['low_stock'];
		if ( ! $in_stock ) {
			$stock_text = 'ناموجود';
		} elseif ( null !== $stock_qty && $stock_qty > 0 ) {
			$stock_text = ( $stock_qty <= $low ) ? 'فقط ' . tdl_fa( $stock_qty ) . ' عدد باقی‌مانده' : tdl_fa( $stock_qty ) . ' عدد در انبار';
		} else {
			$stock_text = 'موجود';
		}
		$sales = (int) $product->get_total_sales();
		$sold  = ( null !== $stock_qty && ( $sales + $stock_qty ) > 0 ) ? (int) round( $sales / ( $sales + $stock_qty ) * 100 ) : 50;

		$sale_end = 0;
		if ( $product->is_on_sale() ) {
			$to = $product->get_date_on_sale_to();
			if ( $to ) {
				$sale_end = $to->getTimestamp();
			} elseif ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $child_id ) {
					$child = wc_get_product( $child_id );
					if ( $child && $child->is_on_sale() && $child->get_date_on_sale_to() ) {
						$ts       = $child->get_date_on_sale_to()->getTimestamp();
						$sale_end = $sale_end ? min( $sale_end, $ts ) : $ts;
					}
				}
			}
		}

		$type = $product->get_type();
		if ( ! $in_stock || ! $product->is_purchasable() ) {
			$mode = $product->is_type( 'external' ) ? 'link' : ( $in_stock ? 'link' : 'none' );
		} elseif ( $product->is_type( 'simple' ) ) {
			$mode = 'ajax';
		} elseif ( $product->is_type( 'variable' ) ) {
			$mode = 'popup' === $woo['variable_mode'] ? 'popup' : 'link';
		} else {
			$mode = 'link';
		}

		$rating = (float) $product->get_average_rating();

		return array(
			'id'         => $product->get_id(),
			'name'       => $product->get_name(),
			'url'        => $product->get_permalink(),
			'image_id'   => (int) $product->get_image_id(),
			'quality'    => $quality,
			'q_label'    => $q_label,
			'clasp'      => $clasp,
			'tag'        => $clasp ? $clasp : $q_label,
			'price'      => tdl_money( $price ),
			'price_raw'  => $price,
			'from'       => $range ? 'از ' : '',
			'old'        => ( $regular > $price ) ? tdl_money( $regular ) : '',
			'off'        => $off,
			'off_text'   => $off ? tdl_fa( $off ) . '٪' : '',
			'stock_text' => $stock_text,
			'in_stock'   => $in_stock,
			'sold'       => max( 6, min( 100, $sold ) ),
			'rating'     => $rating > 0 ? tdl_fa( number_format( $rating, 1 ) ) : '',
			'sale_end'   => $sale_end,
			'type'       => $type,
			'mode'       => $mode,
		);
	}

	/**
	 * Review statistics for the scope products.
	 *
	 * @return array avg (float), count (int)
	 */
	public static function review_stats() {
		return self::cached(
			'review_stats|' . wp_json_encode( tdl_opt( 'woo', 'scope_cats', array() ) ),
			static function () {
				$res   = self::ids(
					array(
						'per_page' => 2000,
						'orderby'  => 'date',
					)
				);
				$sum   = 0.0;
				$count = 0;
				foreach ( $res['ids'] as $id ) {
					$c = (int) get_post_meta( $id, '_wc_review_count', true );
					if ( $c > 0 ) {
						$count += $c;
						$sum   += $c * (float) get_post_meta( $id, '_wc_average_rating', true );
					}
				}
				return array(
					'avg'   => $count ? round( $sum / $count, 1 ) : 0,
					'count' => $count,
					'ids'   => array_slice( $res['ids'], 0, 500 ),
				);
			}
		);
	}

	/**
	 * Approved reviews for the scope products.
	 *
	 * @param int $min   Minimum rating.
	 * @param int $limit Count.
	 * @return array[] text, name, product
	 */
	public static function reviews( $min, $limit ) {
		$stats = self::review_stats();
		if ( empty( $stats['count'] ) ) {
			return array();
		}
		return self::cached(
			'reviews|' . $min . '|' . $limit,
			static function () use ( $stats, $min, $limit ) {
				$comments = get_comments(
					array(
						'post__in'   => $stats['ids'],
						'post_type'  => 'product',
						'status'     => 'approve',
						'type'       => 'review',
						'number'     => (int) $limit,
						'orderby'    => 'comment_date_gmt',
						'order'      => 'DESC',
						'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
							array(
								'key'     => 'rating',
								'value'   => (int) $min,
								'compare' => '>=',
								'type'    => 'NUMERIC',
							),
						),
					)
				);
				$out      = array();
				foreach ( $comments as $c ) {
					$text = trim( wp_strip_all_tags( $c->comment_content ) );
					if ( '' === $text ) {
						continue;
					}
					$out[] = array(
						'text'    => wp_html_excerpt( $text, 180, '…' ),
						'name'    => $c->comment_author,
						'product' => get_the_title( $c->comment_post_ID ),
						'rating'  => (int) get_comment_meta( $c->comment_ID, 'rating', true ),
					);
				}
				return $out;
			}
		);
	}

	/**
	 * Lowest price among scope products (for the mobile bar).
	 *
	 * @return string formatted or ''
	 */
	public static function min_price_text() {
		$res = self::ids(
			array(
				'per_page' => 1,
				'orderby'  => 'price',
			)
		);
		if ( empty( $res['ids'] ) ) {
			return '';
		}
		$product = wc_get_product( $res['ids'][0] );
		if ( ! $product ) {
			return '';
		}
		list( $price ) = self::prices( $product );
		return $price > 0 ? tdl_money( $price ) . ' ' . tdl_currency() : '';
	}
}
