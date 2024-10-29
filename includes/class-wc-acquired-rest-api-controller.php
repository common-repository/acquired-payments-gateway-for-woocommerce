<?php
/**
 * WC Acquired API
 *
 * @package acquired-payment/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Rest_Api_Controller
 *
 * @property WC_Acquired_Helper _helper
 */
class WC_Acquired_Rest_Api_Controller extends WC_REST_Data_Controller {
	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'acquired';

	/**
	 * WC_Acquired_Rest_Api_Controller constructor.
	 */
	public function __construct() {
		$this->_helper = new WC_Acquired_Helper();
	}

	/**
	 * Register routes.
	 *
	 * @since 3.5.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => \WP_REST_Server::ALLMETHODS,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			)
		);
	}

	/**
	 * Check API request
	 *
	 * @param WP_REST_Request $request Request $request.
	 *
	 * @return WP_Error|WP_REST_Response
	 * @throws WC_Data_Exception Exception $exception.
	 */
	public function get_items( $request ) {
		$this->_helper->acquired_logs( 'API get_items params ' . json_encode( $request->get_params() ), true );
		$response_params  = $request->get_params();
		if ( isset( $response_params['status'] ) && '500' === $response_params['status'] ) {
            $order_id = ! empty( $response_params['merchant_order_id'] ) ? $response_params['merchant_order_id'] : '';
        } else {
            $order_id = ! empty( $response_params['merchant_custom_1'] ) ? $response_params['merchant_custom_1'] : '';
        }
		$transaction_type = ! empty( get_post_meta( $order_id, 'transaction_type_acquired' ) ) ? get_post_meta(
			$order_id,
			'transaction_type_acquired'
		) : '';
		if ( empty( $order_id ) ) {
			return;
		}

		$check_transaction_success = isset( $response_params['code'] ) && 1 == $response_params['code'];
		if ( isset( $response_params['is_remember'] ) && 1 == $response_params['is_remember'] && $check_transaction_success ) {
			$cardexp    = isset( $response_params['cardexp'] ) ? $response_params['cardexp'] : '';
			$card_type  = isset( $response_params['card_type'] ) ? $response_params['card_type'] : '';
			$cardnumber = isset( $response_params['cardnumber'] ) ? $response_params['cardnumber'] : '';

			if ( ! empty( $cardnumber ) && ! empty( $card_type ) && ! empty( $cardexp ) ) {
                $count = strlen($cardexp);
                if ( '5' == $count ) {
                    $cardexp = '0'.$cardexp;
                }
				$response = array(
					'card_category'   => isset( $response_params['card_category'] ) ? $response_params['card_category'] : '',
					'card_level'      => isset( $response_params['card_level'] ) ? $response_params['card_level'] : '',
					'card_type'       => $card_type,
					'expiry_date'     => $cardexp,
					'cardholder_name' => isset( $response_params['cardholder_name'] ) ? $response_params['cardholder_name'] : '',
					'card_number'     => $cardnumber,
					'card_identifier' => isset( $response_params['transaction_id'] ) ? $response_params['transaction_id'] : '',
				);
                $user     = get_user_by( 'email', $response_params['billing_email'] );
                $user_id  = $user->ID;
                // $user_id  = get_current_user_id();
				if ( empty( $user_id ) ) {
					$user_id = get_post_meta( $order_id, 'acquired_current_user_id', true );
				}

				if ( $user_id ) {
                    $this->_helper->save_card( $user_id, $response );
                }
			}
		}

		$order = wc_get_order( $order_id );
		if ( empty( $order ) ) {
			if ( strpos( $response_params['merchant_order_id'], 'INIT_CARD_UPDATE' ) !== false ) {
				$original_order_id = substr( $response_params['merchant_order_id'], 22, strlen( $response_params['merchant_order_id'] ) );
				$wc_order          = wc_get_order( $original_order_id );
				$update_all_sub    = get_post_meta( $original_order_id, 'update_for_all_subs', true );
				if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
					if ( true == $update_all_sub ) {
						$user_subscriptions = wcs_get_subscriptions(
							array(
								'subscriptions_per_page' => - 1,
								'customer_id'            => $wc_order->get_user_id(),
							)
						);
						foreach ( $user_subscriptions as $subscription ) {
							$this->save_original_transaction_id( $subscription['id'], $response_params['transaction_id'], $response_params );
						}
					} else {
						$this->save_original_transaction_id( $original_order_id, $response_params['transaction_id'], $response_params );
					}
				}
			} else {
				return;
			}
		}
		try {
			$raw_data = array(
				'transaction_type' => $transaction_type,
				'order_id'         => $order_id,
				'customer_email'   => ! empty( $order->get_billing_email() ) ? $order->get_billing_email() : '',
			);

			$data = array_merge( $raw_data, $response_params );

			foreach ( $data as $key_param => $value ) {
				if ( empty( $value ) ) {
					unset( $data[ $key_param ] );
				}
			}
			update_post_meta( $order_id, 'subscription_card_number', isset( $response_params['cardnumber'] ) ? $response_params['cardnumber'] : '' );
			update_post_meta( $order_id, 'subscription_card_type', isset( $response_params['card_type'] ) ? $response_params['card_type'] : '' );

			$this->_helper->acquired_logs( 'Response form API: ', true );
			$this->_helper->acquired_logs( $data, true );
			$this->_helper->process_response( $order_id, $data );
		} catch ( Exception $exception ) {
			$this->_helper->acquired_logs( $exception, true );
		}

		return new WP_REST_Response(
			array(
				'message' => 'completed',
				'status'  => 200,
			),
			200
		);
	}

	/**
	 * Get items permissions check
	 *
	 * @param null $request Request $request.
	 *
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request = null ) {
		return true;
	}

	/**
	 * Save Original Transaction id
	 *
	 * @param mixed $subscription_id Subscription $subscription_id.
	 * @param mixed $transaction_id Transaction $transaction_id.
	 * @param mixed $response_params Params $response_params.
	 *
	 * @throws WC_Data_Exception Exception $exception.
	 */
	public function save_original_transaction_id( $subscription_id, $transaction_id, $response_params ) {
		$wc_order = wc_get_order( $subscription_id );
		$wc_order->set_transaction_id( $transaction_id );
		$wc_parent_order = wc_get_order( $wc_order->get_parent_id() );
		$wc_parent_order->set_transaction_id( $transaction_id );
		update_post_meta( $subscription_id, 'original_transaction_id', $transaction_id );
		update_post_meta( $wc_parent_order->get_id(), 'subscription_card_number', isset( $response_params['cardnumber'] ) ? $response_params['cardnumber'] : '' );
		update_post_meta( $wc_parent_order->get_id(), 'subscription_card_type', isset( $response_params['card_type'] ) ? $response_params['card_type'] : '' );
		$wc_parent_order->add_order_note( __( 'Acquired Card change payment successfully (Transaction ID: ', 'acquired-payment' ) . $transaction_id . ').' );
		$wc_order->add_order_note( __( 'Acquired Card change payment successfully (Transaction ID: ', 'acquired-payment' ) . $transaction_id . ').' );
	}
}

return new WC_Acquired_Rest_Api_Controller();
