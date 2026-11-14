<?php
/**
 * Front end: renders the landing as a standalone page on the chosen WordPress page.
 */

defined( 'ABSPATH' ) || exit;

class YL_Frontend {

	private static $is = null;

	/**
	 * Opt-out markers honoured by common optimisers (Cloudflare/Arvan Rocket Loader, LiteSpeed Cache,
	 * WP Rocket "delay JS", Autoptimize). The landing's own small scripts must run as written:
	 * if they are delayed, combined or rewritten, the form, price modal and link shield stop working.
	 */
	const NO_OPT = ' data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-pagespeed-no-defer nowprocket';

	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'isolate' ), 9999 );
		add_action( 'wp_head', array( __CLASS__, 'shield' ), PHP_INT_MIN );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'prepare' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'script_tag' ), 99, 2 );
		add_action( 'wp_ajax_yl_fal', array( __CLASS__, 'ajax_fal' ) );
		add_action( 'wp_ajax_nopriv_yl_fal', array( __CLASS__, 'ajax_fal' ) );
	}

	public static function is_landing() {
		if ( null === self::$is ) {
			$id       = (int) YL_Settings::get( 'general', 'page_id', 0 );
			self::$is = $id > 0 && is_page( $id );
		}
		return self::$is;
	}

	public static function prepare() {
		if ( ! self::is_landing() ) {
			return;
		}
		if ( YL_Settings::get( 'general', 'isolate', 1 ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
			remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
			remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
		}
	}

	/**
	 * One random Hafez fortune. The page carries only a handful inline (as a fallback), so the
	 * full list (80+) never weighs down the HTML; the canary's 4-second animation hides the wait.
	 * "not" = index shown last time, so the same fortune doesn't come twice in a row.
	 */
	public static function ajax_fal() {
		nocache_headers();
		$items = array_values( array_filter( (array) YL_Settings::get( 'fal', 'items', array() ), static function ( $f ) {
			return is_array( $f ) && ! empty( $f['b1'] );
		} ) );
		$n = count( $items );
		if ( ! $n || ! YL_Settings::on( 'fal' ) ) {
			wp_send_json( array( 'ok' => false ) );
		}
		$not = isset( $_GET['not'] ) ? (int) $_GET['not'] : -1; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $n > 1 && $not >= 0 && $not < $n ) {
			$i = wp_rand( 0, $n - 2 );
			if ( $i >= $not ) {
				$i++;
			}
		} else {
			$i = wp_rand( 0, $n - 1 );
		}
		$f = $items[ $i ];
		wp_send_json( array(
			'ok' => true,
			'i'  => $i,
			'b1' => (string) $f['b1'],
			'b2' => isset( $f['b2'] ) ? (string) $f['b2'] : '',
			't'  => isset( $f['t'] ) ? (string) $f['t'] : '',
		) );
	}

	public static function script_tag( $tag, $handle ) {
		if ( 'yl-landing' !== $handle || false !== strpos( $tag, 'nowprocket' ) ) {
			return $tag;
		}
		return preg_replace( '/<script\b/', '<script' . self::NO_OPT, $tag, 1 );
	}

	public static function template( $template ) {
		return self::is_landing() ? YL_DIR . 'templates/landing.php' : $template;
	}

	public static function assets() {
		if ( ! self::is_landing() ) {
			return;
		}
		$v = YL_VERSION . '.' . filemtime( YL_DIR . 'assets/js/landing.js' );
		wp_enqueue_style( 'yl-landing', YL_URL . 'assets/css/landing.css', array(), YL_VERSION . '.' . filemtime( YL_DIR . 'assets/css/landing.css' ) );
		wp_enqueue_script( 'yl-landing', YL_URL . 'assets/js/landing.js', array(), $v, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/** Drop theme / WooCommerce / block-library assets on the landing so it stays light and unaffected. */
	public static function isolate() {
		if ( ! self::is_landing() || ! YL_Settings::get( 'general', 'isolate', 1 ) ) {
			return;
		}
		$theme_urls = array_unique( array( get_template_directory_uri(), get_stylesheet_directory_uri() ) );
		$known      = array(
			'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'core-block-supports',
			'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-inline',
			'wc-cart-fragments', 'woocommerce', 'wc-add-to-cart', 'wc-order-attribution', 'sourcebuster-js', 'wc-single-product',
		);
		foreach ( array( wp_styles(), wp_scripts() ) as $deps ) {
			foreach ( (array) $deps->queue as $h ) {
				if ( 0 === strpos( $h, 'yl-' ) || 'admin-bar' === $h ) {
					continue;
				}
				$src  = isset( $deps->registered[ $h ] ) ? (string) $deps->registered[ $h ]->src : '';
				$drop = in_array( $h, $known, true ) || 0 === strpos( $h, 'wc-blocks' ) || 0 === strpos( $h, 'wp-block-' );
				foreach ( $theme_urls as $u ) {
					if ( $src && 0 === strpos( $src, $u ) ) {
						$drop = true;
					}
				}
				if ( $drop ) {
					if ( $deps instanceof WP_Styles ) {
						wp_dequeue_style( $h );
					} else {
						wp_dequeue_script( $h );
					}
				}
			}
		}
	}

	public static function head() {
		if ( ! self::is_landing() ) {
			return;
		}
		$f = YL_URL . 'assets/fonts/';
		echo '<link rel="preload" href="' . esc_url( $f . 'estedad-500.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		echo '<link rel="preload" href="' . esc_url( $f . 'estedad-700.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		$hero = YL_Images::get( self::img_value( 'hero', 'title_img' ), array( 720, 1200 ) );
		if ( $hero ) {
			echo '<link rel="preload" as="image" href="' . esc_url( $hero['src'] ) . '"'
				. ( $hero['srcset'] ? ' imagesrcset="' . esc_attr( $hero['srcset'] ) . '" imagesizes="(max-width:760px) 92vw, 570px"' : '' )
				. ' fetchpriority="high">' . "\n";
		}
	}

	/**
	 * Link shield, printed first in <head> so its listeners run before any theme or plugin script.
	 *
	 * Themes and plugins often catch every link click on the site (AJAX/PJAX page loading,
	 * preloaders, page transitions). On this standalone template they cannot find the theme
	 * wrapper, so they stay stuck in their "loading" state and the page looks frozen until a
	 * refresh. For links inside the landing we stop the click from reaching those scripts
	 * (the browser still follows the link itself) and keep their beforeunload loaders quiet.
	 * In-page "#section" links are scrolled here, so theme smooth-scroll code does not fight ours.
	 */
	public static function shield() {
		if ( ! self::is_landing() || ! YL_Settings::get( 'general', 'shield', 1 ) ) {
			return;
		}
		echo '<script id="yl-shield"' . self::NO_OPT . '>' . self::shield_js() . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function shield_js() {
		return <<<'JS'
(function(){var nav=0;
window.addEventListener('click',function(e){var t=e.target,a=t&&t.closest&&t.closest('a[href]');if(!a)return;var r=document.getElementById('yl');if(!r||!r.contains(a))return;var h=a.getAttribute('href')||'';
if(h.charAt(0)==='#'){var el=h.length>1&&document.getElementById(h.slice(1));if(!el)return;e.preventDefault();e.stopImmediatePropagation();var rm=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;el.scrollIntoView({behavior:rm?'auto':'smooth',block:'start'});if(history.replaceState)history.replaceState(null,'',h);return;}
e.stopImmediatePropagation();nav=Date.now();},true);
window.addEventListener('beforeunload',function(e){if(Date.now()-nav<1500)e.stopImmediatePropagation();},true);
})();
JS;
	}

	/* ------------------------------------------------------------------ view helpers */

	/** Image value of a field, falling back to the bundled default. */
	public static function img_value( $section, $field ) {
		$v = YL_Settings::get( $section, $field, '' );
		if ( '' === $v || 0 === $v || '0' === $v || null === $v ) {
			$v = YL_Defaults::image( $section, $field );
		}
		return $v;
	}

	public static function img( $section, $field, $widths, $attr = array() ) {
		return YL_Images::tag( YL_Images::get( self::img_value( $section, $field ), $widths ), $attr );
	}

	/** Section heading: knitted-text image, or plain text if no image. */
	public static function heading( $section, $id ) {
		$alt = (string) YL_Settings::get( $section, 'title_alt', '' );
		$img = YL_Images::get( self::img_value( $section, 'title_img' ), array( 480, 840 ) );
		echo '<h2 id="' . esc_attr( $id ) . '">';
		if ( $img ) {
			$h     = max( 1, (int) $img['h'] );
			$w1x   = (int) round( 100 * $img['w'] / $h );
			$wm    = (int) round( 58 * $img['w'] / $h );
			$sizes = '(max-width:760px) ' . $wm . 'px, ' . $w1x . 'px';
			echo YL_Images::tag( $img, array( 'alt' => $alt, 'sizes' => $sizes ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo '<span class="yl-h2t">' . esc_html( $alt ) . '</span>';
		}
		echo '</h2>';
	}

	/** Yellow note pill under a heading. */
	public static function note( $text, $wrap = true ) {
		if ( '' === trim( (string) $text ) ) {
			return;
		}
		$pill = '<span class="yl-smp">' . esc_html( $text ) . '</span>';
		echo $wrap ? '<div>' . $pill . '</div>' : $pill; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** Floating knitted ornaments (positions from the design). */
	public static function deco( $where ) {
		if ( ! YL_Settings::get( 'general', 'deco', 1 ) ) {
			return;
		}
		$m = array( 'moon', '', 21 );
		$c = array( 'cloud', 'cld', 24 );
		$s = array( 'star', 'tw', 30 );
		$set = array(
			'hero'   => array(
				array( $m, 'hm', 'top:14%;left:3%', 132 ),
				array( $c, 'hm', 'top:74%;left:2%', 112 ),
				array( $c, 'hm', 'top:66%;right:2%', 104 ),
				array( $s, 's1', 'top:15%;right:3%', 54 ),
				array( $s, 's2 hm', 'top:44%;right:1.5%', 38 ),
				array( $s, 's3 hm', 'top:92%;right:24%', 34 ),
				array( $s, 's4 hm', 'top:46%;left:5%', 42 ),
			),
			'yaldaf' => array( array( $c, 'hm', 'top:96px;right:4%', 104 ), array( $s, 's5 hm', 'top:250px;left:5%', 36 ) ),
			'build'  => array( array( $m, 'hm', 'top:84px;left:4.5%', 112 ), array( $s, 's6 hm', 'top:160px;right:6%', 40 ) ),
			'recent' => array( array( $c, 'hm', 'top:110px;left:3.5%', 100 ), array( $s, 's7 hm', 'top:210px;right:5%', 32 ) ),
			'proof'  => array( array( $s, 's8 hm', 'top:120px;right:7%', 40 ) ),
			'fal'    => array( array( $c, 'hm', 'top:120px;right:4%', 96 ) ),
			'faq'    => array( array( $m, 'hm', 'top:100px;right:4.5%', 104 ), array( $s, 's9 hm', 'top:260px;left:5%', 38 ) ),
			'end'    => array(
				array( $s, 's10 hm', 'top:24%;left:22%', 38 ),
				array( $c, 'hm', 'top:56%;left:6%', 104 ),
				array( $m, 'hm', 'top:18%;right:8%', 110 ),
			),
		);
		if ( empty( $set[ $where ] ) ) {
			return;
		}
		foreach ( $set[ $where ] as $d ) {
			list( $kind, $cls, $pos, $w ) = $d;
			$img = YL_Images::get( 'b:' . $kind[0] );
			if ( ! $img ) {
				continue;
			}
			$classes = trim( 'yl-fl ' . $kind[1] . ' ' . $cls );
			echo '<div class="' . esc_attr( $classes ) . '" style="' . esc_attr( $pos . ';--w:' . $w . 'px;--p:' . $kind[2] . 'px' ) . '" aria-hidden="true">'
				. YL_Images::tag( $img, array( 'alt' => '' ) ) // phpcs:ignore WordPress.Security.EscapeOutput
				. '</div>';
		}
	}

	/** Navigation entries for enabled sections. */
	public static function nav() {
		$out = array();
		foreach ( (array) YL_Settings::get( 'general', 'order', array() ) as $key ) {
			if ( ! YL_Settings::on( $key ) ) {
				continue;
			}
			$label = (string) YL_Settings::get( $key, 'nav', '' );
			if ( '' !== trim( $label ) ) {
				$out[ $key ] = $label;
			}
		}
		return $out;
	}

	/** Days left until the target (for server-side first paint). */
	public static function days_left() {
		return max( 0, (int) floor( ( YL_Settings::target_ts() - time() ) / DAY_IN_SECONDS ) );
	}
}
