<?php
/**
 * Settings storage: one non-autoloaded option, merged over the defaults.
 */

defined( 'ABSPATH' ) || exit;

class YL_Settings {

	private static $cache = null;

	/** Full settings (saved values over defaults). */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( YL_OPTION, array() );
			self::$cache = self::merge( YL_Defaults::get(), is_array( $saved ) ? $saved : array() );
		}
		return self::$cache;
	}

	/** One section. */
	public static function section( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : array();
	}

	public static function get( $section, $field, $fallback = null ) {
		$s = self::section( $section );
		return array_key_exists( $field, $s ) ? $s[ $field ] : $fallback;
	}

	public static function save( array $data ) {
		update_option( YL_OPTION, $data, false );
		self::$cache = null;
	}

	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Associative arrays merge key by key; lists (repeaters) are replaced as a whole.
	 */
	public static function merge( $def, $saved ) {
		if ( ! is_array( $def ) || ! is_array( $saved ) ) {
			return $saved;
		}
		if ( self::is_list( $def ) || self::is_list( $saved ) ) {
			return $saved;
		}
		$out = $def;
		foreach ( $saved as $k => $v ) {
			$out[ $k ] = array_key_exists( $k, $def ) ? self::merge( $def[ $k ], $v ) : $v;
		}
		return $out;
	}

	private static function is_list( $a ) {
		if ( array() === $a ) {
			return false;
		}
		return array_keys( $a ) === range( 0, count( $a ) - 1 );
	}

	/** Is a section switched on? Sections without an "on" key are always on. */
	public static function on( $section ) {
		$s = self::section( $section );
		return ! isset( $s['on'] ) || ! empty( $s['on'] );
	}

	/** Countdown target as unix timestamp. */
	public static function target_ts() {
		$t = self::get( 'general', 'target', array() );
		$t = wp_parse_args( is_array( $t ) ? $t : array(), array( 'y' => 1405, 'm' => 9, 'd' => 30, 't' => '17:00' ) );
		return yl_jalali_ts( $t['y'], $t['m'], $t['d'], $t['t'] );
	}
}
