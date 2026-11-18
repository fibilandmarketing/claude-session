<?php
/**
 * Settings storage and sanitization.
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
class TDL_Settings {

	/**
	 * Runtime cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'update_option_' . TDL_OPTION, array( __CLASS__, 'flush' ) );
	}

	/**
	 * Reset runtime cache.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$defaults = TDL_Defaults::get();
		$stored   = get_option( TDL_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$merged = $defaults;
		foreach ( $stored as $section => $values ) {
			if ( 'order' === $section ) {
				$merged['order'] = self::normalize_order( $values );
				continue;
			}
			if ( ! isset( $defaults[ $section ] ) || ! is_array( $values ) ) {
				continue;
			}
			foreach ( $values as $k => $v ) {
				$merged[ $section ][ $k ] = $v;
			}
		}
		self::$cache = $merged;
		return $merged;
	}

	/**
	 * Save a full settings array (already sanitized).
	 *
	 * @param array $settings Settings.
	 */
	public static function save( $settings ) {
		update_option( TDL_OPTION, $settings, false );
		self::flush();
		TDL_Products::bump_cache();
	}

	/**
	 * Keep only known sections, append missing ones.
	 *
	 * @param mixed $order Order list.
	 * @return array
	 */
	public static function normalize_order( $order ) {
		$known = TDL_Defaults::sortable_sections();
		$out   = array();
		if ( is_array( $order ) ) {
			foreach ( $order as $key ) {
				$key = sanitize_key( $key );
				if ( in_array( $key, $known, true ) && ! in_array( $key, $out, true ) ) {
					$out[] = $key;
				}
			}
		}
		foreach ( $known as $key ) {
			if ( ! in_array( $key, $out, true ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * Sanitize a raw payload (decoded JSON from the admin form) against the schema.
	 *
	 * @param array $raw Raw data keyed by tab.
	 * @return array Sanitized settings (complete).
	 */
	public static function sanitize( $raw ) {
		$defaults = TDL_Defaults::get();
		$current  = self::get();
		$out      = $current;

		foreach ( TDL_Schema::tabs() as $tab => $def ) {
			foreach ( $def['fields'] as $field ) {
				if ( 'order' === $field['type'] ) {
					if ( isset( $raw['order'] ) ) {
						$out['order'] = self::normalize_order( $raw['order'] );
					}
					continue;
				}
				$section = $tab;
				$key     = $field['key'];
				$value   = isset( $raw[ $section ][ $key ] ) ? $raw[ $section ][ $key ] : null;
				$default = isset( $defaults[ $section ][ $key ] ) ? $defaults[ $section ][ $key ] : '';
				if ( null === $value && ! in_array( $field['type'], array( 'toggle', 'terms', 'products', 'repeater' ), true ) ) {
					// Field absent from payload: keep current value.
					continue;
				}
				if ( ! isset( $raw[ $section ] ) ) {
					continue;
				}
				$out[ $section ][ $key ] = self::sanitize_field( $field, $value, $default );
			}
		}
		return $out;
	}

	/**
	 * Sanitize a single value.
	 *
	 * @param array $field   Field definition.
	 * @param mixed $value   Raw value.
	 * @param mixed $default Default value.
	 * @return mixed
	 */
	public static function sanitize_field( $field, $value, $default ) {
		switch ( $field['type'] ) {
			case 'text':
				return sanitize_text_field( (string) $value );

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			case 'html':
				return wp_kses_post( (string) $value );

			case 'code':
				return trim( wp_strip_all_tags( (string) $value ) );

			case 'url':
				$value = trim( (string) $value );
				if ( '' === $value ) {
					return '';
				}
				if ( '#' === $value[0] ) {
					return '#' . preg_replace( '/[^A-Za-z0-9_\-]/', '', substr( $value, 1 ) );
				}
				return esc_url_raw( $value, array( 'http', 'https', 'tel', 'mailto' ) );

			case 'image':
				if ( is_numeric( $value ) ) {
					return absint( $value );
				}
				$value = trim( (string) $value );
				if ( preg_match( '/^asset:[a-z0-9\-]+(\.(png|webp|jpg|gif))?$/', $value ) ) {
					return $value;
				}
				if ( preg_match( '#^https?://#', $value ) ) {
					return esc_url_raw( $value );
				}
				return '';

			case 'color':
				$color = sanitize_hex_color( (string) $value );
				return $color ? $color : $default;

			case 'toggle':
				return ( ! empty( $value ) && '0' !== $value && 'false' !== $value ) ? 1 : 0;

			case 'number':
				$min = isset( $field['min'] ) ? (int) $field['min'] : PHP_INT_MIN;
				$max = isset( $field['max'] ) ? (int) $field['max'] : PHP_INT_MAX;
				return max( $min, min( $max, (int) $value ) );

			case 'select':
				$value = (string) $value;
				return array_key_exists( $value, $field['options'] ) ? $value : $default;

			case 'term':
				return absint( $value );

			case 'terms':
			case 'products':
				if ( ! is_array( $value ) ) {
					$value = ( null === $value || '' === $value ) ? array() : explode( ',', (string) $value );
				}
				return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );

			case 'icon':
				$value = sanitize_key( (string) $value );
				return array_key_exists( $value, tdl_icons() ) ? $value : 'diamond';

			case 'attribute':
				return sanitize_key( (string) $value );

			case 'filter':
				$value = (string) $value;
				if ( in_array( $value, array( 'all', 'sale', 'eco', 'master' ), true ) || preg_match( '/^cat:\d+$/', $value ) ) {
					return $value;
				}
				return '';

			case 'jdatetime':
				if ( is_numeric( $value ) ) {
					return absint( $value );
				}
				if ( ! is_array( $value ) || empty( $value['y'] ) || empty( $value['m'] ) || empty( $value['d'] ) ) {
					return 0;
				}
				$y = (int) $value['y'];
				$m = max( 1, min( 12, (int) $value['m'] ) );
				$d = max( 1, min( 31, (int) $value['d'] ) );
				$h = max( 0, min( 23, isset( $value['h'] ) ? (int) $value['h'] : 0 ) );
				$i = max( 0, min( 59, isset( $value['i'] ) ? (int) $value['i'] : 0 ) );
				if ( $y < 1300 || $y > 1600 ) {
					return 0;
				}
				return TDL_Jalali::to_timestamp( $y, $m, $d, $h, $i );

			case 'repeater':
				$items = array();
				if ( is_array( $value ) ) {
					foreach ( array_slice( array_values( $value ), 0, 60 ) as $item ) {
						if ( ! is_array( $item ) ) {
							continue;
						}
						$clean = array();
						foreach ( $field['fields'] as $sub ) {
							$sub_value             = isset( $item[ $sub['key'] ] ) ? $item[ $sub['key'] ] : '';
							$clean[ $sub['key'] ] = self::sanitize_field( $sub, $sub_value, '' );
						}
						$items[] = $clean;
					}
				}
				return $items;
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sections whose texts still contain [placeholder] markers from the design.
	 *
	 * @return array tab => count
	 */
	public static function placeholders() {
		$found = array();
		foreach ( self::get() as $section => $values ) {
			if ( ! is_array( $values ) || 'order' === $section ) {
				continue;
			}
			$json = wp_json_encode( $values, JSON_UNESCAPED_UNICODE );
			if ( preg_match_all( '/\[[^\]\["]{3,80}\]/u', (string) $json, $m ) ) {
				$found[ $section ] = count( $m[0] );
			}
		}
		return $found;
	}
}
