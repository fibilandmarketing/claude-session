<?php

defined( 'ABSPATH' ) || exit;

final class MM_Attribution {
	const VISITOR_COOKIE = 'mm_visitor_id';
	const SESSION_COOKIE      = 'mm_session_id';
	const SESSION_ATTR_COOKIE = 'mm_session_attribution';
	const ATTR_COOKIE         = 'mm_attribution';
	const ATTR_TTL       = 2592000;
	const SESSION_TTL    = 1800;
	const BOT_PATTERN    = '/bot|crawl|crawler|spider|slurp|headless|lighthouse|pagespeed|preview|monitor|uptime|facebookexternalhit|pingdom/i';

	private static $current_source = null;
	private static $immediate_tracker_printed = false;

	public static function boot() {
		/*
		 * v1.4.2: HTML responses no longer send Set-Cookie. All tracking cookies
		 * are issued by the tracker endpoint, so WP Rocket / Cloudflare / ArvanCloud
		 * can serve every public page from cache. Visits are written by the
		 * lightweight collect.php endpoint; REST and admin-ajax stay as fallbacks.
		 */
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'wp_ajax_mm_record_visit', array( __CLASS__, 'ajax_record_visit' ) );
		add_action( 'wp_ajax_nopriv_mm_record_visit', array( __CLASS__, 'ajax_record_visit' ) );

		/*
		 * Keep Monitor Man active for every frontend visitor. Logged-in customers
		 * use the same tracker, but it is loaded after the first interaction window
		 * so analytics cannot compete with the personalized page's first render.
		 */
		add_action( 'wp_head', array( __CLASS__, 'print_immediate_tracker' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'print_immediate_tracker' ), 1 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'mark_tracker_script' ), 10, 3 );
		foreach ( array( 'rocket_delay_js_exclusions', 'rocket_exclude_defer_js', 'rocket_minify_excluded_external_js' ) as $rocket_filter ) {
			add_filter( $rocket_filter, array( __CLASS__, 'rocket_exclusions' ) );
		}
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attach_to_order' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'attach_to_store_order' ), 20, 1 );
		add_action( 'woocommerce_checkout_order_created', array( 'MM_Repository', 'sync_order_object' ), 100, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( 'MM_Repository', 'sync_order_object' ), 100, 1 );
		add_action( 'woocommerce_order_status_changed', array( 'MM_Repository', 'sync_order_id' ), 100, 1 );
	}

	private static function is_logged_in_frontend() {
		return function_exists( 'is_user_logged_in' ) && is_user_logged_in();
	}

	private static function tracker_config() {
		$context = self::tracker_page_context();
		return array(
			'collector'      => esc_url_raw( MM_URL . 'collect.php' ),
			'endpoint'       => esc_url_raw( rest_url( 'monitor-man/v1/visit' ) ),
			'ajaxEndpoint'   => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'pageType'       => $context['page_type'],
			'objectId'       => $context['object_id'],
			'sessionTtlMs'   => self::SESSION_TTL * 1000,
			'storageVersion' => 'v2',
		);
	}

	private static function tracker_page_context() {
		$page_type = 'page';
		$object_id = get_queried_object_id();
		if ( function_exists( 'is_product' ) && is_product() ) {
			$page_type = 'product';
		} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$page_type = 'product_category';
		} elseif ( function_exists( 'is_cart' ) && is_cart() ) {
			$page_type = 'cart';
		} elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$page_type = 'checkout';
		}
		return array( 'page_type' => $page_type, 'object_id' => (int) $object_id );
	}

	/**
	 * Tiny first-paint tracker. It is deliberately inline and tagged with
	 * data-nowprocket so a cached page does not wait for interaction before the
	 * acquisition hit is sent. The footer hook is only a safety net for themes
	 * that omit wp_head(); a global guard prevents a duplicate send.
	 */
	/**
	 * Tiny first-paint tracker. It is deliberately inline and tagged with
	 * data-nowprocket so a cached page does not wait for interaction before the
	 * acquisition hit is sent. The footer hook is only a safety net for themes
	 * that omit wp_head(); a global guard prevents a duplicate send.
	 *
	 * The markup is identical for every guest (no user-agent check in PHP): the
	 * page that primes a full-page cache may be requested by a crawler or uptime
	 * monitor, and the cached copy must still track real visitors. Bots are
	 * filtered in the browser and again by the endpoint with the same pattern.
	 *
	 * Delivery order: collect.php (SHORTINIT, one INSERT) via fetch keepalive at
	 * low priority, then the original admin-ajax sendBeacon / REST chain if the
	 * collector is blocked or missing. Duplicate deliveries are harmless because
	 * the visit row is keyed by session + URL + 10-minute bucket (INSERT IGNORE).
	 */
	public static function print_immediate_tracker() {
		if ( is_admin() || self::$immediate_tracker_printed ) {
			return;
		}

		self::$immediate_tracker_printed = true;
		$config = self::tracker_config();
		if ( self::is_logged_in_frontend() ) {
			$tracker_url = MM_URL . 'assets/tracker.min.js?ver=' . rawurlencode( MM_VERSION );
			?>
			<script id="monitor-man-immediate-tracker" data-nowprocket data-cfasync="false">
			(function(w,d,c,u){'use strict';if(w.__monitorManTracked||w.__monitorManDeferredScheduled)return;w.__monitorManDeferredScheduled=1;function load(){if(w.__monitorManTrackerLoaded)return;w.__monitorManTrackerLoaded=1;w.MonitorManTracker=c;var s=d.createElement('script');s.src=u;s.async=true;s.setAttribute('data-nowprocket','');s.setAttribute('data-cfasync','false');(d.head||d.documentElement).appendChild(s);}function schedule(){w.setTimeout(function(){if(w.requestIdleCallback){w.requestIdleCallback(load,{timeout:3000});}else{load();}},10000);}if(d.readyState==='complete'){schedule();}else{w.addEventListener('load',schedule,{once:true,passive:true});}})(window,document,<?php echo wp_json_encode( $config ); ?>,<?php echo wp_json_encode( esc_url( $tracker_url ) ); ?>);
			</script>
			<?php
			return;
		}
		?>
		<script id="monitor-man-immediate-tracker" data-nowprocket data-cfasync="false">
		(function(w,d,c){'use strict';if(w.__monitorManTracked)return;var ua=navigator.userAgent||'';if(!ua||<?php echo self::BOT_PATTERN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constant regex literal. ?>.test(ua))return;w.__monitorManTracked=1;var n=Date.now(),ttl=parseInt(c.sessionTtlMs||1800000,10),ver=c.storageVersion||'v2',vk='mm_vid_'+ver,sk='mm_sid_'+ver,lk='mm_last_'+ver,ck='mm_cx_'+ver;function id(){if(w.crypto&&typeof w.crypto.randomUUID==='function')return w.crypto.randomUUID();return'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,function(q){var r=Math.random()*16|0,v=q==='x'?r:(r&3|8);return v.toString(16);});}function g(k){try{return localStorage.getItem(k)||'';}catch(e){return'';}}function p(k,v){try{localStorage.setItem(k,v);}catch(e){}}function host(u){try{return(new URL(u,w.location.href)).hostname.replace(/^www\./,'').toLowerCase();}catch(e){return'';}}var ref=d.referrer||'',rh=host(ref),oh=w.location.hostname.replace(/^www\./,'').toLowerCase(),q=new URLSearchParams(w.location.search),campaign=!!(q.get('utm_source')||q.get('gclid')||q.get('fbclid')||q.get('torob_clid')),gatewayReturn=/order-received/i.test(w.location.pathname)||q.has('wc-api'),external=!!rh&&rh!==oh&&!gatewayReturn,vid=g(vk)||id(),sid=g(sk),last=parseInt(g(lk)||'0',10),fresh=!sid||!last||n-last>ttl||external||campaign;if(fresh)sid=id();p(vk,vid);p(sk,sid);p(lk,String(n));var data={page_url:w.location.href.split('#')[0],page_type:c.pageType||'page',object_id:parseInt(c.objectId||0,10),referrer:ref,visitor_id:vid,session_id:sid,new_session:fresh?'1':'0',transport:'head_beacon'};function form(a){var f=new FormData();if(a)f.append('action','mm_record_visit');Object.keys(data).forEach(function(k){f.append(k,data[k]);});return f;}function legacy(){var sent=false;try{if(navigator.sendBeacon&&c.ajaxEndpoint)sent=navigator.sendBeacon(c.ajaxEndpoint,form(1));}catch(e){}if(sent)return;function ajax(){if(!c.ajaxEndpoint)return Promise.reject(new Error('ajax unavailable'));return fetch(c.ajaxEndpoint,{method:'POST',credentials:'same-origin',cache:'no-store',keepalive:true,body:form(1)});}if(c.endpoint&&w.fetch){fetch(c.endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',keepalive:true,headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}).then(function(r){if(!r.ok)throw new Error('rest failed');}).catch(ajax).catch(function(){});}else if(w.fetch){ajax().catch(function(){});}}var bad=parseInt(g(ck)||'0',10);if(c.collector&&w.fetch&&!(bad&&n-bad<86400000)){fetch(c.collector,{method:'POST',credentials:'same-origin',cache:'no-store',keepalive:true,priority:'low',body:form(0)}).then(function(r){if(!r.ok){p(ck,String(Date.now()));throw new Error('collector '+r.status);}}).catch(function(){legacy();});}else{legacy();}})(window,document,<?php echo wp_json_encode( $config ); ?>);
		</script>
		<?php
	}

	public static function mark_tracker_script( $tag, $handle, $src = '' ) {
		if ( 'mm-tracker' !== $handle ) {
			return $tag;
		}
		// WP Rocket / Cloudflare must not postpone analytics until user interaction.
		if ( false === strpos( $tag, 'data-nowprocket' ) ) {
			$tag = str_replace( '<script ', '<script data-nowprocket data-cfasync="false" ', $tag );
		}
		return $tag;
	}

	public static function rocket_exclusions( $excluded ) {
		$excluded = is_array( $excluded ) ? $excluded : array();
		foreach ( array( 'monitor-man/assets/tracker.min.js', 'tracker.min.js', 'mm-tracker', 'MonitorManTracker', 'monitor-man-immediate-tracker', '__monitorManTracked' ) as $needle ) {
			if ( ! in_array( $needle, $excluded, true ) ) {
				$excluded[] = $needle;
			}
		}
		return $excluded;
	}

	public static function register_routes() {
		register_rest_route(
			'monitor-man/v1',
			'/visit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'record_visit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function record_visit( WP_REST_Request $request ) {
		$params = array();
		foreach ( self::hit_param_keys() as $key ) {
			$params[ $key ] = $request->get_param( $key );
		}
		$result = self::record_hit( $params, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response( $result );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	private static function hit_param_keys() {
		return array( 'page_url', 'page_type', 'object_id', 'referrer', 'visitor_id', 'session_id', 'new_session', 'transport' );
	}

	/**
	 * Store one tracker hit. Shared by collect.php, REST and admin-ajax so every
	 * transport classifies and stores visits exactly the same way.
	 *
	 * It only writes the raw visit row. The hourly/audience aggregate tables are
	 * derived data and are rebuilt incrementally by
	 * MM_Repository::sync_live_aggregates() (cron + before admin reports), so a
	 * visitor's request never waits for aggregate queries.
	 *
	 * @param array $params  Hit fields (unslashed).
	 * @param int   $user_id Current user ID, 0 for guests.
	 * @return array|WP_Error
	 */
	public static function record_hit( array $params, $user_id = 0 ) {
		if ( self::is_bot() ) {
			return array( 'recorded' => false );
		}
		$param = static function ( $key ) use ( $params ) {
			return $params[ $key ] ?? null;
		};

		$page_url = esc_url_raw( (string) $param( 'page_url' ) );
		if ( ! self::is_same_site_url( $page_url ) ) {
			return new WP_Error( 'mm_invalid_url', 'نشانی صفحه معتبر نیست.', array( 'status' => 400 ) );
		}

		/*
		 * The browser owns the analytics visitor/session IDs. This is deliberate:
		 * full-page cache can bypass PHP completely and cache plugins may postpone
		 * normal footer scripts. A small localStorage identity (same pattern as the
		 * proven legacy tracker) keeps sessions stable even on fully cached pages.
		 */
		$client_visitor = self::valid_uuid( (string) $param( 'visitor_id' ) );
		$visitor_id     = $client_visitor ?: self::cookie_uuid( self::VISITOR_COOKIE );
		if ( ! $visitor_id ) {
			$visitor_id = wp_generate_uuid4();
		}
		self::set_cookie( self::VISITOR_COOKIE, $visitor_id, time() + YEAR_IN_SECONDS, true );

		$client_session = self::valid_uuid( (string) $param( 'session_id' ) );
		$session_id     = $client_session ?: self::cookie_uuid( self::SESSION_COOKIE );
		if ( ! $session_id ) {
			$session_id = wp_generate_uuid4();
		}
		self::set_cookie( self::SESSION_COOKIE, $session_id, time() + self::SESSION_TTL, true );

		$referrer = esc_url_raw( (string) $param( 'referrer' ) );
		if ( $referrer && self::is_same_site_url( $referrer ) && ! self::has_campaign_params( $page_url ) ) {
			/*
			 * v1.4.2: an internal click is a continuation of the session. Before,
			 * the previous page's URL (the referrer) was searched for source words,
			 * so leaving a ?utm_source=instagram landing page counted a second
			 * Instagram entry. Same rule as the browser tracker's "campaign" flag.
			 */
			$detected = self::source( 'internal', 'داخل سایت', 'internal', '', self::host_from_url( home_url() ) );
		} else {
			$detected = self::detect_source( $page_url, $referrer );
		}
		$new_session = filter_var( $param( 'new_session' ), FILTER_VALIDATE_BOOLEAN );
		if ( self::is_checkout_return_url( $page_url ) && ! in_array( $detected['key'] ?? 'direct', array( 'direct', 'internal' ), true ) ) {
			// A payment-gateway return is a continuation of the checkout session,
			// not a new acquisition source. Keep the existing session attribution.
			$detected    = self::source( 'internal', 'داخل سایت', 'internal', '', self::host_from_url( home_url() ) );
			$new_session = false;
		}
		$session_attr = self::read_signed_cookie( self::SESSION_ATTR_COOKIE );
		$session_attr_valid = is_array( $session_attr ) && hash_equals( (string) ( $session_attr['session_id'] ?? '' ), $session_id );

		// Traffic source is SESSION-scoped. Do not leak a 30-day order-attribution
		// cookie into a new direct visit; that was the main source-classification flaw.
		if ( ! in_array( $detected['key'] ?? 'direct', array( 'direct', 'internal' ), true ) ) {
			$source = $detected;
			$new_session = true; // a fresh external landing is a new acquisition entry.
		} elseif ( ! $new_session && $session_attr_valid && is_array( $session_attr['source'] ?? null ) ) {
			$source = $session_attr['source'];
		} else {
			$source = 'internal' === ( $detected['key'] ?? '' ) ? self::direct_source() : $detected;
		}

		if ( empty( $source['landing_url'] ) ) {
			$source['landing_url'] = $page_url;
		}
		if ( $new_session || ! $session_attr_valid ) {
			$source['landing_url'] = $page_url;
		}
		self::set_signed_cookie(
			self::SESSION_ATTR_COOKIE,
			array( 'session_id' => $session_id, 'source' => $source, 'updated_at' => time() ),
			time() + self::SESSION_TTL
		);

		// Order attribution intentionally remains longer lived than traffic source.
		if ( ! in_array( $detected['key'] ?? 'direct', array( 'direct', 'internal' ), true ) ) {
			$order_source = $detected;
			$order_source['captured_at'] = time();
			$order_source['landing_url'] = $page_url;
			self::set_signed_cookie( self::ATTR_COOKIE, $order_source, time() + self::ATTR_TTL );
			self::$current_source = $order_source;
		}

		$transport = sanitize_key( (string) $param( 'transport' ) );
		$transport = in_array( $transport, array( 'head_beacon', 'rest', 'ajax', 'fallback' ), true ) ? $transport : 'rest';
		$is_entry  = $new_session ? 1 : 0;

		$page_type = sanitize_key( (string) $param( 'page_type' ) );
		$page_type = in_array( $page_type, array( 'page', 'product', 'product_category', 'cart', 'checkout' ), true ) ? $page_type : 'page';
		$object_id = absint( $param( 'object_id' ) );
		$bucket     = (int) floor( time() / 600 );
		$event_key  = hash( 'sha256', $session_id . '|' . $page_url . '|' . $bucket );
		$tables     = MM_Installer::tables();

		global $wpdb;
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$tables['visits']}
				(event_key, visitor_id, session_id, visited_at, source_key, source_label, medium, campaign, referrer_host, landing_url, page_url, page_type, object_id, user_id, is_entry, transport)
				VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%d,%d,%d,%s)",
				$event_key,
				$visitor_id,
				$session_id,
				current_time( 'mysql' ),
				sanitize_key( $source['key'] ?? 'direct' ),
				sanitize_text_field( $source['label'] ?? 'مستقیم' ),
				sanitize_text_field( $source['medium'] ?? '' ),
				sanitize_text_field( $source['campaign'] ?? '' ),
				sanitize_text_field( $source['referrer_host'] ?? '' ),
				esc_url_raw( $source['landing_url'] ?? $page_url ),
				$page_url,
				$page_type,
				$object_id,
				absint( $user_id ),
				$is_entry,
				$transport
			)
		);

		if ( false === $result && class_exists( 'MM_Logger' ) ) {
			MM_Logger::error(
				'tracker',
				'ذخیره بازدید صفحه در بانک مانیتور من ناموفق بود.',
				array(
					'db_error'  => $wpdb->last_error,
					'page_type' => $page_type,
					'object_id' => $object_id,
					'source'    => sanitize_key( $source['key'] ?? 'direct' ),
				)
			);
		}
		return array( 'recorded' => 1 === (int) $result, 'session' => $session_id );
	}

	/**
	 * Entry point for collect.php. Runs under SHORTINIT for guests (no plugins,
	 * theme, REST server or admin bootstrap), so it must only use functions that
	 * collect.php loads.
	 *
	 * @param bool $slashed Whether WordPress added magic quotes to $_POST (full boot only).
	 * @param int  $user_id Current user ID when WordPress was fully booted.
	 */
	public static function collect( $slashed = false, $user_id = 0 ) {
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
		$send = static function ( $status, $body ) {
			if ( ! headers_sent() ) {
				http_response_code( $status );
			}
			echo wp_json_encode( $body );
			exit;
		};

		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			$send( 405, array( 'success' => false, 'data' => array( 'message' => 'Method not allowed' ) ) );
		}
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		if ( $origin && ! self::is_same_site_url( $origin ) ) {
			$send( 403, array( 'success' => false, 'data' => array( 'message' => 'مبدأ درخواست معتبر نیست.' ) ) );
		}

		$params = array();
		foreach ( self::hit_param_keys() as $key ) {
			$value          = isset( $_POST[ $key ] ) ? $_POST[ $key ] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- public analytics beacon, same as the nopriv ajax action.
			$params[ $key ] = $slashed ? wp_unslash( $value ) : $value;
		}
		if ( ! $params['transport'] ) {
			$params['transport'] = 'head_beacon';
		}

		try {
			$result = self::record_hit( $params, $user_id );
		} catch ( Throwable $e ) {
			if ( class_exists( 'MM_Logger' ) ) {
				MM_Logger::error( 'tracker', 'اجرای collect.php با خطا متوقف شد.', array( 'message' => $e->getMessage() ) );
			}
			$send( 500, array( 'success' => false, 'data' => array( 'message' => 'collector error' ) ) );
		}
		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = is_array( $error_data ) ? (int) ( $error_data['status'] ?? 400 ) : 400;
			$send( $status, array( 'success' => false, 'data' => array( 'message' => $result->get_error_message() ) ) );
		}
		$send( 200, array( 'success' => true, 'data' => $result ) );
	}

	public static function ajax_record_visit() {
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		if ( $origin && ! self::is_same_site_url( $origin ) ) {
			wp_send_json_error( array( 'message' => 'مبدأ درخواست معتبر نیست.' ), 403 );
		}
		$params = array();
		foreach ( self::hit_param_keys() as $key ) {
			$params[ $key ] = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		}
		if ( ! $params['transport'] ) {
			$params['transport'] = 'ajax';
		}
		$result = self::record_hit( $params, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status = is_array( $error_data ) ? (int) ( $error_data['status'] ?? 400 ) : 400;
			wp_send_json_error( array( 'message' => $result->get_error_message() ), $status );
		}
		wp_send_json_success( $result );
	}

	public static function attach_to_store_order( $order ) {
		self::attach_to_order( $order, array() );
	}

	public static function attach_to_order( $order, $data = array() ) {
		if ( ! is_a( $order, 'WC_Order' ) || $order->get_meta( '_mm_source_key', true ) ) {
			return;
		}

		$source = self::$current_source;
		if ( ! is_array( $source ) ) {
			$source = self::read_signed_cookie( self::ATTR_COOKIE );
		}
		if ( ! is_array( $source ) ) {
			$source = self::direct_source();
		}

		$order->update_meta_data( '_mm_source_key', sanitize_key( $source['key'] ?? 'direct' ) );
		$order->update_meta_data( '_mm_source_label', sanitize_text_field( $source['label'] ?? 'مستقیم' ) );
		$order->update_meta_data( '_mm_medium', sanitize_text_field( $source['medium'] ?? '' ) );
		$order->update_meta_data( '_mm_campaign', sanitize_text_field( $source['campaign'] ?? '' ) );
		$order->update_meta_data( '_mm_referrer_host', sanitize_text_field( $source['referrer_host'] ?? '' ) );
		$order->update_meta_data( '_mm_visitor_id', self::cookie_uuid( self::VISITOR_COOKIE ) );
		$order->update_meta_data( '_mm_attribution_version', MM_VERSION );
	}

	public static function detect_source( $url, $referrer = '' ) {
		$query = array();
		$raw   = wp_parse_url( $url, PHP_URL_QUERY );
		if ( $raw ) {
			parse_str( $raw, $query );
		}

		$utm_source = strtolower( sanitize_text_field( $query['utm_source'] ?? '' ) );
		$medium     = sanitize_text_field( $query['utm_medium'] ?? '' );
		$campaign   = sanitize_text_field( $query['utm_campaign'] ?? '' );
		$host       = self::host_from_url( $referrer );
		$haystack   = strtolower( $utm_source . ' ' . $host . ' ' . $referrer . ' ' . $url );

		if ( ! empty( $query['torob_clid'] ) || false !== strpos( $haystack, 'torob' ) ) {
			return self::source( 'torob', 'ترب', $medium ?: 'referral', $campaign, $host );
		}
		if ( false !== strpos( $haystack, 'snapp' ) || false !== strpos( $haystack, 'snapppay' ) ) {
			return self::source( 'snapp_pay', 'اسنپ‌پی', $medium ?: 'referral', $campaign, $host );
		}
		if ( ! empty( $query['gclid'] ) ) {
			return self::source( 'google_ads', 'تبلیغات گوگل', $medium ?: 'cpc', $campaign, $host );
		}
		if ( ! empty( $query['fbclid'] ) ) {
			return self::source( 'meta', 'متا', $medium ?: 'social', $campaign, $host );
		}

		$rules = self::source_rules();
		foreach ( $rules as $rule ) {
			foreach ( $rule['needles'] as $needle ) {
				if ( $needle && false !== strpos( $haystack, strtolower( $needle ) ) ) {
					return self::source( $rule['key'], $rule['label'], $medium ?: $rule['medium'], $campaign, $host );
				}
			}
		}

		if ( $utm_source ) {
			return self::source( sanitize_key( $utm_source ), $utm_source, $medium, $campaign, $host );
		}
		if ( $referrer && self::is_same_site_url( $referrer ) ) {
			return self::source( 'internal', 'داخل سایت', 'internal', '', self::host_from_url( home_url() ) );
		}
		if ( $host ) {
			return self::source( 'referral', 'ارجاع از ' . $host, 'referral', '', $host );
		}

		return self::direct_source();
	}

	public static function source_rules() {
		$rules = array(
			array( 'key' => 'google', 'label' => 'گوگل', 'medium' => 'organic', 'needles' => array( 'google.', 'googlequicksearchbox', 'com.google.android.googlequicksearchbox' ) ),
			array( 'key' => 'instagram', 'label' => 'اینستاگرام', 'medium' => 'social', 'needles' => array( 'instagram', 'utm_source=ig' ) ),
			array( 'key' => 'telegram', 'label' => 'تلگرام', 'medium' => 'social', 'needles' => array( 't.me', 'telegram' ) ),
			array( 'key' => 'whatsapp', 'label' => 'واتساپ', 'medium' => 'social', 'needles' => array( 'wa.me', 'whatsapp' ) ),
			array( 'key' => 'bale', 'label' => 'بله', 'medium' => 'social', 'needles' => array( 'ble.ir', 'bale.ai', 'utm_source=bale' ) ),
			array( 'key' => 'eitaa', 'label' => 'ایتا', 'medium' => 'social', 'needles' => array( 'eitaa.com', 'eitaa' ) ),
			array( 'key' => 'bing', 'label' => 'بینگ', 'medium' => 'organic', 'needles' => array( 'bing.com' ) ),
			array( 'key' => 'yahoo', 'label' => 'یاهو', 'medium' => 'organic', 'needles' => array( 'yahoo.' ) ),
			array( 'key' => 'aparat', 'label' => 'آپارات', 'medium' => 'social', 'needles' => array( 'aparat.com' ) ),
			array( 'key' => 'email', 'label' => 'ایمیل', 'medium' => 'email', 'needles' => array( 'utm_medium=email', 'newsletter' ) ),
			array( 'key' => 'emalls', 'label' => 'ایمالز', 'medium' => 'referral', 'needles' => array( 'emalls.ir' ) ),
			array( 'key' => 'digikala', 'label' => 'دیجی‌کالا', 'medium' => 'referral', 'needles' => array( 'digikala.com' ) ),
		);

		$custom = (string) get_option( 'mm_custom_sources', '' );
		foreach ( preg_split( '/\r\n|\r|\n/', $custom ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) >= 3 && sanitize_key( $parts[0] ) && $parts[2] ) {
				$rules[] = array(
					'key'     => sanitize_key( $parts[0] ),
					'label'   => sanitize_text_field( $parts[1] ),
					'medium'  => 'referral',
					'needles' => array_filter( array_map( 'trim', explode( ',', $parts[2] ) ) ),
				);
			}
		}

		return $rules;
	}

	private static function source( $key, $label, $medium, $campaign, $host ) {
		return array(
			'key'           => sanitize_key( $key ),
			'label'         => sanitize_text_field( $label ),
			'medium'        => sanitize_text_field( $medium ),
			'campaign'      => sanitize_text_field( $campaign ),
			'referrer_host' => sanitize_text_field( $host ),
		);
	}

	private static function direct_source() {
		return self::source( 'direct', 'مستقیم', '', '', '' );
	}

	private static function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return esc_url_raw( $scheme . $host . $uri );
	}

	private static function host_from_url( $url ) {
		$url  = trim( (string) $url );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( ! $host && $url && false === strpos( $url, '://' ) ) {
			$host = strtolower( (string) wp_parse_url( 'https://' . ltrim( $url, '/' ), PHP_URL_HOST ) );
		}
		return preg_replace( '/^www\./', '', $host );
	}

	private static function is_same_site_url( $url ) {
		return $url && self::host_from_url( $url ) === self::host_from_url( home_url() );
	}

	private static function has_campaign_params( $url ) {
		$query = array();
		$raw   = wp_parse_url( $url, PHP_URL_QUERY );
		if ( $raw ) {
			parse_str( $raw, $query );
		}
		foreach ( array( 'utm_source', 'gclid', 'fbclid', 'torob_clid' ) as $key ) {
			if ( ! empty( $query[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	private static function is_checkout_return_url( $url ) {
		$path  = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$query = strtolower( (string) wp_parse_url( $url, PHP_URL_QUERY ) );
		return false !== strpos( $path, 'order-received' ) || false !== strpos( $query, 'wc-api=' );
	}

	private static function valid_uuid( $value ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^[a-f0-9\-]{32,64}$/i', $value ) ? $value : '';
	}

	private static function cookie_uuid( $name ) {
		$value = isset( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ) : '';
		return preg_match( '/^[a-f0-9\-]{32,64}$/i', $value ) ? $value : '';
	}

	private static function set_cookie( $name, $value, $expires, $httponly = true ) {
		if ( ! headers_sent() ) {
			setcookie(
				$name,
				$value,
				array(
					'expires'  => $expires,
					'path'     => '/',
					'secure'   => is_ssl(),
					'httponly' => $httponly,
					'samesite' => 'Lax',
				)
			);
		}
		$_COOKIE[ $name ] = $value;
	}

	private static function set_signed_cookie( $name, array $data, $expires ) {
		$payload = rtrim( strtr( base64_encode( wp_json_encode( $data ) ), '+/', '-_' ), '=' );
		$sign    = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		self::set_cookie( $name, $payload . '.' . $sign, $expires, true );
	}

	private static function read_signed_cookie( $name ) {
		$value = isset( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ) : '';
		if ( ! $value || false === strpos( $value, '.' ) ) {
			return null;
		}
		list( $payload, $sign ) = explode( '.', $value, 2 );
		$valid = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		if ( ! hash_equals( $valid, $sign ) ) {
			return null;
		}
		$payload = strtr( $payload, '-_', '+/' );
		$pad     = strlen( $payload ) % 4;
		if ( $pad ) {
			$payload .= str_repeat( '=', 4 - $pad );
		}
		$data = json_decode( (string) base64_decode( $payload, true ), true );
		return is_array( $data ) ? $data : null;
	}

	private static function is_bot() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
		return '' === $ua || (bool) preg_match( self::BOT_PATTERN, $ua );
	}
}
