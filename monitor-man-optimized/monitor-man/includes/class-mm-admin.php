<?php

defined( 'ABSPATH' ) || exit;

final class MM_Admin {
	const PAGE = 'monitor-man';

	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 50 );
		add_action( 'current_screen', array( __CLASS__, 'silence_wordpress_notices' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_mm_dashboard_data', array( __CLASS__, 'ajax_dashboard' ) );
		add_action( 'wp_ajax_mm_comparison_data', array( __CLASS__, 'ajax_comparison' ) );
		add_action( 'wp_ajax_mm_products_data', array( __CLASS__, 'ajax_products' ) );
		add_action( 'wp_ajax_mm_product_sources', array( __CLASS__, 'ajax_product_sources' ) );
		add_action( 'wp_ajax_mm_scan', array( __CLASS__, 'ajax_scan' ) );
		add_action( 'wp_ajax_mm_save_settings', array( __CLASS__, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_mm_logs', array( __CLASS__, 'ajax_logs' ) );
		add_action( 'wp_ajax_mm_clear_logs', array( __CLASS__, 'ajax_clear_logs' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MM_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function silence_wordpress_notices( $screen ) {
		if ( $screen && 'toplevel_page_' . self::PAGE === $screen->id && self::current_user_allowed() ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}

	public static function current_user_allowed() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$allowed = array_map( 'absint', (array) get_option( 'mm_allowed_users', array() ) );
		if ( ! $allowed ) {
			return current_user_can( 'manage_options' );
		}
		return in_array( get_current_user_id(), $allowed, true ) && current_user_can( 'view_monitor_man' );
	}

	public static function menu() {
		if ( ! self::current_user_allowed() ) {
			return;
		}
		add_menu_page(
			'مانیتور من',
			'مانیتور من',
			'read',
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-chart-area',
			3
		);
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE !== $hook || ! self::current_user_allowed() ) {
			return;
		}

		wp_enqueue_style( 'mm-admin', MM_URL . 'assets/admin.min.css', array(), MM_VERSION );
		wp_enqueue_script( 'mm-admin', MM_URL . 'assets/admin.min.js', array(), MM_VERSION, true );

		$statuses = array();
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			foreach ( wc_get_order_statuses() as $key => $label ) {
				$statuses[] = array( 'key' => str_replace( 'wc-', '', $key ), 'label' => $label );
			}
		}
		wp_localize_script(
			'mm-admin',
			'MonitorManAdmin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mm_admin' ),
				'statuses' => $statuses,
				'defaultStatuses' => array(),
				'currency' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'تومان',
				'i18n'     => array(
					'loading' => 'در حال خواندن داده‌ها…',
					'error'   => 'خواندن گزارش با خطا روبه‌رو شد.',
					'empty'   => 'برای این فیلتر داده‌ای وجود ندارد.',
				),
			)
		);
	}

	public static function action_links( $links ) {
		if ( self::current_user_allowed() ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">داشبورد</a>' );
		}
		return $links;
	}

	public static function ajax_dashboard() {
		self::guard_ajax();
		$args = array(
			'range'    => sanitize_key( $_POST['range'] ?? '7d' ),
			'from'     => sanitize_text_field( wp_unslash( $_POST['from'] ?? '' ) ),
			'to'       => sanitize_text_field( wp_unslash( $_POST['to'] ?? '' ) ),
			'statuses' => isset( $_POST['statuses'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['statuses'] ) ) : array(),
			'sources'  => isset( $_POST['sources'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sources'] ) ) : array(),
		);
		self::validate_range_or_error( $args );
		try {
			$data = MM_Repository::dashboard_data( $args );
		} catch ( Throwable $e ) {
			self::ajax_exception( 'dashboard', $e );
		}
		wp_send_json_success( $data );
	}

	public static function ajax_comparison() {
		self::guard_ajax();
		$args = array(
			'range'    => sanitize_key( $_POST['range'] ?? '7d' ),
			'from'     => sanitize_text_field( wp_unslash( $_POST['from'] ?? '' ) ),
			'to'       => sanitize_text_field( wp_unslash( $_POST['to'] ?? '' ) ),
			'statuses' => isset( $_POST['statuses'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['statuses'] ) ) : array(),
			'sources'  => isset( $_POST['sources'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sources'] ) ) : array(),
		);
		self::validate_range_or_error( $args );
		try {
			$data = MM_Repository::comparison_data( $args );
		} catch ( Throwable $e ) {
			self::ajax_exception( 'comparison', $e );
		}
		wp_send_json_success( $data );
	}

	public static function ajax_products() {
		self::guard_ajax();
		$args = array(
			'range'    => sanitize_key( $_POST['range'] ?? '7d' ),
			'from'     => sanitize_text_field( wp_unslash( $_POST['from'] ?? '' ) ),
			'to'       => sanitize_text_field( wp_unslash( $_POST['to'] ?? '' ) ),
			'statuses' => isset( $_POST['statuses'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['statuses'] ) ) : array(),
			'sources'  => isset( $_POST['sources'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sources'] ) ) : array(),
			'page'     => absint( $_POST['page'] ?? 1 ),
			'per_page' => sanitize_text_field( wp_unslash( $_POST['per_page'] ?? '20' ) ),
			'sort'     => sanitize_key( $_POST['sort'] ?? 'revenue' ),
			'order'    => sanitize_key( $_POST['order'] ?? 'desc' ),
		);
		self::validate_range_or_error( $args );
		try {
			$data = MM_Repository::products_data( $args );
		} catch ( Throwable $e ) {
			self::ajax_exception( 'products', $e );
		}
		wp_send_json_success( $data );
	}

	public static function ajax_product_sources() {
		self::guard_ajax();
		$args = array(
			'range'   => sanitize_key( $_POST['range'] ?? '7d' ),
			'from'    => sanitize_text_field( wp_unslash( $_POST['from'] ?? '' ) ),
			'to'      => sanitize_text_field( wp_unslash( $_POST['to'] ?? '' ) ),
			'statuses' => isset( $_POST['statuses'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['statuses'] ) ) : array(),
			'sources' => isset( $_POST['sources'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sources'] ) ) : array(),
		);
		self::validate_range_or_error( $args );
		try {
			$data = MM_Repository::product_source_breakdown( absint( $_POST['product_id'] ?? 0 ), $args );
		} catch ( Throwable $e ) {
			self::ajax_exception( 'product_sources', $e );
		}
		wp_send_json_success( $data );
	}

	public static function ajax_scan() {
		self::guard_ajax();
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$phase = sanitize_key( $_POST['phase'] ?? '' );
		try {
			if ( 'reset' === $phase ) {
				$result = MM_Repository::scan_reset();
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
				}
				wp_send_json_success( $result );
			}
			if ( 'traffic' === $phase ) {
				$result = MM_Repository::scan_traffic();
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
				}
				wp_send_json_success( $result );
			}
			if ( 'orders' === $phase ) {
				$result = MM_Repository::scan_orders_page( absint( $_POST['page'] ?? 1 ) );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
				}
				wp_send_json_success( $result );
			}
			if ( 'finish' === $phase ) {
				$result = MM_Repository::finish_scan();
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message(), 'scan' => (array) get_option( 'mm_scan_state', array() ) ), 409 );
				}
				wp_send_json_success( $result );
			}
			if ( 'fail' === $phase ) {
				wp_send_json_success( MM_Repository::fail_scan( sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) ) ) );
			}
			wp_send_json_error( array( 'message' => 'مرحله اسکن نامعتبر است.' ), 400 );
		} catch ( Throwable $e ) {
			if ( 'fail' !== $phase ) {
				MM_Repository::fail_scan( $e->getMessage() );
			}
			self::ajax_exception( 'scan_' . ( $phase ?: 'unknown' ), $e );
		}
	}

	public static function ajax_save_settings() {
		self::guard_ajax();
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'فقط مدیر کل می‌تواند دسترسی‌ها را تغییر دهد.' ), 403 );
		}

		$selected = array_filter( array_map( 'absint', (array) ( $_POST['allowed_users'] ?? array() ) ) );
		$selected[] = get_current_user_id();
		$selected   = array_values( array_unique( $selected ) );
		$eligible   = wp_list_pluck( self::eligible_users(), 'ID' );
		$selected   = array_values( array_intersect( $selected, array_map( 'absint', $eligible ) ) );
		$previous   = array_map( 'absint', (array) get_option( 'mm_allowed_users', array() ) );

		foreach ( array_unique( array_merge( $previous, $selected ) ) as $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( ! $user ) {
				continue;
			}
			if ( in_array( $user_id, $selected, true ) ) {
				$user->add_cap( 'view_monitor_man' );
			} else {
				$user->remove_cap( 'view_monitor_man' );
			}
		}

		update_option( 'mm_allowed_users', $selected, false );
		update_option( 'mm_custom_sources', sanitize_textarea_field( wp_unslash( $_POST['custom_sources'] ?? '' ) ), false );
		wp_send_json_success( array( 'message' => 'تنظیمات ذخیره شد.' ) );
	}


	public static function ajax_logs() {
		self::guard_ajax();
		try {
			wp_send_json_success(
				array(
					'diagnostics' => MM_Repository::diagnostics_detail(),
					'logs'        => class_exists( 'MM_Logger' ) ? MM_Logger::recent( 120 ) : array(),
				)
			);
		} catch ( Throwable $e ) {
			if ( class_exists( 'MM_Logger' ) ) {
				MM_Logger::error( 'admin_logs', 'خواندن گزارش سلامت سیستم ناموفق بود.', array( 'message' => $e->getMessage() ) );
			}
			wp_send_json_error( array( 'message' => 'خواندن گزارش سلامت سیستم ناموفق بود: ' . $e->getMessage() ), 500 );
		}
	}

	public static function ajax_clear_logs() {
		self::guard_ajax();
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'فقط مدیر کل می‌تواند لاگ‌ها را پاک کند.' ), 403 );
		}
		$ok = class_exists( 'MM_Logger' ) ? MM_Logger::clear() : false;
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => 'پاک‌کردن لاگ‌ها انجام نشد.' ), 500 );
		}
		wp_send_json_success( array( 'message' => 'لاگ‌های مانیتور من پاک شدند.' ) );
	}

	private static function ajax_exception( $context, Throwable $e ) {
		$message = sanitize_text_field( $e->getMessage() );
		if ( class_exists( 'MM_Logger' ) ) {
			MM_Logger::error( 'ajax_' . sanitize_key( $context ), 'اجرای درخواست مدیریتی با خطای سرور متوقف شد.', array(
				'message' => $message,
				'file'    => wp_basename( $e->getFile() ),
				'line'    => (int) $e->getLine(),
			) );
		}
		wp_send_json_error( array( 'message' => 'خطای سرور در مانیتور من: ' . $message ), 500 );
	}

	private static function guard_ajax() {
		check_ajax_referer( 'mm_admin', 'nonce' );
		if ( ! self::current_user_allowed() ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز است.' ), 403 );
		}
	}

	private static function validate_range_or_error( array $args ) {
		$result = MM_Repository::validate_range_args( $args );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}
	}

	private static function eligible_users() {
		return get_users(
			array(
				'role__in' => array( 'administrator', 'shop_manager' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
			)
		);
	}

	public static function render() {
		if ( ! self::current_user_allowed() ) {
			wp_die( esc_html( 'شما اجازه مشاهده مانیتور من را ندارید.' ), '', array( 'response' => 403 ) );
		}
		$allowed     = array_map( 'absint', (array) get_option( 'mm_allowed_users', array() ) );
		$users       = self::eligible_users();
		$diagnostics = MM_Repository::diagnostics();
		$scan        = (array) get_option( 'mm_scan_state', array() );
		?>
		<div class="mm-app" dir="rtl">
			<aside class="mm-sidebar">
				<div class="mm-brand"><span class="mm-brand-mark">M</span><div><strong>مانیتور من</strong><small>مرکز فرمان فروش</small></div></div>
				<nav class="mm-nav">
					<button class="is-active" data-mm-view="overview"><span class="dashicons dashicons-chart-area"></span>نمای کلی</button>
					<button data-mm-view="compare"><span class="dashicons dashicons-chart-bar"></span>مقایسه</button>
					<button data-mm-view="sources"><span class="dashicons dashicons-share"></span>منابع ورودی</button>
					<button data-mm-view="gateways"><span class="dashicons dashicons-money-alt"></span>درگاه‌های پرداخت</button>
					<button data-mm-view="products"><span class="dashicons dashicons-products"></span>محصولات</button>
					<button data-mm-view="gantt"><span class="dashicons dashicons-schedule"></span>نمای گانت</button>
					<button data-mm-view="orders"><span class="dashicons dashicons-cart"></span>سفارش‌ها</button>
					<button data-mm-view="settings"><span class="dashicons dashicons-admin-generic"></span>تنظیمات</button>
					<button data-mm-view="logs"><span class="dashicons dashicons-warning"></span>لاگ و خطاها</button>
				</nav>
				<div class="mm-sidebar-foot"><span class="mm-live-dot"></span> نسخه <?php echo esc_html( MM_VERSION ); ?></div>
			</aside>

			<main class="mm-main">
				<header class="mm-hero">
					<div class="mm-hero-copy">
						<span class="mm-eyebrow">WOOCOMMERCE INTELLIGENCE</span>
						<h1>فروش سایتت زیر ذره‌بین است.</h1>
						<p>از اولین ورودی تا مبلغ سفارش؛ دقیق، قابل مقایسه و یک‌جا.</p>
						<div class="mm-hero-actions">
							<button type="button" class="mm-btn mm-btn-primary" id="mm-scan"><span class="dashicons dashicons-update"></span> بررسی دستی همه داده‌ها</button>
							<span class="mm-last-scan">آخرین بررسی: <strong id="mm-last-scan-value" data-date="<?php echo esc_attr( ! empty( $scan['finished_at'] ) ? $scan['finished_at'] : '' ); ?>"><?php echo esc_html( ! empty( $scan['finished_at'] ) ? 'در حال خواندن…' : 'هنوز انجام نشده' ); ?></strong></span>
						</div>
					</div>
					<img src="<?php echo esc_url( MM_URL . 'assets/images/monitor-man.png' ); ?>" alt="شخصیت مانیتور من" class="mm-mascot">
				</header>

				<div class="mm-scan-progress" id="mm-scan-progress" hidden>
					<div class="mm-scan-bar"><span></span></div>
					<div class="mm-scan-head"><p id="mm-scan-text">در حال آماده‌سازی…</p><strong id="mm-scan-percent">۰٪</strong></div>
					<div class="mm-scan-report" id="mm-scan-report">
						<div><span>مرحله</span><strong data-scan-stat="stage">آماده‌سازی</strong></div>
						<div><span>رکورد ترافیک</span><strong data-scan-stat="traffic">۰</strong></div>
						<div><span>سفارش پردازش‌شده</span><strong data-scan-stat="processed">۰</strong></div>
						<div><span>سفارش ذخیره‌شده</span><strong data-scan-stat="stored">۰</strong></div>
						<div><span>کل سفارش‌ها</span><strong data-scan-stat="total">۰</strong></div>
					</div>
					<div class="mm-scan-error" id="mm-scan-error" hidden></div>
				</div>

				<section class="mm-filterbar">
					<div class="mm-range" id="mm-range">
						<button data-range="24h">۲۴ ساعت</button><button class="is-active" data-range="7d">۷ روز</button><button data-range="10d">۱۰ روز</button><button data-range="30d">۳۰ روز</button><button data-range="custom">دلخواه</button>
					</div>
					<div class="mm-custom-dates" id="mm-custom-dates" hidden><input type="text" inputmode="numeric" autocomplete="off" id="mm-from" placeholder="۱۴۰۵/۰۶/۰۱" aria-label="از تاریخ شمسی"><span>تا</span><input type="text" inputmode="numeric" autocomplete="off" id="mm-to" placeholder="۱۴۰۵/۰۶/۳۱" aria-label="تا تاریخ شمسی"></div>
					<details class="mm-filter-dropdown"><summary>وضعیت سفارش <span id="mm-status-count">همه</span></summary><div id="mm-status-options"></div></details>
					<details class="mm-filter-dropdown"><summary>منبع ورودی <span id="mm-source-count">همه</span></summary><div id="mm-source-options"><small>پس از اولین اسکن تکمیل می‌شود.</small></div></details>
					<button class="mm-btn mm-btn-ghost" id="mm-apply">اعمال فیلتر</button>
				</section>

				<div class="mm-loading" id="mm-loading"><span></span><p>در حال ساخت گزارش…</p></div>

				<section class="mm-view is-active" data-mm-panel="overview">
					<div class="mm-kpis" id="mm-kpis"></div>
					<div class="mm-grid mm-grid-main">
						<article class="mm-panel mm-panel-wide"><div class="mm-panel-head"><div><span class="mm-kicker">روند عملکرد</span><h2>ورودی، سفارش و مبلغ فروش</h2></div><div class="mm-chart-legend"><i class="is-blue"></i>ورودی <i class="is-green"></i>سفارش <i class="is-yellow"></i>فروش</div></div><div class="mm-canvas-wrap"><canvas id="mm-trend-chart"></canvas></div></article>
						<article class="mm-panel"><div class="mm-panel-head"><div><span class="mm-kicker">ترکیب ترافیک</span><h2>سهم منابع از ورودی‌ها</h2></div></div><div class="mm-donut-wrap"><canvas id="mm-source-donut"></canvas><div id="mm-donut-center"></div></div><div class="mm-mini-legend" id="mm-source-legend"></div></article>
						<article class="mm-panel"><div class="mm-panel-head"><div><span class="mm-kicker">قیف تبدیل</span><h2>از ورودی تا خرید</h2></div></div><div class="mm-funnel" id="mm-funnel"></div></article>
						<article class="mm-panel mm-panel-wide"><div class="mm-panel-head"><div><span class="mm-kicker">درآمد کانال‌ها</span><h2>فروش ثبت‌شده به تفکیک منبع</h2></div></div><div class="mm-canvas-wrap mm-canvas-short"><canvas id="mm-revenue-bars"></canvas></div></article>
						<article class="mm-panel mm-panel-wide"><div class="mm-panel-head"><div><span class="mm-kicker">درگاه‌های پرداخت</span><h2>سفارش و مبلغ به تفکیک درگاه</h2></div></div><div class="mm-gateway-overview" id="mm-gateway-overview"></div></article>
						<article class="mm-panel"><div class="mm-panel-head"><div><span class="mm-kicker">کیفیت داده</span><h2>اطمینان انتساب سفارش</h2></div></div><div id="mm-confidence"></div><p class="mm-note">بالا: ثبت مستقیم/ووکامرس، متوسط: تطبیق کاربر، پایین: منبع تاریخی ناموجود.</p></article>
					</div>
				</section>

				<section class="mm-view" data-mm-panel="compare">
					<article class="mm-panel mm-compare-toolbar">
						<div class="mm-panel-head"><div><span class="mm-kicker">مقایسه دوره‌ای</span><h2>رشد و افت منابع، فروش و وضعیت سفارش‌ها</h2><small class="mm-panel-hint">بازه فعلی از فیلتر بالای صفحه گرفته می‌شود؛ منبع و وضعیت سفارش نیز در محاسبات اعمال می‌شوند.</small></div></div>
						<div class="mm-compare-controls">
							<div class="mm-compare-tabs" id="mm-compare-tabs"><button class="is-active" data-ref="previous">دوره قبل</button><button data-ref="m3">۳ ماه قبل</button><button data-ref="m6">۶ ماه قبل</button><button data-ref="y1">سال قبل</button></div>
							<label class="mm-compare-select">منبع برای وضعیت سفارش‌ها<select id="mm-compare-source"><option value="">همه منابع</option></select></label>
						</div>
						<div class="mm-compare-range-note" id="mm-compare-range-note">در حال آماده‌سازی مقایسه…</div>
					</article>

					<div class="mm-compare-kpis" id="mm-compare-kpis"></div>

					<div class="mm-grid mm-grid-main mm-compare-grid">
						<article class="mm-panel mm-panel-wide"><div class="mm-panel-head"><div><span class="mm-kicker">مقایسه فروش</span><h2>فروش منابع در دو دوره</h2></div></div><div class="mm-canvas-wrap"><canvas id="mm-compare-revenue"></canvas></div></article>
						<article class="mm-panel"><div class="mm-panel-head"><div><span class="mm-kicker">مقایسه ورودی</span><h2>ورودی‌های منابع</h2></div></div><div class="mm-canvas-wrap"><canvas id="mm-compare-visitors"></canvas></div></article>
						<article class="mm-panel mm-panel-wide"><div class="mm-panel-head"><div><span class="mm-kicker">وضعیت‌های ووکامرس</span><h2>رشد یا افت وضعیت سفارش‌ها</h2><small class="mm-panel-hint">این نمودار همه وضعیت‌ها را مستقل از فیلتر وضعیت نمایش می‌دهد تا لغوشده، در انتظار پرداخت و فروش موفق قابل مقایسه باشند.</small></div></div><div class="mm-canvas-wrap"><canvas id="mm-compare-status"></canvas></div></article>
					</div>

					<article class="mm-panel"><div class="mm-panel-head"><div><span class="mm-kicker">نمای یک‌نگاه</span><h2>مقایسه منابع با دوره‌های گذشته</h2></div></div><div class="mm-table-wrap"><table class="mm-table mm-compare-table"><thead><tr><th>منبع</th><th>فروش فعلی</th><th>دوره قبل</th><th>۳ ماه قبل</th><th>۶ ماه قبل</th><th>سال قبل</th><th>تغییر منتخب</th></tr></thead><tbody id="mm-compare-table"></tbody></table></div></article>
				</section>

				<section class="mm-view" data-mm-panel="sources">
					<article class="mm-panel"><div class="mm-panel-head"><div><span class="mm-kicker">تحلیل کانال</span><h2>سهم ورودی و فروش منابع</h2><small class="mm-panel-hint" id="mm-traffic-coverage-note">در حال بررسی پوشش زمانی داده‌های ورودی…</small></div><button class="mm-export" data-export="sources">خروجی CSV</button></div><div class="mm-table-wrap"><table class="mm-table"><thead><tr><th>منبع</th><th>ورودی‌ها</th><th>سهم ورودی</th><th>سفارش</th><th>فروش</th><th>سهم فروش</th><th>نرخ تبدیل</th><th>تغییر</th></tr></thead><tbody id="mm-sources-table"></tbody></table></div></article>
				</section>


				<section class="mm-view" data-mm-panel="gateways">
					<div class="mm-grid mm-grid-main">
						<article class="mm-panel mm-panel-wide"><div class="mm-panel-head"><div><span class="mm-kicker">تحلیل درگاه‌ها</span><h2>تعداد و مبلغ سفارش روی هر درگاه</h2><small class="mm-panel-hint">درگاه‌ها به‌صورت خودکار از خود سفارش‌های ووکامرس خوانده می‌شوند؛ شامل هر درگاه اختصاصی یا جدیدی که روی سایت نصب شود.</small></div></div><div class="mm-table-wrap"><table class="mm-table"><thead><tr><th>درگاه</th><th>شناسه</th><th>تعداد سفارش</th><th>مبلغ کل</th><th>میانگین سفارش</th><th>سهم مبلغ</th><th>فروش موفق</th><th>مبلغ موفق</th></tr></thead><tbody id="mm-gateways-table"></tbody></table></div></article>
						<article class="mm-panel mm-panel-wide"><div class="mm-panel-head"><div><span class="mm-kicker">سفارش‌های اخیر</span><h2>آخرین سفارش‌ها و درگاه ثبت‌شده</h2></div></div><div class="mm-table-wrap"><table class="mm-table"><thead><tr><th>سفارش</th><th>زمان</th><th>درگاه</th><th>شناسه درگاه</th><th>وضعیت</th><th>مبلغ</th></tr></thead><tbody id="mm-gateway-orders-table"></tbody></table></div></article>
					</div>
				</section>

				<section class="mm-view" data-mm-panel="products">
					<article class="mm-panel"><div class="mm-panel-head mm-products-head"><div><span class="mm-kicker">محصولات قهرمان</span><h2>فروش، بازدید و نرخ تبدیل همه محصولات</h2><small class="mm-panel-hint">روی عنوان هر ستون بزنید تا از بیشترین به کمترین یا برعکس مرتب شود. برای دیدن منابع ورودی، روی ردیف محصول کلیک کنید.</small><small class="mm-panel-hint mm-products-period" id="mm-products-period">در حال خواندن بازه انتخابی…</small></div><div class="mm-product-tools"><label>نمایش <select id="mm-products-per-page"><option value="20">۲۰</option><option value="50">۵۰</option><option value="100">۱۰۰</option><option value="all">همه</option></select> محصول</label><button class="mm-export" data-export="products">خروجی CSV</button></div></div><div class="mm-table-wrap"><table class="mm-table mm-products-table"><thead><tr><th><button type="button" class="mm-sort" data-product-sort="rank">رتبه</button></th><th><button type="button" class="mm-sort" data-product-sort="product">محصول</button></th><th><button type="button" class="mm-sort" data-product-sort="quantity">تعداد فروش</button></th><th><button type="button" class="mm-sort" data-product-sort="orders">سفارش</button></th><th><button type="button" class="mm-sort" data-product-sort="views">بازدید محصول</button></th><th><button type="button" class="mm-sort" data-product-sort="conversion">تبدیل</button></th><th><button type="button" class="mm-sort is-active is-desc" data-product-sort="revenue">درآمد</button></th><th><button type="button" class="mm-sort" data-product-sort="change">مقایسه</button></th></tr></thead><tbody id="mm-products-table"></tbody></table></div><div class="mm-products-pagination" id="mm-products-pagination"><span id="mm-products-page-info">—</span><div><button type="button" id="mm-products-prev">قبلی</button><button type="button" id="mm-products-next">بعدی</button></div></div></article>
				</section>

				<section class="mm-view" data-mm-panel="gantt">
					<article class="mm-panel">
						<div class="mm-panel-head mm-gantt-panel-head"><div><span class="mm-kicker">نمای زمانی</span><h2>گانت عملکرد سایت در بازه انتخابی</h2><small class="mm-panel-hint">در بازه ۲۴ ساعت، سلول‌ها ساعتی و در بازه‌های طولانی‌تر روزانه هستند.</small></div><div class="mm-gantt-modes" id="mm-gantt-modes"><button type="button" class="is-active" data-gantt-mode="traffic">ورودی‌های سایت</button><button type="button" data-gantt-mode="gateways">درگاه‌های پرداخت</button><button type="button" data-gantt-mode="source_sales">فروش منابع</button></div></div>
						<p class="mm-note" id="mm-gantt-note">روی هر سلول بروید تا تعداد ورودی‌های همان منبع در آن بازه را ببینید.</p>
						<div class="mm-gantt" id="mm-gantt"></div>
					</article>
				</section>

				<section class="mm-view" data-mm-panel="orders">
					<div class="mm-grid mm-grid-orders"><article class="mm-panel"><div class="mm-panel-head"><div><span class="mm-kicker">وضعیت سفارش</span><h2>توزیع وضعیت‌ها</h2></div></div><div id="mm-status-cards"></div></article><article class="mm-panel mm-panel-wide"><div class="mm-panel-head"><div><span class="mm-kicker">آخرین رخدادها</span><h2>سفارش‌های اخیر</h2></div></div><div class="mm-table-wrap"><table class="mm-table"><thead><tr><th>سفارش</th><th>زمان</th><th>منبع</th><th>وضعیت</th><th>مبلغ</th><th>اطمینان</th></tr></thead><tbody id="mm-orders-table"></tbody></table></div></article></div>
				</section>


				<section class="mm-view" data-mm-panel="logs">
					<article class="mm-panel mm-panel-wide"><div class="mm-panel-head"><div><span class="mm-kicker">عیب‌یابی زنده</span><h2>سلامت ذخیره‌سازی و آخرین خطاها</h2><small class="mm-panel-hint">اگر اسکن کامل شود ولی گزارش خالی باشد، این بخش دقیقاً نشان می‌دهد داده در کدام مرحله یا جدول متوقف شده است.</small></div><div class="mm-log-actions"><button type="button" class="mm-btn mm-btn-ghost" id="mm-refresh-logs">بروزرسانی</button><button type="button" class="mm-btn mm-btn-ghost" id="mm-clear-logs">پاک کردن لاگ</button></div></div><div class="mm-health-grid" id="mm-health-grid"><span class="mm-inline-loader"></span> در حال خواندن سلامت سیستم…</div></article>
					<article class="mm-panel mm-panel-wide"><div class="mm-panel-head"><div><span class="mm-kicker">رویدادهای فنی</span><h2>لاگ مانیتور من</h2></div></div><div class="mm-log-list" id="mm-log-list"><span class="mm-inline-loader"></span> در حال خواندن لاگ‌ها…</div></article>
				</section>

				<section class="mm-view" data-mm-panel="settings">
					<div class="mm-grid mm-grid-settings">
						<article class="mm-panel"><div class="mm-panel-head"><div><span class="mm-kicker">کنترل دسترسی</span><h2>مدیران مجاز</h2></div></div><p class="mm-note">فقط افراد انتخاب‌شده منو و داده‌های مانیتورینگ را می‌بینند. مدیر فعلی برای جلوگیری از قفل‌شدن دسترسی حفظ می‌شود.</p><div class="mm-user-list" id="mm-user-list">
						<?php foreach ( $users as $user ) : ?><label><input type="checkbox" value="<?php echo esc_attr( $user->ID ); ?>" <?php checked( in_array( (int) $user->ID, $allowed, true ) || get_current_user_id() === (int) $user->ID ); ?>><span class="mm-avatar"><?php echo get_avatar( $user->ID, 34 ); ?></span><span><strong><?php echo esc_html( $user->display_name ); ?></strong><small><?php echo esc_html( $user->user_email ); ?></small></span></label><?php endforeach; ?>
						</div></article>
						<article class="mm-panel"><div class="mm-panel-head"><div><span class="mm-kicker">منابع اختصاصی</span><h2>قانون تشخیص منبع</h2></div></div><p class="mm-note">هر خط: <code>کلید|نام نمایشی|عبارت یا دامنه</code> — چند عبارت را با ویرگول جدا کنید.</p><textarea id="mm-custom-sources" rows="8" placeholder="bale|بله|ble.ir,bale.ai"><?php echo esc_textarea( (string) get_option( 'mm_custom_sources', '' ) ); ?></textarea></article>
						<article class="mm-panel mm-panel-wide"><div class="mm-panel-head"><div><span class="mm-kicker">سلامت سیستم</span><h2>منابع داده و سازگاری</h2></div></div><div class="mm-diagnostics">
						<?php foreach ( $diagnostics as $key => $value ) : ?><div><span><?php echo esc_html( self::diagnostic_label( $key ) ); ?></span><?php if ( is_array( $value ) ) : ?><strong class="<?php echo $value['exists'] ? 'is-ok' : 'is-bad'; ?>"><?php echo $value['exists'] ? 'فعال — ' . esc_html( number_format_i18n( $value['rows'] ) ) . ' رکورد' : 'یافت نشد'; ?></strong><?php else : ?><strong class="<?php echo $value ? 'is-ok' : 'is-muted'; ?>"><?php echo $value ? 'فعال' : 'غیرفعال'; ?></strong><?php endif; ?></div><?php endforeach; ?>
						</div></article>
					</div>
					<div class="mm-settings-actions"><button class="mm-btn mm-btn-primary" id="mm-save-settings">ذخیره تنظیمات</button><span id="mm-settings-message"></span></div>
				</section>
			</main>
		</div>
		<?php
	}

	private static function diagnostic_label( $key ) {
		$labels = array(
			'visits' => 'رهگیری مستقل مانیتور من', 'audience' => 'کاربران و نشست‌ها', 'hourly' => 'آمار تجمیعی ورودی', 'orders' => 'بانک سفارش‌ها', 'items' => 'اقلام سفارش', 'logs' => 'لاگ فنی مانیتور من',
			'legacy_traffic' => 'افزونه قدیمی منابع ورودی', 'legacy_products' => 'افزونه قدیمی بازدید محصول', 'hpos' => 'HPOS ووکامرس',
		);
		return $labels[ $key ] ?? $key;
	}
}
