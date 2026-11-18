<?php
/**
 * Plugin Name:       Tiadem Tennis Landing — لندینگ تنیسی تیادم
 * Plugin URI:        https://tiadem.ir
 * Description:       لندینگ اختصاصی محصولات تنیسی تیادم؛ کاملاً سازگار با ووکامرس، با پنل مدیریت محتوای هر سکشن، افزودن به سبد AJAX، انتخاب سریع محصولات متغیر و انیمیشن‌های سبک.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Tiadem
 * Text Domain:       tiadem-tl
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   11.3
 * License:           GPLv2 or later
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

define( 'TDL_VERSION', '1.0.0' );
define( 'TDL_FILE', __FILE__ );
define( 'TDL_DIR', plugin_dir_path( __FILE__ ) );
define( 'TDL_URL', plugin_dir_url( __FILE__ ) );
define( 'TDL_TEMPLATE', 'tiadem-tennis-landing.php' );
define( 'TDL_OPTION', 'tdl_settings' );

require_once TDL_DIR . 'includes/helpers.php';
require_once TDL_DIR . 'includes/class-jalali.php';
require_once TDL_DIR . 'includes/class-defaults.php';
require_once TDL_DIR . 'includes/class-schema.php';
require_once TDL_DIR . 'includes/class-settings.php';
require_once TDL_DIR . 'includes/class-products.php';
require_once TDL_DIR . 'includes/class-wishlist.php';
require_once TDL_DIR . 'includes/class-ajax.php';
require_once TDL_DIR . 'includes/class-render.php';
require_once TDL_DIR . 'includes/class-frontend.php';
require_once TDL_DIR . 'includes/class-installer.php';
require_once TDL_DIR . 'includes/class-admin.php';

// Declare compatibility with WooCommerce HPOS and cart/checkout blocks.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', TDL_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', TDL_FILE, true );
		}
	}
);

register_activation_hook( __FILE__, array( 'TDL_Installer', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'tiadem-tl', false, dirname( plugin_basename( TDL_FILE ) ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'افزونه «لندینگ تنیسی تیادم» برای کار به ووکامرس نیاز دارد. لطفاً ووکامرس را نصب و فعال کنید.', 'tiadem-tl' ) . '</p></div>';
				}
			);
			return;
		}

		TDL_Settings::init();
		TDL_Products::init();
		TDL_Ajax::init();
		TDL_Frontend::init();
		TDL_Installer::init();
		if ( is_admin() ) {
			TDL_Admin::init();
		}
	}
);
