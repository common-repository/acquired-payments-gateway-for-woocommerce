<?php
/**
 * Iframe Form
 *
 * @package acquired-payment/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( isset($args['rebill_redirect_url']) && !empty( esc_html( $args['rebill_redirect_url'] ) ) ) {
	?>
	<script>
		window.location.href = "<?php echo esc_html( $args['rebill_redirect_url'] ); ?>";
	</script>
	<?php
} elseif ( ! empty( $args['url'] ) ) { ?>
	<div class="iframe-form">
		<iframe width="450" height="700" src="<?php echo esc_html( $args['url'] ); ?>"
				title="Acquired Payment i-frame" frameborder="0" allowfullscreen></iframe>
	</div>
	<script>
		window.addEventListener("message", function (event) {
		    if ( event.data.response ) {
                let response_code = event.data.response.code;
                if (response_code === "1") {
                    window.location.href = "<?php echo esc_html( $args['url_return_true'] ); ?>";
                } else {
                    window.location.href = "<?php echo esc_html( $args['url_return_false'] ); ?>" + response_code;
                }
            }
		});
	</script>
	<?php
} elseif ( ! empty( $args['error_message'] ) ) {
	?>
	<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout woocommerce-error">
		<?php echo esc_html( $args['error_message'] ); ?>
	</div>
	<a href="<?php echo esc_html( site_url( 'checkout' ) ); ?>" class="button">Back to checkout</a>
	<?php
}
