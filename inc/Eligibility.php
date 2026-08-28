<?php
/**
 * Legal-period eligibility engine.
 *
 * Computes whether a request type is still open for an order based on the
 * order completion date and a configurable period (days for withdrawal,
 * months for warranty claims). Supports per-product exclusions and per-product
 * period overrides — inspired by the WPify Woo Withdrawal & Claims module.
 *
 * Product meta:
 *   _pxer_{type}_excluded         'yes' → item excluded from this request type
 *   _pxer_{type}_period_override  int   → per-product days/months override
 *
 * Filters:
 *   pxer_request_period_end ( DateTimeImmutable|null $end, WC_Order $order, string $type )
 *   pxer_is_item_eligible   ( bool $eligible, WC_Order_Item $item, WC_Order $order, string $type )
 *   pxer_eligible_items     ( array $items, WC_Order $order, string $type )
 *   pxer_item_available_qty ( int $qty, WC_Order_Item $item, WC_Order $order, string $type )
 *   pxer_closed_statuses    ( string[] $statuses )
 *
 * Per-unit reservation: every unit of a line item may sit in at most one open
 * request at a time (any type). Closed requests release their units again so a
 * repaired item can be claimed a second time — except types flagged
 * `consumes_items` (withdrawal): units of their resolved requests stay consumed,
 * the goods were returned. WooCommerce refunds count as consumed as well.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class Eligibility {

	/**
	 * Effective period config: type defaults merged with the admin settings
	 * overrides (amount + start statuses).
	 *
	 * @return array{enabled:bool,unit:string,amount:int,start_statuses:array}
	 */
	public static function config( string $type ): array {
		$period = RequestTypes::get_period( $type );

		$amount = (int) get_option( 'pxer_' . $type . '_period_amount', $period['amount'] ?? 14 );
		$starts = get_option( 'pxer_' . $type . '_period_start_statuses', $period['start_statuses'] ?? array( 'completed' ) );
		if ( ! is_array( $starts ) || empty( $starts ) ) {
			$starts = $period['start_statuses'] ?? array( 'completed' );
		}

		return array(
			'enabled'        => ! empty( $period['enabled'] ),
			'unit'           => ( $period['unit'] ?? 'days' ) === 'months' ? 'months' : 'days',
			'amount'         => max( 1, $amount ),
			'start_statuses' => array_map( static fn( $s ) => preg_replace( '/^wc-/', '', (string) $s ), $starts ),
		);
	}

	/**
	 * The moment the period clock starts ticking for an order.
	 */
	public static function start_date( \WC_Order $order ): ?\DateTimeImmutable {
		$date = $order->get_date_completed() ?: $order->get_date_paid() ?: $order->get_date_created();
		if ( ! $date ) {
			return null;
		}

		return ( new \DateTimeImmutable() )->setTimestamp( $date->getTimestamp() );
	}

	/**
	 * Has the order reached a status that starts the clock?
	 */
	public static function is_started( \WC_Order $order, array $cfg ): bool {
		$status = $order->get_status();
		if ( in_array( $status, $cfg['start_statuses'], true ) ) {
			return true;
		}
		// Order may have moved past the start status — accept the recorded date.
		if ( in_array( 'completed', $cfg['start_statuses'], true ) && $order->get_date_completed() ) {
			return true;
		}
		if ( in_array( 'processing', $cfg['start_statuses'], true ) && $order->get_date_paid() ) {
			return true;
		}

		return false;
	}

	/**
	 * The deadline for the request, optionally with a per-item amount override.
	 */
	public static function period_end( \WC_Order $order, string $type, ?int $amount_override = null ): ?\DateTimeImmutable {
		$cfg   = self::config( $type );
		$start = self::start_date( $order );
		if ( ! $cfg['enabled'] || ! $start ) {
			return null;
		}

		$amount = $amount_override ?: $cfg['amount'];
		$end    = $start->modify( sprintf( '+%d %s', $amount, $cfg['unit'] ) )->setTime( 23, 59, 59 );

		/** @var \DateTimeImmutable|null $end */
		$end = apply_filters( 'pxer_request_period_end', $end, $order, $type );

		return $end;
	}

	/**
	 * Is the period currently open for the order as a whole?
	 */
	public static function is_open( \WC_Order $order, string $type ): bool {
		$cfg = self::config( $type );
		if ( ! $cfg['enabled'] ) {
			return true;
		}
		if ( ! self::is_started( $order, $cfg ) ) {
			return false;
		}
		$end = self::period_end( $order, $type );

		return ! $end || self::now() <= $end->getTimestamp();
	}

	/**
	 * Overall gate used by the controller. Returns true or a WP_Error with a
	 * customer-facing reason.
	 *
	 * @return true|\WP_Error
	 */
	public static function gate( \WC_Order $order, string $type ) {
		$cfg = self::config( $type );

		if ( $cfg['enabled'] ) {
			if ( ! self::is_started( $order, $cfg ) ) {
				return new \WP_Error( 'period_not_started', __( 'The period has not started yet — the order is not completed.', 'px-wc-requests' ) );
			}

			$end = self::period_end( $order, $type );
			if ( $end && self::now() > $end->getTimestamp() ) {
				return new \WP_Error(
					'period_expired',
					sprintf(
						/* translators: %s: deadline date */
						__( 'The deadline for this request has already passed (it ended on %s).', 'px-wc-requests' ),
						wp_date( get_option( 'date_format' ), $end->getTimestamp() )
					)
				);
			}
		}

		if ( ! self::eligible_items( $order, $type ) ) {
			// Distinguish "everything is already in another request" from
			// "nothing qualifies" — the customer can act on the former.
			foreach ( $order->get_items() as $item ) {
				if ( $item instanceof \WC_Order_Item_Product && self::passes_product_rules( $item, $order, $type, $cfg ) ) {
					return new \WP_Error( 'items_reserved', __( 'All items of this order are already covered by an existing request.', 'px-wc-requests' ) );
				}
			}

			return new \WP_Error( 'no_eligible_items', __( 'There are no eligible items for this request.', 'px-wc-requests' ) );
		}

		return true;
	}

	// =====================================================================
	// Per-item eligibility
	// =====================================================================

	public static function is_item_excluded( \WC_Product $product, string $type ): bool {
		if ( 'yes' === $product->get_meta( '_pxer_' . $type . '_excluded' ) ) {
			return true;
		}
		$parent_id = $product->get_parent_id();
		if ( $parent_id ) {
			$parent = wc_get_product( $parent_id );
			if ( $parent && 'yes' === $parent->get_meta( '_pxer_' . $type . '_excluded' ) ) {
				return true;
			}
		}

		return false;
	}

	private static function item_amount( \WC_Product $product, string $type, array $cfg ): int {
		$override = (int) $product->get_meta( '_pxer_' . $type . '_period_override' );
		if ( $override < 1 ) {
			$parent_id = $product->get_parent_id();
			if ( $parent_id ) {
				$parent = wc_get_product( $parent_id );
				$override = $parent ? (int) $parent->get_meta( '_pxer_' . $type . '_period_override' ) : 0;
			}
		}

		return $override > 0 ? $override : $cfg['amount'];
	}

	/**
	 * Product-level rules: exclusion flag and (when the period is enabled)
	 * the per-item deadline. Quantity reservations are checked separately.
	 */
	private static function passes_product_rules( \WC_Order_Item_Product $item, \WC_Order $order, string $type, array $cfg ): bool {
		$product = $item->get_product();

		if ( $product && self::is_item_excluded( $product, $type ) ) {
			return false;
		}
		if ( ! $cfg['enabled'] ) {
			return true;
		}
		if ( ! $product ) {
			return false;
		}
		$end = self::period_end( $order, $type, self::item_amount( $product, $type, $cfg ) );

		return ! $end || self::now() <= $end->getTimestamp();
	}

	public static function is_item_eligible( \WC_Order_Item_Product $item, \WC_Order $order, string $type ): bool {
		$eligible = self::passes_product_rules( $item, $order, $type, self::config( $type ) )
			&& self::available_qty( $item, $order, $type ) > 0;

		return (bool) apply_filters( 'pxer_is_item_eligible', $eligible, $item, $order, $type );
	}

	/**
	 * Items of an order that may be included in a request of this type.
	 *
	 * @return array<int,\WC_Order_Item_Product> item_id => item
	 */
	public static function eligible_items( \WC_Order $order, string $type ): array {
		$out = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( $item instanceof \WC_Order_Item_Product && self::is_item_eligible( $item, $order, $type ) ) {
				$out[ $item_id ] = $item;
			}
		}

		/** @var array $out */
		$out = apply_filters( 'pxer_eligible_items', $out, $order, $type );

		return $out;
	}

	/**
	 * Units still available for a new request, per eligible item.
	 *
	 * @return array<int,int> item_id => available quantity
	 */
	public static function eligible_quantities( \WC_Order $order, string $type ): array {
		$out = array();
		foreach ( self::eligible_items( $order, $type ) as $item_id => $item ) {
			$out[ (int) $item_id ] = self::available_qty( $item, $order, $type );
		}

		return $out;
	}

	// =====================================================================
	// Per-unit reservations
	// =====================================================================

	/**
	 * Request statuses that no longer hold their items (the case is closed).
	 *
	 * @return string[]
	 */
	public static function closed_statuses(): array {
		/**
		 * @param string[] $statuses Post status slugs treated as closed.
		 */
		return (array) apply_filters( 'pxer_closed_statuses', array( 'pxer_resolved', 'pxer_rejected' ) );
	}

	/**
	 * Units of a line item a customer may still put into a new request:
	 * ordered − units in open requests (any type) − units already gone
	 * (WooCommerce refund or a resolved request of a consuming type, whichever
	 * is larger so an auto-refund is never counted twice).
	 */
	public static function available_qty( \WC_Order_Item_Product $item, \WC_Order $order, string $type ): int {
		$item_id  = (int) $item->get_id();
		$ordered  = (int) $item->get_quantity();
		$refunded = abs( (int) $order->get_qty_refunded_for_item( $item_id ) );
		$closed   = self::closed_statuses();

		$open     = 0;
		$consumed = 0;
		foreach ( pxer_get_requests_by_order_id( (int) $order->get_id() ) as $post ) {
			$data = get_post_meta( $post->ID, '_pxer_data', true );
			if ( ! is_array( $data ) || empty( $data['items'][ $item_id ] ) ) {
				continue;
			}
			$row = $data['items'][ $item_id ];
			$qty = is_array( $row ) ? max( 1, (int) ( $row['quantity'] ?? 1 ) ) : 1;

			if ( ! in_array( $post->post_status, $closed, true ) ) {
				$open += $qty;
			} elseif ( 'pxer_rejected' !== $post->post_status ) {
				$req_type = (string) get_post_meta( $post->ID, '_pxer_type', true );
				if ( RequestTypes::consumes_items( $req_type ) ) {
					$consumed += $qty;
				}
			}
		}

		$available = max( 0, $ordered - $open - max( $refunded, $consumed ) );

		/**
		 * @param int                    $available Units still free for a new request.
		 * @param \WC_Order_Item_Product $item
		 * @param \WC_Order              $order
		 * @param string                 $type      Type of the request being prepared.
		 */
		return max( 0, (int) apply_filters( 'pxer_item_available_qty', $available, $item, $order, $type ) );
	}

	/**
	 * Current time as a site-timezone timestamp.
	 */
	private static function now(): int {
		return current_datetime()->getTimestamp();
	}
}
