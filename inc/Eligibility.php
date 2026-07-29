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
		if ( ! $cfg['enabled'] ) {
			return true;
		}

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

		if ( ! self::eligible_items( $order, $type ) ) {
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

	public static function is_item_eligible( \WC_Order_Item_Product $item, \WC_Order $order, string $type ): bool {
		$cfg     = self::config( $type );
		$product = $item->get_product();

		$eligible = true;

		if ( ! $product ) {
			$eligible = false;
		} elseif ( self::is_item_excluded( $product, $type ) ) {
			$eligible = false;
		} else {
			$end = self::period_end( $order, $type, self::item_amount( $product, $type, $cfg ) );
			if ( $end && self::now() > $end->getTimestamp() ) {
				$eligible = false;
			}
		}

		return (bool) apply_filters( 'pxer_is_item_eligible', $eligible, $item, $order, $type );
	}

	/**
	 * Items of an order that may be included in a request of this type.
	 *
	 * @return array<int,\WC_Order_Item_Product> item_id => item
	 */
	public static function eligible_items( \WC_Order $order, string $type ): array {
		$cfg = self::config( $type );
		$out = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $cfg['enabled'] || ( $item instanceof \WC_Order_Item_Product && self::is_item_eligible( $item, $order, $type ) ) ) {
				$out[ $item_id ] = $item;
			}
		}

		/** @var array $out */
		$out = apply_filters( 'pxer_eligible_items', $out, $order, $type );

		return $out;
	}

	/**
	 * Current time as a site-timezone timestamp.
	 */
	private static function now(): int {
		return current_datetime()->getTimestamp();
	}
}
