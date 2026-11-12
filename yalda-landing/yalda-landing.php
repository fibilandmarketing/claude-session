<?php
/**
 * Plugin Name:       لندینگ شب یلدا
 * Plugin URI:        https://partakart.com/
 * Description:       لندینگ هدیه‌ی سازمانی شب یلدا با پیشخوان تنظیمات بخش‌به‌بخش، اتصال به ووکامرس، فرم پنج‌مرحله‌ای، محاسبه‌گر قیمت و فال حافظ.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            آرش شکیبایی
 * Text Domain:       yalda-landing
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'YL_VERSION', '1.1.0' );
define( 'YL_FILE', __FILE__ );
define( 'YL_DIR', plugin_dir_path( __FILE__ ) );
define( 'YL_URL', plugin_dir_url( __FILE__ ) );
define( 'YL_OPTION', 'yl_settings' );

require_once YL_DIR . 'includes/helpers.php';
require_once YL_DIR . 'includes/class-defaults.php';
require_once YL_DIR . 'includes/class-settings.php';
require_once YL_DIR . 'includes/class-images.php';
require_once YL_DIR . 'includes/class-data.php';
require_once YL_DIR . 'includes/class-leads.php';
require_once YL_DIR . 'includes/class-cart.php';
require_once YL_DIR . 'includes/class-frontend.php';

if ( is_admin() ) {
	require_once YL_DIR . 'includes/class-schema.php';
	require_once YL_DIR . 'includes/class-admin.php';
}

add_action( 'plugins_loaded', static function () {
	YL_Leads::init();
	YL_Cart::init();
	YL_Frontend::init();
	if ( is_admin() ) {
		YL_Admin::init();
	}
} );

register_activation_hook( __FILE__, static function () {
	YL_Leads::register_type();
	if ( false === get_option( YL_OPTION, false ) ) {
		add_option( YL_OPTION, array(), '', false );
	}
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), static function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=yalda-landing' ) ) . '">تنظیمات لندینگ</a>' );
	return $links;
} );
