<?php
/**
 * Order visibility helpers.
 *
 * @package TeaMore
 */

defined( 'ABSPATH' ) || exit;

/**
 * TeaMore order service.
 */
class Teamore_Orders {
	/**
	 * Subaccounts service.
	 *
	 * @var Teamore_Subaccounts
	 */
	private $subaccounts;

	/**
	 * Settings service.
	 *
	 * @var Teamore_Settings
	 */
	private $settings;

	/**
	 * Switching service.
	 *
	 * @var Teamore_Switching
	 */
	private $switching;

	/**
	 * Constructor.
	 *
	 * @param Teamore_Settings    $settings    Settings service.
	 * @param Teamore_Subaccounts $subaccounts Subaccounts service.
	 * @param Teamore_Switching   $switching   Switching service.
	 */
	public function __construct( Teamore_Settings $settings, Teamore_Subaccounts $subaccounts, Teamore_Switching $switching ) {
		$this->settings    = $settings;
		$this->subaccounts = $subaccounts;
		$this->switching   = $switching;

		add_action( 'woocommerce_checkout_create_order', array( $this, 'store_placed_by' ), 10, 2 );
		add_filter( 'woocommerce_checkout_customer_id', array( $this, 'filter_checkout_customer_id' ) );
		add_filter( 'woocommerce_my_account_my_orders_query', array( $this, 'include_subaccount_orders' ) );
		add_action( 'woocommerce_my_account_my_orders_column_order-placed-by', array( $this, 'render_placed_by_column' ) );
		add_filter( 'woocommerce_account_orders_columns', array( $this, 'add_placed_by_column' ) );
		add_filter( 'user_has_cap', array( $this, 'allow_parent_to_view_subaccount_order' ), 20, 4 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_admin_order_info' ) );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_admin_order_list_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_classic_admin_order_list_column' ), 20, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_admin_order_list_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_hpos_admin_order_list_column' ), 20, 2 );
	}

	/**
	 * Store who placed the order.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Checkout data.
	 * @return void
	 */
	public function store_placed_by( $order, $data ) {
		unset( $data );

		$user_id              = $this->switching->get_context_user_id();
		$active_subaccount_id = $this->switching->get_active_subaccount_id();

		if ( $user_id > 0 ) {
			$order->update_meta_data( '_teamore_placed_by_user_id', $user_id );
			$order->update_meta_data( '_teamore_parent_user_id', $active_subaccount_id > 0 ? get_current_user_id() : $this->subaccounts->get_parent_id( $user_id ) );
		}
	}

	/**
	 * Assign checkout orders to the active subaccount context.
	 *
	 * @param int $customer_id Checkout customer ID.
	 * @return int
	 */
	public function filter_checkout_customer_id( $customer_id ) {
		$context_user_id = $this->switching->get_context_user_id();

		if ( $context_user_id > 0 && $context_user_id !== get_current_user_id() ) {
			return $context_user_id;
		}

		return $customer_id;
	}

	/**
	 * Include subaccount orders for parents.
	 *
	 * @param array $query Order query.
	 * @return array
	 */
	public function include_subaccount_orders( $query ) {
		$user_id = get_current_user_id();

		if ( $user_id < 1 || $this->subaccounts->is_subaccount( $user_id ) ) {
			return $query;
		}

		$active_subaccount_id = $this->switching->get_active_subaccount_id();

		if ( $active_subaccount_id > 0 ) {
			$query['customer'] = array( $active_subaccount_id );
			return $query;
		}

		$customer_ids = array( $user_id );

		foreach ( $this->subaccounts->get_subaccounts( $user_id ) as $subaccount ) {
			$customer_ids[] = absint( $subaccount->ID );
		}

		$query['customer'] = array_unique( $customer_ids );

		return $query;
	}

	/**
	 * Add placed-by column to My Account orders table.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_placed_by_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'order-date' === $key ) {
				$new_columns['order-placed-by'] = __( 'Placed by', 'teamore' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render placed-by column.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function render_placed_by_column( $order ) {
		$placed_by = absint( $order->get_meta( '_teamore_placed_by_user_id' ) );

		if ( ! $placed_by ) {
			$placed_by = absint( $order->get_customer_id() );
		}

		$user = get_user_by( 'id', $placed_by );

		if ( ! $user ) {
			esc_html_e( 'Unknown', 'teamore' );
			return;
		}

		if ( $this->subaccounts->is_subaccount( $placed_by ) ) {
			echo esc_html( sprintf( /* translators: %s: subaccount display name. */ __( '%s (Subaccount)', 'teamore' ), $this->subaccounts->get_display_name( $user ) ) );
			return;
		}

		echo esc_html( sprintf( /* translators: %s: parent display name. */ __( '%s (Parent)', 'teamore' ), $this->subaccounts->get_display_name( $user ) ) );
	}

	/**
	 * Get orders for a single subaccount.
	 *
	 * @param int $subaccount_id Subaccount user ID.
	 * @return WC_Order[]
	 */
	public function get_orders_for_subaccount( $subaccount_id ) {
		return wc_get_orders(
			array(
				'customer_id' => absint( $subaccount_id ),
				'limit'       => 20,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
	}

	/**
	 * Let parents open My Account order detail pages for owned subaccount orders.
	 *
	 * @param array   $allcaps All user capabilities.
	 * @param array   $caps    Required capabilities.
	 * @param array   $args    Capability arguments.
	 * @param WP_User $user    User object.
	 * @return array
	 */
	public function allow_parent_to_view_subaccount_order( $allcaps, $caps, $args, $user ) {
		unset( $caps );

		if ( empty( $args[0] ) || 'view_order' !== $args[0] || empty( $args[2] ) || ! $user instanceof WP_User ) {
			return $allcaps;
		}

		$order = wc_get_order( absint( $args[2] ) );

		if ( ! $order ) {
			return $allcaps;
		}

		$order_customer_id = absint( $order->get_customer_id() );

		if ( $order_customer_id && $this->subaccounts->parent_owns_subaccount( $user->ID, $order_customer_id ) ) {
			$allcaps['view_order'] = true;
		}

		return $allcaps;
	}

	/**
	 * Render TeaMore information on the WooCommerce admin order page.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function render_admin_order_info( $order ) {
		if ( ! $this->settings->is_display_location_enabled( 'show_order_page_info' ) || ! $order instanceof WC_Order ) {
			return;
		}

		$info = $this->get_order_relationship_label( $order );

		if ( '' === $info ) {
			return;
		}

		echo '<div class="address teamore-order-info">';
		echo '<p><strong>' . esc_html__( 'TeaMore', 'teamore' ) . ':</strong><br />' . esc_html( $info ) . '</p>';
		echo '</div>';
	}

	/**
	 * Add TeaMore column to WooCommerce order lists.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_admin_order_list_column( $columns ) {
		if ( ! $this->settings->is_display_location_enabled( 'show_order_list_info' ) ) {
			return $columns;
		}

		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( in_array( $key, array( 'order_status', 'status' ), true ) ) {
				$new_columns['teamore_order_account'] = __( 'TeaMore', 'teamore' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render classic orders list column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Order post ID.
	 * @return void
	 */
	public function render_classic_admin_order_list_column( $column, $post_id ) {
		if ( 'teamore_order_account' !== $column || ! $this->settings->is_display_location_enabled( 'show_order_list_info' ) ) {
			return;
		}

		echo esc_html( $this->get_order_relationship_label( wc_get_order( $post_id ) ) );
	}

	/**
	 * Render HPOS orders list column.
	 *
	 * @param string   $column Column key.
	 * @param WC_Order $order  Order object.
	 * @return void
	 */
	public function render_hpos_admin_order_list_column( $column, $order ) {
		if ( 'teamore_order_account' !== $column || ! $this->settings->is_display_location_enabled( 'show_order_list_info' ) ) {
			return;
		}

		echo esc_html( $this->get_order_relationship_label( $order ) );
	}

	/**
	 * Get relationship label for an order.
	 *
	 * @param WC_Order|false $order Order object.
	 * @return string
	 */
	private function get_order_relationship_label( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return '';
		}

		$customer_id = absint( $order->get_customer_id() );

		if ( $customer_id < 1 ) {
			return __( 'Guest order', 'teamore' );
		}

		if ( $this->subaccounts->is_subaccount( $customer_id ) ) {
			$parent_id = $this->subaccounts->get_parent_id( $customer_id );
			$parent    = get_user_by( 'id', $parent_id );

			if ( $parent ) {
				return sprintf(
					/* translators: 1: subaccount display name, 2: parent display name. */
					__( 'Placed by %1$s under %2$s', 'teamore' ),
					$this->subaccounts->get_display_name( $customer_id ),
					$this->subaccounts->get_display_name( $parent )
				);
			}

			return sprintf(
				/* translators: %s: subaccount display name. */
				__( 'Placed by %s', 'teamore' ),
				$this->subaccounts->get_display_name( $customer_id )
			);
		}

		$count = $this->subaccounts->count_subaccounts( $customer_id );

		if ( $count > 0 ) {
			return sprintf(
				/* translators: 1: parent display name, 2: subaccount count. */
				_n( 'Parent account: %1$s (%2$d subaccount)', 'Parent account: %1$s (%2$d subaccounts)', $count, 'teamore' ),
				$this->subaccounts->get_display_name( $customer_id ),
				$count
			);
		}

		return sprintf(
			/* translators: %s: customer display name. */
			__( 'Customer: %s', 'teamore' ),
			$this->subaccounts->get_display_name( $customer_id )
		);
	}
}
