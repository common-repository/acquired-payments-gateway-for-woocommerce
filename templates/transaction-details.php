<?php
/**
 * Transaction details
 *
 * @package acquired-payment/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $post;
$transaction_id          = get_post_meta( $post->ID, 'transaction_id', true );
$authorization_code      = get_post_meta( $post->ID, 'authorization_code', true );
$card_category           = get_post_meta( $post->ID, 'card_category', true );
$card_level              = get_post_meta( $post->ID, 'card_level', true );
$card_type               = get_post_meta( $post->ID, 'card_type', true );
$cardexp                 = get_post_meta( $post->ID, 'cardexp', true );
$cardholder_name         = get_post_meta( $post->ID, 'cardholder_name', true );
$cardnumber              = get_post_meta( $post->ID, 'cardnumber', true );
$message                 = get_post_meta( $post->ID, 'message', true );
$original_transaction_id = get_post_meta( $post->ID, 'original_transaction_id', true );
$timestamp               = get_post_meta( $post->ID, 'timestamp', true );

$helper             = new WC_Acquired_Helper();
$settle_status      = '';
$transaction_status = get_post_meta( $post->ID, 'transaction_status', true );
$date               = $post->post_date;
$order_id           = get_post_meta( $post->ID, 'order_id', true );
if ( empty( get_post_meta( $post->ID, 'order_id', true ) ) ) {
	$merchant_order_id = get_post_meta( $post->ID, 'merchant_order_id', true );
	$order_id          = substr( $merchant_order_id, 19, strlen( $merchant_order_id ) );
}
$transaction_type = get_post_meta( $post->ID, 'transaction_type', true );
if ( empty( $transaction_type ) ) {
	$transaction_type = get_post_meta( $order_id, '_transaction_type_acquired', true );
}
$order_data = wc_get_order( $order_id );
if ( ! empty( $order_data ) ) {
	$order_url    = empty( $order_data->get_edit_order_url() ) ? '' : $order_data->get_edit_order_url();
	$order_numver = empty( $order_data->get_order_number( $order_data ) ) ? '' : $order_data->get_order_number( $order_data );
} else {
	$order_url    = '#';
	$order_numver = '#';
}
global $wpdb;
$original_transaction_post = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM $wpdb->postmeta WHERE meta_key = 'transaction_id' 
AND  meta_value = %s LIMIT 1",
		$original_transaction_id
	),
	ARRAY_A
);
if ( ! empty( $original_transaction_post[0]['post_id'] ) ) {
	$edit_link = get_edit_post_link( $original_transaction_post[0]['post_id'] );
}
?>
	<div>
		<table>
			<tr>
				<th><?php echo esc_html( __( 'Transaction Id' ) ); ?></th>
				<td><?php echo esc_html( $transaction_id ); ?></td>
			</tr>
			<tr>
				<th><?php echo esc_html( __( 'Transaction Status' ) ); ?></th>
				<td><?php echo esc_html( $message ); ?></td>
			</tr>
			<tr>
				<th><?php echo esc_html( __( 'Order Id' ) ); ?></th>
				<td>
					<a href="<?php echo esc_url( $order_url ); ?>" target="_blank">
						#<?php echo esc_html( $order_numver ); ?>
					</a>
				</td>
			</tr>
			<tr>
				<th><?php echo esc_html( __( 'Transaction Type' ) ); ?></th>
				<td><?php echo esc_html( $transaction_type ); ?></td>
			</tr>
			<tr>
				<?php
				if ( ! empty( $edit_link ) ) {
					?>
					<th><?php echo esc_html( __( 'Original Transaction ID' ) ); ?></th>
					<td>
					   <?php echo esc_html( $original_transaction_id ); ?>
					</td>
					<?php
				}
				?>
			</tr>
			<tr>
				<?php
				if ( ! empty( $authorization_code ) ) {
					?>
					<th><?php echo esc_html( __( 'Authorization Code' ) ); ?></th>
					<td><?php echo esc_html( $authorization_code ); ?></td>
					<?php
				}
				?>
			</tr>
			<tr>
				<?php
				if ( ! empty( $card_category ) ) {
					?>
					<th><?php echo esc_html( __( 'Card Category' ) ); ?></th>
					<td><?php echo esc_html( $card_category ); ?></td>
					<?php
				}
				?>
			</tr>
			<tr>
				<?php
				if ( ! empty( $card_level ) ) {
					?>
					<th><?php echo esc_html( __( 'Card Level' ) ); ?></th>
					<td><?php echo esc_html( $card_level ); ?></td>
					<?php
				}
				?>
			</tr>
			<tr>
				<?php
				if ( ! empty( $card_type ) ) {
					?>
					<th><?php echo esc_html( __( 'Card Type' ) ); ?></th>
					<td><?php echo esc_html( $card_type ); ?></td>
					<?php
				}
				?>
			</tr>
			<tr>
				<?php
				if ( ! empty( $cardexp ) ) {
					?>
					<th><?php echo esc_html( __( 'Card Expiry' ) ); ?></th>
					<td><?php echo esc_html( $cardexp ); ?></td>
					<?php
				}
				?>
			</tr>
			<tr>
				<?php
				if ( ! empty( $cardholder_name ) ) {
					?>
					<th><?php echo esc_html( __( 'Card Name' ) ); ?></th>
					<td><?php echo esc_html( $cardholder_name ); ?></td>
					<?php
				}
				?>
			</tr>
			<tr>
				<?php
				if ( ! empty( $cardnumber ) ) {
					?>
					<th><?php echo esc_html( __( 'Card Number' ) ); ?></th>
					<td><?php echo esc_html( $cardnumber ); ?></td>
					<?php
				}
				?>
			</tr>
		</table>
	</div>
<?php
