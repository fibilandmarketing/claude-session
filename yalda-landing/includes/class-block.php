<?php
/**
 * Keeps other plugins' widgets (chat bubbles, pop-ups, call buttons…) off the landing page only.
 *
 * Two ways to block, both from «تنظیمات کلی ← ابزارک افزونه‌های دیگر»:
 *  1. Tick a plugin: its scripts/styles are dequeued and the code it prints in the page head,
 *     body or footer is unhooked — on the landing only; the rest of the site is untouched.
 *  2. Keywords: any <script>, <style>, <link>, <iframe> or <noscript> whose address or code
 *     contains the word is removed, and any element whose id/class contains it is hidden.
 *     This also catches widgets pasted as code (header/footer code boxes) rather than plugins.
 *     A keyword also blocks a plugin whose folder name contains it.
 */

defined( 'ABSPATH' ) || exit;

class YL_Block {

	const SEEN = 'yl_seen_plugins';

	/** Hooks where plugins print or enqueue front-end widgets. */
	const HOOKS = array( 'wp_head', 'wp_body_open', 'wp_footer', 'wp_print_footer_scripts', 'wp_enqueue_scripts', 'wp_print_scripts', 'wp_print_styles', 'get_footer' );

	private static $on      = false;
	private static $plugins = array();
	private static $words   = array();
	private static $seen    = array();

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'start' ), PHP_INT_MAX );
	}

	/** Folder (or single-file) key of a plugin file such as "afracc/afracc.php". */
	public static function slug_of_file( $file ) {
		$file = str_replace( '\\', '/', (string) $file );
		$slug = false !== strpos( $file, '/' ) ? strstr( $file, '/', true ) : $file;
		return sanitize_key( $slug );
	}

	/** Keywords from the settings: one per line (or comma separated), at least 4 characters. */
	public static function words() {
		$raw = (string) YL_Settings::get( 'general', 'block_words', '' );
		$out = array();
		foreach ( preg_split( '/[\r\n,،]+/u', $raw ) as $w ) {
			$w = strtolower( trim( $w ) );
			if ( strlen( $w ) >= 4 ) {
				$out[] = $w;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function start() {
		if ( ! YL_Frontend::is_landing() ) {
			return;
		}
		self::$on      = true;
		self::$plugins = array_map( 'strval', (array) YL_Settings::get( 'general', 'block_plugins', array() ) );
		self::$words   = self::words();

		self::sweep();
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'sweep' ), PHP_INT_MAX );
		add_action( 'wp_head', array( __CLASS__, 'sweep' ), PHP_INT_MIN + 1 );
		add_action( 'wp_footer', array( __CLASS__, 'sweep' ), PHP_INT_MIN );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'sweep' ), PHP_INT_MIN );
		add_action( 'shutdown', array( __CLASS__, 'remember' ) );
		if ( self::$words ) {
			ob_start( array( __CLASS__, 'filter_html' ) );
		}
	}

	private static function blocked( $slug ) {
		if ( in_array( $slug, self::$plugins, true ) ) {
			return true;
		}
		foreach ( self::$words as $w ) {
			if ( false !== strpos( $slug, sanitize_key( $w ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** Which plugin folder a hooked callback lives in (null for core, theme, mu-plugins, this plugin). */
	private static function owner( $fn ) {
		try {
			if ( is_string( $fn ) && false !== strpos( $fn, '::' ) ) {
				$r = new ReflectionMethod( $fn );
			} elseif ( is_string( $fn ) ) {
				if ( ! function_exists( $fn ) ) {
					return null;
				}
				$r = new ReflectionFunction( $fn );
			} elseif ( $fn instanceof Closure ) {
				$r = new ReflectionFunction( $fn );
			} elseif ( is_array( $fn ) && 2 === count( $fn ) ) {
				$r = new ReflectionMethod( $fn[0], $fn[1] );
			} elseif ( is_object( $fn ) && method_exists( $fn, '__invoke' ) ) {
				$r = new ReflectionMethod( $fn, '__invoke' );
			} else {
				return null;
			}
		} catch ( Exception $e ) {
			return null;
		}
		$file = $r->getFileName();
		return $file ? self::slug_of_path( $file ) : null;
	}

	private static function slug_of_path( $path ) {
		$path = str_replace( '\\', '/', (string) $path );
		$base = rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR ), '/' ) . '/';
		if ( 0 !== strpos( $path, $base ) ) {
			return null;
		}
		$slug = self::slug_of_file( substr( $path, strlen( $base ) ) );
		return self::slug_of_file( plugin_basename( YL_FILE ) ) === $slug ? null : $slug;
	}

	private static function slug_of_src( $src ) {
		$base = wp_parse_url( WP_PLUGIN_URL, PHP_URL_PATH );
		$path = wp_parse_url( (string) $src, PHP_URL_PATH );
		if ( ! $base || ! $path ) {
			return null;
		}
		$base = rtrim( $base, '/' ) . '/';
		$at   = strpos( $path, $base );
		if ( false === $at ) {
			return null;
		}
		$slug = self::slug_of_file( substr( $path, $at + strlen( $base ) ) );
		return self::slug_of_file( plugin_basename( YL_FILE ) ) === $slug ? null : $slug;
	}

	/** Unhook and dequeue everything that belongs to a blocked plugin. Safe to run repeatedly. */
	public static function sweep() {
		if ( ! self::$on ) {
			return;
		}
		global $wp_filter;
		foreach ( self::HOOKS as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $prio => $cbs ) {
				foreach ( $cbs as $cb ) {
					$slug = self::owner( $cb['function'] );
					if ( ! $slug ) {
						continue;
					}
					self::$seen[ $slug ] = 1;
					if ( self::blocked( $slug ) ) {
						remove_action( $hook, $cb['function'], $prio );
					}
				}
			}
		}
		foreach ( array( wp_scripts(), wp_styles() ) as $deps ) {
			foreach ( (array) $deps->queue as $h ) {
				$src  = isset( $deps->registered[ $h ] ) ? (string) $deps->registered[ $h ]->src : '';
				$slug = $src ? self::slug_of_src( $src ) : null;
				if ( $slug ) {
					self::$seen[ $slug ] = 1;
				}
				$hit = $slug && self::blocked( $slug );
				foreach ( self::$words as $w ) {
					if ( false !== stripos( $src, $w ) || false !== stripos( $h, $w ) ) {
						$hit = true;
					}
				}
				if ( $hit ) {
					$deps->dequeue( $h );
				}
			}
		}
	}

	/** Keyword pass over the final HTML. */
	public static function filter_html( $html ) {
		if ( ! self::$words || ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		$words = self::$words;
		$has   = static function ( $text ) use ( $words ) {
			foreach ( $words as $w ) {
				if ( false !== stripos( $text, $w ) ) {
					return true;
				}
			}
			return false;
		};
		// Whole tags that load or run code: drop them.
		$out = preg_replace_callback(
			'#<(script|style|iframe|noscript)\b[^>]*>.*?</\1\s*>|<link\b[^>]*>#is',
			static function ( $m ) use ( $has ) {
				if ( preg_match( '#^<[a-z]+\b[^>]*\bid=["\']yl-#i', $m[0] ) ) {
					return $m[0]; // the landing's own scripts
				}
				return $has( $m[0] ) ? '' : $m[0];
			},
			$html
		);
		if ( null === $out ) {
			return $html; // regex limit hit: never break the page
		}
		// Containers the widget printed (e.g. <div id="afracc-box">): hide them.
		$out2 = preg_replace_callback(
			'#<(div|section|aside|a|span|button|img|iframe|form)\b([^>]*)>#i',
			static function ( $m ) use ( $has ) {
				if ( ! preg_match_all( '#\b(?:id|class)\s*=\s*(["\'])(.*?)\1#is', $m[2], $attrs ) ) {
					return $m[0];
				}
				return $has( implode( ' ', $attrs[2] ) ) ? '<' . $m[1] . ' hidden style="display:none!important"' . $m[2] . '>' : $m[0];
			},
			$out
		);
		return null === $out2 ? $out : $out2;
	}

	/** Remember which plugins put something on the landing, so the settings page can point them out. */
	public static function remember() {
		$seen = array_keys( self::$seen );
		sort( $seen );
		$old = get_option( self::SEEN, array() );
		if ( $seen && $seen !== $old ) {
			update_option( self::SEEN, $seen, false );
		}
	}

	/** Options for the settings checklist: active plugins, the ones seen on the landing first. */
	public static function plugin_options() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all    = get_plugins();
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$seen  = (array) get_option( self::SEEN, array() );
		$self  = self::slug_of_file( plugin_basename( YL_FILE ) );
		$first = array();
		$rest  = array();
		foreach ( array_unique( $active ) as $file ) {
			$slug = self::slug_of_file( $file );
			if ( $slug === $self ) {
				continue;
			}
			$name = isset( $all[ $file ]['Name'] ) ? wp_strip_all_tags( $all[ $file ]['Name'] ) : $file;
			if ( in_array( $slug, $seen, true ) ) {
				$first[ $slug ] = $name . ' ●';
			} else {
				$rest[ $slug ] = $name;
			}
		}
		return $first + $rest;
	}
}
