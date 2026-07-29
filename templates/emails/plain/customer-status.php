<?php
/**
 * Customer "status changed" email (plain text).
 *
 * @var \Pixeler\Requests\Request $request
 * @var string $new_status_label
 * @var string $email_heading
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

if ( $request && $request->exists() ) {
	printf(
		/* translators: 1: type label, 2: request id, 3: status */
		esc_html__( 'The status of your request %1$s no. %2$d has changed to: %3$s', 'px-wc-requests' ),
		esc_html( $request->get_type_label() ),
		(int) $request->get_id(),
		esc_html( $new_status_label )
	);
	echo "\n";
}

echo "\n" . esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
