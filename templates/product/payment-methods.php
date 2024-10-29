<?php
/**
 * Google Pay button in product
 *
 * @package acquired-payment/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
?>
<div class="wc-acquired-product-checkout-container product_google_pay">
	<ul class="wc_acquired_product_payment_methods" style="list-style: none">
		<li class="payment_method_<?php echo esc_attr( $gateways ); ?>">
				<div class="payment-box">
					<input name="acquired_variation_id" id="acquired_variation_id" value="" type="hidden">
					<input name="acquired_variation_price" id="acquired_variation_price" value="" type="hidden">
					<input name="acquired_total" id="acquired_total" value="" type="hidden">
					<input name="group_product" id="group_product" value="<?php echo $group_product; ?>" type="hidden">
					<input name="group_product_data" id="group_product_data" value="" type="hidden">
					<input name="variable_product" id="variable_product" value="<?php echo $variabled_product; ?>" type="hidden">
					<input name="acquired_product_qty" id="acquired_product_qty" value="" type="hidden">
					<div id="wc-acquired-<?php echo $gateways; ?>-container"></div>
					<?php echo $gateway_instance->output_display_items( 'product' ); ?>
				</div>
		</li>
	</ul>
</div>
<?php
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
