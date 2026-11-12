<?php
/**
 * Plugin Name:       لندینگ نورون (صفحهٔ اصلی)
 * Plugin URI:        https://noroncar.ir/
 * Description:       صفحهٔ اصلی طراحی‌شدهٔ نورون با ماشین متحرک روی مسیر، نقشهٔ نمایندگی‌ها، نسخهٔ دسکتاپ و موبایل، و پنل مدیریت کامل برای ویرایش همهٔ بخش‌ها.
 * Version:           1.3.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Neuron
 * Text Domain:       neuron-landing
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'NRN_VERSION', '1.3.0' );
define( 'NRN_FILE', __FILE__ );
define( 'NRN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NRN_URL', plugin_dir_url( __FILE__ ) );
define( 'NRN_OPTION', 'neuron_landing' );

require_once NRN_DIR . 'includes/class-nrn-content.php';
require_once NRN_DIR . 'includes/partials.php';
require_once NRN_DIR . 'includes/bnav.php';
require_once NRN_DIR . 'includes/frontend.php';
require_once NRN_DIR . 'includes/cache.php';

if ( is_admin() ) {
	require_once NRN_DIR . 'includes/admin.php';
}

register_deactivation_hook( __FILE__, function () {
	nrn_cache_purge( false );
} );

register_activation_hook( __FILE__, 'nrn_activate' );
function nrn_activate() {
	if ( false === get_option( NRN_OPTION ) ) {
		// Saved data only holds what the admin changes; everything else falls back to the design.
		add_option( NRN_OPTION, array(), '', false );
	}
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=neuron-landing' ) ) . '">تنظیمات</a>' );
	return $links;
} );
