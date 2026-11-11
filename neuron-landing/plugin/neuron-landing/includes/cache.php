<?php
/**
 * Page cache for the landing.
 *
 * The finished HTML of the homepage is written to uploads/neuron-landing-cache/
 * and sent as is to later visitors (no theme, no WooCommerce queries, no
 * template work). Logged-in users, previews, POST requests and URLs with
 * query strings (other than tracking parameters) always get a fresh page.
 * The file is dropped whenever the landing, a product, a price/stock, a post or
 * a category changes, and rebuilt in the background right after.
 */

defined( 'ABSPATH' ) || exit;

function nrn_cache_dir() {
	$u = wp_upload_dir( null, false );
	return trailingslashit( $u['basedir'] ) . 'neuron-landing-cache/';
}

function nrn_cache_file() {
	return nrn_cache_dir() . 'home-' . md5( home_url( '/' ) . '|' . NRN_VERSION . '|' . get_option( 'nrn_rev', 0 ) ) . '.html';
}

function nrn_cache_on() {
	return (bool) NRN_Content::get()->setting( 'cache' );
}

function nrn_cache_ttl() {
	return max( 1, (int) NRN_Content::get()->setting( 'cache_ttl' ) ) * HOUR_IN_SECONDS;
}

/** May this request be answered from / stored in the cache? */
function nrn_cacheable_request() {
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) || is_user_logged_in() || is_customize_preview() ) {
		return false;
	}
	// tracking parameters do not change the page; anything else does
	$ignore = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'yclid', '_ga', 'ref' );
	foreach ( array_keys( $_GET ) as $k ) { // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( strtolower( $k ), $ignore, true ) ) {
			return false;
		}
	}
	foreach ( array_keys( $_COOKIE ) as $c ) {
		if ( 0 === strpos( $c, 'comment_author_' ) || 0 === strpos( $c, 'wp-postpass_' ) ) {
			return false;
		}
	}
	return true;
}

/** Sends the cached file and stops, if there is a fresh one. */
function nrn_cache_send() {
	$file = nrn_cache_file();
	if ( is_readable( $file ) && ( time() - filemtime( $file ) ) < nrn_cache_ttl() ) {
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		header( 'X-NRN-Cache: HIT' );
		header( 'Vary: Accept-Encoding' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $file ) ) . ' GMT' );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}

/*
 * Early answer: as soon as plugins are loaded, a request for the bare homepage
 * URL is served from the file (skips the theme, the main query and templates).
 */
add_action( 'plugins_loaded', function () {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}
	if ( ! nrn_cache_on() || ! NRN_Content::get()->setting( 'enabled' ) || ! nrn_cacheable_request() ) {
		return;
	}
	$req  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== $req && untrailingslashit( $req ) === untrailingslashit( $home ) ) {
		nrn_cache_send();
	}
}, 1 );

// Otherwise: serve / start storing right after the main query (we then know it is the homepage).
add_action( 'template_redirect', 'nrn_cache_serve', 0 );
function nrn_cache_serve() {
	if ( ! nrn_cache_on() || ! nrn_is_active_request() || ! nrn_cacheable_request() ) {
		return;
	}
	nrn_cache_send();
	header( 'X-NRN-Cache: MISS' );
	ob_start( 'nrn_cache_store' );
}

function nrn_cache_store( $html ) {
	if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
		return $html;
	}
	if ( http_response_code() !== 200 || strlen( $html ) < 10000 || false === stripos( $html, '</html>' ) || false === strpos( $html, 'nrn-d' ) ) {
		return $html;
	}
	$dir = nrn_cache_dir();
	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return $html;
	}
	if ( ! file_exists( $dir . 'index.php' ) ) {
		@file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
	}
	$out = $html . "\n<!-- neuron-landing page cache: " . gmdate( 'Y-m-d H:i:s' ) . ' UTC -->';
	$tmp = $dir . uniqid( 'tmp-', true );
	if ( false !== @file_put_contents( $tmp, $out ) ) { // phpcs:ignore
		@rename( $tmp, nrn_cache_file() ); // phpcs:ignore -- atomic swap: visitors never read a half-written file
	}
	return $html;
}

/** Drops every cached copy (and rebuilds the page shortly after). */
function nrn_cache_purge( $warm = true ) {
	$dir = nrn_cache_dir();
	if ( is_dir( $dir ) ) {
		foreach ( (array) glob( $dir . 'home-*.html' ) as $f ) {
			@unlink( $f ); // phpcs:ignore
		}
	}
	if ( $warm && nrn_cache_on() && ! wp_next_scheduled( 'nrn_cache_warm' ) ) {
		wp_schedule_single_event( time() + 10, 'nrn_cache_warm' );
	}
}

add_action( 'nrn_cache_warm', function () {
	wp_remote_get( home_url( '/' ), array( 'timeout' => 20, 'blocking' => false, 'sslverify' => false, 'headers' => array( 'User-Agent' => 'neuron-landing-cache-warmup' ) ) );
} );

/** A change that can show on the landing: new asset version (busts product/HTML caches) + purge. */
function nrn_content_changed() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	nrn_bump_rev();
	nrn_cache_purge();
}

// posts (magazine) and products (carousel, prices, stock)
add_action( 'save_post', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post ) {
		return;
	}
	if ( in_array( $post->post_type, array( 'post', 'product', 'product_variation', 'page' ), true ) ) {
		nrn_content_changed();
	}
}, 20, 2 );
add_action( 'deleted_post', 'nrn_content_changed' );
add_action( 'trashed_post', 'nrn_content_changed' );
foreach ( array( 'woocommerce_update_product', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status', 'woocommerce_reduce_order_stock', 'edited_product_cat', 'edited_category', 'update_option_woocommerce_currency', 'switch_theme', 'update_option_blogname' ) as $nrn_hook ) {
	add_action( $nrn_hook, 'nrn_content_changed' );
}

/** Cache state for the settings screen. */
function nrn_cache_status() {
	$file = nrn_cache_file();
	if ( ! is_readable( $file ) ) {
		return null;
	}
	return array( 'time' => filemtime( $file ), 'size' => filesize( $file ) );
}
