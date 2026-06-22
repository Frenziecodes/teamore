<?php
/**
 * Subaccount service.
 *
 * @package AccountX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage parent/subaccount relationships.
 */
class AccountX_Subaccounts {
	const META_PARENT_ID = '_accountx_parent_user_id';

	/**
	 * Settings.
	 *
	 * @var AccountX_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param AccountX_Settings $settings Settings service.
	 */
	public function __construct( AccountX_Settings $settings ) {
		$this->settings = $settings;

		add_action( 'admin_init', array( $this, 'block_subaccount_admin' ) );
	}

	/**
	 * Get parent ID for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_parent_id( $user_id ) {
		return absint( get_user_meta( $user_id, self::META_PARENT_ID, true ) );
	}

	/**
	 * Check if user is a subaccount.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_subaccount( $user_id ) {
		return $this->get_parent_id( $user_id ) > 0;
	}

	/**
	 * Check ownership.
	 *
	 * @param int $parent_id     Parent user ID.
	 * @param int $subaccount_id Subaccount user ID.
	 * @return bool
	 */
	public function parent_owns_subaccount( $parent_id, $subaccount_id ) {
		return absint( $parent_id ) > 0 && absint( $parent_id ) === $this->get_parent_id( $subaccount_id );
	}

	/**
	 * Get subaccounts for parent.
	 *
	 * @param int $parent_id Parent user ID.
	 * @return WP_User[]
	 */
	public function get_subaccounts( $parent_id ) {
		return get_users(
			array(
				'meta_key'   => self::META_PARENT_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Parent/subaccount ownership is stored as user meta in the MVP.
				'meta_value' => absint( $parent_id ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Query is scoped to one parent account and capped by the configured subaccount limit.
				'orderby'    => 'registered',
				'order'      => 'ASC',
			)
		);
	}

	/**
	 * Count subaccounts.
	 *
	 * @param int $parent_id Parent user ID.
	 * @return int
	 */
	public function count_subaccounts( $parent_id ) {
		return count( $this->get_subaccounts( $parent_id ) );
	}

	/**
	 * Can parent create more subaccounts?
	 *
	 * @param int $parent_id Parent user ID.
	 * @return bool
	 */
	public function can_create_subaccount( $parent_id ) {
		return $this->count_subaccounts( $parent_id ) < $this->settings->subaccount_limit();
	}

	/**
	 * Create a subaccount.
	 *
	 * @param int    $parent_id  Parent user ID.
	 * @param string $first_name First name.
	 * @param string $last_name  Last name.
	 * @param string $email      Email.
	 * @param string $password   Password.
	 * @return int|WP_Error
	 */
	public function create_subaccount( $parent_id, $first_name, $last_name, $email, $password ) {
		$parent_id  = absint( $parent_id );
		$first_name = sanitize_text_field( $first_name );
		$last_name  = sanitize_text_field( $last_name );
		$email      = sanitize_email( $email );

		if ( ! $this->can_create_subaccount( $parent_id ) ) {
			return new WP_Error( 'accountx_limit_reached', __( 'Subaccount limit reached.', 'accountx' ) );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'accountx_invalid_email', __( 'Please enter a valid email address.', 'accountx' ) );
		}

		if ( email_exists( $email ) ) {
			return new WP_Error( 'accountx_email_exists', __( 'A user with this email address already exists.', 'accountx' ) );
		}

		if ( empty( $password ) || strlen( $password ) < 8 ) {
			return new WP_Error( 'accountx_short_password', __( 'Password must be at least 8 characters long.', 'accountx' ) );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => trim( $first_name . ' ' . $last_name ),
				'role'         => 'customer',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, self::META_PARENT_ID, $parent_id );

		return $user_id;
	}

	/**
	 * Update subaccount.
	 *
	 * @param int    $parent_id     Parent user ID.
	 * @param int    $subaccount_id Subaccount user ID.
	 * @param string $first_name    First name.
	 * @param string $last_name     Last name.
	 * @param string $email         Email.
	 * @param string $password      Password.
	 * @return true|WP_Error
	 */
	public function update_subaccount( $parent_id, $subaccount_id, $first_name, $last_name, $email, $password = '' ) {
		if ( ! $this->parent_owns_subaccount( $parent_id, $subaccount_id ) ) {
			return new WP_Error( 'accountx_forbidden', __( 'You cannot edit this subaccount.', 'accountx' ) );
		}

		$email         = sanitize_email( $email );
		$existing_user = get_user_by( 'email', $email );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'accountx_invalid_email', __( 'Please enter a valid email address.', 'accountx' ) );
		}

		if ( $existing_user && absint( $existing_user->ID ) !== absint( $subaccount_id ) ) {
			return new WP_Error( 'accountx_email_exists', __( 'A user with this email address already exists.', 'accountx' ) );
		}

		$data = array(
			'ID'           => absint( $subaccount_id ),
			'user_email'   => $email,
			'first_name'   => sanitize_text_field( $first_name ),
			'last_name'    => sanitize_text_field( $last_name ),
			'display_name' => trim( sanitize_text_field( $first_name ) . ' ' . sanitize_text_field( $last_name ) ),
			'role'         => 'customer',
		);

		if ( '' !== $password ) {
			if ( strlen( $password ) < 8 ) {
				return new WP_Error( 'accountx_short_password', __( 'Password must be at least 8 characters long.', 'accountx' ) );
			}

			$data['user_pass'] = $password;
		}

		$result = wp_update_user( $data );

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Delete a subaccount.
	 *
	 * @param int $parent_id     Parent user ID.
	 * @param int $subaccount_id Subaccount user ID.
	 * @return true|WP_Error
	 */
	public function delete_subaccount( $parent_id, $subaccount_id ) {
		if ( ! $this->parent_owns_subaccount( $parent_id, $subaccount_id ) ) {
			return new WP_Error( 'accountx_forbidden', __( 'You cannot delete this subaccount.', 'accountx' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( absint( $subaccount_id ) );

		return true;
	}

	/**
	 * Prevent subaccounts from using wp-admin.
	 *
	 * @return void
	 */
	public function block_subaccount_admin() {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( $this->is_subaccount( $user_id ) && ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( wc_get_account_endpoint_url( 'dashboard' ) );
			exit;
		}
	}
}
