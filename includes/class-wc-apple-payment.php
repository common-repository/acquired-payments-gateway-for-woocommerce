<?php
/**
 * WC Apple Pay
 *
 * @package acquired-payment/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides a Apple Pay Payment.
 *
 * @property WC_Acquired_Helper _helper
 * @class  WC_Apple_Payment
 */
class WC_Apple_Payment extends WC_Payment_Gateway {
    /**
     * Enabled Apple
     *
     * @var string $enable_apple
     */
    public $enable_apple;

    /**
     * Domain association url
     *
     * @var string $domain_association_url
     */
    public $domain_association_url;

    /**
     * API Info
     *
     * @var string $merchant_id
     */
    public $merchant_id;

    /**
     * Debug mode
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
     * @var $sdk_endpoint_sandbox
     */
    public $sdk_endpoint_sandbox;

    /**
     * API Info
     *
     * @var $sdk_endpoint_live
     */
    public $sdk_endpoint_live;

    /**
     * API Info
     *
     * @var $charge_type
     */
    public $charge_type;

    /**
     * API Info
     *
     * @var $mode
     */
    public $mode;

    /**
     * WC_Acquired_Payment_Abstract constructor.
     */
    public function __construct() {
        $this->id                     = 'apple';
        $this->title                  = $this->get_option( 'title', __( 'Apple Pay', 'acquired-payment' ) );
        $this->method_title           = __( 'Apple Pay', 'acquired-payment' );
        $this->method_description     = $this->get_option(
            'description',
            __( 'Express checkout with Apple Pay.', 'acquired-payment' )
        );
        $this->enable_apple           = $this->get_option( 'enabled', 0 );
        $this->domain_association_url = $this->get_option( 'domain_association_url', '' );
        $this->debug_mode             = $this->get_option( 'debug_mode', 1 );
        $this->api_endpoint_sandbox   = 'https://qaapi.acquired.com/api.php';
        $this->api_endpoint_live      = 'https://gateway.acquired.com/api.php';
        $this->sdk_endpoint_sandbox   = 'https://qaapi.acquired.com/api.php/sdk';
        $this->sdk_endpoint_live      = 'https://gateway.acquired.com/api.php/sdk';
        $this->has_fields             = true;
        $this->merchant_id            = $this->get_option( 'merchant_id', 'merchant.com.acquired.test' );
        $this->company_pass           = $this->get_option( 'company_pass', '' );
        $this->company_mid_id         = $this->get_option( 'company_mid_id', '' );
        $this->company_hashcode       = $this->get_option( 'company_hashcode', '' );
        $this->transaction_type       = $this->get_option( 'charge_type', 'AUTH_CAPTURE' );
        $this->mode                   = $this->get_option( 'mode', 'sandbox' );
        update_option( 'apple_pay_debug_mode', $this->debug_mode );

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
        $this->supports = array(
            'products',
        );
        add_action( 'woocommerce_after_cart_totals', array( $this, 'output_cart_fields' ) );
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'disable_apple_checkout' ) );

        /* Display button Apple Pay before billing form */
        add_action( 'woocommerce_review_order_before_payment', array( $this, 'acquired_display_apple_button' ), 20 );

        /* Notification in page checkout if payment declined */
        add_action( 'woocommerce_before_checkout_form', array( $this, 'notification_status_payment_checkout' ) );

        /* Void/Refund Apple Pay */
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'applepay_void_order' ), 10, 2 );
    }

    /**
     * Hide Apple Pay if can not show at checkout
     *
     * @param mixed $available_gateways All gateways available.
     *
     * @return mixed
     * @throws Exception Exception $exception.
     */
    public function disable_apple_checkout( $available_gateways ) {
        if ( ! $this->_helper->is_payment_section( $this, 'checkout' ) || $this->_helper->check_if_isset_subscription_product()
            || ! $this->check_apple_sdk() ) {
            unset( $available_gateways['apple'] );
        }

        return $available_gateways;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'          => array(
                'title'       => __( 'Enable Apple Pay', 'acquired-payment' ),
                'label'       => __( 'Enable Apple Pay', 'acquired-payment' ),
                'type'        => 'checkbox',
                'description' => __( 'Accept Apple Pay as a payment method.', 'acquired-payment' ),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'payment_sections' => array(
                'type'     => 'multiselect',
                'title'    => __( 'Display the Apple Pay button on', 'acquired-payment' ),
                'class'    => 'wc-enhanced-select',
                'options'  => array(
                    'product'  => __( 'Product Page', 'acquired-payment' ),
                    'cart'     => __( 'Cart Page', 'acquired-payment' ),
                    'checkout' => __( 'Checkout Page', 'acquired-payment' ),
                ),
                'default'  => array( 'product', 'cart', 'checkout' ),
                'desc_tip' => __( 'Select where you would like the Apple Pay button to be displayed for a faster checkout process.', 'acquired-payment' ),
            ),
            'button_height'    => array(
                'title'    => __( 'Button Height', 'acquired-payment' ),
                'type'     => 'text',
                'class'    => 'apple-button-option button-width',
                'default'  => 30,
                'desc_tip' => __( 'Enter in the height of the Apple Pay button in pixels which will be displayed on your website.', 'acquired-payment' ),
            ),
            'button_style'     => array(
                'type'     => 'select',
                'title'    => __( 'Button Colour', 'acquired-payment' ),
                'class'    => 'wc-enhanced-select',
                'default'  => 'apple-pay-button-black',
                'options'  => array(
                    'black'         => __( 'Black', 'acquired-payment' ),
                    'white-outline' => __( 'White with outline', 'acquired-payment' ),
                    'white'         => __( 'White', 'acquired-payment' ),
                ),
                'desc_tip' => __( 'Which colour for the Apple Pay button would look best for your website?', 'acquired-payment' ),
            ),
            'button_type'      => array(
                'title'    => __( 'Button Type', 'acquired-payment' ),
                'type'     => 'select',
                'options'  => array(
                    'book'       => __( 'Book with Apple Pay', 'acquired-payment' ),
                    'buy'        => __( 'Buy with Apple Pay', 'acquired-payment' ),
                    'check-out'  => __( 'Checkout with Apple Pay', 'acquired-payment' ),
                    'donate'     => __( 'Donate with Apple Pay', 'acquired-payment' ),
                    'order'      => __( 'Order with Apple Pay', 'acquired-payment' ),
                    'pay'        => __( 'Pay with Apple Pay', 'acquired-payment' ),
                    'plain'      => __( 'Standard Button', 'acquired-payment' ),
                    'subscribe'  => __( 'Subscribe with Apple Pay', 'acquired-payment' ),
                    'add-money'  => __( 'Add money with Apple Pay', 'acquired-payment' ),
                    'contribute' => __( 'Contribute with Apple Pay', 'acquired-payment' ),
                    'continue'   => __( 'Continue with Apple Pay', 'acquired-payment' ),
                    'rent'       => __( 'Rent with Apple Pay', 'acquired-payment' ),
                    'reload'     => __( 'Reload with Apple Pay', 'acquired-payment' ),
                    'set-up'     => __( 'Set up with Apple Pay', 'acquired-payment' ),
                    'support'    => __( 'Support with Apple Pay', 'acquired-payment' ),
                    'tip'        => __( 'Tip with Apple Pay', 'acquired-payment' ),
                    'top-up'     => __( 'Top up with Apple Pay', 'acquired-payment' ),
                ),
                'default'  => 'plain',
                'desc_tip' => __( 'Dictate the text that is displayed within the Apple Pay button.', 'acquired-payment' ),
            ),
        );
    }

    /**
     * Register script for Apple Pay
     */
    public function enqueue_checkout_scripts() {
        if ( is_checkout() && 'yes' == $this->enable_apple && $this->_helper->is_payment_section( $this, 'checkout' ) ) {
            wp_register_script(
                'applepay-checkout',
                ACQUIRED_URL . '/assets/js/applepay-checkout.js',
                array( 'jquery' ),
                ACQUIRED_VERSION,
                true
            );
            wp_localize_script(
                'applepay-checkout',
                'applepay_checkout',
                $this->get_localized_params()
            );
            wp_enqueue_script( 'applepay-checkout' );
        }
    }

    /**
     * Button checkout Apple payment
     *
     * @since 2.6.0
     */
    public function acquired_apple_button() {
        if ( $this->_helper->is_payment_section( $this, 'checkout' ) ) {
            echo '<div class="wc-acquired-applepay">';
            echo '<div id="wc-acquired-applepay-container"></div>';
            echo '<input type="hidden" name="applepay_token_response" id="applepay_token_response"/>'
                . '<input type="hidden" name="paymentDisplayName" id="paymentDisplayName"/>'
                . '<input type="hidden" name="paymentNetwork" id="paymentNetwork"/>';
            echo '<div class="button-container" id="button-container">';
            echo '</div>';
            ?>
            <style>
                apple-pay-button {
                    --apple-pay-button-height: <?php echo $this->get_option( 'button_height' ); ?>px;
                    --apple-pay-button-border-radius: 3px;
                    --apple-pay-button-padding: 0px 0px;
                    --apple-pay-button-box-sizing: border-box;
                }
            </style>
            <apple-pay-button buttonstyle="<?php echo $this->get_option( 'button_style' ); ?>" type="<?php echo $this->get_option( 'button_type' ); ?>" locale="en"></apple-pay-button>
            <p style="text-align: center;"><?php echo __( ' - OR - ', 'acquired-payment' ) ?></p>
            <?php
            $this->output_display_items( 'checkout' );
            echo '</div>';
        } else {
            echo '<p>'.__( 'Apple Pay is not available at checkout page. Please contact your merchant', 'acquired-payment' ).'</p>';
        }
    }

    /**
     * Display button
     *
     * @since 2.6.0
     */
    public function acquired_display_apple_button() {
        $this->acquired_apple_button();
    }

    /**
     * Builds our payment fields area - including tokenization fields for logged
     * in users, and the actual payment fields.
     *
     * @since 2.6.0
     */
    public function payment_fields() {
        // $this->acquired_apple_button();
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
                'Apple Pay is not available at checkout page. Please contact your merchant or try with another payment method.',
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
     * Notification in page checkout if payment declined
     *
     */
    public function notification_status_payment_checkout() {
        if ( isset( $_GET['order-id-declined'] )  ) {
            $order_id = $_GET['order-id-declined'];
            $order    = wc_get_order( $order_id );
            $status_payment_apple = get_post_meta( $order_id, 'order_declined', true );
            if ( $order->get_status() == 'failed' && $status_payment_apple == 1 ) {
                $message = __( 'Your payment was declined, please try again.', 'acquired-payment' );
                wc_print_notice( $message, 'error' );
            }
        }
    }

    /**
     * Process refund
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
     * @throws Exception Exception $exception.
     */
    public function get_localized_params() {
        return array(
            'id'                 => $this->id,
            'ajaxurl'            => admin_url( 'admin-ajax.php' ),
            'applepay_process_nonce' => wp_create_nonce('applepay_process_create_nonce'),
            'environment'        => $this->get_apple_pay_environment(),
            'gatewayMerchantId'  => $this->merchant_id,
            'processing_country' => WC()->countries ? WC()->countries->get_base_country() : strtoupper(wc_get_base_location()['country']),
            'messages'           => array(
                'invalid_amount' => __( 'Please update you product quantity before using Apple Pay.', 'acquired-payment' ),
                'choose_product' => __( 'Please select a product option before updating quantity.', 'acquired-payment' ),
                'terms'          => __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce' ),
                'required_field' => __( 'Please fill out all required fields.', 'acquired-payment' ),
            ),
            'button_style'       => $this->get_option( 'button_style' ),
            'button_type'        => $this->get_option( 'button_type' ),
            'gateway_id'         => $this->id,
            'currency'           => get_woocommerce_currency(),
            'total_label'        => __( 'Total', 'acquired-payment' ),
            'country_code'       => strtoupper(wc_get_base_location()['country']),
            'user_id'            => get_current_user_id(),
            'description'        => $this->get_description(),
            'page'               => $this->wc_acquired_get_current_page(),
            'button_height'      => $this->get_option( 'button_height', '30' ),
            'required_billing'   => $this->get_apple_required_billing(),
            'required_shipping'  => $this->get_apple_required_shipping(),
        );
    }

    /**
     * Get current page
     *
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
     * Get Apple pay environment
     *
     * @return string
     */
    public function get_apple_pay_environment() {
        return 'sandbox' == $this->mode ? 'TEST' : 'PRODUCTION';
    }

    /**
     * Returns the Apple Pay button type based on the current page.
     *
     * @return string
     */
    protected function get_button_type() {
        if ( is_checkout() ) {
            return $this->get_option( 'button_type_checkout' );
        }
        if ( is_cart() ) {
            return $this->get_option( 'button_type_cart' );
        }
        if ( is_product() ) {
            return $this->get_option( 'button_type_product' );
        }
    }

    /**
     * Woocommerce get template html
     *
     * @param mixed $template_name Template name.
     * @param array $args Args.
     *
     * @return string
     */
    public function wc_get_template_html( $template_name, $args = array() ) {
        $template_path = ACQUIRED_PATH . 'templates/';
        $default_path  = ACQUIRED_PATH . 'templates/';

        return wc_get_template_html( $template_name, $args, $template_path, $default_path );
    }

    /**
     * Show Apple Pay button on cart page
     *
     * @throws Exception Exception $exception.
     */
    public function output_cart_fields() {
        if ( 'yes' == $this->enable_apple && $this->check_apple_sdk() ) {
            if ( $this->_helper->is_payment_section( $this, 'cart' ) && ! $this->_helper->check_if_isset_subscription_product() ) {
                wp_localize_script(
                    'applepay-cart',
                    'applepay_cart',
                    $this->get_localized_params()
                );
                wp_enqueue_script( 'applepay-cart' );

                $template_path = ACQUIRED_PATH . 'templates/';
                $default_path  = ACQUIRED_PATH . 'templates/';

                wc_get_template(
                    'cart/applepay-methods.php',
                    array(
                        'gateways'           => $this->id,
                        'after'              => true,
                        'cart_total'         => WC()->cart->total,
                        'apple_pay_instance' => $this,
                    ),
                    $template_path,
                    $default_path
                );
            }
        }
    }

    /**
     * Check if Apple Pay enabled on Acquired
     *
     * @throws Exception Exception $exception.
     */
    public function check_apple_sdk() {
        /*
          $sdk_response = $this->_helper->get_apple_sdk_config( $this );

        if ( $sdk_response['payment_methods']['apple_pay']['is_active'] == true ) {
        return true;
        }
        */
        return true;
    }

    /**
     * Outputs fields required by Apple Pay to render the payment wallet.
     *
     * @param string $page Page.
     * @param array  $data Data.
     */
    public function output_display_items( $page = 'checkout', $data = array() ) {
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        global $wp;
        global $product;
        global $woocommerce;
        $order = null;

        $data  = wp_parse_args(
            $data,
            array(
                'items'            => ! empty( $this->get_display_items( $page ) ) ? $this->get_display_items( $page ) : array(),
                'shipping_options' => ! empty( $this->get_formatted_shipping_methods() ) ? $this->get_formatted_shipping_methods() : array(),
                'total'            => WC()->cart->get_cart_contents_total(),
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
            echo '<input type="hidden" id="woocommerce-applepay-gateway" value="' . $product->get_id() . '"/>';
        }
        $country_code = ( new WC_Countries() )->get_base_country();
        $data['country'] = strtoupper($country_code);
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
            'label'  => $label,
            'type'   => $type,
            'amount' => strval( round( $price, 2 ) ),
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
            'label'  => $label,
            'type'   => $type,
            'amount' => strval( round( $price, 2 ) ),
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
            'label'  => esc_attr( $product->get_name() ),
            'type'   => 'final',
            'amount' => strval( round( $product->get_price(), 2 ) ),
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
                'identifier' => 'default',
                'label'      => __( 'Default', 'acquired-payment' ),
                'detail'     => __( '0.00', 'acquired-payment' ),
                'amount'     => __( '0.00', 'acquired-payment' ),
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
            'identifier' => $this->get_shipping_method_id( $rate->id, $i ),
            'label'      => $this->get_formatted_shipping_label( $price, $rate, $incl_tax ),
            'detail'     => $price,
            'amount'     => $price,
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
     * Get Apple required fields from API
     *
     * @throws Exception Exception $exception.
     */
    public function get_apple_required_billing() {
        $sdk_response = $this->_helper->get_apple_sdk_config( $this );

        return $sdk_response['payment_methods']['apple_pay']['required_billing'];
    }

    /**
     * Get Apple required fields from API
     *
     * @throws Exception Exception $exception.
     */
    public function get_apple_required_shipping() {
        $sdk_response = $this->_helper->get_apple_sdk_config( $this );

        return $sdk_response['payment_methods']['apple_pay']['required_shipping'];
    }

    /**
     * Void the Apple Pay charge
     *
     * @param int      $order_id Order $order_id.
     * @param WC_Order $order Order $order.
     *
     * @return WP_Error|bool
     * @throws Exception Exception $exception.
     */
    public function applepay_void_order( $order_id, $order ) {
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

        $this->_helper->acquired_logs( 'acquired_void_order for apple payment data', $this->debug_mode );
        $this->_helper->acquired_logs( $request_array, $this->debug_mode );
        $cancel_response = $this->_helper->acquired_api_request( $path, 'POST', $request_array );
        $this->_helper->acquired_logs( 'acquired_void_order for apple payment data', $this->debug_mode );
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
