<?php
/**
 * Simple user switching.
 *
 * @package AccountX
 */

defined( 'ABSPATH' ) || exit;

/**
 * AccountX switching service.
 */
class AccountX_Switching {
	const SESSION_PARENT_ID = 'accountx_parent_user_id';

	/**
	 * Settings.
	 *
	 * @var AccountX_Settings
	 */
	private $settings;

	/**
	 * Subaccounts.
	 *
	 * @var AccountX_Subaccounts
	 */
	private $subaccounts;

	/**
	 * Constructor.
	 *
	 * @param AccountX_Settings    $settings    Settings service.
	 * @param AccountX_Subaccounts $subaccounts Subaccounts service.
	 */
	public function __construct( AccountX_Settings $settings, AccountX_Subaccounts $subaccounts ) {
		$this->settings    = $settings;
		$this->subaccounts = $subaccounts;

		add_action( 'template_redirect', array( $this, 'maybe_switch' ) );
		add_action( 'woocommerce_before_account_navigation', array( $this, 'render_switch_back_notice' ) );
	}

	/**
	 * Switch into or back from a subaccount.
	 *
	 * @return void
	 */
	public function maybe_switch() {
		if ( ! $this->settings->is_enabled() || ! $this->settings->is_switching_enabled() || ! is_user_logged_in() ) {
			return;
		}

		if ( isset( $_GET['accountx_switch_to'], $_GET['_wpnonce'] ) ) {
			$subaccount_id = absint( wp_unslash( $_GET['accountx_switch_to'] ) );

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'accountx_switch_to_' . $subaccount_id ) ) {
				wc_add_notice( __( 'Switch request could not be verified.', 'accountx' ), 'error' );
				return;
			}

			$parent_id = get_current_user_id();

			if ( ! $this->subaccounts->parent_owns_subaccount( $parent_id, $subaccount_id ) ) {
				wc_add_notice( __( 'You cannot switch to this subaccount.', 'accountx' ), 'error' );
				return;
			}

			$this->set_parent_session( $parent_id );
			wp_set_current_user( $subaccount_id );
			wp_set_auth_cookie( $subaccount_id );
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}

		if ( isset( $_GET['accountx_switch_back'], $_GET['_wpnonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'accountx_switch_back' ) ) {
				wc_add_notice( __( 'Switch back request could not be verified.', 'accountx' ), 'error' );
				return;
			}

			$parent_id = $this->get_parent_session();

			if ( $parent_id < 1 ) {
				wc_add_notice( __( 'No parent session was found.', 'accountx' ), 'error' );
				return;
			}

			$this->clear_parent_session();
			wp_set_current_user( $parent_id );
			wp_set_auth_cookie( $parent_id );
			wp_safe_redirect( wc_get_account_endpoint_url( 'accountx-subaccounts' ) );
			exit;
		}
	}

	/**
	 * Get switch-to URL.
	 *
	 * @param int $subaccount_id Subaccount ID.
	 * @return string
	 */
	public function get_switch_to_url( $subaccount_id ) {
		return wp_nonce_url(
			add_query_arg( 'accountx_switch_to', absint( $subaccount_id ), wc_get_account_endpoint_url( 'accountx-subaccounts' ) ),
			'accountx_switch_to_' . absint( $subaccount_id )
		);
	}

	/**
	 * Get switch-back URL.
	 *
	 * @return string
	 */
	public function get_switch_back_url() {
		return wp_nonce_url( add_query_arg( 'accountx_switch_back', '1', wc_get_page_permalink( 'myaccount' ) ), 'accountx_switch_back' );
	}

	/**
	 * Is current session switched?
	 *
	 * @return bool
	 */
	public function is_switched() {
		return $this->get_parent_session() > 0;
	}

	/**
	 * Render switch-back notice.
	 *
	 * @return void
	 */
	public function render_switch_back_notice() {
		if ( ! $this->is_switched() ) {
			return;
		}

		echo '<p class="woocommerce-info accountx-switch-back">';
		echo esc_html__( 'You are viewing this store as a subaccount.', 'accountx' ) . ' ';
		echo '<a class="button" href="' . esc_url( $this->get_switch_back_url() ) . '">' . esc_html__( 'Switch Back to Parent', 'accountx' ) . '</a>';
		echo '</p>';
	}

	/**
	 * Store parent session.
	 *
	 * @param int $parent_id Parent ID.
	 * @return void
	 */
	private function set_parent_session( $parent_id ) {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_PARENT_ID, absint( $parent_id ) );
		}
	}

	/**
	 * Get parent session.
	 *
	 * @return int
	 */
	private function get_parent_session() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			return absint( WC()->session->get( self::SESSION_PARENT_ID ) );
		}

		return 0;
	}

	/**
	 * Clear parent session.
	 *
	 * @return void
	 */
	private function clear_parent_session() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->__unset( self::SESSION_PARENT_ID );
		}
	}
}
