<?php
/**
 * Apple Pay button in Cart
 *
 * @package acquired-payment/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
    <style>
        apple-pay-button {
            --apple-pay-button-height: <?php echo $apple_pay_instance->get_option( 'button_height', '30' ); ?>px;
            --apple-pay-button-border-radius: 3px;
            --apple-pay-button-padding: 0px 0px;
            --apple-pay-button-box-sizing: border-box;
        }
    </style>
    <div class="wc-acquired-cart-checkout-container cart_apple_pay"
        <?php
        if ( $cart_total == 0 ) {
            ?>
            style="display: none"<?php } ?>>
        <ul class="wc_acquired_cart_payment_methods" style="list-style: none; width: 100%; text-align: center; margin: 0;">
            <?php if ( $after ) : ?>
                <li class="wc-acquired-payment-method or">
                    <p class="wc-acquired-cart-or-apple">
                    </p>
                </li>
                <li
                        class="wc-acquired-payment-method payment_method_<?php echo $gateways; ?>">
                    <div class="payment-box">
                        <div id="wc-acquired-<?php echo $gateways; ?>-container">
                            <apple-pay-button buttonstyle="<?php echo $apple_pay_instance->get_option( 'button_style' ); ?>" type="<?php echo $apple_pay_instance->get_option( 'button_type' ); ?>"  locale="en" onclick="applePayButtonClicked()"></apple-pay-button>
                        </div>
                        <input type="hidden" name="applepay_token_response" id="applepay_token_response"/>
                        <input type="hidden" name="paymentDisplayName" id="paymentDisplayName"/>
                        <input type="hidden" name="paymentNetwork" id="paymentNetwork"/>
                        <input type="hidden" name="applepay_shipping_method" id="applepay_shipping_method"/>
                    </div>
                    <?php echo $apple_pay_instance->output_display_items( 'cart' ); ?>
                </li>
            <?php endif; ?>
        </ul>
    </div>
    <script>
        jQuery('apple-pay-button').css("--apple-pay-button-width", jQuery('.wc-proceed-to-checkout').width().toString() + 'px');
        /**
         * @returns {jQuery}
         */
        function get_gateway_data() {
            let container = 'li.payment_method_apple';
            let data = jQuery(container).find(".woocommerce_apple_gateway_data").data('gateway');

            return data;
        }

        function applePayButtonClicked(event) {
            let gateway_data = get_gateway_data();
            let countryCode = gateway_data.country ? gateway_data.country.toUpperCase() : null;
            let amount = gateway_data.total;
            let currencyCode = gateway_data.currency;
            let all_shipping_methods = gateway_data.shipping_options;

            let request = {
                countryCode: countryCode,
                currencyCode: currencyCode,
                supportedNetworks: ['visa', 'masterCard', 'amex'],
                merchantCapabilities: ['supports3DS'],
                total: {label: 'Total Amount: ', amount: amount},
            }

            if (gateway_data.needs_shipping === true) {
                request.requiredShippingContactFields = ['email', 'name', 'phone', 'postalAddress'];
                request.shippingContactEditingMode = 'enabled';
                request.shippingType = 'shipping';
                request.shippingMethods = gateway_data.shipping_options;
            } else {
                request.requiredBillingContactFields = ['email', 'name', 'phone', 'postalAddress'];
                request.requiredShippingContactFields = ['email', 'phone'];
            }

            let session = new ApplePaySession(3, request);

            session.onvalidatemerchant = function (event) {
                let promise = performValidation(event.validationURL);

                promise.then(function (merchantSession) {
                    session.completeMerchantValidation(merchantSession);
                });
            }

            session.onshippingcontactselected = function (event) {
                let shippingContact = event.shippingContact;
                let promise = validateTimeZone(shippingContact.administrativeArea, shippingContact.countryCode, shippingContact.postalCode);

                promise.then(function (validateTimeZones) {
                    let errors_shipping;
                    let shipping_methods;
                    let total = parseFloat(amount) + parseFloat(all_shipping_methods[0].amount);
                    total = total.toFixed(2);
                    let newTotal = {type: 'final', label: 'TOTAL', amount: total};
                    let newLineItems = [
                        {type: 'final', label: 'Cart Total', amount: amount},
                        {type: 'final', label: 'Shipping Fee', amount: all_shipping_methods[0].amount}
                    ];

                    if (typeof validateTimeZones.no_validate !== 'undefined') {
                        shipping_methods = [];
                        errors_shipping = [];
                    } else if (typeof validateTimeZones.no_shipping_available !== 'undefined') {
                        shipping_methods = [];
                        errors_shipping = [new ApplePayError("shippingContactInvalid", "postalAddress", "Postal Address is invalid")];
                    }
                    // else {
                    // 	shipping_methods = validateTimeZones;
                    // 	all_shipping_methods = validateTimeZones;
                    // 	errors_shipping = [];
                    //
                    // 	let total = parseFloat(amount) + parseFloat(validateTimeZones[0].amount);
                    // 	total = total.toFixed(2);
                    // 	newTotal = {type: 'final', label: 'TOTAL', amount: total};
                    // 	newLineItems = [
                    // 		{type: 'final', label: 'Cart Total', amount: amount},
                    // 		{type: 'final', label: 'Shipping Fee', amount: validateTimeZones[0].amount}
                    // 	];
                    // 	jQuery('#applepay_shipping_method').val(all_shipping_methods[0].identifier);
                    // }

                    let update = {
                        newLineItems: newLineItems,
                        newShippingMethods: shipping_methods,
                        newTotal: newTotal,
                        errors: errors_shipping
                    };

                    session.completeShippingContactSelection(update);
                });
            }

            session.onpaymentmethodselected = function (event) {
                let total = parseFloat(amount) + parseFloat(all_shipping_methods[0].amount);
                total = total.toFixed(2);
                let newTotal = {type: 'final', label: 'TOTAL', amount: total};
                let newLineItems = [
                    {type: 'final', label: 'Cart Total', amount: amount},
                    {type: 'final', label: 'Shipping Fee', amount: all_shipping_methods[0].amount}
                ];
                jQuery('#applepay_shipping_method').val(all_shipping_methods[0].identifier);
                session.completePaymentMethodSelection(newTotal, newLineItems);
            }

            session.onshippingmethodselected = function (event) {
                let myShippingMethod = event.shippingMethod;
                let cartTotal = parseFloat(amount) + parseFloat(myShippingMethod.amount);
                cartTotal = cartTotal.toFixed(2);
                let newTotal = {type: 'final', label: 'TOTAL', amount: cartTotal};
                let newLineItems = [
                    {type: 'final', label: 'Cart Total', amount: amount},
                    {type: 'final', label: 'Shipping Fee', amount: myShippingMethod.amount}
                ];
                jQuery('#applepay_shipping_method').val(myShippingMethod.identifier);
                session.completeShippingMethodSelection({newTotal, newLineItems});
            };

            session.onpaymentauthorized = function (event) {
                let paymentMethod = event.payment.token.paymentMethod;
                let billingToken = window.btoa(JSON.stringify(event.payment));
                jQuery('#paymentDisplayName').val(paymentMethod.displayName);
                jQuery('#paymentNetwork').val(paymentMethod.network);
                jQuery('#applepay_token_response').val(billingToken);

                let status = ApplePaySession.STATUS_SUCCESS;

                session.completePayment(status);
                jQuery.ajax({
                    type: "POST",
                    url: "<?php echo admin_url( 'admin-ajax.php' ); ?>",
                    data: ({
                        'action': 'create_order_applepay',
                        'paymentData': JSON.stringify(event.payment),
                        'billingToken': billingToken,
                        'paymentMethodTitle': jQuery('#paymentDisplayName').val(),
                        'shippingMethod': jQuery('#applepay_shipping_method').val(),
                        'needShipping': gateway_data.needs_shipping,
                        'fees': gateway_data.fees
                    }),
                    success: function (response) {
                        let json = response.slice(response.indexOf("returnUrl") + 9, response.length);
                        window.location.href = json;
                    }
                });
            }

            session.begin();
        }

        if (window.ApplePaySession) {
            let merchantIdentifier = "<?php echo $apple_pay_instance->merchant_id; ?>";
            let promise = ApplePaySession.canMakePaymentsWithActiveCard(merchantIdentifier);

            promise.then(function (canMakePayments) {
                if (canMakePayments) {
                    // Display Apple Pay button here.
                    showApplePayButton();
                    jQuery('.cart_google_pay').hide();
                } else {
                    jQuery('.cart_apple_pay').hide();
                }
            });
        } else {
            jQuery('.cart_apple_pay').hide();
            // jQuery(".payment-box").html("<p>Your browser or device does not support Apple Pay on the web. To use Apple Pay, open this page in Safari on a compatible device.</p>");
        }

        function showApplePayButton() {
            jQuery('.wc-acquired-cart-or-apple').html('&mdash;&nbsp;<?php _e( 'or', 'acquired-payment' ); ?>&nbsp;&mdash;');
        }

        function performValidation(validationURL) {
            return new Promise((resolve, reject) => {
                jQuery.ajax({
                    type: "POST",
                    url: "<?php echo admin_url( 'admin-ajax.php' ); ?>",
                    data: ({
                        'action': 'request_payment_session',
                        'validationURL': validationURL
                    }),
                    success: function (response) {
                        if (response != 0) {
                            let json = response.slice(response.indexOf("merchantSession") + 15, response.length);
                            json = JSON.parse(atob(json));
                            resolve(json);
                        } else {
                            reject(response.message);
                        }
                    }
                });
            });
        }

        function validateTimeZone(administrativeArea, countryCode, postalCode) {
            return new Promise((resolve, reject) => {
                jQuery.ajax({
                    type: "POST",
                    url: "<?php echo admin_url( 'admin-ajax.php' ); ?>",
                    data: ({
                        'action': 'validate_time_zone',
                        'administrativeArea': administrativeArea,
                        'countryCode': countryCode ? countryCode.toUpperCase() : null,
                        'postalCode': postalCode
                    }),
                    success: function (response) {
                        resolve(JSON.parse(response));
                    }
                });
            });
        }
    </script>
<?php
