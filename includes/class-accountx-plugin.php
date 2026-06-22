<?php
/**
 * Main plugin bootstrap.
 *
 * @package AccountX
 */

defined( 'ABSPATH' ) || exit;

require_once ACCOUNTX_PATH . 'includes/class-accountx-settings.php';
require_once ACCOUNTX_PATH . 'includes/class-accountx-subaccounts.php';
require_once ACCOUNTX_PATH . 'includes/class-accountx-orders.php';
require_once ACCOUNTX_PATH . 'includes/class-accountx-switching.php';
require_once ACCOUNTX_PATH . 'includes/class-accountx-my-account.php';
require_once ACCOUNTX_PATH . 'includes/class-accountx-admin.php';

/**
 * AccountX plugin container.
 */
final class AccountX_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var AccountX_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings service.
	 *
	 * @var AccountX_Settings
	 */
	public $settings;

	/**
	 * Subaccounts service.
	 *
	 * @var AccountX_Subaccounts
	 */
	public $subaccounts;

	/**
	 * Orders service.
	 *
	 * @var AccountX_Orders
	 */
	public $orders;

	/**
	 * Switching service.
	 *
	 * @var AccountX_Switching
	 */
	public $switching;

	/**
	 * My Account integration.
	 *
	 * @var AccountX_My_Account
	 */
	public $my_account;

	/**
	 * Admin integration.
	 *
	 * @var AccountX_Admin
	 */
	public $admin;

	/**
	 * Get singleton instance.
	 *
	 * @return AccountX_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );
			return;
		}

		$this->settings    = new AccountX_Settings();
		$this->subaccounts = new AccountX_Subaccounts( $this->settings );
		$this->orders      = new AccountX_Orders( $this->subaccounts );
		$this->switching   = new AccountX_Switching( $this->settings, $this->subaccounts );
		$this->my_account  = new AccountX_My_Account( $this->settings, $this->subaccounts, $this->orders, $this->switching );
		$this->admin       = new AccountX_Admin( $this->settings );
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! get_option( 'accountx_settings' ) ) {
			add_option( 'accountx_settings', AccountX_Settings::defaults() );
		}

		if ( class_exists( 'AccountX_My_Account' ) ) {
			AccountX_My_Account::add_endpoint();
			flush_rewrite_rules();
		}
	}

	/**
	 * Check WooCommerce dependency.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Dependency notice.
	 *
	 * @return void
	 */
	public function woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		esc_html_e( 'AccountX requires WooCommerce to be installed and active.', 'accountx' );
		echo '</p></div>';
	}
}
