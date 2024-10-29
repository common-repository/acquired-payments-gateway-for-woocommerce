jQuery(function ($) {
    let $body = $(document.body);
    let $form = $('form.woocommerce-checkout');
    $.fn.ignore = function(sel) {
        return this.clone().find(sel || ">*").remove().end();
    };

    const baseRequest = {
        apiVersion: 2,
        apiVersionMinor: 0
    };

    /**
     * Card networks supported by your site and your gateway
     *
     * @todo confirm card networks supported by your site and gateway
     */
    const allowedCardNetworks = ["AMEX", "MASTERCARD", "VISA"];

    /**
     * Card authentication methods supported by your site and your gateway
     *
     * @todo confirm your processor supports Android device tokens for your
     * supported card networks
     */
    const allowedCardAuthMethods = googlepay_checkout.authentication_method;

    /**
     * Identify your gateway and your site's gateway merchant identifier
     *
     * The Google Pay API response will return an encrypted payment method capable
     * of being charged by a supported gateway after payer authorization
     *
     * @todo check with your gateway on the parameters to pass
     */
    const tokenizationSpecification = {
        type: 'PAYMENT_GATEWAY',
        parameters: {
            'gateway': 'acquired',
            'gatewayMerchantId': googlepay_checkout.gatewayMerchantId
        }
    };

    /**
     * Describe your site's support for the CARD payment method and its required fields
     */
    const baseCardPaymentMethod = {
        type: 'CARD',
        parameters: {
            allowedAuthMethods: allowedCardAuthMethods,
            allowedCardNetworks: allowedCardNetworks
        }
    };

    /**
     * Describe your site's support for the CARD payment method including optional fields
     */
    const cardPaymentMethod = Object.assign(
        {},
        baseCardPaymentMethod,
        {
            tokenizationSpecification: tokenizationSpecification
        }
    );

    /**
     * An initialized google.payments.api.PaymentsClient object or null if not yet set
     *
     * @see {@link getGooglePaymentsClient}
     */
    let paymentsClient = null;

    /**
     * Configure your site's support for payment methods supported by the Google Pay
     * API.
     *
     * Each member of allowedPaymentMethods should contain only the required fields,
     * allowing reuse of this base request when determining a viewer's ability
     * to pay and later requesting a supported payment method
     *
     * @returns {object} Google Pay API version, payment methods supported by the site
     */
    function getGoogleIsReadyToPayRequest() {
        return Object.assign(
            {},
            baseRequest,
            {
                allowedPaymentMethods: [baseCardPaymentMethod]
            }
        );
    }

    /**
     * Configure support for the Google Pay API
     *
     * @returns {object} PaymentDataRequest fields
     */
    function getGooglePaymentDataRequest() {
        let shippingOptionArray = [
            {
                'id' : 'default',
                'label' : 'Default',
                'description' : '0.00'
            }
        ]

        const paymentDataRequest = Object.assign({}, baseRequest);
        paymentDataRequest.allowedPaymentMethods = [cardPaymentMethod];
        paymentDataRequest.transactionInfo = getGoogleTransactionInfo();
        paymentDataRequest.merchantInfo = get_merchant_info();
        paymentDataRequest.allowedPaymentMethods[0].parameters.billingAddressParameters = {
            format: "FULL",
            phoneNumberRequired: true
        };
        paymentDataRequest.emailRequired = true;
        paymentDataRequest.callbackIntents = ["SHIPPING_ADDRESS", "SHIPPING_OPTION", "PAYMENT_AUTHORIZATION"];
        paymentDataRequest.shippingAddressRequired = true;
        paymentDataRequest.shippingAddressParameters = getGoogleShippingAddressParameters();
        paymentDataRequest.shippingOptionParameters = {
            defaultSelectedOptionId: shippingOptionArray[0].id,
            shippingOptions: shippingOptionArray,
        };
        paymentDataRequest.shippingOptionRequired = true;

        return paymentDataRequest;
    }

    /**
     * Return an active PaymentsClient or initialize
     *
     * @returns {google.payments.api.PaymentsClient} Google Pay API client
     */
    function getGooglePaymentsClient() {
        if (paymentsClient === null) {
            paymentsClient = new google.payments.api.PaymentsClient(get_payment_options());
        }
        return paymentsClient;
    }

    function get_payment_options() {
        let current_environment = "PRODUCTION";
        if (googlepay_checkout.environment === 'TEST') {
            current_environment = 'TEST';
        }
        let options = {
            environment: current_environment,
            merchantInfo: get_merchant_info(),
            paymentDataCallbacks: {
                onPaymentAuthorized: function onPaymentAuthorized() {
                    return new Promise(function (resolve) {
                        resolve({
                            transactionState: "SUCCESS"
                        });
                    });
                }
            }
        };

        options.paymentDataCallbacks.onPaymentDataChanged = onPaymentDataChanged;

        return options;
    }

    /**
     * Get merchant Infor
     * @returns {{merchantId: *, merchantName: *}}
     */
    function get_merchant_info() {
        var options = {
            merchantId: googlepay_checkout.google_merchant_id,
            merchantName: googlepay_checkout.merchant_name
        };

        if (googlepay_checkout.environment === 'TEST') {
            delete options.merchantId;
        }

        return options;
    }

    /**
     * Handles dynamic buy flow shipping address and shipping options callback intents.
     *
     * @param {object} itermediatePaymentData response from Google Pay API a shipping address or shipping option is selected in the payment sheet.
     * @returns Promise<{object}> Promise of PaymentDataRequestUpdate object to update the payment sheet.
     */
    function onPaymentDataChanged(intermediatePaymentData) {
        return new Promise(function (resolve, reject) {

            let shippingAddress = intermediatePaymentData.shippingAddress;
            let shippingOptionData = intermediatePaymentData.shippingOptionData;
            let paymentDataRequestUpdate = {};
            let promise = validateTimeZone(shippingAddress.administrativeArea, shippingAddress.countryCode, shippingAddress.postalCode);

            promise.then(function (validateTimeZones) {
                if (intermediatePaymentData.callbackTrigger == "INITIALIZE" || intermediatePaymentData.callbackTrigger == "SHIPPING_ADDRESS") {
                    if (shippingAddress.administrativeArea == "NJ") {
                        paymentDataRequestUpdate.error = getGoogleUnserviceableAddressError(validateTimeZones);
                    } else {
                        paymentDataRequestUpdate.newShippingOptionParameters = getGoogleDefaultShippingOptions(validateTimeZones);
                        let selectedShippingOptionId = paymentDataRequestUpdate.newShippingOptionParameters.defaultSelectedOptionId;
                        paymentDataRequestUpdate.newTransactionInfo = calculateNewTransactionInfo(selectedShippingOptionId, validateTimeZones);
                    }
                } else if (intermediatePaymentData.callbackTrigger == "SHIPPING_OPTION") {
                    paymentDataRequestUpdate.newTransactionInfo = calculateNewTransactionInfo(shippingOptionData.id, validateTimeZones);
                }

                resolve(paymentDataRequestUpdate);
            });
        });
    }

    /**
     * Helper function to create a new TransactionInfo object.

     * @param string shippingOptionId respresenting the selected shipping option in the payment sheet.
     *
     * @returns {object} transaction info, suitable for use as transactionInfo property of PaymentDataRequest
     */
    function calculateNewTransactionInfo(shippingOptionId, validateTimeZones) {
        let newTransactionInfo = getGoogleTransactionInfo();

        let shippingCost = getShippingCosts(validateTimeZones)[shippingOptionId];
        newTransactionInfo.displayItems.forEach(function (displayItem, index, object) {
            if (displayItem.label === 'Shipping cost' || displayItem.label === 'Shipping') {
                object.splice(index, 1);
            }
        });
        newTransactionInfo.displayItems.push({
            type: "LINE_ITEM",
            label: "Shipping cost",
            price: shippingCost,
            status: "FINAL"
        });

        let totalPrice = 0.00;
        newTransactionInfo.displayItems.forEach(displayItem => totalPrice += parseFloat(displayItem.price));
        totalPrice = totalPrice.toFixed(2);
        newTransactionInfo.totalPrice = totalPrice.toString();

        return newTransactionInfo;
    }

    /**
     * Initialize Google PaymentsClient after Google-hosted JavaScript has loaded
     *
     * Display a Google Pay payment button after confirmation of the viewer's
     * ability to pay.
     */
    function onGooglePayLoaded() {
        const paymentsClient = getGooglePaymentsClient();
        paymentsClient.isReadyToPay(getGoogleIsReadyToPayRequest())
            .then(function (response) {
                if (response.result) {
                    addGooglePayButton();
                } else {
                    // jQuery('#wc-acquired-googlepay-container').html('Cannot checkout with GooglePay right now. Please contact your merchant');
					jQuery('.wc-acquired-googlepay').remove();
                }
            })
            // .catch(function (err) {
                // show error in developer console for debugging
                // console.error('isReadyToPay error');
            // });
    }

    /**
     * Add a Google Pay purchase button alongside an existing checkout button
     */
    function addGooglePayButton() {
        const paymentsClient = getGooglePaymentsClient();
        const button =
            paymentsClient.createButton({
                buttonColor: googlepay_checkout.button_color,
                buttonType: googlepay_checkout.button_type,
                buttonSizeMode: "fill",
                onClick: onGooglePaymentButtonClicked
            });
        if (googlepay_checkout.button_height !== '') {
            $('#wc-acquired-googlepay-container').css('height', googlepay_checkout.button_height);
        }

		if ( /^((?!chrome|android).)*safari/i.test(navigator.userAgent)) {
			jQuery('.wc-acquired-googlepay').remove();
		} else {
			$('.wc-acquired-googlepay').css('display', 'block');
		}

		$('#wc-acquired-googlepay-container').css('width', '100%');
        document.getElementById('wc-acquired-googlepay-container').appendChild(button);
        $('#wc-acquired-googlepay-container').addClass("button_active");
    }

    /**
     * Provide Google Pay API with a payment amount, currency, and amount status
     *
     * @returns {object} transaction info, suitable for use as transactionInfo property of PaymentDataRequest
     */
    function getGoogleTransactionInfo() {
        let json = $form.serializeArray().reduce(function (acc, item) {
            acc[item.name] = item.value;
            return acc;
        }, {});
        let gateway_data = get_gateway_data();
        let total = $('.order-total .woocommerce-Price-amount bdi').ignore('span').text();
        return {
            displayItems: gateway_data.items,
            countryCode: json.billing_country,
            currencyCode: gateway_data.currency,
            totalPriceStatus: "FINAL",
            totalPrice: total,
            totalPriceLabel: "Total"
        };
    }

    /**
     * @returns {jQuery}
     */
    function get_gateway_data() {
        let json = $form.serializeArray().reduce(function (acc, item) {
            acc[item.name] = item.value;
			acc['payment_method'] = 'google';
            return acc;
        }, {});

        var container = 'li.payment_method_' + json.payment_method;
        var data = $(container).find(".woocommerce_".concat(json.payment_method, "_gateway_data")).data('gateway');
        if (typeof data === 'undefined' && googlepay_checkout.page === 'checkout') {
            data = $('form.checkout').find(".woocommerce_".concat(json.payment_method, "_gateway_data")).data('gateway');
            if (typeof data === 'undefined') {
                data = $('.woocommerce_' + json.payment_method + '_gateway_data').data('gateway');
            }
        }
        return data;
    }

    /**
     * Provide a key value store for shippingCost options.
     */
    function getShippingCosts(validateTimeZones) {
        let shippingOptions = getGoogleDefaultShippingOptions(validateTimeZones);
        let shippingCost = {};
        shippingOptions.shippingOptions.forEach(function (shippingOption) {
            shippingCost[shippingOption.id] = shippingOption.description;
        });

        return shippingCost;
    }

    /**
     * Provide Google Pay API with shipping address parameters when using dynamic buy flow.
     *
     * @returns {object} shipping address details, suitable for use as shippingAddressParameters property of PaymentDataRequest
     */
    function getGoogleShippingAddressParameters() {
        return {
            phoneNumberRequired: true
        };
    }

    /**
     * Provide Google Pay API with shipping options and a default selected shipping option.
     *
     * @returns {object} shipping option parameters, suitable for use as shippingOptionParameters property of PaymentDataRequest
     */
    function getGoogleDefaultShippingOptions(validateTimeZones) {
        let shippingOptionArray = [];
        let needs_shipping = get_gateway_data().needs_shipping;
        if ( needs_shipping === false || typeof validateTimeZones.no_validate !== 'undefined' || typeof validateTimeZones.no_shipping_available !== 'undefined') {
            shippingOptionArray = [
                {
                    'id' : 'default',
                    'label' : 'Default',
                    'description' : '0.00'
                }
            ]
            return {
                defaultSelectedOptionId: shippingOptionArray[0].id,
                shippingOptions: shippingOptionArray,
            }
        } else {
            let shipping_options = get_gateway_data().shipping_options;
            shipping_options.forEach(function (item, index) {
                shippingOptionArray.push(
                    {
                        'id' : item.id,
                        'label' : item.label,
                        'description' : item.description
                    }
                )
            });

            let shippingChecked = $('#shipping_method').find('input[checked=checked]').val();
            let defaultOption = '';
            if (shippingChecked === undefined) {
                defaultOption = shippingOptionArray[0].id;
            } else {
                defaultOption = shippingChecked;
            }

            return {
                defaultSelectedOptionId: defaultOption,
                shippingOptions: shippingOptionArray
            };
        }
    }

    /**
     * Provide Google Pay API with a payment data error.
     *
     * @returns {object} payment data error, suitable for use as error property of PaymentDataRequestUpdate
     */
    function getGoogleUnserviceableAddressError() {
        return {
            reason: "SHIPPING_ADDRESS_UNSERVICEABLE",
            message: "Cannot ship to the selected address",
            intent: "SHIPPING_ADDRESS"
        };
    }

    /**
     * Prefetch payment data to improve performance
     */
    function prefetchGooglePaymentData() {
        const paymentDataRequest = getGooglePaymentDataRequest();
        // transactionInfo must be set but does not affect cache
        paymentDataRequest.transactionInfo = {
            totalPriceStatus: 'NOT_CURRENTLY_KNOWN',
            currencyCode: 'USD'
        };
        const paymentsClient = getGooglePaymentsClient();
        paymentsClient.prefetchPaymentData(paymentDataRequest);
    }
    
    /**
     * Show Google Pay payment sheet when Google Pay payment button is clicked
     */
    function onGooglePaymentButtonClicked() {
        // Set Google Pay method
        var payment_method = $("input[name='payment_method']:checked");
        if ( payment_method ) {
            payment_method.prop('checked', false);
        }
        $( '.place-order' ).append( '<input id="payment_method_'+googlepay_checkout.id+'" name="payment_method" type="hidden" value="'+googlepay_checkout.id+'" />' );
        $form.trigger('submit');

        $( 'form.checkout' ).on( 'checkout_place_order_success', function( c, t ) {
            const method = t.payment_method;
            if ( method == googlepay_checkout.id ) {
                const paymentDataRequest = getGooglePaymentDataRequest();
                paymentDataRequest.transactionInfo = getGoogleTransactionInfo();

                const paymentsClient = getGooglePaymentsClient();
                const orderId = t.order_id;
                paymentsClient.loadPaymentData(paymentDataRequest)
                    .then(function (paymentData) {
                        // handle the response
                        processPayment(paymentData, orderId);
                    })
                    .catch(function (err) {
                        // show error in developer console for debugging
                        console.error("loadPaymentData error"+JSON.stringify(err));
                        // Remove payment_method_google
                        if ( err.statusCode ) {
                            var error_message = 'Acquired Payments: '+err.statusCode;
                        } else {
                            var error_message = 'Acquired Payments: Processing Order';
                        }
                        $( '.acquired_'+googlepay_checkout.id+'-notice' ).text(error_message);
                        $( '#payment_method_'+googlepay_checkout.id+'' ).remove();
                    });
            }
        });
    }

    /**
     * Process payment data returned by the Google Pay API
     *
     * @param {object} paymentData response from Google Pay API after user approves payment
     */
    function processPayment(paymentData, orderId) {
        paymentData.paymentMethodData.description = escape(paymentData.paymentMethodData.description);
        let billingToken = window.btoa(JSON.stringify(paymentData));
        let paymentDisplayName = paymentData.paymentMethodData.description;
        let paymentNetwork = paymentData.paymentMethodData.info.cardNetwork;
        addTdsData(paymentData);
        let tds = JSON.stringify(paymentData.tds);
        $.ajax({
            type: "POST",
            url: googlepay_checkout.ajaxurl,
            data: ({
                'action': 'googlepay_process_payment',
                'googlepay_process_nonce': googlepay_checkout.googlepay_process_nonce,
                'order_id': orderId,
                'gpay_token_response': billingToken,
                'paymentDisplayName': paymentDisplayName,
                'paymentNetwork': paymentNetwork,
                'tds': tds
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

    function update_billing(paymentData) {
        $('#billing_first_name').val(paymentData.shippingAddress.name);
        $('#billing_last_name').val(paymentData.shippingAddress.name);
        $('#billing_country').val(paymentData.shippingAddress.countryCode);
        $('#billing_address_1').val(paymentData.shippingAddress.address1);
        $('#billing_address_2').val(paymentData.shippingAddress.address2);
        $('#billing_city').val(paymentData.shippingAddress.locality);
        $('#billing_state').val(paymentData.shippingAddress.administrativeArea);
        $('#billing_postcode').val(paymentData.shippingAddress.postalCode);
        $('#billing_phone').val(paymentData.shippingAddress.phoneNumber);
        $('#billing_email').val(paymentData.email);
    }

    /**
     * @param data
     */
    function addTdsData(data) {
        gatherBrowserData(data);
    }

    /**
     * @param data
     */
    function gatherBrowserData(data) {
        let browserTime = new Date();
        let browserTimezoneZoneOffset = (browserTime.getTimezoneOffset()); // 0

        if (!data.hasOwnProperty('tds')) data.tds = {};

        data.tds.browser_data = {
            accept_header: '*/*',
            color_depth: screen.colorDepth, // 24
            java_enabled: navigator.javaEnabled().toString(), // true
            javascript_enabled: "true", // true
            language: navigator.language, // en_US
            screen_height: screen.height, // 1080
            screen_width: screen.width, // 1920
            challenge_window_size: 'FULL_SCREEN', // WINDOWED_250X400 WINDOWED_390X400 WINDOWED_500X600 WINDOWED_600X400 FULL_SCREEN
            user_agent: navigator.userAgent, // Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.110 Safari/537.36
            timezone: browserTimezoneZoneOffset, // 0
        };
    }

//     $('.woocommerce-checkout').on('click', 'input[id=payment_method_google]', function () {
//         if (!$('#wc-acquired-googlepay-container').hasClass("button_active")) {
//             onGooglePayLoaded();
//         }
//     });

	onGooglePayLoaded();

    $('#payment_method_google').on('checked', function () {
        $('#place_order').hide();
    });

    $form.on('change',function () {
        if (jQuery( "#payment_method_apple:checked").length > 0 || jQuery( "#payment_method_google:checked").length > 0) {
            jQuery("#place_order").hide();
        } else {
            jQuery("#place_order").show();
        }
    });

    /**
     * Validate shipping option by timezone
     *
     * @param administrativeArea
     * @param countryCode
     * @param postalCode
     * @returns {Promise<unknown>}
     */
    function validateTimeZone(administrativeArea, countryCode, postalCode) {
        return new Promise((resolve, reject) => {
            jQuery.ajax({
                type: "POST",
                url: googlepay_checkout.ajaxurl,
                data: ({
                    'action': 'validate_time_zone',
                    'administrativeArea': administrativeArea,
                    'countryCode': countryCode,
                    'postalCode': postalCode
                }),
                success: function (response) {
                    resolve(JSON.parse(response));
                }
            });
        });
    }

    /**
     * Remove Google Pay option
     */
    $( document ).on( 'updated_checkout', function() {
        $( '.payment_method_google' ).remove();
    } );
});
