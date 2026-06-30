<?php
/**
 * Main plugin bootstrap.
 *
 * @package TeaMore
 */

defined( 'ABSPATH' ) || exit;

require_once TEAMORE_PATH . 'includes/class-teamore-settings.php';
require_once TEAMORE_PATH . 'includes/class-teamore-subaccounts.php';
require_once TEAMORE_PATH . 'includes/class-teamore-orders.php';
require_once TEAMORE_PATH . 'includes/class-teamore-switching.php';
require_once TEAMORE_PATH . 'includes/class-teamore-my-account.php';
require_once TEAMORE_PATH . 'includes/class-teamore-admin.php';

/**
 * TeaMore plugin container.
 */
final class Teamore_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Teamore_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings service.
	 *
	 * @var Teamore_Settings
	 */
	public $settings;

	/**
	 * Subaccounts service.
	 *
	 * @var Teamore_Subaccounts
	 */
	public $subaccounts;

	/**
	 * Orders service.
	 *
	 * @var Teamore_Orders
	 */
	public $orders;

	/**
	 * Switching service.
	 *
	 * @var Teamore_Switching
	 */
	public $switching;

	/**
	 * My Account integration.
	 *
	 * @var Teamore_My_Account
	 */
	public $my_account;

	/**
	 * Admin integration.
	 *
	 * @var Teamore_Admin
	 */
	public $admin;

	/**
	 * Get singleton instance.
	 *
	 * @return Teamore_Plugin
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

		$this->settings    = new Teamore_Settings();
		$this->subaccounts = new Teamore_Subaccounts( $this->settings );
		$this->switching   = new Teamore_Switching( $this->settings, $this->subaccounts );
		$this->orders      = new Teamore_Orders( $this->settings, $this->subaccounts, $this->switching );
		$this->my_account  = new Teamore_My_Account( $this->settings, $this->subaccounts, $this->orders, $this->switching );
		$this->admin       = new Teamore_Admin( $this->settings, $this->subaccounts );
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! get_option( 'teamore_settings' ) ) {
			add_option( 'teamore_settings', Teamore_Settings::defaults() );
		}

		if ( class_exists( 'Teamore_My_Account' ) ) {
			Teamore_My_Account::add_endpoint();
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
		esc_html_e( 'TeaMore requires WooCommerce to be installed and active.', 'teamore' );
		echo '</p></div>';
	}
}
