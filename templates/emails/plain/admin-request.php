<?php
/**
 * Admin "new request" email (plain text).
 *
 * @var \Pixeler\Requests\Request $request
 * @var string $email_heading
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

if ( $request && $request->exists() ) {
	$order = $request->get_order();
	$data  = $request->get_data();

	echo esc_html__( 'Type:', 'px-wc-requests' ) . ' ' . esc_html( $request->get_type_label() ) . "\n";
	echo esc_html__( 'Request number:', 'px-wc-requests' ) . ' ' . esc_html( $request->get_id() ) . "\n";
	if ( $order ) {
		echo esc_html__( 'Order:', 'px-wc-requests' ) . ' ' . esc_html( $order->get_order_number() ) . "\n";
	}
	if ( ! empty( $data['iban'] ) ) {
		echo esc_html__( 'IBAN:', 'px-wc-requests' ) . ' ' . esc_html( pxer_format_iban( $data['iban'] ) ) . "\n";
	}
	if ( ! empty( $data['email'] ) ) {
		echo esc_html__( 'E-mail:', 'px-wc-requests' ) . ' ' . esc_html( $data['email'] ) . "\n";
	}
}

echo "\n" . esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
