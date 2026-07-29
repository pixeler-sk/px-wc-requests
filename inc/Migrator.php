<?php
/**
 * One-way data migrator: WPify Woo (WithdrawalClaims) → Pixeler Woo Requests.
 *
 * Non-destructive (reads the WPify custom table only), idempotent (each imported
 * request stores `_pxer_migrated_from = {wpify_id}` and is skipped on re-run),
 * batched via AJAX with a dry-run mode. Admin tool under WooCommerce menu.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class Migrator {

	const META_SOURCE = '_pxer_migrated_from';
	const NONCE       = 'pxer_migrate';
	const PAGE        = 'pxer-migrate';
	const BATCH       = 50;

	public function setup(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_pxer_migrate_scan', array( $this, 'ajax_scan' ) );
		add_action( 'wp_ajax_pxer_migrate_batch', array( $this, 'ajax_batch' ) );

		// Surface the importer link inside the Customer requests settings tab.
		add_filter( 'pxer_settings', array( $this, 'add_settings_link' ) );
	}

	/**
	 * Is the source plugin (WPify Woo) currently active? The importer link is
	 * only shown when it is — there is nothing to migrate otherwise.
	 */
	public static function is_wpify_active(): bool {
		return function_exists( 'wpify_woo' ) || class_exists( '\\WpifyWoo\\Plugin' );
	}

	/**
	 * Append an "Import from WPify" section with a link to the (hidden) importer
	 * page. Only when WPify Woo is active.
	 *
	 * @param array $settings
	 *
	 * @return array
	 */
	public function add_settings_link( array $settings ): array {
		if ( ! self::is_wpify_active() ) {
			return $settings;
		}

		$url = admin_url( 'admin.php?page=' . self::PAGE );

		$settings[] = array(
			'title' => __( 'Import from WPify', 'px-wc-requests' ),
			'type'  => 'title',
			'desc'  => sprintf(
				/* translators: %s: button link to the importer */
				__( 'WPify Woo is active. Import its existing requests: %s', 'px-wc-requests' ),
				'<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Open importer', 'px-wc-requests' ) . '</a>'
			),
			'id'    => 'pxer_section_import',
		);
		$settings[] = array( 'type' => 'sectionend', 'id' => 'pxer_section_import' );

		return $settings;
	}

	// =====================================================================
	// Source table
	// =====================================================================

	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpify_woo_requests';
	}

	private function table_exists(): bool {
		global $wpdb;
		$table = $this->table();

		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	private function count_total(): int {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return 0;
		}

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table() ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	private function count_migrated(): int {
		$ids = get_posts( array(
			'post_type'   => RequestPostType::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => self::META_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery
		) );

		return count( $ids );
	}

	// =====================================================================
	// Admin page
	// =====================================================================

	public function register_menu(): void {
		// Only register the importer when there is a source to import from.
		if ( ! self::is_wpify_active() ) {
			return;
		}

		add_submenu_page(
			'woocommerce',
			__( 'Import requests from WPify', 'px-wc-requests' ),
			__( 'Import from WPify', 'px-wc-requests' ),
			'manage_woocommerce',
			self::PAGE,
			array( $this, 'render_page' )
		);

		// Keep the page reachable (via the settings-tab link / direct URL) but
		// hide it from the WooCommerce submenu — it now lives under Settings.
		remove_submenu_page( 'woocommerce', self::PAGE );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$exists = $this->table_exists();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import requests from WPify', 'px-wc-requests' ); ?></h1>

			<?php if ( ! $exists ) : ?>
				<div class="notice notice-warning"><p>
					<?php
					/* translators: %s: table name */
					printf( esc_html__( 'No WPify data found (table %s does not exist).', 'px-wc-requests' ), '<code>' . esc_html( $this->table() ) . '</code>' );
					?>
				</p></div>
				<?php return; ?>
			<?php endif; ?>

			<p><?php esc_html_e( 'This is non-destructive and can be run repeatedly — already imported requests are skipped. Customers are NOT e-mailed.', 'px-wc-requests' ); ?></p>

			<table class="form-table"><tbody>
				<tr>
					<th><?php esc_html_e( 'Total requests in WPify', 'px-wc-requests' ); ?></th>
					<td id="pxer-stat-total"><?php echo esc_html( $this->count_total() ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Already imported', 'px-wc-requests' ); ?></th>
					<td id="pxer-stat-migrated"><?php echo esc_html( $this->count_migrated() ); ?></td>
				</tr>
				<tr>
					<th><label for="pxer-migrate-status"><?php esc_html_e( 'Import as status', 'px-wc-requests' ); ?></label></th>
					<td>
						<select id="pxer-migrate-status">
							<?php foreach ( RequestTypes::all_statuses() as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( 'pxer_received', $slug ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody></table>

			<?php wp_nonce_field( self::NONCE, 'pxer_migrate_nonce' ); ?>
			<p>
				<button type="button" class="button" id="pxer-migrate-dry"><?php esc_html_e( 'Dry run', 'px-wc-requests' ); ?></button>
				<button type="button" class="button button-primary" id="pxer-migrate-run"><?php esc_html_e( 'Import', 'px-wc-requests' ); ?></button>
				<span id="pxer-migrate-bar" style="margin-left:10px;font-weight:600"></span>
			</p>
			<div id="pxer-migrate-log" style="max-height:300px;overflow:auto;background:#fff;border:1px solid #ddd;padding:8px"></div>
		</div>
		<?php
	}

	public function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_script( 'pxer-admin-migrate', PXER_URL . 'assets/admin-migrate.js', array( 'jquery' ), PXER_VERSION, true );
		wp_localize_script( 'pxer-admin-migrate', 'pxer_migrate', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::NONCE ),
			'i18n'     => array(
				'dry_done'     => __( 'Dry-run finished', 'px-wc-requests' ),
				'import_done'  => __( 'Import finished', 'px-wc-requests' ),
				'imported'     => __( 'imported', 'px-wc-requests' ),
				'skipped'      => __( 'skipped', 'px-wc-requests' ),
				'errors'       => __( 'errors', 'px-wc-requests' ),
				'failed'       => __( 'Request failed.', 'px-wc-requests' ),
			),
		) );
	}

	// =====================================================================
	// AJAX
	// =====================================================================

	private function guard(): void {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'px-wc-requests' ) ), 403 );
		}
	}

	public function ajax_scan(): void {
		$this->guard();
		$total    = $this->count_total();
		$migrated = $this->count_migrated();
		wp_send_json_success( array(
			'total'     => $total,
			'migrated'  => $migrated,
			'to_import' => max( 0, $total - $migrated ),
		) );
	}

	public function ajax_batch(): void {
		$this->guard();

		$offset  = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$dry_run = ! empty( $_POST['dry_run'] );
		$status  = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'pxer_received';
		if ( ! isset( RequestTypes::all_statuses()[ $status ] ) ) {
			$status = 'pxer_received';
		}

		$total = $this->count_total();
		$rows  = $this->fetch_rows( $offset, self::BATCH );

		$imported = 0;
		$skipped  = 0;
		$errors   = array();

		foreach ( $rows as $row ) {
			$result = $this->import_row( $row, $status, $dry_run );
			if ( is_wp_error( $result ) ) {
				/* translators: 1: wpify id, 2: error */
				$errors[] = sprintf( __( 'Row #%1$s: %2$s', 'px-wc-requests' ), $row->id, $result->get_error_message() );
			} elseif ( 'skipped' === $result ) {
				$skipped++;
			} else {
				$imported++;
			}
		}

		$processed   = count( $rows );
		$next_offset = $offset + $processed;
		$done        = $processed < self::BATCH || $next_offset >= $total;

		wp_send_json_success( array(
			'imported'     => $imported,
			'skipped'      => $skipped,
			'errors'       => $errors,
			'errors_count' => count( $errors ),
			'next_offset'  => $next_offset,
			'done'         => $done,
			'progress'     => $total > 0 ? min( 100, (int) round( $next_offset / $total * 100 ) ) : 100,
		) );
	}

	// =====================================================================
	// Import logic
	// =====================================================================

	/**
	 * @return object[]
	 */
	private function fetch_rows( int $offset, int $limit ): array {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . $this->table() . ' ORDER BY id ASC LIMIT %d OFFSET %d',
			$limit,
			$offset
		) );
	}

	private function already_imported( int $source_id ): bool {
		$existing = get_posts( array(
			'post_type'   => RequestPostType::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => self::META_SOURCE,   // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $source_id,          // phpcs:ignore WordPress.DB.SlowDBQuery
		) );

		return ! empty( $existing );
	}

	/**
	 * @return string|\WP_Error  'imported' | 'skipped' | WP_Error
	 */
	private function import_row( object $row, string $status, bool $dry_run ) {
		$source_id = (int) ( $row->id ?? 0 );
		if ( ! $source_id ) {
			return new \WP_Error( 'no_id', __( 'Missing source id.', 'px-wc-requests' ) );
		}

		$type = (string) ( $row->request_type ?? '' );
		if ( ! RequestTypes::exists( $type ) ) {
			/* translators: %s: type */
			return new \WP_Error( 'type', sprintf( __( 'Unknown request type: %s', 'px-wc-requests' ), $type ) );
		}

		if ( $this->already_imported( $source_id ) ) {
			return 'skipped';
		}

		if ( $dry_run ) {
			return 'imported'; // would import
		}

		$order_id = (int) ( $row->order_id ?? 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : null;
		$email    = (string) ( $row->customer_email ?? '' );

		// Name → first/last.
		$name  = trim( (string) ( $row->customer_name ?? '' ) );
		$parts = $name !== '' ? preg_split( '/\s+/', $name, 2 ) : array( '', '' );

		// Items.
		$reason  = (string) ( $row->reason ?? '' );
		$decoded = json_decode( (string) ( $row->items_json ?? '' ), true );
		$items   = array();
		if ( ( 'whole_order' === ( $row->scope ?? '' ) || empty( $decoded ) ) && $order ) {
			foreach ( $order->get_items() as $iid => $item ) {
				$items[ (int) $iid ] = array( 'quantity' => (int) $item->get_quantity(), 'reason' => $reason );
			}
		} elseif ( is_array( $decoded ) ) {
			foreach ( $decoded as $d ) {
				$iid = (int) ( $d['line_item_id'] ?? 0 );
				if ( $iid ) {
					$items[ $iid ] = array( 'quantity' => (int) ( $d['quantity'] ?? 1 ), 'reason' => $reason );
				}
			}
		}

		$data = array(
			'order_id'     => $order_id,
			'firstname'    => $parts[0] ?? '',
			'lastname'     => $parts[1] ?? '',
			'email'        => $email,
			'phone'        => '',
			'address'      => '',
			'postcode'     => '',
			'city'         => '',
			'account_name' => '',
			'iban'         => '',
			'items'        => $items,
		);

		$submitted = (string) ( $row->submitted_at ?? $row->created_at ?? '' );
		$label     = RequestTypes::get_label( $type );

		$post_id = wp_insert_post( array(
			'post_type'   => RequestPostType::POST_TYPE,
			'post_status' => $status,
			'post_title'  => $label,
			'post_date'   => $submitted ?: current_time( 'mysql' ),
			'meta_input'  => array(
				'_pxer_type'      => $type,
				'_pxer_order_id'  => $order_id,
				'_pxer_data'      => $data,
				'_pxer_email'     => $email,
				'_pxer_user_id'   => pxer_resolve_owner_id( $order, $email ),
				self::META_SOURCE => $source_id,
			),
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		wp_update_post( array(
			'ID'         => $post_id,
			/* translators: 1: type label, 2: id */
			'post_title' => sprintf( __( '%1$s no. %2$d', 'px-wc-requests' ), $label, $post_id ),
		) );

		RequestNotes::add_note(
			$post_id,
			sprintf(
				/* translators: %s: date */
				__( 'Imported from WPify (submitted %s).', 'px-wc-requests' ),
				$submitted ?: '—'
			),
			false
		);

		if ( $reason ) {
			RequestNotes::add_note( $post_id, $reason, false );
		}

		return 'imported';
	}
}
