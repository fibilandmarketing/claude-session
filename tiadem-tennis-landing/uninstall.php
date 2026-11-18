<?php
/**
 * Uninstall: remove plugin options and caches. The landing page itself is kept.
 *
 * @package TiademTennisLanding
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'tdl_settings' );
delete_option( 'tdl_cache_ver' );
delete_option( 'tdl_page_id' );
delete_option( 'tdl_settings_backup' );
delete_transient( 'tdl_activated' );

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_tdl\_%' OR option_name LIKE '\_transient\_timeout\_tdl\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
