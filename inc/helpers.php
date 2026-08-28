<?php
/**
 * Global helper functions (intentionally in the global namespace).
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get a Request model for a post ID.
 */
function pxer_get_request( int $request_id ): \Pixeler\Requests\Request {
	return new \Pixeler\Requests\Request( $request_id );
}

/**
 * Fetch requests bound to an order, optionally filtered by type.
 *
 * @return WP_Post[]
 */
function pxer_get_requests_by_order_id( int $order_id, string $type = '', int $limit = 50 ): array {
	$order_id = absint( $order_id );
	if ( ! $order_id ) {
		return array();
	}

	// Per-process cache, invalidated whenever a request is saved or deleted
	// (availability checks must see a request created moments ago).
	static $cache = array();
	$cache_key = $order_id . ':' . $type . ':' . did_action( 'save_post_' . \Pixeler\Requests\RequestPostType::POST_TYPE ) . ':' . did_action( 'deleted_post' );
	if ( isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	$meta_query = array(
		array(
			'key'   => '_pxer_order_id',
			'value' => $order_id,
		),
	);
	if ( $type ) {
		$meta_query[] = array(
			'key'   => '_pxer_type',
			'value' => $type,
		);
	}

	$cache[ $cache_key ] = get_posts( array(
		'post_type'              => \Pixeler\Requests\RequestPostType::POST_TYPE,
		'post_status'            => 'any',
		'posts_per_page'         => $limit,
		'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
	) );

	return $cache[ $cache_key ];
}

/**
 * Resolve the account that owns a request: the order's customer, otherwise a
 * registered user matching the request e-mail. 0 if none (true guest).
 */
function pxer_resolve_owner_id( $order, string $email ): int {
	if ( $order instanceof \WC_Order && $order->get_customer_id() ) {
		return (int) $order->get_customer_id();
	}
	if ( $email ) {
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			return (int) $user->ID;
		}
	}

	return 0;
}

/**
 * Validate an IBAN using the ISO 13616 mod-97 checksum.
 */
function pxer_validate_iban( string $iban ): bool {
	$iban = strtoupper( str_replace( array( ' ', "\t", "\n", "\r", '-' ), '', $iban ) );

	if ( ! preg_match( '/^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$/', $iban ) ) {
		return false;
	}

	// Per-country length table (common EU/EEA). Unknown countries pass format-only.
	$lengths = array(
		'SK' => 24, 'CZ' => 24, 'AT' => 20, 'DE' => 22, 'PL' => 28, 'HU' => 28,
		'GB' => 22, 'FR' => 27, 'IT' => 27, 'ES' => 24, 'NL' => 18, 'BE' => 16,
		'IE' => 22, 'PT' => 25, 'RO' => 24, 'SI' => 19, 'HR' => 21, 'BG' => 22,
		'LU' => 20, 'DK' => 18, 'FI' => 18, 'SE' => 24, 'LT' => 20, 'LV' => 21,
		'EE' => 20, 'CH' => 21, 'GR' => 27, 'CY' => 28, 'MT' => 31,
	);
	$country = substr( $iban, 0, 2 );
	if ( isset( $lengths[ $country ] ) && strlen( $iban ) !== $lengths[ $country ] ) {
		return false;
	}

	// Move first 4 chars to the end, then replace letters with numbers (A=10..Z=35).
	$rearranged = substr( $iban, 4 ) . substr( $iban, 0, 4 );
	$numeric    = '';
	$length     = strlen( $rearranged );
	for ( $i = 0; $i < $length; $i++ ) {
		$char      = $rearranged[ $i ];
		$numeric  .= ctype_alpha( $char ) ? (string) ( ord( $char ) - 55 ) : $char;
	}

	// Iterative mod-97 to avoid bigint issues.
	$remainder = 0;
	$length    = strlen( $numeric );
	for ( $i = 0; $i < $length; $i += 7 ) {
		$block     = $remainder . substr( $numeric, $i, 7 );
		$remainder = (int) $block % 97;
	}

	return 1 === $remainder;
}

/**
 * Normalise an IBAN for storage (uppercase, no spaces).
 */
function pxer_normalize_iban( string $iban ): string {
	return strtoupper( str_replace( array( ' ', "\t", "\n", "\r", '-' ), '', $iban ) );
}

/**
 * Pretty-print an IBAN in groups of four for display.
 */
function pxer_format_iban( string $iban ): string {
	return trim( chunk_split( pxer_normalize_iban( $iban ), 4, ' ' ) );
}

/**
 * Mask an IBAN for list views: keep the first 4 and last 4 characters.
 */
function pxer_mask_iban( string $iban ): string {
	$iban = pxer_normalize_iban( $iban );
	$len  = strlen( $iban );
	if ( $len <= 8 ) {
		return $iban;
	}

	return substr( $iban, 0, 4 ) . ' …' . substr( $iban, -4 );
}

/**
 * Handle a single uploaded file into the media library.
 *
 * @param array    $file    A single $_FILES-style entry.
 * @param int|null $post_id Parent post.
 * @return array|WP_Error
 */
function pxer_upload_file( array $file, $post_id = null ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$_FILES = array( 'pxer_file' => $file );

	$attachment_id = media_handle_upload( 'pxer_file', (int) $post_id );

	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	return array(
		'id'  => $attachment_id,
		'url' => wp_get_attachment_url( $attachment_id ),
	);
}

/**
 * Handle a multi-file ($_FILES['images']) upload.
 *
 * @return array List of attachment IDs.
 */
function pxer_upload_files( array $files, $post_id = null ): array {
	$ids = array();

	if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
		return $ids;
	}

	// Store request attachments in the protected directory.
	\Pixeler\Requests\PrivateFiles::begin_private_upload();

	foreach ( $files['name'] as $key => $name ) {
		if ( empty( $name ) || ( isset( $files['error'][ $key ] ) && UPLOAD_ERR_OK !== $files['error'][ $key ] ) ) {
			continue;
		}

		$single = array(
			'name'     => $files['name'][ $key ],
			'type'     => $files['type'][ $key ],
			'tmp_name' => $files['tmp_name'][ $key ],
			'error'    => $files['error'][ $key ],
			'size'     => $files['size'][ $key ],
		);

		$uploaded = pxer_upload_file( $single, $post_id );
		if ( ! is_wp_error( $uploaded ) && isset( $uploaded['id'] ) ) {
			\Pixeler\Requests\PrivateFiles::mark_private( (int) $uploaded['id'] );
			$ids[] = $uploaded['id'];
		}
	}

	\Pixeler\Requests\PrivateFiles::end_private_upload();

	return $ids;
}

/**
 * Render the admin-configured extra e-mail text (Customer requests → E-mail
 * texts). Placeholders are already resolved by the e-mail class; this only
 * formats and escapes.
 *
 * @param string $content    Text to render (may be empty).
 * @param bool   $plain_text Render for the plain-text e-mail template.
 */
function pxer_render_email_custom_content( string $content, bool $plain_text = false ): void {
	$content = trim( $content );
	if ( '' === $content ) {
		return;
	}

	if ( $plain_text ) {
		echo esc_html( wp_strip_all_tags( wptexturize( $content ) ) ) . "\n\n";

		return;
	}

	echo '<div class="pxer-email-custom-content">' . wp_kses_post( wpautop( wptexturize( $content ) ) ) . '</div>';
}

/**
 * Render the read-only summary of a request (used in emails and history).
 * Schema-driven: prints the items table plus every field flagged for `email`.
 */
function pxer_render_request_summary( \Pixeler\Requests\Request $request ): void {
	$data         = $request->get_data();
	$type         = $request->get_type();
	$fields       = \Pixeler\Requests\RequestTypes::get_fields( $type );
	$order        = $request->get_order();
	$order_items  = $order ? $order->get_items() : array();
	$order_number = $order ? $order->get_order_number() : (string) $request->get_order_id();
	$items        = (array) ( $data['items'] ?? array() );
	?>
	<table class="td" cellspacing="0" cellpadding="6" style="width:100%" border="1">
		<tbody>
		<tr>
			<th class="td" scope="row" style="text-align:left"><?php esc_html_e( 'Type:', 'px-wc-requests' ); ?></th>
			<td class="td"><?php echo esc_html( $request->get_type_label() ); ?></td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align:left"><?php esc_html_e( 'Request number:', 'px-wc-requests' ); ?></th>
			<td class="td">#<?php echo esc_html( $request->get_id() ); ?></td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align:left"><?php esc_html_e( 'Status:', 'px-wc-requests' ); ?></th>
			<td class="td"><?php echo esc_html( $request->get_status_label() ); ?></td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align:left"><?php esc_html_e( 'Date created:', 'px-wc-requests' ); ?></th>
			<td class="td"><?php echo esc_html( get_the_date( 'd.m.Y H:i', $request->get_id() ) ); ?></td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align:left"><?php esc_html_e( 'Order number:', 'px-wc-requests' ); ?></th>
			<td class="td"><?php echo esc_html( $order_number ); ?></td>
		</tr>
		<?php
		foreach ( $fields as $field ) {
			if ( ! in_array( 'email', $field['show_in'], true ) ) {
				continue;
			}
			if ( in_array( $field['type'], array( 'order_items', 'file', 'checkbox' ), true ) ) {
				continue;
			}
			$value = $data[ $field['key'] ] ?? '';
			if ( '' === $value ) {
				continue;
			}
			if ( 'iban' === $field['type'] ) {
				$value = pxer_format_iban( (string) $value );
			} elseif ( in_array( $field['type'], array( 'select', 'radio' ), true ) && isset( $field['options'][ $value ] ) ) {
				$value = $field['options'][ $value ];
			}
			?>
			<tr>
				<th class="td" scope="row" style="text-align:left"><?php echo esc_html( $field['label'] ); ?>:</th>
				<td class="td"><?php echo esc_html( $value ); ?></td>
			</tr>
			<?php
		}
		?>
		</tbody>
	</table>

	<?php if ( $items ) : ?>
		<table class="td" cellspacing="0" cellpadding="6" style="width:100%;margin-top:20px" border="1">
			<thead>
			<tr>
				<th class="td" scope="col"><?php esc_html_e( 'Product', 'px-wc-requests' ); ?></th>
				<th class="td" scope="col"><?php esc_html_e( 'Quantity', 'px-wc-requests' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $items as $item_id => $item ) : ?>
				<?php
				$order_item = $order_items[ $item_id ] ?? null;
				$name       = $order_item ? $order_item->get_name() : sprintf( __( 'Item #%d', 'px-wc-requests' ), $item_id );
				?>
				<tr>
					<td class="td">
						<?php echo esc_html( $name ); ?>
						<?php if ( ! empty( $item['reason'] ) ) : ?>
							<br><strong><?php esc_html_e( 'Reason:', 'px-wc-requests' ); ?> </strong><?php echo esc_html( $item['reason'] ); ?>
						<?php endif; ?>
					</td>
					<td class="td"><?php echo esc_html( $item['quantity'] ?? 1 ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php
}

/**
 * Render a representative summary with placeholder values, used by email
 * previews when no real request is available.
 */
function pxer_render_request_summary_sample(): void {
	$types        = \Pixeler\Requests\RequestTypes::all();
	$type         = reset( $types );
	$type_label   = $type ? $type['label'] : __( 'Request', 'px-wc-requests' );
	$status_label = $type && $type['statuses'] ? reset( $type['statuses'] ) : '';
	?>
	<table class="td" cellspacing="0" cellpadding="6" style="width:100%" border="1">
		<tbody>
		<tr><th class="td" style="text-align:left"><?php esc_html_e( 'Type:', 'px-wc-requests' ); ?></th><td class="td"><?php echo esc_html( $type_label ); ?></td></tr>
		<tr><th class="td" style="text-align:left"><?php esc_html_e( 'Request number:', 'px-wc-requests' ); ?></th><td class="td">#123</td></tr>
		<tr><th class="td" style="text-align:left"><?php esc_html_e( 'Status:', 'px-wc-requests' ); ?></th><td class="td"><?php echo esc_html( $status_label ); ?></td></tr>
		<tr><th class="td" style="text-align:left"><?php esc_html_e( 'Date created:', 'px-wc-requests' ); ?></th><td class="td"><?php echo esc_html( wp_date( 'd.m.Y H:i' ) ); ?></td></tr>
		<tr><th class="td" style="text-align:left"><?php esc_html_e( 'Order number:', 'px-wc-requests' ); ?></th><td class="td">1001</td></tr>
		<tr><th class="td" style="text-align:left"><?php esc_html_e( 'Account name / recipient', 'px-wc-requests' ); ?>:</th><td class="td">Jane Doe</td></tr>
		<tr><th class="td" style="text-align:left"><?php esc_html_e( 'IBAN', 'px-wc-requests' ); ?>:</th><td class="td">SK89 0200 0000 0000 0000 0000</td></tr>
		</tbody>
	</table>
	<table class="td" cellspacing="0" cellpadding="6" style="width:100%;margin-top:20px" border="1">
		<thead>
		<tr>
			<th class="td" scope="col"><?php esc_html_e( 'Product', 'px-wc-requests' ); ?></th>
			<th class="td" scope="col"><?php esc_html_e( 'Quantity', 'px-wc-requests' ); ?></th>
		</tr>
		</thead>
		<tbody>
		<tr>
			<td class="td"><?php esc_html_e( 'Sample product', 'px-wc-requests' ); ?></td>
			<td class="td">1</td>
		</tr>
		</tbody>
	</table>
	<?php
}
