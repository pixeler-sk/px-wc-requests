<?php
/**
 * Registry of request types. Each type is a declarative config: label set,
 * statuses, field schema, legal period. Withdrawal and Claim ship by default;
 * other eshops register/modify types via the `pxer_request_types` filter.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class RequestTypes {

	/**
	 * Memoised, filtered type registry.
	 *
	 * @var array<string,array>|null
	 */
	private static ?array $types = null;

	/**
	 * Get all registered types (keyed by id).
	 *
	 * @return array<string,array>
	 */
	public static function all(): array {
		if ( null === self::$types ) {
			$defaults = array(
				'withdrawal' => self::default_withdrawal(),
				'claim'      => self::default_claim(),
			);

			/**
			 * Filter the request type registry.
			 *
			 * @param array $defaults Type definitions keyed by id.
			 */
			$types = apply_filters( 'pxer_request_types', $defaults );

			self::$types = array();
			foreach ( $types as $id => $type ) {
				self::$types[ $id ] = self::normalize( $id, $type );
			}
		}

		return self::$types;
	}

	public static function get( string $id ): ?array {
		return self::all()[ $id ] ?? null;
	}

	public static function exists( string $id ): bool {
		return isset( self::all()[ $id ] );
	}

	public static function ids(): array {
		return array_keys( self::all() );
	}

	/**
	 * Field schema for a type.
	 *
	 * @return array<int,array>
	 */
	public static function get_fields( string $id ): array {
		$type = self::get( $id );

		return $type['fields'] ?? array();
	}

	public static function get_statuses( string $id ): array {
		$type = self::get( $id );

		return $type['statuses'] ?? array();
	}

	/**
	 * Normalised legal period config for a type.
	 *
	 * @return array{enabled:bool,unit:string,amount:int,start_statuses:array}
	 */
	public static function get_period( string $id ): array {
		$type = self::get( $id );

		return $type['period'] ?? array();
	}

	public static function get_default_status( string $id ): string {
		$type = self::get( $id );
		if ( ! $type ) {
			return '';
		}

		return $type['default_status'] ?: (string) array_key_first( $type['statuses'] );
	}

	public static function get_label( string $id, bool $plural = false ): string {
		$type = self::get( $id );
		if ( ! $type ) {
			return $id;
		}

		return $plural ? $type['label_plural'] : $type['label'];
	}

	/**
	 * Map a status slug to its human label across all types.
	 */
	public static function status_label( string $status ): string {
		foreach ( self::all() as $type ) {
			if ( isset( $type['statuses'][ $status ] ) ) {
				return $type['statuses'][ $status ];
			}
		}

		$obj = get_post_status_object( $status );

		return $obj ? $obj->label : $status;
	}

	/**
	 * Union of all status definitions across every type (for registration).
	 *
	 * @return array<string,string> slug => label
	 */
	public static function all_statuses(): array {
		$all = array();
		foreach ( self::all() as $type ) {
			foreach ( $type['statuses'] as $slug => $label ) {
				$all[ $slug ] = $label;
			}
		}

		return $all;
	}

	/**
	 * Ensure a type definition has every expected key.
	 */
	private static function normalize( string $id, array $type ): array {
		$defaults = array(
			'id'             => $id,
			'label'          => ucfirst( $id ),
			'label_plural'   => ucfirst( $id ),
			'menu_label'     => ucfirst( $id ),
			'button_label'   => '', // My Account orders action button; falls back to label
			'statuses'       => array(),
			'default_status' => '',
			'fields'         => array(),
			'period'         => array(),
			'emails'         => array(
				'admin_new'              => true,
				'customer_confirmation'  => true,
				'customer_status_change' => true,
			),
			'item_mode'       => 'multiple', // multiple|single
			'refund_due_days' => 0,          // statutory refund deadline reminder (0 = off)
			'legal_notice'    => '',         // info text shown on the form
			'refund'          => array(),    // automatic refund record, see below
		);

		$type       = wp_parse_args( $type, $defaults );
		$type['id'] = $id;

		/**
		 * Filter the status set of a single request type. Lets extensions add,
		 * remove or relabel statuses without redefining the whole type.
		 *
		 * @param array  $statuses slug => label
		 * @param string $id       Request type id.
		 */
		$type['statuses'] = apply_filters( 'pxer_request_statuses', $type['statuses'], $id );

		// Normalise the refund sub-array. `enabled` only makes the feature
		// available for the type — it activates when the admin picks a trigger
		// status in the settings (Settings::get_refund_status()).
		$type['refund'] = wp_parse_args( $type['refund'], array(
			'enabled'          => false,
			'restock'          => false,     // restock refunded items
			'include_shipping' => 'if_full', // never|if_full|always
		) );

		// Normalise the period sub-array.
		$type['period'] = wp_parse_args( $type['period'], array(
			'enabled'        => false,
			'unit'           => 'days',          // days|months
			'amount'         => 14,
			'start_statuses' => array( 'completed' ),
		) );

		// Normalise each field through FieldSchema so partial defs are safe.
		$type['fields'] = array_map(
			static fn( $field ) => FieldSchema::normalize( $field ),
			$type['fields']
		);

		return $type;
	}

	// =====================================================================
	// Default type definitions
	// =====================================================================

	/**
	 * Lean shared status lifecycle used by the built-in types.
	 * Extend per type via the `pxer_request_statuses` filter.
	 *
	 * @return array<string,string>
	 */
	private static function default_statuses(): array {
		return array(
			'pxer_received'    => __( 'Received', 'px-wc-requests' ),
			'pxer_in_progress' => __( 'In progress', 'px-wc-requests' ),
			'pxer_resolved'    => __( 'Resolved', 'px-wc-requests' ),
		);
	}

	private static function default_withdrawal(): array {
		return array(
			'label'          => __( 'Withdrawal from contract', 'px-wc-requests' ),
			'label_plural'   => __( 'Withdrawals from contract', 'px-wc-requests' ),
			'menu_label'     => __( 'Withdrawals', 'px-wc-requests' ),
			'button_label'    => __( 'Withdraw from contract', 'px-wc-requests' ),
			'item_mode'       => 'multiple',
			'statuses'        => self::default_statuses(),
			'default_status'  => 'pxer_received',
			'refund_due_days' => 14,
			'refund'          => array( 'enabled' => true ),
			'legal_notice'    => __( 'You have the right to withdraw from this contract within 14 days without giving any reason. The withdrawal period starts on the day you take possession of the goods. You bear the direct cost of returning the goods. We will refund all payments within 14 days of being informed of your decision to withdraw.', 'px-wc-requests' ),
			'period'          => array(
				'enabled'        => true,
				'unit'           => 'days',
				'amount'         => 14,
				'start_statuses' => array( 'completed' ),
			),
			'fields'         => array_merge(
				array( FieldSchema::order_items_field( 'multiple', false ) ),
				FieldSchema::customer_fields(),
				FieldSchema::bank_fields(),
				array( FieldSchema::consent_field() )
			),
		);
	}

	private static function default_claim(): array {
		return array(
			'label'          => __( 'Warranty claim', 'px-wc-requests' ),
			'label_plural'   => __( 'Warranty claims', 'px-wc-requests' ),
			'menu_label'     => __( 'Claims', 'px-wc-requests' ),
			'button_label'   => __( 'File a claim', 'px-wc-requests' ),
			'item_mode'      => 'single',
			'statuses'       => self::default_statuses(),
			'default_status' => 'pxer_received',
			'legal_notice'   => __( 'The statutory warranty applies for 24 months from receipt of the goods. Please describe the defect as precisely as possible and attach photos if available.', 'px-wc-requests' ),
			'period'         => array(
				'enabled'        => true,
				'unit'           => 'months',
				'amount'         => 24,
				'start_statuses' => array( 'completed' ),
			),
			'fields'         => array_merge(
				array( FieldSchema::order_items_field( 'single', true ) ),
				array( FieldSchema::images_field() ),
				FieldSchema::customer_fields(),
				FieldSchema::bank_fields(),
				array( FieldSchema::consent_field() )
			),
		);
	}
}
