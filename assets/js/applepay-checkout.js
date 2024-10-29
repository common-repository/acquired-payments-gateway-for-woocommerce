jQuery(function ($) {
    $.fn.ignore = function(sel) {
        return this.clone().find(sel || ">*").remove().end();
    };

    /**
     * @returns {$}
     */
    function get_gateway_data() {
        let container = 'li.payment_method_apple';
        let data = $(container).find(".woocommerce_apple_gateway_data").data('gateway');
        if (typeof data === 'undefined') {
            data = $('form.checkout').find(".woocommerce_apple_gateway_data").data('gateway');
            if (typeof data === 'undefined') {
                data = $('.woocommerce_apple_gateway_data').data('gateway');
            }
        }
        return data;
    }

    function applePayButtonClicked(event) {
        let $form = $('form.woocommerce-checkout');
        if ($form.find('#terms').length && !$form.find('#terms').is(':checked')) {
            $form.trigger('submit');
            return;
        }

        // Set Apple Pay method
        var payment_method = $("input[name='payment_method']:checked");
        if ( payment_method ) {
            payment_method.prop('checked', false);
        }
        $( '.place-order' ).append( '<input id="payment_method_'+applepay_checkout.id+'" name="payment_method" type="hidden" value="'+applepay_checkout.id+'" />' );
        $form.trigger('submit');

        let json = $form.serializeArray().reduce(function (acc, item) {
            acc[item.name] = item.value;
            return acc;
        }, {});
        let gateway_data = get_gateway_data();

        let countryCode = json.billing_country ? json.billing_country.toUpperCase() : null;
        let amount = $('.order-total .woocommerce-Price-amount bdi').ignore('span').text();
        let currencyCode = gateway_data.currency;
        let all_shipping_methods = gateway_data.shipping_options;

        let request = {
            countryCode: countryCode,
            currencyCode: currencyCode,
            supportedNetworks: ['visa', 'masterCard', 'amex'],
            merchantCapabilities: ['supports3DS'],
            total: {label: 'Total amount: ', amount: amount},
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
                try {
                    // Checkout object merchantSession
                    if (typeof merchantSession === 'object' && merchantSession !== null) {
                        session.completeMerchantValidation(merchantSession);
                    } else {
                        console.error('merchantSession is invalid:', merchantSession);
                    }
                } catch (error) {
                    console.error('Error completing merchant authentication:', error);
                }
            }).catch(function (error) {
                console.error('Error in performValidation:', error);
            });
        }

        session.onshippingcontactselected = function (event) {
            let shippingContact = event.shippingContact;
            let promise = validateTimeZone(shippingContact.administrativeArea, shippingContact.countryCode, shippingContact.postalCode);

            promise.then(function (validateTimeZones) {
                let errors_shipping;
                let shipping_methods;
                let total = $('.order-total .woocommerce-Price-amount bdi').find('span').remove().end().text();
                let newTotal = {type: 'final', label: 'TOTAL', amount: total};
                let newLineItems = [
                    {type: 'final', label: 'Cart Total', amount: amount},
                    {type: 'final', label: 'Shipping Fee', amount: all_shipping_methods[0].amount}
                ];

                if ( (typeof validateTimeZones.no_validate !== 'undefined') || ( $('input:checked', '#shipping_method').length == '0' ) ) {
                    shipping_methods = [];
                    errors_shipping = [];
                } else if (typeof validateTimeZones.no_shipping_available !== 'undefined') {
                    shipping_methods = [];
                    errors_shipping = [new ApplePayError("shippingContactInvalid", "postalAddress", "Postal Address is invalid")];
                } else {
                    let shipping_current;
                    $.each(gateway_data.shipping_options, function( index, value ) {
                        if ( $('input:checked', '#shipping_method').val() == value.identifier ) {
                            shipping_current = [value];
                        }
                    });

                    shipping_methods = shipping_current;
                    all_shipping_methods = shipping_current;
                    errors_shipping = [];

                    let total = $('.order-total .woocommerce-Price-amount bdi').find('span').remove().end().text();

                    newTotal = {type: 'final', label: 'TOTAL', amount: total};
                    newLineItems = [
                        {type: 'final', label: 'Cart Total', amount: amount},
                        {type: 'final', label: 'Shipping Fee', amount: shipping_current[0].amount}
                    ];
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

        session.onpaymentmethodselected = function (event) {
            let total = $('.order-total .woocommerce-Price-amount bdi').find('span').remove().end().text();
            let newTotal = {type: 'final', label: 'TOTAL', amount: total};
            let newLineItems = [
                {type: 'final', label: 'Cart Total', amount: amount},
                {type: 'final', label: 'Shipping Fee', amount: all_shipping_methods[0].amount}
            ];
            session.completePaymentMethodSelection(newTotal, newLineItems);
        }

        session.onshippingmethodselected = function (event) {
            let myShippingMethod = event.shippingMethod;
            let total = $('.order-total .woocommerce-Price-amount bdi').find('span').remove().end().text();
            let newTotal = {type: 'final', label: 'TOTAL', amount: total};
            let newLineItems = [
                {type: 'final', label: 'Cart Total', amount: amount},
                {type: 'final', label: 'Shipping Fee', amount: myShippingMethod.amount}
            ];
            let paymentMethodId = $('form.woocommerce-checkout').find("input[value='"+ myShippingMethod.identifier +"']").attr('id')
            $('#' + paymentMethodId ).trigger('click');
            session.completeShippingMethodSelection({newTotal, newLineItems});
        };

        session.onpaymentauthorized = function (event) {
            let paymentMethod = event.payment.token.paymentMethod;
            let billingToken = window.btoa(JSON.stringify(event.payment));
            let paymentDisplayName = paymentMethod.displayName;
            let paymentNetwork = paymentMethod.network;

            let status = ApplePaySession.STATUS_SUCCESS;
            session.completePayment(status);

            // Process Payment
            $.ajax({
                type: "POST",
                url: applepay_checkout.ajaxurl,
                data: ({
                    'action': 'apple_process_payment',
                    'applepay_process_nonce': applepay_checkout.applepay_process_nonce,
                    'apple_token_response': billingToken,
                    'paymentDisplayName': paymentDisplayName,
                    'paymentNetwork': paymentNetwork
                }),
                beforeSend: function () {
                    $form.block({
                        message: null,
                        overlayCSS: {
                            background: "#fff",
                            opacity: .6
                        }
                    });
                },
                success: function (response) {
                    var obj = JSON.parse(response);
                    window.top.location.href = obj.redirect;
                }
            });
        }

        session.oncancel = function (event) {
            $('#payment_method_'+applepay_checkout.id).remove();
        }

        $( 'form.checkout' ).on( 'checkout_place_order_success', function( c, t ) {
            const method = t.payment_method;
            if ( method == applepay_checkout.id ) {
                session.begin();
            }
        });
    }

    if (window.ApplePaySession) {
        let merchantIdentifier = applepay_checkout.gatewayMerchantId;
        let promise = ApplePaySession.canMakePaymentsWithActiveCard(merchantIdentifier);
        $('.wc-acquired-googlepay').remove();
        $('.wc-acquired-applepay > p').css( 'display', 'none' );

        promise.then(function (canMakePayments) {
            if (canMakePayments) {
                // Display Apple Pay button here.
                $('.wc-acquired-applepay > p').css( 'display', 'block' );
                $("#place_order").attr( "style", "display: none !important;" );
            } else {
                $('.wc-acquired-applepay').remove();
            }
        });
    } else {
        $('.wc-acquired-applepay').remove();
    }

    function performValidation(validationURL) {
        return new Promise((resolve, reject) => {
            $.ajax({
                type: "POST",
                url: applepay_checkout.ajaxurl,
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
            $.ajax({
                type: "POST",
                url: applepay_checkout.ajaxurl,
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

    if ($( "#payment_method_apple:checked").length > 0 || $( "#payment_method_google:checked").length > 0) {
        $("#place_order").attr( "style", "display: none !important;" );
    } else {
        $("#place_order").attr( "style", "display: block !important;" );
    }

    $('#payment_method_apple').on('checked', function () {
        $("#place_order").attr( "style", "display: none !important;" );
    });

    $('form.woocommerce-checkout').on('change',function () {
        if ($( "#payment_method_apple:checked").length > 0 || $( "#payment_method_google:checked").length > 0) {
            $("#place_order").attr( "style", "display: none !important;" );
        } else {
            $("#place_order").attr( "style", "display: block !important;" );
        }
    });

    $('apple-pay-button').on('click',function (event) {
        applePayButtonClicked(event);
    });

    /**
     * Remove Apple Pay option
     */
    $('apple-pay-button').css("--apple-pay-button-width", "100%");
    $( document ).on( 'updated_checkout', function() {
        $( '.payment_method_apple' ).remove();
    } );
});
