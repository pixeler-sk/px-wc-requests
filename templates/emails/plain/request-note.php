<?php
/**
 * Customer "note added" email (plain text).
 *
 * @var \Pixeler\Requests\Request $request
 * @var string $customer_note
 * @var string $email_heading
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

if ( $request && $request->exists() ) {
	printf(
		/* translators: 1: type label, 2: request id */
		esc_html__( 'A note has been added to your request %1$s no. %2$d:', 'px-wc-requests' ),
		esc_html( $request->get_type_label() ),
		(int) $request->get_id()
	);
	echo "\n\n" . esc_html( wp_strip_all_tags( $customer_note ) ) . "\n";
}

echo "\n" . esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
