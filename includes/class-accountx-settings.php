<?php
/**
 * Settings service.
 *
 * @package AccountX
 */

defined( 'ABSPATH' ) || exit;

/**
 * AccountX settings wrapper.
 */
class AccountX_Settings {
	const OPTION_NAME = 'accountx_settings';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'          => 'yes',
			'mode'             => 'multi_user',
			'subaccount_limit' => 10,
			'user_switching'   => 'yes',
		);
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function all() {
		$saved = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Get setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Is AccountX enabled?
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === $this->get( 'enabled', 'yes' );
	}

	/**
	 * Is user switching enabled?
	 *
	 * @return bool
	 */
	public function is_switching_enabled() {
		return 'yes' === $this->get( 'user_switching', 'yes' );
	}

	/**
	 * Current mode.
	 *
	 * @return string
	 */
	public function mode() {
		$mode = (string) $this->get( 'mode', 'multi_user' );

		return in_array( $mode, array( 'multi_user', 'sub_user' ), true ) ? $mode : 'multi_user';
	}

	/**
	 * User-facing subaccount label.
	 *
	 * @return string
	 */
	public function account_label() {
		return 'sub_user' === $this->mode() ? __( 'Subaccount', 'accountx' ) : __( 'Team Member', 'accountx' );
	}

	/**
	 * Subaccount limit.
	 *
	 * @return int
	 */
	public function subaccount_limit() {
		return max( 1, absint( $this->get( 'subaccount_limit', 10 ) ) );
	}
}
