<?php
/**
 * WooCommerce admin orders list — "Requests" column with one badge per bound
 * request (type + status) linking straight to the request edit screen. Active
 * requests are highlighted, closed ones are muted. Supports both the legacy
 * posts table and the HPOS orders table.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class OrderList {

	const COLUMN = 'pxer_requests';

	/**
	 * order_id => WP_Post[] map, built once per screen load so the list table
	 * doesn't run one meta query per order row.
	 *
	 * @var array<int,\WP_Post[]>|null
	 */
	private static ?array $map = null;

	public function setup(): void {
		if ( ! is_admin() ) {
			return;
		}

		// Legacy posts table.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_legacy_column' ), 10, 2 );

		// HPOS orders table.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'columns' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_hpos_column' ), 10, 2 );

		add_action( 'admin_head', array( $this, 'print_styles' ) );
	}

	/**
	 * Insert the column right after the order status.
	 */
	public function columns( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$out[ self::COLUMN ] = __( 'Requests', 'px-wc-requests' );
			}
		}
		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out[ self::COLUMN ] = __( 'Requests', 'px-wc-requests' );
		}

		return $out;
	}

	public function render_legacy_column( string $column, int $post_id ): void {
		if ( self::COLUMN === $column ) {
			$this->render_cell( $post_id );
		}
	}

	/**
	 * @param string          $column Column key.
	 * @param \WC_Order|mixed $order  Order object (WC passes it since 7.9).
	 */
	public function render_hpos_column( $column, $order ): void {
		if ( self::COLUMN === $column && $order instanceof \WC_Order ) {
			$this->render_cell( $order->get_id() );
		}
	}

	private function render_cell( int $order_id ): void {
		$requests = $this->requests_for_order( $order_id );
		if ( ! $requests ) {
			echo '<span class="pxer-order-requests-none">&ndash;</span>';

			return;
		}

		/**
		 * Statuses treated as closed (muted badge). Used as the colour
		 * fallback for custom statuses without their own CSS rule.
		 *
		 * @param string[] $statuses Post status slugs.
		 */
		$closed = apply_filters( 'pxer_order_list_closed_statuses', Eligibility::closed_statuses() );

		foreach ( $requests as $post ) {
			$request = pxer_get_request( $post->ID );
			$title   = $request->get_type_label() . ' — ' . $request->get_status_label();
			// Fallback class first, then the per-status class so a colour rule
			// for the concrete status wins over the generic active/closed one.
			$class   = in_array( $post->post_status, $closed, true ) ? 'is-closed' : 'is-active';
			$class  .= ' status-' . sanitize_html_class( $post->post_status );
			$link    = get_edit_post_link( $post->ID );

			if ( $link ) {
				printf(
					'<a class="pxer-order-request %1$s" href="%2$s" title="%3$s">#%4$d</a>',
					esc_attr( $class ),
					esc_url( $link ),
					esc_attr( $title ),
					(int) $post->ID
				);
			} else {
				printf(
					'<span class="pxer-order-request %1$s" title="%2$s">#%3$d</span>',
					esc_attr( $class ),
					esc_attr( $title ),
					(int) $post->ID
				);
			}
		}
	}

	/**
	 * All requests bound to an order, from a map built with a single query
	 * (get_posts primes the meta cache, so the per-request meta reads are free).
	 *
	 * @return \WP_Post[]
	 */
	private function requests_for_order( int $order_id ): array {
		if ( null === self::$map ) {
			self::$map = array();

			$posts = get_posts( array(
				'post_type'              => RequestPostType::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			) );

			foreach ( $posts as $post ) {
				$oid = (int) get_post_meta( $post->ID, '_pxer_order_id', true );
				if ( $oid ) {
					self::$map[ $oid ][] = $post;
				}
			}
		}

		return self::$map[ $order_id ] ?? array();
	}

	public function print_styles(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}
		?>
		<style>
			.column-pxer_requests { width:70px; }
			.pxer-order-request { display:inline-block; margin:1px 4px 3px 0; padding:2px 8px; border-radius:4px; font-size:12px; line-height:1.5; text-decoration:none; white-space:nowrap; }
			/* Colour = status, mirroring the WooCommerce order-status palette:
			   received ~ on-hold, in progress ~ processing, resolved ~ completed,
			   rejected ~ failed. Generic active/closed rules first as fallback
			   for custom statuses. */
			.pxer-order-request.is-active { background:#f8dda7; color:#94660c; }
			.pxer-order-request.is-closed { background:#e5e5e5; color:#777; }
			.pxer-order-request.status-pxer_received { background:#f8dda7; color:#94660c; }
			.pxer-order-request.status-pxer_in_progress { background:#c6e1c6; color:#5b841b; }
			.pxer-order-request.status-pxer_resolved { background:#c8d7e1; color:#2e4453; }
			.pxer-order-request.status-pxer_rejected { background:#eba3a3; color:#761919; }
			.pxer-order-request:hover { text-decoration:underline; }
			.pxer-order-requests-none { color:#a0a5aa; }
		</style>
		<?php
	}
}
