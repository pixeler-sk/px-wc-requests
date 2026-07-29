<?php
/**
 * Declarative field schema. One definition drives the frontend input, the
 * admin metabox field, sanitisation and validation. Field types are pluggable
 * via the `pxer_field_types` filter.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class FieldSchema {

	/**
	 * Fill in missing keys of a field definition.
	 */
	public static function normalize( array $field ): array {
		$defaults = array(
			'key'                  => '',
			'type'                 => 'text',
			'label'                => '',
			'required'             => false,
			'prefill'              => null,   // WC_Order getter name without "get_", or callable
			'show_in'              => array( 'form', 'admin', 'email' ),
			'help'                 => '',
			'placeholder'          => '',
			'width'                => 'full', // full|half
			'options'              => array(), // select/radio: value => label
			'show_if'              => array(), // conditional visibility: ['field' => key, 'value' => scalar|array]
			// order_items only:
			'item_mode'            => 'multiple',
			'item_reason_required' => false,
			'item_reason_label'    => __( 'Reason', 'px-wc-requests' ),
			// file only:
			'accept'               => array( 'jpg', 'jpeg', 'png' ),
			'max_size'             => 7168000, // 7 MB
		);

		return wp_parse_args( $field, $defaults );
	}

	// =====================================================================
	// Reusable field group builders
	// =====================================================================

	/**
	 * @return array<int,array>
	 */
	public static function customer_fields(): array {
		return array(
			self::normalize( array( 'key' => 'firstname', 'label' => __( 'First name', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_first_name', 'width' => 'half' ) ),
			self::normalize( array( 'key' => 'lastname', 'label' => __( 'Last name', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_last_name', 'width' => 'half' ) ),
			self::normalize( array( 'key' => 'email', 'type' => 'email', 'label' => __( 'E-mail', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_email', 'width' => 'half' ) ),
			self::normalize( array( 'key' => 'phone', 'type' => 'tel', 'label' => __( 'Phone', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_phone', 'width' => 'half' ) ),
			self::normalize( array( 'key' => 'address', 'label' => __( 'Street and number', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_address_1' ) ),
			self::normalize( array( 'key' => 'postcode', 'label' => __( 'Postcode', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_postcode', 'width' => 'half' ) ),
			self::normalize( array( 'key' => 'city', 'label' => __( 'City', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_city', 'width' => 'half' ) ),
		);
	}

	/**
	 * @return array<int,array>
	 */
	public static function bank_fields(): array {
		return array(
			self::normalize( array( 'key' => 'account_name', 'label' => __( 'Account name / recipient', 'px-wc-requests' ), 'required' => true, 'width' => 'half' ) ),
			self::normalize( array( 'key' => 'iban', 'type' => 'iban', 'label' => __( 'IBAN', 'px-wc-requests' ), 'required' => true, 'help' => __( 'for a possible refund', 'px-wc-requests' ), 'width' => 'half' ) ),
		);
	}

	public static function consent_field(): array {
		return self::normalize( array(
			'key'      => 'agree',
			'type'     => 'checkbox',
			'label'    => __( 'I agree with the processing of personal data', 'px-wc-requests' ),
			'required' => true,
			'show_in'  => array( 'form' ),
		) );
	}

	public static function order_items_field( string $mode = 'multiple', bool $reason_required = false ): array {
		return self::normalize( array(
			'key'                  => 'items',
			'type'                 => 'order_items',
			'label'                => __( 'Select items', 'px-wc-requests' ),
			'required'             => true,
			'item_mode'            => $mode,
			'item_reason_required' => $reason_required,
			'item_reason_label'    => 'single' === $mode
				? __( 'Describe the defect', 'px-wc-requests' )
				: __( 'Reason', 'px-wc-requests' ),
			'show_in'              => array( 'form', 'admin' ),
		) );
	}

	public static function images_field(): array {
		return self::normalize( array(
			'key'      => 'images',
			'type'     => 'file',
			'label'    => __( 'Photos', 'px-wc-requests' ),
			'required' => false,
			'help'     => __( 'Maximum size per photo is 7 MB (JPG, PNG)', 'px-wc-requests' ),
			'show_in'  => array( 'form' ),
		) );
	}

	// =====================================================================
	// Prefill / sanitise
	// =====================================================================

	public static function prefill_value( array $field, ?\WC_Order $order ) {
		if ( ! $order || ! $field['prefill'] ) {
			return '';
		}
		if ( is_callable( $field['prefill'] ) ) {
			return call_user_func( $field['prefill'], $order );
		}
		$getter = 'get_' . $field['prefill'];

		return method_exists( $order, $getter ) ? $order->{$getter}() : '';
	}

	/**
	 * Sanitise a single scalar field value from raw input.
	 */
	public static function sanitize_value( array $field, $raw ) {
		switch ( $field['type'] ) {
			case 'email':
				return sanitize_email( (string) $raw );
			case 'textarea':
				return sanitize_textarea_field( (string) $raw );
			case 'iban':
				return pxer_normalize_iban( sanitize_text_field( (string) $raw ) );
			case 'checkbox':
				return $raw ? 'yes' : '';
			case 'select':
			case 'radio':
				$val = sanitize_text_field( (string) $raw );
				// Constrain to the declared options when any are set.
				if ( ! empty( $field['options'] ) && ! isset( $field['options'][ $val ] ) ) {
					return '';
				}
				return $val;
			default:
				return sanitize_text_field( (string) $raw );
		}
	}

	// =====================================================================
	// Frontend rendering
	// =====================================================================

	/**
	 * Render a frontend field.
	 *
	 * @param array          $field
	 * @param mixed          $value
	 * @param \WC_Order|null $order   Needed for order_items.
	 * @param array          $context Optional: 'eligible_ids' => int[] to limit items.
	 */
	public static function render_field( array $field, $value, ?\WC_Order $order = null, array $context = array() ): void {
		if ( ! in_array( 'form', $field['show_in'], true ) ) {
			return;
		}

		ob_start();
		switch ( $field['type'] ) {
			case 'order_items':
				self::render_order_items( $field, (array) $value, $order, $context );
				break;
			case 'file':
				self::render_file( $field );
				break;
			case 'checkbox':
				self::render_checkbox( $field, $value );
				break;
			case 'select':
				self::render_select( $field, $value );
				break;
			case 'radio':
				self::render_radio( $field, $value );
				break;
			default:
				self::render_input( $field, $value );
		}
		$html = ob_get_clean();

		// Wrap in a conditional container when the field declares a `show_if`
		// dependency. Visibility (and disabling of its inputs) is toggled on the
		// frontend by assets/ajax-form.js; the value is still schema-validated.
		if ( ! empty( $field['show_if']['field'] ) ) {
			$show_values = (array) ( $field['show_if']['value'] ?? array() );
			printf(
				// The wrapper is the grid item, so it must carry the width class —
				// otherwise a full-width field collapses to one grid column.
				'<div class="pxer-conditional pxer-field-%4$s" data-pxer-show-if="%1$s" data-pxer-show-value="%2$s">%3$s</div>',
				esc_attr( (string) $field['show_if']['field'] ),
				esc_attr( implode( ',', array_map( 'strval', $show_values ) ) ),
				$html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner field HTML already escaped by the render_* methods
				esc_attr( $field['width'] )
			);

			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner field HTML already escaped by the render_* methods
	}

	private static function render_select( array $field, $value ): void {
		?>
		<p class="pxer-field pxer-field-<?php echo esc_attr( $field['width'] ); ?>">
			<label for="pxer-<?php echo esc_attr( $field['key'] ); ?>">
				<?php echo esc_html( $field['label'] ); ?>
				<?php if ( $field['required'] ) : ?><span class="required">*</span><?php endif; ?>
			</label>
			<select id="pxer-<?php echo esc_attr( $field['key'] ); ?>" name="<?php echo esc_attr( $field['key'] ); ?>"
				<?php echo $field['required'] ? 'required' : ''; ?>>
				<option value="">&mdash;</option>
				<?php foreach ( (array) $field['options'] as $opt_value => $opt_label ) : ?>
					<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( (string) $opt_value, (string) $value ); ?>>
						<?php echo esc_html( $opt_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	private static function render_radio( array $field, $value ): void {
		?>
		<div class="pxer-field pxer-field-<?php echo esc_attr( $field['width'] ); ?> pxer-field-radio">
			<span class="pxer-radio-legend">
				<?php echo esc_html( $field['label'] ); ?>
				<?php if ( $field['required'] ) : ?><span class="required">*</span><?php endif; ?>
			</span>
			<?php foreach ( (array) $field['options'] as $opt_value => $opt_label ) : ?>
				<label class="pxer-radio-option">
					<input type="radio" name="<?php echo esc_attr( $field['key'] ); ?>"
					       value="<?php echo esc_attr( $opt_value ); ?>" <?php checked( (string) $opt_value, (string) $value ); ?>
						<?php echo $field['required'] ? 'required' : ''; ?>>
					<?php echo esc_html( $opt_label ); ?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function render_input( array $field, $value ): void {
		$input_type = in_array( $field['type'], array( 'email', 'tel' ), true ) ? $field['type'] : 'text';
		?>
		<p class="pxer-field pxer-field-<?php echo esc_attr( $field['width'] ); ?>">
			<label for="pxer-<?php echo esc_attr( $field['key'] ); ?>">
				<?php echo esc_html( $field['label'] ); ?>
				<?php if ( $field['required'] ) : ?><span class="required">*</span><?php endif; ?>
			</label>
			<input id="pxer-<?php echo esc_attr( $field['key'] ); ?>"
			       type="<?php echo esc_attr( $input_type ); ?>"
			       name="<?php echo esc_attr( $field['key'] ); ?>"
			       value="<?php echo esc_attr( (string) $value ); ?>"
			       placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
				<?php echo $field['required'] ? 'required' : ''; ?>>
		</p>
		<?php
	}

	private static function render_checkbox( array $field, $value ): void {
		?>
		<p class="pxer-field pxer-field-full pxer-field-checkbox">
			<label for="pxer-<?php echo esc_attr( $field['key'] ); ?>">
				<input id="pxer-<?php echo esc_attr( $field['key'] ); ?>" type="checkbox"
				       name="<?php echo esc_attr( $field['key'] ); ?>" value="yes" <?php checked( 'yes', $value ); ?>
					<?php echo $field['required'] ? 'required' : ''; ?>>
				<?php
				if ( 'agree' === $field['key'] ) {
					printf(
						/* translators: %s: privacy policy link */
						esc_html__( 'I agree with the %s.', 'px-wc-requests' ),
						'<a href="' . esc_url( get_privacy_policy_url() ) . '" target="_blank">' . esc_html__( 'processing of personal data', 'px-wc-requests' ) . '</a>'
					);
				} else {
					echo esc_html( $field['label'] );
				}
				?>
			</label>
		</p>
		<?php
	}

	private static function render_file( array $field ): void {
		$accept = '.' . implode( ',.', array_map( 'sanitize_text_field', $field['accept'] ) );
		?>
		<p class="pxer-field pxer-field-full">
			<label for="pxer-<?php echo esc_attr( $field['key'] ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
			<input id="pxer-<?php echo esc_attr( $field['key'] ); ?>" type="file"
			       name="<?php echo esc_attr( $field['key'] ); ?>[]" multiple
			       accept="<?php echo esc_attr( $accept ); ?>">
		</p>
		<?php
	}

	/**
	 * @param array    $context May contain 'eligible_ids' => int[] to filter rows.
	 */
	private static function render_order_items( array $field, array $value, ?\WC_Order $order, array $context = array() ): void {
		if ( ! $order ) {
			return;
		}
		$single       = 'single' === $field['item_mode'];
		$eligible_ids = $context['eligible_ids'] ?? null; // null = no filtering
		?>
		<div class="pxer-field pxer-field-full pxer-order-items" data-mode="<?php echo esc_attr( $field['item_mode'] ); ?>">
			<h3><?php echo esc_html( $field['label'] ); ?> <?php if ( $field['required'] ) : ?><span class="required">*</span><?php endif; ?></h3>
			<table class="pxer-items-table">
				<?php foreach ( $order->get_items() as $item_id => $item ) :
					if ( is_array( $eligible_ids ) && ! in_array( $item_id, $eligible_ids, true ) ) {
						continue;
					}
					$product   = $item->get_product();
					$selected  = isset( $value[ $item_id ] );
					$qty_value = $selected ? ( $value[ $item_id ]['quantity'] ?? 1 ) : 1;
					$reason    = $selected ? ( $value[ $item_id ]['reason'] ?? '' ) : '';
					?>
					<tr class="pxer-item-row">
						<td class="pxer-item-select">
							<?php if ( $single ) : ?>
								<input class="pxer-item-radio" type="radio" id="pxer-item-<?php echo esc_attr( $item_id ); ?>"
								       name="selected_item" value="<?php echo esc_attr( $item_id ); ?>" <?php checked( $selected ); ?>>
							<?php else : ?>
								<input class="pxer-item-check" type="checkbox" id="pxer-item-<?php echo esc_attr( $item_id ); ?>"
								       name="items[<?php echo esc_attr( $item_id ); ?>][selected]" value="1" <?php checked( $selected ); ?>>
							<?php endif; ?>
						</td>
						<td class="pxer-item-image">
							<?php echo $product ? $product->get_image( 'thumbnail' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
						<td class="pxer-item-name">
							<label for="pxer-item-<?php echo esc_attr( $item_id ); ?>">
								<?php echo esc_html( $item->get_name() ); ?><br>
								<small><?php esc_html_e( 'Quantity:', 'px-wc-requests' ); ?> <?php echo esc_html( $item->get_quantity() ); ?></small>
							</label>
						</td>
						<?php if ( ! $single ) : ?>
							<td class="pxer-item-qty">
								<input type="number" min="1" max="<?php echo esc_attr( $item->get_quantity() ); ?>"
								       name="items[<?php echo esc_attr( $item_id ); ?>][quantity]" value="<?php echo esc_attr( $qty_value ); ?>">
							</td>
						<?php endif; ?>
					</tr>
					<tr class="pxer-item-reason" data-item="<?php echo esc_attr( $item_id ); ?>" style="<?php echo $selected ? '' : 'display:none'; ?>">
						<td colspan="<?php echo $single ? 3 : 4; ?>">
							<label><?php echo esc_html( $field['item_reason_label'] ); ?><?php echo $field['item_reason_required'] ? ' *' : ''; ?></label>
							<textarea rows="4" name="<?php echo $single ? 'reason[' . esc_attr( $item_id ) . ']' : 'items[' . esc_attr( $item_id ) . '][reason]'; ?>"><?php echo esc_textarea( $reason ); ?></textarea>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>
		<?php
	}
}
