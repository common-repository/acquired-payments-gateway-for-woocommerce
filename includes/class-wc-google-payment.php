<?php
/**
 * WC Google Pay
 *
 * @package acquired-payment/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides a Google Pay Payment.
 *
 * @property WC_Acquired_Helper _helper
 * @class  WC_Google_Payment
 */
class WC_Google_Payment extends WC_Payment_Gateway {
    /**
     * Enable
     *
     * @var string $enable_google
     */
    public $enable_google;

    /**
     * API Info
     *
     * @var string $merchant_id
     */
    public $merchant_id;

    /**
     * API Info
     *
     * @var string $merchant_name
     */
    public $merchant_name;

    /**
     * API Info
     *
     * @var string $debug_mode
     */
    public $debug_mode;

    /**
     * API Info
     *
     * @var string $api_endpoint_sandbox
     */
    public $api_endpoint_sandbox;

    /**
     * API Info
     *
     * @var string $payment_link_endpoint_live
     */
    public $api_endpoint_live;

    /**
     * API Info
     *
     * @var string $mode
     */
    public $mode;

    /**
     * API Info
     *
     * @var string $gateway_merchant_id
     */
    public $gateway_merchant_id;

    /**
     * API Info
     *
     * @var $tds_action
     */
    public $tds_action;

    /**
     * API Info
     *
     * @var $company_pass
     */
    public $company_pass;

    /**
     * API Info
     *
     * @var $company_mid_id
     */
    public $company_mid_id;

    /**
     * API Info
     *
     * @var $company_hashcode
     */
    public $company_hashcode;

    /**
     * API Info
     *
     * @var $transaction_type
     */
    public $transaction_type;

    /**
     * API Info
     *
     * @var $challenge_window_size
     */
    public $challenge_window_size;

    /**
     * WC_Acquired_Payment_Abstract constructor.
     */
    public function __construct() {
        $this->id                    = 'google';
        $this->title                 = $this->get_option( 'title', __( 'Google Pay', 'acquired-payment' ) );
        $this->method_title          = __( 'Google Pay', 'acquired-payment' );
        $this->method_description    = $this->get_option(
            'description',
            __( 'Express checkout with Google Pay.', 'acquired-payment' )
        );
        $this->enable_google         = $this->get_option( 'enabled', 0 );
        $this->merchant_id           = $this->get_option( 'merchant_id', '' );
        $this->merchant_name         = $this->get_option( 'merchant_name' );
        $this->debug_mode            = $this->get_option( 'debug_mode', 1 );
        $this->api_endpoint_sandbox  = 'https://qaapi.acquired.com/api.php';
        $this->api_endpoint_live     = 'https://gateway.acquired.com/api.php';
        $this->mode                  = $this->get_option( 'mode', 'sandbox' );
        $this->gateway_merchant_id   = $this->get_option( 'gateway_merchant_id', '' );
        $this->has_fields            = true;
        $this->tds_action            = $this->get_option( 'tds_action', 0 );
        $this->company_pass          = $this->get_option( 'company_pass', '' );
        $this->company_mid_id        = $this->get_option( 'company_mid_id', '' );
        $this->company_hashcode      = $this->get_option( 'company_hashcode', '' );
        $this->transaction_type      = $this->get_option( 'transaction_type', 'AUTH_CAPTURE' );
        $this->challenge_window_size = $this->get_option( 'challenge_window_size', 'WINDOWED_250X400' );
        $this->google_merchant_id    = $this->get_option( 'google_merchant_id', '' );
        update_option( 'google_pay_debug_mode', $this->debug_mode );

        $this->_helper = new WC_Acquired_Helper();

        $this->init_form_fields();
        $this->init_settings();

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array(
                $this,
                'process_admin_options',
            )
        );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_scripts' ) );
        add_action( 'woocommerce_after_cart_totals', array( $this, 'output_cart_fields' ) );
        $this->supports = array(
            'products',
        );
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'disable_google_checkout' ) );
        add_action( 'woocommerce_receipt_google', array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'acquired_gpay_checkout_return' ) );
        add_action( 'woocommerce_thankyou_google', array( $this, 'acquired_gpay_checkout_return' ) );

        /* Display button Google Pay before billing form */
        add_action( 'woocommerce_review_order_before_payment', array( $this, 'acquired_display_google_button' ), 10 );

        /* Void/Refund Google Pay */
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'googlepay_void_order' ), 10, 2 );
    }

    /**
     * Hide Google Pay if can not show at checkout
     *
     * @param mixed $available_gateways All available gateways.
     *
     * @return mixed
     */
    public function disable_google_checkout( $available_gateways ) {
        if ( ! $this->_helper->is_payment_section( $this, 'checkout' ) || $this->_helper->check_if_isset_subscription_product() ) {
            unset( $available_gateways['google'] );
        }

        return $available_gateways;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'                 => array(
                'title'       => __( 'Enable Google Pay', 'acquired-payment' ),
                'label'       => __( 'Enable Google Pay', 'acquired-payment' ),
                'type'        => 'checkbox',
                'description' => __( 'Accept Google Pay as a payment method.', 'acquired-payment' ),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'google_merchant_id'    => array(
                'title'       => __( 'Google Pay Merchant ID', 'acquired-payment' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __( 'Enter Google Pay Merchant ID.', 'acquired-payment' ),
            ),
            'payment_sections' => array(
                'type'     => 'multiselect',
                'title'    => __( 'Display the Google Pay button on', 'acquired-payment' ),
                'class'    => 'wc-enhanced-select',
                'options'  => array(
                    'product'  => __( 'Product Page', 'acquired-payment' ),
                    'cart'     => __( 'Cart Page', 'acquired-payment' ),
                    'checkout' => __( 'Checkout Page', 'acquired-payment' ),
                ),
                'default'  => array( 'product', 'cart', 'checkout' ),
                'desc_tip' => __( 'Select where you would like the Google Pay button to be displayed for a faster checkout process.', 'acquired-payment' ),
            ),
            'button_height'   => array(
                'title'       => __( 'Button Height', 'acquired-payment' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'class'       => 'gpay-button-option button-width',
                'default'     => 40,
                'description' => __( 'Enter in the height of the Google Pay button in pixels which will be displayed on your website.', 'acquired-payment' ),
            ),
            'button_color'            => array(
                'title'       => __( 'Button Colour', 'acquired-payment' ),
                'type'        => 'select',
                'desc_tip'    => true,
                'class'       => 'gpay-button-option button-color',
                'options'     => array(
                    'black' => __( 'Black', 'acquired-payment' ),
                    'white' => __( 'White', 'acquired-payment' ),
                ),
                'default'     => 'black',
                'description' => __( 'Which colour for the Apple Pay button would look best for your website?', 'acquired-payment' ),
            ),
            'button_type'             => array(
                'title'       => __( 'Button Type', 'acquired-payment' ),
                'type'        => 'select',
                'desc_tip'    => true,
                'class'       => 'gpay-button-option button-style',
                'options'     => array(
                    'book'      => __( 'Book with G-Pay', 'acquired-payment' ),
                    'buy'       => __( 'G-Pay | VISA •••••• 1111', 'acquired-payment' ),
                    'checkout'  => __( 'Checkout with G-Pay', 'acquired-payment' ),
                    'donate'    => __( 'Donate with G-Pay', 'acquired-payment' ),
                    'order'     => __( 'Order with G-Pay', 'acquired-payment' ),
                    'pay'       => __( 'Pay with G-Pay', 'acquired-payment' ),
                    'plain'     => __( 'Standard Button', 'acquired-payment' ),
                    'subscribe' => __( 'Subscribe with G-Pay', 'acquired-payment' ),
                ),
                'default'     => 'buy',
                'description' => __( 'This allows you to customise the style for all Google Pay buttons presented on your store.', 'acquired-payment' ),
            ),
        );
    }

    /**
     * Button checkout Google Pay
     *
     * @since 2.6.0
     */
    public function acquired_google_button() {
        if ( ( 'yes' === $this->enable_google ) && ( $this->_helper->is_payment_section( $this, 'checkout' ) ) ) {
            echo '<div class="wc-acquired-googlepay" style="display: none;">';
            echo '<div id="wc-acquired-googlepay-container"></div>';

            if ( $this->_helper->is_payment_section( $this, 'checkout' ) ) : ?>
                <p style="text-align: center; margin-top: 10px;"><?php echo __( ' - OR - ', 'acquired-payment' ) ?></p>
            <?php endif;

            $this->output_display_items( 'checkout' );
            echo '</div>';
        }
    }

    /**
     * Display button
     *
     * @since 2.6.0
     */
    public function acquired_display_google_button() {
        $this->acquired_google_button();
    }


    /**
     * Builds our payment fields area - including tokenization fields for logged
     * in users, and the actual payment fields.
     *
     * @since 2.6.0
     */
    public function payment_fields() {
        $this->acquired_google_button();
    }

    /**
     * Process Payment
     *
     * @param int $order_id Order ID.
     *
     * @return array
     * @throws Exception Exception $exception.
     */
    public function process_payment( $order_id ) {
        if ( ! $this->_helper->is_payment_section( $this, 'checkout' ) ) {
            wc_add_notice(
                'Google Pay is not available at checkout page. Please contact your merchant or try with another payment method.',
                'error'
            );

            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        } else {
            return array(
                'order_id' => $order_id,
                'payment_method' => $this->id,
                'result'   => 'success',
                'messages'   => '<div class="woocommerce-info acquired_'.$this->id.'-notice"><span>'.__( 'Acquired Payments: ', 'acquired-payment' ).' </span>'.__( 'Processing Order', 'acquired-payment' ).'</div>'
            );
        }
    }


    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     * a passed in amount.
     *
     * @param int        $order_id Order ID.
     * @param float|null $amount Refund amount.
     * @param string     $reason Refund reason.
     *
     * @return boolean True or false based on success, or a WP_Error object.
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        return false;
    }

    /**
     * Get all needed params
     *
     * @return array
     */
    public function get_localized_params() {
        return array(
            'id'                    => $this->id,
            'ajaxurl'               => admin_url( 'admin-ajax.php' ),
            'googlepay_process_nonce' => wp_create_nonce('googlepay_process_create_nonce'),
            'environment'           => $this->get_google_pay_environment(),
            'merchant_id'           => ! empty( $this->merchant_id ) ? $this->merchant_id : '01234567890123456789',
            'merchant_name'         => ! empty( $this->merchant_name ) ? $this->merchant_name : get_bloginfo( 'name' ),
            'gatewayMerchantId'     => $this->gateway_merchant_id,
            'google_merchant_id'    => ! empty( $this->google_merchant_id ) ? $this->google_merchant_id : '',
            'processing_country'    => WC()->countries ? WC()->countries->get_base_country() : wc_get_base_location()['country'],
            'button_color'          => $this->get_option( 'button_color', 'black' ),
            'button_type'           => $this->get_option( 'button_type', 'buy' ),
            'button_size_mode'      => 'fill',
            'total_price_label'     => __( 'Total', 'acquired-payment' ),
            'messages'              => array(
                'invalid_amount' => __( 'Please update you product quantity before using Google Pay.', 'acquired-payment' ),
            ),
            'page'                  => $this->wc_acquired_get_current_page(),
            'currency'              => get_woocommerce_currency(),
            'tdsAction'             => $this->tds_action,
            'button_height'         => $this->get_option( 'button_height', '40' ),
            'authentication_method' => $this->get_option( 'authentication_method', array( 'CRYPTOGRAM_3DS', 'PAN_ONLY' ) ),
        );
    }

    /**
     * Get current page
     *
     * @since 3.2.3
     * @retun string
     */
    public function wc_acquired_get_current_page() {
        global $wp;
        if ( is_product() ) {
            return 'product';
        }
        if ( is_cart() ) {
            return 'cart';
        }
        if ( is_checkout() ) {
            if ( ! empty( $wp->query_vars['order-pay'] ) ) {
                return 'order_pay';
            }

            return 'checkout';
        }
        if ( is_add_payment_method_page() ) {
            return 'add_payment_method';
        }

        return '';
    }

    /**
     * Get Google pay environment
     *
     * @return string
     */
    public function get_google_pay_environment() {
        return 'sandbox' == $this->mode ? 'TEST' : 'PRODUCTION';
    }

    /**
     * Outputs fields required by Apple Pay to render the payment wallet.
     *
     * @param string $page Page.
     * @param array  $data Data.
     */
    public function output_display_items( $page = 'checkout', $data = array() ) {
        global $wp;
        global $product;
        global $woocommerce;
        $order = null;

        $data  = wp_parse_args(
            $data,
            array(
                'items'            => ! empty( $this->get_display_items( $page ) ) ? $this->get_display_items( $page ) : array(),
                'shipping_options' => ! empty( $this->get_formatted_shipping_methods() ) ? $this->get_formatted_shipping_methods() : array(),
                'total'            => WC()->cart->total,
                'currency'         => get_woocommerce_currency(),
            )
        );
        if ( in_array( $page, array( 'checkout', 'cart' ) ) ) {
            if ( ! empty( $wp->query_vars['order-pay'] ) ) {
                $order                  = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
                $page                   = 'order_pay';
                $data['needs_shipping'] = false;
                $data['items']          = ! empty( $this->get_display_items( $page, $order ) ) ? $this->get_display_items( $page, $order ) : array();
                $data['total']          = $order->get_total();
                $data['currency']       = $order->get_currency();
            } else {
                $data['needs_shipping'] = WC()->cart->needs_shipping();
                $current_total          = $woocommerce->cart->total - WC()->cart->get_shipping_total();
                $data['total']          = $current_total;
                $fees = WC()->cart->get_fees();
                if ( !empty($fees) ) {
                    $data['fees'] = $fees;
                }
                if ( 'checkout' === $page && is_cart() ) {
                    $page = 'cart';
                } elseif ( is_add_payment_method_page() ) {
                    $page = 'add_payment_method';
                }
            }
        } elseif ( 'product' === $page ) {
            $data['needs_shipping'] = $product->needs_shipping();
            $data['product']        = array(
                'id'        => $product->get_id(),
                'price'     => $product->get_price(),
                'variation' => $product->is_type( 'variable' ),
            );
            $data['total']          = $product->get_price();
            echo '<input type="hidden" id="woocommerce-googlepay-gateway" value="' . $product->get_id() . '"/>';
        }
        $data = wp_json_encode( $data );
        $data = function_exists( 'wc_esc_json' ) ? wc_esc_json( $data ) : _wp_specialchars( $data, ENT_QUOTES, 'UTF-8', true );
        printf( '<input type="hidden" class="%1$s" data-gateway="%2$s"/>', "woocommerce_{$this->id}_gateway_data {$page}-page", $data );
    }

    /**
     * Returns a formatted array of items for display in the payment gateway's payment sheet.
     *
     * @param string $page Page.
     *
     * @param null   $order Order.
     *
     * @return mixed|void []
     */
    public function get_display_items( $page = 'checkout', $order = null ) {
        global $wp;
        $items = array();
        if ( in_array( $page, array( 'cart', 'checkout' ) ) ) {
            $items = $this->get_display_items_for_cart( WC()->cart );
        } elseif ( 'order_pay' === $page ) {
            $order = ! is_null( $order ) ? $order : wc_get_order( absint( $wp->query_vars['order-pay'] ) );
            $items = $this->get_display_items_for_order( $order );
        } elseif ( 'product' === $page ) {
            global $product;
            $items = array( $this->get_display_item_for_product( $product ) );
        }

        return $items;
    }

    /**
     * Get display items for cart
     *
     * @param WC_Cart $cart Cart instance.
     * @param array   $items Cart items.
     *
     * @return array
     */
    public function get_display_items_for_cart( $cart, $items = array() ) {
        $incl_tax = $this->_helper->wc_acquired_display_prices_including_tax();
        foreach ( $cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'];
            $qty     = $cart_item['quantity'];
            $label   = $qty > 1 ? sprintf( '%s X %s', $product->get_name(), $qty ) : $product->get_name();
            $price   = $incl_tax ? wc_get_price_including_tax( $product, array( 'qty' => $qty ) ) : wc_get_price_excluding_tax(
                $product,
                array( 'qty' => $qty )
            );
            $items[] = $this->get_display_item_for_cart( $price, $label, 'product', $cart_item, $cart );
        }
        if ( $cart->needs_shipping() ) {
            $price   = $incl_tax ? $cart->shipping_total + $cart->shipping_tax_total : $cart->shipping_total;
            $items[] = $this->get_display_item_for_cart( $price, __( 'Shipping', 'acquired-payment' ), 'shipping' );
        }
        foreach ( $cart->get_fees() as $fee ) {
            $price   = $incl_tax ? $fee->total + $fee->tax : $fee->total;
            $items[] = $this->get_display_item_for_cart( $price, $fee->name, 'fee', $fee, $cart );
        }
        if ( 0 < $cart->discount_cart ) {
            $price   = - 1 * abs( $incl_tax ? $cart->discount_cart + $cart->discount_cart_tax : $cart->discount_cart );
            $items[] = $this->get_display_item_for_cart( $price, __( 'Discount', 'acquired-payment' ), 'discount', $cart );
        }
        if ( ! $incl_tax && wc_tax_enabled() ) {
            $items[] = $this->get_display_item_for_cart( $cart->get_taxes_total(), __( 'Tax', 'acquired-payment' ), 'tax', $cart );
        }

        return $items;
    }

    /**
     * Get display item for cart
     *
     * @param float  $price Price.
     * @param string $label Label.
     * @param string $type Type.
     * @param mixed  ...$args Args.
     *
     * @return array
     */
    public function get_display_item_for_cart( $price, $label, $type, ...$args ) {
        switch ( $type ) {
            case 'tax':
                $type = 'TAX';
                break;
            default:
                $type = 'LINE_ITEM';
                break;
        }

        return array(
            'label' => $label,
            'type'  => $type,
            'price' => strval( round( $price, 2 ) ),
        );
    }

    /**
     * Get display items for order
     *
     * @param WC_Order $order Order instance.
     * @param array    $items Order items.
     *
     * @return array
     */
    protected function get_display_items_for_order( $order, $items = array() ) {
        foreach ( $order->get_items() as $item ) {
            $qty     = $item->get_quantity();
            $label   = $qty > 1 ? sprintf( '%s X %s', $item->get_name(), $qty ) : $item->get_name();
            $items[] = $this->get_display_item_for_order( $item->get_subtotal(), $label, $order, 'item', $item );
        }
        if ( 0 < $order->get_shipping_total() ) {
            $items[] = $this->get_display_item_for_order( $order->get_shipping_total(), __( 'Shipping', 'acquired-payment' ), $order, 'shipping' );
        }
        if ( 0 < $order->get_total_discount() ) {
            $items[] = $this->get_display_item_for_order(
                - 1 * $order->get_total_discount(),
                __( 'Discount', 'acquired-payment' ),
                $order,
                'discount'
            );
        }
        if ( 0 < $order->get_fees() ) {
            $fee_total = 0;
            foreach ( $order->get_fees() as $fee ) {
                $fee_total += $fee->get_total();
            }
            $items[] = $this->get_display_item_for_order( $fee_total, __( 'Fees', 'acquired-payment' ), $order, 'fee' );
        }
        if ( 0 < $order->get_total_tax() ) {
            $items[] = $this->get_display_item_for_order( $order->get_total_tax(), __( 'Tax', 'woocommerce' ), $order, 'tax' );
        }

        return $items;
    }

    /**
     * Get display item for order
     *
     * @param float    $price Item price.
     * @param string   $label Item label.
     * @param WC_Order $order Item in order.
     * @param string   $type Item type.
     * @param mixed    ...$args Item args.
     *
     * @return array
     */
    public function get_display_item_for_order( $price, $label, $order, $type, ...$args ) {
        switch ( $type ) {
            case 'tax':
                $type = 'TAX';
                break;
            default:
                $type = 'LINE_ITEM';
                break;
        }

        return array(
            'label' => $label,
            'type'  => $type,
            'price' => strval( round( $price, 2 ) ),
        );
    }

    /**
     * Get display item for product
     *
     * @param WC_Product $product Product instance.
     *
     * @return array
     */
    public function get_display_item_for_product( $product ) {
        return array(
            'label' => esc_attr( $product->get_name() ),
            'type'  => 'SUBTOTAL',
            'price' => strval( round( $product->get_price(), 2 ) ),
        );
    }

    /**
     * Get formatted shipping methods parent
     *
     * @param array $methods Shipping methods.
     *
     * @return array
     */
    public function get_formatted_shipping_methods_parent( $methods = array() ) {
        $methods        = array();
        $chosen_methods = array();
        $packages       = WC()->shipping()->get_packages();
        $incl_tax       = $this->_helper->wc_acquired_display_prices_including_tax();
        foreach ( WC()->session->get( 'chosen_shipping_methods', array() ) as $i => $id ) {
            $chosen_methods[] = $this->get_shipping_method_id( $id, $i );
        }
        foreach ( $packages as $i => $package ) {
            foreach ( $package['rates'] as $rate ) {
                $price     = $incl_tax ? $rate->cost + $rate->get_shipping_tax() : $rate->cost;
                $methods[] = $this->get_formatted_shipping_method( $price, $rate, $i, $package, $incl_tax );
            }
        }

        /**
         * Sort shipping methods so the selected method is first in the array.
         */
        usort(
            $methods,
            function ( $method ) use ( $chosen_methods ) {
                foreach ( $chosen_methods as $id ) {
                    if ( in_array( $id, $method, true ) ) {
                        return - 1;
                    }
                }

                return 1;
            }
        );

        return $methods;
    }

    /**
     * Get formatted shipping methods
     *
     * @param array $methods Shipping methods.
     *
     * @return array
     */
    public function get_formatted_shipping_methods( $methods = array() ) {
        $methods = $this->get_formatted_shipping_methods_parent( $methods );
        if ( empty( $methods ) ) {
            $methods[] = array(
                'id'          => 'default',
                'label'       => __( 'Default', 'acquired-payment' ),
                'description' => __( '0', 'acquired-payment' ),
            );
        }

        return $methods;
    }

    /**
     * Get formatted shipping method
     *
     * @param float            $price Shipping method price.
     * @param WC_Shipping_Rate $rate Shipping method rate.
     * @param string           $i Shipping method index.
     * @param array            $package Shipping method package.
     * @param bool             $incl_tax Shipping method tax.
     *
     * @return array
     */
    public function get_formatted_shipping_method( $price, $rate, $i, $package, $incl_tax ) {
        return array(
            'id'          => $this->get_shipping_method_id( $rate->id, $i ),
            'label'       => $this->get_formatted_shipping_label( $price, $rate, $incl_tax ),
            'description' => $price,
        );
    }

    /**
     * Get shipping method ID
     *
     * @param string $id Shipping method id.
     * @param string $index Shipping method index.
     *
     * @return mixed
     */
    public function get_shipping_method_id( $id, $index ) {
        return sprintf( '%s', $id );
    }

    /**
     * Get formatted shipping label
     *
     * @param float            $price Price.
     * @param WC_Shipping_Rate $rate Rate.
     * @param bool             $incl_tax Include tax.
     *
     * @return string
     */
    public function get_formatted_shipping_label( $price, $rate, $incl_tax ) {
        $label = sprintf( '%s: %s %s', esc_attr( $rate->get_label() ), number_format( $price, 2 ), get_woocommerce_currency() );
        if ( $incl_tax ) {
            if ( $rate->get_shipping_tax() > 0 && ! wc_prices_include_tax() ) {
                $label .= ' ' . WC()->countries->inc_tax_or_vat();
            }
        } else {
            if ( $rate->get_shipping_tax() > 0 && wc_prices_include_tax() ) {
                $label .= ' ' . WC()->countries->ex_tax_or_vat();
            }
        }

        return $label;
    }

    /**
     * Register script for GPay
     */
    public function enqueue_checkout_scripts() {
        if ( is_checkout() && 'yes' == $this->enable_google && $this->_helper->is_payment_section( $this, 'checkout' ) ) {
            wp_register_script(
                'googlepay-checkout',
                ACQUIRED_URL . '/assets/js/googlepay-checkout.js',
                array( 'jquery' ),
                ACQUIRED_VERSION,
                true
            );
            wp_localize_script(
                'googlepay-checkout',
                'googlepay_checkout',
                $this->get_localized_params()
            );
            wp_enqueue_script( 'googlepay-checkout' );
        }
    }

    /**
     * Show Google Pay button on cart page
     */
    public function output_cart_fields() {
        if ( 'yes' == $this->enable_google ) {
            if ( $this->_helper->is_payment_section( $this, 'cart' ) && ! $this->_helper->check_if_isset_subscription_product() ) {
                wp_localize_script(
                    'googlepay-cart',
                    'googlepay_cart',
                    $this->get_localized_params()
                );
                wp_enqueue_script( 'googlepay-cart' );

                $template_path = ACQUIRED_PATH . 'templates/';
                $default_path  = ACQUIRED_PATH . 'templates/';

                wc_get_template(
                    'cart/payment-methods.php',
                    array(
                        'gateways'            => $this->id,
                        'after'               => true,
                        'cart_total'          => WC()->cart->total,
                        'google_pay_instance' => $this,
                    ),
                    $template_path,
                    $default_path
                );

            }
        }
    }

    /**
     * Receipt Page
     *
     * @param mixed $order_id Order.
     *
     * @return string
     */
    public function receipt_page( $order_id ) {
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<p>' . __( 'Thank you for your order. Please click button below to Authenticate your card.' ) . '</p>';
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        $acsurl             = WC()->session->get( 'acquired_gpay_url' );
        $element            = '<form id="google_3d_form" action="' . $acsurl . '" method="post"></form>';
        $gpay_response_code = get_post_meta( $order_id, 'acquired_gpay_response_code', true );

        if ( 503 == $gpay_response_code ) {
            $creq   = WC()->session->get( 'acquired_gpay_creq' );
            $script = "( function( $ ) {
                        var element = '$element';
                            form = $(element);
                        $('body').append(form);
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'creq',
                            value: '$creq'
                        }).appendTo('#google_3d_form');
                        form.submit();
                    })( jQuery );";

            WC()->session->set( 'acquired_gpay_creq', null );
        } else {
            $pareq   = WC()->session->get( 'acquired_gpay_pareq' );
            $md      = WC()->session->get( 'acquired_gpay_md' );
            $term_url = WC()->session->get( 'acquired_gpay_term_url' );
            $script  = "( function( $ ) {
                        var element = '$element';
                            form = $(element);
                        $('body').append(form);
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'PaReq',
                            value: '$pareq'
                        }).appendTo('#google_3d_form');
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'TermUrl',
                            value: '$term_url'
                        }).appendTo('#google_3d_form');
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'MD',
                            value: '$md'
                        }).appendTo('#google_3d_form');
                        form.submit();
                    })( jQuery );";

            WC()->session->set( 'acquired_gpay_pareq', null );
        }
        wc_enqueue_js( $script );

        return $element;
    }

    /**
     * Google pay checkout return
     *
     * @param mixed $order_id Order.
     *
     * @return void
     */
    public function acquired_gpay_checkout_return( $order_id ) {
        if ( isset( $_REQUEST['PaRes'] ) || isset( $_REQUEST['cres'] ) ) {
            if ( ! empty( $_REQUEST['MD'] ) ) {
                $order = wc_get_order( $_REQUEST['MD'] );
            } else {
                $order = wc_get_order( $_GET['order_id'] );
            }

            if ( ! empty( $order->get_payment_method() ) && $order->get_payment_method() == $this->id ) {
                $threeds_data        = ! empty( $_REQUEST['PaRes'] ) ? $_REQUEST['PaRes'] : $_REQUEST['cres'];
                $settlement_response = $this->_helper->settlement_gpay_request( $this, $order, $threeds_data );

                if ( 1 == $settlement_response['response_code'] ) {
                    // Remove cart.
                    $message = sprintf(
                    /* translators: %s: transaction */                        __( 'Google Pay (Acquired Payments) settlement successfully %1$s (Transaction ID: %2$s)', 'acquired-payment' ),
                        wc_price( $order->get_total() ),
                        $settlement_response['transaction_id']
                    );
                    $order->add_order_note( $message );
                    $order->payment_complete( $order->get_transaction_id() );
                    WC()->cart->empty_cart();
                } else {
                    if ( ! empty( $settlement_response['response_code'] ) ) {
                        $order->add_order_note( $settlement_response['response_code'] . ' ' . $settlement_response['response_message'] );
                    } else {
                        $order->add_order_note( 'SCA response is empty.' );
                    }

                    $order->update_status( 'failed' );
                }
            }

            wp_redirect( $this->get_return_url( $order ), 301 );
        }
    }

    /**
     * Void the Google Pay charge
     *
     * @param int      $order_id Order $order_id.
     * @param WC_Order $order Order $order.
     *
     * @return WP_Error|bool
     * @throws Exception Exception $exception.
     */
    public function googlepay_void_order( $order_id, $order ) {
        $payment_method = $order->get_payment_method();
        if ( $payment_method !== $this->id ) {
            return;
        }

        if ( empty( $order ) ) {
            $this->_helper->acquired_logs( 'Your order is empty.', 'acquired-payment', $this->debug_mode );

            return new WP_Error( 'error', __( 'Your order is empty.', 'acquired-payment' ) );
        }

        $transaction_id        = get_post_meta( $order_id, '_transaction_id', true );
        if ( empty($transaction_id) ) {
            $transaction_id    = $order->get_transaction_id();
        }

        if ( empty( $transaction_id ) ) {
            throw new Exception( __( 'Can not void order.', 'acquired-payment' ) );
        }

        $transaction_infor = array(
            'transaction_type'        => 'VOID',
            'original_transaction_id' => $transaction_id,
        );
        $request_array     = array(
            'timestamp'    => date('YmdHis'),
            'company_id'   => $this->merchant_id,
            'company_pass' => $this->company_pass,
            'transaction'  => $transaction_infor,
        );

        $request_hash = $this->_helper->request_hash(
            array_merge( $transaction_infor, $request_array ),
            $this->company_hashcode
        );
        $request_array['request_hash'] = $request_hash;

        $path = 'sandbox' == $this->mode ? $this->api_endpoint_sandbox : $this->api_endpoint_live;

        $this->_helper->acquired_logs( 'acquired_void_order for google payment data', $this->debug_mode );
        $this->_helper->acquired_logs( $request_array, $this->debug_mode );
        $cancel_response = $this->_helper->acquired_api_request( $path, 'POST', $request_array );
        $this->_helper->acquired_logs( 'acquired_void_order for google payment data', $this->debug_mode );
        $this->_helper->acquired_logs( $cancel_response, $this->debug_mode );

        if ( is_array( $cancel_response ) && isset( $cancel_response['response_code'] ) ) {
            if ( '1' == $cancel_response['response_code'] ) {
                $message = __(
                    'Acquired Payments void order successfully (Transaction ID: ' . $cancel_response['transaction_id'] . ')',
                    'acquired-payment'
                );
                $transaction_infor['transaction_id'] = $cancel_response['transaction_id'];
                $request_array['order_id']           = $order_id;
                $order->add_order_note( $message );

                return true;
            } else {
                $message = __(
                        'Code ',
                        'acquired-payment'
                    ) . $cancel_response['response_code'] . ' - ' . $cancel_response['response_message'];
                $order->add_order_note( $message );
                $order->update_status('processing');

                return false;
            }
        }

        $message = sprintf( __( 'Acquired Payments void payment fail (Transaction ID: %s)', 'acquired-payment' ), $transaction_id );
        $order->add_order_note( $message );

        return false;
    }
}
