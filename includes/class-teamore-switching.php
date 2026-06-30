<?php
/**
 * Subaccount context switching.
 *
 * @package TeaMore
 */

defined( 'ABSPATH' ) || exit;

/**
 * TeaMore switching service.
 */
class Teamore_Switching {
	const SESSION_ACTIVE_SUBACCOUNT_ID = 'teamore_active_subaccount_id';

	/**
	 * Settings.
	 *
	 * @var Teamore_Settings
	 */
	private $settings;

	/**
	 * Subaccounts.
	 *
	 * @var Teamore_Subaccounts
	 */
	private $subaccounts;

	/**
	 * Constructor.
	 *
	 * @param Teamore_Settings    $settings    Settings service.
	 * @param Teamore_Subaccounts $subaccounts Subaccounts service.
	 */
	public function __construct( Teamore_Settings $settings, Teamore_Subaccounts $subaccounts ) {
		$this->settings    = $settings;
		$this->subaccounts = $subaccounts;

		add_action( 'template_redirect', array( $this, 'maybe_switch' ) );
		add_action( 'woocommerce_before_account_navigation', array( $this, 'render_switch_back_notice' ) );
	}

	/**
	 * Set or clear the active subaccount context.
	 *
	 * @return void
	 */
	public function maybe_switch() {
		if ( ! $this->settings->is_enabled() || ! $this->settings->is_switching_enabled() || ! is_user_logged_in() ) {
			return;
		}

		if ( isset( $_GET['teamore_switch_to'], $_GET['_wpnonce'] ) ) {
			$subaccount_id = absint( wp_unslash( $_GET['teamore_switch_to'] ) );

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'teamore_switch_to_' . $subaccount_id ) ) {
				wc_add_notice( __( 'Switch request could not be verified.', 'teamore' ), 'error' );
				return;
			}

			$parent_id = get_current_user_id();

			if ( $this->subaccounts->is_subaccount( $parent_id ) ) {
				wc_add_notice( __( 'Subaccounts cannot switch to other accounts.', 'teamore' ), 'error' );
				return;
			}

			if ( ! $this->subaccounts->parent_owns_subaccount( $parent_id, $subaccount_id ) ) {
				wc_add_notice( __( 'You cannot switch to this subaccount.', 'teamore' ), 'error' );
				return;
			}

			$this->set_active_subaccount_id( $subaccount_id );
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}

		if ( isset( $_GET['teamore_switch_back'], $_GET['_wpnonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'teamore_switch_back' ) ) {
				wc_add_notice( __( 'Switch back request could not be verified.', 'teamore' ), 'error' );
				return;
			}

			if ( ! $this->is_switched() ) {
				wc_add_notice( __( 'No active subaccount session was found.', 'teamore' ), 'error' );
				return;
			}

			$this->clear_active_subaccount_id();
			wp_safe_redirect( wc_get_account_endpoint_url( Teamore_My_Account::ENDPOINT ) );
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
			add_query_arg( 'teamore_switch_to', absint( $subaccount_id ), wc_get_account_endpoint_url( Teamore_My_Account::ENDPOINT ) ),
			'teamore_switch_to_' . absint( $subaccount_id )
		);
	}

	/**
	 * Get switch-back URL.
	 *
	 * @return string
	 */
	public function get_switch_back_url() {
		return wp_nonce_url( add_query_arg( 'teamore_switch_back', '1', wc_get_page_permalink( 'myaccount' ) ), 'teamore_switch_back' );
	}

	/**
	 * Is current session switched?
	 *
	 * @return bool
	 */
	public function is_switched() {
		return $this->get_active_subaccount_id() > 0;
	}

	/**
	 * Get the user ID TeaMore should use for contextual actions.
	 *
	 * WordPress authentication remains attached to the parent user. This method
	 * only affects TeaMore-specific behavior.
	 *
	 * @return int
	 */
	public function get_context_user_id() {
		$subaccount_id = $this->get_active_subaccount_id();

		return $subaccount_id > 0 ? $subaccount_id : get_current_user_id();
	}

	/**
	 * Get active subaccount ID for the current authenticated parent.
	 *
	 * @return int
	 */
	public function get_active_subaccount_id() {
		if ( ! $this->settings->is_enabled() || ! $this->settings->is_switching_enabled() || ! is_user_logged_in() ) {
			$this->clear_active_subaccount_id();
			return 0;
		}

		$parent_id = get_current_user_id();

		if ( $this->subaccounts->is_subaccount( $parent_id ) ) {
			$this->clear_active_subaccount_id();
			return 0;
		}

		$subaccount_id = $this->get_active_subaccount_id_from_session();

		if ( $subaccount_id < 1 ) {
			return 0;
		}

		if ( ! $this->subaccounts->parent_owns_subaccount( $parent_id, $subaccount_id ) ) {
			$this->clear_active_subaccount_id();
			return 0;
		}

		return $subaccount_id;
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

		echo '<p class="woocommerce-info teamore-switch-back">';
		echo esc_html__( 'You are acting as a subaccount. Your WordPress login remains your parent account.', 'teamore' ) . ' ';
		echo '<a class="button" href="' . esc_url( $this->get_switch_back_url() ) . '">' . esc_html__( 'Return to Parent', 'teamore' ) . '</a>';
		echo '</p>';
	}

	/**
	 * Store active subaccount context.
	 *
	 * @param int $subaccount_id Subaccount ID.
	 * @return void
	 */
	private function set_active_subaccount_id( $subaccount_id ) {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_ACTIVE_SUBACCOUNT_ID, absint( $subaccount_id ) );
		}
	}

	/**
	 * Get active subaccount context from session.
	 *
	 * @return int
	 */
	private function get_active_subaccount_id_from_session() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			return absint( WC()->session->get( self::SESSION_ACTIVE_SUBACCOUNT_ID ) );
		}

		return 0;
	}

	/**
	 * Clear active subaccount context.
	 *
	 * @return void
	 */
	private function clear_active_subaccount_id() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->__unset( self::SESSION_ACTIVE_SUBACCOUNT_ID );
		}
	}
}
