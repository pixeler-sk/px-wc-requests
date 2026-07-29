<?php
/**
 * Single shared CPT for every request type, with dynamic admin columns,
 * metaboxes and statuses driven by the type registry.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class RequestPostType {

	const POST_TYPE = 'pxer_request';

	/** @var array<int,\WC_Order> */
	private static array $order_cache = array();

	public function setup(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 9 );
		add_action( 'init', array( $this, 'register_status_objects' ), 8 );

		// List table.
		add_filter( 'manage_edit-' . self::POST_TYPE . '_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'the_posts', array( $this, 'preload_orders' ), 10, 2 );

		// Type filter in the list table.
		add_action( 'restrict_manage_posts', array( $this, 'type_filter_dropdown' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_type_filter' ) );

		// Metaboxes.
		add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save' ) );

		// Apply the Status metabox choice during the post save.
		add_filter( 'wp_insert_post_data', array( $this, 'apply_status_on_save' ), 10, 2 );

		// Classic-editor save notices ("Post updated." etc.) come from this filter,
		// not the post type labels — override them for our CPT.
		add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );
		add_filter( 'bulk_post_updated_messages', array( $this, 'bulk_updated_messages' ), 10, 2 );
	}

	/**
	 * Bulk actions + status registration. The publish-box dropdown is disabled
	 * (`_submitbox => false`) in favour of a dedicated Status metabox below.
	 *
	 * Runs on `init` (not in setup(), which fires at plugins_loaded) because the
	 * status labels come from RequestTypes and are translated — building them
	 * earlier triggers just-in-time textdomain loading, which WP 6.7+ flags as
	 * doing_it_wrong. Priority 8 keeps it ahead of the `init` @10 callback that
	 * CustomPostStatus registers in its constructor to do register_post_status().
	 */
	public function register_status_objects(): void {
		foreach ( RequestTypes::all_statuses() as $slug => $label ) {
			new CustomPostStatus(
				$slug,
				self::status_args( $label ) + array( '_show_in_state' => true, '_submitbox' => false ),
				array( self::POST_TYPE )
			);
		}
	}

	/**
	 * Classic-editor save/update notices for the request CPT.
	 *
	 * @param array $messages
	 *
	 * @return array
	 */
	public function updated_messages( array $messages ): array {
		$messages[ self::POST_TYPE ] = array(
			0  => '',
			1  => __( 'Request updated.', 'px-wc-requests' ),
			2  => __( 'Custom field updated.', 'px-wc-requests' ),
			3  => __( 'Custom field deleted.', 'px-wc-requests' ),
			4  => __( 'Request updated.', 'px-wc-requests' ),
			5  => '',
			6  => __( 'Request created.', 'px-wc-requests' ),
			7  => __( 'Request saved.', 'px-wc-requests' ),
			8  => __( 'Request submitted.', 'px-wc-requests' ),
			9  => '',
			10 => __( 'Request draft updated.', 'px-wc-requests' ),
		);

		return $messages;
	}

	/**
	 * Bulk action notices ("X posts updated.") for the request CPT.
	 *
	 * @param array $messages
	 * @param array $counts
	 *
	 * @return array
	 */
	public function bulk_updated_messages( array $messages, array $counts ): array {
		$messages[ self::POST_TYPE ] = array(
			/* translators: %s: number of requests */
			'updated'   => sprintf( _n( '%s request updated.', '%s requests updated.', $counts['updated'], 'px-wc-requests' ), number_format_i18n( $counts['updated'] ) ),
			/* translators: %s: number of requests */
			'locked'    => sprintf( _n( '%s request not updated, somebody is editing it.', '%s requests not updated, somebody is editing them.', $counts['locked'], 'px-wc-requests' ), number_format_i18n( $counts['locked'] ) ),
			/* translators: %s: number of requests */
			'deleted'   => sprintf( _n( '%s request permanently deleted.', '%s requests permanently deleted.', $counts['deleted'], 'px-wc-requests' ), number_format_i18n( $counts['deleted'] ) ),
			/* translators: %s: number of requests */
			'trashed'   => sprintf( _n( '%s request moved to the Trash.', '%s requests moved to the Trash.', $counts['trashed'], 'px-wc-requests' ), number_format_i18n( $counts['trashed'] ) ),
			/* translators: %s: number of requests */
			'untrashed' => sprintf( _n( '%s request restored from the Trash.', '%s requests restored from the Trash.', $counts['untrashed'], 'px-wc-requests' ), number_format_i18n( $counts['untrashed'] ) ),
		);

		return $messages;
	}

	// =====================================================================
	// Registration
	// =====================================================================

	public static function register_post_type(): void {
		$labels = array(
			'name'                  => __( 'Requests', 'px-wc-requests' ),
			'singular_name'         => __( 'Request', 'px-wc-requests' ),
			'menu_name'             => __( 'Customer requests', 'px-wc-requests' ),
			'all_items'             => __( 'Requests', 'px-wc-requests' ),
			'add_new'               => __( 'Add request', 'px-wc-requests' ),
			'add_new_item'          => __( 'Add request', 'px-wc-requests' ),
			'new_item'              => __( 'New request', 'px-wc-requests' ),
			'edit_item'             => __( 'Edit request', 'px-wc-requests' ),
			'view_item'             => __( 'View request', 'px-wc-requests' ),
			'view_items'            => __( 'View requests', 'px-wc-requests' ),
			'search_items'          => __( 'Search requests', 'px-wc-requests' ),
			'not_found'             => __( 'No requests found', 'px-wc-requests' ),
			'not_found_in_trash'    => __( 'No requests in trash', 'px-wc-requests' ),
			'item_updated'          => __( 'Request updated.', 'px-wc-requests' ),
			'item_published'        => __( 'Request created.', 'px-wc-requests' ),
			'items_list'            => __( 'Requests list', 'px-wc-requests' ),
			'items_list_navigation' => __( 'Requests list navigation', 'px-wc-requests' ),
			'filter_items_list'     => __( 'Filter requests list', 'px-wc-requests' ),
		);

		register_post_type( self::POST_TYPE, array(
			'labels'            => $labels,
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => 'woocommerce',
			'show_in_nav_menus' => false,
			'show_in_rest'      => false,
			'rewrite'           => false,
			'has_archive'       => false,
			'supports'          => array( 'title' ),
			'map_meta_cap'      => true,
			'capability_type'   => 'post',
		) );
	}

	public static function register_statuses(): void {
		foreach ( RequestTypes::all_statuses() as $slug => $label ) {
			register_post_status( $slug, self::status_args( $label ) );
		}
	}

	private static function status_args( string $label ): array {
		return array(
			'label'                     => $label,
			/* translators: %s: count */
			'label_count'               => _n_noop(
				$label . ' <span class="count">(%s)</span>',
				$label . ' <span class="count">(%s)</span>'
			),
			'public'                    => false,
			// `protected` + `show_in_admin_all_list` are BOTH required for the
			// post to appear in the admin "All" list (see WP_Query, where it
			// only adds statuses matching get_post_stati(protected + all_list)).
			'protected'                 => true,
			// MUST be false, otherwise post_status => 'any' (used by every
			// request query) excludes these statuses. The post type itself is
			// non-public, so requests still never appear in front-end search.
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
		);
	}

	// =====================================================================
	// List table
	// =====================================================================

	public function columns( array $columns ): array {
		$date = $columns['date'] ?? '';
		unset( $columns['date'] );

		$columns['pxer_type']    = __( 'Type', 'px-wc-requests' );
		$columns['pxer_order']   = __( 'Order', 'px-wc-requests' );
		$columns['pxer_status']  = __( 'Status', 'px-wc-requests' );
		$columns['pxer_contact'] = __( 'Contact', 'px-wc-requests' );
		$columns['pxer_iban']    = __( 'IBAN', 'px-wc-requests' );
		$columns['pxer_items']   = __( 'Items', 'px-wc-requests' );
		$columns['date']         = $date ?: __( 'Date', 'px-wc-requests' );

		return $columns;
	}

	public function render_column( string $column, int $post_id ): void {
		$request = pxer_get_request( $post_id );
		$data    = $request->get_data();
		$order   = self::$order_cache[ $request->get_order_id() ] ?? $request->get_order();

		switch ( $column ) {
			case 'pxer_type':
				echo esc_html( $request->get_type_label() );
				break;
			case 'pxer_order':
				$oid = $request->get_order_id();
				if ( $order ) {
					echo '<a href="' . esc_url( $order->get_edit_order_url() ) . '" target="_blank">#' . esc_html( $order->get_order_number() ) . '</a>';
				} else {
					echo esc_html( $oid );
				}
				break;
			case 'pxer_status':
				echo esc_html( $request->get_status_label() );
				break;
			case 'pxer_contact':
				$lines = array_filter( array(
					trim( ( $data['firstname'] ?? '' ) . ' ' . ( $data['lastname'] ?? '' ) ),
					$data['email'] ?? '',
					$data['phone'] ?? '',
					trim( ( $data['postcode'] ?? '' ) . ' ' . ( $data['city'] ?? '' ) ),
				) );
				echo wp_kses_post( implode( '<br>', array_map( 'esc_html', $lines ) ) );
				break;
			case 'pxer_iban':
				echo esc_html( ! empty( $data['iban'] ) ? pxer_mask_iban( $data['iban'] ) : '' );
				break;
			case 'pxer_items':
				$order_items = $order ? $order->get_items() : array();
				foreach ( (array) ( $data['items'] ?? array() ) as $item_id => $item ) {
					$order_item = $order_items[ $item_id ] ?? null;
					if ( $order_item ) {
						echo esc_html( ( $item['quantity'] ?? 1 ) . '× ' . $order_item->get_name() ) . '<br>';
					}
				}
				break;
		}
	}

	/**
	 * Bulk preload orders to avoid N+1 queries in the list table.
	 *
	 * @param \WP_Post[] $posts
	 */
	public function preload_orders( array $posts, \WP_Query $query ): array {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return $posts;
		}

		$order_ids = array();
		foreach ( $posts as $post ) {
			$oid = (int) get_post_meta( $post->ID, '_pxer_order_id', true );
			if ( $oid ) {
				$order_ids[] = $oid;
			}
		}

		foreach ( array_unique( $order_ids ) as $oid ) {
			$order = wc_get_order( $oid );
			if ( $order ) {
				self::$order_cache[ $oid ] = $order;
			}
		}

		return $posts;
	}

	public function type_filter_dropdown(): void {
		global $typenow;
		if ( self::POST_TYPE !== $typenow ) {
			return;
		}
		$current = isset( $_GET['pxer_type'] ) ? sanitize_text_field( wp_unslash( $_GET['pxer_type'] ) ) : '';
		echo '<select name="pxer_type">';
		echo '<option value="">' . esc_html__( 'All types', 'px-wc-requests' ) . '</option>';
		foreach ( RequestTypes::all() as $id => $type ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $id ),
				selected( $current, $id, false ),
				esc_html( $type['label_plural'] )
			);
		}
		echo '</select>';
	}

	public function apply_type_filter( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		global $pagenow, $typenow;
		if ( 'edit.php' !== $pagenow || self::POST_TYPE !== $typenow ) {
			return;
		}
		if ( ! empty( $_GET['pxer_type'] ) ) {
			$query->set( 'meta_key', '_pxer_type' );
			$query->set( 'meta_value', sanitize_text_field( wp_unslash( $_GET['pxer_type'] ) ) );
		}
	}

	// =====================================================================
	// Metaboxes
	// =====================================================================

	public function register_metaboxes(): void {
		add_meta_box( 'pxer_status', __( 'Status', 'px-wc-requests' ), array( $this, 'render_status_metabox' ), self::POST_TYPE, 'side', 'high' );
		add_meta_box( 'pxer_data', __( 'Request details', 'px-wc-requests' ), array( $this, 'render_data_metabox' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'pxer_items', __( 'Items', 'px-wc-requests' ), array( $this, 'render_items_metabox' ), self::POST_TYPE, 'normal', 'default' );
		add_meta_box( 'pxer_images', __( 'Photos', 'px-wc-requests' ), array( $this, 'render_images_metabox' ), self::POST_TYPE, 'side', 'default' );
	}

	/**
	 * Status selector — change the request status here (the publish-box dropdown
	 * is disabled for this CPT). Applied in {@see apply_status_on_save()}.
	 */
	public function render_status_metabox( \WP_Post $post ): void {
		$request  = pxer_get_request( $post->ID );
		$type     = $request->get_type();
		$statuses = RequestTypes::get_statuses( $type ) ?: RequestTypes::all_statuses();
		$current  = $post->post_status;
		?>
		<p>
			<select name="pxer_status" id="pxer_status" style="width:100%">
				<?php foreach ( $statuses as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'Save the request to apply the status. The customer is notified by e-mail.', 'px-wc-requests' ); ?></p>
		<?php
	}

	/**
	 * Apply the Status metabox choice while the post is being saved, so the
	 * native transition (and customer e-mail + history note) fire exactly once.
	 */
	public function apply_status_on_save( array $data, array $postarr ): array {
		if ( self::POST_TYPE !== ( $data['post_type'] ?? '' ) || empty( $postarr['ID'] ) ) {
			return $data;
		}
		if ( empty( $_POST['pxer_status'] ) ) {
			return $data;
		}
		if ( ! isset( $_POST['pxer_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pxer_meta_nonce'], 'pxer_save_meta' ) ) {
			return $data;
		}

		$new = sanitize_key( wp_unslash( $_POST['pxer_status'] ) );
		if ( isset( RequestTypes::all_statuses()[ $new ] ) ) {
			$data['post_status'] = $new;
		}

		return $data;
	}

	public function render_data_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'pxer_save_meta', 'pxer_meta_nonce' );
		$request = pxer_get_request( $post->ID );
		$data    = $request->get_data();
		$type    = $request->get_type();
		$fields  = RequestTypes::get_fields( $type );
		?>
		<style>
			.pxer-metabox-grid { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
			.pxer-metabox-grid .full { grid-column:1 / -1; }
			.pxer-metabox-grid label { font-weight:600; display:block; margin-bottom:4px; }
			.pxer-metabox-grid input[type=text],
			.pxer-metabox-grid input[type=email] { width:100%; }
		</style>
		<p>
			<label for="pxer_order_id"><?php esc_html_e( 'Order number', 'px-wc-requests' ); ?></label>
			<input type="number" id="pxer_order_id" name="pxer_order_id" value="<?php echo esc_attr( $request->get_order_id() ); ?>">
		</p>
		<div class="pxer-metabox-grid">
			<?php
			foreach ( $fields as $field ) {
				if ( in_array( $field['type'], array( 'order_items', 'file' ), true ) ) {
					continue;
				}
				$value = $data[ $field['key'] ] ?? '';
				$full  = 'full' === $field['width'] ? ' full' : '';
				echo '<div class="' . esc_attr( trim( $full ) ) . '">';
				if ( 'checkbox' === $field['type'] ) {
					printf(
						'<label><input type="checkbox" name="pxer_data[%1$s]" value="yes" %2$s> %3$s</label>',
						esc_attr( $field['key'] ),
						checked( 'yes', $value, false ),
						esc_html( $field['label'] )
					);
				} elseif ( 'textarea' === $field['type'] ) {
					printf(
						'<label for="pxer_%1$s">%2$s</label><textarea id="pxer_%1$s" name="pxer_data[%1$s]" rows="3" style="width:100%%">%3$s</textarea>',
						esc_attr( $field['key'] ),
						esc_html( $field['label'] ),
						esc_textarea( (string) $value )
					);
				} elseif ( in_array( $field['type'], array( 'select', 'radio' ), true ) ) {
					$options_html = '<option value="">&mdash;</option>';
					foreach ( (array) $field['options'] as $opt_value => $opt_label ) {
						$options_html .= sprintf(
							'<option value="%1$s" %2$s>%3$s</option>',
							esc_attr( $opt_value ),
							selected( (string) $opt_value, (string) $value, false ),
							esc_html( $opt_label )
						);
					}
					printf(
						'<label for="pxer_%1$s">%2$s</label><select id="pxer_%1$s" name="pxer_data[%1$s]" style="width:100%%">%3$s</select>',
						esc_attr( $field['key'] ),
						esc_html( $field['label'] ),
						$options_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr/esc_html above
					);
				} else {
					$itype = in_array( $field['type'], array( 'email', 'tel' ), true ) ? $field['type'] : 'text';
					printf(
						'<label for="pxer_%1$s">%2$s</label><input type="%3$s" id="pxer_%1$s" name="pxer_data[%1$s]" value="%4$s">',
						esc_attr( $field['key'] ),
						esc_html( $field['label'] ),
						esc_attr( $itype ),
						esc_attr( (string) $value )
					);
				}
				echo '</div>';
			}
			?>
		</div>
		<?php
	}

	public function render_items_metabox( \WP_Post $post ): void {
		$request     = pxer_get_request( $post->ID );
		$data        = $request->get_data();
		$order       = $request->get_order();
		$order_items = $order ? $order->get_items() : array();
		$req_items   = $data['items'] ?? array();

		if ( ! $order || empty( $order_items ) ) {
			echo '<p>' . esc_html__( 'Order not found or has no items.', 'px-wc-requests' ) . '</p>';

			return;
		}
		?>
		<table class="widefat striped">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Product', 'px-wc-requests' ); ?></th>
				<th><?php esc_html_e( 'Quantity', 'px-wc-requests' ); ?></th>
				<th><?php esc_html_e( 'Reason / description', 'px-wc-requests' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $order_items as $item_id => $item ) :
				$row    = $req_items[ $item_id ] ?? array();
				$qty    = $row['quantity'] ?? 0;
				$reason = $row['reason'] ?? '';
				?>
				<tr>
					<td><strong><?php echo esc_html( $item->get_name() ); ?></strong><br>
						<small><?php esc_html_e( 'Max:', 'px-wc-requests' ); ?> <?php echo esc_html( $item->get_quantity() ); ?></small>
					</td>
					<td><input type="number" min="0" max="<?php echo esc_attr( $item->get_quantity() ); ?>"
					           name="pxer_data[items][<?php echo esc_attr( $item_id ); ?>][quantity]" value="<?php echo esc_attr( $qty ); ?>" style="width:70px"></td>
					<td><textarea name="pxer_data[items][<?php echo esc_attr( $item_id ); ?>][reason]" rows="2" style="width:100%"><?php echo esc_textarea( $reason ); ?></textarea></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	public function render_images_metabox( \WP_Post $post ): void {
		$images = pxer_get_request( $post->ID )->get_images();
		if ( empty( $images ) ) {
			echo '<p>' . esc_html__( 'No photos', 'px-wc-requests' ) . '</p>';

			return;
		}
		echo '<div style="display:flex;flex-wrap:wrap;gap:10px">';
		foreach ( $images as $image_id ) {
			// Private attachments are served through the gated viewer; others
			// fall back to the normal media URL.
			if ( PrivateFiles::is_private( (int) $image_id ) ) {
				$url = PrivateFiles::view_url( (int) $image_id );
			} else {
				$url = wp_get_attachment_url( $image_id );
			}
			if ( $url ) {
				printf(
					'<a href="%1$s" target="_blank"><img src="%1$s" alt="" style="max-width:100px;height:auto"></a>',
					esc_url( $url )
				);
			}
		}
		echo '</div>';
	}

	public function save( int $post_id ): void {
		if ( ! isset( $_POST['pxer_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pxer_meta_nonce'], 'pxer_save_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// Only rebuild the data when the details metabox was actually submitted —
		// otherwise a save that omits it (e.g. status-only change) would wipe the
		// stored fields.
		if ( ! isset( $_POST['pxer_data'] ) ) {
			return;
		}

		$request = pxer_get_request( $post_id );
		$type    = $request->get_type();
		$fields  = RequestTypes::get_fields( $type );
		$data    = $request->get_data();
		$raw     = (array) wp_unslash( $_POST['pxer_data'] );

		foreach ( $fields as $field ) {
			if ( 'order_items' === $field['type'] ) {
				$items = array();
				foreach ( (array) ( $raw['items'] ?? array() ) as $item_id => $item ) {
					$qty = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;
					if ( $qty > 0 ) {
						$items[ absint( $item_id ) ] = array(
							'quantity' => $qty,
							'reason'   => isset( $item['reason'] ) ? sanitize_textarea_field( $item['reason'] ) : '',
						);
					}
				}
				$data['items'] = $items;
				continue;
			}
			if ( 'file' === $field['type'] ) {
				continue;
			}
			$data[ $field['key'] ] = FieldSchema::sanitize_value( $field, $raw[ $field['key'] ] ?? '' );
		}

		if ( isset( $_POST['pxer_order_id'] ) ) {
			update_post_meta( $post_id, '_pxer_order_id', absint( $_POST['pxer_order_id'] ) );
		}
		update_post_meta( $post_id, '_pxer_data', $data );
	}
}
