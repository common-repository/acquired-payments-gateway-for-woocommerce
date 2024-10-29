<?php
/**
 * WC Acquired Subscription
 *
 * @package acquired-payment/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility class for Subscriptions.
 *
 * @extends WC_Acquired_Payment
 */
class WC_Acquired_Subscription extends WC_Acquired_Payment {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
			add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );
			add_action( 'woocommerce_subscription_failing_payment_method_updated_acquired', array( $this, 'update_failing_payment_method' ), 10, 2 );
			add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'maybe_render_subscription_payment_method' ), 10, 2 );
			add_action( 'before_woocommerce_pay', array( $this, 'add_order_information' ) );

			add_action( 'woocommerce_subscriptions_change_payment_before_submit', array( $this, 'differentiate_change_payment_method_form' ) );
		}
	}

	/**
	 * Is $order_id a subscription?
	 *
	 * @param int $order_id Order $order_id.
	 *
	 * @return boolean
	 */
	public function has_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' )
				 && ( wcs_order_contains_subscription( $order_id )
					  || wcs_is_subscription( $order_id )
					  || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * Checks if page is pay for order and change subs payment page.
	 *
	 * @return bool
	 * @since 4.0.4
	 */
	public function is_subs_change_payment() {
		return ( isset( $_GET['pay_for_order'] ) && isset( $_GET['change_payment_method'] ) );
	}

	/**
	 * Process the payment based on type.
	 *
	 * @param int  $order_id Order $order_id.
	 * @param bool $retry Order $retry.
	 * @param bool $force_save_source Order $force_save_source.
	 * @param bool $previous_error Order $previous_error.
	 * @param bool $use_order_source Order $use_order_source.
	 *
	 * @return array
	 * @throws Exception Exception $exception.
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false, $previous_error = false, $use_order_source = false ) {
		if ( $this->has_subscription( $order_id ) ) {
			if ( $this->is_subs_change_payment() ) {
				return $this->change_subs_payment_method( $order_id );
			}

			return parent::process_payment( $order_id, $retry, true, $previous_error, $use_order_source );
		} else {
			return parent::process_payment( $order_id, $retry, $force_save_source, $previous_error, $use_order_source );
		}
	}

	/**
	 * Render a dummy element in the "Change payment method" form (that does not appear in the "Pay for order" form)
	 *
	 * @since 4.6.1
	 */
	public function differentiate_change_payment_method_form() {
		if ( ! empty( $_GET['change_payment_method'] ) ) {
			echo '<input type="hidden" id="change_payment_method" name="change_payment_method" value="change_payment_method"/>';
		}
	}

	/**
	 * Process the payment method change for subscriptions.
	 *
	 * @param int $order_id Order $use_order_source.
	 *
	 * @return array
	 * @throws Exception Exception $exception.
	 * @since 4.1.11 Remove 3DS check as it is not needed.
	 * @since 4.0.4
	 */
	public function change_subs_payment_method( $order_id ) {
		try {
			$order = wc_get_order( $order_id );

			if ( 'redirect' == $this->iframe_option ) {
				$params = array( 'order_id' => $order_id );
				// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( ! empty( $_GET['pay_for_order'] ) && empty( $_GET['change_payment_method'] ) ) {
					$params['pay_for_order'] = $_GET['pay_for_order'];
				} else {
					$params['change_payment_method'] = $_GET['change_payment_method'];
				}
				$endpoint = $this->_helper->get_payment_url( $params );
				// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

				return array(
					'result'   => 'success',
					'redirect' => $endpoint,
				);
			} else {
				if ( ! empty( $_GET['pay_for_order'] ) ) {
					return array();
				}
				return array(
					'result'   => 'success',
					'redirect' => $order->get_checkout_order_received_url(),
				);
			}
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
		}
	}

	/**
	 * Trigger scheduled subscription payment.
	 *
	 * @param float    $amount_to_charge The amount to charge.
	 * @param WC_Order $renewal_order An order object created to record the renewal payment.
	 *
	 * @throws Exception Exception $exception.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$result = $this->process_subscription_payment( $renewal_order, $amount_to_charge );
	}

	/**
	 * Process payment for subscription
	 * we mimic recurring payments, using the last
	 * transaction id
	 *
	 * @param WC_Order $order the renewal order created from the initial order only containing the subscription product we are renewing.
	 * @param int      $amount The amount for the subscription.
	 *
	 * @return bool|int|mixed|null|WP_Error
	 * @throws Exception Exception $exception.
	 */
	public function process_subscription_payment( $order = null, $amount = 0 ) {
		if ( 0 == $amount ) {
			$order->payment_complete();

			return true;
		}
        $original_transaction_id = $this->get_transaction_id( $order ) ;
        if ( false === $original_transaction_id ) {
            $message = __( 'Cannot find original transaction id. Please contact your merchant', 'acquired-payment' );
            $order->update_status( 'failed', sprintf( /* translators: %s: transaction */ __( 'Acquired Cards error: %s', 'acquired-payment' ), $message ) );

            return false;
        }
		$order_id                = $order->get_id();
        $data = array(
            'timestamp'      => strftime( '%Y%m%d%H%M%S' ),
            'company_id'     => $this->company_id,
            'company_pass'   => $this->company_pass,
            'company_mid_id' => $this->company_mid_id,
        );

        $transaction_info = array(
            'merchant_order_id'       => $order_id,
            'transaction_type'        => $this->transaction_type,
            'original_transaction_id' => $original_transaction_id,
            'amount'                  => $amount,
            'currency_code_iso3'      => $order->get_currency(),
            'subscription_type'       => 'REBILL',
            'subscription_reason'     => 'R',
        );

        $request_hash         = $this->_helper->request_hash(
            array_merge( $transaction_info, $data ),
            $this->company_hashcode
        );
        $data['request_hash'] = $request_hash;
        $path                 = 'sandbox' == $this->mode ? $this->api_endpoint_sandbox : $this->api_endpoint_live;

        $data['transaction'] = $transaction_info;
        $this->_helper->acquired_logs( 'process_subscription_payment data', $this->debug_mode );
        $this->_helper->acquired_logs( $data, $this->debug_mode );
        $payment_submit_response = $this->_helper->acquired_api_request( $path, 'POST', $data );
        $this->_helper->acquired_logs( 'process_subscription_payment data', $this->debug_mode );
        $this->_helper->acquired_logs( $data, $this->debug_mode );

        if ( is_array( $payment_submit_response ) && isset( $payment_submit_response['response_code'] ) ) {
            $order = wc_get_order( $order_id );

            if ( '1' == $payment_submit_response['response_code'] ) {
                $order->payment_complete( $payment_submit_response['transaction_id'] );
                $message                   = sprintf(
                /* translators: %s: transaction */                        __( 'Acquired Payments charge successfully %s', 'acquired-payment' ),
                    wc_price( $order->get_total() )
                );
                $request_array['order_id'] = $order_id;
                $order->add_order_note( $message );

                return true;
            } else {
                $message = __(
                        'Transaction Failed: ',
                        'acquired-payment'
                    ) . $payment_submit_response['response_code'] . ' - ' . $payment_submit_response['response_message'];
                $order->add_order_note( $message );
            }
        }

		return false;
	}

	/**
	 * Get transaction ID
	 *
	 * @param mixed $order Order $order.
	 *
	 * @return mixed
	 */
	public function get_transaction_id( $order ) {
		// we continue our search on the subscriptions.
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order->get_id() ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order->get_id() );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order->get_id() ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order->get_id() );
		} else {
			$subscriptions = array();
		}
		foreach ( $subscriptions as $subscription ) {
			$original_transaction_id = get_post_meta( $subscription->get_id(), 'original_transaction_id', true );
			if ( ! empty( $original_transaction_id ) ) {
				return $original_transaction_id;
			} elseif ( ! empty( $subscription->get_transaction_id() ) ) {
				return $subscription->get_transaction_id();
			} elseif ( $subscription->get_parent_id() ) {
				$wc_order = wc_get_order( $subscription->get_parent_id() );
				if ( ! empty( $wc_order->get_transaction_id() ) ) {
					$transaction_id = $wc_order->get_transaction_id();
				} else {
                    $transaction_id = get_post_meta( $wc_order->get_id(), '_transaction_id', true );
                }
                return $transaction_id;
			}
		}

		return false;
	}

	/**
	 * Don't transfer Acquired transaction id meta to resubscribe orders.
	 *
	 * @param WC_Order $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription.
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		delete_post_meta( $resubscribe_order->get_id(), '_transaction_id' );
	}

	/**
	 * Update the acquired_transaction_id for a subscription after using Acquired to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
	 * @param WC_Order        $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 *
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		update_post_meta( $subscription->get_id(), '_transaction_id', get_post_meta( $renewal_order->get_id(), '_transaction_id', true ) );
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string          $payment_method_to_display the default payment method text to display.
	 * @param WC_Subscription $subscription the subscription details.
	 *
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		// bail for other payment methods.
		if ( $this->id !== $subscription->get_payment_method() || ! $subscription->get_user() ) {
			return $payment_method_to_display;
		}
		$parent_order_id = $subscription->get_parent_id();
		// add more details, if we can get the card.
		$card_number = get_post_meta( $parent_order_id, 'subscription_card_number' )[0];
		$card_type   = get_post_meta( $parent_order_id, 'subscription_card_type' )[0];

		if ( ! empty( $card_number ) ) {
			$card = substr( $card_number, - 4 );
			/* translators: %1$s is replaced with card type, %2$s is replaced with last4 digits and %3$s is replaced with the card id */
			$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s (%3$s)', 'acquired-payment' ), $card_type, $card, ucfirst( $this->id ) );
		}

		return $payment_method_to_display;
	}

	/**
	 * Add order information
	 */
	public function add_order_information() {
		parent::add_order_information();
	}
}
