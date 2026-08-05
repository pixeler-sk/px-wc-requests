<?php
/**
 * Automatic WooCommerce refund records for requests.
 *
 * When a request transitions into the status configured in the settings, a
 * refund for the requested items/quantities is recorded on the order via
 * wc_create_refund(). Money is NEVER sent to the payment gateway
 * (refund_payment = false) — the actual transfer stays a manual step for
 * safety. The value is the bookkeeping: exact amounts after discounts, tax,
 * reports and the order status handled by WooCommerce, instead of retyping
 * numbers from the request into the order refund form.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class Refunds {

	/** Request meta holding the created refund id (idempotency guard). */
	const REFUND_META = '_pxer_refund_id';

	public function setup(): void {
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 20, 3 );
	}

	/**
	 * Normalised refund config of a type (see RequestTypes::normalize()).
	 *
	 * @return array{enabled:bool,restock:bool,include_shipping:string}
	 */
	public static function config( string $type ): array {
		$def = RequestTypes::get( $type );

		return $def['refund'] ?? array(
			'enabled'          => false,
			'restock'          => false,
			'include_shipping' => 'if_full',
		);
	}

	/**
	 * Create the refund when a request transitions into the configured status.
	 * Mirrors Emails::on_transition(): only real transitions between registered
	 * request statuses count, so submitting a request can never trigger one.
	 */
	public function on_transition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( RequestPostType::POST_TYPE !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		$statuses = RequestTypes::all_statuses();
		if ( ! isset( $statuses[ $new_status ] ) || ! isset( $statuses[ $old_status ] ) ) {
			return;
		}

		$request = pxer_get_request( $post->ID );
		$type    = $request->get_type();
		$config  = self::config( $type );

		if ( empty( $config['enabled'] ) || $new_status !== Settings::get_refund_status( $type ) ) {
			return;
		}

		// Idempotency: one refund per request. Toggling the status back and
		// forth must not refund twice. If the stored refund was deleted in the
		// order admin (e.g. it was a mistake), allow creating a new one.
		$existing = (int) $request->get_meta( self::REFUND_META );
		if ( $existing && wc_get_order( $existing ) ) {
			return;
		}

		$result = $this->create_refund( $request );

		if ( is_wp_error( $result ) ) {
			RequestNotes::add_note(
				$post->ID,
				sprintf(
					/* translators: %s: error message */
					__( 'Automatic refund was not created: %s', 'px-wc-requests' ),
					$result->get_error_message()
				)
			);
		}
	}

	/**
	 * Record a WooCommerce refund for the items of a request.
	 *
	 * Quantities are capped at what is left to refund on each line; amounts are
	 * proportional to get_total() — the price actually paid after discounts —
	 * with tax split per rate. Shipping is added according to the type config
	 * (`include_shipping`: never | if_full | always, where "full" means the
	 * request covers every remaining item quantity of the order).
	 *
	 * @return \WC_Order_Refund|\WP_Error
	 */
	public function create_refund( Request $request ) {
		if ( ! $request->exists() ) {
			return new \WP_Error( 'pxer_refund', __( 'Request not found.', 'px-wc-requests' ) );
		}

		$order = $request->get_order();
		if ( ! $order ) {
			return new \WP_Error( 'pxer_refund', __( 'The linked order no longer exists.', 'px-wc-requests' ) );
		}

		if ( $order->get_remaining_refund_amount() <= 0 ) {
			return new \WP_Error( 'pxer_refund', __( 'The order is already fully refunded.', 'px-wc-requests' ) );
		}

		$items = $request->get_field( 'items', array() );
		if ( empty( $items ) || ! is_array( $items ) ) {
			return new \WP_Error( 'pxer_refund', __( 'The request has no items to refund.', 'px-wc-requests' ) );
		}

		$config     = self::config( $request->get_type() );
		$dp         = wc_get_price_decimals();
		$line_items = array();
		$amount     = 0.0;
		$full       = true; // request covers every remaining item quantity?

		foreach ( $order->get_items() as $item_id => $item ) {
			$ordered   = (int) $item->get_quantity();
			$refunded  = abs( $order->get_qty_refunded_for_item( $item_id ) );
			$remaining = max( 0, $ordered - $refunded );
			$requested = isset( $items[ $item_id ] ) ? (int) ( $items[ $item_id ]['quantity'] ?? 0 ) : 0;
			$qty       = min( $requested, $remaining );

			if ( $remaining > $qty ) {
				$full = false;
			}
			if ( $qty < 1 || $ordered < 1 ) {
				continue;
			}

			$total = round( (float) $item->get_total() * $qty / $ordered, $dp );
			$total = min( $total, round( (float) $item->get_total() - abs( (float) $order->get_total_refunded_for_item( $item_id ) ), $dp ) );
			$total = max( 0.0, $total );

			$refund_tax = array();
			$taxes      = $item->get_taxes();
			foreach ( (array) ( $taxes['total'] ?? array() ) as $rate_id => $tax_total ) {
				if ( '' === $tax_total || null === $tax_total ) {
					continue;
				}
				$tax = round( (float) $tax_total * $qty / $ordered, $dp );
				$tax = min( $tax, round( (float) $tax_total - abs( (float) $order->get_tax_refunded_for_item( $item_id, $rate_id ) ), $dp ) );
				if ( $tax > 0 ) {
					$refund_tax[ $rate_id ] = $tax;
					$amount               += $tax;
				}
			}

			$line_items[ $item_id ] = array(
				'qty'          => $qty,
				'refund_total' => $total,
				'refund_tax'   => $refund_tax,
			);
			$amount                += $total;
		}

		if ( ! $line_items ) {
			return new \WP_Error( 'pxer_refund', __( 'Nothing is left to refund for the requested items.', 'px-wc-requests' ) );
		}

		if ( 'always' === $config['include_shipping'] || ( 'if_full' === $config['include_shipping'] && $full ) ) {
			foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
				$total = round( (float) $item->get_total() - abs( (float) $order->get_total_refunded_for_item( $item_id, 'shipping' ) ), $dp );
				$total = max( 0.0, $total );

				$refund_tax = array();
				$taxes      = $item->get_taxes();
				foreach ( (array) ( $taxes['total'] ?? array() ) as $rate_id => $tax_total ) {
					if ( '' === $tax_total || null === $tax_total ) {
						continue;
					}
					$tax = round( (float) $tax_total - abs( (float) $order->get_tax_refunded_for_item( $item_id, $rate_id, 'shipping' ) ), $dp );
					if ( $tax > 0 ) {
						$refund_tax[ $rate_id ] = $tax;
						$amount               += $tax;
					}
				}

				if ( $total <= 0 && ! $refund_tax ) {
					continue;
				}

				$line_items[ $item_id ] = array(
					'qty'          => 0,
					'refund_total' => $total,
					'refund_tax'   => $refund_tax,
				);
				$amount                += $total;
			}
		}

		// wc_create_refund() rejects amounts above what is refundable.
		$amount = min( round( $amount, $dp ), (float) $order->get_remaining_refund_amount() );
		if ( $amount <= 0 ) {
			return new \WP_Error( 'pxer_refund', __( 'The calculated refund amount is zero.', 'px-wc-requests' ) );
		}

		$refund = wc_create_refund( array(
			'order_id'       => $order->get_id(),
			'amount'         => $amount,
			'reason'         => sprintf( '%s #%d', $request->get_type_label(), $request->get_id() ),
			'line_items'     => $line_items,
			'refund_payment' => false, // never touch the gateway — money moves manually.
			'restock_items'  => ! empty( $config['restock'] ),
		) );

		if ( is_wp_error( $refund ) ) {
			return $refund;
		}

		update_post_meta( $request->get_id(), self::REFUND_META, $refund->get_id() );

		$amount_text = wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) );
		$order->add_order_note( sprintf(
			/* translators: 1: refund amount, 2: request id */
			__( 'Refund of %1$s recorded from customer request #%2$d. The money was NOT sent — transfer it manually.', 'px-wc-requests' ),
			$amount_text,
			$request->get_id()
		) );
		RequestNotes::add_note( $request->get_id(), sprintf(
			/* translators: 1: refund amount, 2: order number */
			__( 'Refund of %1$s recorded on order #%2$s. The money was NOT sent — transfer it manually.', 'px-wc-requests' ),
			$amount_text,
			$order->get_order_number()
		) );

		/**
		 * Fires after a refund record is created from a request.
		 *
		 * @param \WC_Order_Refund $refund
		 * @param Request          $request
		 */
		do_action( 'pxer_refund_created', $refund, $request );

		return $refund;
	}
}
