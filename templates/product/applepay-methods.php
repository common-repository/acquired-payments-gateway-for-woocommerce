<?php
/**
 * Apple Pay button in product
 *
 * @package acquired-payment/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
?>
    <style>
        apple-pay-button {
            --apple-pay-button-height: <?php echo $button_height; ?>px;
            --apple-pay-button-border-radius: 3px;
            --apple-pay-button-padding: 0px 0px;
            --apple-pay-button-box-sizing: border-box;
        }
    </style>
    <script>
        /**
         * @returns {jQuery}
         */
        function get_gateway_data() {
            let container = 'li.payment_method_apple';
            return jQuery(container).find(".woocommerce_apple_gateway_data").data('gateway');
        }

        function applePayButtonClicked(event) {
            let all_shipping_methods;
            let gateway_data = get_gateway_data();

            let countryCode = gateway_data.country ? gateway_data.country.toUpperCase() : null;
            let amount = gateway_data.total;
            let total = jQuery('#acquired_total').val();
            if ( jQuery.isNumeric(total)) {
                amount = total;
            }
            let currencyCode = gateway_data.currency;

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

            session.onpaymentmethodselected = function (event) {
                let newTotal;
                let newLineItems;
                if (typeof all_shipping_methods === 'undefined') {
                    newTotal = {type: 'final', label: 'TOTAL', amount: amount};
                    newLineItems = [{type: 'final', label: gateway_data.items[0].label, amount: amount}];
                } else {
                    let total = parseFloat(amount) + parseFloat(all_shipping_methods[0].amount);
                    total = total.toFixed(2);
                    newTotal = {type: 'final', label: 'TOTAL', amount: total};
                    newLineItems = [
                        {type: 'final', label: 'Cart Total', amount: amount},
                        {type: 'final', label: 'Shipping Fee', amount: all_shipping_methods[0].amount}
                    ];
                    jQuery('#applepay_shipping_method').val(all_shipping_methods[0].identifier);
                }
                session.completePaymentMethodSelection(newTotal, newLineItems);
            }

            session.onshippingcontactselected = function (event) {
                let shippingContact = event.shippingContact;
                let promise = validateTimeZone(shippingContact.administrativeArea, shippingContact.countryCode, shippingContact.postalCode);

                promise.then(function (validateTimeZones) {
                    let errors_shipping;
                    let shipping_methods;
                    let newTotal = {type: 'final', label: 'TOTAL', amount: amount};
                    let newLineItems = [{type: 'final', label: gateway_data.items[0].label, amount: amount}];

                    if (typeof validateTimeZones.no_validate !== 'undefined') {
                        shipping_methods = [];
                        errors_shipping = [];
                    } else if (typeof validateTimeZones.no_shipping_available !== 'undefined') {
                        shipping_methods = [];
                        errors_shipping = [new ApplePayError("shippingContactInvalid", "postalAddress", "Postal Address is invalid")];
                    } else {
                        shipping_methods = validateTimeZones;
                        all_shipping_methods = validateTimeZones;
                        errors_shipping = [];

                        let total = parseFloat(amount) + parseFloat(validateTimeZones[0].amount);
                        total = total.toFixed(2);
                        newTotal = {type: 'final', label: 'TOTAL', amount: total};
                        newLineItems = [
                            {type: 'final', label: gateway_data.items[0].label, amount: amount},
                            {type: 'final', label: 'Shipping Fee', amount: validateTimeZones[0].amount}
                        ];
                        jQuery('#applepay_shipping_method').val(all_shipping_methods[0].identifier);
                    }

                    let update = {
                        newLineItems: newLineItems,
                        newShippingMethods: shipping_methods,
                        newTotal: newTotal,
                        errors: errors_shipping
                    };

                    session.completeShippingContactSelection(update);
                });
            }

            session.onshippingmethodselected = function (event) {
                let myShippingMethod = event.shippingMethod;
                let total = parseFloat(amount) + parseFloat(myShippingMethod.amount);
                total = total.toFixed(2);
                let newTotal = {type: 'final', label: 'TOTAL', amount: total};
                let newLineItems = [
                    {type: 'final', label: gateway_data.items[0].label, amount: amount},
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

                let gateway_data = get_gateway_data();
                let productGroupData = [];
                if (jQuery('#group_product').val() === 'yes') {
                    jQuery('table.woocommerce-grouped-product-list').find('tr').each(function () {
                        let qty = parseFloat(jQuery(this).find('input').val());
                        let group_product_id = jQuery(this).attr('id');
                        group_product_id = group_product_id.replace('product-', '');
                        productGroupData.push({'product_id' : group_product_id, 'product_quantity' : qty });
                    });
                }

                let status = ApplePaySession.STATUS_SUCCESS;

                session.completePayment(status);
                jQuery.ajax({
                    type: "POST",
                    url: "<?php echo admin_url( 'admin-ajax.php' ); ?>",
                    data: ({
                        'action': 'create_order_applepay',
                        'paymentData': JSON.stringify(event.payment),
                        'billingToken': billingToken,
                        'paymentMethodTitle':  jQuery('#paymentDisplayName').val(),
                        'productData': jQuery('#woocommerce-applepay-gateway').val(),
                        'productQty':  jQuery('[name=quantity]').val(),
                        'needShipping': gateway_data.needs_shipping,
                        'variation_id': jQuery('#acquired_variation_id').val(),
                        'productGroupData': productGroupData,
                        'shippingMethod': jQuery('#applepay_shipping_method').val()
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
            let merchantIdentifier = "<?php echo $gateway_instance->merchant_id; ?>";
            let promise = ApplePaySession.canMakePaymentsWithActiveCard(merchantIdentifier);
            promise.then(function (canMakePayments) {
                if (canMakePayments) {
                    // Display Apple Pay button here.
                    showApplePayButton();
                    jQuery('.product_google_pay').hide();
                    jQuery('#is_apple_device').val('true');
                    let gateway_data = get_gateway_data();
                    if ( gateway_data.product.variation === true && jQuery('#acquired_variation_id').val() === '' ) {
                        jQuery('apple-pay-button').hide();
                    }
                } else {
                    jQuery('.product_apple_pay').hide();
                }
            });
        } else {
            jQuery('.product_apple_pay').hide();
            jQuery("#button-container").html("<p>Your browser or device does not support Apple Pay on the web.<br><br>To try out this demo, open this page in Safari on a compatible device.</p>");
        }

        jQuery( ".single_variation_wrap" ).on( "show_variation", function (event, data) {
            if (jQuery('#is_apple_device').val() == 'true') {
                jQuery('#acquired_variation_price').val(data.display_price);
                jQuery('#acquired_variation_id').val(data.variation_id);
                jQuery('apple-pay-button').show();
            }
        } );

        function showApplePayButton() {
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
    <div class="wc-acquired-product-checkout-container product_apple_pay">
        <ul class="wc_acquired_product_payment_methods" style="list-style: none">
            <li class="payment_method_<?php echo esc_attr( $gateways ); ?>">
                <div class="payment-box">
                    <div id="wc-acquired-<?php echo $gateways; ?>-container">
                        <input name="is_apple_device" id="is_apple_device" value="false" type="hidden">
                        <input name="acquired_variation_id" id="acquired_variation_id" value="" type="hidden">
                        <input name="acquired_variation_price" id="acquired_variation_price" value="" type="hidden">
                        <input name="acquired_total" id="acquired_total" value="" type="hidden">
                        <input name="group_product" id="group_product" value="<?php echo $group_product; ?>" type="hidden">
                        <input name="group_product_data" id="group_product_data" value="" type="hidden">
                        <input name="variable_product" id="variable_product" value="<?php echo $variabled_product; ?>" type="hidden">
                        <input name="acquired_product_qty" id="acquired_product_qty" value="" type="hidden">
                        <input type="hidden" name="applepay_token_response" id="applepay_token_response"/>
                        <input type="hidden" name="applepay_shipping_method" id="applepay_shipping_method"/>
                        <input type="hidden" name="paymentDisplayName" id="paymentDisplayName"/>
                        <input type="hidden" name="paymentNetwork" id="paymentNetwork"/>
                        <apple-pay-button buttonstyle="<?php echo $gateway_instance->get_option( 'button_style' ); ?>" type="<?php echo $gateway_instance->get_option( 'button_type' ); ?>" locale="en" onclick="applePayButtonClicked()"></apple-pay-button>
                    </div>
                    <?php echo $gateway_instance->output_display_items( 'product' ); ?>
                </div>
            </li>
        </ul>
    </div>
    <script>
        jQuery('apple-pay-button').css("--apple-pay-button-width", jQuery('.wc-acquired-product-checkout-container').width().toString() + 'px');
    </script>
<?php
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
