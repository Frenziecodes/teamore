<?php
/**
 * Plugin Name: AccountX
 * Plugin URI:  https://github.com/38zo/accountx
 * Description: Turn WooCommerce customer accounts into team accounts with lightweight subaccount management.
 * Version:     1.0.0
 * Author:      38zo
 * Author URI:  https://github.com/38zo
 * Text Domain: accountx
 * Domain Path: /languages
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires PHP: 7.4
 * Requires at least: 6.2
 * WC requires at least: 7.0
 * WC tested up to: 9.9
 *
 * @package AccountX
 */

defined( 'ABSPATH' ) || exit;

define( 'ACCOUNTX_VERSION', '1.0.0' );
define( 'ACCOUNTX_FILE', __FILE__ );
define( 'ACCOUNTX_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACCOUNTX_URL', plugin_dir_url( __FILE__ ) );

require_once ACCOUNTX_PATH . 'includes/class-accountx-plugin.php';

register_activation_hook( __FILE__, array( 'AccountX_Plugin', 'activate' ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'AccountX_Plugin', 'instance' ) );
