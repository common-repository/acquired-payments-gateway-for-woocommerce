<?php
/**
 * Plugin Name: Acquired.com for WooCommerce
 * Plugin URI: https://acquired.com/
 * Description: Securely accept Cards, Apple Pay & Google Pay on your store using Acquired.com.
 * Version: 1.3.4
 * Author: Acquired
 * Author URI: https://acquired.com/
 * Developer: Acquired
 * Developer URI: https://acquired.com/
 * Text Domain: acquired-payment
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * WC requires at least: 5.4
 * WC tested up to: 6.1.1
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin folder URL
 */
if ( ! defined( 'ACQUIRED_URL' ) ) {
    define( 'ACQUIRED_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
}

/**
 * Plugin root file
 */
if ( ! defined( 'ACQUIRED_FILE' ) ) {
    define( 'ACQUIRED_FILE', plugin_basename( __FILE__ ) );
}

/**
 * Plugin folder path
 */
if ( ! defined( 'ACQUIRED_PATH' ) ) {
    define( 'ACQUIRED_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * Plugin version
 */
if ( ! defined( 'ACQUIRED_VERSION' ) ) {
    define( 'ACQUIRED_VERSION', '1.3.4' );
}

const ACQUIRED_POST_TYPE = 'acquired_transaction';

register_activation_hook( ACQUIRED_FILE, 'create_pages' );

/**
 * Create Acquired Payment pages for plugin
 */
function create_pages() {
    if ( ! function_exists( 'wc_create_page' ) ) {
        include_once dirname( __DIR__ ) . '/woocommerce/includes/admin/wc-admin-functions.php';
    }
    $pages = array(
        'acquired' => array(
            'name'    => _x( 'acquired', 'Page slug', 'woocommerce' ),
            'title'   => _x( 'Acquired Payments', 'Page title', 'woocommerce' ),
            'content' => '[acquired_iframe]',
        ),
    );
    foreach ( $pages as $key => $page ) {
        wc_create_page(
            esc_sql( $page ['name'] ),
            'acquired' . $key . '_page_id',
            $page ['title'],
            $page ['content'],
            ! empty( $page ['parent'] ) ? wc_get_page_id( $page ['parent'] ) : ''
        );
    }
}

add_action( 'plugins_loaded', 'woocommerce_acquired_init', 0 );


add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Init Acquired Class
 */
function woocommerce_acquired_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }
    new Acquired_Payment();
}

/**
 * Deactivate plugin
 */
function plugin_deactivate() {
    update_option( 'acquired_active_status', 'inactive' );
}
register_deactivation_hook( __FILE__, 'plugin_deactivate' );

/**
 * Display order info in received page
 */
function add_delivery_date_to_order_received_page ( $order ) {
    if( version_compare( get_option( 'woocommerce_version' ), '3.0.0', ">=" ) ) {
        $order_id = $order->get_id();
    } else {
        $order_id = $order->id;
    }
    $merchant_customer_id = get_post_meta( $order_id, 'merchant_customer_id', true );
    $customer_dob = get_post_meta( $order_id, 'customer_dob', true );

    if ( '' != $merchant_customer_id ) {
        echo '<p><strong>' . __( 'Zinc Reference Number', 'acquired-payment' ) . ':</strong> ' . $merchant_customer_id.'</p>';
    }

    if ( '' != $customer_dob ) {
        echo '<p><strong>' . __( 'Date of Birth', 'acquired-payment' ) . ':</strong> ' . $customer_dob.'</p>';
    }
}
// add_action( 'woocommerce_order_details_after_customer_details', 'add_delivery_date_to_order_received_page' );
add_action( 'woocommerce_admin_order_data_after_billing_address', 'add_delivery_date_to_order_received_page', 10 , 1 );

/**
 * Class Acquired_Payment
 */
class Acquired_Payment {
    /**
     * Acquired_Payment constructor.
     */
    public function __construct() {
        require_once plugin_basename( 'includes/class-wc-acquired-general.php' );

        require_once plugin_basename( 'includes/class-wc-acquired-payment.php' );
        require_once plugin_basename( 'includes/class-wc-apple-payment.php' );
        require_once plugin_basename( 'includes/class-wc-google-payment.php' );
        require_once plugin_basename( 'includes/class-wc-acquired-helper.php' );
        require_once plugin_basename( 'includes/class-wc-acquired-rest-api-controller.php' );
        require_once plugin_basename( 'admin/class-acquired-transaction-grid.php' );
        require_once plugin_basename( 'includes/class-wc-acquired-subscription.php' );
        add_action( 'wp_enqueue_scripts', array( $this, 'acquired_enqueue_scripts' ) );
        add_action( 'wp_ajax_checkout_get_payment_link', array( $this, 'checkout_get_payment_link' ) );
        add_action( 'wp_ajax_nopriv_checkout_get_payment_link', array( $this, 'checkout_get_payment_link' ) );
        add_action( 'wp_ajax_create_order', array( $this, 'create_order_googlepay' ) );
        add_action( 'wp_ajax_nopriv_create_order', array( $this, 'create_order_googlepay' ) );
        add_action( 'wp_ajax_create_order_applepay', array( $this, 'create_order_applepay' ) );
        add_action( 'wp_ajax_nopriv_create_order_applepay', array( $this, 'create_order_applepay' ) );
        add_action( 'wp_ajax_request_payment_session', array( $this, 'get_apple_merchant_session' ) );
        add_action( 'wp_ajax_nopriv_request_payment_session', array( $this, 'get_apple_merchant_session' ) );
        add_action( 'wp_ajax_validate_time_zone', array( $this, 'validate_time_zone' ) );
        add_action( 'wp_ajax_nopriv_validate_time_zone', array( $this, 'validate_time_zone' ) );
        add_action( 'wp_ajax_googlepay_process_payment', array( $this, 'googlepay_process_payment' ) );
        add_action( 'wp_ajax_nopriv_googlepay_process_payment', array( $this, 'googlepay_process_payment' ) );
        add_action( 'wp_ajax_apple_process_payment', array( $this, 'apple_process_payment' ) );
        add_action( 'wp_ajax_nopriv_apple_process_payment', array( $this, 'apple_process_payment' ) );
        add_shortcode( 'acquired_iframe', array( $this, 'acquired_iframe' ) );
        add_filter(
            'plugin_action_links_' . plugin_basename( __FILE__ ),
            array(
                $this,
                'woocommerce_acquired_plugin_links',
            )
        );
        add_filter( 'body_class', array( $this, 'remove_notice_popup', ) );
        add_filter( 'woocommerce_payment_gateways', array( $this, 'woocommerce_acquired_general' ) );
        add_filter( 'woocommerce_payment_gateways', array( $this, 'woocommerce_acquired_add_gateway' ) );
        add_filter( 'woocommerce_payment_gateways', array( $this, 'woocommerce_apple_add_gateway' ) );
        add_filter( 'woocommerce_payment_gateways', array( $this, 'woocommerce_google_add_gateway' ) );
        add_action( 'init', array( $this, 'load_rest_api' ) );
        add_action( 'woocommerce_before_checkout_form', array( $this, 'acquired_error_submit' ) );
        add_action( 'init', array( $this, 'redirect_checkout_success' ) );
        add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'output_product_fields' ) );
        new Acquired_Transaction_Grid();
        add_action( 'woocommerce_customer_save_address', array( $this, 'update_billing_address' ), 99, 2 );
//        add_action( 'woocommerce_process_shop_order_meta', array( $this, 'admin_update_billing_address' ), 10, 2 );
        add_action( 'admin_notices', array( $this, 'add_notice_when_activated' ) );
        add_action( 'woocommerce_add_payment_method_form_bottom', array( $this, 'woocommerce_acquired_modify_payment_method' ) );

        add_action( 'woocommerce_sections_checkout', array( $this, 'acquired_sections_general' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'acquired_admin_scripts' ) );

        /* Popup success received */
        add_action( 'wp_ajax_nopriv_success_received', array( $this, 'mgn_success_received' ) );
        add_action( 'wp_ajax_success_received', array( $this, 'mgn_success_received' ) );

        /* Remove gateways Acquired.com */
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'custom_available_payment_gateways' ) );

        /* Rename Mastercard */
        add_filter( 'woocommerce_get_credit_card_type_label', array( $this, 'woocommerce_rename_mastercard' ) );

        /* Disable lottery validate items */
        add_action( 'wp_head', array( $this, 'woocommerce_remove_lottery' ) );

        /* My account add card show popup */
        add_action( 'wp_ajax_my_account_get_payment_link', array( $this, 'my_account_get_payment_link' ) );
        add_action( 'wp_ajax_nopriv_my_account_get_payment_link', array( $this, 'my_account_get_payment_link' ) );

        /* Remove old data order acquired */
        add_action( 'admin_init', array($this, 'acquired_remove_old_data_order') );

        /* Create cron jobs (clear them first) */
        add_action( 'woocommerce_new_order', array($this, 'acquired_create_schedule_query_api_pending_order'), 10, 1 );
        add_action( 'acquired_query_api_pending_order', array($this, 'acquired_handle_query_api_for_order'), 10, 1 );
    }

    public function my_account_get_payment_link(){
        
        $helper = new WC_Acquired_Helper();
        $acquired_settings = get_option( 'woocommerce_acquired_settings' );
        $generate_order_id = $helper->generate_code( '[A4]-[N4]-[A4]-[N4]' );
        $params = array(
            'order_id'      => $generate_order_id,
            'acquired-data' => $acquired_settings,
            'redirect_url'  => get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ),
        );
        $url_iframe = $helper->get_payment_link_without_order( $params );

        $results = array();
        if ( ! empty( $url_iframe['payment_link'] ) ) {
            $results['result'] = 'success';
            $results['payment_link'] = $url_iframe['payment_link'];
        } else {
            wc_add_notice( $url_iframe['message'], 'error' );
            $results['result'] = 'failure';
            $results['message'] = $url_iframe['message'];
            $results['url']    = wc_get_account_endpoint_url( 'payment-methods' );
        }
        wp_send_json( $results );
    }

    /**
     * Remove gateways Acquired.com
     *
     * @param mixed $links Plugin links.
     *
     * @return array
     */
    public function custom_available_payment_gateways($available_gateways) {
        $payment_ids = array('general'); // Here define the allowed payment methods ids to keep ( cod, stripe ... )
        // For Order pay
        foreach ($available_gateways as $payment_id => $available_gateway) {
            if (in_array($payment_id, $payment_ids)) {
                unset($available_gateways[$payment_id]);
            }
        }
        return $available_gateways;
    }

    /**
     * Add Acquired payment gateway
     *
     * @param mixed $methods Payment methods.
     *
     * @return mixed
     */
    public function woocommerce_acquired_general( $methods ) {
        $methods[] = 'WC_Acquired_General';

        return $methods;
    }

    /**
     * Add Acquired payment gateway
     *
     * @param mixed $methods Payment methods.
     *
     * @return mixed
     */
    public function woocommerce_acquired_add_gateway( $methods ) {
        if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
            $methods[] = 'WC_Acquired_Subscription';
        } else {
            $methods[] = 'WC_Acquired_Payment';
        }

        return $methods;
    }

    /**
     * Add Acquired payment gateway
     *
     * @param mixed $methods Payment methods.
     *
     * @return mixed
     */
    public function woocommerce_apple_add_gateway( $methods ) {
        $methods[] = 'WC_Apple_Payment';

        return $methods;
    }

    /**
     * Add Acquired payment gateway
     *
     * @param mixed $methods Payment methods.
     *
     * @return mixed
     */
    public function woocommerce_google_add_gateway( $methods ) {
        $methods[] = 'WC_Google_Payment';

        return $methods;
    }

    /**
     * Register routes
     */
    public function load_rest_api() {
        add_action( 'rest_api_init', array( $this, 'create_api' ), 99 );
    }

    /**
     * Create API to accept setup Webhook
     */
    public function create_api() {
        $api = new WC_Acquired_Rest_Api_Controller();
        $api->register_routes();
    }

    /**
     * Register script
     */
    public function acquired_enqueue_scripts() {
        $acquired_settings = get_option( 'woocommerce_acquired_settings' );
        wp_register_style( 'acquired-style', plugin_dir_url( __FILE__ ) . 'assets/css/style.css', false, ACQUIRED_VERSION );

        //enqueue style in page my-account woo
        if ( is_account_page() ) {
            wp_enqueue_style( 'acquired-style' );
        }

        if ( 'popup' == $acquired_settings['iframe_option'] && 'yes' == $acquired_settings['enabled'] ) {
            wp_register_script(
                'acquired-woocommerce',
                plugin_dir_url( __FILE__ ) . 'assets/js/acquired.js',
                array( 'jquery' ),
                ACQUIRED_VERSION,
                false
            );
            wp_localize_script(
                'acquired-woocommerce',
                'wc_acquired',
                array(
                    'ajaxurl' => admin_url( 'admin-ajax.php' ),
                )
            );
            wp_enqueue_script( 'acquired-woocommerce' );
            if ( 'yes' == $acquired_settings['enabled'] ) {
                wp_deregister_script( 'wc-checkout' );
                wp_enqueue_script(
                    'wc-checkout',
                    plugin_dir_url( __FILE__ ) . 'assets/js/checkout.js',
                    array( 'jquery', 'woocommerce', 'wc-country-select', 'wc-address-i18n' ),
                    ACQUIRED_VERSION,
                    true
                );
                // wp_register_style( 'style-acquired-popup', plugin_dir_url( __FILE__ ) . 'assets/css/acquired.popup.css', false, ACQUIRED_VERSION );
                // wp_enqueue_style( 'style-acquired-popup' );
            }
        }

        $gpay_setting = get_option( 'woocommerce_google_settings' );
        if ( isset($gpay_setting['enabled']) && ('yes' == $gpay_setting['enabled']) ) {
            wp_register_script( 'wc-acquired-gpay', 'https://pay.google.com/gp/p/js/pay.js', array( 'jquery' ), ACQUIRED_VERSION, true );
            wp_enqueue_script( 'wc-acquired-gpay' );

            if ( is_cart() ) {
                wp_register_script(
                    'googlepay-cart',
                    ACQUIRED_URL . '/assets/js/googlepay-cart.js',
                    array( 'jquery' ),
                    ACQUIRED_VERSION,
                    true
                );
            }

            if ( is_product() ) {
                wp_register_script(
                    'googlepay-product',
                    ACQUIRED_URL . '/assets/js/googlepay-product.js',
                    array( 'jquery' ),
                    ACQUIRED_VERSION,
                    true
                );
            }
        }

        $apple_pay_setting = get_option( 'woocommerce_apple_settings' );
        if ( isset($apple_pay_setting['enabled']) && ('yes' == $apple_pay_setting['enabled']) ) {
            wp_register_script( 'wc-acquired-apple', 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js', array( 'jquery' ), ACQUIRED_VERSION, true );
            wp_enqueue_script( 'wc-acquired-apple' );

            if ( is_product() ) {
                wp_register_script(
                    'applepay-product',
                    ACQUIRED_URL . '/assets/js/applepay-product.js',
                    array( 'jquery' ),
                    ACQUIRED_VERSION,
                    true
                );
            }
        }

    }

    /**
     * Add Acquired payment admin scripts
     *
     * @param mixed $methods Payment methods.
     *
     * @return mixed
     */
    public function acquired_admin_scripts($hook) {
        global $current_section;

//        if ( 'woocommerce_page_wc-settings' === $hook ) {
//            wp_register_script(
//                'acquired-admin',
//                ACQUIRED_URL . '/assets/js/payment.admin.js',
//                array( 'jquery' ),
//                ACQUIRED_VERSION,
//                false
//            );
//            wp_enqueue_script( 'acquired-admin' );
//        }

        if ( ( 'general' === $current_section ) || ( 'woocommerce_page_wc-settings' === $hook ) ) {
            wp_register_style(
                'acquired-admin',
                ACQUIRED_URL . '/assets/css/payment.admin.css',
                false,
                ACQUIRED_VERSION
            );
            wp_enqueue_style( 'acquired-admin' );
        }
    }

    /**
     * Add Acquired payment gateway
     *
     * @param mixed $methods Payment methods.
     *
     * @return mixed
     */
    public function acquired_sections_general() {
        global $current_section;

        if ( ( 'general' === $current_section ) || ( 'acquired' === $current_section ) ) {
            $tab_id = 'checkout';
            $sections = array(
                'general'  => __( 'General', 'woocommerce' ),
                'acquired'     => __( 'Card Payment', 'woocommerce' ),
                'apple'    => __( 'Apple Pay', 'woocommerce' ),
                'google'   => __( 'Google Pay', 'woocommerce' )
            );

            echo '<ul class="subsubsub">';

            $array_keys = array_keys( $sections );

            foreach ( $sections as $id => $label ) {
                echo '<li><a href="' . admin_url( 'admin.php?page=wc-settings&tab=' . $tab_id . '&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';
            }

            echo '</ul><br class="clear" />';

            echo '<p class="sub-title" style="margin-bottom: 0;">'.__( 'These settings will apply to all of your payment methods and must be configured first.', 'acquired-payment' ).'</p>';
            echo '<p class="sub-title" style="margin-top: 0;">'.__( 'Navigate to each payment method to enable and configure how it is displayed to your customers.', 'acquired-payment' ).'</p>';

            echo '<ul class="subsubsub">';
            echo '<li><a target="_blank" href="https://docs.acquired.com/docs/woocommerce-1">'.__( 'Documentation', 'acquired-payment' ).'</a></li>'.'|';
            echo '<li><a target="_blank" href="https://acquired.com/contact/">'.__( 'Contact Support', 'acquired-payment' ).'</a></li>';
            echo '</ul><br class="clear" />';

            ?>
            <style>
                #mainform > h2,
                #mainform > p:not(.submit):not(.sub-title),
                table.wc_gateways tr[ data-gateway_id=apple] {
                    display: none;
                }
            </style>
            <?php
        }

        if ( 'general' === $current_section ) {
            echo '<span class="save-tootip">'.wc_help_tip(__( 'Please save your settings before leaving this page.', 'acquired-payment' )).'</span>'; ?>
            <script type="text/javascript">
                jQuery(document).ready( function($) {
                    $('.save-tootip').appendTo('.submit');
                });
            </script>
            <?php
        }

        if ( 'acquired' === $current_section ) {
            $woocommerce_acquired_settings = get_option('woocommerce_acquired_settings');

            if ( '2' === $woocommerce_acquired_settings['three_d_secure'] ) { ?>
                <script type="text/javascript">
                    jQuery(document).ready( function($) {
                        $('#woocommerce_acquired_three_d_secure').each(function(e){
                            $(this).attr("checked", "checked");
                        });
                    });
                </script>
            <?php }

            if ( '1' === $woocommerce_acquired_settings['saved_cards'] ) { ?>
                <script type="text/javascript">
                    jQuery(document).ready( function($) {
                        $('#woocommerce_acquired_saved_cards').each(function(e){
                            $(this).attr("checked", "checked");
                        });
                    });
                </script>
            <?php } ?>
            <script type="text/javascript">
                jQuery(document).ready( function($) {
                    $('#woocommerce_acquired_text_title_medthod').remove();
                    $('#woocommerce_acquired_text_title_secure').remove();
                    $('#woocommerce_acquired_desc_1_save_card').remove();
                    $('#woocommerce_acquired_desc_2_save_card').remove();
                    $('#woocommerce_acquired_desc_3_save_card').remove();
                });
            </script>
            <style>
                #mainform .form-table tbody tr:nth-child(7),
                #mainform .form-table tbody tr:nth-child(10) {
                    border-top: 1px solid #ddd;
                }

                #mainform .form-table tbody tr:nth-child(2) label,
                #mainform .form-table tbody tr:nth-child(7) label,
                #mainform .form-table tbody tr:nth-child(10) label {
                    font-size: 22px;
                    font-weight: normal;
                }

                #mainform .form-table tbody tr:nth-child(2) th {
                    padding: 10px 0;
                }

                #mainform .form-table tbody tr:nth-child(7) th,
                #mainform .form-table tbody tr:nth-child(10) th {
                    padding: 20px 0 10px;
                }

                #mainform .form-table tbody tr:nth-child(6) th,
                #mainform .form-table tbody tr:nth-child(6) td,
                #mainform .form-table tbody tr:nth-child(9) th,
                #mainform .form-table tbody tr:nth-child(9) td {
                    padding-bottom: 30px;
                }

                #mainform .form-table tbody tr:nth-child(11),
                #mainform .form-table tbody tr:nth-child(12),
                #mainform .form-table tbody tr:nth-child(13) {
                    display: flex;
                }

                #mainform .form-table tbody tr:nth-child(11) th,
                #mainform .form-table tbody tr:nth-child(12) th,
                #mainform .form-table tbody tr:nth-child(13) th {
                    padding: 0;
                    min-width: 600px;
                    font-weight: normal;
                    font-style: italic;
                }

                #mainform .form-table tbody tr:nth-child(11) td,
                #mainform .form-table tbody tr:nth-child(12) td,
                #mainform .form-table tbody tr:nth-child(13) td {
                    display: none;
                }

                #mainform .form-table tbody tr:nth-child(13) {
                    padding-bottom: 10px;
                }
            </style>
            <?php
        }

        if ( 'apple' === $current_section ) {
            $tab_id = 'checkout';

            // Must contain more than one section to display the links
            // Make first element's key empty ('')
            $sections = array(
                'general'  => __( 'General', 'woocommerce' ),
                'acquired'     => __( 'Card Payment', 'woocommerce' ),
                'apple'    => __( 'Apple Pay', 'woocommerce' ),
                'google'   => __( 'Google Pay', 'woocommerce' )
            );

            echo '<ul class="subsubsub">';

            $array_keys = array_keys( $sections );

            foreach ( $sections as $id => $label ) {
                echo '<li><a href="' . admin_url( 'admin.php?page=wc-settings&tab=' . $tab_id . '&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';
            }

            echo '</ul><br class="clear" />';

            echo '<p class="sub-title">'.__( 'In order for Apple Pay to work on your store, you need to follow these steps and configure the details below.', 'acquired-payment' ).'</p>';

            echo '<ul class="subsubsub">';
            echo '<li><a target="_blank" href="https://docs.acquired.com/docs/woocommerce-1">'.__( 'Documentation', 'acquired-payment' ).'</a></li>'.'|';
            echo '<li><a target="_blank" href="https://acquired.com/contact">'.__( 'Contact Support', 'acquired-payment' ).'</a></li>';
            echo '</ul><br class="clear" />';

            ?>
            <style>
                #mainform > h2,
                #mainform > p:not(.submit):not(.sub-title) {
                    display: none;
                }
            </style>
            <?php
        }

        if ( 'google' === $current_section ) {
            $tab_id = 'checkout';

            // Must contain more than one section to display the links
            // Make first element's key empty ('')
            $sections = array(
                'general'  => __( 'General', 'woocommerce' ),
                'acquired'     => __( 'Card Payment', 'woocommerce' ),
                'apple'    => __( 'Apple Pay', 'woocommerce' ),
                'google'   => __( 'Google Pay', 'woocommerce' )
            );

            echo '<ul class="subsubsub">';

            $array_keys = array_keys( $sections );

            foreach ( $sections as $id => $label ) {
                echo '<li><a href="' . admin_url( 'admin.php?page=wc-settings&tab=' . $tab_id . '&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';
            }

            echo '</ul><br class="clear" />';

            echo '<p class="sub-title">'.__( 'In order for Google Pay to work on your store, you need to follow these steps and configure the details below.', 'acquired-payment' ).'</p>';

            echo '<ul class="subsubsub">';
            echo '<li><a target="_blank" href="https://docs.acquired.com/docs/woocommerce-1">'.__( 'Documentation', 'acquired-payment' ).'</a></li>'.'|';
            echo '<li><a target="_blank" href="https://acquired.com/contact">'.__( 'Contact Support', 'acquired-payment' ).'</a></li>';
            echo '</ul><br class="clear" />';

            ?>
            <style>
                #mainform > h2,
                #mainform > p:not(.submit):not(.sub-title) {
                    display: none;
                }
            </style>
            <?php
        }

    }

    /**
     * Fix notice conflix CheckoutWC
     */
    public function remove_notice_popup( $classes ) {
        // $acquired_settings = get_option( 'woocommerce_acquired_settings' );
        $acquired_settings = get_option( 'woocommerce_acquired_settings' );
        if ( 'popup' == $acquired_settings['iframe_option'] && 'yes' == $acquired_settings['enabled'] ) {
            $classes[] = 'acquired-popup';
        }
        return $classes;
    }

    /**
     * Acquired iframe page
     *
     * @return false|string
     * @throws Exception Exception $exception.
     */
    public function acquired_iframe() {
        $helper            = new WC_Acquired_Helper();
        $params            = $helper->get_params();
        $order_id          = isset( $params['order_id'] ) ? $params['order_id'] : null;
        $acquired_settings = get_option( 'woocommerce_acquired_settings' );
        $response_code     = ! empty( $params['code'] ) ? $params['code'] : '';
        $order             = wc_get_order( $order_id );
        if ( ! empty( $order_id ) && ! empty( $response_code ) ) {
            if ( $response_code != '1' ) {
                $url = wc_get_checkout_url();
                wc_add_notice( __( 'Decline your payment. Please try again!' ), 'error' );
            } else {
                $url = $helper->get_return_url( $order );
            }
            echo "<script>document.addEventListener('DOMContentLoaded', function(){ window.top.location.href = '" . $url . "'; }); </script>";
        } else {
            $template_path = ACQUIRED_PATH . 'templates/';
            $default_path  = ACQUIRED_PATH . 'templates/';
            if ( $order_id != null ) {
                $params = array(
                    'order'         => $order,
                    'order_id'      => $order_id,
                    'acquired-data' => $acquired_settings,
                    'redirect_url'  => $order->get_checkout_order_received_url(),
                    'token'         => get_post_meta( $order_id, 'token_data', true ),
                );
                if ( ! empty( $_GET['pay_for_order'] ) ) {
                    $params['pay_for_order'] = $_GET['pay_for_order'];
                } elseif ( ! empty( $_GET['change_payment_method'] ) ) {
                    $params['change_payment_method'] = $_GET['change_payment_method'];
                    $params['order_id']              = 'INIT_CARD_UPDATE_' . $helper->generate_code( '[A2][N2]' ) . '_' . $order_id;

                    update_post_meta( $order_id, 'update_for_all_subs', ! empty( $json['update_all_subscriptions_payment_method'] ) );
                }
                $url = $helper->get_payment_link( $params );
                if ( is_int( $url ) ) {
                    $params['rebill_redirect_url'] = $order->get_checkout_order_received_url();
                } elseif ( strpos( $url, 'Rebill' ) > -1 ) {
                    $params['error_message'] = $url;
                } else {
                    $str_pos = strpos( $url, 'https' );
                    if ( false !== $str_pos ) {
                        $params['url'] = $url;
                    } else {
                        $params['error_message'] = $url;
                        $helper->acquired_logs( 'Error when get payment link: ' . $url, true );
                    }
                    $params['url_return_true']  = site_url( '?transaction_id=' );
                    $params['url_return_false'] = site_url( 'checkout?response_code=' );
                }
            }
            ob_start();
            wc_get_template( 'iframe-form.php', $params, $template_path, $default_path );

            return ob_get_clean();
        }
    }

    /**
     * Display Acquired error message
     */
    public function acquired_error_submit() { ?>
        <script>
            (function ($) {
                'use strict';

                // Document ready
                $(function () {
                    if (typeof wpcf7 !== 'undefined') {
                        wpcf7.cached = 0;
                    }
                });

                // On window load
                $(window).on('message', function (e) {
                    var data = e.originalEvent.data;
                    if ( data.response ) {
                        var response_code = data.response.code;
                        if (response_code === "1") {
                            // console.log( JSON.stringify(data.response) );
                            var merchant_custom_1 = data.response.merchant_custom_1;
                            $.ajax({
                                type: 'POST',
                                data: {
                                    action: 'success_received',
                                    order_id: merchant_custom_1
                                },
                                url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                                beforeSend: function () {
                                },
                                success: function (responses) {
                                    var obj = JSON.parse(responses);

                                    if ( obj.success ) {
                                        window.top.location.href = obj.url;
                                    }
                                }
                            });
                        } else {
                            window.location.href = "<?php echo esc_html( site_url( 'checkout?response_code=' ) ); ?>" + response_code;
                        }
                    }
                });
            })(jQuery);
        </script>
        <?php
        if ( isset( $_GET['response_code'] ) ) {
            $_helper       = new WC_Acquired_Helper();
            $error_code    = $_GET['response_code'];
            $error_message = $_helper->mapping_error_code( $error_code );
            wc_add_notice( $error_message, 'error' );
        }
    }

    /**
     * Popup redirect checkout success
     *
     */
    public function mgn_success_received() {
        try{
            $order_id = (!empty($_POST['order_id'])) ? $_POST['order_id'] : '';
            $order = wc_get_order($order_id);
            $url = $order->get_checkout_order_received_url();
            if ( isset( WC()->cart ) ) {
                WC()->cart->empty_cart();
            }

            $output = array(
                'success' => true,
                'url' => $url
            );
        } catch (\Exception $exception) {
            $output = array(
                'success' => false,
                'url' => ''
            );
        }

        echo json_encode($output);
        wp_die();
    }

    /**
     * Redirect checkout success
     */
    public function redirect_checkout_success() {
        $check_if_add_payment = false !== strpos( @$_SERVER['REQUEST_URI'], 'my-account/payment-methods/?response_code' ) ||
            false !== strpos( @$_SERVER['REQUEST_URI'], 'my-account/payment-methods/?transaction_card' );
        if ( $check_if_add_payment ) {
            if ( ! empty( $_GET['transaction_card'] ) ) {
                wc_add_notice( 'Payment method has been added, Please wait for the list to update!', 'success' );
            }
            if ( ! empty( $_GET['response_code'] ) ) {
                $helper     = new WC_Acquired_Helper();
                $error_mess = $helper->mapping_error_code( $_GET['response_code'] );
                wc_add_notice( 'Add payment method response: ' . $error_mess, 'error' );
            }
        }

        if ( isset( $_GET['pay_for_order'] ) ) {
            $helper     = new WC_Acquired_Helper();
            $error_mess = $helper->mapping_error_code( $_GET['response_code'] );
            wc_add_notice( $error_mess, 'error' );
        }
    }

    /**
     * Get payment link for popup
     *
     * @throws Exception Exception $exception.
     */
    public function checkout_get_payment_link() {
        $json              = $_POST['json'];
        $acquired_settings = get_option( 'woocommerce_acquired_settings' );
        $order_id          = ! empty( $json['order_id'] ) ? $json['order_id'] : $json['woocommerce_change_payment'];
        $helper            = new WC_Acquired_Helper();

        $token_savecard = ((isset( $_POST['json']['wc-acquired-payment-token'] )) && ( !empty($_POST['json']['wc-acquired-payment-token']) )) ? $_POST['json']['wc-acquired-payment-token'] : '';
        if ( ! empty( $token_savecard ) ) {
            $acquired_token = new WC_Payment_Token_CC( $token_savecard );
            $token_data     = $acquired_token->get_data();
            update_post_meta( $order_id, 'token_data', $token_data );
        }

        if ( ! empty( $order_id ) ) {
            $order  = wc_get_order( $order_id );
            $params = array(
                'order'         => $order,
                'order_id'      => $order_id,
                'acquired-data' => $acquired_settings,
                'redirect_url'  => $order->get_checkout_order_received_url(),
                'token'         => get_post_meta( $order_id, 'token_data', true ),
            );
            // if ( ! empty( $_POST['isMoto'] ) ) {
            //     $params['pay_for_order'] = $_POST['isMoto'];
            // }

            if ( ! empty( $json['change_payment_method'] ) ) {
                $params['change_payment_method'] = $json['change_payment_method'];
                $params['order_id']              = 'INIT_CARD_UPDATE_' . $helper->generate_code( '[A2][N2]' ) . '_' . $order_id;

                update_post_meta( $order_id, 'update_for_all_subs', ! empty( $json['update_all_subscriptions_payment_method'] ) );
            }
            $url = $helper->get_payment_link( $params );

            if ( is_int( $url ) ) {
                wp_die( 'Rebill' . esc_html( $order->get_checkout_order_received_url() ) );
            } elseif ( strpos( $url, 'Rebill' ) > -1 ) {
                $order->add_order_note( $url );
                wp_die( 'Gateway Response: ' . esc_html( $url ) );
            } elseif ( strpos( $url, 'https' ) > - 1 ) {
                wp_die( esc_html( $url ) );
            } else {
                $order->add_order_note( 'Gateway Response: ' . $url );
                wp_die( 'Gateway Response: ' . esc_html( $url ) );
            }
        }
    }

    /**
     * Show Google Pay button on Product page
     *
     * @throws Exception Exception $exception.
     */
    public function output_product_fields() {
        $google_payment = new WC_Google_Payment();
        $apple_payment  = new WC_Apple_Payment();
        global $product;
        $product_id = $product->get_id();
        wp_register_style( 'acquired-payment-style', plugin_dir_url( __FILE__ ) . 'assets/css/style.css', false, ACQUIRED_VERSION );
        wp_enqueue_style( 'acquired-payment-style' );
        if ( 'yes' == $google_payment->enable_google && $google_payment->_helper->is_payment_section( $google_payment, 'product' ) ) {
            if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
                return;
            } else {
                wp_localize_script(
                    'googlepay-product',
                    'googlepay_product',
                    $google_payment->get_localized_params()
                );
                wp_enqueue_script( 'googlepay-product' );

                $template_path = ACQUIRED_PATH . 'templates/';
                $default_path  = ACQUIRED_PATH . 'templates/';

                wc_get_template(
                    'product/payment-methods.php',
                    array(
                        'gateways'          => 'google',
                        'gateway_instance'  => $google_payment,
                        'product_id'        => $product_id,
                        'group_product'     => 'grouped' == $product->get_type() ? 'yes' : 'no',
                        'variabled_product' => 'variable' == $product->get_type() ? 'yes' : 'no',
                    ),
                    $template_path,
                    $default_path
                );
            }
        }

        if ( 'yes' == $apple_payment->enable_apple && $apple_payment->_helper->is_payment_section( $apple_payment, 'product' )
            && $apple_payment->check_apple_sdk() ) {
            if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
                return;
            } else {
                $template_path = ACQUIRED_PATH . 'templates/';
                $default_path  = ACQUIRED_PATH . 'templates/';
                wp_enqueue_script( 'applepay-product' );

                wc_get_template(
                    'product/applepay-methods.php',
                    array(
                        'gateways'          => 'apple',
                        'gateway_instance'  => $apple_payment,
                        'product_id'        => $product_id,
                        'group_product'     => 'grouped' == $product->get_type() ? 'yes' : 'no',
                        'variabled_product' => 'variable' == $product->get_type() ? 'yes' : 'no',
                        'button_height'     => $apple_payment->get_option( 'button_height' ),
                    ),
                    $template_path,
                    $default_path
                );
            }
        }
    }

    /**
     * Create order through ajax
     *
     * @throws Exception Exception $exception.
     */
    public function create_order_googlepay() {
        $payment_data_param = (array) json_decode( html_entity_decode( stripslashes( $_POST['paymentData'] ) ) );
        $tds         = (array) json_decode( html_entity_decode( stripslashes( $_POST['tds'] ) ) );
        $order       = wc_create_order();
        $helper      = new WC_Acquired_Helper();
        $wc_google_payment = new WC_Google_Payment();
        $fees = !empty($_POST['fees']) ? $_POST['fees'] : '';
        if ( ! empty( $_POST['productData'] ) ) {
            if ( ! empty( $_POST['productGroupData'] ) ) {
                foreach ( $_POST['productGroupData'] as $product ) {
                    $order->add_product( wc_get_product( $product['product_id'] ), $product['product_quantity'] );
                }
            } else {
                if ( empty( $_POST['variation_id'] ) ) {
                    $order->add_product( wc_get_product( $_POST['productData'] ), $_POST['productQty'] );
                } else {
                    $order->add_product( wc_get_product( $_POST['variation_id'] ), $_POST['productQty'] );
                }
            }
        } else {
            $cart = WC()->cart;
            foreach ( $cart->get_cart() as $cart_item ) {
                /**
                 * @var WC_Product $product
                 */
                $default_args = array(
                    'variation_id' => $cart_item['variation_id'],
                    'variation'    => $cart_item['variation'],
                );
                $order->add_product( wc_get_product( $cart_item['product_id'] ), $cart_item['quantity'], $default_args );
            }

            /* Add Fee */
            if ( !empty($fees) ) {
                foreach ( $fees as $fee ) {
                    $orderFee= (object) $fee;
                    $order->add_fee($orderFee);
                }
            }
        }

        $address = array();

        if ( !empty($payment_data_param['billingContact'] ) ) {
            $address = array(
                'first_name' => $payment_data_param['billingContact']->givenName,
                'last_name'  => $payment_data_param['billingContact']->familyName,
                'address_1'  => $payment_data_param['billingContact']->addressLines[0],
                'address_2'  => $payment_data_param['billingContact']->addressLines[0],
                'city'       => $payment_data_param['billingContact']->locality,
                'state'      => $payment_data_param['billingContact']->administrativeArea,
                'postcode'   => $payment_data_param['billingContact']->postalCode,
                'country'    => $payment_data_param['billingContact']->countryCode,
                'email'      => $payment_data_param['email'],
                'phone'      => $payment_data_param['billingContact']->phoneNumber
            );
        } elseif ( !empty($payment_data_param['shippingContact'] ) ) {
            $address = array(
                'first_name' => $payment_data_param['shippingContact']->givenName,
                'last_name'  => $payment_data_param['shippingContact']->familyName,
                'address_1'  => $payment_data_param['shippingContact']->addressLines,
                'address_2'  => $payment_data_param['shippingContact']->addressLines,
                'city'       => $payment_data_param['shippingContact']->locality,
                'state'      => $payment_data_param['shippingContact']->administrativeArea,
                'postcode'   => $payment_data_param['shippingContact']->postalCode,
                'country'    => $payment_data_param['shippingContact']->countryCode,
                'email'      => $payment_data_param['email'],
                'phone'      => $payment_data_param['shippingContact']->phoneNumber
            );
        } elseif ( !empty($payment_data_param['shippingAddress'] ) ) {
            $address = array(
                'last_name'  => $payment_data_param['shippingAddress']->name,
                'address_1'  => $payment_data_param['shippingAddress']->address1,
                'address_2'  => $payment_data_param['shippingAddress']->address2,
                'city'       => $payment_data_param['shippingAddress']->locality,
                'state'      => $payment_data_param['shippingAddress']->administrativeArea,
                'postcode'   => $payment_data_param['shippingAddress']->postalCode,
                'country'    => $payment_data_param['shippingAddress']->countryCode,
                'email'      => $payment_data_param['email'],
                'phone'      => $payment_data_param['shippingAddress']->phoneNumber
            );
        }

        $order->set_address( $address, 'billing' );
        $order->set_address( $address, 'shipping' );
        $order->set_payment_method( $wc_google_payment->id );
        $order->set_payment_method_title( $wc_google_payment->title );

        if ( is_user_logged_in() ) {
            update_post_meta($order->get_id(), '_customer_user', get_current_user_id());
        }

        if ( 'true' == $_POST['needShipping'] ) {
            $zones          = WC_Shipping_Zones::get_zones();
            $check_shipping = false;

            foreach ( $zones as $zone ) {
                foreach ( $zone['shipping_methods'] as $shipping_method ) {
                    $shipping_name        = $payment_data_param['shippingOptionData']->id;
                    $shipping_instance_id = substr( $shipping_name, strpos( $shipping_name, ':' ) + 1, strlen( $shipping_name ) );
                    if ( false !== strpos( $shipping_name, $shipping_method->id ) && $shipping_method->instance_id == $shipping_instance_id ) {
                        $item = new WC_Order_Item_Shipping();
                        $item->set_method_title( $shipping_method->method_title );
                        $item->set_method_id( $payment_data_param['shippingOptionData']->id );
                        $item->set_total( $shipping_method->cost );

                        $order->add_item( $item );
                        $check_shipping = true;
                        break;
                    }
                }
                if ( $check_shipping ) {
                    break;
                }
            }
        }

        $order->calculate_totals();
        $submit_result = $helper->submit_googlepay_order( $order, $payment_data_param, $tds );

        if ( isset( WC()->cart ) ) {
            WC()->cart->empty_cart();
        }
        if ( 2 == $submit_result ) {
            $url = $order->get_checkout_payment_url( true );
        } else {
            $url = $order->get_checkout_order_received_url();
        }

        wp_die( 'returnUrl' . esc_html( $url ) );
    }

    /**
     * Create order through ajax
     *
     * @throws Exception Exception $exception.
     */
    public function create_order_applepay() {
        $payment_data_param    = (array) json_decode( html_entity_decode( stripslashes( $_POST['paymentData'] ) ) );
        $shipping_method_param = ! empty( $_POST['shippingMethod'] ) ? $_POST['shippingMethod'] : '';
        $order          = wc_create_order();
        $helper      = new WC_Acquired_Helper();
        $wc_apple_payment = new WC_Apple_Payment();
        $fees = !empty($_POST['fees']) ? $_POST['fees'] : '';
        if ( ! empty( $_POST['productData'] ) ) {
            if ( ! empty( $_POST['productGroupData'] ) ) {
                foreach ( $_POST['productGroupData'] as $product ) {
                    $order->add_product( wc_get_product( $product['product_id'] ), $product['product_quantity'] );
                }
            } else {
                if ( empty( $_POST['variation_id'] ) ) {
                    $order->add_product( wc_get_product( $_POST['productData'] ), $_POST['productQty'] );
                } else {
                    $order->add_product( wc_get_product( $_POST['variation_id'] ), $_POST['productQty'] );
                }
            }
        } else {
            $cart = WC()->cart;
            foreach ( $cart->get_cart() as $cart_item ) {
                $default_args = array(
                    'variation_id' => $cart_item['variation_id'],
                    'variation'    => $cart_item['variation'],
                );
                $order->add_product( wc_get_product( $cart_item['product_id'] ), $cart_item['quantity'], $default_args );
            }

            /* Add Fee */
            if ( !empty($fees) ) {
                foreach ( $fees as $fee ) {
                    $orderFee= (object) $fee;
                    $order->add_fee($orderFee);
                }
            }
        }

        if ( !empty($payment_data_param['billingContact'] ) ) {
            if ( !empty($payment_data_param['billingContact']->emailAddress) ) {
                $email = $payment_data_param['billingContact']->emailAddress;
            } elseif ( !empty($payment_data_param['shippingContact']->emailAddress) ) {
                $email = $payment_data_param['shippingContact']->emailAddress;
            }

            if ( !empty($payment_data_param['billingContact']->phoneNumber) ) {
                $phone = $payment_data_param['billingContact']->phoneNumber;
            } elseif ( !empty($payment_data_param['shippingContact']->phoneNumber) ) {
                $phone = $payment_data_param['shippingContact']->phoneNumber;
            }

            $address_billing = array(
                'first_name' => $payment_data_param['billingContact']->givenName ?: '',
                'last_name'  => $payment_data_param['billingContact']->familyName  ?: '',
                'address_1'  => $payment_data_param['billingContact']->addressLines[0] ?: '',
                'address_2'  => $payment_data_param['billingContact']->addressLines[1] ?: '',
                'city'       => $payment_data_param['billingContact']->locality ?: '',
                'state'      => $payment_data_param['billingContact']->administrativeArea ?: '',
                'postcode'   => $payment_data_param['billingContact']->postalCode ?: '',
                'country'    => !empty($payment_data_param['billingContact']->countryCode) ? strtoupper($payment_data_param['billingContact']->countryCode) : '',
                'email'      => $email ?: '',
                'phone'      => $phone ?: '',
            );

            $order->set_address( $address_billing, 'billing' );
        }

        if ( !empty($payment_data_param['shippingContact'] ) ) {
            $address_shipping = array(
                'first_name' => $payment_data_param['shippingContact']->givenName ?: '',
                'last_name'  => $payment_data_param['shippingContact']->familyName ?: '',
                'address_1'  => $payment_data_param['shippingContact']->addressLines[0] ?: '',
                'address_2'  => $payment_data_param['shippingContact']->addressLines[1] ?: '',
                'city'       => $payment_data_param['shippingContact']->locality ?: '',
                'state'      => $payment_data_param['shippingContact']->administrativeArea ?: '',
                'postcode'   => $payment_data_param['shippingContact']->postalCode ?: '',
                'country'    => !empty($payment_data_param['shippingContact']->countryCode) ? strtoupper($payment_data_param['shippingContact']->countryCode) : '',
                'email'      => $payment_data_param['shippingContact']->emailAddress ?: '',
                'phone'      => $payment_data_param['shippingContact']->phoneNumber ?: ''
            );

            $order->set_address( $address_shipping, 'shipping' );
            /* Check case billing empty */
            if ( empty($payment_data_param['billingContact'] ) ) {
                $order->set_address( $address_shipping, 'billing' );
            }
        }

        $order->set_payment_method( $wc_apple_payment->id );
        $order->set_payment_method_title( $wc_apple_payment->title );

        if ( is_user_logged_in() ) {
            update_post_meta($order->get_id(), '_customer_user', get_current_user_id());
        }

        if ( 'true' == $_POST['needShipping'] ) {
            $zones          = WC_Shipping_Zones::get_zones();
            if ( !empty($zones) ) {
                $check_shipping = false;
                foreach ( $zones as $zone ) {
                    foreach ( $zone['shipping_methods'] as $shipping_method ) {
                        $shipping_instance_id = substr( $shipping_method_param, strpos( $shipping_method_param, ':' ) + 1, strlen( $shipping_method_param ) );
                        if ( false !== strpos( $shipping_method_param, $shipping_method->id ) && $shipping_method->instance_id == $shipping_instance_id ) {
                            $item = new WC_Order_Item_Shipping();
                            $item->set_method_title( $shipping_method->method_title );
                            if ( !empty($payment_data_param['shippingOptionData']) ) {
                                $item->set_method_id( $payment_data_param['shippingOptionData']->id );
                            }
                            if ( !empty($shipping_method->cost) ) {
                                $item->set_total( $shipping_method->cost );
                            }
                            $order->add_item( $item );
                            $check_shipping = true;
                            break;
                        }
                    }
                    if ( $check_shipping ) {
                        break;
                    }
                }
            }
        }

        $has_coupons = count(WC()->cart->applied_coupons)>0?true:false;
        if($has_coupons) {
            foreach (WC()->cart->get_applied_coupons() as $key => $value) {
                $order->apply_coupon($value);
            }
        }
        $order->calculate_totals();
        $helper->submit_applepay_order( $order, $payment_data_param );

        if ( isset( WC()->cart ) ) {
            WC()->cart->empty_cart();
        }
        $url = $order->get_checkout_order_received_url();

        wp_die( 'returnUrl' . esc_html( $url ) );
    }

    /**
     * Get merchantSession for Apple Pay
     *
     * @throws Exception Exception $exception.
     */
    public function get_apple_merchant_session() {
        $qa_endpoint         = 'https://qaapi.acquired.com/api.php/status';
        $production_endpoint = 'https://gateway.acquired.com/api.php/status';
        $apple_payment       = new WC_Apple_Payment();
        $debug_mode          = 1 == $apple_payment->debug_mode;
        $site_url            = parse_url(get_home_url());
        $domain              = $site_url['host'];
        $data                = array(
            'timestamp'      => strftime( '%Y%m%d%H%M%S' ),
            'company_id'     => $apple_payment->merchant_id,
            'company_pass'   => $apple_payment->company_pass,
        );

        $validation_url = 'live' == $apple_payment->mode ? 'https://apple-pay-gateway.apple.com/paymentservices/paymentSession'
            : 'https://apple-pay-gateway.apple.com/paymentservices/startSession';
        $transaction_info = array(
            'status_request_type' => 'APPLE_SESSION',
            'domain'              => $domain,
            'display_name'        => 'AcquiredPayment',
            'validation_url'      => $validation_url,
        );
        $request_hash_string  = $data['timestamp'] . $transaction_info['status_request_type'] . $data['company_id'] .
            $apple_payment->company_hashcode;
        $data['request_hash'] = hash( 'sha256', $request_hash_string );
        $data['transaction']  = $transaction_info;

        $path = 'sandbox' == $apple_payment->mode ? $qa_endpoint : $production_endpoint;
        $apple_payment->_helper->acquired_logs( 'get_apple_merchant_session data', $debug_mode );
        $apple_payment->_helper->acquired_logs( $data, $debug_mode );
        $merchant_session_response = $apple_payment->_helper->acquired_api_request( $path, 'POST', $data );
        $apple_payment->_helper->acquired_logs( 'get_apple_merchant_session response', $debug_mode );
        $apple_payment->_helper->acquired_logs( $merchant_session_response, $debug_mode );

        if ( is_array( $merchant_session_response ) && isset( $merchant_session_response['response_code'] ) ) {
            if ( '1' == $merchant_session_response['response_code'] ) {
                $merchant_session = $merchant_session_response['merchant_session'];
                die( 'merchantSession' . $merchant_session );
            }
        } else {
            wp_die( false );
        }
    }

    /**
     * When a subscriber's billing or shipping address is successfully updated, check if the subscriber
     * has also requested to update the addresses on existing subscriptions and if so, go ahead and update
     * the addresses on the initial order for each subscription.
     *
     * @param int   $user_id The ID of a user who own's the subscription (and address).
     *
     * @param mixed $address_type Address type.
     *
     * @throws Exception Exception $exception.
     * @since 1.3
     */
    public function update_billing_address( $user_id, $address_type ) {
        if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
            if ( isset( $_POST['update_all_subscriptions_addresses'] ) && 'billing' == $address_type ) {
                $users_subscriptions   = wcs_get_users_subscriptions( $user_id );
                $helper                = new WC_Acquired_Helper();
                $acquired_payment_info = new WC_Acquired_Payment();

                foreach ( $users_subscriptions as $subscription ) {
                    if ( $subscription->has_status( array( 'active', 'on-hold' ) ) ) {
                        $helper->acquired_update_billing( $subscription, $_POST, $acquired_payment_info );
                    }
                }
            }
        }
    }

    /**
     * Send update billing request when admin update order
     *
     * @param mixed $post_id Post ID.
     * @param mixed $post Post instance.
     *
     * @throws Exception Exception $exception.
     */
    public function admin_update_billing_address( $post_id, $post ) {
        if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
            $billing_fields = array(
                '_billing_address_1',
                '_billing_address_2',
                '_billing_city',
                '_billing_postcode',
                '_billing_country',
                '_billing_state',
                '_billing_email',
                '_billing_phone',
            );
            foreach ( $billing_fields as $billing_field ) {
                $current_billing_meta = get_post_meta( $post_id, $billing_field, true );
                if ( $_POST[ $billing_field ] != $current_billing_meta ) {
                    $wc_order = wc_get_order( $post_id );
                    if ( 'acquired' == $wc_order->get_payment_method() ) {
                        $helper                = new WC_Acquired_Helper();
                        $acquired_payment_info = new WC_Acquired_Payment();

                        $helper->acquired_update_billing( $wc_order, $_POST, $acquired_payment_info );
                    }
                }
            }
        }
    }

    /**
     * Validate timezone for address from Apple
     */
    public function validate_time_zone() {
        $zones           = WC_Shipping_Zones::get_zones();
        $country_code     = $_POST['countryCode'];
        $continent       = ( new WC_Countries() )->get_continent_code_for_country( $country_code );
        $shipping_option = array();

        foreach ( $zones as $zone ) {
            foreach ( $zone['zone_locations'] as $zone_location ) {
                if ( $country_code == $zone_location->code || $continent == $zone_location->code ) {
                    if ( empty( $zone['shipping_methods'] ) ) {
                        $shipping_option = array(
                            'no_validate' => 'No timezone found',
                        );
                    } else {
                        foreach ( $zone['shipping_methods'] as $shipping_method ) {
                            if ( ! empty( $shipping_method->id ) ) {
                                $cost = (float) ! empty( $shipping_method->cost ) ? $shipping_method->cost : '0.00';
                                array_push(
                                    $shipping_option,
                                    array(
                                        'identifier' => $shipping_method->id . ':' . $shipping_method->instance_id,
                                        'label'      => $shipping_method->title . ': ' . $cost . ' ' . get_woocommerce_currency(),
                                        'detail'     => number_format( $cost, 2 ),
                                        'amount'     => number_format( $cost, 2 ),
                                    )
                                );
                            }
                        }
                    }
                }
            }
        }

        if ( empty( $zones ) ) {
            $shipping_option = array(
                'no_validate' => 'No timezone found',
            );
        } elseif ( array() == $shipping_option ) {
            $zones = array();
            $zones[] = new WC_Shipping_Zone( 0 ); // ADD ZONE "0" MANUALLY

            foreach ( $zones as $zone ) {
                $zone_shipping_methods = $zone->get_shipping_methods();
            }

            foreach ($zone_shipping_methods as $key => $value) {
                $cost = (float) ! empty( $value->cost ) ? $value->cost : '0.00';
                array_push(
                    $shipping_option,
                    array(
                        'identifier' => $value->id . ':' . $value->instance_id,
                        'label'      => $value->title . ': ' . $cost . ' ' . get_woocommerce_currency(),
                        'detail'     => number_format( $cost, 2 ),
                        'amount'     => number_format( $cost, 2 ),
                    )
                );
            }
        }

        $shipping_option = array_unique( $shipping_option, SORT_REGULAR );

        wp_die( json_encode( $shipping_option ) );
    }

    /**
     * Add admin notice when activated plugin
     */
    public function add_notice_when_activated() {
        if ( get_option( 'acquired_active_status' ) != 'active' && is_plugin_active( 'acquired-payment/acquired-payment.php' ) ) {
            $settings_url = add_query_arg(
                array(
                    'page' => 'wc-settings',
                    'tab'  => 'checkout',
                ),
                admin_url( 'admin.php' )
            );

            ?>
            <div id="acquired-notice-setup" class="updated woocommerce-message is-dismissible" style="border-left-color: #a36597 !important;">
                <p><b>Acquired Payment Gateway is installed.</b></p>
                <p>
                    <?php esc_html_e( 'Thanks for using Acquired\'s payment gateway for WooCommerce - you\'re almost ready to start accepting payments.', 'acquired-payment' ); ?>
                </p>
                <p class="submit">
                    <a href="<?php echo esc_url( $settings_url ); ?>" class="button-primary debug-report" style="background: #a36597 !important;"><?php esc_html_e( 'Complete setup', 'acquired-payment' ); ?></a>
                    <a class="button-secondary docs" href="#" onclick="disable_notice()">
                        <?php esc_html_e( 'Skip Setup', 'acquired-payment' ); ?>
                    </a>
                </p>
            </div>
            <script>
                function disable_notice() {
                    jQuery('#acquired-notice-setup').remove();
                }
            </script>
            <?php
            update_option( 'acquired_active_status', 'active' );
        }
    }

    /**
     * Modify Acquired payment method
     *
     * @param mixed $methods Payment methods.
     *
     * @return mixed
     */
    public function woocommerce_acquired_modify_payment_method() { ?>
        <script>
            window.addEventListener("message", function (event) {
                if ( event.data.response ) {
                    let response_code = event.data.response.code;
                    if (response_code === "1") {
                        window.location.href = "<?php echo esc_html( wc_get_account_endpoint_url( 'payment-methods' ) . '?transaction_card=' ); ?>" + event.data.response.transaction_id;
                    } else {
                        window.location.href = "<?php echo esc_html( wc_get_account_endpoint_url( 'payment-methods' ) . '?response_code=' ); ?>" + response_code;
                    }
                }
            });
        </script>
    <?php }

    /**
     * Set up Acquired plugin links
     *
     * @param mixed $links Plugin links.
     *
     * @return array
     */
    public function woocommerce_acquired_plugin_links( $links ) {
        $settings_url = add_query_arg(
            array(
                'page'    => 'wc-settings',
                'tab'     => 'checkout',
                'section' => 'acquired',
            ),
            admin_url( 'admin.php' )
        );

        $plugin_links = array(
            '<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'acquired-payment' ) . '</a>',
            '<a href="https://acquired.com/">' . __( 'Support', 'acquired-payment' ) . '</a>',
        );

        return array_merge( $plugin_links, $links );
    }

    /**
     * Rename Mastercard in My Account
     *
     * @param mixed $links Plugin links.
     *
     * @return array
     */
    public function woocommerce_rename_mastercard( $labels ) {
        if ( 'Mc' === $labels ) {
            return __( 'Mastercard', 'acquired-payment' );
        }

        return $labels;
    }

    /**
     * Fixed conflix LTY_Lottery_Cart
     *
     * @param mixed $links Plugin links.
     *
     * @return array
     */
    public function woocommerce_remove_lottery() {
        if (class_exists('LTY_Lottery_Cart')) {
            remove_action( 'woocommerce_check_cart_items', array( 'LTY_Lottery_Cart', 'check_cart_items' ), 1 );
        }
    }

    /**
     * Google Pay Process Payment
     *
     * @return array
     */
    public function googlepay_process_payment() {
        // Verify nonce.
        check_ajax_referer( 'googlepay_process_create_nonce', 'googlepay_process_nonce' );

        $order_id = (!empty($_POST['order_id'])) ? (int) $_POST['order_id'] : 0;
        $gpay_token_response = (!empty($_POST['gpay_token_response'])) ? $_POST['gpay_token_response'] : '';
        $paymentDisplayName = (!empty($_POST['paymentDisplayName'])) ? $_POST['paymentDisplayName'] : '';
        $paymentNetwork = (!empty($_POST['paymentNetwork'])) ? $_POST['paymentNetwork'] : '';
        $tds = (!empty($_POST['tds'])) ? (array) json_decode( html_entity_decode( stripslashes( $_POST['tds'] ) ) ) : '';
        $order = wc_get_order( $order_id );
        $billing_address_1 = $order->get_billing_address_1();
        $billing_address_2 = $order->get_billing_address_2();
        $billing_city = $order->get_billing_city();
        $billing_state = $order->get_billing_state();
        $billing_postcode = $order->get_billing_postcode();
        $billing_phone = $order->get_billing_phone();
        $billing_email = $order->get_billing_email();
        $billing_country = $order->get_billing_country();
        $helper = new WC_Acquired_Helper();
        $WC_Google_Payment = new WC_Google_Payment();

        $transaction_info = array(
            'merchant_order_id'  => $order_id,
            'transaction_type'   => $WC_Google_Payment->transaction_type,
            'amount'             => $order->get_total(),
            'currency_code_iso3' => $order->get_currency(),
        );
        $payment_info     = array(
            'method'       => 'google_pay',
            'token'        => $gpay_token_response,
            'display_name' => $paymentDisplayName,
            'network'      => $paymentNetwork
        );

        if ( !empty($billing_phone) ) {
            $billing_phone = $_POST['billing_phone'];
            $billing_phone = str_replace( '+', '', $billing_phone );
            $billing_phone = str_replace( '-', '', $billing_phone );
            $billing_phone = str_replace( ' ', '', $billing_phone );
        }

        $billing_info     = array(
            'billing_street'            => $billing_address_1 ?: '',
            'billing_street2'           => $billing_address_2 ?: '',
            'billing_city'              => $billing_city ?: '',
            'billing_state'             => $billing_state ?: '',
            'billing_zipcode'           => $billing_postcode ?: '',
            'billing_country_code_iso2' => $billing_country ?: '',
            'billing_phone'             => $billing_phone ?: '',
            'billing_email'             => $billing_email ?: '',
        );
        $billing_info = array_filter($billing_info);

        $data = array(
            'timestamp'            => strftime( '%Y%m%d%H%M%S' ),
            'company_id'           => $WC_Google_Payment->gateway_merchant_id,
            'company_pass'         => $WC_Google_Payment->company_pass,
            'transaction'          => $transaction_info,
            'payment'              => $payment_info,
            'merchantId'           => $WC_Google_Payment->google_merchant_id
        );

        $request_hash         = $helper->request_hash(
            array_merge( $transaction_info, $payment_info, $data ),
            $WC_Google_Payment->company_hashcode
        );
        $data['request_hash'] = $request_hash;
        $data['billing']      = $billing_info;

        if ( 1 == $WC_Google_Payment->tds_action ) {
            $data['tds']['action'] = 'ENQUIRE';
        } elseif ( 2 == $WC_Google_Payment->tds_action ) {
            $data['tds']['action']                                = 'SCA';
            $data['tds']['source']                                = '1';
            $data['tds']['type']                                  = '2';
            $data['tds']['preference']                            = '0';
            $data['tds']['method_url_complete']                   = '1';
            $data['tds']['browser_data']                          = (array) $tds['browser_data'];
            $data['tds']['browser_data']['ip']                    = $helper->get_client_ip();
            $data['tds']['browser_data']['color_depth']           = $helper->mapping_color_depth( (string) $data['tds']['browser_data']['color_depth'] );
            $data['tds']['browser_data']['challenge_window_size'] = $WC_Google_Payment->challenge_window_size;
            $data['tds']['merchant']['contact_url']               = site_url( 'contact' );
            $data['tds']['merchant']['challenge_url']             = site_url( 'wc-api/wc_google_payment/?order_id=' . $order_id );
        }

        $path = 'sandbox' == $WC_Google_Payment->mode ? $WC_Google_Payment->api_endpoint_sandbox : $WC_Google_Payment->api_endpoint_live;
        $helper->acquired_logs( 'process_payment Google Pay data', $WC_Google_Payment->debug_mode );
        $helper->acquired_logs( $data, $WC_Google_Payment->debug_mode );
        $payment_submit_response = $helper->acquired_api_request( $path, 'POST', $data );
        $helper->acquired_logs( 'process_payment Google Pay response', $WC_Google_Payment->debug_mode );
        $helper->acquired_logs( $payment_submit_response, $WC_Google_Payment->debug_mode );
        $redirectUrl = esc_url( wc_get_checkout_url() );

        if ( is_array( $payment_submit_response ) && isset( $payment_submit_response['response_code'] ) ) {
            if ( isset( $payment_submit_response['transaction_id'] ) ) {
                $order->set_transaction_id( $payment_submit_response['transaction_id'] );
                $order->save();
            }
            if ( '1' == $payment_submit_response['response_code'] ) {
                $message = sprintf(
                    __( 'Google Pay (Acquired Payments) charge successfully %1$s (Transaction ID: %2$s)', 'acquired-payment' ),
                    wc_price( $order->get_total() ),
                    $payment_submit_response['transaction_id']
                );
                $order->add_order_note( $message );
                $helper->set_status_order_acquired( $order );
                if ( isset( WC()->cart ) ) {
                    WC()->cart->empty_cart();
                }
                $redirectUrl = $order->get_checkout_order_received_url();
            } elseif ( '501' == $payment_submit_response['response_code'] || '503' == $payment_submit_response['response_code'] ) {
                $message = sprintf(
                    __( 'Google Pay (Acquired Payments) create transaction successfully %1$s (Transaction ID: %2$s)', 'acquired-payment' ),
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
                $redirectUrl = $order->get_checkout_order_received_url();
            } else {
                $message = __(
                        'Cannot submit order to Acquired. Code ',
                        'acquired-payment'
                    ) . $payment_submit_response['response_code'] . ' - ' . $payment_submit_response['response_message'];
                $order->add_order_note( $message );
                wc_add_notice( __( 'Acquired Payments: Cannot submit order to Acquired. Code ', 'acquired-payment' ).$payment_submit_response['response_code'].' - '.$payment_submit_response['response_message'], 'error' );
            }
        } else {
            wc_add_notice( __( 'Acquired Payments: Something went wrong.', 'acquired-payment' ) );
        }

        $output = array(
            'result'   => 'success',
            'redirect' => $redirectUrl
        );

        echo json_encode($output);
        wp_die();
    }

    /**
     * Google Pay Process Payment
     *
     * @return array
     */
    public function apple_process_payment() {
        // Verify nonce.
        check_ajax_referer( 'applepay_process_create_nonce', 'applepay_process_nonce' );

        // $order_id = (!empty($_POST['order_id'])) ? (int) $_POST['order_id'] : 0;
        $order_id = (int)WC()->session->get('order_awaiting_payment');
        $apple_token_response = (!empty($_POST['apple_token_response'])) ? $_POST['apple_token_response'] : '';
        $paymentDisplayName = (!empty($_POST['paymentDisplayName'])) ? $_POST['paymentDisplayName'] : '';
        $paymentNetwork = (!empty($_POST['paymentNetwork'])) ? $_POST['paymentNetwork'] : '';
        $helper = new WC_Acquired_Helper();
        $WC_Apple_Payment = new WC_Apple_Payment();
        $order = wc_get_order( $order_id );
        $billing_address_1 = $order->get_billing_address_1();
        $billing_address_2 = $order->get_billing_address_2();
        $billing_city = $order->get_billing_city();
        $billing_state = $order->get_billing_state();
        $billing_postcode = $order->get_billing_postcode();
        $billing_phone = $order->get_billing_phone();
        $billing_email = $order->get_billing_email();
        $billing_country = $order->get_billing_country();
        $merchant_custom_2 = $order->get_meta('merchant_custom_2');
        $merchant_custom_3 = ! empty (get_option('woocommerce_general_settings') ) ? get_option( 'woocommerce_general_settings' )[ 'dynamic_descriptor' ] : '';

        $transaction_info = array(
            'merchant_order_id'  => $order_id,
            'transaction_type'   => $WC_Apple_Payment->transaction_type,
            'amount'             => $order->get_total(),
            'currency_code_iso3' => $order->get_currency(),
            'merchant_custom_1'    => (string) $order_id,
            'merchant_custom_2'    => (string) $merchant_custom_2,
            'merchant_custom_3'    => (string) $merchant_custom_3,
        );
        $payment_info     = array(
            'method'       => 'apple_pay',
            'token'        => $apple_token_response,
            'display_name' => $paymentDisplayName,
            'network'      => $paymentNetwork,
        );
        if ( !empty($billing_phone) ) {
            $billing_phone = $_POST['billing_phone'];
            $billing_phone = str_replace( '+', '', $billing_phone );
            $billing_phone = str_replace( '-', '', $billing_phone );
            $billing_phone = str_replace( ' ', '', $billing_phone );
        }

        $billing_info     = array(
            'billing_street'            => $billing_address_1 ?: '',
            'billing_street2'           => $billing_address_2 ?: '',
            'billing_city'              => $billing_city ?: '',
            'billing_state'             => $billing_state ?: '',
            'billing_zipcode'           => $billing_postcode ?: '',
            'billing_country_code_iso2' => $billing_country ?: '',
            'billing_phone'             => $billing_phone ?: '',
            'billing_email'             => $billing_email ?: '',
        );
        $billing_info = array_filter($billing_info);

        $data = array(
            'timestamp'    => strftime( '%Y%m%d%H%M%S' ),
            'company_id'   => $WC_Apple_Payment->merchant_id,
            'company_pass' => $WC_Apple_Payment->company_pass,
            'transaction'  => $transaction_info,
            'payment'      => $payment_info,
        );

        $request_hash         = $helper->request_hash(
            array_merge( $transaction_info, $payment_info, $data ),
            $WC_Apple_Payment->company_hashcode
        );
        $data['request_hash'] = $request_hash;
        $data['billing']      = $billing_info;

        $path = 'sandbox' == $WC_Apple_Payment->mode ? $WC_Apple_Payment->api_endpoint_sandbox : $WC_Apple_Payment->api_endpoint_live;
        $helper->acquired_logs( 'process_payment Apple Pay data', $WC_Apple_Payment->debug_mode );
        $helper->acquired_logs( $data, $WC_Apple_Payment->debug_mode );
        $payment_submit_response = $helper->acquired_api_request( $path, 'POST', $data );
        $helper->acquired_logs( 'process_payment Apple Pay response', $WC_Apple_Payment->debug_mode );
        $helper->acquired_logs( $payment_submit_response, $WC_Apple_Payment->debug_mode );
        $redirectUrl = esc_url( wc_get_checkout_url() );

        if ( is_array( $payment_submit_response ) && isset( $payment_submit_response['response_code'] ) ) {
            if ( isset( $payment_submit_response['transaction_id'] ) ) {
                $order->set_transaction_id( $payment_submit_response['transaction_id'] );
            }
            if ( '1' == $payment_submit_response['response_code'] ) {
                $message = sprintf(
                    __( 'Apple Pay (Acquired Payments) charge successfully %s', 'acquired-payment' ),
                    wc_price( $order->get_total() )
                );
                $order->add_order_note( $message );
                $helper->set_status_order_acquired( $order );
                if ( isset( WC()->cart ) ) {
                    WC()->cart->empty_cart();
                }
                $redirectUrl = $order->get_checkout_order_received_url();
            } else {
                $message = __(
                        'Cannot submit order to Acquired. Code ',
                        'acquired-payment'
                    ) . $payment_submit_response['response_code'] . ' - ' . $payment_submit_response['response_message'];
                $order->add_order_note( $message );
                wc_add_notice( __( 'Acquired Payments: Cannot submit order to Acquired. Code ', 'acquired-payment' ).$payment_submit_response['response_code'].' - '.$payment_submit_response['response_message'], 'error' );
            }
        } else {
            wc_add_notice( __( 'Acquired Payments: Something went wrong.', 'acquired-payment' ) );
        }

        $output = array(
            'result'   => 'success',
            'redirect' => $redirectUrl
        );

        echo json_encode($output);
        wp_die();
    }

    /**
     * @return void
     */
    public function acquired_remove_old_data_order() {
        global $wpdb;
        $acquired_transactions = $wpdb->get_var("SELECT EXISTS(SELECT 1 FROM {$wpdb->posts} WHERE post_type = 'acquired_transaction')");
        if ( $acquired_transactions ) {
            global $wpdb;
            $sql = "DELETE p, pm FROM {$wpdb->prefix}posts as p LEFT JOIN {$wpdb->prefix}postmeta as pm ON p.ID = pm.post_id WHERE p.post_type = 'acquired_transaction'";
            $wpdb->query($sql);
        } else {
            return;
        }
    }

    /**
     * Create cron jobs (clear them first).
     */
    public function acquired_create_schedule_query_api_pending_order( $order_id ) {
        $acquiredGeneralSettings = get_option('woocommerce_general_settings');
        if ( !array_key_exists('accquired_query_order', $acquiredGeneralSettings) ||
            ( array_key_exists('accquired_query_order', $acquiredGeneralSettings) && 'no' === $acquiredGeneralSettings['accquired_query_order']  )
        ) {
            return;
        }

        $order = wc_get_order( $order_id );

        if ( 'pending' === $order->get_status() && 'acquired' === $order->get_payment_method() ) {
            wp_schedule_single_event( time() + 120, 'acquired_query_api_pending_order', array( $order_id ) );
        }
    }

    /**
     * @param $order_id
     * @return void
     */
    public function acquired_handle_query_api_for_order( $order_id ) {
        $order = wc_get_order( $order_id );
        $order_time = strtotime( $order->get_date_created() );
        $held_duration = !empty(get_option( 'woocommerce_hold_stock_minutes' )) ? get_option( 'woocommerce_hold_stock_minutes' ) : 15;
        $cancel_unpaid_interval = apply_filters( 'woocommerce_cancel_unpaid_orders_interval_minutes', absint( $held_duration ) );
        $cancel_time = $order_time + ( $cancel_unpaid_interval * 60 );
        $helper = new WC_Acquired_Helper();
        $acquiredPayment = new WC_Acquired_Payment();
        $debugMode = $acquiredPayment->debug_mode;

        if ( time() > $cancel_time ) {
            wp_clear_scheduled_hook( 'acquired_query_api_pending_order', array( $order_id ) );
            return;
        }

        $api_result = $this->acquired_query_api_for_order( $order_id );

        if ( '1' !== $api_result['result_code'] ) {
            $error_message = __( 'Acquired Payments error: Error code ' ).$api_result['result_code'];
            $order->add_order_note( $error_message );
            $order->update_status( 'failed' );
            $helper->acquired_logs( 'Cron cleared because error when query order.', $debugMode );
            $helper->acquired_logs( $api_result, $debugMode );
            return;
        }

        if ( $this->acquired_continue_schedule( $api_result ) ) {
            wp_schedule_single_event( time() + 30, 'acquired_query_api_pending_order', array( $order_id ) );
        } else {
            wp_clear_scheduled_hook( 'acquired_query_api_pending_order', array( $order_id ) );
            $orderUpdate = $api_result['transaction'];
            $orderUpdate['code'] = $orderUpdate['response_code'];
            $orderUpdate['hash'] = $api_result['response_hash'];
            $helper->acquired_logs( 'Schedule update order via query API', $debugMode );
            $helper->acquired_logs( $api_result, $debugMode );
            $helper->process_response( $order_id, $orderUpdate );
        }
    }

    /**
     * @param $api_result
     * @return bool
     */
    public function acquired_continue_schedule( $api_result ) {
        if (
            0 == $api_result['total_transaction'] ||
            ( 0 < $api_result['total_transaction'] && '503' == $api_result['transaction']['response_code'] )
        ) {
            $helper = new WC_Acquired_Helper();
            $acquiredPayment = new WC_Acquired_Payment();
            $debugMode = $acquiredPayment->debug_mode;
            $helper->acquired_logs( 'Continue scheduling order updates. Response: ', $debugMode );
            $helper->acquired_logs( $api_result, $debugMode );
            return true;
        }
        return false;
    }

    /**
     * @param $orderId
     * @return false|mixed
     */
    public function acquired_query_api_for_order( $orderId ) {
        try {
            $helper = new WC_Acquired_Helper();
            $acquiredPayment = new WC_Acquired_Payment();
            $qaEndpoint = 'https://qaapi.acquired.com/api.php/status';
            $productionEndpoint = 'https://gateway.acquired.com/api.php/status';
            $endpointQuery = 'live' == $acquiredPayment->mode ? $productionEndpoint : $qaEndpoint;
            $companyId = $acquiredPayment->company_id;
            $companyPass = $acquiredPayment->company_pass;
            $companyHashCode = $acquiredPayment->company_hashcode;
            $dataQuery = [
                'timestamp' => date('YmdHis'),
                'company_id' => $companyId,
                'company_pass' => $companyPass
            ];
            $transaction = [
                'status_request_type' => 'ORDER_ID_LAST',
                'merchant_order_id' => $orderId
            ];

            $requestHash = $helper->query_request_hash(array_merge($dataQuery, $transaction), $companyHashCode);
            $dataQuery['request_hash'] = $requestHash;
            $dataQuery['transaction'] = $transaction;

            $result = $helper->acquired_api_request($endpointQuery, 'POST', $dataQuery);

            return $result;
        } catch (\Throwable $exception) {
            return false;
        }
    }
}