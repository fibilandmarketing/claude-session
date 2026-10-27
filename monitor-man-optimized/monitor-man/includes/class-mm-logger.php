<?php

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight persistent logger for Monitor Man.
 *
 * The log table is intentionally capped by periodic pruning so diagnostics do
 * not grow without bound on busy stores.
 */
final class MM_Logger {
	const MAX_ROWS = 1000;

	public static function info( $context, $message, $data = array() ) {
		return self::log( 'info', $context, $message, $data );
	}

	public static function warning( $context, $message, $data = array() ) {
		return self::log( 'warning', $context, $message, $data );
	}

	public static function error( $context, $message, $data = array() ) {
		return self::log( 'error', $context, $message, $data );
	}

	public static function log( $level, $context, $message, $data = array() ) {
		global $wpdb;

		$tables = MM_Installer::tables();
		$table  = isset( $tables['logs'] ) ? $tables['logs'] : $wpdb->prefix . 'mm_logs';
		if ( ! self::table_exists( $table ) ) {
			return false;
		}

		$allowed_levels = array( 'info', 'warning', 'error' );
		$level          = in_array( $level, $allowed_levels, true ) ? $level : 'info';
		$context        = sanitize_key( $context ?: 'general' );
		$message        = sanitize_text_field( wp_strip_all_tags( (string) $message ) );
		if ( strlen( $message ) > 1800 ) {
			$message = substr( $message, 0, 1800 );
		}

		$payload = '';
		if ( ! empty( $data ) ) {
			$payload = wp_json_encode( self::sanitize_payload( $data ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( strlen( (string) $payload ) > 12000 ) {
				$payload = substr( (string) $payload, 0, 12000 );
			}
		}

		$result = $wpdb->insert(
			$table,
			array(
				'created_at' => current_time( 'mysql' ),
				'level'      => $level,
				'context'    => $context,
				'message'    => $message,
				'details'    => $payload,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false !== $result && 0 === mt_rand( 0, 24 ) ) {
			self::prune();
		}

		return false !== $result;
	}

	public static function recent( $limit = 100, $level = '' ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$table  = isset( $tables['logs'] ) ? $tables['logs'] : $wpdb->prefix . 'mm_logs';
		if ( ! self::table_exists( $table ) ) {
			return array();
		}

		$limit = max( 1, min( 250, absint( $limit ) ) );
		$sql   = "SELECT id, created_at, level, context, message, details FROM {$table}";
		$args  = array();
		if ( in_array( $level, array( 'info', 'warning', 'error' ), true ) ) {
			$sql   .= ' WHERE level=%s';
			$args[] = $level;
		}
		$sql .= ' ORDER BY id DESC LIMIT ' . $limit;
		$rows = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A );

		foreach ( (array) $rows as &$row ) {
			$row['id']      = (int) $row['id'];
			$row['details'] = $row['details'] ? json_decode( $row['details'], true ) : array();
			if ( ! is_array( $row['details'] ) ) {
				$row['details'] = array();
			}
		}
		unset( $row );
		return array_values( (array) $rows );
	}

	public static function clear() {
		global $wpdb;
		$tables = MM_Installer::tables();
		$table  = isset( $tables['logs'] ) ? $tables['logs'] : $wpdb->prefix . 'mm_logs';
		if ( ! self::table_exists( $table ) ) {
			return false;
		}
		$result = $wpdb->query( "TRUNCATE TABLE {$table}" );
		if ( false !== $result ) {
			return true;
		}
		// Some managed hosts deny TRUNCATE while still allowing DELETE.
		return false !== $wpdb->query( "DELETE FROM {$table}" );
	}

	public static function prune() {
		global $wpdb;
		$tables = MM_Installer::tables();
		$table  = isset( $tables['logs'] ) ? $tables['logs'] : $wpdb->prefix . 'mm_logs';
		if ( ! self::table_exists( $table ) ) {
			return;
		}
		$threshold = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET %d",
				self::MAX_ROWS - 1
			)
		);
		if ( $threshold > 0 ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id < %d", $threshold ) );
		}
	}

	private static function sanitize_payload( $value, $depth = 0 ) {
		if ( $depth > 5 ) {
			return '[depth-limit]';
		}
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) {
				$key = is_int( $key ) ? $key : sanitize_key( (string) $key );
				$out[ $key ] = self::sanitize_payload( $item, $depth + 1 );
			}
			return $out;
		}
		if ( is_object( $value ) ) {
			return self::sanitize_payload( get_object_vars( $value ), $depth + 1 );
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}
		$text = wp_strip_all_tags( (string) $value );
		return strlen( $text ) > 2000 ? substr( $text, 0, 2000 ) : $text;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}
}
