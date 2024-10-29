jQuery(
	function ($) {
		/**
		 * @returns {jQuery}
		 */
		function get_gateway_infor() {
			let container = 'li.payment_method_apple';
			return jQuery( container ).find( ".woocommerce_apple_gateway_data" ).data( 'gateway' );
		}

		let gateway_infor = get_gateway_infor();

		jQuery( ".single_variation_wrap" ).on(
			"show_variation",
			function (event, data) {
				$( '#acquired_variation_price' ).val( data.display_price );
				$( '#acquired_variation_id' ).val( data.variation_id );
				calculate_total_apple();
			}
		);

		jQuery( "form.cart" ).on(
			"change",
			function () {
				let variationPrice = $( '#acquired_variation_price' ).val();

				if (variationPrice == '' && $( '#variable_product' ).val() === 'yes') {
					return;
				} else {
					calculate_total_apple();
				}
			}
		);

		/**
		 * Calculate total
		 */
		function calculate_total_apple() {
			let total           = 0;
			let variation_price = $( '#acquired_variation_price' ).val();

			if ($( '#group_product' ).val() === 'yes') {
				$( 'table.woocommerce-grouped-product-list' ).find( 'tr' ).each(
					function () {
						let qty = parseFloat( $( this ).find( 'input' ).val() );
						if ($.isNumeric( qty )) {
							let productAmount = '';
							let strCurrency   = '';
							if ($( this ).find( '.woocommerce-Price-currencySymbol' ).length > 1) {
								strCurrency   = $( this ).find( '.woocommerce-Price-currencySymbol' ).eq( 1 ).text();
								productAmount = $( this ).find( '.woocommerce-Price-amount' ).eq( 1 ).text();
							} else {
								strCurrency   = $( this ).find( '.woocommerce-Price-currencySymbol' ).text();
								productAmount = $( this ).find( '.woocommerce-Price-amount' ).text();
							}

							productAmount = productAmount.replace( strCurrency, '' );

							let total_amount = parseFloat( productAmount ) * qty;
							total           += total_amount;
						}
					}
				);
				total = total.toFixed( 2 );
				$( '#acquired_total' ).val( total.toString() );
			} else if ($( '#variable_product' ).val() === 'yes') {
				let qty = parseFloat( jQuery( '[name=quantity]' ).val() );
				total   = parseFloat( variation_price ) * qty;
				total   = total.toFixed( 2 );
				$( '#acquired_total' ).val( total.toString() );
			} else {
				let qty          = parseFloat( jQuery( '[name=quantity]' ).val() );
				let total_amount = parseFloat( gateway_infor.total ) * qty;
				total_amount     = total_amount.toFixed( 2 );
				total            = total_amount.toString();
				$( '#acquired_total' ).val( total );
			}
		}
	}
);
