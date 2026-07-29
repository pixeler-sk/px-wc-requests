<?php
/**
 * Customer confirmation email (plain text).
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

	printf(
		/* translators: 1: type label, 2: request id */
		esc_html__( 'We have received your request %1$s no. %2$d and will get back to you soon.', 'px-wc-requests' ),
		esc_html( $request->get_type_label() ),
		(int) $request->get_id()
	);
	echo "\n\n";

	echo esc_html__( 'Type:', 'px-wc-requests' ) . ' ' . esc_html( $request->get_type_label() ) . "\n";
	echo esc_html__( 'Status:', 'px-wc-requests' ) . ' ' . esc_html( $request->get_status_label() ) . "\n";
	echo esc_html__( 'Order number:', 'px-wc-requests' ) . ' ' . esc_html( $order ? $order->get_order_number() : $request->get_order_id() ) . "\n";
	if ( ! empty( $data['iban'] ) ) {
		echo esc_html__( 'IBAN:', 'px-wc-requests' ) . ' ' . esc_html( pxer_format_iban( $data['iban'] ) ) . "\n";
	}
}

echo "\n" . esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
