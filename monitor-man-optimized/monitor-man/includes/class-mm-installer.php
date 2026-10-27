<?php

defined( 'ABSPATH' ) || exit;

final class MM_Installer {
	const DB_VERSION = '1.3.0';
	const BOOT_OPTION = 'mm_boot_state';
	const AGG_EVENT   = 'mm_sync_aggregates';

	public static function tables() {
		global $wpdb;

		return array(
			'visits' => $wpdb->prefix . 'mm_visits',
			'audience' => $wpdb->prefix . 'mm_audience_facts',
			'hourly' => $wpdb->prefix . 'mm_source_hourly',
			'orders' => $wpdb->prefix . 'mm_order_facts',
			'items'  => $wpdb->prefix . 'mm_order_items',
			'logs'   => $wpdb->prefix . 'mm_logs',
		);
	}

	public static function activate() {
		$installed = self::install();
		if ( $installed ) {
			self::schedule_events();
		}

		$user_id = get_current_user_id();
		$allowed = get_option( 'mm_allowed_users', null );
		if ( null === $allowed ) {
			$allowed = $user_id ? array( $user_id ) : array();
			add_option( 'mm_allowed_users', $allowed, '', false );
		}
		foreach ( array_map( 'absint', (array) $allowed ) as $allowed_user_id ) {
			$user = get_user_by( 'id', $allowed_user_id );
			if ( $user ) {
				$user->add_cap( 'view_monitor_man' );
			}
		}

		if ( ! get_option( 'mm_installed_at' ) ) {
			add_option( 'mm_installed_at', current_time( 'mysql' ), '', false );
		}

		if ( class_exists( 'MM_Logger' ) ) {
			if ( $installed ) {
				MM_Logger::info( 'installer', 'افزونه مانیتور من فعال شد و ساختار بانک اطلاعاتی با موفقیت تأیید شد.', array( 'db_version' => self::DB_VERSION ) );
			} else {
				MM_Logger::error( 'installer', 'افزونه فعال شد اما ساختار بانک اطلاعاتی کامل نشد.', array( 'db_version' => self::DB_VERSION, 'error' => get_option( 'mm_db_install_error', '' ) ) );
			}
		}
	}

	/*
	 * Runs on every request (plugins_loaded), including uncached frontend pages.
	 * mm_db_version is not autoloaded, so reading it cost one query per page;
	 * the autoloaded boot marker below makes the normal case query-free. A failed
	 * install is retried every ten minutes instead of on every page view.
	 */
	public static function maybe_upgrade() {
		$marker = self::DB_VERSION . '|' . ( defined( 'MM_VERSION' ) ? MM_VERSION : '' );
		$state  = get_option( self::BOOT_OPTION, array() );
		$state  = is_array( $state ) ? $state : array();
		if ( ( $state['ready'] ?? '' ) === $marker ) {
			return;
		}
		if ( ( $state['failed'] ?? '' ) === $marker && (int) ( $state['retry_at'] ?? 0 ) > time() ) {
			return;
		}

		$ok = self::DB_VERSION === (string) get_option( 'mm_db_version', '' ) ? true : self::install();
		if ( $ok ) {
			self::schedule_events();
			update_option( self::BOOT_OPTION, array( 'ready' => $marker ), true );
		} else {
			update_option( self::BOOT_OPTION, array( 'failed' => $marker, 'retry_at' => time() + 600 ), true );
		}
	}

	public static function schedule_events() {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::AGG_EVENT ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::AGG_EVENT );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::AGG_EVENT );
		delete_option( self::BOOT_OPTION );
	}

	private static function install() {
		global $wpdb;

		$tables  = self::tables();
		$collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE {$tables['visits']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_key char(64) NOT NULL,
				visitor_id varchar(64) NOT NULL,
				session_id varchar(64) NOT NULL,
				visited_at datetime NOT NULL,
				source_key varchar(100) NOT NULL DEFAULT 'direct',
				source_label varchar(190) NOT NULL DEFAULT '',
				medium varchar(100) NOT NULL DEFAULT '',
				campaign varchar(190) NOT NULL DEFAULT '',
				referrer_host varchar(190) NOT NULL DEFAULT '',
				landing_url varchar(1000) NOT NULL DEFAULT '',
				page_url varchar(1000) NOT NULL DEFAULT '',
				page_type varchar(32) NOT NULL DEFAULT 'page',
				object_id bigint(20) unsigned NOT NULL DEFAULT 0,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				is_entry tinyint(1) unsigned NOT NULL DEFAULT 0,
				transport varchar(24) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				UNIQUE KEY event_key (event_key),
				KEY visited_at (visited_at),
				KEY visitor_id (visitor_id),
				KEY session_id (session_id),
				KEY source_key (source_key),
				KEY object_id (object_id),
				KEY source_date (source_key, visited_at),
				KEY product_date (page_type, object_id, visited_at),
				KEY entry_date (is_entry, visited_at),
				KEY entry_source (is_entry, source_key, visited_at)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$tables['audience']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				fact_key char(64) NOT NULL,
				visitor_hash char(64) NOT NULL,
				session_hash char(64) NOT NULL,
				first_seen datetime NOT NULL,
				last_seen datetime NOT NULL,
				source_key varchar(100) NOT NULL DEFAULT 'direct',
				source_label varchar(190) NOT NULL DEFAULT '',
				referrer_host varchar(190) NOT NULL DEFAULT '',
				pageviews bigint(20) unsigned NOT NULL DEFAULT 0,
				product_views bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY fact_key (fact_key),
				KEY visitor_hash (visitor_hash),
				KEY session_hash (session_hash),
				KEY first_seen (first_seen),
				KEY last_seen (last_seen),
				KEY source_key (source_key),
				KEY source_range (source_key, first_seen, last_seen)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$tables['hourly']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				bucket_start datetime NOT NULL,
				source_hash char(32) NOT NULL,
				source_key varchar(100) NOT NULL DEFAULT 'direct',
				source_label varchar(190) NOT NULL DEFAULT '',
				referrer_host varchar(190) NOT NULL DEFAULT '',
				visitors bigint(20) unsigned NOT NULL DEFAULT 0,
				sessions bigint(20) unsigned NOT NULL DEFAULT 0,
				pageviews bigint(20) unsigned NOT NULL DEFAULT 0,
				product_views bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY bucket_source (bucket_start, source_hash),
				KEY bucket_start (bucket_start),
				KEY source_key (source_key)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$tables['orders']} (
				order_id bigint(20) unsigned NOT NULL,
				created_at datetime NOT NULL,
				status varchar(40) NOT NULL DEFAULT '',
				currency varchar(12) NOT NULL DEFAULT '',
				gross_total decimal(24,4) NOT NULL DEFAULT 0,
				payment_method varchar(100) NOT NULL DEFAULT '',
				payment_method_title varchar(190) NOT NULL DEFAULT '',
				source_key varchar(100) NOT NULL DEFAULT 'unknown',
				source_label varchar(190) NOT NULL DEFAULT '',
				medium varchar(100) NOT NULL DEFAULT '',
				campaign varchar(190) NOT NULL DEFAULT '',
				referrer_host varchar(190) NOT NULL DEFAULT '',
				visitor_id varchar(64) NOT NULL DEFAULT '',
				attribution_method varchar(40) NOT NULL DEFAULT '',
				confidence tinyint(3) unsigned NOT NULL DEFAULT 0,
				scanned_at datetime NOT NULL,
				PRIMARY KEY  (order_id),
				KEY created_at (created_at),
				KEY status (status),
				KEY payment_method (payment_method),
				KEY source_key (source_key),
				KEY report_range (created_at, status, source_key)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$tables['items']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				order_id bigint(20) unsigned NOT NULL,
				order_item_id bigint(20) unsigned NOT NULL,
				product_id bigint(20) unsigned NOT NULL DEFAULT 0,
				variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
				product_name varchar(255) NOT NULL DEFAULT '',
				quantity decimal(20,4) NOT NULL DEFAULT 0,
				line_total decimal(24,4) NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY order_item (order_id, order_item_id),
				KEY order_id (order_id),
				KEY product_id (product_id),
				KEY product_order (product_id, order_id)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$tables['logs']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created_at datetime NOT NULL,
				level varchar(20) NOT NULL DEFAULT 'info',
				context varchar(80) NOT NULL DEFAULT 'general',
				message text NOT NULL,
				details longtext NULL,
				PRIMARY KEY  (id),
				KEY created_at (created_at),
				KEY level (level),
				KEY context (context)
			) {$collate};"
		);


		/*
		 * v1.4.1: traffic entries are stored explicitly. Older rows did not have an
		 * entry marker, so mark the first hit of every historical session once.
		 * This keeps existing seven-day data instead of making it disappear after
		 * the schema upgrade.
		 */
		$visit_columns = wp_list_pluck( (array) $wpdb->get_results( "SHOW COLUMNS FROM `{$tables['visits']}`", ARRAY_A ), 'Field' );
		if ( in_array( 'is_entry', $visit_columns, true ) && '1' !== (string) get_option( 'mm_entry_backfill_version', '' ) ) {
			$wpdb->query( "UPDATE {$tables['visits']} SET is_entry=0" );
			$backfill = $wpdb->query(
				"UPDATE {$tables['visits']} v
				 INNER JOIN (SELECT session_id, MIN(id) first_id FROM {$tables['visits']} GROUP BY session_id) firsts
				 ON firsts.first_id=v.id
				 SET v.is_entry=1"
			);
			if ( false !== $backfill ) {
				update_option( 'mm_entry_backfill_version', '1', false );
			}
		}

		// Never mark a migration as complete until the required storage is really usable.
		$errors = array();
		foreach ( $tables as $key => $table ) {
			$exists = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( ! $exists ) {
				$errors[] = sprintf( 'table:%s', $key );
			}
		}

		if ( ! $errors ) {
			$order_columns = wp_list_pluck( (array) $wpdb->get_results( "SHOW COLUMNS FROM `{$tables['orders']}`", ARRAY_A ), 'Field' );
			foreach ( array( 'order_id', 'created_at', 'status', 'gross_total', 'payment_method', 'payment_method_title', 'source_key', 'scanned_at' ) as $column ) {
				if ( ! in_array( $column, $order_columns, true ) ) {
					$errors[] = 'orders_column:' . $column;
				}
			}
			$visit_columns = wp_list_pluck( (array) $wpdb->get_results( "SHOW COLUMNS FROM `{$tables['visits']}`", ARRAY_A ), 'Field' );
			foreach ( array( 'event_key', 'visitor_id', 'session_id', 'visited_at', 'source_key', 'is_entry', 'transport' ) as $column ) {
				if ( ! in_array( $column, $visit_columns, true ) ) {
					$errors[] = 'visits_column:' . $column;
				}
			}
		}

		if ( $errors ) {
			$error = 'ساختار بانک مانیتور من کامل نشد: ' . implode( ', ', $errors );
			if ( $wpdb->last_error ) {
				$error .= ' — ' . $wpdb->last_error;
			}
			$previous_error = sanitize_text_field( (string) get_option( 'mm_db_install_error', '' ) );
			update_option( 'mm_db_install_error', sanitize_text_field( $error ), false );
			if ( class_exists( 'MM_Logger' ) && $previous_error !== sanitize_text_field( $error ) ) {
				MM_Logger::error( 'installer', $error, array( 'db_error' => $wpdb->last_error, 'missing' => $errors ) );
			}
			return false;
		}

		delete_option( 'mm_db_install_error' );
		update_option( 'mm_db_version', self::DB_VERSION, false );
		return true;
	}
}
