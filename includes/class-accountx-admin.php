<?php
/**
 * Admin settings page.
 *
 * @package AccountX
 */

defined( 'ABSPATH' ) || exit;

/**
 * AccountX admin UI.
 */
class AccountX_Admin {
	/**
	 * Settings service.
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

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'AccountX', 'accountx' ),
			__( 'AccountX', 'accountx' ),
			'manage_woocommerce',
			'accountx',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'accountx_settings',
			AccountX_Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => AccountX_Settings::defaults(),
			)
		);

		add_settings_section( 'accountx_main', __( 'AccountX Settings', 'accountx' ), '__return_false', 'accountx' );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'enabled'          => isset( $input['enabled'] ) ? 'yes' : 'no',
			'mode'             => isset( $input['mode'] ) && in_array( $input['mode'], array( 'multi_user', 'sub_user' ), true ) ? $input['mode'] : 'multi_user',
			'subaccount_limit' => max( 1, min( 100, absint( isset( $input['subaccount_limit'] ) ? $input['subaccount_limit'] : 10 ) ) ),
			'user_switching'   => isset( $input['user_switching'] ) ? 'yes' : 'no',
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = $this->settings->all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AccountX - Teams & Subaccounts', 'accountx' ); ?></h1>
			<p><?php esc_html_e( 'Turn WooCommerce customer accounts into team accounts.', 'accountx' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'accountx_settings' ); ?>
				<?php do_settings_sections( 'accountx' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Features', 'accountx' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( AccountX_Settings::OPTION_NAME ); ?>[enabled]" value="yes" <?php checked( $settings['enabled'], 'yes' ); ?> />
								<?php esc_html_e( 'Enable subaccount features', 'accountx' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Show the AccountX menu in WooCommerce My Account and allow parent customers to manage subaccounts.', 'accountx' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="accountx-mode"><?php esc_html_e( 'Mode', 'accountx' ); ?></label></th>
						<td>
							<select id="accountx-mode" name="<?php echo esc_attr( AccountX_Settings::OPTION_NAME ); ?>[mode]">
								<option value="multi_user" <?php selected( $settings['mode'], 'multi_user' ); ?>><?php esc_html_e( 'Multi-User Mode', 'accountx' ); ?></option>
								<option value="sub_user" <?php selected( $settings['mode'], 'sub_user' ); ?>><?php esc_html_e( 'Sub-User Mode', 'accountx' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Choose how subaccounts are presented to customers: team members for company accounts, or controlled subaccounts under one parent customer.', 'accountx' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="accountx-limit"><?php esc_html_e( 'Subaccount limit', 'accountx' ); ?></label></th>
						<td>
							<input id="accountx-limit" type="number" min="1" max="100" name="<?php echo esc_attr( AccountX_Settings::OPTION_NAME ); ?>[subaccount_limit]" value="<?php echo esc_attr( $settings['subaccount_limit'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Set the maximum number of subaccounts each parent customer can create.', 'accountx' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'User switching', 'accountx' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( AccountX_Settings::OPTION_NAME ); ?>[user_switching]" value="yes" <?php checked( $settings['user_switching'], 'yes' ); ?> />
								<?php esc_html_e( 'Allow parents to switch into subaccount sessions', 'accountx' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Adds a My Account button that lets a parent customer temporarily view the store as one of their subaccounts.', 'accountx' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
