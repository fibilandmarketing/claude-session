<?php
/**
 * Plugin Name: مانیتور من
 * Description: داشبورد حرفه‌ای مانیتورینگ ورودی‌ها، فروش ووکامرس، انتساب منبع و عملکرد محصولات.
 * Version:     1.4.2
 * Author:      آقا طاها
 * Text Domain: monitor-man
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 7.8
 */

defined( 'ABSPATH' ) || exit;

define( 'MM_VERSION', '1.4.2' );
define( 'MM_FILE', __FILE__ );
define( 'MM_PATH', plugin_dir_path( __FILE__ ) );
define( 'MM_URL', plugin_dir_url( __FILE__ ) );

require_once MM_PATH . 'includes/class-mm-installer.php';
require_once MM_PATH . 'includes/class-mm-logger.php';
require_once MM_PATH . 'includes/class-mm-attribution.php';
require_once MM_PATH . 'includes/class-mm-repository.php';
require_once MM_PATH . 'includes/class-mm-admin.php';

register_activation_hook( __FILE__, array( 'MM_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MM_Installer', 'deactivate' ) );
add_action(
	MM_Installer::AGG_EVENT,
	static function () {
		MM_Repository::sync_live_aggregates( 20.0 );
	}
);

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		MM_Installer::maybe_upgrade();
		MM_Attribution::boot();
		MM_Admin::boot();
	},
	20
);
