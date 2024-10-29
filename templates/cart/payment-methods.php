<?php
/**
 * Google Pay button in Cart
 *
 * @package acquired-payment/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wc-acquired-cart-checkout-container cart_google_pay" 
<?php
if ( $cart_total == 0 ) {
	?>
	style="display: none"<?php } ?>>
	<ul class="wc_acquired_cart_payment_methods" style="list-style: none; width: 100%; text-align: center; margin: 0;">
		<?php if ( $after ) : ?>
			<li class="wc-acquired-payment-method or">
				<p class="wc-acquired-cart-or-google">
				</p>
			</li>
			<li
				class="wc-acquired-payment-method payment_method_<?php echo $gateways; ?>">
				<div class="payment-box">
					<div id="wc-acquired-<?php echo $gateways; ?>-container"></div>
					<?php echo $google_pay_instance->output_display_items( 'cart' ); ?>
				</div>
			</li>
		<?php endif; ?>
	</ul>
</div>
<?php
