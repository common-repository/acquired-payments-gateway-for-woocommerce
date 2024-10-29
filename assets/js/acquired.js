const PAYMENT_METHOD = 'acquired';
jQuery(
	function ($) {
		let $body          = $( document.body );
		let $form          = $( 'form.woocommerce-checkout' );
		let $order_review  = $( '#order_review' );
		let $pay_for_order = $( 'form[id=order_review]' ).find( 'button[id=place_order]' );
		let redirect_url   = '';

		$body.css( 'position', 'relative' );
		$body.append( '<div class="acquired-overlay"></div><div id="acquired-form"><div class="acquired-iframe"></div></div>' );

		/**
		 * Show Acquired Payment Iframe
		 *
		 * @returns {boolean}
		 */
		function showIframeForm(e, data_result) {
			e.preventDefault();
			let json      = $form.serializeArray().reduce(
				function (acc, item) {
					acc[item.name] = item.value;
					return acc;
				},
				{}
			);
			json.order_id = data_result.order_id;
			if (json.payment_method === PAYMENT_METHOD) {
				e.preventDefault();
				$.ajax(
					{
						type: "POST",
						url: wc_acquired.ajaxurl,
						data: {
							action: 'checkout_get_payment_link',
							json: json
						},
						success: function (response) {
							let acquired_iframe = $( '.acquired-iframe' );
							$( '.acquired-overlay' ).addClass( "acquired-show" );
							$( '.blockOverlay' ).attr( "style", "display: none" );
							acquired_iframe.addClass( "acquired-form-show" );

							if (response.indexOf( "Rebill" ) === 0) {
								window.location.href = response.slice( 6, response.length );
							} else if (response.indexOf( "https" ) > -1) {
								let str_res = response.slice( response.indexOf( "https" ), response.length )
								acquired_iframe.html( '<iframe width="450" height="700" src="' + str_res + '" title="Acquired Payment Form" frameborder="0" ></iframe>' );
								// $('.woocommerce-notices-wrapper').html('');
							} else {
								$form.removeClass( 'processing' ).unblock();
								$( '.acquired-show' ).css( "position", "" );
								submitError( $form, response );
							}
						}
					}
				);
				return "acquired_check";
			}
		}

		$form.on( 'checkout_place_order_success', showIframeForm );

		/**
		 * Show Acquired Payment Iframe in page My Account
		 *
		 */

		$('form#add_payment_method').on('submit',function(e){
			e.preventDefault();
			e.stopPropagation();

			const elemChecked  = $(this).find('input[name="payment_method"]:checked');
			const payment_method = elemChecked.val();
			if (payment_method === PAYMENT_METHOD) {
				$.ajax(
					{
						type: "POST",
						url: wc_acquired.ajaxurl,
						data: {
							action: 'my_account_get_payment_link',
						},
						success: function (response) {
							let acquired_iframe = $( '.acquired-iframe' );
							$( '.acquired-overlay' ).addClass( "acquired-show" );
							$( '.blockOverlay' ).attr( "style", "display: none" );
							acquired_iframe.addClass( "acquired-form-show" );

							if(response.result == 'success'){
								const str_res = response.payment_link;
								acquired_iframe.html( '<iframe width="450" height="700" src="' + str_res + '" title="Acquired Payment Form" frameborder="0" ></iframe>' );
							}else{
								window.location.href = response.url;
							}
						}
					}
				);
				return false;
			}
		});

		/**
		 * Pay for order
		 *
		 * @returns {boolean}
		 */
		$( 'form#order_review' ).on(
			'click',
			'#place_order',
			function (e) {
				e.preventDefault();
				e.stopPropagation();
				let json = $order_review.serializeArray().reduce(
					function (acc, item) {
						acc[item.name] = item.value;
						return acc;
					},
					{}
				);
				if (json.payment_method === PAYMENT_METHOD) {
					$.ajax(
						{
							type: "POST",
							url: wc_acquired.ajaxurl,
							data: {
								action: 'checkout_get_payment_link',
								json: json
							},
							success: function (response) {
								let acquired_iframe = $( '.acquired-iframe' );
								$( '.acquired-overlay' ).addClass( "acquired-show" );
								acquired_iframe.addClass( "acquired-form-show" );

								if (response.indexOf( "https" ) > -1) {
									let str_res = response.slice( response.indexOf( "https" ), response.length );
									acquired_iframe.html( '<iframe width="450" height="650" src="' + str_res + '" title="Acquired Payment Form" frameborder="0" ></iframe>' );
								} else {
									$order_review.removeClass( 'processing' ).unblock();
									$( '.acquired-show' ).css( "position", "" );
									let error_res = response.slice( response.indexOf( "Acquired Response" ), response.length )
									submitError( $order_review, error_res );
									return false;
								}
							}
						}
					);
				} else {
					submitError( "Can not get Acquired payment link, please contact to your merchant!" )
				}
				return false;
			}
		);

		$( 'div.acquired-overlay' ).on(
			'click',
			function () {
				$( '.acquired-iframe' ).html( "" );
				$( document.body ).trigger( 'update_checkout' );
				submitError( $form, "Error processing checkout. Please try again." );
				$( this ).removeClass( 'acquired-show' );
			}
		);

		/**
		 * Display error message
		 *
		 * @param {string} messages
		 */
		function submitError(form, messages) {
			$( 'div.acquired-overlay' ).removeClass( 'acquired-show' );

			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();

			if (messages === '' || typeof messages === "undefined" ) {
				messages = 'Error processing checkout. Please try again.';
			}

			form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout woocommerce-error">' + messages + '</div>' );
			form.removeClass( 'processing' ).unblock();

			form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();

			var scrollElement = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );

			if ( ! scrollElement.length) {
				scrollElement = $( '.form.checkout' );
			}

			$.scroll_to_notices( scrollElement );

			$body.trigger( 'checkout_error' );
		}
	}
);
