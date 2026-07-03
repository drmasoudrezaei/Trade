<?php
/**
 * Plugin Name: Crypto Signals Pro
 * Plugin URI: https://example.com/crypto-signals-pro
 * Description: تحلیل حرفه‌ای بازار ارزهای دیجیتال، ردیابی پوزیشن نهنگ‌ها و معامله‌گران بزرگ در برابر خرده‌فروشان، و تولید سیگنال خرید/فروش بر پایه تحلیل تکنیکال، فاندامنتال و آن-چین. دسترسی به گزارش‌ها برای مشتریان قابل فروش و مدیریت است.
 * Version: 1.0.0
 * Author: Crypto Signals Pro
 * Text Domain: crypto-signals-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'CSP_VERSION', '1.0.0' );
define( 'CSP_PLUGIN_FILE', __FILE__ );
define( 'CSP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CSP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CSP_TEXT_DOMAIN', 'crypto-signals-pro' );

require_once CSP_PLUGIN_DIR . 'includes/class-csp-db.php';
require_once CSP_PLUGIN_DIR . 'includes/class-csp-activator.php';
require_once CSP_PLUGIN_DIR . 'includes/class-csp-deactivator.php';
require_once CSP_PLUGIN_DIR . 'includes/class-csp-settings.php';
require_once CSP_PLUGIN_DIR . 'includes/class-csp-data-provider.php';
require_once CSP_PLUGIN_DIR . 'includes/class-csp-indicators.php';
require_once CSP_PLUGIN_DIR . 'includes/class-csp-whale-tracker.php';
require_once CSP_PLUGIN_DIR . 'includes/class-csp-fundamental.php';
require_once CSP_PLUGIN_DIR . 'includes/class-csp-signal-engine.php';
require_once CSP_PLUGIN_DIR . 'includes/class-csp-access-control.php';
require_once CSP_PLUGIN_DIR . 'includes/class-csp-cron.php';
require_once CSP_PLUGIN_DIR . 'includes/class-csp-ajax.php';
require_once CSP_PLUGIN_DIR . 'includes/class-csp-shortcode.php';
require_once CSP_PLUGIN_DIR . 'includes/class-csp-plugin.php';

register_activation_hook( __FILE__, array( 'CSP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CSP_Deactivator', 'deactivate' ) );

if ( is_admin() ) {
	require_once CSP_PLUGIN_DIR . 'admin/class-csp-admin.php';
}

/**
 * Boots the plugin.
 */
function csp_run_plugin() {
	$plugin = new CSP_Plugin();
	$plugin->run();

	if ( is_admin() ) {
		$admin = new CSP_Admin();
		$admin->run();
	}
}
csp_run_plugin();
