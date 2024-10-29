jQuery(function ($) {
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
    const allowedCardAuthMethods = googlepay_product.authentication_method;

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
            'gatewayMerchantId': googlepay_product.gatewayMerchantId
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
        if (googlepay_product.environment === 'TEST') {
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
            merchantId: googlepay_product.google_merchant_id,
            merchantName: googlepay_product.merchant_name
        };

        if (googlepay_product.environment === 'TEST') {
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
                        paymentDataRequestUpdate.error = getGoogleUnserviceableAddressError();
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
            if (displayItem.type === 'SUBTOTAL') {
                displayItem.price = $('#acquired_total').val();
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
                }
            })
            .catch(function (err) {
                // show error in developer console for debugging
                console.error('isReadyToPay error');
            });
    }

    /**
     * Add a Google Pay purchase button alongside an existing checkout button
     */
    function addGooglePayButton() {
        const paymentsClient = getGooglePaymentsClient();
        const button =
            paymentsClient.createButton({
                buttonColor: googlepay_product.button_color,
                buttonType: googlepay_product.button_type,
                buttonSizeMode: "fill",
                onClick: onGooglePaymentButtonClicked
            });
        if (googlepay_product.button_height !== '') {
            $('#wc-acquired-google-container').css('height', googlepay_product.button_height);
        }

        $('#wc-acquired-google-container').html('');
        document.getElementById('wc-acquired-google-container').appendChild(button);
        $('#wc-acquired-google-container').addClass("button_active");
    }

    /**
     * Provide Google Pay API with a payment amount, currency, and amount status
     *
     * @returns {object} transaction info, suitable for use as transactionInfo property of PaymentDataRequest
     */
    function getGoogleTransactionInfo() {
        let gateway_data = get_gateway_data();
        let total = '';

        total = $('#acquired_total').val();

        return {
            displayItems: gateway_data.items,
            currencyCode: gateway_data.currency,
            totalPriceStatus: "FINAL",
            totalPrice: total,
            totalPriceLabel: "Total",
        };
    }

    /**
     * @returns {jQuery}
     */
    function get_gateway_data() {
        let container = 'li.payment_method_google';
        let data = $(container).find(".woocommerce_google_gateway_data").data('gateway');

        return data;
    };



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
        if (needs_shipping === false || typeof validateTimeZones.no_validate !== 'undefined'
            || typeof validateTimeZones.no_shipping_available !== 'undefined'
            || Object.keys(validateTimeZones).length === 0) {
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
            validateTimeZones.forEach(function (item, index) {
                shippingOptionArray.push(
                    {
                        'id' : item.identifier,
                        'label' : item.label,
                        'description' : item.amount
                    }
                )
            });

            return {
                defaultSelectedOptionId: shippingOptionArray[0].id,
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
        const paymentDataRequest = getGooglePaymentDataRequest();
        paymentDataRequest.transactionInfo = getGoogleTransactionInfo();

        const paymentsClient = getGooglePaymentsClient();
        paymentsClient.loadPaymentData(paymentDataRequest)
            .then(function (paymentData) {
                // handle the response
                processPayment(paymentData);
            })
            .catch(function (err) {
                // show error in developer console for debugging
                console.error("loadPaymentData error");
            });
    }

    /**
     * Process payment data returned by the Google Pay API
     *
     * @param {object} paymentData response from Google Pay API after user approves payment
     */
    function processPayment(paymentData) {
        let paymentMethodTitle = paymentData.paymentMethodData.description;
        paymentData.paymentMethodData.description = escape(paymentData.paymentMethodData.description);
        let billingToken = window.btoa(JSON.stringify(paymentData));
        $('#gpay_token_response').val(billingToken);
        addTdsData(paymentData);
        $('#tds').val(JSON.stringify(paymentData.tds));
        let gateway_data = get_gateway_data();
        let productGroupData = [];
        if ($('#group_product').val() === 'yes') {
            $('table.woocommerce-grouped-product-list').find('tr').each(function () {
                let qty = parseFloat($(this).find('input').val());
                let group_product_id = jQuery(this).attr('id');
                group_product_id = group_product_id.replace('product-', '');
                productGroupData.push({'product_id' : group_product_id, 'product_quantity' : qty });
            });
        }

        $.ajax({
            type: "POST",
            url: googlepay_product.ajaxurl,
            data: ({
                'action': 'create_order',
                'paymentData': JSON.stringify(paymentData),
                'billingToken': billingToken,
                'paymentMethodTitle': paymentMethodTitle,
                'productData': $('#woocommerce-googlepay-gateway').val(),
                'tds': JSON.stringify(paymentData.tds),
                'productQty':  $('[name=quantity]').val(),
                'needShipping': gateway_data.needs_shipping,
                'variation_id': $('#acquired_variation_id').val(),
                'productGroupData': productGroupData
            }),
            success: function (response) {
                let json = response.slice(response.indexOf("returnUrl") + 9, response.length);
                window.location.href = json;
            }
        });
    }

    let gateway_data = get_gateway_data();
    if (gateway_data.product.variation === false ) {
        calculate_total();
        onGooglePayLoaded();
    }

    jQuery( ".single_variation_wrap" ).on( "show_variation", function (event, data) {
        $('#acquired_variation_price').val(data.display_price);
        $('#acquired_variation_id').val(data.variation_id);
        calculate_total();
        onGooglePayLoaded();
    } );

    jQuery( "form.cart" ).on( "change", function () {
        let variationPrice = $('#acquired_variation_price').val();

        if (variationPrice == '' && $('#variable_product').val() === 'yes') {
            return;
        } else {
            calculate_total();

            onGooglePayLoaded();
        }
    } );

    /**
     * Calculate total
     */
    function calculate_total() {
        let total = 0;
        let variation_price = $('#acquired_variation_price').val();

        if ($('#group_product').val() === 'yes') {
            $('table.woocommerce-grouped-product-list').find('tr').each(function () {
                let qty = parseFloat($(this).find('input').val());
                let productAmount = '';
                let strCurrency = '';
                if ($(this).find('.woocommerce-Price-currencySymbol').length > 1) {
                    strCurrency = $(this).find('.woocommerce-Price-currencySymbol').eq(1).text();
                    productAmount = $(this).find('.woocommerce-Price-amount').eq(1).text();
                } else {
                    strCurrency = $(this).find('.woocommerce-Price-currencySymbol').text();
                    productAmount = $(this).find('.woocommerce-Price-amount').text();
                }

                productAmount = productAmount.replace(strCurrency, '');

                let total_amount = parseFloat(productAmount) * qty;
                total += total_amount;
            });
            total = total.toFixed(2);
            $('#acquired_total').val(total.toString());
        } else if ($('#variable_product').val() === 'yes') {
            let qty = parseFloat(jQuery('[name=quantity]').val());
            total = parseFloat(variation_price) * qty;
            total = total.toFixed(2);
            $('#acquired_total').val(total.toString());
        } else {
            let qty = parseFloat(jQuery('[name=quantity]').val());
            let total_amount = parseFloat(gateway_data.total) * qty;
            total_amount = total_amount.toFixed(2);
            total = total_amount.toString();
            $('#acquired_total').val(total);
        }
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
            timezone: browserTimezoneZoneOffset,
        };
    }

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
                url: googlepay_product.ajaxurl,
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
});
