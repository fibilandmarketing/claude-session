<?php
/**
 * Activation: landing page, defaults, automatic category mapping.
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class TDL_Installer {

	const PAGE_OPTION = 'tdl_page_id';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'activation_notice' ) );
	}

	/**
	 * Activation hook.
	 */
	public static function activate() {
		$stored = get_option( TDL_OPTION, null );
		if ( ! is_array( $stored ) ) {
			$stored = array(
				'festival' => array( 'end_ts' => time() + 7 * DAY_IN_SECONDS ),
			);
			add_option( TDL_OPTION, $stored, '', false );
		}
		add_option( TDL_Products::VER_OPTION, (string) microtime( true ) );

		self::create_page();

		if ( taxonomy_exists( 'product_cat' ) ) {
			self::auto_map();
		}
		set_transient( 'tdl_activated', 1, 120 );
	}

	/**
	 * Create (or reuse) the landing page.
	 *
	 * @return int Page id.
	 */
	public static function create_page() {
		$id = (int) get_option( self::PAGE_OPTION, 0 );
		if ( $id && get_post( $id ) && 'trash' !== get_post_status( $id ) ) {
			return $id;
		}
		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'meta_key'       => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => TDL_TEMPLATE, // phpcs:ignore WordPress.DB.SlowDBQuery
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( $existing ) {
			update_option( self::PAGE_OPTION, (int) $existing[0], false );
			return (int) $existing[0];
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'خرید بدلیجات تنیسی',
				'post_name'    => 'tennis-jewelry',
				'post_content' => '<!-- محتوای این صفحه از «لندینگ تنیسی» در پیشخوان مدیریت می‌شود. -->',
				'meta_input'   => array( '_wp_page_template' => TDL_TEMPLATE ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		update_option( self::PAGE_OPTION, (int) $id, false );
		return (int) $id;
	}

	/**
	 * Find a product category whose name contains one of the needles.
	 *
	 * @param string[] $needles Needles.
	 * @param int[]    $within  Prefer terms inside these ancestors.
	 * @return int
	 */
	private static function find_term( $needles, $within = array() ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return 0;
		}
		$best       = 0;
		$best_score = -1;
		foreach ( $terms as $term ) {
			$name = mb_strtolower( trim( $term->name ) );
			$slug = mb_strtolower( urldecode( $term->slug ) );
			foreach ( $needles as $needle ) {
				$needle   = mb_strtolower( $needle );
				$anchored = '^' === mb_substr( $needle, 0, 1 );
				if ( $anchored ) {
					$needle = mb_substr( $needle, 1 );
				}
				$latin = (bool) preg_match( '/^[a-z0-9 _-]+$/', $needle );
				if ( $latin ) {
					// Whole-word match on slug/name tokens ("ring" must not match "earring").
					$hit = (bool) preg_match( '/(^|[\s_-])' . preg_quote( $needle, '/' ) . '([\s_-]|s?$)/u', $slug . ' ' . $name );
					if ( $anchored ) {
						$hit = $hit && ( 0 === strpos( $slug, $needle ) || 0 === mb_strpos( $name, $needle ) );
					}
				} elseif ( $anchored ) {
					// Starts with the word, not a longer word ("ست" must not match "ستاره").
					$hit = (bool) preg_match( '/^' . preg_quote( $needle, '/' ) . '(?![\p{L}])/u', $name );
				} else {
					$hit = false !== mb_strpos( $name, $needle );
				}
				if ( ! $hit ) {
					continue;
				}
				$score = 0;
				if ( $name === $needle ) {
					$score += 100;
				}
				if ( $within && array_intersect( get_ancestors( $term->term_id, 'product_cat' ), $within ) ) {
					$score += 50;
				}
				if ( 0 === (int) $term->parent ) {
					$score += 10;
				}
				$score += max( 0, 40 - mb_strlen( $name ) ) / 10;
				$score += min( 5, (int) $term->count ) / 10;
				if ( $score > $best_score ) {
					$best_score = $score;
					$best       = (int) $term->term_id;
				}
			}
		}
		return $best;
	}

	/**
	 * Fill empty category mappings by matching category names.
	 *
	 * @return int Number of mappings filled.
	 */
	public static function auto_map() {
		$s      = TDL_Settings::get();
		$filled = 0;

		if ( empty( $s['woo']['scope_cats'] ) ) {
			$t = self::find_term( array( 'تنیسی', 'tennis' ) );
			if ( $t ) {
				$s['woo']['scope_cats'] = array( $t );
				$filled++;
			}
		}
		$scope = (array) $s['woo']['scope_cats'];

		$map = array(
			'eco_cat'         => array( 'اکونومی', 'economy' ),
			'master_cat'      => array( 'مستر', 'master' ),
			'clasp_book_cat'  => array( 'کتابی', 'book' ),
			'clasp_cuban_cat' => array( 'کوبایی', 'cuban' ),
		);
		foreach ( $map as $key => $needles ) {
			if ( empty( $s['woo'][ $key ] ) ) {
				$t = self::find_term( $needles, $scope );
				if ( $t ) {
					$s['woo'][ $key ] = $t;
					$filled++;
				}
			}
		}

		$kinds = array(
			'دستبند'  => array( 'دستبند', 'bracelet' ),
			'گردنبند' => array( 'گردنبند', 'necklace' ),
			'پابند'   => array( 'پابند', 'anklet' ),
			'انگشتر'  => array( 'انگشتر', 'حلقه', 'ring' ),
			'گوشواره' => array( 'گوشواره', 'earring' ),
			'ست'      => array( '^ست', '^set' ),
		);
		$find_kind = static function ( $label ) use ( $kinds, $scope ) {
			foreach ( $kinds as $kind => $needles ) {
				if ( false !== mb_strpos( $label, $kind ) ) {
					return self::find_term( $needles, $scope );
				}
			}
			return 0;
		};

		foreach ( $s['grid']['chips'] as $i => $chip ) {
			if ( '' === $chip['filter'] ) {
				$t = $find_kind( $chip['label'] );
				if ( $t ) {
					$s['grid']['chips'][ $i ]['filter'] = 'cat:' . $t;
					$filled++;
				}
			}
		}
		foreach ( array( 'cats', 'types' ) as $section ) {
			foreach ( $s[ $section ]['items'] as $i => $item ) {
				if ( empty( $item['term'] ) ) {
					$t = $find_kind( $item['name'] );
					if ( $t ) {
						$s[ $section ]['items'][ $i ]['term'] = $t;
						$filled++;
					}
				}
			}
		}

		if ( $filled ) {
			TDL_Settings::save( $s );
		}
		return $filled;
	}

	/**
	 * One-time notice after activation.
	 */
	public static function activation_notice() {
		if ( ! get_transient( 'tdl_activated' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		delete_transient( 'tdl_activated' );
		$page = (int) get_option( self::PAGE_OPTION, 0 );
		echo '<div class="notice notice-success is-dismissible"><p><strong>لندینگ تنیسی تیادم فعال شد.</strong> ';
		if ( $page ) {
			echo 'صفحه «' . esc_html( get_the_title( $page ) ) . '» ساخته شد: <a href="' . esc_url( get_permalink( $page ) ) . '" target="_blank">مشاهده لندینگ</a> | ';
		}
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=tdl-landing' ) ) . '">مدیریت محتوا و اتصال دسته‌ها</a></p></div>';
	}
}
