<?php
/**
 * WC Acquired Payment General
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
class WC_Acquired_General extends WC_Payment_Gateway {
    /**
     * Version
     *
     * @var string $version
     */
    public $version;

    /**
     * WC_Acquired_Payment_Abstract constructor.
     */
    public function __construct() {
        $this->version              = ACQUIRED_VERSION;
        $this->id                   = 'general';
        $this->icon = '';
        $this->title                = $this->get_option( 'title', __( 'Acquired.com for WooCommerce', 'acquired-payment' ) );
        $this->method_title         = __( 'Acquired.com for WooCommerce', 'acquired-payment' );
        $this->method_description   = $this->get_option(
            'description',
            __(
                'Securely accept Cards, Apple Pay & Google Pay on your store using Acquired.com.',
                'acquired-payment'
            )
        );

        $this->init_form_fields();

        // This action hook saves the settings
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        if(is_admin()) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        /* Company */
        $woocommerce_acquired_settings = get_option('woocommerce_acquired_settings');
        $woocommerce_apple_settings = get_option('woocommerce_apple_settings');
        $woocommerce_google_settings = get_option('woocommerce_google_settings');

        // Acquired
        $woocommerce_acquired_settings['company_id'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_company_id'] : '';
        $woocommerce_acquired_settings['company_mid_id'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_mid'] : '';
        $woocommerce_acquired_settings['company_pass'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_password'] : '';

        // Apple
        $woocommerce_apple_settings['merchant_id'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_company_id'] : '';
        $woocommerce_apple_settings['company_mid_id'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_mid'] : '';
        $woocommerce_apple_settings['company_pass'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_password'] : '';
        $woocommerce_apple_settings['charge_type'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_set_order'] : '';
        $woocommerce_apple_settings['debug_mode'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_debug_mode'] : '';

        // Google
        $woocommerce_google_settings['merchant_id'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_company_id'] : '';
        $woocommerce_google_settings['gateway_merchant_id'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_company_id'] : '';
        $woocommerce_google_settings['company_pass'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_password'] : '';
        $woocommerce_google_settings['transaction_type'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_set_order'] : '';
        $woocommerce_google_settings['merchant_name'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_company_name'] : '';
        $woocommerce_google_settings['company_hashcode'] = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_hashcode'] : '';

        /* Title */
        $woocommerce_apple_settings['title'] = __( 'Apple Pay', 'acquired-payment' );
        $woocommerce_google_settings['title'] = __( 'Google Pay', 'acquired-payment' );

        /* Staging mode */
        $accquired_staging_mode = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_staging_mode'] : '';
        if ( 'yes' === $accquired_staging_mode ) {
            $woocommerce_acquired_settings['mode'] = 'sandbox';
            $woocommerce_apple_settings['mode'] = 'sandbox';
            $woocommerce_google_settings['mode'] = 'sandbox';
        } elseif ( 'no' === $accquired_staging_mode ) {
            $woocommerce_acquired_settings['mode'] = 'live';
            $woocommerce_apple_settings['mode'] = 'live';
            $woocommerce_google_settings['mode'] = 'live';
        }

        /* Debug mode */
        $accquired_debug_mode = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_debug_mode'] : '';
        if ( 'yes' === $accquired_debug_mode ) {
            $woocommerce_google_settings['debug_mode'] = '1';
            $woocommerce_apple_settings['debug_mode'] = '1';
            $woocommerce_acquired_settings['debug_mode'] = '1';
        } elseif ( 'no' === $accquired_debug_mode ) {
            $woocommerce_apple_settings['debug_mode'] = '0';
            $woocommerce_google_settings['debug_mode'] = '0';
            $woocommerce_acquired_settings['debug_mode'] = '0';
        }

        /* Transaction type */
        $accquired_set_order = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_set_order'] : '';
        if ( 'AUTH_CAPTURE' === $accquired_set_order ) {
            $woocommerce_acquired_settings['transaction_type'] = 'AUTH_CAPTURE';
        } elseif ( 'AUTH_ONLY' === $accquired_set_order ) {
            $woocommerce_acquired_settings['transaction_type'] = 'AUTH_ONLY';
        }

        /* Hashcode */
        $accquired_hashcode = !empty(get_option('woocommerce_general_settings')) ? get_option('woocommerce_general_settings')['accquired_hashcode'] : '';
        $woocommerce_acquired_settings['company_hashcode'] = $accquired_hashcode;
        $woocommerce_apple_settings['company_hashcode'] = $accquired_hashcode;

        /* Update option */
        update_option( 'woocommerce_acquired_settings', $woocommerce_acquired_settings );
        update_option( 'woocommerce_google_settings', $woocommerce_google_settings );
        update_option( 'woocommerce_apple_settings', $woocommerce_apple_settings );
    }

    public function process_admin_options(){

        $dynamic_descriptor = ! empty( $_POST['woocommerce_general_dynamic_descriptor'] ) ? $_POST['woocommerce_general_dynamic_descriptor'] : '';

        $containsSpecial = preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $dynamic_descriptor);
        $containsLetter  = preg_match('/[a-zA-Z]/', $dynamic_descriptor);

        if ( ! $containsLetter || strlen( $dynamic_descriptor) > 25 )  {
			WC_Admin_Settings::add_error( 'The Dynamic Descriptor incorrect.' );
		} else if(  $containsSpecial ) {
            WC_Admin_Settings::add_error( 'The Dynamic Descriptor must not contain special characters.' );
        }

        parent::process_admin_options();
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'accquired_staging_mode' => array(
                'title' => __( 'Enable staging mode', 'acquired-payment' ),
                'type' => 'checkbox',
                'description' => __( 'When enabled connects to our test environment before processing live traffic.', 'acquired-payment' ),
                'default' => 'yes',
                'desc_tip' => true,
            ),
            'accquired_company_name' => array(
                'title' => __( 'Company name', 'acquired-payment' ),
                'type' => 'text',
                'description' => __( 'Dictates what your customer will see when checking out with Google Pay.', 'acquired-payment' ),
                'desc_tip'      => true,
            ),
            'accquired_company_id' => array(
                'title' => __( 'Company ID', 'acquired-payment' ),
                'type' => 'text',
                'description' => __( 'Your Acquired.com Company ID value.', 'acquired-payment' ),
                'desc_tip'      => true,
                'maxlength'      => '4',
            ),
            'accquired_mid' => array(
                'title' => __( 'MID', 'acquired-payment' ),
                'type' => 'text',
                'description' => __( 'Your Acquired.com Company MID ID value.', 'acquired-payment' ),
                'desc_tip'      => true,
            ),
            'accquired_hashcode' => array(
                'title' => __( 'Hashcode', 'acquired-payment' ),
                'type' => 'text',
                'description' => __( 'Your Acquired.com Hashcode provided via Email/LastPass.', 'acquired-payment' ),
                'desc_tip'      => true,
            ),
            'accquired_password' => array(
                'title' => __( 'Password', 'acquired-payment' ),
                'type' => 'text',
                'description' => __( 'Your Acquired.com Password provided via Email/LastPass.', 'acquired-payment' ),
                'desc_tip'      => true,
            ),
            'accquired_set_order' => array(
                'title' => __( 'Set order status to', 'acquired-payment' ),
                'type' => 'select',
                'description' => __( 'Processing = no further action and funds will arrive into your account. On Hold = you need to manually update the status via WooCommerce to release the funds into your account.', 'acquired-payment' ),
                'desc_tip'      => true,
                'options' => array(
                    'AUTH_CAPTURE' => 'Processing',
                    'AUTH_ONLY' => 'On Hold'
                )
            ),
            'dynamic_descriptor' => array(
                'title' => __( 'Dynamic Descriptor', 'acquired-payment' ),
                'type' => 'text',
                'description' => __( 'The value set here will appear as a reference on the customers bank account. It should accuratel represent your business name.', 'acquired-payment' ),
                'desc_tip'      => true,
            ),
            'accquired_query_order' => array(
                'title' => __( 'Query Order', 'acquired-payment' ),
                'type' => 'checkbox',
                'description' => __( 'Enable this feature to Query Order.', 'acquired-payment' ),
                'default' => 'no',
                'desc_tip' => true,
            ),
            'accquired_debug_mode' => array(
                'title' => __( 'Debug Mode', 'acquired-payment' ),
                'type' => 'checkbox',
                'description' => __( 'Enable this feature to have all API logs for each interaction with Acquired.com.', 'acquired-payment' ),
                'default' => 'yes',
                'desc_tip' => true,
            ),
        );

    }

}
