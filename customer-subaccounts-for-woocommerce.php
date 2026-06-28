<?php
/**
 * Plugin Name: Customer Subaccounts for WooCommerce
 * Plugin URI:  https://github.com/Frenziecodes/customer-subaccounts-for-woocommerce
 * Description: Turn WooCommerce customer accounts into team accounts with lightweight subaccount management.
 * Version:     1.0.0
 * Author:      lewisushindi
 * Author URI:  https://github.com/frenziecodes
 * Text Domain: customer-subaccounts-for-woocommerce
 * Domain Path: /languages
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires PHP: 7.4
 * Requires at least: 6.2
 * WC requires at least: 7.0
 * WC tested up to: 9.9
 *
 * @package Customer Subaccounts for WooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'CSFW_VERSION', '1.0.0' );
define( 'CSFW_FILE', __FILE__ );
define( 'CSFW_PATH', plugin_dir_path( __FILE__ ) );
define( 'CSFW_URL', plugin_dir_url( __FILE__ ) );

require_once CSFW_PATH . 'includes/class-csfw-plugin.php';

register_activation_hook( __FILE__, array( 'CSFW_Plugin', 'activate' ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'CSFW_Plugin', 'instance' ) );
