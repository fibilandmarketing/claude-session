<?php
/**
 * Monitor Man lightweight tracker endpoint.
 *
 * admin-ajax.php boots all of WordPress plus every plugin's admin_init for each
 * page view. This endpoint boots WordPress with SHORTINIT (config, database,
 * options, object cache only), records the visit with the same code path as the
 * REST/admin-ajax endpoints and exits. Logged-in visitors get a full boot so the
 * visit keeps its user ID.
 *
 * If this file cannot run (blocked by a security rule, unusual directory
 * layout) the browser tracker falls back to admin-ajax automatically.
 */

// phpcs:disable WordPress.Security.NonceVerification -- public analytics beacon.

if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
	http_response_code( 405 );
	header( 'Allow: POST' );
	exit;
}

$mm_logged_in = false;
foreach ( array_keys( $_COOKIE ) as $mm_cookie_name ) {
	if ( 0 === strpos( (string) $mm_cookie_name, 'wordpress_logged_in_' ) ) {
		$mm_logged_in = true;
		break;
	}
}
if ( ! $mm_logged_in ) {
	define( 'SHORTINIT', true );
}

$mm_wp_load = '';
$mm_dir     = __DIR__;
for ( $mm_i = 0; $mm_i < 8 && ! $mm_wp_load; $mm_i++ ) {
	$mm_dir = dirname( $mm_dir );
	foreach ( array( $mm_dir . '/wp-load.php', $mm_dir . '/wp/wp-load.php' ) as $mm_candidate ) {
		if ( is_file( $mm_candidate ) ) {
			$mm_wp_load = $mm_candidate;
			break;
		}
	}
}
if ( ! $mm_wp_load ) {
	http_response_code( 503 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo '{"success":false,"data":{"message":"wp-load.php not found"}}';
	exit;
}

require_once $mm_wp_load;

$mm_plugin = basename( __DIR__ ) . '/monitor-man.php';

if ( defined( 'SHORTINIT' ) && SHORTINIT ) {
	$mm_active = in_array( $mm_plugin, (array) get_option( 'active_plugins', array() ), true );
	if ( ! $mm_active && is_multisite() ) {
		$mm_sitewide = (array) get_site_option( 'active_sitewide_plugins', array() );
		$mm_active   = isset( $mm_sitewide[ $mm_plugin ] );
	}
	if ( ! $mm_active ) {
		http_response_code( 404 );
		exit;
	}

	// Only what MM_Attribution::record_hit() needs: esc_url_raw, wp_parse_url, home_url, wp_salt.
	foreach ( array( 'l10n.php', 'class-wp-textdomain-registry.php', 'kses.php', 'link-template.php', 'http.php', 'pluggable.php' ) as $mm_file ) {
		require_once ABSPATH . WPINC . '/' . $mm_file;
	}
	if ( empty( $GLOBALS['wp_textdomain_registry'] ) ) {
		$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();
	}

	require_once __DIR__ . '/includes/class-mm-installer.php';
	require_once __DIR__ . '/includes/class-mm-logger.php';
	require_once __DIR__ . '/includes/class-mm-attribution.php';

	MM_Attribution::collect( false, 0 );
}

if ( ! class_exists( 'MM_Attribution' ) ) {
	http_response_code( 404 );
	exit;
}
MM_Attribution::collect( true, get_current_user_id() );
