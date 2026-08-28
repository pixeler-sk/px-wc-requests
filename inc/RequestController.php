<?php
/**
 * Handles the public submission flow (AJAX) and renders the request form.
 * One controller drives every type via the field schema.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class RequestController {

	const AJAX_ACTION = 'pxer_submit_request';
	const NONCE       = 'pxer_submit_request';

	public function setup(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'ajax_submit' ) );
	}

	// =====================================================================
	// Frontend form
	// =====================================================================

	public static function show_form( int $order_id, string $type ): string {
		wp_enqueue_style( 'pxer-form' );
		wp_enqueue_script( 'pxer-form' );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return wc_print_notice( __( 'Order not found.', 'px-wc-requests' ), 'error', array(), true );
		}

		// Enforce the legal period before rendering the form.
		$gate = Eligibility::gate( $order, $type );
		if ( is_wp_error( $gate ) ) {
			return wc_print_notice( $gate->get_error_message(), 'error', array(), true );
		}

		$eligible_qty = Eligibility::eligible_quantities( $order, $type );
		$eligible_ids = array_keys( $eligible_qty );

		$fields = RequestTypes::get_fields( $type );
		$values = array();
		foreach ( $fields as $field ) {
			$values[ $field['key'] ] = FieldSchema::prefill_value( $field, $order );
		}
		$values['items'] = array();

		return pxer_get_template_html( 'request-form.php', array(
			'order'        => $order,
			'type'         => $type,
			'type_def'     => RequestTypes::get( $type ),
			'fields'       => $fields,
			'values'       => $values,
			'eligible_ids' => $eligible_ids,
			'eligible_qty' => $eligible_qty,
			'deadline'     => Eligibility::period_end( $order, $type ),
			'past'         => pxer_get_requests_by_order_id( $order_id, $type ),
		) );
	}

	// =====================================================================
	// AJAX submit
	// =====================================================================

	/**
	 * Shared submission core used by both the AJAX and REST entry points.
	 *
	 * @param array  $params     Raw (slashed) input — form/body params.
	 * @param string $type       Request type id.
	 * @param bool   $check_bot  Run honeypot/time-trap (UI flows only).
	 *
	 * @return int|\WP_Error  Request id, or WP_Error. Code `silent_bot` means
	 *                        the caller should pretend success and drop it.
	 */
	public function submit( array $params, string $type, bool $check_bot = true ) {
		if ( ! RequestTypes::exists( $type ) ) {
			return new \WP_Error( 'type', __( 'Invalid request type.', 'px-wc-requests' ) );
		}

		if ( $check_bot && Security::is_bot( $params ) ) {
			return new \WP_Error( 'silent_bot', 'silent' );
		}

		$data       = $this->sanitize( $params, $type );
		$validation = $this->validate( $data, $type );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$order  = wc_get_order( $data['order_id'] );
		$limits = $order ? Security::check_limits( $order, $type, $data ) : true;
		if ( is_wp_error( $limits ) ) {
			return $limits;
		}

		$request_id = $this->create( $data, $type );
		if ( is_wp_error( $request_id ) ) {
			return $request_id;
		}

		$this->after_success( $request_id, $type );

		return $request_id;
	}

	public function ajax_submit(): void {
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], self::NONCE ) ) {
			$this->fail( '', __( 'Security check failed. Please reload the page and try again.', 'px-wc-requests' ) );
		}

		$result = $this->submit( $_POST, $type );

		if ( is_wp_error( $result ) && 'silent_bot' !== $result->get_error_code() ) {
			$this->fail( $result->get_error_code(), $result->get_error_message() );
		}

		// Success (or silent bot drop).
		wp_send_json_success( array(
			'error_code' => '',
			'message'    => wc_print_notice( __( 'Your request has been submitted successfully.', 'px-wc-requests' ), 'success', array(), true ),
		) );
	}

	private function fail( string $code, string $message ): void {
		wp_send_json_error( array(
			'error_code' => $code,
			'message'    => wc_print_notice( $message, 'error', array(), true ),
		) );
	}

	// =====================================================================
	// Sanitise / validate / create
	// =====================================================================

	private function sanitize( array $post, string $type ): array {
		$fields = RequestTypes::get_fields( $type );
		$data   = array( 'order_id' => isset( $post['order_id'] ) ? absint( $post['order_id'] ) : 0 );

		foreach ( $fields as $field ) {
			switch ( $field['type'] ) {
				case 'order_items':
					$data['items'] = $this->sanitize_items( $post, $field );
					break;
				case 'file':
					// Files handled at create() from $_FILES.
					break;
				default:
					$data[ $field['key'] ] = FieldSchema::sanitize_value( $field, $post[ $field['key'] ] ?? '' );
			}
		}

		return $data;
	}

	private function sanitize_items( array $post, array $field ): array {
		$items = array();

		if ( 'single' === $field['item_mode'] ) {
			if ( ! empty( $post['selected_item'] ) ) {
				$item_id = absint( $post['selected_item'] );
				$reason  = '';
				if ( ! empty( $post['reason'] ) && is_array( $post['reason'] ) && isset( $post['reason'][ $item_id ] ) ) {
					$reason = sanitize_textarea_field( wp_unslash( $post['reason'][ $item_id ] ) );
				}
				$items[ $item_id ] = array( 'quantity' => 1, 'reason' => $reason );
			}

			return $items;
		}

		// multiple — only checked rows count (every row carries a quantity input).
		if ( ! empty( $post['items'] ) && is_array( $post['items'] ) ) {
			foreach ( $post['items'] as $item_id => $row ) {
				if ( empty( $row['selected'] ) ) {
					continue;
				}
				$qty = isset( $row['quantity'] ) ? max( 1, absint( $row['quantity'] ) ) : 1;
				$items[ absint( $item_id ) ] = array(
					'quantity' => $qty,
					'reason'   => isset( $row['reason'] ) ? sanitize_textarea_field( wp_unslash( $row['reason'] ) ) : '',
				);
			}
		}

		return $items;
	}

	/**
	 * @return true|\WP_Error
	 */
	private function validate( array $data, string $type ) {
		$order = wc_get_order( $data['order_id'] );
		if ( ! $order ) {
			return new \WP_Error( 'order', __( 'Order not found.', 'px-wc-requests' ) );
		}

		// Server-side period enforcement (the form may be stale).
		$gate = Eligibility::gate( $order, $type );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$eligible = Eligibility::eligible_items( $order, $type );

		$fields = RequestTypes::get_fields( $type );

		foreach ( $fields as $field ) {
			$key = $field['key'];

			if ( 'order_items' === $field['type'] ) {
				if ( $field['required'] && empty( $data['items'] ) ) {
					return new \WP_Error( 'items', __( 'Select at least one item.', 'px-wc-requests' ) );
				}
				foreach ( $data['items'] as $item_id => $item ) {
					if ( ! isset( $eligible[ $item_id ] ) ) {
						return new \WP_Error( 'items', __( 'One of the selected items is not eligible for this request.', 'px-wc-requests' ) );
					}
					$available = Eligibility::available_qty( $eligible[ $item_id ], $order, $type );
					if ( (int) ( $item['quantity'] ?? 1 ) > $available ) {
						return new \WP_Error(
							'items',
							sprintf(
								/* translators: 1: item name, 2: number of units */
								__( 'Only %2$d unit(s) of "%1$s" can still be requested — the rest is already part of another request.', 'px-wc-requests' ),
								$eligible[ $item_id ]->get_name(),
								$available
							)
						);
					}
					if ( $field['item_reason_required'] && empty( $item['reason'] ) ) {
						return new \WP_Error( 'items', __( 'Please fill in the description for each selected item.', 'px-wc-requests' ) );
					}
				}
				continue;
			}

			if ( 'file' === $field['type'] ) {
				$error = $this->validate_files( $field );
				if ( is_wp_error( $error ) ) {
					return $error;
				}
				continue;
			}

			if ( $field['required'] && empty( $data[ $key ] ) ) {
				/* translators: %s: field label */
				return new \WP_Error( $key, sprintf( __( 'Please fill in the field: %s', 'px-wc-requests' ), $field['label'] ) );
			}

			if ( 'iban' === $field['type'] && ! empty( $data[ $key ] ) && ! pxer_validate_iban( $data[ $key ] ) ) {
				return new \WP_Error( $key, __( 'Please enter a valid IBAN.', 'px-wc-requests' ) );
			}
		}

		return true;
	}

	/**
	 * @return true|\WP_Error
	 */
	private function validate_files( array $field ) {
		$name = $field['key'];
		if ( empty( $_FILES[ $name ]['name'][0] ) ) {
			return true;
		}

		$allowed_ext  = array_map( 'strtolower', $field['accept'] );
		$allowed_mime = array( 'image/jpeg', 'image/png' );

		foreach ( $_FILES[ $name ]['name'] as $key => $file_name ) {
			if ( empty( $file_name ) || UPLOAD_ERR_OK !== $_FILES[ $name ]['error'][ $key ] ) {
				continue;
			}

			$ext = strtolower( wp_check_filetype( $file_name )['ext'] ?? '' );
			if ( ! in_array( $ext, $allowed_ext, true ) ) {
				/* translators: %s: allowed extensions */
				return new \WP_Error( $name, sprintf( __( 'Invalid file extension, allowed only: %s', 'px-wc-requests' ), implode( ', ', $allowed_ext ) ) );
			}

			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime  = finfo_file( $finfo, $_FILES[ $name ]['tmp_name'][ $key ] );
			finfo_close( $finfo );
			if ( ! in_array( $mime, $allowed_mime, true ) ) {
				return new \WP_Error( $name, __( 'Invalid file type. Upload JPG or PNG only.', 'px-wc-requests' ) );
			}

			if ( false === @getimagesize( $_FILES[ $name ]['tmp_name'][ $key ] ) ) {
				return new \WP_Error( $name, __( 'The uploaded file is not a valid image.', 'px-wc-requests' ) );
			}

			if ( $_FILES[ $name ]['size'][ $key ] >= $field['max_size'] ) {
				/* translators: %d: size in MB */
				return new \WP_Error( $name, sprintf( __( 'The file must not be larger than %d MB.', 'px-wc-requests' ), (int) round( $field['max_size'] / 1000000 ) ) );
			}
		}

		return true;
	}

	/**
	 * @return int|\WP_Error
	 */
	private function create( array $data, string $type ) {
		/**
		 * Filter request data right before it is stored (e.g. add custom meta).
		 *
		 * @param array  $data
		 * @param string $type
		 */
		$data = apply_filters( 'pxer_request_data', $data, $type );

		$type_label = RequestTypes::get_label( $type );
		$order      = wc_get_order( $data['order_id'] );
		// Owner = order customer, else a user matching the e-mail, else the
		// logged-in submitter (guest order opened by a logged-in user).
		$user_id    = pxer_resolve_owner_id( $order, $data['email'] ?? '' );
		if ( ! $user_id && is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		$post_id = wp_insert_post( array(
			'post_title'  => $type_label,
			'post_type'   => RequestPostType::POST_TYPE,
			'post_status' => RequestTypes::get_default_status( $type ),
			'meta_input'  => array(
				'_pxer_type'     => $type,
				'_pxer_order_id' => $data['order_id'],
				'_pxer_data'     => $data,
				'_pxer_email'    => $data['email'] ?? '',
				'_pxer_user_id'  => $user_id,
			),
		), true );

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error( 'insert_failed', __( 'An error occurred while saving the request.', 'px-wc-requests' ) );
		}

		// Append the request number to the title.
		wp_update_post( array(
			'ID'         => $post_id,
			/* translators: 1: type label, 2: id */
			'post_title' => sprintf( __( '%1$s no. %2$d', 'px-wc-requests' ), $type_label, $post_id ),
		) );

		// Handle image uploads (any file field in the schema).
		foreach ( RequestTypes::get_fields( $type ) as $field ) {
			if ( 'file' === $field['type'] && ! empty( $_FILES[ $field['key'] ]['name'][0] ) ) {
				$ids = pxer_upload_files( $_FILES[ $field['key'] ], $post_id );
				update_post_meta( $post_id, '_pxer_images', $ids );
			}
		}

		/**
		 * Fires after a request has been created.
		 *
		 * @param int    $post_id
		 * @param int    $order_id
		 * @param string $type
		 */
		do_action( 'pxer_request_created', $post_id, (int) $data['order_id'], $type );

		return $post_id;
	}

	private function after_success( int $request_id, string $type ): void {
		$type_def = RequestTypes::get( $type );
		$emails   = WC()->mailer()->get_emails();

		if ( ! empty( $type_def['emails']['admin_new'] ) && isset( $emails['Pxer_Admin_Request_Email'] ) ) {
			$emails['Pxer_Admin_Request_Email']->trigger( $request_id );
		}

		if ( ! empty( $type_def['emails']['customer_confirmation'] ) && isset( $emails['Pxer_Customer_Request_Email'] ) ) {
			$emails['Pxer_Customer_Request_Email']->trigger( $request_id );
		}

		$request = pxer_get_request( $request_id );
		$order   = $request->get_order();
		if ( $order ) {
			$order->add_order_note( sprintf(
				/* translators: 1: type label, 2: request id */
				__( 'Customer request created: %1$s no. %2$d', 'px-wc-requests' ),
				$request->get_type_label(),
				$request_id
			) );
		}

		// EU 2023/2673: the trader must refund within 14 days of being informed.
		// Record the statutory refund deadline as an internal reminder.
		$refund_days = (int) ( $type_def['refund_due_days'] ?? 0 );
		if ( $refund_days > 0 ) {
			$due = current_datetime()->modify( '+' . $refund_days . ' days' );
			update_post_meta( $request_id, '_pxer_refund_due', $due->format( 'Y-m-d' ) );
			RequestNotes::add_note(
				$request_id,
				sprintf(
					/* translators: %s: date */
					__( 'Statutory refund deadline: %s.', 'px-wc-requests' ),
					wp_date( get_option( 'date_format' ), $due->getTimestamp() )
				),
				false
			);
		}
	}
}
