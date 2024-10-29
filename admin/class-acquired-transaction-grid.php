<?php
/**
 * Transaction Grid
 *
 * @package acquired-payment/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Acquired_Transaction_Grid
 */
class Acquired_Transaction_Grid {
	/**
	 * Acquired_Transaction_Grid constructor.
	 */
	public function __construct() {
		add_filter( 'list_table_primary_column', array( $this, 'list_table_primary_column' ), 10, 2 );
		add_filter(
			'manage_edit-' . ACQUIRED_POST_TYPE . '_columns',
			array(
				$this,
				'acquired_transaction_add_columns',
			)
		);
		add_action(
			'manage_' . ACQUIRED_POST_TYPE . '_posts_custom_column',
			array(
				$this,
				'acquired_transaction_custom_columns',
			),
			2
		);
		add_filter( 'bulk_actions-edit-' . ACQUIRED_POST_TYPE, array( $this, 'define_bulk_actions' ) );
		add_action( 'add_meta_boxes', array( $this, 'remove_meta_boxes' ), 10 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 30 );
		add_filter( 'parse_query', array( $this, 'custom_query_transaction' ) );
		add_filter( 'post_row_actions', array( $this, 'remove_view_row_action' ), 10, 2 );
	}

	/**
	 * List table primary column
	 *
	 * @param mixed $default comment $default.
	 * @param mixed $screen_id comment $screen_id.
	 *
	 * @return string
	 */
	public function list_table_primary_column( $default, $screen_id ) {
		if ( 'edit-' . ACQUIRED_POST_TYPE === $screen_id ) {
			return 'order_id';
		}

		return $default;
	}

	/**
	 * All of your columns will be added before the actions column on the Mange Transaction page
	 *
	 * @param mixed $columns comment $columns.
	 *
	 * @return array
	 */
	public function acquired_transaction_add_columns( $columns ) {
		$new_columns                       = array();
		$new_columns['cb']                 = $columns['cb'];
		$new_columns['order_id']           = __( 'Merchant Order Id', 'acquired-payment' );
		$new_columns['title']              = __( 'Transaction Id', 'acquired-payment' );
		$new_columns['transaction_type']   = __( 'Transaction Type', 'acquired-payment' );
		$new_columns['subscription_type']  = __( 'Subscription Type', 'acquired-payment' );
		$new_columns['payment_method']     = __( 'Payment Method', 'acquired-payment' );
		$new_columns['transaction_status'] = __( 'Status', 'acquired-payment' );
		$new_columns['amount']             = __( 'Amount', 'acquired-payment' );
		$new_columns['customer_email']     = __( 'Customer Email', 'acquired-payment' );

		return $new_columns;
	}

	/**
	 * Acquired transaction custom columns
	 *
	 * @param mixed $column comment $column.
	 */
	public function acquired_transaction_custom_columns( $column ) {
		global $post;

		switch ( $column ) {
			case 'cb':
				$id = get_post_meta( $post->ID, 'id', true );
				echo '<span>' . esc_html( $id ) . '</div>';
				break;
			case 'title':
				echo '<span>' . esc_html( get_post_meta( $post->ID, 'title', true ) ) . '</span></div>';
				break;
			case 'transaction_type':
				$order_id         = get_post_meta( $post->ID, 'order_id', true );
				$transaction_type = get_post_meta( $post->ID, 'transaction_type', true );
				if ( empty( $transaction_type ) ) {
					$transaction_type = get_post_meta( $order_id, '_transaction_type_acquired', true );
				}
				echo '<span>' . esc_html( $transaction_type ) . '</span></div>';
				break;
			case 'subscription_type':
				$subscription_type      = get_post_meta( $post->ID, 'subscription_type', true );
				$subcription_type_order = get_post_meta( get_post_meta( $post->ID, 'order_id', true ), 'subscription_type', true );
				$transaction_type       = get_post_meta( $post->ID, 'transaction_type', true );

				if ( ! empty( $transaction_type ) && 'CAPTURE' == $transaction_type ) {
					echo '<span>N/A</span></div>';
				} elseif ( ! empty( $subscription_type ) ) {
					echo '<span>' . esc_html( $subscription_type ) . '</span></div>';
				} elseif ( ! empty( $subcription_type_order ) ) {
					echo '<span>' . esc_html( $subcription_type_order ) . '</span></div>';
				} else {
					echo '<span>N/A</span></div>';
				}
				break;
			case 'payment_method':
				$payment_method = get_post_meta( $post->ID, 'payment_method', true );
				echo '<span>' . esc_html( $payment_method ) . '</span></div>';
				break;
			case 'transaction_status':
				echo '<span>' . esc_html( get_post_meta( $post->ID, 'message', true ) ) . '</span></div>';
				break;
			case 'amount':
				$order_id = get_post_meta( $post->ID, 'order_id', true );
				if ( ! empty( $order_id ) ) {
					$wc_order = wc_get_order( $order_id );
					echo '<span>' . esc_html( $wc_order->get_total() . $wc_order->get_currency() ) . '</span></div>';
				} else {
					echo '<span>' . esc_html( 'N/A' ) . '</span></div>';
				}
				break;
			case 'order_id':
				if ( ! empty( get_post_meta( $post->ID, 'order_id', true ) ) ) {
					echo '<a href="' . esc_html( get_edit_post_link( get_post_meta( $post->ID, 'order_id', true ) ) ) . '" target="_blank">
                        #' . esc_html( get_post_meta( $post->ID, 'order_id', true ) ) . '
                    </a>';
				} else {
					$merchant_order_id = get_post_meta( $post->ID, 'merchant_order_id', true );
					$original_order_id = substr( $merchant_order_id, 19, strlen( $merchant_order_id ) );
					echo '<a href="' . esc_html( get_edit_post_link( $original_order_id ) ) . '" target="_blank">
                        #' . esc_html( $original_order_id ) . '
                    </a>';
				}
				break;
			case 'customer_email':
				echo '<span>' . esc_html( get_post_meta( $post->ID, 'billing_email', true ) ) . '</span></div>';
				break;
		}
	}

	/**
	 * Define bulk actions
	 *
	 * @param mixed $actions comment $actions.
	 *
	 * @return array
	 */
	public function define_bulk_actions( $actions ) {
		$actions = array();

		return $actions;
	}

	/**
	 * Remove bloat.
	 */
	public function remove_meta_boxes() {
		remove_meta_box( 'commentsdiv', ACQUIRED_POST_TYPE, 'normal' );
		remove_meta_box( 'woothemes-settings', ACQUIRED_POST_TYPE, 'normal' );
		remove_meta_box( 'slugdiv', ACQUIRED_POST_TYPE, 'normal' );
		remove_meta_box( 'submitdiv', ACQUIRED_POST_TYPE, 'side' );
		remove_meta_box( 'postexcerpt', ACQUIRED_POST_TYPE, 'normal' );
		remove_meta_box( 'commentstatusdiv', ACQUIRED_POST_TYPE, 'side' );
		remove_meta_box( 'commentstatusdiv', ACQUIRED_POST_TYPE, 'normal' );
	}

	/**
	 * Add Acquired meta box
	 */
	public function add_meta_boxes() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		add_meta_box(
			'acquired-transaction-detail-data',
			/* translators: %s: data */
			sprintf( __( '%s data', 'acquired-payment' ), 'Acquired Transaction' ),
			array(
				$this,
				'acquired_transaction_detail_data',
			),
			ACQUIRED_POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Acquired transaction detail data
	 *
	 * @param mixed $post comment $post.
	 */
	public function acquired_transaction_detail_data( $post ) {
		global $post;
		$template_path = ACQUIRED_PATH . 'templates/';
		include $template_path . 'transaction-details.php';
	}

	/**
	 * Custom query transaction
	 *
	 * @param mixed $query comment $query.
	 */
	public function custom_query_transaction( $query ) {
		global $pagenow;
		$type = 'post';
		if ( isset( $_GET['post_type'] ) && '' != $_GET['post_type'] ) {
			$type = sanitize_text_field( wp_unslash( $_GET['post_type'] ) );
		}
		if ( ACQUIRED_POST_TYPE == $type && is_admin() && 'edit.php' == $pagenow && isset( $_GET['admin_filter'] ) && '' != $_GET['admin_filter'] ) {
			$query->query_vars['meta_key']   = 'transaction_status';
			$query->query_vars['meta_value'] = sanitize_text_field( wp_unslash( $_GET['admin_filter'] ) );
		}
	}

	/**
	 * Remove view row action
	 *
	 * @param mixed $actions comment $actions.
	 * @param mixed $post comment $post.
	 *
	 * @return mixed
	 */
	public function remove_view_row_action( $actions, $post ) {
		if ( ACQUIRED_POST_TYPE == $post->post_type ) {
			unset( $actions['view'] );
			unset( $actions['inline hide-if-no-js'] );
		}

		return $actions;
	}
}
