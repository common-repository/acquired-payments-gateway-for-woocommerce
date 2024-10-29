<?php
/**
 * WC Acquired Helper
 *
 * @package acquired-payment/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WC_Acquired_Helper
 */
class WC_Acquired_Helper {
    /**
     * WC_Acquired_Helper constructor
     */
    public function __construct() {
    }

    /**
     * Get billing address
     *
     * @param mixed $billing Billing $billing.
     *
     * @return array|string[]
     */
    public function get_billing_address( $billing ) {
        $billing_address = array();
        if ( ! empty( $billing ) && is_array( $billing ) ) {
            $billing_address = array(
                'customer_fname'            => isset( $billing['first_name'] ) ? $billing['first_name'] : '',
                'customer_lname'            => isset( $billing['last_name'] ) ? $billing['last_name'] : '',
                'billing_street'            => isset( $billing['address_1'] ) ? $billing['address_1'] : '',
                'billing_street2'           => isset( $billing['address_2'] ) ? $billing['address_2'] : '',
                'billing_city'              => isset( $billing['city'] ) ? $billing['city'] : '',
                'billing_state'             => isset( $billing['state'] ) ? $billing['state'] : '',
                'billing_zipcode'           => isset( $billing['postcode'] ) ? $billing['postcode'] : '',
                'billing_country_code_iso2' => isset( $billing['country'] ) ? $billing['country'] : '',
                'billing_email'             => isset( $billing['email'] ) ? $billing['email'] : '',
                'billing_phone'             => isset( $billing['phone'] ) ? $billing['phone'] : '',
            );
            foreach ( $billing_address as $key => $value ) {
                if ( '' === $value ) {
                    unset( $billing_address[ $key ] );
                }
            }
        }

        return $billing_address;
    }

    /**
     * Get shipping address
     *
     * @param mixed $shipping Shipping $shipping.
     *
     * @return array
     */
    public function get_shipping_address( $shipping ) {
        $shipping_address = array();
        if ( ! empty( $shipping ) && is_array( $shipping ) ) {
            $shipping_address = array(
                'shipping_street2'           => isset( $shipping['address_2'] ) ? $shipping['address_2'] : '',
                'shipping_street'            => ! empty( $shipping['address_1'] ) ? $shipping['address_1'] : $shipping['address_2'],
                'shipping_city'              => isset( $shipping['city'] ) ? $shipping['city'] : '',
                'shipping_state'             => isset( $shipping['state'] ) ? $shipping['state'] : '',
                'shipping_zipcode'           => isset( $shipping['postcode'] ) ? $shipping['postcode'] : '',
                'shipping_country_code_iso2' => isset( $shipping['country'] ) ? $shipping['country'] : '',
                'shipping_email'             => isset( $shipping['email'] ) ? $shipping['email'] : '',
                'shipping_phone'             => isset( $shipping['phone'] ) ? $shipping['phone'] : '',
            );
            foreach ( $shipping_address as $key => $value ) {
                if ( '' === $value ) {
                    unset( $shipping_address[ $key ] );
                }
            }
        }

        return $shipping_address;
    }

    /**
     * Get the return url (thank you page).
     *
     * @param WC_Order $order Order object.
     *
     * @return string
     */
    public function get_return_url( $order = null ) {
        $order_obj = new WC_Order( $order );
        if ( $order_obj ) {
            $return_url = $order_obj->get_checkout_order_received_url();
        } else {
            $return_url = wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ) );
        }

        if ( is_ssl() || 'yes' == get_option( 'woocommerce_force_ssl_checkout' ) ) {
            $return_url = str_replace( 'http:', 'https:', $return_url );
        }

        return apply_filters( 'woocommerce_get_return_url', $return_url, $order_obj );
    }

    /**
     * Create hash for payment request
     *
     * @param mixed $wc_acquired_data Acquired Data.
     * @param mixed $hash_code Hashcode $hash_code.
     *
     * @return string
     */
    public function create_hash_value( $wc_acquired_data, $hash_code ) {
        ksort( $wc_acquired_data );
        $plain = '';

        foreach ( $wc_acquired_data as $param_value ) {
            $plain .= $param_value;
        }
        $temp = hash( 'sha256', $plain );

        if ( ! empty( $hash_code ) ) {
            $temp = $temp . $hash_code;
        } else {
            return '';
        }

        return hash( 'sha256', $temp );
    }

    /**
     * Get payment link from API
     *
     * @param mixed $wc_acquired_data AcquiredData $wc_acquired_data.
     *
     * @return string
     * @throws Exception Exception $exception.
     */
    public function get_payment_link( $wc_acquired_data ) {
        $api_info = $wc_acquired_data['acquired-data'];
        $woocommerce_acquired_settings = get_option('woocommerce_acquired_settings');
        if ( ! is_array( $api_info ) ) {
            $api_info = (array) $api_info;
        }
        $order          = $wc_acquired_data['order']->get_data();
        $order_id       = ! empty( $wc_acquired_data['order_pay'] ) ? $wc_acquired_data['order_pay'] : $wc_acquired_data['order_id'];
        $merchant_customer_id = wc_get_order($order_id)->get_meta('merchant_customer_id');
        $customer_dob = wc_get_order($order_id)->get_meta('customer_dob');

        $merchant_custom_2 = wc_get_order($order_id)->get_meta('merchant_custom_2');
        $merchant_custom_3 = ! empty (get_option('woocommerce_general_settings') ) ? get_option( 'woocommerce_general_settings' )[ 'dynamic_descriptor' ] : '';
       
        $params_request = array(
            'company_id'           => $api_info['company_id'],
            'company_mid_id'       => $api_info['company_mid_id'],
            'merchant_order_id'    => (string) $order_id,
            'transaction_type'     => $api_info['transaction_type'],
            'currency_code_iso3'   => $order['currency'],
            'amount'               => $order['total'],
            'is_tds'               => (int) $api_info['three_d_secure'],
            'merchant_contact_url' => ! empty( $api_info['merchant_contact_url'] ) ? $api_info['merchant_contact_url'] : site_url(),
            'template_id'          => ! empty( $api_info['template_id'] ) ? $api_info['template_id'] : '',
            'callback_url'         => site_url( 'wp-json/wc/v3/acquired' ),
            'expiry_time'          => ! empty( $api_info['expiry_time'] ) ? $api_info['expiry_time'] : 86400,
            'retry_attempts'       => ! empty( $api_info['retry_attempts'] ) ? $api_info['retry_attempts'] : 3,
            'error_url'            => site_url( 'wp-json/wc/v3/acquired' ),
            'cancel_url'           => site_url( 'checkout' ),
            'tds_source'           => "1",
            'tds_type'             => "2",
            'tds_preference'       => ! empty( $api_info['tds_preference'] ) ? $api_info['tds_preference'] : '0',
            'load_balance'         => 1,
            'merchant_customer_id' => (string) $merchant_customer_id,
            'customer_dob'         => (string) $customer_dob,
            'merchant_custom_1'    => (string) $order_id,
            'merchant_custom_2'    => (string) $merchant_custom_2,
            'merchant_custom_3'    => (string) $merchant_custom_3,
        );

        if ( 'redirect' == $woocommerce_acquired_settings['iframe_option'] ) {
            $params_request['return_method'] = 'post';
            $params_request['return_url'] = wc_get_order($order_id)->get_checkout_order_received_url();
        } else {
            $params_request['return_method'] = 'post_message';
        }

        if ( 0 == $params_request['amount'] || ! empty( $wc_acquired_data['change_payment_method'] ) ) {
            $params_request['amount']           = '0';
            $params_request['transaction_type'] = 'AUTH_ONLY';
        }
        $debug_mode = 1 == $api_info['debug_mode'];

        // if ( ! empty( $wc_acquired_data['pay_for_order'] ) ) {
        //     $params_request['vt'] = 1;
        // }

        update_post_meta( $order_id, '_transaction_type_acquired', $api_info['transaction_type'] );
        $billing_address  = $this->get_billing_address( $order['billing'] );
        $shipping_address = $this->get_shipping_address( $order['shipping'] );

        if ( ! empty( $wc_acquired_data['token']['token'] ) && empty( $wc_acquired_data['pay_for_order'] ) ) {
            $params_request['subscription_type'] = 'REUSE';
            update_post_meta( $order_id, 'subscription_type', 'REUSE' );
            $params_request['original_transaction_id'] = $wc_acquired_data['token']['token'];
            $params_request['merchant_contact_url']    = ! empty( $api_info['merchant_contact_url'] ) ? $api_info['merchant_contact_url'] : site_url();
            if ( ! empty( $api_info['reuse_template_id'] ) ) {
                $params_request['template_id'] = $api_info['reuse_template_id'];
            }

// 			$unset_fields = array(
// 				'merchant_customer_id',
// 			);

//            foreach ( $unset_fields as $field ) {
//                unset( $params_request[ $field ] );
//            }
//            $params = $params_request;
        } elseif ( $this->check_if_isset_subscription_product() ) {
            $params_request['subscription_type'] = 'INIT';
            update_post_meta( $order_id, 'subscription_type', 'INIT' );
        }

        if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
            if ( count( wcs_get_order_type_cart_items( 'renewal' ) ) === count( WC()->cart->get_cart() ) ) {
                $original_transaction_id = get_post_meta( $order_id, 'subsciption_original_transaction_id' )[0];
                if ( ! empty( $original_transaction_id ) ) {
                    $data = array(
                        'timestamp'      => strftime( '%Y%m%d%H%M%S' ),
                        'company_id'     => $params_request['company_id'],
                        'company_pass'   => $api_info['company_pass'],
                        'company_mid_id' => $params_request['company_mid_id'],
                    );

                    $transaction_info = array(
                        'merchant_order_id'       => $order_id,
                        'transaction_type'        => $params_request['transaction_type'],
                        'original_transaction_id' => $original_transaction_id,
                        'amount'                  => $params_request['amount'],
                        'currency_code_iso3'      => $order['currency'],
                        'subscription_type'       => 'REBILL',
                        'subscription_reason'     => 'R',
                    );

                    $request_hash         = $this->request_hash(
                        array_merge( $transaction_info, $data ),
                        $api_info['company_hashcode']
                    );
                    $data['request_hash'] = $request_hash;
                    $path                 = 'sandbox' == $api_info['mode'] ? 'https://qaapi.acquired.com/api.php' : 'https://gateway.acquired.com/api.php';

                    $data['transaction'] = $transaction_info;
                    $this->acquired_logs( 'get_payment_link of rebill subscription data', $debug_mode );
                    $this->acquired_logs( $data, $debug_mode );
                    $payment_submit_response = $this->acquired_api_request( $path, 'POST', $data );
                    $this->acquired_logs( 'get_payment_link of rebill subscription response', $debug_mode );
                    $this->acquired_logs( $data, $debug_mode );

                    if ( is_array( $payment_submit_response ) && isset( $payment_submit_response['response_code'] ) ) {
                        $order = wc_get_order( $order_id );

                        if ( '1' == $payment_submit_response['response_code'] ) {
                            $order->payment_complete( $payment_submit_response['transaction_id'] );
                            $message                   = sprintf(
                            /* translators: %s: transaction */                                __( 'Acquired Payments charge successfully %s', 'acquired-payment' ),
                                wc_price( $order->get_total() )
                            );
                            $request_array['order_id'] = $order_id;
                            $order->add_order_note( $message );

                            if ( isset( WC()->cart ) ) {
                                WC()->cart->empty_cart();
                            }

                            return (int) $order_id;
                        } else {
                            $message = __(
                                    'Transaction Failed: ',
                                    'acquired-payment'
                                ) . $payment_submit_response['response_code'] . ' - ' . $payment_submit_response['response_message'];
                            $order->add_order_note( $message );
                        }

                        return 'Rebill ' . $message;
                    }
                }
            }
        }

        $params = array_merge( $params_request, $billing_address, $shipping_address );
        $params = array_filter($params);

        $hash_string    = $this->create_hash_value( $params, $api_info['company_hashcode'] );
        $params['hash'] = $hash_string;
        $path           = 'sandbox' == $api_info['mode'] ? 'https://qahpp.acquired.com/link' : 'https://hpp.acquired.com/link';

        $this->acquired_logs( 'get_payment_link data', $debug_mode );
        $this->acquired_logs( $params, $debug_mode );
        $payment_link = $this->acquired_api_request( $path, 'POST', $params );
        $this->acquired_logs( 'get_payment_link response', $debug_mode );
        $this->acquired_logs( $payment_link, $debug_mode );

        if ( ! empty( $payment_link['payment_link'] ) ) {
            return $payment_link['payment_link'];
        } else {
            return $payment_link['message'];
        }
    }

    /**
     * Get payment link from API without order
     *
     * @param mixed $wc_acquired_data AcquiredObject $wc_acquired_data.
     *
     * @return string
     * @throws Exception Exception $exception.
     */
    public function get_payment_link_without_order( $wc_acquired_data ) {
        $api_info = $wc_acquired_data['acquired-data'];
        if ( ! is_array( $api_info ) ) {
            $api_info = (array) $api_info;
        }
        $order_id       = $wc_acquired_data['order_id'];
        $params_request = array(
            'company_id'           => $api_info['company_id'],
            'company_mid_id'       => $api_info['company_mid_id'],
            'merchant_order_id'    => $order_id,
            'transaction_type'     => 'AUTH_ONLY',
            'subscription_type'    => 'INIT',
            'currency_code_iso3'   => get_woocommerce_currency(),
            'amount'               => '0',
            'is_tds'               => (int) $api_info['three_d_secure'],
            'tds_source'           => 1,
            'tds_type'             => 2,
            'merchant_contact_url' => site_url(),
            'billing_email'        => wp_get_current_user()->user_email,
//            'template_id'          => ! empty( $api_info['template_id'] ) ? $api_info['template_id'] : '',
            'callback_url'         => site_url( 'wp-json/wc/v3/acquired' ),
            'expiry_time'          => ! empty( $api_info['expiry_time'] ) ? $api_info['expiry_time'] : 86400,
            'retry_attempts'       => ! empty( $api_info['retry_attempts'] ) ? $api_info['retry_attempts'] : 3,
            'return_method'        => 'post_message',
            'error_url'            => site_url( 'wp-json/wc/v3/acquired' ),
            'cancel_url'           => get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ),
            'load_balance'         => 1,
            'merchant_custom_1'    => (string) $order_id,
        );
        $debug_mode     = 1 == $api_info['debug_mode'];

        foreach ( $params_request as $key_param => $value ) {
            if ( '' == $value ) {
                unset( $params_request[ $key_param ] );
            }
        }

        $hash_string            = $this->create_hash_value( $params_request, $api_info['company_hashcode'] );
        $params_request['hash'] = $hash_string;
        $path                   = 'sandbox' == $api_info['mode'] ? 'https://qahpp.acquired.com/link' : 'https://hpp.acquired.com/link';
        $this->acquired_logs( 'get_payment_link_without_order data', $debug_mode );
        $this->acquired_logs( $params_request, $debug_mode );
        $payment_link = $this->acquired_api_request( $path, 'POST', $params_request );
        $this->acquired_logs( 'get_payment_link_without_order response', $debug_mode );
        $this->acquired_logs( $payment_link, $debug_mode );

        return $payment_link;
    }

    /**
     * Acquired API required field
     *
     * @return string[]
     */
    public function api_required_field() {
        return array(
            'company_id',
            'company_mid_id',
            'template_id',
            'company_pass',
            'company_hashcode',
        );
    }

    /**
     * Send request to API
     *
     * @param mixed $path Path $path.
     * @param mixed $method Method $method.
     * @param null  $body Body $body.
     *
     * @return mixed
     * @throws Exception Exception $exception.
     */
    public function acquired_api_request( $path, $method, $body = null ) {
        if ( ! is_array( $body ) ) {
            $this->acquired_logs( 'Body request is not array when ' . $method . ' to ' . $path, true );
            throw new Exception( 'Body request is not array when ' . $method . ' to ' . $path );
        }
        $request = array(
            'headers' => $this->api_request_header(),
            'method'  => $method,
            'body'    => json_encode( $body ),
        );

        $response      = wp_remote_request( $path, $request );
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( wp_remote_retrieve_response_code( $response ) > 500 ) {
            $this->acquired_logs( "Failed request to $method $path. Error code " . $response_body['code'] . ': ' . $response_body['message'], true );
            throw new Exception( 'Something went wrong. Error code ' . $response_body['code'] . ': ' . $response_body['message'] );
        }

        return $response_body;
    }

    /**
     * WP remote headers
     *
     * @return array
     */
    public function api_request_header() {
        global $wp_version;
        global $woocommerce;

        return array(
            'User-Agent'   => 'Acquired Payment Gateway/' . ACQUIRED_VERSION
                . ' WooCommerce/' . $woocommerce->version
                . ' Wordpress/' . $wp_version
                . ' PHP/' . PHP_VERSION,
            'Content-Type' => 'application/json',

        );
    }

    /**
     * Build logging system
     *
     * @param mixed $message Message $message.
     * @param bool  $accept Accept $accept.
     */
    public function acquired_logs( $message, $accept = true ) {
        $logger  = wc_get_logger();
        $enabled = get_option( 'acquired_debug_mode' );
        if ( 1 == $enabled || true == $accept || '1' == $accept ) {
            if ( is_array( $message ) ) {
                $message = print_r( $message, true );
            }
            $handle = 'acquired-log-action';
            $logger->add( $handle, $message );
        }
    }

    /**
     * Process response
     *
     * @param mixed $order_id OrderID $order_id.
     * @param mixed $params Params $params.
     */
    public function process_response( $order_id, $params ) {
        try {
            $api_code       = $params['code'];
            $transaction_id = ! empty( $params['transaction_id'] ) ? $params['transaction_id'] : '';
            $hash           = ! empty( $params['hash'] ) ? $params['hash'] : '';
            $order          = wc_get_order( $order_id );
            $order->set_transaction_id( $transaction_id );
            if (
                ( '1' == $api_code && !array_key_exists('response_code', $params) ) ||
                ( '1' == $api_code && array_key_exists('response_code', $params) && '540' != $params['response_code'] )
                 ) {
                update_post_meta( $order_id, '_transaction_id', $transaction_id );
                update_post_meta( $order_id, '_hash', $hash );
                $transaction_type = get_post_meta( $order_id, '_transaction_type_acquired', true );
                $order->set_transaction_id( $transaction_id );
                if ( 'AUTH_ONLY' == $transaction_type ) {
                    update_post_meta( $order_id, 'acquired_capture', 'not_yet' );
                } else {
                    $this->set_status_order_acquired( $order );
                }
                $message = sprintf(
                    __( 'Acquired Payments via %1$s (Transaction ID: %2$s)', 'acquired-payment' ), 'i-frame', $transaction_id
                );
                $order->add_order_note( $message );
                if ( isset( WC()->cart ) ) {
                    WC()->cart->empty_cart();
                }
// 				wp_redirect( $order->get_checkout_order_received_url() );
                exit();
            } else {
                if ( array_key_exists('response_code',$api_code ) && '540' == $api_code ) {
                    $error_message = __( 'Acquired Payments error: ' ).$api_code['response_message'];
                } else {
                    $error_message = $this->mapping_error_code( $params['code'] );
                }
                $order->add_order_note( $error_message );
                $order->update_status( 'failed' );
// 				wp_redirect( site_url( 'checkout' ) );
                exit();
            }
        } catch ( Exception $exception ) {
            $this->acquired_logs( $exception, true );
        }
    }

    /**
     * Get param from callback url
     *
     * @return array
     */
    public function get_params() {
        $results = array();
        if ( ! empty( $_REQUEST ) ) {
            foreach ( $_REQUEST as $key => $value ) {
                if ( is_array( $value ) ) {
                    foreach ( $value as $k => $v ) {
                        $results[ $key ][ $k ] = sanitize_text_field( $v );
                    }
                } else {
                    $results[ $key ] = sanitize_text_field( $value );
                }
            }
        }

        return $results;
    }

    /**
     * @param $order
     */
    public function set_status_order_acquired( $order ) {
        if ( ! empty ( $order ) ) {
            $contains_lottery_product = false;
            $contains_other_product = false;

            foreach( $order->get_items() as $item ) {
                if ( 'lottery' === $item->get_product()->get_type() ) {
                    $contains_lottery_product = true;
                } else {
                    $contains_other_product = true;
                }
            }

            if ( $contains_lottery_product && !$contains_other_product ) {
                $order->update_status( 'completed' );
            } elseif ( $contains_lottery_product && $contains_other_product ) {
                $order->update_status( 'processing' );
            } else {
                $order->payment_complete();
            }
        }
    }

    /**
     * Mapping error code API
     *
     * @param mixed $error_code Error $error_code.
     *
     * @return string
     */
    public function mapping_error_code( $error_code ) {
        $errors = array(
            1   => 'Transaction Success',
            11  => 'Pending',
            101 => 'Your payment was declined, please try again.',
            102 => 'There was an issue with the payment, please try again.',
            103 => 'There was an issue with the payment, please try again.',
            104 => 'There was an issue with the payment, please try again.',
            105 => 'There was an issue with the payment, please try again.',
            106 => 'There was an issue with the payment, please try again.',
            107 => 'There was an issue with the payment, please try again.',
            108 => 'Your payment was blocked, please try again',
            109 => 'There was an issue with the payment, please try again.',
            110 => 'There was an issue with the payment, please try again.',
            111 => 'There was an issue with the payment, please try again.',
            112 => 'There was an issue with the payment, please try again.',
            151 => 'Your payment was blocked, please try again.',
            152 => 'Your payment was blocked, please try again.',
            153 => 'Your payment was blocked, please try again.',
            154 => 'Your payment was blocked, please try again.',
            155 => 'Your payment was blocked, please try again.',
            156 => 'Your payment was blocked, please try again.',
            157 => 'Your payment was blocked, please try again.',
            158 => 'Your payment was blocked, please try again.',
            159 => 'Your payment was blocked, please try again.',
            160 => 'Your payment was blocked, please try again.',
            161 => 'Your payment was blocked, please try again.',
            162 => 'Your payment was blocked, please try again.',
            163 => 'Your payment was blocked, please try again.',
            164 => 'Your payment was blocked, please try again.',
            170 => 'Your payment was blocked, please try again.',
            171 => 'Your payment was blocked, please try again.',
            172 => 'Your payment was blocked, please try again.',
            173 => 'Your payment was blocked, please try again.',
            174 => 'Your payment was blocked, please try again.',
            175 => 'Your payment was blocked, please try again.',
            200 => 'There was an issue with the payment, please try again.',
            201 => 'There was an issue with the payment, please try again.',
            202 => 'There was an issue with the payment, please try again.',
            203 => 'There was an issue with the payment, please try again.',
            204 => 'There was an issue with the payment, please try again.',
            205 => 'There was an issue with the payment, please try again.',
            206 => 'There was an issue with the payment, please try again.',
            207 => 'There was an issue with the payment, please try again.',
            208 => 'There was an issue with the payment, please try again.',
            209 => 'There was an issue with the payment, please try again.',
            210 => 'There was an issue with the payment, please try again.',
            211 => 'There was an issue with the payment, please try again.',
            212 => 'There was an issue with the payment, please try again.',
            213 => 'There was an issue with the payment, please try again.',
            214 => 'There was an issue with the payment, please try again.',
            215 => 'There was an issue with the payment, please try again.',
            216 => 'There was an issue with the payment, please try again.',
            217 => 'There was an issue with the payment, please try again.',
            218 => 'There was an issue with the payment, please try again.',
            219 => 'There was an issue with the payment, please try again.',
            220 => 'Your payment was blocked, please try again.',
            221 => 'There was an issue with the payment, please try again.',
            222 => 'There was an issue with the payment, please try again.',
            223 => 'There was an issue with the payment, please try again.',
            224 => 'There was an issue with the payment, please try again.',
            225 => 'There was an issue with the payment, please try again.',
            226 => 'There was an issue with the payment, please try again.',
            227 => 'There was an issue with the payment, please try again.',
            228 => 'There was an issue with the payment, please try again.',
            229 => 'There was an issue with the payment, please try again.',
            230 => 'There was an issue with the payment, please try again.',
            231 => 'There was an issue with the payment, please try again.',
            232 => 'There was an issue with the payment, please try again.',
            233 => 'There was an issue with the payment, please try again.',
            234 => 'There was an issue with the payment, please try again.',
            235 => 'There was an issue with the payment, please try again.',
            236 => 'There was an issue with the payment, please try again.',
            237 => 'There was an issue with the payment, please try again.',
            238 => 'Your payment was blocked, please try again.',
            251 => 'Your payment was declined, please try again.',
            252 => 'Your payment was blocked, please try again.',
            253 => 'Your payment was blocked, please try again.',
            254 => 'Your payment was blocked, please try again.',
            255 => 'Your payment was blocked, please try again.',
            256 => 'Your payment was blocked, please try again.',
            257 => 'Your payment was blocked, please try again.',
            258 => 'Your payment was blocked, please try again.',
            261 => 'There was an issue with the payment, please try again.',
            262 => 'There was an issue with the payment, please try again.',
            263 => 'There was an issue with the payment, please try again.',
            264 => 'There was an issue with the payment, please try again.',
            265 => 'There was an issue with the payment, please try again.',
            266 => 'There was an issue with the payment, please try again.',
            267 => 'There was an issue with the payment, please try again.',
            268 => 'There was an issue with the payment, please try again.',
            269 => 'There was an issue with the payment, please try again.',
            270 => 'Your payment was blocked, please try again.',
            271 => 'There was an issue with the payment, please try again.',
            272 => 'There was an issue with the payment, please try again.',
            273 => 'There was an issue with the payment, please try again.',
            274 => 'There was an issue with the payment, please try again.',
            275 => 'Your payment was blocked, please try again.',
            276 => 'There was an issue with the payment, please try again.',
            277 => 'Your payment was blocked, please try again.',
            278 => 'There was an issue with the payment, please try again.',
            279 => 'Your payment was blocked, please try again.',
            301 => 'Your payment was declined, please try again.',
            302 => 'Your payment was blocked, please try again.',
            303 => 'Your payment was blocked, please try again.',
            311 => 'Your payment was blocked, please try again.',
            312 => 'Your payment was blocked, please try again.',
            321 => 'Your payment was declined, please try again.',
            322 => 'Your payment was blocked, please try again.',
            323 => 'Your payment was declined, please try again.',
            324 => 'Your payment was declined, please try again.',
            325 => 'Your payment was declined, please try again.',
            351 => 'There was an issue with the payment, please try again.',
            400 => 'There was an issue with the payment, please try again.',
            401 => 'Your payment was Declined, please try again.',
            402 => 'Your payment was Declined, please try again.',
            403 => 'Your payment was declined, please try again.',
            404 => 'Your payment was declined, please try again.',
            405 => 'Your payment was blocked, please try again.',
            406 => 'Your payment was blocked, please try again.',
            407 => 'Your payment was blocked, please try again.',
            408 => 'Your payment was blocked, please try again.',
            409 => 'Your payment was blocked, please try again.',
            501 => 'Pending:Card Enrolled',
            502 => 'Pending:Card Not Enrolled',
            504 => 'There was an issue with the payment, please try again.',
            521 => 'There was an issue with the payment, please try again.',
            522 => 'There was an issue with the payment, please try again.',
            523 => 'There was an issue with the payment, please try again.',
            524 => 'There was an issue with the payment, please try again.',
            525 => 'There was an issue with the payment, please try again.',
            526 => 'There was an issue with the payment, please try again.',
            527 => 'There was an issue with the payment, please try again.',
            528 => 'There was an issue with the payment, please try again.',
            529 => 'There was an issue with the payment, please try again.',
            530 => 'There was an issue with the payment, please try again.',
            531 => 'There was an issue with the payment, please try again.',
            532 => 'There was an issue with the payment, please try again.',
            533 => 'There was an issue with the payment, please try again.',
            534 => 'There was an issue with the payment, please try again.',
            535 => 'There was an issue with the payment, please try again.',
            540 => 'There was an issue with the payment, please try again.',
            541 => 'There was an issue with the payment, please try again.',
            542 => 'There was an issue with the payment, please try again.',
            550 => 'There was an issue with the payment, please try again.',
            565 => 'There was an issue with the payment, please try again.',
            601 => 'There was an issue with the payment, please try again.',
            602 => 'There was an issue with the payment, please try again.',
            603 => 'There was an issue with the payment, please try again.',
            604 => 'There was an issue with the payment, please try again.',
            605 => 'Your payment was declined, please try again.',
            606 => 'There was an issue with the payment, please try again.',
            610 => 'There was an issue with the payment, please try again.',
            611 => 'There was an issue with the payment, please try again.',
            612 => 'There was an issue with the payment, please try again.',
            650 => 'Your payment was declined, please try again.',
            651 => 'Your payment was declined, please try again.',
            652 => 'There was an issue with the payment, please try again.',
            901 => 'There was an issue with the payment, please try again.',
            902 => 'There was an issue with the payment, please try again.',
            903 => 'There was an issue with the payment, please try again.',
            904 => 'There was an issue with the payment, please try again.',
            905 => 'There was an issue with the payment, please try again.',
            906 => 'There was an issue with the payment, please try again.',
            907 => 'There was an issue with the payment, please try again.',
            908 => 'There was an issue with the payment, please try again.',
            909 => 'There was an issue with the payment, please try again.',
            999 => 'There was an issue with the payment, please try again.',
        );
        if ( ! empty( $errors[ $error_code ] ) ) {
            return $errors[ $error_code ];
        }

        return '';
    }

    /**
     * Request hash for VOID,CAPTURE,REFUND request
     *
     * @param mixed $param Param $param.
     * @param mixed $company_hashcode Hashcode $company_hashcode.
     *
     * @return string
     */
    public function request_hash( $param, $company_hashcode ) {
        $str = '';
        if ( in_array(
            $param['transaction_type'],
            array(
                'AUTH_ONLY',
                'AUTH_CAPTURE',
                'CREDIT',
                'BENEFICIARY_NEW',
            )
        ) ) {
            $str = $param['timestamp'] . $param['transaction_type'] . $param['company_id'] .
                $param['merchant_order_id'];
        } elseif (
        in_array(
            $param['transaction_type'],
            array(
                'CAPTURE',
                'VOID',
                'REFUND',
                'PAY_OUT',
                'SUBSCRIPTION_MANAGE',
            )
        ) ) {
            $str = $param['timestamp'] . $param['transaction_type'] . $param['company_id'] .
                $param['original_transaction_id'];
        }

        return hash( 'sha256', $str . $company_hashcode );
    }

    /**
     * Request hash for Query API request
     *
     * @param mixed $param Param $param.
     * @param mixed $company_hashcode Hashcode $company_hashcode.
     *
     * @return string
     */
    public function query_request_hash( $param, $secret ) {
        if( in_array($param['status_request_type'], array('ORDER_ID_ALL','ORDER_ID_FIRST','ORDER_ID_LAST','ORDER_ID_SUCCESS')) ) {
            $str = $param['timestamp'].$param['status_request_type'].$param['company_id'].$param['merchant_order_id'];
        } elseif( in_array($param['status_request_type'], array('TRANSACTION_ID','TRANSACTION_ID_CHILDREN_ALL','TRANSACTION_ID_CHILDREN_FIRST','TRANSACTION_ID_CHILDREN_LAST','TRANSACTION_ID_CHILDREN_SUCCESS')) ) {
            $str = $param['timestamp'].$param['status_request_type'].$param['company_id'].$param['transaction_id'];
        } elseif( in_array($param['status_request_type'], array('BIN')) ) {
            $str = $param['timestamp'].$param['status_request_type'].$param['company_id'].$param['bin'];
        }

        return hash('sha256',$str.$secret);
    }

    /**
     * Insert transaction to Acquired Transaction Grid View
     *
     * @param mixed $data Data $data.
     * @param null  $type Type $type.
     *
     * @return false
     */
    public function create_transaction( $data, $type = null ) {
        global $wpdb;
        if ( ! empty( $data ) && is_array( $data ) && '' != $data['transaction_id'] ) {
            $transaction_id = $data['transaction_id'];
            $page            = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->prefix}posts WHERE post_title = %s AND post_type = %s",
                    $transaction_id,
                    ACQUIRED_POST_TYPE
                )
            );
            if ( ! $page ) {
                $post_id = wp_insert_post(
                    array(
                        'comment_status' => 'closed',
                        'ping_status'    => 'closed',
                        'post_author'    => get_current_user_id(),
                        'post_title'     => $data['transaction_id'],
                        'post_status'    => 'publish',
                        'post_type'      => ACQUIRED_POST_TYPE,
                    )
                );
                if ( 0 == $post_id ) {
                    return false;
                }
                update_post_meta( $post_id, 'payment_method', $type );
                if ( ! empty( $data['transaction_type'] ) ) {
                    update_post_meta( $post_id, 'transaction_type', $data['transaction_type'] );
                    update_post_meta( $post_id, 'message', $data['response_message'] );
                }
                if ( ! empty( $data['order_id'] ) ) {
                    update_post_meta( $post_id, 'order_id', $data['order_id'] );
                    $wc_order = wc_get_order( $data['order_id'] );
                } else {
                    if ( 'SUBSCRIPTION_MANAGE' == $data['transaction_type'] ) {
                        $original_order_id = substr( $data['merchant_order_id'], 19, strlen( $data['merchant_order_id'] ) );
                        $wc_order          = wc_get_order( $original_order_id );
                    } else {
                        update_post_meta( $post_id, 'order_id', $data['merchant_order_id'] );
                        $wc_order = wc_get_order( $data['merchant_order_id'] );
                    }
                }

                if ( ! empty( $wc_order ) ) {
                    update_post_meta( $post_id, 'billing_email', $wc_order->get_billing_email() );
                }
                foreach ( $data as $key => $value ) {
                    update_post_meta( $post_id, $key, $value );
                }
            }
        } else {
            return false;
        }
    }

    /**
     * Get payment url
     *
     * @param mixed $params Params $param.
     *
     * @return string
     */
    public function get_payment_url( $params ) {
        return site_url( 'acquired' ) . '?' . http_build_query( $params, '', '&' );
    }

    /**
     * Save card function
     *
     * @param mixed $user_id User $user_id.
     * @param mixed $response Response $response.
     */
    public function save_card( $user_id, $response ) {
        if ( 0 != $user_id && class_exists( 'WC_Payment_Token_CC' ) ) {
            $wc_token = new WC_Payment_Token_CC();
            $wc_token->set_token( $response['card_identifier'] );
            $wc_token->set_gateway_id( 'acquired' );
            $wc_token->set_card_type( strtolower( $response['card_type'] ) );
            $wc_token->set_last4( substr( $response['card_number'], - 4 ) );
            $wc_token->set_expiry_month( substr( $response['expiry_date'], 0, 2 ) );
            $wc_token->set_expiry_year( substr( $response['expiry_date'], - 4 ) );
            $wc_token->set_user_id( $user_id );
            $wc_token->save();
        }
    }

    /**
     * Generate Code
     *
     * @param mixed $pattern Pattern $pattern.
     *
     * @return string|string[]|null
     */
    public function generate_code( $pattern ) {
        $gen_arr = array();

        preg_match_all( '/\[[AN][.*\d]*\]/', $pattern, $matches, PREG_SET_ORDER );
        foreach ( $matches as $match ) {
            $delegate = substr( $match [0], 1, 1 );
            $length   = substr( $match [0], 2, strlen( $match [0] ) - 3 );
            if ( 'A' == $delegate ) {

                $gen = $this->generate_string( $length );
            } elseif ( 'N' == $delegate ) {

                $gen = $this->generate_num( $length );
            }

            $gen_arr [] = $gen;
        }

        foreach ( $gen_arr as $g ) {
            $pattern = preg_replace( '/\[[AN][.*\d]*\]/', $g, $pattern, 1 );
        }

        return $pattern;
    }

    /**
     * Generate string contain number digit
     *
     * @param int $length Length $length.
     *
     * @return string
     */
    public function generate_string( $length ) {
        if ( 0 == $length || null == $length || '' == $length ) {
            $length = 5;
        }
        $c    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $rand = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $rand .= $c [ rand() % strlen( $c ) ];
        }

        return $rand;
    }

    /**
     * Generate number contain number digit
     *
     * @param int $length Length $length.
     *
     * @return string
     */
    public function generate_num( $length ) {
        if ( 0 == $length || null == $length || '' == $length ) {
            $length = 5;
        }
        $c    = '0123456789';
        $rand = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $rand .= $c [ rand() % strlen( $c ) ];
        }

        return $rand;
    }

    /**
     * Including tax
     *
     * @return bool
     */
    public function wc_acquired_display_prices_including_tax() {
        $cart = WC()->cart;
        if ( method_exists( $cart, 'display_prices_including_tax' ) ) {
            return $cart->display_prices_including_tax();
        }
        if ( is_callable( array( $cart, 'get_tax_price_display_mode' ) ) ) {
            return 'incl' == $cart->get_tax_price_display_mode() && ( WC()->customer && ! WC()->customer->is_vat_exempt() );
        }

        return 'incl' == $cart->tax_display_cart && ( WC()->customer && ! WC()->customer->is_vat_exempt() );
    }

    /**
     * Submit Google Pay Order
     *
     * @param mixed $order Order $order.
     * @param mixed $payment_data Data $payment_data.
     * @param mixed $tds Tds $tds.
     *
     * @return int
     * @throws Exception Exception $exception.
     */
    public function submit_googlepay_order( $order, $payment_data, $tds ) {
        $api_endpoint_sandbox = 'https://qaapi.acquired.com/api.php';
        $api_endpoint_live    = 'https://gateway.acquired.com/api.php';
        $order_id             = $order->get_id();
        $gpay_token           = $_POST['billingToken'];
        $google_settings      = get_option( 'woocommerce_google_settings' );

        $transaction_info = array(
            'merchant_order_id'  => $order_id,
            'transaction_type'   => $google_settings['transaction_type'],
            'amount'             => $order->get_total(),
            'currency_code_iso3' => $order->get_currency(),
        );
        $payment_info     = array(
            'method'       => 'google_pay',
            'token'        => $gpay_token,
            'display_name' => $_POST['paymentMethodTitle'],
            'network'      => $payment_data['paymentMethodData']->info->cardNetwork,
        );
        $billing_info     = array(
            'billing_street'            => $order->get_billing_address_1(),
            'billing_street2'           => $order->get_billing_address_2(),
            'billing_city'              => $order->get_billing_city(),
            'billing_state'             => $order->get_billing_state(),
            'billing_zipcode'           => $order->get_billing_postcode(),
            'billing_country_code_iso2' => $order->get_billing_country(),
            'billing_phone'             => str_replace( '+', '', $order->get_billing_phone() ),
            'billing_email'             => $payment_data['email'],
        );

        foreach ( $billing_info as $key => $value ) {
            if ( '' == $value ) {
                unset( $billing_info[ $key ] );
            }
        }

        $data = array(
            'timestamp'    => strftime( '%Y%m%d%H%M%S' ),
            'company_id'   => $google_settings['gateway_merchant_id'],
            'company_pass' => $google_settings['company_pass'],
            'transaction'  => $transaction_info,
            'payment'      => $payment_info,
        );

        $request_hash         = $this->request_hash(
            array_merge( $transaction_info, $payment_info, $data ),
            $google_settings['company_hashcode']
        );
        $data['request_hash'] = $request_hash;
        $data['billing']      = $billing_info;
        if ( array_key_exists('tds_action', $google_settings) && 1 == $google_settings['tds_action'] ) {
            $data['tds']['action'] = 'ENQUIRE';
        } elseif ( array_key_exists('tds_action', $google_settings) && 2 == $google_settings['tds_action'] ) {
            $data['tds']['action']                      = 'SCA';
            $data['tds']['source']                      = '1';
            $data['tds']['type']                        = '2';
            $data['tds']['preference']                  = '0';
            $data['tds']['method_url_complete']         = '1';
            $data['tds']['browser_data']                         = (array) $tds['browser_data'];
            $data['tds']['browser_data']['ip']          = $this->get_client_ip();
            $data['tds']['browser_data']['color_depth'] = $this->mapping_color_depth( (string) $data['tds']['browser_data']['color_depth'] );
            $data['tds']['merchant']['contact_url']     = site_url( 'contact' );
            $data['tds']['merchant']['challenge_url']   = site_url( 'wc-api/wc_google_payment/?order_id=' . $order_id );
        }
        $path = 'sandbox' == $google_settings['mode'] ? $api_endpoint_sandbox : $api_endpoint_live;

        $this->acquired_logs( 'submit_googlepay_order data', $google_settings['debug_mode'] );
        $this->acquired_logs( $data, $google_settings['debug_mode'] );
        $payment_submit_response = $this->acquired_api_request( $path, 'POST', $data );
        $this->acquired_logs( 'submit_googlepay_order response', $google_settings['debug_mode'] );
        $this->acquired_logs( $payment_submit_response, $google_settings['debug_mode'] );

        if ( is_array( $payment_submit_response ) && isset( $payment_submit_response['response_code'] ) ) {
            if ( isset( $payment_submit_response['transaction_id'] ) ) {
                $order->set_transaction_id( $payment_submit_response['transaction_id'] );
                $order->save();
            }
            if ( '1' == $payment_submit_response['response_code'] ) {
                $message = sprintf(
                /* translators: %s: transaction */                    __( 'Google Pay (Acquired Payments) charge successfully %1$s (Transaction ID: %2$s)', 'acquired-payment' ),
                    wc_price( $order->get_total() ),
                    $payment_submit_response['transaction_id']
                );

                $order->add_order_note( $message );
                $this->set_status_order_acquired( $order );

                return 1;
            } elseif ( '501' == $payment_submit_response['response_code'] || '503' == $payment_submit_response['response_code'] ) {
                $message = sprintf(
                /* translators: %s: transaction */                    __( 'Google Pay (Acquired Payments) create transaction successfully %1$s (Transaction ID: %2$s)', 'acquired-payment' ),
                    wc_price( $order->get_total() ),
                    $payment_submit_response['transaction_id']
                );
                $order->add_order_note( $message );

                $term_url = site_url( 'wc-api/wc_google_payment' ) . '?order_id=' . $order_id;

                foreach ( $payment_submit_response['tds'] as $tds_param_key => $tds_param_value ) {
                    WC()->session->set( 'acquired_gpay_' . $tds_param_key, $tds_param_value );
                }
                WC()->session->set( 'acquired_gpay_term_url', $term_url );
                WC()->session->set( 'acquired_gpay_md', $order_id );
                update_post_meta( $order_id, 'acquired_gpay_response_code', $payment_submit_response['response_code'] );

                return 2;
            } else {
                $message = __(
                        'Cannot submit order to Acquired. Code ',
                        'acquired-payment'
                    ) . $payment_submit_response['response_code'] . ' - ' . $payment_submit_response['response_message'];
                $order->add_order_note( $message );
                $order->update_status( 'failed' );

                return 0;
            }
        }
    }

    /**
     * Get client ip
     *
     * @return mixed|string
     */
    public function get_client_ip() {
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $ipaddress = '';
        if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ipaddress = wp_unslash( $_SERVER['HTTP_CLIENT_IP'] );
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ipaddress = wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] );
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED'] ) ) {
            $ipaddress = wp_unslash( $_SERVER['HTTP_X_FORWARDED'] );
        } elseif ( isset( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
            $ipaddress = wp_unslash( $_SERVER['HTTP_FORWARDED_FOR'] );
        } elseif ( isset( $_SERVER['HTTP_FORWARDED'] ) ) {
            $ipaddress = wp_unslash( $_SERVER['HTTP_FORWARDED'] );
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ipaddress = wp_unslash( $_SERVER['REMOTE_ADDR'] );
        } else {
            $ipaddress = 'UNKNOWN';
        }
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        return $ipaddress;
    }

    /**
     * Mapping Color Depth
     *
     * @param string $color_depth ColorDepth $color_depth.
     *
     * @return string
     */
    public function mapping_color_depth( $color_depth ) {
        $all_color = array(
            '1'  => 'ONE_BIT',
            '2'  => 'TWO_BITS',
            '4'  => 'FOUR_BITS',
            '8'  => 'EIGHT_BITS',
            '15' => 'FIFTEEN_BITS',
            '16' => 'SIXTEEN_BITS',
            '24' => 'TWENTY_FOUR_BITS',
            '32' => 'THIRTY_TWO_BITS',
            '48' => 'FORTY_EIGHT_BITS',
        );

        if ( isset( $all_color[ $color_depth ] ) ) {
            return $all_color[ $color_depth ];
        } else {
            return $color_depth;
        }
    }

    /**
     * Check payment section
     *
     * @param mixed $payment_method Payment $payment_method.
     * @param mixed $section Section $section.
     *
     * @return bool
     */
    public function is_payment_section( $payment_method, $section ) {
        $payment_sections = $payment_method->get_option( 'payment_sections' );

        if ( $payment_sections ) {
            foreach ( $payment_sections as $payment_section ) {
                if ( $payment_section == $section ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Submit Apple Pay Order
     *
     * @param mixed $order Order $order.
     * @param mixed $payment_data Payment $payment_data.
     *
     * @throws Exception Exception $exception.
     */
    public function submit_applepay_order( $order, $payment_data ) {
        $api_endpoint_sandbox = 'https://qaapi.acquired.com/api.php';
        $api_endpoint_live    = 'https://gateway.acquired.com/api.php';
        $order_id             = $order->get_id();
        $applepay_token       = $_POST['billingToken'];
        $apple_settings       = get_option( 'woocommerce_apple_settings' );

        $transaction_info = array(
            'merchant_order_id'  => $order_id,
            'transaction_type'   => $apple_settings['charge_type'],
            // 'amount'             => WC()->cart->get_cart_contents_total(),
            'amount'             => $order->get_total(),
            'currency_code_iso3' => $order->get_currency(),
        );
        $payment_info     = array(
            'method'       => 'apple_pay',
            'token'        => $applepay_token,
            'display_name' => $_POST['paymentMethodTitle'],
            'network'      => $payment_data['token']->paymentMethod->network,
        );
        $billing_info     = array(
//			'billing_street'            => implode( ', ', $order->get_billing_address_1() ),
//			'billing_street2'           => implode( ', ', $order->get_billing_address_2() ),
            'billing_street'            => $order->get_billing_address_1(),
            'billing_street2'           => $order->get_billing_address_2(),
            'billing_city'              => $order->get_billing_city(),
            'billing_state'             => $order->get_billing_state(),
            'billing_zipcode'           => $order->get_billing_postcode(),
            'billing_country_code_iso2' => strtoupper($order->get_billing_country()),
            'billing_phone'             => $order->get_billing_phone(),
            'billing_email'             =>  $order->get_billing_email(),
            // 'billing_email'             => $payment_data['shippingContact']->emailAddress,
        );

        foreach ( $billing_info as $key => $value ) {
            if ( '' === $value ) {
                unset( $billing_info[ $key ] );
            }
        }

        $data = array(
            'timestamp'    => strftime( '%Y%m%d%H%M%S' ),
            'company_id'   => $apple_settings['merchant_id'],
            'company_pass' => $apple_settings['company_pass'],
            'transaction'  => $transaction_info,
            'payment'      => $payment_info,
        );

        $request_hash         = $this->request_hash(
            array_merge( $transaction_info, $payment_info, $data ),
            $apple_settings['company_hashcode']
        );
        $data['request_hash'] = $request_hash;
        $data['billing']      = $billing_info;
        $path                 = 'sandbox' == $apple_settings['mode'] ? $api_endpoint_sandbox : $api_endpoint_live;

        $this->acquired_logs( 'submit_applepay_order data', $apple_settings['debug_mode'] );
        $this->acquired_logs( $data, $apple_settings['debug_mode'] );
        $payment_submit_response = $this->acquired_api_request( $path, 'POST', $data );
        $this->acquired_logs( 'submit_applepay_order response', $apple_settings['debug_mode'] );
        $this->acquired_logs( $payment_submit_response, $apple_settings['debug_mode'] );

        if ( is_array( $payment_submit_response ) && isset( $payment_submit_response['response_code'] ) ) {
            if ( isset( $payment_submit_response['transaction_id'] ) ) {
                $order->set_transaction_id( $payment_submit_response['transaction_id'] );
            }
            if ( '1' == $payment_submit_response['response_code'] ) {
                $message                   = sprintf(
                /* translators: %s: total */                    __( 'Apple Pay (Acquired Payments) charge successfully %s', 'acquired-payment' ),
                    wc_price( WC()->cart->get_cart_contents_total() )
                );
                $request_array['order_id'] = $order_id;
                $order->add_order_note( $message );
                $this->set_status_order_acquired( $order );
            } else {
                $message = __(
                        'Cannot submit order to Acquired. Code ',
                        'acquired-payment'
                    ) . $payment_submit_response['response_code'] . ' - ' . $payment_submit_response['response_message'];
                $order->add_order_note( $message );
                $order->update_status( 'failed' );
            }
        }
    }

    /**
     * Acquired Update Billing
     *
     * @param mixed $subscription Subscription $subscription.
     * @param mixed $address Address $address.
     * @param mixed $api_info API $api_info.
     */
    public function acquired_update_billing( $subscription, $address, $api_info ) {
        $subcription_order_id = $subscription->get_id();

        if ( $subscription->get_parent_id() ) {
            $get_transaction_id = wc_get_order( $subscription->get_parent_id() )->get_transaction_id();
            if ( '' === $get_transaction_id ) {
                $original_transaction_id = get_post_meta( $subscription->get_parent_id(), 'original_transaction_id', true );
            }
        } else {
            if ( ! empty( $subscription->get_transaction_id() ) ) {
                $original_transaction_id = $subscription->get_transaction_id();
            } else {
                return;
            }
        }
        $transaction = array(
            'merchant_order_id'       => $this->generate_code( 'UPDATE_BILLING_[N3]_' . $subcription_order_id . '' ),
            'transaction_type'        => 'SUBSCRIPTION_MANAGE',
            'subscription_type'       => 'UPDATE_BILLING',
            'original_transaction_id' => $original_transaction_id,
        );
        $billing     = array(
            'billing_street'            => ! empty( $address['billing_address_1'] ) ? $address['billing_address_1'] : $address['_billing_address_1'],
            'billing_street2'           => ! empty( $address['billing_address_2'] ) ? $address['billing_address_2'] : $address['_billing_address_2'],
            'billing_city'              => ! empty( $address['billing_city'] ) ? $address['billing_city'] : $address['_billing_city'],
            'billing_state'             => ! empty( $address['billing_state'] ) ? $address['billing_state'] : $address['_billing_state'],
            'billing_zipcode'           => ! empty( $address['billing_postcode'] ) ? $address['billing_postcode'] : $address['_billing_postcode'],
            'billing_country_code_iso2' => ! empty( $address['billing_country'] ) ? $address['billing_country'] : $address['_billing_country'],
            'billing_phone'             => ! empty( $address['billing_phone'] ) ? $address['billing_phone'] : $address['_billing_phone'],
            'billing_email'             => ! empty( $address['billing_email'] ) ? $address['billing_email'] : $address['_billing_email'],
        );

        foreach ( $billing as $key => $value ) {
            if ( '' === $value ) {
                unset( $billing[ $key ] );
            }
        }

        $data = array(
            'timestamp'      => strftime( '%Y%m%d%H%M%S' ),
            'company_id'     => $api_info->company_id,
            'company_pass'   => $api_info->company_pass,
            'company_mid_id' => $api_info->company_mid_id,
        );

        $request_hash         = $this->request_hash(
            array_merge( $transaction, $billing, $data ),
            $api_info->company_hashcode
        );
        $data['request_hash'] = $request_hash;
        $data['transaction']  = $transaction;
        $data['billing']      = $billing;
        $path                 = 'sandbox' == $api_info->mode ? $api_info->api_endpoint_sandbox : $api_info->api_endpoint_live;

        $api_info->_helper->acquired_logs( 'update_billing Subscription data', $api_info->debug_mode );
        $api_info->_helper->acquired_logs( $data, $api_info->debug_mode );
        $update_billing_response = $api_info->_helper->acquired_api_request( $path, 'POST', $data );
        $api_info->_helper->acquired_logs( 'update_billing Subscription response', $api_info->debug_mode );
        $api_info->_helper->acquired_logs( $update_billing_response, $api_info->debug_mode );

        if ( is_array( $update_billing_response ) && isset( $update_billing_response['response_code'] ) ) {
            $order = wc_get_order( $subscription->get_id() );
            if ( '1' == $update_billing_response['response_code'] ) {
                $message = sprintf(
                /* translators: %s: transaction */                    __( 'Acquired update subscription billing address successfully. (Transaction ID: %s)', 'acquired-payment' ),
                    $update_billing_response['transaction_id']
                );
                $order->add_order_note( $message );
            } else {
                $message = __(
                        'Cannot update billing address to Acquired. Code ',
                        'acquired-payment'
                    ) . $update_billing_response['response_code'] . ' - ' . $update_billing_response['response_message'];
                $order->add_order_note( $message );
            }
        }
    }

    /**
     * Isset subscription product
     *
     * @return false
     */
    public function check_if_isset_subscription_product() {
        if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
            if ( ! empty( WC()->cart ) ) {
                foreach ( WC()->cart->get_cart() as $cart_item ) {
                    if ( in_array( $cart_item['data']->get_type(), array( 'subscription', 'subscription_variation' ) ) ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get apple sdk config
     *
     * @param mixed $apple_instance AppleObject $apple_instance.
     *
     * @return array|false
     */
    public function get_apple_sdk_config( $apple_instance ) {
        $data = array(
            'timestamp'    => strftime( '%Y%m%d%H%M%S' ),
            'company_id'   => $apple_instance->merchant_id,
            'company_pass' => $apple_instance->company_pass,
        );

        $transaction_info = array(
            'status_request_type' => 'SDK',
            'company_mid_id'      => $apple_instance->company_mid_id,
        );

        $request_hash_string  = $data['timestamp'] . $transaction_info['status_request_type'] . $data['company_id'] .
            $transaction_info['company_mid_id'] . $apple_instance->company_hashcode;
        $data['request_hash'] = hash( 'sha256', $request_hash_string );
        $data['transaction']  = $transaction_info;

        $path = 'sandbox' == $apple_instance->mode ? $apple_instance->sdk_endpoint_sandbox : $apple_instance->sdk_endpoint_live;

//        $this->acquired_logs( 'check_apple_sdk Apple Pay data', $apple_instance->debug_mode );
//        $this->acquired_logs( $data, $apple_instance->debug_mode );
        try {
            $sdk_response = $this->acquired_api_request( $path, 'POST', $data );
//            $this->acquired_logs( 'check_apple_sdk Apple Pay data', $apple_instance->debug_mode );
//            $this->acquired_logs( $sdk_response, $apple_instance->debug_mode );

            if ( is_array( $sdk_response ) && isset( $sdk_response['response_code'] ) ) {
                if ( '1' == $sdk_response['response_code'] ) {
                    return $sdk_response;
                }
            }
        } catch ( Exception $e ) {
            $this->acquired_logs( $e, $apple_instance->debug_mode );
        }

        return false;
    }

    /**
     * Settlement GPay Request
     *
     * @param mixed $gpay_setting Google $gpay_setting.
     * @param mixed $order Order $order.
     * @param mixed $threeds_data 3DS $threeds_data.
     *
     * @return array
     */
    public function settlement_gpay_request( $gpay_setting, $order, $threeds_data ) {
        $data = array(
            'timestamp'      => strftime( '%Y%m%d%H%M%S' ),
            'company_id'     => $gpay_setting->gateway_merchant_id,
            'company_mid_id' => $gpay_setting->company_mid_id,
            'company_pass'   => $gpay_setting->company_pass,
        );

        $transaction_info = array(
            'merchant_order_id'       => $order->get_id(),
            'transaction_type'        => $gpay_setting->transaction_type,
            'original_transaction_id' => $order->get_transaction_id(),
            // 'amount'                  => $order->get_total(),
            'amount'                  => WC()->cart->get_cart_contents_total(),
            'currency_code_iso3'      => $order->get_currency(),
        );

        $gpay_response_code = get_post_meta( $order->get_id(), 'acquired_gpay_response_code', true );
        if ( 501 == $gpay_response_code ) {
            $tds = array(
                'action' => 'SETTLEMENT',
                'pares'  => $threeds_data,
            );
        } else {
            $tds = array(
                'action' => 'SCA_COMPLETE',
                'cres'   => $threeds_data,
            );

            unset( $data['company_mid_id'] );
            unset( $transaction_info['merchant_order_id'] );
            unset( $transaction_info['amount'] );
            unset( $transaction_info['currency_code_iso3'] );
        }

        $request_hash         = $this->request_hash(
            array_merge( $transaction_info, $tds, $data ),
            $gpay_setting->company_hashcode
        );
        $data['request_hash'] = $request_hash;
        $data['transaction']  = $transaction_info;
        $data['tds']          = $tds;

        $path = 'sandbox' == $gpay_setting->mode ? $gpay_setting->api_endpoint_sandbox : $gpay_setting->api_endpoint_live;

        $this->acquired_logs( 'settlement_gpay_request data', $gpay_setting->debug_mode );
        $this->acquired_logs( $data, $gpay_setting->debug_mode );
        try {
            $settlement_gpay_response = $this->acquired_api_request( $path, 'POST', $data );
            $this->acquired_logs( 'settlement_gpay_request Pay data', $gpay_setting->debug_mode );
            $this->acquired_logs( $settlement_gpay_response, $gpay_setting->debug_mode );

            if ( is_array( $settlement_gpay_response ) && isset( $settlement_gpay_response['response_code'] ) ) {
                return $settlement_gpay_response;
            }
        } catch ( Exception $e ) {
            $this->acquired_logs( $e, $gpay_setting->debug_mode );
        }

        return array();
    }
}
