<?php
/**
 * AccountX uninstall cleanup.
 *
 * @package AccountX
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'accountx_settings' );
