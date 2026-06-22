<?php
/**
 * Order visibility helpers.
 *
 * @package AccountX
 */

defined( 'ABSPATH' ) || exit;

/**
 * AccountX order service.
 */
class AccountX_Orders {
	/**
	 * Subaccounts service.
	 *
	 * @var AccountX_Subaccounts
	 */
	private $subaccounts;

	/**
	 * Constructor.
	 *
	 * @param AccountX_Subaccounts $subaccounts Subaccounts service.
	 */
	public function __construct( AccountX_Subaccounts $subaccounts ) {
		$this->subaccounts = $subaccounts;

		add_action( 'woocommerce_checkout_create_order', array( $this, 'store_placed_by' ), 10, 2 );
		add_filter( 'woocommerce_my_account_my_orders_query', array( $this, 'include_subaccount_orders' ) );
		add_action( 'woocommerce_my_account_my_orders_column_order-placed-by', array( $this, 'render_placed_by_column' ) );
		add_filter( 'woocommerce_account_orders_columns', array( $this, 'add_placed_by_column' ) );
		add_filter( 'user_has_cap', array( $this, 'allow_parent_to_view_subaccount_order' ), 20, 4 );
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

		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			$order->update_meta_data( '_accountx_placed_by_user_id', $user_id );
			$order->update_meta_data( '_accountx_parent_user_id', $this->subaccounts->get_parent_id( $user_id ) );
		}
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
				$new_columns['order-placed-by'] = __( 'Placed by', 'accountx' );
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
		$placed_by = absint( $order->get_meta( '_accountx_placed_by_user_id' ) );

		if ( ! $placed_by ) {
			$placed_by = absint( $order->get_customer_id() );
		}

		$user = get_user_by( 'id', $placed_by );

		if ( ! $user ) {
			esc_html_e( 'Unknown', 'accountx' );
			return;
		}

		if ( $this->subaccounts->is_subaccount( $placed_by ) ) {
			echo esc_html( sprintf( /* translators: %s: subaccount display name. */ __( '%s (Subaccount)', 'accountx' ), $user->display_name ) );
			return;
		}

		echo esc_html( sprintf( /* translators: %s: parent display name. */ __( '%s (Parent)', 'accountx' ), $user->display_name ) );
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
}
