<?php
/**
 * Request notes — WooCommerce-order-note style history for requests.
 *
 * Notes are stored as WordPress comments of type `pxer_request_note` attached
 * to the request post. Customer notes carry the `is_customer_note` meta and
 * trigger an email to the customer. Notes are excluded from regular comment
 * queries so they never leak to the front-end or the Comments screen.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class RequestNotes {

	const COMMENT_TYPE = 'pxer_request_note';
	const NONCE        = 'pxer_request_note';

	/** Bypass flag so our own queries can read the notes. */
	private static bool $querying = false;

	public function setup(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ), 40 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_pxer_add_request_note', array( $this, 'ajax_add' ) );
		add_action( 'wp_ajax_pxer_delete_request_note', array( $this, 'ajax_delete' ) );

		// Log status changes as internal notes (like WooCommerce order notes).
		add_action( 'transition_post_status', array( $this, 'log_status_change' ), 10, 3 );

		// Hide our notes from comment queries / counts.
		add_filter( 'comments_clauses', array( __CLASS__, 'exclude_notes' ) );
		add_filter( 'comment_feed_where', array( __CLASS__, 'exclude_notes_feed' ) );
	}

	// =====================================================================
	// Data API (mirrors WC_Order::add_order_note / wc_get_order_notes)
	// =====================================================================

	/**
	 * Add a note to a request.
	 *
	 * @return int Comment ID (0 on failure).
	 */
	public static function add_note( int $request_id, string $note, bool $is_customer_note = false ): int {
		if ( ! $request_id || '' === trim( $note ) ) {
			return 0;
		}

		if ( is_user_logged_in() && current_user_can( 'edit_post', $request_id ) ) {
			$user                  = wp_get_current_user();
			$author                = $user->display_name;
			$author_email          = $user->user_email;
		} else {
			$author       = __( 'System', 'px-wc-requests' );
			$author_email = 'noreply@' . wp_parse_url( home_url(), PHP_URL_HOST );
		}

		$comment_id = wp_insert_comment( array(
			'comment_post_ID'      => $request_id,
			'comment_author'       => $author,
			'comment_author_email' => $author_email,
			'comment_content'      => $note,
			'comment_type'         => self::COMMENT_TYPE,
			'comment_parent'       => 0,
			'comment_approved'     => 1,
			'comment_agent'        => 'Pixeler Woo Requests',
		) );

		if ( ! $comment_id ) {
			return 0;
		}

		if ( $is_customer_note ) {
			add_comment_meta( $comment_id, 'is_customer_note', 1 );

			/**
			 * Fires when a customer note is added — used to email the customer.
			 *
			 * @param int    $request_id
			 * @param string $note
			 */
			do_action( 'pxer_request_customer_note_notification', $request_id, $note );
		}

		do_action( 'pxer_request_note_added', $comment_id, $request_id, $is_customer_note );

		return (int) $comment_id;
	}

	/**
	 * Get the notes of a request, newest first.
	 *
	 * @param bool $customer_visible When true, return only entries the customer
	 *                               may see: notes addressed to them and status
	 *                               change logs (never private admin notes).
	 *
	 * @return \WP_Comment[]
	 */
	public static function get_notes( int $request_id, bool $customer_visible = false ): array {
		$args = array(
			'post_id' => $request_id,
			'orderby' => 'comment_ID',
			'order'   => 'DESC',
			'approve' => 'approve',
			'type'    => self::COMMENT_TYPE,
		);

		if ( $customer_visible ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array( 'key' => 'is_customer_note', 'compare' => 'EXISTS' ),
				array( 'key' => '_pxer_status_log', 'compare' => 'EXISTS' ),
			);
		}

		self::$querying = true;
		$notes = get_comments( $args );
		self::$querying = false;

		return $notes;
	}

	public static function is_status_log( int $note_id ): bool {
		return (bool) get_comment_meta( $note_id, '_pxer_status_log', true );
	}

	public static function delete_note( int $note_id ): bool {
		return (bool) wp_delete_comment( $note_id, true );
	}

	/**
	 * Record an internal note whenever a request changes status.
	 */
	public function log_status_change( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( RequestPostType::POST_TYPE !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		$statuses = RequestTypes::all_statuses();
		if ( ! isset( $statuses[ $new_status ] ) ) {
			return; // Not a request status (e.g. trash, draft) — ignore.
		}

		if ( isset( $statuses[ $old_status ] ) ) {
			$note = sprintf(
				/* translators: 1: old status, 2: new status */
				__( 'Status changed from %1$s to %2$s.', 'px-wc-requests' ),
				RequestTypes::status_label( $old_status ),
				RequestTypes::status_label( $new_status )
			);
		} else {
			$note = sprintf(
				/* translators: %s: status */
				__( 'Request created with status: %s', 'px-wc-requests' ),
				RequestTypes::status_label( $new_status )
			);
		}

		$note_id = self::add_note( (int) $post->ID, $note, false );
		if ( $note_id ) {
			add_comment_meta( $note_id, '_pxer_status_log', 1 );
		}
	}

	public static function is_customer_note( int $note_id ): bool {
		return (bool) get_comment_meta( $note_id, 'is_customer_note', true );
	}

	// =====================================================================
	// Query exclusion
	// =====================================================================

	public static function exclude_notes( array $clauses ): array {
		if ( self::$querying ) {
			return $clauses;
		}
		global $wpdb;
		$clauses['where'] .= ( $clauses['where'] ? ' AND ' : '' ) . $wpdb->comments . ".comment_type != '" . self::COMMENT_TYPE . "'";

		return $clauses;
	}

	public static function exclude_notes_feed( string $where ): string {
		global $wpdb;

		return $where . ( $where ? ' AND ' : '' ) . $wpdb->comments . ".comment_type != '" . self::COMMENT_TYPE . "'";
	}

	// =====================================================================
	// Admin metabox
	// =====================================================================

	public function register_metabox(): void {
		add_meta_box(
			'pxer_notes',
			__( 'Request notes', 'px-wc-requests' ),
			array( $this, 'render_metabox' ),
			RequestPostType::POST_TYPE,
			'side',
			'default'
		);
	}

	public function render_metabox( \WP_Post $post ): void {
		$notes = self::get_notes( $post->ID );
		?>
		<ul class="pxer-notes">
			<?php
			if ( $notes ) {
				foreach ( $notes as $note ) {
					echo self::render_note( $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			} else {
				echo '<li class="pxer-note pxer-note-empty">' . esc_html__( 'There are no notes yet.', 'px-wc-requests' ) . '</li>';
			}
			?>
		</ul>

		<div class="pxer-add-note">
			<p>
				<label for="pxer-note-content"><?php esc_html_e( 'Add note', 'px-wc-requests' ); ?></label>
				<textarea id="pxer-note-content" rows="4" style="width:100%"></textarea>
			</p>
			<p>
				<select id="pxer-note-type">
					<option value="private"><?php esc_html_e( 'Private note', 'px-wc-requests' ); ?></option>
					<option value="customer"><?php esc_html_e( 'Note to customer', 'px-wc-requests' ); ?></option>
				</select>
				<button type="button" class="button" id="pxer-add-note-btn"><?php esc_html_e( 'Add', 'px-wc-requests' ); ?></button>
			</p>
			<?php wp_nonce_field( self::NONCE, 'pxer_note_nonce' ); ?>
		</div>
		<?php
	}

	/**
	 * Render one note as an <li> (shared by metabox + AJAX response).
	 */
	public static function render_note( \WP_Comment $note ): string {
		$is_customer = self::is_customer_note( (int) $note->comment_ID );
		$classes     = array( 'pxer-note' );
		if ( $is_customer ) {
			$classes[] = 'pxer-note-customer';
		}

		ob_start();
		?>
		<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-note-id="<?php echo esc_attr( $note->comment_ID ); ?>">
			<div class="pxer-note-content"><?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?></div>
			<p class="pxer-note-meta">
				<abbr class="exact-date">
					<?php
					printf(
						/* translators: 1: date, 2: time, 3: author */
						esc_html__( 'added on %1$s at %2$s by %3$s', 'px-wc-requests' ),
						esc_html( get_comment_date( wc_date_format(), $note->comment_ID ) ),
						esc_html( get_comment_date( wc_time_format(), $note->comment_ID ) ),
						esc_html( $note->comment_author )
					);
					?>
				</abbr>
				<?php if ( $is_customer ) : ?>
					<span class="pxer-note-badge"><?php esc_html_e( 'note to customer', 'px-wc-requests' ); ?></span>
				<?php endif; ?>
				<a href="#" class="pxer-delete-note" role="button"><?php esc_html_e( 'Delete', 'px-wc-requests' ); ?></a>
			</p>
		</li>
		<?php
		return ob_get_clean();
	}

	// =====================================================================
	// AJAX
	// =====================================================================

	public function ajax_add(): void {
		check_ajax_referer( self::NONCE, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error();
		}

		$note     = isset( $_POST['note'] ) ? wp_kses_post( trim( wp_unslash( $_POST['note'] ) ) ) : '';
		$customer = isset( $_POST['note_type'] ) && 'customer' === $_POST['note_type'];

		if ( '' === $note ) {
			wp_send_json_error();
		}

		$note_id = self::add_note( $post_id, $note, $customer );
		if ( ! $note_id ) {
			wp_send_json_error();
		}

		wp_send_json_success( array( 'html' => self::render_note( get_comment( $note_id ) ) ) );
	}

	public function ajax_delete(): void {
		check_ajax_referer( self::NONCE, 'nonce' );

		$note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0;
		$comment = $note_id ? get_comment( $note_id ) : null;

		if ( ! $comment || self::COMMENT_TYPE !== $comment->comment_type || ! current_user_can( 'edit_post', (int) $comment->comment_post_ID ) ) {
			wp_send_json_error();
		}

		self::delete_note( $note_id );
		wp_send_json_success();
	}

	// =====================================================================
	// Assets
	// =====================================================================

	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		if ( get_post_type() !== RequestPostType::POST_TYPE ) {
			return;
		}

		wp_enqueue_style( 'pxer-admin-notes', PXER_URL . 'assets/admin-notes.css', array(), PXER_VERSION );
		wp_enqueue_script( 'pxer-admin-notes', PXER_URL . 'assets/admin-notes.js', array( 'jquery' ), PXER_VERSION, true );
		wp_localize_script( 'pxer-admin-notes', 'pxer_notes', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::NONCE ),
			'post_id'  => get_the_ID(),
			'i18n'     => array(
				'confirm_delete' => __( 'Delete this note?', 'px-wc-requests' ),
			),
		) );
	}
}
