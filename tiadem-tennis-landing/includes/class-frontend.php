<?php
/**
 * Page template, assets, isolation and SEO output.
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front-end.
 */
class TDL_Frontend {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'theme_page_templates', array( __CLASS__, 'register_template' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'isolate' ), 9999 );
		add_action( 'wp_print_styles', array( __CLASS__, 'isolate' ), 9999 );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'isolate' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'schema' ), 50 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_shortcode( 'tiadem_tennis_landing', array( __CLASS__, 'shortcode' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_state' ), 10, 2 );
	}

	/**
	 * Register the page template in the editor dropdown.
	 *
	 * @param array $templates Templates.
	 * @return array
	 */
	public static function register_template( $templates ) {
		$templates[ TDL_TEMPLATE ] = 'لندینگ تنیسی تیادم';
		return $templates;
	}

	/**
	 * Is the current request the landing page.
	 *
	 * @return bool
	 */
	public static function is_landing() {
		static $result = null;
		if ( null !== $result ) {
			return $result;
		}
		if ( ! is_singular( 'page' ) ) {
			return false;
		}
		$id     = get_queried_object_id();
		$result = ( TDL_TEMPLATE === get_page_template_slug( $id ) );
		return $result;
	}

	/**
	 * Does the current singular content contain our shortcode.
	 *
	 * @return bool
	 */
	public static function has_shortcode_page() {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post( get_queried_object_id() );
		return $post && has_shortcode( $post->post_content, 'tiadem_tennis_landing' );
	}

	/**
	 * Use theme header/footer.
	 *
	 * @return bool
	 */
	public static function theme_layout() {
		return (bool) tdl_opt( 'advanced', 'theme_layout', 0 );
	}

	/**
	 * Swap in our template.
	 *
	 * @param string $template Template.
	 * @return string
	 */
	public static function template_include( $template ) {
		if ( self::is_landing() ) {
			return TDL_DIR . 'templates/landing.php';
		}
		return $template;
	}

	/**
	 * Mark the landing page in the pages list.
	 *
	 * @param array   $states States.
	 * @param WP_Post $post   Post.
	 * @return array
	 */
	public static function post_state( $states, $post ) {
		if ( TDL_TEMPLATE === get_page_template_slug( $post ) ) {
			$states['tdl'] = 'لندینگ تنیسی';
		}
		return $states;
	}

	/**
	 * Body classes.
	 *
	 * @param array $classes Classes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		if ( self::is_landing() ) {
			$classes[] = 'tdl-body';
			if ( ! tdl_opt( 'advanced', 'animations', 1 ) ) {
				$classes[] = 'tdl-no-anim';
			}
		}
		return $classes;
	}

	/**
	 * Enqueue assets only where needed.
	 */
	public static function enqueue() {
		if ( ! self::is_landing() && ! self::has_shortcode_page() ) {
			return;
		}
		$css_ver = TDL_VERSION . '.' . filemtime( TDL_DIR . 'assets/css/landing.css' );
		$js_ver  = TDL_VERSION . '.' . filemtime( TDL_DIR . 'assets/js/landing.js' );
		wp_enqueue_style( 'tdl-landing', TDL_URL . 'assets/css/landing.css', array(), $css_ver );

		$g    = tdl_opt( 'general' );
		$vars = sprintf(
			':root{--vt-ink:%1$s;--vt-accent:%2$s;--vt-btn-2:%3$s}',
			sanitize_hex_color( $g['color_ink'] ) ? $g['color_ink'] : '#142033',
			sanitize_hex_color( $g['color_accent'] ) ? $g['color_accent'] : '#2F5F8A',
			sanitize_hex_color( $g['color_btn'] ) ? $g['color_btn'] : '#172A44'
		);
		$vars .= self::font_faces();
		$custom = (string) tdl_opt( 'advanced', 'custom_css', '' );
		if ( '' !== trim( $custom ) ) {
			$vars .= "\n" . str_replace( '</', '<\/', $custom );
		}
		wp_add_inline_style( 'tdl-landing', $vars );

		$args = array( 'in_footer' => true );
		if ( version_compare( get_bloginfo( 'version' ), '6.3', '>=' ) ) {
			$args['strategy'] = 'defer';
		}
		wp_enqueue_script( 'tdl-landing', TDL_URL . 'assets/js/landing.js', array(), $js_ver, $args );

		$festival = tdl_opt( 'festival' );
		$config   = array(
			'ajax'      => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'cartUrl'   => wc_get_cart_url(),
			'now'       => time() * 1000,
			'festEnd'   => (int) $festival['end_ts'] * 1000,
			'hideFest'  => (int) $festival['hide_expired'],
			'wish'      => TDL_Wishlist::provider(),
			'anim'      => (int) tdl_opt( 'advanced', 'animations', 1 ),
			'currency'  => tdl_currency(),
			'i18n'      => array(
				'add'       => 'افزودن به سبد',
				'added'     => 'به سبد اضافه شد',
				'choose'    => 'انتخاب گزینه‌ها',
				'out'       => 'ناموجود',
				'viewCart'  => 'مشاهده سبد',
				'error'     => 'خطایی رخ داد؛ دوباره تلاش کنید.',
				'pick'      => 'لطفاً همه گزینه‌ها را انتخاب کنید.',
				'unavail'   => 'این ترکیب موجود نیست.',
				'wishOn'    => 'به علاقه‌مندی‌ها اضافه شد',
				'wishOff'   => 'از علاقه‌مندی‌ها حذف شد',
				'loading'   => 'در حال بارگذاری…',
				'details'   => 'مشاهده جزئیات محصول',
			),
		);
		wp_add_inline_script( 'tdl-landing', 'window.TDL=' . wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';', 'before' );
	}

	/**
	 * Self-hosted @font-face rules (absolute URLs).
	 *
	 * @return string
	 */
	private static function font_faces() {
		$f = TDL_URL . 'assets/fonts/';
		return '@font-face{font-family:"Vazirmatn";src:url(' . $f . 'vazirmatn-var.woff2) format("woff2");font-weight:100 900;font-style:normal;font-display:swap}'
			. '@font-face{font-family:"TDL Head";src:url(' . $f . 'alibaba-var.woff2) format("woff2");font-weight:100 900;font-style:normal;font-display:swap}'
			. '@font-face{font-family:"TDL Serif";src:url(' . $f . 'cormorant-500i.woff2) format("woff2");font-weight:500;font-style:italic;font-display:swap;unicode-range:U+0000-00FF}'
			. '.vt-root{--tdl-bg-texture:url(' . TDL_URL . 'assets/img/bg-texture.webp);--tdl-sparkle:url(' . TDL_URL . 'assets/img/sparkle.png)}';
	}

	/**
	 * Remove theme / block / WooCommerce default assets on the landing page.
	 */
	public static function isolate() {
		if ( ! self::is_landing() || self::theme_layout() ) {
			return;
		}
		$level = tdl_opt( 'advanced', 'isolation', 'theme' );
		if ( 'none' === $level ) {
			return;
		}

		$keep = array( 'tdl-landing', 'admin-bar', 'dashicons', 'query-monitor', 'wp-hooks', 'wp-i18n' );
		$drop = array( 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles', 'core-block-supports', 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-blocktheme', 'wc-blocks-style', 'wc-blocks-vendors-style', 'woocommerce-inline', 'wp-emoji-styles', 'brands-styles', 'wc-add-to-cart', 'woocommerce', 'wc-cart-fragments', 'wc-jquery-blockui', 'jquery-blockui', 'wc-js-cookie', 'js-cookie', 'wc-single-product', 'wc-add-to-cart-variation', 'wc-blocks-checkout', 'wp-block-navigation' );
		$extra = preg_split( '/\s+/', (string) tdl_opt( 'advanced', 'extra_dequeue', '' ) );
		$drop  = array_merge( $drop, array_filter( array_map( 'sanitize_key', $extra ) ) );

		$theme_urls = array_unique( array( get_template_directory_uri(), get_stylesheet_directory_uri() ) );

		foreach ( array( wp_styles(), wp_scripts() ) as $deps ) {
			foreach ( (array) $deps->queue as $handle ) {
				if ( in_array( $handle, $keep, true ) ) {
					continue;
				}
				$src    = isset( $deps->registered[ $handle ] ) ? (string) $deps->registered[ $handle ]->src : '';
				$remove = in_array( $handle, $drop, true );
				foreach ( $theme_urls as $url ) {
					if ( $src && 0 === strpos( $src, $url ) ) {
						$remove = true;
					}
				}
				if ( 'strict' === $level && false !== strpos( $src, '/plugins/' ) && false === strpos( $src, '/tiadem-tennis-landing/' ) ) {
					$remove = true;
				}
				if ( $remove ) {
					if ( $deps instanceof WP_Styles ) {
						wp_dequeue_style( $handle );
					} else {
						wp_dequeue_script( $handle );
					}
				}
			}
		}

		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
		remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
		remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_footer', 'wp_enqueue_stored_styles', 1 );
	}

	/**
	 * Head output: JS flag, font preload, meta description fallback.
	 */
	public static function head() {
		if ( ! self::is_landing() && ! self::has_shortcode_page() ) {
			return;
		}
		echo "<script>document.documentElement.classList.add('tdl-js');</script>\n";
		echo '<link rel="preload" href="' . esc_url( TDL_URL . 'assets/fonts/vazirmatn-var.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		echo '<link rel="preload" href="' . esc_url( TDL_URL . 'assets/fonts/alibaba-var.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		echo '<meta name="theme-color" content="#EAF2F8">' . "\n";

		$seo_plugin = defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
		$desc       = trim( (string) tdl_opt( 'general', 'meta_description', '' ) );
		if ( self::is_landing() && ! $seo_plugin && '' !== $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
	}

	/**
	 * JSON-LD: FAQPage and ItemList.
	 */
	public static function schema() {
		if ( ! self::is_landing() && ! self::has_shortcode_page() ) {
			return;
		}
		$graph = array();
		$faq   = tdl_opt( 'faq' );
		if ( ! empty( $faq['enabled'] ) && ! empty( $faq['schema'] ) && ! empty( $faq['items'] ) ) {
			$entities = array();
			foreach ( $faq['items'] as $item ) {
				if ( empty( $item['q'] ) || empty( $item['a'] ) || preg_match( '/\[[^\]]{3,}\]/u', $item['a'] ) ) {
					continue; // Skip unanswered placeholder questions.
				}
				$entities[] = array(
					'@type'          => 'Question',
					'name'           => wp_strip_all_tags( $item['q'] ),
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => wp_strip_all_tags( $item['a'] ),
					),
				);
			}
			if ( $entities ) {
				$graph[] = array(
					'@type'      => 'FAQPage',
					'mainEntity' => $entities,
				);
			}
		}
		if ( tdl_opt( 'advanced', 'schema_itemlist', 1 ) && TDL_Render::$listed ) {
			$items = array();
			$pos   = 1;
			foreach ( array_slice( TDL_Render::$listed, 0, 30, true ) as $url ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'url'      => $url,
				);
			}
			$graph[] = array(
				'@type'           => 'ItemList',
				'name'            => wp_strip_all_tags( (string) tdl_opt( 'hero', 'title', '' ) ),
				'itemListElement' => $items,
			);
		}
		if ( ! $graph ) {
			return;
		}
		echo '<script type="application/ld+json">' . wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		) . '</script>' . "\n";
	}

	/**
	 * Shortcode — content sections only.
	 *
	 * @return string
	 */
	public static function shortcode() {
		return TDL_Render::embedded();
	}
}
