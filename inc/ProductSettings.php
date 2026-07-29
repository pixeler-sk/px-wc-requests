<?php
/**
 * Per-product overrides for request eligibility: exclude a product from a
 * request type, or override its period (days/months). Rendered in the product
 * "General" data panel. Inspired by WPify Woo's per-product withdrawal/warranty
 * exclusions and overrides.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class ProductSettings {

	public function setup(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_fields' ) );
	}

	/**
	 * Types that enforce a period are the ones worth overriding per product.
	 *
	 * @return array<string,array>
	 */
	private function period_types(): array {
		return array_filter( RequestTypes::all(), static fn( $t ) => ! empty( $t['period']['enabled'] ) );
	}

	public function render_fields(): void {
		$types = $this->period_types();
		if ( empty( $types ) ) {
			return;
		}

		echo '<div class="options_group">';
		echo '<p class="form-field"><strong>' . esc_html__( 'Customer requests', 'px-wc-requests' ) . '</strong></p>';

		foreach ( $types as $id => $type ) {
			$unit_label = 'months' === $type['period']['unit']
				? __( 'months', 'px-wc-requests' )
				: __( 'days', 'px-wc-requests' );

			woocommerce_wp_checkbox( array(
				'id'          => '_pxer_' . $id . '_excluded',
				/* translators: %s: request type label */
				'label'       => sprintf( __( 'Exclude from: %s', 'px-wc-requests' ), $type['label_plural'] ),
				'description' => __( 'This product cannot be selected in this request type.', 'px-wc-requests' ),
			) );

			woocommerce_wp_text_input( array(
				'id'                => '_pxer_' . $id . '_period_override',
				/* translators: 1: request type label, 2: unit (days/months) */
				'label'             => sprintf( __( 'Period override (%1$s, in %2$s)', 'px-wc-requests' ), $type['label'], $unit_label ),
				'description'       => __( 'Leave empty to use the global period.', 'px-wc-requests' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			) );
		}

		echo '</div>';
	}

	public function save_fields( int $product_id ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		foreach ( $this->period_types() as $id => $type ) {
			$excluded = isset( $_POST[ '_pxer_' . $id . '_excluded' ] ) ? 'yes' : 'no';
			$product->update_meta_data( '_pxer_' . $id . '_excluded', $excluded );

			$override_key = '_pxer_' . $id . '_period_override';
			$override     = isset( $_POST[ $override_key ] ) ? absint( wp_unslash( $_POST[ $override_key ] ) ) : 0;
			if ( $override > 0 ) {
				$product->update_meta_data( $override_key, $override );
			} else {
				$product->delete_meta_data( $override_key );
			}
		}

		$product->save();
	}
}
