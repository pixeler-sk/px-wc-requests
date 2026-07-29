<?php
/**
 * Admin "new request" email (HTML).
 *
 * @var \Pixeler\Requests\Request $request
 * @var string $email_heading
 * @var WC_Email $email
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

if ( $request && $request->exists() ) {
	pxer_render_request_summary( $request );
} else {
	echo '<p>' . esc_html__( 'This is a sample preview of the email.', 'px-wc-requests' ) . '</p>';
	pxer_render_request_summary_sample();
}

do_action( 'woocommerce_email_footer', $email );
