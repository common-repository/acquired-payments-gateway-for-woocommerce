<?php
/**
 * WC Acquired Payment
 *
 * @package acquired-payment/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides a Acquired Payment Gateway.
 *
 * @class  WC_Acquired_Payment_Abstract
 */
class WC_Acquired_Payment extends WC_Payment_Gateway {
    /**
     * Version
     *
     * @var string $version
     */
    public $version;

    /**
     * 3DS
     *
     * @var string $three_d_secure
     */
    public $three_d_secure;

    /**
     * Save cards
     *
     * @var string $enable_saved_cards
     */
    public $enable_saved_cards;

    /**
     * Mode
     *
     * @var string $mode
     */
    public $mode;

    /**
     * Number of save card
     *
     * @var string $number_of_saved_card
     */
    public $number_of_saved_card;

    /**
     * API Param
     *
     * @var string $company_id
     */
    public $company_id;

    /**
     * API Param
     *
     * @var string $company_mid_id
     */
    public $company_mid_id;

    /**
     * API Param
     *
     * @var string $company_hashcode
     */
    public $company_hashcode;

    /**
     * API Param
     *
     * @var string $company_pass
     */
    public $company_pass;

    /**
     * Iframe option
     *
     * @var string $iframe_option
     */
    public $iframe_option;

    /**
     * Helper instance
     *
     * @var string $_helper
     */
    public $_helper;

    /**
     * API Param
     *
     * @var string $transaction_type
     */
    public $transaction_type;

    /**
     * API Param
     *
     * @var string $currency_code_iso3
     */
    public $currency_code_iso3;

    /**
     * API Param
     *
     * @var string $payment_link_endpoint_sandbox
     */
    public $payment_link_endpoint_sandbox;

    /**
     * API Param
     *
     * @var string $payment_link_endpoint_live
     */
    public $payment_link_endpoint_live;

    /**
     * API Param
     *
     * @var string $template_id
     */
    public $template_id;

    /**
     * API Param
     *
     * @var string $load_balance
     */
    public $load_balance;

    /**
     * Debug mode
     *
     * @var string $debug_mode
     */
    public $debug_mode;

    /**
     * API Param
     *
     * @var string $api_endpoint_sandbox
     */
    public $api_endpoint_sandbox;

    /**
     * API Param
     *
     * @var string $payment_link_endpoint_live
     */
    public $api_endpoint_live;

    /**
     * API Param
     *
     * @var string $merchant_contact_url
     */
    public $merchant_contact_url;

    /**
     * API Param
     *
     * @var string $tds_preference
     */
    public $tds_preference;

    /**
     * API Param
     *
     * @var string $expiry_time
     */
    public $expiry_time;

    /**
     * API Param
     *
     * @var string $retry_attempts
     */
    public $retry_attempts;

    /**
     * API Param
     *
     * @var string $saved_cards
     */
    public $saved_cards;

    /**
     * API Param
     *
     * @var string $reuse_template_id
     */
    public $reuse_template_id;

    /**
     * WC_Acquired_Payment_Abstract constructor.
     */
    public function __construct() {
        $this->version              = ACQUIRED_VERSION;
        $this->id                   = 'acquired';
        $this->title                = $this->get_option( 'title', __( 'Acquired Card Payments', 'acquired-payment' ) );
        $this->method_title         = __( 'Acquired Card Payments', 'acquired-payment' );
        $this->method_description   = $this->get_option(
            'description',
            __(
                'Checkout using your credit or debit card.',
                'acquired-payment'
            )
        );
        $this->icon                 = $this->get_icon();
        $this->has_fields           = true;
        $this->three_d_secure       = $this->get_option( 'three_d_secure', 0 );
        $this->saved_cards          = $this->get_option( 'saved_cards', 0 );
        $this->mode                 = $this->get_option( 'mode', 'sandbox' );
        $this->number_of_saved_card = $this->get_option( 'number_of_saved_card', 3 );
        $this->merchant_contact_url = $this->get_option( 'merchant_contact_url', '' );
        $this->tds_preference       = $this->get_option( 'tds_preference', '0' );
        $this->company_id           = $this->get_option( 'company_id', '' );
        $this->company_mid_id       = $this->get_option( 'company_mid_id', '' );
        $this->company_hashcode     = $this->get_option( 'company_hashcode', '' );
        $this->company_pass         = $this->get_option( 'company_pass', '' );
        $this->iframe_option        = $this->get_option( 'iframe_option', 'redirect' );
        $this->transaction_type     = $this->get_option( 'transaction_type', 'AUTH_CAPTURE' );
        $this->template_id          = $this->get_option( 'template_id', '' );
        $this->load_balance         = $this->get_option( 'load_balance', 1 );
        $this->debug_mode           = $this->get_option( 'debug_mode', 1 );
        $this->expiry_time          = $this->get_option( 'expiry_time', 86400 );
        $this->retry_attempts       = $this->get_option( 'retry_attempts', 3 );
        $this->reuse_template_id    = $this->get_option( 'reuse_template_id', '' );
        update_option( 'acquired_debug_mode', $this->debug_mode );
        $this->currency_code_iso3            = get_woocommerce_currency();
        $this->payment_link_endpoint_sandbox = 'https://qahpp.acquired.com/link';
        $this->payment_link_endpoint_live    = 'https://hpp.acquired.com/link';
        $this->api_endpoint_sandbox          = 'https://qaapi.acquired.com/api.php';
        $this->api_endpoint_live             = 'https://gateway.acquired.com/api.php';

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
        add_action( 'woocommerce_before_thankyou', array( $this, 'acquired_checkout_return' ) );
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'acquired_void_order' ), 10, 2 );
        add_action( 'woocommerce_order_status_changed', array( $this, 'acquired_capture_order' ), 10, 3 );
        add_action( 'woocommerce_order_status_changed', array( $this, 'acquired_refund_order' ), 10, 3 );
        add_action( 'woocommerce_pay_order_before_submit', array( $this, 'add_order_information' ) );

        $this->supports = array(
            'products',
            'refunds',
            'tokenization',
            'add_payment_method',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'multiple_subscriptions',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
        );

        /* 3DS Secure */
        $woocommerce_acquired_settings = get_option('woocommerce_acquired_settings');
        $three_d_secure = get_option('woocommerce_acquired_settings')['three_d_secure'];
        if ( 'yes' === $three_d_secure ) {
            $woocommerce_acquired_settings['three_d_secure'] = '2';
        } elseif ( 'no' === $three_d_secure ) {
            $woocommerce_acquired_settings['three_d_secure'] = '0';
        }

        /* Save card */
        $saved_cards = get_option('woocommerce_acquired_settings')['saved_cards'];
        if ( 'yes' === $saved_cards ) {
            $woocommerce_acquired_settings['saved_cards'] = '1';
        } elseif ( 'no' === $saved_cards ) {
            $woocommerce_acquired_settings['saved_cards'] = '0';
        }

        /* Update option */
        update_option( 'woocommerce_acquired_settings', $woocommerce_acquired_settings );
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'              => array(
                'title'       => __( 'Enable Card Payments', 'acquired-payment' ),
                'label'       => __( 'Enable Card Payments', 'acquired-payment' ),
                'type'        => 'checkbox',
                'description' => __( 'Accept Visa/Mastercard payments.', 'acquired-payment' ),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'text_title_medthod' => array(
                'title'       => __( 'Card Payments', 'acquired-payment' ),
                'type'        => 'hidden',
            ),
            'title'                => array(
                'title'       => __( 'Checkout Title', 'acquired-payment' ),
                'type'        => 'text',
                'description' => __( 'Controls the title which the user sees during the checkout, for example, "Pay by Card".', 'acquired-payment' ),
                'default'     => __( 'Acquired Card Payments', 'acquired-payment' ),
                'desc_tip'    => true,
            ),
            'description'          => array(
                'title'       => __( 'Description', 'acquired-payment' ),
                'type'        => 'text',
                'description' => __( 'Controls the text which the user sees during the checkout process, for example, “You will be asked to securely enter your card details on the next page after selecting Place Order.', 'acquired-payment' ),
                'default'     => __(
                    'Checkout using your credit or debit card.',
                    'acquired-payment'
                ),
                'desc_tip'    => true,
            ),
            'iframe_option'        => array(
                'title'    => __( 'Display Payment Form', 'acquired-payment' ),
                'desc_tip' => __( 'This option controls how you would like the payment details form to be presented to the consumer.', 'acquired-payment' ),
                'type'     => 'select',
                'default'  => $this->iframe_option,
                'options'  => array(
                    'redirect'       => __( 'Redirect', 'acquired-payment' ),
                    'popup'          => __( 'iFrame Popup', 'acquired-payment' ),
//                    'popup_redirect' => __( 'iFrame Redirect', 'acquired-payment' ),
                ),
            ),
            'template_id'          => array(
                'title'       => __( 'Template ID', 'acquired-payment' ),
                'type'        => 'text',
                'description' => __(
                    'Retrieve directly from your HPP configuration within the Acquired.com Hub.',
                    'acquired-payment'
                ),
                'desc_tip'    => true,
                'default'     => $this->template_id,
            ),
            'text_title_secure' => array(
                'title'       => __( '3D-Secure', 'acquired-payment' ),
                'type'        => 'hidden',
            ),
            'three_d_secure'              => array(
                'title'    => __( '3D-Secure', 'acquired-payment' ),
                'label'    => __( 'Require 3D Secure when applicable', 'acquired-payment' ),
                'type'        => 'checkbox',
                'description' => __( 'Enable 3DS to ensure your transactions are protected.', 'acquired-payment' ),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'merchant_contact_url' => array(
                'title'    => __( 'Contact us link', 'acquired-payment' ),
                'label'    => __( 'Contact us link', 'acquired-payment' ),
                'type'     => 'text',
                'default'  => $this->merchant_contact_url,
                'desc_tip' => __( 'Insert a link to your Contact Us page.', 'acquired-payment' ),
            ),
            'text_title_save_card' => array(
                'title'       => __( 'Save Cards', 'acquired-payment' ),
                'type'        => 'hidden',
            ),
            'desc_1_save_card' => array(
                'title'       => __( 'Enable express checkout by allowing your customers to store their credit/debit cards details.', 'acquired-payment' ),
                'type'        => 'hidden',
            ),
            'desc_2_save_card' => array(
                'title'       => __( 'On their next purchase, they will only need to input their CVV.', 'acquired-payment' ),
                'type'        => 'hidden',
            ),
            'desc_3_save_card' => array(
                'title'       => __( 'More information on how this works <a target="_blank" href="https://developer.acquired.com/cardstorage">here</a>', 'acquired-payment' ),
                'type'        => 'hidden',
            ),
            'saved_cards'          => array(
                'title'    => __( 'Save Cards', 'acquired-payment' ),
                'label'    => __( 'Save Cards', 'acquired-payment' ),
                'type'     => 'checkbox',
                'default'  => 'no',
                'description' => __( 'Enable to allow customers to checkout using a previously used card.', 'acquired-payment' ),
                'desc_tip'    => true,
            ),
            'reuse_template_id' => array(
                'title'    => __( 'Save Cards Template ID', 'acquired-payment' ),
                'label'    => __( 'Save Cards Template ID', 'acquired-payment' ),
                'type'     => 'text',
                'desc_tip' => __( 'Retrieve directly from your HPP configuration within the Acquired.com Hub.', 'acquired-payment' ),
            ),
        );
    }

    /**
     * Add order ID for Pay for order
     */
    public function add_order_information() {
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        if ( is_wc_endpoint_url( 'order-pay' ) ) {
            $order_id             = wc_get_order_id_by_order_key( $_GET['key'] );
            $order                = wc_get_order( $order_id );
            $current_url          = $order->get_checkout_payment_url( false );
            $checkout_success_url = $order->get_checkout_order_received_url();
            ?>
            <div style="display: none">
                <input type="text" class="input-text " name="order_id" id="order_id"
                       placeholder="" value="<?php echo $order_id; ?>"
                       autocomplete="given-name" readonly/>
            </div>
            <script>
                window.addEventListener("message", function (event) {
                    let response_code = event.data.response.code;
                    let transaction_id = event.data.response.transaction_id;
                    if (response_code === "1") {
                        window.location.href = "<?php echo esc_html( $checkout_success_url ); ?>";
                    } else {
                        window.location.href = "<?php echo $current_url . '&order_id=' . $order_id . '&response_code='; ?>" + response_code;
                    }
                });
            </script>
            <?php
            if ( isset( $_GET['response_code'] ) ) {
                $_helper       = new WC_Acquired_Helper();
                $error_code    = $_GET['response_code'];
                $error_message = $_helper->mapping_error_code( $error_code );
                wc_add_notice( 'Acquired response: ' . $error_message, 'error' );
            }
        }
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Builds our payment fields area - including tokenization fields for logged
     * in users, and the actual payment fields.
     *
     * @since 2.6.0
     */
    public function payment_fields() {
        wp_enqueue_style( 'acquired-style' );
        $config_required      = $this->_helper->api_required_field();
        $data                 = (array) $this;
        $payment_notice       = '';
        $user                 = wp_get_current_user();
        $user_id              = get_current_user_id();
        // $display_tokenization = $this->supports( 'tokenization' ) && is_checkout();
        $display_tokenization = $this->supports( 'tokenization' );
        $total                = WC()->cart->total;
        $user_email           = '';
        $description          = $this->method_description;
        $firstname            = '';
        $lastname             = '';
        ob_start();
        // If paying from order, we need to get total from order not cart.
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
            $order      = wc_get_order( wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) ) );
            $total      = $order->get_total();
            $user_email = $order->get_billing_email();
        } else {
            if ( $user->ID ) {
                $user_email = get_user_meta( $user->ID, 'billing_email', true );
                $user_email = $user_email ? $user_email : $user->user_email;
            }
        }
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        foreach ( $config_required as $field ) {
            if ( empty( $data[ $field ] ) ) {
                $payment_notice = $this->get_option(
                    'description',
                    __(
                        'Checkout using your credit or debit card.',
                        'acquired-payment'
                    )
                );
            }
        }

        if ( empty( $payment_notice ) ) {
            if ( $description ) {
                if ( 'sandbox' == $this->mode ) {
                    $description .= ' ' . sprintf(
                            __(
                                '<div id="test_mode"><br/>TEST MODE ENABLED. <br/>You can use card number 4000018525249219 for testing</div>',
                                'acquired-payment'
                            )
                        );
                    $description  = trim( $description );
                }
                // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $description;
                // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            if ( $display_tokenization ) {
                $show_save_method = true;
                if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
                    if ( WC_Subscriptions_Cart::cart_contains_subscription() || count( wcs_get_order_type_cart_items( 'renewal' ) ) === count( WC()->cart->get_cart() ) ) {
                        $show_save_method = false;
                    }
                }

                if ( 0 == $this->saved_cards || isset( $_GET['pay_for_order'] ) ) {
                    add_filter(
                        'woocommerce_payment_gateway_get_saved_payment_method_option_html',
                        function ( $html ) {
                            return '';
                        }
                    );
                } else {
                    if ( $user_id && $show_save_method ) {
                        global $wpdb;
                        $sql = 'SELECT COUNT(*) FROM '.$wpdb->prefix.'woocommerce_payment_tokens WHERE user_id='.$user_id;
                        $count_card = $wpdb->get_var($sql);
                        if ( $count_card > 0 ) {
                            $this->saved_payment_methods();
                        }
                    }
                }
            }

            if ( 'yes' == $this->enabled && 0 != $user_id ) {
                $this->tokenization_script();
            }
        }
        else {
            // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
// 			$show_save_method = true;
// 			if ( $show_save_method ) {
// 				$this->saved_payment_methods();
// 			}
            echo $description;
            // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        ob_end_flush();
    }

    /**
     * Displays the save to account checkbox.
     *
     * @since 4.1.0
     */
    public function save_payment_method_checkbox() {
        printf(
            '<p class="form-row woocommerce-Savedpayment_methods-saveNew">
				<input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
				<label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
			</p>',
            esc_attr( $this->id ),
            esc_html( __( 'Save payment information to my account for future purchases.', 'acquired-payment' ) )
        );
    }

    /**
     * Gets saved payment method HTML from a token.
     *
     * @since 2.6.0
     * @param  WC_Payment_Token $token Payment Token.
     * @return string Generated payment method HTML
     */
    public function get_saved_payment_method_option_html( $token ) {
        $html = sprintf(
            '<li class="woocommerce-SavedPaymentMethods-token">
				<input id="wc-%1$s-payment-token-%2$s" type="radio" name="wc-%1$s-payment-token" value="%2$s" style="width:auto;" class="woocommerce-SavedPaymentMethods-tokenInput" %4$s />
				<label for="wc-%1$s-payment-token-%2$s">%3$s</label>
			</li>',
            esc_attr( $this->id ),
            esc_attr( $token->get_id() ),
            esc_html( $token->get_display_name() ),
            checked( $token->is_default(), true, false )
        );

        if ( 'mc' === $token->get_card_type() ) {
            $mc_change_text = __( 'Mastercard', 'acquired-payment' );
            $mc_display_name = str_replace('Mc', $mc_change_text, $token->get_display_name());
            $html = sprintf(
                '<li class="woocommerce-SavedPaymentMethods-token">
				<input id="wc-%1$s-payment-token-%2$s" type="radio" name="wc-%1$s-payment-token" value="%2$s" style="width:auto;" class="woocommerce-SavedPaymentMethods-tokenInput" %4$s />
				<label for="wc-%1$s-payment-token-%2$s">%3$s</label>
			</li>',
                esc_attr( $this->id ),
                esc_attr( $token->get_id() ),
                esc_html( $mc_display_name ),
                checked( $token->is_default(), true, false )
            );
        } else {
            $html = sprintf(
                '<li class="woocommerce-SavedPaymentMethods-token">
				<input id="wc-%1$s-payment-token-%2$s" type="radio" name="wc-%1$s-payment-token" value="%2$s" style="width:auto;" class="woocommerce-SavedPaymentMethods-tokenInput" %4$s />
				<label for="wc-%1$s-payment-token-%2$s">%3$s</label>
			</li>',
                esc_attr( $this->id ),
                esc_attr( $token->get_id() ),
                esc_html( $token->get_display_name() ),
                checked( $token->is_default(), true, false )
            );
        }

        return apply_filters( 'woocommerce_payment_gateway_get_saved_payment_method_option_html', $html, $token, $this );
    }

    /**
     * Process Payment
     *
     * @param int  $order_id Order ID.
     *
     * @param bool $retry Order $retry.
     * @param bool $force_save_source Order $force_save_source.
     * @param bool $previous_error Order $previous_error.
     *
     * @return array
     * @throws Exception Exception $exception.
     */
    public function process_payment( $order_id, $retry = true, $force_save_source = false, $previous_error = false ) {
        $order = wc_get_order( $order_id );
        if ( 'AUTH_ONLY' == $this->transaction_type ) {
            $order->update_status( 'on-hold' );
        }
        if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
            if ( count( wcs_get_order_type_cart_items( 'renewal' ) ) === count( WC()->cart->get_cart() ) ) {
                foreach ( wcs_get_order_type_cart_items( 'renewal' ) as $cart_items ) {
                    $subsciption_id          = $cart_items['subscription_renewal']['subscription_id'];
                    $subsciption             = new WC_Subscription( $subsciption_id );
                    $original_transaction_id = '';

                    if ( ! empty( get_post_meta( $subsciption->get_id(), 'original_transaction_id', true ) ) ) {
                        $original_transaction_id = get_post_meta( $subsciption->get_id(), 'original_transaction_id', true );
                    } elseif ( ! empty( $subsciption->get_transaction_id() ) ) {
                        $original_transaction_id = $subsciption->get_transaction_id();
                    } elseif ( $subsciption->get_parent_id() ) {
                        $wc_order = wc_get_order( $subsciption->get_parent_id() );
                        if ( ! empty( $wc_order->get_transaction_id() ) ) {
                            $transaction_id = $wc_order->get_transaction_id();
                            if ( $transaction_id ) {
                                $original_transaction_id = $transaction_id;
                            }
                        }
                    }
                    update_post_meta( $order_id, 'subsciption_original_transaction_id', $original_transaction_id );
                }
            }
        }

        if ( $order->get_payment_method() == $this->id ) {
            update_post_meta( $order_id, 'acquired_current_user_id', get_current_user_id() );
            if ( isset( $_POST['wc-acquired-payment-token'] ) && 'new' !== $_POST['wc-acquired-payment-token'] ) {
                $token_savecard = $_POST['wc-acquired-payment-token'];
                $acquired_token = new WC_Payment_Token_CC( $token_savecard );
                $token_data     = $acquired_token->get_data();
                update_post_meta( $order_id, 'token_data', $token_data );
            }
            if ( 'redirect' == $this->iframe_option ) {
                $order  = wc_get_order( $order_id );
                $acquired_settings = get_option( 'woocommerce_acquired_settings' );
                $params = array(
                    'order'         => $order,
                    'order_id'      => $order_id,
                    'acquired-data' => $acquired_settings,
                    'redirect_url'  => $order->get_checkout_order_received_url(),
                    'token'         => get_post_meta( $order_id, 'token_data', true ),
                );

                if ( ! empty( $json['change_payment_method'] ) ) {
                    $params['change_payment_method'] = $json['change_payment_method'];
                    $params['order_id'] = 'INIT_CARD_UPDATE_' . $this->_helper->generate_code( '[A2][N2]' ) . '_' . $order_id;
                }

                $endpoint = $this->_helper->get_payment_link( $params );

                return array(
                    'result'   => 'success',
                    'redirect' => $endpoint,
                );

            } elseif ( 'popup_redirect' == $this->iframe_option ) {
                $params = array( 'order_id' => $order_id );
                if ( ! empty( $_GET['pay_for_order'] ) ) {
                    $params['pay_for_order'] = $_GET['pay_for_order'];
                }
                $endpoint = $this->_helper->get_payment_url( $params );

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
                    'redirect' => false
                    // 'redirect' => $order->get_checkout_order_received_url(),
                );
            }
        }

        return array();
    }

    /**
     * Process refund.
     *
     * This will allow to refund a passed in amount.
     *
     * @param int        $order_id Order ID.
     * @param float|null $amount Refund amount.
     * @param string     $reason Refund reason.
     *
     * @return boolean True or false based on success, or a WP_Error object.
     * @throws Exception Exception $exception.
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $wc_order = wc_get_order( $order_id );

        if ( empty( $wc_order ) ) {
            $this->_helper->acquired_logs( 'Order empty can not refund.', 'acquired-payment', $this->debug_mode );

            throw new Exception( 'error', __( 'Order empty can not refund.', 'acquired-payment' ) );
        }
        // phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralText
        if ( ! $this->can_refund_order( $wc_order ) ) {
            $this->_helper->acquired_logs( __( 'Order number #' . $order_id . 'can\'t be refunded.', 'acquired-payment' ), $this->debug_mode );

            throw new Exception( 'error', __( 'Order number #' . $order_id . 'can\'t be refunded.', 'acquired-payment' ) );
        }
        // phpcs:enable WordPress.WP.I18n.NonSingularStringLiteralText

        if ( null == $amount ) {
            $amount = $wc_order->get_total();
        }
        if ( get_post_meta( $order_id, 'refunded', true ) ) {
            return true;
        }

        $transaction_id = $wc_order->get_transaction_id();
        $payment_method = $wc_order->get_payment_method();

        if ( empty( $transaction_id ) ) {
            throw new Exception( __( 'Can not refund unpaid order.', 'acquired-payment' ) );
        } else {
            if ( $payment_method == $this->id ) {
                $transaction_infor = array(
                    'transaction_type'        => 'REFUND',
                    'original_transaction_id' => $transaction_id,
                    'amount'                  => $amount,
                );
                $request_array     = array(
                    'timestamp'    => strftime( '%Y%m%d%H%M%S' ),
                    'company_id'   => $this->company_id,
                    'company_pass' => $this->company_pass,
                    'transaction'  => $transaction_infor,
                );

                $request_hash                  = $this->_helper->request_hash(
                    array_merge( $transaction_infor, $request_array ),
                    $this->company_hashcode
                );
                $request_array['request_hash'] = $request_hash;

                $path = 'sandbox' == $this->mode ? $this->api_endpoint_sandbox : $this->api_endpoint_live;

                $this->_helper->acquired_logs( 'process_refund for acquired card payment data', $this->debug_mode );
                $this->_helper->acquired_logs( $request_array, $this->debug_mode );
                $refund_response = $this->_helper->acquired_api_request( $path, 'POST', $request_array );
                $this->_helper->acquired_logs( 'process_refund for acquired card payment response', $this->debug_mode );
                $this->_helper->acquired_logs( $refund_response, $this->debug_mode );

                if ( is_array( $refund_response ) && isset( $refund_response['response_code'] ) ) {
                    // phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralText
                    if ( '1' == $refund_response['response_code'] ) {
                        $message                   = sprintf(
                        /* translators: %s: transaction */                            __(
                            'Acquired Payments refund successfully %s (Transaction ID: ' . $refund_response['transaction_id'] . ')',
                            'acquired-payment'
                        ),
                            wc_price( $amount )
                        );
                        $request_array['order_id'] = $order_id;
                        $wc_order->add_order_note( $message );

                        if ( $wc_order->get_total_refunded() == $wc_order->get_total() ) {
                            update_post_meta( $order_id, 'refunded', true );
                        }

                        return true;
                    } else {
                        $message = __(
                                'Code ',
                                'acquired-payment'
                            ) . $refund_response['response_code'] . ' - ' . $refund_response['response_message'];
                        $wc_order->add_order_note( $message );
                        throw new Exception( $refund_response['response_message'] );
                    }
                    // phpcs:enable WordPress.WP.I18n.NonSingularStringLiteralText
                } else {
                    throw new Exception( $refund_response['response_message'] );
                }
            }
        }

        return false;
    }

    /**
     * Void the Acquired charge
     *
     * @param int      $order_id Order $order_id.
     * @param WC_Order $order Order $order.
     *
     * @return WP_Error|bool
     * @throws Exception Exception $exception.
     */
    public function acquired_void_order( $order_id, $order ) {
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
            'timestamp'    => strftime( '%Y%m%d%H%M%S' ),
            'company_id'   => $this->company_id,
            'company_pass' => $this->company_pass,
            'transaction'  => $transaction_infor,
        );

        $request_hash                  = $this->_helper->request_hash(
            array_merge( $transaction_infor, $request_array ),
            $this->company_hashcode
        );
        $request_array['request_hash'] = $request_hash;

        $path = 'sandbox' == $this->mode ? $this->api_endpoint_sandbox : $this->api_endpoint_live;

        $this->_helper->acquired_logs( 'acquired_void_order for acquired card payment data', $this->debug_mode );
        $this->_helper->acquired_logs( $request_array, $this->debug_mode );
        $cancel_response = $this->_helper->acquired_api_request( $path, 'POST', $request_array );
        $this->_helper->acquired_logs( 'acquired_void_order for acquired card payment data', $this->debug_mode );
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

                /* Update status order */
                $order->update_status('processing');

                return false;
            }
        }

        return false;
    }

    /**
     * Add payment method via account screen.
     *
     * @return array
     * @since 3.2.0 Included here from 3.2.0, but supported from 3.0.0.
     */
    public function add_payment_method() {
        // if ( 0 == $this->saved_cards ) {
        //     return array(
        //         'result'   => 'failure',
        //         'redirect' => wc_get_endpoint_url( 'payment-methods' ),
        //     );
        // }

        // /* Redirect to i-Frame */
        // $helper = new WC_Acquired_Helper();
        // $acquired_settings = get_option( 'woocommerce_acquired_settings' );
        // $generate_order_id = $helper->generate_code( '[A4]-[N4]-[A4]-[N4]' );
        // $params = array(
        //     'order_id'      => $generate_order_id,
        //     'acquired-data' => $acquired_settings,
        //     'redirect_url'  => get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ),
        // );
        // $url_iframe = $helper->get_payment_link_without_order( $params );

        // if ( ! empty( $url_iframe['payment_link'] ) ) {
        //     return array(
        //         'result'   => 'true',
        //         'redirect' => $url_iframe['payment_link']
        //     );
        // } else {
        //     wc_add_notice($url_iframe['message'], 'error');
        //     return array(
        //         'result'   => 'failure',
        //         'redirect' => wc_get_endpoint_url( 'payment-methods' ),
        //     );
        // }
    }

    /**
     * Checks if payment is via saved payment source.
     */
    public function is_using_saved_payment_method() {
        $payment_method = isset( $_POST['payment_method'] ) ? wc_clean( $_POST['payment_method'] ) : $this->id;

        return ( isset( $_POST[ 'wc-' . $payment_method . '-payment-token' ] ) && 'new' !== $_POST[ 'wc-' . $payment_method . '-payment-token' ] );
    }

    /**
     * Acquired checkout return
     *
     * @param mixed $order_id Order $order_id.
     *
     * @return string[]
     */
    public function acquired_checkout_return( $order_id ) {
        global $woocommerce;
        $order         = wc_get_order( $order_id );
        $payment_method = $order->get_payment_method();
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        if ( $payment_method == $this->id && isset( $_REQUEST['code'] ) && '1' != $_REQUEST['code'] ) {
            $error_mesage = $this->_helper->mapping_error_code( $_REQUEST['code'] );

            $order->add_order_note( $error_mesage );
            $html = "<div class='woocommerce'>
                    <ul class='woocommerce-error'>
                        <li>$error_mesage</li>
                    </ul>
                </div>";
            echo $html;

            return array(
                'result' => 'error',
            );
        }
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Capture order
     *
     * @param mixed $order_id Order $order_id.
     * @param mixed $old_status Order $old_status.
     * @param mixed $new_status Order $new_status.
     *
     * @return bool
     * @throws Exception Exception $exception.
     */
    public function acquired_capture_order( $order_id, $old_status, $new_status ) {
        if ( 'processing' == $new_status ) {
            $wc_order       = wc_get_order( $order_id );
            $transaction_id = $wc_order->get_transaction_id();
            $payment_method = $wc_order->get_payment_method();

            if ( $payment_method == $this->id ) {
                if ( ! isset( $transaction_id ) ) {
                    throw new Exception( __( 'Can not find transaction ID of Acquired.', 'acquired-payment' ) );
                } else {
                    $check_capture = get_post_meta( $order_id, 'acquired_capture', true );
                    if ( 'not_yet' == $check_capture ) {
                        $data                 = array(
                            'timestamp'    => strftime( '%Y%m%d%H%M%S' ),
                            'company_id'   => $this->company_id,
                            'company_pass' => $this->company_pass,
                        );
                        $transaction          = array(
                            'transaction_type'        => 'CAPTURE',
                            'original_transaction_id' => $transaction_id,
                            'amount'                  => $wc_order->get_total(),
                        );
                        $request_hash         = $this->_helper->request_hash(
                            array_merge( $data, $transaction ),
                            $this->company_hashcode
                        );
                        $data['request_hash'] = $request_hash;
                        $data['transaction']  = $transaction;

                        $path = 'sandbox' == $this->mode ? $this->api_endpoint_sandbox : $this->api_endpoint_live;

                        $this->_helper->acquired_logs( 'acquired_capture_order for acquired card payment data', $this->debug_mode );
                        $this->_helper->acquired_logs( $data, $this->debug_mode );
                        $capture_response = $this->_helper->acquired_api_request( $path, 'POST', $data );
                        $this->_helper->acquired_logs( 'acquired_capture_order for acquired card payment data', $this->debug_mode );
                        $this->_helper->acquired_logs( $capture_response, $this->debug_mode );

                        if ( is_array( $capture_response ) && isset( $capture_response['response_code'] ) ) {
                            $transaction['order_id']       = $order_id;
                            $transaction['transaction_id'] = $capture_response['transaction_id'];
                            if ( '1' == $capture_response['response_code'] ) {
                                $message = __( 'Acquired Payments capture order successfully.', 'acquired-payment' );
                                $wc_order->add_order_note( $message );
                                return true;
                            } else {
                                $message = __(
                                        'Code ',
                                        'acquired-payment'
                                    ) . $capture_response['response_code'] . ' - ' . $capture_response['response_message'];
                                throw new Exception( $message );
                            }
                        } else {
                            throw new Exception( 'Can not get Acquired capture response!' );
                        }
                    }
                }
            }
        }
    }

    /**
     * Refund order
     *
     * @param mixed $order_id Order $order_id.
     * @param mixed $old_status Order $old_status.
     * @param mixed $new_status Order $new_status.
     *
     * @return void
     * @throws Exception Exception $exception.
     */
    public function acquired_refund_order( $order_id, $old_status, $new_status ) {
        if ( 'refunded' == $new_status ) {
            $this->process_refund( $order_id );
        }
    }
}
