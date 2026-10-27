<?php

defined( 'ABSPATH' ) || exit;

final class MM_Repository {
	const AGG_CURSOR_OPTION = 'mm_agg_cursor';
	const AGG_BATCH_ROWS    = 20000;

	private static $order_queue = array();
	private static $order_flush_registered = false;

	/*
	 * Checkout fires several hooks for one order (order created, Store API
	 * processed, every status change). The report row is written once, for the
	 * final state, after the response has been handed to the visitor.
	 */
	public static function sync_order_object( $order ) {
		if ( is_a( $order, 'WC_Order' ) ) {
			self::queue_order_sync( $order->get_id() );
		}
	}

	public static function sync_order_id( $order_id ) {
		self::queue_order_sync( $order_id );
	}

	private static function queue_order_sync( $order_id ) {
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return;
		}
		self::$order_queue[ $order_id ] = true;
		if ( ! self::$order_flush_registered ) {
			self::$order_flush_registered = true;
			register_shutdown_function( array( __CLASS__, 'flush_order_queue' ) );
		}
	}

	public static function flush_order_queue() {
		if ( ! self::$order_queue || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order_ids          = array_keys( self::$order_queue );
		self::$order_queue  = array();
		// Runs after WordPress' own shutdown actions, so cookies/sessions are already sent.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			@fastcgi_finish_request(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			@litespeed_finish_request(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		foreach ( $order_ids as $order_id ) {
			try {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					self::store_order( $order );
				}
			} catch ( Throwable $e ) {
				if ( class_exists( 'MM_Logger' ) ) {
					MM_Logger::error( 'store_order', 'همگام‌سازی سفارش با خطا متوقف شد.', array( 'order_id' => $order_id, 'message' => $e->getMessage() ) );
				}
			}
		}
	}

	/**
	 * Bring the hourly/audience aggregate tables up to date with visits stored
	 * since the last run. Replaces the per-hit refresh_live_traffic() call: the
	 * tracker only inserts the raw row and this recomputes every touched
	 * (hour, source) bucket and every touched session from raw visits, with the
	 * same SQL definitions as the manual scan. Runs from WP-Cron and right before
	 * admin reports read the tables, so reports never see stale aggregates.
	 *
	 * @param float $time_budget Seconds to spend before leaving the rest for the next run.
	 * @return int Visit rows processed.
	 */
	public static function sync_live_aggregates( $time_budget = 5.0 ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		foreach ( array( 'visits', 'hourly', 'audience' ) as $key ) {
			if ( ! self::table_exists( $tables[ $key ] ) ) {
				return 0;
			}
		}

		$lock = 'mm_agg_' . $wpdb->prefix;
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			return 0; // another request is already syncing.
		}

		$processed = 0;
		$started   = microtime( true );
		try {
			$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$tables['visits']}" );
			$cursor = get_option( self::AGG_CURSOR_OPTION, null );
			if ( null === $cursor || '' === $cursor ) {
				// First run after upgrading from per-hit refresh: re-derive the last 24 hours.
				$since  = self::mysql_datetime_from_timestamp( current_datetime()->getTimestamp() - DAY_IN_SECONDS );
				$first  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MIN(id) FROM {$tables['visits']} WHERE visited_at >= %s", $since ) );
				// Bounded so the first sync after an upgrade stays a few seconds of cron work.
				$cursor = $first ? max( $first - 1, $max_id - 50000, 0 ) : $max_id;
				update_option( self::AGG_CURSOR_OPTION, (int) $cursor, false );
			}
			$cursor = (int) $cursor;

			$host_expr   = "CASE WHEN v.source_key='referral' THEN COALESCE(v.referrer_host,'') ELSE '' END";
			$source_expr = "COALESCE(NULLIF(v.source_key,''),'direct')";
			while ( $cursor < $max_id ) {
				$upto = min( $max_id, $cursor + self::AGG_BATCH_ROWS );

				/*
				 * Touched (source, hour) groups are few per batch; recompute each one
				 * with constant bounds so MySQL uses the source_date index instead of
				 * scanning the whole visits table.
				 */
				$hourly  = true;
				$touched = (array) $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DISTINCT source_key, DATE_FORMAT(visited_at, '%%Y-%%m-%%d %%H:00:00') bucket
						 FROM {$tables['visits']} WHERE id > %d AND id <= %d AND source_key NOT IN ('internal','test')",
						$cursor,
						$upto
					),
					ARRAY_A
				);
				foreach ( $touched as $group ) {
					$bucket_end = gmdate( 'Y-m-d H:i:s', strtotime( $group['bucket'] . ' UTC' ) + HOUR_IN_SECONDS );
					$saved      = $wpdb->query(
						$wpdb->prepare(
							"INSERT INTO {$tables['hourly']}
							(bucket_start, source_hash, source_key, source_label, referrer_host, visitors, sessions, pageviews, product_views)
							SELECT %s, MD5(CONCAT({$source_expr},'|',{$host_expr})),
							       {$source_expr}, MAX(COALESCE(v.source_label,'')), {$host_expr},
							       COUNT(DISTINCT v.visitor_id), COUNT(DISTINCT v.session_id), COUNT(*),
							       SUM(CASE WHEN v.page_type='product' THEN 1 ELSE 0 END)
							FROM {$tables['visits']} v
							WHERE v.source_key = %s AND v.visited_at >= %s AND v.visited_at < %s
							GROUP BY {$host_expr}
							ON DUPLICATE KEY UPDATE source_label=VALUES(source_label), visitors=VALUES(visitors), sessions=VALUES(sessions),
							pageviews=VALUES(pageviews), product_views=VALUES(product_views)",
							$group['bucket'],
							$group['source_key'],
							$group['bucket'],
							$bucket_end
						)
					);
					if ( false === $saved ) {
						$hourly = false;
						break;
					}
				}

				$audience = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$tables['audience']}
						(fact_key, visitor_hash, session_hash, first_seen, last_seen, source_key, source_label, referrer_host, pageviews, product_views)
						SELECT SHA2(CONCAT('own|',v.session_id,'|',v.source_key,'|',{$host_expr}),256),
						       SHA2(CONCAT('own|',MIN(v.visitor_id)),256), SHA2(CONCAT('own|',v.session_id),256),
						       MIN(v.visited_at), MAX(v.visited_at), {$source_expr}, MAX(COALESCE(v.source_label,'')),
						       {$host_expr}, COUNT(*), SUM(CASE WHEN v.page_type='product' THEN 1 ELSE 0 END)
						FROM (
							SELECT DISTINCT session_id FROM {$tables['visits']}
							WHERE id > %d AND id <= %d AND source_key NOT IN ('internal','test')
						) touched
						INNER JOIN {$tables['visits']} v ON v.session_id = touched.session_id
						WHERE v.source_key NOT IN ('internal','test')
						GROUP BY v.session_id, v.source_key, {$host_expr}
						ON DUPLICATE KEY UPDATE first_seen=VALUES(first_seen), last_seen=VALUES(last_seen),
						pageviews=VALUES(pageviews), product_views=VALUES(product_views)",
						$cursor,
						$upto
					)
				);

				if ( false === $hourly || false === $audience ) {
					if ( class_exists( 'MM_Logger' ) ) {
						MM_Logger::error( 'live_traffic', 'بروزرسانی آمار تجمیعی ورودی ناموفق بود.', array( 'db_error' => $wpdb->last_error, 'from_id' => $cursor, 'to_id' => $upto ) );
					}
					break;
				}
				$processed += $upto - $cursor;
				$cursor     = $upto;
				update_option( self::AGG_CURSOR_OPTION, $cursor, false );
				if ( ( microtime( true ) - $started ) > $time_budget ) {
					break;
				}
			}
		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
		return $processed;
	}

	public static function refresh_live_traffic( $source_key, $source_label, $referrer_host = '', $visitor_id = '', $session_id = '' ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$now    = current_datetime();
		$start  = $now->format( 'Y-m-d H:00:00' );
		$end    = $now->format( 'Y-m-d H:59:59' );
		$hash   = md5( $source_key . '|' . ( 'referral' === $source_key ? $referrer_host : '' ) );
		$sql    = "SELECT COUNT(DISTINCT visitor_id) visitors, COUNT(DISTINCT session_id) sessions,
			COUNT(*) pageviews, SUM(CASE WHEN page_type='product' THEN 1 ELSE 0 END) product_views
			FROM {$tables['visits']} WHERE visited_at BETWEEN %s AND %s AND source_key=%s";
		$params = array( $start, $end, $source_key );
		if ( 'referral' === $source_key ) {
			$sql     .= ' AND referrer_host=%s';
			$params[] = $referrer_host;
		}
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );
		if ( ! $row ) {
			return;
		}
		$hourly_saved = $wpdb->replace(
			$tables['hourly'],
			array(
				'bucket_start'  => $start,
				'source_hash'   => $hash,
				'source_key'    => $source_key,
				'source_label'  => $source_label,
				'referrer_host' => 'referral' === $source_key ? $referrer_host : '',
				'visitors'      => (int) $row['visitors'],
				'sessions'      => (int) $row['sessions'],
				'pageviews'     => (int) $row['pageviews'],
				'product_views' => (int) $row['product_views'],
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' )
		);
		if ( false === $hourly_saved && class_exists( 'MM_Logger' ) ) {
			MM_Logger::error( 'live_traffic', 'بروزرسانی آمار ساعتی ورودی ناموفق بود.', array( 'source' => $source_key, 'db_error' => $wpdb->last_error ) );
		}

		if ( $visitor_id && $session_id ) {
			$fact_host = 'referral' === $source_key ? $referrer_host : '';
			$session_where = "session_id=%s AND source_key=%s";
			$session_args  = array( $session_id, $source_key );
			if ( 'referral' === $source_key ) {
				$session_where .= ' AND referrer_host=%s';
				$session_args[] = $referrer_host;
			}
			$session_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT MIN(visited_at) first_seen, MAX(visited_at) last_seen, COUNT(*) pageviews,
					 SUM(CASE WHEN page_type='product' THEN 1 ELSE 0 END) product_views
					 FROM {$tables['visits']} WHERE {$session_where}",
					$session_args
				),
				ARRAY_A
			);
			if ( $session_row && $session_row['first_seen'] ) {
				$audience_saved = $wpdb->replace(
					$tables['audience'],
					array(
						'fact_key'      => hash( 'sha256', 'own|' . $session_id . '|' . $source_key . '|' . $fact_host ),
						'visitor_hash'  => hash( 'sha256', 'own|' . $visitor_id ),
						'session_hash'  => hash( 'sha256', 'own|' . $session_id ),
						'first_seen'    => $session_row['first_seen'],
						'last_seen'     => $session_row['last_seen'],
						'source_key'    => $source_key,
						'source_label'  => $source_label,
						'referrer_host' => $fact_host,
						'pageviews'     => (int) $session_row['pageviews'],
						'product_views' => (int) $session_row['product_views'],
					),
					array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
				);
				if ( false === $audience_saved && class_exists( 'MM_Logger' ) ) {
					MM_Logger::error( 'live_traffic', 'بروزرسانی جدول کاربران/نشست‌ها ناموفق بود.', array( 'source' => $source_key, 'db_error' => $wpdb->last_error ) );
				}
			}
		}
	}

	public static function scan_reset() {
		self::assert_storage_ready();
		$previous = (array) get_option( 'mm_scan_state', array() );
		$state    = array(
			'status'           => 'running',
			'scan_id'          => wp_generate_uuid4(),
			'started_at'       => current_time( 'mysql' ),
			'cutoff_timestamp' => current_datetime()->getTimestamp(),
			'finished_at'      => sanitize_text_field( $previous['finished_at'] ?? '' ),
			'processed_orders' => 0,
			'stored_orders'    => 0,
			'failed_orders'    => 0,
			'item_errors'      => 0,
			'expected_orders'  => 0,
			'verified_orders'  => 0,
			'last_page'        => 0,
			'max_pages'        => 0,
			'processed_pages'  => array(),
			'traffic'          => 'pending',
			'traffic_complete' => false,
			'traffic_hours'    => 0,
			'audience_rows'    => 0,
			'errors'           => array(),
			'message'          => 'اسکن در حال اجرا است.',
		);
		update_option( 'mm_scan_state', $state, false );
		$state['message'] = 'اسکن آماده شد؛ داده‌های قبلی تا زمان آماده‌شدن گزارش جدید حفظ می‌شوند.';
		if ( class_exists( 'MM_Logger' ) ) {
			MM_Logger::info( 'scan', 'اسکن دستی جدید شروع شد.', array( 'scan_id' => $state['scan_id'], 'started_at' => $state['started_at'] ) );
		}
		return $state;
	}

	public static function scan_traffic() {
		self::assert_storage_ready();
		global $wpdb;
		$tables    = MM_Installer::tables();
		$legacy    = self::legacy_traffic_table();
		$own_first = self::own_tracking_start();
		$state     = (array) get_option( 'mm_scan_state', array() );

		if ( 'running' !== ( $state['status'] ?? '' ) ) {
			return new WP_Error( 'mm_scan_not_running', 'ابتدا اسکن را از نو شروع کنید.' );
		}

		// Keep the incremental aggregate sync out while the tables are rebuilt.
		$agg_lock   = 'mm_agg_' . $wpdb->prefix;
		$wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 30)', $agg_lock ) );
		$agg_cursor = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$tables['visits']}" );

		$wpdb->query( 'START TRANSACTION' );
		$queries_ok = true;
		$last_error = '';
		$run = static function ( $sql ) use ( $wpdb, &$queries_ok, &$last_error ) {
			if ( false === $wpdb->query( $sql ) ) {
				$queries_ok = false;
				$last_error = $wpdb->last_error ?: 'خطای ناشناخته پایگاه داده';
				return false;
			}
			return true;
		};

		$run( "DELETE FROM {$tables['hourly']}" );
		$run( "DELETE FROM {$tables['audience']}" );

		$legacy_columns = self::table_columns( $legacy );
		if ( $queries_ok && in_array( 'visitor_id', $legacy_columns, true ) && in_array( 'visited_at', $legacy_columns, true ) ) {
			$legacy_cutoff = $own_first ?: '9999-12-31 23:59:59';
			$source_expr   = in_array( 'source_key', $legacy_columns, true ) ? "COALESCE(NULLIF(source_key,''),'direct')" : "'direct'";
			$label_expr    = in_array( 'source_label', $legacy_columns, true ) ? "MAX(COALESCE(source_label,''))" : "''";
			$host_expr     = in_array( 'referrer_host', $legacy_columns, true )
				? "CASE WHEN {$source_expr}='referral' THEN COALESCE(referrer_host,'') ELSE '' END"
				: "''";
			$legacy_where  = 'visited_at < %s';
			if ( in_array( 'source_key', $legacy_columns, true ) ) {
				$legacy_where .= " AND COALESCE(source_key,'') NOT IN ('internal','test')";
			}
			$legacy_params = array( $legacy_cutoff );

			$audience_sql = "INSERT INTO {$tables['audience']}
				(fact_key, visitor_hash, session_hash, first_seen, last_seen, source_key, source_label, referrer_host, pageviews, product_views)
				SELECT SHA2(CONCAT('legacy|',visitor_id,'|',DATE(visited_at),'|',{$source_expr},'|',{$host_expr}),256),
				       SHA2(CONCAT('legacy|',visitor_id),256), SHA2(CONCAT('legacy|',visitor_id,'|',DATE(visited_at)),256),
				       MIN(visited_at), MAX(visited_at), {$source_expr}, {$label_expr}, {$host_expr}, COUNT(*), 0
				FROM {$legacy} WHERE {$legacy_where}
				GROUP BY visitor_id, DATE(visited_at), {$source_expr}, {$host_expr}
				ON DUPLICATE KEY UPDATE first_seen=VALUES(first_seen), last_seen=VALUES(last_seen), pageviews=VALUES(pageviews), product_views=VALUES(product_views)";
			$run( self::maybe_prepare( $audience_sql, $legacy_params ) );

			$hourly_sql = "INSERT INTO {$tables['hourly']}
				(bucket_start, source_hash, source_key, source_label, referrer_host, visitors, sessions, pageviews, product_views)
				SELECT DATE_FORMAT(visited_at, '%%Y-%%m-%%d %%H:00:00'),
				       MD5(CONCAT({$source_expr},'|',{$host_expr})),
				       {$source_expr}, {$label_expr}, {$host_expr},
				       COUNT(DISTINCT visitor_id), COUNT(DISTINCT visitor_id), COUNT(*), 0
				FROM {$legacy} WHERE {$legacy_where}
				GROUP BY DATE_FORMAT(visited_at, '%%Y-%%m-%%d %%H:00:00'), {$source_expr}, {$host_expr}
				ON DUPLICATE KEY UPDATE visitors=VALUES(visitors), sessions=VALUES(sessions),
				pageviews=VALUES(pageviews), product_views=VALUES(product_views)";
			$run( self::maybe_prepare( $hourly_sql, $legacy_params ) );
		}

		if ( $queries_ok ) {
			$audience_sql = "INSERT INTO {$tables['audience']}
				(fact_key, visitor_hash, session_hash, first_seen, last_seen, source_key, source_label, referrer_host, pageviews, product_views)
				SELECT SHA2(CONCAT('own|',session_id,'|',source_key,'|',CASE WHEN source_key='referral' THEN COALESCE(referrer_host,'') ELSE '' END),256),
				       SHA2(CONCAT('own|',visitor_id),256), SHA2(CONCAT('own|',session_id),256),
				       MIN(visited_at), MAX(visited_at), COALESCE(NULLIF(source_key,''),'direct'), MAX(COALESCE(source_label,'')),
				       CASE WHEN source_key='referral' THEN COALESCE(referrer_host,'') ELSE '' END,
				       COUNT(*), SUM(CASE WHEN page_type='product' THEN 1 ELSE 0 END)
				FROM {$tables['visits']} WHERE source_key NOT IN ('internal','test')
				GROUP BY session_id, source_key, CASE WHEN source_key='referral' THEN COALESCE(referrer_host,'') ELSE '' END
				ON DUPLICATE KEY UPDATE first_seen=VALUES(first_seen), last_seen=VALUES(last_seen),
				pageviews=VALUES(pageviews), product_views=VALUES(product_views)";
			$run( $audience_sql );

			$hourly_sql = "INSERT INTO {$tables['hourly']}
				(bucket_start, source_hash, source_key, source_label, referrer_host, visitors, sessions, pageviews, product_views)
				SELECT DATE_FORMAT(visited_at, '%Y-%m-%d %H:00:00'),
				       MD5(CONCAT(COALESCE(NULLIF(source_key,''),'direct'),'|',CASE WHEN source_key='referral' THEN COALESCE(referrer_host,'') ELSE '' END)),
				       COALESCE(NULLIF(source_key,''),'direct'), MAX(COALESCE(source_label,'')),
				       CASE WHEN source_key='referral' THEN COALESCE(referrer_host,'') ELSE '' END,
				       COUNT(DISTINCT visitor_id), COUNT(DISTINCT session_id), COUNT(*),
				       SUM(CASE WHEN page_type='product' THEN 1 ELSE 0 END)
				FROM {$tables['visits']} WHERE source_key NOT IN ('internal','test')
				GROUP BY DATE_FORMAT(visited_at, '%Y-%m-%d %H:00:00'), source_key,
				         CASE WHEN source_key='referral' THEN COALESCE(referrer_host,'') ELSE '' END
				ON DUPLICATE KEY UPDATE visitors=VALUES(visitors), sessions=VALUES(sessions),
				pageviews=VALUES(pageviews), product_views=VALUES(product_views)";
			$run( $hourly_sql );
		}

		if ( ! $queries_ok ) {
			$wpdb->query( 'ROLLBACK' );
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $agg_lock ) );
			if ( class_exists( 'MM_Logger' ) ) {
				MM_Logger::error( 'scan_traffic', 'تجمیع داده‌های ورودی با خطای پایگاه داده متوقف شد.', array( 'db_error' => $last_error ) );
			}
			self::fail_scan( 'خواندن داده‌های ورودی کامل نشد: ' . ( $last_error ?: 'خطای ناشناخته پایگاه داده' ) );
			return new WP_Error( 'mm_traffic_scan_failed', 'خواندن کامل داده‌های ورودی ممکن نشد. اطلاعات قبلی حفظ شد.' );
		}

		$wpdb->query( 'COMMIT' );
		// Rows after $agg_cursor arrived during the rebuild; the next sync recomputes them.
		update_option( self::AGG_CURSOR_OPTION, $agg_cursor, false );
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $agg_lock ) );
		$hours    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['hourly']}" );
		$audience = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['audience']}" );
		$state['traffic']          = 'complete';
		$state['traffic_complete'] = true;
		$state['traffic_hours']    = $hours;
		$state['audience_rows']    = $audience;
		$state['legacy_found']     = self::table_exists( $legacy );
		$state['message']          = 'ورودی‌ها و کاربران با موفقیت تجمیع شدند.';
		update_option( 'mm_scan_state', $state, false );
		if ( class_exists( 'MM_Logger' ) ) {
			MM_Logger::info( 'scan_traffic', 'تجمیع ورودی‌ها با موفقیت انجام شد.', array( 'hours' => $hours, 'audience' => $audience, 'legacy_found' => self::table_exists( $legacy ) ) );
		}

		return array(
			'legacy_found' => self::table_exists( $legacy ),
			'own_first'    => $own_first,
			'hours'        => $hours,
			'audience'     => $audience,
			'scan'         => $state,
		);
	}

	public static function scan_orders_page( $page = 1, $limit = 50 ) {
		self::assert_storage_ready();
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return new WP_Error( 'mm_no_woocommerce', 'ووکامرس فعال نیست.' );
		}

		$page  = max( 1, absint( $page ) );
		$limit = max( 10, min( 100, absint( $limit ) ) );
		$state = (array) get_option( 'mm_scan_state', array() );
		if ( 'running' !== ( $state['status'] ?? '' ) ) {
			return new WP_Error( 'mm_scan_not_running', 'اسکن فعال نیست؛ دوباره روی بررسی دستی بزنید.' );
		}

		try {
			$cutoff = absint( $state['cutoff_timestamp'] ?? current_datetime()->getTimestamp() );
			$result = wc_get_orders(
				array(
					'type'     => 'shop_order',
					'status'   => self::woocommerce_scan_statuses(),
					'limit'    => $limit,
					'page'     => $page,
					'paginate' => true,
					'orderby'  => 'ID',
					'order'    => 'ASC',
					'return'   => 'objects',
					'date_created' => '<=' . $cutoff,
				)
			);
		} catch ( Throwable $e ) {
			if ( class_exists( 'MM_Logger' ) ) {
				MM_Logger::error( 'scan_orders', 'خواندن سفارش‌های ووکامرس با استثنا متوقف شد.', array( 'page' => $page, 'message' => $e->getMessage() ) );
			}
			self::fail_scan( $e->getMessage() );
			return new WP_Error( 'mm_order_scan_failed', 'خواندن سفارش‌های ووکامرس متوقف شد: ' . $e->getMessage() );
		}

		$orders      = isset( $result->orders ) ? (array) $result->orders : array();
		$stored      = 0;
		$failed_ids  = array();
		$page_errors = array();
		foreach ( $orders as $order ) {
			$saved = self::store_order( $order, true );
			if ( empty( $saved['ok'] ) ) {
				$saved = self::store_order( $order, true );
			}
			if ( ! empty( $saved['ok'] ) ) {
				$stored++;
			} else {
				$failed_ids[] = is_a( $order, 'WC_Order' ) ? (int) $order->get_id() : 0;
				if ( ! empty( $saved['error'] ) ) {
					$page_errors[] = $saved['error'];
				}
			}
		}

		if ( $failed_ids ) {
			$failed_ids = array_values( array_filter( array_unique( $failed_ids ) ) );
			$message    = sprintf( 'ذخیره %1$d سفارش کامل نشد. شناسه‌ها: %2$s', count( $failed_ids ), implode( '، ', array_slice( $failed_ids, 0, 8 ) ) );
			if ( $page_errors ) {
				$message .= ' — ' . implode( ' | ', array_slice( array_unique( $page_errors ), 0, 2 ) );
			}
			if ( class_exists( 'MM_Logger' ) ) {
				MM_Logger::error( 'scan_orders', $message, array( 'page' => $page, 'failed_order_ids' => $failed_ids, 'errors' => array_slice( array_unique( $page_errors ), 0, 5 ) ) );
			}
			self::fail_scan( $message );
			return new WP_Error( 'mm_order_store_failed', $message );
		}

		$pages = array_map( 'absint', (array) ( $state['processed_pages'] ?? array() ) );
		if ( ! in_array( $page, $pages, true ) ) {
			$state['processed_orders'] = (int) ( $state['processed_orders'] ?? 0 ) + count( $orders );
			$state['stored_orders']    = (int) ( $state['stored_orders'] ?? 0 ) + $stored;
			$pages[]                   = $page;
		}
		$state['processed_pages'] = array_values( array_unique( $pages ) );
		$state['expected_orders'] = max( (int) ( $state['expected_orders'] ?? 0 ), isset( $result->total ) ? (int) $result->total : count( $orders ) );
		$state['max_pages']       = max( (int) ( $state['max_pages'] ?? 0 ), isset( $result->max_num_pages ) ? (int) $result->max_num_pages : 1 );
		$state['last_page']       = max( (int) ( $state['last_page'] ?? 0 ), $page );
		$state['message']         = sprintf( 'در حال خواندن سفارش‌ها: %1$s از %2$s', number_format_i18n( $state['processed_orders'] ), number_format_i18n( $state['expected_orders'] ) );
		update_option( 'mm_scan_state', $state, false );
		if ( class_exists( 'MM_Logger' ) ) {
			MM_Logger::info( 'scan_orders', 'یک صفحه از سفارش‌ها پردازش شد.', array( 'page' => $page, 'fetched' => count( $orders ), 'stored' => $stored, 'processed_total' => (int) $state['processed_orders'], 'expected' => (int) $state['expected_orders'] ) );
		}

		return array(
			'processed'       => $stored,
			'fetched'         => count( $orders ),
			'processed_total' => (int) $state['processed_orders'],
			'stored_total'    => (int) $state['stored_orders'],
			'total'           => (int) $state['expected_orders'],
			'max_pages'       => max( 1, (int) $state['max_pages'] ),
			'page'            => $page,
			'limit'           => $limit,
			'scan'            => $state,
		);
	}

	public static function finish_scan() {
		global $wpdb;
		$tables = MM_Installer::tables();
		$state  = (array) get_option( 'mm_scan_state', array() );
		if ( 'running' !== ( $state['status'] ?? '' ) ) {
			return new WP_Error( 'mm_scan_not_running', 'اسکن در وضعیت قابل نهایی‌سازی نیست.' );
		}

		$count_result = self::woocommerce_order_count( absint( $state['cutoff_timestamp'] ?? 0 ) );
		if ( is_wp_error( $count_result ) ) {
			$state['status']  = 'partial';
			$state['message'] = $count_result->get_error_message();
			update_option( 'mm_scan_state', $state, false );
			return $count_result;
		}
		$expected   = max( (int) ( $state['expected_orders'] ?? 0 ), (int) $count_result );
		$processed  = (int) ( $state['stored_orders'] ?? 0 );
		$started_at = sanitize_text_field( $state['started_at'] ?? '' );
		if ( 'complete' !== ( $state['traffic'] ?? '' ) || $processed < $expected ) {
			$state['status']  = 'partial';
			$state['message'] = sprintf( 'بررسی ناقص ماند: %1$s از %2$s سفارش پردازش شد.', number_format_i18n( $processed ), number_format_i18n( $expected ) );
			update_option( 'mm_scan_state', $state, false );
			if ( class_exists( 'MM_Logger' ) ) {
				MM_Logger::warning( 'scan_finish', $state['message'], array( 'traffic' => $state['traffic'] ?? '', 'processed' => $processed, 'expected' => $expected ) );
			}
			return new WP_Error( 'mm_scan_incomplete', $state['message'] . ' داده‌های قبلی حذف نشدند.' );
		}

		$wpdb->query( 'START TRANSACTION' );
		$cleanup_ok = true;
		if ( $started_at && false === $wpdb->query( $wpdb->prepare( "DELETE FROM {$tables['orders']} WHERE scanned_at < %s", $started_at ) ) ) {
			$cleanup_ok = false;
		}
		if ( $cleanup_ok && false === $wpdb->query( "DELETE i FROM {$tables['items']} i LEFT JOIN {$tables['orders']} o ON o.order_id=i.order_id WHERE o.order_id IS NULL" ) ) {
			$cleanup_ok = false;
		}

		$verified = $cleanup_ok ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['orders']}" ) : 0;
		if ( ! $cleanup_ok || $verified < $expected ) {
			$db_error = $wpdb->last_error;
			$wpdb->query( 'ROLLBACK' );
			$state['status']          = 'partial';
			$state['verified_orders'] = $verified;
			$state['message']         = $cleanup_ok
				? sprintf( 'پس از تطبیق نهایی، %1$s از %2$s سفارش در گزارش موجود است.', number_format_i18n( $verified ), number_format_i18n( $expected ) )
				: 'پاک‌سازی نهایی بانک گزارش با خطای پایگاه داده متوقف شد.';
			if ( $db_error ) {
				$state['db_error'] = sanitize_text_field( $db_error );
			}
			update_option( 'mm_scan_state', $state, false );
			if ( class_exists( 'MM_Logger' ) ) {
				MM_Logger::error( 'scan_finish', $state['message'], array( 'verified' => $verified, 'expected' => $expected, 'db_error' => $db_error ) );
			}
			return new WP_Error( 'mm_scan_verify_failed', $state['message'] . ' اطلاعات موجود حفظ شد.' );
		}
		$wpdb->query( 'COMMIT' );

		$items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['items']}" );
		$state['status']          = 'complete';
		$state['finished_at']     = current_time( 'mysql' );
		$state['verified_orders'] = $verified;
		$state['expected_orders'] = $expected;
		$state['database_orders'] = $verified;
		$state['database_items']  = $items;
		$coverage = $wpdb->get_row( "SELECT MIN(created_at) oldest_order, MAX(created_at) latest_order FROM {$tables['orders']}", ARRAY_A );
		$state['complete']          = true;
		$state['oldest_order_at']   = sanitize_text_field( $coverage['oldest_order'] ?? '' );
		$state['latest_order_at']   = sanitize_text_field( $coverage['latest_order'] ?? '' );
		$state['message']           = sprintf( 'بررسی کامل شد؛ %1$s سفارش و %2$s قلم سفارش در گزارش تأیید شد.', number_format_i18n( $verified ), number_format_i18n( $items ) );
		unset( $state['processed_pages'] );
		update_option( 'mm_scan_state', $state, false );
		if ( class_exists( 'MM_Logger' ) ) {
			MM_Logger::info( 'scan_finish', $state['message'], array( 'orders' => $verified, 'items' => $items, 'oldest_order' => $state['oldest_order_at'], 'latest_order' => $state['latest_order_at'] ) );
		}
		return $state;
	}

	public static function fail_scan( $message = '' ) {
		$state = (array) get_option( 'mm_scan_state', array() );
		$state['status']    = 'error';
		$state['failed_at'] = current_time( 'mysql' );
		$state['complete']  = false;
		$state['message']   = sanitize_text_field( $message ?: 'اسکن کامل نشد.' );
		update_option( 'mm_scan_state', $state, false );
		if ( class_exists( 'MM_Logger' ) ) {
			MM_Logger::error( 'scan', $state['message'], array( 'scan_id' => $state['scan_id'] ?? '', 'processed_orders' => $state['processed_orders'] ?? 0, 'stored_orders' => $state['stored_orders'] ?? 0 ) );
		}
		return $state;
	}

	private static function store_order( $order, $atomic = false ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return array( 'ok' => false, 'item_errors' => 0, 'error' => 'یک شیء سفارش نامعتبر دریافت شد.' );
		}

		global $wpdb;
		$tables = MM_Installer::tables();
		$attr   = self::resolve_order_source( $order );
		$date   = $order->get_date_created();
		$date   = $date ? $date->date( 'Y-m-d H:i:s' ) : current_time( 'mysql' );

		if ( $atomic ) {
			$wpdb->query( 'START TRANSACTION' );
		}

		$saved = $wpdb->replace(
			$tables['orders'],
			array(
				'order_id'           => $order->get_id(),
				'created_at'         => $date,
				'status'             => sanitize_key( $order->get_status() ),
				'currency'             => sanitize_text_field( $order->get_currency() ),
				'gross_total'          => (float) $order->get_total(),
				'payment_method'       => sanitize_key( (string) $order->get_payment_method() ),
				'payment_method_title' => sanitize_text_field( (string) $order->get_payment_method_title() ),
				'source_key'           => $attr['key'],
				'source_label'       => $attr['label'],
				'medium'             => $attr['medium'],
				'campaign'           => $attr['campaign'],
				'referrer_host'      => $attr['referrer_host'],
				'visitor_id'         => $attr['visitor_id'],
				'attribution_method' => $attr['method'],
				'confidence'         => $attr['confidence'],
				'scanned_at'         => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		if ( false === $saved ) {
			$error = sprintf( 'ذخیره سفارش #%d ناموفق بود: %s', $order->get_id(), $wpdb->last_error );
			if ( class_exists( 'MM_Logger' ) ) { MM_Logger::error( 'store_order', $error, array( 'order_id' => $order->get_id(), 'db_error' => $wpdb->last_error ) ); }
			if ( $atomic ) {
				$wpdb->query( 'ROLLBACK' );
			}
			return array( 'ok' => false, 'item_errors' => 0, 'error' => $error );
		}

		if ( false === $wpdb->delete( $tables['items'], array( 'order_id' => $order->get_id() ), array( '%d' ) ) ) {
			$error = sprintf( 'پاک‌سازی اقلام سفارش #%d ناموفق بود: %s', $order->get_id(), $wpdb->last_error );
			if ( class_exists( 'MM_Logger' ) ) { MM_Logger::error( 'store_order_items', $error, array( 'order_id' => $order->get_id(), 'db_error' => $wpdb->last_error ) ); }
			if ( $atomic ) {
				$wpdb->query( 'ROLLBACK' );
			}
			return array( 'ok' => false, 'item_errors' => 1, 'error' => $error );
		}

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product_id   = (int) $item->get_product_id();
			$variation_id = (int) $item->get_variation_id();
			$inserted = $wpdb->insert(
				$tables['items'],
				array(
					'order_id'      => $order->get_id(),
					'order_item_id' => (int) $item->get_id(),
					'product_id'    => $product_id,
					'variation_id'  => $variation_id,
					'product_name'  => sanitize_text_field( $item->get_name() ),
					'quantity'      => (float) $item->get_quantity(),
					'line_total'    => (float) $item->get_total() + (float) $item->get_total_tax(),
				),
				array( '%d', '%d', '%d', '%d', '%s', '%f', '%f' )
			);
			if ( false === $inserted ) {
				$error = sprintf( 'ذخیره قلم سفارش #%d ناموفق بود: %s', $order->get_id(), $wpdb->last_error );
				if ( class_exists( 'MM_Logger' ) ) { MM_Logger::error( 'store_order_items', $error, array( 'order_id' => $order->get_id(), 'item_id' => (int) $item->get_id(), 'db_error' => $wpdb->last_error ) ); }
				if ( $atomic ) {
					$wpdb->query( 'ROLLBACK' );
				}
				return array( 'ok' => false, 'item_errors' => 1, 'error' => $error );
			}
		}

		if ( $atomic ) {
			$wpdb->query( 'COMMIT' );
		}
		return array( 'ok' => true, 'item_errors' => 0, 'error' => '' );
	}

	private static function resolve_order_source( $order ) {
		$key = sanitize_key( (string) $order->get_meta( '_mm_source_key', true ) );
		if ( $key ) {
			return array(
				'key'           => $key,
				'label'         => sanitize_text_field( $order->get_meta( '_mm_source_label', true ) ?: self::source_label( $key ) ),
				'medium'        => sanitize_text_field( $order->get_meta( '_mm_medium', true ) ),
				'campaign'      => sanitize_text_field( $order->get_meta( '_mm_campaign', true ) ),
				'referrer_host' => sanitize_text_field( $order->get_meta( '_mm_referrer_host', true ) ),
				'visitor_id'    => sanitize_text_field( $order->get_meta( '_mm_visitor_id', true ) ),
				'method'        => 'monitor_man',
				'confidence'    => 100,
			);
		}

		$utm_source  = self::order_meta( $order, 'wc_order_attribution_utm_source' );
		$utm_medium  = self::order_meta( $order, 'wc_order_attribution_utm_medium' );
		$utm_campaign = self::order_meta( $order, 'wc_order_attribution_utm_campaign' );
		$referrer    = self::order_meta( $order, 'wc_order_attribution_referrer' );
		$entry       = self::order_meta( $order, 'wc_order_attribution_session_entry' );
		$source_type = self::order_meta( $order, 'wc_order_attribution_source_type' );

		if ( $utm_source || $referrer || $entry || $source_type ) {
			$url = $entry ?: home_url( '/' );
			if ( $utm_source ) {
				$url = add_query_arg(
					array(
						'utm_source'   => $utm_source,
						'utm_medium'   => $utm_medium,
						'utm_campaign' => $utm_campaign,
					),
					$url
				);
			}
			$source = MM_Attribution::detect_source( $url, $referrer );
			if ( 'direct' === $source['key'] && ! in_array( $source_type, array( 'typein', 'direct' ), true ) && $source_type ) {
				$source['key']   = sanitize_key( $source_type );
				$source['label'] = sanitize_text_field( $source_type );
			}
			return array(
				'key'           => $source['key'],
				'label'         => $source['label'],
				'medium'        => $utm_medium ?: ( $source['medium'] ?? '' ),
				'campaign'      => $utm_campaign,
				'referrer_host' => $source['referrer_host'] ?? '',
				'visitor_id'    => '',
				'method'        => 'woocommerce',
				'confidence'    => 90,
			);
		}

		$matched = self::match_legacy_visit( $order );
		if ( $matched ) {
			return $matched;
		}

		return array(
			'key' => 'unknown', 'label' => 'نامشخص', 'medium' => '', 'campaign' => '',
			'referrer_host' => '', 'visitor_id' => '', 'method' => 'unavailable', 'confidence' => 0,
		);
	}

	private static function order_meta( $order, $key ) {
		$value = $order->get_meta( '_' . ltrim( $key, '_' ), true );
		if ( '' === $value || null === $value ) {
			$value = $order->get_meta( ltrim( $key, '_' ), true );
		}
		return sanitize_text_field( (string) $value );
	}

	private static function match_legacy_visit( $order ) {
		$user_id = (int) $order->get_user_id();
		if ( ! $user_id ) {
			return null;
		}

		global $wpdb;
		$table = self::legacy_traffic_table();
		$columns = self::table_columns( $table );
		$required = array( 'user_id', 'visited_at', 'visitor_id', 'source_key' );
		if ( array_diff( $required, $columns ) ) {
			return null;
		}

		$date = $order->get_date_created();
		if ( ! $date ) {
			return null;
		}
		$end   = $date->date( 'Y-m-d H:i:s' );
		$start = self::mysql_datetime_from_timestamp( $date->getTimestamp() - ( 7 * DAY_IN_SECONDS ) );
		$label_select    = in_array( 'source_label', $columns, true ) ? 'source_label' : "'' source_label";
		$medium_select   = in_array( 'medium', $columns, true ) ? 'medium' : "'' medium";
		$campaign_select = in_array( 'campaign', $columns, true ) ? 'campaign' : "'' campaign";
		$host_select     = in_array( 'referrer_host', $columns, true ) ? 'referrer_host' : "'' referrer_host";
		$order_by        = in_array( 'id', $columns, true ) ? 'visited_at DESC, id DESC' : 'visited_at DESC';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT visitor_id, source_key, {$label_select}, {$medium_select}, {$campaign_select}, {$host_select}
				 FROM {$table}
				 WHERE user_id=%d AND visited_at BETWEEN %s AND %s
				   AND source_key NOT IN ('internal','direct','test')
				 ORDER BY {$order_by} LIMIT 1",
				$user_id,
				$start,
				$end
			)
		);

		if ( ! $row ) {
			return null;
		}
		return array(
			'key'           => sanitize_key( $row->source_key ),
			'label'         => sanitize_text_field( $row->source_label ?: self::source_label( $row->source_key ) ),
			'medium'        => sanitize_text_field( $row->medium ),
			'campaign'      => sanitize_text_field( $row->campaign ),
			'referrer_host' => sanitize_text_field( $row->referrer_host ),
			'visitor_id'    => sanitize_text_field( $row->visitor_id ),
			'method'        => 'legacy_user_match',
			'confidence'    => 70,
		);
	}

	public static function dashboard_data( array $args ) {
		self::assert_storage_ready();
		self::sync_live_aggregates();
		$range    = self::range( $args );
		$statuses = self::sanitize_statuses( $args['statuses'] ?? array() );
		$sources  = array_filter( array_map( 'sanitize_key', (array) ( $args['sources'] ?? array() ) ) );

		$current  = self::summary( $range['start'], $range['end'], $statuses, $sources );
		$previous = self::summary( $range['previous_start'], $range['previous_end'], $statuses, $sources );
		$source_rows = self::source_rows( $range, $statuses, $sources );
		$traffic_coverage = self::traffic_coverage();

		return array(
			'range'       => $range,
			'summary'     => $current,
			'previous'    => $previous,
			'changes'     => self::changes( $current, $previous ),
			'timeline'    => self::timeline( $range, $statuses, $sources ),
			'sources'     => $source_rows,
			'gantt'       => self::gantt( $range, $statuses, $sources ),
			'statuses'    => self::status_distribution( $range['start'], $range['end'], $sources ),
			'confidence'     => self::confidence_distribution( $range['start'], $range['end'], $statuses, $sources ),
			'recent'         => self::recent_orders( $range['start'], $range['end'], $statuses, $sources ),
			'gateways'       => self::gateway_summary( $range['start'], $range['end'], $statuses, $sources ),
			'gateway_recent' => self::recent_gateway_orders( $range['start'], $range['end'], $statuses, $sources ),
			'health'         => self::report_health( $range['start'], $range['end'], $statuses, $sources ),
			'scan'           => (array) get_option( 'mm_scan_state', array() ),
			'currency'    => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'تومان',
			'source_list' => self::available_sources(),
			'traffic_coverage' => $traffic_coverage,
		);
	}

	public static function products_data( array $args ) {
		self::assert_storage_ready();
		$range    = self::range( $args );
		$statuses = self::sanitize_statuses( $args['statuses'] ?? array() );
		$sources  = array_filter( array_map( 'sanitize_key', (array) ( $args['sources'] ?? array() ) ) );
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_raw  = sanitize_text_field( (string) ( $args['per_page'] ?? '20' ) );
		$per_page = 'all' === $per_raw ? 0 : max( 20, min( 100, absint( $per_raw ) ) );
		$sort     = sanitize_key( $args['sort'] ?? 'revenue' );
		$order    = 'asc' === strtolower( sanitize_text_field( (string) ( $args['order'] ?? 'desc' ) ) ) ? 'asc' : 'desc';
		$allowed  = array( 'rank', 'product', 'quantity', 'orders', 'views', 'conversion', 'revenue', 'change' );
		if ( ! in_array( $sort, $allowed, true ) ) {
			$sort = 'revenue';
		}

		$result = self::top_products( $range, $statuses, $sources, $page, $per_page, $sort, $order );
		return array(
			'range'        => $range,
			'products'     => $result['rows'],
			'pagination'   => $result['pagination'],
			'sort'         => array( 'key' => $sort, 'order' => $order ),
			'generated_at' => current_time( 'mysql' ),
			'scan'         => (array) get_option( 'mm_scan_state', array() ),
		);
	}

	private static function summary( $start, $end, $statuses, $sources ) {
		global $wpdb;
		$tables = MM_Installer::tables();

		$order_where = self::order_where( $start, $end, $statuses, $sources );
		$order_sql   = "SELECT COUNT(*) orders, COALESCE(SUM(gross_total),0) revenue, COALESCE(AVG(gross_total),0) aov FROM {$tables['orders']} {$order_where['sql']}";
		$order       = $wpdb->get_row( self::maybe_prepare( $order_sql, $order_where['params'] ), ARRAY_A );
		$traffic     = self::traffic_summary_exact( $start, $end, $sources );
		$traffic['product_views'] = self::product_views_summary( $start, $end, $sources );

		$entries    = (int) ( $traffic['entries'] ?? $traffic['sessions'] ?? 0 );
		$visitors   = (int) ( $traffic['visitors'] ?? 0 );
		$orders     = (int) ( $order['orders'] ?? 0 );
		$revenue    = (float) ( $order['revenue'] ?? 0 );
		$conversion = $entries > 0 ? ( $orders / $entries ) * 100 : 0;

		return array(
			'entries'       => $entries,
			'visitors'      => $visitors,
			'sessions'      => (int) ( $traffic['sessions'] ?? $entries ),
			'pageviews'     => (int) ( $traffic['pageviews'] ?? 0 ),
			'product_views' => (int) ( $traffic['product_views'] ?? 0 ),
			'orders'        => $orders,
			'revenue'       => $revenue,
			'aov'           => (float) ( $order['aov'] ?? 0 ),
			'conversion'    => round( $conversion, 2 ),
			'rpv'           => $entries > 0 ? round( $revenue / $entries, 2 ) : 0,
		);
	}

	private static function source_rows( $range, $statuses, $sources ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$ow     = self::order_where( $range['start'], $range['end'], $statuses, $sources );
		$pw     = self::order_where( $range['previous_start'], $range['previous_end'], $statuses, $sources );
		$traffic_map = self::traffic_source_metrics_exact( $range['start'], $range['end'], $sources );

		$orders = $wpdb->get_results(
			self::maybe_prepare(
				"SELECT source_key, MAX(source_label) source_label, COUNT(*) orders, SUM(gross_total) revenue
				 FROM {$tables['orders']} {$ow['sql']} GROUP BY source_key",
				$ow['params']
			),
			ARRAY_A
		);
		$previous = $wpdb->get_results(
			self::maybe_prepare(
				"SELECT source_key, SUM(gross_total) revenue FROM {$tables['orders']} {$pw['sql']} GROUP BY source_key",
				$pw['params']
			),
			ARRAY_A
		);

		$map = array();
		foreach ( $traffic_map as $key => $row ) {
			$map[ $key ] = array(
				'key' => $key,
				'label' => $row['label'] ?: self::source_label( $key ),
				'entries' => (int) ( $row['entries'] ?? $row['sessions'] ?? 0 ),
				'visitors' => (int) $row['visitors'],
				'sessions' => (int) $row['sessions'],
				'pageviews' => (int) $row['pageviews'],
				'orders' => 0,
				'revenue' => 0.0,
				'previous_revenue' => 0.0,
			);
		}
		foreach ( $orders as $row ) {
			$key = sanitize_key( $row['source_key'] ?: 'unknown' );
			if ( ! isset( $map[ $key ] ) ) {
				$map[ $key ] = array(
					'key' => $key, 'label' => $row['source_label'] ?: self::source_label( $key ),
					'entries' => 0, 'visitors' => 0, 'sessions' => 0, 'pageviews' => 0,
					'orders' => 0, 'revenue' => 0.0, 'previous_revenue' => 0.0,
				);
			}
			$map[ $key ]['orders']  = (int) $row['orders'];
			$map[ $key ]['revenue'] = (float) $row['revenue'];
		}
		foreach ( $previous as $row ) {
			$key = sanitize_key( $row['source_key'] ?: 'unknown' );
			if ( isset( $map[ $key ] ) ) {
				$map[ $key ]['previous_revenue'] = (float) $row['revenue'];
			}
		}

		$total_entries  = array_sum( array_column( $map, 'entries' ) );
		$total_visitors = array_sum( array_column( $map, 'visitors' ) );
		$total_revenue  = array_sum( array_column( $map, 'revenue' ) );
		foreach ( $map as &$row ) {
			$row['entry_share']   = $total_entries > 0 ? round( $row['entries'] / $total_entries * 100, 1 ) : 0;
			$row['visitor_share'] = $row['entry_share']; // backwards-compatible UI key.
			$row['revenue_share'] = $total_revenue > 0 ? round( $row['revenue'] / $total_revenue * 100, 1 ) : 0;
			$row['conversion']    = $row['entries'] > 0 ? round( $row['orders'] / $row['entries'] * 100, 2 ) : 0;
			$row['change']        = self::percent_change( $row['revenue'], $row['previous_revenue'] );
		}
		unset( $row );
		usort( $map, static function ( $a, $b ) {
			$cmp = $b['entries'] <=> $a['entries'];
			return 0 !== $cmp ? $cmp : ( $b['revenue'] <=> $a['revenue'] );
		} );
		return array_values( $map );
	}

	private static function timeline( $range, $statuses, $sources ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$ow     = self::order_where( $range['start'], $range['end'], $statuses, $sources );
		$traffic = self::traffic_timeline_exact( $range['start'], $range['end'], $sources );
		$orders = $wpdb->get_results(
			self::maybe_prepare(
				"SELECT DATE(created_at) day, COUNT(*) orders, SUM(gross_total) revenue
				 FROM {$tables['orders']} {$ow['sql']} GROUP BY DATE(created_at) ORDER BY day",
				$ow['params']
			), ARRAY_A
		);

		$coverage = self::traffic_coverage();
		$coverage_start = sanitize_text_field( $coverage['entry_start'] ?? $coverage['start'] ?? '' );
		$coverage_day = $coverage_start ? substr( $coverage_start, 0, 10 ) : '';
		$by_day = array();
		$tz = wp_timezone();
		$cursor = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $range['start'], $tz );
		$finish = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $range['end'], $tz );
		if ( $cursor && $finish ) {
			$cursor = $cursor->setTime( 0, 0, 0 );
			$finish = $finish->setTime( 0, 0, 0 );
			$guard = 0;
			while ( $cursor <= $finish && $guard < 800 ) {
				$day = $cursor->format( 'Y-m-d' );
				$covered = ! $coverage_day || $day >= $coverage_day;
				$by_day[ $day ] = array(
					'day' => $day,
					'entries' => $covered ? 0 : null,
					'visitors' => $covered ? 0 : null,
					'pageviews' => $covered ? 0 : null,
					'traffic_available' => $covered,
					'orders' => 0,
					'revenue' => 0,
				);
				$cursor = $cursor->modify( '+1 day' );
				$guard++;
			}
		}
		foreach ( $traffic as $row ) {
			if ( ! isset( $by_day[ $row['day'] ] ) ) {
				$by_day[ $row['day'] ] = array( 'day' => $row['day'], 'entries' => 0, 'visitors' => 0, 'pageviews' => 0, 'traffic_available' => true, 'orders' => 0, 'revenue' => 0 );
			}
			$by_day[ $row['day'] ]['entries'] = (int) ( $row['entries'] ?? 0 );
			$by_day[ $row['day'] ]['visitors'] = (int) ( $row['visitors'] ?? 0 );
			$by_day[ $row['day'] ]['pageviews'] = (int) ( $row['pageviews'] ?? 0 );
			$by_day[ $row['day'] ]['traffic_available'] = true;
		}
		foreach ( $orders as $row ) {
			if ( ! isset( $by_day[ $row['day'] ] ) ) {
				$covered = ! $coverage_day || $row['day'] >= $coverage_day;
				$by_day[ $row['day'] ] = array( 'day' => $row['day'], 'entries' => $covered ? 0 : null, 'visitors' => $covered ? 0 : null, 'pageviews' => $covered ? 0 : null, 'traffic_available' => $covered, 'orders' => 0, 'revenue' => 0 );
			}
			$by_day[ $row['day'] ]['orders']  = (int) $row['orders'];
			$by_day[ $row['day'] ]['revenue'] = (float) $row['revenue'];
		}
		ksort( $by_day );
		return array_values( $by_day );
	}

	private static function top_products( $range, $statuses, $sources, $page = 1, $per_page = 20, $sort = 'revenue', $direction = 'desc' ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$ow     = self::order_where( $range['start'], $range['end'], $statuses, $sources, 'o' );
		$pw     = self::order_where( $range['previous_start'], $range['previous_end'], $statuses, $sources, 'o' );

		$product_rows = $wpdb->get_results(
			"SELECT ID product_id, post_title product_name FROM {$wpdb->posts}
			 WHERE post_type='product' AND post_status NOT IN ('trash','auto-draft','inherit')",
			ARRAY_A
		);
		$catalog = array();
		foreach ( (array) $product_rows as $product_row ) {
			$product_id = (int) $product_row['product_id'];
			if ( $product_id ) {
				$catalog[ $product_id ] = sanitize_text_field( $product_row['product_name'] );
			}
		}

		$sales_sql = "SELECT i.product_id, MAX(i.product_name) product_name, SUM(i.quantity) quantity,
			COUNT(DISTINCT i.order_id) orders, SUM(i.line_total) revenue
			FROM {$tables['items']} i INNER JOIN {$tables['orders']} o ON o.order_id=i.order_id
			{$ow['sql']} AND i.product_id > 0 GROUP BY i.product_id";
		$sales_rows = $wpdb->get_results( self::maybe_prepare( $sales_sql, $ow['params'] ), ARRAY_A );
		$sales_map  = array();
		foreach ( (array) $sales_rows as $row ) {
			$product_id = (int) $row['product_id'];
			$sales_map[ $product_id ] = $row;
			if ( ! isset( $catalog[ $product_id ] ) ) {
				$catalog[ $product_id ] = sanitize_text_field( $row['product_name'] );
			}
		}

		$prev_sql = "SELECT i.product_id, SUM(i.line_total) revenue FROM {$tables['items']} i
			INNER JOIN {$tables['orders']} o ON o.order_id=i.order_id {$pw['sql']} AND i.product_id > 0 GROUP BY i.product_id";
		$prev_rows = $wpdb->get_results( self::maybe_prepare( $prev_sql, $pw['params'] ), ARRAY_A );
		$prev_map  = array();
		foreach ( (array) $prev_rows as $row ) {
			$prev_map[ (int) $row['product_id'] ] = (float) $row['revenue'];
		}

		$view_map = self::all_product_views_map( $range['start'], $range['end'], $sources );
		foreach ( array_keys( $view_map ) as $product_id ) {
			if ( $product_id && ! isset( $catalog[ $product_id ] ) ) {
				$title = get_the_title( $product_id );
				if ( $title ) {
					$catalog[ $product_id ] = sanitize_text_field( $title );
				}
			}
		}

		$rows = array();
		foreach ( $catalog as $product_id => $product_name ) {
			$sale       = $sales_map[ $product_id ] ?? array();
			$quantity   = (float) ( $sale['quantity'] ?? 0 );
			$orders     = (int) ( $sale['orders'] ?? 0 );
			$revenue    = (float) ( $sale['revenue'] ?? 0 );
			$views      = (int) ( $view_map[ $product_id ] ?? 0 );
			$conversion = $views > 0 ? round( $orders / $views * 100, 2 ) : null;
			$rows[]     = array(
				'product_id'   => (int) $product_id,
				'product_name' => $product_name ?: sanitize_text_field( $sale['product_name'] ?? ( 'محصول #' . $product_id ) ),
				'quantity'     => $quantity,
				'orders'       => $orders,
				'views'        => $views,
				'conversion'   => $conversion,
				'revenue'      => $revenue,
				'change'       => self::percent_change( $revenue, (float) ( $prev_map[ $product_id ] ?? 0 ) ),
			);
		}

		$numeric_key = array(
			'rank' => 'revenue', 'quantity' => 'quantity', 'orders' => 'orders', 'views' => 'views',
			'conversion' => 'conversion', 'revenue' => 'revenue', 'change' => 'change',
		);
		usort(
			$rows,
			static function ( $a, $b ) use ( $sort, $direction, $numeric_key ) {
				if ( 'product' === $sort ) {
					$cmp = strnatcasecmp( (string) $a['product_name'], (string) $b['product_name'] );
				} else {
					$key = $numeric_key[ $sort ] ?? 'revenue';
					$av  = null === ( $a[ $key ] ?? null ) ? -INF : (float) $a[ $key ];
					$bv  = null === ( $b[ $key ] ?? null ) ? -INF : (float) $b[ $key ];
					$cmp = $av <=> $bv;
					if ( 0 === $cmp ) {
						$cmp = (int) $a['product_id'] <=> (int) $b['product_id'];
					}
				}
				return 'asc' === $direction ? $cmp : -$cmp;
			}
		);

		$total       = count( $rows );
		$total_pages = $per_page > 0 ? max( 1, (int) ceil( $total / $per_page ) ) : 1;
		$page        = $per_page > 0 ? min( max( 1, $page ), $total_pages ) : 1;
		$offset      = $per_page > 0 ? ( $page - 1 ) * $per_page : 0;
		$page_rows   = $per_page > 0 ? array_slice( $rows, $offset, $per_page ) : $rows;

		foreach ( $page_rows as $index => &$row ) {
			$product_id      = (int) $row['product_id'];
			$thumb_id        = get_post_thumbnail_id( $product_id );
			$row['rank']      = $offset + $index + 1;
			$row['image']     = $thumb_id ? ( wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) ?: '' ) : '';
			$row['edit_url']  = get_edit_post_link( $product_id, 'raw' ) ?: '';
		}
		unset( $row );

		return array(
			'rows'       => array_values( $page_rows ),
			'pagination' => array(
				'page'        => $page,
				'per_page'    => $per_page > 0 ? $per_page : 'all',
				'total'       => $total,
				'total_pages' => $total_pages,
				'from'        => $total ? $offset + 1 : 0,
				'to'          => $total ? $offset + count( $page_rows ) : 0,
			),
		);
	}

	private static function all_product_views_map( $start, $end, $sources = array() ) {
		global $wpdb;
		$tables    = MM_Installer::tables();
		$own_first = self::own_tracking_start();
		$split_at  = $sources ? '' : self::product_tracking_split_start();
		$own_start = $split_at ?: ( $own_first && $start < $own_first ? $own_first : $start );
		$own_start = $start < $own_start ? $own_start : $start;
		$counts    = array();

		if ( $own_start <= $end ) {
			$sql    = "SELECT object_id product_id, COUNT(*) views FROM {$tables['visits']}
				WHERE page_type='product' AND object_id > 0 AND visited_at BETWEEN %s AND %s";
			$params = array( $own_start, $end );
			if ( $sources ) {
				$sql .= ' AND source_key IN (' . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')';
				$params = array_merge( $params, $sources );
			}
			$sql .= ' GROUP BY object_id';
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) as $row ) {
				$counts[ (int) $row['product_id'] ] = (int) $row['views'];
			}
		}

		$legacy = $wpdb->prefix . 'kkpa_product_views';
		$legacy_columns = self::table_columns( $legacy );
		if ( ! $sources && ! array_diff( array( 'product_id', 'view_date', 'views' ), $legacy_columns ) ) {
			$sql    = "SELECT product_id, COALESCE(SUM(views),0) views FROM {$legacy} WHERE product_id > 0 AND view_date BETWEEN %s AND %s";
			$params = array( substr( $start, 0, 10 ), substr( $end, 0, 10 ) );
			if ( $split_at ) {
				$sql      .= ' AND view_date < %s';
				$params[] = substr( $split_at, 0, 10 );
			}
			$sql .= ' GROUP BY product_id';
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) as $row ) {
				$product_id = (int) $row['product_id'];
				$counts[ $product_id ] = (int) ( $counts[ $product_id ] ?? 0 ) + (int) $row['views'];
			}
		}
		return $counts;
	}

	private static function gantt( $range, $statuses, $sources ) {
		$columns = self::gantt_columns( $range );
		return array(
			'default' => 'traffic',
			'modes'   => array(
				'traffic'      => self::gantt_traffic( $range, $sources, $columns ),
				'gateways'     => self::gantt_gateways( $range, $statuses, $sources, $columns ),
				'source_sales' => self::gantt_source_sales( $range, $statuses, $sources, $columns ),
			),
		);
	}

	private static function status_distribution( $start, $end, $sources ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$where  = self::order_where( $start, $end, array(), $sources );
		$rows   = $wpdb->get_results( self::maybe_prepare( "SELECT status, COUNT(*) count, COALESCE(SUM(gross_total),0) revenue FROM {$tables['orders']} {$where['sql']} GROUP BY status ORDER BY count DESC", $where['params'] ), ARRAY_A );

		$total_count     = 0;
		$total_revenue   = 0.0;
		$success_count   = 0;
		$success_revenue = 0.0;
		foreach ( $rows as &$row ) {
			$row['label']   = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $row['status'] ) : $row['status'];
			$row['count']   = (int) $row['count'];
			$row['revenue'] = (float) $row['revenue'];
			$total_count   += $row['count'];
			$total_revenue += $row['revenue'];
			if ( in_array( $row['status'], array( 'processing', 'completed' ), true ) ) {
				$success_count   += $row['count'];
				$success_revenue += $row['revenue'];
			}
		}
		unset( $row );

		array_unshift(
			$rows,
			array(
				'status'  => '__success',
				'label'   => 'فروش موفق',
				'count'   => $success_count,
				'revenue' => $success_revenue,
				'special' => true,
			),
		);
		array_unshift(
			$rows,
			array(
				'status'  => '__all',
				'label'   => 'همه',
				'count'   => $total_count,
				'revenue' => $total_revenue,
				'special' => true,
			),
		);
		return $rows;
	}

	private static function confidence_distribution( $start, $end, $statuses, $sources ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$where  = self::order_where( $start, $end, $statuses, $sources );
		$sql    = "SELECT CASE WHEN confidence>=90 THEN 'high' WHEN confidence>=60 THEN 'medium' ELSE 'low' END level, COUNT(*) count
			FROM {$tables['orders']} {$where['sql']} GROUP BY level";
		return $wpdb->get_results( self::maybe_prepare( $sql, $where['params'] ), ARRAY_A );
	}

	private static function recent_orders( $start, $end, $statuses, $sources ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$where  = self::order_where( $start, $end, $statuses, $sources );
		$sql    = "SELECT order_id, created_at, status, gross_total, source_key, source_label, confidence
			FROM {$tables['orders']} {$where['sql']} ORDER BY created_at DESC LIMIT 12";
		$rows   = $wpdb->get_results( self::maybe_prepare( $sql, $where['params'] ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['order_id']    = (int) $row['order_id'];
			$row['gross_total'] = (float) $row['gross_total'];
			$row['confidence']  = (int) $row['confidence'];
			$row['status_label'] = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $row['status'] ) : $row['status'];
			$hpos = class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
			$row['edit_url'] = $hpos ? admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $row['order_id'] ) : admin_url( 'post.php?post=' . $row['order_id'] . '&action=edit' );
		}
		return $rows;
	}


	private static function gateway_summary( $start, $end, $statuses, $sources ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$where  = self::order_where( $start, $end, $statuses, $sources );
		$sql    = "SELECT COALESCE(NULLIF(payment_method,''),'unknown') payment_method,
			MAX(CASE WHEN payment_method_title<>'' THEN payment_method_title ELSE '' END) payment_method_title,
			COUNT(*) orders, COALESCE(SUM(gross_total),0) revenue, COALESCE(AVG(gross_total),0) aov,
			SUM(CASE WHEN status IN ('processing','completed') THEN 1 ELSE 0 END) successful_orders,
			COALESCE(SUM(CASE WHEN status IN ('processing','completed') THEN gross_total ELSE 0 END),0) successful_revenue
			FROM {$tables['orders']} {$where['sql']}
			GROUP BY COALESCE(NULLIF(payment_method,''),'unknown') ORDER BY revenue DESC, orders DESC";
		$rows = $wpdb->get_results( self::maybe_prepare( $sql, $where['params'] ), ARRAY_A );
		$total_revenue = array_sum( array_map( 'floatval', array_column( (array) $rows, 'revenue' ) ) );
		foreach ( (array) $rows as &$row ) {
			$key = sanitize_key( $row['payment_method'] ?: 'unknown' );
			$row['payment_method']       = $key;
			$row['payment_method_title'] = sanitize_text_field( $row['payment_method_title'] ?: ( 'unknown' === $key ? 'بدون درگاه / نامشخص' : $key ) );
			$row['orders']               = (int) $row['orders'];
			$row['revenue']              = (float) $row['revenue'];
			$row['aov']                  = (float) $row['aov'];
			$row['successful_orders']    = (int) $row['successful_orders'];
			$row['successful_revenue']   = (float) $row['successful_revenue'];
			$row['share']                = $total_revenue > 0 ? round( $row['revenue'] / $total_revenue * 100, 1 ) : 0;
		}
		unset( $row );
		return array_values( (array) $rows );
	}

	private static function recent_gateway_orders( $start, $end, $statuses, $sources ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$where  = self::order_where( $start, $end, $statuses, $sources );
		$sql    = "SELECT order_id, created_at, status, gross_total, payment_method, payment_method_title
			FROM {$tables['orders']} {$where['sql']} ORDER BY created_at DESC, order_id DESC LIMIT 30";
		$rows = $wpdb->get_results( self::maybe_prepare( $sql, $where['params'] ), ARRAY_A );
		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		foreach ( (array) $rows as &$row ) {
			$row['order_id']             = (int) $row['order_id'];
			$row['gross_total']          = (float) $row['gross_total'];
			$row['payment_method']       = sanitize_key( $row['payment_method'] ?: 'unknown' );
			$row['payment_method_title'] = sanitize_text_field( $row['payment_method_title'] ?: ( 'unknown' === $row['payment_method'] ? 'بدون درگاه / نامشخص' : $row['payment_method'] ) );
			$row['status_label']         = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $row['status'] ) : $row['status'];
			$row['edit_url']             = $hpos ? admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $row['order_id'] ) : admin_url( 'post.php?post=' . $row['order_id'] . '&action=edit' );
		}
		unset( $row );
		return array_values( (array) $rows );
	}

	private static function report_health( $start, $end, $statuses, $sources ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$total_orders = self::table_exists( $tables['orders'] ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['orders']}" ) : 0;
		$range_where  = self::order_where( $start, $end, array(), $sources );
		$range_count  = self::table_exists( $tables['orders'] ) ? (int) $wpdb->get_var( self::maybe_prepare( "SELECT COUNT(*) FROM {$tables['orders']} {$range_where['sql']}", $range_where['params'] ) ) : 0;
		$filtered_where = self::order_where( $start, $end, $statuses, $sources );
		$filtered_count = self::table_exists( $tables['orders'] ) ? (int) $wpdb->get_var( self::maybe_prepare( "SELECT COUNT(*) FROM {$tables['orders']} {$filtered_where['sql']}", $filtered_where['params'] ) ) : 0;
		$coverage = self::table_exists( $tables['orders'] ) ? $wpdb->get_row( "SELECT MIN(created_at) oldest, MAX(created_at) latest FROM {$tables['orders']}", ARRAY_A ) : array();
		return array(
			'database_orders' => $total_orders,
			'range_orders'    => $range_count,
			'range_start'     => sanitize_text_field( $start ),
			'range_end'       => sanitize_text_field( $end ),
			'filtered_orders' => $filtered_count,
			'oldest_order'    => sanitize_text_field( $coverage['oldest'] ?? '' ),
			'latest_order'    => sanitize_text_field( $coverage['latest'] ?? '' ),
			'has_data'        => $total_orders > 0,
			'filter_hides_data' => $range_count > 0 && 0 === $filtered_count,
			'range_hides_data'  => $total_orders > 0 && 0 === $range_count,
		);
	}


	private static function own_tracking_start() {
		static $resolved = false;
		static $start    = '';
		if ( $resolved ) {
			return $start;
		}
		$resolved = true;
		global $wpdb;
		$tables = MM_Installer::tables();
		if ( self::table_exists( $tables['visits'] ) ) {
			$start = sanitize_text_field( (string) $wpdb->get_var( "SELECT MIN(visited_at) FROM {$tables['visits']}" ) );
		}
		return $start;
	}

	private static function woocommerce_scan_statuses() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}
		return array_values( array_map( 'sanitize_key', array_keys( wc_get_order_statuses() ) ) );
	}

	private static function woocommerce_order_count( $cutoff_timestamp = 0 ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}
		try {
			$args = array(
					'type'     => 'shop_order',
					'status'   => self::woocommerce_scan_statuses(),
					'limit'    => 1,
					'page'     => 1,
					'paginate' => true,
					'return'   => 'ids',
				);
			if ( $cutoff_timestamp ) {
				$args['date_created'] = '<=' . absint( $cutoff_timestamp );
			}
			$result = wc_get_orders( $args );
			return isset( $result->total ) ? (int) $result->total : 0;
		} catch ( Throwable $e ) {
			return new WP_Error( 'mm_order_count_failed', 'شمارش سفارش‌های ووکامرس ناموفق بود: ' . $e->getMessage() );
		}
	}

	private static function product_views_total( $start, $end ) {
		global $wpdb;
		$table = $wpdb->prefix . 'kkpa_product_views';
		if ( array_diff( array( 'view_date', 'views' ), self::table_columns( $table ) ) ) {
			return 0;
		}
		$split_at  = self::product_tracking_split_start();
		$sql       = "SELECT COALESCE(SUM(views),0) FROM {$table} WHERE view_date BETWEEN %s AND %s";
		$params    = array( substr( $start, 0, 10 ), substr( $end, 0, 10 ) );
		if ( $split_at ) {
			$sql      .= ' AND view_date < %s';
			$params[] = substr( $split_at, 0, 10 );
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	private static function product_views_summary( $start, $end, $sources = array() ) {
		global $wpdb;
		$tables    = MM_Installer::tables();
		$split_at  = $sources ? '' : self::product_tracking_split_start();
		$own_first = self::own_tracking_start();
		$own_start = $split_at ?: ( $own_first && $start < $own_first ? $own_first : $start );
		$own_start = $own_start && $start < $own_start ? $own_start : $start;
		$count     = 0;
		if ( $own_start <= $end ) {
			$sql    = "SELECT COUNT(*) FROM {$tables['visits']} WHERE page_type='product' AND visited_at BETWEEN %s AND %s";
			$params = array( $own_start, $end );
			if ( $sources ) {
				$sql .= ' AND source_key IN (' . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')';
				$params = array_merge( $params, $sources );
			}
			$count = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		}
		return $count + ( $sources ? 0 : self::product_views_total( $start, $end ) );
	}

	private static function product_tracking_split_start() {
		global $wpdb;
		$legacy = $wpdb->prefix . 'kkpa_product_views';
		if ( array_diff( array( 'product_id', 'view_date', 'views' ), self::table_columns( $legacy ) ) ) {
			return '';
		}
		$own_first = self::own_tracking_start();
		if ( ! $own_first ) {
			return '';
		}
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $own_first, wp_timezone() );
		return $date ? $date->modify( 'tomorrow' )->setTime( 0, 0, 0 )->format( 'Y-m-d H:i:s' ) : '';
	}

	public static function product_source_breakdown( $product_id, array $args ) {
		self::assert_storage_ready();
		global $wpdb;
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return array( 'product_id' => 0, 'total' => 0, 'orders' => 0, 'revenue' => 0, 'rows' => array() );
		}

		$tables    = MM_Installer::tables();
		$range     = self::range( $args );
		$statuses  = self::sanitize_statuses( $args['statuses'] ?? array() );
		$sources   = array_values( array_filter( array_map( 'sanitize_key', (array) ( $args['sources'] ?? array() ) ) ) );
		$own_first = self::own_tracking_start();
		$split_at  = $sources ? '' : self::product_tracking_split_start();
		$own_start = $split_at ?: ( $own_first && $range['start'] < $own_first ? $own_first : $range['start'] );
		$own_start = $range['start'] < $own_start ? $own_start : $range['start'];
		$map       = array();
		$empty_row = static function ( $key, $label ) {
			return array(
				'key' => $key, 'label' => $label, 'views' => 0, 'visitors' => 0,
				'orders' => 0, 'quantity' => 0.0, 'revenue' => 0.0,
			);
		};

		if ( $own_start <= $range['end'] ) {
			$sql    = "SELECT source_key, MAX(source_label) source_label, COUNT(*) views, COUNT(DISTINCT visitor_id) visitors
				FROM {$tables['visits']} WHERE page_type='product' AND object_id=%d AND visited_at BETWEEN %s AND %s
				AND source_key NOT IN ('internal','test')";
			$params = array( $product_id, $own_start, $range['end'] );
			if ( $sources ) {
				$sql .= ' AND source_key IN (' . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')';
				$params = array_merge( $params, $sources );
			}
			$sql      .= ' GROUP BY source_key ORDER BY views DESC';
			$own_rows  = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
			foreach ( $own_rows as $row ) {
				$key = sanitize_key( $row['source_key'] ?: 'direct' );
				$map[ $key ] = $empty_row( $key, sanitize_text_field( $row['source_label'] ?: self::source_label( $key ) ) );
				$map[ $key ]['views']    = (int) $row['views'];
				$map[ $key ]['visitors'] = (int) $row['visitors'];
			}
		}

		$order_where = self::order_where( $range['start'], $range['end'], $statuses, $sources, 'o' );
		$sales_sql   = "SELECT o.source_key, MAX(o.source_label) source_label,
			COUNT(DISTINCT o.order_id) orders, SUM(i.quantity) quantity, SUM(i.line_total) revenue
			FROM {$tables['items']} i INNER JOIN {$tables['orders']} o ON o.order_id=i.order_id
			{$order_where['sql']} AND i.product_id=%d GROUP BY o.source_key";
		$sales_rows  = $wpdb->get_results(
			self::maybe_prepare( $sales_sql, array_merge( $order_where['params'], array( $product_id ) ) ),
			ARRAY_A
		);
		foreach ( (array) $sales_rows as $row ) {
			$key = sanitize_key( $row['source_key'] ?: 'unknown' );
			if ( ! isset( $map[ $key ] ) ) {
				$map[ $key ] = $empty_row( $key, sanitize_text_field( $row['source_label'] ?: self::source_label( $key ) ) );
			}
			$map[ $key ]['orders']   = (int) $row['orders'];
			$map[ $key ]['quantity'] = (float) $row['quantity'];
			$map[ $key ]['revenue']  = (float) $row['revenue'];
		}

		$own_total    = array_sum( array_column( $map, 'views' ) );
		$reported     = self::product_views_for( $product_id, $range['start'], $range['end'], $sources );
		$legacy_total = $sources ? 0 : max( 0, $reported - $own_total );
		$legacy       = self::legacy_traffic_table();

		$legacy_columns = self::table_columns( $legacy );
		if ( $legacy_total > 0 && in_array( 'visitor_id', $legacy_columns, true ) && in_array( 'visited_at', $legacy_columns, true ) ) {
			$split_dt       = $split_at ? DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $split_at, wp_timezone() ) : false;
			$legacy_cutoff  = $split_dt ? $split_dt->modify( '-1 second' )->format( 'Y-m-d H:i:s' ) : $range['end'];
			$legacy_end     = $range['end'] < $legacy_cutoff ? $range['end'] : $legacy_cutoff;
			$permalink  = get_permalink( $product_id );
			$path       = $permalink ? (string) wp_parse_url( $permalink, PHP_URL_PATH ) : '';
			$path_alt   = '/' === $path ? $path : ( '/' === substr( $path, -1 ) ? untrailingslashit( $path ) : trailingslashit( $path ) );
			$conditions = array();
			$params     = array( $range['start'], $legacy_end );
			if ( $path && in_array( 'landing_path', $legacy_columns, true ) ) {
				$conditions[] = 'landing_path IN (%s,%s)';
				$params[] = $path;
				$params[] = $path_alt;
			}
			if ( $permalink && in_array( 'page_url', $legacy_columns, true ) ) {
				$conditions[] = 'page_url LIKE %s';
				$params[] = $wpdb->esc_like( $permalink ) . '%';
			}
			if ( $conditions && $range['start'] <= $legacy_end ) {
				$source_expr = in_array( 'source_key', $legacy_columns, true )
					? "CASE WHEN source_key NOT IN ('internal','test','') THEN source_key ELSE 'direct' END"
					: "'direct'";
				$legacy_sql = "SELECT {$source_expr} source_key, COUNT(*) tracked_views,
					COUNT(DISTINCT visitor_id) visitors FROM {$legacy}
					WHERE visited_at BETWEEN %s AND %s AND (" . implode( ' OR ', $conditions ) . ")
					GROUP BY source_key ORDER BY tracked_views DESC";
				$legacy_rows = $wpdb->get_results( $wpdb->prepare( $legacy_sql, $params ), ARRAY_A );
				$tracked_total = array_sum( array_map( 'intval', array_column( $legacy_rows, 'tracked_views' ) ) );
				$remaining     = $legacy_total;
				foreach ( $legacy_rows as $index => $row ) {
					$key       = sanitize_key( $row['source_key'] ?: 'direct' );
					$allocated = count( $legacy_rows ) - 1 === $index
						? $remaining
						: min( $remaining, (int) round( $legacy_total * ( (int) $row['tracked_views'] / max( 1, $tracked_total ) ) ) );
					$remaining -= $allocated;
					if ( ! isset( $map[ $key ] ) ) {
						$map[ $key ] = $empty_row( $key, self::source_label( $key ) );
					}
					$map[ $key ]['views']    += $allocated;
					$map[ $key ]['visitors'] += (int) $row['visitors'];
				}
			}
		}

		$identified = array_sum( array_column( $map, 'views' ) );
		if ( $reported > $identified ) {
			if ( ! isset( $map['unknown'] ) ) {
				$map['unknown'] = $empty_row( 'unknown', self::source_label( 'unknown' ) );
			}
			$map['unknown']['views'] += $reported - $identified;
		}
		$total         = array_sum( array_column( $map, 'views' ) );
		$total_orders  = array_sum( array_column( $map, 'orders' ) );
		$total_revenue = array_sum( array_column( $map, 'revenue' ) );
		foreach ( $map as &$row ) {
			$row['share']         = $total > 0 ? round( $row['views'] / $total * 100, 1 ) : 0;
			$row['revenue_share'] = $total_revenue > 0 ? round( $row['revenue'] / $total_revenue * 100, 1 ) : 0;
		}
		unset( $row );
		usort( $map, static function ( $a, $b ) { return $b['revenue'] <=> $a['revenue'] ?: $b['views'] <=> $a['views']; } );

		return array(
			'product_id'   => $product_id,
			'product_name' => get_the_title( $product_id ),
			'total'        => $total,
			'orders'       => $total_orders,
			'revenue'      => $total_revenue,
			'range'        => $range,
			'rows'         => array_values( $map ),
		);
	}

	private static function product_views_map( $product_ids, $start, $end, $sources = array() ) {
		global $wpdb;
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $product_ids ) ) ) );
		if ( ! $product_ids ) {
			return array();
		}

		$tables    = MM_Installer::tables();
		$own_first = self::own_tracking_start();
		$split_at  = $sources ? '' : self::product_tracking_split_start();
		$own_start = $split_at ?: ( $own_first && $start < $own_first ? $own_first : $start );
		$own_start = $start < $own_start ? $own_start : $start;
		$counts    = array_fill_keys( $product_ids, 0 );

		if ( $own_start <= $end ) {
			$sql    = "SELECT object_id product_id, COUNT(*) views FROM {$tables['visits']}
				WHERE page_type='product' AND object_id IN (" . implode( ',', array_fill( 0, count( $product_ids ), '%d' ) ) . ")
				AND visited_at BETWEEN %s AND %s";
			$params = array_merge( $product_ids, array( $own_start, $end ) );
			if ( $sources ) {
				$sql .= ' AND source_key IN (' . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')';
				$params = array_merge( $params, $sources );
			}
			$sql .= ' GROUP BY object_id';
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) as $row ) {
				$counts[ (int) $row['product_id'] ] = (int) $row['views'];
			}
		}

		$legacy = $wpdb->prefix . 'kkpa_product_views';
		$legacy_columns = self::table_columns( $legacy );
		$required = array( 'product_id', 'view_date', 'views' );
		if ( ! $sources && ! array_diff( $required, $legacy_columns ) ) {
			$legacy_sql = "SELECT product_id, COALESCE(SUM(views),0) views FROM {$legacy}
				WHERE product_id IN (" . implode( ',', array_fill( 0, count( $product_ids ), '%d' ) ) . ")
				AND view_date BETWEEN %s AND %s";
			$legacy_params = array_merge( $product_ids, array( substr( $start, 0, 10 ), substr( $end, 0, 10 ) ) );
			if ( $split_at ) {
				$legacy_sql      .= ' AND view_date < %s';
				$legacy_params[] = substr( $split_at, 0, 10 );
			}
			$legacy_sql .= ' GROUP BY product_id';
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( $legacy_sql, $legacy_params ), ARRAY_A ) as $row ) {
				$product_id = (int) $row['product_id'];
				$counts[ $product_id ] = (int) ( $counts[ $product_id ] ?? 0 ) + (int) $row['views'];
			}
		}
		return $counts;
	}

	private static function product_views_for( $product_id, $start, $end, $sources = array() ) {
		$counts = self::product_views_map( array( $product_id ), $start, $end, $sources );
		return (int) ( $counts[ absint( $product_id ) ] ?? 0 );
	}

	private static function legacy_traffic_table() {
		global $wpdb;
		static $resolved = null;
		if ( null !== $resolved ) {
			return $resolved;
		}
		$candidates = array(
			$wpdb->prefix . 'koodakkhallagh_traffic_visits',
			$wpdb->prefix . 'traffic_visits',
			$wpdb->prefix . 'site_traffic_visits',
		);
		$like = $wpdb->esc_like( $wpdb->prefix ) . '%traffic_visits';
		$found = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		foreach ( $found as $table ) {
			if ( ! in_array( $table, $candidates, true ) ) {
				$candidates[] = $table;
			}
		}
		foreach ( array_unique( $candidates ) as $table ) {
			if ( ! self::table_exists( $table ) ) {
				continue;
			}
			$columns = self::table_columns( $table );
			if ( ! array_diff( array( 'visitor_id', 'visited_at' ), $columns ) ) {
				$resolved = $table;
				return $resolved;
			}
		}
		$resolved = '';
		return $resolved;
	}

	private static function traffic_summary_exact( $start, $end, $sources = array() ) {
		global $wpdb;
		$tables  = MM_Installer::tables();
		$sources = array_values( array_filter( array_map( 'sanitize_key', (array) $sources ) ) );
		$result  = array( 'entries' => 0, 'visitors' => 0, 'sessions' => 0, 'pageviews' => 0, 'product_views' => 0 );

		if ( self::table_exists( $tables['visits'] ) ) {
			/*
			 * v1.4.1 stores acquisition entries explicitly. Counting the entry row
			 * avoids reconstructing sessions differently for each report/range and
			 * makes 24h, 7d, 30d and custom ranges use the same raw event definition.
			 */
			$sql = "SELECT COUNT(*) entries, COUNT(DISTINCT visitor_id) visitors, COUNT(DISTINCT session_id) sessions
				FROM {$tables['visits']} WHERE is_entry=1 AND visited_at BETWEEN %s AND %s
				AND source_key NOT IN ('internal','test')";
			$params = array( $start, $end );
			if ( $sources ) {
				$sql .= ' AND source_key IN (' . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')';
				$params = array_merge( $params, $sources );
			}
			$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );
			$result['entries']  += (int) ( $row['entries'] ?? 0 );
			$result['visitors'] += (int) ( $row['visitors'] ?? 0 );
			$result['sessions'] += (int) ( $row['sessions'] ?? 0 );

			$page_sql = "SELECT COUNT(*) pageviews, COALESCE(SUM(CASE WHEN page_type='product' THEN 1 ELSE 0 END),0) product_views
				FROM {$tables['visits']} WHERE visited_at BETWEEN %s AND %s AND source_key NOT IN ('internal','test')";
			$page_params = array( $start, $end );
			if ( $sources ) {
				$page_sql .= ' AND source_key IN (' . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')';
				$page_params = array_merge( $page_params, $sources );
			}
			$page_row = $wpdb->get_row( $wpdb->prepare( $page_sql, $page_params ), ARRAY_A );
			$result['pageviews']     += (int) ( $page_row['pageviews'] ?? 0 );
			$result['product_views'] += (int) ( $page_row['product_views'] ?? 0 );
		}

		$legacy = self::legacy_traffic_table();
		$legacy_range = self::legacy_traffic_range( $start, $end );
		if ( $legacy && $legacy_range ) {
			$columns = self::table_columns( $legacy );
			$source_expr = in_array( 'source_key', $columns, true ) ? "COALESCE(NULLIF(source_key,''),'direct')" : "'direct'";
			$session_expr = in_array( 'session_id', $columns, true ) ? 'session_id' : "CONCAT(visitor_id,'|',DATE(visited_at))";
			$product_expr = in_array( 'page_type', $columns, true ) ? "CASE WHEN page_type='product' THEN 1 ELSE 0 END" : '0';
			$sql = "SELECT COUNT(DISTINCT {$session_expr}) entries, COUNT(DISTINCT visitor_id) visitors, COUNT(DISTINCT {$session_expr}) sessions, COUNT(*) pageviews,
				COALESCE(SUM({$product_expr}),0) product_views FROM {$legacy} WHERE visited_at BETWEEN %s AND %s";
			$params = $legacy_range;
			if ( in_array( 'source_key', $columns, true ) ) {
				$sql .= " AND {$source_expr} NOT IN ('internal','test')";
				if ( $sources ) {
					$sql .= " AND {$source_expr} IN (" . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')';
					$params = array_merge( $params, $sources );
				}
			} elseif ( $sources && ! in_array( 'direct', $sources, true ) ) {
				return $result;
			}
			$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );
			foreach ( $result as $key => $unused ) {
				$result[ $key ] += (int) ( $row[ $key ] ?? 0 );
			}
		}
		return $result;
	}

	private static function traffic_source_metrics_exact( $start, $end, $sources = array() ) {
		global $wpdb;
		$tables  = MM_Installer::tables();
		$sources = array_values( array_filter( array_map( 'sanitize_key', (array) $sources ) ) );
		$map     = array();
		$merge = static function ( &$map, $rows ) {
			foreach ( (array) $rows as $row ) {
				$key = sanitize_key( $row['source_key'] ?: 'direct' );
				if ( ! isset( $map[ $key ] ) ) {
					$map[ $key ] = array( 'key' => $key, 'label' => sanitize_text_field( $row['source_label'] ?: MM_Repository::source_label( $key ) ), 'entries' => 0, 'visitors' => 0, 'sessions' => 0, 'pageviews' => 0, 'product_views' => 0 );
				}
				$map[ $key ]['entries'] += (int) ( $row['entries'] ?? $row['sessions'] ?? 0 );
				$map[ $key ]['visitors'] += (int) ( $row['visitors'] ?? 0 );
				$map[ $key ]['sessions'] += (int) ( $row['sessions'] ?? 0 );
				$map[ $key ]['pageviews'] += (int) ( $row['pageviews'] ?? 0 );
				$map[ $key ]['product_views'] += (int) ( $row['product_views'] ?? 0 );
				if ( ! empty( $row['source_label'] ) ) $map[ $key ]['label'] = sanitize_text_field( $row['source_label'] );
			}
		};

		if ( self::table_exists( $tables['visits'] ) ) {
			$where = "visited_at BETWEEN %s AND %s AND source_key NOT IN ('internal','test')";
			$params = array( $start, $end );
			if ( $sources ) {
				$where .= ' AND source_key IN (' . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')';
				$params = array_merge( $params, $sources );
			}
			$entry_sql = "SELECT source_key, MAX(source_label) source_label, COUNT(*) entries,
				COUNT(DISTINCT visitor_id) visitors, COUNT(DISTINCT session_id) sessions, 0 pageviews, 0 product_views
				FROM {$tables['visits']} WHERE is_entry=1 AND {$where} GROUP BY source_key";
			$merge( $map, $wpdb->get_results( $wpdb->prepare( $entry_sql, $params ), ARRAY_A ) );

			$page_sql = "SELECT source_key, MAX(source_label) source_label, 0 entries, 0 visitors, 0 sessions,
				COUNT(*) pageviews, COALESCE(SUM(CASE WHEN page_type='product' THEN 1 ELSE 0 END),0) product_views
				FROM {$tables['visits']} WHERE {$where} GROUP BY source_key";
			$merge( $map, $wpdb->get_results( $wpdb->prepare( $page_sql, $params ), ARRAY_A ) );
		}

		$legacy = self::legacy_traffic_table();
		$legacy_range = self::legacy_traffic_range( $start, $end );
		if ( $legacy && $legacy_range ) {
			$columns = self::table_columns( $legacy );
			$source_expr = in_array( 'source_key', $columns, true ) ? "COALESCE(NULLIF(source_key,''),'direct')" : "'direct'";
			$label_expr = in_array( 'source_label', $columns, true ) ? 'MAX(COALESCE(source_label,\'\'))' : "''";
			$session_expr = in_array( 'session_id', $columns, true ) ? 'session_id' : "CONCAT(visitor_id,'|',DATE(visited_at))";
			$product_expr = in_array( 'page_type', $columns, true ) ? "CASE WHEN page_type='product' THEN 1 ELSE 0 END" : '0';
			$sql = "SELECT {$source_expr} source_key, {$label_expr} source_label, COUNT(DISTINCT {$session_expr}) entries, COUNT(DISTINCT visitor_id) visitors,
				COUNT(DISTINCT {$session_expr}) sessions, COUNT(*) pageviews, COALESCE(SUM({$product_expr}),0) product_views
				FROM {$legacy} WHERE visited_at BETWEEN %s AND %s";
			$params = $legacy_range;
			if ( in_array( 'source_key', $columns, true ) ) {
				$sql .= " AND {$source_expr} NOT IN ('internal','test')";
				if ( $sources ) { $sql .= " AND {$source_expr} IN (" . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')'; $params = array_merge( $params, $sources ); }
			} elseif ( $sources && ! in_array( 'direct', $sources, true ) ) {
				return $map;
			}
			$sql .= " GROUP BY {$source_expr}";
			$merge( $map, $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) );
		}
		return $map;
	}

	private static function traffic_timeline_exact( $start, $end, $sources = array() ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$sources = array_values( array_filter( array_map( 'sanitize_key', (array) $sources ) ) );
		$map = array();
		$merge = static function ( &$map, $rows ) {
			foreach ( (array) $rows as $row ) {
				$day = sanitize_text_field( $row['day'] );
				if ( ! isset( $map[ $day ] ) ) $map[ $day ] = array( 'day' => $day, 'entries' => 0, 'visitors' => 0, 'pageviews' => 0 );
				$map[ $day ]['entries'] += (int) ( $row['entries'] ?? 0 );
				$map[ $day ]['visitors'] += (int) ( $row['visitors'] ?? 0 );
				$map[ $day ]['pageviews'] += (int) ( $row['pageviews'] ?? 0 );
			}
		};
		if ( self::table_exists( $tables['visits'] ) ) {
			$where = "visited_at BETWEEN %s AND %s AND source_key NOT IN ('internal','test')";
			$params = array( $start, $end );
			if ( $sources ) { $where .= ' AND source_key IN (' . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')'; $params = array_merge( $params, $sources ); }
			$sql = "SELECT DATE(visited_at) day,
				SUM(CASE WHEN is_entry=1 THEN 1 ELSE 0 END) entries,
				COUNT(DISTINCT CASE WHEN is_entry=1 THEN visitor_id ELSE NULL END) visitors,
				COUNT(*) pageviews FROM {$tables['visits']} WHERE {$where} GROUP BY DATE(visited_at)";
			$merge( $map, $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) );
		}
		$legacy = self::legacy_traffic_table();
		$legacy_range = self::legacy_traffic_range( $start, $end );
		if ( $legacy && $legacy_range ) {
			$columns = self::table_columns( $legacy );
			$source_expr = in_array( 'source_key', $columns, true ) ? "COALESCE(NULLIF(source_key,''),'direct')" : "'direct'";
			$session_expr = in_array( 'session_id', $columns, true ) ? 'session_id' : "CONCAT(visitor_id,'|',DATE(visited_at))";
			$sql = "SELECT DATE(visited_at) day, COUNT(DISTINCT {$session_expr}) entries, COUNT(DISTINCT visitor_id) visitors, COUNT(*) pageviews FROM {$legacy} WHERE visited_at BETWEEN %s AND %s";
			$params = $legacy_range;
			if ( in_array( 'source_key', $columns, true ) ) {
				$sql .= " AND {$source_expr} NOT IN ('internal','test')";
				if ( $sources ) { $sql .= " AND {$source_expr} IN (" . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')'; $params = array_merge( $params, $sources ); }
			} elseif ( $sources && ! in_array( 'direct', $sources, true ) ) {
				ksort( $map ); return array_values( $map );
			}
			$sql .= ' GROUP BY DATE(visited_at)';
			$merge( $map, $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) );
		}
		ksort( $map );
		return array_values( $map );
	}

	private static function legacy_traffic_range( $start, $end ) {
		$legacy = self::legacy_traffic_table();
		if ( ! $legacy ) {
			return false;
		}
		$legacy_end = $end;
		$own_first = self::own_tracking_start();
		if ( $own_first && $own_first <= $legacy_end ) {
			$date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $own_first, wp_timezone() );
			if ( $date ) {
				$legacy_end = $date->modify( '-1 second' )->format( 'Y-m-d H:i:s' );
			}
		}
		return $start <= $legacy_end ? array( $start, $legacy_end ) : false;
	}

	private static function traffic_coverage() {
		global $wpdb;
		$tables = MM_Installer::tables();
		$starts = array();
		$ends   = array();
		$entry_starts = array();
		$entry_ends   = array();
		$total_visits = 0;
		$total_entries = 0;
		$transports = array();
		if ( self::table_exists( $tables['visits'] ) ) {
			$row = $wpdb->get_row( "SELECT MIN(visited_at) oldest, MAX(visited_at) latest, COUNT(*) total_visits,
				MIN(CASE WHEN is_entry=1 THEN visited_at ELSE NULL END) oldest_entry,
				MAX(CASE WHEN is_entry=1 THEN visited_at ELSE NULL END) latest_entry,
				SUM(CASE WHEN is_entry=1 THEN 1 ELSE 0 END) total_entries
				FROM {$tables['visits']} WHERE source_key NOT IN ('internal','test')", ARRAY_A );
			if ( ! empty( $row['oldest'] ) ) $starts[] = $row['oldest'];
			if ( ! empty( $row['latest'] ) ) $ends[] = $row['latest'];
			if ( ! empty( $row['oldest_entry'] ) ) $entry_starts[] = $row['oldest_entry'];
			if ( ! empty( $row['latest_entry'] ) ) $entry_ends[] = $row['latest_entry'];
			$total_visits += (int) ( $row['total_visits'] ?? 0 );
			$total_entries += (int) ( $row['total_entries'] ?? 0 );
			$transports = (array) $wpdb->get_results( "SELECT COALESCE(NULLIF(transport,''),'legacy') transport, COUNT(*) hits FROM {$tables['visits']} GROUP BY transport ORDER BY hits DESC", ARRAY_A );
		}
		$legacy = self::legacy_traffic_table();
		if ( $legacy ) {
			$columns = self::table_columns( $legacy );
			$where = in_array( 'source_key', $columns, true ) ? " WHERE COALESCE(source_key,'') NOT IN ('internal','test')" : '';
			$row = $wpdb->get_row( "SELECT MIN(visited_at) oldest, MAX(visited_at) latest, COUNT(*) total_visits FROM {$legacy}{$where}", ARRAY_A );
			if ( ! empty( $row['oldest'] ) ) { $starts[] = $row['oldest']; $entry_starts[] = $row['oldest']; }
			if ( ! empty( $row['latest'] ) ) { $ends[] = $row['latest']; $entry_ends[] = $row['latest']; }
			$total_visits += (int) ( $row['total_visits'] ?? 0 );
		}
		sort( $starts ); rsort( $ends ); sort( $entry_starts ); rsort( $entry_ends );
		$latest = $entry_ends ? $entry_ends[0] : ( $ends ? $ends[0] : '' );
		$freshness = null;
		if ( $latest ) {
			$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $latest, wp_timezone() );
			if ( $dt ) $freshness = max( 0, (int) floor( ( current_datetime()->getTimestamp() - $dt->getTimestamp() ) / 60 ) );
		}
		return array(
			'start' => $starts ? sanitize_text_field( $starts[0] ) : '',
			'end' => $ends ? sanitize_text_field( $ends[0] ) : '',
			'entry_start' => $entry_starts ? sanitize_text_field( $entry_starts[0] ) : '',
			'entry_end' => $entry_ends ? sanitize_text_field( $entry_ends[0] ) : '',
			'freshness_minutes' => $freshness,
			'total_visits' => $total_visits,
			'total_entries' => $total_entries,
			'transports' => $transports,
			'legacy_table' => $legacy ? sanitize_text_field( $legacy ) : '',
		);
	}

	private static function gantt_columns( $range ) {
		$tz = wp_timezone();
		$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $range['start'], $tz );
		$end   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $range['end'], $tz );
		if ( ! $start || ! $end ) {
			$start = current_datetime()->modify( '-6 days' );
			$end = current_datetime();
		}
		$hourly = '24h' === ( $range['key'] ?? '' );
		$keys = array();
		if ( $hourly ) {
			$cursor = $start->setTime( (int) $start->format( 'H' ), 0, 0 );
			$limit = 0;
			while ( $cursor <= $end && $limit < 30 ) {
				$keys[] = $cursor->format( 'Y-m-d H:00:00' );
				$cursor = $cursor->modify( '+1 hour' );
				$limit++;
			}
		} else {
			$cursor = $start->setTime( 0, 0, 0 );
			$finish = $end->setTime( 0, 0, 0 );
			while ( $cursor <= $finish ) {
				$keys[] = $cursor->format( 'Y-m-d' );
				$cursor = $cursor->modify( '+1 day' );
			}
			if ( count( $keys ) > 60 ) {
				$keys = array_slice( $keys, -60 );
			}
		}
		return array( 'keys' => $keys, 'granularity' => $hourly ? 'hour' : 'day', 'truncated' => ! $hourly && count( $keys ) >= 60 );
	}

	private static function gantt_traffic( $range, $sources, $columns ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$sources = array_values( array_filter( array_map( 'sanitize_key', (array) $sources ) ) );
		// Hourly buckets go through $wpdb->prepare(), so DATE_FORMAT's % must be escaped (v1.4.2: the 24h Gantt was always empty).
		$bucket_expr = 'hour' === $columns['granularity'] ? "DATE_FORMAT(visited_at,'%%Y-%%m-%%d %%H:00:00')" : 'DATE(visited_at)';
		$map = array();
		$merge = static function ( &$map, $rows ) {
			foreach ( (array) $rows as $row ) {
				$key = sanitize_key( $row['source_key'] ?: 'direct' );
				if ( ! isset( $map[ $key ] ) ) $map[ $key ] = array( 'key' => $key, 'label' => sanitize_text_field( $row['source_label'] ?: MM_Repository::source_label( $key ) ), 'values' => array(), 'total' => 0 );
				$bucket = sanitize_text_field( $row['bucket'] );
				$value = (int) ( $row['entries'] ?? $row['sessions'] ?? 0 );
				$map[ $key ]['values'][ $bucket ] = (int) ( $map[ $key ]['values'][ $bucket ] ?? 0 ) + $value;
				$map[ $key ]['total'] += $value;
			}
		};
		if ( self::table_exists( $tables['visits'] ) ) {
			$entry_bucket = 'hour' === $columns['granularity'] ? "DATE_FORMAT(visited_at,'%%Y-%%m-%%d %%H:00:00')" : 'DATE(visited_at)';
			$sql = "SELECT {$entry_bucket} bucket, source_key, MAX(source_label) source_label, COUNT(*) entries
				FROM {$tables['visits']} WHERE is_entry=1 AND visited_at BETWEEN %s AND %s
				AND source_key NOT IN ('internal','test')";
			$params = array( $range['start'], $range['end'] );
			if ( $sources ) { $sql .= ' AND source_key IN (' . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')'; $params = array_merge( $params, $sources ); }
			$sql .= " GROUP BY {$entry_bucket}, source_key";
			$merge( $map, $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) );
		}
		$legacy = self::legacy_traffic_table();
		$legacy_range = self::legacy_traffic_range( $range['start'], $range['end'] );
		if ( $legacy && $legacy_range ) {
			$columns_legacy = self::table_columns( $legacy );
			$source_expr = in_array( 'source_key', $columns_legacy, true ) ? "COALESCE(NULLIF(source_key,''),'direct')" : "'direct'";
			$label_expr = in_array( 'source_label', $columns_legacy, true ) ? 'MAX(COALESCE(source_label,\'\'))' : "''";
			$session_expr = in_array( 'session_id', $columns_legacy, true ) ? 'session_id' : "CONCAT(visitor_id,'|',DATE(visited_at))";
			$sql = "SELECT {$bucket_expr} bucket, {$source_expr} source_key, {$label_expr} source_label, COUNT(DISTINCT {$session_expr}) entries FROM {$legacy} WHERE visited_at BETWEEN %s AND %s";
			$params = $legacy_range;
			if ( in_array( 'source_key', $columns_legacy, true ) ) {
				$sql .= " AND {$source_expr} NOT IN ('internal','test')";
				if ( $sources ) { $sql .= " AND {$source_expr} IN (" . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')'; $params = array_merge( $params, $sources ); }
			} elseif ( $sources && ! in_array( 'direct', $sources, true ) ) {
				$params = array(); $sql = '';
			}
			if ( $sql ) { $sql .= " GROUP BY {$bucket_expr}, {$source_expr}"; $merge( $map, $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ); }
		}
		$rows = array_values( $map );
		usort( $rows, static function ( $a, $b ) { return $b['total'] <=> $a['total']; } );
		$max = 1; foreach ( $rows as $row ) foreach ( $row['values'] as $value ) $max = max( $max, (int) $value );
		return array( 'keys' => $columns['keys'], 'granularity' => $columns['granularity'], 'rows' => $rows, 'max' => $max, 'unit' => 'entries', 'row_label' => 'منبع ورودی', 'empty' => 'برای ورودی‌های سایت در این بازه داده‌ای ثبت نشده است.' );
	}

	private static function gantt_gateways( $range, $statuses, $sources, $columns ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$where = self::order_where( $range['start'], $range['end'], $statuses, $sources );
		$bucket = 'hour' === $columns['granularity'] ? "DATE_FORMAT(created_at,'%%Y-%%m-%%d %%H:00:00')" : 'DATE(created_at)';
		$sql = "SELECT {$bucket} bucket, COALESCE(NULLIF(payment_method,''),'unknown') row_key,
			MAX(COALESCE(NULLIF(payment_method_title,''),payment_method,'نامشخص')) row_label,
			COUNT(*) orders, COALESCE(SUM(gross_total),0) value FROM {$tables['orders']} {$where['sql']}
			GROUP BY {$bucket}, payment_method ORDER BY bucket";
		$data = $wpdb->get_results( self::maybe_prepare( $sql, $where['params'] ), ARRAY_A );
		$rows = array();
		foreach ( $data as $item ) {
			$key = sanitize_key( $item['row_key'] ?: 'unknown' );
			if ( ! isset( $rows[ $key ] ) ) $rows[ $key ] = array( 'key' => $key, 'label' => sanitize_text_field( $item['row_label'] ?: $key ), 'values' => array(), 'counts' => array(), 'total' => 0 );
			$rows[ $key ]['values'][ $item['bucket'] ] = (float) $item['value'];
			$rows[ $key ]['counts'][ $item['bucket'] ] = (int) $item['orders'];
			$rows[ $key ]['total'] += (float) $item['value'];
		}
		$rows = array_values( $rows ); usort( $rows, static function ( $a, $b ) { return $b['total'] <=> $a['total']; } );
		$max = 1; foreach ( $rows as $row ) foreach ( $row['values'] as $value ) $max = max( $max, (float) $value );
		return array( 'keys' => $columns['keys'], 'granularity' => $columns['granularity'], 'rows' => $rows, 'max' => $max, 'unit' => 'money', 'row_label' => 'درگاه پرداخت', 'empty' => 'در بازه انتخابی سفارشی روی درگاه‌های پرداخت ثبت نشده است.' );
	}

	private static function gantt_source_sales( $range, $statuses, $sources, $columns ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$where = self::order_where( $range['start'], $range['end'], $statuses, $sources );
		$bucket = 'hour' === $columns['granularity'] ? "DATE_FORMAT(created_at,'%%Y-%%m-%%d %%H:00:00')" : 'DATE(created_at)';
		$sql = "SELECT {$bucket} bucket, source_key row_key, MAX(source_label) row_label,
			COUNT(*) orders, COALESCE(SUM(gross_total),0) value FROM {$tables['orders']} {$where['sql']}
			GROUP BY {$bucket}, source_key ORDER BY bucket";
		$data = $wpdb->get_results( self::maybe_prepare( $sql, $where['params'] ), ARRAY_A );
		$rows = array();
		foreach ( $data as $item ) {
			$key = sanitize_key( $item['row_key'] ?: 'unknown' );
			if ( ! isset( $rows[ $key ] ) ) $rows[ $key ] = array( 'key' => $key, 'label' => sanitize_text_field( $item['row_label'] ?: self::source_label( $key ) ), 'values' => array(), 'counts' => array(), 'total' => 0 );
			$rows[ $key ]['values'][ $item['bucket'] ] = (float) $item['value'];
			$rows[ $key ]['counts'][ $item['bucket'] ] = (int) $item['orders'];
			$rows[ $key ]['total'] += (float) $item['value'];
		}
		$rows = array_values( $rows ); usort( $rows, static function ( $a, $b ) { return $b['total'] <=> $a['total']; } );
		$max = 1; foreach ( $rows as $row ) foreach ( $row['values'] as $value ) $max = max( $max, (float) $value );
		return array( 'keys' => $columns['keys'], 'granularity' => $columns['granularity'], 'rows' => $rows, 'max' => $max, 'unit' => 'money', 'row_label' => 'منبع فروش', 'empty' => 'برای فروش منابع در این بازه داده‌ای ثبت نشده است.' );
	}

	public static function validate_range_args( array $args ) {
		if ( 'custom' !== sanitize_key( $args['range'] ?? '' ) ) {
			return true;
		}
		$from = self::parse_requested_date( $args['from'] ?? '' );
		$to   = self::parse_requested_date( $args['to'] ?? '' );
		if ( ! $from || ! $to ) {
			return new WP_Error( 'mm_invalid_date', 'تاریخ شروع و پایان را به‌صورت شمسی معتبر، مانند ۱۴۰۵/۰۶/۰۹ وارد کنید.' );
		}
		if ( $from > $to ) {
			return new WP_Error( 'mm_invalid_date_order', 'تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.' );
		}
		return true;
	}

	private static function parse_requested_date( $value ) {
		$value = strtr(
			(string) $value,
			array(
				'۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
				'٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
			)
		);
		$value = trim( str_replace( array( "\xE2\x80\x8E", "\xE2\x80\x8F" ), '', $value ) );
		if ( ! preg_match( '/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/', $value, $match ) ) {
			return false;
		}
		$year  = (int) $match[1];
		$month = (int) $match[2];
		$day   = (int) $match[3];
		if ( $year >= 1200 && $year <= 1600 ) {
			$gregorian = self::jalali_to_gregorian( $year, $month, $day );
			if ( ! $gregorian ) {
				return false;
			}
			list( $year, $month, $day ) = $gregorian;
		} elseif ( $year < 1700 || ! checkdate( $month, $day, $year ) ) {
			return false;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-n-j', $year . '-' . $month . '-' . $day, wp_timezone() );
		return $date && $date->format( 'Y-n-j' ) === $year . '-' . $month . '-' . $day ? $date : false;
	}

	private static function jalali_to_gregorian( $jy, $jm, $jd ) {
		if ( $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31 || ( $jm > 6 && $jd > 30 ) ) {
			return false;
		}
		$original = array( $jy, $jm, $jd );
		$jy += 1595;
		$days = -355668 + ( 365 * $jy ) + ( intdiv( $jy, 33 ) * 8 ) + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd;
		$days += $jm < 7 ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186;
		$gy = 400 * intdiv( $days, 146097 );
		$days %= 146097;
		if ( $days > 36524 ) {
			$days--;
			$gy += 100 * intdiv( $days, 36524 );
			$days %= 36524;
			if ( $days >= 365 ) {
				$days++;
			}
		}
		$gy += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$gy += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}
		$gd = $days + 1;
		$leap = ( 0 === $gy % 4 && 0 !== $gy % 100 ) || 0 === $gy % 400;
		$month_days = array( 0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$gm = 1;
		while ( $gm <= 12 && $gd > $month_days[ $gm ] ) {
			$gd -= $month_days[ $gm++ ];
		}
		if ( self::gregorian_to_jalali( $gy, $gm, $gd ) !== $original ) {
			return false;
		}
		return array( $gy, $gm, $gd );
	}

	private static function gregorian_to_jalali( $gy, $gm, $gd ) {
		$gdm = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		if ( $gy > 1600 ) {
			$jy = 979;
			$gy -= 1600;
		} else {
			$jy = 0;
			$gy -= 621;
		}
		$gy2  = $gm > 2 ? $gy + 1 : $gy;
		$days = ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) - 80 + $gd + $gdm[ $gm - 1 ];
		$jy  += 33 * intdiv( $days, 12053 );
		$days %= 12053;
		$jy  += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}
		if ( $days < 186 ) {
			return array( $jy, 1 + intdiv( $days, 31 ), 1 + ( $days % 31 ) );
		}
		return array( $jy, 7 + intdiv( $days - 186, 30 ), 1 + ( ( $days - 186 ) % 30 ) );
	}

	private static function range( $args ) {
		$now_datetime = current_datetime();
		$now  = $now_datetime->getTimestamp();
		$key  = sanitize_key( $args['range'] ?? '7d' );
		$end  = $now;
		$days = 7;

		if ( '24h' === $key ) {
			$start = $now - DAY_IN_SECONDS;
		} elseif ( '10d' === $key ) {
			$days  = 10;
			$start = $now_datetime->setTime( 0, 0, 0 )->modify( '-9 days' )->getTimestamp();
		} elseif ( '30d' === $key ) {
			$days  = 30;
			$start = $now_datetime->setTime( 0, 0, 0 )->modify( '-29 days' )->getTimestamp();
		} elseif ( 'custom' === $key && ! empty( $args['from'] ) && ! empty( $args['to'] ) ) {
			$from_date = self::parse_requested_date( $args['from'] );
			$to_date   = self::parse_requested_date( $args['to'] );
			$start = $from_date ? $from_date->setTime( 0, 0, 0 )->getTimestamp() : 0;
			$end   = $to_date ? $to_date->setTime( 23, 59, 59 )->getTimestamp() : 0;
			if ( ! $start || ! $end || $start > $end ) {
				$start = $now_datetime->setTime( 0, 0, 0 )->modify( '-6 days' )->getTimestamp();
				$end   = $now;
			}
		} else {
			$key   = '7d';
			$start = $now_datetime->setTime( 0, 0, 0 )->modify( '-6 days' )->getTimestamp();
		}

		$duration       = max( HOUR_IN_SECONDS, $end - $start + 1 );
		$previous_end   = $start - 1;
		$previous_start = $previous_end - $duration + 1;
		return array(
			'key'            => $key,
			'start'          => self::mysql_datetime_from_timestamp( $start ),
			'end'            => self::mysql_datetime_from_timestamp( $end ),
			'previous_start' => self::mysql_datetime_from_timestamp( $previous_start ),
			'previous_end'   => self::mysql_datetime_from_timestamp( $previous_end ),
		);
	}

	private static function sanitize_statuses( $statuses ) {
		$allowed = function_exists( 'wc_get_order_statuses' ) ? array_map( static function ( $v ) { return str_replace( 'wc-', '', $v ); }, array_keys( wc_get_order_statuses() ) ) : array();
		$clean   = array_filter( array_map( static function ( $v ) { return sanitize_key( str_replace( 'wc-', '', $v ) ); }, (array) $statuses ) );
		return array_values( array_intersect( $clean, $allowed ) );
	}

	private static function order_where( $start, $end, $statuses, $sources, $alias = '' ) {
		$p      = $alias ? $alias . '.' : '';
		$sql    = "WHERE {$p}created_at BETWEEN %s AND %s";
		$params = array( $start, $end );
		if ( $statuses ) {
			$sql .= " AND {$p}status IN (" . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
			$params = array_merge( $params, $statuses );
		}
		if ( $sources ) {
			$sql .= " AND {$p}source_key IN (" . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')';
			$params = array_merge( $params, $sources );
		}
		return array( 'sql' => $sql, 'params' => $params );
	}

	private static function traffic_where( $start, $end, $sources ) {
		$sql    = 'WHERE bucket_start BETWEEN %s AND %s';
		$params = array( $start, $end );
		if ( $sources ) {
			$sql .= ' AND source_key IN (' . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')';
			$params = array_merge( $params, $sources );
		}
		return array( 'sql' => $sql, 'params' => $params );
	}

	private static function audience_where( $start, $end, $sources ) {
		$sql    = 'WHERE first_seen <= %s AND last_seen >= %s';
		$params = array( $end, $start );
		if ( $sources ) {
			$sql .= ' AND source_key IN (' . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')';
			$params = array_merge( $params, $sources );
		}
		return array( 'sql' => $sql, 'params' => $params );
	}

	private static function available_sources() {
		global $wpdb;
		$tables = MM_Installer::tables();
		$rows   = $wpdb->get_results(
			"SELECT source_key, MAX(source_label) label FROM (
			 SELECT source_key, source_label FROM {$tables['hourly']} WHERE source_key NOT IN ('internal','test')
			 UNION ALL SELECT source_key, source_label FROM {$tables['orders']} WHERE source_key NOT IN ('internal','test')
			) s GROUP BY source_key ORDER BY label",
			ARRAY_A
		);
		foreach ( $rows as &$row ) {
			$row['label'] = $row['label'] ?: self::source_label( $row['source_key'] );
		}
		return $rows;
	}

	public static function comparison_data( array $args ) {
		self::assert_storage_ready();
		self::sync_live_aggregates();
		$range    = self::range( $args );
		$statuses = self::sanitize_statuses( $args['statuses'] ?? array() );
		$sources  = array_filter( array_map( 'sanitize_key', (array) ( $args['sources'] ?? array() ) ) );

		$ranges = array(
			'current'  => array( 'key' => 'current', 'start' => $range['start'], 'end' => $range['end'] ),
			'previous' => array( 'key' => 'previous', 'start' => $range['previous_start'], 'end' => $range['previous_end'] ),
			'm3'       => self::shift_range( $range['start'], $range['end'], '-3 months', 'm3' ),
			'm6'       => self::shift_range( $range['start'], $range['end'], '-6 months', 'm6' ),
			'y1'       => self::shift_range( $range['start'], $range['end'], '-1 year', 'y1' ),
		);

		$snapshots = array();
		foreach ( $ranges as $key => $period ) {
			$snapshots[ $key ] = array(
				'key'      => $key,
				'range'    => array( 'start' => $period['start'], 'end' => $period['end'] ),
				'summary'  => self::summary( $period['start'], $period['end'], $statuses, $sources ),
				'sources'  => self::source_snapshot( $period['start'], $period['end'], $statuses, $sources ),
				'statuses' => self::status_snapshot( $period['start'], $period['end'], $sources ),
			);
		}

		return array(
			'base_range'  => $range,
			'snapshots'   => $snapshots,
			'source_list' => self::available_sources(),
		);
	}

	private static function shift_range( $start, $end, $modifier, $key ) {
		$timezone = wp_timezone();
		$start_dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $start, $timezone );
		$end_dt   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $end, $timezone );
		if ( ! $start_dt || ! $end_dt ) {
			return array( 'key' => $key, 'start' => $start, 'end' => $end );
		}

		$months = array( '-3 months' => 3, '-6 months' => 6, '-1 year' => 12 );
		if ( isset( $months[ $modifier ] ) ) {
			$start_dt = self::shift_datetime_months( $start_dt, $months[ $modifier ] );
			$end_dt   = self::shift_datetime_months( $end_dt, $months[ $modifier ] );
		} else {
			$start_dt = $start_dt->modify( $modifier );
			$end_dt   = $end_dt->modify( $modifier );
		}

		return array(
			'key'   => $key,
			'start' => $start_dt->format( 'Y-m-d H:i:s' ),
			'end'   => $end_dt->format( 'Y-m-d H:i:s' ),
		);
	}

	private static function shift_datetime_months( DateTimeImmutable $date, $months ) {
		$year       = (int) $date->format( 'Y' );
		$month      = (int) $date->format( 'n' );
		$day        = (int) $date->format( 'j' );
		$total      = ( $year * 12 ) + ( $month - 1 ) - absint( $months );
		$target_y   = (int) floor( $total / 12 );
		$target_m   = ( $total % 12 ) + 1;
		$first      = $date->setDate( $target_y, $target_m, 1 );
		$last_day   = (int) $first->modify( 'last day of this month' )->format( 'j' );
		return $date->setDate( $target_y, $target_m, min( $day, $last_day ) );
	}

	private static function source_snapshot( $start, $end, $statuses, $sources ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$ow     = self::order_where( $start, $end, $statuses, $sources );
		$traffic = self::traffic_source_metrics_exact( $start, $end, $sources );
		$orders = $wpdb->get_results(
			self::maybe_prepare(
				"SELECT source_key, MAX(source_label) source_label, COUNT(*) orders, COALESCE(SUM(gross_total),0) revenue
				 FROM {$tables['orders']} {$ow['sql']} GROUP BY source_key",
				$ow['params']
			),
			ARRAY_A
		);

		$map = array();
		foreach ( $traffic as $key => $row ) {
			$map[ $key ] = array(
				'key'      => $key,
				'label'    => $row['label'] ?: self::source_label( $key ),
				'entries'  => (int) ( $row['entries'] ?? $row['sessions'] ?? 0 ),
				'visitors' => (int) ( $row['visitors'] ?? 0 ),
				'sessions' => (int) ( $row['sessions'] ?? 0 ),
				'orders'   => 0,
				'revenue'  => 0.0,
			);
		}
		foreach ( $orders as $row ) {
			$key = sanitize_key( $row['source_key'] ?: 'unknown' );
			if ( ! isset( $map[ $key ] ) ) {
				$map[ $key ] = array(
					'key' => $key, 'label' => $row['source_label'] ?: self::source_label( $key ),
					'entries' => 0, 'visitors' => 0, 'sessions' => 0, 'orders' => 0, 'revenue' => 0.0,
				);
			}
			$map[ $key ]['orders']  = (int) $row['orders'];
			$map[ $key ]['revenue'] = (float) $row['revenue'];
		}
		foreach ( $map as &$row ) {
			$row['conversion'] = $row['entries'] > 0 ? round( $row['orders'] / $row['entries'] * 100, 2 ) : 0;
		}
		unset( $row );
		usort( $map, static function ( $a, $b ) {
			$cmp = $b['entries'] <=> $a['entries'];
			return 0 !== $cmp ? $cmp : ( $b['revenue'] <=> $a['revenue'] );
		} );
		return array_values( $map );
	}

	private static function status_snapshot( $start, $end, $sources ) {
		global $wpdb;
		$tables = MM_Installer::tables();
		$where  = self::order_where( $start, $end, array(), $sources );
		$rows   = $wpdb->get_results(
			self::maybe_prepare(
				"SELECT source_key, MAX(source_label) source_label, status, COUNT(*) count, COALESCE(SUM(gross_total),0) revenue
				 FROM {$tables['orders']} {$where['sql']} GROUP BY source_key, status ORDER BY source_key, count DESC",
				$where['params']
			),
			ARRAY_A
		);

		$out = array();
		foreach ( $rows as $row ) {
			$key = sanitize_key( $row['source_key'] ?: 'unknown' );
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array(
					'key' => $key,
					'label' => $row['source_label'] ?: self::source_label( $key ),
					'rows' => array(),
				);
			}
			$out[ $key ]['rows'][] = array(
				'status'  => sanitize_key( $row['status'] ),
				'label'   => function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $row['status'] ) : $row['status'],
				'count'   => (int) $row['count'],
				'revenue' => (float) $row['revenue'],
			);
		}
		return array_values( $out );
	}

	public static function diagnostics() {
		global $wpdb;
		self::sync_live_aggregates();
		$tables = MM_Installer::tables();
		$data   = array();
		foreach ( $tables as $key => $table ) {
			$data[ $key ] = array( 'exists' => self::table_exists( $table ), 'rows' => self::table_exists( $table ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : 0 );
		}
		$legacy_traffic = self::legacy_traffic_table();
		$data['legacy_traffic'] = $legacy_traffic ? self::table_exists( $legacy_traffic ) : false;
		$data['legacy_products'] = self::table_exists( $wpdb->prefix . 'kkpa_product_views' );
		$data['hpos'] = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ? \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() : false;
		return $data;
	}

	public static function diagnostics_detail() {
		global $wpdb;
		$tables = MM_Installer::tables();
		$base   = self::diagnostics();
		$required_columns = array(
			'orders'   => array( 'order_id', 'created_at', 'status', 'gross_total', 'payment_method', 'payment_method_title', 'source_key', 'scanned_at' ),
			'items'    => array( 'order_id', 'order_item_id', 'product_id', 'quantity', 'line_total' ),
			'visits'   => array( 'event_key', 'visitor_id', 'session_id', 'visited_at', 'source_key', 'page_type', 'object_id', 'is_entry', 'transport' ),
			'audience' => array( 'fact_key', 'visitor_hash', 'session_hash', 'first_seen', 'last_seen', 'source_key' ),
			'hourly'   => array( 'bucket_start', 'source_hash', 'source_key', 'visitors', 'sessions', 'pageviews', 'product_views' ),
			'logs'     => array( 'created_at', 'level', 'context', 'message', 'details' ),
		);
		$schema = array();
		foreach ( $required_columns as $key => $required ) {
			$columns = self::table_columns( $tables[ $key ] );
			$missing = array_values( array_diff( $required, $columns ) );
			$schema[ $key ] = array( 'ok' => empty( $missing ), 'missing' => $missing );
		}

		$report = array( 'orders' => 0, 'items' => 0, 'visits' => 0, 'entries' => 0, 'oldest_order' => '', 'latest_order' => '', 'oldest_visit' => '', 'latest_visit' => '', 'oldest_entry' => '', 'latest_entry' => '', 'tracker_freshness_minutes' => null, 'gateways' => 0 );
		if ( self::table_exists( $tables['orders'] ) ) {
			$report['orders'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['orders']}" );
			$coverage = $wpdb->get_row( "SELECT MIN(created_at) oldest_order, MAX(created_at) latest_order, COUNT(DISTINCT COALESCE(NULLIF(payment_method,''),'unknown')) gateways FROM {$tables['orders']}", ARRAY_A );
			$report['oldest_order'] = sanitize_text_field( $coverage['oldest_order'] ?? '' );
			$report['latest_order'] = sanitize_text_field( $coverage['latest_order'] ?? '' );
			$report['gateways']     = (int) ( $coverage['gateways'] ?? 0 );
		}
		if ( self::table_exists( $tables['items'] ) ) {
			$report['items'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['items']}" );
		}
		if ( self::table_exists( $tables['visits'] ) ) {
			$report['visits'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['visits']}" );
		}
		$traffic_coverage = self::traffic_coverage();
		$report['oldest_visit'] = sanitize_text_field( $traffic_coverage['start'] ?? '' );
		$report['latest_visit'] = sanitize_text_field( $traffic_coverage['end'] ?? '' );
		$report['oldest_entry'] = sanitize_text_field( $traffic_coverage['entry_start'] ?? '' );
		$report['latest_entry'] = sanitize_text_field( $traffic_coverage['entry_end'] ?? '' );
		$report['entries'] = (int) ( $traffic_coverage['total_entries'] ?? 0 );
		$report['tracker_freshness_minutes'] = $traffic_coverage['freshness_minutes'] ?? null;
		$report['tracker_transports'] = (array) ( $traffic_coverage['transports'] ?? array() );

		$woo_count = self::woocommerce_order_count();
		$woo_error = is_wp_error( $woo_count ) ? $woo_count->get_error_message() : '';

		// Some Persian/Jalali plugins filter wp_date(). SQL storage is Gregorian, so
		// report boundaries must never depend on a localized/filterable formatter.
		$now_timestamp = current_datetime()->getTimestamp();
		$native_now    = self::mysql_datetime_from_timestamp( $now_timestamp );
		$wp_date_now   = wp_date( 'Y-m-d H:i:s', $now_timestamp, wp_timezone() );
		$date_filter_detected = $native_now !== $wp_date_now;

		return array(
			'environment' => array(
				'wordpress' => get_bloginfo( 'version' ),
				'php'       => PHP_VERSION,
				'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
				'plugin'    => defined( 'MM_VERSION' ) ? MM_VERSION : '',
				'db_version'=> (string) get_option( 'mm_db_version', '' ),
				'native_now' => $native_now,
				'wp_date_now' => sanitize_text_field( $wp_date_now ),
				'date_filter_detected' => $date_filter_detected,
				'traffic_engine' => 'explicit-entry-v3',
				'tracker_cache_safe' => true,
			),
			'tables'     => $base,
			'schema'     => $schema,
			'report'     => $report,
			'woocommerce_orders' => is_wp_error( $woo_count ) ? null : (int) $woo_count,
			'woocommerce_error'  => $woo_error,
			'scan'       => (array) get_option( 'mm_scan_state', array() ),
			'db_install_error' => sanitize_text_field( (string) get_option( 'mm_db_install_error', '' ) ),
			'wpdb_last_error' => sanitize_text_field( $wpdb->last_error ),
		);
	}

	private static function changes( $current, $previous ) {
		$out = array();
		foreach ( array( 'entries', 'visitors', 'sessions', 'pageviews', 'product_views', 'orders', 'revenue', 'aov', 'conversion', 'rpv' ) as $key ) {
			$out[ $key ] = self::percent_change( (float) $current[ $key ], (float) $previous[ $key ] );
		}
		return $out;
	}

	private static function percent_change( $current, $previous ) {
		if ( 0.0 === (float) $previous ) {
			return 0.0 === (float) $current ? 0 : 100;
		}
		return round( ( ( $current - $previous ) / abs( $previous ) ) * 100, 1 );
	}

	private static function source_label( $key ) {
		$labels = array(
			'direct' => 'مستقیم', 'unknown' => 'نامشخص', 'google' => 'گوگل', 'google_ads' => 'تبلیغات گوگل',
			'torob' => 'ترب', 'snapp_pay' => 'اسنپ‌پی', 'instagram' => 'اینستاگرام', 'telegram' => 'تلگرام',
			'whatsapp' => 'واتساپ', 'bale' => 'بله', 'eitaa' => 'ایتا', 'aparat' => 'آپارات',
			'facebook' => 'فیسبوک', 'meta' => 'متا', 'bing' => 'بینگ', 'email' => 'ایمیل', 'referral' => 'ارجاعی',
		);
		return $labels[ $key ] ?? $key;
	}

	private static function assert_storage_ready() {
		$install_error = sanitize_text_field( (string) get_option( 'mm_db_install_error', '' ) );
		if ( $install_error ) {
			throw new RuntimeException( $install_error );
		}

		$tables = MM_Installer::tables();
		foreach ( array( 'visits', 'audience', 'hourly', 'orders', 'items' ) as $key ) {
			if ( empty( $tables[ $key ] ) || ! self::table_exists( $tables[ $key ] ) ) {
				throw new RuntimeException( 'جدول ضروری مانیتور من موجود نیست: ' . $key . '. افزونه را یک‌بار غیرفعال/فعال کنید یا تب لاگ را بررسی کنید.' );
			}
		}

		$missing = array_diff(
			array( 'order_id', 'created_at', 'status', 'gross_total', 'payment_method', 'payment_method_title', 'source_key', 'scanned_at' ),
			self::table_columns( $tables['orders'] )
		);
		if ( $missing ) {
			throw new RuntimeException( 'ساختار جدول سفارش‌های مانیتور من ناقص است. ستون‌های مفقود: ' . implode( ', ', $missing ) );
		}
	}

	private static function table_exists( $table ) {
		global $wpdb;
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	private static function table_columns( $table ) {
		static $cache = array();
		if ( isset( $cache[ $table ] ) ) {
			return $cache[ $table ];
		}
		if ( ! self::table_exists( $table ) ) {
			$cache[ $table ] = array();
			return array();
		}
		global $wpdb;
		$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
		$cache[ $table ] = array_values( array_filter( array_map( 'sanitize_key', wp_list_pluck( (array) $rows, 'Field' ) ) ) );
		return $cache[ $table ];
	}

	/**
	 * Format an epoch timestamp for SQL using the site timezone without wp_date/date_i18n.
	 * This deliberately bypasses localization hooks (e.g. Jalali/Persian date plugins).
	 */
	private static function mysql_datetime_from_timestamp( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return '';
		}
		try {
			return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
		} catch ( Throwable $e ) {
			return gmdate( 'Y-m-d H:i:s', $timestamp );
		}
	}

	private static function maybe_prepare( $sql, $params ) {
		global $wpdb;
		return $params ? $wpdb->prepare( $sql, $params ) : $sql;
	}
}
