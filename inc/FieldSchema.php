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
			'autocomplete'         => '',      // HTML autocomplete token (given-name, email, …); '' = by type
			'inputmode'            => '',      // HTML inputmode hint; '' = none
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
			self::normalize( array( 'key' => 'firstname', 'label' => __( 'First name', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_first_name', 'width' => 'half', 'autocomplete' => 'given-name' ) ),
			self::normalize( array( 'key' => 'lastname', 'label' => __( 'Last name', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_last_name', 'width' => 'half', 'autocomplete' => 'family-name' ) ),
			self::normalize( array( 'key' => 'email', 'type' => 'email', 'label' => __( 'E-mail', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_email', 'width' => 'half', 'autocomplete' => 'email' ) ),
			self::normalize( array( 'key' => 'phone', 'type' => 'tel', 'label' => __( 'Phone', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_phone', 'width' => 'half', 'autocomplete' => 'tel' ) ),
			self::normalize( array( 'key' => 'address', 'label' => __( 'Street and number', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_address_1', 'autocomplete' => 'address-line1' ) ),
			self::normalize( array( 'key' => 'postcode', 'label' => __( 'Postcode', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_postcode', 'width' => 'half', 'autocomplete' => 'postal-code' ) ),
			self::normalize( array( 'key' => 'city', 'label' => __( 'City', 'px-wc-requests' ), 'required' => true, 'prefill' => 'billing_city', 'width' => 'half', 'autocomplete' => 'address-level2' ) ),
		);
	}

	/**
		* @return array<int,array>
		*/
	public static function bank_fields(): array {
		return array(
			self::normalize( array( 'key' => 'account_name', 'label' => __( 'Account name / recipient', 'px-wc-requests' ), 'required' => true, 'width' => 'half', 'autocomplete' => 'name' ) ),
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
			case 'textarea':
				self::render_textarea( $field, $value );
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

	/**
		* Field id used by label `for`, error and help ids.
		*/
	private static function field_id( array $field ): string {
		return 'pxer-' . $field['key'];
	}

	/**
		* The required marker is visual only: `required` on the control already
		* tells assistive technology, a read-out "star" would be noise.
		*/
	private static function required_mark( array $field ): void {
		if ( $field['required'] ) {
			echo '<span class="required" aria-hidden="true">*</span>';
		}
	}

	/**
		* Help text under the label, linked to the control via aria-describedby.
		*/
	private static function render_help( array $field ): void {
		if ( '' === (string) $field['help'] ) {
			return;
		}
		printf(
			'<span class="pxer-help" id="%1$s-help">%2$s</span>',
			esc_attr( self::field_id( $field ) ),
			esc_html( $field['help'] )
		);
	}

	/**
		* Common attributes of a control: required, aria-describedby (help),
		* autocomplete and inputmode. Escaped.
		*/
	private static function control_attrs( array $field ): string {
		$attrs = array();
		if ( $field['required'] ) {
			$attrs[] = 'required';
		}
		if ( '' !== (string) $field['help'] ) {
			$attrs[] = 'aria-describedby="' . esc_attr( self::field_id( $field ) . '-help' ) . '"';
		}
		if ( '' !== (string) $field['autocomplete'] ) {
			$attrs[] = 'autocomplete="' . esc_attr( $field['autocomplete'] ) . '"';
		}
		if ( '' !== (string) $field['inputmode'] ) {
			$attrs[] = 'inputmode="' . esc_attr( $field['inputmode'] ) . '"';
		}

		return implode( ' ', $attrs );
	}

	private static function render_select( array $field, $value ): void {
		$id = self::field_id( $field );
		?>
		<p class="pxer-field pxer-field-<?php echo esc_attr( $field['width'] ); ?>">
			<label for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $field['label'] ); ?>
				<?php self::required_mark( $field ); ?>
			</label>
			<?php self::render_help( $field ); ?>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $field['key'] ); ?>"
				<?php echo self::control_attrs( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in control_attrs() ?>>
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
		$id = self::field_id( $field );
		?>
		<div class="pxer-field pxer-field-<?php echo esc_attr( $field['width'] ); ?> pxer-field-radio"
			role="radiogroup" aria-labelledby="<?php echo esc_attr( $id ); ?>-legend"
			<?php echo $field['required'] ? 'aria-required="true"' : ''; ?>
			<?php echo '' !== (string) $field['help'] ? 'aria-describedby="' . esc_attr( $id . '-help' ) . '"' : ''; ?>>
			<span class="pxer-radio-legend" id="<?php echo esc_attr( $id ); ?>-legend">
				<?php echo esc_html( $field['label'] ); ?>
				<?php self::required_mark( $field ); ?>
			</span>
			<?php self::render_help( $field ); ?>
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
		$id         = self::field_id( $field );
		$input_type = in_array( $field['type'], array( 'email', 'tel' ), true ) ? $field['type'] : 'text';
		$extra      = '';
		if ( 'iban' === $field['type'] ) {
			// No autocomplete token exists for an IBAN; keep the browser from
			// offering unrelated values and from "correcting" the code.
			$extra = 'data-pxer-type="iban" spellcheck="false" autocapitalize="characters" maxlength="42"'
				. ( '' === (string) $field['autocomplete'] ? ' autocomplete="off"' : '' );
		}
		?>
		<p class="pxer-field pxer-field-<?php echo esc_attr( $field['width'] ); ?>">
			<label for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $field['label'] ); ?>
				<?php self::required_mark( $field ); ?>
			</label>
			<?php self::render_help( $field ); ?>
			<input id="<?php echo esc_attr( $id ); ?>"
				type="<?php echo esc_attr( $input_type ); ?>"
				name="<?php echo esc_attr( $field['key'] ); ?>"
				value="<?php echo esc_attr( (string) $value ); ?>"
				<?php if ( '' !== (string) $field['placeholder'] ) : ?>placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"<?php endif; ?>
				<?php echo self::control_attrs( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in control_attrs() ?>
				<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attributes ?>>
		</p>
		<?php
	}

	private static function render_textarea( array $field, $value ): void {
		$id = self::field_id( $field );
		?>
		<p class="pxer-field pxer-field-<?php echo esc_attr( $field['width'] ); ?>">
			<label for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $field['label'] ); ?>
				<?php self::required_mark( $field ); ?>
			</label>
			<?php self::render_help( $field ); ?>
			<textarea id="<?php echo esc_attr( $id ); ?>" rows="4"
				name="<?php echo esc_attr( $field['key'] ); ?>"
				<?php if ( '' !== (string) $field['placeholder'] ) : ?>placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"<?php endif; ?>
				<?php echo self::control_attrs( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in control_attrs() ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
		</p>
		<?php
	}

	private static function render_checkbox( array $field, $value ): void {
		$id = self::field_id( $field );
		?>
		<p class="pxer-field pxer-field-full pxer-field-checkbox">
			<label for="<?php echo esc_attr( $id ); ?>">
				<input id="<?php echo esc_attr( $id ); ?>" type="checkbox"
					name="<?php echo esc_attr( $field['key'] ); ?>" value="yes" <?php checked( 'yes', $value ); ?>
					<?php echo self::control_attrs( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in control_attrs() ?>>
				<span>
				<?php
				if ( 'agree' === $field['key'] ) {
					printf(
						/* translators: %s: privacy policy link */
						esc_html__( 'I agree with the %s.', 'px-wc-requests' ),
						'<a href="' . esc_url( get_privacy_policy_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'processing of personal data', 'px-wc-requests' ) . '</a>'
					);
				} else {
					echo esc_html( $field['label'] );
				}
				?>
				<?php self::required_mark( $field ); ?>
				</span>
			</label>
			<?php self::render_help( $field ); ?>
		</p>
		<?php
	}

	private static function render_file( array $field ): void {
		$id     = self::field_id( $field );
		$accept = '.' . implode( ',.', array_map( 'sanitize_text_field', $field['accept'] ) );
		?>
		<p class="pxer-field pxer-field-full">
			<label for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $field['label'] ); ?>
				<?php self::required_mark( $field ); ?>
			</label>
			<?php self::render_help( $field ); ?>
			<input id="<?php echo esc_attr( $id ); ?>" type="file"
				name="<?php echo esc_attr( $field['key'] ); ?>[]" multiple
				accept="<?php echo esc_attr( $accept ); ?>"
				<?php echo self::control_attrs( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in control_attrs() ?>>
		</p>
		<?php
	}

	/**
		* @param array    $context May contain 'eligible_ids' => int[] to filter rows
		*                          and 'eligible_qty' => int[] item_id => units still free.
		*/
	private static function render_order_items( array $field, array $value, ?\WC_Order $order, array $context = array() ): void {
		if ( ! $order ) {
			return;
		}
		$single       = 'single' === $field['item_mode'];
		$eligible_ids = $context['eligible_ids'] ?? null; // null = no filtering
		$eligible_qty = $context['eligible_qty'] ?? array();
		$id           = self::field_id( $field );
		?>
		<div class="pxer-field pxer-field-full pxer-order-items" data-mode="<?php echo esc_attr( $field['item_mode'] ); ?>"
			id="<?php echo esc_attr( $id ); ?>" role="<?php echo $single ? 'radiogroup' : 'group'; ?>"
			aria-labelledby="<?php echo esc_attr( $id ); ?>-legend"
			<?php echo $field['required'] ? 'data-pxer-required="1"' : ''; ?>
			<?php echo $field['required'] && $single ? 'aria-required="true"' : ''; ?>
			<?php echo '' !== (string) $field['help'] ? 'aria-describedby="' . esc_attr( $id . '-help' ) . '"' : ''; ?>>
			<h3 id="<?php echo esc_attr( $id ); ?>-legend"><?php echo esc_html( $field['label'] ); ?> <?php self::required_mark( $field ); ?></h3>
			<?php self::render_help( $field ); ?>
			<table class="pxer-items-table" role="presentation">
				<?php foreach ( $order->get_items() as $item_id => $item ) :
					if ( is_array( $eligible_ids ) && ! in_array( $item_id, $eligible_ids, true ) ) {
						continue;
					}
					$product   = $item->get_product();
					$selected  = isset( $value[ $item_id ] );
					$max_qty   = isset( $eligible_qty[ $item_id ] ) ? (int) $eligible_qty[ $item_id ] : (int) $item->get_quantity();
					$qty_value = $selected ? min( $max_qty, (int) ( $value[ $item_id ]['quantity'] ?? 1 ) ) : 1;
					$reason    = $selected ? ( $value[ $item_id ]['reason'] ?? '' ) : '';
					$reason_id = 'pxer-reason-' . $item_id;
					/* translators: %s: product name */
					$qty_label = sprintf( __( 'Number of units: %s', 'px-wc-requests' ), $item->get_name() );
					?>
					<tr class="pxer-item-row">
						<td class="pxer-item-select">
							<?php if ( $single ) : ?>
								<input class="pxer-item-radio" type="radio" id="pxer-item-<?php echo esc_attr( $item_id ); ?>"
									name="selected_item" value="<?php echo esc_attr( $item_id ); ?>" <?php checked( $selected ); ?>
									aria-controls="<?php echo esc_attr( $reason_id ); ?>">
							<?php else : ?>
								<input class="pxer-item-check" type="checkbox" id="pxer-item-<?php echo esc_attr( $item_id ); ?>"
									name="items[<?php echo esc_attr( $item_id ); ?>][selected]" value="1" <?php checked( $selected ); ?>
									aria-controls="<?php echo esc_attr( $reason_id ); ?>">
							<?php endif; ?>
						</td>
						<td class="pxer-item-image">
							<?php echo $product ? $product->get_image( 'thumbnail', array( 'alt' => '' ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- decorative, the name is in the label ?>
						</td>
						<td class="pxer-item-name">
							<label for="pxer-item-<?php echo esc_attr( $item_id ); ?>">
								<?php echo esc_html( $item->get_name() ); ?><br>
								<small>
									<?php esc_html_e( 'Quantity:', 'px-wc-requests' ); ?> <?php echo esc_html( $item->get_quantity() ); ?>
									<?php if ( $max_qty < (int) $item->get_quantity() ) : ?>
										<?php
										/* translators: %d: number of units not yet part of another request */
										echo esc_html( sprintf( __( '(%d still available — the rest is already part of another request)', 'px-wc-requests' ), $max_qty ) );
										?>
									<?php endif; ?>
								</small>
							</label>
						</td>
						<?php if ( ! $single ) : ?>
							<td class="pxer-item-qty">
								<input type="number" min="1" max="<?php echo esc_attr( $max_qty ); ?>" step="1" inputmode="numeric"
									id="pxer-qty-<?php echo esc_attr( $item_id ); ?>"
									name="items[<?php echo esc_attr( $item_id ); ?>][quantity]" value="<?php echo esc_attr( $qty_value ); ?>"
									aria-label="<?php echo esc_attr( $qty_label ); ?>">
							</td>
						<?php endif; ?>
					</tr>
					<tr class="pxer-item-reason" data-item="<?php echo esc_attr( $item_id ); ?>" id="<?php echo esc_attr( $reason_id ); ?>" style="<?php echo $selected ? '' : 'display:none'; ?>">
						<td colspan="<?php echo $single ? 3 : 4; ?>">
							<label for="<?php echo esc_attr( $reason_id ); ?>-text">
								<?php echo esc_html( $field['item_reason_label'] ); ?>
								<?php if ( $field['item_reason_required'] ) : ?><span class="required" aria-hidden="true">*</span><?php endif; ?>
								<span class="screen-reader-text"><?php echo esc_html( '(' . $item->get_name() . ')' ); ?></span>
							</label>
							<textarea rows="4" id="<?php echo esc_attr( $reason_id ); ?>-text"
								name="<?php echo $single ? 'reason[' . esc_attr( $item_id ) . ']' : 'items[' . esc_attr( $item_id ) . '][reason]'; ?>"
								<?php echo $field['item_reason_required'] ? 'data-pxer-required="1" aria-required="true"' : ''; ?>><?php echo esc_textarea( $reason ); ?></textarea>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>
		<?php
	}
}
