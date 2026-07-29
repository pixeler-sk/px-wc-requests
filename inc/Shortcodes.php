<?php
/**
 * Frontend shortcode + order-search 2FA flow.
 *
 * Flow:
 *   1. No `?key`  → render order search form (email + order number).
 *   2. Search submit → verified on `template_redirect` (BEFORE any output, so the
 *      redirect works even on page builders like Elementor) → redirect with key.
 *   3. `?key` valid → render the request form for that order + type.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class Shortcodes {

	public function setup(): void {
		add_shortcode( 'pxer_request_form', array( $this, 'render' ) );
		// Handle the search submit before headers are sent (shortcodes render too
		// late to call wp_safe_redirect()).
		add_action( 'template_redirect', array( $this, 'maybe_redirect_search' ) );
	}

	/**
	 * Step 2: verify the order-search submission and redirect to `?key=…` before
	 * any HTML is output. On mismatch, queue an error notice for the form.
	 */
	public function maybe_redirect_search(): void {
		if ( is_admin() || ! is_singular() ) {
			return;
		}
		if ( empty( $_REQUEST['email'] ) || empty( $_REQUEST['order_number'] ) || empty( $_REQUEST['type'] ) ) {
			return;
		}
		if ( ! empty( $_GET['key'] ) ) {
			return; // Already resolved.
		}

		$type = sanitize_text_field( wp_unslash( $_REQUEST['type'] ) );
		if ( ! RequestTypes::exists( $type ) ) {
			return;
		}

		$email        = sanitize_email( wp_unslash( $_REQUEST['email'] ) );
		$order_number = absint( $_REQUEST['order_number'] );
		$order        = wc_get_order( $order_number );

		if ( $order && $email && hash_equals( strtolower( $order->get_billing_email() ), strtolower( $email ) ) ) {
			wp_safe_redirect( add_query_arg( array(
				'key'  => $order->get_order_key(),
				'type' => $type,
			), get_permalink( get_queried_object_id() ) ) );
			exit;
		}

		wc_add_notice( __( 'No order found with this e-mail and number.', 'px-wc-requests' ), 'error' );
	}

	public function render( $atts ): string {
		$atts = shortcode_atts( array( 'type' => 'withdrawal' ), $atts, 'pxer_request_form' );
		$type = sanitize_text_field( $atts['type'] );

		if ( ! RequestTypes::exists( $type ) ) {
			return wc_print_notice(
				sprintf( /* translators: %s: type id */ __( 'Unknown request type: %s', 'px-wc-requests' ), esc_html( $type ) ),
				'error',
				array(),
				true
			);
		}

		// Step 3: valid order key present → show the form.
		if ( ! empty( $_GET['key'] ) ) {
			$order_id = wc_get_order_id_by_order_key( sanitize_text_field( wp_unslash( $_GET['key'] ) ) );
			if ( $order_id ) {
				return RequestController::show_form( (int) $order_id, $type );
			}

			return wc_print_notice( __( 'Order not found.', 'px-wc-requests' ), 'error', array(), true );
		}

		// Step 1: search form (a failed search queued its error on template_redirect).
		return pxer_get_template_html( 'order-search-form.php', array(
			'type'     => $type,
			'type_def' => RequestTypes::get( $type ),
		) );
	}
}
